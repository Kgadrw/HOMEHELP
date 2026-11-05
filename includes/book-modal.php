<?php
/**
 * In-page hire / book modal. Include once before </body>.
 * Open with: data-open-book or data-book-wizard, or window.HomeHelpBook.open()
 */
if (!defined('BASE_URL') || !isset($conn)) {
    return;
}
if (!empty($GLOBALS['__hh_book_modal_included'])) {
    return;
}
$GLOBALS['__hh_book_modal_included'] = true;

$hh_book_services = $conn->query("
    SELECT id, name FROM services WHERE status='active' ORDER BY sort_order ASC, id ASC
")->fetch_all(MYSQLI_ASSOC);

$hh_book_icons = [
    6 => 'sparkles', 7 => 'house', 9 => 'chef-hat',
];
function hh_book_icon($sv) {
    global $hh_book_icons;
    if (isset($hh_book_icons[(int)$sv['id']])) return $hh_book_icons[(int)$sv['id']];
    $n = strtolower($sv['name'] ?? '');
    if (str_contains($n, 'clean')) return 'sparkles';
    if (str_contains($n, 'chef') || str_contains($n, 'cook')) return 'chef-hat';
    if (str_contains($n, 'nanny') || str_contains($n, 'baby')) return 'baby';
    if (str_contains($n, 'care')) return 'heart-pulse';
    return 'house';
}

$hh_book_csrf  = function_exists('generateCSRFToken') ? generateCSRFToken() : '';
$hh_book_logo  = rtrim(BASE_URL, '/') . '/Image/homehelp-logo.png';
$hh_book_api   = rtrim(BASE_URL, '/') . '/book-request';
$hh_book_login = rtrim(BASE_URL, '/') . '/login';
$hh_book_logged = isLoggedIn() && (($_SESSION['user_role'] ?? '') === 'client');
$hh_book_name  = $hh_book_logged ? ($_SESSION['user_name'] ?? '') : '';
?>
<style>
.hh-book-root{--hh-navy:#0D1B4B;--hh-navy-mid:#152268;--hh-blue:#2E5FC2;--hh-soft:#EEF2FB;--hh-line:#E8E4DC;--hh-bg:#FAFAF8;--hh-text:#111827;--hh-muted:#6B7280;--hh-white:#fff;--hh-danger:#DC2626;--hh-font:'Plus Jakarta Sans',system-ui,sans-serif}
.hh-book-root[hidden]{display:none!important}
.hh-book-root{position:fixed;inset:0;z-index:10001;display:flex;align-items:center;justify-content:center;padding:20px 14px;font-family:var(--hh-font)}
.hh-book-backdrop{position:absolute;inset:0;background:rgba(13,27,75,.55);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);border:0;cursor:pointer}
.hh-book-dialog{
  position:relative;width:100%;max-width:520px;max-height:min(92vh,820px);overflow:auto;
  background:var(--hh-white);border-radius:16px;box-shadow:0 20px 60px rgba(13,27,75,.28);
  padding:26px 26px 22px;animation:hhBookIn .22s ease both;
}
@keyframes hhBookIn{from{opacity:0;transform:translateY(10px) scale(.98)}to{opacity:1;transform:none}}
.hh-book-close{
  position:absolute;top:12px;right:12px;width:34px;height:34px;border-radius:8px;
  border:1px solid var(--hh-line);background:var(--hh-bg);color:var(--hh-muted);cursor:pointer;
  display:inline-flex;align-items:center;justify-content:center;
}
.hh-book-close:hover{color:var(--hh-navy);border-color:#C9D4EE}
.hh-book-close svg{width:16px;height:16px;stroke-width:2.25}
.hh-book-brand{display:block;width:40px;height:40px;margin:0 auto 12px;line-height:0}
.hh-book-brand img{width:100%;height:100%;object-fit:contain;border-radius:50%}
.hh-book-dialog h2{margin:0 0 4px;text-align:center;font-size:1.3rem;font-weight:800;letter-spacing:-.03em;color:var(--hh-navy)}
.hh-book-sub{margin:0 0 16px;text-align:center;font-size:.84rem;color:var(--hh-muted);line-height:1.45}
.hh-book-sub a{color:var(--hh-blue);font-weight:600;text-decoration:none}
.hh-book-steps{display:flex;gap:6px;margin:0 0 18px}
.hh-book-steps span{flex:1;height:3px;border-radius:99px;background:var(--hh-line)}
.hh-book-steps span.on{background:var(--hh-navy)}
.hh-book-alert{
  display:none;align-items:flex-start;gap:8px;padding:10px 12px;margin-bottom:14px;border-radius:10px;
  background:#FEF2F2;border:1px solid #FECACA;color:#B91C1C;font-size:.8125rem;line-height:1.4
}
.hh-book-alert.is-on{display:flex}
.hh-book-pane{display:none}
.hh-book-pane.on{display:block;animation:hhBookIn .2s ease both}
.hh-book-q{font-size:.98rem;font-weight:700;color:var(--hh-text);margin:0 0 4px}
.hh-book-hint{font-size:.8rem;color:var(--hh-muted);margin:0 0 14px}
.hh-book-svc{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:16px}
.hh-book-svc label{cursor:pointer}
.hh-book-svc input{position:absolute;opacity:0;pointer-events:none}
.hh-book-svc .box{
  display:flex;flex-direction:column;align-items:center;gap:8px;padding:14px 8px;
  border:1.5px solid var(--hh-line);border-radius:12px;background:var(--hh-bg);text-align:center;
  transition:border-color .15s,background .15s,box-shadow .15s
}
.hh-book-svc .box i,.hh-book-svc .box svg{width:20px;height:20px;color:var(--hh-navy);stroke-width:2}
.hh-book-svc .box span{font-size:.78rem;font-weight:650;color:var(--hh-text);line-height:1.25}
.hh-book-svc input:checked + .box{border-color:var(--hh-navy);background:var(--hh-soft);box-shadow:0 0 0 3px rgba(13,27,75,.08)}
.hh-book-freq{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px}
.hh-book-freq label{cursor:pointer}
.hh-book-freq input{position:absolute;opacity:0;pointer-events:none}
.hh-book-freq .chip{
  display:block;padding:11px 12px;border:1.5px solid var(--hh-line);border-radius:10px;
  font-size:.84rem;font-weight:600;color:var(--hh-text);text-align:center;background:var(--hh-white);
  transition:border-color .15s,background .15s
}
.hh-book-freq input:checked + .chip{border-color:var(--hh-navy);background:var(--hh-soft);color:var(--hh-navy)}
.hh-book-field{display:flex;flex-direction:column;gap:6px;margin-bottom:12px}
.hh-book-field label{font-size:.78rem;font-weight:600;color:var(--hh-text)}
.hh-book-field input,.hh-book-field select{
  width:100%;padding:11px 12px;border:1px solid var(--hh-line);border-radius:10px;
  font:inherit;font-size:.9rem;color:var(--hh-text);background:var(--hh-white);outline:none
}
.hh-book-field input:focus,.hh-book-field select:focus{border-color:var(--hh-blue);box-shadow:0 0 0 3px rgba(46,95,194,.12)}
.hh-book-field input.is-bad{border-color:var(--hh-danger)}
.hh-book-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.hh-book-checks{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px}
.hh-book-checks label{
  display:flex;align-items:center;gap:8px;padding:10px 12px;border:1px solid var(--hh-line);
  border-radius:10px;font-size:.8rem;font-weight:550;color:var(--hh-text);cursor:pointer;background:var(--hh-white)
}
.hh-book-checks input{accent-color:var(--hh-navy)}
.hh-book-langs{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px}
.hh-book-langs label{cursor:pointer}
.hh-book-langs input{position:absolute;opacity:0;pointer-events:none}
.hh-book-langs .pill{
  display:inline-block;padding:8px 12px;border:1.5px solid var(--hh-line);border-radius:999px;
  font-size:.78rem;font-weight:600;color:var(--hh-muted);background:var(--hh-white)
}
.hh-book-langs input:checked + .pill{border-color:var(--hh-navy);background:var(--hh-soft);color:var(--hh-navy)}
.hh-book-chef{display:none;margin-bottom:12px;padding:12px;border:1px solid var(--hh-line);border-radius:12px;background:var(--hh-bg)}
.hh-book-chef.on{display:block}
.hh-book-nav{display:flex;gap:8px;margin-top:18px}
.hh-book-back{
  padding:12px 18px;border-radius:999px;border:1px solid var(--hh-line);background:var(--hh-white);
  color:var(--hh-text);font:inherit;font-size:.875rem;font-weight:650;cursor:pointer
}
.hh-book-next{
  flex:1;padding:12px 18px;border:0;border-radius:999px;background:var(--hh-navy);color:#fff;
  font:inherit;font-size:.9rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:8px
}
.hh-book-next:hover{background:var(--hh-navy-mid)}
.hh-book-next:disabled{opacity:.65;pointer-events:none}
.hh-book-next svg{width:16px;height:16px;stroke-width:2.25}
.hh-book-note{font-size:.75rem;color:var(--hh-muted);line-height:1.45;margin:0 0 4px}
@media(max-width:520px){
  .hh-book-root{padding:0;align-items:stretch}
  .hh-book-dialog{max-width:none;max-height:none;min-height:100%;border-radius:0;padding:22px 16px 28px}
  .hh-book-svc{grid-template-columns:1fr}
  .hh-book-row,.hh-book-checks,.hh-book-freq{grid-template-columns:1fr}
}
</style>

<div class="hh-book-root" id="hhBookModal" hidden>
  <button type="button" class="hh-book-backdrop" data-hh-book-close aria-label="Close"></button>
  <div class="hh-book-dialog" role="dialog" aria-modal="true" aria-labelledby="hhBookTitle" tabindex="-1">
    <button type="button" class="hh-book-close" data-hh-book-close aria-label="Close">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
    </button>
    <a href="<?= htmlspecialchars(BASE_URL) ?>/" class="hh-book-brand" aria-label="Home Help">
      <img src="<?= htmlspecialchars($hh_book_logo) ?>" alt="Home Help"/>
    </a>
    <h2 id="hhBookTitle">Hire a worker</h2>
    <p class="hh-book-sub">Tell us what you need — we’ll match you. <?php if (!$hh_book_logged): ?>Have an account? <a href="<?= htmlspecialchars($hh_book_login) ?>" data-open-login>Sign in</a><?php else: ?>Signed in as <?= htmlspecialchars($hh_book_name) ?><?php endif; ?></p>

    <div class="hh-book-steps" aria-hidden="true">
      <span class="on" data-hh-step-bar="1"></span>
      <span data-hh-step-bar="2"></span>
      <span data-hh-step-bar="3"></span>
    </div>

    <div class="hh-book-alert" id="hhBookAlert" role="alert"><span id="hhBookAlertText"></span></div>

    <form id="hhBookForm" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($hh_book_csrf) ?>"/>

      <div class="hh-book-pane on" data-hh-pane="1">
        <p class="hh-book-q">Which services do you need?</p>
        <p class="hh-book-hint">Select one or more.</p>
        <div class="hh-book-svc">
          <?php foreach ($hh_book_services as $sv): ?>
          <label>
            <input type="checkbox" name="service_ids[]" value="<?= (int)$sv['id'] ?>" data-hh-svc="<?= (int)$sv['id'] ?>"/>
            <div class="box">
              <i data-lucide="<?= htmlspecialchars(hh_book_icon($sv)) ?>"></i>
              <span><?= htmlspecialchars($sv['name']) ?></span>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
        <p class="hh-book-q">How often?</p>
        <p class="hh-book-hint">Pick the schedule that fits.</p>
        <div class="hh-book-freq">
          <label><input type="radio" name="frequency" value="3 days/week"/><span class="chip">3 days / week</span></label>
          <label><input type="radio" name="frequency" value="5 days/week"/><span class="chip">5 days / week</span></label>
          <label><input type="radio" name="frequency" value="6 days/week"/><span class="chip">6 days / week</span></label>
          <label><input type="radio" name="frequency" value="Temporary Work"/><span class="chip">Temporary</span></label>
        </div>
        <div class="hh-book-field" id="hhBookTempDays" hidden>
          <label for="hhBookDuration">Number of days</label>
          <input type="number" id="hhBookDuration" name="duration" min="1" placeholder="e.g. 5"/>
        </div>
        <div class="hh-book-nav">
          <button type="button" class="hh-book-next" data-hh-next="1">Continue
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </button>
        </div>
      </div>

      <div class="hh-book-pane" data-hh-pane="2">
        <p class="hh-book-q">A few details</p>
        <p class="hh-book-hint">Optional — helps us match better.</p>
        <div class="hh-book-field">
          <label for="hhBookRooms">Number of rooms</label>
          <input type="number" id="hhBookRooms" name="rooms" min="0" placeholder="e.g. 3"/>
        </div>
        <div class="hh-book-checks">
          <label><input type="checkbox" name="outdoor_areas" value="1"/> Outdoor areas</label>
          <label><input type="checkbox" name="has_car" value="1"/> Car washing</label>
          <label><input type="checkbox" name="has_pool" value="1"/> Pool</label>
          <label><input type="checkbox" name="dish_washing" value="1"/> Dish washing</label>
          <label><input type="checkbox" name="has_pets" value="1"/> Pets</label>
          <label><input type="checkbox" name="laundry" value="1"/> Laundry</label>
          <label><input type="checkbox" name="live_out" value="1"/> Live out</label>
          <label><input type="checkbox" name="live_in" value="1"/> Live in</label>
        </div>
        <div class="hh-book-chef" id="hhBookChef">
          <div class="hh-book-field" style="margin-bottom:10px">
            <label for="hhBookChefType">Chef type</label>
            <select id="hhBookChefType" name="chef_type">
              <option value="">Select…</option>
              <option value="home">Home cook</option>
              <option value="events">Events</option>
            </select>
          </div>
          <div class="hh-book-field" style="margin-bottom:0">
            <label for="hhBookPeople">Number of people</label>
            <input type="number" id="hhBookPeople" name="num_people" min="1" placeholder="e.g. 4"/>
          </div>
        </div>
        <p class="hh-book-q" style="margin-top:4px">Languages</p>
        <div class="hh-book-langs">
          <?php foreach (['English','French','Swahili','Kinyarwanda'] as $lang): ?>
          <label><input type="checkbox" name="languages[]" value="<?= $lang ?>"/><span class="pill"><?= $lang ?></span></label>
          <?php endforeach; ?>
        </div>
        <div class="hh-book-nav">
          <button type="button" class="hh-book-back" data-hh-back="2">Back</button>
          <button type="button" class="hh-book-next" data-hh-next="2">Continue
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </button>
        </div>
      </div>

      <div class="hh-book-pane" data-hh-pane="3">
        <p class="hh-book-q"><?= $hh_book_logged ? 'Confirm & submit' : 'Your details' ?></p>
        <p class="hh-book-hint"><?= $hh_book_logged ? 'We’ll review your request and email a quote.' : 'Create your account and submit the request.' ?></p>
        <?php if ($hh_book_logged): ?>
          <input type="hidden" name="name" value="<?= htmlspecialchars($hh_book_name) ?>"/>
        <?php else: ?>
        <div class="hh-book-row">
          <div class="hh-book-field">
            <label for="hhBookName">Full name</label>
            <input type="text" id="hhBookName" name="name" placeholder="Amina Uwase" autocomplete="name" required/>
          </div>
          <div class="hh-book-field">
            <label for="hhBookPhone">Phone / WhatsApp</label>
            <input type="tel" id="hhBookPhone" name="phone" placeholder="+250 7XX XXX XXX" autocomplete="tel" required/>
          </div>
        </div>
        <div class="hh-book-row">
          <div class="hh-book-field">
            <label for="hhBookEmail">Email</label>
            <input type="email" id="hhBookEmail" name="email" placeholder="you@example.com" autocomplete="email" required/>
          </div>
          <div class="hh-book-field">
            <label for="hhBookNb">Neighbourhood</label>
            <input type="text" id="hhBookNb" name="neighbourhood" placeholder="e.g. Kicukiro"/>
          </div>
        </div>
        <div class="hh-book-row">
          <div class="hh-book-field">
            <label for="hhBookPass">Password</label>
            <input type="password" id="hhBookPass" name="password" placeholder="Min. 8 characters" autocomplete="new-password" required minlength="8"/>
          </div>
          <div class="hh-book-field">
            <label for="hhBookPass2">Confirm</label>
            <input type="password" id="hhBookPass2" name="confirm_password" placeholder="Repeat password" autocomplete="new-password" required minlength="8"/>
          </div>
        </div>
        <?php endif; ?>
        <?php if ($hh_book_logged): ?>
        <div class="hh-book-field">
          <label for="hhBookNb2">Neighbourhood</label>
          <input type="text" id="hhBookNb2" name="neighbourhood" placeholder="e.g. Kicukiro"/>
        </div>
        <?php endif; ?>
        <p class="hh-book-note">No payment now. Our team reviews your request and sends a proposed invoice by email.</p>
        <div class="hh-book-nav">
          <button type="button" class="hh-book-back" data-hh-back="3">Back</button>
          <button type="submit" class="hh-book-next" id="hhBookSubmit"><span>Submit request</span>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  if (window.HomeHelpBook) return;
  var root = document.getElementById('hhBookModal');
  if (!root) return;
  var form = document.getElementById('hhBookForm');
  var alertBox = document.getElementById('hhBookAlert');
  var alertText = document.getElementById('hhBookAlertText');
  var submitBtn = document.getElementById('hhBookSubmit');
  var api = <?= json_encode($hh_book_api) ?>;
  var loggedIn = <?= $hh_book_logged ? 'true' : 'false' ?>;
  var step = 1;
  var lastFocus = null;

  function showError(msg){
    alertText.textContent = msg || 'Something went wrong.';
    alertBox.classList.add('is-on');
    root.querySelector('.hh-book-dialog').scrollTop = 0;
  }
  function clearError(){ alertBox.classList.remove('is-on'); alertText.textContent = ''; }

  function goStep(n){
    step = n;
    root.querySelectorAll('[data-hh-pane]').forEach(function(p){
      p.classList.toggle('on', Number(p.getAttribute('data-hh-pane')) === n);
    });
    root.querySelectorAll('[data-hh-step-bar]').forEach(function(b){
      b.classList.toggle('on', Number(b.getAttribute('data-hh-step-bar')) <= n);
    });
    clearError();
    root.querySelector('.hh-book-dialog').scrollTop = 0;
    if (window.lucide) lucide.createIcons();
  }

  function syncChef(){
    var chefOn = !!root.querySelector('[data-hh-svc="9"]:checked');
    document.getElementById('hhBookChef').classList.toggle('on', chefOn);
  }

  function preselectService(serviceId){
    root.querySelectorAll('[data-hh-svc]').forEach(function(chk){
      chk.checked = false;
    });
    if (!serviceId) return;
    var target = root.querySelector('[data-hh-svc="' + String(serviceId) + '"]');
    if (target) target.checked = true;
  }

  function open(opts){
    opts = opts || {};
    if (window.HomeHelpLogin) HomeHelpLogin.close();
    if (window.HomeHelpRegister) HomeHelpRegister.close();
    if (window.HomeHelpWorkerLogin) HomeHelpWorkerLogin.close();
    lastFocus = document.activeElement;
    root.hidden = false;
    document.body.style.overflow = 'hidden';
    goStep(1);
    if (opts.serviceId) preselectService(opts.serviceId);
    else preselectService(null);
    syncChef();
    if (window.lucide) lucide.createIcons();
  }
  function close(){
    root.hidden = true;
    document.body.style.overflow = '';
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  window.HomeHelpBook = { open: open, close: close };

  root.querySelectorAll('[data-hh-book-close]').forEach(function(el){
    el.addEventListener('click', close);
  });
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape' && !root.hidden) close();
  });

  root.querySelectorAll('[data-hh-svc]').forEach(function(chk){
    chk.addEventListener('change', syncChef);
  });
  root.querySelectorAll('input[name="frequency"]').forEach(function(r){
    r.addEventListener('change', function(){
      document.getElementById('hhBookTempDays').hidden = r.value !== 'Temporary Work' || !r.checked;
    });
  });

  root.querySelectorAll('[data-hh-next]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var from = Number(btn.getAttribute('data-hh-next'));
      if (from === 1) {
        if (!root.querySelectorAll('[data-hh-svc]:checked').length) {
          showError('Please select at least one service.'); return;
        }
        if (!root.querySelector('input[name="frequency"]:checked')) {
          showError('Please choose how often you need help.'); return;
        }
        goStep(2); return;
      }
      if (from === 2) goStep(3);
    });
  });
  root.querySelectorAll('[data-hh-back]').forEach(function(btn){
    btn.addEventListener('click', function(){
      goStep(Number(btn.getAttribute('data-hh-back')) - 1);
    });
  });

  document.addEventListener('click', function(e){
    var a = e.target.closest('a[data-open-book], a[data-book-wizard], button[data-open-book], [data-open-book]');
    if (!a) return;
    if (a.tagName === 'A' || a.hasAttribute('data-open-book') || a.hasAttribute('data-book-wizard')) {
      e.preventDefault();
    }
    var sid = a.getAttribute('data-book-service') || '';
    open(sid ? { serviceId: sid } : {});
    var mob = document.getElementById('mobileMenu');
    var ham = document.getElementById('hamburger');
    if (mob) mob.classList.remove('open');
    if (ham) ham.classList.remove('open');
  });

  try {
    var q = new URLSearchParams(window.location.search);
    if (q.get('book') === '1' || window.location.hash === '#book') open();
  } catch (err) {}

  form.addEventListener('submit', function(e){
    e.preventDefault();
    clearError();
    if (!loggedIn) {
      var name = document.getElementById('hhBookName');
      var phone = document.getElementById('hhBookPhone');
      var email = document.getElementById('hhBookEmail');
      var pass = document.getElementById('hhBookPass');
      var pass2 = document.getElementById('hhBookPass2');
      var bad = false;
      [name, phone, email, pass, pass2].forEach(function(el){
        if (!el || !el.value.trim()) { if (el) el.classList.add('is-bad'); bad = true; }
        else el.classList.remove('is-bad');
      });
      if (email && email.value && !/\S+@\S+\.\S+/.test(email.value)) { email.classList.add('is-bad'); bad = true; }
      if (pass && pass.value.length < 8) { pass.classList.add('is-bad'); bad = true; }
      if (pass && pass2 && pass.value !== pass2.value) { pass2.classList.add('is-bad'); bad = true; }
      if (bad) { showError('Please check the highlighted fields.'); return; }
    }

    var fd = new FormData(form);
    submitBtn.disabled = true;
    submitBtn.querySelector('span').textContent = 'Submitting…';

    fetch(api, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function(r){ return r.json().then(function(j){ return { j: j }; }); })
      .then(function(res){
        if (res.j && res.j.ok) {
          window.location.href = res.j.redirect || '/dashboard';
          return;
        }
        showError((res.j && res.j.error) || 'Could not submit request.');
        submitBtn.disabled = false;
        submitBtn.querySelector('span').textContent = 'Submit request';
      })
      .catch(function(){
        showError('Network error. Please try again.');
        submitBtn.disabled = false;
        submitBtn.querySelector('span').textContent = 'Submit request';
      });
  });
})();
</script>
