<?php
/* TOPLU SMTP AKTARICISI (31 Eki 2026, operatör: "bu e-posta gönderme işine son noktayı koy, günde 2 bin e-posta gitsin").
 * Ölçülen: ayar dosyası okunur/eksikse yok sayılır; SMTP konuşması doğru sırada (EHLO, STARTTLS, AUTH LOGIN, MAIL FROM,
 * RCPT TO, DATA, QUIT), şifre base64 ile gider, mektup başlıkları (List-Unsubscribe, Message-ID, To, Subject) ve nokta
 * doldurma DATA'da; aktarıcı reddederse sunucunun mail()'ine düşülür (no_fallback ile düşülmez); aktarıcı varken
 * mail() hiç çağrılmaz; Return-Path = serviste doğrulanmış 'from'. Kum havuzu: VESTRA_DATA_DIR geçici dizin. */
$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; } };
$root = dirname(__DIR__).'/vestra';
$sand = sys_get_temp_dir().'/vestra-bulk-'.bin2hex(random_bytes(4)); mkdir($sand.'/data', 0777, true);
define('VESTRA_DATA_DIR', $sand.'/data');
require $root.'/inc/products.php'; require_once $root.'/inc/finder.php'; require_once $root.'/inc/mailbox.php';

echo "== ayar ==\n";
$t('dosya yok → aktarıcı yok', vestra_mailbox_bulk_cfg() === null);
file_put_contents($sand.'/data/bulk_smtp.json', json_encode(['host' => 'smtp-relay.example', 'port' => 587, 'user' => 'u@example', 'pass' => 'secret', 'from' => 'support@vestrasales.com']));
$c = vestra_mailbox_bulk_cfg();
$t('dosya var → host/port/tls starttls/from', $c && $c['host'] === 'smtp-relay.example' && $c['port'] === 587 && $c['tls'] === 'starttls' && $c['from'] === 'support@vestrasales.com' && !$c['no_fallback']);
file_put_contents($sand.'/data/bulk_smtp.json', json_encode(['host' => 'h', 'port' => 465, 'user' => 'u', 'pass' => 'p', 'disabled' => true]));
$t('disabled → yok', vestra_mailbox_bulk_cfg() === null);
file_put_contents($sand.'/data/bulk_smtp.json', json_encode(['host' => 'h', 'port' => 465, 'user' => 'u', 'pass' => 'p', 'from' => 'bozuk']));
$c = vestra_mailbox_bulk_cfg();
$t('465 → ssl; geçersiz from → boş (support@ kullanılır)', $c['tls'] === 'ssl' && $c['from'] === '');
file_put_contents($sand.'/data/bulk_smtp.json', json_encode(['host' => 'h', 'pass' => '']));
$t('şifresiz → yok', vestra_mailbox_bulk_cfg() === null);
@unlink($sand.'/data/bulk_smtp.json');

echo "\n== SMTP konuşması (sahte aktarıcı) ==\n";
/* Sahte sunucu: yazılan komutları kaydeder, senaryodaki yanıtları verir. */
$mk = function (array $script, array &$log) {
  return function (string $host, int $port) use ($script, &$log) {
    $i = 0; $greet = true;
    return ['read' => function () use (&$i, &$greet, $script): string { if ($greet) { $greet = false; return "220 relay ready\r\n"; } return ($script[$i++] ?? "250 ok\r\n"); },
            'write' => function (string $x) use (&$log): void { $log[] = rtrim($x, "\r\n"); },
            'tls' => fn(): bool => true, 'close' => function () use (&$log): void { $log[] = '<close>'; }];
  };
};
$cfg = ['host' => 'h', 'port' => 587, 'user' => 'login@example', 'pass' => 'xkeysib-secret', 'from' => 'support@vestrasales.com', 'tls' => 'starttls', 'no_fallback' => false, 'label' => 'h'];
$log = [];
[$okD, $why] = vestra_mailbox_smtp_deliver($cfg, 'support@vestrasales.com', 'shop@boutique.example', "From: VESTRA <support@vestrasales.com>\r\nTo: shop@boutique.example\r\n\r\n.leading dot\r\nhello", $mk(["250-h\r\n250 STARTTLS\r\n", "220 go\r\n", "250 h\r\n", "334 VXNlcm5hbWU6\r\n", "334 UGFzc3dvcmQ6\r\n", "235 ok\r\n", "250 ok\r\n", "250 ok\r\n", "354 go\r\n", "250 queued\r\n", "221 bye\r\n"], $log));
$t('teslim ok', $okD && $why === '');
$t('sıra: EHLO, STARTTLS, EHLO, AUTH LOGIN, user, pass, MAIL FROM, RCPT TO, DATA, mektup, QUIT', array_slice($log, 0, 9) === ['EHLO vestrasales.com', 'STARTTLS', 'EHLO vestrasales.com', 'AUTH LOGIN', base64_encode('login@example'), base64_encode('xkeysib-secret'), 'MAIL FROM:<support@vestrasales.com>', 'RCPT TO:<shop@boutique.example>', 'DATA'] && $log[10] === 'QUIT' && end($log) === '<close>');
$t('mektup DATA\'da, nokta doldurma, sonda tek nokta', str_contains($log[9], "To: shop@boutique.example") && str_contains($log[9], "\r\n..leading dot") && str_ends_with($log[9], "\r\n."));
$t('şifre düz metin olarak hiçbir komutta geçmez', !in_array('xkeysib-secret', $log, true));
$log = [];
[$okD, $why] = vestra_mailbox_smtp_deliver($cfg, 'support@vestrasales.com', 'x@y.example', "a", $mk(["250 h\r\n", "220 go\r\n", "250 h\r\n", "334\r\n", "334\r\n", "535 bad creds\r\n"], $log));
$t('kimlik reddi → ok değil, neden kullanıcı/şifre, QUIT + kapat', !$okD && str_contains($why, 'kimlik') && in_array('QUIT', $log, true) && end($log) === '<close>');
$log = [];
[$okD, $why] = vestra_mailbox_smtp_deliver($cfg, 'support@vestrasales.com', 'x@y.example', "a", $mk(["250 h\r\n", "220 go\r\n", "250 h\r\n", "334\r\n", "334\r\n", "235 ok\r\n", "250 ok\r\n", "550 no such user\r\n"], $log));
$t('alıcı reddi → neden 550', !$okD && str_contains($why, '550'));
$cfgSsl = $cfg; $cfgSsl['tls'] = 'ssl'; $log = [];
[$okD] = vestra_mailbox_smtp_deliver($cfgSsl, 'support@vestrasales.com', 'x@y.example', "a", $mk(["250 h\r\n", "334\r\n", "334\r\n", "235 ok\r\n", "250 ok\r\n", "250 ok\r\n", "354\r\n", "250 ok\r\n"], $log));
$t('ssl (465): STARTTLS yok', $okD && !in_array('STARTTLS', $log, true));

echo "\n== send_local: aktarıcı önce, sunucu yedek ==\n";
$mailCalls = 0; $fakeMail = function () use (&$mailCalls) { $mailCalls++; return true; };
$it = ['email' => 'shop@boutique.example', 'subject' => 'Trade markdowns', 'text' => 'hello', 'html' => '<p>hello</p>', 'listUnsub' => 'https://vestrasales.com/lead-unsubscribe?token=abc'];
$seen = null; $fakeSmtpOk = function (array $b, string $from, string $to, string $data) use (&$seen) { $seen = compact('b', 'from', 'to', 'data'); return [true, '']; };
[$r, $mid, $why] = vestra_mailbox_send_local($it, 'VESTRA', $fakeMail, ['bulk' => $cfg, 'smtp' => $fakeSmtpOk, 'dkim_key' => '']);
$t('aktarıcı kabul etti → ok, Message-ID var, mail() HİÇ çağrılmadı', $r && str_starts_with($mid, '<') && $mailCalls === 0);
$t('mektupta List-Unsubscribe (tek tık), Message-ID, To, Subject, iki parça; Return-Path = from', str_contains($seen['data'], 'List-Unsubscribe-Post: List-Unsubscribe=One-Click') && str_contains($seen['data'], 'Message-ID: '.$mid)
   && str_contains($seen['data'], "\r\nTo: shop@boutique.example\r\n") && str_contains($seen['data'], "\r\nSubject: ") && str_contains($seen['data'], 'text/html') && $seen['from'] === 'support@vestrasales.com' && $seen['to'] === 'shop@boutique.example');
$fakeSmtpBad = fn() => [false, 'smtp kimlik reddedildi'];
[$r, $mid, $why] = vestra_mailbox_send_local($it, 'VESTRA', $fakeMail, ['bulk' => $cfg, 'smtp' => $fakeSmtpBad, 'dkim_key' => '']);
$t('aktarıcı reddetti → sunucunun mail()\'ine düştü, ok', $r && $mailCalls === 1 && $why === '');
$cfgNf = $cfg; $cfgNf['no_fallback'] = true; $mailCalls = 0;
[$r, $mid, $why] = vestra_mailbox_send_local($it, 'VESTRA', $fakeMail, ['bulk' => $cfgNf, 'smtp' => $fakeSmtpBad, 'dkim_key' => '']);
$t('no_fallback: düşmez, neden aktarıcının', !$r && $mailCalls === 0 && str_contains($why, 'toplu smtp'));
$mailCalls = 0;
[$r] = vestra_mailbox_send_local($it, 'VESTRA', $fakeMail, ['bulk' => false, 'dkim_key' => '']);
$t('aktarıcı yok → eskisi gibi mail()', $r && $mailCalls === 1);
$fakeMailNo = fn() => false; $mailCalls = 0;
[$r, , $why] = vestra_mailbox_send_local($it, 'VESTRA', $fakeMailNo, ['bulk' => $cfg, 'smtp' => $fakeSmtpBad, 'dkim_key' => '']);
$t('ikisi de reddetti → neden ikisini de söyler', !$r && str_contains($why, 'posta servisi kabul etmedi') && str_contains($why, 'toplu smtp de'));

echo "\n{$ok} ok, {$fail} hata\n"; exit($fail ? 1 : 0);
