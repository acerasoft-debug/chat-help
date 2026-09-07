<?php
/* TALEP ("Open dispute") — operator, 5 Eyl 2026: "dispute yaziyor ama böyle bir
 * dispute yeri yok görünmüyor".
 *
 * BU TESTIN ASIL ISI: METIN ILE KODUN AYNI SEYI SOYLEDIGINI TUTMAK. Hata zaten
 * dugmenin eksikligi degildi; SSS, order-confirm, help, membership, index ve
 * buyer sayfalari BES ayri yerde var olmayan bir dugmeyi tarif ediyordu ve
 * `disputed` bayragi TUM depoda okunuyor ama hicbir yerde yazilmiyordu. Yani
 * metin bir tarafa, kod baska tarafa gitmisti ve hicbir test bunu tutmuyordu.
 *
 * Ikinci is: PARANIN GERCEKTEN TUTULDUGUNU tutmak. SSS returns/14 birebir
 * "talep acikken para birakilmaz" diyor; bu tek cumle escrow supurucusunda bir
 * satira bagli ve o satir olmazsa dugme kozmetik olurdu.
 */
$root = __DIR__ . '/../vestra';
require_once $root . '/inc/claims.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};
$src = fn(string $f) => (string)@file_get_contents(__DIR__ . '/../vestra/' . $f);

echo "== 1. Metnin vaat ettigi dugme GERCEKTEN var ==\n";
/* Bu satirlar bozulursa metin yine "Open dispute'a basin" der, ekranda hicbir
   sey olmaz -- duzeltilen hatanin ta kendisi. */
$t('sipariş detayinda talep karti', str_contains($src('inc/orders.php'), "vestra_claim_state(\$ref, \$statusEntry)"));
$t('form "Open dispute" yaziyor',   str_contains($src('inc/claims.php'), "\$tr('Open dispute')"));
$t('buyer.php open_claim isliyor',  str_contains($src('buyer.php'), "==='open_claim'"));
$t('form _action=open_claim gonderiyor', str_contains($src('inc/claims.php'), 'value="open_claim"'));

echo "\n== 2. `disputed` bayragi artik YAZILIYOR ==\n";
/* admin.php:3678 bu bayragi okuyor ve bugune kadar hicbir yerde yazilmadigi
   icin o uyari satiri hic gorunemezdi. */
$t('talep acilinca escrow isaretleniyor', str_contains($src('inc/claims.php'), "'disputed'=>true"));
$t('talep kapaninca temizleniyor',        str_contains($src('inc/claims.php'), "'disputed'=>false"));
$t('admin hala ayni bayragi okuyor',      str_contains($src('admin.php'), "\$er['disputed']"));

echo "\n== 3. ACIK TALEP PARAYI TUTUYOR (SSS returns/14) ==\n";
$esc = $src('inc/escrow.php');
$t('supurucu talebi soruyor', str_contains($esc, 'vestra_claim_is_open($ref)'));
/* Kontrol, sure hesabindan ONCE olmali: sonra gelseydi suresi dolmus bir
   sipariş talep acikken de birakilirdi. */
$posClaim = strpos($esc, 'vestra_claim_is_open($ref)');
$posDead  = strpos($esc, '$deadline = max(');
$t('kontrol sure hesabindan ONCE', $posClaim !== false && $posDead !== false && $posClaim < $posDead);

echo "\n== 4. vestra_claim_state() — tek karar noktasi ==\n";
$now = 1757000000;                       // sabit "simdi": test saate bagli olmasin
$day = 86400;
$t('odenmemis sipariste talep yok',   vestra_claim_state('X', ['status'=>'pending'], $now)['phase'] === 'na');
$t('iptal edilmis sipariste yok',     vestra_claim_state('X', ['status'=>'cancelled'], $now)['phase'] === 'na');
$t('odenmis sipariste acilabilir',    vestra_claim_state('X', ['status'=>'paid'], $now)['phase'] === 'open');
/* Teslim edilmemis siparişte "mal gelmedi" talebinin suresi teslimattan
   sayilamaz -- SSS: "teslim edilmemede, kararlastirilan teslim tarihine kadar". */
$sh = vestra_claim_state('X', ['status'=>'shipped'], $now);
$t('yolda: acik, son tarih YOK',      $sh['phase'] === 'open' && $sh['deadline'] === null);

$d1 = date('c', $now - 1 * $day);
$st1 = vestra_claim_state('X', ['status'=>'delivered', 'delivered_at'=>$d1], $now);
$t('teslimden 1 gun sonra acik',      $st1['phase'] === 'open');
$t('son tarih = teslim + CLAIM_DAYS', $st1['deadline'] === strtotime($d1) + VESTRA_CLAIM_DAYS * $day);
$t('kalan gun dogru',                 $st1['days_left'] === VESTRA_CLAIM_DAYS - 1);

$late = date('c', $now - (VESTRA_CLAIM_DAYS + 1) * $day);
$t('sure dolunca late',               vestra_claim_state('X', ['status'=>'delivered', 'delivered_at'=>$late], $now)['phase'] === 'late');
/* Tam sinirda ACIK kalmali: "3 gun icinde" 3. gunu de kapsar. */
$edge = date('c', $now - VESTRA_CLAIM_DAYS * $day + 60);
$t('sinirda hala acik',               vestra_claim_state('X', ['status'=>'delivered', 'delivered_at'=>$edge], $now)['phase'] === 'open');
/* Alici teslim aldigini onaylamis olabilir ama kutuyu sonra acar. */
$t('completed sipariste de acilabilir', vestra_claim_state('X', ['status'=>'completed'], $now)['phase'] === 'open');

echo "\n== 5. Gun sayisi TEK kaynaktan ==\n";
/* KURAL 11: metin ile sabit birlikte kilitli. Kodda ikinci bir "3" olsaydi
   sabiti degistiren kisi bu yolu kacirirdi. */
$t('VESTRA_CLAIM_DAYS kullaniliyor', str_contains($src('inc/claims.php'), 'VESTRA_CLAIM_DAYS'));
$t('claims.php\'de gomulu gun sayisi yok',
   preg_match('~(?<![A-Za-z0-9_])3\s*\*\s*86400~', $src('inc/claims.php')) !== 1);

echo "\n== 6. Sebepler SSS ile ayni DORT ==\n";
$r = vestra_claim_reasons();
$t('4 sebep', count($r) === 4);
$faq = $src('inc/faq.php');
foreach (['non-delivery', 'not-as-described', 'quality issue', 'counterfeit'] as $needle) {
    $t("SSS disputes/1 '$needle' sayiyor", stripos($faq, $needle) !== false);
}

echo "\n== 7. Girdi dogrulamasi ==\n";
/* Gecersiz sebep/bos aciklama KAYITTAN ONCE durmali (KURAL 4'un ayni ilkesi). */
$t('gecersiz sebep reddediliyor', vestra_claim_open_new('NOPE', 'made_up', 'x', [])['error'] === 'reason');
$t('bos aciklama reddediliyor',   vestra_claim_open_new('NOPE', 'quality', '  ', [])['error'] === 'detail');
$t('her ret icin metin var',      vestra_claim_error_text('reason') !== '' && vestra_claim_error_text('window') !== ''
                                   && vestra_claim_error_text('detail') !== '' && vestra_claim_error_text('exists') !== '');

echo "\n== 8. Kanit dosyalari acikta degil ==\n";
$t('dizin .htaccess yaziyor',   str_contains($src('inc/claims.php'), 'Deny from all'));
$t('dosya kurallari auth\'tan', str_contains($src('inc/claims.php'), 'auth_doc_file_check')
                                 && str_contains($src('inc/claims.php'), 'auth_doc_max_bytes'));
$t('admin indirme yolu var',    str_contains($src('admin.php'), "isset(\$_GET['dl_claim'])"));
/* Kayitli ama diskte olmayan dosya gosterilmemeli. */
$t('diskte var mi kontrolu',    str_contains($src('inc/claims.php'), 'files_on_disk'));

echo "\n== 9. 'delivered' zincirde ve etiketli ==\n";
/* Bu is sirasinda cikti: satici teslimati isaretleyebiliyor, escrow sayaci ve
   alicinin talep suresi ONA bagli, ama 'delivered' ne durum etiketinde ne de
   adim zincirinde vardi. Sonuc: mali eline gecmis alici sipariste "Awaiting
   payment" goruyordu -- ekranin en yanlis oldugu an, dogru olmasinin en cok
   gerektigi andi. Ayni tuzak 'cancelled' icin kodda zaten yaziliydi. */
require_once $root . '/inc/orders.php';
$t("'delivered' etiketi 'Awaiting payment' DEGIL",
   vestra_order_status_label('delivered', true) !== 'Awaiting payment');
$t("'delivered' etiketi 'Delivered'", vestra_order_status_label('delivered', true) === 'Delivered');
$t("'delivered' adim zincirinde",     in_array('delivered', VESTRA_ORDER_STEPS, true));
$t("'delivered' shipped ile completed ARASINDA",
   array_search('delivered', VESTRA_ORDER_STEPS, true) > array_search('shipped', VESTRA_ORDER_STEPS, true)
   && array_search('delivered', VESTRA_ORDER_STEPS, true) < array_search('completed', VESTRA_ORDER_STEPS, true));
/* Zincirde olmasi, operatorun panelden secebilmesini de sagliyor. */
$t("operator 'delivered' secebiliyor", in_array('delivered', vestra_order_settable_statuses(), true));
/* Zaman cizelgesi teslim edilmis siparişi ILK noktada gostermemeli. */
$tl = vestra_order_timeline_html('delivered', false);
$t('zaman cizelgesi ilk adimda takilmiyor',
   preg_match('~otstep now~', $tl) === 1 && strpos($tl, 'otstep now') > strpos($tl, 'Shipped'));

echo "\n== 10. Metinler 8 dilde ==\n";
$miss = [];
foreach (['de','fr','es','it','pt','ru','ar','ja'] as $lg) {
    $d = @include $root . "/inc/lang/$lg.php";
    foreach (['Report a problem', 'Open dispute', 'Claim reference', 'Non-delivery', 'Counterfeit',
              'The claim window for this delivery has closed.'] as $k) {
        if (!is_array($d) || trim((string)($d[$k] ?? '')) === '') { $miss[] = "$lg/$k"; }
    }
}
$t('talep metinleri 8 dilde' . ($miss ? ': ' . implode(', ', array_slice($miss, 0, 5)) . ' EKSIK' : ''), $miss === []);

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
