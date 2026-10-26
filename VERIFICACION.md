# Verificación de la migración PHP y MySQL

Comparación con el modelo original `index_pisco_2026.html`. Ejecución local el 26 de octubre de 2026 con PHP 8.4, MySQL 8.4 y Chromium. Hostinger no se ha actualizado desde este entorno.

## Alcance implementado

Se conserva la interfaz HTML, CSS y JavaScript del modelo. PHP controla sesiones, permisos, cuentas, validación, consultas públicas y persistencia; MySQL guarda el contenido compartido, cuentas, archivos binarios y visitas. No requiere Laravel, Composer ni Node en el hosting.

- 11 perfiles: administrador, imagen institucional, secretaría, coordinador, transparencia, atención, directivo, docente, estudiante, egresado y empresa.
- 25 definiciones de editores: noticias, categorías, eventos, inversiones, convocatorias, documentos, tipos documentales, TUPA, jerarquía, docentes/personas, locales, reconocimientos, programas, egresados, certificados, becarios, periodos y tipos de beca, enlaces, servicios, portada, usuarios, páginas, admisión y matrícula.
- Maestro de alumnos, horarios y cruces, publicación de horarios, notas, certificaciones modulares, títulos y verificación pública por código o DNI.
- CV docente y autorización de publicación; perfiles profesionales y archivos CV de estudiantes/egresados; registro y validación institucional de empresas, ofertas, postulaciones y resultados.
- Formularios públicos de contacto, suscripción, expedientes y reclamos; seguimiento con identificación del solicitante; documentos y evidencias compartidos entre equipos.
- Avisos internos, visitas registradas por el servidor, respaldo SQL con archivos y actualizador protegido que conserva datos existentes.

## Evidencia ejecutada

| Comprobación | Resultado |
|---|---|
| Pruebas base del servidor | 31 aprobadas: instalación, autenticación, privacidad, CSRF, concurrencia, formularios y archivos |
| Flujos y permisos del modelo | 51 aprobadas: los 11 perfiles; alcance por persona/programa/empresa; rechazo de cambios no autorizados; calificaciones; horarios; certificados; ofertas/postulaciones; CV; visitas |
| Navegador | 115 comprobaciones aprobadas: módulos administrativos, los 25 editores, las 39 rutas públicas incluidas sus variantes de detalle, acceso por perfil, noticia compartida entre ventanas y franja móvil solo en Inicio; sin errores JavaScript |
| Formularios con documentos | Expediente y reclamo enviados desde la interfaz con PDF real; descarga del adjunto recibido por el administrador; bytes idénticos |
| Perfiles desde la interfaz | CV docente guardado y recargado; CV del alumno subido y recuperado desde MySQL |
| Actualización del esquema anterior | Aplicada dos veces, conservando exactamente el contenido y los hashes de contraseña |
| Actualizador web | Rechaza clave incorrecta y completa la actualización con la clave privada |
| Respaldo SQL | Descargado desde la intranet y restaurado en una base distinta: 0 diferencias de contenido, cuentas y archivos; 4 archivos recuperados con sus mismos hashes |

La prueba general de navegación excluye recursos externos para aislar errores de la aplicación. Una prueba separada descarga las bibliotecas externas mediante TLS verificado y prueba generación de PDF, Excel y QR. No equivale a una revisión visual de todas las imágenes o a probar cada combinación posible de datos de producción.

## Aplicación en Hostinger

Sigue `INSTRUCCIONES.md`. Conserva `portal-private/config.php` y la base existente. Sube ambas carpetas en su ubicación actual y ejecuta `/actualizar.php`; no ejecutes una reinstalación. Crea una copia independiente de archivos y base desde hPanel antes de actualizar.

El tráfico comienza a medirse realmente desde esta versión; no se conservan cifras ficticias como visitas reales. Los datos de ejemplo del prototipo necesitan revisión institucional. Los archivos antiguos que solo estaban guardados en IndexedDB de una computadora no se pueden recuperar desde el servidor: deben volver a adjuntarse desde esa computadora.

El correo electrónico se omite por decisión del usuario. Las respuestas y constancias se guardan en el portal; no se simula su envío. Las cuentas existentes conservan su contraseña y su rol administrador; los demás perfiles se crean y vinculan a alumnos, docentes, programas o empresas reales desde Usuarios.
