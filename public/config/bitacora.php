<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

function app_bitacora_common_recipients(): array
{
    return [
        'coordinador.sistemas@misterwings.com','soporte@misterwings.com','subgerente@misterwings.com',
        'coordinadora.sg-sst@misterwings.com','jefegestionhumana@misterwings.com','ambiental@misterwings.com',
        'gestionhumana@misterwings.com','supervisora.cocinas@misterwings.com','operaciones.supervisor@misterwings.com',
        'coordinador.operaciones@misterwings.com','mejoramiento@misterwings.com','mercadeo@misterwings.com',
        'gerencia@misterwings.com','mantenimiento@misterwings.com','auxiliar.sg-sst@misterwings.com',
        'coord.inventarios@misterwings.com','visual@misterwings.com','comercial@misterwings.com',
        'supervisor.comercial@misterwings.com','supervisor.cocinas2@misterwings.com','capacitacionmw@misterwings.com',
        'auxiliar1.sg-sst@misterwings.com','auxiliar.sistemas@misterwings.com','comercial.gerencia@misterwings.com',
        'colquingroup@hotmail.com','aux.gestionhumana@misterwings.com','coordinador.procesos@misterwings.com',
        'apr.mejoramiento@misterwings.com','asistente.operativo@misterwings.com','grafica@misterwings.com',
    ];
}

function app_bitacora_configs(): array
{
    $common = app_bitacora_common_recipients();

    return [
        1 => [
            'slug' => 'mes_group',
            'type' => 'operational',
            'title' => 'Bitacora Mister Wings',
            'sedes' => ['PANCE', 'CIUDAD JARDÍN', 'JARDÍN PLAZA', 'BOCHALEMA', 'UNICENTRO'],
            'extras_by_sede' => ['PANCE' => ['chetano'], 'UNICENTRO' => ['chetano']],
            'recipients' => [
                'global' => array_merge($common, [
                    'tesoreria@misterwings.com',
                    'contabilidad@misterwings.com',
                    'aux.tesoreria@misterwings.com',
                    'aux.contable2@misterwings.com',
                    'director.administrativosedes@misterwings.com',
                ]),
                'by_sede' => [
                    'PANCE' => ['adminpance@misterwings.com', 'pance@misterwings.com'],
                    'CIUDAD JARDÍN' => ['adminciudadjardin@misterwings.com', 'ciudadjardin@misterwings.com'],
                    'JARDÍN PLAZA' => ['adminjardinplaza@misterwings.com', 'jardinplaza@misterwings.com'],
                    'BOCHALEMA' => ['adminbochalema@misterwings.com', 'coor.bochalema@misterwings.com', 'bochalema@misterwings.com'],
                    'UNICENTRO' => ['adminunicentro@misterwings.com', 'unicentro@misterwings.com'],
                ],
            ],
        ],
        2 => [
            'slug' => 'mes_soluciones_hcqc',
            'type' => 'operational',
            'title' => 'Bitacora Mister Wings',
            'sedes' => ['GRANADA'],
            'default_sede' => 'GRANADA',
            'recipients' => [
                'global' => array_merge($common, [
                    'granada@misterwings.com',
                    'admingranada@misterwings.com',
                    'coor.granada@misterwings.com',
                    'tesoreria@misterwings.com',
                    'contabilidad@misterwings.com',
                    'aux.tesoreria@misterwings.com',
                    'aux.contable2@misterwings.com',
                ]),
                'by_sede' => [],
            ],
        ],
        3 => [
            'slug' => 'les_group',
            'type' => 'operational',
            'title' => 'Bitacora Mister Wings',
            'sedes' => ['CHIPICHAPE', 'FLORA'],
            'recipients' => [
                'global' => array_merge($common, [
                    'adminlaflora@misterwings.com',
                    'lenisalvaro@hotmail.com',
                    'contabilidad.valquin.les@misterwings.com',
                    'esquin@hotmail.com',
                ]),
                'by_sede' => [
                    'CHIPICHAPE' => ['Adminchipichape@misterwings.com', 'chipichape@misterwings.com'],
                    'FLORA' => ['coordinadorflora@misterwings.com', 'laflora@misterwings.com'],
                ],
            ],
        ],
        4 => [
            'slug' => 'inversiones_valquin',
            'type' => 'operational',
            'title' => 'Bitacora Mister Wings',
            'sedes' => ['LIMONAR', 'SAN FERNANDO'],
            'recipients' => [
                'global' => array_merge($common, [
                    'contabilidad.valquin.les@misterwings.com',
                    'esquin@hotmail.com',
                    'lenisalvaro@hotmail.com',
                    'contabilidad-sanfernando@misterwings.com',
                ]),
                'by_sede' => [
                    'LIMONAR' => ['adminlimonar@misterwings.com', 'limonar@misterwings.com'],
                    'SAN FERNANDO' => ['adminsanfernando@misterwings.com', 'sanfernando@misterwings.com'],
                ],
            ],
        ],
        5 => [
            'slug' => 'lebor_sas',
            'type' => 'operational',
            'title' => 'Bitacora Mister Wings',
            'sedes' => ['LLANOGRANDE'],
            'default_sede' => 'LLANOGRANDE',
            'extras' => ['reunion_calidad'],
            'recipients' => [
                'global' => array_merge($common, [
                    'cajallanogrande@misterwings.com',
                    'coordinadorllanogrande@misterwings.com',
                    'contabilidad.valquin.les@misterwings.com',
                    'esquin@hotmail.com',
                    'lenisalvaro@hotmail.com',
                    'contabilidad-sanfernando@misterwings.com',
                ]),
                'by_sede' => [],
            ],
        ],
        6 => [
            'slug' => 'supervisiones',
            'type' => 'supervision',
            'title' => 'Reporte de supervision',
            'sedes' => ['Pance', 'Ciudad Jardín', 'Jardín Plaza', 'Unicentro', 'Limonar', 'San Fernando', 'Granada', 'Chipichape', 'Flora', 'Llanogrande', 'Bochalema'],
            'recipients' => [
                'global' => ['gerente.administrativo@misterwings.com', 'gerencia@misterwings.com', 'gerente.franquicias@misterwings.com'],
                'cc' => ['supervisor.cocinas2@misterwings.com', 'supervisor.comercial@misterwings.com', 'supervisor.cocinas@misterwings.com', 'operaciones.supervisor@misterwings.com', 'coordinador.operaciones@misterwings.com', 'soporte@misterwings.com'],
                'by_sede' => [
                    'Pance' => ['adminpance@misterwings.com'],
                    'Ciudad Jardín' => ['adminciudadjardin@misterwings.com'],
                    'Jardín Plaza' => ['adminjardinplaza@misterwings.com'],
                    'Unicentro' => ['admin.unicentro@misterwings.com'],
                    'Limonar' => ['esquin@hotmail.com', 'lenisalvaro@hotmail.com', 'adminlimonar@misterwings.com'],
                    'San Fernando' => ['esquin@hotmail.com', 'lenisalvaro@hotmail.com', 'adminsanfernando@misterwings.com'],
                    'Granada' => ['admingranada@misterwings.com', 'coor.granada@misterwings.com'],
                    'Chipichape' => ['esquin@hotmail.com', 'adminlaflora@misterwings.com', 'adminchipichape@misterwings.com'],
                    'Flora' => ['esquin@hotmail.com', 'adminlaflora@misterwings.com', 'coordinadorflora@misterwings.com'],
                    'Llanogrande' => ['esquin@hotmail.com', 'lenisalvaro@hotmail.com', 'coordinadorllanogrande@misterwings.com'],
                    'Bochalema' => ['adminbochalema@misterwings.com', 'coor.bochalema@misterwings.com'],
                ],
            ],
        ],
        7 => [
            'slug' => 'mes_trilogia',
            'type' => 'operational',
            'title' => 'Bitacora Mister Wings',
            'sedes' => ['UNICENTRO - TRILOGIA'],
            'default_sede' => 'UNICENTRO - TRILOGIA',
            'extras' => ['chetano', 'torito'],
            'recipients' => [
                'global' => array_merge($common, [
                    'unicentro@misterwings.com',
                    'admin.unicentro@misterwings.com',
                    'tesoreria@misterwings.com',
                    'contabilidad@misterwings.com',
                    'aux.tesoreria@misterwings.com',
                    'aux.contable2@misterwings.com',
                    'aux.admin.bochalema@misterwings.com',
                    'director.administrativo@misterwings.com',
                    'director.administrativosedes@misterwings.com',
                ]),
                'by_sede' => [],
            ],
        ],
        8 => [
            'slug' => 'mes_test',
            'type' => 'operational',
            'title' => 'Bitacora Mister Wings',
            'sedes' => ['PANCE', 'CIUDAD JARDÍN', 'JARDÍN PLAZA', 'BOCHALEMA', 'UNICENTRO'],
            'extras_by_sede' => ['PANCE' => ['chetano'], 'UNICENTRO' => ['chetano']],
            'recipients' => [
                'global' => ['coordinador.sistemas@misterwings.com'],
                'bcc' => ['coordinador.sistemas@misterwings.com'],
                'by_sede' => [],
            ],
        ],
    ];
}

function app_bitacora_config(int $empresaId): ?array
{
    static $cache = [];
    if (array_key_exists($empresaId, $cache)) {
        return $cache[$empresaId];
    }

    $configs = app_bitacora_configs();
    $config = $configs[$empresaId] ?? null;

    if ($config === null) {
        return $cache[$empresaId] = null;
    }

    $dbSedes = app_bitacora_db_sedes($empresaId);
    if ($dbSedes !== []) {
        $config['sedes'] = $dbSedes;
        if (isset($config['default_sede']) && !in_array((string) $config['default_sede'], $dbSedes, true)) {
            unset($config['default_sede']);
        }
    }

    return $cache[$empresaId] = $config;
}

function app_bitacora_db_sedes(int $empresaId): array
{
    static $cache = [];
    if (array_key_exists($empresaId, $cache)) {
        return $cache[$empresaId];
    }

    try {
        require_once __DIR__ . '/../bd/conexion.php';
        $pdo = Conexion::Conectar();
        $stmt = $pdo->prepare('
            SELECT valor_form
            FROM empresa_sedes
            WHERE idEmpresa = :idEmpresa
              AND activo = 1
            ORDER BY orden, valor_form
        ');
        $stmt->execute(['idEmpresa' => $empresaId]);

        $sedes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sede = trim((string) ($row['valor_form'] ?? ''));
            if ($sede !== '') {
                $sedes[] = $sede;
            }
        }

        return $cache[$empresaId] = array_values(array_unique($sedes));
    } catch (Throwable $e) {
        error_log('No fue posible leer sedes de bitácora: ' . $e->getMessage());
        return $cache[$empresaId] = [];
    }
}

function app_bitacora_empresa_options(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $configs = app_bitacora_configs();
    $ids = array_keys($configs);
    if ($ids === []) {
        return $cache = [];
    }

    try {
        require_once __DIR__ . '/../bd/conexion.php';
        $pdo = Conexion::Conectar();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare('SELECT id, empresa FROM razones_sociales WHERE id IN (' . $placeholders . ') ORDER BY empresa');
        $stmt->execute($ids);

        $options = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $id = (int) ($row['id'] ?? 0);
            if (isset($configs[$id])) {
                $options[$id] = (string) ($row['empresa'] ?? $configs[$id]['slug'] ?? ('Empresa ' . $id));
            }
        }

        foreach ($configs as $id => $config) {
            if (!isset($options[$id])) {
                $options[$id] = (string) ($config['slug'] ?? ('Empresa ' . $id));
            }
        }

        return $cache = $options;
    } catch (Throwable $e) {
        error_log('No fue posible leer empresas de bitácora: ' . $e->getMessage());
        $options = [];
        foreach ($configs as $id => $config) {
            $options[$id] = (string) ($config['slug'] ?? ('Empresa ' . $id));
        }
        return $cache = $options;
    }
}

function app_bitacora_empresa_label(int $empresaId): string
{
    $options = app_bitacora_empresa_options();
    return $options[$empresaId] ?? ('Empresa ' . $empresaId);
}

function app_bitacora_first_empresa_id(): int
{
    $options = app_bitacora_empresa_options();
    $first = array_key_first($options);
    return $first === null ? 0 : (int) $first;
}

function app_bitacora_extra_enabled(array $config, string $extra, string $sede = ''): bool
{
    $sede = trim($sede);
    $extras = $config['extras'] ?? [];
    if (in_array($extra, $extras, true)) {
        return true;
    }

    $extrasBySede = $config['extras_by_sede'] ?? [];
    if ($sede !== '' && isset($extrasBySede[$sede]) && in_array($extra, $extrasBySede[$sede], true)) {
        return true;
    }

    return false;
}

function app_bitacora_recipient_source(int $empresaId): string
{
    static $cache = [];
    if (array_key_exists($empresaId, $cache)) {
        return $cache[$empresaId];
    }

    try {
        require_once __DIR__ . '/../bd/conexion.php';
        $pdo = Conexion::Conectar();
        $stmt = $pdo->prepare('SELECT modo FROM bitacora_destinatarios_config WHERE idEmpresa = :idEmpresa LIMIT 1');
        $stmt->execute(['idEmpresa' => $empresaId]);
        $mode = (string) ($stmt->fetchColumn() ?: 'php');

        return $cache[$empresaId] = $mode === 'database' ? 'database' : 'php';
    } catch (Throwable $e) {
        error_log('No fue posible leer modo de destinatarios de bitácora: ' . $e->getMessage());
        return $cache[$empresaId] = 'php';
    }
}

function app_bitacora_db_recipients(int $empresaId, string $sede, bool $configuredOrder = false): array
{
    static $cache = [];
    $sede = trim($sede);
    $cacheKey = $empresaId . '|' . $sede . '|' . ($configuredOrder ? 'ordered' : 'legacy');
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $recipients = ['to' => [], 'cc' => [], 'bcc' => []];

    try {
        require_once __DIR__ . '/../bd/conexion.php';
        $pdo = Conexion::Conectar();
        $orderBy = $configuredOrder
            ? 'ORDER BY CASE WHEN bd.idSede IS NULL THEN 1 ELSE 0 END, bd.tipo, bd.orden, bd.id'
            : 'ORDER BY CASE WHEN bd.idSede IS NULL THEN 0 ELSE 1 END, bd.tipo, bd.email';
        $stmt = $pdo->prepare('
            SELECT bd.tipo, bd.email
            FROM bitacora_destinatarios bd
            LEFT JOIN empresa_sedes es
                ON es.idEmpresa = bd.idEmpresa
                AND es.idSede = bd.idSede
                AND es.activo = 1
            WHERE bd.idEmpresa = :idEmpresa
              AND bd.activo = 1
              AND (bd.idSede IS NULL OR es.valor_form = :sede)
            ' . $orderBy);
        $stmt->execute(['idEmpresa' => $empresaId, 'sede' => $sede]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $type = (string) ($row['tipo'] ?? '');
            $email = trim((string) ($row['email'] ?? ''));
            if (!isset($recipients[$type]) || $email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            $recipients[$type][] = $email;
        }

        foreach ($recipients as $type => $emails) {
            $recipients[$type] = array_values(array_unique($emails));
        }

        return $cache[$cacheKey] = $recipients;
    } catch (Throwable $e) {
        error_log('No fue posible leer destinatarios de bitácora: ' . $e->getMessage());
        return $cache[$cacheKey] = $recipients;
    }
}

function app_bitacora_recipients_have_values(array $recipients): bool
{
    foreach (['to', 'cc', 'bcc'] as $type) {
        if (!empty($recipients[$type])) {
            return true;
        }
    }

    return false;
}

function app_bitacora_normalize_recipients(array $recipients): array
{
    $normalized = ['to' => [], 'cc' => [], 'bcc' => []];
    $seen = [];

    foreach (['to', 'cc', 'bcc'] as $type) {
        foreach ((array) ($recipients[$type] ?? []) as $email) {
            $email = trim((string) $email);
            $key = strtolower($email);
            if ($email === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $normalized[$type][] = $email;
        }
    }

    return $normalized;
}

function app_bitacora_static_recipients_for_sede(array $config, string $sede): array
{
    $recipients = $config['recipients'] ?? [];
    $sedeRecipients = $recipients['by_sede'][$sede] ?? [];

    return [
        'to' => array_values(array_unique(array_merge((array) $sedeRecipients, (array) ($recipients['global'] ?? [])))),
        'cc' => array_values(array_unique((array) ($recipients['cc'] ?? []))),
        'bcc' => array_values(array_unique((array) ($recipients['bcc'] ?? []))),
    ];
}

function app_bitacora_recipients_for_sede(int $empresaId, string $sede): array
{
    $config = app_bitacora_config($empresaId);
    if ($config === null) {
        throw new RuntimeException('Empresa sin configuración de bitácora.');
    }

    $source = app_bitacora_recipient_source($empresaId);
    $staticRecipients = $source === 'database' ? ['to' => [], 'cc' => [], 'bcc' => []] : app_bitacora_static_recipients_for_sede($config, $sede);
    $dbRecipients = app_bitacora_db_recipients($empresaId, $sede, $source === 'database');

    $recipients = app_bitacora_normalize_recipients([
        'to' => array_merge($staticRecipients['to'] ?? [], $dbRecipients['to'] ?? []),
        'cc' => array_merge($staticRecipients['cc'] ?? [], $dbRecipients['cc'] ?? []),
        'bcc' => array_merge($staticRecipients['bcc'] ?? [], $dbRecipients['bcc'] ?? []),
    ]);

    if (($config['type'] ?? '') !== 'operational') {
        return $recipients;
    }

    return app_bitacora_filter_full_recipients($recipients, app_bitacora_db_section_recipients($empresaId, $sede));
}

function app_bitacora_filter_full_recipients(array $recipients, array $sectionRecipients): array
{
    $sectionEmails = [];
    foreach ($sectionRecipients as $recipient) {
        $email = strtolower(trim((string) ($recipient['email'] ?? '')));
        if ($email !== '') {
            $sectionEmails[$email] = true;
        }
    }

    if ($sectionEmails === []) {
        return $recipients;
    }

    foreach (['to', 'cc', 'bcc'] as $type) {
        $recipients[$type] = array_values(array_filter(
            (array) ($recipients[$type] ?? []),
            static function ($email) use ($sectionEmails): bool {
                return !isset($sectionEmails[strtolower(trim((string) $email))]);
            }
        ));
    }

    return $recipients;
}

function app_bitacora_recipient_type_priority(string $type): int
{
    return ['to' => 1, 'cc' => 2, 'bcc' => 3][$type] ?? 99;
}

function app_bitacora_db_section_recipients(int $empresaId, string $sede): array
{
    static $cache = [];
    $sede = trim($sede);
    $cacheKey = $empresaId . '|' . $sede;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $grouped = [];

    try {
        require_once __DIR__ . '/../bd/conexion.php';
        $pdo = Conexion::Conectar();
        $stmt = $pdo->prepare('
            SELECT bsd.section_key, bsd.tipo, bsd.email
            FROM bitacora_seccion_destinatarios bsd
            LEFT JOIN empresa_sedes es
                ON es.idEmpresa = bsd.idEmpresa
                AND es.idSede = bsd.idSede
                AND es.activo = 1
            WHERE bsd.idEmpresa = :idEmpresa
              AND bsd.activo = 1
              AND (bsd.idSede IS NULL OR es.valor_form = :sede)
            ORDER BY CASE WHEN bsd.idSede IS NULL THEN 0 ELSE 1 END, bsd.email, bsd.section_key
        ');
        $stmt->execute(['idEmpresa' => $empresaId, 'sede' => $sede]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $type = (string) ($row['tipo'] ?? '');
            $email = trim((string) ($row['email'] ?? ''));
            $sectionKey = trim((string) ($row['section_key'] ?? ''));
            if (!in_array($type, ['to', 'cc', 'bcc'], true) || $email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $sectionKey)) {
                continue;
            }

            $emailKey = strtolower($email);
            if (!isset($grouped[$emailKey])) {
                $grouped[$emailKey] = [
                    'email' => $email,
                    'type' => $type,
                    'sections' => [],
                ];
            } elseif (app_bitacora_recipient_type_priority($type) < app_bitacora_recipient_type_priority((string) $grouped[$emailKey]['type'])) {
                $grouped[$emailKey]['type'] = $type;
            }

            $grouped[$emailKey]['sections'][$sectionKey] = true;
        }

        foreach ($grouped as &$recipient) {
            $recipient['sections'] = array_keys((array) $recipient['sections']);
        }
        unset($recipient);

        return $cache[$cacheKey] = array_values($grouped);
    } catch (Throwable $e) {
        error_log('No fue posible leer destinatarios por sección de bitácora: ' . $e->getMessage());
        return $cache[$cacheKey] = [];
    }
}

function app_bitacora_add_recipient_list(PHPMailer $mail, array $recipients): void
{
    $recipients = app_bitacora_normalize_recipients($recipients);

    foreach (array_unique((array) ($recipients['to'] ?? [])) as $email) {
        $mail->addAddress($email);
    }

    foreach (array_unique((array) ($recipients['cc'] ?? [])) as $email) {
        $mail->addCC($email);
    }

    foreach (array_unique((array) ($recipients['bcc'] ?? [])) as $email) {
        $mail->addBCC($email);
    }
}

function app_bitacora_add_recipients(PHPMailer $mail, int $empresaId, string $sede): void
{
    app_bitacora_add_recipient_list($mail, app_bitacora_recipients_for_sede($empresaId, $sede));
}

function app_bitacora_db_config_json(int $empresaId): array
{
    static $cache = [];
    if (array_key_exists($empresaId, $cache)) {
        return $cache[$empresaId];
    }

    try {
        require_once __DIR__ . '/../bd/conexion.php';
        $pdo = Conexion::Conectar();
        $stmt = $pdo->prepare('SELECT config_json FROM bitacora_empresa_config WHERE idEmpresa = :idEmpresa LIMIT 1');
        $stmt->execute(['idEmpresa' => $empresaId]);
        $json = $stmt->fetchColumn();

        if (!is_string($json) || trim($json) === '') {
            return $cache[$empresaId] = [];
        }

        $decoded = json_decode($json, true);
        return $cache[$empresaId] = is_array($decoded) ? $decoded : [];
    } catch (Throwable $e) {
        error_log('No fue posible leer config_json de bitácora: ' . $e->getMessage());
        return $cache[$empresaId] = [];
    }
}

function app_bitacora_field(string $type, string $name, string $label, array $extra = []): array
{
    return array_merge([
        'type' => $type,
        'name' => $name,
        'label' => $label,
        'required' => true,
    ], $extra);
}

function app_bitacora_normalize_number_options(array $field): array
{
    if ((string) ($field['type'] ?? '') !== 'number') {
        return $field;
    }

    $format = (string) ($field['number_format'] ?? 'plain');
    $field['number_format'] = in_array($format, ['plain', 'currency'], true) ? $format : 'plain';

    if (array_key_exists('number_decimals', $field)) {
        $decimals = trim((string) $field['number_decimals']);
        if (preg_match('/^\d+$/', $decimals) === 1) {
            $field['number_decimals'] = max(0, min(6, (int) $decimals));
        } else {
            unset($field['number_decimals']);
        }
    }

    foreach (['suffix', 'suffix_singular', 'suffix_plural'] as $key) {
        if (array_key_exists($key, $field)) {
            $field[$key] = mb_substr(trim((string) $field[$key]), 0, 100, 'UTF-8');
        }
    }

    return $field;
}

function app_bitacora_subsection(string $name, string $label, string $description = '', array $extra = []): array
{
    return array_merge([
        'type' => 'subsection',
        'name' => $name,
        'label' => $label,
        'description' => $description,
        'required' => false,
        'col' => 'col-md-12',
    ], $extra);
}

function app_bitacora_field_is_presentational(array $field): bool
{
    return (string) ($field['type'] ?? '') === 'subsection';
}

function app_bitacora_yes_no_field(
    string $name,
    string $label,
    string $groupId,
    string $detailName,
    string $detailLabel,
    string $detailType = 'textarea',
    array $extra = []
): array
{
    return app_bitacora_field('yes_no', $name, $label, array_merge([
        'group_id' => $groupId,
        'detail_name' => $detailName,
        'detail_label' => $detailLabel,
        'detail_type' => $detailType,
        'no_report_value' => 'Sin novedad',
    ], $extra));
}

function app_bitacora_gh_base_cargos(): array
{
    return [
        'Coordinador/a',
        'Cajero/a',
        'Mesero/a',
        'Auxiliar de Cocina',
        'Auxiliar de Bar',
        'Auxiliar de Inventarios',
    ];
}

function app_bitacora_gh_cargos(int $empresaId): array
{
    $json = app_bitacora_db_config_json($empresaId);
    $extra = array_filter((array) ($json['gh_cargos_extra'] ?? []), static fn($cargo) => trim((string) $cargo) !== '');
    $cargos = array_merge(app_bitacora_gh_base_cargos(), array_map('strval', $extra));

    return array_values(array_unique(array_map(static fn($cargo) => trim((string) $cargo), $cargos)));
}

function app_bitacora_group_item_field_name(string $groupName, int $index, string $fieldName): string
{
    return $groupName . '_' . $index . '_' . $fieldName;
}

function app_bitacora_detail_group_field_name(string $groupName, string $fieldName): string
{
    return $groupName . '_' . $fieldName;
}

function app_bitacora_yes_no_quantity_group_field(string $name, string $label, string $quantityName, string $quantityLabel, array $fields, array $extra = []): array
{
    return array_merge([
        'type' => 'yes_no_quantity_group',
        'name' => $name,
        'label' => $label,
        'quantity_name' => $quantityName,
        'quantity_label' => $quantityLabel,
        'required' => true,
        'min' => 1,
        'max' => 10,
        'item_label' => 'Registro',
        'fields' => $fields,
        'col' => 'col-md-12',
    ], $extra);
}

function app_bitacora_quantity_group_field(string $name, string $label, string $quantityName, string $quantityLabel, array $fields, array $extra = []): array
{
    return array_merge([
        'type' => 'quantity_group',
        'name' => $name,
        'label' => $label,
        'quantity_name' => $quantityName,
        'quantity_label' => $quantityLabel,
        'required' => true,
        'min' => 0,
        'max' => 10,
        'item_label' => 'Registro',
        'zero_report_value' => 'Sin registros',
        'fields' => $fields,
        'col' => 'col-md-12',
    ], $extra);
}

function app_bitacora_yes_no_detail_group_field(string $name, string $label, array $fields, array $extra = []): array
{
    return array_merge([
        'type' => 'yes_no_detail_group',
        'name' => $name,
        'label' => $label,
        'required' => true,
        'no_report_value' => 'Sin novedad',
        'fields' => $fields,
        'col' => 'col-md-12',
    ], $extra);
}

function app_bitacora_multiselect_detail_group_field(string $name, string $label, array $options, array $extra = []): array
{
    return array_merge([
        'type' => 'multiselect_detail_group',
        'name' => $name,
        'id' => $name,
        'label' => $label,
        'required' => true,
        'options' => $options,
        'detail_name' => $name . '_detalles',
        'no_apply_value' => 'No se tuvieron visitas el dia de hoy',
        'placeholder' => 'Escribe Nombre Apellido - Cargo',
        'help' => 'Registra cada visitante, si no aparece en el listado, con el formato Nombre Apellido - Cargo. Ejemplo: Juan Pérez - Auxiliar SST.',
        'col' => 'col-md-12',
    ], $extra);
}

function app_bitacora_weekday_from_date(string $date): ?int
{
    $date = trim($date);
    if ($date === '') {
        return null;
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return null;
    }

    return (int) date('w', $timestamp);
}

function app_bitacora_field_available_for_date(array $field, string $date): bool
{
    if (!array_key_exists('weekday_only', $field)) {
        return true;
    }

    $weekday = app_bitacora_weekday_from_date($date);
    return $weekday !== null && $weekday === (int) $field['weekday_only'];
}

function app_bitacora_default_form_sections(array $companyConfig, int $empresaId): array
{
    $afluencia = ['BAJA' => 'AFLUENCIA BAJA', 'MODERADA' => 'AFLUENCIA MODERADA', 'ALTA' => 'AFLUENCIA ALTA'];
    $ghCargos = app_bitacora_gh_cargos($empresaId);

    //Esquema de visitas de áreas Nombre - Cargo, para el multiselect de visitas de áreas. Se puede agregar más áreas y cargos según sea necesario.
    $visitasAreas = [
        'No se tuvieron visitas el dia de hoy' => 'No se tuvieron visitas el dia de hoy',
        'Alejandro Noguera - Supervisor de Mejoramiento y Calidad' => 'Alejandro Noguera - Supervisor de Mejoramiento y Calidad',
        'Fabian Salazar - Coordinador de Mejoramiento y Calidad' => 'Fabian Salazar - Coordinador de Mejoramiento y Calidad',
        'Angela Mesa - Entrenadora de Cocina y Bar' => 'Angela Mesa - Entrenadora de Cocina y Bar',
        'Brian Ortiz - Director de Franquicias' => 'Brian Ortiz - Director de Franquicias',
        'Camila Galvez - Auxiliar de Gestión Humana' => 'Camila Galvez - Auxiliar de Gestión Humana',
        'Cristian Giraldo - Auxiliar de TI' => 'Cristian Giraldo - Auxiliar de TI',
        'Edson Ramos - Coordinador de TI' => 'Edson Ramos - Coordinador de TI',
        'Esly González - Gestora de Comunicaciones y Experiencia' => 'Esly González - Gestora de Comunicaciones y Experiencia',
        'Johanna Findo - Auxiliar de SST' => 'Johanna Findo - Auxiliar de SST',
        'Julia Carabalí - Entrenadora de Cocina y Bar' => 'Julia Carabalí - Entrenadora de Cocina y Bar',
        'Leidy Casanova - Analista de Selección' => 'Leidy Casanova - Analista de Selección',
        'Lina Hernández - Jefe de Gestión Humana' => 'Lina Hernández - Jefe de Gestión Humana',
        'Nicol Muñoz - Supervisora de Mantenimiento' => 'Nicol Muñoz - Supervisora de Mantenimiento',
        'Pamela Valencia - Coordinadora de SST' => 'Pamela Valencia - Coordinadora de SST',
        'Paola Varón - Auxiliar de SST' => 'Paola Varón - Auxiliar de SST',
        'Sandra González - Tesorera' => 'Sandra Milena González -Tesorera',
        'Sandra Tapia - Subgerente' => 'Sandra Tapia - Subgerente',
        'Valentina Charry - Diseñadora' => 'Valentina Charry - Diseñadora',
        'Yeison Cabezas - Coordinador de Inventarios' => 'Yeison Cabezas - Coordinador de Inventarios',
    ];

    $formatosSalon = [
        'LIMPIEZA Y DESINFECCIÓN DEL ÁREA DE SALÓN',
        'LIMPIEZA Y DESINFECCIÓN DE LOS BAÑOS',
        'DEVOLUCIÓN DE PRODUCTOS DESDE LA MESA',
        'CONTROL Y ENTREGA DE OBSEQUIOS A CLIENTES',
        'REGISTRO DE RESIDUOS APROVECHABLES SEDES DE CENTROS COMERCIALES',
        'REGISTRO DE RESIDUOS ORGÁNICOS'
    ];

    $formatosCocina = [
        'PROCESADO COCINA LOMO VICHE',
        'PROCESADO PICO DE GALLO',
        'PROCESADO DE ENSALADAS',
        'PORCIONADOS VARIOS',
        'PROCESADO DE BBQ Y SOUR CREAM',
        'PROCESADOS DE COCINA (TOTOPOS, TOCINETA PICADA Y CILANTRO)',
        'PROCESADO DE PROTEINAS',
        'PROCESADO DE ARROZ MEXICANO',
        'PROGRAMACIÓN DE LAS ACTIVIDADES DIARIAS DE COCINA',
        'VERIFICACIÓN DEL PROCESO DE FREIDO',
        'REGISTRO DE PROCESADO DE VERDURAS',
        'REPORTE DE AVERIAS CRISTALERIA, UTENSILIOS Y/O PRODUCTO NO CONFORME',
        'TRATAMIENTO DE LA TRAMPA DE GRASA (LIMPIEZA Y APLICACIÓN DE LA BIOSA)',
        'MEDICIÓN DEL ACEITE DE COCINA USADO',
        'REGISTRO DE RESIDUOS ORGÁNICOS',
        'INSPECCIÓN DE VEHICULOS DE MATERIA PRIMA',
        'RECEPCIÓN E INSPECCIÓN DE MATERIAL DE EMPAQUE',
        'FORMATO LIMPIEZA Y DESINFECCION DE LA COCINA',
        'RECEPCIÓN E INSPECCIÓN DE MATERIA PRIMA DE COCINA',
        'CONTROL DIARIO DE TEMPERATURA EN EQUIPOS DE REFRIGERACIÓN',
        'CONTROL DIARIO DE TEMPERATURA EN EQUIPOS DE CONGELACIÓN',
        'LIMPIEZA Y DESINFECCIÓN DE BODEGA',
    ];

    $formatosBar = [
        'PROCESADO DE MELAO',
        'TRASLADO DE PRODUCTO ENTRE AREAS',
        'REGISTRO PROCESADOS DEL BAR',
        'CHECK LIST SURTIDO BAR',
        'REPORTE DE AVERIAS CRISTALERIA, UTENSILIOS Y/O PRODUCTO NO CONFORME',
        'REGISTRO GENERACION DE HIELO',
        'CONTROL DIARIO DE TEMPERATURA EN EQUIPOS DE REFRIGERACIÓN',
        'CONTROL DIARIO DE TEMPERATURA EN EQUIPOS DE CONGELACIÓN',
        'FORMATO LIMPIEZA Y DESINFECCION DE BAR',
        'CONTROL DE PH Y CLORO DE AGUA POTABLE',
        'RECEPCIÓN E INSPECCIÓN DE MATERIA PRIMA DEL BAR',
    ];

    $formatosChetano = [
        'PROCESADO DE PIÑA CALADA',
        'PROCESADO DE TOMATE ROSTIZADO',
        'REGISTRO DE PROCESADOS CHETANO',
        'PROCESADO DE PERA CALADA',
        'PROGRAMACIÓN DE LAS ACTIVIDADES DIARIAS DE COCINA',
        'PROCESADO DE MADURO HORNEADO',
        'PROCESADO DE FOCACCIA',
        'REPORTE DE AVERIAS CRISTALERIA, UTENSILIOS Y/O PRODUCTO NO CONFORME',
        'REGISTRO DE RESIDUOS ORGÁNICOS',
        'LIMPIEZA Y DESINFECCIÓN DE COCINA CHETANO',
        'CONTROL DIARIO DE TEMPERATURA EN EQUIPOS DE REFRIGERACIÓN',
        'CONTROL DIARIO DE TEMPERATURA EN EQUIPOS DE CONGELACIÓN',
        'RECEPCIÓN E INSPECCIÓN DE MATERIA PRIMA DE COCINA',
    ];

    $sections = [
        ['key' => 'base', 'title' => 'DATOS BÁSICOS', 'fields' => [
            app_bitacora_field('date', 'fechab', 'Fecha de bitácora', ['col' => 'col-md-6']),
            app_bitacora_field('select', 'sede', 'Sede', ['id' => 'idSede', 'options' => $companyConfig['sedes'] ?? [], 'selected' => $companyConfig['default_sede'] ?? null, 'col' => 'col-md-6']),
            app_bitacora_field('text', 'responsable', 'Responsable', ['col' => 'col-md-6']),
            app_bitacora_field('select', 'cargo', 'Cargo', ['options' => ['Coordinador/a', 'Cajero/a', 'Jefe/a de área'], 'col' => 'col-md-6']),
        ]],
        // Alfuencia, metricas e indicadores ()
        ['key' => 'desempenio', 'title' => 'DESEMPEÑO DE LA SEDE', 'fields' => [
            app_bitacora_subsection('afluencia', 'AFLUENCIA DE COMENSALES', 'Ingrese la afluencia de comensales durante la jornada'),
            app_bitacora_field('select', 'comens', 'AFLUENCIA MEDIO DÍA', ['options' => $afluencia, 'col' => 'col-md-4']),
            app_bitacora_field('select', 'comens1', 'AFLUENCIA TARDE', ['options' => $afluencia, 'col' => 'col-md-4']),
            app_bitacora_field('select', 'comens2', 'AFLUENCIA NOCHE', ['options' => $afluencia, 'col' => 'col-md-4']),

            app_bitacora_subsection('indicadores', 'INDICADORES DE DESEMPEÑO', 'Ingrese los indicadores de desempeño durante la jornada'),
            app_bitacora_field('number', 'pd', 'CUMPLIMIENTO PRESUPUESTO DIARIO', ['col' => 'col-md-3', 'suffix' => '%']),
            app_bitacora_field('number', 'tp', 'TICKET PROMEDIO', ['col' => 'col-md-3', 'number_format' => 'currency', 'number_decimals' => 0]),

            app_bitacora_subsection('metricas_servicios', 'METRÍCAS DE SERVICIOS', 'Ingrese las métricas de servicios durante la jornada'),
            app_bitacora_field('number', 'rappi', 'NÚMERO DE ÓRDENES RAPPI', ['col' => 'col-md-3', 'suffix_singular' => ' orden', 'suffix_plural' => ' ordenes']),
            app_bitacora_field('number', 'domi', 'NÚMERO DE DOMICILIOS', ['col' => 'col-md-3', 'suffix_singular' => 'domicilio', 'suffix_plural' => 'domicilios']),
            app_bitacora_field('number', 'domiciliarios', 'DOMICILIARIOS', ['col' => 'col-md-3', 'suffix_singular' => 'domicilliario', 'suffix_plural' => 'domiciliarios']),
            app_bitacora_field('number', 'hdomi', 'HORAS EMPLEADAS DOMICILIARIOS', ['col' => 'col-md-3', 'suffix_singular' => 'hora', 'suffix_plural' => 'horas']),
        ]],
        ['key' => 'visitas_areas', 'title' => 'VISITAS DE ÁREAS', 'fields' => [
            app_bitacora_multiselect_detail_group_field(
                'visitas_areas',
                'INGRESE LAS PERSONAS DE LAS DISTINTAS ÁREAS QUE VISITARON LA SEDE',
                $visitasAreas,
                ['order' => 10, 'col' => 'col-md-12', 'detail_label' => 'DETALLE DE LAS ACTIVIDADES REALIZADAS POR EL VISITANTE', 'detail_type' => 'textarea', 'detail_required' => true]
            ),
        ]],
        ['key' => 'mmto_contratistas', 'title' => 'EQUIPO DE MANTENIMIENTO Y CONTRATISTAS', 'fields' => [
            app_bitacora_yes_no_quantity_group_field(
                'visitas_contratistas',
                '¿HUBO VISITAS DEL EQUIPO DE MANTENIMIENTO O CONTRATISTAS EN LA SEDE?',
                'contratistas_cantidad',
                'CUÁNTOS AUXILIARES DE MANTENIMIENTO O CONTRATISTAS VISITARON LA SEDE',
                [
                    app_bitacora_field('text', 'nombre_contratista', 'NOMBRE DE AUXILIAR O CONTRATISTA', ['col' => 'col-md-3']),
                    app_bitacora_field('text', 'empresa', 'EMPRESA', ['col' => 'col-md-3']),
                    app_bitacora_field('textarea', 'detalle_contratista', 'DETALLE DE LAS ACTIVIDADES REALIZADAS POR EL AUXILIAR O CONTRATISTA', ['col' => 'col-md-6']),
                ], ['item_label' => 'EQUIPO MANTENIMIENTO/CONTRATISTA', 'order' => 10, 'no_report_value' => 'No se tuvieron visitas el dia de hoy.']
            ),
        ]],
        ['key' => 'descanso_coordinador', 'title' => 'DESCANSO DEL COORDINADOR DE SEDE', 'fields' => [
            app_bitacora_yes_no_field('descanso_coordinador', '¿EL COORDINADOR TOMÓ SU DESCANSO DURANTE LA JORNADA?', 'descanso_coordinadorGroup', 'fecha_descanso', 'FECHA DEL DESCANSO DEL COORDINADOR', 'date', ['detail_default_from' => 'fechab', 'no_report_value' => 'El coordinador trabajó según su turno programado.']),
        ]],
        ['key' => 'operaciones', 'title' => 'OPERACIONES', 'fields' => [
            //OBSERVACIONES JEFES
            app_bitacora_subsection('novedades_jefe_mesa', 'OBSERVACIONES JEFE DE SALÓN', 'Ingrese las observaciones del jefe de salón'),
            app_bitacora_yes_no_quantity_group_field(
                'salon_novedades',
                'NOVEDADES CON EL PERSONAL A CARGO',
                'salon_novedades_cantidad',
                'NÚMERO DE PERSONAS CON NOVEDADES',
                [
                    app_bitacora_field('text', 'nombre_colab_salon', 'NOMBRE DEL COLABORADOR', ['col' => 'col-md-4']),
                    app_bitacora_field('textarea', 'detalle_novedad_salon', 'MOTIVO DE LA NOVEDAD', ['col' => 'col-md-8']),
                ], ['item_label' => 'COLABORADOR', 'order' => 0, 'col' => 'col-md-6', 'no_report_value' => 'El dia de hoy se trabajo con el personal completo.']
            ),
            app_bitacora_yes_no_field('sac_novedades_yes_no', '¿HUBO NOVEDADES CON EL SERVICO AL CLIENTE?', 'sac_novedadesGroup', 'sac_novedades', 'DETALLE DE NOVEDADES CON EL SERVICIO AL CLIENTE', 'textarea', ['col' => 'col-md-6', 'no_report_value' => 'Sin novedades durante el servicio.']),
            app_bitacora_yes_no_field('devoluciones_novedades_yes_no', '¿HUBO NOVEDADES CON LOS PRODUCTOS (RETORNO A COCINA)?', 'devoluciones_novedadesGroup', 'devoluciones_novedades', 'DETALLE DE NOVEDADES CON DEVOLUCIONES DE PRODUCTO', 'textarea', ['col' => 'col-md-6', 'no_report_value' => 'No se retornaron productos el dia de hoy.']),
            //app_bitacora_yes_no_field('planillas_novedades_yes_no', 'FORMATOS DILIGENCIADOS DURANTE LA JORNADA', 'planillas_novedadesGroup', 'planillas_novedades', 'DETALLE DE FORMATOS DILIGENCIADOS', 'textarea', ['col' => 'col-md-6', 'no_report_value' => 'El dia de hoy no se diligenciaron formatos.']),
            app_bitacora_field('multiselect', 'formatos_salon', 'FORMATOS DILIGENCIADOS DURANTE LA JORNADA', ['col' => 'col-md-6', 'options' => $formatosSalon, 'required' => false]),
            app_bitacora_subsection('novedades_jefe_cocina', 'OBSERVACIONES JEFE DE COCINA', 'Ingrese las observaciones del jefe de cocina'),
            app_bitacora_yes_no_quantity_group_field(
                'cocina_novedades',
                'NOVEDADES CON EL PERSONAL A CARGO',
                'cocina_novedades_cantidad',
                'NÚMERO DE PERSONAS CON NOVEDADES',
                [
                    app_bitacora_field('text', 'nombre_colab_cocina', 'NOMBRE DEL COLABORADOR', ['col' => 'col-md-4']),
                    app_bitacora_field('textarea', 'detalle_novedad_cocina', 'MOTIVO DE LA NOVEDAD', ['col' => 'col-md-8']),
                ], ['item_label' => 'COLABORADOR', 'order' => 0, 'col' => 'col-md-12', 'no_report_value' => 'El dia de hoy se trabajo con el personal completo.']
            ),
            //app_bitacora_yes_no_field('procesados_novedades_yes_no', '¿CUALES PROCESADOS SE REALIZARON DURANTE LA JORNADA?', 'procesados_novedadesGroup', 'procesados_novedades', 'DETALLE LOS PROCESADOS REALIZADOS', 'textarea', ['col' => 'col-md-6', 'no_report_value' => 'El dia de hoy no se realizaron procesados.']),
            //app_bitacora_yes_no_field('productos_cocina_novedades_yes_no', 'NOVEDADES CON LOS PRODUCTOS (PROXIMOS A VENCER)', 'productos_cocina_novedadesGroup', 'productos_cocina_novedades', 'DETALLE LOS PRODUCTOS PROXIMOS A VENCER PARA IMPULSAR SU VENTA', 'textarea', ['col' => 'col-md-6', 'no_report_value' => 'Sin productos próximos a vencer.']),
            //app_bitacora_yes_no_field('planillas_cocina_novedades_yes_no', 'FORMATOS DILIGENCIADOS DURANTE LA JORNADA', 'planillas_cocina_novedadesGroup', 'planillas_cocina_novedades', 'DETALLE DE FORMATOS DILIGENCIADOS', 'textarea', ['col' => 'col-md-6', 'no_report_value' => 'El dia de hoy no se diligenciaron formatos.']),
            app_bitacora_field('multiselect', 'formatos_cocina', 'FORMATOS DILIGENCIADOS DURANTE LA JORNADA', ['col' => 'col-md-12', 'options' => $formatosCocina, 'required' => false]),
            app_bitacora_subsection('novedades_jefe_bar', 'OBSERVACIONES JEFE DE BAR', 'Ingrese las observaciones del jefe de bar'),
            app_bitacora_yes_no_quantity_group_field(
                'bar_novedades',
                'NOVEDADES CON EL PERSONAL A CARGO',
                'bar_novedades_cantidad',
                'NÚMERO DE PERSONAS CON NOVEDADES',
                [
                    app_bitacora_field('text', 'nombre_colab_bar', 'NOMBRE DEL COLABORADOR', ['col' => 'col-md-4']),
                    app_bitacora_field('textarea', 'detalle_novedad_bar', 'MOTIVO DE LA NOVEDAD', ['col' => 'col-md-8']),
                ], ['item_label' => 'COLABORADOR', 'order' => 0, 'col' => 'col-md-12',  'no_report_value' => 'El dia de hoy se trabajo con el personal completo.']
            ),
            //app_bitacora_yes_no_field('procesados_bar_novedades_yes_no', '¿CUALES PROCESADOS SE REALIZARON DURANTE LA JORNADA?', 'procesados_bar_novedadesGroup', 'procesados_bar_novedades', 'DETALLE LOS PROCESADOS REALIZADOS', 'textarea', ['col' => 'col-md-6', 'no_report_value' => 'El dia de hoy no se realizaron procesados.']),
            //app_bitacora_yes_no_field('productos_bar_novedades_yes_no', 'NOVEDADES CON LOS PRODUCTOS (PROXIMOS A VENCER)', 'productos_bar_novedadesGroup', 'productos_bar_novedades', 'DETALLE LOS PRODUCTOS PROXIMOS A VENCER PARA IMPULSAR SU VENTA', 'textarea', ['col' => 'col-md-6', 'no_report_value' => 'Sin productos próximos a vencer.']),
            //app_bitacora_yes_no_field('planillas_bar_novedades_yes_no', 'FORMATOS DILIGENCIADOS DURANTE LA JORNADA', 'planillas_bar_novedadesGroup', 'planillas_bar_novedades', 'DETALLE DE FORMATOS DILIGENCIADOS','textarea', ['col' => 'col-md-6', 'no_report_value' => 'El dia de hoy no se diligenciaron formatos.']),
            app_bitacora_field('multiselect', 'formatos_bar', 'FORMATOS DILIGENCIADOS DURANTE LA JORNADA', ['col' => 'col-md-12', 'options' => $formatosBar, 'required' => false]),
            app_bitacora_yes_no_field('hielo_produ', '¿HUBO PRODUCCIÓN DE HIELO?', 'hieloGroup', 'hielo', 'CANTIDAD PRODUCIDA (UNIDADES)', 'number', ['col'=>'col-md-3', 'suffix_singular' => 'bolsa', 'suffix_plural' => 'bolsas', 'no_report_value' => 'El dia de hoy no se produjo.']),
            app_bitacora_yes_no_field('hielo_kolbitos', '¿SE COMPRA HIELO A KOLBITOS?', 'hielo1Group', 'hielo1', 'CANTIDAD COMPRADA (UNIDADES)', 'number', ['col'=>'col-md-3', 'suffix_singular' => 'bolsa', 'suffix_plural' => 'bolsas', 'no_report_value' => 'No se realizó compra de hielo.']),
            app_bitacora_yes_no_field('hielo_consumo', '¿HUBO CONSUMO DE HIELO?', 'hielo2Group', 'hielo2', 'CANTIDAD CONSUMIDA (UNIDADES)', 'number', ['col'=>'col-md-3', 'suffix_singular' => 'bolsa', 'suffix_plural' => 'bolsas', 'no_report_value' => 'No hubo consumo.']),
            app_bitacora_field('number', 'hielo3', 'INVENTARIO DE HIELO AL FINAL DE LA JORNADA (UNIDADES)', ['col' => 'col-md-3', 'suffix_singular' => 'bolsa', 'suffix_plural' => 'bolsas']),
            app_bitacora_yes_no_field('hielo_enviado', '¿SE HA ENVIADO HIELO A OTRA SEDE?', 'hielo4Group', 'hielo4', 'DETALLE DE ENVÍO DE HIELO', 'textarea', ['col' => 'col-md-6']),
            app_bitacora_yes_no_field('hielo_recibido', '¿SE HA RECIBIDO HIELO DE OTRA SEDE?', 'hielo5Group', 'hielo5', 'DETALLE DE RECEPCIÓN DE HIELO', 'textarea', ['col' => 'col-md-6']),

            app_bitacora_subsection('novedades_reservas', 'RESERVAS', 'Ingrese las reservas realizadas del día'),
            app_bitacora_yes_no_quantity_group_field(
                'reservas',
                '¿HUBO RESERVAS DURANTE LA JORNADA?',
                'reservas_cantidad',
                '¿CUÁNTAS RESERVAS SE REALIZARON?',
                [
                    app_bitacora_field('select', 'tipo_reserva', 'TIPO DE RESERVA', ['options' => ['Personal' => 'Personal', 'Empresarial' => 'Empresarial'], 'col' => 'col-md-4']),
                    app_bitacora_field('text', 'nombre_cliente', 'NOMBRE DEL CLIENTE O EMPRESA', ['col' => 'col-md-4']),
                    app_bitacora_field('date', 'fecha_reserva', 'FECHA DE LA RESERVA', ['col' => 'col-md-4']),
                    app_bitacora_field('number', 'cantidad_personas', 'NÚMERO DE PERSONAS', ['min' => 1, 'max' => 1000, 'step' => 1, 'col' => 'col-md-4']),
                    app_bitacora_field('text','motivo_reserva','MOTIVO DE LA RESERVA',['col' => 'col-md-4']),
                    app_bitacora_field('simple_radio','decoracion_reserva','¿REQUIERE DECORACIÓN?', ['col' => 'col-md-4']),
                ], ['item_label' => 'RESERVA #', 'order' => 0, 'no_report_value' => 'No se recibieron reservas el dia de hoy.']
            ),

            app_bitacora_subsection('domicilios', 'NOVEDADES DOMICILIOS', 'Ingrese las novedades de domicilios durante la jornada'),
            app_bitacora_yes_no_field('novedades_rappi', '¿NOVEDADES CON RAPPI?', 'dorpGroup', 'dorp', 'DETALLE LAS NOVEDADES CON RAPPI', 'textarea', ['col' => 'col-md-6', 'no_report_value' => 'No hubo novedades con Rappi.']),
            app_bitacora_yes_no_field('novedades_domi', '¿NOVEDADES CON DOMICILIOS PROPIOS?', 'dompGroup', 'domp', 'DETALLE LAS NOVEDADES CON DOMICILIOS PROPIOS', 'textarea', ['col' => 'col-md-6', 'no_report_value' => 'No hubo novedades con Domicilios propios.']),
            //app_bitacora_field('textarea', 'domp', 'NOVEDADES CON DOMICILIOS PROPIOS', ['col' => 'col-md-6']),
        ]],
        ['key' => 'mercadeo', 'title' => 'MERCADEO', 'fields' => [
            app_bitacora_yes_no_detail_group_field('material_pop', '¿SE RECIBIÓ MATERIAL POP?', [
                app_bitacora_field('textarea', 'tipo_material', '¿QUÉ MATERIAL SE RECIBIÓ?', ['placeholder' => 'Ejemplo: Volantes, afiches, etc.', 'col' => 'col-md-4']),
                app_bitacora_field('textarea', 'cantidad_material', '¿CUÁNTAS UNIDADES SE RECIBIERON?', ['col' => 'col-md-4']),
                app_bitacora_field('text', 'quien_recibe', '¿QUIÉN RECIBE EL MATERIAL?', ['col' => 'col-md-4']),
            ], ['order' => 0, 'col' => 'col-md-12', 'no_report_value' => 'No por el dia de hoy.']),
            app_bitacora_quantity_group_field(
                'actividades_mercadeo',
                'ACTIVIDADES/CAMPAÑAS',
                'actividades_mercadeo_cantidad',
                'CUÁNTAS ACTIVIDADES/CAMPAÑAS SE REALIZARON EN EL DÍA', [
                app_bitacora_field('text', 'actividad_mercadeo', 'NOMBRE DE ACTIVIDAD/CAMPAÑA', ['col' => 'col-md-4']),
                app_bitacora_field('textarea', 'resultados_actividad', 'DESCRIBA AQUÍ LOS DETALLES Y/O RESULTADOS, COMENTARIOS DE LOS CLIENTES', ['col' => 'col-md-4']),
                app_bitacora_field('simple_radio','decoracion_actividad','¿HUBO DECORACIÓN EN LA SEDE?', ['col' => 'col-md-4']),
                //app_bitacora_field('textarea','souvenirs','ENTREGA DE SOUVENIRS A LOS CLIENTES',['placeholder' => 'Ingrese la cantidad de souvenirs entregados y el tipo de souvenir entregado de acuerdo a la actividad. Ejemplo: 10 llaveros, 5 gorras, etc.','col' => 'col-md-8']),
            ], ['item_label' => 'ACTIVIDAD/CAMPAÑA', 'order' => 10, 'zero_report_value' => 'No se realizaron actividades de mercadeo el día de hoy.', 'suffix_singular' => 'campaña', 'suffix_plural' => 'campañas']),
            app_bitacora_yes_no_field('souvenirs', 'ENTREGA DE SOUVENIRS A LOS CLIENTES', 'souvenirsGroup', 'souvenirs_detalle','Ingrese la cantidad de souvenirs entregados y el tipo de souvenir entregado de acuerdo a la actividad. Ejemplo: 10 llaveros, 5 gorras, etc.', 'textarea',['col' => 'col-md-12', 'no_report_value' => 'No se entregaron souvenirs.', 'order' => 20]),
            app_bitacora_yes_no_quantity_group_field(
                'casos_reportados',
                '¿CASOS REPORTADOS EN EL HELPDESK DURANTE LA JORNADA?',
                'casos_reportados_cantidad',
                'CANTIDAD DE CASOS REPORTADOS', [
                app_bitacora_field('number', 'numero_caso', 'NÚMERO DEL CASO', ['col' => 'col-md-4']),
                app_bitacora_field('textarea', 'detalle_caso', 'DESCRIPCIÓN DEL CASO REPORTADO', ['col' => 'col-md-8']),
            ], ['item_label' => 'CASO REPORTADO', 'order' => 30, 'no_report_value' => 'No se reportaron casos el dia de hoy.', 'col' => 'col-md-6']),
            app_bitacora_yes_no_quantity_group_field(
                'casos_pendientes',
                '¿HAY CASOS PENDIENTES EN EL HELPDESK?',
                'casos_pendientes_cantidad',
                'CANTIDAD DE CASOS PENDIENTES', [
                app_bitacora_field('number', 'numero_caso', 'NÚMERO DEL CASO', ['col' => 'col-md-4']),
                app_bitacora_field('textarea', 'detalle_caso', 'PENDIENTE', ['col' => 'col-md-8']),
            ], ['item_label' => 'CASO PENDIENTE', 'order' => 40, 'no_report_value' => 'No hay casos pendientes.', 'col' => 'col-md-6']),
        ]],
        ['key' => 'gestion_humana', 'title' => 'GESTIÓN HUMANA', 'fields' => [
            app_bitacora_yes_no_quantity_group_field(
                'gh_vacantes_abiertas',
                '¿SE CUENTA CON VACANTES ABIERTAS ACTUALMENTE?',
                'gh_vacantes_cantidad',
                'NÚMERO DE PERSONAS REQUERIDAS',
                [
                    app_bitacora_field('select', 'cargo_requerido', 'CARGO REQUERIDO', ['options' => $ghCargos, 'col' => 'col-md-6']),
                    app_bitacora_field('text', 'reemplaza', 'A QUIÉN REEMPLAZA', ['col' => 'col-md-6']),
                    app_bitacora_field('textarea', 'motivo_retiro', 'MOTIVO DEL RETIRO'),
                ], ['item_label' => 'VACANTE', 'order' => 10, 'col' => 'col-md-6', 'no_report_value' => 'No se tienen vacantes pendientes.']
            ),
            /*app_bitacora_yes_no_quantity_group_field(
                'gh_proceso_disciplinario',
                '¿SE REALIZÓ EL DÍA DE HOY POR CORREO ELECTRÓNICO ALGUNA SOLICITUD DE PROCESO DISCIPLINARIO?',
                'gh_proceso_disciplinario_cantidad',
                'A CUÁNTAS PERSONAS',
                [
                    app_bitacora_field('text', 'nombre_colaborador', 'NOMBRE DEL COLABORADOR', ['col' => 'col-md-6']),
                    app_bitacora_field('select', 'cargo', 'CARGO', ['options' => $ghCargos, 'col' => 'col-md-6']),
                    app_bitacora_field('textarea', 'motivo', 'MOTIVO', ['col' => 'col-md-12']),
                ], ['item_label' => 'COLABORADOR', 'order' => 20, 'col' => 'col-md-6']
            ),
            app_bitacora_yes_no_quantity_group_field(
                'gh_notificacion_interna',
                '¿SE REALIZÓ EL DÍA DE HOY ALGUNA NOTIFICACIÓN INTERNA?',
                'gh_notificacion_interna_cantidad',
                'A CUÁNTAS PERSONAS',
                [
                    app_bitacora_field('text', 'nombre_colaborador', 'NOMBRE DEL COLABORADOR', ['col' => 'col-md-6']),
                    app_bitacora_field('select', 'cargo', 'CARGO', ['options' => $ghCargos, 'col' => 'col-md-6']),
                    app_bitacora_field('textarea', 'motivo', 'MOTIVO', ['col' => 'col-md-12']),
                ], ['item_label' => 'NOTIFICACIÓN', 'order' => 30, 'col' => 'col-md-6']
            ),*/
            app_bitacora_yes_no_quantity_group_field(
                'gh_vacaciones',
                '¿HAY NOVEDADES DEL PERSONAL POR VACACIONES?',
                'gh_vacaciones_cantidad',
                'CUÁNTAS PERSONAS ESTÁN EN VACACIONES',
                [
                    app_bitacora_field('text', 'nombre_colaborador', 'NOMBRE DEL COLABORADOR', ['col' => 'col-md-6']),
                    app_bitacora_field('select', 'cargo', 'CARGO', ['options' => $ghCargos, 'col' => 'col-md-6']),
                    app_bitacora_field('date', 'fecha_inicio', 'FECHA INICIO', ['col' => 'col-md-6']),
                    app_bitacora_field('date', 'fecha_final', 'FECHA FINAL', ['col' => 'col-md-6']),
                ], ['item_label' => 'COLABORADOR', 'order' => 20, 'col' => 'col-md-6', 'no_report_value' => 'Sin novedad alguna.']
            ),
            app_bitacora_yes_no_detail_group_field(
                'gh_reingreso_vacaciones',
                '¿HUBO REINGRESO DEL PERSONAL EN VACACIONES?',
                [
                    app_bitacora_field('text', 'nombre_colaborador', 'NOMBRE DEL COLABORADOR', ['col' => 'col-md-12']),
                ], ['order' => 30, 'col' => 'col-md-6', 'no_report_value' => 'No se presento ningun reingreso.']
            ),
            app_bitacora_yes_no_quantity_group_field(
                'gh_ingreso_personal',
                '¿HUBO INGRESO DE PERSONAL NUEVO?',
                'gh_ingreso_personal_cantidad',
                'CUÁNTAS PERSONAS INGRESARON EL DÍA DE HOY',
                [
                    app_bitacora_field('text', 'nombre_colaborador', 'NOMBRE DEL COLABORADOR', ['col' => 'col-md-6']),
                    app_bitacora_field('select', 'cargo', 'CARGO', ['options' => $ghCargos, 'col' => 'col-md-6']),
                    app_bitacora_field('date', 'fecha_ingreso', 'FECHA INGRESO', ['col' => 'col-md-6']),
                    app_bitacora_field('text', 'tutor_asignado', 'NOMBRE DEL TUTOR ASIGNADO', ['col' => 'col-md-6']),
                ], ['item_label' => 'COLABORADOR', 'order' => 40, 'col' => 'col-md-6', 'no_report_value' => 'No por el dia de hoy.']
            ),
            app_bitacora_yes_no_quantity_group_field(
                'gh_periodo_prueba',
                '¿SE REALIZÓ SEGUIMIENTO DEL PERSONAL EN PERIODO DE PRUEBA?',
                'gh_periodo_prueba_cantidad',
                'CUÁNTAS PERSONAS ESTÁN EN PERIODO DE PRUEBA',
                [
                    app_bitacora_field('text', 'nombre_colaborador', 'NOMBRE DEL COLABORADOR'),
                    app_bitacora_field('select', 'cargo', 'CARGO', ['options' => $ghCargos]),
                    app_bitacora_field('date', 'fecha_ingreso', 'FECHA INGRESO'),
                    app_bitacora_field('date', 'fecha_finalizacion', 'FECHA FINALIZACIÓN PERIODO DE PRUEBA'),
                    app_bitacora_field('number', 'nota_recetario', 'NOTA DE EVALUACIÓN DEL RECETARIO (1 A 5)', ['min' => 1, 'max' => 5, 'step' => '0.1']),
                    app_bitacora_field('number', 'nota_general', 'NOTA GENERAL (1 A 5)', ['min' => 1, 'max' => 5, 'step' => '0.1']),
                    app_bitacora_field('textarea', 'observaciones', 'OBSERVACIONES DEL DESEMPEÑO DURANTE LA SEMANA'),
                ], ['item_label' => 'COLABORADOR EN PERIODO DE PRUEBA', 'weekday_only' => 1, 'availability_message' => 'Este seguimiento solo se diligencia los lunes según la fecha de bitácora.', 'order' => 70, 'col' => 'col-md-6', 'no_report_value' => 'No hay seguimientos por realizar.']
            ),
        ]],
        ['key' => 'sst', 'title' => 'SEGURIDAD Y SALUD EN EL TRABAJO - SST', 'fields' => [
            app_bitacora_yes_no_field('accidentes_sst', 'EVENTOS POR INCIDENTES LABORALES, ACCIDENTES LABORALES Y DE TRANSITO', 'sst1Group', 'sst1', 'Ingrese el detalle de los eventos por incidentes laborales, accidentes laborales y de tránsito ocurridos a los coaboradores durante la jornada.', 'textarea', ['col' => 'col-md-6', 'no_report_value' => 'Sin eventos para reportar.']),
            app_bitacora_yes_no_field('incapacidades_sst', 'INCAPACIDADES IGUALES O MAYORES A 15 DÍAS', 'sst2Group', 'sst2', 'Ingrese el detalle de las incapacidades iguales o mayores a 15 días ocurridas a los coaboradores.', 'textarea', ['col' => 'col-md-6', 'no_report_value' => 'Sin ningun caso para reportar.']),
            app_bitacora_yes_no_field('ambiente_laboral', 'HALLAZGOS POR AMBIENTE LABORAL', 'sst3Group', 'sst3', 'Ingrese el detalle de los hallazgos por ambiente laboral ocurridos durante la jornada.'),
            app_bitacora_yes_no_field('senal_sst', 'REPORTES DE EXTINTORES Y SEÑALIZACIÓN', 'sst4Group', 'sst4', 'Ingrese el detalle de los reportes de extintores y señalización ocurridos durante la jornada.'),
            app_bitacora_yes_no_field('entrega_epp', 'ENTREGA DE EPP (ELEMENTOS DE PROTECCIÓN PERSONAL)', 'sst6Group', 'sst6', 'Ingrese el detalle de la entrega de EPP (Elementos de Protección Personal) a los colaboradores durante la jornada.'),
            app_bitacora_yes_no_field('novedades_sst', 'OTRAS NOVEDADES (SITUACIONES DE SALUD, CONDICIONES, ACTOS INSEGUROS, ETC)', 'sst8Group', 'sst8', 'Ingrese el detalle de las novedades que no estén incluidas en las categorías anteriores.', 'textarea', ['col' => 'col-md-6', 'no_report_value' => 'Sin novedades por reportar.']),
        ]],
        ['key' => 'ti', 'title' => 'SISTEMAS - TI', 'fields' => [
            app_bitacora_yes_no_field('equipos_ti', '¿HUBO NOVEDADES EN LOS EQUIPOS TI O EN LA INFRAESTRUCTURA DE RED?', 'tiGroup', 'ti', 'Describa detalladamente las novedades en los equipos de TI o en la infraestructura de red.'),
            app_bitacora_yes_no_field('facturas_ti', '¿HUBO NOVEDADES EN LA FACTURACIÓN ELECTRÓNICA?', 'ti1Group', 'ti1', 'Detalle de las novedades en la facturación electrónica.'),
            app_bitacora_yes_no_field('novedades_ti', '¿HUBO OTRAS NOVEDADES RELACIONADAS CON TI?', 'ti2Group', 'ti2', 'Describa las novedades presentadas que tengan pertinencia con el área de TI.'),
            app_bitacora_yes_no_quantity_group_field(
                'casos_ti',
                '¿HUBO CASOS REGISTRADOS PARA TI EN HELPDESK?',
                'casos_ti_cantidad',
                'CANTIDAD DE CASOS REGISTRADOS', [
                app_bitacora_field('number', 'numero_caso', 'NÚMERO DEL CASO DE HELPDESK', ['col' => 'col-md-4']),
                app_bitacora_field('textarea', 'detalle_caso', 'DETALLE DE LA NOVEDAD PRESENTADA', ['col' => 'col-md-8']),
            ], ['item_label' => 'CASO DE TI', 'order' => 10, 'no_report_value' => 'No se reportaron casos el dia de hoy.', 'col' => 'col-md-6']),
        ]],
        ['key' => 'mantenimiento', 'title' => 'MANTENIMIENTO', 'fields' => [
            app_bitacora_yes_no_quantity_group_field('casos_mantenimiento',
                '¿CASOS REPORTADOS EN EL HELPDESK DURANTE LA JORNADA?',
                'casos_mantenimiento_cantidad',
                'CANTIDAD DE CASOS REPORTADOS', [
                app_bitacora_field('number', 'numero_caso', 'NÚMERO DEL CASO', ['col' => 'col-md-4']),
                app_bitacora_field('textarea', 'detalle_caso', 'DESCRIPCIÓN DEL CASO REPORTADO', ['col' => 'col-md-8']),
            ], ['item_label' => 'CASO DE MANTENIMIENTO', 'order' => 10, 'no_report_value' => 'No se reportaron casos el dia de hoy.', 'col' => 'col-md-6']),
            app_bitacora_yes_no_quantity_group_field('pendientes_cerrar',
                '¿HAY CASOS PENDIENTES EN EL HELPDESK?',
                'pendientes_cerrar_cantidad',
                'CANTIDAD DE PENDIENTES POR CERRAR', [
                app_bitacora_field('number', 'numero_pendiente', 'NÚMERO DEL CASO PENDIENTE', ['col' => 'col-md-4']),
                app_bitacora_field('textarea', 'detalle_pendiente', 'DESCRIPCIÓN DEL PENDIENTE', ['col' => 'col-md-8']),
            ], ['item_label' => 'PENDIENTE POR CERRAR', 'order' => 20, 'no_report_value' => 'No hay casos pendientes.', 'col' => 'col-md-6']),
            app_bitacora_field('plant', 'planta_elect', '¿SE USÓ LA PLANTA ELÉCTRICA?', ['col' => 'col-md-6', 'order' => 30]),
            app_bitacora_yes_no_field('novedades_planta', 'NOVEDADES DE PLANTA ELÉCTRICA', 'mant8Group', 'mant8', 'Ingrese las novedades de la planta eléctrica ocurridas durante la jornada.', 'textarea', ['col' => 'col-md-6', 'order' => 40, 'no_report_value' => 'Sin novedades por reportar.']),
        ]],
        ['key' => 'mejoramiento', 'title' => 'MEJORAMIENTO Y ESTANDARIZACIÓN (CALIDAD Y AMBIENTAL)', 'fields' => [
            app_bitacora_yes_no_field('visita_ss', '¿HUBO VISITA DE LA SECRETARÍA DE SALUD?', 'bpm1Group', 'bpm1', "Describa aquí los detalles de la visita: Nombre de los funcionarios que visitaron la sede. ¿Cuáles fueron los resultados? ¿Qué recomendaciones dejaron?",'textarea', ['col' => 'col-md-6', 'no_report_value' => 'No se recibio ninguna visita.']),
            app_bitacora_yes_no_field('visita_dagma', '¿HUBO VISITA DEL DAGMA?', 'bpm2Group', 'bpm2', "Describa aquí los detalles de la visita: Nombre de los funcionarios que visitaron la sede. ¿Cuales fueron los resultados? ¿Que recomendaciones dejaron?", 'textarea', ['col' => 'col-md-6', 'no_report_value' => 'No se recibio ninguna visita.']),
            app_bitacora_yes_no_field('visita_west', '¿HUBO VISITA DEL PROVEEDOR WEST QUIMICA?', 'bpm3Group', 'bpm3', "Describa aquí los detalles de la visita: ¿Cuáles fueron los resultados de la visita? ¿Qué equipos intervino, reparo o cambio? ¿Que recomendaciones dejo?", 'textarea', ['col' => 'col-md-4', 'no_report_value' => 'El dia de hoy no se recibio ninguna visita.']),
            app_bitacora_yes_no_field('visita_cp', '¿HUBO VISITA DEL PROVEEDOR DE CONTROL DE PLAGAS?', 'bpm4Group', 'bpm4', "Describa aquí los detalles de la visita: ¿Cuáles fueron los resultados de la visita? ¿Qué areas se intervinieron? ¿Qué recomendaciones dejo?", 'textarea', ['col' => 'col-md-4', 'no_report_value' => 'El dia de hoy no se fumigo.']),
            app_bitacora_yes_no_field('visita_acu', '¿HUBO VISITA DEL PROVEEDOR QUE RECOGE EL ACU?', 'bpm5Group', 'bpm5', "Describa aquí los detalles de la visita: ¿Cuáles fueron los resultados de la visita? ¿Cuántos bidonos se entregaron? ¿Qué recomendaciones dejo?", 'textarea', ['col' => 'col-md-4', 'no_report_value' => 'El dia de hoy no se realizo ninguna entrega.']),
        ]],
        ['key' => 'despensa', 'title' => 'DESPENSA', 'fields' => [
            //app_bitacora_field('textarea', 'desp', 'INGRESE LAS NOVEDADES RELACIONADAS CON MATERIAS PRIMAS DE DESPENSA', ['col' => 'col-md-12']),
            app_bitacora_yes_no_field('novedades_despensa', '¿NOVEDADES RELACIONADAS CON DESPENSA?', 'despGroup', 'desp', 'INGRESE LAS NOVEDADES RELACIONADAS CON MATERIAS PRIMAS DE DESPENSA', 'textarea', ['col' => 'col-md-12', 'no_report_value' => 'No hubo novedades con Despensa.']),
        ]],
        ['key' => 'tesoreria', 'title' => 'TESORERÍA', 'fields' => [
            app_bitacora_field('text', 'tesor1', '¿QUIEN CIERRA LA CAJA?', ['col' => 'col-md-4']),
            app_bitacora_yes_no_detail_group_field(
                'tesor2',
                '¿HUBO DIFERENCIA EN EL CIERRE DE CAJA?', [
                app_bitacora_field('number', 'diferencia_valor', 'VALOR DE LA DIFERENCIA', ['col' => 'col-md-6', 'number_format' => 'currency', 'number_decimals' => 0]),
                app_bitacora_field('select', 'diferencia_tipo', 'TIPO DE DIFERENCIA', ['options' => ['Faltante' => 'Faltante', 'Sobrante' => 'Sobrante'], 'col' => 'col-md-6']),
                app_bitacora_field('textarea', 'observacion_caja', 'OBSERVACIONES', ['required' => false, 'col' => 'col-md-12']),
            ], ['col' => 'col-md-8', 'no_report_value' => 'La caja queda cuadrada el dia de hoy.']),
            app_bitacora_yes_no_field('novedades_cierre_caja','¿HUBO NOVEDADES EN EL CIERRE DE CAJA?', 'cierreGroup', 'cierre_caja', 'INGRESE LAS NOVEDADES PRESENTADAS EN EL CIERRE', 'textarea', ['col' => 'col-md-12']),
            app_bitacora_yes_no_quantity_group_field(
                'tesor3',
                'INVITACIONES',
                'tesor3_cantidad',
                'NÚMERO DE INVITACIONES', [
                app_bitacora_field('text', 'nombre_invitacion', 'A NOMBRE DE QUIEN FUE LA INVITACIÓN', ['col' => 'col-md-4']),
                app_bitacora_field('text', 'autoriza_invitacion', 'AUTORIZADO POR', ['col' => 'col-md-4']),
                app_bitacora_field('number', 'valor_invitacion', 'VALOR DE LA INVITACIÓN', ['col' => 'col-md-4', 'number_format' => 'currency', 'number_decimals' => 0]),
            ], ['item_label' => 'INVITACIÓN', 'order' => 200, 'no_report_value' => 'No hubo invitaciones.', 'col' => 'col-md-12']),
        ]],
        ['key' => 'contabilidad', 'title' => 'CONTABILIDAD', 'fields' => [
            app_bitacora_yes_no_field('facturas_mesas', 'FACTURAS ANULADAS EN MESAS', 'fa_mesasGroup', 'fa_mesas', 'Ingrese el detalle de las facturas anuladas en mesas durante la jornada.', 'textarea', ['col' => 'col-md-4']),
            app_bitacora_yes_no_field('facturas_domic', 'FACTURAS ANULADAS EN DOMICILIOS', 'fa_domGroup', 'fa_dom', 'Ingrese el detalle de las facturas anuladas en domicilios durante la jornada.', 'textarea', ['col' => 'col-md-4']),
            app_bitacora_yes_no_field('facturas_rappi', 'FACTURAS ANULADAS EN RAPPI', 'fa_rappiGroup', 'fa_rappi', 'Ingrese el detalle de las facturas anuladas en Rappi durante la jornada.', 'textarea', ['col' => 'col-md-4']),
            app_bitacora_yes_no_field('bonos_redimidos', 'REDENCIONES DE BONOS', 'bonosGroup', 'bonos_redem', 'Ingrese el detalle de las redenciones de bonos durante el día.', 'textarea', ['col' => 'col-md-6', 'no_report_value' => 'El dia de hoy no se redimieron bonos.']),
            app_bitacora_yes_no_field('visita_dian', '¿HUBO VISITA DE LA DIAN DURANTE EL DÍA?', 'visita_dianGroup', 'visita_d', 'Describa aquí los detalles de la visita: Nombre de los funcionarios. ¿Cuáles fueron los resultados? ¿Qué recomendaciones dejo?', 'textarea', ['col' => 'col-md-6', 'no_report_value' => 'No se recibio ninguna visita.']),
        ]],
    ];

    // Agregamos campos de Chetano si la empresa tiene habilitado el módulo de Chetano en alguna sede
    $chetanoSedes = [];
    foreach (['PANCE', 'UNICENTRO'] as $sede) {
        if (in_array($sede, (array) ($companyConfig['sedes'] ?? []), true)
            && app_bitacora_extra_enabled($companyConfig, 'chetano', $sede)) {
            $chetanoSedes[] = $sede;
        }
    }

    // Si hay sedes de Chetano habilitadas, agregamos los campos correspondientes a la sección de operaciones
    if ($chetanoSedes !== []) {
        foreach ($sections as &$section) {
            if ((string) ($section['key'] ?? '') !== 'operaciones') {
                continue;
            }

            $chetanoFields = [
                app_bitacora_subsection('novedades_chetano', 'OBSERVACIONES JEFE DE CHETANO', 'Ingrese las observaciones de Chetano', ['sedes' => $chetanoSedes]),
                app_bitacora_yes_no_quantity_group_field(
                    'chetano_novedades',
                    'NOVEDADES CON EL PERSONAL A CARGO',
                    'chetano_novedades_cantidad',
                    'NÚMERO DE PERSONAS CON NOVEDADES',
                    [
                        app_bitacora_field('text', 'nombre_colab_chetano', 'NOMBRE DEL COLABORADOR', ['col' => 'col-md-4']),
                        app_bitacora_field('textarea', 'detalle_novedad_chetano', 'MOTIVO DE LA NOVEDAD', ['col' => 'col-md-8']),
                    ],
                    ['item_label' => 'COLABORADOR', 'order' => 0, 'sedes' => $chetanoSedes, 'col' => 'col-md-6', 'no_report_value' => 'El dia de hoy se trabajo con el personal completo.']
                ),
                //app_bitacora_yes_no_field('procesados_chetano_novedades_yes_no', '¿CUALES PROCESADOS SE REALIZARON DURANTE LA JORNADA?', 'procesados_chetano_novedadesGroup', 'procesados_chetano_novedades', 'DETALLE LOS PROCESADOS REALIZADOS', 'textarea', ['sedes' => $chetanoSedes, 'col' => 'col-md-6', 'no_report_value' => 'El dia de hoy no se realizaron procesados.']),
                //app_bitacora_yes_no_field('productos_chetano_novedades_yes_no', 'NOVEDADES CON LOS PRODUCTOS (PROXIMOS A VENCER)', 'productos_chetano_novedadesGroup', 'productos_chetano_novedades', 'DETALLE LOS PRODUCTOS PROXIMOS A VENCER PARA IMPULSAR SU VENTA', 'textarea', ['sedes' => $chetanoSedes, 'col' => 'col-md-6', 'no_report_value' => 'Sin productos próximos a vencer.']),
                //app_bitacora_yes_no_field('planillas_chetano_novedades_yes_no', 'FORMATOS DILIGENCIADOS DURANTE LA JORNADA', 'planillas_chetano_novedadesGroup', 'planillas_chetano_novedades', 'DETALLE DE FORMATOS DILIGENCIADOS', 'textarea', ['sedes' => $chetanoSedes, 'col' => 'col-md-6', 'no_report_value' => 'El dia de hoy no se diligenciaron formatos.']),
                app_bitacora_field('multiselect', 'formatos_chetano', 'FORMATOS DILIGENCIADOS DURANTE LA JORNADA', ['col' => 'col-md-12', 'options' => $formatosChetano, 'sedes' => $chetanoSedes, 'required' => false]),
                app_bitacora_field('textarea', 'ventas_chetano', 'VENTA DE PRODUCTOS', ['sedes' => $chetanoSedes, 'col' => 'col-md-4']),
                app_bitacora_field('textarea', 'dom_chetano', 'VENTAS POR DOMICILIO', ['sedes' => $chetanoSedes, 'col' => 'col-md-4']),
                app_bitacora_field('textarea', 'mp_chetano', 'MASAS DISPONIBLES', ['sedes' => $chetanoSedes, 'col' => 'col-md-4']),
            ];

            $insertAt = count((array) ($section['fields'] ?? []));
            foreach ((array) ($section['fields'] ?? []) as $index => $field) {
                if ((string) ($field['type'] ?? '') === 'subsection' && (string) ($field['name'] ?? '') === 'reservas') {
                    $insertAt = $index;
                    break;
                }
            }
            array_splice($section['fields'], $insertAt, 0, $chetanoFields);
            break;
        }
        unset($section);
    }

    // Campo que aplica a la sede de Llanogrande.
    if (app_bitacora_extra_enabled($companyConfig, 'reunion_calidad')) {
        $sections[] = ['key' => 'reunion_calidad', 'title' => 'REUNIÓN DE CALIDAD', 'fields' => [
            app_bitacora_field('textarea', 'reu', 'REUNIÓN DE CALIDAD 3:00 P.M.', ['col' => 'col-md-12']),
        ]];
    }
    return $sections;
}

function app_bitacora_supervision_form_sections(array $companyConfig): array
{
    return [[
        'key' => 'supervision',
        'title' => 'INFORMACIÓN DE SUPERVISIÓN',
        'fields' => [
            app_bitacora_field('date', 'fechasup', 'Fecha', ['col' => 'col-md-3']),
            app_bitacora_field('select', 'horasup', 'Horario de supervisión', ['options' => ['11:00 AM - 3:00 PM', '3:00 PM - 6:00 PM', '6:00 PM - 10:00 PM', 'Todo el turno']]),
            app_bitacora_field('select', 'sede', 'Sede', ['id' => 'sede', 'options' => $companyConfig['sedes'] ?? []]),
            app_bitacora_field('select', 'area', 'Área', ['options' => ['Salon', 'Cocina', 'Bar']]),
            app_bitacora_field('select', 'responsableb', 'Responsable de supervisión', ['options' => ['Angela Mesa - Supervisora de Cocina y Bar', 'Brian Ortiz - Coordinador de Operaciones', 'Gabriel Perez - Supervisor Comercial', 'Maria Conchita - Supervisora de Cocina y Bar', 'Nicol Muñoz - Supervisora de Operaciones']]),
            app_bitacora_field('textarea', 'hallazgos', 'Hallazgos encontrados con sus evidencias'),
            app_bitacora_field('textarea', 'ryc', 'Retroalimentación y colaboradores'),
            app_bitacora_field('textarea', 'tappv', 'Tareas asignadas para próximas visitas'),
            app_bitacora_field('textarea', 'pasc', 'Plan de acción o recomendaciones'),
            app_bitacora_field('textarea', 'actsup', 'Otras actividades del supervisor'),
        ],
    ]];
}

function app_bitacora_base_form_sections(int $empresaId, array $companyConfig): array
{
    if (($companyConfig['type'] ?? '') === 'supervision') {
        return app_bitacora_supervision_form_sections($companyConfig);
    }

    return app_bitacora_default_form_sections($companyConfig, $empresaId);
}

function app_bitacora_normalize_dynamic_field(array $field): ?array
{
    $allowedTypes = ['text', 'textarea', 'number', 'date', 'time', 'select', 'multiselect', 'yes_no', 'yes_no_quantity_group', 'quantity_group', 'multiselect_detail_group', 'subsection'];
    $name = (string) ($field['name'] ?? '');
    $type = (string) ($field['type'] ?? 'text');

    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $name) || !in_array($type, $allowedTypes, true)) {
        return null;
    }

    $field['name'] = $name;
    $field['type'] = $type;
    $field['label'] = trim((string) ($field['label'] ?? $name));
    $field['required'] = (bool) ($field['required'] ?? false);
    $field['dynamic'] = true;

    if ($type === 'subsection') {
        $field['description'] = mb_substr(trim((string) ($field['description'] ?? '')), 0, 2000, 'UTF-8');
        $field['required'] = false;
        $field['col'] = 'col-md-12';
    }

    if (in_array($type, ['select', 'multiselect'], true)) {
        $field['options'] = array_values(array_filter((array) ($field['options'] ?? []), static fn($v) => trim((string) $v) !== ''));
    }

    if ($type === 'number') {
        $field = app_bitacora_normalize_number_options($field);
    }

    if ($type === 'yes_no') {
        $detailName = (string) ($field['detail_name'] ?? ($name . '_detalle'));
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $detailName)) {
            return null;
        }
        $field['detail_name'] = $detailName;
        $field['group_id'] = $field['group_id'] ?? ($detailName . 'Group');
        $field['detail_label'] = $field['detail_label'] ?? 'Detalle';
        $detailType = (string) ($field['detail_type'] ?? 'textarea');
        if (!in_array($detailType, ['textarea', 'number', 'date', 'multiselect'], true)) {
            $detailType = 'textarea';
        }
        $field['detail_type'] = $detailType;
        if ($detailType === 'multiselect') {
            $detailOptions = array_values(array_unique(array_filter(
                (array) ($field['detail_options'] ?? []),
                static fn($value) => trim((string) $value) !== ''
            )));
            if ($detailOptions === []) {
                return null;
            }
            $field['detail_options'] = $detailOptions;
        } else {
            unset($field['detail_options']);
        }
        $detailDefaultFrom = trim((string) ($field['detail_default_from'] ?? ''));
        if ($detailDefaultFrom !== '' && preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $detailDefaultFrom)) {
            $field['detail_default_from'] = $detailDefaultFrom;
        } else {
            unset($field['detail_default_from']);
        }
        $field['no_report_value'] = mb_substr(trim((string) ($field['no_report_value'] ?? 'Sin novedad')), 0, 500, 'UTF-8') ?: 'Sin novedad';
    }

    if ($type === 'multiselect_detail_group') {
        $options = array_values(array_unique(array_filter(
            (array) ($field['options'] ?? []),
            static fn($v) => trim((string) $v) !== ''
        )));
        if ($options === []) {
            return null;
        }

        $detailName = (string) ($field['detail_name'] ?? ($name . '_detalles'));
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $detailName)) {
            return null;
        }

        $field['options'] = $options;
        $field['id'] = (string) ($field['id'] ?? $name);
        $field['detail_name'] = $detailName;
        $field['no_apply_value'] = (string) ($field['no_apply_value'] ?? 'No aplica visita');
        $field['placeholder'] = (string) ($field['placeholder'] ?? 'Escribe Nombre Apellido - Cargo');
        $field['help'] = (string) ($field['help'] ?? '');
        $field['col'] = (string) ($field['col'] ?? 'col-md-12');
    }

    if ($type === 'yes_no_quantity_group') {
        $quantityName = (string) ($field['quantity_name'] ?? ($name . '_cantidad'));
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $quantityName)) {
            return null;
        }

        $min = max(1, (int) ($field['min'] ?? 1));
        $max = max($min, min(10, (int) ($field['max'] ?? 10)));

        $fields = [];
        foreach ((array) ($field['fields'] ?? []) as $itemField) {
            $itemName = trim((string) ($itemField['name'] ?? ''));
            $itemType = (string) ($itemField['type'] ?? 'text');
            if ($itemName === '' || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $itemName) || !in_array($itemType, ['text', 'textarea', 'number', 'select', 'date', 'time', 'simple_radio'], true)) {
                continue;
            }

            $normalized = [
                'name' => $itemName,
                'label' => trim((string) ($itemField['label'] ?? $itemName)),
                'type' => $itemType,
                'required' => (bool) ($itemField['required'] ?? true),
            ];
            if ($itemType === 'select') {
                $normalized['options'] = array_values(array_filter((array) ($itemField['options'] ?? []), static fn($v) => trim((string) $v) !== ''));
            }
            if ($itemType === 'number') {
                if (array_key_exists('min', $itemField)) {
                    $normalized['min'] = (int) $itemField['min'];
                }
                if (array_key_exists('max', $itemField)) {
                    $normalized['max'] = (int) $itemField['max'];
                }
                if (array_key_exists('step', $itemField)) {
                    $normalized['step'] = (float) $itemField['step'];
                }
                foreach (['suffix', 'suffix_singular', 'suffix_plural', 'number_format', 'number_decimals'] as $key) {
                    if (array_key_exists($key, $itemField)) {
                        $normalized[$key] = $itemField[$key];
                    }
                }
                $normalized = app_bitacora_normalize_number_options($normalized);
            }
            $fields[] = $normalized;
        }

        if ($fields === []) {
            return null;
        }

        $field['quantity_name'] = $quantityName;
        $field['quantity_label'] = (string) ($field['quantity_label'] ?? 'Cantidad');
        $field['min'] = $min;
        $field['max'] = $max;
        $field['item_label'] = (string) ($field['item_label'] ?? 'Registro');
        foreach (['suffix', 'suffix_singular', 'suffix_plural'] as $key) {
            if (array_key_exists($key, $field)) {
                $field[$key] = mb_substr(trim((string) $field[$key]), 0, 100, 'UTF-8');
            }
        }
        $field['no_report_value'] = mb_substr(trim((string) ($field['no_report_value'] ?? 'Sin novedad')), 0, 500, 'UTF-8') ?: 'Sin novedad';
        $field['fields'] = $fields;
        $field['col'] = (string) ($field['col'] ?? 'col-md-12');
    }

    if ($type === 'quantity_group') {
        $quantityName = (string) ($field['quantity_name'] ?? ($name . '_cantidad'));
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $quantityName)) {
            return null;
        }

        $max = max(1, min(10, (int) ($field['max'] ?? 10)));
        $fields = [];
        foreach ((array) ($field['fields'] ?? []) as $itemField) {
            $itemName = trim((string) ($itemField['name'] ?? ''));
            $itemType = (string) ($itemField['type'] ?? 'text');
            if ($itemName === '' || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $itemName) || !in_array($itemType, ['text', 'textarea', 'number', 'select', 'date', 'time', 'simple_radio'], true)) {
                continue;
            }

            $normalized = [
                'name' => $itemName,
                'label' => trim((string) ($itemField['label'] ?? $itemName)),
                'type' => $itemType,
                'required' => (bool) ($itemField['required'] ?? true),
            ];
            if ($itemType === 'select') {
                $normalized['options'] = array_values(array_filter((array) ($itemField['options'] ?? []), static fn($v) => trim((string) $v) !== ''));
            }
            if ($itemType === 'number') {
                if (array_key_exists('min', $itemField)) {
                    $normalized['min'] = (int) $itemField['min'];
                }
                if (array_key_exists('max', $itemField)) {
                    $normalized['max'] = (int) $itemField['max'];
                }
                if (array_key_exists('step', $itemField)) {
                    $normalized['step'] = (float) $itemField['step'];
                }
                foreach (['suffix', 'suffix_singular', 'suffix_plural', 'number_format', 'number_decimals'] as $key) {
                    if (array_key_exists($key, $itemField)) {
                        $normalized[$key] = $itemField[$key];
                    }
                }
                $normalized = app_bitacora_normalize_number_options($normalized);
            }
            $fields[] = $normalized;
        }

        if ($fields === []) {
            return null;
        }

        $field['quantity_name'] = $quantityName;
        $field['quantity_label'] = (string) ($field['quantity_label'] ?? 'Cantidad');
        $field['required'] = true;
        $field['min'] = 0;
        $field['max'] = $max;
        $field['item_label'] = (string) ($field['item_label'] ?? 'Registro');
        foreach (['suffix', 'suffix_singular', 'suffix_plural'] as $key) {
            if (array_key_exists($key, $field)) {
                $field[$key] = mb_substr(trim((string) $field[$key]), 0, 100, 'UTF-8');
            }
        }
        $field['zero_report_value'] = mb_substr(trim((string) ($field['zero_report_value'] ?? 'Sin registros')), 0, 500, 'UTF-8') ?: 'Sin registros';
        $field['fields'] = $fields;
        $field['col'] = (string) ($field['col'] ?? 'col-md-12');
    }

    return $field;
}

function app_bitacora_protected_field_names(): array
{
    return ['fechab', 'fechasup', 'sede', 'responsable', 'responsableb', 'cargo'];
}

function app_bitacora_normalize_hidden_fields(array $json): array
{
    $protected = array_flip(app_bitacora_protected_field_names());
    $hidden = [];

    foreach ((array) ($json['hidden_fields'] ?? []) as $name) {
        $name = trim((string) $name);
        if ($name === '' || isset($protected[$name]) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $name)) {
            continue;
        }

        $hidden[] = $name;
    }

    return array_values(array_unique($hidden));
}

function app_bitacora_base_fields_by_name(array $sections): array
{
    $fieldsByName = [];

    foreach ($sections as $section) {
        foreach ((array) ($section['fields'] ?? []) as $field) {
            $name = (string) ($field['name'] ?? '');
            if ($name !== '') {
                $fieldsByName[$name] = $field;
            }
        }
    }

    return $fieldsByName;
}

function app_bitacora_normalize_field_override(array $override, array $baseField): array
{
    $name = (string) ($baseField['name'] ?? '');
    $type = (string) ($baseField['type'] ?? 'text');
    if ($name === '') {
        return [];
    }

    $normalized = ['name' => $name];

    if (array_key_exists('label', $override)) {
        $label = trim((string) $override['label']);
        if ($label !== '') {
            $normalized['label'] = $label;
        }
    }

    if (array_key_exists('required', $override)) {
        $normalized['required'] = (bool) $override['required'];
    }

    if (array_key_exists('order', $override)) {
        $normalized['order'] = (int) $override['order'];
    }

    if (array_key_exists('col', $override) && in_array((string) $override['col'], ['col-md-3', 'col-md-4', 'col-md-6', 'col-md-12'], true)) {
        $normalized['col'] = (string) $override['col'];
    }

    if (array_key_exists('sedes', $override)) {
        $sedes = array_map(static fn($sede) => trim((string) $sede), (array) $override['sedes']);
        $normalized['sedes'] = array_values(array_unique(array_filter($sedes, static fn($sede) => $sede !== '')));
    }

    if ($type === 'subsection' && array_key_exists('description', $override)) {
        $normalized['description'] = mb_substr(trim((string) $override['description']), 0, 2000, 'UTF-8');
        $normalized['required'] = false;
        $normalized['col'] = 'col-md-12';
    }

    if (in_array($type, ['select', 'multiselect'], true) && array_key_exists('options', $override)) {
        $options = [];
        foreach ((array) $override['options'] as $value => $label) {
            $label = trim((string) $label);
            if ($label === '') {
                continue;
            }

            if (is_int($value)) {
                $options[] = $label;
                continue;
            }

            $value = trim((string) $value);
            if ($value !== '') {
                $options[$value] = $label;
            }
        }

        if ($options !== []) {
            $normalized['options'] = $options;
        }
    }

    if ($type === 'number') {
        foreach (['min', 'max'] as $key) {
            if (array_key_exists($key, $override) && trim((string) $override[$key]) !== '' && is_numeric(str_replace(',', '.', (string) $override[$key]))) {
                $normalized[$key] = str_replace(',', '.', (string) $override[$key]);
            }
        }

        if (array_key_exists('step', $override) && trim((string) $override['step']) !== '') {
            $normalized['step'] = trim((string) $override['step']);
        }
        foreach (['suffix', 'suffix_singular', 'suffix_plural'] as $key) {
            if (array_key_exists($key, $override)) {
                $normalized[$key] = mb_substr(trim((string) $override[$key]), 0, 100, 'UTF-8');
            }
        }
        if (array_key_exists('number_format', $override)) {
            $format = (string) $override['number_format'];
            $normalized['number_format'] = in_array($format, ['plain', 'currency'], true) ? $format : 'plain';
        }
        if (array_key_exists('number_decimals', $override) && preg_match('/^\d+$/', trim((string) $override['number_decimals'])) === 1) {
            $normalized['number_decimals'] = max(0, min(6, (int) $override['number_decimals']));
        }
    }

    if ($type === 'yes_no') {
        if (array_key_exists('detail_label', $override)) {
            $detailLabel = trim((string) $override['detail_label']);
            if ($detailLabel !== '') {
                $normalized['detail_label'] = $detailLabel;
            }
        }
        if (array_key_exists('detail_type', $override) && in_array((string) $override['detail_type'], ['textarea', 'number', 'date', 'multiselect'], true)) {
            $normalized['detail_type'] = (string) $override['detail_type'];
        }
        $detailType = (string) ($normalized['detail_type'] ?? ($baseField['detail_type'] ?? 'textarea'));
        if ($detailType === 'multiselect' && array_key_exists('detail_options', $override)) {
            $detailOptions = array_values(array_unique(array_filter(
                (array) $override['detail_options'],
                static fn($value) => trim((string) $value) !== ''
            )));
            if ($detailOptions !== []) {
                $normalized['detail_options'] = $detailOptions;
            }
        }
    }

    if (in_array($type, ['yes_no_quantity_group', 'quantity_group'], true)) {
        foreach (['suffix', 'suffix_singular', 'suffix_plural'] as $key) {
            if (array_key_exists($key, $override)) {
                $normalized[$key] = mb_substr(trim((string) $override[$key]), 0, 100, 'UTF-8');
            }
        }
    }

    if ($type === 'yes_no_quantity_group' && array_key_exists('no_report_value', $override)) {
        $noReportValue = mb_substr(trim((string) $override['no_report_value']), 0, 500, 'UTF-8');
        $normalized['no_report_value'] = $noReportValue !== '' ? $noReportValue : 'Sin novedad';
    }

    if ($type === 'quantity_group') {
        $normalized['required'] = true;
        if (array_key_exists('zero_report_value', $override)) {
            $zeroReportValue = mb_substr(trim((string) $override['zero_report_value']), 0, 500, 'UTF-8');
            $normalized['zero_report_value'] = $zeroReportValue !== '' ? $zeroReportValue : 'Sin registros';
        }
    }

    return $normalized;
}

function app_bitacora_normalize_field_overrides(array $json, array $sections): array
{
    $baseFields = app_bitacora_base_fields_by_name($sections);
    $protected = array_flip(app_bitacora_protected_field_names());
    $overrides = [];

    foreach ((array) ($json['field_overrides'] ?? []) as $key => $override) {
        if (!is_array($override)) {
            continue;
        }

        $name = trim((string) ($override['name'] ?? $key));
        if ($name === '' || isset($protected[$name]) || !isset($baseFields[$name])) {
            continue;
        }

        $normalized = app_bitacora_normalize_field_override($override, $baseFields[$name]);
        if ($normalized !== []) {
            $overrides[$name] = $normalized;
        }
    }

    return $overrides;
}

function app_bitacora_apply_field_override(array $field, array $override): array
{
    foreach (['label', 'description', 'required', 'order', 'col', 'sedes'] as $key) {
        if (array_key_exists($key, $override)) {
            $field[$key] = $override[$key];
        }
    }

    $type = (string) ($field['type'] ?? 'text');
    if (in_array($type, ['select', 'multiselect'], true) && array_key_exists('options', $override)) {
        $field['options'] = $override['options'];
    }

    if ($type === 'number') {
        foreach (['min', 'max', 'step', 'suffix', 'suffix_singular', 'suffix_plural', 'number_format', 'number_decimals'] as $key) {
            if (array_key_exists($key, $override)) {
                $field[$key] = $override[$key];
            }
        }
    }

    if ($type === 'yes_no') {
        foreach (['detail_label', 'detail_type', 'detail_options'] as $key) {
            if (array_key_exists($key, $override)) {
                $field[$key] = $override[$key];
            }
        }
    }

    if (in_array($type, ['yes_no_quantity_group', 'quantity_group'], true)) {
        foreach (['suffix', 'suffix_singular', 'suffix_plural'] as $key) {
            if (array_key_exists($key, $override)) {
                $field[$key] = $override[$key];
            }
        }
    }


    if ($type === 'yes_no_quantity_group' && array_key_exists('no_report_value', $override)) {
        $field['no_report_value'] = $override['no_report_value'];
    }

    if ($type === 'quantity_group' && array_key_exists('zero_report_value', $override)) {
        $field['zero_report_value'] = $override['zero_report_value'];
    }

    return $field;
}

function app_bitacora_apply_field_overrides(array $sections, array $json): array
{
    $overrides = app_bitacora_normalize_field_overrides($json, $sections);
    if ($overrides === []) {
        return $sections;
    }

    foreach ($sections as &$section) {
        $fields = (array) ($section['fields'] ?? []);
        foreach ($fields as &$field) {
            $name = (string) ($field['name'] ?? '');
            if ($name !== '' && isset($overrides[$name])) {
                $field = app_bitacora_apply_field_override($field, $overrides[$name]);
            }
        }
        unset($field);
        $section['fields'] = $fields;
    }
    unset($section);

    return $sections;
}

function app_bitacora_filter_hidden_fields(array $sections, array $hiddenFields): array
{
    if ($hiddenFields === []) {
        return $sections;
    }

    $hidden = array_flip($hiddenFields);
    $filteredSections = [];

    foreach ($sections as $section) {
        $fields = [];
        foreach ((array) ($section['fields'] ?? []) as $field) {
            $name = (string) ($field['name'] ?? '');
            if ($name !== '' && isset($hidden[$name])) {
                continue;
            }

            $fields[] = $field;
        }

        if ($fields === []) {
            continue;
        }

        $section['fields'] = $fields;
        $filteredSections[] = $section;
    }

    return $filteredSections;
}

function app_bitacora_normalize_sedes_list(array $sedes): array
{
    $sedes = array_map(static fn($sede) => trim((string) $sede), $sedes);
    return array_values(array_unique(array_filter($sedes, static fn($sede) => $sede !== '')));
}

function app_bitacora_section_sedes_from_fields(array $section): array
{
    $sedes = [];

    foreach ((array) ($section['fields'] ?? []) as $field) {
        $fieldSedes = app_bitacora_normalize_sedes_list((array) ($field['sedes'] ?? []));
        if ($fieldSedes === []) {
            return [];
        }

        $sedes = array_merge($sedes, $fieldSedes);
    }

    return app_bitacora_normalize_sedes_list($sedes);
}

function app_bitacora_sync_section_sedes(array $sections): array
{
    foreach ($sections as &$section) {
        if (empty($section['fields'])) {
            continue;
        }

        $sedes = app_bitacora_section_sedes_from_fields($section);
        if ($sedes === []) {
            unset($section['sedes']);
            continue;
        }

        $section['sedes'] = $sedes;
    }
    unset($section);

    return $sections;
}

function app_bitacora_apply_config_json(array $sections, array $json): array
{
    $sections = app_bitacora_apply_field_overrides($sections, $json);

    foreach ((array) ($json['dynamic_fields'] ?? []) as $field) {
        if (!is_array($field)) {
            continue;
        }
        $field = app_bitacora_normalize_dynamic_field($field);
        if ($field === null) {
            continue;
        }

        $target = trim((string) ($field['section'] ?? 'Campos adicionales'));
        $found = false;
        foreach ($sections as &$section) {
            if (strcasecmp((string) ($section['title'] ?? ''), $target) === 0 || strcasecmp((string) ($section['key'] ?? ''), $target) === 0) {
                $section['fields'][] = $field;
                $found = true;
                break;
            }
        }
        unset($section);

        if (!$found) {
            $sections[] = ['key' => strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $target)), 'title' => $target, 'fields' => [$field]];
        }
    }

    foreach ($sections as &$section) {
        usort($section['fields'], static fn($a, $b) => (int) ($a['order'] ?? 0) <=> (int) ($b['order'] ?? 0));
    }
    unset($section);

    $sections = app_bitacora_filter_hidden_fields($sections, app_bitacora_normalize_hidden_fields($json));
    return app_bitacora_sync_section_sedes($sections);
}

function app_bitacora_form_sections(int $empresaId, array $companyConfig): array
{
    static $cache = [];
    if (array_key_exists($empresaId, $cache)) {
        return $cache[$empresaId];
    }

    $sections = app_bitacora_base_form_sections($empresaId, $companyConfig);
    return $cache[$empresaId] = app_bitacora_apply_config_json($sections, app_bitacora_db_config_json($empresaId));
}

function app_bitacora_field_visible_for_sede(array $field, string $sede): bool
{
    $sedes = $field['sedes'] ?? [];
    if ($sedes === [] || $sedes === null) {
        return true;
    }

    return in_array($sede, (array) $sedes, true);
}

function app_bitacora_collect_field_names(array $sections, string $sede = ''): array
{
    $names = [];
    foreach ($sections as $section) {
        foreach ((array) ($section['fields'] ?? []) as $field) {
            if (!app_bitacora_field_visible_for_sede($field, $sede)) {
                continue;
            }
            $type = (string) ($field['type'] ?? 'text');
            if (app_bitacora_field_is_presentational($field)) {
                continue;
            }
            $name = (string) ($field['name'] ?? '');
            if ($name !== '' && $type !== 'quantity_group') {
                $names[] = $name;
            }
            if ($type === 'yes_no' && !empty($field['detail_name'])) {
                $names[] = (string) $field['detail_name'];
            }
            if ($type === 'supervisor_detail') {
                array_push($names, 'hora_entrada', 'hora_salida', 'act_sup');
            }
            if ($type === 'plant') {
                array_push($names, 'mant5', 'mant6', 'mant7');
            }
            if (in_array($type, ['yes_no_quantity_group', 'quantity_group'], true)) {
                $quantityName = (string) ($field['quantity_name'] ?? ($name . '_cantidad'));
                if ($quantityName !== '') {
                    $names[] = $quantityName;
                }

                $max = max(1, min(10, (int) ($field['max'] ?? 10)));
                foreach (range(1, $max) as $index) {
                    foreach ((array) ($field['fields'] ?? []) as $itemField) {
                        $itemFieldName = (string) ($itemField['name'] ?? '');
                        if ($itemFieldName !== '') {
                            $names[] = app_bitacora_group_item_field_name($name, $index, $itemFieldName);
                        }
                    }
                }
            }
            if ($type === 'yes_no_detail_group') {
                foreach ((array) ($field['fields'] ?? []) as $detailField) {
                    $detailFieldName = (string) ($detailField['name'] ?? '');
                    if ($detailFieldName !== '') {
                        $names[] = app_bitacora_detail_group_field_name($name, $detailFieldName);
                    }
                }
            }
        }
    }

    return array_values(array_unique($names));
}

function app_bitacora_collect_fields_by_type(array $sections, array $types, string $sede = ''): array
{
    $fields = [];
    foreach ($sections as $section) {
        foreach ((array) ($section['fields'] ?? []) as $field) {
            if (!app_bitacora_field_visible_for_sede($field, $sede)) {
                continue;
            }
            if (!in_array((string) ($field['type'] ?? 'text'), $types, true)) {
                continue;
            }

            $field['section_title'] = $section['title'] ?? 'Campos adicionales';
            $field['section_key'] = $section['key'] ?? '';
            $fields[] = $field;
        }
    }

    return $fields;
}

function app_bitacora_dynamic_render_fields(array $sections, string $sede = ''): array
{
    $fields = [];
    foreach ($sections as $section) {
        foreach ((array) ($section['fields'] ?? []) as $field) {
            if ((empty($field['dynamic']) && !app_bitacora_field_is_presentational($field)) || !app_bitacora_field_visible_for_sede($field, $sede)) {
                continue;
            }
            $field['section_title'] = $section['title'] ?? 'Campos adicionales';
            $field['section_key'] = $section['key'] ?? '';
            $fields[] = $field;
        }
    }
    return $fields;
}
