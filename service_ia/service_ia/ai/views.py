import json

from django.http import JsonResponse
from django.views.decorators.csrf import csrf_exempt
from .task_router import obtener_tarea
from .prompt_builder import construir_prompt
from .context_manager import construir_contexto
from .response_formatter import formatear_respuesta
from .gemini_client import get_model

@csrf_exempt
def ia_handler(request):
    if request.method != 'POST':
        return JsonResponse({'error': 'Metodo no permitido'}, status=405)

    try:
        payload = json.loads(request.body or '{}')
    except json.JSONDecodeError:
        return JsonResponse({'error': 'JSON invalido'}, status=400)

    tipo = payload.get('tipo')
    data = payload.get('data')
    if not isinstance(data, dict):
        return JsonResponse({'error': 'El campo data debe ser un objeto JSON'}, status=400)

    tarea = obtener_tarea(tipo)
    if tarea == 'desconocido':
        return JsonResponse({'error': 'Tipo de tarea no soportado'}, status=400)

    contexto = construir_contexto(data)

    try:
        prompt = construir_prompt(tarea, contexto)
        respuesta = get_model().generate_content(prompt)
    except Exception as exc:
        return JsonResponse({'error': str(exc)}, status=500)

    resultado = formatear_respuesta(respuesta.text)

    return JsonResponse(resultado, safe=isinstance(resultado, dict))
