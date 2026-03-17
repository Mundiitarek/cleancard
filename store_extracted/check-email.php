<?php
// check-email.php - للتحقق من البريد الإلكتروني أثناء التسجيل
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
header('Content-Type: application/json');
$email = strtolower(trim($_GET['email'] ?? ''));
if (!validateEmail($email)) { echo json_encode(['available'=>false]); exit; }
$exists = dbFetchOne("SELECT id FROM users WHERE email=?", 's', $email);
echo json_encode(['available' => !$exists]);
