<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// ─── AJAX ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && !empty($_POST['ajax'])) {
    header('Content-Type: application/json');
    if (!verifyCsrfToken($_POST['csrf_token']??'')) {
        echo json_encode(['success'=>false,'message'=>__t('رمز غير صالح','Invalid token')]); exit;
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'remove') {
        $ok    = removeFromCart((int)($_POST['cart_id']??0));
        $items = getCartItems();
        $sub   = getCartTotal();
        $ship  = $sub >= freeShippingMin() ? 0 : shippingFee();
        echo json_encode([
            'success'  => $ok,
            'message'  => $ok ? __t('تم الحذف','Removed') : __t('حدث خطأ','Error'),
            'count'    => getCartCount(),
            'subtotal' => formatPrice($sub),
            'shipping' => $ship==0 ? '<span style="color:#065f46;font-weight:700">'.__t('مجاني','Free').'</span>' : formatPrice($ship),
            'total'    => formatPrice($sub + $ship),
            'empty'    => empty($items),
        ]); exit;
    }

    if ($action === 'update') {
        $cartId = (int)($_POST['cart_id']??0);
        $qty    = max(1,(int)($_POST['qty']??1));
        $ok     = updateCartQty($cartId, $qty);
        $sub    = getCartTotal();
        $ship   = $sub >= freeShippingMin() ? 0 : shippingFee();
        $items  = getCartItems();
        $item   = array_values(array_filter($items, fn($i)=>$i['id']==$cartId))[0] ?? null;
        $lineTotal = $item ? formatPrice(($item['sale_price']?:$item['price'])*$qty) : '';
        echo json_encode([
            'success'    => $ok,
            'count'      => getCartCount(),
            'line_total' => $lineTotal,
            'subtotal'   => formatPrice($sub),
            'shipping'   => $ship==0 ? '<span style="color:#065f46;font-weight:700">'.__t('مجاني','Free').'</span>' : formatPrice($ship),
            'total'      => formatPrice($sub + $ship),
        ]); exit;
    }

    if ($action === 'coupon') {
        $code = strtoupper(trim($_POST['code']??''));
        $sub  = getCartTotal();
        $res  = applyCoupon($code, $sub);
        if ($res['success']) {
            $_SESSION['coupon'] = ['code'=>$code,'discount'=>$res['discount']];
            $ship  = $sub >= freeShippingMin() ? 0 : shippingFee();
            $total = max(0, $sub + $ship - $res['discount']);
            $res['discount_formatted'] = formatPrice($res['discount']);
            $res['total_formatted']    = formatPrice($total);
        }
        echo json_encode($res); exit;
    }

    if ($action === 'remove_coupon') {
        unset($_SESSION['coupon']);
        $sub  = getCartTotal();
        $ship = $sub >= freeShippingMin() ? 0 : shippingFee();
        echo json_encode([
            'success'  => true,
            'subtotal' => formatPrice($sub),
            'shipping' => $ship==0 ? '<span style="color:#065f46;font-weight:700">'.__t('مجاني','Free').'</span>' : formatPrice($ship),
            'total'    => formatPrice($sub+$ship),
        ]); exit;
    }

    echo json_encode(['success'=>false,'message'=>'Unknown action']); exit;
}

// ─── Page data ─────────────────────────────────────────────
$items             = getCartItems();
$subtotal          = getCartTotal();
$coupon            = $_SESSION['coupon'] ?? null;
$discount          = $coupon ? (float)$coupon['discount'] : 0;
$shipping          = $subtotal >= freeShippingMin() ? 0 : ($subtotal > 0 ? shippingFee() : 0);
$total             = max(0, $subtotal + $shipping - $discount);
$freeShipRemaining = max(0, freeShippingMin() - $subtotal);

$pageTitle = __t('سلة التسوق','Shopping Cart');
require_once __DIR__ . '/header.php';

// Product card helper
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
            <div class="pc__price">
                <span class="pc__price-now"><?= formatPrice($price) ?></span>
                <?php if($onSale): ?><span class="pc__price-was"><?= formatPrice((float)$p['price']) ?></span><?php endif; ?>
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
html, body { overflow-x: hidden; }

/* ── Hero ── */
.cart-hero {
    background: linear-gradient(135deg, var(--ink-2) 0%, var(--ink) 60%, #1a0a0c 100%);
    padding: 28px 0;
}
.cart-hero__inner {
    display: flex; align-items: center;
    justify-content: space-between; gap: 16px; flex-wrap: wrap;
}
.cart-hero__title {
    font-size: clamp(1.2rem,2.5vw,1.7rem);
    font-weight: 900; color: #fff;
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
}
.cart-hero__title svg { color: var(--red); }
.cart-count-pill {
    background: var(--red); color: #fff;
    font-size: .72rem; font-weight: 800;
    padding: 4px 12px; border-radius: var(--r-full);
    id: pageCartCount;
}

/* ── Free shipping bar ── */
.fsbar {
    padding: 12px 0;
    border-bottom: 1px solid var(--line);
}
.fsbar--pending { background: #fffbeb; border-color: #fde68a; }
.fsbar--done    { background: #f0fdf4; border-color: #bbf7d0; }
.fsbar__inner   { display: flex; align-items: center; gap: 14px; }
.fsbar__icon {
    width: 36px; height: 36px; border-radius: var(--r-md);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.fsbar--pending .fsbar__icon { background: #fef3c7; color: #d97706; }
.fsbar--done    .fsbar__icon { background: #d1fae5; color: #059669; }
.fsbar__icon svg { width: 18px; height: 18px; }
.fsbar__body { flex: 1; }
.fsbar__text { font-size: .86rem; font-weight: 500; color: var(--ink-3); margin-bottom: 6px; }
.fsbar__text strong { color: var(--ink); }
.fsbar--done .fsbar__text { color: #065f46; font-weight: 700; margin-bottom: 0; }
.fsbar__track {
    height: 5px; background: #fde68a; border-radius: var(--r-full); overflow: hidden;
}
.fsbar__fill {
    height: 100%; background: linear-gradient(90deg, #f59e0b, #d97706);
    border-radius: var(--r-full); transition: width .6s var(--ease);
}

/* ── Layout ── */
.cart-wrap {
    display: grid;
    grid-template-columns: 1fr 356px;
    gap: 24px;
    align-items: start;
    padding: 28px 0 64px;
}

/* ── Items column ── */
.cart-col {}
.cart-col__head {
    display: flex; align-items: center;
    justify-content: space-between; margin-bottom: 16px;
}
.cart-col__title {
    font-size: .95rem; font-weight: 800; color: var(--ink-3);
    display: flex; align-items: center; gap: 8px;
}
.cart-col__title svg { color: var(--red); width: 16px; height: 16px; }

/* Cart item row */
.cart-item {
    display: grid;
    grid-template-columns: 88px 1fr auto auto auto;
    align-items: center; gap: 16px;
    background: var(--white);
    border: 1px solid var(--line);
    border-radius: var(--r-lg); padding: 16px;
    margin-bottom: 10px;
    transition: box-shadow var(--t-base), border-color var(--t-base);
}
.cart-item:hover { box-shadow: var(--sh-sm); border-color: var(--line-3); }

.ci-img-link { display: block; flex-shrink: 0; }
.ci-img {
    width: 88px; height: 88px; object-fit: cover;
    border-radius: var(--r-md); border: 1px solid var(--line);
    display: block;
}

.ci-details { min-width: 0; }
.ci-name {
    font-size: .88rem; font-weight: 700; color: var(--ink-3);
    display: block; margin-bottom: 6px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    text-decoration: none; transition: color var(--t-base);
}
.ci-name:hover { color: var(--red); }
.ci-price-row  { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; }
.ci-price-now  { font-size: .9rem; font-weight: 700; color: var(--red); }
.ci-price-was  { font-size: .76rem; color: var(--subtle); text-decoration: line-through; }
.ci-stock-warn {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: .72rem; color: #d97706; font-weight: 600;
}
.ci-stock-warn svg { width: 11px; height: 11px; }

/* Qty stepper */
.ci-qty {
    display: flex; align-items: center;
    border: 1.5px solid var(--line);
    border-radius: var(--r-full); overflow: hidden;
    flex-shrink: 0;
}
.ci-qty-btn {
    width: 32px; height: 36px;
    background: var(--line-2); color: var(--body);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; border: none; font-family: var(--font);
    transition: all var(--t-base);
}
.ci-qty-btn svg { width: 12px; height: 12px; }
.ci-qty-btn:hover { background: var(--red); color: #fff; }
.ci-qty-inp {
    width: 40px; border: none; text-align: center;
    font-size: .88rem; font-weight: 700; color: var(--ink-3);
    height: 36px; outline: none; font-family: var(--font);
    background: var(--white);
}
.ci-qty-inp::-webkit-inner-spin-button { display: none; }

/* Line total */
.ci-total {
    font-size: .92rem; font-weight: 800; color: var(--ink-3);
    white-space: nowrap; min-width: 72px; text-align: center;
    flex-shrink: 0;
}

/* Remove btn */
.ci-del {
    width: 30px; height: 30px; border-radius: var(--r-md);
    background: var(--line-2); color: var(--subtle);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; border: none; flex-shrink: 0;
    transition: all var(--t-base);
}
.ci-del svg { width: 13px; height: 13px; }
.ci-del:hover { background: #fee2e2; color: #dc2626; }

/* Cart actions footer */
.cart-footer-row {
    display: flex; align-items: center;
    justify-content: space-between;
    padding: 16px 0; border-top: 1px solid var(--line-2); margin-top: 4px;
    flex-wrap: wrap; gap: 10px;
}

/* ── Order Summary ── */
.order-summary {
    background: var(--white); border: 1px solid var(--line);
    border-radius: var(--r-xl); padding: 24px;
    position: sticky;
    top: calc(var(--hdr-h) + 16px);
}
.summary__title {
    font-size: .95rem; font-weight: 800; color: var(--ink-3);
    margin-bottom: 20px; padding-bottom: 14px;
    border-bottom: 2px solid var(--red);
    display: flex; align-items: center; gap: 8px;
}
.summary__title svg { color: var(--red); width: 16px; height: 16px; }

.sum-rows { margin-bottom: 20px; }
.sum-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 10px 0; font-size: .875rem; color: var(--body);
    border-bottom: 1px dashed var(--line-2);
}
.sum-row:last-child { border-bottom: none; }
.sum-row__label { display: flex; align-items: center; gap: 6px; }
.sum-row__label svg { width: 13px; height: 13px; color: var(--subtle); }
.sum-total {
    padding-top: 16px !important; border-top: 2px solid var(--line) !important;
    border-bottom: none !important;
    font-size: 1rem; font-weight: 900; color: var(--ink-3);
}
.sum-total span:last-child { color: var(--red); font-size: 1.15rem; }
.sum-discount span:last-child { color: #059669; font-weight: 700; }

/* Coupon */
.coupon-box {
    background: var(--line-2); border-radius: var(--r-lg);
    padding: 16px; margin-bottom: 16px;
}
.coupon-label {
    font-size: .8rem; font-weight: 700; color: var(--ink-3);
    margin-bottom: 10px; display: flex; align-items: center; gap: 6px;
}
.coupon-label svg { width: 14px; height: 14px; color: var(--red); }
.coupon-row { display: flex; }
.coupon-inp {
    flex: 1; border: 1.5px solid var(--line);
    border-radius: var(--r-full) 0 0 var(--r-full);
    padding: 9px 14px; font-size: .82rem; outline: none;
    font-family: var(--font); background: var(--white);
    transition: border-color var(--t-base); color: var(--ink-3);
}
[dir=ltr] .coupon-inp { border-radius: 0 var(--r-full) var(--r-full) 0; }
.coupon-inp:focus { border-color: var(--red); }
.coupon-apply {
    background: var(--red); color: #fff; border: none;
    border-radius: 0 var(--r-full) var(--r-full) 0;
    padding: 9px 16px; font-size: .82rem; font-weight: 700;
    cursor: pointer; font-family: var(--font);
    transition: background var(--t-base); white-space: nowrap;
}
[dir=ltr] .coupon-apply { border-radius: var(--r-full) 0 0 var(--r-full); }
.coupon-apply:hover { background: var(--red-d); }
.coupon-msg { font-size: .78rem; margin-top: 7px; font-weight: 600; }
.coupon-msg.ok  { color: #059669; }
.coupon-msg.err { color: #dc2626; }

.coupon-applied {
    display: flex; align-items: center; justify-content: space-between;
    background: #d1fae5; color: #065f46;
    padding: 8px 14px; border-radius: var(--r-full);
    font-size: .82rem; font-weight: 700;
    gap: 8px;
}
.coupon-applied svg { width: 13px; height: 13px; flex-shrink: 0; }
.coupon-rm {
    background: none; border: none; cursor: pointer;
    color: #065f46; display: flex; align-items: center;
    padding: 2px;
}
.coupon-rm svg { width: 13px; height: 13px; }
.coupon-rm:hover { color: #dc2626; }

/* Checkout btn */
.checkout-btn {
    width: 100%; margin-bottom: 14px;
    font-size: .95rem; display: flex;
    align-items: center; justify-content: center; gap: 8px;
}
.checkout-btn svg { width: 16px; height: 16px; }

/* Payment row */
.pay-row {
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    font-size: .76rem; color: var(--muted); margin-bottom: 12px;
}
.pay-row__label { font-weight: 700; color: var(--ink-3); }
.pay-badge {
    display: inline-flex; align-items: center; gap: 5px;
    background: var(--line-2); padding: 3px 10px;
    border-radius: var(--r-full); color: var(--body); font-weight: 600;
}
.pay-badge svg { width: 12px; height: 12px; }

/* Security row */
.sec-row {
    display: flex; align-items: center; justify-content: center;
    gap: 14px; flex-wrap: wrap;
    border-top: 1px solid var(--line-2); padding-top: 12px;
}
.sec-item {
    display: flex; align-items: center; gap: 4px;
    font-size: .72rem; color: var(--muted); font-weight: 600;
}
.sec-item svg { width: 12px; height: 12px; color: var(--subtle); }

/* Free ship reminder */
.ship-reminder {
    background: var(--red-5); border: 1px solid var(--red-10);
    border-radius: var(--r-md); padding: 10px 14px;
    margin-top: 12px; font-size: .78rem; color: var(--red);
    display: flex; align-items: center; gap: 7px; font-weight: 600;
}
.ship-reminder svg { width: 14px; height: 14px; flex-shrink: 0; }

/* ── Empty cart ── */
.cart-empty {
    text-align: center; padding: 80px 24px;
    background: var(--white); border-radius: var(--r-xl);
    border: 1.5px dashed var(--line);
}
.cart-empty__icon {
    width: 80px; height: 80px; background: var(--line-2);
    border-radius: var(--r-xl); display: flex;
    align-items: center; justify-content: center;
    margin: 0 auto 22px; color: var(--subtle);
    animation: float 3s ease-in-out infinite;
}
.cart-empty__icon svg { width: 36px; height: 36px; }
@keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-10px)} }
.cart-empty h2 { font-size: 1.4rem; font-weight: 900; color: var(--ink-3); margin-bottom: 8px; }
.cart-empty p  { color: var(--muted); margin-bottom: 28px; font-size: .9rem; }
.empty-login   { margin-top: 20px; font-size: .84rem; color: var(--muted); }
.empty-login a { color: var(--red); font-weight: 700; }

/* ══════════════════
   RESPONSIVE
══════════════════ */
@media(max-width:900px) {
    .cart-wrap   { grid-template-columns: 1fr; }
    .order-summary { position: static; }
}
@media(max-width:640px) {
    .cart-item { grid-template-columns: 70px 1fr auto; grid-template-rows: auto auto; gap: 10px; position: relative; }
    .ci-img    { width: 70px; height: 70px; }
    .ci-qty    { grid-column: 2; }
    .ci-total  { grid-column: 1; text-align: <?= isAr()?'right':'left' ?>; }
    .ci-del    { position: absolute; top: 10px; <?= isAr()?'left':'right' ?>: 10px; }
}
</style>

<!-- ── Hero ── -->
<div class="cart-hero">
  <div class="container">
    <div class="cart-hero__inner">
      <div>
        <nav class="breadcrumb" style="margin-bottom:10px;">
          <a href="index.php"><?= __t('الرئيسية','Home') ?></a>
          <span class="sep"><svg viewBox="0 0 20 20" fill="currentColor" width="10" height="10"><path fill-rule="evenodd" d="M<?= isAr()?'12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z':'7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z'?>" clip-rule="evenodd"/></svg></span>
          <span class="cur"><?= __t('سلة التسوق','Shopping Cart') ?></span>
        </nav>
        <h1 class="cart-hero__title">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="26" height="26">
            <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/>
          </svg>
          <?= __t('سلة التسوق','Shopping Cart') ?>
          <span class="cart-count-pill" id="pageCartCount"><?= count($items) ?> <?= __t('منتج','items') ?></span>
        </h1>
      </div>
      <?php if ($items): ?>
      <a href="shop.php" class="btn btn-outline btn-sm" style="color:#fff;border-color:rgba(255,255,255,.3);">
        <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M<?= isAr()?'7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z':'12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z'?>" clip-rule="evenodd"/></svg>
        <?= __t('متابعة التسوق','Continue Shopping') ?>
      </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($items): ?>

<!-- ── Free shipping progress ── -->
<?php if ($freeShipRemaining > 0): ?>
<div class="fsbar fsbar--pending">
  <div class="container">
    <div class="fsbar__inner">
      <div class="fsbar__icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H11a1 1 0 001-1V5a1 1 0 00-1-1H3zM14 7a1 1 0 00-1 1v6.05A2.5 2.5 0 0115.95 16H17a1 1 0 001-1v-5a1 1 0 00-.293-.707L16 7.586A1 1 0 0015.293 7H14z"/></svg>
      </div>
      <div class="fsbar__body">
        <div class="fsbar__text">
          <?= __t('أضف','Add') ?> <strong><?= formatPrice($freeShipRemaining) ?></strong> <?= __t('للحصول على شحن مجاني!','more for free shipping!') ?>
        </div>
        <div class="fsbar__track">
          <div class="fsbar__fill" style="width:<?= min(100,round($subtotal/freeShippingMin()*100)) ?>%"></div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php else: ?>
<div class="fsbar fsbar--done">
  <div class="container">
    <div class="fsbar__inner">
      <div class="fsbar__icon">
        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
      </div>
      <div class="fsbar__text"><?= __t('تهانينا! طلبك مؤهل للشحن المجاني','Congratulations! Your order qualifies for free shipping') ?></div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ── Cart layout ── -->
<div class="container">
<div class="cart-wrap">

  <!-- Items column -->
  <div class="cart-col">
    <div class="cart-col__head">
      <span class="cart-col__title">
        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 2a4 4 0 00-4 4v1H5a1 1 0 00-.994.89l-1 9A1 1 0 004 18h12a1 1 0 00.994-1.11l-1-9A1 1 0 0015 7h-1V6a4 4 0 00-4-4zm2 5V6a2 2 0 10-4 0v1h4zm-6 3a1 1 0 112 0 1 1 0 01-2 0zm7-1a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd"/></svg>
        <?= __t('المنتجات','Products') ?> (<?= count($items) ?>)
      </span>
      <button class="btn btn-sm" onclick="clearAllCart()"
              style="background:var(--line-2);color:var(--muted);border:1px solid var(--line);display:flex;align-items:center;gap:6px;font-size:.78rem;">
        <svg viewBox="0 0 20 20" fill="currentColor" width="13" height="13"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
        <?= __t('تفريغ السلة','Clear Cart') ?>
      </button>
    </div>

    <div id="cartItemsList">
      <?php foreach ($items as $item):
        $itemPrice = (float)($item['sale_price'] ?: $item['price']);
        $lineTotal = $itemPrice * (int)$item['quantity'];
        $isOnSale  = $item['sale_price'] && $item['sale_price'] < $item['price'];
        $img       = productImage($item);
      ?>
      <div class="cart-item" data-id="<?= (int)$item['id'] ?>" id="cartItem_<?= (int)$item['id'] ?>">

        <!-- Image -->
        <a href="product.php?slug=<?= urlencode($item['slug']) ?>" class="ci-img-link">
          <img src="<?= e($img) ?>" alt="<?= e(t($item,'name')) ?>" class="ci-img"
               onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgZmlsbD0iI2YxZjNmNiIvPjwvc3ZnPg=='">
        </a>

        <!-- Details -->
        <div class="ci-details">
          <a href="product.php?slug=<?= urlencode($item['slug']) ?>" class="ci-name">
            <?= e(t($item,'name')) ?>
          </a>
          <div class="ci-price-row">
            <span class="ci-price-now"><?= formatPrice($itemPrice) ?></span>
            <?php if ($isOnSale): ?>
              <span class="ci-price-was"><?= formatPrice((float)$item['price']) ?></span>
            <?php endif; ?>
          </div>
          <?php if ($item['stock'] <= 5): ?>
          <span class="ci-stock-warn">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            <?= __t('باقي','Only') ?> <?= (int)$item['stock'] ?> <?= __t('قطعة','left') ?>
          </span>
          <?php endif; ?>
        </div>

        <!-- Qty stepper -->
        <div class="ci-qty">
          <button class="ci-qty-btn" onclick="updateQty(<?= (int)$item['id'] ?>,-1)">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>
          </button>
          <input type="number" class="ci-qty-inp"
                 id="qty_<?= (int)$item['id'] ?>"
                 value="<?= (int)$item['quantity'] ?>"
                 min="1" max="<?= (int)$item['stock'] ?>"
                 onchange="updateQtyDirect(<?= (int)$item['id'] ?>)">
          <button class="ci-qty-btn" onclick="updateQty(<?= (int)$item['id'] ?>,1)">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
          </button>
        </div>

        <!-- Line total -->
        <div class="ci-total" id="lineTotal_<?= (int)$item['id'] ?>">
          <?= formatPrice($lineTotal) ?>
        </div>

        <!-- Delete -->
        <button class="ci-del" onclick="removeItem(<?= (int)$item['id'] ?>)" title="<?= __t('حذف','Remove') ?>">
          <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
        </button>

      </div>
      <?php endforeach; ?>
    </div>

    <!-- Footer actions -->
    <div class="cart-footer-row">
      <a href="shop.php" class="btn btn-outline btn-sm">
        <svg viewBox="0 0 20 20" fill="currentColor" width="13" height="13"><path fill-rule="evenodd" d="M<?= isAr()?'7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z':'12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z'?>" clip-rule="evenodd"/></svg>
        <?= __t('متابعة التسوق','Continue Shopping') ?>
      </a>
      <span style="font-size:.8rem; color:var(--muted);">
        <?= __t('المجموع','Total') ?>:
        <strong style="color:var(--red); font-size:.95rem;"><?= formatPrice($subtotal) ?></strong>
      </span>
    </div>
  </div>

  <!-- Order Summary -->
  <div class="cart-summary-col">
    <div class="order-summary">

      <div class="summary__title">
        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
        <?= __t('ملخص الطلب','Order Summary') ?>
      </div>

      <div class="sum-rows">
        <div class="sum-row">
          <span class="sum-row__label">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 2a4 4 0 00-4 4v1H5a1 1 0 00-.994.89l-1 9A1 1 0 004 18h12a1 1 0 00.994-1.11l-1-9A1 1 0 0015 7h-1V6a4 4 0 00-4-4zm2 5V6a2 2 0 10-4 0v1h4zm-6 3a1 1 0 112 0 1 1 0 01-2 0zm7-1a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd"/></svg>
            <?= __t('المجموع الفرعي','Subtotal') ?>
          </span>
          <span id="sumSubtotal"><?= formatPrice($subtotal) ?></span>
        </div>
        <div class="sum-row">
          <span class="sum-row__label">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="13" height="13"><path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H11a1 1 0 001-1V5a1 1 0 00-1-1H3zM14 7a1 1 0 00-1 1v6.05A2.5 2.5 0 0115.95 16H17a1 1 0 001-1v-5a1 1 0 00-.293-.707L16 7.586A1 1 0 0015.293 7H14z"/></svg>
            <?= __t('رسوم الشحن','Shipping') ?>
          </span>
          <span id="sumShipping">
            <?php if ($shipping==0 && $subtotal>0): ?>
              <span style="color:#065f46;font-weight:700;"><?= __t('مجاني','Free') ?></span>
            <?php else: ?>
              <?= formatPrice($shipping) ?>
            <?php endif; ?>
          </span>
        </div>
        <?php if ($discount > 0): ?>
        <div class="sum-row sum-discount" id="discountRow">
          <span class="sum-row__label">
            <svg viewBox="0 0 20 20" fill="currentColor" width="13" height="13"><path fill-rule="evenodd" d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7A.997.997 0 012 10V5a3 3 0 013-3h5c.256 0 .512.098.707.293l7 7zM5 6a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
            <?= __t('خصم الكوبون','Coupon') ?>
          </span>
          <span id="sumDiscount" style="color:#059669;font-weight:700;">− <?= formatPrice($discount) ?></span>
        </div>
        <?php endif; ?>
        <div class="sum-row sum-total">
          <span><?= __t('الإجمالي','Total') ?></span>
          <span id="sumTotal"><?= formatPrice($total) ?></span>
        </div>
      </div>

      <!-- Coupon -->
      <div class="coupon-box">
        <div class="coupon-label">
          <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7A.997.997 0 012 10V5a3 3 0 013-3h5c.256 0 .512.098.707.293l7 7zM5 6a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
          <?= __t('كوبون خصم','Coupon Code') ?>
        </div>
        <?php if ($coupon): ?>
          <div class="coupon-applied">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            <span><strong><?= e($coupon['code']) ?></strong> — − <?= formatPrice($discount) ?></span>
            <button class="coupon-rm" onclick="removeCoupon()">
              <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
            </button>
          </div>
        <?php else: ?>
          <div class="coupon-row">
            <input type="text" id="couponInput" class="coupon-inp"
                   placeholder="<?= __t('أدخل الكود...','Enter code...') ?>">
            <button class="coupon-apply" onclick="applyCoupon()"><?= __t('تطبيق','Apply') ?></button>
          </div>
          <div class="coupon-msg" id="couponMsg"></div>
        <?php endif; ?>
      </div>

      <!-- Checkout -->
      <a href="checkout.php" class="btn btn-primary btn-lg checkout-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        <?= __t('إتمام الشراء','Proceed to Checkout') ?>
      </a>

      <!-- Payment -->
      <div class="pay-row">
        <span class="pay-row__label"><?= __t('الدفع:','Payment:') ?></span>
        <span class="pay-badge">
          <svg viewBox="0 0 20 20" fill="currentColor" width="12" height="12"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>
          <?= __t('الدفع عند الاستلام','Cash on Delivery') ?>
        </span>
      </div>

      <!-- Security -->
      <div class="sec-row">
        <span class="sec-item">
          <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
          <?= __t('آمن','Secure') ?>
        </span>
        <span class="sec-item">
          <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
          <?= __t('مضمون','Guaranteed') ?>
        </span>
        <span class="sec-item">
          <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/></svg>
          <?= __t('إرجاع مجاني','Free Return') ?>
        </span>
      </div>

      <!-- Free shipping reminder -->
      <?php if ($freeShipRemaining > 0): ?>
      <div class="ship-reminder">
        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H11a1 1 0 001-1V5a1 1 0 00-1-1H3zM14 7a1 1 0 00-1 1v6.05A2.5 2.5 0 0115.95 16H17a1 1 0 001-1v-5a1 1 0 00-.293-.707L16 7.586A1 1 0 0015.293 7H14z"/></svg>
        <?= __t('أضف','Add') ?> <strong><?= formatPrice($freeShipRemaining) ?></strong> <?= __t('للشحن المجاني','for free shipping') ?>
      </div>
      <?php endif; ?>

    </div>
  </div>

</div><!-- /.cart-wrap -->
</div><!-- /.container -->

<?php else: ?>

<!-- ── Empty cart ── -->
<div class="container" style="padding:48px 0 80px;">
  <div class="cart-empty">
    <div class="cart-empty__icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/>
      </svg>
    </div>
    <h2><?= __t('سلتك فارغة!','Your cart is empty!') ?></h2>
    <p><?= __t('لم تضف أي منتجات بعد. ابدأ التسوق الآن!','You haven\'t added any products yet. Start shopping now!') ?></p>
    <a href="shop.php" class="btn btn-primary btn-lg">
      <svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
      <?= __t('ابدأ التسوق','Start Shopping') ?>
    </a>
    <?php if (!isLoggedIn()): ?>
    <p class="empty-login">
      <?= __t('لديك حساب؟','Have an account?') ?>
      <a href="login.php"><?= __t('سجل دخولك','Login') ?></a>
      <?= __t('لاسترجاع سلتك','to restore your cart') ?>
    </p>
    <?php endif; ?>
  </div>
</div>

<?php endif; ?>

<!-- ── Suggested products ── -->
<?php $suggested = getProducts(['is_featured'=>true,'limit'=>5]); if ($suggested): ?>
<section style="background:var(--line-2); padding:56px 0; border-top:1px solid var(--line);">
  <div class="container">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
      <div>
        <div class="t-tag" style="margin-bottom:8px;">
          <svg viewBox="0 0 20 20" fill="currentColor" width="11" height="11"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
          <?= __t('اقتراحات','Suggestions') ?>
        </div>
        <h2 class="t-h2"><?= __t('قد يعجبك <span style="color:var(--red)">أيضاً</span>','You May Also <span style="color:var(--red)">Like</span>') ?></h2>
      </div>
      <a href="shop.php?filter=featured" class="btn btn-outline btn-sm">
        <?= __t('عرض الكل','View All') ?>
        <svg viewBox="0 0 20 20" fill="currentColor" width="13" height="13"><path fill-rule="evenodd" d="M<?= isAr()?'12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z':'7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z'?>" clip-rule="evenodd"/></svg>
      </a>
    </div>
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:18px;">
      <?php foreach ($suggested as $p) echo pCard($p); ?>
    </div>
  </div>
</section>
<?php endif; ?>

<script>
const CSRF = '<?= generateCsrfToken() ?>';

function removeItem(id){
  if(!confirm('<?= __t("هل تريد حذف هذا المنتج؟","Remove this item?") ?>')) return;
  fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`ajax=1&action=remove&cart_id=${id}&csrf_token=${CSRF}`})
  .then(r=>r.json()).then(d=>{
    if(d.success){
      document.getElementById('cartItem_'+id)?.remove();
      updateSummary(d);
      if(d.empty) location.reload();
    } else showToast(d.message,'e');
  });
}

function updateQty(id, delta){
  const inp=document.getElementById('qty_'+id); if(!inp) return;
  inp.value=Math.max(1,Math.min(+inp.max||999,+inp.value+delta));
  sendQtyUpdate(id,+inp.value);
}
function updateQtyDirect(id){
  const inp=document.getElementById('qty_'+id); if(!inp) return;
  sendQtyUpdate(id,Math.max(1,+inp.value||1));
}
function sendQtyUpdate(id,qty){
  fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`ajax=1&action=update&cart_id=${id}&qty=${qty}&csrf_token=${CSRF}`})
  .then(r=>r.json()).then(d=>{
    if(d.success){
      document.getElementById('lineTotal_'+id).textContent=d.line_total;
      updateSummary(d);
    }
  });
}

function updateSummary(d){
  if(d.subtotal) document.getElementById('sumSubtotal').textContent=d.subtotal;
  if(d.shipping) document.getElementById('sumShipping').innerHTML=d.shipping;
  if(d.total)    document.getElementById('sumTotal').textContent=d.total;
  if(d.count!==undefined){
    document.querySelectorAll('.hdr-badge').forEach(b=>b.textContent=d.count);
    const pc=document.getElementById('pageCartCount');
    if(pc) pc.textContent=d.count+' <?= __t("منتج","items") ?>';
  }
}

function clearAllCart(){
  if(!confirm('<?= __t("هل تريد تفريغ السلة بالكامل؟","Clear the entire cart?") ?>')) return;
  const ids=[...document.querySelectorAll('.cart-item')].map(el=>el.dataset.id);
  Promise.all(ids.map(id=>fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`ajax=1&action=remove&cart_id=${id}&csrf_token=${CSRF}`}).then(r=>r.json())))
  .then(()=>location.reload());
}

function applyCoupon(){
  const code=document.getElementById('couponInput')?.value?.trim();
  if(!code){showToast('<?= __t("أدخل كود الخصم","Enter coupon code") ?>','e');return;}
  fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`ajax=1&action=coupon&code=${encodeURIComponent(code)}&csrf_token=${CSRF}`})
  .then(r=>r.json()).then(d=>{
    const msg=document.getElementById('couponMsg');
    if(d.success){
      if(msg){msg.className='coupon-msg ok';msg.textContent='✓ '+d.message;}
      document.getElementById('sumTotal').textContent=d.total_formatted;
      if(!document.getElementById('discountRow')){
        const rows=document.querySelector('.sum-rows');
        const total=document.querySelector('.sum-total');
        const dr=document.createElement('div');
        dr.className='sum-row sum-discount'; dr.id='discountRow';
        dr.innerHTML='<span class="sum-row__label"><svg viewBox="0 0 20 20" fill="currentColor" width="13" height="13"><path fill-rule="evenodd" d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7A.997.997 0 012 10V5a3 3 0 013-3h5c.256 0 .512.098.707.293l7 7zM5 6a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg><?= __t("خصم الكوبون","Coupon") ?></span><span id="sumDiscount" style="color:#059669;font-weight:700;">− '+d.discount_formatted+'</span>';
        rows.insertBefore(dr,total);
      } else {
        const sd=document.getElementById('sumDiscount');
        if(sd) sd.textContent='− '+d.discount_formatted;
      }
      showToast(d.message,'s');
    } else {
      if(msg){msg.className='coupon-msg err';msg.textContent='✕ '+d.message;}
    }
  });
}

function removeCoupon(){
  fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`ajax=1&action=remove_coupon&csrf_token=${CSRF}`})
  .then(r=>r.json()).then(d=>{if(d.success) location.reload();});
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>