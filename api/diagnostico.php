<?php
/**
 * diagnostico.php – Endpoint GET para validar el entorno y servicios del API Ramsol
 * Requiere parámetro ?clave=<diag_key>
 */
define('RS_API', true);
require_once __DIR__ . '/lib.php';

header('Content-Type: text/html; charset=utf-8');

$config = rs_config();
$diag_key = $config['diag_key'] ?? '';

// Validar clave de acceso
$input_key = $_GET['clave'] ?? '';
if (empty($diag_key) || !hash_equals($diag_key, $input_key)) {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>403 Prohibido</title></head>';
    echo '<body style="font-family:sans-serif; text-align:center; padding:50px;">';
    echo '<h1 style="color:#d63e2d;">Acceso Denegado</h1>';
    echo '<p>El diagnóstico está deshabilitado o la clave proporcionada es incorrecta.</p>';
    echo '</body></html>';
    exit;
}

// 1. Verificaciones del sistema
$php_version = PHP_VERSION;
$required_extensions = ['curl', 'gd', 'mbstring', 'json', 'openssl'];
$ext_results = [];
foreach ($required_extensions as $ext) {
    $ext_results[$ext] = extension_loaded($ext);
}

// 2. Permisos en directorio data/
$dirs_to_check = [
    'data'           => __DIR__ . '/data',
    'data/logs'      => __DIR__ . '/data/logs',
    'data/nonces'    => __DIR__ . '/data/nonces',
    'data/ratelimit' => __DIR__ . '/data/ratelimit',
];
$write_results = [];
foreach ($dirs_to_check as $name => $path) {
    if (!is_dir($path)) {
        $write_results[$name] = ['status' => false, 'msg' => 'No existe'];
        continue;
    }
    $test_file = $path . '/test_' . bin2hex(random_bytes(4)) . '.tmp';
    $can_write = @file_put_contents($test_file, 'test') !== false;
    if ($can_write) {
        @unlink($test_file);
        $write_results[$name] = ['status' => true, 'msg' => 'Lectura / Escritura OK'];
    } else {
        $write_results[$name] = ['status' => false, 'msg' => 'Sin permisos de escritura'];
    }
}

// 3. IP Pública del servidor
$public_ip = 'No disponible';
$ch_ip = curl_init('https://api.ipify.org');
curl_setopt_array($ch_ip, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 3,
]);
$ip_res = curl_exec($ch_ip);
if ($ip_res && filter_var(trim($ip_res), FILTER_VALIDATE_IP)) {
    $public_ip = trim($ip_res);
}
curl_close($ch_ip);

// 4. Conexión con Brevo API
$brevo_key = $config['brevo_api_key'] ?? '';
$brevo_account = null;
$brevo_senders = null;
$brevo_status_msg = 'No configurado';
$brevo_ok = false;

if (!empty($brevo_key)) {
    // Test cuenta
    $ch_acc = curl_init('https://api.brevo.com/v3/account');
    curl_setopt_array($ch_acc, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => [
            'api-key: ' . $brevo_key,
            'Accept: application/json',
        ],
    ]);
    $acc_res = curl_exec($ch_acc);
    $acc_code = curl_getinfo($ch_acc, CURLINFO_HTTP_CODE);
    curl_close($ch_acc);

    if ($acc_code === 200) {
        $brevo_account = json_decode($acc_res, true);
        $brevo_ok = true;
        $brevo_status_msg = 'Conectado exitosamente (HTTP 200)';
    } else {
        $brevo_status_msg = "Error de autenticación Brevo (HTTP {$acc_code}): " . substr($acc_res, 0, 150);
    }

    // Obtener remitentes autorizados
    $ch_send = curl_init('https://api.brevo.com/v3/senders');
    curl_setopt_array($ch_send, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => [
            'api-key: ' . $brevo_key,
            'Accept: application/json',
        ],
    ]);
    $send_res = curl_exec($ch_send);
    $send_code = curl_getinfo($ch_send, CURLINFO_HTTP_CODE);
    curl_close($ch_send);

    if ($send_code === 200) {
        $send_data = json_decode($send_res, true);
        $brevo_senders = $send_data['senders'] ?? [];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Diagnóstico Ramsol API</title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background:#f7f9fa; color:#2c3e50; margin:0; padding:30px 20px; }
  .container { max-width: 800px; margin: 0 auto; background: #fff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); overflow: hidden; }
  .header { background: #111; color: #fff; padding: 25px 30px; border-bottom: 4px solid #d63e2d; }
  .header h1 { margin: 0 0 5px 0; font-size: 22px; }
  .header p { margin: 0; font-size: 13px; color: #aaa; }
  .content { padding: 30px; }
  .section { margin-bottom: 30px; }
  .section h2 { font-size: 16px; text-transform: uppercase; letter-spacing: 0.5px; color: #7f8c8d; margin: 0 0 15px 0; border-bottom: 1px solid #ecf0f1; padding-bottom: 8px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
  td, th { padding: 10px 12px; text-align: left; font-size: 14px; border-bottom: 1px solid #f1f2f6; }
  th { background: #fafafa; font-weight: 600; color: #555; }
  .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
  .badge-ok { background: #e8f8f5; color: #27ae60; }
  .badge-err { background: #fdedec; color: #e74c3c; }
  .badge-warn { background: #fef9e7; color: #f39c12; }
  .code { font-family: monospace; background: #f4f6f7; padding: 2px 6px; border-radius: 3px; font-size: 13px; }
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <h1>Ramsol API · Diagnóstico de Sistema</h1>
    <p>Fecha de verificación: <?= date('Y-m-d H:i:s') ?> · SAPI: <?= PHP_SAPI ?></p>
  </div>
  <div class="content">

    <!-- Servidor & PHP -->
    <div class="section">
      <h2>Entorno del Servidor</h2>
      <table>
        <tr><td style="width:220px;">Versión de PHP</td><td><span class="code"><?= $php_version ?></span> <?= version_compare($php_version, '7.4.0', '>=') ? '<span class="badge badge-ok">OK</span>' : '<span class="badge badge-err">Actualización requerida</span>' ?></td></tr>
        <tr><td>Sistema Operativo</td><td><span class="code"><?= PHP_OS ?></span></td></tr>
        <tr><td>IP Pública del Servidor</td><td><span class="code"><?= htmlspecialchars($public_ip) ?></span></td></tr>
        <tr><td>Software Web</td><td><span class="code"><?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') ?></span></td></tr>
      </table>
    </div>

    <!-- Extensiones PHP -->
    <div class="section">
      <h2>Extensiones PHP Requeridas</h2>
      <table>
        <?php foreach ($ext_results as $ext => $loaded): ?>
        <tr>
          <td style="width:220px;"><span class="code"><?= $ext ?></span></td>
          <td><?= $loaded ? '<span class="badge badge-ok">Cargada</span>' : '<span class="badge badge-err">Faltante</span>' ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>

    <!-- Permisos de escritura -->
    <div class="section">
      <h2>Permisos de Almacenamiento (data/)</h2>
      <table>
        <?php foreach ($write_results as $dir => $info): ?>
        <tr>
          <td style="width:220px;"><span class="code"><?= $dir ?></span></td>
          <td><span class="badge <?= $info['status'] ? 'badge-ok' : 'badge-err' ?>"><?= $info['msg'] ?></span></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>

    <!-- Brevo API -->
    <div class="section">
      <h2>Conectividad con Brevo API</h2>
      <table>
        <tr>
          <td style="width:220px;">Estado de conexión</td>
          <td><span class="badge <?= $brevo_ok ? 'badge-ok' : ($brevo_key ? 'badge-err' : 'badge-warn') ?>"><?= htmlspecialchars($brevo_status_msg) ?></span></td>
        </tr>
        <?php if ($brevo_account): ?>
        <tr>
          <td>Cuenta Brevo</td>
          <td><strong><?= htmlspecialchars($brevo_account['email'] ?? '') ?></strong> (<?= htmlspecialchars($brevo_account['companyName'] ?? '') ?>)</td>
        </tr>
        <tr>
          <td>Créditos SMTP</td>
          <td>
            <?php
            $plan = $brevo_account['plan'] ?? [];
            foreach ($plan as $p) {
                echo htmlspecialchars($p['type'] ?? '') . ': ' . ($p['credits'] ?? 0) . ' créditos<br>';
            }
            ?>
          </td>
        </tr>
        <?php endif; ?>
        <?php if ($brevo_senders !== null): ?>
        <tr>
          <td>Remitentes verificados</td>
          <td>
            <?php if (empty($brevo_senders)): ?>
              <em>No hay remitentes configurados.</em>
            <?php else: ?>
              <ul style="margin:0; padding-left:20px;">
                <?php foreach ($brevo_senders as $s): ?>
                  <li><strong><?= htmlspecialchars($s['name'] ?? '') ?></strong> &lt;<?= htmlspecialchars($s['email'] ?? '') ?>&gt; <?= !empty($s['active']) ? '<span class="badge badge-ok">Activo</span>' : '<span class="badge badge-err">Inactivo</span>' ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </td>
        </tr>
        <?php endif; ?>
      </table>
    </div>

  </div>
</div>
</body>
</html>
