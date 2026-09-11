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

/* ── 8) FIYAT LISTESI mektubu ────────────────────────────────────────────
   Kardes mektup: ayni alicinin "listenizi gonderin" istegine cevap. Iki yonu
   birden tutuluyor, cunku bu mektubun tek isi BIR KAPSAM IDDIASI:
     1) Rakamlar govdede DOGRU yaziliyor (kalem/marka/bolme, canli sayimdan).
     2) TEK BIR FIYAT govdeye girmiyor -- fiyatlar ekte ve ikinci bir yerde
        durmamali (bu depoda "ayni olgu iki yerde" defalarca kayitli). */
$facts = [
    'articles' => 827, 'brands' => 34,
    'sections' => ['Apparel' => 612, 'Footwear' => 335, 'Underwear' => 146],
    'brand_names' => ['Calzados Pili Pérez', 'Visatin', 'BALMAIN', 'Burberry', 'NBB', 'Q-EN'],
    'url' => 'https://vestrasales.com/price-list',
];
[$ps, $pb, $po] = vestra_tpl_price_list('Guten Tag', $facts, ['pdf', 'xlsx'], 'Marco Bellini', 'de');
ok(str_contains($pb, '827 Artikel'), 'kalem sayisi govdede');
ok(str_contains($pb, '34 Marken'), 'marka sayisi govdede');
ok(str_contains($pb, 'Apparel 612') && str_contains($pb, 'Underwear 146'), 'bolme dagilimi govdede');
ok(str_contains($pb, 'PDF und Excel'), 'ekin bicimi yaziyor');
ok(str_contains($ps, '827 articles') || str_contains($ps, '(827 Artikel)'), 'konu kalem sayisini tasiyor');
/* Fiyat govdeye GIRMIYOR: ne rakam, ne para birimi isareti. "EUR" kelimesi
   "pro Stück in EUR" cumlesinde gecebilir -- yasak olan RAKAM. */
ok(!preg_match('/\d+[.,]\d{2}\s*(€|EUR)|(€|EUR)\s*\d+[.,]\d{2}/u', $pb),
   'govdede TEK BIR FIYAT yok -- fiyatlar ekte, ikinci bir yerde durmuyor');
ok(str_contains($pb, 'zzgl. Versand'), 'kargo haric oldugu yine yaziyor');
ok(!preg_match('/\b(netto|inkl\.?\s*MwSt)/i', $pb), 'vergi iddiasi yok');
/* Imza VESTRA: katalog listesi birden fazla saticinin malini tasiyor. */
ok(str_contains($pb, 'VESTRA – vestrasales.com'), 'VESTRA adina imzalaniyor');
ok(!str_contains($pb, 'GARAGE LE PARIS') && !str_contains($pb, 'TYREX'),
   'tek bir dukkanin adi katalog listesine KONMUYOR');
ok(str_contains($pb, 'Marco Bellini'), 'persona imzasi basiliyor');
/* Ekin bicimi ISTEKTEN degil, GERCEKTEN eklenenden yaziliyor: olmayan bir
   dosyayi adiyla anan mektup, musteriyi onu aramaya yollar. */
[, $pb2, ] = vestra_tpl_price_list('Dear Sir', $facts, ['xlsx'], '', 'en');
ok(str_contains($pb2, 'attached as Excel') && !str_contains($pb2, 'PDF'),
   'yalniz Excel eklendiyse mektup PDF demiyor');
[, $pb3, $po3] = vestra_tpl_price_list('Dear Sir', $facts, [], '', 'en');
ok(!str_contains($pb3, 'attached'), 'hic ek yoksa "ekte" denmiyor');
ok(!array_filter($po3['rows'], fn($r) => ($r['label'] ?? '') === 'Attached'),
   'ek satiri da yok');
/* Daraltilmis kapsam: marka sayisi anlamsizlasir, yazilmaz. */
$narrow = ['articles' => 2, 'brands' => 1, 'scope' => 'Fred Perry',
           'sections' => ['Apparel' => 2], 'brand_names' => ['Fred Perry'],
           'url' => 'https://vestrasales.com/price-list?brand=Fred%20Perry'];
[$ns, $nb, ] = vestra_tpl_price_list('Dear Sir', $narrow, ['pdf'], '', 'en');
ok(str_contains($ns, 'Fred Perry — price list'), 'daraltilmis kapsam konuda');
ok(!str_contains($nb, 'houses'), 'tek markada "kac marka" cumlesi yazilmiyor');
ok(str_contains($nb, 'brand=Fred%20Perry'), 'hesaptaki liste adresi de daraltilmis');

/* Marka kapsamli liste "BIZIM" degil, MARKANIN listesidir (operator, 11 Eyl
   2026: *"anbei die Preisliste von F.Perrey nicht unsere — ben satici
   degilim"*). VESTRA pazar yeri; mali satan taraf degil. Iki yon de tutuluyor:
   marka verildiginde markaya atfediliyor, verilmediginde "bizim" kaliyor --
   katalogun tamami gercekten VESTRA'nin kendi listesi. */
$fp = ['articles' => 2, 'brands' => 1, 'scope' => 'Fred Perry', 'brand_scope' => 'Fred Perry',
       'sections' => ['Apparel' => 2], 'brand_names' => ['Fred Perry'],
       'url' => 'https://vestrasales.com/price-list?brand=Fred%20Perry'];
[, $fb, ] = vestra_tpl_price_list('Guten Tag', $fp, ['pdf', 'xlsx'], 'Marco Bellini', 'de');
ok(str_contains($fb, 'die Preisliste von Fred Perry'), 'marka listesi MARKAYA atfediliyor');
ok(!str_contains($fb, 'unsere Preisliste'), '"bizim listemiz" YAZMIYOR (satici biz degiliz)');
[, $fben, ] = vestra_tpl_price_list('Dear Sir', $fp, ['pdf'], '', 'en');
ok(str_contains($fben, 'the Fred Perry price list is attached'), 'Ingilizcesi de markaya atfediliyor');
ok(!str_contains($fben, 'our price list'), 'Ingilizcede de "our" yok');
ok(!str_contains($fb, 'Marken u. a.'),
   'tek markalik listede marka satiri TEKRAR EDILMIYOR (acilis cumlesi zaten soyluyor)');
ok(!str_contains($fb, 'Sortiment:'),
   'tek bolmelik listede bolme satiri da yazilmiyor');
/* Ters yon: katalogun TAMAMINDA "bizim" dogru ve kalmali. */
ok(str_contains($pb, 'unsere Preisliste'), 'kapsamsiz listede "unsere" KORUNUYOR');
ok(str_contains($pb2, 'our price list'), 'kapsamsiz Ingilizcede "our" KORUNUYOR');

/* ── 9) Kablolama: inceleme yolu PAYLASILAN blokta ───────────────────────── */
ok(str_contains($wf, "if (strtolower(\$E('copy')) === 'true') {"),
   'copy=true inceleme yolu var');
/* Tek bir dalin icinde degil, PAYLASILAN gonderim blogunda: her mektubun
   onizlenecek bir yeri olmali. Olcut: gonderim kapisindan ONCE geliyor. */
/* OLCUM TUZAGINA DUSTUM, kayda geciyor: ilk yazimda `strpos` ile arayip
   "kopya, gonderim kapisindan once mi" diye sordum. Dosyada ALTI ayri
   `send=false` kapisi var (her isin kendi yolu) ve strpos ILKINI buluyor --
   yani iddia dogru calisan bir kodda KIRMIZI dondu. Bu deponun alti kez
   kaydettigi "kontrol yanlis yere bakiyor"un aynisi. Olcut artik PAYLASILAN
   gonderim: `$ok = vestra_send_mail($to, ...)` tek satir ve kopya ondan once
   gelmek zorunda. */
$posCopy = strpos($wf, "if (strtolower(\$E('copy')) === 'true') {");
/* Ikinci deneme de yanlis yere bakti: bu dosyada ALTI is var ve `$ok =
   vestra_send_mail($to, $subject, $body,` kalibi baska bir isin kendi
   gonderiminde de geciyor -- yani yine ilk esleşme olculdu. Ayirt eden sey
   gonderen adi: paylasilan blok $fromName gecirir (digeri sabit 'VESTRA'). */
$sharedSend = '$ok = vestra_send_mail($to, $subject, $body, '
            . "'support@vestrasales.com', \$fromName,";
$posSend = strpos($wf, $sharedSend);
ok(substr_count($wf, $sharedSend) === 1,
   'paylasilan gonderim TEK satir (kopyanin atlayabilecegi ikinci yol yok)');
ok($posCopy !== false && $posSend !== false && $posCopy < $posSend,
   'kopya kontrolu PAYLASILAN gonderimden ONCE -- send=true olmadan da onizlenebiliyor');
ok(substr_count($wf, "\$E('copy')") === 1,
   'inceleme yolu TEK yerde (dal basina ikinci kopya yok)');
ok(str_contains($wf, 'vestra_tpl_price_list($salutation, $plFacts, $plDone, $signer, $plLang)'),
   'price_list dali sablonu cagiriyor');
ok(str_contains($wf, "\$plFacts['articles']") || str_contains($wf, "'articles'    => count(\$plRows)"),
   'kalem sayisi CANLI kayittan sayiliyor, girdiden degil');

echo $fail
    ? "\nlisting_sheet_test: {$fail}/{$n} KALDI\n"
    : "listing_sheet_test: {$n} iddia gecti\n";
exit($fail ? 1 : 0);
