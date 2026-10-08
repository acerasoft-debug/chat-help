<?php
/**
 * VESTRA — MUSTERI NEREDEN GELDI (operator, 8 Eki 2026: "musterilerin nereden
 * geldigini olcmek icin kod ekle").
 *
 * 8 Eki 2026 olcumu: 233 alici hesabinin 225'inin kaynagi BILINMIYORDU -- kayitta
 * kaynak alani yoktu ve geriye donuk tek ipucu adresin lead listesinde olup
 * olmamasiydi. Erisim gunlugu kayit sayfasinin %85'inin site ICINDEN acildigini
 * gosteriyordu: ziyaretci once bir sayfaya iniyor, sonra kaydoluyor. Yani kaynagi
 * kayit aninda degil, ziyaretcinin siteye ILK INDIGI anda yakalamak gerekiyor.
 *
 * NASIL: ilk inis (first touch) ve son dis kaynakli inis (last touch) OTURUMDA
 * tutulur -- $_SESSION['vsrc'] / ['vsrc_last']. Yeni cerez YOK: oturum cerezi
 * zaten her ziyaretciye aciliyor (auth.php; 3 gun hareketsiz kalan oturum dosyasi
 * deploy-vestra'daki temizlikle siliniyor, yani ilk inis en az o kadar hatirlanir)
 * ve cerez bandi "yalnizca
 * zorunlu cerezler, takip yok" diyor; bu dosya o cumleyi yalanlamaz. Veri sunucuda
 * kalir, ucuncu bir tarafa gitmez; hesaba yalnizca KAYIT OLURSA yazilir
 * (auth_register -> signup_source).
 *
 * Kanal adlari sabit bir sozluk: raporlar (seller-products.yml acq_funnel) bunlari
 * gruplar. 'ai:*' ayri tutulur -- ChatGPT Eylul 2026'da siteye 353 sayfa
 * gonderdi ve operatorun ayrica guclendirmek istedigi kanal bu.
 */

/** Dis kaynagi kanal adina cevirir. $q: sorgu dizisi (utm_*, gclid, fbclid). */
function vestra_attr_channel(string $refHost, array $q): string {
    $us = strtolower(trim((string)($q['utm_source'] ?? '')));
    $um = strtolower(trim((string)($q['utm_medium'] ?? '')));
    /* Once ACIK isaretler: utm ve reklam tiklama kimlikleri. ChatGPT aramasi
       verdigi baglantilara kendisi utm_source=chatgpt.com ekliyor; referrer'i
       silen bir tarayicida da kanal boylece okunur. */
    if ($us !== '') {
        if (preg_match('/chatgpt|openai/', $us))             return 'ai:chatgpt';
        if (str_contains($us, 'perplexity'))                 return 'ai:perplexity';
        if (preg_match('/gemini|bard/', $us))                return 'ai:gemini';
        if (preg_match('/copilot/', $us))                    return 'ai:copilot';
        if (preg_match('/claude|anthropic/', $us))           return 'ai:claude';
        if ($um === 'email' || preg_match('/brevo|sendinblue|newsletter|mail/', $us)) return 'email';
        if (in_array($um, ['cpc', 'ppc', 'paid', 'ads'], true)) return 'ads:'.substr(preg_replace('/[^a-z0-9._-]/', '', $us), 0, 24);
        return 'utm:'.substr(preg_replace('/[^a-z0-9._-]/', '', $us), 0, 24);
    }
    if (!empty($q['gclid']) || !empty($q['gbraid']) || !empty($q['wbraid'])) return 'ads:google';
    if (!empty($q['msclkid']))                                return 'ads:bing';
    if (!empty($q['fbclid']))                                 return 'social:facebook';
    $h = strtolower(preg_replace('/^(www|m|l|lm|mobile)\./', '', $refHost));
    if ($h === '') return 'direct';
    $rules = [
        '/(^|\.)(chatgpt\.com|chat\.openai\.com|openai\.com)$/'        => 'ai:chatgpt',
        '/(^|\.)perplexity\.ai$/'                                      => 'ai:perplexity',
        '/^gemini\.google\.com$|^bard\.google\.com$/'                  => 'ai:gemini',
        '/^copilot\.microsoft\.com$/'                                => 'ai:copilot',
        '/(^|\.)claude\.ai$/'                                          => 'ai:claude',
        '/(^|\.)you\.com$|(^|\.)phind\.com$/'                          => 'ai:other',
        '/(^|\.)(sendibt\d*\.com|sendinblue\.com|brevo\.com|r\.sendibm\d*\.com)$|sendib/' => 'email',
        '/^(mail|webmail|outlook|email)\.|(^|\.)mail\.google\.com$|(^|\.)titan\.email$|mimecast|(^|\.)mail\.(yahoo|zoho|qq)\./' => 'email',
        '/(^|\.)google\.[a-z.]+$/'                                     => 'search:google',
        '/(^|\.)bing\.com$/'                                           => 'search:bing',
        '/(^|\.)(duckduckgo\.com|yahoo\.[a-z.]+|yandex\.[a-z.]+|ecosia\.org|qwant\.com|baidu\.com|naver\.com|seznam\.cz)$/' => 'search:other',
        '/(^|\.)instagram\.com$/'                                      => 'social:instagram',
        '/(^|\.)(facebook\.com|fb\.me|fb\.com|messenger\.com)$/'       => 'social:facebook',
        '/(^|\.)(linkedin\.com|lnkd\.in)$/'                            => 'social:linkedin',
        '/(^|\.)tiktok\.com$/'                                         => 'social:tiktok',
        '/(^|\.)(t\.co|x\.com|twitter\.com)$/'                         => 'social:x',
        '/(^|\.)(pinterest\.[a-z.]+|pin\.it)$/'                        => 'social:pinterest',
        '/(^|\.)(wa\.me|whatsapp\.com|t\.me|telegram\.org)$/'          => 'social:messenger',
    ];
    foreach ($rules as $re => $ch) if (preg_match($re, $h)) return $ch;
    return 'referral:'.substr($h, 0, 40);
}

/** Bu istek bir insan ziyaretcisinin sayfa acilisi mi? Bot, onizleme ve arka plan
 *  istekleri kaynak yazmaz -- yoksa ilk inisi bir tarayici botu "kapar". */
function vestra_attr_is_pageview(): bool {
    if (PHP_SAPI === 'cli') return false;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return false;
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (function_exists('vestra_is_bot') && vestra_is_bot($ua)) return false;
    if ($ua === '' || preg_match('/bot\b|bot\/|crawl|spider|slurp|preview|facebookexternalhit|embedly|monitor|curl|wget|python|headless|lighthouse|GPTBot|OAI-SearchBot|ChatGPT-User|PerplexityBot|ClaudeBot|Bytespider|Amazonbot|Applebot|YandexBot|AhrefsBot|SemrushBot/i', $ua)) return false;
    $acc = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
    return $acc === '' || str_contains($acc, 'text/html');
}

/** Ilk inisi ve son DIS kaynakli inisi oturuma yazar. Her sayfada cagrilir
 *  (auth.php); oturum yoksa sessizce hicbir sey yapmaz. */
function vestra_attr_capture(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) return;
    if (($_GET['aiw'] ?? '') === '0') $_SESSION['aiw_closed'] = 1;       // karsilama seridi kapatildi
    if (!vestra_attr_is_pageview()) return;
    $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
    $refHost = $ref !== '' ? strtolower((string)parse_url($ref, PHP_URL_HOST)) : '';
    $self = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? 'vestrasales.com')));
    $internal = $refHost !== '' && ($refHost === $self || preg_match('/(^|\.)vestrasales\.com$/', $refHost));
    $q = $_GET;
    $hasUtm = !empty($q['utm_source']) || !empty($q['gclid']) || !empty($q['gbraid']) || !empty($q['wbraid']) || !empty($q['msclkid']) || !empty($q['fbclid']);
    if ($internal && !$hasUtm) return;                                   // site ici gezinti: kaynak degil
    $row = [
        'ch'   => vestra_attr_channel($internal ? '' : $refHost, $q),
        'ref'  => $internal ? '' : substr(preg_replace('/^www\./', '', $refHost), 0, 60),
        'us'   => substr(preg_replace('/[^A-Za-z0-9._ -]/', '', (string)($q['utm_source'] ?? '')), 0, 40),
        'um'   => substr(preg_replace('/[^A-Za-z0-9._ -]/', '', (string)($q['utm_medium'] ?? '')), 0, 40),
        'uc'   => substr(preg_replace('/[^A-Za-z0-9._ -]/', '', (string)($q['utm_campaign'] ?? '')), 0, 60),
        /* Inis sayfasi: YOL + yalniz kimlik parametreleri (id/brand/cat/slug/market).
           Tam URL tutulmaz -- e-posta baglantilarindaki jetonlar oturuma yazilmasin. */
        'land' => substr((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), 0, 80)
                  .(($k = array_intersect_key($q, array_flip(['id', 'brand', 'cat', 'slug', 'market']))) ? '?'.http_build_query(array_map(fn($v) => substr((string)$v, 0, 60), $k)) : ''),
        'at'   => date('c'),
    ];
    if (empty($_SESSION['vsrc'])) $_SESSION['vsrc'] = $row;              // ilk inis: bir kez
    /* Son dokunus yalniz GERCEK bir dis kaynakta guncellenir: "dogrudan" bir
       donus, ziyaretciyi ChatGPT'den getiren ilk ziyaretin izini silmesin. */
    if ($row['ch'] !== 'direct') $_SESSION['vsrc_last'] = $row;
}

/** "Bizi nereden buldunuz?" seceneginin sabit sozlugu (kayit formu + rapor). */
function vestra_attr_how_options(): array {
    return [
        'google'   => 'Google search',
        'ai'       => 'ChatGPT or another AI assistant',
        'email'    => 'An e-mail from VESTRA',
        'social'   => 'Instagram / Facebook / LinkedIn',
        'referral' => 'Recommended by a colleague or supplier',
        'fair'     => 'Trade fair or event',
        'other'    => 'Other',
    ];
}

/** Kayit aninda hesaba yazilacak kaynak kaydi. */
function vestra_attr_for_signup(string $how = ''): array {
    $first = (array)($_SESSION['vsrc'] ?? []);
    $last  = (array)($_SESSION['vsrc_last'] ?? []);
    $how   = array_key_exists($how, vestra_attr_how_options()) ? $how : '';
    return [
        'channel' => (string)($first['ch'] ?? 'unknown'),     // ilk inis kanali -- raporun ana kirilimi
        'first'   => $first ?: null,
        'last'    => $last ?: null,
        'how'     => $how,                                    // kullanicinin kendi beyani (opsiyonel)
        'at'      => date('c'),
    ];
}

/** Yapay zeka asistanindan gelen ziyaretci mi (ilk ya da son dokunus)? */
function vestra_attr_from_ai(): bool {
    foreach (['vsrc', 'vsrc_last'] as $k) {
        if (str_starts_with((string)($_SESSION[$k]['ch'] ?? ''), 'ai:')) return true;
    }
    return false;
}

/** Panel/rapor icin kisa insan-okur etiket. */
function vestra_attr_label(string $ch): string {
    static $m = [
        'ai:chatgpt' => 'ChatGPT', 'ai:perplexity' => 'Perplexity', 'ai:gemini' => 'Gemini', 'ai:copilot' => 'Copilot',
        'ai:claude' => 'Claude', 'ai:other' => 'AI assistant', 'search:google' => 'Google', 'search:bing' => 'Bing',
        'search:other' => 'Other search', 'email' => 'E-mail', 'direct' => 'Direct', 'unknown' => '—',
        'ads:google' => 'Google Ads', 'ads:bing' => 'Bing Ads',
    ];
    if (isset($m[$ch])) return $m[$ch];
    if (str_starts_with($ch, 'social:'))   return ucfirst(substr($ch, 7));
    if (str_starts_with($ch, 'referral:')) return substr($ch, 9);
    if (str_starts_with($ch, 'utm:') || str_starts_with($ch, 'ads:')) return $ch;
    return $ch;
}

/** BU istegin kanali (gunluk ziyaret sayaci icin): dis kaynak/utm varsa o, site ici
 *  ya da kaynaksiz acilis 'direct'. */
function vestra_attr_request_channel(): string {
    $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
    $refHost = $ref !== '' ? strtolower((string)parse_url($ref, PHP_URL_HOST)) : '';
    if ($refHost !== '' && preg_match('/(^|\.)vestrasales\.com$/', $refHost)) $refHost = '';
    if ($refHost !== '' && $refHost === strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')))) $refHost = '';
    return vestra_attr_channel($refHost, $_GET);
}

/**
 * YAPAY ZEKADAN GELEN MISAFIRE KARSILAMA SERIDI (operator, 8 Eki 2026: "chatgpt icin
 * guclu yol ekle"). ChatGPT bir kullaniciya VESTRA'yi onerdiginde kullanici genellikle
 * bir urun ya da marka sayfasina iniyor ve fiyat yerine bir kilit goruyor -- neden
 * kilitli oldugunu ve ne yapmasi gerektigini ilk bakista anlamazsa geri donuyor.
 * Serit uc seyi soyler: burasi perakendecilere toptan satis platformu, kac marka
 * stokta (CANLI sayi), fiyatlar ucretsiz ticari hesapla aciliyor. Yalniz GIRIS
 * YAPMAMIS ve kanali ai:* olan ziyaretciye; kapatilinca oturum boyunca bir daha
 * gorunmez; kayit sayfasinin kendisinde gosterilmez.
 */
function vestra_ai_welcome_html(?array $user): string {
    if ($user || !empty($_SESSION['aiw_closed']) || !vestra_attr_from_ai()) return '';
    /* Yol uzerinden: SCRIPT_NAME yerel yonlendiricide yonlendiricinin kendisi oluyor. */
    $path = rtrim((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), '/');
    if (preg_match('~^/(register|login|forgot|reset)(\.php)?$~', $path)) return '';
    $ch = (string)($_SESSION['vsrc_last']['ch'] ?? ($_SESSION['vsrc']['ch'] ?? 'ai:other'));
    $who = vestra_attr_label($ch);
    $nb = 0;
    if (function_exists('vestra_seo_brands')) $nb = count(vestra_seo_brands(0));
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $close = $uri.(str_contains($uri, '?') ? '&' : '?').'aiw=0';
    $tr = fn(string $s) => function_exists('t') ? t($s) : $s;
    $msg = $nb > 0
        ? sprintf($tr('VESTRA is a B2B wholesale marketplace for retailers — %d brands in stock. Wholesale prices open with a free trade account.'), $nb)
        : $tr('VESTRA is a B2B wholesale marketplace for retailers. Wholesale prices open with a free trade account.');
    return '<style>.aiwel{background:linear-gradient(90deg,#1d1a14,#2a241a);color:#efe6d2;border-bottom:1px solid rgba(201,168,106,.35);font-size:14px}'
         . '.aiwel .wrap{display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding-top:10px;padding-bottom:10px}'
         . '.aiwel b{color:#d8bd86}.aiwel .aiw-t{flex:1;min-width:220px;line-height:1.45}'
         . '.aiwel .aiw-x{color:#b8b2a4;text-decoration:none;font-size:20px;line-height:1;padding:4px 6px}'
         . '.aiwel .aiw-x:hover{color:#fff}'
         . '@media(max-width:600px){.aiwel .wrap{position:relative;padding-right:44px}.aiwel .aiw-x{position:absolute;top:6px;right:12px}}</style>'
         . '<div class="aiwel" role="region" aria-label="'.htmlspecialchars($tr('Welcome')).'"><div class="wrap">'
         . '<span class="aiw-t">👋 <b>'.htmlspecialchars(sprintf($tr('Welcome from %s.'), $who)).'</b> '.htmlspecialchars($msg).'</span>'
         . '<a class="btn btn-p btn-sm" href="/register?type=buyer">'.htmlspecialchars($tr('Create a free trade account')).'</a>'
         . '<a class="btn btn-o btn-sm" href="/faq" style="color:#efe6d2;border-color:rgba(239,230,210,.35)">'.htmlspecialchars($tr('How it works')).'</a>'
         . '<a class="aiw-x" href="'.htmlspecialchars($close).'" aria-label="'.htmlspecialchars($tr('Close')).'">×</a>'
         . '</div></div>';
}
