<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$user     = currentUser();
$wishlist = [];

if ($user) {
    $wishlist = dbFetchAll(
        "SELECT p.*, c.name_ar as cat_ar, c.name_en as cat_en, c.slug as cat_slug
         FROM wishlist w JOIN products p ON w.product_id=p.id
         LEFT JOIN categories c ON p.category_id=c.id
         WHERE w.user_id=? AND p.is_active=1 ORDER BY w.added_at DESC",
        'i', $user['id']
    );
}

$suggested = getProducts(['is_featured'=>true,'limit'=>5]);

$pageTitle = __t('المفضلة','Wishlist');
require_once __DIR__ . '/header.php';
?>

<div class="page-banner">
  <div class="container">
    <div class="breadcrumb">
      <a href="index.php"><?= __t('الرئيسية','Home') ?></a>
      <span class="sep">›</span>
      <span class="current"><?= __t('المفضلة','Wishlist') ?></span>
    </div>
    <h1>♡ <?= __t('قائمة الأمنيات','Wishlist') ?>
      <?php if ($wishlist): ?>
        <span class="count-badge"><?= count($wishlist) ?> <?= __t('منتج','items') ?></span>
      <?php endif; ?>
    </h1>
  </div>
</div>

<section class="section-sm">
  <div class="container">

    <?php if (!$user): ?>
    <!-- Not logged in -->
    <div class="empty-state" style="padding:80px 20px">
      <div class="empty-icon">🔒</div>
      <h3><?= __t('سجل دخولك أولاً','Login First') ?></h3>
      <p><?= __t('سجل دخولك لعرض قائمة الأمنيات الخاصة بك','Login to view your wishlist') ?></p>
      <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
        <a href="login.php" class="btn btn-primary btn-lg"><?= __t('تسجيل الدخول','Login') ?></a>
        <a href="register.php" class="btn btn-outline btn-lg"><?= __t('إنشاء حساب','Register') ?></a>
      </div>
    </div>

    <?php elseif ($wishlist): ?>

    <!-- Toolbar -->
    <div class="wish-toolbar">
      <span class="results-count">
        <?= __t('إجمالي','Total') ?> <strong><?= count($wishlist) ?></strong> <?= __t('منتج في المفضلة','items in wishlist') ?>
      </span>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn btn-primary btn-sm" onclick="addAllToCart()">
          🛒 <?= __t('إضافة الكل للسلة','Add All to Cart') ?>
        </button>
        <button class="btn btn-dark btn-sm" onclick="clearWishlist()">
          🗑 <?= __t('مسح الكل','Clear All') ?>
        </button>
      </div>
    </div>

    <!-- Products Grid -->
    <div class="grid grid-5" id="wishlistGrid">
      <?php foreach ($wishlist as $p):
        $price    = productPrice($p);
        $isOnSale = $p['sale_price'] && $p['sale_price'] < $p['price'];
        $disc     = $isOnSale ? getDiscountPercent($p) : 0;
        $outStock = (int)$p['stock'] === 0;
        $img      = productImage($p);
        $name     = e(t($p,'name'));
        $slug     = e($p['slug']);
      ?>
      <div class="product-card wish-card" id="wcard_<?= (int)$p['id'] ?>">
        <div class="product-img-wrap">
          <a href="product.php?slug=<?= $slug ?>">
            <img src="<?= e($img) ?>" alt="<?= $name ?>" loading="lazy"
                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2YwZjBmMCIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LXNpemU9IjQwIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iLjNlbSI+8J+Sq8K3PC90ZXh0Pjwvc3ZnPg=='">
          </a>
          <div class="product-badges">
            <?php if ($outStock): ?>
              <span class="badge badge-out"><?= __t('نفذ','Out') ?></span>
            <?php elseif ($disc > 0): ?>
              <span class="badge badge-sale">-<?= $disc ?>%</span>
            <?php elseif ($p['is_new']): ?>
              <span class="badge badge-new"><?= __t('جديد','New') ?></span>
            <?php endif; ?>
          </div>
          <!-- Remove from wishlist -->
          <button class="wish-remove-btn"
                  onclick="removeFromWishlistUI(<?= (int)$p['id'] ?>, this)"
                  title="<?= __t('حذف من المفضلة','Remove from Wishlist') ?>">
            ✕
          </button>
        </div>

        <div class="product-body">
          <a href="shop.php?cat=<?= urlencode($p['cat_slug']??'') ?>" class="product-cat-link">
            <?= e(isAr() ? ($p['cat_ar']??'') : ($p['cat_en']??'')) ?>
          </a>
          <a href="product.php?slug=<?= $slug ?>" class="product-name"><?= $name ?></a>
          <?php if ($p['rating_count'] > 0): ?>
            <div class="product-rating">
              <?= starsHtml((float)$p['rating_avg'], true) ?>
              <span class="rating-count">(<?= (int)$p['rating_count'] ?>)</span>
            </div>
          <?php endif; ?>
          <div class="product-price">
            <span class="price-current"><?= formatPrice($price) ?></span>
            <?php if ($isOnSale): ?>
              <span class="price-old"><?= formatPrice((float)$p['price']) ?></span>
              <span class="price-badge">-<?= $disc ?>%</span>
            <?php endif; ?>
          </div>
          <?php if ($outStock): ?>
            <div class="stock-warn">⚠ <?= __t('نفذت الكمية، سنُعلمك عند توفره','Out of stock, we\'ll notify you') ?></div>
          <?php elseif ($p['stock'] <= 5): ?>
            <div class="stock-low">⚡ <?= __t('باقي','Only') ?> <?= (int)$p['stock'] ?> <?= __t('قطعة فقط!','left!') ?></div>
          <?php endif; ?>
        </div>

        <div class="product-footer">
          <?php if ($outStock): ?>
            <button class="add-cart-btn out-of-stock" disabled>✕ <?= __t('نفذت','Out') ?></button>
          <?php else: ?>
            <button class="add-cart-btn"
                    data-product-id="<?= (int)$p['id'] ?>"
                    onclick="addToCartFromWish(<?= (int)$p['id'] ?>, this)">
              🛒 <?= __t('أضف للسلة','Add to Cart') ?>
            </button>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php else: ?>
    <!-- Empty wishlist (logged in) -->
    <div class="empty-state" style="padding:80px 20px">
      <div class="empty-icon" style="animation:float 3s ease-in-out infinite">♡</div>
      <h3><?= __t('قائمة الأمنيات فارغة!','Your wishlist is empty!') ?></h3>
      <p><?= __t('لم تضف أي منتجات بعد. تصفح المتجر وأضف ما يعجبك!','No products added yet. Browse the store and add what you like!') ?></p>
      <a href="shop.php" class="btn btn-primary btn-lg"><?= __t('تصفح المنتجات','Browse Products') ?> →</a>
    </div>
    <?php endif; ?>

  </div>
</section>

<!-- Suggested Products -->
<?php if ($suggested): ?>
<section class="section" style="background:var(--gray-light)">
  <div class="container">
    <div class="section-header" style="margin-bottom:28px">
      <span class="section-badge">⭐ <?= __t('قد يعجبك','You May Like') ?></span>
      <h2 class="section-title"><?= __t('منتجات <span>مقترحة</span>','<span>Suggested</span> Products') ?></h2>
    </div>
    <div class="grid grid-5">
      <?php foreach ($suggested as $p):
        $price    = productPrice($p);
        $isOnSale = $p['sale_price'] && $p['sale_price'] < $p['price'];
        $disc     = $isOnSale ? getDiscountPercent($p) : 0;
        $img      = productImage($p);
        $name     = e(t($p,'name'));
        $slug     = e($p['slug']);
        $inWish   = isInWishlist((int)$p['id']);
      ?>
      <div class="product-card">
        <div class="product-img-wrap">
          <a href="product.php?slug=<?= $slug ?>">
            <img src="<?= e($img) ?>" alt="<?= $name ?>" loading="lazy">
          </a>
          <div class="product-badges">
            <?php if ($disc): ?><span class="badge badge-sale">-<?= $disc ?>%</span><?php endif; ?>
          </div>
          <div class="product-actions">
            <button class="action-btn <?= $inWish?'active':'' ?>"
                    onclick="toggleWishlist(<?= (int)$p['id'] ?>,this)">♡</button>
          </div>
        </div>
        <div class="product-body">
          <a href="product.php?slug=<?= $slug ?>" class="product-name"><?= $name ?></a>
          <div class="product-price">
            <span class="price-current"><?= formatPrice($price) ?></span>
            <?php if ($isOnSale): ?><span class="price-old"><?= formatPrice((float)$p['price']) ?></span><?php endif; ?>
          </div>
        </div>
        <div class="product-footer">
          <button class="add-cart-btn" onclick="addToCart(<?= (int)$p['id'] ?>,this)">🛒 <?= __t('أضف للسلة','Add to Cart') ?></button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<style>
.page-banner { background:linear-gradient(135deg,var(--dark),var(--dark2));padding:28px 0;color:white }
.page-banner h1 { font-size:1.6rem;font-weight:900;margin-top:10px;display:flex;align-items:center;gap:14px;flex-wrap:wrap }

.wish-toolbar {
  display:flex;align-items:center;justify-content:space-between;
  gap:16px;flex-wrap:wrap;
  padding:14px 18px;background:white;
  border-radius:var(--radius-md);border:1px solid var(--border);
  margin-bottom:24px;
}

.wish-card { position:relative; }
.wish-remove-btn {
  position:absolute;top:10px;<?= isAr()?'left':'right' ?>:10px;
  width:28px;height:28px;border-radius:50%;
  background:rgba(220,53,69,.9);color:white;
  font-size:.7rem;display:flex;align-items:center;justify-content:center;
  cursor:pointer;transition:var(--transition);
  opacity:0;
}
.wish-card:hover .wish-remove-btn { opacity:1; }
.wish-remove-btn:hover { background:#dc3545;transform:scale(1.1); }

.stock-warn { font-size:.74rem;color:#d97706;margin-top:6px; }
.stock-low  { font-size:.74rem;color:var(--primary);font-weight:600;margin-top:6px; }

@keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-12px)} }

@media(max-width:900px) { .grid-5 { grid-template-columns:repeat(3,1fr); } }
@media(max-width:600px) { .grid-5 { grid-template-columns:repeat(2,1fr); } }
</style>

<script>
const CSRF_W = '<?= generateCsrfToken() ?>';

// ── Remove single from wishlist ────────────────────────
function removeFromWishlistUI(productId, btn) {
  fetch('wishlist-action.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: `product_id=${productId}&csrf_token=${CSRF_W}`
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      const card = document.getElementById('wcard_' + productId);
      if (card) {
        card.style.transition = 'opacity .3s, transform .3s';
        card.style.opacity = '0';
        card.style.transform = 'scale(.9)';
        setTimeout(() => {
          card.remove();
          const remaining = document.querySelectorAll('.wish-card').length;
          if (remaining === 0) location.reload();
          // Update header badge
          document.querySelectorAll('.hdr-badge').forEach(b => {
            const v = parseInt(b.textContent) - 1;
            b.textContent = v > 0 ? v : '';
          });
        }, 300);
        showToast(d.message, 'info');
      }
    } else if (d.redirect) {
      window.location.href = d.redirect;
    }
  });
}

// ── Add to cart from wishlist ──────────────────────────
function addToCartFromWish(productId, btn) {
  if (btn) { btn.disabled = true; btn.textContent = '...'; }
  fetch('cart-action.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: `action=add&product_id=${productId}&csrf_token=${CSRF_W}`
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      showToast(d.message || '<?= __t("تمت الإضافة","Added") ?>', 'success');
      document.querySelectorAll('.hdr-badge').forEach(b => b.textContent = d.count || '');
    } else {
      showToast(d.message || '<?= __t("حدث خطأ","Error") ?>', 'error');
    }
  })
  .finally(() => {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '🛒 <?= __t("أضف للسلة","Add to Cart") ?>';
    }
  });
}

// ── Add all to cart ────────────────────────────────────
function addAllToCart() {
  const cards = document.querySelectorAll('.wish-card');
  if (!cards.length) return;
  let count = 0;
  cards.forEach(card => {
    const btn = card.querySelector('.add-cart-btn:not(.out-of-stock)');
    if (btn) {
      const pid = parseInt(card.id.replace('wcard_',''));
      fetch('cart-action.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `action=add&product_id=${pid}&csrf_token=${CSRF_W}`
      })
      .then(r => r.json())
      .then(d => {
        if (d.success) {
          count++;
          document.querySelectorAll('.hdr-badge').forEach(b => b.textContent = d.count || '');
          if (count === cards.length) {
            showToast('<?= __t("تمت إضافة كل المنتجات للسلة","All products added to cart") ?>', 'success');
          }
        }
      });
    }
  });
}

// ── Clear all wishlist ─────────────────────────────────
function clearWishlist() {
  if (!confirm('<?= __t("هل تريد مسح كل المفضلة؟","Clear all wishlist?") ?>')) return;
  const cards = document.querySelectorAll('.wish-card');
  cards.forEach(card => {
    const pid = parseInt(card.id.replace('wcard_',''));
    fetch('wishlist-action.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: `product_id=${pid}&csrf_token=${CSRF_W}`
    });
  });
  setTimeout(() => location.reload(), 800);
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
