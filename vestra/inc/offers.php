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
        'responded_at'  => date('c'),
        'responded_by'  => $actor['id'] ?? 'operator',
    ];
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
    if ($notify && !empty($offerRow['email']) && filter_var($offerRow['email'], FILTER_VALIDATE_EMAIL)) {
        $buyerName = $offerRow['company'] ?? ($buyerAcc['name'] ?? 'there');
        [$mSubject, $mBody, $mOpts] = vestra_tpl_offer_response(
            vestra_user_lang($buyerAcc), $action, $buyerName, $prodName, $ref,
            $action === 'counter' ? $ctr : null,
            $action === 'counter' ? vestra_offer_accept_url($ref, (string)$rs[$ref]['accept_token']) : null,
            vestra_offer_product_url($listing)
        );
        vestra_send_mail($offerRow['email'], $mSubject, $mBody, $actor['email'] ?? '', $label, null, '', $mOpts);
    }

    $invoice = null;
    if ($action === 'accept') {
        /* $force VERILMIYOR: fatura OPERATOR ONAYIYLA kesiliyor (Admin ▸ Invoice
           approvals). Burada donen sey yalnizca 'pending' -- stok teyit edilmeden
           ve eksik alici bilgileri tamamlanmadan numara yakilmasin. */
        $invoice = vestra_offer_issue_invoice($ref, false);
    }

    return ['ok' => true, 'error' => '', 'invoice' => $invoice];
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

/* Teklifin FATURA yuku: alici blogu + tek satir + fatura kesecek satici.
 * Uc yerde (operator kabulu, alici kabulu, panelden onayli kesim) elle
 * kuruluyordu; ucu de ayni rakami uretmek ZORUNDA, cunku ayni belge.
 * Ayri kopyalar zamanla ayrisir ve ayrisma faturada gorunur. */
function vestra_offer_invoice_payload(string $ref): ?array {
    $offerRow = vestra_offer_row($ref);
    if (!$offerRow) return null;
    $listing  = vestra_listing_by_sku($offerRow['sku'] ?? '');
    $buyerAcc = auth_find($offerRow['email'] ?? '') ?: [];
    $unit = vestra_offer_agreed_unit($ref, null, $offerRow);
    $qty  = (int)($offerRow['qty'] ?? 0);

    /* Satici hesabi yoksa (kurasyonlu katalog urunu) faturayi PLATFORM keser:
       Acerasoft LLC kimligi + panelden girilen banka hesabi. Onceden null
       geciliyordu ve fatura banka bilgisi olmadan cikiyordu. */
    require_once __DIR__.'/invoice.php';
    $sellerUid = (string)($listing['seller_uid'] ?? '');
    $sellerAcc = null;
    if ($sellerUid !== '') foreach (auth_accounts() as $sa) { if (($sa['id'] ?? '') === $sellerUid) { $sellerAcc = $sa; break; } }
    if (!$sellerAcc) $sellerAcc = vestra_platform_seller();

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
    if (!$resp || ($resp['status'] ?? '') !== 'counter') {
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
    if (($resp['status'] ?? '') !== 'counter') {
        return $fail(($resp['status'] ?? '') === 'accept' ? 'zaten kabul edildi' : 'bu karsi teklif artik gecerli degil');
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
