<?php
/**
 * ChatHelp — ver.php  (surum ucnoktasi)
 *  index.php'nin gercek son-degisiklik zamanini (filemtime) dondurur.
 *  Onbellek KESIN kapali; edge/CDN de gecmesin diye no-store + rastgele
 *  query (istemci tarafinda ?_=timestamp) beklenir.
 *  apply-cache-buster.php bunu kullanir: yuklu HTML'in surumu != canli surum
 *  ise sayfa bir kez taze surume yenilenir.
 */
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Surrogate-Control: no-store');
header('CDN-Cache-Control: no-store');
header('Cloudflare-CDN-Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');
clearstatcache();
$f = __DIR__ . '/index.php';
$mt = @filemtime($f);
echo $mt ? (string)$mt : '0';
