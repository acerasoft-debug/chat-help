<?php
/* Admin ▸ Messages'taki konusma BASLIK satiri: "urun" olmayan bir thread'i urun gibi
 * gostermemeli.
 *
 * NEDEN VAR (operator, 17 Eyl 2026: "bu urunu offer vermis musteri ancak urun yok neden
 * silindi ve neydi"): satir `listing_id`'yi cozemeyince ham id'yi basip onu
 * /product?id=<id> adresine BAGLIYORDU. Ama `listing_id` her zaman bir ilan degil --
 * request-offer.php thread'i TALEP ref'iyle aciyor
 * (`vestra_msg_post_system($buyer,$seller,$ref,...)`). Sonuc: talep panosundan gelen bir
 * teklif satirda `RFBF89` diye duruyor ve var olamayacak bir urun sayfasina baglaniyor.
 * Operator bunu "urun silinmis" diye okudu; hicbir sey silinmemisti, ortada urun hic
 * yoktu. Olculdu (diag-live find_ref=RFBF89): kayit requests.csv + request_offers.csv'de
 * DURUYOR.
 *
 * NEDEN KUM HAVUZUNDA GERCEKTEN CIZDIRILIYOR: kaynak taramasi bu isi olcemez -- olculmesi
 * gereken sey `$requests` degiskeninin o satirda KAPSAMDA olup olmadigi ve uretilen
 * HTML. Bu depoda `php -l` gecen iki calisma-zamani hatasi ayni gun yasandi
 * (csrf_field / vestra_order_status).
 *
 * IKI YON DE tutuluyor: gercek ilan HALA urun sayfasina baglanmali -- tek yon yazilsaydi
 * butun baglantilari soken bir hata yesil kalirdi. */

$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? "  ok   " : "  FAIL ").$n."\n"; };

$root = dirname(__DIR__).'/vestra';
$sand = sys_get_temp_dir().'/vestra_mtl_'.bin2hex(random_bytes(4));
mkdir($sand.'/data', 0777, true);

/* --- kum havuzu verisi ------------------------------------------------------ */
$BUYER  = 'b0000000000000aa';
$SELLER = 's0000000000000bb';

file_put_contents($sand.'/data/accounts.json', json_encode([
  ['id'=>$BUYER,  'type'=>'buyer',  'email'=>'buyer@example.test',  'company'=>'Mfitel Test', 'name'=>'Mfitel Test', 'status'=>'active'],
  ['id'=>$SELLER, 'type'=>'seller', 'email'=>'seller@example.test', 'company'=>'GARAGE TEST', 'name'=>'GARAGE TEST', 'status'=>'active'],
]));

/* Gercek, onayli bir ilan: kontrol grubu. */
file_put_contents($sand.'/data/listings.json', json_encode([
  ['id'=>'lst-real-1','status'=>'approved','brand'=>'Lacoste','name'=>'Pique Polo',
   'seller_uid'=>$SELLER,'price'=>39,'tiers'=>[['min'=>10,'price'=>39]]],
]));

/* Talep panosu kaydi -- thread'in listing_id'si BU ref. */
file_put_contents($sand.'/data/requests.csv',
  "timestamp,ref,title,cat,qty,target,country,email,notes\n".
  "2026-09-17T10:19:00+00:00,RQTEST1,\"Lacoste nike ralph Laurent tommy\",\"Hoodies & Sweatshirts\",20,15,France,buyer@example.test,\n");

$mk = fn(string $lid) => [
  'id'=>'th-'.$lid, 'buyer_uid'=>$BUYER, 'seller_uid'=>$SELLER, 'listing_id'=>$lid,
  'last_at'=>'2026-09-17T18:07:00+00:00', 'read'=>[],
  'messages'=>[['at'=>'2026-09-17T18:07:00+00:00','from'=>$SELLER,'text'=>'hello']],
];
file_put_contents($sand.'/data/messages.json', json_encode([
  $mk('RQTEST1'),      // talep ref'i    -> talep basligi + Requests sekmesi
  $mk('lst-real-1'),   // gercek ilan    -> urun sayfasi (kontrol grubu)
  $mk('ZZGHOST9'),     // hicbir sey     -> duz metin, baglanti YOK
]));

/* --- admin.php'yi AYRI SURECTE cizdir ---------------------------------------- */
$runner = $sand.'/_render.php';
file_put_contents($runner, <<<'PHP'
<?php
define('VESTRA_DATA_DIR', getenv('MTL_DIR'));
define('VESTRA_ACCOUNTS', getenv('MTL_DIR').'/accounts.json');
define('VESTRA_MESSAGES', getenv('MTL_DIR').'/messages.json');
define('VESTRA_BLOCKED_MESSAGES', getenv('MTL_DIR').'/blocked_messages.json');
session_start();
$_SESSION['vadmin'] = true; $_SESSION['vadmin_csrf'] = 'tok';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/admin?tab=messages';
$_GET = ['tab' => 'messages'];
include getenv('MTL_ADMIN');
PHP);

$html = (string)shell_exec(
  'MTL_DIR='.escapeshellarg($sand.'/data').
  ' MTL_ADMIN='.escapeshellarg($root.'/admin.php').
  ' php '.escapeshellarg($runner).' 2>&1');

echo "== 0. sayfa gercekten cizildi mi ==\n";
$t('Messages sekmesi basildi', str_contains($html, 'Read conversation'));
/* Kapsam hatasi (`$requests` tanimsiz) TAM OLARAK burada gorunur: php -l gecer, uyari basar. */
$t('PHP uyarisi/fatal yok', stripos($html,'Warning')===false && stripos($html,'Fatal')===false
   && stripos($html,'Undefined variable')===false);

/* SATIRIN KENDISINE bak, sayfanin tamamina degil. Ilk yazimda
   `str_contains($html,'href="/admin?tab=requests"')` yazmistim ve sabotaj altinda YESIL
   kaldi: o adres SOL MENUDE de var, yani iddia satiri degil navigasyonu olcuyordu --
   mango/zara dersinin testin kendi icindeki hali (bu depoda `class="msgtick` oneki
   `msgtickdefs`'i yakalayinca bir kez daha yasandi). */
$rowOf = function (string $needle) use ($html): string {
    $at = strpos($html, $needle);
    if ($at === false) return '';
    $open = strrpos(substr($html, 0, $at), '<div class="ahint">');
    if ($open === false) return '';
    $end = strpos($html, '</div>', $open);
    return substr($html, $open, ($end === false ? strlen($html) : $end) - $open);
};
$reqRow   = $rowOf('RQTEST1');
$prodRow  = $rowOf('lst-real-1');
$ghostRow = $rowOf('ZZGHOST9');

echo "\n== 1. TALEP ref'i: urun gibi gosterilmiyor ==\n";
$t('talep satiri bulundu', $reqRow !== '');
$t('talebin KENDI basligi basiliyor', str_contains($reqRow, 'Lacoste nike ralph Laurent tommy'));
$t('SATIRIN KENDISI Requests sekmesine baglaniyor', str_contains($reqRow, 'href="/admin?tab=requests"'));
$t('ref hala gorunuyor (tanitici kayboldu degil)', str_contains($reqRow, 'RQTEST1'));
/* Asil kusur: olmayan bir urun sayfasina baglanti. */
$t('satirda /product?id= baglantisi YOK', !str_contains($reqRow, '/product?id='));

echo "\n== 2. TERS YON: gercek ilan hala urun sayfasina baglaniyor ==\n";
/* Tek yon yazilsaydi, butun urun baglantilarini soken bir hata yesil kalirdi. */
$t('gercek ilan satiri bulundu', $prodRow !== '');
$t('gercek ilan urun sayfasina baglaniyor', str_contains($prodRow, '/product?id=lst-real-1'));
$t('gercek ilanin ADI basiliyor', str_contains($prodRow, 'Lacoste Pique Polo'));

echo "\n== 3. cozulemeyen id: DUZ METIN, olu baglanti yok ==\n";
/* `vestra_find()` yalniz APPROVED + satici askida degil olanlari goruyor, yani buraya
   gercekten silinmis bir ilan da, gizli duran bir ilan da dusuyor. Ikisinde de "silindi"
   demek yalan olurdu; dogru olan tek sey ham id'yi baglantisiz basmak. */
$t('hayalet satir bulundu', $ghostRow !== '');
$t('ham id basiliyor', str_contains($ghostRow, 'ZZGHOST9'));
$t('satirda HIC baglanti yok', !str_contains($ghostRow, '<a '));

echo "\n".($bad ? "KIRMIZI: {$bad}\n" : '')."msg_thread_label_test: {$ok} iddia gecti".($bad ? ", {$bad} DUSTU" : '')."\n";
exit($bad ? 1 : 0);
