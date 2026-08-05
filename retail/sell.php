<?php
/**
 * "Verkaufen" — satıcı kazanım sayfası
 * ------------------------------------
 * İki satıcı tipi bilinçli olarak eşit ağırlıkta anlatılıyor: Händler ve
 * Privatverkäufer. Özel kişilerin satabilmesi istenen bir özellik, ama
 * pazaryerinin kullanıcıya karşı sorumluluğu şu: alıcı, karşısındakinin
 * gewerblich mi privat mı olduğunu ve bunun hangi hakları değiştirdiğini
 * ödeme öncesinde bilmek zorunda. O yüzden bu ayrım her yerde açık taşınıyor.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/view.php';

$feeB   = (int)vr_config('fee_bps_business', 1200) / 100;
$feeP   = (int)vr_config('fee_bps_private', 900) / 100;
$feeV   = (int)vr_config('fee_bps_outlet', 1500) / 100;
$fixed  = vr_money((int)vr_config('fee_fixed_cents', 35));
$seller = vr_current_seller();

vr_layout_start([
    'title' => t('nav_sell'),
    'desc'  => t('sell_hero_b'),
    'jsonld' => [vr_jsonld_breadcrumbs([
        (string)vr_config('brand') => vr_url('/'),
        t('nav_sell')              => null,
    ])],
]);
?>
<section class="sec vault">
  <div class="wrap">
    <p class="eyebrow"><?= te('nav_sell') ?></p>
    <h1 style="font-size:clamp(32px,5vw,66px);max-width:22ch"><?= te('sell_hero_t') ?></h1>
    <p class="lede" style="margin-top:20px"><?= te('sell_hero_b') ?></p>

    <div class="hero__cta" style="margin-top:30px">
      <?php if ($seller !== null): ?>
        <a class="btn btn--brass btn--lg" href="<?= h(vr_url('seller/index.php')) ?>"><span><?= te('dashboard') ?></span><?= vr_icon('arrow', 16) ?></a>
      <?php else: ?>
        <a class="btn btn--brass btn--lg" href="<?= h(vr_url('seller/register.php')) ?>"><span><?= te('sell_cta') ?></span><?= vr_icon('arrow', 16) ?></a>
        <a class="btn btn--ghost btn--lg" style="color:var(--bone);border-color:rgba(245,242,236,.4)" href="<?= h(vr_url('seller/login.php')) ?>"><span><?= te('sell_login') ?></span></a>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ------------------------------------------------------------ iki satıcı tipi -->
<section class="sec">
  <div class="wrap">
    <?php vr_section_head(t('sell_who_t')); ?>
    <div class="grid grid--2">
      <div class="doc__box" data-reveal style="margin:0">
        <h3 style="font-family:var(--serif);font-size:23px;margin-bottom:10px"><?= te('sell_biz_t') ?></h3>
        <p style="margin-bottom:14px"><?= te('sell_biz_b') ?></p>
        <p style="font-size:13px;color:var(--muted)">
          <strong><?= te('sell_fees_t') ?>:</strong>
          <?= te('sell_fee_biz', ['pct' => $feeB, 'fixed' => $fixed]) ?>
        </p>
        <ul style="font-size:13px;color:var(--muted);margin-top:12px;padding-left:20px">
          <li>Rechnung mit MwSt., gesetzliche Gewährleistung</li>
          <li>Widerrufsrecht für Verbraucher (14 Tage)</li>
          <li>Stripe Connect: business_type <code>company</code></li>
        </ul>
      </div>

      <div class="doc__box" data-reveal style="margin:0">
        <h3 style="font-family:var(--serif);font-size:23px;margin-bottom:10px"><?= te('sell_priv_t') ?></h3>
        <p style="margin-bottom:14px"><?= te('sell_priv_b') ?></p>
        <p style="font-size:13px;color:var(--muted)">
          <strong><?= te('sell_fees_t') ?>:</strong>
          <?= te('sell_fee_priv', ['pct' => $feeP, 'fixed' => $fixed]) ?>
        </p>
        <ul style="font-size:13px;color:var(--muted);margin-top:12px;padding-left:20px">
          <li>Angebote sichtbar als „<?= te('seller_private') ?>“</li>
          <li>Kein Widerrufsrecht, Gewährleistung ausschließbar</li>
          <li>Stripe Connect: business_type <code>individual</code></li>
        </ul>
        <p style="font-size:12px;color:var(--muted-2);margin-top:12px">
          Wer regelmäßig und mit Gewinnabsicht verkauft, handelt gewerblich — dann bitte als
          <?= te('sell_biz_t') ?> anmelden. Wir prüfen das Volumen und melden uns, wenn ein
          Wechsel nötig ist.
        </p>
      </div>
    </div>
  </div>
</section>

<!-- --------------------------------------------------------------- adımlar -->
<section class="sec sec--tight">
  <div class="wrap">
    <?php vr_section_head(t('sell_steps_t')); ?>
    <div class="steps" style="color:var(--text)">
      <?php
      $steps = [
          ['01', t('sell_step1'), 'Name, E-Mail, Verkäufertyp. Zwei Minuten.'],
          ['02', t('sell_step2'), t('sell_payout_b')],
          ['03', t('sell_step3'), 'Marke, Modellcode, Größen, Preis, Bilder.'],
          ['04', t('sell_step4'), t('sell_rules_b')],
      ];
      foreach ($steps as [$n, $title, $body]): ?>
        <div class="step" style="border-color:var(--bone-3)" data-reveal>
          <b style="color:var(--brass-text)"><?= h($n) ?></b>
          <h3><?= h($title) ?></h3>
          <p><?= h($body) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- --------------------------------------------------------------- ücretler -->
<section class="sec sec--tight">
  <div class="wrap wrap--narrow">
    <div class="doc">
      <h2><?= te('sell_fees_t') ?></h2>
      <table>
        <thead><tr><th><?= te('seller_type') ?></th><th><?= te('our_fee') ?></th><th><?= te('nav_outlet') ?></th></tr></thead>
        <tbody>
          <tr><td><?= te('sell_biz_t') ?></td><td><?= h($feeB) ?>% + <?= h($fixed) ?></td><td><?= h($feeV) ?>%</td></tr>
          <tr><td><?= te('sell_priv_t') ?></td><td><?= h($feeP) ?>% + <?= h($fixed) ?></td><td><?= h($feeV) ?>%</td></tr>
        </tbody>
      </table>
      <p><?= te('sell_fee_note') ?></p>

      <h3><?= te('sell_payout_t') ?></h3>
      <p><?= te('sell_payout_b') ?></p>
      <p>Versandkosten trägt und behält <?= h((string)vr_config('brand')) ?> — sie sind nicht Teil
         Ihres Auszahlungsbetrags. Details in den
         <a href="<?= h(vr_url('legal/verkaeufer.php')) ?>"><?= te('legal_seller_terms') ?></a>.</p>

      <h3><?= te('sell_rules_t') ?></h3>
      <p><?= te('sell_rules_b') ?></p>

      <div class="doc__box">
        <strong><?= te('sell_cta') ?></strong>
        <p style="margin:10px 0 14px"><?= te('sell_hero_b') ?></p>
        <a class="btn" href="<?= h(vr_url('seller/register.php')) ?>"><span><?= te('register') ?></span><?= vr_icon('arrow', 16) ?></a>
      </div>
    </div>
  </div>
</section>

<?php vr_layout_end(); ?>
