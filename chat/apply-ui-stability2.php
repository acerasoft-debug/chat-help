<?php
/**
 * ChatHelp — apply-ui-stability2 (CH_UI_STAB2) — yerlesme kapisi v2 (SIKI)
 *  v1'de kapi %15 soluk gosteriyordu ve yalnizca cocuk EKLEMELERINI izliyordu;
 *  kartlarin goster/gizle (display) degisimleri kapiyi uzatmiyor ve grid
 *  yuksekligi degistikce ALTINDAKI icerik zipliyordu.
 *  v2:
 *   • Yerlesme sirasinda grid TAMAMEN gizli (opacity:0) + sabit yer tutucu
 *     yukseklik (min-height:60vh) -> altindaki icerik de kimildayamaz.
 *   • Gozlemci artik style/class degisimlerini de (goster/gizle churn'u)
 *     izler -> kapi, TUM hareket bitene kadar kapali kalir (sessizlik 900ms,
 *     en gec 6 sn).
 * KULLANIM: pull-updates.php?files=apply-ui-stability2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-ui-stability2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_UI_STAB2')!==false) exit("Zaten ekli (CH_UI_STAB2).\n");
if (strpos($src,'CH_UI_STABILITY')===false) exit("HATA: once apply-ui-stability.php gerekli.\n");

$fail=[];

/* [1] tamamen gizle + yer tutucu yukseklik */
$o1 = '.wcat-grid.ch-settling{opacity:.15;pointer-events:none}';
$n1 = '.wcat-grid.ch-settling{opacity:0;pointer-events:none;min-height:60vh} /*CH_UI_STAB2*/';
$c=0; $src=str_replace($o1,$n1,$src,$c);
echo ($c===1?"  ✓ [1] yerlesme sirasinda tam gizli + sabit yer tutucu\n":"  ✗ [1] anchor ($c)\n"); if($c!==1)$fail[]=1;

/* [2] gozlemci: goster/gizle degisimlerini de izle */
$o2 = "        new MutationObserver(function(){ if(!shown) arm(); }).observe(g,{childList:true});";
$n2 = "        new MutationObserver(function(){ if(!shown) arm(); }).observe(g,{childList:true,subtree:true,attributes:true,attributeFilter:['style','class']}); /*CH_UI_STAB2*/";
$c=0; $src=str_replace($o2,$n2,$src,$c);
echo ($c===1?"  ✓ [2] goster/gizle churn'u da kapiyi uzatiyor\n":"  ✗ [2] anchor ($c)\n"); if($c!==1)$fail[]=2;

/* [3] sessizlik penceresi 700 -> 900ms */
$o3 = "    clearTimeout(t); t=setTimeout(reveal, 700);";
$n3 = "    clearTimeout(t); t=setTimeout(reveal, 900); /*CH_UI_STAB2*/";
$c=0; $src=str_replace($o3,$n3,$src,$c);
echo ($c===1?"  ✓ [3] sessizlik penceresi 900ms\n":"  ✗ [3] anchor ($c)\n"); if($c!==1)$fail[]=3;

/* [4] guvenlik tavani 5 -> 6 sn (iki nokta) */
$o4a = "  function resettle(){ shown=false; arm(); setTimeout(function(){ if(!shown) reveal(); }, 5000); }";
$n4a = "  function resettle(){ shown=false; arm(); setTimeout(function(){ if(!shown) reveal(); }, 6000); } /*CH_UI_STAB2*/";
$c=0; $src=str_replace($o4a,$n4a,$src,$c);
echo ($c===1?"  ✓ [4a] resettle tavani 6sn\n":"  ✗ [4a] anchor ($c)\n"); if($c!==1)$fail[]='4a';

$o4b = "    setTimeout(function(){ if(!shown) reveal(); }, 5000); /* guvenlik: en gec 5 sn */";
$n4b = "    setTimeout(function(){ if(!shown) reveal(); }, 6000); /* guvenlik: en gec 6 sn */ /*CH_UI_STAB2*/";
$c=0; $src=str_replace($o4b,$n4b,$src,$c);
echo ($c===1?"  ✓ [4b] acilis tavani 6sn\n":"  ✗ [4b] anchor ($c)\n"); if($c!==1)$fail[]='4b';

if ($fail) { echo "\nHATA: eslesmeyen adimlar: ".implode(', ',$fail)." — index.php DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(),'u2').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-uistab2-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_UI_STAB2 uygulandi. Kartlar otururken grid tamamen gizli + yer tutuculu;\n";
echo "   goster/gizle dahil TUM hareket bitmeden acilmaz — gozle gorulur oynama kalmadi.\n";
