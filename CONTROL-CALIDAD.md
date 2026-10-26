# Control de calidad antes del despliegue

Fecha: 26 de octubre de 2026. Tecnología comprobada: PHP 8.4, MySQL 8.4 y Chromium. Las pruebas se ejecutaron en una base desechable separada del sitio de Hostinger, con cuentas y documentos sintéticos. El correo sigue excluido por indicación del propietario.

## Recorrido de enlaces y pantallas

| Recorrido | Resultado |
|---|---|
| Portal público | 73 páginas y variantes; 6.752 apariciones de enlaces. 133 destinos distintos; ningún enlace interno roto ni error JavaScript |
| Diseño público | Las 73 variantes comprobadas a 320, 375, 768 y 1.440 píxeles, sin desbordamiento horizontal de la página |
| Administrador | 585 pantallas y variantes, 31.140 apariciones de enlaces, sin destinos administrativos rotos, errores JavaScript o desbordamientos en los módulos revisados |
| Otros diez perfiles | 120 pantallas, 2.427 apariciones de enlaces; sin enlaces a módulos denegados desde sus menús y accesos revisados |
| Controles públicos | Menú móvil, carrusel, búsqueda, paginación, filtros de noticias y documentos, categorías y perfiles docentes, mallas desplegables, PDF de programas y documento aprobado desde MySQL |

Las apariciones de enlaces incluyen elementos repetidos, como cabecera y pie. No son miles de destinos distintos. La revisión administrativa incluye listados, fichas, submódulos y variantes por perfil; no representa una garantía sobre cualquier combinación posible de datos.

## Pruebas funcionales

- 281 comprobaciones de servidor: base, once perfiles, migración, CRUD académico, regresiones y consultas de certificados. Además, actualización idempotente del esquema antiguo conservando contenido y hashes de contraseña.
- 50 operaciones de creación y edición desde los 25 editores originales; persistencia confirmada consultando el servidor.
- 115 comprobaciones generales de navegación, publicación entre ventanas, perfiles y franja amarilla únicamente en Inicio.
- Edición y restitución desde diez formularios académicos, registro de título y creación/eliminación de ambiente mediante confirmación real.
- 13 comprobaciones adicionales de servicios: validación, reintento tras captcha incorrecto, suscripción, motivo de anulación, consulta sin datos obsoletos, privacidad de adjuntos, descarga con sesión cerrada y seguimiento de expedientes/reclamos.
- 7 comprobaciones de importación y empleo: plantilla Excel, alta y actualización sin duplicar un DNI, exclusión de fila inválida, retirada del registro de prueba, oferta publicada, postulación de alumno y evaluación de empresa visible en otra sesión.
- 25 controles públicos adicionales: paginación, búsqueda, perfiles docentes, desplegables y descargas PDF, más consulta por DNI con identificación enmascarada.
- PDF de 300 filas en varias páginas; Excel generado y leído; QR generado con las bibliotecas locales incluidas.
- 20 ediciones simultáneas desde dos perfiles sin pérdida de cambios en registros distintos.
- Adjuntos de expedientes y reclamos recibidos y descargados con los mismos bytes; CV docente y de alumno guardados y recargados.
- Copia portable creada, descargada, subida y restaurada desde la interfaz; cuentas y contenido recuperados; auditoría y programación comprobadas. El cron de hPanel se configura al desplegar.
- Actualizador web: rechaza clave incorrecta y completa la actualización con la clave privada. Revisión de sintaxis PHP y JavaScript incluida.

Los scripts reproducibles están en `tests/`. No se suben ni se ejecutan contra la base de producción. Las pruebas requieren una configuración privada `config.test.php` y una base cuyo nombre termine en `_test`.

## Fallos encontrados y corregidos en esta revisión

1. Desbordamiento móvil del registro de empresas: los textos largos y la columna lateral ahora se ajustan al ancho disponible.
2. Agenda y accesos rápidos ofrecían enlaces administrativos a perfiles sin permiso. Ahora muestran destinos públicos o accesos permitidos, manteniendo la autorización del servidor. Los perfiles de consulta tampoco reciben controles activos para aprobar empresas, cambiar criterios o importar alumnos.
3. Una función intermedia no devolvía el resultado del guardado académico. Los formularios ahora reciben la confirmación real y se cierran al guardar; las confirmaciones conservan el error cuando falla el servidor.
4. La consulta por código omitía detalle y motivo de anulación, y podía devolver certificados de la papelera. Se conservan esos datos de verificación y se excluyen los registros eliminados tanto por código como por DNI.
5. Consultas públicas realizadas con sesión administrativa podían reemplazar registros completos por versiones resumidas. Ahora no modifican la información privada del editor. La pantalla muestra la respuesta actual del servidor y no reutiliza un resultado antiguo después de un rechazo.
6. El plazo local entre envíos empezaba también tras intentos fallidos. Ahora se registra después de un envío exitoso, permitiendo corregir la suma sin esperar ni perder los campos. La protección de PHP sigue activa; se limpian las marcas de error al corregir.
7. La descarga de un adjunto con sesión cerrada podía dejar un error JavaScript sin tratar. Ahora muestra un aviso y mantiene la restricción de acceso. Se retiraron instrucciones de descarga que hacían referencia a la plataforma del prototipo.

8. El cierre exterior de los menús desplegables solo atendía el primer clic. Ahora funciona durante toda la navegación y actualiza el estado accesible; se comprueba también el cierre con Escape.

## Límites y aplicación en Hostinger

Se consultaron 24 enlaces externos públicos mediante HEAD: 10 respondieron HTTP 200 y 14 no pudieron comprobarse porque la red de este entorno bloqueó sus dominios. Esto no demuestra que esos sitios estén caídos. Los enlaces de compartir no se utilizaron para enviar mensajes. La disponibilidad de redes sociales, mapas, aula virtual y servicios ministeriales depende de terceros y requiere una comprobación desde el navegador del instituto.

No se ha subido esta versión al hosting ni se ha modificado su base real. Antes de desplegar, guarda una copia de archivos y base en hPanel. Conserva `portal-private/config.php`, los respaldos privados y los usuarios existentes; sigue `INSTRUCCIONES.md` y ejecuta `actualizar.php`, sin reinstalar. El correo saliente y la recuperación por correo siguen pendientes por acuerdo con el propietario.

Los datos de ejemplo del modelo deben sustituirse o corregirse con información institucional real. La validación rechaza, por ejemplo, un RUC de muestra cuyo dígito verificador sea incorrecto; no se relaja esa regla para aceptar datos inválidos.

## Segunda revisión funcional

Consulta `REVISION-PROFUNDA.md` para las correcciones posteriores, la matriz de los 38 módulos y las nuevas pruebas `deep-*.php` y `browser-deep-*.cjs`.
