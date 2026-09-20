# Despliegue En Servidor

Esta guía cubre los dos modos de producción: Docker Compose/Coolify y despliegue directo en cPanel. Ambos mantienen el mismo código PHP, la misma base de datos y el almacenamiento de PDFs fuera del document root.

## Requisitos

- Servidor Linux con Docker Engine y Docker Compose Plugin.
- Proxy inverso/plataforma que enrute tráfico al servicio `nginx` en el puerto interno `80`.
- Acceso SMTP válido.
- Acceso SSH al servidor.

## Subida Inicial

1. Copia el proyecto al servidor, por ejemplo en `/opt/bitacora-mw`.
2. No subas un `.env` real al repositorio ni lo compartas por chat/correo.
3. En el servidor crea el archivo de variables:

```bash
cd /opt/bitacora-mw
cp .env.production.example .env
nano .env
```

4. Cambia todas las credenciales `change_this_*`.
5. Genera `BITACORA_DRAFT_KEY_BASE64` con `openssl rand -base64 32`, guárdala como secreto y no la cambies mientras existan borradores que deban recuperarse.
6. Si usas un nombre distinto de `.env`, agrega `--env-file ruta/al/archivo` a cada comando Compose.
7. Si el sitio estará detrás de HTTPS, deja `SESSION_SECURE=true`. Si estás probando temporalmente solo por HTTP, usa `SESSION_SECURE=false` hasta activar HTTPS.

## Configuración En Coolify

1. Crea un recurso de tipo Docker Compose.
2. Selecciona `docker-compose.prod.yml` como archivo Compose; no uses el `docker-compose.yml` de desarrollo.
3. Configura el dominio/proxy para el servicio `nginx` en el puerto interno `80`.
4. Registra en Coolify las variables de `.env.production.example` como variables de entorno o secretos. No subas el archivo `.env` real.
5. Genera `BITACORA_DRAFT_KEY_BASE64` con `openssl rand -base64 32` y conserva esa clave en Coolify para futuras actualizaciones.

El Compose de producción no publica puertos al host: Coolify debe enrutar internamente al servicio `nginx`. `app` solo expone PHP-FPM en `9000` y no es un destino HTTP válido.

## Configuración En cPanel

cPanel usa Apache y PHP-FPM/EA-PHP en lugar de los servicios Docker. El repositorio incluye `public/.htaccess` y `.cpanel.yml` para este modo, pero Docker y Coolify no se modifican.

### Requisitos

- cPanel Git Version Control habilitado.
- PHP 8.4 seleccionado para el dominio y disponible para CLI, porque `composer.json` requiere PHP `^8.4`.
- Extensiones PHP `pdo_mysql`, `mbstring`, `gd`, `intl`, `zip`, `bcmath`, `openssl`, `xml` y `zlib`.
- Composer disponible para la versión PHP seleccionada.
- `rsync` disponible en `/usr/bin/rsync` o en una ruta configurada mediante `CPANEL_RSYNC_BIN`.
- Apache con `mod_rewrite`, `mod_headers` y soporte para `.htaccess` mediante `AllowOverride FileInfo,Indexes,Limit`.
- Una base MySQL creada desde cPanel y una cuenta SMTP válida.

### Estructura Y Document Root

El despliegue debe conservar la raíz completa de la aplicación fuera del document root público. El valor predeterminado de `.cpanel.yml` es:

```text
/home/usuario/apps/bitacora-mw/
```

El dominio debe apuntar exactamente a:

```text
/home/usuario/apps/bitacora-mw/public
```

No apuntes el dominio a la raíz `bitacora-mw`, porque expondría archivos de configuración, migraciones y scripts CLI.

### Variables Y Secretos

1. Crea la base de datos y el usuario desde **MySQL Databases**. Usa los nombres con el prefijo de la cuenta cPanel.
2. Crea el destino privado y copia la plantilla como `.env` antes del primer despliegue:

```bash
mkdir -p /home/usuario/apps/bitacora-mw
cp /home/usuario/repositories/bitacora-mw/.env.cpanel.example /home/usuario/apps/bitacora-mw/.env
chmod 600 /home/usuario/apps/bitacora-mw/.env
```

3. Reemplaza las credenciales MySQL y SMTP, cambia `CPUSER` en `BITACORA_STORAGE_PATH` y conserva esa ruta fuera de `public/`.
4. Genera `BITACORA_DRAFT_KEY_BASE64` con `openssl rand -base64 32` y respáldala junto con la base de datos.
5. Mantén `SESSION_SECURE=true` cuando el dominio tenga HTTPS.
6. No subas `.env` al repositorio ni lo copies mediante `.cpanel.yml`.

### Git Y Despliegue

1. Crea el repositorio en **Git Version Control** usando una ruta privada, por ejemplo `/home/usuario/repositories/bitacora-mw`.
2. Confirma que `.cpanel.yml` esté en la raíz del repositorio.
3. Si el servidor usa rutas diferentes, ajusta `DEPLOYPATH`, `CPANEL_PHP_BIN`, `CPANEL_COMPOSER_BIN` y `CPANEL_RSYNC_BIN` al inicio del archivo.
4. Crea y configura `.env` antes del primer despliegue, porque las tareas ejecutan Composer y migraciones.
5. Ejecuta **Deploy HEAD Commit** desde cPanel o usa el webhook configurado para despliegue automático.

`.cpanel.yml` valida PHP, Composer, rsync y `.env` antes de modificar producción. Después valida y audita Composer, sincroniza explícitamente `public/`, `database/` y `scripts/`, preserva `public/uploads`, y finalmente aplica migraciones e importa destinatarios.

### Primer Arranque

Después del primer despliegue, crea el administrador por SSH desde la raíz desplegada:

```bash
cd /home/usuario/apps/bitacora-mw
read -s BITACORA_ADMIN_PASSWORD
export BITACORA_ADMIN_PASSWORD
export BITACORA_ADMIN_USERNAME=admin
export BITACORA_ADMIN_EMAIL=admin@example.com
/usr/local/bin/ea-php84 scripts/create_admin.php
unset BITACORA_ADMIN_PASSWORD
```

Si el binario PHP tiene otra ruta, usa el mismo valor configurado en `CPANEL_PHP_BIN`.

### Correos Y Cron

El ejemplo cPanel usa `BITACORA_MAIL_ASYNC=false`, por lo que el envío ocurre durante la petición y no necesita un worker permanente. Para limpiar PDFs y borradores, crea estos cron jobs desde **Cron Jobs** usando la ruta PHP de cPanel:

```cron
0 * * * * /usr/local/bin/ea-php84 /home/usuario/apps/bitacora-mw/database/cleanup_bitacora_pdfs.php >> /home/usuario/bitacora-pdf-cleanup.log 2>&1
15 * * * * /usr/local/bin/ea-php84 /home/usuario/apps/bitacora-mw/database/cleanup_bitacora_drafts.php >> /home/usuario/bitacora-draft-cleanup.log 2>&1
```

Si se activa `BITACORA_MAIL_ASYNC=true`, agrega además un cron frecuente para `scripts/process_bitacora_email_queue.php`. No ejecutes los bucles Docker `worker_loop.sh` o `maintenance_loop.sh` como procesos web.

```cron
*/5 * * * * /usr/local/bin/ea-php84 /home/usuario/apps/bitacora-mw/scripts/process_bitacora_email_queue.php >> /home/usuario/bitacora-mail-queue.log 2>&1
```

### Verificación cPanel

Comprueba que `/healthz` y `/readyz` respondan correctamente, que el login cree sesión y que un envío genere PDF y correo. También verifica que estas rutas no sean accesibles directamente:

```text
/.env
/composer.json
/config/env.php
/vendor/
/storage/
```

Deben responder con rechazo o no encontrarse. Revisa además que el document root termine en `/public` y que `storage/bitacoras_pdf` sea escribible por el usuario PHP.

## Construcción Y Arranque

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

La imagen de producción valida el proyecto, instala dependencias Composer y ejecuta `composer audit --locked` durante el build desde `composer.lock` en la raíz. Composer no se instala en la imagen runtime. El servidor necesita salida a internet para descargar dependencias la primera vez. El primer build puede tardar varios minutos porque compila extensiones PHP como `gd`.

## Migraciones De Base De Datos

El servicio `app` ejecuta el migrador antes de iniciar PHP-FPM. Para verificarlo o ejecutarlo manualmente:

```bash
docker compose -f docker-compose.prod.yml exec app php database/migrate.php
```

El migrador obtiene un bloqueo de MySQL, crea `schema_migrations` y aplica en orden todos los archivos de `database/migrations/`, actualmente `000` a `019`. Al iniciar `app`, el importador idempotente `scripts/import_bitacora_recipients.php` traslada la configuración PHP actual a la base de datos y activa el modo administrado por BD una sola vez por empresa. Antes de desplegar una versión con migraciones nuevas sobre una base existente, realiza backup.

Una base vacía queda con empresas y sedes, pero sin credenciales predeterminadas. Crea el primer administrador usando variables de entorno y `scripts/create_admin.php`, tal como se documenta en `README.md`.

## Verificación

```bash
docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml exec nginx nginx -t
docker compose -f docker-compose.prod.yml exec app sh -lc "find public database scripts tests -name '*.php' -print0 | xargs -0 -n1 php -l"
docker compose -f docker-compose.prod.yml exec app php tests/run.php
docker compose -f docker-compose.prod.yml exec app php tests/section_mail_privacy_test.php
docker compose -f docker-compose.prod.yml exec app php tests/integration.php
docker compose -f docker-compose.prod.yml exec app php tests/recipient_admin_integration.php
```

La validación y auditoría de Composer se ejecutan automáticamente durante el build de `Dockerfile.prod`; un build exitoso confirma ambas comprobaciones.

Luego abre el dominio configurado en tu proxy/plataforma.

## Exposición HTTP

`docker-compose.prod.yml` no publica `ports` en el host. Expone el puerto interno `80` del servicio `nginx` para que Coolify u otro proxy inverso lo enrute sin chocar con el puerto `80` del servidor.

En Coolify, configura el dominio/proxy apuntando exactamente al servicio `nginx` y al puerto interno `80`. No apuntes al servicio `app` ni al puerto `9000`: `app` ejecuta PHP-FPM, no HTTP, y el proxy terminará en `504 Gateway Timeout`.

En la pantalla del recurso Compose, asigna el dominio al servicio `nginx`. Como `nginx` escucha en el puerto interno `80`, el dominio puede quedar como `https://tu-dominio.com` sin sufijo de puerto.

El compose de producción usa la red default del stack para que el proxy de Coolify pueda alcanzar el servicio `nginx`. No agregues una red custom para estos servicios salvo que también configures el proxy para esa red.

El endpoint de salud HTTP es:

```text
/healthz
```

Debe responder `ok` desde el servicio `nginx`.

El endpoint de readiness es:

```text
/readyz
```

También debe responder `ok`. Valida MySQL, todas las migraciones, escritura real en almacenamiento, el keyring y una muestra cifrada por cada versión usada. `/php-healthz.php` se conserva como alias compatible.

Las vistas activas bloquean scripts inline. La CSP admite estilos inline solo para el funcionamiento de SweetAlert2, Select2 y las mutaciones visuales de jQuery. Si agregas comportamiento frontend, mantén JavaScript y CSS propios bajo `public/resources/`.

## Diagnóstico De 504 En Coolify

Si el despliegue termina pero el navegador muestra `504 Gateway Timeout`, revisa primero la configuración del dominio en Coolify:

- El dominio debe estar asignado al servicio `nginx`, no a `app`.
- El puerto interno debe ser `80`, no `9000`.
- El endpoint `/healthz` debe responder `ok`.
- El endpoint `/readyz` debe responder `ok`.

Comandos útiles desde el servidor o consola de Coolify:

```bash
docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml logs --tail=120 nginx app
docker compose -f docker-compose.prod.yml exec nginx wget -q -O - http://127.0.0.1/healthz
docker compose -f docker-compose.prod.yml exec nginx wget -q -O - http://127.0.0.1/php-healthz.php
```

Si `/healthz` responde dentro del contenedor pero el dominio externo sigue en `504`, el problema está en el enrutamiento del proxy/Coolify hacia el servicio o puerto incorrecto.

Si `/healthz` responde pero `/php-healthz.php` falla, el problema está entre `nginx` y PHP-FPM (`app:9000`) o en el arranque del servicio `app`.

Si vas a ejecutar sin proxy inverso ni Coolify, agrega un override local no versionado, por ejemplo `docker-compose.expose.yml`:

```yaml
services:
  nginx:
    ports:
      - "80:80"
```

Y arranca con:

```bash
docker compose -f docker-compose.prod.yml -f docker-compose.expose.yml up -d --build
```

## Diagnóstico De MySQL Unhealthy

Si el panel de despliegue reporta `container db is unhealthy`, revisa los logs del servicio `db`:

```bash
docker compose -f docker-compose.prod.yml logs --tail=120 db
```

Casos frecuentes:

- Primera inicialización lenta en servidores pequeños: espera unos minutos y revisa si el contenedor termina quedando `healthy`.
- `MYSQL_USER=root`: no uses `root` como usuario de aplicación; usa un usuario como `bitacora_user` y deja `MYSQL_ROOT_PASSWORD` solo para administración.
- Variables vacías o mal escapadas: confirma que `.env` tenga `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD` y `MYSQL_ROOT_PASSWORD` con valores reales.
- Contraseñas con caracteres especiales en `.env`: si usas `#`, espacios o comillas, envuélvelas entre comillas.

## HTTPS

Recomendado: usar un proxy inverso con certificados TLS, por ejemplo Nginx Proxy Manager, Traefik, Caddy o Nginx + Certbot.

Cuando HTTPS esté activo:

```env
SESSION_SECURE=true
SESSION_IDLE_TIMEOUT_SECONDS=3600
SESSION_MAX_LIFETIME_SECONDS=43200
```

Después reinicia:

```bash
docker compose -f docker-compose.prod.yml up -d
```

## Archivos Persistentes

Producción usa estos volúmenes Docker:

- `bitacora_db_data`: datos MySQL.
- `bitacora_storage`: PDFs, logs PHP y temporales de PDF.
- `bitacora_uploads`: archivos subidos bajo `public/uploads`.

No borres estos volúmenes salvo que tengas backup.

## Correos Asíncronos

Producción usa por defecto el envío asíncrono:

```env
BITACORA_MAIL_ASYNC=true
```

El servicio `worker` incluido en Compose procesa la cola continuamente. Para ajustar cada lote:

```env
BITACORA_MAIL_WORKER_LIMIT=20
```

Para una ejecución manual de diagnóstico:

```bash
docker compose -f docker-compose.prod.yml exec worker php scripts/process_bitacora_email_queue.php
```

El worker lee `bitacora_email_queue`, usa la configuración SMTP centralizada y actualiza `bitacora_envios` cuando el correo principal o por sección se envía o falla definitivamente. Su healthcheck exige un ciclo reciente de acceso a la cola.

## Borradores Persistentes

Los borradores se cifran en MySQL, tienen una retención predeterminada de 30 días y se aíslan por usuario, empresa y tipo de formulario. Incluye todas las claves vigentes en el procedimiento de recuperación de secretos: restaurar la base sin ellas deja los borradores cifrados irrecuperables.

Rotación compatible:

1. Conserva la clave actual como versión `1` en `BITACORA_DRAFT_KEYRING_JSON`.
2. Agrega una nueva clave, por ejemplo versión `2`, y cambia `BITACORA_DRAFT_ACTIVE_KEY_VERSION=2`.
3. Reconstruye `app` y confirma que `/readyz` responde `ok`.
4. Ejecuta `docker compose -f docker-compose.prod.yml exec app php database/rotate_bitacora_draft_keys.php`.
5. Confirma con `scripts/bitacora_status.php` que no quedan borradores en la versión anterior antes de retirar esa clave.

Ejemplo conceptual, usando secretos reales distintos:

```env
BITACORA_DRAFT_KEY_BASE64=<clave-v1>
BITACORA_DRAFT_KEYRING_JSON={"1":"<clave-v1>","2":"<clave-v2>"}
BITACORA_DRAFT_ACTIVE_KEY_VERSION=2
```

El servicio `maintenance` elimina cada hora PDFs y borradores expirados. Para ejecutar únicamente la limpieza de borradores:

```bash
docker compose -f docker-compose.prod.yml exec app php database/cleanup_bitacora_drafts.php
```

`worker` y `maintenance` publican healthchecks basados en heartbeats. Consulta colas, borradores por versión de clave, bloqueos y reservas incompletas con:

```bash
docker compose -f docker-compose.prod.yml exec app php scripts/bitacora_status.php
```

Una reserva con `delivery_unknown` significa que SMTP pudo aceptar el correo antes de una interrupción. Debe verificarse en historial/proveedor antes de repetirla; esta ambigüedad no puede eliminarse transaccionalmente con SMTP convencional.

## Backup

Base de datos:

```bash
docker compose -f docker-compose.prod.yml exec -T db sh -lc 'mysqldump --single-transaction --quick --routines --events -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' > backup_bitacora.sql
```

Volúmenes de archivos:

```bash
docker run --rm -v bitacora-mw_bitacora_storage:/data -v "$PWD":/backup alpine tar czf /backup/backup_storage.tar.gz -C /data .
docker run --rm -v bitacora-mw_bitacora_uploads:/data -v "$PWD":/backup alpine tar czf /backup/backup_uploads.tar.gz -C /data .
```

El prefijo real del volumen puede variar según el nombre de la carpeta. Confírmalo con:

```bash
docker volume ls | grep bitacora
```

## Actualización De Código

1. Realiza backup de la base de datos y de los volúmenes persistentes.
2. Sube los cambios al servidor.
3. Reconstruye imágenes y aplica automáticamente las migraciones antes de iniciar `app`:

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

4. Confirma que `app` haya quedado saludable. Si el arranque falla por una migración, no repitas el despliegue sin revisar los logs y restaurar el backup si corresponde.
5. Limpia PDFs expirados si corresponde:

```bash
docker compose -f docker-compose.prod.yml exec app php database/cleanup_bitacora_pdfs.php
```

La limpieza periódica de PDFs y borradores también queda a cargo del servicio `maintenance`.

6. Si `BITACORA_MAIL_ASYNC=true`, confirma que el worker esté activo:

```bash
docker compose -f docker-compose.prod.yml ps worker
```

7. Confirma que el mantenimiento esté activo:

```bash
docker compose -f docker-compose.prod.yml ps maintenance
```

8. Revisa logs:

```bash
docker compose -f docker-compose.prod.yml logs -f app nginx worker maintenance
```

## Seguridad Operativa

- No publiques `db` ni `phpmyadmin` en producción.
- No subas `.env`, backups SQL, logs, `storage/`, `public/uploads/` ni `vendor/` al repositorio.
- Mantén legacy fuera de `public/`; las copias de referencia viven en `legacy/public/` y no deben exponerse por Nginx.
- Mantén `SMTP_VERIFY_TLS=true`.
- Cambia credenciales por valores únicos y largos.
- Haz backup antes de actualizar.
- El límite de cuerpo HTTP en Nginx es `16M`; súbelo solo si agregas cargas de archivos reales.
