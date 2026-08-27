<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/bitacora.php';
require_once __DIR__ . '/../config/admin_destinatarios_helpers.php';

app_require_admin();

$empresaOptions = app_bitacora_empresa_options();
if ($empresaOptions === []) {
    app_fail_request('No hay empresas configuradas para la bitácora.', 500);
}

$empresaId = (int) ($_GET['empresa'] ?? app_current_empresa_id());
if (!isset($empresaOptions[$empresaId])) {
    $empresaId = app_bitacora_first_empresa_id();
}
if ($empresaId > 0) {
    app_set_admin_empresa_id($empresaId);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    app_require_post_admin(false);

    $postEmpresaId = (int) ($_POST['empresa_id'] ?? 0);
    if (!isset($empresaOptions[$postEmpresaId])) {
        bit_admin_destinatarios_redirect($empresaId, BIT_ADMIN_GLOBAL_SCOPE, 'recipients', 'danger', 'Empresa inválida.');
    }

    $empresaId = $postEmpresaId;
    app_set_admin_empresa_id($empresaId);
    $companyConfig = app_bitacora_config($empresaId);
    $scope = trim((string) ($_POST['scope'] ?? BIT_ADMIN_GLOBAL_SCOPE));
    $tab = (string) ($_POST['tab'] ?? 'recipients');
    if ($companyConfig === null) {
        bit_admin_destinatarios_redirect($empresaId, $scope, $tab, 'danger', 'La empresa no tiene configuración de bitácora.');
    }

    try {
        $action = trim((string) ($_POST['action'] ?? ''));
        if ($action === 'save_recipient') {
            bit_admin_save_recipient($empresaId, $_POST, $companyConfig);
            bit_admin_destinatarios_redirect($empresaId, $scope, 'recipients', 'success', 'Destinatario guardado correctamente.');
        }
        if ($action === 'toggle_recipient') {
            bit_admin_toggle_recipient($empresaId, (int) ($_POST['id'] ?? 0), (int) ($_POST['activo'] ?? 0));
            bit_admin_destinatarios_redirect($empresaId, $scope, 'recipients', 'success', 'Estado del destinatario actualizado.');
        }
        if ($action === 'move_recipient') {
            bit_admin_move_recipient($empresaId, (int) ($_POST['id'] ?? 0), trim((string) ($_POST['direction'] ?? '')));
            bit_admin_destinatarios_redirect($empresaId, $scope, 'recipients', 'success', 'Orden actualizado correctamente.');
        }
        if ($action === 'save_section_recipient') {
            bit_admin_save_section_recipient($empresaId, $_POST, $companyConfig);
            bit_admin_destinatarios_redirect($empresaId, $scope, 'sections', 'success', 'Asignación por sección guardada correctamente.');
        }
        if ($action === 'toggle_section_recipient') {
            if (($companyConfig['type'] ?? '') !== 'operational') {
                throw new InvalidArgumentException('Las asignaciones por sección solo aplican a empresas operativas.');
            }
            bit_admin_toggle_recipient($empresaId, (int) ($_POST['id'] ?? 0), (int) ($_POST['activo'] ?? 0), true);
            bit_admin_destinatarios_redirect($empresaId, $scope, 'sections', 'success', 'Estado de la asignación actualizado.');
        }

        throw new InvalidArgumentException('Acción administrativa inválida.');
    } catch (Throwable $e) {
        error_log('Error administrando destinatarios de bitácora: ' . $e->getMessage());
        bit_admin_destinatarios_redirect($empresaId, $scope, $tab, 'danger', $e instanceof InvalidArgumentException ? $e->getMessage() : 'No fue posible guardar los cambios.');
    }
}

$companyConfig = app_bitacora_config($empresaId);
$scopeOptions = $companyConfig === null ? [] : bit_admin_scope_options($empresaId, $companyConfig);
$scope = trim((string) ($_GET['scope'] ?? BIT_ADMIN_GLOBAL_SCOPE));
if ($scopeOptions === [] || !isset($scopeOptions[$scope])) {
    $scope = BIT_ADMIN_GLOBAL_SCOPE;
}
$selectedSedeId = $scopeOptions[$scope]['id'] ?? null;
$activeTab = (string) ($_GET['tab'] ?? 'recipients');
if (!in_array($activeTab, ['recipients', 'sections'], true) || (($companyConfig['type'] ?? '') !== 'operational' && $activeTab === 'sections')) {
    $activeTab = 'recipients';
}

$recipientRows = $companyConfig === null ? [] : bit_admin_recipient_rows($empresaId, $selectedSedeId);
$recipientTypes = bit_admin_recipient_type_options();
$sectionOptions = $companyConfig !== null && ($companyConfig['type'] ?? '') === 'operational'
    ? bit_admin_section_options($empresaId, $companyConfig)
    : [];
$sectionOrder = bit_admin_section_order($sectionOptions);
$sectionRows = $sectionOptions === [] ? [] : bit_admin_section_recipient_rows($empresaId, $selectedSedeId, $sectionOrder);

$editingRecipient = $companyConfig === null ? null : bit_admin_recipient_by_id($empresaId, (int) ($_GET['edit'] ?? 0));
$editingSectionRecipient = $companyConfig === null ? null : bit_admin_section_recipient_by_id($empresaId, (int) ($_GET['section_edit'] ?? 0));
$recipientForm = [
    'id' => 0,
    'email' => '',
    'tipo' => 'to',
    'scope' => $scope,
    'activo' => 1,
];
if (is_array($editingRecipient)) {
    $recipientForm = array_merge($recipientForm, $editingRecipient, [
        'scope' => bit_admin_scope_from_sede_id($editingRecipient['idSede'] === null ? null : (int) $editingRecipient['idSede'], $scopeOptions),
    ]);
}

$sectionForm = [
    'id' => 0,
    'email' => '',
    'tipo' => 'to',
    'scope' => $scope,
    'section_key' => array_key_first($sectionOptions) ?: '',
    'activo' => 1,
];
if (is_array($editingSectionRecipient)) {
    $sectionForm = array_merge($sectionForm, $editingSectionRecipient, [
        'scope' => bit_admin_scope_from_sede_id($editingSectionRecipient['idSede'] === null ? null : (int) $editingSectionRecipient['idSede'], $scopeOptions),
    ]);
}

$preview = null;
if ($companyConfig !== null && $selectedSedeId !== null) {
    try {
        $preview = app_bitacora_recipients_for_sede($empresaId, $scope);
    } catch (Throwable $e) {
        error_log('No fue posible preparar vista previa de destinatarios: ' . $e->getMessage());
    }
}

$flash = $_SESSION['admin_destinatarios_flash'] ?? null;
unset($_SESSION['admin_destinatarios_flash']);
$source = app_bitacora_recipient_source($empresaId);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Parametrizar correos de bitácora</title>
    <link rel="stylesheet" href="../resources/css/bootstrap.min.css">
    <link rel="stylesheet" href="../resources/css/bitacora.css">
    <link rel="stylesheet" href="../resources/css/admin_formulario.css">
    <link rel="stylesheet" href="../resources/css/admin_destinatarios.css">
    <link rel="stylesheet" href="../resources/css/session_timeout.css">
    <link rel="shortcut icon" href="../resources/img/LOGO ALITAS-09.png">
</head>
<body class="admin-page" <?php echo app_session_client_attributes(); ?>>
<main class="admin-shell">
    <header class="admin-topbar">
        <div class="admin-brand">
            <img class="admin-brand-logo" src="../resources/img/LOGO ALITAS-09.png" alt="Logo">
            <div>
                <p class="admin-eyebrow">Panel de administración</p>
                <h1 class="admin-title">Parametrizar correos</h1>
            </div>
        </div>
        <div class="admin-session">
            <span class="admin-user-pill">Usuario: <strong><?php echo app_h($_SESSION['s_nombre'] ?? ''); ?></strong> · Admin</span>
            <a class="admin-btn-ghost" href="bitacora.php?empresa=<?php echo app_h((string) $empresaId); ?>">Ver bitácora</a>
            <a class="admin-btn-ghost" href="admin_formulario.php?empresa=<?php echo app_h((string) $empresaId); ?>">Administrar formulario</a>
            <?php echo app_logout_form('admin-btn-danger', 'Cerrar sesión'); ?>
        </div>
    </header>

    <?php if (is_array($flash) && isset($flash['message'], $flash['type'])): ?>
        <div class="alert alert-<?php echo app_h((string) $flash['type']); ?> admin-flash"><?php echo app_h((string) $flash['message']); ?></div>
    <?php endif; ?>

    <div class="admin-card mb-3">
        <div class="admin-card-body">
            <form method="get" class="form-row align-items-end">
                <input type="hidden" name="tab" value="<?php echo app_h($activeTab); ?>">
                <div class="form-group col-md-5 mb-md-0">
                    <label for="empresa">Empresa</label>
                    <select id="empresa" name="empresa" class="form-control" data-auto-submit="1">
                        <?php foreach ($empresaOptions as $optionId => $optionLabel): ?>
                            <option value="<?php echo app_h((string) $optionId); ?>" <?php echo (int) $optionId === $empresaId ? 'selected' : ''; ?>><?php echo app_h($optionLabel); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-5 mb-md-0">
                    <label for="scope">Alcance de la lista</label>
                    <select id="scope" name="scope" class="form-control" data-auto-submit="1">
                        <?php foreach ($scopeOptions as $scopeValue => $scopeOption): ?>
                            <option value="<?php echo app_h((string) $scopeValue); ?>" <?php echo $scope === $scopeValue ? 'selected' : ''; ?>><?php echo app_h((string) $scopeOption['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-2 mb-0">
                    <p class="admin-muted mb-0">Fuente actual:<br><strong><?php echo $source === 'database' ? 'Base de datos' : 'PHP + BD'; ?></strong></p>
                </div>
            </form>
        </div>
    </div>

    <?php if ($companyConfig === null): ?>
        <div class="alert alert-warning">La empresa seleccionada no tiene configuración de bitácora.</div>
    <?php else: ?>
        <nav class="admin-tabs" aria-label="Secciones de administración">
            <a class="admin-tab <?php echo $activeTab === 'recipients' ? 'is-active' : ''; ?>" href="?empresa=<?php echo app_h((string) $empresaId); ?>&scope=<?php echo app_h($scope); ?>&tab=recipients">Correo completo</a>
            <?php if (($companyConfig['type'] ?? '') === 'operational'): ?>
                <a class="admin-tab <?php echo $activeTab === 'sections' ? 'is-active' : ''; ?>" href="?empresa=<?php echo app_h((string) $empresaId); ?>&scope=<?php echo app_h($scope); ?>&tab=sections">Por sección</a>
            <?php endif; ?>
        </nav>

        <?php if ($activeTab === 'recipients'): ?>
            <section class="admin-card mb-3">
                <div class="admin-card-header">
                    <div>
                        <h2 class="h5 mb-0"><?php echo $recipientForm['id'] ? 'Editar destinatario' : 'Agregar destinatario'; ?></h2>
                        <p class="admin-muted">Se conserva el orden actual por alcance y tipo. Las listas por sede se agregan antes que las globales.</p>
                    </div>
                </div>
                <div class="admin-card-body">
                    <form method="post" class="row align-items-end">
                        <?php echo app_csrf_input(); ?>
                        <input type="hidden" name="action" value="save_recipient">
                        <input type="hidden" name="empresa_id" value="<?php echo app_h((string) $empresaId); ?>">
                        <input type="hidden" name="tab" value="recipients">
                        <input type="hidden" name="id" value="<?php echo app_h((string) ($recipientForm['id'] ?? 0)); ?>">
                        <div class="form-group col-md-4">
                            <label for="recipient_email">Correo electrónico</label>
                            <input id="recipient_email" name="email" type="email" class="form-control" maxlength="120" value="<?php echo app_h((string) ($recipientForm['email'] ?? '')); ?>" required>
                        </div>
                        <div class="form-group col-md-2">
                            <label for="recipient_type">Tipo</label>
                            <select id="recipient_type" name="tipo" class="form-control">
                                <?php foreach ($recipientTypes as $type => $label): ?>
                                    <option value="<?php echo app_h($type); ?>" <?php echo (string) ($recipientForm['tipo'] ?? 'to') === $type ? 'selected' : ''; ?>><?php echo app_h($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="recipient_scope">Alcance</label>
                            <select id="recipient_scope" name="scope" class="form-control">
                                <?php foreach ($scopeOptions as $scopeValue => $scopeOption): ?>
                                    <option value="<?php echo app_h((string) $scopeValue); ?>" <?php echo (string) ($recipientForm['scope'] ?? $scope) === $scopeValue ? 'selected' : ''; ?>><?php echo app_h((string) $scopeOption['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <div class="form-check mb-2">
                                <input id="recipient_active" name="activo" type="checkbox" class="form-check-input" value="1" <?php echo !empty($recipientForm['activo']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="recipient_active">Activo</label>
                            </div>
                            <button type="submit" class="admin-btn-primary btn-block"><?php echo $recipientForm['id'] ? 'Guardar cambios' : 'Agregar correo'; ?></button>
                        </div>
                        <?php if (!empty($recipientForm['id'])): ?>
                            <div class="col-12"><a href="?empresa=<?php echo app_h((string) $empresaId); ?>&scope=<?php echo app_h($scope); ?>&tab=recipients">Cancelar edición</a></div>
                        <?php endif; ?>
                    </form>
                </div>
            </section>

            <section class="admin-card mb-3">
                <div class="admin-card-header">
                    <div>
                        <h2 class="h5 mb-0">Destinatarios <?php echo app_h((string) ($scopeOptions[$scope]['label'] ?? '')); ?></h2>
                        <p class="admin-muted">Los cambios afectan nuevos envíos. Un destinatario inactivo no se agrega al correo.</p>
                    </div>
                </div>
                <div class="admin-card-body">
                    <?php if ($recipientRows === []): ?>
                        <p class="admin-muted mb-0">No hay destinatarios configurados en este alcance.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover admin-mail-table">
                                <thead><tr><th>Orden</th><th>Correo</th><th>Tipo</th><th>Estado</th><th>Acciones</th></tr></thead>
                                <tbody>
                                <?php foreach ($recipientRows as $row): ?>
                                    <tr>
                                        <td><?php echo app_h((string) ($row['position'] ?? $row['orden'])); ?></td>
                                        <td><code><?php echo app_h((string) $row['email']); ?></code></td>
                                        <td><?php echo app_h($recipientTypes[(string) $row['tipo']] ?? (string) $row['tipo']); ?></td>
                                        <td><span class="field-chip <?php echo !empty($row['activo']) ? 'mail-chip-active' : 'mail-chip-inactive'; ?>"><?php echo !empty($row['activo']) ? 'Activo' : 'Inactivo'; ?></span></td>
                                        <td>
                                            <div class="d-flex admin-actions-sm">
                                                <form method="post" class="mail-inline-form">
                                                    <?php echo app_csrf_input(); ?>
                                                    <input type="hidden" name="action" value="move_recipient">
                                                    <input type="hidden" name="empresa_id" value="<?php echo app_h((string) $empresaId); ?>">
                                                    <input type="hidden" name="scope" value="<?php echo app_h($scope); ?>">
                                                    <input type="hidden" name="tab" value="recipients">
                                                    <input type="hidden" name="id" value="<?php echo app_h((string) $row['id']); ?>">
                                                    <button type="submit" name="direction" value="up" class="admin-btn-icon" title="Subir" aria-label="Subir">&#8593;</button>
                                                    <button type="submit" name="direction" value="down" class="admin-btn-icon" title="Bajar" aria-label="Bajar">&#8595;</button>
                                                </form>
                                                <a class="admin-btn-sm" href="?empresa=<?php echo app_h((string) $empresaId); ?>&scope=<?php echo app_h($scope); ?>&tab=recipients&edit=<?php echo app_h((string) $row['id']); ?>">Editar</a>
                                                <form method="post" class="mail-inline-form" data-confirm="¿Cambiar el estado de este destinatario?">
                                                    <?php echo app_csrf_input(); ?>
                                                    <input type="hidden" name="action" value="toggle_recipient">
                                                    <input type="hidden" name="empresa_id" value="<?php echo app_h((string) $empresaId); ?>">
                                                    <input type="hidden" name="scope" value="<?php echo app_h($scope); ?>">
                                                    <input type="hidden" name="tab" value="recipients">
                                                    <input type="hidden" name="id" value="<?php echo app_h((string) $row['id']); ?>">
                                                    <input type="hidden" name="activo" value="<?php echo !empty($row['activo']) ? '0' : '1'; ?>">
                                                    <button type="submit" class="admin-btn-sm admin-btn-danger-ghost"><?php echo !empty($row['activo']) ? 'Desactivar' : 'Activar'; ?></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php else: ?>
            <section class="admin-card mb-3">
                <div class="admin-card-header">
                    <div>
                        <h2 class="h5 mb-0"><?php echo $sectionForm['id'] ? 'Editar asignación por sección' : 'Agregar asignación por sección'; ?></h2>
                        <p class="admin-muted">El destinatario recibe solamente las secciones asignadas y queda fuera del correo completo.</p>
                    </div>
                </div>
                <div class="admin-card-body">
                    <form method="post" class="row align-items-end">
                        <?php echo app_csrf_input(); ?>
                        <input type="hidden" name="action" value="save_section_recipient">
                        <input type="hidden" name="empresa_id" value="<?php echo app_h((string) $empresaId); ?>">
                        <input type="hidden" name="tab" value="sections">
                        <input type="hidden" name="id" value="<?php echo app_h((string) ($sectionForm['id'] ?? 0)); ?>">
                        <div class="form-group col-md-3">
                            <label for="section_email">Correo electrónico</label>
                            <input id="section_email" name="email" type="email" class="form-control" maxlength="120" value="<?php echo app_h((string) ($sectionForm['email'] ?? '')); ?>" required>
                        </div>
                        <div class="form-group col-md-2">
                            <label for="section_type">Tipo</label>
                            <select id="section_type" name="tipo" class="form-control">
                                <?php foreach ($recipientTypes as $type => $label): ?>
                                    <option value="<?php echo app_h($type); ?>" <?php echo (string) ($sectionForm['tipo'] ?? 'to') === $type ? 'selected' : ''; ?>><?php echo app_h($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="section_scope">Alcance</label>
                            <select id="section_scope" name="scope" class="form-control">
                                <?php foreach ($scopeOptions as $scopeValue => $scopeOption): ?>
                                    <option value="<?php echo app_h((string) $scopeValue); ?>" <?php echo (string) ($sectionForm['scope'] ?? $scope) === $scopeValue ? 'selected' : ''; ?>><?php echo app_h((string) $scopeOption['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="section_key">Sección</label>
                            <select id="section_key" name="section_key" class="form-control">
                                <?php foreach ($sectionOptions as $sectionKey => $sectionTitle): ?>
                                    <option value="<?php echo app_h($sectionKey); ?>" <?php echo (string) ($sectionForm['section_key'] ?? '') === $sectionKey ? 'selected' : ''; ?>><?php echo app_h($sectionTitle); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-1">
                            <div class="form-check mb-2">
                                <input id="section_active" name="activo" type="checkbox" class="form-check-input" value="1" <?php echo !empty($sectionForm['activo']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="section_active">Activo</label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <button type="submit" class="admin-btn-primary"><?php echo $sectionForm['id'] ? 'Guardar cambios' : 'Agregar asignación'; ?></button>
                            <?php if (!empty($sectionForm['id'])): ?>
                                <a class="admin-btn-ghost ml-2" href="?empresa=<?php echo app_h((string) $empresaId); ?>&scope=<?php echo app_h($scope); ?>&tab=sections">Cancelar edición</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </section>

            <section class="admin-card mb-3">
                <div class="admin-card-header">
                    <div>
                        <h2 class="h5 mb-0">Asignaciones <?php echo app_h((string) ($scopeOptions[$scope]['label'] ?? '')); ?></h2>
                        <p class="admin-muted">El contenido se renderiza en el orden actual del formulario.</p>
                    </div>
                </div>
                <div class="admin-card-body">
                    <?php if ($sectionRows === []): ?>
                        <p class="admin-muted mb-0">No hay asignaciones por sección en este alcance.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover admin-mail-table">
                                <thead><tr><th>Sección</th><th>Correo</th><th>Tipo</th><th>Estado</th><th>Acciones</th></tr></thead>
                                <tbody>
                                <?php foreach ($sectionRows as $row): ?>
                                    <tr>
                                        <td><?php echo app_h($sectionOptions[(string) $row['section_key']] ?? (string) $row['section_key']); ?></td>
                                        <td><code><?php echo app_h((string) $row['email']); ?></code></td>
                                        <td><?php echo app_h($recipientTypes[(string) $row['tipo']] ?? (string) $row['tipo']); ?></td>
                                        <td><span class="field-chip <?php echo !empty($row['activo']) ? 'mail-chip-active' : 'mail-chip-inactive'; ?>"><?php echo !empty($row['activo']) ? 'Activo' : 'Inactivo'; ?></span></td>
                                        <td>
                                            <div class="d-flex admin-actions-sm">
                                                <a class="admin-btn-sm" href="?empresa=<?php echo app_h((string) $empresaId); ?>&scope=<?php echo app_h($scope); ?>&tab=sections&section_edit=<?php echo app_h((string) $row['id']); ?>">Editar</a>
                                                <form method="post" class="mail-inline-form" data-confirm="¿Cambiar el estado de esta asignación?">
                                                    <?php echo app_csrf_input(); ?>
                                                    <input type="hidden" name="action" value="toggle_section_recipient">
                                                    <input type="hidden" name="empresa_id" value="<?php echo app_h((string) $empresaId); ?>">
                                                    <input type="hidden" name="scope" value="<?php echo app_h($scope); ?>">
                                                    <input type="hidden" name="tab" value="sections">
                                                    <input type="hidden" name="id" value="<?php echo app_h((string) $row['id']); ?>">
                                                    <input type="hidden" name="activo" value="<?php echo !empty($row['activo']) ? '0' : '1'; ?>">
                                                    <button type="submit" class="admin-btn-sm admin-btn-danger-ghost"><?php echo !empty($row['activo']) ? 'Desactivar' : 'Activar'; ?></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if (is_array($preview)): ?>
            <section class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <h2 class="h5 mb-0">Vista efectiva para <?php echo app_h($scope); ?></h2>
                        <p class="admin-muted">Esta es la lista que utilizará el próximo envío para la sede seleccionada.</p>
                    </div>
                </div>
                <div class="admin-card-body mail-preview-grid">
                    <?php foreach ($recipientTypes as $type => $label): ?>
                        <div>
                            <h3><?php echo app_h($label); ?></h3>
                            <?php if (empty($preview[$type])): ?>
                                <p class="admin-muted">Sin destinatarios.</p>
                            <?php else: ?>
                                <ol>
                                    <?php foreach ((array) $preview[$type] as $email): ?><li><code><?php echo app_h((string) $email); ?></code></li><?php endforeach; ?>
                                </ol>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/session_timeout.php'; ?>
<script src="../resources/jquery/jquery-3.6.0.min.js"></script>
<script src="../resources/js/session_timeout.js"></script>
<script src="../resources/js/admin_destinatarios.js"></script>
</body>
</html>
