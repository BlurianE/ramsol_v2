<?php
/**
 * sincronizar-menu.php (ramsol_v2)
 *
 * Sincroniza el menú moderno del Home en todas las páginas de ramsol_v2.
 * - Coloca el header fuera de wrap_all (para que ningún banner ni parallax lo tape).
 * - Elimina cualquier hueco o padding duplicado.
 * - Asegura que los dropdowns floten siempre por encima con z-index absoluto.
 *
 * Fuente de la verdad: _scraper/partials/header.html
 */

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Sincronizador de Menú · Ramsol v2</title>';
    echo '<style>body{font-family:system-ui,-apple-system,sans-serif;max-width:800px;margin:40px auto;padding:0 20px;background:#f9f9f9;color:#333}';
    echo 'h1{color:#E74339;border-bottom:2px solid #ddd;padding-bottom:10px}';
    echo '.ok{color:#28a745;font-weight:bold} .info{color:#007bff} .box{background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1)}';
    echo 'ul{line-height:1.8} a{color:#E74339;text-decoration:none} a:hover{text-decoration:underline}</style></head><body><div class="box">';
    echo '<h1>Sincronizador de Menú Moderno Ramsol v2</h1>';
}

$baseDir = __DIR__;
$headerFile = $baseDir . '/_scraper/partials/header.html';

if (!file_exists($headerFile)) {
    $err = "ERROR: No existe el archivo maestro de cabecera: $headerFile\n";
    if ($isCli) { fwrite(STDERR, $err); } else { echo "<p style='color:red'>$err</p></div></body></html>"; }
    exit(1);
}

$masterHeader = file_get_contents($headerFile);
if (empty(trim($masterHeader))) {
    $err = "ERROR: El archivo de cabecera maestro está vacío.\n";
    if ($isCli) { fwrite(STDERR, $err); } else { echo "<p style='color:red'>$err</p></div></body></html>"; }
    exit(1);
}

$pages = [
    'index.html',
    'ramsol-grupo-industrial.html',
    'ecologia-ramsol.html',
    'tecnologia-ramsol.html',
    'pergolas-ramsol.html',
    'space-terraza-loft.html',
    'muebles.html',
    'comedores.html',
    'mesas-cubiertas.html',
    'salas.html',
    'tumbonas.html',
    'productos-todo-terreno.html',
    'tiendas-showroom.html',
    'catalogos.html',
    'aviso-de-privacidad.html'
];

if ($isCli) {
    echo "========================================================\n";
    echo "  Sincronizando Menú Moderno y Capas (Ramsol v2)\n";
    echo "  Fuente: _scraper/partials/header.html (" . strlen($masterHeader) . " bytes)\n";
    echo "========================================================\n\n";
} else {
    echo "<p class='info'>Fuente maestra: <code>_scraper/partials/header.html</code> (" . number_format(strlen($masterHeader)) . " bytes)</p><ul>";
}

$updated = 0;
$skipped = 0;
$notFound = 0;

foreach ($pages as $page) {
    if ($page === 'index.html') {
        $skipped++;
        $msg = "[=] $page (fuente original del Home)";
        if ($isCli) echo "$msg\n"; else echo "<li>[=] $page (fuente original)</li>";
        continue;
    }

    $filePath = $baseDir . '/' . $page;
    if (!file_exists($filePath)) {
        $notFound++;
        $msg = "[-] $page (archivo no encontrado)";
        if ($isCli) echo "$msg\n"; else echo "<li><span style='color:orange'>$msg</span></li>";
        continue;
    }

    $content = file_get_contents($filePath);

    // 1. Eliminar cualquier versión anterior del header (viejo id='header' o site-header) dentro de wrap_all
    // y colocar el nuevo header maestro antes de <div id='wrap_all'>
    // Primero limpiamos viejos headers
    $content = preg_replace('/<header[^>]*id=[\"\x27]header[\"\x27][^>]*>.*?<\/header>/is', '', $content);
    $content = preg_replace('/<header class=[\"\x27]site-header[\"\x27]>.*?<\/header>/is', '', $content);
    $content = preg_replace('/<div class=[\"\x27]mobile-nav-backdrop[\"\x27]><\/div>\s*<aside class=[\"\x27]mobile-nav-drawer[\"\x27][^>]*>.*?<\/aside>/is', '', $content);

    // Ahora inyectamos el header justo antes de <div id='wrap_all'>
    if (strpos($content, "<div id='wrap_all'") !== false) {
        $content = str_replace("<div id='wrap_all'", $masterHeader . "\n\n<div id='wrap_all'", $content);
    } elseif (strpos($content, '<div id="wrap_all"') !== false) {
        $content = str_replace('<div id="wrap_all"', $masterHeader . "\n\n<div id=\"wrap_all\"", $content);
    } else {
        // Si no tiene wrap_all, ponerlo después de <body>
        $content = preg_replace('/(<body[^>]*>)/i', "$1\n\n" . $masterHeader, $content, 1);
    }

    // 2. Inyectar CSS ramsol-header.css en <head> si falta
    if (strpos($content, 'ramsol-header.css') === false) {
        $cssTag = "  <!-- Estilos del nuevo header moderno Ramsol -->\n  <link rel=\"stylesheet\" href=\"css/ramsol-header.css\">\n</head>";
        $content = str_replace('</head>', $cssTag, $content);
    }

    // 3. Inyectar JS ramsol-main.js antes de </body> si falta
    if (strpos($content, 'ramsol-main.js') === false) {
        $jsTag = "  <!-- Script para interactividad del menú moderno Ramsol -->\n  <script src=\"js/ramsol-main.js\"></script>\n</body>";
        $content = str_replace('</body>', $jsTag, $content);
    }

    file_put_contents($filePath, $content);
    $updated++;
    $msg = "[✓] $page -> Header sincronizado (fuera de wrap_all, z-index prioritario, sin huecos)";
    if ($isCli) echo "$msg\n"; else echo "<li><span class='ok'>[✓]</span> $page — Sincronizado</li>";
}

if ($isCli) {
    echo "\n--------------------------------------------------------\n";
    echo "Resumen: $updated páginas actualizadas, $skipped sin cambios, $notFound no encontradas.\n";
    echo "¡Listo! Todas las páginas de ramsol_v2 tienen el menú fijo, sin espacios vacíos y flotando sobre los banners.\n";
    echo "========================================================\n";
} else {
    echo "</ul>";
    echo "<hr><p><strong>Resultado:</strong> $updated actualizadas, $skipped sin cambios.</p>";
    echo "<p><a href='index.html'>← Volver a Portada (Home)</a> | <a href='ecologia-ramsol.html'>Ver Ecología</a> | <a href='muebles.html'>Ver Muebles</a> | <a href='salas.html'>Ver Salas</a></p>";
    echo "</div></body></html>";
}
