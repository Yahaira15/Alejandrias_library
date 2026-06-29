import logging
import os
from pathlib import Path
import random
import time
import sys
import json
from urllib.error import HTTPError, URLError
from urllib.parse import quote
from urllib.request import Request, urlopen

from .env_loader import load_env

try:
    from google import genai
    from google.genai import errors as genai_errors
except ImportError:
    genai = None
    genai_errors = None


logger = logging.getLogger(__name__)

BASE_DIR = Path(__file__).resolve().parents[1]
PROJECT_ENV_PATH = BASE_DIR / ".env"
ROOT_ENV_PATH = BASE_DIR.parent / ".env"

load_env(PROJECT_ENV_PATH)
load_env(ROOT_ENV_PATH)

DEFAULT_MODEL = os.getenv("GEMINI_MODEL", "gemini-2.5-flash")
FALLBACK_MODELS = [
    modelo.strip()
    for modelo in os.getenv("GEMINI_FALLBACK_MODELS", "gemini-2.5-flash-lite,gemini-2.0-flash").split(",")
    if modelo.strip()
]
MAX_HISTORIAL = 5
MAX_REINTENTOS_MODELO = int(os.getenv("GEMINI_MAX_RETRIES", "3"))


class AIProviderError(RuntimeError):
    def __init__(self, kind, message, retryable=False):
        super().__init__(message)
        self.kind = kind
        self.retryable = retryable

SYSTEM_PROMPT = """
Eres un profesor experto dentro de Alejandrias Library.

Tu objetivo es ensenar de forma clara, profunda, estructurada y util.

Reglas obligatorias:
- Responde directamente la pregunta del usuario.
- Explica paso a paso cuando el tema lo requiera.
- Da contexto historico, tecnico o conceptual cuando aporte valor.
- Usa ejemplos claros y concretos.
- Adapta la dificultad al nivel implicito del usuario.
- Evita respuestas genericas, vacias o de relleno.
- No digas frases como "recibi tu mensaje" o "puedo ayudarte" sin contenido real.
- Si no tienes suficiente informacion, dilo con honestidad y ofrece el mejor siguiente paso.

Formato preferido:
1. Explicacion simple
2. Contexto
3. Ejemplo o aplicacion

Si la consulta es breve, puedes responder en 1 a 4 parrafos cortos sin mencionar los numeros.
""".strip()


def _obtener_api_key():
    return (
        os.getenv("GEMINI_API_KEY")
        or os.getenv("GOOGLE_API_KEY")
        or os.getenv("GEMINI:API_KEY")
    )


def _api_key_diagnostico(api_key):
    if not api_key:
        return {"presente": False, "longitud": 0, "terminacion": None}

    api_key = str(api_key)
    return {
        "presente": True,
        "longitud": len(api_key),
        "terminacion": api_key[-4:] if len(api_key) >= 4 else "***",
    }


def obtener_cliente():
    api_key = _obtener_api_key()
    vertex_project = os.getenv("VERTEX_AI_PROJECT") or os.getenv("GOOGLE_CLOUD_PROJECT")
    vertex_location = os.getenv("VERTEX_AI_LOCATION") or os.getenv("GOOGLE_CLOUD_LOCATION") or "us-central1"
    usar_vertex = os.getenv("GOOGLE_GENAI_USE_VERTEXAI", "").lower() in {"1", "true", "yes"}

    logger.info(
        "Validacion credenciales Gemini/Vertex: %s",
        {
            "api_key": _api_key_diagnostico(api_key),
            "usa_vertex_ai": usar_vertex,
            "vertex_project_presente": bool(vertex_project),
            "vertex_location": vertex_location if usar_vertex else None,
        },
    )

    if not api_key and not usar_vertex:
        raise AIProviderError(
            kind="configuracion_api_key",
            message="No se encontro GEMINI_API_KEY en el entorno del servicio IA.",
            retryable=False,
        )

    if genai is None:
        raise AIProviderError(
            kind="dependencia_faltante",
            message=f"Falta google-genai en {sys.executable}.",
            retryable=False,
        )

    if usar_vertex:
        if not vertex_project:
            raise AIProviderError(
                kind="configuracion_vertex",
                message="GOOGLE_GENAI_USE_VERTEXAI esta activo pero falta VERTEX_AI_PROJECT o GOOGLE_CLOUD_PROJECT.",
                retryable=False,
            )

        logger.info("Cliente Gemini inicializado en modo Vertex AI")
        return genai.Client(vertexai=True, project=vertex_project, location=vertex_location)

    logger.info("Cliente Gemini inicializado con API key")
    return genai.Client(api_key=api_key)


def _construir_config_rest(config):
    generation_config = {}

    if config.get("response_mime_type"):
        generation_config["responseMimeType"] = config["response_mime_type"]
    if config.get("response_json_schema"):
        generation_config["responseSchema"] = config["response_json_schema"]

    return generation_config


def _generar_con_rest(api_key, modelo, prompt_final, config):
    body = {
        "contents": [
            {
                "role": "user",
                "parts": [{"text": prompt_final}],
            }
        ],
    }

    generation_config = _construir_config_rest(config)
    if generation_config:
        body["generationConfig"] = generation_config

    url = (
        "https://generativelanguage.googleapis.com/v1beta/models/"
        f"{quote(modelo, safe='')}:generateContent"
    )
    request = Request(
        url,
        data=json.dumps(body).encode("utf-8"),
        headers={
            "Content-Type": "application/json",
            "x-goog-api-key": api_key,
        },
        method="POST",
    )

    try:
        with urlopen(request, timeout=60) as response:
            return json.loads(response.read().decode("utf-8"))
    except HTTPError as exc:
        detalle = exc.read().decode("utf-8", errors="replace")
        raise RuntimeError(f"{exc.code} {exc.reason}: {detalle}") from exc
    except URLError as exc:
        raise RuntimeError(str(exc)) from exc


def _extraer_texto_respuesta_rest(respuesta):
    partes = []
    for candidato in respuesta.get("candidates", []) or []:
        contenido = candidato.get("content") or {}
        for parte in contenido.get("parts", []) or []:
            texto = parte.get("text")
            if texto:
                partes.append(str(texto))

    return "\n".join(partes).strip()


def _extraer_metadata_seguridad_rest(respuesta):
    return {
        "prompt_feedback": respuesta.get("promptFeedback"),
        "candidates": [
            {
                "finish_reason": candidato.get("finishReason"),
                "safety_ratings": candidato.get("safetyRatings"),
            }
            for candidato in respuesta.get("candidates", []) or []
        ],
    }


def obtener_historial(user_id):
    return []


def guardar_historial(user_id, mensaje_usuario, respuesta_ia):
    return


def _extraer_texto_respuesta(respuesta):
    texto = getattr(respuesta, "text", None)
    if texto and str(texto).strip():
        return str(texto).strip()

    candidatos = []
    for atributo in ("output_text", "text"):
        valor = getattr(respuesta, atributo, None)
        if valor:
            candidatos.append(str(valor).strip())

    for candidato in candidatos:
        if candidato:
            return candidato

    return ""


def _serializar_valor(valor, profundidad=0):
    if profundidad > 4:
        return str(valor)
    if valor is None or isinstance(valor, (str, int, float, bool)):
        return valor
    if isinstance(valor, (list, tuple)):
        return [_serializar_valor(item, profundidad + 1) for item in valor]
    if isinstance(valor, dict):
        return {str(clave): _serializar_valor(item, profundidad + 1) for clave, item in valor.items()}

    resultado = {}
    for atributo in ("category", "probability", "probability_score", "severity", "severity_score", "blocked", "finish_reason"):
        if hasattr(valor, atributo):
            item = getattr(valor, atributo)
            resultado[atributo] = getattr(item, "name", item)

    if resultado:
        return resultado

    if hasattr(valor, "model_dump"):
        try:
            return _serializar_valor(valor.model_dump(), profundidad + 1)
        except Exception:
            pass

    return str(valor)


def _extraer_metadata_seguridad(respuesta):
    metadata = {
        "prompt_feedback": _serializar_valor(getattr(respuesta, "prompt_feedback", None)),
        "candidates": [],
    }

    for candidato in getattr(respuesta, "candidates", []) or []:
        metadata["candidates"].append(
            {
                "finish_reason": _serializar_valor(getattr(candidato, "finish_reason", None)),
                "safety_ratings": _serializar_valor(getattr(candidato, "safety_ratings", None)),
            }
        )

    return metadata


def construir_prompt_chat(mensaje_usuario, historial=None, instrucciones_extra=None):
    bloques = [SYSTEM_PROMPT]

    if instrucciones_extra:
        bloques.append("INSTRUCCIONES ADICIONALES:")
        bloques.append(str(instrucciones_extra).strip())

    historial = historial or []
    if historial:
        bloques.append("HISTORIAL RECIENTE:")
        for item in historial[-MAX_HISTORIAL:]:
            usuario = str(item.get("usuario", "")).strip()
            ia = str(item.get("ia", "")).strip()
            if usuario:
                bloques.append(f"Usuario: {usuario}")
            if ia:
                bloques.append(f"Asistente: {ia}")

    bloques.append("MENSAJE ACTUAL DEL USUARIO:")
    bloques.append(str(mensaje_usuario or "").strip())

    return "\n\n".join(bloque for bloque in bloques if bloque)


def _serializar_foros(foros):
    if not foros:
        return ""

    lineas = ["FOROS DISPONIBLES:"]
    for foro in foros:
        if not isinstance(foro, dict):
            continue

        titulo = str(foro.get("titulo") or foro.get("foro_titulo") or "Sin titulo").strip()
        descripcion = str(foro.get("descripcion") or foro.get("foro_descripcion") or "").strip()
        categoria = str(foro.get("categoria") or foro.get("categoria_nombre") or "").strip()

        detalle = titulo
        if categoria:
            detalle += f" | Categoria: {categoria}"
        if descripcion:
            detalle += f" | Descripcion: {descripcion}"

        lineas.append(f"- {detalle}")

    return "\n".join(lineas)


def generar_texto(
    prompt,
    user_id=None,
    mensaje_usuario=None,
    foros=None,
    response_mime_type=None,
    response_json_schema=None,
    return_metadata=False,
):
    prompt_limpio = str(prompt or "").strip()
    if not prompt_limpio:
        raise ValueError("El prompt no puede estar vacio")

    client = None
    api_key = _obtener_api_key()
    usar_rest = genai is None

    if usar_rest:
        if not api_key:
            raise AIProviderError(
                kind="configuracion_api_key",
                message="No se encontro GEMINI_API_KEY en el entorno del servicio IA.",
                retryable=False,
            )
        logger.warning("google-genai no esta disponible; usando Gemini REST con x-goog-api-key.")
    else:
        client = obtener_cliente()

    prompt_final = prompt_limpio

    if foros:
        prompt_final = f"{prompt_limpio}\n\n{_serializar_foros(foros)}"

    modelos = [DEFAULT_MODEL] + [modelo for modelo in FALLBACK_MODELS if modelo != DEFAULT_MODEL]
    ultimo_error = None
    respuesta = None

    logger.info(
        "Preparando solicitud Gemini",
        extra={
            "modelo_principal": DEFAULT_MODEL,
            "modelos_fallback": FALLBACK_MODELS,
            "prompt_caracteres": len(prompt_final),
            "max_reintentos_modelo": MAX_REINTENTOS_MODELO,
            "response_mime_type": response_mime_type,
            "usa_response_json_schema": bool(response_json_schema),
        },
    )
    logger.info("Prompt final enviado a Gemini:\n%s", prompt_final)

    config = {}
    if response_mime_type:
        config["response_mime_type"] = response_mime_type
    if response_json_schema:
        config["response_json_schema"] = response_json_schema

    for modelo in modelos:
        for intento in range(1, MAX_REINTENTOS_MODELO + 1):
            try:
                inicio = time.perf_counter()
                kwargs = {
                    "model": modelo,
                    "contents": prompt_final,
                }
                if config:
                    kwargs["config"] = config

                if usar_rest:
                    respuesta = _generar_con_rest(api_key, modelo, prompt_final, config)
                else:
                    respuesta = client.models.generate_content(**kwargs)
                logger.info(
                    "Respuesta Gemini recibida para modelo %s en intento %s tras %.3fs",
                    modelo,
                    intento,
                    time.perf_counter() - inicio,
                )
                break
            except Exception as exc:
                ultimo_error = exc
                es_error_503 = (
                    genai_errors is not None
                    and isinstance(exc, getattr(genai_errors, "ServerError", tuple()))
                    and "503" in str(exc)
                )
                ultimo_intento = intento >= MAX_REINTENTOS_MODELO
                if not es_error_503 or ultimo_intento:
                    logger.warning(
                        "Fallo Gemini con modelo %s en intento %s: %s",
                        modelo,
                        intento,
                        exc,
                        exc_info=True,
                    )
                    break

                # Retroceso corto para absorber picos de demanda temporales.
                espera = 0.8 * (2 ** (intento - 1)) + random.uniform(0, 0.4)
                logger.warning(
                    "Gemini saturado (503) para modelo %s. Reintentando en %.2fs (intento %s/%s).",
                    modelo,
                    espera,
                    intento + 1,
                    MAX_REINTENTOS_MODELO,
                )
                time.sleep(espera)

        if respuesta is not None:
            break

    if respuesta is None:
        error_texto = str(ultimo_error or "")
        if (
            "WinError 10061" in error_texto
            or "ConnectError" in error_texto
            or "connection" in error_texto.lower()
        ):
            raise AIProviderError(
                kind="conexion_modelo",
                message=f"No se pudo conectar con Gemini: {error_texto}",
                retryable=True,
            )
        if "429" in error_texto or "RESOURCE_EXHAUSTED" in error_texto:
            raise AIProviderError(
                kind="cuota_excedida",
                message=f"Gemini rechazo la solicitud por cuota/rate limit: {error_texto}",
                retryable=True,
            )
        if "401" in error_texto or "UNAUTHENTICATED" in error_texto:
            raise AIProviderError(
                kind="configuracion_api_key",
                message=f"Gemini rechazo la credencial configurada: {error_texto}",
                retryable=False,
            )
        if "503" in error_texto or "UNAVAILABLE" in error_texto:
            raise AIProviderError(
                kind="modelo_saturado",
                message=f"Gemini no disponible temporalmente: {error_texto}",
                retryable=True,
            )
        raise AIProviderError(
            kind="fallo_modelo",
            message=f"No se pudo obtener respuesta de Gemini: {error_texto}",
            retryable=False,
        )

    if usar_rest:
        texto = _extraer_texto_respuesta_rest(respuesta)
        metadata_seguridad = _extraer_metadata_seguridad_rest(respuesta)
    else:
        texto = _extraer_texto_respuesta(respuesta)
        metadata_seguridad = _extraer_metadata_seguridad(respuesta)
    logger.info("Texto RAW extraido de Gemini:\n%s", texto)
    logger.info("Metadata de seguridad Gemini/Vertex extraida: %s", metadata_seguridad)

    if not texto:
        raise AIProviderError(
            kind="respuesta_vacia",
            message="Gemini no devolvio texto util.",
            retryable=True,
        )

    if user_id and mensaje_usuario:
        guardar_historial(user_id, mensaje_usuario, texto)

    if return_metadata:
        return {
            "text": texto,
            "safety_metadata": metadata_seguridad,
        }

    return texto


def generar_respuesta(user_id, mensaje, foros=None):
    mensaje_limpio = str(mensaje or "").strip()
    if not mensaje_limpio:
        raise ValueError("El mensaje no puede estar vacio")

    historial = obtener_historial(user_id)
    instrucciones_extra = _serializar_foros(foros)
    prompt = construir_prompt_chat(
        mensaje_usuario=mensaje_limpio,
        historial=historial,
        instrucciones_extra=instrucciones_extra,
    )
    return generar_texto(
        prompt=prompt,
        user_id=user_id,
        mensaje_usuario=mensaje_limpio,
    )
