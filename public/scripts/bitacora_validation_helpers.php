<?php
declare(strict_types=1);

function bit_allowed_select_values(array $options): array
{
    $allowed = [];
    foreach ($options as $value => $label) {
        $allowed[] = is_int($value) ? (string) $label : (string) $value;
    }
    return $allowed;
}

function bit_valid_date_value(string $value): bool
{
    foreach (['Y-m-d', 'd-m-Y'] as $format) {
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
        if ($date instanceof DateTimeImmutable && $date->format($format) === $value) {
            return true;
        }
    }

    return false;
}

function bit_valid_time_value(string $value): bool
{
    return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) === 1;
}

function bit_validate_configured_value(array $field, string $name, string $label): array
{
    $type = (string) ($field['type'] ?? 'text');
    $required = (bool) ($field['required'] ?? false);
    $value = trim((string) ($_POST[$name] ?? ''));

    if ($required && $value === '') {
        return [false, 'El campo "' . $label . '" es obligatorio.'];
    }

    if ($value === '') {
        return [true, ''];
    }

    if (in_array($type, ['yes_no', 'simple_radio'], true) && !in_array($value, ['Si', 'No'], true)) {
        return [false, 'El campo "' . $label . '" debe ser Si o No.'];
    }

    if ($type === 'number') {
        $numericValue = str_replace(',', '.', $value);
        if (!is_numeric($numericValue)) {
            return [false, 'El campo "' . $label . '" debe ser numérico.'];
        }

        $numericValue = (float) $numericValue;
        if (array_key_exists('min', $field) && $numericValue < (float) $field['min']) {
            return [false, 'El campo "' . $label . '" debe ser mayor o igual a ' . $field['min'] . '.'];
        }
        if (array_key_exists('max', $field) && $numericValue > (float) $field['max']) {
            return [false, 'El campo "' . $label . '" debe ser menor o igual a ' . $field['max'] . '.'];
        }
    }

    if ($type === 'date' && !bit_valid_date_value($value)) {
        return [false, 'El campo "' . $label . '" debe ser una fecha válida.'];
    }

    if ($type === 'time' && !bit_valid_time_value($value)) {
        return [false, 'El campo "' . $label . '" debe ser una hora válida.'];
    }

    if ($type === 'select') {
        $allowed = bit_allowed_select_values((array) ($field['options'] ?? []));
        if ($allowed !== [] && !in_array($value, $allowed, true)) {
            return [false, 'El campo "' . $label . '" tiene una opción inválida.'];
        }
    }

    if (in_array($type, ['text', 'textarea'], true)) {
        $maxLength = (int) ($field['max_length'] ?? ($type === 'textarea' ? 10000 : 500));
        if ($maxLength > 0 && mb_strlen($value, 'UTF-8') > $maxLength) {
            return [false, 'El campo "' . $label . '" excede la longitud máxima permitida.'];
        }
    }

    return [true, ''];
}

function bit_validate_sede(array $companyConfig, string $sede): array
{
    if ($sede === '') {
        return [false, 'La sede es obligatoria.'];
    }

    $allowed = array_map('strval', (array) ($companyConfig['sedes'] ?? []));
    if ($allowed !== [] && !in_array($sede, $allowed, true)) {
        return [false, 'La sede seleccionada no pertenece a la empresa activa.'];
    }

    return [true, ''];
}

function bit_validate_yes_no_schema_field(array $field): array
{
    $name = (string) ($field['name'] ?? '');
    $label = (string) ($field['label'] ?? $name);
    $required = (bool) ($field['required'] ?? false);
    $answer = trim((string) ($_POST[$name] ?? ''));

    if ($required && !in_array($answer, ['Si', 'No'], true)) {
        return [false, 'El campo "' . $label . '" debe ser Si o No.'];
    }

    if ($answer === '' || $answer === 'No') {
        return [true, ''];
    }

    if ($answer !== 'Si') {
        return [false, 'El campo "' . $label . '" debe ser Si o No.'];
    }

    $detailName = (string) ($field['detail_name'] ?? '');
    if ($detailName === '') {
        return [true, ''];
    }

    $detailLabel = (string) ($field['detail_label'] ?? ('Detalle de ' . $label));
    $detailType = (string) ($field['detail_type'] ?? 'textarea');
    if ($detailType === 'multiselect') {
        return bit_validate_multiselect_schema_field([
            'name' => $detailName,
            'label' => $detailLabel,
            'required' => true,
            'options' => $field['detail_options'] ?? [],
        ]);
    }

    $detailField = [
        'type' => $detailType,
        'required' => true,
    ];
    return bit_validate_configured_value($detailField, $detailName, $detailLabel);
}

function bit_validate_plant_schema_field(array $field): array
{
    $name = (string) ($field['name'] ?? 'planta_elect');
    $label = (string) ($field['label'] ?? $name);
    $required = (bool) ($field['required'] ?? false);
    $answer = trim((string) ($_POST[$name] ?? ''));

    if ($required && !in_array($answer, ['Si', 'No'], true)) {
        return [false, 'El campo "' . $label . '" debe ser Si o No.'];
    }

    if ($answer !== 'Si') {
        return [true, ''];
    }

    foreach ([
        ['name' => 'mant5', 'label' => 'Hora encendido', 'type' => 'time'],
        ['name' => 'mant6', 'label' => 'Hora apagado', 'type' => 'time'],
    ] as $detailField) {
        [$valid, $message] = bit_validate_configured_value([
            'type' => $detailField['type'],
            'required' => true,
        ], $detailField['name'], $detailField['label']);
        if (!$valid) {
            return [false, $message];
        }
    }

    return [true, ''];
}

function bit_validate_multiselect_schema_field(array $field): array
{
    $name = (string) ($field['name'] ?? '');
    $label = (string) ($field['label'] ?? $name);
    $selected = bit_selected_values($_POST[$name] ?? []);

    if (!empty($field['required']) && $selected === []) {
        return [false, 'El campo "' . $label . '" es obligatorio.'];
    }

    $allowed = bit_allowed_select_values((array) ($field['options'] ?? []));
    if ($allowed !== []) {
        foreach ($selected as $value) {
            if (!in_array($value, $allowed, true)) {
                return [false, 'El campo "' . $label . '" tiene una opción inválida.'];
            }
        }
    }

    return [true, ''];
}

function bit_validate_schema_fields(array $sections, string $sede = ''): array
{
    $groupTypes = ['yes_no_quantity_group', 'quantity_group', 'yes_no_detail_group', 'yes_no_branch_group', 'multiselect_detail_group'];

    foreach ($sections as $section) {
        foreach ((array) ($section['fields'] ?? []) as $field) {
            if (!app_bitacora_field_visible_for_sede($field, $sede)) {
                continue;
            }

            $type = (string) ($field['type'] ?? 'text');
            if (app_bitacora_field_is_presentational($field)) {
                continue;
            }
            if (in_array($type, $groupTypes, true)) {
                continue;
            }

            if ($type === 'yes_no' || $type === 'simple_radio') {
                [$valid, $message] = bit_validate_yes_no_schema_field($field);
            } elseif ($type === 'plant') {
                [$valid, $message] = bit_validate_plant_schema_field($field);
            } elseif ($type === 'multiselect') {
                [$valid, $message] = bit_validate_multiselect_schema_field($field);
            } else {
                $name = (string) ($field['name'] ?? '');
                if ($name === '') {
                    continue;
                }
                $label = (string) ($field['label'] ?? $name);
                [$valid, $message] = bit_validate_configured_value($field, $name, $label);
            }

            if (!$valid) {
                return [false, $message];
            }
        }
    }

    return [true, ''];
}

function bit_validate_quantity_groups(array $groups, string $fecha): array
{
    foreach ($groups as $group) {
        if (!app_bitacora_field_available_for_date($group, $fecha)) {
            continue;
        }

        $name = (string) ($group['name'] ?? '');
        $label = (string) ($group['label'] ?? $name);
        $answer = trim((string) ($_POST[$name] ?? ''));
        $required = (bool) ($group['required'] ?? false);

        if ($required && !in_array($answer, ['Si', 'No'], true)) {
            return [false, 'El campo "' . $label . '" debe ser Si o No.'];
        }

        if ($answer !== 'Si') {
            continue;
        }

        $quantityName = (string) ($group['quantity_name'] ?? ($name . '_cantidad'));
        $quantityLabel = (string) ($group['quantity_label'] ?? 'Cantidad');
        $rawQuantity = trim((string) ($_POST[$quantityName] ?? ''));
        $min = max(1, (int) ($group['min'] ?? 1));
        $max = max($min, min(10, (int) ($group['max'] ?? 10)));

        if (!preg_match('/^\d+$/', $rawQuantity)) {
            return [false, 'El campo "' . $quantityLabel . '" debe ser un número entero entre ' . $min . ' y ' . $max . '.'];
        }

        $quantity = (int) $rawQuantity;
        if ($quantity < $min || $quantity > $max) {
            return [false, 'El campo "' . $quantityLabel . '" debe estar entre ' . $min . ' y ' . $max . '.'];
        }

        foreach (range(1, $quantity) as $index) {
            foreach ((array) ($group['fields'] ?? []) as $itemField) {
                $itemFieldName = (string) ($itemField['name'] ?? '');
                if ($itemFieldName === '') {
                    continue;
                }

                $inputName = app_bitacora_group_item_field_name($name, $index, $itemFieldName);
                $inputLabel = $label . ' - ' . (string) ($group['item_label'] ?? 'Registro') . ' ' . $index . ' - ' . (string) ($itemField['label'] ?? $itemFieldName);
                [$valid, $message] = bit_validate_configured_value($itemField, $inputName, $inputLabel);
                if (!$valid) {
                    return [false, $message];
                }
            }
        }
    }

    return [true, ''];
}

function bit_validate_direct_quantity_groups(array $groups, string $fecha): array
{
    foreach ($groups as $group) {
        if (!app_bitacora_field_available_for_date($group, $fecha)) {
            continue;
        }

        $name = (string) ($group['name'] ?? '');
        $label = (string) ($group['label'] ?? $name);
        $quantityName = (string) ($group['quantity_name'] ?? ($name . '_cantidad'));
        $quantityLabel = (string) ($group['quantity_label'] ?? 'Cantidad');
        $rawQuantity = trim((string) ($_POST[$quantityName] ?? ''));
        $max = max(1, min(10, (int) ($group['max'] ?? 10)));

        if (!preg_match('/^\d+$/', $rawQuantity)) {
            return [false, 'El campo "' . $quantityLabel . '" debe ser un número entero entre 0 y ' . $max . '.'];
        }

        $quantity = (int) $rawQuantity;
        if ($quantity > $max) {
            return [false, 'El campo "' . $quantityLabel . '" debe estar entre 0 y ' . $max . '.'];
        }

        foreach ($quantity > 0 ? range(1, $quantity) : [] as $index) {
            foreach ((array) ($group['fields'] ?? []) as $itemField) {
                $itemFieldName = (string) ($itemField['name'] ?? '');
                if ($itemFieldName === '') {
                    continue;
                }

                $inputName = app_bitacora_group_item_field_name($name, $index, $itemFieldName);
                $inputLabel = $label . ' - ' . (string) ($group['item_label'] ?? 'Registro') . ' ' . $index . ' - ' . (string) ($itemField['label'] ?? $itemFieldName);
                [$valid, $message] = bit_validate_configured_value($itemField, $inputName, $inputLabel);
                if (!$valid) {
                    return [false, $message];
                }
            }
        }
    }

    return [true, ''];
}

function bit_validate_detail_groups(array $groups, string $fecha): array
{
    foreach ($groups as $group) {
        if (!app_bitacora_field_available_for_date($group, $fecha)) {
            continue;
        }

        $name = (string) ($group['name'] ?? '');
        $label = (string) ($group['label'] ?? $name);
        $answer = trim((string) ($_POST[$name] ?? ''));
        $required = (bool) ($group['required'] ?? false);

        if ($required && !in_array($answer, ['Si', 'No'], true)) {
            return [false, 'El campo "' . $label . '" debe ser Si o No.'];
        }

        if ($answer !== 'Si') {
            continue;
        }

        foreach ((array) ($group['fields'] ?? []) as $detailField) {
            $detailFieldName = (string) ($detailField['name'] ?? '');
            if ($detailFieldName === '') {
                continue;
            }

            $inputName = app_bitacora_detail_group_field_name($name, $detailFieldName);
            $inputLabel = $label . ' - ' . (string) ($detailField['label'] ?? $detailFieldName);
            [$valid, $message] = bit_validate_configured_value($detailField, $inputName, $inputLabel);
            if (!$valid) {
                return [false, $message];
            }
        }
    }

    return [true, ''];
}

function bit_validate_branch_groups(array $groups, string $fecha): array
{
    foreach ($groups as $group) {
        if (!app_bitacora_field_available_for_date($group, $fecha)) {
            continue;
        }

        $name = (string) ($group['name'] ?? '');
        $label = (string) ($group['label'] ?? $name);
        $answer = trim((string) ($_POST[$name] ?? ''));
        $required = (bool) ($group['required'] ?? false);

        if ($required && !in_array($answer, ['Si', 'No'], true)) {
            return [false, 'El campo "' . $label . '" debe ser Si o No.'];
        }

        if ($answer === '') {
            continue;
        }

        $branch = $answer === 'Si' ? 'si' : 'no';
        $branchKey = $branch . '_fields';
        foreach ((array) ($group[$branchKey] ?? []) as $branchField) {
            $branchFieldName = (string) ($branchField['name'] ?? '');
            if ($branchFieldName === '') {
                continue;
            }

            $inputName = app_bitacora_branch_group_field_name($name, $branch, $branchFieldName);
            $inputLabel = $label . ' - ' . (string) ($branchField['label'] ?? $branchFieldName);
            [$valid, $message] = bit_validate_configured_value($branchField, $inputName, $inputLabel);
            if (!$valid) {
                return [false, $message];
            }
        }
    }

    return [true, ''];
}

function bit_valid_area_visit_label(string $visitor): bool
{
    if (!preg_match('/^(.+?)\s+-\s+(.+)$/u', trim($visitor), $matches)) {
        return false;
    }

    $nameParts = preg_split('/\s+/u', trim((string) $matches[1]));
    $cargo = trim((string) $matches[2]);

    return is_array($nameParts) && count(array_filter($nameParts)) >= 2 && $cargo !== '';
}

function bit_validate_multiselect_detail_groups(array $groups): array
{
    foreach ($groups as $group) {
        $name = (string) ($group['name'] ?? '');
        if ($name === '') {
            continue;
        }

        $label = (string) ($group['label'] ?? $name);
        $selected = bit_selected_values($_POST[$name] ?? []);
        $required = (bool) ($group['required'] ?? false);
        $noApply = bit_multiselect_detail_no_apply($group);

        if ($required && $selected === []) {
            return [false, 'El campo "' . $label . '" es obligatorio.'];
        }

        if ($selected === []) {
            continue;
        }

        if (in_array($noApply, $selected, true)) {
            if (count($selected) > 1) {
                return [false, 'No puedes seleccionar "' . $noApply . '" junto con otros visitantes.'];
            }
            continue;
        }

        $details = bit_multiselect_detail_rows_from_post($_POST, $group);
        foreach ($selected as $visitor) {
            if (!bit_valid_area_visit_label($visitor)) {
                return [false, 'La visita "' . $visitor . '" debe tener el formato Nombre Apellido - Cargo.'];
            }

            if (!isset($details[$visitor])) {
                return [false, 'Debes diligenciar el detalle de la visita "' . $visitor . '".'];
            }

            $detail = $details[$visitor];
            if (!bit_valid_time_value((string) ($detail['hora_inicio'] ?? ''))) {
                return [false, 'La hora inicio de "' . $visitor . '" debe ser válida.'];
            }
            if (!bit_valid_time_value((string) ($detail['hora_final'] ?? ''))) {
                return [false, 'La hora final de "' . $visitor . '" debe ser válida.'];
            }
            if (trim((string) ($detail['actividades'] ?? '')) === '') {
                return [false, 'Las actividades realizadas de "' . $visitor . '" son obligatorias.'];
            }
        }
    }

    return [true, ''];
}
