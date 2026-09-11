<?php
declare(strict_types=1);

function bit_view_options(array $options, ?string $selected = null): string
{
    $html = '<option value="">Seleccionar...</option>';
    foreach ($options as $value => $label) {
        if (is_int($value)) {
            $value = $label;
        }
        $isSelected = ((string) $value === (string) $selected) ? ' selected' : '';
        $html .= '<option value="' . app_h($value) . '"' . $isSelected . '>' . app_h($label) . '</option>';
    }
    return $html;
}

function bit_view_sede_attr(array $field): string
{
    $sedes = array_filter((array) ($field['sedes'] ?? []));
    if ($sedes === []) {
        return '';
    }
    return ' data-sede="' . app_h(implode(',', $sedes)) . '"';
}

function bit_view_dynamic_attr(array $field): string
{
    return !empty($field['dynamic']) ? ' data-dynamic-field="1"' : '';
}

function bit_view_wrapper_start(array $field, string $fallbackCol = 'col-md-6'): void
{
    $col = $field['col'] ?? $fallbackCol;
    $hiddenClass = !empty($field['sedes']) ? ' bit-initial-hidden' : '';
    echo '<div class="form-group bit-field ' . app_h($col) . $hiddenClass . '"' . bit_view_sede_attr($field) . '>';
}

function bit_view_label(string $for, string $label, bool $required): void
{
    echo '<label class="bit-label" for="' . app_h($for) . '">' . app_h($label) . ($required ? ' <span class="text-danger">*</span>' : '') . '</label>';
}

function bit_view_control_attrs(array $field, string $name = ''): string
{
    $name = $name !== '' ? $name : (string) ($field['name'] ?? '');
    return ' name="' . app_h($name) . '"' . bit_view_sede_attr($field) . bit_view_dynamic_attr($field) . (!empty($field['required']) ? ' required' : '');
}

function bit_view_number_attrs(array $field): string
{
    $attrs = ' step="' . app_h((string) ($field['step'] ?? 'any')) . '"';
    if (array_key_exists('min', $field)) {
        $attrs .= ' min="' . app_h((string) $field['min']) . '"';
    }
    if (array_key_exists('max', $field)) {
        $attrs .= ' max="' . app_h((string) $field['max']) . '"';
    }
    return $attrs;
}

function bit_view_maxlength_attr(array $field, int $default): string
{
    $maxLength = (int) ($field['max_length'] ?? $default);
    return $maxLength > 0 ? ' maxlength="' . app_h((string) $maxLength) . '"' : '';
}

function bit_view_input_type_attrs(array $field, string $type): string
{
    if ($type === 'number') {
        return bit_view_number_attrs($field);
    }
    if ($type === 'text') {
        return bit_view_maxlength_attr($field, 500);
    }
    return '';
}

function bit_view_simple_input(array $field, string $type): void
{
    $name = (string) $field['name'];
    bit_view_wrapper_start($field, $type === 'number' ? 'col-md-3' : 'col-md-6');
    bit_view_label($name, (string) $field['label'], (bool) ($field['required'] ?? false));
    echo '<input type="' . app_h($type) . '" class="form-control bit-input" id="' . app_h($name) . '"' . bit_view_input_type_attrs($field, $type) . bit_view_control_attrs($field) . '>';
    echo '</div>';
}

function bit_view_textarea_field(array $field): void
{
    $name = (string) $field['name'];
    bit_view_wrapper_start($field);
    bit_view_label($name, (string) $field['label'], (bool) ($field['required'] ?? false));
    echo '<textarea class="form-control bit-input" rows="4" id="' . app_h($name) . '"' . bit_view_maxlength_attr($field, 10000) . bit_view_control_attrs($field) . '></textarea>';
    echo '</div>';
}

function bit_view_select_field(array $field): void
{
    $name = (string) $field['name'];
    $id = (string) ($field['id'] ?? $name);
    bit_view_wrapper_start($field);
    bit_view_label($id, (string) $field['label'], (bool) ($field['required'] ?? false));
    echo '<select id="' . app_h($id) . '" class="form-control bit-input"' . bit_view_control_attrs($field) . '>';
    echo bit_view_options((array) ($field['options'] ?? []), isset($field['selected']) ? (string) $field['selected'] : null);
    echo '</select></div>';
}

function bit_view_multiselect_field(array $field): void
{
    $name = (string) $field['name'];
    $id = (string) ($field['id'] ?? $name);
    bit_view_wrapper_start($field);
    bit_view_label($id, (string) $field['label'], (bool) ($field['required'] ?? false));
    echo '<select id="' . app_h($id) . '" class="form-control bit-input select2-field"' . bit_view_control_attrs($field, $name . '[]') . ' multiple>';
    foreach ((array) ($field['options'] ?? []) as $value => $label) {
        if (is_int($value)) {
            $value = $label;
        }
        echo '<option value="' . app_h($value) . '">' . app_h($label) . '</option>';
    }
    echo '</select></div>';
}

function bit_view_multiselect_detail_group_field(array $field): void
{
    $name = (string) $field['name'];
    $id = (string) ($field['id'] ?? $name);
    $detailName = (string) ($field['detail_name'] ?? ($name . '_detalles'));
    $noApply = (string) ($field['no_apply_value'] ?? 'No aplica visita');
    $placeholder = (string) ($field['placeholder'] ?? 'Escribe Nombre Apellido - Cargo');
    $examples = array_filter((array) ($field['examples'] ?? []), static fn($value) => trim((string) $value) !== '');

    bit_view_wrapper_start($field, 'col-md-12');
    bit_view_label($id, (string) $field['label'], (bool) ($field['required'] ?? false));
    if (!empty($field['help'])) {
        echo '<small class="form-text text-muted bit-availability-message">' . app_h((string) $field['help']) . '</small>';
    }
    if ($examples !== []) {
        echo '<small class="form-text text-muted bit-availability-message">Cargos sugeridos: ' . app_h(implode(', ', $examples)) . '.</small>';
    }

    echo '<select id="' . app_h($id) . '" class="form-control bit-input select2-field bit-multiselect-detail-select" name="' . app_h($name) . '[]" multiple data-dynamic-field="1" data-detail-name="' . app_h($detailName) . '" data-no-apply="' . app_h($noApply) . '" data-placeholder="' . app_h($placeholder) . '"' . (!empty($field['required']) ? ' required' : '') . '>';
    foreach ((array) ($field['options'] ?? []) as $value => $label) {
        if (is_int($value)) {
            $value = $label;
        }
        echo '<option value="' . app_h($value) . '">' . app_h($label) . '</option>';
    }
    echo '</select>';
    echo '<div class="bit-detail-panel bit-multiselect-detail-panel bit-initial-hidden" id="' . app_h($id) . '_detalles"></div>';
    echo '</div>';
}

function bit_view_yes_no_field(array $field): void
{
    $name = (string) $field['name'];
    $detailName = (string) ($field['detail_name'] ?? ($name . '_detalle'));
    $groupId = (string) ($field['group_id'] ?? ($detailName . 'Group'));
    $detailType = (string) ($field['detail_type'] ?? 'textarea');
    $detailDefaultFrom = trim((string) ($field['detail_default_from'] ?? ''));
    $detailDefaultAttr = $detailDefaultFrom !== '' ? ' data-default-from="' . app_h($detailDefaultFrom) . '"' : '';

    bit_view_wrapper_start($field);
    echo '<label class="bit-label">' . app_h((string) $field['label']) . ' <span class="text-danger">*</span></label>';
    echo '<div class="bit-radio-group"><label class="bit-radio-pill"><input type="radio" value="Si" data-toggle-detail="#' . app_h($groupId) . '"' . bit_view_control_attrs($field) . '> Si</label>';
    echo '<label class="bit-radio-pill"><input type="radio" value="No" data-toggle-detail="#' . app_h($groupId) . '"' . bit_view_control_attrs($field) . '> No</label></div>';
    echo '<div class="bit-detail-panel bit-initial-hidden" id="' . app_h($groupId) . '">';
    bit_view_label($detailName, (string) ($field['detail_label'] ?? 'Detalle'), false);
    if ($detailType === 'multiselect') {
        echo '<select class="form-control bit-input select2-field" id="' . app_h($detailName) . '" name="' . app_h($detailName) . '[]" multiple' . bit_view_dynamic_attr($field) . '>';
        foreach ((array) ($field['detail_options'] ?? []) as $value => $label) {
            if (is_int($value)) {
                $value = $label;
            }
            echo '<option value="' . app_h($value) . '">' . app_h($label) . '</option>';
        }
        echo '</select>';
    } elseif ($detailType === 'number') {
        echo '<input class="form-control bit-input" type="number" step="any" id="' . app_h($detailName) . '" name="' . app_h($detailName) . '"' . bit_view_dynamic_attr($field) . '>';
    } elseif ($detailType === 'date') {
        echo '<input class="form-control bit-input" type="date" id="' . app_h($detailName) . '" name="' . app_h($detailName) . '"' . $detailDefaultAttr . bit_view_dynamic_attr($field) . '>';
    } else {
        echo '<textarea class="form-control bit-input" rows="3" id="' . app_h($detailName) . '" name="' . app_h($detailName) . '"' . bit_view_dynamic_attr($field) . '></textarea>';
    }
    echo '</div></div>';
}

function bit_view_simple_radio_field(array $field): void
{
    $name = (string) $field['name'];
    bit_view_wrapper_start($field, 'col-md-3');
    echo '<label class="bit-label">' . app_h((string) $field['label']) . ' <span class="text-danger">*</span></label>';
    echo '<div class="bit-radio-group"><label class="bit-radio-pill"><input type="radio" value="Si"' . bit_view_control_attrs($field) . '> Si</label>';
    echo '<label class="bit-radio-pill"><input type="radio" value="No"' . bit_view_control_attrs($field) . '> No</label></div>';
    echo '</div>';
}

function bit_view_supervisor_detail_field(array $field): void
{
    echo '<div id="contenedor_sup" class="form-group bit-field col-md-6 bit-initial-hidden">';
    echo '<label class="bit-label">' . app_h((string) $field['label']) . '</label>';
    echo '<div class="form-row">';
    echo '<div class="form-group bit-field col-md-6"><input class="form-control bit-input" type="time" id="hora_entrada" name="hora_entrada"></div>';
    echo '<div class="form-group bit-field col-md-6"><input class="form-control bit-input" type="time" id="hora_salida" name="hora_salida"></div>';
    echo '<div class="form-group bit-field col-md-12"><textarea class="form-control bit-input" rows="3" id="act_sup" name="act_sup" placeholder="Actividades realizadas"></textarea></div>';
    echo '</div></div>';
}

function bit_view_conditional_textarea_field(array $field): void
{
    $name = (string) $field['name'];
    $containerId = (string) ($field['container_id'] ?? ('contenedor_' . $name));
    echo '<div id="' . app_h($containerId) . '" class="form-group bit-field col-md-6 bit-initial-hidden">';
    bit_view_label($name, (string) $field['label'], false);
    echo '<textarea class="form-control bit-input" rows="3" id="' . app_h($name) . '" name="' . app_h($name) . '"' . bit_view_maxlength_attr($field, 10000) . bit_view_dynamic_attr($field) . '></textarea>';
    echo '</div>';
}

function bit_view_plant_field(array $field): void
{
    $required = !empty($field['required']);
    $requiredAttr = $required ? ' required' : '';
    bit_view_wrapper_start($field);
    echo '<label class="bit-label">' . app_h((string) $field['label']) . ($required ? ' <span class="text-danger">*</span>' : '') . '</label>';
    echo '<div class="bit-radio-group"><label class="bit-radio-pill"><input type="radio" name="planta_elect" value="Si"' . $requiredAttr . '> Si</label><label class="bit-radio-pill"><input type="radio" name="planta_elect" value="No"' . $requiredAttr . '> No</label></div>';
    echo '<div class="bit-detail-panel bit-initial-hidden" id="plantaGroup">';
    echo '<label class="bit-label" for="mant5">Hora encendido</label>';
    echo '<input class="form-control bit-input mb-2" type="time" id="mant5" name="mant5" required>';
    echo '<label class="bit-label" for="mant6">Hora apagado</label>';
    echo '<input class="form-control bit-input mb-2" type="time" id="mant6" name="mant6" required>';
    echo '<label class="bit-label" for="mant7">Tiempo de uso (minutos)</label>';
    echo '<input class="form-control bit-input mb-2" type="number" id="mant7" name="mant7" placeholder="Calculado automáticamente" readonly>';
    echo '</div></div>';
}

function bit_view_dependent_control(array $field, string $name, string $id, string $extraClass = ''): void
{
    $type = (string) ($field['type'] ?? 'text');
    $required = !empty($field['required']) ? ' data-required="1"' : '';

    if ($type === 'simple_radio') {
        echo '<div class="bit-radio-group">';
        foreach (['Si', 'No'] as $value) {
            $radioId = $id . '_' . strtolower($value);
            echo '<label class="bit-radio-pill" for="' . app_h($radioId) . '">';
            echo '<input type="radio" id="' . app_h($radioId) . '" name="' . app_h($name) . '" class="bit-human-dependent" value="' . app_h($value) . '" data-dynamic-field="1"' . $required . ' disabled> ' . app_h($value);
            echo '</label>';
        }
        echo '</div>';
        return;
    }

    $class = trim('form-control bit-input bit-human-dependent ' . $extraClass);
    $baseAttrs = ' id="' . app_h($id) . '" name="' . app_h($name) . '" class="' . app_h($class) . '" data-dynamic-field="1"' . $required . ' disabled';

    if ($type === 'select') {
        echo '<select' . $baseAttrs . '>' . bit_view_options((array) ($field['options'] ?? [])) . '</select>';
        return;
    }

    if ($type === 'textarea') {
        echo '<textarea rows="3"' . bit_view_maxlength_attr($field, 10000) . $baseAttrs . '></textarea>';
        return;
    }

    $inputType = in_array($type, ['number', 'date', 'time'], true) ? $type : 'text';
    echo '<input type="' . app_h($inputType) . '"' . $baseAttrs . bit_view_input_type_attrs($field, $inputType) . '>';
}

function bit_view_yes_no_quantity_group_field(array $field): void
{
    $name = (string) $field['name'];
    $quantityName = (string) ($field['quantity_name'] ?? ($name . '_cantidad'));
    $min = max(1, (int) ($field['min'] ?? 1));
    $max = max($min, min(10, (int) ($field['max'] ?? 10)));
    $col = $field['col'] ?? 'col-md-12';
    $weekdayAttr = array_key_exists('weekday_only', $field) ? ' data-weekday-only="' . app_h((string) $field['weekday_only']) . '"' : '';
    $requiredAttr = !empty($field['required']) ? ' data-required="1"' : '';
    $sedeAttr = bit_view_sede_attr($field);
    $hiddenClass = !empty($field['sedes']) ? ' bit-initial-hidden' : '';
    $message = (string) ($field['availability_message'] ?? '');

    echo '<div class="form-group bit-field ' . app_h($col) . $hiddenClass . ' bit-quantity-group" data-group-name="' . app_h($name) . '"' . $weekdayAttr . $requiredAttr . $sedeAttr . '>';
    echo '<label class="bit-label">' . app_h((string) $field['label']) . (!empty($field['required']) ? ' <span class="text-danger">*</span>' : '') . '</label>';
    if ($message !== '') {
        echo '<small class="form-text text-muted bit-availability-message">' . app_h($message) . '</small>';
    }
    echo '<div class="bit-radio-group">';
    echo '<label class="bit-radio-pill"><input type="radio" class="bit-human-toggle" name="' . app_h($name) . '" value="Si" data-dynamic-field="1"> Si</label>';
    echo '<label class="bit-radio-pill"><input type="radio" class="bit-human-toggle" name="' . app_h($name) . '" value="No" data-dynamic-field="1"> No</label>';
    echo '</div>';
    echo '<div class="bit-detail-panel bit-quantity-panel bit-initial-hidden">';
    bit_view_label($quantityName, (string) ($field['quantity_label'] ?? 'Cantidad'), true);
    echo '<input type="number" class="form-control bit-input bit-quantity-input bit-human-dependent" id="' . app_h($quantityName) . '" name="' . app_h($quantityName) . '" min="' . app_h((string) $min) . '" max="' . app_h((string) $max) . '" step="1" data-required="1" data-dynamic-field="1" disabled>';

    bit_view_quantity_group_items($field, $name, $max);

    echo '</div></div>';
}

function bit_view_quantity_group_items(array $field, string $name, int $max): void
{
    foreach (range(1, $max) as $index) {
        echo '<div class="bit-repeat-item bit-quantity-item bit-initial-hidden" data-item-index="' . app_h((string) $index) . '">';
        echo '<div class="bit-repeat-title">' . app_h((string) ($field['item_label'] ?? 'Registro')) . ' ' . app_h((string) $index) . '</div>';
        echo '<div class="form-row">';
        foreach ((array) ($field['fields'] ?? []) as $itemField) {
            $itemFieldName = (string) ($itemField['name'] ?? '');
            if ($itemFieldName === '') {
                continue;
            }
            $controlName = app_bitacora_group_item_field_name($name, $index, $itemFieldName);
            $controlId = $controlName;
            $controlType = (string) ($itemField['type'] ?? 'text');
            $controlCol = (string) ($itemField['col'] ?? ($controlType === 'textarea' ? 'col-md-12' : 'col-md-6'));
            echo '<div class="form-group bit-field ' . app_h($controlCol) . '">';
            bit_view_label($controlId, (string) ($itemField['label'] ?? $itemFieldName), (bool) ($itemField['required'] ?? false));
            bit_view_dependent_control($itemField, $controlName, $controlId);
            echo '</div>';
        }
        echo '</div></div>';
    }
}

function bit_view_quantity_group_field(array $field): void
{
    $name = (string) $field['name'];
    $quantityName = (string) ($field['quantity_name'] ?? ($name . '_cantidad'));
    $max = max(1, min(10, (int) ($field['max'] ?? 10)));
    $col = $field['col'] ?? 'col-md-12';
    $weekdayAttr = array_key_exists('weekday_only', $field) ? ' data-weekday-only="' . app_h((string) $field['weekday_only']) . '"' : '';
    $sedeAttr = bit_view_sede_attr($field);
    $hiddenClass = !empty($field['sedes']) ? ' bit-initial-hidden' : '';
    $message = (string) ($field['availability_message'] ?? '');

    echo '<div class="form-group bit-field ' . app_h($col) . $hiddenClass . ' bit-direct-quantity-group" data-group-name="' . app_h($name) . '"' . $weekdayAttr . $sedeAttr . '>';
    echo '<label class="bit-label">' . app_h((string) $field['label']) . ' <span class="text-danger">*</span></label>';
    if ($message !== '') {
        echo '<small class="form-text text-muted bit-availability-message">' . app_h($message) . '</small>';
    }
    echo '<div class="bit-detail-panel bit-direct-quantity-panel">';
    bit_view_label($quantityName, (string) ($field['quantity_label'] ?? 'Cantidad'), true);
    echo '<input type="number" class="form-control bit-input bit-direct-quantity-input" id="' . app_h($quantityName) . '" name="' . app_h($quantityName) . '" min="0" max="' . app_h((string) $max) . '" step="1" required data-dynamic-field="1">';
    bit_view_quantity_group_items($field, $name, $max);
    echo '</div></div>';
}

function bit_view_yes_no_detail_group_field(array $field): void
{
    $name = (string) $field['name'];
    $col = $field['col'] ?? 'col-md-12';
    $requiredAttr = !empty($field['required']) ? ' data-required="1"' : '';

    echo '<div class="form-group bit-field ' . app_h($col) . ' bit-detail-group" data-group-name="' . app_h($name) . '"' . $requiredAttr . '>';
    echo '<label class="bit-label">' . app_h((string) $field['label']) . (!empty($field['required']) ? ' <span class="text-danger">*</span>' : '') . '</label>';
    echo '<div class="bit-radio-group">';
    echo '<label class="bit-radio-pill"><input type="radio" class="bit-human-toggle" name="' . app_h($name) . '" value="Si" data-dynamic-field="1"> Si</label>';
    echo '<label class="bit-radio-pill"><input type="radio" class="bit-human-toggle" name="' . app_h($name) . '" value="No" data-dynamic-field="1"> No</label>';
    echo '</div>';
    echo '<div class="bit-detail-panel bit-detail-group-panel bit-initial-hidden">';
    echo '<div class="form-row">';
    foreach ((array) ($field['fields'] ?? []) as $detailField) {
        $detailFieldName = (string) ($detailField['name'] ?? '');
        if ($detailFieldName === '') {
            continue;
        }
        $controlName = app_bitacora_detail_group_field_name($name, $detailFieldName);
        $controlId = $controlName;
        $controlType = (string) ($detailField['type'] ?? 'text');
        $controlCol = (string) ($detailField['col'] ?? ($controlType === 'textarea' ? 'col-md-12' : 'col-md-6'));
        echo '<div class="form-group bit-field ' . app_h($controlCol) . '">';
        bit_view_label($controlId, (string) ($detailField['label'] ?? $detailFieldName), (bool) ($detailField['required'] ?? false));
        bit_view_dependent_control($detailField, $controlName, $controlId);
        echo '</div>';
    }
    echo '</div></div></div>';
}

function bit_view_yes_no_branch_group_field(array $field): void
{
    $name = (string) ($field['name'] ?? '');
    $col = (string) ($field['col'] ?? 'col-md-12');
    $requiredAttr = !empty($field['required']) ? ' data-required="1"' : '';
    $sedeAttr = bit_view_sede_attr($field);
    $hiddenClass = !empty($field['sedes']) ? ' bit-initial-hidden' : '';

    echo '<div class="form-group bit-field ' . app_h($col) . $hiddenClass . ' bit-branch-group" data-group-name="' . app_h($name) . '"' . $requiredAttr . $sedeAttr . '>';
    echo '<label class="bit-label bit-special-label">' . app_h((string) ($field['label'] ?? $name)) . (!empty($field['required']) ? ' <span class="text-danger">*</span>' : '') . '</label>';
    echo '<div class="bit-radio-group">';
    echo '<label class="bit-radio-pill"><input type="radio" class="bit-human-toggle" name="' . app_h($name) . '" value="Si" data-dynamic-field="1"> Si</label>';
    echo '<label class="bit-radio-pill"><input type="radio" class="bit-human-toggle" name="' . app_h($name) . '" value="No" data-dynamic-field="1"> No</label>';
    echo '</div>';

    foreach (['si' => 'si_fields', 'no' => 'no_fields'] as $branch => $branchKey) {
        echo '<div class="bit-detail-panel bit-branch-panel bit-initial-hidden" data-branch="' . app_h($branch) . '">';
        echo '<div class="form-row">';
        foreach ((array) ($field[$branchKey] ?? []) as $branchField) {
            $branchFieldName = (string) ($branchField['name'] ?? '');
            if ($branchFieldName === '') {
                continue;
            }

            $controlName = app_bitacora_branch_group_field_name($name, $branch, $branchFieldName);
            $controlId = $controlName;
            $controlType = (string) ($branchField['type'] ?? 'text');
            $controlCol = (string) ($branchField['col'] ?? ($controlType === 'textarea' ? 'col-md-12' : 'col-md-6'));
            echo '<div class="form-group bit-field ' . app_h($controlCol) . '">';
            bit_view_label($controlId, (string) ($branchField['label'] ?? $branchFieldName), (bool) ($branchField['required'] ?? false));
            bit_view_dependent_control($branchField, $controlName, $controlId);
            echo '</div>';
        }
        echo '</div></div>';
    }

    echo '</div>';
}

function bit_view_subsection(array $field): void
{
    $hiddenClass = !empty($field['sedes']) ? ' bit-initial-hidden' : '';
    echo '<div class="col-12 bit-subsection' . $hiddenClass . '"' . bit_view_sede_attr($field) . '>';
    echo '<h4>' . app_h((string) ($field['label'] ?? '')) . '</h4>';
    $description = trim((string) ($field['description'] ?? ''));
    if ($description !== '') {
        echo '<p>' . nl2br(app_h($description)) . '</p>';
    }
    echo '</div>';
}

function bit_view_render_field(array $field): void
{
    switch ((string) ($field['type'] ?? 'text')) {
        case 'subsection':
            bit_view_subsection($field);
            break;
        case 'date':
        case 'time':
        case 'text':
        case 'number':
            bit_view_simple_input($field, (string) $field['type']);
            break;
        case 'textarea':
            bit_view_textarea_field($field);
            break;
        case 'select':
            bit_view_select_field($field);
            break;
        case 'multiselect':
            bit_view_multiselect_field($field);
            break;
        case 'multiselect_detail_group':
            bit_view_multiselect_detail_group_field($field);
            break;
        case 'yes_no':
            bit_view_yes_no_field($field);
            break;
        case 'simple_radio':
            bit_view_simple_radio_field($field);
            break;
        case 'supervisor_detail':
            bit_view_supervisor_detail_field($field);
            break;
        case 'conditional_textarea':
            bit_view_conditional_textarea_field($field);
            break;
        case 'plant':
            bit_view_plant_field($field);
            break;
        case 'yes_no_quantity_group':
            bit_view_yes_no_quantity_group_field($field);
            break;
        case 'quantity_group':
            bit_view_quantity_group_field($field);
            break;
        case 'yes_no_detail_group':
            bit_view_yes_no_detail_group_field($field);
            break;
        case 'yes_no_branch_group':
            bit_view_yes_no_branch_group_field($field);
            break;
    }
}

function bit_view_section_schema(array $section): void
{
    static $sectionIndex = 0;
    $sectionIndex++;
    $sedes = array_filter((array) ($section['sedes'] ?? []));
    $attr = $sedes === [] ? '' : ' data-sede="' . app_h(implode(',', $sedes)) . '"';
    $hiddenClass = $sedes === [] ? '' : ' bit-initial-hidden';
    echo '<div class="col-12' . $hiddenClass . '"' . $attr . '><div class="bit-section-heading"><span class="bit-section-index">' . app_h((string) $sectionIndex) . '</span><h3>' . app_h((string) ($section['title'] ?? '')) . '</h3></div></div>';
}

function bit_view_operational_form(array $config, int $empresaId): void
{
    $sections = app_bitacora_form_sections($empresaId, $config);
    ?>
    <form method="post" action="../scripts/send_bitacora.php" class="form-bitacora" autocomplete="off">
        <?php echo app_csrf_input(); ?>
        <input type="hidden" name="empresa_id" value="<?php echo app_h((string) $empresaId); ?>">
        <div class="form-row">
            <?php foreach ($sections as $section): ?>
                <?php bit_view_section_schema($section); ?>
                <?php foreach ((array) ($section['fields'] ?? []) as $field): ?>
                    <?php bit_view_render_field($field); ?>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>

        <div class="bit-actions">
            <button id="bit_draft_restore" type="button" class="bit-btn-ghost" hidden>Cargar/Restaurar</button>
            <button id="bit_draft_delete" type="button" class="bit-btn-ghost" hidden>Eliminar borrador</button>
            <button id="bit_draft_save" type="button" class="bit-btn-secondary">Guardar ahora</button>
            <button id="generar_pdf" type="button" class="bit-btn-pdf" data-default-text="Generar PDF">Generar PDF</button>
            <button id="boton" type="submit" class="bit-btn-primary" data-default-text="Enviar bitácora">Enviar bitácora</button>
        </div>
    </form>
    <?php
}

function bit_view_supervision_form(array $config, int $empresaId): void
{
    $sections = app_bitacora_form_sections($empresaId, $config);
    ?>
    <form method="post" action="../scripts/send_bitacora.php" class="form-bitacora" autocomplete="off">
        <?php echo app_csrf_input(); ?>
        <input type="hidden" name="empresa_id" value="<?php echo app_h((string) $empresaId); ?>">
        <div class="form-row">
            <?php foreach ($sections as $section): ?>
                <?php bit_view_section_schema($section); ?>
                <?php foreach ((array) ($section['fields'] ?? []) as $field): ?>
                    <?php bit_view_render_field($field); ?>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
        <div class="bit-actions">
            <button id="bit_draft_restore" type="button" class="bit-btn-ghost" hidden>Cargar/Restaurar</button>
            <button id="bit_draft_delete" type="button" class="bit-btn-ghost" hidden>Eliminar borrador</button>
            <button id="bit_draft_save" type="button" class="bit-btn-secondary">Guardar ahora</button>
            <button id="boton" type="submit" class="bit-btn-primary" data-default-text="Enviar reporte">Enviar reporte</button>
        </div>
    </form>
    <?php
}
