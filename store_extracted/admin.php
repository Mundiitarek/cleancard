<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php-error.log');

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function ($e) {
    $msg = "Uncaught Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString();
    error_log($msg);

    http_response_code(500);
    echo '<div style="direction:ltr;background:#111;color:#eee;padding:20px;font-family:monospace;line-height:1.7">';
    echo '<h2 style="color:#ff6b6b;margin-top:0">PHP Exception</h2>';
    echo '<p><strong>Message:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
    echo '<p><strong>Line:</strong> ' . (int)$e->getLine() . '</p>';
    echo '<h3 style="color:#ffd166">Trace</h3>';
    echo '<pre style="white-space:pre-wrap">' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    echo '</div>';
    exit;
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $msg = "Fatal Error: {$error['message']} in {$error['file']}:{$error['line']}";
        error_log($msg);

        http_response_code(500);
        echo '<div style="direction:ltr;background:#111;color:#eee;padding:20px;font-family:monospace;line-height:1.7">';
        echo '<h2 style="color:#ff6b6b;margin-top:0">Fatal Error</h2>';
        echo '<p><strong>Message:</strong> ' . htmlspecialchars($error['message']) . '</p>';
        echo '<p><strong>File:</strong> ' . htmlspecialchars($error['file']) . '</p>';
        echo '<p><strong>Line:</strong> ' . (int)$error['line'] . '</p>';
        echo '</div>';
    }
});
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
requireAdmin();

$tab    = $_GET['tab'] ?? 'dashboard';
$errors = [];
$successMsg = '';

// ══════════════════════════════════════════════════════
// POST HANDLERS
// ══════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = __t('رمز غير صالح','Invalid token');
    } else {
        $action = $_POST['action'] ?? '';

        // ── Order status update ─────────────────────
        if ($action === 'update_order_status') {
            $orderId    = (int)($_POST['order_id'] ?? 0);
            $status     = sanitize($_POST['status'] ?? '');
            $trackingNo = sanitize($_POST['tracking_no'] ?? '');
            $validStatuses = array_keys(getOrderStatuses());
            if ($orderId && in_array($status, $validStatuses)) {
                dbUpdate('orders',
                    ['status'=>$status,'tracking_no'=>$trackingNo,'updated_at'=>date('Y-m-d H:i:s')],
                    'id=?','i',$orderId
                );
                $successMsg = __t('تم تحديث حالة الطلب','Order status updated');
                logActivity('update_order','Order #'.$orderId.' → '.$status);
            }
            $tab = 'orders';
        }

        // ── Add/Edit Product ────────────────────────
        if (in_array($action, ['add_product','edit_product'])) {
            $pid     = (int)($_POST['product_id'] ?? 0);
            $nameAr  = sanitize($_POST['name_ar'] ?? '');
            $nameEn  = sanitize($_POST['name_en'] ?? '');
            $descAr  = sanitize($_POST['desc_ar'] ?? '');
            $descEn  = sanitize($_POST['desc_en'] ?? '');
            $catId   = (int)($_POST['category_id'] ?? 0);
            $price   = (float)($_POST['price'] ?? 0);
            $sale    = (float)($_POST['sale_price'] ?? 0);
            $stock   = (int)($_POST['stock'] ?? 0);
            $sku     = sanitize($_POST['sku'] ?? '');
            $tags    = sanitize($_POST['tags'] ?? '');
            $isFeat  = isset($_POST['is_featured']) ? 1 : 0;
            $isNew   = isset($_POST['is_new'])      ? 1 : 0;
            $isAct   = isset($_POST['is_active'])   ? 1 : 0;

            if (!$nameAr) $errors[] = __t('الاسم بالعربية مطلوب','Arabic name required');
            if ($price <= 0) $errors[] = __t('السعر مطلوب','Price required');
            if (!$catId)  $errors[] = __t('القسم مطلوب','Category required');

            // Image upload
            $imageName = '';
            if (!empty($_FILES['image']['name'])) {
                $res = uploadImage($_FILES['image'], 'products');
                if ($res['success']) $imageName = $res['filename'];
                else $errors[] = $res['error'];
            }

            if (!$errors) {
                $data = [
                    'name_ar'     => $nameAr,
                    'name_en'     => $nameEn ?: $nameAr,
                    'desc_ar'     => $descAr,
                    'desc_en'     => $descEn,
                    'category_id' => $catId,
                    'price'       => $price,
                    'sale_price'  => $sale ?: null,
                    'stock'       => $stock,
                    'sku'         => $sku,
                    'tags'        => $tags,
                    'is_featured' => $isFeat,
                    'is_new'      => $isNew,
                    'is_active'   => $isAct,
                    'slug'        => slug($nameEn ?: $nameAr) . '-' . time(),
                ];
                if ($imageName) $data['image'] = $imageName;

                if ($action === 'add_product') {
                    dbInsert('products', $data);
                    $successMsg = __t('تم إضافة المنتج بنجاح','Product added successfully');
                } else {
                    unset($data['slug']);
                    dbUpdate('products', $data, 'id=?', 'i', $pid);
                    $successMsg = __t('تم تعديل المنتج بنجاح','Product updated successfully');
                }
                logActivity($action, $nameAr);
            }
            $tab = 'products';
        }

        // ── Delete Product ──────────────────────────
        if ($action === 'delete_product') {
            $pid = (int)($_POST['product_id'] ?? 0);
            if ($pid) {
                dbUpdate('products',['is_active'=>0],'id=?','i',$pid);
                $successMsg = __t('تم حذف المنتج','Product deleted');
            }
            $tab = 'products';
        }

        // ── Add/Edit Category ───────────────────────
        if (in_array($action, ['add_category','edit_category'])) {
            $cid    = (int)($_POST['cat_id'] ?? 0);
            $nameAr = sanitize($_POST['name_ar'] ?? '');
            $nameEn = sanitize($_POST['name_en'] ?? '');
            $icon   = sanitize($_POST['icon'] ?? '📦');
            $isAct  = isset($_POST['is_active']) ? 1 : 0;

            if (!$nameAr) $errors[] = __t('الاسم مطلوب','Name required');
            if (!$errors) {
                $data = ['name_ar'=>$nameAr,'name_en'=>$nameEn?:$nameAr,'icon'=>$icon,'is_active'=>$isAct,'slug'=>slug($nameEn?:$nameAr)];
                if ($action === 'add_category') { dbInsert('categories',$data); $successMsg=__t('تم إضافة القسم','Category added'); }
                else { dbUpdate('categories',$data,'id=?','i',$cid); $successMsg=__t('تم تعديل القسم','Category updated'); }
            }
            $tab = 'categories';
        }

        // ── Delete Category ─────────────────────────
        if ($action === 'delete_category') {
            $cid = (int)($_POST['cat_id'] ?? 0);
            if ($cid) { dbUpdate('categories',['is_active'=>0],'id=?','i',$cid); $successMsg=__t('تم حذف القسم','Category deleted'); }
            $tab = 'categories';
        }

        // ── Add/Edit Zone ───────────────────────────
        if (in_array($action, ['add_zone','edit_zone'])) {
            $zid    = (int)($_POST['zone_id'] ?? 0);
            $nameAr = sanitize($_POST['name_ar'] ?? '');
            $nameEn = sanitize($_POST['name_en'] ?? '');
            $fee    = (float)($_POST['shipping_fee'] ?? 0);
            $sort   = (int)($_POST['sort_order'] ?? 0);
            $isAct  = isset($_POST['is_active']) ? 1 : 0;

            if (!$nameAr) $errors[] = __t('الاسم مطلوب','Name required');
            if ($fee < 0)  $errors[] = __t('رسوم الشحن يجب أن تكون 0 أو أكثر','Shipping fee must be >= 0');
            if (!$errors) {
                $data = ['name_ar'=>$nameAr,'name_en'=>$nameEn?:$nameAr,'shipping_fee'=>$fee,'sort_order'=>$sort,'is_active'=>$isAct];
                if ($action === 'add_zone') { dbInsert('shipping_zones',$data); $successMsg=__t('تم إضافة المنطقة','Zone added'); }
                else { dbUpdate('shipping_zones',$data,'id=?','i',$zid); $successMsg=__t('تم تعديل المنطقة','Zone updated'); }
            }
            $tab = 'zones';
        }

        // ── Delete Zone ─────────────────────────────
        if ($action === 'delete_zone') {
            $zid = (int)($_POST['zone_id'] ?? 0);
            if ($zid) { dbQuery("DELETE FROM shipping_zones WHERE id=?",'i',$zid); $successMsg=__t('تم حذف المنطقة','Zone deleted'); }
            $tab = 'zones';
        }

        // ── Add/Edit Coupon ─────────────────────────
        if (in_array($action,['add_coupon','edit_coupon'])) {
            $cid      = (int)($_POST['coupon_id'] ?? 0);
            $code     = strtoupper(sanitize($_POST['code'] ?? ''));
            $type     = in_array($_POST['type']??'',['percent','fixed']) ? $_POST['type'] : 'percent';
            $val      = (float)($_POST['value'] ?? 0);
            $minOrder = (float)($_POST['min_order'] ?? 0);
            $maxUses  = (int)($_POST['max_uses'] ?? 0);
            $expires  = sanitize($_POST['expires_at'] ?? '');
            $isAct    = isset($_POST['is_active']) ? 1 : 0;

            if (!$code) $errors[] = __t('الكود مطلوب','Code required');
            if ($val <= 0) $errors[] = __t('القيمة مطلوبة','Value required');
            if (!$errors) {
                $data = ['code'=>$code,'type'=>$type,'value'=>$val,'min_order'=>$minOrder,'max_uses'=>$maxUses,'expires_at'=>$expires?:null,'is_active'=>$isAct];
                if ($action==='add_coupon') { dbInsert('coupons',$data); $successMsg=__t('تم إضافة الكوبون','Coupon added'); }
                else { dbUpdate('coupons',$data,'id=?','i',$cid); $successMsg=__t('تم تعديل الكوبون','Coupon updated'); }
            }
            $tab = 'coupons';
        }

        // ── Delete Coupon ───────────────────────────
        if ($action==='delete_coupon') {
            $cid=(int)($_POST['coupon_id']??0);
            if($cid){ dbQuery("DELETE FROM coupons WHERE id=?",'i',$cid); $successMsg=__t('تم حذف الكوبون','Coupon deleted'); }
            $tab='coupons';
        }

        // ── Approve/Delete Review ───────────────────
        if ($action==='approve_review') {
            $rid=(int)($_POST['review_id']??0);
            dbUpdate('reviews',['is_approved'=>1],'id=?','i',$rid);
            $successMsg=__t('تم قبول التقييم','Review approved');
            $tab='reviews';
        }
        if ($action==='delete_review') {
            $rid=(int)($_POST['review_id']??0);
            dbQuery("DELETE FROM reviews WHERE id=?",'i',$rid);
            $successMsg=__t('تم حذف التقييم','Review deleted');
            $tab='reviews';
        }

        // ── Save Settings ───────────────────────────
        if ($action==='save_settings') {
            // خريطة: اسم الحقل في الفورم => المفتاح في قاعدة البيانات
            $fieldMap = [
                'site_name_ar'    => 'site_name_ar',
                'site_name_en'    => 'site_name_en',
                'site_tagline_ar' => 'site_tagline_ar',
                'site_tagline_en' => 'site_tagline_en',
                'currency_ar'     => 'currency_ar',
                'currency_en'     => 'currency_en',
                'shipping_fee'    => 'shipping_fee',
                'free_ship_min'   => 'free_ship_min',
                'site_phone'      => 'site_phone',
                'site_email'      => 'site_email',
                'site_address_ar' => 'site_address_ar',
                'facebook'        => 'facebook',
                'instagram'       => 'instagram',
                'twitter'         => 'twitter',
                'whatsapp'        => 'whatsapp',
            ];
            foreach ($fieldMap as $postKey => $dbKey) {
                $v = sanitize($_POST[$postKey] ?? '');
                $ex = dbFetchOne("SELECT id FROM settings WHERE key_name=?",'s',$dbKey);
                if ($ex) dbUpdate('settings',['value'=>$v],'key_name=?','s',$dbKey);
                else dbInsert('settings',['key_name'=>$dbKey,'value'=>$v]);
            }
            $successMsg=__t('تم حفظ الإعدادات بنجاح','Settings saved successfully');
            $tab='settings';
        }

        // ── Toggle User status ──────────────────────
        if ($action==='toggle_user') {
            $uid=(int)($_POST['user_id']??0);
            $u=dbFetchOne("SELECT is_active FROM users WHERE id=?",'i',$uid);
            if($u){ dbUpdate('users',['is_active'=>$u['is_active']?0:1],'id=?','i',$uid); $successMsg=__t('تم تحديث حالة المستخدم','User status updated'); }
            $tab='users';
        }
    }
}

// ══════════════════════════════════════════════════════
// DATA LOADING
// ══════════════════════════════════════════════════════
$stats = getDashboardStats();

// pagination helper
$perPage = 20;
$page    = max(1,(int)($_GET['page']??1));
$offset  = ($page-1)*$perPage;
$search  = sanitize($_GET['q']??'');

switch ($tab) {
    case 'orders':
        $statusFilter = $_GET['status'] ?? '';
        $whereOrders  = $statusFilter ? "WHERE status=?" : "WHERE 1";
        $paramsO      = $statusFilter ? [$statusFilter] : [];
        $totalOrders  = (int)(dbFetchOne("SELECT COUNT(*) c FROM orders $whereOrders", $statusFilter?'s':'', ...$paramsO)['c']??0);
        $orders = $statusFilter
            ? dbFetchAll("SELECT * FROM orders WHERE status=? ORDER BY created_at DESC LIMIT $perPage OFFSET $offset",'s',$statusFilter)
            : dbFetchAll("SELECT * FROM orders ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
        $editOrder = isset($_GET['edit_order']) ? dbFetchOne("SELECT * FROM orders WHERE id=?",'i',(int)$_GET['edit_order']) : null;
        if ($editOrder) $editOrder['items'] = dbFetchAll("SELECT * FROM order_items WHERE order_id=?",'i',$editOrder['id']);
        break;
    case 'products':
        $catFilter = (int)($_GET['cat'] ?? 0);
        $searchWhere = $search ? "AND (p.name_ar LIKE '%$search%' OR p.name_en LIKE '%$search%')" : '';
        $catWhere    = $catFilter ? "AND p.category_id=$catFilter" : '';
        $totalProds  = (int)(dbFetchOne("SELECT COUNT(*) c FROM products p WHERE p.is_active=1 $searchWhere $catWhere")['c']??0);
        $products    = dbFetchAll("SELECT p.*,c.name_ar cat_ar,c.name_en cat_en FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.is_active=1 $searchWhere $catWhere ORDER BY p.created_at DESC LIMIT $perPage OFFSET $offset");
        $editProduct = isset($_GET['edit']) ? dbFetchOne("SELECT * FROM products WHERE id=?",'i',(int)$_GET['edit']) : null;
        $allCats     = getCategories();
        break;
    case 'categories':
        $categories  = dbFetchAll("SELECT c.*,(SELECT COUNT(*) FROM products WHERE category_id=c.id AND is_active=1) prod_count FROM categories c ORDER BY c.sort_order");
        $editCat     = isset($_GET['edit']) ? dbFetchOne("SELECT * FROM categories WHERE id=?",'i',(int)$_GET['edit']) : null;
        break;
    case 'coupons':
        $coupons     = dbFetchAll("SELECT * FROM coupons ORDER BY created_at DESC");
        $editCoupon  = isset($_GET['edit']) ? dbFetchOne("SELECT * FROM coupons WHERE id=?",'i',(int)$_GET['edit']) : null;
        break;
    case 'reviews':
        $pendingOnly = isset($_GET['pending']);
        $reviews     = $pendingOnly
            ? dbFetchAll("SELECT r.*,p.name_ar pname FROM reviews r LEFT JOIN products p ON r.product_id=p.id WHERE r.is_approved=0 ORDER BY r.created_at DESC")
            : dbFetchAll("SELECT r.*,p.name_ar pname FROM reviews r LEFT JOIN products p ON r.product_id=p.id ORDER BY r.created_at DESC LIMIT 50");
        break;
    case 'users':
        $totalUsers = (int)(dbFetchOne("SELECT COUNT(*) c FROM users WHERE role='customer'")['c']??0);
        $users      = dbFetchAll("SELECT * FROM users WHERE role='customer' ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
        break;
    case 'zones':
        $zones    = dbFetchAll("SELECT * FROM shipping_zones ORDER BY sort_order ASC, id ASC");
        $editZone = isset($_GET['edit']) ? dbFetchOne("SELECT * FROM shipping_zones WHERE id=?",'i',(int)$_GET['edit']) : null;
        break;
    case 'settings':
        $settings = getAllSettings();
        break;
}

$pageTitle = __t('لوحة التحكم','Admin Panel');
?>
<!DOCTYPE html>
<html lang="<?= isAr() ? 'ar' : 'en' ?>" dir="<?= appDir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= __t('الإدارة','Admin') ?> | <?= e(siteName()) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;900&family=Outfit:wght@400;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --primary:#e63946;--primary-dark:#c1121f;--primary-bg:#fff0f1;
  --dark:#1a1a2e;--dark2:#16213e;--sidebar-w:240px;
  --text:#212529;--gray:#6c757d;--border:#dee2e6;
  --gray-light:#f8f9fa;--shadow:0 2px 12px rgba(0,0,0,.08);
  --radius:10px;--transition:all .2s ease;
  font-family:<?= isAr()?'"Tajawal"':'Outfit' ?>,sans-serif;
}
body{background:var(--gray-light);color:var(--text);min-height:100vh;display:flex}
a{text-decoration:none;color:inherit}
button{border:none;cursor:pointer;font-family:inherit}
input,select,textarea{font-family:inherit}

/* ── Sidebar ── */
.adm-sidebar{
  width:var(--sidebar-w);background:var(--dark);
  min-height:100vh;position:fixed;top:0;
  <?= isAr()?'right':'left' ?>:0;z-index:100;
  display:flex;flex-direction:column;
  transition:transform .3s ease;
}
.adm-logo{
  padding:20px 20px 16px;border-bottom:1px solid rgba(255,255,255,.1);
  display:flex;align-items:center;gap:12px;
}
.adm-logo-icon{
  width:40px;height:40px;background:var(--primary);border-radius:8px;
  display:flex;align-items:center;justify-content:center;
  font-size:1.2rem;font-weight:900;color:white;flex-shrink:0;
}
.adm-logo-text{color:white;font-size:.9rem;font-weight:700;line-height:1.3}
.adm-logo-text small{display:block;color:rgba(255,255,255,.45);font-size:.7rem;font-weight:400}

.adm-nav{flex:1;padding:12px 0;overflow-y:auto}
.adm-nav-section{padding:8px 16px 4px;font-size:.68rem;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.08em}
.adm-nav-link{
  display:flex;align-items:center;gap:10px;
  padding:11px 20px;color:rgba(255,255,255,.7);font-size:.87rem;
  transition:var(--transition);position:relative;
}
.adm-nav-link:hover{color:white;background:rgba(255,255,255,.06)}
.adm-nav-link.active{color:white;background:rgba(230,57,70,.25);border-<?= isAr()?'right':'left' ?>:3px solid var(--primary)}
.adm-nav-link .nav-icon{width:20px;text-align:center;font-size:1rem}
.adm-nav-badge{background:var(--primary);color:white;font-size:.6rem;padding:2px 6px;border-radius:10px;margin-<?= isAr()?'right':'left' ?>:auto}

.adm-sidebar-footer{padding:16px 20px;border-top:1px solid rgba(255,255,255,.1)}
.adm-user-mini{display:flex;align-items:center;gap:10px;color:rgba(255,255,255,.7);font-size:.82rem}
.adm-user-mini .ava{width:32px;height:32px;background:var(--primary);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;color:white;flex-shrink:0}

/* ── Main ── */
.adm-main{
  margin-<?= isAr()?'right':'left' ?>:var(--sidebar-w);
  flex:1;min-height:100vh;display:flex;flex-direction:column;
}
.adm-topbar{
  background:white;border-bottom:1px solid var(--border);
  padding:14px 28px;display:flex;align-items:center;
  justify-content:space-between;gap:16px;flex-wrap:wrap;
  position:sticky;top:0;z-index:50;
}
.adm-topbar-title{font-size:1.1rem;font-weight:800;color:var(--dark)}
.adm-topbar-actions{display:flex;align-items:center;gap:10px}
.adm-content{padding:24px 28px;flex:1}

/* ── Cards & Boxes ── */
.adm-card{background:white;border-radius:var(--radius);border:1px solid var(--border);overflow:hidden}
.adm-card-header{
  display:flex;align-items:center;justify-content:space-between;
  padding:16px 20px;border-bottom:1px solid var(--border);
}
.adm-card-header h3{font-size:.95rem;font-weight:800;color:var(--dark)}
.adm-card-body{padding:20px}

/* ── Stats Grid ── */
.adm-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
.stat-box{
  background:white;border-radius:var(--radius);border:1px solid var(--border);
  padding:20px;display:flex;align-items:center;gap:16px;
  transition:var(--transition);
}
.stat-box:hover{box-shadow:var(--shadow);border-color:var(--primary)}
.stat-icon{
  width:48px;height:48px;border-radius:10px;
  display:flex;align-items:center;justify-content:center;
  font-size:1.4rem;flex-shrink:0;
}
.stat-icon.red    {background:#fee2e2}
.stat-icon.blue   {background:#dbeafe}
.stat-icon.green  {background:#d1fae5}
.stat-icon.yellow {background:#fef3c7}
.stat-details{}
.stat-num  {font-size:1.5rem;font-weight:900;color:var(--dark);display:block}
.stat-label{font-size:.75rem;color:var(--gray)}

/* ── Table ── */
.adm-table-wrap{overflow-x:auto}
.adm-table{width:100%;border-collapse:collapse;font-size:.85rem}
.adm-table th{
  background:var(--gray-light);padding:11px 16px;
  text-align:<?= isAr()?'right':'left' ?>;
  font-weight:700;color:var(--dark);white-space:nowrap;
  border-bottom:2px solid var(--border);
}
.adm-table td{
  padding:11px 16px;border-bottom:1px solid var(--gray-light);
  vertical-align:middle;
}
.adm-table tr:last-child td{border-bottom:none}
.adm-table tr:hover td{background:#fafafa}

/* ── Buttons ── */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:8px 18px;border-radius:30px;font-size:.84rem;font-weight:700;transition:var(--transition);cursor:pointer;border:none;font-family:inherit}
.btn-primary{background:var(--primary);color:white}
.btn-primary:hover{background:var(--primary-dark)}
.btn-outline{background:transparent;border:1.5px solid var(--border);color:var(--text)}
.btn-outline:hover{border-color:var(--primary);color:var(--primary)}
.btn-dark{background:var(--dark);color:white}
.btn-dark:hover{background:#111}
.btn-green{background:#198754;color:white}
.btn-green:hover{background:#146c43}
.btn-red{background:#dc3545;color:white}
.btn-red:hover{background:#b02a37}
.btn-sm{padding:5px 12px;font-size:.78rem}
.btn-xs{padding:4px 9px;font-size:.72rem}
.btn-block{width:100%}
.btn-icon{width:32px;height:32px;padding:0;border-radius:8px}

/* ── Form ── */
.form-group{margin-bottom:16px}
.form-label{display:block;font-size:.82rem;font-weight:600;color:var(--text);margin-bottom:6px}
.form-label.required::after{content:" *";color:var(--primary)}
.form-control{width:100%;padding:9px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:.86rem;outline:none;transition:border-color .2s;font-family:inherit;background:white;color:var(--text)}
.form-control:focus{border-color:var(--primary)}
textarea.form-control{resize:vertical}
.form-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px}

/* ── Status badges ── */
.status-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700}
.status-pending    {background:#fff3cd;color:#856404}
.status-confirmed  {background:#cfe2ff;color:#084298}
.status-processing {background:#d1ecf1;color:#0c5460}
.status-shipped    {background:#d4edda;color:#155724}
.status-delivered  {background:#d1fae5;color:#065f46}
.status-cancelled  {background:#fee2e2;color:#991b1b}

/* ── Alert ── */
.alert{padding:12px 16px;border-radius:8px;font-size:.86rem;margin-bottom:16px;display:flex;align-items:center;gap:10px}
.alert-success{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0}
.alert-error  {background:#fee2e2;color:#991b1b;border:1px solid #fca5a5}

/* ── Modal ── */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;padding:20px}
.modal-overlay.open{display:flex}
.modal-box{background:white;border-radius:var(--radius);max-width:680px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.modal-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border)}
.modal-header h3{font-size:1rem;font-weight:800}
.modal-close{width:32px;height:32px;border-radius:50%;background:var(--gray-light);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:1rem}
.modal-close:hover{background:var(--border)}
.modal-body{padding:20px}
.modal-footer{padding:16px 20px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end}

/* ── Toggle ── */
.toggle-sw{position:relative;display:inline-block;width:42px;height:24px}
.toggle-sw input{opacity:0;width:0;height:0}
.toggle-slider{position:absolute;inset:0;background:#ccc;border-radius:24px;cursor:pointer;transition:.3s}
.toggle-slider::before{content:'';position:absolute;width:18px;height:18px;<?= isAr()?'right':'left' ?>:3px;bottom:3px;background:white;border-radius:50%;transition:.3s}
.toggle-sw input:checked+.toggle-slider{background:var(--primary)}
.toggle-sw input:checked+.toggle-slider::before{transform:translateX(<?= isAr()?'-18px':'18px' ?>)}

/* ── Product img ── */
.prod-thumb{width:44px;height:44px;object-fit:cover;border-radius:6px;border:1px solid var(--border)}

/* ── Responsive ── */
.adm-mobile-toggle{display:none;width:36px;height:36px;background:var(--dark);color:white;border-radius:8px;align-items:center;justify-content:center;font-size:1.1rem;cursor:pointer}
@media(max-width:900px){
  .adm-sidebar{transform:translateX(<?= isAr()?'100%':'-100%' ?>)}
  .adm-sidebar.open{transform:translateX(0)}
  .adm-main{margin:0}
  .adm-stats{grid-template-columns:repeat(2,1fr)}
  .adm-mobile-toggle{display:flex}
}
@media(max-width:600px){
  .adm-stats{grid-template-columns:1fr}
  .adm-content{padding:16px}
  .form-grid-2,.form-grid-3{grid-template-columns:1fr}
}
</style>
</head>
<body>

<!-- ════════════════════════════════════════
     SIDEBAR
════════════════════════════════════════ -->
<aside class="adm-sidebar" id="admSidebar">
  <div class="adm-logo">
    <div class="adm-logo-icon"><?= mb_substr(siteName(),0,1) ?></div>
    <div class="adm-logo-text"><?= e(siteName()) ?><small><?= __t('لوحة التحكم','Admin Panel') ?></small></div>
  </div>

  <nav class="adm-nav">
    <div class="adm-nav-section"><?= __t('الرئيسية','Main') ?></div>
    <?php
    $pendingCount = (int)(dbFetchOne("SELECT COUNT(*) c FROM orders WHERE status='pending'")['c']??0);
    $pendingReviews = (int)(dbFetchOne("SELECT COUNT(*) c FROM reviews WHERE is_approved=0")['c']??0);
    $navLinks = [
      ['dashboard', '📊', __t('إحصائيات','Dashboard')],
      ['orders',    '📦', __t('الطلبات','Orders'),    $pendingCount],
      ['products',  '🛍', __t('المنتجات','Products')],
      ['categories','📁', __t('الأقسام','Categories')],
      ['zones',     '📍', __t('مناطق التوصيل','Delivery Zones')],
      ['coupons',   '🏷', __t('الكوبونات','Coupons')],
      ['reviews',   '💬', __t('التقييمات','Reviews'),  $pendingReviews],
      ['users',     '👥', __t('المستخدمون','Users')],
      ['settings',  '⚙',  __t('الإعدادات','Settings')],
    ];
    foreach ($navLinks as $nl): ?>
      <a href="admin.php?tab=<?= $nl[0] ?>" class="adm-nav-link <?= $tab===$nl[0]?'active':'' ?>">
        <span class="nav-icon"><?= $nl[1] ?></span>
        <span><?= $nl[2] ?></span>
        <?php if (!empty($nl[3])): ?>
          <span class="adm-nav-badge"><?= $nl[3] ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>

    <div class="adm-nav-section" style="margin-top:8px"><?= __t('الموقع','Site') ?></div>
    <a href="index.php" target="_blank" class="adm-nav-link">
      <span class="nav-icon">🌐</span><span><?= __t('عرض الموقع','View Site') ?></span>
    </a>
    <a href="logout.php" class="adm-nav-link">
      <span class="nav-icon">↩</span><span><?= __t('خروج','Logout') ?></span>
    </a>
  </nav>

  <div class="adm-sidebar-footer">
    <div class="adm-user-mini">
      <div class="ava"><?= mb_substr(currentUser()['name'],0,1) ?></div>
      <div><strong style="color:white;font-size:.82rem"><?= e(currentUser()['name']) ?></strong><br><small><?= __t('مدير','Admin') ?></small></div>
    </div>
  </div>
</aside>

<!-- ════════════════════════════════════════
     MAIN
════════════════════════════════════════ -->
<div class="adm-main">

  <!-- Topbar -->
  <div class="adm-topbar">
    <div style="display:flex;align-items:center;gap:12px">
      <button class="adm-mobile-toggle" onclick="document.getElementById('admSidebar').classList.toggle('open')">☰</button>
      <div class="adm-topbar-title">
        <?= ['dashboard'=>'📊 '.__t('الإحصائيات','Dashboard'), 'orders'=>'📦 '.__t('الطلبات','Orders'),
             'products'=>'🛍 '.__t('المنتجات','Products'), 'categories'=>'📁 '.__t('الأقسام','Categories'),
             'zones'=>'📍 '.__t('مناطق التوصيل','Delivery Zones'),
             'coupons'=>'🏷 '.__t('الكوبونات','Coupons'), 'reviews'=>'💬 '.__t('التقييمات','Reviews'),
             'users'=>'👥 '.__t('المستخدمون','Users'), 'settings'=>'⚙ '.__t('الإعدادات','Settings')][$tab] ?? '' ?>
      </div>
    </div>
    <div class="adm-topbar-actions">
      <span style="font-size:.8rem;color:var(--gray)"><?= date('d M Y') ?></span>
      <a href="index.php" target="_blank" class="btn btn-outline btn-sm">🌐 <?= __t('الموقع','Site') ?></a>
    </div>
  </div>

  <div class="adm-content">

    <?php if ($successMsg): ?>
      <div class="alert alert-success">✓ <?= e($successMsg) ?></div>
    <?php endif; ?>
    <?php foreach ($errors as $e_): ?>
      <div class="alert alert-error">✕ <?= e($e_) ?></div>
    <?php endforeach; ?>

    <!-- ════ DASHBOARD ════ -->
    <?php if ($tab === 'dashboard'): ?>
    <div class="adm-stats">
      <div class="stat-box">
        <div class="stat-icon red">📦</div>
        <div class="stat-details">
          <span class="stat-num"><?= $stats['orders_total'] ?></span>
          <span class="stat-label"><?= __t('إجمالي الطلبات','Total Orders') ?></span>
        </div>
      </div>
      <div class="stat-box">
        <div class="stat-icon green">💰</div>
        <div class="stat-details">
          <span class="stat-num"><?= formatPrice($stats['revenue_total']) ?></span>
          <span class="stat-label"><?= __t('إجمالي الإيرادات','Total Revenue') ?></span>
        </div>
      </div>
      <div class="stat-box">
        <div class="stat-icon blue">🛍</div>
        <div class="stat-details">
          <span class="stat-num"><?= $stats['products_total'] ?></span>
          <span class="stat-label"><?= __t('المنتجات','Products') ?></span>
        </div>
      </div>
      <div class="stat-box">
        <div class="stat-icon yellow">👥</div>
        <div class="stat-details">
          <span class="stat-num"><?= $stats['customers_total'] ?></span>
          <span class="stat-label"><?= __t('العملاء','Customers') ?></span>
        </div>
      </div>
    </div>

    <!-- Quick stats row -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px">
      <div class="adm-card">
        <div class="adm-card-header"><h3>📋 <?= __t('حالة الطلبات','Orders by Status') ?></h3></div>
        <div class="adm-card-body" style="padding:12px 20px">
          <?php foreach (getOrderStatuses() as $st => $lbl):
            $cnt = (int)(dbFetchOne("SELECT COUNT(*) c FROM orders WHERE status=?",'s',$st)['c']??0);
            if (!$cnt) continue;
          ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px dashed var(--border)">
            <span class="status-badge status-<?= $st ?>"><?= orderStatusLabel($st) ?></span>
            <strong><?= $cnt ?></strong>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="adm-card" style="grid-column:span 2">
        <div class="adm-card-header"><h3>📦 <?= __t('أحدث الطلبات','Latest Orders') ?></h3><a href="admin.php?tab=orders" class="btn btn-outline btn-sm"><?= __t('الكل','All') ?></a></div>
        <div class="adm-table-wrap">
          <table class="adm-table">
            <thead><tr><th><?= __t('رقم الطلب','Order') ?></th><th><?= __t('العميل','Customer') ?></th><th><?= __t('الإجمالي','Total') ?></th><th><?= __t('الحالة','Status') ?></th><th><?= __t('التاريخ','Date') ?></th></tr></thead>
            <tbody>
            <?php $latestOrders = dbFetchAll("SELECT * FROM orders ORDER BY created_at DESC LIMIT 8");
            foreach ($latestOrders as $o): ?>
              <tr>
                <td><a href="admin.php?tab=orders&edit_order=<?= $o['id'] ?>" style="color:var(--primary);font-weight:700"><?= e($o['order_number']) ?></a></td>
                <td><?= e($o['name']) ?><br><small style="color:var(--gray)"><?= e($o['phone']) ?></small></td>
                <td><strong><?= formatPrice((float)$o['total']) ?></strong></td>
                <td><span class="status-badge status-<?= $o['status'] ?>"><?= orderStatusLabel($o['status']) ?></span></td>
                <td style="font-size:.78rem;color:var(--gray)"><?= date('d M',strtotime($o['created_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Low stock alert -->
    <?php $lowStock = dbFetchAll("SELECT * FROM products WHERE stock<=5 AND is_active=1 ORDER BY stock ASC LIMIT 6");
    if ($lowStock): ?>
    <div class="adm-card">
      <div class="adm-card-header"><h3>⚠ <?= __t('مخزون منخفض','Low Stock Alert') ?></h3></div>
      <div class="adm-table-wrap">
        <table class="adm-table">
          <thead><tr><th><?= __t('المنتج','Product') ?></th><th><?= __t('المخزون','Stock') ?></th><th></th></tr></thead>
          <tbody>
          <?php foreach ($lowStock as $p): ?>
            <tr>
              <td><?= e($p['name_ar']) ?></td>
              <td><span style="color:<?= $p['stock']==0?'#dc3545':'#d97706' ?>;font-weight:700"><?= $p['stock'] ?></span></td>
              <td><a href="admin.php?tab=products&edit=<?= $p['id'] ?>" class="btn btn-outline btn-xs"><?= __t('تعديل','Edit') ?></a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- ════ ORDERS ════ -->
    <?php elseif ($tab === 'orders'): ?>

    <?php if ($editOrder): ?>
    <!-- Order Detail/Edit -->
    <div style="margin-bottom:16px">
      <a href="admin.php?tab=orders" class="btn btn-outline btn-sm">← <?= __t('الطلبات','Orders') ?></a>
    </div>
    <div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start">
      <div>
        <div class="adm-card" style="margin-bottom:20px">
          <div class="adm-card-header">
            <h3>📦 <?= __t('الطلب','Order') ?> #<?= e($editOrder['order_number']) ?></h3>
            <span class="status-badge status-<?= $editOrder['status'] ?>"><?= orderStatusLabel($editOrder['status']) ?></span>
          </div>
          <div class="adm-table-wrap">
            <table class="adm-table">
              <thead><tr><th><?= __t('المنتج','Product') ?></th><th><?= __t('السعر','Price') ?></th><th><?= __t('الكمية','Qty') ?></th><th><?= __t('الإجمالي','Total') ?></th></tr></thead>
              <tbody>
              <?php foreach ($editOrder['items'] as $item): ?>
                <tr>
                  <td><?= e(isAr()?$item['name_ar']:$item['name_en']) ?></td>
                  <td><?= formatPrice((float)$item['unit_price']) ?></td>
                  <td><?= (int)$item['quantity'] ?></td>
                  <td><strong><?= formatPrice((float)$item['total_price']) ?></strong></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="adm-card">
          <div class="adm-card-header"><h3>📍 <?= __t('بيانات التوصيل','Delivery Info') ?></h3></div>
          <div class="adm-card-body" style="font-size:.88rem;line-height:2">
            <strong><?= e($editOrder['name']) ?></strong> | <?= e($editOrder['phone']) ?><br>
            <?= e($editOrder['address']) ?>, <?= e($editOrder['city']) ?><br>
            <?php if ($editOrder['notes']): ?><em style="color:var(--gray)"><?= e($editOrder['notes']) ?></em><?php endif; ?>
          </div>
        </div>
      </div>
      <div>
        <div class="adm-card" style="margin-bottom:16px">
          <div class="adm-card-header"><h3><?= __t('ملخص المبالغ','Amounts') ?></h3></div>
          <div class="adm-card-body">
            <div style="font-size:.86rem;display:flex;flex-direction:column;gap:8px">
              <div style="display:flex;justify-content:space-between"><span><?= __t('المجموع','Subtotal') ?></span><strong><?= formatPrice((float)$editOrder['subtotal']) ?></strong></div>
              <div style="display:flex;justify-content:space-between"><span><?= __t('الشحن','Shipping') ?></span><strong><?= formatPrice((float)$editOrder['shipping_fee']) ?></strong></div>
              <?php if ($editOrder['discount']>0): ?><div style="display:flex;justify-content:space-between"><span><?= __t('خصم','Discount') ?></span><strong style="color:#198754">-<?= formatPrice((float)$editOrder['discount']) ?></strong></div><?php endif; ?>
              <div style="display:flex;justify-content:space-between;border-top:2px solid var(--border);padding-top:8px;font-size:1rem"><span><?= __t('الإجمالي','Total') ?></span><strong style="color:var(--primary)"><?= formatPrice((float)$editOrder['total']) ?></strong></div>
            </div>
          </div>
        </div>
        <div class="adm-card">
          <div class="adm-card-header"><h3>⚙ <?= __t('تحديث الحالة','Update Status') ?></h3></div>
          <div class="adm-card-body">
            <form method="POST">
              <?= csrfInput() ?>
              <input type="hidden" name="action" value="update_order_status">
              <input type="hidden" name="order_id" value="<?= $editOrder['id'] ?>">
              <div class="form-group">
                <label class="form-label"><?= __t('الحالة','Status') ?></label>
                <select name="status" class="form-control">
                  <?php foreach (getOrderStatuses() as $st=>$lbl): ?>
                    <option value="<?= $st ?>" <?= $editOrder['status']===$st?'selected':'' ?>><?= $lbl ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label"><?= __t('رقم التتبع','Tracking #') ?></label>
                <input type="text" name="tracking_no" class="form-control" value="<?= e($editOrder['tracking_no']??'') ?>">
              </div>
              <button type="submit" class="btn btn-primary btn-block"><?= __t('حفظ','Save') ?></button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <?php else: ?>
    <!-- Orders List -->
    <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:center">
      <?php foreach ([''=>__t('الكل','All')]+getOrderStatuses() as $st=>$lbl): ?>
        <a href="admin.php?tab=orders<?= $st?'&status='.$st:'' ?>"
           class="btn btn-sm <?= ($_GET['status']??'')===$st?'btn-primary':'btn-outline' ?>">
          <?= is_array($lbl) ? orderStatusLabel($st) : e($lbl) ?>
          <?php $cnt=(int)(dbFetchOne("SELECT COUNT(*) c FROM orders".($st?" WHERE status=?":" WHERE 1"), $st?'s':'', ...($st?[$st]:[]))['c']??0); ?>
          <span style="background:rgba(255,255,255,.25);padding:1px 6px;border-radius:10px;font-size:.7rem"><?= $cnt ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="adm-card">
      <div class="adm-table-wrap">
        <table class="adm-table">
          <thead><tr><th><?= __t('رقم الطلب','Order #') ?></th><th><?= __t('العميل','Customer') ?></th><th><?= __t('المنتجات','Items') ?></th><th><?= __t('الإجمالي','Total') ?></th><th><?= __t('الحالة','Status') ?></th><th><?= __t('التاريخ','Date') ?></th><th></th></tr></thead>
          <tbody>
          <?php foreach ($orders as $o): ?>
            <tr>
              <td><strong style="color:var(--primary)"><?= e($o['order_number']) ?></strong></td>
              <td><?= e($o['name']) ?><br><small style="color:var(--gray)"><?= e($o['phone']) ?></small></td>
              <td><a href="admin.php?tab=orders&edit_order=<?= $o['id'] ?>" style="color:var(--primary)"><?= __t('عرض','View') ?></a></td>
              <td><strong><?= formatPrice((float)$o['total']) ?></strong></td>
              <td><span class="status-badge status-<?= $o['status'] ?>"><?= orderStatusLabel($o['status']) ?></span></td>
              <td style="font-size:.78rem;color:var(--gray)"><?= date('d M Y',strtotime($o['created_at'])) ?></td>
              <td><a href="admin.php?tab=orders&edit_order=<?= $o['id'] ?>" class="btn btn-outline btn-xs"><?= __t('تفاصيل','Details') ?></a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- ════ PRODUCTS ════ -->
    <?php elseif ($tab === 'products'): ?>
    <div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;align-items:center">
      <form method="GET" style="display:flex;gap:8px;flex:1">
        <input type="hidden" name="tab" value="products">
        <input type="text" name="q" class="form-control" value="<?= e($search) ?>" placeholder="<?= __t('بحث...','Search...') ?>" style="max-width:240px">
        <button type="submit" class="btn btn-primary btn-sm"><?= __t('بحث','Search') ?></button>
        <?php if ($search): ?><a href="admin.php?tab=products" class="btn btn-outline btn-sm">✕</a><?php endif; ?>
      </form>
      <button class="btn btn-primary" onclick="openModal('productModal')">+ <?= __t('منتج جديد','Add Product') ?></button>
    </div>

    <div class="adm-card">
      <div class="adm-table-wrap">
        <table class="adm-table">
          <thead><tr><th></th><th><?= __t('المنتج','Product') ?></th><th><?= __t('القسم','Category') ?></th><th><?= __t('السعر','Price') ?></th><th><?= __t('المخزون','Stock') ?></th><th><?= __t('الحالة','Status') ?></th><th></th></tr></thead>
          <tbody>
          <?php foreach ($products as $p): ?>
            <tr>
              <td><img src="<?= e(productImage($p)) ?>" class="prod-thumb" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDQiIGhlaWdodD0iNDQiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjQ0IiBoZWlnaHQ9IjQ0IiBmaWxsPSIjZjBmMGYwIi8+PC9zdmc+'"></td>
              <td>
                <strong style="display:block"><?= e($p['name_ar']) ?></strong>
                <small style="color:var(--gray)"><?= e($p['name_en']) ?></small>
              </td>
              <td><?= e($p['cat_ar']) ?></td>
              <td>
                <strong style="color:var(--primary)"><?= formatPrice((float)productPrice($p)) ?></strong>
                <?php if ($p['sale_price']): ?><br><small style="text-decoration:line-through;color:var(--gray)"><?= formatPrice((float)$p['price']) ?></small><?php endif; ?>
              </td>
              <td><span style="color:<?= $p['stock']==0?'#dc3545':($p['stock']<=5?'#d97706':'#198754') ?>;font-weight:700"><?= (int)$p['stock'] ?></span></td>
              <td>
                <?php if ($p['is_featured']): ?><span style="font-size:.7rem;background:#fef3c7;color:#92400e;padding:2px 7px;border-radius:10px">⭐ <?= __t('مميز','Featured') ?></span><?php endif; ?>
                <?php if ($p['is_new']): ?><span style="font-size:.7rem;background:#dbeafe;color:#1e40af;padding:2px 7px;border-radius:10px">🆕 <?= __t('جديد','New') ?></span><?php endif; ?>
              </td>
              <td>
                <div style="display:flex;gap:6px">
                  <a href="admin.php?tab=products&edit=<?= $p['id'] ?>" class="btn btn-outline btn-xs"><?= __t('تعديل','Edit') ?></a>
                  <form method="POST" style="display:inline" onsubmit="return confirm('<?= __t("حذف؟","Delete?") ?>')">
                    <?= csrfInput() ?>
                    <input type="hidden" name="action" value="delete_product">
                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                    <button class="btn btn-red btn-xs">🗑</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Add/Edit Product Modal/Form -->
    <?php if ($editProduct || isset($_GET['add'])): ?>
    <div class="adm-card" style="margin-top:20px">
      <div class="adm-card-header">
        <h3><?= $editProduct ? __t('تعديل المنتج','Edit Product') : __t('إضافة منتج','Add Product') ?></h3>
        <a href="admin.php?tab=products" class="btn btn-outline btn-sm">✕ <?= __t('إلغاء','Cancel') ?></a>
      </div>
      <div class="adm-card-body">
        <form method="POST" enctype="multipart/form-data">
          <?= csrfInput() ?>
          <input type="hidden" name="action" value="<?= $editProduct?'edit_product':'add_product' ?>">
          <?php if ($editProduct): ?><input type="hidden" name="product_id" value="<?= $editProduct['id'] ?>"><?php endif; ?>
          <div class="form-grid-2">
            <div class="form-group"><label class="form-label required"><?= __t('الاسم بالعربية','Name (AR)') ?></label><input type="text" name="name_ar" class="form-control" value="<?= e($editProduct['name_ar']??'') ?>" required></div>
            <div class="form-group"><label class="form-label"><?= __t('الاسم بالإنجليزية','Name (EN)') ?></label><input type="text" name="name_en" class="form-control" value="<?= e($editProduct['name_en']??'') ?>"></div>
            <div class="form-group"><label class="form-label required"><?= __t('القسم','Category') ?></label><select name="category_id" class="form-control" required><option value=""><?= __t('اختر','Choose') ?></option><?php foreach ($allCats as $c): ?><option value="<?= $c['id'] ?>" <?= ($editProduct['category_id']??'')==$c['id']?'selected':'' ?>><?= e($c['name_ar']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label class="form-label"><?= __t('كود المنتج','SKU') ?></label><input type="text" name="sku" class="form-control" value="<?= e($editProduct['sku']??'') ?>"></div>
            <div class="form-group"><label class="form-label required"><?= __t('السعر','Price') ?></label><input type="number" name="price" step="0.01" class="form-control" value="<?= e($editProduct['price']??'') ?>" required></div>
            <div class="form-group"><label class="form-label"><?= __t('سعر الخصم','Sale Price') ?></label><input type="number" name="sale_price" step="0.01" class="form-control" value="<?= e($editProduct['sale_price']??'') ?>"></div>
            <div class="form-group"><label class="form-label"><?= __t('المخزون','Stock') ?></label><input type="number" name="stock" class="form-control" value="<?= e($editProduct['stock']??0) ?>"></div>
            <div class="form-group"><label class="form-label"><?= __t('الصورة','Image') ?></label><input type="file" name="image" class="form-control" accept="image/*"></div>
            <div class="form-group" style="grid-column:span 2"><label class="form-label"><?= __t('الوصف بالعربية','Description (AR)') ?></label><textarea name="desc_ar" class="form-control" rows="3"><?= e($editProduct['desc_ar']??$editProduct['description_ar']??'') ?></textarea></div>
            <div class="form-group" style="grid-column:span 2"><label class="form-label"><?= __t('الوصف بالإنجليزية','Description (EN)') ?></label><textarea name="desc_en" class="form-control" rows="3"><?= e($editProduct['desc_en']??$editProduct['description_en']??'') ?></textarea></div>
            <div class="form-group" style="grid-column:span 2"><label class="form-label"><?= __t('الوسوم (مفصولة بفاصلة)','Tags (comma separated)') ?></label><input type="text" name="tags" class="form-control" value="<?= e($editProduct['tags']??'') ?>"></div>
          </div>
          <div style="display:flex;gap:20px;flex-wrap:wrap;margin-bottom:16px">
            <label class="qcheck"><input type="checkbox" name="is_featured" value="1" <?= !empty($editProduct['is_featured'])?'checked':'' ?>> <span>⭐ <?= __t('مميز','Featured') ?></span></label>
            <label class="qcheck"><input type="checkbox" name="is_new" value="1" <?= !empty($editProduct['is_new'])?'checked':'' ?>> <span>🆕 <?= __t('جديد','New') ?></span></label>
            <label class="qcheck"><input type="checkbox" name="is_active" value="1" <?= ($editProduct['is_active']??1)?'checked':'' ?>> <span>✓ <?= __t('مفعل','Active') ?></span></label>
          </div>
          <button type="submit" class="btn btn-primary"><?= $editProduct?__t('حفظ التعديلات','Save Changes'):__t('إضافة المنتج','Add Product') ?></button>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- ════ CATEGORIES ════ -->
    <?php elseif ($tab === 'categories'): ?>
    <div style="display:flex;justify-content:flex-end;margin-bottom:16px">
      <a href="admin.php?tab=categories&add=1" class="btn btn-primary">+ <?= __t('قسم جديد','Add Category') ?></a>
    </div>
    <div style="display:grid;grid-template-columns:<?= ($editCat||isset($_GET['add']))?'1fr 380px':'1fr' ?>;gap:20px;align-items:start">
      <div class="adm-card">
        <div class="adm-table-wrap">
          <table class="adm-table">
            <thead><tr><th><?= __t('الأيقونة','Icon') ?></th><th><?= __t('الاسم','Name') ?></th><th><?= __t('المنتجات','Products') ?></th><th><?= __t('الحالة','Status') ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($categories as $cat): ?>
              <tr>
                <td style="font-size:1.5rem"><?= e($cat['icon']??'📦') ?></td>
                <td><strong><?= e($cat['name_ar']) ?></strong><br><small style="color:var(--gray)"><?= e($cat['name_en']) ?></small></td>
                <td><?= (int)$cat['prod_count'] ?></td>
                <td><span style="color:<?= $cat['is_active']?'#198754':'#dc3545' ?>"><?= $cat['is_active']?__t('مفعل','Active'):__t('معطل','Inactive') ?></span></td>
                <td>
                  <div style="display:flex;gap:6px">
                    <a href="admin.php?tab=categories&edit=<?= $cat['id'] ?>" class="btn btn-outline btn-xs"><?= __t('تعديل','Edit') ?></a>
                    <form method="POST" style="display:inline" onsubmit="return confirm('<?= __t("حذف؟","Delete?") ?>')">
                      <?= csrfInput() ?>
                      <input type="hidden" name="action" value="delete_category">
                      <input type="hidden" name="cat_id" value="<?= $cat['id'] ?>">
                      <button class="btn btn-red btn-xs">🗑</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php if ($editCat || isset($_GET['add'])): ?>
      <div class="adm-card">
        <div class="adm-card-header"><h3><?= $editCat?__t('تعديل القسم','Edit Category'):__t('إضافة قسم','Add Category') ?></h3><a href="admin.php?tab=categories" class="btn btn-outline btn-sm">✕</a></div>
        <div class="adm-card-body">
          <form method="POST">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="<?= $editCat?'edit_category':'add_category' ?>">
            <?php if ($editCat): ?><input type="hidden" name="cat_id" value="<?= $editCat['id'] ?>"><?php endif; ?>
            <div class="form-group"><label class="form-label required"><?= __t('الاسم بالعربية','Name (AR)') ?></label><input type="text" name="name_ar" class="form-control" value="<?= e($editCat['name_ar']??'') ?>" required></div>
            <div class="form-group"><label class="form-label"><?= __t('الاسم بالإنجليزية','Name (EN)') ?></label><input type="text" name="name_en" class="form-control" value="<?= e($editCat['name_en']??'') ?>"></div>
            <div class="form-group"><label class="form-label"><?= __t('الأيقونة (إيموجي)','Icon (emoji)') ?></label><input type="text" name="icon" class="form-control" value="<?= e($editCat['icon']??'📦') ?>" style="font-size:1.4rem"></div>
            <label class="qcheck" style="margin-bottom:16px"><input type="checkbox" name="is_active" value="1" <?= ($editCat['is_active']??1)?'checked':'' ?>> <span><?= __t('مفعل','Active') ?></span></label>
            <button type="submit" class="btn btn-primary btn-block"><?= $editCat?__t('حفظ','Save'):__t('إضافة','Add') ?></button>
          </form>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- ════ ZONES ════ -->
    <?php elseif ($tab === 'zones'): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
      <p style="color:var(--gray);font-size:.85rem"><?= __t('أضف مناطق التوصيل وحدد رسوم الشحن لكل منطقة. سيختار العميل منطقته عند الطلب.','Add delivery zones and set shipping fees. Customers select their zone at checkout.') ?></p>
      <a href="admin.php?tab=zones&add=1" class="btn btn-primary">+ <?= __t('منطقة جديدة','Add Zone') ?></a>
    </div>
    <div style="display:grid;grid-template-columns:<?= ($editZone||isset($_GET['add']))?'1fr 380px':'1fr' ?>;gap:20px;align-items:start">
      <div class="adm-card">
        <div class="adm-table-wrap">
          <table class="adm-table">
            <thead><tr>
              <th>#</th>
              <th><?= __t('المنطقة','Zone') ?></th>
              <th><?= __t('رسوم التوصيل','Delivery Fee') ?></th>
              <th><?= __t('الترتيب','Order') ?></th>
              <th><?= __t('الحالة','Status') ?></th>
              <th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($zones as $z): ?>
              <tr>
                <td style="color:var(--gray);font-size:.78rem"><?= $z['id'] ?></td>
                <td>
                  <strong><?= e($z['name_ar']) ?></strong>
                  <?php if ($z['name_en'] !== $z['name_ar']): ?>
                    <br><small style="color:var(--gray)"><?= e($z['name_en']) ?></small>
                  <?php endif; ?>
                </td>
                <td><strong style="color:var(--primary)"><?= formatPrice((float)$z['shipping_fee']) ?></strong></td>
                <td style="color:var(--gray)"><?= (int)$z['sort_order'] ?></td>
                <td><span style="color:<?= $z['is_active']?'#198754':'#dc3545' ?>"><?= $z['is_active']?__t('مفعل','Active'):__t('معطل','Inactive') ?></span></td>
                <td>
                  <div style="display:flex;gap:6px">
                    <a href="admin.php?tab=zones&edit=<?= $z['id'] ?>" class="btn btn-outline btn-xs"><?= __t('تعديل','Edit') ?></a>
                    <form method="POST" style="display:inline" onsubmit="return confirm('<?= __t("حذف هذه المنطقة؟","Delete this zone?") ?>')">
                      <?= csrfInput() ?>
                      <input type="hidden" name="action" value="delete_zone">
                      <input type="hidden" name="zone_id" value="<?= $z['id'] ?>">
                      <button class="btn btn-red btn-xs">🗑</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($zones)): ?>
              <tr><td colspan="6" style="text-align:center;color:var(--gray);padding:30px"><?= __t('لا توجد مناطق بعد','No zones yet') ?></td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php if ($editZone || isset($_GET['add'])): ?>
      <div class="adm-card">
        <div class="adm-card-header">
          <h3><?= $editZone ? __t('تعديل المنطقة','Edit Zone') : __t('إضافة منطقة','Add Zone') ?></h3>
          <a href="admin.php?tab=zones" class="btn btn-outline btn-sm">✕</a>
        </div>
        <div class="adm-card-body">
          <form method="POST">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="<?= $editZone ? 'edit_zone' : 'add_zone' ?>">
            <?php if ($editZone): ?><input type="hidden" name="zone_id" value="<?= $editZone['id'] ?>"><?php endif; ?>
            <div class="form-group">
              <label class="form-label required"><?= __t('اسم المنطقة (عربي)','Zone Name (AR)') ?></label>
              <input type="text" name="name_ar" class="form-control" value="<?= e($editZone['name_ar']??'') ?>" required placeholder="<?= __t('مثال: رام الله','e.g. Ramallah') ?>">
            </div>
            <div class="form-group">
              <label class="form-label"><?= __t('اسم المنطقة (إنجليزي)','Zone Name (EN)') ?></label>
              <input type="text" name="name_en" class="form-control" value="<?= e($editZone['name_en']??'') ?>" placeholder="e.g. Ramallah">
            </div>
            <div class="form-group">
              <label class="form-label required"><?= __t('رسوم التوصيل (₪)','Delivery Fee (₪)') ?></label>
              <input type="number" name="shipping_fee" step="0.01" min="0" class="form-control" value="<?= e($editZone['shipping_fee']??'0') ?>" required>
              <small style="color:var(--gray);font-size:.75rem"><?= __t('أدخل 0 للشحن المجاني','Enter 0 for free shipping') ?></small>
            </div>
            <div class="form-group">
              <label class="form-label"><?= __t('ترتيب العرض','Display Order') ?></label>
              <input type="number" name="sort_order" class="form-control" value="<?= e($editZone['sort_order']??'0') ?>">
            </div>
            <label class="qcheck" style="margin-bottom:16px">
              <input type="checkbox" name="is_active" value="1" <?= ($editZone['is_active']??1)?'checked':'' ?>>
              <span><?= __t('مفعل','Active') ?></span>
            </label>
            <button type="submit" class="btn btn-primary btn-block"><?= $editZone ? __t('حفظ التعديلات','Save Changes') : __t('إضافة المنطقة','Add Zone') ?></button>
          </form>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- ════ COUPONS ════ -->
    <?php elseif ($tab === 'coupons'): ?>
    <div style="display:flex;justify-content:flex-end;margin-bottom:16px">
      <a href="admin.php?tab=coupons&add=1" class="btn btn-primary">+ <?= __t('كوبون جديد','New Coupon') ?></a>
    </div>
    <div style="display:grid;grid-template-columns:<?= ($editCoupon||isset($_GET['add']))?'1fr 360px':'1fr' ?>;gap:20px;align-items:start">
      <div class="adm-card">
        <div class="adm-table-wrap">
          <table class="adm-table">
            <thead><tr><th><?= __t('الكود','Code') ?></th><th><?= __t('النوع','Type') ?></th><th><?= __t('القيمة','Value') ?></th><th><?= __t('الحد الأدنى','Min Order') ?></th><th><?= __t('الاستخدامات','Uses') ?></th><th><?= __t('الانتهاء','Expires') ?></th><th><?= __t('الحالة','Status') ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($coupons as $c): ?>
              <tr>
                <td><strong style="color:var(--primary);font-family:monospace"><?= e($c['code']) ?></strong></td>
                <td><?= $c['type']==='percent'?'%':__t('ثابت','Fixed') ?></td>
                <td><?= $c['type']==='percent'?$c['value'].'%':formatPrice((float)$c['value']) ?></td>
                <td><?= $c['min_order']>0?formatPrice((float)$c['min_order']):'-' ?></td>
                <td><?= (int)$c['used_count'] ?>/<?= $c['max_uses']>0?$c['max_uses']:'∞' ?></td>
                <td style="font-size:.78rem"><?= $c['expires_at']?date('d M Y',strtotime($c['expires_at'])):'-' ?></td>
                <td><span style="color:<?= $c['is_active']?'#198754':'#dc3545' ?>"><?= $c['is_active']?'✓':'-' ?></span></td>
                <td>
                  <div style="display:flex;gap:6px">
                    <a href="admin.php?tab=coupons&edit=<?= $c['id'] ?>" class="btn btn-outline btn-xs"><?= __t('تعديل','Edit') ?></a>
                    <form method="POST" style="display:inline" onsubmit="return confirm('<?= __t("حذف؟","Delete?") ?>')">
                      <?= csrfInput() ?>
                      <input type="hidden" name="action" value="delete_coupon">
                      <input type="hidden" name="coupon_id" value="<?= $c['id'] ?>">
                      <button class="btn btn-red btn-xs">🗑</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php if ($editCoupon || isset($_GET['add'])): ?>
      <div class="adm-card">
        <div class="adm-card-header"><h3><?= $editCoupon?__t('تعديل الكوبون','Edit Coupon'):__t('كوبون جديد','New Coupon') ?></h3><a href="admin.php?tab=coupons" class="btn btn-outline btn-sm">✕</a></div>
        <div class="adm-card-body">
          <form method="POST">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="<?= $editCoupon?'edit_coupon':'add_coupon' ?>">
            <?php if ($editCoupon): ?><input type="hidden" name="coupon_id" value="<?= $editCoupon['id'] ?>"><?php endif; ?>
            <div class="form-group"><label class="form-label required"><?= __t('الكود','Code') ?></label><input type="text" name="code" class="form-control" value="<?= e($editCoupon['code']??'') ?>" required style="text-transform:uppercase"></div>
            <div class="form-group"><label class="form-label"><?= __t('النوع','Type') ?></label><select name="type" class="form-control"><option value="percent" <?= ($editCoupon['type']??'')==='percent'?'selected':'' ?>><?= __t('نسبة مئوية %','Percentage %') ?></option><option value="fixed" <?= ($editCoupon['type']??'')==='fixed'?'selected':'' ?>><?= __t('قيمة ثابتة','Fixed Amount') ?></option></select></div>
            <div class="form-group"><label class="form-label required"><?= __t('القيمة','Value') ?></label><input type="number" name="value" step="0.01" class="form-control" value="<?= e($editCoupon['value']??'') ?>" required></div>
            <div class="form-group"><label class="form-label"><?= __t('الحد الأدنى للطلب','Min Order') ?></label><input type="number" name="min_order" step="0.01" class="form-control" value="<?= e($editCoupon['min_order']??0) ?>"></div>
            <div class="form-group"><label class="form-label"><?= __t('أقصى استخدامات (0=بلا حد)','Max Uses (0=unlimited)') ?></label><input type="number" name="max_uses" class="form-control" value="<?= e($editCoupon['max_uses']??0) ?>"></div>
            <div class="form-group"><label class="form-label"><?= __t('تاريخ الانتهاء','Expiry Date') ?></label><input type="date" name="expires_at" class="form-control" value="<?= e($editCoupon['expires_at']??'') ?>"></div>
            <label class="qcheck" style="margin-bottom:16px"><input type="checkbox" name="is_active" value="1" <?= ($editCoupon['is_active']??1)?'checked':'' ?>> <span><?= __t('مفعل','Active') ?></span></label>
            <button type="submit" class="btn btn-primary btn-block"><?= $editCoupon?__t('حفظ','Save'):__t('إضافة','Add') ?></button>
          </form>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- ════ REVIEWS ════ -->
    <?php elseif ($tab === 'reviews'): ?>
    <div style="display:flex;gap:10px;margin-bottom:16px">
      <a href="admin.php?tab=reviews" class="btn btn-sm <?= !isset($_GET['pending'])?'btn-primary':'btn-outline' ?>"><?= __t('الكل','All') ?></a>
      <a href="admin.php?tab=reviews&pending=1" class="btn btn-sm <?= isset($_GET['pending'])?'btn-primary':'btn-outline' ?>">⏳ <?= __t('في الانتظار','Pending') ?> <span style="background:var(--primary);color:white;padding:1px 6px;border-radius:10px;font-size:.7rem"><?= $pendingReviews ?></span></a>
    </div>
    <div class="adm-card">
      <div class="adm-table-wrap">
        <table class="adm-table">
          <thead><tr><th><?= __t('المنتج','Product') ?></th><th><?= __t('المستخدم','User') ?></th><th><?= __t('التقييم','Rating') ?></th><th><?= __t('التعليق','Comment') ?></th><th><?= __t('التاريخ','Date') ?></th><th><?= __t('الحالة','Status') ?></th><th></th></tr></thead>
          <tbody>
          <?php foreach ($reviews as $r): ?>
            <tr>
              <td style="font-size:.8rem"><?= e($r['pname']??'') ?></td>
              <td><?= e($r['reviewer_name']??__t('مجهول','Anonymous')) ?></td>
              <td><?= starsHtml((float)$r['rating'],true) ?></td>
              <td style="max-width:200px;font-size:.82rem"><?= e(mb_substr($r['comment']??'',0,80)) ?></td>
              <td style="font-size:.75rem;color:var(--gray)"><?= date('d M Y',strtotime($r['created_at'])) ?></td>
              <td><?= $r['is_approved']?'<span style="color:#198754">✓ '.__t('مقبول','Approved').'</span>':'<span style="color:#d97706">⏳ '.__t('انتظار','Pending').'</span>' ?></td>
              <td>
                <div style="display:flex;gap:6px">
                  <?php if (!$r['is_approved']): ?>
                  <form method="POST" style="display:inline">
                    <?= csrfInput() ?>
                    <input type="hidden" name="action" value="approve_review">
                    <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                    <button class="btn btn-green btn-xs">✓</button>
                  </form>
                  <?php endif; ?>
                  <form method="POST" style="display:inline" onsubmit="return confirm('<?= __t("حذف؟","Delete?") ?>')">
                    <?= csrfInput() ?>
                    <input type="hidden" name="action" value="delete_review">
                    <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                    <button class="btn btn-red btn-xs">🗑</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ════ USERS ════ -->
    <?php elseif ($tab === 'users'): ?>
    <div class="adm-card">
      <div class="adm-table-wrap">
        <table class="adm-table">
          <thead><tr><th>#</th><th><?= __t('الاسم','Name') ?></th><th><?= __t('البريد','Email') ?></th><th><?= __t('الهاتف','Phone') ?></th><th><?= __t('الطلبات','Orders') ?></th><th><?= __t('الإنفاق','Spent') ?></th><th><?= __t('تاريخ التسجيل','Joined') ?></th><th><?= __t('الحالة','Status') ?></th><th></th></tr></thead>
          <tbody>
          <?php foreach ($users as $u):
            $uOrders = (int)(dbFetchOne("SELECT COUNT(*) c FROM orders WHERE user_id=?",'i',$u['id'])['c']??0);
            $uSpent  = (float)(dbFetchOne("SELECT SUM(total) s FROM orders WHERE user_id=? AND status!='cancelled'",'i',$u['id'])['s']??0);
          ?>
            <tr>
              <td style="color:var(--gray);font-size:.78rem"><?= $u['id'] ?></td>
              <td><strong><?= e($u['name']) ?></strong></td>
              <td style="font-size:.82rem"><?= e($u['email']) ?></td>
              <td style="font-size:.82rem"><?= e($u['phone']??'-') ?></td>
              <td><?= $uOrders ?></td>
              <td style="color:var(--primary);font-weight:700"><?= formatPrice($uSpent) ?></td>
              <td style="font-size:.75rem;color:var(--gray)"><?= date('d M Y',strtotime($u['created_at'])) ?></td>
              <td><span style="color:<?= $u['is_active']?'#198754':'#dc3545' ?>"><?= $u['is_active']?__t('مفعل','Active'):__t('معطل','Banned') ?></span></td>
              <td>
                <form method="POST" style="display:inline">
                  <?= csrfInput() ?>
                  <input type="hidden" name="action" value="toggle_user">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button class="btn btn-sm <?= $u['is_active']?'btn-red':'btn-green' ?>"><?= $u['is_active']?__t('تعطيل','Ban'):__t('تفعيل','Unban') ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ════ SETTINGS ════ -->
    <?php elseif ($tab === 'settings'): ?>
    <form method="POST">
      <?= csrfInput() ?>
      <input type="hidden" name="action" value="save_settings">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">

        <div class="adm-card">
          <div class="adm-card-header"><h3>🏪 <?= __t('معلومات المتجر','Store Information') ?></h3></div>
          <div class="adm-card-body">
            <div class="form-group"><label class="form-label"><?= __t('اسم المتجر (عربي)','Store Name (AR)') ?></label><input type="text" name="site_name_ar" class="form-control" value="<?= e($settings['site_name_ar']??'') ?>"></div>
            <div class="form-group"><label class="form-label"><?= __t('اسم المتجر (إنجليزي)','Store Name (EN)') ?></label><input type="text" name="site_name_en" class="form-control" value="<?= e($settings['site_name_en']??'') ?>"></div>
            <div class="form-group"><label class="form-label"><?= __t('الشعار الفرعي (عربي)','Tagline (AR)') ?></label><input type="text" name="site_tagline_ar" class="form-control" value="<?= e($settings['site_tagline_ar']??'') ?>"></div>
            <div class="form-group"><label class="form-label"><?= __t('الشعار الفرعي (إنجليزي)','Tagline (EN)') ?></label><input type="text" name="site_tagline_en" class="form-control" value="<?= e($settings['site_tagline_en']??'') ?>"></div>
          </div>
        </div>

        <div class="adm-card">
          <div class="adm-card-header"><h3>💰 <?= __t('العملة والشحن','Currency & Shipping') ?></h3></div>
          <div class="adm-card-body">
            <div class="form-group"><label class="form-label"><?= __t('رمز العملة (عربي)','Currency Symbol (AR)') ?></label><input type="text" name="currency_ar" class="form-control" value="<?= e($settings['currency_ar']??'₪') ?>"></div>
            <div class="form-group"><label class="form-label"><?= __t('رمز العملة (إنجليزي)','Currency Symbol (EN)') ?></label><input type="text" name="currency_en" class="form-control" value="<?= e($settings['currency_en']??'₪') ?>"></div>
            <div class="form-group"><label class="form-label"><?= __t('رسوم الشحن الافتراضية (₪)','Default Shipping Fee (₪)') ?></label><input type="number" name="shipping_fee" step="0.01" class="form-control" value="<?= e($settings['shipping_fee']??15) ?>"><small style="color:var(--gray);font-size:.75rem"><?= __t('تُستخدم عند عدم تحديد منطقة','Used when no zone is selected') ?></small></div>
            <div class="form-group"><label class="form-label"><?= __t('حد الشحن المجاني (₪)','Free Shipping Min (₪)') ?></label><input type="number" name="free_ship_min" step="0.01" class="form-control" value="<?= e($settings['free_ship_min']??200) ?>"></div>
          </div>
        </div>

        <div class="adm-card">
          <div class="adm-card-header"><h3>📞 <?= __t('معلومات التواصل','Contact Info') ?></h3></div>
          <div class="adm-card-body">
            <div class="form-group"><label class="form-label"><?= __t('الهاتف','Phone') ?></label><input type="text" name="site_phone" class="form-control" value="<?= e($settings['site_phone']??'') ?>"></div>
            <div class="form-group"><label class="form-label"><?= __t('البريد الإلكتروني','Email') ?></label><input type="email" name="site_email" class="form-control" value="<?= e($settings['site_email']??'') ?>"></div>
            <div class="form-group"><label class="form-label"><?= __t('العنوان','Address') ?></label><textarea name="site_address_ar" class="form-control" rows="2"><?= e($settings['site_address_ar']??'') ?></textarea></div>
          </div>
        </div>

        <div class="adm-card">
          <div class="adm-card-header"><h3>📱 <?= __t('التواصل الاجتماعي','Social Media') ?></h3></div>
          <div class="adm-card-body">
            <div class="form-group"><label class="form-label">Facebook URL</label><input type="url" name="facebook" class="form-control" value="<?= e($settings['facebook']??'') ?>"></div>
            <div class="form-group"><label class="form-label">Instagram URL</label><input type="url" name="instagram" class="form-control" value="<?= e($settings['instagram']??'') ?>"></div>
            <div class="form-group"><label class="form-label">Twitter/X URL</label><input type="url" name="twitter" class="form-control" value="<?= e($settings['twitter']??'') ?>"></div>
            <div class="form-group"><label class="form-label">WhatsApp Number</label><input type="text" name="whatsapp" class="form-control" value="<?= e($settings['whatsapp']??'') ?>"></div>
          </div>
        </div>

      </div>
      <div style="margin-top:20px">
        <button type="submit" class="btn btn-primary btn-lg">💾 <?= __t('حفظ جميع الإعدادات','Save All Settings') ?></button>
      </div>
    </form>

    <?php endif; ?>

  </div><!-- .adm-content -->
</div><!-- .adm-main -->

<style>
.qcheck{display:flex;align-items:center;gap:8px;cursor:pointer;font-size:.86rem}
.qcheck input{accent-color:var(--primary);width:16px;height:16px}
</style>
<script>
// Mobile sidebar
document.addEventListener('click', e => {
  const sidebar = document.getElementById('admSidebar');
  if (window.innerWidth <= 900 && !sidebar.contains(e.target) && !e.target.closest('.adm-mobile-toggle')) {
    sidebar.classList.remove('open');
  }
});
// Auto-hide alerts
setTimeout(() => document.querySelectorAll('.alert').forEach(a => {
  a.style.transition = 'opacity .5s'; a.style.opacity = '0';
  setTimeout(() => a.remove(), 500);
}), 4000);
</script>
</body>
</html>
