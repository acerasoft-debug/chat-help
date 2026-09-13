<?php
/* Para birimi secimi — operator, 13 Eyl 2026:
 *   "para birimi surekli degisiyor ... para birimi secilmesine ragmen bir
 *    sonraki linke tiklandiginda gene EUR oluyor ayrica IP den algilanip
 *    para biriminin ... o ulkeye gore ayarlanmasi gerekir"
 *
 * IKI YONU DE tutuyor, cunku tek yon yazilsaydi test yesil kalir ve gercek
 * kusur gorunmezdi:
 *   - SECIM KALICI olmali (cerez gercekten yaziliyor mu, tek yazici mi),
 *   - ama secim IP'yi EZMELI (ulke yalnizca secim yokken devreye girer).
 *
 * MEKANIZMA ile SEVK EDILEN DEGERLER ayri ayri: mekanizma testin kendi
 * kurdugu $_GET/$_COOKIE ile, kablolama ise kaynaktan. `vestra_currency()`
 * surecte `static` onbellekli, o yuzden oncelik siralamasi AYRI PHP
 * SURECLERINDE olculuyor -- ayni surecte ikinci kez sormak yazilan degeri
 * degil onbellegi olcerdi (bu depoda dropship bayraginda bir kez yasandi).
 */
$root = dirname(__DIR__);
$src  = file_get_contents($root.'/vestra/inc/money.php');
if ($src === false || $src === '') { echo "HATA: money.php okunamadi\n"; exit(1); }

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};

/* Fonksiyonlari kaynaktan cikarip calistiriyoruz: money.php'nin tamami
   security.php'yi ve agi getirir; olcmek istedigimiz kisim saf. */
foreach (['vestra_currencies','vestra_eu_countries','vestra_currency_for_cc'] as $fn) {
    if (!preg_match('/^function '.preg_quote($fn,'/').'\(.*?^}/ms', $src, $m)) {
        echo "HATA: $fn money.php'de bulunamadi\n"; exit(1);
    }
    eval($m[0]);
}

echo "== 1. ULKE -> PARA BIRIMI ==\n";
$t('AU -> AUD',                vestra_currency_for_cc('AU') === 'AUD');
$t('CA -> CAD',                vestra_currency_for_cc('CA') === 'CAD');
$t('US -> USD',                vestra_currency_for_cc('US') === 'USD');
$t('DE (AB) -> EUR',           vestra_currency_for_cc('DE') === 'EUR');
$t('PL (AB, euro degil) -> EUR', vestra_currency_for_cc('PL') === 'EUR');
$t('TR (AB disi) -> USD',      vestra_currency_for_cc('TR') === 'USD');
$t('bos ulke -> EUR (taban)',  vestra_currency_for_cc('') === 'EUR');
$t('kucuk harf de calisiyor',  vestra_currency_for_cc('au') === 'AUD');
/* YAKIN KOMSU TUZAKLARI — mango/zara dersi. Bir harfi tutan bir eslesme
   Avusturyaliya Avustralya dolari gosterirdi. */
$t('AT (Avusturya) AUD DEGIL', vestra_currency_for_cc('AT') === 'EUR');
$t('CH (Isvicre) CAD DEGIL',   vestra_currency_for_cc('CH') === 'USD');
$t('CY (Kibris, AB) -> EUR',   vestra_currency_for_cc('CY') === 'EUR');
$t('AUS diye bir ISO yok -> USD (AB listesinde degil)',
   vestra_currency_for_cc('AUS') === 'USD');
/* Her dondurdugu deger GERCEKTEN destekleniyor olmali: tabloya bir gun
   "GBP" yazilirsa switcher onu cizemez ve sayfa bos bir birim gosterirdi. */
$all = vestra_currencies();
$bad = [];
foreach (['AU','CA','US','DE','TR','','ZZ','JP','BR','GB'] as $c)
    if (!isset($all[vestra_currency_for_cc($c)])) $bad[] = $c;
$t('dondurulen her birim vestra_currencies() icinde', $bad === []);

echo "\n== 2. SECIMI HATIRLAMA (kaynak kablolamasi) ==\n";
/* Yasanmis kusur: cerez yazma `vestra_currency()`nin icindeydi ve o fonksiyon
   head.php'nin ~235. satirinda, cikti tamponu bosaldiktan SONRA cagriliyordu;
   `headers_sent()` DOGRU oluyor ve `@setcookie` sessizce atlaniyordu. */
$t('vestra_currency_remember() tanimli',
   (bool)preg_match('/function vestra_currency_remember\(\)/', $src));
/* ASIL IDDIA: money.php YUKLENIRKEN cagriliyor. Satir basina bagli (`^…;`),
   cunku yorum icinde gecen ayni ad iddiayi bosa gecirirdi. */
$t('money.php yuklenirken cagriliyor (satir basinda)',
   (bool)preg_match('/^vestra_currency_remember\(\);\s*$/m', $src));
/* Cagri, FONKSIYON TANIMINDAN SONRA olmali; PHP kosullu olmayan tanimlari
   hoist eder ama include-time cagriyi tanimin ustune koymak, dosya bir gun
   kosullu bir bloga sarilirsa "undefined function" ile olur. */
$t('cagri, tanimdan SONRA geliyor',
   strpos($src, 'function vestra_currency_remember(') <
   strpos($src, "\nvestra_currency_remember();"));
/* TEK YAZICI: iki kopya er gec ayrisir (bu depoda dort mektup govdesi dersi). */
$t('vcur cerezini yazan TEK yer var',
   substr_count($src, "setcookie('vcur'") === 1);
$t('CLI cerez yazmiyor (cron/test)',
   (bool)preg_match("/function vestra_currency_remember.*?PHP_SAPI === 'cli'/s", $src));
/* Sessiz basarisizlik yok: yazamadiysa kutuge dussun, yoksa ayni kusur geri
   gelir ve yine kimse gormez. */
$t('yazilamayinca error_log yaziyor',
   (bool)preg_match("/function vestra_currency_remember.*?error_log\(/s", $src));
$t('ayni istek de secimi goruyor (\$_COOKIE yaziliyor)',
   (bool)preg_match("/function vestra_currency_remember.*?\\\$_COOKIE\['vcur'\]\s*=/s", $src));

echo "\n== 3. IP MALIYET KORUMALARI (KURAL 12 ile ayni) ==\n";
/* `vlang_from_ip()` bu korumalari tasiyordu, para birimi yolu tasimiyordu:
   ayni `vestra_ip_intel()`, ama bot atlanmiyor ve zaman asimi 3 sn. */
if (!preg_match('/^function vestra_currency\(\).*?^}/ms', $src, $mCur)) {
    $t('vestra_currency() govdesi bulundu', false); $mCur = [''];
} else { $t('vestra_currency() govdesi bulundu', true); }
$body = $mCur[0];
$t('bot atlaniyor',            strpos($body, 'vestra_is_bot(') !== false);
$t('CLI atlaniyor',            strpos($body, "PHP_SAPI !== 'cli'") !== false);
$t('zaman asimi 1 sn',         (bool)preg_match('/vestra_ip_intel\(\$ip,\s*1\)/', $body));
$t('IP cozulemezse EUR',       (bool)preg_match("/return \\\$cur = 'EUR';/", $body));
/* Beyan edilen ulke AGSIZ ve IP'den ONCE sorulmali: girisli hesabin kendi
   kaydi hem dogru hem bedava. */
$t('hesabin ulkesi IP\'den ONCE',
   strpos($body, 'vestra_cc_of_country(') !== false
   && strpos($body, 'vestra_cc_of_country(') < strpos($body, 'vestra_ip_intel('));

echo "\n== 4. ONCELIK SIRASI (ayri PHP surecleri) ==\n";
/* Her senaryo kendi surecinde: `vestra_currency()` static onbellekli. */
$run = function (array $get, array $cookie) use ($root): string {
    $php = '<?php $_GET='.var_export($get, true).'; $_COOKIE='.var_export($cookie, true).';'
         . '$_SERVER["HTTP_USER_AGENT"]="Mozilla/5.0 (compatible; TestBot/1.0)";'
         . 'require '.var_export($root.'/vestra/inc/money.php', true).';'
         . 'echo vestra_currency(), "|", ($_COOKIE["vcur"] ?? "-");';
    $f = tempnam(sys_get_temp_dir(), 'cur').'.php';
    file_put_contents($f, $php);
    $out = (string)shell_exec('php '.escapeshellarg($f).' 2>/dev/null');
    @unlink($f);
    return trim($out);
};
$t('?cur=USD -> USD ve $_COOKIE guncelleniyor', $run(['cur'=>'USD'], []) === 'USD|USD');
$t('?cur=aud (kucuk harf) -> AUD',              $run(['cur'=>'aud'], []) === 'AUD|AUD');
$t('cerez CAD -> CAD',                          $run([], ['vcur'=>'CAD']) === 'CAD|CAD');
/* SECIM IP\'YI EZER: ikisi de varken kazanan GET. Ters yon yazilmasaydi
   "her zaman IP" diyen bir kusur da yesil gorunurdu. */
$t('?cur=EUR, cerez USD -> EUR (secim kazanir)',
   $run(['cur'=>'EUR'], ['vcur'=>'USD']) === 'EUR|EUR');
/* Gecersiz girdi sessizce KABUL EDILMEMELI: uydurma bir birim switcher\'da
   cizilemez ve fiyat bloklari bos kalirdi. */
$t('?cur=XXX yok sayiliyor, cerez korunuyor',   $run(['cur'=>'XXX'], ['vcur'=>'CAD']) === 'CAD|CAD');
$t('bozuk cerez yok sayiliyor',                 str_starts_with($run([], ['vcur'=>'ZZZ']), 'EUR'));
/* CLI + bot: ag cagrisi YOK, taban EUR. (Test zaten CLI\'da kosuyor.) */
$t('secim yokken CLI/bot -> EUR (ag yok)',      str_starts_with($run([], []), 'EUR'));

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
