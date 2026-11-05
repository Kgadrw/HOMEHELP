<?php
/**
 * In-page hiring (client) register modal.
 * Open with: <a href="/register" data-open-register>…</a>
 * Or: window.HomeHelpRegister.open()
 */
if (!defined('BASE_URL')) {
    return;
}
if (!empty($GLOBALS['__hh_register_modal_included'])) {
    return;
}
$GLOBALS['__hh_register_modal_included'] = true;

$hh_reg_csrf = function_exists('generateCSRFToken') ? generateCSRFToken() : '';
$hh_reg_api  = rtrim(BASE_URL, '/') . '/register-account';
$hh_login    = rtrim(BASE_URL, '/') . '/login';
$hh_terms    = rtrim(BASE_URL, '/') . '/terms';
$hh_privacy  = rtrim(BASE_URL, '/') . '/privacy';
$hh_google   = rtrim(BASE_URL, '/') . '/google-login?role=client';
?>
<style>
.hh-reg-root{
  --hh-navy:#0D1B4B;--hh-navy-mid:#152268;--hh-blue:#2E5FC2;--hh-soft:#EEF2FB;
  --hh-line:#E5E7EB;--hh-bg:#F8F9FC;--hh-text:#111827;--hh-muted:#6B7280;
  --hh-white:#fff;--hh-danger:#DC2626;--hh-ok:#047857;
  --hh-font:'Plus Jakarta Sans',system-ui,sans-serif;
}
.hh-reg-root[hidden]{display:none!important}
.hh-reg-root{position:fixed;inset:0;z-index:10004;display:flex;align-items:center;justify-content:center;padding:20px 14px;font-family:var(--hh-font)}
.hh-reg-backdrop{position:absolute;inset:0;background:rgba(8,16,48,.62);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);border:0;cursor:pointer}
.hh-reg-dialog{
  position:relative;width:100%;max-width:460px;max-height:min(94vh,820px);overflow:auto;
  background:var(--hh-white);border-radius:20px;box-shadow:0 28px 80px rgba(8,16,48,.35);
  padding:16px 22px 22px;animation:hhRegIn .26s cubic-bezier(.22,1,.36,1) both;
}
@keyframes hhRegIn{from{opacity:0;transform:translateY(16px) scale(.97)}to{opacity:1;transform:none}}
.hh-reg-top{
  display:flex;align-items:flex-start;justify-content:space-between;gap:12px;
  margin:0 0 16px;
}
.hh-reg-heading{flex:1;min-width:0;padding-top:2px}
.hh-reg-heading .hh-reg-eyebrow{
  display:inline-block;margin:0 0 6px;padding:4px 9px;border-radius:999px;
  background:var(--hh-soft);color:var(--hh-navy);font-size:.68rem;font-weight:750;
  letter-spacing:.04em;text-transform:uppercase;
}
.hh-reg-heading h2{
  margin:0 0 4px;font-size:1.28rem;font-weight:800;letter-spacing:-.03em;
  color:var(--hh-navy);line-height:1.2;
}
.hh-reg-heading p{margin:0;font-size:.84rem;color:var(--hh-muted);line-height:1.4}
.hh-reg-close{
  width:36px;height:36px;border-radius:10px;flex-shrink:0;
  border:1px solid var(--hh-line);background:var(--hh-bg);color:var(--hh-muted);cursor:pointer;
  display:inline-flex;align-items:center;justify-content:center;
}
.hh-reg-close:hover{color:var(--hh-navy);border-color:#C9D4EE;background:var(--hh-soft)}
.hh-reg-close svg{width:16px;height:16px;stroke-width:2.25}
.hh-reg-tabs{
  display:grid;grid-template-columns:1fr 1fr;gap:4px;padding:4px;margin:0 0 16px;
  background:var(--hh-bg);border:1px solid var(--hh-line);border-radius:12px;
}
.hh-reg-tabs a{
  display:flex;align-items:center;justify-content:center;padding:9px 10px;border-radius:9px;
  font-size:.82rem;font-weight:700;text-decoration:none;color:var(--hh-muted);transition:background .15s,color .15s,box-shadow .15s;
}
.hh-reg-tabs a.is-on{background:var(--hh-white);color:var(--hh-navy);box-shadow:0 1px 3px rgba(13,27,75,.08)}
.hh-reg-tabs a:hover:not(.is-on){color:var(--hh-navy)}
.hh-reg-alert{
  display:none;align-items:flex-start;gap:8px;padding:10px 12px;margin-bottom:14px;border-radius:10px;
  background:#FEF2F2;border:1px solid #FECACA;color:#B91C1C;font-size:.8125rem;line-height:1.4
}
.hh-reg-alert.is-on{display:flex}
.hh-reg-alert svg{width:16px;height:16px;flex-shrink:0;margin-top:1px}
.hh-reg-google{
  width:100%;display:flex;align-items:center;justify-content:center;gap:10px;
  padding:11px 14px;margin-bottom:14px;border-radius:999px;border:1px solid var(--hh-line);
  background:var(--hh-white);color:var(--hh-text);font:inherit;font-size:.875rem;font-weight:650;
  text-decoration:none;cursor:pointer;transition:border-color .15s,background .15s,transform .15s
}
.hh-reg-google:hover{border-color:#C9D4EE;background:var(--hh-soft);transform:translateY(-1px)}
.hh-reg-google svg{width:18px;height:18px}
.hh-reg-div{display:flex;align-items:center;gap:12px;margin:0 0 14px;color:var(--hh-muted);font-size:.72rem;font-weight:600;letter-spacing:.04em;text-transform:uppercase}
.hh-reg-div::before,.hh-reg-div::after{content:'';flex:1;height:1px;background:var(--hh-line)}
.hh-reg-field{display:flex;flex-direction:column;gap:6px;margin-bottom:12px}
.hh-reg-field label{font-size:.76rem;font-weight:650;color:var(--hh-text)}
.hh-reg-field input{
  width:100%;padding:12px 13px;border:1px solid var(--hh-line);border-radius:11px;
  font:inherit;font-size:.9rem;color:var(--hh-text);background:var(--hh-bg);outline:none;
  transition:border-color .15s,box-shadow .15s,background .15s
}
.hh-reg-field input:focus{border-color:var(--hh-blue);background:var(--hh-white);box-shadow:0 0 0 3px rgba(46,95,194,.14)}
.hh-reg-field input.is-bad{border-color:var(--hh-danger);box-shadow:0 0 0 3px rgba(220,38,38,.1)}
.hh-reg-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.hh-reg-terms{display:flex;align-items:flex-start;gap:10px;margin:4px 0 16px;font-size:.78rem;color:var(--hh-muted);line-height:1.45;cursor:pointer}
.hh-reg-terms input{margin-top:2px;accent-color:var(--hh-navy)}
.hh-reg-terms a{color:var(--hh-blue);font-weight:650;text-decoration:none}
.hh-reg-terms a:hover{text-decoration:underline}
.hh-reg-submit{
  width:100%;padding:13px 16px;border:0;border-radius:999px;
  background:linear-gradient(180deg,#1a2f78 0%,var(--hh-navy) 100%);color:#fff;
  font:inherit;font-size:.9375rem;font-weight:750;cursor:pointer;
  display:inline-flex;align-items:center;justify-content:center;gap:8px;
  box-shadow:0 8px 20px rgba(13,27,75,.22);transition:transform .15s,filter .15s
}
.hh-reg-submit:hover{filter:brightness(1.06);transform:translateY(-1px)}
.hh-reg-submit:disabled{opacity:.65;pointer-events:none;transform:none}
.hh-reg-submit svg{width:16px;height:16px;stroke-width:2.25}
.hh-reg-trust{
  margin:14px 0 0;padding-top:14px;border-top:1px solid var(--hh-line);
  display:flex;align-items:center;justify-content:center;gap:8px;
  font-size:.72rem;color:var(--hh-muted);text-align:center;line-height:1.35
}
.hh-reg-trust svg{width:14px;height:14px;color:var(--hh-ok);flex-shrink:0}
@media(max-width:480px){
  .hh-reg-root{padding:0;align-items:stretch}
  .hh-reg-dialog{max-width:none;max-height:none;min-height:100%;border-radius:0;padding:16px 18px 28px}
  .hh-reg-top{margin:0 0 16px}
  .hh-reg-row{grid-template-columns:1fr}
}
</style>

<div class="hh-reg-root" id="hhRegModal" hidden>
  <button type="button" class="hh-reg-backdrop" data-hh-reg-close aria-label="Close"></button>
  <div class="hh-reg-dialog" role="dialog" aria-modal="true" aria-labelledby="hhRegTitle" tabindex="-1">
    <div class="hh-reg-top">
      <div class="hh-reg-heading">
        <span class="hh-reg-eyebrow">Hiring</span>
        <h2 id="hhRegTitle">Create hiring account</h2>
        <p>Register as a hirer to book vetted domestic workers.</p>
      </div>
      <button type="button" class="hh-reg-close" data-hh-reg-close aria-label="Close">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
      </button>
    </div>
      <div class="hh-reg-tabs" role="tablist">
        <a href="<?= htmlspecialchars($hh_login) ?>" data-open-login data-hh-reg-close role="tab" aria-selected="false">Sign in</a>
        <a href="<?= htmlspecialchars(BASE_URL) ?>/register" class="is-on" role="tab" aria-selected="true">Create account</a>
      </div>

      <div class="hh-reg-alert" id="hhRegAlert" role="alert">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
        <span id="hhRegAlertText"></span>
      </div>

      <a class="hh-reg-google" href="<?= htmlspecialchars($hh_google) ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
          <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
          <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
          <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
          <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
        </svg>
        Continue with Google
      </a>
      <div class="hh-reg-div">or email</div>

      <form id="hhRegForm" method="post" action="<?= htmlspecialchars($hh_reg_api) ?>" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($hh_reg_csrf) ?>"/>
        <div class="hh-reg-field">
          <label for="hhRegName">Full name</label>
          <input type="text" id="hhRegName" name="name" placeholder="Amina Uwase" autocomplete="name" required/>
        </div>
        <div class="hh-reg-field">
          <label for="hhRegEmail">Email</label>
          <input type="email" id="hhRegEmail" name="email" placeholder="you@example.com" autocomplete="email" required/>
        </div>
        <div class="hh-reg-field">
          <label for="hhRegPhone">Phone / WhatsApp</label>
          <input type="tel" id="hhRegPhone" name="phone" placeholder="+250 7XX XXX XXX" autocomplete="tel" required/>
        </div>
        <div class="hh-reg-row">
          <div class="hh-reg-field">
            <label for="hhRegPass">Password</label>
            <input type="password" id="hhRegPass" name="password" placeholder="Min. 8 characters" autocomplete="new-password" required minlength="8"/>
          </div>
          <div class="hh-reg-field">
            <label for="hhRegPass2">Confirm</label>
            <input type="password" id="hhRegPass2" name="confirm_password" placeholder="Repeat password" autocomplete="new-password" required minlength="8"/>
          </div>
        </div>
        <label class="hh-reg-terms">
          <input type="checkbox" name="terms" value="1" id="hhRegTerms" required/>
          <span>I agree to the <a href="<?= htmlspecialchars($hh_terms) ?>" target="_blank" rel="noopener">Terms</a> and <a href="<?= htmlspecialchars($hh_privacy) ?>" target="_blank" rel="noopener">Privacy Policy</a></span>
        </label>
        <button type="submit" class="hh-reg-submit" id="hhRegSubmit">
          <span>Create hiring account</span>
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
        </button>
      </form>
      <p class="hh-reg-trust">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/></svg>
        Free to join · Start hiring in minutes
      </p>
  </div>
</div>

<script>
(function(){
  if (window.HomeHelpRegister) return;
  var root = document.getElementById('hhRegModal');
  if (!root) return;
  var form = document.getElementById('hhRegForm');
  var alertBox = document.getElementById('hhRegAlert');
  var alertText = document.getElementById('hhRegAlertText');
  var submitBtn = document.getElementById('hhRegSubmit');
  var api = <?= json_encode($hh_reg_api) ?>;
  var lastFocus = null;

  function showError(msg){
    alertText.textContent = msg || 'Something went wrong.';
    alertBox.classList.add('is-on');
  }
  function clearError(){
    alertBox.classList.remove('is-on');
    alertText.textContent = '';
    form.querySelectorAll('input.is-bad').forEach(function(el){ el.classList.remove('is-bad'); });
  }
  function open(){
    if (window.HomeHelpLogin) HomeHelpLogin.close();
    if (window.HomeHelpBook) HomeHelpBook.close();
    if (window.HomeHelpWorkerLogin) HomeHelpWorkerLogin.close();
    lastFocus = document.activeElement;
    root.hidden = false;
    document.body.style.overflow = 'hidden';
    clearError();
    var first = document.getElementById('hhRegName');
    if (first) setTimeout(function(){ first.focus(); }, 40);
  }
  function close(){
    root.hidden = true;
    if (!document.getElementById('hhClientLoginModal') || document.getElementById('hhClientLoginModal').hidden) {
      document.body.style.overflow = '';
    }
    if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
  }

  window.HomeHelpRegister = { open: open, close: close };

  root.querySelectorAll('[data-hh-reg-close]').forEach(function(el){
    el.addEventListener('click', close);
  });
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape' && !root.hidden) close();
  });

  document.addEventListener('click', function(e){
    var a = e.target.closest('a[data-open-register]');
    if (!a) return;
    if (a.hasAttribute('data-open-book') || a.hasAttribute('data-book-wizard')) return;
    e.preventDefault();
    open();
    var mob = document.getElementById('mobileMenu');
    var ham = document.getElementById('hamburger');
    if (mob) mob.classList.remove('open');
    if (ham) ham.classList.remove('open');
  });

  try {
    var q = new URLSearchParams(window.location.search);
    var path = window.location.pathname || '';
    if (q.get('register') === '1' || window.location.hash === '#register' || /\/register\/?$/.test(path)) open();
  } catch (err) {}

  form.addEventListener('submit', function(e){
    e.preventDefault();
    clearError();
    var name = document.getElementById('hhRegName');
    var email = document.getElementById('hhRegEmail');
    var phone = document.getElementById('hhRegPhone');
    var pass = document.getElementById('hhRegPass');
    var pass2 = document.getElementById('hhRegPass2');
    var terms = document.getElementById('hhRegTerms');
    var bad = false;
    [name, email, phone, pass, pass2].forEach(function(el){
      if (!el.value.trim()) { el.classList.add('is-bad'); bad = true; }
    });
    if (email.value && !/\S+@\S+\.\S+/.test(email.value)) { email.classList.add('is-bad'); bad = true; }
    if (pass.value && pass.value.length < 8) { pass.classList.add('is-bad'); bad = true; }
    if (pass.value !== pass2.value) { pass2.classList.add('is-bad'); bad = true; }
    if (!terms.checked) { bad = true; showError('Please accept the Terms of Service.'); return; }
    if (bad) { showError('Please check the highlighted fields.'); return; }

    var fd = new FormData(form);
    submitBtn.disabled = true;
    submitBtn.querySelector('span').textContent = 'Creating…';

    fetch(api, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function(r){ return r.json().then(function(j){ return { ok: r.ok, j: j }; }); })
      .then(function(res){
        if (res.j && res.j.ok) {
          window.location.href = res.j.redirect || '/dashboard';
          return;
        }
        showError((res.j && res.j.error) || 'Could not create account.');
        submitBtn.disabled = false;
        submitBtn.querySelector('span').textContent = 'Create hiring account';
      })
      .catch(function(){
        showError('Network error. Please try again.');
        submitBtn.disabled = false;
        submitBtn.querySelector('span').textContent = 'Create hiring account';
      });
  });
})();
</script>
