<?php
/* set-product + SURESI DOLMUS AKTION (22 Eki 2026, Lacoste SH9623 / SH9608 / SH9626).
 *
 * Bitmis bir aktion'un blogu kayitta kaliyordu ve vestra_aktion_apply() ilan her okunusunda blogun 'prev' fiyatlarini,
 * listesini ve pinned=false'unu geri yaziyordu: set-product'in yazdigi yeni kademeler dosyaya giriyor, sitede hic
 * gorunmuyordu; SAYFADA satiri ham kayittan hesaplandigi icin de "-%30" deyip yaniltiyordu.
 * Iddialar: (1) fiyat/sabitleme yazan satir suresi dolmus blogu siler ve sayfanin okudugu hal (aktion_apply sonrasi)
 * yeni degerleri tasir; (2) SAYFADA satiri sayfanin okudugu halden hesaplanir; (3) SUREN aktion varken fiyat yazmak
 * reddedilir ve hicbir sey kaydedilmez; (4) fiyatla ilgisi olmayan satir (ad) blogu silmez.
 */
require_once __DIR__.'/../vestra/inc/products.php';
$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; } };
$root = dirname(__DIR__); $listings = $root.'/vestra/data/listings.json';
if (file_exists($listings)) { fwrite(STDERR, "ATLANAMAZ: {$listings} zaten var -- yerel dosyanizi ezmemek icin duruyor. Once tasiyin.\n"); exit(1); }
@mkdir(dirname($listings), 0777, true);
$home = sys_get_temp_dir().'/vestra_aktion_test_'.getmypid(); @mkdir($home, 0777, true); @symlink($root.'/vestra', $home.'/public_html');
$past = gmdate('c', time() - 5 * 86400); $future = gmdate('c', time() + 2 * 86400);
$seed = function (string $until) use ($listings) {
  file_put_contents($listings, json_encode([
    ['id'=>'lac-h','brand'=>'Lacoste','cat'=>'Hoodies','name'=>'Fleece Hoodie','mode'=>'sale','moq'=>50,'list'=>49.9,'status'=>'approved',
     'tiers'=>[['min'=>50,'price'=>45],['min'=>100,'price'=>39.9]],
     'aktion'=>['price'=>39.9,'min'=>100,'until'=>$until,'started'=>gmdate('c', time() - 9 * 86400),
       'prev'=>['tiers'=>[['min'=>40,'price'=>49.9],['min'=>100,'price'=>42]],'mode'=>'sale','list'=>49.9,'pinned'=>false]]],
  ], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
};
$run = function (array $fixes, bool $apply) use ($home, $root): array {
  $cmd = 'HOME='.escapeshellarg($home).' P_DRY='.escapeshellarg($apply ? 'false' : 'true').' P_JSON='.escapeshellarg(base64_encode(json_encode($fixes)))
       .' php '.escapeshellarg($root.'/scripts/set_product.php').' 2>&1';
  $out = []; $rc = 0; exec($cmd, $out, $rc); return [$rc, implode("\n", $out)];
};
$read = function () use ($listings) { $d = json_decode((string)file_get_contents($listings), true); return $d[0]; };
$fix = [['match'=>'lac-h','expect'=>1,'moq'=>50,'tiers'=>[['min'=>50,'price'=>38],['min'=>100,'price'=>36],['min'=>200,'price'=>35]],'pinned'=>true]];

echo "== 1. suresi dolmus aktion: blog silinir, sayfa yeni fiyati okur ==\n";
$seed($past);
[$rc0, $o0] = $run($fix, false);
$t('kuru kosu blogun kalkacagini soyler', $rc0 === 0 && str_contains($o0, 'suresi dolmus aktion kaydi KALDIRILDI'));
$t('kuru kosu SAYFADA: sayfanin gorecegi -%30', str_contains($o0, 'rozet -%30'));
$t('kuru kosu dosyaya dokunmaz', isset($read()['aktion']));
[$rc1, $o1] = $run($fix, true);
$r1 = $read(); $live = vestra_aktion_apply($r1);
$t('uygulama basarili', $rc1 === 0 && str_contains($o1, 'KAYDEDILDI'));
$t('aktion blogu kayitta YOK', !isset($r1['aktion']));
$t('sayfanin okudugu kademeler yeni (50/38, 100/36, 200/35)', ($live['tiers'] ?? null) === [['min'=>50,'price'=>38],['min'=>100,'price'=>36],['min'=>200,'price'=>35]]);
$t('sayfanin okudugu sabitleme acik', !empty($live['pinned']));
$t('sayfanin rozeti -%30', vestra_discount($live) === 30);

echo "\n== 2. SUREN aktion: fiyat yazmak reddedilir ==\n";
$seed($future);
[$rc2, $o2] = $run($fix, true);
$t('ret (cikis kodu != 0)', $rc2 !== 0 && str_contains($o2, 'SUREN bir aktion var'));
$t('kayit degismedi (blog ve eski kademeler yerinde)', isset($read()['aktion']) && $read()['tiers'][0]['price'] == 45);

echo "\n== 3. fiyatla ilgisi olmayan satir blogu silmez ==\n";
$seed($past);
$run([['match'=>'lac-h','expect'=>1,'name'=>'Fleece Hoodie SH9623']], true);
$t('ad degisti, aktion blogu duruyor', isset($read()['aktion']) && $read()['name'] === 'Fleece Hoodie SH9623');

foreach (array_merge([$listings], glob($listings.'.bak-*') ?: []) as $f) @unlink($f);
@unlink($home.'/public_html'); @rmdir($home);
printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
