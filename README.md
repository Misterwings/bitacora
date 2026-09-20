# Bitácora Mister Wings

Aplicación PHP para registro, envío y generación de bitácoras por empresa/sede. El flujo principal usa la vista unificada `public/vistas/bitacora.php` y el handler `public/scripts/send_bitacora.php`.

## Arranque Local

1. Copia `.env.example` a `.env` y reemplaza `BITACORA_DRAFT_KEY_BASE64` con el resultado de `openssl rand -base64 32`.
2. Ejecuta `docker compose up --build`.
3. Abre `http://localhost:8080`.
4. PhpMyAdmin queda disponible en `http://localhost:8081` solo desde localhost.
5. Mailpit captura los correos locales en `http://localhost:8025`; desarrollo no contacta el SMTP real.

## Pruebas SMTP Reales En Desarrollo

El desarrollo normal usa Mailpit. Para probar envíos reales de forma explícita, configura un archivo local `.env.dev-real` con una cuenta SMTP de desarrollo y carga el override `docker-compose.dev-real.yml`. El archivo real queda ignorado por Git. El archivo `.env.dev-real.example` es solo una plantilla: no lo uses directamente como configuración activa.

Si ya existe el volumen `db_data`, conserva en `.env.dev-real` el mismo `MYSQL_DATABASE`, `MYSQL_USER` y `MYSQL_PASSWORD` con los que se creó ese volumen. MySQL no actualiza esas credenciales al arrancar un volumen existente.

El override exige autenticación SMTP, mantiene `SMTP_VERIFY_TLS=true` y fuerza `BITACORA_MAIL_ASYNC=false`, por lo que no requiere worker y los errores se devuelven durante la petición:

```bash
docker compose --env-file .env.dev-real -f docker-compose.yml -f docker-compose.dev-real.yml config --quiet
docker compose --env-file .env.dev-real -f docker-compose.yml -f docker-compose.dev-real.yml up -d --build
```

Este modo utiliza los destinatarios administrados en la base de datos. `bitacora_destinatarios` conserva los correos principales por empresa y sede; `bitacora_seccion_destinatarios` define los correos restringidos por sección. Una asignación activa por sección tiene precedencia: ese destinatario queda fuera del correo completo y recibe únicamente las secciones asignadas. La parametrización administrativa está disponible para usuarios `admin` en `vistas/admin_destinatarios.php`.

Para probar solo la conexión SMTP antes de enviar una bitácora:

```bash
docker compose --env-file .env.dev-real -f docker-compose.yml -f docker-compose.dev-real.yml exec -e BITACORA_SMOKE_MAIL_TO=destinatario@example.com app php scripts/smoke_mail.php
```

Después de enviar una bitácora desde la vista, revisa el estado y los errores:

```bash
docker compose --env-file .env.dev-real -f docker-compose.yml -f docker-compose.dev-real.yml exec app php scripts/bitacora_status.php
```

El correo principal puede incluir el PDF. Los correos enviados por sección no incluyen adjuntos; esta regla se verifica con `tests/section_mail_privacy_test.php`.

## Despliegue En Servidor

Para producción con Docker/Coolify usa `docker-compose.prod.yml`, que no incluye PhpMyAdmin, no expone MySQL y construye imágenes con el código y dependencias dentro.

Para desplegar directamente en cPanel, configura el document root en `public/` y sigue la sección cPanel de [`DEPLOYMENT.md`](DEPLOYMENT.md). El ejemplo de variables específico es [`.env.cpanel.example`](.env.cpanel.example).

Guía completa: [`DEPLOYMENT.md`](DEPLOYMENT.md).

## Variables Requeridas

- `MYSQL_HOST`, `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD` para conexión a BD.
- `SMTP_HOST`, `SMTP_PORT`, `SMTP_SECURE`, `SMTP_AUTH`, `SMTP_USER`, `SMTP_PASSWORD`, `SMTP_FROM` para envío de correos.
- `SMTP_HELO_NAME` opcional para definir el nombre válido anunciado durante HELO/EHLO desde Docker.
- `BITACORA_STORAGE_PATH` para almacenar PDFs fuera de `public`.
- `BITACORA_DRAFT_KEY_BASE64` para cifrar borradores persistentes; debe ser el base64 canónico de 32 bytes aleatorios.
- `BITACORA_DRAFT_KEYRING_JSON` y `BITACORA_DRAFT_ACTIVE_KEY_VERSION` permiten conservar claves anteriores y rotar la clave activa sin perder borradores.
- `BITACORA_DRAFT_TTL_DAYS` y `BITACORA_DRAFT_MAX_BYTES` para controlar la retención y el tamaño máximo de cada borrador.
- `BITACORA_MAIL_ASYNC=true` para encolar correos y procesarlos con worker CLI.
- `SESSION_SECURE=true` en producción con HTTPS.
- `SESSION_IDLE_TIMEOUT_SECONDS` y `SESSION_MAX_LIFETIME_SECONDS` para expirar sesiones por inactividad o vida máxima; `0` desactiva cada límite.
- Las vistas autenticadas avisan 10 minutos antes del vencimiento y permiten renovar la sesión mediante una acción explícita.

## Seguridad

- No versionar `.env`, logs, uploads, storage ni `vendor/`.
- Rotar credenciales si alguna vez estuvieron en el repositorio o en respaldos.
- Los endpoints de envío requieren sesión activa, empresa autorizada y token CSRF.
- Los PDFs se guardan fuera de `public` y se descargan mediante `scripts/download_bitacora.php` con sesión válida.
- Los borradores se cifran en MySQL, pertenecen al usuario/empresa/tipo de formulario y no se deben guardar en `localStorage`.
- Conserva `BITACORA_DRAFT_KEY_BASE64` en backups seguros: cambiarla impide recuperar los borradores existentes.
- Los envíos asociados a un borrador son idempotentes: una repetición con los mismos datos devuelve la respuesta original sin duplicar correo, PDF ni cola.
- Las vistas/scripts legacy están archivados en `legacy/public/`; no se sirven desde el webroot.
- Nginx bloquea scripts inline. Los estilos inline se permiten únicamente en las directivas CSP de estilos porque SweetAlert2, Select2 y jQuery los generan para renderizar y posicionar componentes; mantén el código propio en `public/resources/`.
- Los cambios del administrador de formulario se registran en `bitacora_admin_audit`.

## Dependencias PHP

Composer está en la raíz del proyecto y `vendor/` queda fuera del webroot. Para instalar dependencias:

```bash
docker compose exec app composer install
```

PHPMailer y mPDF se instalan desde Composer; no se deben agregar librerías PHP manuales bajo `public/`.

## Base De Datos

Las migraciones viven en `database/migrations/` y se aplican automáticamente antes de iniciar PHP-FPM. También pueden ejecutarse manualmente con:

```bash
docker compose exec app php database/migrate.php
```

El migrador usa un bloqueo de MySQL y `schema_migrations` para registrar archivos aplicados. `000_base_schema.sql` permite iniciar sobre una base vacía; las migraciones siguientes crean la configuración, cola, PDFs y auditoría.

En una instalación nueva, crea el primer administrador sin escribir la contraseña literal en el historial:

```bash
read -s BITACORA_ADMIN_PASSWORD
export BITACORA_ADMIN_PASSWORD
docker compose exec \
  -e BITACORA_ADMIN_USERNAME=admin \
  -e BITACORA_ADMIN_PASSWORD \
  -e BITACORA_ADMIN_EMAIL=admin@example.com \
  app php scripts/create_admin.php
unset BITACORA_ADMIN_PASSWORD
```

`bitacora_empresa_config.config_json` permite agregar campos al formulario unificado sin editar PHP. Ejemplo:

```json
{
  "schema": "operational_current",
  "dynamic_fields": [
    {
      "name": "datos_equipo",
      "label": "Datos del equipo",
      "description": "Registra el estado general y cualquier novedad relevante.",
      "type": "subsection",
      "section": "Operaciones",
      "order": 10
    },
    {
      "name": "temperatura_nevera",
      "label": "Temperatura de nevera",
      "type": "number",
      "section": "Operaciones",
      "required": true,
      "suffix": "°C",
      "sedes": ["PANCE"],
      "order": 20
    }
  ]
}
```

Tipos soportados: `text`, `textarea`, `number`, `date`, `time`, `select`, `yes_no`, `yes_no_quantity_group`, `quantity_group`, `multiselect_detail_group` y `subsection`. Una subsección es presentacional: usa `label` como título, acepta `description`, no genera datos y conserva su orden en formulario, PDF y correo.

Los campos `number` conservan el valor numérico en el formulario y permiten formatear su presentación en PDF y correo. `number_format: "currency"` usa formato colombiano (`123456` se muestra como `$123.456`); `number_decimals` permite definir entre 0 y 6 decimales. El atributo `suffix` agrega texto, y `suffix_singular` permite usar un texto diferente cuando el valor es exactamente `1`:

```json
{
  "name": "valor_venta",
  "label": "Valor de venta",
  "type": "number",
  "number_format": "currency",
  "number_decimals": 0,
  "section": "Operaciones"
}
```

```php
app_bitacora_field('number', 'cantidad_cajas', 'Cantidad de cajas', [
    'suffix' => 'cajas',
    'suffix_singular' => 'caja',
    'suffix_plural' => 'cajas',
])
```

Las subsecciones fijas del esquema base se pueden declarar en PHP con `app_bitacora_subsection()` dentro del arreglo `fields` de una sección:

```php
app_bitacora_subsection(
    'datos_equipo',
    'Datos del equipo',
    'Registra el estado general y cualquier novedad relevante.',
    ['order' => 10]
)
```

Un campo Sí/No puede mostrar una fecha obligatoria cuando se selecciona "Sí" usando `date` como sexto argumento:

```php
app_bitacora_yes_no_field(
    'requiere_visita',
    '¿Requiere visita?',
    'requiereVisitaGroup',
    'fecha_visita',
    'Fecha de visita',
    'date',
    ['detail_default_from' => 'fechab']
)
```

`detail_default_from` copia el valor del campo indicado cuando se selecciona "Sí". Mientras el usuario no cambie manualmente el detalle, este se actualiza junto con el campo de origen.

Los grupos Sí/No con cantidad aceptan `no_report_value` como texto semántico para una respuesta "No". El valor enviado por el formulario continúa siendo `No`; los `No` predeterminados se omiten de PDF y correo para que no mantengan visibles secciones sin novedades:

```php
app_bitacora_yes_no_quantity_group_field(
    'visitas_proveedores',
    '¿Hubo visitas de proveedores?',
    'visitas_proveedores_cantidad',
    'Cantidad de proveedores',
    [
        app_bitacora_field('text', 'nombre_proveedor', 'Nombre del proveedor'),
    ],
    ['no_report_value' => 'No se recibieron proveedores durante la jornada']
)
```

En estos grupos, `suffix` se puede usar para agregar texto a la cantidad total en PDF y correo. `suffix_singular` se usa cuando la cantidad es `1` y `suffix_plural` cuando es mayor o igual a `2`; si no se configuran, se mantiene el `suffix` existente. El formulario continúa enviando únicamente el número:

```php
app_bitacora_yes_no_quantity_group_field(
    'visitas',
    '¿Hubo visitas?',
    'visitas_cantidad',
    'Cantidad de visitas',
    [app_bitacora_field('text', 'visitante', 'Visitante')],
    [
        'suffix' => 'unidades',
        'suffix_singular' => 'unidad',
        'suffix_plural' => 'unidades',
    ]
)
```

Los campos Sí/No simples y los grupos Sí/No con detalle también aceptan `no_report_value`. Si no se especifica, el texto semántico es `Sin novedad`; los `No` predeterminados no generan filas en PDF/correo y un detalle explícito no generado automáticamente conserva prioridad:

```php
app_bitacora_yes_no_field(
    'novedades',
    '¿Hubo novedades?',
    'novedadesGroup',
    'novedades_detalle',
    'Detalle',
    'textarea',
    ['no_report_value' => 'No se presentaron novedades']
)

app_bitacora_yes_no_detail_group_field(
    'material_pop',
    '¿Se entregó material POP?',
    [app_bitacora_field('text', 'tipo_material', 'Tipo de material')],
    ['no_report_value' => 'No se entregó material POP']
)
```

Los registros repetibles admiten `simple_radio` para respuestas Sí/No. El control se activa únicamente para los registros visibles y se incluye en borradores, PDF y correo:

```php
app_bitacora_field(
    'simple_radio',
    'decoracion_reserva',
    '¿CON DECORACIÓN?',
    ['col' => 'col-md-4']
)
```

Los grupos de cantidad directa omiten la pregunta Sí/No. La cantidad es obligatoria, admite `0` y `zero_report_value` conserva el texto semántico del estado sin registros; cuando la cantidad es `0`, ese estado se omite de PDF y correo:

```php
app_bitacora_quantity_group_field(
    'incidentes',
    'Incidentes de la jornada',
    'incidentes_cantidad',
    'Cantidad de incidentes',
    [app_bitacora_field('textarea', 'descripcion', 'Descripción')],
    ['max' => 5, 'zero_report_value' => 'No se presentaron incidentes']
)
```

Para agregar cargos al bloque de Gestión Humana sin editar PHP, incluir `gh_cargos_extra` en `config_json`:

```json
{
  "schema": "operational_current",
  "gh_cargos_extra": ["Administrador/a", "Domiciliario/a"],
  "dynamic_fields": []
}
```

## Operación

Ejecutar checks locales:

```bash
sh scripts/checks.sh
```

Para auditar el Compose de producción con un archivo de variables explícito:

```bash
BITACORA_COMPOSE_FILE=docker-compose.prod.yml BITACORA_COMPOSE_ENV_FILE=.env.production sh scripts/checks.sh
```

En PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/checks.ps1
```

En PowerShell, define `$env:BITACORA_COMPOSE_FILE='docker-compose.prod.yml'` y `$env:BITACORA_COMPOSE_ENV_FILE='.env.production'` antes de ejecutar el mismo script.

La batería incluye pruebas de integración contra MySQL para transacciones, aislamiento, concurrencia optimista e idempotencia.

Probar SMTP local y revisar el mensaje en Mailpit:

```bash
docker compose exec app php scripts/smoke_mail.php
```

Limpiar PDFs expirados según `BITACORA_PDF_TTL_DAYS`:

```bash
docker compose exec app php database/cleanup_bitacora_pdfs.php
```

Para eliminar únicamente registros cuyo archivo físico ya no existe:

```bash
docker compose exec app php database/cleanup_bitacora_pdfs.php --missing
```

Limpiar borradores expirados según `BITACORA_DRAFT_TTL_DAYS`:

```bash
docker compose exec app php database/cleanup_bitacora_drafts.php
```

Consultar el estado de borradores, versiones de clave, cola y reservas de envío:

```bash
docker compose exec app php scripts/bitacora_status.php
```

Después de configurar un keyring con una nueva versión activa, recifrar inmediatamente los borradores existentes:

```bash
docker compose exec app php database/rotate_bitacora_draft_keys.php
```

Procesar correos pendientes cuando `BITACORA_MAIL_ASYNC=true`:

```bash
docker compose exec app php scripts/process_bitacora_email_queue.php
```

En producción, `docker-compose.prod.yml` mantiene un servicio `worker` supervisado. Procesa hasta `BITACORA_MAIL_WORKER_LIMIT` correos por ciclo y reintenta fallos automáticamente.
