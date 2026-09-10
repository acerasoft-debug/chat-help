<?php
/**
 * UCUNCU PARTI MEKTUBU — ayakkabi + ic giyim + premium markalar
 * (operator, 10 Eyl 2026: *"250 email gitsin ayyakkabi , underwear ve
 * premium brands olarak"*, *"yeni emaillere gonderme saticilarada gonderme"*).
 *
 * Tutulanlar: rakamlar PARAMETREDEN gelir (metne gomulu degil), bos bolum
 * yazilmaz, fiyat listesi ACIK diye SOZ VERILMEZ (KURAL 19: sayfa artik
 * kayit duvari), ve secim tarafinda kendi saticimiz elenir.
 */
$root = dirname(__DIR__).'/vestra';
require_once $root.'/inc/products.php';
require_once $root.'/inc/email_templates.php';

$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $bad++; echo "  HATA $n\n"; }
};
$noTurkish = fn(string $s) => !preg_match('/[şğıİÇĞŞ]/u', $s);

echo "== 1. Rakamlar parametreden ==\n";
[$s1, $b1] = vestra_tpl_new_collection_shoes('en', 'Base Blu', ['shoes'=>335,'underwear'=>42,'brands'=>'Balmain, Burberry']);
$t('ayakkabi sayisi govdede',      str_contains($b1, '335 articles of footwear'));
$t('ic giyim sayisi govdede',      str_contains($b1, '42 articles of underwear'));
$t('markalar govdede',             str_contains($b1, 'Balmain, Burberry'));
$t('firma adiyla hitap',           str_contains($b1, 'Hello Base Blu,'));
/* Baska rakamla cagirinca metin DEGISMELI: sabit yazilmis olsaydi bu iddia
   dusmezdi ve "canli kayittan" cumlesi bos bir iddia olurdu. */
[, $b2] = vestra_tpl_new_collection_shoes('en', 'X', ['shoes'=>7,'underwear'=>3,'brands'=>'Lacoste']);
$t('rakam gercekten degisiyor',    str_contains($b2, '7 articles') && !str_contains($b2, '335'));

echo "\n== 2. Bos bolum yazilmaz ==\n";
[, $b3] = vestra_tpl_new_collection_shoes('en', 'X', ['shoes'=>0,'underwear'=>12,'brands'=>'Lacoste']);
$t('0 ayakkabi satiri hic yok',    !str_contains($b3, 'footwear'));
$t('ic giyim satiri duruyor',      str_contains($b3, '12 articles of underwear'));
[, $b4] = vestra_tpl_new_collection_shoes('en', 'X', ['shoes'=>5,'underwear'=>0,'brands'=>'']);
$t('0 ic giyim satiri hic yok',    !str_contains($b4, 'underwear'));
$t('marka yoksa marka satiri yok', !str_contains($b4, 'Premium houses'));
$t('yer tutucu sizmiyor',          !str_contains($b4, '%SHOES%') && !str_contains($b4, '%BRANDS%') && !str_contains($b4, '%d'));

echo "\n== 3. Fiyat listesi ACIK diye soz VERILMIYOR (KURAL 19) ==\n";
foreach (['en','de','fr','it','es'] as $lg) {
    [, $bb] = vestra_tpl_new_collection_shoes($lg, 'X', ['shoes'=>10,'underwear'=>10,'brands'=>'Lacoste']);
    /* Eski Winter mektubu "344 articles ... are on the price list" diyordu;
       sayfa artik kayit duvari, o cumle bugun yanlis olurdu. */
    $t("$lg: 'listede' vaadi yok", !preg_match('/on the price list|in der Preisliste|sur la liste de prix|nel listino|en la lista de precios/iu', $bb));
    $t("$lg: kayit sarti yaziyor", (bool)preg_match('/registered businesses|registrierte Betriebe|entreprises enregistrées|aziende registrate|empresas registradas/iu', $bb));
}

echo "\n== 4. Diller ==\n";
$src = file_get_contents($root.'/inc/email_templates.php');
preg_match('/function vestra_tpl_new_collection_shoes\(.*?\n}/s', $src, $m);
$langs = [];
preg_match_all("/^      '([a-z]{2})' =>/m", $m[0], $lm);
$langs = $lm[1];
$t('12 dil var (Winter mektubuyla ayni set)', count($langs) === 12);
foreach (['en','de','fr','it','es','nl','pt','pl','cs','el','ja','ko'] as $lg) {
    $t("dil {$lg} tanimli", in_array($lg, $langs, true));
}
[, $bJa] = vestra_tpl_new_collection_shoes('ja', 'X', ['shoes'=>10,'underwear'=>10,'brands'=>'Lacoste']);
$t('ja gercekten Japonca',         str_contains($bJa, 'フットウェア'));
[$sTr, $bTr] = vestra_tpl_new_collection_shoes('tr', 'X', ['shoes'=>10,'underwear'=>10,'brands'=>'Lacoste']);
$t('bilinmeyen dil Ingilizceye duser', str_contains($bTr, 'articles of footwear'));
$t('metinde Turkce sizmiyor',      $noTurkish($bTr) && $noTurkish($sTr));

echo "\n== 5. Is akisi kablolamasi ==\n";
$wf = file_get_contents(dirname(__DIR__).'/.github/workflows/send-outreach.yml');
$t('newcoll_letter girdisi var',   str_contains($wf, 'newcoll_letter:'));
$t('girdi ADIM env\'inde',          str_contains($wf, 'SEND_NC_LETTER: ${{ github.event.inputs.newcoll_letter }}'));
$t('envs listesinde',              str_contains($wf, 'SEND_NC_LETTER,'));
$t('shoes secilince shoes sablonu', str_contains($wf, 'vestra_tpl_new_collection_shoes($lang, $company, $NC_FACTS)'));
$t('winter varsayilan kaliyor',    str_contains($wf, 'vestra_tpl_new_collection($lang, $company)'));
/* Rakamlar canli kayittan: sayim kodu workflow'da olmali. */
$t('ayakkabi CANLI sayiliyor',     str_contains($wf, "=== 'footwear') \$ncShoes++"));
$t('ic giyim CANLI sayiliyor',     str_contains($wf, "'underwear')) \$ncUnder++"));
$t('iki bolum de bossa DURUYOR',   str_contains($wf, 'ayakkabi ve ic giyim ikisi de 0'));
$t('markalar kuru kosuda basiliyor', str_contains($wf, 'MARKALAR (mektupta yazacak)'));

echo "\n== 6. Saticiya kampanya gitmez ==\n";
$t('satici hesaplari toplaniyor',  str_contains($wf, "if ((\$sAcc['type'] ?? '') !== 'seller') continue;"));
$t('adres eslesmesi eliyor',       str_contains($wf, 'isset($SELLER_MAIL[$email])'));
$t('alan adi eslesmesi eliyor',    str_contains($wf, 'isset($SELLER_DOM[$slDom])'));
/* gmail'deki bir satici, gmail'deki her leadi elemesin. */
$t('serbest posta saglayicisi muaf', str_contains($wf, '!isset($NC_SHARED[$slDom])'));
$t('elenen sayisi ozet satirinda', str_contains($wf, 'SATICI: kendi satici hesabimiz oldugu icin elenen'));

echo "\n{$ok} ok, {$bad} hata\n";
exit($bad ? 1 : 0);
