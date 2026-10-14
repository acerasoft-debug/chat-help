<?php
/**
 * Bulunan mağaza sayfalarını AB sürümünden yeniden oku — CLI, runner'da
 *   php tools/recheck-prices-eu.php ../diag/prices/candidates.json
 *
 * İlk tur ABD'deki runner'dan koştu; çok uluslu mağazalar (marka sitesi,
 * thecorner, END., thebs) fiyatı dolar ya da sterlinle verdi. Almanya'daki
 * UVP için Euro fiyatı gerekiyor. Bilinen ülke yolu kalıplarını Avrupa
 * sürümüne çevirip aynı sayfayı yeniden okuyoruz; bulunan fiyatlar ayrı bir
 * kaynak olarak eklenir (asıl kayıt silinmez).
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$file = (string)($argv[1] ?? '');
$cand = json_decode((string)@file_get_contents($file), true);
if (!is_array($cand)) exit(1);
// find-prices-ddg.php'nin sayfa okuyucusunu kullan (arama kısmı çalışmasın diye boş hedefle yükle).
$tmp = tempnam(sys_get_temp_dir(), 'fp'); file_put_contents($tmp, '[]');
$argvSave = $argv; $argv = ['x', $tmp, $tmp . '.out'];
require __DIR__ . '/find-prices-ddg.php';
$argv = $argvSave;

$rules = [
    ['#//(www\.)?dolcegabbana\.com/[a-z]{2}-[a-z]{2}/#', ['//www.dolcegabbana.com/en-it/', '//www.dolcegabbana.com/en-de/']],
    ['#//(www\.)?thecorner\.com/[a-z]{2}-[a-z]{2}/#',    ['//thecorner.com/it-en/', '//thecorner.com/de-en/']],
    ['#//www\.endclothing\.com/(us|gb|row|ca|au)/#',     ['//www.endclothing.com/de/', '//www.endclothing.com/eu/']],
    ['#//www\.thebs\.com/[a-z]{2}/#',                    ['//www.thebs.com/de/']],
    ['#//www\.luxuryfashionstores\.ch/en/#',             ['//www.luxuryfashionstores.sk/en/']],
];
$added = 0;
foreach ($cand as $id => &$c) {
    $skuFlat = fp_flat((string)($c['sku'] ?? ''));
    $extra = [];
    foreach ((array)($c['sources'] ?? []) as $s) {
        foreach ($rules as [$re, $to]) {
            if (!preg_match($re, (string)$s['page'])) continue;
            foreach ($to as $rep) {
                $u = preg_replace($re, $rep, (string)$s['page'], 1);
                if ($u === $s['page']) continue;
                [$rows, $has] = fp_page($u, $skuFlat);
                $rows = array_values(array_filter($rows, fn($r) => strtoupper((string)$r['cur']) === 'EUR'));
                usleep(700000);
                if (!$rows) continue;
                $extra[] = ['host' => (string)parse_url($u, PHP_URL_HOST), 'page' => $u, 'title' => (string)$s['title'] . ' [EU]', 'prices' => $rows, 'eu' => 1, 'has' => $has ? 1 : 0];
                $added++;
                break;
            }
        }
    }
    if ($extra) $c['sources'] = array_merge((array)$c['sources'], $extra);
}
unset($c);
file_put_contents($file, json_encode($cand, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
fwrite(STDERR, "AB sürümünden eklenen kaynak: $added\n");
