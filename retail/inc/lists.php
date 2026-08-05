<?php
/**
 * Çerez tabanlı listeler: Merkliste (beğeniler) ve "son bakılanlar"
 * -----------------------------------------------------------------
 * Neden hesap değil çerez: alıcı tarafında hesap açmıyoruz. Bu iki liste
 * kullanıcının kendi cihazında kalıyor, sunucuda hiçbir profil oluşmuyor —
 * veri minimizasyonu (DSGVO Art. 5) açısından da doğrusu bu.
 *
 * Çerez yalnızca ürün id'si taşıyor: kişisel veri değil, izleme yok, sunucuda
 * eşleştirilmiyor. Bu yüzden rıza gerektirmiyor (TDDDG §25 Abs. 2 Nr. 2) —
 * işlevi kullanıcı açıkça istediği için var.
 */

declare(strict_types=1);

require_once __DIR__ . '/boot.php';
require_once __DIR__ . '/catalog.php';

const VR_WISH_COOKIE = 'vr_wish';
const VR_SEEN_COOKIE = 'vr_seen';
const VR_WISH_MAX    = 60;
const VR_SEEN_MAX    = 12;

/** Çerezden id listesi oku (bozuk/uzun değerlere karşı temizleyerek). */
function vr_list_read(string $cookie, int $max): array
{
    $raw = (string)($_COOKIE[$cookie] ?? '');
    if ($raw === '') return [];

    $ids = [];
    foreach (explode(',', $raw) as $id) {
        $id = trim($id);
        // Katalog id'leri harf/rakam/nokta/tire; başka bir şey gelmişse at.
        if ($id === '' || !preg_match('/^[A-Za-z0-9._-]{1,64}$/', $id)) continue;
        $ids[$id] = true;
        if (count($ids) >= $max) break;
    }
    return array_keys($ids);
}

function vr_list_write(string $cookie, array $ids, int $days = 180): void
{
    $val = implode(',', array_slice(array_values(array_unique($ids)), 0, VR_WISH_MAX));
    @setcookie($cookie, $val, [
        'expires'  => $val === '' ? time() - 3600 : time() + $days * 86400,
        'path'     => vr_base_url() === '' ? '/' : vr_base_url() . '/',
        'httponly' => true,
        'secure'   => str_starts_with(vr_origin(), 'https://'),
        'samesite' => 'Lax',
    ]);
    // Aynı istek içinde okunabilsin (yönlendirmeden önce sayaç doğru olsun).
    $_COOKIE[$cookie] = $val;
}

// ------------------------------------------------------------------ Merkliste
function vr_wish_ids(): array
{
    return vr_list_read(VR_WISH_COOKIE, VR_WISH_MAX);
}

function vr_wish_has(string $id): bool
{
    return in_array($id, vr_wish_ids(), true);
}

function vr_wish_count(): int
{
    return count(vr_wish_ids());
}

/** Ekle/çıkar (aç-kapa). Dönüş: artık listede mi? */
function vr_wish_toggle(string $id): bool
{
    $ids = vr_wish_ids();
    $pos = array_search($id, $ids, true);

    if ($pos === false) {
        array_unshift($ids, $id);
        vr_list_write(VR_WISH_COOKIE, $ids);
        return true;
    }
    unset($ids[$pos]);
    vr_list_write(VR_WISH_COOKIE, array_values($ids));
    return false;
}

/** Listedeki ürünler (artık katalogda olmayanlar sessizce düşer). */
function vr_wish_products(): array
{
    $out = [];
    foreach (vr_wish_ids() as $id) {
        $p = vr_product($id);
        if ($p !== null) $out[] = $p;
    }
    return $out;
}

// ------------------------------------------------------------- son bakılanlar
/** Ürün sayfası açılınca çağrılır. */
function vr_seen_push(string $id): void
{
    $ids = vr_list_read(VR_SEEN_COOKIE, VR_SEEN_MAX + 1);
    $ids = array_values(array_filter($ids, fn($x) => $x !== $id));
    array_unshift($ids, $id);
    vr_list_write(VR_SEEN_COOKIE, array_slice($ids, 0, VR_SEEN_MAX), 30);
}

/** Son bakılanlar; $exclude verilirse (açık olan ürün) listeden çıkarılır. */
function vr_seen_products(string $exclude = '', int $limit = 6): array
{
    $out = [];
    foreach (vr_list_read(VR_SEEN_COOKIE, VR_SEEN_MAX) as $id) {
        if ($id === $exclude) continue;
        $p = vr_product($id);
        if ($p === null) continue;
        $out[] = $p;
        if (count($out) >= $limit) break;
    }
    return $out;
}

/**
 * Merkliste düğmesi (kart ve ürün sayfası).
 * Saf form gönderimi — JavaScript olmadan da çalışır; JS varsa sayfa
 * yenilenmeden güncellenir (assets/js).
 */
function vr_wish_button(array $p, bool $large = false): void
{
    $on = vr_wish_has((string)$p['id']);
?>
<form class="wishform" method="post" action="<?= h(vr_url('wishlist.php')) ?>" data-wish>
  <?= vr_csrf_field() ?>
  <input type="hidden" name="id" value="<?= h((string)$p['id']) ?>">
  <input type="hidden" name="back" value="<?= h((string)($_SERVER['REQUEST_URI'] ?? '/')) ?>">
  <button class="wish<?= $large ? ' wish--lg' : '' ?><?= $on ? ' is-on' : '' ?>" type="submit"
          aria-pressed="<?= $on ? 'true' : 'false' ?>"
          title="<?= $on ? te('wish_remove') : te('wish_add') ?>">
    <svg width="<?= $large ? 20 : 17 ?>" height="<?= $large ? 20 : 17 ?>" viewBox="0 0 24 24"
         fill="<?= $on ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="1.5"
         aria-hidden="true"><path d="M12 20s-7-4.6-7-9.6A4.4 4.4 0 0 1 12 7a4.4 4.4 0 0 1 7 3.4c0 5-7 9.6-7 9.6z"/></svg>
    <?php if ($large): ?><span><?= $on ? te('wish_saved') : te('wish_add') ?></span><?php endif; ?>
  </button>
</form>
<?php
}
