<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

function bit_json_body(bool $ok, string $message, array $extra = []): array
{
    return array_merge(['ok' => $ok, 'message' => $message], $extra);
}

function bit_emit_json_response(array $body, int $status = 200): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, private');
    }
    http_response_code($status);

    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

function bit_json_response(bool $ok, string $message, array $extra = [], int $status = 200): void
{
    bit_emit_json_response(bit_json_body($ok, $message, $extra), $status);
}

function bit_submission_request_hash(array $request): string
{
    unset($request['csrf_token'], $request['draft_token'], $request['draft_version']);
    return hash('sha256', json_encode(bit_draft_canonicalize($request), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
}

function bit_submission_replay_existing(array $context, ?PDO $pdo = null): bool
{
    $submissionKey = (string) ($context['submission_key'] ?? '');
    if ($submissionKey === '') {
        return false;
    }

    require_once __DIR__ . '/../bd/conexion.php';
    $pdo = $pdo ?? Conexion::Conectar();
    $stmt = $pdo->prepare('
        SELECT e.id, e.request_hash, e.response_http_status, e.response_json, e.delivery_started_at,
               e.pdf_id, TIMESTAMPDIFF(SECOND, e.creado_en, UTC_TIMESTAMP()) claim_age_seconds,
               p.relative_path pdf_relative_path
        FROM bitacora_envios e
        LEFT JOIN bitacora_pdfs p ON p.id = e.pdf_id
        WHERE e.submission_key = :submission_key
        LIMIT 1
    ');
    $stmt->execute(['submission_key' => $submissionKey]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        return false;
    }

    if (!hash_equals((string) ($row['request_hash'] ?? ''), (string) ($context['request_hash'] ?? ''))) {
        bit_json_response(false, 'La misma referencia de envío fue usada con datos diferentes.', ['code' => 'idempotency_conflict'], 409);
    }

    $responseJson = $row['response_json'] ?? null;
    if (!is_string($responseJson) || trim($responseJson) === '') {
        if (!empty($row['delivery_started_at'])) {
            bit_json_response(false, 'El envío quedó con estado de entrega incierto. Verifica el historial antes de repetirlo.', ['code' => 'submission_status_unknown'], 409);
        }
        $claimTtl = max(30, app_env_int('BITACORA_SUBMISSION_CLAIM_TTL_SECONDS', 300));
        if (!$pdo->inTransaction() && (int) ($row['claim_age_seconds'] ?? 0) >= $claimTtl) {
            $pdo->beginTransaction();
            try {
                $delete = $pdo->prepare('
                    DELETE FROM bitacora_envios
                    WHERE id = :id AND response_json IS NULL AND delivery_started_at IS NULL
                      AND TIMESTAMPDIFF(SECOND, creado_en, UTC_TIMESTAMP()) >= :claim_ttl
                ');
                $delete->execute(['id' => (int) $row['id'], 'claim_ttl' => $claimTtl]);
                if ($delete->rowCount() === 1) {
                    if (!empty($row['pdf_id'])) {
                        $deletePdf = $pdo->prepare('DELETE FROM bitacora_pdfs WHERE id = :id');
                        $deletePdf->execute(['id' => (int) $row['pdf_id']]);
                    }
                    $pdo->commit();
                    $pdfPath = bit_storage_resolve_path((string) ($row['pdf_relative_path'] ?? ''));
                    if ($pdfPath !== null && !@unlink($pdfPath)) {
                        error_log('No fue posible eliminar el PDF de una reserva recuperada: ' . $pdfPath);
                    }
                    return false;
                }
                $pdo->rollBack();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        }
        bit_json_response(false, 'El envío ya está siendo procesado. Espera antes de consultar nuevamente.', ['code' => 'submission_in_progress'], 409);
    }

    try {
        $body = json_decode($responseJson, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('La respuesta idempotente almacenada no es válida.', 0, $e);
    }
    if (!is_array($body)) {
        throw new RuntimeException('La respuesta idempotente almacenada no es un objeto.');
    }

    bit_emit_json_response($body, max(100, min(599, (int) ($row['response_http_status'] ?? 200))));
}

function bit_submission_draft_context(int $empresaId, string $type, bool $required = false): ?array
{
    $token = trim((string) ($_POST['draft_token'] ?? ''));
    $versionRaw = trim((string) ($_POST['draft_version'] ?? ''));
    if ($token === '' && $versionRaw === '') {
        if ($required) {
            bit_json_response(false, 'Guarda el borrador antes de continuar.', ['code' => 'draft_required'], 428);
        }
        return null;
    }
    if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1 || preg_match('/^[1-9]\d*$/', $versionRaw) !== 1) {
        bit_json_response(false, 'La referencia del borrador no es válida.', ['code' => 'invalid_draft_reference'], 400);
    }

    $userId = (int) ($_SESSION['s_usuario_id'] ?? 0);
    if ($userId <= 0) {
        bit_json_response(false, 'La sesión no tiene un propietario válido.', ['code' => 'invalid_draft_owner'], 403);
    }

    $idempotent = trim((string) ($_POST['bitacora_action'] ?? 'send')) !== 'generate_pdf';
    $context = [
        'token' => $token,
        'version' => (int) $versionRaw,
        'user_id' => $userId,
        'empresa_id' => $empresaId,
        'type' => $type,
        'submission_key' => $idempotent ? hash('sha256', implode("\0", [(string) $userId, (string) $empresaId, $type, $token, $versionRaw])) : null,
        'request_hash' => $idempotent ? bit_submission_request_hash($_POST) : null,
    ];

    try {
        require_once __DIR__ . '/../bd/conexion.php';
        $pdo = Conexion::Conectar();
        if ($idempotent) {
            bit_submission_replay_existing($context, $pdo);
        }
        $stmt = $pdo->prepare('SELECT 1 FROM bitacora_borradores WHERE token = :token AND version = :version AND idUsuario = :user_id AND idEmpresa = :empresa_id AND tipo_formulario = :type AND expires_at > UTC_TIMESTAMP() LIMIT 1');
        $stmt->execute([
            'token' => $token,
            'version' => (int) $versionRaw,
            'user_id' => $userId,
            'empresa_id' => $empresaId,
            'type' => $type,
        ]);
        if ($stmt->fetchColumn() === false) {
            bit_json_response(false, 'El borrador cambió o ya no está disponible. Recárgalo antes de enviar.', ['code' => 'draft_conflict'], 409);
        }
    } catch (Throwable $e) {
        error_log('No fue posible validar el borrador antes del envío: ' . get_class($e));
        bit_json_response(false, 'No fue posible validar el borrador antes del envío.', ['code' => 'draft_unavailable'], 503);
    }

    return $context;
}

function bit_finalize_submission_draft(?array $draft, ?PDO $pdo = null): bool
{
    if ($draft === null) {
        return true;
    }

    $externalPdo = $pdo !== null;
    try {
        require_once __DIR__ . '/../bd/conexion.php';
        $pdo = $pdo ?? Conexion::Conectar();
        $stmt = $pdo->prepare('DELETE FROM bitacora_borradores WHERE token = :token AND version = :version AND idUsuario = :user_id AND idEmpresa = :empresa_id AND tipo_formulario = :type');
        $stmt->execute([
            'token' => $draft['token'],
            'version' => $draft['version'],
            'user_id' => $draft['user_id'],
            'empresa_id' => $draft['empresa_id'],
            'type' => $draft['type'],
        ]);
        return $stmt->rowCount() === 1;
    } catch (Throwable $e) {
        if ($externalPdo) {
            throw $e;
        }
        error_log('No fue posible finalizar el borrador enviado: ' . get_class($e));
        return false;
    }
}

function bit_submission_claim_envio(
    PDO $pdo,
    int $empresaId,
    string $sede,
    string $fecha,
    string $responsable,
    string $usuario,
    string $type,
    ?array $context
): int {
    $envioId = bit_register_envio(
        $empresaId,
        $sede,
        $fecha,
        $responsable,
        $usuario,
        $type,
        isset($context['submission_key']) ? (string) $context['submission_key'] : null,
        isset($context['request_hash']) ? (string) $context['request_hash'] : null,
        $pdo
    );
    if ($envioId === null || $envioId <= 0) {
        throw new RuntimeException('No fue posible reservar el envío.');
    }
    return $envioId;
}

function bit_submission_store_response(PDO $pdo, int $envioId, array $body, int $status = 200): void
{
    $stmt = $pdo->prepare('
        UPDATE bitacora_envios
        SET response_http_status = :response_http_status,
            response_json = :response_json,
            response_completed_at = UTC_TIMESTAMP()
        WHERE id = :id
    ');
    $stmt->execute([
        'response_http_status' => $status,
        'response_json' => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        'id' => $envioId,
    ]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('No fue posible almacenar la respuesta del envío.');
    }
}

function bit_submission_mark_delivery_started(int $envioId): void
{
    require_once __DIR__ . '/../bd/conexion.php';
    $pdo = Conexion::Conectar();
    $stmt = $pdo->prepare('UPDATE bitacora_envios SET delivery_started_at = UTC_TIMESTAMP() WHERE id = :id AND response_json IS NULL');
    $stmt->execute(['id' => $envioId]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('No fue posible marcar el inicio de la entrega.');
    }
}

function bit_submission_complete(
    PDO $pdo,
    int $envioId,
    ?array $draft,
    array $envioUpdate,
    bool $ok,
    string $message,
    array $extra,
    int $status = 200
): array {
    $extra['draftFinalized'] = bit_finalize_submission_draft($draft, $pdo);
    bit_update_envio($envioId, $envioUpdate, $pdo);
    $body = bit_json_body($ok, $message, $extra);
    bit_submission_store_response($pdo, $envioId, $body, $status);
    return $body;
}

function bit_submission_is_claim_conflict(Throwable $error, ?array $context): bool
{
    return $context !== null
        && (string) ($context['submission_key'] ?? '') !== ''
        && $error instanceof PDOException
        && (string) $error->getCode() === '23000';
}

function bit_handle_supervision(int $empresaId, array $companyConfig, ?array $draftContext = null): void
{
    $sede = trim((string) ($_POST['sede'] ?? ''));
    [$validSede, $sedeMessage] = bit_validate_sede($companyConfig, $sede);
    if (!$validSede) {
        bit_json_response(false, $sedeMessage);
    }

    $sections = app_bitacora_form_sections($empresaId, $companyConfig);
    $fields = app_bitacora_collect_field_names($sections, $sede);

    [$validSchema, $schemaMessage] = bit_validate_schema_fields($sections, $sede);
    if (!$validSchema) {
        bit_json_response(false, $schemaMessage);
    }

    $directQuantityGroups = app_bitacora_collect_fields_by_type($sections, ['quantity_group'], $sede);
    [$validDirectQuantityGroups, $directQuantityMessage] = bit_validate_direct_quantity_groups($directQuantityGroups, trim((string) ($_POST['fechasup'] ?? '')));
    if (!$validDirectQuantityGroups) {
        bit_json_response(false, $directQuantityMessage);
    }

    $data = [];
    foreach ($fields as $field) {
        $data[$field] = bit_normalize_array_value($_POST[$field] ?? '');
    }

    $data['fecha_iso'] = trim((string) ($data['fechasup'] ?? ''));
    $timestamp = strtotime((string) ($data['fechasup'] ?? ''));
    if ($timestamp !== false) {
        $data['fechasup'] = date('d-m-Y', $timestamp);
    }

    $body = bit_render_supervision_body($sections, $data);
    $subject = 'Reporte de Supervisión ' . preg_replace('/[\r\n]+/', ' ', (string) ($data['sede'] ?? ''));
    $usuario = (string) ($_SESSION['s_usuario'] ?? '');
    $errors = [];

    if (bit_mail_async_enabled()) {
        try {
            $recipients = app_bitacora_recipients_for_sede($empresaId, $sede);
            require_once __DIR__ . '/../bd/conexion.php';
            $pdo = Conexion::Conectar();
            $pdo->beginTransaction();
            try {
                $envioId = bit_submission_claim_envio(
                    $pdo,
                    $empresaId,
                    $sede,
                    (string) ($data['fechasup'] ?? ''),
                    (string) ($data['responsableb'] ?? ''),
                    $usuario,
                    'supervision',
                    $draftContext
                );
                bit_enqueue_email($envioId, $empresaId, $sede, $usuario, $subject, $body, $recipients, [], $pdo);
                $response = bit_submission_complete($pdo, $envioId, $draftContext, [
                    'estado' => 'pendiente',
                    'correo_enviado' => false,
                    'pdf_generado' => false,
                ], true, 'El reporte de supervisión fue registrado y el correo quedó en cola.', [
                    'correoEnviado' => null,
                    'correoEncolado' => true,
                    'pdfGenerado' => false,
                    'downloadUrl' => null,
                    'warnings' => $errors,
                ]);
                $pdo->commit();
                bit_emit_json_response($response);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if (bit_submission_is_claim_conflict($e, $draftContext)) {
                    bit_submission_replay_existing($draftContext);
                }
                throw $e;
            }
        } catch (Throwable $e) {
            error_log('No fue posible encolar reporte de supervisión; se intentará envío síncrono: ' . $e->getMessage());
        }
    }

    $envioId = null;
    try {
        require_once __DIR__ . '/../bd/conexion.php';
        $pdo = Conexion::Conectar();
        $pdo->beginTransaction();
        try {
            $envioId = bit_submission_claim_envio(
                $pdo,
                $empresaId,
                $sede,
                (string) ($data['fechasup'] ?? ''),
                (string) ($data['responsableb'] ?? ''),
                $usuario,
                'supervision',
                $draftContext
            );
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (bit_submission_is_claim_conflict($e, $draftContext)) {
                bit_submission_replay_existing($draftContext);
            }
            throw $e;
        }

        bit_submission_mark_delivery_started($envioId);
        $mail = new PHPMailer(true);
        app_configure_mailer($mail, 'Supervisión Mister Wings');
        app_bitacora_add_recipients($mail, $empresaId, $sede);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();

        $pdo = Conexion::Conectar();
        $pdo->beginTransaction();
        $response = bit_submission_complete($pdo, $envioId, $draftContext, [
            'estado' => 'completado',
            'correo_enviado' => true,
            'pdf_generado' => false,
        ], true, 'El reporte de supervisión fue enviado correctamente.', [
            'correoEnviado' => true,
            'pdfGenerado' => false,
            'downloadUrl' => null,
            'warnings' => $errors,
        ]);
        $pdo->commit();
        bit_emit_json_response($response);
    } catch (Throwable $e) {
        error_log('Error enviando reporte de supervisión: ' . $e->getMessage());
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($envioId !== null) {
            try {
                $pdo = Conexion::Conectar();
                $pdo->beginTransaction();
                bit_update_envio($envioId, [
                    'estado' => 'fallido',
                    'correo_enviado' => false,
                    'pdf_generado' => false,
                    'error_mensaje' => $e->getMessage(),
                ], $pdo);
                $response = bit_json_body(false, 'No se pudo enviar el reporte de supervisión.', ['draftFinalized' => false]);
                bit_submission_store_response($pdo, $envioId, $response);
                $pdo->commit();
                bit_emit_json_response($response);
            } catch (Throwable $storeError) {
                if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('No fue posible cerrar el envío fallido de supervisión: ' . $storeError->getMessage());
            }
        }
        bit_json_response(false, 'No se pudo enviar el reporte de supervisión.', ['draftFinalized' => false], 503);
    }
}

function bit_handle_operational(int $empresaId, ?array $draftContext = null): void
{
    $action = trim((string) ($_POST['bitacora_action'] ?? 'send'));
    $generatePdfOnly = $action === 'generate_pdf';

    $sede = trim((string) ($_POST['sede'] ?? ''));
    $companyConfig = app_bitacora_config($empresaId) ?? [];
    [$validSede, $sedeMessage] = bit_validate_sede($companyConfig, $sede);
    if (!$validSede) {
        bit_json_response(false, $sedeMessage);
    }

    $defaults = bit_get_default_texts();
    $config = bit_get_config($empresaId, $sede);
    $sections = app_bitacora_form_sections($empresaId, $companyConfig);
    $rules = bit_get_conditional_rules($defaults);

    [$validSchema, $schemaMessage] = bit_validate_schema_fields($sections, $sede);
    if (!$validSchema) {
        bit_json_response(false, $schemaMessage);
    }

    [$validQuantityGroups, $quantityMessage] = bit_validate_quantity_groups($config['quantity_groups'] ?? [], trim((string) ($_POST['fechab'] ?? '')));
    if (!$validQuantityGroups) {
        bit_json_response(false, $quantityMessage);
    }

    [$validDirectQuantityGroups, $directQuantityMessage] = bit_validate_direct_quantity_groups($config['direct_quantity_groups'] ?? [], trim((string) ($_POST['fechab'] ?? '')));
    if (!$validDirectQuantityGroups) {
        bit_json_response(false, $directQuantityMessage);
    }

    [$validDetailGroups, $detailMessage] = bit_validate_detail_groups($config['detail_groups'] ?? [], trim((string) ($_POST['fechab'] ?? '')));
    if (!$validDetailGroups) {
        bit_json_response(false, $detailMessage);
    }

    [$validBranchGroups, $branchMessage] = bit_validate_branch_groups($config['branch_groups'] ?? [], trim((string) ($_POST['fechab'] ?? '')));
    if (!$validBranchGroups) {
        bit_json_response(false, $branchMessage);
    }

    [$validVisitGroups, $visitMessage] = bit_validate_multiselect_detail_groups($config['multiselect_detail_groups'] ?? []);
    if (!$validVisitGroups) {
        bit_json_response(false, $visitMessage);
    }

    $conditionalDefaultFields = bit_apply_conditional_defaults($_POST, $rules);
    $conditionalDefaultFields = array_values(array_unique(array_merge(
        $conditionalDefaultFields,
        bit_handle_planta_electrica($_POST, $defaults)
    )));

    $data = bit_normalize_data($_POST, $config);
    $data['_conditional_default_fields'] = $conditionalDefaultFields;
    $html = bit_render_html($data, $config);
    $pdfHtml = bit_render_html($data, $config, true);
    $subject = 'BITÁCORA SEDE ' . preg_replace('/[\r\n]+/', ' ', (string) ($data['sede'] ?? ''));
    $usuario = (string) ($_SESSION['s_usuario'] ?? '');
    $errors = [];
    $pdfInfo = null;
    $pdfRecord = null;
    $pdfGenerado = false;
    try {
        $pdfInfo = bit_build_pdf_info(
            $empresaId,
            $data['sede'] ?? '',
            $data['fecha'] ?? date('d-m-Y'),
            $data['responsable'] ?? 'SIN_RESPONSABLE'
        );
        bit_generate_pdf($pdfHtml, $pdfInfo['path']);
        $pdfGenerado = is_file($pdfInfo['path']);
    } catch (Throwable $e) {
        error_log('Error generando PDF bitácora unificada: ' . $e->getMessage());
        if ($pdfInfo !== null && is_file((string) $pdfInfo['path'])) {
            @unlink((string) $pdfInfo['path']);
        }
        $errors[] = 'No se pudo generar el PDF.';
    }

    if ($generatePdfOnly) {
        if ($pdfGenerado && $pdfInfo !== null) {
            $pdfRecord = bit_register_pdf(
                $pdfInfo,
                (string) ($data['sede'] ?? ''),
                (string) ($data['fecha_iso'] ?? $data['fecha'] ?? ''),
                (string) ($data['responsable'] ?? ''),
                $usuario
            );
            if ($pdfRecord === null) {
                $pdfGenerado = false;
                $errors[] = 'El PDF se generó, pero no quedó disponible para descarga.';
            }
        }
        $downloadUrl = $pdfGenerado ? bit_pdf_download_url($pdfRecord) : null;
        bit_json_response($pdfGenerado, $pdfGenerado ? 'PDF generado correctamente.' : 'No se pudo preparar o generar el PDF.', [
            'correoEnviado' => null,
            'pdfGenerado' => $pdfGenerado,
            'pdfFileName' => ($pdfGenerado && $pdfInfo !== null) ? $pdfInfo['fileName'] : null,
            'downloadUrl' => $downloadUrl,
            'pdfOnly' => true,
            'warnings' => $errors,
            'draftFinalized' => false,
        ]);
    }

    $correoEnviado = false;
    $fullRecipients = [];
    $asyncCommitAmbiguous = false;
    try {
        $fullRecipients = app_bitacora_recipients_for_sede($empresaId, $sede);
    } catch (Throwable $e) {
        error_log('Error consultando destinatarios de bitácora: ' . $e->getMessage());
        $errors[] = 'No fue posible consultar los destinatarios.';
    }

    if (bit_mail_async_enabled() && $fullRecipients !== []) {
        try {
            require_once __DIR__ . '/../bd/conexion.php';
            $pdo = Conexion::Conectar();
            $pdo->beginTransaction();
            try {
                $envioId = bit_submission_claim_envio(
                    $pdo,
                    $empresaId,
                    (string) ($data['sede'] ?? ''),
                    (string) ($data['fecha_iso'] ?? $data['fecha'] ?? ''),
                    (string) ($data['responsable'] ?? ''),
                    $usuario,
                    'operational',
                    $draftContext
                );
                if ($pdfGenerado && $pdfInfo !== null) {
                    $pdfRecord = bit_register_pdf(
                        $pdfInfo,
                        (string) ($data['sede'] ?? ''),
                        (string) ($data['fecha_iso'] ?? $data['fecha'] ?? ''),
                        (string) ($data['responsable'] ?? ''),
                        $usuario,
                        $pdo
                    );
                    if ($pdfRecord === null) {
                        $pdfGenerado = false;
                        $errors[] = 'El PDF no quedó registrado.';
                    }
                }
                $downloadUrl = $pdfGenerado ? bit_pdf_download_url($pdfRecord) : null;
                $attachments = $pdfGenerado ? bit_pdf_queue_attachments($pdfInfo) : [];
                bit_enqueue_email($envioId, $empresaId, $sede, $usuario, $subject, $html, $fullRecipients, $attachments, $pdo);
                $correosSeccionEncolados = bit_queue_section_emails($empresaId, $sede, $usuario, $data, $sections, $fullRecipients, $subject, $pdo, $envioId);
                $message = $pdfGenerado
                    ? 'La bitácora fue registrada, el PDF se generó y el correo quedó en cola.'
                    : 'La bitácora fue registrada y el correo quedó en cola, pero no se pudo generar el PDF.';
                $response = bit_submission_complete($pdo, $envioId, $draftContext, [
                    'estado' => 'pendiente',
                    'correo_enviado' => false,
                    'pdf_generado' => $pdfGenerado,
                'correos_seccion_enviados' => 0,
                    'pdf_id' => is_array($pdfRecord) ? ($pdfRecord['pdfId'] ?? null) : null,
                    'error_mensaje' => $errors === [] ? null : implode("\n", $errors),
                ], true, $message, [
                    'correoEnviado' => null,
                    'correoEncolado' => true,
                'correosSeccionEncolados' => $correosSeccionEncolados,
                'pdfGenerado' => $pdfGenerado,
                'pdfFileName' => ($pdfGenerado && $pdfInfo !== null) ? $pdfInfo['fileName'] : null,
                    'downloadUrl' => $downloadUrl,
                    'warnings' => $errors,
                ]);
                $asyncCommitAmbiguous = true;
                $pdo->commit();
                $asyncCommitAmbiguous = false;
                bit_emit_json_response($response);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                    $asyncCommitAmbiguous = false;
                }
                if (bit_submission_is_claim_conflict($e, $draftContext)) {
                    if ($pdfGenerado && $pdfInfo !== null && is_file((string) $pdfInfo['path'])) {
                        @unlink((string) $pdfInfo['path']);
                    }
                    bit_submission_replay_existing($draftContext);
                }
                if (!$asyncCommitAmbiguous && $pdfGenerado && $pdfInfo !== null && is_file((string) $pdfInfo['path'])) {
                    @unlink((string) $pdfInfo['path']);
                }
                throw $e;
            }
        } catch (Throwable $e) {
            error_log('No fue posible encolar bitácora unificada; se intentará envío síncrono: ' . $e->getMessage());
        }
    }

    $envioId = null;
    try {
        require_once __DIR__ . '/../bd/conexion.php';
        $pdo = Conexion::Conectar();
        $pdo->beginTransaction();
        try {
            $envioId = bit_submission_claim_envio(
                $pdo,
                $empresaId,
                (string) ($data['sede'] ?? ''),
                (string) ($data['fecha_iso'] ?? $data['fecha'] ?? ''),
                (string) ($data['responsable'] ?? ''),
                $usuario,
                'operational',
                $draftContext
            );
            if ($pdfGenerado && $pdfInfo !== null) {
                $pdfRecord = bit_register_pdf(
                    $pdfInfo,
                    (string) ($data['sede'] ?? ''),
                    (string) ($data['fecha_iso'] ?? $data['fecha'] ?? ''),
                    (string) ($data['responsable'] ?? ''),
                    $usuario,
                    $pdo
                );
                if ($pdfRecord === null) {
                    $pdfGenerado = false;
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (bit_submission_is_claim_conflict($e, $draftContext)) {
                if (!$asyncCommitAmbiguous && $pdfGenerado && $pdfInfo !== null && is_file((string) $pdfInfo['path'])) {
                    @unlink((string) $pdfInfo['path']);
                }
                bit_submission_replay_existing($draftContext);
            }
            throw $e;
        }
    } catch (Throwable $e) {
        error_log('No fue posible reservar la bitácora unificada: ' . $e->getMessage());
        if (!$asyncCommitAmbiguous && $pdfGenerado && $pdfInfo !== null && is_file((string) $pdfInfo['path'])) {
            @unlink((string) $pdfInfo['path']);
        }
        bit_json_response(false, 'No fue posible registrar el envío. Intenta nuevamente.', ['draftFinalized' => false], 503);
    }

    $downloadUrl = $pdfGenerado ? bit_pdf_download_url($pdfRecord) : null;
    try {
        bit_submission_mark_delivery_started($envioId);
        $mail = new PHPMailer(true);
        app_configure_mailer($mail);
        app_bitacora_add_recipient_list($mail, $fullRecipients);
        $mail->Subject = $subject;
        $mail->Body = $html;

        if ($pdfGenerado && $pdfInfo !== null) {
            $mail->addAttachment($pdfInfo['path'], $pdfInfo['fileName']);
        }

        $correoEnviado = $mail->send();
    } catch (Throwable $e) {
        error_log('Error enviando bitácora unificada: ' . $e->getMessage());
        $errors[] = 'No fue posible enviar el correo principal.';
    }

    $correosSeccionEnviados = 0;
    $correosSeccionEnviados = bit_send_section_emails($empresaId, $sede, $data, $sections, $fullRecipients, $subject);

    if ($correoEnviado && $pdfGenerado) {
        $message = 'La bitácora fue enviada correctamente y el PDF se generó con éxito.';
    } elseif (!$correoEnviado && $pdfGenerado) {
        $message = 'No se pudo enviar el correo, pero el PDF sí fue generado correctamente.';
    } elseif ($correoEnviado) {
        $message = 'El correo fue enviado, pero no se pudo generar el PDF.';
    } else {
        $message = 'No se pudo enviar el correo ni generar el PDF.';
    }

    $estadoEnvio = ($correoEnviado && $pdfGenerado) ? 'completado' : (($correoEnviado || $pdfGenerado) ? 'parcial' : 'fallido');
    $extra = [
        'correoEnviado' => $correoEnviado,
        'correosSeccionEnviados' => $correosSeccionEnviados,
        'pdfGenerado' => $pdfGenerado,
        'pdfFileName' => ($pdfGenerado && $pdfInfo !== null) ? $pdfInfo['fileName'] : null,
        'downloadUrl' => $downloadUrl,
        'warnings' => $errors,
    ];
    try {
        $pdo = Conexion::Conectar();
        $pdo->beginTransaction();
        $envioUpdate = [
            'estado' => $estadoEnvio,
            'correo_enviado' => $correoEnviado,
            'pdf_generado' => $pdfGenerado,
            'correos_seccion_enviados' => $correosSeccionEnviados,
            'pdf_id' => is_array($pdfRecord) ? ($pdfRecord['pdfId'] ?? null) : null,
            'error_mensaje' => $errors === [] ? null : implode("\n", $errors),
        ];
        if ($correoEnviado) {
            $response = bit_submission_complete($pdo, $envioId, $draftContext, $envioUpdate, ($correoEnviado || $pdfGenerado), $message, $extra);
        } else {
            bit_update_envio($envioId, $envioUpdate, $pdo);
            $extra['draftFinalized'] = false;
            $response = bit_json_body(($correoEnviado || $pdfGenerado), $message, $extra);
            bit_submission_store_response($pdo, $envioId, $response);
        }
        $pdo->commit();
        bit_emit_json_response($response);
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('No fue posible finalizar la bitácora unificada: ' . $e->getMessage());
        bit_json_response(false, 'El envío terminó, pero no fue posible confirmar su estado. No lo repitas hasta verificar el historial.', ['draftFinalized' => false], 503);
    }
}
