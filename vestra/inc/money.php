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
 * ACIK SECIMI HEMEN YAZ — `vestra_currency()` cagrilana kadar bekleme.
 *
 * Yasanmis kusur (operator, 13 Eyl 2026: "para birimi secilmesine ragmen bir
 * sonraki linke tiklandiginda gene EUR oluyor"): cerez yazma
 * `vestra_currency()`'nin ICINDEYDI ve o fonksiyon sayfada ILK KEZ
 * `head.php`'nin ust cubugunda (~235. satir) cagriliyor. O noktada PHP'nin
 * cikti tamponu coktan bosalmis oluyor, yani `headers_sent()` DOGRU ve
 * `@setcookie` sessizce atlaniyor -- ustelik `@` uyariyi da yutuyor. Sonuc:
 * secilen birim O SAYFADA gorunuyor, bir sonraki istekte yok.
 *
 * OLCULDU, tahmin edilmedi: `?cur=USD` yaniti `vcur` cerezi TASIMIYOR ama
 * sayfa USD basiyor; ayni istekte `headers_sent()` = EVET.
 *
 * DIL TARAFI CALISIYORDU ve sebebi ogreticidir: `vlang()` `<html lang=…>`
 * icinde, yani sayfanin ~2. KB'sinde cagriliyor -- tampon henuz bosalmamis.
 * Yani ayni kusur iki fonksiyonda da yaziliydi; yalnizca BIRI, tamponun nerede
 * bosaldigina bagli olarak tetikleniyordu. *Cikti tamponuna bagli bir yazma,
 * calisiyor gorunse bile tesadufen calisiyordur.*
 *
 * Cozum konumdan BAGIMSIZ: bu fonksiyon money.php YUKLENIRKEN cagriliyor
 * (head.php onu 5. satirda, hicbir cikti basilmadan once require ediyor), yani
 * cerez her zaman yazilabiliyor. Sayfa gövdesinde ayrica cagrilmasi gerekmiyor
 * -- "her yeni sayfada hatirla" cozumu, bu depoda tekrar tekrar kaydedildigi
 * gibi, hatirlamaya birakildigi anda bozulur.
 *
 * TEK YAZICI: `vestra_currency()` de artik kendi setcookie'sini tasimiyor,
 * buraya devrediyor -- ayni olgunun iki kopyasi er gec ayrisir.
 */
function vestra_currency_remember(): string {
    $pick = strtoupper(trim((string)($_GET['cur'] ?? '')));
    if ($pick === '' || !isset(vestra_currencies()[$pick])) return '';

    /* Ayni istek de secimi gormeli: cerez ancak bir SONRAKI istekte geri gelir.
       Bu bir TASIMA degil, sureç ici ayna — o yuzden CLI muafiyetinin USTUNDE:
       cron cerez yazmamali ama `?cur=` verilmis bir cagrida hangi birimin
       secildigini yine de bilmeli. Ilk yazimda ayna da muafiyetin altindaydi
       ve test bunu yakaladi (`USD|-`). */
    $_COOKIE['vcur'] = $pick;

    if (PHP_SAPI === 'cli') return $pick;              // cron/test cerez YAZMAZ

    if (headers_sent($hf, $hl)) {
        /* Sessiz kalmiyor: bu satir gorunuyorsa cerez yazilamamistir ve
           kullanicinin secimi yine kaybolur. Kutuk teshise giriyor, o yuzden
           yalnizca dosya:satir — kisiye ait hicbir sey yok. */
        error_log('[VESTRA cur] cerez yazilamadi, cikti zaten baslamis: '.$hf.':'.$hl);
        return $pick;
    }
    @setcookie('vcur', $pick, [
        'expires'  => time() + 31536000,
        'path'     => '/',
        'samesite' => 'Lax',   // kampanya linkinden gelen ziyaretcide de tasinsin
    ]);
    return $pick;
}
vestra_currency_remember();

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
    if (isset($all[$pick])) { vestra_currency_remember(); return $cur = $pick; }

    $ck = strtoupper(trim((string)($_COOKIE['vcur'] ?? '')));
    if (isset($all[$ck])) return $cur = $ck;

    /* Beyan edilen ulke AGSIZ: girisli hesabin kendi kaydi. Once o sorulur —
       hem dogru hem bedava. */
    $acc = function_exists('auth_user') ? auth_user() : null;
    if (is_array($acc) && function_exists('vestra_cc_of_country')) {
        $cc = vestra_cc_of_country((string)($acc['country'] ?? ''));
        if ($cc === '') $cc = strtoupper(trim((string)($acc['reg_cc'] ?? '')));
        if ($cc !== '') return $cur = vestra_currency_for_cc($cc);
    }

    /* IP ULKESI — KURAL 12'nin maliyet korumalari BURADA DA gecerli.
       O korumalar (CLI atla, BOT atla, 1 sn zaman asimi) dil tarafi icin
       yazilmis ve `vlang_from_ip()` icinde duruyordu; para birimi yolu ayni
       `vestra_ip_intel()`'i cagiriyor ama korumalarin HICBIRINI tasimiyordu,
       yani her tarayici botu 3 sn'lik iki cografi sorgu tetikleyebiliyordu.
       Ayni olgunun ikinci cagri yeri, ilkinin ogrendiklerini otomatik olarak
       miras almiyor.
       Fonksiyon PAYLASILMADI: `vlang_from_ip()` DIL donduruyor, bu ULKE ariyor;
       ortak bir gövde, yarin dil tablosunda yapilan bir degisikligi para
       birimine de sessizce tasirdi. */
    if (PHP_SAPI !== 'cli'
        && function_exists('vestra_ip_intel') && function_exists('vestra_client_ip')
        && !(function_exists('vestra_is_bot') && vestra_is_bot((string)($_SERVER['HTTP_USER_AGENT'] ?? '')))) {
        $ip = vestra_client_ip();
        if ($ip !== '') {
            $cc = strtoupper((string)(vestra_ip_intel($ip, 1)['cc'] ?? ''));
            if ($cc !== '') return $cur = vestra_currency_for_cc($cc);
        }
    }
    return $cur = 'EUR';
}

/* ── kur kaynagi ──────────────────────────────────────────────────────────────
 *
 * Kur ucu ACILMAYABILIR. Paylasimli barindirmada disari HTTP cikisi kapali
 * olabiliyor ve bu, ilk yazdigimiz halin iki ayri yerinden isirdi:
 *
 *   1) Kur alinamayinca hicbir sey diske yazilmiyordu; yani ONBELLEK OLUSMUYOR,
 *      dolayisiyla HER SAYFA ISTEGI ucu yeniden deniyordu. Kapali bir ucta bu,
 *      her ziyaretciye birkac saniyelik bekleme demek -- cevirmeyen bir vitrin
 *      degil, YAVAS bir vitrin. Simdi basarisizlik da yaziliyor ve yarim saat
 *      boyunca tekrar denenmiyor.
 *   2) Tek kaynak vardi. Simdi sirayla birkac kaynak deneniyor.
 *
 * Yine de hicbiri acilmazsa operatorun elle girdigi kur devreye giriyor. Ama o
 * kur ECB kuru DEGIL, o yuzden sayfadaki cumle de degisiyor: "Avrupa Merkez
 * Bankasi kuru" yazip baska bir kur kullanmak, uydurma kurun kibarcasi olurdu.
 */

/** Kur kaynaklari, sirayla. Her biri ['rates'=>['USD'=>..], 'date'=>'Y-m-d'] ya da null doner. */
function _vestra_fx_sources(): array {
    return [
        /* ECB referans kuru, anahtar istemiyor. */
        'ecb' => function (): ?array {
            $raw = _vestra_fx_get('https://api.frankfurter.app/latest?from=EUR&to=USD,AUD,CAD');
            if ($raw === null) return null;
            $d = json_decode($raw, true);
            $r = array_filter((array)($d['rates'] ?? []));
            return $r ? ['rates' => $r, 'date' => (string)($d['date'] ?? '')] : null;
        },
        /* ECB'nin kendi gunluk XML'i — ayni kur, baska bir kapi. */
        'ecb2' => function (): ?array {
            $raw = _vestra_fx_get('https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml');
            if ($raw === null) return null;
            $date = preg_match('/time=\'([\d-]+)\'/', $raw, $m) ? $m[1] : '';
            $r = [];
            if (preg_match_all('/currency=\'(\w{3})\'\s+rate=\'([\d.]+)\'/', $raw, $mm, PREG_SET_ORDER)) {
                foreach ($mm as $x) if (in_array($x[1], ['USD','AUD','CAD'], true)) $r[$x[1]] = (float)$x[2];
            }
            return $r ? ['rates' => $r, 'date' => $date] : null;
        },
        /* Piyasa kuru — ECB degil, o yuzden en sonda ve etiketi farkli. */
        'market' => function (): ?array {
            $raw = _vestra_fx_get('https://open.er-api.com/v6/latest/EUR');
            if ($raw === null) return null;
            $d = json_decode($raw, true);
            $r = [];
            foreach (['USD','AUD','CAD'] as $c) if (!empty($d['rates'][$c])) $r[$c] = (float)$d['rates'][$c];
            return $r ? ['rates' => $r, 'date' => substr((string)($d['time_last_update_utc'] ?? ''), 5, 11)] : null;
        },
    ];
}

function _vestra_fx_get(string $url): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5,
                            CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_USERAGENT => 'vestrasales.com fx']);
    $raw  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($code >= 200 && $code < 300 && is_string($raw) && $raw !== '') ? $raw : null;
}

/**
 * Gecerli kur tablosu: ['rates' => [...], 'date' => 'Y-m-d', 'source' => 'ecb'|'market'|'manual'|''].
 * Istek basina bir kez hesaplaniyor.
 */
function vestra_fx_state(): array {
    static $st = null;
    if ($st !== null) return $st;

    $cache = _vsec_read('fx_rates.json');
    $now   = time();

    /* Taze onbellek: aga hic dokunma. */
    if (!empty($cache['rates']) && ($now - (int)($cache['ts'] ?? 0)) < 86400) {
        return $st = ['rates' => (array)$cache['rates'], 'date' => (string)($cache['date'] ?? ''),
                      'source' => (string)($cache['source'] ?? 'ecb')];
    }

    /* Son deneme yakin zamanda basarisizsa yeniden denemiyoruz: kapali bir uca
       her istekte vurmak, sayfayi ziyaretcinin gozunde yavaslatmaktan baska is
       gormuyor. */
    $backoff = ($now - (int)($cache['fail_ts'] ?? 0)) < 1800;
    if (!$backoff) {
        foreach (_vestra_fx_sources() as $name => $fetch) {
            $r = $fetch();
            if ($r === null) continue;
            $out = ['ts' => $now, 'date' => $r['date'], 'rates' => $r['rates'],
                    'source' => $name === 'market' ? 'market' : 'ecb'];
            _vsec_write('fx_rates.json', $out);
            return $st = ['rates' => $out['rates'], 'date' => $out['date'], 'source' => $out['source']];
        }
        /* Hicbiri acilmadi: bunu YAZ ki yarim saat boyunca tekrar denemeyelim.
           Elimizdeki bayat kuru da kaybetmiyoruz. */
        _vsec_write('fx_rates.json', $cache + ['fail_ts' => $now]);
    }

    /* Bayat da olsa onbellekteki kur, kur olmamasindan iyi. */
    if (!empty($cache['rates'])) {
        return $st = ['rates' => (array)$cache['rates'], 'date' => (string)($cache['date'] ?? ''),
                      'source' => (string)($cache['source'] ?? 'ecb')];
    }

    /* Son care: operatorun elle girdigi kur. ECB demiyoruz, "manual" diyoruz. */
    $man = _vsec_read('fx_manual.json');
    if (!empty($man['rates'])) {
        return $st = ['rates' => array_map('floatval', (array)$man['rates']),
                      'date' => (string)($man['date'] ?? ''), 'source' => 'manual'];
    }
    return $st = ['rates' => [], 'date' => '', 'source' => ''];
}

/** EUR -> hedef para birimi kuru. Bulunamazsa 0.0 (cevrim YAPILMAZ). */
function vestra_fx(string $to): float {
    $to = strtoupper($to);
    if ($to === 'EUR') return 1.0;
    if (!isset(vestra_currencies()[$to])) return 0.0;
    return (float)(vestra_fx_state()['rates'][$to] ?? 0.0);
}

/** Kurun tarihi (gosterimde "hangi gunun kuru" diyebilmek icin). '' = bilinmiyor. */
function vestra_fx_date(): string {
    return (string)vestra_fx_state()['date'];
}

/** Kur nereden geldi: 'ecb' | 'market' | 'manual' | '' (kur yok). */
function vestra_fx_source(): string {
    return (string)vestra_fx_state()['source'];
}

/**
 * Elle kur yaz (yonetim panelinden). Bos dizi verilirse elle kur kaldirilir.
 * Sadece son care olarak kullanilir: canli kaynaklarin hepsi kapaliysa.
 */
function vestra_fx_set_manual(array $rates, string $date = ''): void {
    $clean = [];
    foreach (['USD','AUD','CAD'] as $c) {
        $v = (float)($rates[$c] ?? 0);
        if ($v > 0) $clean[$c] = round($v, 6);
    }
    _vsec_write('fx_manual.json', $clean ? ['rates' => $clean, 'date' => $date !== '' ? $date : date('Y-m-d'),
                                            'saved_at' => date('c')] : []);
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
 *
 * Cumle KAYNAGA gore degisiyor: ECB kuru kullanmiyorsak "ECB kuru" demiyoruz.
 */
function vestra_money_note(): string {
    if (!vestra_money_converted()) return '';
    $cur  = vestra_currency();
    $date = vestra_fx_date();
    $tail = $date !== '' ? ' ('.$date.')' : '';
    $t = function (string $s): string { return function_exists('t') ? t($s) : $s; };

    if (vestra_fx_source() === 'ecb') {
        return sprintf($t('Prices shown in %s are converted from EUR at the European Central Bank reference rate%s. Orders are invoiced in EUR.'), $cur, $tail);
    }
    return sprintf($t('Prices shown in %s are converted from EUR at an indicative rate%s. Orders are invoiced in EUR.'), $cur, $tail);
}

/**
 * Para birimi secici — ACILIR menu.
 *
 * Once dort kod yan yana duruyordu ve basligta yer kapliyordu. Simdi tek dugme:
 * tiklayinca listeyi ASAGI, sayfanin USTUNE aciyor (position:absolute), yani
 * acilirken hicbir seyi itmiyor -- acilip kapandikca basligin yeniden dizilmesi,
 * kullanicinin tiklamak uzere oldugu seyi yerinden oynatir.
 *
 * <details> ile: JavaScript gerekmiyor, klavyeyle acilip kapaniyor ve JS
 * calismasa da calisiyor. Secenekler sirali baglantilar oldugu icin arama
 * motoru da, ekran okuyucu da ne oldugunu anliyor.
 */
function vestra_cur_switcher(string $class = 'cursw'): string {
    $cur  = vestra_currency();
    $all  = vestra_currencies();
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = array_filter($_GET, fn($k) => $k !== 'cur', ARRAY_FILTER_USE_KEY);

    $out  = '<details class="'.$class.'">';
    $out .= '<summary title="'.htmlspecialchars($all[$cur]['label'] ?? $cur).'">'
          . '<span class="cswcur">'.htmlspecialchars($cur).'</span>'
          . '<svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
          . 'stroke-width="3" stroke-linecap="round"><path d="M6 9l6 6 6-6"/></svg></summary>';
    $out .= '<div class="cswmenu">';
    foreach ($all as $code => $meta) {
        $qs   = http_build_query($base + ['cur' => $code]);
        $href = htmlspecialchars($path.'?'.$qs, ENT_QUOTES);
        $out .= '<a class="csw'.($code === $cur ? ' on' : '').'" href="'.$href.'">'
              . '<b>'.htmlspecialchars($code).'</b>'
              . '<span>'.htmlspecialchars($meta['sym']).'</span></a>';
    }
    return $out.'</div></details>';
}
