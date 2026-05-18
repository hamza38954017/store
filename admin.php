<?php

require_once __DIR__ . '/helpers.php';
session_start();

// ── Auth ──────────────────────────────────────────────────────────────────
if (isset($_POST['login'])) {
    $u = $_POST['username'] ?? '';
    $p = $_POST['password'] ?? '';
    $r = apiCall('admin_login', ['username'=>$u,'password'=>$p]);
    if ($r['success'] ?? false) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user']      = $u;
        header('Location: admin.php'); exit;
    }
    $loginError = $r['msg'] ?? 'Invalid credentials.';
}
if (isset($_GET['logout'])) { session_destroy(); header('Location: admin.php'); exit; }

// ── AJAX/POST handlers — proxy to API ─────────────────────────────────────
if (isAdminLoggedIn() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    // Send full $_POST to api.php — api key added by apiCall()
    $result = apiCall($action, array_map('strval', $_POST));
    echo json_encode($result); exit;
}

// ── Load admin dashboard data ─────────────────────────────────────────────
$s = [];
$totalPhotos = $totalPurchases = $pendingPurch = 0;
$verifiedRevenue = $totalVisitors = $totalViews = 0;
$utrSubmitted = $utrNotSubmitted = 0;
$photos = $purchases = $purchasesUTR = $sessionsNoUTR = [];
$reviews = $notifications = $slides = $photoStats = $visitors = $recentRevUsers = [];
$vPage = max(1,(int)($_GET['vpage']??1));
$vTotal = $vPages = 0;

if (isAdminLoggedIn()) {
    $d = apiCall('get_admin_data', ['vpage' => $vPage]);

    $st              = $d['stats']          ?? [];
    $totalPhotos     = (int)($st['totalPhotos']     ?? 0);
    $totalPurchases  = (int)($st['totalPurchases']  ?? 0);
    $pendingPurch    = (int)($st['pendingPurch']     ?? 0);
    $verifiedRevenue = (float)($st['verifiedRev']   ?? 0);
    $totalVisitors   = (int)($st['totalVisitors']   ?? 0);
    $totalViews      = (int)($st['totalViews']      ?? 0);
    $utrSubmitted    = (int)($st['utrSubmitted']    ?? 0);
    $utrNotSubmitted = (int)($st['utrNotSubmitted'] ?? 0);

    $photos         = $d['photos']         ?? [];
    $purchases      = $d['purchases']      ?? [];
    $purchasesUTR   = $d['purchasesUTR']   ?? [];
    $sessionsNoUTR  = $d['sessionsNoUTR']  ?? [];
    $reviews        = $d['reviews']        ?? [];
    $notifications  = $d['notifications']  ?? [];
    $slides         = $d['slides']         ?? [];
    $photoStats     = $d['photoStats']     ?? [];
    $visitors       = $d['visitors']       ?? [];
    $vTotal         = (int)($d['vTotal']   ?? 0);
    $vPages         = (int)($d['vPages']   ?? 1);
    $recentRevUsers = $d['recentRevUsers'] ?? [];
    $s              = $d['settings']       ?? [];
}
?>   
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin — <?= e($s['site_name']??'Photo Seller') ?></title>
<meta name="robots" content="noindex,nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">
<style>
:root{
  --p:#FF6B35;--a:#FFD700;--bg:#090912;--sb:#0f0f22;
  --text:#e6e6f0;--border:rgba(255,255,255,0.07);
  --card:rgba(255,255,255,0.03);--r:14px;
  --ok:#22c55e;--err:#ef4444;--warn:#f59e0b;
}
body.day{--bg:#EEEEF5;--sb:#FFFFFF;--text:#1A1A2E;--border:rgba(0,0,0,0.09);--card:rgba(0,0,0,0.03);}

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{font-size:14px;overflow-x:hidden}
body{background:var(--bg);color:var(--text);font-family:'DM Sans',sans-serif;min-height:100vh;display:flex;overflow-x:hidden;}
::-webkit-scrollbar{width:4px;height:4px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--p);border-radius:2px}

/* ── LOGIN ── */
.login-wrap{width:100%;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;background:radial-gradient(ellipse 80% 60% at 50% -10%,rgba(255,107,53,0.14) 0%,transparent 65%);}
.login-box{background:var(--sb);border:1px solid var(--border);border-radius:24px;padding:48px 40px;width:min(420px,100%);text-align:center;}
.login-logo{font-family:'Playfair Display',serif;font-size:2rem;font-weight:900;background:linear-gradient(135deg,var(--p),var(--a));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin-bottom:4px;}
.login-sub{color:rgba(128,128,128,0.55);font-size:.84rem;margin-bottom:32px;}
.fg{margin-bottom:16px;text-align:left;}
.fl{display:block;font-size:.68rem;font-weight:700;color:rgba(128,128,128,0.5);margin-bottom:5px;text-transform:uppercase;letter-spacing:.9px;}
.fi{width:100%;background:var(--card);border:1px solid var(--border);border-radius:11px;padding:13px 15px;color:var(--text);font-size:.9rem;font-family:inherit;outline:none;transition:.3s;}
.fi:focus{border-color:var(--p);box-shadow:0 0 0 3px rgba(255,107,53,0.1);}
.fi::placeholder{color:rgba(128,128,128,0.25);}
.login-btn{width:100%;background:linear-gradient(135deg,var(--p),#ff8c00);color:#fff;border:none;padding:14px;border-radius:12px;font-weight:700;font-size:.95rem;cursor:pointer;margin-top:8px;transition:.3s;font-family:inherit;}
.login-btn:hover{opacity:.9;transform:translateY(-2px);}
.login-err{background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.25);border-radius:10px;padding:10px 14px;font-size:.82rem;color:#f87171;margin-bottom:14px;}

/* ── LAYOUT ── */
.sidebar{width:215px;min-width:215px;background:var(--sb);border-right:1px solid var(--border);display:flex;flex-direction:column;position:sticky;top:0;height:100vh;overflow-y:auto;flex-shrink:0;z-index:200;transition:transform .3s ease;}
.sb-logo{padding:20px 18px;border-bottom:1px solid var(--border);}
.sb-logo span{font-family:'Playfair Display',serif;font-size:1.05rem;font-weight:900;background:linear-gradient(135deg,var(--p),var(--a));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;display:block;line-height:1.2;}
.sb-logo p{font-size:.62rem;color:rgba(128,128,128,0.35);margin-top:3px;}
.sb-nav{list-style:none;padding:8px 0;flex:1;}
.nav-sec{padding:10px 16px 4px;font-size:.58rem;text-transform:uppercase;letter-spacing:2px;color:rgba(128,128,128,0.28);font-weight:700;}
.nav-item button,.nav-item a{display:flex;align-items:center;gap:8px;width:100%;padding:9px 16px;color:rgba(128,128,128,0.65);text-decoration:none;font-size:.82rem;font-weight:500;border:none;background:none;cursor:pointer;font-family:inherit;border-left:3px solid transparent;transition:.18s;text-align:left;}
.nav-item button:hover,.nav-item a:hover,.nav-item button.act,.nav-item a.act{color:var(--text);background:rgba(255,107,53,0.06);border-left-color:var(--p);}
.ni-icon{font-size:.88rem;width:17px;text-align:center;flex-shrink:0;}
.ni-pip{background:var(--p);color:#fff;font-size:.58rem;font-weight:700;min-width:16px;height:16px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;padding:0 3px;margin-left:auto;}
.sb-foot{padding:12px 16px;border-top:1px solid var(--border);}
.sb-foot a{font-size:.76rem;color:rgba(128,128,128,0.38);text-decoration:none;display:flex;align-items:center;gap:6px;transition:.2s;}
.sb-foot a:hover{color:var(--err);}

.sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:199;backdrop-filter:blur(3px);}
.sb-overlay.open{display:block;}

.main{flex:1;min-width:0;display:flex;flex-direction:column;overflow-x:hidden;}
.topbar{background:var(--sb);border-bottom:1px solid var(--border);padding:0 20px;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;flex-shrink:0;}
.tb-left{display:flex;align-items:center;gap:10px;}
.hamburger{background:none;border:none;color:var(--text);font-size:1.25rem;cursor:pointer;padding:6px;display:none;align-items:center;}
.tb-title{font-weight:700;font-size:.9rem;}
.tb-right{display:flex;align-items:center;gap:8px;}
.tb-badge{background:rgba(255,107,53,0.1);color:var(--p);font-size:.7rem;font-weight:700;padding:3px 10px;border-radius:100px;border:1px solid rgba(255,107,53,0.22);}
.tb-btn{background:var(--card);border:1px solid var(--border);color:var(--text);padding:6px 12px;border-radius:8px;cursor:pointer;font-size:.74rem;font-weight:600;font-family:inherit;transition:.2s;text-decoration:none;display:inline-flex;align-items:center;gap:4px;white-space:nowrap;}
.tb-btn:hover{border-color:var(--p);color:var(--p);}
.theme-btn{padding:6px 12px;border-radius:100px;background:var(--card);border:1px solid var(--border);color:var(--text);cursor:pointer;font-size:.74rem;font-weight:600;font-family:inherit;transition:.2s;white-space:nowrap;}

.content{padding:20px;max-width:1400px;width:100%;}

/* ── STATS ── */
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(175px,1fr));gap:12px;margin-bottom:22px;}
.sc{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:18px 16px;cursor:pointer;transition:.3s;position:relative;overflow:hidden;}
.sc::after{content:'';position:absolute;top:-12px;right:-12px;width:64px;height:64px;background:radial-gradient(circle,rgba(255,107,53,0.1) 0%,transparent 70%);border-radius:50%;}
.sc:hover{border-color:rgba(255,107,53,0.3);transform:translateY(-3px);box-shadow:0 10px 32px rgba(0,0,0,0.3);}
.sc-icon{font-size:1.5rem;margin-bottom:8px;}
.sc-val{font-family:'Playfair Display',serif;font-size:1.75rem;font-weight:900;color:var(--p);}
.sc-lbl{font-size:.66rem;color:rgba(128,128,128,0.5);font-weight:500;margin-top:2px;text-transform:uppercase;letter-spacing:.9px;}
.sc-sub{font-size:.65rem;color:rgba(255,107,53,0.55);margin-top:3px;}

/* ── SECTIONS ── */
.sec{display:none;animation:secIn .3s ease;}
.sec.act{display:block;}
@keyframes secIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
.sec-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:8px;}
.sec-hdr h2{font-family:'Playfair Display',serif;font-size:1.25rem;font-weight:900;}

/* ── TABLE ── */
.tw{background:var(--card);border:1px solid var(--border);border-radius:var(--r);overflow:hidden;overflow-x:auto;margin-bottom:16px;}
table{width:100%;border-collapse:collapse;min-width:560px;}
th{padding:10px 14px;text-align:left;font-size:.62rem;text-transform:uppercase;letter-spacing:.9px;color:rgba(128,128,128,0.4);font-weight:700;background:rgba(255,255,255,0.015);border-bottom:1px solid var(--border);}
td{padding:10px 14px;font-size:.8rem;border-bottom:1px solid rgba(255,255,255,0.025);vertical-align:middle;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:rgba(255,255,255,0.012);}
.thumb{width:42px;height:42px;border-radius:8px;object-fit:cover;flex-shrink:0;}
.mono{font-family:monospace;}
.badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:100px;font-size:.66rem;font-weight:700;}
.b-pending{background:rgba(245,158,11,0.12);color:var(--warn);}
.b-verified{background:rgba(34,197,94,0.12);color:var(--ok);}
.b-rejected{background:rgba(239,68,68,0.12);color:var(--err);}
.b-active{background:rgba(34,197,94,0.12);color:var(--ok);}
.b-inactive{background:rgba(100,100,100,0.12);color:rgba(128,128,128,0.4);}
.b-info{background:rgba(99,179,237,0.12);color:#63b3ed;}
.truncate{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:160px;display:block;}

/* ── BUTTONS ── */
.btn{display:inline-flex;align-items:center;gap:5px;padding:8px 15px;border-radius:9px;font-size:.78rem;font-weight:600;cursor:pointer;border:none;transition:.2s;font-family:inherit;}
.btn:hover{opacity:.9;transform:translateY(-1px);}
.btn-primary{background:linear-gradient(135deg,var(--p),#ff8c00);color:#fff;}
.btn-danger{background:rgba(239,68,68,0.09);color:var(--err);border:1px solid rgba(239,68,68,0.22);}
.btn-danger:hover{background:var(--err);color:#fff;}
.btn-ghost{background:var(--card);color:var(--text);border:1px solid var(--border);}
.btn-ghost:hover{border-color:var(--p);color:var(--p);}
.btn-sm{padding:5px 10px;font-size:.7rem;border-radius:7px;}
.btn-success{background:rgba(34,197,94,0.09);color:var(--ok);border:1px solid rgba(34,197,94,0.22);}

/* ── FILTER TABS ── */
.ftabs{display:flex;gap:7px;margin-bottom:16px;flex-wrap:wrap;}
.ftab{padding:7px 16px;border-radius:100px;border:1px solid var(--border);background:var(--card);font-size:.76rem;font-weight:600;cursor:pointer;transition:.2s;font-family:inherit;}
.ftab.act,.ftab:hover{background:var(--p);border-color:var(--p);color:#fff;}

/* ── MODAL ── */
.mo{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.84);z-index:9000;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(6px);}
.mo.open{display:flex;}
.mc{background:var(--sb);border:1px solid var(--border);border-radius:20px;width:min(620px,100%);max-height:90vh;overflow-y:auto;animation:popIn .28s ease;}
@keyframes popIn{from{opacity:0;transform:scale(.93)}to{opacity:1;transform:scale(1)}}
.mh{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--sb);z-index:2;}
.mh-title{font-weight:700;font-size:.92rem;}
.mc-x{background:none;border:none;color:rgba(128,128,128,0.4);font-size:1.2rem;cursor:pointer;transition:.2s;}
.mc-x:hover{color:var(--err);}
.mb{padding:20px 24px;}
.mf{padding:12px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:8px;position:sticky;bottom:0;background:var(--sb);}

/* ── FORMS ── */
.g2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.fr{margin-bottom:13px;}
.fl{display:block;font-size:.66rem;font-weight:700;color:rgba(128,128,128,0.45);margin-bottom:4px;text-transform:uppercase;letter-spacing:.8px;}
.fc{width:100%;background:var(--card);border:1px solid var(--border);border-radius:9px;padding:10px 12px;color:var(--text);font-size:.84rem;font-family:inherit;outline:none;transition:.3s;}
.fc:focus{border-color:var(--p);box-shadow:0 0 0 3px rgba(255,107,53,0.08);}
.fc::placeholder{color:rgba(128,128,128,0.2);}
textarea.fc{resize:vertical;min-height:76px;line-height:1.5;}
select.fc{cursor:pointer;}
.fc option{background:#1a1a2e;}
.cbw{display:flex;align-items:center;gap:7px;cursor:pointer;font-size:.82rem;color:rgba(128,128,128,0.65);}
.cbw input{width:14px;height:14px;accent-color:var(--p);}
.cr{display:flex;align-items:center;gap:9px;}
.cs{width:32px;height:32px;border-radius:7px;border:1px solid var(--border);cursor:pointer;flex-shrink:0;}

/* ── SETTINGS TABS ── */
.stabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:18px;}
.stab{padding:6px 15px;border-radius:100px;border:1px solid var(--border);background:var(--card);font-size:.74rem;font-weight:600;cursor:pointer;transition:.2s;font-family:inherit;}
.stab.act,.stab:hover{background:var(--p);border-color:var(--p);color:#fff;}
.sp{display:none;}
.sp.act{display:block;}

/* ── PAGINATION ── */
.pgn{display:flex;gap:5px;align-items:center;flex-wrap:wrap;margin-top:14px;}
.pgn-btn{padding:5px 11px;border-radius:7px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:.73rem;cursor:pointer;text-decoration:none;transition:.2s;}
.pgn-btn:hover,.pgn-btn.act{background:var(--p);border-color:var(--p);color:#fff;}

/* ── SEARCH ── */
.sinp{background:var(--card);border:1px solid var(--border);border-radius:9px;padding:8px 12px;color:var(--text);font-size:.8rem;font-family:inherit;outline:none;width:190px;transition:.3s;}
.sinp:focus{border-color:var(--p);}

/* ── TOAST ── */
.toasts{position:fixed;bottom:20px;right:18px;z-index:99999;display:flex;flex-direction:column;gap:8px;max-width:calc(100vw - 36px);}
.tm{background:var(--sb);border:1px solid var(--border);border-radius:11px;padding:11px 15px;font-size:.81rem;font-weight:500;display:flex;align-items:center;gap:8px;animation:slR .3s ease;box-shadow:0 8px 26px rgba(0,0,0,0.4);min-width:220px;}
.tm.ok{border-left:3px solid var(--ok);}
.tm.err{border-left:3px solid var(--err);}
@keyframes slR{from{opacity:0;transform:translateX(26px)}to{opacity:1;transform:translateX(0)}}

/* ── RESPONSIVE ── */
@media(max-width:768px){
  .sidebar{position:fixed;left:0;top:0;bottom:0;transform:translateX(-100%);z-index:300;}
  .sidebar.open{transform:translateX(0);}
  .hamburger{display:flex;}
  .g2{grid-template-columns:1fr;}
  .stat-grid{grid-template-columns:repeat(2,1fr);}
  .content{padding:12px;}
  .topbar{padding:0 12px;}
}
@media(max-width:380px){.stat-grid{grid-template-columns:1fr;}}
</style>
</head>
<body id="abody" class="day">

<?php if (!isAdminLoggedIn()): ?>
<!-- ══ LOGIN ══ -->
<div class="login-wrap">
  <div class="login-box">
    <div class="login-logo">📸 <?= e($s['site_name']??'Photo Seller') ?></div>
    <div class="login-sub">Admin Control Panel</div>
    <?php if (isset($loginError)): ?><div class="login-err">❌ <?= e($loginError) ?></div><?php endif; ?>
    <form method="post">
      <div class="fg"><label class="fl">Username</label><input type="text" name="username" class="fi" placeholder="admin" required autofocus autocomplete="username"></div>
      <div class="fg"><label class="fl">Password</label><input type="password" name="password" class="fi" placeholder="••••••••" required autocomplete="current-password"></div>
      <button type="submit" name="login" class="login-btn">🔐 Login to Admin Panel</button>
    </form>
    <p style="margin-top:18px;font-size:.7rem;color:rgba(128,128,128,0.25)">Unauthorized access is prohibited</p>
  </div>
</div>

<?php else: ?>
<!-- ══ FULL ADMIN PANEL ══ -->
<div class="sb-overlay" id="sb-overlay" onclick="closeSB()"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sb-logo">
    <span>📸 <?= e($s['site_name']??'Photo Seller') ?></span>
    <p>Admin Panel</p>
  </div>
  <ul class="sb-nav">
    <li class="nav-sec">Overview</li>
    <li class="nav-item"><button onclick="goSec('dashboard')" id="nb-dashboard" class="act"><span class="ni-icon">📊</span> Dashboard</button></li>
    <li class="nav-sec">Content</li>
    <li class="nav-item"><button onclick="goSec('photos')" id="nb-photos"><span class="ni-icon">🖼️</span> Photos</button></li>
    <li class="nav-item"><button onclick="goSec('carousel')" id="nb-carousel"><span class="ni-icon">🎠</span> Carousel</button></li>
    <li class="nav-item"><button onclick="goSec('reviews')" id="nb-reviews"><span class="ni-icon">⭐</span> Reviews</button></li>
    <li class="nav-item"><button onclick="goSec('notifications')" id="nb-notifications"><span class="ni-icon">🔔</span> Notifications</button></li>
    <li class="nav-sec">Commerce</li>
    <li class="nav-item"><button onclick="goSec('purchases')" id="nb-purchases"><span class="ni-icon">💰</span> Purchases <span class="ni-pip"><?= $pendingPurch ?></span></button></li>
    <li class="nav-item"><button onclick="goSec('analytics')" id="nb-analytics"><span class="ni-icon">📈</span> Analytics</button></li>
    <li class="nav-item"><button onclick="goSec('visitors')" id="nb-visitors"><span class="ni-icon">👥</span> Visitors</button></li>
    <li class="nav-sec">Config</li>
    <li class="nav-item"><button onclick="goSec('settings')" id="nb-settings"><span class="ni-icon">⚙️</span> Settings</button></li>
    <li class="nav-item"><a href="index.php" target="_blank"><span class="ni-icon">🌐</span> View Website</a></li>
  </ul>
  <div class="sb-foot"><a href="admin.php?logout=1">🚪 Logout</a></div>
</aside>

<div class="main">
  <!-- TOPBAR -->
  <div class="topbar">
    <div class="tb-left">
      <button class="hamburger" onclick="toggleSB()" aria-label="Menu">☰</button>
      <span class="tb-title" id="tb-title">📊 Dashboard</span>
    </div>
    <div class="tb-right">
      <button class="theme-btn" onclick="toggleTheme()" id="theme-btn">☀️ Day</button>
      <span class="tb-badge">👤 <?= e($_SESSION['admin_user']??'Admin') ?></span>
      <a href="index.php" target="_blank" class="tb-btn">🌐 Site</a>
      <a href="admin.php?logout=1" class="tb-btn">🚪 Logout</a>
    </div>
  </div>

  <div class="content">

  <!-- ══════ DASHBOARD ══════ -->
  <div class="sec act" id="sec-dashboard">
    <div class="stat-grid">
      <div class="sc" onclick="goSec('photos')"><div class="sc-icon">🖼️</div><div class="sc-val"><?= $totalPhotos ?></div><div class="sc-lbl">Active Photos</div></div>
      <div class="sc" onclick="goSec('purchases')"><div class="sc-icon">💰</div><div class="sc-val"><?= $totalPurchases ?></div><div class="sc-lbl">Total Orders</div></div>
      <div class="sc" onclick="goSec('purchases')"><div class="sc-icon">⏳</div><div class="sc-val" style="color:var(--warn)"><?= $pendingPurch ?></div><div class="sc-lbl">Pending Verify</div></div>
      <div class="sc" onclick="goSec('purchases')"><div class="sc-icon">₹</div><div class="sc-val">₹<?= number_format($verifiedRevenue,0) ?></div><div class="sc-lbl">Verified Revenue</div><div class="sc-sub">Click for breakdown</div></div>
      <div class="sc" onclick="goSec('visitors')"><div class="sc-icon">👥</div><div class="sc-val"><?= number_format($totalVisitors) ?></div><div class="sc-lbl">Unique Visitors</div><div class="sc-sub">Click for details</div></div>
      <div class="sc" onclick="goSec('analytics')"><div class="sc-icon">👁️</div><div class="sc-val"><?= number_format($totalViews) ?></div><div class="sc-lbl">Total Views</div></div>
      <div class="sc"><div class="sc-icon">✅</div><div class="sc-val" style="color:var(--ok)"><?= $utrSubmitted ?></div><div class="sc-lbl">UTR Submitted</div></div>
      <div class="sc"><div class="sc-icon">❌</div><div class="sc-val" style="color:var(--err)"><?= $utrNotSubmitted ?></div><div class="sc-lbl">UTR Not Submitted</div></div>
    </div>

    <div class="sec-hdr"><h2>🕐 Recent Orders</h2><button class="btn btn-ghost btn-sm" onclick="goSec('purchases')">View All →</button></div>
    <div class="tw">
      <table>
        <thead><tr><th>Order ID</th><th>Photo</th><th>Amount</th><th>UTR</th><th>Method</th><th>Status</th><th>Time</th><th>Date</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($purchases,0,8) as $pu): ?>
        <tr>
          <td class="mono" style="font-size:.68rem"><?= e($pu['order_id']) ?></td>
          <td><span class="truncate" style="max-width:110px"><?= e($pu['photo_title']) ?></span></td>
          <td style="color:var(--p);font-weight:700">₹<?= number_format($pu['amount'],2) ?></td>
          <td class="mono" style="color:var(--a);font-size:.76rem"><?= e($pu['utr_number'] ?: '—') ?></td>
          <td style="font-size:.76rem"><?= e($pu['payment_method'] ?: '—') ?></td>
          <td><span class="badge b-<?= e($pu['payment_status']) ?>"><?= ucfirst(e($pu['payment_status'])) ?></span></td>
          <td style="font-size:.7rem;color:rgba(128,128,128,0.45)"><?= $pu['time_taken_seconds'] ? $pu['time_taken_seconds'].'s' : '—' ?></td>
          <td style="font-size:.68rem;color:rgba(128,128,128,0.4)"><?= date('d M H:i',strtotime($pu['created_at'])) ?></td>
          <td><button class="btn btn-ghost btn-sm" onclick='openPurchase(<?= htmlspecialchars(json_encode($pu),ENT_QUOTES) ?>)'>Edit</button></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Revenue breakdown -->
    <div class="sec-hdr" style="margin-top:20px"><h2>💵 Verified Revenue — Who Paid</h2></div>
    <div class="tw">
      <table>
        <thead><tr><th>Order ID</th><th>Photo</th><th>Amount</th><th>UTR</th><th>IP</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($recentRevUsers as $ru): ?>
        <tr>
          <td class="mono" style="font-size:.68rem"><?= e($ru['order_id']) ?></td>
          <td style="font-size:.78rem"><?= e($ru['photo_title']) ?></td>
          <td style="color:var(--ok);font-weight:700">₹<?= number_format($ru['amount'],2) ?></td>
          <td class="mono" style="color:var(--a);font-size:.76rem"><?= e($ru['utr_number']) ?></td>
          <td class="mono" style="font-size:.7rem;color:rgba(128,128,128,0.5)"><?= e($ru['customer_ip']) ?></td>
          <td style="font-size:.68rem;color:rgba(128,128,128,0.4)"><?= date('d M Y',strtotime($ru['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ══════ PHOTOS ══════ -->
  <div class="sec" id="sec-photos">
    <div class="sec-hdr">
      <h2>🖼️ Photos (<?= count($photos) ?>)</h2>
      <div style="display:flex;gap:7px;flex-wrap:wrap;align-items:center">
        <input type="text" class="sinp" placeholder="Search photos..." oninput="filterTbl('ptbl',this.value)">
        <button class="btn btn-primary" onclick="openPhotoMo(null)">+ Add Photo</button>
      </div>
    </div>
    <div class="tw">
      <table id="ptbl">
        <thead><tr><th></th><th>Title / Slug</th><th>Country</th><th>Type</th><th>Price</th><th>Actual</th><th>Status</th><th>★</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($photos as $ph): ?>
        <tr data-s="<?= strtolower(e($ph['title'].' '.$ph['country'].' '.$ph['type'])) ?>">
          <td><img src="<?= e($ph['thumbnail_url']?:$ph['image_url']) ?>" class="thumb" onerror="this.src='https://placehold.co/42x42/111/FF6B35?text=P'" alt=""></td>
          <td><div style="font-weight:600;font-size:.82rem;margin-bottom:2px"><?= e($ph['title']) ?></div><div class="mono" style="color:rgba(128,128,128,0.33);font-size:.66rem">/<?= e($ph['slug']) ?></div></td>
          <td style="font-size:.78rem"><?= e($ph['country']) ?></td>
          <td style="font-size:.78rem"><?= e($ph['type']) ?></td>
          <td style="color:var(--p);font-weight:700">₹<?= number_format($ph['price'],2) ?></td>
          <td style="text-decoration:line-through;color:rgba(128,128,128,0.33);font-size:.76rem">₹<?= number_format($ph['actual_price'],2) ?></td>
          <td><span class="badge b-<?= e($ph['status']) ?>"><?= ucfirst(e($ph['status'])) ?></span></td>
          <td><?= $ph['featured']?'⭐':'—' ?></td>
          <td style="display:flex;gap:4px;flex-wrap:wrap">
            <button class="btn btn-ghost btn-sm" onclick='openPhotoMo(<?= htmlspecialchars(json_encode($ph),ENT_QUOTES) ?>)'>✏️</button>
            <button class="btn btn-danger btn-sm" onclick="delPhoto(<?= $ph['id'] ?>)">🗑</button>
            <a href="index.php?photo=<?= e($ph['slug']) ?>" target="_blank" class="btn btn-ghost btn-sm">👁</a>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ══════ CAROUSEL ══════ -->
  <div class="sec" id="sec-carousel">
    <div class="sec-hdr">
      <h2>🎠 Carousel Slides <span style="font-size:.72rem;color:rgba(128,128,128,0.4)">(<?= count($slides) ?>/15)</span></h2>
      <button class="btn btn-primary" onclick="openSlideMo(null)" <?= count($slides)>=15?'disabled title="Max 15 slides"':'' ?>>+ Add Slide</button>
    </div>
    <p style="font-size:.8rem;color:rgba(128,128,128,0.5);margin-bottom:14px">Auto-scrolls every 1.5s. Supports up to 15 images. Lower sort order = shown first.</p>
    <div class="tw">
      <table>
        <thead><tr><th>Preview</th><th>Caption</th><th>Sort</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($slides as $sl): ?>
        <tr>
          <td><img src="<?= e($sl['image_url']) ?>" style="width:88px;height:50px;object-fit:cover;border-radius:8px" onerror="this.src='https://placehold.co/88x50/111/FF6B35?text=Slide'" alt=""></td>
          <td style="font-size:.8rem;max-width:200px"><span class="truncate"><?= e($sl['caption']) ?></span></td>
          <td><?= (int)$sl['sort_order'] ?></td>
          <td><span class="badge b-<?= e($sl['status']) ?>"><?= ucfirst(e($sl['status'])) ?></span></td>
          <td style="display:flex;gap:4px">
            <button class="btn btn-ghost btn-sm" onclick='openSlideMo(<?= htmlspecialchars(json_encode($sl),ENT_QUOTES) ?>)'>✏️</button>
            <button class="btn btn-danger btn-sm" onclick="delSlide(<?= $sl['id'] ?>)">🗑</button>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ══════ PURCHASES ══════ -->
  <div class="sec" id="sec-purchases">
    <div class="sec-hdr"><h2>💰 Purchases</h2></div>
    <div class="ftabs">
      <button class="ftab act" onclick="showPF('all',this)">All (<?= count($purchases) ?>)</button>
      <button class="ftab" onclick="showPF('utr',this)">✅ UTR Submitted (<?= count($purchasesUTR) ?>)</button>
      <button class="ftab" onclick="showPF('noutr',this)">❌ No UTR (<?= count($sessionsNoUTR) ?>)</button>
    </div>

    <!-- ALL -->
    <div id="pf-all">
      <div class="tw"><table>
        <thead><tr><th>Order ID</th><th>Photo</th><th>₹</th><th>UTR</th><th>IP</th><th>Browser</th><th>Method</th><th>Status</th><th>Time</th><th>Date</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($purchases as $pu): $bi=json_decode($pu['browser_info']??'{}',true); ?>
        <tr>
          <td class="mono" style="font-size:.66rem"><?= e($pu['order_id']) ?></td>
          <td><span class="truncate" style="max-width:100px;font-size:.78rem"><?= e($pu['photo_title']) ?></span></td>
          <td style="color:var(--p);font-weight:700;font-size:.82rem">₹<?= number_format($pu['amount'],2) ?></td>
          <td class="mono" style="color:var(--a);font-size:.74rem"><?= e($pu['utr_number']?:'—') ?></td>
          <td class="mono" style="font-size:.68rem;color:rgba(128,128,128,0.55)"><?= e($pu['customer_ip']) ?></td>
          <td><span class="truncate" style="max-width:140px;font-size:.68rem;color:rgba(128,128,128,0.5)" title="<?= e($bi['user_agent']??$pu['device_info']??'') ?>"><?= e($bi['user_agent']??$pu['device_info']??'—') ?></span></td>
          <td style="font-size:.74rem"><?= e($pu['payment_method']?:'—') ?></td>
          <td><span class="badge b-<?= e($pu['payment_status']) ?>"><?= ucfirst(e($pu['payment_status'])) ?></span></td>
          <td style="font-size:.68rem;color:rgba(128,128,128,0.45)"><?= $pu['time_taken_seconds'] ? $pu['time_taken_seconds'].'s' : '—' ?></td>
          <td style="font-size:.66rem;color:rgba(128,128,128,0.38)"><?= date('d M H:i',strtotime($pu['created_at'])) ?></td>
          <td><button class="btn btn-ghost btn-sm" onclick='openPurchase(<?= htmlspecialchars(json_encode($pu),ENT_QUOTES) ?>)'>Manage</button></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>

    <!-- UTR SUBMITTED -->
    <div id="pf-utr" style="display:none">
      <div class="tw"><table>
        <thead><tr><th>Order ID</th><th>Photo</th><th>₹</th><th>UTR</th><th>IP</th><th>Browser Details</th><th>Status</th><th>Submitted At</th><th>Time Taken</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($purchasesUTR as $pu): $bi=json_decode($pu['browser_info']??'{}',true); ?>
        <tr>
          <td class="mono" style="font-size:.66rem"><?= e($pu['order_id']) ?></td>
          <td style="font-size:.78rem"><?= e($pu['photo_title']) ?></td>
          <td style="color:var(--p);font-weight:700">₹<?= number_format($pu['amount'],2) ?></td>
          <td class="mono" style="color:var(--ok);font-weight:700;font-size:.8rem"><?= e($pu['utr_number']) ?></td>
          <td class="mono" style="font-size:.68rem;color:rgba(128,128,128,0.55)"><?= e($pu['customer_ip']) ?></td>
          <td><span class="truncate" style="max-width:150px;font-size:.68rem;color:rgba(128,128,128,0.5)" title="<?= e($bi['user_agent']??'') ?>"><?= e($bi['user_agent']??'—') ?></span></td>
          <td><span class="badge b-<?= e($pu['payment_status']) ?>"><?= ucfirst(e($pu['payment_status'])) ?></span></td>
          <td style="font-size:.68rem;color:rgba(128,128,128,0.38)"><?= date('d M H:i',strtotime($pu['created_at'])) ?></td>
          <td style="font-size:.7rem;color:rgba(128,128,128,0.45)"><?= $pu['time_taken_seconds'] ? $pu['time_taken_seconds'].'s ('. ($pu['countdown_at_submit']??'?').'s left)' : '—' ?></td>
          <td><button class="btn btn-ghost btn-sm" onclick='openPurchase(<?= htmlspecialchars(json_encode($pu),ENT_QUOTES) ?>)'>Manage</button></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>

    <!-- NO UTR -->
    <div id="pf-noutr" style="display:none">
      <div class="tw"><table>
        <thead><tr><th>Order ID</th><th>Photo</th><th>₹</th><th>IP</th><th>Browser Details</th><th>Timed Out</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($sessionsNoUTR as $ps): $bi=json_decode($ps['browser_info']??'{}',true); ?>
        <tr>
          <td class="mono" style="font-size:.66rem"><?= e($ps['order_id']) ?></td>
          <td style="font-size:.78rem"><?= e($ps['photo_title']??'—') ?></td>
          <td style="color:var(--p);font-weight:700">₹<?= number_format($ps['amount'],2) ?></td>
          <td class="mono" style="font-size:.68rem;color:rgba(128,128,128,0.55)"><?= e($ps['customer_ip']) ?></td>
          <td><span class="truncate" style="max-width:150px;font-size:.68rem;color:rgba(128,128,128,0.5)" title="<?= e($bi['user_agent']??'') ?>"><?= e($bi['user_agent']??'—') ?></span></td>
          <td><?= $ps['timed_out'] ? '<span class="badge b-rejected">Yes</span>' : '<span class="badge b-pending">No</span>' ?></td>
          <td style="font-size:.66rem;color:rgba(128,128,128,0.38)"><?= date('d M H:i',strtotime($ps['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>

  <!-- ══════ ANALYTICS ══════ -->
  <div class="sec" id="sec-analytics">
    <div class="sec-hdr"><h2>📈 Photo Analytics</h2></div>
    <div class="tw"><table>
      <thead><tr><th>Photo</th><th>Slug</th><th>👁 Views</th><th>⚡ Purchase Clicks</th><th>🛒 Cart Adds</th></tr></thead>
      <tbody>
      <?php foreach ($photoStats as $ps): ?>
      <tr>
        <td style="font-weight:600;font-size:.82rem"><?= e($ps['title']) ?></td>
        <td class="mono" style="color:rgba(128,128,128,0.35);font-size:.68rem"><?= e($ps['slug']) ?></td>
        <td style="color:var(--p);font-weight:700"><?= number_format($ps['views']) ?></td>
        <td style="color:var(--warn);font-weight:600"><?= number_format($ps['purchase_clicks']) ?></td>
        <td style="color:var(--ok);font-weight:600"><?= number_format($ps['cart_adds']) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>

  <!-- ══════ VISITORS ══════ -->
  <div class="sec" id="sec-visitors">
    <div class="sec-hdr"><h2>👥 Visitor Logs <span style="font-size:.72rem;color:rgba(128,128,128,0.4)">(<?= number_format($vTotal) ?> total · 50/page)</span></h2></div>
    <div class="tw"><table>
      <thead><tr><th>IP</th><th>Browser</th><th>OS</th><th>Screen</th><th>Referrer</th><th>Date</th></tr></thead>
      <tbody>
      <?php foreach ($visitors as $v): ?>
      <tr>
        <td class="mono" style="font-size:.76rem"><?= e($v['ip_address'] ?? 'Unknown') ?></td>
        <td style="font-size:.76rem"><?= e($v['browser_name'] ?? 'Unknown') ?></td>
        <td style="font-size:.76rem"><?= e($v['os_name'] ?? 'Unknown') ?></td>
        <td style="font-size:.7rem;color:rgba(128,128,128,0.45)"><?= (!empty($v['screen_width']) && !empty($v['screen_height'])) ? $v['screen_width'].'×'.$v['screen_height'] : '—' ?></td>
        <td><span class="truncate" style="max-width:120px;font-size:.7rem;color:rgba(128,128,128,0.45)" title="<?= e($v['referrer'] ?? '') ?>"><?= e(!empty($v['referrer']) ? $v['referrer'] : 'Direct') ?></span></td>
        <td style="font-size:.68rem;color:rgba(128,128,128,0.38)"><?= date('d M Y H:i', strtotime($v['visited_at'] ?? 'now')) ?></td>
      </tr>

      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php if ($vPages > 1): ?>
    <div class="pgn">
      <?php if ($vPage>1): ?><a href="?vpage=<?= $vPage-1 ?>" class="pgn-btn" onclick="goSec('visitors')">← Prev</a><?php endif; ?>
      <?php for ($pg=max(1,$vPage-2);$pg<=min($vPages,$vPage+2);$pg++): ?>
      <a href="?vpage=<?= $pg ?>" class="pgn-btn <?= $pg===$vPage?'act':'' ?>"><?= $pg ?></a>
      <?php endfor; ?>
      <?php if ($vPage<$vPages): ?><a href="?vpage=<?= $vPage+1 ?>" class="pgn-btn">Next →</a><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- ══════ REVIEWS ══════ -->
  <div class="sec" id="sec-reviews">
    <div class="sec-hdr"><h2>⭐ Reviews (<?= count($reviews) ?>)</h2><button class="btn btn-primary" onclick="openReviewMo(null)">+ Add Review</button></div>
    <div class="tw"><table>
      <thead><tr><th>Photo</th><th>Reviewer</th><th>Rating</th><th>Comment</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($reviews as $rv): ?>
      <tr>
        <td style="font-size:.78rem"><?= e($rv['photo_title']) ?></td>
        <td style="font-size:.8rem;font-weight:600"><?= e($rv['reviewer_name']) ?></td>
        <td style="color:var(--a)"><?= str_repeat('★',(int)$rv['rating']) ?></td>
        <td><span class="truncate" style="max-width:160px;font-size:.78rem"><?= e($rv['comment']) ?></span></td>
        <td><span class="badge <?= $rv['status']==='approved'?'b-verified':($rv['status']==='rejected'?'b-rejected':'b-pending') ?>"><?= ucfirst(e($rv['status'])) ?></span></td>
        <td style="font-size:.68rem;color:rgba(128,128,128,0.38)"><?= date('d M Y',strtotime($rv['created_at'])) ?></td>
        <td style="display:flex;gap:4px">
          <button class="btn btn-ghost btn-sm" onclick='openReviewMo(<?= htmlspecialchars(json_encode($rv),ENT_QUOTES) ?>)'>✏️</button>
          <button class="btn btn-danger btn-sm" onclick="delReview(<?= $rv['id'] ?>)">🗑</button>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>

  <!-- ══════ NOTIFICATIONS ══════ -->
  <div class="sec" id="sec-notifications">
    <div class="sec-hdr"><h2>🔔 Notifications</h2><button class="btn btn-primary" onclick="openNotifMo()">+ Add</button></div>
    <div class="tw"><table>
      <thead><tr><th>Title</th><th>Message</th><th>Type</th><th>Read</th><th>Date</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($notifications as $n): ?>
      <tr>
        <td style="font-weight:600;font-size:.82rem"><?= e($n['title']) ?></td>
        <td><span class="truncate" style="max-width:180px;font-size:.78rem"><?= e($n['message']) ?></span></td>
        <td><span class="badge b-info"><?= e($n['type']) ?></span></td>
        <td><?= $n['is_read']?'✅':'🔵 Unread' ?></td>
        <td style="font-size:.68rem;color:rgba(128,128,128,0.38)"><?= date('d M H:i',strtotime($n['created_at'])) ?></td>
        <td><button class="btn btn-danger btn-sm" onclick="delNotif(<?= $n['id'] ?>)">🗑</button></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>

  <!-- ══════ SETTINGS ══════ -->
  <div class="sec" id="sec-settings">
    <div class="sec-hdr"><h2>⚙️ Settings</h2></div>
    <div class="stabs">
      <button class="stab act" onclick="showStab('general',this)">🏠 General</button>
      <button class="stab" onclick="showStab('payment',this)">💳 Payment</button>
      <button class="stab" onclick="showStab('social',this)">📱 Social</button>
      <button class="stab" onclick="showStab('colors',this)">🎨 Colors</button>
      <button class="stab" onclick="showStab('faq',this)">❓ FAQs</button>
      <button class="stab" onclick="showStab('policy',this)">📜 Policy</button>
      <button class="stab" onclick="showStab('security',this)">🔐 Admin</button>
    </div>
    <form id="sf">
    <!-- General -->
    <div class="sp act" id="st-general">
      <div class="g2">
        <div class="fr"><label class="fl">Site Name</label><input class="fc" name="site_name" value="<?= e($s['site_name']??'') ?>"></div>
        <div class="fr"><label class="fl">Site Title (SEO)</label><input class="fc" name="site_title" value="<?= e($s['site_title']??'') ?>"></div>
        <div class="fr"><label class="fl">Copyright Name</label><input class="fc" name="copyright_name" value="<?= e($s['copyright_name']??'') ?>"></div>
        <div class="fr"><label class="fl">Copyright Year</label><input class="fc" name="copyright_year" value="<?= e($s['copyright_year']??'2026') ?>"></div>
      </div>
      <div class="fr"><label class="fl">Meta Description (SEO)</label><textarea class="fc" name="meta_description"><?= e($s['meta_description']??'') ?></textarea></div>
      <div class="fr"><label class="fl">UTR Guide Image URL</label><input class="fc" name="guide_image_url" placeholder="https://..." value="<?= e($s['guide_image_url']??'') ?>"></div>
    </div>
    <!-- Payment -->
    <div class="sp" id="st-payment">
      <div class="g2">
        <div class="fr"><label class="fl">UPI ID</label><input class="fc" name="upi_id" value="<?= e($s['upi_id']??'') ?>"></div>
        <div class="fr"><label class="fl">Payee Name (UPI)</label><input class="fc" name="upi_name" value="<?= e($s['upi_name']??'') ?>"></div>
        <div class="fr"><label class="fl">Telegram URL</label><input class="fc" name="telegram_url" value="<?= e($s['telegram_url']??'') ?>"></div>
        <div class="fr"><label class="fl">Support Username</label><input class="fc" name="telegram_support_user" value="<?= e($s['telegram_support_user']??'') ?>"></div>
        <div class="fr"><label class="fl">Payment Timeout (seconds)</label><input class="fc" type="number" name="payment_timeout" value="<?= e($s['payment_timeout']??'300') ?>"></div>
      </div>
    </div>
    <!-- Social -->
    <div class="sp" id="st-social">
      <div class="g2">
        <div class="fr"><label class="fl">Instagram URL</label><input class="fc" name="instagram_url" value="<?= e($s['instagram_url']??'') ?>"></div>
        <div class="fr"><label class="fl">YouTube URL</label><input class="fc" name="youtube_url" value="<?= e($s['youtube_url']??'') ?>"></div>
        <div class="fr"><label class="fl">Facebook URL</label><input class="fc" name="facebook_url" value="<?= e($s['facebook_url']??'') ?>"></div>
        <div class="fr"><label class="fl">X (Twitter) URL</label><input class="fc" name="twitter_url" value="<?= e($s['twitter_url']??'') ?>"></div>
      </div>
    </div>
    <!-- Colors -->
    <div class="sp" id="st-colors">
      <?php $cf=['primary_color'=>['Primary','#FF6B35'],'secondary_color'=>['Secondary','#1A1A2E'],'accent_color'=>['Accent','#FFD700'],'bg_color'=>['Background','#0D0D1A'],'text_color'=>['Text Color','#FFFFFF']];
      foreach ($cf as $key=>[$label,$def]): ?>
      <div class="fr"><label class="fl"><?= $label ?></label>
        <div class="cr">
          <input type="color" class="cs" value="<?= e($s[$key]??$def) ?>" oninput="document.querySelector('[name=<?= $key ?>]').value=this.value">
          <input class="fc" name="<?= $key ?>" value="<?= e($s[$key]??$def) ?>" style="flex:1">
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <!-- FAQs -->
    <div class="sp" id="st-faq">
      <?php for ($i=1;$i<=6;$i++): ?>
      <div style="background:var(--card);border:1px solid var(--border);border-radius:11px;padding:14px;margin-bottom:11px">
        <div style="font-weight:700;font-size:.76rem;color:var(--p);margin-bottom:10px">FAQ <?= $i ?></div>
        <div class="fr"><label class="fl">Question</label><input class="fc" name="faq_<?= $i ?>_q" value="<?= e($s["faq_{$i}_q"]??'') ?>"></div>
        <div class="fr" style="margin-bottom:0"><label class="fl">Answer</label><textarea class="fc" name="faq_<?= $i ?>_a" style="min-height:58px"><?= e($s["faq_{$i}_a"]??'') ?></textarea></div>
      </div>
      <?php endfor; ?>
    </div>
    <!-- Policy -->
    <div class="sp" id="st-policy">
      <div class="fr"><label class="fl">Privacy Policy</label><textarea class="fc" name="privacy_policy" style="min-height:150px"><?= e($s['privacy_policy']??'') ?></textarea></div>
      <div class="fr"><label class="fl">Terms & Conditions</label><textarea class="fc" name="terms_conditions" style="min-height:150px"><?= e($s['terms_conditions']??'') ?></textarea></div>
    </div>
    <!-- Security -->
    <div class="sp" id="st-security">
      <div style="background:rgba(245,158,11,0.07);border:1px solid rgba(245,158,11,0.22);border-radius:10px;padding:11px 14px;font-size:.8rem;color:rgba(245,158,11,0.9);margin-bottom:16px">⚠️ Changing credentials will log you out. Remember the new values.</div>
      <div class="g2">
        <div class="fr"><label class="fl">Admin Username</label><input class="fc" name="admin_username" value="<?= e($s['admin_username']??'admin') ?>"></div>
        <div class="fr"><label class="fl">New Password</label><input type="password" class="fc" name="admin_password" placeholder="Leave blank to keep current" autocomplete="new-password"></div>
      </div>
    </div>
    <div style="margin-top:16px"><button type="button" class="btn btn-primary" onclick="saveSettings()" style="padding:12px 28px;font-size:.86rem">💾 Save All Settings</button></div>
    </form>
  </div>

  </div><!-- content -->
</div><!-- main -->

<!-- ══ PHOTO MODAL ══ -->
<div class="mo" id="mo-photo">
  <div class="mc">
    <div class="mh"><span class="mh-title" id="pm-h">Add Photo</span><button class="mc-x" onclick="closeMo('mo-photo')">✕</button></div>
    <div class="mb">
      <input type="hidden" id="pm-id">
      <div class="g2">
        <div class="fr"><label class="fl">Title *</label><input class="fc" id="pm-title" oninput="autoSlug()" placeholder="Photo title"></div>
        <div class="fr"><label class="fl">Slug</label><input class="fc" id="pm-slug" placeholder="auto-generated"></div>
      </div>
      <div class="fr"><label class="fl">Description</label><textarea class="fc" id="pm-desc"></textarea></div>
      <div class="g2">
        <div class="fr"><label class="fl">Price (₹) *</label><input class="fc" type="number" id="pm-price" step="0.01" min="0" placeholder="0.00"></div>
        <div class="fr"><label class="fl">Actual / MRP (₹)</label><input class="fc" type="number" id="pm-actual" step="0.01" min="0" placeholder="0.00"></div>
        <div class="fr"><label class="fl">Country</label><input class="fc" id="pm-country" placeholder="e.g. India, Japan"></div>
        <div class="fr"><label class="fl">Type / Style</label><input class="fc" id="pm-type" placeholder="e.g. Elegant, Premium"></div>
      </div>
      <div class="fr"><label class="fl">Image URL (Full Size)</label><input class="fc" id="pm-img" placeholder="https://..."></div>
      <div class="fr"><label class="fl">Thumbnail URL (optional)</label><input class="fc" id="pm-thumb" placeholder="https://... (uses image if empty)"></div>
      <div class="fr"><label class="fl">Tags (comma-separated)</label><input class="fc" id="pm-tags" placeholder="nature,sunset,landscape"></div>
      <div class="fr"><label class="fl">Meta Title (SEO)</label><input class="fc" id="pm-meta-title" placeholder="Leave blank to auto-generate"></div>
      <div class="fr"><label class="fl">Meta Description (SEO)</label><textarea class="fc" id="pm-meta-desc" style="min-height:60px"></textarea></div>
      <div style="display:flex;gap:20px;align-items:center;flex-wrap:wrap;margin-top:6px">
        <label class="cbw"><input type="checkbox" id="pm-featured"> ⭐ Featured</label>
        <div><label class="fl" style="margin-bottom:4px">Status</label>
          <select class="fc" id="pm-status" style="padding:8px 11px"><option value="active">Active</option><option value="inactive">Inactive</option></select>
        </div>
      </div>
    </div>
    <div class="mf"><button class="btn btn-ghost" onclick="closeMo('mo-photo')">Cancel</button><button class="btn btn-primary" onclick="savePhoto()">💾 Save Photo</button></div>
  </div>
</div>

<!-- ══ PURCHASE MODAL ══ -->
<div class="mo" id="mo-purchase">
  <div class="mc">
    <div class="mh"><span class="mh-title">💰 Manage Purchase</span><button class="mc-x" onclick="closeMo('mo-purchase')">✕</button></div>
    <div class="mb">
      <input type="hidden" id="pu-id">
      <div style="background:var(--card);border:1px solid var(--border);border-radius:11px;padding:14px;margin-bottom:16px;font-size:.8rem">
        <div class="g2" style="gap:10px">
          <div><div style="color:rgba(128,128,128,0.4);font-size:.62rem;margin-bottom:2px;text-transform:uppercase">Order ID</div><div id="pu-oid" class="mono" style="color:var(--a)"></div></div>
          <div><div style="color:rgba(128,128,128,0.4);font-size:.62rem;margin-bottom:2px;text-transform:uppercase">Amount</div><div id="pu-amt" style="color:var(--p);font-weight:700"></div></div>
          <div><div style="color:rgba(128,128,128,0.4);font-size:.62rem;margin-bottom:2px;text-transform:uppercase">UTR Number</div><div id="pu-utr" class="mono" style="color:var(--ok);font-weight:700"></div></div>
          <div><div style="color:rgba(128,128,128,0.4);font-size:.62rem;margin-bottom:2px;text-transform:uppercase">Method</div><div id="pu-meth"></div></div>
          <div><div style="color:rgba(128,128,128,0.4);font-size:.62rem;margin-bottom:2px;text-transform:uppercase">IP Address</div><div id="pu-ip" class="mono" style="font-size:.76rem"></div></div>
          <div><div style="color:rgba(128,128,128,0.4);font-size:.62rem;margin-bottom:2px;text-transform:uppercase">Photo</div><div id="pu-photo" style="font-size:.78rem"></div></div>
          <div><div style="color:rgba(128,128,128,0.4);font-size:.62rem;margin-bottom:2px;text-transform:uppercase">Time Taken</div><div id="pu-time" style="font-size:.76rem;color:rgba(128,128,128,0.55)"></div></div>
          <div><div style="color:rgba(128,128,128,0.4);font-size:.62rem;margin-bottom:2px;text-transform:uppercase">Date</div><div id="pu-date" style="font-size:.76rem;color:rgba(128,128,128,0.55)"></div></div>
        </div>
        <div style="margin-top:10px"><div style="color:rgba(128,128,128,0.4);font-size:.62rem;margin-bottom:3px;text-transform:uppercase">Browser / Device</div><div id="pu-dev" style="font-size:.68rem;color:rgba(128,128,128,0.48);word-break:break-all;line-height:1.6"></div></div>
      </div>
      <div class="fr"><label class="fl">Payment Status</label>
        <select class="fc" id="pu-status">
          <option value="pending">Pending</option>
          <option value="verified">Verified ✅</option>
          <option value="rejected">Rejected ❌</option>
        </select>
      </div>
      <div class="fr"><label class="fl">Admin Notes</label><textarea class="fc" id="pu-notes" placeholder="Internal notes..."></textarea></div>
    </div>
    <div class="mf"><button class="btn btn-ghost" onclick="closeMo('mo-purchase')">Cancel</button><button class="btn btn-primary" onclick="savePurchase()">💾 Update</button></div>
  </div>
</div>

<!-- ══ REVIEW MODAL ══ -->
<div class="mo" id="mo-review">
  <div class="mc" style="max-width:500px">
    <div class="mh"><span class="mh-title">⭐ Review</span><button class="mc-x" onclick="closeMo('mo-review')">✕</button></div>
    <div class="mb">
      <input type="hidden" id="rv-id">
      <div class="g2">
        <div class="fr"><label class="fl">Photo</label>
          <select class="fc" id="rv-photo">
            <?php foreach ($photos as $ph): ?><option value="<?= $ph['id'] ?>"><?= e($ph['title']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="fr"><label class="fl">Reviewer Name</label><input class="fc" id="rv-name" placeholder="Customer name"></div>
        <div class="fr"><label class="fl">Rating</label>
          <select class="fc" id="rv-rating">
            <option value="5">★★★★★ 5</option><option value="4">★★★★☆ 4</option>
            <option value="3">★★★☆☆ 3</option><option value="2">★★☆☆☆ 2</option><option value="1">★☆☆☆☆ 1</option>
          </select>
        </div>
        <div class="fr"><label class="fl">Status</label>
          <select class="fc" id="rv-status"><option value="approved">Approved</option><option value="pending">Pending</option><option value="rejected">Rejected</option></select>
        </div>
      </div>
      <div class="fr"><label class="fl">Comment</label><textarea class="fc" id="rv-comment" style="min-height:80px"></textarea></div>
    </div>
    <div class="mf"><button class="btn btn-ghost" onclick="closeMo('mo-review')">Cancel</button><button class="btn btn-primary" onclick="saveReview()">💾 Save</button></div>
  </div>
</div>

<!-- ══ NOTIFICATION MODAL ══ -->
<div class="mo" id="mo-notif">
  <div class="mc" style="max-width:460px">
    <div class="mh"><span class="mh-title">🔔 Add Notification</span><button class="mc-x" onclick="closeMo('mo-notif')">✕</button></div>
    <div class="mb">
      <div class="fr"><label class="fl">Title</label><input class="fc" id="nm-title" placeholder="Notification title"></div>
      <div class="fr"><label class="fl">Message</label><textarea class="fc" id="nm-msg" style="min-height:80px"></textarea></div>
      <div class="fr"><label class="fl">Type</label>
        <select class="fc" id="nm-type"><option value="info">Info</option><option value="success">Success</option><option value="warning">Warning</option><option value="error">Error</option></select>
      </div>
    </div>
    <div class="mf"><button class="btn btn-ghost" onclick="closeMo('mo-notif')">Cancel</button><button class="btn btn-primary" onclick="saveNotif()">📢 Send</button></div>
  </div>
</div>

<!-- ══ SLIDE MODAL ══ -->
<div class="mo" id="mo-slide">
  <div class="mc" style="max-width:500px">
    <div class="mh"><span class="mh-title" id="sl-h">Add Carousel Slide</span><button class="mc-x" onclick="closeMo('mo-slide')">✕</button></div>
    <div class="mb">
      <input type="hidden" id="sl-id">
      <div class="fr"><label class="fl">Image URL *</label><input class="fc" id="sl-img" placeholder="https://images.unsplash.com/..."></div>
      <div class="fr"><label class="fl">Caption / Text</label><input class="fc" id="sl-caption" placeholder="✨ Amazing photos — Buy Now!"></div>
      <div class="g2">
        <div class="fr"><label class="fl">Sort Order</label><input class="fc" type="number" id="sl-order" placeholder="1" min="0"></div>
        <div class="fr"><label class="fl">Status</label>
          <select class="fc" id="sl-status"><option value="active">Active</option><option value="inactive">Inactive</option></select>
        </div>
      </div>
      <div id="sl-preview" style="margin-top:8px;display:none">
        <img id="sl-prev-img" src="" style="width:100%;max-height:180px;object-fit:cover;border-radius:10px" alt="">
      </div>
    </div>
    <div class="mf"><button class="btn btn-ghost" onclick="closeMo('mo-slide')">Cancel</button><button class="btn btn-primary" onclick="saveSlide()">💾 Save Slide</button></div>
  </div>
</div>

<div class="toasts" id="toasts"></div>
<?php endif; ?>

<script>
// ── Theme ─────────────────────────────────────────────
// ── Theme ─────────────────────────────────────────────
let isDark = false; // Changed default to Day Mode
function toggleTheme(){
  isDark = !isDark;
  document.getElementById('abody').className = isDark ? '' : 'day';
  const btn = document.getElementById('theme-btn');
  if(btn) btn.textContent = isDark ? '🌙 Night' : '☀️ Day';
  localStorage.setItem('ps_admin_theme', isDark ? 'dark' : 'day');
}
(function(){
  const t = localStorage.getItem('ps_admin_theme');
  // Only switch to dark if the user specifically saved it before
  if(t === 'dark'){
    isDark = true;
    const b = document.getElementById('abody');
    if(b) b.className = '';
    const btn = document.getElementById('theme-btn');
    if(btn) btn.textContent = '🌙 Night';
  }
})();


// ── Sidebar mobile ────────────────────────────────────
function toggleSB(){
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sb-overlay').classList.toggle('open');
}
function closeSB(){
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sb-overlay').classList.remove('open');
}

// ── Section nav ───────────────────────────────────────
const secs=['dashboard','photos','carousel','purchases','analytics','visitors','reviews','notifications','settings'];
const secTitles={
  dashboard:'📊 Dashboard',photos:'🖼️ Photos',carousel:'🎠 Carousel',
  purchases:'💰 Purchases',analytics:'📈 Analytics',visitors:'👥 Visitors',
  reviews:'⭐ Reviews',notifications:'🔔 Notifications',settings:'⚙️ Settings'
};
function goSec(id){
  secs.forEach(s=>{
    const el=document.getElementById('sec-'+s);
    const nb=document.getElementById('nb-'+s);
    if(el) el.classList.toggle('act',s===id);
    if(nb) nb.classList.toggle('act',s===id);
  });
  const tt=document.getElementById('tb-title');
  if(tt) tt.textContent=secTitles[id]||'';
  closeSB();
  window.scrollTo({top:0,behavior:'smooth'});
}

// ── Settings tabs ─────────────────────────────────────
function showStab(id,btn){
  document.querySelectorAll('.sp').forEach(p=>p.classList.remove('act'));
  document.querySelectorAll('.stab').forEach(b=>b.classList.remove('act'));
  const el=document.getElementById('st-'+id);
  if(el) el.classList.add('act');
  if(btn) btn.classList.add('act');
}

// ── Purchase filter ───────────────────────────────────
function showPF(id,btn){
  ['all','utr','noutr'].forEach(k=>{
    const el=document.getElementById('pf-'+k);
    if(el) el.style.display=k===id?'block':'none';
  });
  document.querySelectorAll('.ftab').forEach(b=>b.classList.remove('act'));
  if(btn) btn.classList.add('act');
}

// ── Modal helpers ─────────────────────────────────────
function openMo(id){document.getElementById(id).classList.add('open');}
function closeMo(id){document.getElementById(id).classList.remove('open');}
document.querySelectorAll('.mo').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('open');}));

// ── Toast ─────────────────────────────────────────────
function toast(msg,type='ok'){
  const c=document.getElementById('toasts');
  const t=document.createElement('div');
  t.className='tm '+type;
  t.textContent=(type==='ok'?'✅ ':'❌ ')+msg;
  c.appendChild(t);
  setTimeout(()=>t.remove(),3500);
}

// ── POST helper ───────────────────────────────────────
function post(fd,cb){
  fetch('admin.php',{method:'POST',body:fd})
    .then(r=>r.json())
    .then(d=>{if(d.success){cb(d);}else{toast(d.msg||'Error','err');}})
    .catch(()=>toast('Network error','err'));
}

// ── Table search ──────────────────────────────────────
function filterTbl(id,q){
  q=q.toLowerCase();
  document.querySelectorAll('#'+id+' tbody tr').forEach(tr=>{
    tr.style.display=(tr.dataset.s||'').includes(q)?'':'none';
  });
}

// ── PHOTO MODAL ───────────────────────────────────────
function openPhotoMo(ph){
  const ids=['pm-id','pm-title','pm-slug','pm-desc','pm-price','pm-actual','pm-country','pm-type','pm-img','pm-thumb','pm-tags','pm-meta-title','pm-meta-desc'];
  ids.forEach(id=>{const el=document.getElementById(id);if(el)el.value='';});
  document.getElementById('pm-featured').checked=false;
  document.getElementById('pm-status').value='active';
  document.getElementById('pm-h').textContent= ph?'Edit Photo':'Add Photo';
  if(ph){
    document.getElementById('pm-id').value=ph.id||'';
    document.getElementById('pm-title').value=ph.title||'';
    document.getElementById('pm-slug').value=ph.slug||'';
    document.getElementById('pm-desc').value=ph.description||'';
    document.getElementById('pm-price').value=ph.price||'';
    document.getElementById('pm-actual').value=ph.actual_price||'';
    document.getElementById('pm-country').value=ph.country||'';
    document.getElementById('pm-type').value=ph.type||'';
    document.getElementById('pm-img').value=ph.image_url||'';
    document.getElementById('pm-thumb').value=ph.thumbnail_url||'';
    document.getElementById('pm-tags').value=ph.tags||'';
    document.getElementById('pm-meta-title').value=ph.meta_title||'';
    document.getElementById('pm-meta-desc').value=ph.meta_description||'';
    document.getElementById('pm-featured').checked=ph.featured=='1';
    document.getElementById('pm-status').value=ph.status||'active';
  }
  openMo('mo-photo');
}
function autoSlug(){
  const v=document.getElementById('pm-title').value;
  document.getElementById('pm-slug').value=v.toLowerCase().trim().replace(/[^a-z0-9\s-]/g,'').replace(/[\s-]+/g,'-').replace(/^-|-$/g,'');
}
function savePhoto(){
  const fd=new FormData();
  fd.append('action','save_photo');
  fd.append('photo_id',document.getElementById('pm-id').value||0);
  [['title','pm-title'],['slug','pm-slug'],['description','pm-desc'],['price','pm-price'],['actual_price','pm-actual'],
   ['country','pm-country'],['type','pm-type'],['image_url','pm-img'],['thumbnail_url','pm-thumb'],
   ['tags','pm-tags'],['meta_title','pm-meta-title'],['meta_description','pm-meta-desc'],['status','pm-status']
  ].forEach(([k,id])=>fd.append(k,document.getElementById(id).value));
  if(document.getElementById('pm-featured').checked) fd.append('featured','1');
  post(fd,()=>{closeMo('mo-photo');toast('Photo saved!');setTimeout(()=>location.reload(),1200);});
}
function delPhoto(id){
  if(!confirm('Delete this photo? This cannot be undone.')) return;
  const fd=new FormData(); fd.append('action','delete_photo'); fd.append('photo_id',id);
  post(fd,()=>{toast('Photo deleted!');setTimeout(()=>location.reload(),1000);});
}

// ── PURCHASE MODAL ────────────────────────────────────
function openPurchase(pu){
  document.getElementById('pu-id').value=pu.id;
  document.getElementById('pu-oid').textContent=pu.order_id||'';
  document.getElementById('pu-amt').textContent='₹'+(parseFloat(pu.amount)||0).toFixed(2);
  document.getElementById('pu-utr').textContent=pu.utr_number||'Not submitted';
  document.getElementById('pu-meth').textContent=pu.payment_method||'—';
  document.getElementById('pu-ip').textContent=pu.customer_ip||'—';
  document.getElementById('pu-photo').textContent=pu.photo_title||'—';
  document.getElementById('pu-date').textContent=pu.created_at||'';
  const t=pu.time_taken_seconds;
  document.getElementById('pu-time').textContent=t?(t+'s taken · '+(pu.countdown_at_submit||'?')+'s remaining'):'—';
  let bi={};try{bi=JSON.parse(pu.browser_info||'{}');}catch(e){}
  document.getElementById('pu-dev').textContent=bi.user_agent||pu.device_info||'—';
  document.getElementById('pu-status').value=pu.payment_status||'pending';
  document.getElementById('pu-notes').value=pu.notes||'';
  openMo('mo-purchase');
}
function savePurchase(){
  const fd=new FormData();
  fd.append('action','update_purchase');
  fd.append('purchase_id',document.getElementById('pu-id').value);
  fd.append('status',document.getElementById('pu-status').value);
  fd.append('notes',document.getElementById('pu-notes').value);
  post(fd,()=>{closeMo('mo-purchase');toast('Purchase updated!');setTimeout(()=>location.reload(),1200);});
}

// ── REVIEW MODAL ──────────────────────────────────────
function openReviewMo(rv){
  document.getElementById('rv-id').value=rv?rv.id:'';
  document.getElementById('rv-name').value=rv?rv.reviewer_name:'';
  document.getElementById('rv-rating').value=rv?rv.rating:5;
  document.getElementById('rv-status').value=rv?rv.status:'approved';
  document.getElementById('rv-comment').value=rv?rv.comment:'';
  if(rv&&rv.photo_id) document.getElementById('rv-photo').value=rv.photo_id;
  openMo('mo-review');
}
function saveReview(){
  const fd=new FormData();
  fd.append('action','save_review');
  fd.append('review_id',document.getElementById('rv-id').value);
  fd.append('photo_id',document.getElementById('rv-photo').value);
  fd.append('reviewer_name',document.getElementById('rv-name').value);
  fd.append('rating',document.getElementById('rv-rating').value);
  fd.append('status',document.getElementById('rv-status').value);
  fd.append('comment',document.getElementById('rv-comment').value);
  post(fd,()=>{closeMo('mo-review');toast('Review saved!');setTimeout(()=>location.reload(),1200);});
}
function delReview(id){
  if(!confirm('Delete this review?')) return;
  const fd=new FormData(); fd.append('action','delete_review'); fd.append('review_id',id);
  post(fd,()=>{toast('Deleted!');setTimeout(()=>location.reload(),1000);});
}

// ── NOTIFICATION MODAL ────────────────────────────────
function openNotifMo(){
  document.getElementById('nm-title').value='';
  document.getElementById('nm-msg').value='';
  document.getElementById('nm-type').value='info';
  openMo('mo-notif');
}
function saveNotif(){
  const fd=new FormData();
  fd.append('action','add_notification');
  fd.append('title',document.getElementById('nm-title').value);
  fd.append('message',document.getElementById('nm-msg').value);
  fd.append('type',document.getElementById('nm-type').value);
  post(fd,()=>{closeMo('mo-notif');toast('Notification sent!');setTimeout(()=>location.reload(),1200);});
}
function delNotif(id){
  if(!confirm('Delete this notification?')) return;
  const fd=new FormData(); fd.append('action','delete_notification'); fd.append('notif_id',id);
  post(fd,()=>{toast('Deleted!');setTimeout(()=>location.reload(),1000);});
}

// ── SLIDE MODAL ───────────────────────────────────────
function openSlideMo(sl){
  document.getElementById('sl-id').value=sl?sl.id:'';
  document.getElementById('sl-img').value=sl?sl.image_url:'';
  document.getElementById('sl-caption').value=sl?sl.caption:'';
  document.getElementById('sl-order').value=sl?sl.sort_order:0;
  document.getElementById('sl-status').value=sl?sl.status:'active';
  document.getElementById('sl-h').textContent=sl?'Edit Slide':'Add Slide';
  const prev=document.getElementById('sl-preview');
  const img=document.getElementById('sl-prev-img');
  if(sl&&sl.image_url){img.src=sl.image_url;prev.style.display='block';}else{prev.style.display='none';}
  document.getElementById('sl-img').addEventListener('input',function(){
    if(this.value){img.src=this.value;prev.style.display='block';}
  },{once:true});
  openMo('mo-slide');
}
function saveSlide(){
  const fd=new FormData();
  fd.append('action','save_slide');
  fd.append('slide_id',document.getElementById('sl-id').value||0);
  fd.append('image_url',document.getElementById('sl-img').value);
  fd.append('caption',document.getElementById('sl-caption').value);
  fd.append('sort_order',document.getElementById('sl-order').value||0);
  fd.append('status',document.getElementById('sl-status').value);
  post(fd,()=>{closeMo('mo-slide');toast('Slide saved!');setTimeout(()=>location.reload(),1200);});
}
function delSlide(id){
  if(!confirm('Delete this slide?')) return;
  const fd=new FormData(); fd.append('action','delete_slide'); fd.append('slide_id',id);
  post(fd,()=>{toast('Deleted!');setTimeout(()=>location.reload(),1000);});
}

// ── SETTINGS SAVE ─────────────────────────────────────
function saveSettings(){
  const fd=new FormData(document.getElementById('sf'));
  fd.append('action','save_settings');
  post(fd,(d)=>toast(d.msg||'Settings saved!'));
}

// ── SLIDE IMG PREVIEW ─────────────────────────────────
document.getElementById('sl-img')?.addEventListener('input',function(){
  const p=document.getElementById('sl-preview');
  const img=document.getElementById('sl-prev-img');
  if(this.value){img.src=this.value;p.style.display='block';}else{p.style.display='none';}
});
</script>
</body>
</html>
