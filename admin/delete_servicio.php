<?php
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
require 'auth.php';
checkAuth();

$id = $_GET['id'] ?? '';

if (!$id) {
    header('Location: dashboard.php?error=ID+no+proporcionado&tab=servicios');
    exit;
}

$serviciosFile = '../data/servicios.json';
$servicios = file_exists($serviciosFile) ? json_decode(file_get_contents($serviciosFile), true) : [];

$newList = [];
$found = false;

foreach ($servicios as $s) {
    if ($s['id'] === $id) {
        $found = true;
    } else {
        $newList[] = $s;
    }
}

if (!$found) {
    header('Location: dashboard.php?error=Servicio+no+encontrado&tab=servicios');
    exit;
}

if (file_put_contents($serviciosFile, json_encode($newList, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    header('Location: dashboard.php?success=Servicio+eliminado+correctamente&tab=servicios');
} else {
    header('Location: dashboard.php?error=Error+al+eliminar+el+servicio&tab=servicios');
}
exit;
