<?php
/* "Hesabini actik, sifreni sec" mektubu (vestra_account_ready_text, 17 Eyl 2026).
 *
 * NEDEN AYRI BIR MEKTUP: vestra_reset_text "birileri (umariz siz) sifre
 * sifirlama ISTEDI" diye aciliyor. Elle acilan bir hesapta musteri hicbir sey
 * istemedi ve hic sahip olmadigi bir sifre "sifirlanmiyor". Bu depo ayni dersi
 * iki kez kaydetti (KURAL 2b, KURAL 2h): varsayilan metin, o hesapta OLMAYAN
 * bir hikayeyi anlatiyorsa yeni bir govde yazilir.
 *
 * TESTIN IKI YONU DE TUTMASI SART: mektup kapi ACIKKEN "hemen siparis
 * verebilirsiniz", KAPALIYKEN "ekibimiz onaylayacak" demeli. Tek yon
 * yazilsaydi, her hesaba ayni cumleyi basan bir kusur yesil kalirdi. */

$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? "  ok   " : "  FAIL ").$n."\n"; };

$root = dirname(__DIR__).'/vestra';
require_once $root.'/inc/notify.php';

$LANGS = ['en', 'de', 'fr', 'it', 'es'];
$LINK  = 'https://vestrasales.com/reset?token=DEADBEEF';
$NAME  = 'Francisco Javier';

echo "== 1. Bu mektup SIFIRLAMA mektubu DEGIL ==\n";
/* Kanit, sablonun adi degil METNIN KENDISI: iki govde birbirinin kopyasi
   olsaydi yeni fonksiyon hicbir sey degistirmiyor demekti. */
foreach ($LANGS as $l) {
    [$rs, $rb] = vestra_reset_text($l, $NAME, $LINK);
    [$as, $ab] = vestra_account_ready_text($l, $NAME, $LINK, true, false);
    $t("[$l] govde sifirlama govdesinden FARKLI", $ab !== $rb);
    $t("[$l] konu sifirlama konusundan FARKLI",   $as !== $rs);
}
/* "Birileri istedi" hikayesi hicbir dilde gecmemeli: musteri istemedi. */
$story = ['en'=>'requested a password reset', 'de'=>'angefordert', 'fr'=>'a demandé la réinitialisation',
          'it'=>'ha richiesto la reimpostazione', 'es'=>'ha solicitado restablecer'];
foreach ($LANGS as $l) {
    [, $ab] = vestra_account_ready_text($l, $NAME, $LINK, true, false);
    $t("[$l] 'birileri sifirlama istedi' hikayesi YOK", !str_contains($ab, $story[$l]));
}

echo "\n== 2. Kapi ACIK / KAPALI: IKI YON de ==\n";
/* KURAL 2b: kapi acikken "ekibimiz inceleyecek" demek, musteriyi yapmasi
   gerekmeyen bir beklemeye yollar. Kapali hesapta ise "hemen siparis
   verebilirsiniz" demek, kilitli sayfaya yollamak olurdu -- hic yazmamaktan
   kotu. */
$openMark   = ['en'=>'unlocked: wholesale prices', 'de'=>'freigeschaltet: Großhandelspreise',
               'fr'=>'débloqué : prix de gros', 'it'=>'sbloccato: prezzi', 'es'=>'desbloqueada: precios'];
$closedMark = ['en'=>'not unlocked yet', 'de'=>'noch nicht freigeschaltet', 'fr'=>"n'est pas encore débloqué",
               'it'=>'non è ancora sbloccato', 'es'=>'todavía no está desbloqueada'];
foreach ($LANGS as $l) {
    [, $bo] = vestra_account_ready_text($l, $NAME, $LINK, true,  false);
    [, $bc] = vestra_account_ready_text($l, $NAME, $LINK, false, false);
    $t("[$l] ACIK: 'siparis verebilirsiniz' VAR",  str_contains($bo, $openMark[$l]));
    $t("[$l] ACIK: 'onay bekleyin' YOK",           !str_contains($bo, $closedMark[$l]));
    $t("[$l] KAPALI: 'onay bekleyin' VAR",         str_contains($bc, $closedMark[$l]));
    $t("[$l] KAPALI: 'siparis verebilirsiniz' YOK", !str_contains($bc, $openMark[$l]));
}

echo "\n== 3. Belge cumlesi: yalniz ISTENMISSE ==\n";
/* KURAL 2b: 'uploaded' olana "yukleyin" demek, yaptigi isi tekrar
   yaptirmaktir. */
$docMark = ['en'=>'trade licence', 'de'=>'Gewerbeanmeldung', 'fr'=>'Kbis', 'it'=>'visura camerale', 'es'=>'licencia comercial'];
foreach ($LANGS as $l) {
    [, $bY] = vestra_account_ready_text($l, $NAME, $LINK, true, true);
    [, $bN] = vestra_account_ready_text($l, $NAME, $LINK, true, false);
    $t("[$l] istenmisse belge cumlesi VAR", str_contains($bY, $docMark[$l]));
    $t("[$l] istenmemisse belge cumlesi YOK", !str_contains($bN, $docMark[$l]));
}

echo "\n== 4. Bes dil eksiksiz, yer tutucular saglam ==\n";
foreach ($LANGS as $l) {
    [$s, $b, $o] = vestra_account_ready_text($l, $NAME, $LINK, true, true);
    $t("[$l] konu bos degil",        trim($s) !== '');
    $t("[$l] ad basildi",            str_contains($b, $NAME));
    $t("[$l] link basildi",          str_contains($b, $LINK));
    /* Ceviride kacan bir '%' sprintf'i kirar ve mektup yarim gider. */
    $t("[$l] cozulmemis yer tutucu YOK", !str_contains($b, '%s') && substr_count($b, '%') === 0);
    $t("[$l] imza satiri var",       str_contains($b, 'vestrasales.com'));
    /* Suresi dolan linkin cikis yolu METINDE: 1 saat soguk bir mektupta kisa. */
    $t("[$l] suresi dolarsa ne yapilacagi yazili", str_contains($b, 'vestrasales.com/forgot'));
}
/* Bilinmeyen dil Ingilizceye duser -- sessizce bos mektup gondermez. */
[$sx, $bx] = vestra_account_ready_text('zz', $NAME, $LINK, true, true);
[$se, $be] = vestra_account_ready_text('en', $NAME, $LINK, true, true);
$t('bilinmeyen dil EN govdesine duser', $bx === $be && $sx === $se);

echo "\n== 5. Dugme, govdedeki linkin AYNISI ==\n";
/* Govdede bir adrese, dugmede baskasina giden bir mektup, dugmesiz bir
   mektuptan kotu. */
foreach ($LANGS as $l) {
    [, , $o] = vestra_account_ready_text($l, $NAME, $LINK, true, true);
    $t("[$l] dugme URL'si = link", (string)($o['button']['url'] ?? '') === $LINK);
    $t("[$l] dugme etiketi var",   trim((string)($o['button']['label'] ?? '')) !== '');
}

echo "\n== 6. Is akisi kablolamasi ==\n";
$wf = (string)file_get_contents(dirname(__DIR__).'/.github/workflows/seller-products.yml');
$t('mod var', str_contains($wf, "admin_mode == 'password_setup'"));
/* Adim SINIRINA kadar kes. Bir sonraki `- name:` yoksa dosyanin GERI KALANI
   olculur ve "sizinti yok" iddialari baska adimlarin kodunu okuyup kirmizi
   doner -- ilk yazimda tam bu oldu ve iki iddia yanlis yere baktigi icin
   dustu (bu depoda kayitli "kontrol yanlis yere bakiyor"un bir baskasi). */
$step = preg_split('/\n      - name:/u', explode("admin_mode == 'password_setup'", $wf)[1] ?? '')[0] ?? '';
$t('adim govdesi okunabildi (olcum gecerli)', strlen($step) > 1500);

/* "Gecmemeli" iddialari YORUMSUZ metne bakiyor: ilk yazimda `vlang()` iddiasi,
   vlang()'in NEDEN kullanilmadigini anlatan kendi yorumumu okuyup kirmizi
   dondu. Iddia gevsetilmedi -- olctugu sey daraltildi. */
$code = preg_replace(['~/\*.*?\*/~su', '~^\s*//.*$~m'], '', $step);

$t('AYNI jeton ureticisi (forgot.php ile)', substr_count($code, 'auth_reset_begin(') === 1);
$t('ikinci bir jeton yolu YOK',             !str_contains($code, 'random_bytes'));
$t('YENI mektubu cagiriyor',                str_contains($step, 'vestra_account_ready_text('));
$t('sifirlama mektubunu cagirmiyor',        !str_contains($step, 'vestra_reset_text('));
$t('deploy inmemisse DURUYOR',              str_contains($step, "function_exists('vestra_account_ready_text')"));
$t('gonderim FALSE ise is KIRMIZI',         str_contains($step, 'vestra_send_mail FALSE dondu'));

/* KURU KOSU JETON URETMEZ: uretseydi musterinin elindeki canli linki
   gecersiz kilar ve bir onizleme kayda YAZARDI. Olcum konumsal ve
   dusebilir: minter, kuru kosunun cikisindan SONRA gelmeli. */
$pDry  = strpos($step, 'KURU KOSU -- jeton uretilmedi');
$pMint = strpos($step, 'auth_reset_begin(');
$t('kuru kosu cikisi kaynakta VAR', $pDry !== false);
$t('jeton ureticisi kuru kosu cikisindan SONRA', $pDry !== false && $pMint !== false && $pMint > $pDry);

/* Hesabin KAYITLI dili: bu mektup operatorun isteginden doguyor, musterinin
   kendi isteginden degil (vestra_reset_text'in kendi notu). */
$t('dil hesaptan okunuyor', str_contains($code, 'vestra_user_lang($acc)'));
$t('istegin dili KULLANILMIYOR', !preg_match('/\bvlang\(\)/', $code));

/* TAM 1 eslesme: sifirda kimseye gitmez, birden fazlada YANLIS musteriye
   giderdi -- ve gonderilmis bir mektup geri alinamaz. */
$t('TAM 1 eslesme sarti var', str_contains($step, 'count($hits) !== 1'));
$t('ID TAM esitlikle araniyor', str_contains($step, "mb_strtolower((string)(\$a['id'] ?? '')) === \$who"));

/* KUTUK HERKESE ACIK: link, jeton ve govde basilmamali; adres maskeli. */
$prints = [];
foreach (explode("\n", $step) as $line) {
    $s = trim($line);
    if (str_starts_with($s, 'printf(') || str_starts_with($s, 'echo ') || str_starts_with($s, 'fwrite(STDERR,')) $prints[] = $s;
}
$t('basim satirlari bulundu (olcum gecerli)', count($prints) >= 8);
$leakLink = $leakTok = $leakBody = $leakMail = [];
foreach ($prints as $s) {
    /* Degeri DEGIL, uzunlugunu/aramayi basan kullanimlari ayikla. */
    $clean = str_replace(['mb_strlen($b)', 'auth_find($mail)', '$mask($mail)', '$mask((string)($a[\'email\'] ?? \'\'))'], '', $s);
    if (str_contains($clean, '$link'))        $leakLink[] = $s;
    if (str_contains($clean, 'reset_token'))  $leakTok[]  = $s;
    if (preg_match('/\$b\b/', $clean))        $leakBody[] = $s;
    if (str_contains($clean, '$mail'))        $leakMail[] = $s;
}
$t('LINK hicbir basimda YOK',   $leakLink === []);
$t('JETON hicbir basimda YOK',  $leakTok  === []);
$t('GOVDE hicbir basimda YOK',  $leakBody === []);
$t('ADRES maskesiz basilmiyor', $leakMail === []);
foreach ([['link',$leakLink],['jeton',$leakTok],['govde',$leakBody],['adres',$leakMail]] as [$w,$L])
    foreach ($L as $s) echo "        SIZINTI ($w): ".substr($s, 0, 110)."\n";
$t('maskeleyici tanimli', str_contains($step, "preg_replace('/^(.).*(@.*)\$/', '\$1***\$2'"));

/* Girdi de cikti kadar acik: adres bir GIRDI olarak da gecmemeli. */
$t('girdi hesap ID (adres degil)', str_contains($step, 'PS_WHO:   ${{ github.event.inputs.issue_ref }}'));

printf("\naccount_ready_letter_test: %d iddia gecti, %d KIRMIZI\n", $ok, $bad);
exit($bad ? 1 : 0);
