<?php
/* Kayitta kapisi ACIK dogan ulkeler (operator kararlari, 8 Eyl 2026, ayni gun
 * uc kez buyudu: "arabistandaan girenler direkt fiyatlari gorebilsinler ve
 * siparis edebilsinler" -> "japonya, avustralya da ayni olsun" -> "singapur da").
 *
 * Bu test IKI seyi tutuyor ve ikincisi asil olan:
 *
 *   1. vestra_country_auto_opens() -- ISO kodu, tam adlar, dil varyantlari, ve
 *      her ulkenin KENDI YAKIN-KOMSU TUZAGI. Kural KAPI ACIYOR: yanlis bir
 *      eslesme, operatorun hakkinda hicbir karar vermedigi bir firmaya toptan
 *      fiyati ve siparis hakkini verir. Turkiye kuralinda Turkmenistan neyse
 *      burada Guney Afrika (SA) ve Avusturya (AU/AT) odur -- CLAUDE.md'deki
 *      mango/zara dersinin ayni sinifi.
 *
 *   2. Kablolama: kapi GERCEKTEN auth_register()'da, GERCEKTEN yalnizca alicida,
 *      GERCEKTEN kapinin kendi alanini (kyb_status) yaziyor ve profil
 *      kaydetmede YOK mu. Kapinin ikinci bir tanimi yazilirsa bu bolum kirmizi
 *      olur -- bu depoda alti kez kontrolun kendisi yanlis yere bakti.
 */

$secSrc = file_get_contents(__DIR__.'/../vestra/inc/security.php');
foreach (['vestra_cc_of_country', 'vestra_country_of_cc',
          'vestra_auto_open_countries', 'vestra_country_auto_opens'] as $fn) {
    if (!preg_match('/^function '.preg_quote($fn,'/').'\(.*?^}/ms', $secSrc, $m)) { echo "HATA: $fn bulunamadi\n"; exit(1); }
    eval($m[0]);
}

$ok=0; $fail=0;
$t = function (string $n, bool $c) use (&$ok,&$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};

echo "-- 1. Liste tam olarak operatorun saydigi dort ulke --\n";
$codes = array_keys(vestra_auto_open_countries());
sort($codes);
$t("liste = AU, JP, SA, SG", $codes === ['AU','JP','SA','SG']);

echo "-- 2. ACMASI gereken yazimlar (ulke -> beklenen ISO) --\n";
$must = [
  'SA' => ['SA','sa',' SA ','Saudi Arabia','saudi arabia','SAUDI ARABIA','Saudi-Arabia',
           'saudiarabia','Saudi','KSA','ksa','Kingdom of Saudi Arabia',
           'The Kingdom of Saudi Arabia','Saudi Arabien','Saudi-Arabien',
           'Arabie Saoudite','Arabia Saudita','Arabia Saudí','Arábia Saudita',
           'Саудовская Аравия','السعودية','المملكة العربية السعودية','Suudi Arabistan',
           "Saudi   Arabia"],                       // cift bosluk -- form serbest metin
  'JP' => ['JP','jp',' JP ','Japan','japan','JAPAN','Nippon','Nihon','State of Japan',
           'Japon','Japón','Giappone','Japão','Япония','日本','日本国','اليابان','Japonya'],
  'AU' => ['AU','au',' AU ','Australia','australia','AUSTRALIA',
           'Commonwealth of Australia','Australien','Australie','Austrália',
           'Австралия','オーストラリア','豪州','أستراليا','Avustralya'],
  'SG' => ['SG','sg',' SG ','Singapore','singapore','SINGAPORE',
           'Republic of Singapore','Singapur','Singapour','Singapura',
           'Сингапур','シンガポール','新加坡','سنغافورة'],
];
foreach ($must as $cc => $vals) {
    foreach ($vals as $v) {
        $got = vestra_country_auto_opens($v);
        $t("'".trim($v)."' -> $cc", $got === $cc);
    }
}

echo "-- 3. YAKIN-KOMSU TUZAKLARI: acmamasi GEREKENLER --\n";
/* Her satir bir karisma vakasi. Hepsi ayni soruyu soruyor: kapi acan bir kural
   benzeyen bir ada bakip acilmasin. */
$trap = [
  // SA tuzagi: gunluk dilde "SA" cok zaman Guney Afrika demek; ISO'su ZA.
  'South Africa', 'south africa', 'Republic of South Africa', 'ZA', 'za',
  'Saudi Arabia Trading Co.',   // sirket adi, ulke degil
  'Saudia', 'Sudan', 'Somalia', 'Syria', 'Samoa', 'San Marino', 'Senegal',
  // AU tuzagi: AVUSTURYA. Ayri bir ulke (AT) ve klasik karisma.
  'Austria', 'austria', 'AT', 'at', 'Österreich', 'Osterreich', 'Autriche',
  'South Australia', 'Western Australia',   // eyalet, ulke degil
  // JP tuzagi: 'JA' DIL kodu, Japonya'nin kodu degil; Jamaika JM.
  'JA', 'ja', 'Jamaica', 'JM', 'Jordan', 'Japanese',
  // SG tuzagi: bare 'SG' Senegal'i (SN) yakalamamali; Sri Lanka LK.
  'SN', 'Sri Lanka', 'LK',
  // Genel
  'Germany', 'France', 'United States', 'China', 'Turkey', 'TR',
];
foreach ($trap as $v) {
    $t("'$v' -> ACMAZ", vestra_country_auto_opens($v) === '');
}
$t("'South Africa' 'sa' alt-dizesiyle yakalanmiyor",  vestra_country_auto_opens('South Africa') === '');
$t("'Austria' 'au' alt-dizesiyle yakalanmiyor",       vestra_country_auto_opens('Austria') === '');
$t("'Australia' Avusturya SAYILMIYOR (ters yon)",     vestra_country_auto_opens('Australia') === 'AU');

echo "-- 4. Bos / anlamsiz deger acmaz --\n";
foreach (['', '   ', '-', '??', 'XX', 'x', '123'] as $v) {
    $t("'".trim($v)."' -> ACMAZ", vestra_country_auto_opens($v) === '');
}

echo "-- 5. Turkiye kurali BOZULMADI (iki kural ters yone bakiyor) --\n";
if (preg_match('/^function vestra_country_declares_turkey\(.*?^}/ms', $secSrc, $mT)) { eval($mT[0]); }
$t('Turkiye hala yakalaniyor',
   function_exists('vestra_country_declares_turkey') && vestra_country_declares_turkey('Turkey'));
$t('Turkiye kapiyi ACMIYOR', vestra_country_auto_opens('Turkey') === '');
foreach (['Saudi Arabia','Japan','Australia','Singapore'] as $v) {
    $t("'$v' Turkiye SAYILMIYOR", !vestra_country_declares_turkey($v));
}

echo "-- 6. Ulke adi ISO kodundan cozuluyor (bildirimde iki ayri ad olmasin) --\n";
foreach (['SA'=>'Saudi Arabia','JP'=>'Japan','AU'=>'Australia','SG'=>'Singapore'] as $cc=>$name) {
    $t("cc_of_country('$name') = $cc", vestra_cc_of_country($name) === $cc);
    $t("country_of_cc('$cc') = $name", vestra_country_of_cc($cc) === $name);
}
$t("Avusturya haritada Avustralya'ya duşmuyor", vestra_cc_of_country('Austria') === 'AT');

echo "\n-- 7. Kablolama: kapi auth_register()'da ve DOGRU alani yaziyor --\n";
$authSrc = file_get_contents(__DIR__.'/../vestra/inc/auth.php');

$posGate = strpos($authSrc, 'vestra_country_auto_opens');
$posKyb  = strpos($authSrc, "'kyb_status'    =>");
$posSave = strpos($authSrc, 'auth_save_accounts($list)');
$t('auth_register(): kontrol VAR', $posGate !== false);
$t('kontrol, hesap KAYDEDILMEDEN once calisiyor', $posGate !== false && $posSave !== false && $posGate < $posSave);
$t('kontrol, kyb_status yazilmadan once calisiyor', $posGate !== false && $posKyb !== false && $posGate < $posKyb);

/* Kapi, promo hesabinin kullandigi AYNI alandan aciliyor: ikinci bir kapi
   tanimi (ornegin status='active' elle yazmak ya da auth_prices_unlocked'i
   degistirmek) bu iddiayi dusurur. */
$t("kapi kyb_status='approved' ile aciliyor, ikinci bir tanimla degil",
   (bool)preg_match("/'kyb_status'\s*=>\s*\(\\\$promo_data\s*\|\|\s*\\\$autoApprove\)\s*\?\s*'approved'\s*:\s*'pending'/", $authSrc));
$t('auth_prices_unlocked() DEGISMEDI (hala tek kapi)',
   (bool)preg_match('/function auth_prices_unlocked\(\?array \$acc\): bool \{\s*if \(!\$acc\) return false;.*?return auth_user_approved\(\$acc\);/s', $authSrc));
$t('auth_user_approved() ulke ozel dali TASIMIYOR (kapi tek yerde)',
   preg_match('/^function auth_user_approved\(.*?^}/ms', $authSrc, $mA) === 1
   && !str_contains(strtolower($mA[0]), 'auto_open') && !str_contains(strtolower($mA[0]), 'country'));

echo "-- 8. YALNIZCA alici; satici eski akista --\n";
$t("kosul type==='buyer' iceriyor",
   (bool)preg_match('/\$autoCc\s*=\s*\$type\s*===\s*\'buyer\'\s*\?\s*vestra_country_auto_opens/', $authSrc));

echo "-- 9. YALNIZCA kayitta; profil kaydetme kapiyi ACMIYOR --\n";
/* Turkiye kontrolu profil kaydetmede de var (KAPATIR). Bu ACAR, o yuzden orada
   olmamali: hesabin ulkesini degistirerek kendi kapisini acmak. */
foreach (['buyer.php', 'seller.php'] as $f) {
    $src = file_get_contents(__DIR__.'/../vestra/'.$f);
    $t("$f: otomatik acilma YOK", !str_contains($src, 'vestra_country_auto_opens'));
    $t("$f: Turkiye engeli hala VAR", str_contains($src, 'vestra_country_declares_turkey'));
}

echo "-- 10. Kayit mektubu: acik hesaba 'bekleyin' DEMIYOR --\n";
$ntSrc = file_get_contents(__DIR__.'/../vestra/inc/notify.php');
$t('vestra_ack_text() $approved parametresi aliyor',
   (bool)preg_match('/function vestra_ack_text\(\$lang,\$name,\$type,\s*bool \$approved\s*=\s*false\)/', $ntSrc));
$t('auth_register() bayragi mektuba GECIRIYOR',
   (bool)preg_match('/vestra_ack_text\(\$lang,\s*\$acc\[\'name\'\][^;]*\$type,\s*\$autoApprove\)/', $authSrc));

if (preg_match('/^function vestra_ack_text\(.*?^}/ms', $ntSrc, $mAck)) {
    $ackSrc = $mAck[0];
    $approvedBlock = '';
    if (preg_match('/if \(\$approved\) \{.*?\n  \}/s', $ackSrc, $mB)) $approvedBlock = $mB[0];
    $t('acik-hesap govdesi VAR', $approvedBlock !== '');
    /* Varsayilan metin "Our team will review them and activate your account"
       diyor; bu hesapta YAPILMAYACAK bir isi bekletiyor (KURAL 2b). */
    $t("acik-hesap govdesi 'review them and activate' DEMIYOR",
       $approvedBlock !== '' && !str_contains($approvedBlock, 'review them and activate'));
    $t('acik-hesap govdesi hesabin ACIK oldugunu soyluyor',
       str_contains($approvedBlock, 'open straight away'));
    $t('acik-hesap govdesi belgeyi hala istiyor (belge KAPI degil, uyari)',
       stripos($approvedBlock, 'trade licence') !== false);
    $t('acik-hesap govdesi belgenin siparisi DURDURMADIGINI soyluyor',
       str_contains($approvedBlock, 'does not hold up your ordering'));
    $t('varsayilan govde DEGISMEDI (acik olmayan hesap hala bekliyor)',
       str_contains($ackSrc, 'Our team will review them and activate your account'));
    $t('acik-hesap dugmesi belge sayfasina degil KATALOGA gidiyor',
       str_contains($approvedBlock, '/shop') && !str_contains($approvedBlock, 'tab=kyc'));
    /* Mektup hicbir ulke adi TASIMIYOR: dort ulke icin dort metin yazmak,
       besincisi eklendiginde sessizce eksik kalirdi. */
    foreach (['Saudi','Japan','Australia','Singapore'] as $n) {
        $t("acik-hesap govdesi '$n' adini gomulu TASIMIYOR", !str_contains($approvedBlock, $n));
    }
    foreach (['de','fr','it','es'] as $L) {
        $t("acik-hesap govdesi '$L' icin de yazili", $approvedBlock !== '' && str_contains($approvedBlock, "'$L'=>["));
    }
}

echo "-- 11. Operator kayittan HABERDAR oluyor (sessiz kapi yok) --\n";
$t('bildirim konusunda acik hesap rozeti var', str_contains($authSrc, '[account OPEN — '));
$t('bildirim govdesinde gerekce yazili', str_contains($authSrc, 'ALREADY OPEN'));
$t('hesapta gerekce saklaniyor (kyb_auto)', str_contains($authSrc, "'country:'.\$autoCc"));
$t('rozet YALNIZCA otomatik onayda basiliyor',
   (bool)preg_match('/\$autoApprove\s*\?\s*\'\s*\[account OPEN/', $authSrc));
/* Ulke adi ISO kodundan cozuluyor: bildirimde elle yazilmis bir ulke adi,
   liste buyudugunde yanlis ulkeyi soyleyen bir satir birakirdi. */
$t('bildirim ulke adini KODDAN cozuyor', str_contains($authSrc, 'vestra_country_of_cc($autoCc)'));
foreach (['Saudi Arabia','Japan','Australia','Singapore'] as $n) {
    $t("bildirimde '$n' elle yazilmamis", !str_contains($authSrc, 'registered country is '.$n));
}

echo "\n$ok gecti, $fail kaldi\n";
exit($fail ? 1 : 0);
