<?php
require_once 'config/db.php';
require_once 'config/config.php';

// Fetch active services for job categories
$services = $conn->query("
    SELECT id, name, description, icon
    FROM services
    WHERE status = 'active'
    ORDER BY sort_order ASC, id ASC
")->fetch_all(MYSQLI_ASSOC);

// Stats for social proof
$total_providers = $conn->query("SELECT COUNT(*) FROM users WHERE role='provider'")->fetch_row()[0];
$approved_apps   = $conn->query("SELECT COUNT(*) FROM job_applications WHERE status='approved'")->fetch_row()[0];
$total_agents    = $conn->query("SELECT COUNT(*) FROM users WHERE role='agent'")->fetch_row()[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta name="description" content="Find domestic work in Rwanda. Join Home Help and connect with verified households looking for nannies, housekeepers, chefs, and caregivers."/>
<title>Career | Find Domestic Work in Rwanda — Home Help</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<style>
:root{
  --navy:#0D1B4B;--navy-mid:#152268;--navy-light:#2E5FC2;--navy-soft:#D4DCF5;
  --cream:#FDFAF5;--cream-dark:#F2EDE3;
  --white:#FFFFFF;--text-dark:#1A1A1A;--text-mid:#4A4A4A;--text-soft:#888888;
  --success:#10B981;--success-bg:#ECFDF5;
  --shadow-sm:0 2px 12px rgba(13,27,75,0.08);
  --shadow-md:0 8px 32px rgba(13,27,75,0.12);
  --radius:16px;--radius-sm:8px;
  --font-display:'Cormorant Garamond',Georgia,serif;
  --font-body:'Plus Jakarta Sans',sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:var(--font-body);background:var(--cream);color:var(--text-dark);overflow-x:hidden;line-height:1.65}
a{text-decoration:none;color:inherit}
img{max-width:100%;height:auto;display:block}
::-webkit-scrollbar{width:6px}
::-webkit-scrollbar-track{background:var(--cream)}
::-webkit-scrollbar-thumb{background:var(--navy-light);border-radius:3px}
.container{width:90%;max-width:1200px;margin:0 auto}

/* ── BUTTONS ── */
.btn{display:inline-flex;align-items:center;gap:8px;padding:14px 28px;border-radius:99px;font-family:var(--font-body);font-size:0.9rem;font-weight:600;cursor:pointer;border:none;transition:all 0.3s ease;white-space:nowrap;text-decoration:none}
.btn-primary{background:var(--navy);color:var(--white)}
.btn-primary:hover{background:var(--navy-mid);transform:translateY(-2px);box-shadow:var(--shadow-md)}
.btn-blue{background:var(--navy-light);color:var(--white)}
.btn-blue:hover{background:var(--navy-mid);transform:translateY(-2px);box-shadow:0 8px 24px rgba(46,95,194,0.35)}
.btn-outline{background:transparent;border:2px solid var(--navy);color:var(--navy)}
.btn-outline:hover{background:var(--navy);color:var(--white);transform:translateY(-2px)}
.btn-ghost-white{background:rgba(255,255,255,0.15);color:var(--white);border:1.5px solid rgba(255,255,255,0.4);display:inline-flex;align-items:center;gap:8px;padding:13px 26px;border-radius:99px;font-family:var(--font-body);font-size:0.9rem;font-weight:600;cursor:pointer;transition:all 0.3s;text-decoration:none}
.btn-ghost-white:hover{background:rgba(255,255,255,0.28);transform:translateY(-2px)}

/* ── NAVBAR ── */
.navbar{position:fixed;top:0;left:0;right:0;z-index:1000;padding:14px 0;transition:all 0.4s ease;background:transparent}
.navbar.scrolled{background:var(--white);padding:10px 0;box-shadow:var(--shadow-sm)}
.navbar-inner{display:flex;align-items:center;justify-content:space-between;gap:16px}
.nav-logo{display:flex;align-items:center;gap:10px;flex-shrink:0;text-decoration:none}
.nav-logo img{width:44px;height:44px;object-fit:contain}
.nav-logo-text{font-family:var(--font-display);font-size:1.45rem;font-weight:700;color:var(--white);transition:color 0.3s}
.navbar.scrolled .nav-logo-text{color:var(--navy)}
.nav-links{display:flex;align-items:center;gap:28px;list-style:none}
.nav-links a{font-size:0.88rem;font-weight:500;color:rgba(255,255,255,0.85);transition:color 0.2s;position:relative;text-decoration:none}
.navbar.scrolled .nav-links a{color:var(--text-mid)}
.nav-links a::after{content:'';position:absolute;bottom:-3px;left:0;width:0;height:2px;background:var(--navy-light);border-radius:2px;transition:width 0.3s}
.nav-links a:hover::after,.nav-links a.active::after{width:100%}
.nav-links a.active{color:var(--white);font-weight:600}
.navbar.scrolled .nav-links a.active{color:var(--navy)}
.nav-cta{display:flex;align-items:center;gap:10px;flex-shrink:0}
.nav-login{font-size:0.88rem;font-weight:600;color:rgba(255,255,255,0.92);transition:color 0.3s;text-decoration:none}
.navbar.scrolled .nav-login{color:var(--text-mid)}
.btn-nav-worker{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:99px;background:var(--navy-light);color:var(--white);font-size:0.82rem;font-weight:700;border:none;cursor:pointer;transition:all 0.3s;text-decoration:none}
.btn-nav-worker:hover{background:var(--navy-mid);transform:translateY(-1px)}
.btn-nav-start{display:inline-flex;align-items:center;padding:9px 18px;border-radius:99px;font-family:var(--font-body);font-size:0.84rem;font-weight:600;cursor:pointer;transition:all 0.3s;text-decoration:none;background:rgba(255,255,255,0.15);color:var(--white);border:1.5px solid rgba(255,255,255,0.4)}
.btn-nav-start:hover{background:rgba(255,255,255,0.25)}
.navbar.scrolled .btn-nav-start{background:var(--navy);color:var(--white);border-color:var(--navy)}
.navbar.scrolled .btn-nav-start:hover{background:var(--navy-mid)}
.hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:4px;background:none;border:none}
.hamburger span{display:block;width:24px;height:2px;background:var(--white);border-radius:2px;transition:all 0.3s}
.navbar.scrolled .hamburger span{background:var(--navy)}
.hamburger.open span:nth-child(1){transform:translateY(7px) rotate(45deg)}
.hamburger.open span:nth-child(2){opacity:0}
.hamburger.open span:nth-child(3){transform:translateY(-7px) rotate(-45deg)}

/* ── MOBILE MENU ── */
.mobile-menu{display:none;position:fixed;inset:0;background:var(--navy);z-index:999;flex-direction:column;align-items:center;justify-content:flex-start;padding:90px 20px 40px;opacity:0;pointer-events:none;transition:opacity 0.3s;overflow-y:auto}
.mobile-menu.open{opacity:1;pointer-events:all;display:flex}
.mob-link{font-family:var(--font-display);font-size:1.9rem;font-weight:700;color:var(--white);transition:color 0.2s;text-decoration:none;padding:10px 0;width:100%;text-align:center;border-bottom:1px solid rgba(255,255,255,0.06)}
.mob-link.active{color:var(--navy-soft)}
.mob-link:hover{opacity:0.7}
.mobile-footer{display:flex;gap:12px;flex-wrap:wrap;justify-content:center;padding:24px 0 0;width:100%;border-top:1px solid rgba(255,255,255,0.1);margin-top:8px}

/* ── HERO ── */
.hero{position:relative;background:linear-gradient(135deg,var(--navy) 0%,var(--navy-mid) 60%,#1a3580 100%);min-height:88vh;display:flex;align-items:center;overflow:hidden;padding:120px 0 80px}
.hero::before{content:'';position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,0.03) 1px,transparent 1px);background-size:48px 48px}
.hero::after{content:'';position:absolute;top:-30%;right:-10%;width:60%;height:160%;background:radial-gradient(ellipse,rgba(46,95,194,0.25) 0%,transparent 60%);border-radius:50%}
.hero-inner{position:relative;z-index:1;display:grid;grid-template-columns:1fr 1fr;gap:64px;align-items:center}
.hero-left{}
.hero-tag{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:rgba(255,255,255,0.9);font-size:0.75rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;padding:6px 16px;border-radius:99px;margin-bottom:24px}
.hero-title{font-family:var(--font-display);font-size:clamp(2.8rem,5vw,4.8rem);font-weight:700;color:var(--white);line-height:1.05;margin-bottom:20px}
.hero-title em{font-style:italic;color:var(--navy-soft);display:block}
.hero-desc{font-size:1.05rem;color:rgba(255,255,255,0.72);line-height:1.8;max-width:480px;margin-bottom:36px}
.hero-actions{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:48px}
.hero-stats{display:flex;gap:32px;flex-wrap:wrap}
.hero-stat-num{font-family:var(--font-display);font-size:2rem;font-weight:700;color:var(--white);line-height:1}
.hero-stat-label{font-size:0.78rem;color:rgba(255,255,255,0.55);margin-top:3px}

/* Hero right card */
.hero-card{background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.12);border-radius:24px;padding:32px;backdrop-filter:blur(12px)}
.hero-card-title{font-family:var(--font-display);font-size:1.5rem;font-weight:700;color:var(--white);margin-bottom:6px}
.hero-card-sub{font-size:0.84rem;color:rgba(255,255,255,0.55);margin-bottom:24px}
.job-item{display:flex;align-items:center;gap:14px;padding:14px;border-radius:12px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.08);margin-bottom:10px;transition:all 0.25s;text-decoration:none}
.job-item:last-child{margin-bottom:0}
.job-item:hover{background:rgba(255,255,255,0.12);border-color:rgba(255,255,255,0.2);transform:translateX(4px)}
.job-icon{width:44px;height:44px;border-radius:12px;background:rgba(46,95,194,0.3);display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0}
.job-info{flex:1}
.job-title{font-weight:700;font-size:0.9rem;color:var(--white)}
.job-meta{font-size:0.75rem;color:rgba(255,255,255,0.5);margin-top:2px}
.job-arrow{color:rgba(255,255,255,0.4);font-size:1rem;transition:transform 0.2s}
.job-item:hover .job-arrow{transform:translateX(4px);color:var(--white)}

/* ── HOW IT WORKS ── */
.how{padding:100px 0;background:var(--white)}
.how-header{text-align:center;margin-bottom:64px}
.section-tag{display:inline-block;background:var(--navy-soft);color:var(--navy);font-size:0.72rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;padding:6px 16px;border-radius:99px;margin-bottom:16px}
.section-title{font-family:var(--font-display);font-size:clamp(2rem,4vw,3.2rem);font-weight:700;color:var(--navy);line-height:1.2;margin-bottom:16px}
.section-title em{font-style:italic;color:var(--navy-light)}
.section-sub{font-size:1rem;color:var(--text-mid);max-width:520px;line-height:1.7}
.how-header .section-sub{margin:0 auto}
.steps-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:24px}
.step-card{background:var(--cream);border:1.5px solid var(--cream-dark);border-radius:var(--radius);padding:28px 24px;position:relative;transition:all 0.3s}
.step-card:hover{transform:translateY(-6px);box-shadow:var(--shadow-md);border-color:var(--navy-soft)}
.step-num{width:40px;height:40px;border-radius:10px;background:var(--navy);color:var(--white);font-family:var(--font-display);font-size:1rem;font-weight:700;display:flex;align-items:center;justify-content:center;margin-bottom:16px}
.step-card:hover .step-num{background:var(--navy-light)}
.step-icon{font-size:1.6rem;margin-bottom:12px}
.step-title{font-family:var(--font-display);font-size:1.1rem;font-weight:700;color:var(--navy);margin-bottom:8px}
.step-desc{font-size:0.83rem;color:var(--text-mid);line-height:1.65}
.step-connector{position:absolute;top:44px;right:-14px;width:28px;height:2px;background:var(--cream-dark);z-index:1}
.steps-grid .step-card:last-child .step-connector{display:none}

/* ── JOBS GRID ── */
.jobs{padding:100px 0;background:var(--cream)}
.jobs-header{display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:20px;margin-bottom:48px}
.jobs-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px}
.job-card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);padding:28px 24px;display:flex;flex-direction:column;align-items:flex-start;transition:all 0.35s;text-decoration:none;color:inherit;position:relative;overflow:hidden}
.job-card::before{content:'';position:absolute;bottom:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--navy-light),var(--navy));transform:scaleX(0);transition:transform 0.35s}
.job-card:hover{transform:translateY(-8px);box-shadow:var(--shadow-md);border-color:var(--navy-soft)}
.job-card:hover::before{transform:scaleX(1)}
.job-card-icon{width:56px;height:56px;border-radius:16px;background:var(--navy-soft);display:flex;align-items:center;justify-content:center;font-size:1.6rem;margin-bottom:16px}
.job-card-name{font-family:var(--font-display);font-size:1.2rem;font-weight:700;color:var(--navy);margin-bottom:8px}
.job-card-desc{font-size:0.82rem;color:var(--text-soft);line-height:1.6;flex:1;margin-bottom:20px}
.job-card-cta{display:inline-flex;align-items:center;gap:6px;font-size:0.82rem;font-weight:700;color:var(--navy-light);transition:gap 0.2s}
.job-card:hover .job-card-cta{gap:10px}

/* ── BENEFITS ── */
.benefits{padding:100px 0;background:var(--navy);position:relative;overflow:hidden}
.benefits::before{content:'';position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,0.03) 1px,transparent 1px);background-size:48px 48px}
.benefits-inner{position:relative;z-index:1;display:grid;grid-template-columns:1fr 1fr;gap:80px;align-items:center}
.benefits-left .section-tag{background:rgba(255,255,255,0.1);color:rgba(255,255,255,0.85)}
.benefits-left .section-title{color:var(--white)}
.benefits-left .section-sub{color:rgba(255,255,255,0.6)}
.benefits-list{margin-top:36px;display:flex;flex-direction:column;gap:16px}
.benefit-item{display:flex;gap:16px;align-items:flex-start}
.benefit-icon{width:44px;height:44px;border-radius:12px;background:rgba(46,95,194,0.3);border:1px solid rgba(46,95,194,0.4);display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0}
.benefit-title{font-weight:700;font-size:0.95rem;color:var(--white);margin-bottom:4px}
.benefit-desc{font-size:0.82rem;color:rgba(255,255,255,0.55);line-height:1.6}

/* Requirements card */
.requirements-card{background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:24px;padding:36px}
.req-title{font-family:var(--font-display);font-size:1.4rem;font-weight:700;color:var(--white);margin-bottom:6px}
.req-sub{font-size:0.84rem;color:rgba(255,255,255,0.5);margin-bottom:24px}
.req-list{display:flex;flex-direction:column;gap:12px}
.req-item{display:flex;align-items:center;gap:12px;font-size:0.88rem;color:rgba(255,255,255,0.8)}
.req-check{width:22px;height:22px;border-radius:50%;background:rgba(46,95,194,0.35);border:1px solid rgba(46,95,194,0.5);display:flex;align-items:center;justify-content:center;font-size:0.65rem;color:var(--navy-soft);flex-shrink:0}
.req-divider{height:1px;background:rgba(255,255,255,0.08);margin:16px 0}
.req-note{font-size:0.78rem;color:rgba(255,255,255,0.4);line-height:1.6}

/* ── CTA APPLY ── */
.cta-apply{padding:80px 0;background:var(--white)}
.cta-apply-inner{background:linear-gradient(135deg,var(--navy) 0%,var(--navy-mid) 100%);border-radius:24px;padding:56px 64px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:32px;position:relative;overflow:hidden}
.cta-apply-inner::before{content:'';position:absolute;top:-50%;right:-10%;width:50%;height:200%;background:radial-gradient(ellipse,rgba(46,95,194,0.3) 0%,transparent 60%);border-radius:50%}
.cta-apply-text{position:relative;z-index:1}
.cta-apply-text h2{font-family:var(--font-display);font-size:clamp(1.8rem,3vw,2.8rem);font-weight:700;color:var(--white);margin-bottom:10px}
.cta-apply-text h2 em{font-style:italic;color:var(--navy-soft)}
.cta-apply-text p{font-size:0.95rem;color:rgba(255,255,255,0.65);max-width:480px;line-height:1.7}
.cta-apply-actions{position:relative;z-index:1;display:flex;gap:12px;flex-wrap:wrap}

/* ── TESTIMONIALS ── */
.testimonials{padding:100px 0;background:var(--cream)}
.testimonials-header{text-align:center;margin-bottom:56px}
.testimonials-header .section-sub{margin:0 auto}
.t-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px}
.t-card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);padding:28px;transition:all 0.35s;position:relative}
.t-card:hover{transform:translateY(-6px);box-shadow:var(--shadow-md)}
.t-card::before{content:'"';position:absolute;top:16px;right:24px;font-family:var(--font-display);font-size:4rem;color:var(--navy-soft);line-height:1}
.stars{display:flex;gap:3px;margin-bottom:14px}
.star{color:var(--navy-light);font-size:0.9rem}
.t-text{font-size:0.92rem;color:var(--text-mid);line-height:1.75;margin-bottom:20px;font-style:italic}
.t-author{display:flex;align-items:center;gap:12px}
.t-avatar{width:42px;height:42px;border-radius:50%;background:linear-gradient(145deg,var(--navy-light),var(--navy));display:flex;align-items:center;justify-content:center;color:var(--white);font-family:var(--font-display);font-size:1rem;font-weight:700;flex-shrink:0}
.t-info h5{font-size:0.9rem;font-weight:700;color:var(--navy)}
.t-info p{font-size:0.76rem;color:var(--text-soft)}

/* ── FOOTER ── */
.footer{background:var(--navy);color:rgba(255,255,255,0.7);padding:64px 0 0}
.footer-grid{display:grid;grid-template-columns:1.8fr 1fr 1fr 1fr;gap:48px;margin-bottom:48px}
.footer-brand{display:flex;align-items:center;gap:10px;margin-bottom:14px}
.footer-brand img{width:44px;height:44px;object-fit:contain}
.footer-brand span{font-family:var(--font-display);font-size:1.5rem;font-weight:700;color:var(--white)}
.footer-desc{font-size:0.85rem;line-height:1.75;color:rgba(255,255,255,0.55);margin-bottom:20px}
.social-links{display:flex;gap:10px}
.social-link{width:36px;height:36px;border-radius:9px;background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.12);display:flex;align-items:center;justify-content:center;font-size:0.9rem;transition:all 0.3s;cursor:pointer}
.social-link:hover{background:var(--navy-light);border-color:var(--navy-light)}
.footer-col h5{font-family:var(--font-display);font-size:1rem;font-weight:700;color:var(--white);margin-bottom:16px}
.footer-links{display:flex;flex-direction:column;gap:10px;list-style:none}
.footer-links a{font-size:0.85rem;color:rgba(255,255,255,0.55);transition:color 0.2s;text-decoration:none}
.footer-links a:hover{color:var(--white)}
.footer-bottom{border-top:1px solid rgba(255,255,255,0.08);padding:24px 0;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px}
.footer-bottom p{font-size:0.82rem;color:rgba(255,255,255,0.4)}
.footer-bottom a{font-size:0.82rem;color:rgba(255,255,255,0.4);transition:color 0.2s;text-decoration:none}
.footer-bottom a:hover{color:var(--white)}
.footer-credit{text-align:center;padding:14px 0;border-top:1px solid rgba(255,255,255,0.06);font-size:0.76rem;color:rgba(255,255,255,0.3)}
.footer-credit a{color:rgba(255,255,255,0.45);text-decoration:none}
.footer-credit a:hover{color:var(--white)}

/* ── REVEAL ── */
.reveal{opacity:0;transform:translateY(30px);transition:opacity 0.7s ease,transform 0.7s ease}
.reveal.visible{opacity:1;transform:translateY(0)}
.reveal-delay-1{transition-delay:0.1s}
.reveal-delay-2{transition-delay:0.2s}
.reveal-delay-3{transition-delay:0.3s}
.reveal-delay-4{transition-delay:0.4s}

/* ── RESPONSIVE ── */
@media(max-width:1024px){
  .hero-inner,.benefits-inner{grid-template-columns:1fr;gap:48px}
  .hero-card{display:none}
  .steps-grid{grid-template-columns:repeat(2,1fr)}
  .footer-grid{grid-template-columns:1fr 1fr;gap:32px}
  .cta-apply-inner{padding:40px 32px}
}
@media(max-width:768px){
  .nav-links,.nav-cta{display:none}.hamburger{display:flex}
  .steps-grid{grid-template-columns:1fr}
  .footer-grid{grid-template-columns:1fr;gap:28px}
  .footer-bottom{flex-direction:column;text-align:center}
  .t-grid{grid-template-columns:1fr}
  .hero-stats{gap:20px}
  .cta-apply-inner{padding:32px 24px}
}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar" id="navbar">
  <div class="container">
    <div class="navbar-inner">
      <a href="/" class="nav-logo">
        <img src="Image/icon/homehelp_logo_transparent_white.svg" id="navLogo" alt="Home Help"/>
        <span class="nav-logo-text">Home Help</span>
      </a>
      <ul class="nav-links">
        <li><a href="/">Home</a></li>
        <li><a href="/#services">Services</a></li>
        <li><a href="/#about">About</a></li>
        <li><a href="/career" class="active">Career</a></li>
        <li><a href="/#faq">FAQ</a></li>
        <li><a href="/#contact">Contact</a></li>
      </ul>
      <div class="nav-cta">
        <a href="/worker-login" class="nav-login">Login</a>
        <a href="/find-worker" class="btn-nav-worker">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          Find a Worker
        </a>
        <a href="/worker-login" class="btn-nav-start">Apply Now</a>
      </div>
      <button class="hamburger" id="hamburger" aria-label="Menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</nav>

<!-- MOBILE MENU -->
<div class="mobile-menu" id="mobileMenu">
  <a href="/"          class="mob-link">Home</a>
  <a href="/#services" class="mob-link">Services</a>
  <a href="/#about"    class="mob-link">About</a>
  <a href="/career"    class="mob-link active">Career</a>
  <a href="/#faq"      class="mob-link">FAQ</a>
  <a href="/#contact"  class="mob-link">Contact</a>
  <a href="/worker-login" class="mob-link">Login</a>
  <div class="mobile-footer">
    <a href="/find-worker"   class="btn-nav-worker">Find a Worker</a>
    <a href="/worker-login"  class="btn btn-blue">Apply Now →</a>
  </div>
</div>

<!-- HERO -->
<section class="hero">
  <div class="container">
    <div class="hero-inner">
      <div class="hero-left">
        <div class="hero-tag">🏆 Over <?= $approved_apps ?: '50' ?>+ placements made</div>
        <h1 class="hero-title">
          Find Domestic Work<br/>
          <em>in Rwanda</em>
        </h1>
        <p class="hero-desc">Join hundreds of domestic workers who found stable, dignified employment through Home Help. Our agents match you with verified households — fast.</p>
        <div class="hero-actions">
          <!-- CHANGE G: redirect to /worker-login -->
          <a href="/worker-login" class="btn btn-blue">Apply for a Job →</a>
          <a href="#how-it-works" class="btn-ghost-white">How It Works</a>
        </div>
        <div class="hero-stats">
          <div>
            <div class="hero-stat-num"><?= $total_providers ?: '200' ?>+</div>
            <div class="hero-stat-label">Workers Registered</div>
          </div>
          <div>
            <div class="hero-stat-num"><?= count($services) ?: '5' ?></div>
            <div class="hero-stat-label">Job Categories</div>
          </div>
          <div>
            <div class="hero-stat-num"><?= $total_agents ?: '10' ?>+</div>
            <div class="hero-stat-label">Active Agents</div>
          </div>
        </div>
      </div>

      <div class="hero-card">
        <div class="hero-card-title">Open Positions</div>
        <div class="hero-card-sub">Click a role to start your application</div>
        <?php foreach(array_slice($services, 0, 5) as $svc): ?>
          <!-- CHANGE F: fixed & → ? -->
          <a href="/worker-login?service=<?= $svc['id'] ?>" class="job-item">
            <div class="job-icon"><?= $svc['icon'] ?: '💼' ?></div>
            <div class="job-info">
              <div class="job-title"><?= htmlspecialchars($svc['name']) ?></div>
              <div class="job-meta">Kigali &amp; surrounding areas · Open</div>
            </div>
            <span class="job-arrow">→</span>
          </a>
        <?php endforeach; ?>
        <?php if(empty($services)): ?>
          <a href="/worker-login" class="job-item">
            <div class="job-icon">💼</div>
            <div class="job-info">
              <div class="job-title">All Domestic Roles</div>
              <div class="job-meta">Kigali &amp; surrounding areas · Open</div>
            </div>
            <span class="job-arrow">→</span>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="how" id="how-it-works">
  <div class="container">
    <div class="how-header reveal">
      <span class="section-tag">Simple Process</span>
      <h2 class="section-title">Get a Job in <em>4 Easy Steps</em></h2>
      <p class="section-sub">From registration to your first day at work — our agents guide you through every step.</p>
    </div>
    <div class="steps-grid">
      <div class="step-card reveal reveal-delay-1">
        <div class="step-num">01</div>
        <div class="step-icon">📝</div>
        <h4 class="step-title">Create Your Account</h4>
        <p class="step-desc">Register as a Job Seeker. Fill in your name, email, and create a password. It's completely free.</p>
        <div class="step-connector"></div>
      </div>
      <div class="step-card reveal reveal-delay-2">
        <div class="step-num">02</div>
        <div class="step-icon">💼</div>
        <h4 class="step-title">Apply for a Role</h4>
        <p class="step-desc">Choose the service you want to provide, add your experience, availability, and location.</p>
        <div class="step-connector"></div>
      </div>
      <div class="step-card reveal reveal-delay-3">
        <div class="step-num">03</div>
        <div class="step-icon">🔍</div>
        <h4 class="step-title">Team Reviews You</h4>
        <p class="step-desc">Our trained team reviews your application, verifies your details, and contacts you via WhatsApp.</p>
        <div class="step-connector"></div>
      </div>
      <div class="step-card reveal reveal-delay-4">
        <div class="step-num">04</div>
        <div class="step-icon">🏆</div>
        <h4 class="step-title">Start Working</h4>
        <p class="step-desc">Get matched with a verified household, agree on terms, and start your new job!</p>
      </div>
    </div>
  </div>
</section>

<!-- JOB CATEGORIES -->
<section class="jobs" id="jobs">
  <div class="container">
    <div class="jobs-header reveal">
      <div>
        <span class="section-tag">Open Positions</span>
        <h2 class="section-title">Choose Your <em>Job Category</em></h2>
        <p class="section-sub">We work with households across Rwanda who need trusted domestic professionals.</p>
      </div>
      <!-- CHANGE G -->
      <a href="/worker-login" class="btn btn-primary">Apply Now →</a>
    </div>
    <div class="jobs-grid">
      <?php if(!empty($services)): ?>
        <?php foreach($services as $i => $svc):
          $delay = ['reveal-delay-1','reveal-delay-2','reveal-delay-3'][$i % 3];
          $desc = $svc['description'] ?: 'Professional domestic work opportunity available in Rwanda.';
        ?>
          <!-- CHANGE F: fixed & → ? | CHANGE G: /worker-login -->
          <a href="/worker-login?service=<?= $svc['id'] ?>" class="job-card reveal <?= $delay ?>">
            <div class="job-card-icon"><?= $svc['icon'] ?: '💼' ?></div>
            <div class="job-card-name"><?= htmlspecialchars($svc['name']) ?></div>
            <p class="job-card-desc"><?= htmlspecialchars($desc) ?></p>
            <span class="job-card-cta">Apply Now →</span>
          </a>
        <?php endforeach; ?>
      <?php else: ?>
        <?php
        $fallbacks = [
          ['🧒','Nanny / Babysitter','Care for children in a loving household environment.'],
          ['🧹','Housekeeper / Cleaner','Keep homes clean, tidy, and organized.'],
          ['👨‍🍳','Home Chef / Cook','Prepare daily meals and special event catering.'],
          ['🤝','Caregiver','Support elderly or patients with daily needs.'],
          ['🐾','Pet Groomer','Care for household pets.'],
        ];
        foreach($fallbacks as $i => [$icon,$name,$desc]):
          $delay = ['reveal-delay-1','reveal-delay-2','reveal-delay-3'][$i % 3];
        ?>
          <a href="/worker-login" class="job-card reveal <?= $delay ?>">
            <div class="job-card-icon"><?= $icon ?></div>
            <div class="job-card-name"><?= $name ?></div>
            <p class="job-card-desc"><?= $desc ?></p>
            <span class="job-card-cta">Apply Now →</span>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- BENEFITS + REQUIREMENTS -->
<section class="benefits">
  <div class="container">
    <div class="benefits-inner">
      <div class="benefits-left reveal">
        <span class="section-tag">Why Join Us</span>
        <h2 class="section-title" style="color:var(--white)">Benefits of Working<br/><em style="color:var(--navy-soft)">With Home Help</em></h2>
        <p class="section-sub" style="color:rgba(255,255,255,0.6)">We don't just find you a job — we support you throughout the process and beyond.</p>
        <div class="benefits-list">
          <div class="benefit-item">
            <div class="benefit-icon">💰</div>
            <div>
              <div class="benefit-title">Free to Register &amp; Apply</div>
              <div class="benefit-desc">No fees. Creating your profile and applying for jobs costs you nothing — ever.</div>
            </div>
          </div>
          <div class="benefit-item">
            <div class="benefit-icon">🛡️</div>
            <div>
              <div class="benefit-title">Verified Households Only</div>
              <div class="benefit-desc">Every client household is reviewed by our team before you're matched with them.</div>
            </div>
          </div>
          <div class="benefit-item">
            <div class="benefit-icon">📱</div>
            <div>
              <div class="benefit-title">WhatsApp Updates</div>
              <div class="benefit-desc">Get real-time updates on your application status directly on WhatsApp.</div>
            </div>
          </div>
          <div class="benefit-item">
            <div class="benefit-icon">🎯</div>
            <div>
              <div class="benefit-title">Dedicated Team Support</div>
              <div class="benefit-desc">A real person reviews your file and helps you through the hiring process step by step.</div>
            </div>
          </div>
        </div>
      </div>

      <div class="reveal reveal-delay-2">
        <div class="requirements-card">
          <div class="req-title">What You Need to Apply</div>
          <div class="req-sub">Basic requirements for all positions</div>
          <div class="req-list">
            <div class="req-item"><div class="req-check">✓</div>Rwandan National ID (Indangamuntu)</div>
            <div class="req-item"><div class="req-check">✓</div>Valid phone number for WhatsApp</div>
            <div class="req-item"><div class="req-check">✓</div>At least 1 year of relevant work experience</div>
            <div class="req-item"><div class="req-check">✓</div>Good references from previous employers</div>
            <div class="req-item"><div class="req-check">✓</div>Willingness to undergo background check</div>
            <div class="req-item"><div class="req-check">✓</div>Clean conduct record</div>
          </div>
          <div class="req-divider"></div>
          <div class="req-note">Don't meet all requirements? Apply anyway — our team will guide you on what to prepare. We work with people at all experience levels.</div>
          <!-- CHANGE G -->
          <a href="/worker-login" class="btn btn-blue" style="width:100%;justify-content:center;margin-top:20px">Start My Application →</a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- TESTIMONIALS -->
<section class="testimonials">
  <div class="container">
    <div class="testimonials-header reveal">
      <span class="section-tag">Success Stories</span>
      <h2 class="section-title">Workers Who Found Jobs <em>Through Us</em></h2>
      <p class="section-sub">Real stories from people who built their careers with Home Help.</p>
    </div>
    <div class="t-grid">
      <div class="t-card reveal reveal-delay-1">
        <div class="stars"><?= str_repeat('<span class="star">★</span>',5) ?></div>
        <p class="t-text">After months of struggling to find work, I applied on Home Help and got placed with a great family in less than a week. The team was very helpful and patient.</p>
        <div class="t-author">
          <div class="t-avatar">J</div>
          <div class="t-info"><h5>Jean Claude Habimana</h5><p>Housekeeper · Musanze</p></div>
        </div>
      </div>
      <div class="t-card reveal reveal-delay-2">
        <div class="stars"><?= str_repeat('<span class="star">★</span>',5) ?></div>
        <p class="t-text">I was nervous about registering online but the process was so simple. Within 3 days a team member called me on WhatsApp and helped me find a nanny job in Kigali.</p>
        <div class="t-author">
          <div class="t-avatar">M</div>
          <div class="t-info"><h5>Marie Uwimana</h5><p>Nanny · Kigali, Gasabo</p></div>
        </div>
      </div>
      <div class="t-card reveal reveal-delay-3">
        <div class="stars"><?= str_repeat('<span class="star">★</span>',5) ?></div>
        <p class="t-text">I have been cooking for families for years but never had a formal way to find clients. Home Help changed that completely. Now I have steady work every month.</p>
        <div class="t-author">
          <div class="t-avatar">P</div>
          <div class="t-info"><h5>Patrick Nzeyimana</h5><p>Home Chef · Kigali, Kicukiro</p></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- CTA APPLY -->
<section class="cta-apply">
  <div class="container">
    <div class="cta-apply-inner">
      <div class="cta-apply-text">
        <h2>Ready to Start Your <em>New Career?</em></h2>
        <p>Join hundreds of domestic workers already earning through Home Help. Create your free profile in minutes and let our team do the rest.</p>
      </div>
      <div class="cta-apply-actions">
        <!-- CHANGE G -->
        <a href="/worker-login" class="btn btn-blue">Apply for a Job →</a>
        <a href="/worker-login" class="btn-ghost-white">Already have an account?</a>
      </div>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer class="footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <div class="footer-brand">
          <img src="Image/icon/homehelp_logo_transparent_white.svg" alt="Home Help"/>
          <span>Home Help</span>
        </div>
        <p class="footer-desc">Rwanda's trusted marketplace for domestic services — connecting households with verified workers across the country.</p>
        <div class="social-links">
          <div class="social-link">📘</div>
          <div class="social-link">📸</div>
          <div class="social-link">🐦</div>
          <div class="social-link">💬</div>
        </div>
      </div>
      <div class="footer-col">
        <h5>For Job Seekers</h5>
        <ul class="footer-links">
          <li><a href="/worker-register">Create Account</a></li>
          <li><a href="/worker-login">Login</a></li>
          <li><a href="#jobs">Browse Jobs</a></li>
          <li><a href="#how-it-works">How It Works</a></li>
          <li><a href="/#contact">Contact Team</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h5>Job Categories</h5>
        <ul class="footer-links">
          <?php foreach(array_slice($services,0,5) as $svc): ?>
            <!-- CHANGE F: fixed & → ? | CHANGE G: /worker-login -->
            <li><a href="/worker-login?service=<?= $svc['id'] ?>"><?= htmlspecialchars($svc['name']) ?></a></li>
          <?php endforeach; ?>
          <?php if(empty($services)): ?>
            <li><a href="/worker-login">Nanny</a></li>
            <li><a href="/worker-login">Housekeeper</a></li>
            <li><a href="/worker-login">Chef</a></li>
          <?php endif; ?>
        </ul>
      </div>
      <div class="footer-col">
        <h5>Platform</h5>
        <ul class="footer-links">
          <li><a href="/">Home</a></li>
          <li><a href="/find-worker">Find a Worker</a></li>
          <li><a href="/#about">About Us</a></li>
          <li><a href="/#faq">FAQ</a></li>
          <li><a href="/#contact">Contact</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <p>© <?php echo date('Y'); ?> Home Help · Brilliant Elite Ltd · Rwanda 🇷🇼</p>
      <div style="display:flex;gap:20px"><a href="#">Privacy Policy</a><a href="#">Terms of Service</a></div>
    </div>
  </div>
  <div class="footer-credit">
    Built by <a href="https://bibhq.com" target="_blank">Brilliant Elite Ltd</a> · Kigali, Rwanda 🇷🇼
  </div>
</footer>

<script>
// Navbar scroll
const navbar = document.getElementById('navbar');
window.addEventListener('scroll', function(){
  const scrolled = window.scrollY > 60;
  navbar.classList.toggle('scrolled', scrolled);
  const logo = document.getElementById('navLogo');
  if(logo) logo.src = scrolled
    ? 'Image/icon/homehelp_logo_transparent_dark.svg'
    : 'Image/icon/homehelp_logo_transparent_white.svg';
});

// Mobile menu
const hamburger = document.getElementById('hamburger');
const mobileMenu = document.getElementById('mobileMenu');
hamburger.addEventListener('click', function(){
  hamburger.classList.toggle('open');
  mobileMenu.classList.toggle('open');
  document.body.style.overflow = mobileMenu.classList.contains('open') ? 'hidden' : '';
});
document.querySelectorAll('.mob-link').forEach(function(l){
  l.addEventListener('click', function(){
    hamburger.classList.remove('open');
    mobileMenu.classList.remove('open');
    document.body.style.overflow = '';
  });
});

// Scroll reveal
const observer = new IntersectionObserver(function(entries){
  entries.forEach(function(e){
    if(e.isIntersecting){ e.target.classList.add('visible'); observer.unobserve(e.target); }
  });
}, {threshold: 0.12});
document.querySelectorAll('.reveal').forEach(function(el){ observer.observe(el); });

// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(function(link){
  link.addEventListener('click', function(e){
    const target = document.querySelector(link.getAttribute('href'));
    if(target){ e.preventDefault(); target.scrollIntoView({behavior:'smooth', block:'start'}); }
  });
});
</script>
</body>
</html>