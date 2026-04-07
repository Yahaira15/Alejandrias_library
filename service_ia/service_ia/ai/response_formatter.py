import json

def formatear_respuesta(texto):
    try:
        return json.loads(texto)
    except:
        return {
            "error": "Formato inválido",
            "raw": texto
        }