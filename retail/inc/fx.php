<?php

/**
 * GÖSTERGE PARA BİRİMİ
 * --------------------
 * Dünyanın her yerinden alıcı için fiyatın yanında kendi para biriminde
 * yaklaşık bir karşılık: "185,00 € · ≈ $213". Bağlayıcı fiyat EUR'dur ve
 * kasada EUR çekilir (PAngV); karşılık yalnızca gösterge ve öyle etiketli.
 *
 * Kurlar data/fx-rates.json'da, tarihli ve kaynaklı. 45 günden eskiyse
 * hiçbir şey gösterilmiyor: bayat bir kur, kur yokluğundan kötüdür.
 *
 * Para birimi seçimi: ?cur=USD ile açıkça (çerezle kalıcı) → yoksa ziyaretçinin
 * ülkesinden (inc/geo.php) → yoksa hiç. EUR bölgesindeki ziyaretçi ikinci bir
 * fiyat görmüyor; ona gürültü olurdu.
 */

declare(strict_types=1);

require_once __DIR__ . '/geo.php';

const VR_FX_MAX_AGE_DAYS = 45;

/** Ülke → gösterge para birimi. Yalnızca kur tablosunda olan birimler. */
function vr_fx_country_currency(string $cc): string
{
    static $map = [
        'US' => 'USD', 'PR' => 'USD',
        'GB' => 'GBP', 'JE' => 'GBP', 'GG' => 'GBP', 'IM' => 'GBP',
        'CH' => 'CHF', 'LI' => 'CHF',
        'AE' => 'AED',
        'SA' => 'SAR',
        'HK' => 'HKD',
    ];
    return $map[strtoupper($cc)] ?? '';
}

/** Kur tablosu; eski ya da bozuksa boş dizi. */
function vr_fx_table(): array
{
    static $t = null;
    if ($t !== null) return $t;
    $raw = vr_store_read('fx-rates.json', []);
    $asOf = strtotime((string)($raw['as_of'] ?? '')) ?: 0;
    if ($asOf <= 0 || (time() - $asOf) > VR_FX_MAX_AGE_DAYS * 86400) return $t = [];
    $rates = [];
    foreach ((array)($raw['rates'] ?? []) as $k => $v) {
        $k = strtoupper((string)$k);
        if (preg_match('/^[A-Z]{3}$/', $k) && is_numeric($v) && (float)$v > 0) $rates[$k] = (float)$v;
    }
    return $t = $rates ? ['as_of' => date('Y-m-d', $asOf), 'source' => (string)($raw['source'] ?? ''), 'rates' => $rates] : [];
}

/** Seçilebilir para birimleri (tablodakiler), gösterim sırasıyla. */
function vr_fx_currencies(): array
{
    return array_keys(vr_fx_table()['rates'] ?? []);
}

/** Ziyaretçinin gösterge para birimi ya da ''. */
function vr_fx_currency(): string
{
    static $cur = null;
    if ($cur !== null) return $cur;

    $avail = vr_fx_currencies();
    if (!$avail) return $cur = '';

    // Açık seçim: ?cur=USD (ya da ?cur=EUR "ikinci fiyat istemiyorum").
    if (isset($_GET['cur'])) {
        $pick = strtoupper(trim((string)$_GET['cur']));
        if ($pick === 'EUR' || in_array($pick, $avail, true)) {
            // Dil çerezinin aynısı: işlevsel tercih, izleme değil (TDDDG §25/2/2).
            @setcookie('vr_cur', $pick, [
                'expires'  => time() + 15552000,
                'path'     => vr_base_url() === '' ? '/' : vr_base_url() . '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            $_COOKIE['vr_cur'] = $pick;
        }
    }
    $c = strtoupper(trim((string)($_COOKIE['vr_cur'] ?? '')));
    if ($c === 'EUR') return $cur = '';
    if (in_array($c, $avail, true)) return $cur = $c;

    // Ülkeden.
    $geo = function_exists('vr_geo_country') ? vr_geo_country() : '';
    $g = vr_fx_country_currency($geo);
    return $cur = in_array($g, $avail, true) ? $g : '';
}

/** Para biriminin simgesi/öneki. */
function vr_fx_symbol(string $code): string
{
    static $s = ['USD' => '$', 'GBP' => '£', 'CHF' => 'CHF ', 'AED' => 'AED ', 'SAR' => 'SAR ', 'HKD' => 'HK$'];
    return $s[$code] ?? ($code . ' ');
}

/**
 * Gösterge karşılık: "≈ $213" ya da ''.
 * Kuruş gösterilmiyor — gösterge bir sayıda kuruş, olmayan bir kesinlik vaat
 * eder. Yuvarlama en yakın tam sayıya.
 */
function vr_money_local(int $cents, string $code = ''): string
{
    $code = $code !== '' ? $code : vr_fx_currency();
    if ($code === '') return '';
    $rate = vr_fx_table()['rates'][$code] ?? 0.0;
    if ($rate <= 0) return '';
    $amt = (int)round($cents / 100 * $rate);
    return "\u{2248}\u{202F}" . vr_fx_symbol($code) . number_format($amt, 0, ',', '.');
}
