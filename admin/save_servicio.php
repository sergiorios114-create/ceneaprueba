<?php
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
header('Content-Type: text/html; charset=UTF-8');
require 'auth.php';
checkAuth();

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
    header('Location: dashboard.php?tab=servicios');
    exit;
}
csrfValidate();

$serviciosFile = '../data/servicios.json';
$servicios = file_exists($serviciosFile) ? json_decode(file_get_contents($serviciosFile), true) : [];

$id        = trim($_POST['id'] ?? '');
$titulo    = limpiarUTF8(trim($_POST['titulo'] ?? ''));
$desc      = limpiarUTF8(trim($_POST['descripcion'] ?? ''));
$destacado = isset($_POST['destacado']) ? true : false;
$icono     = trim($_POST['icono'] ?? 'activity');

if ($titulo === '') {
    header('Location: dashboard.php?error=El+título+es+obligatorio&tab=servicios');
    exit;
}

if ($id) {
    // Editar existente
    $found = false;
    foreach ($servicios as &$s) {
        if ($s['id'] === $id) {
            $s['titulo']      = $titulo;
            $s['descripcion'] = $desc;
            $s['icono']       = $icono;
            $s['destacado']   = $destacado;
            $found = true;
            break;
        }
    }
    unset($s);
    if (!$found) {
        header('Location: dashboard.php?error=Servicio+no+encontrado&tab=servicios');
        exit;
    }
    $successMsg = 'Servicio+actualizado+correctamente';
} else {
    // Nuevo servicio
    $maxOrden = 0;
    foreach ($servicios as $s) {
        if (($s['orden'] ?? 0) > $maxOrden) $maxOrden = $s['orden'];
    }
    $servicios[] = [
        'id'          => 'serv_' . time(),
        'titulo'      => $titulo,
        'descripcion' => $desc,
        'icono'       => $icono,
        'destacado'   => $destacado,
        'orden'       => $maxOrden + 1
    ];
    $successMsg = 'Servicio+añadido+correctamente';
}

if (file_put_contents($serviciosFile, json_encode($servicios, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    header("Location: dashboard.php?success={$successMsg}&tab=servicios");
} else {
    header('Location: dashboard.php?error=Error+al+guardar+servicios&tab=servicios');
}
exit;
