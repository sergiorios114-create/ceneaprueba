<?php
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
require 'auth.php';
checkAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}
csrfValidate();

$id = $_POST['id'] ?? '';
$nombre = $_POST['nombre'] ?? '';

if (!$id) {
    // Generate id from name lowercase and replacing non-alphanumeric with hyphens
    $id = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $nombre)));
    $id = trim($id, '-');
}

$catFile = '../data/categorias.json';
$categorias = file_exists($catFile) ? json_decode(file_get_contents($catFile), true) : [];

$found = false;
foreach ($categorias as &$c) {
    if ($c['id'] === $id) {
        $c['nombre'] = $nombre;
        $found = true;
        break;
    }
}
if (!$found) {
    $categorias[] = ['id' => $id, 'nombre' => $nombre];
}

if (file_put_contents($catFile, json_encode($categorias, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    header('Location: dashboard.php?success=Categoría guardada correctamente');
} else {
    header('Location: dashboard.php?error=Error al guardar la categoría');
}
exit;
