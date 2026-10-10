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
 * yapmaz. Kilit dosyası: önceki gönderim sürerken ikincisi başlamaz (25–55 sn arayla 50 mektup ~35 dk).
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

$lock = @fopen(vestra_data_dir().'/mailbox_cron.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { exit(0); }   // önceki gönderim sürüyor

if ($dry) {
  $open = array_values(array_filter(vestra_mailbox_runs(), 'vestra_mailbox_is_open'));
  echo '[mailbox] kuru kosu · bekleyen istek: '.count($open).' · bugun gonderilen '.vestra_mailbox_sent_today().'/'.vestra_mailbox_daily_cap()
     .' · gonderime hazir '.count(vestra_finder_send_targets(1000, 'all')).' · mail(): '.(function_exists('mail') ? 'var' : 'YOK')."\n";
  exit(0);
}

$req = vestra_mailbox_take();
if (!$req) exit(0);
$stamp = date('Y-m-d H:i');
echo "[mailbox {$stamp}] istek {$req['id']} · {$req['mode']} · ".($req['mode'] === 'test' ? vestra_mailbox_mask((string)$req['test_to']) : (int)$req['limit'].' musteri')."\n";
$sum = vestra_mailbox_run($req, ['log' => static function (string $m): void { echo '  ', $m, "\n"; }]);
vestra_mailbox_finish((string)$req['id'], $sum);
echo "[mailbox] bitti · gonderildi {$sum['sent']} · gonderilmedi (olu alan adi) {$sum['bounced']} · hata {$sum['failed']}".($sum['note'] !== '' ? ' · '.$sum['note'] : '')."\n";
