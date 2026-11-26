<?php
/**
 * contacto.php – Endpoint POST para procesar el formulario de contacto Ramsol
 */
define('RS_API', true);
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/email-template.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// 1. Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rs_json(['ok' => false, 'error' => 'Método no permitido'], 405);
}

$config = rs_config();
$ip = rs_client_ip($config);

// 2. Validar tamaño del cuerpo (máx 20KB = 20480 bytes)
$content_length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($content_length > 20480) {
    rs_log('rejected_size', ['length' => $content_length, 'ip' => $ip]);
    rs_json(['ok' => false, 'error' => 'El tamaño de la petición excede el límite permitido'], 413);
}

// 3. Validar Origin / Referer contra allowed_hosts
if (!rs_check_origin($config)) {
    rs_log('rejected_origin', ['origin' => $_SERVER['HTTP_ORIGIN'] ?? '', 'referer' => $_SERVER['HTTP_REFERER'] ?? '', 'ip' => $ip]);
    rs_json(['ok' => false, 'error' => 'Origen de la solicitud no permitido'], 403);
}

// 4. Cabecera X-Requested-With: fetch requerida
$requested_with = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
if (strtolower($requested_with) !== 'fetch') {
    rs_log('rejected_header', ['header' => $requested_with, 'ip' => $ip]);
    rs_json(['ok' => false, 'error' => 'Cabecera de solicitud inválida'], 400);
}

// Función helper para respuesta fingida de spam (código 200 'enviado' silencioso)
function rs_spam_response(array $config, string $reason, array $context = []): void {
    $folio = rs_folio($config['email_brand']['folio_prefix'] ?? 'RS');
    rs_log('spam_blocked', array_merge(['reason' => $reason, 'folio' => $folio], $context));
    rs_json([
        'ok'      => true,
        'status'  => 'enviado',
        'folio'   => $folio,
        'mensaje' => '¡Mensaje enviado con éxito! Nos comunicaremos contigo pronto.',
    ], 200);
}

// 5. Honeypot: rs_hp_k7 debe estar vacío
$honeypot = $_POST['rs_hp_k7'] ?? '';
if (!empty($honeypot)) {
    rs_spam_response($config, 'honeypot_triggered', ['ip' => $ip]);
}

// 6. Token anti-CSRF y temporizador
$token = $_POST['token'] ?? '';
if (empty($token)) {
    rs_log('token_missing', ['ip' => $ip]);
    rs_json(['ok' => false, 'error' => 'Token de seguridad faltante'], 400);
}

$min_fill = (int)($config['min_fill_seconds'] ?? 4);
$max_ttl  = (int)($config['token_ttl'] ?? 7200);
$token_res = rs_token_verify($token, $config['token_secret'], $ip, $min_fill, $max_ttl);
if (!$token_res['valid']) {
    if ($token_res['error'] === 'muy_rapido') {
        rs_spam_response($config, 'token_too_fast', ['ip' => $ip]);
    }
    rs_log('token_invalid', ['error' => $token_res['error'], 'ip' => $ip]);
    rs_json(['ok' => false, 'error' => 'Token de seguridad inválido o expirado. Recarga la página.'], 400);
}

// 7. Rate limit por IP
$rate = rs_rate_check($ip, $config);
if (!$rate['allowed']) {
    rs_log('rate_limit_exceeded', ['type' => $rate['error'], 'ip' => $ip]);
    rs_spam_response($config, 'rate_limit', ['type' => $rate['error'], 'ip' => $ip]);
}

// 8. Captcha
$captcha_mode = $config['captcha_mode'] ?? 'imagen';
if ($captcha_mode === 'imagen') {
    $captcha_id  = $_POST['captcha_id'] ?? '';
    $captcha_ans = trim($_POST['captcha_ans'] ?? '');

    if (empty($captcha_id) || empty($captcha_ans)) {
        rs_json(['ok' => false, 'error' => 'Debes responder el código captcha de seguridad.'], 400);
    }

    $decoded_cap = rs_unb64($captcha_id);
    $cap_parts = explode('.', $decoded_cap);
    if (count($cap_parts) !== 2) {
        rs_json(['ok' => false, 'error' => 'Captcha inválido. Por favor recarga.'], 400);
    }

    [$nonce, $hash] = $cap_parts;
    $expected_hash = rs_captcha_hash($captcha_ans, $nonce, $config['token_secret']);

    if (!hash_equals($expected_hash, $hash)) {
        rs_log('captcha_failed', ['ip' => $ip]);
        rs_json(['ok' => false, 'error' => 'El código captcha ingresado es incorrecto.'], 400);
    }
}

// 9. Sanitización y validación de campos
$nombre  = rs_sanitize($_POST['nombre'] ?? '', 100);
$email   = trim($_POST['email'] ?? '');
$asunto  = rs_sanitize($_POST['asunto'] ?? '', 150);
$mensaje = rs_sanitize($_POST['mensaje'] ?? '', 2000);

if (mb_strlen($nombre, 'UTF-8') < 2 || mb_strlen($nombre, 'UTF-8') > 100) {
    rs_json(['ok' => false, 'error' => 'El nombre debe tener entre 2 y 100 caracteres.'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email, 'UTF-8') > 150) {
    rs_json(['ok' => false, 'error' => 'El correo electrónico no tiene un formato válido.'], 400);
}

if (empty($asunto) || mb_strlen($asunto, 'UTF-8') > 150) {
    rs_json(['ok' => false, 'error' => 'El asunto es obligatorio (máximo 150 caracteres).'], 400);
}

// 10. Filtros de contenido contra spam
// a) Bloquear enlaces en nombre y asunto
$url_pattern = '/(https?:\/\/|www\.)[a-z0-9\-._~:\/?#\[\]@!$&\'()*+,;=]+/i';
if (preg_match($url_pattern, $nombre) || preg_match($url_pattern, $asunto)) {
    rs_spam_response($config, 'links_in_name_or_subject', ['ip' => $ip, 'nombre' => $nombre, 'asunto' => $asunto]);
}

// b) Máximo de enlaces permitidos en mensaje
$max_links = (int)($config['max_links'] ?? 1);
$link_count = preg_match_all($url_pattern, $mensaje);
if ($link_count > $max_links) {
    rs_spam_response($config, 'too_many_links', ['ip' => $ip, 'count' => $link_count]);
}

// c) Palabras bloqueadas (blocked_words)
$blocked = $config['blocked_words'] ?? [];
$all_text = mb_strtolower($nombre . ' ' . $asunto . ' ' . $mensaje, 'UTF-8');
foreach ($blocked as $bw) {
    $bw_clean = trim(mb_strtolower($bw, 'UTF-8'));
    if ($bw_clean !== '' && strpos($all_text, $bw_clean) !== false) {
        rs_spam_response($config, 'blocked_word_match', ['ip' => $ip, 'word' => $bw_clean]);
    }
}

// 11. Generar Folio único
$prefix = $config['email_brand']['folio_prefix'] ?? 'RS';
$folio  = rs_folio($prefix);

// Obtener página de origen
$pagina_origen = $_SERVER['HTTP_REFERER'] ?? 'https://ramsol.com/tiendas-showroom.html';

// 12. Construir correos
$email_data = [
    'folio'         => $folio,
    'nombre'        => $nombre,
    'email'         => $email,
    'asunto'        => $asunto,
    'mensaje'       => $mensaje,
    'pagina_origen' => $pagina_origen,
];
$email_content = rs_email_notification($email_data, $config);

// 13. Comprobar MODO_PRUEBA (< 10 minutos de antigüedad)
$modo_prueba_files = [
    __DIR__ . '/MODO_PRUEBA',
    __DIR__ . '/data/MODO_PRUEBA',
    dirname(__DIR__) . '/MODO_PRUEBA',
];
$es_modo_prueba = false;
foreach ($modo_prueba_files as $mpf) {
    if (file_exists($mpf) && (time() - filemtime($mpf)) < 600) {
        $es_modo_prueba = true;
        break;
    }
}

if ($es_modo_prueba) {
    rs_log('dry_run_contacto', [
        'folio'  => $folio,
        'nombre' => $nombre,
        'email'  => $email,
        'asunto' => $asunto,
        'ip'     => $ip,
        'modo'   => 'MODO_PRUEBA activo',
    ]);
    rs_json([
        'ok'      => true,
        'status'  => 'enviado',
        'folio'   => $folio,
        'mensaje' => '¡Mensaje recibido en modo prueba! Folio: ' . $folio,
        'dry_run' => true,
    ], 200);
}

// 14. Enviar mediante Brevo API v3
$brevo_key = $config['brevo_api_key'] ?? '';
if (empty($brevo_key)) {
    rs_log('brevo_missing_key', ['folio' => $folio, 'ip' => $ip]);
    rs_json([
        'ok'      => true,
        'status'  => 'enviado',
        'folio'   => $folio,
        'mensaje' => '¡Mensaje recibido con éxito! Nos comunicaremos contigo pronto.',
    ], 200);
}

// Configurar destinatarios
$recipients_list = [];
foreach ($config['recipients'] as $rcpt) {
    $recipients_list[] = ['email' => trim($rcpt)];
}

$payload = [
    'sender' => [
        'name'  => $config['sender_name'] ?? 'Ramsol · Sitio web',
        'email' => $config['sender_email'] ?? 'no-reply@ramsol.com.mx',
    ],
    'to' => $recipients_list,
    'replyTo' => [
        'name'  => $nombre,
        'email' => $email,
    ],
    'subject'     => ($config['subject_prefix'] ?? 'Ramsol | ') . $asunto . ' [' . $folio . ']',
    'htmlContent' => $email_content['html'],
    'textContent' => $email_content['text'],
];

$ch = curl_init('https://api.brevo.com/v3/smtp/email');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => [
        'api-key: ' . $brevo_key,
        'Content-Type: application/json',
        'Accept: application/json',
    ],
]);

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_error($ch);
curl_close($ch);

if ($http_code >= 200 && $http_code < 300) {
    rs_log('email_sent', ['folio' => $folio, 'ip' => $ip, 'brevo_code' => $http_code]);
    rs_json([
        'ok'      => true,
        'status'  => 'enviado',
        'folio'   => $folio,
        'mensaje' => '¡Mensaje enviado con éxito! Nos comunicaremos contigo pronto.',
    ], 200);
} else {
    rs_log('brevo_error', ['folio' => $folio, 'code' => $http_code, 'err' => $curl_err, 'response' => $response, 'ip' => $ip]);
    rs_json([
        'ok'    => false,
        'error' => 'No fue posible enviar el mensaje por el momento. Por favor intenta más tarde.',
    ], 500);
}
