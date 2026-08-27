<?php
require_once __DIR__ . '/../config/security.php';

app_start_session();

if (empty($_SESSION['s_usuario'])) {
    header('Location: ../index.php');
    exit;
}

$empresaId = app_current_empresa_id();
if ($empresaId > 0) {
    header('Location: bitacora.php');
    exit;
}
?>
<!doctype html>
<html lang="es">
<head>
    <link rel="shortcut icon" href="../resources/img/LOGO ALITAS-09.png" alt="Logo">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="../resources/css/bootstrap.min.css">
    <link rel="stylesheet" href="../resources/css/session_timeout.css">
    <title>Bitácora Mister Wings</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(215, 25, 32, 0.18), transparent 32rem),
                linear-gradient(135deg, #111111 0%, #2b0f0f 45%, #d71920 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            color: #1f2937;
        }

        .welcome-card {
            width: 100%;
            max-width: 460px;
            background: #ffffff;
            border-radius: 24px;
            padding: 42px 38px 32px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.35);
            text-align: center;
            animation: welcomeFadeIn 0.7s ease;
        }

        .welcome-logo {
            width: 120px;
            height: 120px;
            margin: -92px auto 20px;
            background: #ffffff;
            border-radius: 50%;
            padding: 10px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .welcome-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
        }

        .welcome-title {
            font-size: 30px;
            font-weight: 850;
            letter-spacing: -0.02em;
            color: #16181d;
            margin-bottom: 6px;
        }

        .welcome-subtitle {
            font-size: 15px;
            color: #6c757d;
            margin-bottom: 22px;
        }

        .welcome-user {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 999px;
            background: #f6f7fb;
            border: 1px solid #e5e7eb;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .welcome-user strong {
            color: #8b1116;
        }

        .welcome-message {
            background: #fdecea;
            border: 1px solid #f5c2c7;
            color: #b02a37;
            border-radius: 12px;
            padding: 14px 16px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 24px;
        }

        .welcome-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .welcome-logout {
            border: 0;
            border-radius: 14px;
            padding: 12px 16px;
            font-weight: 800;
            color: #fff;
            background: linear-gradient(135deg, #d71920, #8b0000);
            box-shadow: 0 14px 28px rgba(215, 25, 32, 0.28);
            width: 100%;
            cursor: pointer;
            transition: filter 0.2s ease, transform 0.15s ease;
        }

        .welcome-logout:hover {
            filter: brightness(1.06);
            transform: translateY(-1px);
        }

        .welcome-footer {
            margin-top: 22px;
            padding-top: 16px;
            border-top: 1px solid #eeeeee;
            font-size: 12px;
            color: #777777;
        }

        @keyframes welcomeFadeIn {
            from {
                opacity: 0;
                transform: translateY(25px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 480px) {
            .welcome-card {
                padding: 38px 24px 24px;
                border-radius: 20px;
            }

            .welcome-logo {
                width: 104px;
                height: 104px;
                margin-top: -78px;
            }

            .welcome-title {
                font-size: 25px;
            }
        }
    </style>
</head>
<body <?php echo app_session_client_attributes(); ?>>

<div class="welcome-card">
    <div class="welcome-logo">
        <img src="../resources/img/LOGO ALITAS-09.png" alt="Logo Mister Wings">
    </div>

    <h1 class="welcome-title">¡Bienvenido!</h1>
    <p class="welcome-subtitle">Bitácora digital Mister Wings</p>

    <div class="welcome-user">
        <span>Usuario:</span> <strong><?php echo app_h($_SESSION['s_nombre'] ?? ''); ?></strong>
    </div>

    <div class="welcome-message">
        No hay una vista asignada para esta empresa.
    </div>

    <div class="welcome-actions">
        <?php echo app_logout_form('welcome-logout', 'Cerrar sesión'); ?>
    </div>

    <div class="welcome-footer">
        <span>Potenciado por Edson Ramos para Mister Wings®</span>
    </div>
</div>
<?php require __DIR__ . '/session_timeout.php'; ?>

<script src="../resources/js/session_timeout.js"></script>
</body>
</html>
