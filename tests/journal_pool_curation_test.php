<?php
/* Havuz 6 Eki 2026'da GOZLE elendi. Bu test iki yonu tutar:
   (1) her yeni ret deseni gercek bir dosya ailesine eslesir (olu desen yok),
   (2) makalenin kullandigi ve elde tutulmasi gereken kareler ELENMEZ.
   Dosya adlari sunucudaki listeden (tests/fixtures_journal_pool_names.txt). */
$src = file_get_contents(__DIR__.'/../vestra/inc/journal.php');
if (!preg_match('/^function vestra_journal_photo_reject\(.*?^}/ms', $src, $m)) { fwrite(STDERR,"reject yok\n"); exit(1); }
eval($m[0]);
$names = array_filter(array_map('trim', file(__DIR__.'/fixtures_journal_pool_names.txt')));
$rej = vestra_journal_photo_reject();
$hit = function(string $n) use ($rej) { $s = str_replace('-', ' ', mb_strtolower($n)); foreach ($rej as $r) if (strpos($s, $r) !== false) return $r; return null; };
$ok=0;$bad=0;$t=function($n,$c)use(&$ok,&$bad){ $c?($ok++.print("  ok   $n\n")):($bad++.print("  HATA $n\n")); };

$start = array_search('manual of dyeing', $rej, true);
$t('yeni ret blogu var', $start !== false);
foreach (array_slice($rej, (int)$start) as $r) {
  $c = count(array_filter($names, fn($n) => strpos(str_replace('-', ' ', mb_strtolower($n)), $r) !== false));
  $t("desen '$r' en az bir dosyaya esler ($c)", $c >= 1);
}
$keep = ['container-terminal-bremerhaven-01-jpg-fa9f78.jpg','container-wechselbr-cken-paketverteilzentrum-frauenf-31cb3.jpg',
  'clothing-store-interior-son-moro-cala-millor-jpg-85bbff.jpg','fabric-shop-on-a-narrow-street-naples-italy-ppl1-cor-9dad42.jpg',
  'busby-fox-clothes-shop-shopfront-princes-street-trur-61c1b3.jpg','leicester-market-textiles-jpg-37e89a.jpg',
  'cargo-ship-primero-eni-02327462-moored-at-eurogate-c-f5be9c.jpg','clothing-racks-in-second-hand-shop-prague-jpg-17866e.jpg',
  'paketzentrum-frauenfeld-container-terminal-jpg-32a2e5.jpg','a-tailor-sewing-cloth-jpg-ad82ee.jpg',
  '5-wale-corduroy-trenkercord-macro-jpg-e030fb.jpg','fashion-shop-mannequins-berlin-jpg-307c39.jpg'];
foreach ($keep as $k) { $r = $hit($k); $t("TUTULUR: $k".($r?" (eleyen: $r)":''), $r === null); }
$drop = ['addie-card-12-years-spinner-in-north-pownal-cotton-m-fc5eee.jpg','giovanni-battista-moroni-the-tailor-1565-1570-jpg-d89537.jpg',
  'renault-clio-techno-bf-black-3d-fabric-and-velvet-1-ecc2a1.jpg','alex-salmond-and-ms-dawn-robson-bell-ryder-cup-tarta-730264.jpg'];
foreach ($drop as $d) $t("ELENIR: $d", $hit($d) !== null);
$left = count(array_filter($names, fn($n) => preg_match('/\.(jpe?g|png|webp)$/i', $n) && !str_starts_with($n, 'art-') && $hit($n) === null));
$t("havuzda yeterli kare kalir ($left >= 50)", $left >= 50);
echo "\n$ok ok, $bad hata\n"; exit($bad ? 1 : 0);
