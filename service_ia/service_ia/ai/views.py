import json
import logging
import os
import re
import time
import unicodedata

from django.http import JsonResponse
from django.views.decorators.csrf import csrf_exempt

from .chat_logic import (
    clasificar_peticion_foro,
    construir_respuesta_foros,
    es_peticion_de_foros,
    recomendar_foros,
)
from .gemini_client import AIProviderError, generar_texto
from .moderation import (
    construir_prompt_moderacion,
    extraer_json_moderacion,
    MODERATION_RESPONSE_SCHEMA,
    moderacion_respaldo,
    normalizar_resultado_moderacion,
)
from .orchestrator import preparar_ejecucion
from .prompt_builder import construir_prompt_recomendador
from .response_formatter import formatear_respuesta


logger = logging.getLogger(__name__)
MAX_INTENTOS_PARSE_MODELO = int(os.getenv("MODERATION_MODEL_PARSE_RETRIES", "2"))


def _normalizar_texto(valor):
    texto = unicodedata.normalize("NFKD", str(valor or ""))
    texto = "".join(caracter for caracter in texto if not unicodedata.combining(caracter))
    return " ".join(texto.lower().split())


def _es_peticion_gaming_especifica(mensaje):
    texto = _normalizar_texto(mensaje)
    if not texto:
        return False

    referencias_videojuegos = [
        r"\bvideojuego(s)?\b",
        r"\bjuego(s)?\b",
        r"\bgaming\b",
        r"\bpartida(s)?\b",
        r"\bmis(i|io)n(es)?\b",
        r"\bnivel(es)?\b",
        r"\bjefe(s)?\b|\bboss(es)?\b",
        r"\bskin(s)?\b",
        r"\bitem(s)?\b|\bobjeto(s)?\b",
        r"\barma(s)?\b",
        r"\bpersonaje(s)?\b",
        r"\bbuild(s)?\b",
        r"\bserver(s)?\b",
        r"\bfortnite\b|\bminecraft\b|\broblox\b|\bfree fire\b|\bvalorant\b|\blol\b|\bleague of legends\b|\bgta\b|\bzelda\b|\bpokemon\b|\bdark souls\b|\belden ring\b|\bgenshin\b|\bclash royale\b",
    ]
    solicitudes_operativas = [
        r"\bcomo\b.*\b(ganar|pasar|avanzar|subir|farmear|conseguir|obtener|desbloquear|completar|derrotar|matar|vencer)\b",
        r"\b(dime|dame|explica|recomienda|recomendame)\b.*\b(build|estrategia|truco|guia|ruta|pasos|mision|arma|objeto|nivel|desbloqueo|farming|farmear)\b",
        r"\b(mejor|mejores)\b.*\b(build|arma|armas|objeto|objetos|personaje|personajes|estrategia|equipo|clase)\b",
        r"\b(secretos?|logros?|codigos?|cheats?|trucos?|exploit(s)?|meta competitivo|tier list)\b",
        r"\b(progresion|farmeo|farming|farmear|desbloqueos?|misiones?|niveles?|objetos?|items?|armas?)\b",
    ]

    menciona_videojuego = any(re.search(patron, texto) for patron in referencias_videojuegos)
    pide_operacion = any(re.search(patron, texto) for patron in solicitudes_operativas)

    return menciona_videojuego and pide_operacion


def _respuesta_limite_videojuegos():
    return (
        "Puedo hablar de videojuegos solo de forma general, educativa, historica o cultural. "
        "Alejandrias es una plataforma educativa, no una guia gaming especializada, asi que no doy "
        "estrategias, misiones, builds, trucos, farming, objetos, niveles, desbloqueos ni pasos detallados."
    )


def _detalle_fallo_modelo(error):
    if isinstance(error, AIProviderError):
        if error.kind == "cuota_excedida":
            return (
                "Gemini rechazo la solicitud por cuota/rate limit. "
                "Se activo la respuesta de respaldo con datos locales."
            )
        if error.kind == "modelo_saturado":
            return (
                "Gemini esta temporalmente saturado (503). "
                "Se activo la respuesta de respaldo mientras se recupera."
            )
        if error.kind == "configuracion_api_key":
            return "Falta GEMINI_API_KEY en el entorno del servicio IA."
        if error.kind == "dependencia_faltante":
            return "Falta la dependencia google-genai en el entorno activo."
        return str(error)

    return "Fallo no clasificado del proveedor IA."


def _extraer_texto_foro(foro):
    if isinstance(foro, str):
        return foro.lower()

    return " ".join(
        str(valor)
        for valor in [
            foro.get("titulo"),
            foro.get("foro_titulo"),
            foro.get("descripcion"),
            foro.get("foro_descripcion"),
            foro.get("categoria"),
            foro.get("categoria_nombre"),
        ]
        if valor
    ).lower()


def _normalizar_titulo(foro, indice):
    if isinstance(foro, str):
        return foro

    return (
        foro.get("titulo")
        or foro.get("foro_titulo")
        or foro.get("nombre")
        or f"Foro {indice + 1}"
    )


def _respuesta_respaldo(contexto):
    intereses = [str(item).strip().lower() for item in (contexto.get("intereses") or []) if str(item).strip()]
    foros = contexto.get("foros") or []

    puntuados = []
    for indice, foro in enumerate(foros):
        texto = _extraer_texto_foro(foro)
        coincidencias = sum(1 for interes in intereses if interes in texto)

        if coincidencias >= 2:
            coincidencia = "alta"
        elif coincidencias == 1:
            coincidencia = "media"
        else:
            coincidencia = "baja"

        nivel = foro.get("nivel") if isinstance(foro, dict) else "intermedio"
        if nivel not in {"basico", "intermedio", "avanzado"}:
            nivel = "intermedio"

        razon = (
            "Coincide directamente con tus intereses."
            if coincidencia == "alta"
            else "Se relaciona parcialmente con lo que buscas."
            if coincidencia == "media"
            else "Puede ampliar tus temas de aprendizaje."
        )

        puntuados.append(
            {
                "orden": coincidencias,
                "item": {
                    "titulo": _normalizar_titulo(foro, indice),
                    "nivel": nivel,
                    "coincidencia": coincidencia,
                    "razon": razon,
                },
            }
        )

    puntuados.sort(key=lambda item: item["orden"], reverse=True)
    return [item["item"] for item in puntuados[:3]]


def _respuesta_chat_respaldo(contexto):
    mensaje = (contexto.get("mensaje") or "").strip()
    intencion = contexto.get("intencion") or "general"
    historial = contexto.get("historial") or []

    if not mensaje:
        return "Estoy listo para ayudarte. Escribe tu mensaje y seguimos."

    mensaje_min = mensaje.lower()
    historial_texto = " ".join(item.get("texto", "") for item in historial).lower()
    if _es_peticion_gaming_especifica(mensaje):
        return _respuesta_limite_videojuegos()

    if any(palabra in mensaje_min for palabra in ["hola", "buenas", "hey"]):
        return "Hola. Estoy listo para ayudarte con foros, estudio y dudas generales."

    if es_peticion_de_foros(contexto):
        return construir_respuesta_foros(contexto, contexto.get("foros") or [])

    if "titulo" in mensaje_min and "foro" in historial_texto:
        return construir_respuesta_foros(contexto, contexto.get("foros") or [])

    if intencion == "explicacion":
        return (
            f"{mensaje} se puede estudiar empezando por la idea central: que fue, por que importo "
            "y que consecuencias tuvo. Para hacerlo claro, separa el tema en tres partes: contexto, "
            "personajes o conceptos clave, y un ejemplo concreto. Asi la respuesta deja de ser solo "
            "un dato suelto y se vuelve una explicacion completa."
        )

    if intencion == "comparacion":
        return (
            "Puedo compararlo con criterios claros y una conclusion util. "
            "Si quieres, lo ordeno por diferencias, ventajas y cuando conviene cada opcion."
        )

    if any(palabra in mensaje_min for palabra in ["python", "laravel", "programacion"]):
        return "Parece un tema tecnico. Si quieres, te doy una explicacion base o una ruta de aprendizaje."

    if len(mensaje.split()) <= 4:
        return (
            f"Sobre {mensaje}, puedo darte una guia breve: primero define el concepto principal, "
            "luego ubicalo en su contexto y finalmente conecta la idea con un ejemplo. "
            "Si lo quieres para estudiar, conviene convertirlo en pregunta, por ejemplo: "
            f"que es {mensaje}, por que importa y que ejemplo lo representa."
        )

    return (
        f"Entendi que quieres trabajar sobre: {mensaje}. Una forma solida de avanzar es convertirlo "
        "en una respuesta con idea principal, contexto y ejemplo. Si es para un foro, tambien puedo "
        "proponerte titulo, descripcion, categoria y una pregunta inicial para abrir el debate."
    )


def _normalizar_recomendaciones_foros(respuesta, foros_disponibles):
    if not isinstance(respuesta, list):
        raise ValueError("Gemini no devolvio una lista JSON de recomendaciones.")

    foros_por_id = {
        str(foro.get("foro_id") or foro.get("id")): foro
        for foro in foros_disponibles
        if isinstance(foro, dict) and (foro.get("foro_id") or foro.get("id"))
    }
    recomendaciones = []
    vistos = set()

    for item in respuesta:
        if not isinstance(item, dict):
            continue

        foro_id = item.get("foro_id") or item.get("id")
        foro_key = str(foro_id)
        foro_real = foros_por_id.get(foro_key)
        if not foro_real or foro_key in vistos:
            continue

        coincidencia = str(item.get("coincidencia") or "media").lower()
        if coincidencia not in {"alta", "media", "baja"}:
            coincidencia = "media"

        razon = "Coincide con lo que buscas."
        if item.get("razon"):
            razon = " ".join(str(item["razon"]).split())[:220]

        recomendaciones.append(
            {
                "foro_id": foro_real.get("foro_id") or foro_real.get("id"),
                "titulo": foro_real.get("titulo") or item.get("titulo") or "Foro recomendado",
                "coincidencia": coincidencia,
                "razon": razon,
            }
        )
        vistos.add(foro_key)

        if len(recomendaciones) == 3:
            break

    if not recomendaciones:
        raise ValueError("Gemini no recomendo foros existentes del contexto real.")

    return recomendaciones


def _mensaje_recomendaciones_foros(recomendaciones):
    lineas = ["Estos foros reales pueden servirte:"]
    for indice, item in enumerate(recomendaciones, start=1):
        lineas.append(
            f"{indice}. {item['titulo']} ({item['coincidencia']}): {item['razon']}"
        )
    return "\n".join(lineas)


def _respuesta_recomendaciones_respaldo(contexto):
    recomendaciones = []
    foros = [foro for foro in contexto.get("foros") or [] if isinstance(foro, dict)]

    for foro_real in recomendar_foros(contexto, foros, limite=3):
        titulo = foro_real.get("titulo") or foro_real.get("foro_titulo")
        texto_foro = " ".join(
            str(foro_real.get(clave, ""))
            for clave in ["titulo", "descripcion", "categoria", "subcategoria"]
        ).lower()
        mensaje = str(contexto.get("mensaje") or "").lower()
        coincidencias = sum(1 for token in mensaje.split() if len(token) > 3 and token in texto_foro)
        coincidencia = "alta" if coincidencias >= 2 else "media" if coincidencias == 1 else "baja"
        razon = (
            "Coincide directamente con tu busqueda."
            if coincidencia == "alta"
            else "Se relaciona parcialmente con lo que buscas."
            if coincidencia == "media"
            else "Puede ampliar tus temas de aprendizaje."
        )
        recomendaciones.append(
            {
                "foro_id": foro_real.get("foro_id") or foro_real.get("id"),
                "titulo": titulo,
                "coincidencia": coincidencia,
                "razon": razon,
            }
        )

    return [item for item in recomendaciones if item.get("foro_id") and item.get("titulo")]


@csrf_exempt
def ia_view(request):
    if request.method == "GET":
        return JsonResponse(
            {
                "ok": True,
                "message": "IA endpoint funcionando",
                "methods": ["GET", "POST"],
            }
        )

    if request.method != "POST":
        return JsonResponse({"ok": False, "error": "Metodo no permitido"}, status=405)

    try:
        payload = json.loads(request.body.decode("utf-8") or "{}")
    except json.JSONDecodeError:
        return JsonResponse({"ok": False, "error": "JSON invalido"}, status=400)

    ejecucion = preparar_ejecucion(payload)
    tipo = ejecucion["tipo"]
    tarea = ejecucion["tarea"]

    if tarea != "recomendar_foros":
        return JsonResponse(
            {"ok": False, "error": f"Tarea no soportada: {tipo}"},
            status=400,
        )

    contexto = ejecucion["contexto"]
    prompt = ejecucion["prompt"]

    logger.info(
        "Recomendador IA iniciado",
        extra={
            "pregunta": contexto.get("mensaje"),
            "intereses": contexto.get("intereses"),
            "foros_recibidos": len(contexto.get("foros") or []),
        },
    )

    try:
        texto = generar_texto(prompt, response_mime_type="application/json")
        logger.info("Respuesta RAW Gemini recomendador:\n%s", texto)
        respuesta = _normalizar_recomendaciones_foros(
            formatear_respuesta(texto),
            contexto.get("foros") or [],
        )
        logger.info("Respuesta parseada recomendador: %s", respuesta)

        return JsonResponse(
            {
                "ok": True,
                "tipo": tipo,
                "origen": "modelo",
                "data": respuesta,
            },
            status=200,
        )
    except Exception as exc:
        logger.exception("Fallo la respuesta del modelo para tarea %s", tipo)
        return JsonResponse(
            {
                "ok": True,
                "tipo": tipo,
                "origen": "respaldo",
                "data": _respuesta_recomendaciones_respaldo(contexto),
                "detalle": _detalle_fallo_modelo(exc),
            },
            status=200,
        )


@csrf_exempt
def chat_view(request):
    if request.method == "GET":
        return JsonResponse(
            {
                "ok": True,
                "message": "Chat IA funcionando",
                "methods": ["GET", "POST"],
            }
        )

    if request.method != "POST":
        return JsonResponse({"ok": False, "error": "Metodo no permitido"}, status=405)

    try:
        payload = json.loads(request.body.decode("utf-8") or "{}")
    except json.JSONDecodeError:
        return JsonResponse({"ok": False, "error": "JSON invalido"}, status=400)

    ejecucion = preparar_ejecucion(payload)
    tipo = ejecucion["tipo"]
    tarea = ejecucion["tarea"]

    if tarea != "chat_texto":
        return JsonResponse({"ok": False, "error": f"Tarea no soportada: {tipo}"}, status=400)

    contexto = ejecucion["contexto"]

    if not contexto["mensaje"].strip():
        return JsonResponse({"ok": False, "error": "El mensaje es obligatorio"}, status=400)

    if _es_peticion_gaming_especifica(contexto["mensaje"]):
        return JsonResponse(
            {
                "ok": True,
                "tipo": tipo,
                "origen": "politica_videojuegos",
                "data": {
                    "mensaje": _respuesta_limite_videojuegos(),
                },
            },
            status=200,
        )

    tipo_peticion_foro = clasificar_peticion_foro(contexto) if es_peticion_de_foros(contexto) else None
    if tipo_peticion_foro == "recomendar":
        logger.info(
            "Chat IA solicita recomendacion de foros",
            extra={
                "pregunta": contexto.get("mensaje"),
                "foros_recibidos": len(contexto.get("foros") or []),
            },
        )
        try:
            prompt_recomendador = construir_prompt_recomendador(contexto)
            texto = generar_texto(prompt_recomendador, response_mime_type="application/json")
            logger.info("Respuesta RAW Gemini recomendador desde chat:\n%s", texto)
            recomendaciones = _normalizar_recomendaciones_foros(
                formatear_respuesta(texto),
                contexto.get("foros") or [],
            )
            logger.info("Respuesta parseada recomendador desde chat: %s", recomendaciones)

            return JsonResponse(
                {
                    "ok": True,
                    "tipo": tipo,
                    "origen": "modelo",
                    "data": {
                        "mensaje": _mensaje_recomendaciones_foros(recomendaciones),
                        "recomendaciones": recomendaciones,
                    },
                },
                status=200,
            )
        except Exception as exc:
            logger.exception("Fallo recomendador de foros desde chat para tipo %s", tipo)
            recomendaciones = _respuesta_recomendaciones_respaldo(contexto)
            return JsonResponse(
                {
                    "ok": True,
                    "tipo": tipo,
                    "origen": "respaldo",
                    "data": {
                        "mensaje": _mensaje_recomendaciones_foros(recomendaciones)
                        if recomendaciones
                        else _respuesta_chat_respaldo(contexto),
                        "recomendaciones": recomendaciones,
                    },
                    "detalle": _detalle_fallo_modelo(exc),
                },
                status=200,
            )

    prompt = ejecucion["prompt"]

    try:
        texto = generar_texto(prompt)
        return JsonResponse(
            {
                "ok": True,
                "tipo": tipo,
                "origen": "modelo",
                "data": {
                    "mensaje": texto.strip(),
                },
            },
            status=200,
        )
    except Exception as exc:
        logger.exception("Fallo la respuesta del chat IA para tipo %s", tipo)
        return JsonResponse(
            {
                "ok": True,
                "tipo": tipo,
                "origen": "respaldo",
                "data": {
                    "mensaje": _respuesta_chat_respaldo(contexto),
                },
                "detalle": _detalle_fallo_modelo(exc),
            },
            status=200,
        )


@csrf_exempt
def moderation_view(request):
    inicio_request = time.perf_counter()
    if request.method == "GET":
        return JsonResponse(
            {
                "ok": True,
                "message": "Moderacion IA funcionando",
                "methods": ["GET", "POST"],
            }
        )

    if request.method != "POST":
        return JsonResponse({"ok": False, "error": "Metodo no permitido"}, status=405)

    try:
        payload = json.loads(request.body.decode("utf-8") or "{}")
    except json.JSONDecodeError:
        logger.warning("Moderacion IA recibio JSON invalido desde Laravel")
        return JsonResponse({"ok": False, "error": "JSON invalido"}, status=400)

    prompt = construir_prompt_moderacion(payload)
    tipo_contenido = payload.get("tipo_contenido", "contenido")

    logger.info(
        "Moderacion IA iniciada",
        extra={
            "tipo_contenido": tipo_contenido,
            "payload_keys": sorted(payload.keys()),
            "contenido_keys": sorted((payload.get("contenido") or {}).keys()),
        },
    )
    logger.info("Prompt enviado a Gemini para moderacion:\n%s", prompt)

    ultimo_error = None
    texto = ""
    json_limpio = ""
    try:
        for intento in range(1, MAX_INTENTOS_PARSE_MODELO + 1):
            inicio_intento = time.perf_counter()
            try:
                logger.info("Invocando Gemini para moderacion, intento %s/%s", intento, MAX_INTENTOS_PARSE_MODELO)
                respuesta_modelo = generar_texto(
                    prompt,
                    response_mime_type="application/json",
                    response_json_schema=MODERATION_RESPONSE_SCHEMA,
                    return_metadata=True,
                )
                if isinstance(respuesta_modelo, dict):
                    texto = respuesta_modelo.get("text", "")
                    safety_metadata = respuesta_modelo.get("safety_metadata") or {}
                else:
                    texto = str(respuesta_modelo or "")
                    safety_metadata = {}
                logger.info("Respuesta RAW de Gemini para moderacion:\n%s", texto)
                logger.info("Safety ratings RAW Gemini/Vertex para moderacion: %s", safety_metadata)
                data, json_limpio = extraer_json_moderacion(texto)
                logger.info("JSON extraido antes del parse/normalizacion:\n%s", json_limpio)
                logger.info("Resultado JSON parseado de Gemini: %s", data)
                resultado = normalizar_resultado_moderacion(data, payload, safety_metadata)
                logger.info("Resultado normalizado de moderacion Gemini: %s", resultado)
                origen = "modelo"
                break
            except (json.JSONDecodeError, ValueError) as exc:
                ultimo_error = exc
                logger.warning(
                    "Respuesta Gemini no fue JSON recuperable en intento %s/%s tras %.3fs: %s",
                    intento,
                    MAX_INTENTOS_PARSE_MODELO,
                    time.perf_counter() - inicio_intento,
                    exc,
                    exc_info=True,
                )
                if intento >= MAX_INTENTOS_PARSE_MODELO:
                    raise
        else:
            raise ultimo_error or RuntimeError("Gemini no produjo resultado de moderacion.")
    except Exception as exc:
        logger.exception(
            "Fallo fatal de moderacion IA; punto exacto de fallback Django. raw=%r json_limpio=%r",
            texto,
            json_limpio,
        )
        resultado = moderacion_respaldo(payload)
        origen = "respaldo"
        resultado["detalle"] = _detalle_fallo_modelo(exc)

    duracion_total = time.perf_counter() - inicio_request
    logger.info(
        "Moderacion IA finalizada",
        extra={
            "tipo_contenido": tipo_contenido,
            "origen": origen,
            "estado": resultado.get("estado"),
            "riesgo": resultado.get("riesgo"),
            "categoria": resultado.get("categoria"),
            "duracion_segundos": round(duracion_total, 3),
        },
    )

    return JsonResponse(
        {
            "ok": True,
            "tipo": tipo_contenido,
            "origen": origen,
            "data": resultado,
            "debug": {
                "duracion_segundos": round(duracion_total, 3),
                "fallback": origen != "modelo",
            },
        },
        status=200,
    )
