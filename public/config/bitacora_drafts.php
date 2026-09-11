<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/bitacora.php';

const BIT_DRAFT_CIPHER = 'aes-256-gcm';

function bit_draft_ttl_days(): int
{
    return max(1, app_env_int('BITACORA_DRAFT_TTL_DAYS', 30));
}

function bit_draft_max_bytes(): int
{
    return max(1, app_env_int('BITACORA_DRAFT_MAX_BYTES', 262144));
}

function bit_draft_decode_key(string $encodedKey): string
{
    if ($encodedKey === '') {
        throw new RuntimeException('No está configurada la clave de borradores.');
    }

    $key = base64_decode($encodedKey, true);
    if ($key === false || strlen($key) !== 32 || base64_encode($key) !== $encodedKey) {
        throw new RuntimeException('La clave de borradores debe ser base64 canónico de 32 bytes.');
    }

    return $key;
}

function bit_draft_parse_keyring(?string $legacyKey, ?string $keyringJson): array
{
    $keyring = [];
    if (is_string($keyringJson) && trim($keyringJson) !== '') {
        try {
            $decoded = json_decode($keyringJson, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('BITACORA_DRAFT_KEYRING_JSON no contiene JSON válido.', 0, $e);
        }
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new RuntimeException('BITACORA_DRAFT_KEYRING_JSON debe ser un objeto por versión.');
        }
        foreach ($decoded as $version => $encodedKey) {
            $versionString = (string) $version;
            if (preg_match('/^[1-9]\d{0,4}$/', $versionString) !== 1 || (int) $versionString > 65535 || !is_string($encodedKey)) {
                throw new RuntimeException('El keyring de borradores contiene una versión inválida.');
            }
            $keyring[(int) $versionString] = bit_draft_decode_key($encodedKey);
        }
    }

    if (is_string($legacyKey) && $legacyKey !== '') {
        $decodedLegacy = bit_draft_decode_key($legacyKey);
        if (isset($keyring[1]) && !hash_equals($keyring[1], $decodedLegacy)) {
            throw new RuntimeException('La clave legacy no coincide con la versión 1 del keyring.');
        }
        $keyring[1] = $decodedLegacy;
    }
    if ($keyring === []) {
        throw new RuntimeException('No está configurado el keyring de borradores.');
    }
    ksort($keyring, SORT_NUMERIC);
    return $keyring;
}

function bit_draft_keyring(): array
{
    return bit_draft_parse_keyring(app_env('BITACORA_DRAFT_KEY_BASE64'), app_env('BITACORA_DRAFT_KEYRING_JSON'));
}

function bit_draft_active_key_version(): int
{
    $version = app_env_int('BITACORA_DRAFT_ACTIVE_KEY_VERSION', 1);
    if ($version < 1 || $version > 65535) {
        throw new RuntimeException('BITACORA_DRAFT_ACTIVE_KEY_VERSION no es válida.');
    }
    if (!isset(bit_draft_keyring()[$version])) {
        throw new RuntimeException('La versión activa no está presente en el keyring de borradores.');
    }
    return $version;
}

function bit_draft_key_for_version(int $version): string
{
    $keyring = bit_draft_keyring();
    if (!isset($keyring[$version])) {
        throw new RuntimeException('No está configurada la clave de borradores para la versión ' . $version . '.');
    }
    return $keyring[$version];
}

function bit_draft_key(?string $encodedKey = null): string
{
    return $encodedKey !== null
        ? bit_draft_decode_key($encodedKey)
        : bit_draft_key_for_version(bit_draft_active_key_version());
}

function bit_draft_encrypt(string $plaintext, string $aad, ?string $key = null): array
{
    $keyVersion = bit_draft_active_key_version();
    $key = $key ?? bit_draft_key_for_version($keyVersion);
    if (strlen($key) !== 32) {
        throw new RuntimeException('La clave de cifrado no tiene la longitud requerida.');
    }

    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($plaintext, BIT_DRAFT_CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, $aad, 16);
    if ($ciphertext === false || strlen($tag) !== 16) {
        throw new RuntimeException('No fue posible cifrar el borrador.');
    }

    return [
        'ciphertext' => $ciphertext,
        'iv' => $iv,
        'tag' => $tag,
        'key_version' => $keyVersion,
    ];
}

function bit_draft_decrypt(string $ciphertext, string $iv, string $tag, string $aad, ?string $key = null): string
{
    $key = $key ?? bit_draft_key();
    if (strlen($key) !== 32 || strlen($iv) !== 12 || strlen($tag) !== 16) {
        throw new RuntimeException('El borrador cifrado no tiene un formato válido.');
    }

    $plaintext = openssl_decrypt($ciphertext, BIT_DRAFT_CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, $aad);
    if ($plaintext === false) {
        throw new RuntimeException('No fue posible autenticar el borrador cifrado.');
    }

    return $plaintext;
}

function bit_draft_canonicalize($value)
{
    if (!is_array($value)) {
        return $value;
    }

    if (array_is_list($value)) {
        return array_map('bit_draft_canonicalize', $value);
    }

    ksort($value, SORT_STRING);
    foreach ($value as $key => $item) {
        $value[$key] = bit_draft_canonicalize($item);
    }

    return $value;
}

function bit_draft_schema_hash(array $sections): string
{
    $json = json_encode(bit_draft_canonicalize($sections), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    return hash('sha256', $json);
}

function bit_draft_aad(int $userId, int $empresaId, string $type, string $token, string $schemaHash = '', ?int $keyVersion = null): string
{
    $keyVersion = $keyVersion ?? bit_draft_active_key_version();
    return json_encode([
        'purpose' => 'bitacora-draft',
        'user_id' => $userId,
        'empresa_id' => $empresaId,
        'type' => $type,
        'token' => $token,
        'schema_hash' => $schemaHash,
        'key_version' => $keyVersion,
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function bit_draft_allowed_options(array $options): array
{
    $allowed = [];
    foreach ($options as $value => $label) {
        $allowed[] = is_int($value) ? (string) $label : (string) $value;
    }
    return array_values(array_unique($allowed));
}

function bit_draft_valid_date(string $value): bool
{
    foreach (['Y-m-d', 'd-m-Y'] as $format) {
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
        if ($date instanceof DateTimeImmutable && $date->format($format) === $value) {
            return true;
        }
    }
    return false;
}

function bit_draft_sanitize_scalar($value, array $field, string $name): string
{
    if (is_bool($value) || is_array($value) || is_object($value) || $value === null || (!is_string($value) && !is_int($value) && !is_float($value))) {
        throw new InvalidArgumentException('El campo "' . $name . '" tiene un tipo inválido.');
    }

    if (is_float($value) && !is_finite($value)) {
        throw new InvalidArgumentException('El campo "' . $name . '" tiene un número inválido.');
    }

    $value = (string) $value;
    if (!mb_check_encoding($value, 'UTF-8')) {
        throw new InvalidArgumentException('El campo "' . $name . '" no contiene texto UTF-8 válido.');
    }
    $trimmed = trim($value);
    if ($trimmed === '') {
        return '';
    }

    $type = (string) ($field['type'] ?? 'text');
    if ($type === 'number') {
        $normalized = str_replace(',', '.', $trimmed);
        if (!is_numeric($normalized)) {
            throw new InvalidArgumentException('El campo "' . $name . '" debe ser numérico.');
        }
        $number = (float) $normalized;
        if (!is_finite($number)
            || (array_key_exists('min', $field) && $number < (float) $field['min'])
            || (array_key_exists('max', $field) && $number > (float) $field['max'])) {
            throw new InvalidArgumentException('El campo "' . $name . '" está fuera del rango permitido.');
        }
        if (!empty($field['integer']) && preg_match('/^-?\d+$/', $trimmed) !== 1) {
            throw new InvalidArgumentException('El campo "' . $name . '" debe ser entero.');
        }
        return $trimmed;
    }

    if ($type === 'date' && !bit_draft_valid_date($trimmed)) {
        throw new InvalidArgumentException('El campo "' . $name . '" debe ser una fecha válida.');
    }
    if ($type === 'time' && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $trimmed) !== 1) {
        throw new InvalidArgumentException('El campo "' . $name . '" debe ser una hora válida.');
    }
    if (in_array($type, ['select', 'multiselect_option'], true)) {
        $allowed = bit_draft_allowed_options((array) ($field['options'] ?? []));
        if ($allowed !== [] && !in_array($trimmed, $allowed, true)) {
            throw new InvalidArgumentException('El campo "' . $name . '" contiene una opción inválida.');
        }
        return $trimmed;
    }
    if ($type === 'yes_no' && !in_array($trimmed, ['Si', 'No'], true)) {
        throw new InvalidArgumentException('El campo "' . $name . '" debe ser Si o No.');
    }

    $maxLength = (int) ($field['max_length'] ?? ($type === 'textarea' ? 10000 : 500));
    if ($maxLength > 0 && mb_strlen($value, 'UTF-8') > $maxLength) {
        throw new InvalidArgumentException('El campo "' . $name . '" excede la longitud máxima.');
    }

    return $value;
}

function bit_draft_add_definition(array &$definitions, string $name, array $definition): void
{
    if ($name === '' || isset($definitions[$name])) {
        throw new RuntimeException('El esquema contiene un nombre de campo inválido o duplicado: ' . ($name !== '' ? $name : '(vacío)') . '.');
    }
    $definitions[$name] = $definition;
}

function bit_draft_field_definitions(array $sections, string $sede = ''): array
{
    $definitions = [];
    foreach ($sections as $section) {
        if ($sede !== '' && !app_bitacora_field_visible_for_sede($section, $sede)) {
            continue;
        }
        foreach ((array) ($section['fields'] ?? []) as $field) {
            if ($sede !== '' && !app_bitacora_field_visible_for_sede($field, $sede)) {
                continue;
            }

            $name = (string) ($field['name'] ?? '');
            $type = (string) ($field['type'] ?? 'text');
            if (app_bitacora_field_is_presentational($field)) {
                continue;
            }
            if ($name === '') {
                throw new RuntimeException('El esquema contiene un campo sin nombre.');
            }

            if (in_array($type, ['text', 'textarea', 'number', 'date', 'time', 'select', 'conditional_textarea'], true)) {
                if ($type === 'conditional_textarea') {
                    $field['type'] = 'textarea';
                }
                bit_draft_add_definition($definitions, $name, ['kind' => 'scalar', 'field' => $field]);
                continue;
            }

            if (in_array($type, ['yes_no', 'simple_radio'], true)) {
                $answerField = $field;
                $answerField['type'] = 'yes_no';
                bit_draft_add_definition($definitions, $name, ['kind' => 'scalar', 'field' => $answerField]);
                if ($type === 'yes_no' && !empty($field['detail_name'])) {
                    $detailType = (string) ($field['detail_type'] ?? 'textarea');
                    if ($detailType === 'multiselect') {
                        bit_draft_add_definition($definitions, (string) $field['detail_name'], [
                            'kind' => 'list',
                            'field' => [
                                'type' => 'multiselect',
                                'options' => array_values(array_unique(array_filter(
                                    (array) ($field['detail_options'] ?? []),
                                    static fn($value) => trim((string) $value) !== ''
                                ))),
                            ],
                        ]);
                    } else {
                        $detailField = ['type' => $detailType];
                        if (array_key_exists('detail_max_length', $field)) {
                            $detailField['max_length'] = $field['detail_max_length'];
                        }
                        bit_draft_add_definition($definitions, (string) $field['detail_name'], [
                            'kind' => 'scalar',
                            'field' => $detailField,
                        ]);
                    }
                }
                continue;
            }

            if ($type === 'multiselect') {
                bit_draft_add_definition($definitions, $name, ['kind' => 'list', 'field' => $field]);
                continue;
            }

            if ($type === 'multiselect_detail_group') {
                bit_draft_add_definition($definitions, $name, ['kind' => 'visitor_list', 'field' => $field]);
                $detailName = (string) ($field['detail_name'] ?? ($name . '_detalles'));
                bit_draft_add_definition($definitions, $detailName, ['kind' => 'visitor_details', 'field' => $field, 'selection_name' => $name]);
                continue;
            }

            if ($type === 'yes_no_quantity_group') {
                $answerField = $field;
                $answerField['type'] = 'yes_no';
                bit_draft_add_definition($definitions, $name, ['kind' => 'scalar', 'field' => $answerField]);
                $min = max(1, (int) ($field['min'] ?? 1));
                $max = max($min, min(10, (int) ($field['max'] ?? 10)));
                $quantityName = (string) ($field['quantity_name'] ?? ($name . '_cantidad'));
                bit_draft_add_definition($definitions, $quantityName, ['kind' => 'scalar', 'field' => ['type' => 'number', 'min' => $min, 'max' => $max, 'integer' => true]]);
                foreach (range(1, $max) as $index) {
                    foreach ((array) ($field['fields'] ?? []) as $itemField) {
                        $itemName = (string) ($itemField['name'] ?? '');
                        $draftItemField = $itemField;
                        if ((string) ($draftItemField['type'] ?? '') === 'simple_radio') {
                            $draftItemField['type'] = 'yes_no';
                        }
                        bit_draft_add_definition($definitions, app_bitacora_group_item_field_name($name, $index, $itemName), ['kind' => 'scalar', 'field' => $draftItemField]);
                    }
                }
                continue;
            }

            if ($type === 'quantity_group') {
                $max = max(1, min(10, (int) ($field['max'] ?? 10)));
                $quantityName = (string) ($field['quantity_name'] ?? ($name . '_cantidad'));
                bit_draft_add_definition($definitions, $quantityName, ['kind' => 'scalar', 'field' => ['type' => 'number', 'min' => 0, 'max' => $max, 'integer' => true]]);
                foreach (range(1, $max) as $index) {
                    foreach ((array) ($field['fields'] ?? []) as $itemField) {
                        $itemName = (string) ($itemField['name'] ?? '');
                        $draftItemField = $itemField;
                        if ((string) ($draftItemField['type'] ?? '') === 'simple_radio') {
                            $draftItemField['type'] = 'yes_no';
                        }
                        bit_draft_add_definition($definitions, app_bitacora_group_item_field_name($name, $index, $itemName), ['kind' => 'scalar', 'field' => $draftItemField]);
                    }
                }
                continue;
            }

            if ($type === 'yes_no_detail_group') {
                $answerField = $field;
                $answerField['type'] = 'yes_no';
                bit_draft_add_definition($definitions, $name, ['kind' => 'scalar', 'field' => $answerField]);
                foreach ((array) ($field['fields'] ?? []) as $detailField) {
                    $detailName = (string) ($detailField['name'] ?? '');
                    bit_draft_add_definition($definitions, app_bitacora_detail_group_field_name($name, $detailName), ['kind' => 'scalar', 'field' => $detailField]);
                }
                continue;
            }

            if ($type === 'yes_no_branch_group') {
                $answerField = $field;
                $answerField['type'] = 'yes_no';
                bit_draft_add_definition($definitions, $name, ['kind' => 'scalar', 'field' => $answerField]);
                foreach (['si' => 'si_fields', 'no' => 'no_fields'] as $branch => $branchKey) {
                    foreach ((array) ($field[$branchKey] ?? []) as $branchField) {
                        $branchFieldName = (string) ($branchField['name'] ?? '');
                        if ($branchFieldName === '') {
                            continue;
                        }

                        $draftBranchField = $branchField;
                        if ((string) ($draftBranchField['type'] ?? '') === 'simple_radio') {
                            $draftBranchField['type'] = 'yes_no';
                        }
                        bit_draft_add_definition(
                            $definitions,
                            app_bitacora_branch_group_field_name($name, $branch, $branchFieldName),
                            ['kind' => 'scalar', 'field' => $draftBranchField]
                        );
                    }
                }
                continue;
            }

            if ($type === 'plant') {
                $answerField = $field;
                $answerField['type'] = 'yes_no';
                bit_draft_add_definition($definitions, $name, ['kind' => 'scalar', 'field' => $answerField]);
                bit_draft_add_definition($definitions, 'mant5', ['kind' => 'scalar', 'field' => ['type' => 'time']]);
                bit_draft_add_definition($definitions, 'mant6', ['kind' => 'scalar', 'field' => ['type' => 'time']]);
                bit_draft_add_definition($definitions, 'mant7', ['kind' => 'scalar', 'field' => ['type' => 'number', 'min' => 0]]);
                continue;
            }

            if ($type === 'supervisor_detail') {
                bit_draft_add_definition($definitions, 'hora_entrada', ['kind' => 'scalar', 'field' => ['type' => 'time']]);
                bit_draft_add_definition($definitions, 'hora_salida', ['kind' => 'scalar', 'field' => ['type' => 'time']]);
                bit_draft_add_definition($definitions, 'act_sup', ['kind' => 'scalar', 'field' => ['type' => 'textarea']]);
                continue;
            }

            throw new RuntimeException('El esquema contiene un tipo de campo no soportado para borradores.');
        }
    }
    return $definitions;
}

function bit_draft_sanitize_list($value, array $field, string $name, bool $allowVisitors): array
{
    if (!is_array($value) || !array_is_list($value) || count($value) > 100) {
        throw new InvalidArgumentException('El campo "' . $name . '" debe ser una lista válida.');
    }

    $result = [];
    $allowed = bit_draft_allowed_options((array) ($field['options'] ?? []));
    foreach ($value as $item) {
        $itemField = ['type' => 'multiselect_option', 'options' => $field['options'] ?? [], 'max_length' => 500];
        if ($allowVisitors) {
            $itemField['type'] = 'text';
        }
        $item = bit_draft_sanitize_scalar($item, $itemField, $name);
        if ($item === '') {
            continue;
        }
        if ($allowVisitors && !in_array($item, $allowed, true) && preg_match('/^(.+?)\s+-\s+(.+)$/u', $item) !== 1) {
            throw new InvalidArgumentException('El campo "' . $name . '" contiene una visita inválida.');
        }
        if (!in_array($item, $result, true)) {
            $result[] = $item;
        }
    }
    return $result;
}

function bit_draft_sanitize_visitor_details($value, array $field, string $name, array $selected): array
{
    if (!is_array($value) || count($value) > 100) {
        throw new InvalidArgumentException('El campo "' . $name . '" debe ser un grupo válido.');
    }

    $noApply = (string) ($field['no_apply_value'] ?? 'No aplica visita');
    if (in_array($noApply, $selected, true) && $value !== []) {
        throw new InvalidArgumentException('El grupo "' . $name . '" no admite detalles para la opción de no aplicación.');
    }

    $result = [];
    foreach ($value as $rowKey => $row) {
        if ((!is_int($rowKey) && preg_match('/^[a-zA-Z0-9_-]{1,64}$/', (string) $rowKey) !== 1) || !is_array($row)) {
            throw new InvalidArgumentException('El grupo "' . $name . '" contiene una fila inválida.');
        }
        $unknown = array_diff(array_keys($row), ['visitante', 'hora_inicio', 'hora_final', 'actividades']);
        if ($unknown !== []) {
            throw new InvalidArgumentException('El grupo "' . $name . '" contiene campos desconocidos.');
        }

        $clean = [];
        foreach ([
            'visitante' => ['type' => 'text', 'max_length' => 500],
            'hora_inicio' => ['type' => 'time'],
            'hora_final' => ['type' => 'time'],
            'actividades' => ['type' => 'textarea'],
        ] as $column => $columnField) {
            if (array_key_exists($column, $row)) {
                $clean[$column] = bit_draft_sanitize_scalar($row[$column], $columnField, $name . '.' . $column);
            }
        }
        $visitor = trim((string) ($clean['visitante'] ?? ''));
        if ($visitor !== '' && !in_array($visitor, $selected, true)) {
            throw new InvalidArgumentException('El grupo "' . $name . '" contiene una visita no seleccionada.');
        }
        $result[$rowKey] = $clean;
    }
    return $result;
}

function bit_draft_sanitize_payload(array $payload, array $sections, array $companyConfig): array
{
    $sede = '';
    if (array_key_exists('sede', $payload)) {
        if (!is_string($payload['sede']) && !is_int($payload['sede'])) {
            throw new InvalidArgumentException('La sede tiene un tipo inválido.');
        }
        $sede = trim((string) $payload['sede']);
        $allowedSedes = array_map('strval', (array) ($companyConfig['sedes'] ?? []));
        if ($sede !== '' && ($allowedSedes === [] || !in_array($sede, $allowedSedes, true))) {
            throw new InvalidArgumentException('La sede no pertenece a la empresa.');
        }
    }

    $definitions = bit_draft_field_definitions($sections, $sede);
    foreach (array_keys($payload) as $name) {
        if (!is_string($name) || !isset($definitions[$name])) {
            throw new InvalidArgumentException('El borrador contiene campos desconocidos.');
        }
    }

    $result = [];
    foreach ($payload as $name => $value) {
        $definition = $definitions[$name];
        $kind = (string) $definition['kind'];
        if ($kind === 'scalar') {
            $result[$name] = bit_draft_sanitize_scalar($value, (array) $definition['field'], $name);
        } elseif ($kind === 'list') {
            $result[$name] = bit_draft_sanitize_list($value, (array) $definition['field'], $name, false);
        } elseif ($kind === 'visitor_list') {
            $result[$name] = bit_draft_sanitize_list($value, (array) $definition['field'], $name, true);
        } elseif ($kind === 'visitor_details') {
            $selectionName = (string) $definition['selection_name'];
            $selected = isset($result[$selectionName])
                ? (array) $result[$selectionName]
                : (array_key_exists($selectionName, $payload) ? bit_draft_sanitize_list($payload[$selectionName], (array) $definition['field'], $selectionName, true) : []);
            $result[$name] = bit_draft_sanitize_visitor_details($value, (array) $definition['field'], $name, $selected);
        }
    }

    return $result;
}

function bit_draft_sanitize_payload_compatible(array $payload, array $sections, array $companyConfig): array
{
    $omitted = [];
    $sede = '';
    if (array_key_exists('sede', $payload) && (is_string($payload['sede']) || is_int($payload['sede']))) {
        $candidate = trim((string) $payload['sede']);
        $allowedSedes = array_map('strval', (array) ($companyConfig['sedes'] ?? []));
        if ($candidate === '' || in_array($candidate, $allowedSedes, true)) {
            $sede = $candidate;
        } else {
            $omitted[] = 'sede';
            unset($payload['sede']);
        }
    }

    $definitions = bit_draft_field_definitions($sections, $sede);
    $result = [];
    foreach ($payload as $name => $value) {
        if (!is_string($name) || !isset($definitions[$name])) {
            $omitted[] = (string) $name;
            continue;
        }

        $definition = $definitions[$name];
        try {
            $kind = (string) $definition['kind'];
            if ($kind === 'scalar') {
                $result[$name] = bit_draft_sanitize_scalar($value, (array) $definition['field'], $name);
            } elseif ($kind === 'list') {
                $result[$name] = bit_draft_sanitize_list($value, (array) $definition['field'], $name, false);
            } elseif ($kind === 'visitor_list') {
                $result[$name] = bit_draft_sanitize_list($value, (array) $definition['field'], $name, true);
            } elseif ($kind === 'visitor_details') {
                $selectionName = (string) $definition['selection_name'];
                $selected = isset($result[$selectionName])
                    ? (array) $result[$selectionName]
                    : (array_key_exists($selectionName, $payload) ? bit_draft_sanitize_list($payload[$selectionName], (array) $definition['field'], $selectionName, true) : []);
                $result[$name] = bit_draft_sanitize_visitor_details($value, (array) $definition['field'], $name, $selected);
            }
        } catch (InvalidArgumentException $e) {
            $omitted[] = $name;
        }
    }

    return [$result, array_values(array_unique($omitted))];
}

function bit_draft_iso_utc(?string $value): ?string
{
    if ($value === null || trim($value) === '') {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('UTC'));
    return $date instanceof DateTimeImmutable ? $date->format('Y-m-d\TH:i:s\Z') : null;
}
