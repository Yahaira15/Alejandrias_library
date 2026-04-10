def obtener_tarea(tipo):
    tareas = {
        "recomendador": "recomendar_foros",
        "moderador": "moderar_contenido",
        "tutor": "explicar_tema",
        "chat": "chat_texto",
    }

    return tareas.get(tipo, "desconocido")
