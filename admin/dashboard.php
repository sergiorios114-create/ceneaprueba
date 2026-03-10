<?php
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
header('Content-Type: text/html; charset=UTF-8');
require 'auth.php';
checkAuth();

$configFile = '../data/site_content.json';
$config = json_decode(file_get_contents($configFile), true);

$perfilesFile = '../data/perfiles.json';
$perfiles = file_exists($perfilesFile) ? json_decode(file_get_contents($perfilesFile), true) : [];

$catFile = '../data/categorias.json';
$categorias = file_exists($catFile) ? json_decode(file_get_contents($catFile), true) : [];

$seoFile = '../data/seo.json';
$seo = file_exists($seoFile) ? json_decode(file_get_contents($seoFile), true) : ['global'=>[],'pages'=>[]];

$intFile = '../data/integraciones.json';
$integ = file_exists($intFile) ? json_decode(file_get_contents($intFile), true) : [];

// Helper para integraciones
function integVal($integ, $key, $field) {
    return htmlspecialchars($integ[$key][$field] ?? '', ENT_QUOTES, 'UTF-8');
}
function integEnabled($integ, $key) {
    return !empty($integ[$key]['enabled']);
}

$msg = $_GET['success'] ?? '';
$err = $_GET['error'] ?? '';
// Recordar pestaña activa después de guardar
$activeTab = $_GET['tab'] ?? 'home';
$validTabs = ['home', 'quienes_somos', 'equipo_medico', 'servicios', 'contacto', 'footer', 'seo', 'integraciones'];
if (!in_array($activeTab, $validTabs)) $activeTab = 'home';

// Helpers SEO
function seoVal($seo, $section, $key, $sub = null) {
    if ($sub) {
        return htmlspecialchars($seo[$section][$key][$sub] ?? '', ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars($seo[$section][$key] ?? '', ENT_QUOTES, 'UTF-8');
}
function seoPageVal($seo, $pageKey, $field) {
    return htmlspecialchars($seo['pages'][$pageKey][$field] ?? '', ENT_QUOTES, 'UTF-8');
}

function val($config, $section, $key) {
    return htmlspecialchars($config[$section][$key] ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * RTE inline de una sola línea (para títulos).
 * Genera un div contenteditable con toolbar compacta.
 * $name  : nombre del campo (POST)
 * $value : valor HTML actual
 * $id    : id opcional
 */
function rte_inline($name, $value = '', $id = '') {
    $idAttr  = $id ? ' id="' . htmlspecialchars($id, ENT_QUOTES) . '"' : '';
    $wrapId  = $id ? ' id="' . htmlspecialchars($id, ENT_QUOTES) . '-rte"' : '';
    return '<div class="rte-wrap rte-inline-wrap"' . $wrapId . '>'
         . '<textarea class="rte-hidden" name="' . htmlspecialchars($name, ENT_QUOTES) . '"'
         . $idAttr
         . ' data-min-height="36" data-inline="1">'
         . $value
         . '</textarea>'
         . '</div>';
}

/**
 * Genera un editor de texto enriquecido (RTE).
 * $name   : nombre del campo (para el POST)
 * $value  : valor HTML actual del campo
 * $id     : id opcional del textarea oculto
 * $minH   : altura mínima del editor (px)
 */
function rte($name, $value = '', $id = '', $minH = 90) {
    $idAttr = $id ? ' id="' . htmlspecialchars($id, ENT_QUOTES) . '"' : '';
    $wrapId = $id ? ' id="' . htmlspecialchars($id, ENT_QUOTES) . '-rte"' : '';
    // El textarea oculto almacena el HTML directamente (no escapado),
    // ya que el campo no se muestra al usuario y el JS lo lee como innerHTML.
    // Al hacer submit, el navegador envía el valor como texto plano al servidor.
    return '<div class="rte-wrap"' . $wrapId . '>'
         . '<textarea class="rte-hidden" name="' . htmlspecialchars($name, ENT_QUOTES) . '"'
         . $idAttr
         . ' data-min-height="' . (int)$minH . '">'
         . $value
         . '</textarea>'
         . '</div>';
}
function imgPreview($config, $section, $key, $label = '') {
    $path = $config[$section][$key] ?? '';
    $out = '';
    if ($path) {
        $out .= '<div class="img-preview-wrap">';
        $out .= '<img src="../' . htmlspecialchars($path, ENT_QUOTES) . '" class="current-img-preview" alt="' . htmlspecialchars($label) . '">';
        $out .= '<span class="img-preview-name">' . htmlspecialchars(basename($path)) . '</span>';
        $out .= '</div>';
    }
    return $out;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin — Cenea</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f0f2f5;
            margin: 0;
            padding: 20px;
            color: #1a1a2e;
            overflow-x: hidden;
        }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        h1 { margin: 0; color: #111; font-size: 1.6rem; }
        .logout { color: #d32f2f; text-decoration: none; font-weight: 500; font-size: 0.95rem; }
        .container { max-width: 1100px; margin: 0 auto; }

        /* Alerts */
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 0.95rem; }
        .alert-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .alert-error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }

        /* Tabs */
        .tabs { display: flex; border-bottom: 2px solid #ddd; margin-bottom: 0; gap: 4px; flex-wrap: wrap; }
        .tab-btn {
            padding: 10px 18px;
            cursor: pointer;
            background: #e8eaed;
            border: 2px solid transparent;
            border-bottom: none;
            font-size: 0.9rem;
            font-weight: 500;
            color: #555;
            border-radius: 6px 6px 0 0;
            transition: all 0.2s;
        }
        .tab-btn:hover { background: #fff; color: #004d40; }
        .tab-btn.active {
            background: #fff;
            border-color: #ddd;
            border-bottom: 2px solid #fff;
            margin-bottom: -2px;
            color: #004d40;
            font-weight: 600;
        }
        .tab-content {
            display: none;
            background: white;
            padding: 28px;
            border-radius: 0 6px 6px 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            border: 1px solid #ddd;
            border-top: none;
        }
        .tab-content.active { display: block; }

        /* Sections dentro de tab */
        .section-block {
            border: 1px solid #e8eaed;
            border-radius: 8px;
            padding: 20px 24px;
            margin-bottom: 24px;
            background: #fafbfc;
        }
        .section-block h3 {
            margin: 0 0 18px 0;
            font-size: 1rem;
            font-weight: 600;
            color: #004d40;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0f2f1;
        }
        .section-block h4 {
            margin: 0 0 14px 0;
            font-size: 0.9rem;
            font-weight: 600;
            color: #444;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Form Elements */
        .form-group { margin-bottom: 18px; }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            /* Evitar que columnas se compriman por debajo de 200px */
            min-width: 0;
        }
        .form-row > * { min-width: 0; }
        label { display: block; margin-bottom: 6px; font-weight: 500; color: #333; font-size: 0.9rem; }
        input[type="text"],
        textarea,
        select {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-family: inherit;
            font-size: 0.95rem;
            color: #1a1a2e;
            background: #fff;
            transition: border-color 0.2s;
        }
        input[type="text"]:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: #004d40;
            box-shadow: 0 0 0 3px rgba(0,77,64,0.08);
        }
        textarea { resize: vertical; min-height: 90px; }
        .help-text { font-size: 0.8rem; color: #888; margin-top: 5px; }

        /* Image preview */
        .img-preview-wrap {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            background: #f0f2f5;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 10px;
        }
        .current-img-preview {
            width: 80px;
            height: 60px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #eee;
            display: block;
        }
        .img-preview-name { font-size: 0.8rem; color: #666; max-width: 200px; word-break: break-all; }
        .img-note { font-size: 0.82rem; color: #e65100; margin-top: 6px; }

        /* Buttons */
        .btn-save {
            background: #004d40;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 500;
            margin-top: 8px;
            transition: background 0.2s;
        }
        .btn-save:hover { background: #00332a; }
        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 500;
            margin-top: 8px;
            margin-left: 8px;
            transition: background 0.2s;
        }
        .btn-secondary:hover { background: #545b62; }

        /* Servicios */
        .servicios-admin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 14px;
            margin-top: 16px;
        }
        .servicio-admin-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 14px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .servicio-admin-card--highlight {
            border-left: 4px solid #004d40;
            background: #f0faf8;
        }
        .servicio-admin-card__titulo {
            font-weight: 600;
            color: #111;
            font-size: 0.95rem;
            margin-bottom: 6px;
        }
        .servicio-admin-card__desc {
            font-size: 0.82rem;
            color: #666;
            margin-bottom: 6px;
            flex: 1;
        }
        .servicio-admin-card__badge {
            font-size: 0.72rem;
            background: #e0f2f1;
            color: #004d40;
            padding: 2px 8px;
            border-radius: 12px;
            display: inline-block;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .servicio-admin-card__actions {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #f0f0f0;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        /* Equipo médico */
        .perfiles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 14px;
            margin-top: 16px;
        }
        .perfil-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 14px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .perfil-card__header { display: flex; gap: 12px; align-items: flex-start; }
        .perfil-card__img {
            width: 56px; height: 56px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #e0f2f1;
            flex-shrink: 0;
        }
        .perfil-card__no-img {
            width: 56px; height: 56px;
            border-radius: 50%;
            background: #eee;
            display: flex; align-items: center; justify-content: center;
            color: #aaa; font-size: 0.7rem; text-align: center;
            flex-shrink: 0;
        }
        .perfil-card__nombre { font-weight: 600; color: #111; font-size: 0.95rem; margin-bottom: 2px; }
        .perfil-card__esp { font-size: 0.82rem; color: #555; margin-bottom: 6px; }
        .perfil-card__cat {
            font-size: 0.72rem;
            background: #e0f2f1;
            color: #004d40;
            padding: 2px 8px;
            border-radius: 12px;
            display: inline-block;
            font-weight: 600;
        }
        .perfil-card__actions {
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1px solid #f0f0f0;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        .btn-edit { background: none; border: none; color: #004d40; cursor: pointer; text-decoration: underline; font-size: 0.88rem; padding: 0; }
        .btn-delete { color: #d32f2f; text-decoration: none; font-size: 0.88rem; }
        .cat-list { list-style: none; padding: 0; margin: 12px 0 0; }
        .cat-list li { padding: 7px 0; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; gap: 8px; font-size: 0.92rem; }
        .cat-list li:last-child { border-bottom: none; }
        .cat-id { color: #999; font-size: 0.8rem; }

        /* ── Rich Text Editor ─────────────────────────────── */
        .rte-wrap { border: 1px solid #d1d5db; border-radius: 6px; overflow: hidden; background: #fff; }
        .rte-wrap:focus-within { border-color: #004d40; box-shadow: 0 0 0 3px rgba(0,77,64,0.08); }
        .rte-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            padding: 7px 10px;
            background: #f5f7fa;
            border-bottom: 1px solid #e0e0e0;
            align-items: center;
            min-height: 42px;
        }
        .rte-sep { width: 1px; height: 22px; background: #ccc; margin: 0 4px; flex-shrink: 0; }
        .rte-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 30px;
            padding: 0 7px;
            border: 1px solid transparent;
            border-radius: 4px;
            background: transparent;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            color: #333;
            transition: all 0.15s;
            font-family: inherit;
            line-height: 1;
            white-space: nowrap;
        }
        .rte-btn:hover { background: #e8eaed; border-color: #ccc; }
        .rte-btn.active { background: #004d40; color: #fff; border-color: #004d40; }
        .rte-btn[data-cmd="bold"] { font-weight: 900; }
        .rte-btn[data-cmd="italic"] { font-style: italic; }
        .rte-btn[data-cmd="underline"] { text-decoration: underline; }
        /* Color swatches */
        .rte-color-group { display: flex; gap: 3px; align-items: center; flex-wrap: wrap; }
        .rte-color-btn {
            width: 22px;
            height: 22px;
            border-radius: 3px;
            border: 2px solid transparent;
            cursor: pointer;
            padding: 0;
            flex-shrink: 0;
            transition: border-color 0.15s, transform 0.1s;
        }
        .rte-color-btn:hover { border-color: #555; transform: scale(1.15); }
        .rte-color-label { font-size: 0.75rem; color: #555; margin-right: 2px; font-weight: 500; }
        /* Highlight colors */
        .rte-highlight-group { display: flex; gap: 3px; align-items: center; flex-wrap: wrap; }
        .rte-body {
            min-height: 90px;
            padding: 10px 12px;
            outline: none;
            font-family: inherit;
            font-size: 0.95rem;
            color: #1a1a2e;
            line-height: 1.6;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .rte-body:empty::before {
            content: attr(data-placeholder);
            color: #aaa;
            pointer-events: none;
        }
        /* Ocultar el textarea real */
        .rte-hidden { display: none !important; }

        /* ── Modal Edición Rápida ─────────────────────── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            z-index: 9000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-overlay.open { display: flex; }
        .modal-box {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.22);
            width: 100%;
            max-width: 520px;
            overflow: hidden;
            animation: modalIn 0.18s ease;
        }
        @keyframes modalIn {
            from { opacity:0; transform: translateY(-16px) scale(0.97); }
            to   { opacity:1; transform: translateY(0) scale(1); }
        }
        .modal-header {
            background: #004d40;
            color: #fff;
            padding: 16px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .modal-header h3 { margin:0; font-size: 1rem; font-weight: 600; }
        .modal-close {
            background: none; border: none; color: #fff;
            font-size: 1.4rem; cursor: pointer; line-height: 1;
            opacity: 0.8; padding: 0 4px;
        }
        .modal-close:hover { opacity: 1; }
        .modal-body { padding: 22px 24px; }
        .modal-footer {
            padding: 14px 24px;
            background: #f5f7fa;
            border-top: 1px solid #e8eaed;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .modal-perfil-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #f0f0f0;
        }
        .modal-perfil-img {
            width: 60px; height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e0f2f1;
            flex-shrink: 0;
        }
        .modal-perfil-noimg {
            width: 60px; height: 60px;
            border-radius: 50%;
            background: #e8eaed;
            display: flex; align-items: center; justify-content: center;
            color: #999; font-size: 1.4rem; flex-shrink: 0;
        }
        .modal-perfil-name { font-size: 1rem; font-weight: 600; color: #111; }
        .modal-perfil-id   { font-size: 0.78rem; color: #999; }
        /* Cats checkboxes en el modal */
        .modal-cats-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 6px;
        }
        .modal-cat-label {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border: 1.5px solid #ddd;
            border-radius: 20px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.15s;
            user-select: none;
            color: #444;
        }
        .modal-cat-label:hover { border-color: #004d40; color: #004d40; }
        .modal-cat-label input { display: none; }
        .modal-cat-label.checked {
            background: #e0f2f1;
            border-color: #004d40;
            color: #004d40;
            font-weight: 600;
        }
        /* Toggle telemedicina */
        .tele-toggle-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 4px;
            padding: 10px 14px;
            background: #f9f9f9;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: border-color 0.15s;
        }
        .tele-toggle-wrap:hover { border-color: #004d40; }
        .tele-toggle-wrap.on { border-color: #2e7d32; background: #f1fdf3; }
        .tele-toggle {
            width: 40px; height: 22px;
            background: #ccc;
            border-radius: 11px;
            position: relative;
            flex-shrink: 0;
            transition: background 0.2s;
        }
        .tele-toggle::after {
            content: '';
            position: absolute;
            top: 3px; left: 3px;
            width: 16px; height: 16px;
            background: #fff;
            border-radius: 50%;
            transition: left 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .tele-toggle-wrap.on .tele-toggle { background: #2e7d32; }
        .tele-toggle-wrap.on .tele-toggle::after { left: 21px; }
        .tele-toggle-text { font-size: 0.9rem; color: #444; }
        .tele-toggle-wrap.on .tele-toggle-text { color: #2e7d32; font-weight: 600; }
        /* Botón edición rápida en card */
        .btn-quick-edit {
            background: #004d40;
            color: #fff;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.78rem;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.15s;
        }
        .btn-quick-edit:hover { background: #00332a; }

        /* RTE inline (una línea, para títulos) */
        .rte-inline-wrap .rte-toolbar {
            padding: 4px 6px;
            gap: 2px;
            min-height: auto;
            flex-wrap: nowrap;   /* ← toolbar siempre en 1 fila */
            overflow: hidden;
        }
        .rte-inline-wrap .rte-btn { min-width: 26px; height: 24px; font-size: 0.78rem; padding: 0 4px; }
        .rte-inline-wrap .rte-color-btn { width: 16px; height: 16px; flex-shrink: 0; }
        .rte-inline-wrap .rte-sep { height: 16px; margin: 0 2px; }
        .rte-inline-wrap .rte-color-label { font-size: 0.68rem; white-space: nowrap; }
        .rte-inline-wrap .rte-color-group { flex-wrap: nowrap; gap: 2px; }
        .rte-inline-wrap .rte-highlight-group { flex-wrap: nowrap; gap: 2px; }
        .rte-inline-wrap .rte-body {
            min-height: 34px !important;
            max-height: 34px;
            overflow: hidden;
            white-space: nowrap;
            padding: 7px 10px;
            line-height: 1.3;
        }

        /* En columnas de form-row: ocultar labels de color para ahorrar espacio */
        @media (min-width: 769px) {
            /* Inline dentro de form-row: sin labels "Color:" ni "Marcador:" */
            .form-row .rte-inline-wrap .rte-color-label { display: none; }

            /* Multiline dentro de form-row: toolbar más compacta */
            .form-row .rte-toolbar {
                padding: 5px 7px;
                gap: 2px;
            }
            .form-row .rte-btn { min-width: 26px; height: 26px; font-size: 0.8rem; padding: 0 5px; }
            .form-row .rte-color-btn { width: 18px; height: 18px; }
            .form-row .rte-color-label { font-size: 0.68rem; }
            .form-row .rte-sep { height: 18px; margin: 0 2px; }
        }

        /* ── Responsive / Mobile ─────────────────────────── */
        @media (max-width: 768px) {
            body { padding: 12px 10px; }
            h1 { font-size: 1.25rem; }

            /* Header mobile */
            header {
                flex-direction: column;
                align-items: flex-start;
                gap: 6px;
                margin-bottom: 1rem;
            }

            /* Tabs: scroll horizontal en móvil */
            .tabs {
                display: flex;
                flex-wrap: nowrap;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                gap: 3px;
                padding-bottom: 2px;
                border-bottom: 2px solid #ddd;
                scrollbar-width: none;
            }
            .tabs::-webkit-scrollbar { display: none; }
            .tab-btn {
                padding: 8px 14px;
                font-size: 0.82rem;
                white-space: nowrap;
                flex-shrink: 0;
            }

            /* Tab content: menos padding */
            .tab-content { padding: 16px 14px; border-radius: 0 0 6px 6px; }

            /* Sections */
            .section-block { padding: 14px 14px; margin-bottom: 16px; }
            .section-block h3 { font-size: 0.95rem; }

            /* Form rows: 1 columna en móvil */
            .form-row { grid-template-columns: 1fr; gap: 0; }

            /* Toolbar RTE: permitir wrap y botones más táctiles */
            .rte-toolbar {
                flex-wrap: wrap;
                gap: 4px;
                padding: 6px 6px;
            }
            .rte-btn {
                min-width: 34px;
                height: 32px;
                font-size: 0.88rem;
            }
            .rte-color-btn {
                width: 24px;
                height: 24px;
            }
            .rte-color-label { display: none; } /* ocultar "Color:" en mobile */
            .rte-sep { height: 20px; margin: 0 2px; }

            /* Inline RTE: permitir que la toolbar tenga 2 filas */
            .rte-inline-wrap .rte-toolbar { flex-wrap: wrap; gap: 3px; }
            .rte-inline-wrap .rte-btn { min-width: 30px; height: 28px; font-size: 0.82rem; }
            .rte-inline-wrap .rte-color-btn { width: 20px; height: 20px; }
            .rte-inline-wrap .rte-body {
                min-height: 40px !important;
                max-height: 60px;
                white-space: normal;
                overflow-y: auto;
            }

            /* Imagen preview */
            .img-preview-wrap { display: flex; flex-direction: column; align-items: flex-start; }
            .img-preview-name { max-width: 100%; }

            /* Grids de cards: 1 columna */
            .servicios-admin-grid,
            .perfiles-grid { grid-template-columns: 1fr; }

            /* Botones save */
            .btn-save, .btn-secondary {
                width: 100%;
                margin-left: 0;
                text-align: center;
            }

            /* Modal en mobile: full screen */
            .modal-overlay { padding: 0; align-items: flex-end; }
            .modal-box {
                max-width: 100%;
                border-radius: 16px 16px 0 0;
                max-height: 90vh;
                overflow-y: auto;
            }

            /* Galería de imágenes */
            .galeria-grid { grid-template-columns: repeat(2, 1fr) !important; }
        }

        @media (max-width: 480px) {
            body { padding: 8px 8px; }
            .tab-btn { padding: 7px 10px; font-size: 0.78rem; }
            .section-block { padding: 12px 10px; }
            .rte-btn { min-width: 30px; height: 30px; }
            .galeria-grid { grid-template-columns: repeat(2, 1fr) !important; }
        }
    </style>
</head>

<body>
    <div class="container">
        <header>
            <h1>Administrar Contenido</h1>
            <a href="logout.php" class="logout">Cerrar Sesión</a>
        </header>

        <?php if ($msg): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars(urldecode($msg)); ?></div>
        <?php endif; ?>
        <?php if ($err): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars(urldecode($err)); ?></div>
        <?php endif; ?>

        <div class="tabs">
            <button class="tab-btn <?php echo $activeTab==='home'?'active':''; ?>" onclick="openTab(event,'home')">Inicio</button>
            <button class="tab-btn <?php echo $activeTab==='quienes_somos'?'active':''; ?>" onclick="openTab(event,'quienes_somos')">Quiénes Somos</button>
            <button class="tab-btn <?php echo $activeTab==='equipo_medico'?'active':''; ?>" onclick="openTab(event,'equipo_medico')">Equipo Médico</button>
            <button class="tab-btn <?php echo $activeTab==='servicios'?'active':''; ?>" onclick="openTab(event,'servicios')">Servicios</button>
            <button class="tab-btn <?php echo $activeTab==='contacto'?'active':''; ?>" onclick="openTab(event,'contacto')">Contacto</button>
            <button class="tab-btn <?php echo $activeTab==='footer'?'active':''; ?>" onclick="openTab(event,'footer')">Footer</button>
            <button class="tab-btn <?php echo $activeTab==='seo'?'active':''; ?>" onclick="openTab(event,'seo')" style="background:#e8f5e9; color:#1b5e20; font-weight:600;">🔍 SEO</button>
            <button class="tab-btn <?php echo $activeTab==='integraciones'?'active':''; ?>" onclick="openTab(event,'integraciones')" style="background:#e8eaf6; color:#283593; font-weight:600;">⚡ Integraciones</button>
        </div>

        <!-- ═══════════════════════════════════════
             HOME TAB
        ════════════════════════════════════════ -->
        <div id="home" class="tab-content <?php echo $activeTab==='home'?'active':''; ?>">
            <form action="save_content.php" method="POST" enctype="multipart/form-data" accept-charset="UTF-8">
                <input type="hidden" name="page" value="home">
                <?= csrfField() ?>

                <!-- HERO -->
                <div class="section-block">
                    <h3>🎬 Hero Section</h3>
                    <div class="form-group">
                        <label>Tag / Etiqueta superior</label>
                        <?php echo rte_inline('hero_tag', $config['home']['hero_tag'] ?? ''); ?>
                        <p class="help-text">Texto pequeño sobre el título (ej: "Cenea Chile")</p>
                    </div>
                    <div class="form-group">
                        <label>Título Principal</label>
                        <?php echo rte_inline('hero_title', $config['home']['hero_title'] ?? ''); ?>
                        <p class="help-text">Tip: usa cursiva <em>C</em> para enfatizar palabras clave.</p>
                    </div>
                    <div class="form-group">
                        <label>Subtítulo / Descripción</label>
                        <?php echo rte('hero_subtitle', $config['home']['hero_subtitle'] ?? ''); ?>
                    </div>
                </div>

                <!-- AGENDA CTA -->
                <div class="section-block">
                    <h3>📅 Sección "Agenda tu hora"</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Título</label>
                            <?php echo rte_inline('agenda_title', $config['home']['agenda_title'] ?? ''); ?>
                        </div>
                        <div class="form-group">
                            <label>Subtítulo</label>
                            <?php echo rte_inline('agenda_subtitle', $config['home']['agenda_subtitle'] ?? ''); ?>
                        </div>
                    </div>
                </div>

                <!-- ABOUT / CENEA INTRO -->
                <div class="section-block">
                    <h3>🏥 Sección "Quiénes Somos" (Inicio)</h3>
                    <div class="form-group">
                        <label>Etiqueta (label pequeño)</label>
                        <?php echo rte_inline('about_label', $config['home']['about_label'] ?? ''); ?>
                    </div>
                    <div class="form-group">
                        <label>Título</label>
                        <?php echo rte_inline('about_title', $config['home']['about_title'] ?? ''); ?>
                        <p class="help-text">Tip: usa cursiva <em>C</em> para enfatizar palabras clave.</p>
                    </div>
                    <div class="form-group">
                        <label>Descripción 1</label>
                        <?php echo rte('about_desc_1', $config['home']['about_desc_1'] ?? ''); ?>
                    </div>
                    <div class="form-group">
                        <label>Descripción 2</label>
                        <?php echo rte('about_desc_2', $config['home']['about_desc_2'] ?? ''); ?>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Imagen 1</label>
                            <?php echo imgPreview($config,'home','about_img_1','Imagen 1 about'); ?>
                            <input type="file" name="about_img_1" accept=".jpg,.jpeg,.png,.webp">
                            <p class="img-note">⚠ Al subir una nueva imagen, la anterior se elimina automáticamente.</p>
                        </div>
                        <div class="form-group">
                            <label>Imagen 2</label>
                            <?php echo imgPreview($config,'home','about_img_2','Imagen 2 about'); ?>
                            <input type="file" name="about_img_2" accept=".jpg,.jpeg,.png,.webp">
                            <p class="img-note">⚠ Al subir una nueva imagen, la anterior se elimina automáticamente.</p>
                        </div>
                    </div>
                </div>

                <!-- ESPECIALIDADES -->
                <div class="section-block">
                    <h3>🧠 Sección "Especialidades"</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Etiqueta (label pequeño)</label>
                            <?php echo rte_inline('especialidades_label', $config['home']['especialidades_label'] ?? ''); ?>
                        </div>
                        <div class="form-group">
                            <label>Título</label>
                            <?php echo rte_inline('especialidades_title', $config['home']['especialidades_title'] ?? ''); ?>
                            <p class="help-text">Tip: usa cursiva <em>C</em> para enfatizar palabras clave.</p>
                        </div>
                    </div>
                </div>

                <!-- RESERVAR CTA -->
                <div class="section-block">
                    <h3>📋 Sección "Reserve su hora de atención"</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Badge / Etiqueta</label>
                            <?php echo rte_inline('reservar_badge', $config['home']['reservar_badge'] ?? ''); ?>
                        </div>
                        <div class="form-group">
                            <label>Título principal</label>
                            <?php echo rte_inline('reservar_title', $config['home']['reservar_title'] ?? ''); ?>
                        </div>
                    </div>
                    <h4>Tarjeta: Agendar por Doctor</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Título tarjeta</label>
                            <?php echo rte_inline('reservar_doctor_title', $config['home']['reservar_doctor_title'] ?? ''); ?>
                        </div>
                        <div class="form-group">
                            <label>Descripción tarjeta</label>
                            <?php echo rte_inline('reservar_doctor_desc', $config['home']['reservar_doctor_desc'] ?? ''); ?>
                        </div>
                    </div>
                    <h4>Tarjeta: Agendar por Especialidad</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Título tarjeta</label>
                            <?php echo rte_inline('reservar_esp_title', $config['home']['reservar_esp_title'] ?? ''); ?>
                        </div>
                        <div class="form-group">
                            <label>Descripción tarjeta</label>
                            <?php echo rte_inline('reservar_esp_desc', $config['home']['reservar_esp_desc'] ?? ''); ?>
                        </div>
                    </div>
                </div>

                <!-- PROFESIONALES -->
                <div class="section-block">
                    <h3>👨‍⚕️ Sección "Profesionales Especialistas"</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Etiqueta (label pequeño)</label>
                            <?php echo rte_inline('profesionales_label', $config['home']['profesionales_label'] ?? ''); ?>
                        </div>
                        <div class="form-group">
                            <label>Título sección</label>
                            <?php echo rte_inline('profesionales_title', $config['home']['profesionales_title'] ?? ''); ?>
                            <p class="help-text">Tip: usa cursiva <em>C</em> para enfatizar palabras clave.</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Subtítulo sección</label>
                        <?php echo rte('profesionales_subtitle', $config['home']['profesionales_subtitle'] ?? '', '', 60); ?>
                    </div>
                    <div class="form-group">
                        <label>Imagen principal (tarjeta grande con foto)</label>
                        <?php echo imgPreview($config,'home','profesionales_img','Imagen profesionales'); ?>
                        <input type="file" name="profesionales_img" accept=".jpg,.jpeg,.png,.webp">
                        <p class="img-note">⚠ Al subir una nueva imagen, la anterior se elimina automáticamente.</p>
                    </div>
                    <h4>Tarjeta 1 — Equipo Multidisciplinario</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Título</label>
                            <?php echo rte_inline('profesionales_card1_title', $config['home']['profesionales_card1_title'] ?? ''); ?>
                        </div>
                        <div class="form-group">
                            <label>Descripción</label>
                            <?php echo rte_inline('profesionales_card1_desc', $config['home']['profesionales_card1_desc'] ?? ''); ?>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Número destacado (ej: 30+)</label>
                            <input type="text" name="profesionales_card1_numero" value="<?php echo val($config,'home','profesionales_card1_numero'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Etiqueta del número (ej: Especialistas)</label>
                            <input type="text" name="profesionales_card1_label" value="<?php echo val($config,'home','profesionales_card1_label'); ?>">
                        </div>
                    </div>
                    <h4>Tarjeta 2 — Especialidades Afines</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Título</label>
                            <?php echo rte_inline('profesionales_card2_title', $config['home']['profesionales_card2_title'] ?? ''); ?>
                        </div>
                        <div class="form-group">
                            <label>Descripción</label>
                            <?php echo rte_inline('profesionales_card2_desc', $config['home']['profesionales_card2_desc'] ?? ''); ?>
                        </div>
                    </div>
                    <h4>Tarjeta 3 — Rehabilitación Integral</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Título</label>
                            <?php echo rte_inline('profesionales_card3_title', $config['home']['profesionales_card3_title'] ?? ''); ?>
                        </div>
                        <div class="form-group">
                            <label>Descripción</label>
                            <?php echo rte_inline('profesionales_card3_desc', $config['home']['profesionales_card3_desc'] ?? ''); ?>
                        </div>
                    </div>
                </div>

                <!-- UBICACIÓN -->
                <div class="section-block">
                    <h3>📍 Sección "Ubicación"</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Etiqueta (label pequeño)</label>
                            <?php echo rte_inline('ubicacion_label', $config['home']['ubicacion_label'] ?? ''); ?>
                        </div>
                        <div class="form-group">
                            <label>Título</label>
                            <?php echo rte_inline('ubicacion_title', $config['home']['ubicacion_title'] ?? ''); ?>
                            <p class="help-text">Tip: usa cursiva <em>C</em> para enfatizar palabras clave.</p>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Dirección</label>
                            <?php echo rte('ubicacion_direccion', $config['home']['ubicacion_direccion'] ?? '', '', 60); ?>
                        </div>
                        <div class="form-group">
                            <label>Horario</label>
                            <?php echo rte_inline('ubicacion_horario', $config['home']['ubicacion_horario'] ?? ''); ?>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-save">💾 Guardar Cambios Inicio</button>
            </form>
        </div>

        <!-- ═══════════════════════════════════════
             QUIENES SOMOS TAB
        ════════════════════════════════════════ -->
        <div id="quienes_somos" class="tab-content <?php echo $activeTab==='quienes_somos'?'active':''; ?>">
            <form action="save_content.php" method="POST" enctype="multipart/form-data" accept-charset="UTF-8">
                <input type="hidden" name="page" value="quienes_somos">
                <?= csrfField() ?>

                <!-- CABECERA -->
                <div class="section-block">
                    <h3>🏷 Cabecera de Página</h3>
                    <div class="form-group">
                        <label>Etiqueta (label pequeño)</label>
                        <?php echo rte_inline('header_label', $config['quienes_somos']['header_label'] ?? ''); ?>
                    </div>
                    <div class="form-group">
                        <label>Título</label>
                        <?php echo rte_inline('header_title', $config['quienes_somos']['header_title'] ?? ''); ?>
                        <p class="help-text">Tip: usa cursiva <em>C</em> para enfatizar palabras clave.</p>
                    </div>
                    <div class="form-group">
                        <label>Bajada / Subtítulo</label>
                        <?php echo rte('header_subtitle', $config['quienes_somos']['header_subtitle'] ?? ''); ?>
                    </div>
                </div>

                <!-- INTRODUCCIÓN -->
                <div class="section-block">
                    <h3>📖 Sección Introducción</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Etiqueta (label pequeño)</label>
                            <?php echo rte_inline('intro_label', $config['quienes_somos']['intro_label'] ?? ''); ?>
                        </div>
                        <div class="form-group">
                            <label>Título</label>
                            <?php echo rte_inline('intro_title', $config['quienes_somos']['intro_title'] ?? ''); ?>
                            <p class="help-text">Tip: usa cursiva <em>C</em> para enfatizar palabras clave.</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Descripción 1</label>
                        <?php echo rte('intro_desc_1', $config['quienes_somos']['intro_desc_1'] ?? ''); ?>
                    </div>
                    <div class="form-group">
                        <label>Descripción 2</label>
                        <?php echo rte('intro_desc_2', $config['quienes_somos']['intro_desc_2'] ?? ''); ?>
                    </div>
                    <div class="form-group">
                        <label>Imagen</label>
                        <?php echo imgPreview($config,'quienes_somos','intro_img','Imagen intro'); ?>
                        <input type="file" name="intro_img" accept=".jpg,.jpeg,.png,.webp">
                        <p class="img-note">⚠ Al subir una nueva imagen, la anterior se elimina automáticamente.</p>
                    </div>
                </div>

                <!-- SOCIOS FUNDADORES -->
                <div class="section-block">
                    <h3>🤝 Socios Fundadores</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Etiqueta (label pequeño)</label>
                            <?php echo rte_inline('fundadores_label', $config['quienes_somos']['fundadores_label'] ?? ''); ?>
                        </div>
                        <div class="form-group">
                            <label>Título</label>
                            <?php echo rte_inline('fundadores_title', $config['quienes_somos']['fundadores_title'] ?? ''); ?>
                            <p class="help-text">Tip: usa cursiva <em>C</em> para enfatizar palabras clave.</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Descripción 1</label>
                        <?php echo rte('fundadores_desc_1', $config['quienes_somos']['fundadores_desc_1'] ?? ''); ?>
                    </div>
                    <div class="form-group">
                        <label>Descripción 2</label>
                        <?php echo rte('fundadores_desc_2', $config['quienes_somos']['fundadores_desc_2'] ?? ''); ?>
                    </div>
                    <div class="form-group">
                        <label>Imagen Fundadores</label>
                        <?php echo imgPreview($config,'quienes_somos','fundadores_img','Imagen fundadores'); ?>
                        <input type="file" name="fundadores_img" accept=".jpg,.jpeg,.png,.webp">
                        <p class="img-note">⚠ Al subir una nueva imagen, la anterior se elimina automáticamente.</p>
                    </div>
                </div>

                <!-- VALORES -->
                <div class="section-block">
                    <h3>💎 Sección "Nuestros Valores"</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Etiqueta (label pequeño)</label>
                            <?php echo rte_inline('valores_label', $config['quienes_somos']['valores_label'] ?? ''); ?>
                        </div>
                        <div class="form-group">
                            <label>Título sección</label>
                            <?php echo rte_inline('valores_title', $config['quienes_somos']['valores_title'] ?? ''); ?>
                            <p class="help-text">Tip: usa cursiva <em>C</em> para enfatizar palabras clave.</p>
                        </div>
                    </div>
                    <h4>Valor 1 — Atención Personalizada</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Título</label>
                            <?php echo rte_inline('valor1_title', $config['quienes_somos']['valor1_title'] ?? ''); ?>
                        </div>
                        <div class="form-group">
                            <label>Descripción</label>
                            <?php echo rte_inline('valor1_desc', $config['quienes_somos']['valor1_desc'] ?? ''); ?>
                        </div>
                    </div>
                    <h4>Valor 2 — Excelencia Médica</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Título</label>
                            <?php echo rte_inline('valor2_title', $config['quienes_somos']['valor2_title'] ?? ''); ?>
                        </div>
                        <div class="form-group">
                            <label>Descripción</label>
                            <?php echo rte_inline('valor2_desc', $config['quienes_somos']['valor2_desc'] ?? ''); ?>
                        </div>
                    </div>
                    <h4>Valor 3 — Equipo Multidisciplinario</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Título</label>
                            <?php echo rte_inline('valor3_title', $config['quienes_somos']['valor3_title'] ?? ''); ?>
                        </div>
                        <div class="form-group">
                            <label>Descripción</label>
                            <?php echo rte_inline('valor3_desc', $config['quienes_somos']['valor3_desc'] ?? ''); ?>
                        </div>
                    </div>
                    <h4>Valor 4 — Acompañamiento Integral</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Título</label>
                            <?php echo rte_inline('valor4_title', $config['quienes_somos']['valor4_title'] ?? ''); ?>
                        </div>
                        <div class="form-group">
                            <label>Descripción</label>
                            <?php echo rte_inline('valor4_desc', $config['quienes_somos']['valor4_desc'] ?? ''); ?>
                        </div>
                    </div>
                </div>

                <!-- INSTALACIONES: textos -->
                <div class="section-block">
                    <h3>🏛 Sección "Nuestras Instalaciones" — Textos</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Etiqueta (label pequeño)</label>
                            <?php echo rte_inline('instalaciones_label', $config['quienes_somos']['instalaciones_label'] ?? ''); ?>
                        </div>
                        <div class="form-group">
                            <label>Título</label>
                            <?php echo rte_inline('instalaciones_title', $config['quienes_somos']['instalaciones_title'] ?? ''); ?>
                            <p class="help-text">Tip: usa cursiva <em>C</em> para enfatizar palabras clave.</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Subtítulo / Descripción</label>
                        <?php echo rte('instalaciones_subtitle', $config['quienes_somos']['instalaciones_subtitle'] ?? '', '', 60); ?>
                    </div>
                </div>

                <!-- CTA EQUIPO -->
                <div class="section-block">
                    <h3>🔗 Sección CTA "Equipo Médico"</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Etiqueta (label pequeño)</label>
                            <?php echo rte_inline('cta_label', $config['quienes_somos']['cta_label'] ?? ''); ?>
                        </div>
                        <div class="form-group">
                            <label>Título</label>
                            <?php echo rte_inline('cta_title', $config['quienes_somos']['cta_title'] ?? ''); ?>
                            <p class="help-text">Tip: usa cursiva <em>C</em> para enfatizar palabras clave.</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Descripción</label>
                        <?php echo rte('cta_desc', $config['quienes_somos']['cta_desc'] ?? '', '', 60); ?>
                    </div>
                </div>

                <button type="submit" class="btn-save">💾 Guardar Textos Quiénes Somos</button>
            </form>

            <!-- ─── GALERÍA DE INSTALACIONES (formulario separado) ─── -->
            <?php
            $galeriaFile = '../data/galeria.json';
            $galeria = file_exists($galeriaFile) ? json_decode(file_get_contents($galeriaFile), true) : ['instalaciones' => []];
            $imagenes = $galeria['instalaciones'] ?? [];
            ?>
            <div class="section-block" style="margin-top: 24px;">
                <h3>🖼 Galería de Instalaciones
                    <span style="font-size:0.8rem; font-weight:400; color:#666; margin-left:8px;"><?php echo count($imagenes); ?> imagen(es)</span>
                </h3>

                <!-- Subir nueva imagen -->
                <form action="save_galeria.php" method="POST" enctype="multipart/form-data" accept-charset="UTF-8" style="margin-bottom:24px;">
                    <?= csrfField() ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nueva imagen</label>
                            <input type="file" name="imagen" accept=".jpg,.jpeg,.png,.webp" required>
                            <p class="help-text">Formatos: JPG, PNG, WEBP. Se agregará al final de la galería.</p>
                        </div>
                        <div class="form-group">
                            <label>Texto alternativo (descripción)</label>
                            <input type="text" name="alt" placeholder="Ej: Sala de espera Cenea">
                        </div>
                    </div>
                    <button type="submit" class="btn-save">📤 Subir imagen a la galería</button>
                </form>

                <!-- Grid de imágenes actuales -->
                <?php if (empty($imagenes)): ?>
                    <p style="color:#888; font-style:italic;">No hay imágenes en la galería. Sube la primera.</p>
                <?php else: ?>
                <div class="galeria-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px;">
                    <?php foreach ($imagenes as $img): ?>
                        <div style="position:relative; border-radius:8px; overflow:hidden; border:1px solid #e0e0e0; background:#f5f5f5;">
                            <img src="../<?php echo htmlspecialchars($img['src']); ?>"
                                 alt="<?php echo htmlspecialchars($img['alt']); ?>"
                                 style="width:100%; height:130px; object-fit:cover; display:block;">
                            <div style="padding:8px 10px;">
                                <p style="margin:0; font-size:0.78rem; color:#555; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"
                                   title="<?php echo htmlspecialchars($img['alt']); ?>">
                                    <?php echo htmlspecialchars($img['alt']); ?>
                                </p>
                            </div>
                            <div style="padding:0 10px 10px; display:flex; justify-content:flex-end;">
                                <a href="delete_galeria.php?id=<?php echo urlencode($img['id']); ?>"
                                   onclick="return confirm('¿Eliminar esta imagen de la galería? Esta acción no se puede deshacer.')"
                                   style="background:#d32f2f; color:white; border:none; padding:4px 10px; border-radius:4px; font-size:0.78rem; text-decoration:none; cursor:pointer;">
                                    🗑 Eliminar
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ═══════════════════════════════════════
             SERVICIOS TAB
        ════════════════════════════════════════ -->
        <div id="servicios" class="tab-content <?php echo $activeTab==='servicios'?'active':''; ?>">

            <!-- TEXTOS ESTÁTICOS DE LA PÁGINA -->
            <form action="save_content.php" method="POST" enctype="multipart/form-data" accept-charset="UTF-8">
                <input type="hidden" name="page" value="servicios">
                <?= csrfField() ?>

                <!-- CABECERA -->
                <div class="section-block">
                    <h3>🏷 Cabecera de Página</h3>
                    <div class="form-group">
                        <label>Etiqueta (label pequeño)</label>
                        <?php echo rte_inline('header_label', $config['servicios']['header_label'] ?? ''); ?>
                    </div>
                    <div class="form-group">
                        <label>Título</label>
                        <?php echo rte_inline('header_title', $config['servicios']['header_title'] ?? ''); ?>
                        <p class="help-text">Tip: usa cursiva <em>C</em> para enfatizar palabras clave.</p>
                    </div>
                    <div class="form-group">
                        <label>Bajada / Subtítulo</label>
                        <?php echo rte('header_subtitle', $config['servicios']['header_subtitle'] ?? ''); ?>
                    </div>
                    <div class="form-group">
                        <label>Texto de ayuda (enlace a orientación)</label>
                        <input type="text" name="header_help" value="<?php echo val($config,'servicios','header_help'); ?>">
                    </div>
                </div>

                <!-- PASOS / CÓMO AGENDAR -->
                <div class="section-block">
                    <h3>📋 Sección "¿Cómo Agendar?" — 4 Pasos</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Etiqueta (label pequeño)</label>
                            <?php echo rte_inline('pasos_label', $config['servicios']['pasos_label'] ?? ''); ?>
                        </div>
                        <div class="form-group">
                            <label>Título sección</label>
                            <?php echo rte_inline('pasos_title', $config['servicios']['pasos_title'] ?? ''); ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Subtítulo</label>
                        <?php echo rte_inline('pasos_subtitle', $config['servicios']['pasos_subtitle'] ?? ''); ?>
                    </div>

                    <h4>Paso 1</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Número</label>
                            <input type="text" name="paso1_num" value="<?php echo val($config,'servicios','paso1_num'); ?>" style="max-width:80px;">
                        </div>
                        <div class="form-group">
                            <label>Título del paso</label>
                            <?php echo rte_inline('paso1_title', $config['servicios']['paso1_title'] ?? ''); ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Descripción</label>
                        <?php echo rte('paso1_desc', $config['servicios']['paso1_desc'] ?? '', '', 70); ?>
                    </div>

                    <h4>Paso 2</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Número</label>
                            <input type="text" name="paso2_num" value="<?php echo val($config,'servicios','paso2_num'); ?>" style="max-width:80px;">
                        </div>
                        <div class="form-group">
                            <label>Título del paso</label>
                            <?php echo rte_inline('paso2_title', $config['servicios']['paso2_title'] ?? ''); ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Descripción</label>
                        <?php echo rte('paso2_desc', $config['servicios']['paso2_desc'] ?? '', '', 70); ?>
                    </div>

                    <h4>Paso 3</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Número</label>
                            <input type="text" name="paso3_num" value="<?php echo val($config,'servicios','paso3_num'); ?>" style="max-width:80px;">
                        </div>
                        <div class="form-group">
                            <label>Título del paso</label>
                            <?php echo rte_inline('paso3_title', $config['servicios']['paso3_title'] ?? ''); ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Descripción</label>
                        <?php echo rte('paso3_desc', $config['servicios']['paso3_desc'] ?? '', '', 70); ?>
                    </div>

                    <h4>Paso 4</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Número</label>
                            <input type="text" name="paso4_num" value="<?php echo val($config,'servicios','paso4_num'); ?>" style="max-width:80px;">
                        </div>
                        <div class="form-group">
                            <label>Título del paso</label>
                            <?php echo rte_inline('paso4_title', $config['servicios']['paso4_title'] ?? ''); ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Descripción</label>
                        <?php echo rte('paso4_desc', $config['servicios']['paso4_desc'] ?? '', '', 70); ?>
                    </div>
                </div>

                <!-- ORIENTACIÓN CTA -->
                <div class="section-block">
                    <h3>💬 Sección "Orientación" (CTA WhatsApp / Teléfono)</h3>
                    <div class="form-group">
                        <label>Título</label>
                        <?php echo rte_inline('orientacion_title', $config['servicios']['orientacion_title'] ?? ''); ?>
                    </div>
                    <div class="form-group">
                        <label>Descripción</label>
                        <?php echo rte('orientacion_desc', $config['servicios']['orientacion_desc'] ?? '', '', 70); ?>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Texto botón WhatsApp</label>
                            <?php echo rte_inline('orientacion_btn_wsp', $config['servicios']['orientacion_btn_wsp'] ?? ''); ?>
                        </div>
                        <div class="form-group">
                            <label>Texto botón Teléfono</label>
                            <?php echo rte_inline('orientacion_btn_tel', $config['servicios']['orientacion_btn_tel'] ?? ''); ?>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-save">💾 Guardar Textos Servicios</button>
            </form>

            <!-- ─── TARJETAS DE SERVICIOS (CRUD) ─── -->
            <?php
            $serviciosFile = '../data/servicios.json';
            $servicios = file_exists($serviciosFile) ? json_decode(file_get_contents($serviciosFile), true) : [];
            usort($servicios, function($a, $b) { return ($a['orden'] ?? 0) - ($b['orden'] ?? 0); });
            ?>

            <!-- Formulario añadir / editar servicio -->
            <div class="section-block" style="margin-top: 24px;">
                <h3 id="servicio-form-title">➕ Añadir Nuevo Servicio</h3>
                <form action="save_servicio.php" method="POST" accept-charset="UTF-8" id="servicio-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="" id="servicio-id">
                    <input type="hidden" name="icono" id="servicio-icono" value="activity">
                    <div class="form-group">
                        <label>Título del servicio <span style="color:#d32f2f;">*</span></label>
                        <?php echo rte_inline('titulo', '', 'servicio-titulo'); ?>
                    </div>
                    <div class="form-group">
                        <label>Descripción</label>
                        <?php echo rte('descripcion', '', 'servicio-desc', 90); ?>
                    </div>

                    <!-- Selector de Icono -->
                    <div class="form-group">
                        <label>Icono del servicio</label>
                        <p class="help-text" style="margin-bottom:10px;">Haz clic en el icono que mejor represente este servicio.</p>
                        <div id="icon-picker" style="display:flex; flex-wrap:wrap; gap:8px;">
                            <?php
                            $iconOptions = [
                                'activity'   => 'M22 12 18 12 15 21 9 3 6 12 2 12',
                                'smile'      => 'circle|smile',
                                'zap'        => 'polygon:13 2 3 14 12 14 11 22 21 10 12 10 13 2',
                                'clock'      => 'circle|clock',
                                'bell'       => 'bell',
                                'refresh-cw' => 'refresh',
                                'heart'      => 'heart',
                                'sun'        => 'sun',
                                'users'      => 'users',
                                'box'        => 'box',
                                'book-open'  => 'book',
                                'moon'       => 'moon',
                                'shield'     => 'shield',
                                'clipboard'  => 'clipboard',
                                'tool'       => 'tool',
                                'monitor'    => 'monitor',
                                'brain'      => 'brain',
                                'eye'        => 'eye',
                                'pill'       => 'pill',
                                'wifi'       => 'wifi',
                                'microscope' => 'microscope',
                                'stethoscope'=> 'stethoscope',
                            ];
                            // SVG paths compactos para el picker (admin)
                            $svgPaths = [
                                'activity'    => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
                                'smile'       => '<circle cx="12" cy="12" r="10"/><path d="M8 13s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/>',
                                'zap'         => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
                                'clock'       => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
                                'bell'        => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
                                'refresh-cw'  => '<polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>',
                                'heart'       => '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>',
                                'sun'         => '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/>',
                                'users'       => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
                                'box'         => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
                                'book-open'   => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
                                'moon'        => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
                                'shield'      => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
                                'clipboard'   => '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>',
                                'tool'        => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
                                'monitor'     => '<rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>',
                                'brain'       => '<path d="M9.5 2A2.5 2.5 0 0 1 12 4.5v15a2.5 2.5 0 0 1-4.96-.46 2.5 2.5 0 0 1-2.96-3.08 3 3 0 0 1-.34-5.58 2.5 2.5 0 0 1 1.32-4.24 2.5 2.5 0 0 1 1.44-4.14z"/><path d="M14.5 2A2.5 2.5 0 0 0 12 4.5v15a2.5 2.5 0 0 0 4.96-.46 2.5 2.5 0 0 0 2.96-3.08 3 3 0 0 0 .34-5.58 2.5 2.5 0 0 0-1.32-4.24 2.5 2.5 0 0 0-1.44-4.14z"/>',
                                'eye'         => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
                                'pill'        => '<path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7z"/><line x1="8.5" y1="8.5" x2="15.5" y2="15.5"/>',
                                'wifi'        => '<path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/>',
                                'microscope'  => '<path d="M6 18h8"/><path d="M3 22h18"/><path d="M14 22a7 7 0 1 0 0-14h-1"/><path d="M9 14h2"/><path d="M9 12a2 2 0 0 1-2-2V6h6v4a2 2 0 0 1-2 2z"/><path d="M12 6V3a1 1 0 0 0-1-1H9a1 1 0 0 0-1 1v3"/>',
                                'stethoscope' => '<path d="M4.8 2.3A.3.3 0 1 0 5 2H4a2 2 0 0 0-2 2v5a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6V4a2 2 0 0 0-2-2h-1a.2.2 0 1 0 .3.3"/><path d="M8 15v1a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6v-4"/><circle cx="20" cy="10" r="2"/>',
                            ];
                            foreach (array_keys($svgPaths) as $key):
                            ?>
                                <button type="button"
                                    data-icon="<?php echo $key; ?>"
                                    onclick="selectIcon('<?php echo $key; ?>')"
                                    title="<?php echo $key; ?>"
                                    style="width:48px; height:48px; border:2px solid #ddd; border-radius:8px; background:#f9f9f9; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all 0.15s; padding:8px;"
                                    class="icon-btn">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="22" height="22">
                                        <?php echo $svgPaths[$key]; ?>
                                    </svg>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <p style="margin-top:8px; font-size:0.82em; color:#555;">Icono seleccionado: <strong id="icon-label">activity</strong></p>
                    </div>

                    <div class="form-group">
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                            <input type="checkbox" name="destacado" id="servicio-destacado" style="width:auto; accent-color:#004d40;">
                            <span>Tarjeta destacada <span style="color:#666; font-weight:400;">(aparecerá con borde verde, ej: Telemedicina)</span></span>
                        </label>
                    </div>
                    <button type="submit" class="btn-save">💾 Guardar Servicio</button>
                    <button type="button" class="btn-secondary" onclick="cancelEditServicio()">✕ Limpiar / Nuevo</button>
                </form>
            </div>

            <!-- Grid servicios existentes -->
            <div class="section-block">
                <h3>🗂 Servicios Existentes
                    <span style="font-size:0.8rem; font-weight:400; color:#666; margin-left:8px;"><?php echo count($servicios); ?> servicio(s)</span>
                </h3>
                <?php if (empty($servicios)): ?>
                    <p style="color:#888; font-style:italic;">No hay servicios. Agrega el primero con el formulario de arriba.</p>
                <?php else: ?>
                <div class="servicios-admin-grid">
                    <?php foreach ($servicios as $sv): ?>
                        <div class="servicio-admin-card<?php echo $sv['destacado'] ? ' servicio-admin-card--highlight' : ''; ?>">
                            <?php if ($sv['destacado']): ?>
                                <span class="servicio-admin-card__badge">⭐ Destacado</span>
                            <?php endif; ?>
                            <div style="font-size:0.75em; color:#777; margin-bottom:4px; font-family:monospace;">🎨 <?php echo htmlspecialchars($sv['icono'] ?? 'activity'); ?></div>
                            <div class="servicio-admin-card__titulo"><?php echo htmlspecialchars($sv['titulo']); ?></div>
                            <div class="servicio-admin-card__desc"><?php echo htmlspecialchars($sv['descripcion']); ?></div>
                            <div class="servicio-admin-card__actions">
                                <button type="button" class="btn-edit"
                                    onclick="editServicio('<?php echo htmlspecialchars($sv['id']); ?>','<?php echo htmlspecialchars(addslashes($sv['titulo'])); ?>','<?php echo htmlspecialchars(addslashes($sv['descripcion'])); ?>',<?php echo $sv['destacado'] ? 'true' : 'false'; ?>,'<?php echo htmlspecialchars($sv['icono'] ?? 'activity'); ?>')">
                                    Editar
                                </button>
                                <a href="delete_servicio.php?id=<?php echo urlencode($sv['id']); ?>"
                                   onclick="return confirm('¿Eliminar el servicio &quot;<?php echo htmlspecialchars(addslashes($sv['titulo'])); ?>&quot;? Esta acción no se puede deshacer.')"
                                   class="btn-delete">Eliminar</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- ═══════════════════════════════════════
             CONTACTO TAB
        ════════════════════════════════════════ -->
        <div id="contacto" class="tab-content <?php echo $activeTab==='contacto'?'active':''; ?>">
            <form action="save_content.php" method="POST" enctype="multipart/form-data" accept-charset="UTF-8">
                <input type="hidden" name="page" value="contacto">
                <?= csrfField() ?>

                <!-- CABECERA -->
                <div class="section-block">
                    <h3>🏷 Cabecera de Página</h3>
                    <div class="form-group">
                        <label>Etiqueta (label pequeño)</label>
                        <?php echo rte_inline('header_label', $config['contacto']['header_label'] ?? ''); ?>
                    </div>
                    <div class="form-group">
                        <label>Título</label>
                        <?php echo rte_inline('header_title', $config['contacto']['header_title'] ?? ''); ?>
                    </div>
                    <div class="form-group">
                        <label>Bajada / Subtítulo</label>
                        <?php echo rte('header_subtitle', $config['contacto']['header_subtitle'] ?? ''); ?>
                    </div>
                </div>

                <!-- COLUMNA INFO -->
                <div class="section-block">
                    <h3>ℹ️ Sección Información</h3>
                    <div class="form-group">
                        <label>Título</label>
                        <?php echo rte_inline('info_title', $config['contacto']['info_title'] ?? ''); ?>
                        <p class="help-text">Tip: usa cursiva <em>C</em> para enfatizar palabras clave.</p>
                    </div>
                    <div class="form-group">
                        <label>Descripción</label>
                        <?php echo rte('info_desc', $config['contacto']['info_desc'] ?? ''); ?>
                    </div>
                </div>

                <!-- DATOS DE CONTACTO -->
                <div class="section-block">
                    <h3>📞 Datos de Contacto</h3>

                    <h4>Teléfono</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Etiqueta</label>
                            <input type="text" name="dato_tel_label" value="<?php echo val($config,'contacto','dato_tel_label'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Número visible</label>
                            <input type="text" name="dato_tel_numero" value="<?php echo val($config,'contacto','dato_tel_numero'); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Enlace tel: (href)</label>
                        <input type="text" name="dato_tel_href" value="<?php echo val($config,'contacto','dato_tel_href'); ?>" placeholder="tel:+56912345678">
                        <p class="help-text">Formato: tel:+56912345678</p>
                    </div>

                    <h4>WhatsApp</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Etiqueta</label>
                            <input type="text" name="dato_wsp_label" value="<?php echo val($config,'contacto','dato_wsp_label'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Número visible</label>
                            <input type="text" name="dato_wsp_numero" value="<?php echo val($config,'contacto','dato_wsp_numero'); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Enlace WhatsApp (href)</label>
                        <input type="text" name="dato_wsp_href" value="<?php echo val($config,'contacto','dato_wsp_href'); ?>" placeholder="https://wa.me/56912345678">
                    </div>

                    <h4>Email</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Etiqueta</label>
                            <input type="text" name="dato_email_label" value="<?php echo val($config,'contacto','dato_email_label'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Email 1</label>
                            <input type="text" name="dato_email_1" value="<?php echo val($config,'contacto','dato_email_1'); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Email 2</label>
                        <input type="text" name="dato_email_2" value="<?php echo val($config,'contacto','dato_email_2'); ?>">
                    </div>

                    <h4>Dirección</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Etiqueta</label>
                            <input type="text" name="dato_dir_label" value="<?php echo val($config,'contacto','dato_dir_label'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Texto dirección</label>
                            <input type="text" name="dato_dir_texto" value="<?php echo val($config,'contacto','dato_dir_texto'); ?>">
                        </div>
                    </div>

                    <h4>Horario</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Etiqueta</label>
                            <input type="text" name="dato_horario_label" value="<?php echo val($config,'contacto','dato_horario_label'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Texto horario</label>
                            <input type="text" name="dato_horario_texto" value="<?php echo val($config,'contacto','dato_horario_texto'); ?>">
                        </div>
                    </div>

                    <h4>Estacionamiento</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Etiqueta</label>
                            <input type="text" name="dato_estac_label" value="<?php echo val($config,'contacto','dato_estac_label'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Texto</label>
                            <input type="text" name="dato_estac_texto" value="<?php echo val($config,'contacto','dato_estac_texto'); ?>">
                        </div>
                    </div>
                </div>

                <!-- CARD CTA -->
                <div class="section-block">
                    <h3>🟢 Card "Reserva tu hora"</h3>
                    <div class="form-group">
                        <label>Título</label>
                        <?php echo rte_inline('cta_card_title', $config['contacto']['cta_card_title'] ?? ''); ?>
                    </div>
                    <div class="form-group">
                        <label>Descripción</label>
                        <?php echo rte('cta_card_desc', $config['contacto']['cta_card_desc'] ?? '', '', 70); ?>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Texto Botón 1 (Doctor)</label>
                            <input type="text" name="cta_card_btn1" value="<?php echo val($config,'contacto','cta_card_btn1'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Texto Botón 2 (Especialidad)</label>
                            <input type="text" name="cta_card_btn2" value="<?php echo val($config,'contacto','cta_card_btn2'); ?>">
                        </div>
                    </div>
                </div>

                <!-- CARD INFO CENEA -->
                <div class="section-block">
                    <h3>🏥 Card Info Cenea</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Teléfono visible</label>
                            <input type="text" name="info_card_tel" value="<?php echo val($config,'contacto','info_card_tel'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Enlace tel: (href)</label>
                            <input type="text" name="info_card_tel_href" value="<?php echo val($config,'contacto','info_card_tel_href'); ?>" placeholder="tel:+56912345678">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="text" name="info_card_email" value="<?php echo val($config,'contacto','info_card_email'); ?>">
                    </div>
                </div>

                <button type="submit" class="btn-save">💾 Guardar Cambios Contacto</button>
            </form>
        </div>

        <!-- ═══════════════════════════════════════
             FOOTER TAB
        ════════════════════════════════════════ -->
        <div id="footer" class="tab-content <?php echo $activeTab==='footer'?'active':''; ?>">
            <form action="save_content.php" method="POST" enctype="multipart/form-data" accept-charset="UTF-8">
                <input type="hidden" name="page" value="footer">
                <?= csrfField() ?>

                <!-- BARRA SUPERIOR -->
                <div class="section-block">
                    <h3>📢 Barra Superior del Footer</h3>
                    <div class="form-group">
                        <label>Texto CTA ("Agenda tu hora…")</label>
                        <input type="text" name="top_cta_text" value="<?php echo val($config,'footer','top_cta_text'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Texto del Botón CTA</label>
                        <input type="text" name="top_cta_btn" value="<?php echo val($config,'footer','top_cta_btn'); ?>">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Teléfono visible</label>
                            <input type="text" name="top_tel" value="<?php echo val($config,'footer','top_tel'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Enlace tel: (href)</label>
                            <input type="text" name="top_tel_href" value="<?php echo val($config,'footer','top_tel_href'); ?>" placeholder="tel:+56912345678">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Email visible</label>
                            <input type="text" name="top_email" value="<?php echo val($config,'footer','top_email'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Texto enlace ubicación</label>
                            <input type="text" name="top_ubicacion_label" value="<?php echo val($config,'footer','top_ubicacion_label'); ?>">
                        </div>
                    </div>
                </div>

                <!-- COLUMNA MARCA -->
                <div class="section-block">
                    <h3>🏥 Columna Marca</h3>
                    <div class="form-group">
                        <label>Descripción de la marca</label>
                        <?php echo rte('brand_desc', $config['footer']['brand_desc'] ?? '', '', 80); ?>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Enlace Facebook</label>
                            <input type="text" name="social_facebook" value="<?php echo val($config,'footer','social_facebook'); ?>" placeholder="https://facebook.com/cenea">
                        </div>
                        <div class="form-group">
                            <label>Enlace Instagram</label>
                            <input type="text" name="social_instagram" value="<?php echo val($config,'footer','social_instagram'); ?>" placeholder="https://instagram.com/cenea">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Enlace LinkedIn</label>
                        <input type="text" name="social_linkedin" value="<?php echo val($config,'footer','social_linkedin'); ?>" placeholder="https://linkedin.com/company/cenea">
                    </div>
                </div>

                <!-- COLUMNA ESPECIALIDADES -->
                <div class="section-block">
                    <h3>🔗 Columna Especialidades (Links)</h3>
                    <p class="help-text">Estos son los 6 ítems que aparecen en la columna "Especialidades" del footer.</p>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Especialidad 1</label>
                            <input type="text" name="esp_1" value="<?php echo val($config,'footer','esp_1'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Especialidad 2</label>
                            <input type="text" name="esp_2" value="<?php echo val($config,'footer','esp_2'); ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Especialidad 3</label>
                            <input type="text" name="esp_3" value="<?php echo val($config,'footer','esp_3'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Especialidad 4</label>
                            <input type="text" name="esp_4" value="<?php echo val($config,'footer','esp_4'); ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Especialidad 5</label>
                            <input type="text" name="esp_5" value="<?php echo val($config,'footer','esp_5'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Especialidad 6</label>
                            <input type="text" name="esp_6" value="<?php echo val($config,'footer','esp_6'); ?>">
                        </div>
                    </div>
                </div>

                <!-- COLUMNA CONTACTO -->
                <div class="section-block">
                    <h3>📍 Columna Contacto</h3>
                    <div class="form-group">
                        <label>Dirección</label>
                        <input type="text" name="contacto_direccion" value="<?php echo val($config,'footer','contacto_direccion'); ?>">
                        <p class="help-text">Puedes usar &lt;br&gt; para salto de línea.</p>
                    </div>
                    <div class="form-group">
                        <label>Teléfonos</label>
                        <input type="text" name="contacto_telefonos" value="<?php echo val($config,'footer','contacto_telefonos'); ?>">
                        <p class="help-text">Puedes usar &lt;br&gt; para salto de línea.</p>
                    </div>
                </div>

                <!-- BARRA INFERIOR + WHATSAPP -->
                <div class="section-block">
                    <h3>📋 Barra Inferior y WhatsApp Flotante</h3>
                    <div class="form-group">
                        <label>Texto copyright</label>
                        <input type="text" name="bottom_copy" value="<?php echo val($config,'footer','bottom_copy'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Enlace WhatsApp flotante (href completo)</label>
                        <input type="text" name="wsp_href" value="<?php echo val($config,'footer','wsp_href'); ?>" placeholder="https://wa.me/56912345678?text=...">
                    </div>
                    <div class="form-group">
                        <label>Texto botón WhatsApp flotante</label>
                        <input type="text" name="wsp_tag" value="<?php echo val($config,'footer','wsp_tag'); ?>">
                    </div>
                </div>

                <button type="submit" class="btn-save">💾 Guardar Cambios Footer</button>
            </form>
        </div>

        <!-- ═══════════════════════════════════════
             SEO TAB
        ════════════════════════════════════════ -->
        <div id="seo" class="tab-content <?php echo $activeTab==='seo'?'active':''; ?>">

            <style>
                /* ── SEO TAB STYLES ── */
                .seo-intro { background:#e8f5e9; border-left:4px solid #2e7d32; border-radius:8px; padding:16px 20px; margin-bottom:24px; }
                .seo-intro h3 { margin:0 0 6px; color:#1b5e20; font-size:1rem; }
                .seo-intro p  { margin:0; color:#2e7d32; font-size:0.88rem; }

                .seo-page-tabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:0; border-bottom:2px solid #e0e0e0; padding-bottom:0; }
                .seo-page-btn {
                    padding:8px 14px; border:2px solid transparent; border-bottom:none;
                    border-radius:6px 6px 0 0; cursor:pointer; font-size:0.82rem; font-weight:500;
                    background:#f0f0f0; color:#555; transition:all .2s;
                }
                .seo-page-btn:hover { background:#fff; color:#1b5e20; }
                .seo-page-btn.active { background:#fff; border-color:#ddd; border-bottom:2px solid #fff; margin-bottom:-2px; color:#1b5e20; font-weight:700; }
                .seo-page-btn.global-btn { background:#e8f5e9; color:#1b5e20; border-color:#a5d6a7; }
                .seo-page-btn.global-btn.active { background:#fff; }

                .seo-page-panel { display:none; background:#fff; border:1px solid #ddd; border-top:none; border-radius:0 6px 6px 6px; padding:24px; }
                .seo-page-panel.active { display:block; }

                /* Snippet Preview */
                .snippet-preview {
                    border:1px solid #ddd; border-radius:10px; padding:18px 20px;
                    background:#fff; margin-bottom:24px;
                    font-family: Arial, sans-serif; max-width:620px;
                    box-shadow:0 1px 6px rgba(0,0,0,.08);
                }
                .snippet-preview__url { color:#202124; font-size:13px; margin-bottom:4px; display:flex; align-items:center; gap:6px; }
                .snippet-preview__url span { color:#4d5156; }
                .snippet-preview__title { color:#1a0dab; font-size:20px; font-weight:400; margin:0 0 4px; line-height:1.3; cursor:pointer; }
                .snippet-preview__title:hover { text-decoration:underline; }
                .snippet-preview__desc { color:#4d5156; font-size:14px; line-height:1.58; margin:0; }
                .snippet-preview__label { font-size:0.75rem; font-weight:600; color:#888; text-transform:uppercase; letter-spacing:.06em; margin-bottom:10px; }
                .snippet-counter { font-size:0.78rem; color:#888; text-align:right; margin-top:4px; }
                .snippet-counter.warn { color:#e65100; font-weight:600; }
                .snippet-counter.ok   { color:#2e7d32; }

                .seo-field-group { margin-bottom:20px; }
                .seo-field-group label { display:block; margin-bottom:5px; font-weight:600; color:#333; font-size:.88rem; }
                .seo-field-group input[type="text"],
                .seo-field-group textarea,
                .seo-field-group select { width:100%; padding:9px 12px; border:1px solid #ccc; border-radius:6px; font-size:.9rem; }
                .seo-field-group textarea { resize:vertical; min-height:72px; }
                .seo-section-title { font-size:.95rem; font-weight:700; color:#1b5e20; border-bottom:2px solid #e0f2f1; padding-bottom:8px; margin:24px 0 16px; }
                .seo-tag { display:inline-block; background:#e8f5e9; color:#1b5e20; border-radius:4px; padding:2px 8px; font-size:.75rem; font-weight:700; margin-left:6px; vertical-align:middle; }
                .seo-row2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
                .seo-info-box { background:#f8f9fa; border:1px solid #e0e0e0; border-radius:6px; padding:12px 16px; margin-bottom:20px; font-size:.84rem; color:#555; }
                .seo-info-box strong { color:#333; }
                .schema-badge { display:inline-block; background:#1b5e20; color:#fff; border-radius:20px; padding:3px 10px; font-size:.75rem; font-weight:700; margin:2px; }
            </style>

            <!-- Intro -->
            <div class="seo-intro">
                <h3>🔍 Módulo SEO — CENEA</h3>
                <p>Edita los meta tags, Open Graph, Twitter Cards y Schemas de cada página. El sitemap se regenera automáticamente al guardar. Los cambios son <strong>inmediatos</strong> en el sitio.</p>
            </div>

            <!-- Sub-tabs de páginas -->
            <div class="seo-page-tabs">
                <button class="seo-page-btn global-btn active" onclick="openSeoTab(event,'seo-global')">⚙️ Global & Organización</button>
                <button class="seo-page-btn" onclick="openSeoTab(event,'seo-index')">🏠 Inicio</button>
                <button class="seo-page-btn" onclick="openSeoTab(event,'seo-quienes')">Quiénes Somos</button>
                <button class="seo-page-btn" onclick="openSeoTab(event,'seo-servicios')">Servicios</button>
                <button class="seo-page-btn" onclick="openSeoTab(event,'seo-equipo')">Equipo Médico</button>
                <button class="seo-page-btn" onclick="openSeoTab(event,'seo-contacto')">Contacto</button>
                <button class="seo-page-btn" onclick="openSeoTab(event,'seo-rdoctor')">Reservar Doctor</button>
                <button class="seo-page-btn" onclick="openSeoTab(event,'seo-resp')">Reservar Especialidad</button>
                <button class="seo-page-btn" onclick="openSeoTab(event,'seo-sitemap')" style="background:#e3f2fd;color:#1565c0;">🗺️ Sitemap</button>
            </div>

            <?php
            // Helper para construir el panel de una página
            function seoPagePanel($seo, $pageKey, $panelId, $pageLabel, $schemaTypes, $isActive=false) {
                $p = $seo['pages'][$pageKey] ?? [];
                $e = function($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); };
                $active = $isActive ? 'active' : '';
                echo '<div id="' . $panelId . '" class="seo-page-panel ' . $active . '">';
                echo '<form action="save_seo.php" method="POST" accept-charset="UTF-8">';
                echo '<input type="hidden" name="seo_section" value="' . $e($pageKey) . '">';
                echo csrfField();

                // Snippet Preview
                echo '<div class="seo-section-title">👁 Preview Snippet Google</div>';
                echo '<div class="snippet-preview">';
                echo '<div class="snippet-preview__label">Así se verá en Google:</div>';
                echo '<div class="snippet-preview__url">🌐 <span id="prev-url-' . $panelId . '">' . $e($p['canonical'] ?? '') . '</span></div>';
                echo '<div class="snippet-preview__title" id="prev-title-' . $panelId . '">' . $e($p['title'] ?? '') . '</div>';
                echo '<div class="snippet-preview__desc" id="prev-desc-' . $panelId . '">' . $e($p['description'] ?? '') . '</div>';
                echo '</div>';

                // Básicos
                echo '<div class="seo-section-title">📄 Básicos <span class="seo-tag">ESENCIAL</span></div>';
                echo '<div class="seo-field-group">';
                echo '<label>Title <span style="color:#888;font-weight:400">— máx. 60 caracteres</span></label>';
                echo '<input type="text" name="title" id="inp-title-' . $panelId . '" maxlength="70" value="' . $e($p['title'] ?? '') . '" oninput="updateSnippet(\'' . $panelId . '\')">';
                echo '<div class="snippet-counter" id="cnt-title-' . $panelId . '">0 / 60</div>';
                echo '</div>';
                echo '<div class="seo-field-group">';
                echo '<label>Meta Description <span style="color:#888;font-weight:400">— máx. 155 caracteres</span></label>';
                echo '<textarea name="description" id="inp-desc-' . $panelId . '" maxlength="160" oninput="updateSnippet(\'' . $panelId . '\')">' . $e($p['description'] ?? '') . '</textarea>';
                echo '<div class="snippet-counter" id="cnt-desc-' . $panelId . '">0 / 155</div>';
                echo '</div>';
                echo '<div class="seo-row2">';
                echo '<div class="seo-field-group"><label>URL Canonical</label><input type="text" name="canonical" value="' . $e($p['canonical'] ?? '') . '"></div>';
                echo '<div class="seo-field-group"><label>Robots</label><select name="robots"><option value="index, follow"' . (($p['robots']??'')==='index, follow'?' selected':'') . '>index, follow</option><option value="noindex, follow"' . (($p['robots']??'')==='noindex, follow'?' selected':'') . '>noindex, follow</option><option value="index, nofollow"' . (($p['robots']??'')==='index, nofollow'?' selected':'') . '>index, nofollow</option><option value="noindex, nofollow"' . (($p['robots']??'')==='noindex, nofollow'?' selected':'') . '>noindex, nofollow</option></select></div>';
                echo '</div>';

                // Open Graph
                echo '<div class="seo-section-title">📘 Open Graph <span class="seo-tag">Redes Sociales</span></div>';
                echo '<div class="seo-field-group"><label>og:title</label><input type="text" name="og_title" maxlength="95" value="' . $e($p['og_title'] ?? '') . '"></div>';
                echo '<div class="seo-field-group"><label>og:description</label><textarea name="og_description" maxlength="200">' . $e($p['og_description'] ?? '') . '</textarea></div>';
                echo '<div class="seo-row2">';
                echo '<div class="seo-field-group"><label>og:image (URL completa)</label><input type="text" name="og_image" value="' . $e($p['og_image'] ?? '') . '" placeholder="https://cenea.cl/imghome/..."></div>';
                echo '<div class="seo-field-group"><label>og:type</label><select name="og_type"><option value="website"' . (($p['og_type']??'website')==='website'?' selected':'') . '>website</option><option value="article"' . (($p['og_type']??'')==='article'?' selected':'') . '>article</option></select></div>';
                echo '</div>';

                // Twitter Cards
                echo '<div class="seo-section-title">🐦 Twitter / X Cards</div>';
                echo '<div class="seo-field-group"><label>twitter:title</label><input type="text" name="twitter_title" maxlength="70" value="' . $e($p['twitter_title'] ?? '') . '"></div>';
                echo '<div class="seo-field-group"><label>twitter:description</label><textarea name="twitter_description" maxlength="200">' . $e($p['twitter_description'] ?? '') . '</textarea></div>';
                echo '<div class="seo-row2">';
                echo '<div class="seo-field-group"><label>twitter:card</label><select name="twitter_card"><option value="summary_large_image"' . (($p['twitter_card']??'summary_large_image')==='summary_large_image'?' selected':'') . '>summary_large_image</option><option value="summary"' . (($p['twitter_card']??'')==='summary'?' selected':'') . '>summary</option></select></div>';
                echo '<div class="seo-field-group"><label>Schema Type <span style="color:#888;font-weight:400">(auto)</span></label><select name="schema_type">';
                $schemaOpts = ['LocalBusiness','MedicalBusiness','MedicalOrganization','AboutPage','ContactPage','webpage'];
                foreach($schemaOpts as $st) { echo '<option value="' . $st . '"' . (($p['schema_type']??'')===$st?' selected':'') . '>' . $st . '</option>'; }
                echo '</select></div>';
                echo '</div>';

                // Schemas activos
                echo '<div class="seo-section-title">🧩 Schemas JSON-LD activos en esta página</div>';
                echo '<div class="seo-info-box">';
                foreach($schemaTypes as $st) echo '<span class="schema-badge">' . $st . '</span>';
                echo '</div>';

                echo '<button type="submit" class="btn-save">💾 Guardar SEO — ' . htmlspecialchars($pageLabel, ENT_QUOTES, 'UTF-8') . '</button>';
                echo '</form></div>';
            }
            ?>

            <!-- PANEL GLOBAL -->
            <div id="seo-global" class="seo-page-panel active">
                <form action="save_seo.php" method="POST" accept-charset="UTF-8">
                    <input type="hidden" name="seo_section" value="global">
                    <?= csrfField() ?>

                    <div class="seo-section-title">🌐 Datos Globales del Sitio</div>
                    <div class="seo-row2">
                        <div class="seo-field-group"><label>Nombre del Sitio</label><input type="text" name="site_name" value="<?php echo seoVal($seo,'global','site_name'); ?>"></div>
                        <div class="seo-field-group"><label>URL del Sitio</label><input type="text" name="site_url" value="<?php echo seoVal($seo,'global','site_url'); ?>" placeholder="https://cenea.cl"></div>
                    </div>
                    <div class="seo-row2">
                        <div class="seo-field-group"><label>Locale</label><input type="text" name="locale" value="<?php echo seoVal($seo,'global','locale'); ?>" placeholder="es_CL"></div>
                        <div class="seo-field-group"><label>Twitter @usuario</label><input type="text" name="twitter_site" value="<?php echo seoVal($seo,'global','twitter_site'); ?>" placeholder="@ceneachile"></div>
                    </div>
                    <div class="seo-row2">
                        <div class="seo-field-group"><label>OG Image por defecto (URL)</label><input type="text" name="og_image" value="<?php echo seoVal($seo,'global','og_image'); ?>"></div>
                        <div class="seo-field-group"><label>Theme Color (hex)</label><input type="text" name="theme_color" value="<?php echo seoVal($seo,'global','theme_color'); ?>" placeholder="#0d3b2e"></div>
                    </div>
                    <div class="seo-row2">
                        <div class="seo-field-group"><label>Robots global</label><select name="robots_global"><option value="index, follow" <?php echo seoVal($seo,'global','robots_global')==='index, follow'?'selected':''; ?>>index, follow</option><option value="noindex, nofollow" <?php echo seoVal($seo,'global','robots_global')==='noindex, nofollow'?'selected':''; ?>>noindex, nofollow (modo mantenimiento)</option></select></div>
                        <div class="seo-field-group"><label>Autor</label><input type="text" name="author" value="<?php echo seoVal($seo,'global','author'); ?>"></div>
                    </div>

                    <div class="seo-section-title">🏢 Schema Organization / LocalBusiness <span class="seo-tag">Datos estructurados</span></div>
                    <div class="seo-info-box">Estos datos alimentan el <strong>Knowledge Panel</strong> de Google y el <strong>Google Maps Pack</strong>. Son los más importantes para SEO local.</div>

                    <?php $org = $seo['global']['schema_organization'] ?? []; ?>
                    <div class="seo-row2">
                        <div class="seo-field-group"><label>Nombre de la Organización</label><input type="text" name="org_name" value="<?php echo htmlspecialchars($org['name']??'',ENT_QUOTES,'UTF-8'); ?>"></div>
                        <div class="seo-field-group"><label>URL</label><input type="text" name="org_url" value="<?php echo htmlspecialchars($org['url']??'',ENT_QUOTES,'UTF-8'); ?>"></div>
                    </div>
                    <div class="seo-field-group"><label>Descripción (para Google Knowledge Panel)</label><textarea name="org_description"><?php echo htmlspecialchars($org['description']??'',ENT_QUOTES,'UTF-8'); ?></textarea></div>
                    <div class="seo-row2">
                        <div class="seo-field-group"><label>Logo (URL completa)</label><input type="text" name="org_logo" value="<?php echo htmlspecialchars($org['logo']??'',ENT_QUOTES,'UTF-8'); ?>"></div>
                        <div class="seo-field-group"><label>Email</label><input type="text" name="org_email" value="<?php echo htmlspecialchars($org['email']??'',ENT_QUOTES,'UTF-8'); ?>"></div>
                    </div>
                    <div class="seo-row2">
                        <div class="seo-field-group"><label>Teléfono principal</label><input type="text" name="org_telephone" value="<?php echo htmlspecialchars($org['telephone']??'',ENT_QUOTES,'UTF-8'); ?>"></div>
                        <div class="seo-field-group"><label>Teléfono secundario</label><input type="text" name="org_telephone2" value="<?php echo htmlspecialchars($org['telephone2']??'',ENT_QUOTES,'UTF-8'); ?>"></div>
                    </div>

                    <div class="seo-section-title">📍 Dirección</div>
                    <div class="seo-row2">
                        <div class="seo-field-group"><label>Calle y número</label><input type="text" name="org_address_street" value="<?php echo htmlspecialchars($org['address_street']??'',ENT_QUOTES,'UTF-8'); ?>"></div>
                        <div class="seo-field-group"><label>Ciudad</label><input type="text" name="org_address_city" value="<?php echo htmlspecialchars($org['address_city']??'',ENT_QUOTES,'UTF-8'); ?>"></div>
                    </div>
                    <div class="seo-row2">
                        <div class="seo-field-group"><label>Región</label><input type="text" name="org_address_region" value="<?php echo htmlspecialchars($org['address_region']??'',ENT_QUOTES,'UTF-8'); ?>"></div>
                        <div class="seo-field-group"><label>País (código ISO)</label><input type="text" name="org_address_country" value="<?php echo htmlspecialchars($org['address_country']??'',ENT_QUOTES,'UTF-8'); ?>" placeholder="CL"></div>
                    </div>
                    <div class="seo-row2">
                        <div class="seo-field-group"><label>Código Postal</label><input type="text" name="org_address_postal" value="<?php echo htmlspecialchars($org['address_postal']??'',ENT_QUOTES,'UTF-8'); ?>"></div>
                        <div class="seo-field-group"><label>Horario de Atención</label><input type="text" name="org_opening_hours" value="<?php echo htmlspecialchars($org['opening_hours']??'',ENT_QUOTES,'UTF-8'); ?>" placeholder="Mo-Fr 09:00-18:00"></div>
                    </div>
                    <div class="seo-row2">
                        <div class="seo-field-group"><label>Latitud</label><input type="text" name="org_latitude" value="<?php echo htmlspecialchars($org['latitude']??'',ENT_QUOTES,'UTF-8'); ?>"></div>
                        <div class="seo-field-group"><label>Longitud</label><input type="text" name="org_longitude" value="<?php echo htmlspecialchars($org['longitude']??'',ENT_QUOTES,'UTF-8'); ?>"></div>
                    </div>
                    <div class="seo-row2">
                        <div class="seo-field-group"><label>Rango de precio</label><input type="text" name="org_price_range" value="<?php echo htmlspecialchars($org['price_range']??'',ENT_QUOTES,'UTF-8'); ?>" placeholder="$$"></div>
                    </div>

                    <div class="seo-section-title">📲 Redes Sociales (sameAs)</div>
                    <div class="seo-row2">
                        <div class="seo-field-group"><label>Facebook URL</label><input type="text" name="org_facebook" value="<?php echo htmlspecialchars($org['facebook']??'',ENT_QUOTES,'UTF-8'); ?>"></div>
                        <div class="seo-field-group"><label>Instagram URL</label><input type="text" name="org_instagram" value="<?php echo htmlspecialchars($org['instagram']??'',ENT_QUOTES,'UTF-8'); ?>"></div>
                    </div>
                    <div class="seo-field-group"><label>LinkedIn URL</label><input type="text" name="org_linkedin" value="<?php echo htmlspecialchars($org['linkedin']??'',ENT_QUOTES,'UTF-8'); ?>"></div>

                    <button type="submit" class="btn-save">💾 Guardar Configuración Global SEO</button>
                </form>
            </div>

            <?php
            seoPagePanel($seo, 'index',                'seo-index',   'Inicio',               ['WebSite', 'MedicalBusiness', 'Organization']);
            seoPagePanel($seo, 'quienes-somos',        'seo-quienes', 'Quiénes Somos',        ['Organization', 'AboutPage', 'BreadcrumbList']);
            seoPagePanel($seo, 'servicios',            'seo-servicios','Servicios',            ['Organization', 'ItemList (Servicios)', 'BreadcrumbList']);
            seoPagePanel($seo, 'equipo-medico',        'seo-equipo',  'Equipo Médico',        ['Organization', 'MedicalOrganization', 'BreadcrumbList']);
            seoPagePanel($seo, 'contacto',             'seo-contacto','Contacto',             ['Organization', 'ContactPage', 'BreadcrumbList']);
            seoPagePanel($seo, 'reservar-doctor',      'seo-rdoctor', 'Reservar Doctor',      ['Organization', 'BreadcrumbList']);
            seoPagePanel($seo, 'reservar-especialidad','seo-resp',    'Reservar Especialidad',['Organization', 'BreadcrumbList']);
            ?>

            <!-- PANEL SITEMAP -->
            <div id="seo-sitemap" class="seo-page-panel">
                <div class="seo-section-title">🗺️ Sitemap XML</div>
                <div class="seo-info-box">
                    El <strong>sitemap.xml</strong> se regenera automáticamente cada vez que guardas cualquier sección SEO.<br>
                    También lo puedes regenerar manualmente aquí. Después, envíalo a <strong>Google Search Console</strong>.
                </div>

                <div class="section-block">
                    <h3>📋 Estado del Sitemap</h3>
                    <?php
                    $sitemapPath = '../sitemap.xml';
                    if (file_exists($sitemapPath)) {
                        $mtime = date('d/m/Y H:i', filemtime($sitemapPath));
                        echo '<p style="color:#2e7d32;font-weight:600;">✅ sitemap.xml existe — Última actualización: ' . $mtime . '</p>';
                        echo '<p>URL: <a href="../sitemap.xml" target="_blank" style="color:#1565c0;">https://cenea.cl/sitemap.xml</a></p>';
                        echo '<pre style="background:#f8f9fa;padding:14px;border-radius:6px;font-size:0.8rem;overflow:auto;max-height:300px;">';
                        echo htmlspecialchars(file_get_contents($sitemapPath), ENT_QUOTES, 'UTF-8');
                        echo '</pre>';
                    } else {
                        echo '<p style="color:#c62828;">⚠️ sitemap.xml no existe aún. Guarda cualquier sección SEO para generarlo.</p>';
                    }
                    ?>
                </div>

                <div class="section-block">
                    <h3>🔧 Regenerar Sitemap Manualmente</h3>
                    <form action="save_seo.php" method="POST" accept-charset="UTF-8">
                        <input type="hidden" name="seo_section" value="global">
                        <?= csrfField() ?>
                        <?php
                        $orgData = $seo['global']['schema_organization'] ?? [];
                        $globalData = $seo['global'] ?? [];
                        foreach ($globalData as $gk => $gv) {
                            if (!is_array($gv)) {
                                echo '<input type="hidden" name="' . htmlspecialchars($gk, ENT_QUOTES) . '" value="' . htmlspecialchars($gv, ENT_QUOTES, 'UTF-8') . '">';
                            }
                        }
                        foreach ($orgData as $ok => $ov) {
                            if (!is_array($ov)) {
                                echo '<input type="hidden" name="org_' . htmlspecialchars($ok, ENT_QUOTES) . '" value="' . htmlspecialchars($ov, ENT_QUOTES, 'UTF-8') . '">';
                            }
                        }
                        ?>
                        <button type="submit" class="btn-save" style="background:#1565c0;">🗺️ Regenerar sitemap.xml ahora</button>
                    </form>
                    <p class="help-text" style="margin-top:12px;">Después de regenerar, envía la URL al <a href="https://search.google.com/search-console" target="_blank" style="color:#1565c0;">Google Search Console</a>: <code>https://cenea.cl/sitemap.xml</code></p>
                </div>

                <div class="section-block">
                    <h3>📖 Guía rápida: Cómo enviar a Google</h3>
                    <ol style="font-size:.9rem;color:#444;line-height:1.8;">
                        <li>Ve a <a href="https://search.google.com/search-console" target="_blank" style="color:#1565c0;">Google Search Console</a></li>
                        <li>Selecciona la propiedad <strong>cenea.cl</strong></li>
                        <li>En el menú izquierdo, haz clic en <strong>"Sitemaps"</strong></li>
                        <li>Ingresa <code>sitemap.xml</code> y haz clic en <strong>"Enviar"</strong></li>
                        <li>Google comenzará a indexar el sitio en las próximas horas</li>
                    </ol>
                </div>
            </div>

            <script>
            function openSeoTab(evt, panelId) {
                document.querySelectorAll('.seo-page-panel').forEach(p => p.classList.remove('active'));
                document.querySelectorAll('.seo-page-btn').forEach(b => b.classList.remove('active'));
                document.getElementById(panelId).classList.add('active');
                evt.currentTarget.classList.add('active');
            }

            function updateSnippet(panelId) {
                const titleEl = document.getElementById('inp-title-' + panelId);
                const descEl  = document.getElementById('inp-desc-'  + panelId);
                const prevTitle = document.getElementById('prev-title-' + panelId);
                const prevDesc  = document.getElementById('prev-desc-'  + panelId);
                const cntTitle  = document.getElementById('cnt-title-'  + panelId);
                const cntDesc   = document.getElementById('cnt-desc-'   + panelId);

                if (titleEl && prevTitle) {
                    prevTitle.textContent = titleEl.value;
                    const tLen = titleEl.value.length;
                    cntTitle.textContent = tLen + ' / 60';
                    cntTitle.className = 'snippet-counter ' + (tLen > 60 ? 'warn' : tLen >= 40 ? 'ok' : '');
                }
                if (descEl && prevDesc) {
                    prevDesc.textContent = descEl.value;
                    const dLen = descEl.value.length;
                    cntDesc.textContent = dLen + ' / 155';
                    cntDesc.className = 'snippet-counter ' + (dLen > 155 ? 'warn' : dLen >= 100 ? 'ok' : '');
                }
            }

            // Inicializar contadores al cargar
            document.addEventListener('DOMContentLoaded', function() {
                ['seo-index','seo-quienes','seo-servicios','seo-equipo','seo-contacto','seo-rdoctor','seo-resp'].forEach(function(id) {
                    updateSnippet(id);
                });
            });
            </script>
        </div>

        <!-- ═══════════════════════════════════════
             EQUIPO MÉDICO TAB
        ════════════════════════════════════════ -->
        <div id="equipo_medico" class="tab-content <?php echo $activeTab==='equipo_medico'?'active':''; ?>">

            <!-- Categorías -->
            <div class="section-block">
                <h3>🗂 Administrar Categorías</h3>
                <form action="save_category.php" method="POST" accept-charset="UTF-8"
                    style="display:flex; gap:10px; align-items:flex-end;">
                    <?= csrfField() ?>
                    <div style="flex:1;">
                        <label style="margin-bottom:5px; display:block;">Nueva Categoría</label>
                        <input type="text" name="nombre" placeholder="Ej: Neurólogos Adultos" required>
                    </div>
                    <button type="submit" class="btn-save" style="margin-top:0; white-space:nowrap;">+ Añadir</button>
                </form>
                <ul class="cat-list">
                    <?php foreach ($categorias as $cat): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($cat['nombre']); ?></strong>
                            <span class="cat-id">(ID: <?php echo htmlspecialchars($cat['id']); ?>)</span>
                            <a href="delete_category.php?id=<?php echo urlencode($cat['id']); ?>"
                                onclick="return confirm('¿Eliminar categoría? Los doctores con esta categoría dejarán de mostrarse bajo ella.')"
                                style="color:#d32f2f; font-size:0.85em; margin-left:auto;">Eliminar</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Formulario perfil -->
            <div class="section-block">
                <h3 id="perfil-form-title">➕ Añadir Nuevo Perfil</h3>
                <form action="save_profile.php" method="POST" enctype="multipart/form-data" accept-charset="UTF-8" id="perfil-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="" id="perfil-id">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nombre del Profesional</label>
                            <input type="text" name="nombre" id="perfil-nombre" required>
                        </div>
                        <div class="form-group">
                            <label>Especialidad</label>
                            <input type="text" name="especialidad" id="perfil-especialidad" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Biografía Pequeña</label>
                        <?php echo rte('bio', '', 'perfil-bio', 120); ?>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Categorías <small style="color:#777;">(selecciona una o más)</small></label>
                            <div style="display:flex; flex-wrap:wrap; gap:10px 20px; margin-top:6px; padding:12px; background:#f5f5f5; border-radius:6px; border:1px solid #ddd;">
                                <?php foreach ($categorias as $cat): ?>
                                    <label style="display:flex; align-items:center; gap:6px; font-weight:normal; cursor:pointer; margin:0;">
                                        <input type="checkbox" name="categoria[]"
                                            value="<?php echo htmlspecialchars($cat['id']); ?>"
                                            class="cat-checkbox"
                                            style="width:16px; height:16px; cursor:pointer;">
                                        <?php echo htmlspecialchars($cat['nombre']); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Foto de Perfil</label>
                            <input type="file" name="imagen_file" accept=".jpg,.jpeg,.png,.webp">
                            <p class="help-text">Si estás editando, déjalo en blanco para mantener la imagen actual.</p>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Link de Reserva (opcional)</label>
                            <input type="text" name="link_reserva" id="perfil-link-reserva" placeholder="https://...">
                            <p class="help-text">URL donde el paciente puede agendar una hora con este profesional.</p>
                        </div>
                        <div class="form-group" style="display:flex; flex-direction:column; justify-content:center;">
                            <label style="margin-bottom:8px;">Telemedicina</label>
                            <label style="display:flex; align-items:center; gap:10px; font-weight:normal; cursor:pointer; padding:12px; background:#f5f5f5; border-radius:6px; border:1px solid #ddd;">
                                <input type="checkbox" name="telemedicina" id="perfil-telemedicina"
                                    style="width:18px; height:18px; cursor:pointer; accent-color:#2e7d32;">
                                <span>¿Atiende por telemedicina?</span>
                            </label>
                            <p class="help-text">Si está marcado, aparecerá un badge verde en el card del profesional.</p>
                        </div>
                    </div>
                    <button type="submit" class="btn-save">💾 Guardar Perfil</button>
                    <button type="button" class="btn-secondary" onclick="cancelEditProfile()">✕ Limpiar / Nuevo</button>
                </form>
            </div>

            <!-- Grid de perfiles -->
            <div class="section-block">
                <h3>👥 Perfiles Existentes (<?php echo count($perfiles); ?>)</h3>
                <div class="perfiles-grid">
                    <?php foreach (array_reverse($perfiles) as $p):
                        // categoria es array; construir string legible y JSON para JS
                        $catArray  = is_array($p['categoria']) ? $p['categoria'] : [$p['categoria']];
                        $catLabels = [];
                        foreach ($catArray as $cid) {
                            foreach ($categorias as $cat) {
                                if ($cat['id'] === $cid) { $catLabels[] = $cat['nombre']; break; }
                            }
                        }
                        $catDisplay  = implode(', ', $catLabels) ?: implode(', ', $catArray);
                        $catJsonB64  = base64_encode(json_encode($catArray));
                        $telemedicina = !empty($p['telemedicina']);
                    ?>
                        <div class="perfil-card">
                            <div class="perfil-card__header">
                                <?php if (!empty($p['imagen'])): ?>
                                    <img src="../<?php echo htmlspecialchars($p['imagen']); ?>" class="perfil-card__img" alt="<?php echo htmlspecialchars($p['nombre']); ?>">
                                <?php else: ?>
                                    <div class="perfil-card__no-img">Sin foto</div>
                                <?php endif; ?>
                                <div>
                                    <div class="perfil-card__nombre"><?php echo htmlspecialchars($p['nombre']); ?></div>
                                    <div class="perfil-card__esp"><?php echo htmlspecialchars($p['especialidad']); ?></div>
                                    <span class="perfil-card__cat"><?php echo htmlspecialchars($catDisplay); ?></span>
                                    <?php if ($telemedicina): ?>
                                        <span style="display:inline-block; margin-top:4px; background:#e8f5e9; color:#2e7d32; font-size:0.72em; padding:2px 8px; border-radius:20px; font-weight:600;">📹 Telemedicina</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="perfil-card__actions" style="flex-wrap:wrap; gap:8px;">
                                <button type="button" class="btn-quick-edit"
                                    data-pid="<?php echo htmlspecialchars($p['id'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-nombre="<?php echo htmlspecialchars($p['nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-esp="<?php echo htmlspecialchars($p['especialidad'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-cats="<?php echo $catJsonB64; ?>"
                                    data-link="<?php echo htmlspecialchars($p['link_reserva'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-tele="<?php echo $telemedicina ? '1' : '0'; ?>"
                                    data-bio="<?php echo htmlspecialchars(base64_encode($p['bio'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-img="<?php echo htmlspecialchars($p['imagen'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    onclick="openQuickEdit(this)">
                                    ⚡ Editar rápido
                                </button>
                                <button type="button" class="btn-edit"
                                    data-pid="<?php echo htmlspecialchars($p['id'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-nombre="<?php echo htmlspecialchars($p['nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-esp="<?php echo htmlspecialchars($p['especialidad'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-cats="<?php echo $catJsonB64; ?>"
                                    data-link="<?php echo htmlspecialchars($p['link_reserva'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-tele="<?php echo $telemedicina ? '1' : '0'; ?>"
                                    data-bio="<?php echo htmlspecialchars(base64_encode($p['bio'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    onclick="editProfileFromBtn(this)">
                                    Editar completo
                                </button>
                                <a href="delete_profile.php?id=<?php echo urlencode($p['id']); ?>"
                                    onclick="return confirm('¿Seguro que deseas eliminar este perfil?')"
                                    class="btn-delete">Eliminar</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <p style="margin-top: 2rem; font-size: 0.9rem;">
            <a href="../index.html" target="_blank" style="color: #004d40;">Ver sitio web →</a>
        </p>
    </div>

        <!-- ═══════════════════════════════════════
             INTEGRACIONES TAB
        ════════════════════════════════════════ -->
        <div id="integraciones" class="tab-content <?php echo $activeTab==='integraciones'?'active':''; ?>">
        <style>
            /* ── INTEGRACIONES TAB ── */
            .integ-intro { background:#e8eaf6; border-left:4px solid #3949ab; border-radius:8px; padding:16px 20px; margin-bottom:24px; }
            .integ-intro h3 { margin:0 0 6px; color:#283593; font-size:1rem; }
            .integ-intro p  { margin:0; color:#3949ab; font-size:0.88rem; }

            .integ-card {
                border:1px solid #e0e0e0; border-radius:10px;
                margin-bottom:20px; overflow:hidden;
                transition: box-shadow .2s;
            }
            .integ-card:hover { box-shadow: 0 3px 12px rgba(0,0,0,.09); }
            .integ-card-header {
                display:flex; align-items:center; gap:14px;
                padding:16px 20px; background:#fafbfc;
                border-bottom:1px solid #eee; cursor:pointer;
                user-select:none;
            }
            .integ-card-header:hover { background:#f0f2ff; }
            .integ-logo {
                width:36px; height:36px; border-radius:8px;
                display:flex; align-items:center; justify-content:center;
                font-size:1.3rem; flex-shrink:0;
            }
            .integ-card-title { flex:1; }
            .integ-card-title strong { display:block; font-size:.95rem; color:#222; }
            .integ-card-title span   { font-size:.8rem; color:#777; }
            .integ-toggle {
                position:relative; width:44px; height:24px; flex-shrink:0;
            }
            .integ-toggle input { opacity:0; width:0; height:0; position:absolute; }
            .integ-toggle-slider {
                position:absolute; inset:0; background:#ccc; border-radius:24px;
                cursor:pointer; transition:.3s;
            }
            .integ-toggle-slider::before {
                content:''; position:absolute;
                height:18px; width:18px; left:3px; bottom:3px;
                background:#fff; border-radius:50%; transition:.3s;
            }
            .integ-toggle input:checked + .integ-toggle-slider { background:#3949ab; }
            .integ-toggle input:checked + .integ-toggle-slider::before { transform:translateX(20px); }

            .integ-badge { font-size:.7rem; font-weight:700; padding:2px 8px; border-radius:20px; margin-left:8px; }
            .integ-badge--on  { background:#e8f5e9; color:#2e7d32; }
            .integ-badge--off { background:#f5f5f5; color:#999; }

            .integ-card-body { padding:18px 20px; display:none; }
            .integ-card-body.open { display:block; }
            .integ-field { margin-bottom:14px; }
            .integ-field label { display:block; font-weight:500; font-size:.87rem; color:#444; margin-bottom:5px; }
            .integ-field input[type="text"],
            .integ-field textarea { width:100%; padding:9px 12px; border:1px solid #ccc; border-radius:6px; font-size:.88rem; font-family:monospace; }
            .integ-field textarea { min-height:120px; resize:vertical; }
            .integ-field .help { font-size:.78rem; color:#888; margin-top:4px; }
            .integ-field .help a { color:#3949ab; }
            .integ-row2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }

            .integ-section-sep { font-size:.8rem; font-weight:700; color:#3949ab; text-transform:uppercase;
                letter-spacing:.07em; margin:28px 0 14px; padding-bottom:6px;
                border-bottom:2px solid #e8eaf6; }
            .integ-warning { background:#fff8e1; border:1px solid #ffe082; border-radius:6px;
                padding:10px 14px; font-size:.82rem; color:#7c5800; margin-bottom:16px; }
        </style>

            <form action="save_integraciones.php" method="POST" accept-charset="UTF-8">
                <?= csrfField() ?>

                <div class="integ-intro">
                    <h3>⚡ Integraciones y Pixels de Marketing</h3>
                    <p>Activa y configura cada herramienta. Los scripts se inyectan automáticamente en todas las páginas del sitio al activarlos. <strong>Solo ingresa los IDs/códigos</strong> — el código completo se genera solo.</p>
                </div>

                <?php
                // ── Helper para renderizar una tarjeta de integración ──
                function integCard($integ, $key, $icon, $iconBg, $title, $desc, $fields, $helpHtml = '') {
                    $enabled = !empty($integ[$key]['enabled']);
                    $badgeClass = $enabled ? 'integ-badge--on' : 'integ-badge--off';
                    $badgeText  = $enabled ? 'Activo' : 'Inactivo';
                    $bodyClass  = $enabled ? 'integ-card-body open' : 'integ-card-body';
                    echo '<div class="integ-card" id="card-' . $key . '">';
                    // Header clicable
                    echo '<div class="integ-card-header" onclick="toggleIntegCard(\'' . $key . '\')">';
                    echo '<div class="integ-logo" style="background:' . $iconBg . '">' . $icon . '</div>';
                    echo '<div class="integ-card-title"><strong>' . htmlspecialchars($title) . '<span class="integ-badge ' . $badgeClass . '" id="badge-' . $key . '">' . $badgeText . '</span></strong><span>' . htmlspecialchars($desc) . '</span></div>';
                    // Toggle switch
                    echo '<label class="integ-toggle" onclick="event.stopPropagation()">';
                    echo '<input type="checkbox" name="' . $key . '_enabled" value="1"' . ($enabled ? ' checked' : '') . ' onchange="onToggle(\'' . $key . '\', this)">';
                    echo '<span class="integ-toggle-slider"></span>';
                    echo '</label>';
                    echo '</div>';
                    // Body
                    echo '<div class="' . $bodyClass . '" id="body-' . $key . '">';
                    if ($helpHtml) echo '<div class="integ-warning">' . $helpHtml . '</div>';
                    foreach ($fields as $f) {
                        echo '<div class="integ-field' . (!empty($f['row2_next']) ? ' integ-row2-start' : '') . '">';
                        echo '<label>' . htmlspecialchars($f['label']) . '</label>';
                        $val = htmlspecialchars($integ[$key][$f['field']] ?? '', ENT_QUOTES, 'UTF-8');
                        $ph  = htmlspecialchars($f['placeholder'] ?? '', ENT_QUOTES, 'UTF-8');
                        if (!empty($f['textarea'])) {
                            echo '<textarea name="' . $key . '_' . $f['field'] . '" placeholder="' . $ph . '">' . $val . '</textarea>';
                        } else {
                            echo '<input type="text" name="' . $key . '_' . $f['field'] . '" value="' . $val . '" placeholder="' . $ph . '">';
                        }
                        if (!empty($f['help'])) echo '<div class="help">' . $f['help'] . '</div>';
                        echo '</div>';
                    }
                    echo '</div></div>';
                }
                ?>

                <!-- ── SECCIÓN: GOOGLE ── -->
                <div class="integ-section-sep">🔵 Google</div>

                <?php integCard($integ, 'google_tag_manager',
                    '📦', '#e8f0fe',
                    'Google Tag Manager',
                    'Gestor centralizado de todos tus tags y pixels',
                    [
                        ['field'=>'container_id','label'=>'Container ID','placeholder'=>'GTM-XXXXXXX',
                         'help'=>'<a href="https://tagmanager.google.com/" target="_blank">Encuentra tu ID</a> en GTM → Admin → Container ID']
                    ],
                    '⚠️ <strong>Recomendado:</strong> Si usas GTM, no necesitas activar GA4 ni Google Ads por separado — gestiónalos desde dentro de GTM.'
                ); ?>

                <?php integCard($integ, 'google_analytics',
                    '📊', '#e6f4ea',
                    'Google Analytics 4 (GA4)',
                    'Analítica web: visitas, comportamiento, conversiones',
                    [
                        ['field'=>'measurement_id','label'=>'Measurement ID','placeholder'=>'G-XXXXXXXXXX',
                         'help'=>'<a href="https://analytics.google.com/" target="_blank">GA4</a> → Admin → Flujos de datos → tu flujo → ID de medición']
                    ]
                ); ?>

                <?php integCard($integ, 'google_search_console',
                    '🔍', '#fce8e6',
                    'Google Search Console',
                    'Verificación de propiedad para indexación en Google',
                    [
                        ['field'=>'verification_code','label'=>'Código de verificación meta tag','placeholder'=>'abc123xyz456',
                         'help'=>'En Search Console → Configuración → Verificar propiedad → Etiqueta HTML. <strong>Copia solo el valor</strong> del atributo <code>content</code>, sin las comillas.']
                    ]
                ); ?>

                <?php integCard($integ, 'google_ads',
                    '🎯', '#fef9c3',
                    'Google Ads',
                    'Remarketing y seguimiento de conversiones',
                    [
                        ['field'=>'conversion_id',   'label'=>'Conversion ID',   'placeholder'=>'AW-123456789',
                         'help'=>'Google Ads → Herramientas → Medición → Conversiones → Etiqueta de Google'],
                        ['field'=>'conversion_label','label'=>'Conversion Label (opcional)','placeholder'=>'AbCdEfGhIj'],
                    ]
                ); ?>

                <!-- ── SECCIÓN: META / FACEBOOK ── -->
                <div class="integ-section-sep">🔵 Meta (Facebook / Instagram)</div>

                <?php integCard($integ, 'meta_pixel',
                    '📘', '#e7f0fd',
                    'Meta Pixel',
                    'Pixel de Facebook e Instagram Ads — seguimiento y remarketing',
                    [
                        ['field'=>'pixel_id','label'=>'Pixel ID','placeholder'=>'1234567890123456',
                         'help'=>'<a href="https://business.facebook.com/events_manager" target="_blank">Meta Events Manager</a> → Orígenes de datos → tu Pixel → ID del pixel']
                    ]
                ); ?>

                <?php integCard($integ, 'meta_conversions_api',
                    '🔗', '#ede7f6',
                    'Meta Conversions API (CAPI)',
                    'API server-side para seguimiento sin cookies (complemento al Pixel)',
                    [
                        ['field'=>'access_token','label'=>'Access Token','placeholder'=>'EAAxxxxx...',
                         'help'=>'Meta Events Manager → Configuración → API de conversiones → Generar token de acceso']
                    ],
                    '⚠️ La API de Conversiones requiere implementación server-side (PHP). El token aquí guardado está disponible para uso en formularios de contacto.'
                ); ?>

                <!-- ── SECCIÓN: ANALÍTICA ── -->
                <div class="integ-section-sep">🟣 Analítica de Comportamiento</div>

                <?php integCard($integ, 'hotjar',
                    '🔥', '#fff3e0',
                    'Hotjar',
                    'Mapas de calor, grabaciones de sesión y encuestas',
                    [
                        ['field'=>'site_id','label'=>'Site ID','placeholder'=>'1234567',
                         'help'=>'<a href="https://insights.hotjar.com/sites" target="_blank">Hotjar</a> → Sites → tu sitio → Site ID']
                    ]
                ); ?>

                <?php integCard($integ, 'clarity',
                    '🟣', '#ede7f6',
                    'Microsoft Clarity',
                    'Mapas de calor y grabaciones de sesión (gratuito)',
                    [
                        ['field'=>'project_id','label'=>'Project ID','placeholder'=>'abc12defgh',
                         'help'=>'<a href="https://clarity.microsoft.com/" target="_blank">Microsoft Clarity</a> → tu proyecto → Configuración → Project ID']
                    ]
                ); ?>

                <!-- ── SECCIÓN: CÓDIGO PERSONALIZADO ── -->
                <div class="integ-section-sep">⚙️ Código Personalizado</div>

                <?php integCard($integ, 'custom_head',
                    '&lt;/&gt;', '#f3e5f5',
                    'Código personalizado — &lt;head&gt;',
                    'Se inyecta dentro del <head>. Para cualquier pixel o script no listado.',
                    [
                        ['field'=>'code','label'=>'Código HTML / Script','placeholder'=>'<!-- Aquí tu código -->','textarea'=>true,
                         'help'=>'Puedes pegar cualquier script, meta tag o link. Se ejecuta en todas las páginas.']
                    ]
                ); ?>

                <?php integCard($integ, 'custom_body_start',
                    '▼', '#e0f7fa',
                    'Código personalizado — inicio del &lt;body&gt;',
                    'Se inyecta justo después del <body>. Ideal para noscript de GTM.',
                    [
                        ['field'=>'code','label'=>'Código HTML / Script','placeholder'=>'<!-- noscript GTM u otro -->','textarea'=>true]
                    ]
                ); ?>

                <?php integCard($integ, 'custom_body_end',
                    '▲', '#e8f5e9',
                    'Código personalizado — final del &lt;body&gt;',
                    'Se inyecta justo antes del </body>. Para scripts de carga diferida.',
                    [
                        ['field'=>'code','label'=>'Código HTML / Script','placeholder'=>'<!-- Scripts diferidos -->','textarea'=>true]
                    ]
                ); ?>

                <button type="submit" class="btn-save" style="background:#3949ab; margin-top:8px;">⚡ Guardar Integraciones</button>
            </form>

            <script>
            function toggleIntegCard(key) {
                const body  = document.getElementById('body-' + key);
                const isOpen = body.classList.contains('open');
                body.classList.toggle('open', !isOpen);
            }
            function onToggle(key, checkbox) {
                const body  = document.getElementById('body-' + key);
                const badge = document.getElementById('badge-' + key);
                const on    = checkbox.checked;
                body.classList.toggle('open', on);
                if (badge) {
                    badge.textContent  = on ? 'Activo' : 'Inactivo';
                    badge.className    = 'integ-badge ' + (on ? 'integ-badge--on' : 'integ-badge--off');
                }
            }
            </script>
        </div>

    <!-- ══════════════════════════════════════════════
         MODAL EDICIÓN RÁPIDA
    ══════════════════════════════════════════════ -->
    <div class="modal-overlay" id="quick-edit-modal" onclick="closeQuickEdit(event)">
        <div class="modal-box">
            <div class="modal-header">
                <h3>⚡ Edición Rápida</h3>
                <button class="modal-close" onclick="closeQuickEditDirect()" title="Cerrar">✕</button>
            </div>
            <form action="save_profile.php" method="POST" enctype="multipart/form-data" accept-charset="UTF-8" id="quick-edit-form">
                <?= csrfField() ?>
                <input type="hidden" name="id" id="qe-id">
                <!-- Preserve campos que no se editan en el modal -->
                <input type="hidden" name="bio"          id="qe-bio-hidden">
                <input type="hidden" name="link_reserva" id="qe-link-hidden">
                <input type="hidden" name="quick_edit"   value="1">

                <div class="modal-body">
                    <!-- Foto + nombre actual (solo display) -->
                    <div class="modal-perfil-header">
                        <div class="modal-perfil-noimg" id="qe-img-wrap">👤</div>
                        <div>
                            <div class="modal-perfil-name" id="qe-display-name">—</div>
                            <div class="modal-perfil-id"   id="qe-display-id">—</div>
                        </div>
                    </div>

                    <!-- Nombre -->
                    <div class="form-group">
                        <label style="font-weight:600;">Nombre del Profesional</label>
                        <input type="text" name="nombre" id="qe-nombre" required
                               style="width:100%; padding:9px 12px; border:1.5px solid #d1d5db; border-radius:6px; font-size:0.95rem; font-family:inherit;">
                    </div>

                    <!-- Especialidad -->
                    <div class="form-group">
                        <label style="font-weight:600;">Especialidad / Bajada</label>
                        <input type="text" name="especialidad" id="qe-especialidad" required
                               style="width:100%; padding:9px 12px; border:1.5px solid #d1d5db; border-radius:6px; font-size:0.95rem; font-family:inherit;">
                    </div>

                    <!-- Categorías -->
                    <div class="form-group">
                        <label style="font-weight:600;">Categorías</label>
                        <div class="modal-cats-grid" id="qe-cats-grid">
                            <?php foreach ($categorias as $cat): ?>
                                <label class="modal-cat-label" onclick="toggleModalCat(this)">
                                    <input type="checkbox" name="categoria[]"
                                           value="<?php echo htmlspecialchars($cat['id'], ENT_QUOTES); ?>"
                                           class="qe-cat-cb">
                                    <?php echo htmlspecialchars($cat['nombre']); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Telemedicina -->
                    <div class="form-group">
                        <label style="font-weight:600;">Telemedicina</label>
                        <div class="tele-toggle-wrap" id="qe-tele-wrap" onclick="toggleTele()">
                            <div class="tele-toggle" id="qe-tele-toggle"></div>
                            <span class="tele-toggle-text" id="qe-tele-text">No atiende por telemedicina</span>
                            <input type="checkbox" name="telemedicina" id="qe-telemedicina" style="display:none;">
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeQuickEditDirect()" style="margin:0;">Cancelar</button>
                    <button type="submit" class="btn-save" style="margin:0;">💾 Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        /* ══════════════════════════════════════════════
           TABS
        ══════════════════════════════════════════════ */
        function openTab(evt, tabName) {
            document.querySelectorAll('.tab-content').forEach(t => {
                t.style.display = 'none';
                t.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(tabName).style.display = 'block';
            document.getElementById(tabName).classList.add('active');
            evt.currentTarget.classList.add('active');
        }

        /* ══════════════════════════════════════════════
           PERFILES
        ══════════════════════════════════════════════ */
        // Decodifica base64 con soporte completo UTF-8
        function b64DecodeUTF8(b64) {
            try {
                const bytes = Uint8Array.from(atob(b64), c => c.charCodeAt(0));
                return new TextDecoder('utf-8').decode(bytes);
            } catch(e) { return ''; }
        }

        function editProfileFromBtn(btn) {
            const id         = btn.dataset.pid;
            const nombre     = btn.dataset.nombre;
            const especialidad = btn.dataset.esp;
            const catJsonB64 = btn.dataset.cats;
            const linkReserva = btn.dataset.link;
            const telemedicina = btn.dataset.tele === '1';
            const bioHtml    = b64DecodeUTF8(btn.dataset.bio);
            editProfile(id, nombre, especialidad, catJsonB64, linkReserva, telemedicina, bioHtml);
        }

        function editProfile(id, nombre, especialidad, catJsonB64, linkReserva, telemedicina, bioHtml) {
            document.getElementById('perfil-id').value = id;
            document.getElementById('perfil-nombre').value = nombre;
            document.getElementById('perfil-especialidad').value = especialidad;
            document.getElementById('perfil-link-reserva').value = linkReserva;
            document.getElementById('perfil-telemedicina').checked = telemedicina;

            // Cargar bio en el RTE con UTF-8 correcto
            if (window.rteSetContent) {
                window.rteSetContent('perfil-bio-rte', bioHtml);
            } else {
                document.getElementById('perfil-bio').value = bioHtml;
            }

            document.querySelectorAll('.cat-checkbox').forEach(cb => cb.checked = false);
            try {
                const cats = JSON.parse(b64DecodeUTF8(catJsonB64));
                cats.forEach(catId => {
                    const cb = document.querySelector('.cat-checkbox[value="' + catId + '"]');
                    if (cb) cb.checked = true;
                });
            } catch(e) { console.warn('Error parsing categorías', e); }

            document.getElementById('perfil-form-title').textContent = '✏️ Editar Perfil: ' + nombre;
            document.getElementById('perfil-nombre').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        function cancelEditProfile() {
            document.getElementById('perfil-id').value = '';
            document.getElementById('perfil-form').reset();
            document.querySelectorAll('.cat-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('perfil-form-title').textContent = '➕ Añadir Nuevo Perfil';
            // Limpiar RTE bio
            if (window.rteSetContent) window.rteSetContent('perfil-bio-rte', '');
        }

        /* ══════════════════════════════════════════════
           SERVICIOS
        ══════════════════════════════════════════════ */
        function selectIcon(nombre) {
            document.getElementById('servicio-icono').value = nombre;
            document.getElementById('icon-label').textContent = nombre;
            document.querySelectorAll('.icon-btn').forEach(btn => {
                const isSelected = btn.dataset.icon === nombre;
                btn.style.borderColor  = isSelected ? '#004d40' : '#ddd';
                btn.style.background   = isSelected ? '#e0f2f1' : '#f9f9f9';
            });
        }

        function editServicio(id, titulo, descripcion, destacado, icono) {
            document.getElementById('servicio-id').value = id;
            // El campo titulo ahora es un RTE inline
            if (window.rteSetContent) {
                window.rteSetContent('servicio-titulo-rte', titulo);
            } else {
                document.getElementById('servicio-titulo').value = titulo;
            }
            // Cargar descripción en el RTE
            if (window.rteSetContent) {
                window.rteSetContent('servicio-desc-rte', descripcion);
            } else {
                document.getElementById('servicio-desc').value = descripcion;
            }
            document.getElementById('servicio-destacado').checked = destacado;
            selectIcon(icono || 'activity');
            document.getElementById('servicio-form-title').textContent = '✏️ Editar Servicio: ' + titulo;
            document.getElementById('servicio-titulo').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        function cancelEditServicio() {
            document.getElementById('servicio-id').value = '';
            if (window.rteSetContent) window.rteSetContent('servicio-titulo-rte', '');
            document.getElementById('servicio-desc').value = '';
            document.getElementById('servicio-destacado').checked = false;
            selectIcon('activity');
            document.getElementById('servicio-form-title').textContent = '➕ Añadir Nuevo Servicio';
            if (window.rteSetContent) window.rteSetContent('servicio-desc-rte', '');
        }

        /* ══════════════════════════════════════════════
           MODAL EDICIÓN RÁPIDA
        ══════════════════════════════════════════════ */
        function openQuickEdit(btn) {
            const id     = btn.dataset.pid;
            const nombre = btn.dataset.nombre;
            const esp    = btn.dataset.esp;
            const cats   = btn.dataset.cats;   // base64 JSON array
            const link   = btn.dataset.link;
            const tele   = btn.dataset.tele === '1';
            const bio    = b64DecodeUTF8(btn.dataset.bio);
            const img    = btn.dataset.img;

            // Rellenar campos
            document.getElementById('qe-id').value              = id;
            document.getElementById('qe-nombre').value          = nombre;
            document.getElementById('qe-especialidad').value    = esp;
            document.getElementById('qe-bio-hidden').value      = bio;
            document.getElementById('qe-link-hidden').value     = link;
            document.getElementById('qe-display-name').textContent = nombre;
            document.getElementById('qe-display-id').textContent   = 'ID: ' + id;

            // Foto
            const imgWrap = document.getElementById('qe-img-wrap');
            if (img) {
                imgWrap.innerHTML = '<img src="../' + img + '" class="modal-perfil-img" alt="' + nombre + '">';
            } else {
                imgWrap.innerHTML = '👤';
                imgWrap.className = 'modal-perfil-noimg';
            }

            // Categorías — desmarcar todas, luego marcar las del perfil
            const activeCats = JSON.parse(b64DecodeUTF8(cats) || '[]');
            document.querySelectorAll('.qe-cat-cb').forEach(cb => {
                cb.checked = activeCats.includes(cb.value);
                cb.closest('.modal-cat-label').classList.toggle('checked', cb.checked);
            });

            // Telemedicina
            setTeleState(tele);

            // Abrir modal
            document.getElementById('quick-edit-modal').classList.add('open');
            document.body.style.overflow = 'hidden';

            // Focus en nombre
            setTimeout(() => document.getElementById('qe-nombre').focus(), 80);
        }

        function closeQuickEdit(e) {
            // Cerrar solo si se hace click en el overlay (fondo oscuro)
            if (e.target === document.getElementById('quick-edit-modal')) {
                closeQuickEditDirect();
            }
        }
        function closeQuickEditDirect() {
            document.getElementById('quick-edit-modal').classList.remove('open');
            document.body.style.overflow = '';
        }

        function toggleModalCat(label) {
            const cb = label.querySelector('input[type=checkbox]');
            cb.checked = !cb.checked;
            label.classList.toggle('checked', cb.checked);
        }

        function toggleTele() {
            const cb = document.getElementById('qe-telemedicina');
            setTeleState(!cb.checked);
        }
        function setTeleState(on) {
            const cb   = document.getElementById('qe-telemedicina');
            const wrap = document.getElementById('qe-tele-wrap');
            const txt  = document.getElementById('qe-tele-text');
            cb.checked = on;
            wrap.classList.toggle('on', on);
            txt.textContent = on ? '📹 Atiende por telemedicina' : 'No atiende por telemedicina';
        }

        // Cerrar con Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeQuickEditDirect();
        });

        /* ══════════════════════════════════════════════
           RICH TEXT EDITOR (RTE)
           Colores CENEA: azul #1565C0, dorado #B8860B,
           negro #111111, gris #757575, blanco #FFFFFF
        ══════════════════════════════════════════════ */
        (function() {
            const COLORS = [
                { label: 'Azul',   value: '#1565C0' },
                { label: 'Dorado', value: '#B8860B' },
                { label: 'Negro',  value: '#111111' },
                { label: 'Gris',   value: '#757575' },
                { label: 'Blanco', value: '#FFFFFF' },
            ];
            const HIGHLIGHTS = [
                { label: 'Azul',   value: '#BBDEFB' },
                { label: 'Dorado', value: '#FFF8DC' },
                { label: 'Negro',  value: '#333333' },
                { label: 'Gris',   value: '#E0E0E0' },
                { label: 'Blanco', value: '#FFFFFF' },
            ];

            function buildToolbar(rteWrap, body, hiddenTA) {
                const tb = document.createElement('div');
                tb.className = 'rte-toolbar';

                function btn(label, cmd, title) {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'rte-btn';
                    b.dataset.cmd = cmd;
                    b.textContent = label;
                    b.title = title || label;
                    b.addEventListener('mousedown', function(e) {
                        e.preventDefault();
                        body.focus();
                        document.execCommand(cmd, false, null);
                        syncHidden(body, hiddenTA);
                        updateToolbarState(tb, body);
                    });
                    return b;
                }

                function sep() {
                    const s = document.createElement('span');
                    s.className = 'rte-sep';
                    return s;
                }

                // Formato básico
                tb.appendChild(btn('N', 'bold', 'Negrita'));
                tb.appendChild(btn('C', 'italic', 'Cursiva'));
                tb.appendChild(btn('S', 'underline', 'Subrayado'));
                tb.appendChild(sep());

                // Color de texto
                const colorLabel = document.createElement('span');
                colorLabel.className = 'rte-color-label';
                colorLabel.textContent = 'Color:';
                tb.appendChild(colorLabel);

                const colorGrp = document.createElement('div');
                colorGrp.className = 'rte-color-group';
                COLORS.forEach(c => {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'rte-color-btn';
                    b.style.background = c.value;
                    b.style.border = c.value === '#FFFFFF' ? '2px solid #ccc' : '2px solid transparent';
                    b.title = 'Color ' + c.label;
                    b.addEventListener('mousedown', function(e) {
                        e.preventDefault();
                        body.focus();
                        document.execCommand('foreColor', false, c.value);
                        syncHidden(body, hiddenTA);
                    });
                    colorGrp.appendChild(b);
                });
                tb.appendChild(colorGrp);
                tb.appendChild(sep());

                // Marcador de fondo
                const hlLabel = document.createElement('span');
                hlLabel.className = 'rte-color-label';
                hlLabel.textContent = 'Marcador:';
                tb.appendChild(hlLabel);

                const hlGrp = document.createElement('div');
                hlGrp.className = 'rte-highlight-group';
                HIGHLIGHTS.forEach(c => {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'rte-color-btn';
                    b.style.background = c.value;
                    b.style.border = c.value === '#FFFFFF' ? '2px solid #ccc' : '2px solid transparent';
                    b.title = 'Marcador ' + c.label;
                    // Usar hiliteColor o backColor según soporte
                    b.addEventListener('mousedown', function(e) {
                        e.preventDefault();
                        body.focus();
                        // hiliteColor es estándar; backColor como fallback
                        try { document.execCommand('hiliteColor', false, c.value); }
                        catch(ex) { document.execCommand('backColor', false, c.value); }
                        syncHidden(body, hiddenTA);
                    });
                    hlGrp.appendChild(b);
                });
                tb.appendChild(hlGrp);
                tb.appendChild(sep());

                // Limpiar formato
                const clearBtn = document.createElement('button');
                clearBtn.type = 'button';
                clearBtn.className = 'rte-btn';
                clearBtn.textContent = '✕ Limpiar';
                clearBtn.title = 'Quitar todo el formato';
                clearBtn.style.fontWeight = 'normal';
                clearBtn.style.color = '#888';
                clearBtn.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    body.focus();
                    document.execCommand('removeFormat', false, null);
                    syncHidden(body, hiddenTA);
                    updateToolbarState(tb, body);
                });
                tb.appendChild(clearBtn);

                return tb;
            }

            function syncHidden(body, hiddenTA) {
                // Guardar innerHTML limpio en el textarea oculto
                hiddenTA.value = body.innerHTML;
            }

            function updateToolbarState(tb, body) {
                tb.querySelectorAll('.rte-btn[data-cmd]').forEach(b => {
                    try {
                        b.classList.toggle('active', document.queryCommandState(b.dataset.cmd));
                    } catch(e) {}
                });
            }

            function initRTE(wrapper) {
                // wrapper es un div.rte-wrap que contiene un textarea.rte-hidden
                const hiddenTA = wrapper.querySelector('textarea.rte-hidden');
                if (!hiddenTA) return;

                const body = document.createElement('div');
                body.className = 'rte-body';
                body.contentEditable = 'true';
                body.spellcheck = true;
                body.lang = 'es-CL';
                const ph = hiddenTA.placeholder || hiddenTA.dataset.placeholder || '';
                if (ph) body.dataset.placeholder = ph;

                // Aplicar altura mínima desde atributo data-min-height
                const minH = parseInt(hiddenTA.dataset.minHeight) || 90;
                body.style.minHeight = minH + 'px';

                // Cargar contenido inicial desde el textarea
                const init = hiddenTA.value || '';
                body.innerHTML = init;

                const tb = buildToolbar(wrapper, body, hiddenTA);
                wrapper.appendChild(tb);
                wrapper.appendChild(body);

                // En modo inline: bloquear Enter para evitar saltos de línea
                if (hiddenTA.dataset.inline === '1') {
                    body.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter') { e.preventDefault(); }
                    });
                }

                // Sincronizar en cada cambio
                body.addEventListener('input', () => syncHidden(body, hiddenTA));
                body.addEventListener('keyup', () => syncHidden(body, hiddenTA));

                // Actualizar estado de botones al cambiar selección
                body.addEventListener('keyup', () => updateToolbarState(tb, body));
                body.addEventListener('mouseup', () => updateToolbarState(tb, body));
                body.addEventListener('selectionchange', () => updateToolbarState(tb, body));

                // Antes de submit: sincronizar
                const form = hiddenTA.closest('form');
                if (form) {
                    form.addEventListener('submit', () => syncHidden(body, hiddenTA), { capture: true });
                }
            }

            // Inicializar todos los editores al cargar
            document.addEventListener('DOMContentLoaded', function() {
                document.querySelectorAll('.rte-wrap').forEach(initRTE);
            });

            // Exponer para uso externo (ej: editProfile)
            window.rteSetContent = function(wrapId, html) {
                const wrap = document.getElementById(wrapId);
                if (!wrap) return;
                const body = wrap.querySelector('.rte-body');
                const ta   = wrap.querySelector('textarea.rte-hidden');
                if (body) body.innerHTML = html;
                if (ta) ta.value = html;
            };
        })();
    </script>
</body>
</html>
