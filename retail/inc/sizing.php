<?php

/**
 * Beden tabloları — TEK KAYNAK.
 *
 * Tablolar eskiden yalnızca size-guide.php'nin içindeydi ve başka hiçbir yer
 * onlara bakamıyordu. Sonuç: ürün sayfasındaki beden çipinde kuru bir "M"
 * yazıyordu, oysa aynı sitenin beden rehberi o "M"in ne demek olduğunu zaten
 * yayımlıyordu (alt giyimde İtalyan 48, bel 80–84 cm).
 *
 * Bu özellikle denimde önemliydi. Kot pantolonun S/M/L ile satılması alışılmış
 * değil — denim İtalyan 44-54 ya da bel ölçüsüyle ölçülür — ama etiket anlamsız
 * değildi: karşılığı bu tabloda yazıyordu, sadece gösterilmiyordu. Doğru
 * çözüm satırı satıştan çekmek değil, karşılığını çipin üstüne yazmak.
 *
 * Alt giyim ile üst giyimin alfa↔İtalyan eşlemesi FARKLI: üst giyimde beden
 * göğüs ölçüsünün yarısı (M = IT 48 = 94-98 cm göğüs), alt giyimde bel
 * ölçüsünden türüyor (M = IT 48 = 80-84 cm bel = 31 inç). Bu yüzden eşleme
 * kategoriye göre seçiliyor.
 *
 * Kaynak: İtalyan konfeksiyon standardı (beden = göğüs cm ÷ 2) ve markaların
 * yayımladığı dönüşümler; D&G plaj giyimi ve iç giyimde XS-XXXXL alfa sistemi
 * kullanıyor ve onu IT 44-58 ile eşliyor, yani mayo/boxer'daki alfa etiket
 * üreticinin KENDİ sistemi, besleme uydurması değil.
 */

declare(strict_types=1);

/** Kategori hangi tabloya düşüyor: 'bottoms' | 'shoes' | 'belts' | 'tops' */
function vr_size_table_for(string $cat): string
{
    $k = mb_strtolower($cat);
    return match (true) {
        (bool)preg_match('/jean|denim|trouser|pant|short|hose/u', $k) => 'bottoms',
        (bool)preg_match('/shoe|sneaker|schuh|boot/u', $k)            => 'shoes',
        (bool)preg_match('/belt|gürtel/u', $k)                        => 'belts',
        default                                                        => 'tops',
    };
}

/**
 * Ham beden tabloları. Sütunlar tablo başına sabit:
 *   tops    : IT, DE/EU, FR, UK/US, Intl, göğüs, bel
 *   bottoms : IT, DE/EU, inç, UK/US, Intl, bel, kalça
 */
function vr_size_rows(string $table): array
{
    static $t = [
        'tops' => [
            ['44', '44', '38', '34', 'XS',  '86–90',   '72–76'],
            ['46', '46', '40', '36', 'S',   '90–94',   '76–80'],
            ['48', '48', '42', '38', 'M',   '94–98',   '80–84'],
            ['50', '50', '44', '40', 'L',   '98–102',  '84–88'],
            ['52', '52', '46', '42', 'XL',  '102–107', '88–94'],
            ['54', '54', '48', '44', 'XXL', '107–112', '94–100'],
            ['56', '56', '50', '46', '3XL', '112–118', '100–106'],
        ],
        'bottoms' => [
            ['44', '44', '28', '28', 'XS',  '72–76',   '88–92'],
            ['46', '46', '30', '30', 'S',   '76–80',   '92–96'],
            ['48', '48', '31', '31', 'M',   '80–84',   '96–100'],
            ['50', '50', '32', '32', 'L',   '84–88',   '100–104'],
            ['52', '52', '34', '34', 'XL',  '88–94',   '104–109'],
            ['54', '54', '36', '36', 'XXL', '94–100',  '109–114'],
            ['56', '56', '38', '38', '3XL', '100–106', '114–119'],
        ],
    ];
    return $t[$table] ?? [];
}

/**
 * Bir beden etiketinin karşılığı.
 *
 * Etiket alfa ("M") ya da İtalyan ("48") olabilir; ikisi de aynı satıra
 * götürüyor. Karşılığı yoksa null döner ve çağıran hiçbir şey göstermez —
 * uydurma yok.
 *
 * Dönüş: ['it' => '48', 'inch' => '31', 'waist' => '80–84', 'chest' => '94–98']
 * (chest yalnızca üst giyimde, inch yalnızca alt giyimde dolu.)
 */
function vr_size_equiv(string $cat, string $label): ?array
{
    $label = strtoupper(trim($label));
    if ($label === '' || $label === 'ONE') return null;

    $table = vr_size_table_for($cat);
    if ($table !== 'tops' && $table !== 'bottoms') return null;

    // "2XL" ve "XXL" aynı beden; besleme ikisini de kullanıyor.
    if ($label === '2XL') $label = 'XXL';
    if ($label === '3XL') $label = '3XL';

    foreach (vr_size_rows($table) as $r) {
        if ($label !== $r[0] && $label !== strtoupper($r[4])) continue;
        return $table === 'bottoms'
            ? ['it' => $r[0], 'inch' => $r[2], 'waist' => $r[5], 'chest' => '', 'intl' => $r[4]]
            : ['it' => $r[0], 'inch' => '',    'waist' => $r[6], 'chest' => $r[5], 'intl' => $r[4]];
    }
    return null;
}

/**
 * Çipin altına yazılacak tek satırlık karşılık.
 *
 * Alt giyimde bel ölçüsü belirleyici, o yüzden İtalyan beden + inç gösteriliyor
 * ("IT 48 · 31\""). Üst giyimde İtalyan beden tek başına yeterli ve satır kısa
 * kalıyor. Etiket zaten İtalyan sayıysa kendini tekrar etmiyor.
 */
function vr_size_equiv_line(string $cat, string $label): string
{
    $e = vr_size_equiv($cat, $label);
    if ($e === null) return '';

    $label = strtoupper(trim($label));
    if ($label === $e['it']) {
        // Etiket zaten İtalyan: karşılık olarak alfa ve inç daha bilgilendirici.
        return vr_size_table_for($cat) === 'bottoms'
            ? $e['intl'] . ' · ' . $e['inch'] . '"'
            : $e['intl'];
    }
    return vr_size_table_for($cat) === 'bottoms'
        ? 'IT ' . $e['it'] . ' · ' . $e['inch'] . '"'
        : 'IT ' . $e['it'];
}
