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
    return ['AR','BO','BR','CL','CO','EC','GY','PY','PE','SR','UY','VE',  // Güney Amerika
            'JP','AU','SG','HK',                                           // + Asya-Pasifik
            'CZ','PL'];                                                    // + Orta Avrupa (13 Eyl 2026)
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
    return $cc === '' ? 0.0 : (float)VESTRA_REGION_DISCOUNT_PCT;
}

/** İndirimin tutar karşılığı. Yuvarlama TEK YERDE: çağıranların her biri kendi
 *  round()'unu yazarsa sepet ile fatura bir kuruş ayrışır (KURAL 5m'nin KDV
 *  dersi — net aşağı yuvarlanır, fark tek yerden hesaplanır). */
function vestra_region_discount_amount(float $subtotal, float $pct): float {
    if ($subtotal <= 0 || $pct <= 0) return 0.0;
    return round($subtotal * $pct / 100, 2);
}
