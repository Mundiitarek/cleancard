</main>
<?php
$_isAr     = isAr();
$_settings = getAllSettings();
$_cats     = getCategories();
$_siteName = siteName();
?>

<style>
/* ══════════════════════
   FOOTER STYLES
══════════════════════ */

/* Trust bar */
.ft-trust {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
}
.ft-trust-item {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 20px 18px;
  border-<?= $_isAr?'left':'right' ?>: 1px solid rgba(255,255,255,.06);
}
.ft-trust-item:<?= $_isAr?'first':'last' ?>-child {
  border: none;
}
.ft-trust-icon {
  width: 40px; height: 40px;
  border-radius: var(--r-md);
  background: var(--red-20);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}

/* Main footer grid */
.ft-main-grid {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr;
  gap: 40px;
}

/* Newsletter form */
.ft-nl-form {
  display: flex;
  gap: 0;
  max-width: 440px;
  margin: 0 auto;
  border-radius: var(--r-full);
  overflow: hidden;
  border: 1.5px solid rgba(255,255,255,.12);
  background: rgba(255,255,255,.05);
}
.ft-nl-form input {
  flex: 1;
  background: transparent;
  border: none;
  outline: none;
  padding: 12px 18px;
  font-size: .875rem;
  color: #fff;
  font-family: var(--font);
  min-width: 0;
}
.ft-nl-form input::placeholder { color: rgba(255,255,255,.3); }
.ft-nl-btn {
  border-radius: 0 var(--r-full) var(--r-full) 0;
  padding: 0 20px;
  flex-shrink: 0;
}

/* Social icons */
.ft-socials { display: flex; gap: 8px; flex-wrap: wrap; }
.ft-social-btn {
  width: 36px; height: 36px;
  border-radius: var(--r-sm);
  border: 1px solid rgba(255,255,255,.1);
  display: flex; align-items: center; justify-content: center;
  color: rgba(255,255,255,.4);
  transition: all var(--t-base);
  text-decoration: none;
}
.ft-social-btn:hover { background: var(--red); border-color: var(--red); color: #fff; }

/* Footer links */
.ft-links { display: flex; flex-direction: column; gap: 10px; list-style: none; }
.ft-link {
  font-size: .84rem;
  color: rgba(255,255,255,.45);
  display: flex; align-items: center; gap: 6px;
  transition: all var(--t-base);
  text-decoration: none;
}
.ft-link:hover { color: #fff; }
.ft-link svg { color: var(--red); flex-shrink: 0; }

/* Footer heading */
.ft-heading {
  font-size: .8rem;
  font-weight: 800;
  color: #fff;
  text-transform: uppercase;
  letter-spacing: .08em;
  margin-bottom: 18px;
}

/* Contact items */
.ft-contacts { display: flex; flex-direction: column; gap: 12px; list-style: none; }
.ft-contact-item { display: flex; align-items: flex-start; gap: 10px; }
.ft-contact-icon {
  width: 30px; height: 30px;
  border-radius: var(--r-sm);
  background: var(--red-20);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; margin-top: 1px;
}
.ft-contact-icon svg { color: var(--red); }
.ft-contact-val { font-size: .82rem; color: rgba(255,255,255,.45); line-height: 1.6; }

/* COD badge */
.ft-cod {
  display: flex; align-items: center; gap: 10px;
  margin-top: 20px; padding: 12px;
  border-radius: var(--r-md);
  border: 1px solid rgba(255,255,255,.08);
  background: rgba(255,255,255,.03);
}

/* Bottom bar */
.ft-bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
}
.ft-bottom-links { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
.ft-bottom-link {
  font-size: .75rem;
  color: rgba(255,255,255,.3);
  text-decoration: none;
  transition: color var(--t-base);
}
.ft-bottom-link:hover { color: rgba(255,255,255,.7); }

/* ══════════════════════
   RESPONSIVE
══════════════════════ */

/* Tablet */
@media(max-width:900px) {
  .ft-trust {
    grid-template-columns: repeat(2, 1fr);
  }
  .ft-trust-item {
    border-bottom: 1px solid rgba(255,255,255,.06);
  }
  .ft-trust-item:nth-child(<?= $_isAr?'even':'odd' ?>) {
    border-<?= $_isAr?'left':'right' ?>: 1px solid rgba(255,255,255,.06);
  }
  .ft-trust-item:<?= $_isAr?'first':'last' ?>-child,
  .ft-trust-item:nth-last-child(2) {
    border-bottom: none;
  }
  .ft-main-grid {
    grid-template-columns: 1fr 1fr;
    gap: 32px;
  }
}

/* Mobile */
@media(max-width:600px) {
  .ft-trust {
    grid-template-columns: 1fr 1fr;
    gap: 0;
  }
  .ft-trust-item {
    padding: 14px 12px;
    gap: 10px;
  }
  .ft-trust-icon { width: 34px; height: 34px; }

  .ft-main-grid {
    grid-template-columns: 1fr;
    gap: 28px;
  }

  .ft-nl-form { max-width: 100%; }

  .ft-bottom {
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
  }
  .ft-bottom-links { gap: 10px; }
}

/* Very small */
@media(max-width:380px) {
  .ft-trust { grid-template-columns: 1fr; }
  .ft-trust-item { border-<?= $_isAr?'left':'right' ?>: none !important; }
}
</style>

<footer>

<!-- ── Newsletter ── -->
<div style="background:var(--ink-2); padding:44px 0;">
  <div class="container">
    <div style="max-width:580px; margin:0 auto; text-align:center;">
      <div class="t-tag" style="margin-bottom:14px; background:rgba(230,57,70,.2); color:var(--red-l);">
        <svg viewBox="0 0 20 20" fill="currentColor" width="12" height="12"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/></svg>
        <?= __t('النشرة البريدية','Newsletter') ?>
      </div>
      <h3 style="font-size:clamp(1.2rem,3vw,1.75rem); font-weight:800; color:#fff; margin-bottom:10px; line-height:1.25;">
        <?= __t('اشترك واحصل على <span style="color:var(--red-l)">10%</span> خصم','Subscribe & get <span style="color:var(--red-l)">10%</span> off') ?>
      </h3>
      <p style="color:rgba(255,255,255,.45); font-size:.86rem; margin-bottom:22px;">
        <?= __t('كن أول من يعلم بأحدث العروض والمنتجات','Be first to know about deals & new products') ?>
      </p>
      <form class="ft-nl-form">
        <input type="email" placeholder="<?= __t('بريدك الإلكتروني...','Your email...') ?>">
        <button type="submit" class="btn btn-primary ft-nl-btn">
          <?= __t('اشترك','Subscribe') ?>
        </button>
      </form>
    </div>
  </div>
</div>

<!-- ── Trust bar ── -->
<div style="background:var(--ink); border-bottom:1px solid rgba(255,255,255,.06);">
  <div class="container">
    <div class="ft-trust">
      <?php
      $features = [
        ['M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H11a1 1 0 001-1V5a1 1 0 00-1-1H3zM14 7a1 1 0 00-1 1v6.05A2.5 2.5 0 0115.95 16H17a1 1 0 001-1v-5a1 1 0 00-.293-.707L16 7.586A1 1 0 0015.293 7H14z',
          __t('شحن سريع','Fast Shipping'), __t('توصيل 24-48 ساعة','24-48 hour delivery')],
        ['M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
          __t('ضمان الجودة','Quality Guarantee'), __t('منتجات أصلية 100%','100% authentic products')],
        ['M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z',
          __t('إرجاع مجاني','Free Returns'), __t('خلال 14 يوماً','Within 14 days')],
        ['M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z',
          __t('دعم 24/7','24/7 Support'), __t('نحن دائماً هنا','We\'re always here')],
      ];
      foreach ($features as [$path, $title, $desc]): ?>
      <div class="ft-trust-item">
        <div class="ft-trust-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="#e63946" stroke-width="1.8" width="19" height="19"><path d="<?= $path ?>"/></svg>
        </div>
        <div>
          <div style="font-size:.83rem; font-weight:700; color:#fff; margin-bottom:2px;"><?= $title ?></div>
          <div style="font-size:.73rem; color:rgba(255,255,255,.38);"><?= $desc ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ── Main footer ── -->
<div style="background:var(--ink); padding:52px 0 40px;">
  <div class="container">
    <div class="ft-main-grid">

      <!-- Brand col -->
      <div>
        <a href="index.php" class="logo" style="margin-bottom:16px; display:inline-flex; text-decoration:none;">
          <div class="logo__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
          </div>
          <div class="logo__text">
            <span class="logo__name" style="color:#fff;"><?= e($_siteName) ?></span>
          </div>
        </a>
        <p style="font-size:.84rem; color:rgba(255,255,255,.4); line-height:1.75; margin-bottom:20px; max-width:270px;">
          <?= __t('متجرك الإلكتروني الموثوق لأفضل المنتجات بأسعار تنافسية وشحن سريع لكل مكان.','Your trusted online store for the best products at competitive prices with fast shipping everywhere.') ?>
        </p>
        <div class="ft-socials">
          <?php
          $socials = [
            ['facebook',  $_settings['facebook']??'#',  'M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z'],
            ['instagram', $_settings['instagram']??'#', 'M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37zm1.5-4.87h.01M6.5 19.5h11a2 2 0 002-2v-11a2 2 0 00-2-2h-11a2 2 0 00-2 2v11a2 2 0 002 2z'],
            ['whatsapp',  'https://wa.me/'.($_settings['whatsapp']??''), 'M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z M11.998 2C6.477 2 2 6.477 2 12c0 1.99.554 3.85 1.517 5.44L2 22l4.668-1.496A9.96 9.96 0 0012 22c5.523 0 10-4.477 10-10S17.523 2 11.998 2z'],
          ];
          foreach ($socials as [$name, $url, $path]): ?>
          <a href="<?= e($url) ?>" target="_blank" rel="noopener" class="ft-social-btn" aria-label="<?= $name ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="15" height="15"><path d="<?= $path ?>"/></svg>
          </a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Quick Links -->
      <div>
        <div class="ft-heading"><?= __t('روابط سريعة','Quick Links') ?></div>
        <ul class="ft-links">
          <?php foreach([
            ['index.php',            __t('الرئيسية','Home')],
            ['shop.php',             __t('المتجر','Shop')],
            ['shop.php?filter=new',  __t('وصل حديثاً','New Arrivals')],
            ['shop.php?filter=sale', __t('العروض','Deals')],
            ['wishlist.php',         __t('المفضلة','Wishlist')],
            ['cart.php',             __t('السلة','Cart')],
          ] as [$href, $label]): ?>
          <li>
            <a href="<?= $href ?>" class="ft-link">
              <svg viewBox="0 0 20 20" fill="currentColor" width="11" height="11"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
              <?= $label ?>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- Categories -->
      <div>
        <div class="ft-heading"><?= __t('الأقسام','Categories') ?></div>
        <ul class="ft-links">
          <?php foreach (array_slice($_cats,0,6) as $c): ?>
          <li>
            <a href="shop.php?cat=<?= urlencode($c['slug']) ?>" class="ft-link">
              <svg viewBox="0 0 20 20" fill="currentColor" width="11" height="11"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
              <?= e(t($c,'name')) ?>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- Contact -->
      <div id="contact">
        <div class="ft-heading"><?= __t('تواصل معنا','Contact Us') ?></div>
        <ul class="ft-contacts">
          <?php
          $contacts = [];
          if (!empty($_settings['phone']))   $contacts[] = ['M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z', $_settings['phone']];
          if (!empty($_settings['email']))   $contacts[] = ['M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z', $_settings['email']];
          if (!empty($_settings['address'])) $contacts[] = ['M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z', $_settings['address']];
          foreach ($contacts as [$path, $val]): ?>
          <li class="ft-contact-item">
            <div class="ft-contact-icon">
              <svg viewBox="0 0 20 20" fill="currentColor" width="13" height="13"><path d="<?= $path ?>"/></svg>
            </div>
            <span class="ft-contact-val"><?= e($val) ?></span>
          </li>
          <?php endforeach; ?>
        </ul>
        <div class="ft-cod">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="22" height="22" style="color:var(--red); flex-shrink:0;"><path d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
          <div>
            <div style="font-size:.8rem; font-weight:700; color:#fff;"><?= __t('الدفع عند الاستلام','Cash on Delivery') ?></div>
            <div style="font-size:.71rem; color:rgba(255,255,255,.35);"><?= __t('آمن وسهل','Safe & Easy') ?></div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- ── Bottom bar ── -->
<div style="background:#0d0f12; padding:16px 0;">
  <div class="container">
    <div class="ft-bottom">
      <p style="font-size:.76rem; color:rgba(255,255,255,.28);">
        &copy; <?= date('Y') ?> <?= e($_siteName) ?>. <?= __t('جميع الحقوق محفوظة','All rights reserved') ?>.
      </p>
      <div class="ft-bottom-links">
        <?php foreach([
          ['#', __t('سياسة الخصوصية','Privacy Policy')],
          ['#', __t('الشروط والأحكام','Terms of Service')],
          ['#', __t('سياسة الإرجاع','Return Policy')],
        ] as [$href, $label]): ?>
        <a href="<?= $href ?>" class="ft-bottom-link"><?= $label ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

</footer>
</body>
</html>