/**
 * Ramsol – Contact form handler (contacto.js)
 * Prefix: rs_
 */
(function() {
  'use strict';

  var API_TOKEN   = 'api/token.php';
  var API_CAPTCHA = 'api/captcha.php';
  var API_SUBMIT  = 'api/contacto.php';
  var MIN_DELAY   = 4500; // ms since token fetched before submit allowed

  var form, btnSubmit, btnText, spinner, msgBox;
  var tokenReady   = false;
  var tokenTime    = 0;
  var captchaMode  = null;
  var initialized  = false;

  /* ── Helpers ────────────────────────────────────── */

  function $(id) { return document.getElementById(id); }

  function showErr(id, text) {
    var el = $(id);
    if (el) { el.textContent = text; el.style.display = text ? '' : 'none'; }
  }
  function clearErrs() {
    var errs = document.querySelectorAll('.rs_field-error');
    for (var i = 0; i < errs.length; i++) { errs[i].textContent = ''; errs[i].style.display = 'none'; }
  }

  function showMsg(text, type) {
    msgBox.textContent = text;
    msgBox.className = 'rs_form-msg ' + (type === 'ok' ? 'rs_msg-ok' : 'rs_msg-err');
    msgBox.style.display = '';
  }
  function hideMsg() { msgBox.style.display = 'none'; msgBox.textContent = ''; }

  function setLoading(on) {
    btnSubmit.disabled = on;
    btnText.style.display = on ? 'none' : '';
    spinner.style.display  = on ? 'inline-block' : 'none';
  }

  /* ── Token ──────────────────────────────────────── */

  function fetchToken() {
    if (tokenReady) return;
    fetch(API_TOKEN, { credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (!d.token) return;
        $('rs_token').value = d.token;
        tokenTime  = Date.now();
        tokenReady = true;
        captchaMode = d.captcha_mode || null;
        if (captchaMode === 'imagen') loadCaptcha();
      })
      .catch(function() { /* silent – will fail on submit */ });
  }

  /* ── Captcha ────────────────────────────────────── */

  function loadCaptcha() {
    fetch(API_CAPTCHA, { credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (!d.id || !d.image) return;
        $('rs_captcha_id').value = d.id;
        $('rs_captcha_img').src  = d.image; // data URI or URL
        $('rs_captcha_wrap').style.display = '';
      })
      .catch(function() {});
  }

  /* ── Validation ─────────────────────────────────── */

  function validate() {
    clearErrs();
    var ok = true;
    var nombre  = $('rs_nombre').value.trim();
    var email   = $('rs_email').value.trim();
    var asunto  = $('rs_asunto').value.trim();
    var mensaje = $('rs_mensaje').value.trim();

    if (!nombre || nombre.length < 2) {
      showErr('rs_nombre_err', 'El nombre debe tener al menos 2 caracteres.'); ok = false;
    } else if (nombre.length > 100) {
      showErr('rs_nombre_err', 'El nombre no puede superar 100 caracteres.'); ok = false;
    }

    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      showErr('rs_email_err', 'Introduce un correo electrónico válido.'); ok = false;
    }

    if (!asunto) {
      showErr('rs_asunto_err', 'El asunto es obligatorio.'); ok = false;
    } else if (asunto.length > 150) {
      showErr('rs_asunto_err', 'El asunto no puede superar 150 caracteres.'); ok = false;
    }

    if (mensaje.length > 2000) {
      showErr('rs_mensaje_err', 'El mensaje no puede superar 2000 caracteres.'); ok = false;
    }

    if (captchaMode === 'imagen') {
      var ans = $('rs_captcha_ans').value.trim();
      if (!ans) {
        showErr('rs_captcha_err', 'Escribe el código de la imagen.'); ok = false;
      }
    }

    return ok;
  }

  /* ── Submit ─────────────────────────────────────── */

  function submit(e) {
    e.preventDefault();
    hideMsg();

    if (!validate()) return;

    if (!tokenReady) {
      showMsg('Espera un momento, se está preparando el formulario…', 'err');
      fetchToken();
      return;
    }

    var elapsed = Date.now() - tokenTime;
    if (elapsed < MIN_DELAY) {
      showMsg('Por favor espera unos segundos antes de enviar.', 'err');
      setTimeout(function() { hideMsg(); }, 2000);
      return;
    }

    // Honeypot check (should be empty)
    if ($('rs_hp_k7').value) return;

    setLoading(true);

    var body = new FormData(form);

    fetch(API_SUBMIT, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'fetch' },
      body: body
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
      setLoading(false);
      if (d.ok) {
        showMsg(d.mensaje || '¡Mensaje enviado con éxito! Nos comunicaremos contigo pronto.', 'ok');
        form.reset();
        tokenReady = false;
        if (captchaMode === 'imagen') {
          $('rs_captcha_wrap').style.display = 'none';
        }
      } else {
        showMsg(d.error || 'Ocurrió un error al enviar. Intenta de nuevo.', 'err');
        // Refresh captcha on error
        if (captchaMode === 'imagen') loadCaptcha();
        // Fetch new token
        tokenReady = false;
        fetchToken();
      }
    })
    .catch(function() {
      setLoading(false);
      showMsg('Error de conexión. Verifica tu internet e intenta de nuevo.', 'err');
    });
  }

  /* ── Init ───────────────────────────────────────── */

  function init() {
    if (initialized) return;
    form      = $('rs_contact_form');
    if (!form) return;
    initialized = true;

    btnSubmit = $('rs_submit_btn');
    btnText   = form.querySelector('.rs_btn-text');
    spinner   = form.querySelector('.rs_spinner');
    msgBox    = $('rs_form_msg');

    form.addEventListener('submit', submit);

    // Refresh captcha button
    var refreshBtn = $('rs_captcha_refresh');
    if (refreshBtn) {
      refreshBtn.addEventListener('click', function() {
        if (captchaMode === 'imagen') loadCaptcha();
      });
    }

    // Fetch token on first focus (lazy) or immediately if visible
    var fields = form.querySelectorAll('input, textarea');
    function onFirstInteraction() {
      fetchToken();
      for (var i = 0; i < fields.length; i++) {
        fields[i].removeEventListener('focus', onFirstInteraction);
      }
    }
    for (var i = 0; i < fields.length; i++) {
      fields[i].addEventListener('focus', onFirstInteraction);
    }

    // Also fetch after a short delay in case user doesn't focus
    setTimeout(fetchToken, 3000);
  }

  // Run init
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
