<?php
/**
 * Ülke tanıma ve ülkeden dil / para birimi seçimi
 * ===============================================
 * Ülke iki kaynaktan, bu sırayla:
 *  1. Önümüzdeki katmanın başlığı (Cloudflare CF-IPCountry, mod_maxminddb
 *     GEOIP_COUNTRY_CODE, X-Geo-Country …) — varsa.
 *  2. YEREL IP→ülke tablosu (data/geoip-v4.bin / -v6.bin, DB-IP Lite, aylık;
 *     tools/geoip-update.php her yayında tazeler). Arama tamamen bu
 *     sunucuda, dosyada ikili arama: IP hiçbir dış servise gitmez, hiçbir
 *     yere yazılmaz, günlüğe geçmez — yalnızca bu isteğin belleğinde ülke
 *     koduna çevrilir. (Dış GeoIP servisi IP'yi üçüncü tarafa aktarmak
 *     olurdu: AB Adalet Divanı C-582/14 Breyer — bilerek yapılmıyor.)
 *  Hiçbiri sonuç vermezse ülke bilinmiyor sayılır; hiçbir şey bozulmaz.
 *
 * Ülke şunları belirler (ziyaretçi açıkça seçmediyse — seçimi her zaman önde):
 *  • Dil: ülkenin dili sitede varsa o, yoksa İngilizce (inc/i18n.php).
 *  • Gösterge para birimi (inc/fx.php). Bağlayıcı fiyat EUR kalır.
 *  • Kasada kargo bölgesi ve ülke alanının önerilen değeri.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Öndeki katmanın bildirdiği ülke kodu (ISO-3166-1 alfa-2, büyük harf).
 * Bilinmiyorsa ''. Sonuç istek boyunca önbelleklenir.
 */
function vr_geo_country(): string
{
    static $cc = null;
    if ($cc !== null) return $cc;

    // Sıra önemli: en spesifik olan önce. Cloudflare bilinmeyen adres için
    // "XX", Tor çıkışları için "T1" gönderir — ikisi de ülke değildir.
    $headers = [
        'HTTP_CF_IPCOUNTRY',        // Cloudflare
        'HTTP_X_GEO_COUNTRY',       // yaygın yük dengeleyici başlığı
        'HTTP_X_COUNTRY_CODE',
        'HTTP_X_APPENGINE_COUNTRY', // Google App Engine
        'HTTP_FASTLY_CLIENT_COUNTRY',
        'GEOIP_COUNTRY_CODE',       // Apache mod_geoip / mod_maxminddb
        'MM_COUNTRY_CODE',
    ];
    foreach ($headers as $h) {
        $v = strtoupper(trim((string)($_SERVER[$h] ?? '')));
        if (preg_match('/^[A-Z]{2}$/', $v) && $v !== 'XX' && $v !== 'T1') {
            return $cc = $v;
        }
    }

    // Geliştirme/test için: VR_GEO_COUNTRY=DE php -S ...
    $env = strtoupper(trim((string)getenv('VR_GEO_COUNTRY')));
    if (preg_match('/^[A-Z]{2}$/', $env)) return $cc = $env;

    // Yerel tablo. Yalnız REMOTE_ADDR: X-Forwarded-For istemcinin yazdığı
    // bir başlık; ona güvenmenin tek sonucu kendi ülkesini "yanıltmak" olur
    // ama yine de doğrudan bağlantının adresi esas.
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    return $cc = $ip !== '' ? vr_geoip_lookup($ip) : '';
}

/**
 * IP → ülke kodu, yerel tablodan (bkz. tools/geoip-update.php). Ağ yok.
 * Kayıtlar başlangıca göre sıralı; ikili arama ~20 küçük okuma.
 * Tablo yoksa, adres özel/ayrılmış aralıktaysa ya da bulunamazsa ''.
 */
function vr_geoip_lookup(string $ip): string
{
    $bin = @inet_pton($ip);
    if ($bin === false) return '';
    // IPv4-eşlemeli IPv6 (::ffff:a.b.c.d) → IPv4
    if (strlen($bin) === 16 && str_starts_with($bin, str_repeat("\0", 10) . "\xff\xff")) $bin = substr($bin, 12);
    $kl   = strlen($bin);
    $file = vr_data_dir() . ($kl === 4 ? '/geoip-v4.bin' : '/geoip-v6.bin');
    $rec  = 2 * $kl + 2;
    $size = @filesize($file);
    if (!$size || $size % $rec !== 0) return '';
    $h = @fopen($file, 'rb');
    if (!$h) return '';
    $lo = 0; $hi = intdiv($size, $rec) - 1; $out = '';
    while ($lo <= $hi) {
        $mid = ($lo + $hi) >> 1;
        fseek($h, $mid * $rec);
        $r = (string)fread($h, $rec);
        if (strlen($r) !== $rec) break;
        if (strcmp($bin, substr($r, 0, $kl)) < 0)        $hi = $mid - 1;
        elseif (strcmp($bin, substr($r, $kl, $kl)) > 0)  $lo = $mid + 1;
        else { $out = substr($r, 2 * $kl, 2); break; }
    }
    fclose($h);
    return preg_match('/^[A-Z]{2}$/', $out) ? $out : '';
}

/**
 * Ülke → dil. Yalnızca sitenin konuştuğu 10 dile eşleniyor; listede olmayan
 * ülke '' döndürür ve çağıran varsayılana düşer.
 *
 * Çok dilli ülkelerde çoğunluk dili seçiliyor (BE → Flamanca/nl, CH → Almanca).
 * Ziyaretçi altbilgideki şeritten tek tıkla değiştirebiliyor ve seçim 180 gün
 * çerezde kalıyor — yani tahmin yanlışsa bedeli bir tık.
 */
function vr_country_lang(string $cc): string
{
    static $map = [
        // Almanca
        'DE' => 'de', 'AT' => 'de', 'CH' => 'de', 'LI' => 'de', 'LU' => 'de',
        // Fransızca
        'FR' => 'fr', 'MC' => 'fr', 'WF' => 'fr', 'NC' => 'fr', 'PF' => 'fr',
        // İtalyanca
        'IT' => 'it', 'SM' => 'it', 'VA' => 'it',
        // İspanyolca
        'ES' => 'es', 'MX' => 'es', 'AR' => 'es', 'CL' => 'es', 'CO' => 'es',
        'PE' => 'es', 'UY' => 'es', 'PY' => 'es', 'BO' => 'es', 'EC' => 'es',
        'VE' => 'es', 'CR' => 'es', 'PA' => 'es', 'GT' => 'es', 'HN' => 'es',
        'SV' => 'es', 'NI' => 'es', 'DO' => 'es', 'CU' => 'es',
        // Flamanca / Felemenkçe
        'NL' => 'nl', 'BE' => 'nl', 'SR' => 'nl', 'AW' => 'nl', 'CW' => 'nl',
        // Danca
        'DK' => 'da', 'GL' => 'da', 'FO' => 'da',
        // İsveççe
        'SE' => 'sv', 'AX' => 'sv',
        // Rusça
        'RU' => 'ru', 'BY' => 'ru', 'KZ' => 'ru', 'KG' => 'ru', 'TJ' => 'ru',
        'UZ' => 'ru', 'TM' => 'ru', 'AM' => 'ru', 'MD' => 'ru',
        // Azerbaycanca
        'AZ' => 'az',
    ];

    $cc = strtoupper($cc);
    if (!isset($map[$cc])) return '';

    // Yapılandırmada kapatılmış bir dile yönlendirme yapma.
    $langs = (array)vr_config('languages', ['en']);
    return in_array($map[$cc], $langs, true) ? $map[$cc] : '';
}

/**
 * Ülkeden kargo bölgesi. Kasadaki ülke alanı ve ürün sayfasındaki kargo satırı
 * için önerilen değer; müşteri değiştirebiliyor, fiyat her hâlükârda sunucuda
 * seçilen ülkeye göre yeniden hesaplanıyor.
 */
function vr_country_zone(string $cc): string
{
    $cc = strtoupper($cc);
    if ($cc === '') return 'de';
    if (in_array($cc, (array)vr_config('shipping_countries_de', []), true)) return 'de';
    if (in_array($cc, (array)vr_config('shipping_countries_eu', []), true)) return 'eu';
    if (in_array($cc, (array)vr_config('shipping_countries_ch', []), true)) return 'ch';
    return 'world';
}

/**
 * Ülke adı — ziyaretçinin dilinde.
 * ----------------------------------
 * Kasada, sepette ve kargo sayfasında "AT · BE · BG" gibi ham ISO kodları
 * görünüyordu. Lüks bir mağazada ülkenin adı yazılır.
 *
 * intl uzantısı varsa ICU'dan okunur (her dilde doğru ad, bakım yok). Yoksa
 * İngilizce yedek tablo: gönderdiğimiz 45 pazar. Tabloda olmayan bir kod
 * olduğu gibi döner — yanlış bir ad uydurmaktansa kod görünsün.
 */
const VR_COUNTRY_EN = [
    'AE' => 'United Arab Emirates', 'AT' => 'Austria', 'AU' => 'Australia', 'BE' => 'Belgium',
    'BG' => 'Bulgaria', 'CA' => 'Canada', 'CH' => 'Switzerland', 'CY' => 'Cyprus', 'CZ' => 'Czechia',
    'DE' => 'Germany', 'DK' => 'Denmark', 'EE' => 'Estonia', 'ES' => 'Spain', 'FI' => 'Finland',
    'FR' => 'France', 'GB' => 'United Kingdom', 'GR' => 'Greece', 'HK' => 'Hong Kong', 'HR' => 'Croatia',
    'HU' => 'Hungary', 'IE' => 'Ireland', 'IL' => 'Israel', 'IT' => 'Italy', 'JP' => 'Japan',
    'KR' => 'South Korea', 'KW' => 'Kuwait', 'LI' => 'Liechtenstein', 'LT' => 'Lithuania',
    'LU' => 'Luxembourg', 'LV' => 'Latvia', 'MT' => 'Malta', 'NL' => 'Netherlands', 'NO' => 'Norway',
    'NZ' => 'New Zealand', 'PL' => 'Poland', 'PT' => 'Portugal', 'QA' => 'Qatar', 'RO' => 'Romania',
    'SA' => 'Saudi Arabia', 'SE' => 'Sweden', 'SG' => 'Singapore', 'SI' => 'Slovenia', 'SK' => 'Slovakia',
    'TR' => 'Türkiye', 'US' => 'United States',
];

function vr_country_name(string $cc): string
{
    $cc = strtoupper(trim($cc));
    if (!preg_match('/^[A-Z]{2}$/', $cc)) return $cc;

    static $cache = [];
    $key = vr_lang() . '|' . $cc;
    if (isset($cache[$key])) return $cache[$key];

    $name = '';
    if (class_exists('Locale')) {
        $name = (string)\Locale::getDisplayRegion('-' . $cc, vr_locale());
        // ICU bilmediği kodu olduğu gibi döndürür; o zaman yedeğe düş.
        if ($name === $cc) $name = '';
    }
    if ($name === '') $name = VR_COUNTRY_EN[$cc] ?? $cc;

    return $cache[$key] = $name;
}

/**
 * <select> için kod ⇒ ad listesi, ziyaretçinin diline göre alfabetik.
 * Collator varsa yerel sıralama (Ö, Ü, É doğru yere düşer).
 */
function vr_country_options(array $codes): array
{
    $out = [];
    foreach ($codes as $cc) {
        $cc = strtoupper((string)$cc);
        if ($cc !== '') $out[$cc] = vr_country_name($cc);
    }
    if (class_exists('Collator')) {
        $col = new \Collator(vr_locale());
        uasort($out, static fn(string $a, string $b): int => (int)$col->compare($a, $b));
    } else {
        uasort($out, 'strcasecmp');
    }
    return $out;
}
