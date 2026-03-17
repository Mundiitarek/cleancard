<?php
// wishlist-action.php - معالج أوامر المفضلة AJAX
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false]); exit; }
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { echo json_encode(['success'=>false,'message'=>__t('رمز غير صالح','Invalid token')]); exit; }

if (!isLoggedIn()) {
    echo json_encode(['success'=>false,'message'=>__t('سجل دخولك أولاً','Please login first'),'redirect'=>'login.php']); exit;
}

$productId = (int)($_POST['product_id'] ?? 0);
if (!$productId) { echo json_encode(['success'=>false,'message'=>__t('منتج غير صالح','Invalid product')]); exit; }

$result = toggleWishlist($productId);
echo json_encode($result);
