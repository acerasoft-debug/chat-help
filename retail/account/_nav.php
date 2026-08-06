<?php
/**
 * Müşteri alanının ortak yan menüsü ve doğrulama uyarısı.
 * $current: aktif sekmenin dosya adı ('index.php', 'orders.php', …)
 */

declare(strict_types=1);

function vr_accnav(string $current): void
{
    $items = [
        'index.php'   => t('acc_nav_overview'),
        'orders.php'  => t('acc_nav_orders'),
        'profile.php' => t('acc_nav_profile'),
        'security.php' => t('acc_nav_security'),
    ];
?>
<nav class="dashnav" aria-label="<?= te('acc_title') ?>">
  <?php foreach ($items as $file => $label): ?>
    <a class="<?= $current === $file ? 'is-on' : '' ?>" href="<?= h(vr_url('account/' . $file)) ?>"><?= h($label) ?></a>
  <?php endforeach; ?>
  <a href="<?= h(vr_url('account/logout.php')) ?>" style="margin-top:12px;color:var(--muted-2)"><?= te('logout') ?></a>
</nav>
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
<div class="notice" style="border-left-color:var(--ember);margin-bottom:22px">
  <strong><?= te('acc_verify_title') ?>.</strong> <?= te('acc_verify_banner') ?>
  <form method="post" action="<?= h(vr_url('account/verify.php')) ?>" style="margin-top:12px">
    <?= vr_csrf_field() ?>
    <input type="hidden" name="action" value="resend">
    <button class="btn btn--ghost btn--sm" type="submit"><span><?= te('acc_verify_resend') ?></span></button>
  </form>
</div>
<?php
}
