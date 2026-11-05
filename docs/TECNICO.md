<p align="center"><img src="../public/assets/brand/kotiza-logo.svg" alt="Kotiza" height="64"></p>

# Kotiza — documentación técnica

*Antes llamado Cotizador.*

Aplicación web self-hosted para generar cotizaciones profesionales a partir del catálogo de mayoristas (**Syscom** y **TVC**, ampliable a otros). Multi-empresa, en español, con PDF membretado, motor de precios, aprobaciones y trabajo colaborativo.

**Stack:** PHP 8.3 (sin framework; FastRoute + PDO), MariaDB 11, Tailwind + Alpine.js, mPDF, Docker.

---

## Contenido

1. [Instalación local (Docker)](#instalación-local-docker)
2. [Instalación en ZimaOS](#instalación-en-zimaos)
3. [Primer arranque](#primer-arranque)
4. [Proveedores y credenciales](#proveedores-y-credenciales)
5. [Precios](#precios)
6. [Cotizaciones: flujo, colaboración y PDF](#cotizaciones-flujo-colaboración-y-pdf)
7. [Respaldos y actualizaciones](#respaldos-y-actualizaciones)
8. [Cómo agregar un proveedor nuevo](#cómo-agregar-un-proveedor-nuevo)
9. [Desarrollo y pruebas](#desarrollo-y-pruebas)

---

## Instalación local (Docker)

Requisitos: Docker con Compose.

```bash
git clone https://github.com/jofuentes/cotizador.git
cd cotizador
cp .env.example .env
```

Edita `.env` (es lo único que se configura fuera de la interfaz):

| Variable | Qué es |
|---|---|
| `APP_URL` | URL con la que se accede (ej. `http://192.168.1.50:8080`) |
| `APP_PORT` | Puerto del host |
| `APP_KEY` | Clave de cifrado de credenciales. Genérala con `openssl rand -base64 32`. **No la cambies después** o las credenciales guardadas no se podrán descifrar |
| `DB_PASS`, `DB_ROOT_PASSWORD` | Contraseñas de MariaDB (cámbialas) |
| `TZ` | Zona horaria (por defecto `America/Mexico_City`) |

```bash
docker compose up -d --build
```

Abre `APP_URL`. Las migraciones de base de datos se aplican solas al arrancar.

Adminer (opcional, para ver la base de datos): `docker compose --profile tools up -d` → puerto `8081`.

## Instalación en ZimaOS

ZimaOS ejecuta Docker, así que se instala igual que en local, desde una terminal SSH del equipo:

```bash
ssh usuario@IP-DE-ZIMA
git clone https://github.com/jofuentes/cotizador.git /DATA/AppData/cotizador
cd /DATA/AppData/cotizador
cp .env.example .env && nano .env      # APP_URL=http://IP-DE-ZIMA:8080, APP_KEY, contraseñas
docker compose up -d --build
```

- La imagen se compila para la arquitectura del equipo (x86-64 o ARM64).
- Los datos viven en dos volúmenes persistentes: `cotizador_db_data` (base de datos) y `cotizador_storage` (logos, membretes, firmas, PDFs, caché).
- Después de instalar, la app aparece en el panel de ZimaOS como contenedor y se puede abrir desde ahí.

## Primer arranque

1. El **asistente** pide el administrador, la empresa y el folio inicial (si ya traes un consecutivo, escribe el siguiente número).
2. **Empresa**: logo, hoja membretada (PDF o imagen), colores, márgenes del PDF, datos bancarios, condiciones comerciales, folios, IVA y permisos (ocultar costos a vendedores, exigir aprobación de cotizaciones).
3. **Usuarios**: vendedores y administradores, con descuento máximo por vendedor, nombre en el PDF y firma.
4. **Proveedores**: captura tus credenciales (siguiente sección).
5. **Precios**: elige cómo se calcula el precio de venta.

Contraseña olvidada: `docker compose exec app php bin/reset-password.php correo@empresa.com`

## Proveedores y credenciales

Cada empresa usa sus propias credenciales de distribuidor. Se guardan **cifradas** con `APP_KEY` y nunca se vuelven a mostrar (solo `••••` y «Reemplazar»). Cada proveedor tiene «Probar conexión» y «Ver log» (endpoint, código HTTP, tiempo de cada llamada).

**Syscom** — genera `client_id` y `client_secret` en <https://developers.syscom.mx/clients>. El token dura un año y se renueva solo. Ajustes recomendados para que el costo coincida con el precio grande de syscom.mx: costo = «Tu precio de distribuidor», con descuento financiero e IVA.

**TVC** — genera el token en tvc.mx → Mi Perfil → API Clientes (TVC lo aprueba; dura un año). Su API no busca por texto: el catálogo se **sincroniza en segundo plano** (cada 12 h o con «Sincronizar ahora») y el buscador consulta esa copia; precios y existencias se confirman en vivo al abrir la ficha o actualizar precios.

**Caché**: búsquedas y fichas se guardan localmente (vigencia configurable en Proveedores). Si un proveedor no responde se muestra el último dato conocido indicando su antigüedad.

## Precios

En **Precios** se define la regla global y reglas específicas. Gana la más específica:

> precio manual de la partida → producto → marca → categoría → proveedor → global

| Método | Precio de venta |
|---|---|
| Costo + porcentaje | costo × (1 + %) |
| Margen sobre venta | costo ÷ (1 − %) |
| Precio de lista del proveedor | lista **sin IVA** (al sumar el IVA, el cliente ve la lista exacta del proveedor); descuento sugerido opcional |

Cada regla admite precio mínimo y redondeo (.00, .90 o múltiplo). El **simulador** muestra qué regla y precio tocarían a un producto. También aquí: tipo de cambio (automático del proveedor, + colchón o manual) y margen mínimo de alerta.

## Cotizaciones: flujo, colaboración y PDF

**Editor**: buscador de todos los proveedores, partidas manuales (servicios, mano de obra, material), secciones, arrastrar y soltar, precio manual, descuento por partida y global, totales en vivo y panel interno de costo/utilidad/margen (no sale en el PDF). Guarda con Ctrl+S y cada 30 s.

**Flujo** (si la empresa exige aprobación, que es lo predeterminado):

```
Borrador → En revisión → Aprobada → Enviada → Aceptada / Rechazada
              ↓ (admin regresa con comentario)          ↘ Vencida (al pasar la vigencia)
           Borrador
```

- Solo un **administrador** aprueba. Aprobar la cotización aprueba también sus descuentos.
- Sin aprobación exigida, el vendedor envía directo (salvo descuentos por encima de su límite, que sí requieren aprobación).
- Una cotización enviada ya no se edita: **Nueva versión** crea v2, v3… con el mismo folio; las anteriores quedan en el historial.
- **Duplicar** crea una cotización nueva (otro folio) con las mismas partidas.
- **Actualizar precios** consulta costos y existencias actuales, muestra qué cambió y aplica solo si confirmas (respeta los precios manuales).

**Colaboración**: el creador comparte con compañeros con permiso «Puede ver» o «Puede editar». El administrador tiene acceso total a todas. Si dos personas editan a la vez, el segundo guardado se rechaza con el nombre de quien guardó primero y se ofrece recargar.

**PDF** (mPDF, tamaño carta): hoja membretada como fondo o encabezado con logo, cliente, vendedor, partidas por sección con imagen, totales, importe con letra, condiciones, datos bancarios, firma y folio/página. Opciones por cotización: imágenes, marca y modelo, existencias, desglose de IVA. Marca de agua «BORRADOR» hasta que se aprueba y «VENCIDA» al vencer. Vista previa en vivo y descarga como `COT-2026-0001_Cliente.pdf`.

## Respaldos y actualizaciones

**Respaldo** (base de datos + archivos):

```bash
docker compose exec db sh -c 'mariadb-dump -u root -p"$MARIADB_ROOT_PASSWORD" --single-transaction "$MARIADB_DATABASE"' > respaldo-$(date +%F).sql
docker run --rm -v cotizador_storage:/s -v "$PWD":/b alpine tar czf /b/storage-$(date +%F).tgz -C /s .
```

Guarda también tu `.env` (sin `APP_KEY` no se pueden descifrar las credenciales de proveedores).

**Restaurar**: `docker compose exec -T db sh -c 'mariadb -u root -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' < respaldo.sql`

**Actualizar** la app: `git pull && docker compose up -d --build` (las migraciones nuevas se aplican solas).

**Tareas en segundo plano**: el contenedor ejecuta `bin/scheduler.php` cada minuto (sincroniza catálogos y vence cotizaciones). Manual: `docker compose exec app php bin/scheduler.php --force`.

## Cómo agregar un proveedor nuevo

1. Crea `config/providers/miproveedor.php` con URLs y endpoints.
2. Crea `src/Providers/MiProveedor/MiProveedorProvider.php` extendiendo `App\Providers\AbstractProvider`:
   - `code()`, `name()`, `description()`
   - `credentialFields()` y `settingFields()` — se dibujan solos en la sección Proveedores
   - `requestToken()` si usa token Bearer (caché del token y renovación ante 401 ya resueltas)
   - `testConnection()`, `searchAsync()`, `getPrices()` (y `getProduct()`, `getExchangeRate()`… si aplica)
   - un método que convierta cada producto de su API a `App\Providers\Product` (incluye `cost`, `listPrice` **sin IVA**, `costIncludesVat`, existencias, claves SAT, imágenes)
3. Usa `$this->getJsonAsync($url, $query)`: incluye reintentos con backoff, respeto de `Retry-After`, log de llamadas y mensajes de error en español.
4. Si su API **no busca por texto**, implementa también `App\Providers\Catalog\SyncableProvider` (`fetchCatalogPage()`, `syncDelayMs()`, `syncIntervalHours()`) y en `searchAsync()` consulta `$this->catalog`; el programador sincroniza y la UI muestra el avance.
5. Agrega pruebas en `tests/Providers/` con respuestas simuladas (`GuzzleHttp\Handler\MockHandler`) y fixtures en `tests/Fixtures/miproveedor/`.

El `ProviderRegistry` lo descubre automáticamente: aparece en **Proveedores** sin tocar nada más.

## Desarrollo y pruebas

```bash
# Pruebas unitarias (motor de precios, conectores con respuestas simuladas, flujo, PDF, etc.)
docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit

# Instalar dependencias de desarrollo
docker run --rm -v "$PWD":/app -w /app composer:2 install --ignore-platform-reqs
```

| Carpeta | Contenido |
|---|---|
| `src/Core` | Router, sesión, CSRF, cifrado, BD, autenticación |
| `src/Http/Controllers` | Controladores |
| `src/Domain` | Folios, cotizaciones (servicio, cálculo, flujo) |
| `src/Providers` | Conectores, catálogo, caché, sincronización |
| `src/Pricing` | Motor de precios, redondeo, tipo de cambio |
| `src/Pdf` | Generación de PDF, importe con letra |
| `templates` | Vistas (PHP con escape `e()`) y plantilla del PDF |
| `database/migrations` | Migraciones SQL versionadas |
| `storage` | Logos, membretes, firmas, PDFs, sesiones, caché (volumen persistente) |
