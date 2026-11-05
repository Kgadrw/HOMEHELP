<?php
/**
 * Shared dashboard sidebar + topbar open.
 * Expects:
 *   $dash_role   = client|provider|admin|agent
 *   $dash_page   = active key (dashboard|book|bookings|profile|apply|applications|users|services|team|reports|...)
 *   $user_name   = display name
 *   $page_heading = topbar title
 * Optional:
 *   $notif_count, $badge_pending
 *   $topbar_action_href, $topbar_action_label, $topbar_action_icon
 */
$dash_role     = $dash_role ?? ($_SESSION['user_role'] ?? 'client');
$dash_page     = $dash_page ?? 'dashboard';
$user_name     = $user_name ?? ($_SESSION['user_name'] ?? 'User');
$page_heading  = $page_heading ?? 'Dashboard';
$notif_count   = (int)($notif_count ?? 0);
$badge_pending = (int)($badge_pending ?? 0);
$initial       = strtoupper(substr($user_name, 0, 1));

$role_labels = [
  'client'   => 'Client',
  'provider' => 'Job Seeker',
  'admin'    => 'Admin',
  'agent'    => 'Agent',
];
$role_label = $role_labels[$dash_role] ?? ucfirst($dash_role);

$nav = [];
if ($dash_role === 'client') {
  $nav = [
    ['section' => 'Main'],
    ['key' => 'dashboard', 'href' => BASE_URL . '/dashboard', 'icon' => 'layout-dashboard', 'label' => 'Dashboard'],
    ['key' => 'book', 'href' => BASE_URL . '/book', 'icon' => 'clipboard-list', 'label' => 'Book a Service'],
    ['key' => 'bookings', 'href' => BASE_URL . '/my-bookings', 'icon' => 'folder', 'label' => 'My Bookings', 'badge' => $badge_pending],
    ['section' => 'Account'],
    ['key' => 'profile', 'href' => BASE_URL . '/profile', 'icon' => 'user', 'label' => 'My Profile'],
    ['section' => 'Help'],
    ['key' => 'faq', 'href' => BASE_URL . '/#faq', 'icon' => 'circle-help', 'label' => 'FAQ'],
  ];
  if (empty($topbar_action_href)) {
    $topbar_action_href  = BASE_URL . '/book';
    $topbar_action_label = 'Book Service';
    $topbar_action_icon  = 'plus';
  }
} elseif ($dash_role === 'provider') {
  $nav = [
    ['key' => 'dashboard', 'href' => BASE_URL . '/provider-dashboard', 'icon' => 'layout-dashboard', 'label' => 'Dashboard'],
    ['key' => 'apply', 'href' => BASE_URL . '/apply', 'icon' => 'file-plus', 'label' => 'Apply for a Job'],
    ['key' => 'applications', 'href' => BASE_URL . '/my-applications', 'icon' => 'folder', 'label' => 'My Applications', 'badge' => $badge_pending],
  ];
  if (empty($topbar_action_href)) {
    $topbar_action_href  = BASE_URL . '/apply';
    $topbar_action_label = 'Apply Now';
    $topbar_action_icon  = 'plus';
  }
} elseif ($dash_role === 'admin') {
  $nav = [
    ['key' => 'dashboard', 'href' => BASE_URL . '/admin', 'icon' => 'layout-dashboard', 'label' => 'Dashboard'],
    ['key' => 'bookings', 'href' => BASE_URL . '/admin/bookings', 'icon' => 'calendar-check', 'label' => 'Bookings'],
    ['key' => 'applications', 'href' => BASE_URL . '/admin/applications', 'icon' => 'file-text', 'label' => 'Applications'],
    ['key' => 'users', 'href' => BASE_URL . '/admin/users', 'icon' => 'users', 'label' => 'Users'],
    ['key' => 'services', 'href' => BASE_URL . '/admin/services', 'icon' => 'layers', 'label' => 'Services'],
    ['key' => 'team', 'href' => BASE_URL . '/admin/team', 'icon' => 'shield', 'label' => 'Team'],
    ['key' => 'reports', 'href' => BASE_URL . '/admin/reports', 'icon' => 'bar-chart-3', 'label' => 'Reports'],
    ['key' => 'settings', 'href' => BASE_URL . '/settings', 'icon' => 'settings', 'label' => 'Settings'],
  ];
} elseif ($dash_role === 'agent') {
  $nav = [
    ['key' => 'dashboard', 'href' => BASE_URL . '/agent', 'icon' => 'layout-dashboard', 'label' => 'Dashboard'],
    ['key' => 'bookings', 'href' => BASE_URL . '/agent/bookings', 'icon' => 'calendar-check', 'label' => 'Bookings', 'badge' => (int)($badge_bookings ?? 0)],
    ['key' => 'applications', 'href' => BASE_URL . '/agent/applications', 'icon' => 'file-text', 'label' => 'Applications', 'badge' => (int)($badge_apps ?? 0)],
    ['key' => 'reports', 'href' => BASE_URL . '/agent/reports', 'icon' => 'bar-chart-3', 'label' => 'Reports'],
    ['key' => 'settings', 'href' => BASE_URL . '/settings', 'icon' => 'settings', 'label' => 'Settings'],
  ];
}

$logo = BASE_URL . '/Image/homehelp-logo.png';
$user_block_bottom = ($dash_role === 'provider');
$hide_user_block   = in_array($dash_role, ['agent', 'admin'], true);
?>
<div class="dash-wrap">
  <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
  <aside class="sidebar" id="sidebar">
    <a href="<?= BASE_URL ?>" class="sidebar-logo">
      <img src="<?= $logo ?>" alt="Home Help"/>
      <span>HomeHelp</span>
    </a>
    <?php if (!$user_block_bottom && !$hide_user_block): ?>
    <div class="sidebar-user">
      <div class="user-avatar"><?= htmlspecialchars($initial) ?></div>
      <div class="user-info">
        <strong><?= htmlspecialchars($user_name) ?></strong>
        <span><?= htmlspecialchars($role_label) ?></span>
      </div>
    </div>
    <?php endif; ?>
    <nav class="sidebar-nav">
      <?php foreach ($nav as $item): ?>
        <?php if (isset($item['section'])): ?>
          <div class="nav-section-label"><?= htmlspecialchars($item['section']) ?></div>
        <?php else: ?>
          <a href="<?= htmlspecialchars($item['href']) ?>" class="nav-item<?= $dash_page === $item['key'] ? ' active' : '' ?>">
            <i data-lucide="<?= htmlspecialchars($item['icon']) ?>"></i>
            <?= htmlspecialchars($item['label']) ?>
            <?php if (!empty($item['badge'])): ?><span class="nav-badge"><?= (int)$item['badge'] ?></span><?php endif; ?>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer<?= $user_block_bottom ? ' sidebar-footer-user' : '' ?>">
      <?php if ($user_block_bottom): ?>
      <div class="sidebar-user sidebar-user-bottom">
        <div class="user-avatar"><?= htmlspecialchars($initial) ?></div>
        <div class="user-info">
          <strong><?= htmlspecialchars($user_name) ?></strong>
          <span><?= htmlspecialchars($role_label) ?></span>
        </div>
      </div>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>/logout" class="btn-logout"><i data-lucide="log-out"></i> Sign Out</a>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <div class="topbar-left">
        <button class="burger" type="button" onclick="toggleSidebar()" aria-label="Menu"><span></span><span></span><span></span></button>
        <span class="topbar-title"><?= htmlspecialchars($page_heading) ?></span>
      </div>
      <div class="topbar-right">
        <?php if ($notif_count > 0 || isset($show_notif)): ?>
          <a href="#notifications" class="topbar-notif" title="Notifications">
            <i data-lucide="bell"></i>
            <?php if ($notif_count > 0): ?><span class="notif-dot"></span><?php endif; ?>
          </a>
        <?php endif; ?>
        <?php if (!empty($topbar_action_href)): ?>
          <a href="<?= htmlspecialchars($topbar_action_href) ?>" class="btn-primary-sm">
            <?php if (!empty($topbar_action_icon)): ?><i data-lucide="<?= htmlspecialchars($topbar_action_icon) ?>"></i><?php endif; ?>
            <?= htmlspecialchars($topbar_action_label ?? 'Action') ?>
          </a>
        <?php endif; ?>
      </div>
    </header>
    <div class="content">
