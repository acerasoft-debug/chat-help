<?php
/**
 * Butik bulucu — TOHUM ALAN ADLARI (26 Eki 2026, operatör: "kendi imkânlarınla kesin çözüm bul, aramaya ve göndermeye").
 *
 * OpenStreetMap havuzu tükendi (son turlarda adayların %90'ı zaten bilinen alan adı), Brave/Google anahtarı yok. Yeni aday
 * kaynağı: Overture Maps Places (ücretsiz, anahtarsız, CDLA-Permissive-2.0 -- saklanabilir; Meta + Microsoft + OSM birleşik)
 * Avrupa'daki giyim / butik / aksesuar / ayakkabı dükkânlarının siteleri (overture-seeds.yml). İsteğe bağlı başka bir liste
 * depoya DÜZ girmez (depo herkese açık): scripts/envelope_seal.php ile mühürlenmiş seeds/*.enc olarak gelir, burada
 * sunucunun özel anahtarıyla (~/.vestra_inbox_key.pem, sunucudan çıkmaz) açılır ve data/finder_seeds.json'a (chmod 600)
 * eklenir. Her bulucu turu (find-customers.yml) buradan sıradaki N alan adını alır ve olağan süzgeçten geçirir: sitede
 * premium marka, zincir / tek marka / pazaryeri değil, gerçek e-posta, MX. Yani tohum yalnız ADAY; kurallar aynen geçerli.
 *
 *   php vestra-finder-seeds.php import <zarf dosyası>   zarfı aç, yeni alan adlarını ekle (sayı basar, alan adı basmaz)
 *   php vestra-finder-seeds.php import -               aynısı, düz JSON stdin'den (overture-seeds.yml, SSH borusu)
 *   php vestra-finder-seeds.php take <n>               sıradaki n alan adını "SEEDS a.it~clothing_store,b.fr" satırında verir
 *                                                     (~ sonrası Overture kategorisi, varsa), alındı damgalar
 *   php vestra-finder-seeds.php status                 toplam / bekleyen / alınan, ülke dağılımı
 * Zarf gövdesi: {"seeds":[{"d":"alanadi.it","c":"Italy","n":"Firma","k":"clothing_store"}, …]}  (k isteğe bağlı)
 */
[$self, $mode, $arg1] = array_pad($argv, 3, '');
$root = getenv('VESTRA_ROOT') ?: (getenv('HOME').'/public_html');
require_once $root.'/inc/products.php';

const SEED_PAUSED = ['germany', 'deutschland'];   // operatör: Almanya kapalı (vestra_send_paused_countries ile aynı)

function sd_open(string $env, string $keyFile): array {
    $dec = fn(string $s): string => (string)base64_decode(strtr($s, '!$%&#,;?@~', '0123456789'), true);
    $parts = explode('.', trim($env));
    if (count($parts) !== 3) throw new RuntimeException('zarf bicimi bozuk');
    $priv = openssl_pkey_get_private((string)@file_get_contents($keyFile));
    if (!$priv) throw new RuntimeException('ozel anahtar okunamadi');
    $aes = '';
    if (!openssl_private_decrypt($dec($parts[0]), $aes, $priv, OPENSSL_PKCS1_OAEP_PADDING)) throw new RuntimeException('anahtar acilamadi');
    $json = openssl_decrypt($dec($parts[2]), 'aes-256-cbc', $aes, OPENSSL_RAW_DATA, $dec($parts[1]));
    $d = is_string($json) ? json_decode($json, true) : null;
    if (!is_array($d)) throw new RuntimeException('govde cozulemedi');
    return $d;
}
function sd_file(): string { return vestra_data_dir().'/finder_seeds.json'; }
function sd_load(): array {
    $d = json_decode((string)@file_get_contents(sd_file()), true);
    return is_array($d) && isset($d['items']) && is_array($d['items']) ? $d : ['items' => []];
}
function sd_save(array $d): void {
    $f = sd_file(); $tmp = $f.'.tmp-'.getmypid();
    file_put_contents($tmp, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX); @chmod($tmp, 0600);
    rename($tmp, $f);
}
/** Alan adını sadeleştir: şema, www., yol, port yok; geçersizse ''. */
function sd_norm(string $d): string {
    $d = strtolower(trim($d)); $d = (string)preg_replace('#^[a-z]+://#', '', $d); $d = explode('/', $d)[0]; $d = explode(':', $d)[0];
    $d = (string)preg_replace('/^www\./', '', $d);
    return preg_match('/^(?=.{4,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/', $d) ? $d : '';
}

try {
    if ($mode === 'import') {
        /* '-' = düz JSON stdin'den: overture-seeds.yml listeyi runner'dan SSH borusuyla verir (depoya / kütüğe hiç girmez). */
        if ($arg1 === '-') { $b = json_decode((string)stream_get_contents(STDIN), true); if (!is_array($b)) throw new RuntimeException('stdin JSON degil'); }
        else {
            if (!is_file($arg1)) throw new RuntimeException('zarf dosyasi yok');
            $b = sd_open((string)file_get_contents($arg1), getenv('HOME').'/.vestra_inbox_key.pem');
        }
        $db = sd_load(); $new = 0; $dup = 0; $bad = 0; $paused = 0; $now = date('c');
        foreach ((array)($b['seeds'] ?? []) as $s) {
            $d = sd_norm((string)($s['d'] ?? '')); $c = trim((string)($s['c'] ?? ''));
            if ($d === '') { $bad++; continue; }
            if (in_array(strtolower($c), SEED_PAUSED, true) || str_ends_with($d, '.de')) { $paused++; continue; }
            $k = preg_replace('/[^a-z_]/', '', strtolower((string)($s['k'] ?? '')));   // Overture kategorisi (27 Eki 2026: işimiz dışı ayrımı)
            if (isset($db['items'][$d])) { if ($k !== '' && empty($db['items'][$d]['k'])) $db['items'][$d]['k'] = $k; $dup++; continue; }
            $db['items'][$d] = ['c' => mb_substr($c, 0, 60), 'n' => mb_substr(trim((string)($s['n'] ?? '')), 0, 120), 'k' => $k, 'src' => (string)($b['source'] ?? 'lemlist'), 'added_at' => $now, 'taken_at' => null];
            $new++;
        }
        $db['imported_at'] = $now; sd_save($db);
        echo "ICE ALINDI: yeni {$new} · zaten vardi {$dup} · gecersiz {$bad} · durdurulan ulke {$paused} · toplam ".count($db['items'])."\n";
        exit(0);
    }
    if ($mode === 'take') {
        $n = max(1, min(1000, (int)$arg1 ?: 200));
        $db = sd_load(); $out = []; $now = date('c');
        foreach ($db['items'] as $d => $it) {
            if (count($out) >= $n) break;
            if (!empty($it['taken_at'])) continue;
            $db['items'][$d]['taken_at'] = $now; $out[] = $d.(!empty($it['k']) ? '~'.$it['k'] : '');   // alan~kategori (bulucu ayırır)
        }
        if ($out) sd_save($db);
        $left = count(array_filter($db['items'], fn($i) => empty($i['taken_at'])));
        fwrite(STDERR, "tohum: bu tur ".count($out)." · bekleyen {$left}\n");
        echo 'SEEDS '.implode(',', $out)."\n";
        exit(0);
    }
    if ($mode === 'status') {
        $db = sd_load(); $by = []; $wait = 0;
        foreach ($db['items'] as $it) { $by[$it['c'] ?: '?'] = ($by[$it['c'] ?: '?'] ?? 0) + 1; if (empty($it['taken_at'])) $wait++; }
        arsort($by);
        echo "tohum toplam ".count($db['items'])." · bekleyen {$wait} · alinan ".(count($db['items']) - $wait)." · son ice alma ".($db['imported_at'] ?? '-')."\n";
        echo "ulke: ".json_encode(array_slice($by, 0, 20, true), JSON_UNESCAPED_UNICODE)."\n";
        exit(0);
    }
    fwrite(STDERR, "kullanim: import <zarf> | take <n> | status\n"); exit(2);
} catch (Throwable $e) { fwrite(STDERR, 'HATA: '.$e->getMessage()."\n"); exit(1); }
