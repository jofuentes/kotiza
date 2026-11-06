# Cómo contribuir a Kotiza

Las mejoras son bienvenidas, en especial **conectores para más mayoristas**. Si el cambio es grande, abre primero un issue para acordar el enfoque.

## Preparar el entorno

Solo necesitas Docker. No hace falta instalar PHP ni Composer.

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 install --ignore-platform-reqs
docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit
```

Para levantar la aplicación completa, sigue la [documentación técnica](docs/TECNICO.md).

## Agregar un mayorista

La guía paso a paso está en [Cómo agregar un proveedor nuevo](docs/TECNICO.md#cómo-agregar-un-proveedor-nuevo). Cada conector se descubre solo, así que normalmente no hay que tocar el resto de la aplicación. Puedes tomar como ejemplo `src/Providers/Syscom/` (API con búsqueda) o `src/Providers/Tvc/` (catálogo sincronizado).

Antes de abrir el pull request:

- **Revisa las condiciones de la API del mayorista** e indica en el pull request si permiten este uso.
- **Pide credenciales al usuario** con `credentialFields()`. Kotiza no incluye claves propias: cada usuario usa las suyas.
- **Agrega pruebas con respuestas simuladas** en `tests/Providers/`, para que corran sin credenciales ni acceso a internet.
- **Anonimiza los fixtures**: quita tokens, datos de tu cuenta y precios de distribuidor reales.

## Pull requests

- Mantén cada pull request enfocado en un solo cambio y explica qué resuelve.
- Verifica que todas las pruebas pasen y agrega pruebas para el comportamiento nuevo.
- Si cambias la base de datos, agrega una migración nueva en `database/migrations/`; no edites las existentes.
- No incluyas `.env`, credenciales ni datos reales de clientes, tampoco en capturas de pantalla.

## Licencia de las contribuciones

Al enviar una contribución aceptas que se publique bajo la misma licencia del proyecto, [GPL-2.0-or-later](LICENSE). Las dependencias nuevas deben ser compatibles con ella. Ten en cuenta que Apache-2.0, GPL-3.0 y AGPL-3.0 no son compatibles con mPDF, que es GPL-2.0-only.

## Vulnerabilidades

No las publiques en un issue. Sigue la [política de seguridad](SECURITY.md).
