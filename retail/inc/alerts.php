<?php
/**
 * Vault fiyat alarmı
 * ==================
 * "Bu los şu fiyata inince haber ver." Vault mekaniğinin doğal tamamlayıcısı:
 * plan zaten yayınlanmış olduğu için kullanıcı hangi fiyatın ne zaman
 * geleceğini biliyor — ama o saatte ekran başında olmak zorunda kalmasın.
 *
 * Tasarım kararları:
 *  • TEK e-posta. Eşik yakalandığında gönderilir ve alarm kapanır. Hatırlatma,
 *    "son şans", "hâlâ ilgileniyor musun" postası YOK — istenmeyen e-posta
 *    üretmemek için mekanizma buna izin vermiyor.
 *  • Çifte onay yok, çünkü bu reklam değil kullanıcının tek bir los için açıkça
 *    istediği bildirim. Yine de her postada iptal bağlantısı var ve alarmın
 *    kendisi tek tıkla silinebiliyor.
 *  • Eşik, o anki fiyatın ALTINDA ve tabanın üstünde olmak zorunda: asla
 *    gerçekleşmeyecek bir alarm kurulmasın.
 */

declare(strict_types=1);

require_once __DIR__ . '/boot.php';
require_once __DIR__ . '/vault.php';
require_once __DIR__ . '/mail.php';

const VR_ALERTS_FILE = 'retail-alerts.json';
const VR_ALERTS_MAX_PER_MAIL = 20;      // aynı adresten sınırsız alarm kurulmasın

function vr_alerts_all(): array
{
    $d = vr_store_read(VR_ALERTS_FILE, []);
    return is_array($d) ? $d : [];
}

/**
 * Alarm kur. Dönüş: [ok, mesaj_anahtari]
 */
function vr_alert_add(string $lotId, string $email, int $thresholdCents, string $lang = 'en'): array
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return [false, 'alert_err'];

    $lot = vr_vault_find($lotId);
    if ($lot === null || $lot['sold']) return [false, 'vault_gone'];

    $st = $lot['state'];
    // Şu anki fiyattan yüksek bir eşik anlamsız (zaten geçilmiş);
    // tabanın altı ise hiç gerçekleşmez.
    if ($thresholdCents >= (int)$st['cents'] || $thresholdCents < (int)$st['floor_cents']) {
        return [false, 'alert_err'];
    }

    $ok = false;
    $reason = 'alert_err';

    vr_store_mutate(VR_ALERTS_FILE, static function ($rows) use ($lotId, $email, $thresholdCents, $lang, &$ok, &$reason) {
        if (!is_array($rows)) $rows = [];

        $mine = 0;
        foreach ($rows as $i => $r) {
            if (strtolower((string)($r['email'] ?? '')) !== $email) continue;
            $mine++;
            // Aynı los için ikinci alarm: eşiği güncelle, yeni satır açma.
            if ((string)($r['lot_id'] ?? '') === $lotId && empty($r['sent_at'])) {
                $rows[$i]['threshold_cents'] = $thresholdCents;
                $rows[$i]['created_at']      = time();
                $ok = true;
                return $rows;
            }
        }
        if ($mine >= VR_ALERTS_MAX_PER_MAIL) { $reason = 'login_throttled'; return null; }

        $rows[] = [
            'id'              => 'al-' . bin2hex(random_bytes(8)),
            'lot_id'          => $lotId,
            'email'           => $email,
            'threshold_cents' => $thresholdCents,
            'lang'            => $lang,
            'created_at'      => time(),
            'sent_at'         => 0,
            // Rıza kanıtı: ne zaman, hangi kaynaktan (IP ham değil, tuzlanmış).
            'ip_hash'         => substr(hash('sha256', vr_client_ip() . vr_member_secret()), 0, 24),
        ];
        $ok = true;
        return $rows;
    });

    return $ok ? [true, 'alert_ok'] : [false, $reason];
}

/** Tek alarmı sil (e-postadaki bağlantı). */
function vr_alert_cancel(string $token): bool
{
    $hit = false;
    vr_store_mutate(VR_ALERTS_FILE, static function ($rows) use ($token, &$hit) {
        if (!is_array($rows)) return null;
        foreach ($rows as $i => $r) {
            if (!hash_equals(vr_alert_token((string)($r['id'] ?? '')), $token)) continue;
            unset($rows[$i]);
            $hit = true;
            return array_values($rows);
        }
        return null;
    });
    return $hit;
}

function vr_alert_token(string $id): string
{
    return substr(hash_hmac('sha256', 'alert|' . $id, vr_member_secret()), 0, 32);
}

/**
 * Eşiği yakalayan alarmları gönder. Cron/Workflow'dan çağrılır.
 * Dönüş: ['sent'=>int, 'dropped'=>int, 'checked'=>int, 'lines'=>[...]]
 */
function vr_alerts_run(bool $dryRun = false): array
{
    $rows    = vr_alerts_all();
    $now     = time();
    $sent    = 0;
    $dropped = 0;
    $lines   = [];
    $keep    = [];

    foreach ($rows as $r) {
        if (!is_array($r)) continue;

        $lotId = (string)($r['lot_id'] ?? '');
        $lot   = vr_vault_find($lotId);

        // Los bitti/kapandı → alarmı sessizce düşür. "Kaçırdınız" postası
        // atmıyoruz; istenmeyen posta üretmenin en kolay yolu o olurdu.
        if ($lot === null || $lot['sold'] || $lot['state']['phase'] !== 'live') {
            $dropped++;
            $lines[] = 'drop  ' . $lotId . ' (los kapandı)';
            continue;
        }
        // 90 günden eski, hâlâ tetiklenmemiş alarmlar da temizlenir.
        if ((int)($r['created_at'] ?? 0) < $now - 90 * 86400) {
            $dropped++;
            $lines[] = 'drop  ' . $lotId . ' (90 gün doldu)';
            continue;
        }

        $price = (int)$lot['state']['cents'];
        $thr   = (int)($r['threshold_cents'] ?? 0);

        if ($price > $thr) {
            $keep[] = $r;                                   // henüz zamanı değil
            continue;
        }

        $lines[] = sprintf('SEND  %s  %s <= %s  →  %s',
            $lotId, vr_money($price), vr_money($thr), substr((string)$r['email'], 0, 3) . '***');

        if ($dryRun) { $keep[] = $r; continue; }

        if (vr_alert_send($r, $lot)) {
            $sent++;
            // Gönderildi → alarm biter. Tek posta sözü buradan geliyor.
        } else {
            $keep[] = $r;                                   // gönderilemedi, sonra dene
        }
    }

    if (!$dryRun && count($keep) !== count($rows)) {
        vr_store_write(VR_ALERTS_FILE, array_values($keep));
    }

    return ['sent' => $sent, 'dropped' => $dropped, 'checked' => count($rows), 'lines' => $lines];
}

/** Bildirim postası — kısa, tek çağrılı, iptal bağlantılı. */
function vr_alert_send(array $alert, array $lot): bool
{
    $p     = $lot['product'];
    $price = (int)$lot['state']['cents'];
    $url   = vr_abs('outlet.php', ['lot' => $lot['lot_id']]);
    $cancel = vr_abs('outlet.php', ['cancel_alert' => vr_alert_token((string)$alert['id'])]);

    $body = '<p style="font-size:13px;color:#8a8578;letter-spacing:.12em;text-transform:uppercase">'
          . h($p['brand']) . '</p>'
          . '<p style="font-family:Georgia,serif;font-size:24px;margin:4px 0 16px">'
          . h(vr_card_name($p)) . '</p>'
          . '<p style="font-family:Georgia,serif;font-size:34px;margin:0">' . h(vr_money($price)) . '</p>'
          . '<p style="font-size:13px;color:#5c584e;margin-top:6px">'
          . 'Ihr Wunschpreis: ' . h(vr_money((int)$alert['threshold_cents']))
          . ' · Stufe ' . ((int)$lot['state']['step'] + 1) . ' von ' . ((int)$lot['state']['steps'] + 1) . '</p>'
          . '<p style="margin-top:24px"><a href="' . h($url) . '"'
          . ' style="display:inline-block;background:#c9a24d;color:#12121a;text-decoration:none;'
          . 'padding:13px 24px;letter-spacing:.1em;font-size:13px;font-family:Helvetica,Arial,sans-serif">'
          . 'Jetzt ansehen</a></p>'
          . '<p style="font-size:12.5px;color:#5c584e;margin-top:20px">'
          . 'Das Los existiert nur einmal — es endet mit dem ersten Käufer. Fällt der Preis weiter, '
          . 'schreiben wir Ihnen nicht noch einmal: dies war die einzige Benachrichtigung für diesen Alarm.'
          . '</p>'
          . '<p style="font-size:11.5px;color:#8a8578;margin-top:18px">'
          . '<a href="' . h($cancel) . '" style="color:#8a8578">Alarm löschen</a></p>';

    [$ok] = vr_mail_send(
        (string)$alert['email'],
        vr_money($price) . ' · ' . $p['brand'] . ' ' . vr_card_name($p),
        vr_mail_layout(t('alert_t'), $body, vr_money($price) . ' — ' . $p['brand']),
        ['unsubscribe' => $cancel]
    );
    return $ok;
}

/** Bir los için kurulu alarm sayısı — los sayfasında sosyal sinyal olarak. */
function vr_alert_count(string $lotId): int
{
    $n = 0;
    foreach (vr_alerts_all() as $r) {
        if ((string)($r['lot_id'] ?? '') === $lotId && empty($r['sent_at'])) $n++;
    }
    return $n;
}
