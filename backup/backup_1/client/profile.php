<?php
require_once '../config/db.php';
require_once '../config/config.php';

// Allow both client and provider
if(!isLoggedIn() || !in_array($_SESSION['user_role'] ?? '', ['client','provider'])) { redirect(BASE_URL . '/login'); }

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$error     = '';
$success   = '';

// ── FETCH CURRENT USER DATA ───────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// ── HANDLE PROFILE UPDATE ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCSRF();

    // ── UPDATE PROFILE INFO ───────────────────────────────────
    if ($_POST['action'] === 'update_profile') {
        $name  = sanitize($_POST['name']  ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $email = sanitize($_POST['email'] ?? '');

        if (!$name || !$phone || !$email) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Check email not taken by another user
            $chk = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $chk->bind_param('si', $email, $user_id);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $error = 'This email is already used by another account.';
            } else {
                $upd = $conn->prepare("UPDATE users SET name=?, email=?, phone=? WHERE id=?");
                $upd->bind_param('sssi', $name, $email, $phone, $user_id);
                if ($upd->execute()) {
                    $_SESSION['user_name'] = $name;
                    $success = 'Profile updated successfully!';
                    $user['name']  = $name;
                    $user['email'] = $email;
                    $user['phone'] = $phone;
                } else {
                    $error = 'Something went wrong. Please try again.';
                }
            }
        }
    }

    // ── CHANGE PASSWORD ───────────────────────────────────────
    elseif ($_POST['action'] === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!$current || !$new || !$confirm) {
            $error = 'Please fill in all password fields.';
        } elseif (!password_verify($current, $user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $hashed = password_hash($new, PASSWORD_BCRYPT);
            $upd = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $upd->bind_param('si', $hashed, $user_id);
            if ($upd->execute()) {
                $success = 'Password changed successfully!';
            } else {
                $error = 'Something went wrong. Please try again.';
            }
        }
    }

    // ── UPLOAD PHOTO ──────────────────────────────────────────
    elseif ($_POST['action'] === 'upload_photo') {
        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Please select a photo to upload.';
        } else {
            $file     = $_FILES['photo'];
            $allowed  = ['image/jpeg','image/png','image/webp','image/gif'];
            $max_size = 2 * 1024 * 1024; // 2MB

            if (!in_array($file['type'], $allowed)) {
                $error = 'Only JPG, PNG, WebP or GIF images are allowed.';
            } elseif ($file['size'] > $max_size) {
                $error = 'Image must be smaller than 2MB.';
            } else {
                $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
                $upload_dir = dirname(__DIR__) . '/uploads/avatars/';

                // Create dir if not exists
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $dest = $upload_dir . $filename;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    // Delete old photo if exists
                    if (!empty($user['photo']) && file_exists($upload_dir . $user['photo'])) {
                        unlink($upload_dir . $user['photo']);
                    }
                    $upd = $conn->prepare("UPDATE users SET photo=? WHERE id=?");
                    $upd->bind_param('si', $filename, $user_id);
                    $upd->execute();
                    $user['photo'] = $filename;
                    $success = 'Profile photo updated!';
                } else {
                    $error = 'Failed to upload photo. Please try again.';
                }
            }
        }
    }
}

$notif_count = unreadNotifCount();
$avatar_url  = !empty($user['photo'])
    ? BASE_URL . '/uploads/avatars/' . $user['photo']
    : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>My Profile | Home Help</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<style>
:root{
  --navy:#0D1B4B;--navy-mid:#152268;--navy-light:#2E5FC2;--navy-soft:#D4DCF5;
  --cream:#FDFAF5;--cream-dark:#F2EDE3;
  --white:#FFFFFF;--text-dark:#1A1A1A;--text-mid:#4A4A4A;--text-soft:#888888;
  --danger:#EF4444;--danger-bg:#FEF2F2;
  --success:#10B981;--success-bg:#ECFDF5;
  --shadow-sm:0 2px 12px rgba(13,27,75,0.07);
  --shadow-md:0 6px 28px rgba(13,27,75,0.11);
  --radius:14px;--radius-sm:8px;--sidebar:260px;
  --font-display:'Cormorant Garamond',Georgia,serif;
  --font-body:'Plus Jakarta Sans',sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:var(--font-body);background:var(--cream);color:var(--text-dark);overflow-x:hidden}
a{text-decoration:none;color:inherit}
.dash-wrap{display:flex;min-height:100vh}

/* ── SIDEBAR ── */
.sidebar{width:var(--sidebar);flex-shrink:0;background:var(--navy);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:200;transition:transform 0.3s ease;overflow-y:auto}
.sidebar-logo{display:flex;align-items:center;gap:10px;padding:24px 20px 20px;border-bottom:1px solid rgba(255,255,255,0.08)}
.sidebar-logo img{width:36px;height:36px;object-fit:contain}
.sidebar-logo-text{font-family:var(--font-display);font-size:1.3rem;font-weight:700;color:var(--white)}
.sidebar-user{padding:20px;border-bottom:1px solid rgba(255,255,255,0.08);display:flex;align-items:center;gap:12px}
.user-avatar-sm{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,var(--navy-light),var(--navy-mid));display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:1.1rem;font-weight:700;color:var(--white);flex-shrink:0;overflow:hidden}
.user-avatar-sm img{width:100%;height:100%;object-fit:cover}
.user-info strong{display:block;font-size:0.88rem;font-weight:700;color:var(--white);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.user-info span{font-size:0.73rem;color:rgba(255,255,255,0.5)}
.sidebar-nav{padding:16px 0;flex:1}
.nav-section-label{font-size:0.66rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:rgba(255,255,255,0.3);padding:12px 20px 6px}
.nav-item{display:flex;align-items:center;gap:12px;padding:11px 20px;font-size:0.87rem;font-weight:500;color:rgba(255,255,255,0.65);cursor:pointer;transition:all 0.2s;position:relative;text-decoration:none}
.nav-item:hover{color:var(--white);background:rgba(255,255,255,0.06)}
.nav-item.active{color:var(--white);background:rgba(255,255,255,0.1);font-weight:600}
.nav-item.active::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--navy-light);border-radius:0 3px 3px 0}
.nav-icon{font-size:1.05rem;width:20px;text-align:center;flex-shrink:0}
.nav-badge{margin-left:auto;background:var(--navy-light);color:var(--white);font-size:0.65rem;font-weight:700;padding:2px 6px;border-radius:99px}
.sidebar-footer{padding:16px 20px;border-top:1px solid rgba(255,255,255,0.08)}
.btn-logout{display:flex;align-items:center;gap:8px;width:100%;padding:10px 14px;border-radius:var(--radius-sm);background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.2);color:rgba(255,100,100,0.9);font-size:0.84rem;font-weight:600;cursor:pointer;transition:all 0.2s;text-decoration:none;font-family:var(--font-body)}
.btn-logout:hover{background:rgba(239,68,68,0.2)}

/* ── MAIN ── */
.main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{background:var(--white);border-bottom:1px solid var(--cream-dark);padding:0 28px;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.topbar-left{display:flex;align-items:center;gap:16px}
.burger{display:none;background:none;border:none;cursor:pointer;padding:6px;flex-direction:column;gap:4px}
.burger span{display:block;width:20px;height:2px;background:var(--text-dark);border-radius:2px}
.topbar-title{font-family:var(--font-display);font-size:1.4rem;font-weight:700;color:var(--navy)}
.topbar-right{display:flex;align-items:center;gap:12px}
.topbar-notif{position:relative;width:38px;height:38px;border-radius:var(--radius-sm);background:var(--cream);border:1.5px solid var(--cream-dark);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:1.1rem;transition:all 0.2s;text-decoration:none}
.topbar-notif:hover{border-color:var(--navy-light);background:var(--navy-soft)}
.notif-dot{position:absolute;top:6px;right:6px;width:8px;height:8px;border-radius:50%;background:var(--danger);border:2px solid var(--white)}
.btn-back{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:99px;background:var(--cream);border:1.5px solid var(--cream-dark);color:var(--text-mid);font-size:0.83rem;font-weight:600;transition:all 0.25s;text-decoration:none}
.btn-back:hover{border-color:var(--navy-light);background:var(--navy-soft);color:var(--navy)}

/* ── CONTENT ── */
.content{padding:28px;flex:1;max-width:860px}
.page-header{margin-bottom:28px}
.page-header h1{font-family:var(--font-display);font-size:2rem;font-weight:700;color:var(--navy);margin-bottom:6px}
.page-header h1 em{font-style:italic;color:var(--navy-light)}
.page-header p{font-size:0.9rem;color:var(--text-soft)}

/* ── ALERTS ── */
.alert{padding:13px 16px;border-radius:var(--radius-sm);font-size:0.87rem;font-weight:500;margin-bottom:22px;display:flex;align-items:center;gap:10px}
.alert-error{background:var(--danger-bg);border:1.5px solid #FECACA;color:#B91C1C}
.alert-success{background:var(--success-bg);border:1.5px solid #BBF7D0;color:#065F46}

/* ── PROFILE HEADER CARD ── */
.profile-hero{background:linear-gradient(135deg,var(--navy) 0%,var(--navy-mid) 100%);border-radius:var(--radius);padding:28px;display:flex;align-items:center;gap:24px;margin-bottom:24px;position:relative;overflow:hidden}
.profile-hero::before{content:'';position:absolute;top:-40%;right:-10%;width:50%;height:200%;background:radial-gradient(ellipse,rgba(46,95,194,0.3) 0%,transparent 60%);border-radius:50%}
.profile-avatar-wrap{position:relative;flex-shrink:0;z-index:1}
.profile-avatar{width:88px;height:88px;border-radius:50%;background:linear-gradient(135deg,var(--navy-light),var(--navy));border:3px solid rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:2.2rem;font-weight:700;color:var(--white);overflow:hidden;cursor:pointer;transition:all 0.3s}
.profile-avatar img{width:100%;height:100%;object-fit:cover}
.avatar-overlay{position:absolute;inset:0;border-radius:50%;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity 0.3s;cursor:pointer;font-size:1.2rem}
.profile-avatar-wrap:hover .avatar-overlay{opacity:1}
.profile-info{flex:1;z-index:1}
.profile-name{font-family:var(--font-display);font-size:1.7rem;font-weight:700;color:var(--white);margin-bottom:4px}
.profile-email{font-size:0.88rem;color:rgba(255,255,255,0.6);margin-bottom:10px}
.profile-badges{display:flex;gap:8px;flex-wrap:wrap}
.profile-badge{display:inline-flex;align-items:center;gap:5px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.15);border-radius:99px;padding:4px 12px;font-size:0.75rem;color:rgba(255,255,255,0.85);font-weight:600}

/* ── TABS ── */
.tabs{display:flex;gap:4px;background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);padding:5px;margin-bottom:24px}
.tab{flex:1;text-align:center;padding:10px 16px;border-radius:var(--radius-sm);font-size:0.85rem;font-weight:600;color:var(--text-soft);cursor:pointer;transition:all 0.25s;border:none;background:transparent;font-family:var(--font-body)}
.tab:hover{color:var(--navy);background:var(--cream)}
.tab.active{background:var(--navy);color:var(--white)}
.tab-panel{display:none}
.tab-panel.active{display:block}

/* ── FORM CARD ── */
.form-card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);padding:28px}
.form-card-title{font-family:var(--font-display);font-size:1.2rem;font-weight:700;color:var(--navy);margin-bottom:6px}
.form-card-sub{font-size:0.84rem;color:var(--text-soft);margin-bottom:24px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.form-group{display:flex;flex-direction:column;gap:6px;margin-bottom:18px}
.form-group:last-of-type{margin-bottom:0}
.form-group label{font-size:0.82rem;font-weight:600;color:var(--text-dark)}
.form-group label .req{color:var(--danger);margin-left:2px}
.input-wrap{position:relative}
.input-icon{position:absolute;left:13px;top:50%;transform:translateY(-50%);font-size:0.9rem;pointer-events:none;z-index:1}
.form-group input{width:100%;padding:12px 14px 12px 40px;border:1.5px solid var(--cream-dark);border-radius:var(--radius-sm);font-family:var(--font-body);font-size:0.88rem;color:var(--text-dark);background:var(--cream);transition:all 0.2s;outline:none}
.form-group input:focus{border-color:var(--navy-light);background:var(--white);box-shadow:0 0 0 3px rgba(46,95,194,0.1)}
.form-group input.error-field{border-color:var(--danger)}

/* Strength */
.strength-bar{display:grid;grid-template-columns:repeat(4,1fr);gap:4px;margin-top:6px}
.strength-seg{height:3px;border-radius:2px;background:var(--cream-dark);transition:background 0.3s}
.strength-seg.weak{background:#ef4444}.strength-seg.fair{background:#f59e0b}.strength-seg.good{background:#22c55e}
.strength-text{font-size:0.73rem;color:var(--text-soft);margin-top:3px}

/* Eye toggle */
.eye-toggle{position:absolute;right:13px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:1rem;color:var(--text-soft);padding:0;line-height:1}
.eye-toggle:hover{color:var(--navy)}

/* Submit */
.form-actions{display:flex;align-items:center;gap:12px;margin-top:24px;flex-wrap:wrap}
.btn-save{display:inline-flex;align-items:center;gap:8px;padding:12px 28px;border-radius:99px;background:var(--navy);color:var(--white);font-family:var(--font-body);font-size:0.9rem;font-weight:700;border:none;cursor:pointer;transition:all 0.3s}
.btn-save:hover{background:var(--navy-mid);transform:translateY(-2px);box-shadow:var(--shadow-md)}
.btn-save.loading{opacity:0.7;cursor:not-allowed;pointer-events:none}

/* Photo upload tab */
.photo-upload-area{border:2px dashed var(--cream-dark);border-radius:var(--radius);padding:48px 24px;text-align:center;cursor:pointer;transition:all 0.3s;background:var(--cream);position:relative}
.photo-upload-area:hover,.photo-upload-area.dragover{border-color:var(--navy-light);background:var(--navy-soft)}
.photo-upload-area input[type="file"]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.upload-icon{font-size:2.5rem;margin-bottom:12px}
.upload-title{font-family:var(--font-display);font-size:1.2rem;font-weight:700;color:var(--navy);margin-bottom:6px}
.upload-sub{font-size:0.84rem;color:var(--text-soft)}
.upload-limit{font-size:0.76rem;color:var(--text-soft);margin-top:6px}
.photo-preview{margin-top:20px;display:none}
.photo-preview img{width:100px;height:100px;border-radius:50%;object-fit:cover;border:3px solid var(--navy-soft);margin:0 auto;display:block}
.photo-preview p{text-align:center;font-size:0.82rem;color:var(--text-soft);margin-top:8px}

/* Current photo display */
.current-photo{display:flex;align-items:center;gap:16px;background:var(--cream);border:1.5px solid var(--cream-dark);border-radius:var(--radius-sm);padding:16px;margin-bottom:20px}
.current-photo img,.current-photo .current-initials{width:56px;height:56px;border-radius:50%;object-fit:cover;flex-shrink:0}
.current-initials{background:linear-gradient(135deg,var(--navy-light),var(--navy));display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:1.3rem;font-weight:700;color:var(--white)}
.current-photo-info strong{display:block;font-size:0.88rem;font-weight:700;color:var(--navy);margin-bottom:2px}
.current-photo-info span{font-size:0.78rem;color:var(--text-soft)}

/* Account stats */
.stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:24px}
.stat-item{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);padding:18px;text-align:center}
.stat-num{font-family:var(--font-display);font-size:1.8rem;font-weight:700;color:var(--navy)}
.stat-label{font-size:0.76rem;color:var(--text-soft);margin-top:4px}

/* Mobile */
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:199}
@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}
  .sidebar-overlay.open{display:block}.main{margin-left:0}.burger{display:flex}
  .content{padding:18px}.topbar{padding:0 16px}
  .form-row{grid-template-columns:1fr}
  .profile-hero{flex-direction:column;text-align:center}
  .stats-row{grid-template-columns:1fr 1fr}
  .tabs{flex-wrap:wrap}
}
</style>
</head>
<body>
<div class="dash-wrap">
  <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <img src="../Image/icon/homehelp_logo_transparent_white.svg" alt="Home Help" onerror="this.style.display='none'"/>
      <span class="sidebar-logo-text">Home Help</span>
    </div>
    <div class="sidebar-user">
      <div class="user-avatar-sm">
        <?php if($avatar_url): ?>
          <img src="<?= $avatar_url ?>" alt="<?= e($user['name']) ?>"/>
        <?php else: ?>
          <?= strtoupper(substr($user['name'],0,1)) ?>
        <?php endif; ?>
      </div>
      <div class="user-info">
        <strong><?= e($user['name']) ?></strong>
        <span><?= ucfirst($user['role'] ?? 'Client') ?></span>
      </div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">Main</div>
      <a href="<?= BASE_URL . ($user['role']=='provider' ? '/provider-dashboard' : '/dashboard') ?>" class="nav-item"><span class="nav-icon">🏠</span> Dashboard</a>
      <a href="<?= BASE_URL ?>/book"        class="nav-item"><span class="nav-icon">📋</span> Book a Service</a>
      <a href="<?= BASE_URL ?>/my-bookings" class="nav-item"><span class="nav-icon">📂</span> My Bookings</a>
      <div class="nav-section-label">Account</div>
      <a href="<?= BASE_URL ?>/profile"     class="nav-item active"><span class="nav-icon">👤</span> My Profile</a>
      <a href="<?= BASE_URL ?>/dashboard#notifications" class="nav-item">
        <span class="nav-icon">🔔</span> Notifications
        <?php if($notif_count > 0): ?><span class="nav-badge"><?= $notif_count ?></span><?php endif; ?>
      </a>
      <div class="nav-section-label">Help</div>
      <a href="<?= BASE_URL ?>/#faq"     class="nav-item"><span class="nav-icon">❓</span> FAQ</a>
      <a href="<?= BASE_URL ?>/#contact" class="nav-item"><span class="nav-icon">💬</span> Contact Support</a>
    </nav>
    <div class="sidebar-footer">
      <a href="<?= BASE_URL ?>/logout" class="btn-logout"><span>🚪</span> Sign Out</a>
    </div>
  </aside>

  <!-- MAIN -->
  <div class="main">
    <header class="topbar">
      <div class="topbar-left">
        <button class="burger" onclick="toggleSidebar()"><span></span><span></span><span></span></button>
        <span class="topbar-title">My Profile</span>
      </div>
      <div class="topbar-right">
        <a href="<?= BASE_URL ?>/dashboard#notifications" class="topbar-notif">
          🔔<?php if($notif_count > 0): ?><span class="notif-dot"></span><?php endif; ?>
        </a>
        <a href="<?= BASE_URL . ($user['role']=='provider' ? '/provider-dashboard' : '/dashboard') ?>" class="btn-back">← Dashboard</a>
      </div>
    </header>

    <div class="content">

      <!-- ALERTS -->
      <?php if($error): ?>
        <div class="alert alert-error">⚠️ <?= e($error) ?></div>
      <?php endif; ?>
      <?php if($success): ?>
        <div class="alert alert-success">✅ <?= e($success) ?></div>
      <?php endif; ?>

      <!-- PROFILE HERO -->
      <div class="profile-hero">
        <div class="profile-avatar-wrap" onclick="switchTab('photo')" title="Change photo">
          <div class="profile-avatar">
            <?php if($avatar_url): ?>
              <img src="<?= $avatar_url ?>" alt="<?= e($user['name']) ?>"/>
            <?php else: ?>
              <?= strtoupper(substr($user['name'],0,1)) ?>
            <?php endif; ?>
          </div>
          <div class="avatar-overlay">📷</div>
        </div>
        <div class="profile-info">
          <div class="profile-name"><?= e($user['name']) ?></div>
          <div class="profile-email">✉️ <?= e($user['email']) ?></div>
          <div class="profile-badges">
            <span class="profile-badge"><?= $user['role']==="provider" ? "💼 Job Seeker" : "🏠 Client" ?></span>
            <span class="profile-badge">🇷🇼 Rwanda</span>
            <span class="profile-badge">📅 Joined <?= date('M Y', strtotime($user['created_at'])) ?></span>
          </div>
        </div>
      </div>

      <!-- STATS -->
      <?php
      $total_bookings    = $conn->query("SELECT COUNT(*) FROM bookings WHERE client_id={$user_id}")->fetch_row()[0];
      $approved_bookings = $conn->query("SELECT COUNT(*) FROM bookings WHERE client_id={$user_id} AND status='approved'")->fetch_row()[0];
      $pending_bookings  = $conn->query("SELECT COUNT(*) FROM bookings WHERE client_id={$user_id} AND status='pending'")->fetch_row()[0];
      ?>
      <div class="stats-row">
        <div class="stat-item">
          <div class="stat-num"><?= $total_bookings ?></div>
          <div class="stat-label">Total Bookings</div>
        </div>
        <div class="stat-item">
          <div class="stat-num"><?= $approved_bookings ?></div>
          <div class="stat-label">Approved</div>
        </div>
        <div class="stat-item">
          <div class="stat-num"><?= $pending_bookings ?></div>
          <div class="stat-label">Pending</div>
        </div>
      </div>

      <!-- TABS -->
      <div class="tabs">
        <button class="tab active" onclick="switchTab('info')"     id="tab-info">👤 Personal Info</button>
        <button class="tab"        onclick="switchTab('password')" id="tab-password">🔒 Change Password</button>
        <button class="tab"        onclick="switchTab('photo')"    id="tab-photo">📷 Profile Photo</button>
      </div>

      <!-- TAB: PERSONAL INFO -->
      <div class="tab-panel active" id="panel-info">
        <div class="form-card">
          <div class="form-card-title">Personal Information</div>
          <div class="form-card-sub">Update your name, email address and phone number.</div>
          <form method="POST" id="profileForm">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"/>
            <input type="hidden" name="action" value="update_profile"/>
            <div class="form-group">
              <label for="name">Full Name <span class="req">*</span></label>
              <div class="input-wrap">
                <span class="input-icon">👤</span>
                <input type="text" id="name" name="name"
                  value="<?= e($user['name']) ?>" placeholder="Your full name" required/>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="email">Email Address <span class="req">*</span></label>
                <div class="input-wrap">
                  <span class="input-icon">✉️</span>
                  <input type="email" id="email" name="email"
                    value="<?= e($user['email']) ?>" placeholder="you@example.com" required/>
                </div>
              </div>
              <div class="form-group">
                <label for="phone">WhatsApp / Phone <span class="req">*</span></label>
                <div class="input-wrap">
                  <span class="input-icon">📱</span>
                  <input type="tel" id="phone" name="phone"
                    value="<?= e($user['phone'] ?? '') ?>" placeholder="+250 7XX XXX XXX" required/>
                </div>
              </div>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn-save" id="saveProfileBtn">
                <span id="saveProfileText">Save Changes →</span>
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- TAB: CHANGE PASSWORD -->
      <div class="tab-panel" id="panel-password">
        <div class="form-card">
          <div class="form-card-title">Change Password</div>
          <div class="form-card-sub">Choose a strong password with at least 8 characters.</div>
          <form method="POST" id="passwordForm">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"/>
            <input type="hidden" name="action" value="change_password"/>
            <div class="form-group">
              <label for="current_password">Current Password <span class="req">*</span></label>
              <div class="input-wrap">
                <span class="input-icon">🔒</span>
                <input type="password" id="current_password" name="current_password"
                  placeholder="Enter your current password" required/>
                <button type="button" class="eye-toggle" onclick="togglePass('current_password',this)">👁</button>
              </div>
            </div>
            <div class="form-group">
              <label for="new_password">New Password <span class="req">*</span></label>
              <div class="input-wrap">
                <span class="input-icon">🔑</span>
                <input type="password" id="new_password" name="new_password"
                  placeholder="Min. 8 characters" required
                  oninput="checkStrength(this.value)"/>
                <button type="button" class="eye-toggle" onclick="togglePass('new_password',this)">👁</button>
              </div>
              <div class="strength-bar">
                <div class="strength-seg" id="s1"></div>
                <div class="strength-seg" id="s2"></div>
                <div class="strength-seg" id="s3"></div>
                <div class="strength-seg" id="s4"></div>
              </div>
              <span class="strength-text" id="strengthText"></span>
            </div>
            <div class="form-group">
              <label for="confirm_password">Confirm New Password <span class="req">*</span></label>
              <div class="input-wrap">
                <span class="input-icon">🔑</span>
                <input type="password" id="confirm_password" name="confirm_password"
                  placeholder="Repeat new password" required/>
                <button type="button" class="eye-toggle" onclick="togglePass('confirm_password',this)">👁</button>
              </div>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn-save" id="savePassBtn">
                <span id="savePassText">Change Password →</span>
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- TAB: PROFILE PHOTO -->
      <div class="tab-panel" id="panel-photo">
        <div class="form-card">
          <div class="form-card-title">Profile Photo</div>
          <div class="form-card-sub">Upload a clear photo so agents and workers can recognize you.</div>

          <!-- Current photo -->
          <div class="current-photo">
            <?php if($avatar_url): ?>
              <img src="<?= $avatar_url ?>" alt="Current photo"/>
            <?php else: ?>
              <div class="current-initials"><?= strtoupper(substr($user['name'],0,1)) ?></div>
            <?php endif; ?>
            <div class="current-photo-info">
              <strong>Current Photo</strong>
              <span><?= $avatar_url ? 'Custom photo uploaded' : 'Using initials avatar — upload a photo!' ?></span>
            </div>
          </div>

          <form method="POST" enctype="multipart/form-data" id="photoForm">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"/>
            <input type="hidden" name="action" value="upload_photo"/>

            <div class="photo-upload-area" id="uploadArea">
              <input type="file" name="photo" accept="image/*" id="photoInput"
                onchange="previewPhoto(this)"/>
              <div class="upload-icon">📷</div>
              <div class="upload-title">Click to upload or drag & drop</div>
              <div class="upload-sub">JPG, PNG or WebP — clear face photo recommended</div>
              <div class="upload-limit">Maximum size: 2MB</div>
            </div>

            <div class="photo-preview" id="photoPreview">
              <img id="previewImg" src="" alt="Preview"/>
              <p id="previewName"></p>
            </div>

            <div class="form-actions">
              <button type="submit" class="btn-save" id="savePhotoBtn" style="display:none">
                <span id="savePhotoText">Upload Photo →</span>
              </button>
            </div>
          </form>
        </div>
      </div>

    </div><!-- end content -->
    <?php include_once '../includes/footer.php'; ?>
  </div>
</div>

<script>
// Sidebar
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open');document.body.style.overflow=document.getElementById('sidebar').classList.contains('open')?'hidden':'';}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open');document.body.style.overflow='';}
window.addEventListener('resize',function(){if(window.innerWidth>768)closeSidebar();});

// Tabs
function switchTab(tab) {
  document.querySelectorAll('.tab').forEach(function(t){t.classList.remove('active');});
  document.querySelectorAll('.tab-panel').forEach(function(p){p.classList.remove('active');});
  document.getElementById('tab-'+tab).classList.add('active');
  document.getElementById('panel-'+tab).classList.add('active');
}

// Auto-switch tab based on which form was submitted
<?php if($success || $error): ?>
  <?php
  $action = $_POST['action'] ?? '';
  $tab = $action === 'change_password' ? 'password' : ($action === 'upload_photo' ? 'photo' : 'info');
  ?>
  switchTab('<?= $tab ?>');
<?php endif; ?>

// Password toggle
function togglePass(id,btn){var i=document.getElementById(id);i.type=i.type==='password'?'text':'password';btn.innerHTML=i.type==='password'?'👁':'🫣';}

// Password strength
function checkStrength(v){
  var segs=[1,2,3,4].map(function(n){return document.getElementById('s'+n)});
  var txt=document.getElementById('strengthText');
  segs.forEach(function(s){s.className='strength-seg'});
  if(!v){txt.textContent='';return;}
  var sc=0;
  if(v.length>=8)sc++;if(/[A-Z]/.test(v))sc++;if(/[0-9]/.test(v))sc++;if(/[^A-Za-z0-9]/.test(v))sc++;
  var cls=['','weak','weak','fair','good'];
  var lbl=['','Too short','Fair','Good','Strong'];
  for(var i=0;i<sc;i++)segs[i].classList.add(cls[sc]);
  txt.textContent=sc?'Strength: '+lbl[sc]:'';
  txt.style.color=sc<=1?'#ef4444':sc===2?'#f59e0b':'#10b981';
}

// Photo preview
function previewPhoto(input){
  if(!input.files||!input.files[0])return;
  var file=input.files[0];
  var reader=new FileReader();
  reader.onload=function(e){
    document.getElementById('previewImg').src=e.target.result;
    document.getElementById('previewName').textContent=file.name+' ('+Math.round(file.size/1024)+'KB)';
    document.getElementById('photoPreview').style.display='block';
    document.getElementById('savePhotoBtn').style.display='inline-flex';
  };
  reader.readAsDataURL(file);
}

// Drag & drop
var uploadArea=document.getElementById('uploadArea');
uploadArea.addEventListener('dragover',function(e){e.preventDefault();uploadArea.classList.add('dragover');});
uploadArea.addEventListener('dragleave',function(){uploadArea.classList.remove('dragover');});
uploadArea.addEventListener('drop',function(e){
  e.preventDefault();uploadArea.classList.remove('dragover');
  var files=e.dataTransfer.files;
  if(files.length){document.getElementById('photoInput').files=files;previewPhoto(document.getElementById('photoInput'));}
});

// Submit loading states
document.getElementById('profileForm').addEventListener('submit',function(){
  var btn=document.getElementById('saveProfileBtn');
  btn.classList.add('loading');
  document.getElementById('saveProfileText').textContent='Saving...';
});
document.getElementById('passwordForm').addEventListener('submit',function(){
  var btn=document.getElementById('savePassBtn');
  btn.classList.add('loading');
  document.getElementById('savePassText').textContent='Changing...';
});
document.getElementById('photoForm').addEventListener('submit',function(){
  var btn=document.getElementById('savePhotoBtn');
  btn.classList.add('loading');
  document.getElementById('savePhotoText').textContent='Uploading...';
});
</script>
</body>
</html>