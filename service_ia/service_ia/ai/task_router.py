def obtener_tarea(tipo):
    tareas = {
        "recomendador": "recomendar_foros",
        "moderador": "moderar_contenido",
        "tutor": "explicar_tema",
        "chat": "chat_texto",
    }

    return tareas.get(tipo, "desconocido")


def obtener_modo_respuesta(contexto):
    intencion = (contexto or {}).get("intencion", "general")

    modos = {
        "recomendacion": "proponer opciones priorizadas y justificar la mejor",
        "explicacion": "explicar de menos a mas con ejemplo concreto",
        "resumen": "sintetizar ideas clave sin perder precision",
        "comparacion": "comparar criterios y cerrar con una conclusion util",
        "pregunta": "responder de forma directa y ampliar solo lo necesario",
        "general": "conversar con claridad y utilidad",
    }

    return modos.get(intencion, modos["general"])
