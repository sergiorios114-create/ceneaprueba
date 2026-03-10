<?php
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
require 'auth.php';
checkAuth();

$id = $_GET['id'] ?? '';

if (!$id) {
    header('Location: dashboard.php?error=ID+no+proporcionado&tab=quienes_somos');
    exit;
}

$galeriaFile = '../data/galeria.json';
$galeria = file_exists($galeriaFile) ? json_decode(file_get_contents($galeriaFile), true) : ['instalaciones' => []];

$found = false;
$newList = [];

foreach ($galeria['instalaciones'] as $item) {
    if ($item['id'] === $id) {
        $found = true;
        // Eliminar el archivo físico del disco
        // Solo elimina archivos en img/quienes-somos/instalaciones/ (no los originales fuera de ese path si no coinciden)
        $filePath = '../' . $item['src'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    } else {
        $newList[] = $item;
    }
}

if (!$found) {
    header('Location: dashboard.php?error=Imagen+no+encontrada&tab=quienes_somos');
    exit;
}

$galeria['instalaciones'] = $newList;

if (file_put_contents($galeriaFile, json_encode($galeria, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    header('Location: dashboard.php?success=Imagen+eliminada+correctamente&tab=quienes_somos');
} else {
    header('Location: dashboard.php?error=Error+al+actualizar+la+galería&tab=quienes_somos');
}
exit;
