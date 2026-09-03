<?php
/* Mesajlasmada satici KIMLIGI (operator karari, 3 Eyl 2026): "her urun icin
 * seller ardindan ident no ya da sku numarasi koy, magaza ismi yapma".
 *
 * Alici bir konusmada karsisindakini urunun SKU'suyla gorur, dukkan adiyla degil.
 * Tuzaklar bu testte tutuluyor:
 *   - satici tarafi ASLA firma adi dondurmez (ne panelde ne de arama filtresinde),
 *   - alici tarafi degismez -- tedarikci kime sattigini bilmeye devam eder,
 *   - VESTRA Support kendi adiyla kalir (sentetik hesap, gizlenecek dukkan yok),
 *   - ilansiz konusmada bile etiket SABIT kalir (thread basina degismez),
 *   - ident, uid'in kendisini ya da dukkan adinin bir parcasini sizdirmaz.
 */
$src = file_get_contents(__DIR__.'/../vestra/inc/messages.php');
if (!defined('VESTRA_SUPPORT_UID')) define('VESTRA_SUPPORT_UID', 'vestra-support');
/* vestra_msg_label tek satirlik: govde deseni ($fn ... ^}) onu yakalarsa bir SONRAKI
   fonksiyonun sonuna kadar yutar ve ayni fonksiyon iki kez tanimlanir. */
if (!preg_match('/^function vestra_msg_label\(.*$/m', $src, $m)) { echo "HATA: vestra_msg_label bulunamadi\n"; exit(1); }
eval($m[0]);
foreach (['vestra_msg_seller_ident','vestra_msg_counterpart_label'] as $fn) {
    if (!preg_match('/^function '.preg_quote($fn,'/').'\(.*?^}/ms', $src, $m)) { echo "HATA: $fn messages.php'de bulunamadi\n"; exit(1); }
    eval($m[0]);
}

/* Kod tarafinda duran bagimliliklar: cevirici, hesap defteri, ilan cozucu. */
function t(string $s): string { return $s; }
$LISTINGS = [
    'lac-l1212'          => ['id'=>'lac-l1212', 'sku'=>'LAC-L1212', 'name'=>'Polo L1212'],
    'blm-ah0eg000bc27'   => ['id'=>'blm-ah0eg000bc27', 'sku'=>'', 'name'=>'SKU girilmemis ilan'],
];
function vestra_listing_by_id(string $id): ?array { global $LISTINGS; return $LISTINGS[$id] ?? null; }
function auth_accounts(): array {
    return [
        ['id'=>'buyer1',  'company'=>'Daymond Proconect', 'name'=>'Daymond'],
        ['id'=>'seller1', 'company'=>'GARAGE LE PARIS',   'name'=>'Garage'],
        ['id'=>'seller2', 'company'=>'TYREX',             'name'=>'Tyrex'],
    ];
}

$ok=0; $fail=0;
$t = function (string $n, bool $c) use (&$ok,&$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};
$thread = fn(array $over=[]) => array_merge(
    ['id'=>'t1','buyer_uid'=>'buyer1','seller_uid'=>'seller1','listing_id'=>'lac-l1212','messages'=>[]], $over);

echo "-- ident: once SKU, sonra ilan id, en son satici kodu --\n";
$t('SKU varsa SKU',                     vestra_msg_seller_ident('lac-l1212', 'seller1') === 'LAC-L1212');
$t('ilanda SKU yoksa ilan id',          vestra_msg_seller_ident('blm-ah0eg000bc27', 'seller1') === 'blm-ah0eg000bc27');
$t('kayitsiz ilan id yine de basilir',  vestra_msg_seller_ident('silinmis-ilan', 'seller1') === 'silinmis-ilan');
$t('ilansiz konusma -> S- kodu',        preg_match('/^S-[0-9A-F]{6}$/', vestra_msg_seller_ident('', 'seller1')) === 1);
$t('   ...ayni satici, ayni kod',       vestra_msg_seller_ident('', 'seller1') === vestra_msg_seller_ident('', 'seller1'));
$t('   ...baska satici, baska kod',     vestra_msg_seller_ident('', 'seller1') !== vestra_msg_seller_ident('', 'seller2'));
$t('   ...uid kodun icinde gecmez',     !str_contains(vestra_msg_seller_ident('', 'seller1'), 'seller1'));
$t('satici da ilan da yok -> tire',     vestra_msg_seller_ident('', '') === '—');

echo "-- alicinin gordugu: urun, dukkan degil --\n";
$asBuyer = vestra_msg_counterpart_label($thread(), 'buyer1');
$t('etiket = Seller + SKU',             $asBuyer === 'Seller LAC-L1212');
$t('dukkan adi GECMEZ',                 !str_contains($asBuyer, 'GARAGE') && !str_contains(mb_strtolower($asBuyer), 'garage'));
$t('SKU yoksa ilan id ile',             vestra_msg_counterpart_label($thread(['listing_id'=>'blm-ah0eg000bc27']), 'buyer1') === 'Seller blm-ah0eg000bc27');
$noListing = vestra_msg_counterpart_label($thread(['listing_id'=>'']), 'buyer1');
$t('ilansiz konusmada da dukkan yok',   preg_match('/^Seller S-[0-9A-F]{6}$/', $noListing) === 1);
$t('silinmis satici hesabi da sizmaz',  vestra_msg_counterpart_label($thread(['seller_uid'=>'yok-boyle-hesap']), 'buyer1') === 'Seller LAC-L1212');

echo "-- saticinin gordugu degismedi --\n";
$asSeller = vestra_msg_counterpart_label($thread(), 'seller1');
$t('satici aliciyi adiyla gorur',       $asSeller === 'Daymond Proconect');
$t('silinmis alici -> Account',         vestra_msg_counterpart_label($thread(['buyer_uid'=>'yok']), 'seller1') === 'Account');

echo "-- VESTRA Support --\n";
$t('support satici slotunda',           vestra_msg_counterpart_label($thread(['seller_uid'=>VESTRA_SUPPORT_UID]), 'buyer1') === 'VESTRA Support');
$t('support alici slotunda',            vestra_msg_counterpart_label($thread(['buyer_uid'=>VESTRA_SUPPORT_UID]), 'seller1') === 'VESTRA Support');

echo "-- gonderim yolu: alicinin mektubu da dukkani yazmaz --\n";
/* vestra_msg_send() dosya/mail bagimliliklari yuzunden burada kosturulamiyor;
   onun yerine mektup etiketini kuran satirlarin KAYNAKTA durdugu dogrulaniyor.
   Panelde gizlenip mektupta yazilan bir ad, gizlemenin kendisini bosa cikarir. */
$t("mektup etiketi identten kuruluyor", str_contains($src, "\$mailLabel = \$toBuyer ? 'Seller '.vestra_msg_seller_ident(\$listingId, \$sellerUid) : \$fromLabel;"));
$t("gonderim mailLabel kullaniyor",     str_contains($src, "vestra_send_mail(\$recAcc['email'], \$mSubj, \$mBody, \$fromEmail, \$mailLabel"));
$t("aliciya Reply-To adres gitmiyor",   str_contains($src, "if (\$toBuyer) \$fromEmail = '';"));
$t("operator bildirimi gercek adla",    str_contains($src, 'vestra_notify("💬 VESTRA message — {$fromLabel}"'));

echo "\n{$ok} gecti, {$fail} kaldi\n";
exit($fail === 0 ? 0 : 1);
