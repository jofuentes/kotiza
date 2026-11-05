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

## Proveedores

Cada empresa captura sus propias credenciales en **Proveedores** (solo administradores). Se guardan cifradas con la `APP_KEY` y nunca se vuelven a mostrar. «Probar conexión» valida las credenciales y «Ver log» muestra cada llamada (endpoint, código HTTP, tiempo).

**Syscom:** genera `client_id` y `client_secret` en https://developers.syscom.mx/clients. El token dura un año y se renueva solo.

**TVC:** genera el token en tvc.mx → Mi Perfil → API Clientes (TVC lo aprueba; dura un año y se muestra una sola vez). La API de TVC no busca por texto, así que su catálogo se **sincroniza en segundo plano** a una tabla local (cada 12 h por defecto, o con «Sincronizar ahora») y el buscador consulta esa copia; precios y existencias se confirman en vivo al abrir la ficha. TVC limita a 200 peticiones por minuto: la sincronización deja una pausa entre páginas para respetarlo.

### Tareas en segundo plano

El contenedor ejecuta `bin/scheduler.php` cada minuto (sincronización de catálogos). Para forzarla manualmente:

```bash
docker compose exec app php bin/scheduler.php --force
```

### Cómo agregar un proveedor nuevo

1. Crea `config/providers/miproveedor.php` con URLs y endpoints.
2. Crea `src/Providers/MiProveedor/MiProveedorProvider.php` extendiendo `App\Providers\AbstractProvider`:
   - `code()`, `name()`, `description()`
   - `credentialFields()` y `settingFields()` (se dibujan solos en la UI)
   - `requestToken()` si usa token Bearer (la caché y la renovación ante 401 ya están resueltas)
   - `testConnection()`, `searchAsync()`, `getPrices()` (y `getProduct()`, `getExchangeRate()`… si aplica)
   - Un método que convierta cada producto de su API a `App\Providers\Product`
3. Usa `$this->getJsonAsync($url, $query)`: ya incluye reintentos con backoff, respeto de `Retry-After`, log de llamadas y mensajes de error en español.
4. Agrega pruebas en `tests/Providers/` con respuestas simuladas (`GuzzleHttp\Handler\MockHandler`) y fixtures en `tests/Fixtures/miproveedor/`.

El `ProviderRegistry` lo descubre automáticamente: aparece en **Proveedores** sin tocar nada más.

Si la API del proveedor **no permite buscar por texto** (como TVC), implementa además `App\Providers\Catalog\SyncableProvider` (`fetchCatalogPage()`, `syncDelayMs()`, `syncIntervalHours()`) y en `searchAsync()` consulta `$this->catalog`. El programador sincroniza el catálogo solo y la UI muestra el avance.
