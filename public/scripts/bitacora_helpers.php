<?php

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/bitacora.php';
require_once __DIR__ . '/bitacora_pdf_helpers.php';

function bit_e($s): string
{
    return nl2br(htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'));
}

function bit_h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function bit_report_display_value(string $value): string
{
    $value = trim($value);
    return $value === 'No' ? 'Sin novedad' : $value;
}

function bit_report_format_number(string $value, array $field): string
{
    $value = trim($value);
    $numericValue = str_replace(',', '.', $value);
    if ($value === '' || !is_numeric($numericValue)) {
        return $value;
    }

    if ((string) ($field['number_format'] ?? 'plain') !== 'currency') {
        return $value;
    }

    $decimals = array_key_exists('number_decimals', $field)
        ? max(0, min(6, (int) $field['number_decimals']))
        : 0;
    $number = (float) $numericValue;
    $formatted = number_format(abs($number), $decimals, ',', '.');

    return ($number < 0 ? '-' : '') . '$' . $formatted;
}

function bit_report_resolve_suffix(string $value, array $field): string
{
    if ((string) ($field['type'] ?? '') !== 'number') {
        return '';
    }

    $plural = trim((string) ($field['suffix_plural'] ?? ''));
    if ($plural === '') {
        $plural = trim((string) ($field['suffix'] ?? ''));
    }

    $singular = trim((string) ($field['suffix_singular'] ?? ''));
    $numericValue = str_replace(',', '.', trim($value));
    if ((string) ($field['type'] ?? '') === 'number' && $singular !== '' && is_numeric($numericValue) && (float) $numericValue === 1.0) {
        return $singular;
    }

    return $plural;
}

function bit_report_field_value($value, array $field): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    $rawValue = $value;

    if ((string) ($field['type'] ?? '') === 'number') {
        $value = bit_report_format_number($value, $field);
    }

    $suffix = bit_report_resolve_suffix($rawValue, $field);
    if ($value !== '' && $suffix !== '') {
        $value .= ' ' . $suffix;
    }

    return $value;
}

function bit_report_yes_no_detail_value(array $field, string $value): string
{
    if ((string) ($field['detail_type'] ?? 'textarea') !== 'number') {
        return $value;
    }

    $detailSuffix = trim((string) ($field['detail_suffix'] ?? ''));
    if ($detailSuffix === '') {
        $detailSuffix = trim((string) ($field['suffix'] ?? ''));
    }
    $detailSuffixSingular = trim((string) ($field['detail_suffix_singular'] ?? ''));
    if ($detailSuffixSingular === '') {
        $detailSuffixSingular = trim((string) ($field['suffix_singular'] ?? ''));
    }
    $detailSuffixPlural = trim((string) ($field['detail_suffix_plural'] ?? ''));
    if ($detailSuffixPlural === '') {
        $detailSuffixPlural = trim((string) ($field['suffix_plural'] ?? ''));
    }

    return bit_report_field_value($value, [
        'type' => 'number',
        'number_format' => $field['detail_number_format'] ?? 'plain',
        'number_decimals' => $field['detail_number_decimals'] ?? null,
        'suffix' => $detailSuffix,
        'suffix_singular' => $detailSuffixSingular,
        'suffix_plural' => $detailSuffixPlural,
    ]);
}

function bit_report_yes_no_detail_from_data(array $field, array $data): string
{
    $detailName = (string) ($field['detail_name'] ?? '');
    if ($detailName === '') {
        return '';
    }

    $value = $data[$detailName] ?? '';
    return (string) ($field['detail_type'] ?? 'textarea') === 'multiselect'
        ? bit_normalize_array_value($value)
        : trim((string) $value);
}

function bit_report_yes_no_value(string $answer, string $detail = '', string $noReportValue = ''): string
{
    $answer = trim($answer);
    $detail = trim($detail);
    $noReportValue = trim($noReportValue);

    if ($answer === 'No') {
        if ($detail !== '') {
            return $detail;
        }

        return $noReportValue !== '' ? $noReportValue : 'Sin novedad';
    }

    return bit_report_display_value(trim($answer . ($detail !== '' ? '. ' . $detail : '')));
}

function bit_upper_clean(string $value): string
{
    return trim(mb_strtoupper($value, 'UTF-8'));
}

function bit_safe_filename(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/[^\p{L}\p{N}\-_\. ]/u', '_', $value);
    $value = preg_replace('/\s+/', '_', $value);
    return trim($value, '_');
}

function bit_normalize_array_value($value): string
{
    if (is_array($value)) {
        $value = array_map(static function ($v) {
            return trim((string)$v);
        }, $value);

        $value = array_filter($value, static function ($v) {
            return $v !== '';
        });

        return implode(', ', $value);
    }

    return trim((string)$value);
}

function bit_get_default_texts(): array
{
    return [
        'DEFAULT_BPM'          => 'No se recibio ninguna visita.',
        'DEFAULT_TI'           => 'Sin novedades con los equipos.',
        'DEFAULT_TI1'          => 'Sin novedades con la facturación eléctronica.',
        'DEFAULT_TI2'          => 'Sin novedades por reportar.',
        'DEFAULT_SST'          => 'Sin hallazgos para reportar.',
        'DEFAULT_PE'           => 'El dia de hoy no se utilizo.',
        'DEFAULT_BH_ENVIADAS'  => 'No se envio hielo a otras sedes.',
        'DEFAULT_BH_RECIBIDAS' => 'No se recibio hielo de otras sedes.',
        'DEFAULT_FACTURAS'     => 'No se anularon facturas.',
    ];
}

/**
 * Mantiene la lógica existente de backend para planta eléctrica:
 * calcula minutos entre HH:MM y soporta cruce de medianoche.
 */
function bit_calcular_minutos_planta(string $inicio = '', string $fin = ''): int
{
    if ($inicio === '' || $fin === '') {
        return 0;
    }

    if (!preg_match('/^\d{2}:\d{2}$/', $inicio) || !preg_match('/^\d{2}:\d{2}$/', $fin)) {
        return 0;
    }

    [$h1, $m1] = array_map('intval', explode(':', $inicio));
    [$h2, $m2] = array_map('intval', explode(':', $fin));

    $min1 = ($h1 * 60) + $m1;
    $min2 = ($h2 * 60) + $m2;

    $diff = $min2 - $min1;

    if ($diff < 0) {
        $diff += 24 * 60;
    }

    return $diff;
}

function bit_handle_planta_electrica(array &$post, array $defaults): array
{
    $planta = trim((string)($post['planta_elect'] ?? ''));
    $defaultedFields = [];

    if ($planta === 'No') {
        if (!isset($post['mant5']) || trim((string)$post['mant5']) === '') {
            $post['mant5'] = '00:00';
        }
        if (!isset($post['mant6']) || trim((string)$post['mant6']) === '') {
            $post['mant6'] = '00:00';
        }
        if (!isset($post['mant7']) || trim((string)$post['mant7']) === '') {
            $post['mant7'] = 0;
        }
        if (!isset($post['mant8']) || trim((string)$post['mant8']) === '') {
            $post['mant8'] = $defaults['DEFAULT_PE'];
        }

        if (trim((string) ($post['novedades_planta'] ?? '')) === 'No'
            && trim((string) ($post['mant8'] ?? '')) === trim((string) ($defaults['DEFAULT_PE'] ?? ''))) {
            $defaultedFields[] = 'novedades_planta';
        }

        return $defaultedFields;
    }

    if ($planta === 'Si') {
        $ini  = trim((string)($post['mant5'] ?? ''));
        $fin  = trim((string)($post['mant6'] ?? ''));

        $post['mant7'] = ($ini !== '' && $fin !== '') ? bit_calcular_minutos_planta($ini, $fin) : 0;

        if (isset($post['mant8']) && trim((string)$post['mant8']) === $defaults['DEFAULT_PE']) {
            $post['mant8'] = '';
        }
    }

    return $defaultedFields;
}

function bit_get_config(int $empresaId, string $sede): array
{
    $sede = bit_upper_clean($sede);
    $companyConfig = app_bitacora_config($empresaId) ?? [];
    $sections = app_bitacora_form_sections($empresaId, $companyConfig);
    return [
        'fields' => app_bitacora_collect_field_names($sections, $sede),
        'form_sections' => $sections,
        'quantity_groups' => app_bitacora_collect_fields_by_type($sections, ['yes_no_quantity_group'], $sede),
        'direct_quantity_groups' => app_bitacora_collect_fields_by_type($sections, ['quantity_group'], $sede),
        'detail_groups' => app_bitacora_collect_fields_by_type($sections, ['yes_no_detail_group'], $sede),
        'branch_groups' => app_bitacora_collect_fields_by_type($sections, ['yes_no_branch_group'], $sede),
        'multiselect_detail_groups' => app_bitacora_collect_fields_by_type($sections, ['multiselect_detail_group'], $sede),
    ];

}

function bit_get_conditional_rules(array $defaults): array
{
    return [
        ['radio' => 'visita_ss',         'field' => 'bpm1',    'default' => $defaults['DEFAULT_BPM']],
        ['radio' => 'visita_dagma',      'field' => 'bpm2',    'default' => $defaults['DEFAULT_BPM']],
        ['radio' => 'visita_west',       'field' => 'bpm3',    'default' => $defaults['DEFAULT_BPM']],
        ['radio' => 'visita_cp',         'field' => 'bpm4',    'default' => $defaults['DEFAULT_BPM']],
        ['radio' => 'visita_acu',        'field' => 'bpm5',    'default' => $defaults['DEFAULT_BPM']],

        ['radio' => 'equipos_ti',        'field' => 'ti',      'default' => $defaults['DEFAULT_TI']],
        ['radio' => 'facturas_ti',       'field' => 'ti1',     'default' => $defaults['DEFAULT_TI1']],
        ['radio' => 'novedades_ti',      'field' => 'ti2',     'default' => $defaults['DEFAULT_TI2']],

        ['radio' => 'accidentes_sst',    'field' => 'sst1',    'default' => $defaults['DEFAULT_SST']],
        ['radio' => 'incapacidades_sst', 'field' => 'sst2',    'default' => $defaults['DEFAULT_SST']],
        ['radio' => 'ambiente_laboral',  'field' => 'sst3',    'default' => $defaults['DEFAULT_SST']],
        ['radio' => 'senal_sst',         'field' => 'sst4',    'default' => $defaults['DEFAULT_SST']],
        ['radio' => 'entrega_epp',       'field' => 'sst6',    'default' => $defaults['DEFAULT_SST']],
        ['radio' => 'novedades_sst',     'field' => 'sst8',    'default' => $defaults['DEFAULT_SST']],

        ['radio' => 'hielo_enviado',     'field' => 'hielo4',  'default' => $defaults['DEFAULT_BH_ENVIADAS']],
        ['radio' => 'hielo_recibido',    'field' => 'hielo5',  'default' => $defaults['DEFAULT_BH_RECIBIDAS']],

        ['radio' => 'facturas_mesas',    'field' => 'fa_mesas', 'default' => $defaults['DEFAULT_FACTURAS']],
        ['radio' => 'facturas_domic',    'field' => 'fa_dom',   'default' => $defaults['DEFAULT_FACTURAS']],
        ['radio' => 'facturas_rappi',    'field' => 'fa_rappi', 'default' => $defaults['DEFAULT_FACTURAS']],

    ];
}

function bit_apply_conditional_defaults(array &$post, array $rules): array
{
    $defaultedFields = [];

    foreach ($rules as $rule) {
        $radio = trim((string)($post[$rule['radio']] ?? ''));
        $value = trim((string)($post[$rule['field']] ?? ''));
        $default = trim((string)($rule['default'] ?? ''));

        if ($radio === 'No' && $value === '' && $default !== '') {
            $post[$rule['field']] = $default;
            $defaultedFields[] = (string) $rule['radio'];
        }

        if ($radio === 'Si' && $default !== '' && $value === $default) {
            $post[$rule['field']] = '';
        }
    }

    return array_values(array_unique($defaultedFields));
}

function bit_selected_values($value): array
{
    $values = is_array($value) ? $value : ($value === null || $value === '' ? [] : [$value]);
    $values = array_map(static fn($item) => trim((string) $item), $values);
    $values = array_filter($values, static fn($item) => $item !== '');

    return array_values(array_unique($values));
}

function bit_multiselect_detail_name(array $field): string
{
    $name = (string) ($field['name'] ?? '');
    return (string) ($field['detail_name'] ?? ($name . '_detalles'));
}

function bit_multiselect_detail_no_apply(array $field): string
{
    return (string) ($field['no_apply_value'] ?? 'No aplica visita');
}

function bit_multiselect_detail_rows_from_post(array $post, array $field): array
{
    $detailName = bit_multiselect_detail_name($field);
    $rawRows = $post[$detailName] ?? [];
    if (!is_array($rawRows)) {
        return [];
    }

    $rows = [];
    foreach ($rawRows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $visitor = trim((string) ($row['visitante'] ?? ''));
        if ($visitor === '') {
            continue;
        }

        $rows[$visitor] = [
            'visitante' => $visitor,
            'hora_inicio' => trim((string) ($row['hora_inicio'] ?? '')),
            'hora_final' => trim((string) ($row['hora_final'] ?? '')),
            'actividades' => trim((string) ($row['actividades'] ?? '')),
        ];
    }

    return $rows;
}

function bit_normalize_multiselect_detail_groups(array $post, array $groups): array
{
    $result = [];

    foreach ($groups as $field) {
        $name = (string) ($field['name'] ?? '');
        if ($name === '') {
            continue;
        }

        $selected = bit_selected_values($post[$name] ?? []);
        $noApply = bit_multiselect_detail_no_apply($field);
        $isNoApply = in_array($noApply, $selected, true);
        $detailRows = bit_multiselect_detail_rows_from_post($post, $field);
        $items = [];

        if (!$isNoApply) {
            foreach ($selected as $visitor) {
                if ($visitor === $noApply || !isset($detailRows[$visitor])) {
                    continue;
                }

                $items[] = $detailRows[$visitor];
            }
        }

        $result[$name] = [
            'selected' => $selected,
            'no_apply' => $isNoApply,
            'items' => $items,
        ];
    }

    return $result;
}

function bit_normalize_data(array $post, array $config): array
{
    $data = [];

    foreach ($config['fields'] as $field) {
        $data[$field] = isset($post[$field]) ? bit_normalize_array_value($post[$field]) : '';
    }

    $data['sede']        = bit_upper_clean($data['sede'] ?? '');
    $data['responsable'] = bit_upper_clean($data['responsable'] ?? '');
    $data['cargo']       = trim((string)($data['cargo'] ?? ''));

    $data['fecha'] = '';
    $data['fecha_iso'] = trim((string) ($post['fechab'] ?? ''));
    if (!empty($post['fechab'])) {
        $timestamp = strtotime((string)$post['fechab']);
        if ($timestamp !== false) {
            $data['fecha'] = date('d-m-Y', $timestamp);
        }
    }

    $data['_multiselect_detail_groups'] = bit_normalize_multiselect_detail_groups($post, $config['multiselect_detail_groups'] ?? []);

    return $data;
}

function bit_render_detail(string $title, string $value, bool $mostrarSiVacio = false, bool $mostrarSinNovedadParaNo = true, bool $tituloRojo = false): string
{
    $value = $mostrarSinNovedadParaNo ? bit_report_display_value($value) : trim($value);

    if (!$mostrarSiVacio && $value === '') {
        return '';
    }
    $titleStyle = $tituloRojo ? ' style="color:#d71920;"' : '';
    return '<div class="sub-item"><strong' . $titleStyle . '>' . bit_h($title) . ':</strong> ' . bit_e($value) . '</div>';
}

function bit_report_yes_no_is_silent(array $field, array $data, string $answer, string $detail = ''): bool
{
    if (trim($answer) !== 'No') {
        return false;
    }

    if (trim($detail) === '') {
        return true;
    }

    $name = (string) ($field['name'] ?? '');
    return $name !== '' && in_array($name, (array) ($data['_conditional_default_fields'] ?? []), true);
}

function bit_report_rows_have_content(array $rows): bool
{
    foreach ($rows as $row) {
        if (trim((string) $row) !== '') {
            return true;
        }
    }

    return false;
}

function bit_render_report_blocks(array $fields, callable $renderField): array
{
    $blocks = [];
    $pendingRows = [];
    $rows = [];
    $hasReportableRows = false;
    $hasFieldsSinceMarker = false;

    $flush = static function () use (&$blocks, &$pendingRows, &$rows, &$hasReportableRows, &$hasFieldsSinceMarker): void {
        if ($hasReportableRows) {
            $blocks[] = array_merge($pendingRows, $rows);
        }

        $pendingRows = [];
        $rows = [];
        $hasReportableRows = false;
        $hasFieldsSinceMarker = false;
    };

    foreach ($fields as $field) {
        $fieldRows = (array) $renderField($field);
        if (app_bitacora_field_is_presentational($field)) {
            if ($hasFieldsSinceMarker) {
                $flush();
            }
            $pendingRows = array_merge($pendingRows, $fieldRows);
            continue;
        }

        $hasFieldsSinceMarker = true;
        if (bit_report_rows_have_content($fieldRows)) {
            $hasReportableRows = true;
        }
        $rows = array_merge($rows, $fieldRows);
    }

    $flush();
    return $blocks;
}

function bit_render_report_block(array $rows): string
{
    $rows = array_values(array_filter($rows, static fn($row) => trim((string) $row) !== ''));
    if ($rows === []) {
        return '';
    }

    return '<div class="report-block">' . implode('', $rows) . '</div>';
}

function bit_render_section(string $title, array $rows): string
{
    $rows = array_filter($rows, function ($row) {
        return trim((string)$row) !== '';
    });

    if (empty($rows)) {
        return '';
    }
    
    $html = '<div class="area-section">';
    $html .= '<div class="area-title">' . bit_h($title) . '</div>';
    foreach ($rows as $row) {
        $html .= $row;
    }
    $html .= '</div>';
    return $html;
}

function bit_render_subsection(array $field): string
{
    $title = trim((string) ($field['label'] ?? ''));
    if ($title === '') {
        return '';
    }

    $description = trim((string) ($field['description'] ?? ''));
    $html = '<div class="report-subsection" style="margin:10px 0 8px;padding:8px 10px;border-left:4px solid #8B1E1E;background:#f6f6f8;page-break-inside:avoid;">';
    $html .= '<div style="font-weight:bold;color:#8B1E1E;">' . bit_h($title) . '</div>';
    if ($description !== '') {
        $html .= '<div style="margin-top:3px;color:#555;font-size:12px;line-height:1.4;">' . bit_e($description) . '</div>';
    }
    return $html . '</div>';
}

function bit_render_group_item(array $field, int $index, array $data): string
{
    $groupName = (string) ($field['name'] ?? '');
    $itemLabel = (string) ($field['item_label'] ?? 'Registro');
    $rows = [];

    foreach ((array) ($field['fields'] ?? []) as $itemField) {
        $itemFieldName = (string) ($itemField['name'] ?? '');
        if ($groupName === '' || $itemFieldName === '') {
            continue;
        }

        $name = app_bitacora_group_item_field_name($groupName, $index, $itemFieldName);
        $label = (string) ($itemField['label'] ?? $itemFieldName);
        $rawValue = trim((string) ($data[$name] ?? ''));
        if ((string) ($itemField['type'] ?? '') === 'simple_radio' && $rawValue === 'No') {
            continue;
        }

        $value = bit_report_display_value(bit_report_field_value($rawValue, $itemField));

        if ($value !== '') {
            $rows[] = '<div class="sub-item"><strong>' . bit_h($label) . ':</strong> ' . bit_e($value) . '</div>';
        }
    }

    if ($rows === []) {
        return '';
    }

    return '<div class="sub-item report-record"><strong>' . bit_h($itemLabel . ' ' . $index) . ':</strong><div style="margin-left:12px; margin-top:4px;">' . implode('', $rows) . '</div></div>';
}

function bit_render_quantity_group(array $field, array $data): array
{
    if (!app_bitacora_field_available_for_date($field, (string) ($data['fecha_iso'] ?? ''))) {
        return [];
    }

    $name = (string) ($field['name'] ?? '');
    $label = (string) ($field['label'] ?? $name);
    $quantityName = (string) ($field['quantity_name'] ?? ($name . '_cantidad'));
    $answer = trim((string) ($data[$name] ?? ''));
    if ($answer === 'No') {
        return [];
    }

    $renderAnswer = $answer !== '' ? $answer : 'No diligenciado';
    $rows = [bit_render_detail($label, $renderAnswer, true)];

    if ($answer !== 'Si') {
        return $rows;
    }

    $quantity = (int) ($data[$quantityName] ?? 0);
    $max = max(1, min(10, (int) ($field['max'] ?? 10)));
    $quantity = max(0, min($quantity, $max));

    if ($quantity > 0) {
        $quantitySuffix = trim((string) ($field['suffix_plural'] ?? ''));
        if ($quantitySuffix === '') {
            $quantitySuffix = trim((string) ($field['suffix'] ?? ''));
        }
        if ($quantitySuffix === '') {
            $quantitySuffix = trim((string) ($field['suffix_singular'] ?? ''));
        }
        if ($quantitySuffix !== '') {
            $rows[] = bit_render_detail((string) ($field['quantity_label'] ?? 'Cantidad'), bit_report_field_value($quantity, [
                'type' => 'number',
                'suffix' => $field['suffix'] ?? '',
                'suffix_plural' => $field['suffix_plural'] ?? '',
                'suffix_singular' => $field['suffix_singular'] ?? '',
            ]), true);
        }
        foreach (range(1, $quantity) as $index) {
            $rows[] = bit_render_group_item($field, $index, $data);
        }
    }

    return $rows;
}

function bit_render_direct_quantity_group(array $field, array $data): array
{
    if (!app_bitacora_field_available_for_date($field, (string) ($data['fecha_iso'] ?? ''))) {
        return [];
    }

    $name = (string) ($field['name'] ?? '');
    $label = (string) ($field['label'] ?? $name);
    $quantityName = (string) ($field['quantity_name'] ?? ($name . '_cantidad'));
    $rawQuantity = trim((string) ($data[$quantityName] ?? ''));
    if ($rawQuantity === '') {
        return [bit_render_detail($label, 'No diligenciado', true)];
    }

    $max = max(1, min(10, (int) ($field['max'] ?? 10)));
    $quantity = max(0, min((int) $rawQuantity, $max));
    if ($quantity === 0) {
        return [];
    }

    $rows = [bit_render_detail($label, bit_report_field_value($quantity, [
        'type' => 'number',
        'number_format' => $field['number_format'] ?? 'plain',
        'number_decimals' => $field['number_decimals'] ?? null,
        'suffix' => $field['suffix'] ?? '',
        'suffix_singular' => $field['suffix_singular'] ?? '',
        'suffix_plural' => $field['suffix_plural'] ?? '',
    ]), true)];
    foreach (range(1, $quantity) as $index) {
        $rows[] = bit_render_group_item($field, $index, $data);
    }
    return $rows;
}

function bit_render_detail_group(array $field, array $data): array
{
    if (!app_bitacora_field_available_for_date($field, (string) ($data['fecha_iso'] ?? ''))) {
        return [];
    }

    $name = (string) ($field['name'] ?? '');
    $label = (string) ($field['label'] ?? $name);
    $answer = trim((string) ($data[$name] ?? ''));
    if ($answer === 'No') {
        return [];
    }

    $renderAnswer = $answer !== '' ? $answer : 'No diligenciado';
    $rows = [bit_render_detail($label, $renderAnswer, true)];

    if ($answer !== 'Si') {
        return $rows;
    }

    foreach ((array) ($field['fields'] ?? []) as $detailField) {
        $detailFieldName = (string) ($detailField['name'] ?? '');
        if ($detailFieldName === '') {
            continue;
        }

        $name = app_bitacora_detail_group_field_name((string) ($field['name'] ?? ''), $detailFieldName);
        $label = (string) ($detailField['label'] ?? $detailFieldName);
        $rows[] = bit_render_detail($label, bit_report_field_value($data[$name] ?? '', $detailField));
    }

    return $rows;
}

function bit_render_branch_group(array $field, array $data): array
{
    if (!app_bitacora_field_available_for_date($field, (string) ($data['fecha_iso'] ?? ''))) {
        return [];
    }

    $name = (string) ($field['name'] ?? '');
    $label = (string) ($field['label'] ?? $name);
    $answer = trim((string) ($data[$name] ?? ''));
    if (!in_array($answer, ['Si', 'No'], true)) {
        return [];
    }

    $branch = $answer === 'Si' ? 'si' : 'no';
    $branchKey = $branch . '_fields';
    $answerLabel = trim((string) ($field['answer_label'] ?? ''));
    if ($answerLabel !== '') {
        $rows = ['<div class="sub-item"><strong style="color:#d71920;">' . bit_h($label) . '</strong> <strong style="color:#000;">' . bit_h($answerLabel) . ':</strong> <span style="color:#000;">' . bit_h($answer) . '</span></div>'];
    } else {
        $rows = [bit_render_detail($label, $answer, true, false, true)];
    }
    foreach ((array) ($field[$branchKey] ?? []) as $branchField) {
        $branchFieldName = (string) ($branchField['name'] ?? '');
        if ($branchFieldName === '') {
            continue;
        }

        $inputName = app_bitacora_branch_group_field_name($name, $branch, $branchFieldName);
        $branchLabel = (string) ($branchField['label'] ?? $branchFieldName);
        $value = bit_report_field_value($data[$inputName] ?? '', $branchField);
        $rows[] = bit_render_detail($branchLabel, $value, false, false);
    }

    return $rows;
}

function bit_render_multiselect_detail_group(array $field, array $data): array
{
    $name = (string) ($field['name'] ?? '');
    $label = (string) ($field['label'] ?? 'Visitas');
    $groupData = $data['_multiselect_detail_groups'][$name] ?? null;

    if (!is_array($groupData)) {
        return [];
    }

    if (!empty($groupData['no_apply'])) {
        return [];
    }

    $rows = [];
    foreach ((array) ($groupData['items'] ?? []) as $item) {
        $visitor = trim((string) ($item['visitante'] ?? ''));
        if ($visitor === '') {
            continue;
        }

        $rows[] = '<div class="sub-item report-visitor"><strong>' . bit_h($visitor) . '</strong><div style="margin-left:12px; margin-top:4px;">' .
            bit_render_detail('HORA INGRESO', (string) ($item['hora_inicio'] ?? '')) .
            bit_render_detail('HORA SALIDA', (string) ($item['hora_final'] ?? '')) .
            bit_render_detail('ACTIVIDADES REALIZADAS', (string) ($item['actividades'] ?? '')) .
            '</div></div>';
    }

    return $rows;
}

function bit_render_schema_field_rows(array $field, array $data): array
{
    if (!app_bitacora_field_available_for_date($field, (string) ($data['fecha_iso'] ?? ''))) {
        return [];
    }

    $type = (string) ($field['type'] ?? 'text');
    if ($type === 'subsection') {
        return [bit_render_subsection($field)];
    }
    if ($type === 'yes_no_quantity_group') {
        return bit_render_quantity_group($field, $data);
    }
    if ($type === 'quantity_group') {
        return bit_render_direct_quantity_group($field, $data);
    }
    if ($type === 'yes_no_detail_group') {
        return bit_render_detail_group($field, $data);
    }
    if ($type === 'yes_no_branch_group') {
        return bit_render_branch_group($field, $data);
    }
    if ($type === 'multiselect_detail_group') {
        return bit_render_multiselect_detail_group($field, $data);
    }

    $name = (string) ($field['name'] ?? '');
    if ($name === '') {
        return [];
    }

    $label = (string) ($field['label'] ?? $name);
    if ($type === 'plant') {
        $answer = trim((string) ($data[$name] ?? ''));
        if ($answer === 'No') {
            return [];
        }

        $rows = [bit_render_detail($label, $answer !== '' ? $answer : 'No diligenciado', true)];
        if ($answer === 'Si') {
            $rows[] = bit_render_detail('HORA ENCENDIDO', (string) ($data['mant5'] ?? ''));
            $rows[] = bit_render_detail('HORA APAGADO', (string) ($data['mant6'] ?? ''));
            $rows[] = bit_render_detail('TIEMPO DE USO (MINUTOS)', (string) ($data['mant7'] ?? ''));
        }
        return $rows;
    }

    $value = $type === 'multiselect'
        ? bit_normalize_array_value($data[$name] ?? '')
        : trim((string) ($data[$name] ?? ''));
    if ($name === 'fechab') {
        $value = trim((string) ($data['fecha'] ?? $value));
    }
    if ($type === 'yes_no' || $type === 'simple_radio') {
        if ($value === '') {
            return [];
        }
        $detail = bit_report_yes_no_detail_from_data($field, $data);
        if (bit_report_yes_no_is_silent($field, $data, $value, $detail)) {
            return [];
        }

        $detail = bit_report_yes_no_detail_value($field, $detail);
        $value = bit_report_yes_no_value($value, $detail, (string) ($field['no_report_value'] ?? ''));
    }

    $value = bit_report_field_value($value, $field);
    return [bit_render_detail($label, $value)];
}

function bit_render_schema_sections(array $sections, array $data, array $excludedKeys = [], array $excludedFieldNames = [], bool $preserveReportBlocks = false): string
{
    $html = '';
    $excluded = array_flip($excludedKeys);
    $excludedFields = array_flip($excludedFieldNames);
    $sede = (string) ($data['sede'] ?? '');

    foreach ($sections as $section) {
        $sectionKey = (string) ($section['key'] ?? '');
        if (isset($excluded[$sectionKey]) || !app_bitacora_field_visible_for_sede($section, $sede)) {
            continue;
        }

        $visibleFields = [];
        foreach ((array) ($section['fields'] ?? []) as $field) {
            if (isset($excludedFields[(string) ($field['name'] ?? '')])) {
                continue;
            }
            if (!app_bitacora_field_visible_for_sede($field, $sede)) {
                continue;
            }
            $visibleFields[] = $field;
        }

        $blocks = bit_render_report_blocks(
            $visibleFields,
            static fn(array $field): array => bit_render_schema_field_rows($field, $data)
        );
        if ($blocks === []) {
            continue;
        }

        $rows = [];
        if ($preserveReportBlocks) {
            foreach ($blocks as $blockRows) {
                $block = bit_render_report_block($blockRows);
                if ($block !== '') {
                    $rows[] = $block;
                }
            }
        } else {
            foreach ($blocks as $blockRows) {
                $rows = array_merge($rows, $blockRows);
            }
        }
        $html .= bit_render_section((string) ($section['title'] ?? $sectionKey), $rows);
    }

    return $html;
}

function bit_render_html(array $data, array $config, bool $includeLogo = false): string
{
    $logoSrc = $includeLogo ? bit_pdf_logo_src() : '';
    $logoHtml = $logoSrc !== '' ? '<td class="logo-cell"><img class="logo" src="' . bit_h($logoSrc) . '" alt="Logo"></td>' : '';
    $bodyMargin = $includeLogo ? '0' : '20px';
    $headerMargin = $includeLogo ? '14px' : '20px';
    $sectionMargin = $includeLogo ? '10px' : '14px';
    $sectionPadding = $includeLogo ? '8px' : '10px';
    $titleMargin = $includeLogo ? '6px' : '8px';
    $subItemMargin = $includeLogo ? '4px' : '6px';
    $lineHeight = $includeLogo ? '1.35' : '1.4';
    $sectionBreakInside = $includeLogo ? 'auto' : 'avoid';
    $titleBreakAfter = $includeLogo ? 'page-break-after: avoid; break-after: avoid;' : '';
    $headerBreakInside = $includeLogo ? 'page-break-inside: avoid; break-inside: avoid;' : '';
    $reportBlockCss = $includeLogo ? '
            .report-block,
            .report-record,
            .report-visitor{
                page-break-inside: avoid;
                break-inside: avoid;
            }
            .report-subsection{
                margin: 8px 0 6px !important;
            }
' : '';

    $html = '
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body{
                font-family: DejaVu Sans, Arial, sans-serif;
                font-size: 14px;
                color: #222;
                margin: ' . $bodyMargin . ';
            }
            .header{
                border-bottom: 2px solid #8B1E1E;
                padding-bottom: 10px;
                margin-bottom: ' . $headerMargin . ';
                ' . $headerBreakInside . '
            }
            .header-table{
                width: 100%;
                border-collapse: collapse;
            }
            .logo-cell{
                width: 115px;
                vertical-align: middle;
                padding-right: 16px;
            }
            .logo{
                width: 95px;
                height: auto;
            }
            .header-info{
                vertical-align: middle;
            }
            .title{
                font-size: 24px;
                font-weight: bold;
                color: #8B1E1E;
                margin-bottom: 6px;
            }
            .meta{
                margin-bottom: 3px;
            }
            .area-section{
                margin-bottom: ' . $sectionMargin . ';
                border: 1px solid #ddd;
                border-radius: 6px;
                padding: ' . $sectionPadding . ';
                page-break-inside: ' . $sectionBreakInside . ';
                break-inside: ' . $sectionBreakInside . ';
            }
            .area-title{
                font-size: 16px;
                font-weight: bold;
                margin-bottom: ' . $titleMargin . ';
                color: #8B1E1E;
                ' . $titleBreakAfter . '
            }
            .sub-item{
                margin-bottom: ' . $subItemMargin . ';
                line-height: ' . $lineHeight . ';
            }
            ' . $reportBlockCss . '
        </style>
    </head>
    <body>
        <div class="header">
            <table class="header-table"><tr>' . $logoHtml . '<td class="header-info">
                <div class="title">BITÁCORA DIARIA ' . bit_h($data['sede'] ?? '') . '</div>
                <div class="meta"><strong>Fecha:</strong> ' . bit_h($data['fecha'] ?? '') . '</div>
                <div class="meta"><strong>Responsable:</strong> ' . bit_h($data['responsable'] ?? '') . '</div>
                <div class="meta"><strong>Cargo:</strong> ' . bit_h($data['cargo'] ?? '') . '</div>
            </td></tr></table>
        </div>';

    $html .= bit_render_schema_sections((array) ($config['form_sections'] ?? []), $data, [], ['fechab', 'sede', 'responsable', 'cargo'], $includeLogo);
    return $html . '</body></html>';

}

function bit_register_envio(
    int $empresaId,
    string $sede,
    string $fecha,
    string $responsable,
    string $usuario,
    string $tipoFormulario,
    ?string $submissionKey = null,
    ?string $requestHash = null,
    ?PDO $pdo = null
): ?int
{
    $externalPdo = $pdo !== null;
    try {
        require_once __DIR__ . '/../bd/conexion.php';
        $pdo = $pdo ?? Conexion::Conectar();
        $stmt = $pdo->prepare('
            INSERT INTO bitacora_envios
                (idEmpresa, sede, fecha_bitacora, usuario, responsable, tipo_formulario, submission_key, request_hash)
            VALUES
                (:idEmpresa, :sede, :fecha_bitacora, :usuario, :responsable, :tipo_formulario, :submission_key, :request_hash)
        ');
        $stmt->execute([
            'idEmpresa' => $empresaId,
            'sede' => $sede,
            'fecha_bitacora' => bit_normalize_pdf_date($fecha),
            'usuario' => $usuario,
            'responsable' => $responsable !== '' ? $responsable : null,
            'tipo_formulario' => $tipoFormulario,
            'submission_key' => $submissionKey,
            'request_hash' => $requestHash,
        ]);

        return (int) $pdo->lastInsertId();
    } catch (Throwable $e) {
        if ($externalPdo) {
            throw $e;
        }
        error_log('No fue posible registrar envio de bitacora: ' . $e->getMessage());
        return null;
    }
}

function bit_update_envio(?int $envioId, array $data, ?PDO $pdo = null): void
{
    if ($envioId === null || $envioId <= 0) {
        return;
    }

    $estado = (string) ($data['estado'] ?? 'procesando');
    if (!in_array($estado, ['procesando', 'pendiente', 'completado', 'parcial', 'fallido'], true)) {
        $estado = 'procesando';
    }

    $externalPdo = $pdo !== null;
    try {
        require_once __DIR__ . '/../bd/conexion.php';
        $pdo = $pdo ?? Conexion::Conectar();
        $stmt = $pdo->prepare('
            UPDATE bitacora_envios
            SET estado = :estado,
                correo_enviado = :correo_enviado,
                pdf_generado = :pdf_generado,
                correos_seccion_enviados = :correos_seccion_enviados,
                pdf_id = :pdf_id,
                error_mensaje = :error_mensaje
            WHERE id = :id
        ');
        $stmt->execute([
            'estado' => $estado,
            'correo_enviado' => !empty($data['correo_enviado']) ? 1 : 0,
            'pdf_generado' => !empty($data['pdf_generado']) ? 1 : 0,
            'correos_seccion_enviados' => max(0, (int) ($data['correos_seccion_enviados'] ?? 0)),
            'pdf_id' => !empty($data['pdf_id']) ? (int) $data['pdf_id'] : null,
            'error_mensaje' => isset($data['error_mensaje']) && trim((string) $data['error_mensaje']) !== '' ? (string) $data['error_mensaje'] : null,
            'id' => $envioId,
        ]);
    } catch (Throwable $e) {
        if ($externalPdo) {
            throw $e;
        }
        error_log('No fue posible actualizar envio de bitacora: ' . $e->getMessage());
    }
}

function bit_mail_async_enabled(): bool
{
    return app_env_bool('BITACORA_MAIL_ASYNC', false);
}

function bit_enqueue_email(
    ?int $envioId,
    int $empresaId,
    string $sede,
    string $usuario,
    string $subject,
    string $bodyHtml,
    array $recipients,
    array $attachments = [],
    ?PDO $pdo = null,
    string $jobType = 'main'
): ?int
{
    if (!in_array($jobType, ['main', 'section'], true)) {
        throw new InvalidArgumentException('El tipo de trabajo de correo no es válido.');
    }
    $externalPdo = $pdo !== null;
    try {
        require_once __DIR__ . '/../bd/conexion.php';
        $pdo = $pdo ?? Conexion::Conectar();
        $stmt = $pdo->prepare('
            INSERT INTO bitacora_email_queue (idEnvio, idEmpresa, sede, usuario, subject, body_html, recipients_json, attachments_json, job_type)
            VALUES (:idEnvio, :idEmpresa, :sede, :usuario, :subject, :body_html, :recipients_json, :attachments_json, :job_type)
        ');
        $stmt->execute([
            'idEnvio' => $envioId,
            'idEmpresa' => $empresaId,
            'sede' => $sede,
            'usuario' => $usuario,
            'subject' => mb_substr($subject, 0, 255, 'UTF-8'),
            'body_html' => $bodyHtml,
            'recipients_json' => json_encode($recipients, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'attachments_json' => $attachments === [] ? null : json_encode($attachments, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'job_type' => $jobType,
        ]);

        return (int) $pdo->lastInsertId();
    } catch (Throwable $e) {
        if ($externalPdo) {
            throw $e;
        }
        error_log('No fue posible encolar correo de bitacora: ' . $e->getMessage());
        return null;
    }
}
