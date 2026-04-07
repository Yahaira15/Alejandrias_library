def construir_prompt_recomendador(contexto):

    return f"""
    Eres una IA educativa dentro de Alejandría’s Library.

    TAREA:
    Recomendar foros relevantes.

    REGLAS:
    - Debes recomendar EXACTAMENTE 3 foros
    - No repitas foros
    - Prioriza coincidencias con intereses
    - Si un foro coincide directamente con un interés, asígnale coincidencia "alta" y priorízalo
    - Explica en máximo 15 palabras
    - Asigna un nivel a cada foro

    NIVELES DISPONIBLES:
    - básico
    - intermedio
    - avanzado

    COINCIDENCIA:
    - alta: coincide directamente con los intereses
    - media: relacionado indirectamente
    - baja: poco relacionado pero útil

    Si no hay intereses, recomienda foros populares.

    INTERESES:
    {contexto.get('intereses')}

    FOROS DISPONIBLES:
    {contexto.get('foros')}

    Responde SOLO en JSON válido:
    [
      {{
        "titulo": "...",
        "nivel": "...",
        "coincidencia": "...",
        "razon": "..."
      }}
    ]
    """