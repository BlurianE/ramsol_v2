<?php
/**
 * captcha.php – Endpoint GET para generar captcha matemático con imagen
 */
require_once __DIR__ . '/lib.php';

// Solo GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    rs_json(['error' => 'método no permitido'], 405);
}

$config = rs_config();

// Generar operación: (10-49) + (1-9)
$a = random_int(10, 49);
$b = random_int(1, 9);
$answer = (string)($a + $b);

// Nonce para firmar
$nonce = bin2hex(random_bytes(16));

// Crear captcha_id: nonce.hash
$hash = rs_captcha_hash($answer, $nonce);
$captcha_id = rs_b64($nonce . '.' . $hash);

// Generar imagen PNG con GD
$width = 200;
$height = 60;
$img = imagecreatetruecolor($width, $height);

// Colores
$bg     = imagecolorallocate($img, 245, 245, 245);
$text_c = imagecolorallocate($img, 50, 50, 50);
$line_c = imagecolorallocate($img, 200, 200, 200);
$noise  = imagecolorallocate($img, 180, 180, 180);

// Fondo
imagefilledrectangle($img, 0, 0, $width - 1, $height - 1, $bg);

// Líneas de ruido
for ($i = 0; $i < 5; $i++) {
    imageline($img, random_int(0, $width), random_int(0, $height),
              random_int(0, $width), random_int(0, $height), $line_c);
}

// Puntos de ruido
for ($i = 0; $i < 100; $i++) {
    imagesetpixel($img, random_int(0, $width), random_int(0, $height), $noise);
}

// Texto de la operación
$text = "$a + $b = ?";
$font_size = 5; // Fuente GD integrada (1-5)
$text_width = imagefontwidth($font_size) * strlen($text);
$text_height = imagefontheight($font_size);
$x = ($width - $text_width) / 2;
$y = ($height - $text_height) / 2;

imagestring($img, $font_size, (int)$x, (int)$y, $text, $text_c);

// Capturar PNG en buffer
ob_start();
imagepng($img);
$png_data = ob_get_clean();
imagedestroy($img);

$image_b64 = 'data:image/png;base64,' . base64_encode($png_data);

header('Access-Control-Allow-Origin: *');
rs_json([
    'captcha_id'   => $captcha_id,
    'id'           => $captcha_id,
    'image'        => $image_b64,
    'captcha_mode' => $config['captcha_mode'] ?? 'imagen',
]);
