<?php
/* CANLI FOTOGRAFLAR (19 Eki 2026, operator: "SH9623 ayni zamanda bu urune live foto, ornek orijinal fotolar olarak
 * ekle"). Ilanin 'live_photos' alani urun sayfasinda katalog fotograflarindan AYRI, kendi basligiyla ve renk
 * gruplariyla cizilir; tiklaninca ayni buyuk goruntuleyicide (rozetli) acilir; uye olmayana yalniz "N canli fotograf,
 * gormek icin giris yapin" karti gorunur; /uploads/ disindaki adres kabul edilmez; alani olmayan ilan aynen kalir. */
error_reporting(E_ALL & ~E_DEPRECATED);
$root = realpath(__DIR__.'/../vestra');
$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { if ($c) { $ok++; echo "  ok   $n\n"; } else { $bad++; echo "  HATA $n\n"; } };

require_once $root.'/inc/products.php';
echo "== 1. vestra_live_photos ==\n";
$lp = vestra_live_photos(['live_photos' => [
  ['src' => '/uploads/live/x/01-a.jpg', 'thumb' => '/uploads/live/x/t-01-a.jpg', 'group' => 'Bleu roi · 4XA', 'note' => 'FR 5 / US L'],
  ['src' => 'https://evil.example/x.jpg', 'group' => 'X'],
  ['src' => '/uploads/../data/accounts.json'],
  ['src' => '/uploads/live/x/02-b.jpg', 'group' => 'Bleu marine · 166'],
  'bozuk',
]]);
$t('gecerli iki kayit kalir, dis adres / .. / bozuk kayit duser', count($lp) === 2 && $lp[0]['thumb'] === '/uploads/live/x/t-01-a.jpg');
$t('kucuk kopya yoksa tam boy kullanilir', $lp[1]['thumb'] === '/uploads/live/x/02-b.jpg');
$t('alani olmayan ilan bos doner', vestra_live_photos(['images' => ['/uploads/a.jpg']]) === []);

echo "== 2. Urun sayfasi ==\n";
$sb = sys_get_temp_dir().'/vestra_live_'.bin2hex(random_bytes(4));
@mkdir($sb, 0777, true);
exec('cp -r '.escapeshellarg($root).' '.escapeshellarg($sb.'/vestra').' 2>&1');
exec('rm -rf '.escapeshellarg($sb.'/vestra/data')); @mkdir($sb.'/vestra/data', 0777, true);
file_put_contents($sb.'/vestra/inc/config.php', "<?php return ['admin_pass'=>'x','mail_enabled'=>false];\n");
$live = [];
foreach ([['01-bleu-roi.jpg', 'Bleu roi · 4XA', 'FR 5 / US L'], ['02-bleu-roi.jpg', 'Bleu roi · 4XA', 'FR 5 / US L'], ['03-bleu-marine.jpg', 'Bleu marine · 166', 'FR 4 / US M']] as [$f, $g, $n])
  $live[] = ['src' => '/uploads/live/lac-fleece-hoodie/'.$f, 'thumb' => '/uploads/live/lac-fleece-hoodie/t-'.$f, 'group' => $g, 'note' => $n];
$base = ['seller_uid' => 'sellX', 'sku' => 'SH9623', 'brand' => 'Lacoste', 'cat' => 'Sweatshirts', 'mode' => 'fixed', 'list' => 49.9, 'moq' => 40, 'unit' => 'pc',
  'tiers' => [['min' => 40, 'price' => 49.9], ['min' => 50, 'price' => 45.0]], 'colors' => ['Navy', 'Light Blue'], 'sizes' => 'S-XXL', 'status' => 'approved'];
file_put_contents($sb.'/vestra/data/listings.json', json_encode([
  ['id' => 'lac-fleece-hoodie', 'name' => 'Fleece Hoodie', 'images' => ['/uploads/cat/a.jpg', '/uploads/cat/b.jpg'], 'live_photos' => $live] + $base,
  ['id' => 'plain-hoodie', 'name' => 'Plain Hoodie', 'images' => ['/uploads/cat/p.jpg']] + $base,
]));
file_put_contents($sb.'/vestra/data/accounts.json', json_encode([['id' => 'buy0000probe0001', 'email' => 'b@probe.example', 'type' => 'buyer', 'status' => 'active', 'kyb_status' => 'approved', 'name' => 'B', 'company' => 'B Srl']]));
$hdr = "<?php error_reporting(E_ALL); ini_set('display_errors','1'); \$_SERVER['REQUEST_METHOD']='GET'; \$_SERVER['REMOTE_ADDR']='127.0.0.1'; \$_SERVER['HTTP_HOST']='localhost'; session_start();\n";
$page = function (string $id, bool $member, string $lang) use ($sb, $hdr): string {
  $who = $member ? "\$_SESSION['member']=true; \$_SESSION['uid']='buy0000probe0001'; " : '';
  file_put_contents($sb.'/p.php', $hdr.$who."\$_GET=['id'=>'$id','lang'=>'$lang']; \$_SERVER['REQUEST_URI']='/product?id=$id&lang=$lang'; chdir(__DIR__.'/vestra'); ob_start(); include __DIR__.'/vestra/product.php'; echo ob_get_clean();");
  return (string)shell_exec('cd '.escapeshellarg($sb).' && php p.php 2>&1');
};
$h = $page('lac-fleece-hoodie', true, 'it');
$t('uye (it): bolum basligi Italyanca + alt baslik', str_contains($h, 'id="live-photos"') && str_contains($h, 'Foto reali') && str_contains($h, 'Campioni originali'));
$t('uye: renk gruplari ve beden notu', str_contains($h, 'Bleu roi · 4XA') && str_contains($h, 'FR 5 / US L') && str_contains($h, 'Bleu marine · 166'));
$t('uye: izgara kucuk kopyalari, slayt numaralari katalogdan SONRA (2,3,4)', str_contains($h, 'src="/uploads/live/lac-fleece-hoodie/t-01-bleu-roi.jpg"')
   && str_contains($h, 'data-gi="2" onclick="galSet(2);galFocus()"') && str_contains($h, 'data-gi="4" onclick="galSet(4);galFocus()"'));
$t('uye: galeri dizisi katalog + canli tam boy, galCat=2', str_contains($h, 'galCat=2') && str_contains($h, '"\/uploads\/cat\/b.jpg","\/uploads\/live\/lac-fleece-hoodie\/01-bleu-roi.jpg"'));
$t('uye: katalog seridinde canli fotograf YOK (2 katalog + marka karti = 3 dugme)', substr_count($h, '<button class="gal-thumb') === 3);
$t('uye: rozet var, ilk slayt katalog oldugu icin gizli', (bool)preg_match('/class="lp-badge" id="galLive" style="display:none"/', $h) && str_contains($h, 'Foto reale'));
$t('uye: PHP uyarisi yok', !preg_match('/\b(Warning|Fatal error|Deprecated|Notice)\b:/', $h));
$g = $page('lac-fleece-hoodie', false, 'en');
$t('misafir: canli fotograf adresi sayfada YOK', !str_contains($g, '/uploads/live/'));
$t('misafir: kilitli kart sayi ve giris baglantisiyla', str_contains($g, '3 live photos of the original samples') && str_contains($g, 'Sign in to view product photos') && str_contains($g, 'href="/login?back='));
$p0 = $page('plain-hoodie', true, 'en');
$t('alansiz ilan: bolum yok, rozet yok, serit eskisi gibi (1 foto + kart)', !str_contains($p0, 'id="live-photos"') && !str_contains($p0, 'galLive" ') && substr_count($p0, '<button class="gal-thumb') === 2 && str_contains($p0, 'galCat=1'));
if (getenv('LIVE_TEST_DUMP')) { file_put_contents(getenv('LIVE_TEST_DUMP'), $h); file_put_contents(getenv('LIVE_TEST_DUMP').'.guest.html', $g); }
exec('rm -rf '.escapeshellarg($sb));
echo "\nTOPLAM: {$ok} gecti, {$bad} kaldi\n";
exit($bad === 0 ? 0 : 1);
