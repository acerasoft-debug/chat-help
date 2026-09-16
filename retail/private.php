<?php
/**
 * ÖZEL MÜŞTERİLER
 * ---------------
 * Dünyanın her yerinden yüksek bütçeli alıcının bir mağazada aradığı şey
 * altın yaldız değil, HİZMET KESİNLİĞİ: aradığı parçayı bulan biri, tek bir
 * muhatap ve dünyaya teslimat. Bu sayfa o üçünü sunuyor ve yalnızca gerçekten
 * var olanı vaat ediyor:
 *
 *   · Talep üzerine tedarik — gerçek: evlere doğrudan kaynağımız var, form
 *     talebi tek gelen kutusuna düşürüyor ve reply-to alıcının adresi.
 *   · Tek muhatap — gerçek: aynı kutu, aynı kişi cevap veriyor.
 *   · Dünyaya teslimat — gerçek: kasa AB, İsviçre, Körfez, Kuzey Amerika ve
 *     Asya'daki seçili pazarlara açık (inc/cart.php vr_shipping_countries).
 *
 * Vaat EDİLMEYENLER, bilerek: gümrük ödenmiş teslimat, ambalaj, telefon hattı
 * ya da randevu. Operatör bunları yapılandırınca sayfa onları gösterir;
 * yapılandırmadan söylemek yalan olurdu.
 *
 * Form contact.php ile aynı korumayı taşıyor: CSRF, bal küpü, açılış zamanı,
 * hız sınırı. reCAPTCHA yok — üçüncü tarafa veri gitmiyor.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/view.php';
require_once __DIR__ . '/inc/mail.php';

$done = false;
$err  = '';
$in   = ['name' => '', 'email' => '', 'phone' => '', 'country' => '', 'request' => '', 'message' => ''];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();
    foreach ($in as $k => $_) $in[$k] = trim((string)($_POST[$k] ?? ''));

    $trap    = trim((string)($_POST['website'] ?? ''));
    $started = (int)($_POST['ts'] ?? 0);
    $tooFast = $started > 0 && (time() - $started) < 3;

    if (!vr_rate_ok('private', 4, 900)) {
        $err = t('login_throttled');
    } elseif ($trap !== '' || $tooFast) {
        $done = true;
        vr_log('private_spam_blocked', ['trap' => $trap !== '', 'fast' => $tooFast]);
    } elseif (!filter_var($in['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($in['request']) < 6) {
        $err = t('contact_err');
    } else {
        $allowed = vr_shipping_countries();
        $row = [
            'at'      => time(),
            'name'    => mb_substr($in['name'], 0, 120),
            'email'   => mb_substr($in['email'], 0, 160),
            'phone'   => mb_substr($in['phone'], 0, 40),
            'country' => in_array(strtoupper($in['country']), $allowed, true) ? strtoupper($in['country']) : '',
            'request' => mb_substr($in['request'], 0, 600),
            'message' => mb_substr($in['message'], 0, 4000),
            'lang'    => vr_lang(),
        ];
        vr_store_append('retail-private-requests.json', $row);

        $to = (string)(vr_config('company')['email'] ?? '');
        if ($to !== '') {
            $body = '<p><strong>' . h(t('private_title')) . '</strong></p>'
                  . '<table style="font-size:14px">'
                  . '<tr><td>Name</td><td>' . h($row['name'] !== '' ? $row['name'] : '—') . '</td></tr>'
                  . '<tr><td>E-Mail</td><td>' . h($row['email']) . '</td></tr>'
                  . '<tr><td>Telefon</td><td>' . h($row['phone'] !== '' ? $row['phone'] : '—') . '</td></tr>'
                  . '<tr><td>Land</td><td>' . h($row['country'] !== '' ? $row['country'] : '—') . '</td></tr>'
                  . '<tr><td>Sprache</td><td>' . h($row['lang']) . '</td></tr>'
                  . '</table>'
                  . '<p><strong>' . h(t('private_req_label')) . '</strong><br>' . nl2br(h($row['request'])) . '</p>'
                  . ($row['message'] !== '' ? '<hr><p>' . nl2br(h($row['message'])) . '</p>' : '');
            vr_mail_send($to, '[' . vr_config('brand') . '] ' . t('private_title') . ' · ' . mb_substr($row['request'], 0, 60),
                vr_mail_layout(t('private_title'), $body), ['reply_to' => $row['email']]);
        }
        vr_mail_send($row['email'], vr_config('brand') . ' — ' . t('private_title'),
            vr_mail_layout(t('private_title'),
                '<p>' . te('private_ok') . '</p>'
                . '<blockquote style="border-left:2px solid #e2e2e2;padding-left:14px;color:#555;font-size:14px">'
                . nl2br(h($row['request'])) . '</blockquote>'));

        $done = true;
        $in = array_map(fn() => '', $in);
    }
}

$co    = vr_config('company');
$email = trim((string)($co['email'] ?? ''));
$phone = trim((string)($co['phone'] ?? ''));

vr_layout_start([
    'title'  => t('private_title'),
    'desc'   => t('private_lede'),
    'jsonld' => [vr_jsonld_breadcrumbs([(string)vr_config('brand') => vr_url('/'), t('private_title') => null])],
]);
?>
<section class="sec sec--tight">
  <div class="wrap">
    <div class="doc">
      <?php vr_breadcrumbs([t('private_title') => null]); ?>
      <h1><?= te('private_title') ?></h1>
      <p class="pdp__lead" style="margin:14px 0 30px"><?= te('private_lede') ?></p>

      <div class="pc__three">
        <div><b>01</b><h2><?= te('private_p1_t') ?></h2><p><?= te('private_p1_b') ?></p></div>
        <div><b>02</b><h2><?= te('private_p2_t') ?></h2><p><?= te('private_p2_b') ?></p></div>
        <div><b>03</b><h2><?= te('private_p3_t') ?></h2>
          <p><?= te('private_p3_b') ?> <a class="link" href="<?= h(vr_url('legal/versand.php')) ?>"><?= te('legal_shipping') ?></a></p></div>
      </div>

      <hr class="rule" style="margin:38px 0 34px">

      <?php if ($done): ?>
        <div class="notice"><strong><?= te('private_ok') ?></strong></div>
      <?php else: ?>
        <h2 style="margin-bottom:8px"><?= te('private_form_t') ?></h2>
        <p style="font-size:14px;color:var(--muted);margin-bottom:22px"><?= te('private_form_s') ?></p>
        <?php if ($err !== ''): ?><div class="notice" style="border-left-color:var(--ember)"><?= h($err) ?></div><?php endif; ?>

        <form method="post" class="form form--pc" novalidate>
          <?= vr_csrf_field() ?>
          <input type="hidden" name="ts" value="<?= time() ?>">
          <div aria-hidden="true" style="position:absolute;left:-9999px" tabindex="-1">
            <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
          </div>

          <div class="form__pair">
            <div class="field">
              <label for="pc-name"><?= te('your_name') ?></label>
              <input id="pc-name" name="name" autocomplete="name" value="<?= h($in['name']) ?>">
            </div>
            <div class="field">
              <label for="pc-email"><?= te('email') ?></label>
              <input id="pc-email" name="email" type="email" required autocomplete="email" value="<?= h($in['email']) ?>">
            </div>
          </div>
          <div class="form__pair">
            <div class="field">
              <label for="pc-phone"><?= te('private_phone') ?></label>
              <input id="pc-phone" name="phone" type="tel" autocomplete="tel" value="<?= h($in['phone']) ?>">
            </div>
            <div class="field">
              <label for="pc-country"><?= te('private_country') ?></label>
              <select id="pc-country" name="country">
                <option value="">—</option>
                <?php foreach (vr_shipping_countries() as $cc): ?>
                  <option value="<?= h($cc) ?>"<?= strtoupper($in['country']) === $cc ? ' selected' : '' ?>><?= h($cc) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="field">
            <label for="pc-request"><?= te('private_req_label') ?></label>
            <input id="pc-request" name="request" required maxlength="600"
                   placeholder="<?= te('private_req_hint') ?>" value="<?= h($in['request']) ?>">
          </div>
          <div class="field">
            <label for="pc-message"><?= te('contact_msg') ?></label>
            <textarea id="pc-message" name="message"><?= h($in['message']) ?></textarea>
          </div>

          <button class="btn btn--lg" type="submit"><span><?= te('private_send') ?></span><?= vr_icon('arrow', 16) ?></button>
          <p style="font-size:12px;color:var(--muted)"><?= te('private_privacy') ?>
            <a class="link" href="<?= h(vr_url('legal/datenschutz.php')) ?>"><?= te('legal_privacy') ?></a>.</p>
        </form>
      <?php endif; ?>

      <?php if ($email !== '' || $phone !== ''): ?>
        <hr class="rule" style="margin:38px 0 26px">
        <p style="font-size:14px;color:var(--muted)">
          <?= te('private_direct') ?>
          <?php if ($email !== ''): ?><a class="link" href="mailto:<?= h($email) ?>"><?= h($email) ?></a><?php endif; ?>
          <?php if ($phone !== ''): ?> · <?= h($phone) ?><?php endif; ?>
        </p>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php vr_layout_end();
