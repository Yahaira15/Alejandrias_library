def construir_contexto(data):
    data = data or {}
    contenido = data.get("data") if isinstance(data.get("data"), dict) else data

    return {
        "intereses": contenido.get("intereses") or [],
        "foros": contenido.get("foros") or [],
        "historial": contenido.get("historial", []),
    }
