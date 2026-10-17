<?php
/**
 * IndexNow anahtar doğrulama dosyası.
 * .htaccess, kökteki <anahtar>.txt isteğini buraya yönlendirir. Yalnızca
 * config'de (seo.indexnow_key) tanımlı anahtarla eşleşirse anahtarı düz metin
 * döner; aksi halde 404. Böylece anahtar dosyasını elle koymaya gerek kalmaz.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/config.php';

$key = trim((string)(vr_config('seo')['indexnow_key'] ?? ''));
$req = (string)($_GET['k'] ?? '');

if ($key !== '' && hash_equals($key, $req)) {
    header('Content-Type: text/plain; charset=utf-8');
    echo $key;
    exit;
}
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "Not found\n";
