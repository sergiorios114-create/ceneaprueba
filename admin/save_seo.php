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

$section = $_POST['seo_section'] ?? '';
$validSections = ['global', 'index', 'quienes-somos', 'servicios', 'equipo-medico', 'contacto', 'reservar-doctor', 'reservar-especialidad'];

if (!in_array($section, $validSections)) {
    header('Location: dashboard.php?error=Sección+SEO+no+válida&tab=seo');
    exit;
}

$seoFile = '../data/seo.json';
$seo = json_decode(file_get_contents($seoFile), true);

if ($section === 'global') {
    // Campos globales planos
    $globalFields = ['site_name','site_url','locale','twitter_site','og_image','og_image_width','og_image_height','author','robots_global','theme_color'];
    foreach ($globalFields as $field) {
        if (isset($_POST[$field])) {
            $seo['global'][$field] = limpiarUTF8($_POST[$field]);
        }
    }
    // Sub-objeto schema_organization
    $orgFields = ['name','url','logo','description','telephone','telephone2','email',
                  'address_street','address_city','address_region','address_country','address_postal',
                  'latitude','longitude','opening_hours','price_range','facebook','instagram','linkedin'];
    foreach ($orgFields as $field) {
        $postKey = 'org_' . $field;
        if (isset($_POST[$postKey])) {
            $seo['global']['schema_organization'][$field] = limpiarUTF8($_POST[$postKey]);
        }
    }
} else {
    // Campos de página específica
    $pageFields = ['title','description','canonical','robots','og_title','og_description','og_image','og_type',
                   'twitter_card','twitter_title','twitter_description','schema_type'];
    foreach ($pageFields as $field) {
        if (isset($_POST[$field])) {
            $seo['pages'][$section][$field] = limpiarUTF8($_POST[$field]);
        }
    }
}

// Regenerar sitemap.xml automáticamente al guardar SEO
$sitemapContent = generateSitemap($seo);
file_put_contents('../sitemap.xml', $sitemapContent);

// Guardar SEO
if (file_put_contents($seoFile, json_encode($seo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))) {
    header("Location: dashboard.php?success=SEO+de+{$section}+actualizado+correctamente&tab=seo");
} else {
    header("Location: dashboard.php?error=Error+al+guardar+SEO&tab=seo");
}
exit;

function generateSitemap($seo) {
    $baseUrl = rtrim($seo['global']['site_url'] ?? 'https://cenea.cl', '/');
    $today = date('Y-m-d');
    $pages = [
        ['loc' => $baseUrl . '/',                           'priority' => '1.00', 'changefreq' => 'weekly'],
        ['loc' => $baseUrl . '/quienes-somos.html',         'priority' => '0.80', 'changefreq' => 'monthly'],
        ['loc' => $baseUrl . '/servicios.html',             'priority' => '0.90', 'changefreq' => 'monthly'],
        ['loc' => $baseUrl . '/equipo-medico.html',         'priority' => '0.90', 'changefreq' => 'weekly'],
        ['loc' => $baseUrl . '/contacto.html',              'priority' => '0.80', 'changefreq' => 'monthly'],
        ['loc' => $baseUrl . '/reservar-doctor.html',       'priority' => '0.85', 'changefreq' => 'weekly'],
        ['loc' => $baseUrl . '/reservar-especialidad.html', 'priority' => '0.85', 'changefreq' => 'weekly'],
    ];
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($pages as $p) {
        $xml .= "  <url>\n";
        $xml .= "    <loc>" . htmlspecialchars($p['loc'], ENT_XML1) . "</loc>\n";
        $xml .= "    <lastmod>{$today}</lastmod>\n";
        $xml .= "    <changefreq>{$p['changefreq']}</changefreq>\n";
        $xml .= "    <priority>{$p['priority']}</priority>\n";
        $xml .= "  </url>\n";
    }
    $xml .= '</urlset>';
    return $xml;
}
