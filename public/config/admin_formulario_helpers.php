<?php
declare(strict_types=1);

require_once __DIR__ . '/bitacora.php';
require_once __DIR__ . '/../bd/conexion.php';

function bit_admin_redirect(int $empresaId, string $type, string $message): void
{
    $_SESSION['admin_formulario_flash'] = ['type' => $type, 'message' => $message];
    header('Location: admin_formulario.php?empresa=' . urlencode((string) $empresaId));
    exit;
}

function bit_admin_respond(int $empresaId, string $type, string $message, bool $json): void
{
    if ($json) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => $type === 'success', 'type' => $type, 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    bit_admin_redirect($empresaId, $type, $message);
}

function bit_admin_next_field_name(string $name, array $existingNames): string
{
    $existing = array_flip(array_map('strval', $existingNames));
    $candidate = $name . '_2';
    $i = 2;
    while (isset($existing[$candidate])) {
        $i++;
        $candidate = $name . '_' . $i;
    }

    return $candidate;
}

function bit_admin_audit_log(int $empresaId, string $action, ?string $target = null, array $details = []): void
{
    try {
        $encodedDetails = $details === [] ? null : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $pdo = Conexion::Conectar();
        $stmt = $pdo->prepare('
            INSERT INTO bitacora_admin_audit (idEmpresa, usuario, accion, objetivo, detalle_json, ip)
            VALUES (:idEmpresa, :usuario, :accion, :objetivo, :detalleJson, :ip)
        ');
        $stmt->execute([
            'idEmpresa' => $empresaId,
            'usuario' => mb_substr((string) ($_SESSION['s_usuario'] ?? ''), 0, 120, 'UTF-8'),
            'accion' => mb_substr($action, 0, 80, 'UTF-8'),
            'objetivo' => $target === null ? null : mb_substr($target, 0, 160, 'UTF-8'),
            'detalleJson' => $encodedDetails,
            'ip' => mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45, 'UTF-8'),
        ]);
    } catch (Throwable $e) {
        error_log('No fue posible registrar auditoria de administracion de formulario: ' . $e->getMessage());
    }
}

function bit_admin_config_json(int $empresaId, array $companyConfig): array
{
    $json = app_bitacora_db_config_json($empresaId);
    if ($json === []) {
        $json = [
            'schema' => (($companyConfig['type'] ?? 'operational') === 'supervision') ? 'supervision_current' : 'operational_current',
        ];
    }

    if (!isset($json['dynamic_fields']) || !is_array($json['dynamic_fields'])) {
        $json['dynamic_fields'] = [];
    }
    if (!isset($json['hidden_fields']) || !is_array($json['hidden_fields'])) {
        $json['hidden_fields'] = [];
    }
    if (!isset($json['field_overrides']) || !is_array($json['field_overrides'])) {
        $json['field_overrides'] = [];
    }

    return $json;
}

function bit_admin_save_config_json(int $empresaId, array $companyConfig, array $json): void
{
    $json['dynamic_fields'] = array_values(array_filter((array) ($json['dynamic_fields'] ?? []), 'is_array'));
    $json['hidden_fields'] = app_bitacora_normalize_hidden_fields($json);
    $json['field_overrides'] = app_bitacora_normalize_field_overrides($json, app_bitacora_base_form_sections($empresaId, $companyConfig));

    $encoded = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        throw new RuntimeException('No fue posible codificar la configuración JSON.');
    }

    $pdo = Conexion::Conectar();
    $stmt = $pdo->prepare('
        INSERT INTO bitacora_empresa_config (idEmpresa, tipo_formulario, config_json)
        VALUES (:idEmpresa, :tipoFormulario, :configJson)
        ON DUPLICATE KEY UPDATE
            tipo_formulario = VALUES(tipo_formulario),
            config_json = VALUES(config_json)
    ');
    $stmt->execute([
        'idEmpresa' => $empresaId,
        'tipoFormulario' => (string) ($companyConfig['type'] ?? 'operational'),
        'configJson' => $encoded,
    ]);
}

function bit_admin_parse_lines(string $value): array
{
    $items = preg_split('/\R+/', trim($value));
    if (!is_array($items)) {
        return [];
    }

    $items = array_map(static fn($item) => trim((string) $item), $items);
    return array_values(array_unique(array_filter($items, static fn($item) => $item !== '')));
}

function bit_admin_parse_option_lines(string $value): array
{
    $items = preg_split('/\R+/', trim($value));
    if (!is_array($items)) {
        return [];
    }

    $options = [];
    foreach ($items as $item) {
        $item = trim((string) $item);
        if ($item === '') {
            continue;
        }

        if (strpos($item, '|') !== false) {
            [$optionValue, $optionLabel] = array_map('trim', explode('|', $item, 2));
            if ($optionValue !== '' && $optionLabel !== '') {
                $options[$optionValue] = $optionLabel;
            }
            continue;
        }

        $options[] = $item;
    }

    return $options;
}

function bit_admin_field_identifiers(array $field): array
{
    $identifiers = [];
    foreach (['name', 'detail_name', 'quantity_name', 'id', 'group_id'] as $key) {
        $value = trim((string) ($field[$key] ?? ''));
        if ($value !== '') {
            $identifiers[$value] = true;
        }
    }

    $type = (string) ($field['type'] ?? '');
    if ($type === 'plant') {
        foreach (['mant5', 'mant6', 'mant7', 'plantaGroup'] as $identifier) {
            $identifiers[$identifier] = true;
        }
    }

    if ($type === 'yes_no_detail_group') {
        $groupName = trim((string) ($field['name'] ?? ''));
        foreach ((array) ($field['fields'] ?? []) as $detailField) {
            $detailName = trim((string) ($detailField['name'] ?? ''));
            if ($groupName !== '' && $detailName !== '') {
                $identifiers[app_bitacora_detail_group_field_name($groupName, $detailName)] = true;
            }
        }
    }

    if ($type === 'yes_no_branch_group') {
        $groupName = trim((string) ($field['name'] ?? ''));
        foreach (['si' => 'si_fields', 'no' => 'no_fields'] as $branch => $branchKey) {
            foreach ((array) ($field[$branchKey] ?? []) as $branchField) {
                $branchFieldName = trim((string) ($branchField['name'] ?? ''));
                if ($groupName !== '' && $branchFieldName !== '') {
                    $identifiers[app_bitacora_branch_group_field_name($groupName, $branch, $branchFieldName)] = true;
                }
            }
        }
    }

    if (in_array($type, ['yes_no_quantity_group', 'quantity_group'], true)) {
        $groupName = trim((string) ($field['name'] ?? ''));
        $max = max(1, min(10, (int) ($field['max'] ?? 10)));
        foreach ((array) ($field['fields'] ?? []) as $itemField) {
            $itemName = trim((string) ($itemField['name'] ?? ''));
            if ($groupName === '' || $itemName === '') {
                continue;
            }
            foreach (range(1, $max) as $index) {
                $identifiers[app_bitacora_group_item_field_name($groupName, $index, $itemName)] = true;
            }
        }
    }

    return array_keys($identifiers);
}

function bit_admin_options_to_lines(array $options): string
{
    $lines = [];
    foreach ($options as $value => $label) {
        if (is_int($value)) {
            $lines[] = (string) $label;
            continue;
        }

        $lines[] = (string) $value . '|' . (string) $label;
    }

    return implode("\n", $lines);
}

function bit_admin_dynamic_fields(array $json): array
{
    $fields = [];
    foreach ((array) ($json['dynamic_fields'] ?? []) as $field) {
        if (!is_array($field)) {
            continue;
        }
        $normalized = app_bitacora_normalize_dynamic_field($field);
        if ($normalized !== null) {
            $fields[] = $normalized;
        }
    }

    return $fields;
}

function bit_admin_base_sections(int $empresaId, array $companyConfig): array
{
    return app_bitacora_base_form_sections($empresaId, $companyConfig);
}

function bit_admin_base_field_names(int $empresaId, array $companyConfig): array
{
    return array_values(array_map(
        static fn(array $field): string => (string) $field['name'],
        bit_admin_configurable_base_fields($empresaId, $companyConfig)
    ));
}

function bit_admin_base_field_identifiers(int $empresaId, array $companyConfig): array
{
    $identifiers = [];
    foreach (bit_admin_base_sections($empresaId, $companyConfig) as $section) {
        foreach ((array) ($section['fields'] ?? []) as $field) {
            foreach (bit_admin_field_identifiers((array) $field) as $identifier) {
                $identifiers[$identifier] = true;
            }
        }
    }

    return array_keys($identifiers);
}

function bit_admin_configurable_base_fields(int $empresaId, array $companyConfig, array $json = []): array
{
    $protected = array_flip(app_bitacora_protected_field_names());
    $fields = [];
    $sections = bit_admin_base_sections($empresaId, $companyConfig);
    if ($json !== []) {
        $sections = app_bitacora_apply_field_overrides($sections, $json);
    }

    foreach ($sections as $section) {
        foreach ((array) ($section['fields'] ?? []) as $field) {
            $name = (string) ($field['name'] ?? '');
            if ($name === '' || isset($protected[$name])) {
                continue;
            }

            $fields[] = array_merge($field, [
                'name' => $name,
                'section' => (string) ($section['title'] ?? 'Campos'),
                'section_key' => (string) ($section['key'] ?? ''),
            ]);
        }
    }

    return $fields;
}

function bit_admin_hideable_base_fields(int $empresaId, array $companyConfig, array $json = []): array
{
    return bit_admin_configurable_base_fields($empresaId, $companyConfig, $json);
}

function bit_admin_field_from_post(array $companyConfig): array
{
    $type = trim((string) ($_POST['type'] ?? 'text'));
    $name = trim((string) ($_POST['name'] ?? ''));
    $label = trim((string) ($_POST['label'] ?? ''));
    $section = trim((string) ($_POST['section'] ?? ''));
    $col = trim((string) ($_POST['col'] ?? 'col-md-6'));
    $suffix = trim((string) ($_POST['suffix'] ?? ''));
    $order = (int) ($_POST['order'] ?? 0);

    if ($section === '') {
        $section = 'Campos adicionales';
    }
    if (!in_array($col, ['col-md-3', 'col-md-4', 'col-md-6', 'col-md-12'], true)) {
        $col = 'col-md-6';
    }

    $field = [
        'type' => $type,
        'name' => $name,
        'label' => $label,
        'section' => $section,
        'required' => !empty($_POST['required']),
        'order' => $order,
        'col' => $col,
    ];

    if ($type === 'subsection') {
        $field['description'] = trim((string) ($_POST['description'] ?? ''));
        $field['required'] = false;
        $field['col'] = 'col-md-12';
    }

    if ($suffix !== '') {
        $field['suffix'] = $suffix;
    }

    $selectedSedes = array_values(array_intersect(
        array_map('strval', (array) ($_POST['sedes'] ?? [])),
        array_map('strval', (array) ($companyConfig['sedes'] ?? []))
    ));
    if ($selectedSedes !== []) {
        $field['sedes'] = $selectedSedes;
    }

    if (in_array($type, ['select', 'multiselect'], true)) {
        $field['options'] = bit_admin_parse_lines((string) ($_POST['options'] ?? ''));
        if ($field['options'] === []) {
            return [null, 'Los campos tipo lista deben tener al menos una opción.'];
        }
    }

    if ($type === 'number') {
        $min = trim((string) ($_POST['min'] ?? ''));
        $max = trim((string) ($_POST['max'] ?? ''));
        $step = trim((string) ($_POST['step'] ?? ''));
        $numberFormat = trim((string) ($_POST['number_format'] ?? 'plain'));
        $numberDecimals = trim((string) ($_POST['number_decimals'] ?? ''));
        $suffixSingular = trim((string) ($_POST['suffix_singular'] ?? ''));
        $suffixPlural = trim((string) ($_POST['suffix_plural'] ?? ''));
        if ($min !== '' && is_numeric(str_replace(',', '.', $min))) {
            $field['min'] = str_replace(',', '.', $min);
        }
        if ($max !== '' && is_numeric(str_replace(',', '.', $max))) {
            $field['max'] = str_replace(',', '.', $max);
        }
        if ($step !== '') {
            $field['step'] = $step;
        }
        $field['number_format'] = in_array($numberFormat, ['plain', 'currency'], true) ? $numberFormat : 'plain';
        if (preg_match('/^\d+$/', $numberDecimals) === 1) {
            $field['number_decimals'] = max(0, min(6, (int) $numberDecimals));
        }
        if ($suffixSingular !== '') {
            $field['suffix_singular'] = $suffixSingular;
        }
        if ($suffixPlural !== '') {
            $field['suffix_plural'] = $suffixPlural;
        }
    }

    if ($type === 'yes_no') {
        $detailName = trim((string) ($_POST['detail_name'] ?? ''));
        $field['detail_name'] = $detailName !== '' ? $detailName : ($name . '_detalle');
        $field['detail_label'] = trim((string) ($_POST['detail_label'] ?? 'Detalle')) ?: 'Detalle';
        $detailType = (string) ($_POST['detail_type'] ?? 'textarea');
        $field['detail_type'] = in_array($detailType, ['textarea', 'number', 'date', 'multiselect'], true) ? $detailType : 'textarea';
        if ($field['detail_type'] === 'multiselect') {
            $field['detail_options'] = bit_admin_parse_lines((string) ($_POST['detail_options'] ?? ''));
            if ($field['detail_options'] === []) {
                return [null, 'El detalle multiselect debe tener al menos una opción.'];
            }
        }
    }

    if ($type === 'multiselect_detail_group') {
        $field['options'] = bit_admin_parse_lines((string) ($_POST['ms_options'] ?? ''));
        if ($field['options'] === []) {
            return [null, 'La lista con detalle debe tener al menos una opción.'];
        }
        $field['detail_name'] = trim((string) ($_POST['ms_detail_name'] ?? '')) ?: ($name . '_detalles');
        $field['no_apply_value'] = trim((string) ($_POST['ms_no_apply_value'] ?? '')) ?: 'No aplica visita';
        $field['placeholder'] = trim((string) ($_POST['ms_placeholder'] ?? '')) ?: 'Escribe Nombre Apellido - Cargo';
        $help = trim((string) ($_POST['ms_help'] ?? ''));
        if ($help !== '') {
            $field['help'] = $help;
        }
    }

    if (in_array($type, ['yes_no_quantity_group', 'quantity_group'], true)) {
        $quantitySuffix = trim((string) ($_POST['quantity_suffix'] ?? ''));
        $quantitySuffixSingular = trim((string) ($_POST['quantity_suffix_singular'] ?? ''));
        $quantitySuffixPlural = trim((string) ($_POST['quantity_suffix_plural'] ?? ''));
        if ($quantitySuffix !== '') {
            $field['suffix'] = $quantitySuffix;
        }
        if ($quantitySuffixSingular !== '') {
            $field['suffix_singular'] = $quantitySuffixSingular;
        }
        if ($quantitySuffixPlural !== '') {
            $field['suffix_plural'] = $quantitySuffixPlural;
        }

        $itemNames = (array) ($_POST['group_fields']['name'] ?? []);
        $itemLabels = (array) ($_POST['group_fields']['label'] ?? []);
        $itemTypes = (array) ($_POST['group_fields']['type'] ?? []);
        $itemOptions = (array) ($_POST['group_fields']['options'] ?? []);
        $itemSuffixes = (array) ($_POST['group_fields']['suffix'] ?? []);
        $itemSuffixSingulars = (array) ($_POST['group_fields']['suffix_singular'] ?? []);
        $itemSuffixPlurals = (array) ($_POST['group_fields']['suffix_plural'] ?? []);
        $itemNumberFormats = (array) ($_POST['group_fields']['number_format'] ?? []);
        $itemNumberDecimals = (array) ($_POST['group_fields']['number_decimals'] ?? []);
        $rowKeys = (array) ($_POST['group_fields']['row_key'] ?? []);
        $requiredValues = array_flip(array_map('strval', (array) ($_POST['group_fields']['required'] ?? [])));
        $allowedItemTypes = ['text', 'textarea', 'number', 'select', 'date', 'time', 'simple_radio'];

        $fields = [];
        $usedItemNames = [];
        foreach ($itemNames as $index => $itemNameRaw) {
            $itemName = trim((string) $itemNameRaw);
            $itemLabel = trim((string) ($itemLabels[$index] ?? ''));
            $itemType = in_array(($itemTypes[$index] ?? 'text'), $allowedItemTypes, true) ? (string) $itemTypes[$index] : 'text';
            if ($itemName === '' || $itemLabel === '') {
                continue;
            }
            if (preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $itemName) !== 1) {
                return [null, 'Cada sub-campo debe tener un nombre técnico válido.'];
            }
            if (isset($usedItemNames[$itemName])) {
                return [null, 'Los nombres técnicos de los sub-campos no pueden repetirse.'];
            }
            $usedItemNames[$itemName] = true;

            $rowKey = (string) ($rowKeys[$index] ?? ('gf_' . $index));
            $itemField = [
                'name' => $itemName,
                'label' => $itemLabel,
                'type' => $itemType,
                'required' => isset($requiredValues[$rowKey]),
            ];
            if ($itemType === 'select') {
                $itemOptionsList = bit_admin_parse_lines((string) ($itemOptions[$index] ?? ''));
                if ($itemOptionsList === []) {
                    return [null, 'Cada sub-campo tipo lista del grupo debe tener al menos una opción.'];
                }
                $itemField['options'] = $itemOptionsList;
            }
            if ($itemType === 'number') {
                $itemSuffix = trim((string) ($itemSuffixes[$index] ?? ''));
                $itemSuffixSingular = trim((string) ($itemSuffixSingulars[$index] ?? ''));
                $itemSuffixPlural = trim((string) ($itemSuffixPlurals[$index] ?? ''));
                $itemNumberFormat = trim((string) ($itemNumberFormats[$index] ?? 'plain'));
                $itemDecimals = trim((string) ($itemNumberDecimals[$index] ?? ''));
                if ($itemSuffix !== '') {
                    $itemField['suffix'] = $itemSuffix;
                }
                if ($itemSuffixSingular !== '') {
                    $itemField['suffix_singular'] = $itemSuffixSingular;
                }
                if ($itemSuffixPlural !== '') {
                    $itemField['suffix_plural'] = $itemSuffixPlural;
                }
                $itemField['number_format'] = in_array($itemNumberFormat, ['plain', 'currency'], true) ? $itemNumberFormat : 'plain';
                if (preg_match('/^\d+$/', $itemDecimals) === 1) {
                    $itemField['number_decimals'] = max(0, min(6, (int) $itemDecimals));
                }
            }
            $fields[] = $itemField;
        }

        if ($fields === []) {
            return [null, 'El grupo con cantidad debe tener al menos un sub-campo.'];
        }

        $field['quantity_name'] = trim((string) ($_POST['quantity_name'] ?? '')) ?: ($name . '_cantidad');
        $field['quantity_label'] = trim((string) ($_POST['quantity_label'] ?? '')) ?: 'Cantidad';
        $field['item_label'] = trim((string) ($_POST['item_label'] ?? '')) ?: 'Registro';
        if ($type === 'quantity_group') {
            $field['zero_report_value'] = trim((string) ($_POST['zero_report_value'] ?? '')) ?: 'Sin registros';
            $field['required'] = true;
            $field['min'] = 0;
        } else {
            $field['no_report_value'] = trim((string) ($_POST['no_report_value'] ?? '')) ?: 'Sin novedad';
            $field['min'] = max(1, (int) ($_POST['quantity_min'] ?? 1));
        }
        $field['max'] = min(10, max(1, (int) ($_POST['quantity_max'] ?? 10)));
        $field['fields'] = $fields;
    }

    $normalized = app_bitacora_normalize_dynamic_field($field);
    if ($normalized === null) {
        return [null, 'Revisa el nombre, tipo y detalle del campo. El nombre solo puede tener letras, números y guion bajo, iniciando con una letra.'];
    }
    if (trim((string) ($normalized['label'] ?? '')) === '') {
        return [null, 'La etiqueta del campo es obligatoria.'];
    }

    return [$normalized, ''];
}

function bit_admin_base_field_map(int $empresaId, array $companyConfig): array
{
    $fields = [];
    foreach (bit_admin_configurable_base_fields($empresaId, $companyConfig) as $field) {
        $fields[(string) ($field['name'] ?? '')] = $field;
    }

    return array_filter($fields, static fn($field) => is_array($field) && (string) ($field['name'] ?? '') !== '');
}

function bit_admin_base_override_from_post(array $baseField, array $companyConfig): array
{
    $name = (string) ($baseField['name'] ?? '');
    $type = (string) ($baseField['type'] ?? 'text');
    $label = trim((string) ($_POST['label'] ?? ''));
    $col = trim((string) ($_POST['col'] ?? ($baseField['col'] ?? 'col-md-6')));
    $order = (int) ($_POST['order'] ?? ($baseField['order'] ?? 0));

    if ($name === '' || $label === '') {
        return [null, 'La etiqueta del campo base es obligatoria.'];
    }
    if (!in_array($col, ['col-md-3', 'col-md-4', 'col-md-6', 'col-md-12'], true)) {
        $col = 'col-md-6';
    }

    $selectedSedes = array_values(array_intersect(
        array_map('strval', (array) ($_POST['sedes'] ?? [])),
        array_map('strval', (array) ($companyConfig['sedes'] ?? []))
    ));

    $override = [
        'name' => $name,
        'label' => $label,
        'required' => !empty($_POST['required']),
        'order' => $order,
        'col' => $col,
        'sedes' => $selectedSedes,
    ];

    if ($type === 'subsection') {
        $override['description'] = trim((string) ($_POST['description'] ?? ($baseField['description'] ?? '')));
        $override['required'] = false;
        $override['col'] = 'col-md-12';
    }

    if (in_array($type, ['select', 'multiselect'], true)) {
        $override['options'] = bit_admin_parse_option_lines((string) ($_POST['options'] ?? ''));
        if ($override['options'] === []) {
            return [null, 'Los campos base tipo lista deben tener al menos una opción.'];
        }
    }

    if ($type === 'number') {
        $min = trim((string) ($_POST['min'] ?? ''));
        $max = trim((string) ($_POST['max'] ?? ''));
        $step = trim((string) ($_POST['step'] ?? ''));
        $suffix = trim((string) ($_POST['suffix'] ?? ''));
        $numberFormat = trim((string) ($_POST['number_format'] ?? 'plain'));
        $numberDecimals = trim((string) ($_POST['number_decimals'] ?? ''));
        $suffixSingular = trim((string) ($_POST['suffix_singular'] ?? ''));
        $suffixPlural = trim((string) ($_POST['suffix_plural'] ?? ''));
        if ($min !== '' && is_numeric(str_replace(',', '.', $min))) {
            $override['min'] = str_replace(',', '.', $min);
        }
        if ($max !== '' && is_numeric(str_replace(',', '.', $max))) {
            $override['max'] = str_replace(',', '.', $max);
        }
        if ($step !== '') {
            $override['step'] = $step;
        }
        $override['suffix'] = $suffix;
        $override['number_format'] = in_array($numberFormat, ['plain', 'currency'], true) ? $numberFormat : 'plain';
        if (preg_match('/^\d+$/', $numberDecimals) === 1) {
            $override['number_decimals'] = max(0, min(6, (int) $numberDecimals));
        }
        if ($suffixSingular !== '') {
            $override['suffix_singular'] = $suffixSingular;
        }
        if ($suffixPlural !== '') {
            $override['suffix_plural'] = $suffixPlural;
        }
    }

    if ($type === 'yes_no') {
        $override['detail_label'] = trim((string) ($_POST['detail_label'] ?? ($baseField['detail_label'] ?? 'Detalle'))) ?: 'Detalle';
        $detailType = (string) ($_POST['detail_type'] ?? ($baseField['detail_type'] ?? 'textarea'));
        $override['detail_type'] = in_array($detailType, ['textarea', 'number', 'date', 'multiselect'], true) ? $detailType : 'textarea';
        if ($override['detail_type'] === 'multiselect') {
            $override['detail_options'] = bit_admin_parse_option_lines((string) ($_POST['detail_options'] ?? ''));
            if ($override['detail_options'] === []) {
                return [null, 'El detalle multiselect debe tener al menos una opción.'];
            }
        }
    }

    if ($type === 'yes_no_quantity_group') {
        $override['no_report_value'] = trim((string) ($_POST['no_report_value'] ?? ($baseField['no_report_value'] ?? 'Sin novedad'))) ?: 'Sin novedad';
    }
    if ($type === 'quantity_group') {
        $override['zero_report_value'] = trim((string) ($_POST['zero_report_value'] ?? ($baseField['zero_report_value'] ?? 'Sin registros'))) ?: 'Sin registros';
        $override['required'] = true;
    }
    if (in_array($type, ['yes_no_quantity_group', 'quantity_group'], true)) {
        $override['suffix'] = trim((string) ($_POST['quantity_suffix'] ?? ($baseField['suffix'] ?? '')));
        $override['suffix_singular'] = trim((string) ($_POST['quantity_suffix_singular'] ?? ($baseField['suffix_singular'] ?? '')));
        $override['suffix_plural'] = trim((string) ($_POST['quantity_suffix_plural'] ?? ($baseField['suffix_plural'] ?? '')));
    }

    $normalized = app_bitacora_normalize_field_override($override, $baseField);
    if ($normalized === []) {
        return [null, 'No fue posible preparar la configuración del campo base.'];
    }

    return [$normalized, ''];
}
