<?php
session_start();
require_once __DIR__ . '/helpers.php';

// ── Visitor logging (once per session) ─────────────────────────────────────
if (empty($_SESSION['visit_logged'])) {
    $_SESSION['visit_logged'] = true;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    apiCall('log_visitor', [
        'session_id' => session_id(),
        'ip'         => getClientIP(),
        'ua'         => $ua,
        'browser'    => parseBrowserName($ua),
        'os'         => parseOSName($ua),
        'referer'    => $_SERVER['HTTP_REFERER'] ?? '',
        'page_url'   => (isHttps()?'https':'http').'://'.($_SERVER['HTTP_HOST']??'').($_SERVER['REQUEST_URI']??''),
    ]);
}

// ── AJAX Handlers ──────────────────────────────────────────────────────────
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $sid = session_id();
    $ip  = getClientIP();

    if ($_GET['ajax'] === 'notifications') {
        echo json_encode(apiCall('get_notifications')); exit;
    }
    if ($_GET['ajax'] === 'add_cart' && isset($_GET['photo_id'])) {
        echo json_encode(apiCall('add_cart', ['session_id'=>$sid,'photo_id'=>(int)$_GET['photo_id'],'ip'=>$ip])); exit;
    }
    if ($_GET['ajax'] === 'cart_items') {
        $r = apiCall('get_cart', ['session_id'=>$sid]);
        echo json_encode($r['items'] ?? []); exit;
    }
    if ($_GET['ajax'] === 'remove_cart' && isset($_GET['photo_id'])) {
        echo json_encode(apiCall('remove_cart', ['session_id'=>$sid,'photo_id'=>(int)$_GET['photo_id']])); exit;
    }
    if ($_GET['ajax'] === 'mark_notif_read' && isset($_GET['id'])) {
        echo json_encode(apiCall('mark_notif_read', ['id'=>(int)$_GET['id']])); exit;
    }
    if ($_GET['ajax'] === 'track' && isset($_GET['event'], $_GET['photo_id'])) {
        echo json_encode(apiCall('track_event', ['photo_id'=>(int)$_GET['photo_id'],'event'=>$_GET['event'],'session_id'=>$sid,'ip'=>$ip])); exit;
    }
    exit;
}

// ── Page Routing ───────────────────────────────────────────────────────────
$page    = $_GET['page'] ?? 'home';
$slug    = $_GET['photo'] ?? '';
$photo   = null;
$related = [];
$reviews = [];

if ($slug) {
    $r      = apiCall('get_photo', ['slug'=>$slug,'session_id'=>session_id(),'ip'=>getClientIP()]);
    $photo  = $r['photo']   ?? null;
    $related= $r['related'] ?? [];
    $reviews= $r['reviews'] ?? [];
    $page   = $photo ? 'photo' : '404';
}

// ── Home data ──────────────────────────────────────────────────────────────
$photos = []; $types = []; $countries = []; $slides = [];
$search = $_GET['q'] ?? ''; $filter_type = $_GET['type'] ?? ''; $filter_country = $_GET['country'] ?? '';

$pageData  = apiCall('get_page_data', ['q'=>$search,'type'=>$filter_type,'country'=>$filter_country]);
$settings  = $pageData['settings']  ?? [];
$photos    = $pageData['photos']    ?? [];
$types     = $pageData['types']     ?? [];
$countries = $pageData['countries'] ?? [];
$slides    = $pageData['slides']    ?? [];

// ── Counts ─────────────────────────────────────────────────────────────────
$counts     = apiCall('get_counts', ['session_id'=>session_id()]);
$cartCnt    = (int)($counts['cart_count']  ?? 0);
$notifCount = (int)($counts['notif_count'] ?? 0);

// ── Settings shortcuts ─────────────────────────────────────────────────────
$s = $settings;
$primaryColor   = $s['primary_color']   ?? '#FF6B35';
$secondaryColor = $s['secondary_color'] ?? '#1A1A2E';
$accentColor    = $s['accent_color']    ?? '#FFD700';
$bgColor        = $s['bg_color']        ?? '#0D0D1A';
$textColor      = $s['text_color']      ?? '#FFFFFF';
$siteName       = $s['site_name']       ?? 'Photo Seller';
$siteTitle      = $s['site_title']      ?? 'Purchase Photo 100% Trusted Seller';
$telegramUrl    = $s['telegram_url']    ?? 'https://t.me/photoseller';

$metaTitle    = $photo ? ($photo['meta_title'] ?: $photo['title'].' - '.$siteName) : $siteTitle;
$metaDesc     = $photo ? ($photo['meta_description'] ?: substr(strip_tags($photo['description']),0,160)) : ($s['meta_description'] ?? '');
$canonicalUrl = (isHttps()?'https':'http').'://'.($_SERVER['HTTP_HOST']??'localhost').($_SERVER['REQUEST_URI']??'/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title><?= e($metaTitle) ?></title>
<meta name="description" content="<?= e($metaDesc) ?>">
<meta name="robots" content="index,follow">
<meta property="og:title" content="<?= e($metaTitle) ?>">
<meta property="og:description" content="<?= e($metaDesc) ?>">
<meta property="og:type" content="<?= $photo ? 'product' : 'website' ?>">
<?php if ($photo && $photo['image_url']): ?><meta property="og:image" content="<?= e($photo['image_url']) ?>"><?php endif; ?>
<link rel="canonical" href="<?= e($canonicalUrl) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">
<style>
/* ── CSS VARIABLES ── */
:root {
  --primary:<?= e($primaryColor) ?>;
  --secondary:<?= e($secondaryColor) ?>;
  --accent:<?= e($accentColor) ?>;
  --bg:<?= e($bgColor) ?>;
  --text:<?= e($textColor) ?>;
  --card-bg:rgba(255,255,255,0.04);
  --card-border:rgba(255,255,255,0.08);
  --radius:20px;
  --shadow:0 20px 60px rgba(0,0,0,0.5);
  --nav-h:64px;
}
/* Day mode overrides */
body.day-mode {
  --bg:#F5F5F0;--text:#1A1A2E;--card-bg:rgba(0,0,0,0.04);
  --card-border:rgba(0,0,0,0.1);--secondary:#FFFFFF;
}
body.day-mode body::before { display:none }
body.day-mode .navbar { background:rgba(245,245,240,0.92); border-bottom:1px solid rgba(0,0,0,0.1); }
body.day-mode .photo-card { box-shadow:0 4px 20px rgba(0,0,0,0.08); }
body.day-mode .drawer { background:#fff; }

*,*::before,*::after { box-sizing:border-box; margin:0; padding:0 }
html { scroll-behavior:smooth; font-size:16px; overflow-x:hidden }
body { background:var(--bg); color:var(--text); font-family:'DM Sans',sans-serif; min-height:100vh; overflow-x:hidden; line-height:1.6; width:100%; }
body::before { content:''; position:fixed; top:0;left:0;right:0;bottom:0; background:radial-gradient(ellipse 80% 50% at 20% 0%,rgba(255,107,53,0.15) 0%,transparent 60%),radial-gradient(ellipse 60% 40% at 80% 100%,rgba(255,215,0,0.08) 0%,transparent 60%); pointer-events:none; z-index:0; }

::-webkit-scrollbar{width:5px}::-webkit-scrollbar-track{background:var(--bg)}::-webkit-scrollbar-thumb{background:var(--primary);border-radius:3px}

/* ── NAVBAR ── */
.navbar { position:fixed;top:0;left:0;right:0;z-index:1000; background:rgba(13,13,26,0.9); backdrop-filter:blur(20px); border-bottom:1px solid var(--card-border); height:var(--nav-h); display:flex;align-items:center;justify-content:space-between;padding:0 16px; }
.navbar-brand { font-family:'Playfair Display',serif; font-size:1.3rem; font-weight:900; background:linear-gradient(135deg,var(--primary),var(--accent)); -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text; text-decoration:none; white-space:nowrap; }
.navbar-right { display:flex;align-items:center;gap:8px; flex-shrink:0; }
.icon-btn { position:relative; background:var(--card-bg); border:1px solid var(--card-border); color:var(--text); width:40px;height:40px; border-radius:12px; cursor:pointer; display:flex;align-items:center;justify-content:center; font-size:1.1rem; transition:.3s; text-decoration:none; flex-shrink:0; }
.icon-btn:hover { background:var(--primary);border-color:var(--primary);transform:translateY(-2px) }
.badge { position:absolute;top:-6px;right:-6px; background:var(--primary);color:#fff; font-size:.62rem;font-weight:700; min-width:17px;height:17px;border-radius:9px; display:flex;align-items:center;justify-content:center;padding:0 3px; }
.menu-btn { background:none;border:none;color:var(--text);font-size:1.4rem;cursor:pointer;padding:8px;display:flex;align-items:center; }
.theme-btn { background:var(--card-bg);border:1px solid var(--card-border);color:var(--text);padding:6px 12px;border-radius:100px;cursor:pointer;font-size:.8rem;font-weight:600;transition:.3s;white-space:nowrap; }
.theme-btn:hover { border-color:var(--primary);color:var(--primary); }

/* ── DRAWER ── */
.drawer-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:2000;backdrop-filter:blur(4px); }
.drawer-overlay.open { display:block }
.drawer { position:fixed;left:0;top:0;bottom:0;width:min(300px,85vw);background:var(--secondary);border-right:1px solid var(--card-border);z-index:2001;transform:translateX(-100%);transition:transform .35s cubic-bezier(.4,0,.2,1);display:flex;flex-direction:column;overflow-y:auto; }
.drawer.open { transform:translateX(0) }
.drawer-header { padding:28px 20px 18px;border-bottom:1px solid var(--card-border);display:flex;align-items:center;justify-content:space-between; }
.drawer-logo { font-family:'Playfair Display',serif;font-size:1.2rem;font-weight:900;background:linear-gradient(135deg,var(--primary),var(--accent));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text; }
.drawer-close { background:none;border:none;color:var(--text);font-size:1.3rem;cursor:pointer }
.drawer-menu { list-style:none;padding:14px 0;flex:1 }
.drawer-menu li a { display:flex;align-items:center;gap:12px;padding:13px 20px;color:var(--text);text-decoration:none;font-size:.9rem;font-weight:500;transition:.2s;border-left:3px solid transparent; }
.drawer-menu li a:hover { background:var(--card-bg);border-left-color:var(--primary);color:var(--primary);padding-left:26px; }
.drawer-menu li a .icon { font-size:1rem;width:20px;text-align:center }
.drawer-footer { padding:18px 20px;border-top:1px solid var(--card-border);font-size:.72rem;color:rgba(128,128,128,0.6) }

/* ── PANELS (notif / cart) ── */
.notif-panel,.cart-panel { position:fixed;top:calc(var(--nav-h) + 8px);right:12px;width:min(360px,calc(100vw - 24px));background:var(--secondary);border:1px solid var(--card-border);border-radius:var(--radius);z-index:999;display:none;max-height:480px;overflow-y:auto;box-shadow:var(--shadow); }
.notif-panel.open,.cart-panel.open { display:block;animation:slideDown .3s ease }
.panel-header { padding:14px 18px;border-bottom:1px solid var(--card-border);font-weight:700;font-size:.9rem;display:flex;justify-content:space-between;align-items:center }
.notif-item { padding:12px 18px;border-bottom:1px solid var(--card-border);cursor:pointer;transition:.2s }
.notif-item:hover { background:var(--card-bg) }
.notif-item.unread { border-left:3px solid var(--primary) }
.notif-item-title { font-weight:600;font-size:.85rem;margin-bottom:3px }
.notif-item-msg { font-size:.78rem;color:rgba(128,128,128,0.8);line-height:1.4 }
.cart-item { display:flex;align-items:center;gap:12px;padding:12px 18px;border-bottom:1px solid var(--card-border) }
.cart-item img { width:52px;height:52px;border-radius:10px;object-fit:cover }
.cart-item-info { flex:1;min-width:0 }
.cart-item-title { font-size:.82rem;font-weight:600;line-height:1.3;overflow:hidden;text-overflow:ellipsis;white-space:nowrap }
.cart-item-price { font-size:.78rem;color:var(--primary);font-weight:700 }
.cart-remove { background:none;border:none;color:rgba(128,128,128,0.5);cursor:pointer;font-size:1rem;padding:4px;transition:.2s }
.cart-remove:hover { color:#ff4444 }
.cart-footer { padding:14px 18px;display:flex;flex-direction:column;gap:10px }
.cart-total { font-weight:700;font-size:.95rem;display:flex;justify-content:space-between }
.btn-goto-cart { display:block;text-align:center;background:linear-gradient(135deg,var(--primary),#ff8c00);color:#fff;border:none;padding:13px;border-radius:12px;font-weight:700;font-size:.92rem;text-decoration:none;transition:.3s;cursor:pointer; }
.btn-goto-cart:hover { transform:translateY(-2px);box-shadow:0 8px 24px rgba(255,107,53,0.4) }

/* ── CAROUSEL ── */

/* ── CAROUSEL ── */
/* ── CAROUSEL ── */
/* ── CAROUSEL ── */
.carousel-wrap { position:relative;width:calc(100% - 32px);max-width:1240px;margin:calc(var(--nav-h) + 20px) auto 20px;border-radius:20px;overflow:hidden;background:#000;max-height:250px;box-shadow:var(--shadow); }
.carousel-track { display:flex;transition:transform .7s cubic-bezier(.4,0,.2,1); }
.carousel-slide { flex:0 0 100%;position:relative;max-height:250px;overflow:hidden; }
.carousel-slide img { width:100%;height:100%;object-fit:cover;display:block;max-height:250px; }

.carousel-caption { position:absolute;bottom:0;left:0;right:0;padding:40px 32px 32px;background:linear-gradient(to top,rgba(0,0,0,0.8) 0%,transparent 100%);color:#fff; }
.carousel-caption-text { font-family:'Playfair Display',serif;font-size:clamp(1.2rem,3.5vw,2rem);font-weight:700;text-shadow:0 2px 12px rgba(0,0,0,0.8); }
.carousel-prev,.carousel-next { position:absolute;top:50%;transform:translateY(-50%);background:rgba(0,0,0,0.5);border:1px solid rgba(255,255,255,0.2);color:#fff;width:44px;height:44px;border-radius:50%;cursor:pointer;font-size:1.2rem;display:flex;align-items:center;justify-content:center;transition:.2s;z-index:10; }
.carousel-prev { left:16px } .carousel-next { right:16px }
.carousel-prev:hover,.carousel-next:hover { background:var(--primary);border-color:var(--primary) }
.carousel-dots { position:absolute;bottom:14px;left:50%;transform:translateX(-50%);display:flex;gap:8px;z-index:10; }
.carousel-dot { width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,0.4);cursor:pointer;transition:.3s; }
.carousel-dot.active { background:var(--primary);width:24px;border-radius:4px; }

/* ── HERO (no carousel version) ── */
.hero { min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:120px 24px 60px;position:relative; }
.hero-eyebrow { display:inline-flex;align-items:center;gap:8px;background:rgba(255,107,53,0.1);border:1px solid rgba(255,107,53,0.3);border-radius:100px;padding:5px 14px;font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:var(--primary);margin-bottom:22px;animation:fadeInUp .8s ease both; }
.hero h1 { font-family:'Playfair Display',serif;font-size:clamp(2.2rem,7vw,5.5rem);font-weight:900;line-height:1.05;letter-spacing:-2px;margin-bottom:18px;animation:fadeInUp .8s .1s ease both; }
.shimmer-text { background:linear-gradient(90deg,var(--primary),var(--accent),var(--primary));background-size:200%;-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;animation:shimmer 3s linear infinite; }
@keyframes shimmer{0%{background-position:0% 50%}100%{background-position:200% 50%}}
.hero-sub { font-size:clamp(.95rem,2vw,1.15rem);color:rgba(128,128,128,0.9);max-width:540px;margin-bottom:36px;animation:fadeInUp .8s .2s ease both;font-weight:300; }
.hero-cta { display:flex;gap:12px;flex-wrap:wrap;justify-content:center;animation:fadeInUp .8s .3s ease both; }

/* ── BUTTONS ── */
.btn-primary { background:linear-gradient(135deg,var(--primary),#ff8c00);color:#fff;border:none;padding:15px 32px;border-radius:100px;font-weight:700;font-size:.95rem;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:8px;transition:all .3s;box-shadow:0 8px 30px rgba(255,107,53,0.35); }
.btn-primary:hover { transform:translateY(-3px);box-shadow:0 12px 40px rgba(255,107,53,0.5) }
.btn-outline { background:transparent;color:var(--text);border:1px solid var(--card-border);padding:15px 32px;border-radius:100px;font-weight:600;font-size:.95rem;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:8px;transition:all .3s; }
.btn-outline:hover { border-color:var(--primary);color:var(--primary) }

/* ── TRUST STRIP ── */
.trust-strip { display:flex;gap:24px;justify-content:center;flex-wrap:wrap;margin-top:44px;animation:fadeInUp .8s .4s ease both; }
.trust-item { display:flex;align-items:center;gap:7px;font-size:.8rem;color:rgba(128,128,128,0.8);font-weight:500; }

/* ── STATS ── */
.stats-strip { display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin:50px 0; }
.stat-card { background:var(--card-bg);border:1px solid var(--card-border);border-radius:18px;padding:24px 18px;text-align:center; }
.stat-number { font-family:'Playfair Display',serif;font-size:2.2rem;font-weight:900;background:linear-gradient(135deg,var(--primary),var(--accent));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text; }
.stat-label { font-size:.75rem;color:rgba(128,128,128,0.7);font-weight:500;margin-top:4px;text-transform:uppercase;letter-spacing:1px }

/* ── SECTION ── */
.section { padding:56px 0;position:relative;z-index:1 }
.container { max-width:1280px;margin:0 auto;padding:0 20px }
.section-title { font-family:'Playfair Display',serif;font-size:clamp(1.6rem,4vw,2.5rem);font-weight:900;margin-bottom:8px;letter-spacing:-1px; }
.section-sub { color:rgba(128,128,128,0.8);margin-bottom:36px;font-size:.9rem }

/* ── SEARCH ── */
.search-bar { display:flex;background:var(--card-bg);border:1px solid var(--card-border);border-radius:100px;overflow:hidden;margin-bottom:18px;transition:.3s; }
.search-bar:focus-within { border-color:var(--primary);box-shadow:0 0 0 3px rgba(255,107,53,0.12) }
.search-bar input { flex:1;background:none;border:none;outline:none;padding:13px 22px;color:var(--text);font-size:.9rem;font-family:inherit;min-width:0; }
.search-bar input::placeholder { color:rgba(128,128,128,0.4) }
.search-bar button { background:linear-gradient(135deg,var(--primary),#ff8c00);border:none;color:#fff;padding:10px 24px;cursor:pointer;font-weight:700;font-size:.88rem;border-radius:100px;margin:4px;transition:.3s;white-space:nowrap; }
.filters { display:flex;gap:8px;flex-wrap:wrap;margin-bottom:36px }
.filter-chip { padding:7px 16px;border-radius:100px;border:1px solid var(--card-border);background:var(--card-bg);color:rgba(128,128,128,0.8);font-size:.78rem;font-weight:500;cursor:pointer;text-decoration:none;transition:.2s; }
.filter-chip:hover,.filter-chip.active { background:var(--primary);border-color:var(--primary);color:#fff }

/* ── PHOTO GRID ── */
.photo-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:20px; }
.photo-card { background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius);overflow:hidden;cursor:pointer;transition:all .35s cubic-bezier(.4,0,.2,1);text-decoration:none;color:inherit;position:relative;display:block; }
.photo-card:hover { transform:translateY(-7px);border-color:rgba(255,107,53,0.4);box-shadow:0 20px 55px rgba(0,0,0,0.45); }
.photo-card-img-wrap { position:relative;overflow:hidden;aspect-ratio:4/3; }
.photo-card-img-wrap img { width:100%;height:100%;object-fit:cover;transition:transform .5s ease; }
.photo-card:hover .photo-card-img-wrap img { transform:scale(1.07) }
.photo-card-badge { position:absolute;top:10px;left:10px;background:linear-gradient(135deg,var(--primary),#ff8c00);color:#fff;font-size:.67rem;font-weight:700;padding:3px 9px;border-radius:100px;text-transform:uppercase;letter-spacing:1px; }
.photo-card-actions { position:absolute;inset:0;background:rgba(0,0,0,0.45);display:flex;align-items:center;justify-content:center;gap:10px;opacity:0;transition:.3s; }
.photo-card:hover .photo-card-actions { opacity:1 }
.action-btn { background:rgba(255,255,255,0.14);border:1px solid rgba(255,255,255,0.3);backdrop-filter:blur(6px);color:#fff;width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1rem;cursor:pointer;transition:.2s;text-decoration:none; }
.action-btn:hover { background:var(--primary);border-color:var(--primary) }
.photo-card-body { padding:16px }
.photo-card-meta { display:flex;gap:7px;align-items:center;font-size:.7rem;color:rgba(128,128,128,0.6);font-weight:500;margin-bottom:7px;text-transform:uppercase;letter-spacing:.5px; }
.photo-card-title { font-family:'Playfair Display',serif;font-size:1rem;font-weight:700;margin-bottom:10px;line-height:1.3;transition:.2s; }
.photo-card:hover .photo-card-title { color:var(--primary) }
.photo-card-pricing { display:flex;align-items:center;gap:8px;flex-wrap:wrap; }
.price-current { font-size:1.1rem;font-weight:800;color:var(--primary) }
.price-original { font-size:.82rem;color:rgba(128,128,128,0.5);text-decoration:line-through }
.price-discount { background:rgba(255,215,0,0.15);color:var(--accent);font-size:.7rem;font-weight:700;padding:2px 7px;border-radius:100px; }

/* ── DETAIL PAGE ── */
.photo-detail { padding:calc(var(--nav-h) + 32px) 0 60px }
.detail-grid { display:grid;grid-template-columns:1fr 1fr;gap:36px;align-items:start }
.detail-image-wrap { position:relative;border-radius:24px;overflow:hidden;box-shadow:0 28px 70px rgba(0,0,0,0.55) }
.detail-image-wrap img { width:100%;display:block }
.detail-meta { display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px }
.detail-tag { padding:3px 11px;border-radius:100px;background:rgba(255,107,53,0.1);border:1px solid rgba(255,107,53,0.3);font-size:.72rem;color:var(--primary);font-weight:600; }
.detail-title { font-family:'Playfair Display',serif;font-size:clamp(1.6rem,4vw,2.4rem);font-weight:900;letter-spacing:-1px;margin-bottom:14px;line-height:1.1; }
.detail-description { color:rgba(128,128,128,0.9);margin-bottom:24px;line-height:1.7;font-size:.93rem; }
.detail-pricing { display:flex;align-items:center;gap:14px;margin-bottom:24px;flex-wrap:wrap; }
.detail-price { font-size:2rem;font-weight:800;color:var(--primary) }
.detail-original { font-size:1rem;text-decoration:line-through;color:rgba(128,128,128,0.4) }
.detail-save { background:rgba(255,215,0,0.12);border:1px solid rgba(255,215,0,0.3);color:var(--accent);padding:5px 12px;border-radius:100px;font-size:.78rem;font-weight:700; }
.detail-actions { display:flex;gap:10px;flex-wrap:wrap;margin-bottom:24px }
.btn-buy { flex:1;min-width:160px;background:linear-gradient(135deg,var(--primary),#ff8c00);color:#fff;border:none;padding:15px 24px;border-radius:14px;font-weight:700;font-size:.95rem;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:.3s;box-shadow:0 8px 28px rgba(255,107,53,0.32); }
.btn-buy:hover { transform:translateY(-3px);box-shadow:0 12px 38px rgba(255,107,53,0.48) }
.btn-cart-add { background:var(--card-bg);color:var(--text);border:1px solid var(--card-border);padding:15px 24px;border-radius:14px;font-weight:600;font-size:.95rem;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:.3s; }
.btn-cart-add:hover { border-color:var(--primary);color:var(--primary) }
.detail-specs { background:var(--card-bg);border:1px solid var(--card-border);border-radius:14px;padding:18px;margin-bottom:20px; }
.detail-spec-row { display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--card-border);font-size:.85rem; }
.detail-spec-row:last-child { border-bottom:none }
.detail-spec-label { color:rgba(128,128,128,0.6);font-weight:500 }
.detail-spec-value { font-weight:600 }

/* ── REVIEWS ── */
.reviews-section { margin-top:52px }
.review-card { background:var(--card-bg);border:1px solid var(--card-border);border-radius:14px;padding:18px;margin-bottom:14px; }
.review-header { display:flex;justify-content:space-between;align-items:center;margin-bottom:9px }
.review-name { font-weight:700;font-size:.88rem }
.review-stars { color:var(--accent);font-size:.88rem;letter-spacing:2px }
.review-text { font-size:.85rem;color:rgba(128,128,128,0.85);line-height:1.6 }

/* ── FAQ ── */
.faq-section { padding:72px 0 }
.faq-item { border:1px solid var(--card-border);border-radius:14px;overflow:hidden;margin-bottom:10px;background:var(--card-bg); }
.faq-question { padding:18px 22px;cursor:pointer;font-weight:600;font-size:.92rem;display:flex;justify-content:space-between;align-items:center;transition:.2s;user-select:none; }
.faq-question:hover { color:var(--primary) }
.faq-chevron { transition:transform .3s;font-size:.95rem;color:rgba(128,128,128,0.5) }
.faq-item.open .faq-chevron { transform:rotate(180deg);color:var(--primary) }
.faq-answer { max-height:0;overflow:hidden;transition:max-height .4s ease,padding .3s;font-size:.86rem;color:rgba(128,128,128,0.85);line-height:1.7;padding:0 22px; }
.faq-item.open .faq-answer { max-height:300px;padding:0 22px 18px }

/* ── SUPPORT ── */
.support-box { background:linear-gradient(135deg,rgba(255,107,53,0.08),rgba(255,215,0,0.04));border:1px solid rgba(255,107,53,0.22);border-radius:24px;padding:44px 36px;text-align:center; }
.support-icon { font-size:3.2rem;margin-bottom:14px;display:block;animation:float 3s ease-in-out infinite }
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-9px)}}
.support-title { font-family:'Playfair Display',serif;font-size:1.7rem;font-weight:900;margin-bottom:10px }
.support-sub { color:rgba(128,128,128,0.85);margin-bottom:24px;font-size:.92rem }
.btn-telegram { display:inline-flex;align-items:center;gap:9px;background:linear-gradient(135deg,#229ED9,#006fb8);color:#fff;border:none;padding:15px 32px;border-radius:100px;font-weight:700;font-size:.95rem;cursor:pointer;text-decoration:none;transition:.3s;box-shadow:0 8px 24px rgba(34,158,217,0.32); }
.btn-telegram:hover { transform:translateY(-3px);box-shadow:0 12px 34px rgba(34,158,217,0.48) }

/* ── FOOTER ── */
footer { border-top:1px solid var(--card-border);padding:36px 0;margin-top:52px;text-align:center; }
.footer-links { display:flex;gap:20px;justify-content:center;flex-wrap:wrap;margin-bottom:16px }
.footer-links a { color:rgba(128,128,128,0.6);text-decoration:none;font-size:.82rem;transition:.2s }
.footer-links a:hover { color:var(--primary) }
.footer-copy { font-size:.75rem;color:rgba(128,128,128,0.38) }

/* ── BOTTOM SHEET (Guide) ── */
.sheet-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:3000;align-items:flex-end;justify-content:center;backdrop-filter:blur(4px); }
.sheet-overlay.open { display:flex;animation:fadeIn .2s ease }
.bottom-sheet { width:100%;max-width:480px;background:var(--secondary);border-radius:24px 24px 0 0;max-height:88vh;overflow-y:auto;animation:slideUp .35s cubic-bezier(.4,0,.2,1); }
.sheet-handle { width:38px;height:4px;background:rgba(128,128,128,0.25);border-radius:2px;margin:14px auto 0 }
.sheet-header { padding:14px 22px;border-bottom:1px solid var(--card-border);display:flex;justify-content:space-between;align-items:center }
.sheet-title { font-weight:700;font-size:1rem }
.sheet-close { background:none;border:none;color:rgba(128,128,128,0.5);font-size:1.3rem;cursor:pointer }
.sheet-body { padding:18px 22px 32px }
.sheet-body img { width:100%;border-radius:12px;display:block }

/* ── TOAST ── */
.toast-container { position:fixed;bottom:22px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:9px;max-width:calc(100vw - 40px); }
.toast { background:var(--secondary);border:1px solid var(--card-border);border-radius:12px;padding:13px 18px;min-width:240px;box-shadow:0 10px 36px rgba(0,0,0,0.45);display:flex;align-items:center;gap:10px;animation:slideInRight .3s ease;font-size:.85rem;font-weight:500; }
.toast.success { border-left:3px solid #22c55e }
.toast.error   { border-left:3px solid #ef4444 }
.toast.info    { border-left:3px solid var(--primary) }

/* ── PAGE 404 ── */
.page-404 { min-height:80vh;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:40px }
.page-404 h2 { font-family:'Playfair Display',serif;font-size:4rem;font-weight:900;color:var(--primary);margin-bottom:14px }

/* ── REVEAL ANIMATIONS ── */
.reveal { opacity:0;transform:translateY(28px);transition:opacity .6s ease,transform .6s ease }
.reveal.visible { opacity:1;transform:translateY(0) }
@keyframes fadeInUp { from{opacity:0;transform:translateY(22px)}to{opacity:1;transform:translateY(0)} }
@keyframes slideDown { from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)} }
@keyframes slideUp { from{transform:translateY(100%)}to{transform:translateY(0)} }
@keyframes slideInRight { from{opacity:0;transform:translateX(28px)}to{opacity:1;transform:translateX(0)} }
@keyframes fadeIn { from{opacity:0}to{opacity:1} }

/* ── RESPONSIVE ── */
@media(max-width:900px) {
  .detail-grid { grid-template-columns:1fr }
  .detail-actions { flex-direction:column }
  .btn-buy,.btn-cart-add { min-width:unset }
}
@media(max-width:600px) {
  .photo-grid { grid-template-columns:repeat(2,1fr);gap:12px }
  .stats-strip { grid-template-columns:repeat(2,1fr) }
  .hero { padding:90px 16px 40px }
  .support-box { padding:28px 18px }
  .trust-strip { gap:14px }
  .navbar-brand { font-size:1.1rem }
}
@media(max-width:380px) {
  .photo-grid { grid-template-columns:1fr }
}
</style>
</head>
<body id="body" class="day-mode">
<!-- ── NAVBAR ── -->
<nav class="navbar">
  <button class="menu-btn" onclick="toggleDrawer()" aria-label="Menu">☰</button>
  <a href="index.php" class="navbar-brand">📸 <?= e($siteName) ?></a>
  <div class="navbar-right">
    <button class="theme-btn" onclick="toggleTheme()" id="theme-btn" title="Toggle Day/Night">☀️ Day</button>
    <button class="icon-btn" onclick="toggleNotif()" title="Notifications" aria-label="Notifications">
      🔔<span class="badge" id="notif-badge" style="display:<?= $notifCount>0?'flex':'none' ?>"><?= $notifCount ?></span>
    </button>
    <a href="cart.php" class="icon-btn" title="Cart" aria-label="Cart" style="text-decoration:none">
      🛒<span class="badge" id="cart-badge"><?= $cartCnt ?></span>
    </a>
  </div>
</nav>

<!-- ── NOTIFICATIONS PANEL ── -->
<div class="notif-panel" id="notif-panel">
  <div class="panel-header">🔔 Notifications <button onclick="closeAll()" style="background:none;border:none;color:rgba(128,128,128,0.5);cursor:pointer">✕</button></div>
  <div id="notif-list"><div style="padding:20px;text-align:center;color:rgba(128,128,128,0.5)">Loading...</div></div>
</div>

<!-- ── DRAWER ── -->
<div class="drawer-overlay" id="drawer-overlay" onclick="toggleDrawer()"></div>
<nav class="drawer" id="drawer">
  <div class="drawer-header">
    <span class="drawer-logo">📸 <?= e($siteName) ?></span>
    <button class="drawer-close" onclick="toggleDrawer()">✕</button>
  </div>
  <ul class="drawer-menu">
    <li><a href="index.php"><span class="icon">🏠</span> Home</a></li>
    <li><a href="cart.php"><span class="icon">🛒</span> Cart</a></li>
    <li><a href="index.php?page=privacy"><span class="icon">🔒</span> Privacy Policy</a></li>
    <li><a href="index.php?page=terms"><span class="icon">📜</span> Terms & Conditions</a></li>
    <li><a href="<?= e($s['instagram_url']??'#') ?>" target="_blank"><span class="icon">📸</span> Instagram</a></li>
    <li><a href="<?= e($s['youtube_url']??'#') ?>" target="_blank"><span class="icon">▶️</span> YouTube</a></li>
    <li><a href="<?= e($s['facebook_url']??'#') ?>" target="_blank"><span class="icon">👍</span> Facebook</a></li>
    <li><a href="<?= e($s['twitter_url']??'#') ?>" target="_blank"><span class="icon">🐦</span> X (Twitter)</a></li>
    <li><a href="<?= e($telegramUrl) ?>" target="_blank"><span class="icon">✈️</span> Telegram Support</a></li>
  </ul>
  <div class="drawer-footer">© <?= e($s['copyright_year']??'2026') ?> <?= e($s['copyright_name']??'Dr.Dev or Dr.Hamza') ?></div>
</nav>

<!-- ── GUIDE BOTTOM SHEET ── -->
<div class="sheet-overlay" id="guide-sheet" onclick="closeGuide(event)">
  <div class="bottom-sheet">
    <div class="sheet-handle"></div>
    <div class="sheet-header">
      <span class="sheet-title">📖 How to Find Your UTR Number</span>
      <button class="sheet-close" onclick="closeGuideBtn()">✕</button>
    </div>
    <div class="sheet-body">
      <p style="color:rgba(128,128,128,0.6);font-size:.82rem;margin-bottom:14px">Scroll down to see guides for all payment apps</p>
      <img src="<?= e($s['guide_image_url']??'') ?>" alt="UTR Guide" onerror="this.src='https://placehold.co/400x800/1A1A2E/FF6B35?text=UTR+Guide'">
    </div>
  </div>
</div>

<!-- ── TOAST CONTAINER ── -->
<div class="toast-container" id="toast-container"></div>

<main style="position:relative;z-index:1">

<?php if ($page === 'home'): ?>
<!-- ══════════════ HOME ══════════════ -->

<!-- CAROUSEL -->
<?php if (!empty($slides)): ?>
<div class="carousel-wrap" id="carousel">
  <div class="carousel-track" id="carousel-track">
    <?php foreach ($slides as $sl): ?>
    <div class="carousel-slide">
      <img src="<?= e($sl['image_url']) ?>" alt="<?= e($sl['caption']) ?>" loading="lazy" onerror="this.src='https://placehold.co/1400x600/1A1A2E/FF6B35?text=Slide'">
      <div class="carousel-caption">
        <div class="carousel-caption-text"><?= e($sl['caption']) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <button class="carousel-prev" onclick="carouselPrev()" aria-label="Previous">&#8592;</button>
  <button class="carousel-next" onclick="carouselNext()" aria-label="Next">&#8594;</button>
  <div class="carousel-dots" id="carousel-dots">
    <?php foreach ($slides as $i => $sl): ?>
    <div class="carousel-dot <?= $i===0?'active':'' ?>" onclick="carouselGoTo(<?= $i ?>)"></div>
    <?php endforeach; ?>
  </div>
</div>
<?php else: ?>
<!-- Hero fallback if no slides -->
<section class="hero">
  <div class="hero-eyebrow">✨ 100% Trusted Seller</div>
  <h1>Premium Cards<br><span class="shimmer-text">Delivered Fast</span></h1>
  <p class="hero-sub">Curated collection of stunning cards from around the world. Buy & receive via Telegram after verified payment.</p>
  <div class="hero-cta">
    <a href="#gallery" class="btn-primary">🖼️ Browse Cards</a>
    <a href="<?= e($telegramUrl) ?>" target="_blank" class="btn-outline">✈️ Support</a>
  </div>
  <div class="trust-strip">
    <div class="trust-item">✅ Manual Verification</div>
    <div class="trust-item">🔒 100% Secure UPI</div>
    <div class="trust-item">⚡ Fast Delivery</div>
    <div class="trust-item">📸 HD Quality</div>
  </div>
</section>
<?php endif; ?>

<!-- Stats -->

<!-- Gallery -->
<section class="section" id="gallery">
  <div class="container">
    <div class="section-title reveal">📸 Premium Cards</div>
    <p class="section-sub reveal">Click any cards to view details and purchase</p>

    <form method="get" action="index.php" style="margin-bottom:16px">
      <div class="search-bar">
        <input type="text" name="q" placeholder="Search by name, country, style..." value="<?= e($search) ?>">
        <button type="submit">Search</button>
      </div>
    </form>
    <div class="filters reveal">
      <a href="index.php" class="filter-chip <?= (!$filter_type&&!$filter_country)?'active':'' ?>">All</a>
      <?php foreach ($types as $t): ?><a href="index.php?type=<?= urlencode($t) ?>" class="filter-chip <?= $filter_type===$t?'active':'' ?>"><?= e($t) ?></a><?php endforeach; ?>
      <?php foreach ($countries as $c): ?><a href="index.php?country=<?= urlencode($c) ?>" class="filter-chip <?= $filter_country===$c?'active':'' ?>">🌍 <?= e($c) ?></a><?php endforeach; ?>
    </div>

    <?php if (empty($photos)): ?>
    <div style="text-align:center;padding:56px;color:rgba(128,128,128,0.5)">
      <div style="font-size:3rem;margin-bottom:14px">🔍</div>
      <div>No cards found. Try a different search.</div>
    </div>
    <?php else: ?>
    <div class="photo-grid">
      <?php foreach ($photos as $ph):
        $disc = $ph['actual_price']>0 ? round((1-$ph['price']/$ph['actual_price'])*100) : 0;
      ?>
      <a href="index.php?photo=<?= e($ph['slug']) ?>" class="photo-card reveal" data-id="<?= $ph['id'] ?>">
        <div class="photo-card-img-wrap">
          <img src="<?= e($ph['thumbnail_url']?:$ph['image_url']) ?>" alt="<?= e($ph['title']) ?>" loading="lazy" onerror="this.src='https://placehold.co/400x300/1A1A2E/FF6B35?text=Photo'">
          <?php if ($ph['featured']): ?><div class="photo-card-badge">⭐ Featured</div><?php endif; ?>
          <div class="photo-card-actions">
            <div class="action-btn" onclick="addToCart(event,<?= $ph['id'] ?>)" title="Add to Cart">🛒</div>
            <div class="action-btn" title="View">👁️</div>
          </div>
        </div>
        <div class="photo-card-body">
          <div class="photo-card-meta"><span>🌍 <?= e($ph['country']) ?></span><span>✨ <?= e($ph['type']) ?></span></div>
          <div class="photo-card-title"><?= e($ph['title']) ?></div>
          <div class="photo-card-pricing">
            <span class="price-current">₹<?= number_format($ph['price'],2) ?></span>
            <?php if ($ph['actual_price']>$ph['price']): ?><span class="price-original">₹<?= number_format($ph['actual_price'],2) ?></span><?php if ($disc>0): ?><span class="price-discount"><?= $disc ?>% OFF</span><?php endif; ?><?php endif; ?>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- FAQ -->
<section class="faq-section">
  <div class="container">
    <div class="section-title reveal">❓ Frequently Asked Questions</div>
    <p class="section-sub reveal">Everything you need to know before purchasing</p>
    <?php for ($i=1;$i<=6;$i++): $q=$s["faq_{$i}_q"]??''; $a=$s["faq_{$i}_a"]??''; if(!$q) continue; ?>
    <div class="faq-item reveal" onclick="toggleFaq(this)">
      <div class="faq-question"><?= e($q) ?><span class="faq-chevron">▾</span></div>
      <div class="faq-answer"><?= e($a) ?></div>
    </div>
        <?php endfor; ?>
  </div>
</section>

<div class="container reveal">
  <div class="stats-strip">
    <div class="stat-card"><div class="stat-number">500+</div><div class="stat-label">Photos Available</div></div>
    <div class="stat-card"><div class="stat-number">10K+</div><div class="stat-label">Happy Customers</div></div>
    <div class="stat-card"><div class="stat-number">4.9★</div><div class="stat-label">Avg Rating</div></div>
    <div class="stat-card"><div class="stat-number">100%</div><div class="stat-label">Trusted Seller</div></div>
  </div>
</div>

<section class="section">
  <div class="container reveal">
    <div class="support-box">


<!-- Support -->
<section class="section">
  <div class="container reveal">
    <div class="support-box">
      <span class="support-icon">✈️</span>
      <div class="support-title">Need Help? We're on Telegram</div>
      <div class="support-sub">Payment successful but didn't receive your photo? Contact us with your UTR number. Response within 1-24 hours.</div>
      <a href="<?= e($telegramUrl) ?>" target="_blank" class="btn-telegram">✈️ Contact Support on Telegram</a>
    </div>
  </div>
</section>

<?php elseif ($page === 'photo' && $photo): ?>
<!-- ══════════════ PHOTO DETAIL ══════════════ -->
<?php $disc = $photo['actual_price']>0 ? round((1-$photo['price']/$photo['actual_price'])*100) : 0;
      $avgRating = count($reviews)>0 ? round(array_sum(array_column($reviews,'rating'))/count($reviews),1) : 5.0; ?>
<div class="photo-detail">
  <div class="container">
    <div style="margin-bottom:18px;font-size:.82rem;color:rgba(128,128,128,0.5)">
      <a href="index.php" style="color:var(--primary);text-decoration:none">Home</a> / <?= e($photo['title']) ?>
    </div>
    <div class="detail-grid">
      <div class="reveal">
        <div class="detail-image-wrap">
          <img src="<?= e($photo['image_url']) ?>" alt="<?= e($photo['title']) ?>" onerror="this.src='https://placehold.co/800x600/1A1A2E/FF6B35?text=Photo'">
        </div>
      </div>
      <div class="reveal">
        <div class="detail-meta">
          <span class="detail-tag">🌍 <?= e($photo['country']) ?></span>
          <span class="detail-tag">✨ <?= e($photo['type']) ?></span>
          <?php if ($photo['tags']): foreach (explode(',', $photo['tags']) as $tag): if (trim($tag)): ?><span class="detail-tag"><?= e(trim($tag)) ?></span><?php endif; endforeach; endif; ?>
        </div>
        <h1 class="detail-title"><?= e($photo['title']) ?></h1>
        <p class="detail-description"><?= nl2br(e($photo['description'])) ?></p>
        <div class="detail-pricing">
          <div class="detail-price">₹<?= number_format($photo['price'],2) ?></div>
          <?php if ($photo['actual_price']>$photo['price']): ?><div class="detail-original">₹<?= number_format($photo['actual_price'],2) ?></div><div class="detail-save"><?= $disc ?>% OFF</div><?php endif; ?>
        </div>
        <?php if (count($reviews)>0): ?><div style="display:flex;align-items:center;gap:8px;margin-bottom:18px"><span style="color:var(--accent);letter-spacing:2px"><?= str_repeat('★',floor($avgRating)) ?></span><span style="font-size:.82rem;color:rgba(128,128,128,0.6)"><?= $avgRating ?> (<?= count($reviews) ?> reviews)</span></div><?php endif; ?>
        <div class="detail-actions">
          <button class="btn-buy" onclick="buyNow(<?= $photo['id'] ?>, '<?= e(addslashes($photo['title'])) ?>')">⚡ Purchase Now</button>
          <button class="btn-cart-add" onclick="addToCart(null,<?= $photo['id'] ?>)">🛒 Add to Cart</button>
        </div>
        <div class="detail-specs">
          <div class="detail-spec-row"><span class="detail-spec-label">Country</span><span class="detail-spec-value"><?= e($photo['country']) ?></span></div>
          <div class="detail-spec-row"><span class="detail-spec-label">Style</span><span class="detail-spec-value"><?= e($photo['type']) ?></span></div>
          <div class="detail-spec-row"><span class="detail-spec-label">Price</span><span class="detail-spec-value" style="color:var(--primary)">₹<?= number_format($photo['price'],2) ?></span></div>
          <div class="detail-spec-row"><span class="detail-spec-label">Downloads</span><span class="detail-spec-value"><?= number_format($photo['downloads']) ?></span></div>
          <div class="detail-spec-row"><span class="detail-spec-label">Delivery</span><span class="detail-spec-value">Via Telegram</span></div>
        </div>
        <div style="background:rgba(255,107,53,0.07);border:1px solid rgba(255,107,53,0.18);border-radius:12px;padding:12px 15px;font-size:.8rem;color:rgba(128,128,128,0.7)">🔒 Payment secured by UPI. Manual verification. Delivered via Telegram after confirmation.</div>
      </div>
    </div>
    <?php if (!empty($reviews)): ?>
    <div class="reviews-section reveal">
      <div class="section-title" style="font-size:1.5rem;margin-bottom:20px">💬 Customer Reviews</div>
      <?php foreach ($reviews as $rv): ?>
      <div class="review-card">
        <div class="review-header"><div class="review-name">👤 <?= e($rv['reviewer_name']) ?></div><div class="review-stars"><?= str_repeat('★',(int)$rv['rating']) ?></div></div>
        <div class="review-text"><?= e($rv['comment']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php if (!empty($related)): ?>
<section style="padding:48px 0">
  <div class="container">
    <div class="section-title reveal" style="font-size:1.5rem;margin-bottom:20px">🖼️ Related Photos</div>
    <div class="photo-grid">
      <?php foreach ($related as $rel): ?><a href="index.php?photo=<?= e($rel['slug']) ?>" class="photo-card reveal"><div class="photo-card-img-wrap"><img src="<?= e($rel['thumbnail_url']?:$rel['image_url']) ?>" alt="<?= e($rel['title']) ?>" loading="lazy"></div><div class="photo-card-body"><div class="photo-card-title"><?= e($rel['title']) ?></div><div class="photo-card-pricing"><span class="price-current">₹<?= number_format($rel['price'],2) ?></span><?php if ($rel['actual_price']>$rel['price']): ?><span class="price-original">₹<?= number_format($rel['actual_price'],2) ?></span><?php endif; ?></div></div></a><?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php elseif ($page==='privacy'): ?>
<div class="container" style="padding-top:calc(var(--nav-h)+32px);padding-bottom:60px;max-width:760px">
  <div class="section-title">🔒 Privacy Policy</div>
  <p style="color:rgba(128,128,128,0.8);margin-top:18px;line-height:1.85"><?= nl2br(e($s['privacy_policy']??'')) ?></p>
</div>

<?php elseif ($page==='terms'): ?>
<div class="container" style="padding-top:calc(var(--nav-h)+32px);padding-bottom:60px;max-width:760px">
  <div class="section-title">📜 Terms & Conditions</div>
  <p style="color:rgba(128,128,128,0.8);margin-top:18px;line-height:1.85"><?= nl2br(e($s['terms_conditions']??'')) ?></p>
</div>

<?php else: ?>
<div class="page-404"><h2>404</h2><p style="color:rgba(128,128,128,0.6);margin-bottom:22px">Page not found</p><a href="index.php" class="btn-primary">🏠 Go Home</a></div>
<?php endif; ?>
</main>

<footer>
  <div class="container">
    <div class="footer-links">
      <a href="index.php">Home</a>
      <a href="cart.php">Cart</a>
      <a href="index.php?page=privacy">Privacy Policy</a>
      <a href="index.php?page=terms">Terms & Conditions</a>
      <a href="<?= e($telegramUrl) ?>" target="_blank">Support</a>
    </div>
    <div class="footer-copy">© <?= e($s['copyright_year']??'2026') ?> <?= e($s['copyright_name']??'Dr.Dev or Dr.Hamza') ?> — <?= e($siteName) ?>. All rights reserved.</div>
  </div>
</footer>

<script>
const BASE='index.php';

// ── Theme ─────────────────────────────────────────────
// ── Theme ─────────────────────────────────────────────
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


// ── Drawer ────────────────────────────────────────────
function toggleDrawer(){
  document.getElementById('drawer').classList.toggle('open');
  document.getElementById('drawer-overlay').classList.toggle('open');
  document.body.style.overflow=document.getElementById('drawer').classList.contains('open')?'hidden':'';
}

// ── Notifications ─────────────────────────────────────
let notifOpen=false;
function toggleNotif(){
  closeAll();
  if(!notifOpen){notifOpen=true;loadNotifs();document.getElementById('notif-panel').classList.add('open');}
}
function loadNotifs(){
  fetch(BASE+'?ajax=notifications').then(r=>r.json()).then(data=>{
    const el=document.getElementById('notif-list');
    if(!data.length){el.innerHTML='<div style="padding:20px;text-align:center;color:rgba(128,128,128,0.5)">No notifications</div>';return;}
    el.innerHTML=data.map(n=>`<div class="notif-item ${n.is_read=='0'?'unread':''}" onclick="markRead(${n.id},this)"><div class="notif-item-title">${n.title}</div><div class="notif-item-msg">${n.message}</div></div>`).join('');
  }).catch(()=>{});
}
function markRead(id,el){
  fetch(BASE+'?ajax=mark_notif_read&id='+id);
  el.classList.remove('unread');
  const cnt=document.querySelectorAll('.notif-item.unread').length;
  const b=document.getElementById('notif-badge');
  b.textContent=cnt;b.style.display=cnt>0?'flex':'none';
}

// ── Cart ─────────────────────────────────────────────
function addToCart(e,id){
  if(e){e.preventDefault();e.stopPropagation();}
  fetch(BASE+'?ajax=add_cart&photo_id='+id).then(r=>r.json()).then(d=>{
    document.getElementById('cart-badge').textContent=d.count;
    showToast('Added to cart! 🛒 ','success');
    let cart=JSON.parse(localStorage.getItem('ps_cart')||'[]');
    if(!cart.includes(id)) cart.push(id);
    localStorage.setItem('ps_cart',JSON.stringify(cart));
    // Redirect to cart.php after brief delay
    setTimeout(()=>window.location.href='cart.php',900);
  }).catch(()=>{});
}
function buyNow(id){
  // Track purchase click
  fetch(BASE+'?ajax=track&event=purchase_click&photo_id='+id).catch(()=>{});
  window.location.href='payment.php?photo_id='+id;
}
function closeAll(){
  notifOpen=false;
  document.getElementById('notif-panel').classList.remove('open');
}

// ── FAQ ───────────────────────────────────────────────
function toggleFaq(el){el.classList.toggle('open');}

// ── Guide ─────────────────────────────────────────────
function openGuide(){document.getElementById('guide-sheet').classList.add('open');}
function closeGuide(e){if(e.target===document.getElementById('guide-sheet'))closeGuideBtn();}
function closeGuideBtn(){document.getElementById('guide-sheet').classList.remove('open');}

// ── Toast ─────────────────────────────────────────────
function showToast(msg,type='info'){
  const t=document.createElement('div');t.className='toast '+type;t.innerHTML=msg;
  document.getElementById('toast-container').appendChild(t);
  setTimeout(()=>t.remove(),3000);
}

// ── Scroll Reveal ─────────────────────────────────────
const io=new IntersectionObserver(entries=>{entries.forEach(e=>{if(e.isIntersecting){e.target.classList.add('visible');io.unobserve(e.target);}});},{threshold:.08});
document.querySelectorAll('.reveal').forEach(el=>io.observe(el));

// Close on outside click
document.addEventListener('click',e=>{
  if(!e.target.closest('.notif-panel')&&!e.target.closest('#notif-badge')&&!e.target.closest('[onclick*="toggleNotif"]'))closeAll();
});

// ── CAROUSEL ──────────────────────────────────────────
<?php if ($page==='home' && !empty($slides)): ?>
let carouselIdx=0;
const carouselTotal=<?= count($slides) ?>;
const track=document.getElementById('carousel-track');
const dots=document.querySelectorAll('.carousel-dot');
let carouselInterval=setInterval(carouselNext,1500);

function carouselGoTo(n){
  carouselIdx=(n+carouselTotal)%carouselTotal;
  track.style.transform='translateX(-'+carouselIdx*100+'%)';
  dots.forEach((d,i)=>{d.classList.toggle('active',i===carouselIdx);});
}
function carouselNext(){carouselGoTo(carouselIdx+1);}
function carouselPrev(){carouselGoTo(carouselIdx-1);}

// Reset interval on manual click
['carousel-prev','carousel-next'].forEach(id=>{
  const btn=document.getElementById ? document.querySelector('.'+id.split('-').join('-')) : null;
});
document.querySelector('.carousel-prev')?.addEventListener('click',()=>{clearInterval(carouselInterval);carouselInterval=setInterval(carouselNext,1500);});
document.querySelector('.carousel-next')?.addEventListener('click',()=>{clearInterval(carouselInterval);carouselInterval=setInterval(carouselNext,1500);});

// Touch/swipe support for carousel
let touchStartX=0;
const carouselEl=document.getElementById('carousel');
carouselEl.addEventListener('touchstart',e=>{touchStartX=e.touches[0].clientX;},{passive:true});
carouselEl.addEventListener('touchend',e=>{
  const dx=e.changedTouches[0].clientX-touchStartX;
  if(Math.abs(dx)>50){clearInterval(carouselInterval);dx<0?carouselNext():carouselPrev();carouselInterval=setInterval(carouselNext,1500);}
},{passive:true});
<?php endif; ?>

// ── Visitor screen dimensions (logged via hidden request) ─────────────────
(function(){
  const sw=screen.width,sh=screen.height,vw=window.innerWidth,vh=window.innerHeight;
  // Send to a lightweight endpoint
  fetch(BASE+'?ajax=log_screen&sw='+sw+'&sh='+sh+'&vw='+vw+'&vh='+vh).catch(()=>{});
})();
</script>
</body>
</html>
