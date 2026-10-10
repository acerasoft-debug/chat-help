<?php
/**
 * Renk adları ve renk noktaları
 * =============================
 * Katalogda renkler İngilizce tutuluyor (Vestra'nın colors alanı, ürün adları
 * ve gözle doğrulanmış data/product-colours.json hep İngilizce). Vitrin onları
 * ziyaretçinin dilinde gösterir; bilinmeyen bir ad olduğu gibi kalır ("Archive
 * Beige" gibi evin kendi renk adı çevrilmez — evin adıdır).
 *
 * İki tonlu adlar ("Black/White", "Navy/Red") parça parça çevrilir.
 */

declare(strict_types=1);

require_once __DIR__ . '/boot.php';

/** Renk noktası için yaklaşık ton. Anahtar küçük harf İngilizce ad. */
const VR_COLOUR_HEX = [
    'black' => '#111111', 'white' => '#f7f7f5', 'off white' => '#f1eee6', 'ivory' => '#f3eedf',
    'cream' => '#efe6cf', 'ecru' => '#e9e0c9', 'beige' => '#d9c3a1', 'sand' => '#d6c4a3', 'camel' => '#bf9465',
    'stone' => '#cfc6b6', 'taupe' => '#9c8b7b', 'brown' => '#6b4a35', 'grey' => '#8d8f93', 'gray' => '#8d8f93',
    'light grey' => '#c9cacc', 'charcoal' => '#3b3d40', 'anthracite' => '#3b3d40', 'graphite' => '#4a4c50',
    'navy' => '#1d2742', 'navy blue' => '#1d2742', 'dark blue' => '#22355e', 'blue' => '#2f5fae',
    'light blue' => '#a9c9ea', 'sky blue' => '#a9c9ea', 'indigo' => '#33337a', 'teal' => '#1f6f72',
    'turquoise' => '#3fb4b0', 'mint' => '#bfe6d2', 'green' => '#2f8f4e', 'khaki' => '#8a8256',
    'olive' => '#6b6b3a', 'yellow' => '#f2cf3a', 'gold' => '#c9a646', 'orange' => '#ef7a2a',
    'red' => '#c8262e', 'burgundy' => '#6d1f2c', 'bordeaux' => '#6d1f2c', 'pink' => '#f0b4c4',
    'fuchsia' => '#c2347e', 'purple' => '#5b3c8f', 'lilac' => '#c8b5dc', 'silver' => '#c0c2c5',
];

/** Sık renk adlarının çevirisi. Dil kodu → ad. İngilizce kendisi. */
const VR_COLOUR_I18N = [
    'black'       => ['de' => 'Schwarz', 'fr' => 'Noir', 'it' => 'Nero', 'es' => 'Negro', 'nl' => 'Zwart', 'da' => 'Sort', 'sv' => 'Svart', 'ru' => 'Чёрный', 'az' => 'Qara'],
    'white'       => ['de' => 'Weiß', 'fr' => 'Blanc', 'it' => 'Bianco', 'es' => 'Blanco', 'nl' => 'Wit', 'da' => 'Hvid', 'sv' => 'Vit', 'ru' => 'Белый', 'az' => 'Ağ'],
    'off white'   => ['de' => 'Naturweiß', 'fr' => 'Blanc cassé', 'it' => 'Bianco sporco', 'es' => 'Blanco roto', 'nl' => 'Gebroken wit', 'da' => 'Råhvid', 'sv' => 'Benvit', 'ru' => 'Молочный', 'az' => 'Süd rəngi'],
    'ivory'       => ['de' => 'Elfenbein', 'fr' => 'Ivoire', 'it' => 'Avorio', 'es' => 'Marfil', 'nl' => 'Ivoor', 'da' => 'Elfenben', 'sv' => 'Elfenben', 'ru' => 'Слоновая кость', 'az' => 'Fil sümüyü'],
    'cream'       => ['de' => 'Creme', 'fr' => 'Crème', 'it' => 'Panna', 'es' => 'Crema', 'nl' => 'Crème', 'da' => 'Creme', 'sv' => 'Gräddvit', 'ru' => 'Кремовый', 'az' => 'Krem'],
    'beige'       => ['de' => 'Beige', 'fr' => 'Beige', 'it' => 'Beige', 'es' => 'Beige', 'nl' => 'Beige', 'da' => 'Beige', 'sv' => 'Beige', 'ru' => 'Бежевый', 'az' => 'Bej'],
    'camel'       => ['de' => 'Camel', 'fr' => 'Camel', 'it' => 'Cammello', 'es' => 'Camel', 'nl' => 'Camel', 'da' => 'Kamel', 'sv' => 'Kamel', 'ru' => 'Кэмел', 'az' => 'Dəvəyunu'],
    'brown'       => ['de' => 'Braun', 'fr' => 'Marron', 'it' => 'Marrone', 'es' => 'Marrón', 'nl' => 'Bruin', 'da' => 'Brun', 'sv' => 'Brun', 'ru' => 'Коричневый', 'az' => 'Qəhvəyi'],
    'grey'        => ['de' => 'Grau', 'fr' => 'Gris', 'it' => 'Grigio', 'es' => 'Gris', 'nl' => 'Grijs', 'da' => 'Grå', 'sv' => 'Grå', 'ru' => 'Серый', 'az' => 'Boz'],
    'gray'        => ['de' => 'Grau', 'fr' => 'Gris', 'it' => 'Grigio', 'es' => 'Gris', 'nl' => 'Grijs', 'da' => 'Grå', 'sv' => 'Grå', 'ru' => 'Серый', 'az' => 'Boz'],
    'light grey'  => ['de' => 'Hellgrau', 'fr' => 'Gris clair', 'it' => 'Grigio chiaro', 'es' => 'Gris claro', 'nl' => 'Lichtgrijs', 'da' => 'Lysegrå', 'sv' => 'Ljusgrå', 'ru' => 'Светло-серый', 'az' => 'Açıq boz'],
    'charcoal'    => ['de' => 'Anthrazit', 'fr' => 'Anthracite', 'it' => 'Antracite', 'es' => 'Antracita', 'nl' => 'Antraciet', 'da' => 'Koksgrå', 'sv' => 'Koksgrå', 'ru' => 'Антрацит', 'az' => 'Antrasit'],
    'graphite'    => ['de' => 'Graphit', 'fr' => 'Graphite', 'it' => 'Grafite', 'es' => 'Grafito', 'nl' => 'Grafiet', 'da' => 'Grafit', 'sv' => 'Grafit', 'ru' => 'Графит', 'az' => 'Qrafit'],
    'navy'        => ['de' => 'Marine', 'fr' => 'Marine', 'it' => 'Blu navy', 'es' => 'Azul marino', 'nl' => 'Marine', 'da' => 'Marine', 'sv' => 'Marinblå', 'ru' => 'Тёмно-синий', 'az' => 'Tünd göy'],
    'navy blue'   => ['de' => 'Marine', 'fr' => 'Marine', 'it' => 'Blu navy', 'es' => 'Azul marino', 'nl' => 'Marine', 'da' => 'Marine', 'sv' => 'Marinblå', 'ru' => 'Тёмно-синий', 'az' => 'Tünd göy'],
    'dark blue'   => ['de' => 'Dunkelblau', 'fr' => 'Bleu foncé', 'it' => 'Blu scuro', 'es' => 'Azul oscuro', 'nl' => 'Donkerblauw', 'da' => 'Mørkeblå', 'sv' => 'Mörkblå', 'ru' => 'Тёмно-синий', 'az' => 'Tünd mavi'],
    'blue'        => ['de' => 'Blau', 'fr' => 'Bleu', 'it' => 'Blu', 'es' => 'Azul', 'nl' => 'Blauw', 'da' => 'Blå', 'sv' => 'Blå', 'ru' => 'Синий', 'az' => 'Mavi'],
    'light blue'  => ['de' => 'Hellblau', 'fr' => 'Bleu clair', 'it' => 'Azzurro', 'es' => 'Azul claro', 'nl' => 'Lichtblauw', 'da' => 'Lyseblå', 'sv' => 'Ljusblå', 'ru' => 'Голубой', 'az' => 'Açıq mavi'],
    'teal'        => ['de' => 'Petrol', 'fr' => 'Bleu canard', 'it' => 'Verde petrolio', 'es' => 'Verde azulado', 'nl' => 'Petrol', 'da' => 'Petrol', 'sv' => 'Petrol', 'ru' => 'Бирюзово-синий', 'az' => 'Firuzəyi'],
    'turquoise'   => ['de' => 'Türkis', 'fr' => 'Turquoise', 'it' => 'Turchese', 'es' => 'Turquesa', 'nl' => 'Turquoise', 'da' => 'Turkis', 'sv' => 'Turkos', 'ru' => 'Бирюзовый', 'az' => 'Firuzəyi'],
    'green'       => ['de' => 'Grün', 'fr' => 'Vert', 'it' => 'Verde', 'es' => 'Verde', 'nl' => 'Groen', 'da' => 'Grøn', 'sv' => 'Grön', 'ru' => 'Зелёный', 'az' => 'Yaşıl'],
    'khaki'       => ['de' => 'Khaki', 'fr' => 'Kaki', 'it' => 'Kaki', 'es' => 'Caqui', 'nl' => 'Kaki', 'da' => 'Khaki', 'sv' => 'Khaki', 'ru' => 'Хаки', 'az' => 'Xaki'],
    'olive'       => ['de' => 'Oliv', 'fr' => 'Olive', 'it' => 'Oliva', 'es' => 'Oliva', 'nl' => 'Olijf', 'da' => 'Oliven', 'sv' => 'Oliv', 'ru' => 'Оливковый', 'az' => 'Zeytuni'],
    'yellow'      => ['de' => 'Gelb', 'fr' => 'Jaune', 'it' => 'Giallo', 'es' => 'Amarillo', 'nl' => 'Geel', 'da' => 'Gul', 'sv' => 'Gul', 'ru' => 'Жёлтый', 'az' => 'Sarı'],
    'orange'      => ['de' => 'Orange', 'fr' => 'Orange', 'it' => 'Arancione', 'es' => 'Naranja', 'nl' => 'Oranje', 'da' => 'Orange', 'sv' => 'Orange', 'ru' => 'Оранжевый', 'az' => 'Narıncı'],
    'red'         => ['de' => 'Rot', 'fr' => 'Rouge', 'it' => 'Rosso', 'es' => 'Rojo', 'nl' => 'Rood', 'da' => 'Rød', 'sv' => 'Röd', 'ru' => 'Красный', 'az' => 'Qırmızı'],
    'burgundy'    => ['de' => 'Bordeaux', 'fr' => 'Bordeaux', 'it' => 'Bordeaux', 'es' => 'Burdeos', 'nl' => 'Bordeaux', 'da' => 'Bordeaux', 'sv' => 'Vinröd', 'ru' => 'Бордовый', 'az' => 'Bordo'],
    'pink'        => ['de' => 'Rosa', 'fr' => 'Rose', 'it' => 'Rosa', 'es' => 'Rosa', 'nl' => 'Roze', 'da' => 'Lyserød', 'sv' => 'Rosa', 'ru' => 'Розовый', 'az' => 'Çəhrayı'],
    'fuchsia'     => ['de' => 'Fuchsia', 'fr' => 'Fuchsia', 'it' => 'Fucsia', 'es' => 'Fucsia', 'nl' => 'Fuchsia', 'da' => 'Fuchsia', 'sv' => 'Cerise', 'ru' => 'Фуксия', 'az' => 'Fuksiya'],
    'purple'      => ['de' => 'Lila', 'fr' => 'Violet', 'it' => 'Viola', 'es' => 'Morado', 'nl' => 'Paars', 'da' => 'Lilla', 'sv' => 'Lila', 'ru' => 'Фиолетовый', 'az' => 'Bənövşəyi'],
    'lilac'       => ['de' => 'Flieder', 'fr' => 'Lilas', 'it' => 'Lilla', 'es' => 'Lila', 'nl' => 'Lila', 'da' => 'Syren', 'sv' => 'Syren', 'ru' => 'Сиреневый', 'az' => 'Yasəmən'],
    'multicolour' => ['de' => 'Mehrfarbig', 'fr' => 'Multicolore', 'it' => 'Multicolore', 'es' => 'Multicolor', 'nl' => 'Meerkleurig', 'da' => 'Flerfarvet', 'sv' => 'Flerfärgad', 'ru' => 'Разноцветный', 'az' => 'Rəngarəng'],
    'multicolor'  => ['de' => 'Mehrfarbig', 'fr' => 'Multicolore', 'it' => 'Multicolore', 'es' => 'Multicolor', 'nl' => 'Meerkleurig', 'da' => 'Flerfarvet', 'sv' => 'Flerfärgad', 'ru' => 'Разноцветный', 'az' => 'Rəngarəng'],
];

/** Renk adı ziyaretçinin dilinde. "Black/White" parça parça çevrilir. */
function vr_colour_label(string $name): string
{
    $lang = vr_lang();
    $parts = preg_split('/\s*\/\s*/', trim($name)) ?: [$name];
    $out = [];
    foreach ($parts as $part) {
        $k = mb_strtolower($part);
        $out[] = ($lang !== 'en' && isset(VR_COLOUR_I18N[$k][$lang])) ? VR_COLOUR_I18N[$k][$lang] : $part;
    }
    return implode(' / ', $out);
}

/**
 * Renk noktasının CSS arka planı. İki tonlu adda iki yarım; bilinmeyen adda
 * içindeki bilinen kelime ("Archive Beige" → beige), o da yoksa nötr gri.
 */
function vr_colour_css(string $name): string
{
    $hex = static function (string $n): string {
        $k = mb_strtolower(trim($n));
        if (isset(VR_COLOUR_HEX[$k])) return VR_COLOUR_HEX[$k];
        foreach (VR_COLOUR_HEX as $w => $h) if (preg_match('/\b' . preg_quote($w, '/') . '\b/', $k)) return $h;
        return '#b9b6b0';
    };
    $k = mb_strtolower(trim($name));
    if (str_starts_with($k, 'multicolo')) {
        return 'conic-gradient(#c8262e,#f2cf3a,#2f8f4e,#2f5fae,#5b3c8f,#c8262e)';
    }
    $parts = preg_split('/\s*\/\s*/', $name) ?: [$name];
    if (count($parts) >= 2) return 'linear-gradient(135deg,' . $hex($parts[0]) . ' 50%,' . $hex($parts[1]) . ' 50%)';
    return $hex($name);
}
