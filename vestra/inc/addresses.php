<?php
/**
 * ALICININ KAYITLI TESLİMAT ADRESLERİ (adres defteri) + tek adres biçimlendirici.
 *
 * Operatör, 24 Eyl 2026: "adreslerde postcode görünmüyor bu giriliyor mu? ayrıca
 * hesaplarda müşterilerin Lieferadresse girebileceği bölüm koy, estetik olarak
 * 1. 2. 3. olarak ve sipariş için kendileri seçebilsin, ad koyabilsin".
 *
 * Ölçüm (aynı gün, diag-live accounts_report): 161 alıcı hesabının 116'sında adres
 * var, yalnız 23'ünde posta kodu görünüyor. Sebep form: kayıt ve profil adresi TEK
 * serbest satır olarak alıyordu, posta kodunun ayrı bir yeri yoktu. Burada adres
 * ALAN ALAN tutuluyor ve posta kodu kendi kutusunda, zorunlu.
 *
 * YUVALAR SABİT (1, 2, 3), liste değil: "2." diye adlandırılmış bir adres, 1. silinince
 * "1." olmamalı. Kayıt {"1":{…},"3":{…}} biçiminde; boş yuva yok sayılır.
 */

if (!defined('VESTRA_SHIP_ADDR_MAX')) define('VESTRA_SHIP_ADDR_MAX', 3);

/** Hesabı id ile bulur (auth.php'de e-postayla bulan var, id ile bulan yok). */
function vestra_ship_addr_account(string $uid): ?array {
    foreach (auth_accounts() as $a) if ((string)($a['id'] ?? '') === $uid && $uid !== '') return $a;
    return null;
}

/** Alanlar ve üst sınırları. Tek liste: form, doğrulayıcı ve biçimlendirici buradan okur. */
function vestra_ship_addr_fields(): array {
    return ['label' => 40, 'recipient' => 80, 'street' => 120, 'postcode' => 16,
            'city' => 60, 'country' => 56, 'phone' => 32];
}

/**
 * Posta kodu vermeyen ülkeler. Liste BİLEREK dar ve eşleşme TAM (harf duyarsız):
 * alt dize eşleşmesi bu depoda mango/zara dersini doğurdu. Burada tanınmayan bir
 * ülke adı posta kodunu ZORUNLU bırakır -- yanlış yön görünür (alıcı bir kutu
 * doldurur), öteki yön görünmez (posta kodsuz bir kurye etiketi).
 */
function vestra_postcode_optional(string $country): bool {
    $c = mb_strtolower(trim($country));
    return in_array($c, ['ae', 'uae', 'u.a.e.', 'united arab emirates', 'emirates',
        'vereinigte arabische emirate', 'émirats arabes unis', 'emiratos árabes unidos',
        'emirati arabi uniti', 'qa', 'qatar', 'katar', 'hk', 'hong kong', 'hongkong',
        'mo', 'macau', 'macao'], true);
}

/**
 * Serbest metinde posta koduna benzeyen bir parça var mı (4-6 hane, UK, NL, CA, US ZIP+4,
 * PL 00-000, SE/CZ 000 00). Yalnız ÖLÇÜM ve UYARI için -- bir adresi reddetmek için
 * değil: dar bir kalıp gerçek bir adresi "posta kodsuz" sanabilir, o yüzden sonucu
 * yalnız operatöre bir işaret olarak gösterilir. Panel ve teşhis aynı gövdeyi çağırır.
 */
function vestra_address_has_postcode(string $text): bool {
    return (bool)preg_match('/\b\d{4,6}\b|\b[A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2}\b|\b\d{4}\s?[A-Z]{2}\b'
        .'|\b[A-Z]\d[A-Z]\s?\d[A-Z]\d\b|\b\d{5}-\d{4}\b|\b\d{2}-\d{3}\b|\b\d{3}\s\d{2}\b/iu', $text);
}

/** Şehri posta kodundan ÖNCE yazan ülkeler (GB/US/CA/AU biçimi). Tam eşleşme. */
function vestra_address_city_first(string $country): bool {
    $c = mb_strtolower(trim($country));
    return in_array($c, ['gb', 'uk', 'united kingdom', 'great britain', 'england', 'scotland', 'wales',
        'us', 'usa', 'united states', 'united states of america', 'ca', 'canada', 'au', 'australia'], true);
}

/** Girdiyi temizler: satır sonu yok (adres siparişin TEK satırlık notuna iniyor), sınır kırpılır. */
function vestra_ship_addr_clean(array $in): array {
    $out = [];
    foreach (vestra_ship_addr_fields() as $k => $max) {
        $v = trim((string)preg_replace('/\s+/u', ' ', (string)($in[$k] ?? '')));
        $out[$k] = mb_substr($v, 0, $max);
    }
    if ($out['postcode'] !== '') $out['postcode'] = mb_strtoupper($out['postcode']);
    return $out;
}

/**
 * Eksik/geçersiz alanların listesi (boş = geçerli). Adı (label) zorunlu DEĞİL:
 * boşsa kart "Address 2" der. Türkiye beyanı KURAL 2g ile reddedilir -- kapatan bir
 * kural her yolda çalışmalı; teslimat adresi yoluyla açık kalsaydı profilde
 * reddedilen pazar sipariş kutusundan geri girerdi.
 */
function vestra_ship_addr_errors(array $a): array {
    $err = [];
    foreach (['street', 'city', 'country'] as $k) if (trim((string)($a[$k] ?? '')) === '') $err[] = $k;
    if (trim((string)($a['postcode'] ?? '')) === '' && !vestra_postcode_optional((string)($a['country'] ?? '')))
        $err[] = 'postcode';
    if (function_exists('vestra_country_declares_turkey') && vestra_country_declares_turkey((string)($a['country'] ?? '')))
        $err[] = 'country_tr';
    return $err;
}

/** Hesabın adres defteri: yuva numarası (1..MAX) → temiz adres. Bozuk kayıt sessizce düşer. */
function vestra_ship_addresses(?array $acc): array {
    $out = [];
    $raw = is_array($acc['ship_addresses'] ?? null) ? $acc['ship_addresses'] : [];
    for ($s = 1; $s <= VESTRA_SHIP_ADDR_MAX; $s++) {
        $a = $raw[(string)$s] ?? ($raw[$s] ?? null);
        if (!is_array($a)) continue;
        $a = vestra_ship_addr_clean($a);
        if ($a['street'] === '' && $a['city'] === '') continue;
        $out[$s] = $a;
    }
    return $out;
}

/** "10115 Berlin" / GB-US-CA-AU'de "London NW1 6XE". Kart ve satır aynı gövdeden. */
function vestra_ship_addr_town(array $a): string {
    $pc = trim((string)($a['postcode'] ?? '')); $city = trim((string)($a['city'] ?? ''));
    return vestra_address_city_first((string)($a['country'] ?? '')) ? trim($city.' '.$pc) : trim($pc.' '.$city);
}

/**
 * TEK adres biçimlendirici: "Alıcı, Sokak No, 10115 Berlin, Germany, Tel +49…".
 * Kasanın siparişe yazdığı `Deliver to:` metni, alıcı panelindeki kart ve kasa
 * seçicisi hep bunu çağırır -- ikinci bir biçim, ekranda bir, belgede başka adres
 * demek olurdu.
 */
function vestra_ship_addr_line(array $a): string {
    $a = vestra_ship_addr_clean($a);
    $town = vestra_ship_addr_town($a);
    $parts = array_filter([$a['recipient'], $a['street'], $town, $a['country']], fn($v) => $v !== '');
    $line = implode(', ', $parts);
    if ($a['phone'] !== '') $line .= ', Tel '.$a['phone'];
    return $line;
}

/**
 * Hesabın FATURA adresi tek satır: serbest `address` + ayrı `postcode`/`city`
 * (panelin "Edit billing details" formu ikisini de yazıyordu ama HİÇBİR YER
 * okumuyordu -- toplanan ama basılmayan alan, KURAL 5j'nin dersi). Adres metni
 * posta kodunu/şehri zaten içeriyorsa tekrar eklenmez.
 */
function vestra_account_billing_line(?array $acc): string {
    $addr = trim((string)preg_replace('/\s+/u', ' ', (string)($acc['address'] ?? '')));
    $pc   = trim((string)($acc['postcode'] ?? ''));
    $city = trim((string)($acc['city'] ?? ''));
    $has  = fn(string $v) => $v === '' || ($addr !== '' && mb_stripos($addr, $v) !== false);
    $tail = trim(($has($pc) ? '' : $pc).' '.($has($city) ? '' : $city));
    if ($tail === '') return $addr;
    return $addr === '' ? $tail : $addr.', '.$tail;
}

/**
 * Bir yuvayı yazar. Dönüş: ['ok'=>true] ya da ['error'=>kod, 'fields'=>[…]].
 * auth_update() void döner ve yazamazsa sessiz kalır; bu yüzden kayıt GERİ OKUNUR
 * (billing_saved'ın bu depodaki dersi).
 */
function vestra_ship_addr_save(string $uid, int $slot, array $in): array {
    if ($slot < 1 || $slot > VESTRA_SHIP_ADDR_MAX) return ['error' => 'slot'];
    $a = vestra_ship_addr_clean($in);
    $err = vestra_ship_addr_errors($a);
    if ($err) return ['error' => in_array('country_tr', $err, true) ? 'country' : 'fields', 'fields' => $err];
    $acc = vestra_ship_addr_account($uid);
    if (!$acc) return ['error' => 'account'];
    $book = is_array($acc['ship_addresses'] ?? null) ? $acc['ship_addresses'] : [];
    $a['updated_at'] = date('c');
    $book[(string)$slot] = $a;
    ksort($book);
    auth_update($uid, ['ship_addresses' => $book]);
    $back = vestra_ship_addresses(vestra_ship_addr_account($uid));
    if (!isset($back[$slot]) || vestra_ship_addr_line($back[$slot]) !== vestra_ship_addr_line($a))
        return ['error' => 'write'];
    return ['ok' => true];
}

/** Bir yuvayı siler (geri okunur). */
function vestra_ship_addr_delete(string $uid, int $slot): array {
    if ($slot < 1 || $slot > VESTRA_SHIP_ADDR_MAX) return ['error' => 'slot'];
    $acc = vestra_ship_addr_account($uid);
    if (!$acc) return ['error' => 'account'];
    $book = is_array($acc['ship_addresses'] ?? null) ? $acc['ship_addresses'] : [];
    unset($book[(string)$slot], $book[$slot]);
    $patch = ['ship_addresses' => $book];
    if ((string)($acc['ship_last'] ?? '') === (string)$slot) $patch['ship_last'] = '';
    auth_update($uid, $patch);
    return isset(vestra_ship_addresses(vestra_ship_addr_account($uid))[$slot]) ? ['error' => 'write'] : ['ok' => true];
}

/**
 * Kasanın seçimini sunucuda çözer. Tarayıcıdan gelen ADRES METNİNE güvenilmez:
 * yalnız yuva numarası gelir, metin hesabın kendi kaydından kurulur.
 * Dönüş: ['address'=>metin] | ['error'=>kod]. $pick: 'billing' | '1'..'3' | 'other'.
 */
function vestra_ship_addr_resolve(?array $acc, string $pick, string $otherText): array {
    $pick = trim($pick);
    if ($pick === '' || $pick === 'other') return ['address' => trim((string)preg_replace('/\s+/u', ' ', $otherText))];
    if ($pick === 'billing') return ['address' => ''];
    if (!ctype_digit($pick)) return ['error' => 'pick'];
    $book = vestra_ship_addresses($acc);
    $slot = (int)$pick;
    if (!isset($book[$slot])) return ['error' => 'missing'];
    if (vestra_ship_addr_errors($book[$slot])) return ['error' => 'incomplete'];
    return ['address' => vestra_ship_addr_line($book[$slot]), 'slot' => $slot];
}
