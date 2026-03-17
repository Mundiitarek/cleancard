<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$slug    = trim($_GET['slug'] ?? '');
$id      = (int)($_GET['id']   ?? 0);
$product = $slug ? getProduct($slug) : ($id ? getProduct((string)$id) : null);

if (!$product) {
    http_response_code(404);
    header('Location: shop.php'); exit;
}

incrementProductViews((int)$product['id']);

$related = getProducts(['category_id'=>$product['category_id'],'limit'=>6]);
$related = array_filter($related, fn($p)=>$p['id']!=$product['id']);

$reviews = dbFetchAll("SELECT r.*, u.name as user_name FROM reviews r
    LEFT JOIN users u ON r.user_id=u.id
    WHERE r.product_id=? AND r.is_approved=1
    ORDER BY r.created_at DESC", 'i', $product['id']);

$gallery  = !empty($product['gallery']) ? json_decode($product['gallery'],true) : [];
if (!is_array($gallery)) $gallery = [];

$inWish   = isInWishlist((int)$product['id']);
$price    = productPrice($product);
$isOnSale = $product['sale_price'] && $product['sale_price'] < $product['price'];
$disc     = $isOnSale ? getDiscountPercent($product) : 0;
$outStock = (int)$product['stock'] === 0;
$img      = productImage($product);

// Review submission
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['submit_review'])) {
    if (!verifyCsrfToken($_POST['csrf_token']??'')) {
        setFlash('error', __t('رمز غير صالح','Invalid token'));
    } else {
        $rating  = max(1,min(5,(int)($_POST['rating']??5)));
        $comment = sanitize($_POST['comment']??'');
        $name    = sanitize($_POST['reviewer_name']??'');
        $u       = currentUser();
        dbInsert('reviews',[
            'product_id'    => (int)$product['id'],
            'user_id'       => $u ? $u['id'] : null,
            'reviewer_name' => $u ? $u['name'] : $name,
            'rating'        => $rating,
            'comment'       => $comment,
            'is_approved'   => 0,
        ]);
        setFlash('success', __t('شكراً! سيتم مراجعة تعليقك قريباً','Thank you! Your review will be reviewed soon'));
        header('Location: product.php?slug='.urlencode($product['slug'])); exit;
    }
}

$pageTitle = t($product,'name');
require_once __DIR__ . '/header.php';

// Related product card — same system as index/shop
function pCard($p) {
    $price    = productPrice($p);
    $onSale   = $p['sale_price'] && $p['sale_price'] < $p['price'];
    $disc     = $onSale ? getDiscountPercent($p) : 0;
    $outStock = (int)$p['stock'] === 0;
    $inWish   = isInWishlist((int)$p['id']);
    ob_start(); ?>
    <div class="pc">
        <div class="pc__img-wrap">
            <a href="product.php?slug=<?= urlencode($p['slug']) ?>">
                <img src="<?= e(productImage($p)) ?>" alt="<?= e(t($p,'name')) ?>" loading="lazy"
                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIgZmlsbD0iI2YxZjNmNiIvPjwvc3ZnPg=='">
            </a>
            <div class="pc__badges">
                <?php if($outStock): ?><span class="badge badge-out"><?= __t('نفذ','Out') ?></span>
                <?php elseif($disc): ?><span class="badge badge-sale">-<?= $disc ?>%</span><?php endif; ?>
                <?php if($p['is_new']&&!$outStock): ?><span class="badge badge-new"><?= __t('جديد','New') ?></span><?php endif; ?>
            </div>
            <button class="pc__wishlist <?= $inWish?'active':'' ?>"
                    onclick="toggleWishlist(<?= (int)$p['id'] ?>,this)" aria-label="Wishlist">
                <svg viewBox="0 0 24 24" fill="<?= $inWish?'currentColor':'none' ?>" stroke="currentColor" stroke-width="2">
                    <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
                </svg>
            </button>
        </div>
        <div class="pc__body">
            <div class="pc__cat"><?= e(isAr()?($p['cat_ar']??''):($p['cat_en']??'')) ?></div>
            <a href="product.php?slug=<?= urlencode($p['slug']) ?>" class="pc__name"><?= e(t($p,'name')) ?></a>
            <?php if((int)$p['rating_count']>0): ?>
            <div class="pc__rating">
                <div class="pc__stars"><?= starsHtml((float)$p['rating_avg']) ?></div>
                <span class="pc__rcount">(<?= (int)$p['rating_count'] ?>)</span>
            </div>
            <?php endif; ?>
            <div class="pc__price">
                <span class="pc__price-now"><?= formatPrice($price) ?></span>
                <?php if($onSale): ?>
                    <span class="pc__price-was"><?= formatPrice((float)$p['price']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="pc__footer">
            <?php if($outStock): ?>
                <button class="pc__atc out" disabled>
                    <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                    <?= __t('نفذت الكمية','Out of Stock') ?>
                </button>
            <?php else: ?>
                <button class="pc__atc" onclick="addToCart(<?= (int)$p['id'] ?>,this)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
                    <?= __t('أضف للسلة','Add to Cart') ?>
                </button>
            <?php endif; ?>
        </div>
    </div>
    <?php return ob_get_clean();
}
?>

<style>
/* ══════════════════════════════════════
   PRODUCT PAGE — same tokens as header
══════════════════════════════════════ */
html, body { overflow-x: hidden; }

/* ── Breadcrumb strip ── */
.pdp-crumb-bar {
    background: var(--white);
    border-bottom: 1px solid var(--line);
    padding: 12px 0;
}

/* ── Main grid ── */
.pdp-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 48px;
    align-items: start;
    padding: 36px 0 56px;
}

/* ════ GALLERY ════ */
.pdp-gallery {}
.pdp-main-img {
    position: relative;
    border-radius: var(--r-xl);
    overflow: hidden;
    background: var(--line-2);
    border: 1px solid var(--line);
    aspect-ratio: 1/1;
    margin-bottom: 12px;
    cursor: zoom-in;
}
.pdp-main-img img {
    width: 100%; height: 100%;
    object-fit: cover;
    transition: transform 400ms var(--ease);
}
.pdp-main-img:hover img { transform: scale(1.04); }

/* Sale badge on image */
.pdp-img-badge {
    position: absolute;
    top: 16px; <?= isAr()?'right':'left' ?>: 16px;
    background: var(--red); color: #fff;
    font-size: .78rem; font-weight: 800;
    padding: 5px 14px; border-radius: var(--r-full);
    z-index: 2;
}

/* Zoom button */
.pdp-zoom-btn {
    position: absolute;
    bottom: 12px; <?= isAr()?'left':'right' ?>: 12px;
    width: 36px; height: 36px; border-radius: var(--r-md);
    background: rgba(0,0,0,.45); color: #fff;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; z-index: 2; border: none;
    transition: background var(--t-base);
}
.pdp-zoom-btn:hover { background: var(--red); }
.pdp-zoom-btn svg { width: 16px; height: 16px; }

/* Thumbnails */
.pdp-thumbs {
    display: flex; gap: 8px; flex-wrap: wrap;
}
.pdp-thumb {
    width: 66px; height: 66px;
    object-fit: cover; border-radius: var(--r-md);
    border: 2px solid var(--line);
    cursor: pointer; transition: border-color var(--t-base);
}
.pdp-thumb:hover, .pdp-thumb.act { border-color: var(--red); }

/* ════ PRODUCT INFO ════ */
.pdp-info {}

/* Category pill */
.pdp-cat {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: .72rem; font-weight: 800;
    color: var(--red); background: var(--red-10);
    padding: 4px 12px; border-radius: var(--r-full);
    text-transform: uppercase; letter-spacing: .06em;
    text-decoration: none; margin-bottom: 14px;
    transition: background var(--t-base);
}
.pdp-cat:hover { background: var(--red-20); }

/* Title */
.pdp-title {
    font-size: clamp(1.3rem,2.5vw,1.9rem);
    font-weight: 900; color: var(--ink);
    line-height: 1.25; margin-bottom: 14px;
}

/* Rating row */
.pdp-rating {
    display: flex; align-items: center; gap: 8px;
    flex-wrap: wrap; margin-bottom: 18px;
}
.pdp-rating__val   { font-weight: 700; color: var(--red); font-size: .9rem; }
.pdp-rating__count { color: var(--muted); font-size: .82rem; }
.pdp-rating__link  { color: var(--red); font-size: .82rem; text-decoration: underline; }

/* Price box */
.pdp-price-box {
    display: flex; align-items: center; gap: 14px;
    flex-wrap: wrap; padding: 18px 20px;
    background: var(--red-5); border: 1.5px dashed var(--red-20);
    border-radius: var(--r-lg); margin-bottom: 20px;
}
.pdp-price-now { font-size: 2rem; font-weight: 900; color: var(--red); line-height: 1; }
.pdp-price-was { font-size: 1rem; color: var(--subtle); text-decoration: line-through; }
.pdp-price-save {
    background: #d1fae5; color: #065f46;
    font-size: .75rem; font-weight: 700;
    padding: 3px 10px; border-radius: var(--r-full);
}

/* Short desc */
.pdp-short-desc {
    color: var(--muted); font-size: .875rem;
    line-height: 1.8; margin-bottom: 20px;
}

/* Stock status */
.pdp-stock {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 7px 16px; border-radius: var(--r-full);
    font-size: .84rem; font-weight: 700; margin-bottom: 22px;
}
.pdp-stock svg { width: 14px; height: 14px; flex-shrink: 0; }
.pdp-stock.in  { background: #d1fae5; color: #065f46; }
.pdp-stock.out { background: #fee2e2; color: #991b1b; }

/* Qty + Cart row */
.pdp-cart-row {
    display: flex; align-items: center; gap: 12px;
    margin-bottom: 16px; flex-wrap: wrap;
}
.pdp-qty {
    display: flex; align-items: center;
    border: 1.5px solid var(--line); border-radius: var(--r-full);
    overflow: hidden; flex-shrink: 0;
}
.pdp-qty-btn {
    width: 40px; height: 44px;
    background: var(--line-2); color: var(--body);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; border: none; font-size: 1.1rem;
    transition: all var(--t-base); font-family: var(--font);
}
.pdp-qty-btn:hover { background: var(--red); color: #fff; }
.pdp-qty-btn svg   { width: 14px; height: 14px; }
.pdp-qty-inp {
    width: 50px; border: none; text-align: center;
    font-size: .95rem; font-weight: 700; color: var(--ink-3);
    height: 44px; outline: none; font-family: var(--font);
    background: var(--white);
}
.pdp-qty-inp::-webkit-inner-spin-button { display: none; }

.pdp-cart-btn { flex: 1; min-width: 160px; }
.pdp-buy-btn  { white-space: nowrap; flex-shrink: 0; }

/* Wishlist / share */
.pdp-actions { display: flex; gap: 8px; margin-bottom: 22px; flex-wrap: wrap; }
.pdp-action-btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 9px 18px; border-radius: var(--r-full);
    border: 1.5px solid var(--line); color: var(--body);
    font-size: .82rem; font-weight: 700; cursor: pointer;
    transition: all var(--t-base); background: var(--white);
    font-family: var(--font); text-decoration: none;
}
.pdp-action-btn svg { width: 15px; height: 15px; }
.pdp-action-btn:hover { border-color: var(--red); color: var(--red); background: var(--red-5); }
.pdp-action-btn.wished { background: var(--red-10); color: var(--red); border-color: var(--red); }

/* Meta */
.pdp-meta { border-top: 1px solid var(--line-2); padding-top: 18px; margin-bottom: 22px; }
.pdp-meta-row {
    display: flex; align-items: flex-start; gap: 10px;
    font-size: .84rem; margin-bottom: 8px;
}
.pdp-meta-label { color: var(--subtle); min-width: 80px; font-weight: 600; flex-shrink: 0; }
.pdp-meta-link  { color: var(--red); text-decoration: none; }
.pdp-meta-link:hover { text-decoration: underline; }
.pdp-tags { display: flex; gap: 6px; flex-wrap: wrap; }
.pdp-tag {
    background: var(--line-2); border: 1px solid var(--line);
    padding: 3px 10px; border-radius: var(--r-full);
    font-size: .75rem; color: var(--body); text-decoration: none;
    transition: all var(--t-base);
}
.pdp-tag:hover { background: var(--red-10); color: var(--red); border-color: var(--red); }

/* Guarantees */
.pdp-guarantees {
    display: grid; grid-template-columns: repeat(2,1fr); gap: 8px;
}
.pdp-guarantee {
    display: flex; align-items: center; gap: 9px;
    background: var(--line-2); padding: 10px 14px;
    border-radius: var(--r-md); font-size: .8rem; font-weight: 600;
    color: var(--ink-3);
}
.pdp-guarantee svg { width: 16px; height: 16px; color: var(--red); flex-shrink: 0; }

/* ════ TABS ════ */
.pdp-tabs { margin-top: 52px; }
.pdp-tabs-nav {
    display: flex; border-bottom: 2px solid var(--line);
    overflow-x: auto; scrollbar-width: none; gap: 0;
}
.pdp-tabs-nav::-webkit-scrollbar { display: none; }
.pdp-tab-btn {
    display: flex; align-items: center; gap: 7px;
    padding: 13px 22px; font-size: .86rem; font-weight: 700;
    color: var(--muted); border-bottom: 2px solid transparent;
    margin-bottom: -2px; white-space: nowrap;
    transition: all var(--t-base); font-family: var(--font);
    cursor: pointer; background: none; border-top: none;
    border-<?= isAr()?'right':'left' ?>: none; border-<?= isAr()?'left':'right' ?>: none;
}
.pdp-tab-btn svg { width: 15px; height: 15px; }
.pdp-tab-btn:hover { color: var(--red); }
.pdp-tab-btn.act  { color: var(--red); border-bottom-color: var(--red); }
.tab-pill {
    background: var(--red); color: #fff;
    font-size: .62rem; font-weight: 800;
    padding: 2px 7px; border-radius: var(--r-full);
}
.pdp-tab-pane { display: none; }
.pdp-tab-pane.act { display: block; }
.pdp-tab-body {
    background: var(--white);
    border: 1px solid var(--line); border-top: none;
    border-radius: 0 0 var(--r-lg) var(--r-lg);
    padding: 28px;
}

/* Description prose */
.pdp-prose { line-height: 1.9; color: var(--body); font-size: .9rem; }

/* Specs table */
.specs-tbl { width: 100%; border-collapse: collapse; }
.specs-tbl th,
.specs-tbl td {
    padding: 11px 16px;
    text-align: <?= isAr()?'right':'left' ?>;
    border-bottom: 1px solid var(--line-2);
    font-size: .86rem;
}
.specs-tbl th {
    background: var(--line-2); font-weight: 700;
    width: 34%; color: var(--ink-3);
}
.specs-tbl tr:last-child th,
.specs-tbl tr:last-child td { border-bottom: none; }

/* ════ REVIEWS ════ */
.reviews-summary {
    display: flex; align-items: center; gap: 40px;
    padding: 24px; background: var(--line-2);
    border-radius: var(--r-lg); margin-bottom: 28px;
    flex-wrap: wrap;
}
.rs-score { text-align: center; flex-shrink: 0; }
.rs-big   { font-size: 3.5rem; font-weight: 900; color: var(--red); line-height: 1; }
.rs-lbl   { font-size: .76rem; color: var(--muted); margin-top: 4px; }
.rs-bars  { flex: 1; display: flex; flex-direction: column; gap: 7px; min-width: 200px; }
.rs-bar-row { display: flex; align-items: center; gap: 10px; font-size: .8rem; color: var(--muted); }
.rs-bar-row span:first-child { width: 22px; text-align: <?= isAr()?'right':'left' ?>; flex-shrink: 0; color: var(--ink-3); font-weight: 600; }
.rs-track { flex: 1; height: 7px; background: var(--line); border-radius: var(--r-full); overflow: hidden; }
.rs-fill  { height: 100%; background: var(--red); border-radius: var(--r-full); transition: width .6s var(--ease); }
.rs-bar-row span:last-child { width: 20px; text-align: center; flex-shrink: 0; }

.reviews-list { display: flex; flex-direction: column; gap: 16px; margin-bottom: 32px; }
.review-card {
    padding: 20px; border: 1px solid var(--line);
    border-radius: var(--r-lg); background: var(--white);
    transition: box-shadow var(--t-base);
}
.review-card:hover { box-shadow: var(--sh-sm); }
.review-card__head {
    display: flex; align-items: center;
    gap: 12px; margin-bottom: 10px;
}
.review-av {
    width: 38px; height: 38px; border-radius: 50%;
    background: var(--red); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: .9rem; flex-shrink: 0;
}
.review-av--muted { background: var(--subtle); }
.review-name  { font-size: .86rem; font-weight: 700; color: var(--ink-3); }
.review-date  { font-size: .74rem; color: var(--muted); }
.review-stars { margin-<?= isAr()?'right':'left' ?>: auto; display: flex; gap: 2px; }
.review-stars svg { width: 13px; height: 13px; }
.review-body  { font-size: .86rem; color: var(--muted); line-height: 1.7; }

/* Empty reviews */
.reviews-empty {
    text-align: center; padding: 40px 24px;
    color: var(--subtle);
}
.reviews-empty__icon {
    width: 56px; height: 56px; background: var(--line-2);
    border-radius: var(--r-lg); display: flex; align-items: center;
    justify-content: center; margin: 0 auto 16px; color: var(--subtle);
}
.reviews-empty__icon svg { width: 24px; height: 24px; }

/* Add review */
.add-review {
    background: var(--line-2); padding: 24px;
    border-radius: var(--r-lg); border: 1px solid var(--line);
    margin-top: 28px;
}
.add-review h4 {
    font-size: .95rem; font-weight: 800;
    color: var(--ink-3); margin-bottom: 20px;
    display: flex; align-items: center; gap: 8px;
}
.add-review h4 svg { color: var(--red); width: 16px; height: 16px; }

/* Star picker */
.star-picker { display: flex; gap: 6px; }
.sp-star {
    width: 32px; height: 32px; cursor: pointer;
    color: var(--line); transition: color var(--t-fast);
}
.sp-star.act { color: #f59e0b; }
.sp-star svg { width: 28px; height: 28px; }

/* ════ SHIPPING TAB ════ */
.ship-grid {
    display: grid; grid-template-columns: repeat(3,1fr); gap: 18px;
}
.ship-card {
    padding: 22px; border-radius: var(--r-lg);
    border: 1px solid var(--line); background: var(--white);
}
.ship-card__icon {
    width: 44px; height: 44px; border-radius: var(--r-md);
    background: var(--red-10); color: var(--red);
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 14px;
}
.ship-card__icon svg { width: 20px; height: 20px; }
.ship-card h4 {
    font-size: .88rem; font-weight: 800; color: var(--ink-3);
    margin-bottom: 12px;
}
.ship-card ul  { display: flex; flex-direction: column; gap: 8px; }
.ship-card li  {
    font-size: .82rem; color: var(--muted);
    padding-<?= isAr()?'right':'left' ?>: 16px;
    position: relative; line-height: 1.5;
}
.ship-card li::before {
    content: '';
    position: absolute; <?= isAr()?'right':'left' ?>: 0; top: 7px;
    width: 6px; height: 6px; border-radius: 50%;
    background: var(--red);
}

/* ════ ZOOM MODAL ════ */
.zoom-modal {
    display: none; position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,.92);
    align-items: center; justify-content: center;
    cursor: zoom-out;
}
.zoom-modal.open { display: flex; }
.zoom-modal img  { max-width: 90vw; max-height: 90vh; object-fit: contain; border-radius: var(--r-lg); }
.zoom-modal__close {
    position: absolute; top: 20px; <?= isAr()?'left':'right' ?>: 20px;
    width: 40px; height: 40px; border-radius: var(--r-md);
    background: rgba(255,255,255,.15); color: #fff;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; border: 1px solid rgba(255,255,255,.2);
    transition: background var(--t-base);
}
.zoom-modal__close:hover { background: var(--red); }
.zoom-modal__close svg   { width: 16px; height: 16px; }

/* ════ RELATED ════ */
.related-grid {
    display: grid;
    grid-template-columns: repeat(5,1fr);
    gap: 18px;
}

/* ══════════════════════
   RESPONSIVE
══════════════════════ */
@media(max-width:1000px) {
    .related-grid { grid-template-columns: repeat(3,1fr); }
}
@media(max-width:900px) {
    .pdp-grid  { grid-template-columns: 1fr; gap: 28px; padding: 24px 0 40px; }
    .ship-grid { grid-template-columns: 1fr; }
    .pdp-cart-row { flex-wrap: wrap; }
    .pdp-cart-btn { width: 100%; }
    .pdp-buy-btn  { width: 100%; }
}
@media(max-width:640px) {
    .pdp-guarantees { grid-template-columns: 1fr; }
    .reviews-summary { flex-direction: column; gap: 20px; }
    .related-grid { grid-template-columns: repeat(2,1fr); }
    .pdp-price-now { font-size: 1.6rem; }
}
</style>

<!-- ── Breadcrumb ── -->
<div class="pdp-crumb-bar">
  <div class="container">
    <nav class="breadcrumb">
      <a href="index.php"><?= __t('الرئيسية','Home') ?></a>
      <span class="sep"><svg viewBox="0 0 20 20" fill="currentColor" width="10" height="10"><path fill-rule="evenodd" d="M<?= isAr()?'12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z':'7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z'?>" clip-rule="evenodd"/></svg></span>
      <a href="shop.php"><?= __t('المتجر','Shop') ?></a>
      <span class="sep"><svg viewBox="0 0 20 20" fill="currentColor" width="10" height="10"><path fill-rule="evenodd" d="M<?= isAr()?'12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z':'7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z'?>" clip-rule="evenodd"/></svg></span>
      <a href="shop.php?cat=<?= urlencode($product['cat_slug']??'') ?>">
        <?= e(isAr()?($product['cat_ar']??''):($product['cat_en']??'')) ?>
      </a>
      <span class="sep"><svg viewBox="0 0 20 20" fill="currentColor" width="10" height="10"><path fill-rule="evenodd" d="M<?= isAr()?'12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z':'7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z'?>" clip-rule="evenodd"/></svg></span>
      <span class="cur"><?= e(t($product,'name')) ?></span>
    </nav>
  </div>
</div>

<!-- ── Product Main ── -->
<div class="container">
<div class="pdp-grid">

  <!-- Gallery -->
  <div class="pdp-gallery">
    <div class="pdp-main-img" id="mainImgWrap">
      <img src="<?= e($img) ?>" alt="<?= e(t($product,'name')) ?>" id="mainImg"
           onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIgZmlsbD0iI2YxZjNmNiIvPjwvc3ZnPg=='">
      <?php if($isOnSale): ?>
        <div class="pdp-img-badge">-<?= $disc ?>% <?= __t('خصم','OFF') ?></div>
      <?php endif; ?>
      <button class="pdp-zoom-btn" id="zoomBtn" title="<?= __t('تكبير','Zoom') ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
      </button>
    </div>
    <?php if ($gallery): ?>
    <div class="pdp-thumbs">
      <img src="<?= e($img) ?>" class="pdp-thumb act" onclick="switchImg(this,'<?= e($img) ?>')">
      <?php foreach ($gallery as $g):
        $gUrl = UPLOADS_URL.'products/'.e($g); ?>
        <img src="<?= $gUrl ?>" class="pdp-thumb" onclick="switchImg(this,'<?= $gUrl ?>')">
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Info -->
  <div class="pdp-info">

    <a href="shop.php?cat=<?= urlencode($product['cat_slug']??'') ?>" class="pdp-cat">
      <svg viewBox="0 0 20 20" fill="currentColor" width="11" height="11"><path fill-rule="evenodd" d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7A.997.997 0 012 10V5a3 3 0 013-3h5c.256 0 .512.098.707.293l7 7zM5 6a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
      <?= e(isAr()?($product['cat_ar']??''):($product['cat_en']??'')) ?>
    </a>

    <h1 class="pdp-title"><?= e(t($product,'name')) ?></h1>

    <?php if ($product['rating_count'] > 0): ?>
    <div class="pdp-rating">
      <div class="pc__stars"><?= starsHtml((float)$product['rating_avg']) ?></div>
      <span class="pdp-rating__val"><?= number_format((float)$product['rating_avg'],1) ?></span>
      <span class="pdp-rating__count">(<?= (int)$product['rating_count'] ?> <?= __t('تقييم','reviews') ?>)</span>
      <a href="#tab-reviews" class="pdp-rating__link" onclick="openTab('reviews')"><?= __t('اقرأ التعليقات','Read reviews') ?></a>
    </div>
    <?php endif; ?>

    <!-- Price -->
    <div class="pdp-price-box">
      <?php if ($isOnSale): ?>
        <span class="pdp-price-was"><?= formatPrice((float)$product['price']) ?></span>
        <span class="pdp-price-now"><?= formatPrice($price) ?></span>
        <span class="pdp-price-save">
          <?= __t('وفّر','Save') ?> <?= formatPrice((float)$product['price']-$price) ?>
        </span>
      <?php else: ?>
        <span class="pdp-price-now"><?= formatPrice($price) ?></span>
      <?php endif; ?>
    </div>

    <!-- Short desc -->
    <?php $desc = t($product,'desc'); if ($desc): ?>
    <div class="pdp-short-desc">
      <?= nl2br(e(mb_substr(strip_tags($desc),0,200))) ?>…
    </div>
    <?php endif; ?>

    <!-- Stock -->
    <div class="pdp-stock <?= $outStock?'out':'in' ?>">
      <?php if ($outStock): ?>
        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
        <?= __t('غير متوفر حالياً','Currently Out of Stock') ?>
      <?php else: ?>
        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
        <?= __t('متوفر في المخزون','In Stock') ?>
        <?php if ($product['stock'] <= 10): ?>
          — <?= __t('باقي','Only') ?> <strong><?= (int)$product['stock'] ?></strong> <?= __t('قطعة فقط!','left!') ?>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <!-- Qty + Cart -->
    <?php if (!$outStock): ?>
    <div class="pdp-cart-row">
      <div class="pdp-qty">
        <button class="pdp-qty-btn" onclick="changeQty(-1)">
          <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>
        </button>
        <input type="number" id="qtyInput" value="1" min="1" max="<?= (int)$product['stock'] ?>" class="pdp-qty-inp">
        <button class="pdp-qty-btn" onclick="changeQty(1)">
          <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
        </button>
      </div>
      <button class="btn btn-primary btn-lg pdp-cart-btn" id="addCartBtn"
              onclick="addToCartQty(<?= (int)$product['id'] ?>)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
          <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/>
        </svg>
        <?= __t('أضف إلى السلة','Add to Cart') ?>
      </button>
      <button class="btn btn-dark pdp-buy-btn" onclick="buyNow(<?= (int)$product['id'] ?>)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15">
          <path d="M13 10V3L4 14h7v7l9-11h-7z"/>
        </svg>
        <?= __t('اشتر الآن','Buy Now') ?>
      </button>
    </div>
    <?php else: ?>
    <div style="margin:20px 0">
      <button class="btn btn-outline btn-lg btn-block" disabled style="opacity:.6;cursor:not-allowed;">
        <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
        <?= __t('نفذت الكمية','Out of Stock') ?>
      </button>
    </div>
    <?php endif; ?>

    <!-- Wishlist / Share -->
    <div class="pdp-actions">
      <button class="pdp-action-btn <?= $inWish?'wished':'' ?>" id="wishBtn"
              onclick="toggleWishlistPage(<?= (int)$product['id'] ?>)">
        <svg viewBox="0 0 24 24" fill="<?= $inWish?'currentColor':'none' ?>" stroke="currentColor" stroke-width="2" id="wishIcon">
          <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
        </svg>
        <span id="wishTxt"><?= $inWish ? __t('في المفضلة','In Wishlist') : __t('أضف للمفضلة','Add to Wishlist') ?></span>
      </button>
      <button class="pdp-action-btn" onclick="shareProduct()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>
          <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
        </svg>
        <?= __t('مشاركة','Share') ?>
      </button>
    </div>

    <!-- Meta -->
    <div class="pdp-meta">
      <?php if (!empty($product['sku'])): ?>
      <div class="pdp-meta-row">
        <span class="pdp-meta-label"><?= __t('الكود:','SKU:') ?></span>
        <span style="color:var(--ink-3); font-weight:600;"><?= e($product['sku']) ?></span>
      </div>
      <?php endif; ?>
      <div class="pdp-meta-row">
        <span class="pdp-meta-label"><?= __t('القسم:','Category:') ?></span>
        <a href="shop.php?cat=<?= urlencode($product['cat_slug']??'') ?>" class="pdp-meta-link">
          <?= e(isAr()?($product['cat_ar']??''):($product['cat_en']??'')) ?>
        </a>
      </div>
      <?php if (!empty($product['tags'])): ?>
      <div class="pdp-meta-row">
        <span class="pdp-meta-label"><?= __t('الوسوم:','Tags:') ?></span>
        <div class="pdp-tags">
          <?php foreach (explode(',',$product['tags']) as $tag): ?>
            <a href="shop.php?q=<?= urlencode(trim($tag)) ?>" class="pdp-tag"><?= e(trim($tag)) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Guarantees -->
    <div class="pdp-guarantees">
      <div class="pdp-guarantee">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM3 4h1.05A2.5 2.5 0 016.9 6H19l-2 7H7M3 4a1 1 0 00-1 1v1"/></svg>
        <?= __t('شحن سريع','Fast Delivery') ?>
      </div>
      <div class="pdp-guarantee">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
        <?= __t('إرجاع 14 يوم','14-day Return') ?>
      </div>
      <div class="pdp-guarantee">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
        <?= __t('دفع آمن','Secure Payment') ?>
      </div>
      <div class="pdp-guarantee">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
        <?= __t('منتج أصلي','Authentic Product') ?>
      </div>
    </div>

  </div><!-- /.pdp-info -->
</div><!-- /.pdp-grid -->

<!-- ════ TABS ════ -->
<div class="pdp-tabs" id="pdp-tabs">
  <div class="pdp-tabs-nav">

    <button class="pdp-tab-btn act" data-tab="description">
      <svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/></svg>
      <?= __t('التفاصيل','Description') ?>
    </button>

    <button class="pdp-tab-btn" data-tab="specs">
      <svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>
      <?= __t('المواصفات','Specifications') ?>
    </button>

    <button class="pdp-tab-btn" data-tab="reviews" id="reviewsTabBtn">
      <svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"/></svg>
      <?= __t('التقييمات','Reviews') ?>
      <?php if ($reviews): ?><span class="tab-pill"><?= count($reviews) ?></span><?php endif; ?>
    </button>

    <button class="pdp-tab-btn" data-tab="shipping">
      <svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H11a1 1 0 001-1V5a1 1 0 00-1-1H3zM14 7a1 1 0 00-1 1v6.05A2.5 2.5 0 0115.95 16H17a1 1 0 001-1v-5a1 1 0 00-.293-.707L16 7.586A1 1 0 0015.293 7H14z"/></svg>
      <?= __t('الشحن والإرجاع','Shipping & Returns') ?>
    </button>

  </div>

  <!-- Description -->
  <div class="pdp-tab-pane act" id="tab-description">
    <div class="pdp-tab-body">
      <?php $desc = t($product,'desc'); if ($desc): ?>
        <div class="pdp-prose"><?= nl2br(e($desc)) ?></div>
      <?php else: ?>
        <div style="text-align:center;padding:32px;color:var(--subtle);">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="40" height="40" style="margin:0 auto 12px;opacity:.4;display:block;"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          <?= __t('لا يوجد وصف متاح لهذا المنتج','No description available for this product') ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Specs -->
  <div class="pdp-tab-pane" id="tab-specs">
    <div class="pdp-tab-body">
      <table class="specs-tbl">
        <tbody>
          <?php if (!empty($product['sku'])): ?>
            <tr><th><?= __t('كود المنتج','Product SKU') ?></th><td><?= e($product['sku']) ?></td></tr>
          <?php endif; ?>
          <tr><th><?= __t('القسم','Category') ?></th><td><?= e(isAr()?($product['cat_ar']??''):($product['cat_en']??'')) ?></td></tr>
          <?php if (!empty($product['weight'])): ?>
            <tr><th><?= __t('الوزن','Weight') ?></th><td><?= e($product['weight']) ?> kg</td></tr>
          <?php endif; ?>
          <tr>
            <th><?= __t('الحالة','Availability') ?></th>
            <td style="color:<?= $outStock?'#991b1b':'#065f46' ?>;font-weight:700;">
              <?= $outStock ? __t('غير متوفر','Out of Stock') : __t('متوفر','In Stock') ?>
            </td>
          </tr>
          <tr><th><?= __t('المخزون','Stock') ?></th><td><?= (int)$product['stock'] ?> <?= __t('قطعة','units') ?></td></tr>
          <tr><th><?= __t('التقييم','Rating') ?></th>
              <td><?= number_format((float)$product['rating_avg'],1) ?>/5 (<?= (int)$product['rating_count'] ?> <?= __t('تقييم','reviews') ?>)</td></tr>
          <tr><th><?= __t('تاريخ الإضافة','Added On') ?></th><td><?= date('Y-m-d',strtotime($product['created_at'])) ?></td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Reviews -->
  <div class="pdp-tab-pane" id="tab-reviews">
    <div class="pdp-tab-body">

      <?php if ($reviews): ?>
      <div class="reviews-summary">
        <div class="rs-score">
          <div class="rs-big"><?= number_format((float)$product['rating_avg'],1) ?></div>
          <div class="pc__stars" style="justify-content:center;"><?= starsHtml((float)$product['rating_avg']) ?></div>
          <div class="rs-lbl"><?= count($reviews) ?> <?= __t('تقييم','reviews') ?></div>
        </div>
        <div class="rs-bars">
          <?php for($s=5;$s>=1;$s--):
            $cnt = count(array_filter($reviews,fn($r)=>(int)$r['rating']===$s));
            $pct = count($reviews) ? round($cnt/count($reviews)*100) : 0;
          ?>
          <div class="rs-bar-row">
            <span><?= $s ?></span>
            <div class="rs-track"><div class="rs-fill" style="width:<?= $pct ?>%"></div></div>
            <span><?= $cnt ?></span>
          </div>
          <?php endfor; ?>
        </div>
      </div>
      <div class="reviews-list">
        <?php foreach ($reviews as $r):
          $rName = $r['reviewer_name'] ?: ($r['user_name'] ?? __t('مجهول','Anonymous'));
        ?>
        <div class="review-card">
          <div class="review-card__head">
            <div class="review-av"><?= mb_substr($rName,0,1) ?></div>
            <div>
              <div class="review-name"><?= e($rName) ?></div>
              <div class="review-date"><?= timeAgo($r['created_at']) ?></div>
            </div>
            <div class="review-stars">
              <?php for($i=1;$i<=5;$i++): ?>
                <svg viewBox="0 0 20 20" fill="<?= $i<=(int)$r['rating']?'#f59e0b':'#e5e7eb' ?>">
                  <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                </svg>
              <?php endfor; ?>
            </div>
          </div>
          <?php if (!empty($r['comment'])): ?>
            <p class="review-body"><?= e($r['comment']) ?></p>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>

      <?php else: ?>
      <div class="reviews-empty">
        <div class="reviews-empty__icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
        </div>
        <p style="font-size:.9rem; margin-bottom:4px;"><?= __t('لا يوجد تقييمات بعد','No reviews yet') ?></p>
        <p style="font-size:.8rem;"><?= __t('كن أول من يقيّم هذا المنتج!','Be the first to review this product!') ?></p>
      </div>
      <?php endif; ?>

      <!-- Add Review -->
      <div class="add-review">
        <h4>
          <svg viewBox="0 0 20 20" fill="currentColor"><path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z"/><path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd"/></svg>
          <?= __t('أضف تقييمك','Add Your Review') ?>
        </h4>
        <form method="POST">
          <?= csrfInput() ?>
          <input type="hidden" name="submit_review" value="1">
          <?php if (!currentUser()): ?>
          <div class="form-group">
            <label class="form-label req"><?= __t('اسمك','Your Name') ?></label>
            <input type="text" name="reviewer_name" class="form-control" required>
          </div>
          <?php endif; ?>
          <div class="form-group">
            <label class="form-label req"><?= __t('التقييم','Rating') ?></label>
            <div class="star-picker" id="starPicker">
              <?php for($i=1;$i<=5;$i++): ?>
              <span class="sp-star <?= $i<=5?'act':'' ?>" data-val="<?= $i ?>">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
              </span>
              <?php endfor; ?>
              <input type="hidden" name="rating" id="ratingInput" value="5">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label"><?= __t('تعليقك','Comment') ?></label>
            <textarea name="comment" class="form-control" rows="4"
                      placeholder="<?= __t('شاركنا رأيك...','Share your opinion...') ?>"></textarea>
          </div>
          <button type="submit" class="btn btn-primary">
            <svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"/></svg>
            <?= __t('إرسال التقييم','Submit Review') ?>
          </button>
        </form>
      </div>

    </div>
  </div>

  <!-- Shipping -->
  <div class="pdp-tab-pane" id="tab-shipping">
    <div class="pdp-tab-body">
      <div class="ship-grid">

        <div class="ship-card">
          <div class="ship-card__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H11a1 1 0 001-1V5a1 1 0 00-1-1H3zM14 7a1 1 0 00-1 1v6.05A2.5 2.5 0 0115.95 16H17a1 1 0 001-1v-5a1 1 0 00-.293-.707L16 7.586A1 1 0 0015.293 7H14z"/></svg>
          </div>
          <h4><?= __t('الشحن','Shipping') ?></h4>
          <ul>
            <li><?= __t('شحن مجاني على الطلبات فوق','Free shipping on orders above') ?> <?= formatPrice(freeShippingMin()) ?></li>
            <li><?= __t('رسوم الشحن','Shipping fee') ?>: <?= formatPrice(shippingFee()) ?></li>
            <li><?= __t('التوصيل خلال 2-5 أيام عمل','Delivery within 2-5 business days') ?></li>
            <li><?= __t('الدفع عند الاستلام متاح','Cash on delivery available') ?></li>
          </ul>
        </div>

        <div class="ship-card">
          <div class="ship-card__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
          </div>
          <h4><?= __t('الإرجاع','Returns') ?></h4>
          <ul>
            <li><?= __t('إرجاع مجاني خلال 14 يوم','Free returns within 14 days') ?></li>
            <li><?= __t('المنتج يجب أن يكون في حالته الأصلية','Product must be in original condition') ?></li>
            <li><?= __t('استرداد كامل للمبلغ','Full refund guaranteed') ?></li>
            <li><?= __t('تواصل معنا لبدء طلب الإرجاع','Contact us to initiate return') ?></li>
          </ul>
        </div>

        <div class="ship-card">
          <div class="ship-card__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
          </div>
          <h4><?= __t('الأمان والجودة','Safety & Quality') ?></h4>
          <ul>
            <li><?= __t('منتجات أصلية 100%','100% authentic products') ?></li>
            <li><?= __t('فحص جودة قبل الشحن','Quality check before shipping') ?></li>
            <li><?= __t('ضمان الرضا التام','Full satisfaction guarantee') ?></li>
            <li><?= __t('دعم عملاء متخصص','Dedicated customer support') ?></li>
          </ul>
        </div>

      </div>
    </div>
  </div>

</div><!-- /.pdp-tabs -->
</div><!-- /.container -->

<!-- ════ RELATED PRODUCTS ════ -->
<?php if ($related): ?>
<section style="background:var(--line-2); padding:56px 0; border-top:1px solid var(--line);">
  <div class="container">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:28px; flex-wrap:wrap; gap:12px;">
      <div>
        <div class="t-tag" style="margin-bottom:8px;">
          <svg viewBox="0 0 20 20" fill="currentColor" width="11" height="11"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
          <?= __t('قد يعجبك أيضاً','You May Also Like') ?>
        </div>
        <h2 class="t-h2"><?= __t('منتجات <span style="color:var(--red)">مشابهة</span>','<span style="color:var(--red)">Related</span> Products') ?></h2>
      </div>
      <a href="shop.php?cat=<?= urlencode($product['cat_slug']??'') ?>" class="btn btn-outline btn-sm">
        <?= __t('عرض الكل','View All') ?>
        <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M<?= isAr()?'12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z':'7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z'?>" clip-rule="evenodd"/></svg>
      </a>
    </div>
    <div class="related-grid">
      <?php foreach (array_slice(array_values($related),0,5) as $p) echo pCard($p); ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- Zoom Modal -->
<div class="zoom-modal" id="zoomModal" onclick="this.classList.remove('open')">
  <button class="zoom-modal__close" onclick="event.stopPropagation();document.getElementById('zoomModal').classList.remove('open')">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
  </button>
  <img id="zoomImg" src="" alt="">
</div>

<script>
// ── Tabs ──────────────────────────────────────────────────
function openTab(name){
  document.querySelectorAll('.pdp-tab-btn').forEach(b=>b.classList.toggle('act',b.dataset.tab===name));
  document.querySelectorAll('.pdp-tab-pane').forEach(p=>p.classList.toggle('act',p.id==='tab-'+name));
}
document.querySelectorAll('.pdp-tab-btn').forEach(btn=>{
  btn.addEventListener('click',()=>openTab(btn.dataset.tab));
});

// ── Gallery ───────────────────────────────────────────────
function switchImg(el,src){
  document.getElementById('mainImg').src=src;
  document.querySelectorAll('.pdp-thumb').forEach(t=>t.classList.remove('act'));
  el.classList.add('act');
}
['mainImg','zoomBtn'].forEach(id=>{
  document.getElementById(id)?.addEventListener('click',()=>{
    document.getElementById('zoomImg').src=document.getElementById('mainImg').src;
    document.getElementById('zoomModal').classList.add('open');
  });
});

// ── Qty ───────────────────────────────────────────────────
function changeQty(d){
  const inp=document.getElementById('qtyInput');
  inp.value=Math.max(1,Math.min(+(inp.max)||999,+inp.value+d));
}

// ── Add to Cart ───────────────────────────────────────────
const _CSRF_PDP='<?= generateCsrfToken() ?>';
function addToCartQty(pid){
  const qty=+(document.getElementById('qtyInput')?.value)||1;
  const btn=document.getElementById('addCartBtn');
  const orig=btn?.innerHTML;
  if(btn){btn.disabled=true;btn.innerHTML='<svg style="animation:spin .7s linear infinite" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>';}
  fetch('cart-action.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`action=add&product_id=${pid}&quantity=${qty}&csrf_token=${_CSRF_PDP}`})
  .then(r=>r.json()).then(d=>{
    showToast(d.message,d.success?'s':'e');
    if(d.success){const b=document.getElementById('cartBadge');if(b)b.textContent=d.count||'';}
  }).catch(()=>showToast('<?= __t("حدث خطأ","Error") ?>','e'))
  .finally(()=>{if(btn){btn.disabled=false;btn.innerHTML=orig;}});
}

// ── Buy Now ───────────────────────────────────────────────
function buyNow(pid){
  const qty=+(document.getElementById('qtyInput')?.value)||1;
  fetch('cart-action.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`action=add&product_id=${pid}&quantity=${qty}&csrf_token=${_CSRF_PDP}`})
  .then(r=>r.json()).then(d=>{if(d.success)location.href='cart.php';});
}

// ── Wishlist ──────────────────────────────────────────────
function toggleWishlistPage(pid){
  fetch('wishlist-action.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`product_id=${pid}&csrf_token=${_CSRF_PDP}`})
  .then(r=>r.json()).then(d=>{
    if(d.redirect){location.href=d.redirect;return;}
    if(d.success){
      showToast(d.message,d.added?'s':'i');
      const btn=document.getElementById('wishBtn');
      const ico=document.getElementById('wishIcon');
      const txt=document.getElementById('wishTxt');
      if(btn) btn.classList.toggle('wished',d.added);
      if(ico) ico.setAttribute('fill',d.added?'currentColor':'none');
      if(txt) txt.textContent=d.added?'<?= __t("في المفضلة","In Wishlist") ?>':'<?= __t("أضف للمفضلة","Add to Wishlist") ?>';
    }
  });
}

// ── Share ─────────────────────────────────────────────────
function shareProduct(){
  if(navigator.share){navigator.share({title:document.title,url:location.href});}
  else{navigator.clipboard?.writeText(location.href);showToast('<?= __t("تم نسخ الرابط","Link copied") ?>','s');}
}

// ── Star picker ───────────────────────────────────────────
const spStars=document.querySelectorAll('.sp-star');
let pickedRating=5;
function renderStars(n){
  spStars.forEach((s,i)=>{
    s.style.color=i<n?'#f59e0b':'var(--line)';
  });
}
renderStars(5);
spStars.forEach((s,i)=>{
  s.addEventListener('mouseover',()=>renderStars(i+1));
  s.addEventListener('click',()=>{pickedRating=i+1;document.getElementById('ratingInput').value=i+1;renderStars(i+1);});
  s.addEventListener('mouseleave',()=>renderStars(pickedRating));
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>