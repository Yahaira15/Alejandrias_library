# Alejandria’s Library 📚

![Laravel](https://img.shields.io/badge/Laravel-Backend-red)
![Angular](https://img.shields.io/badge/Angular-Frontend-dd0031)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-Database-blue)
![Supabase](https://img.shields.io/badge/Supabase-Cloud-green)
![Python](https://img.shields.io/badge/Python-AI_Service-yellow)
![License](https://img.shields.io/badge/License-Academic-lightgrey)

---

# Descripción

**Alejandria’s Library** es una plataforma web full stack orientada a la gestión de recursos bibliográficos digitales y espacios colaborativos de aprendizaje.

El sistema integra tecnologías modernas de frontend, backend e inteligencia artificial para ofrecer una experiencia interactiva, escalable y enfocada en la comunidad.

La plataforma permite:

- Gestión de usuarios y perfiles.
- Consulta de contenido bibliográfico.
- Participación en foros y discusiones.
- Integración con servicios de inteligencia artificial.
- Arquitectura modular preparada para despliegue cloud.

El objetivo principal del proyecto es modernizar la experiencia tradicional de bibliotecas digitales mediante herramientas colaborativas e inteligentes.

---

# Integrantes

- Andres Padilla
- Kevin Nohorquez
- Yahaira Tellez
- Kevin Rincón

---

# Arquitectura del Proyecto

```bash
Alejandrias-Library/
│
├── alejandrias_backend/     # Backend Laravel
├── alejandrias_frontend/    # Frontend Angular
├── service_ia/              # Servicio IA en Python
└── docs/                    # Documentación adicional
```

## Componentes principales

| Componente              | Tecnología           |
| ----------------------- | -------------------- |
| Frontend                | Angular + TypeScript |
| Backend                 | Laravel + PHP        |
| Base de datos           | PostgreSQL           |
| Infraestructura DB      | Supabase             |
| Inteligencia Artificial | Python + Django      |

---

# Características Principales

## Gestión Bibliográfica

- Consulta de libros y recursos digitales.
- Organización y categorización del contenido.
- Visualización responsive y moderna.

## Sistema de Usuarios

- Registro e inicio de sesión.
- Gestión de perfiles.
- Validación de acceso y permisos.

## Comunidad y Foros

- Espacios colaborativos.
- Publicación e interacción en foros.
- Compartición de conocimiento.

## Inteligencia Artificial

- Integración con servicios IA.
- Procesamiento contextual.
- Sistema de respuestas inteligentes.

## Arquitectura Modular

- Separación entre frontend, backend y microservicios.
- Escalabilidad y mantenibilidad.
- Preparado para despliegues productivos.

---

# Tecnologías utilizadas

## Backend

- PHP 8
- Laravel
- PostgreSQL
- Supabase

## Frontend

- Angular
- TypeScript
- SCSS

## Inteligencia Artificial

- Python >= 3.10
- Django
- Google Generative AI (Gemini)

## Herramientas adicionales

- Git & GitHub
- Composer
- Node.js
- npm / yarn
- Docker (opcional)

---

# Requisitos previos

Antes de ejecutar el proyecto, es necesario tener instalado:

## Generales

- Git
- Node.js >= 18
- npm o yarn

## Backend

- PHP >= 8.0
- Composer

## Base de datos

- PostgreSQL >= 10

## Servicio IA

- Python >= 3.10
- PyQt6

---

# Instalación

## 1. Clonar el repositorio

```bash
git clone https://github.com/SmartLee1229/Alejandrias-Library.git

cd Alejandrias-Library
```

---

## 2. Configuración del Backend

```bash
cd alejandrias_backend

composer install

cp .env.example .env

php artisan key:generate
```

---

## 3. Configuración del Frontend

```bash
cd ../alejandrias_frontend

npm install
```

o

```bash
yarn install
```

---

## 4. Configuración del Servicio IA

```bash
cd ../service_ia

python -m venv venv
```

### Activar entorno virtual

#### Windows

```bash
venv\Scripts\activate
```

#### Linux / macOS

```bash
source venv/bin/activate
```

### Instalar dependencias

```bash
pip install -r requirements.txt
```

### Configurar variables de entorno

```bash
cp .env.example .env
```

Editar `.env` con tu clave de API de Google Generative AI.

---

# Ejecución local

## Backend Laravel

```bash
cd alejandrias_backend

php artisan serve
```

Servidor local:

```bash
http://127.0.0.1:8000
```

---

## Frontend Angular

```bash
cd alejandrias_frontend

ng serve
```

Servidor local:

```bash
http://localhost:4200
```

---

## Servicio IA

```bash
cd service_ia

python manage.py runserver
```

---

# Base de datos

El proyecto utiliza **PostgreSQL** alojado en **Supabase**, permitiendo una infraestructura cloud escalable y segura.

## Configuración de la base de datos

### 1. Crear cuenta en Supabase

1. Accede a [Supabase](https://supabase.com)
2. Regístrate o inicia sesión
3. Crea un nuevo proyecto con:
   - Nombre del proyecto
   - Contraseña segura
   - Región más cercana

### 2. Obtener credenciales

Una vez creado el proyecto, obtén:

- **DB_HOST**: URL del servidor PostgreSQL
- **DB_PORT**: 5432 (por defecto)
- **DB_DATABASE**: postgres
- **DB_USERNAME**: Usuario proporcionado por Supabase
- **DB_PASSWORD**: Contraseña asignada

### 3. Configurar variables de entorno

Actualiza el archivo `.env` en `alejandrias_backend/`:

```env
DB_CONNECTION=pgsql
DB_HOST=tu-proyecto.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.xxxxxxxxxxxxx
DB_PASSWORD=tu_contraseña_segura
```

### 4. Ejecutar migraciones

Una vez configurada la conexión, ejecuta:

```bash
cd alejandrias_backend

php artisan migrate
```

⚠️ **IMPORTANTE**: Las migraciones crearán automáticamente todas las tablas necesarias. No es necesario importar un archivo SQL.

## Migraciones disponibles

El proyecto incluye las siguientes migraciones:

- `create_users_table` - Tabla de usuarios
- `create_cache_table` - Caché de aplicación
- `create_jobs_table` - Cola de trabajos
- `create_personal_access_tokens_table` - Tokens de autenticación
- `add_foro_password_to_foro_table` - Campos adicionales de foros
- `add_ruta_to_notificacion_table` - Campos de notificaciones
- `add_usuario_intereses_to_usuario_table` - Intereses de usuarios
- `prepare_ai_moderation_tables` - Tablas de moderación por IA
- `align_ai_moderation_schema` - Esquema de moderación

---

# Variables de entorno

## Backend `.env`

```env
APP_NAME=AlejandriasLibrary
APP_ENV=local
APP_KEY=
APP_DEBUG=true

DB_CONNECTION=pgsql
DB_HOST=
DB_PORT=5432
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
```

## Servicio IA `.env`

```env
GEMINI_API_KEY=
DEBUG=True
```

## Explicación de variables

| Variable       | Descripción                    |
| -------------- | ------------------------------ |
| APP_NAME       | Nombre de la aplicación        |
| APP_ENV        | Entorno de ejecución           |
| APP_KEY        | Clave de seguridad de Laravel  |
| APP_DEBUG      | Modo desarrollo                |
| DB_CONNECTION  | Tipo de base de datos          |
| DB_HOST        | Host de PostgreSQL             |
| DB_PORT        | Puerto de conexión             |
| DB_DATABASE    | Nombre de la base de datos     |
| DB_USERNAME    | Usuario de la base de datos    |
| DB_PASSWORD    | Contraseña de la base de datos |
| GEMINI_API_KEY | Clave de acceso al servicio IA |
| DEBUG          | Activa modo depuración         |

---

# Archivo `.env.example`

Se recomienda crear los siguientes archivos:

```bash
alejandrias_backend/.env.example
service_ia/.env.example
```

Ejemplo:

```env
DB_HOST=
DB_PORT=
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
```

⚠️ Nunca subas credenciales reales, claves API o contraseñas al repositorio.

---

# Flujo General del Sistema

```text
Usuario
   ↓
Frontend Angular
   ↓
Backend Laravel
   ↓
PostgreSQL (Supabase)
   ↓
Servicio IA (Python/Django)
```

---

# Usuario de prueba

Para probar la plataforma en desarrollo local:

## Crear usuario de prueba

### Opción 1: Usando Laravel Tinker (recomendado)

```bash
cd alejandrias_backend

php artisan tinker

User::create([
    'name' => 'Usuario Prueba',
    'email' => 'prueba@example.com',
    'password' => Hash::make('password123'),
]);
```

### Opción 2: Usando Seeder (si disponible)

```bash
php artisan db:seed DatabaseSeeder
```

## Credenciales por defecto (desarrollo)

| Campo      | Valor              |
| ---------- | ------------------ |
| Email      | prueba@example.com |
| Contraseña | password123        |

⚠️ **IMPORTANTE**: Cambiar estas credenciales en producción. No usar contraseñas débiles.

---

# Despliegue

El proyecto está diseñado para despliegues modernos mediante contenedores y servicios cloud.

## Plataforma recomendada

- **Render.com** (recomendado para principiantes)
- **Railway.app** (simple y rápido)
- **Vercel** (para Angular frontend)
- **Supabase** (base de datos ya incluida)
- **Docker + VPS Linux** (máximo control)
- **Heroku** (legacy, no es recomendado)

## Pasos generales de despliegue

### 1. Preparar el proyecto

```bash
# Frontend
cd alejandrias_frontend
npm run build

# Backend
cd ../alejandrias_backend
composer install --optimize-autoloader --no-dev
```

### 2. Configurar variables de entorno en producción

**Cambios CRÍTICOS en producción**:

```env
APP_ENV=production
APP_DEBUG=false
DB_PASSWORD=contraseña_fuerte_nueva
GEMINI_API_KEY=tu_clave_api_produccion
```

⚠️ **NO**: Usar credenciales de desarrollo en producción.

### 3. Desplegar en Render.com (opción simplificada)

1. Conectar repositorio GitHub
2. Crear servicio web para backend Laravel
3. Crear servicio web para frontend Angular
4. Configurar variables de entorno
5. Desplegar

### 4. Configurar dominio y HTTPS

- Asociar dominio personalizado
- Certificado SSL automático (incluido en Render)

### 5. Desplegar servicio IA

```bash
cd service_ia
pip install -r requirements.txt
python manage.py collectstatic --noinput
```

Para producción en VPS:

```bash
# Usar Gunicorn
pip install gunicorn
gunicorn service_ia.wsgi:application --bind 0.0.0.0:8000
```

---

# Buenas prácticas implementadas

- Arquitectura modular.
- Separación de responsabilidades.
- Variables de entorno seguras.
- Diseño responsive.
- Servicios desacoplados.
- Escalabilidad orientada a producción.

---

# Estado del Proyecto

🚧 Proyecto en desarrollo activo.

Actualmente se trabaja en:

- Optimización del sistema IA.
- Mejoras UX/UI.
- Escalabilidad del backend.
- Automatización de despliegue.

---

# Posibles mejoras futuras

- Implementación completa con Docker.
- CI/CD automatizado.
- Sistema de recomendaciones IA.
- Chat contextual inteligente.
- Panel administrativo avanzado.
- Sistema de analíticas y métricas.

---

# Evidencias

## Repositorio oficial

https://github.com/SmartLee1229/Alejandrias-Library

## Capturas del sistema (Pendientes de documentación)

Se recomienda agregar evidencias visuales en la carpeta `/docs/screenshots/` con:

| Captura         | Descripción                       |
| --------------- | --------------------------------- |
| `home.png`      | Página principal de la biblioteca |
| `forum.png`     | Sistema de foros y discusiones    |
| `dashboard.png` | Panel de control de usuarios      |
| `profile.png`   | Perfil de usuario                 |
| `ia.png`        | Integración con servicio IA       |
| `admin.png`     | Panel administrativo              |

### Instrucción para agregar evidencias

1. Captura pantallazos del sistema funcionando
2. Guarda en `docs/screenshots/`
3. Enlaza en este README

Ejemplo:

```markdown
![Página principal](./docs/screenshots/home.png)
```

## Video de demostración (Opcional)

Se recomienda crear un video corto demostrando:

- Flujo de registro e inicio de sesión
- Navegación por la biblioteca
- Participación en foros
- Interacción con el servicio IA

**Plataforma recomendada**: YouTube o asignar enlace en el README.

---

# Seguridad

## Buenas prácticas implementadas

- ✅ Variables de entorno para proteger configuraciones sensibles
- ✅ Contraseñas hash con bcrypt en Laravel
- ✅ Supabase con conexiones PostgreSQL seguras
- ✅ Validación y sanitización de inputs en Laravel
- ✅ Tokens de autenticación (Sanctum)
- ✅ CORS configurado de forma segura

## Recomendaciones de seguridad

### ⚠️ NUNCA en el repositorio:

- Credenciales de bases de datos (contraseñas)
- Claves API o tokens
- Información sensible de usuarios
- Archivos `.env` con datos reales

### ✅ Siempre hacer:

1. Usar archivos `.env.example` sin credenciales
2. Agregar `.env` al `.gitignore` (ya configurado)
3. Cambiar contraseñas en producción
4. Usar variables de entorno en servidores
5. Habilitar HTTPS en producción
6. Mantener dependencias actualizadas:

```bash
# Backend
composer update

# Frontend
npm update

# IA
pip install --upgrade -r requirements.txt
```

7. Realizar auditorías de seguridad periódicamente

---

# Licencia

Proyecto desarrollado con fines académicos y de investigación.

---

# Repositorio

https://github.com/SmartLee1229/Alejandrias-Library
