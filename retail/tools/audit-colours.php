<?php
/**
 * Renk seçimi denetimi (SALT OKUNUR) — php tools/audit-colours.php [--root=DIR]
 * ==========================================================================
 * Birden çok rengi olan her ürünü sınıflandırır ve JSON basar:
 *
 *   siblings  renk başına ayrı satır; şerit diğer satırların sayfasına gider.
 *             Denetlenen: gruptaki satır sayısı ile şeritte görünen sayı,
 *             etiketi boş / aynı olan satırlar, fotoğrafı olmayan satırlar,
 *             iki satırın ilk karesi aynı mı.
 *   sets      tek satırda çok renk, her rengin kendi galerisi (vr_colour_sets).
 *   chips     tek satırda çok renk ama karelerle eşleşme yok: renk seçilince
 *             fotoğraf DEĞİŞMİYOR. Her kare için baskın renk ve en yakın renk
 *             adı ölçülür (eşleştirme önerisi için).
 *
 * Hiçbir dosyaya yazmaz.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__);
foreach ($argv as $a) if (str_starts_with($a, '--root=')) $root = substr($a, 7);
require_once $root . '/inc/catalog.php';
require_once $root . '/inc/colours.php';
require_once $root . '/inc/uploads.php';
require_once __DIR__ . '/lib-photo-colour.php';

$doc = rtrim(vr_doc_root(), '/');

/** Baskın renk ve en yakın renk adı. */
function ac_measure(string $abs): array
{
    $r = cac_pixels($abs);
    if ($r === null) return ['dom' => null, 'name' => '', 'fill' => 0.0];
    [$px, $frac] = $r;
    $dom = cac_dominant($px);
    return ['dom' => $dom ? sprintf('#%02x%02x%02x', ...$dom) : null, 'name' => $dom ? ac_nearest($dom) : '', 'fill' => round($frac, 3)];
}

function ac_nearest(array $rgb): string
{
    $best = ''; $bd = INF;
    foreach (VR_COLOUR_HEX as $n => $hex) {
        $c = sscanf($hex, '#%02x%02x%02x');
        $d = cac_dist($rgb, $c);
        if ($d < $bd) { $bd = $d; $best = $n; }
    }
    return $best;
}

$cat = vr_catalog();
$out = ['summary' => ['products' => count($cat), 'siblings_groups' => 0, 'sets' => 0, 'chips' => 0], 'groups' => [], 'sets' => [], 'chips' => []];
$doneGroups = [];

foreach ($cat as $id => $p) {
    $id = (string)$id;
    $key = vr_variant_key($p);
    $group = vr_variant_index()[$key] ?? [];
    if (count($group) > 1) {
        if (isset($doneGroups[$key])) continue;
        $doneGroups[$key] = 1;
        $shown = array_map(fn($s) => (string)$s['id'], vr_variant_siblings($p));
        $rows = [];
        $firsts = [];
        foreach ($group as $gid) {
            $g = $cat[$gid];
            $cols = vr_product_colours($g);
            $img = vr_swatch_image($g);
            $first = (string)($g['images'][0] ?? '');
            $rows[] = [
                'id' => $gid, 'name' => vr_card_name($g), 'variant_colour' => vr_variant_colour($g),
                'colours' => $cols, 'images' => count(array_filter((array)($g['images'] ?? []), 'strlen')),
                'swatch' => $img, 'real' => str_starts_with($img, '/uploads/'), 'stock' => (int)$g['stock'],
                'shown' => in_array($gid, $shown, true),
                'measured' => str_starts_with($img, '/uploads/') ? ac_measure($doc . $img) : null,
            ];
            if ($first !== '') $firsts[$first][] = $gid;
        }
        // Sorun = müşterinin gördüğü şeritte: aynı rengin kopyası olan satırlar
        // şeritte gizleniyor (vr_variant_siblings), onlar yalnız bilgi.
        $issues = [];
        $info = count($shown) < count($group) ? 'hidden:' . (count($group) - count($shown)) : '';
        $labels = [];
        foreach ($rows as $r) {
            if (!$r['shown']) continue;
            $l = mb_strtolower($r['colours'][0] ?? $r['variant_colour']);
            if ($l === '') $issues[] = 'no-label:' . $r['id'];
            else $labels[$l][] = $r['id'];
            if (!$r['real']) $issues[] = 'no-photo:' . $r['id'];
        }
        foreach ($labels as $l => $ids) if (count($ids) > 1) $issues[] = "same-label:$l";
        foreach ($firsts as $f => $ids) {
            $vis = array_values(array_intersect($ids, $shown));
            if (count($vis) > 1) $issues[] = 'same-photo:' . implode('+', $vis);
        }
        $out['groups'][] = ['key' => $key, 'brand' => $p['brand'], 'strip' => count($shown), 'info' => $info, 'issues' => $issues, 'rows' => $rows];
        $out['summary']['siblings_groups']++;
        continue;
    }
    $cols = vr_product_colours($p);
    if (count($cols) < 2) continue;
    $sets = vr_colour_sets($p);
    if ($sets) {
        $out['sets'][] = ['id' => $id, 'name' => vr_card_name($p), 'colours' => $cols,
                          'sets' => array_map(fn($s) => ['label' => $s['label'], 'n' => count($s['images']), 'first' => $s['images'][0] ?? ''], $sets)];
        $out['summary']['sets']++;
        continue;
    }
    $imgs = array_values(array_filter((array)($p['images'] ?? []), 'strlen'));
    $m = [];
    foreach ($imgs as $im) $m[] = ['img' => $im] + (str_starts_with($im, '/uploads/') ? ac_measure($doc . $im) : ['dom' => null, 'name' => '', 'fill' => 0]);
    $out['chips'][] = ['id' => $id, 'brand' => $p['brand'], 'name' => vr_card_name($p), 'colours' => $cols,
                       'reviewed' => isset(vr_reviewed_colours()[$id]), 'raw_colours' => $p['colours'] ?? null,
                       'images' => $m];
    $out['summary']['chips']++;
}

$issueCount = 0;
foreach ($out['groups'] as $g) if ($g['issues']) $issueCount++;
$out['summary']['groups_with_issues'] = $issueCount;
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
