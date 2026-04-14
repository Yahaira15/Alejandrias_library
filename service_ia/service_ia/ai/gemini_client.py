import os
from pathlib import Path


def _load_env_file():
    env_path = Path(__file__).resolve().parent.parent / '.env'
    if not env_path.exists():
        return

    for line in env_path.read_text(encoding='utf-8').splitlines():
        line = line.strip()
        if not line or line.startswith('#') or '=' not in line:
            continue

        key, value = line.split('=', 1)
        os.environ.setdefault(key.strip(), value.strip())


def get_model():
    import google.generativeai as genai

    _load_env_file()
    api_key = os.getenv('GEMINI_API_KEY') or os.getenv('GEMINI:API_KEY')
    if not api_key:
        raise RuntimeError('No se encontro GEMINI_API_KEY en el entorno o en .env')

    genai.configure(api_key=api_key)
    return genai.GenerativeModel('gemini-1.5-flash')
