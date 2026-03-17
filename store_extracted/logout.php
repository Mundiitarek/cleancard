<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
logActivity('logout', 'User logged out');
logoutUser();
header('Location: index.php'); exit;
