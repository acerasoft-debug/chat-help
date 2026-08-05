<?php
/**
 * Vault üyeliği — çifte onay (double opt-in)
 * ------------------------------------------
 * Almanya'da tek tıkla listeye eklemek yeterli DEĞİL: onay e-postası ile
 * doğrulanmayan abonelik reklam hukuku açısından risklidir (§7 UWG). Bu yüzden
 * akış: form → bekleyen kayıt + onay maili → linke tıklama → üyelik + imzalı
 * çerez (erken erişim için).
 *
 * E-posta adresi düz metin saklanır (gönderim için gerekli), ama onay öncesi
 * kayıtlar 7 gün sonra otomatik siliniyor.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/view.php';
require_once __DIR__ . '/inc/mail.php';

const VR_NEWS_FILE = 'retail-members.json';

$done = '';
$err  = '';

// ------------------------------------------------------------------ onay linki
$confirm = trim((string)($_GET['confirm'] ?? ''));
if ($confirm !== '') {
    $matched = null;

    vr_store_mutate(VR_NEWS_FILE, static function ($rows) use ($confirm, &$matched) {
        if (!is_array($rows)) return null;
        foreach ($rows as $i => $r) {
            if (!hash_equals((string)($r['token'] ?? ''), $confirm)) continue;
            $rows[$i]['confirmed_at'] = time();
            $rows[$i]['token']        = '';        // tek kullanımlık
            $matched = $rows[$i];
            return $rows;
        }
        return null;
    });

    if ($matched !== null) {
        vr_member_grant((string)$matched['email']);
        $done = t('news_ok');
        vr_flash(t('connect_ready'), 'ok');
        vr_redirect('outlet.php');
    }
    $err = t('order_not_found');
}

// ------------------------------------------------------------------ abonelik iptali
/**
 * Abmeldung. Datenschutzerklärung "her e-postada abonelikten çıkma bağlantısı"
 * diyor — o bağlantının gerçekten çalışması gerek. Jeton, e-postadan HMAC ile
 * türetiliyor: listede saklanan ayrı bir alan yok, bağlantı süresiz geçerli ve
 * tahmin edilemez.
 */
$unsub = trim((string)($_GET['unsubscribe'] ?? ''));
if ($unsub !== '') {
    $hit = false;

    vr_store_mutate(VR_NEWS_FILE, static function ($rows) use ($unsub, &$hit) {
        if (!is_array($rows)) return null;
        foreach ($rows as $i => $r) {
            $mail = (string)($r['email'] ?? '');
            if ($mail === '' || !hash_equals(vr_unsub_token($mail), $unsub)) continue;
            unset($rows[$i]);              // kaydı tutmuyoruz: silmek en temiz iptal
            $hit = true;
            return array_values($rows);
        }
        return null;
    });

    if ($hit) {
        // Üyelik çerezini de düşür, yoksa erken erişim açık kalır.
        @setcookie('vr_member', '', [
            'expires' => time() - 3600,
            'path'    => vr_base_url() === '' ? '/' : vr_base_url() . '/',
        ]);
        $done = t('unsub_ok');
    } else {
        $err = t('unsub_fail');
    }
}

// ------------------------------------------------------------- abonelik isteği
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));

    if (!vr_rate_ok('newsletter', 6, 900)) {
        $err = t('login_throttled');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = t('news_err');
    } elseif (empty($_POST['consent'])) {
        $err = t('must_accept');
    } else {
        $token = bin2hex(random_bytes(20));
        $now   = time();

        vr_store_mutate(VR_NEWS_FILE, static function ($rows) use ($email, $token, $now) {
            if (!is_array($rows)) $rows = [];

            // Onaylanmamış ve 7 günden eski kayıtları temizle.
            $rows = array_values(array_filter($rows, static function ($r) use ($now) {
                if (!is_array($r)) return false;
                if (!empty($r['confirmed_at'])) return true;
                return (int)($r['created_at'] ?? 0) > $now - 7 * 86400;
            }));

            foreach ($rows as $i => $r) {
                if (strtolower((string)($r['email'] ?? '')) !== $email) continue;
                // Zaten kayıtlı: onaylıysa dokunma, değilse yeni token ver.
                if (empty($r['confirmed_at'])) {
                    $rows[$i]['token']      = $token;
                    $rows[$i]['created_at'] = $now;
                }
                return $rows;
            }

            $rows[] = [
                'email'      => $email,
                'token'      => $token,
                'created_at' => $now,
                'lang'       => vr_lang(),
                // Onayın kanıtı: ne zaman, hangi kaynaktan. IP'yi ham
                // tutmuyoruz, tuzlanmış özetini tutuyoruz.
                'ip_hash'    => substr(hash('sha256', vr_client_ip() . vr_member_secret()), 0, 24),
                'confirmed_at' => 0,
            ];
            return $rows;
        });

        vr_mail_newsletter_confirm($email, $token);
        $done = t('news_ok');
    }
}

$isUnsub = $unsub !== '';

vr_layout_start([
    'title'  => $isUnsub ? t('unsub_title') : t('news_title'),
    'robots' => 'noindex,follow',
]);
?>
<section class="sec">
  <div class="wrap wrap--narrow">
    <h1 class="sechead__t"><?= $isUnsub ? te('unsub_title') : te('news_title') ?></h1>
    <p class="sechead__s" style="margin:12px 0 26px">
      <?= $isUnsub ? te('unsub_ok') : te('news_sub', ['hours' => (int)vr_config('vault_member_head_start_hours', 12)]) ?>
    </p>

    <?php if ($done !== ''): ?>
      <div class="flash flash--ok" style="margin-bottom:18px"><?= h($done) ?></div>
      <a class="btn btn--ghost" href="<?= h(vr_url($isUnsub ? '/' : 'outlet.php')) ?>">
        <span><?= $isUnsub ? te('nav_shop') : te('nav_outlet') ?></span>
      </a>

    <?php else: ?>
      <?php if ($err !== ''): ?>
        <div class="flash flash--err" style="margin-bottom:18px"><?= h($err) ?></div>
      <?php endif; ?>

      <form class="form" method="post" action="<?= h(vr_url('newsletter.php')) ?>">
        <?= vr_csrf_field() ?>
        <div class="field">
          <label for="email"><?= te('news_email') ?></label>
          <input id="email" name="email" type="email" required autocomplete="email"
                 value="<?= h((string)($_POST['email'] ?? '')) ?>">
        </div>
        <label class="check">
          <input type="checkbox" name="consent" value="1" required>
          <span><?= te('news_consent') ?>
            <a class="link" href="<?= h(vr_url('legal/datenschutz.php')) ?>"><?= te('legal_privacy') ?></a>
          </span>
        </label>
        <button class="btn btn--lg" type="submit"><span><?= te('news_cta') ?></span></button>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php vr_layout_end(); ?>
