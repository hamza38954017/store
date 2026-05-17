<?php
require_once __DIR__ . '/helpers.php';

session_start();
$photoId  = (int)($_GET['photo_id'] ?? 0);
$fromCart = ($_GET['from'] ?? '') === 'cart';
$sid      = session_id();

// Load photo/cart + settings from API
$data       = apiCall('get_payment_data', ['session_id'=>$sid,'photo_id'=>$photoId,'from_cart'=>$fromCart]);
$s          = $data['settings']   ?? [];
$photo      = $data['photo']      ?? null;
$cartPhotos = $data['cart_photos']?? [];
$totalAmount= (float)($data['total'] ?? 0);

if (!$photo && empty($cartPhotos)) { header('Location: index.php'); exit; }

$primaryColor   = $s['primary_color']    ?? '#FF6B35';
$secondaryColor = $s['secondary_color']  ?? '#1A1A2E';
$accentColor    = $s['accent_color']     ?? '#FFD700';
$bgColor        = $s['bg_color']         ?? '#0D0D1A';
$siteName       = $s['site_name']        ?? 'Photo Seller';
$telegramUrl    = $s['telegram_url']     ?? 'https://t.me/photoseller';
$upiId          = $s['upi_id']           ?? '9534591071-3@axl';
$upiName        = $s['upi_name']         ?? 'MAHMUDA PARWEEN';
$timeout        = (int)($s['payment_timeout'] ?? 300);

// ── Generate order ID (once per session or when expired) ──────────────────
if (!isset($_SESSION['payment_order_id']) || (time() - ($_SESSION['payment_time'] ?? 0)) > $timeout) {
    $_SESSION['payment_order_id'] = generateOrderId();
    $_SESSION['payment_time']     = time();
    // Log payment session to InfinityFree DB
    $ptitle = $fromCart ? implode(', ', array_column($cartPhotos, 'title')) : ($photo['title'] ?? '');
    $pid    = $fromCart ? null : $photoId;
    apiCall('log_payment_session', [
        'order_id'    => $_SESSION['payment_order_id'],
        'photo_id'    => $pid,
        'photo_title' => $ptitle,
        'amount'      => $totalAmount,
        'ip'          => getClientIP(),
        'browser_info'=> getBrowserInfo(),
    ]);
}
$orderId       = $_SESSION['payment_order_id'];
$elapsedTime   = time() - ($_SESSION['payment_time'] ?? time());
$timeRemaining = max(0, $timeout - $elapsedTime);

// ── Handle UTR Submission (AJAX POST) ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['utr'])) {
    header('Content-Type: application/json');
    $ptitle = $fromCart ? implode(', ', array_column($cartPhotos, 'title')) : ($photo['title'] ?? '');
    $pid    = $fromCart ? null : $photoId;
    $result = apiCall('submit_utr', [
        'order_id'           => $orderId,
        'photo_id'           => $pid,
        'photo_title'        => $ptitle,
        'amount'             => $totalAmount,
        'utr'                => $_POST['utr']               ?? '',
        'method'             => $_POST['method']            ?? 'UPI',
        'countdown_total'    => $timeout,
        'countdown_at_submit'=> (int)($_POST['countdown_at_submit'] ?? 0),
        'ip'                 => getClientIP(),
        'browser_info'       => getBrowserInfo(),
        'device_info'        => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'from_cart'          => $fromCart,
        'session_id'         => $sid,
    ]);
    if ($result['success'] ?? false) {
        unset($_SESSION['payment_order_id'], $_SESSION['payment_time']);
    }
    echo json_encode($result); exit;
}

// ── Handle timeout notification (AJAX) ────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'timeout_notify') {
    header('Content-Type: application/json');
    echo json_encode(apiCall('timeout_notify', ['order_id' => $orderId])); exit;
}

// ── UPI URLs ──────────────────────────────────────────────────────────────
$amt        = number_format($totalAmount, 2, '.', '');
$encUpiId   = urlencode($upiId);
$encName    = urlencode($upiName);
$upiBase    = "pa=$encUpiId&pn=$encName&mc=0000&mode=02&purpose=00&am=$amt&cu=INR";
$gpayUrl    = "tez://upi/pay?$upiBase";
$phonePeUrl = "phonepe://pay?$upiBase";
$paytmUrl   = "paytmmp://pay?$upiBase";
$genericUpi = "upi://pay?$upiBase";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Payment — <?= e($siteName) ?></title>
<meta name="robots" content="noindex">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">
<style>
:root{
  --primary:<?= e($primaryColor) ?>;
  --secondary:<?= e($secondaryColor) ?>;
  --accent:<?= e($accentColor) ?>;
  --bg:<?= e($bgColor) ?>;
  --text:<?= e($s['text_color']??'#FFFFFF') ?>;
  --card-bg:rgba(255,255,255,0.04);
  --card-border:rgba(255,255,255,0.08);
  --radius:20px;
}
body.day-mode{--bg:#F5F5F0;--text:#1A1A2E;--card-bg:rgba(0,0,0,0.04);--card-border:rgba(0,0,0,0.1);--secondary:#fff;}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{overflow-x:hidden}
body{background:var(--bg);color:var(--text);font-family:'DM Sans',sans-serif;min-height:100vh;overflow-x:hidden;}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse 80% 50% at 20% 0%,rgba(255,107,53,0.11) 0%,transparent 60%);pointer-events:none;z-index:0;}

.page-wrap{max-width:540px;margin:0 auto;padding:20px 16px 60px;position:relative;z-index:1;}

.back-btn{display:inline-flex;align-items:center;gap:6px;color:rgba(128,128,128,0.6);text-decoration:none;font-size:.84rem;margin-bottom:20px;transition:.2s;}
.back-btn:hover{color:var(--primary);}

/* ── ORDER CARD ── */
.order-card{background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius);padding:20px;margin-bottom:16px;}
.order-title{font-family:'Playfair Display',serif;font-size:1rem;font-weight:700;margin-bottom:14px;color:rgba(128,128,128,0.7);}
.order-photo{display:flex;gap:12px;align-items:center;margin-bottom:12px;}
.order-photo img{width:58px;height:58px;border-radius:10px;object-fit:cover;}
.order-photo-name{font-weight:700;font-size:.9rem;margin-bottom:3px;line-height:1.3;}
.order-photo-price{font-size:1.15rem;font-weight:800;color:var(--primary);}
.order-id{font-size:.72rem;color:rgba(128,128,128,0.35);font-family:monospace;margin-top:6px;}

/* ── TIMER ── */
.timer-bar{background:var(--card-bg);border:1px solid var(--card-border);border-radius:14px;padding:14px 18px;margin-bottom:16px;}
.timer-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;}
.timer-label{font-size:.78rem;color:rgba(128,128,128,0.6);font-weight:600;text-transform:uppercase;letter-spacing:1px;}
.timer-display{font-size:1.2rem;font-weight:800;color:var(--accent);font-family:monospace;}
.timer-display.urgent{color:#ef4444;animation:pulse 1s ease-in-out infinite;}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.55}}
.timer-progress{width:100%;height:5px;background:rgba(255,255,255,0.08);border-radius:3px;overflow:hidden;}
.timer-bar-fill{height:100%;background:linear-gradient(90deg,var(--primary),var(--accent));border-radius:3px;transition:width 1s linear;}

/* ── STEP CARD ── */
.step-card{background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius);padding:22px;margin-bottom:14px;}
.step-header{font-weight:700;font-size:.95rem;margin-bottom:16px;display:flex;align-items:center;gap:10px;}
.step-num{width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,var(--primary),#ff8c00);color:#fff;font-size:.78rem;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;}

/* ── QR ── */
.qr-center{display:flex;flex-direction:column;align-items:center;}
.qr-wrap{background:#fff;border-radius:16px;padding:18px;display:inline-flex;align-items:center;justify-content:center;margin-bottom:14px;box-shadow:0 8px 30px rgba(0,0,0,0.3);}
.qr-amount-badge{background:linear-gradient(135deg,var(--primary),#ff8c00);color:#fff;font-weight:800;font-size:1.2rem;padding:10px 24px;border-radius:100px;margin-bottom:12px;letter-spacing:.5px;}
.qr-hint{font-size:.8rem;color:rgba(128,128,128,0.6);text-align:center;}
.qr-upi-id{font-size:.8rem;text-align:center;margin-top:10px;color:rgba(128,128,128,0.5);}
.qr-upi-id strong{color:var(--primary);}

/* ── PAYMENT METHODS ── */
.pay-methods{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;}
.pay-btn{
  display:flex;flex-direction:column;align-items:center;justify-content:center;gap:7px;
  background:rgba(255,255,255,0.04);border:2px solid rgba(255,255,255,0.1);border-radius:16px;
  padding:18px 10px;cursor:pointer;text-decoration:none;color:var(--text);transition:all .3s;position:relative;
}
.pay-btn:hover,.pay-btn.active{border-color:var(--primary);background:rgba(255,107,53,0.07);transform:translateY(-2px);}
.pay-btn-icon{font-size:2rem;line-height:1;}
.pay-btn-name{font-size:.8rem;font-weight:700;text-align:center;}
.pay-btn-amount{font-size:.72rem;color:rgba(128,128,128,0.5);}
/* "App not installed" badge */
.not-installed-badge{
  position:absolute;top:6px;right:6px;
  background:rgba(239,68,68,0.15);
  border:1px solid rgba(239,68,68,0.4);
  color:#ef4444;
  font-size:.6rem;font-weight:700;
  padding:2px 7px;border-radius:100px;
  display:none;
}
.pay-btn.not-installed .not-installed-badge{display:block;}
.pay-btn.not-installed{opacity:.7;border-color:rgba(239,68,68,0.3);}

/* ── UTR SECTION ── */
.utr-section{margin-top:2px;}
.utr-label-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;}
.utr-label{font-size:.84rem;font-weight:700;color:rgba(128,128,128,0.8);}
.guide-link{font-size:.74rem;color:var(--primary);background:rgba(255,107,53,0.1);border:1px solid rgba(255,107,53,0.3);border-radius:100px;padding:3px 11px;cursor:pointer;text-decoration:none;transition:.2s;}
.guide-link:hover{background:var(--primary);color:#fff;}
.utr-input{
  width:100%;background:rgba(255,255,255,0.06);border:2px solid rgba(255,255,255,0.1);border-radius:14px;
  padding:16px 18px;color:var(--text);font-size:1.1rem;font-weight:700;font-family:monospace;
  letter-spacing:2px;outline:none;transition:.3s;
}
.utr-input:focus{border-color:var(--primary);box-shadow:0 0 0 4px rgba(255,107,53,0.12);}
.utr-input.shake{animation:shake .5s cubic-bezier(.36,.07,.19,.97) both;}
.utr-input.valid{border-color:#22c55e;}
@keyframes shake{10%,90%{transform:translateX(-2px)}20%,80%{transform:translateX(4px)}30%,50%,70%{transform:translateX(-6px)}40%,60%{transform:translateX(6px)}}
.utr-counter{font-size:.7rem;color:rgba(128,128,128,0.4);text-align:right;margin-top:4px;}
.submit-btn{
  width:100%;margin-top:14px;
  background:linear-gradient(135deg,var(--primary),#ff8c00);
  color:#fff;border:none;padding:17px;border-radius:14px;font-size:1rem;font-weight:800;
  cursor:pointer;transition:.3s;box-shadow:0 8px 28px rgba(255,107,53,0.32);letter-spacing:.3px;
}
.submit-btn:hover{transform:translateY(-2px);box-shadow:0 12px 36px rgba(255,107,53,0.46);}
.submit-btn:disabled{opacity:.6;cursor:not-allowed;transform:none;}
.security-note{background:rgba(34,197,94,0.07);border:1px solid rgba(34,197,94,0.18);border-radius:11px;padding:11px 14px;font-size:.76rem;color:rgba(128,128,128,0.7);display:flex;align-items:center;gap:7px;margin-top:14px;}

/* ── SUCCESS OVERLAY ── */
/* ── SUCCESS OVERLAY ── */
.success-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.92);z-index:9999;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:40px;backdrop-filter:blur(14px);}
.success-overlay.show{display:flex;}
.success-icon{font-size:5rem;margin-bottom:18px;animation:bounceIn .6s ease;}
@keyframes bounceIn{0%{transform:scale(.3);opacity:0}60%{transform:scale(1.1)}100%{transform:scale(1);opacity:1}}
.success-title{font-family:'Playfair Display',serif;font-size:1.9rem;font-weight:900;margin-bottom:10px;color:#22c55e;}
.success-sub{color:rgba(255,255,255,0.65);margin-bottom:6px;font-size:.93rem;}
.success-redirect{font-size:.8rem;color:rgba(255,255,255,0.4);margin-top:8px;}

body.day-mode .success-overlay { background:rgba(245, 245, 240, 0.95); }
body.day-mode .success-sub { color:rgba(26, 26, 46, 0.8); }
body.day-mode .success-redirect { color:rgba(26, 26, 46, 0.5); }

/* ── BOTTOM SHEET (Guide) ── */


    
/* ── BOTTOM SHEET (Guide) ── */
.sheet-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:5000;align-items:flex-end;justify-content:center;backdrop-filter:blur(4px);}
.sheet-overlay.open{display:flex;animation:fadeIn .2s ease;}
.bottom-sheet{width:100%;max-width:480px;background:var(--secondary);border-radius:24px 24px 0 0;max-height:88vh;overflow-y:auto;animation:slideUp .35s ease;}
.sheet-handle{width:38px;height:4px;background:rgba(128,128,128,0.22);border-radius:2px;margin:14px auto 0;}
.sheet-hdr{padding:14px 22px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--card-border);}
.sheet-close-btn{background:none;border:none;color:rgba(128,128,128,0.5);font-size:1.3rem;cursor:pointer;}
.sheet-content{padding:18px 22px 40px;}
.sheet-content img{width:100%;border-radius:12px;}
@keyframes slideUp{from{transform:translateY(100%)}to{transform:translateY(0)}}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}

::-webkit-scrollbar{width:5px}::-webkit-scrollbar-thumb{background:var(--primary);border-radius:3px}
</style>
</head>
<body id="body" class="day-mode">

<div class="page-wrap">
  <a href="<?= $fromCart ? 'cart.php' : 'index.php?photo='.e($photo['slug']??'') ?>" class="back-btn">← Back</a>

  <!-- Order Summary -->
  <div class="order-card">
    <div class="order-title">📦 Order Summary</div>
    <?php if ($fromCart): foreach ($cartPhotos as $cp): ?>
    <div class="order-photo">
      <img src="<?= e($cp['thumbnail_url']?:$cp['image_url']) ?>" alt="" onerror="this.src='https://placehold.co/58x58/1A1A2E/FF6B35?text=P'">
      <div>
        <div class="order-photo-name"><?= e($cp['title']) ?></div>
        <div style="color:var(--primary);font-weight:700">₹<?= number_format($cp['price'],2) ?></div>
      </div>
    </div>
    <?php endforeach;
    echo '<div style="border-top:1px solid var(--card-border);padding-top:10px;margin-top:4px;display:flex;justify-content:space-between;font-weight:800;font-size:1.05rem"><span>Total</span><span style="color:var(--primary)">₹'.number_format($totalAmount,2).'</span></div>';
    elseif ($photo): ?>
    <div class="order-photo">
      <img src="<?= e($photo['thumbnail_url']?:$photo['image_url']) ?>" alt="" onerror="this.src='https://placehold.co/58x58/1A1A2E/FF6B35?text=P'">
      <div>
        <div class="order-photo-name"><?= e($photo['title']) ?></div>
        <div class="order-photo-price">₹<?= number_format($totalAmount,2) ?></div>
      </div>
    </div>
    <?php endif; ?>
    <div class="order-id">Order ID: <?= e($orderId) ?></div>
  </div>

  <!-- Timer -->
  <div class="timer-bar">
    <div class="timer-top">
      <span class="timer-label">⏱ Time Remaining</span>
      <span class="timer-display" id="timer-display"><?= gmdate('i:s',$timeRemaining) ?></span>
    </div>
    <div class="timer-progress"><div class="timer-bar-fill" id="timer-fill" style="width:<?= ($timeRemaining/$timeout)*100 ?>%"></div></div>
  </div>

  <!-- STEP 1: QR Code — PROMINENT AT TOP -->
  <div class="step-card">
    <div class="step-header"><div class="step-num">1</div> Scan QR Code to Pay</div>
    <div class="qr-center">
      <div class="qr-amount-badge">Pay ₹<?= number_format($totalAmount,2) ?></div>
      <div class="qr-wrap"><div id="qr-canvas"></div></div>
      <div class="qr-hint">Scan with GPay, PhonePe, Paytm, or any UPI app</div>
      <div class="qr-upi-id">UPI ID: <strong><?= e($upiId) ?></strong></div>
    </div>
  </div>

  <!-- STEP 2: Payment App Buttons -->
  <div class="step-card">
    <div class="step-header"><div class="step-num">2</div> Or Open Payment App</div>
    <div class="pay-methods" id="pay-methods">
      <a href="<?= e($gpayUrl) ?>" class="pay-btn" id="btn-gpay" onclick="handlePayClick(event,'GPay','<?= e($gpayUrl) ?>',this)">
        <span class="not-installed-badge">Not Installed</span>
        <div class="pay-btn-icon">🟢</div>
        <div class="pay-btn-name">Google Pay</div>
        <div class="pay-btn-amount">₹<?= number_format($totalAmount,2) ?></div>
      </a>
      <a href="<?= e($phonePeUrl) ?>" class="pay-btn" id="btn-phonepe" onclick="handlePayClick(event,'PhonePe','<?= e($phonePeUrl) ?>',this)">
        <span class="not-installed-badge">Not Installed</span>
        <div class="pay-btn-icon">🟣</div>
        <div class="pay-btn-name">PhonePe</div>
        <div class="pay-btn-amount">₹<?= number_format($totalAmount,2) ?></div>
      </a>
      <a href="<?= e($paytmUrl) ?>" class="pay-btn" id="btn-paytm" onclick="handlePayClick(event,'Paytm','<?= e($paytmUrl) ?>',this)">
        <span class="not-installed-badge">Not Installed</span>
        <div class="pay-btn-icon">🔵</div>
        <div class="pay-btn-name">Paytm</div>
        <div class="pay-btn-amount">₹<?= number_format($totalAmount,2) ?></div>
      </a>
      <a href="<?= e($genericUpi) ?>" class="pay-btn" id="btn-upi" onclick="handlePayClick(event,'UPI','<?= e($genericUpi) ?>',this)">
        <span class="not-installed-badge">Not Installed</span>
        <div class="pay-btn-icon">💳</div>
        <div class="pay-btn-name">Any UPI App</div>
        <div class="pay-btn-amount">₹<?= number_format($totalAmount,2) ?></div>
      </a>
    </div>
  </div>

  <!-- STEP 3: Submit UTR — BELOW PAYMENT APPS -->
  <div class="step-card">
    <div class="step-header"><div class="step-num">3</div> Submit UTR / Ref Number After Payment</div>
    <div class="utr-section">
      <div class="utr-label-row">
        <span class="utr-label">* UTR Number (12 digits)</span>
        <button class="guide-link" onclick="openGuide()">📖 Guide</button>
      </div>
      <input type="text" class="utr-input" id="utr-input"
        placeholder="Enter 12-digit UTR / UPI Ref No"
        maxlength="12" inputmode="numeric"
        oninput="handleUtrInput(this)"
        onfocus="triggerShake()">
      <div class="utr-counter" id="utr-counter">0 / 12 digits</div>
      <button class="submit-btn" id="submit-btn" onclick="submitUTR()">✅ Submit UTR / Ref No</button>
    </div>
    <div class="security-note">🔒 Payment secured by UPI APP Pay. Manual verification within 1-24 hours.</div>
  </div>
</div>

<!-- Success Overlay -->
<div class="success-overlay" id="success-overlay">
  <div class="success-icon">✅</div>
  <div class="success-title">Payment Submitted!</div>
  <div class="success-sub">Your UTR has been recorded for manual verification.</div>
  <div class="success-sub">Please contact us on <strong>Telegram</strong> with your UTR number.</div>
  <div class="success-redirect" id="redirect-txt">Redirecting to Telegram in 3 seconds...</div>
</div>

<!-- Guide Bottom Sheet -->
<div class="sheet-overlay" id="guide-sheet" onclick="closeGuide(event)">
  <div class="bottom-sheet">
    <div class="sheet-handle"></div>
    <div class="sheet-hdr">
      <span style="font-weight:700;font-size:.95rem">📖 How to Find Your UTR Number</span>
      <button class="sheet-close-btn" onclick="closeGuideBtn()">✕</button>
    </div>
    <div class="sheet-content">
      <p style="color:rgba(128,128,128,0.55);font-size:.8rem;margin-bottom:14px">Scroll down to see guides for all payment apps</p>
      <img src="<?= e($s['guide_image_url']??'') ?>" alt="UTR Guide" onerror="this.src='https://placehold.co/400x900/1A1A2E/FF6B35?text=UTR+Guide'">
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
const TIMEOUT     = <?= $timeout ?>;
const REMAINING   = <?= $timeRemaining ?>;
const TELEGRAM    = '<?= e($telegramUrl) ?>';
const UPI_URL     = '<?= e($genericUpi) ?>';
const POST_URL    = 'payment.php<?= $fromCart ? '?from=cart' : '?photo_id='.$photoId ?>';

let currentCountdown = REMAINING;

// ── Theme ─────────────────────────────────────────────
// ── Theme ─────────────────────────────────────────────
(function(){
  const t = localStorage.getItem('ps_theme');
  // Only switch to dark if the user specifically saved it before
  if(t === 'dark'){
    document.getElementById('body').className = '';
  }
})();


// ── QR Code ───────────────────────────────────────────
window.addEventListener('DOMContentLoaded',()=>{
  try {
    new QRCode(document.getElementById('qr-canvas'),{
      text:UPI_URL, width:200, height:200,
      colorDark:'#000000', colorLight:'#ffffff',
      correctLevel:QRCode.CorrectLevel.H
    });
  } catch(e) {
    document.getElementById('qr-canvas').innerHTML='<div style="color:#999;font-size:.78rem;text-align:center;padding:20px">QR unavailable<br>Use UPI ID: <?= e($upiId) ?></div>';
  }
  startTimer();
  checkAppsInstalled();
});

// ── Timer ─────────────────────────────────────────────
function startTimer(){
  const display=document.getElementById('timer-display');
  const fill=document.getElementById('timer-fill');
  fill.style.width=(currentCountdown/TIMEOUT*100)+'%';
  const iv=setInterval(()=>{
    currentCountdown--;
    if(currentCountdown<=0){
      clearInterval(iv);
      display.textContent='00:00';
      fill.style.width='0%';
      display.classList.remove('urgent');
      // Lock form
      document.getElementById('utr-input').disabled=true;
      const btn=document.getElementById('submit-btn');
      btn.disabled=true;btn.textContent='❌ Time Expired. Please Refresh.';
      // Notify server
      const fd=new FormData();fd.append('action','timeout_notify');
      fetch(POST_URL,{method:'POST',body:fd}).catch(()=>{});
      return;
    }
    const m=Math.floor(currentCountdown/60),s=currentCountdown%60;
    display.textContent=String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
    if(currentCountdown<60) display.classList.add('urgent');
    fill.style.width=(currentCountdown/TIMEOUT*100)+'%';
  },1000);
}

// ── App detection ─────────────────────────────────────
// Attempt to open deep link; if page stays visible after timeout, app is not installed
function checkAppsInstalled(){
  // On desktop/non-Android, just mark all as available
  // On mobile Android, attempt each deeplink and watch for blur/visibilitychange
}

let selectedMethod='';
let clickTimestamp=0;

function handlePayClick(e,method,url,btnEl){
  e.preventDefault();
  selectedMethod=method;
  clickTimestamp=Date.now();

  document.querySelectorAll('.pay-btn').forEach(b=>{b.classList.remove('active');});
  btnEl.classList.add('active');

  // Try to open the deep link
  let appOpened=false;
  const iframe=document.createElement('iframe');
  iframe.style.display='none';
  document.body.appendChild(iframe);

  // Listen for page hide (app opened)
  const onHide=()=>{ appOpened=true; };
  document.addEventListener('visibilitychange',onHide,{once:true});
  window.addEventListener('blur',onHide,{once:true});

  // Redirect to app URL
  window.location.href=url;

  // After 2s, check if app was opened; if not, show "not installed"
  setTimeout(()=>{
    document.removeEventListener('visibilitychange',onHide);
    window.removeEventListener('blur',onHide);
    document.body.removeChild(iframe);
    if(!appOpened){
      btnEl.classList.add('not-installed');
      showToast('⚠️ '+method+' app not installed. Try another app or scan the QR code.','error');
    }
  },2000);
}

// ── UTR Input ─────────────────────────────────────────
function handleUtrInput(el){
  el.value=el.value.replace(/\D/g,'').slice(0,12);
  document.getElementById('utr-counter').textContent=el.value.length+' / 12 digits';
  el.classList.toggle('valid', el.value.length===12);
  el.style.borderColor='';
}

let shaken=false;
function triggerShake(){
  if(shaken) return; shaken=true;
  const el=document.getElementById('utr-input');
  el.classList.add('shake');
  setTimeout(()=>el.classList.remove('shake'),600);
}
function triggerShakeFull(){
  const el=document.getElementById('utr-input');
  el.classList.remove('shake');
  void el.offsetWidth;
  el.classList.add('shake');
  setTimeout(()=>el.classList.remove('shake'),600);
}

// ── Submit UTR ────────────────────────────────────────
function submitUTR(){
  const utr=document.getElementById('utr-input').value.trim();
  if(utr.length!==12){ triggerShakeFull(); showToast('❌ Please enter a valid 12-digit UTR number.','error'); return; }

  const btn=document.getElementById('submit-btn');
  btn.disabled=true; btn.textContent='⏳ Submitting...';

  const fd=new FormData();
  fd.append('utr',utr);
  fd.append('method',selectedMethod||'UPI');
  fd.append('countdown_at_submit',currentCountdown);   // time remaining at submit

  fetch(POST_URL,{method:'POST',body:fd})
    .then(r=>r.json())
    .then(d=>{
      if(d.success){
        document.getElementById('success-overlay').classList.add('show');
        let cnt=3;
        const ri=setInterval(()=>{
          cnt--;
          document.getElementById('redirect-txt').textContent='Redirecting to Telegram in '+cnt+' seconds...';
          if(cnt<=0){clearInterval(ri);window.location.href=TELEGRAM;}
        },1000);
      } else {
        btn.disabled=false; btn.textContent='✅ Submit UTR / Ref No';
        showToast('❌ '+(d.msg||'Error. Contact support.'),'error');
      }
    })
    .catch(()=>{
      btn.disabled=false; btn.textContent='✅ Submit UTR / Ref No';
      showToast('❌ Network error. Please try again.','error');
    });
}

// ── Guide Sheet ───────────────────────────────────────
function openGuide(){document.getElementById('guide-sheet').classList.add('open');}
function closeGuide(e){if(e.target===document.getElementById('guide-sheet'))closeGuideBtn();}
function closeGuideBtn(){document.getElementById('guide-sheet').classList.remove('open');}

// ── Toast ─────────────────────────────────────────────
function showToast(msg,type='info'){
  let c=document.getElementById('toast-c');
  if(!c){c=document.createElement('div');c.id='toast-c';c.style.cssText='position:fixed;bottom:22px;right:18px;z-index:9999;display:flex;flex-direction:column;gap:8px;max-width:calc(100vw - 36px)';document.body.appendChild(c);}
  const t=document.createElement('div');
  t.style.cssText='background:var(--secondary,#1A1A2E);border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:12px 16px;font-size:.84rem;font-weight:500;animation:slideInR .3s ease;box-shadow:0 8px 28px rgba(0,0,0,0.4);';
  if(type==='error')t.style.borderLeft='3px solid #ef4444';
  if(type==='success')t.style.borderLeft='3px solid #22c55e';
  t.textContent=msg;c.appendChild(t);
  setTimeout(()=>t.remove(),3500);
}
</script>
<style>@keyframes slideInR{from{opacity:0;transform:translateX(28px)}to{opacity:1;transform:translateX(0)}}</style>
</body>
</html>
