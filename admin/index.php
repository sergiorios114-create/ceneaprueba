<?php
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
require_once 'auth.php';

$error = '';

// ── Rate limiting: máximo 5 intentos fallidos, bloqueo de 15 minutos ─────────
const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCKOUT_SECS = 900; // 15 minutos

if (!isset($_SESSION['login_attempts']))   $_SESSION['login_attempts']   = 0;
if (!isset($_SESSION['login_locked_until'])) $_SESSION['login_locked_until'] = 0;

$isLocked = (time() < $_SESSION['login_locked_until']);
$minutosRestantes = $isLocked ? ceil(($_SESSION['login_locked_until'] - time()) / 60) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isLocked) {
        $error = "Demasiados intentos fallidos. Espera {$minutosRestantes} minuto(s) antes de volver a intentarlo.";
    } else {
        $user = $_POST['username'] ?? '';
        $pass = $_POST['password'] ?? '';

        if (isset($USERS[$user]) && password_verify($pass, $USERS[$user])) {
            // Login exitoso: limpiar contadores y regenerar sesión
            $_SESSION['login_attempts']    = 0;
            $_SESSION['login_locked_until'] = 0;
            session_regenerate_id(true);
            $_SESSION['logged_in'] = true;
            header('Location: /admin/dashboard.php');
            exit;
        } else {
            $_SESSION['login_attempts']++;
            if ($_SESSION['login_attempts'] >= LOGIN_MAX_ATTEMPTS) {
                $_SESSION['login_locked_until'] = time() + LOGIN_LOCKOUT_SECS;
                $error = 'Demasiados intentos fallidos. Cuenta bloqueada por 15 minutos.';
            } else {
                $intentosRestantes = LOGIN_MAX_ATTEMPTS - $_SESSION['login_attempts'];
                $error = "Credenciales incorrectas. Intentos restantes: {$intentosRestantes}";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Cenea</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f4f4f4;
            margin: 0;
            font-family: 'Inter', sans-serif;
        }

        .login-card {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        .login-card h1 {
            margin-bottom: 1.5rem;
            color: #333;
        }

        .form-group {
            margin-bottom: 1rem;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #666;
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }

        .btn-login {
            width: 100%;
            padding: 0.75rem;
            background-color: #004d40;
            /* Cenea specific color if possible, using a generic dark teal */
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn-login:hover {
            background-color: #00332a;
        }

        .error-msg {
            color: red;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
    </style>
</head>

<body>
    <div class="login-card">
        <h1>Admin Cenea</h1>
        <?php if ($error): ?>
            <p class="error-msg"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label for="username">Usuario</label>
                <input type="text" id="username" name="username" required autofocus <?= $isLocked ? 'disabled' : '' ?>>
            </div>
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required <?= $isLocked ? 'disabled' : '' ?>>
            </div>
            <button type="submit" class="btn-login" <?= $isLocked ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : '' ?>>
                <?= $isLocked ? "🔒 Bloqueado ({$minutosRestantes} min)" : 'Ingresar' ?>
            </button>
        </form>
        <p style="margin-top: 1rem;"><a href="../index.html">← Volver al sitio</a></p>
    </div>
</body>

</html>