<?php
/**
 * VAULT — Premium Outlet motoru
 * =============================
 * Her los tek bir stok kaleminden oluşur ve fiyatı YAYINLANMIŞ bir plana göre
 * kademe kademe düşer (Hollanda usulü açık eksiltme). İlk alan losu kapatır.
 *
 * Tasarım kararları — ve nedenleri:
 *
 *  • Fiyat SUNUCUDA plandan hesaplanır, asla istemciden alınmaz ve asla
 *    ziyaretçiye göre değişmez. Tarayıcıdaki geri sayım yalnızca bir görüntü;
 *    sepete eklerken de, ödeme oluşturulurken de fiyat yeniden hesaplanır.
 *  • Plan los açılırken yazılır (open/floor/steps/step_hours/start_at) ve bir
 *    daha değişmez. "Sahte indirim" tam olarak burada olur: sonradan tabanı
 *    yükseltmek. Bu yüzden vr_vault_open() dışında yazan yol yok.
 *  • PAngV §11: bir indirim duyurulduğunda son 30 günün en düşük fiyatı
 *    belirtilmek zorunda. Plan monoton azaldığı için "şu anki kademe öncesi
 *    geçerli olan fiyat" bu koşulun tam karşılığıdır — vr_vault_reference()
 *    bunu döndürür ve 1. kademede (henüz indirim YOK) null verir; o durumda
 *    hiçbir yüzde/indirim iddiası basılmaz.
 *  • Sepete eklenen los REZERVE edilir: ödeme sırasında fiyat kaymaz, aynı
 *    losu iki kişi ödeyemez. Rezervasyon süresi dolarsa los geri açılır.
 */

declare(strict_types=1);

require_once __DIR__ . '/boot.php';
require_once __DIR__ . '/catalog.php';

const VR_VAULT_FILE      = 'retail-vault.json';
const VR_RESERVE_MINUTES = 20;

/** Ham los listesi. */
function vr_vault_all(): array
{
    $d = vr_store_read(VR_VAULT_FILE, ['lots' => []]);
    $lots = is_array($d['lots'] ?? null) ? $d['lots'] : [];

    // Tohum: hiç los yoksa ve demo katalog açıksa örnek plan dosyasını kullan.
    if (!$lots && vr_config('demo_catalog')) {
        $seed = json_decode((string)@file_get_contents(VR_ROOT . '/data/seed/demo-vault.json'), true);
        if (is_array($seed['lots'] ?? null)) $lots = $seed['lots'];
    }
    if (!is_array($lots)) return [];

    // Tohum losları sabit zaman damgası taşımaz — taşısaydı demo bir hafta
    // sonra "hepsi tabanda" görünürdü. start_offset_hours göreceli verilir ve
    // burada gerçek zamana çevrilir (negatif = bu kadar saat önce açıldı).
    $now = time();
    foreach ($lots as $i => $lot) {
        if (!is_array($lot)) { unset($lots[$i]); continue; }
        if (empty($lot['start_at']) && isset($lot['start_offset_hours'])) {
            $lots[$i]['start_at'] = $now + (int)round((float)$lot['start_offset_hours'] * 3600);
        }
    }
    return array_values($lots);
}

/**
 * Losun o andaki durumu — fiyat, kademe, sonraki senkron noktası.
 * Dönüş: null (plan bozuk) veya durum dizisi.
 */
function vr_vault_state(array $lot, ?int $at = null): ?array
{
    $at    = $at ?? time();
    $open  = (int)($lot['open_cents'] ?? 0);
    $floor = (int)($lot['floor_cents'] ?? 0);
    $steps = (int)($lot['steps'] ?? vr_config('vault_steps', 6));
    $hours = (float)($lot['step_hours'] ?? vr_config('vault_step_hours', 24));
    $start = (int)($lot['start_at'] ?? 0);

    if ($open <= 0 || $floor <= 0 || $floor > $open || $steps < 1 || $hours <= 0 || $start <= 0) {
        return null;                                   // bozuk plan: los gizlenir
    }

    $stepSec = (int)round($hours * 3600);
    $elapsed = $at - $start;

    if ($elapsed < 0) {
        // Henüz açılmadı.
        return [
            'phase'      => 'scheduled',
            'cents'      => $open,
            'step'       => 0,
            'steps'      => $steps,
            'open_cents' => $open,
            'floor_cents' => $floor,
            'next_at'    => $start,
            'next_cents' => $open,
            'reference'  => null,
            'at_floor'   => false,
            'opens_in'   => $start - $at,
        ];
    }

    $step = (int)floor($elapsed / $stepSec);
    $step = min($step, $steps);                        // son kademede sabitlenir

    // Kademe 0 = açılış fiyatı, kademe $steps = taban. Aradaki her kademe eşit
    // aralıklı; kuruş artığı son kademeye değil, her kademede yuvarlanarak
    // dağıtılır ki gösterilen fiyat her zaman planla birebir aynı çıksın.
    $priceAt = static function (int $s) use ($open, $floor, $steps): int {
        if ($s <= 0) return $open;
        if ($s >= $steps) return $floor;
        return (int)round($open - ($open - $floor) * ($s / $steps));
    };

    $cents    = $priceAt($step);
    $atFloor  = $step >= $steps;
    $nextAt   = $atFloor ? null : $start + ($step + 1) * $stepSec;
    $nextCent = $atFloor ? null : $priceAt($step + 1);

    // Son 30 günün en düşük fiyatı = bu indirimden HEMEN ÖNCE geçerli olan
    // fiyat (plan monoton azaldığı için). 1. kademede indirim yok → null.
    $reference = $step >= 1 ? $priceAt($step - 1) : null;
    // Önceki kademe 30 günlük pencerenin dışında kaldıysa referans iddiası
    // yapmıyoruz (uzun süredir tabanda duran loslar).
    if ($reference !== null && ($at - ($start + $step * $stepSec)) > 30 * 86400) {
        $reference = null;
    }

    return [
        'phase'       => 'live',
        'cents'       => $cents,
        'step'        => $step,
        'steps'       => $steps,
        'open_cents'  => $open,
        'floor_cents' => $floor,
        'next_at'     => $nextAt,
        'next_cents'  => $nextCent,
        'reference'   => $reference,
        'at_floor'    => $atFloor,
        'opens_in'    => 0,
    ];
}

/** Los + ürün + durum birleşimi; vitrinde kullanılan tek biçim. */
function vr_vault_lot(array $lot, ?int $at = null): ?array
{
    $state = vr_vault_state($lot, $at);
    if ($state === null) return null;

    $p = vr_product((string)($lot['product_id'] ?? ''));
    if ($p === null) return null;

    $now      = $at ?? time();
    $status   = (string)($lot['status'] ?? 'open');
    $reserved = (int)($lot['reserved_until'] ?? 0) > $now;

    $pct = $state['open_cents'] > 0
        ? (int)round(100 - ($state['cents'] * 100 / $state['open_cents']))
        : 0;

    return [
        'lot_id'      => (string)($lot['lot_id'] ?? $lot['product_id'] ?? ''),
        'product'     => $p,
        'state'       => $state,
        'status'      => $status,
        'sold'        => $status === 'sold',
        'reserved'    => $reserved && $status !== 'sold',
        'reserved_by' => (string)($lot['reserved_by'] ?? ''),
        'rrp_cents'   => isset($lot['rrp_cents']) ? (int)$lot['rrp_cents'] : ($p['rrp_cents'] ?? null),
        'members_until' => (int)($lot['members_only_until'] ?? 0),
        'members_only' => (int)($lot['members_only_until'] ?? 0) > $now,
        'saving_pct'  => max(0, $pct),
        'available'   => $status === 'open' && !$reserved && $state['phase'] === 'live',
    ];
}

/**
 * Vitrine çıkacak loslar.
 * $opts: include_sold (bool), include_scheduled (bool), limit (int)
 */
function vr_vault_lots(array $opts = []): array
{
    $now  = time();
    $out  = [];

    foreach (vr_vault_all() as $lot) {
        $v = vr_vault_lot($lot, $now);
        if ($v === null) continue;
        if ($v['sold'] && empty($opts['include_sold'])) continue;
        if ($v['state']['phase'] === 'scheduled' && empty($opts['include_scheduled'])) continue;
        if ((string)($lot['status'] ?? 'open') === 'closed') continue;
        $out[] = $v;
    }

    // Sıra: canlı ve tabana yakın olanlar önce (en cazip), sonra yeni açılanlar.
    usort($out, function ($a, $b) {
        return [$a['sold'] ? 1 : 0, -$a['saving_pct'], $a['state']['cents']]
           <=> [$b['sold'] ? 1 : 0, -$b['saving_pct'], $b['state']['cents']];
    });

    if (!empty($opts['limit'])) $out = array_slice($out, 0, (int)$opts['limit']);
    return $out;
}

function vr_vault_find(string $lotId): ?array
{
    foreach (vr_vault_all() as $lot) {
        if ((string)($lot['lot_id'] ?? '') === $lotId) return vr_vault_lot($lot);
    }
    return null;
}

/** Vault'ta duran ürün id'leri (vitrinde tekrar göstermemek için). */
function vr_vault_product_ids(): array
{
    $ids = [];
    foreach (vr_vault_all() as $lot) {
        $st = (string)($lot['status'] ?? 'open');
        if ($st === 'open' || $st === 'reserved') $ids[(string)($lot['product_id'] ?? '')] = true;
    }
    unset($ids['']);
    return $ids;
}

/** Sonraki senkron noktası — anasayfadaki "sonraki senkron" sayacı için. */
function vr_vault_next_drop(): ?int
{
    $best = null;
    foreach (vr_vault_lots() as $v) {
        $n = $v['state']['next_at'];
        if ($n !== null && ($best === null || $n < $best)) $best = $n;
    }
    return $best;
}

// ---------------------------------------------------------------- rezervasyon
/**
 * Losu bu oturum için rezerve et ve fiyatı sabitle.
 * Dönüş: [ok, mesaj_anahtari, sabitlenmis_kurus]
 */
function vr_vault_reserve(string $lotId, string $sessionId): array
{
    $now    = time();
    $result = [false, 'vault_gone', 0];

    vr_store_mutate(VR_VAULT_FILE, static function ($d) use ($lotId, $sessionId, $now, &$result) {
        if (!is_array($d)) $d = ['lots' => []];
        if (!is_array($d['lots'] ?? null)) $d['lots'] = [];

        // Tohum losları ilk yazmada kalıcı dosyaya taşı (demo → gerçek).
        if (!$d['lots']) $d['lots'] = vr_vault_all();

        foreach ($d['lots'] as $i => $lot) {
            if ((string)($lot['lot_id'] ?? '') !== $lotId) continue;

            $st = vr_vault_state($lot, $now);
            if ($st === null || $st['phase'] !== 'live') { $result = [false, 'vault_gone', 0]; return null; }
            if ((string)($lot['status'] ?? 'open') === 'sold') { $result = [false, 'vault_gone', 0]; return null; }

            $resUntil = (int)($lot['reserved_until'] ?? 0);
            $resBy    = (string)($lot['reserved_by'] ?? '');
            if ($resUntil > $now && $resBy !== $sessionId) {
                $result = [false, 'vault_gone', 0];   // başkası kasada
                return null;
            }

            // Fiyatı ŞİMDİ sabitle: ödeme sırasında bir kademe düşse bile
            // kullanıcı gördüğü fiyatı öder (lehine olan durumda da aynısı —
            // sabit fiyat, sürpriz yok).
            $d['lots'][$i]['reserved_until']      = $now + VR_RESERVE_MINUTES * 60;
            $d['lots'][$i]['reserved_by']         = $sessionId;
            $d['lots'][$i]['reserved_price_cents'] = $st['cents'];
            $d['lots'][$i]['reserved_step']       = $st['step'];

            $result = [true, '', $st['cents']];
            return $d;
        }

        $result = [false, 'vault_gone', 0];
        return null;
    }, ['lots' => []]);

    return $result;
}

/** Rezervasyonu bırak (sepetten çıkarma / ödeme iptali). */
function vr_vault_release(string $lotId, string $sessionId): void
{
    vr_store_mutate(VR_VAULT_FILE, static function ($d) use ($lotId, $sessionId) {
        if (!is_array($d['lots'] ?? null)) return null;
        $changed = false;
        foreach ($d['lots'] as $i => $lot) {
            if ((string)($lot['lot_id'] ?? '') !== $lotId) continue;
            if ((string)($lot['reserved_by'] ?? '') !== $sessionId) continue;
            if ((string)($lot['status'] ?? '') === 'sold') continue;
            unset($d['lots'][$i]['reserved_until'], $d['lots'][$i]['reserved_by'],
                  $d['lots'][$i]['reserved_price_cents'], $d['lots'][$i]['reserved_step']);
            $changed = true;
        }
        return $changed ? $d : null;
    });
}

/**
 * Rezerve fiyatı doğrula. Rezervasyon bu oturuma aitse sabitlenmiş fiyatı,
 * değilse güncel plan fiyatını döndürür — ödeme oluşturulurken tek kaynak bu.
 */
function vr_vault_price_for(string $lotId, string $sessionId): ?int
{
    $now = time();
    foreach (vr_vault_all() as $lot) {
        if ((string)($lot['lot_id'] ?? '') !== $lotId) continue;
        if ((string)($lot['status'] ?? 'open') === 'sold') return null;

        if ((int)($lot['reserved_until'] ?? 0) > $now
            && (string)($lot['reserved_by'] ?? '') === $sessionId
            && (int)($lot['reserved_price_cents'] ?? 0) > 0) {
            return (int)$lot['reserved_price_cents'];
        }
        $st = vr_vault_state($lot, $now);
        return ($st && $st['phase'] === 'live') ? $st['cents'] : null;
    }
    return null;
}

/** Ödeme onaylandı — losu kapat. Webhook'tan çağrılır, idempotenttir. */
function vr_vault_mark_sold(string $lotId, string $orderId): bool
{
    $ok = false;
    vr_store_mutate(VR_VAULT_FILE, static function ($d) use ($lotId, $orderId, &$ok) {
        if (!is_array($d['lots'] ?? null)) $d['lots'] = vr_vault_all();
        foreach ($d['lots'] as $i => $lot) {
            if ((string)($lot['lot_id'] ?? '') !== $lotId) continue;
            if ((string)($lot['status'] ?? '') === 'sold') { $ok = true; return null; }  // zaten satılmış
            $d['lots'][$i]['status']   = 'sold';
            $d['lots'][$i]['sold_at']  = time();
            $d['lots'][$i]['order_id'] = $orderId;
            unset($d['lots'][$i]['reserved_until'], $d['lots'][$i]['reserved_by']);
            $ok = true;
            return $d;
        }
        return null;
    }, ['lots' => []]);
    return $ok;
}

/**
 * Yeni los aç. Plan burada yazılır ve bir daha değişmez.
 * Yönetim aracı (tools/vault-open.php) ve satıcı paneli bunu kullanır.
 */
function vr_vault_open(string $productId, int $openCents, int $floorCents, array $o = []): array
{
    $p = vr_product($productId);
    if ($p === null)                 return [false, 'urun bulunamadi'];
    if ($openCents <= 0 || $floorCents <= 0) return [false, 'fiyat gecersiz'];
    if ($floorCents > $openCents)    return [false, 'taban acilis fiyatindan yuksek olamaz'];

    $lot = [
        'lot_id'      => 'v-' . substr(sha1($productId . '|' . microtime(true)), 0, 12),
        'product_id'  => $productId,
        'open_cents'  => $openCents,
        'floor_cents' => $floorCents,
        'steps'       => max(1, (int)($o['steps'] ?? vr_config('vault_steps', 6))),
        'step_hours'  => max(0.5, (float)($o['step_hours'] ?? vr_config('vault_step_hours', 24))),
        'start_at'    => (int)($o['start_at'] ?? time()),
        'status'      => 'open',
        'created_at'  => time(),
        'seller_uid'  => $p['seller_uid'],
    ];
    if (!empty($o['rrp_cents'])) $lot['rrp_cents'] = (int)$o['rrp_cents'];

    // Üye önceliği los başına ayarlanabilir. Sıfır verilirse los ilk saniyeden
    // itibaren herkese açık. Gerekli, çünkü her losa öncelik konursa yeni
    // ziyaretçi Vault'u baştan sona kilitli görüyor — teşvik değil duvar olur.
    $head = isset($o['members_head_hours'])
        ? (float)$o['members_head_hours']
        : (float)vr_config('vault_member_head_start_hours', 12);
    if ($head > 0) $lot['members_only_until'] = $lot['start_at'] + (int)round($head * 3600);

    $ok = false;
    vr_store_mutate(VR_VAULT_FILE, static function ($d) use ($lot, &$ok) {
        if (!is_array($d)) $d = ['lots' => []];
        if (!is_array($d['lots'] ?? null) || !$d['lots']) $d['lots'] = vr_vault_all();
        // Aynı ürün için açık bir los varsa ikincisini açmıyoruz.
        foreach ($d['lots'] as $ex) {
            if ((string)($ex['product_id'] ?? '') === $lot['product_id']
                && in_array((string)($ex['status'] ?? 'open'), ['open', 'reserved'], true)) {
                return null;
            }
        }
        $d['lots'][] = $lot;
        $ok = true;
        return $d;
    }, ['lots' => []]);

    return $ok ? [true, $lot['lot_id']] : [false, 'bu urun icin zaten acik bir los var'];
}

// ------------------------------------------------------------------- üyelik
/**
 * Vault erken erişim üyeliği. Newsletter onayında imzalı çerez verilir —
 * kişisel veri saklamadan "üye mi?" sorusunu cevaplayabilmek için.
 */
function vr_member_secret(): string
{
    $f = vr_data_dir() . '/retail-secret.key';
    if (is_readable($f)) {
        $k = trim((string)file_get_contents($f));
        if ($k !== '') return $k;
    }
    $k = bin2hex(random_bytes(32));
    @file_put_contents($f, $k);
    @chmod($f, 0600);
    return $k;
}

function vr_member_grant(string $email): void
{
    $exp = time() + 31536000;                       // 1 yıl
    $payload = $exp . '|' . substr(hash('sha256', strtolower($email)), 0, 16);
    $sig = hash_hmac('sha256', $payload, vr_member_secret());
    @setcookie('vr_member', $payload . '|' . $sig, [
        'expires'  => $exp,
        'path'     => vr_base_url() === '' ? '/' : vr_base_url() . '/',
        'httponly' => true,
        'secure'   => str_starts_with(vr_origin(), 'https://'),
        'samesite' => 'Lax',
    ]);
}

function vr_is_member(): bool
{
    $c = (string)($_COOKIE['vr_member'] ?? '');
    if ($c === '') return false;

    $parts = explode('|', $c);
    if (count($parts) !== 3) return false;
    [$exp, $hash, $sig] = $parts;

    if (!hash_equals(hash_hmac('sha256', $exp . '|' . $hash, vr_member_secret()), $sig)) return false;
    return (int)$exp > time();
}
