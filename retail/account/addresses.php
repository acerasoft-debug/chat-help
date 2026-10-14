<?php
/**
 * Teslimat ve fatura adresi.
 *
 * Kayıtta "address" (teslimat) eskisiyle aynı alanları taşıyor (street, zip,
 * city, country) — eski kayıtlar olduğu gibi okunuyor. Yeni alanlar: line2,
 * company; fatura adresi ayrı ("billing") ya da "teslimatla aynı".
 * Teslimat ülkesi kasada önceden seçili geliyor (checkout.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/customers.php';
require_once __DIR__ . '/../inc/cart.php';
require_once __DIR__ . '/_nav.php';

$me = vr_customer_require();
$saved = false;
$countries = vr_country_options(vr_shipping_countries());

/** Formdan bir adres bloğu ($p öneki: s_ ya da b_). */
function vr_acc_addr_in(string $p, array $countries): array
{
    $cc = strtoupper(substr(trim((string)($_POST[$p . 'country'] ?? '')), 0, 2));
    return [
        'name'    => mb_substr(trim((string)($_POST[$p . 'name'] ?? '')), 0, 120),
        'company' => mb_substr(trim((string)($_POST[$p . 'company'] ?? '')), 0, 120),
        'street'  => mb_substr(trim((string)($_POST[$p . 'street'] ?? '')), 0, 160),
        'line2'   => mb_substr(trim((string)($_POST[$p . 'line2'] ?? '')), 0, 160),
        'zip'     => mb_substr(trim((string)($_POST[$p . 'zip'] ?? '')), 0, 20),
        'city'    => mb_substr(trim((string)($_POST[$p . 'city'] ?? '')), 0, 120),
        'country' => isset($countries[$cc]) ? $cc : '',
    ];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();
    $ship = vr_acc_addr_in('s_', $countries);
    $same = !empty($_POST['same']);
    $bill = $same ? [] : vr_acc_addr_in('b_', $countries) + ['vat' => mb_substr(strtoupper(preg_replace('/\s+/', '', (string)($_POST['b_vat'] ?? ''))), 0, 20)];
    vr_customer_update((string)$me['id'], ['address' => $ship, 'billing' => $bill, 'billing_same' => $same]);
    $me = vr_current_customer();
    $saved = true;
}

$s = (array)($me['address'] ?? []);
$b = (array)($me['billing'] ?? []);
$same = !isset($me['billing_same']) || !empty($me['billing_same']);

/** Bir adres bloğunun alanları. */
function vr_acc_addr_fields(string $p, array $a, array $countries, array $me, bool $vat = false): void
{
?>
  <div class="field"><label for="<?= $p ?>name"><?= te('acc_addr_name') ?></label>
    <input id="<?= $p ?>name" name="<?= $p ?>name" type="text" autocomplete="<?= $p === 's_' ? 'shipping' : 'billing' ?> name"
           value="<?= h((string)($a['name'] ?? ($p === 's_' ? ($me['name'] ?? '') : ''))) ?>"></div>
  <div class="field"><label for="<?= $p ?>company"><?= te('acc_company') ?></label>
    <input id="<?= $p ?>company" name="<?= $p ?>company" type="text" autocomplete="organization" value="<?= h((string)($a['company'] ?? '')) ?>"></div>
  <div class="field"><label for="<?= $p ?>street"><?= te('acc_street') ?></label>
    <input id="<?= $p ?>street" name="<?= $p ?>street" type="text" autocomplete="<?= $p === 's_' ? 'shipping' : 'billing' ?> address-line1" value="<?= h((string)($a['street'] ?? '')) ?>"></div>
  <div class="field"><label for="<?= $p ?>line2"><?= te('acc_addr_line2') ?></label>
    <input id="<?= $p ?>line2" name="<?= $p ?>line2" type="text" autocomplete="<?= $p === 's_' ? 'shipping' : 'billing' ?> address-line2" value="<?= h((string)($a['line2'] ?? '')) ?>"></div>
  <div class="fieldrow">
    <div class="field"><label for="<?= $p ?>zip"><?= te('acc_zip') ?></label>
      <input id="<?= $p ?>zip" name="<?= $p ?>zip" type="text" autocomplete="<?= $p === 's_' ? 'shipping' : 'billing' ?> postal-code" value="<?= h((string)($a['zip'] ?? '')) ?>"></div>
    <div class="field"><label for="<?= $p ?>city"><?= te('acc_city') ?></label>
      <input id="<?= $p ?>city" name="<?= $p ?>city" type="text" autocomplete="<?= $p === 's_' ? 'shipping' : 'billing' ?> address-level2" value="<?= h((string)($a['city'] ?? '')) ?>"></div>
  </div>
  <div class="field"><label for="<?= $p ?>country"><?= te('country') ?></label>
    <select id="<?= $p ?>country" name="<?= $p ?>country" autocomplete="<?= $p === 's_' ? 'shipping' : 'billing' ?> country">
      <option value=""><?= te('acc_choose') ?></option>
      <?php foreach ($countries as $cc => $nm): ?>
        <option value="<?= h($cc) ?>"<?= ($a['country'] ?? '') === $cc ? ' selected' : '' ?>><?= h($nm) ?></option>
      <?php endforeach; ?>
    </select></div>
  <?php if ($vat): ?>
    <div class="field"><label for="b_vat"><?= te('acc_vat') ?></label>
      <input id="b_vat" name="b_vat" type="text" value="<?= h((string)($a['vat'] ?? '')) ?>"></div>
  <?php endif; ?>
<?php
}

vr_acc_open($me, 'addresses.php', t('acc_nav_addresses'), t('acc_addr_lead'));
?>
<?php if ($saved): ?><div class="flash flash--ok accflash"><?= vr_icon('check', 16) ?> <?= te('acc_saved') ?></div><?php endif; ?>

<form class="form accform" method="post" action="<?= h(vr_url('account/addresses.php')) ?>">
  <?= vr_csrf_field() ?>
  <div class="acc2">
    <fieldset class="acccard">
      <legend class="acccard__h"><h2><?= vr_icon('truck', 18) ?> <?= te('acc_addr_ship') ?></h2></legend>
      <?php vr_acc_addr_fields('s_', $s, $countries, $me); ?>
    </fieldset>
    <fieldset class="acccard">
      <legend class="acccard__h"><h2><?= vr_icon('doc', 18) ?> <?= te('acc_addr_bill') ?></h2></legend>
      <label class="switch">
        <input type="checkbox" name="same" value="1" data-toggle-target="#billfields"<?= $same ? ' checked' : '' ?>>
        <span class="switch__ui" aria-hidden="true"></span><span><?= te('acc_addr_same') ?></span>
      </label>
      <div id="billfields"<?= $same ? ' hidden' : '' ?>>
        <?php vr_acc_addr_fields('b_', $b, $countries, $me, true); ?>
      </div>
    </fieldset>
  </div>
  <div class="accform__bar">
    <button class="btn" type="submit"><span><?= te('acc_save') ?></span></button>
  </div>
</form>
<?php vr_acc_close(); ?>
