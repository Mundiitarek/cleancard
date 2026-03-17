<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
requireLogin();

$user   = currentUser();
$tab    = $_GET['tab'] ?? 'overview';
$errors = [];
$successMsg = '';

// ─── Handle POST actions ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = __t('رمز غير صالح','Invalid token');
    } else {
        $action = $_POST['action'] ?? '';

        // Update profile
        if ($action === 'update_profile') {
            $name    = sanitize($_POST['name']    ?? '');
            $phone   = sanitize($_POST['phone']   ?? '');
            $address = sanitize($_POST['address'] ?? '');
            $city    = sanitize($_POST['city']    ?? '');
            $lang_p  = in_array($_POST['lang'] ?? '', ['ar','en']) ? $_POST['lang'] : 'ar';

            if (mb_strlen($name) < 2) $errors[] = __t('الاسم مطلوب','Name required');
            if ($phone && !validatePhone($phone)) $errors[] = __t('هاتف غير صالح','Invalid phone');

            if (!$errors) {
                dbUpdate('users',
                    ['name'=>$name,'phone'=>$phone,'address'=>$address,'city'=>$city,'lang'=>$lang_p],
                    'id=?','i',$user['id']
                );
                $_SESSION['lang'] = $lang_p;
                $user = currentUser();
                $successMsg = __t('تم تحديث البيانات بنجاح ✓','Profile updated successfully ✓');
            }
            $tab = 'profile';
        }

        // Change password
        if ($action === 'change_password') {
            $current = $_POST['current_password'] ?? '';
            $new     = $_POST['new_password']     ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if (!password_verify($current, $user['password'])) $errors[] = __t('كلمة المرور الحالية غير صحيحة','Current password is incorrect');
            if (mb_strlen($new) < 6)  $errors[] = __t('كلمة المرور الجديدة 6 أحرف على الأقل','New password min 6 chars');
            if ($new !== $confirm)    $errors[] = __t('كلمتا المرور غير متطابقتان','Passwords do not match');

            if (!$errors) {
                dbUpdate('users',['password'=>password_hash($new,PASSWORD_BCRYPT)],'id=?','i',$user['id']);
                $successMsg = __t('تم تغيير كلمة المرور بنجاح ✓','Password changed successfully ✓');
                logActivity('change_password','User changed password');
            }
            $tab = 'profile';
        }
    }
}

// ─── Data per tab ──────────────────────────────────────
$orders   = [];
$wishlist = [];

if ($tab === 'orders' || $tab === 'overview') {
    $orders = dbFetchAll(
        "SELECT o.*, COUNT(oi.id) as item_count
         FROM orders o LEFT JOIN order_items oi ON o.id=oi.order_id
         WHERE o.user_id=? GROUP BY o.id ORDER BY o.created_at DESC",
        'i', $user['id']
    );
}

if ($tab === 'wishlist' || $tab === 'overview') {
    $wishlist = dbFetchAll(
        "SELECT p.*, c.name_ar as cat_ar, c.name_en as cat_en, c.slug as cat_slug
         FROM wishlist w JOIN products p ON w.product_id=p.id
         LEFT JOIN categories c ON p.category_id=c.id
         WHERE w.user_id=? AND p.is_active=1 ORDER BY w.added_at DESC",
        'i', $user['id']
    );
}

// Single order detail
$orderDetail = null;
if ($tab === 'order-detail' && isset($_GET['id'])) {
    $orderDetail = dbFetchOne("SELECT * FROM orders WHERE id=? AND user_id=?",'ii',(int)$_GET['id'],$user['id']);
    if ($orderDetail) {
        $orderDetail['items'] = dbFetchAll("SELECT * FROM order_items WHERE order_id=?",'i',$orderDetail['id']);
    }
}

// Stats
$totalOrders    = (int)(dbFetchOne("SELECT COUNT(*) c FROM orders WHERE user_id=?",'i',$user['id'])['c']??0);
$totalSpent     = (float)(dbFetchOne("SELECT SUM(total) s FROM orders WHERE user_id=? AND status!='cancelled'",'i',$user['id'])['s']??0);
$wishlistCount  = (int)(dbFetchOne("SELECT COUNT(*) c FROM wishlist WHERE user_id=?",'i',$user['id'])['c']??0);
$pendingOrders  = (int)(dbFetchOne("SELECT COUNT(*) c FROM orders WHERE user_id=? AND status='pending'",'i',$user['id'])['c']??0);

$pageTitle = __t('حسابي','My Account');
require_once __DIR__ . '/header.php';
?>

<div class="page-banner">
  <div class="container">
    <div class="breadcrumb">
      <a href="index.php"><?= __t('الرئيسية','Home') ?></a>
      <span class="sep">›</span>
      <span class="current"><?= __t('حسابي','My Account') ?></span>
    </div>
    <h1>👤 <?= __t('مرحباً,','Hello,') ?> <?= e(explode(' ',$user['name'])[0]) ?>!</h1>
  </div>
</div>

<section class="section-sm">
  <div class="container">

    <?php if ($successMsg): ?>
      <div class="flash-msg flash-success" style="margin-bottom:20px">✓ <?= e($successMsg) ?><button class="flash-close" onclick="this.parentElement.remove()">×</button></div>
    <?php endif; ?>
    <?php foreach ($errors as $e_): ?>
      <div class="flash-msg flash-error" style="margin-bottom:10px">✕ <?= e($e_) ?><button class="flash-close" onclick="this.parentElement.remove()">×</button></div>
    <?php endforeach; ?>

    <div class="dash-layout">

      <!-- ── Sidebar ── -->
      <aside class="dash-sidebar">
        <!-- User card -->
        <div class="dash-user-card">
          <div class="duc-avatar"><?= mb_substr($user['name'],0,1) ?></div>
          <div class="duc-info">
            <strong><?= e($user['name']) ?></strong>
            <span><?= e($user['email']) ?></span>
            <span class="duc-join"><?= __t('عضو منذ','Member since') ?> <?= date('M Y',strtotime($user['created_at'])) ?></span>
          </div>
        </div>

        <!-- Nav -->
        <nav class="dash-nav">
          <?php
          $navItems = [
            ['overview',     '🏠', __t('الرئيسية','Overview')],
            ['orders',       '📦', __t('طلباتي','My Orders')],
            ['wishlist',     '♡',  __t('المفضلة','Wishlist')],
            ['profile',      '✏',  __t('تعديل الملف','Edit Profile')],
            ['password',     '🔒', __t('تغيير كلمة المرور','Change Password')],
          ];
          foreach ($navItems as [$key,$icon,$label]):
          ?>
            <a href="dashboard.php?tab=<?= $key ?>"
               class="dash-nav-link <?= $tab===$key?'active':'' ?>">
              <span class="dn-icon"><?= $icon ?></span>
              <span><?= $label ?></span>
              <?php if ($key==='orders' && $pendingOrders>0): ?>
                <span class="dn-badge"><?= $pendingOrders ?></span>
              <?php endif; ?>
              <?php if ($key==='wishlist' && $wishlistCount>0): ?>
                <span class="dn-badge"><?= $wishlistCount ?></span>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
          <a href="logout.php" class="dash-nav-link logout-link">
            <span class="dn-icon">↩</span>
            <span><?= __t('تسجيل الخروج','Logout') ?></span>
          </a>
        </nav>
      </aside>

      <!-- ── Main Content ── -->
      <main class="dash-main">

        <?php if ($tab === 'overview'): ?>
        <!-- ══ Overview ══ -->
        <div class="dash-section-title"><?= __t('نظرة عامة','Overview') ?></div>

        <!-- Stats -->
        <div class="dash-stats">
          <div class="ds-card">
            <div class="ds-icon">📦</div>
            <div class="ds-val"><?= $totalOrders ?></div>
            <div class="ds-label"><?= __t('إجمالي الطلبات','Total Orders') ?></div>
          </div>
          <div class="ds-card">
            <div class="ds-icon">💰</div>
            <div class="ds-val"><?= formatPrice($totalSpent) ?></div>
            <div class="ds-label"><?= __t('إجمالي الإنفاق','Total Spent') ?></div>
          </div>
          <div class="ds-card">
            <div class="ds-icon">⏳</div>
            <div class="ds-val"><?= $pendingOrders ?></div>
            <div class="ds-label"><?= __t('طلبات معلقة','Pending Orders') ?></div>
          </div>
          <div class="ds-card">
            <div class="ds-icon">♡</div>
            <div class="ds-val"><?= $wishlistCount ?></div>
            <div class="ds-label"><?= __t('في المفضلة','In Wishlist') ?></div>
          </div>
        </div>

        <!-- Recent orders -->
        <?php if ($orders): ?>
        <div class="dash-box" style="margin-top:24px">
          <div class="dbox-header">
            <h3><?= __t('آخر الطلبات','Recent Orders') ?></h3>
            <a href="dashboard.php?tab=orders" class="dbox-more"><?= __t('عرض الكل','View All') ?> →</a>
          </div>
          <div class="orders-table-wrap">
            <table class="orders-table">
              <thead>
                <tr>
                  <th><?= __t('رقم الطلب','Order #') ?></th>
                  <th><?= __t('التاريخ','Date') ?></th>
                  <th><?= __t('المنتجات','Items') ?></th>
                  <th><?= __t('الإجمالي','Total') ?></th>
                  <th><?= __t('الحالة','Status') ?></th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach (array_slice($orders,0,5) as $ord): ?>
                <tr>
                  <td><strong class="order-num-cell"><?= e($ord['order_number']) ?></strong></td>
                  <td><?= date('d M Y',strtotime($ord['created_at'])) ?></td>
                  <td><?= (int)$ord['item_count'] ?> <?= __t('منتج','items') ?></td>
                  <td><strong style="color:var(--primary)"><?= formatPrice((float)$ord['total']) ?></strong></td>
                  <td>
                    <span class="status-badge status-<?= e($ord['status']) ?>">
                      <?= orderStatusLabel($ord['status']) ?>
                    </span>
                  </td>
                  <td><a href="dashboard.php?tab=order-detail&id=<?= $ord['id'] ?>" class="btn btn-outline btn-sm"><?= __t('تفاصيل','Details') ?></a></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endif; ?>

        <!-- Wishlist preview -->
        <?php if ($wishlist): ?>
        <div class="dash-box" style="margin-top:24px">
          <div class="dbox-header">
            <h3>♡ <?= __t('المفضلة','Wishlist') ?></h3>
            <a href="dashboard.php?tab=wishlist" class="dbox-more"><?= __t('عرض الكل','View All') ?> →</a>
          </div>
          <div class="grid grid-4" style="gap:16px">
            <?php foreach (array_slice($wishlist,0,4) as $p): ?>
              <a href="product.php?slug=<?= urlencode($p['slug']) ?>" class="mini-product-card">
                <img src="<?= e(productImage($p)) ?>" alt="<?= e(t($p,'name')) ?>">
                <div class="mpc-name"><?= e(t($p,'name')) ?></div>
                <div class="mpc-price"><?= formatPrice(productPrice($p)) ?></div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php elseif ($tab === 'orders'): ?>
        <!-- ══ Orders ══ -->
        <div class="dash-section-title"><?= __t('طلباتي','My Orders') ?></div>
        <?php if ($orders): ?>
          <div class="orders-table-wrap dash-box">
            <table class="orders-table">
              <thead>
                <tr>
                  <th><?= __t('رقم الطلب','Order #') ?></th>
                  <th><?= __t('التاريخ','Date') ?></th>
                  <th><?= __t('المنتجات','Items') ?></th>
                  <th><?= __t('الإجمالي','Total') ?></th>
                  <th><?= __t('الدفع','Payment') ?></th>
                  <th><?= __t('الحالة','Status') ?></th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($orders as $ord): ?>
                <tr>
                  <td><strong class="order-num-cell"><?= e($ord['order_number']) ?></strong></td>
                  <td><?= date('d M Y',strtotime($ord['created_at'])) ?></td>
                  <td><?= (int)$ord['item_count'] ?> <?= __t('منتج','items') ?></td>
                  <td><strong style="color:var(--primary)"><?= formatPrice((float)$ord['total']) ?></strong></td>
                  <td><?= __t('الدفع عند الاستلام','COD') ?></td>
                  <td>
                    <span class="status-badge status-<?= e($ord['status']) ?>">
                      <?= orderStatusLabel($ord['status']) ?>
                    </span>
                  </td>
                  <td><a href="dashboard.php?tab=order-detail&id=<?= $ord['id'] ?>" class="btn btn-outline btn-sm"><?= __t('تفاصيل','Details') ?></a></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <div class="empty-icon">📦</div>
            <h3><?= __t('لا توجد طلبات بعد','No orders yet') ?></h3>
            <p><?= __t('ابدأ التسوق الآن وأول طلبك سيظهر هنا','Start shopping now and your first order will appear here') ?></p>
            <a href="shop.php" class="btn btn-primary"><?= __t('تسوق الآن','Shop Now') ?></a>
          </div>
        <?php endif; ?>

        <?php elseif ($tab === 'order-detail' && $orderDetail): ?>
        <!-- ══ Order Detail ══ -->
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px;flex-wrap:wrap">
          <a href="dashboard.php?tab=orders" style="color:var(--primary);font-size:.9rem">← <?= __t('الطلبات','Orders') ?></a>
          <div class="dash-section-title" style="margin:0"><?= __t('تفاصيل الطلب','Order Details') ?> #<?= e($orderDetail['order_number']) ?></div>
          <span class="status-badge status-<?= e($orderDetail['status']) ?>"><?= orderStatusLabel($orderDetail['status']) ?></span>
        </div>

        <div class="order-detail-grid">
          <div>
            <!-- Items -->
            <div class="dash-box" style="margin-bottom:20px">
              <div class="dbox-header"><h3><?= __t('المنتجات','Products') ?></h3></div>
              <?php foreach ($orderDetail['items'] as $item): ?>
              <div class="od-item">
                <img src="<?= e(!empty($item['image'])?UPLOADS_URL.'products/'.$item['image']:'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjYwIiBoZWlnaHQ9IjYwIiBmaWxsPSIjZjBmMGYwIi8+PC9zdmc+') ?>"
                     alt="<?= e(isAr()?$item['name_ar']:$item['name_en']) ?>" class="od-item-img">
                <div class="od-item-info">
                  <span class="od-item-name"><?= e(isAr()?$item['name_ar']:$item['name_en']) ?></span>
                  <span class="od-item-meta"><?= formatPrice((float)$item['unit_price']) ?> × <?= (int)$item['quantity'] ?></span>
                </div>
                <div class="od-item-total"><?= formatPrice((float)$item['total_price']) ?></div>
              </div>
              <?php endforeach; ?>
            </div>

            <!-- Shipping info -->
            <div class="dash-box">
              <div class="dbox-header"><h3>📍 <?= __t('عنوان التوصيل','Delivery Address') ?></h3></div>
              <div style="padding:16px;font-size:.9rem;line-height:1.8;color:var(--text-light)">
                <strong style="color:var(--dark)"><?= e($orderDetail['name']) ?></strong><br>
                <?= e($orderDetail['phone']) ?><br>
                <?= e($orderDetail['address']) ?>, <?= e($orderDetail['city']) ?><br>
                <?php if ($orderDetail['notes']): ?>
                  <em style="color:var(--gray)"><?= e($orderDetail['notes']) ?></em>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Summary -->
          <div class="dash-box order-summary-detail">
            <div class="dbox-header"><h3><?= __t('ملخص الطلب','Order Summary') ?></h3></div>
            <div style="padding:0 20px 20px">
              <div class="sum-row"><span><?= __t('المجموع','Subtotal') ?></span><span><?= formatPrice((float)$orderDetail['subtotal']) ?></span></div>
              <div class="sum-row"><span><?= __t('الشحن','Shipping') ?></span><span><?= $orderDetail['shipping_fee']>0 ? formatPrice((float)$orderDetail['shipping_fee']) : __t('مجاني','Free') ?></span></div>
              <?php if ($orderDetail['discount']>0): ?>
              <div class="sum-row"><span><?= __t('الخصم','Discount') ?></span><span style="color:#198754">−<?= formatPrice((float)$orderDetail['discount']) ?></span></div>
              <?php endif; ?>
              <div class="sum-row sum-total"><span><?= __t('الإجمالي','Total') ?></span><span><?= formatPrice((float)$orderDetail['total']) ?></span></div>
              <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);font-size:.84rem;color:var(--gray)">
                <div><?= __t('تاريخ الطلب:','Order date:') ?> <?= date('d M Y H:i',strtotime($orderDetail['created_at'])) ?></div>
                <div><?= __t('طريقة الدفع:','Payment:') ?> <?= __t('الدفع عند الاستلام','Cash on Delivery') ?></div>
                <?php if ($orderDetail['tracking_no']): ?>
                  <div><?= __t('رقم التتبع:','Tracking #:') ?> <strong><?= e($orderDetail['tracking_no']) ?></strong></div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <?php elseif ($tab === 'wishlist'): ?>
        <!-- ══ Wishlist ══ -->
        <div class="dash-section-title">♡ <?= __t('قائمة الأمنيات','Wishlist') ?></div>
        <?php if ($wishlist): ?>
          <div class="grid grid-4">
            <?php foreach ($wishlist as $p):
              $price    = productPrice($p);
              $isOnSale = $p['sale_price'] && $p['sale_price'] < $p['price'];
              $disc     = $isOnSale ? getDiscountPercent($p) : 0;
            ?>
            <div class="product-card">
              <div class="product-img-wrap">
                <a href="product.php?slug=<?= urlencode($p['slug']) ?>">
                  <img src="<?= e(productImage($p)) ?>" alt="<?= e(t($p,'name')) ?>" loading="lazy">
                </a>
                <?php if ($disc): ?><div class="product-badges"><span class="badge badge-sale">-<?= $disc ?>%</span></div><?php endif; ?>
                <div class="product-actions">
                  <button class="action-btn active" onclick="toggleWishlist(<?= (int)$p['id'] ?>,this)" title="<?= __t('حذف','Remove') ?>">♥</button>
                </div>
              </div>
              <div class="product-body">
                <a href="product.php?slug=<?= urlencode($p['slug']) ?>" class="product-name"><?= e(t($p,'name')) ?></a>
                <div class="product-price">
                  <span class="price-current"><?= formatPrice($price) ?></span>
                  <?php if ($isOnSale): ?><span class="price-old"><?= formatPrice((float)$p['price']) ?></span><?php endif; ?>
                </div>
              </div>
              <div class="product-footer">
                <?php if ((int)$p['stock']>0): ?>
                  <button class="add-cart-btn" onclick="addToCart(<?= (int)$p['id'] ?>,this)">🛒 <?= __t('أضف للسلة','Add to Cart') ?></button>
                <?php else: ?>
                  <button class="add-cart-btn out-of-stock" disabled>✕ <?= __t('نفذت','Out') ?></button>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <div class="empty-icon">♡</div>
            <h3><?= __t('المفضلة فارغة','Wishlist is empty') ?></h3>
            <p><?= __t('أضف المنتجات التي تحبها لتجدها هنا بسهولة','Add products you love to find them easily') ?></p>
            <a href="shop.php" class="btn btn-primary"><?= __t('تصفح المنتجات','Browse Products') ?></a>
          </div>
        <?php endif; ?>

        <?php elseif ($tab === 'profile'): ?>
        <!-- ══ Edit Profile ══ -->
        <div class="dash-section-title">✏ <?= __t('تعديل الملف الشخصي','Edit Profile') ?></div>
        <div class="dash-box">
          <form method="POST">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="update_profile">
            <div class="grid grid-2" style="gap:20px">
              <div class="form-group">
                <label class="form-label required"><?= __t('الاسم الكامل','Full Name') ?></label>
                <input type="text" name="name" class="form-control" value="<?= e($user['name']) ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label"><?= __t('البريد الإلكتروني','Email') ?></label>
                <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled style="background:var(--gray-light);color:var(--gray)">
                <small style="color:var(--gray);font-size:.75rem"><?= __t('لا يمكن تغيير البريد الإلكتروني','Email cannot be changed') ?></small>
              </div>
              <div class="form-group">
                <label class="form-label"><?= __t('رقم الهاتف','Phone') ?></label>
                <input type="tel" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>" placeholder="01xxxxxxxxx">
              </div>
              <div class="form-group">
                <label class="form-label"><?= __t('المدينة','City') ?></label>
                <input type="text" name="city" class="form-control" value="<?= e($user['city'] ?? '') ?>">
              </div>
              <div class="form-group" style="grid-column:1/-1">
                <label class="form-label"><?= __t('العنوان','Address') ?></label>
                <textarea name="address" class="form-control" rows="3"><?= e($user['address'] ?? '') ?></textarea>
              </div>
              <div class="form-group">
                <label class="form-label"><?= __t('اللغة المفضلة','Preferred Language') ?></label>
                <select name="lang" class="form-control">
                  <option value="ar" <?= ($user['lang']??'ar')==='ar'?'selected':'' ?>><?= __t('العربية','Arabic') ?></option>
                  <option value="en" <?= ($user['lang']??'ar')==='en'?'selected':'' ?>><?= __t('الإنجليزية','English') ?></option>
                </select>
              </div>
            </div>
            <div style="margin-top:8px">
              <button type="submit" class="btn btn-primary"><?= __t('حفظ التغييرات','Save Changes') ?></button>
            </div>
          </form>
        </div>

        <?php elseif ($tab === 'password'): ?>
        <!-- ══ Change Password ══ -->
        <div class="dash-section-title">🔒 <?= __t('تغيير كلمة المرور','Change Password') ?></div>
        <div class="dash-box" style="max-width:480px">
          <form method="POST">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="change_password">
            <div class="form-group">
              <label class="form-label required"><?= __t('كلمة المرور الحالية','Current Password') ?></label>
              <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="form-group">
              <label class="form-label required"><?= __t('كلمة المرور الجديدة','New Password') ?></label>
              <input type="password" name="new_password" class="form-control" required minlength="6">
            </div>
            <div class="form-group">
              <label class="form-label required"><?= __t('تأكيد كلمة المرور الجديدة','Confirm New Password') ?></label>
              <input type="password" name="confirm_password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary"><?= __t('تغيير كلمة المرور','Change Password') ?></button>
          </form>
        </div>

        <?php endif; ?>

      </main><!-- .dash-main -->
    </div><!-- .dash-layout -->
  </div>
</section>

<style>
.page-banner { background:linear-gradient(135deg,var(--dark),var(--dark2));padding:28px 0;color:white }
.page-banner h1 { font-size:1.5rem;font-weight:900;margin-top:10px }

.dash-layout { display:grid;grid-template-columns:260px 1fr;gap:28px;align-items:start }

/* Sidebar */
.dash-sidebar { position:sticky;top:calc(var(--topbar-h)+var(--header-h)+16px) }
.dash-user-card {
  background:white;border-radius:var(--radius-md);border:1px solid var(--border);
  padding:20px;text-align:center;margin-bottom:14px
}
.duc-avatar {
  width:60px;height:60px;border-radius:50%;
  background:var(--primary);color:white;
  display:flex;align-items:center;justify-content:center;
  font-size:1.5rem;font-weight:900;margin:0 auto 12px
}
.duc-info strong { display:block;font-size:.95rem;font-weight:700;color:var(--dark) }
.duc-info span   { display:block;font-size:.78rem;color:var(--gray) }
.duc-join        { font-size:.72rem !important;color:var(--gray) !important;margin-top:2px }

.dash-nav { background:white;border-radius:var(--radius-md);border:1px solid var(--border);overflow:hidden }
.dash-nav-link {
  display:flex;align-items:center;gap:10px;
  padding:13px 18px;font-size:.88rem;color:var(--text);
  border-bottom:1px solid var(--gray-mid);transition:var(--transition);
}
.dash-nav-link:last-child { border-bottom:none }
.dash-nav-link:hover,.dash-nav-link.active { background:var(--primary-bg);color:var(--primary);font-weight:600 }
.dash-nav-link.active { border-<?= isAr()?'right':'left' ?>:3px solid var(--primary) }
.logout-link:hover { background:#fee2e2;color:#dc3545 }
.dn-icon  { font-size:1rem;width:22px;text-align:center }
.dn-badge { background:var(--primary);color:white;font-size:.65rem;font-weight:700;padding:2px 6px;border-radius:10px;margin-<?= isAr()?'right':'left' ?>:auto }

/* Main */
.dash-section-title { font-size:1.1rem;font-weight:900;color:var(--dark);margin-bottom:20px;padding-bottom:12px;border-bottom:2px solid var(--primary) }
.dash-box { background:white;border-radius:var(--radius-md);border:1px solid var(--border);overflow:hidden }
.dbox-header { display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border) }
.dbox-header h3 { font-size:.95rem;font-weight:800;color:var(--dark) }
.dbox-more { color:var(--primary);font-size:.82rem;font-weight:600 }

/* Stats */
.dash-stats { display:grid;grid-template-columns:repeat(4,1fr);gap:16px }
.ds-card {
  background:white;border:1px solid var(--border);border-radius:var(--radius-md);
  padding:20px;text-align:center;transition:var(--transition)
}
.ds-card:hover { border-color:var(--primary);box-shadow:var(--shadow-sm) }
.ds-icon  { font-size:1.8rem;margin-bottom:8px }
.ds-val   { font-size:1.3rem;font-weight:900;color:var(--primary);display:block }
.ds-label { font-size:.76rem;color:var(--gray);margin-top:4px }

/* Orders table */
.orders-table-wrap { overflow-x:auto }
.orders-table { width:100%;border-collapse:collapse;font-size:.86rem }
.orders-table th { background:var(--gray-light);padding:12px 16px;text-align:<?= isAr()?'right':'left' ?>;font-weight:700;color:var(--dark);white-space:nowrap }
.orders-table td { padding:12px 16px;border-bottom:1px solid var(--gray-mid);vertical-align:middle }
.orders-table tr:last-child td { border-bottom:none }
.orders-table tr:hover td { background:var(--gray-light) }
.order-num-cell { color:var(--primary);font-size:.82rem }

/* Status badges */
.status-badge { display:inline-block;padding:4px 12px;border-radius:20px;font-size:.74rem;font-weight:700 }
.status-pending    { background:#fff3cd;color:#856404 }
.status-confirmed  { background:#cfe2ff;color:#084298 }
.status-processing { background:#d1ecf1;color:#0c5460 }
.status-shipped    { background:#d4edda;color:#155724 }
.status-delivered  { background:#d1fae5;color:#065f46 }
.status-cancelled  { background:#fee2e2;color:#991b1b }

/* Mini product card */
.mini-product-card { display:flex;flex-direction:column;border:1px solid var(--border);border-radius:var(--radius-md);overflow:hidden;transition:var(--transition) }
.mini-product-card:hover { border-color:var(--primary);box-shadow:var(--shadow-sm) }
.mini-product-card img { width:100%;aspect-ratio:1/1;object-fit:cover }
.mpc-name  { padding:8px 10px 2px;font-size:.78rem;font-weight:600;color:var(--dark);white-space:nowrap;overflow:hidden;text-overflow:ellipsis }
.mpc-price { padding:2px 10px 8px;font-size:.82rem;font-weight:700;color:var(--primary) }

/* Order detail */
.order-detail-grid { display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:start }
.od-item { display:flex;align-items:center;gap:14px;padding:14px 20px;border-bottom:1px solid var(--gray-mid) }
.od-item:last-child { border-bottom:none }
.od-item-img { width:52px;height:52px;object-fit:cover;border-radius:var(--radius-sm);border:1px solid var(--border);flex-shrink:0 }
.od-item-info { flex:1;min-width:0 }
.od-item-name { display:block;font-size:.88rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis }
.od-item-meta { font-size:.78rem;color:var(--gray) }
.od-item-total { font-weight:700;color:var(--primary);white-space:nowrap }
.order-summary-detail .sum-row { display:flex;justify-content:space-between;padding:10px 0;font-size:.86rem;border-bottom:1px dashed var(--border) }
.order-summary-detail .sum-total { font-weight:800;font-size:.95rem;border-top:2px solid var(--border) !important }

@media(max-width:900px) {
  .dash-layout { grid-template-columns:1fr }
  .dash-sidebar { position:static }
  .dash-stats { grid-template-columns:repeat(2,1fr) }
  .order-detail-grid { grid-template-columns:1fr }
}
@media(max-width:600px) {
  .dash-stats { grid-template-columns:repeat(2,1fr) }
  .grid-4 { grid-template-columns:repeat(2,1fr) }
}
</style>

<?php require_once __DIR__ . '/footer.php'; ?>
