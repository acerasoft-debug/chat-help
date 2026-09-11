<?php
/* BEBILDERTES SORTIMENTSBLATT — bir ya da daha cok ilanin her rengini fotografiyla,
 * istenirse FIYAT KADEMELERIYLE yazan mektup (operator, 11 Eyl 2026: *"ilandaki
 * fiyatlar ile beraber gönder"*; alici BRITISHSTYLE iki kez *"das bebilderte
 * Sortimentsblatt"* istedi).
 *
 * Test IKI YONU birden tutuyor, cunku bu mektup rakam tasiyor:
 *   1) Fiyat ISTENDIGINDE: merdiven MOQ'da basliyor, sepetin odedigi rakam
 *      yaziliyor, EUR olarak yaziliyor.
 *   2) Fiyat ISTENMEDIGINDE: govdede TEK BIR RAKAM yok. Tek yon yazilsaydi
 *      varsayilan bir gun "on"a kayar ve kimse fark etmezdi -- kapisi kapali bir
 *      alici sayfada goremedigi fiyati mektupta okurdu (KURAL 2b'nin tersi).
 *
 * Merdiven neden AYRI bir fonksiyon: ham `tiers` ilk satiri cogu ilanda min=1
 * yaziyor. "ab 1 Stuck 70,20 EUR" yazan bir mektup, sepetin kabul etmedigi bir
 * adedin fiyatini ilan eder. Duzeltme tek yerde (vestra_price_ladder) ve buradan
 * olculuyor; her cagiranin kendi duzeltmesini yazmasi, bu depoda defalarca
 * kaydedilen "ayni olgu bir kac yerde yazili" hatasinin ta kendisi olurdu.
 */

require_once __DIR__ . '/../vestra/inc/products.php';
require_once __DIR__ . '/../vestra/inc/email_templates.php';

$fail = 0; $n = 0;
function ok(bool $c, string $m): void {
    global $fail, $n; $n++;
    if (!$c) { $fail++; echo "  KALDI: {$m}\n"; }
}

/* Gercek iki ilanin sekli: canli kayittan alindi (fp-m3600-polo / fp-m7535-sweat).
   Rakamlar uydurulmadi -- bu depoda kendi uydurdugum pack_qty degerleriyle yesil
   kalan bir test canlida yanlis metin urettirmisti. */
$polo = [
    'id' => 'fp-m3600-polo', 'sku' => 'M3600', 'brand' => 'Fred Perry',
    'name' => 'The Fred Perry Shirt — M3600 Twin Tipped',
    'colors' => ['Black', 'White', 'Navy', 'Bordeaux', 'Green', 'Light Blue'],
    'moq' => 56, 'min_colors' => 2, 'size_step' => 8,
    'tiers' => [['min' => 1, 'price' => 70.20], ['min' => 96, 'price' => 63.90], ['min' => 192, 'price' => 57.60]],
];
$sweat = [
    'id' => 'fp-m7535-sweat', 'sku' => 'M7535', 'brand' => 'Fred Perry',
    'name' => 'Crew Neck Sweatshirt — M7535',
    'colors' => ['Black', 'Navy', 'Green', 'Grey', 'Bordeaux'],
    'moq' => 50, 'min_colors' => 4, 'size_step' => 10,
    'tiers' => [['min' => 1, 'price' => 71.82]],
];
$pairsOf = fn(array $p, string $pre) => array_map(
    fn($c) => ['colour' => $c, 'img' => 'https://vestrasales.com/uploads/fredperry/'
                                      . $pre . '-' . strtolower(str_replace(' ', '-', $c)) . '.jpg'],
    (array)$p['colors']);
$pp = $pairsOf($polo, 'm3600');
$ps = $pairsOf($sweat, 'm7535');

/* ── 1) Merdiven: ILK BASAMAK MOQ'DA ────────────────────────────────────── */
$lp = vestra_price_ladder($polo);
ok(count($lp) === 3, 'polo: uc basamak');
ok($lp[0]['min'] === 56, 'ilk basamak MOQ (56), ham tiers\'in 1\'i DEGIL');
ok(abs($lp[0]['price'] - 70.20) < 0.001, 'MOQ fiyati sepetin odedigi rakam');
ok($lp[1]['min'] === 96 && $lp[2]['min'] === 192, 'ustteki kademeler sirayla');
ok(abs($lp[2]['price'] - 57.60) < 0.001, 'en ust kademe fiyati');
/* MOQ'nun altindaki kademe merdivende GORUNMUYOR ama fiyati kayboluyor da degil:
   56 adette gecerli olan fiyat neyse ilk basamak o. */
ok(!array_filter($lp, fn($r) => $r['min'] < 56), 'MOQ altinda basamak yok');

$ls = vestra_price_ladder($sweat);
ok(count($ls) === 1 && $ls[0]['min'] === 50, 'tek kademeli ilanda tek basamak, MOQ\'da');

/* Fiyati DEGISTIRMEYEN basamak dusuyor; YUKSELEN basamak DUSMUYOR. */
$dup = $polo; $dup['tiers'] = [['min' => 1, 'price' => 70.20], ['min' => 96, 'price' => 70.20]];
ok(count(vestra_price_ladder($dup)) === 1, 'ayni fiyati tekrarlayan basamak yazilmiyor');
$up = $polo; $up['tiers'] = [['min' => 1, 'price' => 70.20], ['min' => 96, 'price' => 80.00]];
ok(count(vestra_price_ladder($up)) === 2, 'YUKSELEN basamak yaziliyor (gizlemek eksik bilgi olurdu)');
$none = $polo; unset($none['tiers']);
ok(vestra_price_ladder($none) === [], 'kademesiz ilanda merdiven BOS -- 0,00 EUR basilmiyor');

/* ── 2) FIYAT ISTENMEDIGINDE govdede rakam YOK ──────────────────────────── */
[$s0, $b0, $o0] = vestra_tpl_listing_colours('Guten Tag', [['p' => $polo, 'pairs' => $pp, 'rungs' => $lp]],
                                             'GARAGE LE PARIS', 'de');
ok(!str_contains($b0, '€') && !str_contains($b0, 'EUR'), 'fiyatsiz mektupta para birimi GECMIYOR');
ok(!str_contains($b0, '70,20'), 'fiyatsiz mektupta kademe rakami GECMIYOR');
ok(!str_contains($s0, 'Preis'), 'fiyatsiz mektubun konusu fiyat vaat etmiyor');
ok(str_contains($b0, '56 Stück'), 'fiyat olmasa da mindestabnahme yaziyor');
ok(count($o0['shots']) === 6, 'tek ilan: alti kare');
ok($o0['shots'][0]['label'] === 'Black', 'tek ilanda etiket yalin renk adi');

/* ── 3) FIYAT ISTENDIGINDE: kademeler govdede ve karttaki satirda ───────── */
[$s1, $b1, $o1] = vestra_tpl_listing_colours('Guten Tag', [['p' => $polo, 'pairs' => $pp, 'rungs' => $lp]],
                                             'GARAGE LE PARIS', 'de', '', true);
ok(str_contains($b1, 'ab 56 Stück 70,20 €'), 'ilk kademe: MOQ + Alman yazimiyla EUR');
ok(str_contains($b1, 'ab 96 Stück 63,90 €') && str_contains($b1, 'ab 192 Stück 57,60 €'), 'ust kademeler');
ok(!str_contains($b1, 'ab 1 Stück'), 'ham tiers\'in min=1\'i mektuba SIZMIYOR');
/* Vergi iddiasi YOK: fiyatlar brut (KURAL 5m) ama faturada KDV gercekten alinacak
   mi karari fatura basina ve AB ici ticarette cogu zaman ters yuklemede. Iki
   yonde de dogru olan tek cumle "pro Stuck, zzgl. Versand". */
ok(str_contains($b1, 'zzgl. Versand'), 'kargo haric oldugu yaziyor');
ok(!preg_match('/\b(netto|inkl\.?\s*MwSt|zzgl\.?\s*MwSt|excl\. VAT|incl\. VAT)/i', $b1),
   'mektup VERGI iddiasi tasimiyor (dogrulayamadigimiz sey yazilmaz)');
ok((bool)array_filter($o1['rows'], fn($r) => str_contains((string)$r['value'], '70,20 €')),
   'karttaki satir da ayni rakami tasiyor (HTML\'i tarayan okuyucu)');
ok(str_contains($s1, 'mit Preisen'), 'konu fiyat tasidigini soyluyor');

/* Ingilizce yazim: nokta ondalik, EUR onde. */
[, $b1en, ] = vestra_tpl_listing_colours('Dear Sir', [['p' => $polo, 'pairs' => $pp, 'rungs' => $lp]],
                                         'GARAGE LE PARIS', 'en', '', true);
ok(str_contains($b1en, 'from 56 pcs EUR 70.20'), 'Ingilizce kademe yazimi');
ok(str_contains($b1en, 'plus shipping'), 'Ingilizce: kargo haric');

/* ── 4) IKI ILAN TEK MEKTUPTA ───────────────────────────────────────────── */
[$s2, $b2, $o2] = vestra_tpl_listing_colours(
    'Guten Tag', [['p' => $polo, 'pairs' => $pp, 'rungs' => $lp, 'tag' => 'M3600'],
                  ['p' => $sweat, 'pairs' => $ps, 'rungs' => $ls, 'tag' => 'M7535']],
    'GARAGE LE PARIS', 'de', '', true);
ok(count($o2['shots']) === 11, 'onbir kare (6 + 5)');
/* Ayni renk adi iki modelde de var: etiketsiz "Black" hangi modelin oldugunu
   soylemez ve serit tam da bunun icin var. Etiket ILANIN KENDI SKU'su. */
$labs = array_column($o2['shots'], 'label');
ok(in_array('M3600 · Black', $labs, true) && in_array('M7535 · Black', $labs, true),
   'cok ilanli seritte etiket modeli de tasiyor');
ok(count(array_unique($labs)) === 11, 'onbir etiketin hepsi ayri');
ok(str_contains($b2, $polo['name']) && str_contains($b2, $sweat['name']), 'iki ilan adi da govdede');
ok(str_contains($b2, '70,20 €') && str_contains($b2, '71,82 €'), 'iki ilanin fiyatlari da govdede');
ok(str_contains($b2, 'ab 4 Farben') && str_contains($b2, 'ab 2 Farben'),
   'her ilan KENDI renk minimumunu yaziyor (biri 2, digeri 4)');
ok(str_contains($b2, '11 angebotenen Farben'), 'toplam renk sayisi iki ilanin toplami');
/* Her karenin bagi KENDI ilanina gidiyor: 11 karenin hepsini tek sayfaya
   baglamak, sweatshirt fotografina tiklayan aliciyi poloya dusururdu. */
$urls = array_unique(array_column($o2['shots'], 'url'));
ok(count($urls) === 2, 'kareler iki ayri urun sayfasina baglaniyor');

/* ── 5) Marka sayfasi: yalniz CAGIRAN cozdurduyse ───────────────────────── */
ok($o2['button']['url'] === 'https://vestrasales.com/product?id=fp-m3600-polo',
   'marka sayfasi verilmediyse dugme ILK ILANA gidiyor (uydurma adres yok)');
[, $b3, $o3] = vestra_tpl_listing_colours(
    'Guten Tag', [['p' => $polo, 'pairs' => $pp, 'rungs' => $lp, 'tag' => 'M3600'],
                  ['p' => $sweat, 'pairs' => $ps, 'rungs' => $ls, 'tag' => 'M7535']],
    'GARAGE LE PARIS', 'de', '', true, 'https://vestrasales.com/wholesale/fred-perry');
ok($o3['button']['url'] === 'https://vestrasales.com/wholesale/fred-perry', 'verilen marka sayfasi dugmeye giriyor');
ok(str_contains($b3, 'Alle Modelle dieser Marke: https://vestrasales.com/wholesale/fred-perry'),
   'adres DUZ METIN govdede de var (cogu istemci dugmeyi gostermez)');

/* ── 6) Imza ve not ─────────────────────────────────────────────────────── */
ok(str_contains($b1, "GARAGE LE PARIS\nüber VESTRA"), 'imza ILANIN saticisi adina');
ok(!str_contains($b1, 'Marco Bellini') && !str_contains($b1, 'Elena Romano'),
   'VESTRA personasi bu mektuba karismiyor');
[, $b4, ] = vestra_tpl_listing_colours('Guten Tag', [['p' => $polo, 'pairs' => $pp, 'rungs' => $lp]],
                                       'GARAGE LE PARIS', 'de', 'Fred Perry führt eine Körperfarbe mit mehreren Tippings.', true);
ok(str_contains($b4, 'Fred Perry führt eine Körperfarbe'), 'not AYNEN basiliyor');
ok(!str_contains($b1, 'Körperfarbe'), 'not verilmediginde sablon kendi cumlesini uydurmuyor');

/* ── 7) Kablolama: is akisi merdiveni KENDI hesaplamiyor ────────────────── */
$wf = (string)file_get_contents(__DIR__ . '/../.github/workflows/send-campaign-preview.yml');
ok(str_contains($wf, '$rungs = vestra_price_ladder($prod);'),
   'listing_colours merdiveni paylasilan fonksiyondan aliyor');
ok(!preg_match('/listing_colours[\s\S]{0,9000}?\$ladder\s*=\s*\[\[/', $wf),
   'is akisinda ikinci bir merdiven hesabi kalmadi');
/* Varsayilan OFF olmali: acikca istenmedikce mektup rakam tasimaz. */
ok(str_contains($wf, "\$lcPrices = in_array(strtolower(trim(\$E('prices'))), ['on', 'true', '1', 'evet'], true);"),
   'fiyat girdisi ACIKCA istenmeli (varsayilan OFF)');

echo $fail
    ? "\nlisting_sheet_test: {$fail}/{$n} KALDI\n"
    : "listing_sheet_test: {$n} iddia gecti\n";
exit($fail ? 1 : 0);
