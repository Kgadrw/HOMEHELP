<?php
/**
 * Shared dashboard <head> assets.
 * Expects: $page_title (string)
 */
$page_title = $page_title ?? 'Dashboard';
$assets_base = rtrim(BASE_URL, '/') . '/assets';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?= htmlspecialchars($page_title) ?> | Home Help</title>
<?php
$seo_robots = 'noindex, nofollow';
include __DIR__ . '/seo-meta.php';
?>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?= $assets_base ?>/css/dash.css"/>
<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
