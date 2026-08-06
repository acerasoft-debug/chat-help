<?php
/** Ad ve adres. Kasada otomatik doldurulur; müşteri orada da değiştirebilir. */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/customers.php';
require_once __DIR__ . '/_nav.php';

$me = vr_customer_require();
$saved = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();
    vr_customer_update((string)$me['id'], [
        'name' => mb_substr(trim((string)($_POST['name'] ?? '')), 0, 120),
        'address' => [
            'street'  => mb_substr(trim((string)($_POST['street'] ?? '')), 0, 160),
            'zip'     => mb_substr(trim((string)($_POST['zip'] ?? '')), 0, 20),
            'city'    => mb_substr(trim((string)($_POST['city'] ?? '')), 0, 120),
            'country' => strtoupper(substr(trim((string)($_POST['country'] ?? '')), 0, 2)),
        ],
    ]);
    $me = vr_current_customer();
    $saved = true;
}

$a = (array)($me['address'] ?? []);

vr_layout_start(['title' => t('acc_nav_profile'), 'robots' => 'noindex,nofollow']);
?>
<section class="sec">
  <div class="wrap">
    <h1 class="sechead__t" style="margin-bottom:28px"><?= te('acc_nav_profile') ?></h1>
    <div class="dash">
      <?php vr_accnav('profile.php'); ?>
      <div>
        <?php if ($saved): ?>
          <div class="flash flash--ok" style="margin-bottom:18px"><?= te('acc_saved') ?></div>
        <?php endif; ?>
        <p class="sechead__s" style="margin-bottom:22px"><?= te('acc_profile_lead') ?></p>

        <form class="form" method="post" action="<?= h(vr_url('account/profile.php')) ?>">
          <?= vr_csrf_field() ?>
          <div class="field">
            <label for="email"><?= te('email') ?></label>
            <input id="email" type="email" value="<?= h((string)$me['email']) ?>" disabled>
          </div>
          <div class="field">
            <label for="name"><?= te('your_name') ?></label>
            <input id="name" name="name" type="text" autocomplete="name" value="<?= h((string)$me['name']) ?>">
          </div>
          <div class="field">
            <label for="street"><?= te('acc_street') ?></label>
            <input id="street" name="street" type="text" autocomplete="street-address" value="<?= h((string)($a['street'] ?? '')) ?>">
          </div>
          <div class="field">
            <label for="zip"><?= te('acc_zip') ?></label>
            <input id="zip" name="zip" type="text" autocomplete="postal-code" value="<?= h((string)($a['zip'] ?? '')) ?>">
          </div>
          <div class="field">
            <label for="city"><?= te('acc_city') ?></label>
            <input id="city" name="city" type="text" autocomplete="address-level2" value="<?= h((string)($a['city'] ?? '')) ?>">
          </div>
          <div class="field">
            <label for="country"><?= te('country') ?></label>
            <input id="country" name="country" type="text" maxlength="2" autocomplete="country"
                   placeholder="DE" value="<?= h((string)($a['country'] ?? '')) ?>">
          </div>
          <div class="field">
            <button class="btn btn--brass" type="submit"><span><?= te('acc_save') ?></span></button>
          </div>
        </form>
      </div>
    </div>
  </div>
</section>
<?php vr_layout_end(); ?>
