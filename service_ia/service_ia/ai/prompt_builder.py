import json

from .task_router import obtener_modo_respuesta


PROMPT_BASE_RECOMENDADOR = """
Eres una IA educativa dentro de Alejandrias Library.

TAREA:
Recomendar foros relevantes.
""".strip()


PROMPT_BASE_CHAT = """
Eres un profesor experto y asistente educativo de Alejandrias Library.

OBJETIVO:
- Responder de forma clara, util y con contenido real.
- Explicar con mentalidad de profesor experto.
- Mantener un tono amable y educativo.
- Responder solo en texto plano.
- No uses markdown ni JSON.
- No uses frases genericas o vacias como "recibi tu mensaje" o "puedo ayudarte" sin desarrollar la respuesta.
""".strip()


def _serializar_historial(historial):
    if not historial:
        return "[]"

    historial_reducido = [
        {
            "rol": item.get("rol"),
            "texto": item.get("texto"),
        }
        for item in historial[-8:]
    ]
    return json.dumps(historial_reducido, ensure_ascii=False)


def construir_prompt_recomendador(contexto):
    intereses = contexto.get("intereses") or []
    foros = contexto.get("foros") or []

    return f"""
{PROMPT_BASE_RECOMENDADOR}

REGLAS:
- Debes recomendar EXACTAMENTE 3 foros si existen al menos 3.
- No repitas foros.
- Prioriza coincidencias con intereses.
- Si un foro coincide directamente con un interes, asigna coincidencia "alta".
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
    "titulo": "string",
    "nivel": "basico|intermedio|avanzado",
    "coincidencia": "alta|media|baja",
    "razon": "string"
  }}
]
""".strip()


def construir_prompt_chat(contexto):
    historial = contexto.get("historial") or []
    mensaje = contexto.get("mensaje") or ""
    intencion = contexto.get("intencion") or "general"
    modo_respuesta = obtener_modo_respuesta(contexto)
    foros = contexto.get("foros") or []
    bloque_foros = ""

    if foros:
        bloque_foros = f"""

FOROS EXISTENTES EN LA PLATAFORMA:
{json.dumps(foros[:12], ensure_ascii=False)}

REGLAS PARA FOROS:
- Si el usuario pide recomendaciones, usa solamente estos foros existentes.
- No inventes nombres de foros.
- Si propone crear uno nuevo, primero valida si ya existe uno parecido.
""".rstrip()

    return f"""
{PROMPT_BASE_CHAT}

ORQUESTACION:
- Piensa de forma interna antes de responder, pero no muestres ese razonamiento.
- Identifica la intencion principal del usuario y prioriza resolverla en esta respuesta.
- Usa el historial solo si aporta contexto real; no repitas informacion ya dicha.
- Si el usuario pide aprender un tema, explica de forma progresiva: definicion, contexto y ejemplo.
- Si faltan datos para una respuesta exacta, dilo con honestidad y ofrece el mejor siguiente paso.
- Evita respuestas vacias, genericas o de relleno.
- Prioriza resolver la pregunta del usuario por encima de saludar o rellenar.

ESTRATEGIA DE RESPUESTA:
- Intencion detectada: {intencion}
- Modo esperado: {modo_respuesta}
- Longitud objetivo: entre 1 y 4 parrafos cortos.
- Si es una explicacion, procura seguir este orden: idea principal, contexto, ejemplo.
- Cierra con una pregunta solo si ayuda de verdad a continuar.

HISTORIAL:
{_serializar_historial(historial)}

MENSAJE DEL USUARIO:
{json.dumps(mensaje, ensure_ascii=False)}

{bloque_foros}
""".strip()
