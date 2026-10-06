<?php
/* 150 € paket gumrugu yazisi: 9 dil, gercek fotograflar, rakamlar her dilde ayni. */
$root = dirname(__DIR__);
$d = json_decode(file_get_contents($root.'/vestra/inc/journal_seed.json'), true);
$src = file_get_contents($root.'/vestra/inc/journal.php');
preg_match('/^function vestra_journal_photo_reject\(.*?^}/ms', $src, $m); eval($m[0]);
$names = array_filter(array_map('trim', file(__DIR__.'/fixtures_journal_pool_names.txt')));
$ok=0;$bad=0;$t=function($n,$c)use(&$ok,&$bad){ $c?($ok++.print("  ok   $n\n")):($bad++.print("  HATA $n\n")); };
$a = $d[0];
$t('ilk sirada', str_contains($a['title'], '€150 Loophole'));
$t('kategori Market & Prices', $a['category'] === 'Market & Prices');
$cov = basename($a['cover']);
$t('kapak sunucudaki havuzda', in_array($cov, $names, true));
$rejected = function($f) { $s = str_replace('-', ' ', mb_strtolower($f)); foreach (vestra_journal_photo_reject() as $r) if (strpos($s,$r)!==false) return true; return false; };
$t('kapak elenmiyor', !$rejected($cov));
$langs = ['en'=>$a] + $a['i18n'];
$t('9 dil', array_keys($langs) === ['en','de','fr','it','es','pt','ru','ar','ja']);
foreach ($langs as $l => $v) {
  preg_match_all('/\[img:([^|\]]+)\|([^\]]+)\]/', $v['body'], $im);
  $t("$l: 2 figur", count($im[1]) === 2);
  foreach ($im[1] as $i => $p) {
    $t("$l: figur havuzda ve elenmiyor (".basename($p).")", in_array(basename($p), $names, true) && !$rejected(basename($p)));
    $t("$l: altyazi fotografciyi aniyor", preg_match('/(JoachimKohler-HB|Steffen Mokosch) · CC BY-SA 4\.0$/u', $im[2][$i]) === 1);
  }
  $t("$l: cozulmemis degisken yok", !preg_match('/\{(IMG|C)\d\}/', $v['body']));
  foreach (['150','2028','15','3','2'] as $n) $t("$l: rakam $n var", preg_match('/(?<![\d.,])'.$n.'(?![\d])/u', $v['body']) === 1);
  $t("$l: baslik+ozet dolu", trim($v['title']) !== '' && trim($v['excerpt']) !== '');
  $t("$l: tek blok gorsel satiri (paragraf olarak)", substr_count($v['body'], "\n\n[img:") === 2);
}
echo "\n$ok ok, $bad hata\n"; exit($bad?1:0);
