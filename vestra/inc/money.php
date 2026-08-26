<?php
/**
 * VESTRA — gosterim para birimi.
 *
 * KURAL: katalogda cevir, sozlesmede EUR kal.
 *
 * Fiyatlar EUR olarak saklaniyor ve fatura, siparis kaydi, escrow ve komisyon
 * EUR uzerinden isliyor. Bu dosya yalnizca GOSTERIMI degistiriyor: Amerikali bir
 * alici katalogda US$ goruyor, ama siparisi EUR uzerinden faturalaniyor ve bunu
 * sayfa acikca soyluyor.
 *
 * Neden boyle: gosterimi cevirmek bir kolaylik, sozlesme para birimini degistirmek
 * ise bambaska bir is -- kur farki riskini kimin tasidigi, hangi anin kuru gecerli
 * oldugu, iade edilirken hangi rakamin donecegi. Faturayi USD kesip siparisi EUR
 * tutmak, belgeyi kendi icinde celiskiye dusurur. O yuzden burada durduk ve
 * sayfada bunu YAZIYORUZ; sessizce ceviren bir vitrin, alicinin kasada baska bir
 * rakam gormesi demektir.
 *
 * KUR UYDURULMUYOR. Kur alinamazsa cevrim yapilmiyor, EUR gosteriliyor. Yanlis
 * bir kurla gosterilen fiyat, fiyat gostermemekten kotudur.
 */

require_once __DIR__.'/security.php';   // vestra_visitor_cc(), _vsec_read/_write

/** Desteklenen para birimleri: kod => [sembol, ondalik ayirici bicimi, ad]. */
function vestra_currencies(): array {
    return [
        'EUR' => ['sym' => '€',    'label' => 'EUR €'],
        'USD' => ['sym' => 'US$',  'label' => 'USD $'],
        'AUD' => ['sym' => 'A$',   'label' => 'AUD $'],
        'CAD' => ['sym' => 'C$',   'label' => 'CAD $'],
    ];
}

/* AB uyesi ulkeler. Kural "AB disi -> USD" oldugu icin bu listenin kendisi
   kuralin tanimi; euro bolgesi degil UYELIK listesi, cunku Polonyali bir alici
   zloty kullansa da AB ici ticaret yapiyor ve EUR fiyat ona tanidik. */
function vestra_eu_countries(): array {
    return ['AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE',
            'IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE'];
}

/**
 * Ulkeye gore varsayilan para birimi.
 *   AU -> AUD, CA -> CAD, US -> USD, AB uyesi -> EUR, geri kalan her yer -> USD.
 * Avustralyaliya USD gostermek "AB disi hepsi USD" kuralinin harfi olurdu ama
 * ruhu degil: ona kendi parasini gosterebiliyoruz, gosterelim.
 */
function vestra_currency_for_cc(string $cc): string {
    $cc = strtoupper(trim($cc));
    if ($cc === '') return 'EUR';                       // bilmiyorsak taban para birimi
    if ($cc === 'AU') return 'AUD';
    if ($cc === 'CA') return 'CAD';
    if ($cc === 'US') return 'USD';
    return in_array($cc, vestra_eu_countries(), true) ? 'EUR' : 'USD';
}

/**
 * Bu ziyaretcinin para birimi.
 *   1) Acik secim (?cur= ya da cerez) — kullanicinin tercihi her seyin ustunde.
 *   2) Ulke (beyan edilen ulke, yoksa IP ulkesi).
 *   3) EUR.
 */
function vestra_currency(): string {
    static $cur = null;
    if ($cur !== null) return $cur;
    $all = vestra_currencies();

    $pick = strtoupper(trim((string)($_GET['cur'] ?? '')));
    if (isset($all[$pick])) {
        $cur = $pick;
        if (!headers_sent()) @setcookie('vcur', $cur, time() + 31536000, '/');
        $_COOKIE['vcur'] = $cur;
        return $cur;
    }
    $ck = strtoupper(trim((string)($_COOKIE['vcur'] ?? '')));
    if (isset($all[$ck])) return $cur = $ck;

    $acc = function_exists('auth_user') ? auth_user() : null;
    $cc  = function_exists('vestra_visitor_cc') ? vestra_visitor_cc($acc) : '';
    return $cur = vestra_currency_for_cc($cc);
}

/**
 * EUR -> hedef para birimi kuru. Bulunamazsa 0.0 (cevrim YAPILMAZ).
 *
 * Kaynak Avrupa Merkez Bankasi'nin gunluk referans kuru (frankfurter.app, anahtar
 * istemiyor). Gunde bir cekiliyor ve diske yaziliyor; ucun cevap vermedigi bir gun
 * ESKI kur kullaniliyor -- bir gunluk eski kur, hic fiyat gostermemekten iyi, ama
 * yasi da yazilabilsin diye zaman damgasi saklaniyor.
 */
function vestra_fx(string $to): float {
    $to = strtoupper($to);
    if ($to === 'EUR') return 1.0;
    if (!isset(vestra_currencies()[$to])) return 0.0;

    $cache = _vsec_read('fx_rates.json');
    $age   = time() - (int)($cache['ts'] ?? 0);
    if ($age < 86400 && !empty($cache['rates'][$to])) return (float)$cache['rates'][$to];

    $ch = curl_init('https://api.frankfurter.app/latest?from=EUR&to=USD,AUD,CAD');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 4,
                            CURLOPT_CONNECTTIMEOUT => 3]);
    $raw  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code >= 200 && $code < 300 && is_string($raw)) {
        $d = json_decode($raw, true);
        $rates = (array)($d['rates'] ?? []);
        if ($rates) {
            _vsec_write('fx_rates.json', ['ts' => time(), 'date' => (string)($d['date'] ?? ''), 'rates' => $rates]);
            return (float)($rates[$to] ?? 0.0);
        }
    }
    /* Uc cevap vermedi: elimizdeki en son kuru kullan, o da yoksa cevirme. */
    return (float)($cache['rates'][$to] ?? 0.0);
}

/** Kurun tarihi (gosterimde "hangi gunun kuru" diyebilmek icin). '' = bilinmiyor. */
function vestra_fx_date(): string {
    $c = _vsec_read('fx_rates.json');
    return (string)($c['date'] ?? '');
}

/**
 * Tutari ziyaretcinin para biriminde yaz. Cevrilemiyorsa EUR yazar.
 * $cur verilirse o para birimi kullanilir (fatura gibi sabit baglamlar icin).
 */
function vestra_money(float $eurAmount, ?string $cur = null): string {
    $cur = $cur !== null ? strtoupper($cur) : vestra_currency();
    $all = vestra_currencies();
    if (!isset($all[$cur]) || $cur === 'EUR') {
        return '€'.number_format($eurAmount, 2, '.', ',');
    }
    $rate = vestra_fx($cur);
    if ($rate <= 0) return '€'.number_format($eurAmount, 2, '.', ',');   // kur yok: uydurma
    return $all[$cur]['sym'].number_format($eurAmount * $rate, 2, '.', ',');
}

/** Ziyaretci EUR disinda bir para birimi goruyor mu? Uyari satirini bu belirliyor. */
function vestra_money_converted(): bool {
    $c = vestra_currency();
    return $c !== 'EUR' && vestra_fx($c) > 0;
}

/**
 * "Bu fiyatlar cevrilmis" satiri. Gosterilen her yerde ayni cumle olsun diye
 * tek yerden ciktigi gibi, cevrim yapilmiyorsa BOS doner -- yani EUR goren
 * ziyaretciye anlamsiz bir uyari cikmaz.
 */
function vestra_money_note(): string {
    if (!vestra_money_converted()) return '';
    $cur  = vestra_currency();
    $date = vestra_fx_date();
    $t = function (string $s): string { return function_exists('t') ? t($s) : $s; };
    return sprintf(
        $t('Prices shown in %s are converted from EUR at the European Central Bank reference rate%s. Orders are invoiced in EUR.'),
        $cur,
        $date !== '' ? ' ('.$date.')' : ''
    );
}

/**
 * Para birimi secici — dil seciciyle ayni desen: mevcut sorgu dizesini korur,
 * yalnizca ?cur= parametresini degistirir. Boylece secim hangi sayfadaysa orada
 * kaliyor; kullaniciyi ana sayfaya atan bir secici, secimi kullanilmaz kilar.
 */
function vestra_cur_switcher(string $class = 'cursw'): string {
    $cur  = vestra_currency();
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = array_filter($_GET, fn($k) => $k !== 'cur', ARRAY_FILTER_USE_KEY);
    $out  = '<div class="'.$class.'">';
    foreach (vestra_currencies() as $code => $meta) {
        $qs   = http_build_query($base + ['cur' => $code]);
        $href = htmlspecialchars($path.'?'.$qs, ENT_QUOTES);
        $out .= '<a class="csw'.($code === $cur ? ' on' : '').'" href="'.$href.'" '
              . 'title="'.htmlspecialchars($meta['label']).'">'.$code.'</a>';
    }
    return $out.'</div>';
}
