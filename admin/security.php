<?php
/**
 * security.php — Cenea Admin
 * Funciones de seguridad centralizadas:
 *  - CSRF tokens (generación y validación)
 *  - Validación de uploads con fileinfo
 *  - Headers de seguridad HTTP
 */

// ── Headers de seguridad HTTP ────────────────────────────────────────────────
// Se aplican en todas las páginas del admin que incluyan este archivo.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
// Content-Security-Policy permisivo para el admin (necesita inline scripts del RTE y Google Fonts)
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:;");
// HSTS: activar solo cuando el sitio tenga HTTPS. Por ahora comentado.
// header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// ── CSRF ─────────────────────────────────────────────────────────────────────

/**
 * Genera el token CSRF de sesión (idempotente: reutiliza el existente).
 */
function csrfGenerate() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Devuelve el campo hidden HTML con el token CSRF.
 * Uso en formularios: <?= csrfField() ?>
 */
function csrfField() {
    $token = csrfGenerate();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES) . '">';
}

/**
 * Valida el token CSRF enviado en el POST.
 * Si falla, redirige al dashboard con error y termina el script.
 */
function csrfValidate() {
    $sent  = $_POST['csrf_token'] ?? '';
    $valid = $_SESSION['csrf_token'] ?? '';
    if (!$valid || !hash_equals($valid, $sent)) {
        header('Location: dashboard.php?error=Solicitud+no+válida+(CSRF)');
        exit;
    }
}

// ── Validación de uploads ─────────────────────────────────────────────────────
// MIME types permitidos para imágenes
const ALLOWED_MIME_TYPES = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/gif',
];
const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
const MAX_UPLOAD_SIZE_BYTES = 5 * 1024 * 1024; // 5 MB

/**
 * Valida un archivo subido. Verifica:
 *  - Errores de subida de PHP
 *  - Tamaño máximo (5 MB)
 *  - Extensión en whitelist
 *  - MIME type real con fileinfo (no el del cliente)
 *
 * @param array  $file       Entrada de $_FILES['campo']
 * @param string $redirectTab  Pestaña a la que redirigir en caso de error
 * @return string  Extensión validada en minúsculas
 */
function validarImagenSubida(array $file, string $redirectTab = '') {
    $tabParam = $redirectTab ? "&tab={$redirectTab}" : '';

    // 1. Error de subida PHP
    if ($file['error'] !== UPLOAD_ERR_OK) {
        header("Location: dashboard.php?error=Error+al+subir+archivo+(código+{$file['error']}){$tabParam}");
        exit;
    }

    // 2. Tamaño máximo
    if ($file['size'] > MAX_UPLOAD_SIZE_BYTES) {
        header("Location: dashboard.php?error=El+archivo+supera+el+tamaño+máximo+de+5+MB{$tabParam}");
        exit;
    }

    // 3. Extensión (whitelist)
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        header("Location: dashboard.php?error=Extensión+no+permitida.+Use+JPG,+PNG+o+WEBP{$tabParam}");
        exit;
    }

    // 4. MIME type real con fileinfo (ignora lo que diga el cliente)
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($realMime, ALLOWED_MIME_TYPES)) {
            header("Location: dashboard.php?error=Tipo+de+archivo+no+permitido{$tabParam}");
            exit;
        }
    }

    return $ext;
}
