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

/**
 * Refuse blocked IPs site-wide. Runs from auth.php's bootstrap, so it covers
 * every page without each page opting in. CLI is exempt — the maintenance
 * scripts the workflows run must never be able to lock themselves out.
 */
function vestra_ip_guard(): void {
    if (PHP_SAPI === 'cli') return;
    $ip = vestra_client_ip();
    if ($ip === '' || !vestra_ip_blocked($ip)) return;
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    /* Terse on purpose: a blocked visitor gets no detail to tune evasion by. */
    echo '<!doctype html><meta charset="utf-8"><title>403</title><body style="font-family:sans-serif;background:#0d0d10;color:#ddd;display:grid;place-items:center;height:100vh;margin:0"><div style="text-align:center"><h1 style="font-weight:600">403</h1><p>Access from your network is not available.</p></div>';
    exit;
}

/* ── per-IP intel (country / VPN) ───────────────────────────────────────────── */

function vestra_ip_intel(string $ip): array {
    $none = ['cc' => '', 'country' => '', 'isp' => '', 'proxy' => false, 'hosting' => false];
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
    $providers[] = ['url' => 'http://ip-api.com/json/{ip}?fields=status,country,countryCode,isp,proxy,hosting', 'kind' => 'ipapi'];
    $providers[] = ['url' => 'https://ipwho.is/{ip}', 'kind' => 'ipwho'];

    $out = $none; $ok = false;
    foreach ($providers as $p) {
        $ch = curl_init(str_replace('{ip}', urlencode($ip), $p['url']));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3,
                                CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_FOLLOWLOCATION => true]);
        $raw = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($code < 200 || $code >= 300 || !is_string($raw) || $raw === '') continue;
        $d = json_decode($raw, true);
        if (!is_array($d)) continue;
        if ($p['kind'] === 'ipapi') {
            if (($d['status'] ?? '') !== 'success') continue;
            $out = ['cc' => (string)($d['countryCode'] ?? ''), 'country' => (string)($d['country'] ?? ''),
                    'isp' => mb_substr((string)($d['isp'] ?? ''), 0, 60),
                    'proxy' => !empty($d['proxy']), 'hosting' => !empty($d['hosting'])];
        } else {
            if (empty($d['success'])) continue;
            $out = ['cc' => (string)($d['country_code'] ?? ''), 'country' => (string)($d['country'] ?? ''),
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
