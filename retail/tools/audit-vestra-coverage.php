<?php
/**
 * Vestra kataloğunun ne kadarı vitrinde — SALT OKUNUR
 * ===================================================
 *   php tools/audit-vestra-coverage.php [--root=DIR]
 *
 * Operatör, 14 Eki 2026: "Vestra'nın tüm ürünlerini çek". Önce sayı: toptan
 * kataloğunda (data/listings.json + data/retail-imported.json) kaç satır var,
 * kaçı vitrine geliyor, gelmeyenler hangi kuraldan düşüyor. Hiçbir şey
 * yazmaz; JSON basar.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__);
foreach ($argv as $a) if (str_starts_with($a, '--root=')) $root = substr($a, 7);
require_once $root . '/inc/catalog.php';
require_once $root . '/inc/view.php';

$cat = vr_catalog();
$cf  = vr_category_fix();
$allow = [];
foreach ((array)vr_config('premium_brands', []) as $b) $allow[vr_brand_key((string)$b)] = true;
$rx = (string)vr_config('excluded_name_regex', '');

$out = ['files' => [], 'reasons' => [], 'brands_out' => [], 'cats_out' => [], 'status' => [], 'samples' => []];
foreach (['listings.json', 'retail-imported.json'] as $file) {
    $rows = vr_store_read($file, []);
    $out['files'][$file] = is_array($rows) ? count($rows) : 0;
    if (!is_array($rows)) continue;
    foreach ($rows as $r) {
        if (!is_array($r)) continue;
        $id = trim((string)($r['id'] ?? ''));
        $st = strtolower(trim((string)($r['status'] ?? '')));
        $out['status'][$file][$st] = ($out['status'][$file][$st] ?? 0) + 1;
        if ($id !== '' && isset($cat[$id])) { $why = 'shown'; }
        elseif ($id === '') $why = 'no id';
        elseif (!in_array($st, VR_SELLABLE_STATUS, true)) $why = 'status:' . $st;
        elseif (!empty($r['hold']) || !empty($r['hidden'])) $why = 'hold/hidden';
        elseif (!empty($cf[$id]['hidden'])) $why = 'operator hidden';
        else {
            $p = $r;
            if (($cf[$id]['cat'] ?? '') !== '')  $p['cat']  = (string)$cf[$id]['cat'];
            if (($cf[$id]['name'] ?? '') !== '') $p['name'] = (string)$cf[$id]['name'];
            $bk = vr_brand_key((string)($p['brand'] ?? ''));
            $c  = mb_strtolower(trim((string)($p['cat'] ?? '')));
            if ($allow && !isset($allow[$bk])) { $why = 'not premium brand'; $out['brands_out'][(string)($p['brand'] ?? '')] = ($out['brands_out'][(string)($p['brand'] ?? '')] ?? 0) + 1; }
            elseif (in_array($id, (array)vr_config('excluded_ids', []), true)) $why = 'excluded id';
            elseif (in_array($c, array_map('mb_strtolower', (array)vr_config('excluded_categories', [])), true)) { $why = 'excluded category'; $out['cats_out'][$c] = ($out['cats_out'][$c] ?? 0) + 1; }
            elseif ($rx !== '' && @preg_match($rx, (string)($p['name'] ?? '')) === 1) $why = 'underwear name';
            elseif (!array_filter((array)($r['images'] ?? []), 'strlen')) $why = 'no photo';
            else $why = 'merged/other';
        }
        $out['reasons'][$file][$why] = ($out['reasons'][$file][$why] ?? 0) + 1;
        if ($why !== 'shown' && count($out['samples'][$why] ?? []) < 8) {
            $out['samples'][$why][] = $id . ' | ' . ($r['brand'] ?? '') . ' | ' . ($r['name'] ?? '') . ' | ' . ($r['cat'] ?? '') . ' | imgs ' . count((array)($r['images'] ?? []));
        }
    }
}
arsort($out['brands_out']);
$out['catalog_total'] = count($cat);
$out['catalog_by_source'] = array_count_values(array_map(fn($p) => (string)($p['source'] ?? ''), $cat));
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
