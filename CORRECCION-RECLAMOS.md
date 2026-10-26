# Corrección del Libro de Reclamaciones

Incluye las correcciones anteriores del cargo de Mesa de Partes.

## Actualizar Hostinger conservando tus datos

1. Guarda una copia de archivos y base desde hPanel.
2. Descarga y extrae este paquete. Sube el contenido de portal-private a la carpeta portal-private existente. Conserva config.php y backups; el paquete no incluye tu configuración privada.
3. Vuelve a subir public_html/actualizar.php al public_html del dominio si lo eliminaste después de la instalación.
4. Abre https://region.aplicacion.io/actualizar.php e introduce tu install_key. Pulsa Actualizar conservando los datos. Este paso asigna un código de verificación a cada reclamo anterior que todavía no lo tiene. Conserva sus números RCL, documentos, contenido, usuarios, contraseñas y roles. La actualización puede repetirse sin cambiar los códigos ya asignados.
5. Limpia la caché de hPanel y abre el portal desde una ventana privada.
6. Para un reclamo anterior consulta con su seguimiento y el documento completo del reclamante; descarga otra vez la constancia. Aparecerán el código de verificación de 16 caracteres y el QR.
7. Al terminar elimina actualizar.php del directorio público. No reinstales ni importes schema.sql sobre tu base existente. Si restauras una copia anterior a esta versión, vuelve a ejecutar la actualización antes de emitir las nuevas constancias.

Los archivos de código modificados son Portal.php, Schema.php, Services.php y views/portal.php, dentro de portal-private. No hace falta configurar correo.

## Uso de la búsqueda

Introduce el número de seguimiento (ejemplo RCL-2026-00001) y el documento completo del reclamante o el código de verificación de su constancia. El número de folios, como 01, no identifica al reclamante: el formulario ahora lo explica y evita dejar resultados anteriores visibles.

Se toleran mayúsculas/minúsculas y ceros iniciales del correlativo. Se mantienen referencias anteriores LR y RCL. Un código o documento incorrecto no revela el registro. El QR lleva el seguimiento y una verificación aleatoria; no incluye el DNI en la URL y permite consultar desde otra sesión sin iniciar sesión.

## Constancias

Tanto la constancia ciudadana como la hoja administrativa incluyen el código de verificación y el QR. Los nuevos reclamos y quejas reciben el código desde PHP, se guarda en MySQL y no puede editarse desde la gestión de reclamos. Los avisos explican que el correo continúa pendiente y la respuesta se consulta en el portal.

## Pruebas realizadas

- 11 casos de servidor: actualización de registros anteriores, conservación de contenido y cuentas, idempotencia, consulta por documento y verificación, normalización, rechazo de 01/código incorrecto, protección contra edición y pasaporte con distintas mayúsculas.
- Flujos reales de Reclamo y Queja desde sus formularios: adjunto, persistencia, PDF, QR, búsqueda con documento, seguimiento desde otra sesión y rechazo de datos incorrectos.
- Descarga de la hoja de un reclamo anterior desde su botón administrativo y consulta de su QR.
- QR de ambos PDF decodificados con un lector independiente. El enlace leído del PDF abrió el seguimiento correcto en una sesión nueva.
- 11 regresiones y 8 pruebas de consultas existentes aprobadas. Actualizador web probado con clave incorrecta y válida. Sintaxis PHP y los diez scripts de la vista comprobados.

Las pruebas se hicieron en PHP/MySQL y Chromium locales con datos sintéticos. No se modificó Hostinger desde este entorno.

Evidencias y PDF de prueba: https://github.com/ranyuo/portal/tree/hostinger-reclamos-pruebas/evidencias/reclamos
