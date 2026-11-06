# Política de seguridad

Kotiza guarda credenciales de mayoristas, costos y datos de clientes, así que los reportes de seguridad son muy valiosos.

## Cómo reportar una vulnerabilidad

**No abras un issue público.** Usa [Report a vulnerability](https://github.com/jofuentes/kotiza/security/advisories/new) en la pestaña *Security* del repositorio. Si puedes, incluye:

- la versión o el commit afectado;
- los pasos para reproducirla;
- el impacto que crees que tiene.

Kotiza es un proyecto mantenido por voluntarios, así que no hay un plazo de respuesta garantizado. Coordinaremos la publicación contigo cuando exista una corrección.

## Versiones con soporte

Las correcciones se publican sobre la rama `main` y la versión más reciente.

## Recomendaciones para tu instalación

- No expongas Kotiza a internet sin HTTPS.
- Cambia las contraseñas de ejemplo de `.env` y genera tu propia `APP_KEY`.
- Respalda `.env` en un lugar seguro: sin `APP_KEY` no se pueden descifrar las credenciales guardadas.
- No dejes activo Adminer (perfil `tools`) cuando no lo uses.
- Actualiza con frecuencia y haz respaldos de la base de datos y de `storage`.
