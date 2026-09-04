<?php
/* DIL SECIMI — sira: ?lang= > vlang cerezi > Accept-Language > IP ulkesi > EN.
   Operator sorusu (4 Eyl 2026): "IP nerenin ise dilde oranin olsun".
   IP en SONDA duruyor ve bu bilincli: Accept-Language kisinin OKUDUGU dili
   soyler, IP yalnizca nerede oldugunu. Bu testin isi o sirayi ve tablonun
   dogrulugunu korumak; ag cagrisi YAPMAZ. */
$root = __DIR__ . '/../vestra';
require_once $root . '/inc/i18n.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   {$n}\n"; }
    else    { $fail++; echo "  KALDI {$n}\n"; }
};

echo "== 1. Ulke -> dil tablosu ==\n";
foreach ([
    'DE'=>'de','AT'=>'de','LI'=>'de',
    'FR'=>'fr','LU'=>'fr','MC'=>'fr',
    'ES'=>'es','MX'=>'es','IT'=>'it',
    'PT'=>'pt','BR'=>'pt',
    'RU'=>'ru','BY'=>'ru','KZ'=>'ru','AZ'=>'ru',
    'AE'=>'ar','SA'=>'ar','QA'=>'ar','EG'=>'ar','MA'=>'ar',
] as $cc => $want) {
    $t("{$cc} -> {$want}", vlang_country_lang($cc) === $want);
}

echo "\n== 2. Iki tartismali ulke, karar kayitli ==\n";
/* Turetme yerine elle yazilmasinin sebebi bu ikisi. */
$t('CH -> de (en buyuk dil grubu)', vlang_country_lang('CH') === 'de');
$t('BE -> en (Flaman cogunluk; NL sitede yok)', vlang_country_lang('BE') === 'en');

echo "\n== 3. Sitenin dili olmayan ulke Ingilizce'ye dusuyor ==\n";
foreach (['TR','JP','KR','CN','US','GB','NL','PL','GR','IN'] as $cc) {
    $t("{$cc} eslesmiyor (=> en)", vlang_country_lang($cc) === null);
}
$t('bos kod', vlang_country_lang('') === null);
$t('cop kod', vlang_country_lang('ZZZ') === null);
$t('kucuk harf de calisir', vlang_country_lang('de') === 'de');

echo "\n== 4. Tablo yalnizca SERVIS EDILEN dilleri gosterebilir ==\n";
/* vlang_list()'ten bir dil cikarsa o ulkeler kendiliginden Ingilizce'ye
   dusmeli -- tabloda oksuz bir dil kodu kalmamali. */
$langs = vlang_list();
$bad = [];
foreach (['DE','FR','ES','IT','PT','RU','AE','BE','CH'] as $cc) {
    $l = vlang_country_lang($cc);
    if ($l !== null && !isset($langs[$l])) $bad[] = $cc;
}
$t('tabloda servis edilmeyen dil yok', $bad === []);

echo "\n== 5. Sira: IP en SONDA ==\n";
$src = file_get_contents($root . '/inc/i18n.php');
$t('?lang= once bakiliyor',      (bool)preg_match('/isset\(\$_GET\[.lang.\]\)/', $src));
$t('sonra cerez',                (bool)preg_match('/\$_COOKIE\[.vlang.\]/', $src));
$t('sonra Accept-Language',      (bool)preg_match('/\$d\s*=\s*vlang_detect\(\);/', $src));
$t('IP yalnizca o bos donunce',  (bool)preg_match('/\$d\s*=\s*vlang_detect\(\);\s*\n\s*if\s*\(\s*\$d\s*===\s*null\s*\)\s*\$d\s*=\s*vlang_from_ip\(\);/', $src));

echo "\n== 6. Maliyet korumalari ==\n";
/* Bu uc satir olmazsa her bot ziyareti bir cografi API sorgusu olurdu. */
$fn = (string)strstr($src, 'function vlang_from_ip');
$t('CLI atlaniyor (cron aga cikmaz)', str_contains($fn, "PHP_SAPI === 'cli'"));
$t('bot atlaniyor',                   str_contains($fn, 'vestra_is_bot'));
$t('kisa zaman asimi (1 sn)',         (bool)preg_match('/vestra_ip_intel\(\$ip,\s*1\)/', $fn));
$t('CLI kontrolu ilk sirada',
   strpos($fn, "PHP_SAPI === 'cli'") < strpos($fn, 'security.php'));

echo "\n== 7. CLI'da ag cagrisi yok ==\n";
/* Test zaten CLI: fonksiyon burada her kosulda null donmeli ve HICBIR
   istek yapmamali. */
$t('CLI vlang_from_ip() null', vlang_from_ip() === null);

$n = $ok + $fail;
echo "\nTOPLAM: {$ok} gecti, {$fail} kaldi\n";
exit($fail === 0 ? 0 : 1);
