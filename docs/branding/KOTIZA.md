# Cambio de marca: Cotizador → Kotiza

Instrucciones para Claude Code. Los archivos del logotipo ya están en `public/assets/brand/`.

## Objetivo

Cambiar el nombre visible de la app de **Cotizador** a **Kotiza** y aplicar el nuevo logotipo, **sin tocar nada que afecte datos, volúmenes ni instalaciones existentes**.

## Recursos de marca (ya incluidos)

| Archivo | Uso |
|---|---|
| `public/assets/brand/kotiza-icon.svg` | Ícono solo (documento + palomita), 64×64 |
| `public/assets/brand/kotiza-logo.svg` | Logo horizontal, texto oscuro (tema claro) |
| `public/assets/brand/kotiza-logo-dark.svg` | Logo horizontal, texto blanco (tema oscuro) |
| `public/assets/brand/favicon.svg` / `favicon-32.png` | Favicon |
| `public/assets/brand/apple-touch-icon.png` | 180×180 para iOS |
| `public/assets/brand/kotiza-icon-512.png` | 512×512 (manifest / redes) |
| `public/assets/brand/kotiza-logo.png` / `kotiza-logo-dark.png` | Versiones PNG del logo |

El texto del logo ya está convertido a trazos (Inter Display ExtraBold), así que no depende de fuentes instaladas.

**Colores de marca Kotiza:** azul `#1E40AF` (documento), azul claro `#93C5FD` (doblez), verde `#16A34A` (palomita), texto `#0F172A`.
Ojo: `--color-brand` en `resources/css/app.css` sigue siendo el color **de cada empresa** (se sobreescribe con `color_primary`). No lo mezcles con los colores fijos del logo de Kotiza.

## Cambios a realizar

1. **Nombre configurable**
   - Agrega `APP_NAME=Kotiza` a `.env.example` (con comentario) y lee el valor en la configuración con valor por defecto `"Kotiza"`.
   - Crea un helper `app_name()` en `src/helpers.php` y úsalo en todos los lugares de abajo, en vez de escribir el nombre a mano.

2. **`templates/partials/head.php`**
   - `<title>`: `… · Kotiza` / `Kotiza` usando `app_name()`.
   - Agrega: `<link rel="icon" href="…/assets/brand/favicon.svg" type="image/svg+xml">`, el fallback `favicon-32.png`, `apple-touch-icon` y `<meta name="theme-color" content="#1E40AF">`. Usa el helper `asset()` existente.

3. **`templates/layouts/app.php`** (barra lateral, línea ~30)
   - Si la empresa **no** tiene logo: reemplaza el cuadro con la "C" + "Cotizador" por `kotiza-logo.svg` (clase `dark:hidden`) y `kotiza-logo-dark.svg` (clase `hidden dark:block`), alto ~`h-8`.
   - Si la empresa **sí** tiene logo: déjalo como está y agrega abajo de la barra lateral una línea discreta «Hecho con [ícono] Kotiza» (texto `text-xs text-slate-400`, ícono de 14 px).

4. **`templates/layouts/guest.php`** (login): reemplaza la "C" + "Cotizador" por el logo horizontal con las variantes claro/oscuro, alto ~`h-10`.

5. **`templates/setup/wizard.php`**: «Bienvenido a Kotiza».

6. **PDF** (`src/Pdf/QuotePdfRenderer.php`): `SetCreator(app_name())`. **No** agregues el logo de Kotiza al PDF: la cotización lleva solo la marca de la empresa del cliente.

7. **User-Agent** en `src/Providers/Http/HttpClientFactory.php` y `src/Pdf/ImageCache.php`: `Kotiza/1.0`.

8. **README.md**: título `# Kotiza`, el logo arriba (`public/assets/brand/kotiza-logo.svg`) y una línea «antes llamado Cotizador». Deja los comandos `git clone …/cotizador.git` y las rutas `/DATA/AppData/cotizador` como están (el repo no se renombra por ahora).

9. **`composer.json`**: cambia solo `description` a «Kotiza — cotizador multi-proveedor (Syscom, TVC, …) self-hosted». Deja `name` como está.

## NO cambiar (rompería instalaciones existentes)

- `name: cotizador` en `docker-compose.yml` → cambiaría los volúmenes `cotizador_db_data` / `cotizador_storage` y la app arrancaría **sin datos**.
- `DB_NAME`, `DB_USER`, `APP_IMAGE` por defecto (`cotizador:latest`) ni `APP_KEY`.
- La cookie `cotizador_sid` (si cambia, todos los usuarios pierden la sesión) y el lock `cotizador_setup`.
- El prefijo de folio `COT-` (significa *cotización*, no la marca).
- El namespace `App\`, las rutas, las migraciones y las carpetas de `storage/`.

## Verificación

1. `grep -rn "Cotizador" templates src config public` → solo deben quedar casos justificados (p. ej. textos genéricos como «cotización»).
2. Pruebas: `docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit` → todas pasan.
3. Recompilar: `docker compose up -d --build` y revisar en el navegador: login (claro y oscuro), barra lateral con y sin logo de empresa, favicon en la pestaña, y un PDF generado (propiedades del documento → creador «Kotiza»).
4. Commit en una rama `rebrand-kotiza` con el mensaje «Cambio de marca a Kotiza: nombre configurable, logotipo y favicon».
