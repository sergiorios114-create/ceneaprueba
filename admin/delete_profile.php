<?php
require 'auth.php';
checkAuth();

$id = $_GET['id'] ?? '';
if (!$id) {
    header('Location: dashboard.php?error=ID no proporcionado');
    exit;
}

$perfilesFile = '../data/perfiles.json';
$perfiles = file_exists($perfilesFile) ? json_decode(file_get_contents($perfilesFile), true) : [];

$newPerfiles = [];
$found = false;

foreach ($perfiles as $p) {
    if ($p['id'] !== $id) {
        $newPerfiles[] = $p;
    } else {
        $found = true;
    }
}

if ($found) {
    if (file_put_contents($perfilesFile, json_encode($newPerfiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        header('Location: dashboard.php?success=Perfil eliminado correctamente');
    } else {
        header('Location: dashboard.php?error=Error al eliminar el perfil');
    }
} else {
    header('Location: dashboard.php?error=Perfil no encontrado');
}
exit;
