import os

from dotenv import load_dotenv

try:
    from google import genai
except ImportError:  # pragma: no cover
    genai = None


load_dotenv()


def generar_texto(prompt):
    api_key = (
        os.getenv("GEMINI_API_KEY")
        or os.getenv("GOOGLE_API_KEY")
        or os.getenv("GEMINI:API_KEY")
    )

    if not api_key:
        raise RuntimeError("No se encontro GEMINI_API_KEY ni GOOGLE_API_KEY")

    if genai is None:
        raise RuntimeError("La libreria google-genai no esta instalada")

    client = genai.Client(api_key=api_key)
    respuesta = client.models.generate_content(
        model="gemini-2.5-flash",
        contents=prompt,
    )

    texto = getattr(respuesta, "text", None)
    if not texto:
        raise RuntimeError("La IA no devolvio texto")

    return texto
