<?php
/**
 * VESTRA — escrow otomatik serbest birakma süpürücüsü (cron / CLI).
 *
 * Operator kurali (31 Agustos 2026): satici teslimati isaretledikten sonra
 * 2 IS GUNU icinde alici ne onay verir ne sorun bildirirse, tutulan odeme
 * saticiya otomatik serbest birakilir.
 *
 * Sayac teslimatta (delivered_at) baslar, kargolamada degil: kendi teslimat
 * sartlarimiz AB icinde 7-14 is gunu diyor; kargolamadan 2 gun sonra
 * birakmak parayi mal yoldayken vermek olurdu. Alici teslimat aninda sureyi
 * bildiren e-postayi zaten almistir (seller.php deliver_order).
 *
 * Sorun bildirilen siparis nasil durur? Operator paneldeki Refund/Release ile
 * karar verir; süpürücü YALNIZCA 'held' kayitlara dokundugu icin verilen her
 * karar otomatigi o siparis icin kendiliginden devre disi birakir. Serbest
 * birakilamayan kayit (ör. kart takasi bitmemis bakiye) 'held' kalir ve
 * yarinki kosuda yeniden denenir — sessizce dusmez.
 *
 * Zamanlama: SUNUCU crontab'i, her gun 06:25 UTC (deploy-vestra.yml her push'ta
 * idempotent kurar; satirlar VESTRA-SWEEP etiketli). GitHub Actions DEGIL:
 * schedule yalnizca varsayilan daldaki workflow'lar icin calisir, bu daldaki
 * pool-sweep.yml hic kayda gecmemisti.
 *
 * Usage:  php cron_escrow_release.php [--dry-run]
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/inc/products.php';
require_once __DIR__ . '/inc/escrow.php';

$dry = in_array('--dry-run', $argv ?? [], true);

echo "[escrow-release] baslangic " . date('c') . ($dry ? ' (KURU KOSU — hicbir sey yazilmaz)' : '') . "\n";
$r = escrow_auto_release_sweep($dry);
foreach ($r['lines'] as $l) echo $l . "\n";
printf("[escrow-release] bitti: held=%d, suresi-dolan=%d, serbest=%d, hata=%d\n",
    $r['checked'], $r['due'], $r['released'], $r['failed']);
/* Hata varsa is KIRMIZI bitsin: yesil gorunen basarisiz para isi, bu depoda
   iki kez yasanmis bir tuzak (kupon isi + uye kipi). */
exit($r['failed'] > 0 ? 1 : 0);
