<?php
/**
 * token.php – Endpoint GET para generar token anti-CSRF
 */
require_once __DIR__ . '/lib.php';

// Solo GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    rs_json(['error' => 'método no permitido'], 405);
}

$config = rs_config();

// Verificar origen
if (!rs_check_origin($config)) {
    rs_json(['error' => 'origen no permitido'], 403);
}

$ip = rs_client_ip($config);
$token = rs_token_create($config['token_secret'], $ip);

header('Access-Control-Allow-Origin: *');
rs_json([
    'token'        => $token,
    'captcha_mode' => $config['captcha_mode'] ?? 'imagen',
]);
