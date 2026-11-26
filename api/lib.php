<?php
/**
 * lib.php – Funciones compartidas del API de contacto Ramsol
 * Todas las funciones llevan el prefijo rs_
 */
defined('RS_API') || define('RS_API', true);

// Cargar configuración (desde config.php si existe, o config.example.php como respaldo)
if (!function_exists('rs_config')) {
    $cfg_file = file_exists(__DIR__ . '/config.php') ? __DIR__ . '/config.php' : __DIR__ . '/config.example.php';
    if (file_exists($cfg_file)) {
        require_once $cfg_file;
    }
}

if (!function_exists('rs_config')) {
    function rs_config(): array {
        static $cfg = null;
        if ($cfg === null) {
            $file = file_exists(__DIR__ . '/config.php') ? __DIR__ . '/config.php' : __DIR__ . '/config.example.php';
            $ret = require $file;
            $cfg = is_array($ret) ? $ret : [];
        }
        return $cfg;
    }
}

/**
 * Genera un folio único: PREFIJO-AAMMDD-HEX6
 */
function rs_folio(string $prefix = 'RS'): string {
    return $prefix . '-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/**
 * Decodifica base64 URL-safe
 */
function rs_unb64(string $str): string {
    return base64_decode(strtr($str, '-_', '+/'));
}

/**
 * Codifica base64 URL-safe
 */
function rs_b64(string $str): string {
    return rtrim(strtr(base64_encode($str), '+/', '-_'), '=');
}

/**
 * Crea un token firmado con HMAC
 * Formato: base64({timestamp}.{nonce}.{ip_hash}).{signature})
 */
function rs_token_create(string $secret, string $ip): string {
    $ts    = time();
    $nonce = bin2hex(random_bytes(16));
    $iphash = hash('sha256', $ip);
    $payload = $ts . '.' . $nonce . '.' . $iphash;
    $sig = hash_hmac('sha256', $payload, $secret);
    return rs_b64($payload . '.' . $sig);
}

/**
 * Verifica un token: firma, IP, ventana de tiempo y uso único
 */
function rs_token_verify(string $token, string $secret, string $ip, int $min_seconds, int $max_seconds): array {
    $decoded = rs_unb64($token);
    $parts = explode('.', $decoded);
    if (count($parts) !== 4) {
        return ['valid' => false, 'error' => 'formato'];
    }
    [$ts, $nonce, $iphash, $sig] = $parts;

    // Verificar firma
    $payload = $ts . '.' . $nonce . '.' . $iphash;
    $expected = hash_hmac('sha256', $payload, $secret);
    if (!hash_equals($expected, $sig)) {
        return ['valid' => false, 'error' => 'firma'];
    }

    // Verificar IP
    if (!hash_equals($iphash, hash('sha256', $ip))) {
        return ['valid' => false, 'error' => 'ip'];
    }

    // Verificar ventana de tiempo
    $age = time() - (int)$ts;
    if ($age < $min_seconds) {
        return ['valid' => false, 'error' => 'muy_rapido'];
    }
    if ($age > $max_seconds) {
        return ['valid' => false, 'error' => 'expirado'];
    }

    // Verificar uso único (nonce)
    $nonce_dir = __DIR__ . '/data/nonces';
    $nonce_file = $nonce_dir . '/' . $nonce;
    if (file_exists($nonce_file)) {
        return ['valid' => false, 'error' => 'reuso'];
    }
    file_put_contents($nonce_file, $ts);

    // Limpiar nonces expirados (mayores a max_seconds)
    $files = glob($nonce_dir . '/*');
    if ($files) {
        foreach ($files as $f) {
            $content = @file_get_contents($f);
            if ($content !== false && (time() - (int)$content) > $max_seconds) {
                @unlink($f);
            }
        }
    }

    return ['valid' => true];
}

/**
 * Genera hash para verificación de captcha
 */
function rs_captcha_hash(string $answer, string $nonce): string {
    $config = rs_config();
    $secret = $config['token_secret'];
    return hash_hmac('sha256', $answer . '.' . $nonce, $secret);
}

/**
 * Verifica límites de tasa por IP
 * Regresa ['allowed' => bool, 'error' => string|null]
 */
function rs_rate_check(string $ip, array $config): array {
    $ip_hash = hash('sha256', $ip);
    $dir = __DIR__ . '/data/ratelimit';
    $file = $dir . '/' . $ip_hash . '.json';

    $now = time();
    $entries = [];

    if (file_exists($file)) {
        $data = @json_decode(file_get_contents($file), true);
        if (is_array($data)) {
            $entries = $data;
        }
    }

    // Limpiar entradas viejas (más de 24h)
    $entries = array_values(array_filter($entries, function ($t) use ($now) {
        return ($now - $t) < 86400;
    }));

    // Contar en ventanas
    $per_10min = $config['rate_limit']['per_10min'] ?? 3;
    $per_day   = $config['rate_limit']['per_day'] ?? 10;

    $recent_10min = count(array_filter($entries, function ($t) use ($now) {
        return ($now - $t) < 600;
    }));

    if ($recent_10min >= $per_10min) {
        return ['allowed' => false, 'error' => 'rate_10min'];
    }

    if (count($entries) >= $per_day) {
        return ['allowed' => false, 'error' => 'rate_day'];
    }

    // Registrar este acceso
    $entries[] = $now;
    file_put_contents($file, json_encode($entries), LOCK_EX);

    return ['allowed' => true, 'error' => null];
}

/**
 * Registra un evento en el log mensual
 */
function rs_log(string $event, array $data = []): void {
    $dir = __DIR__ . '/data/logs';
    $file = $dir . '/' . date('Y-m') . '.log';

    $ip = $data['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    // Cifrar IP con hash para privacidad
    $data['ip_hash'] = hash('sha256', $ip);
    unset($data['ip']);

    $entry = [
        'ts'    => date('c'),
        'event' => $event,
        'data'  => $data,
    ];

    file_put_contents($file, json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Sanitiza una cadena: trim, elimina caracteres de control, trunca
 */
function rs_sanitize(string $str, int $maxlen): string {
    $str = trim($str);
    // Eliminar caracteres de control excepto newline y tab
    $str = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $str);
    if (mb_strlen($str, 'UTF-8') > $maxlen) {
        $str = mb_substr($str, 0, $maxlen, 'UTF-8');
    }
    return $str;
}

/**
 * Verifica que el Origin o Referer esté en la lista de hosts permitidos
 */
function rs_check_origin(array $config): bool {
    $allowed = $config['allowed_hosts'] ?? [];

    // Revisar Origin
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin) {
        $host = parse_url($origin, PHP_URL_HOST);
        if ($host && in_array($host, $allowed, true)) {
            return true;
        }
    }

    // Revisar Referer
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if ($referer) {
        $host = parse_url($referer, PHP_URL_HOST);
        if ($host && in_array($host, $allowed, true)) {
            return true;
        }
    }

    return false;
}

/**
 * Obtiene la IP real del cliente
 */
function rs_client_ip(array $config): string {
    if (!empty($config['trust_proxy']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Envía respuesta JSON y termina
 */
function rs_json(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
