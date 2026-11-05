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

$flash = flashGet();

// Map a service name to a Lucide icon
if (!function_exists('svc_icon')) {
    function svc_icon($name) {
        $n = strtolower((string)$name);
        if (strpos($n,'chef') !== false || strpos($n,'cook') !== false) return 'chef-hat';
        if (strpos($n,'clean') !== false)                               return 'sparkles';
        if (strpos($n,'house') !== false)                               return 'house';
        if (strpos($n,'laundry') !== false)                             return 'shirt';
        if (strpos($n,'garden') !== false)                              return 'flower-2';
        if (strpos($n,'nanny') !== false || strpos($n,'child') !== false) return 'baby';
        return 'briefcase';
    }
}

// ── SHELL VARS ────────────────────────────────────────────────
$page_title          = 'Apply for a Job';
$dash_role           = 'provider';
$dash_page           = 'apply';
$page_heading        = 'Apply for a Job';
$topbar_action_href  = BASE_URL . '/my-applications';
$topbar_action_label = 'My Applications';
$topbar_action_icon  = 'folder';
?>
<?php include '../includes/dash-head.php'; ?>
<style>
.apply-wrap{width:100%}
.apply-layout{display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:start}
.apply-main{min-width:0}
.apply-side{position:sticky;top:88px}

.steps-rail{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap}
.step-chip{
  display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:99px;
  background:var(--white);border:1px solid var(--line);font-size:.78rem;font-weight:600;color:var(--muted);
  transition:background .15s,border-color .15s,color .15s;
}
.step-chip i{width:14px;height:14px}
.step-chip .n{
  width:20px;height:20px;border-radius:50%;background:var(--bg);color:var(--navy);
  display:inline-flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:800;
}
.step-chip.is-active{border-color:var(--navy);color:var(--navy);background:#F5F7FD}
.step-chip.is-active .n{background:var(--navy);color:#fff}
.step-chip.is-done{border-color:#A7F3D0;color:#047857;background:var(--success-bg)}
.step-chip.is-done .n{background:#10B981;color:#fff}
.wizard-step{display:none}
.wizard-step.is-active{display:block}
.step-error{
  display:none;align-items:flex-start;gap:8px;margin-bottom:14px;padding:11px 12px;
  border-radius:10px;background:var(--danger-bg);border:1px solid #FECACA;color:#B91C1C;font-size:.84rem;
}
.step-error.is-visible{display:flex}
.step-error i{width:16px;height:16px;flex-shrink:0;margin-top:1px}

.active-banner{
  background:linear-gradient(135deg,var(--navy),var(--navy-mid));border-radius:16px;
  padding:20px 22px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;
  flex-wrap:wrap;gap:14px;color:#fff;
}
.active-banner-ico{
  width:42px;height:42px;border-radius:12px;background:rgba(255,255,255,.12);
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
.active-banner-ico i{width:20px;height:20px}
.active-banner h3{font-size:1rem;font-weight:800;margin-bottom:4px}
.active-banner p{font-size:.84rem;color:rgba(255,255,255,.72)}
.btn-view-app{
  display:inline-flex;align-items:center;gap:7px;padding:10px 16px;border-radius:10px;
  background:#fff;color:var(--navy);font-size:.84rem;font-weight:700;
}
.btn-view-app i{width:16px;height:16px}

.form-card{margin-bottom:16px;border:1px solid var(--line);box-shadow:none}
.form-card .card-header{
  display:flex;align-items:center;gap:12px;padding:16px 20px;background:#F8F9FC;
}
.section-ico{
  width:36px;height:36px;border-radius:10px;background:var(--navy);color:#fff;
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
.section-ico i{width:17px;height:17px}
.section-meta{flex:1;min-width:0}
.section-meta .card-title{display:block;font-size:.95rem;font-weight:750;color:var(--navy)}
.section-meta .card-sub{display:block;font-size:.78rem;color:var(--muted);margin-top:2px}
.form-card .card-body{padding:20px}

.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px 18px}
.form-group{margin-bottom:0}
.form-group + .form-group,.form-row + .form-group,.form-group + .form-row{margin-top:16px}
.form-group label{display:flex;align-items:center;gap:6px;font-size:.8rem;font-weight:600;margin-bottom:7px;color:var(--text)}
.form-group label .req{color:var(--danger)}
.form-group label .opt{color:var(--soft);font-weight:500;font-size:.72rem}
.field{
  position:relative;display:flex;align-items:center;
}
.field > i{
  position:absolute;left:13px;width:16px;height:16px;color:var(--soft);pointer-events:none;z-index:1;
}
.field input,.field select{
  width:100%;padding:12px 14px 12px 40px;border:1px solid var(--line);border-radius:10px;
  font-family:var(--font);font-size:.9rem;background:var(--white);outline:none;color:var(--text);
  transition:border-color .15s,box-shadow .15s;
}
.field select{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%238B93A7' d='M1 1l5 5 5-5'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;padding-right:36px}
.field input:focus,.field select:focus,.form-group textarea:focus{
  border-color:var(--navy-light);box-shadow:0 0 0 3px rgba(46,95,194,.12);
}
.form-group textarea{
  width:100%;min-height:120px;resize:vertical;padding:12px 14px;border:1px solid var(--line);
  border-radius:10px;font-family:var(--font);font-size:.9rem;outline:none;
}
.char-counter{font-size:.72rem;color:var(--soft);text-align:right;margin-top:6px}

/* Personal info step */
.pi-body{padding:0 !important}
.pi-block{padding:22px 22px 20px;border-bottom:1px solid var(--line)}
.pi-block:last-child{border-bottom:0}
.pi-block-head{display:flex;align-items:center;gap:10px;margin-bottom:16px}
.pi-block-ico{
  width:32px;height:32px;border-radius:9px;background:var(--navy-soft);color:var(--navy);
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
.pi-block-ico i{width:15px;height:15px}
.pi-block-head strong{display:block;font-size:.88rem;font-weight:750;color:var(--navy)}
.pi-block-head span{display:block;font-size:.75rem;color:var(--muted);margin-top:1px}
.pi-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:16px 18px}
.pi-grid-3{display:grid;grid-template-columns:1.2fr 1fr 1fr;gap:16px 18px}
.pi-field{display:flex;flex-direction:column;gap:7px;min-width:0}
.pi-field.span-2{grid-column:1 / -1}
.pi-field label{
  display:flex;align-items:center;justify-content:space-between;gap:8px;
  font-size:.78rem;font-weight:650;color:#374151;margin:0;
}
.pi-field label .req{
  width:6px;height:6px;border-radius:50%;background:var(--danger);display:inline-block;flex-shrink:0;
}
.pi-field label .opt-pill{
  font-size:.68rem;font-weight:650;color:var(--muted);background:var(--bg);
  border:1px solid var(--line);padding:2px 7px;border-radius:99px;
}
.pi-control{
  display:flex;align-items:center;gap:0;background:var(--bg);border:1px solid var(--line);
  border-radius:12px;overflow:hidden;transition:border-color .15s,box-shadow .15s,background .15s;
}
.pi-control:focus-within{background:#fff;border-color:var(--navy-light);box-shadow:0 0 0 3px rgba(46,95,194,.12)}
.pi-control .ico{
  width:44px;height:46px;display:flex;align-items:center;justify-content:center;
  color:var(--soft);flex-shrink:0;border-right:1px solid var(--line);background:rgba(255,255,255,.45);
}
.pi-control .ico i{width:16px;height:16px}
.pi-control input,.pi-control select{
  flex:1;min-width:0;border:0;background:transparent;outline:none;
  font-family:var(--font);font-size:.9rem;color:var(--text);padding:12px 14px;height:46px;
}
.pi-control select{
  appearance:none;cursor:pointer;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%238B93A7' d='M1 1l5 5 5-5'/%3E%3C/svg%3E");
  background-repeat:no-repeat;background-position:right 14px center;padding-right:36px;
}
.pi-note{
  display:flex;align-items:flex-start;gap:8px;margin-top:14px;padding:11px 12px;
  border-radius:10px;background:#F5F7FD;border:1px solid var(--navy-soft);
  font-size:.78rem;color:var(--navy);line-height:1.45;
}
.pi-note i{width:14px;height:14px;flex-shrink:0;margin-top:1px}

.services-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px}
.service-option{position:relative;cursor:pointer}
.service-option input{position:absolute;opacity:0;width:0;height:0}
.service-card{
  height:100%;border:1.5px solid var(--line);border-radius:14px;padding:18px 14px;
  background:var(--white);transition:border-color .15s,background .15s,box-shadow .15s,transform .15s;
  display:flex;flex-direction:column;align-items:center;gap:10px;text-align:center;
}
.service-card .svc-ico{
  width:44px;height:44px;border-radius:12px;background:var(--navy-soft);color:var(--navy);
  display:flex;align-items:center;justify-content:center;
}
.service-card .svc-ico i{width:20px;height:20px}
.service-label{font-size:.84rem;font-weight:700;color:var(--navy);line-height:1.3}
.service-card:hover{border-color:var(--navy-light);transform:translateY(-1px)}
.service-option input:checked + .service-card{
  border-color:var(--navy);background:#F5F7FD;box-shadow:0 0 0 3px rgba(10,31,107,.1);
}
.service-option input:checked + .service-card .svc-ico{background:var(--navy);color:#fff}
.service-check{
  position:absolute;top:10px;right:10px;width:22px;height:22px;border-radius:50%;
  background:var(--navy);color:#fff;display:none;align-items:center;justify-content:center;
}
.service-check i{width:12px;height:12px;stroke-width:3}
.service-option input:checked + .service-card .service-check{display:flex}

.upload-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px}
.upload-box{
  position:relative;border:1.5px dashed var(--line);border-radius:14px;padding:18px 14px;
  background:#FBFBFD;transition:border-color .15s,background .15s;cursor:pointer;min-height:128px;
  display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:8px;
}
.upload-box:hover,.upload-box.has-file{border-color:var(--navy-light);background:#F5F7FD}
.upload-box input[type="file"]{position:absolute;inset:0;opacity:0;cursor:pointer}
.upload-box .up-ico{
  width:40px;height:40px;border-radius:12px;background:var(--navy-soft);color:var(--navy);
  display:flex;align-items:center;justify-content:center;
}
.upload-box .up-ico i{width:18px;height:18px}
.upload-box strong{font-size:.84rem;font-weight:700;color:var(--navy)}
.upload-box .upload-hint{font-size:.72rem;color:var(--soft)}
.upload-box .file-name{font-size:.72rem;color:var(--navy-light);font-weight:600;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.upload-box .req-tag{font-size:.68rem;font-weight:700;color:var(--danger)}

.elig-card{
  background:var(--white);border:1px solid var(--line);border-radius:16px;padding:22px;margin-bottom:16px;
}
.elig-head{display:flex;align-items:flex-start;gap:12px;margin-bottom:16px}
.elig-head .section-ico{background:var(--navy)}
.elig-title{font-size:.95rem;font-weight:800;color:var(--navy);margin-bottom:4px}
.elig-sub{font-size:.82rem;color:var(--muted);line-height:1.55}
.elig-list{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:18px}
.elig-item{
  display:flex;align-items:flex-start;gap:10px;font-size:.84rem;color:#374151;
  background:var(--bg);border-radius:10px;padding:12px;
}
.elig-item i{width:16px;height:16px;color:var(--navy);flex-shrink:0;margin-top:1px}
.elig-confirm{
  display:flex;align-items:flex-start;gap:12px;background:#F5F7FD;border:1px solid var(--navy-soft);
  border-radius:12px;padding:14px;cursor:pointer;
}
.elig-confirm input[type="checkbox"]{width:18px;height:18px;accent-color:var(--navy);cursor:pointer;flex-shrink:0;margin-top:2px}
.elig-confirm span{font-size:.84rem;line-height:1.6;color:var(--text)}
.elig-confirm strong{color:var(--navy)}

.side-card{background:var(--white);border:1px solid var(--line);border-radius:16px;padding:18px;margin-bottom:14px}
.side-card h4{font-size:.88rem;font-weight:750;color:var(--navy);margin-bottom:12px;display:flex;align-items:center;gap:8px}
.side-card h4 i{width:16px;height:16px}
.side-tip{display:flex;gap:10px;font-size:.8rem;color:var(--muted);line-height:1.5;margin-bottom:10px}
.side-tip:last-child{margin-bottom:0}
.side-tip i{width:15px;height:15px;color:var(--navy);flex-shrink:0;margin-top:2px}

.form-actions{
  display:flex;align-items:center;gap:12px;flex-wrap:wrap;
  padding:18px 20px;background:var(--white);border:1px solid var(--line);border-radius:16px;
  margin-top:4px;
}
.form-actions .btn-submit{margin-left:auto}
.btn-submit{
  display:inline-flex;align-items:center;gap:8px;padding:13px 22px;border:0;border-radius:11px;
  background:var(--navy);color:#fff;font-family:var(--font);font-size:.9rem;font-weight:700;cursor:pointer;
}
.btn-submit:hover{background:var(--navy-mid)}
.btn-submit i{width:16px;height:16px}
.btn-submit.loading{opacity:.7;pointer-events:none}
.btn-cancel{
  display:inline-flex;align-items:center;gap:7px;padding:12px 16px;border-radius:11px;
  border:1px solid var(--line);background:var(--white);color:var(--muted);font-size:.88rem;font-weight:600;
}
.btn-cancel i{width:15px;height:15px}
.btn-cancel:hover{border-color:var(--navy-light);color:var(--navy)}

@media(max-width:1100px){
  .apply-layout{grid-template-columns:1fr}
  .apply-side{position:static;display:grid;grid-template-columns:1fr 1fr;gap:14px}
  .pi-grid-3{grid-template-columns:1fr 1fr}
}
@media(max-width:768px){
  .form-row,.elig-list,.pi-grid,.pi-grid-3{grid-template-columns:1fr}
  .pi-field.span-2{grid-column:auto}
  .apply-side{grid-template-columns:1fr}
  .form-actions{flex-direction:column-reverse;align-items:stretch}
  .btn-submit,.btn-cancel{justify-content:center}
}
</style>
</head>
<body>
<?php include '../includes/dash-sidebar.php'; ?>

<div class="apply-wrap">

  <?php if($error): ?>
    <div class="flash flash-error"><i data-lucide="triangle-alert"></i><span><?= e($error) ?></span></div>
  <?php endif; ?>

  <?php if($flash): ?>
    <div class="flash flash-<?= ($flash['type'] ?? 'success') === 'error' ? 'error' : 'success' ?>">
      <i data-lucide="<?= ($flash['type'] ?? 'success') === 'error' ? 'triangle-alert' : 'circle-check' ?>"></i>
      <span><?= e($flash['message']) ?></span>
    </div>
  <?php endif; ?>

  <?php if($active_app): ?>
    <div class="active-banner">
      <div style="display:flex;align-items:center;gap:14px">
        <div class="active-banner-ico"><i data-lucide="hourglass"></i></div>
        <div>
          <h3>You already have an active application</h3>
          <p>Status: <strong><?= ucfirst(str_replace('_',' ',$active_app['status'])) ?></strong> — wait for review before applying again.</p>
        </div>
      </div>
      <a href="<?= BASE_URL ?>/my-applications" class="btn-view-app"><i data-lucide="folder-open"></i> View application</a>
    </div>
  <?php endif; ?>

  <?php if(!$active_app): ?>
  <div class="steps-rail" id="stepsRail">
    <span class="step-chip is-active" data-step-chip="1"><span class="n">1</span><i data-lucide="briefcase"></i> Job role</span>
    <span class="step-chip" data-step-chip="2"><span class="n">2</span><i data-lucide="user"></i> Personal</span>
    <span class="step-chip" data-step-chip="3"><span class="n">3</span><i data-lucide="clipboard-list"></i> Experience</span>
    <span class="step-chip" data-step-chip="4"><span class="n">4</span><i data-lucide="file-up"></i> Documents</span>
    <span class="step-chip" data-step-chip="5"><span class="n">5</span><i data-lucide="shield-check"></i> Confirm</span>
  </div>

  <div class="apply-layout">
  <div class="apply-main">
  <form method="POST" id="applyForm" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"/>
    <input type="hidden" name="service_id" id="serviceIdInput" value=""/>
    <div class="step-error" id="stepError"><i data-lucide="triangle-alert"></i><span></span></div>

    <div class="wizard-step is-active" data-step="1">
    <div class="card form-card">
      <div class="card-header">
        <div class="section-ico"><i data-lucide="briefcase"></i></div>
        <div class="section-meta">
          <span class="card-title">Job role</span>
          <span class="card-sub">Select the category you want to apply for</span>
        </div>
      </div>
      <div class="card-body">
        <div class="services-grid">
          <?php foreach($services as $svc): ?>
            <label class="service-option">
              <input type="radio" name="service_display" value="<?= $svc['id'] ?>"
                onchange="document.getElementById('serviceIdInput').value=this.value;hideStepError()"/>
              <div class="service-card">
                <span class="svc-ico"><i data-lucide="<?= svc_icon($svc['name']) ?>"></i></span>
                <span class="service-label"><?= e($svc['name']) ?></span>
                <div class="service-check"><i data-lucide="check"></i></div>
              </div>
            </label>
          <?php endforeach; ?>
        </div>
        <?php if(empty($services)): ?>
          <div class="form-group">
            <label>Job applied for <span class="req">*</span></label>
            <div class="field">
              <i data-lucide="briefcase"></i>
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
    </div>
    </div>

    <div class="wizard-step" data-step="2">
    <div class="card form-card">
      <div class="card-header">
        <div class="section-ico"><i data-lucide="user"></i></div>
        <div class="section-meta">
          <span class="card-title">Personal information</span>
          <span class="card-sub">Use the same details as on your national ID</span>
        </div>
      </div>
      <div class="card-body pi-body">

        <div class="pi-block">
          <div class="pi-block-head">
            <div class="pi-block-ico"><i data-lucide="contact"></i></div>
            <div>
              <strong>Identity &amp; contact</strong>
              <span>How we can reach you and verify your identity</span>
            </div>
          </div>
          <div class="pi-grid-3">
            <div class="pi-field span-2">
              <label>Full name (as on ID) <span class="req" title="Required"></span></label>
              <div class="pi-control">
                <span class="ico"><i data-lucide="user"></i></span>
                <input type="text" name="full_name" value="<?= e($current_user['name'] ?? '') ?>"
                  placeholder="Enter your full legal name" required/>
              </div>
            </div>
            <div class="pi-field">
              <label>Date of birth <span class="req" title="Required"></span></label>
              <div class="pi-control">
                <span class="ico"><i data-lucide="calendar"></i></span>
                <input type="date" name="dob" required/>
              </div>
            </div>
            <div class="pi-field">
              <label>Phone number <span class="req" title="Required"></span></label>
              <div class="pi-control">
                <span class="ico"><i data-lucide="phone"></i></span>
                <input type="tel" name="phone" value="<?= e($current_user['phone'] ?? '') ?>"
                  placeholder="0781234567" required/>
              </div>
            </div>
            <div class="pi-field span-2">
              <label>
                Email address
                <span class="opt-pill">Optional</span>
              </label>
              <div class="pi-control">
                <span class="ico"><i data-lucide="mail"></i></span>
                <input type="email" name="email" value="<?= e($current_user['email'] ?? '') ?>"
                  placeholder="you@example.com"/>
              </div>
            </div>
          </div>
          <div class="pi-note">
            <i data-lucide="info"></i>
            <span>Your full name must match your national ID exactly. We’ll use your phone for application updates.</span>
          </div>
        </div>

        <div class="pi-block">
          <div class="pi-block-head">
            <div class="pi-block-ico"><i data-lucide="map-pinned"></i></div>
            <div>
              <strong>Location</strong>
              <span>Where you currently live in Rwanda</span>
            </div>
          </div>
          <div class="pi-grid">
            <div class="pi-field">
              <label>Home address (sector) <span class="req" title="Required"></span></label>
              <div class="pi-control">
                <span class="ico"><i data-lucide="map-pin"></i></span>
                <input type="text" name="home_address" placeholder="e.g. Kimironko Sector" required/>
              </div>
            </div>
            <div class="pi-field">
              <label>District <span class="req" title="Required"></span></label>
              <div class="pi-control">
                <span class="ico"><i data-lucide="map"></i></span>
                <select name="district" required>
                  <option value="">Select district...</option>
                  <?php foreach($districts as $d): ?>
                    <option value="<?= $d ?>"><?= $d ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>
        </div>

        <div class="pi-block">
          <div class="pi-block-head">
            <div class="pi-block-ico"><i data-lucide="graduation-cap"></i></div>
            <div>
              <strong>Education &amp; experience</strong>
              <span>Your background for matching with households</span>
            </div>
          </div>
          <div class="pi-grid">
            <div class="pi-field">
              <label>Level of education <span class="req" title="Required"></span></label>
              <div class="pi-control">
                <span class="ico"><i data-lucide="book-open"></i></span>
                <select name="education" required>
                  <option value="">Select education level...</option>
                  <?php foreach($education_options as $ed): ?>
                    <option value="<?= $ed ?>"><?= $ed ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="pi-field">
              <label>Years of experience <span class="req" title="Required"></span></label>
              <div class="pi-control">
                <span class="ico"><i data-lucide="timer"></i></span>
                <select name="experience" required>
                  <option value="">Select experience...</option>
                  <?php foreach($experience_options as $ex): ?>
                    <option value="<?= $ex ?>"><?= $ex ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
    </div>

    <div class="wizard-step" data-step="3">
    <div class="card form-card">
      <div class="card-header">
        <div class="section-ico"><i data-lucide="clipboard-list"></i></div>
        <div class="section-meta">
          <span class="card-title">Experience &amp; availability</span>
          <span class="card-sub">Help employers understand your background</span>
        </div>
      </div>
      <div class="card-body">
        <div class="form-group">
          <label>Describe your work experience <span class="req">*</span></label>
          <textarea name="bio"
            placeholder="Example: I have worked as a nanny for two families in Kigali for 3 years..."
            maxlength="600" oninput="updateCount(this,'expCount')" required></textarea>
          <div class="char-counter"><span id="expCount">0</span>/600</div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Availability <span class="req">*</span></label>
            <div class="field">
              <i data-lucide="clock-3"></i>
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
            <label>Expected monthly pay (RWF) <span class="opt">optional</span></label>
            <div class="field">
              <i data-lucide="banknote"></i>
              <input type="text" name="expected_pay" placeholder="e.g. 80,000 RWF"/>
            </div>
          </div>
        </div>
        <div class="form-group">
          <label>Languages you speak <span class="opt">optional</span></label>
          <div class="field">
            <i data-lucide="languages"></i>
            <input type="text" name="languages" placeholder="e.g. Kinyarwanda, French, English"/>
          </div>
        </div>
      </div>
    </div>
    </div>

    <div class="wizard-step" data-step="4">
    <div class="card form-card">
      <div class="card-header">
        <div class="section-ico"><i data-lucide="file-up"></i></div>
        <div class="section-meta">
          <span class="card-title">Required documents</span>
          <span class="card-sub">PDF, JPG or PNG · Max 5MB each</span>
        </div>
      </div>
      <div class="card-body">
        <div class="upload-grid">
          <?php
          $uploads = [
            ['police_clearance','shield','Police clearance certificate'],
            ['application_letter','file-text','Job application letter'],
            ['education_cert','graduation-cap','Education certificate'],
            ['reference_letter','stamp','Reference letter'],
            ['national_id','id-card','National ID (Indangamuntu)'],
          ];
          foreach ($uploads as [$name,$icon,$label]):
          ?>
          <label class="upload-box" data-upload="<?= $name ?>">
            <input type="file" name="<?= $name ?>" accept=".pdf,.jpg,.jpeg,.png" required
              onchange="markUpload(this)"/>
            <span class="up-ico"><i data-lucide="<?= $icon ?>"></i></span>
            <strong><?= $label ?></strong>
            <span class="req-tag">Required</span>
            <span class="upload-hint">Click to upload · PDF / JPG / PNG</span>
            <span class="file-name" hidden></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    </div>

    <div class="wizard-step" data-step="5">
    <div class="elig-card">
      <div class="elig-head">
        <div class="section-ico"><i data-lucide="shield-check"></i></div>
        <div>
          <div class="elig-title">Eligibility confirmation</div>
          <div class="elig-sub">We partner with skilled people who deliver quality home services with pride and professionalism.</div>
        </div>
      </div>
      <div class="elig-list">
        <div class="elig-item"><i data-lucide="circle-check"></i><span>Committed to delivering quality service</span></div>
        <div class="elig-item"><i data-lucide="circle-check"></i><span>Proven experience in domestic work</span></div>
        <div class="elig-item"><i data-lucide="circle-check"></i><span>Reliable, honest, and professional</span></div>
        <div class="elig-item"><i data-lucide="circle-check"></i><span>Good communication skills</span></div>
        <div class="elig-item"><i data-lucide="circle-check"></i><span>Able to perform household tasks</span></div>
        <div class="elig-item"><i data-lucide="circle-check"></i><span>Can provide valid identification</span></div>
        <div class="elig-item"><i data-lucide="circle-check"></i><span>Willing to undergo background verification</span></div>
        <div class="elig-item"><i data-lucide="circle-check"></i><span>Accept HomeHelp Terms and Code of Conduct</span></div>
      </div>
      <label class="elig-confirm">
        <input type="checkbox" name="eligibility_confirm" id="eligibilityCheck" required/>
        <span>
          <strong>I confirm</strong> that I meet the requirements above and agree to HomeHelp’s Terms and Code of Conduct.
        </span>
      </label>
    </div>
    </div>

    <div class="form-actions">
      <button type="button" class="btn-cancel" id="prevBtn" hidden><i data-lucide="arrow-left"></i> Back</button>
      <a href="<?= BASE_URL ?>/provider-dashboard" class="btn-cancel" id="cancelBtn"><i data-lucide="x"></i> Cancel</a>
      <button type="button" class="btn-submit" id="nextBtn">
        <span>Continue</span> <i data-lucide="arrow-right"></i>
      </button>
      <button type="submit" class="btn-submit" id="submitBtn" hidden>
        <span id="btnText">Submit application</span> <i data-lucide="send"></i>
      </button>
    </div>
  </form>
  </div>

  <aside class="apply-side">
    <div class="side-card">
      <h4><i data-lucide="lightbulb"></i> Tips for approval</h4>
      <div class="side-tip"><i data-lucide="check"></i><span>Use your legal name exactly as on your national ID.</span></div>
      <div class="side-tip"><i data-lucide="check"></i><span>Upload clear, readable document scans.</span></div>
      <div class="side-tip"><i data-lucide="check"></i><span>Describe real experience with roles and duration.</span></div>
    </div>
    <div class="side-card">
      <h4><i data-lucide="bell"></i> After you submit</h4>
      <div class="side-tip"><i data-lucide="mail"></i><span>You’ll get an email confirmation once your application is received.</span></div>
      <div class="side-tip"><i data-lucide="search"></i><span>Our team reviews documents and may contact you for verification.</span></div>
    </div>
  </aside>
  </div>
  <?php endif; ?>
</div>

<script>
function updateCount(el,spanId){document.getElementById(spanId).textContent=el.value.length;}
function markUpload(input){
  var box=input.closest('.upload-box');
  var nameEl=box.querySelector('.file-name');
  if(input.files && input.files[0]){
    box.classList.add('has-file');
    nameEl.hidden=false;
    nameEl.textContent=input.files[0].name;
    hideStepError();
  } else {
    box.classList.remove('has-file');
    nameEl.hidden=true;
    nameEl.textContent='';
  }
}

var currentStep=1;
var totalSteps=5;
var applyForm=document.getElementById('applyForm');
var nextBtn=document.getElementById('nextBtn');
var prevBtn=document.getElementById('prevBtn');
var submitBtn=document.getElementById('submitBtn');
var cancelBtn=document.getElementById('cancelBtn');
var stepError=document.getElementById('stepError');

function hideStepError(){
  if(!stepError) return;
  stepError.classList.remove('is-visible');
}
function showStepError(msg){
  if(!stepError) return;
  stepError.querySelector('span').textContent=msg;
  stepError.classList.add('is-visible');
  if(window.lucide) lucide.createIcons();
  stepError.scrollIntoView({behavior:'smooth',block:'nearest'});
}

function showStep(step){
  currentStep=step;
  document.querySelectorAll('.wizard-step').forEach(function(el){
    el.classList.toggle('is-active', Number(el.dataset.step)===step);
  });
  document.querySelectorAll('[data-step-chip]').forEach(function(chip){
    var n=Number(chip.dataset.stepChip);
    chip.classList.toggle('is-active', n===step);
    chip.classList.toggle('is-done', n<step);
  });
  prevBtn.hidden=step===1;
  cancelBtn.hidden=step!==1;
  nextBtn.hidden=step===totalSteps;
  submitBtn.hidden=step!==totalSteps;
  hideStepError();
  if(window.lucide) lucide.createIcons();
  window.scrollTo({top:0,behavior:'smooth'});
}

function validateStep(step){
  if(step===1){
    if(!document.getElementById('serviceIdInput').value){
      showStepError('Please select the job role you want to apply for.');
      return false;
    }
    return true;
  }
  if(step===2){
    var fields=['full_name','phone','dob','home_address','district','education','experience'];
    for(var i=0;i<fields.length;i++){
      var el=applyForm.querySelector('[name="'+fields[i]+'"]');
      if(!el || !String(el.value||'').trim()){
        showStepError('Please complete all required personal information fields.');
        if(el) el.focus();
        return false;
      }
    }
    return true;
  }
  if(step===3){
    var bio=applyForm.querySelector('[name="bio"]');
    var availability=applyForm.querySelector('[name="availability"]');
    if(!bio || !String(bio.value||'').trim()){
      showStepError('Please describe your work experience.');
      if(bio) bio.focus();
      return false;
    }
    if(!availability || !availability.value){
      showStepError('Please select your availability.');
      if(availability) availability.focus();
      return false;
    }
    return true;
  }
  if(step===4){
    var files=['police_clearance','application_letter','education_cert','reference_letter','national_id'];
    for(var j=0;j<files.length;j++){
      var input=applyForm.querySelector('[name="'+files[j]+'"]');
      if(!input || !input.files || !input.files.length){
        showStepError('Please upload all required documents before continuing.');
        return false;
      }
    }
    return true;
  }
  if(step===5){
    var chk=document.getElementById('eligibilityCheck');
    if(!chk || !chk.checked){
      showStepError('Please confirm the eligibility requirements to submit.');
      return false;
    }
    return true;
  }
  return true;
}

if(applyForm){
  nextBtn.addEventListener('click',function(){
    if(!validateStep(currentStep)) return;
    if(currentStep<totalSteps) showStep(currentStep+1);
  });
  prevBtn.addEventListener('click',function(){
    if(currentStep>1) showStep(currentStep-1);
  });
  applyForm.addEventListener('submit',function(e){
    if(!validateStep(1)||!validateStep(2)||!validateStep(3)||!validateStep(4)||!validateStep(5)){
      e.preventDefault();
      return;
    }
    submitBtn.classList.add('loading');
    document.getElementById('btnText').textContent='Submitting...';
  });
  showStep(1);
}
</script>

<?php include '../includes/dash-end.php'; ?>
