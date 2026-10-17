<?php
/**
 * Dil katmanı
 * -----------
 * Canlı B2B sitesiyle AYNI kural: varsayılan dil en, diğerleri ?lang=de|fr|it|es.
 * Seçim çerezde saklanır ki kullanıcı her tıklamada tekrar seçmesin.
 *
 * Sözlükte olmayan anahtar → İngilizce karşılığı → anahtarın kendisi. Yani eksik
 * çeviri sayfayı bozmaz, sadece o satır İngilizce görünür.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function vr_lang_init(): void
{
    $langs = (array)vr_config('languages', ['en']);
    $lang  = null;

    $q = strtolower(trim((string)($_GET['lang'] ?? '')));
    if ($q !== '' && in_array($q, $langs, true)) {
        $lang = $q;
        // 180 gün: dil seçimi işlevsel bir tercih, izleme amacı yok — bu yüzden
        // rıza bandına tabi değil (TDDDG §25 Abs. 2 Nr. 2).
        @setcookie('vr_lang', $lang, [
            'expires'  => time() + 15552000,
            'path'     => vr_base_url() === '' ? '/' : vr_base_url() . '/',
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }

    if ($lang === null) {
        $c = strtolower((string)($_COOKIE['vr_lang'] ?? ''));
        if ($c !== '' && in_array($c, $langs, true)) $lang = $c;
    }

    if ($lang === null) {
        // Ziyaretçinin ülkesi (inc/geo.php — CDN başlığı ya da yerel IP
        // tablosu). Ülke biliniyorsa dil oradan: o ülkenin dili sitede varsa
        // o, yoksa İngilizce. Açık seçim (?lang, çerez) her zaman önde ve
        // dil şeridinden tek tıkla değişiyor.
        require_once __DIR__ . '/geo.php';
        $cc = vr_geo_country();
        if ($cc !== '') {
            $byGeo = vr_country_lang($cc);
            $lang  = $byGeo !== '' ? $byGeo : (in_array('en', $langs, true) ? 'en' : null);
        }
    }

    if ($lang === null) {
        // Ülke bilinmiyorsa (yerel ağ, tablo yok): tarayıcının dili.
        $al = strtolower((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
        foreach (explode(',', $al) as $part) {
            $code = substr(trim(explode(';', $part)[0]), 0, 2);
            if ($code !== '' && in_array($code, $langs, true)) { $lang = $code; break; }
        }
    }

    $GLOBALS['vr_lang'] = $lang ?? (string)vr_config('primary_lang', 'en');
}

function vr_lang(): string
{
    return (string)($GLOBALS['vr_lang'] ?? vr_config('primary_lang', 'en'));
}

/** HTML lang / hreflang değeri. */
function vr_locale(?string $lang = null): string
{
    return match ($lang ?? vr_lang()) {
        'de' => 'de-DE', 'fr' => 'fr-FR', 'it' => 'it-IT', 'es' => 'es-ES',
        'ru' => 'ru-RU', 'az' => 'az-AZ', 'da' => 'da-DK', 'sv' => 'sv-SE',
        // Flamanca ayrı bir ISO dil kodu değil: Hollandaca'nın Belçika
        // varyantı. hreflang'de nl-BE veriyoruz, dosya adı nl.php.
        'nl' => 'nl-BE',
        default => 'en-US',
    };
}

function vr_dict(?string $lang = null): array
{
    static $cache = [];
    $lang = $lang ?? vr_lang();
    if (isset($cache[$lang])) return $cache[$lang];

    $file = __DIR__ . '/lang/' . preg_replace('/[^a-z]/', '', $lang) . '.php';
    $d    = is_readable($file) ? (include $file) : [];
    return $cache[$lang] = is_array($d) ? $d : [];
}

/**
 * Çeviri. t('add_to_cart') veya yer tutucularla t('n_items', ['n' => 3]).
 */
function t(string $key, array $vars = []): string
{
    $s = vr_dict()[$key] ?? null;
    if ($s === null && vr_lang() !== 'en') $s = vr_dict('en')[$key] ?? null;
    $s = (string)($s ?? $key);

    foreach ($vars as $k => $v) {
        $s = str_replace('{' . $k . '}', (string)$v, $s);
    }
    // Mağaza adı sözlükte sabit yazılmıyor, {brand} olarak duruyor: ad
    // değişirse yalnızca vr_config('brand') (ya da data/retail-settings.json)
    // değişsin, on dilde altmış cümle değil.
    if (str_contains($s, '{brand}')) {
        $s = str_replace('{brand}', (string)vr_config('brand'), $s);
    }
    return $s;
}

/** Kaçışlı çeviri — şablonlarda kısayol. */
function te(string $key, array $vars = []): string
{
    return htmlspecialchars(t($key, $vars), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Mevcut sayfanın başka dildeki URL'si (hreflang ve dil seçici için).
 * $explicit: dil seçicide birincil dil (en) de ?lang=en taşır — yoksa
 * çerezde ya da ülkeden gelen dil geri gelir ve İngilizceye geçilemezdi.
 * hreflang'de birincil dilin adresi yalın kalır (x-default ile aynı).
 */
function vr_lang_url(string $lang, bool $explicit = false): string
{
    $path  = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $parts = parse_url($path);
    $q     = [];
    if (!empty($parts['query'])) parse_str($parts['query'], $q);
    unset($q['lang']);
    if ($explicit || $lang !== vr_config('primary_lang')) $q['lang'] = $lang;

    return ($parts['path'] ?? '/') . ($q ? '?' . http_build_query($q) : '');
}

/** Tarih biçimi — locale'e göre kaba ama bağımlılıksız. */
function vr_date(int $ts, bool $withTime = false): string
{
    $fmt = match (vr_lang()) {
        'de', 'da', 'nl', 'ru', 'az' => $withTime ? 'd.m.Y, H:i' : 'd.m.Y',
        'sv'             => $withTime ? 'Y-m-d H:i' : 'Y-m-d',
        'fr', 'it', 'es' => $withTime ? 'd/m/Y H:i' : 'd/m/Y',
        default          => $withTime ? 'M j, Y g:ia' : 'M j, Y',
    };
    return date($fmt, $ts);
}
