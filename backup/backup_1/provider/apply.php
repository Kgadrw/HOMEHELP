<?php
require_once '../config/db.php';
require_once '../config/config.php';

requireRole('provider');

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Fetch current user details for pre-filling
$user_stmt = $conn->prepare("SELECT name, email, phone FROM users WHERE id = ? LIMIT 1");
$user_stmt->bind_param('i', $user_id);
$user_stmt->execute();
$current_user = $user_stmt->get_result()->fetch_assoc();

// Check if already applied
$existing = $conn->prepare("
    SELECT id, status FROM job_applications
    WHERE provider_id = ? AND status NOT IN ('rejected','cancelled')
    LIMIT 1
");
$existing->bind_param('i', $user_id);
$existing->execute();
$active_app = $existing->get_result()->fetch_assoc();

// Fetch services
$services = $conn->query("
    SELECT * FROM services WHERE status='active'
    ORDER BY sort_order ASC, id ASC
")->fetch_all(MYSQLI_ASSOC);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();

    $service_id     = (int)($_POST['service_id']      ?? 0);
    $full_name      = sanitize($_POST['full_name']     ?? '');
    $phone          = sanitize($_POST['phone']         ?? '');
    $email          = sanitize($_POST['email']         ?? '');
    $home_address   = sanitize($_POST['home_address']  ?? '');
    $district       = sanitize($_POST['district']      ?? '');
    $dob            = sanitize($_POST['dob']           ?? '');
    $education      = sanitize($_POST['education']     ?? '');
    $experience     = sanitize($_POST['experience']    ?? '');
    $bio            = sanitize($_POST['bio']           ?? '');
    $availability   = sanitize($_POST['availability']  ?? '');
    $expected_pay   = sanitize($_POST['expected_pay']  ?? '');
    $languages      = sanitize($_POST['languages']     ?? '');
    $eligibility    = isset($_POST['eligibility_confirm']) ? 1 : 0;

    if (!$service_id || !$full_name || !$phone || !$home_address || !$district || !$dob || !$education || !$experience || !$availability) {
        $error = 'Please fill in all required fields.';
    } elseif (!$eligibility) {
        $error = 'You must confirm the eligibility requirements to submit your application.';
    } elseif ($active_app) {
        $error = 'You already have an active application. Please wait for it to be reviewed.';
    } else {
        // Get service name & icon
        $svc_stmt = $conn->prepare("SELECT name, icon FROM services WHERE id = ? LIMIT 1");
        $svc_stmt->bind_param('i', $service_id);
        $svc_stmt->execute();
        $svc = $svc_stmt->get_result()->fetch_assoc();
        $service_name = $svc ? $svc['name'] : 'Service';

        // Handle file uploads (store path in bio/notes for now)
        $upload_dir = '../uploads/applications/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $uploaded_files = [];
        $file_fields = ['police_clearance','application_letter','education_cert','reference_letter','national_id'];
        foreach ($file_fields as $field) {
            if (!empty($_FILES[$field]['name'])) {
                $ext      = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
                $allowed  = ['pdf','jpg','jpeg','png'];
                if (in_array($ext, $allowed)) {
                    $filename = $field . '_' . $user_id . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES[$field]['tmp_name'], $upload_dir . $filename)) {
                        $uploaded_files[$field] = $filename;
                    }
                }
            }
        }

        // Build location string
        $location = $home_address;

        $stmt = $conn->prepare("
            INSERT INTO job_applications
                (provider_id, service_id, location, district, experience,
                 bio, availability, expected_pay, languages, status)
            VALUES (?,?,?,?,?,?,?,?,?,'pending')
        ");

        // Append extra info to bio field until schema is extended
        $bio_full  = "FULL NAME: {$full_name}\n";
        $bio_full .= "PHONE: {$phone}\n";
        $bio_full .= "EMAIL: {$email}\n";
        $bio_full .= "DOB: {$dob}\n";
        $bio_full .= "EDUCATION: {$education}\n";
        if (!empty($uploaded_files)) {
            $bio_full .= "DOCS: " . implode(', ', array_keys($uploaded_files)) . "\n";
        }
        $bio_full .= "\nABOUT: " . $bio;

        $stmt->bind_param('iisssssss',
            $user_id, $service_id, $location, $district,
            $experience, $bio_full, $availability, $expected_pay, $languages
        );

        if ($stmt->execute()) {
            $app_id = $conn->insert_id;

            // Notify admin
            $admin = $conn->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetch_assoc();
            if ($admin) {
                logNotification($admin['id'], 'new_application',
                    "New job application from {$full_name} for {$service_name}.");
            }
            logNotification($user_id, 'application_submitted',
                'Your application has been submitted! Our team will review it and notify you by email.');

            flashSet('success', 'Your application has been submitted successfully! You will be notified after it is reviewed.');
            redirect(BASE_URL . '/my-applications');
        } else {
            $error = 'Something went wrong. Please try again.';
        }
    }
}

$notif_count = unreadNotifCount();

$job_options = ['Cleaning','Housekeeping','Chef','Cook'];
$education_options = ['Primary','Secondary','Diploma',"Bachelor's",'Other'];
$experience_options = ['Less than 1 year','1-2 years','3-5 years','5+ years'];
$districts = ['Gasabo','Kicukiro','Nyarugenge','Bugesera','Gatsibo','Kayonza',
    'Kirehe','Ngoma','Nyagatare','Rwamagana','Burera','Gakenke','Gicumbi',
    'Musanze','Rulindo','Gisagara','Huye','Kamonyi','Muhanga','Nyamagabe',
    'Nyanza','Nyaruguru','Ruhango','Karongi','Ngororero','Nyabihu','Nyamasheke',
    'Rubavu','Rutsiro','Rusizi'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Apply for a Job | Home Help</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<style>
:root{
  --navy-deep:#0D1B4B;--navy-mid:#152268;--navy-light:#2E5FC2;--navy-soft:#D4DCF5;
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
.sidebar{width:var(--sidebar);flex-shrink:0;background:var(--navy-deep);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:200;transition:transform 0.3s ease;overflow-y:auto}
.sidebar-logo{display:flex;align-items:center;gap:10px;padding:24px 20px 20px;border-bottom:1px solid rgba(255,255,255,0.08)}
.sidebar-logo img{width:36px;height:36px;object-fit:contain}
.sidebar-logo-text{font-family:var(--font-display);font-size:1.3rem;font-weight:700;color:var(--white)}
.sidebar-user{padding:20px;border-bottom:1px solid rgba(255,255,255,0.08);display:flex;align-items:center;gap:12px}
.user-avatar{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,var(--navy-light),var(--navy-mid));display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:1.1rem;font-weight:700;color:var(--white);flex-shrink:0}
.user-info strong{display:block;font-size:0.88rem;font-weight:700;color:var(--white)}
.user-info span{font-size:0.73rem;color:rgba(255,255,255,0.5)}
.sidebar-nav{padding:16px 0;flex:1}
.nav-section-label{font-size:0.66rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:rgba(255,255,255,0.3);padding:12px 20px 6px}
.nav-item{display:flex;align-items:center;gap:12px;padding:11px 20px;font-size:0.87rem;font-weight:500;color:rgba(255,255,255,0.65);cursor:pointer;transition:all 0.2s;position:relative;text-decoration:none}
.nav-item:hover{color:var(--white);background:rgba(255,255,255,0.06)}
.nav-item.active{color:var(--white);background:rgba(255,255,255,0.1);font-weight:600}
.nav-item.active::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--navy-light);border-radius:0 3px 3px 0}
.nav-icon{font-size:1.05rem;width:20px;text-align:center;flex-shrink:0}
.sidebar-footer{padding:16px 20px;border-top:1px solid rgba(255,255,255,0.08)}
.btn-logout{display:flex;align-items:center;gap:8px;width:100%;padding:10px 14px;border-radius:var(--radius-sm);background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.2);color:rgba(255,100,100,0.9);font-size:0.84rem;font-weight:600;cursor:pointer;transition:all 0.2s;text-decoration:none;font-family:var(--font-body)}
.btn-logout:hover{background:rgba(239,68,68,0.2)}
.main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{background:var(--white);border-bottom:1px solid var(--cream-dark);padding:0 28px;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.topbar-left{display:flex;align-items:center;gap:16px}
.burger{display:none;background:none;border:none;cursor:pointer;padding:6px;flex-direction:column;gap:4px}
.burger span{display:block;width:20px;height:2px;background:var(--text-dark);border-radius:2px}
.topbar-title{font-family:var(--font-display);font-size:1.4rem;font-weight:700;color:var(--navy-deep)}
.topbar-right{display:flex;align-items:center;gap:12px}
.topbar-notif{position:relative;width:38px;height:38px;border-radius:var(--radius-sm);background:var(--cream);border:1.5px solid var(--cream-dark);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:1.1rem;transition:all 0.2s;text-decoration:none}
.topbar-notif:hover{border-color:var(--navy-light);background:var(--navy-soft)}
.content{padding:28px;flex:1;max-width:860px}
.page-header{margin-bottom:28px}
.page-header h1{font-family:var(--font-display);font-size:2rem;font-weight:700;color:var(--navy-deep);margin-bottom:6px}
.page-header h1 em{font-style:italic;color:var(--navy-light)}
.page-header p{font-size:0.9rem;color:var(--text-soft)}
.alert{padding:13px 16px;border-radius:var(--radius-sm);font-size:0.87rem;font-weight:500;margin-bottom:22px;display:flex;align-items:flex-start;gap:10px}
.alert-error{background:var(--danger-bg);border:1.5px solid #FECACA;color:#B91C1C}
.alert-success{background:var(--success-bg);border:1.5px solid #BBF7D0;color:#065F46}
/* Active app banner */
.active-banner{background:linear-gradient(135deg,var(--navy-deep),var(--navy-mid));border-radius:var(--radius);padding:24px 28px;margin-bottom:28px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px}
.active-banner-text h3{font-family:var(--font-display);font-size:1.3rem;font-weight:700;color:var(--white);margin-bottom:4px}
.active-banner-text p{font-size:0.85rem;color:rgba(255,255,255,0.65)}
.btn-view-app{display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border-radius:99px;background:var(--navy-light);color:var(--white);font-size:0.84rem;font-weight:700;text-decoration:none;transition:all 0.25s}
.btn-view-app:hover{background:var(--navy-mid);transform:translateY(-1px)}
/* How it works — single step */
.how-it-works-single{display:flex;align-items:center;gap:14px;background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);padding:16px 20px;margin-bottom:24px}
.hiw-num{width:36px;height:36px;border-radius:50%;background:var(--navy-deep);color:var(--white);font-size:0.9rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.hiw-icon{font-size:1.5rem;flex-shrink:0}
.hiw-text strong{display:block;font-size:0.9rem;font-weight:700;color:var(--navy-deep)}
.hiw-text span{font-size:0.8rem;color:var(--text-soft)}
/* Email note */
.email-note{display:flex;align-items:center;gap:10px;background:#EFF6FF;border:1.5px solid #BFDBFE;border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:24px;font-size:0.84rem;color:#1E40AF}
.email-note strong{font-weight:700}
/* Service selector */
.services-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:4px}
.service-option{position:relative;cursor:pointer}
.service-option input[type="radio"]{position:absolute;opacity:0;width:0;height:0}
.service-card{border:2px solid var(--cream-dark);border-radius:var(--radius);padding:14px 10px;text-align:center;background:var(--white);transition:all 0.25s}
.service-option input:checked + .service-card{border-color:var(--navy-deep);background:var(--navy-soft);box-shadow:0 0 0 3px rgba(13,27,75,0.08)}
.service-card:hover{border-color:var(--navy-light);background:var(--navy-soft)}
.service-emoji{font-size:1.6rem;display:block;margin-bottom:6px}
.service-label{font-size:0.78rem;font-weight:700;color:var(--navy-deep)}
.service-check{position:absolute;top:6px;right:6px;width:16px;height:16px;border-radius:50%;background:var(--navy-deep);color:var(--white);font-size:0.6rem;display:none;align-items:center;justify-content:center}
.service-option input:checked + .service-card .service-check{display:flex}
/* Form */
.form-card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);padding:24px;margin-bottom:18px}
.form-card-title{font-family:var(--font-display);font-size:1.1rem;font-weight:700;color:var(--navy-deep);margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid var(--cream-dark)}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px}
.form-group{display:flex;flex-direction:column;gap:6px;margin-bottom:16px}
.form-group:last-child{margin-bottom:0}
.form-group label{font-size:0.81rem;font-weight:600;color:var(--text-dark)}
.form-group label .req{color:var(--danger);margin-left:2px}
.form-group label .opt{color:var(--text-soft);font-weight:400;font-size:0.73rem;margin-left:4px}
.input-wrap{position:relative}
.input-icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:0.9rem;pointer-events:none}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:10px 14px 10px 38px;border:1.5px solid var(--cream-dark);border-radius:var(--radius-sm);font-family:var(--font-body);font-size:0.87rem;color:var(--text-dark);background:var(--cream);transition:all 0.2s;outline:none}
.form-group textarea{padding:10px 14px;min-height:90px;resize:vertical}
.form-group input[type="file"]{padding:8px 14px;background:var(--white);cursor:pointer}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:var(--navy-light);background:var(--white);box-shadow:0 0 0 3px rgba(46,95,194,0.1)}
.no-icon{padding-left:14px !important}
.char-counter{font-size:0.71rem;color:var(--text-soft);text-align:right;margin-top:2px}
.upload-hint{font-size:0.73rem;color:var(--text-soft);margin-top:3px}
/* Eligibility section */
.eligibility-card{background:linear-gradient(135deg,rgba(13,27,75,0.04),rgba(46,95,194,0.06));border:1.5px solid var(--navy-soft);border-radius:var(--radius);padding:24px;margin-bottom:18px}
.eligibility-title{font-family:var(--font-display);font-size:1.15rem;font-weight:700;color:var(--navy-deep);margin-bottom:6px}
.eligibility-sub{font-size:0.82rem;color:var(--text-soft);margin-bottom:18px}
.eligibility-list{display:flex;flex-direction:column;gap:8px;margin-bottom:20px}
.eligibility-item{display:flex;align-items:flex-start;gap:10px;font-size:0.84rem;color:var(--text-mid)}
.elig-check{width:20px;height:20px;border-radius:50%;background:var(--navy-deep);color:var(--white);font-size:0.6rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px}
.eligibility-confirm{display:flex;align-items:flex-start;gap:12px;background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius-sm);padding:14px 16px;cursor:pointer}
.eligibility-confirm input[type="checkbox"]{width:18px;height:18px;accent-color:var(--navy-deep);cursor:pointer;flex-shrink:0;margin-top:1px}
.eligibility-confirm-text{font-size:0.84rem;color:var(--text-dark);line-height:1.6}
.eligibility-confirm-text strong{color:var(--navy-deep)}
/* Form actions */
.form-actions{display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-top:6px}
.btn-submit{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:13px 32px;border-radius:99px;background:var(--navy-deep);color:var(--white);font-family:var(--font-body);font-size:0.94rem;font-weight:700;border:none;cursor:pointer;transition:all 0.3s}
.btn-submit:hover{background:var(--navy-mid);transform:translateY(-2px);box-shadow:var(--shadow-md)}
.btn-submit.loading{opacity:0.7;cursor:not-allowed;pointer-events:none}
.btn-cancel{display:inline-flex;align-items:center;gap:6px;padding:13px 22px;border-radius:99px;border:1.5px solid var(--cream-dark);background:transparent;color:var(--text-mid);font-family:var(--font-body);font-size:0.9rem;font-weight:600;cursor:pointer;transition:all 0.25s;text-decoration:none}
.btn-cancel:hover{border-color:var(--navy-light);color:var(--navy-deep)}
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:199}
@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}
  .sidebar-overlay.open{display:block}.main{margin-left:0}.burger{display:flex}
  .content{padding:18px}.topbar{padding:0 16px}
  .services-grid{grid-template-columns:repeat(3,1fr)}
  .form-row,.form-row-3{grid-template-columns:1fr}
}
@media(max-width:480px){.services-grid{grid-template-columns:repeat(2,1fr)}}
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
      <div class="user-avatar"><?= strtoupper(substr($user_name,0,1)) ?></div>
      <div class="user-info">
        <strong><?= e($user_name) ?></strong>
        <span>Job Seeker</span>
      </div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">Main</div>
      <a href="<?= BASE_URL ?>/provider-dashboard" class="nav-item"><span class="nav-icon">🏠</span> Dashboard</a>
      <a href="<?= BASE_URL ?>/apply" class="nav-item active"><span class="nav-icon">📝</span> Apply for a Job</a>
      <a href="<?= BASE_URL ?>/my-applications" class="nav-item"><span class="nav-icon">📂</span> My Applications</a>
      <div class="nav-section-label">Account</div>
      <a href="<?= BASE_URL ?>/profile" class="nav-item"><span class="nav-icon">👤</span> My Profile</a>
      <div class="nav-section-label">Help</div>
      <a href="<?= BASE_URL ?>/#faq" class="nav-item"><span class="nav-icon">❓</span> FAQ</a>
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
        <span class="topbar-title">Apply for a Job</span>
      </div>
      <div class="topbar-right">
        <a href="<?= BASE_URL ?>/my-applications" class="topbar-notif" title="My Applications">📂</a>
      </div>
    </header>

    <div class="content">
      <div class="page-header">
        <h1>Apply for a <em>Job</em></h1>
        <p>Fill in the form below and submit your application. Our team will review it and get back to you.</p>
      </div>

      <?php if($error): ?>
        <div class="alert alert-error">⚠️ <?= e($error) ?></div>
      <?php endif; ?>

      <?php $flash = flashGet('success'); if($flash): ?>
        <div class="alert alert-success">✅ <?= e($flash) ?></div>
      <?php endif; ?>

      <!-- Active app banner -->
      <?php if($active_app): ?>
        <div class="active-banner">
          <div class="active-banner-text">
            <h3>You have an active application</h3>
            <p>Status: <strong><?= ucfirst(str_replace('_',' ',$active_app['status'])) ?></strong> — Please wait for it to be reviewed before applying again.</p>
          </div>
          <a href="<?= BASE_URL ?>/my-applications" class="btn-view-app">📂 View Application →</a>
        </div>
      <?php endif; ?>

      <!-- H.I: How it works — Fill Form only -->
      <div class="how-it-works-single">
        <div class="hiw-num">1</div>
        <div class="hiw-icon">📝</div>
        <div class="hiw-text">
          <strong>Fill Form</strong>
          <span>Complete all sections below and submit your application</span>
        </div>
      </div>

      <!-- H.II: Email notification note (replaces WhatsApp note) -->
      <div class="email-note">
        📧 <span>You will be notified after submitting your application.</span>
      </div>

      <?php if(!$active_app): ?>
      <form method="POST" id="applyForm" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"/>
        <input type="hidden" name="service_id" id="serviceIdInput" value=""/>

        <!-- SERVICE SELECTION -->
        <div class="form-card">
          <div class="form-card-title">Step 1 — Job Applied For <span style="color:var(--danger)">*</span></div>
          <div class="services-grid">
            <?php foreach($services as $svc): ?>
              <label class="service-option">
                <input type="radio" name="service_display" value="<?= $svc['id'] ?>"
                  onchange="document.getElementById('serviceIdInput').value=this.value"/>
                <div class="service-card">
                  <span class="service-emoji"><?= $svc['icon']?:'🏠' ?></span>
                  <span class="service-label"><?= e($svc['name']) ?></span>
                  <div class="service-check">✓</div>
                </div>
              </label>
            <?php endforeach; ?>
          </div>
          <?php if(empty($services)): ?>
            <!-- Fallback if no services in DB -->
            <div class="form-group" style="margin-top:14px">
              <label>Job Applied For <span class="req">*</span></label>
              <div class="input-wrap">
                <span class="input-icon">💼</span>
                <select name="job_type" required>
                  <option value="">Select...</option>
                  <?php foreach($job_options as $jo): ?>
                    <option value="<?= $jo ?>"><?= $jo ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <!-- H.III: PERSONAL INFORMATION -->
        <div class="form-card">
          <div class="form-card-title">Step 2 — Personal Information</div>
          <div class="form-row">
            <div class="form-group">
              <label>Full Name (as on ID) <span class="req">*</span></label>
              <div class="input-wrap">
                <span class="input-icon">👤</span>
                <input type="text" name="full_name"
                  value="<?= e($current_user['name'] ?? '') ?>"
                  placeholder="Enter your full legal name" required/>
              </div>
            </div>
            <div class="form-group">
              <label>Phone Number <span class="req">*</span></label>
              <div class="input-wrap">
                <span class="input-icon">📱</span>
                <input type="tel" name="phone"
                  value="<?= e($current_user['phone'] ?? '') ?>"
                  placeholder="e.g. 0781234567" required/>
              </div>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Email Address <span class="opt">(if available)</span></label>
              <div class="input-wrap">
                <span class="input-icon">✉️</span>
                <input type="email" name="email"
                  value="<?= e($current_user['email'] ?? '') ?>"
                  placeholder="you@example.com"/>
              </div>
            </div>
            <div class="form-group">
              <label>Date of Birth <span class="req">*</span></label>
              <div class="input-wrap">
                <span class="input-icon">🗓️</span>
                <input type="date" name="dob" required/>
              </div>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Home Address (Sector) <span class="req">*</span></label>
              <div class="input-wrap">
                <span class="input-icon">📍</span>
                <input type="text" name="home_address" placeholder="e.g. Kimironko Sector" required/>
              </div>
            </div>
            <div class="form-group">
              <label>District <span class="req">*</span></label>
              <div class="input-wrap">
                <span class="input-icon">🗺️</span>
                <select name="district" required>
                  <option value="">Select district...</option>
                  <?php foreach($districts as $d): ?>
                    <option value="<?= $d ?>"><?= $d ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Level of Education <span class="req">*</span></label>
              <div class="input-wrap">
                <span class="input-icon">🎓</span>
                <select name="education" required>
                  <option value="">Select...</option>
                  <?php foreach($education_options as $ed): ?>
                    <option value="<?= $ed ?>"><?= $ed ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label>Years of Experience <span class="req">*</span></label>
              <div class="input-wrap">
                <span class="input-icon">⏳</span>
                <select name="experience" required>
                  <option value="">Select...</option>
                  <?php foreach($experience_options as $ex): ?>
                    <option value="<?= $ex ?>"><?= $ex ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- EXPERIENCE & PROFILE -->
        <div class="form-card">
          <div class="form-card-title">Step 3 — Your Experience &amp; Profile</div>
          <div class="form-group">
            <label>Describe Your Work Experience <span class="req">*</span></label>
            <textarea name="bio" class="no-icon"
              placeholder="Describe your background e.g. I have worked as a nanny for 2 families in Kigali for 3 years..."
              maxlength="600" oninput="updateCount(this,'expCount')" required></textarea>
            <div class="char-counter"><span id="expCount">0</span>/600</div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Availability <span class="req">*</span></label>
              <div class="input-wrap">
                <span class="input-icon">⏰</span>
                <select name="availability" required>
                  <option value="">Select...</option>
                  <option value="Full-time">Full-time (Mon–Fri)</option>
                  <option value="Part-time mornings">Part-time — Mornings</option>
                  <option value="Part-time evenings">Part-time — Evenings</option>
                  <option value="Weekends only">Weekends only</option>
                  <option value="Live-in">Live-in (permanent)</option>
                  <option value="Flexible">Flexible / Any time</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label>Expected Monthly Pay (RWF) <span class="opt">(optional)</span></label>
              <div class="input-wrap">
                <span class="input-icon">💰</span>
                <input type="text" name="expected_pay" placeholder="e.g. 80,000 RWF"/>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label>Languages You Speak <span class="opt">(optional)</span></label>
            <div class="input-wrap">
              <span class="input-icon">🗣️</span>
              <input type="text" name="languages" placeholder="e.g. Kinyarwanda, French, English"/>
            </div>
          </div>
        </div>

        <!-- H.IV: DOCUMENT UPLOADS -->
        <div class="form-card">
          <div class="form-card-title">Step 4 — Required Documents</div>
          <div class="form-row">
            <div class="form-group">
              <label>Police Clearance Certificate <span class="req">*</span></label>
              <input type="file" name="police_clearance" accept=".pdf,.jpg,.jpeg,.png" required/>
              <div class="upload-hint">PDF, JPG or PNG · Max 5MB</div>
            </div>
            <div class="form-group">
              <label>Job Application Letter <span class="req">*</span></label>
              <input type="file" name="application_letter" accept=".pdf,.jpg,.jpeg,.png" required/>
              <div class="upload-hint">PDF, JPG or PNG · Max 5MB</div>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Education Certificate <span class="req">*</span></label>
              <input type="file" name="education_cert" accept=".pdf,.jpg,.jpeg,.png" required/>
              <div class="upload-hint">PDF, JPG or PNG · Max 5MB</div>
            </div>
            <div class="form-group">
              <label>Reference Letter <span class="req">*</span></label>
              <input type="file" name="reference_letter" accept=".pdf,.jpg,.jpeg,.png" required/>
              <div class="upload-hint">PDF, JPG or PNG · Max 5MB</div>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>National ID (Indangamuntu) <span class="req">*</span></label>
              <input type="file" name="national_id" accept=".pdf,.jpg,.jpeg,.png" required/>
              <div class="upload-hint">PDF, JPG or PNG · Max 5MB</div>
            </div>
          </div>
        </div>

        <!-- H.V: ELIGIBILITY -->
        <div class="eligibility-card">
          <div class="eligibility-title">WE ARE LOOKING FOR INDIVIDUALS WHO:</div>
          <div class="eligibility-sub">ARE SKILLED INDIVIDUALS WHO CAN PROVIDE HIGH-QUALITY HOME SERVICES AND TAKE PRIDE IN THEIR WORK.</div>
          <div class="eligibility-list">
            <div class="eligibility-item"><div class="elig-check">✓</div><span>Are committed to delivering quality service</span></div>
            <div class="eligibility-item"><div class="elig-check">✓</div><span>Have proven experience in domestic work</span></div>
            <div class="eligibility-item"><div class="elig-check">✓</div><span>Are reliable, honest, and professional</span></div>
            <div class="eligibility-item"><div class="elig-check">✓</div><span>Have good communication skills</span></div>
            <div class="eligibility-item"><div class="elig-check">✓</div><span>Are physically fit and able to perform household tasks</span></div>
            <div class="eligibility-item"><div class="elig-check">✓</div><span>Can provide valid identification</span></div>
            <div class="eligibility-item"><div class="elig-check">✓</div><span>Are willing to undergo a background check and allow HomeHelp to verify their information</span></div>
            <div class="eligibility-item"><div class="elig-check">✓</div><span>Accept HomeHelp's Terms and Code of Conduct</span></div>
          </div>
          <label class="eligibility-confirm">
            <input type="checkbox" name="eligibility_confirm" id="eligibilityCheck" required/>
            <span class="eligibility-confirm-text">
              <strong>I confirm</strong> that I meet the above requirements. I am committed to delivering quality service, have proven domestic work experience, am reliable and professional, can provide valid identification, and agree to HomeHelp's Terms and Code of Conduct.
            </span>
          </label>
        </div>

        <!-- ACTIONS -->
        <div class="form-actions">
          <button type="submit" class="btn-submit" id="submitBtn">
            <span id="btnText">Submit Application →</span>
          </button>
          <a href="<?= BASE_URL ?>/provider-dashboard" class="btn-cancel">Cancel</a>
        </div>
      </form>
      <?php endif; ?>

    </div>
    <?php include_once '../includes/footer.php'; ?>
  </div>
</div>

<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open');document.body.style.overflow=document.getElementById('sidebar').classList.contains('open')?'hidden':'';}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open');document.body.style.overflow='';}
window.addEventListener('resize',function(){if(window.innerWidth>768)closeSidebar();});
function updateCount(el,spanId){document.getElementById(spanId).textContent=el.value.length;}
var applyForm = document.getElementById('applyForm');
if(applyForm){
  applyForm.addEventListener('submit',function(e){
    var sid = document.getElementById('serviceIdInput').value;
    if(!sid){e.preventDefault();alert('Please select the job you are applying for.');return;}
    var chk = document.getElementById('eligibilityCheck');
    if(!chk.checked){e.preventDefault();alert('Please confirm the eligibility requirements to proceed.');return;}
    var btn = document.getElementById('submitBtn');
    btn.classList.add('loading');
    document.getElementById('btnText').textContent='Submitting...';
  });
}
</script>
</body>
</html>