<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/bitacora.php';
require_once __DIR__ . '/../config/admin_formulario_helpers.php';

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

$isJsonRequest = (($_POST['ajax'] ?? '') === '1' || ($_GET['ajax'] ?? '') === '1');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    app_require_post_admin($isJsonRequest);

    $postEmpresaId = (int) ($_POST['empresa_id'] ?? 0);
    if (!isset($empresaOptions[$postEmpresaId])) {
        bit_admin_redirect($empresaId, 'danger', 'Empresa inválida.');
    }

    $empresaId = $postEmpresaId;
    app_set_admin_empresa_id($empresaId);
    $companyConfig = app_bitacora_config($empresaId);
    if ($companyConfig === null) {
        bit_admin_redirect($empresaId, 'danger', 'La empresa no tiene configuración de bitácora.');
    }

    $json = bit_admin_config_json($empresaId, $companyConfig);
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'save_hidden') {
            $allowed = array_flip(array_map(static fn($field) => $field['name'], bit_admin_hideable_base_fields($empresaId, $companyConfig, $json)));
            $selected = [];
            foreach ((array) ($_POST['hidden_fields'] ?? []) as $name) {
                $name = trim((string) $name);
                if (isset($allowed[$name])) {
                    $selected[] = $name;
                }
            }
            $json['hidden_fields'] = array_values(array_unique($selected));
            bit_admin_save_config_json($empresaId, $companyConfig, $json);
            bit_admin_audit_log($empresaId, 'save_hidden', 'hidden_fields', ['hidden_fields' => $json['hidden_fields']]);
            bit_admin_respond($empresaId, 'success', 'Campos visibles actualizados correctamente.', $isJsonRequest);
        }

        if ($action === 'save_base_field') {
            $baseFieldName = trim((string) ($_POST['name'] ?? ''));
            $baseFieldMap = bit_admin_base_field_map($empresaId, $companyConfig);
            if (!isset($baseFieldMap[$baseFieldName])) {
                bit_admin_respond($empresaId, 'danger', 'El campo base no existe o no se puede editar.', $isJsonRequest);
            }

            [$override, $message] = bit_admin_base_override_from_post($baseFieldMap[$baseFieldName], $companyConfig);
            if ($override === null) {
                bit_admin_respond($empresaId, 'danger', $message, $isJsonRequest);
            }

            $json['field_overrides'] = app_bitacora_normalize_field_overrides($json, bit_admin_base_sections($empresaId, $companyConfig));
            $json['field_overrides'][$baseFieldName] = $override;
            bit_admin_save_config_json($empresaId, $companyConfig, $json);
            bit_admin_audit_log($empresaId, 'save_base_field', $baseFieldName, ['override' => $override]);
            bit_admin_respond($empresaId, 'success', 'Campo base actualizado correctamente.', $isJsonRequest);
        }

        if ($action === 'reset_base_field') {
            $baseFieldName = trim((string) ($_POST['name'] ?? ''));
            $baseFieldMap = bit_admin_base_field_map($empresaId, $companyConfig);
            if (!isset($baseFieldMap[$baseFieldName])) {
                bit_admin_respond($empresaId, 'danger', 'El campo base no existe o no se puede restaurar.', $isJsonRequest);
            }

            $json['field_overrides'] = app_bitacora_normalize_field_overrides($json, bit_admin_base_sections($empresaId, $companyConfig));
            unset($json['field_overrides'][$baseFieldName]);
            bit_admin_save_config_json($empresaId, $companyConfig, $json);
            bit_admin_audit_log($empresaId, 'reset_base_field', $baseFieldName);
            bit_admin_respond($empresaId, 'success', 'Campo base restaurado correctamente.', $isJsonRequest);
        }

        if ($action === 'delete_field') {
            $deleteName = trim((string) ($_POST['name'] ?? ''));
            $json['dynamic_fields'] = array_values(array_filter(bit_admin_dynamic_fields($json), static function ($field) use ($deleteName) {
                return (string) ($field['name'] ?? '') !== $deleteName;
            }));
            bit_admin_save_config_json($empresaId, $companyConfig, $json);
            bit_admin_audit_log($empresaId, 'delete_field', $deleteName);
            bit_admin_respond($empresaId, 'success', 'Campo eliminado correctamente.', $isJsonRequest);
        }

        if ($action === 'save_field') {
            $originalName = trim((string) ($_POST['original_name'] ?? ''));
            [$field, $message] = bit_admin_field_from_post($companyConfig);
            if ($field === null) {
                bit_admin_respond($empresaId, 'danger', $message, $isJsonRequest);
            }

            $fieldName = (string) ($field['name'] ?? '');
            $usedIdentifiers = array_fill_keys(bit_admin_base_field_identifiers($empresaId, $companyConfig), true);

            $dynamicFields = [];
            foreach (bit_admin_dynamic_fields($json) as $existingField) {
                $existingName = (string) ($existingField['name'] ?? '');
                if ($originalName !== '' && $existingName === $originalName) {
                    continue;
                }
                foreach (bit_admin_field_identifiers($existingField) as $identifier) {
                    $usedIdentifiers[$identifier] = true;
                }
                $dynamicFields[] = $existingField;
            }

            $conflicts = array_values(array_intersect(bit_admin_field_identifiers($field), array_keys($usedIdentifiers)));
            if ($conflicts !== []) {
                bit_admin_respond($empresaId, 'danger', 'Ya existe el identificador técnico: ' . implode(', ', $conflicts) . '.', $isJsonRequest);
            }

            $dynamicFields[] = $field;
            $json['dynamic_fields'] = $dynamicFields;
            bit_admin_save_config_json($empresaId, $companyConfig, $json);
            bit_admin_audit_log($empresaId, $originalName === '' ? 'create_field' : 'update_field', $fieldName, ['original_name' => $originalName, 'field' => $field]);
            bit_admin_respond($empresaId, 'success', 'Campo guardado correctamente.', $isJsonRequest);
        }

        if ($action === 'save_order') {
            $scope = trim((string) ($_POST['scope'] ?? ''));
            $rawOrder = json_decode((string) ($_POST['order'] ?? '[]'), true);
            $order = is_array($rawOrder) ? array_values(array_filter(array_map(static fn($name) => trim((string) $name), $rawOrder), static fn($name) => $name !== '')) : [];

            if ($order === []) {
                bit_admin_respond($empresaId, 'danger', 'El orden recibido está vacío.', $isJsonRequest);
            }

            if ($scope === 'dynamic') {
                $byName = [];
                foreach (bit_admin_dynamic_fields($json) as $field) {
                    $byName[(string) ($field['name'] ?? '')] = $field;
                }

                $reordered = [];
                foreach ($order as $name) {
                    if (!isset($byName[$name])) {
                        continue;
                    }
                    $field = $byName[$name];
                    $field['order'] = count($reordered) + 1;
                    $reordered[] = $field;
                    unset($byName[$name]);
                }

                $position = count($reordered);
                foreach ($byName as $field) {
                    $position++;
                    $field['order'] = $position;
                    $reordered[] = $field;
                }
                $json['dynamic_fields'] = $reordered;
            } elseif ($scope === 'base') {
                $allowed = array_flip(bit_admin_base_field_names($empresaId, $companyConfig));
                $overrides = app_bitacora_normalize_field_overrides($json, bit_admin_base_sections($empresaId, $companyConfig));
                foreach ($order as $index => $name) {
                    if (!isset($allowed[$name])) {
                        continue;
                    }
                    $override = $overrides[$name] ?? ['name' => $name];
                    $override['name'] = $name;
                    $override['order'] = $index + 1;
                    $overrides[$name] = $override;
                }
                $json['field_overrides'] = $overrides;
            } else {
                bit_admin_respond($empresaId, 'danger', 'Alcance inválido para reordenar.', $isJsonRequest);
            }

            bit_admin_save_config_json($empresaId, $companyConfig, $json);
            bit_admin_audit_log($empresaId, 'save_order', $scope, ['order' => $order]);
            bit_admin_respond($empresaId, 'success', 'Orden actualizado correctamente.', $isJsonRequest);
        }

        if ($action === 'duplicate_field') {
            $sourceName = trim((string) ($_POST['name'] ?? ''));
            $baseFieldMap = bit_admin_base_field_map($empresaId, $companyConfig);
            $source = $baseFieldMap[$sourceName] ?? null;
            $fromBase = $source !== null;

            if ($source === null) {
                foreach (bit_admin_dynamic_fields($json) as $field) {
                    if ((string) ($field['name'] ?? '') === $sourceName) {
                        $source = $field;
                        break;
                    }
                }
            }

            if ($source === null) {
                bit_admin_respond($empresaId, 'danger', 'El campo a duplicar no existe.', $isJsonRequest);
            }

            $existingNames = bit_admin_base_field_identifiers($empresaId, $companyConfig);
            foreach (bit_admin_dynamic_fields($json) as $field) {
                $existingNames = array_merge($existingNames, bit_admin_field_identifiers($field));
            }
            $existingNames = array_values(array_unique($existingNames));
            $reservedIdentifiers = $existingNames;

            $newName = bit_admin_next_field_name($sourceName, $existingNames);
            $existingNames[] = $newName;
            $copy = $source;
            $copy['name'] = $newName;
            $copy['label'] = ((string) ($copy['label'] ?? $sourceName)) . ' (copia)';

            if (!empty($source['detail_name'])) {
                $copy['detail_name'] = bit_admin_next_field_name((string) $source['detail_name'], $existingNames);
                $existingNames[] = $copy['detail_name'];
                unset($copy['group_id']);
            }

            if ((string) ($source['type'] ?? '') === 'multiselect_detail_group') {
                $copy['id'] = $newName;
            }

            if (!empty($source['quantity_name'])) {
                $copy['quantity_name'] = bit_admin_next_field_name((string) $source['quantity_name'], $existingNames);
                $existingNames[] = $copy['quantity_name'];
            }

            $copy['section'] = (string) ($source['section'] ?? $source['section_title'] ?? 'Campos adicionales');
            $copy['order'] = ((int) ($copy['order'] ?? 0)) + 1;
            unset($copy['dynamic'], $copy['section_title'], $copy['section_key']);

            $normalized = app_bitacora_normalize_dynamic_field($copy);
            if ($normalized === null) {
                bit_admin_respond($empresaId, 'danger', 'Este tipo de campo no se puede duplicar como campo dinámico.', $isJsonRequest);
            }
            $duplicateConflicts = array_values(array_intersect(bit_admin_field_identifiers($normalized), $reservedIdentifiers));
            if ($duplicateConflicts !== []) {
                bit_admin_respond($empresaId, 'danger', 'No se puede duplicar porque colisionan los identificadores: ' . implode(', ', $duplicateConflicts) . '.', $isJsonRequest);
            }

            $json['dynamic_fields'][] = $normalized;
            bit_admin_save_config_json($empresaId, $companyConfig, $json);
            bit_admin_audit_log($empresaId, 'duplicate_field', $newName, ['source' => $sourceName, 'from_base' => $fromBase]);
            bit_admin_respond($empresaId, 'success', 'Campo duplicado correctamente.', $isJsonRequest);
        }

        bit_admin_respond($empresaId, 'danger', 'Acción inválida.', $isJsonRequest);
    } catch (Throwable $e) {
        error_log('Error administrando formulario de bitácora: ' . $e->getMessage());
        bit_admin_respond($empresaId, 'danger', 'No fue posible guardar los cambios.', $isJsonRequest);
    }
}

$companyConfig = app_bitacora_config($empresaId);
$json = $companyConfig === null ? [] : bit_admin_config_json($empresaId, $companyConfig);
$dynamicFields = bit_admin_dynamic_fields($json);
$hiddenFields = app_bitacora_normalize_hidden_fields($json);
$hideableFields = $companyConfig === null ? [] : bit_admin_hideable_base_fields($empresaId, $companyConfig, $json);
$baseFields = $companyConfig === null ? [] : bit_admin_configurable_base_fields($empresaId, $companyConfig, $json);
$baseFieldMap = [];
foreach ($baseFields as $baseField) {
    $baseFieldMap[(string) ($baseField['name'] ?? '')] = $baseField;
}
$baseOverrides = $companyConfig === null ? [] : app_bitacora_normalize_field_overrides($json, bit_admin_base_sections($empresaId, $companyConfig));
$sectionNames = [];
if ($companyConfig !== null) {
    foreach (bit_admin_base_sections($empresaId, $companyConfig) as $section) {
        $sectionName = trim((string) ($section['title'] ?? ''));
        if ($sectionName !== '') {
            $sectionNames[] = $sectionName;
        }
    }
}
$sectionNames = array_values(array_unique(array_merge($sectionNames, ['Campos adicionales'])));

$editName = trim((string) ($_GET['edit'] ?? ''));
$editingField = null;
foreach ($dynamicFields as $field) {
    if ((string) ($field['name'] ?? '') === $editName) {
        $editingField = $field;
        break;
    }
}

$fieldDefaults = [
    'type' => 'text',
    'name' => '',
    'label' => '',
    'section' => 'Campos adicionales',
    'required' => false,
    'order' => 100,
    'col' => 'col-md-6',
    'options' => [],
    'sedes' => [],
    'min' => '',
    'max' => '',
    'step' => 'any',
    'suffix' => '',
    'suffix_singular' => '',
    'suffix_plural' => '',
    'number_format' => 'plain',
    'number_decimals' => '0',
    'detail_name' => '',
    'detail_label' => 'Detalle',
    'detail_type' => 'textarea',
    'no_apply_value' => 'No aplica visita',
    'placeholder' => 'Escribe Nombre Apellido - Cargo',
    'help' => '',
    'description' => '',
    'quantity_name' => '',
    'quantity_label' => 'Cantidad',
    'quantity_suffix' => '',
    'item_label' => 'Registro',
    'no_report_value' => 'Sin novedad',
    'zero_report_value' => 'Sin registros',
];
$fieldForm = array_merge($fieldDefaults, $editingField ?? []);

$editBaseName = trim((string) ($_GET['edit_base'] ?? ''));
$editingBaseField = $editBaseName !== '' && isset($baseFieldMap[$editBaseName]) ? $baseFieldMap[$editBaseName] : null;
$baseFieldDefaults = array_merge($fieldDefaults, ['order' => 0]);
$baseFieldForm = array_merge($baseFieldDefaults, $editingBaseField ?? []);
$flash = $_SESSION['admin_formulario_flash'] ?? null;
unset($_SESSION['admin_formulario_flash']);
$formType = (string) ($companyConfig['type'] ?? 'operational');

$activeTab = 'tab-dynamic';
$requestedTab = trim((string) ($_GET['tab'] ?? ''));
if ($requestedTab === 'base' || $editBaseName !== '') {
    $activeTab = 'tab-base';
} elseif ($requestedTab === 'visibility') {
    $activeTab = 'tab-visibility';
}

if (($_GET['ajax'] ?? '') === '1') {
    header('Content-Type: text/html; charset=UTF-8');

    if (($_GET['preview'] ?? '') === '1') {
        require_once __DIR__ . '/bitacora_view_helpers.php';
        echo '<div class="admin-preview-scope">';
        if ($formType === 'supervision') {
            bit_view_supervision_form($companyConfig, $empresaId);
        } else {
            bit_view_operational_form($companyConfig, $empresaId);
        }
        echo '</div>';
        exit;
    }

    if ($editBaseName !== '') {
        ?>
        <form method="post" data-form-role="base">
            <?php echo app_csrf_input(); ?>
            <input type="hidden" name="action" value="save_base_field">
            <input type="hidden" name="empresa_id" value="<?php echo app_h((string) $empresaId); ?>">
            <input type="hidden" name="name" value="<?php echo app_h((string) $baseFieldForm['name']); ?>">

            <h3 class="h6">Campo base: <code><?php echo app_h((string) $baseFieldForm['name']); ?></code></h3>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="base_name_display">Nombre técnico</label>
                    <input id="base_name_display" class="form-control" value="<?php echo app_h((string) $baseFieldForm['name']); ?>" readonly>
                </div>
                <div class="form-group col-md-6">
                    <label for="base_type_display">Tipo</label>
                    <input id="base_type_display" class="form-control" value="<?php echo app_h((string) $baseFieldForm['type']); ?>" readonly>
                </div>
            </div>

            <div class="form-group">
                <label for="base_label">Etiqueta visible</label>
                <input id="base_label" name="label" class="form-control" value="<?php echo app_h((string) $baseFieldForm['label']); ?>" required>
            </div>

            <?php if ((string) ($baseFieldForm['type'] ?? '') === 'subsection'): ?>
                <div class="form-group">
                    <label for="base_description">Texto descriptivo</label>
                    <textarea id="base_description" name="description" class="form-control" rows="4" maxlength="2000"><?php echo app_h((string) ($baseFieldForm['description'] ?? '')); ?></textarea>
                    <small class="form-text text-muted">Es opcional y aparecerá debajo del título en formulario, PDF y correo.</small>
                </div>
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group <?php echo (string) ($baseFieldForm['type'] ?? '') === 'subsection' ? 'col-md-12' : 'col-md-6'; ?>">
                    <label for="base_order">Orden</label>
                    <input id="base_order" name="order" type="number" class="form-control" value="<?php echo app_h((string) ($baseFieldForm['order'] ?? 0)); ?>">
                </div>
                <?php if ((string) ($baseFieldForm['type'] ?? '') !== 'subsection'): ?>
                    <div class="form-group col-md-6">
                        <label for="base_col">Ancho</label>
                        <select id="base_col" name="col" class="form-control">
                            <?php foreach (['col-md-3' => '25%', 'col-md-4' => '33%', 'col-md-6' => '50%', 'col-md-12' => '100%'] as $colValue => $colLabel): ?>
                                <option value="<?php echo app_h($colValue); ?>" <?php echo (string) ($baseFieldForm['col'] ?? 'col-md-6') === $colValue ? 'selected' : ''; ?>><?php echo app_h($colLabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ((string) ($baseFieldForm['type'] ?? '') !== 'subsection'): ?>
                <div class="form-group form-check">
                    <input id="base_required" name="required" type="checkbox" class="form-check-input" value="1" <?php echo !empty($baseFieldForm['required']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="base_required">Campo obligatorio</label>
                </div>
            <?php endif; ?>

            <?php if (!empty($companyConfig['sedes'])): ?>
                <div class="form-group">
                    <label for="base_sedes">Sedes donde aparece</label>
                    <select id="base_sedes" name="sedes[]" class="form-control admin-select2" multiple>
                        <?php foreach ((array) $companyConfig['sedes'] as $sede): ?>
                            <option value="<?php echo app_h((string) $sede); ?>" <?php echo in_array((string) $sede, (array) ($baseFieldForm['sedes'] ?? []), true) ? 'selected' : ''; ?>><?php echo app_h((string) $sede); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">Si no eliges ninguna, el campo aparece en todas las sedes de la empresa.</small>
                </div>
            <?php endif; ?>

            <?php if (in_array((string) ($baseFieldForm['type'] ?? ''), ['select', 'multiselect'], true)): ?>
                <div class="form-group">
                    <label for="base_options">Opciones de lista</label>
                    <textarea id="base_options" name="options" class="form-control" rows="4"><?php echo app_h(bit_admin_options_to_lines((array) ($baseFieldForm['options'] ?? []))); ?></textarea>
                    <small class="form-text text-muted">Usa una opción por línea. Si necesitas conservar valor y etiqueta, usa valor|etiqueta.</small>
                </div>
            <?php endif; ?>

            <?php if ((string) ($baseFieldForm['type'] ?? '') === 'number'): ?>
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label for="base_min">Mínimo</label>
                        <input id="base_min" name="min" class="form-control" value="<?php echo app_h((string) ($baseFieldForm['min'] ?? '')); ?>">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="base_max">Máximo</label>
                        <input id="base_max" name="max" class="form-control" value="<?php echo app_h((string) ($baseFieldForm['max'] ?? '')); ?>">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="base_step">Paso</label>
                        <input id="base_step" name="step" class="form-control" value="<?php echo app_h((string) ($baseFieldForm['step'] ?? 'any')); ?>">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="base_suffix">Sufijo</label>
                        <input id="base_suffix" name="suffix" class="form-control" value="<?php echo app_h((string) ($baseFieldForm['suffix'] ?? '')); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="base_number_format">Formato de presentación</label>
                        <select id="base_number_format" name="number_format" class="form-control">
                            <option value="plain" <?php echo (string) ($baseFieldForm['number_format'] ?? 'plain') === 'plain' ? 'selected' : ''; ?>>Número normal</option>
                            <option value="currency" <?php echo (string) ($baseFieldForm['number_format'] ?? 'plain') === 'currency' ? 'selected' : ''; ?>>Moneda colombiana ($)</option>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="base_number_decimals">Decimales</label>
                        <input id="base_number_decimals" name="number_decimals" type="number" min="0" max="6" class="form-control" value="<?php echo app_h((string) ($baseFieldForm['number_decimals'] ?? '0')); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="base_suffix_singular">Sufijo cuando el valor es 1</label>
                        <input id="base_suffix_singular" name="suffix_singular" maxlength="100" class="form-control" value="<?php echo app_h((string) ($baseFieldForm['suffix_singular'] ?? '')); ?>" placeholder="unidad">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="base_suffix_plural">Sufijo para otros valores</label>
                        <input id="base_suffix_plural" name="suffix_plural" maxlength="100" class="form-control" value="<?php echo app_h((string) ($baseFieldForm['suffix_plural'] ?? '')); ?>" placeholder="unidades">
                    </div>
                </div>
            <?php endif; ?>

            <?php if ((string) ($baseFieldForm['type'] ?? '') === 'yes_no'): ?>
                <div class="form-row">
                    <div class="form-group col-md-8">
                        <label for="base_detail_label">Etiqueta del detalle</label>
                        <input id="base_detail_label" name="detail_label" class="form-control" value="<?php echo app_h((string) ($baseFieldForm['detail_label'] ?? 'Detalle')); ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="base_detail_type">Tipo de detalle</label>
                        <select id="base_detail_type" name="detail_type" class="form-control">
                            <option value="textarea" <?php echo (string) ($baseFieldForm['detail_type'] ?? 'textarea') === 'textarea' ? 'selected' : ''; ?>>Texto largo</option>
                            <option value="number" <?php echo (string) ($baseFieldForm['detail_type'] ?? 'textarea') === 'number' ? 'selected' : ''; ?>>Número</option>
                            <option value="date" <?php echo (string) ($baseFieldForm['detail_type'] ?? 'textarea') === 'date' ? 'selected' : ''; ?>>Fecha</option>
                            <option value="multiselect" <?php echo (string) ($baseFieldForm['detail_type'] ?? 'textarea') === 'multiselect' ? 'selected' : ''; ?>>Lista múltiple</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" data-detail-type-panel="multiselect">
                    <label for="base_detail_options">Opciones del detalle multiselect</label>
                    <textarea id="base_detail_options" name="detail_options" class="form-control" rows="4" placeholder="Una opción por línea"><?php echo app_h(implode("\n", (array) ($baseFieldForm['detail_options'] ?? []))); ?></textarea>
                </div>
            <?php endif; ?>

            <?php if ((string) ($baseFieldForm['type'] ?? '') === 'yes_no_quantity_group'): ?>
                <div class="form-group">
                    <label for="base_no_report_value">Texto de referencia para respuestas No predeterminadas</label>
                    <input id="base_no_report_value" name="no_report_value" class="form-control" maxlength="500" value="<?php echo app_h((string) ($baseFieldForm['no_report_value'] ?? 'Sin novedad')); ?>">
                </div>
            <?php endif; ?>

            <?php if ((string) ($baseFieldForm['type'] ?? '') === 'quantity_group'): ?>
                <div class="form-group">
                    <label for="base_zero_report_value">Texto de referencia cuando la cantidad es 0</label>
                    <input id="base_zero_report_value" name="zero_report_value" class="form-control" maxlength="500" value="<?php echo app_h((string) ($baseFieldForm['zero_report_value'] ?? 'Sin registros')); ?>">
                </div>
            <?php endif; ?>

            <?php if (in_array((string) ($baseFieldForm['type'] ?? ''), ['yes_no_quantity_group', 'quantity_group'], true)): ?>
                <div class="form-group">
                    <label for="base_quantity_suffix">Sufijo de la cantidad en PDF/correo</label>
                    <input id="base_quantity_suffix" name="quantity_suffix" class="form-control" maxlength="100" value="<?php echo app_h((string) ($baseFieldForm['suffix'] ?? '')); ?>" placeholder="unidades">
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="base_quantity_suffix_singular">Sufijo cuando la cantidad es 1</label>
                        <input id="base_quantity_suffix_singular" name="quantity_suffix_singular" maxlength="100" class="form-control" value="<?php echo app_h((string) ($baseFieldForm['suffix_singular'] ?? '')); ?>" placeholder="unidad">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="base_quantity_suffix_plural">Sufijo para otras cantidades</label>
                        <input id="base_quantity_suffix_plural" name="quantity_suffix_plural" maxlength="100" class="form-control" value="<?php echo app_h((string) ($baseFieldForm['suffix_plural'] ?? '')); ?>" placeholder="unidades">
                    </div>
                </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-danger btn-block" data-default-text="Guardar campo base">Guardar campo base</button>
        </form>
        <?php
        exit;
    }

    // Formulario de campo dinámico (nuevo o edición)
    ?>
    <form method="post" data-form-role="dynamic">
        <?php echo app_csrf_input(); ?>
        <input type="hidden" name="action" value="save_field">
        <input type="hidden" name="empresa_id" value="<?php echo app_h((string) $empresaId); ?>">
        <input type="hidden" name="original_name" value="<?php echo app_h((string) ($editingField['name'] ?? '')); ?>">

        <div class="form-group">
            <label for="type">Tipo</label>
            <select id="type" name="type" class="form-control" required>
                <?php foreach (['text' => 'Texto corto', 'textarea' => 'Texto largo', 'number' => 'Número', 'date' => 'Fecha', 'time' => 'Hora', 'select' => 'Lista', 'multiselect' => 'Lista múltiple', 'yes_no' => 'Sí / No con detalle', 'yes_no_quantity_group' => 'Sí / No con cantidad y registros', 'quantity_group' => 'Cantidad y registros', 'multiselect_detail_group' => 'Lista con detalle por persona', 'subsection' => 'Etiqueta de subsección'] as $typeValue => $typeLabel): ?>
                    <option value="<?php echo app_h($typeValue); ?>" <?php echo (string) $fieldForm['type'] === $typeValue ? 'selected' : ''; ?>><?php echo app_h($typeLabel); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="name">Identificador técnico</label>
            <input id="name" name="name" class="form-control" value="<?php echo app_h((string) $fieldForm['name']); ?>" placeholder="ejemplo_campo" required>
            <small class="form-text text-muted">Solo letras, números y guion bajo. En una subsección se usa únicamente para administrarla.</small>
        </div>

        <div class="form-group">
            <label for="label">Etiqueta visible</label>
            <input id="label" name="label" class="form-control" value="<?php echo app_h((string) $fieldForm['label']); ?>" required>
        </div>

        <div class="form-group">
            <label for="section">Sección</label>
            <input id="section" name="section" class="form-control" list="section_options" value="<?php echo app_h((string) $fieldForm['section']); ?>">
            <datalist id="section_options">
                <?php foreach ($sectionNames as $sectionName): ?>
                    <option value="<?php echo app_h($sectionName); ?>"></option>
                <?php endforeach; ?>
            </datalist>
        </div>

        <div class="form-row">
            <div class="form-group col-md-6">
                <label for="order">Orden</label>
                <input id="order" name="order" type="number" class="form-control" value="<?php echo app_h((string) $fieldForm['order']); ?>">
            </div>
            <div class="form-group col-md-6" data-input-field-only>
                <label for="col">Ancho</label>
                <select id="col" name="col" class="form-control">
                    <?php foreach (['col-md-3' => '25%', 'col-md-4' => '33%', 'col-md-6' => '50%', 'col-md-12' => '100%'] as $colValue => $colLabel): ?>
                        <option value="<?php echo app_h($colValue); ?>" <?php echo (string) $fieldForm['col'] === $colValue ? 'selected' : ''; ?>><?php echo app_h($colLabel); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group form-check" data-input-field-only>
            <input id="required" name="required" type="checkbox" class="form-check-input" value="1" <?php echo !empty($fieldForm['required']) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="required">Campo obligatorio</label>
        </div>

        <?php if (!empty($companyConfig['sedes'])): ?>
            <div class="form-group">
                <label for="sedes">Sedes donde aparece</label>
                <select id="sedes" name="sedes[]" class="form-control admin-select2" multiple>
                    <?php foreach ((array) $companyConfig['sedes'] as $sede): ?>
                        <option value="<?php echo app_h((string) $sede); ?>" <?php echo in_array((string) $sede, (array) ($fieldForm['sedes'] ?? []), true) ? 'selected' : ''; ?>><?php echo app_h((string) $sede); ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="form-text text-muted">Si no eliges ninguna, el campo aparece en todas las sedes de la empresa.</small>
            </div>
        <?php endif; ?>

        <div class="form-group" data-type-panel="subsection">
            <label for="description">Texto descriptivo</label>
            <textarea id="description" name="description" class="form-control" rows="4" maxlength="2000" placeholder="Contexto o instrucciones para los campos de esta subsección"><?php echo app_h((string) ($fieldForm['description'] ?? '')); ?></textarea>
            <small class="form-text text-muted">Es opcional y aparecerá debajo del título en formulario, PDF y correo.</small>
        </div>

        <div class="form-group" data-type-panel="select multiselect">
            <label for="options">Opciones de lista</label>
            <textarea id="options" name="options" class="form-control" rows="4" placeholder="Una opción por línea"><?php echo app_h(implode("\n", (array) ($fieldForm['options'] ?? []))); ?></textarea>
        </div>

        <div data-type-panel="number">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="min">Mínimo</label>
                    <input id="min" name="min" class="form-control" value="<?php echo app_h((string) ($fieldForm['min'] ?? '')); ?>">
                </div>
                <div class="form-group col-md-4">
                    <label for="max">Máximo</label>
                    <input id="max" name="max" class="form-control" value="<?php echo app_h((string) ($fieldForm['max'] ?? '')); ?>">
                </div>
                <div class="form-group col-md-4">
                    <label for="step">Paso</label>
                    <input id="step" name="step" class="form-control" value="<?php echo app_h((string) ($fieldForm['step'] ?? 'any')); ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="suffix">Sufijo en PDF/correo</label>
                    <input id="suffix" name="suffix" class="form-control" value="<?php echo app_h((string) ($fieldForm['suffix'] ?? '')); ?>" placeholder="%, bolsas, unidades">
                </div>
                <div class="form-group col-md-3">
                    <label for="number_format">Formato</label>
                    <select id="number_format" name="number_format" class="form-control">
                        <option value="plain" <?php echo (string) ($fieldForm['number_format'] ?? 'plain') === 'plain' ? 'selected' : ''; ?>>Número normal</option>
                        <option value="currency" <?php echo (string) ($fieldForm['number_format'] ?? 'plain') === 'currency' ? 'selected' : ''; ?>>Moneda ($)</option>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label for="number_decimals">Decimales</label>
                    <input id="number_decimals" name="number_decimals" type="number" min="0" max="6" class="form-control" value="<?php echo app_h((string) ($fieldForm['number_decimals'] ?? '0')); ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="suffix_singular">Sufijo cuando el valor es 1</label>
                    <input id="suffix_singular" name="suffix_singular" maxlength="100" class="form-control" value="<?php echo app_h((string) ($fieldForm['suffix_singular'] ?? '')); ?>" placeholder="unidad">
                </div>
                <div class="form-group col-md-6">
                    <label for="suffix_plural">Sufijo para otros valores</label>
                    <input id="suffix_plural" name="suffix_plural" maxlength="100" class="form-control" value="<?php echo app_h((string) ($fieldForm['suffix_plural'] ?? '')); ?>" placeholder="unidades">
                </div>
            </div>
        </div>

        <div data-type-panel="yes_no">
            <div class="form-group">
                <label for="detail_name">Nombre técnico del detalle</label>
                <input id="detail_name" name="detail_name" class="form-control" value="<?php echo app_h((string) ($fieldForm['detail_name'] ?? '')); ?>">
            </div>
            <div class="form-group">
                <label for="detail_label">Etiqueta del detalle</label>
                <input id="detail_label" name="detail_label" class="form-control" value="<?php echo app_h((string) ($fieldForm['detail_label'] ?? 'Detalle')); ?>">
            </div>
            <div class="form-group">
                <label for="detail_type">Tipo de detalle</label>
                <select id="detail_type" name="detail_type" class="form-control">
                    <option value="textarea" <?php echo (string) ($fieldForm['detail_type'] ?? 'textarea') === 'textarea' ? 'selected' : ''; ?>>Texto largo</option>
                    <option value="number" <?php echo (string) ($fieldForm['detail_type'] ?? 'textarea') === 'number' ? 'selected' : ''; ?>>Número</option>
                    <option value="date" <?php echo (string) ($fieldForm['detail_type'] ?? 'textarea') === 'date' ? 'selected' : ''; ?>>Fecha</option>
                    <option value="multiselect" <?php echo (string) ($fieldForm['detail_type'] ?? 'textarea') === 'multiselect' ? 'selected' : ''; ?>>Lista múltiple</option>
                </select>
            </div>
            <div class="form-group" data-detail-type-panel="multiselect">
                <label for="detail_options">Opciones del detalle multiselect</label>
                <textarea id="detail_options" name="detail_options" class="form-control" rows="4" placeholder="Una opción por línea"><?php echo app_h(implode("\n", (array) ($fieldForm['detail_options'] ?? []))); ?></textarea>
            </div>
        </div>

        <div data-type-panel="multiselect_detail_group">
            <div class="form-group">
                <label for="ms_options">Opciones de la lista</label>
                <textarea id="ms_options" name="ms_options" class="form-control" rows="4" placeholder="Una opción por línea (ej. Proveedor, Visita SST)"><?php echo app_h(implode("\n", (array) ($fieldForm['options'] ?? []))); ?></textarea>
            </div>
            <div class="form-group">
                <label for="ms_detail_name">Nombre técnico del detalle</label>
                <input id="ms_detail_name" name="ms_detail_name" class="form-control" value="<?php echo app_h((string) ($fieldForm['detail_name'] ?? '')); ?>">
            </div>
            <div class="form-group">
                <label for="ms_no_apply_value">Texto para "No aplica visita"</label>
                <input id="ms_no_apply_value" name="ms_no_apply_value" class="form-control" value="<?php echo app_h((string) ($fieldForm['no_apply_value'] ?? 'No aplica visita')); ?>">
            </div>
            <div class="form-group">
                <label for="ms_placeholder">Texto guía del campo</label>
                <input id="ms_placeholder" name="ms_placeholder" class="form-control" value="<?php echo app_h((string) ($fieldForm['placeholder'] ?? 'Escribe Nombre Apellido - Cargo')); ?>">
            </div>
            <div class="form-group">
                <label for="ms_help">Ayuda visible</label>
                <textarea id="ms_help" name="ms_help" class="form-control" rows="2"><?php echo app_h((string) ($fieldForm['help'] ?? '')); ?></textarea>
            </div>
        </div>

        <div data-type-panel="yes_no_quantity_group quantity_group">
            <div class="form-group" data-quantity-mode="yes_no_quantity_group">
                <label for="no_report_value">Texto de referencia para respuestas No predeterminadas</label>
                <input id="no_report_value" name="no_report_value" class="form-control" maxlength="500" value="<?php echo app_h((string) ($fieldForm['no_report_value'] ?? 'Sin novedad')); ?>" placeholder="Sin novedad">
            </div>
            <div class="form-group" data-quantity-mode="quantity_group">
                <label for="zero_report_value">Texto de referencia cuando la cantidad es 0</label>
                <input id="zero_report_value" name="zero_report_value" class="form-control" maxlength="500" value="<?php echo app_h((string) ($fieldForm['zero_report_value'] ?? 'Sin registros')); ?>" placeholder="Sin registros">
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="quantity_name">Nombre técnico de la cantidad</label>
                    <input id="quantity_name" name="quantity_name" class="form-control" value="<?php echo app_h((string) ($fieldForm['quantity_name'] ?? '')); ?>">
                </div>
                <div class="form-group col-md-6">
                    <label for="quantity_label">Etiqueta de la cantidad</label>
                    <input id="quantity_label" name="quantity_label" class="form-control" value="<?php echo app_h((string) ($fieldForm['quantity_label'] ?? 'Cantidad')); ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-3" data-quantity-mode="yes_no_quantity_group">
                    <label for="quantity_min">Cantidad mínima</label>
                    <input id="quantity_min" name="quantity_min" type="number" class="form-control" min="1" max="10" value="<?php echo app_h((string) ((string) ($fieldForm['min'] ?? '') === '' ? '1' : $fieldForm['min'])); ?>">
                </div>
                <div class="form-group col-md-3" data-quantity-mode="quantity_group">
                    <label>Cantidad mínima</label>
                    <input type="number" class="form-control" value="0" disabled>
                </div>
                <div class="form-group col-md-3">
                    <label for="quantity_max">Cantidad máxima</label>
                    <input id="quantity_max" name="quantity_max" type="number" class="form-control" min="1" max="10" value="<?php echo app_h((string) ((string) ($fieldForm['max'] ?? '') === '' ? '10' : $fieldForm['max'])); ?>">
                </div>
                <div class="form-group col-md-6">
                    <label for="item_label">Etiqueta de cada registro</label>
                    <input id="item_label" name="item_label" class="form-control" value="<?php echo app_h((string) ($fieldForm['item_label'] ?? 'Registro')); ?>">
                </div>
            </div>
            <div class="form-group">
                <label for="quantity_suffix">Sufijo de la cantidad en PDF/correo</label>
                <input id="quantity_suffix" name="quantity_suffix" maxlength="100" class="form-control" value="<?php echo app_h((string) ($fieldForm['suffix'] ?? '')); ?>" placeholder="unidades">
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="quantity_suffix_singular">Sufijo cuando la cantidad es 1</label>
                    <input id="quantity_suffix_singular" name="quantity_suffix_singular" maxlength="100" class="form-control" value="<?php echo app_h((string) ($fieldForm['suffix_singular'] ?? '')); ?>" placeholder="unidad">
                </div>
                <div class="form-group col-md-6">
                    <label for="quantity_suffix_plural">Sufijo para otras cantidades</label>
                    <input id="quantity_suffix_plural" name="quantity_suffix_plural" maxlength="100" class="form-control" value="<?php echo app_h((string) ($fieldForm['suffix_plural'] ?? '')); ?>" placeholder="unidades">
                </div>
            </div>
            <div class="admin-group-fields-editor">
                <label>Sub-campos de cada registro</label>
                <div id="adminGroupFieldsList">
                    <?php if (in_array($fieldForm['type'], ['yes_no_quantity_group', 'quantity_group'], true) && !empty($fieldForm['fields'])): ?>
                        <?php foreach ((array) $fieldForm['fields'] as $itemIndex => $itemField): ?>
                            <?php $itemRowKey = 'gf_' . ($itemIndex + 1); ?>
                            <div class="admin-group-field-row border rounded p-2 mb-2">
                                <div class="form-row">
                                    <div class="form-group col-md-3 mb-2">
                                        <label>Nombre técnico</label>
                                        <input type="text" class="form-control" name="group_fields[name][]" value="<?php echo app_h((string) ($itemField['name'] ?? '')); ?>" placeholder="ej. nombre">
                                    </div>
                                    <div class="form-group col-md-4 mb-2">
                                        <label>Etiqueta</label>
                                        <input type="text" class="form-control" name="group_fields[label][]" value="<?php echo app_h((string) ($itemField['label'] ?? '')); ?>" placeholder="Nombre del visitante">
                                    </div>
                                    <div class="form-group col-md-3 mb-2">
                                        <label>Tipo</label>
                                        <select class="form-control admin-group-field-type" name="group_fields[type][]">
                                            <?php foreach (['text' => 'Texto corto', 'textarea' => 'Texto largo', 'number' => 'Número', 'select' => 'Lista', 'date' => 'Fecha', 'time' => 'Hora', 'simple_radio' => 'Radio Sí / No'] as $itemTypeValue => $itemTypeLabel): ?>
                                                <option value="<?php echo app_h($itemTypeValue); ?>" <?php echo (string) ($itemField['type'] ?? 'text') === $itemTypeValue ? 'selected' : ''; ?>><?php echo app_h($itemTypeLabel); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-2 mb-2 d-flex align-items-center">
                                        <label class="form-check-label mr-2 mb-0">
                                            <input type="checkbox" class="form-check-input" name="group_fields[required][]" value="<?php echo app_h($itemRowKey); ?>" <?php echo !empty($itemField['required']) ? 'checked' : ''; ?>>
                                            Obligatorio
                                        </label>
                                        <button type="button" class="admin-btn-sm admin-btn-danger-ghost admin-group-field-remove" title="Quitar sub-campo">Quitar</button>
                                    </div>
                                </div>
                                <input type="hidden" name="group_fields[row_key][]" value="<?php echo app_h($itemRowKey); ?>">
                                <div class="form-group mb-0 admin-group-field-options-wrap" data-options-for="select" <?php echo (string) ($itemField['type'] ?? 'text') === 'select' ? '' : 'hidden'; ?>>
                                    <label>Opciones de la lista (una por línea)</label>
                                    <textarea class="form-control" name="group_fields[options][]" rows="2"><?php echo app_h(implode("\n", (array) ($itemField['options'] ?? []))); ?></textarea>
                                </div>
                                <div class="form-row admin-group-field-number-options-wrap" <?php echo (string) ($itemField['type'] ?? 'text') === 'number' ? '' : 'hidden'; ?>>
                                    <div class="form-group col-md-6 mb-0">
                                        <label>Formato de presentación</label>
                                        <select class="form-control" name="group_fields[number_format][]">
                                            <option value="plain" <?php echo (string) ($itemField['number_format'] ?? 'plain') === 'plain' ? 'selected' : ''; ?>>Número normal</option>
                                            <option value="currency" <?php echo (string) ($itemField['number_format'] ?? 'plain') === 'currency' ? 'selected' : ''; ?>>Moneda ($)</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-3 mb-0">
                                        <label>Decimales</label>
                                        <input type="number" min="0" max="6" class="form-control" name="group_fields[number_decimals][]" value="<?php echo app_h((string) ($itemField['number_decimals'] ?? '0')); ?>">
                                    </div>
                                    <div class="form-group col-md-3 mb-0">
                                        <label>Sufijo</label>
                                        <input type="text" class="form-control" name="group_fields[suffix][]" value="<?php echo app_h((string) ($itemField['suffix'] ?? '')); ?>" placeholder="unidades">
                                    </div>
                                </div>
                                <div class="form-row admin-group-field-number-suffix-options-wrap" <?php echo (string) ($itemField['type'] ?? 'text') === 'number' ? '' : 'hidden'; ?>>
                                    <div class="form-group col-md-6 mb-0">
                                        <label>Sufijo cuando el valor es 1</label>
                                        <input type="text" class="form-control" name="group_fields[suffix_singular][]" value="<?php echo app_h((string) ($itemField['suffix_singular'] ?? '')); ?>" placeholder="unidad">
                                    </div>
                                    <div class="form-group col-md-6 mb-0">
                                        <label>Sufijo para otros valores</label>
                                        <input type="text" class="form-control" name="group_fields[suffix_plural][]" value="<?php echo app_h((string) ($itemField['suffix_plural'] ?? '')); ?>" placeholder="unidades">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button type="button" class="admin-btn-sm" id="adminAddGroupField">+ Agregar sub-campo</button>
                <small class="form-text text-muted">Cada sub-campo se repite por cada registro visible según la cantidad ingresada.</small>
            </div>
        </div>

        <button type="submit" class="btn btn-danger btn-block" data-default-text="<?php echo $editingField === null ? 'Agregar campo' : 'Guardar cambios'; ?>"><?php echo $editingField === null ? 'Agregar campo' : 'Guardar cambios'; ?></button>
    </form>
    <?php
    exit;
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Administrar formulario de bitácora</title>
    <link rel="stylesheet" href="../resources/css/bootstrap.min.css">
    <link rel="stylesheet" href="../resources/sweetalert/sweetalert2.min.css">
    <link rel="stylesheet" href="../resources/select2/select2.min.css">
    <link rel="stylesheet" href="../resources/css/bitacora.css">
    <link rel="stylesheet" href="../resources/css/admin_formulario.css">
    <link rel="stylesheet" href="../resources/css/session_timeout.css">
    <link rel="shortcut icon" href="../resources/img/LOGO ALITAS-09.png">
</head>
<body class="admin-page"
      <?php echo app_session_client_attributes(); ?>
      data-admin-bitacora-id="<?php echo app_h((string) $empresaId); ?>"
      data-admin-url="admin_formulario.php"
      data-admin-csrf="<?php echo app_h(app_csrf_token()); ?>">
<main class="admin-shell">
    <header class="admin-topbar">
        <div class="admin-brand">
            <img class="admin-brand-logo" src="../resources/img/LOGO ALITAS-09.png" alt="Logo">
            <div>
                <p class="admin-eyebrow">Panel de administración</p>
                <h1 class="admin-title">Administrar formulario</h1>
            </div>
        </div>
        <div class="admin-session">
            <span class="admin-user-pill">Usuario: <strong><?php echo app_h($_SESSION['s_nombre'] ?? ''); ?></strong> · Admin</span>
            <a class="admin-btn-ghost" href="bitacora.php?empresa=<?php echo app_h((string) $empresaId); ?>">Ver bitácora</a>
            <button type="button" class="admin-btn-ghost" id="adminPreviewBtn">Vista previa</button>
            <a class="admin-btn-ghost" href="admin_destinatarios.php?empresa=<?php echo app_h((string) $empresaId); ?>">Parametrizar correos</a>
            <?php echo app_logout_form('admin-btn-danger', 'Cerrar sesión'); ?>
        </div>
    </header>

    <?php if (is_array($flash) && isset($flash['message'], $flash['type'])): ?>
        <div class="alert alert-<?php echo app_h((string) $flash['type']); ?> admin-flash"><?php echo app_h((string) $flash['message']); ?></div>
    <?php endif; ?>

    <div class="admin-card mb-3">
        <div class="admin-card-body">
            <form method="get" class="form-row align-items-end">
                <div class="form-group col-md-6 mb-md-0">
                    <label for="empresa">Empresa</label>
                    <select id="empresa" name="empresa" class="form-control" data-auto-submit="1">
                        <?php foreach ($empresaOptions as $optionId => $optionLabel): ?>
                            <option value="<?php echo app_h((string) $optionId); ?>" <?php echo (int) $optionId === $empresaId ? 'selected' : ''; ?>><?php echo app_h($optionLabel); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-6 mb-0">
                    <p class="admin-muted mb-0">Empresa activa: <strong><?php echo app_h(app_bitacora_empresa_label($empresaId)); ?></strong></p>
                </div>
            </form>
        </div>
    </div>

    <?php if ($companyConfig === null): ?>
        <div class="alert alert-warning">La empresa seleccionada no tiene configuración de bitácora.</div>
    <?php else: ?>
        <nav class="admin-tabs" role="tablist" aria-label="Secciones de administración">
            <button type="button" class="admin-tab <?php echo $activeTab === 'tab-dynamic' ? 'is-active' : ''; ?>" data-tab-panel="tab-dynamic">Campos dinámicos</button>
            <button type="button" class="admin-tab <?php echo $activeTab === 'tab-base' ? 'is-active' : ''; ?>" data-tab-panel="tab-base">Campos base</button>
            <button type="button" class="admin-tab <?php echo $activeTab === 'tab-visibility' ? 'is-active' : ''; ?>" data-tab-panel="tab-visibility">Visibilidad</button>
        </nav>

        <section class="admin-panel <?php echo $activeTab === 'tab-dynamic' ? 'is-active' : ''; ?>" id="tab-dynamic">
            <div class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <h2 class="h5 mb-0">Campos y subsecciones configurables</h2>
                        <p class="admin-muted">Elementos adicionales configurados para esta empresa.</p>
                    </div>
                    <button type="button" class="admin-btn-primary" id="adminAddField">+ Agregar elemento</button>
                </div>
                <div class="admin-card-body">
                    <div class="admin-search">
                        <input type="search" id="adminFieldSearch" class="form-control" placeholder="Buscar campo por nombre, etiqueta, tipo o sección...">
                    </div>
                    <?php if ($dynamicFields === []): ?>
                        <p class="admin-muted mb-0">No hay campos ni subsecciones configurados para esta empresa.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover admin-sortable" id="adminDynamicTable" data-scope="dynamic">
                                <thead>
                                <tr>
                                    <th class="admin-col-reorder" aria-label="Orden"></th>
                                    <th>Nombre</th>
                                    <th>Etiqueta</th>
                                    <th>Tipo</th>
                                    <th>Sección</th>
                                    <th>Acciones</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($dynamicFields as $field): ?>
                                    <?php $fieldName = (string) ($field['name'] ?? ''); ?>
                                    <tr data-field-name="<?php echo app_h($fieldName); ?>">
                                        <td class="admin-col-reorder">
                                            <button type="button" class="admin-btn-icon" data-reorder="up" title="Subir" aria-label="Subir">&#8593;</button>
                                            <button type="button" class="admin-btn-icon" data-reorder="down" title="Bajar" aria-label="Bajar">&#8595;</button>
                                        </td>
                                        <td><code><?php echo app_h($fieldName); ?></code></td>
                                        <td><?php echo app_h((string) ($field['label'] ?? $fieldName)); ?><?php echo !empty($field['required']) ? ' *' : ''; ?></td>
                                        <td><?php echo app_h((string) ($field['type'] ?? 'text')); ?></td>
                                        <td><?php echo app_h((string) ($field['section_title'] ?? $field['section'] ?? 'Campos adicionales')); ?></td>
                                        <td>
                                            <div class="d-flex admin-actions-sm">
                                                <button type="button" class="admin-btn-sm" data-edit-field="<?php echo app_h($fieldName); ?>">Editar</button>
                                                <button type="button" class="admin-btn-sm" data-dup-field="<?php echo app_h($fieldName); ?>">Duplicar</button>
                                                 <form method="post" data-confirm="¿Eliminar este elemento configurable?">
                                                    <?php echo app_csrf_input(); ?>
                                                    <input type="hidden" name="action" value="delete_field">
                                                    <input type="hidden" name="empresa_id" value="<?php echo app_h((string) $empresaId); ?>">
                                                    <input type="hidden" name="name" value="<?php echo app_h($fieldName); ?>">
                                                    <button type="submit" class="admin-btn-sm admin-btn-danger-ghost">Eliminar</button>
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
            </div>
        </section>

        <section class="admin-panel <?php echo $activeTab === 'tab-base' ? 'is-active' : ''; ?>" id="tab-base">
            <div class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <h2 class="h5 mb-0">Campos base editables</h2>
                        <p class="admin-muted">Ajusta campos ya existentes sin cambiar su nombre técnico. Para listas puedes usar una opción por línea o el formato valor|etiqueta.</p>
                    </div>
                </div>
                <div class="admin-card-body">
                    <?php if ($baseFields === []): ?>
                        <p class="admin-muted mb-0">No hay campos base editables para esta empresa.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover admin-sortable" id="adminBaseTable" data-scope="base">
                                <thead>
                                <tr>
                                    <th class="admin-col-reorder" aria-label="Orden"></th>
                                    <th>Nombre</th>
                                    <th>Etiqueta</th>
                                    <th>Tipo</th>
                                    <th>Sección</th>
                                    <th>Sedes</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($baseFields as $field): ?>
                                    <?php
                                    $fieldName = (string) ($field['name'] ?? '');
                                    $isOverridden = isset($baseOverrides[$fieldName]);
                                    $isHidden = in_array($fieldName, $hiddenFields, true);
                                    $fieldSedes = array_values(array_filter((array) ($field['sedes'] ?? []), static fn($sede) => trim((string) $sede) !== ''));
                                    ?>
                                    <tr data-field-name="<?php echo app_h($fieldName); ?>">
                                        <td class="admin-col-reorder">
                                            <button type="button" class="admin-btn-icon" data-reorder="up" title="Subir" aria-label="Subir">&#8593;</button>
                                            <button type="button" class="admin-btn-icon" data-reorder="down" title="Bajar" aria-label="Bajar">&#8595;</button>
                                        </td>
                                        <td><code><?php echo app_h($fieldName); ?></code></td>
                                        <td><?php echo app_h((string) ($field['label'] ?? $fieldName)); ?><?php echo !empty($field['required']) ? ' *' : ''; ?></td>
                                        <td><?php echo app_h((string) ($field['type'] ?? 'text')); ?></td>
                                        <td><?php echo app_h((string) ($field['section'] ?? 'Campos')); ?></td>
                                        <td><?php echo $fieldSedes === [] ? 'Todas' : app_h(implode(', ', $fieldSedes)); ?></td>
                                        <td>
                                            <?php if ($isOverridden): ?><span class="field-chip">Personalizado</span><?php endif; ?>
                                            <?php if ($isHidden): ?><span class="field-chip">Oculto</span><?php endif; ?>
                                            <?php if (!$isOverridden && !$isHidden): ?><span class="field-chip">Base</span><?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex admin-actions-sm">
                                                <button type="button" class="admin-btn-sm" data-edit-base="<?php echo app_h($fieldName); ?>">Editar</button>
                                                <?php if ($isOverridden): ?>
                                                    <form method="post" data-confirm="¿Restaurar este campo base a su configuración original?">
                                                        <?php echo app_csrf_input(); ?>
                                                        <input type="hidden" name="action" value="reset_base_field">
                                                        <input type="hidden" name="empresa_id" value="<?php echo app_h((string) $empresaId); ?>">
                                                        <input type="hidden" name="name" value="<?php echo app_h($fieldName); ?>">
                                                        <button type="submit" class="admin-btn-sm admin-btn-danger-ghost">Restaurar</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="admin-panel <?php echo $activeTab === 'tab-visibility' ? 'is-active' : ''; ?>" id="tab-visibility">
            <div class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <h2 class="h5 mb-0">Campos base visibles</h2>
                        <p class="admin-muted">Marca los campos existentes que quieras ocultar del formulario. Fecha, sede y responsables principales no se pueden ocultar.</p>
                    </div>
                </div>
                <div class="admin-card-body">
                    <form method="post" id="adminHiddenForm">
                        <?php echo app_csrf_input(); ?>
                        <input type="hidden" name="action" value="save_hidden">
                        <input type="hidden" name="empresa_id" value="<?php echo app_h((string) $empresaId); ?>">

                        <?php if ($hideableFields === []): ?>
                            <p class="admin-muted mb-0">No hay campos base disponibles para ocultar.</p>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($hideableFields as $field): ?>
                                    <?php $isHidden = in_array($field['name'], $hiddenFields, true); ?>
                                    <div class="col-md-6 col-lg-4 mb-2">
                                        <label class="border rounded p-2 d-block h-100 <?php echo $isHidden ? 'bg-light text-muted' : ''; ?>">
                                            <input type="checkbox" name="hidden_fields[]" value="<?php echo app_h($field['name']); ?>" <?php echo $isHidden ? 'checked' : ''; ?>>
                                            Ocultar <strong><?php echo app_h($field['label']); ?></strong>
                                            <span class="field-chip"><?php echo app_h($field['section']); ?></span>
                                            <span class="field-chip"><?php echo app_h($field['type']); ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="submit" class="admin-btn-primary mt-2" data-default-text="Guardar campos ocultos">Guardar campos ocultos</button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </section>
    <?php endif; ?>
</main>

<div class="admin-modal-overlay" id="adminModalOverlay" hidden>
    <div class="admin-modal" role="dialog" aria-modal="true" aria-labelledby="adminModalTitle" tabindex="-1">
        <div class="admin-modal-head">
            <h3 id="adminModalTitle">Formulario</h3>
            <button type="button" class="admin-modal-close" id="adminModalClose" aria-label="Cerrar">&times;</button>
        </div>
        <div class="admin-modal-body" id="adminModalBox"></div>
    </div>
</div>
<?php require __DIR__ . '/session_timeout.php'; ?>

<script src="../resources/jquery/jquery-3.6.0.min.js"></script>
<script src="../resources/sweetalert/sweetalert2.all.min.js"></script>
<script src="../resources/select2/select2.min.js"></script>
<script src="../resources/js/session_timeout.js"></script>
<script src="../resources/js/admin_formulario.js"></script>
</body>
</html>
