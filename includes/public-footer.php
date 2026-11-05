<?php
$year = date('Y');
$logo = rtrim(BASE_URL, '/') . '/Image/homehelp-logo.png';
?>
<footer class="footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <a href="<?= BASE_URL ?>/" class="footer-brand">
          <img src="<?= $logo ?>" alt="Home Help"/>
          <span>HomeHelp</span>
        </a>
        <p class="footer-desc">Rwanda’s trusted marketplace for domestic services, connecting households with verified workers across the country.</p>
        <div class="social-links">
          <a class="social-link" href="https://www.facebook.com/" target="_blank" rel="noopener" aria-label="Facebook">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M14 13.5h2.5l1-4H14v-2c0-1.03 0-2 2-2h1.5V2.14C17.026 2.087 15.824 2 14.647 2 11.928 2 10 3.657 10 6.7v2.8H7v4h3V22h4z"/></svg>
          </a>
          <a class="social-link" href="https://www.instagram.com/" target="_blank" rel="noopener" aria-label="Instagram">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M7.8 2h8.4C19.4 2 22 4.6 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8C4.6 22 2 19.4 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2m-.2 2A3.6 3.6 0 0 0 4 7.6v8.8C4 18.39 5.61 20 7.6 20h8.8a3.6 3.6 0 0 0 3.6-3.6V7.6C20 5.61 18.39 4 16.4 4H7.6m9.65 1.5a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5M12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10m0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/></svg>
          </a>
          <a class="social-link" href="https://x.com/" target="_blank" rel="noopener" aria-label="X">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.743l7.727-8.835L1.254 2.25H8.08l4.264 5.686L18.244 2.25zm-1.161 17.52h1.833L7.084 4.126H5.117L17.083 19.77z"/></svg>
          </a>
          <a class="social-link" href="https://wa.me/250790533144" target="_blank" rel="noopener" aria-label="WhatsApp">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
          </a>
        </div>
      </div>
      <div class="footer-col">
        <h5>Services</h5>
        <ul class="footer-links">
          <li><a href="<?= BASE_URL ?>/home-cleaning">Home Cleaning</a></li>
          <li><a href="<?= BASE_URL ?>/housekeeping">Housekeeping</a></li>
          <li><a href="<?= BASE_URL ?>/home-chef">Home Chef</a></li>
          <li><a href="<?= BASE_URL ?>/nanny">Nanny &amp; Caregiver</a></li>
          <li><a href="<?= BASE_URL ?>/office-cleaning">Office Cleaning</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h5>Platform</h5>
        <ul class="footer-links">
          <li><a href="<?= BASE_URL ?>/#services">Why Choose Us</a></li>
          <li><a href="<?= BASE_URL ?>/#how-it-works">How It Works</a></li>
          <li><a href="<?= BASE_URL ?>/career">Career</a></li>
          <li><a href="<?= BASE_URL ?>/#faq">FAQ</a></li>
          <li><a href="<?= BASE_URL ?>/#contact">Contact</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h5>Account</h5>
        <ul class="footer-links">
          <li><a href="<?= BASE_URL ?>/login" data-open-login>Login</a></li>
          <li><a href="<?= BASE_URL ?>/register" data-open-register>Register</a></li>
          <li><a href="<?= BASE_URL ?>/worker-login" data-open-worker-login>Job Seeker Login</a></li>
          <li><a href="<?= BASE_URL ?>/worker-register">Job Seeker Register</a></li>
          <li><a href="<?= BASE_URL ?>/admin-login">Admin Login</a></li>
          <li><a href="<?= BASE_URL ?>/staff-login">Team Login</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <p>&copy; <?= $year ?> Home Help · Rwanda</p>
      <div style="display:flex;gap:16px">
        <a href="<?= BASE_URL ?>/privacy">Privacy Policy</a>
        <a href="<?= BASE_URL ?>/terms">Terms of Service</a>
      </div>
    </div>
  </div>
</footer>
<script>
(function(){ if(window.lucide) lucide.createIcons(); })();
</script>
<?php
include __DIR__ . '/worker-login-modal.php';
include __DIR__ . '/client-login-modal.php';
include __DIR__ . '/register-modal.php';
include __DIR__ . '/book-modal.php';
?>
