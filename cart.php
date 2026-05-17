<?php
session_start();
require_once __DIR__ . '/helpers.php';

$sid = session_id();

// Handle remove / clear via GET — call API then redirect
if (isset($_GET['remove'])) {
    apiCall('remove_cart', ['session_id'=>$sid, 'photo_id'=>(int)$_GET['remove']]);
    header('Location: cart.php'); exit;
}
if (isset($_GET['clear'])) {
    apiCall('clear_cart', ['session_id'=>$sid]);
    header('Location: cart.php'); exit;
}

// Load cart + settings in one API call
$data      = apiCall('get_cart_page', ['session_id'=>$sid]);
$cartItems = $data['items']    ?? [];
$s         = $data['settings'] ?? [];
$total     = array_sum(array_column($cartItems, 'price'));

$primaryColor = $s['primary_color'] ?? '#FF6B35';
$bgColor      = $s['bg_color']      ?? '#0D0D1A';
$textColor    = $s['text_color']    ?? '#FFFFFF';
$siteName     = $s['site_name']     ?? 'Photo Seller';
$telegramUrl  = $s['telegram_url']  ?? 'https://t.me/photoseller';
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Cart — <?= e($siteName) ?></title>
<meta name="robots" content="noindex">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">
<style>
:root{--primary:<?= e($primaryColor) ?>;--bg:<?= e($bgColor) ?>;--text:<?= e($textColor) ?>;--card-bg:rgba(255,255,255,0.04);--card-border:rgba(255,255,255,0.08);--radius:18px;--secondary:<?= e($s['secondary_color']??'#1A1A2E') ?>;}
body.day-mode{--bg:#F5F5F0;--text:#1A1A2E;--card-bg:rgba(0,0,0,0.04);--card-border:rgba(0,0,0,0.1);--secondary:#fff;}
body.day-mode .navbar { background:rgba(245,245,240,0.92); border-bottom:1px solid rgba(0,0,0,0.1); }

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{overflow-x:hidden}
body{background:var(--bg);color:var(--text);font-family:'DM Sans',sans-serif;min-height:100vh;overflow-x:hidden;}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse 70% 50% at 20% 0%,rgba(255,107,53,0.1) 0%,transparent 60%);pointer-events:none;z-index:0;}

.navbar{position:fixed;top:0;left:0;right:0;z-index:1000;background:rgba(13,13,26,0.9);backdrop-filter:blur(20px);border-bottom:1px solid var(--card-border);height:64px;display:flex;align-items:center;justify-content:space-between;padding:0 16px;}
.navbar-brand{font-family:'Playfair Display',serif;font-size:1.3rem;font-weight:900;background:linear-gradient(135deg,var(--primary),#FFD700);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;text-decoration:none;}
.nav-right{display:flex;align-items:center;gap:10px;}
.nav-link{display:inline-flex;align-items:center;gap:6px;background:var(--card-bg);border:1px solid var(--card-border);color:var(--text);padding:8px 16px;border-radius:100px;font-size:.82rem;font-weight:600;text-decoration:none;transition:.2s;}
.nav-link:hover{border-color:var(--primary);color:var(--primary);}
.theme-btn{background:var(--card-bg);border:1px solid var(--card-border);color:var(--text);padding:6px 12px;border-radius:100px;cursor:pointer;font-size:.8rem;font-weight:600;transition:.3s;white-space:nowrap;}

.page-wrap{max-width:860px;margin:0 auto;padding:90px 20px 60px;position:relative;z-index:1;}
.page-title{font-family:'Playfair Display',serif;font-size:clamp(1.8rem,5vw,2.6rem);font-weight:900;margin-bottom:8px;letter-spacing:-1px;}
.page-sub{color:rgba(128,128,128,0.7);margin-bottom:32px;font-size:.9rem;}

.cart-layout{display:grid;grid-template-columns:1fr 320px;gap:24px;align-items:start;}
@media(max-width:700px){.cart-layout{grid-template-columns:1fr;}}

.cart-list-wrap{background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius);overflow:hidden;}
.cart-row{display:flex;align-items:center;gap:14px;padding:16px 18px;border-bottom:1px solid var(--card-border);}
.cart-row:last-child{border-bottom:none;}
.cart-row img{width:70px;height:70px;border-radius:12px;object-fit:cover;flex-shrink:0;}
.cart-row-info{flex:1;min-width:0;}
.cart-row-title{font-weight:700;font-size:.92rem;margin-bottom:4px;line-height:1.3;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.cart-row-meta{font-size:.75rem;color:rgba(128,128,128,0.6);margin-bottom:6px;}
.cart-row-price{font-weight:800;color:var(--primary);font-size:1rem;}
.cart-row-remove{background:none;border:none;cursor:pointer;color:rgba(128,128,128,0.4);font-size:1.1rem;padding:6px;transition:.2s;flex-shrink:0;}
.cart-row-remove:hover{color:#ef4444;}

.cart-empty{text-align:center;padding:60px 20px;color:rgba(128,128,128,0.5);}
.cart-empty-icon{font-size:3.5rem;margin-bottom:16px;}

.order-summary{background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius);padding:24px;position:sticky;top:80px;}
.summary-title{font-weight:700;font-size:1rem;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid var(--card-border);}
.summary-row{display:flex;justify-content:space-between;font-size:.88rem;margin-bottom:10px;color:rgba(128,128,128,0.8);}
.summary-total{display:flex;justify-content:space-between;font-weight:800;font-size:1.15rem;padding-top:12px;border-top:1px solid var(--card-border);margin-top:8px;}
.summary-total span:last-child{color:var(--primary);}
.btn-checkout{display:block;width:100%;margin-top:18px;background:linear-gradient(135deg,var(--primary),#ff8c00);color:#fff;border:none;padding:16px;border-radius:14px;font-weight:700;font-size:1rem;cursor:pointer;text-align:center;text-decoration:none;transition:.3s;box-shadow:0 8px 28px rgba(255,107,53,0.32);}
.btn-checkout:hover{transform:translateY(-2px);box-shadow:0 12px 36px rgba(255,107,53,0.46);}
.btn-clear{display:block;width:100%;margin-top:10px;background:transparent;color:rgba(128,128,128,0.6);border:1px solid var(--card-border);padding:11px;border-radius:12px;font-weight:600;font-size:.85rem;cursor:pointer;text-align:center;text-decoration:none;transition:.2s;}
.btn-clear:hover{border-color:#ef4444;color:#ef4444;}
.security-note{background:rgba(34,197,94,0.07);border:1px solid rgba(34,197,94,0.18);border-radius:10px;padding:11px 14px;font-size:.76rem;color:rgba(128,128,128,0.7);display:flex;align-items:center;gap:7px;margin-top:14px;}

::-webkit-scrollbar{width:5px}::-webkit-scrollbar-thumb{background:var(--primary);border-radius:3px}
</style>
</head>

<body id="body" class="day-mode">

<nav class="navbar">
  <a href="index.php" class="navbar-brand">📸 <?= e($siteName) ?></a>
  <div class="nav-right">
    <button class="theme-btn" onclick="toggleTheme()" id="theme-btn">☀️ Day</button>

    <a href="index.php" class="nav-link">🏠 Home</a>
  </div>
</nav>

<div class="page-wrap">
  <div class="page-title">🛒 Shopping Cart</div>
  <p class="page-sub"><?= count($cartItems) ?> item<?= count($cartItems)!==1?'s':'' ?> in your cart</p>

  <?php if (empty($cartItems)): ?>
  <div class="cart-empty">
    <div class="cart-empty-icon">🛒</div>
    <div style="font-size:1.1rem;font-weight:600;margin-bottom:10px">Your cart is empty</div>
    <div style="font-size:.88rem;margin-bottom:24px">Browse our Store and add Cards to cart</div>
    <a href="index.php#gallery" class="btn-checkout" style="display:inline-flex;width:auto;padding:14px 32px;">🖼️ Browse Store</a>
  </div>

  <?php else: ?>
  <div class="cart-layout">
    <!-- Cart Items -->
    <div class="cart-list-wrap">
      <?php foreach ($cartItems as $item):
        $disc = $item['actual_price']>0 ? round((1-$item['price']/$item['actual_price'])*100) : 0;
      ?>
      <div class="cart-row">
        <img src="<?= e($item['thumbnail_url']?:$item['image_url']) ?>" alt="<?= e($item['title']) ?>" onerror="this.src='https://placehold.co/70x70/1A1A2E/FF6B35?text=P'">
        <div class="cart-row-info">
          <div class="cart-row-title"><?= e($item['title']) ?></div>
          <div class="cart-row-meta">🌍 <?= e($item['country']) ?> &nbsp;•&nbsp; ✨ <?= e($item['type']) ?><?php if ($disc>0): ?> &nbsp;•&nbsp; <span style="color:#FFD700"><?= $disc ?>% OFF</span><?php endif; ?></div>
          <div class="cart-row-price">₹<?= number_format($item['price'],2) ?> <?php if ($item['actual_price']>$item['price']): ?><span style="font-size:.78rem;text-decoration:line-through;color:rgba(128,128,128,0.45);font-weight:400">₹<?= number_format($item['actual_price'],2) ?></span><?php endif; ?></div>
        </div>
        <a href="cart.php?remove=<?= $item['id'] ?>" class="cart-row-remove" title="Remove" onclick="return confirm('Remove this photo from cart?')">🗑</a>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Order Summary -->
    <div class="order-summary">
      <div class="summary-title">📦 Order Summary</div>
      <?php foreach ($cartItems as $item): ?>
      <div class="summary-row">
        <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:170px"><?= e($item['title']) ?></span>
        <span>₹<?= number_format($item['price'],2) ?></span>
      </div>
      <?php endforeach; ?>
      <div class="summary-total"><span>Total</span><span>₹<?= number_format($total,2) ?></span></div>
      <a href="payment.php?from=cart" class="btn-checkout">💳 Proceed to Payment</a>
      <a href="cart.php?clear=1" class="btn-clear" onclick="return confirm('Clear all items from cart?')">🗑 Clear Cart</a>
      <div class="security-note">🔒 Payment secured by UPI. Delivery via Telegram after manual verification.</div>
    </div>
  </div>
  <?php endif; ?>
</div>
<script>
let isDark = false; // Changed default to Day Mode
function toggleTheme(){
  isDark = !isDark;
  document.getElementById('body').className = isDark ? '' : 'day-mode';
  document.getElementById('theme-btn').textContent = isDark ? '🌙 Night' : '☀️ Day';
  localStorage.setItem('ps_theme', isDark ? 'dark' : 'day');
}
(function(){
  const t = localStorage.getItem('ps_theme');
  // Only switch to dark if the user specifically saved it before
  if(t === 'dark'){
    isDark = true;
    document.getElementById('body').className = '';
    document.getElementById('theme-btn').textContent = '🌙 Night';
  }
})();
</script>
</body>
</html>
