import json


def _limpiar_bloques_codigo(texto):
    if not texto.startswith("```"):
        return texto

    lineas = texto.splitlines()
    if len(lineas) >= 3 and lineas[-1].strip() == "```":
        return "\n".join(lineas[1:-1]).strip()

    return texto


def _extraer_json(texto):
    inicio_objeto = texto.find("{")
    inicio_lista = texto.find("[")

    indices_validos = [indice for indice in [inicio_objeto, inicio_lista] if indice != -1]
    if not indices_validos:
        return texto

    inicio = min(indices_validos)
    cierre = "}" if texto[inicio] == "{" else "]"
    fin = texto.rfind(cierre)

    if fin == -1 or fin < inicio:
        return texto

    return texto[inicio : fin + 1].strip()


def formatear_respuesta(texto):
    texto = (texto or "").strip()
    texto = _limpiar_bloques_codigo(texto)
    texto = _extraer_json(texto)

    try:
        return json.loads(texto)
    except Exception:
        return {
            "error": "Formato invalido",
            "raw": texto,
        }
