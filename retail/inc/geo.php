<?php
/**
 * Ülke tanıma ve ülkeden dil seçimi
 * =================================
 * Ziyaretçinin ülkesini SUNUCUNUN/CDN'İN zaten hesapladığı başlıktan okuyoruz.
 * Kendimiz IP araması YAPMIYORUZ — ne dış servise ne yerel veritabanına.
 *
 * Neden böyle:
 *  • Dış GeoIP servisi çağırmak ziyaretçinin IP'sini üçüncü tarafa aktarmak
 *    demek. IP kişisel veridir (AB Adalet Divanı C-582/14, Breyer); aktarım
 *    rıza gerektirir ve bu sitenin tamamı bilerek rıza bandı OLMADAN uyumlu
 *    kalacak şekilde kuruldu. Tek bir GeoIP çağrısı bunu bozardı.
 *  • Ülke bilgisi zaten önümüzdeki katmanda var: Cloudflare CF-IPCountry
 *    gönderiyor, Apache mod_maxminddb/mod_geoip GEOIP_COUNTRY_CODE koyuyor,
 *    çoğu yük dengeleyici X-Geo-Country ekliyor. Bedava ve yerelde.
 *  • Hiçbiri yoksa ülke bilinmiyor sayılır ve hiçbir şey bozulmaz.
 *
 * IP'nin kendisi burada ne okunur ne yazılır ne günlüğe geçer.
 *
 * Ülke iki yerde işe yarıyor:
 *  1. Tarayıcı dili hiçbir dilimizle eşleşmediğinde dil seçimi
 *     (örn. Türkçe tarayıcıyla Almanya'dan gelen ziyaretçi → Almanca).
 *  2. Kasada kargo bölgesi ve ülke alanının önerilen değeri.
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

    return $cc = '';
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
