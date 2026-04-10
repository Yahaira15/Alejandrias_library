import json

from django.http import JsonResponse
from django.views.decorators.csrf import csrf_exempt
from .context_manager import construir_contexto
from .gemini_client import generar_texto
from .prompt_builder import construir_prompt_chat
from .prompt_builder import construir_prompt_recomendador
from .response_formatter import formatear_respuesta
from .task_router import obtener_tarea


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

    if not mensaje:
        return "Estoy listo para ayudarte. Escribe tu mensaje y seguimos."

    mensaje_min = mensaje.lower()

    if any(palabra in mensaje_min for palabra in ["hola", "buenas", "hey"]):
        return "Hola. Estoy listo para ayudarte con foros, estudio y dudas generales."

    if "foro" in mensaje_min:
        return "Puedo ayudarte a pensar titulos, descripciones y categorias para un foro."

    if any(palabra in mensaje_min for palabra in ["python", "laravel", "programacion"]):
        return "Parece un tema tecnico. Si quieres, te doy una explicacion base o una ruta de aprendizaje."

    return "Recibi tu mensaje. Puedo responder preguntas, orientar ideas y ayudarte con temas de estudio."


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

    tipo = payload.get("tipo", "recomendador")
    tarea = obtener_tarea(tipo)

    if tarea != "recomendar_foros":
        return JsonResponse(
            {"ok": False, "error": f"Tarea no soportada: {tipo}"},
            status=400,
        )

    contexto = construir_contexto(payload)
    prompt = construir_prompt_recomendador(contexto)

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
        return JsonResponse(
            {
                "ok": True,
                "tipo": tipo,
                "origen": "respaldo",
                "data": _respuesta_respaldo(contexto),
                "detalle": str(exc),
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

    tipo = payload.get("tipo", "chat")
    tarea = obtener_tarea(tipo)

    if tarea != "chat_texto":
        return JsonResponse({"ok": False, "error": f"Tarea no soportada: {tipo}"}, status=400)

    contexto_base = construir_contexto(payload)
    contenido = payload.get("data") if isinstance(payload.get("data"), dict) else payload
    contexto = {
        **contexto_base,
        "mensaje": contenido.get("mensaje", ""),
        "historial": contenido.get("historial", []),
    }

    if not contexto["mensaje"].strip():
        return JsonResponse({"ok": False, "error": "El mensaje es obligatorio"}, status=400)

    prompt = construir_prompt_chat(contexto)

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
        return JsonResponse(
            {
                "ok": True,
                "tipo": tipo,
                "origen": "respaldo",
                "data": {
                    "mensaje": _respuesta_chat_respaldo(contexto),
                },
                "detalle": str(exc),
            },
            status=200,
        )
