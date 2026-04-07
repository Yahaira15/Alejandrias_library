from django.shortcuts import render
from .task_router import obtener_tarea
from .prompt_builder import construir_prompt
from .context_manager import construir_contexto
from .response_formatter import formatear_respuesta
from .gemini_client import model

@api_view(['POST'])
def ia_handler(request):

    tipo = request.data.get('tipo')
    data = request.data.get('data')

    tarea = obtener_tarea(tipo)
    contexto = construir_contexto(data)
    prompt = construir_prompt(tarea, contexto)

    respuesta = model.generate_content(prompt)

    resultado = formatear_respuesta(respuesta.text)

    return Response(resultado)