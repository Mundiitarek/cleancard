<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// ─── Parameters ───────────────────────────────────────────
$catSlug  = trim($_GET['cat']       ?? '');
$search   = trim($_GET['q']         ?? '');
$filter   = trim($_GET['filter']    ?? '');
$sortBy   = trim($_GET['sort']      ?? '');
$minPrice = (float)($_GET['min_price'] ?? 0);
$maxPrice = (float)($_GET['max_price'] ?? 0);
$page     = max(1,(int)($_GET['page'] ?? 1));
$perPage  = 20;

$currentCat = $catSlug ? getCategory($catSlug) : null;
$allCats    = getCategories();

$priceRange = dbFetchOne("SELECT MIN(COALESCE(sale_price,price)) as mn, MAX(COALESCE(sale_price,price)) as mx FROM products WHERE is_active=1");
$dbMin = (float)($priceRange['mn'] ?? 0);
$dbMax = (float)($priceRange['mx'] ?? 10000);

// ─── Helpers ──────────────────────────────────────────────
function getProductsFiltered(array $opts): array {
    $where=['p.is_active=1']; $types=''; $params=[];
    if (!empty($opts['category_id'])) { $where[]='p.category_id=?'; $types.='i'; $params[]=(int)$opts['category_id']; }
    if (!empty($opts['is_featured']))  $where[]='p.is_featured=1';
    if (!empty($opts['is_new']))       $where[]='p.is_new=1';
    if (!empty($opts['_sale']))        $where[]='p.sale_price IS NOT NULL AND p.sale_price < p.price';
    if (!empty($opts['search'])) {
        $s='%'.$opts['search'].'%';
        $where[]='(p.name_ar LIKE ? OR p.name_en LIKE ? OR p.tags LIKE ?)';
        $types.='sss'; $params[]=$s; $params[]=$s; $params[]=$s;
    }
    if (!empty($opts['min_price'])) { $where[]='COALESCE(p.sale_price,p.price)>=?'; $types.='d'; $params[]=(float)$opts['min_price']; }
    if (!empty($opts['max_price'])) { $where[]='COALESCE(p.sale_price,p.price)<=?'; $types.='d'; $params[]=(float)$opts['max_price']; }
    $orderBy = match($opts['sort']??'') {
        'price_asc'  => 'COALESCE(p.sale_price,p.price) ASC',
        'price_desc' => 'COALESCE(p.sale_price,p.price) DESC',
        'newest'     => 'p.created_at DESC',
        'popular'    => 'p.views DESC',
        'rating'     => 'p.rating_avg DESC',
        default      => 'p.is_featured DESC, p.created_at DESC',
    };
    $sql = "SELECT p.*, c.name_ar AS cat_ar, c.name_en AS cat_en, c.slug AS cat_slug
            FROM products p LEFT JOIN categories c ON p.category_id=c.id
            WHERE ".implode(' AND ',$where)." ORDER BY $orderBy
            LIMIT ".(int)$opts['limit']." OFFSET ".(int)$opts['offset'];
    return $types ? dbFetchAll($sql,$types,...$params) : dbFetchAll($sql);
}

function countProducts(array $opts): int {
    $where=['p.is_active=1']; $types=''; $params=[];
    if (!empty($opts['category_id'])) { $where[]='p.category_id=?'; $types.='i'; $params[]=(int)$opts['category_id']; }
    if (!empty($opts['is_featured']))  $where[]='p.is_featured=1';
    if (!empty($opts['is_new']))       $where[]='p.is_new=1';
    if (!empty($opts['_sale']))        $where[]='p.sale_price IS NOT NULL AND p.sale_price < p.price';
    if (!empty($opts['search'])) {
        $s='%'.$opts['search'].'%';
        $where[]='(p.name_ar LIKE ? OR p.name_en LIKE ? OR p.tags LIKE ?)';
        $types.='sss'; $params[]=$s; $params[]=$s; $params[]=$s;
    }
    if (!empty($opts['min_price'])) { $where[]='COALESCE(p.sale_price,p.price)>=?'; $types.='d'; $params[]=(float)$opts['min_price']; }
    if (!empty($opts['max_price'])) { $where[]='COALESCE(p.sale_price,p.price)<=?'; $types.='d'; $params[]=(float)$opts['max_price']; }
    $sql = "SELECT COUNT(*) as c FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE ".implode(' AND ',$where);
    $row = $types ? dbFetchOne($sql,$types,...$params) : dbFetchOne($sql);
    return (int)($row['c']??0);
}

function shopUrl(array $extra=[]): string {
    $base = array_filter(array_merge([
        'cat'       => $_GET['cat']       ?? '',
        'q'         => $_GET['q']         ?? '',
        'filter'    => $_GET['filter']    ?? '',
        'sort'      => $_GET['sort']      ?? '',
        'min_price' => $_GET['min_price'] ?? '',
        'max_price' => $_GET['max_price'] ?? '',
    ], $extra));
    return 'shop.php?'.http_build_query($base);
}

$opts = ['limit'=>$perPage,'offset'=>($page-1)*$perPage,'sort'=>$sortBy];
if ($currentCat)          $opts['category_id'] = $currentCat['id'];
if ($search)              $opts['search']       = $search;
if ($filter==='featured') $opts['is_featured']  = true;
if ($filter==='new')      $opts['is_new']       = true;
if ($minPrice>0)          $opts['min_price']    = $minPrice;
if ($maxPrice>0)          $opts['max_price']    = $maxPrice;
if ($filter==='sale')     $opts['_sale']        = true;

$totalCount = countProducts($opts);
$products   = getProductsFiltered($opts);
$totalPages = max(1,(int)ceil($totalCount/$perPage));

$sorts = [
    ''           => __t('الافتراضي','Default'),
    'newest'     => __t('الأحدث','Newest'),
    'price_asc'  => __t('السعر: الأقل أولاً','Price: Low to High'),
    'price_desc' => __t('السعر: الأعلى أولاً','Price: High to Low'),
    'popular'    => __t('الأكثر مشاهدة','Most Viewed'),
    'rating'     => __t('الأعلى تقييماً','Top Rated'),
];

if ($currentCat)              $pageTitle = t($currentCat,'name');
elseif ($search)              $pageTitle = __t('نتائج البحث','Search Results');
elseif ($filter==='featured') $pageTitle = __t('المنتجات المميزة','Featured Products');
elseif ($filter==='new')      $pageTitle = __t('وصل حديثاً','New Arrivals');
elseif ($filter==='sale')     $pageTitle = __t('العروض والخصومات','Offers & Discounts');
else                          $pageTitle = __t('المتجر','Shop');

require_once __DIR__ . '/header.php';

// ─── Product Card ──────────────────────────────────────────
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
                <?php if($outStock): ?>
                    <span class="badge badge-out"><?= __t('نفذ','Out') ?></span>
                <?php elseif($disc): ?>
                    <span class="badge badge-sale">-<?= $disc ?>%</span>
                <?php endif; ?>
                <?php if($p['is_new']&&!$outStock): ?>
                    <span class="badge badge-new"><?= __t('جديد','New') ?></span>
                <?php endif; ?>
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
                    <span class="pc__save"><?= __t('وفّر','Save') ?> <?= formatPrice((float)$p['price']-$price) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="pc__footer">
            <?php if($outStock): ?>
                <button class="pc__atc out" disabled>
                    <svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                    <?= __t('نفذت الكمية','Out of Stock') ?>
                </button>
            <?php else: ?>
                <button class="pc__atc" onclick="addToCart(<?= (int)$p['id'] ?>,this)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15">
                        <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/>
                    </svg>
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
   SHOP PAGE — uses same tokens as header
══════════════════════════════════════ */
html, body { overflow-x: hidden; }

/* ── Hero ── */
.shop-hero {
    background: linear-gradient(135deg, var(--ink-2) 0%, var(--ink) 60%, #1a0a0c 100%);
    padding: 32px 0;
}
.shop-hero__inner {
    display: flex; align-items: center;
    justify-content: space-between;
    gap: 20px; flex-wrap: wrap;
}
.shop-hero__title {
    font-size: clamp(1.3rem,3vw,1.9rem);
    font-weight: 900; color: #fff;
    display: flex; align-items: center;
    gap: 12px; flex-wrap: wrap; margin-bottom: 8px;
}
.shop-hero__count {
    background: var(--red); color: #fff;
    font-size: .72rem; font-weight: 800;
    padding: 4px 12px; border-radius: var(--r-full);
}

/* Quick filter pills */
.qf-pills { display: flex; gap: 8px; flex-wrap: wrap; }
.qf-pill {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 16px; border-radius: var(--r-full);
    font-size: .8rem; font-weight: 600;
    background: rgba(255,255,255,.08);
    color: rgba(255,255,255,.7);
    border: 1px solid rgba(255,255,255,.12);
    transition: all var(--t-base); text-decoration: none;
}
.qf-pill:hover { background: rgba(255,255,255,.15); color: #fff; }
.qf-pill.act { background: var(--red); border-color: var(--red); color: #fff; }
.qf-pill svg { width: 13px; height: 13px; }

/* ── Layout ── */
.shop-wrap {
    display: grid;
    grid-template-columns: 260px 1fr;
    gap: 24px;
    align-items: start;
    padding: 28px 0 64px;
}

/* ── Sidebar ── */
.shop-sidebar {
    position: sticky;
    top: calc(var(--hdr-h) + 16px);
}
.filter-card {
    background: var(--white);
    border: 1px solid var(--line);
    border-radius: var(--r-lg);
    padding: 20px;
    margin-bottom: 14px;
}
.filter-card__title {
    display: flex; align-items: center; gap: 8px;
    font-size: .82rem; font-weight: 800;
    color: var(--ink-3); text-transform: uppercase;
    letter-spacing: .06em; margin-bottom: 16px;
    padding-bottom: 12px; border-bottom: 1.5px solid var(--line-2);
}
.filter-card__title svg { color: var(--red); }

/* Category list */
.fcat-list { display: flex; flex-direction: column; gap: 2px; }
.fcat-item {
    display: flex; align-items: center;
    justify-content: space-between;
    padding: 9px 12px; border-radius: var(--r-md);
    font-size: .84rem; color: var(--body);
    transition: all var(--t-fast); text-decoration: none;
    cursor: pointer;
}
.fcat-item:hover { background: var(--red-5); color: var(--red); }
.fcat-item.act   { background: var(--red-10); color: var(--red); font-weight: 700; }
.fcat-item__left { display: flex; align-items: center; gap: 8px; }
.fcat-item__left svg { width: 14px; height: 14px; color: var(--subtle); flex-shrink: 0; }
.fcat-item.act .fcat-item__left svg { color: var(--red); }
.fcat-count {
    font-size: .68rem; font-weight: 700;
    background: var(--line-2); color: var(--subtle);
    padding: 2px 7px; border-radius: var(--r-full);
}
.fcat-item.act .fcat-count { background: var(--red-10); color: var(--red); }

/* Price range */
.price-vals {
    display: flex; align-items: center; gap: 6px;
    font-size: .84rem; font-weight: 700; color: var(--red);
    margin-bottom: 14px;
}
.price-sep { color: var(--subtle); font-weight: 400; }
.price-slider {
    width: 100%; accent-color: var(--red);
    margin-bottom: 6px; display: block;
    height: 4px; cursor: pointer;
}
.price-inputs-row {
    display: flex; align-items: center; gap: 8px; margin-top: 10px;
}
.price-inp {
    flex: 1; border: 1.5px solid var(--line); border-radius: var(--r-md);
    padding: 8px 10px; font-size: .82rem; outline: none;
    font-family: var(--font); text-align: center; color: var(--ink-3);
    transition: border-color var(--t-base);
}
.price-inp:focus { border-color: var(--red); box-shadow: 0 0 0 3px var(--red-10); }

/* Quick checkboxes */
.qcheck-list { display: flex; flex-direction: column; gap: 8px; }
.qcheck {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 12px; border-radius: var(--r-md);
    cursor: pointer; transition: background var(--t-fast);
    font-size: .85rem; color: var(--body);
}
.qcheck:hover { background: var(--line-2); }
.qcheck input {
    width: 16px; height: 16px;
    accent-color: var(--red); cursor: pointer; flex-shrink: 0;
}
.qcheck svg { width: 14px; height: 14px; color: var(--subtle); }

/* Mobile filter overlay */
.sidebar-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.45); z-index: 499;
    backdrop-filter: blur(3px);
}
.sidebar-overlay.on { display: block; }

/* ── Toolbar ── */
.shop-toolbar {
    display: flex; align-items: center;
    justify-content: space-between; gap: 12px;
    padding: 12px 16px;
    background: var(--white); border: 1px solid var(--line);
    border-radius: var(--r-lg); margin-bottom: 20px;
    flex-wrap: wrap;
}
.toolbar__count {
    font-size: .84rem; color: var(--muted);
}
.toolbar__count strong { color: var(--red); }
.toolbar__right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

/* Sort select */
.sort-wrap { display: flex; align-items: center; gap: 8px; }
.sort-wrap label { font-size: .8rem; color: var(--muted); white-space: nowrap; }
.sort-sel {
    border: 1.5px solid var(--line); border-radius: var(--r-md);
    padding: 7px 12px; font-size: .82rem; outline: none;
    cursor: pointer; font-family: var(--font); color: var(--ink-3);
    transition: border-color var(--t-base);
}
.sort-sel:focus { border-color: var(--red); }

/* View toggle */
.view-toggle { display: flex; gap: 4px; }
.vt-btn {
    width: 34px; height: 34px;
    border-radius: var(--r-md);
    border: 1.5px solid var(--line);
    background: var(--white); color: var(--subtle);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: all var(--t-base);
}
.vt-btn svg { width: 16px; height: 16px; }
.vt-btn.act, .vt-btn:hover { background: var(--red); border-color: var(--red); color: #fff; }

/* Filter toggle (mobile) */
.filter-tog {
    display: none; align-items: center; gap: 6px;
    padding: 8px 14px; border-radius: var(--r-full);
    background: var(--ink-2); color: #fff;
    font-size: .82rem; font-weight: 700;
    cursor: pointer; border: none; font-family: var(--font);
    transition: background var(--t-base);
}
.filter-tog:hover { background: var(--red); }
.filter-tog svg { width: 15px; height: 15px; }

/* Active filters strip */
.active-filters {
    display: flex; align-items: center; gap: 8px;
    flex-wrap: wrap; padding: 10px 14px;
    background: var(--line-2); border-radius: var(--r-md);
    margin-bottom: 16px; font-size: .8rem; color: var(--muted);
}
.af-tag {
    display: inline-flex; align-items: center; gap: 6px;
    background: var(--white); border: 1px solid var(--line);
    padding: 4px 10px; border-radius: var(--r-full);
    color: var(--ink-3); font-size: .78rem;
}
.af-tag a { color: var(--red); line-height: 1; display: flex; }
.af-tag a svg { width: 11px; height: 11px; }
.af-clear {
    margin-<?= isAr()?'right':'left' ?>: auto;
    color: var(--red); font-weight: 700; text-decoration: none;
    font-size: .8rem;
}
.af-clear:hover { text-decoration: underline; }

/* ── Products grid / list ── */
.prods-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
}
.prods-grid.cols-3 { grid-template-columns: repeat(3, 1fr); }

/* List view */
.prods-grid.list-view {
    grid-template-columns: 1fr;
    gap: 12px;
}
.prods-grid.list-view .pc {
    flex-direction: row;
}
.prods-grid.list-view .pc__img-wrap {
    width: 160px; flex-shrink: 0;
    aspect-ratio: auto; height: 160px;
}
.prods-grid.list-view .pc__body { padding: 16px; }
.prods-grid.list-view .pc__footer { padding: 16px; display: flex; align-items: flex-end; }
.prods-grid.list-view .pc__atc { width: auto; padding: 10px 20px; }

/* ── Empty state ── */
.shop-empty {
    text-align: center; padding: 80px 24px;
    background: var(--white); border-radius: var(--r-xl);
    border: 1.5px dashed var(--line);
}
.shop-empty__icon {
    width: 64px; height: 64px; background: var(--line-2);
    border-radius: var(--r-lg); display: flex;
    align-items: center; justify-content: center;
    margin: 0 auto 20px; color: var(--subtle);
}
.shop-empty__icon svg { width: 28px; height: 28px; }
.shop-empty h3 { font-size: 1.1rem; font-weight: 800; color: var(--ink-3); margin-bottom: 8px; }
.shop-empty p  { color: var(--muted); font-size: .9rem; margin-bottom: 24px; }

/* ── Pagination ── */
.pag-wrap { display: flex; justify-content: center; padding-top: 40px; }
.pag { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.pag a, .pag span {
    display: flex; align-items: center; justify-content: center;
    width: 38px; height: 38px; border-radius: var(--r-md);
    font-size: .875rem; font-weight: 600;
    border: 1.5px solid var(--line);
    color: var(--muted); background: var(--white);
    transition: all var(--t-base); text-decoration: none;
}
.pag a:hover  { border-color: var(--red); color: var(--red); background: var(--red-5); }
.pag li.act a { background: var(--red); color: #fff; border-color: var(--red); }
.pag .dots    { border: none; background: none; color: var(--subtle); cursor: default; }

/* ══════════════════════
   RESPONSIVE
══════════════════════ */
@media(max-width:1100px) {
    .prods-grid { grid-template-columns: repeat(3,1fr); }
}
@media(max-width:900px) {
    .shop-wrap { grid-template-columns: 1fr; gap: 0; }
    .shop-sidebar {
        position: fixed;
        top: 0; bottom: 0;
        <?= isAr()?'right':'left' ?>: -290px;
        width: 280px; z-index: 500;
        overflow-y: auto;
        background: var(--white);
        padding: 20px 16px;
        box-shadow: var(--sh-xl);
        transition: <?= isAr()?'right':'left' ?> var(--t-slow) var(--ease);
    }
    .shop-sidebar.open { <?= isAr()?'right':'left' ?>: 0; }
    .filter-tog   { display: flex; }
    .sort-wrap label { display: none; }
    .qf-pills { display: none; }
    .prods-grid { grid-template-columns: repeat(3,1fr); }
}
@media(max-width:640px) {
    .prods-grid { grid-template-columns: repeat(2,1fr); gap: 12px; }
    .prods-grid.list-view .pc { flex-direction: column; }
    .prods-grid.list-view .pc__img-wrap { width: 100%; height: auto; }
    .shop-hero { padding: 24px 0; }
    .vt-btn#viewList { display: none; }
}
@media(max-width:400px) {
    .prods-grid { grid-template-columns: repeat(2,1fr); gap: 8px; }
}
</style>

<!-- ════ HERO ════ -->
<div class="shop-hero">
  <div class="container">
    <div class="shop-hero__inner">
      <div>
        <!-- Breadcrumb -->
        <nav class="breadcrumb" style="margin-bottom:10px;">
          <a href="index.php"><?= __t('الرئيسية','Home') ?></a>
          <span class="sep">
            <svg viewBox="0 0 20 20" fill="currentColor" width="10" height="10"><path fill-rule="evenodd" d="M<?= isAr()?'12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z':'7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z'?>" clip-rule="evenodd"/></svg>
          </span>
          <?php if ($currentCat): ?>
            <a href="shop.php"><?= __t('المتجر','Shop') ?></a>
            <span class="sep"><svg viewBox="0 0 20 20" fill="currentColor" width="10" height="10"><path fill-rule="evenodd" d="M<?= isAr()?'12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z':'7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z'?>" clip-rule="evenodd"/></svg></span>
            <span class="cur"><?= e(t($currentCat,'name')) ?></span>
          <?php else: ?>
            <span class="cur"><?= e($pageTitle) ?></span>
          <?php endif; ?>
        </nav>

        <h1 class="shop-hero__title">
          <?= e($pageTitle) ?>
          <span class="shop-hero__count"><?= $totalCount ?> <?= __t('منتج','Product') ?></span>
        </h1>
        <?php if ($search): ?>
          <p style="color:rgba(255,255,255,.55); font-size:.86rem; margin-top:4px;">
            <?= __t('نتائج البحث عن:','Search results for:') ?>
            "<strong style="color:#fff"><?= e($search) ?></strong>"
          </p>
        <?php endif; ?>
      </div>

      <!-- Quick filter pills -->
      <div class="qf-pills">
        <a href="shop.php" class="qf-pill <?= (!$filter&&!$catSlug&&!$search)?'act':'' ?>">
          <svg viewBox="0 0 20 20" fill="currentColor" width="13" height="13"><path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM14 11a1 1 0 011 1v1h1a1 1 0 110 2h-1v1a1 1 0 11-2 0v-1h-1a1 1 0 110-2h1v-1a1 1 0 011-1z"/></svg>
          <?= __t('الكل','All') ?>
        </a>
        <a href="shop.php?filter=featured" class="qf-pill <?= $filter==='featured'?'act':'' ?>">
          <svg viewBox="0 0 20 20" fill="currentColor" width="13" height="13"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
          <?= __t('مميزة','Featured') ?>
        </a>
        <a href="shop.php?filter=new" class="qf-pill <?= $filter==='new'?'act':'' ?>">
          <svg viewBox="0 0 20 20" fill="currentColor" width="13" height="13"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
          <?= __t('جديد','New') ?>
        </a>
        <a href="shop.php?filter=sale" class="qf-pill <?= $filter==='sale'?'act':'' ?>">
          <svg viewBox="0 0 20 20" fill="currentColor" width="13" height="13"><path fill-rule="evenodd" d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7A.997.997 0 012 10V5a3 3 0 013-3h5c.256 0 .512.098.707.293l7 7zM5 6a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
          <?= __t('عروض','Sale') ?>
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Mobile sidebar overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="sidebarClose()"></div>

<!-- ════ SHOP WRAP ════ -->
<div class="container">
<div class="shop-wrap">

  <!-- ── Sidebar ── -->
  <aside class="shop-sidebar" id="shopSidebar">

    <!-- Close btn (mobile) -->
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; padding-bottom:14px; border-bottom:1px solid var(--line);">
      <span style="font-size:.9rem; font-weight:800; color:var(--ink-3);"><?= __t('الفلاتر','Filters') ?></span>
      <button onclick="sidebarClose()" style="width:30px;height:30px;border-radius:var(--r-sm);background:var(--line-2);display:flex;align-items:center;justify-content:center;cursor:pointer;border:none;" id="sidebarCloseBtn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>

    <form method="GET" action="shop.php" id="filterForm">
      <?php if($search): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>
      <?php if($filter): ?><input type="hidden" name="filter" value="<?= e($filter) ?>"><?php endif; ?>

      <!-- Categories -->
      <div class="filter-card">
        <div class="filter-card__title">
          <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM14 11a1 1 0 011 1v1h1a1 1 0 110 2h-1v1a1 1 0 11-2 0v-1h-1a1 1 0 110-2h1v-1a1 1 0 011-1z"/></svg>
          <?= __t('الأقسام','Categories') ?>
        </div>
        <div class="fcat-list">
          <a href="shop.php<?= $filter?'?filter='.$filter:'' ?>" class="fcat-item <?= !$catSlug?'act':'' ?>">
            <div class="fcat-item__left">
              <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>
              <?= __t('كل الأقسام','All Categories') ?>
            </div>
            <span class="fcat-count"><?= $totalCount ?></span>
          </a>
          <?php foreach ($allCats as $cat):
            $cc=(int)(dbFetchOne("SELECT COUNT(*) c FROM products WHERE category_id=? AND is_active=1",'i',$cat['id'])['c']??0);
          ?>
          <a href="shop.php?cat=<?= urlencode($cat['slug']) ?><?= $filter?'&filter='.$filter:'' ?>"
             class="fcat-item <?= ($catSlug===$cat['slug'])?'act':'' ?>">
            <div class="fcat-item__left">
              <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
              <?= e(t($cat,'name')) ?>
            </div>
            <span class="fcat-count"><?= $cc ?></span>
          </a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Price Range -->
      <div class="filter-card">
        <div class="filter-card__title">
          <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/></svg>
          <?= __t('نطاق السعر','Price Range') ?>
        </div>
        <div class="price-vals">
          <span id="priceMinLbl"><?= number_format($minPrice?:$dbMin) ?></span>
          <span class="price-sep">—</span>
          <span id="priceMaxLbl"><?= number_format($maxPrice?:$dbMax) ?></span>
          <span style="font-size:.76rem; color:var(--subtle); font-weight:500;"><?= currencyLabel() ?></span>
        </div>
        <input type="range" name="min_price" id="rMin"
               min="<?= $dbMin ?>" max="<?= $dbMax ?>" step="10"
               value="<?= $minPrice?:$dbMin ?>"
               class="price-slider" oninput="syncPrice()">
        <input type="range" name="max_price" id="rMax"
               min="<?= $dbMin ?>" max="<?= $dbMax ?>" step="10"
               value="<?= $maxPrice?:$dbMax ?>"
               class="price-slider" oninput="syncPrice()">
        <div class="price-inputs-row">
          <input type="number" id="pMinI" class="price-inp"
                 value="<?= (int)($minPrice?:$dbMin) ?>"
                 min="<?= $dbMin ?>" max="<?= $dbMax ?>"
                 onchange="syncFromInput('min')" placeholder="Min">
          <span style="color:var(--subtle);">—</span>
          <input type="number" id="pMaxI" class="price-inp"
                 value="<?= (int)($maxPrice?:$dbMax) ?>"
                 min="<?= $dbMin ?>" max="<?= $dbMax ?>"
                 onchange="syncFromInput('max')" placeholder="Max">
        </div>
        <button type="submit" class="btn btn-primary btn-sm btn-block" style="margin-top:14px;">
          <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd"/></svg>
          <?= __t('تطبيق','Apply') ?>
        </button>
      </div>

      <!-- Quick filters -->
      <div class="filter-card">
        <div class="filter-card__title">
          <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7A.997.997 0 012 10V5a3 3 0 013-3h5c.256 0 .512.098.707.293l7 7zM5 6a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
          <?= __t('فلاتر سريعة','Quick Filters') ?>
        </div>
        <div class="qcheck-list">
          <label class="qcheck">
            <input type="checkbox" name="filter" value="featured"
                   <?= $filter==='featured'?'checked':'' ?> onchange="this.form.submit()">
            <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
            <?= __t('منتجات مميزة','Featured Only') ?>
          </label>
          <label class="qcheck">
            <input type="checkbox" name="filter" value="new"
                   <?= $filter==='new'?'checked':'' ?> onchange="this.form.submit()">
            <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
            <?= __t('وصل حديثاً','New Arrivals') ?>
          </label>
          <label class="qcheck">
            <input type="checkbox" name="filter" value="sale"
                   <?= $filter==='sale'?'checked':'' ?> onchange="this.form.submit()">
            <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7A.997.997 0 012 10V5a3 3 0 013-3h5c.256 0 .512.098.707.293l7 7zM5 6a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
            <?= __t('منتجات بخصم','On Sale') ?>
          </label>
        </div>
      </div>

      <!-- Sort (sidebar, visible on mobile) -->
      <div class="filter-card">
        <div class="filter-card__title">
          <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path d="M3 3a1 1 0 000 2h11a1 1 0 100-2H3zM3 7a1 1 0 000 2h7a1 1 0 100-2H3zM3 11a1 1 0 100 2h4a1 1 0 100-2H3zM15 7a1 1 0 10-2 0v5.586l-1.293-1.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L15 12.586V7z"/></svg>
          <?= __t('الترتيب','Sort By') ?>
        </div>
        <select name="sort" class="form-control" onchange="this.form.submit()">
          <?php foreach ($sorts as $val=>$lbl): ?>
            <option value="<?= $val ?>" <?= $sortBy===$val?'selected':'' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>

    </form>
  </aside>

  <!-- ── Main ── -->
  <div class="shop-main">

    <!-- Toolbar -->
    <div class="shop-toolbar">
      <p class="toolbar__count">
        <?= __t('عرض','Showing') ?>
        <strong><?= min(($page-1)*$perPage+1,$totalCount) ?>–<?= min($page*$perPage,$totalCount) ?></strong>
        <?= __t('من','of') ?>
        <strong><?= $totalCount ?></strong>
        <?= __t('منتج','products') ?>
      </p>
      <div class="toolbar__right">
        <!-- Sort desktop -->
        <div class="sort-wrap">
          <label><?= __t('ترتيب:','Sort:') ?></label>
          <select class="sort-sel" onchange="location.href='<?= shopUrl() ?>&sort='+this.value">
            <?php foreach ($sorts as $val=>$lbl): ?>
              <option value="<?= $val ?>" <?= $sortBy===$val?'selected':'' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <!-- View toggle -->
        <div class="view-toggle">
          <button class="vt-btn act" id="btnGrid" title="<?= __t('شبكة','Grid') ?>">
            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
          </button>
          <button class="vt-btn" id="btnList" title="<?= __t('قائمة','List') ?>">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>
          </button>
        </div>
        <!-- Filter toggle (mobile) -->
        <button class="filter-tog" id="filterTog" onclick="sidebarOpen()">
          <svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd"/></svg>
          <?= __t('فلتر','Filter') ?>
        </button>
      </div>
    </div>

    <!-- Active filters -->
    <?php if ($catSlug||$search||$filter||$minPrice||$maxPrice): ?>
    <div class="active-filters">
      <svg viewBox="0 0 20 20" fill="currentColor" width="13" height="13"><path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd"/></svg>
      <?= __t('الفلاتر:','Filters:') ?>
      <?php if ($currentCat): ?>
        <span class="af-tag">
          <?= e(t($currentCat,'name')) ?>
          <a href="shop.php<?= $filter?'?filter='.$filter:'' ?>">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
          </a>
        </span>
      <?php endif; ?>
      <?php if ($search): ?>
        <span class="af-tag">
          "<?= e($search) ?>"
          <a href="shop.php<?= $catSlug?'?cat='.$catSlug:'' ?>">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
          </a>
        </span>
      <?php endif; ?>
      <?php if ($filter): ?>
        <span class="af-tag"><?= $sorts[''] ?? $filter ?></span>
      <?php endif; ?>
      <?php if ($minPrice||$maxPrice): ?>
        <span class="af-tag">
          <?= number_format($minPrice) ?>–<?= number_format($maxPrice) ?> <?= currencyLabel() ?>
          <a href="<?= shopUrl(['min_price'=>'','max_price'=>'']) ?>">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
          </a>
        </span>
      <?php endif; ?>
      <a href="shop.php" class="af-clear"><?= __t('مسح الكل','Clear All') ?></a>
    </div>
    <?php endif; ?>

    <!-- Products -->
    <?php if ($products): ?>
      <div class="prods-grid" id="prodsGrid">
        <?php foreach ($products as $p) echo pCard($p); ?>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages>1): ?>
      <div class="pag-wrap">
        <ul class="pag">
          <?php if ($page>1): ?>
            <li><a href="<?= shopUrl(['page'=>$page-1]) ?>">
              <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M<?= isAr()?'7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z':'12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z'?>" clip-rule="evenodd"/></svg>
            </a></li>
          <?php endif; ?>
          <?php
          $s=max(1,$page-2); $e=min($totalPages,$page+2);
          if($s>1): ?><li><a href="<?= shopUrl(['page'=>1]) ?>">1</a></li><?php if($s>2): ?><li><span class="dots">…</span></li><?php endif; endif;
          for($i=$s;$i<=$e;$i++): ?>
            <li class="<?= $i===$page?'act':'' ?>"><a href="<?= shopUrl(['page'=>$i]) ?>"><?= $i ?></a></li>
          <?php endfor;
          if($e<$totalPages): if($e<$totalPages-1): ?><li><span class="dots">…</span></li><?php endif; ?><li><a href="<?= shopUrl(['page'=>$totalPages]) ?>"><?= $totalPages ?></a></li><?php endif; ?>
          <?php if ($page<$totalPages): ?>
            <li><a href="<?= shopUrl(['page'=>$page+1]) ?>">
              <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M<?= isAr()?'12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z':'7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z'?>" clip-rule="evenodd"/></svg>
            </a></li>
          <?php endif; ?>
        </ul>
      </div>
      <?php endif; ?>

    <?php else: ?>
      <div class="shop-empty">
        <div class="shop-empty__icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        </div>
        <h3><?= __t('لا توجد منتجات','No Products Found') ?></h3>
        <p><?= __t('جرب تغيير الفلاتر أو البحث بكلمات مختلفة','Try changing filters or search with different keywords') ?></p>
        <a href="shop.php" class="btn btn-primary">
          <svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
          <?= __t('عرض كل المنتجات','View All Products') ?>
        </a>
      </div>
    <?php endif; ?>

  </div><!-- /.shop-main -->
</div><!-- /.shop-wrap -->
</div><!-- /.container -->

<script>
/* ── Sidebar mobile ── */
function sidebarOpen(){
  document.getElementById('shopSidebar').classList.add('open');
  document.getElementById('sidebarOverlay').classList.add('on');
  document.body.style.overflow='hidden';
}
function sidebarClose(){
  document.getElementById('shopSidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('on');
  document.body.style.overflow='';
}

/* ── View toggle ── */
document.getElementById('btnGrid')?.addEventListener('click',function(){
  document.getElementById('prodsGrid')?.classList.remove('list-view');
  this.classList.add('act');
  document.getElementById('btnList')?.classList.remove('act');
});
document.getElementById('btnList')?.addEventListener('click',function(){
  document.getElementById('prodsGrid')?.classList.add('list-view');
  this.classList.add('act');
  document.getElementById('btnGrid')?.classList.remove('act');
});

/* ── Price range ── */
function syncPrice(){
  const mn=document.getElementById('rMin'),mx=document.getElementById('rMax');
  let mnV=+mn.value, mxV=+mx.value;
  if(mnV>mxV){ mn.value=mxV; mnV=mxV; }
  document.getElementById('priceMinLbl').textContent=Math.round(mnV).toLocaleString();
  document.getElementById('priceMaxLbl').textContent=Math.round(mxV).toLocaleString();
  document.getElementById('pMinI').value=Math.round(mnV);
  document.getElementById('pMaxI').value=Math.round(mxV);
}
function syncFromInput(w){
  const mn=document.getElementById('pMinI'),mx=document.getElementById('pMaxI');
  if(w==='min') document.getElementById('rMin').value=mn.value;
  else          document.getElementById('rMax').value=mx.value;
  syncPrice();
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>