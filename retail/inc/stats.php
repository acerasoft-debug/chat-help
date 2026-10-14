<?php
/**
 * Ziyaretçi istatistiği ve IP kaydı
 * =================================
 * Operatör, 14 Eki 2026: "Vestra gibi bir admin: IP adresleri, istatistik,
 * kullanıcı, siparişler, hiçbir eksik kalmasın."
 *
 * Dış servis yok (Google Analytics vb. yok, çerez yok): her sayfa görünümü
 * sunucuda bir satır olarak data/stats/hits-YYYY-MM-DD.jsonl dosyasına
 * ekleniyor. Satır: zaman, IP, ülke (yerel tablo), yol, önemli sorgu
 * (ürün/marka/arama), yönlendiren, cihaz/tarayıcı/işletim sistemi, dil,
 * ziyaretçi kimliği (IP+tarayıcı günlük özeti), müşteri kimliği (girişliyse), bot işareti.
 *
 * HUKUK — IP adresi kişisel veridir (DSGVO). Dayanak: meşru menfaat, Art. 6
 * Abs. 1 lit. f (güvenlik, dolandırıcılık önleme, erişim istatistiği). Ham
 * satırlar VR_STATS_KEEP_DAYS gün sonra silinir; günlük özetler (ülke, sayfa,
 * cihaz sayıları — IP yok) kalır. Datenschutzerklärung "Server-Logfiles"
 * bölümü bunu anlatıyor.
 *
 * Performans: yazma tek append (kilit yok, satır < 4 KB olduğu için atomik).
 * Okuma: geçmiş günlerin özeti bir kez hesaplanıp day-YYYY-MM-DD.json'a
 * yazılıyor; yalnız bugün her seferinde taranıyor.
 */

declare(strict_types=1);

require_once __DIR__ . '/boot.php';
require_once __DIR__ . '/geo.php';

const VR_STATS_KEEP_DAYS = 60;     // ham satır (IP'li)
const VR_STATS_TOP       = 300;    // günlük özette boyut başına saklanan en çok N

function vr_stats_dir(): string
{
    $d = vr_data_dir() . '/stats';
    if (!is_dir($d)) { @mkdir($d, 0750, true); @file_put_contents($d . '/.htaccess', "Require all denied\n"); }
    return $d;
}

/** Bot mu: tarayıcı kimliğinden. Boş kimlik de bot sayılır. */
function vr_stats_is_bot(string $ua): bool
{
    if ($ua === '') return true;
    return (bool)preg_match('/bot|crawl|spider|slurp|curl|wget|python|java\/|go-http|headless|lighthouse|pagespeed|facebookexternalhit|whatsapp|telegram|preview|monitor|uptime|pingdom|scan|fetch|httpclient|okhttp|axios|node-fetch|libwww|ahrefs|semrush|mj12|dotbot|petalbot|bytespider|gptbot|claudebot|ccbot|applebot|bingpreview|yandex|baidu|duckduck/i', $ua);
}

/** Cihaz, tarayıcı, işletim sistemi — kaba ama yeterli. */
function vr_stats_ua(string $ua): array
{
    $d = preg_match('/iPad|Tablet|Android(?!.*Mobile)/i', $ua) ? 'tablet'
       : (preg_match('/Mobi|iPhone|Android/i', $ua) ? 'phone' : 'desktop');
    $b = 'other';
    foreach (['Edg' => 'Edge', 'OPR' => 'Opera', 'SamsungBrowser' => 'Samsung', 'Firefox' => 'Firefox', 'Chrome' => 'Chrome', 'CriOS' => 'Chrome', 'Safari' => 'Safari'] as $k => $n) {
        if (str_contains($ua, $k)) { $b = $n; break; }
    }
    $o = 'other';
    foreach (['iPhone' => 'iOS', 'iPad' => 'iOS', 'Android' => 'Android', 'Windows' => 'Windows', 'Mac OS' => 'macOS', 'CrOS' => 'ChromeOS', 'Linux' => 'Linux'] as $k => $n) {
        if (str_contains($ua, $k)) { $o = $n; break; }
    }
    return [$d, $b, $o];
}

/**
 * Ziyaretçi kimliği: IP + tarayıcı kimliğinin GÜNLÜK tuzlu özeti.
 *
 * Önce oturum kimliğinden türetiliyordu; canlıda ilk saatte görüldü ki çerez
 * tutmayan bir tarayıcı (tarama aracı, bazı uygulama içi görüntüleyiciler)
 * her istekte yeni oturum açıyor ve 9 IP'den 42 "tekil ziyaretçi" çıkıyordu.
 * IP+UA özeti çerezsiz de kararlı; tuz güne bağlı olduğu için kimlik günler
 * arasında eşleştirilemez (Plausible/Fathom'un yaptığı gibi), ham IP zaten
 * satırda ayrı duruyor ve 60 günde siliniyor.
 */
function vr_stats_visitor_id(): string
{
    $ip = vr_client_ip();
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($ip === '') return '';
    return substr(hash_hmac('sha256', $ip . '|' . $ua, vr_stats_secret() . gmdate('Y-m-d')), 0, 16);
}

function vr_stats_secret(): string
{
    static $s = null;
    if ($s !== null) return $s;
    $f = vr_data_dir() . '/stats-secret.txt';
    $s = is_readable($f) ? trim((string)file_get_contents($f)) : '';
    if ($s === '') { $s = bin2hex(random_bytes(24)); @file_put_contents($f, $s); @chmod($f, 0600); }
    return $s;
}

/**
 * Sayfa görünümünü kaydet. vr_layout_start çağırıyor; panel, statik dosya ve
 * ölçüm araçları hariç. Hata hiçbir zaman sayfayı düşürmez.
 */
function vr_track_view(array $o = []): void
{
    try {
        if (PHP_SAPI === 'cli') return;
        $path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $base = vr_base_url();
        if ($base !== '' && str_starts_with($path, $base)) $path = substr($path, strlen($base));
        if ($path === '' ) $path = '/';
        if (preg_match('#^/(admin|assets|uploads|api)/#', $path)) return;
        if (!empty($_SESSION['vr_admin'])) return;          // yönetici gezintisi istatistiğe girmez

        $ua  = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300);
        $bot = vr_stats_is_bot($ua);
        [$dev, $br, $os] = vr_stats_ua($ua);
        $ip  = vr_client_ip();
        $q   = [];
        foreach (['id', 'brand', 'cat', 'q', 'lot'] as $k) {
            $v = trim((string)($_GET[$k] ?? ''));
            if ($v !== '') $q[$k] = mb_substr($v, 0, 80);
        }
        $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
        $rh  = $ref !== '' ? strtolower((string)parse_url($ref, PHP_URL_HOST)) : '';
        if ($rh !== '' && ($rh === strtolower((string)parse_url(vr_origin(), PHP_URL_HOST)) || str_ends_with($rh, '.' . strtolower((string)parse_url(vr_origin(), PHP_URL_HOST))))) $rh = '';
        $row = [
            't'  => time(),
            'ip' => $ip,
            'cc' => $ip !== '' ? vr_geoip_lookup($ip) : '',
            'p'  => mb_substr($path, 0, 120),
            'q'  => $q,
            'r'  => mb_substr($rh, 0, 80),
            'd'  => $dev, 'b' => $br, 'o' => $os,
            'l'  => vr_lang(),
            's'  => $bot ? '' : vr_stats_visitor_id(),
            'u'  => (string)($_SESSION['vr_customer_id'] ?? ''),
            'bot'=> $bot ? 1 : 0,
            'ua' => $ua,
        ];
        $f = vr_stats_dir() . '/hits-' . gmdate('Y-m-d') . '.jsonl';
        @file_put_contents($f, json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
        if (random_int(1, 400) === 1) vr_stats_prune();
    } catch (Throwable $e) {
        // sessiz
    }
}

/** Eski ham dosyaları sil (özetler kalır). */
function vr_stats_prune(): void
{
    $cut = gmdate('Y-m-d', time() - VR_STATS_KEEP_DAYS * 86400);
    foreach (glob(vr_stats_dir() . '/hits-*.jsonl') ?: [] as $f) {
        if (preg_match('/hits-(\d{4}-\d{2}-\d{2})\.jsonl$/', $f, $m) && $m[1] < $cut) {
            vr_stats_day($m[1]);          // özet yoksa son kez üret
            @unlink($f);
        }
    }
}

/** Bir günün ham satırları (bot dahil). */
function vr_stats_rows(string $day): array
{
    $f = vr_stats_dir() . '/hits-' . $day . '.jsonl';
    if (!is_readable($f)) return [];
    $out = [];
    $h = fopen($f, 'r');
    if (!$h) return [];
    while (($line = fgets($h)) !== false) {
        $r = json_decode($line, true);
        if (is_array($r) && isset($r['t'])) $out[] = $r;
    }
    fclose($h);
    return $out;
}

/** Sayaç dizisini büyükten küçüğe sırala, ilk N. */
function vr_stats_top(array $m, int $n = VR_STATS_TOP): array
{
    arsort($m);
    return array_slice($m, 0, $n, true);
}

/**
 * Günlük özet (IP'siz): sayfa görünümü, ziyaretçi, ülke, sayfa, ürün, marka,
 * yönlendiren, cihaz, tarayıcı, sistem, dil, saat dağılımı, aramalar.
 * Geçmiş gün bir kez hesaplanıp önbelleğe alınır.
 */
function vr_stats_day(string $day): array
{
    $today = gmdate('Y-m-d');
    $cache = vr_stats_dir() . '/day-' . $day . '.json';
    if ($day < $today && is_readable($cache)) {
        $c = json_decode((string)file_get_contents($cache), true);
        if (is_array($c)) return $c;
    }
    $rows = vr_stats_rows($day);
    $s = ['day' => $day, 'pv' => 0, 'bots' => 0, 'uv' => 0, 'ips' => 0, 'cust' => 0,
          'cc' => [], 'p' => [], 'prod' => [], 'brand' => [], 'ref' => [], 'd' => [], 'b' => [], 'o' => [], 'l' => [], 'h' => array_fill(0, 24, 0), 'q' => [], 'entry' => []];
    $uv = []; $ips = []; $cust = []; $firstBySid = [];
    foreach ($rows as $r) {
        if (!empty($r['bot'])) { $s['bots']++; continue; }
        $s['pv']++;
        $uv[$r['s'] ?? ''] = 1; $ips[$r['ip'] ?? ''] = 1;
        if (($r['u'] ?? '') !== '') $cust[$r['u']] = 1;
        $s['cc'][$r['cc'] ?: '??'] = ($s['cc'][$r['cc'] ?: '??'] ?? 0) + 1;
        $s['p'][$r['p']] = ($s['p'][$r['p']] ?? 0) + 1;
        if (($r['q']['id'] ?? '') !== '') $s['prod'][$r['q']['id']] = ($s['prod'][$r['q']['id']] ?? 0) + 1;
        if (($r['q']['brand'] ?? '') !== '') $s['brand'][$r['q']['brand']] = ($s['brand'][$r['q']['brand']] ?? 0) + 1;
        if (($r['q']['q'] ?? '') !== '') { $k = mb_strtolower($r['q']['q']); $s['q'][$k] = ($s['q'][$k] ?? 0) + 1; }
        $s['ref'][$r['r'] ?: '(direkt)'] = ($s['ref'][$r['r'] ?: '(direkt)'] ?? 0) + 1;
        foreach (['d', 'b', 'o', 'l'] as $k) $s[$k][$r[$k] ?? ''] = ($s[$k][$r[$k] ?? ''] ?? 0) + 1;
        $s['h'][(int)gmdate('G', (int)$r['t'])]++;
        if (($r['s'] ?? '') !== '' && !isset($firstBySid[$r['s']])) { $firstBySid[$r['s']] = 1; $s['entry'][$r['p']] = ($s['entry'][$r['p']] ?? 0) + 1; }
    }
    unset($uv[''], $ips['']);
    $s['uv'] = count($uv); $s['ips'] = count($ips); $s['cust'] = count($cust);
    foreach (['cc', 'p', 'prod', 'brand', 'ref', 'd', 'b', 'o', 'l', 'q', 'entry'] as $k) $s[$k] = vr_stats_top($s[$k]);
    if ($day < $today) @file_put_contents($cache, json_encode($s, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    return $s;
}

/** Gün listesi: bugünden geriye $days gün (eski → yeni). */
function vr_stats_days(int $days): array
{
    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) $out[] = gmdate('Y-m-d', time() - $i * 86400);
    return $out;
}

/** Birden çok günün özetini birleştir. */
function vr_stats_range(int $days): array
{
    $tot = ['days' => [], 'pv' => 0, 'bots' => 0, 'uv' => 0, 'ips' => 0, 'cust' => 0,
            'cc' => [], 'p' => [], 'prod' => [], 'brand' => [], 'ref' => [], 'd' => [], 'b' => [], 'o' => [], 'l' => [], 'h' => array_fill(0, 24, 0), 'q' => [], 'entry' => []];
    foreach (vr_stats_days($days) as $day) {
        $s = vr_stats_day($day);
        $tot['days'][$day] = ['pv' => $s['pv'], 'uv' => $s['uv'], 'ips' => $s['ips'], 'bots' => $s['bots'], 'cust' => $s['cust']];
        foreach (['pv', 'bots', 'uv', 'ips', 'cust'] as $k) $tot[$k] += $s[$k];
        foreach (['cc', 'p', 'prod', 'brand', 'ref', 'd', 'b', 'o', 'l', 'q', 'entry'] as $k) {
            foreach ($s[$k] as $kk => $n) $tot[$k][$kk] = ($tot[$k][$kk] ?? 0) + $n;
        }
        foreach ($s['h'] as $i => $n) $tot['h'][$i] += $n;
    }
    foreach (['cc', 'p', 'prod', 'brand', 'ref', 'd', 'b', 'o', 'l', 'q', 'entry'] as $k) $tot[$k] = vr_stats_top($tot[$k]);
    return $tot;
}

/** Şu an sitede olanlar: son $sec saniyede görülen ziyaretçiler. */
function vr_stats_live(int $sec = 300): array
{
    $cut = time() - $sec;
    $by = [];
    foreach ([gmdate('Y-m-d'), gmdate('Y-m-d', time() - 86400)] as $day) {
        foreach (vr_stats_rows($day) as $r) {
            if ((int)$r['t'] < $cut || !empty($r['bot'])) continue;
            $k = $r['s'] ?: $r['ip'];
            if (!isset($by[$k])) $by[$k] = ['ip' => $r['ip'], 'cc' => $r['cc'], 'd' => $r['d'], 'b' => $r['b'], 'u' => $r['u'] ?? '', 'first' => $r['t'], 'last' => $r['t'], 'n' => 0, 'p' => $r['p'], 'q' => $r['q']];
            $by[$k]['n']++;
            if ($r['t'] >= $by[$k]['last']) { $by[$k]['last'] = $r['t']; $by[$k]['p'] = $r['p']; $by[$k]['q'] = $r['q']; }
            if ($r['t'] < $by[$k]['first']) $by[$k]['first'] = $r['t'];
        }
    }
    uasort($by, fn($a, $b) => $b['last'] <=> $a['last']);
    return $by;
}

/**
 * IP dizini: son $days günde görülen her adres — ülke, ilk/son görülme,
 * isabet, sayfa sayısı, ziyaretçi sayısı, müşteri kimlikleri, cihaz,
 * tarayıcı, son sayfa, bot mu. Ham dosyalardan (IP yalnız orada).
 */
function vr_stats_ips(int $days, bool $withBots = false): array
{
    $by = [];
    foreach (vr_stats_days(min($days, VR_STATS_KEEP_DAYS)) as $day) {
        foreach (vr_stats_rows($day) as $r) {
            if (!$withBots && !empty($r['bot'])) continue;
            $ip = (string)($r['ip'] ?? '');
            if ($ip === '') continue;
            if (!isset($by[$ip])) $by[$ip] = ['ip' => $ip, 'cc' => $r['cc'], 'first' => $r['t'], 'last' => $r['t'], 'n' => 0, 'pages' => [], 'sids' => [], 'uids' => [], 'd' => [], 'b' => [], 'o' => [], 'bot' => 0, 'p' => $r['p'], 'ua' => $r['ua'] ?? ''];
            $x = &$by[$ip];
            $x['n']++;
            $x['pages'][$r['p']] = 1;
            if (($r['s'] ?? '') !== '') $x['sids'][$r['s']] = 1;
            if (($r['u'] ?? '') !== '') $x['uids'][$r['u']] = 1;
            $x['d'][$r['d']] = ($x['d'][$r['d']] ?? 0) + 1; $x['b'][$r['b']] = ($x['b'][$r['b']] ?? 0) + 1; $x['o'][$r['o']] = ($x['o'][$r['o']] ?? 0) + 1;
            if (!empty($r['bot'])) $x['bot']++;
            if ($r['t'] >= $x['last']) { $x['last'] = $r['t']; $x['p'] = $r['p']; $x['ua'] = $r['ua'] ?? $x['ua']; }
            if ($r['t'] < $x['first']) $x['first'] = $r['t'];
            unset($x);
        }
    }
    foreach ($by as &$x) {
        $x['pages'] = count($x['pages']); $x['sids'] = count($x['sids']); $x['uids'] = array_keys($x['uids']);
        foreach (['d', 'b', 'o'] as $k) { arsort($x[$k]); $x[$k] = (string)array_key_first($x[$k]); }
    }
    unset($x);
    uasort($by, fn($a, $b) => $b['last'] <=> $a['last']);
    return $by;
}

/** Bir IP'nin (ya da müşterinin) ham satırları, yeni → eski. */
function vr_stats_trail(string $ip = '', string $uid = '', int $days = VR_STATS_KEEP_DAYS, int $max = 2000): array
{
    $out = [];
    foreach (array_reverse(vr_stats_days(min($days, VR_STATS_KEEP_DAYS))) as $day) {
        foreach (array_reverse(vr_stats_rows($day)) as $r) {
            if ($ip !== '' && ($r['ip'] ?? '') !== $ip) continue;
            if ($uid !== '' && ($r['u'] ?? '') !== $uid) continue;
            $out[] = $r;
            if (count($out) >= $max) return $out;
        }
    }
    return $out;
}

// ------------------------------------------------------------ IP engeli
/** Engelli adresler: [['ip' => …, 'note' => …, 'at' => …, 'by' => …]] */
function vr_ip_blocklist(): array
{
    $l = vr_store_read('ip-block.json', []);
    return is_array($l) ? array_values(array_filter($l, fn($r) => is_array($r) && ($r['ip'] ?? '') !== '')) : [];
}

/** Adres engelli mi (tam eşleşme ya da "1.2.3." öneki / "2001:db8:" öneki). */
function vr_ip_blocked(string $ip): bool
{
    if ($ip === '') return false;
    foreach (vr_ip_blocklist() as $r) {
        $b = (string)$r['ip'];
        if ($b === $ip) return true;
        if (str_ends_with($b, '.') || str_ends_with($b, ':')) { if (str_starts_with($ip, $b)) return true; }
    }
    return false;
}

function vr_ip_block(string $ip, string $note, string $by): void
{
    $ip = trim($ip);
    if ($ip === '') return;
    vr_store_mutate('ip-block.json', static function ($l) use ($ip, $note, $by) {
        if (!is_array($l)) $l = [];
        foreach ($l as $r) if (($r['ip'] ?? '') === $ip) return null;
        $l[] = ['ip' => $ip, 'note' => mb_substr($note, 0, 200), 'at' => time(), 'by' => $by];
        return $l;
    });
}

function vr_ip_unblock(string $ip): void
{
    vr_store_mutate('ip-block.json', static function ($l) use ($ip) {
        if (!is_array($l)) return null;
        return array_values(array_filter($l, fn($r) => ($r['ip'] ?? '') !== $ip));
    });
}

/** Engelli adrese 403 — vr_layout_start'ın ilk satırı. */
function vr_ip_gate(): void
{
    if (PHP_SAPI === 'cli') return;
    $ip = vr_client_ip();
    if (!vr_ip_blocked($ip)) return;
    if (preg_match('#/admin/#', (string)($_SERVER['REQUEST_URI'] ?? ''))) return;   // yönetici kendini kilitlemesin
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Zugriff gesperrt.\n";
    exit;
}

// ------------------------------------------------------- yönetici kaydı
/** Panelde yapılan her değişiklik: kim, ne, ne zaman, hangi adresten. */
function vr_admin_audit(string $action, array $ctx = []): void
{
    vr_session_start();
    $line = ['at' => time(), 'who' => (string)($_SESSION['vr_admin'] ?? ''), 'ip' => vr_client_ip(), 'action' => $action, 'ctx' => $ctx];
    $f = vr_data_dir() . '/admin-audit.log';
    @file_put_contents($f, json_encode($line, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
    @chmod($f, 0640);
}

/** JSONL kayıt dosyasını oku (son $max satır, yeni → eski). */
function vr_log_tail(string $file, int $max = 500): array
{
    $f = vr_data_dir() . '/' . $file;
    if (!is_readable($f)) return [];
    $lines = file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $lines = array_slice($lines, -$max);
    $out = [];
    foreach (array_reverse($lines) as $l) { $r = json_decode($l, true); if (is_array($r)) $out[] = $r; }
    return $out;
}
