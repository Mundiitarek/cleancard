<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$items    = getCartItems();
$subtotal = getCartTotal();

if (!$items) { header('Location: cart.php'); exit; }

$coupon   = $_SESSION['coupon'] ?? null;
$discount = $coupon ? (float)$coupon['discount'] : 0;
$shippingZones = getShippingZones();

// احسب رسوم الشحن: إذا اختار العميل منطقة، استخدم سعرها، وإلا استخدم الافتراضي
$selectedZoneId = (int)($_POST['zone_id'] ?? $_SESSION['selected_zone_id'] ?? 0);
$selectedZone   = $selectedZoneId ? getShippingZone($selectedZoneId) : null;
if ($selectedZone) {
    $shipping = (float)$selectedZone['shipping_fee'];
    $_SESSION['selected_zone_id'] = $selectedZoneId;
} else {
    $shipping = shippingFee();
}
$total    = max(0, $subtotal + $shipping - $discount);
$user     = currentUser();
$settings = getAllSettings();

// ─── Handle submission ─────────────────────────────────
$errors = [];
$success = false;
$orderId = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = __t('رمز غير صالح، حاول مرة أخرى', 'Invalid token, please try again');
    } else {
        $name    = sanitize($_POST['name']    ?? '');
        $email   = sanitize($_POST['email']   ?? '');
        $phone   = sanitize($_POST['phone']   ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $zone_id = (int)($_POST['zone_id']    ?? 0);
        $notes   = sanitize($_POST['notes']   ?? '');

        // جلب بيانات المنطقة المختارة
        $zone = $zone_id ? getShippingZone($zone_id) : null;
        $zoneName = $zone ? t($zone, 'name') : '';
        $zoneShippingFee = $zone ? (float)$zone['shipping_fee'] : shippingFee();

        if (mb_strlen($name) < 2)       $errors[] = __t('الاسم مطلوب (2 حروف على الأقل)', 'Name required (min 2 chars)');
        if (!validatePhone($phone))     $errors[] = __t('رقم الهاتف غير صالح', 'Invalid phone number');
        if (mb_strlen($address) < 5)    $errors[] = __t('العنوان مطلوب', 'Address required');
        if (!$zone_id || !$zone)        $errors[] = __t('الرجاء اختيار منطقة التوصيل', 'Please select a delivery zone');
        if ($email && !validateEmail($email)) $errors[] = __t('البريد الإلكتروني غير صالح', 'Invalid email');

        if (!$errors) {
            $orderId = createOrder([
                'name'         => $name,
                'email'        => $email,
                'phone'        => $phone,
                'address'      => $address,
                'city'         => $zoneName,
                'notes'        => $notes,
                'discount'     => $discount,
                'coupon_code'  => $coupon['code'] ?? '',
                'shipping_fee' => $zoneShippingFee,
            ], $items);

            if ($orderId) {
                unset($_SESSION['coupon'], $_SESSION['selected_zone_id']);
                $success = true;
                $order = dbFetchOne("SELECT * FROM orders WHERE id=?", 'i', $orderId);
                logActivity('place_order', "Order #{$order['order_number']}", $user['id'] ?? 0);
            } else {
                $errors[] = __t('حدث خطأ أثناء إنشاء الطلب، حاول مرة أخرى', 'Error creating order, please try again');
            }
        }
    }
}

$pageTitle = __t('إتمام الشراء', 'Checkout');
if (!$success) require_once __DIR__ . '/header.php';
?>

<?php if ($success && isset($order)): ?>
<!DOCTYPE html>
<html lang="<?= lang() ?>" dir="<?= dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= __t('تم الطلب بنجاح','Order Confirmed') ?> | <?= e(siteName()) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;900&family=Outfit:wght@400;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:<?= isAr()?'"Tajawal"':'Outfit' ?>,sans-serif;background:#f8f9fa;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
.success-card{background:white;border-radius:20px;box-shadow:0 8px 40px rgba(0,0,0,.12);max-width:560px;width:100%;overflow:hidden}
.success-top{background:linear-gradient(135deg,#e63946,#c1121f);padding:40px;text-align:center;color:white}
.check-circle{width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,.2);border:3px solid rgba(255,255,255,.5);display:flex;align-items:center;justify-content:center;font-size:2.5rem;margin:0 auto 20px;animation:pop .5s ease}
@keyframes pop{0%{transform:scale(0)}70%{transform:scale(1.1)}100%{transform:scale(1)}}
.success-top h1{font-size:1.6rem;font-weight:900;margin-bottom:8px}
.success-top p{opacity:.85;font-size:.95rem}
.success-body{padding:32px}
.order-num{text-align:center;margin-bottom:24px}
.on-label{font-size:.82rem;color:#6c757d;margin-bottom:6px}
.on-val{font-size:1.4rem;font-weight:900;color:#e63946;letter-spacing:.05em}
.order-details{background:#f8f9fa;border-radius:12px;padding:20px;margin-bottom:24px}
.od-row{display:flex;justify-content:space-between;padding:8px 0;font-size:.88rem;border-bottom:1px dashed #dee2e6}
.od-row:last-child{border-bottom:none;font-weight:800;font-size:.95rem}
.od-row span:last-child{color:#e63946}
.info-box{background:#fff8e7;border:1px solid #fde68a;border-radius:12px;padding:16px;margin-bottom:24px;font-size:.84rem;color:#92400e;line-height:1.8;text-align:center}
.actions{display:flex;gap:12px;flex-direction:column}
.btn{display:flex;align-items:center;justify-content:center;gap:8px;padding:13px 24px;border-radius:30px;font-size:.9rem;font-weight:700;text-decoration:none;transition:all .2s;font-family:inherit;cursor:pointer;border:none}
.btn-red{background:#e63946;color:white}
.btn-red:hover{background:#c1121f}
.btn-outline{background:transparent;border:2px solid #dee2e6;color:#495057}
.btn-outline:hover{border-color:#e63946;color:#e63946}
</style>
</head>
<body>
<div class="success-card">
  <div class="success-top">
    <div class="check-circle">✓</div>
    <h1><?= __t('تم الطلب بنجاح!', 'Order Placed Successfully!') ?></h1>
    <p><?= __t('سنتواصل معك قريباً لتأكيد طلبك', 'We will contact you soon to confirm your order') ?></p>
  </div>
  <div class="success-body">
    <div class="order-num">
      <div class="on-label"><?= __t('رقم الطلب', 'Order Number') ?></div>
      <div class="on-val"><?= e($order['order_number']) ?></div>
    </div>
    <div class="order-details">
      <div class="od-row"><span><?= __t('الاسم', 'Name') ?></span><span><?= e($order['name']) ?></span></div>
      <div class="od-row"><span><?= __t('الهاتف', 'Phone') ?></span><span><?= e($order['phone']) ?></span></div>
      <div class="od-row"><span><?= __t('منطقة التوصيل', 'Delivery Zone') ?></span><span><?= e($order['city']) ?></span></div>
      <div class="od-row"><span><?= __t('طريقة الدفع', 'Payment') ?></span><span>💵 <?= __t('الدفع عند الاستلام', 'Cash on Delivery') ?></span></div>
      <?php if ($order['discount'] > 0): ?>
      <div class="od-row"><span><?= __t('الخصم', 'Discount') ?></span><span>− <?= formatPrice((float)$order['discount']) ?></span></div>
      <?php endif; ?>
      <div class="od-row"><span><?= __t('الشحن', 'Shipping') ?></span><span><?= $order['shipping_fee']>0 ? formatPrice((float)$order['shipping_fee']) : __t('مجاني','Free') ?></span></div>
      <div class="od-row"><span><?= __t('الإجمالي', 'Total') ?></span><span><?= formatPrice((float)$order['total']) ?></span></div>
    </div>
    <div class="info-box">
      📞 <?= __t('سيقوم فريقنا بالتواصل معك على رقم', 'Our team will contact you at') ?>
      <strong><?= e($order['phone']) ?></strong>
      <?= __t('خلال 24 ساعة لتأكيد الطلب', 'within 24 hours to confirm the order') ?>
    </div>
    <div class="actions">
      <?php if ($user): ?>
        <a href="dashboard.php?tab=orders" class="btn btn-red">📦 <?= __t('تتبع طلباتي', 'Track My Orders') ?></a>
      <?php endif; ?>
      <a href="index.php" class="btn btn-outline">🏠 <?= __t('العودة للرئيسية', 'Back to Home') ?></a>
      <a href="shop.php" class="btn btn-outline">🛍 <?= __t('مواصلة التسوق', 'Continue Shopping') ?></a>
    </div>
  </div>
</div>
</body>
</html>
<?php exit; endif; ?>

<!-- ═══ PAGE HEADER ═══ -->
<div class="page-banner">
  <div class="container">
    <div class="breadcrumb">
      <a href="index.php"><?= __t('الرئيسية','Home') ?></a>
      <span class="sep">›</span>
      <a href="cart.php"><?= __t('السلة','Cart') ?></a>
      <span class="sep">›</span>
      <span class="current"><?= __t('إتمام الشراء','Checkout') ?></span>
    </div>
    <h1>⚡ <?= __t('إتمام الشراء','Checkout') ?></h1>
  </div>
</div>

<!-- ═══ CHECKOUT STEPS ═══ -->
<div class="checkout-steps-bar">
  <div class="container">
    <div class="steps">
      <div class="step done"><span class="step-num">✓</span><span><?= __t('السلة','Cart') ?></span></div>
      <div class="step-line done"></div>
      <div class="step active"><span class="step-num">2</span><span><?= __t('التفاصيل','Details') ?></span></div>
      <div class="step-line"></div>
      <div class="step"><span class="step-num">3</span><span><?= __t('التأكيد','Confirm') ?></span></div>
    </div>
  </div>
</div>

<?php if ($errors): ?>
<div class="container" style="padding-top:20px">
  <?php foreach ($errors as $err): ?>
    <div class="flash-msg flash-error">✕ <?= e($err) ?> <button class="flash-close" onclick="this.parentElement.remove()">×</button></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ═══ CHECKOUT LAYOUT ═══ -->
<section class="section-sm">
  <div class="container">
    <form method="POST" id="checkoutForm">
      <?= csrfInput() ?>
      <div class="checkout-layout">

        <!-- ── Shipping Form ── -->
        <div class="checkout-main">

          <!-- Customer Info -->
          <div class="checkout-box">
            <h3 class="cbox-title">👤 <?= __t('بيانات المستلم','Recipient Information') ?></h3>
            <div class="grid grid-2">
              <div class="form-group">
                <label class="form-label required"><?= __t('الاسم الكامل','Full Name') ?></label>
                <input type="text" name="name" class="form-control"
                       value="<?= e($_POST['name'] ?? $user['name'] ?? '') ?>"
                       placeholder="<?= __t('محمد أحمد','John Smith') ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label required"><?= __t('رقم الهاتف','Phone Number') ?></label>
                <input type="tel" name="phone" class="form-control"
                       value="<?= e($_POST['phone'] ?? $user['phone'] ?? '') ?>"
                       placeholder="01xxxxxxxxx" required>
              </div>
              <div class="form-group">
                <label class="form-label"><?= __t('البريد الإلكتروني','Email') ?></label>
                <input type="email" name="email" class="form-control"
                       value="<?= e($_POST['email'] ?? $user['email'] ?? '') ?>"
                       placeholder="example@email.com">
              </div>
              <div class="form-group">
                <label class="form-label required"><?= __t('منطقة التوصيل','Delivery Zone') ?></label>
                <select name="zone_id" id="zoneSelect" class="form-control" required onchange="updateShipping(this)">
                  <option value=""><?= __t('اختر منطقتك','Select Your Zone') ?></option>
                  <?php foreach ($shippingZones as $z): ?>
                    <option value="<?= $z['id'] ?>"
                            data-fee="<?= (float)$z['shipping_fee'] ?>"
                            <?= $selectedZoneId == $z['id'] ? 'selected' : '' ?>>
                      <?= e(t($z, 'name')) ?> — <?= $z['shipping_fee'] > 0 ? number_format((float)$z['shipping_fee'], 0).' ₪' : __t('مجاني','Free') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <?php if(empty($shippingZones)): ?>
                  <small style="color:#dc3545"><?= __t('لا توجد مناطق توصيل. يرجى مراسلتنا.','No delivery zones available. Please contact us.') ?></small>
                <?php endif; ?>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label required"><?= __t('العنوان التفصيلي','Detailed Address') ?></label>
              <textarea name="address" class="form-control" rows="3" required
                        placeholder="<?= __t('الشارع، رقم المبنى، الحي...','Street, building number, neighborhood...') ?>"><?= e($_POST['address'] ?? $user['address'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
              <label class="form-label"><?= __t('ملاحظات للمندوب (اختياري)','Notes for courier (optional)') ?></label>
              <textarea name="notes" class="form-control" rows="2"
                        placeholder="<?= __t('مثال: اتصل قبل التسليم بساعة','Example: Call 1 hour before delivery') ?>"><?= e($_POST['notes'] ?? '') ?></textarea>
            </div>
          </div>

          <!-- Payment Method -->
          <div class="checkout-box">
            <h3 class="cbox-title">💳 <?= __t('طريقة الدفع','Payment Method') ?></h3>
            <div class="payment-options">
              <label class="payment-option active">
                <input type="radio" name="payment_method" value="cod" checked>
                <div class="po-content">
                  <div class="po-icon">💵</div>
                  <div>
                    <strong><?= __t('الدفع عند الاستلام','Cash on Delivery') ?></strong>
                    <span><?= __t('ادفع نقداً عند استلام طلبك','Pay cash when you receive your order') ?></span>
                  </div>
                  <div class="po-check">✓</div>
                </div>
              </label>
            </div>
            <div class="cod-notice">
              ℹ <?= __t('الدفع عند الاستلام هو طريقة الدفع الوحيدة المتاحة حالياً','Cash on delivery is the only available payment method currently') ?>
            </div>
          </div>

          <!-- Submit -->
          <button type="submit" class="btn btn-primary btn-lg btn-block checkout-submit-btn" id="submitBtn">
            ✓ <?= __t('تأكيد الطلب','Confirm Order') ?>
            <span class="submit-total" id="submitTotal"><?= number_format($total,0) ?> ₪</span>
          </button>
          <p class="checkout-note">
            🔒 <?= __t('بياناتك محمية وآمنة تماماً','Your data is completely safe and secure') ?>
          </p>

        </div><!-- .checkout-main -->

        <!-- ── Order Summary ── -->
        <aside class="checkout-summary">
          <div class="order-summary">
            <h3 class="summary-title">📋 <?= __t('ملخص الطلب','Order Summary') ?></h3>

            <!-- Items list -->
            <div class="co-items">
              <?php foreach ($items as $item):
                $p = $item['sale_price'] ?: $item['price'];
              ?>
              <div class="co-item">
                <div class="co-item-img-wrap">
                  <img src="<?= e(productImage($item)) ?>" alt="<?= e(t($item,'name')) ?>"
                       onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjYwIiBoZWlnaHQ9IjYwIiBmaWxsPSIjZjBmMGYwIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtc2l6ZT0iMjAiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj7wn5qrwrc8L3RleHQ+PC9zdmc+'">
                  <span class="co-qty-badge"><?= (int)$item['quantity'] ?></span>
                </div>
                <div class="co-item-info">
                  <span class="co-item-name"><?= e(t($item,'name')) ?></span>
                  <span class="co-item-price"><?= formatPrice($p * $item['quantity']) ?></span>
                </div>
              </div>
              <?php endforeach; ?>
            </div>

            <!-- Coupon field -->
            <div class="co-coupon">
              <?php if ($coupon): ?>
                <div class="coupon-applied-sm">
                  ✓ <strong><?= e($coupon['code']) ?></strong> — − <?= formatPrice($discount) ?>
                </div>
              <?php else: ?>
                <div style="display:flex;gap:0">
                  <input type="text" id="coCoupon" class="coupon-input"
                         placeholder="<?= __t('كود الخصم','Coupon code') ?>">
                  <button type="button" class="coupon-btn" onclick="applyCheckoutCoupon()">
                    <?= __t('تطبيق','Apply') ?>
                  </button>
                </div>
                <div id="coCouponMsg" class="coupon-msg"></div>
              <?php endif; ?>
            </div>

            <!-- Totals -->
            <div class="summary-rows">
              <div class="sum-row">
                <span><?= __t('المجموع الفرعي','Subtotal') ?></span>
                <span><?= formatPrice($subtotal) ?></span>
              </div>
              <div class="sum-row">
                <span><?= __t('الشحن','Shipping') ?></span>
                <span id="shippingDisplay"><?= $shipping==0 ? '<span style="color:#198754;font-weight:700">'.__t('مجاني','Free').'</span>' : number_format($shipping,0).' ₪' ?></span>
              </div>
              <?php if ($discount > 0): ?>
              <div class="sum-row">
                <span>🏷 <?= __t('الخصم','Discount') ?></span>
                <span style="color:#198754;font-weight:700">− <?= formatPrice($discount) ?></span>
              </div>
              <?php endif; ?>
              <div class="sum-row sum-total">
                <span><?= __t('الإجمالي','Total') ?></span>
                <span id="coTotal"><?= number_format($total,0) ?> ₪</span>
              </div>
            </div>

            <div class="co-guarantee">
              🔒 <?= __t('شراء آمن مضمون','Safe & Guaranteed Purchase') ?>
            </div>
          </div>
        </aside>

      </div><!-- .checkout-layout -->
    </form>
  </div>
</section>

<style>
.page-banner { background:linear-gradient(135deg,var(--dark),var(--dark2));padding:28px 0;color:white;margin-bottom:0 }
.page-banner h1 { font-size:1.5rem;font-weight:900;margin-top:10px }

.checkout-steps-bar { background:white;border-bottom:1px solid var(--border);padding:16px 0 }
.steps { display:flex;align-items:center;gap:0;max-width:400px;margin:0 auto }
.step { display:flex;align-items:center;gap:8px;font-size:.84rem;font-weight:600;color:var(--gray) }
.step.active { color:var(--primary) }
.step.done   { color:#198754 }
.step-num {
  width:30px;height:30px;border-radius:50%;
  background:var(--border);color:var(--gray);
  display:flex;align-items:center;justify-content:center;
  font-size:.8rem;font-weight:700;flex-shrink:0;
}
.step.active .step-num { background:var(--primary);color:white }
.step.done   .step-num { background:#198754;color:white }
.step-line { flex:1;height:2px;background:var(--border);margin:0 8px }
.step-line.done { background:#198754 }

.checkout-layout { display:grid;grid-template-columns:1fr 360px;gap:28px;align-items:start }

.checkout-box {
  background:white;border-radius:var(--radius-md);
  border:1px solid var(--border);padding:24px;margin-bottom:20px
}
.cbox-title { font-size:1rem;font-weight:800;color:var(--dark);margin-bottom:20px;padding-bottom:12px;border-bottom:2px solid var(--primary) }

.payment-options { display:flex;flex-direction:column;gap:10px;margin-bottom:14px }
.payment-option { cursor:pointer;display:block }
.payment-option input { display:none }
.po-content {
  display:flex;align-items:center;gap:14px;
  padding:16px;border:2px solid var(--border);border-radius:var(--radius-md);
  transition:var(--transition);background:var(--gray-light)
}
.payment-option.active .po-content,
.payment-option input:checked ~ .po-content {
  border-color:var(--primary);background:var(--primary-bg)
}
.po-icon { font-size:1.8rem;flex-shrink:0 }
.po-content > div { flex:1 }
.po-content strong { display:block;font-size:.9rem;font-weight:700 }
.po-content span   { font-size:.78rem;color:var(--gray) }
.po-check { color:var(--primary);font-size:1.1rem;font-weight:800;opacity:0 }
.payment-option input:checked ~ .po-content .po-check { opacity:1 }

.cod-notice {
  background:#fff8e7;border:1px solid #fde68a;
  padding:10px 16px;border-radius:var(--radius-sm);
  font-size:.8rem;color:#92400e
}

.checkout-submit-btn {
  width:100%;font-size:1rem;padding:16px;
  display:flex;justify-content:space-between;align-items:center;
  margin-bottom:12px
}
.submit-total { font-size:1.1rem;font-weight:900 }
.checkout-note { text-align:center;font-size:.78rem;color:var(--gray) }

/* Summary */
.order-summary { background:white;border:1px solid var(--border);border-radius:var(--radius-md);padding:24px;position:sticky;top:calc(var(--topbar-h) + var(--header-h) + 16px) }
.summary-title { font-size:1rem;font-weight:800;color:var(--dark);margin-bottom:16px;padding-bottom:12px;border-bottom:2px solid var(--primary) }

.co-items { display:flex;flex-direction:column;gap:12px;margin-bottom:16px;max-height:280px;overflow-y:auto }
.co-item  { display:flex;align-items:center;gap:12px }
.co-item-img-wrap { position:relative;flex-shrink:0 }
.co-item-img-wrap img { width:52px;height:52px;object-fit:cover;border-radius:var(--radius-sm);border:1px solid var(--border) }
.co-qty-badge {
  position:absolute;top:-6px;<?= isAr()?'left':'right' ?>:-6px;
  background:var(--primary);color:white;
  width:20px;height:20px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  font-size:.65rem;font-weight:700
}
.co-item-info { flex:1;min-width:0 }
.co-item-name  { display:block;font-size:.82rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis }
.co-item-price { font-size:.82rem;color:var(--primary);font-weight:700 }

.co-coupon { margin-bottom:16px;padding:14px;background:var(--gray-light);border-radius:var(--radius-sm) }
.coupon-applied-sm { background:#d1fae5;color:#065f46;padding:8px 14px;border-radius:var(--radius-xl);font-size:.82rem;font-weight:600 }

.sum-row { display:flex;justify-content:space-between;padding:10px 0;font-size:.88rem;border-bottom:1px dashed var(--border) }
.sum-row:last-child { border-bottom:none }
.sum-total { font-weight:800;font-size:1rem;border-top:2px solid var(--border) !important;padding-top:14px !important;border-bottom:none !important }
.sum-total span:last-child { color:var(--primary);font-size:1.1rem }

.co-guarantee { text-align:center;font-size:.78rem;color:var(--gray);margin-top:14px;padding-top:14px;border-top:1px solid var(--border) }

@media(max-width:900px){
  .checkout-layout { grid-template-columns:1fr }
  .order-summary { position:static }
  .checkout-summary { order:-1 }
}
@media(max-width:600px){
  .grid-2 { grid-template-columns:1fr }
}
</style>

<script>
const _subtotal = <?= $subtotal ?>;
const _discount = <?= $discount ?>;

function updateShipping(sel) {
  const opt = sel.options[sel.selectedIndex];
  const fee = parseFloat(opt.dataset.fee || 0);
  const newTotal = Math.max(0, _subtotal + fee - _discount);
  const freeLabel = '<?= __t("مجاني","Free") ?>';

  document.getElementById('shippingDisplay').innerHTML =
    fee === 0 ? '<span style="color:#198754;font-weight:700">' + freeLabel + '</span>'
              : fee.toFixed(0) + ' ₪';
  document.getElementById('coTotal').textContent = newTotal.toFixed(0) + ' ₪';
  const st = document.getElementById('submitTotal');
  if (st) st.textContent = newTotal.toFixed(0) + ' ₪';
}

// تحديث فوري عند تحميل الصفحة إذا كانت منطقة مختارة
window.addEventListener('DOMContentLoaded', function() {
  const sel = document.getElementById('zoneSelect');
  if (sel && sel.value) updateShipping(sel);
});

document.getElementById('submitBtn')?.addEventListener('click',function(){
  this.innerHTML='⏳ <?= __t("جاري المعالجة...","Processing...") ?>';
  this.disabled=true;
  document.getElementById('checkoutForm').submit();
});

function applyCheckoutCoupon(){
  const code = document.getElementById('coCoupon')?.value?.trim();
  if(!code) return;
  fetch('cart.php',{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`ajax=1&action=coupon&code=${encodeURIComponent(code)}&csrf_token=<?= generateCsrfToken() ?>`
  })
  .then(r=>r.json())
  .then(d=>{
    const msg = document.getElementById('coCouponMsg');
    if(d.success){
      msg.className='coupon-msg success';
      msg.textContent='✓ '+d.message;
      document.getElementById('coTotal').textContent=d.total_formatted;
    } else {
      msg.className='coupon-msg error';
      msg.textContent='✕ '+d.message;
    }
  });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
