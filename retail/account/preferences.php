<?php
/**
 * E-posta tercihleri. Yalnız gerçekten var olan iki kanal:
 *   · sipariş e-postaları — sözleşmenin parçası, kapatılamaz (bilgi);
 *   · bülten + Vault ön erişimi — çifte onaylı (newsletter.php), buradan
 *     açılıyor ya da kapatılıyor.
 * Var olmayan bir "bildirim" anahtarı göstermiyoruz: işe yaramayan bir
 * düğme müşteriyi yanıltır.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/customers.php';
require_once __DIR__ . '/../inc/mail.php';
require_once __DIR__ . '/_nav.php';

$me = vr_customer_require();
$mail = strtolower((string)$me['email']);
$state = 'off';
$since = 0;
foreach ((array)vr_store_read('retail-members.json', []) as $r) {
    if (!is_array($r) || strtolower((string)($r['email'] ?? '')) !== $mail) continue;
    $state = !empty($r['confirmed_at']) ? 'on' : 'pending';
    $since = (int)($r['confirmed_at'] ?? 0);
}
$hours = (int)vr_config('vault_member_head_start_hours', 12);

vr_acc_open($me, 'preferences.php', t('acc_nav_prefs'), t('acc_prefs_lead'));
?>
<article class="acccard pref">
  <span class="pref__i"><?= vr_icon('doc', 20) ?></span>
  <div class="pref__b">
    <h2><?= te('acc_prefs_service_t') ?></h2>
    <p class="accmuted"><?= te('acc_prefs_service_b') ?></p>
  </div>
  <span class="ochip ochip--ok"><?= te('acc_prefs_always') ?></span>
</article>

<article class="acccard pref">
  <span class="pref__i"><?= vr_icon('mail', 20) ?></span>
  <div class="pref__b">
    <h2><?= te('acc_prefs_news_t') ?></h2>
    <?php if ($state === 'on'): ?>
      <p><?= te('acc_prefs_news_on', ['hours' => $hours]) ?><?php if ($since): ?> <span class="accmuted">(<?= h(vr_date($since)) ?>)</span><?php endif; ?></p>
      <p style="margin-top:14px"><a class="btn btn--ghost btn--sm" href="<?= h(vr_unsub_url($mail)) ?>"><span><?= te('acc_prefs_news_unsub') ?></span></a></p>
    <?php else: ?>
      <p class="accmuted"><?= $state === 'pending' ? te('acc_prefs_news_pending') : te('acc_prefs_news_off', ['hours' => $hours]) ?></p>
      <form method="post" action="<?= h(vr_url('newsletter.php')) ?>" class="pref__form">
        <?= vr_csrf_field() ?>
        <input type="hidden" name="email" value="<?= h($mail) ?>">
        <label class="consent">
          <input type="checkbox" name="consent" value="1" required>
          <span><?= te('acc_prefs_news_consent') ?></span>
        </label>
        <button class="btn btn--sm" type="submit"><span><?= $state === 'pending' ? te('acc_verify_resend') : te('acc_prefs_news_sub') ?></span></button>
      </form>
    <?php endif; ?>
    <p class="accmuted pref__note"><?= te('acc_prefs_news_note') ?></p>
  </div>
  <span class="ochip<?= $state === 'on' ? ' ochip--ok' : '' ?>"><?= $state === 'on' ? te('acc_on') : ($state === 'pending' ? te('acc_pending') : te('acc_off')) ?></span>
</article>
<?php vr_acc_close(); ?>
