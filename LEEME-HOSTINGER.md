# Paquete listo para Hostinger — actualización de tu instalación

Esta distribución contiene el mismo código de la revisión profunda probada. Se excluyen las pruebas de desarrollo y las configuraciones privadas.

## Actualizar region.aplicacion.io

1. En hPanel crea una copia de los archivos y de la base de datos.
2. Descarga este ZIP y extráelo en tu computadora.
3. Abre el administrador de archivos del dominio. La estructura debe quedar así:

```
carpeta-del-dominio/
├── portal-private/
│   ├── config.php       (conserva el que ya tienes)
│   ├── Portal.php
│   ├── Rules.php
│   └── ...
└── public_html/
    ├── index.php
    ├── actualizar.php
    ├── assets/
    └── ...
```

4. Sube el CONTENIDO de la carpeta local portal-private dentro de la carpeta portal-private existente. Reemplaza los archivos de código. Conserva config.php y la carpeta backups. El paquete no contiene config.php.
5. Sube el CONTENIDO de la carpeta local public_html dentro del public_html del dominio. Incluye assets/vendor, .htaccess y .user.ini; activa la visualización de archivos ocultos si hace falta. No crees public_html/public_html.
6. Abre https://region.aplicacion.io/actualizar.php. Introduce el valor install_key de portal-private/config.php y pulsa «Actualizar conservando los datos». Esta clave es distinta de la contraseña MySQL y de tu contraseña de usuario. No la compartas.
7. Abre https://region.aplicacion.io/ y entra con tu usuario y contraseña habituales. No ejecutes install.php ni importes schema.sql sobre tu base existente.
8. Limpia la caché del sitio en hPanel y recarga con Ctrl+F5.

## Prueba rápida después de subir

- Inicio muestra la franja amarilla; Programas no la muestra.
- Crea una noticia de prueba, guárdala y recarga el editor. Publícala y comprueba su aparición desde una ventana privada. Retira la noticia al terminar.
- Envía un mensaje ficticio desde Contacto y confirma su aparición en Mensajes.
- Descarga un documento PDF y comprueba que abre.
- Crea y descarga un respaldo desde el panel.
- Cierra sesión y comprueba que el panel exige iniciar sesión otra vez.

Al terminar elimina public_html/actualizar.php, recuperar.php e install.php. Si necesitas recuperar una cuenta posteriormente puedes volver a subir recuperar.php temporalmente.

## Datos importantes

- Requiere PHP 8.2 o posterior, PDO MySQL, sesiones, Fileinfo y Zlib. No necesita Laravel, Composer, Node ni compilar archivos.
- Conserva la base y las cuentas existentes. No cambies la contraseña MySQL ni la configuración si la instalación actual ya conecta correctamente.
- No coloques portal-private ni este ZIP dentro de public_html. La carpeta privada debe quedar al mismo nivel que public_html.
- El correo saliente sigue pendiente. Los mensajes y las respuestas se registran en el portal.
- Los respaldos automáticos requieren configurar el cron en hPanel; la pantalla de respaldos muestra las instrucciones de tu instalación.
- No se ha actualizado Hostinger desde este entorno. Comprueba allí las extensiones, permisos y funcionamiento después de subir.

Informe de las pruebas: https://github.com/ranyuo/portal/blob/hostinger-revision-profunda/REVISION-PROFUNDA.md
