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
$t('sipariş detayinda talep bileseni', str_contains($src('inc/orders.php'), "vestra_claim_widget(\$ref, \$statusEntry, \$formHref)"));
/* Operator (6 Eyl 2026): "cok belirgin olmasin" -- <details> ile katlanmis
   sessiz bir baglanti; "Open dispute" yazan buyuk bir dugme DEGIL. */
$t('bilesen katlanmis <details>',   str_contains($src('inc/claims.php'), '<details class="claimdis"><summary>'));
$t('baglanti metni "I have a problem with this order"', str_contains($src('inc/claims.php'), "\$tr('I have a problem with this order')"));
$t('buyer.php open_claim isliyor',  str_contains($src('buyer.php'), "==='open_claim'"));
$t('form _action=open_claim gonderiyor', str_contains($src('inc/claims.php'), 'value="open_claim"'));
/* Ilerleyen acilis: GIZLEYEN JS'tir. JS yoksa ikinci adim acik kalir ve form
   yine gonderilir -- ':has()' gibi tarayiciya bagli bir CSS numarasi degil. */
$t('ikinci adimi JS gizliyor (JS yoksa acik)', str_contains($src('inc/claims.php'), 's.hidden=true;') && !str_contains($src('inc/claims.php'), 'claimstep2" hidden'));

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
$posDead  = strpos($esc, '$deadline = escrow_release_deadline($dts)');
$t('kontrol sure hesabindan ONCE', $posClaim !== false && $posDead !== false && $posClaim < $posDead);
/* ALICININ "TESLIM ALDIM" DUGMESI DE KAPALI: onay escrow'u serbest birakir,
   talep onu tutar; ikisi ayni anda dogru olamaz. Sunucu tarafi reddediyor
   (gizli dugme kapi degil) ve detay/liste gorunumleri dugmeyi basmiyor. */
$t('confirm_receipt talep acikken REDDEDIYOR', str_contains($src('buyer.php'), "claim_err=hold"));
$t('detayda onay dugmesi talep acikken yok',   str_contains($src('inc/orders.php'), "&& !vestra_claim_is_open(\$ref)) {"));
$t('listede onay dugmesi talep acikken yok',   str_contains($src('buyer.php'), "&& !\$claimOpen){"));
$t("'hold' icin metin var",                     vestra_claim_error_text('hold') !== '' && vestra_claim_error_text('hold') !== 'hold');

echo "\n== 3b. Son tarih TEK kaynaktan (escrow_release_deadline) ==\n";
/* Saticinin "teslim edildi" mektubu kendi basina "2 is gunu" hesapliyordu;
   KURAL 11 sureyi max(2 is gunu, 3 takvim gunu) yapinca mektup geride kaldi.
   Simdi supurucu ve mektup ayni fonksiyonu cagirir. */
$t('supurucu escrow_release_deadline kullaniyor', str_contains($esc, '$deadline = escrow_release_deadline($dts)'));
$t('teslim mektubu da ayni fonksiyonu kullaniyor', str_contains($src('seller.php'), 'escrow_release_deadline(time())'));
$t('mektupta kendi basina "2 is gunu" hesabi kalmadi', !str_contains($src('seller.php'), 'vestra_business_days_after(time(), 2)'));
$t('mektup dispute yolunu soyluyor', str_contains($src('seller.php'), 'I have a problem with this order'));
$mon = strtotime('2026-09-07 10:00:00');                       // Pazartesi
$t('Pzt teslimat -> Prs (3 is gunu), Crs degil',   date('N', escrow_release_deadline($mon)) === '4');
$fri2 = strtotime('2026-09-11 10:00:00');                      // Cuma
$t('Cum teslimat -> Crs (3 is gunu, hafta sonu atlanir)', date('N', escrow_release_deadline($fri2)) === '3');
$t('para talep penceresinden ONCE cikmaz',          escrow_release_deadline($fri2) >= vestra_claim_deadline($fri2));

echo "\n== 4. vestra_claim_state() — tek karar noktasi ==\n";
$now = 1757000000;                       // sabit "simdi": test saate bagli olmasin
$day = 86400;
/* Operator (6 Eyl 2026): "musteri HER siparisinde bunu yapabilmeli" -- odenmemis
   siparişte de (yanlis adet, adres). Yalnizca iptal disarida. */
$t('odenmemis sipariste de acilabilir', vestra_claim_state('X', ['status'=>'pending'], $now)['phase'] === 'open');
$t('kaydi hic olmayan sipariste de',    vestra_claim_state('X', [], $now)['phase'] === 'open');
$t('iptal edilmis sipariste yok',       vestra_claim_state('X', ['status'=>'cancelled'], $now)['phase'] === 'na');
$t('odenmis sipariste acilabilir',    vestra_claim_state('X', ['status'=>'paid'], $now)['phase'] === 'open');
/* Teslim edilmemis siparişte "mal gelmedi" talebinin suresi teslimattan
   sayilamaz -- SSS: "teslim edilmemede, kararlastirilan teslim tarihine kadar". */
$sh = vestra_claim_state('X', ['status'=>'shipped'], $now);
$t('yolda: acik, son tarih YOK',      $sh['phase'] === 'open' && $sh['deadline'] === null);

/* IS GUNU (operator, 6 Eyl 2026: "3 gunde hafta sonlari sayilmasin").
   Sabit "simdi" bir Pazartesi 10:00 -- hafta sonu davranisi gorunsun diye. */
$mon10 = strtotime('2026-09-07 10:00:00');                       // Pazartesi
$d1 = date('c', $mon10 - 1 * $day);                              // Pazar teslimat
$st1 = vestra_claim_state('X', ['status'=>'delivered', 'delivered_at'=>$d1], $mon10);
$t('teslimden 1 gun sonra acik',      $st1['phase'] === 'open');
$t('son tarih = vestra_claim_deadline (TEK kaynak)', $st1['deadline'] === vestra_claim_deadline(strtotime($d1)));
/* Cuma teslimat: Cmt/Paz sayilmaz -> Pzt, Sal, Crs -> Carsamba gece yarisi. */
$fri = strtotime('2026-09-04 15:00:00');
$t('Cuma teslimat -> Carsamba (hafta sonu sayilmaz)', date('D', vestra_claim_deadline($fri)) === 'Wed');
$t('son tarih gun SONUNA kadar (23:59)',           date('H:i', vestra_claim_deadline($fri)) === '23:59');
/* Eski takvim kurali Cuma -> Pazartesi derdi; yeni kural Pazartesi'de hala ACIK. */
$t('Cuma teslimat, Pazartesi hala acik',
   vestra_claim_state('X', ['status'=>'delivered', 'delivered_at'=>date('c', $fri)], $mon10)['phase'] === 'open');
$t('Cuma teslimat, Persembe sabahi late',
   vestra_claim_state('X', ['status'=>'delivered', 'delivered_at'=>date('c', $fri)], strtotime('2026-09-10 08:00:00'))['phase'] === 'late');
/* Tam sinirda ACIK kalmali: "3 is gunu icinde" ucuncu gunun tamamini kapsar. */
$t('Carsamba 23:00 hala acik',
   vestra_claim_state('X', ['status'=>'delivered', 'delivered_at'=>date('c', $fri)], strtotime('2026-09-09 23:00:00'))['phase'] === 'open');
/* Alici teslim aldigini onaylamis olabilir ama kutuyu sonra acar. */
$t('completed sipariste de acilabilir', vestra_claim_state('X', ['status'=>'completed'], $now)['phase'] === 'open');

echo "\n== 5. Gun sayisi TEK kaynaktan ==\n";
/* KURAL 11: metin ile sabit birlikte kilitli. Kodda ikinci bir "3" olsaydi
   sabiti degistiren kisi bu yolu kacirirdi. */
$t('VESTRA_CLAIM_DAYS kullaniliyor', str_contains($src('inc/claims.php'), 'VESTRA_CLAIM_DAYS'));
$t('claims.php\'de gomulu gun sayisi yok',
   preg_match('~(?<![A-Za-z0-9_])3\s*\*\s*86400~', $src('inc/claims.php')) !== 1);

echo "\n== 6. Sebepler: SSS'nin DORDU + 'baska bir sey' ==\n";
$r = vestra_claim_reasons();
$t('SSS disputes/1 dort sebebi var', isset($r['non_delivery'], $r['not_as_described'], $r['quality'], $r['counterfeit']));
/* 'other' operator karari ("her sipariste"): yola cikmamis sipariste de sorun
   olur ve bir yeri olmali. */
$t("'other' var (her sipariste)",   isset($r['other']));
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

echo "\n== 9b. SSS returns/9: satici gorur, sonuc bildirilir, dosya tek yerde ==\n";
$t('satici blogu sipariş detayinda',     str_contains($src('inc/orders.php'), 'vestra_claim_seller_block($ref)'));
$t('satici kaniti indirebiliyor (sahiplik kontrollu)',
   str_contains($src('seller.php'), "isset(\$_GET['dl_claim'])") && str_contains($src('seller.php'), 'vestra_order_has_seller_sku($row, $dcSkus)'));
$t('acilista bildirim tek yoldan',       str_contains($src('buyer.php'), "vestra_claim_notify('opened'"));
$t('kapanista bildirim AYNI yoldan',     str_contains($src('admin.php'), "vestra_claim_notify('resolved'"));
$t('sonuc alicinin bileseninde basiliyor', str_contains($src('inc/claims.php'), "\$tr('Outcome')"));
$t('sohbet ipligine kart (kind=claim)',  str_contains($src('inc/claims.php'), "'kind'=>'claim'") && str_contains($src('inc/messages.php'), "\$kind === 'claim'"));
/* Sonuc metni ZORUNLU: bos sonuc = alicinin hic okumayacagi bir mektup. */
$t('bos sonucla kapatilamaz',            vestra_claim_resolve('NOPE', '   ')['error'] === 'detail');
$t('mektup sablonlari var',              function_exists('vestra_tpl_claim_received') || str_contains($src('inc/email_templates.php'), 'function vestra_tpl_claim_received'));
$t('sohbet karti paid/delivered da taniyor', str_contains($src('inc/messages.php'), "'delivered' => ['ctr'") && str_contains($src('inc/messages.php'), "'paid'      => ['ok'"));

echo "\n== 9c. Bekleyen talep SESSIZ kalmaz (cron) ==\n";
$t('cron_claims.php var',                 is_file(__DIR__.'/../vestra/cron_claims.php'));
$t('cron acik listeyi claims.php\'den okuyor', str_contains($src('cron_claims.php'), 'vestra_claims_open()'));
$t('sure VESTRA_CLAIM_REVIEW_BDAYS\'ten',   str_contains($src('cron_claims.php'), 'VESTRA_CLAIM_REVIEW_BDAYS') && !preg_match('~business_days_after\(\$opened,\s*2\)~', $src('cron_claims.php')));
$t('gecikmis yoksa mektup GITMEZ',         str_contains($src('cron_claims.php'), "if (!\$due) {"));
$deploy = (string)@file_get_contents(__DIR__.'/../.github/workflows/deploy-vestra.yml');
$t('deploy crontab\'a kuruyor',            str_contains($deploy, 'cron_claims.php >> $HOME/vestra_sweep.log'));
$t('deploy kanaryasi kuru kosuyor',        str_contains($deploy, 'php cron_claims.php --dry-run'));
$t('admin listesi acik talebi isaretliyor', str_contains($src('admin.php'), 'vestra_claims_open()') && str_contains($src('admin.php'), 'claim open'));

echo "\n== 10. Metinler 8 dilde ==\n";
$miss = [];
foreach (['de','fr','es','it','pt','ru','ar','ja'] as $lg) {
    $d = @include $root . "/inc/lang/$lg.php";
    foreach (['I have a problem with this order', 'Something else', 'Claim open', 'Outcome', 'Non-delivery', 'Counterfeit',
              'The claim window for this delivery has closed.',
              'A claim is open on this order — receipt cannot be confirmed until it is resolved.'] as $k) {
        if (!is_array($d) || trim((string)($d[$k] ?? '')) === '') { $miss[] = "$lg/$k"; }
    }
}
$t('talep metinleri 8 dilde' . ($miss ? ': ' . implode(', ', array_slice($miss, 0, 5)) . ' EKSIK' : ''), $miss === []);

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
