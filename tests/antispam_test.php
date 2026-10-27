<?php
/* SPAM ÖNLEMLERİ + PARTİ 400 (27 Eki 2026, operatör: "tek gönderişte 400 email gönder … spama düşmemesi için önlemler al").
 * Ölçülen: tek parti en çok 400; günlük tavan kademeli (27 Eki 800 → 31 Eki 2000, sonra 2000); Avrupa 01:00–07:00 arası yeni
 * parti başlamaz ve otomatik istek açılmaz (test isteği başlar); geri dönme freni %5; tek tıkla çıkış başlığı duruyor.
 * Kum havuzu: VESTRA_DATA_DIR geçici dizin. */
$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; } };
$root = dirname(__DIR__).'/vestra';
$sand = sys_get_temp_dir().'/vestra-spam-'.bin2hex(random_bytes(4)); mkdir($sand.'/data', 0777, true);
define('VESTRA_DATA_DIR', $sand.'/data'); define('VESTRA_FINDER_ON', true);
require $root.'/inc/products.php'; require_once $root.'/inc/finder.php'; require_once $root.'/inc/mailbox.php';
file_put_contents($sand.'/data/mailbox.json', json_encode(['warmup' => false, 'daily_cap' => 2000, 'daily_cap_set_at' => '2026-10-27T03:00:00+00:00']));

echo "== kademeli tavan ==\n";
$d = fn(string $s) => strtotime($s.' 12:00:00 UTC');
$t('27 Eki 800 · 28 Eki 1100 · 29 Eki 1400 · 30 Eki 1700 · 31 Eki 2000', vestra_mailbox_ramp($d('2026-10-27')) === 800 && vestra_mailbox_ramp($d('2026-10-28')) === 1100
   && vestra_mailbox_ramp($d('2026-10-29')) === 1400 && vestra_mailbox_ramp($d('2026-10-30')) === 1700 && vestra_mailbox_ramp($d('2026-10-31')) === 2000);
$t('sonra tavan panel değeri (2000) — rampa onu aşamaz', vestra_mailbox_ramp($d('2026-11-05')) > 2000 && vestra_mailbox_daily_cap() === 2000);
$t('bugünkü tavan = min(panel, rampa)', vestra_mailbox_cap_today() === min(2000, vestra_mailbox_ramp()));

echo "\n== gece sessizliği (Avrupa 01:00–07:00) ==\n";
$b = fn(string $hm) => (new DateTimeImmutable('2026-10-28 '.$hm, new DateTimeZone('Europe/Berlin')))->getTimestamp();
$t('00:59 değil · 01:00 ve 06:59 sessiz · 07:00 değil', !vestra_mailbox_quiet($b('00:59')) && vestra_mailbox_quiet($b('01:00')) && vestra_mailbox_quiet($b('06:59')) && !vestra_mailbox_quiet($b('07:00')));
vestra_mailbox_runs_save([['id' => 'MBx1', 'mode' => 'send', 'limit' => 50, 'campaign' => 'edit', 'pool' => 'all', 'ids' => [], 'test_to' => '', 'status' => 'requested', 'requested_at' => date('c')]]);
$t('gece: bekleyen gönderim BAŞLAMAZ, istek yerinde kalır', vestra_mailbox_take($b('03:00')) === null && (vestra_mailbox_runs()[0]['status'] ?? '') === 'requested');
$tk = vestra_mailbox_take($b('08:00'));
$t('sabah: başlar', ($tk['id'] ?? '') === 'MBx1' && (vestra_mailbox_runs()[0]['status'] ?? '') === 'running');
vestra_mailbox_runs_save([['id' => 'MBt1', 'mode' => 'test', 'limit' => 1, 'campaign' => 'edit', 'pool' => 'web', 'ids' => [], 'test_to' => 'ops@example.com', 'status' => 'requested', 'requested_at' => date('c')]]);
$t('gece: operatörün TEST isteği yine başlar', (vestra_mailbox_take($b('03:00'))['id'] ?? '') === 'MBt1');
vestra_mailbox_runs_save([]);
$t('gece: otomatik istek açılmaz', vestra_mailbox_auto_catchup($b('03:00')) === '' && vestra_mailbox_runs() === []);

echo "\n== parti 400 ==\n";
$src = (string)file_get_contents($root.'/inc/mailbox.php');
$t('istek sınırı min(istenen, bugünkü kalan, 400)', VESTRA_MAILBOX_BATCH_MAX === 400 && str_contains($src, 'min($limit, $left, VESTRA_MAILBOX_BATCH_MAX)'));
$t('otomatik istek varsayılanı 400', VESTRA_MAILBOX_AUTO_DEFAULTS['auto_limit'] === 400);

echo "\n== diğer önlemler ==\n";
$t('geri dönme freni %5', VESTRA_MAILBOX_BOUNCE_MAX === 0.05);
$t('tek tıkla çıkış başlığı (Gmail / Yahoo toplu gönderici kuralı)', str_contains($src, 'List-Unsubscribe-Post: List-Unsubscribe=One-Click'));
$t('alıcı alan adları karıştırılır, mektuplar arası 8–11 sn', function_exists('vestra_mailbox_interleave') && VESTRA_MAILBOX_PAUSE_MIN === 8 && VESTRA_MAILBOX_PAUSE_MAX === 11);

echo "\n{$ok} ok, {$fail} hata\n"; exit($fail ? 1 : 0);
