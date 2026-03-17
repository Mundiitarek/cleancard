<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
startSession();
if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$errors=[]; $vals=[];
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!verifyCsrfToken($_POST['csrf_token']??'')) { $errors[]='Invalid token'; }
  else {
    $vals['name']  = sanitize($_POST['name']??'');
    $vals['email'] = sanitize($_POST['email']??'');
    $vals['phone'] = sanitize($_POST['phone']??'');
    $pass    = $_POST['password']??'';
    $confirm = $_POST['confirm']??'';
    if (mb_strlen($vals['name'])<2)    $errors[]=__t('الاسم 2 أحرف على الأقل','Name must be at least 2 characters');
    if (!validateEmail($vals['email'])) $errors[]=__t('البريد الإلكتروني غير صالح','Invalid email address');
    else {
      $exists = dbFetchOne("SELECT id FROM users WHERE email=?",'s',$vals['email']);
      if ($exists) $errors[]=__t('البريد الإلكتروني مستخدم بالفعل','Email already in use');
    }
    if (mb_strlen($pass)<6)   $errors[]=__t('كلمة المرور 6 أحرف على الأقل','Password at least 6 characters');
    if ($pass!==$confirm)     $errors[]=__t('كلمتا المرور غير متطابقتان','Passwords do not match');
    if (!isset($_POST['terms'])) $errors[]=__t('يجب الموافقة على الشروط والأحكام','You must accept the terms');
    if (!$errors) {
      $uid = dbInsert('users',['name'=>$vals['name'],'email'=>$vals['email'],'phone'=>$vals['phone'],'password'=>password_hash($pass,PASSWORD_BCRYPT),'role'=>'customer','is_active'=>1,'lang'=>lang()]);
      $user = dbFetchOne("SELECT * FROM users WHERE id=?",'i',$uid);
      loginUser($user);
      logActivity('register','New user registered');
      header('Location: dashboard.php'); exit;
    }
  }
}
$pageTitle=__t('إنشاء حساب','Register');
?>
<!DOCTYPE html>
<html lang="<?= lang() ?>" dir="<?= appDir() ?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= __t('إنشاء حساب','Register') ?> — <?= e(siteName()) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--red:#e63946;--red-d:#c1121f;--red-10:rgba(230,57,70,.10);--ink:#111317;--ink-2:#1e2128;--body:#414a57;--muted:#6b7380;--subtle:#9aa3af;--line:#e4e7ec;--line-2:#f1f3f6;--white:#ffffff;--r-sm:8px;--r-md:12px;--r-lg:16px;--r-xl:24px;--r-full:999px;--sh-lg:0 8px 32px rgba(0,0,0,.12);--font:<?= isAr()?"'Tajawal',sans-serif":"'Plus Jakarta Sans',sans-serif" ?>;}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:var(--font);color:var(--body);min-height:100vh;display:flex;-webkit-font-smoothing:antialiased;overflow-x:hidden;}
a{text-decoration:none;color:inherit;}
input{font-family:var(--font);}
button{font-family:var(--font);cursor:pointer;border:none;}

.auth-left{width:42%;background:var(--ink-2);display:flex;flex-direction:column;justify-content:center;position:relative;overflow:hidden;padding:60px 56px;}
.auth-left::before{content:'';position:absolute;top:-80px;<?= isAr()?'left':'right' ?>:-80px;width:360px;height:360px;border-radius:50%;background:radial-gradient(circle,rgba(230,57,70,.2),transparent 70%);}
.auth-right{flex:1;display:flex;align-items:center;justify-content:center;background:var(--line-2);padding:40px 24px;overflow-y:auto;}
.auth-form-wrap{width:100%;max-width:460px;background:var(--white);border-radius:var(--r-xl);padding:40px;box-shadow:var(--sh-lg);}
.al-logo{display:flex;align-items:center;gap:10px;margin-bottom:48px;position:relative;z-index:1;}
.al-logo-icon{width:42px;height:42px;background:var(--red);border-radius:var(--r-md);display:flex;align-items:center;justify-content:center;box-shadow:0 4px 14px rgba(230,57,70,.4);}
.al-logo-icon svg{width:22px;height:22px;stroke:#fff;fill:none;stroke-width:2;}
.al-logo-name{font-size:1.05rem;font-weight:800;color:#fff;}
.al-head{font-size:clamp(1.4rem,3vw,1.9rem);font-weight:900;color:#fff;line-height:1.2;margin-bottom:16px;position:relative;z-index:1;}
.al-head span{color:var(--red);}
.al-sub{font-size:.88rem;color:rgba(255,255,255,.45);line-height:1.7;margin-bottom:36px;position:relative;z-index:1;}

/* Steps indicator */
.steps{display:flex;gap:0;margin-bottom:32px;}
.step{display:flex;align-items:center;flex:1;}
.step-circle{width:28px;height:28px;border-radius:50%;border:2px solid var(--line);display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:800;color:var(--subtle);flex-shrink:0;transition:all .3s;}
.step.done .step-circle{background:var(--red);border-color:var(--red);color:#fff;}
.step.active .step-circle{border-color:var(--red);color:var(--red);}
.step-line{flex:1;height:2px;background:var(--line);margin:0 6px;transition:background .3s;}
.step.done .step-line{background:var(--red);}
.step-label{font-size:.7rem;color:var(--subtle);margin-top:4px;white-space:nowrap;}

.inp-wrap{position:relative;margin-bottom:16px;}
.inp-label{display:block;font-size:.82rem;font-weight:600;color:var(--ink-2);margin-bottom:7px;}
.inp-label .req{color:var(--red);}
.inp{width:100%;padding:11px 14px 11px <?= isAr()?'14px':'44px' ?>;padding-<?= isAr()?'right':'left' ?>:44px;border:1.5px solid var(--line);border-radius:var(--r-md);font-size:.875rem;color:var(--ink);background:var(--white);outline:none;transition:border-color .2s,box-shadow .2s;font-family:var(--font);}
.inp:focus{border-color:var(--red);box-shadow:0 0 0 3px var(--red-10);}
.inp.err{border-color:#f87171;background:#fff5f5;}
.inp.ok{border-color:#10b981;}
.inp-icon{position:absolute;top:50%;transform:translateY(-50%);<?= isAr()?'right':'left' ?>:13px;color:var(--subtle);pointer-events:none;display:flex;align-items:center;}
.inp-icon svg{width:17px;height:17px;}
.inp-toggle{position:absolute;top:50%;transform:translateY(-50%);<?= isAr()?'left':'right' ?>:13px;color:var(--subtle);cursor:pointer;background:none;border:none;padding:4px;display:flex;}
.inp-toggle:hover{color:var(--red);}
.inp-toggle svg{width:16px;height:16px;}

/* Password strength */
.pw-strength{margin-top:8px;}
.pw-bars{display:flex;gap:4px;height:4px;border-radius:2px;overflow:hidden;margin-bottom:4px;}
.pw-bar{flex:1;background:var(--line);border-radius:2px;transition:background .3s;}
.pw-label{font-size:.74rem;color:var(--subtle);}

/* Grid 2 */
.fg2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}

.err-list{background:#fff5f5;border:1px solid #fca5a5;border-radius:var(--r-md);padding:12px 16px;margin-bottom:20px;}
.err-item{display:flex;align-items:center;gap:8px;font-size:.82rem;color:#991b1b;padding:3px 0;}
.err-item svg{width:14px;height:14px;flex-shrink:0;}

.submit-btn{width:100%;height:48px;border-radius:var(--r-full);background:var(--red);color:#fff;font-size:.95rem;font-weight:700;border:none;cursor:pointer;font-family:var(--font);transition:all .2s;box-shadow:0 4px 14px rgba(230,57,70,.3);display:flex;align-items:center;justify-content:center;gap:8px;margin-top:8px;}
.submit-btn:hover{background:var(--red-d);transform:translateY(-1px);}
.submit-btn:active{transform:scale(.97);}

.email-status{position:absolute;top:50%;transform:translateY(-50%);<?= isAr()?'left':'right' ?>:13px;display:flex;align-items:center;}
.email-status svg{width:16px;height:16px;}

@media(max-width:768px){.auth-left{display:none;}.auth-right{padding:20px 16px;}body{display:block;}.fg2{grid-template-columns:1fr;}}
</style>
</head>
<body>
<div class="auth-left">
  <a href="index.php" class="al-logo">
    <div class="al-logo-icon"><svg viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg></div>
    <span class="al-logo-name"><?= e(siteName()) ?></span>
  </a>
  <h2 class="al-head"><?= __t('انضم إلى<br>مجتمع <span>المتسوقين</span><br>الأذكياء','Join The<br><span>Smart Shoppers</span><br>Community') ?></h2>
  <p class="al-sub"><?= __t('أنشئ حسابك مجاناً واستمتع بتجربة تسوق لا مثيل لها مع عروض حصرية لأعضائنا.','Create your free account and enjoy an unmatched shopping experience with exclusive member offers.') ?></p>
  <div style="position:relative;z-index:1;display:flex;flex-direction:column;gap:14px;">
    <?php foreach ([
      ['M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', __t('حساب مجاني 100%','100% Free Account')],
      ['M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z', __t('بياناتك آمنة ومحمية','Your data is safe and protected')],
      ['M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z', __t('عروض حصرية للأعضاء','Exclusive member deals')],
    ] as [$path,$text]): ?>
    <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:var(--r-md);border:1px solid rgba(255,255,255,.07);background:rgba(255,255,255,.04);">
      <div style="width:32px;height:32px;border-radius:var(--r-sm);background:rgba(230,57,70,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <svg viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="1.8" width="15" height="15"><path d="<?= $path ?>"/></svg>
      </div>
      <span style="font-size:.84rem;color:rgba(255,255,255,.55);"><?= $text ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="auth-right">
  <div class="auth-form-wrap">
    <div style="margin-bottom:24px;">
      <h1 style="font-size:1.5rem;font-weight:900;color:var(--ink);margin-bottom:6px;"><?= __t('إنشاء حساب جديد','Create New Account') ?></h1>
      <p style="font-size:.88rem;color:var(--muted);"><?= __t('لديك حساب بالفعل؟','Already have an account?') ?> <a href="login.php" style="color:var(--red);font-weight:700;"><?= __t('سجل دخولك','Sign in') ?></a></p>
    </div>

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

    <form method="POST" id="regForm">
      <?= csrfInput() ?>

      <!-- Name -->
      <div class="inp-wrap">
        <label class="inp-label" for="name"><?= __t('الاسم الكامل','Full Name') ?> <span class="req">*</span></label>
        <div class="inp-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
        <input type="text" id="name" name="name" class="inp <?= $errors&&!($vals['name']??'')?'err':'' ?>"
               placeholder="<?= __t('أدخل اسمك الكامل','Enter your full name') ?>"
               value="<?= e($vals['name']??'') ?>" required>
      </div>

      <!-- Email -->
      <div class="inp-wrap" style="position:relative;">
        <label class="inp-label" for="email"><?= __t('البريد الإلكتروني','Email Address') ?> <span class="req">*</span></label>
        <div class="inp-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
        <input type="email" id="email" name="email" class="inp <?= $errors&&!($vals['email']??'')?'err':'' ?>"
               placeholder="example@email.com"
               value="<?= e($vals['email']??'') ?>" required
               oninput="checkEmailAvail(this)">
        <div class="email-status" id="emailStatus"></div>
      </div>

      <!-- Phone -->
      <div class="inp-wrap">
        <label class="inp-label" for="phone"><?= __t('رقم الهاتف','Phone Number') ?></label>
        <div class="inp-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 8.81 19.79 19.79 0 011 2.22 2 2 0 012.92 0h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L7.06 7.76a16 16 0 006.18 6.18l1.12-1.11a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 14.92z"/></svg></div>
        <input type="tel" id="phone" name="phone" class="inp" placeholder="01xxxxxxxxx" value="<?= e($vals['phone']??'') ?>">
      </div>

      <!-- Password row -->
      <div class="fg2">
        <div>
          <div class="inp-wrap">
            <label class="inp-label" for="password"><?= __t('كلمة المرور','Password') ?> <span class="req">*</span></label>
            <div class="inp-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg></div>
            <input type="password" id="password" name="password" class="inp" placeholder="••••••••" required minlength="6" oninput="updateStrength(this.value)">
            <button type="button" class="inp-toggle" onclick="togglePw('password',this)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
          <div class="pw-strength">
            <div class="pw-bars" id="pwBars"><div class="pw-bar"></div><div class="pw-bar"></div><div class="pw-bar"></div><div class="pw-bar"></div></div>
            <span class="pw-label" id="pwLabel"><?= __t('أدخل كلمة المرور','Enter password') ?></span>
          </div>
        </div>
        <div class="inp-wrap">
          <label class="inp-label" for="confirm"><?= __t('تأكيد المرور','Confirm') ?> <span class="req">*</span></label>
          <div class="inp-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg></div>
          <input type="password" id="confirm" name="confirm" class="inp" placeholder="••••••••" required oninput="checkMatch(this)">
        </div>
      </div>

      <!-- Terms -->
      <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;font-size:.84rem;color:var(--muted);margin-bottom:20px;line-height:1.5;">
        <input type="checkbox" name="terms" id="terms" style="accent-color:var(--red);width:15px;height:15px;margin-top:2px;flex-shrink:0;" required>
        <?= __t('أوافق على','I agree to') ?> <a href="#" style="color:var(--red);font-weight:600;"><?= __t('الشروط والأحكام','Terms & Conditions') ?></a> <?= __t('وسياسة الخصوصية','and Privacy Policy') ?>
      </label>

      <button type="submit" class="submit-btn">
        <svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>
        <?= __t('إنشاء الحساب الآن','Create Account Now') ?>
      </button>
    </form>

    <div style="text-align:center;margin-top:20px;">
      <a href="index.php" style="font-size:.82rem;color:var(--muted);display:inline-flex;align-items:center;gap:5px;">
        <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M<?= isAr()?'7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z':'12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z' ?>" clip-rule="evenodd"/></svg>
        <?= __t('العودة للمتجر','Back to Store') ?>
      </a>
    </div>
  </div>
</div>
<script>
function togglePw(id,btn){
  const i=document.getElementById(id);const s=i.type==='password';i.type=s?'text':'password';
  btn.querySelector('svg').innerHTML=s?'<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>':'<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
}
function updateStrength(v){
  let s=0;
  if(v.length>=6)s++;if(v.length>=10)s++;
  if(/[A-Z]/.test(v)||/[a-z]/.test(v))s++;if(/[0-9]/.test(v))s++;
  const cols=['#fee2e2','#fed7aa','#fef3c7','#d1fae5'];
  const labels=['<?= __t('ضعيف','Weak') ?>','<?= __t('مقبول','Fair') ?>','<?= __t('جيد','Good') ?>','<?= __t('قوي','Strong') ?>'];
  const bars=document.querySelectorAll('.pw-bar');
  bars.forEach((b,i)=>{b.style.background=i<s?(s===1?'#ef4444':s===2?'#f97316':s===3?'#eab308':'#10b981'):'var(--line)';});
  const lbl=document.getElementById('pwLabel');
  if(v.length)lbl.textContent=labels[s-1]||labels[0];
  else lbl.textContent='<?= __t('أدخل كلمة المرور','Enter password') ?>';
}
function checkMatch(inp){
  const pw=document.getElementById('password').value;
  inp.classList.toggle('ok',inp.value&&inp.value===pw);
  inp.classList.toggle('err',inp.value&&inp.value!==pw);
}
let emailTimer;
function checkEmailAvail(inp){
  clearTimeout(emailTimer);
  const st=document.getElementById('emailStatus');
  if(!inp.value||!inp.value.includes('@')){st.innerHTML='';return;}
  st.innerHTML='<svg style="animation:spin .6s linear infinite" viewBox="0 0 24 24" fill="none" stroke="#9aa3af" stroke-width="2" width="15" height="15"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>';
  emailTimer=setTimeout(()=>{
    fetch('check-email.php?email='+encodeURIComponent(inp.value))
    .then(r=>r.json()).then(d=>{
      if(d.available){
        inp.classList.remove('err');inp.classList.add('ok');
        st.innerHTML='<svg viewBox="0 0 20 20" fill="#10b981" width="16" height="16"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>';
      } else {
        inp.classList.add('err');inp.classList.remove('ok');
        st.innerHTML='<svg viewBox="0 0 20 20" fill="#ef4444" width="16" height="16"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>';
      }
    });
  },600);
}
document.head.insertAdjacentHTML('beforeend','<style>@keyframes spin{to{transform:rotate(360deg)}}</style>');
</script>
</body>
</html>