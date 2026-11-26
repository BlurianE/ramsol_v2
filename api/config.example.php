<?php
defined('RS_API') || exit;
function rs_config() {
    return [
        'brevo_api_key' => '',  // Brevo API key (xkeysib-...)
        'sender_email' => 'no-reply@ramsol.com.mx',
        'sender_name' => 'Ramsol · Sitio web',
        'recipients' => ['desarrollo.cayab@gmail.com','ramsol@ramsol.com.mx','ventas@ramtec.com.mx'],
        'subject_prefix' => 'Ramsol | ',
        'email_brand' => ['name'=>'ramsol.com','color'=>'#d63e2d','folio_prefix'=>'RS'],
        'allowed_hosts' => ['ramsol.com','www.ramsol.com','localhost','127.0.0.1'],
        'token_secret' => '',  // php -r 'echo bin2hex(random_bytes(32));'
        'rate_limit' => ['per_10min'=>3, 'per_day'=>10],
        'min_fill_seconds' => 4,
        'token_ttl' => 7200,
        'max_links' => 1,
        'blocked_words' => ['viagra','casino','crypto','bitcoin','lottery','prize'],
        'captcha_mode' => 'imagen',  // imagen, turnstile, ninguno
        'diag_key' => '',  // empty = disabled
        'trust_proxy' => false,
    ];
}
