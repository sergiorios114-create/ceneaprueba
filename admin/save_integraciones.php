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

function limpiarUTF8($valor) {
    if (!is_string($valor)) return $valor;
    $codificacion = mb_detect_encoding($valor, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ISO-8859-15'], true);
    if ($codificacion && $codificacion !== 'UTF-8') {
        $valor = mb_convert_encoding($valor, 'UTF-8', $codificacion);
    }
    $valor = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $valor);
    return trim($valor);
}

$file = '../data/integraciones.json';
$data = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

// Campos de texto simples por integración
$integraciones = [
    'google_analytics'      => ['measurement_id'],
    'google_tag_manager'    => ['container_id'],
    'google_search_console' => ['verification_code'],
    'meta_pixel'            => ['pixel_id'],
    'meta_conversions_api'  => ['access_token'],
    'google_ads'            => ['conversion_id', 'conversion_label'],
    'hotjar'                => ['site_id'],
    'clarity'               => ['project_id'],
    'whatsapp_business'     => ['phone', 'default_message'],
    'custom_head'           => ['code'],
    'custom_body_start'     => ['code'],
    'custom_body_end'       => ['code'],
];

foreach ($integraciones as $key => $fields) {
    // enabled: checkbox = presente en POST = true, ausente = false
    $data[$key]['enabled'] = isset($_POST[$key . '_enabled']) && $_POST[$key . '_enabled'] === '1';

    foreach ($fields as $field) {
        $postKey = $key . '_' . $field;
        if (isset($_POST[$postKey])) {
            $data[$key][$field] = limpiarUTF8($_POST[$postKey]);
        }
    }
}

if (file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))) {
    header('Location: dashboard.php?success=Integraciones+guardadas+correctamente&tab=integraciones');
} else {
    header('Location: dashboard.php?error=Error+al+guardar+integraciones&tab=integraciones');
}
exit;
