<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
startSession();
if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$errors = []; $email_val = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!verifyCsrfToken($_POST['csrf_token']??'')) { $errors[]='Invalid token'; }
  else {
    $email = sanitize($_POST['email']??'');
    $pass  = $_POST['password']??'';
    $email_val = $email;
    if (!$email) $errors[]=__t('البريد الإلكتروني مطلوب','Email is required');
    if (!$pass)  $errors[]=__t('كلمة المرور مطلوبة','Password is required');
    if (!$errors) {
      $user = dbFetchOne("SELECT * FROM users WHERE email=? AND is_active=1",'s',$email);
      if ($user && password_verify($pass,$user['password'])) {
        loginUser($user);
        logActivity('login','User logged in');
        $redirect = $_SESSION['redirect_after_login'] ?? 'index.php';
        unset($_SESSION['redirect_after_login']);
        header('Location:'.$redirect); exit;
      } else {
        $errors[]=__t('البريد الإلكتروني أو كلمة المرور غير صحيحة','Incorrect email or password');
      }
    }
  }
}
$pageTitle = __t('تسجيل الدخول','Login');
$_no_header_init = true;
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
startSession();
$_lang=$_isAr=isAr();$_siteName=siteName();
?>
<!DOCTYPE html>
<html lang="<?= lang() ?>" dir="<?= appDir() ?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= __t('تسجيل الدخول','Login') ?> — <?= e(siteName()) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--red:#e63946;--red-d:#c1121f;--red-10:rgba(230,57,70,.10);--ink:#111317;--ink-2:#1e2128;--body:#414a57;--muted:#6b7380;--subtle:#9aa3af;--line:#e4e7ec;--line-2:#f1f3f6;--white:#ffffff;--r-sm:8px;--r-md:12px;--r-lg:16px;--r-xl:24px;--r-full:999px;--sh-lg:0 8px 32px rgba(0,0,0,.12),0 3px 8px rgba(0,0,0,.07);--font:<?= isAr()?"'Tajawal',sans-serif":"'Plus Jakarta Sans',sans-serif" ?>;}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{-webkit-tap-highlight-color:transparent;}
body{font-family:var(--font);color:var(--body);min-height:100vh;display:flex;-webkit-font-smoothing:antialiased;overflow-x:hidden;}
a{text-decoration:none;color:inherit;}
input{font-family:var(--font);}
button{font-family:var(--font);cursor:pointer;border:none;}

/* Left panel */
.auth-left{
  width:42%; background:var(--ink-2); display:flex; flex-direction:column;
  justify-content:center; position:relative; overflow:hidden; padding:60px 56px;
}
.auth-left::before{
  content:''; position:absolute; top:-80px; <?= isAr()?'left':'right' ?>:-80px;
  width:360px; height:360px; border-radius:50%;
  background:radial-gradient(circle,rgba(230,57,70,.2),transparent 70%);
}
.auth-left::after{
  content:''; position:absolute; bottom:-100px; <?= isAr()?'right':'left' ?>:-60px;
  width:300px; height:300px; border-radius:50%;
  background:radial-gradient(circle,rgba(230,57,70,.1),transparent 70%);
}
.al-logo{display:flex;align-items:center;gap:10px;margin-bottom:56px;position:relative;z-index:1;}
.al-logo-icon{width:42px;height:42px;background:var(--red);border-radius:var(--r-md);display:flex;align-items:center;justify-content:center;box-shadow:0 4px 14px rgba(230,57,70,.4);}
.al-logo-icon svg{width:22px;height:22px;stroke:#fff;fill:none;stroke-width:2;}
.al-logo-name{font-size:1.05rem;font-weight:800;color:#fff;}
.al-tagline{font-size:clamp(1.5rem,3vw,2rem);font-weight:900;color:#fff;line-height:1.2;margin-bottom:20px;position:relative;z-index:1;}
.al-tagline span{color:var(--red);}
.al-desc{font-size:.9rem;color:rgba(255,255,255,.45);line-height:1.7;margin-bottom:40px;position:relative;z-index:1;}
.al-features{display:flex;flex-direction:column;gap:14px;position:relative;z-index:1;}
.al-feat{display:flex;align-items:center;gap:12px;padding:12px 16px;border-radius:var(--r-md);border:1px solid rgba(255,255,255,.07);background:rgba(255,255,255,.04);}
.al-feat-icon{width:34px;height:34px;border-radius:var(--r-sm);background:rgba(230,57,70,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.al-feat-icon svg{width:16px;height:16px;color:var(--red);}
.al-feat-text{font-size:.84rem;color:rgba(255,255,255,.6);font-weight:500;}

/* Right panel */
.auth-right{
  flex:1; display:flex; align-items:center; justify-content:center;
  background:var(--line-2); padding:40px 24px;
}
.auth-form-wrap{
  width:100%; max-width:420px; background:var(--white);
  border-radius:var(--r-xl); padding:40px; box-shadow:var(--sh-lg);
}
.af-title{font-size:1.5rem;font-weight:900;color:var(--ink);margin-bottom:6px;}
.af-sub  {font-size:.88rem;color:var(--muted);margin-bottom:28px;}
.af-sub a{color:var(--red);font-weight:700;}

.inp-wrap{position:relative;margin-bottom:18px;}
.inp-label{display:block;font-size:.82rem;font-weight:600;color:var(--ink-2);margin-bottom:7px;}
.inp-label .req{color:var(--red);}
.inp{
  width:100%;padding:11px 14px 11px <?= isAr()?'14px':'44px' ?>;
  padding-<?= isAr()?'right':'left' ?>:44px;
  border:1.5px solid var(--line);border-radius:var(--r-md);
  font-size:.875rem;color:var(--ink);background:var(--white);
  outline:none;transition:border-color .2s,box-shadow .2s;font-family:var(--font);
}
.inp:focus{border-color:var(--red);box-shadow:0 0 0 3px var(--red-10);}
.inp.err{border-color:#f87171;background:#fff5f5;}
.inp-icon{
  position:absolute;top:50%;transform:translateY(-50%);
  <?= isAr()?'right':'left' ?>:13px;color:var(--subtle);pointer-events:none;
  display:flex;align-items:center;justify-content:center;
}
.inp-icon svg{width:17px;height:17px;}
.inp-toggle{
  position:absolute;top:50%;transform:translateY(-50%);
  <?= isAr()?'left':'right' ?>:13px;color:var(--subtle);cursor:pointer;
  display:flex;align-items:center;justify-content:center;background:none;border:none;
  padding:4px;
}
.inp-toggle:hover{color:var(--red);}
.inp-toggle svg{width:16px;height:16px;}

.err-list{background:#fff5f5;border:1px solid #fca5a5;border-radius:var(--r-md);padding:12px 16px;margin-bottom:20px;}
.err-item{display:flex;align-items:center;gap:8px;font-size:.84rem;color:#991b1b;padding:3px 0;}
.err-item svg{width:14px;height:14px;flex-shrink:0;}

.submit-btn{
  width:100%;height:48px;border-radius:var(--r-full);
  background:var(--red);color:#fff;font-size:.95rem;font-weight:700;
  border:none;cursor:pointer;font-family:var(--font);
  transition:all .2s;box-shadow:0 4px 14px rgba(230,57,70,.3);
  display:flex;align-items:center;justify-content:center;gap:8px;
}
.submit-btn:hover{background:var(--red-d);transform:translateY(-1px);box-shadow:0 6px 20px rgba(230,57,70,.4);}
.submit-btn:active{transform:scale(.97);}

.divider{display:flex;align-items:center;gap:12px;margin:24px 0;}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--line);}
.divider span{font-size:.78rem;color:var(--subtle);white-space:nowrap;}

.forgot-link{font-size:.8rem;color:var(--muted);float:<?= isAr()?'left':'right' ?>;transition:color .2s;}
.forgot-link:hover{color:var(--red);}

@media(max-width:768px){
  .auth-left{display:none;}
  .auth-right{padding:24px 16px;}
  body{display:block;}
}
</style>
</head>
<body>
<div class="auth-left">
  <a href="index.php" class="al-logo">
    <div class="al-logo-icon"><svg viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg></div>
    <span class="al-logo-name"><?= e(siteName()) ?></span>
  </a>
  <h2 class="al-tagline"><?= __t('مرحباً بك في<br><span>أفضل تجربة</span><br>تسوق إلكتروني','Welcome to the<br><span>Best Shopping</span><br>Experience') ?></h2>
  <p class="al-desc"><?= __t('سجل دخولك الآن للوصول إلى آلاف المنتجات بأفضل الأسعار مع شحن سريع وضمان الجودة.','Login now to access thousands of products at the best prices with fast shipping and quality guarantee.') ?></p>
  <div class="al-features">
    <?php foreach ([
      ['M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H11a1 1 0 001-1V5a1 1 0 00-1-1H3zM14 7a1 1 0 00-1 1v6.05A2.5 2.5 0 0115.95 16H17a1 1 0 001-1v-5a1 1 0 00-.293-.707L16 7.586A1 1 0 0015.293 7H14z', __t('شحن مجاني وسريع','Free Fast Shipping')],
      ['M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', __t('ضمان الجودة والأصالة','Quality & Authenticity Guaranteed')],
      ['M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z', __t('الدفع الآمن عند الاستلام','Safe Cash on Delivery')],
    ] as [$path, $text]): ?>
    <div class="al-feat">
      <div class="al-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="16" height="16"><path d="<?= $path ?>"/></svg></div>
      <span class="al-feat-text"><?= $text ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="auth-right">
  <div class="auth-form-wrap">
    <h1 class="af-title"><?= __t('تسجيل الدخول','Sign In') ?></h1>
    <p class="af-sub"><?= __t('ليس لديك حساب؟','Don\'t have an account?') ?> <a href="register.php"><?= __t('إنشاء حساب جديد','Create one') ?></a></p>

    <?php if ($errors): ?>
    <div class="err-list">
      <?php foreach ($errors as $e_): ?>
      <div class="err-item">
        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
        <?= e($e_) ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="loginForm">
      <?= csrfInput() ?>

      <div style="margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <label class="inp-label" for="email"><?= __t('البريد الإلكتروني','Email Address') ?> <span class="req">*</span></label>
      </div>
      <div class="inp-wrap" style="margin-bottom:18px;">
        <div class="inp-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
        <input type="email" id="email" name="email" class="inp <?= $errors?'err':'' ?>"
               placeholder="example@email.com" value="<?= e($email_val) ?>" required autocomplete="email">
      </div>

      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:7px;">
        <label class="inp-label" style="margin-bottom:0;" for="password"><?= __t('كلمة المرور','Password') ?> <span class="req">*</span></label>
        <a href="#" class="forgot-link"><?= __t('نسيت كلمة المرور؟','Forgot password?') ?></a>
      </div>
      <div class="inp-wrap" style="margin-bottom:24px;">
        <div class="inp-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg></div>
        <input type="password" id="password" name="password" class="inp <?= $errors?'err':'' ?>"
               placeholder="••••••••" required autocomplete="current-password">
        <button type="button" class="inp-toggle" onclick="togglePw('password',this)">
          <svg id="eye-password" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>

      <label style="display:flex; align-items:center; gap:8px; margin-bottom:24px; cursor:pointer; font-size:.86rem; color:var(--muted);">
        <input type="checkbox" name="remember" style="accent-color:var(--red); width:15px; height:15px;">
        <?= __t('تذكرني','Remember me') ?>
      </label>

      <button type="submit" class="submit-btn">
        <svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><path fill-rule="evenodd" d="M3 3a1 1 0 011 1v12a1 1 0 11-2 0V4a1 1 0 011-1zm7.707 3.293a1 1 0 010 1.414L9.414 9H17a1 1 0 110 2H9.414l1.293 1.293a1 1 0 01-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
        <?= __t('تسجيل الدخول','Sign In') ?>
      </button>
    </form>

    <div class="divider"><span><?= __t('أو','OR') ?></span></div>

    <div style="text-align:center; padding:14px; background:var(--line-2); border-radius:var(--r-md); font-size:.82rem; color:var(--muted);">
      <strong style="color:var(--ink); display:block; margin-bottom:4px;"><?= __t('للتجربة السريعة','Quick Demo') ?></strong>
      admin@store.com / Admin@123
    </div>

    <div style="text-align:center; margin-top:20px;">
      <a href="index.php" style="font-size:.82rem; color:var(--muted); display:inline-flex; align-items:center; gap:5px;">
        <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M<?= isAr()?'7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z':'12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z' ?>" clip-rule="evenodd"/></svg>
        <?= __t('العودة للمتجر','Back to Store') ?>
      </a>
    </div>
  </div>
</div>
<script>
function togglePw(id,btn){
  const inp=document.getElementById(id);
  const show=inp.type==='password';
  inp.type=show?'text':'password';
  btn.querySelector('svg').innerHTML=show
    ?'<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>'
    :'<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
}
</script>
</body>
</html>