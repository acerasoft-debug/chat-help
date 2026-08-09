<?php
/**
 * MAXSALES Retail — merkezi yapılandırma
 * ------------------------------------
 * Bu dosyadaki HİÇBİR gizli anahtar koda yazılmaz. Stripe/Brevo anahtarları
 * sırayla şu kaynaklardan okunur (ilk bulunan kazanır):
 *
 *   1) Ortam değişkeni      (STRIPE_SECRET_KEY, ...)          — CI/CD için
 *   2) data/stripe_settings.json  (chmod 0600)                — sunucuda kalıcı
 *   3) yok  → site "ödeme yapılandırılmadı" moduna düşer, çökmez
 *
 * Canlı B2B sitesi (vestrasales.com) ile AYNI sunucuda çalışır ama onun
 * dosyalarına YAZMAZ: listings.json salt-okunur okunur, perakendeye özel her
 * şey retail-*.json dosyalarında tutulur. Bu yüzden bu katmanı kurmak canlı
 * toptan satışı hiçbir şekilde etkilemez.
 */

declare(strict_types=1);

// ---------------------------------------------------------------- yol keşfi
/**
 * Uygulama kökü (bu dosyanın bir üstü). Site hangi klasöre kurulursa kurulsun
 * (public_html/, public_html/shop/, ayrı bir vhost) yollar kendiliğinden doğru
 * çözülsün diye hiçbir yere sabit yol yazmıyoruz.
 */
const VR_ROOT = __DIR__ . '/..';

/**
 * Veri dizini. Canlı sitede data/ zaten public_html/data — orada listings.json
 * ve email_settings.json duruyor, onları da okuyabilmek için varsayılan olarak
 * ÜST dizindeki data/ klasörünü de arıyoruz.
 */
function vr_data_dir(): string
{
    static $dir = null;
    if ($dir !== null) return $dir;

    $env = getenv('VR_DATA_DIR');
    if (is_string($env) && $env !== '' && is_dir($env)) return $dir = rtrim($env, '/');

    // Kurulum senaryoları: retail/ public_html altında bir alt klasörse üstteki
    // data/ paylaşılır; kendi başına bir vhost'sa kendi data/ klasörü kullanılır.
    foreach ([VR_ROOT . '/../data', VR_ROOT . '/data'] as $cand) {
        if (is_dir($cand)) return $dir = realpath($cand) ?: $cand;
    }
    $own = VR_ROOT . '/data';
    @mkdir($own, 0750, true);
    return $dir = $own;
}

/** Bu uygulamanın URL ön eki — /, /shop, /retail … otomatik bulunur. */
function vr_base_url(): string
{
    static $base = null;
    if ($base !== null) return $base;

    $env = getenv('VR_BASE_URL');
    if (is_string($env) && $env !== '') return $base = rtrim($env, '/');

    // CLI'da SCRIPT_NAME göreceli bir dosya yolu ("tools/selftest.php") olur;
    // ondan URL ön eki türetmek yanlış sonuç verir. Araçlar için kök varsayıyoruz.
    if (PHP_SAPI === 'cli') return $base = '';

    $script = '/' . ltrim(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');

    /**
     * Ön eki alt dizin ADLARINDAN türetmiyoruz. Eskiden bilinen bir liste vardı
     * (seller, api, legal, tools) ve listeye girmeyen her yeni klasör — account,
     * admin — bağlantıları ikiye katlıyordu: /account/account/login.php.
     *
     * Doğrusu dosya sisteminden saymak: çalışan betiğin VR_ROOT'a göre kaç
     * seviye derinde olduğunu bul, URL yolundan o kadar segment kırp. Klasör
     * adından bağımsız, yeni klasör eklendiğinde kendiliğinden doğru.
     */
    $file = str_replace('\\', '/', (string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $root = str_replace('\\', '/', (string)(realpath(VR_ROOT) ?: VR_ROOT));
    if ($file !== '' && $root !== '') {
        $real = str_replace('\\', '/', (string)(realpath($file) ?: $file));
        if (str_starts_with($real, $root . '/')) {
            $rel   = substr($real, strlen($root) + 1);         // account/login.php
            $depth = substr_count($rel, '/');                  // kaç klasör derinde
            $parts = explode('/', trim($script, '/'));
            array_pop($parts);                                 // dosya adı
            for ($i = 0; $i < $depth && $parts; $i++) array_pop($parts);
            return $base = $parts ? '/' . implode('/', $parts) : '';
        }
    }

    // Yol eşleşmesi kurulamadıysa (sembolik bağ, alışılmadık kurulum) eski
    // davranışa dön: bir seviye yukarı.
    $dir = rtrim(dirname($script), '/');
    return $base = ($dir === '/' ? '' : $dir);
}

/** Şema + host (canonical/absolute URL üretimi ve Stripe redirect'leri için). */
function vr_origin(): string
{
    $env = getenv('VR_ORIGIN');
    if (is_string($env) && $env !== '') return rtrim($env, '/');

    $https = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
          || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443);
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'vestrasales.com');
    // Host başlığı istemciden gelir: yalnızca izin verilen karakterleri geçiriyoruz.
    if (!preg_match('/^[A-Za-z0-9.\-]+(:\d+)?$/', $host)) $host = 'vestrasales.com';

    return ($https ? 'https://' : 'http://') . $host;
}

/**
 * İstek mağazanın asıl adresine mi geldi?
 *
 * Yalnızca robots.txt için kullanılıyor: yardımcı adreslerden (test alt adı,
 * cPanel'in ürettiği alt adlar) gelen istekte tarayıcıya "burayı dizine
 * ekleme" diyoruz. Yönlendirme YAPMIYORUZ — DNS taşınmadan önce asıl adres
 * cevap vermiyor, oraya yönlendirmek siteyi tamamen erişilemez yapardı.
 */
function vr_is_canonical_host(): bool
{
    $allow = (array)vr_config('canonical_hosts', []);
    if (!$allow) return true;

    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host);
    foreach ($allow as $h) {
        if ($host === strtolower(trim((string)$h))) return true;
    }
    return false;
}

// ------------------------------------------------------------------- ayarlar
/**
 * Tek yapılandırma sözlüğü. Sunucuya özel değerler data/retail-settings.json
 * ile ezilebilir — kod güncellemesi ayarları silmesin diye.
 */
function vr_config(?string $key = null, mixed $default = null): mixed
{
    static $cfg = null;

    if ($cfg === null) {
        $cfg = [
            // ---- marka
            'brand'            => 'MAXSALES',
            'brand_tagline_key' => 'tagline',
            /**
             * Mağazanın asıl alan adı. Site birden fazla adresten cevap
             * verebiliyor: DNS taşınana kadar test için açılan
             * maxsales.vestrasales.com, cPanel'in her ek alanla birlikte
             * ürettiği alt adlar, bir de asıl adres. Hepsi aynı sayfaları
             * döndürdüğü için arama motoru bunları çoğaltılmış içerik sayar
             * ve hangisini sıralayacağına kendisi karar verir.
             * Liste boş bırakılırsa denetim kapanır (yerel geliştirme).
             */
            'canonical_hosts'  => ['maxsales.de', 'www.maxsales.de'],
            'primary_lang'     => 'en',
            // Canlı B2B sitesinin 5 dili + ayrıca istenen 5 pazar:
            // ru (Rusça), az (Azerbaycanca), da (Danca), sv (İsveççe),
            // nl (Flamanca/Hollandaca — Flaman bölgesi için standart kod nl,
            // locale nl-BE olarak veriliyor).
            'languages'        => ['en', 'de', 'fr', 'it', 'es', 'ru', 'az', 'da', 'sv', 'nl'],
            'currency'         => 'EUR',
            'currency_symbol'  => '€',
            'locale_default'   => 'de-DE',

            /**
             * ---- işletmeci (Impressum)
             *
             * Değerler UYDURULMADI: VESTRA'nın yayındaki hukuk metinlerinden
             * (sunucudaki inc/legal/de.php, 'imprint' bloğu) birebir alındı.
             * İki site aynı tüzel kişiye ait; künye de aynı olmak zorunda,
             * yoksa iki farklı işletmeci beyanı ortaya çıkar.
             *
             * reg_number BİLEREK BOŞ: Delaware dosya numarası o metinde
             * yazmıyor ve künyeye tahmin yazılamaz (§5 DDG yanlış beyan).
             * Numara gelene kadar selftest bu satırda FAIL veriyor — istenen
             * davranış bu.
             */
            'company' => [
                'legal_name'   => 'acerasoft LLC',
                'form'         => 'US Limited Liability Company (State of Delaware)',
                'street'       => '8 The Green, Suite B',
                'city'         => 'Dover',
                'zip'          => '19901',
                'state'        => 'Delaware',
                'country'      => 'USA',
                'reg_authority' => 'Delaware Division of Corporations',
                'reg_number'   => '',      // Delaware File Number — eksik
                'vat_id'       => '',      // USt-IdNr. varsa
                'represented_by' => 'Management',
                'email'        => 'support@vestrasales.com',
                'phone'        => '',
                'eu_rep'       => '',      // DSGVO Art. 27 AB temsilcisi (varsa)
                // Uyuşmazlıklarda geçerli hukuk — VESTRA sözleşmeleriyle aynı.
                'law'          => 'State of Delaware, USA',
                'email_legal'   => 'legal@vestrasales.com',
                'email_privacy' => 'privacy@vestrasales.com',
            ],

            // ---- para/vergi
            'vat_rate_bps'       => 1900,  // %19 DE standart oran
            'prices_include_vat' => true,  // B2C: gösterilen fiyat brüt (PAngV §1)
            'stripe_tax'         => false, // true → vergiyi Stripe Tax hesaplar

            // ---- perakende fiyatlama (B2B kataloğundan türetme)
            'retail_multiplier'  => 3.00,  // toptan birim fiyat × 3
            'retail_round_to'    => 900,   // 10 €'luk ızgarada …9,00 ile bitir
            'retail_min_cents'   => 2900,

            /**
             * ---- SABİT OUTLET FİYATLARI
             * Marka + kategoriye göre sabit satış fiyatı. Bir kural eşleşirse
             * çarpan da yuvarlama da devre dışı kalır; yazan rakam neyse o
             * basılır. Kaynak fark etmez — B2B satırı, içe aktarılmış satır ya
             * da satıcı ilanı, hepsi buradan geçer.
             *
             * Kurallar SIRAYLA denenir, İLK eşleşen kazanır. Bu yüzden özel
             * olan (kapüşonlu, fermuarlı) genel olandan ÖNCE gelmeli.
             *
             *   brand  zorunlu, büyük harf, tam eşleşme
             *   cat    isteğe bağlı; boşsa markanın tüm kategorileri
             *   match  isteğe bağlı; ürün adına uygulanan düzenli ifade
             *   cents  satış fiyatı, kuruş
             *
             * Fiyatı değiştirmek için yalnızca burayı düzenlemek yeter —
             * katalogu yeniden içe aktarmaya gerek yok.
             */
            /**
             * MARKA BASAMAĞI × KATEGORİ ÇARPANI
             * ---------------------------------
             * 478 ürünün fiyatı toptan veriden gelemiyor: kaynak stapellerde
             * parti başına tek rakam var, Burberry'nin 62 ürünü aynı fiyatı
             * taşıyordu. Onun yerine işletmecinin verdiği çapalardan bir ölçek
             * kuruldu.
             *
             * ÇAPALAR (işletmeci verdi):
             *   Gucci T-Shirt 220 · Gucci Tracksuit 550 · Balmain T-Shirt 139
             *   DSQUARED2 T-Shirt 89–99
             *
             * Gucci'nin iki rakamı tam 2,5 kat: 220 × 2,5 = 550. Kategori
             * çarpanları bu orana oturtuldu, yani ölçek işletmecinin kendi
             * fiyatlamasından türüyor, dışarıdan dayatılmıyor.
             *
             * Taban = o markanın T-SHIRT fiyatı (avro). Diğer kategoriler
             * çarpanla çıkıyor. Rakamlar CAZİP YUVARLAMAYA (X9) sokulmuyor:
             * verilen çapalar 220 ve 550 gibi tam sayılar, X9'a çevirmek
             * işletmecinin dediğini bozardı.
             *
             * Adı geçmeyen markalar ("Givenchy ve diğerlerini premium olarak
             * ona göre yap") bu basamağa yerleştirildi. Bunlar BİR ÖNERİ:
             * rakamı değiştirmek tek satır.
             */
            'brand_base_eur' => [
                'BALENCIAGA'      => 240,
                'GUCCI'           => 220,   // çapa
                'VALENTINO'       => 230,
                'FENDI'           => 210,
                'VERSACE'         => 190,
                'GIVENCHY'        => 180,
                'DOLCE & GABBANA' => 170,
                'BURBERRY'        => 160,
                'BALMAIN'         => 139,   // çapa
                'MARCELO BURLON'  => 120,
                'GCDS'            => 110,
                'DSQUARED2'       => 95,    // çapa: 89–99 aralığının ortası
            ],
            'cat_factor' => [
                'Bademode'              => 0.85,
                'T-Shirts'              => 1.00,
                'Shorts'                => 1.10,
                'Polos'                 => 1.15,
                'Jeans Shorts'          => 1.35,
                'Trousers'              => 1.60,
                'Jeans'                 => 1.90,
                'Hoodies & Sweatshirts' => 1.90,
                'Westen'                => 2.20,
                'Tracksuit Sets'        => 2.50,   // çapa: 220 × 2,5 = 550
                'Jacken'                => 3.00,
            ],

            'price_rules' => [
                ['brand' => 'LACOSTE', 'cat' => 'Polos',    'cents' =>  8000],
                ['brand' => 'LACOSTE', 'cat' => 'T-Shirts', 'cents' =>  3990],
                // Kapüşonlu → 119,00
                ['brand' => 'LACOSTE', 'cat' => 'Hoodies & Sweatshirts',
                 'match' => '/hood|kapuz|capuch|cappucc/iu',       'cents' => 11900],
                // Fermuarlı sweat ceket → 99,90
                ['brand' => 'LACOSTE', 'cat' => 'Hoodies & Sweatshirts',
                 'match' => '/zip|jacke|jacket|veste|blouson/iu',  'cents' =>  9990],
                // Düz sweatshirt → 89,90
                ['brand' => 'LACOSTE', 'cat' => 'Hoodies & Sweatshirts', 'cents' => 8990],
            ],

            /**
             * ---- DOĞRULANMIŞ KESİM BİLGİSİ
             * Marka + kategori için işletmecinin bildiği kesin bilgi. Künye
             * tablosunda ayrı bir satır olarak çıkar. Metin ÜRETİLMEZ, sadece
             * burada yazan basılır — uydurma yaka/kumaş bilgisi çıkmasın diye
             * metin motoruna değil, künyeye bağlı.
             */
            'cut_notes' => [
                ['brand' => 'LACOSTE', 'cat' => 'T-Shirts',
                 'de' => 'Rundhals', 'en' => 'Crew neck'],
            ],

            // ---- Gutschein / kupon
            // Kayıt olup e-postasını doğrulayan her müşteriye otomatik
            // gönderilen ilk sipariş indirimi. 0 = otomatik kupon kapalı.
            // Resmî fiyattan (UVP) indirim. data/official-prices.json
            // doldurulduğunda satış fiyatı buradan hesaplanıyor ve UVP ürün
            // sayfasında üstü çizili görünüyor. Tablo boşsa hiçbir etkisi yok.
            'official_discount_bps' => 2000,  // %20

            'welcome_discount_bps' => 500,   // %5
            'welcome_valid_days'   => 90,

            // ---- pazaryeri komisyonu (Stripe Connect application fee)
            'fee_bps_business'   => 1200,  // %12 tüccar satıcı
            'fee_bps_private'    => 900,   // %9  özel satıcı (Privatverkäufer)
            'fee_fixed_cents'    => 35,    // sabit işlem payı
            'fee_bps_outlet'     => 1500,  // Vault/Outlet yerleşimi daha yüksek

            // ---- kargo (brüt, kuruş)
            'shipping' => [
                'de'   => ['label_key' => 'ship_de',   'cents' => 590,  'free_over' => 15000, 'days' => '1–3'],
                'eu'   => ['label_key' => 'ship_eu',   'cents' => 1290, 'free_over' => 25000, 'days' => '3–6'],
                'ch'   => ['label_key' => 'ship_ch',   'cents' => 1990, 'free_over' => 40000, 'days' => '4–8'],
                'world' => ['label_key' => 'ship_world', 'cents' => 2990, 'free_over' => 60000, 'days' => '5–12'],
            ],
            'shipping_countries_de' => ['DE'],
            'shipping_countries_eu' => ['AT','BE','BG','CY','CZ','DK','EE','ES','FI','FR','GR','HR','HU','IE','IT','LT','LU','LV','MT','NL','PL','PT','RO','SE','SI','SK'],
            'shipping_countries_ch' => ['CH','LI','NO','GB'],

            // ---- iade / cayma
            'return_days'        => 30,    // yasal 14 günün üstüne gönüllü 30 gün
            'withdrawal_days'    => 14,

            // ---- Vault (Premium Outlet) motoru
            'vault_step_hours'   => 24,
            'vault_steps'        => 6,
            'vault_member_head_start_hours' => 12,

            // ---- katalog
            'per_page'           => 24,
            'demo_catalog'       => true,  // listings.json yoksa vitrin boş kalmasın

            // ---- e-posta
            'mail_from'          => 'support@vestrasales.com',
            'mail_from_name'     => 'MAXSALES',
            'mail_bcc_ops'       => '',    // sipariş kopyası

            // ---- işletme
            'order_prefix'       => 'VS',
            'session_name'       => 'vestra_retail',
            'debug'              => false,
        ];

        $file = vr_data_dir() . '/retail-settings.json';
        if (is_readable($file)) {
            $over = json_decode((string)file_get_contents($file), true);
            if (is_array($over)) {
                // company gibi iç içe diziler tek tek birleşsin (komple ezilmesin).
                foreach ($over as $k => $v) {
                    $cfg[$k] = (is_array($v) && isset($cfg[$k]) && is_array($cfg[$k]))
                        ? array_replace_recursive($cfg[$k], $v)
                        : $v;
                }
            }
        }
    }

    if ($key === null) return $cfg;
    return $cfg[$key] ?? $default;
}

// -------------------------------------------------------- gizli anahtarlar
/**
 * Stripe anahtarları. Ortam > data/stripe_settings.json. Anahtar yoksa
 * ['configured' => false] döner ve çağıran taraf ödeme akışını kibarca kapatır.
 */
function vr_stripe_settings(): array
{
    static $s = null;
    if ($s !== null) return $s;

    $file = vr_data_dir() . '/stripe_settings.json';
    $json = is_readable($file) ? (json_decode((string)file_get_contents($file), true) ?: []) : [];
    if (!is_array($json)) $json = [];

    $pick = static function (string $env, string $key) use ($json): string {
        $v = getenv($env);
        if (is_string($v) && $v !== '') return trim($v);
        return is_string($json[$key] ?? null) ? trim((string)$json[$key]) : '';
    };

    $secret = $pick('STRIPE_SECRET_KEY', 'secret_key');
    $pub    = $pick('STRIPE_PUBLISHABLE_KEY', 'publishable_key');
    $whsec  = $pick('STRIPE_WEBHOOK_SECRET', 'webhook_secret');

    $s = [
        'secret_key'      => $secret,
        'publishable_key' => $pub,
        'webhook_secret'  => $whsec,
        // sk_test_… / sk_live_… anahtarın kendisi modu söyler; ayrı bayrak tutup
        // yanlış eşleşme riski almıyoruz.
        'mode'            => str_starts_with($secret, 'sk_live_') ? 'live'
                           : (str_starts_with($secret, 'sk_') ? 'test' : 'none'),
        'configured'      => $secret !== '',
        'webhook_ready'   => $whsec !== '',
    ];
    return $s;
}

/** Brevo (canlı sitedeki email_settings.json ile aynı dosyayı paylaşır). */
function vr_mail_settings(): array
{
    static $m = null;
    if ($m !== null) return $m;

    $file = vr_data_dir() . '/email_settings.json';
    $j = is_readable($file) ? (json_decode((string)file_get_contents($file), true) ?: []) : [];
    if (!is_array($j)) $j = [];

    $envKey = getenv('MAIL_API_KEY');
    $key    = (is_string($envKey) && $envKey !== '') ? $envKey : (string)($j['mail_api_key'] ?? '');

    $m = [
        'enabled'  => $key !== '' && ($j['mail_enabled'] ?? true),
        'provider' => (string)($j['mail_api_provider'] ?? 'brevo'),
        'api_key'  => $key,
        'from'     => (string)($j['mail_from'] ?? vr_config('mail_from')),
        'name'     => (string)($j['smtp_name'] ?? vr_config('mail_from_name')),
    ];
    return $m;
}

/** Impressum/AGB için zorunlu işletmeci alanları dolu mu? */
function vr_company_complete(): bool
{
    $c = vr_config('company');
    foreach (['street', 'city', 'country', 'reg_authority', 'reg_number', 'represented_by', 'email'] as $k) {
        if (trim((string)($c[$k] ?? '')) === '') return false;
    }
    return true;
}
