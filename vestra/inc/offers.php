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
            $action === 'counter' ? vestra_offer_accept_url($ref, (string)$rs[$ref]['accept_token']) : null
        );
        vestra_send_mail($offerRow['email'], $mSubject, $mBody, $actor['email'] ?? '', $label, null, '', $mOpts);
    }

    $invoice = null;
    if ($action === 'accept') {
        require_once __DIR__.'/invoice.php';
        $buyerFull = $buyerAcc ?: null;
        $orderMeta = [
            'ref' => $ref, 'date' => date('c'),
            'buyer' => [
                'company' => $offerRow['company'] ?? ($buyerFull['company'] ?? ''),
                'vat'     => $buyerFull['vat_id'] ?? '',
                'name'    => $buyerFull['name'] ?? '',
                'email'   => $offerRow['email'] ?? '',
                'country' => $buyerFull['country'] ?? '',
                'address' => $buyerFull['address'] ?? '',
            ],
        ];
        $qty = (int)($offerRow['qty'] ?? 0);
        $items = [[
            'sku'   => $listing['sku'] ?? ($offerRow['sku'] ?? ''),
            'brand' => $listing['brand'] ?? '',
            'name'  => $listing['name'] ?? ($offerRow['product'] ?? ''),
            'colors' => [],
            'qty'   => $qty,
            /* UZLASILAN fiyat -- karsi teklif verilmisse o. Satir toplami da
               yeniden hesaplaniyor: CSV'deki offer_total ilk teklifin toplami,
               pazarlik sonrasi artik dogru degil. */
            'unit'  => round($agreedUnit, 2),
            'line'  => round($agreedUnit * $qty, 2),
        ]];
        /* $force VERILMIYOR: otomatik fatura kesme kapali (vestra_auto_invoice_enabled),
           yani burada PDF URETILMIYOR, sadece 'pending' donuyor. Kasitli -- stok teyit
           edilmeden ve eksik alici bilgileri tamamlanmadan numara yakilmasin. Faturayi
           operator elle kesiyor. */
        /* Satici hesabi yoksa (kurasyonlu katalog urunu) faturayi PLATFORM kesiyor:
           Acerasoft LLC kimligi + admin panelinden girilen banka hesabi. Onceden
           null geciliyordu ve fatura banka bilgisi olmadan cikiyordu. */
        $sellerAcc = $actor ?: vestra_platform_seller();
        $invoice = vestra_ensure_invoice($orderMeta, $items, $sellerAcc);
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

    $buyerAcc = auth_find($offerRow['email'] ?? '');

    require_once __DIR__.'/invoice.php';
    $orderMeta = [
        'ref' => $ref, 'date' => date('c'),
        'buyer' => [
            'company' => $offerRow['company'] ?? ($buyerAcc['company'] ?? ''),
            'vat'     => $buyerAcc['vat_id'] ?? '',
            'name'    => $buyerAcc['name'] ?? '',
            'email'   => $offerRow['email'] ?? '',
            'country' => $buyerAcc['country'] ?? '',
            'address' => $buyerAcc['address'] ?? '',
        ],
    ];
    $sellerUid = (string)($listing['seller_uid'] ?? '');
    $sellerAcc = null;
    if ($sellerUid !== '') foreach (auth_accounts() as $sa) { if (($sa['id'] ?? '') === $sellerUid) { $sellerAcc = $sa; break; } }
    if (!$sellerAcc) $sellerAcc = vestra_platform_seller();
    $invoice = vestra_ensure_invoice($orderMeta, [[
        'sku'   => $listing['sku'] ?? ($offerRow['sku'] ?? ''),
        'brand' => $listing['brand'] ?? '',
        'name'  => $listing['name'] ?? ($offerRow['product'] ?? ''),
        'colors' => [],
        'qty'   => $qty,
        'unit'  => round($unit, 2),
        'line'  => round($unit * $qty, 2),
    ]], $sellerAcc);

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
