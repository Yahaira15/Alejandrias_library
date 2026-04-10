import json


def construir_prompt_recomendador(contexto):
    intereses = contexto.get("intereses") or []
    foros = contexto.get("foros") or []

    return f"""
Eres una IA educativa dentro de Alejandrias Library.

TAREA:
Recomendar foros relevantes.

REGLAS:
- Debes recomendar EXACTAMENTE 3 foros si existen al menos 3.
- No repitas foros.
- Prioriza coincidencias con intereses.
- Si un foro coincide directamente con un interes, asigna coincidencia \"alta\".
- Explica cada razon en maximo 15 palabras.
- Asigna un nivel a cada foro: basico, intermedio o avanzado.
- Responde SOLO con JSON valido.
- No uses markdown ni bloques de codigo.

INTERESES:
{json.dumps(intereses, ensure_ascii=False)}

FOROS DISPONIBLES:
{json.dumps(foros, ensure_ascii=False)}

FORMATO ESPERADO:
[
  {{
    \"titulo\": \"string\",
    \"nivel\": \"basico|intermedio|avanzado\",
    \"coincidencia\": \"alta|media|baja\",
    \"razon\": \"string\"
  }}
]
""".strip()


def construir_prompt_chat(contexto):
    historial = contexto.get("historial") or []
    mensaje = contexto.get("mensaje") or ""

    return f"""
Eres el asistente de Alejandrias Library.

OBJETIVO:
- Responder de forma clara, breve y util.
- Mantener un tono amable y educativo.
- Responder solo en texto plano.
- No uses markdown ni JSON.

HISTORIAL:
{json.dumps(historial, ensure_ascii=False)}

MENSAJE DEL USUARIO:
{json.dumps(mensaje, ensure_ascii=False)}
""".strip()
