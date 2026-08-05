<?php
/**
 * Atomik JSON deposu
 * ------------------
 * Veritabanı yok — canlı site de JSON dosyalarıyla çalışıyor, aynı deseni
 * sürdürüyoruz. İki şeyi ciddiye alıyoruz:
 *
 *  1) Yazma atomik: geçici dosyaya yaz → rename(). Yarı yazılmış JSON asla
 *     görünmez (rename aynı dosya sisteminde atomiktir).
 *  2) Oku-değiştir-yaz kilitli: sepet/sipariş/stok aynı anda iki istekten
 *     gelebilir. vr_store_mutate() flock ile seri hale getirir.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function vr_store_path(string $name): string
{
    // Dosya adı asla istemciden gelmemeli; yine de yol kaçışını kapatıyoruz.
    if (!preg_match('/^[a-z0-9._-]+$/i', $name)) {
        throw new InvalidArgumentException('gecersiz depo adi: ' . $name);
    }
    return vr_data_dir() . '/' . $name;
}

/** Salt-okunur JSON oku. Dosya yoksa/bozuksa $default döner (asla çökmez). */
function vr_store_read(string $name, mixed $default = []): mixed
{
    $path = vr_store_path($name);
    if (!is_readable($path)) return $default;

    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') return $default;

    $data = json_decode($raw, true);
    return $data === null ? $default : $data;
}

/** Atomik yaz. Başarısızlıkta false döner, exception atmaz. */
function vr_store_write(string $name, mixed $data): bool
{
    $path = vr_store_path($name);
    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0750, true);

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) return false;

    $tmp = $path . '.tmp-' . bin2hex(random_bytes(6));
    if (@file_put_contents($tmp, $json) === false) { @unlink($tmp); return false; }
    @chmod($tmp, 0640);

    if (!@rename($tmp, $path)) { @unlink($tmp); return false; }
    return true;
}

/**
 * Kilitli oku-değiştir-yaz. $fn mevcut veriyi alır, yenisini döndürür.
 * $fn null döndürürse yazma yapılmaz (değişiklik yok / iptal).
 * Dönüş: [basarili_mi, $fn'in dondurdugu deger].
 */
function vr_store_mutate(string $name, callable $fn, mixed $default = []): array
{
    $path = vr_store_path($name);
    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0750, true);

    // Kilidi ayrı bir .lock dosyasında tutuyoruz: veri dosyası rename ile
    // değiştiği için onun üzerindeki flock yeni inode'a taşınmaz.
    $lockPath = $path . '.lock';
    $lock = @fopen($lockPath, 'c');
    if ($lock === false) return [false, null];

    try {
        if (!flock($lock, LOCK_EX)) return [false, null];

        $current = vr_store_read($name, $default);
        $next    = $fn($current);
        if ($next === null) return [true, null];   // bilinçli "yazma yok"

        $ok = vr_store_write($name, $next);
        return [$ok, $next];
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/**
 * Yazma öncesi zaman damgalı yedek — canlı workflow'ların (add-products.yml vb.)
 * listings.json için yaptığının aynısı. Sadece kritik dosyalarda çağırıyoruz.
 */
function vr_store_backup(string $name): bool
{
    $path = vr_store_path($name);
    if (!is_readable($path)) return true;      // yedeklenecek bir şey yok
    return @copy($path, $path . '.bak-' . gmdate('Ymd-His'));
}

/** Bir listeye satır ekle (kilitli). */
function vr_store_append(string $name, array $row): bool
{
    [$ok] = vr_store_mutate($name, static function ($rows) use ($row) {
        if (!is_array($rows)) $rows = [];
        $rows[] = $row;
        return $rows;
    });
    return $ok;
}
