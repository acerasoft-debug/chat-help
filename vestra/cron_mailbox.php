<?php
/**
 * VESTRA — support@vestrasales.com'dan kampanya gönderimi, SUNUCUNUN KENDİ posta servisiyle (cron / CLI).
 *
 * Operatör, 9 Eki 2026: "hata vermeyi ve sorunsuz göndermeyi gerçekleştir … kendi serverımızdan" ve
 * "e-mailler support@vestrasales.com'dan gidecek". Ölçüm (9 Eki 01:35 UTC): sunucunun mail()'i Exim →
 * GoDaddy barındırma aktarıcısı üzerinden Gmail'e GELEN KUTUSU olarak ulaştı, SPF=pass, DMARC=pass.
 * Şifre, GitHub, Brevo kotası gerekmez.
 *
 * Her 10 dakikada bir (sunucu crontab'ı, deploy-vestra.yml VESTRA-SWEEP satırı): Admin ▸ Müşteriler ▸ 📮
 * kartından verilen isteği alır (inc/mailbox.php), gönderir, sonucu panele yazar. İstek yoksa hiçbir şey
 * yapmaz. Kilit dosyası: önceki gönderim sürerken ikincisi başlamaz (8–11 sn arayla 500 mektup ~80 dk).
 *
 * Usage:  php cron_mailbox.php [--dry-run]
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
@set_time_limit(0);
require_once __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/leads.php';
require_once __DIR__.'/inc/notify.php';
require_once __DIR__.'/inc/finder.php';
require_once __DIR__.'/inc/mailbox.php';

$dry = in_array('--dry-run', $argv, true);
/* Müşteri aramasını da sunucu başlatır (GitHub anahtarı kayıtlıysa): GitHub'ın kendi zamanlaması saatlerce gecikiyor.
   Gönderim kilidinden ÖNCE — uzun bir gönderim sürerken de arama zamanında başlasın. */
if (!$dry && ($tick = vestra_finder_server_tick()) !== '') echo '[finder '.date('Y-m-d H:i').'] '.$tick."\n";

/* Satıcıların sunucu yolu (inc/seller_outbox.php, via=server): kendi kilidi — admin kampanyası saatlerce sürse de
   satıcı mektupları her 10 dk'da gider (tur başına en çok 40, 8-15 sn ara). */
require_once __DIR__.'/inc/seller_outbox.php';
if (!$dry) {
  $slk = @fopen(vestra_data_dir().'/seller_outbox_cron.lock', 'c');
  if ($slk && flock($slk, LOCK_EX | LOCK_NB)) {
    $ss = vestra_seller_outbox_run_server(['log' => static function (string $m): void { echo $m, "\n"; }]);
    if ($ss['sent'] || $ss['failed'] || $ss['skipped']) echo '[seller-outbox '.date('Y-m-d H:i').'] gonderildi '.$ss['sent'].' · hata '.$ss['failed'].' · atlandi '.$ss['skipped']."\n";
    flock($slk, LOCK_UN); fclose($slk);
  }
}

$lock = @fopen(vestra_data_dir().'/mailbox_cron.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { exit(0); }   // önceki gönderim sürüyor

if ($dry) {
  $open = array_values(array_filter(vestra_mailbox_runs(), 'vestra_mailbox_is_open'));
  echo '[mailbox] kuru kosu · bekleyen istek: '.count($open).' · bugun gonderilen '.vestra_mailbox_sent_today().'/'.vestra_mailbox_daily_cap()
     .' · gonderime hazir '.count(vestra_finder_send_targets(1000, 'all')).' · mail(): '.(function_exists('mail') ? 'var' : 'YOK')."\n";
  exit(0);
}

/* Otomatik modda bekleyen yeni butiklere kendiliğinden istek aç (açık istek yokken, bugünkü hak varken). */
if (($cu = vestra_mailbox_auto_catchup()) !== '') echo '[mailbox '.date('Y-m-d H:i').'] otomatik: '.$cu."\n";
/* Gece takip turu (13 Eki 2026): Avrupa saatiyle 23:45'ten sonra bugünkü tavandan artan pay, en az mektup almış butiklere. */
if (($nt = vestra_mailbox_nightly()) !== '') echo '[mailbox '.date('Y-m-d H:i').'] gece takip: '.$nt."\n";
$req = vestra_mailbox_take();
if (!$req) exit(0);
$stamp = date('Y-m-d H:i');
echo "[mailbox {$stamp}] istek {$req['id']} · {$req['mode']} · ".($req['mode'] === 'test' ? vestra_mailbox_mask((string)$req['test_to']) : (int)$req['limit'].' musteri')."\n";
$sum = vestra_mailbox_run($req, ['log' => static function (string $m): void { echo '  ', $m, "\n"; }]);
vestra_mailbox_finish((string)$req['id'], $sum);
/* Operatörün telefonuna özet (12 Eki 2026): deneme gönderimi değilse ve bir şey olduysa. Fren devredeyse bir kez "durduruldu". */
require_once __DIR__.'/inc/push.php';
try {
  if ($req['mode'] !== 'test' && ((int)$sum['sent'] + (int)$sum['bounced'] + (int)$sum['failed'] + (int)($sum['deferred'] ?? 0)) > 0) {
    vestra_push_admin('admin_mailbox', ['sent' => (int)$sum['sent'], 'bounced' => (int)$sum['bounced'], 'failed' => (int)$sum['failed'], 'deferred' => (int)($sum['deferred'] ?? 0)]);
  }
  $bs = vestra_mailbox_bounce_stats();
  $cfg = vestra_mailbox_cfg();
  $pushedAt = (string)($cfg['stop_pushed_at'] ?? ''); $resetAt = (string)($cfg['bounce_reset_at'] ?? '');
  if ($bs['stop'] && ($pushedAt === '' || $pushedAt < $resetAt)) {   // her fren için bir kez; "devam et"ten sonra yeniden
    $GLOBALS['vestra_admin_push_done'] = false;
    vestra_push_admin('admin_stop', ['days' => VESTRA_MAILBOX_BOUNCE_DAYS, 'rate' => (int)round($bs['rate'] * 100), 'bounced' => $bs['bounced'], 'sent' => $bs['sent']]);
    $cfg['stop_pushed_at'] = date('c');
    $f = vestra_mailbox_file('mailbox.json');
    file_put_contents($f, json_encode($cfg, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), LOCK_EX); @chmod($f, 0600);
  }
} catch (\Throwable $e) { echo '[mailbox] bildirim: '.$e->getMessage()."\n"; }
echo "[mailbox] bitti · gonderildi {$sum['sent']} · gonderilmedi (olu alan adi) {$sum['bounced']} · hata {$sum['failed']}".($sum['note'] !== '' ? ' · '.$sum['note'] : '')."\n";
