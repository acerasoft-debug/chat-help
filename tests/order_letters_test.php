<?php
/* Siparis mektuplari (2 Eyl 2026):
 *   vestra_tpl_order_tracking_soon -- "ne zaman gonderiyorsunuz?" diyen aliciya
 *     "takip numarasi birkac gune girilecek, sabriniz icin tesekkurler" cevabi
 *     (operator talimati, O39419 / INV-2026-1009).
 *   vestra_tpl_order_shipped -- satici VE admin panelinin ortak "gonderildi"
 *     mektubu; admin yolu eskiden hic mektup gondermiyordu.
 * Tutulanlar: Ingilizce, ref/fatura no govdede, tarih ve kargo firmasi
 * UYDURULMAZ, imza secimi, hesap varsa/yoksa farkli cumle, "release payment"
 * (escrow dili) havale siparisine yazilmaz.
 */
$src = file_get_contents(__DIR__.'/../vestra/inc/email_templates.php');
foreach (['vestra_display_name', 'vestra_tpl_order_tracking_soon', 'vestra_tpl_order_shipped',
          'vestra_tpl_order_address_request', 'vestra_tpl_order_invoice_soon',
          'vestra_tpl_claim_received', 'vestra_tpl_claim_resolved',
          'vestra_tpl_order_payment_notice'] as $fn) {
    if (!preg_match('/^function '.preg_quote($fn,'/').'\(.*?^}/ms', $src, $m)) { echo "HATA: $fn bulunamadi\n"; exit(1); }
    eval($m[0]);
}

$ok=0; $fail=0;
$t = function (string $n, bool $c) use (&$ok,&$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};
$noTurkish = fn(string $s) => !preg_match('/[şğıİçöüŞĞÇÖÜ]/u', $s);

echo "-- tracking_soon: hesapsiz alici, sirket imzasi --\n";
[$s, $b, $o] = vestra_tpl_order_tracking_soon('samuel kozak', 'O39419', 'INV-2026-1009', false, '');
$t('konu ref ve fatura no tasir',                 str_contains($s, 'O39419') && str_contains($s, 'INV-2026-1009'));
$t('hitap bas harfli ("Dear Samuel Kozak")',      str_contains($b, 'Dear Samuel Kozak,'));
$t('govde ref + fatura no',                       str_contains($b, 'order O39419 (invoice INV-2026-1009)'));
$t('"tracking number" + "next few days"',         str_contains($b, 'tracking number') && str_contains($b, 'within the next few days'));
$t('sabir icin tesekkur',                         str_contains($b, 'thank you for your patience'));
$t('tarih/kargo UYDURULMAZ (gun-ay-yil yok)',     !preg_match('/\b\d{1,2}\s+(Sep|Oct|Aug|September|October)\b/i', $b) && !preg_match('/\b(DHL|UPS|FedEx)\b/', $b));
$t('sirket imzasi (persona yok)',                 str_contains($b, "VESTRA · Acerasoft LLC\n8 The Green"));
$t('hesap yok -> "VESTRA account" cumlesi yok',   !str_contains($b, 'VESTRA account'));
$t('hesap yok -> dugme yok',                      !isset($o['button']));
$t('bilgi kutusu: ref + fatura satiri',           count($o['rows']) === 2 && $o['rows'][1]['value'] === 'INV-2026-1009');
$t('Turkce karakter yok',                         $noTurkish($s) && $noTurkish($b));

echo "-- tracking_soon: hesapli alici, persona imzasi, fatura no yok --\n";
[$s2, $b2, $o2] = vestra_tpl_order_tracking_soon('Maison Test', 'O11111', '', true, 'Marco Bellini');
$t('fatura yoksa konu/govde parantezsiz',         !str_contains($s2, 'invoice') && !str_contains($b2, '(invoice'));
$t('hesap var -> Orders sekmesi cumlesi',         str_contains($b2, 'under Orders in your VESTRA account'));
$t('hesap var -> "View my order" dugmesi',        ($o2['button']['url'] ?? '') === 'https://vestrasales.com/buyer?tab=orders');
$t('persona imzalar, sirket blogu yok',           str_contains($b2, "Marco Bellini\nVESTRA") && !str_contains($b2, '8 The Green'));
$t('bos ad -> "Customer"',                        str_contains(vestra_tpl_order_tracking_soon('', 'O1')[1], 'Dear Customer,'));

echo "-- shipped --\n";
[$s3, $b3, $o3] = vestra_tpl_order_shipped('samuel kozak', 'O39419', '1Z999AA10123456784', false);
$t('konu ref tasir',                              str_contains($s3, 'O39419'));
$t('takip numarasi govdede',                      str_contains($b3, 'Tracking number: 1Z999AA10123456784'));
$t('takip numarasi kutuda ve kalin',              ($o3['rows'][1]['value'] ?? '') === '1Z999AA10123456784' && !empty($o3['rows'][1]['strong']));
$t('hesap yok -> panel linki yok, "reply" cumlesi', !str_contains($b3, 'buyer?tab=orders') && str_contains($b3, 'reply to this e-mail'));
$t('"release payment" (escrow dili) YOK',         !str_contains($b3, 'release payment'));
[$s4, $b4, $o4] = vestra_tpl_order_shipped('Maison Test', 'O11111', '', true);
$t('takip yoksa "Tracking number" satiri yok',    !str_contains($b4, 'Tracking number') && count($o4['rows']) === 1);
$t('hesap var -> onay + panel linki',             str_contains($b4, 'confirm receipt') && ($o4['button']['url'] ?? '') === 'https://vestrasales.com/buyer?tab=orders');
$t('Turkce karakter yok',                         $noTurkish($s3.$b3.$s4.$b4));

/* --- order_address: yeni siparise tesekkur + teslimat adresi (5 Eyl 2026) --- */
echo "\n== order_address ==\n";
[$s5, $b5, $o5] = vestra_tpl_order_address_request('LINCHAOWEI', 'VES-6B53D265', 4680.0, 'EUR', true, 'Marco Bellini');
$t('konu ref tasir',            str_contains($s5, 'VES-6B53D265'));
$t('tesekkur var',              stripos($b5, 'Thank you for your order') !== false);
$t('teslimat adresi isteniyor', stripos($b5, 'delivery address') !== false);
$t('Latin harf sarti yazili',   stripos($b5, 'Latin script') !== false);
$t('yerel yazim da isteniyor',  stripos($b5, 'another script') !== false);
$t('alici adi + telefon',       stripos($b5, 'consignee name') !== false && stripos($b5, 'telephone') !== false);
$t('fatura adresi sorusu',      stripos($b5, 'same as your billing address') !== false);
$t('fatura hazirlanacak',       stripos($b5, 'invoice will be prepared and sent') !== false);
/* KURAL 5: faturayi operator onayi kesiyor -- mektup TARIH/SURE vermemeli. */
$t('teslim/fatura TARIHI yok',  !preg_match('/\b(\d+\s*(business\s+)?(day|days|week|weeks|hour|hours)|tomorrow|within\s+\d+)\b/i', $b5));
/* Rakam metne gomulu degil: parametreden basiliyor. */
$t('tutar kutuda, kayittan',    ($o5['rows'][1]['value'] ?? '') === '€4,680.00');
$t('hesap var -> panel linki',  ($o5['button']['url'] ?? '') === 'https://vestrasales.com/buyer?tab=orders');
$t('imza Marco Bellini',        str_contains($b5, 'Marco Bellini'));
[$s6, $b6, $o6] = vestra_tpl_order_address_request('', 'VES-0000', 0.0, 'EUR', false, 'Elena Romano');
$t('ad bossa notr hitap',       str_contains($b6, 'Dear Customer'));
$t('tutar 0 -> satir yok',      count($o6['rows']) === 1);
$t('hesap yok -> panel linki yok', !isset($o6['button']));
$t('ikinci persona da basiliyor',  str_contains($b6, 'Elena Romano'));
$t('Turkce karakter yok',       $noTurkish($s5.$b5.$s6.$b6));


/* --- order_invoice_soon: "faturaniz ilk is gunu gelecek" (5 Eyl 2026) --- */
echo "\n== order_invoice_soon ==\n";
$sat = strtotime('2026-09-05 12:00');   /* Cumartesi */
[$s7, $b7, $o7] = vestra_tpl_order_invoice_soon('LINCHAOWEI', 'VES-6B53D265', $sat, 'early October 2026', true, 'Marco Bellini');
$t('konu ref tasir',              str_contains($s7, 'VES-6B53D265'));
$t('fatura hazirlaniyor',         stripos($b7, 'invoice is being prepared') !== false);
$t('hesap + e-posta ikisi de',    stripos($b7, 'VESTRA account and by e-mail') !== false);
/* TARIH SABIT DEGIL: Cumartesi gonderimde ilk is gunu Pazartesi 7 Eylul. */
$t('Cumartesi -> Monday 7 September', str_contains($b7, 'Monday 7 September'));
$t('on siparis tarihi ilandan',   stripos($b7, 'dispatch is scheduled for early October 2026') !== false);
$t('Latin harfli adres isteniyor',stripos($b7, 'Latin script') !== false);
$t('fatura adresi sorusu',        stripos($b7, 'same as your billing address') !== false);
$t('imza Marco Bellini',          str_contains($b7, 'Marco Bellini'));
$t('hesap var -> panel linki',    ($o7['button']['url'] ?? '') === 'https://vestrasales.com/buyer?tab=orders');
/* Ayni sablon Pazartesi gonderilse Sali demeli -- gomulu tarih olsaydi yalan olurdu. */
[, $b8, ] = vestra_tpl_order_invoice_soon('X', 'VES-1', strtotime('2026-09-07 12:00'), '', false, 'Elena Romano');
$t('Pazartesi -> Tuesday 8 September', str_contains($b8, 'Tuesday 8 September'));
$t('on siparis yoksa cumle YOK',  stripos($b8, 'pre-order') === false);
$t('hesap yok -> panel linki yok', !isset($o8['button']) && !isset($b8['button']));
$t('ikinci persona basiliyor',    str_contains($b8, 'Elena Romano'));
$t('Turkce karakter yok',         $noTurkish($s7.$b7.$b8));

/* --- claim_received / claim_resolved (6 Eyl 2026) ---
   SSS disputes/1 "referans numarasi alirsiniz", returns/6 "yazili yetki alirsiniz",
   returns/9 "sonuc size bildirilir". Bu iki mektup o uc vaadin yazili hali.
   Tutulanlar: ref + talep no konuda, sebep govdede, "geri gondermeyin" uyarisi,
   sonuc metni OLDUGU GIBI (rakam/adres uydurulmaz), hesap varsa dugme. */
echo "\n== claim_received ==\n";
if (!defined('VESTRA_CLAIM_REVIEW_BDAYS')) define('VESTRA_CLAIM_REVIEW_BDAYS', 2);
[$s9, $b9, $o9] = vestra_tpl_claim_received('maison test', 'VES-1', 'CLM-A1B2C3', 'Not as described', true);
$t('konu: talep no + siparis ref',      str_contains($s9, 'CLM-A1B2C3') && str_contains($s9, 'VES-1'));
$t('hitap bas harfli',                  str_contains($b9, 'Hello Maison Test,'));
$t('govde: referans + sebep',           str_contains($b9, 'Claim reference: CLM-A1B2C3') && str_contains($b9, 'Reason: Not as described'));
$t('2 is gunu sozu sabitten',           str_contains($b9, 'within '.VESTRA_CLAIM_REVIEW_BDAYS.' business days'));
$t('"geri gondermeyin" uyarisi',        stripos($b9, 'do not ship anything back') !== false);
$t('para tutuluyor cumlesi',            stripos($b9, 'stays held') !== false);
$t('hesap var -> siparis linki',        str_contains($o9['button']['url'] ?? '', 'view=VES-1'));
$t('bilgi kutusu: talep no vurgulu',    ($o9['rows'][0]['label'] ?? '') === 'Claim reference' && !empty($o9['rows'][0]['strong']));
[, $b9b, $o9b] = vestra_tpl_claim_received('X', 'VES-2', 'CLM-000000', 'Counterfeit', false);
$t('hesap yok -> dugme yok, link yok',  !isset($o9b['button']) && !str_contains($b9b, 'buyer?tab=orders'));
$t('Turkce karakter yok',               $noTurkish($s9.$b9.$b9b));

echo "\n== claim_resolved ==\n";
$outcome = 'Partial credit of 12 pieces; keep the goods.';
[$s10, $b10, $o10] = vestra_tpl_claim_resolved('Maison Test', 'VES-1', 'CLM-A1B2C3', $outcome, true);
$t('konu: talep no + "outcome"',        str_contains($s10, 'CLM-A1B2C3') && stripos($s10, 'outcome') !== false);
$t('sonuc OLDUGU GIBI basiliyor',       str_contains($b10, 'Outcome: '.$outcome));
$t('yazili yetki cumlesi',              stripos($b10, 'written authorisation') !== false);
$t('rakam/adres uydurulmuyor',          !preg_match('/€\s?\d|EUR\s?\d|\bIBAN\b/', $b10));
$t('bilgi kutusu: sonuc vurgulu',       ($o10['rows'][2]['label'] ?? '') === 'Outcome' && !empty($o10['rows'][2]['strong']));
$t('Turkce karakter yok',               $noTurkish($s10.$b10));

echo "\n== payment_notice: havale gonderilince haber ver ==\n";
/* Operator, 7 Eyl 2026: "musteriye faturayi gonder. havale yaptiktan sonra
   haber versin." Fatura kesilirken giden mektup "hesaba havale edin" deyip
   duruyordu; parayi gonderen musterinin soyleyecek yeri yoktu. */
[$s11, $b11, $o11] = vestra_tpl_order_payment_notice('Marianne HECQUET', 'O7A484', 'INV-2026-1103', 3320.00, 'EUR', true, 'Marco Bellini');
$t('Ingilizce, Turkce karakter yok',    $noTurkish($s11.$b11));
$t('konu haber vermeyi istiyor',        str_contains(strtolower($s11), 'let us know'));
$t('fatura no konuda',                  str_contains($s11, 'INV-2026-1103'));
$t('ref govdede',                       str_contains($b11, 'O7A484'));
/* Tutar KAYITTAN basiliyor: faturayla bir kurus ayrisan bir rakam musteriye
   "hangisi dogru" diye sordurur. */
$t('tutar govdede',                     str_contains($b11, '3,320.00'));
$t('para birimi simgesi',               str_contains($b11, '€3,320.00'));
$t('referans yazmasi isteniyor',        str_contains($b11, 'quote O7A484'));
/* Hesabi olana dekont kutusu; olmayana duz cevap. Olmayan bir dugmeye
   yollamak, KURAL 2b'nin kilitli sayfaya yollama hatasinin aynisi olurdu. */
$t('dekont yukleme dugmesi',            ($o11['button']['url'] ?? '') === 'https://vestrasales.com/buyer?tab=orders&view=O7A484');
$t('siparis sayfasi govdede de var',    str_contains($b11, 'order page'));
$t('cevap yolu da yaziliyor',           str_contains($b11, 'reply to this e-mail'));
$t('imza personadan',                   str_contains($b11, 'Marco Bellini'));
/* SAAT BASLATMIYOR: payment_due (KURAL 7) iptal uyarisi tasir ve gercekten
   5 is gunluk saati kurar. Operator onu istemedi; iki mektubu tek metinde
   birlestirmek, sorulmamis bir tehdidi de gondermek olurdu. */
$t('iptal tehdidi YOK',                 !preg_match('/cancel|business days|deadline/i', $b11));
/* IBAN/banka faturada; mektuba ikinci kopyasi yazilmaz -- ayrisir, ve bu
   depoda banka numarasi metne gomulmez. */
$t('IBAN mektuba gomulmemis',           !preg_match('/\bIBAN\b|\b[A-Z]{2}\d{2}[A-Z0-9]{10,}/', $b11));

[$s12, $b12, $o12] = vestra_tpl_order_payment_notice('', 'O7A484', '', 0.0, 'EUR', false, '');
$t('hesapsiz: dugme yok',               !isset($o12['button']));
$t('hesapsiz: cevap yolu tek yol',      str_contains($b12, 'reply to this e-mail') && !str_contains($b12, 'order page'));
$t('adsiz alici "Customer"',            str_contains($b12, 'Dear Customer'));
/* Tutar/fatura yoksa UYDURULMAZ: satir hic basilmaz. */
$t('tutar yoksa rakam basmiyor',        !preg_match('/€\d/', $b12));
$t('fatura yoksa konuda no yok',        !str_contains($s12, 'INV-'));
$t('imzasiz = sirket imzasi',           str_contains($b12, 'Acerasoft LLC'));

[$s13, $b13,] = vestra_tpl_order_payment_notice('Li', 'O1', 'INV-1', 100.0, 'USD', true, '');
$t('USD simgesi dogru',                 str_contains($b13, 'US$100.00') && !str_contains($b13, '€100.00'));

/* Mektup, is akisindaki kapilarla birlikte anlamli: faturasiz/odenmis/
   dekontu gelmis sipariste GONDERILMEZ. Kapilar workflow'da, burada
   varliklari dogrulaniyor -- kapisiz bir mektup yanlis anda gider. */
$wf = (string)@file_get_contents(__DIR__.'/../.github/workflows/send-campaign-preview.yml');
$t('mektup is akisinda kabloli',        str_contains($wf, "\$letter === 'payment_notice'"));
$t('faturasiz sipariste durur',         str_contains($wf, "fatura KESILMEMIS -- 'faturaniz sizde' cumlesi yanlis olur"));
$t('odenmis/gonderilmis sipariste durur', str_contains($wf, "in_array(\$ost, ['paid','shipped','completed'], true)"));
$t('dekont gelmisse ikinci kez istemez', str_contains($wf, 'dekont ZATEN yuklenmis'));
$t('escrow sipariste durur',            str_contains($wf, 'escrow siparisi -- havale bildirimi yanlis olur'));

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
