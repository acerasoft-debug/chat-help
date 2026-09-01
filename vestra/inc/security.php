<?php
/**
 * VESTRA — visitor security layer: IP log, geo/VPN intel, IP blocking.
 *
 * Three small pieces, all file-backed under data/ (web-denied, never in git —
 * these files hold customer IPs and belong on the server only):
 *
 *   security_log.json  — the last N auth events (register / login / login_fail /
 *                        admin_login…) with IP, country and VPN flags. Capped, so
 *                        it is a rolling window, not an ever-growing archive: this
 *                        is security telemetry, not bookkeeping.
 *   ip_intel.json      — per-IP lookup cache. One HTTP call per NEW ip, then
 *                        cached for 30 days; display never triggers lookups.
 *   ip_blocks.json     — the operator's block list. Checked on every request via
 *                        vestra_ip_guard() (wired into auth.php's bootstrap, which
 *                        every page loads first).
 *
 * The geo/VPN source is ip-api.com's free endpoint by default — no key, and its
 * `proxy`/`hosting` flags are exactly the "VPN mi?" answer the operator asked
 * for. It is rate-limited and its free tier is meant for light use, which a few
 * sign-ups a day is; the URL is overridable via cfg('ipintel_url') so a paid
 * provider can be dropped in without code changes. Lookups fail SOFT: no answer
 * means "unknown", never a blocked registration.
 */

/* products.php'nin JSON yardimcilarina GUVENME: auth.php bu dosyayi her sayfada
   yukluyor ve bazi sayfalar products.php'yi hic yuklemiyor. Kendi kucuk okuma/
   yazma yardimcilarimiz var; vestra_* varsa onu kullanirlar. */
function _vsec_dir(): string {
    return function_exists('vestra_data_dir') ? vestra_data_dir() : dirname(__DIR__).'/data';
}
function _vsec_read(string $name): array {
    $f = _vsec_dir().'/'.$name;
    if (!is_readable($f)) return [];
    $d = json_decode((string)file_get_contents($f), true);
    return is_array($d) ? $d : [];
}
function _vsec_write(string $name, array $d): void {
    $dir = _vsec_dir();
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    @file_put_contents($dir.'/'.$name, json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function vestra_client_ip(): string {
    /* Shared hosting sits behind no trusted proxy of ours, so REMOTE_ADDR is the
       truth. X-Forwarded-For is attacker-controlled and is deliberately ignored —
       honouring it would let anyone dodge the block list by sending a header. */
    return (string)($_SERVER['REMOTE_ADDR'] ?? '');
}

/* ── block list ─────────────────────────────────────────────────────────────── */

function vestra_ip_blocks(): array {
    return _vsec_read('ip_blocks.json');
}

function vestra_save_ip_blocks(array $list): void {
    _vsec_write('ip_blocks.json', array_values($list));
}

/** Exact IP, "1.2.3." prefix, or IPv4 CIDR ("1.2.3.0/24"). IPv6: exact only. */
function vestra_ip_matches(string $ip, string $rule): bool {
    $rule = trim($rule);
    if ($rule === '' || $ip === '') return false;
    if (strcasecmp($ip, $rule) === 0) return true;
    if (str_ends_with($rule, '.') && str_starts_with($ip, $rule)) return true;
    if (strpos($rule, '/') !== false && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        [$net, $bits] = explode('/', $rule, 2);
        $bits = (int)$bits;
        if ($bits >= 0 && $bits <= 32 && filter_var($net, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $mask = $bits === 0 ? 0 : (~0 << (32 - $bits));
            return ((ip2long($ip) ^ ip2long($net)) & $mask) === 0;
        }
    }
    return false;
}

function vestra_ip_blocked(string $ip): ?array {
    foreach (vestra_ip_blocks() as $b) {
        if (vestra_ip_matches($ip, (string)($b['ip'] ?? ''))) return $b;
    }
    return null;
}

/* ── country block ───────────────────────────────────────────────────────────
 * Bir ULKENIN tamamini siteden kes. Tek tek IP yasaklamaktan ayri bir liste:
 * IP kurallari belirli bir saldirganı hedefler, bu ise ticari bir karardir
 * (o pazarda satmiyoruz / kanal orada zaten kapali).
 *
 * KENDINI DISARIDA BIRAKMA riski bu ozelligin asil tehlikesi -- operator
 * Turkiye'den baglaniyor ve "TR'yi engelle" dedigi anda kendi panelini de
 * kapatabilirdi. Uc ayri kacis yolu var ve UCU DE ayni anda calisir:
 *
 *   1. /admin YOLU MUAF. Kapi oturum baslamadan once kosuyor (auth.php:14),
 *      yani "bu admin mi" diye session'a bakamiyor. Yol muafiyeti sessiondan
 *      once bilinebilen tek olcut. Panelin kendi parolasi + hiz siniri zaten
 *      duruyor; ulke engeli bir guvenlik siniri degil, ticari bir kapi.
 *   2. IZIN LISTESI. Operatorun kendi IP'si (ya da 88.230. gibi bir onek)
 *      country_allow_ips'e yazilir ve ulke ne olursa olsun gecer.
 *   3. ULKE COZULEMEZSE ENGELLEME YOK. vestra_ip_intel() cografi API'ye
 *      soruyor; API duserse cc BOS doner. Bos cc'yi "engelle" saymak, saglayici
 *      bir dakikaligina bayildiginda TUM DUNYAYA 403 basmak olurdu.
 */
function vestra_country_blocks(): array {
    $d = _vsec_read('country_blocks.json');
    $cc = [];
    foreach ((array)($d['countries'] ?? []) as $c) {
        $c = strtoupper(trim((string)$c));
        if (preg_match('/^[A-Z]{2}$/', $c)) $cc[$c] = true;
    }
    return ['countries' => $cc, 'allow_ips' => array_values(array_filter(
        array_map('trim', (array)($d['allow_ips'] ?? [])), fn($s) => $s !== ''))];
}

function vestra_save_country_blocks(array $countries, array $allowIps): void {
    $cc = [];
    foreach ($countries as $c) {
        $c = strtoupper(trim((string)$c));
        if (preg_match('/^[A-Z]{2}$/', $c)) $cc[] = $c;
    }
    _vsec_write('country_blocks.json', [
        'countries' => array_values(array_unique($cc)),
        'allow_ips' => array_values(array_unique(array_filter(array_map('trim', $allowIps), fn($s) => $s !== ''))),
    ]);
}

/** Bu istek ulke kuralina takiliyor mu? Kacis yollari burada, tek yerde. */
function vestra_country_blocked(string $ip, string $path = ''): bool {
    $cfg = vestra_country_blocks();
    if (!$cfg['countries']) return false;                       // liste bos -> kapali degil

    // (1) Panel her zaman acik: kapi session'dan once kosuyor, yol tek olcut.
    if ($path === '') $path = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    if (str_starts_with($path, '/admin')) return false;

    // (2) Izin listesindeki IP ulkeden bagimsiz gecer.
    foreach ($cfg['allow_ips'] as $rule) if (vestra_ip_matches($ip, (string)$rule)) return false;

    // (3) Ulke COZULEMEZSE engelleme yok -- API dustugunde herkesi kesmemek icin.
    $cc = strtoupper((string)(vestra_ip_intel($ip, 1)['cc'] ?? ''));
    if ($cc === '') return false;

    return isset($cfg['countries'][$cc]);
}

/**
 * Refuse blocked IPs site-wide. Runs from auth.php's bootstrap, so it covers
 * every page without each page opting in. CLI is exempt — the maintenance
 * scripts the workflows run must never be able to lock themselves out.
 */
function vestra_ip_guard(): void {
    if (PHP_SAPI === 'cli') return;
    $ip = vestra_client_ip();
    if ($ip === '') return;
    if (!vestra_ip_blocked($ip) && !vestra_country_blocked($ip)) return;
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    /* Terse on purpose: a blocked visitor gets no detail to tune evasion by. */
    echo '<!doctype html><meta charset="utf-8"><title>403</title><body style="font-family:sans-serif;background:#0d0d10;color:#ddd;display:grid;place-items:center;height:100vh;margin:0"><div style="text-align:center"><h1 style="font-weight:600">403</h1><p>Access from your network is not available.</p></div>';
    exit;
}

/* ── per-IP intel (country / VPN) ───────────────────────────────────────────── */

/**
 * $timeout: how long a NEW ip may take. Page-view tracking passes a short one —
 * a visitor must never sit waiting on a geo API. Cached ips cost nothing either
 * way, so a returning visitor never pays at all.
 */
function vestra_ip_intel(string $ip, int $timeout = 3): array {
    $none = ['cc' => '', 'country' => '', 'city' => '', 'region' => '', 'isp' => '', 'proxy' => false, 'hosting' => false];
    if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) return $none;
    // Private/loopback ranges have no geo and the API rejects them.
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return $none;

    $cache = _vsec_read('ip_intel.json');
    $hit = $cache[$ip] ?? null;
    if (is_array($hit) && (time() - (int)($hit['ts'] ?? 0)) < 30 * 86400) return $hit + $none;

    /* Iki saglayici, sirayla. Tek kaynaga bagli kalmak "IP tespiti kesinlikle
       calissin" sartiyla bagdasmiyor: ip-api ucretsiz uctan yalnizca HTTP
       konusuyor ve dakikada 45 istekte kotaya giriyor; o kapi kapandigi anda
       ulke bilgisi sessizce bosalirdi. ipwho.is HTTPS ve kotasiz, ama VPN
       bayragini vermiyor -- bu yuzden ikinci sirada: ulkeyi kurtarir, VPN
       tespitini ilk saglayici yapar. */
    $providers = [];
    $custom = (string)(function_exists('vestra_cfg') ? vestra_cfg('ipintel_url', '') : '');
    if ($custom !== '') $providers[] = ['url' => $custom, 'kind' => 'ipapi'];
    $providers[] = ['url' => 'http://ip-api.com/json/{ip}?fields=status,country,countryCode,city,regionName,isp,proxy,hosting', 'kind' => 'ipapi'];
    $providers[] = ['url' => 'https://ipwho.is/{ip}', 'kind' => 'ipwho'];

    $out = $none; $ok = false;
    foreach ($providers as $p) {
        $ch = curl_init(str_replace('{ip}', urlencode($ip), $p['url']));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout,
                                CURLOPT_CONNECTTIMEOUT => $timeout, CURLOPT_FOLLOWLOCATION => true]);
        $raw = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($code < 200 || $code >= 300 || !is_string($raw) || $raw === '') continue;
        $d = json_decode($raw, true);
        if (!is_array($d)) continue;
        if ($p['kind'] === 'ipapi') {
            if (($d['status'] ?? '') !== 'success') continue;
            $out = ['cc' => (string)($d['countryCode'] ?? ''), 'country' => (string)($d['country'] ?? ''),
                    'city' => mb_substr((string)($d['city'] ?? ''), 0, 40),
                    'region' => mb_substr((string)($d['regionName'] ?? ''), 0, 40),
                    'isp' => mb_substr((string)($d['isp'] ?? ''), 0, 60),
                    'proxy' => !empty($d['proxy']), 'hosting' => !empty($d['hosting'])];
        } else {
            if (empty($d['success'])) continue;
            $out = ['cc' => (string)($d['country_code'] ?? ''), 'country' => (string)($d['country'] ?? ''),
                    'city' => mb_substr((string)($d['city'] ?? ''), 0, 40),
                    'region' => mb_substr((string)($d['region'] ?? ''), 0, 40),
                    'isp' => mb_substr((string)($d['connection']['isp'] ?? ''), 0, 60),
                    /* ipwho.is proxy/hosting bayragi vermiyor: "false" yazmak yerine
                       false biraktik -- bilmemekle "VPN degil" demek ayni sey degil. */
                    'proxy' => false, 'hosting' => false];
        }
        if (($out['cc'] ?? '') !== '') { $ok = true; break; }
    }
    if (!$ok) error_log('[VESTRA ipintel] hicbir saglayici cevap vermedi: '.$ip);
    /* Failures are cached too (as empty, with a timestamp) — otherwise an
       unreachable API would be re-asked on every request from the same IP.
       Basarisizlik daha KISA sure tutuluyor: gecici bir kesintinin sonucunu
       30 gun boyunca "ulke bilinmiyor" diye tasimak, tespiti kalici olarak
       kapatmak olurdu. */
    $out['ts'] = $ok ? time() : time() - (30 * 86400 - 1800);
    $cache[$ip] = $out;
    if (count($cache) > 3000) $cache = array_slice($cache, -2000, null, true);
    _vsec_write('ip_intel.json', $cache);
    return $out;
}

/* ── event log ──────────────────────────────────────────────────────────────── */

/**
 * Record one auth event. $event: register | login_ok | login_fail | admin_ok |
 * admin_fail | reset_request … Keep the vocabulary small; the admin table shows
 * these verbatim.
 */
function vestra_sec_log(string $event, string $email = '', string $uid = ''): void {
    $ip = vestra_client_ip();
    $intel = vestra_ip_intel($ip);
    $log = _vsec_read('security_log.json');
    $log[] = [
        'ts'      => date('c'),
        'event'   => $event,
        'email'   => mb_substr($email, 0, 80),
        'uid'     => $uid,
        'ip'      => $ip,
        'cc'      => $intel['cc'],
        'country' => $intel['country'],
        'city'    => $intel['city'],
        'isp'     => $intel['isp'],
        'vpn'     => !empty($intel['proxy']) || !empty($intel['hosting']),
        'ua'      => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120),
    ];
    if (count($log) > 800) $log = array_slice($log, -800);
    _vsec_write('security_log.json', $log);
}

/**
 * Declared-country name → ISO code, for the "declared vs. registration IP"
 * comparison in the admin. Deliberately small: only countries this platform
 * actually deals with. Unknown names return '' and the caller stays SILENT —
 * an unmappable declaration is uncertainty, not evidence of a mismatch.
 */
function vestra_cc_of_country(string $name): string {
    static $map = [
        'germany'=>'DE','deutschland'=>'DE','austria'=>'AT','switzerland'=>'CH',
        'netherlands'=>'NL','belgium'=>'BE','france'=>'FR','italy'=>'IT','italia'=>'IT',
        'spain'=>'ES','españa'=>'ES','portugal'=>'PT','ireland'=>'IE',
        'united kingdom'=>'GB','uk'=>'GB','great britain'=>'GB','england'=>'GB',
        'poland'=>'PL','czechia'=>'CZ','czech republic'=>'CZ','greece'=>'GR',
        'sweden'=>'SE','denmark'=>'DK','norway'=>'NO','finland'=>'FI',
        'united states'=>'US','usa'=>'US','united states of america'=>'US',
        'canada'=>'CA','mexico'=>'MX','turkey'=>'TR','türkiye'=>'TR',
        'japan'=>'JP','south korea'=>'KR','korea'=>'KR','china'=>'CN',
        'australia'=>'AU','uae'=>'AE','united arab emirates'=>'AE',
        'russia'=>'RU','ukraine'=>'UA','bulgaria'=>'BG','romania'=>'RO',
        'hungary'=>'HU','croatia'=>'HR','slovenia'=>'SI','slovakia'=>'SK',
        'azerbaijan'=>'AZ',
    ];
    return $map[strtolower(trim($name))] ?? '';
}

/**
 * Which country's rules apply to THIS visitor?
 *
 * Order matters and is not arbitrary. The country the account holder typed on
 * the registration form wins, because a trade licence is a legal instrument of
 * the country the business is registered in — not of the country the person
 * happens to be sitting in today. Only when nothing is declared do we fall back
 * to where the connection comes from, and the registration IP is preferred over
 * the live one: a German shop signing in from a holiday in Spain is still German.
 *
 * Returns an ISO-3166 alpha-2 code, or '' when nothing is known — callers must
 * treat '' as "use the neutral wording", never as a guess.
 */
function vestra_visitor_cc(?array $acc = null): string {
    if (is_array($acc)) {
        $cc = vestra_cc_of_country((string)($acc['country'] ?? ''));
        if ($cc !== '') return $cc;
        $cc = strtoupper(trim((string)($acc['reg_cc'] ?? '')));
        if ($cc !== '') return $cc;
    }
    return strtoupper(vestra_ip_intel(vestra_client_ip())['cc'] ?? '');
}

/* ── visitor traffic ─────────────────────────────────────────────────────────
 * "Siteye kac kisi girmis" — daily/weekly/monthly, plus who is on the site now.
 *
 * Deliberately NOT a log of every request. A per-hit append on shared hosting is
 * both slow and lossy under concurrency, and nobody needs the millionth row; what
 * the operator asks is a COUNT. So each day gets its own small counter file
 * (data/visits/YYYY-MM-DD.json) which is read, incremented and written under an
 * exclusive lock — one tiny file per day instead of one growing file forever.
 *
 * "Online now" is a separate short-lived map (data/visits_live.json) holding one
 * entry per visitor for five minutes. Entries expire by time, so it never grows.
 *
 * A visitor is identified by a SALTED HASH of ip + user-agent, never by a
 * tracking cookie and never by the raw ip in the live map: enough to tell two
 * people apart for five minutes, not enough to be a profile.
 */

function vestra_visits_dir(): string {
    $d = _vsec_dir().'/visits';
    if (!is_dir($d)) @mkdir($d, 0755, true);
    return $d;
}

/** Crawlers are not customers. Counting them turns "how many people came" into a lie. */
function vestra_is_bot(string $ua): bool {
    if ($ua === '') return true;   // no UA at all is a script, not a browser
    return (bool)preg_match('/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|headless|curl|wget|python-requests|monitor|uptime|pingdom|semrush|ahrefs|mj12|dotbot|petalbot|gptbot|claudebot|ccbot/i', $ua);
}

/**
 * Count one page view. Called from auth.php's bootstrap, so every page counts
 * without opting in — the same reason the ip guard lives there.
 */
function vestra_track_visit(): void {
    if (PHP_SAPI === 'cli') return;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return;   // form posts are not visits
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (vestra_is_bot($ua)) return;
    $path = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    // The operator's own panel is not traffic.
    if (str_starts_with($path, '/admin')) return;
    $ip = vestra_client_ip();
    if ($ip === '') return;

    /* Short timeout on purpose: a first-time visitor pays ~200 ms once per 30 days
       (measured on this server), and if both providers were down they would wait
       at most 1.2 s rather than 6. Country stats are worth that; a slow page is not. */
    $intel = vestra_ip_intel($ip, 1);
    $cc    = (string)($intel['cc'] ?? '');
    $city  = (string)($intel['city'] ?? '');

    /* Unique = one per browser session per day. The session already exists (auth.php
       started it), so this needs no extra cookie and no stored list of hashes. */
    $today = date('Y-m-d');
    $isNew = (($_SESSION['v_day'] ?? '') !== $today);
    if ($isNew) $_SESSION['v_day'] = $today;

    $f = vestra_visits_dir().'/'.$today.'.json';
    $fh = @fopen($f, 'c+');
    if ($fh) {
        if (flock($fh, LOCK_EX)) {
            $raw = stream_get_contents($fh);
            $d = $raw !== '' ? json_decode($raw, true) : null;
            if (!is_array($d)) $d = ['hits' => 0, 'uniq' => 0, 'cc' => [], 'city' => [], 'pages' => []];
            $d['hits'] = (int)($d['hits'] ?? 0) + 1;
            if ($isNew) {
                $d['uniq'] = (int)($d['uniq'] ?? 0) + 1;
                if ($cc !== '')   $d['cc'][$cc] = (int)($d['cc'][$cc] ?? 0) + 1;
                if ($city !== '') { $k = ($cc !== '' ? $cc.' · ' : '').$city; $d['city'][$k] = (int)($d['city'][$k] ?? 0) + 1; }
            }
            $pk = $path !== '' ? $path : '/';
            $d['pages'][$pk] = (int)($d['pages'][$pk] ?? 0) + 1;
            /* Keep the buckets from growing without bound on a busy day. */
            if (count($d['pages']) > 200) { arsort($d['pages']); $d['pages'] = array_slice($d['pages'], 0, 150, true); }
            if (count($d['city'])  > 400) { arsort($d['city']);  $d['city']  = array_slice($d['city'],  0, 300, true); }
            ftruncate($fh, 0); rewind($fh);
            fwrite($fh, json_encode($d, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
            fflush($fh); flock($fh, LOCK_UN);
        }
        fclose($fh);
    }

    /* Online now. Salted so the file is not a list of who visited from where:
       the salt lives only on the server and the hash cannot be reversed to an ip.
       Oku-degistir-yaz arasinda kilit yok: iki istek ayni ani yakalarsa biri
       otekinin satirini ezebilir. Gunluk sayaclar icin bunu goze alamazdik (orada
       flock var), ama bu harita bes dakikada bir kendini yeniliyor -- en kotusu
       bir ziyaretcinin birkac saniye "sitede" gorunmemesi. */
    $salt = (string)(function_exists('vestra_cfg') ? vestra_cfg('visit_salt', '') : '');
    if ($salt === '') $salt = 'vestra-live';
    $key  = substr(hash('sha256', $salt.'|'.$ip.'|'.$ua), 0, 16);
    $live = _vsec_read('visits_live.json');
    $now  = time();
    foreach ($live as $k => $v) { if ($now - (int)($v['ts'] ?? 0) > 300) unset($live[$k]); }
    $live[$key] = ['ts' => $now, 'cc' => $cc, 'city' => $city,
                   'path' => mb_substr($path, 0, 60),
                   'uid'  => (string)($_SESSION['uid'] ?? '')];
    if (count($live) > 500) { uasort($live, fn($a,$b)=>($b['ts']??0)<=>($a['ts']??0)); $live = array_slice($live, 0, 400, true); }
    _vsec_write('visits_live.json', $live);
}

/** One day's counters. Missing day = all zeroes, never a warning. */
function vestra_visits_day(string $ymd): array {
    $f = vestra_visits_dir().'/'.$ymd.'.json';
    $d = is_readable($f) ? json_decode((string)file_get_contents($f), true) : null;
    if (!is_array($d)) $d = [];
    return $d + ['hits' => 0, 'uniq' => 0, 'cc' => [], 'city' => [], 'pages' => []];
}

/** Totals over the last $days days (today included). */
function vestra_visits_range(int $days): array {
    $hits = 0; $uniq = 0; $cc = []; $city = []; $pages = []; $series = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $ymd = date('Y-m-d', strtotime("-{$i} days"));
        $d = vestra_visits_day($ymd);
        $hits += (int)$d['hits']; $uniq += (int)$d['uniq'];
        $series[$ymd] = ['hits' => (int)$d['hits'], 'uniq' => (int)$d['uniq']];
        foreach ((array)$d['cc']    as $k => $n) $cc[$k]    = ($cc[$k]    ?? 0) + (int)$n;
        foreach ((array)$d['city']  as $k => $n) $city[$k]  = ($city[$k]  ?? 0) + (int)$n;
        foreach ((array)$d['pages'] as $k => $n) $pages[$k] = ($pages[$k] ?? 0) + (int)$n;
    }
    arsort($cc); arsort($city); arsort($pages);
    return ['hits' => $hits, 'uniq' => $uniq, 'cc' => $cc, 'city' => $city, 'pages' => $pages, 'series' => $series];
}

/** Who is on the site right now (last 5 minutes). */
function vestra_visits_live(): array {
    $live = _vsec_read('visits_live.json');
    $now = time();
    foreach ($live as $k => $v) { if ($now - (int)($v['ts'] ?? 0) > 300) unset($live[$k]); }
    uasort($live, fn($a, $b) => ($b['ts'] ?? 0) <=> ($a['ts'] ?? 0));
    return $live;
}
