# Cotizador

Aplicación web self-hosted para generar cotizaciones profesionales a partir del catálogo de mayoristas (Syscom, TVC, …). PHP 8.3 + MariaDB, empaquetada con Docker.

> En desarrollo. Esta guía se completará al terminar las fases (instalación en ZimaOS, credenciales de proveedores y cómo agregar un proveedor nuevo).

## Arranque local

```bash
cp .env.example .env
# Edita .env: genera APP_KEY con `openssl rand -base64 32` y cambia las contraseñas de BD
docker compose up -d --build
```

Abre http://localhost:8080 y sigue el asistente de primer arranque (crea al administrador y la primera empresa).

Adminer (opcional): `docker compose --profile tools up -d` → http://localhost:8081

## Comandos útiles

```bash
# Restablecer la contraseña de un usuario (también lo desbloquea)
docker compose exec app php bin/reset-password.php correo@empresa.com

# Aplicar migraciones manualmente (se aplican solas al arrancar)
docker compose exec app php bin/migrate.php

# Pruebas unitarias
docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit
```

## Estructura

| Carpeta | Contenido |
|---|---|
| `src/Core` | Router, sesión, CSRF, cifrado, BD, autenticación |
| `src/Http/Controllers` | Controladores |
| `src/Providers` | Conectores de mayoristas |
| `src/Pricing` | Motor de precios |
| `src/Pdf` | Generación de PDF |
| `templates` | Vistas (PHP con escape `e()`) |
| `database/migrations` | Migraciones SQL versionadas |
| `storage` | Logos, membretes, PDFs, sesiones (volumen persistente) |
