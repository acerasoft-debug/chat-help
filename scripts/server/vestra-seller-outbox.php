<?php
/* Sunucuda (CLI) çalışır — .github/workflows/seller-outbox.yml tarafından. Satıcının KENDİ posta
   sunucusundan gönderim kuyruğu (vestra/inc/seller_outbox.php):
     php vestra-seller-outbox.php take <N>   → kuyruktaki en çok N mektup + satıcı SMTP girişleri, JSON (stdout).
                                              Çıktı YALNIZ SSH borusuyla koşucunun belleğine gider; loga yazılmaz.
     php vestra-seller-outbox.php stamp      → stdin'den {"results":[{id,status,reason,message_id}]} — şifre yok.
   STDOUT'ta yalnız JSON; uyarılar STDERR'e. */
ini_set('display_errors', 'stderr');
error_reporting(E_ALL);
$home = getenv('HOME');
require $home.'/public_html/inc/products.php';
if (!is_readable($home.'/public_html/inc/seller_outbox.php')) { echo json_encode(['items' => [], 'creds' => new stdClass, 'note' => 'eski sunucu kodu']), "\n"; exit(0); }
require_once $home.'/public_html/inc/seller_outbox.php';

$cmd = (string)($argv[1] ?? '');
if ($cmd === 'take') {
  $r = vestra_seller_outbox_take(max(1, min(200, (int)($argv[2] ?? 40))));
  echo json_encode(['items' => $r['items'], 'creds' => $r['creds'] ?: new stdClass], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), "\n";
  exit(0);
}
if ($cmd === 'stamp') {
  $in = json_decode((string)stream_get_contents(STDIN), true);
  $c = vestra_seller_outbox_stamp((array)($in['results'] ?? []));
  echo json_encode($c), "\n";
  exit(0);
}
fwrite(STDERR, "kullanim: take <N> | stamp\n");
exit(2);
