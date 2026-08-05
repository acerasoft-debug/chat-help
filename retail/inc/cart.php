<?php
/**
 * Sepet
 * -----
 * Oturumda yalnızca KİMLİK tutulur (ürün id, beden, adet, los id) — fiyat ASLA.
 * Fiyat her okumada katalogdan/Vault planından yeniden hesaplanır, böylece
 * istemci tarafından manipüle edilebilecek bir tutar hiç var olmaz.
 */

declare(strict_types=1);

require_once __DIR__ . '/boot.php';
require_once __DIR__ . '/catalog.php';
require_once __DIR__ . '/vault.php';
require_once __DIR__ . '/accounts.php';

function vr_cart_key(string $pid, string $size, string $lot = ''): string
{
    return $pid . '|' . strtoupper($size) . '|' . $lot;
}

function vr_cart_raw(): array
{
    vr_session_start();
    $c = $_SESSION['vr_cart'] ?? [];
    return is_array($c) ? $c : [];
}

function vr_cart_save(array $c): void
{
    vr_session_start();
    $_SESSION['vr_cart'] = $c;
}

/** Sepette kaç parça var (üst çubuktaki sayaç). */
function vr_cart_count(): int
{
    $n = 0;
    foreach (vr_cart_raw() as $it) $n += (int)($it['qty'] ?? 0);
    return $n;
}

/**
 * Sepete ekle. Dönüş: [ok, mesaj_anahtari]
 * $lot boş değilse Vault losu — eklerken rezerve edilir ve fiyat sabitlenir.
 */
function vr_cart_add(string $pid, string $size, int $qty = 1, string $lot = ''): array
{
    $p = vr_product($pid);
    if ($p === null) return [false, 'cart_gone'];

    $size = strtoupper(trim($size)) ?: 'ONE';
    $qty  = max(1, min(10, $qty));

    if ($lot !== '') {
        // Vault: her los tek parça, adet daima 1.
        $v = vr_vault_find($lot);
        if ($v === null || $v['product']['id'] !== $pid) return [false, 'vault_gone'];
        if ($v['sold'])                                  return [false, 'vault_gone'];
        if ($v['members_only'] && !vr_is_member())       return [false, 'vault_early'];

        [$ok, $err] = vr_vault_reserve($lot, session_id());
        if (!$ok) return [false, $err ?: 'vault_gone'];
        $qty = 1;
    } else {
        $avail = vr_size_qty($p, $size);
        if ($avail < 1) return [false, 'sold_out'];
        $qty = min($qty, $avail);
    }

    $c   = vr_cart_raw();
    $key = vr_cart_key($pid, $size, $lot);

    if (isset($c[$key])) {
        $newQty = (int)$c[$key]['qty'] + $qty;
        $c[$key]['qty'] = $lot !== '' ? 1 : min($newQty, max(1, vr_size_qty($p, $size)));
    } else {
        $c[$key] = ['pid' => $pid, 'size' => $size, 'qty' => $qty, 'lot' => $lot, 'added_at' => time()];
    }

    vr_cart_save($c);
    return [true, 'added_to_cart'];
}

function vr_cart_remove(string $key): void
{
    $c = vr_cart_raw();
    if (isset($c[$key])) {
        $lot = (string)($c[$key]['lot'] ?? '');
        if ($lot !== '') vr_vault_release($lot, session_id());
        unset($c[$key]);
        vr_cart_save($c);
    }
}

function vr_cart_set_qty(string $key, int $qty): void
{
    $c = vr_cart_raw();
    if (!isset($c[$key])) return;

    if ($qty < 1) { vr_cart_remove($key); return; }
    if ((string)($c[$key]['lot'] ?? '') !== '') return;      // los adedi sabit 1

    $p = vr_product((string)$c[$key]['pid']);
    $max = $p ? max(1, vr_size_qty($p, (string)$c[$key]['size'])) : 1;
    $c[$key]['qty'] = min($qty, $max, 10);
    vr_cart_save($c);
}

function vr_cart_clear(bool $releaseLots = true): void
{
    if ($releaseLots) {
        foreach (vr_cart_raw() as $it) {
            $lot = (string)($it['lot'] ?? '');
            if ($lot !== '') vr_vault_release($lot, session_id());
        }
    }
    vr_cart_save([]);
}

/**
 * Sepeti doğrulayıp satırları döndür.
 * Artık satılamayan satırlar sessizce DÜŞÜRÜLÜR ve nedeni mesaj olarak döner —
 * kullanıcı kasada sürprizle karşılaşmasın.
 *
 * Dönüş: ['lines'=>[...], 'messages'=>[...]]
 */
function vr_cart_lines(): array
{
    $c        = vr_cart_raw();
    $lines    = [];
    $messages = [];
    $dirty    = false;

    foreach ($c as $key => $it) {
        $pid  = (string)($it['pid'] ?? '');
        $size = (string)($it['size'] ?? 'ONE');
        $lot  = (string)($it['lot'] ?? '');
        $qty  = max(1, (int)($it['qty'] ?? 1));

        $p = vr_product($pid);
        if ($p === null) {
            unset($c[$key]); $dirty = true;
            $messages[] = t('cart_gone', ['name' => $pid]);
            continue;
        }

        if ($lot !== '') {
            $unit = vr_vault_price_for($lot, session_id());
            if ($unit === null) {
                unset($c[$key]); $dirty = true;
                $messages[] = t('cart_gone', ['name' => $p['brand'] . ' ' . $p['name']]);
                continue;
            }
            $qty = 1;
        } else {
            $avail = vr_size_qty($p, $size);
            if ($avail < 1) {
                unset($c[$key]); $dirty = true;
                $messages[] = t('cart_gone', ['name' => $p['brand'] . ' ' . $p['name']]);
                continue;
            }
            if ($qty > $avail) {
                $qty = $avail;
                $c[$key]['qty'] = $qty; $dirty = true;
                $messages[] = t('cart_qty_cut', ['n' => $avail, 'name' => $p['brand'] . ' ' . $p['name']]);
            }
            $unit = (int)$p['price_cents'];
        }

        $seller = vr_product_seller($p);

        $lines[] = [
            'key'         => $key,
            'pid'         => $pid,
            'lot'         => $lot,
            'vault'       => $lot !== '',
            'size'        => $size,
            'qty'         => $qty,
            'unit_cents'  => (int)$unit,
            'total_cents' => (int)$unit * $qty,
            'brand'       => $p['brand'],
            'name'        => vr_card_name($p),
            'sku'         => $p['sku'],
            'image'       => vr_product_image($p),
            'url'         => vr_product_url($p),
            'seller_uid'  => $seller['id'],
            'seller_type' => $seller['type'],
            'seller_name' => $seller['name'],
            'seller_stripe' => $seller['stripe'],
            'seller_ready' => $seller['ready'],
        ];
    }

    if ($dirty) vr_cart_save($c);
    return ['lines' => $lines, 'messages' => $messages];
}

// ------------------------------------------------------------------- kargo
function vr_shipping_zone(string $country): string
{
    $c = strtoupper(trim($country));
    if ($c === '') return 'de';
    if (in_array($c, (array)vr_config('shipping_countries_de', ['DE']), true)) return 'de';
    if (in_array($c, (array)vr_config('shipping_countries_eu', []), true))     return 'eu';
    if (in_array($c, (array)vr_config('shipping_countries_ch', []), true))     return 'ch';
    return 'world';
}

/** Bölge + ara toplam → kargo tutarı ve etiketi. */
function vr_shipping_quote(string $country, int $subtotalCents): array
{
    $zone  = vr_shipping_zone($country);
    $table = (array)vr_config('shipping');
    $row   = $table[$zone] ?? ['cents' => 0, 'free_over' => 0, 'days' => '', 'label_key' => ''];

    $free  = (int)($row['free_over'] ?? 0) > 0 && $subtotalCents >= (int)$row['free_over'];
    $cents = $free ? 0 : (int)($row['cents'] ?? 0);

    return [
        'zone'      => $zone,
        'cents'     => $cents,
        'free'      => $free,
        'free_over' => (int)($row['free_over'] ?? 0),
        'days'      => (string)($row['days'] ?? ''),
        'label'     => vr_zone_label($zone),
    ];
}

function vr_zone_label(string $zone): string
{
    return match ($zone) {
        'de'    => 'Deutschland',
        'eu'    => 'EU',
        'ch'    => 'CH / NO / GB',
        default => 'International',
    };
}

/** Teslimat için izin verilen ülkeler (kasa açılır listesi). */
function vr_shipping_countries(): array
{
    $out = [];
    foreach (['shipping_countries_de', 'shipping_countries_eu', 'shipping_countries_ch'] as $k) {
        foreach ((array)vr_config($k, []) as $c) $out[] = strtoupper((string)$c);
    }
    // Kalan dünya için elle seçilmiş, gerçekten gönderdiğimiz pazarlar.
    foreach (['US', 'CA', 'AE', 'SA', 'QA', 'KW', 'TR', 'JP', 'KR', 'SG', 'AU', 'NZ', 'IL', 'HK'] as $c) $out[] = $c;

    $out = array_values(array_unique($out));
    sort($out);
    return $out;
}

/** Sepet toplamları. $country boşsa kargo "kasada hesaplanır" olarak döner. */
function vr_cart_totals(?string $country = null): array
{
    $data     = vr_cart_lines();
    $subtotal = 0;
    foreach ($data['lines'] as $ln) $subtotal += (int)$ln['total_cents'];

    $ship = $country !== null && $country !== ''
        ? vr_shipping_quote($country, $subtotal)
        : null;

    $shipCents = $ship['cents'] ?? 0;
    $total     = $subtotal + $shipCents;

    // Satıcı sayısı — çok satıcılı sepette kullanıcıya ayrı gönderi uyarısı.
    $sellers = [];
    foreach ($data['lines'] as $ln) $sellers[$ln['seller_uid'] ?: 'vestra'] = true;

    return [
        'lines'      => $data['lines'],
        'messages'   => $data['messages'],
        'subtotal'   => $subtotal,
        'shipping'   => $ship,
        'ship_cents' => $shipCents,
        'total'      => $total,
        'vat'        => vr_config('prices_include_vat') ? vr_vat_part($total) : 0,
        'sellers'    => count($sellers),
        'has_private' => (bool)array_filter($data['lines'], fn($l) => $l['seller_type'] === 'private'),
        'count'      => array_sum(array_map(fn($l) => (int)$l['qty'], $data['lines'])),
    ];
}
