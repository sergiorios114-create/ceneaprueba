<?php
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
header('Content-Type: text/html; charset=UTF-8');
require 'auth.php';
checkAuth();

// Función para limpiar y normalizar texto a UTF-8
function limpiarUTF8($valor) {
    if (!is_string($valor)) return $valor;
    $codificacion = mb_detect_encoding($valor, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ISO-8859-15'], true);
    if ($codificacion && $codificacion !== 'UTF-8') {
        $valor = mb_convert_encoding($valor, 'UTF-8', $codificacion);
    }
    $valor = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $valor);
    return $valor;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}
csrfValidate();

$id          = $_POST['id'] ?? '';
$nombre      = limpiarUTF8($_POST['nombre'] ?? '');
$especialidad = limpiarUTF8($_POST['especialidad'] ?? '');
$bio         = limpiarUTF8($_POST['bio'] ?? '');
$link_reserva = limpiarUTF8($_POST['link_reserva'] ?? '');
$telemedicina = isset($_POST['telemedicina']) ? true : false;

// Categorías: puede venir como array (checkboxes múltiples)
$categoriaRaw = $_POST['categoria'] ?? [];
if (is_string($categoriaRaw)) {
    $categoriaRaw = [$categoriaRaw];
}
$categorias = array_values(array_filter(array_map('trim', $categoriaRaw)));

$perfilesFile = '../data/perfiles.json';
$perfiles = file_exists($perfilesFile) ? json_decode(file_get_contents($perfilesFile), true) : [];

$profileIndex = -1;
if ($id) {
    foreach ($perfiles as $index => $p) {
        if ($p['id'] == $id) {
            $profileIndex = $index;
            break;
        }
    }
} else {
    $id = uniqid('doc_');
}

$imagen = '';
if ($profileIndex !== -1) {
    $imagen = $perfiles[$profileIndex]['imagen'] ?? '';
}

// Handle file upload
if (isset($_FILES['imagen_file']) && $_FILES['imagen_file']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../img/doctores/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Validación completa con fileinfo
    $ext = validarImagenSubida($_FILES['imagen_file'], 'equipo_medico');

    if (true) { // validarImagenSubida() ya hace exit si falla
        // Borrar imagen anterior si es de uploads
        if ($imagen && strpos($imagen, 'img/doctores/doc_') !== false && file_exists('../' . $imagen)) {
            unlink('../' . $imagen);
        }
        $filename = 'doc_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['imagen_file']['tmp_name'], $uploadDir . $filename)) {
            $imagen = 'img/doctores/' . $filename;
        }
    }
}

$newProfile = [
    'id'           => $id,
    'nombre'       => $nombre,
    'especialidad' => $especialidad,
    'bio'          => $bio,
    'categoria'    => $categorias,   // array
    'telemedicina' => $telemedicina, // bool
    'link_reserva' => $link_reserva,
    'imagen'       => $imagen
];

if ($profileIndex !== -1) {
    $perfiles[$profileIndex] = $newProfile;
} else {
    $perfiles[] = $newProfile;
}

if (file_put_contents($perfilesFile, json_encode($perfiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    header('Location: dashboard.php?success=' . urlencode('Perfil guardado correctamente') . '&tab=equipo_medico');
} else {
    header('Location: dashboard.php?error=' . urlencode('Error al guardar el perfil') . '&tab=equipo_medico');
}
exit;
