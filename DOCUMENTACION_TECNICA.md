# Documentación técnica

## 1. Propósito y alcance

Bitácora Mister Wings es una aplicación PHP para diligenciar, guardar, convertir a PDF y enviar por correo bitácoras operativas y reportes de supervisión por empresa y sede.

Este documento describe la implementación que existe actualmente en el repositorio. Es una referencia para desarrollo, soporte, despliegue, operación y revisión de seguridad. No reemplaza los archivos de variables de entorno ni debe contener credenciales reales.

Documentos relacionados:

- [`README.md`](README.md): arranque local, SMTP de desarrollo, variables básicas, checks y comandos frecuentes.
- [`DEPLOYMENT.md`](DEPLOYMENT.md): despliegue Docker/Coolify, cPanel, backups, cron, healthchecks y diagnóstico de producción.
- [`MANUAL_USUARIO.md`](MANUAL_USUARIO.md): operación del formulario, borradores, PDF, envío y administración funcional.

## 2. Resumen del sistema

### 2.1 Stack

| Componente | Implementación |
| --- | --- |
| Lenguaje | PHP `^8.4` |
| Servidor web | Nginx en Docker; Apache/.htaccess en cPanel |
| Runtime | PHP-FPM |
| Base de datos | MySQL `8.4` |
| PDF | mPDF `^8.3`; existe una ruta de fallback para Dompdf si estuviera instalada |
| Correo | PHPMailer `^6.9` mediante SMTP |
| Frontend | PHP renderizado, Bootstrap, jQuery, Select2 y SweetAlert2 |
| Dependencias | Composer en la raíz; `vendor/` fuera del webroot |
| Almacenamiento | PDFs y logs bajo `storage/`; uploads bajo `public/uploads/` |

### 2.2 Principios importantes

- El webroot es `public/`; la raíz completa del proyecto no debe publicarse.
- El formulario activo es unificado: `public/vistas/bitacora.php`.
- El POST unificado es `public/scripts/send_bitacora.php`.
- La configuración de empresas, sedes, campos y destinatarios vive en `public/config/bitacora.php` con extensiones persistidas en MySQL.
- Los destinatarios pueden migrarse de PHP a base de datos y después administrarse desde la interfaz.
- Los PDFs nunca se sirven directamente desde `storage/`; se descargan mediante un token y `public/scripts/download_bitacora.php`.
- El correo puede enviarse dentro de la petición o mediante `bitacora_email_queue` y un worker CLI.
- El código legacy bajo `legacy/public/` es referencia histórica y no forma parte del flujo activo.

## 3. Arquitectura y flujo de ejecución

### 3.1 Desarrollo local

```text
Navegador
    |
    v
nginx:8080 -> public/
    |
    +--> app: PHP-FPM 9000
    |        |
    |        +--> MySQL db:3306
    |        +--> Mailpit:1025
    |        +--> storage/ y public/uploads/
    |
    +--> PhpMyAdmin:8081 (solo loopback del host)
    +--> Mailpit UI:8025 (solo loopback del host)
```

El archivo [`docker-compose.yml`](docker-compose.yml) monta el proyecto completo dentro de `app` y `nginx`. El comando de `app` instala Composer, aplica migraciones, importa destinatarios, prepara directorios y arranca PHP-FPM.

### 3.2 Producción Docker/Coolify

```text
Proxy/Coolify -> nginx:80 -> app:9000 -> MySQL
                                      |
                                      +--> SMTP externo

worker       -> bitacora_email_queue -> SMTP externo
maintenance  -> limpieza de PDFs y borradores
```

[`docker-compose.prod.yml`](docker-compose.prod.yml) contiene cinco servicios:

| Servicio | Responsabilidad | Exposición |
| --- | --- | --- |
| `app` | PHP-FPM, migraciones, importación inicial y healthcheck | Solo red interna, puerto FPM `9000` |
| `nginx` | HTTP, archivos públicos, FastCGI y cabeceras de seguridad | `expose: 80` para el proxy |
| `db` | MySQL persistente | Sin puerto publicado |
| `worker` | Procesamiento continuo de correo asíncrono | Solo CLI |
| `maintenance` | Limpieza periódica de PDFs y borradores | Solo CLI |

El proxy debe apuntar a `nginx:80`, nunca a `app:9000`. PHP-FPM no es un servidor HTTP.

### 3.3 cPanel

cPanel utiliza el mismo código PHP, pero reemplaza Docker por Apache y PHP-FPM/EA-PHP. El dominio debe apuntar exactamente a `public/` y el resto del proyecto debe conservarse fuera del document root. La guía completa está en la sección cPanel de [`DEPLOYMENT.md`](DEPLOYMENT.md).

## 4. Mapa de módulos

### 4.1 Entradas web

| Ruta | Función |
| --- | --- |
| `public/index.php` | Formulario de login y carga de assets públicos |
| `public/bd/login.php` | Autenticación AJAX, sesión y rate limit |
| `public/bd/session.php` | Consulta y renovación explícita de sesión |
| `public/bd/logout.php` | Cierre de sesión mediante POST protegido |
| `public/vistas/pag_inicio.php` | Fallback para usuarios sin empresa/formulario asignado |
| `public/vistas/bitacora.php` | Vista unificada operativa o de supervisión |
| `public/vistas/admin_formulario.php` | Administración de campos, orden y visibilidad |
| `public/vistas/admin_destinatarios.php` | Administración de destinatarios completos y por sección |
| `public/scripts/send_bitacora.php` | POST unificado que delega al tipo de formulario |
| `public/scripts/bitacora_draft.php` | API JSON de borradores |
| `public/scripts/download_bitacora.php` | Descarga autenticada y validada de PDFs |
| `public/healthz` | Healthcheck HTTP simple gestionado por Nginx |
| `public/readyz` | Alias Nginx de `public/php-healthz.php` |

### 4.2 Configuración y seguridad

| Archivo | Responsabilidad |
| --- | --- |
| `public/config/env.php` | Carga `.env` y conversión de booleanos/enteros |
| `public/config/security.php` | Sesión, CSRF, roles, empresa activa y autorización |
| `public/config/bitacora.php` | Empresas, sedes, esquema base, dinámicos y destinatarios |
| `public/config/bitacora_drafts.php` | AES-256-GCM, keyring y saneamiento de borradores |
| `public/config/mailer.php` | Configuración única de PHPMailer y TLS |
| `public/config/admin_formulario_helpers.php` | Normalización y persistencia de configuración del formulario |
| `public/config/admin_destinatarios_helpers.php` | Validación y mutaciones de destinatarios |

### 4.3 Procesamiento de dominio

| Archivo | Responsabilidad |
| --- | --- |
| `public/scripts/bitacora_helpers.php` | Normalización, textos por defecto y render del reporte |
| `public/scripts/bitacora_validation_helpers.php` | Validación de esquema y grupos condicionales |
| `public/scripts/bitacora_pdf_helpers.php` | Rutas, generación, registro y expiración de PDF |
| `public/scripts/bitacora_mail_helpers.php` | Render de correos y envío/encolado por sección |
| `public/scripts/bitacora_submission_helpers.php` | Idempotencia, reservas, estados y respuestas del envío |
| `public/scripts/bitacora_download_helpers.php` | Resolución segura de archivos para descarga |
| `scripts/process_bitacora_email_queue.php` | Worker CLI, reintentos y actualización de estados |

## 5. Autenticación, sesión y autorización

### 5.1 Login

1. El usuario abre `public/index.php`.
2. El formulario incluye `csrf_token` generado por `app_csrf_input()`.
3. El frontend envía usuario y contraseña a `public/bd/login.php` mediante POST.
4. El backend busca el usuario en `usuarios_login` unido a `razones_sociales`.
5. Se intenta primero `password_verify()`.
6. Si el valor almacenado es un MD5 hexadecimal legacy válido, se acepta una sola vez y se reemplaza por `password_hash(PASSWORD_DEFAULT)`.
7. Se regenera el ID de sesión y se guardan usuario, nombre, empresa, rol, fecha de creación y última actividad.
8. El usuario normal queda limitado a su empresa. El administrador puede cambiar la empresa activa dentro del conjunto configurado.

La cuenta se bloquea temporalmente después de cinco fallos acumulados para la combinación usuario/IP. El registro se guarda en `login_attempts`; el bloqueo normal dura quince minutos.

### 5.2 Sesión

Las cookies de sesión son `HttpOnly`, tienen `SameSite` configurable y usan `Secure` cuando corresponde. Los valores de producción recomendados son:

```env
SESSION_SECURE=true
SESSION_SAMESITE=Lax
SESSION_IDLE_TIMEOUT_SECONDS=3600
SESSION_MAX_LIFETIME_SECONDS=43200
```

La interfaz avisa diez minutos antes del vencimiento. **Continuar sesión** ejecuta un POST CSRF a `public/bd/session.php`; **Ir al login** redirige al login. La renovación no elimina los límites máximos configurados.

### 5.3 Reglas de autorización

- Toda vista protegida debe cargar `public/config/security.php` y llamar `app_require_login()` o `app_require_admin()`.
- Todo POST protegido debe llamar `app_require_post_login($empresaId, true)` o `app_require_post_admin()` antes de leer datos de usuario.
- El usuario normal solo puede operar sobre su `s_idEmpresa`.
- El administrador puede seleccionar una empresa configurada, pero el servidor vuelve a validar que exista.
- Las descargas se validan por sesión, empresa, propietario o rol admin, token y ruta física.
- Los endpoints legacy están deshabilitados por defecto mediante `APP_ENABLE_LEGACY_BITACORA=false`.

## 6. Empresas, sedes y tipos de formulario

La matriz de empresas está definida en `public/config/bitacora.php`; los nombres visibles se obtienen de `razones_sociales` cuando existen. Las sedes activas de la base de datos (`empresa_sedes`) tienen precedencia sobre el fallback PHP.

| ID | Slug técnico | Nombre base | Tipo | Sedes configuradas |
| ---: | --- | --- | --- | --- |
| 1 | `mes_group` | MES GROUP | `operational` | PANCE, CIUDAD JARDÍN, JARDÍN PLAZA, BOCHALEMA, UNICENTRO |
| 2 | `mes_soluciones_hcqc` | MES SOLUCIONES HCQC | `operational` | GRANADA |
| 3 | `les_group` | LES GROUP | `operational` | CHIPICHAPE, FLORA |
| 4 | `inversiones_valquin` | INVERSIONES VALQUIN | `operational` | LIMONAR, SAN FERNANDO |
| 5 | `lebor_sas` | LEBOR | `operational` | LLANOGRANDE |
| 6 | `supervisiones` | MES GROUP SAS -ADMIN | `supervision` | Pance, Ciudad Jardín, Jardín Plaza, Unicentro, Limonar, San Fernando, Granada, Chipichape, Flora, Llanogrande, Bochalema |
| 7 | `mes_trilogia` | MES GROUP - TRILOGIA | `operational` | UNICENTRO - TRILOGIA |
| 8 | `mes_test` | MES DEV | `operational` | PANCE, CIUDAD JARDÍN, JARDÍN PLAZA, BOCHALEMA, UNICENTRO |

Las sedes de supervisión conservan la capitalización declarada en la configuración. No se deben normalizar manualmente en SQL sin revisar campos, destinatarios y reglas que comparan el valor del formulario.

### 6.1 Extras por sede

La configuración puede activar bloques adicionales:

- `chetano`: disponible por sede para empresas que lo declaran, actualmente PANCE/UNICENTRO en las configuraciones correspondientes.
- `torito`: disponible para la configuración de Trilogía.
- `reunion_calidad`: bloque adicional de LEBOR/Llanogrande.

La visibilidad se determina en servidor y se refleja en el frontend. Un campo no disponible para la sede se deshabilita y no se envía.

## 7. Esquema de formularios

### 7.1 Formulario operativo

El esquema base se arma con `app_bitacora_default_form_sections()`. Incluye, entre otros, estos bloques:

- Datos básicos: fecha, sede, responsable y cargo.
- Desempeño de la sede: afluencia, presupuesto, ticket promedio y métricas de servicios.
- Visitas de áreas y actividades realizadas.
- Equipo de mantenimiento y contratistas.
- Descanso del coordinador.
- Operaciones: novedades de personal, servicio, devoluciones, cocina, bar, hielo, reservas y domicilios.
- Mercadeo.
- Gestión humana.
- Mejoramiento, calidad y ambiental.
- Tecnología de la información.
- Seguridad y salud en el trabajo.
- Mantenimiento, planta eléctrica, facturación y otros bloques declarados por el esquema actual.

La lista exacta y el orden son código versionado. El administrador puede ocultar o modificar elementos permitidos sin editar PHP.

### 7.2 Formulario de supervisión

La empresa 6 usa `app_bitacora_supervision_form_sections()` y solicita:

- Fecha.
- Horario de supervisión.
- Sede.
- Área: Salón, Cocina o Bar.
- Responsable de supervisión.
- Hallazgos con evidencias.
- Retroalimentación y colaboradores.
- Tareas para próximas visitas.
- Plan de acción o recomendaciones.
- Otras actividades del supervisor.

El reporte de supervisión no genera PDF en el flujo actual; se renderiza y envía por correo.

### 7.3 Tipos de campo dinámico

`bitacora_empresa_config.config_json` acepta `dynamic_fields`. La normalización de servidor permite:

| Tipo | Uso |
| --- | --- |
| `text` | Texto corto |
| `textarea` | Texto largo |
| `number` | Número con límites, formato y sufijos |
| `date` | Fecha |
| `time` | Hora |
| `select` | Lista simple |
| `multiselect` | Lista múltiple |
| `yes_no` | Sí/No con detalle condicional |
| `yes_no_quantity_group` | Sí/No, cantidad y registros repetibles |
| `quantity_group` | Cantidad y registros repetibles; admite cero |
| `multiselect_detail_group` | Personas/opciones seleccionadas con detalle por persona |
| `subsection` | Encabezado y descripción presentacional; no es dato obligatorio |

Los subcampos de grupos pueden ser `text`, `textarea`, `number`, `select`, `date`, `time` o `simple_radio`.

Todos los nombres técnicos deben comenzar por letra y contener únicamente letras, números y guion bajo. No se deben reutilizar identificadores de campos existentes.

Ejemplo mínimo:

```json
{
  "schema": "operational_current",
  "dynamic_fields": [
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

Un campo sin `sedes` aparece en todas las sedes. `subsection` siempre se fuerza como no obligatorio y ancho completo.

### 7.4 Reglas de presentación y reporte

- La fecha se prellena con el día actual si está vacía.
- El responsable operativo se prellena con el nombre de la sesión si existe.
- Los grupos Sí/No muestran sus detalles únicamente cuando se selecciona `Si`.
- Los detalles que no aplican se limpian y deshabilitan en frontend; el backend vuelve a validarlos.
- Los campos numéricos pueden usar formato de moneda colombiana y sufijo singular/plural.
- Las respuestas `No` sin detalle se muestran como `Sin novedad` o el `no_report_value` configurado.
- Las respuestas `No` predeterminadas por backend se omiten del PDF/correo para evitar reportar una novedad inexistente.
- Los grupos de cantidad directa con cantidad `0` no generan filas en PDF/correo.
- Una subsección se renderiza solo si el bloque posterior tiene contenido reportable.
- Los grupos multiselección con detalle pueden usar una opción de no aplicación; en ese caso no se solicitan detalles.

## 8. Flujo de envío

### 8.1 Operativo

1. `public/vistas/bitacora.php` renderiza el esquema de la empresa activa.
2. `public/resources/js/bitacora.js` valida los controles visibles del navegador y pide confirmación.
3. El frontend guarda el borrador pendiente antes de enviar.
4. `public/scripts/send_bitacora.php` valida sesión, CSRF, empresa, sede, tipos y grupos condicionales.
5. Se normalizan datos, se renderiza HTML de correo y HTML de PDF.
6. Se intenta generar el PDF bajo `BITACORA_STORAGE_PATH/<empresa>/<año>/<mes>/`.
7. Si la acción es `generate_pdf`, se registra el PDF y se devuelve un enlace de descarga; no se envía correo ni se elimina el borrador.
8. Si la acción es `send`, se registra una reserva en `bitacora_envios` para evitar duplicados.
9. Con `BITACORA_MAIL_ASYNC=true`, se registra el PDF y se encola el correo principal y los correos por sección.
10. Con correo síncrono, se marca el inicio de entrega, se envía el correo principal y luego los correos por sección.
11. Se actualiza el estado del envío, se persiste la respuesta JSON y se elimina el borrador solo cuando el proceso puede finalizarlo correctamente.

### 8.2 Supervisión

La empresa 6 usa el mismo endpoint, pero delega primero en `bit_handle_supervision()`:

- Valida la sede y el esquema de supervisión.
- Renderiza el cuerpo de correo.
- Encola o envía correo según `BITACORA_MAIL_ASYNC`.
- No crea PDF.
- Guarda el tipo de formulario `supervision` en `bitacora_envios`.

### 8.3 Estados del envío

| Estado | Significado |
| --- | --- |
| `procesando` | Se reservó el envío y todavía no hay respuesta final |
| `pendiente` | El correo quedó en cola asíncrona |
| `completado` | Correo principal y resultado esperado terminaron correctamente |
| `parcial` | Se obtuvo PDF o correo, pero no ambos, o falló una sección |
| `fallido` | No se logró entregar ni completar el resultado esperado |

El frontend informa por separado correo, PDF, advertencias y enlace de descarga. Un PDF generado no implica que el correo se haya entregado.

### 8.4 Idempotencia y entrega incierta

Los envíos respaldados por borrador usan una `submission_key` única y un `request_hash` en `bitacora_envios`.

- Repetir la misma solicitud devuelve la respuesta almacenada y no duplica PDF, correo ni cola.
- Usar la misma referencia con datos diferentes devuelve `idempotency_conflict`.
- Si el proceso falló después de iniciar SMTP pero antes de registrar respuesta, devuelve `submission_status_unknown`.
- Una entrega incierta debe verificarse en el historial SMTP antes de repetirla.
- Una reserva sin inicio de entrega puede recuperarse después de `BITACORA_SUBMISSION_CLAIM_TTL_SECONDS`.

## 9. Borradores persistentes

Aunque el archivo frontend histórico se llama `public/localstorage_bitacora.js`, los borradores actuales no dependen de `localStorage` ni `sessionStorage`. El script elimina claves legacy y usa exclusivamente `public/scripts/bitacora_draft.php`.

### 9.1 Características

- Un borrador por usuario, empresa y tipo de formulario.
- Cifrado AES-256-GCM.
- Payload validado y saneado contra el esquema actual.
- Token aleatorio de 64 caracteres hexadecimales.
- Control de versión optimista.
- Hash del esquema para detectar cambios de formulario.
- Expiración predeterminada de 30 días.
- Tamaño predeterminado máximo de 262144 bytes.
- Reintentos automáticos ante errores transitorios.
- Conflicto explícito si otra solicitud modificó el borrador.

### 9.2 Keyring

Generar una clave inicial:

```bash
openssl rand -base64 32
```

La salida debe ser base64 canónico de exactamente 32 bytes. En producción se puede usar la variable legacy `BITACORA_DRAFT_KEY_BASE64` o un keyring JSON versionado:

```env
BITACORA_DRAFT_KEY_BASE64=<clave-v1>
BITACORA_DRAFT_KEYRING_JSON={"1":"<clave-v1>","2":"<clave-v2>"}
BITACORA_DRAFT_ACTIVE_KEY_VERSION=2
```

Procedimiento de rotación:

1. Mantener la clave anterior dentro del keyring.
2. Agregar la nueva versión y hacerla activa.
3. Reconstruir/reiniciar `app` y verificar `/readyz`.
4. Ejecutar `php database/rotate_bitacora_draft_keys.php`.
5. Consultar `scripts/bitacora_status.php`.
6. Retirar la clave anterior únicamente cuando no existan borradores con esa versión.

Cambiar o perder una clave antes de recifrar los borradores vuelve esos datos irrecuperables.

## 10. PDFs y almacenamiento

### 10.1 Generación

`public/scripts/bitacora_pdf_helpers.php` crea una ruta como:

```text
<BITACORA_STORAGE_PATH>/<empresa>/<año>/<mes>/BITACORA_<sede>_<fecha>_<responsable>_<aleatorio>.pdf
```

El nombre se sanea con `bit_safe_filename()`. mPDF genera A4 usando UTF-8 y un directorio temporal bajo `storage/tmp`.

### 10.2 Registro y descarga

Después de generar el archivo se registra en `bitacora_pdfs`:

- Token de descarga.
- Empresa, sede, fecha y responsable.
- Usuario creador.
- Ruta relativa y nombre de archivo.
- Fecha de creación y expiración.

El enlace usa:

```text
public/scripts/download_bitacora.php?token=<token>
```

El endpoint valida formato del token, existencia, expiración, propietario/empresa/rol y ruta dentro del almacenamiento. No se debe construir una URL de archivo físico ni publicar `storage/`.

La retención predeterminada es 90 días mediante `BITACORA_PDF_TTL_DAYS`. La limpieza elimina primero el archivo y después el registro; los errores de archivo detienen la operación para no perder metadatos sin confirmar.

## 11. Correo y destinatarios

### 11.1 SMTP

Toda configuración SMTP debe pasar por `app_configure_mailer()` en `public/config/mailer.php`. No se debe repetir configuración PHPMailer en handlers o workers.

Producción rechaza `SMTP_SECURE=none` y `SMTP_VERIFY_TLS=false`. Los valores habituales son:

```env
SMTP_PORT=465
SMTP_SECURE=ssl
SMTP_AUTH=true
SMTP_VERIFY_TLS=true
```

`SMTP_HELO_NAME` permite anunciar un hostname válido cuando el contenedor tiene un nombre no aceptado por el servidor SMTP.

### 11.2 Fuentes de destinatarios

La función `app_bitacora_recipients_for_sede()` combina:

- Configuración estática de `public/config/bitacora.php` cuando el modo es `php`.
- Registros de `bitacora_destinatarios`.
- Destinatarios por empresa y sede.
- Tipos `to`, `cc` y `bcc`.

La tabla `bitacora_destinatarios_config` controla el modo por empresa:

- `php`: usa la configuración PHP y agrega filas administradas que correspondan.
- `database`: usa la configuración persistida como fuente operativa principal.

El comando `scripts/import_bitacora_recipients.php` importa la configuración PHP una sola vez por empresa, evita duplicados, conserva el orden y cambia la empresa a modo `database`. Si una empresa ya está en modo `database`, una ejecución posterior no reemplaza sus cambios administrativos.

### 11.3 Destinatarios por sección

Las empresas operativas pueden usar `bitacora_seccion_destinatarios`:

- Cada asignación contiene empresa, sede opcional, tipo, correo y `section_key`.
- El destinatario recibe únicamente las secciones asignadas.
- Si un correo tiene una asignación activa por sección, se elimina del correo completo para evitar duplicación y exposición de secciones no autorizadas.
- Los correos por sección no llevan el PDF adjunto.
- El orden de secciones sigue el esquema vigente del formulario.

En modo síncrono las secciones se envían en la misma petición. En modo asíncrono se crean trabajos separados con `job_type=section`.

## 12. Modelo de datos y migraciones

### 12.1 Tablas principales

| Tabla | Uso |
| --- | --- |
| `razones_sociales` | Empresas |
| `sedes` | Catálogo base de sedes |
| `usuarios_login` | Usuarios, hash, empresa, sede y rol |
| `empresa_sedes` | Sedes activas por empresa, valor del formulario y orden |
| `login_attempts` | Rate limit por usuario/IP |
| `bitacora_empresa_config` | Tipo de formulario y `config_json` |
| `bitacora_destinatarios` | Destinatarios globales y por sede |
| `bitacora_destinatarios_config` | Modo de fuente de destinatarios |
| `bitacora_seccion_destinatarios` | Asignaciones de correo por sección |
| `bitacora_pdfs` | Metadatos y tokens de PDF |
| `bitacora_envios` | Reservas, estados, resultados e idempotencia |
| `bitacora_email_queue` | Trabajos de correo asíncrono |
| `bitacora_borradores` | Payload cifrado, versión y expiración |
| `bitacora_admin_audit` | Auditoría de cambios administrativos |
| `schema_migrations` | Archivo y checksum de cada migración |

### 12.2 Ejecución del migrador

[`database/migrate.php`](database/migrate.php) hace lo siguiente:

1. Exige ejecución CLI.
2. Ordena los `.sql` de `database/migrations/`.
3. Adquiere `GET_LOCK('bitacora_schema_migrations', 30)`.
4. Crea `schema_migrations` si no existe.
5. Calcula SHA-256 de cada archivo.
6. Omite migraciones ya aplicadas con el mismo checksum.
7. Reaplica únicamente la lista de migraciones DDL idempotentes declarada en el script cuando cambió su checksum.
8. Falla si una migración aplicada cambió checksum y no está autorizada para reaplicación.
9. Libera el bloqueo al finalizar.

Comando manual:

```bash
docker compose exec app php database/migrate.php
```

Antes de modificar migraciones ya aplicadas se debe hacer backup y entender la política de checksum. Nunca se deben renombrar archivos de migración ya registrados.

## 13. Variables de entorno

Los ejemplos completos son `.env.example`, `.env.production.example`, `.env.cpanel.example` y `.env.dev-real.example`. Las variables se cargan desde `.env` por `public/config/env.php`; Docker también puede inyectarlas.

### 13.1 Base de datos

| Variable | Uso |
| --- | --- |
| `MYSQL_HOST` | Host MySQL; normalmente `db` en Docker y `localhost` en cPanel |
| `MYSQL_DATABASE` | Base de datos de aplicación |
| `MYSQL_USER` | Usuario de aplicación |
| `MYSQL_PASSWORD` | Contraseña de aplicación |
| `MYSQL_ROOT_PASSWORD` | Solo Compose para inicialización/administración de MySQL |

### 13.2 SMTP

| Variable | Uso |
| --- | --- |
| `SMTP_HOST` | Host SMTP |
| `SMTP_PORT` | Puerto, normalmente 465 o 587 |
| `SMTP_SECURE` | `ssl`, `tls`/`starttls` o `none` solo en desarrollo local |
| `SMTP_AUTH` | Activa autenticación |
| `SMTP_USER` / `SMTP_PASSWORD` | Credenciales SMTP |
| `SMTP_FROM` | Remitente |
| `SMTP_VERIFY_TLS` | Verificación de certificado; debe permanecer `true` en producción |
| `SMTP_TIMEOUT_SECONDS` | Timeout SMTP |
| `SMTP_HELO_NAME` | Nombre para HELO/EHLO |

### 13.3 Aplicación

| Variable | Predeterminado | Uso |
| --- | ---: | --- |
| `APP_ENV` | `development` | Entorno; producción aplica reglas TLS estrictas |
| `BITACORA_STORAGE_PATH` | `storage/bitacoras_pdf` | Almacenamiento privado de PDFs |
| `BITACORA_PDF_TTL_DAYS` | `90` | Retención de PDFs |
| `BITACORA_DRAFT_TTL_DAYS` | `30` | Retención de borradores |
| `BITACORA_DRAFT_MAX_BYTES` | `262144` | Tamaño máximo de payload |
| `BITACORA_MAIL_ASYNC` | `false` local / `true` prod | Envío por cola |
| `BITACORA_SUBMISSION_CLAIM_TTL_SECONDS` | `300` | Recuperación de reservas sin respuesta |
| `BITACORA_MAIL_WORKER_LIMIT` | `20` | Máximo de trabajos por ciclo |
| `APP_ENABLE_LEGACY_BITACORA` | `false` | Compatibilidad legacy; no activar sin necesidad explícita |

### 13.4 Keyring y worker

| Variable | Uso |
| --- | --- |
| `BITACORA_DRAFT_KEY_BASE64` | Clave legacy/versión 1 de 32 bytes |
| `BITACORA_DRAFT_KEYRING_JSON` | Objeto JSON de claves por versión |
| `BITACORA_DRAFT_ACTIVE_KEY_VERSION` | Versión de cifrado usada para nuevos borradores |
| `BITACORA_MAIL_WORKER_INTERVAL_SECONDS` | Intervalo del worker |
| `BITACORA_MAIL_WORKER_HEALTH_MAX_AGE_SECONDS` | Antigüedad máxima del heartbeat |
| `BITACORA_MAINTENANCE_INTERVAL_SECONDS` | Intervalo de mantenimiento |
| `BITACORA_MAINTENANCE_BATCH_SIZE` | Filas por lote de limpieza |
| `BITACORA_MAINTENANCE_MAX_BATCHES` | Lotes máximos por ciclo |

## 14. Despliegue

### 14.1 Local

```bash
cp .env.example .env
openssl rand -base64 32
# colocar la salida en BITACORA_DRAFT_KEY_BASE64
docker compose up --build
```

URLs locales:

- Aplicación: `http://localhost:8080`.
- PhpMyAdmin: `http://localhost:8081`.
- Mailpit: `http://localhost:8025`.

El SMTP local apunta a Mailpit y no debe confundirse con una prueba de entrega real.

### 14.2 SMTP real en desarrollo

Usar un archivo local `.env.dev-real` que no se versiona:

```bash
docker compose --env-file .env.dev-real \
  -f docker-compose.yml -f docker-compose.dev-real.yml \
  config --quiet

docker compose --env-file .env.dev-real \
  -f docker-compose.yml -f docker-compose.dev-real.yml \
  up -d --build
```

Este modo fuerza autenticación SMTP, mantiene `SMTP_VERIFY_TLS=true`, desactiva la cola y devuelve errores SMTP durante la petición.

### 14.3 Docker/Coolify

1. Copiar `.env.production.example` a un secreto o archivo privado.
2. Cambiar todas las credenciales placeholder.
3. Generar y conservar `BITACORA_DRAFT_KEY_BASE64`.
4. Seleccionar `docker-compose.prod.yml`.
5. Asignar el dominio al servicio `nginx`, puerto interno `80`.
6. Arrancar con:

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

7. Verificar `/healthz` y `/readyz`.
8. Crear el primer administrador por CLI.
9. Confirmar `worker` y `maintenance` saludables.

### 14.4 cPanel

Requisitos esenciales:

- PHP 8.4 para web y CLI.
- Extensiones `pdo_mysql`, `mbstring`, `gd`, `intl`, `zip`, `bcmath`, `openssl`, `xml` y `zlib`.
- Composer y `rsync`.
- Document root exactamente en `public/`.
- `.env` privado fuera de Git y `chmod 600`.
- `BITACORA_STORAGE_PATH` fuera de `public/`.
- `BITACORA_MAIL_ASYNC=false` salvo que exista un cron confiable para el procesador.

La secuencia Git, Composer, migraciones, importación de destinatarios y cron está documentada en [`DEPLOYMENT.md`](DEPLOYMENT.md).

## 15. Primer administrador

El script `scripts/create_admin.php` solo se puede ejecutar por CLI. Exige:

- Usuario alfanumérico, punto, guion o guion bajo, máximo 25 caracteres.
- Contraseña de mínimo 12 caracteres.
- Correo válido, máximo 50 caracteres.
- Nombre de máximo 50 caracteres.

Ejemplo sin escribir la contraseña literal en el historial:

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

Variables opcionales: `BITACORA_ADMIN_NAME`, `BITACORA_ADMIN_EMPRESA_ID` y `BITACORA_ADMIN_SEDE_ID`. Por defecto se asigna la empresa 6 y la sede administrativa 11.

## 16. Operación y mantenimiento

### 16.1 Estado operativo

```bash
docker compose exec app php scripts/bitacora_status.php
```

El resultado incluye:

- Borradores por versión de clave y próxima expiración.
- Borradores expirados.
- Cola de correo agrupada por estado.
- Locks de cola antiguos.
- Reservas de envío sin respuesta.
- Espacio libre del almacenamiento.

### 16.2 Cola de correo

Procesamiento manual:

```bash
docker compose exec app php scripts/process_bitacora_email_queue.php
```

El worker:

- Reclama un trabajo pendiente con bloqueo transaccional.
- Usa `SKIP LOCKED` cuando MySQL lo admite.
- Incrementa intentos.
- Envía adjuntos solo si la ruta privada es válida.
- Reintenta hasta tres veces con backoff de hasta una hora.
- Libera locks antiguos de más de 30 minutos.
- Actualiza `bitacora_envios` al enviar o fallar definitivamente.

### 16.3 Limpieza

```bash
docker compose exec app php database/cleanup_bitacora_pdfs.php
docker compose exec app php database/cleanup_bitacora_drafts.php
```

Para eliminar solamente registros de PDF cuyo archivo físico ya no existe:

```bash
docker compose exec app php database/cleanup_bitacora_pdfs.php --missing
```

En producción, `maintenance` ejecuta ambas limpiezas según sus intervalos y lotes.

### 16.4 Importación y administración de destinatarios

```bash
docker compose exec app php scripts/import_bitacora_recipients.php
```

Este comando debe ejecutarse con cuidado: importa la configuración estática en empresas que todavía están en modo `php` y luego las pasa a modo `database`. Los cambios posteriores deben hacerse desde `admin_destinatarios.php` o mediante SQL controlado y auditado.

## 17. Backups y recuperación

Un backup útil debe incluir los tres elementos siguientes:

- Dump completo de MySQL.
- Volumen/directorio `storage`, especialmente PDFs y logs requeridos para diagnóstico.
- `BITACORA_DRAFT_KEY_BASE64` y todas las claves del keyring vigentes.

Dump de producción:

```bash
docker compose -f docker-compose.prod.yml exec -T db \
  sh -lc 'mysqldump --single-transaction --quick --routines --events -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
  > backup_bitacora.sql
```

No se debe restaurar una base de borradores sin restaurar también sus claves. La base se puede abrir y consultar, pero los payloads cifrados no serán recuperables.

Antes de una actualización:

1. Respaldar base y volúmenes.
2. Revisar migraciones nuevas.
3. Construir la imagen.
4. Confirmar migraciones y healthchecks.
5. Confirmar worker, maintenance y almacenamiento.
6. Probar login, generación de PDF y correo.

## 18. Healthchecks y diagnóstico

### 18.1 Endpoints

| Endpoint | Validación |
| --- | --- |
| `/healthz` | Nginx responde `ok`; no valida dependencias |
| `/readyz` | Alias de readiness PHP |
| `/php-healthz.php` | MySQL, migraciones aplicadas, almacenamiento escribible y keyring criptográficamente utilizable |

`/readyz` puede devolver `503` por:

- MySQL inaccesible.
- Migración faltante.
- `BITACORA_STORAGE_PATH` inexistente o sin permisos.
- Keyring ausente, inválido o con versión activa inexistente.
- Borrador existente que no puede descifrarse con la clave configurada.

### 18.2 Diagnóstico rápido

```bash
docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml logs --tail=120 nginx app worker maintenance
docker compose -f docker-compose.prod.yml exec nginx wget -q -O - http://127.0.0.1/healthz
docker compose -f docker-compose.prod.yml exec nginx wget -q -O - http://127.0.0.1/php-healthz.php
```

Problemas frecuentes:

| Síntoma | Revisión |
| --- | --- |
| `504 Gateway Timeout` | Coolify debe apuntar a `nginx:80`, no a `app:9000` |
| `db is unhealthy` | Logs de MySQL, credenciales iniciales y tiempo de arranque |
| No se recuperan borradores | Keyring, versión activa y claves respaldadas |
| PDF no aparece | Permisos de `BITACORA_STORAGE_PATH`, espacio y logs PHP |
| Correo pendiente | Salud del worker, cola, intentos y configuración SMTP |
| Correo no llega en local | Revisar Mailpit, no el proveedor SMTP externo |
| Login bloqueado | Revisar combinación usuario/IP y esperar el lock de 15 minutos |

## 19. Seguridad de infraestructura y aplicación

- No versionar `.env`, backups, logs, `storage/`, `public/uploads/` ni `vendor/`.
- Mantener secretos fuera de chats, tickets y archivos públicos.
- Mantener `SMTP_VERIFY_TLS=true` en producción.
- No publicar `db`, PhpMyAdmin, PHP-FPM ni almacenamiento privado.
- Mantener el document root en `public/`.
- Nginx bloquea `.env`, Composer, `config/`, `storage/`, `vendor/`, logs y rutas legacy.
- Nginx aplica `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` y CSP.
- Los scripts propios deben vivir bajo `public/resources/`; no agregar JavaScript inline.
- Mantener CSRF en todos los formularios y POST administrativos.
- No poner datos de borrador en `localStorage` o `sessionStorage`.
- Verificar empresa y rol en cada operación; no confiar en `empresa_id` enviado por el navegador.
- Usar `password_hash`/`password_verify` para nuevas contraseñas.
- Revisar `bitacora_admin_audit` después de cambios administrativos.
- Hacer backup antes de migraciones y rotación de claves.

## 20. Verificación automatizada

Checks completos del entorno local:

```bash
sh scripts/checks.sh
```

Checks de producción con archivo de variables explícito:

```bash
BITACORA_COMPOSE_FILE=docker-compose.prod.yml \
BITACORA_COMPOSE_ENV_FILE=.env.production \
sh scripts/checks.sh
```

En PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/checks.ps1
```

La batería verifica:

- `docker compose config --quiet`.
- Construcción de imágenes productivas cuando aplica.
- Sintaxis PHP de `public`, `database`, `scripts` y `tests`.
- `tests/run.php`.
- `tests/section_mail_privacy_test.php`.
- `tests/integration.php`.
- `tests/recipient_admin_integration.php`.
- Validación y auditoría Composer en local.
- Configuración Nginx mediante `nginx -t`.

Comandos individuales definidos por `AGENTS.md`:

```bash
docker compose run --rm --no-deps app php tests/run.php
docker compose run --rm --no-deps app sh -lc "find public database scripts tests -name '*.php' -print0 | xargs -0 -n1 php -l"
docker compose run --rm --no-deps nginx nginx -t
docker compose config --quiet
```

## 21. Incidencias y límites conocidos

Estas observaciones deben considerarse antes de afirmar que una instalación está completamente saneada:

1. La rama asíncrona de `public/scripts/bitacora_submission_helpers.php` decide si entra a cola con `$fullRecipients !== []`. La función de configuración devuelve las claves `to`, `cc` y `bcc` aunque sus listas estén vacías. La defensa correcta debería comprobar valores reales con `app_bitacora_recipients_have_values()`. En una instalación con destinatarios importados el flujo normal funciona, pero una configuración vacía puede producir un intento de cola sin destinatarios.
2. El Compose local permite que `BITACORA_DRAFT_KEY_BASE64` quede vacío para facilitar la copia inicial. La aplicación de borradores y readiness sí exigen una clave válida; siempre se debe reemplazar el placeholder antes de probar borradores o `/readyz`.
3. Las migraciones de semillas y normalización usan collations propias de MySQL 8 en algunos puntos. La plataforma de producción debe ser MySQL 8 compatible; no se debe asumir compatibilidad completa con MariaDB.
4. SMTP convencional no permite confirmar atómicamente si el proveedor aceptó el correo justo antes de una interrupción. El estado `delivery_unknown` exige verificación externa antes de repetir el envío.
5. El despliegue cPanel no incluye el worker Docker. Si se activa correo asíncrono allí, debe existir un cron que ejecute explícitamente `scripts/process_bitacora_email_queue.php`.

## 22. Checklist para cambios

### Código PHP

- [ ] El archivo nuevo queda fuera de `public/` si contiene lógica sensible.
- [ ] Las vistas protegidas validan sesión y los POST validan CSRF/autorización.
- [ ] No se duplicó configuración SMTP.
- [ ] No se escriben PDFs bajo `public/`.
- [ ] Se actualizó la documentación si cambia un flujo visible.

### Esquema y configuración

- [ ] La migración es ordenada, idempotente cuando corresponde y conserva checksum.
- [ ] Se hizo backup antes de modificar producción.
- [ ] Se revisaron `bitacora_empresa_config`, sedes y destinatarios.
- [ ] Los identificadores dinámicos no colisionan.
- [ ] Se consideró el impacto en borradores existentes y `schema_hash`.

### Verificación

- [ ] `docker compose config --quiet` pasa.
- [ ] Sintaxis PHP pasa.
- [ ] Pruebas unitarias/integración pasan.
- [ ] `nginx -t` pasa.
- [ ] Login, timeout, borrador, PDF y correo fueron probados según el entorno.
- [ ] `/healthz` y `/readyz` responden correctamente en producción.
