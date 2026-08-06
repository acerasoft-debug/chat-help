<?php
/**
 * Müşteri hesabı akış testi — CLI
 * ===============================
 *   php tools/test-account.php
 *
 * Geçici bir veri dizininde çalışır ve posta göndermez; canlı veriye
 * dokunmaz. En kritik iddia şu: DOĞRULANMAMIŞ bir hesap sipariş geçmişi
 * göremez ve hiçbir hesap BAŞKASININ siparişini açamaz.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Nur über die Kommandozeile.\n";
    exit(1);
}

putenv('VR_NO_MAIL=1');
$tmp = sys_get_temp_dir() . '/vr-acc-' . getmypid();
@mkdir($tmp, 0700, true);
putenv('VR_DATA_DIR=' . $tmp);
$_SERVER['REQUEST_METHOD'] = 'GET';
require __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/customers.php';

$n = 0; $bad = 0;
function ok(string $what, bool $cond, string $note = '') {
    global $n, $bad; $n++;
    if (!$cond) { $bad++; echo "  [FAIL] $what $note\n"; }
    else echo "  [ok  ] $what" . ($note ? "  $note" : '') . "\n";
}

echo "== kayıt ==\n";
[$k, $c, $raw] = vr_customer_create(['email'=>'Kunde@Example.com','password'=>'sehr-geheim-123','name'=>'Anna B']);
ok('hesap açıldı', $k === true);
ok('e-posta küçük harfe indi', ($c['email'] ?? '') === 'kunde@example.com');
ok('şifre düz metin DEĞİL', !str_contains(json_encode($c), 'sehr-geheim-123'));
ok('doğrulama jetonu dosyada hash olarak', ($c['verify_hash'] ?? '') === hash('sha256', $raw) && $raw !== $c['verify_hash']);
ok('yeni hesap doğrulanmamış', empty($c['verified']));

[$k2,$e2,] = vr_customer_create(['email'=>'kunde@example.com','password'=>'baska-sifre-9999']);
ok('aynı e-posta ikinci kez açılamaz', $k2 === false && $e2 === 'email_taken');
[$k3,$e3,] = vr_customer_create(['email'=>'yok','password'=>'sehr-geheim-123']);
ok('geçersiz e-posta reddedildi', $k3 === false && $e3 === 'email_invalid');
[$k4,$e4,] = vr_customer_create(['email'=>'x@y.de','password'=>'kisa']);
ok('kısa şifre reddedildi', $k4 === false && $e4 === 'pw_too_short');

echo "\n== giriş ==\n";
[$li] = vr_customer_login('kunde@example.com','yanlis-sifre-123');
ok('yanlış şifre giremez', $li === false);
[$li2] = vr_customer_login('KUNDE@example.com','sehr-geheim-123');
ok('doğru şifre girdi (büyük/küçük harf farketmez)', $li2 === true);
ok('oturumdaki müşteri doğru', (vr_current_customer()['id'] ?? '') === $c['id']);

echo "\n== sipariş gizliliği ==\n";
// Bu e-postaya ait ödenmiş bir sipariş kaydı
vr_store_append('retail-orders.json', [
  'id'=>'o-test1','number'=>'MX-1','status'=>'paid','created_at'=>time(),
  'total_cents'=>12900,'lines'=>[],'customer'=>['email'=>'kunde@example.com','name'=>'Anna B','address'=>[]],
]);
// BAŞKASINA ait sipariş
vr_store_append('retail-orders.json', [
  'id'=>'o-test2','number'=>'MX-2','status'=>'paid','created_at'=>time(),
  'total_cents'=>9900,'lines'=>[],'customer'=>['email'=>'fremd@example.com','name'=>'X','address'=>[]],
]);
$me = vr_current_customer();
ok('DOĞRULANMADAN sipariş geçmişi BOŞ', count(vr_customer_orders($me)) === 0);
ok('doğrulanmadan tek sipariş de açılmaz', vr_customer_order($me,'o-test1') === null);

[$v] = vr_customer_verify('kunde@example.com', 'yanlis-jeton');
ok('yanlış jetonla doğrulanmaz', $v === false);
[$v2] = vr_customer_verify('kunde@example.com', $raw);
ok('doğru jetonla doğrulandı', $v2 === true);
$me = vr_current_customer();
ok('doğrulandıktan sonra kendi siparişi görünüyor', count(vr_customer_orders($me)) === 1);
ok('BAŞKASININ siparişi görünmüyor', vr_customer_order($me,'o-test2') === null);
[$v3] = vr_customer_verify('kunde@example.com', $raw);
ok('jeton tek kullanımlık (ikinci kez zaten doğrulanmış sayılır)', $v3 === true);
ok('jeton dosyadan silindi', (vr_customer($c['id'])['verify_hash'] ?? 'x') === '');

echo "\n== şifre değiştirme ==\n";
[$sp] = vr_customer_set_password($c['id'], 'yeni-sifre-4444', 'yanlis');
ok('eski şifre yanlışsa değişmez', $sp === false);
[$sp2] = vr_customer_set_password($c['id'], 'yeni-sifre-4444', 'sehr-geheim-123');
ok('doğru eski şifreyle değişti', $sp2 === true);
[$li3] = vr_customer_login('kunde@example.com','yeni-sifre-4444');
ok('yeni şifreyle giriliyor', $li3 === true);

echo "\n== şifre sıfırlama ==\n";
[, $noAcct] = vr_customer_reset_request('hicyok@example.com');
ok('olmayan hesap için jeton üretilmez ama hata da verilmez', $noAcct === '');
[, $rraw] = vr_customer_reset_request('kunde@example.com');
ok('sıfırlama jetonu üretildi', $rraw !== '');
ok('sıfırlama jetonu dosyada hash', (vr_customer($c['id'])['reset_hash'] ?? '') === hash('sha256',$rraw));
[$ra] = vr_customer_reset_apply('kunde@example.com','yanlis',   'baska-sifre-7777');
ok('yanlış jetonla sıfırlanmaz', $ra === false);
[$ra2] = vr_customer_reset_apply('kunde@example.com',$rraw,'baska-sifre-7777');
ok('doğru jetonla sıfırlandı', $ra2 === true);
[$ra3] = vr_customer_reset_apply('kunde@example.com',$rraw,'ucuncu-sifre-888');
ok('sıfırlama jetonu tek kullanımlık', $ra3 === false);
[$li4] = vr_customer_login('kunde@example.com','baska-sifre-7777');
ok('sıfırlanmış şifreyle giriliyor', $li4 === true);

echo "\n== hesap silme (DSGVO Art. 17) ==\n";
$before = count(vr_orders());
ok('silmeden önce sipariş var', $before === 2);
ok('hesap silindi', vr_customer_delete($c['id']) === true);
ok('hesap kaydı gitti', vr_customer_by_email('kunde@example.com') === null);
ok('SİPARİŞLER duruyor (§147 AO / §257 HGB)', count(vr_orders()) === $before);
ok('silince oturum da kapandı', vr_current_customer() === null);

echo "\n== ülkeden dil ==\n";
require_once __DIR__ . '/../inc/geo.php';
ok('DE → de', vr_country_lang('DE') === 'de');
ok('AZ → az', vr_country_lang('AZ') === 'az');
ok('TR → eşleşme yok', vr_country_lang('TR') === '');
ok('bilinmeyen ülke bölgesi world', vr_country_zone('JP') === 'world');
ok('DE bölgesi de', vr_country_zone('DE') === 'de');

array_map('unlink', glob($tmp . '/*') ?: []);
@rmdir($tmp);
echo "\n" . str_repeat('=', 60) . "\n";
printf("%d test, %d başarısız\n", $n, $bad);
exit($bad ? 1 : 0);
