def construir_contexto(data):
    return {
        "intereses": data.get("intereses"),
        "foros": data.get("foros"),
        "historial": data.get("historial", [])
        #Agregar más funciones ("logros")
    }