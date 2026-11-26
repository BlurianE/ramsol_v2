<?php
defined('RS_API') || exit;

/**
 * Genera fecha en formato largo en español
 */
function rs_fecha_espanol(int $ts = null): string {
    $ts = $ts ?? time();
    $meses = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
    ];
    $dia  = date('j', $ts);
    $mes  = $meses[(int)date('n', $ts)] ?? date('m', $ts);
    $anio = date('Y', $ts);
    $hora = date('H:i', $ts);
    return "{$dia} de {$mes} de {$anio}, {$hora} hrs";
}

/**
 * Genera el correo de notificación en HTML y texto plano
 *
 * @param array $data ['folio', 'nombre', 'email', 'asunto', 'mensaje', 'pagina_origen']
 * @param array $config
 * @return array ['html' => string, 'text' => string]
 */
function rs_email_notification(array $data, array $config): array {
    $folio         = htmlspecialchars($data['folio'] ?? 'RS-000000-000000', ENT_QUOTES, 'UTF-8');
    $nombre        = htmlspecialchars($data['nombre'] ?? '', ENT_QUOTES, 'UTF-8');
    $email         = htmlspecialchars($data['email'] ?? '', ENT_QUOTES, 'UTF-8');
    $asunto        = htmlspecialchars($data['asunto'] ?? 'Consulta general', ENT_QUOTES, 'UTF-8');
    $mensaje_raw   = $data['mensaje'] ?? '(Sin mensaje)';
    $mensaje_html  = nl2br(htmlspecialchars($mensaje_raw, ENT_QUOTES, 'UTF-8'));
    $pagina_origen = htmlspecialchars($data['pagina_origen'] ?? 'https://ramsol.com/', ENT_QUOTES, 'UTF-8');
    $fecha_str     = rs_fecha_espanol();

    $brand_name    = htmlspecialchars($config['email_brand']['name'] ?? 'ramsol.com', ENT_QUOTES, 'UTF-8');
    $brand_color   = htmlspecialchars($config['email_brand']['color'] ?? '#d63e2d', ENT_QUOTES, 'UTF-8');

    // Mailto pre-llenado para responder al interesado
    $mailto_subject = rawurlencode("RE: " . ($data['asunto'] ?? '') . " [Folio: " . ($data['folio'] ?? '') . "]");
    $mailto_body    = rawurlencode("Hola " . ($data['nombre'] ?? '') . ",\n\nGracias por comunicarte con Ramsol.\n\nRespecto a tu consulta:\n\n");
    $mailto_href    = "mailto:" . urlencode($data['email'] ?? '') . "?subject={$mailto_subject}&body={$mailto_body}";

    // Template HTML con estilos en línea y layout en tablas para compatibilidad total
    $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nuevo Prospecto - {$folio}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f4; font-family:Arial, Helvetica, sans-serif; -webkit-font-smoothing:antialiased; color:#333333;">
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f4f4f4; padding:20px 0;">
  <tr>
    <td align="center">
      <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600" style="max-width:600px; width:100%; background-color:#ffffff; border-radius:4px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
        <!-- Header negro con texto y folio a la derecha -->
        <tr>
          <td style="background-color:#111111; padding:20px 25px; color:#ffffff;">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
              <tr>
                <td style="font-size:14px; font-weight:bold; letter-spacing:1.5px; color:#ffffff; vertical-align:middle;">
                  RAMSOL · ZEROMADERA WATERPROOF
                </td>
                <td align="right" style="font-size:12px; color:#cccccc; vertical-align:middle; font-family:monospace;">
                  FOLIO: <span style="color:#ffffff; font-weight:bold;">{$folio}</span>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <!-- Franja roja Ramsol -->
        <tr>
          <td style="background-color:{$brand_color}; height:4px; line-height:4px; font-size:0;">&nbsp;</td>
        </tr>
        <!-- Cuerpo principal -->
        <tr>
          <td style="padding:30px 30px 20px 30px;">
            <h1 style="margin:0 0 10px 0; font-size:22px; font-weight:bold; color:#1a1a1a;">
              Notificación de nuevo prospecto
            </h1>
            <p style="margin:0 0 25px 0; font-size:14px; color:#666666; line-height:1.5;">
              Se ha recibido un nuevo mensaje a través del formulario de contacto del sitio web.
            </p>

            <!-- Tabla de datos -->
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse; margin-bottom:25px;">
              <tr>
                <td style="padding:10px 12px; border-bottom:1px solid #eeeeee; font-size:13px; font-weight:bold; color:#555555; width:130px; background-color:#fafafa;">Nombre:</td>
                <td style="padding:10px 12px; border-bottom:1px solid #eeeeee; font-size:14px; color:#222222;">{$nombre}</td>
              </tr>
              <tr>
                <td style="padding:10px 12px; border-bottom:1px solid #eeeeee; font-size:13px; font-weight:bold; color:#555555; background-color:#fafafa;">Correo:</td>
                <td style="padding:10px 12px; border-bottom:1px solid #eeeeee; font-size:14px; color:#d63e2d;"><a href="mailto:{$email}" style="color:{$brand_color}; text-decoration:none; font-weight:bold;">{$email}</a></td>
              </tr>
              <tr>
                <td style="padding:10px 12px; border-bottom:1px solid #eeeeee; font-size:13px; font-weight:bold; color:#555555; background-color:#fafafa;">Asunto:</td>
                <td style="padding:10px 12px; border-bottom:1px solid #eeeeee; font-size:14px; color:#222222;">{$asunto}</td>
              </tr>
              <tr>
                <td style="padding:10px 12px; border-bottom:1px solid #eeeeee; font-size:13px; font-weight:bold; color:#555555; background-color:#fafafa;">Fecha:</td>
                <td style="padding:10px 12px; border-bottom:1px solid #eeeeee; font-size:14px; color:#222222;">{$fecha_str}</td>
              </tr>
              <tr>
                <td style="padding:10px 12px; border-bottom:1px solid #eeeeee; font-size:13px; font-weight:bold; color:#555555; background-color:#fafafa;">Página de origen:</td>
                <td style="padding:10px 12px; border-bottom:1px solid #eeeeee; font-size:13px; color:#666666; word-break:break-all;">{$pagina_origen}</td>
              </tr>
            </table>

            <!-- Sección Mensaje con borde izquierdo rojo -->
            <div style="margin:25px 0; padding:15px 20px; background-color:#fcfcfc; border-left:4px solid {$brand_color}; border-radius:0 4px 4px 0;">
              <div style="font-size:12px; font-weight:bold; text-transform:uppercase; color:#888888; margin-bottom:8px; letter-spacing:0.5px;">Mensaje del prospecto:</div>
              <div style="font-size:14px; line-height:1.6; color:#2c3e50;">
                {$mensaje_html}
              </div>
            </div>

            <!-- Botón Responder -->
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin:30px 0 20px 0;">
              <tr>
                <td align="center" style="background-color:{$brand_color}; border-radius:4px;">
                  <a href="{$mailto_href}" target="_blank" style="display:inline-block; padding:12px 28px; font-size:14px; font-weight:bold; color:#ffffff; text-decoration:none; letter-spacing:0.5px;">
                    Responder al interesado &rarr;
                  </a>
                </td>
              </tr>
            </table>

            <!-- Recomendación de seguimiento -->
            <div style="background-color:#fff9f8; border:1px solid #f9dedb; padding:12px 15px; border-radius:4px; font-size:13px; color:#8a3b33; line-height:1.5; margin-bottom:20px;">
              <strong>Recomendación de seguimiento:</strong> Responder dentro de las primeras 24 horas hábiles y registrar el folio <strong>{$folio}</strong> en su bitácora de CRM o ventas para rastreo.
            </div>
          </td>
        </tr>

        <!-- Footer con aviso de privacidad LFPDPPP -->
        <tr>
          <td style="background-color:#f9f9f9; padding:20px 30px; border-top:1px solid #eeeeee; font-size:11px; color:#888888; line-height:1.5;">
            <p style="margin:0 0 8px 0;">
              Este correo es una notificación automática generada por el sitio web <strong>{$brand_name}</strong>. Por favor, no responda directamente a esta dirección automatizada.
            </p>
            <p style="margin:0;">
              <strong>Aviso de Privacidad (LFPDPPP):</strong> La información contenida en este mensaje y sus anexos es confidencial y para uso exclusivo del destinatario. En cumplimiento con la Ley Federal de Protección de Datos Personales en Posesión de los Particulares (México), los datos recabados serán tratados exclusivamente para la atención comercial y de servicio solicitada.
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML;

    // Versión en texto plano
    $text = <<<TEXT
RAMSOL · ZEROMADERA WATERPROOF
FOLIO: {$data['folio']}
======================================================
NOTIFICACIÓN DE NUEVO PROSPECTO
======================================================

Nombre:           {$data['nombre']}
Correo:           {$data['email']}
Asunto:           {$data['asunto']}
Fecha:            {$fecha_str}
Página de origen: {$data['pagina_origen']}

------------------------------------------------------
MENSAJE:
------------------------------------------------------
{$mensaje_raw}
------------------------------------------------------

RECOMENDACIÓN DE SEGUIMIENTO:
Responder dentro de las primeras 24 horas y registrar el folio {$data['folio']} en el CRM.

Para responder directamente al interesado: {$data['email']}

======================================================
Aviso de Privacidad (LFPDPPP): Los datos personales contenidos en esta notificación están protegidos de conformidad con la legislación mexicana aplicable y son para uso exclusivo del destinatario designado.
TEXT;

    return [
        'html' => $html,
        'text' => $text,
    ];
}
