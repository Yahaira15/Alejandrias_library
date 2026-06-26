import os
from pathlib import Path

try:
    from dotenv import load_dotenv as _python_dotenv_load
except ImportError:
    _python_dotenv_load = None


def load_env(path):
    env_path = Path(path)
    if not env_path.exists():
        return False

    if _python_dotenv_load is not None:
        return bool(_python_dotenv_load(env_path))

    for raw_line in env_path.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue

        key, value = line.split("=", 1)
        key = key.strip()
        value = value.strip().strip('"').strip("'")
        if key:
            os.environ.setdefault(key, value)

    return True
