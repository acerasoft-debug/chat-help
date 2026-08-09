<?php
/**
 * PREMIUM OUTLET — Vault
 * ----------------------
 * İki görünüm aynı dosyada:
 *   ?lot=<id> yoksa  → tüm loslar (oda görünümü)
 *   ?lot=<id> varsa  → tek losun detay sayfası (plan tablosu dahil)
 *
 * Sayfanın ayırt edici yeri, indirimi "kırmızı yüzde" ile değil PLANI
 * göstererek anlatması: hangi kademede olduğu, bir sonraki fiyatın ne olacağı,
 * ne zaman düşeceği ve nerede duracağı açık. Fiyat iddiaları PAngV §11'e göre
 * yalnızca gerçek bir önceki fiyat varsa basılıyor (bkz. inc/vault.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/view.php';
require_once __DIR__ . '/inc/alerts.php';
require_once __DIR__ . '/inc/copy.php';

// ---- alarm kurma (POST) ve iptal (e-postadaki bağlantı)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'alert') {
    vr_csrf_check();
    $lotFor = (string)($_POST['lot'] ?? '');

    if (!vr_rate_ok('alert', 10, 900)) {
        vr_flash(t('login_throttled'), 'err');
    } else {
        // "249,90" / "249.90" / "249" → kuruş
        $raw = str_replace([' ', "\u{202F}", '€'], '', (string)($_POST['price'] ?? ''));
        $cents = (int)round(((float)str_replace(',', '.', $raw)) * 100);

        [$ok, $key] = vr_alert_add($lotFor, (string)($_POST['email'] ?? ''), $cents, vr_lang());
        vr_flash(t($key), $ok ? 'ok' : 'err');
    }
    vr_redirect('outlet.php', ['lot' => $lotFor]);
}

if (!empty($_GET['cancel_alert'])) {
    $gone = vr_alert_cancel((string)$_GET['cancel_alert']);
    vr_flash(t($gone ? 'unsub_ok' : 'unsub_fail'), $gone ? 'ok' : 'err');
    vr_redirect('outlet.php');
}

$lotId = trim((string)($_GET['lot'] ?? ''));
$single = $lotId !== '' ? vr_vault_find($lotId) : null;

if ($lotId !== '' && $single === null) {
    http_response_code(404);
}

$steps = (int)vr_config('vault_steps', 6);
$hours = (int)vr_config('vault_step_hours', 24);

// ---------------------------------------------------------------------------
// TEK LOS GÖRÜNÜMÜ
// ---------------------------------------------------------------------------
if ($single !== null) {
    $p  = $single['product'];
    $st = $single['state'];
    $seller = vr_product_seller($p);
    $locked = $single['members_only'] && !vr_is_member();

    vr_layout_start([
        'title'  => $p['brand'] . ' ' . vr_card_name($p) . ' — ' . t('vault_title'),
        'desc'   => $p['brand'] . ' ' . vr_card_name($p) . ' — ' . vr_product_teaser($p),
        'og_image' => vr_product_image($p),
        'body_class' => 'is-vault',
        'jsonld' => [
            vr_jsonld_product($p, (int)$st['cents']),
            vr_jsonld_breadcrumbs([
                (string)vr_config('brand') => vr_url('/'),
                t('vault_title')           => vr_url('outlet.php'),
                $p['brand']                => null,
            ]),
        ],
    ]);
?>
<section class="sec vault">
  <div class="wrap">
    <?php vr_breadcrumbs([
        t('nav_outlet') => vr_url('outlet.php'),
        $p['brand']     => vr_url('shop.php', ['brand' => $p['brand']]),
        vr_card_name($p) => null,
    ]); ?>

    <div class="pdp">
      <?php $shots = vr_product_gallery($p, 5); ?>
      <div class="pdp__media<?= count($shots) > 1 ? ' pdp__media--multi' : '' ?>" data-gallery>
        <?php foreach ($shots as $i => $src): ?>
          <a class="pdp__shot" href="<?= h($src) ?>" data-zoom
             aria-label="<?= te('zoom') ?>: <?= h($p['brand'] . ' ' . vr_card_name($p)) ?>">
            <img src="<?= h(vr_img($src, 900)) ?>"
                 srcset="<?= h(vr_img_srcset($src, [420, 600, 900, 1400])) ?>"
                 sizes="(max-width:940px) 96vw, 640px"
                 alt="<?= h($p['brand'] . ' ' . $p['name']) ?>"
                 loading="<?= $i === 0 ? 'eager' : 'lazy' ?>" width="800" height="1000">
            <span class="pdp__zoom" aria-hidden="true"><?= vr_icon('search', 16) ?></span>
          </a>
        <?php endforeach; ?>
      </div>

      <div class="pdp__side">
        <div>
          <p class="pdp__brand" style="color:var(--brass-text)"><?= h($p['brand']) ?></p>
          <h1><?= h(vr_card_name($p)) ?></h1>
        </div>

        <!-- fiyat bloğu: plan tam görünür -->
        <div class="panel" style="border-color:rgba(245,242,236,.16)">
          <h2 style="color:var(--brass-text)"><?= te('vault_price_now') ?></h2>
          <div class="lot__price">
            <span class="lot__now" style="font-size:clamp(34px,4.4vw,52px)"><?= h(vr_money((int)$st['cents'])) ?></span>
            <?php if ($st['reference'] !== null): ?><s class="lot__ref"><?= h(vr_money((int)$st['reference'])) ?></s><?php endif; ?>
          </div>
          <p class="pdp__vat" style="color:var(--muted-2)">
            <?= te('vat_incl', ['rate' => (int)vr_config('vat_rate_bps', 1900) / 100]) ?>
          </p>
          <?php if ($st['reference'] !== null): ?>
            <p class="lot__legal"><?= te('vault_lowest30', ['amount' => vr_money((int)$st['reference'])]) ?></p>
          <?php endif; ?>
          <?php if (!empty($single['rrp_cents']) && (int)$single['rrp_cents'] > (int)$st['cents']): ?>
            <p class="lot__legal"><?= te('vault_rrp', ['amount' => vr_money((int)$single['rrp_cents'])]) ?></p>
          <?php endif; ?>

          <div class="lot__meter" style="margin-top:12px">
            <span style="--p:<?= (int)round(($st['step'] / max(1, $st['steps'])) * 100) ?>%"></span>
          </div>

          <?php if (!$single['sold'] && $st['next_at']): ?>
            <p class="lot__drop">
              <?= vr_icon('clock', 15) ?>
              <span><?= te('vault_next_drop') ?></span>
              <span class="lot__cd" data-countdown="<?= (int)$st['next_at'] ?>">—</span>
              <span class="lot__then"><?= te('vault_next_price', ['amount' => vr_money((int)$st['next_cents'])]) ?></span>
            </p>
          <?php elseif (!$single['sold']): ?>
            <p class="lot__drop lot__drop--floor"><?= te('vault_at_floor') ?> · <?= te('vault_floor_note') ?></p>
          <?php endif; ?>
        </div>

        <?php if ($locked): ?>
          <div class="notice"><?= te('vault_early', ['time' => vr_until($single['members_until'])]) ?>
            <a class="link" style="color:var(--brass-text)" href="<?= h(vr_url('/')) ?>#member"><?= te('news_cta') ?></a>
          </div>
        <?php elseif ($single['sold'] || !$single['available']): ?>
          <span class="btn btn--block is-disabled"><span><?= te('vault_gone') ?></span></span>
        <?php else: ?>
          <?php
          /* Beden seçimi burada da gerekli. Önceden los formu bedeni sessizce
             dizinin ilkine sabitliyordu; beden seti olan bir parçada (S×1 ·
             M×3 · L×3 …) alıcı istediğini seçemiyor, eline başka beden
             geçiyordu. Mağaza sayfasındaki davranışın aynısı. */
          $lotSizes = array_values(array_filter((array)$p['sizes'], fn($s) => (int)($s['qty'] ?? 0) > 0));
          $oneSize  = count($lotSizes) <= 1;
          ?>
          <form method="post" action="<?= h(vr_url('cart.php')) ?>" <?= $oneSize ? '' : 'data-needs-size' ?>>
            <?= vr_csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="pid" value="<?= h($p['id']) ?>">
            <input type="hidden" name="lot" value="<?= h($single['lot_id']) ?>">

            <?php if ($oneSize): ?>
              <input type="hidden" name="size" value="<?= h($lotSizes[0]['label'] ?? 'ONE') ?>">
            <?php else: ?>
              <div class="panel" style="border:0;padding:0;margin-bottom:16px">
                <h2 class="panel__h" style="color:var(--brass-text)">
                  <span><?= te('select_size') ?></span>
                  <a class="link" style="color:var(--brass-text)"
                     href="<?= h(vr_url('size-guide.php', ['cat' => $p['cat']])) ?>">
                    <?= vr_icon('ruler', 14) ?> <?= te('size_guide') ?>
                  </a>
                </h2>
                <div class="sizes">
                  <?php foreach ($lotSizes as $s): ?>
                    <label class="size">
                      <input type="radio" name="size" value="<?= h($s['label']) ?>">
                      <?= h($s['label']) ?>
                      <?php if ((int)$s['qty'] <= 2): ?><i><?= (int)$s['qty'] ?>×</i><?php endif; ?>
                    </label>
                  <?php endforeach; ?>
                </div>
                <p class="field__err" data-size-error hidden><?= te('size_missing') ?></p>
              </div>
            <?php endif; ?>

            <button class="btn btn--brass btn--block btn--lg" type="submit">
              <span><?= te('vault_take_it', ['amount' => vr_money((int)$st['cents'])]) ?></span>
            </button>
          </form>
          <p style="font-size:11.5px;color:var(--muted-2)">
            <?= vr_icon('lock', 13) ?> <?= te('checkout_stripe') ?>
          </p>
        <?php endif; ?>

        <!-- fiyat alarmı: kullanıcı istediği fiyatı söylesin, biz haber verelim -->
        <?php if (!$single['sold'] && $st['phase'] === 'live' && !$st['at_floor']): ?>
          <div class="alert">
            <h2><?= te('alert_t') ?></h2>
            <p><?= te('alert_b') ?></p>
            <form method="post" action="<?= h(vr_url('outlet.php')) ?>">
              <?= vr_csrf_field() ?>
              <input type="hidden" name="action" value="alert">
              <input type="hidden" name="lot" value="<?= h($single['lot_id']) ?>">
              <div class="alert__row">
                <label class="vh" for="al-mail"><?= te('news_email') ?></label>
                <input id="al-mail" type="email" name="email" required placeholder="<?= te('news_email') ?>" autocomplete="email">
                <label class="vh" for="al-price"><?= te('alert_price') ?></label>
                <input id="al-price" type="text" inputmode="decimal" name="price" required
                       style="flex:0 1 130px"
                       value="<?= h(vr_money((int)($st['next_cents'] ?? $st['floor_cents']), false)) ?>"
                       aria-describedby="al-hint">
                <button class="btn btn--brass btn--sm" type="submit"><span><?= te('alert_cta') ?></span></button>
              </div>
              <p id="al-hint" class="lot__legal" style="margin-top:10px">
                <?= te('vault_floor_price') ?>: <?= h(vr_money((int)$st['floor_cents'])) ?> ·
                <?= te('vault_price_now') ?>: <?= h(vr_money((int)$st['cents'])) ?>
                <?php $an = vr_alert_count($single['lot_id']); if ($an >= 3): ?>
                  · <?= (int)$an ?> <?= te('alert_t') ?>
                <?php endif; ?>
              </p>
            </form>
          </div>
        <?php endif; ?>

        <!-- planın tamamı: hangi kademede ne olacak -->
        <div class="panel" style="border-color:rgba(245,242,236,.16)">
          <h2 style="color:var(--brass-text)"><?= te('vault_how') ?></h2>
          <table class="table" style="color:var(--muted)">
            <thead><tr>
              <th style="color:var(--muted-2)"><?= te('vault_stage', ['x' => '#', 'y' => (int)$st['steps'] + 1]) ?></th>
              <th style="color:var(--muted-2)"><?= te('filter_price') ?></th>
              <th style="color:var(--muted-2)"><?= te('vault_next_drop') ?></th>
            </tr></thead>
            <tbody>
            <?php
            $open  = (int)$st['open_cents'];
            $floor = (int)$st['floor_cents'];
            $n     = (int)$st['steps'];
            for ($i = 0; $i <= $n; $i++) {
                $price = $i === 0 ? $open : ($i >= $n ? $floor : (int)round($open - ($open - $floor) * ($i / $n)));
                // Kademe başlangıcı: mevcut kademenin zamanından geriye/ileriye adım.
                $at = ($st['next_at'] ?? 0) > 0
                    ? (int)$st['next_at'] + ($i - (int)$st['step'] - 1) * $hours * 3600
                    : 0;
                $isNow = $i === (int)$st['step'];
                echo '<tr' . ($isNow ? ' style="color:var(--bone)"' : '') . '>';
                echo '<td>' . ($i + 1) . ($isNow ? ' · ' . h(t('vault_live_now')) : '') . '</td>';
                echo '<td style="font-variant-numeric:tabular-nums">' . h(vr_money($price)) . '</td>';
                echo '<td>' . ($at > 0 ? h(vr_date($at, true)) : '—') . '</td>';
                echo '</tr>';
            }
            ?>
            </tbody>
          </table>
          <p class="lot__legal"><?= te('vault_fairness_b') ?></p>
        </div>

        <!-- satıcı + hukuki durum -->
        <div class="panel panel--seller" style="border-color:rgba(245,242,236,.16)">
          <?= vr_icon($seller['type'] === 'private' ? 'user' : 'shield', 22) ?>
          <div>
            <p class="seller__name" style="color:var(--bone)"><?= h($seller['name']) ?></p>
            <p class="seller__type"><?= te('sold_by') ?> · <?= te('seller_' . $seller['type']) ?></p>
            <p class="seller__note"><?= te('seller_' . $seller['type'] . '_note', ['days' => (int)vr_config('return_days', 30)]) ?></p>
          </div>
        </div>

        <?php $copy = vr_product_copy($p); ?>
        <div class="panel" style="border-color:rgba(245,242,236,.16)">
          <h2 style="color:var(--brass-text)"><?= te('description') ?></h2>
          <?php if (($copy['lead'] ?? '') !== ''): ?>
            <p class="pdp__lead"><?= h($copy['lead']) ?></p>
          <?php endif; ?>
          <?php foreach ($copy['paras'] as $para): ?><p><?= h($para) ?></p><?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<?php
    $more = array_values(array_filter(vr_vault_lots(['limit' => 4]), fn($v) => $v['lot_id'] !== $single['lot_id']));
    if ($more):
?>
<section class="sec vault" style="padding-top:0">
  <div class="wrap">
    <?php vr_section_head(t('sec_vault'), '', vr_url('outlet.php')); ?>
    <div class="lots"><?php foreach (array_slice($more, 0, 3) as $v) vr_vault_card($v); ?></div>
  </div>
</section>
<?php endif;

    vr_layout_end();
    exit;
}

// ---------------------------------------------------------------------------
// ODA GÖRÜNÜMÜ — tüm loslar
// ---------------------------------------------------------------------------
$lots   = vr_vault_lots(['include_sold' => true]);
$live   = array_values(array_filter($lots, fn($v) => !$v['sold']));
$sold   = array_values(array_filter($lots, fn($v) => $v['sold']));
$member = vr_is_member();

// Planlanmış loslar da sayfada duruyor. Sadece canlı olanları göstermek iki
// şeyi birden kaybettiriyordu: sayfanın altı boş kalıyor ve ziyaretçi geri
// gelmek için bir sebep bulamıyor. Takvim ikisini de veriyor — üstelik
// uydurma bir kıtlık yaratmadan, çünkü tarihler gerçek plandan geliyor.
$soon = array_values(array_filter(
    vr_vault_lots(['include_scheduled' => true]),
    fn($v) => $v['state']['phase'] === 'scheduled'
));
usort($soon, fn($a, $b) => $a['state']['next_at'] <=> $b['state']['next_at']);

vr_layout_start([
    'title' => t('vault_title'),
    'desc'  => t('vault_lede'),
    'body_class' => 'is-vault',
    'jsonld' => [vr_jsonld_breadcrumbs([
        (string)vr_config('brand') => vr_url('/'),
        t('vault_title')           => null,
    ])],
]);
?>
<section class="sec vault">
  <div class="wrap">
    <p class="eyebrow"><?= te('vault_live_now') ?> · <?= te('vault_lots_n', ['n' => count($live)]) ?></p>
    <h1 style="font-size:clamp(34px,5.4vw,72px);max-width:20ch"><?= te('vault_title') ?></h1>
    <p class="lede" style="margin-top:22px"><?= te('vault_lede') ?></p>

    <?php if (!$member): ?>
      <p style="margin-top:22px">
        <a class="btn btn--brass btn--sm" href="<?= h(vr_url('/')) ?>#member">
          <span><?= te('news_cta') ?></span><?= vr_icon('arrow', 15) ?>
        </a>
      </p>
    <?php endif; ?>

    <hr class="rule" style="margin:clamp(34px,4.4vw,58px) 0">

    <div class="steps">
      <div class="step" data-reveal>
        <b>01</b><h3><?= te('vault_step1_t') ?></h3>
        <p><?= te('vault_step1_b', ['steps' => $steps + 1]) ?></p>
      </div>
      <div class="step" data-reveal>
        <b>02</b><h3><?= te('vault_step2_t') ?></h3>
        <p><?= te('vault_step2_b', ['hours' => $hours]) ?></p>
      </div>
      <div class="step" data-reveal>
        <b>03</b><h3><?= te('vault_step3_t') ?></h3>
        <p><?= te('vault_step3_b') ?></p>
      </div>
      <div class="step" data-reveal>
        <b>04</b><h3><?= te('vault_fairness') ?></h3>
        <p><?= te('vault_fairness_b') ?></p>
      </div>
    </div>
  </div>
</section>

<section class="sec vault" style="padding-top:0">
  <div class="wrap">
    <?php if (!$live && !$sold): ?>
      <p class="lede"><?= te('vault_empty') ?></p>
      <p style="margin-top:20px"><a class="btn btn--light" href="<?= h(vr_url('shop.php')) ?>"><span><?= te('nav_shop') ?></span></a></p>
    <?php else: ?>
      <div class="lots"><?php foreach ($live as $i => $v) vr_vault_card($v, ['eager' => $i < 3]); ?></div>

      <?php if ($sold): ?>
        <hr class="rule" style="margin:clamp(38px,5vw,64px) 0">
        <?php vr_section_head(t('vault_gone'), ''); ?>
        <div class="lots"><?php foreach (array_slice($sold, 0, 6) as $v) vr_vault_card($v); ?></div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($soon): ?>
      <hr class="rule" style="margin:clamp(38px,5vw,64px) 0">
      <?php vr_section_head(t('vault_upcoming'), t('vault_upcoming_b')); ?>
      <ol class="soon">
        <?php foreach ($soon as $v): $p = $v['product']; $at = (int)$v['state']['next_at']; ?>
          <li class="soon__row" data-reveal>
            <img class="soon__img" src="<?= h(vr_img(vr_product_image($p), 180)) ?>" alt="" aria-hidden="true"
                 loading="lazy" decoding="async" width="120" height="150">
            <div class="soon__who">
              <p class="soon__brand"><?= h($p['brand']) ?></p>
              <p class="soon__name"><?= h(vr_card_name($p)) ?></p>
            </div>
            <p class="soon__when">
              <?= h(vr_date($at)) ?>
              <span><?= te('vault_opens_in', ['time' => vr_until($at)]) ?></span>
            </p>
            <p class="soon__price">
              <?= h(vr_money((int)$v['state']['open_cents'])) ?>
              <span><?= te('vault_floor_price') ?> <?= h(vr_money((int)$v['state']['floor_cents'])) ?></span>
            </p>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  </div>
</section>

<?php vr_layout_end(); ?>
