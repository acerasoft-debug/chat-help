<?php
/**
 * ChatHelp — dump-hero2 — hero bolgesi TAM yapisi (OKUR): H1, wlc-sub, wlc-trust, DSGVO,
 *  ch-howto konumu. Amac: H1 degistir + ch-howto yeniden tasarla + DSGVO'yu ustune al.
 * KULLANIM: pull2.php?key=...&files=dump-hero2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-hero2 (".strlen($src)." B) ==\n\n";

echo "=== [1] hero blogu: 'Ihr Recht' etrafinda GENIS baglam (±260, 1400) ===\n";
$p=stripos($src,'Ihr Recht');
if($p!==false){ echo substr($src,max(0,$p-260),1700)."\n\n"; }
else echo "  'Ihr Recht' bulunamadi\n";

echo "\n=== [2] DSGVO / Datenschutz gecen yerler (±140, 260) ===\n";
$off=0;$n=0;
while(($p=stripos($src,'DSGVO',$off))!==false && $n<5){
  echo "  [$n] @$p ...".substr($src,max(0,$p-140),300)."...\n\n"; $off=$p+5;$n++;
}
if($n===0) echo "  'DSGVO' bulunamadi\n";
foreach(['wlc-trust','Datenschutz','DSGVO-konform','EU-Server','GDPR'] as $kw){
  echo "  '$kw': ".substr_count($src,$kw)."\n";
}

echo "\n=== [3] ch-howto (benim ekledigim) konumu ===\n";
$p=strpos($src,'ch-planfax-js');
if($p!==false){ echo "  ch-planfax @".$p."\n"; }
$p=strpos($src,'id="ch-howto"');
echo "  id=ch-howto (JS-injected, runtime): kaynak-statik degil (setInterval ile eklenir)\n";

echo "\n== BITTI ==\n";
