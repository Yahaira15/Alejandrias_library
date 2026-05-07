# Alejandria’s Library 📚

## Descripción

**´Alejandria’s Library´** es una plataforma digital enfocada en la gestión, consulta e interacción con recursos bibliográficos y espacios colaborativos. El proyecto busca modernizar la experiencia tradicional de una biblioteca mediante una solución tecnológica intuitiva, escalable y accesible.

La plataforma permite a los usuarios:

- Consultar recursos bibliográficos digitales.
- Participar en foros y discusiones.
- Gestionar perfiles de usuario.
- Acceder a funcionalidades potenciadas por inteligencia artificial.
- Interactuar en una comunidad de aprendizaje y compartición de conocimiento.

El proyecto resuelve la necesidad de centralizar recursos académicos y sociales en un entorno moderno, mejorando la experiencia tanto para usuarios finales como para administradores.

---

## Integrantes

- Andres Padilla
- Kevin Nohorquez
- Yahaira Tellez
- Kevin Rincón

---

## Arquitectura del Proyecto

```bash
Alejandrias-Library/
│
├── alejandrias_backend/     # API y lógica de negocio (Laravel)
├── alejandrias_frontend/    # Aplicación cliente (Angular)
├── service_ia/              # Servicios de inteligencia artificial
└── docs/                    # Documentación adicional
```

---

## Características Principales

### Gestión Bibliográfica
- Consulta de libros y recursos digitales.
- Organización y categorización del contenido.
- Visualización responsive y moderna.

### Sistema de Usuarios
- Registro e inicio de sesión.
- Gestión de perfiles.
- Validación de acceso y permisos.

### Comunidad y Foros
- Espacios colaborativos.
- Publicación e interacción en foros.
- Compartición de conocimiento.

### Inteligencia Artificial
- Integración con servicios IA.
- Procesamiento contextual.
- Sistema de respuestas inteligentes.

### Arquitectura Modular
- Separación entre frontend, backend y microservicios.
- Escalabilidad y mantenibilidad.
- Preparado para despliegues productivos.

---

## Tecnologías utilizadas

### Backend
- PHP 8
- Laravel
- PostgreSQL
- Supabase

### Frontend
- Angular
- TypeScript
- SCSS

### Inteligencia Artificial
- Python
- Django
- Gemini AI
- PyQt6

### Herramientas adicionales
- Git & GitHub
- Composer
- Node.js
- npm / yarn
- Docker (opcional)

---

## Requisitos previos

Antes de ejecutar el proyecto, es necesario tener instalado:

### Generales
- Git
- Node.js >= 18
- npm o yarn

### Backend
- PHP >= 8.0
- Composer

### Base de datos
- PostgreSQL >= 10

### Servicio IA
- Python >= 3.10
- PyQt6

---

## Instalación

### 1. Clonar el repositorio

```bash
git clone https://github.com/SmartLee1229/Alejandrias-Library.git

cd Alejandrias-Library
```

---

### 2. Configuración del Backend

```bash
cd alejandrias_backend

composer install

cp .env.example .env

php artisan key:generate
```

---

### 3. Configuración del Frontend

```bash
cd ../alejandrias_frontend

npm install
```

o

```bash
yarn install
```

---

### 4. Configuración del Servicio IA

```bash
cd ../service_ia

python -m venv venv
```

#### Activar entorno virtual

##### Windows

```bash
venv\Scripts\activate
```

##### Linux / macOS

```bash
source venv/bin/activate
```

#### Instalar dependencias

```bash
pip install -r requirements.txt
```

---

## Ejecución local

### Backend Laravel

```bash
cd alejandrias_backend

php artisan serve
```

Servidor local:

```bash
http://127.0.0.1:8000
```

---

### Frontend Angular

```bash
cd alejandrias_frontend

ng serve
```

Servidor local:

```bash
http://localhost:4200
```

---

### Servicio IA

```bash
cd service_ia

python manage.py runserver
```

---

## Base de datos

El proyecto utiliza **PostgreSQL** alojado en **Supabase**, permitiendo una infraestructura cloud escalable y segura.

### Configuración general

1. Crear una cuenta en Supabase.
2. Crear un nuevo proyecto.
3. Configurar:
   - Nombre del proyecto.
   - Contraseña.
   - Región.
4. Obtener las credenciales de conexión.
5. Configurarlas en el archivo `.env`.

### Ejecutar migraciones

```bash
php artisan migrate
```

---

## Variables de entorno

### Backend `.env`

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

### Servicio IA `.env`

```env
GEMINI_API_KEY=your_api_key
DEBUG=True
```

### Explicación de variables

| Variable | Descripción |
|---|---|
| APP_NAME | Nombre de la aplicación |
| APP_ENV | Entorno de ejecución |
| APP_KEY | Clave de seguridad de Laravel |
| APP_DEBUG | Modo desarrollo |
| DB_CONNECTION | Tipo de base de datos |
| DB_HOST | Host de PostgreSQL |
| DB_PORT | Puerto de conexión |
| DB_DATABASE | Nombre de la base de datos |
| DB_USERNAME | Usuario de la base de datos |
| DB_PASSWORD | Contraseña de la base de datos |
| GEMINI_API_KEY | Clave de acceso al servicio IA |
| DEBUG | Activa modo depuración |

---

## Flujo General del Sistema

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

## Usuario de prueba

Usuario = hola
Contraseña = Hola1234&

---

## Despliegue

El proyecto está diseñado para despliegues modernos mediante contenedores y servicios cloud.

### Plataforma recomendada
- Docker
- Supabase
- VPS Linux
- Servicios Cloud

### Flujo general de despliegue

1. Construcción de contenedores.
2. Configuración de variables de entorno.
3. Despliegue del backend Laravel.
4. Despliegue del frontend Angular.
5. Configuración de Supabase.
6. Despliegue del servicio IA.
7. Configuración de dominios y HTTPS.

---

## Buenas prácticas implementadas

- Arquitectura modular.
- Separación de responsabilidades.
- Uso de variables de entorno.
- Diseño responsive.
- Validaciones de negocio.
- Servicios desacoplados.
- Escalabilidad orientada a producción.

---

## Estado del Proyecto

🚧 Proyecto en desarrollo activo.

Actualmente se trabaja en:

- Mejoras de experiencia de usuario.
- Optimización del sistema IA.
- Integraciones avanzadas.
- Mejoras de rendimiento.
- Despliegue productivo.

---

## Posibles mejoras futuras

- Implementación completa con Docker.
- CI/CD automatizado.
- Sistema de recomendaciones IA.
- Chat contextual inteligente.
- Panel administrativo avanzado.
- Sistema de analíticas y métricas.

---

## Evidencias

### Repositorio oficial

https://github.com/SmartLee1229/Alejandrias-Library

### Capturas del sistema

Pendiente de anexar:

- Pantalla principal.
- Sistema de foros.
- Gestión de usuarios.
- Integración IA.
- Panel administrativo.

---

## Licencia

Proyecto desarrollado con fines académicos y de investigación.