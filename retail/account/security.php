<?php
/** Şifre, oturum bilgisi, veri dışa aktarma (DSGVO Art. 15/20) ve hesap silme. */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/customers.php';
require_once __DIR__ . '/_nav.php';

$me = vr_customer_require();
$err = '';
$ok  = false;

// ---- veri dışa aktarma: hesapta ne tutuyorsak, okunur JSON olarak.
// Şifre özeti ve tek kullanımlık bağlantı özetleri dışarıda: onlar veri
// değil, güvenlik malzemesi.
if (($_GET['export'] ?? '') === '1') {
    $data = array_diff_key($me, array_flip(['pw_hash', 'verify_hash', 'verify_expires', 'reset_hash', 'reset_expires']));
    foreach (['created_at', 'last_login'] as $k) if (!empty($data[$k])) $data[$k] = gmdate('c', (int)$data[$k]);
    $data['orders'] = array_map(fn($o) => [
        'number' => $o['number'] ?? '', 'date' => gmdate('c', (int)($o['created_at'] ?? 0)), 'status' => $o['status'] ?? '',
        'total_eur' => round((int)($o['total_cents'] ?? 0) / 100, 2),
        'items' => array_map(fn($l) => ['brand' => $l['brand'] ?? '', 'name' => $l['name'] ?? '', 'size' => $l['size'] ?? '',
                                        'colour' => $l['colour'] ?? '', 'qty' => (int)($l['qty'] ?? 1),
                                        'unit_eur' => round((int)($l['unit_cents'] ?? 0) / 100, 2)], (array)($o['lines'] ?? [])),
        'ship_to' => $o['customer']['address'] ?? null,
    ], vr_customer_orders($me));
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . strtolower((string)vr_config('brand')) . '-account-' . gmdate('Y-m-d') . '.json"');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();

    if (($_POST['action'] ?? '') === 'delete') {
        // Onay kutusu işaretlenmeden silinmiyor: geri dönüşü yok.
        if (($_POST['confirm'] ?? '') === '1') {
            vr_customer_delete((string)$me['id']);
            vr_customer_logout();
            vr_flash(t('acc_delete_done'), 'ok');
            vr_redirect('/');
        }
        $err = t('required_field');
    } else {
        $pw  = (string)($_POST['password'] ?? '');
        $pw2 = (string)($_POST['password2'] ?? '');
        if ($pw !== $pw2) {
            $err = t('pw_mismatch');
        } else {
            [$done, $key] = vr_customer_set_password((string)$me['id'], $pw, (string)($_POST['old'] ?? ''));
            if ($done) $ok = true;
            else $err = $key === 'pw_wrong' ? t('acc_pw_wrong') : t($key);
        }
    }
}

vr_acc_open($me, 'security.php', t('acc_nav_security'));
?>
<?php if ($ok): ?><div class="flash flash--ok accflash"><?= vr_icon('check', 16) ?> <?= te('acc_pw_changed') ?></div><?php endif; ?>
<?php if ($err !== ''): ?><div class="flash flash--err accflash"><?= h($err) ?></div><?php endif; ?>

<form class="form accform" method="post" action="<?= h(vr_url('account/security.php')) ?>">
  <?= vr_csrf_field() ?>
  <fieldset class="acccard">
    <legend class="acccard__h"><h2><?= vr_icon('lock', 18) ?> <?= te('acc_pw_change') ?></h2></legend>
    <?php if (!empty($me['last_login'])): ?>
      <p class="accmuted" style="margin:-4px 0 16px"><?= te('acc_last_login', ['date' => vr_date((int)$me['last_login'], true)]) ?></p>
    <?php endif; ?>
    <?php vr_pw_field('old', t('acc_pw_old'), 'current-password'); ?>
    <?php vr_pw_field('password', t('acc_pw_new'), 'new-password', true, true, 10); ?>
    <?php vr_pw_field('password2', t('password_again'), 'new-password', false, true, 10); ?>
    <div class="accform__bar accform__bar--in">
      <button class="btn" type="submit"><span><?= te('acc_pw_change') ?></span></button>
    </div>
  </fieldset>
</form>

<article class="acccard pref">
  <span class="pref__i"><?= vr_icon('doc', 20) ?></span>
  <div class="pref__b">
    <h2><?= te('acc_export_t') ?></h2>
    <p class="accmuted"><?= te('acc_export_b') ?></p>
    <p style="margin-top:14px"><a class="btn btn--ghost btn--sm" href="<?= h(vr_url('account/security.php', ['export' => 1])) ?>"><span><?= te('acc_export_cta') ?></span></a></p>
  </div>
</article>

<details class="acccard accdanger">
  <summary><h2><?= te('acc_delete_title') ?></h2><span class="accdanger__plus" aria-hidden="true"></span></summary>
  <p style="font-size:13.5px;margin:12px 0 10px"><?= te('acc_delete_lead') ?></p>
  <p class="accmuted" style="margin-bottom:18px"><?= te('acc_delete_orders_note') ?></p>
  <form class="form" method="post" action="<?= h(vr_url('account/security.php')) ?>">
    <?= vr_csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <label class="consent">
      <input type="checkbox" name="confirm" value="1" required>
      <span><?= te('acc_delete_confirm') ?></span>
    </label>
    <div class="field">
      <button class="btn btn--ghost btn--sm accdanger__btn" type="submit"><span><?= te('acc_delete_title') ?></span></button>
    </div>
  </form>
</details>
<?php vr_acc_close(); ?>
