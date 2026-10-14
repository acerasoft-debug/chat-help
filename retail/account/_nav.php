<?php
/**
 * Müşteri alanının ortak iskeleti: üst bant, yan menü, doğrulama uyarısı ve
 * giriş/kayıt sayfalarının iki sütunlu çerçevesi.
 *
 * Operatör, 14 Eki 2026: "kullanıcı hesabını eksiksiz ve estetik şekilde
 * yap". Her hesap sayfası aynı iskeleti kullanıyor ki menü, başlık ve boşluk
 * dili sayfadan sayfaya değişmesin:
 *
 *   vr_acc_open($me, 'orders.php', t('acc_nav_orders'), t('acc_orders_lead'));
 *   … sayfanın kendisi …
 *   vr_acc_close();
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/geo.php';

/** Menü: dosya ⇒ [etiket anahtarı, ikon]. Merkliste mağazanın kendi sayfası. */
function vr_acc_items(): array
{
    return [
        'index.php'       => ['acc_nav_overview',  'home'],
        'orders.php'      => ['acc_nav_orders',    'bag'],
        'addresses.php'   => ['acc_nav_addresses', 'truck'],
        '../wishlist.php' => ['acc_nav_wishlist',  'heart'],
        'profile.php'     => ['acc_nav_profile',   'user'],
        'preferences.php' => ['acc_nav_prefs',     'bell'],
        'security.php'    => ['acc_nav_security',  'lock'],
    ];
}

/** Ad ya da e-postadan iki harflik monogram. */
function vr_acc_initials(array $me): string
{
    $n = trim((string)($me['name'] ?? ''));
    if ($n === '') $n = (string)strtok((string)($me['email'] ?? ''), '@');
    $parts = preg_split('/[\s._-]+/u', $n, -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
    $a = mb_substr($parts[0], 0, 1);
    $b = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : mb_substr($parts[0], 1, 1);
    return mb_strtoupper($a . $b);
}

function vr_accnav(string $current): void
{
?>
<nav class="accnav" aria-label="<?= te('acc_title') ?>">
  <?php foreach (vr_acc_items() as $file => [$key, $ico]): ?>
    <a class="accnav__a<?= $current === $file ? ' is-on' : '' ?>"<?= $current === $file ? ' aria-current="page"' : '' ?>
       href="<?= h(vr_url(str_starts_with($file, '../') ? substr($file, 3) : 'account/' . $file)) ?>">
      <?= vr_icon($ico, 18) ?><span><?= te($key) ?></span>
      <?php if ($file === '../wishlist.php' && function_exists('vr_wish_count') && vr_wish_count() > 0): ?>
        <em class="accnav__n"><?= vr_wish_count() ?></em>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>
  <a class="accnav__a accnav__a--out" href="<?= h(vr_url('account/logout.php')) ?>">
    <?= vr_icon('arrow', 18) ?><span><?= te('logout') ?></span>
  </a>
</nav>
<?php
}

/** Hesap sayfasının başı: koyu bant (monogram, başlık, üyelik) + menü. */
function vr_acc_open(array $me, string $current, string $title, string $lead = ''): void
{
    vr_layout_start(['title' => $title . ' · ' . t('acc_title'), 'robots' => 'noindex,nofollow']);
    $since = (int)($me['created_at'] ?? 0);
?>
<section class="acc">
  <header class="acc__hero">
    <span class="acc__aura" aria-hidden="true"></span>
    <div class="wrap acc__hero-in">
      <span class="acc__mono" aria-hidden="true"><?= h(vr_acc_initials($me)) ?></span>
      <div class="acc__id">
        <p class="acc__kicker"><?= te('acc_title') ?><?php if ($since): ?> · <?= te('acc_member_since', ['date' => vr_date($since)]) ?><?php endif; ?></p>
        <h1 class="acc__title"><?= h($title) ?></h1>
        <p class="acc__mail"><?= h((string)$me['email']) ?>
          <?php if (!empty($me['verified'])): ?><span class="acc__ok"><?= vr_icon('check', 14) ?><?= te('acc_verified') ?></span><?php endif; ?></p>
      </div>
    </div>
  </header>
  <div class="wrap acc__body">
    <?php vr_accnav($current); ?>
    <div class="acc__main">
      <?php if ($lead !== ''): ?><p class="acc__lead"><?= h($lead) ?></p><?php endif; ?>
<?php
}

function vr_acc_close(): void
{
?>
    </div>
  </div>
</section>
<?php
    vr_layout_end();
}

/**
 * Giriş / kayıt / şifre sayfaları: solda koyu marka paneli (kısa vaat
 * listesi), sağda form. Telefonda panel formun üstünde ince bir şerit.
 */
function vr_auth_open(string $title, string $lead = ''): void
{
    vr_layout_start(['title' => $title, 'robots' => 'noindex,nofollow']);
?>
<section class="auth">
  <div class="auth__panel">
    <span class="acc__aura" aria-hidden="true"></span>
    <div class="auth__panel-in">
      <p class="acc__kicker"><?= te('acc_auth_kicker') ?></p>
      <p class="auth__brand"><?= h((string)vr_config('brand')) ?></p>
      <ul class="auth__list">
        <li><?= vr_icon('truck', 18) ?><span><?= te('acc_auth_b1') ?></span></li>
        <li><?= vr_icon('heart', 18) ?><span><?= te('acc_auth_b2') ?></span></li>
        <li><?= vr_icon('clock', 18) ?><span><?= te('acc_auth_b3') ?></span></li>
        <li><?= vr_icon('shield', 18) ?><span><?= te('acc_auth_b4') ?></span></li>
      </ul>
    </div>
  </div>
  <div class="auth__form">
    <div class="auth__form-in">
      <h1 class="auth__title"><?= h($title) ?></h1>
      <?php if ($lead !== ''): ?><p class="auth__lead"><?= h($lead) ?></p><?php endif; ?>
<?php
}

function vr_auth_close(): void
{
?>
    </div>
  </div>
</section>
<?php
    vr_layout_end();
}

/** Şifre alanı: göster/gizle düğmesi ve (yeni şifrede) güç çubuğu. */
function vr_pw_field(string $id, string $label, string $autocomplete, bool $meter = false, bool $required = true, int $min = 0): void
{
?>
<div class="field">
  <label for="<?= h($id) ?>"><?= h($label) ?><?php if ($required && $min > 0): ?> <span class="req">*</span><?php endif; ?></label>
  <span class="pw">
    <input id="<?= h($id) ?>" name="<?= h($id) ?>" type="password"<?= $required ? ' required' : '' ?><?= $min ? ' minlength="' . $min . '"' : '' ?>
           autocomplete="<?= h($autocomplete) ?>"<?= $meter ? ' data-pw-meter' : '' ?>>
    <button class="pw__eye" type="button" data-pw-toggle aria-controls="<?= h($id) ?>"
            data-pw-show="<?= te('acc_show_pw') ?>" data-pw-hide="<?= te('acc_hide_pw') ?>"><?= te('acc_show_pw') ?></button>
  </span>
  <?php if ($meter): ?>
    <span class="pwmeter" aria-hidden="true"><i></i></span>
    <small class="pwmeter__t" data-l0="<?= te('pw_too_short') ?>" data-l1="<?= te('acc_pw_weak') ?>"
           data-l2="<?= te('acc_pw_ok') ?>" data-l3="<?= te('acc_pw_strong') ?>"><?= te('pw_too_short') ?></small>
  <?php endif; ?>
</div>
<?php
}

/**
 * E-posta doğrulanmadıysa sipariş geçmişi KAPALI. Bunu saklamıyoruz; nedenini
 * de yazıyoruz, çünkü kullanıcı "siparişlerim nerede" diye sormadan cevabı
 * görmeli. Yeniden gönderme düğmesi burada.
 */
function vr_verify_notice(array $customer): void
{
    if (!empty($customer['verified'])) return;
?>
<div class="accnote">
  <?= vr_icon('mail', 20) ?>
  <div>
    <strong><?= te('acc_verify_title') ?></strong>
    <p><?= te('acc_verify_banner') ?></p>
    <form method="post" action="<?= h(vr_url('account/verify.php')) ?>">
      <?= vr_csrf_field() ?>
      <input type="hidden" name="action" value="resend">
      <button class="btn btn--ghost btn--sm" type="submit"><span><?= te('acc_verify_resend') ?></span></button>
    </form>
  </div>
</div>
<?php
}

/** Sipariş durumu adımları: [anahtar, tamam mı, zaman]. */
function vr_acc_order_steps(array $o): array
{
    $paid = in_array((string)($o['status'] ?? ''), ['paid'], true);
    return [
        ['acc_track_placed',  true,                      (int)($o['created_at'] ?? 0)],
        ['acc_track_paid',    $paid,                     (int)($o['paid_at'] ?? 0)],
        ['acc_track_shipped', !empty($o['shipped_at']),  (int)($o['shipped_at'] ?? 0)],
    ];
}

function vr_acc_steps(array $o): void
{
    if ((string)($o['status'] ?? '') === 'cancelled') {
        echo '<p class="ostatus ostatus--x">' . te('order_cancelled') . '</p>';
        return;
    }
?>
<ol class="osteps">
  <?php foreach (vr_acc_order_steps($o) as [$k, $done, $ts]): ?>
    <li class="<?= $done ? 'is-done' : '' ?>">
      <i aria-hidden="true"><?= $done ? vr_icon('check', 12) : '' ?></i>
      <span><?= te($k) ?></span>
      <?php if ($done && $ts): ?><small><?= h(vr_date($ts)) ?></small><?php endif; ?>
    </li>
  <?php endforeach; ?>
</ol>
<?php
}

/** Sipariş satırının küçük resmi (ürün artık katalogda yoksa boş). */
function vr_acc_line_img(array $l): string
{
    $p = ($l['pid'] ?? '') !== '' ? vr_product((string)$l['pid']) : null;
    return $p ? vr_card_image($p) : '';
}
