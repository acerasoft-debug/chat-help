<?php
/* SSS'nin TAMAMI sitenin butun dillerinde (operator istegi, 5 Eyl 2026:
 * "FAQ Bölümünde Japonca ve diğer çevrilmeyen dillere çevir").
 *
 * returns_policy_test.php yalnizca 'returns' bolumunu tutuyor -- KURAL 11 o
 * bolum icin yazilmisti. Geri kalan 11 kategori icin hicbir bekci yoktu ve
 * durum tam da bunu gosterdi: pt/ru/ar dosyalari AYLARCA yalniz 'returns'
 * tasidi (87 maddenin 18'i), ja dosyasi ise hic yoktu. Site calisiyordu,
 * sayfa aciliyordu, kimse fark etmiyordu -- cunku vestra_faq() eksik maddeyi
 * SESSIZCE Ingilizceye dusuruyor.
 *
 * Dil basina AYRI SUREC: vlang() ilk cagrida sabitlenir; tek surecte donguye
 * alinirsa butun diller "Ingilizce" olarak olculur.
 */
$root = __DIR__ . '/../vestra';
require_once $root . '/inc/faq.php';
require_once $root . '/inc/i18n.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};

$en    = vestra_faq_en();
$langs = array_values(array_diff(array_keys(vlang_list()), ['en']));
$php   = PHP_BINARY ?: 'php';

/* Yanlis alfabe gozle fark edilmiyor. Bu depoda iki kez oldu: japonca metne
   kiril hece (широкな, особに). Latin harfleri her dilde SERBEST -- e-posta
   adresleri, marka adlari ve DDP/SEPA/KYB gibi kisaltmalar oyle yaziliyor. */
$script = [
    'ja' => ['olmali' => '/[\x{3040}-\x{30ff}\x{4e00}-\x{9fff}]/u',
             'olmamali' => ['kiril' => '/[\x{0400}-\x{04FF}]/u', 'arap' => '/[\x{0600}-\x{06FF}]/u']],
    'ru' => ['olmali' => '/[\x{0400}-\x{04FF}]/u',
             'olmamali' => ['kana' => '/[\x{3040}-\x{30ff}]/u', 'arap' => '/[\x{0600}-\x{06FF}]/u']],
    'ar' => ['olmali' => '/[\x{0600}-\x{06FF}]/u',
             'olmamali' => ['kana' => '/[\x{3040}-\x{30ff}]/u', 'kiril' => '/[\x{0400}-\x{04FF}]/u']],
];
$latinDefault = ['olmali' => null,
                 'olmamali' => ['kana' => '/[\x{3040}-\x{30ff}]/u', 'kiril' => '/[\x{0400}-\x{04FF}]/u',
                                'arap' => '/[\x{0600}-\x{06FF}]/u']];

$totalItems = 0;
foreach ($en as $c) $totalItems += count($c['items']);
echo "Ingilizce kaynak: " . count($en) . " kategori, {$totalItems} madde\n";

foreach ($langs as $lang) {
    echo "\n== {$lang} ==\n";
    $code = '$_GET=["lang"=>' . var_export($lang, true) . '];'
          . 'require ' . var_export($root . '/inc/faq.php', true) . ';'
          . 'echo json_encode(vestra_faq());';
    $got = json_decode((string)shell_exec(escapeshellarg($php) . ' -r ' . escapeshellarg($code) . ' 2>/dev/null'), true);
    if (!is_array($got)) { $t("{$lang}: SSS okunabildi", false); continue; }

    $sc = $script[$lang] ?? $latinDefault;
    $untranslated = []; $html = []; $foreign = []; $countBad = [];

    foreach ($en as $k => $cat) {
        $c = $got[$k] ?? null;
        if (!$c) { $countBad[] = "{$k}: kategori yok"; continue; }
        if (($c['title'] ?? '') === $cat['title']) $untranslated[] = "{$k}: baslik";
        if (count($c['items'] ?? []) !== count($cat['items'])) {
            $countBad[] = "{$k}: " . count($c['items'] ?? []) . '/' . count($cat['items']);
            continue;
        }
        foreach ($cat['items'] as $i => $ei) {
            $q = (string)($c['items'][$i]['q'] ?? '');
            $a = (string)($c['items'][$i]['a'] ?? '');
            if ($q === '' || $a === '' || $q === $ei['q'] || $a === $ei['a']) $untranslated[] = "{$k}/{$i}";
            /* Cevaplar nl2br(htmlspecialchars(...)) ile basiliyor: bir etiket
               koyulursa kullanici HAM etiketi gorur. Canlida bir kez oldu. */
            if (preg_match('~<\s*/?\s*[a-z][a-z0-9]*\s*[^>]*>~i', $a)) $html[] = "{$k}/{$i}";
            if ($sc['olmali'] && !preg_match($sc['olmali'], $a)) $foreign[] = "{$k}/{$i}: dil yazisi yok";
            foreach ($sc['olmamali'] as $adi => $re) {
                if (preg_match($re, $q . ' ' . $a)) $foreign[] = "{$k}/{$i}: {$adi}";
            }
        }
    }

    /* Rakip sure izleri: 4 Eyl 2026'da Ingilizce tek sayiya (3 gun) indirildi ama
       de/fr/es/it'nin returns DISINDAKI maddeleri guncellenmedi -- shipping/5
       "48 saat", disputes/0 ve /4 "5 is gunu", disputes/2 ise saticinin iade
       sunabilecegini soyleyen eski metin. Uc farkli sure, dort dilde, iki gun
       boyunca. 6 Eyl 2026'da pencere IS GUNU oldu; bu bekci hem eski sayilari
       hem "takvim gunu" kalintisini tutar. */
    $stale = [];
    foreach (['shipping/5','returns/2','returns/3','returns/11','returns/12','returns/14','returns/17','disputes/0','disputes/2','disputes/4'] as $key) {
        [$ck, $ci] = explode('/', $key);
        $a = (string)($got[$ck]['items'][(int)$ci]['a'] ?? '');
        if (preg_match('~\b48\b~', $a)) $stale[] = "$key: 48";
        if (preg_match('~(?<![0-9])5\s*(Werktag|jours? ouvr|d[ií]as h[aá]bil|giorni lavorativ|dias [uú]teis|рабоч|أيام عمل|営業日)~u', $a)) $stale[] = "$key: 5 is gunu";
        /* Rakam yalnizca sayiyi RAKAMLA yazan iki maddede aranir: returns/2 ve
           disputes/4. Japonca returns/3 "金曜日→水曜日", Arapca returns/11 "الثلاثة"
           sayiyi yaziyla verir -- ilk yazimim her maddede '3' arayip sekiz dogru
           ceviriyi kirmizi boyadi. */
        if (in_array($key, ['returns/2', 'disputes/4'], true) && !preg_match('~3~', $a)) $stale[] = "$key: '3' yok";
    }
    if (preg_match('~Kalendertage, nicht Werktage|jours calendaires, non ouvr|Días naturales, no hábiles|Giorni di calendario, non lavorativi|Dias de calendário, não úteis|Календарными днями, а не рабочими|بالأيام التقويمية لا أيام العمل|営業日ではなく暦日~u', (string)($got['returns']['items'][3]['a'] ?? ''))) $stale[] = 'returns/3: takvim gunu kalmis';
    $t("{$lang}: 12 kategori / {$totalItems} madde tam" . ($countBad ? ' — ' . implode(', ', $countBad) : ''), $countBad === []);
    $t("{$lang}: rakip sure izi yok (48 saat / 5 is gunu / takvim gunu)" . ($stale ? ' — ' . implode(', ', $stale) : ''), $stale === []);
    $t("{$lang}: Ingilizce kalan yok" . ($untranslated ? ' — ' . count($untranslated) . ' adet: ' . implode(', ', array_slice($untranslated, 0, 6)) : ''), $untranslated === []);
    $t("{$lang}: cevaplarda HTML yok" . ($html ? ' — ' . implode(', ', $html) : ''), $html === []);
    $t("{$lang}: yabanci alfabe sizmamis" . ($foreign ? ' — ' . implode(', ', array_slice($foreign, 0, 6)) : ''), $foreign === []);
}

printf("\n%d gecti, %d kaldi\n", $ok, $fail);
exit($fail ? 1 : 0);
