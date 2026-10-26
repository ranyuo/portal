# Corrección del código de verificación del cargo

Los nuevos expedientes reciben un código aleatorio de 16 caracteres, con comprobación de que no se repita. Antes se generaban 32 caracteres.

Los códigos existentes no cambian: sus enlaces, QR y consultas siguen siendo válidos. Al volver a descargar un cargo antiguo, el PDF conserva el código completo y ajusta su tamaño para no superponerlo al sello.

## Actualizar la instalación existente en Hostinger

1. Guarda una copia de los dos archivos actuales.
2. Del paquete, sube portal-private/Portal.php a esa misma ruta del hosting.
3. Sube portal-private/views/portal.php a esa misma ruta del hosting.
4. No reemplaces config.php ni modifiques la base. Esta corrección no cambia el esquema y no requiere ejecutar instalar/actualizar ni importar SQL.
5. Limpia la caché de hPanel y recarga con Ctrl+F5. Vuelve a descargar el cargo anterior para comprobar su diseño; el PDF que ya tienes en tu computadora no cambia automáticamente.
6. Registra un expediente de prueba para comprobar el nuevo código de 16 caracteres. Consulta un expediente anterior con su código original para verificar compatibilidad.

Si todavía no has subido la versión completa anterior, sigue LEEME-HOSTINGER.md para realizar esa actualización completa. Este ZIP contiene también los demás archivos de la distribución, sin pruebas ni configuración privada.

## Verificación realizada

En PHP/MySQL y Chromium locales se registró un expediente nuevo con adjunto PDF real. Se descargaron los cargos de un expediente nuevo (16 caracteres) y uno anterior (32); se midió el ancho real del texto en jsPDF y se comprobó que termina antes del sello. Ambos códigos permitieron consultar sus respectivos expedientes. También pasaron las pruebas de reclamo con adjunto, descarga administrativa y respaldo SQL. Sintaxis PHP y los diez scripts de la vista comprobados.

Las pruebas no modifican Hostinger. Fuente de la prueba: tests/browser-cargo-code.cjs en la rama hostinger-cargo-pruebas.
