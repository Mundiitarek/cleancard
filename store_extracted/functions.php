<?php
// ============================================================
// functions.php - الدوال المشتركة
// ============================================================
if (!defined('DB_HOST')) require_once __DIR__ . '/db.php';

// ============================================================
// ─── اللغة والترجمة ─────────────────────────────────────────
// ============================================================
function initLang(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (isset($_GET['lang']) && in_array($_GET['lang'], ['ar','en'])) {
        $_SESSION['lang'] = $_GET['lang'];
        if (isset($_SERVER['HTTP_REFERER'])) {
            header('Location: ' . strtok($_SERVER['HTTP_REFERER'], '?') .
                   (strpos($_SERVER['HTTP_REFERER'],'?') !== false
                       ? '?' . http_build_query(array_diff_key($_GET, ['lang'=>'']))
                       : ''));
        }
    }
    if (!isset($_SESSION['lang'])) $_SESSION['lang'] = 'ar';
}

function lang(): string {
    return $_SESSION['lang'] ?? 'ar';
}

function isAr(): bool { return lang() === 'ar'; }
function isEn(): bool { return lang() === 'en'; }
function appDir(): string { return isAr() ? 'rtl' : 'ltr'; }
function textStart(): string { return isAr() ? 'right' : 'left'; }
function textEnd(): string   { return isAr() ? 'left' : 'right'; }

// ترجمة حقول ثنائية اللغة
function t(array $row, string $field): string {
    $key = $field . '_' . lang();
    return $row[$key] ?? $row[$field . '_ar'] ?? $row[$field] ?? '';
}

// النصوص الثابتة
function __t(string $ar, string $en): string {
    return isAr() ? $ar : $en;
}

// ============================================================
// ─── الإعدادات ───────────────────────────────────────────────
// ============================================================
function getSetting(string $key, string $default = ''): string {
    static $cache = [];
    if (!isset($cache[$key])) {
        $row = dbFetchOne("SELECT value FROM settings WHERE key_name=?", 's', $key);
        $cache[$key] = $row['value'] ?? $default;
    }
    return $cache[$key];
}

function getAllSettings(): array {
    static $all = null;
    if ($all === null) {
        $rows = dbFetchAll("SELECT key_name, value FROM settings");
        $all  = array_column($rows, 'value', 'key_name');
    }
    return $all;
}

function siteName(): string {
    return isAr() ? getSetting('site_name_ar','متجري') : getSetting('site_name_en','MyStore');
}

function currencyLabel(): string {
    return isAr() ? getSetting('currency_ar','ج.م') : getSetting('currency_en','EGP');
}

function formatPrice(float $price): string {
    return number_format($price, 2) . ' ' . currencyLabel();
}

function shippingFee(): float {
    return (float) getSetting('shipping_fee', '50');
}

function freeShippingMin(): float {
    return (float) getSetting('free_ship_min', '500');
}

// ============================================================
// ─── الجلسة والمصادقة ────────────────────────────────────────
// ============================================================
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['lifetime'=>0,'path'=>'/','httponly'=>true,'samesite'=>'Lax']);
        session_start();
    }
}

function currentUser(): ?array {
    startSession();
    if (empty($_SESSION['user_id'])) return null;
    return dbFetchOne("SELECT * FROM users WHERE id=? AND is_active=1", 'i', $_SESSION['user_id']);
}

function isLoggedIn(): bool {
    return currentUser() !== null;
}

function isAdmin(): bool {
    $u = currentUser();
    return $u && $u['role'] === 'admin';
}

function requireLogin(string $redirect = 'login.php'): void {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header("Location: $redirect"); exit;
    }
}

function requireAdmin(): void {
    if (!isAdmin()) { header('Location: index.php'); exit; }
}

function loginUser(array $user): void {
    startSession();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['lang'] = $user['lang'] ?? 'ar';
}

function logoutUser(): void {
    startSession();
    session_unset();
    session_destroy();
    setcookie(session_name(), '', time()-3600, '/');
}

// ============================================================
// ─── السلة ───────────────────────────────────────────────────
// ============================================================
function getSessionId(): string {
    startSession();
    if (empty($_SESSION['cart_session'])) {
        $_SESSION['cart_session'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['cart_session'];
}

function getCartItems(): array {
    $u = currentUser();
    if ($u) {
        return dbFetchAll("
            SELECT c.*, p.name_ar, p.name_en, p.price, p.sale_price,
                   p.image, p.stock, p.slug
            FROM cart c JOIN products p ON c.product_id=p.id
            WHERE c.user_id=? AND p.is_active=1
            ORDER BY c.added_at DESC
        ", 'i', $u['id']);
    } else {
        $sid = getSessionId();
        return dbFetchAll("
            SELECT c.*, p.name_ar, p.name_en, p.price, p.sale_price,
                   p.image, p.stock, p.slug
            FROM cart c JOIN products p ON c.product_id=p.id
            WHERE c.session_id=? AND p.is_active=1
            ORDER BY c.added_at DESC
        ", 's', $sid);
    }
}

function getCartCount(): int {
    $items = getCartItems();
    return array_sum(array_column($items, 'quantity'));
}

function getCartTotal(): float {
    $items = getCartItems();
    $total = 0;
    foreach ($items as $item) {
        $price = $item['sale_price'] ?: $item['price'];
        $total += $price * $item['quantity'];
    }
    return $total;
}

function addToCart(int $productId, int $qty = 1): array {
    $product = dbFetchOne("SELECT * FROM products WHERE id=? AND is_active=1", 'i', $productId);
    if (!$product) return ['success'=>false,'message'=>__t('المنتج غير موجود','Product not found')];
    if ($product['stock'] < 1) return ['success'=>false,'message'=>__t('المنتج غير متوفر','Out of stock')];

    $u = currentUser();
    if ($u) {
        $existing = dbFetchOne("SELECT * FROM cart WHERE user_id=? AND product_id=?", 'ii', $u['id'], $productId);
        if ($existing) {
            $newQty = min($existing['quantity'] + $qty, $product['stock']);
            dbUpdate('cart', ['quantity'=>$newQty], 'id=?', 'i', $existing['id']);
        } else {
            dbInsert('cart', ['user_id'=>$u['id'],'product_id'=>$productId,'quantity'=>min($qty,$product['stock'])]);
        }
    } else {
        $sid = getSessionId();
        $existing = dbFetchOne("SELECT * FROM cart WHERE session_id=? AND product_id=?", 'si', $sid, $productId);
        if ($existing) {
            $newQty = min($existing['quantity'] + $qty, $product['stock']);
            dbUpdate('cart', ['quantity'=>$newQty], 'id=?', 'i', $existing['id']);
        } else {
            dbInsert('cart', ['session_id'=>$sid,'product_id'=>$productId,'quantity'=>min($qty,$product['stock'])]);
        }
    }
    return ['success'=>true,'message'=>__t('تمت الإضافة للسلة','Added to cart'),'count'=>getCartCount()];
}

function updateCartQty(int $cartId, int $qty): bool {
    $u = currentUser();
    if ($u) {
        return dbUpdate('cart',['quantity'=>$qty],'id=? AND user_id=?','ii',$cartId,$u['id']);
    }
    $sid = getSessionId();
    return dbUpdate('cart',['quantity'=>$qty],'id=? AND session_id=?','is',$cartId,$sid);
}

function removeFromCart(int $cartId): bool {
    $u = currentUser();
    if ($u) {
        $stmt = dbQuery("DELETE FROM cart WHERE id=? AND user_id=?", 'ii', $cartId, $u['id']);
    } else {
        $sid = getSessionId();
        $stmt = dbQuery("DELETE FROM cart WHERE id=? AND session_id=?", 'is', $cartId, $sid);
    }
    return $stmt ? $stmt->affected_rows > 0 : false;
}

function clearCart(): void {
    $u = currentUser();
    if ($u) {
        dbQuery("DELETE FROM cart WHERE user_id=?", 'i', $u['id']);
    } else {
        $sid = getSessionId();
        dbQuery("DELETE FROM cart WHERE session_id=?", 's', $sid);
    }
}

function mergeGuestCart(): void {
    startSession();
    if (empty($_SESSION['cart_session'])) return;
    $sid = $_SESSION['cart_session'];
    $u   = currentUser();
    if (!$u) return;
    $guestItems = dbFetchAll("SELECT * FROM cart WHERE session_id=?", 's', $sid);
    foreach ($guestItems as $item) {
        $ex = dbFetchOne("SELECT * FROM cart WHERE user_id=? AND product_id=?", 'ii', $u['id'], $item['product_id']);
        if ($ex) {
            dbUpdate('cart',['quantity'=>$ex['quantity']+$item['quantity']],'id=?','i',$ex['id']);
        } else {
            dbInsert('cart',['user_id'=>$u['id'],'product_id'=>$item['product_id'],'quantity'=>$item['quantity']]);
        }
    }
    dbQuery("DELETE FROM cart WHERE session_id=?", 's', $sid);
    unset($_SESSION['cart_session']);
}

// ============================================================
// ─── المفضلة ─────────────────────────────────────────────────
// ============================================================
function isInWishlist(int $productId): bool {
    $u = currentUser();
    if (!$u) return false;
    $r = dbFetchOne("SELECT id FROM wishlist WHERE user_id=? AND product_id=?", 'ii', $u['id'], $productId);
    return $r !== null;
}

function toggleWishlist(int $productId): array {
    $u = currentUser();
    if (!$u) return ['success'=>false,'message'=>__t('سجل دخولك أولاً','Please login first')];
    if (isInWishlist($productId)) {
        dbQuery("DELETE FROM wishlist WHERE user_id=? AND product_id=?", 'ii', $u['id'], $productId);
        return ['success'=>true,'added'=>false,'message'=>__t('تم الحذف من المفضلة','Removed from wishlist')];
    }
    dbInsert('wishlist',['user_id'=>$u['id'],'product_id'=>$productId]);
    return ['success'=>true,'added'=>true,'message'=>__t('تمت الإضافة للمفضلة','Added to wishlist')];
}

function getWishlistCount(): int {
    $u = currentUser();
    if (!$u) return 0;
    $r = dbFetchOne("SELECT COUNT(*) as c FROM wishlist WHERE user_id=?", 'i', $u['id']);
    return (int)($r['c'] ?? 0);
}

// ============================================================
// ─── الكوبونات ───────────────────────────────────────────────
// ============================================================
function applyCoupon(string $code, float $subtotal): array {
    $coupon = dbFetchOne("SELECT * FROM coupons WHERE code=? AND is_active=1", 's', $code);
    if (!$coupon) return ['success'=>false,'message'=>__t('كوبون غير صالح','Invalid coupon')];
    if ($coupon['expires_at'] && strtotime($coupon['expires_at']) < time())
        return ['success'=>false,'message'=>__t('انتهت صلاحية الكوبون','Coupon expired')];
    if ($coupon['max_uses'] && $coupon['used_count'] >= $coupon['max_uses'])
        return ['success'=>false,'message'=>__t('الكوبون استُنفد','Coupon limit reached')];
    if ($subtotal < $coupon['min_order'])
        return ['success'=>false,'message'=>__t('الحد الأدنى للطلب: ','Min order: ') . formatPrice($coupon['min_order'])];

    $discount = $coupon['type'] === 'percent'
        ? $subtotal * ($coupon['value'] / 100)
        : (float)$coupon['value'];
    $discount = min($discount, $subtotal);
    return ['success'=>true,'discount'=>round($discount,2),'coupon'=>$coupon];
}

// ============================================================
// ─── الطلبات ─────────────────────────────────────────────────
// ============================================================
function generateOrderNumber(): string {
    return 'ORD-' . strtoupper(substr(md5(uniqid()),0,8)) . '-' . date('ymd');
}

function createOrder(array $data, array $cartItems): int {
    $subtotal = 0;
    foreach ($cartItems as $item) {
        $price = $item['sale_price'] ?: $item['price'];
        $subtotal += $price * $item['quantity'];
    }
    // استخدم رسوم الشحن من المنطقة إذا وُجدت، وإلا من الإعدادات العامة
    if (isset($data['shipping_fee'])) {
        $shipping = (float)$data['shipping_fee'];
    } else {
        $shipping = $subtotal >= freeShippingMin() ? 0 : shippingFee();
    }
    $discount  = $data['discount'] ?? 0;
    $total     = max(0, $subtotal + $shipping - $discount);
    $orderNum  = generateOrderNumber();

    $orderId = dbInsert('orders', [
        'order_number'   => $orderNum,
        'user_id'        => currentUser()['id'] ?? null,
        'name'           => $data['name'],
        'email'          => $data['email'] ?? '',
        'phone'          => $data['phone'],
        'address'        => $data['address'],
        'city'           => $data['city'],
        'notes'          => $data['notes'] ?? '',
        'subtotal'       => $subtotal,
        'shipping_fee'   => $shipping,
        'discount'       => $discount,
        'total'          => $total,
        'coupon_code'    => $data['coupon_code'] ?? '',
        'payment_method' => 'cod',
        'status'         => 'pending',
    ]);

    if (!$orderId) return 0;

    foreach ($cartItems as $item) {
        $price = $item['sale_price'] ?: $item['price'];
        dbInsert('order_items', [
            'order_id'    => $orderId,
            'product_id'  => $item['product_id'],
            'name_ar'     => $item['name_ar'],
            'name_en'     => $item['name_en'],
            'image'       => $item['image'] ?? '',
            'quantity'    => $item['quantity'],
            'unit_price'  => $price,
            'total_price' => $price * $item['quantity'],
        ]);
        // خصم الكمية من المخزون
        dbQuery("UPDATE products SET stock=stock-? WHERE id=? AND stock>=?",
                'iii', $item['quantity'], $item['product_id'], $item['quantity']);
    }

    // تحديث عدد استخدام الكوبون
    if (!empty($data['coupon_code'])) {
        dbQuery("UPDATE coupons SET used_count=used_count+1 WHERE code=?", 's', $data['coupon_code']);
    }

    clearCart();
    return $orderId;
}

function getOrderStatuses(): array {
    return [
        'pending'    => ['ar'=>'في الانتظار',   'en'=>'Pending',     'color'=>'warning'],
        'confirmed'  => ['ar'=>'تم التأكيد',    'en'=>'Confirmed',   'color'=>'info'],
        'processing' => ['ar'=>'جاري التجهيز',  'en'=>'Processing',  'color'=>'primary'],
        'shipped'    => ['ar'=>'تم الشحن',      'en'=>'Shipped',     'color'=>'secondary'],
        'delivered'  => ['ar'=>'تم التسليم',    'en'=>'Delivered',   'color'=>'success'],
        'cancelled'  => ['ar'=>'ملغي',          'en'=>'Cancelled',   'color'=>'danger'],
    ];
}

function orderStatusLabel(string $status): string {
    $statuses = getOrderStatuses();
    $s = $statuses[$status] ?? null;
    if (!$s) return $status;
    return isAr() ? $s['ar'] : $s['en'];
}

function orderStatusColor(string $status): string {
    $statuses = getOrderStatuses();
    return $statuses[$status]['color'] ?? 'secondary';
}

// ============================================================
// ─── المنتجات ────────────────────────────────────────────────
// ============================================================
function getProducts(array $opts = []): array {
    $where  = ['p.is_active=1'];
    $types  = '';
    $params = [];

    if (!empty($opts['category_id'])) {
        $where[] = 'p.category_id=?'; $types .= 'i'; $params[] = (int)$opts['category_id'];
    }
    if (!empty($opts['is_featured'])) {
        $where[] = 'p.is_featured=1';
    }
    if (!empty($opts['is_new'])) {
        $where[] = 'p.is_new=1';
    }
    if (!empty($opts['search'])) {
        $s = '%' . $opts['search'] . '%';
        $where[] = '(p.name_ar LIKE ? OR p.name_en LIKE ? OR p.tags LIKE ?)';
        $types .= 'sss'; $params[] = $s; $params[] = $s; $params[] = $s;
    }
    if (!empty($opts['min_price'])) {
        $where[] = 'COALESCE(p.sale_price,p.price)>=?'; $types .= 'd'; $params[] = (float)$opts['min_price'];
    }
    if (!empty($opts['max_price'])) {
        $where[] = 'COALESCE(p.sale_price,p.price)<=?'; $types .= 'd'; $params[] = (float)$opts['max_price'];
    }

    $whereStr = implode(' AND ', $where);
    $orderBy  = match($opts['sort'] ?? '') {
        'price_asc'  => 'COALESCE(p.sale_price,p.price) ASC',
        'price_desc' => 'COALESCE(p.sale_price,p.price) DESC',
        'newest'     => 'p.created_at DESC',
        'popular'    => 'p.views DESC',
        'rating'     => 'p.rating_avg DESC',
        default      => 'p.is_featured DESC, p.created_at DESC',
    };

    $limit  = (int)($opts['limit'] ?? 20);
    $offset = (int)($opts['offset'] ?? 0);

    $sql = "SELECT p.*, c.name_ar AS cat_ar, c.name_en AS cat_en, c.slug AS cat_slug
            FROM products p
            LEFT JOIN categories c ON p.category_id=c.id
            WHERE $whereStr ORDER BY $orderBy LIMIT $limit OFFSET $offset";

    return $types ? dbFetchAll($sql, $types, ...$params) : dbFetchAll($sql);
}

function getProduct(string $slugOrId): ?array {
    if (is_numeric($slugOrId)) {
        return dbFetchOne("SELECT p.*, c.name_ar AS cat_ar, c.name_en AS cat_en
                           FROM products p LEFT JOIN categories c ON p.category_id=c.id
                           WHERE p.id=? AND p.is_active=1", 'i', (int)$slugOrId);
    }
    return dbFetchOne("SELECT p.*, c.name_ar AS cat_ar, c.name_en AS cat_en
                       FROM products p LEFT JOIN categories c ON p.category_id=c.id
                       WHERE p.slug=? AND p.is_active=1", 's', $slugOrId);
}

function getDiscountPercent(array $product): int {
    if (!$product['sale_price'] || $product['sale_price'] >= $product['price']) return 0;
    return (int)round((($product['price'] - $product['sale_price']) / $product['price']) * 100);
}

function productPrice(array $product): float {
    return $product['sale_price'] ?: (float)$product['price'];
}

function productImage(array $product, string $default = ''): string {
    if (!empty($product['image'])) return UPLOADS_URL . 'products/' . $product['image'];
    return $default ?: SITE_URL . '/assets/no-image.png';
}

function incrementProductViews(int $id): void {
    dbQuery("UPDATE products SET views=views+1 WHERE id=?", 'i', $id);
}

// ============================================================
// ─── الأقسام ─────────────────────────────────────────────────
// ============================================================
function getCategories(bool $activeOnly = true): array {
    $where = $activeOnly ? 'WHERE is_active=1' : '';
    return dbFetchAll("SELECT * FROM categories $where ORDER BY sort_order ASC, id ASC");
}

function getCategory(string $slug): ?array {
    return dbFetchOne("SELECT * FROM categories WHERE slug=? AND is_active=1", 's', $slug);
}

// ============================================================
// ─── مناطق التوصيل ───────────────────────────────────────────
// ============================================================
function getShippingZones(bool $activeOnly = true): array {
    $where = $activeOnly ? 'WHERE is_active=1' : '';
    return dbFetchAll("SELECT * FROM shipping_zones $where ORDER BY sort_order ASC, id ASC");
}

function getShippingZone(int $id): ?array {
    return dbFetchOne("SELECT * FROM shipping_zones WHERE id=? AND is_active=1", 'i', $id);
}

function categoryImage(array $cat, string $default = ''): string {
    if (!empty($cat['image'])) return UPLOADS_URL . 'categories/' . $cat['image'];
    return $default;
}

// ============================================================
// ─── رفع الصور ───────────────────────────────────────────────
// ============================================================
function uploadImage(array $file, string $folder = 'products'): array {
    $allowed   = ['image/jpeg','image/png','image/webp','image/gif'];
    $maxSize   = 5 * 1024 * 1024; // 5MB

    if ($file['error'] !== UPLOAD_ERR_OK)
        return ['success'=>false,'message'=>__t('خطأ في الرفع','Upload error')];
    if (!in_array($file['type'], $allowed))
        return ['success'=>false,'message'=>__t('نوع الملف غير مسموح','File type not allowed')];
    if ($file['size'] > $maxSize)
        return ['success'=>false,'message'=>__t('حجم الملف كبير جداً','File too large (max 5MB)')];

    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('img_') . '.' . strtolower($ext);
    $dir      = UPLOADS_DIR . $folder . '/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    if (!move_uploaded_file($file['tmp_name'], $dir . $filename))
        return ['success'=>false,'message'=>__t('فشل حفظ الملف','Failed to save file')];

    return ['success'=>true,'filename'=>$filename,'url'=>UPLOADS_URL.$folder.'/'.$filename];
}

function deleteImage(string $filename, string $folder = 'products'): bool {
    if (empty($filename)) return false;
    $path = UPLOADS_DIR . $folder . '/' . $filename;
    return file_exists($path) && unlink($path);
}

// ============================================================
// ─── التحقق والأمان ──────────────────────────────────────────
// ============================================================
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function sanitize(string $str): string {
    return trim(strip_tags($str));
}

function validateEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePhone(string $phone): bool {
    return preg_match('/^[\d\+\-\s\(\)]{7,20}$/', $phone) === 1;
}

function slug(string $text): string {
    $text = mb_strtolower(trim($text));
    $text = preg_replace('/[\s\-]+/', '-', $text);
    $text = preg_replace('/[^\p{L}\p{N}\-]/u', '', $text);
    return trim($text, '-');
}

function generateCsrfToken(): string {
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool {
    startSession();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfInput(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(generateCsrfToken()) . '">';
}

// ============================================================
// ─── Pagination ──────────────────────────────────────────────
// ============================================================
function paginate(string $sql, string $types, array $params, int $page, int $perPage = 20): array {
    // Count
    $countSql = preg_replace('/SELECT .+? FROM/si', 'SELECT COUNT(*) as total FROM', $sql);
    $countSql = preg_replace('/ORDER BY .+$/si', '', $countSql);
    $countRow = $params ? dbFetchOne($countSql, $types, ...$params) : dbFetchOne($countSql);
    $total    = (int)($countRow['total'] ?? 0);
    $pages    = max(1, (int)ceil($total / $perPage));
    $page     = max(1, min($page, $pages));
    $offset   = ($page - 1) * $perPage;

    $pageSql = $sql . " LIMIT $perPage OFFSET $offset";
    $items   = $params ? dbFetchAll($pageSql, $types, ...$params) : dbFetchAll($pageSql);

    return [
        'items'      => $items,
        'total'      => $total,
        'page'       => $page,
        'pages'      => $pages,
        'per_page'   => $perPage,
        'has_prev'   => $page > 1,
        'has_next'   => $page < $pages,
    ];
}

function paginationHtml(array $pager, string $baseUrl): string {
    if ($pager['pages'] <= 1) return '';
    $sep = strpos($baseUrl, '?') !== false ? '&' : '?';
    $html = '<nav class="pagination-nav"><ul class="pagination">';
    if ($pager['has_prev']) {
        $html .= '<li><a href="'.$baseUrl.$sep.'page='.($pager['page']-1).'">&#8249;</a></li>';
    }
    for ($i = max(1,$pager['page']-2); $i <= min($pager['pages'],$pager['page']+2); $i++) {
        $active = $i === $pager['page'] ? ' active' : '';
        $html .= '<li class="'.$active.'"><a href="'.$baseUrl.$sep.'page='.$i.'">'.$i.'</a></li>';
    }
    if ($pager['has_next']) {
        $html .= '<li><a href="'.$baseUrl.$sep.'page='.($pager['page']+1).'">&#8250;</a></li>';
    }
    $html .= '</ul></nav>';
    return $html;
}

// ============================================================
// ─── الإشعارات (Flash Messages) ─────────────────────────────
// ============================================================
function setFlash(string $type, string $message): void {
    startSession();
    $_SESSION['flash'][] = ['type'=>$type,'message'=>$message];
}

function getFlash(): array {
    startSession();
    $flash = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flash;
}

function showFlash(): string {
    $messages = getFlash();
    if (!$messages) return '';
    $html = '';
    foreach ($messages as $f) {
        $icon = match($f['type']) {
            'success' => '✓', 'error' => '✕', 'warning' => '⚠', default => 'ℹ',
        };
        $html .= '<div class="flash-msg flash-'.$f['type'].'">'.$icon.' '.e($f['message']).'
                  <button class="flash-close" onclick="this.parentElement.remove()">×</button></div>';
    }
    return $html;
}

// ============================================================
// ─── النشاط والسجل ───────────────────────────────────────────
// ============================================================
function logActivity(string $action, string $desc = '', int $userId = 0): void {
    $u  = $userId ?: (currentUser()['id'] ?? 0);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    dbInsert('activity_logs', [
        'user_id'     => $u ?: null,
        'action'      => $action,
        'description' => $desc,
        'ip'          => $ip,
    ]);
}

// ============================================================
// ─── الإحصائيات (للوحة التحكم) ──────────────────────────────
// ============================================================
function getDashboardStats(): array {
    return [
        'orders_total'     => (int)(dbFetchOne("SELECT COUNT(*) c FROM orders")['c'] ?? 0),
        'orders_today'     => (int)(dbFetchOne("SELECT COUNT(*) c FROM orders WHERE DATE(created_at)=CURDATE()")['c'] ?? 0),
        'orders_pending'   => (int)(dbFetchOne("SELECT COUNT(*) c FROM orders WHERE status='pending'")['c'] ?? 0),
        'revenue_total'    => (float)(dbFetchOne("SELECT SUM(total) s FROM orders WHERE status NOT IN ('cancelled')")['s'] ?? 0),
        'revenue_today'    => (float)(dbFetchOne("SELECT SUM(total) s FROM orders WHERE DATE(created_at)=CURDATE() AND status!='cancelled'")['s'] ?? 0),
        'products_total'   => (int)(dbFetchOne("SELECT COUNT(*) c FROM products WHERE is_active=1")['c'] ?? 0),
        'products_lowstock'=> (int)(dbFetchOne("SELECT COUNT(*) c FROM products WHERE stock<=5 AND is_active=1")['c'] ?? 0),
        'customers_total'  => (int)(dbFetchOne("SELECT COUNT(*) c FROM users WHERE role='customer'")['c'] ?? 0),
        'customers_new'    => (int)(dbFetchOne("SELECT COUNT(*) c FROM users WHERE role='customer' AND DATE(created_at)=CURDATE()")['c'] ?? 0),
        'categories_total' => (int)(dbFetchOne("SELECT COUNT(*) c FROM categories WHERE is_active=1")['c'] ?? 0),
    ];
}

// ============================================================
// ─── النجوم / التقييم ────────────────────────────────────────
// ============================================================
function starsHtml(float $rating, bool $small = false): string {
    $cls  = $small ? 'stars-sm' : 'stars';
    $html = '<span class="'.$cls.'">';
    for ($i = 1; $i <= 5; $i++) {
        if ($rating >= $i)        $html .= '<span class="star full">★</span>';
        elseif ($rating >= $i-.5) $html .= '<span class="star half">★</span>';
        else                      $html .= '<span class="star empty">☆</span>';
    }
    return $html . '</span>';
}

// ============================================================
// ─── روابط مساعدة ────────────────────────────────────────────
// ============================================================
function productUrl(array $product): string {
    return SITE_URL . '/product.php?slug=' . urlencode($product['slug']);
}

function categoryUrl(array $cat): string {
    return SITE_URL . '/shop.php?cat=' . urlencode($cat['slug']);
}

function activeNavClass(string $page): string {
    $current = basename($_SERVER['PHP_SELF']);
    return $current === $page ? 'active' : '';
}

function langToggleUrl(): string {
    $params = $_GET;
    $params['lang'] = isAr() ? 'en' : 'ar';
    return '?' . http_build_query($params);
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)   return __t('الآن', 'Just now');
    if ($diff < 3600) return (int)($diff/60)  . ' ' . __t('دقيقة', 'min ago');
    if ($diff < 86400)return (int)($diff/3600) . ' ' . __t('ساعة', 'h ago');
    return date('Y-m-d', strtotime($datetime));
}

// ─── تهيئة ───────────────────────────────────────────────────
startSession();
initLang();
