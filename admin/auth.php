<?php
// ── Sesión segura: configurar ANTES de session_start() ──────────────────────
session_set_cookie_params([
    'lifetime' => 0,         // Expira al cerrar el navegador
    'path'     => '/',
    'secure'   => false,     // Cambiar a true cuando el sitio tenga HTTPS activo
    'httponly' => true,      // La cookie NO es accesible por JavaScript (protege contra XSS)
    'samesite' => 'Strict'   // Protección básica contra CSRF
]);
session_start();

// ── Usuarios permitidos (contraseñas con hash bcrypt, cost=12) ───────────────
// NUNCA guardar contraseñas en texto plano.
// Para regenerar un hash: password_hash('nueva_contraseña', PASSWORD_BCRYPT, ['cost'=>12])
$USERS = [
    'WBA-CENEA-MASTER'  => '$2a$12$ndz.MdQJaLJKu9kaoXo3qe5ZA4DmeoVow9g9m6RN46AIrFxLKvHxy',
    'Cliente_Cenea_X92' => '$2a$12$wVIRmu.ru.NadK1xBTkR8Ot4EsQClPDLQ4SJD.aKQgstqZdb0Xp2a'
];

// ── Cargar funciones de seguridad centralizadas ──────────────────────────────
// (CSRF, headers HTTP, validación de uploads)
require_once __DIR__ . '/security.php';

// ── Verificar autenticación ──────────────────────────────────────────────────
function checkAuth()
{
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header('Location: index.php');
        exit;
    }
    // Asegurar que el token CSRF esté inicializado para esta sesión
    csrfGenerate();
}
