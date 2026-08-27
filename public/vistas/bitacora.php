<?php
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/bitacora.php';
require_once __DIR__ . '/bitacora_view_helpers.php';

app_require_login();

if (app_is_admin()) {
    $requestedEmpresaId = (int) ($_GET['empresa'] ?? 0);
    if ($requestedEmpresaId > 0 && app_bitacora_config($requestedEmpresaId) !== null) {
        app_set_admin_empresa_id($requestedEmpresaId);
    }

    $empresaId = app_current_empresa_id();
    if (app_bitacora_config($empresaId) === null) {
        $fallbackEmpresaId = app_bitacora_first_empresa_id();
        if ($fallbackEmpresaId > 0) {
            app_set_admin_empresa_id($fallbackEmpresaId);
            $empresaId = $fallbackEmpresaId;
        }
    }
} else {
    $empresaId = app_current_empresa_id();
}

$config = app_bitacora_config($empresaId);
$pageTitle = $config['title'] ?? 'Bitácora Mister Wings';
$empresaOptions = app_is_admin() ? app_bitacora_empresa_options() : [];
$formType = (string) ($config['type'] ?? 'operational');

?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo app_h($pageTitle); ?></title>
    <link rel="stylesheet" href="../resources/css/bootstrap.min.css">
    <link rel="stylesheet" href="../resources/sweetalert/sweetalert2.min.css">
    <link rel="stylesheet" href="../resources/select2/select2.min.css">
    <link rel="stylesheet" href="../resources/css/bitacora.css">
    <link rel="stylesheet" href="../resources/css/session_timeout.css">
    <link rel="shortcut icon" href="../resources/img/LOGO ALITAS-09.png" alt="Logo">
</head>
<body class="bitacora-page" <?php echo app_session_client_attributes(); ?> data-bitacora-user="<?php echo app_h((string) ($_SESSION['s_usuario'] ?? '')); ?>" data-bitacora-nombre="<?php echo app_h((string) ($_SESSION['s_nombre'] ?? '')); ?>" data-bitacora-type="<?php echo app_h($formType); ?>">
<main class="bit-shell">
    <header class="bit-topbar">
        <div class="bit-brand">
            <img class="bit-brand-logo" src="../resources/img/LOGO ALITAS-09.png" alt="Logo">
            <div class="bit-brand-copy">
                <p class="bit-eyebrow">Bitácora digital</p>
                <h1 class="bit-title"><?php echo app_h($pageTitle); ?></h1>
                <p class="bit-header-caption"><?php echo $formType === 'supervision' ? 'Supervisión de campo · registro de hallazgos' : 'Control operativo · registro de turno'; ?></p>
            </div>
        </div>
        <div class="bit-session">
            <?php if (app_is_admin()): ?>
                <form method="get" class="d-flex align-items-center bit-admin-company-form">
                    <label class="mb-0" for="empresa">Empresa activa</label>
                    <select id="empresa" name="empresa" class="form-control form-control-sm bit-company-select bit-auto-submit">
                        <?php foreach ($empresaOptions as $optionId => $optionLabel): ?>
                            <option value="<?php echo app_h((string) $optionId); ?>" <?php echo (int) $optionId === $empresaId ? 'selected' : ''; ?>><?php echo app_h($optionLabel); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <a class="bit-logout" href="admin_formulario.php?empresa=<?php echo app_h((string) $empresaId); ?>" role="button">Administrar formulario</a>
                <a class="bit-logout" href="admin_destinatarios.php?empresa=<?php echo app_h((string) $empresaId); ?>" role="button">Parametrizar correos</a>
            <?php endif; ?>
            <span class="bit-user-pill">Usuario: <strong><?php echo app_h($_SESSION['s_nombre'] ?? ''); ?></strong><?php echo app_is_admin() ? ' · Admin' : ''; ?></span>
            <?php echo app_logout_form('bit-logout', 'Cerrar sesión'); ?>
        </div>
    </header>

    <div class="bit-progress-wrap" aria-label="Progreso de la bitácora">
        <div class="bit-progress-copy">
            <span class="bit-progress-kicker">Estado del registro</span>
            <strong class="bit-progress-text" id="bitProgressText" aria-live="polite">Calculando progreso...</strong>
        </div>
        <div class="bit-progress-track" aria-hidden="true"><div class="bit-progress-fill" id="bitProgressFill"></div></div>
    </div>
    <?php if ($config !== null): ?>
        <section class="bit-draft-bar" id="bitDraftBar" data-endpoint="../scripts/bitacora_draft.php" aria-label="Borrador de la bitácora">
            <div class="bit-draft-status" role="status" aria-live="polite" aria-atomic="true">
                <span class="bit-draft-dot" aria-hidden="true"></span>
                <span id="bitDraftStatus">Buscando borrador...</span>
                <small id="bitDraftStatusDetail"></small>
            </div>
            <div class="bit-draft-conflict-actions" id="bitDraftConflictActions" hidden>
                <button id="bit_draft_load_server" type="button" class="bit-btn-ghost">Cargar servidor</button>
                <button id="bit_draft_overwrite" type="button" class="bit-btn-secondary">Sobrescribir</button>
            </div>
        </section>
    <?php endif; ?>
    <nav class="bit-section-nav" id="bitSectionNav" aria-label="Secciones de la bitácora"></nav>

    <section class="bit-card">
        <div class="bit-card-header">
            <div class="bit-card-header-copy">
                <span class="bit-card-kicker">Hoja de control / <?php echo $formType === 'supervision' ? 'supervisión' : 'operaciones'; ?></span>
                <h2><?php echo $formType === 'supervision' ? 'Reporte de supervisión' : 'Registro operativo'; ?></h2>
                <p>Completa los campos requeridos. La información se enviará al correo configurado y se generará el PDF cuando aplique.</p>
            </div>
            <div class="bit-card-stamp" aria-hidden="true"><strong>MW</strong><span>registro<br>en curso</span></div>
        </div>
        <div class="bit-card-body">
            <?php if ($config === null): ?>
                <div class="alert alert-warning mb-0">No hay configuración para esta empresa.</div>
            <?php elseif ($formType === 'supervision'): ?>
                <?php bit_view_supervision_form($config, $empresaId); ?>
            <?php else: ?>
                <?php bit_view_operational_form($config, $empresaId); ?>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php require __DIR__ . '/session_timeout.php'; ?>

<script src="../resources/jquery/jquery-3.6.0.min.js"></script>
<script src="../resources/sweetalert/sweetalert2.all.min.js"></script>
<script src="../resources/select2/select2.min.js"></script>
<script src="../resources/js/session_timeout.js"></script>
<script src="../resources/js/bitacora.js"></script>
<script src="../localstorage_bitacora.js"></script>
</body>
</html>
