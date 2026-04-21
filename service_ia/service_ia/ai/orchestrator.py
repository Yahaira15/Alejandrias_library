from .chat_logic import es_peticion_de_foros
from .context_manager import construir_contexto
from .forum_repository import obtener_foros_existentes
from .prompt_builder import construir_prompt_chat, construir_prompt_recomendador
from .task_router import obtener_tarea


def preparar_ejecucion(payload):
    tipo = payload.get("tipo", "recomendador")
    tarea = obtener_tarea(tipo)
    contexto = construir_contexto(payload)

    debe_cargar_foros = tarea == "recomendar_foros" or (
        tarea == "chat_texto" and es_peticion_de_foros(contexto)
    )

    if debe_cargar_foros and not contexto.get("foros"):
        contexto["foros"] = obtener_foros_existentes()

    if tarea == "recomendar_foros":
        prompt = construir_prompt_recomendador(contexto)
    elif tarea == "chat_texto":
        prompt = construir_prompt_chat(contexto)
    else:
        prompt = ""

    return {
        "tipo": tipo,
        "tarea": tarea,
        "contexto": contexto,
        "prompt": prompt,
    }
