# API de Contacto Ramsol

Backend en PHP para la gestión, validación y envío seguro de prospectos del sitio web Ramsol (`site_ramsol`).

## Estructura de Archivos

```
api/
├── .htaccess              # Control de acceso seguro a endpoints PHP
├── lib.php                # Biblioteca compartida de utilidades (prefijo rs_)
├── config.example.php     # Plantilla de configuración sin credenciales
├── token.php              # Generador de tokens anti-CSRF temporizados
├── captcha.php            # Generador de captcha matemático visual (PNG)
├── contacto.php           # Endpoint POST de recepción y envío vía Brevo API
├── email-template.php     # Plantilla de correo HTML y texto plano
├── diagnostico.php        # Panel de diagnóstico y verificación del entorno
├── LEEME.md               # Esta documentación
└── data/                  # Directorio protegido de almacenamiento
    ├── .htaccess          # Acceso denegado a nivel web (Require all denied)
    ├── logs/              # Bitácoras mensuales YYYY-MM.log (IPs hasheadas)
    ├── nonces/            # Registro temporal de un solo uso para anti-replay
    └── ratelimit/         # Control de tasa de peticiones por IP
```

## Requisitos

- PHP 7.4 o PHP 8.x
- Extensiones PHP: `curl`, `gd`, `mbstring`, `json`, `openssl`
- Servidor web Apache con módulo `mod_authz_core` habilitado

## Puesta en Marcha

1. **Crear archivo de configuración:**
   Copiar `config.example.php` como `config.php`:
   ```bash
   cp config.example.php config.php
   ```

2. **Generar secreto para tokens:**
   Ejecutar en la terminal:
   ```bash
   php -r 'echo bin2hex(random_bytes(32)) . PHP_EOL;'
   ```
   Asignar el resultado en la clave `'token_secret'` de `config.php`.

3. **Configurar Brevo:**
   - Asignar tu API Key (`xkeysib-...`) en `'brevo_api_key'`.
   - Ajustar los correos destinatarios en `'recipients'`.

4. **Clave de Diagnóstico:**
   - Definir una clave alfanumérica segura en `'diag_key'`.
   - Acceder al panel mediante: `http://localhost/site_ramsol/api/diagnostico.php?clave=TU_CLAVE`

## Modo de Prueba (Dry-run)

Para realizar pruebas completas del formulario sin realizar llamadas reales a la API de Brevo:
```bash
touch api/MODO_PRUEBA
```
Mientras este archivo exista y tenga menos de 10 minutos de antigüedad, el endpoint simula el envío, genera el folio, registra el log y responde éxito con `"dry_run": true`.

## Capas de Seguridad

1. **Restricción de Origen:** Valida encabezados `Origin` y `Referer` contra la lista blanca permitida.
2. **Anti-CSRF y Anti-Replay:** Tokens únicos firmados con HMAC-SHA256 y validados con nonces de un solo uso.
3. **Temporizador de Llenado Humano:** Requiere un mínimo de 4 segundos entre la carga del formulario y el envío.
4. **Honeypot:** Campo oculto `rs_hp_k7` para atrapar robots de spam.
5. **Rate Limiting:** Máximo 3 envíos cada 10 minutos y 10 envíos por día por dirección IP.
6. **Filtros Antispam Silenciosos:** Bloqueo de múltiples enlaces y palabras no deseadas respondiendo con código 200 fingido para no alertar a los spammers.
7. **Privacidad:** Las direcciones IP se registran únicamente en formato hash criptográfico (SHA-256).
