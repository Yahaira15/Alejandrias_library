import json
import logging

from django.http import JsonResponse
from django.views.decorators.csrf import csrf_exempt

from .chat_logic import construir_respuesta_foros, es_peticion_de_foros
from .gemini_client import AIProviderError, generar_texto
from .orchestrator import preparar_ejecucion
from .response_formatter import formatear_respuesta


logger = logging.getLogger(__name__)


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

    try:
        texto = generar_texto(prompt)
        respuesta = formatear_respuesta(texto)

        if isinstance(respuesta, dict) and respuesta.get("error"):
            raise ValueError("La IA no devolvio JSON valido")

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
                "data": _respuesta_respaldo(contexto),
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
