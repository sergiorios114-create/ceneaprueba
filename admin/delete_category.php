<?php
require 'auth.php';
checkAuth();

$id = $_GET['id'] ?? '';
if (!$id) {
    header('Location: dashboard.php?error=ID no proporcionado');
    exit;
}

$catFile = '../data/categorias.json';
$categorias = file_exists($catFile) ? json_decode(file_get_contents($catFile), true) : [];

$newCategorias = [];
$found = false;

foreach ($categorias as $c) {
    if ($c['id'] !== $id) {
        $newCategorias[] = $c;
    } else {
        $found = true;
    }
}

if ($found) {
    if (file_put_contents($catFile, json_encode($newCategorias, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        header('Location: dashboard.php?success=Categoría eliminada correctamente');
    } else {
        header('Location: dashboard.php?error=Error al eliminar la categoría');
    }
} else {
    header('Location: dashboard.php?error=Categoría no encontrada');
}
exit;
