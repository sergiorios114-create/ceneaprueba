<?php
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
require 'auth.php';
checkAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php?tab=quienes_somos');
    exit;
}
csrfValidate();

$galeriaFile = '../data/galeria.json';
$galeria = file_exists($galeriaFile) ? json_decode(file_get_contents($galeriaFile), true) : ['instalaciones' => []];

if (!isset($galeria['instalaciones'])) {
    $galeria['instalaciones'] = [];
}

$alt = trim($_POST['alt'] ?? 'Instalación Cenea');
if ($alt === '') $alt = 'Instalación Cenea';

// Verificar que se subió un archivo
if (!isset($_FILES['imagen'])) {
    header('Location: dashboard.php?error=No+se+recibió+ninguna+imagen&tab=quienes_somos');
    exit;
}

$uploadDir = '../img/quienes-somos/instalaciones/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Validación completa: error PHP, tamaño, extensión y MIME real con fileinfo
$extension = validarImagenSubida($_FILES['imagen'], 'quienes_somos');

// Generar ID único y nombre de archivo seguro
$newId = 'inst_' . time() . '_' . rand(100, 999);
$filename = 'galeria_' . time() . '_' . rand(100, 999) . '.' . $extension;
$targetPath = $uploadDir . $filename;

if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $targetPath)) {
    header('Location: dashboard.php?error=Error+al+guardar+la+imagen&tab=quienes_somos');
    exit;
}

// Calcular orden (último + 1)
$maxOrden = 0;
foreach ($galeria['instalaciones'] as $item) {
    if (($item['orden'] ?? 0) > $maxOrden) {
        $maxOrden = $item['orden'];
    }
}

// Agregar al JSON
$galeria['instalaciones'][] = [
    'id'    => $newId,
    'src'   => 'img/quienes-somos/instalaciones/' . $filename,
    'alt'   => $alt,
    'orden' => $maxOrden + 1
];

if (file_put_contents($galeriaFile, json_encode($galeria, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    header('Location: dashboard.php?success=Imagen+agregada+a+la+galería+correctamente&tab=quienes_somos');
} else {
    header('Location: dashboard.php?error=Error+al+guardar+la+galería&tab=quienes_somos');
}
exit;
