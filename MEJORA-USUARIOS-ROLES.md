# Usuarios y roles: edición y control de acceso

## Cambios

- Usuarios: búsqueda por usuario, nombre, correo o nombre del rol; filtros por rol y estado; acciones de edición visibles al desplazar la tabla.
- La edición de una cuenta permite cambiar su rol, vínculos académicos, estado y contraseña. Las contraseñas vacías se conservan; la validación del formulario coincide con PHP (mínimo 12 caracteres, máximo 72 bytes).
- Nuevo editor en `/roles.php`, accesible desde **Usuarios y roles → Editar roles y permisos**. Permite renombrar cada perfil y activar/desactivar sus módulos institucionales. Guarda en MySQL, no en el navegador.
- Restauración de los permisos originales por perfil; protección de la cuenta administradora y de los ámbitos personales o del programa.
- El editor no crea roles nuevos ni transfiere módulos entre perfiles institucionales. Las reglas de aprobación/publicación y los vínculos con docentes, alumnos y empresas permanecen vigentes.
- PHP comprueba los permisos al consultar y modificar datos. Ocultar enlaces no concede permisos. Un cambio de rol cierra las sesiones de sus usuarios para aplicar los nuevos accesos.
- Detección de ediciones simultáneas, protección CSRF y registro de cambios en auditoría.
- Los respaldos portables nuevos conservan los permisos editados. Restaurar una copia antigua sin configuración de roles recupera los permisos institucionales originales. La exportación SQL ya incluye `pisco_settings`.

## Actualización en Hostinger

1. Respalde archivos y MySQL.
2. Actualice `portal-private/Accounts.php`, `Backups.php`, `Policy.php` y `Portal.php`.
3. Actualice `public_html/portal-server.js` y agregue `public_html/roles.php`.
4. Conserve `portal-private/config.php` y los respaldos existentes. No reinstale ni importe `schema.sql`.
5. Si todavía no aplicó las correcciones anteriores, suba las carpetas completas de `main` y ejecute `actualizar.php` según `INSTRUCCIONES.md`.
6. Cierre sesión, limpie la caché y vuelva a ingresar como administrador. Abra **Usuarios y roles**.

Esta mejora utiliza la tabla `pisco_settings` existente; no requiere cambios nuevos en el esquema. Solo los administradores pueden gestionar roles.

## Verificación local

PHP 8.4, MySQL 8.4 y Chromium, con una base exclusiva de pruebas:

**58 comprobaciones aprobadas:** 15 del servicio de roles, 13 del navegador, 6 de cuentas/sesiones y 24 regresiones/contratos. Sintaxis PHP y JavaScript verificada.

- `tests/roles-permissions.php`: autorización, persistencia, rechazo de escalamiento, bloqueo del administrador, conflictos, sesiones, restauración de permisos y respaldo portable.
- `tests/browser-roles.cjs`: formularios reales de cambio de rol y desactivación, filtros, acciones visibles, guardado/recarga/restauración, acceso denegado y diseño de 375/1440 px.
- Regresiones del portal y contratos de validación existentes.

El correo sigue pendiente. Las pruebas se realizan localmente; los archivos deben subirse a Hostinger para que el sitio publicado cambie.
