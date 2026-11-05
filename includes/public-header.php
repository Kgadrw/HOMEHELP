<?php
/**
 * Shared public site header — same markup/behavior as the homepage.
 * Optional: $nav_active = home|find-worker|career|services|how-it-works|stories|contact|faq
 */
$nav_active = $nav_active ?? '';
$base = rtrim(BASE_URL, '/');
$logo = $base . '/Image/icon/homehelp_logo_transparent_dark.svg';

$is = function (string $key) use ($nav_active): string {
  return $nav_active === $key ? ' active' : '';
};
?>
<link rel="stylesheet" href="<?= $base ?>/assets/css/site-nav.css"/>
<nav class="navbar" id="navbar">
  <div class="container">
    <div class="navbar-inner">
      <a href="<?= $base ?>/" class="nav-logo">
        <img src="<?= $logo ?>" alt="Home Help Logo"/>
        <span class="nav-logo-text">HomeHelp</span>
      </a>
      <ul class="nav-links">
        <li><a href="<?= $base ?>/find-worker" class="<?= trim($is('find-worker')) ?>">Find Workers</a></li>
        <li><a href="<?= $base ?>/career" class="<?= trim($is('career')) ?>">Career</a></li>
        <li><a href="<?= $base ?>/#home-services" class="<?= trim($is('services')) ?>">Services</a></li>
        <li><a href="<?= $base ?>/#how-it-works" class="<?= trim($is('how-it-works')) ?>">How It Works</a></li>
        <li><a href="<?= $base ?>/#testimonials" class="<?= trim($is('stories')) ?>">Stories</a></li>
        <li><a href="<?= $base ?>/#contact" class="<?= trim($is('contact')) ?>">Contact</a></li>
      </ul>
      <div class="nav-cta">
        <a href="<?= $base ?>/login" class="nav-login" data-open-login>Login</a>
        <a href="<?= $base ?>/register" class="nav-register" data-open-register>Register</a>
        <a href="<?= $base ?>/login" class="btn-find-worker" data-open-login>Hire Now</a>
      </div>
      <button class="hamburger" id="hamburger" aria-label="Menu" type="button">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</nav>

<div class="mobile-menu" id="mobileMenu">
  <a href="<?= $base ?>/find-worker" class="mob-link<?= $is('find-worker') ?>">Find Workers</a>
  <a href="<?= $base ?>/career" class="mob-link<?= $is('career') ?>">Career</a>
  <a href="<?= $base ?>/#home-services" class="mob-link<?= $is('services') ?>">Services</a>
  <a href="<?= $base ?>/#how-it-works" class="mob-link<?= $is('how-it-works') ?>">How It Works</a>
  <a href="<?= $base ?>/#testimonials" class="mob-link<?= $is('stories') ?>">Stories</a>
  <a href="<?= $base ?>/#contact" class="mob-link<?= $is('contact') ?>">Contact</a>
  <a href="<?= $base ?>/login" class="mob-link" data-open-login>Login</a>
  <a href="<?= $base ?>/register" class="mob-link" data-open-register>Register</a>
  <div class="mobile-menu-footer">
    <a href="<?= $base ?>/login" class="btn-find-worker" data-open-login>Hire Now</a>
  </div>
</div>
<script src="<?= $base ?>/assets/js/site-nav.js" defer></script>
