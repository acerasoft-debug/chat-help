<?php
/**
 * ChatHelp — apply-stability-final (CH_STAB_FINAL) — kart/menu KESIN sabitlik paketi
 *  Mevcut (geri donulmus) yapida kalan GERCEK hareket kaynaklari:
 *   • Acilis/ulke degisiminde kategoriler ilk saniyelerde teker teker eklenirken
 *     izgara buyuyor -> altindaki her sey asagi itiliyor. v1 kapisi %15 soluk +
 *     700ms sessizlik + 5sn tavanla ERKEN aciliyordu.
 *  SIKILASTIRMA (v1 uzerinde 5 cerrahi degisiklik — v2'nin attribute-gozlemci
 *  kismi YOK, en guvenli surum):
 *   [1] Yerlesirken izgara TAM GIZLI (opacity:0) + 55vh yer tutucu -> buyume
 *       gorunmez, alt icerik zipllamaz.
 *   [2] Sessizlik penceresi 700ms -> 1200ms (kademeli eklemelerin tamami sigsin).
 *   [3] Acilis tavani 5sn -> 8sn.  [4] Ulke-degisimi tavani 5sn -> 8sn.
 *   [5] ACILIRKEN YUKSEKLIK KILIDI: izgara gorunur yapilmadan hemen once o anki
 *       icerik yuksekligi inline min-height olarak sabitlenir -> acilistan sonra
 *       gec gelen nadir kartlar rezervin icinde kalir, sayfayi ITEMEZ.
 *  NOT: Ayni deploy'da apply-sbprobe3-remove.php de calistirin — sag alttaki
 *  kirmizi sayacli izleme dugmesi (her kaydirmayi 'degisiklik' sayan TESHIS
 *  araci) kaldirilsin; 'surekli oynuyor' izleniminin buyuk kismi odur.
 * KULLANIM: pull-updates.php?files=apply-stability-final.php,apply-sbprobe3-remove.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-stability-final BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_STAB_FINAL')!==false) exit("Zaten ekli (CH_STAB_FINAL).\n");
if (strpos($src,'CH_UI_STABILITY')===false) exit("HATA: v1 stabilite katmani yok (beklenmedik durum).\n");

$fail=[];

/* [1] tam gizle + yer tutucu */
$o1 = '.wcat-grid.ch-settling{opacity:.15;pointer-events:none}';
$n1 = '.wcat-grid.ch-settling{opacity:0;pointer-events:none;min-height:55vh} /*CH_STAB_FINAL*/';
$c=0; $src=str_replace($o1,$n1,$src,$c);
echo ($c===1?"  ✓ [1] yerlesirken tam gizli + yer tutucu\n":"  ✗ [1] anchor ($c)\n"); if($c!==1)$fail[]=1;

/* [2] sessizlik 700 -> 1200ms */
$o2 = "    clearTimeout(t); t=setTimeout(reveal, 700);";
$n2 = "    clearTimeout(t); t=setTimeout(reveal, 1200); /*CH_STAB_FINAL*/";
$c=0; $src=str_replace($o2,$n2,$src,$c);
echo ($c===1?"  ✓ [2] sessizlik penceresi 1200ms\n":"  ✗ [2] anchor ($c)\n"); if($c!==1)$fail[]=2;

/* [3] acilis tavani 8sn */
$o3 = "    setTimeout(function(){ if(!shown) reveal(); }, 5000); /* guvenlik: en gec 5 sn */";
$n3 = "    setTimeout(function(){ if(!shown) reveal(); }, 8000); /* guvenlik: en gec 8 sn */ /*CH_STAB_FINAL*/";
$c=0; $src=str_replace($o3,$n3,$src,$c);
echo ($c===1?"  ✓ [3] acilis tavani 8sn\n":"  ✗ [3] anchor ($c)\n"); if($c!==1)$fail[]=3;

/* [4] ulke-degisimi tavani 8sn */
$o4 = "  function resettle(){ shown=false; arm(); setTimeout(function(){ if(!shown) reveal(); }, 5000); }";
$n4 = "  function resettle(){ shown=false; arm(); setTimeout(function(){ if(!shown) reveal(); }, 8000); } /*CH_STAB_FINAL*/";
$c=0; $src=str_replace($o4,$n4,$src,$c);
echo ($c===1?"  ✓ [4] ulke-degisimi tavani 8sn\n":"  ✗ [4] anchor ($c)\n"); if($c!==1)$fail[]=4;

/* [5] acilirken yukseklik kilidi */
$o5 = '  function reveal(){ grids().forEach(function(g){ g.classList.remove("ch-settling"); }); shown=true; }';
$n5 = '  function reveal(){ grids().forEach(function(g){ try{ var h=g.scrollHeight; var cur=parseInt(g.style.minHeight||"0",10)||0; if(h>cur) g.style.minHeight=h+"px"; }catch(e){} g.classList.remove("ch-settling"); }); shown=true; } /*CH_STAB_FINAL*/';
$c=0; $src=str_replace($o5,$n5,$src,$c);
echo ($c===1?"  ✓ [5] acilista yukseklik kilidi\n":"  ✗ [5] anchor ($c)\n"); if($c!==1)$fail[]=5;

if ($fail) { echo "\nHATA: eslesmeyen adimlar: ".implode(', ',$fail)." — index.php DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(),'sf').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-stabfinal-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_STAB_FINAL uygulandi. Acilis/ulke degisiminde izgara gorunmez yerlesir,\n";
echo "   acilinca yuksekligi kilitlenir — kartlar ve altindaki icerik ARTIK ITILMEZ.\n";
