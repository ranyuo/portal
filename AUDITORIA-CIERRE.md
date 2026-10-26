# Cierre de la migración del portal

La distribución final usa PHP y MySQL con HTML, CSS y JavaScript para conservar la interfaz del modelo. Se han implementado los módulos del modelo original, incluidas sus operaciones de servidor y permisos; el correo es el único servicio excluido por decisión del propietario.

## Hallazgos de la auditoría anterior resueltos

- Las noticias programadas no se entregan públicamente antes de su fecha; las fechas mantienen el instante al editar.
- Los documentos históricos siguen disponibles según la publicación y sus versiones. Los perfiles sujetos a aprobación no pueden eludirla mediante Histórico ni editar contenido publicado sin revisión.
- Los horarios publicados se vuelven a validar al cambiar sesiones, docentes, ambientes o disponibilidad relevante.
- Los avisos laborales usan el programa correcto y las ofertas modificadas por empresas vuelven a revisión institucional.
- Captcha, aceptación, declaración, tipos de documento y límites de archivos se validan en PHP; no dependen solo de JavaScript.
- La auditoría registra acciones por registro y operaciones de cuentas, acceso, archivos y respaldos; dispone de consulta completa y CSV.
- Existen copia portable y restauración desde el portal con verificación de integridad, transacción y copia previa. La programación diaria incluye retención y ejecutor CLI para cron de hPanel.
- El guardado usa cambios por registro y campos, con detección de conflictos sobre el mismo registro. La información compartida se guarda en MySQL; el almacenamiento del navegador se limita a preferencias de interfaz.
- Los formularios conservan el borrador al fallar y muestran el resultado real del servidor. Las respuestas e historiales de servicios se guardan y recargan correctamente.
- Los reportes PDF incluyen todas las filas; Excel y QR funcionan con bibliotecas incluidas en el paquete.
- Los intentos fallidos de acceso, bloqueos temporales e invalidación de sesiones se controlan en servidor. Los respaldos restaurados invalidan sesiones anteriores.

Los datos compatibles del editor permanecen en JSON dentro de MySQL; cuentas, archivos, visitas, auditoría y ajustes tienen tablas separadas. Esta decisión mantiene compatibilidad con el modelo y no deja la persistencia en el navegador.

## Pendiente y despliegue

Pendiente: configurar correo real y recuperación por correo cuando se autorice. La recuperación del propietario con clave privada está implementada.

La subida de esta versión a Hostinger y la ejecución de `actualizar.php` corresponden al despliegue, todavía no realizado desde este entorno. Conserva `config.php`, los respaldos privados y tu base existente. No reinstales. La programación automática requiere dar de alta el cron indicado en hPanel.

La evidencia y los scripts están en `VERIFICACION.md` y `tests/`. Los datos y documentos de ejemplo heredados deben ser revisados por la institución; no se presentan como documentos oficiales nuevos.

La revisión funcional posterior está documentada en `CONTROL-CALIDAD.md`, con los hallazgos adicionales corregidos y sus pruebas reproducibles.
