<?php
/* SATICIYA ÖDEME — "BAŞARILI SİPARİŞ" (operatör, 19 Eyl 2026: *"siparişleri
 * ben alıcam ve başarılı olan siparişleri satıcılara ödeyeceğim, bunun
 * yapılması için hukuki bir sistem yap"*).
 *
 * Tutulan ilkeler:
 *   - Kural TEK yerde HESAPLANIYOR (`vestra_seller_settlement`); hukuk metni ve
 *     panel onu ANLATIYOR, yeniden tanımlamıyor.
 *   - Rakamlar sabitten: VESTRA_CLAIM_DAYS + VESTRA_SELLER_SETTLEMENT_DAYS.
 *     SSS metni yer tutucu taşıyor ve `vestra_faq_fill` çözüyor — dokuz dilin
 *     metnine rakam gömmek KURAL 6'nın escrow tavanı hatasının dokuz katı olurdu.
 *   - Hukuk maddesi BEŞ dile birden girdi: çeviri varsa İngilizce metin HİÇ
 *     okunmuyor (KURAL 5q), yani yalnız İngilizceye eklenen bir madde
 *     de/fr/it/es'te görünmez.
 *   - İKİ YÖN: ödenebilir hâller kadar ÖDENMEMESİ gereken hâller de.
 */
error_reporting(E_ALL & ~E_DEPRECATED);
$root = __DIR__ . '/../vestra';
require_once $root . '/inc/products.php';
require_once $root . '/inc/orders.php';
require_once $root . '/inc/escrow.php';
require_once $root . '/inc/faq.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};
$src = fn(string $f) => (string)@file_get_contents(__DIR__ . '/../' . $f);
/* Kayıtlı bir siparişe DEĞİL, doğrudan duruma karşı ölçüyoruz: fonksiyon saf
   olduğu için kum havuzuna dosya yazmaya gerek yok. */
$S = fn(array $entry) => vestra_seller_settlement('PROBE-' . bin2hex(random_bytes(3)), $entry);
$old = date('c', strtotime('-10 days'));
$fresh = date('c');

echo "== 1. ÖDENMEZ: dört koşuldan biri eksikse ==\n";
$t('ödenmemiş sipariş',        $S(['status' => 'pending'])['why'] === 'unpaid');
$t('ödendi ama teslim yok',    $S(['status' => 'paid'])['why'] === 'not_delivered');
$t('kargoda ama teslim değil', $S(['status' => 'shipped'])['why'] === 'not_delivered');
$t('teslim TARİHİ yoksa durur',$S(['status' => 'delivered'])['why'] === 'no_delivery_date');
$t('talep penceresi sürüyor',  $S(['status' => 'delivered', 'delivered_at' => $fresh])['why'] === 'claim_window');
/* İPTAL: zincirde bilerek yok, yani ödenmiş tarafa hiç geçemiyor. */
$t('iptal edilen ödenmez',     $S(['status' => 'cancelled', 'delivered_at' => $old])['payable'] === false);
foreach (['pending','paid','preparing','to_vestra','shipped','cancelled'] as $st) {
    if ($S(['status' => $st, 'delivered_at' => $old])['payable']) { $t("$st ödenebilir görünüyor", false); }
}
$t('hiçbir erken aşama ödenebilir değil', true);

echo "\n== 2. ÖDENİR: dördü de sağlandığında ==\n";
$r = $S(['status' => 'delivered', 'delivered_at' => $old]);
$t('teslim + pencere kapalı → ödenebilir', $r['payable'] === true && $r['why'] === 'payable');
$t('son ödeme tarihi yazılı',              $r['due'] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $r['due']) === 1);
$t('talep penceresi tarihi yazılı',        $r['claim_until'] !== '');
$t('completed de ödenebilir',              $S(['status' => 'completed', 'delivered_at' => $old])['payable'] === true);
/* Tarih UYDURULMUYOR: geçmişteki damga da okunuyor, `updated_at` DEĞİL. */
$h = $S(['status' => 'delivered', 'updated_at' => $fresh,
         'history' => [['status' => 'delivered', 'at' => $old]]]);
$t('teslim tarihi geçmişten okunuyor', $h['payable'] === true);
$t('updated_at teslim tarihi SAYILMIYOR',
    !str_contains(substr($src('vestra/inc/orders.php'),
        strpos($src('vestra/inc/orders.php'), 'function vestra_seller_settlement'), 3000), "updated_at'"));

echo "\n== 3. Süre SABİTTEN, elle hesaplanmıyor ==\n";
$t('talep penceresi sabiti var',  defined('VESTRA_CLAIM_DAYS') && VESTRA_CLAIM_DAYS > 0);
$t('ödeme süresi sabiti var',     defined('VESTRA_SELLER_SETTLEMENT_DAYS') && VESTRA_SELLER_SETTLEMENT_DAYS > 0);
$o = $src('vestra/inc/orders.php');
$fn = substr($o, strpos($o, 'function vestra_seller_settlement'));
$fn = substr($fn, 0, strpos($fn, "\n}\n") + 2);
$t('pencere kendi hesaplanmıyor', str_contains($fn, 'vestra_claim_deadline('));
$t('süre sabitten okunuyor',      str_contains($fn, 'VESTRA_SELLER_SETTLEMENT_DAYS'));
$t('İŞ GÜNÜ sayılıyor',           str_contains($fn, 'vestra_business_days_after('));
$t('ödeme kapısı yeniden tanımlanmıyor', str_contains($fn, 'vestra_order_payment_settled('));
$t('zincir elle listelenmiyor',   str_contains($fn, 'VESTRA_ORDER_STEPS'));
/* Süre gerçekten UYGULANIYOR: pencere kapandıktan sonra son ödeme günü
   tam olarak N iş günü ileride. Kaynakta sabiti görmek ölçüm değil. */
$dts = strtotime($old);
$want = date('Y-m-d', vestra_business_days_after(vestra_claim_deadline($dts), VESTRA_SELLER_SETTLEMENT_DAYS));
$t('son ödeme günü sabitle birebir', $r['due'] === $want);

echo "\n== 4. SSS: rakam metne gömülü DEĞİL, yer tutucu çözülüyor ==\n";
$t('yer tutucu çözücüsü var',  function_exists('vestra_faq_fill'));
$t('talep günü çözülüyor',     vestra_faq_fill('{claim_days}') === (string)VESTRA_CLAIM_DAYS);
$t('ödeme günü çözülüyor',     vestra_faq_fill('{settle_days}') === (string)VESTRA_SELLER_SETTLEMENT_DAYS);
$t('komisyon çözülüyor',       vestra_faq_fill('{commission}') === vestra_commission_pct_label('en'));
$t('tanınmayan yer tutucu KORUNUYOR', vestra_faq_fill('{unknown_token}') === '{unknown_token}');
$faqSrc = $src('vestra/inc/faq.php');
$t('İngilizce yol da çözücüden geçiyor', str_contains($faqSrc, "if(\$l==='en') return vestra_faq_resolve(\$en, \$l);"));
/* CANLI ÇİZİM YAKALADI, kaynak taraması değil: `vestra/faq.php` inc/faq.php'yi
   head.php'den ÖNCE yüklüyor, yani komisyon etiketi o anda tanımsızdı ve
   `function_exists` yedeği SESSİZCE boş dize dönüyordu — sayfa rakamsız bir
   "%" basıyordu. Dosya artık bağımlılığını kendi yüklüyor (KURAL 15) ve
   yedek KALDIRILDI: yüklenmemişse sessizce geçmek yerine ölmeli. */
$t('faq.php kendi bağımlılığını yüklüyor', str_contains($faqSrc, "require_once __DIR__.'/products.php';"));
$t('komisyonda sessiz yedek YOK',          !str_contains($faqSrc, "function_exists('vestra_commission_pct_label')"));
/* AYRI SÜREÇ: sayfanın gerçek yükleme sırası (önce inc/faq.php, sonra
   products.php) taklit ediliyor — tek süreçte ölçmek bu kusuru göremezdi. */
$cmdFaq = 'php -r ' . escapeshellarg(
    'require "'.$root.'/inc/faq.php"; $f=vestra_faq();'
    . 'echo (string)preg_match("/[0-9]/u", $f["fees"]["items"][1]["a"]);');
$t('SSS tek başına yüklendiğinde de rakam basıyor', trim((string)shell_exec($cmdFaq)) === '1');
/* İki yön: metin yer tutucu TAŞIMALI (rakam gömülü değil) ve çıktıda
   çözülmemiş yer tutucu KALMAMALI. */
$rawEn = $src('vestra/inc/faq.php');
$t('İngilizce metin yer tutucu taşıyor', substr_count($rawEn, '{settle_days}') >= 2 && substr_count($rawEn, '{claim_days}') >= 2);
$out = json_encode(vestra_faq(), JSON_UNESCAPED_UNICODE);
$t('çıktıda çözülmemiş yer tutucu yok', preg_match('/\{(claim_days|settle_days|commission)\}/', $out) === 0);
$t('eski kademeli komisyon metni gitti', preg_match('/3\.2%|2\.8%|Starter|Elite/', $out) === 0);
$t('SSS cevaplarında HTML yok',          preg_match('/<[a-z\/]|&[a-z]{2,8};/i', $out) === 0);

echo "\n== 5. Dokuz dilin metninde de rakam gömülü değil ==\n";
foreach (['de','fr','it','es','pt','ru','ar','ja'] as $l) {
    $raw = $src("vestra/inc/faq/$l.php");
    $t("$l: yer tutucu taşıyor",  substr_count($raw, '{settle_days}') >= 2 && substr_count($raw, '{commission}') >= 3);
    $t("$l: yeni satıcı maddesi", substr_count($raw, '{claim_days}') >= 2);
}

echo "\n== 6. Hukuk metni: madde BEŞ dile birden girdi ==\n";
/* Dil başına AYRI PHP SÜRECİ: vlang() ilk çağrıda sabitleniyor ve tek süreçte
   beş dili gezmek beşinin de "İngilizce" olduğunu ölçer (KURAL 11'in tuzağı;
   bu turda bir kez daha yaşandı). */
foreach (['en','de','fr','it','es'] as $l) {
    $cmd = 'php -r ' . escapeshellarg(
        '$_GET["lang"]="'.$l.'";'
        . 'require "'.$root.'/inc/products.php"; require "'.$root.'/inc/legal.php";'
        . '$L=vestra_legal();'
        . '$s=$L["seller"]["html"]; $p=$L["payments"]["html"]; $a=$L["aml"]["html"];'
        . 'echo json_encode(["lang"=>vlang(),'
        . '"seller_h"=>preg_match_all("/<h3>/",$s),'
        . '"sec9"=>(int)str_contains($s,"9."),'
        . '"settle"=>(int)(str_contains($s,(string)VESTRA_SELLER_SETTLEMENT_DAYS)),'
        . '"claim"=>(int)(str_contains($s,(string)VESTRA_CLAIM_DAYS)),'
        . '"pay_sec"=>preg_match_all("/<h3>/",$p),'
        . '"aml_exc"=>(int)(str_contains($a,"3c")),'
        . '"unresolved"=>preg_match_all("/\{\\\\$[a-zA-Z_]+\}/",$s.$p.$a)]);');
    $j = json_decode((string)shell_exec($cmd), true) ?: [];
    $t("$l: dil gerçekten yüklendi",     ($j['lang'] ?? '') === $l);
    $t("$l: Satıcı Sözleşmesi 9 bölüm",  (int)($j['seller_h'] ?? 0) === 9);
    $t("$l: §9 var",                     (int)($j['sec9'] ?? 0) === 1);
    $t("$l: ödeme süresi yazılı",        (int)($j['settle'] ?? 0) === 1);
    $t("$l: talep penceresi yazılı",     (int)($j['claim'] ?? 0) === 1);
    $t("$l: Ödemeler politikasında yeni bölüm", (int)($j['pay_sec'] ?? 0) >= 6);
    $t("$l: AML istisnası 3c'ye atıf",   (int)($j['aml_exc'] ?? 0) === 1);
    $t("$l: çözülmemiş değişken yok",    (int)($j['unresolved'] ?? 1) === 0);
}
/* Rakam hukuk metnine de GÖMÜLMÜYOR: kaynakta yer tutucu/değişken duruyor. */
$t('EN hukuk metni sabitten okuyor',  str_contains($src('vestra/inc/legal.php'), '{$setDays} business days'));
foreach (['de','fr','it','es'] as $l) {
    $t("$l hukuk metni sabitten okuyor", str_contains($src("vestra/inc/legal/$l.php"), '{$setDays}'));
}
$t('hukuk tarihi güncellendi', str_contains($src('vestra/inc/legal.php'), "'en' => '2026-09-19'"));

echo "\n== 7. Panel kuralı ANLATMIYOR, HESAPLATIYOR ==\n";
$adm = $src('vestra/admin.php');
$t('panel aynı gövdeyi çağırıyor', str_contains($adm, 'vestra_seller_settlement($viewRef)'));
$t('panel sabitleri basıyor',      str_contains($adm, 'VESTRA_SELLER_SETTLEMENT_DAYS ?> iş günü'));
$t('panelde elle gün sayısı yok',  !preg_match('/Satıcıya ödeme.{0,400}\b5 iş günü\b/su', $adm));

echo "\n".($fail ? "SONUÇ: {$fail} HATA, {$ok} ok\n" : "SONUÇ: hepsi geçti ({$ok})\n");
exit($fail ? 1 : 0);
