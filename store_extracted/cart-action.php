<?php
// cart-action.php - معالج أوامر السلة AJAX
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false]); exit; }
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { echo json_encode(['success'=>false,'message'=>__t('رمز غير صالح','Invalid token')]); exit; }

$action = $_POST['action'] ?? '';

if ($action === 'add') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $qty       = max(1,(int)($_POST['quantity'] ?? 1));
    if (!$productId) { echo json_encode(['success'=>false,'message'=>__t('منتج غير صالح','Invalid product')]); exit; }
    // Add multiple times if qty > 1
    $result = addToCart($productId, $qty);
    echo json_encode($result); exit;
}

if ($action === 'remove') {
    $cartId = (int)($_POST['cart_id'] ?? 0);
    $ok = removeFromCart($cartId);
    echo json_encode(['success'=>$ok,'count'=>getCartCount(),'message'=>$ok?__t('تم الحذف','Removed'):__t('خطأ','Error')]); exit;
}

if ($action === 'update') {
    $cartId = (int)($_POST['cart_id'] ?? 0);
    $qty    = max(1,(int)($_POST['qty'] ?? 1));
    $ok     = updateCartQty($cartId, $qty);
    echo json_encode(['success'=>$ok,'count'=>getCartCount()]); exit;
}

if ($action === 'clear') {
    clearCart();
    echo json_encode(['success'=>true,'count'=>0]); exit;
}

echo json_encode(['success'=>false,'message'=>'Unknown action']);
