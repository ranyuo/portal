# Revisión funcional profunda

26 de octubre de 2026. Revisión sobre PHP 8.4, MySQL 8.4 y Chromium, en una instalación local y base de pruebas separadas de Hostinger. Se conserva PHP, MySQL, HTML, CSS y JavaScript, sin Laravel. El correo sigue excluido por indicación del propietario.

## Correcciones adicionales

1. Varias acciones de papelera, duplicación, versiones y temas mostraban éxito aunque el servidor rechazara el guardado. Ahora comprueban el resultado, conservan el contenido del servidor y muestran el error. La activación visual del tema espera la confirmación.
2. Los errores de campos compuestos podían pasar inadvertidos. Se muestran junto al control correspondiente y bloquean el guardado. El borrador de portada se conserva al fallar la petición.
3. Se reforzaron en PHP las reglas de portada, enlaces, fechas, comunicado, institución, licenciamiento, WhatsApp, UIT y estadísticas. Se conserva la lista heredada de nueve beneficios cuando no se modifica; al cambiarla se aplica el máximo de ocho del editor, sin borrar contenido automáticamente.
4. La constancia modular usaba referencias incompatibles entre interfaz y servidor. Ahora se emite para el alumno correcto, se descarga en PDF y se verifica públicamente por código y DNI. PHP determina titular, fecha y emisor; rechaza documentos de otro alumno y modificaciones de documentos emitidos.
5. Un código académico podía sobrescribir otro certificado. Ahora se rechaza la colisión, sin distinguir mayúsculas. No puede retirarse el origen de una constancia vigente; después de anularla se permite la operación correspondiente.
6. Restaurar una versión de página podía recuperar nuevamente el formulario anterior. Ahora carga el contenido elegido y solo lo publica al guardar. Las nuevas versiones de documentos calculan un número único incluso desde una versión con prefijo `v`.
7. Cambiar la contraseña invalidaba también la sesión actual por una consulta durante la auditoría. Se mantiene la sesión actual y se invalidan las otras; se conserva el actor correcto en auditoría.
8. Los beneficiarios de un periodo en borrador podían aparecer públicamente. La publicación y la consulta por DNI requieren un periodo publicado o cerrado y conservan el DNI enmascarado.
9. Un proceso en revisión podía desactivar otro ya publicado. Admisión, matrícula y periodos de becas sustituyen al vigente al publicarse.

10. El foco automático de los formularios podía volver al DNI después de elegir otro campo. Ahora respeta el campo que ya tiene el foco y descarta temporizadores de modales cerrados.

11. Se retiraron instrucciones de mesa de partes y textos de constancias que prometían correos todavía no habilitados. Se indica consultar el seguimiento del portal y descargar la constancia.

## Comprobación por componente del modelo

La matriz identifica los 38 módulos del contrato original. Las operaciones de edición de los 25 editores se comprueban además creando, editando y, donde existe la función, eliminando, restaurando y retirando definitivamente registros sintéticos. No todos los módulos ofrecen las mismas operaciones.

| Componente | Verificación funcional |
|---|---|
| Inicio administrativo | Accesos, indicadores y navegación según perfil |
| Aprobaciones | Enviar, devolver con observación, corregir y aprobar entre cuentas distintas |
| Páginas institucionales | Edición, persistencia, historial y restauración de versión |
| Plana jerárquica | Alta, edición, publicación, papelera y consulta pública |
| Plana docente | Edición, publicación, búsqueda, perfiles y CV |
| Programas de estudio | CRUD, publicación, mallas y PDF |
| Egresados | CRUD, publicación y filtros |
| Admisión | Edición, aprobación, vigencia y conservación del proceso publicado |
| Matrícula | Requisitos, copia de periodo, validación y vigencia |
| Becas | Beneficiarios, periodos, tipos, importación CSV, duplicados, filtros y privacidad |
| Documentos de gestión | Archivos, revisión editorial, nuevas versiones e histórico descargable |
| TUPA | Procedimientos, montos, UIT por año y validación de configuración |
| Estadísticas | Edición por programa, persistencia y rechazo de valores negativos o fraccionarios |
| Licenciamiento | Guardado y restitución; requisitos de estado y resolución |
| Inversiones | CRUD, publicación y navegación pública |
| Convocatorias | CRUD, publicación y navegación pública |
| Noticias | CRUD, categorías, programación, duplicación, papelera, publicación y filtros |
| Eventos | CRUD, publicación y navegación pública |
| Portada | Carrusel, diapositivas, visibilidad de bloques y guardado entre sesiones |
| Mensajes | Envío, validación, respuesta e historial persistente |
| Reclamos | Captcha, adjunto, registro, atención, respuesta y consulta |
| Expedientes | Presentación, adjunto, recepción, derivación, atención y seguimiento |
| Certificados públicos | CRUD, código, DNI enmascarado, anulaciones y exclusión de papelera |
| Enlaces | Edición, publicación y destinos internos; verificación externa limitada por la red |
| Campus y locales | CRUD, publicación y navegación pública |
| Servicios | CRUD, publicación y accesos públicos |
| Configuración institucional | Guardado, fechas, datos institucionales y enlaces permitidos |
| Maestro de alumnos | Alta, edición, importación Excel, duplicados y perfiles |
| Horarios | Sesiones, docentes, ambientes, validación, publicar/retirar y PDF/Excel |
| Certificaciones académicas | Edición, constancia del alumno, PDF, verificación pública y permisos |
| Títulos | Alta, edición, reporte PDF del egresado, verificación pública y sincronización sin colisiones de códigos |
| Bolsa laboral | Empresa, oferta, revisión, postulación y evaluación visible para el alumno |
| Configuración académica | Programas, unidades, módulos, docentes y ambientes |
| Temas y apariencia | Activación, duplicación, edición y rechazo de cambios sin falsa confirmación |
| Usuarios | Crear cuenta, roles, contraseña, bloqueo, desactivación, recuperación con clave privada e invalidación de sesiones |
| Auditoría | Acciones persistentes, consulta, actor y exportación CSV |
| Respaldo | Crear, descargar, subir, restaurar, copia previa y programación |
| CV docente | Guardar, recargar, permisos y descarga de adjunto |

La batería de servidor completa reúne 312 comprobaciones aprobadas, además de la actualización idempotente del esquema. El nuevo recorrido público revisó 73 páginas y variantes, 6.752 apariciones de enlaces y cuatro anchos (320, 375, 768 y 1.440 píxeles), sin enlaces internos rotos, errores JavaScript ni desbordamientos de página.

## Pruebas reproducibles nuevas

| Script | Alcance |
|---|---|
| `deep-contracts.php` | 13 casos inválidos rechazados sin cambios parciales |
| `deep-documents.php` | 8 comprobaciones de titularidad, metadatos, inmutabilidad y anulación |
| `deep-scholarships.php` | 4 comprobaciones de publicación de periodos y consulta por DNI |
| `deep-publication.php` | 6 comprobaciones de vigencia antes y después de publicar |
| `browser-deep-lifecycle.cjs` | 101 operaciones desde los 25 editores, incluida papelera donde corresponde |
| `browser-deep-failures.cjs` | 11 rechazos controlados HTTP 403/409/422/503; sin cambios persistidos ni falso éxito |
| `browser-deep-settings.cjs` | 14 comprobaciones de configuración, campos compuestos, bloques y estadísticas |
| `browser-deep-editorial.cjs` | 11 comprobaciones de aprobación, versiones e históricos con archivos distintos |
| `browser-deep-scholarships.cjs` | 5 comprobaciones de importación, duplicados, filtros y CSV |
| `browser-deep-schedule.cjs` | 5 comprobaciones de publicación/retirada y descargas PDF/Excel |
| `browser-deep-home-blocks.cjs` | 24 comprobaciones: ocultar/mostrar los 11 bloques de Inicio, subir/bajar y verificar el orden desde otra sesión |
| `browser-deep-focus.cjs` | 3 comprobaciones de conservación del foco elegido en formularios |
| `browser-deep-recovery.cjs` | 7 comprobaciones de clave privada, recuperación y revocación de sesiones |
| `browser-deep-accounts.cjs` | 6 comprobaciones de cuentas PHP y sesiones |
| `browser-deep-documents.cjs` | Constancia del alumno y reporte de título del egresado, PDF, persistencia y verificación pública desde otra sesión |

Se ejecutó también la batería anterior de servidor, editores, perfiles, servicios, concurrencia, respaldos, controles públicos y actualizador descrita en `CONTROL-CALIDAD.md` y `VERIFICACION.md`. Las 101 operaciones de ciclo de vida incluyen las operaciones de creación y edición; no deben sumarse como si todas fueran funcionalidades distintas. Las pruebas de errores HTTP provocan rechazos controlados deliberadamente.

## Alcance y despliegue

Estas pruebas comprueban los flujos descritos con datos sintéticos; no garantizan todas las combinaciones posibles ni acreditan la configuración del hosting. No se ha cambiado Hostinger ni su base real. La revisión anterior de 24 enlaces externos obtuvo 10 respuestas HTTP 200 y 14 destinos bloqueados por la red del entorno; esos 14 requieren comprobación desde el navegador del instituto.

El correo saliente y la recuperación por correo siguen pendientes. El cron de respaldos debe configurarse en hPanel. Los documentos e imágenes de ejemplo y los datos institucionales deben revisarse antes de publicar.

Para actualizar, guarda una copia de archivos y base, conserva `portal-private/config.php` y los respaldos privados, sube los archivos del paquete y ejecuta `actualizar.php` siguiendo `INSTRUCCIONES.md`. No reinstales ni subas `tests/`. Esta distribución no incluye contraseñas privadas ni la base usada en las pruebas.
