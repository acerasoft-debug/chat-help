<?php
/**
 * VESTRA — order detail helpers shared by the buyer, seller and admin panels.
 * Reconstructs a full line-item breakdown (brand/name/colours/image) from the
 * terse strings order.php writes into orders.csv, and renders the "open an
 * order and review it" detail view used by both buyer.php and seller.php.
 */

/** Find a product (demo or live) by SKU — reconstructs brand/name/image for an order line.
 *  Includes unlisted items: an order line for the Musterstueck sample must still show its
 *  brand, name and photo in the buyer, seller and admin panels. */
function vestra_product_by_sku(string $sku): ?array {
    if ($sku === '') return null;
    foreach (vestra_products(true) as $p) if (($p['sku'] ?? '') === $sku) return $p;
    /* Katalogdan cekilmis ilan (askidaki satici, bekleyen/reddedilen ilan):
       eski siparisin satiri yine de marka/ad/fotografla okunmali. Ham liste
       son care -- satis yolu degil, gecmis. */
    foreach (vestra_listings() as $p) if (($p['sku'] ?? '') === $sku) return $p;
    return null;
}

/* order.php, alicinin serbest metnine "Colours — SKU: A, B | SKU2: C. " (ve
   artik "Sizes — …") parcalarini ekliyor. Bunu SKU->deger haritasina geri
   cevirir ve parcayi metinden cikarir. SKU, renk ve beden adlari '.' icermiyor,
   yani ilk noktaya kadar okumak parcayi serbest metinden ayirmaya yetiyor.
 *
 * KALIP ARTIK BASA BAGLI DEGIL -- ve bu bir hata duzeltmesi, susleme degil.
 * Eski surum '/^Colours — .../' idi, oysa order.php notlari HER ZAMAN
 * "Payment: Bank transfer. " ile acıyor (ve cogu zaman "Deliver to: …" da
 * araya giriyor). Yani kalip CANLI hicbir siparise uymuyordu ve
 * vestra_order_lines() her zaman BOS bir renk haritasi donduruyordu: alicinin
 * sectigi renkler CSV'ye yaziliyor, ama siparis tablosunda, sipariş PDF'inde
 * ve fatura satirinda hicbir zaman gorunmuyordu. Olculdu (10 Eyl 2026): gercek
 * bir not dizgesiyle [] doner, yalnizca "Colours" ile BASLAYAN kurgusal bir
 * dizgeyle calisirdi. Beden secimi ayni yoldan gectigi icin bu once
 * duzeltilmeliydi -- yoksa yeni alan da "yazilan ama hic okunmayan" olurdu. */
function vestra_order_notes_map(string $notes, string $label): array {
    $map = [];
    $re = '/(?:^|\s)'.preg_quote($label, '/').' — (.+?)\.(?=\s|$)/su';
    if (!preg_match($re, $notes, $m)) return [$map, $notes];
    foreach (explode(' | ', $m[1]) as $seg) {
        if (preg_match('/^(\S+):\s*(.+)$/', trim($seg), $sm)) {
            $map[$sm[1]] = array_map('trim', explode(',', $sm[2]));
        }
    }
    /* Parca cikarilinca geriye cift bosluk kaliyor ve bu metin hem alicinin
       siparis sayfasinda hem operator panelinde OLDUGU GIBI basiliyor. */
    $rest = (string)preg_replace($re, ' ', $notes, 1);
    return [$map, trim((string)preg_replace('/[ \t]{2,}/', ' ', $rest))];
}

function vestra_order_notes_colors(string $notes): array {
    [$colors, $rest] = vestra_order_notes_map($notes, 'Colours');
    [$sizes,  $rest] = vestra_order_notes_map($rest,  'Sizes');
    return ['colors' => $colors, 'sizes' => $sizes, 'notes' => $rest];
}

/**
 * Siparişin notlarındaki teslimat adresi (`Deliver to: …`).
 *
 * Bu kalıp ÜÇ yere ayrı ayrı yazılmıştı — fatura (`vestra_invoice_buyer`),
 * operatör paneli ve sipariş sayfası — ve üçü de aynı kusuru taşıyordu:
 * `(?:\.\s|$)` yalnızca "nokta + boşluk"u ya da dizge sonunu tanıyor, yani
 * adres notların EN SONUNDAysa kapanış noktası adresin İÇİNDE kalıyordu
 * ("… Hong Kong."). Belgeye, ekrana ve kurye etiketine öyle basılıyordu.
 * Yazma tarafı eklenince (`vestra_order_set_delivery`) bu görünür oldu:
 * yazılan ile geri okunan aynı değildi. Kalıp artık tek yerde ve `\.$`
 * durumunu da tanıyor.
 */
function vestra_order_delivery_address(string $notes): string {
    if (preg_match('/Deliver to: (.*?)(?:\.\s|\.$|$)/u', $notes, $m)) return trim($m[1]);
    return '';
}

/** Full line items for an order row, enriched with product info + per-SKU colours. */
function vestra_order_lines(array $orderRow): array {
    $parsed = vestra_parse_order_items($orderRow['items'] ?? '');
    $cn = vestra_order_notes_colors($orderRow['notes'] ?? '');
    $out = [];
    foreach ($parsed as $it) {
        $p = vestra_product_by_sku($it['sku']);
        $out[] = [
            'sku' => $it['sku'], 'qty' => $it['qty'], 'unit' => $it['unit'], 'line' => round($it['qty'] * $it['unit'], 2),
            'brand' => $p['brand'] ?? '', 'name' => $p['name'] ?? t('Product no longer listed'),
            'image' => $p ? vestra_primary_image($p) : '', 'id' => $p['id'] ?? '', 'seller_uid' => $p['seller_uid'] ?? '',
            'colors' => $cn['colors'][$it['sku']] ?? [],
            'sizes'  => $cn['sizes'][$it['sku']] ?? [],
        ];
    }
    return ['lines' => $out, 'notes' => $cn['notes']];
}

function vestra_order_history_entry(string $status, string $by, string $note = ''): array {
    return array_filter(['status' => $status, 'at' => date('c'), 'by' => $by, 'note' => $note], fn($v) => $v !== '');
}

/* ─────────────────────────── Gönderim (kargo firması + servis + takip) ───────
 * Operatör, 9 Eyl 2026: "bu gönderim numarasini ekle link ile beraber ups
 * express saver" + "her pakette gönderici kargo bölümüde olsun".
 *
 * O güne kadar `tracking` ÇIPLAK BİR DİZGEYDİ: alıcı sipariş sayfasında ve
 * mektupta 18 karakterlik bir numara görüyor, hangi firmanın taşıdığını ve
 * nereye bakacağını bilmiyordu. Kargo firması, servis adı ve takip bağlantısı
 * üç ayrı olgu ve üçü de eksikti.
 *
 * TAKİP BAĞLANTISI KAYDA YAZILMAZ, numaradan TÜRETİLİR. Elle yapıştırılan bir
 * URL, aynı olgunun ikinci kopyası olurdu ve bu depo o hatanın bedelini bu
 * hafta iki kez ödedi (`desc`/`sizes` beden serisi, thread id). Numara
 * değişince bağlantı kendiliğinden değişir. */
function vestra_carriers(): array {
    /* URL kalıbı operatörün KENDİ verdiği biçim (UPS) — "düzeltilmedi".
     * Liste bilerek DAR: yalnız gerçekten kullanılan taşıyıcılar. */
    return [
        'ups'    => ['name' => 'UPS',        'url' => 'https://www.ups.com/track?tracknum=%s&loc=en_US&requester=ST/trackdetails'],
        'dhl'    => ['name' => 'DHL',        'url' => 'https://www.dhl.com/global-en/home/tracking.html?tracking-id=%s'],
        'fedex'  => ['name' => 'FedEx',      'url' => 'https://www.fedex.com/fedextrack/?trknbr=%s'],
        'tnt'    => ['name' => 'TNT',        'url' => 'https://www.tnt.com/express/en_gb/site/shipping-tools/tracking.html?searchType=con&cons=%s'],
        'gls'    => ['name' => 'GLS',        'url' => 'https://gls-group.com/track?match=%s'],
        'dpd'    => ['name' => 'DPD',        'url' => 'https://tracking.dpd.de/status/en_US/parcel/%s'],
        'postnl' => ['name' => 'PostNL',     'url' => 'https://postnl.nl/en/track-and-trace/?B=%s'],
        'other'  => ['name' => '',           'url' => ''],
    ];
}

/**
 * Numaranın KENDİ biçiminden taşıyıcı çıkarımı — ve yalnızca biçim KESİNSE.
 *
 * Tek çıkarım UPS: `1Z` + 16 alfanümerik, başka hiçbir taşıyıcının kullanmadığı
 * bir kalıp. DHL'in 10 hanesi ile FedEx'in 12 hanesi gibi kalıplar birbirine ve
 * başka numaralara benziyor; oradan tahmin yürütmek alıcıya BAŞKA BİR PAKETİN
 * ya da hiçbir şeyin sayfasını açan bir bağlantı verir — bağlantı olmamasından
 * kötü. Onlarda taşıyıcıyı operatör yazar (KURAL 3'ün kargo hâli).
 *
 * SAĞLAMA BASAMAĞI (1Z'nin son hanesi) BİLEREK DOĞRULANMIYOR: elimde
 * algoritmayı sınayacak güvenilir bir referans numara yok ve yanlış yazılmış
 * bir sağlama, GEÇERLİ numaraları reddederdi — hiç kontrol etmemekten kötü bir
 * arıza. Biçim kontrolü kesin ve yanlış ret üretemez.
 */
function vestra_carrier_from_tracking(string $tracking): string {
    $t = strtoupper(preg_replace('/\s+/', '', $tracking));
    return preg_match('/^1Z[0-9A-Z]{16}$/', $t) ? 'ups' : '';
}

/**
 * Bir siparişin gönderim bilgisi, TEK yerde. Alıcı sayfası, satıcı/admin paneli
 * ve "gönderildi" mektubu üçü de burayı okur; ayrı ayrı kurulsalardı üçü ayrı
 * şey yazardı (bu depoda KURAL 5f'in üç katmanı aynı sınıf).
 *
 * @return array{tracking:string,carrier:string,carrier_name:string,service:string,url:string,has:bool}
 */
function vestra_order_shipment(?array $statusEntry): array {
    $st       = (array)($statusEntry ?? []);
    $tracking = trim((string)($st['tracking'] ?? ''));
    $service  = trim((string)($st['ship_service'] ?? ''));
    /* Sıra: operatörün YAZDIĞI taşıyıcı > numaradan çıkarılan. Yazılmış bir
       değeri çıkarımın ezmesi, operatörün kararını sessizce geri almak olurdu. */
    $carrier  = strtolower(trim((string)($st['ship_carrier'] ?? '')));
    $known    = vestra_carriers();
    if ($carrier === '' || !isset($known[$carrier])) $carrier = vestra_carrier_from_tracking($tracking);
    $name = $carrier !== '' ? (string)($known[$carrier]['name'] ?? '') : '';
    $url  = '';
    if ($tracking !== '' && $carrier !== '' && !empty($known[$carrier]['url'])) {
        $url = sprintf((string)$known[$carrier]['url'], rawurlencode($tracking));
    }
    return [
        'tracking'     => $tracking,
        'carrier'      => $carrier,
        'carrier_name' => $name,
        'service'      => $service,
        'url'          => $url,
        'has'          => $tracking !== '' || $name !== '' || $service !== '',
    ];
}

/**
 * Gönderim bilgisini kaydeder (durum DEĞİŞTİRMEZ — onu çağıran yol yapar).
 * `vestra_order_set_shipping()` (navlun tutarı) ile karıştırma: o para, bu paket.
 *
 * Boş dizge alanı SİLER: bir taşıyıcıyı kaldırmanın başka yolu olmazdı.
 */
function vestra_order_set_shipment(string $ref, ?string $tracking, ?string $carrier, ?string $service): array {
    $ref = trim($ref);
    if ($ref === '') return ['ok' => false, 'error' => 'ref yok'];
    $all = vestra_read_json('order_statuses.json');
    $row = (array)($all[$ref] ?? []);
    if ($tracking !== null) {
        $t = strtoupper(preg_replace('/\s+/', '', trim($tracking)));
        if ($t === '') unset($row['tracking']); else $row['tracking'] = $t;
    }
    if ($carrier !== null) {
        $c = strtolower(trim($carrier));
        if ($c !== '' && !isset(vestra_carriers()[$c])) {
            return ['ok' => false, 'error' => 'bilinmeyen tasiyici: '.$c.' (gecerli: '.implode(', ', array_keys(vestra_carriers())).')'];
        }
        if ($c === '') unset($row['ship_carrier']); else $row['ship_carrier'] = $c;
    }
    if ($service !== null) {
        $s = trim(preg_replace('/\s+/', ' ', $service));
        if (mb_strlen($s) > 60) return ['ok' => false, 'error' => 'servis adi 60 karakteri asiyor'];
        if ($s === '') unset($row['ship_service']); else $row['ship_service'] = $s;
    }
    $row['updated_at'] = date('c');
    $all[$ref] = $row;
    vestra_write_json('order_statuses.json', $all);
    /* GERİ OKU: "kaydedildi" diyen bir satır tek başına kanıt değil. */
    $back = vestra_read_json('order_statuses.json');
    return ['ok' => true, 'error' => '', 'shipment' => vestra_order_shipment($back[$ref] ?? null)];
}

/** Distinct sellers whose SKUs appear in this order (uid => label), for the "seller(s)" info block. */
function vestra_order_sellers(array $lines): array {
    $out = [];
    foreach ($lines as $l) {
        $sid = $l['seller_uid'] ?: 'vestra';
        if (isset($out[$sid])) continue;
        if ($sid === 'vestra') { $out[$sid] = 'VESTRA'; continue; }
        foreach (auth_accounts() as $a) {
            if (($a['id'] ?? '') === $sid) { $out[$sid] = $a['company'] ?: ($a['name'] ?: t('Seller')); break; }
        }
    }
    return $out;
}

/* The chain a healthy order walks. 'preparing' and 'to_vestra' sit between payment and
   despatch because that is where the real waiting happens: the buyer has paid, nothing
   visible moves for days, and "Paid" on its own reads as "nobody is doing anything".
   Naming the two stages turns silence into progress the buyer can see. */
/* 'delivered' zincire 5 Eyl 2026'da eklendi. Yoktu, ama satici onu ISARETLEYEBILIYOR
   (seller.php) ve escrow sayaci ile alicinin talep suresi ONA bagli. Zincirde
   olmayinca array_search false donuyor, $idx 0'a dusuyor ve teslim edilmis
   sipariş ilk noktada "Awaiting payment" olarak cizilıyordu. Ayrica bu sabit
   vestra_order_settable_statuses()'i de besliyor: operator panelden 'delivered'
   secemiyordu, yani zincirin isleyen bir asamasi yalnizca saticinin elindeydi. */
const VESTRA_ORDER_STEPS = ['pending', 'paid', 'preparing', 'to_vestra', 'shipped', 'delivered', 'completed'];

/* Cancelled is deliberately NOT a step. It is not a later stage of the same journey, it
   is the journey stopping, and putting it on the end of the chain would render every
   cancelled order as though it had passed through despatch first. */
const VESTRA_ORDER_CANCELLED = 'cancelled';

/**
 * $forceEnglish is for admin-only surfaces (the status picker, an admin's own PDF
 * download): vlang() is a per-REQUEST cookie/browser guess, not per-viewer-role, so
 * an operator whose own browser had ever picked up a non-English vlang cookie (their
 * device language, or an earlier click on a translated storefront link) would see
 * order statuses translate right along with it — an internal tool silently following
 * whatever language the person happens to be browsing in is exactly the "arapça
 * ifadeler" bug, not a one-off. Buyer/seller-facing callers (the order timeline, the
 * order detail panel) leave this false on purpose — that translation is correct.
 */
function vestra_order_status_label(string $status, bool $forceEnglish = false): string {
    $tt = $forceEnglish ? (fn(string $s): string => $s) : 't';
    return match ($status) {
        'review' => $tt('In review'),
        'paid' => $tt('Paid'),
        'preparing' => $tt('Being prepared'),
        'to_vestra' => $tt('On its way to VESTRA'),
        'shipped' => $tt('Shipped'),
        /* 'delivered' BURADA YOKTU ve default'a dusuyordu: satici teslimati
           isaretledigi anda (seller.php) alici sipariste "Awaiting payment"
           goruyordu -- parasi cekilmis, mali eline gecmis bir siparişte. Ayni
           durum escrow sayacini ve alicinin 3 gunluk talep suresini baslatiyor,
           yani ekranin en yanlis oldugu an, dogru olmasinin en cok gerektigi
           andi. `cancelled` icin ayni tuzak asagidaki yorumda zaten yaziliydi. */
        'delivered' => $tt('Delivered'),
        'completed' => $tt('Completed'),
        'cancelled' => $tt('Cancelled'),
        default => $tt('Awaiting payment'),
    };
}

/** Everything the admin may set by hand, cancellation included. */
function vestra_order_settable_statuses(): array {
    return array_merge(VESTRA_ORDER_STEPS, [VESTRA_ORDER_CANCELLED]);
}

/**
 * The <option> list for every admin status picker.
 *
 * There are two pickers — the orders table and the order dossier — and they were two
 * hand-written copies of the same list. Adding a stage to one and forgetting the other
 * produces a page that looks entirely normal and quietly cannot reach half the chain.
 * One builder, driven by the same constant the timeline and the save handler use.
 *
 * Both callers are admin.php — labels are forced English (operator instruction, 4 Sep
 * 2026: order statuses must never follow the viewer's own vlang cookie/browser
 * language, English only in the admin panel).
 */
function vestra_order_status_options(string $current): string {
    $icons = ['pending'=>'⏳','paid'=>'💶','preparing'=>'📦','to_vestra'=>'🚛',
              'shipped'=>'🚚','completed'=>'✓','cancelled'=>'✕'];
    $out = '';
    foreach (vestra_order_settable_statuses() as $s) {
        $out .= '<option value="'.htmlspecialchars($s).'"'.($current === $s ? ' selected' : '').'>'
              . ($icons[$s] ?? '').' '.htmlspecialchars(vestra_order_status_label($s, true)).'</option>';
    }
    return $out;
}

/**
 * Is this order still being checked over rather than waiting to be paid?
 *
 * A fresh order sits at 'pending', which the tracker labels "Awaiting payment" — but
 * invoicing is suspended at checkout, so until the operator confirms stock and issues
 * the invoice there is nothing for the buyer to pay against. Showing them a payment
 * prompt with no invoice and no bank details reads as "we are waiting on you" when the
 * truth is the opposite, and it contradicts the confirmation mail, which promises the
 * invoice is coming once stock is confirmed.
 *
 * Derived from whether an invoice exists rather than stored as its own status: the
 * invoice IS the fact that separates the two states, so this can never drift out of
 * sync with reality, and issuing an invoice moves the order on with no extra step.
 */
function vestra_order_in_review(string $ref, string $status): bool {
    if ($status !== 'pending' || $ref === '') return false;
    if (!function_exists('vestra_invoices_for_ref')) require_once __DIR__.'/invoice.php';
    return !vestra_invoices_for_ref($ref);
}

/**
 * BU SATISIN PARASI GELDI MI? — tek karar noktasi.
 *
 * Ayni olgunun IKI ayri kaydi vardi ve biri otekini HIC okumuyordu:
 *
 *   order_statuses[ref].status        yazan: Admin > Orders durum secici
 *                                     okuyan: odeme saati/otomatik iptal
 *                                             (cron_order_payment), siparis
 *                                             sayfasi, order-pdf
 *   offer_responses[ref].invoice_paid_at
 *                                     yazan: Admin > Invoice approvals "✓ Paid"
 *                                     okuyan: alicinin "Payment due" bandi
 *                                             (buyer.php) ve o dugmenin kendisi
 *
 * Yani ayni satis bir ekranda odenmis, obur ekranda odenmemis gorunuyordu.
 * 19 Eyl 2026'da OLCULDU: O39419 order_statuses'te `completed` (paid 24 Agu →
 * shipped 9 Eyl → completed 12 Eyl, sonuncusunu ALICI isaretlemis) ve faturasi
 * INV-2026-1009 kesilmis; buna ragmen `invoice_paid_at` okuyan iki yuzey de
 * "odenmemis" diyordu -- yani parasini 26 gun once odemis, malini teslim almis
 * musteriye "⚠ Payment due / awaiting payment" gosteriliyordu.
 *
 * Bu, bu dosyada ZATEN bir kez odenmis dersin ust katmani: 5 Eyl 2026'da
 * 'delivered' zincirde olmadigi icin default'a dusuyor ve teslim edilmis bir
 * siparis "Awaiting payment" yaziyordu (bkz. vestra_order_status_label'daki
 * not). Orada eksik olan bir ADIMDI, burada eksik olan bir KAYIT.
 *
 * TURETILIYOR, ucuncu bir bayrak olarak SAKLANMIYOR (vestra_order_in_review'in
 * kendi gerekcesi): saklansaydi gunun birinde o da otekilerden ayrisirdi.
 * Olcut ZINCIRIN KENDISINDEN okunuyor, elle yazilmis bir durum listesinden
 * degil -- yarin araya bir adim girerse ('to_vestra' boyle girmisti) o da
 * kendiliginden "parasi gelmis" tarafinda kalir.
 *
 * 'cancelled' zincirde BILEREK yok (VESTRA_ORDER_CANCELLED'in kendi notu), yani
 * iptal edilmis siparis "odendi" saymiyor -- ama zaten pending de olmadigi icin
 * kimse onun parasini kovalamiyor.
 *
 * @return array{settled:bool,via:string,status:string,at:string}
 *         via: 'status'  -> siparisin kendi durumu zincirde 'paid' ya da otesi
 *              'invoice' -> teklif faturasinda odendi isareti var
 */
function vestra_order_payment_settled(string $ref, ?array $statusEntry = null): array {
    $ref = preg_replace('/[^A-Za-z0-9_-]/', '', trim($ref));
    if ($statusEntry === null && $ref !== '') {
        $statusEntry = (array)((vestra_read_json('order_statuses.json'))[$ref] ?? []);
    }
    $status = (string)(($statusEntry ?? [])['status'] ?? 'pending');
    $out = ['settled' => false, 'via' => '', 'status' => $status, 'at' => ''];

    $iNow  = array_search($status, VESTRA_ORDER_STEPS, true);
    $iPaid = array_search('paid',  VESTRA_ORDER_STEPS, true);
    if ($iNow !== false && $iPaid !== false && $iNow >= $iPaid) {
        $out['settled'] = true;
        $out['via']     = 'status';
        /* TARIH: acik alan, yoksa GECMISTE 'paid' satirinin damgasi. `updated_at`
           BILEREK kullanilmiyor -- o, kaydin SON yazilma ani, paranin geldigi an
           degil. Olculdu (O39419, 19 Eyl 2026): `paid_at` yok, `updated_at`
           2026-09-09T14:03 (kargo damgasi) ve sonda "odendi: EVET ... 9 Eyl"
           yaziyordu; gecmise gore para 24 Agustos'ta gelmisti. Yani rakam
           dogruydu, ETIKET yalandi -- bu depoda kayitli: yanlis rakam sorgulanir,
           yanlis etikete inanilir. Ikisi de yoksa BOS kalir: bilinmeyen bir
           tarihi uydurmaktansa yazmamak (KURAL 3). */
        $out['at'] = trim((string)(($statusEntry ?? [])['paid_at'] ?? ''));
        if ($out['at'] === '') {
            foreach ((array)(($statusEntry ?? [])['history'] ?? []) as $h) {
                if ((string)($h['status'] ?? '') === 'paid') { $out['at'] = trim((string)($h['at'] ?? '')); break; }
            }
        }
        return $out;
    }

    /* Teklif faturasinin kendi isareti. Siparis satiri 'pending' kalmis olabilir
       (operator isareti Invoice approvals'tan koydu, Orders sekmesine hic
       girmedi) ve o hâlde otomatik iptal saati ODENMIS bir siparisi kovalar. */
    if ($ref === '') return $out;
    $rs = (array)vestra_read_json('offer_responses.json');
    $at = trim((string)($rs[$ref]['invoice_paid_at'] ?? ''));
    if ($at === '') {
        /* UYE SATIRI: birlesik belge BIRINCIL ref adina kesildi ve odendi
           isareti orada duruyor (KURAL 5e). Uyeyi kendi basina sormak, tek
           belgeyle odenmis bir satisin yarisini "odenmemis" gosterirdi --
           vestra_invoices_for_ref'in bagi izlemesiyle ayni gerekce. */
        $grp = trim((string)($rs[$ref]['invoice_group_ref'] ?? ''));
        if ($grp !== '' && $grp !== $ref) $at = trim((string)($rs[$grp]['invoice_paid_at'] ?? ''));
    }
    if ($at !== '') { $out['settled'] = true; $out['via'] = 'invoice'; $out['at'] = $at; }
    return $out;
}

/** What the buyer is told while the order is in review. */
function vestra_order_review_note(string $orderDate = ''): string {
    $d = $orderDate !== '' ? substr($orderDate, 0, 10) : '';
    return ($d !== '' ? $d.' — ' : '')
        . t('Your order is being reviewed. We will confirm stock and contact you shortly.');
}

/**
 * Visual step tracker. In review the chain gains a leading step, so the buyer sees a
 * stage they are actually in rather than being parked on "Awaiting payment".
 */
function vestra_order_timeline_html(string $currentStatus, bool $inReview = false): string {
    /* A cancelled order gets its own marker rather than a position in the chain. Falling
       through to the chain would have parked it on "Awaiting payment" (array_search
       fails, idx defaults to 0), telling the buyer their cancelled order is waiting on
       their money. */
    if ($currentStatus === VESTRA_ORDER_CANCELLED) {
        return '<div class="otimeline"><div class="otstep now otcancelled">'
             . '<span class="otdot"></span><span class="otlabel">'
             . vestra_order_status_label(VESTRA_ORDER_CANCELLED).'</span></div></div>';
    }
    $steps = $inReview ? array_merge(['review'], VESTRA_ORDER_STEPS) : VESTRA_ORDER_STEPS;
    $idx = $inReview ? 0 : array_search($currentStatus, VESTRA_ORDER_STEPS, true);
    if ($idx === false) $idx = 0;
    $out = '<div class="otimeline">';
    foreach ($steps as $i => $st) {
        $cls = $i < $idx ? 'done' : ($i === $idx ? 'now' : '');
        $out .= '<div class="otstep '.$cls.'"><span class="otdot"></span><span class="otlabel">'.vestra_order_status_label($st).'</span></div>';
    }
    return $out.'</div>';
}

/**
 * Render the "open an order" detail view. $viewerRole is 'buyer' or 'seller';
 * $backHref/$formHref point back to the owning panel's orders tab.
 */
function vestra_render_order_detail(array $orderRow, array $statusEntry, string $viewerRole, string $viewerUid, string $backHref, string $formHref): string {
    $ref = $orderRow['ref'] ?? '';
    $status = $statusEntry['status'] ?? 'pending';
    $ld = vestra_order_lines($orderRow);
    $lines = $ld['lines']; $buyerNotes = $ld['notes'];
    $sellers = vestra_order_sellers($lines);

    $h = '<div class="panelcard">';
    $h .= '<div class="pcfhead"><h3>'.t('Order').' <span class="atag">'.htmlspecialchars($ref).'</span></h3><a class="btn btn-o btn-sm" href="'.htmlspecialchars($backHref).'">← '.t('Back to orders').'</a></div>';
    $inReview = vestra_order_in_review($ref, $status);
    $h .= vestra_order_timeline_html($status, $inReview);
    /* Said once, plainly, at the top — this is the answer to the only question a buyer
       has on a fresh order, and it is the same thing the confirmation mail promised. */
    if ($inReview) {
        $h .= '<div class="oreview"><span class="oreview-i">🔎</span><div><b>'.t('In review').'</b>'
            . '<div>'.htmlspecialchars(vestra_order_review_note((string)($orderRow['timestamp'] ?? ''))).'</div></div></div>';
    }

    // Payment method + escrow state + delivery address, parsed from the order record —
    // the three facts both sides ask about first.
    $rawNotes = (string)($orderRow['notes'] ?? '');
    $isEscrowOrder = str_contains($rawNotes, 'Secure escrow');
    $escrowBadge = '';
    if (function_exists('escrow_get')) {
        $er = escrow_get($ref);
        if ($er) { $isEscrowOrder = true; $escrowBadge = ' · '.escrow_badge($er['status'] ?? ''); }
    }
    $shipTo = '';
    $shipTo = vestra_order_delivery_address($rawNotes);
    $h .= '<div class="hint" style="display:flex;gap:18px;flex-wrap:wrap;margin:2px 0 14px;font-size:13px">'
        . '<span><b>'.t('Payment').':</b> '.($isEscrowOrder ? '🛡️ '.t('Secure escrow (card)') : '🏦 '.t('Bank transfer (invoice)')).$escrowBadge.'</span>'
        . ($shipTo !== '' ? '<span><b>'.t('Deliver to').':</b> '.htmlspecialchars($shipTo).'</span>' : '')
        . '</div>';

    $h .= '<div class="odgrid">';
    // Line items
    $h .= '<div><table class="ctable"><thead><tr><th>'.t('Product').'</th><th>'.t('Colours').'</th><th>'.t('Sizes').'</th><th>'.t('Qty').'</th><th class="r">'.t('Unit').'</th><th class="r">'.t('Line total').'</th></tr></thead><tbody>';
    $subtotal = 0.0;
    foreach ($lines as $l) {
        $subtotal += $l['line'];
        $prod = $l['id'] ? '<a class="acc" href="/product?id='.urlencode($l['id']).'">'.htmlspecialchars(trim($l['brand'].' '.$l['name'])).'</a>' : htmlspecialchars(trim($l['brand'].' '.$l['name']));
        $h .= '<tr><td>'.$prod.'<div class="hint">SKU '.htmlspecialchars($l['sku']).'</div></td>'.
              '<td class="hint">'.htmlspecialchars(implode(', ', $l['colors'])).'</td>'.
              '<td class="hint">'.htmlspecialchars(implode(', ', $l['sizes'] ?? [])).'</td>'.
              '<td>'.(int)$l['qty'].'</td><td class="r">'.eur($l['unit']).'</td><td class="r">'.eur($l['line']).'</td></tr>';
    }
    /* TOPLAM. Bu sayfa bugune kadar kalemleri gosteriyor ama TOPLAMI hic
       basmiyordu ($subtotal hesaplaniyor ve kullanilmiyordu): alici siparisini
       aciyor ve ne odeyecegini goremiyordu.
       Rakam siparis kaydindan aliniyor, kalemlerden yeniden HESAPLANMIYOR --
       kargo ve escrow koruma ucreti toplamin icinde ve burada yeniden toplamak
       PDF'te duran mantigin ikinci bir kopyasi olurdu; iki kopya ayrisir ve
       ayrisma parada gorunur. Kayitta toplam yoksa kalem toplami yazilir. */
    $rowTotal = isset($orderRow['total']) && (float)$orderRow['total'] > 0
        ? round((float)$orderRow['total'], 2) : round($subtotal, 2);
    $h .= '</tbody><tfoot><tr><td colspan="4" class="r"><b>'.t('Total').'</b></td>'
        . '<td class="r"><b>'.eur($rowTotal).'</b></td></tr></tfoot>';
    $h .= '</table>';
    if ($buyerNotes !== '') $h .= '<p class="hint" style="margin-top:10px"><b>'.t('Buyer notes').':</b> '.htmlspecialchars($buyerNotes).'</p>';

    $invLinks = '';
    foreach (vestra_invoices_for_ref($ref) as $iv) {
        if ($viewerRole === 'seller' && $iv['seller_key'] !== $viewerUid) continue;
        $invLinks .= '<a class="btn btn-o btn-sm" href="'.htmlspecialchars($iv['url']).'" target="_blank" rel="noopener" style="margin:10px 10px 0 0">📄 '.t('Invoice').' '.htmlspecialchars(vestra_invoice_link_label($iv)).'</a>';
    }
    /* The order summary is always downloadable, invoice or not — before the invoice is
       issued it is the only paper the buyer has, and that is precisely when a purchasing
       team asks them for one. */
    $invLinks .= '<a class="btn btn-o btn-sm" href="/order-pdf?ref='.urlencode($ref).'" style="margin:10px 10px 0 0">⤓ '.t('Download order summary (PDF)').'</a>';
    $h .= '<div>'.$invLinks.'</div>';
    $h .= '</div>'; // end line-items column

    // Side column: counterpart info + tracking/notes
    $h .= '<div>';
    if ($viewerRole === 'buyer') {
        $h .= '<div class="panelcard" style="margin:0 0 14px"><div class="pcfhead"><h3 style="font-size:14px">'.t('Seller').'</h3></div>'.
              '<p style="margin:0">'.htmlspecialchars(implode(', ', $sellers)).'</p></div>';
        /* Bank-transfer receipt segment (operator, 2 Sep 2026): only where it makes
           sense — order still pending (not paid/shipped/cancelled), an invoice already
           exists (not "in review"), and it is a bank-transfer order, not escrow (card
           payment there is instant, a receipt has no meaning). */
        if ($status === 'pending' && !$inReview && !$isEscrowOrder) {
            if (!function_exists('vestra_order_receipt')) require_once __DIR__.'/receipts.php';
            $rcpt = vestra_order_receipt($ref);
            $h .= '<div class="panelcard" style="margin:0 0 14px"><div class="pcfhead"><h3 style="font-size:14px">💳 '.t('Payment').'</h3></div>';
            if ($rcpt && $rcpt['exists']) {
                $h .= '<p class="hint" style="margin:0">📎 '.t('Receipt received').' '
                    . htmlspecialchars(substr((string)($rcpt['uploaded_at'] ?? ''), 0, 10)).' — '.t('awaiting confirmation.').'</p>';
            } else {
                $h .= '<p class="hint" style="margin:0 0 10px">'
                    . t('Already paid by bank transfer? Attach the receipt here and we will confirm your order.').'</p>'
                    . vestra_receipt_upload_form($formHref, $ref);
                if (function_exists('vestra_doc_upload_js')) $h .= vestra_doc_upload_js();
            }
            $h .= '</div>';
        }
        /* Talep ("Open dispute") — operator, 5 Eyl 2026: "dispute yaziyor ama
           böyle bir dispute yeri yok görünmüyor". Metin bes ayri sayfada bu
           dugmeyi vaat ediyordu ve dugme hicbir yerde yoktu. Asama karari
           vestra_claim_state()'te; kart, POST isleyicisi ve escrow supurucusu
           ucu de ONU okur. */
        /* Bilesenin kendisi claims.php'de (vestra_claim_widget): asama karari,
           form ve durum satiri TEK yerde. Operator (6 Eyl 2026): her sipariste,
           ama sessiz -- kocaman bir kart degil, katlanmis bir baglanti. */
        if (!function_exists('vestra_claim_state')) require_once __DIR__.'/claims.php';
        $h .= vestra_claim_widget($ref, $statusEntry, $formHref);
    } else {
        $h .= '<div class="panelcard" style="margin:0 0 14px"><div class="pcfhead"><h3 style="font-size:14px">'.t('Buyer').'</h3></div>'.
              '<p style="margin:0">'.htmlspecialchars($orderRow['company'] ?? '').'<br>'.
              htmlspecialchars($orderRow['name'] ?? '').' · <a class="acc" href="mailto:'.htmlspecialchars($orderRow['email'] ?? '').'">'.htmlspecialchars($orderRow['email'] ?? '').'</a>'.
              (!empty($orderRow['country']) ? '<br>'.htmlspecialchars($orderRow['country']) : '').'</p></div>';
        /* Satici talebi GORUR (salt okunur): SSS returns/9 "VESTRA saticidan
           aciklama ister" -- neyin sikayet edildigini gormeden aciklama olmaz. */
        if (!function_exists('vestra_claim_seller_block')) require_once __DIR__.'/claims.php';
        $h .= vestra_claim_seller_block($ref);
    }

    $h .= '<div class="panelcard" style="margin:0"><div class="pcfhead"><h3 style="font-size:14px">'.t('Shipping').'</h3></div>';
    /* Taşıyıcı / servis / takip TEK yerden (vestra_order_shipment): alıcı sayfası,
       satıcı formu ve mektup aynı üç olguyu okumalı. Operatör, 9 Eyl 2026:
       "her pakette gönderici kargo bölümüde olsun". */
    $shp = vestra_order_shipment($statusEntry);
    if ($viewerRole === 'seller') {
        $carrierOpts = '';
        foreach (vestra_carriers() as $ck => $cv) {
            if ($ck === 'other') continue;
            $carrierOpts .= '<option value="'.htmlspecialchars($ck).'"'.($shp['carrier'] === $ck ? ' selected' : '').'>'.htmlspecialchars($cv['name']).'</option>';
        }
        $h .= '<form method="post" action="'.htmlspecialchars($formHref).'">
          <input type="hidden" name="_action" value="update_order_note">
          <input type="hidden" name="ref" value="'.htmlspecialchars($ref).'">
          <label class="hint">'.t('Carrier').'</label>
          <select name="ship_carrier" style="width:100%;margin-bottom:10px"><option value="">—</option>'.$carrierOpts.'</select>
          <label class="hint">'.t('Service').'</label>
          <input name="ship_service" value="'.htmlspecialchars($shp['service']).'" placeholder="Express Saver" style="width:100%;margin-bottom:10px">
          <label class="hint">'.t('Tracking number').'</label>
          <input name="tracking" value="'.htmlspecialchars($shp['tracking']).'" style="width:100%;margin-bottom:10px">
          <label class="hint">'.t('Note to buyer').'</label>
          <textarea name="seller_note" rows="2" style="width:100%;margin-bottom:10px">'.htmlspecialchars($statusEntry['seller_note'] ?? '').'</textarea>
          <button class="btn btn-p btn-sm" type="submit">'.t('Save').'</button>
        </form>';
    } else {
        if ($shp['carrier_name'] !== '') {
            $h .= '<p style="margin:0 0 6px"><b>'.t('Carrier').':</b> '.htmlspecialchars($shp['carrier_name'])
                . ($shp['service'] !== '' ? ' · '.htmlspecialchars($shp['service']) : '').'</p>';
        } elseif ($shp['service'] !== '') {
            $h .= '<p style="margin:0 0 6px"><b>'.t('Service').':</b> '.htmlspecialchars($shp['service']).'</p>';
        }
        /* Numara BAĞLANTI olarak basılıyor — çözülebildiyse. Çözülemeyen taşıyıcıda
           düz metin kalır: kırık bir bağlantı, bağlantı olmamasından kötü. */
        $trkTxt = $shp['tracking'] === ''
            ? '<span class="hint">'.t('Not shipped yet').'</span>'
            : ($shp['url'] !== ''
                ? '<a class="acc" href="'.htmlspecialchars($shp['url']).'" target="_blank" rel="noopener nofollow">'.htmlspecialchars($shp['tracking']).'</a>'
                : htmlspecialchars($shp['tracking']));
        $h .= '<p style="margin:0 0 6px"><b>'.t('Tracking number').':</b> '.$trkTxt.'</p>';
        if (!empty($statusEntry['seller_note'])) $h .= '<p style="margin:0"><b>'.t('Note from seller').':</b> '.htmlspecialchars($statusEntry['seller_note']).'</p>';
        /* 'delivered' da dahil: buyer.php'nin isleyicisi ikisini de kabul ediyor,
           bu gorunum yalnizca 'shipped'e dugme basiyordu -- teslim edilmis
           sipariste alici detay sayfasindan onaylayamiyordu (listeden olabiliyordu).
           Talep ACIKKEN dugme YOK: onay parayi serbest birakir, talep ise onu
           tutuyor -- ikisi ayni anda dogru olamaz. Sunucu tarafi da reddediyor;
           dugmeyi gizlemek kapi degil (sold_out dersi). */
        if (!function_exists('vestra_claim_is_open')) require_once __DIR__.'/claims.php';
        if (in_array($status, ['shipped', 'delivered'], true) && !vestra_claim_is_open($ref)) {
            $h .= '<form method="post" action="'.htmlspecialchars($formHref).'" style="margin-top:10px">
              <input type="hidden" name="_action" value="confirm_receipt">
              <input type="hidden" name="ref" value="'.htmlspecialchars($ref).'">
              <button class="btn btn-p btn-sm" type="submit">✓ '.t('Confirm receipt').'</button></form>';
        }
    }
    $h .= '</div></div>'; // end side column
    $h .= '</div>'; // end odgrid

    if (!empty($statusEntry['history'])) {
        $h .= '<details style="margin-top:16px"><summary class="hint" style="cursor:pointer">'.t('Status history').'</summary><div style="margin-top:8px">';
        foreach ($statusEntry['history'] as $ev) {
            $h .= '<div class="hint" style="padding:4px 0">'.htmlspecialchars(substr($ev['at'] ?? '', 0, 16)).' — '.vestra_order_status_label($ev['status'] ?? '').
                  (!empty($ev['note']) ? ' · '.htmlspecialchars($ev['note']) : '').'</div>';
        }
        $h .= '</div></details>';
    }

    $h .= '</div>'; // end panelcard
    return $h;
}

/**
 * Delete an order outright — meant for test rows and duplicates that should never
 * have existed. Cancelling is the everyday action and leaves the record standing;
 * this removes it.
 *
 * Reads the RAW file instead of going through vestra_read_csv(), which hands rows
 * back newest-first. Writing that array straight back would silently invert the
 * whole ledger — every "recent orders" panel would start showing the oldest eight.
 * vestra_orders_fix_dup_refs() above sidesteps the same trap the same way.
 *
 * Returns rows removed, or -1 when the file could not be rewritten. The caller needs
 * that distinction: "no such order" and "the disk refused" are different faults and
 * a shared 0 would hide a permissions problem behind a reassuring message.
 */

/* ─────────────────────────── NAVLUN TARİFESİ (Avrupa + ABD) ──────────────────
 * Operatör, 19 Eyl 2026, dört cümlede: "her faturaya 10 ad. için 20 eur,
 * sonraki her 10 ad. için +5 eur shipping cost ekle" → "sadece 40+ üstü aynı
 * model siparişlerde her 100 ad. başına 30 eur yap" → "bu avrupa siparişleri
 * için geçerli" → "abd için her siparişe 30 eur + 20 ad. sonrasına her 10 ad.
 * +5 eur; tek model ve üründen alınırsa her 100 ad. 50 eur, 100 + 50 ad.'e
 * kadar 50+20 eur".
 *
 * TEK KAYNAK: rakamlar vestra_shipping_tariffs() tablosunda, hiçbir metne,
 * panele ya da mektuba gömülü değil (KURAL 6'nın escrow tavanı dersi). Kasa
 * (order.php), teklif faturası, panel ipucu/çipi, sepet önizlemesi ve iş
 * akışının `auto` kipi hepsi vestra_shipping_schedule()'ı çağırır.
 *
 * KURAL (bölge başına aynı şekil, farklı rakamlar):
 *   - HAVUZ rayı: bulk eşiğinin ALTINDAKİ satırların adedi BİRLİKTE sayılır;
 *     ilk `base_qty` adet `base`, sonraki her BAŞLAYAN `step_qty` adet +`step`.
 *     AB: 10 → 20, 11 → 25, 30 → 30.  ABD: 20 → 30, 21 → 35, 40 → 40.
 *   - TOPTAN rayı: bir SKU'da adet >= `bulk_min` ise o satır tek başına: her
 *     TAM `bulk_per` (100) adet `bulk`; kalan için AB'de yine `bulk` (başlayan
 *     100), ABD'de kalan <= `half_qty` (50) ise `half` (€20), üstü `bulk`.
 *     AB: 40 → 30, 100 → 30, 101 → 60.  ABD: 100 → 50, 150 → 70, 151 → 100.
 *   "Başlayan" okuması bir yorum: operatör "sonraki her 10 ad." dedi, kesri
 *   söylemedi. Kesri düşürmek 19 adedi 10 adetle aynı fiyata taşırdı; yukarı
 *   yuvarlamak en fazla bir basamak ekler ve ekranda görünür.
 *   ABD'nin toptan eşiği OPERATÖR VERMEDİ; AB için verdiği 40 alındı ve
 *   tabloda ayrı bir satır olarak duruyor — tek rakam, tek satır. (Not: ABD'de
 *   40–59 adetlik tek modelde toptan rayı €50, havuz rayı €40–45 — toptan ray
 *   ancak ~60'tan sonra ucuzlar; eşiği değiştirmek operatörün.)
 *
 * YALNIZ TANINAN BÖLGE: ülke Avrupa (`vestra_country_in_europe`) ya da ABD
 * (`vestra_country_is_us`) olarak POZİTİF tanınıyorsa. Boş/tanınmayan ülkede
 * tarife UYGULANMAZ (null döner) ve navlunu operatör elle yazar — Japonya'daki
 * bir alıcıya AB tarifesini uygulamak gerçek navlunun altında bir rakam basmak
 * olurdu; boş kalan satır ise panelde ve taslakta görünür (fazla sorulan soru
 * görünür, eksik tahsilat görünmez).
 *
 * TUTAR EUR (siparişin kendi birimi). USD kesilen belgede çevrimi zaten tek yer
 * yapıyor (KURAL 5i/5s). DROPSHIP KAPSAM DIŞI: o yolun kendi bölge/ücret
 * tablosu var ve ödemesi zaten durdurulmuş.
 */
function vestra_shipping_tariffs(): array {
    return [
        'eu' => ['label' => 'Shipping (EU tariff)', 'base_qty' => 10, 'base' => 20.0, 'step_qty' => 10, 'step' => 5.0,
                 'bulk_min' => 40, 'bulk_per' => 100, 'bulk' => 30.0, 'half_qty' => 0,  'half' => 0.0],
        'us' => ['label' => 'Shipping (US tariff)', 'base_qty' => 20, 'base' => 30.0, 'step_qty' => 10, 'step' => 5.0,
                 'bulk_min' => 40, 'bulk_per' => 100, 'bulk' => 50.0, 'half_qty' => 50, 'half' => 20.0],
    ];
}

/**
 * Ülke Avrupa olarak TANINIYOR mu? POZİTİF ölçüt: boş ya da tanınmayan ülke
 * false. `vestra_user_in_europe()` bunun tersini (boş = Avrupa) döndürüyor,
 * çünkü orada soru "asgari sipariş tabanından muaf mı" ve belirsizlikte kapı
 * açık kalmalı. Burada soru "Avrupa tarifesi basılsın mı" ve belirsizlikte
 * BASILMAMALI. İki fonksiyon aynı tabloyu okuyor, yönü farklı — tek
 * fonksiyona bayrak eklemek, iki çağrı yerinden birinde yanlış yönü sessizce
 * seçmeyi kolaylaştırırdı.
 */
function vestra_country_in_europe(string $country): bool {
    $raw = trim($country);
    if ($raw === '') return false;
    if (preg_match('/^[A-Za-z]{2}$/', $raw)) return in_array(strtoupper($raw), vestra_europe_codes(), true);
    $folded = trim(preg_replace('/\s+/u', ' ', strtr(mb_strtolower($raw), ['-' => ' ', '_' => ' '])));
    if ($folded === '') return false;
    foreach (vestra_europe_names() as $names) {
        if (in_array($folded, $names, true)) return true;
    }
    return false;
}

/**
 * Ülke ABD mi? POZİTİF, TAM eşleşme (mango/zara dersi): 'US'/'USA' kodu ya da
 * ülkenin bilinen yazımları. "Virgin Islands (US)" gibi bir dizge eşleşmez ve
 * eşleşmemeli — o ayrı bir gümrük alanı.
 */
function vestra_us_names(): array {
    return ['us', 'usa', 'united states', 'united states of america', 'america',
            'vereinigte staaten', 'vereinigte staaten von amerika', 'états unis', 'etats unis',
            'stati uniti', 'stati uniti d\'america', 'estados unidos', 'estados unidos de américa',
            'estados unidos da américa', 'сша', 'アメリカ', 'アメリカ合衆国', '米国'];
}
function vestra_country_is_us(string $country): bool {
    $raw = trim($country);
    if ($raw === '') return false;
    $folded = trim(preg_replace('/\s+/u', ' ', strtr(mb_strtolower($raw), ['-' => ' ', '_' => ' ', '.' => ''])));
    return in_array($folded, vestra_us_names(), true);
}

/**
 * Sepetin ÖNİZLEMESİ için ülke → bölge haritası (JS'e basılıyor).
 *
 * TÜRETİLİYOR, elle yazılmıyor: aynı `vestra_europe_codes()/names()` ve
 * `vestra_us_names()` tablolarından kuruluyor, yani sunucunun kapısı ile
 * sepetteki önizleme aynı listeyi okuyor. İkinci bir liste yazmak, bir gün
 * eklenen bir ülkede "sepette €0, kasada €30" demekti — bu depoda defalarca
 * kaydedilen *"sayfada bir, kasada başka rakam"* hatası.
 *
 * Anahtarlar GEVŞEK katlanmış (küçük harf, '-'/'_' boşluk, nokta atılmış,
 * boşluklar tek): JS tek bir katlama uyguluyor. Avrupa adlarının hiçbirinde
 * nokta yok, yani gevşeklik orada fark üretmiyor; kesin karar zaten sunucuda.
 */
function vestra_shipping_region_map(): array {
    $fold = function (string $v): string {
        return trim(preg_replace('/\s+/u', ' ', strtr(mb_strtolower($v), ['-' => ' ', '_' => ' ', '.' => ''])));
    };
    $map = [];
    foreach (vestra_europe_codes() as $cc) $map[$fold($cc)] = 'eu';
    foreach (vestra_europe_names() as $names) foreach ($names as $n) $map[$fold($n)] = 'eu';
    foreach (vestra_us_names() as $n) $map[$fold($n)] = 'us';
    return $map;
}

/** Tarife bölgesi: 'eu' | 'us' | null (tanınmayan → tarife yok, navlun elle). */
function vestra_shipping_region(string $country): ?string {
    if (vestra_country_in_europe($country)) return 'eu';
    if (vestra_country_is_us($country))     return 'us';
    return null;
}

/**
 * Satırlardan (her biri ['sku','qty']) bölge navlununu hesaplar. SAF: girdi
 * satırlar + ülke, çıktı tutar. Bölge tanınmıyorsa ya da hiç adet yoksa null.
 *
 * Döner: ['region','amount'=>float, 'label'=>string, 'qty'=>int, 'pooled_qty'=>int,
 *         'pooled'=>float, 'bulk'=>[['sku','qty','amount'],…]]
 */
function vestra_shipping_schedule(array $lines, string $country): ?array {
    $region = vestra_shipping_region($country);
    if ($region === null) return null;
    $T = vestra_shipping_tariffs()[$region];
    $pooledQty = 0; $bulk = []; $bulkSum = 0.0; $total = 0;
    foreach ($lines as $l) {
        $q = (int)($l['qty'] ?? 0);
        if ($q <= 0) continue;
        $total += $q;
        if ($q >= $T['bulk_min']) {
            $full = intdiv($q, $T['bulk_per']); $rem = $q % $T['bulk_per'];
            $amt  = $full * $T['bulk'];
            /* Kalan: yalnız TAM bir 100'ün ÜSTÜNDEKİ <= half_qty kalan `half`
               (ABD "100 + 50 ad.'e kadar 50+20"); ilk 100'e kadar her adet `bulk`
               ("her 100 ad.'e kadar 50 eur"). $full > 0 şartı olmadan 40 adet
               €20'ye taşınırdı — probe bunu yakaladı. */
            if ($rem > 0) $amt += ($full > 0 && $T['half_qty'] > 0 && $rem <= $T['half_qty']) ? $T['half'] : $T['bulk'];
            $bulk[] = ['sku' => (string)($l['sku'] ?? ''), 'qty' => $q, 'amount' => round($amt, 2)];
            $bulkSum += $amt;
        } else {
            $pooledQty += $q;
        }
    }
    if ($total <= 0) return null;
    $pooled = 0.0;
    if ($pooledQty > 0) {
        $extra  = max(0, $pooledQty - $T['base_qty']);
        $pooled = $T['base'] + (float)ceil($extra / $T['step_qty']) * $T['step'];
    }
    return ['region' => $region, 'amount' => round($pooled + $bulkSum, 2), 'label' => $T['label'],
            'qty' => $total, 'pooled_qty' => $pooledQty, 'pooled' => round($pooled, 2), 'bulk' => $bulk];
}

/** Tarifenin bir SİPARİŞ SATIRI için karşılığı (satırın kendi ülkesi ve kalemleri). */
function vestra_order_shipping_schedule(array $orderRow): ?array {
    $ld = vestra_order_lines($orderRow);
    return vestra_shipping_schedule($ld['lines'], (string)($orderRow['country'] ?? ''));
}

/* ─────────── SATICIYA ÖDEME: "BAŞARILI SİPARİŞ" TEK KARAR NOKTASI ───────────
 * Operatör, 19 Eyl 2026: *"satıcılar için siparişleri ben alıcam ve başarılı
 * olan siparişleri satıcılara ödeyeceğim, bunun yapılması için hukuki bir
 * sistem yap"*.
 *
 * Hukuk metni (Satıcı Sözleşmesi §9, Ödemeler politikası) ve SSS bu kuralı
 * ANLATIYOR; burası onu HESAPLAYAN tek yer. İkisi ayrı yazılsaydı, sözleşmede
 * yazan tarih ile operatörün ekranında gördüğü tarih er geç ayrışırdı — bu
 * depoda mektup ile otomatik iptalin son tarihi tam böyle ayrışmıştı (KURAL 7)
 * ve çözüm ikisini aynı fonksiyona bağlamak olmuştu.
 *
 * DÖRT KOŞUL, hepsi birlikte — ve hiçbiri burada yeniden TANIMLANMIYOR:
 *   1. Alıcının parası geldi  → vestra_order_payment_settled() (KURAL 7b)
 *   2. Mal teslim edildi      → sipariş zincirinde 'delivered' ya da sonrası
 *   3. Talep penceresi kapandı→ vestra_claim_deadline() (KURAL 11, İŞ GÜNÜ)
 *   4. Açık talep yok         → vestra_claim_is_open()
 * Kapının ikinci bir kopyasını yazmak bu depoda ALTI kez yanlış yere baktı;
 * yedincisi yazılmadı.
 *
 * İPTAL EDİLEN SİPARİŞ ÖDENMEZ: 'cancelled' zincirde bilerek yok
 * (VESTRA_ORDER_STEPS), yani 2. koşul onu kendiliğinden eliyor.
 *
 * TESLİM TARİHİ UYDURULMUYOR: damga yoksa pencere başlamamıştır ve fonksiyon
 * "tarih bilinmiyor" der, bugünün tarihiyle doldurmaz (KURAL 3 — aynı ders
 * `updated_at`'i ödeme tarihi sanan sondada bir kez ödendi).
 *
 * Döner: ['payable'=>bool, 'why'=>makine-okunur sebep, 'due'=>'Y-m-d' ya da '',
 *         'paid_ok'=>bool, 'delivered_at'=>'', 'claim_until'=>''].
 */
function vestra_seller_settlement(string $ref, ?array $statusEntry = null): array {
    require_once __DIR__.'/escrow.php';
    $ref = preg_replace('/[^A-Za-z0-9_-]/', '', trim($ref));
    if ($statusEntry === null && $ref !== '') {
        $statusEntry = (array)((vestra_read_json('order_statuses.json'))[$ref] ?? []);
    }
    $st     = (array)($statusEntry ?? []);
    $status = (string)($st['status'] ?? 'pending');
    $out = ['payable' => false, 'why' => '', 'due' => '', 'paid_ok' => false,
            'delivered_at' => '', 'claim_until' => '', 'status' => $status];

    $pay = vestra_order_payment_settled($ref, $st);
    $out['paid_ok'] = !empty($pay['settled']);
    if (!$out['paid_ok']) { $out['why'] = 'unpaid'; return $out; }

    /* TESLİMAT: zincirde 'delivered' ya da sonrası. Elle yazılmış bir durum
       listesi değil zincirin kendisi ölçüt — yarın araya bir adım girerse
       (bu depoda 'to_vestra' böyle girdi) o da kendiliğinden doğru tarafta
       kalır. */
    $iNow = array_search($status, VESTRA_ORDER_STEPS, true);
    $iDel = array_search('delivered', VESTRA_ORDER_STEPS, true);
    if ($iNow === false || $iDel === false || $iNow < $iDel) { $out['why'] = 'not_delivered'; return $out; }

    /* Teslim ANI: açık damga, yoksa geçmişteki 'delivered' satırı. `updated_at`
       BİLEREK okunmuyor — o kaydın son yazılma anı, malın teslim anı değil. */
    $dAt = trim((string)($st['delivered_at'] ?? ''));
    if ($dAt === '') {
        foreach ((array)($st['history'] ?? []) as $h) {
            if ((string)($h['status'] ?? '') === 'delivered') { $dAt = trim((string)($h['at'] ?? '')); break; }
        }
    }
    $out['delivered_at'] = $dAt;
    $dTs = $dAt !== '' ? strtotime($dAt) : false;
    if (!$dTs) { $out['why'] = 'no_delivery_date'; return $out; }

    if (function_exists('vestra_claim_is_open') && $ref !== '' && vestra_claim_is_open($ref)) {
        $out['why'] = 'claim_open'; return $out;
    }

    $claimEnd = vestra_claim_deadline($dTs);
    $out['claim_until'] = date('Y-m-d', $claimEnd);
    $dueTs = vestra_business_days_after($claimEnd, VESTRA_SELLER_SETTLEMENT_DAYS);
    $out['due'] = date('Y-m-d', $dueTs);
    if (time() < $claimEnd) { $out['why'] = 'claim_window'; return $out; }

    $out['payable'] = true;
    $out['why']     = 'payable';
    return $out;
}

/**
 * Bir siparişe İNDİRİM yazar (operatör, 19 Eyl 2026: *"Yeni yaptığımız
 * siparişlere yüzde 5 indirim uygula welcome code"*).
 *
 * `discount` ve `voucher_code` sütunları kasadan beri var (order.php kupon
 * yolu); onlara sonradan yazan HİÇBİR yol yoktu. Fatura (vestra_order_invoice_
 * payloads), sipariş PDF'i ve panel hepsi bu iki alanı zaten okuyor ve
 * `Voucher <kod>  -€x` satırı olarak basıyor — yani alan eklenmedi, yalnız
 * yazıcı. Kupon KAYDINA (vouchers.json) dokunmuyor: kodu bulan/yaratan/yakan
 * çağıran taraf (iş akışı `admin_mode=discount`), çünkü o karar hesaba ve
 * kampanyaya bakıyor, satırın kendisine değil.
 *
 * İKİ ALAN BİRLİKTE + TOPLAM: `discount`, `voucher_code` ve `total` (= mal −
 * indirim + navlun). Yalnız indirimi yazıp toplamı bırakmak, alıcının sipariş
 * sayfası ile faturasını iki rakama bölerdi (KURAL 5f'in üç-katman dersi);
 * navlun yazıcısıyla aynı formül, aynı `vestra_order_lines`.
 *
 * NOTA DA DÜŞER: kasa kuponu notların sonuna `Voucher KOD (-5%) = -€x.` diye
 * yazıyor ve alıcı sipariş sayfasında onu görüyor; sonradan uygulanan indirim
 * aynı biçimde, aynı yere. İkinci uygulamada eski parça sökülür (tek kopya).
 *
 * FATURASI KESİLMİŞ siparişte varsayılan RED; `$allowInvoiced` ile yazar ve
 * `must_redraft` döner (KURAL 5f: aynı numarayla yeniden çizim — renk
 * yazıcısının deseni). 0 yazmak indirimi KALDIRIR (kod da silinir).
 */
function vestra_order_set_discount(string $ref, float $amount, string $code = '', bool $allowInvoiced = false): array {
    $ref = preg_replace('/[^A-Za-z0-9_-]/', '', trim($ref));
    if ($ref === '') return ['error' => 'ref yok'];
    if (!is_finite($amount) || $amount < 0) return ['error' => 'indirim negatif olamaz'];
    $amount = round($amount, 2);
    $code   = strtoupper(trim(preg_replace('/[^A-Za-z0-9_-]/', '', $code)));
    if ($amount > 0 && $code === '') return ['error' => 'indirim için kupon kodu şart — belgede "Voucher <kod>" satırı olarak basılıyor'];

    require_once __DIR__.'/invoice.php';
    $invoiced = (bool)vestra_invoices_for_ref($ref);
    if ($invoiced && !$allowInvoiced) {
        return ['error' => 'bu siparişin faturası zaten kesilmiş — indirim belgeyi kendiliğinden değiştirmez '
                         . '(KURAL 5f: |allow_invoiced=1 ile yaz ve AYNI numarayla yeniden çiz)'];
    }

    $file = vestra_data_dir().'/orders.csv';
    if (!is_readable($file)) return ['error' => 'orders.csv okunamıyor'];
    /* HAM dosya: vestra_read_csv() satırları ters çeviriyor (navlun/adres
       yazıcılarıyla aynı tuzak). */
    $in = fopen($file, 'r'); if (!$in) return ['error' => 'orders.csv açılamadı'];
    $head = fgetcsv($in, null, ',', '"', '\\');
    if (!$head) { fclose($in); return ['error' => 'orders.csv başlıksız']; }
    $idx = array_flip($head);
    foreach (['ref', 'discount', 'voucher_code', 'total', 'notes'] as $need) {
        if (!isset($idx[$need])) { fclose($in); return ['error' => "orders.csv '{$need}' sütunu yok"]; }
    }
    $rows = []; $hit = null;
    while (($r = fgetcsv($in, null, ',', '"', '\\')) !== false) {
        $r = array_slice(array_pad($r, count($head), ''), 0, count($head));
        if ((string)$r[$idx['ref']] === $ref) $hit = count($rows);
        $rows[] = $r;
    }
    fclose($in);
    if ($hit === null) return ['error' => 'sipariş bulunamadı: '.$ref];

    $assoc = array_combine($head, $rows[$hit]);
    $ld    = vestra_order_lines($assoc);
    $goods = 0.0;
    foreach ($ld['lines'] as $l) $goods += (float)($l['line'] ?? 0);
    $goods = round($goods, 2);
    if ($amount > $goods) return ['error' => 'indirim mal toplamından ('.number_format($goods, 2).') büyük olamaz'];
    $shipping = round((float)($assoc['shipping'] ?? 0), 2);
    $total    = round(max(0.0, $goods - $amount) + $shipping, 2);
    $pctLbl   = $goods > 0 ? rtrim(rtrim(number_format($amount / $goods * 100, 2, '.', ''), '0'), '.') : '0';

    $notes = trim((string)$assoc['notes']);
    $notes = trim(preg_replace('/\s*Voucher [A-Z0-9_-]+ \([^)]*\) = -€[0-9.,]+\./u', '', $notes));
    if ($amount > 0) $notes = trim($notes.' Voucher '.$code.' (-'.$pctLbl.'%) = -€'.number_format($amount, 2, '.', '').'.');

    $rows[$hit][$idx['discount']]     = $amount > 0 ? number_format($amount, 2, '.', '') : '';
    $rows[$hit][$idx['voucher_code']] = $amount > 0 ? $code : '';
    $rows[$hit][$idx['total']]        = number_format($total, 2, '.', '');
    $rows[$hit][$idx['notes']]        = $notes;

    @copy($file, $file.'.bak-disc-'.date('Ymd_His'));
    $tmp = $file.'.tmp';
    $out = fopen($tmp, 'w'); if (!$out) return ['error' => 'geçici dosya açılamadı'];
    fputcsv($out, $head, ',', '"', '\\');
    foreach ($rows as $r) fputcsv($out, $r, ',', '"', '\\');
    fclose($out);
    if (!rename($tmp, $file)) { @unlink($tmp); return ['error' => 'orders.csv yazılamadı (izin?)']; }

    /* GERİ OKU — satır ve FATURANIN göreceği rakam. */
    $back = null;
    foreach (vestra_read_csv('orders.csv') as $r) { if (($r['ref'] ?? '') === $ref) { $back = $r; break; } }
    if (!$back || abs((float)($back['discount'] ?? -1) - $amount) > 0.004
               || abs((float)($back['total'] ?? -1) - $total) > 0.004
               || (string)($back['voucher_code'] ?? '') !== ($amount > 0 ? $code : '')) {
        return ['error' => 'yazıldı ama geri okuma tutmadı — kayıt değişmemiş olabilir'];
    }

    $st = vestra_read_json('order_statuses.json');
    if (!isset($st[$ref]) || !is_array($st[$ref])) $st[$ref] = [];
    $st[$ref]['discount_set_at'] = date('c');
    $st[$ref]['discount_set_by'] = 'operator';
    vestra_write_json('order_statuses.json', $st);

    return ['ok' => true, 'goods' => $goods, 'discount' => $amount, 'code' => $amount > 0 ? $code : '',
            'pct' => $pctLbl, 'shipping' => $shipping, 'total' => $total,
            'must_redraft' => $invoiced];
}

/**
 * Bir siparişe NAVLUN yazar (operatör, 7 Eyl 2026: *"kargo bölümü yok kargo
 * eklemek gerekiyor"* — VES-6B53D265).
 *
 * TEKLİF faturasında navlun alanı vardı (`offer_responses.json.invoice_shipping`,
 * `Admin ▸ Invoice approvals`'taki "Kargo €" kutusu, 1 Eyl 2026'da tam bu sebeple
 * eklenmişti), SİPARİŞTE yoktu: sipariş sayfası navlunu yalnızca GÖSTERİYOR
 * (`admin.php`, `shipping > 0` ise satır), yazacak hiçbir yol yoktu.
 *
 * TUTAR SİPARİŞİN KENDİ PARA BİRİMİNDE saklanır. Belge başka birimde kesiliyorsa
 * çevrimi zaten `vestra_invoice_convert_payload()` yapıyor — burada ikinci bir
 * çevrim, aynı rakamın iki yerde hesaplanması olurdu (KURAL 5i).
 *
 * İKİ ALAN BİRLİKTE HAREKET EDER: `shipping` ve `total`. Sipariş satırının
 * toplamı navlunu İÇERİYOR (denetimi `diag-live` "toplam farki (kayitli - mal -
 * navlun)" satırı yapıyor) — yalnız birini yazmak o denetimi kırar ve alıcının
 * sipariş sayfası ile faturası iki ayrı rakam gösterir. Mal toplamı faturanın
 * okuduğu **aynı** fonksiyondan (`vestra_order_lines`) geliyor, ikinci bir
 * ayrıştırıcıdan değil.
 *
 * FATURASI KESİLMİŞ SİPARİŞTE YAZMAZ: belge alıcının elinde ve numara yanmış.
 * Doğru yol KURAL 5f (aynı numarayla yeniden çizim), sessizce kaydı değiştirmek
 * değil — kayıt ile belge ayrışırsa farkı ancak alıcı görür.
 *
 * Döner: ['ok'=>true, 'goods'=>…, 'shipping'=>…, 'total'=>…] ya da
 * ['error'=>gerekçe]. Yazma GERİ OKUNARAK doğrulanıyor (KURAL 5c'nin
 * `billing_saved` dersi: yazılamayan bir değeri "kaydettim" diye raporlamak,
 * operatöre olmayan bir kaydı doğru sandırır).
 */
function vestra_order_set_shipping(string $ref, float $amount, string $label = ''): array {
    $ref = preg_replace('/[^A-Za-z0-9_-]/', '', trim($ref));
    if ($ref === '') return ['error' => 'ref yok'];
    if (!is_finite($amount) || $amount < 0) return ['error' => 'navlun negatif olamaz'];
    $amount = round($amount, 2);
    $label  = trim($label);

    require_once __DIR__.'/invoice.php';
    if (vestra_invoices_for_ref($ref)) {
        return ['error' => 'bu siparişin faturası zaten kesilmiş — navlun artık belgeyi değiştirmez '
                         . '(KURAL 5f: aynı numarayla yeniden çizim ya da iptal + yeniden kesim)'];
    }

    $file = vestra_data_dir().'/orders.csv';
    if (!is_readable($file)) return ['error' => 'orders.csv okunamıyor'];
    /* HAM dosya okunuyor: vestra_read_csv() satırları en yeniden eskiye çeviriyor
       ve o diziyi geri yazmak bütün defteri ters çevirirdi (bkz. yukarıdaki not). */
    $in = fopen($file, 'r'); if (!$in) return ['error' => 'orders.csv açılamadı'];
    $head = fgetcsv($in, null, ',', '"', '\\');
    if (!$head) { fclose($in); return ['error' => 'orders.csv başlıksız']; }
    $idx = array_flip($head);
    if (!isset($idx['ref'])) { fclose($in); return ['error' => 'orders.csv ref sütunu yok'] ; }
    foreach (['shipping', 'shipping_label', 'total'] as $need) {
        if (!isset($idx[$need])) { fclose($in); return ['error' => "orders.csv '{$need}' sütunu yok"]; }
    }

    $rows = []; $hit = null;
    while (($r = fgetcsv($in, null, ',', '"', '\\')) !== false) {
        $r = array_slice(array_pad($r, count($head), ''), 0, count($head));
        if ((string)$r[$idx['ref']] === $ref) $hit = count($rows);
        $rows[] = $r;
    }
    fclose($in);
    if ($hit === null) return ['error' => 'sipariş bulunamadı: '.$ref];

    $assoc = array_combine($head, $rows[$hit]);
    $ld    = vestra_order_lines($assoc);
    $goods = 0.0;
    foreach ($ld['lines'] as $l) $goods += (float)($l['line'] ?? 0);
    $goods    = round($goods, 2);
    $discount = round((float)($assoc['discount'] ?? 0), 2);
    $total    = round(max(0.0, $goods - $discount) + $amount, 2);

    $rows[$hit][$idx['shipping']]       = number_format($amount, 2, '.', '');
    $rows[$hit][$idx['shipping_label']] = $label;
    $rows[$hit][$idx['total']]          = number_format($total, 2, '.', '');

    @copy($file, $file.'.bak-ship-'.date('Ymd_His'));
    $tmp = $file.'.tmp';
    $out = fopen($tmp, 'w'); if (!$out) return ['error' => 'geçici dosya açılamadı'];
    fputcsv($out, $head, ',', '"', '\\');
    foreach ($rows as $r) fputcsv($out, $r, ',', '"', '\\');
    fclose($out);
    if (!rename($tmp, $file)) { @unlink($tmp); return ['error' => 'orders.csv yazılamadı (izin?)'] ; }

    /* GERİ OKU. Dosyaya yazdıktan sonra kaydın gerçekten öyle olduğunu görmeden
       "kaydedildi" demiyoruz. */
    $back = null;
    foreach (vestra_read_csv('orders.csv') as $r) { if (($r['ref'] ?? '') === $ref) { $back = $r; break; } }
    if (!$back || abs((float)($back['shipping'] ?? -1) - $amount) > 0.004
               || abs((float)($back['total'] ?? -1) - $total) > 0.004) {
        return ['error' => 'yazıldı ama geri okuma tutmadı — kayıt değişmemiş olabilir'];
    }

    /* Kim/ne zaman: mutable sipariş durumu zaten burada duruyor (escrow, fatura
       seçimi, FX damgası). Rakamın kendisi CSV'de; bu yalnızca iz. */
    $st = vestra_read_json('order_statuses.json');
    if (!isset($st[$ref]) || !is_array($st[$ref])) $st[$ref] = [];
    $st[$ref]['shipping_set_at'] = date('c');
    $st[$ref]['shipping_set_by'] = 'operator';
    vestra_write_json('order_statuses.json', $st);

    return ['ok' => true, 'goods' => $goods, 'discount' => $discount,
            'shipping' => $amount, 'label' => $label, 'total' => $total];
}

/**
 * Bir siparişe TESLİMAT ADRESİ yazar (operatör, 7 Eyl 2026: *"kargo yeri aç"*).
 *
 * Adres siparişin `notes` alanında `Deliver to: …` parçası olarak duruyor —
 * `vestra_invoice_buyer()` faturayı oradan besliyor, sipariş ekranı da oradan
 * okuyup gösteriyor. GÖSTERİYOR ama yazacak yer yoktu: adres yalnızca alıcı
 * sipariş verirken yazabiliyordu, sonradan gelen bir adresi operatör hiçbir
 * yere giremiyordu. VES-6B53D265'te fatura taslağı üç koşu boyunca "no street
 * address on file — gümrük ve kurye ister" diye uyardı ve yapılabilecek bir şey
 * yoktu. (Navlunda da aynı boşluk vardı; bkz. `vestra_order_set_shipping`.)
 *
 * NOTLARIN GERİSİNE DOKUNMAZ: `Payment: …`, `Colours — …` gibi parçalar aynen
 * kalır, yalnızca `Deliver to: …` parçası değiştirilir/eklenir. Notları
 * baştan yazmak, siparişin kendi kaydından bilgi silmek olurdu.
 *
 * Faturası kesilmiş sipariş REDDEDİLİR: adres belgenin üzerinde ve alıcının
 * elinde; kaydı sessizce değiştirmek ikisini ayrıştırır (KURAL 5f).
 */
function vestra_order_set_delivery(string $ref, string $address): array {
    $ref = preg_replace('/[^A-Za-z0-9_-]/', '', trim($ref));
    if ($ref === '') return ['error' => 'ref yok'];
    /* Tek satıra indiriliyor: notlar tek CSV alanı ve ayrıştırıcı `Deliver to:`
       parçasını nokta+boşluğa kadar okuyor. Satır sonu bırakmak adresi ortadan
       keserdi. */
    $address = trim(preg_replace('/\s+/u', ' ', $address));
    if (mb_strlen($address) > 300) return ['error' => 'adres çok uzun (300 karakter sınırı)'];

    require_once __DIR__.'/invoice.php';
    if (vestra_invoices_for_ref($ref)) {
        return ['error' => 'bu siparişin faturası zaten kesilmiş — adres belgenin üzerinde ve alıcının '
                         . 'elinde (KURAL 5f: aynı numarayla yeniden çizim)'];
    }

    $file = vestra_data_dir().'/orders.csv';
    if (!is_readable($file)) return ['error' => 'orders.csv okunamıyor'];
    $in = fopen($file, 'r'); if (!$in) return ['error' => 'orders.csv açılamadı'];
    $head = fgetcsv($in, null, ',', '"', '\\');
    if (!$head) { fclose($in); return ['error' => 'orders.csv başlıksız']; }
    $idx = array_flip($head);
    if (!isset($idx['ref'], $idx['notes'])) { fclose($in); return ['error' => 'orders.csv ref/notes sütunu yok']; }

    $rows = []; $hit = null;
    while (($r = fgetcsv($in, null, ',', '"', '\\')) !== false) {
        $r = array_slice(array_pad($r, count($head), ''), 0, count($head));
        if ((string)$r[$idx['ref']] === $ref) $hit = count($rows);
        $rows[] = $r;
    }
    fclose($in);
    if ($hit === null) return ['error' => 'sipariş bulunamadı: '.$ref];

    $notes = (string)$rows[$hit][$idx['notes']];
    /* Var olan parça çıkarılıyor (okuyucunun kullandığı kalıbın aynısı), sonra
       yenisi ekleniyor. İki ayrı kalıp yazmak, bir gün birinin diğerinin
       yazdığını bulamaması demek. */
    $notes = trim(preg_replace('/Deliver to: .*?(?:\.\s|\.$|$)/u', '', $notes));
    if ($address !== '') $notes = trim($notes . ($notes !== '' ? ' ' : '') . 'Deliver to: ' . $address . '.');
    $rows[$hit][$idx['notes']] = $notes;

    @copy($file, $file.'.bak-addr-'.date('Ymd_His'));
    $tmp = $file.'.tmp';
    $out = fopen($tmp, 'w'); if (!$out) return ['error' => 'geçici dosya açılamadı'];
    fputcsv($out, $head, ',', '"', '\\');
    foreach ($rows as $r) fputcsv($out, $r, ',', '"', '\\');
    fclose($out);
    if (!rename($tmp, $file)) { @unlink($tmp); return ['error' => 'orders.csv yazılamadı (izin?)']; }

    /* GERİ OKU — hem satırı hem de faturanın gerçekten ne göreceğini. Kaydın
       değiştiğini görmek yetmez: belgeyi besleyen `vestra_invoice_buyer()` aynı
       adresi çözebiliyor mu, onu da doğruluyoruz. */
    $back = null;
    foreach (vestra_read_csv('orders.csv') as $r) { if (($r['ref'] ?? '') === $ref) { $back = $r; break; } }
    if (!$back) return ['error' => 'yazıldı ama satır geri okunamadı'];
    $seen = trim((string)(vestra_invoice_buyer($back)['address'] ?? ''));
    if ($address !== '' && $seen !== $address) {
        return ['error' => 'yazıldı ama fatura bu adresi göremiyor ("'.mb_substr($seen, 0, 40).'…")'];
    }

    $st = vestra_read_json('order_statuses.json');
    if (!isset($st[$ref]) || !is_array($st[$ref])) $st[$ref] = [];
    $st[$ref]['delivery_set_at'] = date('c');
    $st[$ref]['delivery_set_by'] = 'operator';
    vestra_write_json('order_statuses.json', $st);

    return ['ok' => true, 'address' => $address, 'on_invoice' => $seen, 'notes' => $notes];
}

/**
 * Bir SKU'nun RENKLERİNİ siparişin notlarında düzeltir (17 Eyl 2026, operatör:
 * *"renkleri faturada siyah ve navy olarak degistir"*).
 *
 * NEDEN AYRI BİR YAZICI GEREKTİ: renk siparişin notlarında duruyor ve oraya
 * yazan tek yol kasaydı (`order.php`) — yani sipariş yazıldıktan sonra rengi
 * düzeltmenin HİÇBİR yolu yoktu. Navlun ve teslimat adresi bu boşluğu bir kez
 * kapattı; renk açık kalmıştı. Kardeşlerinin desenini aynen izliyor: notların
 * GERİSİNE dokunmaz, önce yedekler, geçici dosyaya yazıp atomik takas eder ve
 * **faturanın gerçekten ne gördüğünü** geri okur.
 *
 * FATURASI KESİLMİŞ SİPARİŞ: kardeşleri koşulsuz reddediyor. Burada koşulsuz
 * reddetmek yanlış olurdu — KURAL 5f'in çaresi tam olarak "aynı numarayla
 * yeniden çizim" ve rengi düzeltmeden yeniden çizmenin anlamı yok. Bu yüzden
 * izin AÇIK bir opt-in (`$allowInvoiced`), sessizce atlanan bir kontrol değil,
 * ve dönüşte `must_redraft` var: çağıran belgeyi yeniden çizmezse kayıt ile
 * belge AYRIŞIR (faturanın üç katmanı dersi tam bundan doğdu).
 *
 * İLANIN RENK LİSTESİ DOĞRULANMIYOR, ama SESSİZ de kalmıyor: `not_listed`
 * alanında ilanda bulunmayan renkler dönüyor. Sipariş kapısı rengi ilandan
 * doğruluyor (KURAL 21b) ve orada doğru olan bu; burada ise operatör
 * müşterinin GERÇEKTEN aldığı malı söylüyor ve katalog kaydı eksik olabilir.
 * Belgeyi kataloğa uydurmak için satılan malı yanlış yazmak KURAL 3'ün tersi
 * olurdu. Karar operatörün — çağıran uyarıyı basar.
 */
function vestra_order_set_colours(string $ref, string $sku, array $colours, bool $allowInvoiced = false): array {
    $ref = preg_replace('/[^A-Za-z0-9_-]/', '', trim($ref));
    if ($ref === '') return ['error' => 'ref yok'];
    $sku = trim($sku);
    if ($sku === '') return ['error' => 'sku yok'];

    /* Virgül ve boru AYIRAÇ: bir rengin adında geçerlerse harita bozulur. */
    $clean = [];
    foreach ($colours as $c) {
        $c = trim(preg_replace('/\s+/u', ' ', (string)$c));
        if ($c === '') continue;
        if (str_contains($c, ',') || str_contains($c, '|')) return ['error' => 'renk adında , ya da | olamaz: '.$c];
        $clean[] = $c;
    }
    if (!$clean) return ['error' => 'en az bir renk gerekli'];

    require_once __DIR__.'/invoice.php';
    $invoiced = vestra_invoices_for_ref($ref);
    if ($invoiced && !$allowInvoiced) {
        return ['error' => 'bu siparişin faturası kesilmiş — belge alıcının elinde olabilir. '
                         . 'Düzeltmek için KURAL 5f: renk yazıldıktan sonra AYNI numarayla yeniden çizilmeli '
                         . '(allow_invoiced opt-in ile çağır).'];
    }

    $file = vestra_data_dir().'/orders.csv';
    if (!is_readable($file)) return ['error' => 'orders.csv okunamıyor'];
    $in = fopen($file, 'r'); if (!$in) return ['error' => 'orders.csv açılamadı'];
    $head = fgetcsv($in, null, ',', '"', '\\');
    if (!$head) { fclose($in); return ['error' => 'orders.csv başlıksız']; }
    $idx = array_flip($head);
    if (!isset($idx['ref'], $idx['notes'])) { fclose($in); return ['error' => 'orders.csv ref/notes sütunu yok']; }

    $rows = []; $hit = null;
    while (($r = fgetcsv($in, null, ',', '"', '\\')) !== false) {
        $r = array_slice(array_pad($r, count($head), ''), 0, count($head));
        if ((string)$r[$idx['ref']] === $ref) $hit = count($rows);
        $rows[] = $r;
    }
    fclose($in);
    if ($hit === null) return ['error' => 'sipariş bulunamadı: '.$ref];

    $notes = (string)$rows[$hit][$idx['notes']];
    /* OKUYUCUNUN kendi ayrıştırıcısıyla sökülüyor: ikinci bir kalıp yazmak, bir
       gün birinin diğerinin yazdığını bulamaması demek — bu depoda tam olarak
       bu yaşandı (`Colours — …` kalıbı başa bağlıydı, hiçbir gerçek siparişe
       uymuyordu ve renkler yıllarca hiç okunmadı). */
    [$map, $rest] = vestra_order_notes_map($notes, 'Colours');
    $before = $map[$sku] ?? [];
    $map[$sku] = $clean;

    $segs = [];
    foreach ($map as $k => $v) {
        $v = array_values(array_filter((array)$v, fn($x) => trim((string)$x) !== ''));
        if ($v) $segs[] = $k.': '.implode(', ', $v);
    }
    $notes = trim($rest);
    if ($segs) $notes = trim(($notes !== '' ? $notes.' ' : '').'Colours — '.implode(' | ', $segs).'.');
    $rows[$hit][$idx['notes']] = $notes;

    @copy($file, $file.'.bak-col-'.date('Ymd_His'));
    $tmp = $file.'.tmp';
    $out = fopen($tmp, 'w'); if (!$out) return ['error' => 'geçici dosya açılamadı'];
    fputcsv($out, $head, ',', '"', '\\');
    foreach ($rows as $r) fputcsv($out, $r, ',', '"', '\\');
    fclose($out);
    if (!rename($tmp, $file)) { @unlink($tmp); return ['error' => 'orders.csv yazılamadı (izin?)']; }

    /* GERİ OKU — satırın değişmesi yetmez, BELGEYİ besleyen yolun aynı rengi
       görmesi gerekiyor. `vestra_order_lines()` fatura, sipariş sayfası ve
       panelin üçünün birden okuduğu fonksiyon. */
    $back = null;
    foreach (vestra_read_csv('orders.csv') as $r) { if (($r['ref'] ?? '') === $ref) { $back = $r; break; } }
    if (!$back) return ['error' => 'yazıldı ama satır geri okunamadı'];
    $seen = [];
    foreach (vestra_order_lines($back)['lines'] as $l) if ((string)$l['sku'] === $sku) { $seen = (array)$l['colors']; break; }
    if (array_map('strval', $seen) !== $clean) {
        return ['error' => 'yazıldı ama fatura bu rengi göremiyor ("'.implode(', ', $seen).'")'];
    }

    /* İlanın kendi renk listesi: doğrulama DEĞİL, uyarı. */
    $notListed = [];
    $p = function_exists('vestra_product_by_sku') ? vestra_product_by_sku($sku) : null;
    if ($p) {
        $have = array_map(fn($x) => mb_strtolower(trim((string)$x)), (array)($p['colors'] ?? []));
        foreach ($clean as $c) if ($have && !in_array(mb_strtolower($c), $have, true)) $notListed[] = $c;
    }

    $st = vestra_read_json('order_statuses.json');
    if (!isset($st[$ref]) || !is_array($st[$ref])) $st[$ref] = [];
    $st[$ref]['colours_set_at'] = date('c');
    $st[$ref]['colours_set_by'] = 'operator';
    vestra_write_json('order_statuses.json', $st);

    return ['ok' => true, 'sku' => $sku, 'before' => $before, 'colours' => $clean,
            'on_invoice' => $seen, 'not_listed' => $notListed, 'notes' => $notes,
            'must_redraft' => (bool)$invoiced];
}

function vestra_order_delete(string $ref): int {
    $ref = trim($ref);
    if ($ref === '') return 0;
    $file = vestra_data_dir().'/orders.csv';
    if (!is_readable($file)) return 0;
    $in = fopen($file, 'r'); if (!$in) return -1;
    $head = fgetcsv($in, null, ',', '"', '\\');
    if (!$head) { fclose($in); return 0; }
    $refIdx = array_search('ref', $head, true);
    if ($refIdx === false) { fclose($in); return 0; }

    $keep = []; $removed = 0;
    while (($r = fgetcsv($in, null, ',', '"', '\\')) !== false) {
        if ((string)($r[$refIdx] ?? '') === $ref) { $removed++; continue; }
        $keep[] = $r;
    }
    fclose($in);
    if (!$removed) return 0;

    /* Copy first. This is the one admin action with no undo, and a timestamped file
       costs nothing next to a row that cannot be typed back in. */
    @copy($file, $file.'.bak-del-'.date('Ymd_His'));

    $tmp = $file.'.tmp';
    $out = fopen($tmp, 'w'); if (!$out) return -1;
    fputcsv($out, $head, ',', '"', '\\');
    foreach ($keep as $r) fputcsv($out, $r, ',', '"', '\\');
    fclose($out);
    if (!rename($tmp, $file)) { @unlink($tmp); return -1; }   // atomic swap

    $all = vestra_read_json('order_statuses.json');
    if (isset($all[$ref])) { unset($all[$ref]); vestra_write_json('order_statuses.json', $all); }
    return $removed;
}

/* ── Duplicate-ref repair ────────────────────────────────────────────────────
 * Before the ref-collision fix, a ref was derived from buyer+items only, so the
 * same buyer reordering the same goods got the SAME ref. Status/tracking/escrow
 * all key on the ref, so those orders shared one status entry — updating one
 * order "changed them all" in the admin. This one-time repair keeps the FIRST
 * (oldest) occurrence — its invoices stay linked — and gives every later
 * duplicate a fresh unique ref, cloning the shared status entry so each order
 * keeps the status the admin last saw. Returns how many rows were re-reffed. */
function vestra_orders_fix_dup_refs(): int {
    $file = vestra_data_dir().'/orders.csv';
    if (!is_readable($file)) return 0;
    $in = fopen($file, 'r'); if (!$in) return 0;
    $head = fgetcsv($in, null, ',', '"', '\\');
    if (!$head) { fclose($in); return 0; }
    $refIdx = array_search('ref', $head, true);
    if ($refIdx === false) { fclose($in); return 0; }
    $rows = [];
    while (($r = fgetcsv($in, null, ',', '"', '\\')) !== false) $rows[] = $r;
    fclose($in);

    $statuses = vestra_read_json('order_statuses.json');
    $seen = []; $fixed = 0;
    $existing = array_flip(array_map(fn($r) => (string)($r[$refIdx] ?? ''), $rows));
    foreach ($rows as &$r) {
        $ref = (string)($r[$refIdx] ?? '');
        if ($ref === '') continue;
        if (!isset($seen[$ref])) { $seen[$ref] = true; continue; }
        do { $new = 'VES-'.strtoupper(substr(md5(random_bytes(16)), 0, 8)); }
        while (isset($seen[$new]) || isset($existing[$new]));
        if (isset($statuses[$ref])) $statuses[$new] = $statuses[$ref];
        $r[$refIdx] = $new; $seen[$new] = true; $existing[$new] = true; $fixed++;
    }
    unset($r);
    if (!$fixed) return 0;

    $tmp = $file.'.tmp';
    $out = fopen($tmp, 'w'); if (!$out) return 0;
    fputcsv($out, $head, ',', '"', '\\');
    foreach ($rows as $r) fputcsv($out, $r, ',', '"', '\\');
    fclose($out);
    rename($tmp, $file);   // atomic swap — readers never see a half-written file
    vestra_write_json('order_statuses.json', $statuses);
    return $fixed;
}

/* ── Payment reminder / auto-cancel (operator decision, 2 Sep 2026) ─────────
 * "Siparişlerin ödemesi 5 iş günü içerisinde gelmez ise otomatik kapanacağını
 * söyle" — a pending order with an ISSUED invoice (bank transfer, not escrow)
 * gets one reminder, and is cancelled automatically if nothing arrives within
 * 5 business days of that reminder. Both cron_order_payment.php and a one-off
 * operator letter (buyer_reply → payment_due) call the SAME sender below, so
 * the deadline a letter promises is the deadline that actually fires — the
 * lesson this repo has paid for more than once (KURAL 3, the tracking-soon
 * letter, the escrow ceiling: a written promise the code doesn't keep is worse
 * than not writing it). */
const VESTRA_ORDER_PAYMENT_GRACE_DAYS = 5;

/**
 * Pure phase machine for ONE order's payment clock. The caller decides
 * ELIGIBILITY (status is 'pending', an invoice exists, it is not an escrow
 * order) before calling this — mirrors auth_seller_doc_grace()'s split between
 * eligibility and clock math, for the same reason: clock math has to be
 * testable without a live order, and every caller re-deriving eligibility its
 * own way is how the checks drift apart.
 *
 * 'has_receipt': a bank-transfer receipt is already on file for this order.
 * The clock STOPS — cron must never auto-cancel an order the buyer has already
 * paid just because nobody has looked at the receipt yet (the same lesson
 * KURAL 2f encodes for seller documents: no automatic punishment while
 * evidence sits unread). The operator still confirms and marks it paid by
 * hand; this only prevents the wrong kind of automatic action.
 *
 * Business-day math is escrow.php's vestra_business_days_after($ts, $days) — the
 * escrow auto-release sweep (31 Aug 2026 decision) already needed the identical
 * "N business days, Sat/Sun skipped" clock, and a second copy of the same loop
 * here is exactly how this repo has drifted before: two functions of the same
 * shape, in two files, is one INSTANT fatal ("Cannot redeclare") the moment both
 * files load on the same request — which is every admin/buyer/seller page.
 *
 * 'paid': the money is IN. The clock must not merely pause, it must never have
 * started — chasing a customer who has already paid is worse than not chasing
 * at all, and the end of this clock is an AUTOMATIC CANCELLATION of a settled
 * sale. Asked here rather than left to each caller because this function is
 * where "should we chase this payment" is decided (CLAUDE.md: "Saat, ilk mektup
 * ve karar mantigi TEK yerde"); a caller-side check is a rule left to memory,
 * and the next caller written would miss it exactly as the fourth one did.
 *
 * @param array  $statusEntry order_statuses.json[$ref] (or a fresh ['status'=>'pending'])
 * @param string $ref         verilirse teklif faturasinin odendi isareti de sorulur.
 *                            Bos birakildiginda fonksiyon SAF kalir (dosya
 *                            okumaz) -- saat aritmetigi canli siparis olmadan
 *                            sinanabilsin diye, yukaridaki eligibility/clock
 *                            ayriminin ayni gerekcesi.
 */
function vestra_order_payment_grace(array $statusEntry, ?int $now = null, string $ref = ''): array {
    if (!function_exists('vestra_business_days_after')) require_once __DIR__.'/escrow.php';
    $now = $now ?? time();
    $settled = vestra_order_payment_settled($ref, $statusEntry);
    if ($settled['settled']) {
        return ['phase'=>'paid', 'start'=>null, 'notice_sent'=>false, 'deadline'=>null, 'days_left'=>null,
                'paid_via'=>$settled['via'], 'paid_at'=>$settled['at']];
    }
    $receipt = $statusEntry['payment_receipt'] ?? null;
    if (is_array($receipt) && !empty($receipt['file'])) {
        return ['phase'=>'has_receipt', 'start'=>null, 'notice_sent'=>false, 'deadline'=>null, 'days_left'=>null];
    }
    $start = trim((string)($statusEntry['payment_grace_start'] ?? ''));
    if ($start === '') {
        return ['phase'=>'unstamped', 'start'=>null, 'notice_sent'=>false, 'deadline'=>null, 'days_left'=>null];
    }
    $noticeSent = !empty($statusEntry['payment_reminder_sent_at']);
    $deadline = vestra_business_days_after((int)(strtotime($start) ?: $now), VESTRA_ORDER_PAYMENT_GRACE_DAYS);
    if ($now < $deadline) {
        return ['phase'=>'running', 'start'=>$start, 'notice_sent'=>$noticeSent, 'deadline'=>$deadline,
                'days_left'=>(int)ceil(($deadline - $now) / 86400)];
    }
    return ['phase'=>'overdue', 'start'=>$start, 'notice_sent'=>$noticeSent, 'deadline'=>$deadline, 'days_left'=>0];
}

/**
 * Sends (or resends) the payment-due reminder for one pending, invoiced order, and
 * starts the auto-cancel clock the FIRST time this runs for that order. Single sender
 * for both the daily cron and the operator's one-off letter — see the note above the
 * constant for why that matters.
 *
 * Returns ['ok'=>bool, 'error'=>string, 'phase_before'=>string, 'deadline'=>?int, 'mail_ok'=>bool].
 * 'ok'=false only for 'has_receipt' (caller should not be sending a cancellation threat
 * over a payment we may already have).
 */
function vestra_order_payment_reminder_send(string $ref, array $orderRow, string $invoiceNo, float $total, string $currency): array {
    if (!function_exists('vestra_tpl_order_payment_due')) require_once __DIR__.'/email_templates.php';
    $now = time();
    $all = vestra_read_json('order_statuses.json');
    $entry = $all[$ref] ?? ['status'=>'pending'];
    $g = vestra_order_payment_grace($entry, $now);
    if ($g['phase'] === 'has_receipt') {
        return ['ok'=>false, 'error'=>'has_receipt', 'phase_before'=>$g['phase'], 'deadline'=>null, 'mail_ok'=>false];
    }
    if ($g['phase'] === 'unstamped') {
        $entry['payment_grace_start'] = date('c', $now);
        $all[$ref] = $entry;
        vestra_write_json('order_statuses.json', $all);
        $g = vestra_order_payment_grace($entry, $now);
    }
    $buyerName = trim((string)($orderRow['name'] ?? '')) ?: trim((string)($orderRow['company'] ?? ''));
    $acc = function_exists('auth_find') ? auth_find((string)($orderRow['email'] ?? '')) : null;
    $uploadUrl = 'https://vestrasales.com/buyer?tab=orders&view='.rawurlencode($ref);
    [$subject, $body, $opts] = vestra_tpl_order_payment_due(
        $buyerName, $ref, $invoiceNo, $total, $currency, gmdate('j F Y', (int)$g['deadline']), (bool)$acc, $uploadUrl);
    $ok = !empty($orderRow['email']) && vestra_send_mail((string)$orderRow['email'], $subject, $body, '', '', null, '', $opts);
    if ($ok) {
        $all2 = vestra_read_json('order_statuses.json');
        $all2[$ref] = array_merge($all2[$ref] ?? $entry, ['payment_reminder_sent_at'=>date('c', $now)]);
        vestra_write_json('order_statuses.json', $all2);
    }
    return ['ok'=>true, 'error'=>'', 'phase_before'=>$g['phase'], 'deadline'=>(int)$g['deadline'], 'mail_ok'=>$ok];
}

/**
 * Order summary as a PDF — the buyer's own copy of what they ordered.
 *
 * Deliberately NOT an invoice. The invoice is the seller's demand for payment: it carries
 * an invoice number, the seller's bank details and a due date, and it is issued by hand
 * once stock is confirmed. This is available from the moment the order exists, which is
 * exactly the window where the buyer has nothing on paper and their own colleagues are
 * asking what was ordered. Labelling it "Order summary" and stamping "not an invoice" on
 * it keeps the two from being paid against each other by mistake.
 *
 * Each line carries the model code, because that is what a buyer matches against their
 * own system and what they quote back to us in an e-mail.
 */
function vestra_render_order_pdf(array $orderRow, array $lines, string $statusLabel): string {
    require_once __DIR__.'/pdf.php';
    $pdf = new VestraPdf();
    $left = 50.0; $right = 545.0; $width = $right - $left; $bottom = 70.0;
    $y = VestraPdf::PAGE_H - 60;
    $newPage = function() use (&$y, $pdf) { $pdf->addPage(); $y = VestraPdf::PAGE_H - 60; };
    $need = function(float $h) use (&$y, $bottom, $newPage) { if ($y - $h < $bottom) $newPage(); };
    $ref = (string)($orderRow['ref'] ?? '');

    $pdf->text($left, $y, 20, 'VESTRA', true);
    $pdf->text($left, $y - 16, 8, 'Acerasoft LLC  ·  vestrasales.com', false);
    $pdf->textR($right, $y, 18, 'ORDER SUMMARY', true);
    $pdf->textR($right, $y - 18, 9, 'Order ref:  '.$ref);
    $pdf->textR($right, $y - 30, 9, 'Date:  '.substr((string)($orderRow['timestamp'] ?? ''), 0, 10));
    $pdf->textR($right, $y - 42, 9, 'Status:  '.$statusLabel);
    $y -= 66;
    $pdf->line($left, $y, $right, $y, 1.0, 0.15);
    $y -= 22;

    $pdf->text($left, $y, 10, 'Buyer', true); $y -= 15;
    foreach (array_filter([
        (string)($orderRow['company'] ?? ''),
        (string)($orderRow['name'] ?? ''),
        (string)($orderRow['email'] ?? ''),
        ((string)($orderRow['vat'] ?? '') !== '' ? 'VAT ID: '.$orderRow['vat'] : ''),
        (string)($orderRow['country'] ?? ''),
    ], fn($v) => trim((string)$v) !== '') as $bl) { $pdf->text($left, $y, 9, $bl); $y -= 13; }
    $y -= 10;

    $colSku = $left; $colDesc = $left + 96; $colQty = $right - 168; $colUnit = $right - 96;
    $pdf->rectFill($left - 6, $y - 5, $width + 12, 20, 0.94);
    $pdf->text($colSku, $y, 9, 'Model / SKU', true);
    $pdf->text($colDesc, $y, 9, 'Product', true);
    $pdf->textR($colQty + 34, $y, 9, 'Qty', true);
    $pdf->textR($colUnit + 40, $y, 9, 'Unit', true);
    $pdf->textR($right - 4, $y, 9, 'Line', true);
    $y -= 24;

    $goods = 0.0;
    foreach ($lines as $l) {
        $desc = trim((string)($l['brand'] ?? '').' '.(string)($l['name'] ?? ''));
        $descLines = $pdf->wrap($desc, $colQty - $colDesc - 8, 9);
        $rowH = max(13, count($descLines) * 11) + 8;
        $need($rowH);
        $pdf->text($colSku, $y, 9, (string)($l['sku'] ?? ''));
        foreach ($descLines as $j => $dl) $pdf->text($colDesc, $y - ($j * 11), 9, $dl);
        /* Renk ve beden TEK alt satirda birlesiyor. Ayri bir blok yazsaydim
           satir yuksekligi hesabi (asagidaki `$y -= $rowH + …`) yalnizca BIR
           blok sayiyor, yani ikincisi bir sonraki satirin uzerine binerdi. */
        $sub = [];
        if (!empty($l['colors'])) $sub[] = implode(', ', (array)$l['colors']);
        if (!empty($l['sizes']))  $sub[] = t('Sizes').': '.implode(', ', (array)$l['sizes']);
        if ($sub) {
            foreach ($pdf->wrap(implode(' · ', $sub), $colQty - $colDesc - 8, 8) as $j => $cl)
                $pdf->text($colDesc, $y - (count($descLines) * 11) - ($j * 10) + 1, 8, $cl);
        }
        $pdf->textR($colQty + 34, $y, 9, (string)(int)($l['qty'] ?? 0));
        $pdf->textR($colUnit + 40, $y, 9, eur($l['unit'] ?? 0));
        $pdf->textR($right - 4, $y, 9, eur($l['line'] ?? 0));
        $goods += (float)($l['line'] ?? 0);
        $y -= $rowH + ($sub ? 10 : 0);
    }

    $need(70);
    $y -= 4; $pdf->line($left, $y, $right, $y, 0.7, 0.5); $y -= 18;
    $discount = round((float)($orderRow['discount'] ?? 0), 2);
    $shipping = round((float)($orderRow['shipping'] ?? 0), 2);
    $shipLbl  = trim((string)($orderRow['shipping_label'] ?? '')) ?: 'Shipping';
    $total    = isset($orderRow['total']) ? round((float)$orderRow['total'], 2)
                                          : round(max(0, $goods - $discount) + $shipping, 2);

    /* Whatever the total carries over and above goods less discount has to be named. Freight
       is named from the order's own shipping line; anything still left after that is the
       escrow protection fee, which the buyer also pays on a card order. Unnamed, either one
       just reads as the arithmetic being wrong — and freight labelled "buyer protection fee",
       which is what happened before the shipping line existed, reads worse than that. */
    $rows = [];
    if ($discount > 0) {
        $vc = trim((string)($orderRow['voucher_code'] ?? ''));
        $rows[] = ['Voucher'.($vc !== '' ? ' '.$vc : ''), '-'.eur($discount)];
    }
    if ($shipping > 0) $rows[] = [$shipLbl, eur($shipping)];
    $fee = round($total - ($goods - $discount) - $shipping, 2);
    if ($fee > 0.009) $rows[] = ['Buyer protection fee', eur($fee)];

    if ($rows) {
        $pdf->textR($colUnit, $y, 10, 'Goods total');
        $pdf->textR($right - 4, $y, 10, eur($goods)); $y -= 15;
        foreach ($rows as [$label, $amount]) {
            $pdf->textR($colUnit, $y, 10, $label);
            $pdf->textR($right - 4, $y, 10, $amount); $y -= 15;
        }
        $y += 9; $pdf->line($colUnit - 60, $y, $right, $y, 0.5, 0.35); $y -= 15;
    }
    $pdf->textR($colUnit, $y, 10, 'Total', true);
    $pdf->textR($right - 4, $y, 11, eur($total), true);
    $y -= 30;

    $need(40);
    foreach ($pdf->wrap('This is an order summary for your records — not an invoice and not a demand for payment. '
        .'Your invoice, with the seller\'s bank details, is issued once stock is confirmed and is sent to you separately.', $width, 8) as $fl) {
        $pdf->text($left, $y, 8, $fl); $y -= 11;
    }
    return $pdf->output();
}


/**
 * Neutral order sheet — the same goods as the order summary, laid out to be handed to a
 * third party.
 *
 * Carries the order number, the photos, the model codes and the quantities, and nothing
 * else: no marketplace name, no buyer identity, no prices. A picking list forwarded to a
 * supplier should tell them what to pull off the shelf and reveal neither who is buying
 * nor what anyone is paying. The branded, priced document stays at
 * vestra_render_order_pdf() and is only reachable from the customer's own account.
 */
function vestra_render_order_sheet_pdf(array $orderRow, array $lines): string {
    require_once __DIR__.'/pdf.php';
    $pdf   = new VestraPdf();
    $left  = 50.0; $right = 545.0; $bottom = 50.0;
    $imgW  = 68.0;                    // photo column, square
    $txtX  = $left + $imgW + 14;
    $textW = $right - $txtX - 74;     // leave the right edge for the quantity
    $y     = VestraPdf::PAGE_H - 62;

    $ref  = (string)($orderRow['ref'] ?? '');
    $date = substr((string)($orderRow['timestamp'] ?? ''), 0, 10);

    $header = function () use ($pdf, $left, $right, $ref, $date, &$y) {
        $pdf->text($left, $y, 16, 'ORDER SHEET', true);
        $pdf->textR($right, $y + 2, 11, 'No. '.$ref, true);
        if ($date !== '') $pdf->textR($right, $y - 11, 9, $date);
        $y -= 18;
        $pdf->line($left, $y, $right, $y, 1.0, 0.15);
        $y -= 20;
        $pdf->text($left, $y, 8.5, 'ITEM', true);
        $pdf->textR($right, $y, 8.5, 'QUANTITY', true);
        $y -= 8;
        $pdf->line($left, $y, $right, $y, 0.5, 0.72);
        $y -= 22;
    };
    $header();

    $units = 0;
    foreach ($lines as $l) {
        $qty    = (int)($l['qty'] ?? 0);
        $units += $qty;
        $sku    = trim((string)($l['sku'] ?? ''));
        $brand  = trim((string)($l['brand'] ?? ''));
        $name   = trim((string)($l['name'] ?? ''));
        $cols   = array_values(array_filter(array_map('trim', array_map('strval', (array)($l['colors'] ?? []))), fn($c) => $c !== ''));

        /* Catalogue names frequently already open with the brand and close with the model
           code that the SKU line shows directly above ("BALMAIN BALMAIN Swimsuit —
           BKBU30810"). Harmless in a product page heading, but on a one-item-per-row
           picking sheet it reads as duplication. */
        if ($brand !== '' && stripos($name, $brand) === 0) $brand = '';
        if ($sku !== '') {
            $cut = preg_replace('/\s*[—–-]\s*'.preg_quote($sku, '/').'\s*$/ui', '', $name);
            if (is_string($cut) && $cut !== '') $name = trim($cut);
        }

        $nameLines = $pdf->wrap(trim($brand.' '.$name), $textW, 10, true);
        /* Beden, rengin YANINDA: toplama listesinde "hangi renk" ile "hangi
           beden" ayni satirin iki yarisi. $rowH bu bloktan hesaplandigi icin
           ayri bir liste eklemek satir yuksekligini de bozardi. */
        $szs       = array_values(array_filter(array_map('trim', array_map('strval', (array)($l['sizes'] ?? []))), fn($c) => $c !== ''));
        $subParts  = [];
        if ($cols) $subParts[] = 'Colours: '.implode(', ', $cols);
        if ($szs)  $subParts[] = 'Sizes: '.implode(', ', $szs);
        $colLines  = $subParts ? $pdf->wrap(implode('   ', $subParts), $textW, 8.5) : [];
        $rowH = max($imgW, 14 + count($nameLines) * 12 + count($colLines) * 11) + 14;

        if ($y - $rowH < $bottom) { $pdf->addPage(); $y = VestraPdf::PAGE_H - 62; $header(); }
        $top = $y;

        $jpg = vestra_pdf_thumb((string)($l['image'] ?? ''));
        if ($jpg === '' || !$pdf->imageJpeg($jpg, $left, $top - $imgW, $imgW, $imgW)) {
            $pdf->rectFill($left, $top - $imgW, $imgW, $imgW, 0.95);
            $pdf->textR($left + $imgW / 2 + 14, $top - $imgW / 2 - 3, 7.5, 'no photo');
        }

        $ty  = $top - 11;
        $sku = trim((string)($l['sku'] ?? ''));
        if ($sku !== '') { $pdf->text($txtX, $ty, 8.5, $sku); $ty -= 14; }
        foreach ($nameLines as $nl) { $pdf->text($txtX, $ty, 10, $nl, true); $ty -= 12; }
        foreach ($colLines as $cl)  { $pdf->text($txtX, $ty, 8.5, $cl);      $ty -= 11; }

        $pdf->textR($right, $top - 14, 15, (string)$qty, true);
        $pdf->textR($right, $top - 27, 8, 'pcs');

        $y = $top - $rowH;
        $pdf->line($left, $y + 9, $right, $y + 9, 0.4, 0.84);
    }

    if ($y - 40 < $bottom) { $pdf->addPage(); $y = VestraPdf::PAGE_H - 62; }
    $y -= 4;
    $pdf->line($left, $y, $right, $y, 0.9, 0.25); $y -= 18;
    $n = count($lines);
    $pdf->text($left, $y, 10, $n.' '.($n === 1 ? 'model' : 'models'), true);
    $pdf->textR($right, $y, 13, $units.' pcs total', true);
    return $pdf->output();
}

/**
 * OPERATORUN ELLE KURDUGU SIPARIS (8 Eyl 2026).
 *
 * Neden gerekti: bu depoda siparis yazmanin YALNIZCA iki yolu vardi --
 * alicinin kendi kasasi (order.php) ve kabul edilmis teklif
 * (vestra_offer_order_ensure). Operator telefonda/e-postada anlasilan bir
 * satisi kayda gecirmek istediginde hicbir yol yoktu; "siparis ve fatura yap"
 * bir dugmeye basmak degil, yazilmamis bir kod parcasiydi.
 *
 * Satir bicimi vestra_offer_order_ensure ile BIREBIR AYNI: ayni kolonlar, ayni
 * items dizgesi, ayni kargo kolonu, ayni FX damgasi. Ikinci bir bicim
 * uydurmak, paneli ve fatura yolunu iki ayri sekli okumak zorunda birakirdi.
 *
 * BEDEN items'a YAZILMAZ, nota yazilir. items kolonunu
 * vestra_order_lines() "Nx SKU @fiyat" olarak ayristiriyor; araya beden
 * sokmak o ayristiriciyi bozardi. Renklerin nota yazilmasiyla ayni desen
 * (bkz. vestra_order_notes_colors).
 *
 * RENK de nota yaziliyor artik (17 Eyl 2026). Kasa `Colours — …` parcasini
 * bastan beri yaziyor ve vestra_order_lines() onu okuyup siparis tablosuna,
 * siparis PDF'ine ve faturanin toplama listesine basiyor; elle kurulan siparis
 * ise rengi hicbir yere yazamiyordu. Bir lot "siyah" mi "bordo" mu, satisin
 * kendi kaydinda durmak zorunda.
 *
 * @param array $acc    Alicinin HESABI (accounts.json satiri). Adres/VAT oradan.
 * @param array $lines  [['sku'=>, 'size'=>, 'colour'=>, 'qty'=>, 'unit'=>], ...]
 *                      'size' ve 'colour' virgullu liste de olabilir ("S×5, M×15").
 * @return array ['ok'=>true,'ref'=>...] ya da ['error'=>gerekce]
 */
function vestra_order_create_manual(array $acc, array $lines, ?float $shipping = null, string $notesExtra = ''): array {
    if (!$acc || trim((string)($acc['email'] ?? '')) === '') return ['error' => 'Alici hesabi yok ya da e-postasi bos.'];
    if (!$lines) return ['error' => 'Kalem yok.'];

    /* Ayni SKU birden fazla bedende gelebiliyor (M/L/XL). items kolonunda SKU
       basina TEK satir olur, adetler toplanir; beden dokumu nota gider. */
    $bySku = [];
    $sizes = [];
    $colours = [];
    foreach ($lines as $l) {
        $sku  = trim((string)($l['sku'] ?? ''));
        $qty  = max(1, (int)($l['qty'] ?? 1));
        $unit = round((float)($l['unit'] ?? 0), 2);
        $size = trim((string)($l['size'] ?? ''));
        $col  = trim((string)($l['colour'] ?? $l['color'] ?? ''));
        if ($sku === '' || $unit <= 0) return ['error' => "Gecersiz kalem: sku='{$sku}' unit={$unit}"];
        if (isset($bySku[$sku]) && abs($bySku[$sku]['unit'] - $unit) > 0.001) {
            return ['error' => "Ayni SKU iki farkli birim fiyatla geldi: {$sku}"];
        }
        $bySku[$sku] = ['qty' => ($bySku[$sku]['qty'] ?? 0) + $qty, 'unit' => $unit];
        /* Beden zaten "S×5, M×15" gibi bir DOKUM olarak gelebiliyor (tam karton
           satisinda oyle geliyor); o hali adetle bir daha carpmak "S×5, M×15×50"
           gibi bir dizge uretirdi. Ayrisma kurali: icinde '×' varsa doküm,
           yoksa tek beden ve adetle yaziliyor. */
        if ($size !== '') foreach (array_filter(array_map('trim', explode(',', $size))) as $s) {
            $sizes[$sku][] = (mb_strpos($s, '×') !== false || mb_strpos($s, 'x') !== false) ? $s : $s.'×'.$qty;
        }
        if ($col !== '') foreach (array_filter(array_map('trim', explode(',', $col))) as $c) $colours[$sku][] = $c;
    }
    foreach ($sizes   as $k => $v) $sizes[$k]   = array_values(array_unique($v));
    foreach ($colours as $k => $v) $colours[$k] = array_values(array_unique($v));

    $goods = 0.0; $items = [];
    foreach ($bySku as $sku => $v) {
        $goods  += round($v['unit'] * $v['qty'], 2);
        $items[] = $v['qty'].'x '.$sku.' @'.number_format($v['unit'], 2, '.', '');
    }
    $goods    = round($goods, 2);
    /* NAVLUN: rakam verilmediyse (null) bölge tarifesi. Açıkça 0.0 verilirse 0
       kalır — "navlun yok" ile "navlunu sen hesapla" iki ayrı talimat ve
       varsayılanı 0.0 bırakmak elle yazılan her siparişi navlunsuz doğururdu.
       Tanınmayan ülkede tarife null döner ve navlun yine 0 olur (operatör elle
       yazar), yani tahmin edilmiş bir rakam hiçbir zaman kayda girmiyor. */
    $shipLabel = '';
    if ($shipping === null) {
        $sched = vestra_shipping_schedule(
            array_map(fn($k, $v) => ['sku' => $k, 'qty' => $v['qty']], array_keys($bySku), $bySku),
            (string)($acc['country'] ?? ''));
        $shipping  = $sched ? (float)$sched['amount'] : 0.0;
        $shipLabel = $sched ? (string)$sched['label'] : '';
    }
    $shipping = round(max(0.0, (float)$shipping), 2);

    /* Ref CAKISMASIZ olmali: ayni ref'e ikinci satir, paneli ve faturayi
       hangi satirin gecerli oldugunu bilemez hale getirir. */
    $existing = [];
    foreach (vestra_read_csv('orders.csv') as $r) $existing[(string)($r['ref'] ?? '')] = true;
    $ref = '';
    for ($i = 0; $i < 20 && $ref === ''; $i++) {
        $try = 'VES-'.strtoupper(bin2hex(random_bytes(4)));
        if (!isset($existing[$try])) $ref = $try;
    }
    if ($ref === '') return ['error' => 'Benzersiz referans uretilemedi.'];

    /* PARCA BICIMI KASANIN BICIMIYLE AYNI OLMAK ZORUNDA -- ve degildi.
       Eski satir her SKU'yu kendi NOKTASIYLA kapatiyordu
       ("Sizes — M3600: S×5. M7535: S×5.") ve degerleri '·' ile ayiriyordu.
       vestra_order_notes_map() ise ILK noktaya kadar okuyor ve degerleri ','
       ile boluyor: yani IKI SKU'LU her elle sipariste ikinci SKU'nun dokumu
       SESSIZCE kayboluyordu (olculdu: ikinci satir haritaya hic girmiyor,
       ustelik kalintisi serbest metinde kalip alicinin siparis sayfasina ve
       operator paneline OLDUGU GIBI basiliyordu). Kasanin bicimi:
       segmentler ' | ' ile, degerler ', ' ile, parca TEK noktayla biter. */
    $frag = function (string $label, array $map): string {
        if (!$map) return '';
        $segs = [];
        foreach ($map as $sku => $vals) {
            /* Deger icinde nokta olursa parca kendi sonunu erken bildirir ve
               gerisi serbest metne dokulur -- kasada renk/beden adlari nokta
               tasimiyor, burada da tasimamali. */
            $clean = array_filter(array_map(fn($v) => trim(str_replace(['.', '|'], '', (string)$v)), $vals), fn($v) => $v !== '');
            if ($clean) $segs[] = $sku.': '.implode(', ', $clean);
        }
        return $segs ? ' '.$label.' — '.implode(' | ', $segs).'.' : '';
    };
    $notes = 'Payment: Bank transfer.'
           . ($notesExtra !== '' ? ' '.trim($notesExtra) : '')
           . $frag('Colours', $colours)
           . $frag('Sizes', $sizes)
           . ($shipping > 0 ? ' Shipping EUR '.number_format($shipping, 2, '.', '').'.' : '');

    $dir = dirname(__DIR__).'/data'; if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $file = $dir.'/orders.csv'; $new = !file_exists($file);
    $head = ['timestamp','ref','company','vat','name','email','country','phone','items','subtotal',
             'commission','payout','total','notes','consent','terms_version','voucher_code','discount',
             'shipping','shipping_label'];
    if (!$new && function_exists('vestra_csv_ensure_header')) vestra_csv_ensure_header('orders.csv', $head);
    $fh = @fopen($file, 'a');
    if (!$fh) return ['error' => 'orders.csv acilamadi.'];
    if ($new) fputcsv($fh, $head, ',', '"', '\\');
    fputcsv($fh, [date('c'), $ref,
        (string)($acc['company'] ?? ''), (string)($acc['vat_id'] ?? ''),
        (string)($acc['name'] ?? ''), (string)($acc['email'] ?? ''),
        (string)($acc['country'] ?? ''), (string)($acc['phone'] ?? ''),
        implode(' | ', $items), number_format($goods, 2, '.', ''), '0.00',
        number_format($goods, 2, '.', ''), number_format($goods + $shipping, 2, '.', ''),
        $notes, 'operator', defined('VESTRA_TERMS_VERSION') ? VESTRA_TERMS_VERSION : '', '', '',
        $shipping > 0 ? number_format($shipping, 2, '.', '') : '',
        $shipping > 0 ? ($shipLabel !== '' ? $shipLabel : 'Shipping') : ''], ',', '"', '\\');
    fclose($fh);

    /* USD damgasi siparis anindaki kurla -- order.php ve teklif yoluyla ayni. */
    require_once __DIR__.'/fx_orders.php';
    vestra_order_fx_stamp($ref, date('c'), true);

    /* YAZMA GERI OKUNUYOR. Bu depoda "kaydettim" diyen ama kaydetmemis bir
       satir daha once cikti; para iceren bir yazmada bu kabul edilemez. */
    $back = null;
    foreach (vestra_read_csv('orders.csv') as $r) if (($r['ref'] ?? '') === $ref) { $back = $r; break; }
    if (!$back) return ['error' => 'Satir yazildi ama geri okunamadi.'];
    $wantTotal = number_format($goods + $shipping, 2, '.', '');
    if (trim((string)($back['total'] ?? '')) !== $wantTotal) {
        return ['error' => "Geri okuma tutmadi: total='{$back['total']}' beklenen '{$wantTotal}'"];
    }
    return ['ok' => true, 'ref' => $ref, 'goods' => $goods, 'shipping' => $shipping,
            'total' => round($goods + $shipping, 2), 'items' => implode(' | ', $items)];
}
