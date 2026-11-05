<?php
/**
 * Shared favicon + SEO meta helpers.
 * Optional before include:
 *   $seo_title, $seo_description, $seo_keywords, $seo_canonical, $seo_robots
 *   $seo_emit_basics = true to also print title/description/keywords/canonical
 */
$seo_base = rtrim(defined('BASE_URL') ? BASE_URL : '', '/');
$favicon  = $seo_base . '/Image/favicon.png';
$logo_url = $seo_base . '/Image/homehelp-logo.png';

$default_keywords = 'Home Help, Home Help Rwanda, homehelp.rw, home cleaning Kigali, cleaning services Kigali, cleaning services Rwanda, house cleaning Rwanda, professional cleaners Kigali, housekeeping services Kigali, housekeeping Rwanda, maid services Kigali, domestic workers Rwanda, office cleaning Kigali, commercial cleaning Rwanda, Airbnb cleaning Kigali, apartment cleaning Kigali, deep cleaning service, same-day cleaning Kigali, best cleaning company in Kigali, housekeeper Kigali, home chef Kigali, personal chef Rwanda, nanny services Kigali, babysitter Kigali, caregiver Kigali, elderly care Rwanda, live-in maid Rwanda, domestic worker Rwanda, house help Rwanda, hire domestic worker Rwanda, find a nanny Kigali, book a cleaner online Rwanda';

$seo_keywords    = $seo_keywords    ?? $default_keywords;
$seo_description = $seo_description ?? 'Home Help Rwanda — trusted domestic services in Kigali. Book home cleaners, housekeepers, nannies, home chefs and caregivers.';
$seo_robots      = $seo_robots      ?? 'index, follow';
$seo_emit_basics = $seo_emit_basics ?? false;
?>
<link rel="icon" type="image/png" href="<?= htmlspecialchars($favicon) ?>"/>
<link rel="shortcut icon" type="image/png" href="<?= htmlspecialchars($favicon) ?>"/>
<link rel="apple-touch-icon" href="<?= htmlspecialchars($favicon) ?>"/>
<meta name="theme-color" content="#0D1B4B"/>
<meta name="application-name" content="Home Help"/>
<meta name="keywords" content="<?= htmlspecialchars($seo_keywords) ?>"/>
<?php if ($seo_emit_basics): ?>
<?php if (!empty($seo_title)): ?><title><?= htmlspecialchars($seo_title) ?></title><?php endif; ?>
<meta name="description" content="<?= htmlspecialchars($seo_description) ?>"/>
<meta name="author" content="Brilliant Elite Ltd"/>
<meta name="robots" content="<?= htmlspecialchars($seo_robots) ?>"/>
<?php if (!empty($seo_canonical)): ?><link rel="canonical" href="<?= htmlspecialchars($seo_canonical) ?>"/><?php endif; ?>
<?php endif; ?>
