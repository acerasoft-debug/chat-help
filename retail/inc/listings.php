<?php
/**
 * Perakende satıcı ürünleri
 * -------------------------
 * Yalnızca data/retail-listings.json'a yazar. Canlı toptan kataloğu
 * (listings.json) bu dosyadan asla etkilenmez.
 *
 * Yeni/değişen her satır 'review' durumuna düşer: pazaryerinde replika ve
 * belgesiz parça sorumluluğu bize ait, o yüzden insan onayı olmadan hiçbir
 * satır vitrine çıkmıyor. Onay tools/moderate.php ile veya doğrudan
 * status='approved' yazılarak veriliyor.
 */

declare(strict_types=1);

require_once __DIR__ . '/boot.php';
require_once __DIR__ . '/catalog.php';
require_once __DIR__ . '/accounts.php';
require_once __DIR__ . '/uploads.php';

const VR_LISTINGS_FILE = 'retail-listings.json';

function vr_listings_all(): array
{
    $d = vr_store_read(VR_LISTINGS_FILE, []);
    return is_array($d) ? $d : [];
}

function vr_seller_listings(string $sellerId): array
{
    $out = [];
    foreach (vr_listings_all() as $row) {
        if (is_array($row) && (string)($row['seller_uid'] ?? '') === $sellerId) $out[] = $row;
    }
    usort($out, fn($a, $b) => (int)($b['updated_at'] ?? 0) <=> (int)($a['updated_at'] ?? 0));
    return $out;
}

function vr_listing_get(string $id, ?string $sellerId = null): ?array
{
    foreach (vr_listings_all() as $row) {
        if (!is_array($row) || (string)($row['id'] ?? '') !== $id) continue;
        if ($sellerId !== null && (string)($row['seller_uid'] ?? '') !== $sellerId) return null;
        return $row;
    }
    return null;
}

/** "M×2, L×1" / "M:2 L:1" / satır satır → ['M'=>2,'L'=>1] */
function vr_parse_size_input(string $raw): array
{
    $out = [];
    if (trim($raw) === '') return $out;

    if (preg_match_all('/([0-9]{2,3}|[A-Za-z]{1,4})\s*[×x\*:=]\s*([0-9]{1,3})/u', $raw, $m, PREG_SET_ORDER)) {
        foreach ($m as $hit) {
            $label = strtoupper(trim($hit[1]));
            $qty   = (int)$hit[2];
            if ($label === '' || $qty <= 0) continue;
            $out[$label] = ($out[$label] ?? 0) + min(999, $qty);
        }
        return $out;
    }

    // Adet verilmemişse ("S, M, L") her bedenden 1 varsayıyoruz.
    foreach (preg_split('/[,\n;\/·]+/', $raw) ?: [] as $tok) {
        $label = strtoupper(trim($tok));
        if ($label !== '' && mb_strlen($label) <= 4) $out[$label] = 1;
    }
    return $out;
}

/**
 * Kaydet (yeni veya güncelleme).
 * Dönüş: [ok, listing_veya_hata_dizisi]
 */
function vr_listing_save(array $seller, array $in, array $files = [], ?string $editId = null): array
{
    $errors = [];

    $brand = trim((string)($in['brand'] ?? ''));
    $name  = trim((string)($in['name'] ?? ''));
    $cat   = trim((string)($in['cat'] ?? ''));
    $sku   = trim((string)($in['sku'] ?? ''));
    $desc  = trim((string)($in['desc'] ?? ''));
    $cond  = (string)($in['condition'] ?? 'new') === 'used' ? 'used' : 'new';

    // Fiyat: "129,90" ve "129.90" ikisi de kabul.
    $priceRaw = str_replace([' ', "\u{202F}", '€'], '', (string)($in['price'] ?? ''));
    $priceRaw = str_replace(',', '.', $priceRaw);
    $cents    = (int)round(((float)$priceRaw) * 100);

    $sizes = vr_parse_size_input((string)($in['sizes'] ?? ''));

    if ($brand === '') $errors['brand'] = t('required_field');
    if ($name === '')  $errors['name']  = t('required_field');
    if ($cat === '')   $errors['cat']   = t('required_field');
    if ($cents < (int)vr_config('retail_min_cents', 2900)) {
        $errors['price'] = t('price_invalid', ['min' => vr_money((int)vr_config('retail_min_cents', 2900))]);
    }
    if (!$sizes) $errors['sizes'] = t('required_field');

    // ---- görseller: yeni yüklenenler + zaten kayıtlı olanlar
    $existing = $editId !== null ? (array)(vr_listing_get($editId, (string)$seller['id'])['images'] ?? []) : [];
    $keep     = [];
    foreach ((array)($in['keep_images'] ?? []) as $pathRaw) {
        $path = (string)$pathRaw;
        if (in_array($path, $existing, true)) $keep[] = $path;
    }

    $up = $files ? vr_upload_images($files, (string)$seller['id']) : ['paths' => [], 'errors' => []];
    foreach ($up['errors'] as $e) $errors['images'] = $e;

    $images = array_values(array_unique(array_merge($keep, $up['paths'])));
    if (!$images) $errors['images'] = t('required_field');

    if ($errors) return [false, $errors];

    $now = time();
    $row = [
        'id'          => $editId ?? ('rl-' . substr(sha1((string)$seller['id'] . '|' . $sku . '|' . microtime(true)), 0, 14)),
        'seller_uid'  => (string)$seller['id'],
        'seller_type' => (string)($seller['seller_type'] ?? 'private'),
        'brand'       => mb_substr($brand, 0, 60),
        'name'        => mb_substr($name, 0, 160),
        'cat'         => mb_substr($cat, 0, 60),
        'sku'         => mb_substr($sku, 0, 60),
        'retail_price' => round($cents / 100, 2),
        'size_stock'  => $sizes,
        'images'      => array_slice($images, 0, VR_UPLOAD_MAX_FILES),
        'desc'        => mb_substr($desc, 0, 2000),
        'condition'   => $cond,
        // İncelemeye düşer. Satıcı Stripe'ta hazır değilse zaten satılamaz,
        // ama görünürlüğü de onaya bağlı tutuyoruz.
        'status'      => 'review',
        'updated_at'  => $now,
    ];

    $ok = false;
    vr_store_mutate(VR_LISTINGS_FILE, static function ($rows) use ($row, $editId, $now, &$ok) {
        if (!is_array($rows)) $rows = [];

        if ($editId !== null) {
            foreach ($rows as $i => $r) {
                if ((string)($r['id'] ?? '') !== $editId) continue;
                if ((string)($r['seller_uid'] ?? '') !== $row['seller_uid']) return null;  // başkasının satırı
                $rows[$i] = array_merge($r, $row);
                $ok = true;
                return $rows;
            }
            return null;
        }

        $row['created_at'] = $now;
        $rows[] = $row;
        $ok = true;
        return $rows;
    });

    return $ok ? [true, $row] : [false, ['general' => t('required_field')]];
}

/** Durum değiştir (satıcı: yalnızca duraklat/sürdür). */
function vr_listing_set_status(string $id, string $sellerId, string $status): bool
{
    if (!in_array($status, ['paused', 'review'], true)) return false;

    $ok = false;
    vr_store_mutate(VR_LISTINGS_FILE, static function ($rows) use ($id, $sellerId, $status, &$ok) {
        if (!is_array($rows)) return null;
        foreach ($rows as $i => $r) {
            if ((string)($r['id'] ?? '') !== $id) continue;
            if ((string)($r['seller_uid'] ?? '') !== $sellerId) return null;

            // Onaylanmış bir satır duraklatılabilir; duraklatılmış satır
            // tekrar açılırken YENİDEN incelemeye girmez (zaten onaylıydı).
            if ($status === 'review' && (string)($r['status'] ?? '') === 'paused') {
                $rows[$i]['status'] = (string)($r['approved_once'] ?? '') !== '' ? 'approved' : 'review';
            } else {
                $rows[$i]['status'] = $status;
            }
            $rows[$i]['updated_at'] = time();
            $ok = true;
            return $rows;
        }
        return null;
    });
    return $ok;
}

/** Moderasyon (araç/yönetici): onayla veya reddet. */
function vr_listing_moderate(string $id, bool $approve, string $note = ''): bool
{
    $ok = false;
    vr_store_mutate(VR_LISTINGS_FILE, static function ($rows) use ($id, $approve, $note, &$ok) {
        if (!is_array($rows)) return null;
        foreach ($rows as $i => $r) {
            if ((string)($r['id'] ?? '') !== $id) continue;
            $rows[$i]['status'] = $approve ? 'approved' : 'rejected';
            if ($approve) $rows[$i]['approved_once'] = (string)time();
            if ($note !== '') $rows[$i]['moderation_note'] = mb_substr($note, 0, 500);
            $rows[$i]['moderated_at'] = time();
            $ok = true;
            return $rows;
        }
        return null;
    });
    return $ok;
}

/** Durum → görsel etiket. */
function vr_listing_status_pill(string $status): string
{
    [$cls, $key] = match ($status) {
        'approved', 'live', 'active' => ['pill--live', 'st_live'],
        'paused'                     => ['', 'st_paused'],
        'rejected'                   => ['pill--out', 'st_rejected'],
        default                      => ['pill--review', 'st_review'],
    };
    return '<span class="pill ' . $cls . '">' . te($key) . '</span>';
}

/** Satıcının kataloğa yansıyan satırları (kaç tanesi gerçekten canlı?). */
function vr_seller_stats(string $sellerId): array
{
    $rows = vr_seller_listings($sellerId);
    $live = 0;
    foreach ($rows as $r) {
        if (in_array((string)($r['status'] ?? ''), ['approved', 'live', 'active'], true)) $live++;
    }
    return ['total' => count($rows), 'live' => $live];
}
