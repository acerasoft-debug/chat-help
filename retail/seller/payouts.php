<?php
/**
 * Auszahlungen — Stripe Connect onboarding
 * ----------------------------------------
 * Akış:
 *   1) Hesap yoksa: /v1/accounts (Express, business_type satıcı tipine göre)
 *   2) account_links ile tek kullanımlık onboarding bağlantısı → Stripe'a çık
 *   3) Dönüşte (?return=1) durumu CANLI sorup önbelleği tazele
 *   4) Hazır olduğunda Express Dashboard bağlantısı gösterilir
 *
 * "Hazır" ölçütü charges_enabled — canlı taraftaki refresh-connect-status.yml
 * ile aynı kural. payouts_enabled/details_submitted yalnızca bilgi olarak.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/orders.php';
require_once __DIR__ . '/_nav.php';

$seller = vr_seller_require();
$err    = '';

// ------------------------------------------------------- Stripe'tan dönüş
if (!empty($_GET['return']) && (string)($seller['stripe_account_id'] ?? '') !== '') {
    $seller = vr_stripe_refresh_seller($seller);
    vr_flash(!empty($seller['charges_enabled']) ? t('connect_ready') : t('connect_pending'),
             !empty($seller['charges_enabled']) ? 'ok' : 'info');
    vr_redirect('seller/payouts.php');
}

// ------------------------------------------------------------ POST eylemleri
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();
    $action = (string)($_POST['action'] ?? '');

    if (!vr_stripe_ready()) {
        $err = t('connect_unavailable');
    } elseif (!vr_rate_ok('connect', 12, 600)) {
        $err = t('login_throttled');
    } elseif ($action === 'onboard') {
        $acct = (string)($seller['stripe_account_id'] ?? '');

        if ($acct === '') {
            [$ok, $res] = vr_stripe_connect_create($seller);
            if (!$ok) {
                $err = (string)$res;
            } else {
                $acct = (string)$res;
                vr_seller_update((string)$seller['id'], ['stripe_account_id' => $acct]);
                $seller['stripe_account_id'] = $acct;
            }
        }

        if ($err === '' && $acct !== '') {
            [$lOk, $url] = vr_stripe_account_link(
                $acct,
                vr_abs('seller/payouts.php', ['return' => 1]),
                vr_abs('seller/payouts.php', ['refresh' => 1])
            );
            if ($lOk && $url !== '') {
                header('Location: ' . $url, true, 303);   // Stripe'a çıkış
                exit;
            }
            $err = (string)$url;
        }

    } elseif ($action === 'dashboard') {
        [$ok, $url] = vr_stripe_login_link((string)($seller['stripe_account_id'] ?? ''));
        if ($ok && $url !== '') {
            header('Location: ' . $url, true, 303);
            exit;
        }
        $err = (string)$url;

    } elseif ($action === 'refresh') {
        $seller = vr_stripe_refresh_seller($seller);
        vr_flash(!empty($seller['charges_enabled']) ? t('connect_ready') : t('connect_pending'), 'info');
        vr_redirect('seller/payouts.php');
    }
}

$acct    = (string)($seller['stripe_account_id'] ?? '');
$ready   = !empty($seller['charges_enabled']);
$state   = $acct === '' ? 'none' : ($ready ? 'ready' : 'pending');
$orders  = vr_orders_for_seller((string)$seller['id']);

$sumNet = $sumFee = $held = 0;
foreach ($orders as $o) {
    $row = (array)($o['by_seller'][(string)$seller['id']] ?? []);
    $sumNet += (int)($row['net'] ?? 0);
    $sumFee += (int)($row['commission'] ?? 0);
    foreach ((array)($o['transfers'] ?? []) as $tr) {
        if ((string)($tr['seller'] ?? '') === (string)$seller['id'] && (string)($tr['status'] ?? '') !== 'sent') {
            $held += (int)($tr['amount'] ?? 0);
        }
    }
}

vr_layout_start(['title' => t('payouts'), 'robots' => 'noindex,nofollow']);
?>
<section class="sec sec--tight">
  <div class="wrap">
    <h1 class="sechead__t" style="margin-bottom:24px"><?= te('connect_t') ?></h1>

    <div class="dash">
      <?php vr_dashnav('payouts.php', $seller); ?>

      <div>
        <?php if ($err !== ''): ?>
          <div class="flash flash--err" style="margin-bottom:16px"><?= h($err) ?></div>
        <?php endif; ?>

        <?php if (!vr_stripe_ready()): ?>
          <div class="notice" style="border-left-color:var(--ember)">
            <strong><?= te('connect_unavailable') ?></strong><br><?= te('pay_unavailable_b') ?>
          </div>
        <?php endif; ?>

        <div class="doc__box" style="margin-top:0">
          <p style="margin-bottom:6px">
            <span class="pill <?= $ready ? 'pill--live' : ($state === 'none' ? 'pill--out' : 'pill--review') ?>">
              <?= te('connect_' . $state) ?>
            </span>
          </p>
          <p style="font-size:13.5px;color:var(--muted);margin:12px 0 16px"><?= te('sell_payout_b') ?></p>

          <dl class="dl" style="margin-bottom:18px">
            <dt>charges_enabled</dt><dd><?= !empty($seller['charges_enabled']) ? 'true' : 'false' ?></dd>
            <dt>payouts_enabled</dt><dd><?= !empty($seller['payouts_enabled']) ? 'true' : 'false' ?></dd>
            <dt>details_submitted</dt><dd><?= !empty($seller['details_submitted']) ? 'true' : 'false' ?></dd>
            <?php if ($acct !== ''): ?>
              <dt>Account</dt><dd style="font-family:ui-monospace,monospace;font-size:12px"><?= h($acct) ?></dd>
            <?php endif; ?>
          </dl>

          <div style="display:flex;gap:10px;flex-wrap:wrap">
            <?php if (!$ready): ?>
              <form method="post" action="<?= h(vr_url('seller/payouts.php')) ?>">
                <?= vr_csrf_field() ?>
                <input type="hidden" name="action" value="onboard">
                <button class="btn" type="submit" <?= vr_stripe_ready() ? '' : 'disabled' ?>>
                  <span><?= $state === 'none' ? te('connect_start') : te('connect_continue') ?></span>
                  <?= vr_icon('arrow', 16) ?>
                </button>
              </form>
            <?php endif; ?>

            <?php if ($acct !== ''): ?>
              <form method="post" action="<?= h(vr_url('seller/payouts.php')) ?>">
                <?= vr_csrf_field() ?>
                <input type="hidden" name="action" value="dashboard">
                <button class="btn btn--ghost" type="submit"><span><?= te('connect_dashboard') ?></span></button>
              </form>
              <form method="post" action="<?= h(vr_url('seller/payouts.php')) ?>">
                <?= vr_csrf_field() ?>
                <input type="hidden" name="action" value="refresh">
                <button class="btn btn--ghost" type="submit"><span><?= te('update') ?></span></button>
              </form>
            <?php endif; ?>
          </div>
        </div>

        <div class="tiles" style="margin-top:24px">
          <div class="tile"><b style="font-size:22px"><?= h(vr_money($sumNet)) ?></b><span><?= te('your_payout') ?></span></div>
          <div class="tile"><b style="font-size:22px"><?= h(vr_money($sumFee)) ?></b><span><?= te('our_fee') ?></span></div>
          <div class="tile"><b><?= count($orders) ?></b><span><?= te('seller_orders') ?></span></div>
        </div>

        <?php if ($held > 0): ?>
          <div class="notice" style="border-left-color:var(--ember);margin-top:20px">
            <strong><?= h(vr_money($held)) ?></strong> —
            Auszahlung wartet: solange Stripe Sie nicht verifiziert hat, bleibt Ihr Anteil auf dem
            Plattformkonto und wird nach der Verifizierung überwiesen.
          </div>
        <?php endif; ?>

        <div class="doc" style="margin-top:28px;max-width:none">
          <h3><?= te('sell_fees_t') ?></h3>
          <p>
            <?= te((string)$seller['seller_type'] === 'private' ? 'sell_fee_priv' : 'sell_fee_biz', [
                'pct'   => vr_fee_bps((string)$seller['seller_type']) / 100,
                'fixed' => vr_money((int)vr_config('fee_fixed_cents', 35)),
            ]) ?>
            · <?= te('sell_fee_outlet', ['pct' => (int)vr_config('fee_bps_outlet', 1500) / 100]) ?>
          </p>
          <p style="font-size:13px;color:var(--muted)"><?= te('sell_fee_note') ?>
            <a href="<?= h(vr_url('legal/verkaeufer.php')) ?>"><?= te('legal_seller_terms') ?></a>
          </p>
        </div>
      </div>
    </div>
  </div>
</section>
<?php vr_layout_end(); ?>
