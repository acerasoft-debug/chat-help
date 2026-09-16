<?php
/**
 * VESTRA — bölgesel KALICI indirim (operatör, 13 Eyl 2026:
 * *"Güney Amerika, Japonya, Avustralya, Singapur ve Hongkong'a yüzde 10 indirim
 * uygula daimi sadece buradan kayıtli ülkelere"*).
 *
 * TEK KARAR NOKTASI. İndirimi hak eden kim sorusunun cevabı yalnız burada;
 * fiyat gösteren ve fiyat TAHSİL EDEN yollar aynı fonksiyonu çağırır. Bu depoda
 * "sayfa bir şey diyor, kasa başkasını alıyor" en az üç kez yaşandı (escrow
 * tavanı KURAL 6, L1212'nin list/kademe ayrışması, katalog zammı).
 *
 * BEYAN EDİLEN ÜLKE, IP DEĞİL — operatörün kendi şartı ("buradan kayıtlı
 * ülkelere"). Aynı gerekçe vestra_auto_open_countries()'de yazılı: IP tek bir
 * istek için doğrudur, seyahat eden alıcı indirimi kaybeder, Tokyo VPN'i olan
 * herkes kazanır. Beyan edilen ülke hesapla birlikte yaşar ve gerçek bir ticari
 * kaydın da yazmak zorunda olduğu şeydir.
 *
 * TAM EŞLEŞME, ASLA ALT DİZE. Bu listede yanlış pozitif = hiç karar verilmemiş
 * bir firmaya kalıcı %10. Depodaki mango/zara dersi (kısa ad başka adın içinde)
 * ve Turkey/Turkmenistan ayrımı aynı sebeple böyle yazıldı.
 */

require_once __DIR__.'/security.php';   // vestra_auto_open_countries()

/** Kalıcı bölgesel indirim yüzdesi. TEK SABİT: metne gömülmez, mektup ve sayfa
 *  bunu okur (KURAL 6'nın escrow tavanı dersi — beş gün boyunca müşteriye
 *  söylenen ile sepetin uyguladığı ayrı kalmıştı). */
if (!defined('VESTRA_REGION_DISCOUNT_PCT')) define('VESTRA_REGION_DISCOUNT_PCT', 10.0);

/* AFRİKA: %8 (operatör, 16 Eyl 2026: *"Afrika bölgesine toplam katalogtan
   yüzde 8 indirim yapılacağını belirt"*). Oran %10'dan AYRI bir sabit, çünkü
   iki ayrı operatör kararı: birini değiştirmek diğerini değiştirmemeli.
   Bu yüzden `vestra_region_discount_pct()` artık tek bir sabit dönmüyor,
   ülkenin KENDİ oranını okuyor — tek oranlı hâlinde Afrika'yı eklemek
   Güney Amerika'yı da %8'e çekerdi. */
if (!defined('VESTRA_AFRICA_DISCOUNT_PCT')) define('VESTRA_AFRICA_DISCOUNT_PCT', 8.0);

/**
 * İndirim kapsamındaki ülkeler: ISO 3166-1 alpha-2.
 *
 * Güney Amerika KITASI 12 egemen ülke olarak yazıldı, "kıta" diye bir alan
 * olmadığı için: Arjantin, Bolivya, Brezilya, Şili, Kolombiya, Ekvador,
 * Guyana, Paraguay, Peru, Surinam, Uruguay, Venezuela.
 *
 * FRANSIZ GUYANASI (GF) BİLEREK YOK: coğrafyası Güney Amerika ama Fransa'nın
 * denizaşırı bölgesi, yani AB gümrük alanı ve euro. "Güney Amerika" derken
 * kastedilenin bu olmadığını varsayıyorum; operatör isterse tek satır.
 * ÇİN ANAKARASI (CN) DA YOK: istenen Hong Kong'du, ikisi ayrı gümrük alanı.
 */
function vestra_region_discount_codes(): array {
    return array_keys(vestra_region_discount_rates());
}

/**
 * Ülke → indirim YÜZDESİ. Kapsam ve oran TEK tabloda duruyor: ayrı bir
 * "kapsam listesi" + ayrı bir "oran listesi" tutmak, listeye eklenip orana
 * eklenmeyen (ya da tersi) bir ülke demekti — bu depoda aynı sınıf hata
 * `invoice_vat_rate`'in birleşik çubukta yazılmamasıyla bir kez yaşandı:
 * alan kardeşlerinin bulunduğu HER yerde olmalı.
 *
 * %10 — Güney Amerika + Asya-Pasifik + Orta Avrupa (31 Ağu / 13 Eyl 2026).
 * %8  — Afrika, 54 ülke (16 Eyl 2026).
 */
function vestra_region_discount_rates(): array {
    static $r = null;
    if ($r !== null) return $r;
    $r = [];
    foreach (['AR','BO','BR','CL','CO','EC','GY','PY','PE','SR','UY','VE',  // Güney Amerika
              'JP','AU','SG','HK',                                          // + Asya-Pasifik
              'CZ','PL'] as $cc) $r[$cc] = (float)VESTRA_REGION_DISCOUNT_PCT;
    foreach (array_keys(vestra_africa_names()) as $cc) $r[$cc] = (float)VESTRA_AFRICA_DISCOUNT_PCT;
    return $r;
}

/**
 * Afrika ülkelerinin yazımları — Afrika Birliği'nin 54 üyesi.
 *
 * NEDEN AYRI TABLO: bu ülkelerin çoğunda kayıt formuna yazılacak ad
 * İNGİLİZCE ya da FRANSIZCA (Benin, Senegal, Fas frankofon; Angola,
 * Mozambik lusofon; Kuzey Afrika arapça) ve tek dilli bir liste, gerçek
 * müşteriyi tam da indirimi hak ettiği anda kapsam dışı bırakırdı.
 * Eşleşme yine TAM — alt dize değil: 'Niger' ile 'Nigeria', 'Guinea' ile
 * 'Equatorial Guinea'/'Guinea-Bissau', 'Congo' ile 'DR Congo', 'Sudan' ile
 * 'South Sudan' ayrı ülkeler ve alt dize eşleşmesi dördünü de karıştırırdı
 * (mango/zara dersinin coğrafya hâli). Uzun ad ile kısa ad AYNI listede
 * durduğu için sıra da önemli değil.
 *
 * KAPSAM DIŞI, bilerek: Réunion/Mayotte (FR), Kanarya Adaları/Ceuta/Melilla
 * (ES) — coğrafyaları Afrika ama AB gümrük alanı ve euro; Fransız
 * Guyanası'nın Güney Amerika listesinde olmamasıyla aynı gerekçe.
 * Batı Sahra (EH) da yok: tanınma durumu tartışmalı ve ticari kaydı yok.
 */
function vestra_africa_names(): array {
    static $t = [
        'DZ' => ['algeria', 'algérie', 'algerie', 'people\'s democratic republic of algeria', 'الجزائر'],
        'AO' => ['angola', 'republic of angola', 'república de angola'],
        'BJ' => ['benin', 'bénin', 'republic of benin', 'république du bénin'],
        'BW' => ['botswana', 'republic of botswana'],
        'BF' => ['burkina faso', 'burkina'],
        'BI' => ['burundi', 'republic of burundi', 'république du burundi'],
        'CV' => ['cabo verde', 'cape verde', 'cap vert', 'cap-vert', 'cabo verde republic'],
        'CM' => ['cameroon', 'cameroun', 'republic of cameroon', 'république du cameroun'],
        'CF' => ['central african republic', 'république centrafricaine', 'centrafrique'],
        'TD' => ['chad', 'tchad', 'republic of chad', 'république du tchad'],
        'KM' => ['comoros', 'comores', 'union of the comoros', 'جزر القمر'],
        'CG' => ['congo', 'republic of the congo', 'congo brazzaville', 'congo-brazzaville', 'république du congo'],
        'CD' => ['democratic republic of the congo', 'dr congo', 'drc', 'congo kinshasa', 'congo-kinshasa', 'république démocratique du congo', 'rdc'],
        'CI' => ['ivory coast', 'côte d\'ivoire', 'cote d\'ivoire', 'cote divoire', 'republic of côte d\'ivoire'],
        'DJ' => ['djibouti', 'republic of djibouti', 'جيبوتي'],
        'EG' => ['egypt', 'égypte', 'egypte', 'arab republic of egypt', 'مصر'],
        'GQ' => ['equatorial guinea', 'guinée équatoriale', 'guinea ecuatorial'],
        'ER' => ['eritrea', 'érythrée', 'erythree', 'state of eritrea', 'إريتريا'],
        'SZ' => ['eswatini', 'swaziland', 'kingdom of eswatini'],
        'ET' => ['ethiopia', 'éthiopie', 'ethiopie', 'federal democratic republic of ethiopia'],
        'GA' => ['gabon', 'gabonese republic', 'république gabonaise'],
        'GM' => ['gambia', 'the gambia', 'gambie', 'republic of the gambia'],
        'GH' => ['ghana', 'republic of ghana'],
        'GN' => ['guinea', 'guinée', 'guinee', 'republic of guinea', 'république de guinée'],
        'GW' => ['guinea bissau', 'guinea-bissau', 'guinée bissau', 'guiné-bissau'],
        'KE' => ['kenya', 'republic of kenya'],
        'LS' => ['lesotho', 'kingdom of lesotho'],
        'LR' => ['liberia', 'republic of liberia'],
        'LY' => ['libya', 'libye', 'state of libya', 'ليبيا'],
        'MG' => ['madagascar', 'republic of madagascar', 'république de madagascar'],
        'MW' => ['malawi', 'republic of malawi'],
        'ML' => ['mali', 'republic of mali', 'république du mali'],
        'MR' => ['mauritania', 'mauritanie', 'islamic republic of mauritania', 'موريتانيا'],
        'MU' => ['mauritius', 'maurice', 'republic of mauritius', 'île maurice'],
        'MA' => ['morocco', 'maroc', 'kingdom of morocco', 'royaume du maroc', 'المغرب'],
        'MZ' => ['mozambique', 'moçambique', 'republic of mozambique'],
        'NA' => ['namibia', 'namibie', 'republic of namibia'],
        'NE' => ['niger', 'republic of the niger', 'république du niger'],
        'NG' => ['nigeria', 'nigéria', 'federal republic of nigeria'],
        'RW' => ['rwanda', 'republic of rwanda', 'république du rwanda'],
        'ST' => ['sao tome and principe', 'são tomé and príncipe', 'sao tome et principe', 'são tomé e príncipe'],
        'SN' => ['senegal', 'sénégal', 'republic of senegal', 'république du sénégal'],
        'SC' => ['seychelles', 'republic of seychelles'],
        'SL' => ['sierra leone', 'republic of sierra leone'],
        'SO' => ['somalia', 'somalie', 'federal republic of somalia', 'الصومال'],
        'ZA' => ['south africa', 'afrique du sud', 'republic of south africa', 'rsa', 'suid-afrika'],
        'SS' => ['south sudan', 'soudan du sud', 'republic of south sudan'],
        'SD' => ['sudan', 'soudan', 'republic of the sudan', 'السودان'],
        'TZ' => ['tanzania', 'tanzanie', 'united republic of tanzania'],
        'TG' => ['togo', 'togolese republic', 'république togolaise'],
        'TN' => ['tunisia', 'tunisie', 'republic of tunisia', 'تونس'],
        'UG' => ['uganda', 'ouganda', 'republic of uganda'],
        'ZM' => ['zambia', 'zambie', 'republic of zambia'],
        'ZW' => ['zimbabwe', 'republic of zimbabwe'],
    ];
    return $t;
}

/**
 * Yalnız BU listeye özel yazım tablosu. JP/AU/SG zaten
 * vestra_auto_open_countries()'de küratörlü duruyor ve oradan okunuyor —
 * ikinci bir kopya çıkarmak, aynı ülkenin iki ayrı yazım listesi demekti ve
 * bu depo o hatayı bir kez yaşadı (vestra_cc_of_country'de Suudi Arabistan ile
 * Singapur eksikti, yani kapısı kendiliğinden açılan iki ülke tam da
 * karşılaştırılması gereken yerde hiç karşılaştırılmıyordu).
 */
function vestra_region_discount_names(): array {
    static $t = [
        'AR' => ['argentina', 'argentine republic', 'argentinien', 'argentine',
                 'аргентина', 'الأرجنتين', 'arjantin'],
        'BO' => ['bolivia', 'plurinational state of bolivia', 'bolivien', 'bolivie',
                 'боливия', 'بوليفيا', 'bolivya'],
        'BR' => ['brazil', 'brasil', 'federative republic of brazil', 'brasilien',
                 'brésil', 'bresil', 'brasile', 'бразилия', 'ブラジル', 'البرازيل',
                 'brezilya'],
        'CL' => ['chile', 'republic of chile', 'chili', 'cile', 'чили', 'تشيلي', 'şili', 'sili'],
        'CO' => ['colombia', 'republic of colombia', 'kolumbien', 'colombie',
                 'колумбия', 'كولومبيا', 'kolombiya'],
        'EC' => ['ecuador', 'republic of ecuador', 'équateur', 'equateur', 'эквадор',
                 'الإكوادور', 'ekvador'],
        'GY' => ['guyana', 'co-operative republic of guyana', 'гайана', 'غيانا'],
        'PY' => ['paraguay', 'republic of paraguay', 'парагвай', 'باراغواي'],
        'PE' => ['peru', 'perú', 'republic of peru', 'pérou', 'perou', 'перу', 'بيرو'],
        'SR' => ['suriname', 'surinam', 'republic of suriname', 'суринам', 'سورينام'],
        'UY' => ['uruguay', 'oriental republic of uruguay', 'уругвай', 'أوروغواي'],
        'VE' => ['venezuela', 'bolivarian republic of venezuela', 'венесуэла',
                 'فنزويلا', 'venezuela'],
        /* Hong Kong: bu depoda GERÇEK bir vaka var — VES-6B53D265'in alıcısı
           香港风徕贸易有限公司 ve vergi alanına "中国香港特别行政区" yazmıştı
           (KURAL 5h). Çince yazımlar o yüzden tabloda. */
        /* Çekya / Polonya (operatör, 13 Eyl 2026: *"Česko ve polonyaya da yüzde 10
           indirim yap oradan girilirse"*). Operatör "girilirse" dedi; ölçüt yine
           KAYITLI ülke — tek indirime iki ayrı ölçüt (biri IP, biri beyan)
           koymak aynı alıcıya bugün indirimli, yarın indirimsiz fiyat verirdi.
           Çek yazımı hem aksanlı hem aksansız: form serbest metin. */
        'CZ' => ['czechia', 'czech republic', 'the czech republic',
                 'česko', 'cesko', 'česká republika', 'ceska republika',
                 'tschechien', 'tschechische republik',                    // de
                 'tchéquie', 'tchequie', 'république tchèque',             // fr
                 'repubblica ceca', 'cechia',                              // it
                 'chequia', 'república checa', 'republica checa',          // es
                 'tchéquia', 'tchequia',                                   // pt
                 'чехия', 'чешская республика',                            // ru
                 'チェコ',                                                  // ja
                 'التشيك', 'جمهورية التشيك',                               // ar
                 'çekya', 'cekya', 'çek cumhuriyeti'],                     // tr
        'PL' => ['poland', 'polska', 'rzeczpospolita polska',
                 'republic of poland', 'polen',                            // de / nl
                 'pologne',                                                // fr
                 'polonia',                                                // it / es
                 'polônia', 'polonia',                                     // pt
                 'польша',                                                 // ru
                 'ポーランド',                                               // ja
                 'بولندا',                                                 // ar
                 'polonya'],                                               // tr
        'HK' => ['hong kong', 'hongkong', 'hong kong sar', 'hong kong sar china',
                 'hong kong s.a.r.', 'hong kong (china)', 'hongkong sar',
                 '香港', '中國香港', '中国香港', '中国香港特别行政区',
                 'гонконг', 'هونغ كونغ', 'hong kong özel idare bölgesi'],
    ];
    return $t;
}

/**
 * Bu ham ülke alanı indirim kapsamındaki hangi ülkeyi beyan ediyor?
 * ISO kodu ya da '' döner. '' aynı zamanda okunamayan/boş değerin cevabı:
 * belirsizlik indirim VERMEZ — ters yön, hiç karar verilmemiş bir firmaya
 * kalıcı indirim demekti.
 */
function vestra_country_region_discount_cc(string $raw): string {
    $raw = trim($raw);
    if ($raw === '') return '';
    $codes = vestra_region_discount_codes();

    /* Çıplak ISO kodu: kayıt formunun kendi örneği 'DE' şeklinde, yani form
       tam bunu istiyor. TUZAK: 'CH' İsviçre, 'CL' Şili — biri kapsamda,
       diğeri değil; 'AT' Avusturya, 'AU' Avustralya; 'SA' Suudi Arabistan
       otomatik açılan ülke ama indirim listesinde DEĞİL. Tam eşleşme bunların
       hepsini kendiliğinden ayırıyor. */
    if (preg_match('/^[A-Za-z]{2}$/', $raw)) {
        $up = strtoupper($raw);
        return in_array($up, $codes, true) ? $up : '';
    }

    $folded = mb_strtolower($raw);
    $folded = strtr($folded, ['-' => ' ', '_' => ' ', '.' => '.']);
    $folded = trim(preg_replace('/\s+/u', ' ', $folded));
    if ($folded === '') return '';

    foreach (vestra_region_discount_names() as $cc => $names) {
        if (in_array($folded, $names, true)) return $cc;
    }
    /* Afrika tablosu AYRI ama aynı kapıdan geçiyor: iki ayrı çözücü yazmak,
       bir gün birine eklenip diğerine eklenmeyen bir yazım demekti. */
    foreach (vestra_africa_names() as $cc => $names) {
        if (in_array($folded, $names, true)) return $cc;
    }
    /* JP/AU/SG: kendi yazım tablosunu KOPYALAMIYORUZ, var olanı okuyoruz.
       Dönen kod indirim listesinde mi diye ayrıca süzülüyor — o tablo Suudi
       Arabistan'ı da taşıyor ve o ülke indirim kapsamında değil. */
    foreach (vestra_auto_open_countries() as $cc => $names) {
        if (in_array($cc, $codes, true) && in_array($folded, $names, true)) return $cc;
    }
    return '';
}

/**
 * Bu hesabın kalıcı bölgesel indirimi (yüzde). Hesap yoksa / ülke kapsam
 * dışıysa 0.0.
 *
 * Girdi bilerek HESAP DİZİSİ: çağıran taraf 'country' alanını kendi okuyup
 * geçmesin diye. Alan adı bir gün değişirse (bu depoda `vat` ile `vat_id`
 * tam bunu yaşattı — teşhis her hesap için "vat=(yok)" basıyordu) tek yer
 * düzeltilir.
 */
function vestra_region_discount_pct(?array $user): float {
    if (!is_array($user)) return 0.0;
    $cc = vestra_country_region_discount_cc((string)($user['country'] ?? ''));
    /* Oran ÜLKENİN KENDİ oranı: tek sabit dönen eski hâli, Afrika eklenince
       Güney Amerika'yı da sessizce %8'e çekerdi. */
    return $cc === '' ? 0.0 : (float)(vestra_region_discount_rates()[$cc] ?? 0.0);
}

/** İndirimin tutar karşılığı. Yuvarlama TEK YERDE: çağıranların her biri kendi
 *  round()'unu yazarsa sepet ile fatura bir kuruş ayrışır (KURAL 5m'nin KDV
 *  dersi — net aşağı yuvarlanır, fark tek yerden hesaplanır). */
function vestra_region_discount_amount(float $subtotal, float $pct): float {
    if ($subtotal <= 0 || $pct <= 0) return 0.0;
    return round($subtotal * $pct / 100, 2);
}

/* ── BAKANIN indirimi ─────────────────────────────────────────────────────────
 *
 * Fiyat bu sitede ZATEN yalnız girişli ve onaylı hesaba basılıyor (KURAL 19 —
 * fiyat listesi girişsiz açılmaz; ürün sayfasının JSON-LD'si fiyat yaymıyor).
 * Yani fiyatın çizildiği her an "bakan kim" sorusunun bir cevabı var ve
 * Googlebot'a başka fiyat gösterme tehlikesi YOK. İndirim bu yüzden çağıran
 * tarafa değil, fiyatı üreten fonksiyonun kendisine konabiliyor: bir sayfayı
 * atlarsak hata "indirim görünmedi" olur, "sayfa bir şey der kasa başkasını
 * alır" OLMAZ. Ters kurgu (her sayfaya tek tek eklemek) tam o ayrışmayı
 * üretirdi ve bu depo onu üç kez yaşadı.
 *
 * İSTİSNA KISA VE AÇIK: operatör paneli, satıcı paneli ve journal HAM fiyatı
 * ister — operatöre indirimli rakam göstermek, sattığı malın fiyatını yanlış
 * bilmesi demek. O çağrılar $raw=true geçiyor ve test bunu denetliyor.
 */

/** Oturumdaki hesabın indirimi. İstek başına BİR kez çözülür: fiyat bir sayfada
 *  yüzlerce kez okunuyor ve her okumada hesap dosyasını taramak sayfayı yavaşlatırdı. */
function vestra_viewer_discount_pct(): float {
    static $pct = null;
    if ($pct !== null) return $pct;
    $pct = 0.0;
    /* CLI için AYRI bir kapı YOK: cron ve teşhisin oturumu olmadığı için
       auth_user() zaten null döner ve sonuç 0 olur. Önce buraya
       `PHP_SAPI==='cli' -> 0` diye bir kestirme koymuştum; davranış aynıydı
       ama ZİNCİRİ ÖLÇÜLEMEZ yapıyordu — kum havuzu ölçümü her ülkede
       indirimsiz fiyat gösterdi ve testin "CLI indirim uygulamaz" iddiası
       buna rağmen yeşildi. Ölçemediğim bir yolu doğru sanmak, bu depoda
       yedi kez yaşanan "kontrol yanlış yere bakıyor" hatasının ta kendisi. */
    if (!function_exists('auth_user')) return $pct;
    $u = auth_user();
    $pct = vestra_region_discount_pct(is_array($u) ? $u : null);
    return $pct;
}

/** Ham fiyata bakanın indirimini uygular. Yuvarlama TEK YERDE. */
function vestra_price_after_region(float $price): float {
    $pct = vestra_viewer_discount_pct();
    if ($price <= 0 || $pct <= 0) return $price;
    return round($price * (100 - $pct) / 100, 2);
}
