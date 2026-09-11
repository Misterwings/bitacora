<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

function bit_recipient_email_set(array $recipients): array
{
    $emails = [];
    foreach (['to', 'cc', 'bcc'] as $type) {
        foreach ((array) ($recipients[$type] ?? []) as $email) {
            $email = strtolower(trim((string) $email));
            if ($email !== '') {
                $emails[$email] = true;
            }
        }
    }

    return $emails;
}

function bit_section_email_add_recipient(PHPMailer $mail, string $type, string $email): void
{
    if ($type === 'cc') {
        $mail->addCC($email);
        return;
    }

    if ($type === 'bcc') {
        $mail->addBCC($email);
        return;
    }

    $mail->addAddress($email);
}

function bit_section_email_rows_for_field(array $field, array $data): array
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
            $rows[] = bit_render_detail('Hora encendido', (string) ($data['mant5'] ?? ''));
            $rows[] = bit_render_detail('Hora apagado', (string) ($data['mant6'] ?? ''));
            $rows[] = bit_render_detail('Tiempo de uso (minutos)', (string) ($data['mant7'] ?? ''));
        }

        return $rows;
    }

    if ($type === 'yes_no' || $type === 'simple_radio') {
        $answer = trim((string) ($data[$name] ?? ''));
        if ($answer === '') {
            return [];
        }

        $detail = bit_report_yes_no_detail_from_data($field, $data);
        if (bit_report_yes_no_is_silent($field, $data, $answer, $detail)) {
            return [];
        }

        $detail = bit_report_yes_no_detail_value($field, $detail);
        $value = bit_report_yes_no_value($answer, $detail, (string) ($field['no_report_value'] ?? ''));
        return [bit_render_detail($label, bit_report_field_value($value, $field), true)];
    }

    $value = $type === 'multiselect'
        ? bit_normalize_array_value($data[$name] ?? '')
        : trim((string) ($data[$name] ?? ''));
    if ($name === 'fechab') {
        $value = trim((string) ($data['fecha'] ?? $value));
    }

    $value = bit_report_field_value($value, $field);

    return [bit_render_detail($label, $value)];
}

function bit_render_assigned_sections_body(array $sections, array $data, array $sectionKeys): string
{
    $allowedSections = array_flip($sectionKeys);
    $sede = (string) ($data['sede'] ?? '');
    $body = '
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body{font-family:DejaVu Sans,Arial,sans-serif;font-size:14px;color:#222;margin:20px;}
            .header{border-bottom:2px solid #8B1E1E;padding-bottom:10px;margin-bottom:20px;}
            .title{font-size:24px;font-weight:bold;color:#8B1E1E;margin-bottom:6px;}
            .meta{margin-bottom:3px;}
            .area-section{margin-bottom:14px;border:1px solid #ddd;border-radius:6px;padding:10px;}
            .area-title{font-size:16px;font-weight:bold;margin-bottom:8px;color:#8B1E1E;}
            .sub-item{margin-bottom:6px;line-height:1.4;}
        </style>
    </head>
    <body>
        <div class="header">
            <div class="title">BITACORA DIARIA ' . bit_h($sede) . '</div>
            <div class="meta"><strong>Fecha:</strong> ' . bit_h($data['fecha'] ?? '') . '</div>
            <div class="meta"><strong>Responsable:</strong> ' . bit_h($data['responsable'] ?? '') . '</div>
            <div class="meta"><strong>Cargo:</strong> ' . bit_h($data['cargo'] ?? '') . '</div>
        </div>';

    $renderedSections = 0;
    foreach ($sections as $section) {
        $sectionKey = (string) ($section['key'] ?? '');
        if ($sectionKey === '' || !isset($allowedSections[$sectionKey]) || !app_bitacora_field_visible_for_sede($section, $sede)) {
            continue;
        }

        $visibleFields = [];
        foreach ((array) ($section['fields'] ?? []) as $field) {
            if (!app_bitacora_field_visible_for_sede($field, $sede)) {
                continue;
            }

            $visibleFields[] = $field;
        }

        $blocks = bit_render_report_blocks(
            $visibleFields,
            static fn(array $field): array => bit_section_email_rows_for_field($field, $data)
        );
        if ($blocks === []) {
            continue;
        }

        $rows = [];
        foreach ($blocks as $blockRows) {
            $rows = array_merge($rows, $blockRows);
        }
        $sectionHtml = bit_render_section((string) ($section['title'] ?? $sectionKey), $rows);
        if ($sectionHtml !== '') {
            $body .= $sectionHtml;
            $renderedSections++;
        }
    }

    if ($renderedSections === 0) {
        return '';
    }

    return $body . '</body></html>';
}

function bit_render_supervision_body(array $sections, array $data): string
{
    $e = static function ($value): string {
        return nl2br(htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'));
    };

    $body = '<h2>Información de Supervisión</h2>';
    $renderedSections = 0;
    $sede = (string) ($data['sede'] ?? '');
    foreach ($sections as $section) {
        $visibleFields = [];
        foreach ((array) ($section['fields'] ?? []) as $field) {
            if (!app_bitacora_field_visible_for_sede($field, $sede)) {
                continue;
            }

            $visibleFields[] = $field;
        }

        $blocks = bit_render_report_blocks(
            $visibleFields,
            static function (array $field) use ($data, $e): array {
            $name = (string) ($field['name'] ?? '');
            if (app_bitacora_field_is_presentational($field)) {
                return ['<li style="list-style:none;margin-left:-20px;">' . bit_render_subsection($field) . '</li>'];
            }
            if ($name === '') {
                return [];
            }

            $type = (string) ($field['type'] ?? 'text');
            if (in_array($type, ['yes_no_quantity_group', 'quantity_group', 'yes_no_detail_group', 'yes_no_branch_group', 'multiselect_detail_group'], true)) {
                $groupRows = [];
                if ($type === 'yes_no_quantity_group') {
                    $groupRows = bit_render_quantity_group($field, $data);
                } elseif ($type === 'quantity_group') {
                    $groupRows = bit_render_direct_quantity_group($field, $data);
                } elseif ($type === 'yes_no_detail_group') {
                    $groupRows = bit_render_detail_group($field, $data);
                } elseif ($type === 'yes_no_branch_group') {
                    $groupRows = bit_render_branch_group($field, $data);
                } else {
                    $groupRows = bit_render_multiselect_detail_group($field, $data);
                }
                $groupRows = array_values(array_filter($groupRows, static fn($row) => trim((string) $row) !== ''));
                return array_map(static fn($groupRow) => '<li>' . $groupRow . '</li>', $groupRows);
            }

            $label = (string) ($field['label'] ?? $name);
            $value = $type === 'multiselect'
                ? bit_normalize_array_value($data[$name] ?? '')
                : trim((string) ($data[$name] ?? ''));
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

            $value = bit_report_display_value(bit_report_field_value($value, $field));

            if ($value === '') {
                return [];
            }

            return ['<li><strong>' . $e($label) . ': </strong>' . $e($value) . '</li>'];
            }
        );

        if ($blocks === []) {
            continue;
        }

        $rows = [];
        foreach ($blocks as $blockRows) {
            $rows = array_merge($rows, $blockRows);
        }
        $body .= '<h3>' . $e((string) ($section['title'] ?? 'Sección')) . '</h3><ul>' . implode('', $rows) . '</ul>';
        $renderedSections++;
    }

    return $renderedSections > 0 ? $body : '';
}

function bit_send_section_emails(int $empresaId, string $sede, array $data, array $sections, array $fullRecipients, string $subject): int
{
    $fullRecipientEmails = bit_recipient_email_set($fullRecipients);
    $sectionRecipients = app_bitacora_db_section_recipients($empresaId, $sede);
    $sent = 0;
    $mail = null;

    foreach ($sectionRecipients as $recipient) {
        $email = trim((string) ($recipient['email'] ?? ''));
        if ($email === '' || isset($fullRecipientEmails[strtolower($email)])) {
            continue;
        }

        $body = bit_render_assigned_sections_body($sections, $data, (array) ($recipient['sections'] ?? []));
        if ($body === '') {
            continue;
        }

        try {
            if ($mail === null) {
                $mail = new PHPMailer(true);
                app_configure_mailer($mail);
                $mail->SMTPKeepAlive = true;
            } else {
                $mail->clearAllRecipients();
                $mail->clearAttachments();
            }

            bit_section_email_add_recipient($mail, (string) ($recipient['type'] ?? 'to'), $email);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->send();
            $sent++;
        } catch (Throwable $e) {
            error_log('Error enviando secciones de bitacora a ' . $email . ': ' . $e->getMessage());
            if ($mail !== null) {
                $mail->smtpClose();
            }
        }
    }

    if ($mail !== null) {
        $mail->smtpClose();
    }

    return $sent;
}

function bit_pdf_queue_attachments(?array $pdfInfo): array
{
    if ($pdfInfo === null || empty($pdfInfo['relativePath'])) {
        return [];
    }

    return [[
        'relative_path' => (string) $pdfInfo['relativePath'],
        'file_name' => (string) ($pdfInfo['fileName'] ?? 'bitacora.pdf'),
    ]];
}

function bit_queue_section_emails(int $empresaId, string $sede, string $usuario, array $data, array $sections, array $fullRecipients, string $subject, ?PDO $pdo = null, ?int $envioId = null): int
{
    $fullRecipientEmails = bit_recipient_email_set($fullRecipients);
    $sectionRecipients = app_bitacora_db_section_recipients($empresaId, $sede);
    $queued = 0;

    foreach ($sectionRecipients as $recipient) {
        $email = trim((string) ($recipient['email'] ?? ''));
        if ($email === '' || isset($fullRecipientEmails[strtolower($email)])) {
            continue;
        }

        $body = bit_render_assigned_sections_body($sections, $data, (array) ($recipient['sections'] ?? []));
        if ($body === '') {
            continue;
        }

        $type = (string) ($recipient['type'] ?? 'to');
        $recipients = ['to' => [], 'cc' => [], 'bcc' => []];
        if (!isset($recipients[$type])) {
            $type = 'to';
        }
        $recipients[$type][] = $email;

        if (bit_enqueue_email($envioId, $empresaId, $sede, $usuario, $subject, $body, $recipients, [], $pdo, 'section') !== null) {
            $queued++;
        }
    }

    return $queued;
}
