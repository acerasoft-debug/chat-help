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
foreach (['vestra_display_name', 'vestra_tpl_order_tracking_soon', 'vestra_tpl_order_shipped'] as $fn) {
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

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
