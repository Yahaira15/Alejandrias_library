import json
import logging
import os
from pathlib import Path
from time import time
from urllib.error import URLError
from urllib.request import urlopen

from .env_loader import load_env

try:
    import psycopg
    from psycopg.rows import dict_row
except ImportError:
    psycopg = None
    dict_row = None


logger = logging.getLogger(__name__)

BASE_DIR = Path(__file__).resolve().parents[1]
REPO_DIR = BASE_DIR.parent.parent
load_env(BASE_DIR / ".env")
load_env(BASE_DIR.parent / ".env")
load_env(REPO_DIR / "alejandrias_backend" / ".env")

DEFAULT_BACKEND_API_URL = os.getenv("ALEJANDRIAS_BACKEND_API_URL", "http://127.0.0.1:8000/api").rstrip("/")
CACHE_TTL_SEGUNDOS = int(os.getenv("FOROS_CACHE_TTL", "60"))
_CACHE_FOROS = {"data": None, "updated_at": 0.0}


def _leer_json(url):
    with urlopen(url, timeout=2) as response:
        contenido = response.read().decode("utf-8")
        return json.loads(contenido)


def _extraer_lista(data):
    if isinstance(data, list):
        return data

    if not isinstance(data, dict):
        return []

    for clave in ["data", "foros", "items"]:
        valor = data.get(clave)
        if isinstance(valor, list):
            return valor

    return []


def _normalizar_foro(foro):
    categoria = foro.get("categoria") if isinstance(foro.get("categoria"), dict) else {}
    usuario = foro.get("usuario") if isinstance(foro.get("usuario"), dict) else {}

    subcategoria = foro.get("subcategoria") if isinstance(foro.get("subcategoria"), dict) else {}

    return {
        "foro_id": foro.get("foro_id") or foro.get("id"),
        "titulo": foro.get("foro_titulo") or foro.get("titulo") or "",
        "descripcion": foro.get("foro_descripcion") or foro.get("descripcion") or "",
        "categoria": categoria.get("categoria_nombre") or foro.get("categoria_nombre") or "",
        "subcategoria": subcategoria.get("subcategoria_nombre") or foro.get("subcategoria_nombre") or "",
        "creador": usuario.get("usuario_apodo") or usuario.get("apodo") or "",
        "privado": bool(foro.get("foro_privado")),
        "estado": foro.get("foro_estado_moderacion") or "permitido",
        "visibilidad": foro.get("foro_visibilidad") or "visible",
    }


def _db_config_disponible():
    requeridas = ["DB_HOST", "DB_PORT", "DB_DATABASE", "DB_USERNAME", "DB_PASSWORD"]
    return all(os.getenv(clave) for clave in requeridas)


def _obtener_foros_desde_postgres():
    if psycopg is None or not _db_config_disponible():
        return []

    sslmode = os.getenv("DB_SSLMODE", "require")
    connection_info = {
        "host": os.getenv("DB_HOST"),
        "port": os.getenv("DB_PORT"),
        "dbname": os.getenv("DB_DATABASE"),
        "user": os.getenv("DB_USERNAME"),
        "password": os.getenv("DB_PASSWORD"),
        "sslmode": sslmode,
    }

    query = """
        SELECT
            f.foro_id AS id,
            f.foro_titulo AS titulo,
            f.foro_descripcion AS descripcion,
            f.foro_privado AS privado,
            f.foro_estado_moderacion AS estado,
            f.foro_visibilidad AS visibilidad,
            c.categoria_nombre AS categoria,
            s.subcategoria_nombre AS subcategoria,
            u.usuario_apodo AS creador
        FROM foro f
        LEFT JOIN categoria c ON c.categoria_id = f.foro_categoria_id
        LEFT JOIN subcategoria s ON s.subcategoria_id = f.subcategoria_id
        LEFT JOIN usuario u ON u.usuario_id = f.foro_creador_id
        WHERE COALESCE(f.foro_privado, false) = false
          AND COALESCE(f.foro_visibilidad, 'visible') = 'visible'
          AND COALESCE(f.foro_estado_moderacion, 'permitido') = 'permitido'
        ORDER BY f.foro_fecha_creacion DESC NULLS LAST, f.foro_id DESC
        LIMIT 50
    """

    try:
        with psycopg.connect(**connection_info, row_factory=dict_row, connect_timeout=5) as conexion:
            with conexion.cursor() as cursor:
                cursor.execute(query)
                return [
                    {
                        "foro_id": foro.get("id"),
                        "titulo": foro.get("titulo") or "",
                        "descripcion": foro.get("descripcion") or "",
                        "categoria": foro.get("categoria") or "",
                        "subcategoria": foro.get("subcategoria") or "",
                        "creador": foro.get("creador") or "",
                        "privado": bool(foro.get("privado")),
                        "estado": foro.get("estado") or "permitido",
                        "visibilidad": foro.get("visibilidad") or "visible",
                    }
                    for foro in cursor.fetchall()
                    if foro.get("titulo")
                ]
    except Exception:
        logger.exception("No se pudieron obtener foros desde PostgreSQL")
        return []


def _deduplicar_foros(foros):
    vistos = set()
    resultado = []

    for foro in foros:
        clave = foro.get("id") or foro.get("titulo", "").strip().lower()
        if not clave or clave in vistos:
            continue

        vistos.add(clave)
        resultado.append(foro)

    return resultado


def _cargar_foros_desde_fuentes():
    urls = [
        f"{DEFAULT_BACKEND_API_URL}/foros-publicos",
        f"{DEFAULT_BACKEND_API_URL}/foros",
    ]

    for url in urls:
        try:
            data = _leer_json(url)
        except (URLError, TimeoutError, json.JSONDecodeError, OSError):
            continue

        data = _extraer_lista(data)

        if isinstance(data, list):
            foros = [_normalizar_foro(foro) for foro in data if isinstance(foro, dict)]
            return _deduplicar_foros([foro for foro in foros if foro["titulo"]])

    return _deduplicar_foros(_obtener_foros_desde_postgres())


def obtener_foros_existentes(force_refresh=False):
    ahora = time()
    cache_data = _CACHE_FOROS["data"]
    cache_age = ahora - _CACHE_FOROS["updated_at"]
    cache_vigente = (
        not force_refresh
        and cache_data is not None
        and cache_age < CACHE_TTL_SEGUNDOS
    )

    if cache_vigente:
        return cache_data

    foros = _cargar_foros_desde_fuentes()

    # Evitamos cachear vacio indefinidamente: si falla una vez, vuelve a intentar en la siguiente llamada.
    if foros:
        _CACHE_FOROS["data"] = foros
        _CACHE_FOROS["updated_at"] = ahora
        return foros

    if cache_data:
        return cache_data

    return []


def limpiar_cache_foros():
    _CACHE_FOROS["data"] = None
    _CACHE_FOROS["updated_at"] = 0.0
