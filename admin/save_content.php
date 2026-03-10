<?php
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
header('Content-Type: text/html; charset=UTF-8');
require 'auth.php';
checkAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}
csrfValidate();

// Función para limpiar y normalizar texto a UTF-8
function limpiarUTF8($valor) {
    if (!is_string($valor)) return $valor;
    // Convertir a UTF-8 si viene en otra codificación
    $codificacion = mb_detect_encoding($valor, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ISO-8859-15'], true);
    if ($codificacion && $codificacion !== 'UTF-8') {
        $valor = mb_convert_encoding($valor, 'UTF-8', $codificacion);
    }
    // Eliminar caracteres de control inválidos pero conservar saltos de línea y tabs
    $valor = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $valor);
    return $valor;
}

$page = $_POST['page'] ?? '';
$validPages = ['home', 'quienes_somos', 'servicios', 'contacto', 'footer'];

if (!in_array($page, $validPages)) {
    header('Location: dashboard.php?error=Página no válida');
    exit;
}

$configFile = '../data/site_content.json';
$config = json_decode(file_get_contents($configFile), true);

if (!isset($config[$page])) {
    $config[$page] = [];
}

// Update text fields (limpiar UTF-8 en cada valor)
foreach ($_POST as $key => $value) {
    if ($key !== 'page') {
        $config[$page][$key] = limpiarUTF8($value);
    }
}

// Handle file uploads — elimina imagen anterior y guarda la nueva
foreach ($_FILES as $key => $file) {
    if ($file['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../img/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Validación completa: tamaño, extensión y MIME real con fileinfo
        $extension = validarImagenSubida($file, $page);

        if (true) { // validarImagenSubida() ya hace exit si falla
            // Eliminar imagen anterior si existe y es del directorio de uploads
            if (!empty($config[$page][$key])) {
                $oldPath = '../' . $config[$page][$key];
                if (file_exists($oldPath) && strpos($config[$page][$key], 'img/uploads/') !== false) {
                    unlink($oldPath);
                }
            }

            // Guardar nueva imagen
            $filename = $page . '_' . $key . '_' . time() . '.' . $extension;
            $targetPath = $uploadDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $config[$page][$key] = 'img/uploads/' . $filename;
            }
        }
    }
}

// Save config
if (file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    header("Location: dashboard.php?success=Contenido+de+{$page}+actualizado+correctamente&tab={$page}");
} else {
    header("Location: dashboard.php?error=Error+al+guardar+la+configuración&tab={$page}");
}
exit;
