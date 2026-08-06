<?php
/**
 * Ürün metni — perakende ağzı
 * ===========================
 * Toptan katalogdaki açıklama şuna benziyor:
 *
 *   "Original BALMAIN, model AH0EG000BC27. S×1 · M×3 · L×3 · 10/pack.
 *    EEA stock with full invoice trail."
 *
 * Bu bir depo satırı; alıcıya hiçbir şey anlatmıyor. Burası onu perakende
 * metnine çeviriyor: parçanın ne olduğu, evin karakteri, nasıl kalıplandığı,
 * neyle geldiği, nasıl bakılacağı.
 *
 * TEK KURAL — UYDURMA YOK.
 * Elimizde marka, kategori, model kodu, beden dökümü, durum ve herkunft var.
 * Renk, kumaş bileşimi, üretim yeri, sezon gibi DOĞRULAYAMADIĞIMIZ hiçbir şey
 * yazılmıyor. Bakım tavsiyesi kategoriye göre veriliyor ve "tavsiye" olarak
 * kuruluyor — ürün iddiası değil. Tedarikçinin kendi açıklaması varsa
 * korunuyor ve metnin içine yerleştiriliyor.
 *
 * Operatör bir ürüne kendi metnini yazdıysa (satırda 'copy' alanı) bu motor
 * devre dışı kalır — elle yazılmış metin her zaman kazanır.
 */

declare(strict_types=1);

require_once __DIR__ . '/boot.php';

/**
 * Evlerin karakteri ve kalıp davranışı.
 * Kalıp notları Größenberatung'daki tabloyla AYNI kaynaktan gelmeli — iki yerde
 * farklı şey yazması, bedeni ona göre seçen müşteriyi yanıltır.
 */
function vr_house_notes(): array
{
    return [
        'LACOSTE' => [
            'de' => ['Französisches Haus, 1933 aus dem Tennissport heraus gegründet; das Petit Piqué stammt von dort.', 'gerade, sportliche Passform — entspricht der Tabelle'],
            'en' => ['A French house founded out of tennis in 1933; the petit piqué comes from there.', 'a straight, sporting fit — true to the table'],
        ],
        'RALPH LAUREN' => [
            'de' => ['Amerikanisches Haus, das aus dem Sportswear-Kanon einen eigenen Stil gemacht hat.', 'großzügig geschnitten — im Zweifel eine Nummer kleiner'],
            'en' => ['An American house that made its own idiom out of the sportswear canon.', 'cut generously — size down if in doubt'],
        ],
        'HUGO BOSS' => [
            'de' => ['Deutsches Haus, seit den 1970ern für nüchtern geschnittene Konfektion bekannt.', 'schmale, aber nicht enge Passform — entspricht der Tabelle'],
            'en' => ['A German house known since the 1970s for soberly cut tailoring.', 'a slim but not tight fit — true to the table'],
        ],
        'TOMMY HILFIGER' => [
            'de' => ['Amerikanisches Haus mit Preppy-Wurzeln und gleichbleibenden Schnitten.', 'entspannte Passform — entspricht der Tabelle'],
            'en' => ['An American house with preppy roots and consistent cuts.', 'a relaxed fit — true to the table'],
        ],
        'CALVIN KLEIN' => [
            'de' => ['New Yorker Haus, das die Reduktion zum Prinzip gemacht hat.', 'schmal geschnitten — bei kräftigem Oberkörper eine Nummer größer'],
            'en' => ['A New York house that turned reduction into a principle.', 'cut slim — size up for a broader build'],
        ],
        'BALENCIAGA' => [
            'de' => ['Pariser Haus, das die Silhouette bewusst überdehnt.', 'oversized geschnitten — im Zweifel eine Nummer kleiner'],
            'en' => ['A Paris house that deliberately overextends the silhouette.', 'cut oversized — size down if in doubt'],
        ],
        'BALMAIN' => [
            'de' => ['Pariser Schneiderei mit schmaler Schulter und aufwendiger Verarbeitung.', 'tailliert, schmale Schulter — bei kräftigem Oberkörper eine Nummer größer'],
            'en' => ['Parisian tailoring with a narrow shoulder and heavy finishing.', 'fitted with a narrow shoulder — size up for a broader build'],
        ],
        'BURBERRY' => [
            'de' => ['Britisches Traditionshaus, seit 1856 für Wetterfestigkeit und Understatement bekannt.', 'klassische, verlässliche Passform — entspricht der Tabelle'],
            'en' => ['A British heritage house, known since 1856 for weatherproofing and understatement.', 'classic, dependable fit — true to the table'],
        ],
        'DSQUARED2' => [
            'de' => ['Kanadische Brüder, italienische Fertigung, konsequent schmale Linie.', 'fällt klein aus — eine Nummer größer nehmen'],
            'en' => ['Canadian brothers, Italian manufacture, a consistently narrow line.', 'runs small — take one size up'],
        ],
        'DOLCE&GABBANA' => [
            'de' => ['Mailänder Haus, das italienische Tailoring mit lauter Ornamentik verbindet.', 'italienisch tailliert — bei breiten Schultern eine Nummer größer'],
            'en' => ['A Milanese house joining Italian tailoring to loud ornament.', 'Italian-tailored — size up for broad shoulders'],
        ],
        'GIVENCHY' => [
            'de' => ['Pariser Couture-Haus mit gerader, strenger Linienführung.', 'gerade, leicht weit — entspricht der Tabelle'],
            'en' => ['A Paris couture house with a straight, severe line.', 'straight, slightly generous — true to the table'],
        ],
        'VALENTINO' => [
            'de' => ['Römisches Couture-Haus; Grafik und Zurückhaltung in einem Stück.', 'regular bis leicht weit — entspricht der Tabelle'],
            'en' => ['A Roman couture house; graphic and restraint in one piece.', 'regular to slightly generous — true to the table'],
        ],
        'VERSACE' => [
            'de' => ['Mailänder Haus, dessen Formensprache nie leise war.', 'körpernah geschnitten — entspricht der Tabelle'],
            'en' => ['A Milanese house whose vocabulary was never quiet.', 'close to the body — true to the table'],
        ],
        'GCDS' => [
            'de' => ['Junge italienische Marke aus der Streetwear-Ecke.', 'Streetwear-weit — eine Nummer kleiner, wenn es normal sitzen soll'],
            'en' => ['A young Italian label out of the streetwear corner.', 'streetwear-wide — size down for a normal fit'],
        ],
        'GUCCI' => [
            'de' => ['Florentiner Haus, 1921 als Lederwarenmanufaktur gegründet.', 'italienische Größen, eher schmal — entspricht der Tabelle'],
            'en' => ['A Florentine house, founded in 1921 as a leather workshop.', 'Italian sizing, on the narrow side — true to the table'],
        ],
        'FENDI' => [
            'de' => ['Römisches Haus, aus der Pelz- und Lederwerkstatt gewachsen.', 'entspricht der Tabelle'],
            'en' => ['A Roman house grown out of the fur and leather workshop.', 'true to the table'],
        ],
    ];
}

/** Kategoriye göre: parçanın adı, ne zaman giyildiği, nasıl bakılacağı. */
function vr_category_notes(string $cat): array
{
    $k = mb_strtolower($cat);

    $m = static fn(string $re): bool => (bool)preg_match($re, $k);

    if ($m('/hood|sweat|felpa|kapuz/u')) return [
        'de' => ['Sweat-Teil für die Tage, an denen nichts anderes passt.',
                 'Auf links waschen, 30 °C, nicht in den Trockner — Druck und Stickerei überstehen Wasser, keine Hitze.'],
        'en' => ['A sweat piece for the days when nothing else fits.',
                 'Wash inside out at 30 °C, never tumble dry — print and embroidery survive water, not heat.'],
        'occasion_de' => 'Wochenende, Reise, unter einem Mantel',
        'occasion_en' => 'weekends, travel, layered under a coat',
    ];
    if ($m('/polo/u')) return [
        'de' => ['Polo — die Grenze zwischen Hemd und T-Shirt, im Sommer beides.',
                 '30 °C, Kragen flach trocknen lassen, nicht über den Bügel hängen.'],
        'en' => ['A polo — the line between shirt and tee, and in summer both at once.',
                 '30 °C, dry the collar flat, never over a hanger.'],
        'occasion_de' => 'Büro ohne Krawatte, Abendessen draußen',
        'occasion_en' => 'the office without a tie, dinner outdoors',
    ];
    if ($m('/t-shirt|tshirt|tee/u')) return [
        'de' => ['Ein T-Shirt, an dem man erkennt, dass es keins von der Stange ist.',
                 'Auf links, 30 °C, kein Trockner. Der Druck hält, solange die Hitze wegbleibt.'],
        'en' => ['A T-shirt you can tell is not off the rack.',
                 'Inside out, 30 °C, no dryer. The print lasts as long as the heat stays away.'],
        'occasion_de' => 'jeden Tag, unter allem',
        'occasion_en' => 'every day, under everything',
    ];
    if ($m('/jean|denim/u')) return [
        'de' => ['Denim, der mit dem Tragen besser wird statt schlechter.',
                 'So selten wie möglich waschen, kalt, auf links, ohne Weichspüler — jede Wäsche kostet Farbe und Passform.'],
        'en' => ['Denim that improves with wear instead of degrading.',
                 'Wash as rarely as possible, cold, inside out, no softener — every wash costs colour and fit.'],
        'occasion_de' => 'überall, wo Jeans erlaubt sind — also fast überall',
        'occasion_en' => 'anywhere jeans are allowed, which is nearly anywhere',
    ];
    if ($m('/short|bermuda/u')) return [
        'de' => ['Shorts mit einer Länge, die nicht nach Urlaubsuniform aussieht.',
                 '30 °C, auf links, flach trocknen.'],
        'en' => ['Shorts cut to a length that does not read as holiday uniform.',
                 '30 °C, inside out, dry flat.'],
        'occasion_de' => 'Stadt im Hochsommer, Terrasse',
        'occasion_en' => 'the city in high summer, a terrace',
    ];
    if ($m('/swim|bade|beach/u')) return [
        'de' => ['Badeshorts, die auch trocken noch als Kleidungsstück durchgehen.',
                 'Nach jedem Tragen in klarem Wasser ausspülen — Chlor und Salz greifen das Elasthan an, und zwar in der Tasche danach, nicht im Wasser.'],
        'en' => ['Swim shorts that still read as clothing once dry.',
                 'Rinse in clean water after every wear — chlorine and salt attack the elastane in the bag afterwards, not in the water.'],
        'occasion_de' => 'Pool, Meer, und der Weg dazwischen',
        'occasion_en' => 'the pool, the sea, and the walk between them',
    ];
    if ($m('/coat|jacket|mantel|jacke|blazer|parka/u')) return [
        'de' => ['Die äußere Schicht — das Teil, das man am häufigsten trägt und am seltensten ersetzt.',
                 'Nicht waschen: ausbürsten, lüften, einmal pro Saison in die Reinigung. Auf einem breiten Bügel aufhängen.'],
        'en' => ['The outer layer — the piece worn most often and replaced least.',
                 'Do not wash: brush, air, one cleaning per season. Hang on a wide hanger.'],
        'occasion_de' => 'von Oktober bis April jeden Tag',
        'occasion_en' => 'every day from October to April',
    ];
    if ($m('/knit|pullover|cardigan|strick|maglia/u')) return [
        'de' => ['Strick, den man nicht nach einer Saison aussortiert.',
                 'Von Hand in lauwarmem Wasser, nicht wringen, flach auf einem Handtuch trocknen. Gefaltet ins Regal, nie auf den Bügel.'],
        'en' => ['Knitwear you will not retire after one season.',
                 'Hand wash lukewarm, never wring, dry flat on a towel. Folded on a shelf, never on a hanger.'],
        'occasion_de' => 'Herbst, Abende, unter der Jacke',
        'occasion_en' => 'autumn, evenings, under a jacket',
    ];
    if ($m('/bag|tasche|borsa|clutch/u')) return [
        'de' => ['Lederware, die den Rest der Garderobe zusammenhält.',
                 'Trocken und ausgestopft lagern, fern von Heizung und Sonne. Regen: bei Zimmertemperatur trocknen, nie föhnen.'],
        'en' => ['Leather goods that hold the rest of the wardrobe together.',
                 'Store dry and stuffed, away from radiators and sun. Caught in rain: dry at room temperature, never with a hairdryer.'],
        'occasion_de' => 'täglich',
        'occasion_en' => 'daily',
    ];
    if ($m('/shoe|sneaker|schuh|boot|scarpa/u')) return [
        'de' => ['Schuhe, die man einläuft statt aufbraucht.',
                 'Mit Zedernholzspannern lagern und im Wechsel tragen — Leder braucht 24 Stunden, um Feuchtigkeit abzugeben.'],
        'en' => ['Shoes to break in rather than use up.',
                 'Store with cedar trees and rotate pairs — leather needs 24 hours to release moisture.'],
        'occasion_de' => 'täglich',
        'occasion_en' => 'daily',
    ];

    return [
        'de' => ['Ein Stück, das den Unterschied im Detail macht.', 'Pflegehinweise auf dem eingenähten Etikett beachten.'],
        'en' => ['A piece that makes its difference in the detail.', 'Follow the care instructions on the sewn-in label.'],
        'occasion_de' => '', 'occasion_en' => '',
    ];
}

/**
 * Ürün metnini kur.
 * Dönüş: ['lead' => string, 'paras' => string[], 'facts' => [etiket => değer]]
 */
function vr_product_copy(array $p): array
{
    $lang = vr_lang() === 'de' ? 'de' : 'en';
    $de   = $lang === 'de';

    $brand = strtoupper(trim((string)($p['brand'] ?? '')));
    $name  = vr_card_name($p);
    $sku   = trim((string)($p['sku'] ?? ''));
    $cat   = (string)($p['cat'] ?? '');
    $used  = ($p['condition'] ?? 'new') !== 'new';
    $stock = (int)($p['stock'] ?? 0);
    $sizes = (array)($p['sizes'] ?? []);
    $days  = (int)vr_config('return_days', 30);

    // Operatörün yazdığı ya da içe aktarılan metin varsa motor devreye girmez.
    // Boş satırlar paragraf sınırıdır; ilk cümle kısa ise giriş satırı olur —
    // aktarılan metin de sayfada üretilen metinle aynı ritmi tutsun.
    $own = trim((string)($p['copy'] ?? ''));
    if ($own !== '') {
        $paras = array_values(array_filter(array_map(
            static fn(string $x): string => trim(preg_replace('/[ \t]*\n[ \t]*/', ' ', $x) ?? $x),
            preg_split('/\n\s*\n/u', $own) ?: [$own]
        ), static fn(string $x): bool => $x !== ''));

        $lead = '';
        if ($paras && mb_strlen($paras[0]) <= 120) $lead = array_shift($paras);

        // Bilgi tablosu üretilen metinden bağımsız: doğrulanmış alanlardan
        // geliyor, o yüzden aktarılan metinle birlikte de gösteriliyor.
        return ['lead' => $lead, 'paras' => $paras, 'facts' => vr_product_facts($p)];
    }

    $house = vr_house_notes()[$brand] ?? null;
    $catN  = vr_category_notes($cat);

    // ---- giriş cümlesi: parça + ev, tek satır
    $lead = $catN[$lang][0];

    $paras = [];

    // ---- ev karakteri + kalıp
    if ($house !== null) {
        $paras[] = $house[$lang][0] . ' ' . ($de
            ? 'Für die Passform heißt das: ' . $house[$lang][1] . '.'
            : 'For fit that means: ' . $house[$lang][1] . '.');
    } else {
        $paras[] = $de
            ? 'Zur Passform: Die Maße in der Größenberatung sind Körpermaße — messen Sie sich einmal, dann stimmt die Größe.'
            : 'On fit: the numbers in the size guide are body measurements — measure once and the size holds.';
    }

    // ---- tedarikçi açıklaması (varsa) — kendi cümlelerimize karıştırmadan
    $supplier = trim((string)($p['desc'] ?? ''));
    // Toptan şablon cümlelerini ayıklıyoruz: perakende sayfasında "EEA stock
    // with full invoice trail" zaten Herkunft bloğunda yazıyor, tekrar olmasın.
    $supplier = trim(preg_replace('/\b(EEA|EU)\s+stock[^.]*\.?/i', '', $supplier) ?? $supplier);
    $supplier = trim(preg_replace('/\bS×\d[^.]*\./u', '', $supplier) ?? $supplier);
    $supplier = trim(preg_replace('/\bOriginal\s+' . preg_quote($brand, '/') . '[^.]*\.?/i', '', $supplier) ?? $supplier);
    $supplier = trim(preg_replace('/Retail price derived[^.]*\.?/i', '', $supplier) ?? $supplier);
    $supplier = trim($supplier, " .·—-\t\n");
    if ($supplier !== '' && mb_strlen($supplier) > 15) {
        $paras[] = $supplier . (str_ends_with($supplier, '.') ? '' : '.');
    }

    // ---- nadirlik: gerçek stok sayısından, abartısız
    if ($stock === 1) {
        $paras[] = $de
            ? 'Von diesem Stück ist genau eines da. Kein Nachschub, keine zweite Größe — was verkauft ist, ist weg.'
            : 'Exactly one of this piece exists here. No restock, no second size — sold is gone.';
    } elseif ($stock > 0 && $stock <= 4) {
        $n = count($sizes);
        $paras[] = $de
            ? "Der Bestand umfasst {$stock} Stück" . ($n > 1 ? " über {$n} Größen" : '') . '. Solche Größensätze kommen einzeln herein und werden nicht nachbestellt.'
            : "The run is {$stock} pieces" . ($n > 1 ? " across {$n} sizes" : '') . '. Size runs like this arrive once and are not reordered.';
    }

    // ---- durum
    if ($used) {
        $paras[] = $de
            ? 'Vorbesitzt. Gebrauchsspuren sind, soweit vorhanden, oben beschrieben; was nicht beschrieben ist, ist auch nicht da.'
            : 'Pre-owned. Any signs of wear are described above; what is not described is not there.';
    }

    // ---- bakım (tavsiye olarak, ürün iddiası olarak değil)
    $paras[] = $catN[$lang][1];

    // ---- kapanış: herkunft + iade, satıcı tipine değinmeden (o blok ayrı)
    $paras[] = $de
        ? "Aus EU-Bestand mit dokumentierter Einkaufshistorie. Passt es nicht, geht es innerhalb von {$days} Tagen zurück — Anprobieren ist ausdrücklich vorgesehen."
        : "From EU stock with documented purchase history. If it does not fit, it goes back within {$days} days — trying it on is expected.";

    return ['lead' => $lead, 'paras' => $paras, 'facts' => vr_product_facts($p)];
}

/**
 * Künye satırları. Metinden BAĞIMSIZ: yalnızca doğrulanmış alanlardan
 * (model kodu, ev, kategori, durum, beden seti) kuruluyor. Bu yüzden hem
 * üretilen metinle hem de içe aktarılmış metinle birlikte gösterilebiliyor.
 */
function vr_product_facts(array $p): array
{
    $lang  = vr_lang() === 'de' ? 'de' : 'en';
    $de    = $lang === 'de';
    $sku   = trim((string)($p['sku'] ?? ''));
    $sizes = (array)($p['sizes'] ?? []);
    $cat   = (string)($p['cat'] ?? '');
    $used  = ($p['condition'] ?? 'new') !== 'new';

    $facts = [];
    if ($sku !== '')  $facts[t('sku')] = $sku;
    $facts[t('house')]     = strtoupper(trim((string)($p['brand'] ?? '')));
    $facts[t('category')]  = vr_cat_label($cat);
    $facts[t('condition')] = $used ? t('condition_used') : t('condition_new');
    if ($sizes) {
        $facts[t('size_run')] = implode(' · ', array_map(
            fn($s) => ($s['label'] === 'ONE' ? t('one_of_one') : $s['label'] . '×' . (int)$s['qty']),
            $sizes
        ));
    }
    $occasion = vr_category_notes($cat)['occasion_' . $lang] ?? '';
    if ($occasion !== '') $facts[$de ? 'Getragen zu' : 'Worn for'] = $occasion;

    return $facts;
}

/**
 * Kısa vitrin cümlesi — meta description ve paylaşım önizlemesi için.
 * 160 karakteri geçmez, marka + parça + kalıp sinyali taşır.
 */
function vr_product_teaser(array $p): string
{
    $copy = vr_product_copy($p);
    $s = trim(($copy['lead'] ?? '') . ' ' . (string)($copy['paras'][0] ?? ''));
    $s = trim(preg_replace('/\s+/', ' ', $s) ?? $s);
    return mb_strlen($s) > 158 ? mb_substr($s, 0, 155) . '…' : $s;
}
