# Despliegue Railway - Servicio IA

Este servicio debe desplegarse como un servicio independiente con `service_ia` como root directory.

## Build y start

Railway debe usar:

```bash
pip install -r requirements.txt && python manage.py collectstatic --noinput
python -m gunicorn service_ia.wsgi:application --bind 0.0.0.0:${PORT:-8000} --workers 2 --timeout 120 --access-logfile - --error-logfile -
```

El archivo `railway.json` ya define esos comandos.

## Variables obligatorias

```env
DEBUG=False
DJANGO_SECRET_KEY=change_me
DJANGO_ALLOWED_HOSTS=tu-servicio.up.railway.app,.up.railway.app
GEMINI_API_KEY=tu_api_key_valida
GEMINI_MODEL=gemini-2.5-flash
ALEJANDRIAS_BACKEND_API_URL=https://tu-backend-laravel.up.railway.app/api
CORS_ALLOWED_ORIGINS=https://tu-frontend-angular.up.railway.app
SESSION_COOKIE_SECURE=True
CSRF_COOKIE_SECURE=True
```

Railway expone `PORT` automaticamente. No lo definas manualmente.

## Variables opcionales

```env
GEMINI_FALLBACK_MODELS=gemini-2.5-flash-lite,gemini-2.0-flash
GEMINI_MAX_RETRIES=3
FOROS_CACHE_TTL=60
IA_FORUM_CONTEXT_LIMIT=80
SECURE_SSL_REDIRECT=False
SECURE_HSTS_SECONDS=0
```

Activa `SECURE_SSL_REDIRECT=True` y HSTS solo si confirmas que Railway/proxy no genera redirecciones en bucle para tu dominio.

## Configuracion en Laravel

En el backend Laravel desplegado:

```env
IA_SERVICE_URL=https://tu-servicio-ia.up.railway.app
IA_SERVICE_TIMEOUT=12
IA_FORUM_CONTEXT_LIMIT=80
```

## Verificacion post-deploy

1. `GET https://tu-servicio-ia.up.railway.app/api/ia/` debe responder `ok=true`.
2. `GET https://tu-servicio-ia.up.railway.app/api/ia/chat/` debe responder `Chat IA funcionando`.
3. Crear una pregunta desde Angular: `Recomiendame foros sobre Python`.
4. En logs de Laravel debe aparecer `foros_enviados`.
5. En logs de Django debe aparecer `foros_recibidos` y la respuesta raw/parseada de Gemini.

## Notas

- Angular no consulta Supabase ni Gemini directamente.
- Laravel envia a Django los foros reales visibles/permitidos.
- Si Gemini falla, Django devuelve recomendaciones estructuradas usando los foros reales recibidos.
- Si el healthcheck responde HTTP 400, revisa `DJANGO_ALLOWED_HOSTS`; normalmente falta el dominio publico de Railway.
