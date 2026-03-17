<?php
if (!isset($_no_header_init)) {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/functions.php';
}
startSession();
$_lang      = lang();
$_dir = appDir();
$_isAr      = isAr();
$_siteName  = siteName();
$_cartCount = getCartCount();
$_wishCount = isLoggedIn() ? getWishlistCount() : 0;
$_user      = currentUser();
$_cats      = getCategories();
$_settings  = getAllSettings();
$_isAdmin   = isAdmin();
$_curPage   = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="<?= isAr() ? 'ar' : 'en' ?>" dir="<?= appDir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=5">
<meta name="theme-color" content="#e63946">
<title><?= isset($pageTitle) ? e($pageTitle).' — '.e($_siteName) : e($_siteName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════
   DESIGN TOKENS
═══════════════════════════════════════ */
:root {
  --red:         #e63946;
  --red-d:       #c1121f;
  --red-l:       #ff6b6b;
  --red-5:       rgba(230,57,70,.05);
  --red-10:      rgba(230,57,70,.10);
  --red-20:      rgba(230,57,70,.20);

  --ink:         #111317;
  --ink-2:       #1e2128;
  --ink-3:       #2d333d;
  --body:        #414a57;
  --muted:       #6b7380;
  --subtle:      #9aa3af;
  --line:        #e4e7ec;
  --line-2:      #f1f3f6;
  --bg:          #f7f8fa;
  --white:       #ffffff;

  --font:        <?= $_isAr ? "'Tajawal', sans-serif" : "'Plus Jakarta Sans', sans-serif" ?>;
  --font-ar:     'Tajawal', sans-serif;
  --font-en:     'Plus Jakarta Sans', sans-serif;

  --r-xs:   4px;
  --r-sm:   8px;
  --r-md:  12px;
  --r-lg:  16px;
  --r-xl:  24px;
  --r-2xl: 32px;
  --r-full:999px;

  --sh-xs:  0 1px 2px rgba(0,0,0,.05);
  --sh-sm:  0 2px 8px rgba(0,0,0,.08), 0 1px 3px rgba(0,0,0,.04);
  --sh-md:  0 4px 16px rgba(0,0,0,.10), 0 2px 5px rgba(0,0,0,.06);
  --sh-lg:  0 8px 32px rgba(0,0,0,.12), 0 3px 8px rgba(0,0,0,.07);
  --sh-xl:  0 20px 60px rgba(0,0,0,.15), 0 6px 16px rgba(0,0,0,.09);
  --sh-red: 0 4px 14px rgba(230,57,70,.35);

  --hdr-top:   42px;
  --hdr-h:     70px;
  --nav-h:     46px;
  --hdr-total: calc(var(--hdr-top) + var(--hdr-h) + var(--nav-h));

  --ease:      cubic-bezier(.4,0,.2,1);
  --ease-out:  cubic-bezier(0,0,.2,1);
  --t-fast:  150ms;
  --t-base:  200ms;
  --t-slow:  350ms;
}

/* ═══ RESET ═══ */
*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
html { scroll-behavior:smooth; -webkit-tap-highlight-color:transparent; font-size:16px; }
body {
  font-family:var(--font); color:var(--body); background:var(--bg);
  line-height:1.6; min-height:100vh; overflow-x:hidden;
  -webkit-font-smoothing:antialiased; -moz-osx-font-smoothing:grayscale;
}
a   { text-decoration:none; color:inherit; }
ul  { list-style:none; }
img { max-width:100%; display:block; }
button { background:none; border:none; cursor:pointer; font-family:var(--font); }
input,select,textarea { font-family:var(--font); }
svg { display:inline-block; vertical-align:middle; flex-shrink:0; }

/* ═══ LAYOUT ═══ */
.container    { max-width:1340px; margin:0 auto; padding:0 24px; }
.container-sm { max-width:900px;  margin:0 auto; padding:0 24px; }
.section      { padding:72px 0; }
.section-md   { padding:52px 0; }
.section-sm   { padding:36px 0; }

/* ═══ TYPOGRAPHY ═══ */
.t-h1  { font-size:clamp(1.8rem,4vw,2.8rem); font-weight:800; line-height:1.15; color:var(--ink); }
.t-h2  { font-size:clamp(1.4rem,3vw,2rem);   font-weight:800; line-height:1.2;  color:var(--ink); }
.t-h3  { font-size:clamp(1.1rem,2vw,1.4rem); font-weight:700; line-height:1.3;  color:var(--ink); }
.t-sub { font-size:.875rem; color:var(--muted); }
.t-tag {
  display:inline-flex; align-items:center; gap:6px;
  background:var(--red-10); color:var(--red);
  font-size:.72rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase;
  padding:4px 12px; border-radius:var(--r-full);
}

/* ═══ GRID ═══ */
.grid      { display:grid; gap:20px; }
.g-2       { grid-template-columns:repeat(2,1fr); }
.g-3       { grid-template-columns:repeat(3,1fr); }
.g-4       { grid-template-columns:repeat(4,1fr); }
.g-5       { grid-template-columns:repeat(5,1fr); }
.g-auto-3  { grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); }
.g-auto-4  { grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); }
.g-auto-5  { grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); }

/* ═══ BUTTONS ═══ */
.btn {
  display:inline-flex; align-items:center; justify-content:center; gap:8px;
  padding:10px 22px; border-radius:var(--r-full);
  font-size:.875rem; font-weight:700; letter-spacing:.01em;
  transition:all var(--t-base) var(--ease);
  cursor:pointer; border:none; white-space:nowrap; font-family:var(--font);
  position:relative; overflow:hidden;
}
.btn-primary { background:var(--red); color:#fff; box-shadow:var(--sh-red); }
.btn-primary:hover { background:var(--red-d); transform:translateY(-1px); box-shadow:0 6px 20px rgba(230,57,70,.4); }
.btn-dark    { background:var(--ink); color:#fff; }
.btn-dark:hover { background:var(--ink-2); transform:translateY(-1px); }
.btn-outline { background:transparent; border:1.5px solid var(--line); color:var(--body); }
.btn-outline:hover { border-color:var(--red); color:var(--red); background:var(--red-5); }
.btn-ghost   { background:transparent; color:var(--muted); }
.btn-ghost:hover { background:var(--line-2); color:var(--ink); }
.btn-sm  { padding:7px 16px; font-size:.8rem; }
.btn-xs  { padding:5px 12px; font-size:.75rem; }
.btn-lg  { padding:13px 30px; font-size:.95rem; }
.btn-xl  { padding:15px 36px; font-size:1rem; }
.btn-block{ width:100%; }
.btn-icon { width:40px; height:40px; padding:0; border-radius:var(--r-md); flex-shrink:0; }
.btn:active { transform:scale(.97); }

/* ═══ FORMS ═══ */
.form-group { margin-bottom:20px; }
.form-label { display:block; font-size:.82rem; font-weight:600; color:var(--ink-3); margin-bottom:7px; }
.form-label.req::after { content:" *"; color:var(--red); }
.form-control {
  width:100%; padding:11px 14px;
  border:1.5px solid var(--line); border-radius:var(--r-md);
  font-size:.875rem; color:var(--ink-3); background:var(--white);
  outline:none; transition:border-color var(--t-base), box-shadow var(--t-base);
  font-family:var(--font); line-height:1.5;
}
.form-control::placeholder { color:var(--subtle); }
.form-control:focus { border-color:var(--red); box-shadow:0 0 0 3px var(--red-10); }
.form-control.error { border-color:#f87171; background:#fff5f5; }
textarea.form-control { resize:vertical; min-height:100px; }
.form-hint  { font-size:.76rem; color:var(--muted); margin-top:5px; }
.form-error { font-size:.76rem; color:var(--red); margin-top:5px; display:flex; align-items:center; gap:4px; }

/* ═══ BADGES ═══ */
.badge {
  display:inline-flex; align-items:center; justify-content:center;
  padding:3px 8px; border-radius:var(--r-xs);
  font-size:.68rem; font-weight:800; letter-spacing:.03em; line-height:1;
}
.badge-red   { background:var(--red);   color:#fff; }
.badge-dark  { background:var(--ink);   color:#fff; }
.badge-green { background:#059669;      color:#fff; }
.badge-sale  { background:var(--red);   color:#fff; border-radius:var(--r-xs); }
.badge-new   { background:#0284c7;      color:#fff; }
.badge-out   { background:var(--muted); color:#fff; }
.badge-hot   { background:#d97706;      color:#fff; }

/* ═══ PRODUCT CARD (global) ═══ */
.pc {
  background:var(--white); border-radius:var(--r-lg);
  border:1px solid var(--line); overflow:hidden;
  display:flex; flex-direction:column;
  transition:transform var(--t-base) var(--ease),
             box-shadow var(--t-base) var(--ease),
             border-color var(--t-base) var(--ease);
}
.pc:hover { transform:translateY(-5px); box-shadow:var(--sh-lg); border-color:transparent; }
.pc__img-wrap {
  position:relative; overflow:hidden;
  background:var(--line-2); aspect-ratio:1/1;
}
.pc__img-wrap img {
  width:100%; height:100%; object-fit:cover;
  transition:transform 500ms var(--ease);
}
.pc:hover .pc__img-wrap img { transform:scale(1.06); }
.pc__badges {
  position:absolute; top:10px; <?= $_isAr?'right':'left' ?>:10px;
  display:flex; flex-direction:column; gap:4px; z-index:2;
}
.pc__wishlist {
  position:absolute; top:10px; <?= $_isAr?'left':'right' ?>:10px;
  width:34px; height:34px; border-radius:var(--r-sm);
  background:var(--white); box-shadow:var(--sh-sm);
  display:flex; align-items:center; justify-content:center;
  cursor:pointer; transition:all var(--t-base) var(--ease);
  z-index:2; opacity:0; transform:scale(.85); border:none;
}
.pc:hover .pc__wishlist { opacity:1; transform:scale(1); }
.pc__wishlist:hover, .pc__wishlist.active { background:var(--red); color:#fff; }
.pc__wishlist svg { width:16px; height:16px; }
.pc__body  { padding:14px; flex:1; display:flex; flex-direction:column; }
.pc__cat   { font-size:.7rem; font-weight:700; color:var(--subtle); text-transform:uppercase; letter-spacing:.06em; margin-bottom:5px; }
.pc__name  {
  font-size:.9rem; font-weight:700; color:var(--ink-3); line-height:1.4;
  margin-bottom:8px; display:-webkit-box; -webkit-line-clamp:2;
  -webkit-box-orient:vertical; overflow:hidden; transition:color var(--t-base);
}
.pc__name:hover { color:var(--red); }
.pc__rating  { display:flex; align-items:center; gap:5px; margin-bottom:8px; }
.pc__stars   { display:flex; gap:1px; }
.pc__star    { width:11px; height:11px; }
.pc__rcount  { font-size:.72rem; color:var(--muted); }
.pc__price   { display:flex; align-items:baseline; gap:8px; flex-wrap:wrap; margin-top:auto; margin-bottom:12px; }
.pc__price-now { font-size:1.05rem; font-weight:800; color:var(--red); }
.pc__price-was { font-size:.8rem; color:var(--subtle); text-decoration:line-through; }
.pc__save    { font-size:.68rem; font-weight:700; color:#059669; background:#d1fae5; padding:2px 6px; border-radius:var(--r-full); }
.pc__footer  { padding:0 14px 14px; }
.pc__atc {
  width:100%; height:40px; border-radius:var(--r-md);
  background:var(--ink-2); color:#fff;
  font-size:.82rem; font-weight:700;
  display:flex; align-items:center; justify-content:center; gap:8px;
  transition:all var(--t-base) var(--ease);
  cursor:pointer; border:none; font-family:var(--font);
}
.pc__atc:hover { background:var(--red); box-shadow:var(--sh-red); transform:translateY(-1px); }
.pc__atc:active { transform:scale(.97); }
.pc__atc.out { background:var(--line); color:var(--subtle); cursor:not-allowed; }
.pc__atc.out:hover { background:var(--line); box-shadow:none; transform:none; }

/* ═══ STARS ═══ */
.stars  { display:inline-flex; gap:2px; }
.star-f { color:#f59e0b; }
.star-e { color:#e5e7eb; }

/* ═══ STATUS ═══ */
.st { display:inline-flex; align-items:center; gap:5px; padding:4px 11px; border-radius:var(--r-full); font-size:.74rem; font-weight:700; }
.st-pending    { background:#fef3c7; color:#92400e; }
.st-confirmed  { background:#dbeafe; color:#1e40af; }
.st-processing { background:#e0e7ff; color:#3730a3; }
.st-shipped    { background:#d1fae5; color:#065f46; }
.st-delivered  { background:#d1fae5; color:#065f46; }
.st-cancelled  { background:#fee2e2; color:#991b1b; }

/* ═══ BREADCRUMB ═══ */
.breadcrumb { display:flex; align-items:center; gap:6px; flex-wrap:wrap; font-size:.8rem; color:var(--subtle); }
.breadcrumb a { color:var(--muted); transition:color var(--t-base); }
.breadcrumb a:hover { color:var(--red); }
.breadcrumb .sep { color:var(--line); font-size:.7rem; }
.breadcrumb .cur { color:var(--ink-3); font-weight:600; }

/* ═══ FLASH ═══ */
.flash-wrap { background:var(--white); border-bottom:1px solid var(--line); }
.flash { display:flex; align-items:center; gap:10px; padding:12px 0; font-size:.875rem; font-weight:500; }
.flash-s { color:#065f46; }
.flash-e { color:#991b1b; }
.flash-i { color:#1e40af; }
.flash-close { margin-<?= $_isAr?'right':'left' ?>:auto; opacity:.5; font-size:1rem; transition:opacity var(--t-base); }
.flash-close:hover { opacity:1; }

/* ═══ EMPTY STATE ═══ */
.empty {
  text-align:center; padding:72px 24px;
  background:var(--white); border-radius:var(--r-xl);
  border:1.5px dashed var(--line);
}
.empty__icon {
  width:64px; height:64px; background:var(--line-2);
  border-radius:var(--r-lg); display:flex; align-items:center;
  justify-content:center; margin:0 auto 20px; color:var(--subtle);
}
.empty h3 { font-size:1.1rem; font-weight:800; color:var(--ink-3); margin-bottom:8px; }
.empty p  { color:var(--muted); font-size:.9rem; margin-bottom:24px; }

/* ═══ PAGINATION ═══ */
.pag { display:flex; align-items:center; justify-content:center; gap:6px; flex-wrap:wrap; padding-top:40px; }
.pag a, .pag span {
  display:flex; align-items:center; justify-content:center;
  width:38px; height:38px; border-radius:var(--r-md);
  font-size:.875rem; font-weight:600;
  border:1.5px solid var(--line); color:var(--muted); background:var(--white);
  transition:all var(--t-base) var(--ease);
}
.pag a:hover { border-color:var(--red); color:var(--red); background:var(--red-5); }
.pag li.act a { background:var(--red); color:#fff; border-color:var(--red); }

/* ═══ TOAST ═══ */
#toastBox {
  position:fixed; bottom:24px; <?= $_isAr?'right':'left' ?>:24px;
  z-index:9999; display:flex; flex-direction:column; gap:10px; pointer-events:none;
}
.toast {
  display:flex; align-items:center; gap:12px;
  padding:14px 18px; border-radius:var(--r-lg);
  background:var(--ink-2); color:#fff;
  font-size:.875rem; font-weight:500;
  box-shadow:var(--sh-xl); min-width:250px; max-width:360px;
  animation:tIn var(--t-slow) var(--ease-out) both;
  pointer-events:all;
}
.toast.out { animation:tOut var(--t-base) var(--ease) both; }
.t-icon { flex-shrink:0; }
.t-s .t-icon { color:#34d399; }
.t-e .t-icon { color:#f87171; }
.t-i .t-icon { color:#60a5fa; }
@keyframes tIn  { from{opacity:0;transform:translateY(16px) scale(.95)} to{opacity:1;transform:none} }
@keyframes tOut { from{opacity:1;transform:none} to{opacity:0;transform:translateY(-10px) scale(.95)} }

/* ═══════════════════════
   TOPBAR
═══════════════════════ */
.topbar {
  height:var(--hdr-top); background:var(--ink-2);
  display:flex; align-items:center; overflow:hidden;
}
.topbar__inner { display:flex; align-items:center; height:100%; gap:0; }
.topbar__scroll { flex:1; overflow:hidden; position:relative; }
.topbar__scroll::before,
.topbar__scroll::after {
  content:''; position:absolute; top:0; bottom:0; width:40px; z-index:2; pointer-events:none;
}
.topbar__scroll::before { <?= $_isAr?'right':'left' ?>:0; background:linear-gradient(to <?= $_isAr?'left':'right' ?>,var(--ink-2),transparent); }
.topbar__scroll::after  { <?= $_isAr?'left':'right' ?>:0; background:linear-gradient(to <?= $_isAr?'right':'left' ?>,var(--ink-2),transparent); }
.topbar__track {
  display:flex; gap:0; white-space:nowrap;
  animation:scroll 35s linear infinite;
}
[dir=rtl] .topbar__track { animation:scrollRtl 35s linear infinite; }
.topbar__item {
  display:inline-flex; align-items:center; gap:8px;
  font-size:.74rem; color:rgba(255,255,255,.6);
  padding:0 28px; border-<?= $_isAr?'left':'right' ?>:1px solid rgba(255,255,255,.08);
}
.topbar__item strong { color:rgba(255,255,255,.9); }
.topbar__item svg { width:13px; height:13px; color:var(--red); flex-shrink:0; }
@keyframes scroll    { from{transform:translateX(0)}   to{transform:translateX(-50%)} }
@keyframes scrollRtl { from{transform:translateX(0)}   to{transform:translateX(50%)} }

.topbar__links {
  display:flex; align-items:center; flex-shrink:0;
  border-<?= $_isAr?'right':'left' ?>:1px solid rgba(255,255,255,.08);
  padding-<?= $_isAr?'right':'left' ?>:16px;
  gap:0;
}
.topbar__link {
  display:inline-flex; align-items:center; gap:5px;
  font-size:.74rem; color:rgba(255,255,255,.55);
  padding:0 10px; height:var(--hdr-top);
  transition:color var(--t-base); white-space:nowrap;
}
.topbar__link:hover { color:#fff; }
.topbar__link svg { width:12px; height:12px; flex-shrink:0; }
.topbar__divider { width:1px; height:14px; background:rgba(255,255,255,.1); flex-shrink:0; }

/* ═══════════════════════
   HEADER  ★ FIXED MOBILE
═══════════════════════ */
.header {
  height:var(--hdr-h); background:var(--white);
  border-bottom:1px solid var(--line);
  position:sticky; top:0; z-index:200;
  transition:box-shadow var(--t-base);
}
.header.stuck { box-shadow:var(--sh-md); }

/* Desktop layout: single row */
.header__inner {
  display:flex; align-items:center;
  gap:16px; height:100%;
}

/* Logo */
.logo { display:flex; align-items:center; gap:10px; flex-shrink:0; }
.logo__icon {
  width:40px; height:40px; background:var(--red);
  border-radius:var(--r-md); display:flex; align-items:center; justify-content:center;
  flex-shrink:0; box-shadow:var(--sh-red);
}
.logo__icon svg { width:22px; height:22px; color:#fff; }
.logo__text  { line-height:1.15; }
.logo__name  { font-size:.98rem; font-weight:800; color:var(--ink); display:block; }
.logo__sub   { font-size:.63rem; color:var(--subtle); font-weight:500; display:block; }

/* Search */
.hdr-search { flex:1; max-width:580px; }
.search-bar {
  display:flex; align-items:stretch; height:46px;
  border:1.5px solid var(--line); border-radius:var(--r-full);
  background:var(--white); overflow:hidden;
  transition:border-color var(--t-base), box-shadow var(--t-base);
}
.search-bar:focus-within { border-color:var(--red); box-shadow:0 0 0 3px var(--red-10); }
.search-cat-btn {
  display:flex; align-items:center; gap:6px;
  padding:0 14px; border-<?= $_isAr?'left':'right' ?>:1.5px solid var(--line);
  font-size:.78rem; font-weight:600; color:var(--body);
  background:var(--line-2); cursor:pointer; white-space:nowrap;
  position:relative; transition:background var(--t-base); flex-shrink:0;
}
.search-cat-btn:hover { background:var(--line); }
.search-cat-btn svg { width:12px; height:12px; color:var(--subtle); }
.search-cat-btn select {
  position:absolute; inset:0; opacity:0; cursor:pointer;
  width:100%; font-family:var(--font);
}
.search-input {
  flex:1; border:none; outline:none; padding:0 16px;
  font-size:.875rem; color:var(--ink-3); background:transparent;
  font-family:var(--font); min-width:0;
}
.search-input::placeholder { color:var(--subtle); }
.search-submit {
  width:50px; background:var(--red); color:#fff; flex-shrink:0;
  display:flex; align-items:center; justify-content:center;
  cursor:pointer; border:none; transition:background var(--t-base);
}
.search-submit:hover { background:var(--red-d); }
.search-submit svg { width:18px; height:18px; }

/* Header Actions */
.hdr-actions { display:flex; align-items:center; gap:4px; flex-shrink:0; }
.hdr-btn {
  position:relative; width:44px; height:44px;
  display:flex; align-items:center; justify-content:center;
  border-radius:var(--r-md); color:var(--body);
  transition:all var(--t-base); text-decoration:none; cursor:pointer;
}
.hdr-btn:hover { background:var(--line-2); color:var(--red); }
.hdr-btn svg { width:22px; height:22px; }
.hdr-badge {
  position:absolute; top:5px; <?= $_isAr?'left':'right' ?>:5px;
  min-width:17px; height:17px; border-radius:var(--r-full);
  background:var(--red); color:#fff;
  font-size:.6rem; font-weight:800;
  display:flex; align-items:center; justify-content:center; padding:0 4px;
  border:2px solid var(--white); line-height:1;
}
.hdr-badge:empty { display:none; }

.hdr-account {
  display:flex; align-items:center; gap:8px;
  padding:8px 14px; border-radius:var(--r-full);
  border:1.5px solid var(--line); color:var(--body);
  font-size:.8rem; font-weight:700; cursor:pointer;
  transition:all var(--t-base); text-decoration:none; flex-shrink:0;
}
.hdr-account:hover { border-color:var(--red); color:var(--red); background:var(--red-5); }
.hdr-account .av {
  width:26px; height:26px; border-radius:50%;
  background:var(--red); color:#fff;
  display:flex; align-items:center; justify-content:center;
  font-size:.75rem; font-weight:800; flex-shrink:0;
}
.hdr-account svg { width:16px; height:16px; }

/* ═══════════════════════
   NAVBAR
═══════════════════════ */
.navbar {
  height:var(--nav-h); background:var(--white);
  border-bottom:1px solid var(--line);
  position:sticky; top:var(--hdr-h); z-index:199;
}
.navbar__inner { display:flex; align-items:center; height:100%; }

.nav-item { position:relative; height:100%; display:flex; align-items:center; }
.nav-link {
  display:flex; align-items:center; gap:5px;
  height:100%; padding:0 15px;
  font-size:.84rem; font-weight:600; color:var(--body);
  white-space:nowrap; cursor:pointer; position:relative;
  transition:color var(--t-base);
}
.nav-link::after {
  content:''; position:absolute; bottom:0;
  left:15px; right:15px; height:2px; background:var(--red);
  transform:scaleX(0); transition:transform var(--t-base) var(--ease);
  transform-origin:center;
}
.nav-link:hover, .nav-link.act { color:var(--red); }
.nav-link:hover::after, .nav-link.act::after { transform:scaleX(1); }
.nav-link svg { width:13px; height:13px; color:var(--subtle); transition:transform var(--t-base); }
.nav-item:hover .nav-link svg { transform:rotate(180deg); color:var(--red); }

.nav-badge {
  background:var(--red); color:#fff;
  font-size:.6rem; font-weight:800; padding:2px 6px;
  border-radius:var(--r-full); line-height:1.3;
}

/* Dropdown */
.dropdown {
  display:none; position:absolute;
  top:100%; <?= $_isAr?'right':'left' ?>:0;
  background:var(--white); border:1px solid var(--line);
  border-top:none; border-radius:0 0 var(--r-lg) var(--r-lg);
  box-shadow:var(--sh-xl); z-index:300; min-width:260px;
  animation:ddIn var(--t-base) var(--ease-out);
}
.dropdown.mega { min-width:580px; }
.nav-item:hover .dropdown { display:block; }
@keyframes ddIn { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:none} }

.dd-item {
  display:flex; align-items:center; gap:12px;
  padding:12px 18px; font-size:.86rem; color:var(--body);
  border-bottom:1px solid var(--line-2);
  transition:all var(--t-fast) var(--ease); cursor:pointer;
}
.dd-item:last-child { border-bottom:none; }
.dd-item:hover {
  background:var(--red-5); color:var(--red);
  padding-<?= $_isAr?'right':'left' ?>:22px;
}
.dd-icon {
  width:34px; height:34px; border-radius:var(--r-sm);
  background:var(--line-2); display:flex; align-items:center;
  justify-content:center; flex-shrink:0; transition:all var(--t-fast);
}
.dd-item:hover .dd-icon { background:var(--red-10); color:var(--red); }
.dd-icon svg { width:16px; height:16px; }
.dd-text strong { display:block; font-size:.85rem; font-weight:700; }
.dd-text span   { font-size:.73rem; color:var(--subtle); }

.mega-grid { display:grid; grid-template-columns:repeat(3,1fr); }
.mega-col  {
  padding:16px 14px;
  border-<?= $_isAr?'left':'right' ?>:1px solid var(--line-2);
}
.mega-col:<?= $_isAr?'first':'last' ?>-child { border:none; }
.mega-col-ttl {
  font-size:.7rem; font-weight:800; color:var(--subtle);
  text-transform:uppercase; letter-spacing:.08em;
  padding-bottom:8px; margin-bottom:6px;
  border-bottom:1px solid var(--line-2);
}
.mega-item {
  display:flex; align-items:center; gap:8px;
  padding:8px 10px; border-radius:var(--r-sm);
  font-size:.84rem; color:var(--body);
  transition:all var(--t-fast); cursor:pointer;
}
.mega-item:hover { background:var(--red-5); color:var(--red); }
.mega-item svg { width:13px; height:13px; color:var(--line); transition:color var(--t-fast); }
.mega-item:hover svg { color:var(--red); }

/* Phone in nav */
.nav-phone {
  display:flex; align-items:center; gap:7px;
  margin-<?= $_isAr?'right':'left' ?>:auto;
  padding:0 12px; border-<?= $_isAr?'right':'left' ?>:1px solid var(--line);
  height:100%; font-size:.8rem; font-weight:700; color:var(--ink-3);
}
.nav-phone svg { width:14px; height:14px; color:var(--red); }

/* ═══════════════════════
   MOBILE BUTTON (hamburger)
═══════════════════════ */
.mob-btn {
  display:none; width:40px; height:40px; flex-shrink:0;
  border-radius:var(--r-md); color:var(--body);
  align-items:center; justify-content:center;
  cursor:pointer; transition:all var(--t-base);
}
.mob-btn:hover { background:var(--line-2); color:var(--red); }
.mob-btn svg { width:22px; height:22px; }

/* Mobile search toggle button */
.mob-search-btn {
  display:none; width:40px; height:40px; flex-shrink:0;
  border-radius:var(--r-md); color:var(--body);
  align-items:center; justify-content:center;
  cursor:pointer; transition:all var(--t-base);
}
.mob-search-btn:hover { background:var(--line-2); color:var(--red); }
.mob-search-btn svg   { width:22px; height:22px; }

/* Mobile expandable search bar */
.mob-search-bar {
  display:none; /* shown via JS toggle */
  position:absolute; top:100%; left:0; right:0;
  padding:10px 14px;
  background:var(--white); border-bottom:1px solid var(--line);
  box-shadow:var(--sh-md); z-index:198;
}
.mob-search-bar .search-bar {
  height:42px; border-radius:var(--r-md);
}
.mob-search-bar.open { display:block; }

/* ═══════════════════════
   MOBILE DRAWER
═══════════════════════ */
.mob-drawer {
  display:none; position:fixed;
  top:0; bottom:0; <?= $_isAr?'right':'left' ?>:-100%;
  width:min(300px,90vw); background:var(--white);
  z-index:600; flex-direction:column; box-shadow:var(--sh-xl);
  transition:<?= $_isAr?'right':'left' ?> var(--t-slow) var(--ease);
  overflow-y:auto;
}
.mob-drawer.open { <?= $_isAr?'right':'left' ?>:0; }
.mob-overlay {
  display:none; position:fixed; inset:0;
  background:rgba(0,0,0,.45); z-index:599;
  backdrop-filter:blur(3px);
}
.mob-overlay.on { display:block; }
.mob-top {
  display:flex; align-items:center; justify-content:space-between;
  padding:16px 18px; border-bottom:1px solid var(--line); flex-shrink:0;
}
.mob-close {
  width:34px; height:34px; border-radius:var(--r-sm);
  background:var(--line-2); display:flex; align-items:center;
  justify-content:center; cursor:pointer; transition:all var(--t-base);
}
.mob-close:hover { background:var(--red); color:#fff; }
.mob-close svg { width:16px; height:16px; }
.mob-search { padding:12px 16px; border-bottom:1px solid var(--line); flex-shrink:0; }
.mob-nav-link {
  display:flex; align-items:center; justify-content:space-between;
  padding:13px 20px; font-size:.9rem; font-weight:600; color:var(--body);
  border-bottom:1px solid var(--line-2); transition:all var(--t-fast);
}
.mob-nav-link:hover, .mob-nav-link.act {
  color:var(--red); background:var(--red-5);
  padding-<?= $_isAr?'right':'left' ?>:26px;
}
.mob-nav-link svg { width:14px; height:14px; color:var(--subtle); }
.mob-cats-ttl {
  padding:10px 20px; font-size:.7rem; font-weight:800; color:var(--subtle);
  text-transform:uppercase; letter-spacing:.08em; background:var(--line-2);
}
.mob-cat {
  display:flex; align-items:center; gap:10px;
  padding:11px 24px; font-size:.86rem; color:var(--body);
  border-bottom:1px solid var(--line-2); transition:all var(--t-fast);
}
.mob-cat:hover { color:var(--red); background:var(--red-5); }
.mob-cat svg { width:13px; height:13px; color:var(--line); }
.mob-footer { padding:16px 18px; border-top:1px solid var(--line); margin-top:auto; flex-shrink:0; }

/* ═══════════════════════
   RESPONSIVE
═══════════════════════ */

/* Tablet: shrink search */
@media(max-width:1100px) {
  .hdr-search { max-width:420px; }
}

/* ── 900px breakpoint: hide navbar, show hamburger ── */
@media(max-width:900px) {
  :root {
    --hdr-h:    60px;
    --hdr-top:  36px;
    --hdr-total:calc(var(--hdr-top) + 60px);
    --nav-h:    0px;
  }
  .navbar          { display:none; }
  .hdr-search      { display:none; }   /* hidden on desktop row */
  .mob-btn         { display:flex; }
  .mob-search-btn  { display:flex; }
  .mob-drawer      { display:flex; }

  /* header inner: [hamburger] [logo] [spacer] [search-icon] [wish] [cart] */
  .header__inner   { gap:8px; }

  /* hide text label in account button, keep avatar only */
  .hdr-account span      { display:none; }
  .hdr-account           { padding:8px; border-radius:var(--r-full); }
  .hdr-account .av       { width:30px; height:30px; }

  /* wishlist button stays */
  .hdr-btn         { width:40px; height:40px; }

  /* push actions to end */
  .hdr-actions     { margin-<?= $_isAr?'right':'left' ?>:auto; gap:2px; }
}

/* ── 600px: hide topbar ── */
@media(max-width:600px) {
  :root {
    --hdr-top:   0px;
    --hdr-total: 60px;
  }
  .topbar    { display:none; }
  .container { padding:0 14px; }

  /* grid adjustments */
  .g-5,.g-4            { grid-template-columns:repeat(2,1fr); }
  .g-3                 { grid-template-columns:repeat(2,1fr); }
}

/* ── Very small phones ── */
@media(max-width:380px) {
  .g-5,.g-4,.g-3,.g-2 { grid-template-columns:1fr; }
  .logo__sub           { display:none; }
}
</style>
</head>
<body>

<div id="toastBox"></div>
<div class="mob-overlay" id="mobOverlay" onclick="drawerClose()"></div>

<!-- ─────────────── TOPBAR ─────────────── -->
<div class="topbar">
  <div class="container">
    <div class="topbar__inner">
      <div class="topbar__scroll">
        <div class="topbar__track">
          <?php for($i=0;$i<2;$i++): ?>
          <span class="topbar__item">
            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/><path d="M3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H11a1 1 0 001-1V5a1 1 0 00-1-1H3zM14 7a1 1 0 00-1 1v6.05A2.5 2.5 0 0115.95 16H17a1 1 0 001-1v-5a1 1 0 00-.293-.707L16 7.586A1 1 0 0015.293 7H14z"/></svg>
            <?= __t('شحن مجاني فوق','Free shipping over') ?> <strong><?= formatPrice(freeShippingMin()) ?></strong>
          </span>
          <span class="topbar__item">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
            <?= __t('توصيل 24-48 ساعة','Delivery 24-48 hours') ?>
          </span>
          <span class="topbar__item">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            <?= __t('إرجاع مجاني خلال 14 يوم','Free returns 14 days') ?>
          </span>
          <span class="topbar__item">
            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/></svg>
            <?= __t('دعم 24/7','Support 24/7') ?>
          </span>
          <span class="topbar__item">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
            <?= __t('دفع آمن 100%','100% Secure Payment') ?>
          </span>
          <?php endfor; ?>
        </div>
      </div>
      <div class="topbar__links">
        <?php if ($_user): ?>
          <a href="dashboard.php" class="topbar__link">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>
            <?= e(explode(' ',$_user['name'])[0]) ?>
          </a>
          <?php if ($_isAdmin): ?>
            <div class="topbar__divider"></div>
            <a href="admin.php" class="topbar__link">
              <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/></svg>
              <?= __t('الإدارة','Admin') ?>
            </a>
          <?php endif; ?>
          <div class="topbar__divider"></div>
          <a href="logout.php" class="topbar__link">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"/></svg>
            <?= __t('خروج','Logout') ?>
          </a>
        <?php else: ?>
          <a href="login.php" class="topbar__link">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>
            <?= __t('تسجيل الدخول','Login') ?>
          </a>
          <div class="topbar__divider"></div>
          <a href="register.php" class="topbar__link"><?= __t('حساب جديد','Register') ?></a>
        <?php endif; ?>
        <div class="topbar__divider"></div>
        <a href="<?= langToggleUrl() ?>" class="topbar__link">
          <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7 2a1 1 0 011 1v1h3a1 1 0 110 2H9.578a18.87 18.87 0 01-1.724 4.78c.29.354.596.696.914 1.026a1 1 0 11-1.44 1.389c-.188-.196-.373-.396-.554-.6a19.098 19.098 0 01-3.107 3.567 1 1 0 01-1.334-1.49 17.087 17.087 0 003.13-3.733 18.992 18.992 0 01-1.487-3.754 1 1 0 111.94-.477c.267 1.02.686 1.98 1.234 2.859A17.354 17.354 0 009.028 9H3a1 1 0 110-2h3V3a1 1 0 011-1zm6 6a1 1 0 01.894.553l2.991 5.992a.869.869 0 01.02.037l.99 1.98a1 1 0 11-1.79.895L15.383 16h-4.764l-.724 1.447a1 1 0 11-1.788-.894l.99-1.98.019-.038 2.99-5.992A1 1 0 0113 8zm-1.382 6h2.764L13 11.236 11.618 14z" clip-rule="evenodd"/></svg>
          <?= $_isAr ? 'EN' : 'عر' ?>
        </a>
      </div>
    </div>
  </div>
</div>

<!-- ─────────────── HEADER ─────────────── -->
<header class="header" id="mainHdr">
  <div class="container">
    <div class="header__inner">

      <!-- Hamburger — mobile only -->
      <button class="mob-btn" onclick="drawerOpen()" aria-label="Menu">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
          <path d="M3 6h18M3 12h18M3 18h18"/>
        </svg>
      </button>

      <!-- Logo -->
      <a href="index.php" class="logo">
        <div class="logo__icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/>
          </svg>
        </div>
        <div class="logo__text">
          <span class="logo__name"><?= e($_siteName) ?></span>
          <span class="logo__sub"><?= e($_isAr ? ($_settings['site_tagline_ar']??'') : ($_settings['site_tagline_en']??'')) ?></span>
        </div>
      </a>

      <!-- Search — desktop only (hidden on mobile via CSS, expanded via toggle) -->
      <div class="hdr-search">
        <form action="shop.php" method="GET" class="search-bar">
          <div class="search-cat-btn">
            <span><?= __t('الأقسام','Categories') ?></span>
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
            <select name="cat" onchange="this.form.submit()">
              <option value=""><?= __t('الكل','All') ?></option>
              <?php foreach ($_cats as $c): ?>
                <option value="<?= e($c['slug']) ?>" <?= ($_GET['cat']??'')===$c['slug']?'selected':'' ?>><?= e(t($c,'name')) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <input type="text" name="q" class="search-input"
                 placeholder="<?= __t('ابحث عن أي منتج...','Search for products...') ?>"
                 value="<?= e($_GET['q']??'') ?>" autocomplete="off">
          <button type="submit" class="search-submit" aria-label="Search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
          </button>
        </form>
      </div>

      <!-- Actions -->
      <div class="hdr-actions">

        <!-- Search icon — mobile only -->
        <button class="mob-search-btn" onclick="mobSearchToggle()" aria-label="Search">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        </button>

        <!-- Wishlist -->
        <a href="wishlist.php" class="hdr-btn" title="<?= __t('المفضلة','Wishlist') ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
          <span class="hdr-badge" id="wishBadge"><?= $_wishCount > 0 ? $_wishCount : '' ?></span>
        </a>

        <!-- Account -->
        <?php if ($_user): ?>
          <a href="dashboard.php" class="hdr-account">
            <div class="av"><?= mb_substr($_user['name'],0,1) ?></div>
            <span><?= e(explode(' ',$_user['name'])[0]) ?></span>
          </a>
        <?php else: ?>
          <a href="login.php" class="hdr-account">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span><?= __t('حسابي','Account') ?></span>
          </a>
        <?php endif; ?>

        <!-- Cart -->
        <a href="cart.php" class="hdr-btn" title="<?= __t('السلة','Cart') ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
          <span class="hdr-badge" id="cartBadge"><?= $_cartCount > 0 ? $_cartCount : '' ?></span>
        </a>
      </div>
    </div>
  </div>

  <!-- Mobile expandable search (drops below header) -->
  <div class="mob-search-bar" id="mobSearchBar">
    <form action="shop.php" method="GET">
      <div class="search-bar">
        <input type="text" name="q" class="search-input"
               placeholder="<?= __t('ابحث عن أي منتج...','Search...') ?>"
               value="<?= e($_GET['q']??'') ?>" autocomplete="off" id="mobSearchInput">
        <button type="submit" class="search-submit">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        </button>
      </div>
    </form>
  </div>
</header>

<!-- ─────────────── NAVBAR ─────────────── -->
<nav class="navbar">
  <div class="container">
    <div class="navbar__inner">
      <div class="nav-item">
        <a href="index.php" class="nav-link <?= $_curPage==='index.php'?'act':'' ?>"><?= __t('الرئيسية','Home') ?></a>
      </div>
      <div class="nav-item">
        <a href="shop.php" class="nav-link <?= $_curPage==='shop.php'?'act':'' ?>">
          <?= __t('المتجر','Shop') ?>
          <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
        </a>
        <div class="dropdown">
          <a href="shop.php" class="dd-item">
            <div class="dd-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM14 11a1 1 0 011 1v1h1a1 1 0 110 2h-1v1a1 1 0 11-2 0v-1h-1a1 1 0 110-2h1v-1a1 1 0 011-1z"/></svg></div>
            <div class="dd-text"><strong><?= __t('كل المنتجات','All Products') ?></strong><span><?= __t('تصفح الكتالوج','Browse catalog') ?></span></div>
          </a>
          <a href="shop.php?filter=featured" class="dd-item">
            <div class="dd-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg></div>
            <div class="dd-text"><strong><?= __t('منتجات مميزة','Featured') ?></strong><span><?= __t('الأكثر مبيعاً','Best sellers') ?></span></div>
          </a>
          <a href="shop.php?filter=new" class="dd-item">
            <div class="dd-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg></div>
            <div class="dd-text"><strong><?= __t('وصل حديثاً','New Arrivals') ?></strong><span><?= __t('أحدث المنتجات','Latest products') ?></span></div>
          </a>
          <a href="shop.php?filter=sale" class="dd-item">
            <div class="dd-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7A.997.997 0 012 10V5a3 3 0 013-3h5c.256 0 .512.098.707.293l7 7zM5 6a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg></div>
            <div class="dd-text"><strong><?= __t('التخفيضات','Deals') ?></strong><span><?= __t('خصومات حتى 70%','Up to 70% off') ?></span></div>
          </a>
        </div>
      </div>
      <div class="nav-item">
        <span class="nav-link">
          <?= __t('الأقسام','Categories') ?>
          <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
        </span>
        <div class="dropdown mega">
          <div class="mega-grid">
            <?php $cols = array_chunk($_cats, max(1,(int)ceil(count($_cats)/3)));
            foreach (array_pad($cols,3,[]) as $col): ?>
            <div class="mega-col">
              <?php foreach ($col as $c): ?>
              <a href="shop.php?cat=<?= urlencode($c['slug']) ?>" class="mega-item">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
                <?= e(t($c,'name')) ?>
              </a>
              <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="nav-item">
        <a href="shop.php?filter=sale" class="nav-link">
          <?= __t('العروض','Offers') ?>
          <span class="nav-badge"><?= __t('جديد','New') ?></span>
        </a>
      </div>
      <div class="nav-item">
        <a href="#contact" class="nav-link"><?= __t('تواصل معنا','Contact') ?></a>
      </div>
      <?php if (!empty($_settings['phone'])): ?>
      <div class="nav-phone" style="margin-<?= $_isAr?'right':'left' ?>:auto">
        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/></svg>
        <span><?= e($_settings['phone']) ?></span>
      </div>
      <?php endif; ?>
    </div>
  </div>
</nav>

<!-- ─────────────── MOBILE DRAWER ─────────────── -->
<div class="mob-drawer" id="mobDrawer">
  <div class="mob-top">
    <a href="index.php" class="logo" onclick="drawerClose()">
      <div class="logo__icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
      </div>
      <div class="logo__text"><span class="logo__name"><?= e($_siteName) ?></span></div>
    </a>
    <button class="mob-close" onclick="drawerClose()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="mob-search">
    <form action="shop.php" method="GET">
      <div class="search-bar" style="height:40px;border-radius:var(--r-md)">
        <input type="text" name="q" class="search-input" placeholder="<?= __t('ابحث...','Search...') ?>">
        <button type="submit" class="search-submit" style="border-radius:0 var(--r-md) var(--r-md) 0">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        </button>
      </div>
    </form>
  </div>
  <a href="index.php"                class="mob-nav-link <?= $_curPage==='index.php'?'act':'' ?>"><?= __t('الرئيسية','Home') ?><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg></a>
  <a href="shop.php"                 class="mob-nav-link"><?= __t('كل المنتجات','All Products') ?><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg></a>
  <a href="shop.php?filter=featured" class="mob-nav-link"><?= __t('منتجات مميزة','Featured') ?><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg></a>
  <a href="shop.php?filter=sale"     class="mob-nav-link"><?= __t('العروض','Deals') ?><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg></a>
  <div class="mob-cats-ttl"><?= __t('الأقسام','Categories') ?></div>
  <?php foreach ($_cats as $c): ?>
  <a href="shop.php?cat=<?= urlencode($c['slug']) ?>" class="mob-cat" onclick="drawerClose()">
    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
    <?= e(t($c,'name')) ?>
  </a>
  <?php endforeach; ?>
  <div class="mob-footer">
    <?php if ($_user): ?>
      <a href="dashboard.php" class="btn btn-dark btn-block" style="margin-bottom:8px"><?= __t('حسابي','My Account') ?></a>
      <a href="logout.php"    class="btn btn-outline btn-block"><?= __t('تسجيل الخروج','Logout') ?></a>
    <?php else: ?>
      <a href="login.php"    class="btn btn-primary btn-block" style="margin-bottom:8px"><?= __t('تسجيل الدخول','Login') ?></a>
      <a href="register.php" class="btn btn-outline btn-block"><?= __t('إنشاء حساب','Register') ?></a>
    <?php endif; ?>
    <a href="<?= langToggleUrl() ?>" class="btn btn-ghost btn-block" style="margin-top:8px;font-size:.82rem">
      <svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path fill-rule="evenodd" d="M7 2a1 1 0 011 1v1h3a1 1 0 110 2H9.578a18.87 18.87 0 01-1.724 4.78c.29.354.596.696.914 1.026a1 1 0 11-1.44 1.389c-.188-.196-.373-.396-.554-.6a19.098 19.098 0 01-3.107 3.567 1 1 0 01-1.334-1.49 17.087 17.087 0 003.13-3.733 18.992 18.992 0 01-1.487-3.754 1 1 0 111.94-.477c.267 1.02.686 1.98 1.234 2.859A17.354 17.354 0 009.028 9H3a1 1 0 110-2h3V3a1 1 0 011-1zm6 6a1 1 0 01.894.553l2.991 5.992a.869.869 0 01.02.037l.99 1.98a1 1 0 11-1.79.895L15.383 16h-4.764l-.724 1.447a1 1 0 11-1.788-.894l.99-1.98.019-.038 2.99-5.992A1 1 0 0113 8zm-1.382 6h2.764L13 11.236 11.618 14z" clip-rule="evenodd"/></svg>
      <?= $_isAr ? 'English' : 'العربية' ?>
    </a>
  </div>
</div>

<?php $flash=getFlash(); if($flash): ?>
<div class="flash-wrap">
  <div class="container">
    <div class="flash flash-<?= $flash['type'][0] ?>">
      <?php if($flash['type']==='success'): ?>
        <svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18" style="color:#059669"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
      <?php else: ?>
        <svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18" style="color:#dc2626"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
      <?php endif; ?>
      <?= e($flash['message']) ?>
      <button class="flash-close" onclick="this.closest('.flash-wrap').remove()">
        <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
      </button>
    </div>
  </div>
</div>
<?php endif; ?>

<main>
<script>
/* ── Toast ── */
function showToast(msg,type='s',dur=3500){
  const ic={
    s:'<svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
    e:'<svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>',
    i:'<svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>'
  };
  const t=document.createElement('div');
  t.className=`toast t-${type}`;
  t.innerHTML=`<span class="t-icon">${ic[type]||ic.i}</span><span>${msg}</span>`;
  document.getElementById('toastBox').appendChild(t);
  setTimeout(()=>{t.classList.add('out');setTimeout(()=>t.remove(),250);},dur);
}

/* ── Cart ── */
const _CSRF='<?= generateCsrfToken() ?>';
function addToCart(pid,btn){
  const orig=btn?btn.innerHTML:'';
  if(btn){btn.disabled=true;btn.innerHTML='<svg style="animation:spin .7s linear infinite" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>';}
  fetch('cart-action.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`action=add&product_id=${pid}&quantity=1&csrf_token=${_CSRF}`})
  .then(r=>r.json()).then(d=>{
    showToast(d.message,d.success?'s':'e');
    if(d.success){const b=document.getElementById('cartBadge');if(b)b.textContent=d.count||'';}
  }).catch(()=>showToast('<?= __t("حدث خطأ","Error") ?>','e'))
  .finally(()=>{if(btn){btn.disabled=false;btn.innerHTML=orig;}});
}

/* ── Wishlist ── */
function toggleWishlist(pid,btn){
  fetch('wishlist-action.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`product_id=${pid}&csrf_token=${_CSRF}`})
  .then(r=>r.json()).then(d=>{
    if(d.redirect){location.href=d.redirect;return;}
    if(d.success){showToast(d.message,d.added?'s':'i');if(btn)btn.classList.toggle('active',d.added);}
  });
}

/* ── Drawer ── */
function drawerOpen(){
  document.getElementById('mobDrawer').classList.add('open');
  document.getElementById('mobOverlay').classList.add('on');
  document.body.style.overflow='hidden';
}
function drawerClose(){
  document.getElementById('mobDrawer').classList.remove('open');
  document.getElementById('mobOverlay').classList.remove('on');
  document.body.style.overflow='';
}

/* ── Mobile search toggle ── */
function mobSearchToggle(){
  const bar=document.getElementById('mobSearchBar');
  const open=bar.classList.toggle('open');
  if(open) setTimeout(()=>document.getElementById('mobSearchInput').focus(),50);
}

/* ── Sticky header ── */
window.addEventListener('scroll',()=>{
  document.getElementById('mainHdr').classList.toggle('stuck',scrollY>8);
},{passive:true});

/* ── Spin keyframe ── */
document.head.insertAdjacentHTML('beforeend','<style>@keyframes spin{to{transform:rotate(360deg)}}</style>');
</script>