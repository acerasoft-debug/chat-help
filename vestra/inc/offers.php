<?php
/**
 * VESTRA — teklife yanit verme (kabul / ret / karsi teklif), TEK yerde.
 *
 * Bu kod eskiden yalnizca seller.php icindeydi ve sahiplik sarti ariyordu:
 *
 *     $ownsOffer = $listing && ($listing['seller_uid'] ?? '') === $uid && $uid !== '';
 *
 * Kurasyonlu katalog urunlerinde seller_uid YOK. Kosul o zaman '' === $uid ile
 * $uid !== '' oluyor ve ikisi ayni anda saglanamiyor -- yani katalog urunune
 * gelen bir teklife HICBIR satici yanit veremiyordu. Admin panelinin Teklifler
 * sekmesi ise tamamen salt-okunurdu, orada da dugme yoktu. Sonuc: gecerli bir
 * teklif sisteme dusuyor, alicisina e-posta gidiyor, ve kimse kabul edemiyordu.
 *
 * Mantik buraya tasindi ki iki cagiran taraf AYNI kodu calistirsin. Bu dosyada
 * yetki kontrolu YOK: karar cagirana ait (satici sahipligi, ya da operator
 * yetkisi). Yetkiyi iceri gomup iki tarafta iki farkli kural olusturmak, bu
 * projede birkac kez yasanmis bir hata -- bir uc "yayinla" derken digeri
 * reddediyordu.
 */
require_once __DIR__.'/products.php';
require_once __DIR__.'/auth.php';

/* Bir pazarlikta verilebilecek EN FAZLA karsi teklif sayisi -- iki taraf
 * TOPLAMI. Operator karari: "karsi teklif en fazla 3 kere verilebilir".
 * Ucuncuden sonra taraflar yalnizca kabul ya da ret verebilir; sinirsiz
 * pazarlik, ikisini de sonuca baglamayan bir yazisma zinciri uretiyor.
 * Tek sabit: sayi degisirse panel, mektup ve kontrol birlikte degisir. */
if (!defined('VESTRA_OFFER_MAX_COUNTERS')) define('VESTRA_OFFER_MAX_COUNTERS', 3);

/* Simdiye kadar kac karsi teklif verildi. Eski kayitlarda 'counters'
 * dizisi YOK ama 'counter_price' olabilir -- o da bir turdur, yoksa
 * bugune kadarki pazarliklar sifirdan baslamis gorunur. */
function vestra_offer_counter_count(?array $resp): int {
    if (!$resp) return 0;
    if (isset($resp['counters']) && is_array($resp['counters'])) return count($resp['counters']);
    return ((float)($resp['counter_price'] ?? 0) > 0) ? 1 : 0;
}

function vestra_offer_counters_left(?array $resp): int {
    return max(0, VESTRA_OFFER_MAX_COUNTERS - vestra_offer_counter_count($resp));
}

/* Siradaki taraf. Karar VERILMIS bir teklifte (kabul/ret) sira kimsede
 * degil -- '' doner ve iki panel de dugme gostermez. */
function vestra_offer_turn(?array $resp): string {
    $st = (string)($resp['status'] ?? '');
    if ($st === 'accept' || $st === 'decline') return '';
    if ($st !== 'counter') return 'seller';                      // henuz yanit yok
    return ((string)($resp['counter_by'] ?? 'seller') === 'buyer') ? 'seller' : 'buyer';
}

/* Bu taraf SU AN karsi teklif verebilir mi: sirasi mi, ve tavan doldu mu. */
function vestra_offer_can_counter(?array $resp, string $side): bool {
    return vestra_offer_turn($resp) === $side && vestra_offer_counters_left($resp) > 0;
}

/* ── Pazarlik sinirlari (operator karari, 31 Agu 2026) ──────────────────
 * ALICI teklifi urunun YARISINDAN AZ olamaz.
 * SATICI karsi teklifi urunun NORMAL FIYATINDAN FAZLA olamaz.
 *
 * Referans fiyat vestra_from_price(): kademe merdiveninin EN DUSUK basamagi,
 * yani alicinin en iyi kosulda odeyecegi rakam. Bilerek boyle -- 'list'
 * alani mode='sale' urunlerde USTU CIZILI eski fiyat, onu tavan yapmak
 * saticiya gercekte hic satmadigi bir fiyattan karsi teklif hakki verirdi.
 *
 * Fiyati COZULEMEYEN urunde (teklif modunda kademe yoksa) sinir UYGULANMAZ:
 * ortada karsilastirilacak bir rakam yokken teklifi reddetmek, mustereyi
 * hicbir sey yapamaz halde birakir. Sinir varsa uygulanir, yoksa serbest. */
if (!defined('VESTRA_OFFER_MIN_BUYER_PCT')) define('VESTRA_OFFER_MIN_BUYER_PCT', 0.50);

function vestra_offer_ref_price(?array $listing): float {
    if (!$listing) return 0.0;
    $ref = (float)vestra_from_price($listing);
    if ($ref <= 0) $ref = (float)($listing['list'] ?? 0);
    return $ref > 0 ? round($ref, 2) : 0.0;
}

/* Bir tarafin EN SON verdigi rakam. Alicida ilk teklif de sayilir (o da
 * onun rakami); saticinin oncesi yoksa null doner ve ilk karsi teklifine
 * yaklasma sarti uygulanmaz. */
function vestra_offer_last_price(?array $resp, string $side, ?array $offerRow = null): ?float {
    $last = null;
    foreach ((array)($resp['counters'] ?? []) as $c) {
        if ((string)($c['by'] ?? '') === $side) $last = (float)($c['price'] ?? 0);
    }
    if ($last === null && $side === 'seller' && (string)($resp['counter_by'] ?? 'seller') === 'seller'
        && (float)($resp['counter_price'] ?? 0) > 0) {
        $last = (float)$resp['counter_price'];       // 'counters' dizisi olmayan eski kayit
    }
    if ($last === null && $side === 'buyer' && $offerRow) {
        $o = (float)($offerRow['offer_unit'] ?? 0);
        if ($o > 0) $last = $o;                      // alicinin ILK teklifi
    }
    return $last;
}

/* $side: 'buyer' | 'seller'. Gecerliyse null, degilse KULLANICIYA
 * gosterilecek gerekce doner -- cagiran taraf metni yeniden yazmasin,
 * yoksa uc ekranda uc farkli cumle olur.
 *
 * $prevSame: ayni tarafin bir onceki rakami. Pazarlik DARALMAK zorunda:
 * alici her turda YUKSELIR, satici her turda DUSER. Bu olmadan taraflar
 * ayni iki rakami tekrarlayip tur hakkini bosa harcayabiliyordu -- ve
 * uc tur, yaklasilmadiginda hicbir sey ifade etmiyor. */
function vestra_offer_price_error(?array $listing, string $side, float $price, ?float $prevSame = null): ?string {
    if ($price <= 0) return 'Enter a unit price.';
    $ref = vestra_offer_ref_price($listing);

    if ($side === 'buyer') {
        if ($ref > 0) {
            $min = round($ref * VESTRA_OFFER_MIN_BUYER_PCT, 2);
            if ($price + 0.004 < $min) {
                return sprintf('An offer cannot be below half the product price. The lowest we can accept is €%s per unit.', number_format($min, 2));
            }
        }
        if ($prevSame !== null && $price <= $prevSame + 0.004) {
            return sprintf('Your new offer has to be higher than your last one (€%s per unit).', number_format($prevSame, 2));
        }
        return null;
    }

    if ($ref > 0 && $price > $ref + 0.004) {
        return sprintf('A counter offer cannot be above the product price (€%s per unit).', number_format($ref, 2));
    }
    if ($prevSame !== null && $price + 0.004 >= $prevSame) {
        return sprintf('Your new counter has to be lower than your last one (€%s per unit).', number_format($prevSame, 2));
    }
    return null;
}

/**
 * @param string     $ref     Teklif referansi (offers.csv -> ref)
 * @param string     $action  accept | decline | counter
 * @param float      $ctr     counter icin birim fiyat, digerlerinde 0
 * @param ?array     $actor   Yaniti veren hesap (satici) — operator icin null
 * @param string     $label   Alicinin e-postada gorecegi gonderen adi
 * @param bool       $notify  false = aliciya BILDIRIM GONDERME (mesaj/push/e-posta).
 *                            Yalnizca cagiran taraf yerine gececek DAHA DOLU bir mektup
 *                            gonderiyorsa kullanilir: aksi halde alici dakikalar arayla
 *                            ayni haberi iki kez alir. Kayit her halukarda yaziliyor --
 *                            "sessiz" olan bildirim, kabulun kendisi degil.
 * @return array     ['ok'=>bool, 'error'=>string, 'invoice'=>?array]
 */
function vestra_offer_respond(string $ref, string $action, float $ctr, ?array $actor, string $label = 'VESTRA', bool $notify = true): array {
    $ref = trim($ref);
    if ($ref === '') return ['ok' => false, 'error' => 'ref yok'];
    if (!in_array($action, ['accept', 'decline', 'counter'], true)) return ['ok' => false, 'error' => 'gecersiz islem'];
    if ($action === 'counter' && $ctr <= 0) return ['ok' => false, 'error' => 'karsi teklif fiyati gerekli'];

    $offerRow = vestra_offer_row($ref);
    if (!$offerRow) return ['ok' => false, 'error' => 'teklif bulunamadi'];
    $listing = vestra_listing_by_sku($offerRow['sku'] ?? '');

    /* Yanit ONCE diske yaziliyor. Bildirim/e-posta/fatura adimlarindan biri
       patlarsa teklif "yanitlanmis" kalir; tersi durumda alici kabul e-postasi
       alip sistemde teklif "bekliyor" gorunurdu -- ikinci bir kabul denemesi
       ikinci bir e-posta demek. */
    $rs   = vestra_read_json('offer_responses.json');
    $prev = $rs[$ref] ?? null;

    /* Sira ve tavan. Bir karara baglanmis teklif yeniden yanitlanamaz --
       aksi halde kabul edilmis bir teklif sonradan reddedilebilir ve
       alici iki celiskili mektup alir. Karsi teklifte ayrica TUR SINIRI:
       ucuncuden sonra yalnizca kabul/ret. */
    $turn = vestra_offer_turn($prev);
    if ($turn === '') {
        return ['ok' => false, 'error' => 'bu teklif zaten '.(($prev['status'] ?? '') === 'accept' ? 'kabul edildi' : 'reddedildi')];
    }
    if ($turn !== 'seller') {
        return ['ok' => false, 'error' => 'sira alicida — son karsi teklifi o verdi'];
    }
    if ($action === 'counter' && vestra_offer_counters_left($prev) < 1) {
        return ['ok' => false, 'error' => 'karsi teklif hakki bitti ('.VESTRA_OFFER_MAX_COUNTERS.'/'.VESTRA_OFFER_MAX_COUNTERS.') — yalnizca kabul ya da ret'];
    }
    /* Fiyat tavani. Kayittan ONCE: gecersiz bir karsi teklif diske yazilip
       aliciya mektup gonderilmemeli. */
    if ($action === 'counter') {
        $pErr = vestra_offer_price_error($listing, 'seller', $ctr,
                    vestra_offer_last_price($prev, 'seller'));
        if ($pErr !== null) return ['ok' => false, 'error' => $pErr];
    }

    /* UZLASILAN birim fiyat. Karsi teklif verilmis ve SONRA kabul edilmisse
       anlasma KARSI TEKLIF fiyati uzerinden; alicinin ilk teklifi artik
       gecerli degil. Onceden fatura her durumda offer_unit'ten kesiliyordu,
       yani karsi teklif pazarligi faturaya hic yansimiyordu -- operator 12
       EUR'ya anlasip 9 EUR'luk fatura kesiyordu. */
    $agreedUnit = (float)($offerRow['offer_unit'] ?? 0);
    if ($action === 'accept' && ($prev['status'] ?? '') === 'counter' && (float)($prev['counter_price'] ?? 0) > 0) {
        $agreedUnit = (float)$prev['counter_price'];
    }

    $rs[$ref] = [
        'status'        => $action,
        'counter_price' => $action === 'counter' ? $ctr : ($prev['counter_price'] ?? null),
        'counter_by'    => $action === 'counter' ? 'seller' : ($prev['counter_by'] ?? null),
        /* Butun turlar saklaniyor, yalnizca sonuncusu degil: hem sayaç
           buradan okunuyor hem de iki panel pazarligin gecmisini
           gosterebiliyor. Kim ne teklif etti sorusunun cevabi kayitta
           durmazsa, uzlasilan fiyat da savunulamaz. */
        'counters'      => (array)($prev['counters'] ?? []),
        'responded_at'  => date('c'),
        'responded_by'  => $actor['id'] ?? 'operator',
    ];
    if ($action === 'counter') {
        $rs[$ref]['counters'][] = ['by' => 'seller', 'price' => round($ctr, 2), 'at' => date('c')];
    }
    if ($action === 'accept') $rs[$ref]['agreed_unit'] = round($agreedUnit, 2);
    /* Karsi teklifle birlikte tek kullanimlik bir KABUL anahtari uretiliyor:
       alici e-postadaki dugmeyle, panele girmeden kabul edebilsin. Token
       yalnizca bu karsi teklife ait -- operator fiyati degistirip yeniden
       karsi teklif verirse yeni token uretilir ve eskisi calismaz, yani
       eski bir mektuptaki dugme yanlis fiyati baglayamaz. */
    if ($action === 'counter') $rs[$ref]['accept_token'] = bin2hex(random_bytes(16));
    vestra_write_json('offer_responses.json', $rs);

    $buyerAcc = auth_find($offerRow['email'] ?? '');
    $prodName = $listing
        ? trim(($listing['brand'] ?? '').' '.($listing['name'] ?? ''))
        : trim((string)($offerRow['product'] ?? ''));

    if ($notify && $buyerAcc) {
        require_once __DIR__.'/messages.php';
        vestra_msg_post_system($buyerAcc['id'], $actor['id'] ?? '', $listing['id'] ?? '', [
            'kind' => 'offer_response', 'ref' => $ref, 'status' => $action,
            'counter_price' => $action === 'counter' ? $ctr : null,
            'product' => $prodName,
        ]);
        require_once __DIR__.'/push.php';
        $pushTxt = match ($action) {
            'accept'  => ['VESTRA — offer accepted ✓', $prodName.' — your offer was accepted.'],
            'counter' => ['VESTRA — counter offer ↩', $prodName.' — seller counters at €'.number_format($ctr, 2).'/unit.'],
            default   => ['VESTRA — offer declined', $prodName.' — the seller declined this offer.'],
        };
        vestra_push_send($buyerAcc['id'], $pushTxt[0], $pushTxt[1], '/buyer?tab=offers');
    }

    require_once __DIR__.'/notify.php';
    /* null = gonderilecek bir sey yoktu (adres yok ya da notify kapali),
       true/false = saglayici ne dedi. Onceden vestra_send_mail'in donusu
       HIC OKUNMUYORDU: operator karsi teklifi kaydediyor, mektup
       gitmiyor, ve panelde bunu soyleyen hicbir sey olmuyordu. Teklif
       "yanitlandi" gorunurken alicinin haberi olmuyordu. */
    $mailed = null;
    if ($notify && !empty($offerRow['email']) && filter_var($offerRow['email'], FILTER_VALIDATE_EMAIL)) {
        $buyerName = $offerRow['company'] ?? ($buyerAcc['name'] ?? 'there');
        [$mSubject, $mBody, $mOpts] = vestra_tpl_offer_response(
            vestra_user_lang($buyerAcc), $action, $buyerName, $prodName, $ref,
            $action === 'counter' ? $ctr : null,
            $action === 'counter' ? vestra_offer_accept_url($ref, (string)$rs[$ref]['accept_token']) : null,
            vestra_offer_product_url($listing),
            /* Alici kac karsi teklif hakki KALDIGINI mektupta gormeli:
               "cevap verebilirsiniz" deyip hakkinin bittigini sayfada
               ogrenmesi, verilmemis bir sozu geri almak gibi okunur. */
            $action === 'counter' ? vestra_offer_counters_left($rs[$ref]) : 0
        );
        $mailed = (bool)vestra_send_mail($offerRow['email'], $mSubject, $mBody, $actor['email'] ?? '', $label, null, '', $mOpts);
    }

    $invoice = null;
    if ($action === 'accept') {
        /* $force VERILMIYOR: fatura OPERATOR ONAYIYLA kesiliyor (Admin ▸ Invoice
           approvals). Burada donen sey yalnizca 'pending' -- stok teyit edilmeden
           ve eksik alici bilgileri tamamlanmadan numara yakilmasin. */
        $invoice = vestra_offer_issue_invoice($ref, false);
    }

    return ['ok' => true, 'error' => '', 'invoice' => $invoice, 'mailed' => $mailed];
}

/* offers.csv'de tek satir. Birden fazla yerde ayni dongu yaziliyordu. */
function vestra_offer_row(string $ref): ?array {
    $ref = trim($ref);
    if ($ref === '') return null;
    foreach (vestra_read_csv('offers.csv') as $row) {
        if (($row['ref'] ?? '') === $ref) return $row;
    }
    return null;
}

function vestra_offer_accept_url(string $ref, string $token): string {
    return 'https://vestrasales.com/offer-accept?ref=' . rawurlencode($ref) . '&token=' . rawurlencode($token);
}

/* Ilanin canli urun sayfasi. Cozulemeyen SKU'da bos string doner ve mektuba
 * hicbir sey yazilmaz -- kirik bir baglanti, baglanti olmamasindan kotu. */
function vestra_offer_product_url(?array $listing): string {
    $id = trim((string)($listing['id'] ?? ''));
    return $id === '' ? '' : 'https://vestrasales.com/product?id=' . rawurlencode($id);
}

/* Kabul edilmis bir teklifin UZLASILAN fiyati. Karsi teklif verilip kabul
 * edilmisse o fiyat, yoksa alicinin ilk teklifi. Tek yerde: panel, fatura ve
 * mektup ayni rakami soylemeli. */
function vestra_offer_agreed_unit(string $ref, ?array $resp = null, ?array $offerRow = null): float {
    if ($resp === null)     { $all = vestra_read_json('offer_responses.json'); $resp = $all[$ref] ?? null; }
    if ($offerRow === null) { $offerRow = vestra_offer_row($ref); }
    if (!empty($resp['agreed_unit'])) return (float)$resp['agreed_unit'];
    if (($resp['status'] ?? '') === 'accept' && (float)($resp['counter_price'] ?? 0) > 0) return (float)$resp['counter_price'];
    return (float)($offerRow['offer_unit'] ?? 0);
}

/* FATURAYI KIM KESER. Sirasiyla: operatorun panelde verdigi karar > ilanin
 * satici hesabi > platform (Acerasoft LLC).
 *
 * Operator karari neden en ustte: ayni alicinin kabul ettigi teklifler birden
 * fazla ilana dagilabiliyor ve her ilanin seller_uid'i ayri -- sistem o zaman
 * tek bir alis icin iki ayri saticidan iki fatura kesiyordu, uzerinde bazen
 * hic satici olmayan (seller_uid bos) satirlarla birlikte. Belgeyi hangi tuzel
 * kisinin kesecegi ticari bir karar, ilan kaydinin yan etkisi degil; operator
 * "satistan sonra hangi fatura hangi saticiya ait benim karar vermem gerekiyor"
 * dedi (1 Eyl 2026). Karar offer_responses.json'da ref basina saklanir, yani
 * belge ile birlikte kayda gecer.
 *
 * 'vestra' ACIK bir secim: platformun kendi kimliginden kesilsin demek, ve
 * ilanda bir satici olsa bile ona donmez.
 *
 * Secilen hesap artik yoksa platforma dusulur -- ama bu yola normalde
 * girilmez: secim admin.php'de KAYIT ANINDA dogrulanir.
 *
 * $pickOverride: KAYDETMEDEN "bu satici secilseydi" cozumu -- onizleme
 * taslagi icin. Kalici secimle ayni oncelige oturur; bos ise kayit okunur. */
function vestra_offer_invoice_seller(string $ref, ?array $listing = null, string $pickOverride = ''): array {
    require_once __DIR__.'/invoice.php';
    $rs   = vestra_read_json('offer_responses.json');
    $pick = $pickOverride !== '' ? $pickOverride : trim((string)($rs[$ref]['invoice_seller_uid'] ?? ''));
    $uid  = $pick !== '' ? $pick : (string)($listing['seller_uid'] ?? '');
    if ($uid !== '' && $uid !== 'vestra') {
        foreach (auth_accounts() as $sa) { if (($sa['id'] ?? '') === $uid) return $sa; }
    }
    return vestra_platform_seller();
}

/* Teklifin FATURA yuku: alici blogu + tek satir + fatura kesecek satici.
 * Uc yerde (operator kabulu, alici kabulu, panelden onayli kesim) elle
 * kuruluyordu; ucu de ayni rakami uretmek ZORUNDA, cunku ayni belge.
 * Ayri kopyalar zamanla ayrisir ve ayrisma faturada gorunur. */
function vestra_offer_invoice_payload(string $ref, string $sellerPickOverride = '', ?string $vatNoteOverride = null, ?float $shippingOverride = null): ?array {
    $offerRow = vestra_offer_row($ref);
    if (!$offerRow) return null;
    $listing  = vestra_listing_by_sku($offerRow['sku'] ?? '');
    $buyerAcc = auth_find($offerRow['email'] ?? '') ?: [];
    $unit = vestra_offer_agreed_unit($ref, null, $offerRow);
    $qty  = (int)($offerRow['qty'] ?? 0);

    $sellerAcc = vestra_offer_invoice_seller($ref, $listing, $sellerPickOverride);

    /* KDV satiri (serbest metin, orn. "TVA non applicable -- article 293 B du
       CGI" ya da "Intra-Community supply -- reverse charge"). Siparis faturasi
       bunu orders.csv'den okuyordu, teklif faturasinin ise koyacak yeri YOKTU --
       oysa bes haneli bir satista KDV'nin neden sifir oldugunu belgenin uzerinde
       soylemek zorunlu (gerekce render'daki shipRows blogunda). Operator onay
       ekranindan girer; null = kayitli degeri oku, '' dahil verilmis deger =
       onizleme gecersiz kilmasi (satici secimiyle ayni desen). */
    $rs = vestra_read_json('offer_responses.json');
    $vatNote = $vatNoteOverride;
    if ($vatNote === null) $vatNote = trim((string)($rs[$ref]['invoice_vat_note'] ?? ''));
    /* KARGO: operator onay ekranindan girer, ref'in kaydinda durur
       (invoice_shipping). Render zaten meta['shipping'] destekliyor --
       Goods total + Shipping + Grand total olarak ayri satirlarda basar;
       0 ise hicbir sey degismez. Override onizleme/redraft icin. */
    $shipping = $shippingOverride !== null ? $shippingOverride
              : (float)($rs[$ref]['invoice_shipping'] ?? 0);

    return [
        'meta' => [
            'ref' => $ref, 'date' => $offerRow['timestamp'] ?? date('c'),
            'vat_note' => trim($vatNote),
            'shipping' => round(max(0.0, $shipping), 2),
            'buyer' => [
                'company' => ($offerRow['company'] ?? '') ?: (string)($buyerAcc['company'] ?? ''),
                'vat'     => (string)($buyerAcc['vat_id'] ?? ''),
                'reg'     => (string)($buyerAcc['reg_number'] ?? ''),
                'name'    => (string)($buyerAcc['name'] ?? ''),
                'email'   => (string)($offerRow['email'] ?? ''),
                'country' => (string)($buyerAcc['country'] ?? ''),
                'address' => (string)($buyerAcc['address'] ?? ''),
            ],
        ],
        'items' => [[
            'sku'    => $listing['sku'] ?? ($offerRow['sku'] ?? ''),
            'brand'  => $listing['brand'] ?? '',
            'name'   => $listing['name'] ?? ($offerRow['product'] ?? ''),
            'colors' => [],
            'qty'    => $qty,
            /* Satir toplami YENIDEN hesaplaniyor: CSV'deki offer_total ilk
               teklifin toplami, pazarlik sonrasi artik dogru degil. */
            'unit'   => round($unit, 2),
            'line'   => round($unit * $qty, 2),
        ]],
        'seller' => $sellerAcc,
        'unit'   => round($unit, 2),
        'qty'    => $qty,
    ];
}

/* SECILEN TEKLIFLERDEN TEK FATURA (operator karari, 1 Eyl 2026: "urunler
 * secilip tek saticiya tek fatura kesilebilmeli"). Ayni alicinin kabul ettigi
 * teklifler ayri ayri faturalaniyordu -- Daymond dosyasinda 6 kalem 6 ayri
 * numara demekti. Bu kurucu, secilen ref'lerden TEK belge yuku uretir.
 *
 * Kurallar:
 * - Butun ref'ler AYNI alicinin kabul edilmis teklifi olmali. Farkli alicilari
 *   tek faturada toplamak anlamsiz; kabul edilmemis teklif faturalanamaz
 *   (KURAL 5). Ihlalde yuk degil ['error' => gerekce] doner ve HICBIR SEY
 *   uretilmez -- yarim liste sessizce kesilmez.
 * - Satici TEK: oncelik operator secimi ($sellerPickOverride ya da birincil
 *   ref'in kayitli secimi) > butun ilanlar AYNI saticiyi gosteriyorsa o.
 *   Ilanlar farkli saticilara dagiliyorsa ve secim yoksa REDDEDILIR --
 *   "tek saticiya tek fatura"nin tek saticisini sistem tahmin edemez.
 * - Birincil ref = secimdeki ilk ref. Belge numarasi/dosyasi onun adina
 *   yazilir; digerleri kesim sirasinda invoice_group_ref ile ona baglanir
 *   (vestra_invoices_for_ref o bagi izler, alici hangi teklifinden bakarsa
 *   baksin ayni belgeyi bulur).
 * - Her satirin fiyati o teklifin ANLASILAN birimi (vestra_offer_agreed_unit,
 *   KURAL 5); satir adina teklif ref'i eklenir ki alici hangi satirin hangi
 *   kabul oldugunu belgeden okuyabilsin.
 * - Tarih = kesim gunu: teklifler farkli gunlerde kabul edilmis olabilir,
 *   tek belgeye iclerinden birinin tarihini vermek digerlerini yanlislar. */
/* $allowInvoiced YALNIZCA redraft icin: kesilmis belgeyi AYNI numarayla
 * yeniden cizerken uyeler elbette faturali gorunur -- normal kesimde ise bu
 * kontrol ikinci numara yakilmasini onluyor, acik kalmali. */
function vestra_offers_combined_invoice_payload(array $refs, string $sellerPickOverride = '', ?string $vatNoteOverride = null, ?float $shippingOverride = null, bool $allowInvoiced = false): array {
    require_once __DIR__.'/invoice.php';
    $refs = array_values(array_unique(array_filter(array_map(
        fn($r) => preg_replace('/[^A-Za-z0-9_-]/', '', (string)$r), $refs))));
    if (!$refs) return ['error' => 'Hiç teklif seçilmedi.'];

    $rs = vestra_read_json('offer_responses.json');
    $items = []; $buyerEmail = null; $buyerRow = null; $listingSellers = [];
    foreach ($refs as $r) {
        $row = vestra_offer_row($r);
        if (!$row) return ['error' => "Teklif bulunamadı: {$r}"];
        if ((($rs[$r]['status'] ?? '')) !== 'accept') return ['error' => "Teklif kabul edilmiş değil: {$r} — kabul edilmemiş teklife fatura kesilmez."];
        if (!$allowInvoiced && count(vestra_invoices_for_ref($r)) > 0) return ['error' => "Bu teklifin faturası zaten kesilmiş: {$r} — aynı satıra ikinci numara yakılmaz."];
        $em = strtolower(trim((string)($row['email'] ?? '')));
        if ($buyerEmail === null) { $buyerEmail = $em; $buyerRow = $row; }
        elseif ($em !== $buyerEmail) return ['error' => "Seçilen teklifler aynı alıcıya ait değil ({$r} farklı) — tek fatura tek alıcıya kesilir."];

        $listing = vestra_listing_by_sku($row['sku'] ?? '');
        $listingSellers[(string)($listing['seller_uid'] ?? '')] = true;
        $unit = vestra_offer_agreed_unit($r, null, $row);
        $qty  = (int)($row['qty'] ?? 0);
        $items[] = [
            'sku'    => $listing['sku'] ?? ($row['sku'] ?? ''),
            'brand'  => $listing['brand'] ?? '',
            /* Ref satirda: alti kabul tek belgeye dusunce satirlarin hangi
               pazarliktan geldigi baska turlu gorunmez. */
            'name'   => trim((string)($listing['name'] ?? ($row['product'] ?? ''))).'  ·  '.$r,
            'colors' => [],
            'qty'    => $qty,
            'unit'   => round($unit, 2),
            'line'   => round($unit * $qty, 2),
        ];
    }

    $primary = $refs[0];
    $pick = trim($sellerPickOverride) !== '' ? trim($sellerPickOverride)
          : trim((string)($rs[$primary]['invoice_seller_uid'] ?? ''));
    if ($pick === '') {
        /* Secim yok: ancak ILANLARIN HEPSI ayni saticiyi gosteriyorsa oraya
           duselim; 'vestra' (bos seller_uid) da dahil tek deger olmali. */
        if (count($listingSellers) !== 1) return ['error' => 'Seçilen ürünler farklı satıcılara ait — faturayı hangi satıcının keseceğini listeden seçin.'];
        $pick = (string)array_key_first($listingSellers);
        if ($pick === '') $pick = 'vestra';
    }
    $sellerAcc = vestra_offer_invoice_seller($primary, null, $pick);

    $vatNote = $vatNoteOverride;
    if ($vatNote === null) $vatNote = trim((string)($rs[$primary]['invoice_vat_note'] ?? ''));
    $shipping = $shippingOverride !== null ? $shippingOverride
              : (float)($rs[$primary]['invoice_shipping'] ?? 0);

    $buyerAcc = auth_find($buyerRow['email'] ?? '') ?: [];
    return [
        'refs' => $refs,
        'meta' => [
            'ref' => $primary, 'date' => date('c'),
            'vat_note' => trim((string)$vatNote),
            'shipping' => round(max(0.0, $shipping), 2),
            'buyer' => [
                'company' => ($buyerRow['company'] ?? '') ?: (string)($buyerAcc['company'] ?? ''),
                'vat'     => (string)($buyerAcc['vat_id'] ?? ''),
                'reg'     => (string)($buyerAcc['reg_number'] ?? ''),
                'name'    => (string)($buyerAcc['name'] ?? ''),
                'email'   => (string)($buyerRow['email'] ?? ''),
                'country' => (string)($buyerAcc['country'] ?? ''),
                'address' => (string)($buyerAcc['address'] ?? ''),
            ],
        ],
        'items'  => $items,
        'seller' => $sellerAcc,
        'seller_pick' => $pick,
        'total'  => round(array_sum(array_column($items, 'line')), 2),
        'qty'    => (int)array_sum(array_column($items, 'qty')),
    ];
}

/* KABUL EDILEN TEKLIF(LER) FATURALANINCA SIPARIS OLUR (operator karari,
 * 1 Eyl 2026: "bir cok teklif kabul olursa ve tek saticida olursa tek order
 * olarak gozukur, order bolumune de gitmeli"). Teklifler yalnizca teklif
 * dunyasinda yasiyordu: admin Orders sekmesi ve alicinin My orders sayfasi
 * satisi hic gormuyordu.
 *
 * Satir orders.csv'ye CHECKOUT'UN KENDI SEMASIYLA yazilir (order.php'deki
 * $ORDER_CSV_HEADER) ve ref = faturanin BIRINCIL ref'i. Boylece:
 *   - vestra_invoices_for_ref(ref) ayni faturayi bulur -> siparis "fatura
 *     bekliyor" kuyruguna DUSMEZ, dosyada tek belge kalir;
 *   - alicinin My orders'i ve admin siparis dosyasi kendiliginden calisir.
 * Tutarlar pazarligin ANLASILAN rakamlari: subtotal=mal, commission=0,
 * payout=mal (teklif satisinda alici ustune platform ucreti binmez --
 * faturada olmayan bir rakami siparise yazmak iki belgeyi celistirir),
 * total=mal+kargo = faturanin genel toplami.
 *
 * IDEMPOTENT: ref zaten orders.csv'de varsa dokunmaz (redraft ikinci satir
 * uretmesin). Yazim basarisi geri OKUNARAK dogrulanmaz cunku append yalnizca
 * tek satir; basarisizlikta false doner ve cagiran operatore soyler. */
function vestra_offer_order_ensure(array $p, bool $update = false): bool {
    $ref = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($p['meta']['ref'] ?? ''));
    if ($ref === '' || empty($p['items'])) return false;
    /* $update=false: satir varsa DOKUNMA (ayni kesim iki kez calisirsa ikinci
       satir uretmesin). $update=true: REDRAFT -- belge degisti, siparis de
       degismeli. Daymond dosyasinda bu eksikti: fatura 4 kalem / 3.950 EUR
       derken siparis satiri eski 2 kalem / 1.600 EUR'da kalmisti, yani ayni
       satis alicinin "My orders" sayfasinda ve admin Orders'ta faturadan
       BASKA bir rakam gosteriyordu. */
    $exists = false;
    foreach (vestra_read_csv('orders.csv') as $r) { if (($r['ref'] ?? '') === $ref) { $exists = true; break; } }
    if ($exists && !$update) return true;
    if ($exists) {
        /* Eski satiri cikar; asagisi yenisini ekliyor. Once YEDEK: bu dosya
           elle geri yazilamaz. */
        $f0 = dirname(__DIR__).'/data/orders.csv';
        if (is_file($f0)) {
            @copy($f0, $f0.'.bak-redraft-'.date('Ymd_His'));
            $rows = vestra_read_csv('orders.csv');
            $head = $rows ? array_keys($rows[0]) : [];
            $keep = array_values(array_filter($rows, fn($r) => ($r['ref'] ?? '') !== $ref));
            if ($head) {
                $tmp = $f0.'.tmp';
                if ($out = @fopen($tmp, 'w')) {
                    fputcsv($out, $head, ',', '"', '\\');
                    foreach ($keep as $r) fputcsv($out, array_values($r), ',', '"', '\\');
                    fclose($out);
                    if (!@rename($tmp, $f0)) { @unlink($tmp); return false; }
                } else { return false; }
            }
        }
    }

    $goods = 0.0;
    $items = [];
    foreach ($p['items'] as $it) {
        $goods  += (float)($it['line'] ?? 0);
        $items[] = (int)($it['qty'] ?? 0).'x '.(string)($it['sku'] ?? '').' @'.number_format((float)($it['unit'] ?? 0), 2, '.', '');
    }
    $goods    = round($goods, 2);
    $shipping = round((float)($p['meta']['shipping'] ?? 0), 2);
    $b        = (array)($p['meta']['buyer'] ?? []);
    $refsNote = implode(', ', (array)($p['refs'] ?? [$ref]));
    $notes    = 'Payment: Bank transfer. Created from accepted offer(s) '.$refsNote.' — invoiced together.'
              .($shipping > 0 ? ' Shipping EUR '.number_format($shipping, 2, '.', '').'.' : '');

    $dir = dirname(__DIR__).'/data'; if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $file = $dir.'/orders.csv'; $new = !file_exists($file);
    /* shipping/shipping_label KUYRUK kolonlari: okuyucu kisa satirlari ''
       ile tamamladigi icin eski kayitlar bozulmaz (voucher_code/discount ile
       ayni desen). Kargo AYRI kolonda durur ki paneller "mal + kargo = toplam"
       dokumunu gosterebilsin ve vestra_render_order_detail ile
       vestra_order_invoice_payloads onu dogrudan okusun -- toplamin icine
       gomulu bir kargo, hicbir ekranda ayristirlamazdi. */
    $head = ['timestamp','ref','company','vat','name','email','country','phone','items','subtotal',
             'commission','payout','total','notes','consent','terms_version','voucher_code','discount',
             'shipping','shipping_label'];
    if (!$new && function_exists('vestra_csv_ensure_header')) vestra_csv_ensure_header('orders.csv', $head);
    $fh = @fopen($file, 'a');
    if (!$fh) return false;
    if ($new) fputcsv($fh, $head, ',', '"', '\\');
    fputcsv($fh, [date('c'), $ref, (string)($b['company'] ?? ''), (string)($b['vat'] ?? ''),
        (string)($b['name'] ?? ''), (string)($b['email'] ?? ''), (string)($b['country'] ?? ''), '',
        implode(' | ', $items), number_format($goods, 2, '.', ''), '0.00',
        number_format($goods, 2, '.', ''), number_format($goods + $shipping, 2, '.', ''),
        $notes, 'offer', defined('VESTRA_TERMS_VERSION') ? VESTRA_TERMS_VERSION : '', '', '',
        $shipping > 0 ? number_format($shipping, 2, '.', '') : '',
        $shipping > 0 ? 'Shipping' : ''], ',', '"', '\\');
    fclose($fh);
    return true;
}

/* KESILMIS teklif faturasinin YENIDEN CIZIM yuku (redraft: ayni numara,
 * duzeltilmis icerik -- orn. kargo eklemek). Birlesik kesilmisse
 * (invoice_members) butun uyelerden yeniden kurar, degilse tek ref'ten.
 * Belge zaten var oldugu icin faturali-olma kontrolu BILEREK atlanir;
 * numara yakilmaz, dosya yerinde yeniden yazilir (vestra_ensure_invoice
 * redraft=true). Ya tam yuk ya ['error'=>gerekce]. */
function vestra_offer_invoice_redraft_payload(string $ref, ?float $shippingOverride = null, ?array $membersOverride = null): array {
    require_once __DIR__.'/invoice.php';
    $ref = preg_replace('/[^A-Za-z0-9_-]/', '', $ref);
    if ($ref === '') return ['error' => 'Ref boş.'];
    if (count(vestra_invoices_for_ref($ref, false)) === 0) {
        return ['error' => "Bu ref'in kesilmiş bir faturası yok: {$ref} — düzeltme değil, normal kesim gerekiyor."];
    }
    $rs = vestra_read_json('offer_responses.json');
    /* UYE LISTESI DEGISTIRILEBILIR: alici bir kalemi iptal ettirdiginde
       (Daymond, 1 Eyl 2026) belgeden o satiri cikarmak gerekiyor; kalan
       kalemler eklenebilmeli de. Verilmezse kayitli uyeler kullanilir.
       BIRINCIL ref listede KALMAK ZORUNDA: numara ve dosya adi ona bagli
       (vestra_invoice_file), listeden dusurmek belgeyi sahipsiz birakirdi. */
    if ($membersOverride !== null) {
        $members = array_values(array_unique(array_filter(array_map(
            fn($r) => preg_replace('/[^A-Za-z0-9_-]/', '', (string)$r), $membersOverride))));
        if (!$members) return ['error' => 'En az bir kalem seçilmeli.'];
        if (!in_array($ref, $members, true)) {
            return ['error' => "Belge {$ref} adına kesildi; numara ona bağlı olduğu için {$ref} listede kalmalı. Bu kalemi tamamen çıkarmak gerekiyorsa fatura iptal edilip yeniden kesilmeli."];
        }
    } else {
        $members = array_values(array_filter(array_map('strval', (array)($rs[$ref]['invoice_members'] ?? []))));
    }
    if (count($members) > 1) {
        $p = vestra_offers_combined_invoice_payload($members, '', null, $shippingOverride, true);
    } else {
        $p = vestra_offer_invoice_payload($ref, '', null, $shippingOverride);
        if (!$p) return ['error' => "Teklif bulunamadı: {$ref}"];
        $p['refs'] = [$ref];
    }
    if (!empty($p['error'])) return $p;
    /* Tarih ve birincil ref BELGEDEKI gibi kalmali: redraft ayni belgeyi
       duzeltir, yeni bir belge kurmaz. Birlesik kurucu tarihi bugune atar --
       burada onemi yok cunku vestra_ensure_invoice redraft'ta mevcut meta
       dosyasindaki numarayi koruyor; ref zaten birincil. */
    return $p;
}

/* KESILMIS teklif faturasini AYNI numarayla yeniden yazar, kayitlari
 * gunceller ve aliciya gonderir. Panel ve is akisi AYNI fonksiyonu cagirir --
 * iki ayri kesim yolu zamanla ayrisir ve ayrisma BELGEDE gorunur (bu dosyada
 * defalarca yazili ders).
 *
 * $copyTo verilirse ayni mektubun bir kopyasi oraya da gider (operatorun
 * "bana da gonder" istegi); alici mektubu her halukarda gider.
 * Ya ['ok'=>true, 'no'=>...] ya ['error'=>gerekce] doner. */
/* $notifyBuyer=false: belge ve kayitlar duzeltilir ama ALICIYA MEKTUP
 * GITMEZ. Yalnizca panel/kayit tarafi bozuksa gereklidir -- alici dogru
 * PDF'i zaten almisken ikinci bir "faturaniz hazir" mektubu, degismemis bir
 * belgeyi degismis gibi gosterir ve gereksiz soru dogurur. */
function vestra_offer_invoice_redraft_apply(string $ref, ?float $ship = null, ?array $members = null, string $copyTo = '', bool $notifyBuyer = true): array {
    require_once __DIR__.'/invoice.php';
    require_once __DIR__.'/notify.php';
    $ref = preg_replace('/[^A-Za-z0-9_-]/', '', $ref);
    $p = vestra_offer_invoice_redraft_payload($ref, $ship, $members);
    if (!empty($p['error'])) return $p;

    /* Kayit belgeden ONCE: kesim yarida kalirsa kayit dogru kalir ve islem
       tekrarlanabilir. Tersi sirada belge kayitsiz kalirdi. */
    $rs = vestra_read_json('offer_responses.json');
    if ($ship !== null) $rs[$ref]['invoice_shipping'] = $ship;
    if ($members !== null) {
        /* Cikarilan uyelerin bagi SILINIYOR: kalsaydi alici faturadan
           cikardigimiz kalemin faturasini gormeye devam ederdi. Bagi kopan
           teklif faturasiz olur ve onay kuyruguna doner -- karar operatorun. */
        foreach ((array)($rs[$ref]['invoice_members'] ?? []) as $o) {
            $o = (string)$o;
            if ($o !== $ref && !in_array($o, $p['refs'], true)) unset($rs[$o]['invoice_group_ref']);
        }
        foreach ($p['refs'] as $r) { if ($r !== $ref) $rs[$r]['invoice_group_ref'] = $ref; }
        $rs[$ref]['invoice_members'] = $p['refs'];
    }
    vestra_write_json('offer_responses.json', $rs);

    $iv = vestra_ensure_invoice($p['meta'], $p['items'], $p['seller'], true, true);
    if (!$iv || ($iv['no'] ?? '') === '' || empty($iv['redrafted'])) {
        return ['error' => 'Belge yeniden yazılamadı — numara ya da dosya bulunamadı.'];
    }
    /* REDRAFT: siparis satiri da GUNCELLENIR -- belge degistiyse siparis de
       degismeli, yoksa ayni satis iki farkli rakam gosterir. */
    vestra_offer_order_ensure($p, true);

    $goods = 0.0; $lines = '';
    foreach ($p['items'] as $it) {
        $goods += (float)$it['line'];
        $lines .= sprintf("  %-16s %4d x EUR %s = EUR %s\n", $it['sku'], (int)$it['qty'],
                  number_format((float)$it['unit'], 2), number_format((float)$it['line'], 2));
    }
    $shp  = (float)($p['meta']['shipping'] ?? 0);
    $subj = "VESTRA — your invoice {$iv['no']} is ready";
    $body = "Hello ".(($p['meta']['buyer']['company'] ?? '') ?: 'there').",\n\n"
          ."Your invoice ({$iv['no']}) is ready — the corrected PDF is attached and replaces any earlier copy of the same invoice number.\n\n"
          .$lines."\n  Goods total : EUR ".number_format($goods, 2)."\n"
          .($shp > 0 ? "  Shipping    : EUR ".number_format($shp, 2)."\n" : '')
          ."  TOTAL DUE   : EUR ".number_format($goods + $shp, 2)."\n\n"
          ."Please pay by bank transfer to the account shown on the invoice, quoting reference ".$p['meta']['ref'].".\n"
          ."You can also download it any time under My offers.\n\n"
          ."View: https://vestrasales.com/buyer?tab=offers\n\n— VESTRA · vestrasales.com";
    $att  = is_file((string)($iv['path'] ?? ''))
          ? ['attachments' => [['name' => 'Invoice-'.$iv['no'].'.pdf', 'path' => $iv['path']]]] : [];

    $em   = (string)($p['meta']['buyer']['email'] ?? '');
    $sent = false;
    if ($notifyBuyer && filter_var($em, FILTER_VALIDATE_EMAIL)) {
        $sent = (bool)vestra_send_mail($em, $subj, $body, '', '', null, '', $att);
    }
    $copied = false;
    if ($copyTo !== '' && filter_var($copyTo, FILTER_VALIDATE_EMAIL)) {
        /* BIREBIR AYNI mektup (operator karari, 1 Eyl 2026: "musteriye
           gonderecegin emailin aynisini bana gonder"). Onceden konuya
           "[KOPYA]" onegi ve Turkce bir aciklama satiri ekleniyordu; operator
           musterinin GORDUGU seyi gormek istiyor, o yuzden hicbiri eklenmiyor.
           Kopya oldugu zaten belli: govde "Hello <alici sirketi>" diye
           basliyor ve ek ayni PDF. */
        $copied = (bool)vestra_send_mail($copyTo, $subj, $body, '', '', null, '', $att);
    }
    return ['ok' => true, 'no' => $iv['no'], 'refs' => $p['refs'], 'total' => round($goods + $shp, 2),
            'sent' => $sent, 'copied' => $copied, 'notified' => $notifyBuyer,
            'seller' => vestra_invoice_issuer_name($p['seller'], 'Acerasoft LLC')];
}

/* $force=false -> yalnizca 'pending' doner, DOSYA URETMEZ (kabul aninda).
 * $force=true  -> numarayi yakar ve PDF'i yazar (operator onayindan sonra). */
function vestra_offer_issue_invoice(string $ref, bool $force): ?array {
    $p = vestra_offer_invoice_payload($ref);
    if (!$p) return null;
    require_once __DIR__.'/invoice.php';
    return vestra_ensure_invoice($p['meta'], $p['items'], $p['seller'], $force);
}

/* Alici KARSI TEKLIFI REDDEDIYOR. Kabulun aynasi: ayni token, ayni tek
 * kullanimlik mantik. Reddin de bir yolu olmali -- yalnizca kabul dugmesi
 * koymak, "hayir" demek isteyen aliciyi cevapsiz birakip pazarligi belirsiz
 * biraktiriyor; operator de bekleyip bekemeyecegini bilemiyor.
 * Kaydinda declined_by='buyer' duruyor: saticinin reddi ile alicinin reddi
 * ayni sey degil ve panelde ayri gorunmeli. */
function vestra_offer_decline_counter(string $ref, string $token): array {
    $ref = trim($ref); $token = trim($token);
    if ($ref === '' || $token === '') return ['ok' => false, 'error' => 'link eksik'];

    $rs   = vestra_read_json('offer_responses.json');
    $resp = $rs[$ref] ?? null;
    if (!$resp || vestra_offer_turn($resp) !== 'buyer') {
        return ['ok' => false, 'error' => 'bu karsi teklif artik gecerli degil'];
    }
    $want = (string)($resp['accept_token'] ?? '');
    if ($want === '' || !hash_equals($want, $token)) return ['ok' => false, 'error' => 'link gecersiz ya da kullanilmis'];

    $offerRow = vestra_offer_row($ref);
    if (!$offerRow) return ['ok' => false, 'error' => 'teklif bulunamadi'];
    $listing  = vestra_listing_by_sku($offerRow['sku'] ?? '');
    $prodName = $listing ? trim(($listing['brand'] ?? '').' '.($listing['name'] ?? ''))
                         : trim((string)($offerRow['product'] ?? ''));
    $unit = (float)($resp['counter_price'] ?? 0);

    $rs[$ref] = [
        'status'        => 'decline',
        'counter_price' => $unit,
        'responded_at'  => $resp['responded_at'] ?? date('c'),
        'responded_by'  => $resp['responded_by'] ?? 'operator',
        'declined_at'   => date('c'),
        'declined_by'   => 'buyer',
    ];
    vestra_write_json('offer_responses.json', $rs);

    require_once __DIR__.'/notify.php';
    vestra_notify(
        "Counter offer DECLINED by buyer — {$ref}",
        "The buyer declined the counter offer.\n\n"
      . "Reference : {$ref}\n"
      . "Product   : {$prodName}\n"
      . "SKU       : ".($offerRow['sku'] ?? '')."\n"
      . "Quantity  : ".(int)($offerRow['qty'] ?? 0)."\n"
      . "Their offer  : EUR ".number_format((float)($offerRow['offer_unit'] ?? 0), 2)."/unit\n"
      . "Our counter  : EUR ".number_format($unit, 2)."/unit\n"
      . "Buyer     : ".($offerRow['email'] ?? '')."\n\n"
      . "If you want to keep this one alive, send a new counter: https://vestrasales.com/admin?tab=offers"
    );

    $buyerAcc = auth_find($offerRow['email'] ?? '');
    if ($buyerAcc) {
        require_once __DIR__.'/messages.php';
        vestra_msg_post_system($buyerAcc['id'], (string)($listing['seller_uid'] ?? ''), $listing['id'] ?? '', [
            'kind' => 'offer_response', 'ref' => $ref, 'status' => 'decline',
            'counter_price' => $unit, 'product' => $prodName,
        ]);
    }
    return ['ok' => true, 'error' => '', 'offer' => $offerRow, 'unit' => $unit];
}

/* Karsi teklifi ALICI kabul ediyor (e-postadaki dugme ya da panel).
 * vestra_offer_respond()'dan AYRI bir fonksiyon, cunku muhatap ters:
 * orada satici/operator yanit veriyor ve alici bilgilendiriliyor; burada
 * alici kabul ediyor, haber verilmesi gereken taraf OPERATOR. Ayni
 * fonksiyona sikistirmak, aliciya "saticiniz teklifinizi kabul etti"
 * yazan bir mektup gonderirdi -- olmayan bir olayi anlatan bir bildirim.
 *
 * Token tek kullanimlik: kabul edilince siliniyor. Bu yuzden ayni linke
 * ikinci kez basmak "zaten kabul edildi" ekrani verir, ikinci bir fatura
 * ya da ikinci bir bildirim degil.
 *
 * @return array ['ok'=>bool,'error'=>string,'offer'=>?array,'unit'=>float,'invoice'=>?array]
 */
function vestra_offer_accept_counter(string $ref, string $token): array {
    $ref = trim($ref); $token = trim($token);
    $fail = fn(string $e) => ['ok' => false, 'error' => $e, 'offer' => null, 'unit' => 0.0, 'invoice' => null];
    if ($ref === '' || $token === '') return $fail('link eksik');

    $rs   = vestra_read_json('offer_responses.json');
    $resp = $rs[$ref] ?? null;
    if (!$resp) return $fail('bu teklife henuz yanit verilmemis');
    /* Sira alicida OLMALI. Alici kendi verdigi karsi teklifi kabul
       edemez: o teklif henuz saticinin onunde ve fiyati baglayan taraf
       kendisi olurdu. */
    if (vestra_offer_turn($resp) !== 'buyer') {
        return $fail(match ((string)($resp['status'] ?? '')) {
            'accept'  => 'zaten kabul edildi',
            'decline' => 'bu pazarlik kapandi',
            default   => 'sira sizde degil — son karsi teklifi siz verdiniz, satici yanit veriyor',
        });
    }
    /* hash_equals: token karsilastirmasi zamanlama sizdirmasin. */
    $want = (string)($resp['accept_token'] ?? '');
    if ($want === '' || !hash_equals($want, $token)) return $fail('link gecersiz ya da kullanilmis');

    $unit = (float)($resp['counter_price'] ?? 0);
    if ($unit <= 0) return $fail('karsi teklif fiyati okunamadi');

    $offerRow = vestra_offer_row($ref);
    if (!$offerRow) return $fail('teklif bulunamadi');
    $qty     = (int)($offerRow['qty'] ?? 0);
    $listing = vestra_listing_by_sku($offerRow['sku'] ?? '');
    $prodName = $listing
        ? trim(($listing['brand'] ?? '').' '.($listing['name'] ?? ''))
        : trim((string)($offerRow['product'] ?? ''));

    /* Once diske: fatura/mektup adimlarindan biri patlarsa teklif kabul
       edilmis kalir. Tersi, ayni linke tekrar basildiginda ikinci fatura
       demek olurdu. */
    $rs[$ref] = [
        'status'        => 'accept',
        'counter_price' => $unit,
        'agreed_unit'   => round($unit, 2),
        'responded_at'  => $resp['responded_at'] ?? date('c'),
        'responded_by'  => $resp['responded_by'] ?? 'operator',
        'accepted_at'   => date('c'),
        'accepted_by'   => 'buyer',
    ];
    vestra_write_json('offer_responses.json', $rs);

    $buyerAcc  = auth_find($offerRow['email'] ?? '');
    $sellerUid = (string)($listing['seller_uid'] ?? '');
    /* PDF URETILMIYOR: fatura operator onayiyla kesiliyor (Admin ▸ Invoice
       approvals). Burada donen 'pending', alicinin panelinde "invoice being
       prepared" olarak gorunur. */
    $invoice = vestra_offer_issue_invoice($ref, false);

    require_once __DIR__.'/notify.php';
    /* OPERATOR haberdar edilmeli: pazarlik kapandi, mal ayrilacak ve fatura
       kesilecek. Bu adim olmadan kabul yalnizca JSON'da durur ve kimse
       bakmadikca alici cevapsiz bekler. */
    vestra_notify(
        "Counter offer ACCEPTED by buyer — {$ref}",
        "The buyer accepted the counter offer.\n\n"
      . "Reference : {$ref}\n"
      . "Product   : {$prodName}\n"
      . "SKU       : ".($offerRow['sku'] ?? '')."\n"
      . "Quantity  : {$qty}\n"
      . "Agreed    : EUR ".number_format($unit, 2)."/unit  (total EUR ".number_format($unit * $qty, 2).")\n"
      . "Buyer     : ".($offerRow['email'] ?? '')."\n\n"
      . "Issue the invoice: https://vestrasales.com/admin?tab=offers"
    );

    if ($buyerAcc) {
        require_once __DIR__.'/messages.php';
        vestra_msg_post_system($buyerAcc['id'], $sellerUid, $listing['id'] ?? '', [
            'kind' => 'offer_response', 'ref' => $ref, 'status' => 'accept',
            'counter_price' => $unit, 'product' => $prodName,
        ]);
        require_once __DIR__.'/push.php';
        vestra_push_send($buyerAcc['id'], 'VESTRA — counter offer accepted ✓',
            $prodName.' — agreed at €'.number_format($unit, 2).'/unit.', '/buyer?tab=offers');
    }

    if (!empty($offerRow['email']) && filter_var($offerRow['email'], FILTER_VALIDATE_EMAIL)) {
        [$s, $b, $o] = vestra_tpl_offer_counter_accepted(
            vestra_user_lang($buyerAcc),
            $offerRow['company'] ?? ($buyerAcc['name'] ?? 'there'),
            $prodName, $ref, $unit, $qty
        );
        vestra_send_mail($offerRow['email'], $s, $b, 'support@vestrasales.com', 'VESTRA', null, '', $o);
    }

    return ['ok' => true, 'error' => '', 'offer' => $offerRow, 'unit' => $unit, 'invoice' => $invoice];
}

/* ALICI karsi teklif veriyor (pazarligi geri cevirmek yerine surdurmek).
 * Kabul/ret ile ayni token, ayni tek kullanimlik mantik. Fark: pazarlik
 * KAPANMIYOR, sira saticiya geciyor -- o yuzden bu islem token'i yakiyor
 * ama yeni bir tane URETMIYOR; yeni token, satici tekrar karsi teklif
 * verirse cikar. Boylece eski bir mektuptaki dugme, sira karsi tarafa
 * gectikten sonra calismaz.
 *
 * Tur sayaci IKI TARAFIN TOPLAMI: "en fazla 3 karsi teklif" pazarligin
 * tamaminda 3 demek, taraf basina 3 degil. Dolunca yalnizca kabul/ret
 * kalir.
 *
 * @return array ['ok','error','left'=>kalan tur,'offer','unit']
 */
function vestra_offer_counter_by_buyer(string $ref, string $token, float $price): array {
    $ref = trim($ref); $token = trim($token);
    $fail = fn(string $e, int $l = 0) => ['ok' => false, 'error' => $e, 'left' => $l, 'offer' => null, 'unit' => 0.0];
    if ($ref === '' || $token === '') return $fail('link eksik');
    if ($price <= 0) return $fail('birim fiyat girin');

    $rs   = vestra_read_json('offer_responses.json');
    $resp = $rs[$ref] ?? null;
    if (!$resp || vestra_offer_turn($resp) !== 'buyer') return $fail('bu karsi teklif artik gecerli degil');

    $want = (string)($resp['accept_token'] ?? '');
    if ($want === '' || !hash_equals($want, $token)) return $fail('link gecersiz ya da kullanilmis');

    $left = vestra_offer_counters_left($resp);
    if ($left < 1) {
        return $fail('karsi teklif hakki doldu ('.VESTRA_OFFER_MAX_COUNTERS.'/'.VESTRA_OFFER_MAX_COUNTERS.') — yalnizca kabul ya da ret verebilirsiniz', 0);
    }

    $offerRow = vestra_offer_row($ref);
    if (!$offerRow) return $fail('teklif bulunamadi');
    $listing  = vestra_listing_by_sku($offerRow['sku'] ?? '');
    /* Taban: urunun yarisi. Kayittan ONCE -- gecersiz bir teklif ne diske
       yazilmali ne de operatore bildirilmeli. */
    $pErr = vestra_offer_price_error($listing, 'buyer', $price,
                vestra_offer_last_price($resp, 'buyer', $offerRow));
    if ($pErr !== null) return $fail($pErr, $left);
    $prodName = $listing ? trim(($listing['brand'] ?? '').' '.($listing['name'] ?? ''))
                         : trim((string)($offerRow['product'] ?? ''));
    $qty      = (int)($offerRow['qty'] ?? 0);
    $theirs   = (float)($resp['counter_price'] ?? 0);

    $counters   = (array)($resp['counters'] ?? []);
    /* Eski kayitta dizi yok ama bir tur yasanmis olabilir -- sayaç onu
       zaten sayiyor, gecmis de ayni sekilde tamamlanmali yoksa panelde
       bir tur eksik gorunur. */
    if (!$counters && $theirs > 0) $counters[] = ['by' => 'seller', 'price' => round($theirs, 2), 'at' => (string)($resp['responded_at'] ?? '')];
    $counters[] = ['by' => 'buyer', 'price' => round($price, 2), 'at' => date('c')];

    $rs[$ref] = [
        'status'        => 'counter',
        'counter_price' => round($price, 2),
        'counter_by'    => 'buyer',
        'counters'      => $counters,
        'responded_at'  => date('c'),
        'responded_by'  => 'buyer',
        /* Token YAKILDI: sira saticida, alicinin elindeki mektup artik
           hicbir seyi baglamamali. */
    ];
    vestra_write_json('offer_responses.json', $rs);
    $leftAfter = vestra_offer_counters_left($rs[$ref]);

    require_once __DIR__.'/notify.php';
    vestra_notify(
        "Buyer COUNTERED back — {$ref}  (EUR ".number_format($price, 2)."/unit)",
        "The buyer answered your counter offer with one of their own.\n\n"
      . "Reference : {$ref}\n"
      . "Product   : {$prodName}\n"
      . "SKU       : ".($offerRow['sku'] ?? '')."\n"
      . "Quantity  : {$qty}\n"
      . "Their first offer : EUR ".number_format((float)($offerRow['offer_unit'] ?? 0), 2)."/unit\n"
      . "Your counter      : EUR ".number_format($theirs, 2)."/unit\n"
      . "THEIR COUNTER     : EUR ".number_format($price, 2)."/unit   (total EUR ".number_format($price * $qty, 2).")\n\n"
      . "Rounds used: ".count($counters)."/".VESTRA_OFFER_MAX_COUNTERS
      . ($leftAfter > 0 ? "  — {$leftAfter} counter(s) left." : "  — LIMIT REACHED: you can only accept or decline now.")."\n\n"
      . "Answer it: https://vestrasales.com/admin?tab=offers"
    );

    $buyerAcc = auth_find($offerRow['email'] ?? '');
    if ($buyerAcc) {
        require_once __DIR__.'/messages.php';
        vestra_msg_post_system($buyerAcc['id'], (string)($listing['seller_uid'] ?? ''), $listing['id'] ?? '', [
            'kind' => 'offer_response', 'ref' => $ref, 'status' => 'counter',
            'counter_price' => round($price, 2), 'product' => $prodName,
        ]);
    }

    if (!empty($offerRow['email']) && filter_var($offerRow['email'], FILTER_VALIDATE_EMAIL)) {
        [$s, $b, $o] = vestra_tpl_offer_buyer_countered(
            vestra_user_lang($buyerAcc),
            ($offerRow['company'] ?? '') ?: (string)($buyerAcc['name'] ?? 'there'),
            $prodName, $ref, round($price, 2), $qty, $leftAfter,
            vestra_offer_product_url($listing)
        );
        vestra_send_mail($offerRow['email'], $s, $b, 'support@vestrasales.com', 'VESTRA', null, '', $o);
    }

    return ['ok' => true, 'error' => '', 'left' => $leftAfter, 'offer' => $offerRow, 'unit' => round($price, 2)];
}
