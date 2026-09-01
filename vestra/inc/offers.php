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
function vestra_offer_invoice_payload(string $ref, string $sellerPickOverride = ''): ?array {
    $offerRow = vestra_offer_row($ref);
    if (!$offerRow) return null;
    $listing  = vestra_listing_by_sku($offerRow['sku'] ?? '');
    $buyerAcc = auth_find($offerRow['email'] ?? '') ?: [];
    $unit = vestra_offer_agreed_unit($ref, null, $offerRow);
    $qty  = (int)($offerRow['qty'] ?? 0);

    $sellerAcc = vestra_offer_invoice_seller($ref, $listing, $sellerPickOverride);

    return [
        'meta' => [
            'ref' => $ref, 'date' => $offerRow['timestamp'] ?? date('c'),
            'buyer' => [
                'company' => ($offerRow['company'] ?? '') ?: (string)($buyerAcc['company'] ?? ''),
                'vat'     => (string)($buyerAcc['vat_id'] ?? ''),
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
