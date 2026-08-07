<?php
/**
 * E-posta — Brevo API, yedek olarak mail()
 * ----------------------------------------
 * Canlı sitedeki data/email_settings.json'u paylaşıyoruz: anahtar zaten orada,
 * gönderen adres (support@vestrasales.com) Brevo'da DKIM/DMARC ile doğrulanmış.
 * Böylece perakende siparişleri de doğrulanmış domainden çıkıyor, spam'e düşmüyor.
 */

declare(strict_types=1);

require_once __DIR__ . '/boot.php';

/**
 * Tek gönderim noktası. Dönüş: [ok, hata].
 * HTML gövde bekleniyor; düz metin karşılığı otomatik üretilir (çok istemci
 * hâlâ text/plain okuyor, ayrıca spam puanı için iyi).
 */
function vr_mail_send(string $to, string $subject, string $html, array $o = []): array
{
    $cfg = vr_mail_settings();
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return [false, 'gecersiz alici'];

    // Test/staging: gerçekten göndermeden akışı çalıştırabilmek için.
    if (getenv('VR_NO_MAIL')) {
        vr_log('mail_suppressed', ['to' => substr($to, 0, 3) . '***', 'subject' => $subject]);
        return [true, ''];
    }

    $text = trim(html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>|<\/p>|<\/tr>/i', "\n", $html) ?? ''), ENT_QUOTES, 'UTF-8'));
    $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

    $from     = (string)($o['from'] ?? $cfg['from']);
    $fromName = (string)($o['from_name'] ?? $cfg['name']);
    $replyTo  = (string)($o['reply_to'] ?? '');

    // ---- 1) Brevo
    if ($cfg['enabled'] && $cfg['provider'] === 'brevo' && $cfg['api_key'] !== '') {
        $payload = [
            'sender'      => ['email' => $from, 'name' => $fromName],
            'to'          => [['email' => $to]],
            'subject'     => $subject,
            'htmlContent' => $html,
            'textContent' => $text,
        ];
        if ($replyTo !== '') $payload['replyTo'] = ['email' => $replyTo];
        if (!empty($o['unsubscribe'])) {
            // Gmail/Outlook'un "Abmelden" düğmesi bu başlıkları okur. Reklam
            // postalarında zorunlu sayılıyor ve teslim edilebilirliği artırıyor.
            $payload['headers'] = [
                'List-Unsubscribe' => '<' . $o['unsubscribe'] . '>',
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ];
        }
        if (!empty($o['bcc']) && filter_var((string)$o['bcc'], FILTER_VALIDATE_EMAIL)) {
            $payload['bcc'] = [['email' => (string)$o['bcc']]];
        }

        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'accept: application/json',
                'content-type: application/json',
                'api-key: ' . $cfg['api_key'],
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw    = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status >= 200 && $status < 300) return [true, ''];

        // Brevo reddettiyse mail() ile bir şansımız daha var.
        vr_log('brevo_failed', ['status' => $status, 'to' => substr($to, 0, 3) . '***', 'resp' => substr((string)$raw, 0, 300)]);
    }

    // ---- 2) yedek: PHP mail()
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . vr_mail_encode_name($fromName) . ' <' . $from . '>',
    ];
    if ($replyTo !== '') $headers[] = 'Reply-To: ' . $replyTo;
    if (!empty($o['unsubscribe'])) {
        $headers[] = 'List-Unsubscribe: <' . $o['unsubscribe'] . '>';
        $headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
    }

    $ok = @mail($to, vr_mail_encode_name($subject), $html, implode("\r\n", $headers));
    return $ok ? [true, ''] : [false, 'gonderilemedi'];
}

/**
 * Abonelikten çıkma jetonu — e-postadan HMAC ile türetilir.
 * Listede ayrı bir alan tutmuyoruz: bağlantı her zaman aynı, süresiz geçerli
 * ve adresi bilmeyen biri üretemez.
 */
function vr_unsub_token(string $email): string
{
    require_once __DIR__ . '/vault.php';           // vr_member_secret()
    return substr(hash_hmac('sha256', 'unsub|' . strtolower(trim($email)), vr_member_secret()), 0, 32);
}

function vr_unsub_url(string $email): string
{
    return vr_abs('newsletter.php', ['unsubscribe' => vr_unsub_token($email)]);
}

/** Başlıkta UTF-8 güvenli kodlama (Almanca umlaut'lar bozulmasın). */
function vr_mail_encode_name(string $s): string
{
    return preg_match('/[\x80-\xFF]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
}

/** Basit, markalı HTML kabuk. Dış kaynak yok — resim yok, yazı tipi yok. */
function vr_mail_layout(string $title, string $bodyHtml, string $preheader = ''): string
{
    $brand = h((string)vr_config('brand'));
    $co    = vr_config('company');
    $foot  = h(trim((string)($co['legal_name'] ?? '')));

    return '<!DOCTYPE html><html><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body style="margin:0;padding:0;background:#f5f2ec;font-family:Georgia,\'Times New Roman\',serif;color:#12121a">'
        . ($preheader !== '' ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0">' . h($preheader) . '</div>' : '')
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f2ec;padding:28px 12px">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:580px;background:#fff;border:1px solid #e2ddd2">'
        . '<tr><td style="padding:26px 30px;border-bottom:1px solid #e2ddd2">'
        . '<div style="font-size:20px;letter-spacing:.24em;font-weight:700">' . $brand . '</div>'
        . '<div style="font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:#8a8578;margin-top:6px">' . h($title) . '</div>'
        . '</td></tr>'
        . '<tr><td style="padding:28px 30px;font-size:15px;line-height:1.65">' . $bodyHtml . '</td></tr>'
        . '<tr><td style="padding:20px 30px;border-top:1px solid #e2ddd2;font-size:11.5px;line-height:1.6;color:#8a8578;font-family:Helvetica,Arial,sans-serif">'
        . $foot . ' · ' . h(vr_origin()) . '<br>'
        . '<a href="' . h(vr_abs('legal/impressum.php')) . '" style="color:#8a8578">' . te('legal_imprint') . '</a> · '
        . '<a href="' . h(vr_abs('legal/agb.php')) . '" style="color:#8a8578">' . te('legal_terms') . '</a> · '
        . '<a href="' . h(vr_abs('legal/datenschutz.php')) . '" style="color:#8a8578">' . te('legal_privacy') . '</a>'
        . '</td></tr></table></td></tr></table></body></html>';
}

/** Sipariş satırlarını e-posta tablosuna dök. */
function vr_mail_lines_table(array $order): string
{
    $rows = '';
    foreach ((array)$order['lines'] as $ln) {
        $sz = ($ln['size'] ?? 'ONE') !== 'ONE' ? ' · ' . t('size') . ' ' . $ln['size'] : '';
        $rows .= '<tr>'
            . '<td style="padding:9px 0;border-bottom:1px solid #eee8dc">'
            . '<strong>' . h($ln['brand']) . '</strong> ' . h($ln['name'])
            . '<div style="font-size:12px;color:#8a8578">' . h(($ln['sku'] ?? '') . $sz)
            . (!empty($ln['vault']) ? ' · VAULT' : '') . '</div></td>'
            . '<td align="right" style="padding:9px 0;border-bottom:1px solid #eee8dc;white-space:nowrap">'
            . (int)$ln['qty'] . ' × ' . h(vr_money((int)$ln['unit_cents'])) . '</td></tr>';
    }

    $sum = static fn(string $l, string $v, bool $b = false) =>
        '<tr><td style="padding:5px 0' . ($b ? ';font-weight:700;font-size:17px' : '') . '">' . h($l) . '</td>'
        . '<td align="right" style="padding:5px 0' . ($b ? ';font-weight:700;font-size:17px' : '') . '">' . h($v) . '</td></tr>';

    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px">'
        . $rows
        . '<tr><td colspan="2" style="height:10px"></td></tr>'
        . $sum(t('subtotal'), vr_money((int)$order['subtotal_cents']))
        . $sum(t('shipping') . ' (' . $order['ship_label'] . ')', (int)$order['ship_cents'] === 0 ? t('shipping_free') : vr_money((int)$order['ship_cents']))
        // İndirim teyitte GÖRÜNMEK zorunda: §312f BGB'ye göre onay, sözleşmenin
        // gerçek içeriğini yansıtmalı — müşteri neden eksik tahsilat olduğunu
        // faturasında görebilmeli.
        . ((int)($order['discount_cents'] ?? 0) > 0
            ? $sum(t('vou_line', ['code' => (string)($order['voucher_code'] ?? '')]),
                   '−' . vr_money((int)$order['discount_cents']))
            : '')
        . $sum(t('total'), vr_money((int)$order['total_cents']), true)
        . ((int)($order['vat_cents'] ?? 0) > 0
            ? '<tr><td colspan="2" style="font-size:12px;color:#8a8578;padding-top:4px">'
              . te('vat_of_which', ['amount' => vr_money((int)$order['vat_cents'])]) . '</td></tr>'
            : '')
        . '</table>';
}

/** Alıcıya sipariş onayı (§312f BGB: metin biçiminde teyit zorunlu). */
function vr_mail_order_buyer(array $order): void
{
    $email = (string)($order['customer']['email'] ?? '');
    if ($email === '') return;

    $orderUrl = vr_abs('order.php', ['id' => $order['id'], 'token' => $order['token']]);
    $days     = (int)vr_config('return_days', 30);

    $body = '<p>' . te('order_thanks') . '</p>'
        . '<p style="font-size:13px;color:#5c584e">' . te('order_number') . ': <strong>' . h($order['number']) . '</strong></p>'
        . vr_mail_lines_table($order)
        . '<p style="margin-top:22px"><a href="' . h($orderUrl) . '" style="display:inline-block;background:#12121a;color:#f5f2ec;text-decoration:none;padding:12px 22px;letter-spacing:.1em;font-size:13px;font-family:Helvetica,Arial,sans-serif">'
        . te('order_status') . '</a></p>'
        . '<p style="font-size:13px;color:#5c584e;margin-top:20px">' . te('order_next_1') . ' ' . te('order_next_2') . '</p>'
        . '<p style="font-size:13px;color:#5c584e">' . te('order_next_3', ['days' => $days]) . '</p>';

    if (!empty($order['has_private'])) {
        $body .= '<p style="font-size:12.5px;color:#5c584e;border-left:3px solid #c9a24d;padding-left:12px">'
              . te('checkout_private_ack') . '</p>';
    }

    // Widerrufsbelehrung siparişte metin olarak GİTMEK zorunda — sadece linkle
    // yetinmek §312f Abs. 2 BGB açısından yeterli sayılmıyor.
    $body .= '<hr style="border:none;border-top:1px solid #e2ddd2;margin:24px 0">'
        . '<div style="font-size:12px;color:#5c584e;line-height:1.6">'
        . '<strong>' . te('legal_withdrawal') . '</strong><br>'
        . h(vr_withdrawal_text_plain())
        . '<br><br><a href="' . h(vr_abs('legal/widerruf.php')) . '" style="color:#5c584e">' . h(vr_abs('legal/widerruf.php')) . '</a>'
        . '</div>';

    vr_mail_send($email, t('order_number') . ' ' . $order['number'] . ' · ' . vr_config('brand'),
        vr_mail_layout(t('order_summary'), $body, t('order_thanks')),
        ['bcc' => (string)vr_config('mail_bcc_ops', '')]
    );
}

/** Her satıcıya kendi satırlarını bildir. */
function vr_mail_order_sellers(array $order): void
{
    require_once __DIR__ . '/accounts.php';

    foreach ((array)$order['by_seller'] as $uid => $row) {
        if ($uid === 'vestra') continue;
        $s = vr_seller((string)$uid);
        if ($s === null || ($s['email'] ?? '') === '') continue;

        $mine = array_values(array_filter((array)$order['lines'], fn($l) => (string)($l['seller_uid'] ?? '') === (string)$uid));
        if (!$mine) continue;

        $rows = '';
        foreach ($mine as $ln) {
            $sz = ($ln['size'] ?? 'ONE') !== 'ONE' ? ' · ' . t('size') . ' ' . $ln['size'] : '';
            $rows .= '<tr><td style="padding:8px 0;border-bottom:1px solid #eee8dc">'
                . '<strong>' . h($ln['brand']) . '</strong> ' . h($ln['name'])
                . '<div style="font-size:12px;color:#8a8578">' . h(($ln['sku'] ?? '') . $sz) . '</div></td>'
                . '<td align="right" style="padding:8px 0;border-bottom:1px solid #eee8dc">'
                . (int)$ln['qty'] . ' × ' . h(vr_money((int)$ln['unit_cents'])) . '</td></tr>';
        }

        $addr = (array)($order['customer']['address'] ?? []);
        $addrHtml = '';
        foreach (['name', 'line1', 'line2', 'postal_code', 'city', 'country'] as $k) {
            $v = trim((string)($addr[$k] ?? ''));
            if ($v !== '') $addrHtml .= h($v) . '<br>';
        }

        $body = '<p>' . h(vr_seller_display($s)) . ',</p>'
            . '<p>' . h($order['number']) . ' — ' . count($mine) . ' Position(en).</p>'
            . '<table role="presentation" width="100%" style="font-size:14px">' . $rows . '</table>'
            . '<table role="presentation" width="100%" style="font-size:14px;margin-top:14px">'
            . '<tr><td>' . te('subtotal') . '</td><td align="right">' . h(vr_money((int)$row['gross'])) . '</td></tr>'
            . '<tr><td>' . te('our_fee') . '</td><td align="right">−' . h(vr_money((int)$row['commission'])) . '</td></tr>'
            . '<tr><td style="font-weight:700">' . te('your_payout') . '</td><td align="right" style="font-weight:700">' . h(vr_money((int)$row['net'])) . '</td></tr>'
            . '</table>'
            . ($addrHtml !== '' ? '<p style="font-size:13px;margin-top:18px"><strong>Lieferadresse</strong><br>' . $addrHtml . '</p>' : '')
            . '<p style="font-size:13px;color:#5c584e">' . h(t('sell_payout_b')) . '</p>';

        vr_mail_send((string)$s['email'], $order['number'] . ' · ' . vr_config('brand'),
            vr_mail_layout(t('seller_orders'), $body));
    }
}

/** Newsletter onay maili (double opt-in). */
function vr_mail_newsletter_confirm(string $email, string $token): void
{
    $url = vr_abs('newsletter.php', ['confirm' => $token]);
    $body = '<p>' . te('news_title') . '</p>'
        . '<p style="font-size:14px;color:#5c584e">' . te('news_sub', ['hours' => (int)vr_config('vault_member_head_start_hours', 12)]) . '</p>'
        . '<p style="margin-top:20px"><a href="' . h($url) . '" style="display:inline-block;background:#12121a;color:#f5f2ec;text-decoration:none;padding:12px 22px;letter-spacing:.1em;font-size:13px;font-family:Helvetica,Arial,sans-serif">'
        . te('news_cta') . '</a></p>'
        . '<p style="font-size:12px;color:#8a8578">' . h($url) . '</p>';

    $body .= '<p style="font-size:11.5px;color:#8a8578;margin-top:22px">'
          . 'Sie haben diese E-Mail angefordert. Nicht gewollt? Dann ignorieren Sie sie einfach — '
          . 'ohne Bestätigung wird nichts eingetragen. '
          . '<a href="' . h(vr_unsub_url($email)) . '" style="color:#8a8578">Abmelden</a></p>';

    vr_mail_send($email, vr_config('brand') . ' — ' . t('news_title'),
        vr_mail_layout(t('news_title'), $body, t('news_sub', ['hours' => 12])),
        ['unsubscribe' => vr_unsub_url($email)]);
}

/**
 * Widerrufsbelehrung'un düz metin özeti — sipariş onay mailine gömülür.
 * Tam metin legal/widerruf.php'de.
 */
function vr_withdrawal_text_plain(): string
{
    $days = (int)vr_config('withdrawal_days', 14);
    $co   = vr_config('company');
    $name = trim((string)($co['legal_name'] ?? ''));
    $mail = trim((string)($co['email'] ?? ''));

    return "Sie haben das Recht, binnen {$days} Tagen ohne Angabe von Gründen diesen Vertrag zu widerrufen. "
        . "Die Widerrufsfrist beträgt {$days} Tage ab dem Tag, an dem Sie oder ein von Ihnen benannter Dritter, "
        . "der nicht der Beförderer ist, die Waren in Besitz genommen haben bzw. hat. "
        . "Um Ihr Widerrufsrecht auszuüben, müssen Sie uns ({$name}, {$mail}) mittels einer eindeutigen Erklärung "
        . "über Ihren Entschluss, diesen Vertrag zu widerrufen, informieren. "
        . "Bei Käufen von Privatverkäufern besteht kein gesetzliches Widerrufsrecht.";
}

/**
 * Hesap e-postası doğrulama bağlantısı.
 * Jetonun HAM hâli yalnızca burada, yani yalnızca posta kutusuna gider;
 * dosyada sadece sha256'sı duruyor (bkz. inc/customers.php).
 */
function vr_mail_customer_verify(string $email, string $rawToken): void
{
    $url = vr_abs('account/verify.php', ['e' => $email, 't' => $rawToken]);
    $body = '<p>' . te('acc_verify_mail_lead') . '</p>'
        . '<p style="margin-top:20px"><a href="' . h($url) . '" style="display:inline-block;background:#12121a;color:#f5f2ec;text-decoration:none;padding:12px 22px;letter-spacing:.1em;font-size:13px;font-family:Helvetica,Arial,sans-serif">'
        . te('acc_verify_cta') . '</a></p>'
        . '<p style="font-size:12px;color:#8a8578">' . h($url) . '</p>'
        . '<p style="font-size:11.5px;color:#8a8578;margin-top:22px">' . te('acc_verify_mail_note') . '</p>';

    vr_mail_send($email, vr_config('brand') . ' — ' . t('acc_verify_title'),
        vr_mail_layout(t('acc_verify_title'), $body, t('acc_verify_mail_lead')));
}

/** Şifre sıfırlama bağlantısı. Jeton tek kullanımlık ve 1 saat geçerli. */
function vr_mail_customer_reset(string $email, string $rawToken): void
{
    $url = vr_abs('account/reset.php', ['e' => $email, 't' => $rawToken]);
    $body = '<p>' . te('acc_reset_mail_lead') . '</p>'
        . '<p style="margin-top:20px"><a href="' . h($url) . '" style="display:inline-block;background:#12121a;color:#f5f2ec;text-decoration:none;padding:12px 22px;letter-spacing:.1em;font-size:13px;font-family:Helvetica,Arial,sans-serif">'
        . te('acc_reset_cta') . '</a></p>'
        . '<p style="font-size:12px;color:#8a8578">' . h($url) . '</p>'
        . '<p style="font-size:11.5px;color:#8a8578;margin-top:22px">' . te('acc_reset_mail_note') . '</p>';

    vr_mail_send($email, vr_config('brand') . ' — ' . t('acc_reset_title'),
        vr_mail_layout(t('acc_reset_title'), $body, t('acc_reset_mail_lead')));
}

/**
 * Willkommensgutschein. Wird verschickt, sobald die Adresse bestätigt ist —
 * vorher wäre der Code an eine Adresse gegangen, die dem Empfänger womöglich
 * gar nicht gehört.
 */
function vr_mail_welcome_voucher(string $email, string $code): void
{
    $pct = rtrim(rtrim(number_format((int)vr_config('welcome_discount_bps', 500) / 100, 2, ',', '.'), '0'), ',');
    $days = (int)vr_config('welcome_valid_days', 90);

    $body = '<p>' . te('vou_welcome_body', ['pct' => $pct]) . '</p>'
        . '<p style="margin:22px 0;text-align:center">'
        . '<span style="display:inline-block;border:1px solid #c9a24d;padding:14px 26px;'
        . 'font-family:Helvetica,Arial,sans-serif;font-size:19px;letter-spacing:.22em;color:#12121a">'
        . h($code) . '</span></p>'
        . '<p style="text-align:center"><a href="' . h(vr_abs('shop.php')) . '" '
        . 'style="display:inline-block;background:#12121a;color:#f5f2ec;text-decoration:none;'
        . 'padding:12px 22px;letter-spacing:.1em;font-size:13px;font-family:Helvetica,Arial,sans-serif">'
        . te('nav_shop') . '</a></p>'
        . '<p style="font-size:11.5px;color:#8a8578;margin-top:22px">'
        . te('vou_welcome_terms', ['days' => $days]) . '</p>';

    vr_mail_send($email, vr_config('brand') . ' — ' . t('vou_welcome_title'),
        vr_mail_layout(t('vou_welcome_title'), $body, t('vou_welcome_body', ['pct' => $pct])));
}
