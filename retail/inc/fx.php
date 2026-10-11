<?php

/**
 * GÖSTERGE PARA BİRİMİ
 * --------------------
 * Dünyanın her yerinden alıcı için fiyatın yanında kendi para biriminde
 * yaklaşık bir karşılık: "185,00 € · ≈ $213". Bağlayıcı fiyat EUR'dur ve
 * kasada EUR çekilir (PAngV); karşılık yalnızca gösterge ve öyle etiketli.
 *
 * Kurlar data/fx-rates.json'da, tarihli ve kaynaklı: Avrupa Merkez Bankası
 * günlük referans kurları (resmî, ücretsiz, EUR bazlı). USD'ye sabit bağlı
 * birimler (AED, SAR, QAR, AZN) USD kurundan resmî sabit oranla türetilir.
 * Tablo 2 günden eskiyse sunucu ECB'den tazeler (en çok 6 saatte bir deneme,
 * kilitli, 4 sn zaman aşımı — ziyaretçinin hiçbir bilgisi gönderilmez);
 * 45 günden eskiyse hiçbir şey gösterilmiyor: bayat bir kur, kur
 * yokluğundan kötüdür.
 *
 * Para birimi seçimi: ?cur=USD ile açıkça (çerezle kalıcı) → yoksa ziyaretçinin
 * ülkesinden (inc/geo.php: CDN başlığı ya da yerel IP tablosu) → yoksa hiç.
 * EUR bölgesindeki ziyaretçi ikinci bir fiyat görmüyor; ona gürültü olurdu.
 */

declare(strict_types=1);

require_once __DIR__ . '/geo.php';

const VR_FX_MAX_AGE_DAYS = 45;
const VR_FX_ECB_URL = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';
/** USD'ye resmî sabit kurla bağlı birimler: 1 USD = … */
const VR_FX_USD_PEGS = ['AED' => 3.6725, 'SAR' => 3.75, 'QAR' => 3.64, 'AZN' => 1.70];

/** Ülke → gösterge para birimi. Yalnızca kur tablosunda olan birimler gösterilir. */
function vr_fx_country_currency(string $cc): string
{
    static $map = [
        'US' => 'USD', 'PR' => 'USD', 'EC' => 'USD', 'SV' => 'USD', 'PA' => 'USD',
        'GB' => 'GBP', 'JE' => 'GBP', 'GG' => 'GBP', 'IM' => 'GBP', 'GI' => 'GBP',
        'CH' => 'CHF', 'LI' => 'CHF',
        'SE' => 'SEK', 'DK' => 'DKK', 'GL' => 'DKK', 'FO' => 'DKK', 'NO' => 'NOK', 'IS' => 'ISK',
        'PL' => 'PLN', 'CZ' => 'CZK', 'HU' => 'HUF', 'RO' => 'RON', 'TR' => 'TRY',
        'CA' => 'CAD', 'AU' => 'AUD', 'NZ' => 'NZD', 'MX' => 'MXN', 'BR' => 'BRL', 'ZA' => 'ZAR',
        'JP' => 'JPY', 'CN' => 'CNY', 'HK' => 'HKD', 'KR' => 'KRW', 'SG' => 'SGD', 'IN' => 'INR',
        'TH' => 'THB', 'MY' => 'MYR', 'ID' => 'IDR', 'PH' => 'PHP', 'IL' => 'ILS',
        'AE' => 'AED', 'SA' => 'SAR', 'QA' => 'QAR', 'AZ' => 'AZN',
    ];
    return $map[strtoupper($cc)] ?? '';
}

/**
 * ECB'den kurları çek ve data/fx-rates.json'a yaz. Başarıda true.
 * $force=false: tablo 2 günden yeniyse ya da son deneme 6 saatten yakınsa
 * hiçbir şey yapmaz; aynı anda iki istek denemesin diye kilit.
 */
function vr_fx_refresh(bool $force = false): bool
{
    $raw  = vr_store_read('fx-rates.json', []);
    $asOf = strtotime((string)($raw['as_of'] ?? '')) ?: 0;
    $try  = (int)($raw['tried'] ?? 0);
    if (!$force && ((time() - $asOf) < 2 * 86400 || (time() - $try) < 6 * 3600)) return false;

    $lock = @fopen(vr_data_dir() . '/.fx.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) return false;
    vr_store_write('fx-rates.json', ['tried' => time()] + (is_array($raw) ? $raw : []));

    $ch = curl_init(VR_FX_ECB_URL);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $force ? 20 : 4,
                            CURLOPT_CONNECTTIMEOUT => $force ? 10 : 2, CURLOPT_USERAGENT => 'sarvesto-fx/1.0']);
    $xml = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $rates = [];
    if ($code === 200 && preg_match("/time=['\"](\d{4}-\d{2}-\d{2})['\"]/", $xml, $d)) {
        preg_match_all("/currency=['\"]([A-Z]{3})['\"]\s+rate=['\"]([0-9.]+)['\"]/", $xml, $m, PREG_SET_ORDER);
        foreach ($m as [, $c, $r]) if ((float)$r > 0) $rates[$c] = (float)$r;
        if (isset($rates['USD'])) foreach (VR_FX_USD_PEGS as $c => $peg) $rates[$c] = round($rates['USD'] * $peg, 4);
    }
    $ok = count($rates) >= 10;
    if ($ok) {
        vr_store_write('fx-rates.json', ['as_of' => $d[1], 'tried' => time(),
            'source' => 'European Central Bank euro foreign exchange reference rates; AED/SAR/QAR/AZN via official USD peg',
            'rates' => $rates]);
    }
    flock($lock, LOCK_UN); fclose($lock);
    return $ok;
}

/** Kur tablosu; eski ya da bozuksa boş dizi. */
function vr_fx_table(): array
{
    static $t = null;
    if ($t !== null) return $t;
    // Gerekirse tazele (çoğu istekte tek bir tarih karşılaştırması).
    vr_fx_refresh();
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
    static $s = ['USD' => '$', 'GBP' => '£', 'CHF' => 'CHF ', 'AED' => 'AED ', 'SAR' => 'SAR ', 'HKD' => 'HK$',
                 'JPY' => '¥', 'CNY' => 'CN¥', 'KRW' => '₩', 'INR' => '₹', 'TRY' => '₺', 'ILS' => '₪',
                 'CAD' => 'CA$', 'AUD' => 'A$', 'NZD' => 'NZ$', 'SGD' => 'S$', 'MXN' => 'MX$', 'BRL' => 'R$',
                 'SEK' => 'SEK ', 'DKK' => 'DKK ', 'NOK' => 'NOK ', 'PLN' => 'zł ', 'CZK' => 'Kč ', 'HUF' => 'Ft ',
                 'AZN' => '₼', 'QAR' => 'QAR ', 'THB' => '฿', 'PHP' => '₱', 'ZAR' => 'R ', 'RON' => 'lei '];
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
