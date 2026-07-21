<?php
/**
 * ChatHelp — dump-herochk — hero degisikligi neden gorunmuyor? (OKUR)
 *  (a) yeni H1 diskte mi? (b) JS wlc-h1'i yeniden yaziyor mu? (c) ch-howto insert.
 * KULLANIM: pull2.php?key=...&files=dump-herochk.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-herochk (".strlen($src)." B) ==\n\n";

echo "=== [1] Diskte yeni H1 var mi? ===\n";
echo "  'Ihr gutes Recht' : ".substr_count($src,'Ihr gutes Recht')." (1 olmali)\n";
echo "  'Einfach durchgesetzt' : ".substr_count($src,'Einfach durchgesetzt')."\n";
echo "  ESKI 'Ihr Recht.<br><em>Ihr Schutz' : ".substr_count($src,'Ihr Recht.<br><em>Ihr Schutz')." (0 olmali)\n";
echo "  CH_HERO2 marker : ".substr_count($src,'CH_HERO2')."\n";
echo "  'Ihr Recht' (herhangi, ceviri olabilir) : ".substr_count($src,'Ihr Recht')."\n";
echo "  'Ihr Schutz' (herhangi) : ".substr_count($src,'Ihr Schutz')."\n";

echo "\n=== [2] JS wlc-h1'i yeniden yaziyor mu? (innerHTML/textContent/querySelector) ===\n";
$off=0;$n=0;
while(($p=strpos($src,'wlc-h1',$off))!==false && $n<8){
  echo "  [$n] @$p ...".substr($src,max(0,$p-90),180)."...\n\n"; $off=$p+6;$n++;
}
echo "  'wlc-h1' toplam: ".substr_count($src,'wlc-h1')."\n";

echo "\n=== [3] Ihr Recht/Schutz ceviri haritasi (JS lang) ===\n";
foreach(['Ihr Recht','Your rights','Vos droits','Sus derechos','Hakkınız','I vostri diritti','wlc_h1','heroTitle','wlcTitle'] as $kw){
  $c=substr_count($src,$kw); if($c>0) echo "  '$kw': $c\n";
}
/* Ihr Recht gecen JS baglami (statik disinda) */
$off=0;$n=0;
while(($p=strpos($src,'Ihr Recht',$off))!==false && $n<6){
  echo "  {IhrRecht $n @$p} ...".substr($src,max(0,$p-60),140)."...\n"; $off=$p+9;$n++;
}

echo "\n=== [4] ch-howto yeni CSS/render diskte mi? ===\n";
echo "  '.hw-ey' (eyebrow) : ".substr_count($src,'hw-ey')."\n";
echo "  'So einfach geht' : ".substr_count($src,"So einfach geht")."\n";
echo "  '.wlc-trust')||host' (yeni insert) : ".substr_count($src,"querySelector('.wlc-trust')||host")."\n";
echo "\n== BITTI ==\n";
