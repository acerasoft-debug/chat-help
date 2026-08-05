<?php
/**
 * Satıcı panelinin ortak yan menüsü.
 * $current: aktif sekmenin dosya adı ('index.php', 'listing.php', …)
 */

declare(strict_types=1);

function vr_dashnav(string $current, array $seller): void
{
    $items = [
        'index.php'   => t('dashboard'),
        'listing.php' => t('new_listing'),
        'orders.php'  => t('seller_orders'),
        'payouts.php' => t('payouts'),
    ];
?>
<nav class="dashnav" aria-label="<?= te('dashboard') ?>">
  <?php foreach ($items as $file => $label): ?>
    <a class="<?= $current === $file ? 'is-on' : '' ?>" href="<?= h(vr_url('seller/' . $file)) ?>"><?= h($label) ?></a>
  <?php endforeach; ?>
  <a href="<?= h(vr_url('seller/logout.php')) ?>" style="margin-top:12px;color:var(--muted-2)"><?= te('logout') ?></a>
</nav>
<?php
}

/**
 * Connect durum kutusu — panelin her yerinde aynı görünsün.
 * Satıcı Stripe'ta hazır değilse ürünleri canlıya çıkmıyor, o yüzden bu uyarı
 * panelin en üstünde duruyor.
 */
function vr_connect_box(array $seller): void
{
    $acct  = (string)($seller['stripe_account_id'] ?? '');
    $ready = !empty($seller['charges_enabled']);
    $state = $acct === '' ? 'none' : ($ready ? 'ready' : 'pending');

    if ($state === 'ready') return;   // hazırsa yer kaplamasın
?>
<div class="notice" style="border-left-color:<?= $state === 'none' ? 'var(--ember)' : 'var(--brass)' ?>">
  <strong><?= te('connect_t') ?>.</strong>
  <?= te('connect_' . $state) ?>
  <a class="link" style="margin-left:6px" href="<?= h(vr_url('seller/payouts.php')) ?>">
    <?= $state === 'none' ? te('connect_start') : te('connect_continue') ?>
  </a>
</div>
<?php
}
