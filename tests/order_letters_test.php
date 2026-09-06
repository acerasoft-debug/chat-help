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
          'vestra_tpl_order_address_request', 'vestra_tpl_order_invoice_soon'] as $fn) {
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

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
