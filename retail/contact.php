<?php
/**
 * Kontakt
 * -------
 * Gerçekten çalışan bir form: mesaj destek adresine gider, kopyası
 * data/retail-messages.json'a yazılır (mail düşse bile kayıp olmasın).
 *
 * Spam koruması reCAPTCHA DEĞİL: Google'a veri aktarmadan çözülebilecek bir
 * sorun için ziyaretçiyi izlemeye açmak orantısız. Onun yerine bal küpü alanı
 * + form açılış zamanı + hız sınırı — bu üçlü otomatik gönderimlerin çok
 * büyük kısmını kesiyor ve hiçbir kişisel veri üçüncü tarafa gitmiyor.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/view.php';
require_once __DIR__ . '/inc/mail.php';

$done = false;
$err  = '';
$in   = ['name' => '', 'email' => '', 'order' => '', 'subject' => '', 'message' => ''];

$subjects = [
    'order'    => t('faq_cat_order'),
    'shipping' => t('faq_cat_ship'),
    'return'   => t('faq_cat_return'),
    'vault'    => t('faq_cat_vault'),
    'size'     => t('size_guide'),
    'sell'     => t('faq_cat_sell'),
    'other'    => t('more'),
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();
    foreach ($in as $k => $_) $in[$k] = trim((string)($_POST[$k] ?? ''));

    $trap    = trim((string)($_POST['website'] ?? ''));      // bal küpü: insan doldurmaz
    $started = (int)($_POST['ts'] ?? 0);
    $tooFast = $started > 0 && (time() - $started) < 3;      // 3 sn'den hızlı = bot

    if (!vr_rate_ok('contact', 6, 900)) {
        $err = t('login_throttled');
    } elseif ($trap !== '' || $tooFast) {
        // Bota "başardın" de, gerçekten gönderme. Hata göstermek botun
        // deneme yanılmasına yardım ederdi.
        $done = true;
        vr_log('contact_spam_blocked', ['trap' => $trap !== '', 'fast' => $tooFast]);
    } elseif (!filter_var($in['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($in['message']) < 10) {
        $err = t('contact_err');
    } else {
        $subjectKey  = isset($subjects[$in['subject']]) ? $in['subject'] : 'other';
        $subjectText = $subjects[$subjectKey];

        $row = [
            'at'      => time(),
            'name'    => mb_substr($in['name'], 0, 120),
            'email'   => mb_substr($in['email'], 0, 160),
            'order'   => mb_substr($in['order'], 0, 40),
            'subject' => $subjectKey,
            'message' => mb_substr($in['message'], 0, 4000),
            'lang'    => vr_lang(),
        ];
        vr_store_append('retail-messages.json', $row);

        $to = (string)(vr_config('company')['email'] ?? '');
        if ($to !== '') {
            $body = '<p><strong>' . h($subjectText) . '</strong></p>'
                  . '<table style="font-size:14px">'
                  . '<tr><td>Name</td><td>' . h($row['name'] !== '' ? $row['name'] : '—') . '</td></tr>'
                  . '<tr><td>E-Mail</td><td>' . h($row['email']) . '</td></tr>'
                  . '<tr><td>Bestellung</td><td>' . h($row['order'] !== '' ? $row['order'] : '—') . '</td></tr>'
                  . '<tr><td>Sprache</td><td>' . h($row['lang']) . '</td></tr>'
                  . '</table>'
                  . '<hr><p>' . nl2br(h($row['message'])) . '</p>';

            // Reply-To müşterinin adresi: destek doğrudan "yanıtla" diyebilsin.
            vr_mail_send($to, '[' . vr_config('brand') . '] ' . $subjectText
                . ($row['order'] !== '' ? ' · ' . $row['order'] : ''),
                vr_mail_layout(t('nav_contact'), $body), ['reply_to' => $row['email']]);
        }

        // Gönderene teyit — mesajın gerçekten ulaştığını bilsin.
        vr_mail_send($row['email'], vr_config('brand') . ' — ' . t('nav_contact'),
            vr_mail_layout(t('nav_contact'),
                '<p>' . te('contact_ok') . '</p>'
                . '<blockquote style="border-left:2px solid #ddd6c8;padding-left:14px;color:#5c584e;font-size:14px">'
                . nl2br(h($row['message'])) . '</blockquote>'));

        $done = true;
        $in = array_map(fn() => '', $in);
    }
}

$co    = vr_config('company');
$email = trim((string)($co['email'] ?? ''));

vr_layout_start([
    'title'  => t('nav_contact'),
    'desc'   => t('contact_sub'),
    'jsonld' => [vr_jsonld_breadcrumbs([
        (string)vr_config('brand') => vr_url('/'),
        t('nav_contact')           => null,
    ])],
]);
?>
<section class="sec sec--tight">
  <div class="wrap">
    <?php vr_breadcrumbs([t('footer_service') => null, t('nav_contact') => null]); ?>

    <div class="cart" style="align-items:start">
      <div>
        <h1 class="sechead__t"><?= te('nav_contact') ?></h1>
        <p class="sechead__s" style="margin:12px 0 28px"><?= te('contact_sub') ?></p>

        <?php if ($done): ?>
          <div class="flash flash--ok" style="margin-bottom:20px"><?= te('contact_ok') ?></div>
          <a class="btn btn--ghost" href="<?= h(vr_url('shop.php')) ?>"><span><?= te('keep_shopping') ?></span></a>
        <?php else: ?>
          <?php if ($err !== ''): ?>
            <div class="flash flash--err" style="margin-bottom:16px"><?= h($err) ?></div>
          <?php endif; ?>

          <form class="form form--wide" method="post" action="<?= h(vr_url('contact.php')) ?>">
            <?= vr_csrf_field() ?>
            <input type="hidden" name="ts" value="<?= time() ?>">
            <?php // Bal küpü: ekranda görünmez, ekran okuyucudan da gizli. ?>
            <div aria-hidden="true" style="position:absolute;left:-9999px" tabindex="-1">
              <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>

            <div class="grid grid--2" style="gap:16px">
              <div class="field">
                <label for="name"><?= te('your_name') ?></label>
                <input id="name" name="name" autocomplete="name" value="<?= h($in['name']) ?>">
              </div>
              <div class="field">
                <label for="email"><?= te('email') ?></label>
                <input id="email" name="email" type="email" required autocomplete="email" value="<?= h($in['email']) ?>">
              </div>
            </div>

            <div class="grid grid--2" style="gap:16px">
              <div class="field">
                <label for="subject"><?= te('contact_subject') ?></label>
                <select id="subject" name="subject">
                  <?php foreach ($subjects as $k => $label): ?>
                    <option value="<?= h($k) ?>" <?= $in['subject'] === $k ? 'selected' : '' ?>><?= h($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="field">
                <label for="order"><?= te('contact_order') ?></label>
                <input id="order" name="order" value="<?= h($in['order']) ?>"
                       placeholder="<?= h((string)vr_config('order_prefix', 'VS')) ?>-2608-A1B2C">
              </div>
            </div>

            <div class="field">
              <label for="message"><?= te('contact_msg') ?></label>
              <textarea id="message" name="message" required minlength="10"><?= h($in['message']) ?></textarea>
              <small>Modellcode, Größe oder Bestellnummer helfen uns, sofort zu antworten.</small>
            </div>

            <button class="btn btn--lg" type="submit"><span><?= te('contact_send') ?></span><?= vr_icon('arrow', 16) ?></button>

            <p style="font-size:12px;color:var(--muted)">
              Ihre Angaben verwenden wir ausschließlich zur Bearbeitung dieser Anfrage —
              <a class="link" href="<?= h(vr_url('legal/datenschutz.php')) ?>"><?= te('legal_privacy') ?></a>.
              Kein Newsletter, keine Weitergabe.
            </p>
          </form>
        <?php endif; ?>
      </div>

      <aside class="summary">
        <h2><?= te('faq_contact_t') ?></h2>
        <?php if ($email !== ''): ?>
          <p style="font-size:14px">
            <?= vr_icon('mail', 15) ?>
            <a class="link" href="mailto:<?= h($email) ?>"><?= h($email) ?></a>
          </p>
        <?php endif; ?>
        <?php if (trim((string)($co['phone'] ?? '')) !== ''): ?>
          <p style="font-size:14px"><?= h((string)$co['phone']) ?></p>
        <?php endif; ?>
        <p class="srow--muted" style="display:block">Antwort innerhalb eines Werktags, Mo–Fr.</p>

        <hr class="rule" style="margin:8px 0">

        <h2><?= te('faq_title') ?></h2>
        <p style="font-size:13.5px;color:var(--muted)">
          Die meisten Fragen — Versand, Rückgabe, Vault, Echtheit — stehen dort schon beantwortet.
        </p>
        <a class="btn btn--ghost btn--block btn--sm" href="<?= h(vr_url('faq.php')) ?>"><span><?= te('faq_title') ?></span></a>

        <p class="srow--muted" style="display:block">
          <a class="link" href="<?= h(vr_url('order.php')) ?>"><?= te('order_status') ?></a> ·
          <a class="link" href="<?= h(vr_url('size-guide.php')) ?>"><?= te('size_guide') ?></a> ·
          <a class="link" href="<?= h(vr_url('legal/impressum.php')) ?>"><?= te('legal_imprint') ?></a>
        </p>
      </aside>
    </div>
  </div>
</section>
<?php vr_layout_end(); ?>
