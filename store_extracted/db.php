<?php
// ============================================================
// db.php - الاتصال بقاعدة البيانات + إنشاء الجداول
// ============================================================

define('DB_HOST',     'localhost');
define('DB_USER',     'smm2355_carder');
define('DB_PASS',     'smm2355_carder');
define('DB_NAME',     'smm2355_carder');
define('DB_CHARSET',  'utf8mb4');

define('SITE_URL',    'https://ahmed-card.online');
define('UPLOADS_DIR', __DIR__ . '/uploads/');
define('UPLOADS_URL', SITE_URL . '/uploads/');

// ─── الاتصال (Singleton) ─────────────────────────────────────
function db(): mysqli {
    static $c = null;
    if ($c === null) {
        $c = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($c->connect_error) die('DB Error: ' . $c->connect_error);
        $c->set_charset(DB_CHARSET);
        $c->query("SET time_zone='+02:00'");
    }
    return $c;
}

// ─── Query helpers ───────────────────────────────────────────
function dbQuery(string $sql, string $types = '', ...$params) {
    $stmt = db()->prepare($sql);
    if (!$stmt) return false;
    if ($types && $params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt;
}

function dbFetchAll(string $sql, string $types = '', ...$params): array {
    $stmt = dbQuery($sql, $types, ...$params);
    if (!$stmt) return [];
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function dbFetchOne(string $sql, string $types = '', ...$params): ?array {
    $rows = dbFetchAll($sql, $types, ...$params);
    return $rows[0] ?? null;
}

function dbInsert(string $table, array $data): int {
    $cols  = implode(',', array_map(fn($k) => "`$k`", array_keys($data)));
    $ph    = implode(',', array_fill(0, count($data), '?'));
    $types = implode('', array_map(fn($v) => is_int($v) ? 'i' : (is_float($v) ? 'd' : 's'), array_values($data)));
    $vals  = array_values($data);
    $stmt  = db()->prepare("INSERT INTO `$table` ($cols) VALUES ($ph)");
    if (!$stmt) return 0;
    $stmt->bind_param($types, ...$vals);
    $stmt->execute();
    return db()->insert_id;
}

function dbUpdate(string $table, array $data, string $where, string $wTypes = '', ...$wParams): bool {
    $set   = implode(',', array_map(fn($k) => "`$k`=?", array_keys($data)));
    $dT    = implode('', array_map(fn($v) => is_int($v) ? 'i' : (is_float($v) ? 'd' : 's'), array_values($data)));
    $vals  = array_values($data);
    if ($wParams) { $dT .= $wTypes; $vals = array_merge($vals, $wParams); }
    $stmt  = db()->prepare("UPDATE `$table` SET $set WHERE $where");
    if (!$stmt) return false;
    $stmt->bind_param($dT, ...$vals);
    return $stmt->execute();
}

// ─── تثبيت قاعدة البيانات ───────────────────────────────────
function installDB(): void {
    $raw = new mysqli(DB_HOST, DB_USER, DB_PASS);
    if ($raw->connect_error) die('Cannot connect: ' . $raw->connect_error);
    $raw->query("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $raw->select_db(DB_NAME);
    $raw->set_charset(DB_CHARSET);

    // ── users ──
    $raw->query("CREATE TABLE IF NOT EXISTS `users` (
        `id`          INT AUTO_INCREMENT PRIMARY KEY,
        `name`        VARCHAR(120) NOT NULL,
        `email`       VARCHAR(160) NOT NULL UNIQUE,
        `password`    VARCHAR(255) NOT NULL,
        `phone`       VARCHAR(25)  DEFAULT NULL,
        `address`     TEXT         DEFAULT NULL,
        `city`        VARCHAR(80)  DEFAULT NULL,
        `role`        ENUM('customer','admin') DEFAULT 'customer',
        `avatar`      VARCHAR(255) DEFAULT NULL,
        `is_active`   TINYINT(1)   DEFAULT 1,
        `lang`        ENUM('ar','en') DEFAULT 'ar',
        `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
        `updated_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX(`email`), INDEX(`role`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── categories ──
    $raw->query("CREATE TABLE IF NOT EXISTS `categories` (
        `id`             INT AUTO_INCREMENT PRIMARY KEY,
        `name_ar`        VARCHAR(120) NOT NULL,
        `name_en`        VARCHAR(120) NOT NULL,
        `slug`           VARCHAR(140) NOT NULL UNIQUE,
        `description_ar` TEXT         DEFAULT NULL,
        `description_en` TEXT         DEFAULT NULL,
        `image`          VARCHAR(255) DEFAULT NULL,
        `icon`           VARCHAR(60)  DEFAULT NULL,
        `parent_id`      INT          DEFAULT NULL,
        `sort_order`     INT          DEFAULT 0,
        `is_active`      TINYINT(1)   DEFAULT 1,
        `created_at`     DATETIME     DEFAULT CURRENT_TIMESTAMP,
        INDEX(`slug`), INDEX(`parent_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── products ──
    $raw->query("CREATE TABLE IF NOT EXISTS `products` (
        `id`           INT AUTO_INCREMENT PRIMARY KEY,
        `category_id`  INT           NOT NULL,
        `name_ar`      VARCHAR(220)  NOT NULL,
        `name_en`      VARCHAR(220)  NOT NULL,
        `slug`         VARCHAR(240)  NOT NULL UNIQUE,
        `desc_ar`      LONGTEXT      DEFAULT NULL,
        `desc_en`      LONGTEXT      DEFAULT NULL,
        `price`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `sale_price`   DECIMAL(10,2) DEFAULT NULL,
        `cost_price`   DECIMAL(10,2) DEFAULT NULL,
        `stock`        INT           DEFAULT 0,
        `sku`          VARCHAR(100)  DEFAULT NULL,
        `image`        VARCHAR(255)  DEFAULT NULL,
        `gallery`      TEXT          DEFAULT NULL COMMENT 'JSON',
        `is_featured`  TINYINT(1)    DEFAULT 0,
        `is_new`       TINYINT(1)    DEFAULT 1,
        `is_active`    TINYINT(1)    DEFAULT 1,
        `views`        INT           DEFAULT 0,
        `rating_avg`   DECIMAL(3,2)  DEFAULT 0.00,
        `rating_count` INT           DEFAULT 0,
        `tags`         VARCHAR(500)  DEFAULT NULL,
        `created_at`   DATETIME      DEFAULT CURRENT_TIMESTAMP,
        `updated_at`   DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY(`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE,
        INDEX(`slug`), INDEX(`category_id`), INDEX(`is_featured`), INDEX(`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── orders ──
    $raw->query("CREATE TABLE IF NOT EXISTS `orders` (
        `id`             INT AUTO_INCREMENT PRIMARY KEY,
        `order_number`   VARCHAR(30)   NOT NULL UNIQUE,
        `user_id`        INT           DEFAULT NULL,
        `name`           VARCHAR(120)  NOT NULL,
        `email`          VARCHAR(160)  DEFAULT NULL,
        `phone`          VARCHAR(25)   NOT NULL,
        `address`        TEXT          NOT NULL,
        `city`           VARCHAR(80)   NOT NULL,
        `notes`          TEXT          DEFAULT NULL,
        `subtotal`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `shipping_fee`   DECIMAL(10,2) DEFAULT 0.00,
        `discount`       DECIMAL(10,2) DEFAULT 0.00,
        `total`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `coupon_code`    VARCHAR(50)   DEFAULT NULL,
        `payment_method` ENUM('cod')   DEFAULT 'cod',
        `payment_status` ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
        `status`         ENUM('pending','confirmed','processing','shipped','delivered','cancelled') DEFAULT 'pending',
        `tracking_no`    VARCHAR(100)  DEFAULT NULL,
        `shipped_at`     DATETIME      DEFAULT NULL,
        `delivered_at`   DATETIME      DEFAULT NULL,
        `created_at`     DATETIME      DEFAULT CURRENT_TIMESTAMP,
        `updated_at`     DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY(`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
        INDEX(`order_number`), INDEX(`user_id`), INDEX(`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── order_items ──
    $raw->query("CREATE TABLE IF NOT EXISTS `order_items` (
        `id`          INT AUTO_INCREMENT PRIMARY KEY,
        `order_id`    INT           NOT NULL,
        `product_id`  INT           NOT NULL,
        `name_ar`     VARCHAR(220)  NOT NULL,
        `name_en`     VARCHAR(220)  NOT NULL,
        `image`       VARCHAR(255)  DEFAULT NULL,
        `quantity`    INT           NOT NULL DEFAULT 1,
        `unit_price`  DECIMAL(10,2) NOT NULL,
        `total_price` DECIMAL(10,2) NOT NULL,
        FOREIGN KEY(`order_id`)   REFERENCES `orders`(`id`)   ON DELETE CASCADE,
        FOREIGN KEY(`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
        INDEX(`order_id`), INDEX(`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── cart ──
    $raw->query("CREATE TABLE IF NOT EXISTS `cart` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `user_id`    INT          DEFAULT NULL,
        `session_id` VARCHAR(120) DEFAULT NULL,
        `product_id` INT          NOT NULL,
        `quantity`   INT          NOT NULL DEFAULT 1,
        `added_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE,
        FOREIGN KEY(`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
        INDEX(`user_id`), INDEX(`session_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── wishlist ──
    $raw->query("CREATE TABLE IF NOT EXISTS `wishlist` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `user_id`    INT      NOT NULL,
        `product_id` INT      NOT NULL,
        `added_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uw` (`user_id`,`product_id`),
        FOREIGN KEY(`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE,
        FOREIGN KEY(`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── coupons ──
    $raw->query("CREATE TABLE IF NOT EXISTS `coupons` (
        `id`          INT AUTO_INCREMENT PRIMARY KEY,
        `code`        VARCHAR(60)   NOT NULL UNIQUE,
        `type`        ENUM('percent','fixed') DEFAULT 'percent',
        `value`       DECIMAL(10,2) NOT NULL,
        `min_order`   DECIMAL(10,2) DEFAULT 0.00,
        `max_uses`    INT           DEFAULT NULL,
        `used_count`  INT           DEFAULT 0,
        `is_active`   TINYINT(1)    DEFAULT 1,
        `expires_at`  DATETIME      DEFAULT NULL,
        `created_at`  DATETIME      DEFAULT CURRENT_TIMESTAMP,
        INDEX(`code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── reviews ──
    $raw->query("CREATE TABLE IF NOT EXISTS `reviews` (
        `id`            INT AUTO_INCREMENT PRIMARY KEY,
        `product_id`    INT         NOT NULL,
        `user_id`       INT         DEFAULT NULL,
        `reviewer_name` VARCHAR(100) DEFAULT NULL,
        `rating`        TINYINT(1)  NOT NULL DEFAULT 5,
        `comment`       TEXT        DEFAULT NULL,
        `is_approved`   TINYINT(1)  DEFAULT 0,
        `created_at`    DATETIME    DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
        FOREIGN KEY(`user_id`)    REFERENCES `users`(`id`)    ON DELETE SET NULL,
        INDEX(`product_id`), INDEX(`is_approved`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── sliders ──
    $raw->query("CREATE TABLE IF NOT EXISTS `sliders` (
        `id`          INT AUTO_INCREMENT PRIMARY KEY,
        `title_ar`    VARCHAR(200) DEFAULT NULL,
        `title_en`    VARCHAR(200) DEFAULT NULL,
        `subtitle_ar` VARCHAR(300) DEFAULT NULL,
        `subtitle_en` VARCHAR(300) DEFAULT NULL,
        `image`       VARCHAR(255) NOT NULL,
        `link`        VARCHAR(500) DEFAULT NULL,
        `btn_ar`      VARCHAR(80)  DEFAULT 'تسوق الآن',
        `btn_en`      VARCHAR(80)  DEFAULT 'Shop Now',
        `sort_order`  INT          DEFAULT 0,
        `is_active`   TINYINT(1)   DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── banners ──
    $raw->query("CREATE TABLE IF NOT EXISTS `banners` (
        `id`        INT AUTO_INCREMENT PRIMARY KEY,
        `title_ar`  VARCHAR(200) DEFAULT NULL,
        `title_en`  VARCHAR(200) DEFAULT NULL,
        `image`     VARCHAR(255) NOT NULL,
        `link`      VARCHAR(500) DEFAULT NULL,
        `position`  VARCHAR(60)  DEFAULT 'home_mid',
        `sort_order` INT         DEFAULT 0,
        `is_active`  TINYINT(1)  DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── settings ──
    $raw->query("CREATE TABLE IF NOT EXISTS `settings` (
        `id`       INT AUTO_INCREMENT PRIMARY KEY,
        `key_name` VARCHAR(100) NOT NULL UNIQUE,
        `value`    TEXT         DEFAULT NULL,
        INDEX(`key_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── activity_logs ──
    $raw->query("CREATE TABLE IF NOT EXISTS `activity_logs` (
        `id`          INT AUTO_INCREMENT PRIMARY KEY,
        `user_id`     INT          DEFAULT NULL,
        `action`      VARCHAR(120) NOT NULL,
        `description` TEXT         DEFAULT NULL,
        `ip`          VARCHAR(45)  DEFAULT NULL,
        `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
        INDEX(`user_id`), INDEX(`action`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── shipping_zones ──
    $raw->query("CREATE TABLE IF NOT EXISTS `shipping_zones` (
        `id`           INT AUTO_INCREMENT PRIMARY KEY,
        `name_ar`      VARCHAR(120)  NOT NULL,
        `name_en`      VARCHAR(120)  NOT NULL,
        `shipping_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `is_active`    TINYINT(1)    DEFAULT 1,
        `sort_order`   INT           DEFAULT 0,
        `created_at`   DATETIME      DEFAULT CURRENT_TIMESTAMP,
        INDEX(`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    _seedData($raw);
    $raw->close();
}

function _seedData(mysqli $db): void {
    // Admin
    $r = $db->query("SELECT id FROM users WHERE role='admin' LIMIT 1");
    if ($r && $r->num_rows === 0) {
        $h = password_hash('Admin@123', PASSWORD_BCRYPT);
        $db->query("INSERT INTO users (name,email,password,role,is_active)
                    VALUES ('المدير','admin@store.com','$h','admin',1)");
    }

    // Categories
    $r = $db->query("SELECT id FROM categories LIMIT 1");
    if ($r && $r->num_rows === 0) {
        $cats = [
            ['إلكترونيات','Electronics','electronics','📱'],
            ['ملابس وأزياء','Fashion','fashion','👗'],
            ['منزل ومطبخ','Home & Kitchen','home-kitchen','🏠'],
            ['رياضة ولياقة','Sports','sports','⚽'],
            ['جمال وعناية','Beauty','beauty','💄'],
            ['كتب وتعليم','Books','books','📚'],
        ];
        foreach ($cats as [$ar,$en,$slug,$icon]) {
            $ar=$db->real_escape_string($ar); $en=$db->real_escape_string($en);
            $db->query("INSERT IGNORE INTO categories (name_ar,name_en,slug,icon,is_active)
                        VALUES ('$ar','$en','$slug','$icon',1)");
        }
    }

    // Settings
    $r = $db->query("SELECT id FROM settings LIMIT 1");
    if ($r && $r->num_rows === 0) {
        $sets = [
            'site_name_ar'=>'متجري','site_name_en'=>'MyStore',
            'site_tagline_ar'=>'كل ما تحتاجه في مكان واحد',
            'site_tagline_en'=>'Everything you need in one place',
            'site_email'=>'info@store.com','site_phone'=>'01000000000',
            'site_address_ar'=>'القاهرة، مصر','site_address_en'=>'Cairo, Egypt',
            'currency_ar'=>'₪','currency_en'=>'₪',
            'shipping_fee'=>'15','free_ship_min'=>'200',
            'primary_color'=>'#e63946',
            'facebook'=>'#','instagram'=>'#','whatsapp'=>'01000000000',
            'logo'=>'','maintenance'=>'0',
        ];
        foreach ($sets as $k=>$v) {
            $k=$db->real_escape_string($k); $v=$db->real_escape_string($v);
            $db->query("INSERT IGNORE INTO settings (key_name,value) VALUES ('$k','$v')");
        }
    }

    // Sample products
    $r = $db->query("SELECT id FROM products LIMIT 1");
    if ($r && $r->num_rows === 0) {
        $cR = $db->query("SELECT id FROM categories LIMIT 1");
        $cId = $cR ? ($cR->fetch_assoc()['id'] ?? 1) : 1;
        $ps = [
            ['سماعة بلوتوث Pro','Bluetooth Headphones Pro','bt-headphones-pro',299.99,199.99,50,1],
            ['ساعة ذكية Series X','Smart Watch Series X','smart-watch-x',799,599,30,1],
            ['لاب توب Gaming','Gaming Laptop 15"','gaming-laptop-15',12999,9999,10,1],
            ['كاميرا 4K','4K Action Camera','4k-action-camera',3499,2799,25,0],
            ['هاتف ذكي Ultra','Smartphone Ultra','smartphone-ultra',8999,7499,20,1],
            ['سماعة أذن لاسلكية','Wireless Earbuds','wireless-earbuds-1',450,350,60,0],
        ];
        foreach ($ps as [$ar,$en,$slug,$price,$sale,$stock,$feat]) {
            $ar=$db->real_escape_string($ar); $en=$db->real_escape_string($en);
            $db->query("INSERT IGNORE INTO products
                (category_id,name_ar,name_en,slug,price,sale_price,stock,is_featured,is_new,is_active)
                VALUES ($cId,'$ar','$en','$slug',$price,$sale,$stock,$feat,1,1)");
        }
    }

    // Shipping Zones
    $r = $db->query("SELECT id FROM shipping_zones LIMIT 1");
    if ($r && $r->num_rows === 0) {
        $zones = [
            ['رام الله والبيرة','Ramallah & Al-Bireh',15.00,0],
            ['نابلس','Nablus',20.00,1],
            ['جنين','Jenin',25.00,2],
            ['طولكرم','Tulkarm',20.00,3],
            ['القدس','Jerusalem',15.00,4],
            ['الخليل','Hebron',20.00,5],
            ['بيت لحم','Bethlehem',18.00,6],
            ['أريحا','Jericho',25.00,7],
            ['طوباس','Tubas',30.00,8],
            ['سلفيت','Salfit',22.00,9],
            ['قلقيلية','Qalqilya',20.00,10],
            ['غزة','Gaza',30.00,11],
        ];
        foreach ($zones as [$ar,$en,$fee,$sort]) {
            $ar=$db->real_escape_string($ar); $en=$db->real_escape_string($en);
            $db->query("INSERT INTO shipping_zones (name_ar,name_en,shipping_fee,is_active,sort_order)
                        VALUES ('$ar','$en',$fee,1,$sort)");
        }
    }

    // Sliders
    $r = $db->query("SELECT id FROM sliders LIMIT 1");
    if ($r && $r->num_rows === 0) {
        $sls = [
            ['عروض خاصة','Special Offers','خصومات تصل إلى 70%','Up to 70% off','shop.php',1],
            ['أحدث الإلكترونيات','Latest Electronics','اكتشف أحدث التقنيات','Discover latest tech','shop.php?cat=electronics',2],
            ['شحن مجاني','Free Shipping','على الطلبات فوق 500 ج.م','On orders above 500 EGP','shop.php',3],
        ];
        foreach ($sls as [$tar,$ten,$sar,$sen,$link,$sort]) {
            $tar=$db->real_escape_string($tar); $ten=$db->real_escape_string($ten);
            $sar=$db->real_escape_string($sar); $sen=$db->real_escape_string($sen);
            $db->query("INSERT INTO sliders (title_ar,title_en,subtitle_ar,subtitle_en,image,link,sort_order,is_active)
                        VALUES ('$tar','$ten','$sar','$sen','','$link',$sort,1)");
        }
    }
}

// ─── Migration: تشغيل التحديثات عند كل طلب ──────────────────
function migrateDB(): void {
    // إنشاء جدول مناطق الشحن إذا لم يكن موجوداً
    db()->query("CREATE TABLE IF NOT EXISTS `shipping_zones` (
        `id`           INT AUTO_INCREMENT PRIMARY KEY,
        `name_ar`      VARCHAR(120)  NOT NULL,
        `name_en`      VARCHAR(120)  NOT NULL,
        `shipping_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `is_active`    TINYINT(1)    DEFAULT 1,
        `sort_order`   INT           DEFAULT 0,
        `created_at`   DATETIME      DEFAULT CURRENT_TIMESTAMP,
        INDEX(`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // إضافة المناطق الافتراضية إذا كان الجدول فارغاً
    $cnt = db()->query("SELECT COUNT(*) c FROM shipping_zones")->fetch_assoc()['c'] ?? 0;
    if ($cnt == 0) {
        $zones = [
            ['رام الله والبيرة','Ramallah & Al-Bireh',15.00,0],
            ['نابلس','Nablus',20.00,1],
            ['جنين','Jenin',25.00,2],
            ['طولكرم','Tulkarm',20.00,3],
            ['القدس','Jerusalem',15.00,4],
            ['الخليل','Hebron',20.00,5],
            ['بيت لحم','Bethlehem',18.00,6],
            ['أريحا','Jericho',25.00,7],
            ['طوباس','Tubas',30.00,8],
            ['سلفيت','Salfit',22.00,9],
            ['قلقيلية','Qalqilya',20.00,10],
            ['غزة','Gaza',30.00,11],
        ];
        foreach ($zones as [$ar,$en,$fee,$sort]) {
            $stmt = db()->prepare("INSERT INTO shipping_zones (name_ar,name_en,shipping_fee,is_active,sort_order) VALUES (?,?,?,1,?)");
            if ($stmt) { $stmt->bind_param('ssdi',$ar,$en,$fee,$sort); $stmt->execute(); }
        }
    }

    // تحديث العملة من جنيه مصري إلى شيكل إذا لم تكن محدّثة
    db()->query("UPDATE settings SET value='₪' WHERE key_name='currency_ar' AND value IN ('ج.م','EGP')");
    db()->query("UPDATE settings SET value='₪' WHERE key_name='currency_en' AND value IN ('EGP','ج.م')");
    db()->query("UPDATE settings SET value='15' WHERE key_name='shipping_fee' AND value='50'");
    db()->query("UPDATE settings SET value='200' WHERE key_name='free_ship_min' AND value='500'");
}

// ─── تشغيل التثبيت مرة واحدة ────────────────────────────────
if (!file_exists(__DIR__ . '/.db_installed')) {
    installDB();
    file_put_contents(__DIR__ . '/.db_installed', date('Y-m-d H:i:s'));
}

// تشغيل الترحيلات في كل طلب
migrateDB();

if (!is_dir(UPLOADS_DIR)) {
    @mkdir(UPLOADS_DIR,              0755, true);
    @mkdir(UPLOADS_DIR.'products/',  0755, true);
    @mkdir(UPLOADS_DIR.'categories/',0755, true);
    @mkdir(UPLOADS_DIR.'sliders/',   0755, true);
    @mkdir(UPLOADS_DIR.'banners/',   0755, true);
}