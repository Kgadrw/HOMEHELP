<?php
require_once 'config/db.php';
require_once 'config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Terms of Service | Home Help Rwanda</title>
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

/* NAVBAR */
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

/* HERO */
.hero{background:linear-gradient(135deg,var(--navy) 0%,var(--navy-mid) 100%);padding:120px 0 60px;position:relative;overflow:hidden}
.hero::before{content:'';position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,0.03) 1px,transparent 1px);background-size:48px 48px}
.hero-inner{position:relative;z-index:1}
.hero-tag{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:rgba(255,255,255,0.9);font-size:0.75rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;padding:6px 16px;border-radius:99px;margin-bottom:20px}
.hero-title{font-family:var(--font-display);font-size:clamp(2.2rem,5vw,3.8rem);font-weight:700;color:var(--white);line-height:1.1;margin-bottom:12px}
.hero-title em{font-style:italic;color:var(--navy-soft)}
.hero-meta{font-size:0.88rem;color:rgba(255,255,255,0.55)}

/* CONTENT */
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
.doc-section ul,.doc-section ol{margin:0 0 14px 20px}
.doc-section li{font-size:0.92rem;color:var(--text-mid);margin-bottom:8px;line-height:1.7}
.doc-section strong{color:var(--text-dark);font-weight:600}
.doc-divider{height:1px;background:var(--cream-dark);margin-bottom:48px}
.highlight-box{background:var(--navy-soft);border:1.5px solid var(--navy-light);border-radius:var(--radius-sm);padding:16px 20px;margin-bottom:16px}
.highlight-box p{color:var(--navy);margin:0;font-weight:500}

/* FOOTER */
.doc-footer{background:var(--navy);color:rgba(255,255,255,0.5);padding:32px 0;text-align:center;font-size:0.84rem}
.doc-footer a{color:rgba(255,255,255,0.7);transition:color 0.2s}
.doc-footer a:hover{color:var(--white)}

@media(max-width:768px){
  .doc-wrap{grid-template-columns:1fr}.doc-nav{display:none}
  .navbar-inner .nav-right{gap:8px}
}
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
      <div class="hero-tag">📄 Legal Document</div>
      <h1 class="hero-title">Terms of <em>Service</em></h1>
      <p class="hero-meta">Last updated: <?= date('d F Y') ?> &nbsp;·&nbsp; Effective immediately &nbsp;·&nbsp; Home Help Rwanda</p>
    </div>
  </div>
</section>

<!-- CONTENT -->
<div class="container">
  <div class="doc-wrap">

    <!-- SIDEBAR NAV -->
    <nav class="doc-nav">
      <div class="doc-nav-title">Contents</div>
      <ul class="doc-nav-list">
        <li><a href="#acceptance">1. Acceptance</a></li>
        <li><a href="#services">2. Our Services</a></li>
        <li><a href="#accounts">3. User Accounts</a></li>
        <li><a href="#clients">4. Clients</a></li>
        <li><a href="#workers">5. Job Seekers</a></li>
        <li><a href="#conduct">6. Conduct</a></li>
        <li><a href="#privacy">7. Privacy</a></li>
        <li><a href="#liability">8. Liability</a></li>
        <li><a href="#termination">9. Termination</a></li>
        <li><a href="#changes">10. Changes</a></li>
        <li><a href="#contact">11. Contact</a></li>
      </ul>
    </nav>

    <!-- DOCUMENT BODY -->
    <div class="doc-content">

      <div class="highlight-box">
        <p>📌 Please read these Terms carefully before using Home Help. By creating an account or using our platform, you agree to be bound by these Terms.</p>
      </div>

      <!-- 1 -->
      <div class="doc-section" id="acceptance">
        <div class="doc-section-num">Section 01</div>
        <h2>Acceptance of Terms</h2>
        <p>Welcome to <strong>Home Help</strong>, a domestic services marketplace operated by <strong>Brilliant Elite Ltd</strong>, a company registered in Kigali, Rwanda. These Terms of Service ("Terms") govern your access to and use of our platform, website, and related services.</p>
        <p>By registering an account, booking a service, or applying for a job through Home Help, you confirm that you have read, understood, and agreed to these Terms. If you do not agree, you must not use our platform.</p>
        <p>These Terms apply to all users including <strong>Clients</strong> (households seeking domestic help), <strong>Job Seekers / Providers</strong> (domestic workers), and <strong>Agents</strong> (Home Help staff).</p>
      </div>
      <div class="doc-divider"></div>

      <!-- 2 -->
      <div class="doc-section" id="services">
        <div class="doc-section-num">Section 02</div>
        <h2>Our Services</h2>
        <p>Home Help provides an online marketplace platform that:</p>
        <ul>
          <li>Connects households with verified domestic workers across Rwanda</li>
          <li>Allows clients to book domestic services including cleaning, childcare, cooking, caregiving, and more</li>
          <li>Allows domestic workers to apply for available job positions</li>
          <li>Assigns dedicated agents to review, approve, and manage bookings and applications</li>
          <li>Sends notifications via WhatsApp to keep users updated on their requests</li>
        </ul>
        <p>Home Help acts as an <strong>intermediary platform</strong> only. We do not directly employ domestic workers. We facilitate the connection between clients and workers and verify worker credentials through our agent network.</p>
      </div>
      <div class="doc-divider"></div>

      <!-- 3 -->
      <div class="doc-section" id="accounts">
        <div class="doc-section-num">Section 03</div>
        <h2>User Accounts</h2>
        <p>To use most features of Home Help, you must create an account. By creating an account, you agree to:</p>
        <ul>
          <li>Provide <strong>accurate, complete, and truthful</strong> information during registration</li>
          <li>Keep your account credentials <strong>confidential and secure</strong></li>
          <li>Immediately notify us of any unauthorized use of your account</li>
          <li>Not share your account with any other person</li>
          <li>Be responsible for all activities that occur under your account</li>
        </ul>
        <p>Home Help reserves the right to suspend or terminate accounts that contain false information or violate these Terms.</p>
        <p>You must be at least <strong>18 years old</strong> to create an account on Home Help.</p>
      </div>
      <div class="doc-divider"></div>

      <!-- 4 -->
      <div class="doc-section" id="clients">
        <div class="doc-section-num">Section 04</div>
        <h2>Clients — Booking Services</h2>
        <p>As a <strong>Client</strong> on Home Help, you agree to:</p>
        <ul>
          <li>Provide accurate booking information including location, dates, and requirements</li>
          <li>Treat all domestic workers assigned to you with <strong>dignity and respect</strong></li>
          <li>Provide a <strong>safe working environment</strong> for all workers</li>
          <li>Not request services outside of what was agreed upon in your booking</li>
          <li>Agree on fair compensation with workers before service begins</li>
          <li>Not attempt to contact workers outside of the platform to bypass our process</li>
        </ul>
        <p>Home Help agents will review your booking request and assign a suitable worker. You will be notified via WhatsApp once your booking is approved or if further information is needed.</p>
      </div>
      <div class="doc-divider"></div>

      <!-- 5 -->
      <div class="doc-section" id="workers">
        <div class="doc-section-num">Section 05</div>
        <h2>Job Seekers — Applying for Work</h2>
        <p>As a <strong>Job Seeker</strong> on Home Help, you agree to:</p>
        <ul>
          <li>Provide <strong>truthful and accurate</strong> information in your application, including experience, location, and identity</li>
          <li>Submit valid identification documents when requested by agents</li>
          <li>Perform agreed services <strong>professionally and reliably</strong></li>
          <li>Respect clients' homes, property, and privacy at all times</li>
          <li>Not engage in any illegal activities while performing services</li>
          <li>Notify clients and Home Help agents if you are unable to fulfill a booking</li>
        </ul>
        <p>Home Help reserves the right to remove job seekers from the platform if they provide false information, receive consistent complaints, or violate these Terms.</p>
      </div>
      <div class="doc-divider"></div>

      <!-- 6 -->
      <div class="doc-section" id="conduct">
        <div class="doc-section-num">Section 06</div>
        <h2>Prohibited Conduct</h2>
        <p>All users of Home Help are strictly prohibited from:</p>
        <ul>
          <li>Submitting false, misleading, or fraudulent information</li>
          <li>Harassing, threatening, or abusing other users, agents, or Home Help staff</li>
          <li>Using the platform for any <strong>illegal purpose</strong></li>
          <li>Attempting to circumvent our platform by dealing directly with matched parties to avoid our process</li>
          <li>Posting or sharing offensive, discriminatory, or harmful content</li>
          <li>Attempting to hack, disrupt, or damage our platform or systems</li>
          <li>Creating multiple accounts to abuse the platform</li>
        </ul>
        <p>Violation of these rules may result in immediate account suspension or permanent ban without notice.</p>
      </div>
      <div class="doc-divider"></div>

      <!-- 7 -->
      <div class="doc-section" id="privacy">
        <div class="doc-section-num">Section 07</div>
        <h2>Privacy &amp; Data</h2>
        <p>Your privacy is important to us. By using Home Help, you agree that we may collect and use your personal information as described in our <a href="/privacy" style="color:var(--navy-light);font-weight:600">Privacy Policy</a>.</p>
        <p>We collect information such as your name, email, phone number, location, and booking details to operate the platform and improve our services.</p>
        <p>We <strong>do not sell</strong> your personal data to third parties. Your information is used solely to facilitate connections between clients and workers, and to communicate with you about your account and bookings.</p>
      </div>
      <div class="doc-divider"></div>

      <!-- 8 -->
      <div class="doc-section" id="liability">
        <div class="doc-section-num">Section 08</div>
        <h2>Limitation of Liability</h2>
        <p>Home Help is a <strong>marketplace platform</strong> that facilitates connections between clients and domestic workers. We are not directly responsible for:</p>
        <ul>
          <li>The quality, safety, or outcome of any service provided by a worker</li>
          <li>Any loss, damage, injury, or harm that occurs during a service</li>
          <li>Disputes between clients and workers</li>
          <li>Actions taken by workers or clients outside of our platform</li>
        </ul>
        <p>We take reasonable steps to verify worker credentials through our agent network, but we <strong>cannot guarantee</strong> the conduct of any individual. Users engage with the platform at their own risk.</p>
        <p>To the maximum extent permitted by Rwandan law, Brilliant Elite Ltd's total liability shall not exceed the fees paid (if any) to Home Help in the preceding 3 months.</p>
      </div>
      <div class="doc-divider"></div>

      <!-- 9 -->
      <div class="doc-section" id="termination">
        <div class="doc-section-num">Section 09</div>
        <h2>Termination</h2>
        <p>Home Help reserves the right to <strong>suspend or terminate</strong> your account at any time, with or without notice, if:</p>
        <ul>
          <li>You violate any of these Terms</li>
          <li>We detect fraudulent or abusive behavior</li>
          <li>Your account has been inactive for an extended period</li>
          <li>We decide to discontinue the service</li>
        </ul>
        <p>You may also delete your account at any time by contacting us at <strong>info@homehelp.rw</strong>. Upon account deletion, your data will be handled as per our Privacy Policy.</p>
      </div>
      <div class="doc-divider"></div>

      <!-- 10 -->
      <div class="doc-section" id="changes">
        <div class="doc-section-num">Section 10</div>
        <h2>Changes to These Terms</h2>
        <p>Home Help reserves the right to update or modify these Terms at any time. When we make significant changes, we will notify you via:</p>
        <ul>
          <li>A notice on the Home Help platform</li>
          <li>An email or WhatsApp message to your registered contact</li>
        </ul>
        <p>Your continued use of the platform after changes are published constitutes your acceptance of the updated Terms. We recommend reviewing these Terms periodically.</p>
      </div>
      <div class="doc-divider"></div>

      <!-- 11 -->
      <div class="doc-section" id="contact">
        <div class="doc-section-num">Section 11</div>
        <h2>Contact Us</h2>
        <p>If you have any questions about these Terms of Service, please contact us:</p>
        <ul>
          <li><strong>Company:</strong> Brilliant Elite Ltd</li>
          <li><strong>Email:</strong> info@homehelp.rw</li>
          <li><strong>WhatsApp:</strong> +250 790 533 144</li>
          <li><strong>Location:</strong> Kigali, Rwanda</li>
          <li><strong>Website:</strong> hh.bibhq.org</li>
        </ul>
        <p>These Terms are governed by the laws of <strong>Rwanda</strong>. Any disputes arising from these Terms shall be resolved under Rwandan jurisdiction.</p>
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
      <a href="/privacy">Privacy Policy</a> &nbsp;·&nbsp;
      <a href="/#contact">Contact</a>
    </p>
  </div>
</footer>

<script>
// Highlight active nav item on scroll
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