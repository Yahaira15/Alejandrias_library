import json


def formatear_respuesta(texto):
    texto = (texto or "").strip()

    if texto.startswith("```"):
        lineas = texto.splitlines()
        if len(lineas) >= 3 and lineas[-1].strip() == "```":
            texto = "\n".join(lineas[1:-1]).strip()

    try:
        return json.loads(texto)
    except Exception:
        return {
            "error": "Formato invalido",
            "raw": texto,
        }
