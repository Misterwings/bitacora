<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/security.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function app_session_json_response(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

app_start_session(false);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    if (!app_refresh_session_auth(false)) {
        app_session_json_response(503, [
            'ok' => false,
            'authenticated' => false,
            'message' => 'No fue posible validar la sesión.',
        ]);
    }

    if (empty($_SESSION['s_usuario'])) {
        app_session_json_response(401, [
            'ok' => false,
            'authenticated' => false,
            'message' => 'La sesión ya no está disponible.',
        ]);
    }

    app_session_json_response(200, array_merge(
        ['ok' => true, 'authenticated' => true],
        app_session_expiration_data()
    ));
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    app_fail_request('Método no permitido.', 405, true);
}

app_require_post_login(null, true);
if (!app_touch_session_activity()) {
    app_session_json_response(401, [
        'ok' => false,
        'authenticated' => false,
        'message' => 'La sesión ya no está disponible.',
    ]);
}

app_session_json_response(200, array_merge(
    ['ok' => true, 'authenticated' => true],
    app_session_expiration_data()
));
