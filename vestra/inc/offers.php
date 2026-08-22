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

    $offerRow = null;
    foreach (vestra_read_csv('offers.csv') as $row) {
        if (($row['ref'] ?? '') === $ref) { $offerRow = $row; break; }
    }
    if (!$offerRow) return ['ok' => false, 'error' => 'teklif bulunamadi'];
    $listing = vestra_listing_by_sku($offerRow['sku'] ?? '');

    /* Yanit ONCE diske yaziliyor. Bildirim/e-posta/fatura adimlarindan biri
       patlarsa teklif "yanitlanmis" kalir; tersi durumda alici kabul e-postasi
       alip sistemde teklif "bekliyor" gorunurdu -- ikinci bir kabul denemesi
       ikinci bir e-posta demek. */
    $rs = vestra_read_json('offer_responses.json');
    $rs[$ref] = [
        'status'        => $action,
        'counter_price' => $action === 'counter' ? $ctr : null,
        'responded_at'  => date('c'),
        'responded_by'  => $actor['id'] ?? 'operator',
    ];
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
            $action === 'counter' ? $ctr : null
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
        $items = [[
            'sku'   => $listing['sku'] ?? ($offerRow['sku'] ?? ''),
            'brand' => $listing['brand'] ?? '',
            'name'  => $listing['name'] ?? ($offerRow['product'] ?? ''),
            'colors' => [],
            'qty'   => (int)($offerRow['qty'] ?? 0),
            'unit'  => (float)($offerRow['offer_unit'] ?? 0),
            'line'  => (float)($offerRow['offer_total'] ?? 0),
        ]];
        /* $force VERILMIYOR: otomatik fatura kesme kapali (vestra_auto_invoice_enabled),
           yani burada PDF URETILMIYOR, sadece 'pending' donuyor. Kasitli -- stok teyit
           edilmeden ve eksik alici bilgileri tamamlanmadan numara yakilmasin. Faturayi
           operator elle kesiyor. */
        $invoice = vestra_ensure_invoice($orderMeta, $items, $actor);
    }

    return ['ok' => true, 'error' => '', 'invoice' => $invoice];
}
