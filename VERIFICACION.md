# Verificación de la migración PHP y MySQL

Revisión del 26 de octubre de 2026 frente al modelo original `index_pisco_2026.html`. Entorno de pruebas separado: PHP 8.4, MySQL 8.4 y Chromium. Esta verificación no actualiza ni certifica la instalación que está actualmente en Hostinger.

## Alcance

Se conserva la interfaz HTML, CSS y JavaScript. PHP controla autenticación, permisos, validación, consultas, archivos y operaciones; MySQL conserva contenido compartido, cuentas, adjuntos, visitas, auditoría y ajustes. No requiere Laravel, Composer ni un servidor Node en el hosting. PDF, Excel y QR están incluidos localmente con sus licencias.

Se cubren los once perfiles del modelo, los veinticinco editores administrativos, páginas públicas y consultas, alumnos, horarios y cruces, notas, certificación modular, títulos, CV y perfiles profesionales, empresas, ofertas, postulaciones, contacto, suscripciones, reclamos, expedientes, seguimiento, aprobaciones, papelera, auditoría y respaldos.

## Pruebas reproducibles incluidas

| Archivo en tests | Cobertura |
|---|---|
| run.php | 31 comprobaciones base: instalación, cuentas, sesiones, CSRF, formularios y archivos |
| upgrade.php | Esquema antiguo actualizado dos veces; contenido y hashes de contraseña conservados |
| parity.php | 51 comprobaciones de los once perfiles y flujos académicos, laborales y de publicación |
| migration.php | 138 comprobaciones adicionales: CRUD de contenido, concurrencia por registro, programación de noticias, histórico, pruebas de formularios, respaldo y restauración con integridad |
| academic-crud.php | 42 operaciones de maestros académicos, horarios y títulos, incluidas dependencias |
| regressions.php | 11 comprobaciones de aprobación, inmutabilidad, fechas, avisos y permisos |
| quality-lookups.php | 8 comprobaciones de detalle, anulación, privacidad, DNI y exclusión de certificados en papelera |
| browser-crud.cjs | 50 operaciones reales de creación y edición desde los 25 editores, confirmadas contra el servidor |
| browser-parity.cjs | 115 comprobaciones de navegación, 39 patrones de rutas públicas, perfiles, publicación entre ventanas y franja móvil solo en Inicio |
| browser-profiles.cjs | Guardar y recargar CV docente y adjunto CV de alumno |
| browser-workflows.cjs | Expediente y reclamo con PDF real, recepción y descarga con bytes idénticos; respaldo SQL |
| browser-libraries.cjs | Excel, QR y PDF; reporte de 300 filas distribuido en varias páginas |
| concurrency.cjs | 20 cambios simultáneos desde dos perfiles conservados sin pisar registros distintos |
| browser-backups.cjs | Crear, descargar, subir y restaurar copia portable desde la interfaz; contenido recuperado, auditoría y programación |
| browser-services.cjs | Respuestas a mensajes y reclamos, derivación y atención de expedientes, historial persistente al recargar |
| browser-upgrade.cjs | Actualizador web: clave incorrecta rechazada y actualización con clave privada |

Las pruebas destructivas rechazan configuraciones que no sean de pruebas y una base cuyo nombre no termine en `_test`. Las credenciales sintéticas de esos scripts pertenecen exclusivamente a la base de pruebas; no se instalan como cuentas en producción.

## Límites de la verificación

La batería comprueba los flujos descritos con datos controlados; no garantiza ausencia de cualquier fallo ni sustituye la revisión de datos institucionales reales. La configuración de PHP, los permisos de archivos, el cron y los límites de Hostinger deben comprobarse al desplegar allí. Las imágenes y mapas externos dependen de sus proveedores. Los archivos del antiguo prototipo que solo existían en el navegador requieren volver a adjuntarse.

El correo saliente y la recuperación por correo quedan pendientes por indicación del usuario. Las respuestas, constancias y avisos internos se guardan realmente; no se simula el envío de correo. Consulta `INSTRUCCIONES.md` para actualizar conservando tu base, configuración y usuarios.

## Control de calidad ampliado

Consulta `CONTROL-CALIDAD.md` para el recorrido de enlaces, adaptación a pantallas pequeñas, fallos corregidos y pruebas adicionales de importación Excel, postulación laboral, formularios académicos, consultas y reintento de envíos. Los nuevos scripts se llaman `browser-quality*.cjs`.

## Segunda revisión funcional

Consulta `REVISION-PROFUNDA.md` para las correcciones posteriores, la matriz de los 38 módulos y las nuevas pruebas `deep-*.php` y `browser-deep-*.cjs`.
