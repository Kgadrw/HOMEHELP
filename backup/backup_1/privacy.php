<?php
require_once 'config/db.php';
require_once 'config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Privacy Policy | Home Help Rwanda</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<style>
:root{
  --navy:#0D1B4B;--navy-mid:#152268;--navy-light:#2E5FC2;--navy-soft:#D4DCF5;
  --cream:#FDFAF5;--cream-dark:#F2EDE3;
  --white:#FFFFFF;--text-dark:#1A1A1A;--text-mid:#4A4A4A;--text-soft:#888888;
  --shadow-sm:0 2px 12px rgba(13,27,75,0.08);
  --radius:14px;--radius-sm:8px;
  --font-display:'Cormorant Garamond',Georgia,serif;
  --font-body:'Plus Jakarta Sans',sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:var(--font-body);background:var(--cream);color:var(--text-dark);line-height:1.7}
a{text-decoration:none;color:inherit}
.container{width:90%;max-width:860px;margin:0 auto}
.navbar{position:fixed;top:0;left:0;right:0;z-index:1000;padding:14px 0;background:var(--white);box-shadow:var(--shadow-sm)}
.navbar-inner{display:flex;align-items:center;justify-content:space-between}
.nav-logo{display:flex;align-items:center;gap:10px;text-decoration:none}
.nav-logo img{width:40px;height:40px;object-fit:contain}
.nav-logo-text{font-family:var(--font-display);font-size:1.4rem;font-weight:700;color:var(--navy)}
.nav-right{display:flex;align-items:center;gap:12px}
.btn-nav{display:inline-flex;align-items:center;padding:9px 18px;border-radius:99px;font-family:var(--font-body);font-size:0.84rem;font-weight:600;cursor:pointer;transition:all 0.3s;text-decoration:none}
.btn-nav-outline{border:1.5px solid var(--navy);color:var(--navy);background:transparent}
.btn-nav-outline:hover{background:var(--navy);color:var(--white)}
.btn-nav-solid{background:var(--navy);color:var(--white);border:none}
.btn-nav-solid:hover{background:var(--navy-mid);transform:translateY(-1px)}
.hero{background:linear-gradient(135deg,var(--navy) 0%,var(--navy-mid) 100%);padding:120px 0 60px;position:relative;overflow:hidden}
.hero::before{content:'';position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,0.03) 1px,transparent 1px);background-size:48px 48px}
.hero-inner{position:relative;z-index:1}
.hero-tag{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:rgba(255,255,255,0.9);font-size:0.75rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;padding:6px 16px;border-radius:99px;margin-bottom:20px}
.hero-title{font-family:var(--font-display);font-size:clamp(2.2rem,5vw,3.8rem);font-weight:700;color:var(--white);line-height:1.1;margin-bottom:12px}
.hero-title em{font-style:italic;color:var(--navy-soft)}
.hero-meta{font-size:0.88rem;color:rgba(255,255,255,0.55)}
.doc-wrap{display:grid;grid-template-columns:220px 1fr;gap:40px;padding:60px 0 100px;align-items:start}
.doc-nav{position:sticky;top:90px}
.doc-nav-title{font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:var(--text-soft);margin-bottom:12px}
.doc-nav-list{list-style:none;display:flex;flex-direction:column;gap:4px}
.doc-nav-list a{display:block;padding:8px 12px;border-radius:var(--radius-sm);font-size:0.84rem;color:var(--text-mid);transition:all 0.2s;border-left:2px solid transparent}
.doc-nav-list a:hover{color:var(--navy);background:var(--navy-soft);border-left-color:var(--navy-light)}
.doc-nav-list a.active{color:var(--navy);background:var(--navy-soft);border-left-color:var(--navy);font-weight:600}
.doc-content{min-width:0}
.doc-section{margin-bottom:48px;scroll-margin-top:90px}
.doc-section-num{font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:var(--navy-light);margin-bottom:6px}
.doc-section h2{font-family:var(--font-display);font-size:1.7rem;font-weight:700;color:var(--navy);margin-bottom:16px;line-height:1.2}
.doc-section p{font-size:0.92rem;color:var(--text-mid);margin-bottom:14px;line-height:1.8}
.doc-section ul{margin:0 0 14px 20px}
.doc-section li{font-size:0.92rem;color:var(--text-mid);margin-bottom:8px;line-height:1.7}
.doc-section strong{color:var(--text-dark);font-weight:600}
.doc-divider{height:1px;background:var(--cream-dark);margin-bottom:48px}
.highlight-box{background:var(--navy-soft);border:1.5px solid var(--navy-light);border-radius:var(--radius-sm);padding:16px 20px;margin-bottom:16px}
.highlight-box p{color:var(--navy);margin:0;font-weight:500}
.data-table{width:100%;border-collapse:collapse;font-size:0.88rem;margin-bottom:16px;border-radius:var(--radius-sm);overflow:hidden}
.data-table th{background:var(--navy);color:var(--white);padding:10px 14px;text-align:left;font-weight:600;font-size:0.8rem}
.data-table td{padding:10px 14px;border-bottom:1px solid var(--cream-dark);color:var(--text-mid);vertical-align:top}
.data-table tr:last-child td{border-bottom:none}
.data-table tr:nth-child(even) td{background:var(--cream)}
.doc-footer{background:var(--navy);color:rgba(255,255,255,0.5);padding:32px 0;text-align:center;font-size:0.84rem}
.doc-footer a{color:rgba(255,255,255,0.7);transition:color 0.2s}
.doc-footer a:hover{color:var(--white)}
@media(max-width:768px){.doc-wrap{grid-template-columns:1fr}.doc-nav{display:none}.navbar-inner .nav-right{gap:8px}}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
  <div class="container">
    <div class="navbar-inner">
      <a href="/" class="nav-logo">
        <img src="Image/icon/homehelp_logo_transparent_dark.svg" alt="Home Help" onerror="this.style.display='none'"/>
        <span class="nav-logo-text">Home Help</span>
      </a>
      <div class="nav-right">
        <a href="/login"    class="btn-nav btn-nav-outline">Login</a>
        <a href="/register" class="btn-nav btn-nav-solid">Get Started</a>
      </div>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="container">
    <div class="hero-inner">
      <div class="hero-tag">🔒 Legal Document</div>
      <h1 class="hero-title">Privacy <em>Policy</em></h1>
      <p class="hero-meta">Last updated: <?= date('d F Y') ?> &nbsp;·&nbsp; Effective immediately &nbsp;·&nbsp; Home Help Rwanda</p>
    </div>
  </div>
</section>

<!-- CONTENT -->
<div class="container">
  <div class="doc-wrap">

    <!-- SIDEBAR -->
    <nav class="doc-nav">
      <div class="doc-nav-title">Contents</div>
      <ul class="doc-nav-list">
        <li><a href="#overview">1. Overview</a></li>
        <li><a href="#collect">2. Data We Collect</a></li>
        <li><a href="#use">3. How We Use It</a></li>
        <li><a href="#sharing">4. Data Sharing</a></li>
        <li><a href="#storage">5. Data Storage</a></li>
        <li><a href="#rights">6. Your Rights</a></li>
        <li><a href="#cookies">7. Cookies</a></li>
        <li><a href="#children">8. Children</a></li>
        <li><a href="#changes">9. Changes</a></li>
        <li><a href="#contact">10. Contact</a></li>
      </ul>
    </nav>

    <!-- DOCUMENT -->
    <div class="doc-content">

      <div class="highlight-box">
        <p>🔒 Your privacy matters to us. We only collect what we need to operate Home Help, and we never sell your personal data to anyone.</p>
      </div>

      <!-- 1 -->
      <div class="doc-section" id="overview">
        <div class="doc-section-num">Section 01</div>
        <h2>Overview</h2>
        <p>This Privacy Policy explains how <strong>Brilliant Elite Ltd</strong> ("we", "us", "Home Help") collects, uses, stores, and protects your personal information when you use our domestic services platform at <strong>hh.bibhq.org</strong>.</p>
        <p>By creating an account or using Home Help, you agree to the collection and use of your information as described in this policy. This policy complies with applicable Rwandan data protection principles.</p>
      </div>
      <div class="doc-divider"></div>

      <!-- 2 -->
      <div class="doc-section" id="collect">
        <div class="doc-section-num">Section 02</div>
        <h2>Data We Collect</h2>
        <p>We collect the following categories of personal information:</p>
        <table class="data-table">
          <thead>
            <tr><th>Data Type</th><th>What We Collect</th><th>Why</th></tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>Account Info</strong></td>
              <td>Name, email, phone number, password (hashed)</td>
              <td>To create and manage your account</td>
            </tr>
            <tr>
              <td><strong>Location</strong></td>
              <td>District, street/neighbourhood</td>
              <td>To match clients with nearby workers</td>
            </tr>
            <tr>
              <td><strong>Booking Data</strong></td>
              <td>Service type, dates, requirements, budget</td>
              <td>To process and manage bookings</td>
            </tr>
            <tr>
              <td><strong>Application Data</strong></td>
              <td>Experience, availability, expected pay, languages</td>
              <td>To process job applications</td>
            </tr>
            <tr>
              <td><strong>Profile Photo</strong></td>
              <td>Uploaded photo image</td>
              <td>To display on your profile</td>
            </tr>
            <tr>
              <td><strong>Usage Data</strong></td>
              <td>IP address, browser type, pages visited</td>
              <td>Security, fraud prevention, analytics</td>
            </tr>
            <tr>
              <td><strong>Contact Messages</strong></td>
              <td>Messages sent via contact form</td>
              <td>To respond to your enquiries</td>
            </tr>
          </tbody>
        </table>
        <p>We collect only the information <strong>necessary</strong> to provide our services. We do not collect sensitive data such as ID numbers unless explicitly required and consented to during agent verification.</p>
      </div>
      <div class="doc-divider"></div>

      <!-- 3 -->
      <div class="doc-section" id="use">
        <div class="doc-section-num">Section 03</div>
        <h2>How We Use Your Data</h2>
        <p>Your personal information is used to:</p>
        <ul>
          <li><strong>Create and manage</strong> your Home Help account</li>
          <li><strong>Process bookings</strong> and match clients with suitable workers</li>
          <li><strong>Review job applications</strong> and connect workers with households</li>
          <li><strong>Send notifications</strong> about your bookings or applications via WhatsApp and email</li>
          <li><strong>Prevent fraud</strong> and protect platform security (login attempt tracking)</li>
          <li><strong>Improve our platform</strong> through anonymous usage analytics</li>
          <li><strong>Respond to support requests</strong> and contact form messages</li>
          <li><strong>Comply with legal obligations</strong> under Rwandan law</li>
        </ul>
        <p>We will <strong>never</strong> use your data for unsolicited marketing without your explicit consent.</p>
      </div>
      <div class="doc-divider"></div>

      <!-- 4 -->
      <div class="doc-section" id="sharing">
        <div class="doc-section-num">Section 04</div>
        <h2>Data Sharing</h2>
        <p>We <strong>do not sell</strong> your personal data. We share your information only in these limited circumstances:</p>
        <ul>
          <li><strong>Home Help Agents:</strong> Our internal agents see your booking or application details to review and process them</li>
          <li><strong>Matched Parties:</strong> When a booking is approved, limited contact information may be shared between the client and assigned worker to facilitate the service</li>
          <li><strong>WhatsApp (Meta):</strong> We use WhatsApp links to notify you — this is subject to WhatsApp's own privacy policy</li>
          <li><strong>Legal Requirements:</strong> We may disclose information if required by Rwandan law or a court order</li>
          <li><strong>Business Transfer:</strong> If Brilliant Elite Ltd is acquired or merged, your data may transfer to the new owner under the same privacy protections</li>
        </ul>
        <p>We do not share your data with advertising networks, data brokers, or any third party for commercial purposes.</p>
      </div>
      <div class="doc-divider"></div>

      <!-- 5 -->
      <div class="doc-section" id="storage">
        <div class="doc-section-num">Section 05</div>
        <h2>Data Storage &amp; Security</h2>
        <p>Your data is stored on secure servers hosted in a professional data centre. We implement the following security measures:</p>
        <ul>
          <li><strong>Password hashing:</strong> All passwords are hashed using bcrypt — we never store plain-text passwords</li>
          <li><strong>CSRF protection:</strong> All forms are protected against cross-site request forgery attacks</li>
          <li><strong>Login rate limiting:</strong> Accounts are temporarily locked after multiple failed login attempts</li>
          <li><strong>Sensitive file blocking:</strong> Configuration files and logs are blocked from public access</li>
          <li><strong>HTTPS:</strong> All data in transit is encrypted via SSL/TLS</li>
        </ul>
        <p>We retain your data for as long as your account is active. If you delete your account, we will delete your personal data within <strong>30 days</strong>, except where retention is required by law.</p>
      </div>
      <div class="doc-divider"></div>

      <!-- 6 -->
      <div class="doc-section" id="rights">
        <div class="doc-section-num">Section 06</div>
        <h2>Your Rights</h2>
        <p>You have the following rights regarding your personal data:</p>
        <ul>
          <li><strong>Access:</strong> Request a copy of all personal data we hold about you</li>
          <li><strong>Correction:</strong> Update or correct inaccurate information via your profile page</li>
          <li><strong>Deletion:</strong> Request deletion of your account and associated data</li>
          <li><strong>Restriction:</strong> Request that we limit how we process your data</li>
          <li><strong>Portability:</strong> Request your data in a readable format</li>
          <li><strong>Objection:</strong> Object to certain types of data processing</li>
        </ul>
        <p>To exercise any of these rights, contact us at <strong>info@homehelp.rw</strong>. We will respond within <strong>14 business days</strong>.</p>
      </div>
      <div class="doc-divider"></div>

      <!-- 7 -->
      <div class="doc-section" id="cookies">
        <div class="doc-section-num">Section 07</div>
        <h2>Cookies &amp; Sessions</h2>
        <p>Home Help uses the following minimal tracking technologies:</p>
        <ul>
          <li><strong>Session cookies:</strong> Used to keep you logged in during your visit. These are deleted when you close your browser or log out.</li>
          <li><strong>Security tokens:</strong> CSRF tokens are stored in your session to protect against attacks</li>
        </ul>
        <p>We do <strong>not</strong> use advertising cookies, analytics cookies, or third-party tracking cookies. We do not use Google Analytics or Facebook Pixel on our platform.</p>
      </div>
      <div class="doc-divider"></div>

      <!-- 8 -->
      <div class="doc-section" id="children">
        <div class="doc-section-num">Section 08</div>
        <h2>Children's Privacy</h2>
        <p>Home Help is not intended for use by persons under the age of <strong>18</strong>. We do not knowingly collect personal data from minors.</p>
        <p>If you believe a minor has created an account on our platform, please contact us immediately at <strong>info@homehelp.rw</strong> and we will delete the account promptly.</p>
      </div>
      <div class="doc-divider"></div>

      <!-- 9 -->
      <div class="doc-section" id="changes">
        <div class="doc-section-num">Section 09</div>
        <h2>Changes to This Policy</h2>
        <p>We may update this Privacy Policy from time to time to reflect changes in our practices or applicable laws. When we make significant changes, we will:</p>
        <ul>
          <li>Update the "Last updated" date at the top of this page</li>
          <li>Notify you via WhatsApp or email if the changes are material</li>
        </ul>
        <p>We encourage you to review this policy periodically. Your continued use of Home Help after changes are published means you accept the updated policy.</p>
      </div>
      <div class="doc-divider"></div>

      <!-- 10 -->
      <div class="doc-section" id="contact">
        <div class="doc-section-num">Section 10</div>
        <h2>Contact &amp; Data Requests</h2>
        <p>For any privacy-related questions, data access requests, or concerns, please contact our data controller:</p>
        <ul>
          <li><strong>Company:</strong> Brilliant Elite Ltd</li>
          <li><strong>Email:</strong> info@homehelp.rw</li>
          <li><strong>WhatsApp:</strong> +250 790 533 144</li>
          <li><strong>Location:</strong> Kigali, Rwanda</li>
        </ul>
        <p>This Privacy Policy is governed by the laws of <strong>Rwanda</strong>. We are committed to protecting your privacy and handling your data responsibly.</p>
      </div>

    </div><!-- end doc-content -->
  </div><!-- end doc-wrap -->
</div>

<!-- FOOTER -->
<footer class="doc-footer">
  <div class="container">
    <p>© <?= date('Y') ?> Home Help · Brilliant Elite Ltd · Kigali, Rwanda 🇷🇼</p>
    <p style="margin-top:8px">
      <a href="/">Home</a> &nbsp;·&nbsp;
      <a href="/terms">Terms of Service</a> &nbsp;·&nbsp;
      <a href="/#contact">Contact</a>
    </p>
  </div>
</footer>

<script>
var sections = document.querySelectorAll('.doc-section');
var navLinks  = document.querySelectorAll('.doc-nav-list a');
window.addEventListener('scroll', function(){
  var scrollY = window.scrollY + 100;
  sections.forEach(function(section){
    if(section.offsetTop <= scrollY && section.offsetTop + section.offsetHeight > scrollY){
      navLinks.forEach(function(l){l.classList.remove('active');});
      var active = document.querySelector('.doc-nav-list a[href="#'+section.id+'"]');
      if(active) active.classList.add('active');
    }
  });
});
</script>
</body>
</html>