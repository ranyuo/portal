# Portal Pisco para Hostinger: PHP y MySQL, sin Laravel

Esta es la versión elegida para desplegar en hPanel. No utiliza Laravel, Composer, npm, un servidor Node ni acceso SSH. Necesita PHP 8.2 o posterior, PDO MySQL, sesiones y Fileinfo. Se valida con PHP 8.4 y MySQL 8.4; las consultas no usan funciones exclusivas de MySQL 8.

## Instalar en `region.aplicacion.io`

1. En hPanel entra a **Databases → Management**. Crea una base MySQL y un usuario con acceso a esa base. Copia los nombres completos con el prefijo de Hostinger. Guarda la contraseña en privado.
2. Abre **Files → File manager**. Usa un sitio de prueba o guarda una copia de los archivos del sitio antes de reemplazar su página principal.
3. Ve a la carpeta del dominio, **un nivel por encima de `public_html`**. Sube y extrae `portal-hostinger-php-mysql.zip` allí. La estructura final debe ser:

   ```text
   carpeta-del-dominio/
   ├── portal-private/
   │   ├── config.example.php
   │   ├── Portal.php
   │   ├── bootstrap.php
   │   ├── schema.sql
   │   ├── data/
   │   └── views/
   └── public_html/
       ├── index.php
       ├── install.php
       ├── accounts.php
       ├── portal-server.js
       ├── forms.css
       ├── .htaccess
       └── .user.ini
   ```

   **No coloques `portal-private` dentro de `public_html`.** No subas el ZIP al directorio público: contiene el código privado y los datos iniciales.
4. Dentro de `portal-private`, copia `config.example.php` como **`config.php`**. Edita únicamente estos valores desde el administrador de archivos:
   - `db_host`: normalmente `localhost`; usa el servidor que indique hPanel.
   - `db_name`: nombre completo de la base MySQL.
   - `db_user`: nombre completo del usuario de la base.
   - `db_password`: la contraseña elegida al crearla.
   - `install_key`: una clave aleatoria privada de al menos 32 caracteres. Puedes generarla con un gestor de contraseñas. Se pedirá una sola vez al instalar.

   Conserva las comillas de PHP. Si un valor contiene una comilla simple, escríbela como `\'`; una barra invertida como `\\`. Mantén `secure_cookies => true`, porque el sitio usa HTTPS. No compartas este archivo ni lo subas a GitHub.
5. Abre **`https://region.aplicacion.io/install.php`**. Introduce la clave de instalación y el nombre, usuario, correo y contraseña de tu administrador. La contraseña debe tener entre 12 y 72 caracteres (máximo 72 bytes).
6. Pulsa **Instalar y crear mi cuenta**. El instalador crea las tablas y carga el contenido inicial. No hay que importar un SQL manualmente. Al existir una cuenta, el instalador se bloquea y no permite reinstalar encima de ella.
7. Abre **`https://region.aplicacion.io/`**. Entra por Intranet con tu cuenta. Puedes borrar `public_html/install.php` después de completar la instalación.

No cambies el nombre de `portal-private`, salvo que adaptes las rutas de los tres PHP públicos. Esta distribución está preparada para la raíz del dominio o subdominio, no para una subcarpeta de la URL.

## Comprobar que funciona

1. Verifica la franja amarilla en Inicio y su ausencia en Programas.
2. Inicia sesión, abre Noticias y cambia el título de una noticia de prueba.
3. Abre una ventana privada y visita Noticias: debe mostrar el título guardado. Recarga para confirmar que viene de MySQL.
4. Envía un mensaje desde Contacto en esa ventana privada. En el panel del administrador debe aparecer en Mensajes.
5. Registra un reclamo ficticio, guarda el código y comprueba el seguimiento con el documento indicado. No uses datos personales reales para las pruebas.
6. Cierra sesión y confirma que no puedes volver al panel sin identificarte.

## Qué incluye esta versión

- Diseño e interfaz del portal, incluida la franja amarilla de admisión únicamente en el inicio.
- Sesiones PHP de servidor y cuentas administradoras con `password_hash` / `password_verify`.
- Persistencia compartida en MySQL con PDO y consultas preparadas.
- Protección CSRF, caducidad de sesión y límites de intentos.
- Edición administrativa del contenido, con detección de conflictos si dos personas guardan a la vez.
- Formularios públicos de contacto, suscripción, reclamos y expedientes, con validación en servidor, revisión de archivos y seguimiento.
- Instalador web protegido por clave privada; creación de administradores y cambio de contraseña en `accounts.php`.

El contenido se conserva en la tabla JSON `pisco_state` para mantener compatible el editor existente. Las cuentas y los contadores de solicitudes tienen tablas propias. Los datos iniciales vienen del prototipo, no de una base de producción ni de los navegadores que usaron la página antigua.

Los perfiles específicos de docentes, estudiantes y empresas y el envío de correos siguen pendientes. El autorregistro de empresas muestra un contacto institucional. No hay entrega de correo habilitada: los mensajes y constancias se guardan en la aplicación. PDF, Excel, QR, fuentes e imágenes siguen utilizando proveedores externos.

## Si aparece un error

- **Primero configura...**: falta `portal-private/config.php` o la carpeta no está en el lugar indicado.
- **No se pudo completar...**: revisa en hPanel los nombres completos de base y usuario, su contraseña y el servidor MySQL. Si sigue fallando, consulta el registro de errores de PHP. La aplicación no muestra credenciales ni errores internos al visitante.
- **La clave de instalación no coincide**: usa exactamente el valor `install_key` de tu configuración.
- **Sesión caducada al instalar**: abre la URL con HTTPS y recarga. Las cookies seguras requieren HTTPS.
- **Ves la página anterior**: comprueba que extrajiste los archivos en el `public_html` de este dominio y que se incluyó `.htaccess`. Conserva una copia y retira un posible `index.html` anterior que tenga prioridad.
- **Adjuntos demasiado grandes**: en PHP Options confirma `post_max_size=64M`, `upload_max_filesize=48M` y `memory_limit=256M`. La distribución incluye `.user.ini` con estos valores; Hostinger puede tardar unos minutos en aplicarlos. El límite total de adjuntos por solicitud es 40 MB.

Las tablas usan el prefijo `pisco_`; el instalador no borra tablas de otras aplicaciones. Evita instalar sobre otra copia de este mismo portal. Haz copias de seguridad de la base desde hPanel y conserva también `portal-private/config.php` en un lugar privado. No confíes solo en los archivos del sitio: el contenido compartido está en MySQL.

