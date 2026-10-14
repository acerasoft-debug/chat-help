<?php
/**
 * Sunucunun kendi zamanlayıcısı (inc/ops_tick.php) — 14 Eki 2026, operatör: "Brevo'da her zaman 100 hak kalsın, 23.45'te
 * kontrol edilsin ve hak kaldıysa devam gönderilsin … halen e-posta hataları geliyor". GitHub sahte: hangi iş akışının
 * hangi girdiyle, hangi saatte, günde kaç kez başlatıldığı ölçülür.
 */
$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? "  ok   " : "  FAIL ").$n."\n"; };
$root = dirname(__DIR__).'/vestra';
$sand = sys_get_temp_dir().'/vestra_ops_'.bin2hex(random_bytes(4)); @mkdir($sand.'/data', 0775, true);
define('VESTRA_DATA_DIR', $sand.'/data');
require $root.'/inc/products.php';
require_once $root.'/inc/ops_tick.php';

$calls = [];
$gh = function (string $m, string $path, ?array $body) use (&$calls) {
  if ($m === 'GET') return [200, ['default_branch' => 'main'], ''];
  $calls[] = [basename(dirname($path)), $body['inputs'] ?? [], $body['ref'] ?? ''];
  return [204, null, ''];
};
$tz = new DateTimeZone('Europe/Berlin');
$at = fn(string $d) => (new DateTimeImmutable($d, $tz))->getTimestamp();
$wfs = function () use (&$calls) { return array_map(fn($c) => $c[0].(isset($c[1]['sweep']) ? '/sweep='.$c[1]['sweep'] : (isset($c[1]['mode']) ? '/'.$c[1]['mode'] : '')), $calls); };   // arrow fn $calls'ı kopyalardı

$l = vestra_ops_tick($at('2026-10-15 08:00'), $gh);
$t('08:00: yalnız saatlik geri dönme taraması (Brevo saati değil)', $wfs() === ['mailbox-send.yml/bounces'] && count($l) === 1);
$calls = [];
vestra_ops_tick($at('2026-10-15 08:30'), $gh);
$t('yarım saat sonra tarama tekrarlanmaz', $calls === []);
vestra_ops_tick($at('2026-10-15 09:15'), $gh);
$t('09:15: Brevo sabah partisi (50, gece taraması değil) + saatlik tarama', $wfs() === ['brevo-daily.yml/sweep=false', 'mailbox-send.yml/bounces'] && $calls[0][1]['count'] === '50' && $calls[0][2] === 'main');
$calls = [];
vestra_ops_tick($at('2026-10-15 09:35'), $gh);
$t('aynı gün sabah partisi ikinci kez başlatılmaz', $calls === []);
vestra_ops_tick($at('2026-10-15 15:20'), $gh);
$t('15:20: öğleden sonra partisi', in_array('brevo-daily.yml/sweep=false', $wfs(), true));
$calls = [];
vestra_ops_tick($at('2026-10-15 23:40'), $gh);
$t('23:40: gece taraması henüz yok', !in_array('brevo-daily.yml/sweep=true', $wfs(), true));
$calls = [];
vestra_ops_tick($at('2026-10-15 23:47'), $gh);
$t('23:47: Brevo gece taraması (sweep=true)', in_array('brevo-daily.yml/sweep=true', $wfs(), true));
$calls = [];
vestra_ops_tick($at('2026-10-15 23:57'), $gh);
$t('gece taraması ikinci kez yok', !in_array('brevo-daily.yml/sweep=true', $wfs(), true));
$calls = [];
vestra_ops_tick($at('2026-10-16 00:05'), $gh);
$t('gece yarısından sonra (14 dk pencere geçti) dünkü tarama yeniden başlamaz', !in_array('brevo-daily.yml/sweep=true', $wfs(), true));
$calls = [];
vestra_ops_tick($at('2026-10-16 09:20'), $gh);
$t('ertesi gün sabah partisi yeniden', in_array('brevo-daily.yml/sweep=false', $wfs(), true));
$bad404 = fn($m, $p, $b) => $m === 'GET' ? [200, ['default_branch' => 'main'], ''] : [404, null, ''];
$st0 = vestra_ops_state(); unset($st0['brevo_pm']); vestra_ops_save($st0);
$l2 = vestra_ops_tick($at('2026-10-16 15:20'), $bad404);
$t('GitHub reddederse slot işaretlenmez (10 dk sonra yeniden denenir)', !isset(vestra_ops_state()['brevo_pm']) && str_contains(implode(' ', $l2), 'HTTP 404'));
$t('anahtar yoksa sahte GitHub olmadan hiçbir şey yapmaz', vestra_ops_tick($at('2026-10-16 15:25')) === []);
$cm = (string)file_get_contents($root.'/cron_mailbox.php');
$t('cron: sunucu zamanlayıcısı kilitten önce, bulucudan sonra', strpos($cm, 'vestra_ops_tick()') > strpos($cm, 'vestra_finder_server_tick()') && strpos($cm, 'vestra_ops_tick()') < strpos($cm, 'mailbox_cron.lock'));
exec('rm -rf '.escapeshellarg($sand));
echo "\nTOPLAM: {$ok} gecti, {$bad} kaldi\n";
exit($bad ? 1 : 0);
