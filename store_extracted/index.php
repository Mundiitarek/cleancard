<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

try {
    // إصلاح مشكلة Session - نضعها في أول الملف
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/functions.php';
    
    // بدلاً من استدعاء startSession() مباشرة، نستخدم الدالة المعدلة
    if (function_exists('startSession')) {
        startSession();
    } else {
        // إذا الدالة غير موجودة، نبدأ session عادية
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    $_isAr     = function_exists('isAr') ? isAr() : false;
    $_siteName = function_exists('siteName') ? siteName() : 'التصفية العالمية';
    $_settings = function_exists('getAllSettings') ? getAllSettings() : [];
    $_cats     = function_exists('getCategories') ? getCategories() : [];
    $_cartCount = function_exists('getCartCount') ? getCartCount() : 0;
    $_wishCount = (function_exists('isLoggedIn') && isLoggedIn() && function_exists('getWishlistCount')) ? getWishlistCount() : 0;
    $_user     = function_exists('currentUser') ? currentUser() : null;
    
    // جلب المنتجات المميزة
    $featured = [];
    if (function_exists('getProducts')) {
        $featured = getProducts(['is_featured' => true, 'limit' => 8]);
    }
    
    // جلب منتجات التخفيضات
    $saleProds = [];
    if (function_exists('dbFetchAll')) {
        try {
            $saleProds = dbFetchAll("SELECT * FROM products WHERE sale_price IS NOT NULL AND sale_price>0 AND sale_price<price AND is_active=1 ORDER BY (price-sale_price)/price DESC LIMIT 8");
        } catch (Exception $e) {
            $saleProds = [];
        }
    }
    
    $pageTitle = 'الرئيسية';
    $_csrf = function_exists('generateCsrfToken') ? generateCsrfToken() : md5(uniqid());
    
    // محاولة جلب البراندات بطريقة آمنة
    $brands = [];
    if (function_exists('dbFetchAll')) {
        try {
            $checkBrandColumn = dbFetchOne("SHOW COLUMNS FROM products LIKE 'brand'");
            if ($checkBrandColumn) {
                $brands = dbFetchAll("SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND brand!='' AND is_active=1 ORDER BY brand LIMIT 8");
            }
        } catch (Exception $e) {
            $brands = [];
        }
    }
    
    if (empty($brands)) {
        $brands = [
            ['brand' => 'ADIDAS'],
            ['brand' => 'SKECHERS'],
            ['brand' => 'NIKE'],
            ['brand' => 'PUMA'],
            ['brand' => 'ZARA'],
            ['brand' => 'H&M']
        ];
    }
    
    $catIcons = ['👟','👕','👜','⌚','🌸'];
    $catNames = ['الأحذية','الملابس','الشنط','الاكسسوارات','العطور'];
    
} catch (Exception $e) {
    echo "<div style='background:#f8d7da;color:#721c24;padding:20px;margin:20px;border-radius:5px;'>";
    echo "<h3>خطأ: " . $e->getMessage() . "</h3>";
    echo "<p>في الملف: " . $e->getFile() . " خط " . $e->getLine() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
    exit;
} catch (Error $e) {
    echo "<div style='background:#f8d7da;color:#721c24;padding:20px;margin:20px;border-radius:5px;'>";
    echo "<h3>خطأ PHP: " . $e->getMessage() . "</h3>";
    echo "<p>في الملف: " . $e->getFile() . " خط " . $e->getLine() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5, user-scalable=yes">
<title><?= htmlspecialchars($pageTitle).' — '.htmlspecialchars($_siteName) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">
<!-- Swiper CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
<style>
/* ══════════════════════════════════
   THEME VARIABLES
══════════════════════════════════ */
:root {
  --red:        #C8102E;
  --red-hover:  #a00c24;
  --red-dim:    rgba(200,16,46,0.10);
  --red-5:      rgba(200,16,46,0.05);
  --red-10:     rgba(200,16,46,0.10);

  --bg:         #f4f4f4;
  --bg2:        #ffffff;
  --dark-sect:  #eeeeee;
  --card:       #ffffff;
  --card2:      #f8f8f8;
  --border:     #e2e2e2;
  --border2:    #cccccc;
  --text:       #111111;
  --text2:      #444444;
  --muted:      #888888;
  --nav-bg:     rgba(255,255,255,0.97);
  --shadow:     rgba(0,0,0,0.07);
  --shadow2:    rgba(0,0,0,0.14);

  --hero-bg:    linear-gradient(135deg,#fff5f6 0%,#f9f9f9 50%,#fff0f2 100%);
  --hero-radial1: rgba(200,16,46,0.08);
  --hero-radial2: rgba(200,16,46,0.05);
  --hero-grid:  rgba(200,16,46,0.05);
  --hero-text:  #111111;
  --hero-sub:   #666666;

  --cd-bg:      rgba(200,16,46,0.06);
  --cd-border:  rgba(200,16,46,0.25);

  --outline-border: rgba(0,0,0,0.2);
  --outline-text:   #111111;

  --sale-bg:    linear-gradient(135deg,#fff0f2 0%,#f9f9f9 50%,#fff0f2 100%);
  --sale-word:  rgba(200,16,46,0.04);
  --sale-tag-bg:rgba(200,16,46,0.08);
  --sale-tag-border: rgba(200,16,46,0.3);

  --footer-bg:  #1a1a1a;
  --footer-text:#cccccc;
  --footer-muted:#777777;
  --footer-border:#2a2a2a;
  --footer-link: #aaaaaa;
}

[data-theme="dark"] {
  --bg:         #0B0B0B;
  --bg2:        #111111;
  --dark-sect:  #111111;
  --card:       #161616;
  --card2:      #1c1c1c;
  --border:     #222222;
  --border2:    #2e2e2e;
  --text:       #ffffff;
  --text2:      #cccccc;
  --muted:      #777777;
  --nav-bg:     rgba(11,11,11,0.97);
  --shadow:     rgba(0,0,0,0.4);
  --shadow2:    rgba(0,0,0,0.6);

  --hero-bg:    linear-gradient(135deg,#0B0B0B 0%,#1a0008 50%,#0B0B0B 100%);
  --hero-radial1: rgba(200,16,46,0.12);
  --hero-radial2: rgba(200,16,46,0.08);
  --hero-grid:  rgba(255,255,255,0.03);
  --hero-text:  #ffffff;
  --hero-sub:   #888888;

  --cd-bg:      rgba(255,255,255,0.04);
  --cd-border:  rgba(200,16,46,0.3);

  --outline-border: rgba(255,255,255,0.3);
  --outline-text:   #ffffff;

  --sale-bg:    linear-gradient(135deg,#1a0008 0%,#0B0B0B 50%,#1a0008 100%);
  --sale-word:  rgba(200,16,46,0.04);
  --sale-tag-bg:rgba(200,16,46,0.10);
  --sale-tag-border: rgba(200,16,46,0.35);

  --footer-bg:  #0d0d0d;
  --footer-text:#cccccc;
  --footer-muted:#666666;
  --footer-border:#1e1e1e;
  --footer-link: #888888;
}

* { margin:0; padding:0; box-sizing:border-box; }
html { scroll-behavior:smooth; }
body {
  font-family:'Cairo',sans-serif;
  background:var(--bg);
  color:var(--text);
  overflow-x:hidden;
  direction:rtl;
  transition:background .35s,color .35s;
}
a { text-decoration:none; color:inherit; }
ul { list-style:none; }
img { max-width:100%; display:block; }
button { background:none; border:none; cursor:pointer; font-family:'Cairo',sans-serif; }

/* Toast */
#toastBox {
  position:fixed; bottom:24px; right:24px; z-index:9999;
  display:flex; flex-direction:column; gap:10px; pointer-events:none;
}
.toast {
  display:flex; align-items:center; gap:12px;
  padding:14px 18px; border-radius:10px;
  background:#1e2128; color:#fff;
  font-size:.875rem; font-weight:500;
  box-shadow:0 20px 60px rgba(0,0,0,.2);
  min-width:250px; max-width:360px;
  animation:tIn .35s ease both; pointer-events:all;
}
.toast.out { animation:tOut .2s ease both; }
.t-s{ color:#34d399; flex-shrink:0; } .t-e{ color:#f87171; flex-shrink:0; }
@keyframes tIn{ from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:none} }
@keyframes tOut{ from{opacity:1} to{opacity:0;transform:translateY(-10px)} }

/* Announcement */
.announcement {
  background:var(--red);
  text-align:center;
  padding:10px;
  font-size:13px;
  font-weight:600;
  letter-spacing:.5px;
  color:#fff;
  overflow:hidden;
  white-space:nowrap;
}
.ticker-inner { display:inline-block; animation:ticker 24s linear infinite; }
@keyframes ticker { 0%{transform:translateX(0)} 100%{transform:translateX(-50%)} }

/* Navbar */
nav {
  position:sticky; top:0; z-index:1000;
  background:var(--nav-bg);
  backdrop-filter:blur(20px);
  border-bottom:1px solid var(--border);
  padding:0 40px;
  transition:background .35s,border-color .35s,box-shadow .3s;
}
.nav-inner {
  max-width:1400px; margin:0 auto;
  display:flex; align-items:center; justify-content:space-between;
  height:70px;
}
.logo {
  font-weight:900; font-size:26px;
  color:var(--text); text-decoration:none;
  letter-spacing:-.5px; transition:color .35s;
}
.logo span { color:var(--red); }
.nav-menu { display:flex; gap:28px; list-style:none; align-items:center; }
.nav-menu a {
  color:var(--text2); text-decoration:none;
  font-size:14px; font-weight:600; transition:color .2s; position:relative;
}
.nav-menu a:hover { color:var(--text); }
.nav-menu a.active { color:var(--red); }
.nav-menu a::after {
  content:''; position:absolute; bottom:-4px; right:0;
  width:0; height:2px; background:var(--red); transition:width .3s;
}
.nav-menu a:hover::after { width:100%; }
.nav-icons { display:flex; gap:14px; align-items:center; }
.nav-icon {
  color:var(--text); cursor:pointer; font-size:20px;
  transition:color .2s; position:relative; background:none; border:none;
  padding:6px; text-decoration:none; display:inline-flex;
}
.nav-icon:hover { color:var(--red); }
.nav-badge {
  position:absolute; top:-4px; left:-4px;
  background:var(--red); color:#fff; border-radius:50%;
  width:18px; height:18px; font-size:10px;
  display:flex; align-items:center; justify-content:center; font-weight:700;
}

/* Theme Toggle */
.theme-toggle {
  display:flex; align-items:center; gap:8px;
  background:var(--card); border:1.5px solid var(--border);
  border-radius:50px; padding:6px 14px;
  cursor:pointer; transition:all .25s; white-space:nowrap; user-select:none;
}
.theme-toggle:hover { border-color:var(--red); box-shadow:0 0 0 3px var(--red-dim); }
.toggle-track {
  width:40px; height:22px; background:var(--border2);
  border-radius:11px; position:relative; transition:background .3s; flex-shrink:0;
}
[data-theme="dark"] .toggle-track { background:var(--red); }
.toggle-thumb {
  position:absolute; top:3px; right:3px;
  width:16px; height:16px; background:#fff; border-radius:50%;
  transition:transform .3s; box-shadow:0 1px 4px rgba(0,0,0,.25);
}
[data-theme="dark"] .toggle-thumb { transform:translateX(-18px); }
.t-icon { font-size:14px; }
.t-lbl  { font-size:12px; font-weight:700; color:var(--muted); min-width:26px; }

/* Mobile Menu */
.mob-btn {
  display:none; width:40px; height:40px;
  align-items:center; justify-content:center;
  border-radius:8px; color:var(--text); transition:all .2s;
  flex-shrink:0; background:none; border:none; cursor:pointer;
}
.mob-btn:hover { background:#f0f0f0; color:var(--red); }
[data-theme="dark"] .mob-btn:hover { background:#222; }
.mob-btn svg { width:24px; height:24px; stroke:currentColor; fill:none; }

.mob-drawer {
  display:none; position:fixed; top:0; bottom:0; right:-100%;
  width:min(300px,90vw); background:var(--bg2); z-index:600;
  flex-direction:column; box-shadow:0 20px 60px rgba(0,0,0,.15);
  transition:right .35s ease; overflow-y:auto;
}
.mob-drawer.open { right:0; }
.mob-overlay {
  display:none; position:fixed; inset:0;
  background:rgba(0,0,0,.45); z-index:599;
}
.mob-overlay.on { display:block; }
.mob-top {
  display:flex; align-items:center; justify-content:space-between;
  padding:16px 18px; border-bottom:1px solid var(--border);
}
.mob-close {
  width:34px; height:34px; border-radius:8px; background:var(--card2);
  display:flex; align-items:center; justify-content:center; cursor:pointer;
  transition:all .2s; border:none;
}
.mob-close:hover { background:var(--red); color:#fff; }
.mob-close svg { width:16px; height:16px; stroke:currentColor; }
.mob-link {
  display:flex; align-items:center; justify-content:space-between;
  padding:14px 20px; font-size:.9rem; font-weight:600; color:var(--text2);
  border-bottom:1px solid var(--border); transition:all .15s; text-decoration:none;
}
.mob-link:hover, .mob-link.active { color:var(--red); background:var(--red-dim); padding-right:26px; }
.mob-footer-links { padding:16px 18px; border-top:1px solid var(--border); margin-top:auto; }

/* Hero */
.hero {
  min-height:100vh;
  background:var(--hero-bg);
  display:flex; align-items:center;
  position:relative; overflow:hidden;
  transition:background .35s;
}
.hero-bg {
  position:absolute; inset:0;
  background:
    radial-gradient(ellipse 60% 60% at 80% 50%, var(--hero-radial1) 0%, transparent 70%),
    radial-gradient(ellipse 40% 40% at 20% 80%, var(--hero-radial2) 0%, transparent 60%);
}
.hero-grid {
  position:absolute; inset:0;
  background-image:
    linear-gradient(var(--hero-grid) 1px, transparent 1px),
    linear-gradient(90deg, var(--hero-grid) 1px, transparent 1px);
  background-size:60px 60px;
}
.hero-content {
  max-width:1400px; margin:0 auto;
  padding:0 60px; z-index:2; position:relative;
}
.hero-badge {
  display:inline-flex; align-items:center; gap:8px;
  background:var(--red-dim); border:1px solid rgba(200,16,46,.35);
  padding:8px 20px; border-radius:50px;
  font-size:13px; font-weight:700; color:var(--red);
  margin-bottom:30px; animation:fadeSlideDown 1s ease both;
}
.hero-title {
  font-size:clamp(52px,8vw,100px);
  font-weight:900; line-height:1.05;
  margin-bottom:24px; color:var(--hero-text);
  animation:fadeSlideDown 1s ease .1s both;
}
.hero-title .accent { color:var(--red); display:block; }
.hero-sub {
  font-size:18px; color:var(--hero-sub);
  max-width:520px; line-height:1.8;
  margin-bottom:48px;
  animation:fadeSlideDown 1s ease .2s both;
}
.hero-btns {
  display:flex; gap:16px; flex-wrap:wrap;
  animation:fadeSlideDown 1s ease .3s both;
}
.btn-primary {
  background:var(--red); color:#fff;
  padding:16px 40px; border:none; border-radius:4px;
  font-size:15px; font-weight:700; font-family:'Cairo',sans-serif;
  cursor:pointer; transition:all .3s; text-decoration:none; display:inline-block;
}
.btn-primary:hover { background:var(--red-hover); transform:translateY(-2px); box-shadow:0 12px 40px rgba(200,16,46,.35); }
.btn-outline {
  background:transparent; color:var(--outline-text);
  padding:16px 40px; border:1.5px solid var(--outline-border);
  border-radius:4px; font-size:15px; font-weight:700;
  font-family:'Cairo',sans-serif; cursor:pointer; transition:all .3s;
  text-decoration:none; display:inline-block;
}
.btn-outline:hover { border-color:var(--red); color:var(--red); }

/* Countdown */
.countdown-wrap { margin-top:60px; animation:fadeSlideDown 1s ease .4s both; }
.countdown-label {
  font-size:13px; font-weight:700; color:var(--red);
  letter-spacing:2px; text-transform:uppercase;
  margin-bottom:16px; display:flex; align-items:center; gap:8px;
}
.countdown { display:flex; gap:16px; }
.cd-box {
  background:var(--cd-bg); border:1px solid var(--cd-border);
  border-radius:8px; padding:16px 24px;
  text-align:center; min-width:80px;
  box-shadow:0 4px 20px rgba(200,16,46,.08);
  transition:background .35s,border-color .35s;
}
.cd-num {
  font-size:36px; font-weight:900; color:var(--red);
  display:block; line-height:1;
  text-shadow:0 0 20px rgba(200,16,46,.3);
}
.cd-label { font-size:11px; color:var(--muted); margin-top:6px; font-weight:600; letter-spacing:1px; }
.cd-sep {
  color:var(--red); font-size:32px; font-weight:900;
  display:flex; align-items:center; opacity:.6;
  animation:blink 1s infinite;
}
@keyframes blink { 0%,100%{opacity:.6} 50%{opacity:.1} }
@keyframes fadeSlideDown { from{opacity:0;transform:translateY(30px)} to{opacity:1;transform:translateY(0)} }

.hero-stats {
  position:absolute; left:60px; bottom:60px;
  display:flex; gap:48px; z-index:2;
  animation:fadeSlideDown 1s ease .5s both;
}
.stat { text-align:center; }
.stat-num { font-size:36px; font-weight:900; color:var(--text); display:block; }
.stat-text { font-size:12px; color:var(--muted); }

/* Section Common */
.section { padding:100px 60px; max-width:1400px; margin:0 auto; }
.section-header { text-align:center; margin-bottom:60px; }
.section-tag { display:inline-block; font-size:11px; font-weight:700; letter-spacing:3px; color:var(--red); text-transform:uppercase; margin-bottom:12px; }
.section-title { font-size:clamp(32px,4vw,52px); font-weight:900; line-height:1.1; color:var(--text); }
.section-line { width:60px; height:3px; background:var(--red); margin:20px auto 0; border-radius:2px; }
.section-hd-row {
  display:flex; align-items:center; justify-content:space-between;
  margin-bottom:32px; flex-wrap:wrap; gap:12px;
}
.btn-sm {
  padding:10px 22px; border:1.5px solid var(--border); border-radius:4px;
  font-size:13px; font-weight:700; color:var(--text2); background:var(--card);
  cursor:pointer; transition:all .2s; font-family:'Cairo',sans-serif;
  text-decoration:none; display:inline-block;
}
.btn-sm:hover { border-color:var(--red); color:var(--red); }

/* Categories */
.categories-grid { display:grid; grid-template-columns:repeat(5,1fr); gap:16px; }
.cat-card {
  background:var(--card); border:1px solid var(--border);
  border-radius:12px; padding:40px 20px; text-align:center;
  cursor:pointer; transition:all .3s; position:relative; overflow:hidden;
  box-shadow:0 2px 10px var(--shadow); text-decoration:none; display:block;
}
.cat-card::before {
  content:''; position:absolute; inset:0;
  background:linear-gradient(135deg,var(--red-dim),transparent);
  opacity:0; transition:opacity .3s;
}
.cat-card:hover { transform:translateY(-8px); border-color:rgba(200,16,46,.4); box-shadow:0 16px 40px var(--shadow2); }
.cat-card:hover::before { opacity:1; }
.cat-icon { font-size:48px; display:block; margin-bottom:16px; }
.cat-name { font-size:16px; font-weight:700; color:var(--text); }
.cat-sub  { font-size:12px; color:var(--muted); margin-top:6px; }

/* Products Grid */
.products-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:24px; }

/* Products Slider */
.products-slider-mobile {
  display:none;
  width:100%;
  overflow:hidden;
  padding:10px 0 30px;
}

.product-card {
  background:var(--card); border:1px solid var(--border);
  border-radius:12px; overflow:hidden;
  transition:all .3s; position:relative;
  box-shadow:0 2px 10px var(--shadow); display:flex; flex-direction:column;
  height:100%;
}
.product-card:hover { transform:translateY(-6px); box-shadow:0 20px 50px var(--shadow2); border-color:rgba(200,16,46,.3); }
.product-img {
  height:280px;
  background:linear-gradient(135deg,#f0f0f0,#e8e8e8);
  position:relative; overflow:hidden;
  display:flex; align-items:center; justify-content:center; font-size:80px;
  transition:background .35s;
}
[data-theme="dark"] .product-img { background:linear-gradient(135deg,#1a1a1a,#222); }
.product-img img {
  width:100%; height:100%; object-fit:cover; position:absolute; inset:0;
}
.product-img::after {
  content:''; position:absolute; inset:0;
  background:linear-gradient(to bottom,transparent 60%,rgba(0,0,0,.1));
}
[data-theme="dark"] .product-img::after {
  background:linear-gradient(to bottom,transparent 60%,rgba(0,0,0,.4));
}
.badge {
  position:absolute; top:16px; right:16px;
  background:var(--red); color:#fff;
  padding:5px 12px; border-radius:4px; font-size:12px; font-weight:700; z-index:2;
}
.badge.hot { background:#ea580c; }
.product-actions {
  position:absolute; top:16px; left:16px;
  display:flex; flex-direction:column; gap:8px; z-index:2;
  opacity:0; transform:translateX(-10px); transition:all .3s;
}
.product-card:hover .product-actions { opacity:1; transform:translateX(0); }
.action-btn {
  width:36px; height:36px;
  background:rgba(255,255,255,.85); backdrop-filter:blur(8px);
  border:1px solid rgba(0,0,0,.1); border-radius:8px;
  display:flex; align-items:center; justify-content:center;
  cursor:pointer; font-size:16px; transition:background .2s;
  box-shadow:0 2px 8px rgba(0,0,0,.1); text-decoration:none; color:inherit;
}
[data-theme="dark"] .action-btn { background:rgba(255,255,255,.08); border-color:rgba(255,255,255,.15); }
.action-btn:hover { background:var(--red); }
.product-info { padding:20px; flex:1; display:flex; flex-direction:column; }
.product-brand { 
  font-size:11px; 
  font-weight:700; 
  letter-spacing:2px; 
  color:var(--red); 
  margin-bottom:6px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.product-name  { 
  font-size:16px; 
  font-weight:700; 
  margin-bottom:12px; 
  line-height:1.4; 
  color:var(--text); 
  text-decoration:none; 
  display:block;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  min-height: 44px;
}
.product-name:hover { color:var(--red); }
.product-pricing { 
  display:flex; 
  align-items:center; 
  gap:8px; 
  margin-bottom:16px; 
  flex-wrap:wrap; 
  margin-top:auto; 
}
.price-new  { 
  font-size:22px; 
  font-weight:900; 
  color:var(--red); 
}
.price-old  { 
  font-size:15px; 
  color:var(--muted); 
  text-decoration:line-through; 
}
.price-save { 
  font-size:12px; 
  color:#16a34a; 
  font-weight:700; 
  background:rgba(22,163,74,0.1);
  padding:4px 10px;
  border-radius:20px;
  display:inline-block;
  width:100%;
  text-align:center;
}
.add-to-cart {
  width:100%; background:var(--red); color:#fff; border:none;
  padding:12px; border-radius:6px; font-size:14px; font-weight:700;
  font-family:'Cairo',sans-serif; cursor:pointer; transition:all .2s;
}
.add-to-cart:hover { background:var(--red-hover); }
.add-to-cart:disabled { opacity:.5; cursor:not-allowed; background:#888; }

/* Swiper Customization */
.swiper {
  width: 100%;
  padding: 10px 5px 30px;
}
.swiper-slide {
  width: 280px;
  height: auto;
}
.swiper-button-next, .swiper-button-prev {
  color: var(--red);
  background: rgba(255,255,255,0.9);
  width: 40px;
  height: 40px;
  border-radius: 50%;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
  top: 40%;
}
.swiper-button-next:after, .swiper-button-prev:after {
  font-size: 18px;
  font-weight: bold;
}
.swiper-pagination-bullet {
  width: 8px;
  height: 8px;
  background: var(--border2);
  opacity: 1;
}
.swiper-pagination-bullet-active {
  background: var(--red);
  width: 20px;
  border-radius: 10px;
}

/* Brands */
.brands-section {
  padding:80px 60px;
  background:var(--dark-sect);
  border-top:1px solid var(--border);
  border-bottom:1px solid var(--border);
  transition:background .35s,border-color .35s;
}
.brands-scroll { 
  display:flex; gap:40px; align-items:center; justify-content:center; 
  flex-wrap:wrap; margin-top:48px; 
}
.brand-item {
  font-family:'Playfair Display',serif;
  font-size:24px; font-weight:700; color:var(--muted);
  cursor:pointer; transition:all .3s; letter-spacing:-.5px;
  padding:10px 20px; border-radius:8px;
}
.brand-item:hover { 
  color:var(--text); transform:scale(1.05); 
  background:var(--card); border-color:var(--border);
}
.brand-sep { color:var(--border2); font-size:30px; }

/* Sale Banner */
.sale-banner {
  padding:100px 60px;
  background:var(--sale-bg);
  position:relative; overflow:hidden;
  text-align:center;
  border-top:1px solid var(--border);
  transition:background .35s;
}
.sale-banner::before {
  content:'SALE'; position:absolute; font-size:300px; font-weight:900;
  color:var(--sale-word); top:50%; left:50%;
  transform:translate(-50%,-50%); white-space:nowrap; pointer-events:none;
}
.sale-content { position:relative; z-index:2; max-width:800px; margin:0 auto; }
.sale-content h2 { font-size:clamp(40px,6vw,80px); font-weight:900; margin-bottom:20px; color:var(--text); }
.sale-content p { font-size:18px; color:var(--muted); margin-bottom:40px; line-height:1.8; }
.sale-tags { display:flex; gap:12px; justify-content:center; flex-wrap:wrap; margin-bottom:40px; }
.sale-tag {
  padding:8px 20px; border:1px solid var(--sale-tag-border);
  border-radius:50px; font-size:13px; font-weight:700;
  color:var(--red); background:var(--sale-tag-bg);
}

/* Fashion Categories */
.fashion-cats-section {
  padding:80px 60px;
  background:var(--bg2);
  border-top:1px solid var(--border);
  transition:background .35s,border-color .35s;
}
.fashion-grid {
  display:grid;
  grid-template-columns:repeat(5,1fr);
  gap:20px;
  margin-top:40px;
}
.fashion-card {
  position:relative;
  border-radius:16px;
  overflow:hidden;
  aspect-ratio:3/4;
  cursor:pointer;
  box-shadow:0 10px 30px var(--shadow);
  transition:all .3s;
  text-decoration:none;
  display:block;
}
.fashion-card:hover {
  transform:translateY(-10px);
  box-shadow:0 20px 40px var(--shadow2);
}
.fashion-img {
  width:100%;
  height:100%;
  object-fit:cover;
  transition:transform .5s;
}
.fashion-card:hover .fashion-img {
  transform:scale(1.1);
}
.fashion-overlay {
  position:absolute;
  inset:0;
  background:linear-gradient(to top, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0.2) 50%, rgba(0,0,0,0) 100%);
  display:flex;
  flex-direction:column;
  justify-content:flex-end;
  padding:25px 20px;
  color:#fff;
}
.fashion-overlay h3 {
  font-size:24px;
  font-weight:900;
  margin-bottom:8px;
  text-shadow:0 2px 10px rgba(0,0,0,0.3);
}
.fashion-overlay p {
  font-size:14px;
  opacity:0.9;
  text-shadow:0 2px 5px rgba(0,0,0,0.3);
}
.fashion-badge {
  position:absolute;
  top:20px;
  right:20px;
  background:var(--red);
  color:#fff;
  padding:5px 15px;
  border-radius:30px;
  font-size:12px;
  font-weight:700;
  z-index:2;
}

/* Trust */
.trust-section {
  background:var(--dark-sect);
  border-top:1px solid var(--border);
  transition:background .35s,border-color .35s;
}
.trust-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:24px; }
.trust-card {
  background:var(--card); border:1px solid var(--border);
  border-radius:12px; padding:36px 24px; text-align:center;
  transition:all .3s; box-shadow:0 2px 10px var(--shadow);
}
.trust-card:hover { border-color:rgba(200,16,46,.35); transform:translateY(-4px); box-shadow:0 12px 32px var(--shadow2); }
.trust-icon  { font-size:36px; display:block; margin-bottom:16px; }
.trust-title { font-size:18px; font-weight:900; margin-bottom:8px; color:var(--text); }
.trust-text  { font-size:14px; color:var(--muted); line-height:1.6; }

/* Contact */
.contact-section { background:var(--bg2); border-top:1px solid var(--border); transition:background .35s; }
.contact-grid { display:grid; grid-template-columns:1fr 1fr; gap:40px; align-items:start; }
.contact-info h3 { font-size:28px; font-weight:900; margin-bottom:32px; color:var(--text); }
.contact-item {
  display:flex; align-items:center; gap:16px;
  padding:20px; background:var(--card); border:1px solid var(--border);
  border-radius:10px; margin-bottom:12px; transition:all .3s;
  box-shadow:0 2px 8px var(--shadow);
}
.contact-item:hover { border-color:rgba(200,16,46,.35); }
.contact-icon { font-size:24px; flex-shrink:0; }
.contact-text { font-size:15px; font-weight:600; color:var(--text); }
.contact-sub  { font-size:12px; color:var(--muted); display:block; }
.map-placeholder {
  background:var(--card); border:1px solid var(--border); border-radius:12px;
  height:400px; display:flex; flex-direction:column;
  align-items:center; justify-content:center; gap:16px;
  font-size:48px; cursor:pointer; transition:all .3s;
  text-decoration:none; color:inherit;
  box-shadow:0 2px 10px var(--shadow);
}
.map-placeholder:hover { border-color:rgba(200,16,46,.4); transform:translateY(-3px); }
.map-text { font-size:15px; color:var(--muted); font-weight:600; }
.map-addr { font-size:13px; color:var(--muted); text-align:center; padding:0 30px; line-height:1.6; }

/* Footer */
footer {
  background:var(--footer-bg);
  border-top:1px solid var(--footer-border);
  padding:80px 60px 40px;
  transition:background .35s;
}
.footer-inner {
  max-width:1400px; margin:0 auto;
  display:grid; grid-template-columns:2fr 1fr 1fr 1fr;
  gap:60px; margin-bottom:60px;
}
.footer-brand h3 { font-size:28px; font-weight:900; margin-bottom:16px; color:#fff; }
.footer-brand h3 span { color:var(--red); }
.footer-brand p { font-size:14px; color:var(--footer-muted); line-height:1.8; margin-bottom:24px; }
.socials { display:flex; gap:12px; flex-wrap:wrap; }
.social {
  width:40px; height:40px; border:1px solid var(--footer-border);
  border-radius:8px; display:flex; align-items:center; justify-content:center;
  font-size:18px; cursor:pointer; transition:all .2s;
  text-decoration:none; color:var(--footer-muted);
}
.social:hover { border-color:var(--red); color:var(--red); background:rgba(200,16,46,.08); }
.footer-col h4 { font-size:15px; font-weight:700; margin-bottom:20px; color:#fff; }
.footer-col ul { list-style:none; }
.footer-col ul li { margin-bottom:10px; }
.footer-col ul li a { color:var(--footer-link); text-decoration:none; font-size:14px; transition:color .2s; }
.footer-col ul li a:hover { color:var(--red); }
.newsletter-form { display:flex; gap:8px; margin-top:20px; }
.newsletter-form input {
  flex:1; background:#222; border:1px solid #333; border-radius:6px;
  padding:10px 16px; color:#fff; font-family:'Cairo',sans-serif;
  font-size:14px; outline:none; transition:border-color .2s;
}
.newsletter-form input:focus { border-color:var(--red); }
.newsletter-form input::placeholder { color:#555; }
.newsletter-form button {
  background:var(--red); border:none; color:#fff;
  padding:10px 20px; border-radius:6px; cursor:pointer;
  font-family:'Cairo',sans-serif; font-weight:700; transition:background .2s;
}
.newsletter-form button:hover { background:var(--red-hover); }
.footer-bottom {
  max-width:1400px; margin:0 auto;
  border-top:1px solid var(--footer-border);
  padding-top:32px;
  display:flex; justify-content:space-between; align-items:center;
  font-size:13px; color:var(--footer-muted);
  flex-wrap:wrap; gap:10px;
}

/* Float Cart */
.float-cart {
  position:fixed; bottom:30px; left:30px;
  background:var(--red); color:#fff;
  width:56px; height:56px; border-radius:50%;
  display:flex; align-items:center; justify-content:center;
  font-size:22px; cursor:pointer;
  box-shadow:0 8px 30px rgba(200,16,46,.45);
  z-index:999; transition:transform .2s; text-decoration:none;
}
.float-cart:hover { transform:scale(1.1); }
.float-count {
  position:absolute; top:-4px; right:-4px;
  background:#fff; color:var(--red);
  width:20px; height:20px; border-radius:50%;
  font-size:11px; font-weight:700;
  display:flex; align-items:center; justify-content:center;
}

/* Scroll Reveal */
.reveal { opacity:0; transform:translateY(40px); transition:opacity .8s ease,transform .8s ease; }
.reveal.visible { opacity:1; transform:translateY(0); }

/* Responsive */
@media(max-width:1200px) {
  .categories-grid { grid-template-columns:repeat(4,1fr); }
  .fashion-grid { grid-template-columns:repeat(4,1fr); }
}

@media(max-width:1024px) {
  .categories-grid { grid-template-columns:repeat(3,1fr); }
  .fashion-grid { grid-template-columns:repeat(3,1fr); }
  .trust-grid { grid-template-columns:repeat(2,1fr); }
  .contact-grid { grid-template-columns:1fr; }
  .footer-inner { grid-template-columns:1fr 1fr; gap:40px; }
}

@media(max-width:900px) {
  .nav-menu { display:none; }
  .mob-btn { display:flex; }
  .mob-drawer { display:flex; }
  nav { padding:0 20px; }
  .hero-stats { left:20px; bottom:24px; gap:28px; }
  .stat-num { font-size:26px; }
  
  .products-grid { display:none; }
  .products-slider-mobile { display:block; }
  
  .swiper-slide { width: 260px; }
}

@media(max-width:768px) {
  .hero { min-height:auto; padding:80px 0 60px; }
  .hero-content { padding:0 24px; }
  .hero-title { font-size:clamp(36px,10vw,60px); }
  .hero-sub { font-size:15px; margin-bottom:28px; }
  .btn-primary, .btn-outline { padding:13px 26px; font-size:14px; }
  .hero-stats { display:none; }
  .countdown { gap:8px; }
  .cd-box { padding:12px 16px; min-width:64px; flex:1; }
  .cd-num { font-size:28px; }
  .t-lbl { display:none; }
  
  .section { padding:60px 24px; }
  .brands-section { padding:60px 24px; }
  .sale-banner { padding:60px 24px; }
  .fashion-cats-section { padding:60px 24px; }
  footer { padding:60px 24px 30px; }
  
  .categories-grid { grid-template-columns:repeat(2,1fr); gap:12px; }
  .cat-card { padding:30px 15px; }
  .cat-icon { font-size:40px; }
  
  .fashion-grid { grid-template-columns:repeat(2,1fr); gap:15px; }
  .fashion-overlay h3 { font-size:20px; }
  
  .product-img { height:220px; }
  
  .trust-grid { grid-template-columns:1fr 1fr; gap:12px; }
  .trust-card { padding:24px 16px; }
  .trust-icon { font-size:28px; }
  .trust-title { font-size:15px; }
  
  .map-placeholder { height:220px; }
  .sale-banner::before { font-size:160px; }
  .brands-scroll { gap:20px; }
  .brand-item { font-size:20px; }
  .footer-inner { grid-template-columns:1fr; gap:32px; }
  .footer-bottom { flex-direction:column; gap:12px; text-align:center; }
  
  .swiper-slide { width: 240px; }
}

@media(max-width:480px) {
  .categories-grid { grid-template-columns:repeat(2,1fr); gap:8px; }
  .cat-card { padding:20px 10px; }
  .cat-icon { font-size:32px; }
  .cat-name { font-size:14px; }
  
  .fashion-grid { grid-template-columns:1fr; gap:15px; }
  .fashion-card { aspect-ratio:2/1; }
  
  .trust-grid { grid-template-columns:1fr 1fr; gap:10px; }
  .hero-btns { gap:8px; }
  .btn-primary, .btn-outline { padding:12px 20px; font-size:13px; }
  .cd-num { font-size:22px; }
  .sale-tags { gap:8px; }
  .sale-tag { padding:6px 14px; font-size:11px; }
  
  .swiper-slide { width: 220px; }
}
</style>
</head>
<body>

<div id="toastBox"></div>
<div class="mob-overlay" id="mobOverlay" onclick="drawerClose()"></div>

<!-- Announcement -->
<div class="announcement">
  <span class="ticker-inner">
    🔥 عروض العيد الكبرى – خصومات تصل إلى 50% على الماركات العالمية &nbsp;|&nbsp;
    🎁 شحن مجاني على الطلبات فوق 200 شيكل &nbsp;|&nbsp;
    ⭐ أكثر من 11 فرع في فلسطين &nbsp;|&nbsp;
    🔥 عروض العيد الكبرى – خصومات تصل إلى 50% على الماركات العالمية &nbsp;|&nbsp;
    🎁 شحن مجاني على الطلبات فوق 200 شيكل &nbsp;|&nbsp;
    ⭐ أكثر من 11 فرع في فلسطين &nbsp;|&nbsp;
  </span>
</div>

<!-- Navbar -->
<nav id="mainNav">
  <div class="nav-inner">
    <a href="index.php" class="logo">التصفية <span>العالمية</span></a>
    <ul class="nav-menu">
      <li><a href="index.php" class="active">الرئيسية</a></li>
      <li><a href="shop.php?cat=shoes">الأحذية</a></li>
      <li><a href="shop.php?cat=clothes">الملابس</a></li>
      <li><a href="shop.php?cat=bags">الشنط</a></li>
      <li><a href="shop.php?cat=accessories">الاكسسوارات</a></li>
      <li><a href="shop.php?cat=perfumes">العطور</a></li>
      <li><a href="shop.php?filter=sale" style="color:var(--red)">🔥 العروض</a></li>
      <li><a href="#branches">فروعنا</a></li>
      <li><a href="#contact">تواصل معنا</a></li>
    </ul>
    <div class="nav-icons">
      <button class="theme-toggle" onclick="toggleTheme()" title="تبديل الوضع">
        <span class="t-icon" id="themeIcon">☀️</span>
        <div class="toggle-track"><div class="toggle-thumb"></div></div>
        <span class="t-lbl" id="themeLbl">فاتح</span>
      </button>
      <a href="wishlist.php" class="nav-icon" style="position:relative;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="22" height="22"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
        <?php if($_wishCount > 0): ?><span class="nav-badge"><?= $_wishCount ?></span><?php endif; ?>
      </a>
      <a href="cart.php" class="nav-icon" style="position:relative;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="22" height="22"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
        <span class="nav-badge" id="cartBadge"><?= $_cartCount > 0 ? $_cartCount : '' ?></span>
      </a>
      <button class="mob-btn" onclick="drawerOpen()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
      </button>
    </div>
  </div>
</nav>

<!-- Mobile Drawer -->
<div class="mob-drawer" id="mobDrawer">
  <div class="mob-top">
    <a href="index.php" class="logo" onclick="drawerClose()">التصفية <span>العالمية</span></a>
    <button class="mob-close" onclick="drawerClose()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
    </button>
  </div>
  <a href="index.php" class="mob-link active">الرئيسية <svg viewBox="0 0 20 20" fill="currentColor" width="12" height="12"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></a>
  <a href="shop.php?cat=shoes" class="mob-link">الأحذية <svg viewBox="0 0 20 20" fill="currentColor" width="12" height="12"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></a>
  <a href="shop.php?cat=clothes" class="mob-link">الملابس <svg viewBox="0 0 20 20" fill="currentColor" width="12" height="12"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></a>
  <a href="shop.php?cat=bags" class="mob-link">الشنط <svg viewBox="0 0 20 20" fill="currentColor" width="12" height="12"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></a>
  <a href="shop.php?cat=accessories" class="mob-link">الاكسسوارات <svg viewBox="0 0 20 20" fill="currentColor" width="12" height="12"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></a>
  <a href="shop.php?cat=perfumes" class="mob-link">العطور <svg viewBox="0 0 20 20" fill="currentColor" width="12" height="12"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></a>
  <a href="shop.php?filter=sale" class="mob-link" style="color:var(--red)">🔥 العروض <svg viewBox="0 0 20 20" fill="currentColor" width="12" height="12"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></a>
  <a href="#branches" class="mob-link" onclick="drawerClose()">فروعنا <svg viewBox="0 0 20 20" fill="currentColor" width="12" height="12"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></a>
  <a href="#contact" class="mob-link" onclick="drawerClose()">تواصل معنا <svg viewBox="0 0 20 20" fill="currentColor" width="12" height="12"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></a>
  <div class="mob-footer-links">
    <?php if($_user): ?>
      <a href="dashboard.php" style="display:block;background:var(--red);color:#fff;text-align:center;padding:12px;border-radius:6px;font-weight:700;margin-bottom:8px;">حسابي</a>
      <a href="logout.php" style="display:block;background:var(--card2);color:var(--text2);text-align:center;padding:12px;border-radius:6px;font-weight:700;">تسجيل الخروج</a>
    <?php else: ?>
      <a href="login.php" style="display:block;background:var(--red);color:#fff;text-align:center;padding:12px;border-radius:6px;font-weight:700;margin-bottom:8px;">تسجيل الدخول</a>
      <a href="register.php" style="display:block;background:var(--card2);color:var(--text2);text-align:center;padding:12px;border-radius:6px;font-weight:700;">إنشاء حساب</a>
    <?php endif; ?>
  </div>
</div>

<!-- Hero -->
<section class="hero">
  <div class="hero-bg"></div>
  <div class="hero-grid"></div>
  <div class="hero-content">
    <div class="hero-badge">🔥 عروض العيد الكبرى – لفترة محدودة</div>
    <h1 class="hero-title">
      عروض العيد
      <span class="accent">الكبرى</span>
    </h1>
    <p class="hero-sub">خصومات ضخمة على الملابس والأحذية والعطور من أفضل الماركات العالمية — Adidas، Skechers وأكثر</p>
    <div class="hero-btns">
      <a href="shop.php" class="btn-primary">تسوق الآن</a>
      <a href="#sale" class="btn-outline">استكشف العروض</a>
    </div>
    <div class="countdown-wrap">
      <div class="countdown-label">⏳ الوقت المتبقي على عروض العيد</div>
      <div class="countdown">
        <div class="cd-box"><span class="cd-num" id="cd-days">00</span><div class="cd-label">يوم</div></div>
        <div class="cd-sep">:</div>
        <div class="cd-box"><span class="cd-num" id="cd-hours">00</span><div class="cd-label">ساعة</div></div>
        <div class="cd-sep">:</div>
        <div class="cd-box"><span class="cd-num" id="cd-mins">00</span><div class="cd-label">دقيقة</div></div>
        <div class="cd-sep">:</div>
        <div class="cd-box"><span class="cd-num" id="cd-secs">00</span><div class="cd-label">ثانية</div></div>
      </div>
    </div>
  </div>
  <div class="hero-stats">
    <div class="stat"><span class="stat-num">+11</span><span class="stat-text">فرع</span></div>
    <div class="stat"><span class="stat-num">+50%</span><span class="stat-text">خصم</span></div>
    <div class="stat"><span class="stat-num">+500</span><span class="stat-text">منتج</span></div>
  </div>
</section>

<!-- Categories -->
<section id="categories" style="background:var(--bg2);border-top:1px solid var(--border);transition:background .35s,border-color .35s;">
  <div class="section reveal">
    <div class="section-header">
      <div class="section-tag">تسوق حسب الفئة</div>
      <h2 class="section-title">اكتشف مجموعاتنا</h2>
      <div class="section-line"></div>
    </div>
    <div class="categories-grid">
      <?php
      if (!empty($_cats)) {
          foreach(array_slice($_cats,0,5) as $i=>$cat):
              $cnt = isset($cat['id']) ? (int)(function_exists('dbFetchOne') ? (dbFetchOne("SELECT COUNT(*) c FROM products WHERE category_id=? AND is_active=1",'i',$cat['id'])['c']??0) : 0) : 0;
              $catName = !empty($cat['name']) ? htmlspecialchars($cat['name']) : $catNames[$i];
              $catSlug = !empty($cat['slug']) ? urlencode($cat['slug']) : $catNames[$i];
          ?>
          <a href="shop.php?cat=<?= $catSlug ?>" class="cat-card">
              <span class="cat-icon"><?= $catIcons[$i%count($catIcons)] ?></span>
              <div class="cat-name"><?= $catName ?></div>
              <div class="cat-sub"><?= $cnt ?> منتج</div>
          </a>
      <?php 
          endforeach; 
      } else {
          for($i=0; $i<5; $i++):
      ?>
          <a href="shop.php?cat=<?= $catNames[$i] ?>" class="cat-card">
              <span class="cat-icon"><?= $catIcons[$i] ?></span>
              <div class="cat-name"><?= $catNames[$i] ?></div>
              <div class="cat-sub">0 منتج</div>
          </a>
      <?php 
          endfor;
      }
      ?>
    </div>
  </div>
</section>

<!-- Fashion Categories -->
<section class="fashion-cats-section">
  <div class="reveal">
    <div class="section-header">
      <div class="section-tag">تسوق حسب</div>
      <h2 class="section-title">أحدث المجموعات</h2>
      <div class="section-line"></div>
    </div>
    <div class="fashion-grid">
      <a href="shop.php?gender=male" class="fashion-card">
        <div class="fashion-badge">رجالي</div>
        <img src="https://images.unsplash.com/photo-1617137968427-85924c800a22?w=400&h=500&fit=crop" alt="رجالي" class="fashion-img" loading="lazy" onerror="this.src='data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22400%22%20height%3D%22500%22%20viewBox%3D%220%200%20400%20500%22%3E%3Crect%20width%3D%22400%22%20height%3D%22500%22%20fill%3D%22%23C8102E%22%2F%3E%3Ctext%20x%3D%22200%22%20y%3D%22250%22%20font-family%3D%22Cairo%22%20font-size%3D%2240%22%20fill%3D%22%23fff%22%20text-anchor%3D%22middle%22%20dominant-baseline%3D%22middle%22%3E%F0%9F%91%94%20رجالي%3C%2Ftext%3E%3C%2Fsvg%3E'">
        <div class="fashion-overlay">
          <h3>رجالي</h3>
          <p>أحدث صيحات الموضة الرجالية</p>
        </div>
      </a>
      
      <a href="shop.php?gender=female" class="fashion-card">
        <div class="fashion-badge">بناتي</div>
        <img src="https://images.unsplash.com/photo-1483985988355-763728e1935b?w=400&h=500&fit=crop" alt="بناتي" class="fashion-img" loading="lazy" onerror="this.src='data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22400%22%20height%3D%22500%22%20viewBox%3D%220%200%20400%20500%22%3E%3Crect%20width%3D%22400%22%20height%3D%22500%22%20fill%3D%22%23C8102E%22%2F%3E%3Ctext%20x%3D%22200%22%20y%3D%22250%22%20font-family%3D%22Cairo%22%20font-size%3D%2240%22%20fill%3D%22%23fff%22%20text-anchor%3D%22middle%22%20dominant-baseline%3D%22middle%22%3E%F0%9F%91%A9%20بناتي%3C%2Ftext%3E%3C%2Fsvg%3E'">
        <div class="fashion-overlay">
          <h3>بناتي</h3>
          <p>أجمل التصاميم النسائية</p>
        </div>
      </a>
      
      <a href="shop.php?cat=kids" class="fashion-card">
        <div class="fashion-badge">أطفال</div>
        <img src="https://images.unsplash.com/photo-1503919545889-a0f4ac7f15a5?w=400&h=500&fit=crop" alt="أطفال" class="fashion-img" loading="lazy" onerror="this.src='data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22400%22%20height%3D%22500%22%20viewBox%3D%220%200%20400%20500%22%3E%3Crect%20width%3D%22400%22%20height%3D%22500%22%20fill%3D%22%23C8102E%22%2F%3E%3Ctext%20x%3D%22200%22%20y%3D%22250%22%20font-family%3D%22Cairo%22%20font-size%3D%2240%22%20fill%3D%22%23fff%22%20text-anchor%3D%22middle%22%20dominant-baseline%3D%22middle%22%3E%F0%9F%A7%B8%20أطفال%3C%2Ftext%3E%3C%2Fsvg%3E'">
        <div class="fashion-overlay">
          <h3>أطفال</h3>
          <p>ملابس وإكسسوارات الأطفال</p>
        </div>
      </a>
      
      <a href="shop.php?cat=perfumes" class="fashion-card">
        <div class="fashion-badge">عطور</div>
        <img src="https://images.unsplash.com/photo-1594035910387-fea47794261f?w=400&h=500&fit=crop" alt="عطور" class="fashion-img" loading="lazy" onerror="this.src='data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22400%22%20height%3D%22500%22%20viewBox%3D%220%200%20400%20500%22%3E%3Crect%20width%3D%22400%22%20height%3D%22500%22%20fill%3D%22%23C8102E%22%2F%3E%3Ctext%20x%3D%22200%22%20y%3D%22250%22%20font-family%3D%22Cairo%22%20font-size%3D%2240%22%20fill%3D%22%23fff%22%20text-anchor%3D%22middle%22%20dominant-baseline%3D%22middle%22%3E%F0%9F%8C%B8%20عطور%3C%2Ftext%3E%3C%2Fsvg%3E'">
        <div class="fashion-overlay">
          <h3>عطور</h3>
          <p>أجمل العطور الشرقية والغربية</p>
        </div>
      </a>
      
      <a href="shop.php?cat=bags" class="fashion-card">
        <div class="fashion-badge">شنط</div>
        <img src="https://images.unsplash.com/photo-1584917865445-4b9b4a1b9b9b?w=400&h=500&fit=crop" alt="شنط" class="fashion-img" loading="lazy" onerror="this.src='data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22400%22%20height%3D%22500%22%20viewBox%3D%220%200%20400%20500%22%3E%3Crect%20width%3D%22400%22%20height%3D%22500%22%20fill%3D%22%23C8102E%22%2F%3E%3Ctext%20x%3D%22200%22%20y%3D%22250%22%20font-family%3D%22Cairo%22%20font-size%3D%2240%22%20fill%3D%22%23fff%22%20text-anchor%3D%22middle%22%20dominant-baseline%3D%22middle%22%3E%F0%9F%91%9B%20شنط%3C%2Ftext%3E%3C%2Fsvg%3E'">
        <div class="fashion-overlay">
          <h3>شنط</h3>
          <p>أحدث موديلات الشنط العالمية</p>
        </div>
      </a>
    </div>
  </div>
</section>

<!-- المنتجات المميزة -->
<section id="products" style="background:var(--dark-sect);border-top:1px solid var(--border);transition:background .35s,border-color .35s;">
  <div class="section reveal">
    <div class="section-hd-row">
      <div>
        <div class="section-tag">⭐ الأكثر مبيعاً</div>
        <h2 class="section-title" style="text-align:right;">المنتجات المميزة</h2>
      </div>
      <a href="shop.php?filter=featured" class="btn-sm">عرض الكل</a>
    </div>
    
    <!-- Grid view for desktop -->
    <div class="products-grid">
      <?php if(!empty($featured)): ?>
        <?php foreach(array_slice($featured,0,4) as $p):
          $onSale = !empty($p['sale_price']) && (float)$p['sale_price'] < (float)$p['price'];
          $price = $onSale ? (float)$p['sale_price'] : (float)$p['price'];
          $disc = $onSale ? round(($p['price']-$p['sale_price'])/$p['price']*100) : 0;
          $out = isset($p['stock']) && (int)$p['stock'] === 0;
          $name = htmlspecialchars($p['name_ar'] ?? $p['name_en'] ?? 'منتج');
          $catLabel = htmlspecialchars($p['cat_ar'] ?? $p['cat_en'] ?? '');
          $img = !empty($p['image']) ? (defined('UPLOADS_URL') ? UPLOADS_URL.'products/'.$p['image'] : 'uploads/products/'.$p['image']) : '';
          $slug = !empty($p['slug']) ? urlencode($p['slug']) : '#';
          $saved = $onSale ? number_format($p['price']-$p['sale_price'],0) : 0;
        ?>
        <div class="product-card">
          <div class="product-img">
            <?php if($img): ?>
              <img src="<?= htmlspecialchars($img) ?>" alt="<?= $name ?>" loading="lazy">
            <?php else: ?>
              👟
            <?php endif; ?>
            <?php if($out): ?>
              <div class="badge" style="background:#6b7280;">نفذ</div>
            <?php elseif($disc): ?>
              <div class="badge">-<?= $disc ?>%</div>
            <?php endif; ?>
            <?php if(!empty($p['is_new']) && !$out): ?>
              <div class="badge hot" style="top:<?= ($disc||$out)?'48px':'16px' ?>;">جديد</div>
            <?php endif; ?>
            <div class="product-actions">
              <button class="action-btn" onclick="toggleWishlist(<?= (int)$p['id'] ?>,this)">♡</button>
              <a href="product.php?slug=<?= $slug ?>" class="action-btn">👁</a>
            </div>
          </div>
          <div class="product-info">
            <?php if($catLabel): ?><div class="product-brand"><?= $catLabel ?></div><?php endif; ?>
            <a href="product.php?slug=<?= $slug ?>" class="product-name"><?= $name ?></a>
            <div class="product-pricing">
              <span class="price-new"><?= number_format($price,0) ?> ₪</span>
              <?php if($onSale): ?>
                <span class="price-old"><?= number_format($p['price'],0) ?> ₪</span>
                <span class="price-save">وفّر <?= $saved ?> ₪</span>
              <?php endif; ?>
            </div>
            <?php if($out): ?>
              <button class="add-to-cart" disabled>نفذت الكمية</button>
            <?php else: ?>
              <button class="add-to-cart" onclick="addToCart(<?= (int)$p['id'] ?>,this)">أضف للسلة</button>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <!-- Static fallback products -->
        <div class="product-card">
          <div class="product-img">👟<div class="badge">-40%</div><div class="product-actions"><div class="action-btn">♡</div><div class="action-btn">👁</div></div></div>
          <div class="product-info"><div class="product-brand">ADIDAS</div><div class="product-name">حذاء أديداس ألترابوست</div><div class="product-pricing"><span class="price-new">249 ₪</span><span class="price-old">419 ₪</span><span class="price-save">وفّر 170 ₪</span></div><button class="add-to-cart">أضف للسلة</button></div>
        </div>
        <div class="product-card">
          <div class="product-img" style="background:linear-gradient(135deg,#d4edda,#b8dfc8)">👟<div class="badge hot">الأكثر طلباً</div><div class="product-actions"><div class="action-btn">♡</div><div class="action-btn">👁</div></div></div>
          <div class="product-info"><div class="product-brand">SKECHERS</div><div class="product-name">حذاء سكيتشرز Go Walk</div><div class="product-pricing"><span class="price-new">189 ₪</span><span class="price-old">299 ₪</span><span class="price-save">وفّر 110 ₪</span></div><button class="add-to-cart">أضف للسلة</button></div>
        </div>
        <div class="product-card">
          <div class="product-img" style="background:linear-gradient(135deg,#fde8e8,#f5d0d0)">👕<div class="badge">عرض خاص</div><div class="product-actions"><div class="action-btn">♡</div><div class="action-btn">👁</div></div></div>
          <div class="product-info"><div class="product-brand">ADIDAS</div><div class="product-name">تيشيرت أديداس أوريجينالز</div><div class="product-pricing"><span class="price-new">89 ₪</span><span class="price-old">149 ₪</span><span class="price-save">وفّر 60 ₪</span></div><button class="add-to-cart">أضف للسلة</button></div>
        </div>
        <div class="product-card">
          <div class="product-img" style="background:linear-gradient(135deg,#ede0f8,#d9c8f0)">🌸<div class="badge">-35%</div><div class="product-actions"><div class="action-btn">♡</div><div class="action-btn">👁</div></div></div>
          <div class="product-info"><div class="product-brand">عطور</div><div class="product-name">عطر فاخر نسائي 100ml</div><div class="product-pricing"><span class="price-new">149 ₪</span><span class="price-old">229 ₪</span><span class="price-save">وفّر 80 ₪</span></div><button class="add-to-cart">أضف للسلة</button></div>
        </div>
      <?php endif; ?>
    </div>
    
    <!-- Slider view for mobile -->
    <div class="products-slider-mobile">
      <div class="swiper productsSwiper">
        <div class="swiper-wrapper">
          <?php if(!empty($featured)): ?>
            <?php foreach($featured as $p):
              $onSale = !empty($p['sale_price']) && (float)$p['sale_price'] < (float)$p['price'];
              $price = $onSale ? (float)$p['sale_price'] : (float)$p['price'];
              $disc = $onSale ? round(($p['price']-$p['sale_price'])/$p['price']*100) : 0;
              $out = isset($p['stock']) && (int)$p['stock'] === 0;
              $name = htmlspecialchars($p['name_ar'] ?? $p['name_en'] ?? 'منتج');
              $catLabel = htmlspecialchars($p['cat_ar'] ?? $p['cat_en'] ?? '');
              $img = !empty($p['image']) ? (defined('UPLOADS_URL') ? UPLOADS_URL.'products/'.$p['image'] : 'uploads/products/'.$p['image']) : '';
              $slug = !empty($p['slug']) ? urlencode($p['slug']) : '#';
              $saved = $onSale ? number_format($p['price']-$p['sale_price'],0) : 0;
            ?>
            <div class="swiper-slide">
              <div class="product-card">
                <div class="product-img">
                  <?php if($img): ?>
                    <img src="<?= htmlspecialchars($img) ?>" alt="<?= $name ?>" loading="lazy">
                  <?php else: ?>
                    👟
                  <?php endif; ?>
                  <?php if($out): ?>
                    <div class="badge" style="background:#6b7280;">نفذ</div>
                  <?php elseif($disc): ?>
                    <div class="badge">-<?= $disc ?>%</div>
                  <?php endif; ?>
                  <?php if(!empty($p['is_new']) && !$out): ?>
                    <div class="badge hot" style="top:<?= ($disc||$out)?'48px':'16px' ?>;">جديد</div>
                  <?php endif; ?>
                  <div class="product-actions">
                    <button class="action-btn" onclick="toggleWishlist(<?= (int)$p['id'] ?>,this)">♡</button>
                    <a href="product.php?slug=<?= $slug ?>" class="action-btn">👁</a>
                  </div>
                </div>
                <div class="product-info">
                  <?php if($catLabel): ?><div class="product-brand"><?= $catLabel ?></div><?php endif; ?>
                  <a href="product.php?slug=<?= $slug ?>" class="product-name"><?= $name ?></a>
                  <div class="product-pricing">
                    <span class="price-new"><?= number_format($price,0) ?> ₪</span>
                    <?php if($onSale): ?>
                      <span class="price-old"><?= number_format($p['price'],0) ?> ₪</span>
                      <span class="price-save">وفّر <?= $saved ?> ₪</span>
                    <?php endif; ?>
                  </div>
                  <?php if($out): ?>
                    <button class="add-to-cart" disabled>نفذت الكمية</button>
                  <?php else: ?>
                    <button class="add-to-cart" onclick="addToCart(<?= (int)$p['id'] ?>,this)">أضف للسلة</button>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
            <!-- Static fallback slides -->
            <div class="swiper-slide">
              <div class="product-card">
                <div class="product-img">👟<div class="badge">-40%</div><div class="product-actions"><div class="action-btn">♡</div><div class="action-btn">👁</div></div></div>
                <div class="product-info"><div class="product-brand">ADIDAS</div><div class="product-name">حذاء أديداس ألترابوست</div><div class="product-pricing"><span class="price-new">249 ₪</span><span class="price-old">419 ₪</span><span class="price-save">وفّر 170 ₪</span></div><button class="add-to-cart">أضف للسلة</button></div>
              </div>
            </div>
            <div class="swiper-slide">
              <div class="product-card">
                <div class="product-img" style="background:linear-gradient(135deg,#d4edda,#b8dfc8)">👟<div class="badge hot">الأكثر طلباً</div><div class="product-actions"><div class="action-btn">♡</div><div class="action-btn">👁</div></div></div>
                <div class="product-info"><div class="product-brand">SKECHERS</div><div class="product-name">حذاء سكيتشرز Go Walk</div><div class="product-pricing"><span class="price-new">189 ₪</span><span class="price-old">299 ₪</span><span class="price-save">وفّر 110 ₪</span></div><button class="add-to-cart">أضف للسلة</button></div>
              </div>
            </div>
            <div class="swiper-slide">
              <div class="product-card">
                <div class="product-img" style="background:linear-gradient(135deg,#fde8e8,#f5d0d0)">👕<div class="badge">عرض خاص</div><div class="product-actions"><div class="action-btn">♡</div><div class="action-btn">👁</div></div></div>
                <div class="product-info"><div class="product-brand">ADIDAS</div><div class="product-name">تيشيرت أديداس أوريجينالز</div><div class="product-pricing"><span class="price-new">89 ₪</span><span class="price-old">149 ₪</span><span class="price-save">وفّر 60 ₪</span></div><button class="add-to-cart">أضف للسلة</button></div>
              </div>
            </div>
            <div class="swiper-slide">
              <div class="product-card">
                <div class="product-img" style="background:linear-gradient(135deg,#ede0f8,#d9c8f0)">🌸<div class="badge">-35%</div><div class="product-actions"><div class="action-btn">♡</div><div class="action-btn">👁</div></div></div>
                <div class="product-info"><div class="product-brand">عطور</div><div class="product-name">عطر فاخر نسائي 100ml</div><div class="product-pricing"><span class="price-new">149 ₪</span><span class="price-old">229 ₪</span><span class="price-save">وفّر 80 ₪</span></div><button class="add-to-cart">أضف للسلة</button></div>
              </div>
            </div>
          <?php endif; ?>
        </div>
        <div class="swiper-pagination"></div>
        <div class="swiper-button-next"></div>
        <div class="swiper-button-prev"></div>
      </div>
    </div>
  </div>
</section>

<!-- Brands -->
<div class="brands-section reveal">
  <div style="max-width:1400px;margin:0 auto;">
    <div class="section-header">
      <div class="section-tag">شركاؤنا</div>
      <h2 class="section-title">الماركات العالمية</h2>
      <div class="section-line"></div>
    </div>
    <div class="brands-scroll">
      <?php foreach($brands as $i=>$b): ?>
        <?php if($i > 0): ?><span class="brand-sep">—</span><?php endif; ?>
        <span class="brand-item"><?= htmlspecialchars(strtoupper($b['brand'] ?? $b)) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Sale Banner -->
<section id="sale" class="sale-banner reveal">
  <div class="sale-content">
    <div class="section-tag">🔥 لفترة محدودة</div>
    <h2>أقوى عروض<br><span style="color:var(--red)">العيد</span></h2>
    <p>تم تمديد العرض بسبب الإقبال الكبير مع توفير كميات إضافية من المنتجات. لا تفوّت فرصتك!</p>
    <div class="sale-tags">
      <?php if(!empty($saleProds)): foreach(array_slice($saleProds,0,4) as $sp): ?>
        <span class="sale-tag"><?= htmlspecialchars($sp['name_ar'] ?? $sp['name_en'] ?? '') ?> — -<?= round(($sp['price']-$sp['sale_price'])/$sp['price']*100) ?>%</span>
      <?php endforeach; else: ?>
        <span class="sale-tag">خصم يصل 50%</span>
        <span class="sale-tag">عرض خاص</span>
        <span class="sale-tag">الأكثر طلباً</span>
        <span class="sale-tag">كميات محدودة</span>
      <?php endif; ?>
    </div>
    <a href="shop.php?filter=sale" class="btn-primary" style="display:inline-block;">تسوق الآن واستفد من العرض</a>
  </div>
</section>

<!-- Fashion Banner -->
<section class="fashion-cats-section" style="background:var(--bg2);">
  <div class="reveal" style="text-align:center">
    <div class="section-tag">أسلوبك</div>
    <h2 class="section-title">أناقتك تبدأ من هنا</h2>
    <p style="color:var(--muted);font-size:18px;margin:20px auto 0;max-width:600px;line-height:1.8">اكتشف أحدث صيحات الموضة بأفضل الأسعار في التصفية العالمية</p>
    <div class="section-line"></div>
  </div>
</section>

<!-- Trust -->
<section class="trust-section">
  <div class="section reveal">
    <div class="section-header">
      <div class="section-tag">لماذا نحن؟</div>
      <h2 class="section-title">لماذا التصفية العالمية؟</h2>
      <div class="section-line"></div>
    </div>
    <div class="trust-grid">
      <div class="trust-card"><span class="trust-icon">🏪</span><div class="trust-title">أكثر من 11 فرع</div><div class="trust-text">نتواجد في مختلف مناطق فلسطين لخدمتك أينما كنت</div></div>
      <div class="trust-card"><span class="trust-icon">🌍</span><div class="trust-title">ماركات عالمية</div><div class="trust-text">نحضر لك أفضل الماركات العالمية بضمان الجودة الأصلية</div></div>
      <div class="trust-card"><span class="trust-icon">💰</span><div class="trust-title">أسعار منافسة</div><div class="trust-text">نضمن لك أفضل الأسعار مع عروض مستمرة على مدار السنة</div></div>
      <div class="trust-card"><span class="trust-icon">🎁</span><div class="trust-title">عروض مستمرة</div><div class="trust-text">خصومات وعروض دورية على جميع المنتجات والفئات</div></div>
    </div>
  </div>
</section>

<!-- Contact -->
<section id="branches" class="contact-section">
  <div class="section reveal">
    <div class="section-header">
      <div class="section-tag">تواصل معنا</div>
      <h2 class="section-title">فروعنا وتواصل معنا</h2>
      <div class="section-line"></div>
    </div>
    <div class="contact-grid" id="contact">
      <div class="contact-info">
        <h3>معلومات التواصل</h3>
        <?php $contactInfo = [
          ['📍','الفرع الرئيسي','رام الله - البيرة، البالوع، شارع الوكالات، خلف بلازا مول، مجمع النعمان'],
          ['📞', $_settings['phone'] ?? '02 242 4433', 'من الأحد إلى الجمعة · 9ص – 9م'],
          ['✉️', $_settings['email'] ?? 'info@tasfyeh.ps', 'نرد خلال 24 ساعة'],
          ['📸', '@tasfyeh.ps', 'Instagram · TikTok: @tasfyeh'],
        ];
        foreach($contactInfo as $item): ?>
        <div class="contact-item">
          <span class="contact-icon"><?= $item[0] ?></span>
          <div><div class="contact-text"><?= htmlspecialchars($item[1]) ?></div><span class="contact-sub"><?= htmlspecialchars($item[2]) ?></span></div>
        </div>
        <?php endforeach; ?>
      </div>
      <a href="https://maps.google.com/?q=رام+الله+البيرة+البالوع" target="_blank" class="map-placeholder">
        🗺️
        <div class="map-text">افتح في خرائط Google</div>
        <div class="map-addr">رام الله - البيرة، البالوع، شارع الوكالات، خلف بلازا مول، مجمع النعمان</div>
      </a>
    </div>
  </div>
</section>

<!-- Footer -->
<footer>
  <div class="footer-inner">
    <div class="footer-brand">
      <h3>التصفية <span>العالمية</span></h3>
      <p>متجرك المفضل للماركات العالمية في فلسطين — أكثر من 11 فرع بأفضل الأسعار وأجود المنتجات الأصلية.</p>
      <div class="socials">
        <a href="<?= htmlspecialchars($_settings['instagram'] ?? 'https://instagram.com/tasfyeh.ps') ?>" target="_blank" class="social">📸</a>
        <a href="https://tiktok.com/@tasfyeh" target="_blank" class="social">🎵</a>
        <a href="tel:<?= preg_replace('/\D/','',$_settings['phone']??'022424433') ?>" class="social">📞</a>
        <a href="mailto:<?= htmlspecialchars($_settings['email']??'info@tasfyeh.ps') ?>" class="social">✉️</a>
      </div>
    </div>
    <div class="footer-col">
      <h4>الفئات</h4>
      <ul>
        <?php if(!empty($_cats)): foreach(array_slice($_cats,0,6) as $c): ?>
        <li><a href="shop.php?cat=<?= urlencode($c['slug'] ?? '') ?>"><?= htmlspecialchars($c['name'] ?? '') ?></a></li>
        <?php endforeach; else: ?>
        <li><a href="shop.php?cat=shoes">الأحذية</a></li>
        <li><a href="shop.php?cat=clothes">الملابس</a></li>
        <li><a href="shop.php?cat=bags">الشنط</a></li>
        <li><a href="shop.php?cat=accessories">الاكسسوارات</a></li>
        <li><a href="shop.php?cat=perfumes">العطور</a></li>
        <?php endif; ?>
      </ul>
    </div>
    <div class="footer-col">
      <h4>روابط سريعة</h4>
      <ul>
        <li><a href="#">من نحن</a></li>
        <li><a href="#branches">فروعنا</a></li>
        <li><a href="#">سياسة الإرجاع</a></li>
        <li><a href="#">الأسئلة الشائعة</a></li>
        <li><a href="#contact">تواصل معنا</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h4>النشرة البريدية</h4>
      <p style="font-size:13px;color:var(--footer-muted);line-height:1.6;margin-bottom:0">اشترك واحصل على عروض حصرية مباشرة لبريدك</p>
      <div class="newsletter-form">
        <input type="email" placeholder="بريدك الإلكتروني" id="nlInput">
        <button onclick="subscribe()">اشترك</button>
      </div>
    </div>
  </div>
  <div class="footer-bottom">
    <span>© جميع الحقوق محفوظة – التصفية العالمية 2025</span>
    <span>صُنع بـ ❤️ في فلسطين 🇵🇸</span>
  </div>
</footer>

<!-- Float Cart -->
<a href="cart.php" class="float-cart">
  🛒<span class="float-count" id="floatCount"><?= $_cartCount ?: '' ?></span>
</a>

<!-- Swiper JS -->
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

<script>
const _CSRF = '<?= $_csrf ?>';

// Theme
let isDark = localStorage.getItem('tasfyeh-theme') === 'dark';
if (isDark) applyTheme(true);

function toggleTheme() { applyTheme(!isDark); }
function applyTheme(dark) {
  isDark = dark;
  document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
  document.getElementById('themeIcon').textContent = dark ? '🌙' : '☀️';
  document.getElementById('themeLbl').textContent  = dark ? 'داكن' : 'فاتح';
  localStorage.setItem('tasfyeh-theme', dark ? 'dark' : 'light');
}

// Countdown
(function countdown() {
  const target = new Date(); target.setDate(target.getDate() + 7);
  function tick() {
    const diff = target - new Date();
    if (diff <= 0) return;
    const pad = n => String(Math.floor(n)).padStart(2,'0');
    document.getElementById('cd-days').textContent  = pad(diff/86400000);
    document.getElementById('cd-hours').textContent = pad((diff%86400000)/3600000);
    document.getElementById('cd-mins').textContent  = pad((diff%3600000)/60000);
    document.getElementById('cd-secs').textContent  = pad((diff%60000)/1000);
  }
  tick(); setInterval(tick, 1000);
})();

// Scroll Reveal
const obs = new IntersectionObserver(entries => {
  entries.forEach(e => { if(e.isIntersecting){ e.target.classList.add('visible'); obs.unobserve(e.target); }});
}, { threshold: 0.1 });
document.querySelectorAll('.reveal').forEach(el => obs.observe(el));

// Sticky Nav
window.addEventListener('scroll',()=>{
  document.getElementById('mainNav').style.boxShadow = scrollY > 8 ? '0 4px 16px rgba(0,0,0,.10)' : '';
},{passive:true});

// Toast
function showToast(msg,type='s',dur=3500){
  const icons = {
    s: '<svg style="color:#34d399" viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
    e: '<svg style="color:#f87171" viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>'
  };
  const t = document.createElement('div');
  t.className = 'toast';
  t.innerHTML = (icons[type] || icons.s) + '<span>'+msg+'</span>';
  document.getElementById('toastBox').appendChild(t);
  setTimeout(() => { t.classList.add('out'); setTimeout(() => t.remove(), 250); }, dur);
}

// Cart
function addToCart(pid, btn) {
  const orig = btn ? btn.innerHTML : '';
  if(btn) { btn.disabled = true; btn.textContent = '...'; }
  fetch('cart-action.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'action=add&product_id=' + pid + '&quantity=1&csrf_token=' + _CSRF
  })
  .then(r => r.json())
  .then(d => {
    showToast(d.message, d.success ? 's' : 'e');
    if(d.success) {
      const b = document.getElementById('cartBadge'); if(b) b.textContent = d.count || '';
      const f = document.getElementById('floatCount'); if(f) f.textContent = d.count || '';
    }
  })
  .catch(() => showToast('حدث خطأ', 'e'))
  .finally(() => { if(btn) { btn.disabled = false; btn.innerHTML = orig; } });
}

// Wishlist
function toggleWishlist(pid, btn) {
  fetch('wishlist-action.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'product_id=' + pid + '&csrf_token=' + _CSRF
  })
  .then(r => r.json())
  .then(d => {
    if(d.redirect) { location.href = d.redirect; return; }
    if(d.success) showToast(d.message, d.added ? 's' : 'i');
  });
}

// Mobile Drawer
function drawerOpen() {
  document.getElementById('mobDrawer').classList.add('open');
  document.getElementById('mobOverlay').classList.add('on');
  document.body.style.overflow = 'hidden';
}
function drawerClose() {
  document.getElementById('mobDrawer').classList.remove('open');
  document.getElementById('mobOverlay').classList.remove('on');
  document.body.style.overflow = '';
}

// Newsletter
function subscribe() {
  const inp = document.getElementById('nlInput');
  const btn = inp.nextElementSibling;
  if(inp.value) {
    btn.textContent = '✓ شكراً!';
    inp.value = '';
    setTimeout(() => btn.textContent = 'اشترك', 2000);
    showToast('تم الاشتراك بنجاح', 's');
  } else {
    showToast('الرجاء إدخال البريد الإلكتروني', 'e');
  }
}

// Swiper
document.addEventListener('DOMContentLoaded', function() {
  if (window.innerWidth <= 900) {
    initSwiper();
  }
  
  window.addEventListener('resize', function() {
    if (window.innerWidth <= 900) {
      if (!document.querySelector('.swiper-initialized')) {
        initSwiper();
      }
    }
  });
});

function initSwiper() {
  const swiperEl = document.querySelector('.productsSwiper');
  if (swiperEl && !swiperEl.classList.contains('swiper-initialized')) {
    new Swiper(swiperEl, {
      slidesPerView: 'auto',
      spaceBetween: 15,
      loop: true,
      pagination: {
        el: '.swiper-pagination',
        clickable: true,
      },
      navigation: {
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button-prev',
      },
      autoplay: {
        delay: 3000,
        disableOnInteraction: false,
      },
      breakpoints: {
        320: { slidesPerView: 1.2, spaceBetween: 10 },
        480: { slidesPerView: 1.5, spaceBetween: 15 },
        640: { slidesPerView: 2, spaceBetween: 15 },
        768: { slidesPerView: 2.2, spaceBetween: 15 }
      }
    });
    swiperEl.classList.add('swiper-initialized');
  }
}
</script>
</body>
</html>