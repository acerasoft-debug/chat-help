<?php
/**
 * ChatHelp — apply-stability-final2 (CH_STAB_FINAL2) — KART BUYUME HATASI GERI ALINIR + kesin sabitlik
 *
 *  HATA (CH_STAB_FINAL [1]+[5] birlesimi): reveal() yukseklik kilidi, izgara
 *  HENUZ 55vh yer tutucusundayken scrollHeight olcuyordu -> SISIRILMIS yukseklik
 *  inline min-height olarak KALICI kilitleniyordu; her hukuk degisiminde sadece
 *  BUYUYORDU (ratchet). Sonuc: "kartlar buyumus" + bos alanlar + gecislerde sallanma.
 *
 *  DUZELTME (mevcut canli index uzerinde 4 cerrahi degisiklik):
 *   [1] 55vh yer tutucu KALDIRILIR (opacity:0 zaten yer kaplar, ekstra sisirme yok)
 *       -> kartlar ve izgara ESKI DOGAL BOYUTUNA doner.
 *   [2] reveal() artik yukseklik KILITLEMEZ; tam tersine inline min-height'i
 *       TEMIZLER -> izgara her zaman dogal boyutunda gorunur, buyume imkansiz.
 *   [3] arm() yerlesmeye baslarken MEVCUT yuksekligi GECICI dondurur (yalniz
 *       yerlesme suresince) -> eski kartlar silinirken sayfa COKMEZ; reveal'de
 *       [2] geregi temizlenir. Ayrica her izgaraya (yeniden olusturulsa bile)
 *       childList gozlemcisi takilir -> sessizlik penceresi hep dogru isler.
 *   [4] ULKE DEGISIMI ANINDA YAKALANIR: setCountry/applyCC sarilir, resettle()
 *       yeniden cizim BASLAMADAN calisir -> 400ms yoklama gecikmesindeki
 *       "yeni kartlar bir an gorunup kaybolma" titremesi TAMAMEN biter.
 *       (400ms CC-watch yedek olarak durur; sarma global degilse devreye girer.)
 * KULLANIM: pull-updates.php?files=apply-stability-final2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-stability-final2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_STAB_FINAL2')!==false) exit("Zaten ekli (CH_STAB_FINAL2).\n");
if (strpos($src,'CH_STAB_FINAL')===false) exit("HATA: CH_STAB_FINAL katmani yok (once apply-stability-final gerekli).\n");

$fail=[];

/* [1] 55vh yer tutucuyu kaldir — kartlar eski boyutuna doner */
$o1 = '.wcat-grid.ch-settling{opacity:0;pointer-events:none;min-height:55vh} /*CH_STAB_FINAL*/';
$n1 = '.wcat-grid.ch-settling{opacity:0;pointer-events:none;transition:none} /*CH_STAB_FINAL2*/';
$c=0; $src=str_replace($o1,$n1,$src,$c);
echo ($c===1?"  ✓ [1] 55vh yer tutucu kaldirildi — izgara dogal boyut\n":"  ✗ [1] anchor ($c)\n"); if($c!==1)$fail[]=1;

/* [2] reveal: kilit YOK, inline min-height TEMIZLENIR */
$o2 = '  function reveal(){ grids().forEach(function(g){ try{ var h=g.scrollHeight; var cur=parseInt(g.style.minHeight||"0",10)||0; if(h>cur) g.style.minHeight=h+"px"; }catch(e){} g.classList.remove("ch-settling"); }); shown=true; } /*CH_STAB_FINAL*/';
$n2 = '  function reveal(){ grids().forEach(function(g){ try{ g.style.minHeight=""; }catch(e){} g.classList.remove("ch-settling"); }); shown=true; } /*CH_STAB_FINAL2*/';
$c=0; $src=str_replace($o2,$n2,$src,$c);
echo ($c===1?"  ✓ [2] kalici yukseklik kilidi SOKULDU (buyume hatasinin koku)\n":"  ✗ [2] anchor ($c)\n"); if($c!==1)$fail[]=2;

/* [3] arm: gecici dondurma (yalniz yerlesme suresince) + gozlemciyi garantile */
$o3 = '    grids().forEach(function(g){ g.classList.add("ch-settling"); });';
$n3 = '    grids().forEach(function(g){ try{ if(!g._chObs){ g._chObs=new MutationObserver(function(){ if(!shown) arm(); }); g._chObs.observe(g,{childList:true}); } if(!g.classList.contains("ch-settling")){ var h=g.offsetHeight; if(h>0) g.style.minHeight=h+"px"; } }catch(e){} g.classList.add("ch-settling"); }); /*CH_STAB_FINAL2*/';
$c=0; $src=str_replace($o3,$n3,$src,$c);
echo ($c===1?"  ✓ [3] yerlesme sirasinda GECICI yukseklik dondurma + gozlemci garantisi\n":"  ✗ [3] anchor ($c)\n"); if($c!==1)$fail[]=3;

/* [4] ulke degisimi ANINDA yakalansin: setCountry/applyCC sarilir */
$o4 = '  function resettle(){ shown=false; arm(); setTimeout(function(){ if(!shown) reveal(); }, 8000); } /*CH_STAB_FINAL*/';
$n4 = '  function resettle(){ shown=false; arm(); setTimeout(function(){ if(!shown) reveal(); }, 8000); } /*CH_STAB_FINAL*/
  /*CH_STAB_FINAL2 — ulke degisimi ANINDA yakalanir (yoklama beklenmez)*/
  (function(){ var tries=0; function hook(){ var all=true; ["setCountry","applyCC"].forEach(function(n){ try{ var f=window[n]; if(typeof f==="function"){ if(!f._chStab){ var w=function(){ try{ resettle(); }catch(e){} return f.apply(this,arguments); }; w._chStab=1; try{ for(var p in f){ w[p]=f[p]; } }catch(e){} window[n]=w; } } else all=false; }catch(e){ all=false; } }); if(!all && tries++<20) setTimeout(hook,500); } if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",hook); else hook(); })();';
$c=0; $src=str_replace($o4,$n4,$src,$c);
echo ($c===1?"  ✓ [4] setCountry/applyCC sarildi — gecis titremesi bitti\n":"  ✗ [4] anchor ($c)\n"); if($c!==1)$fail[]=4;

if ($fail) { echo "\nHATA: eslesmeyen adimlar: ".implode(', ',$fail)." — index.php DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(),'s2').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-stabfinal2-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_STAB_FINAL2 uygulandi:\n";
echo "   • kartlar/izgara ESKI DOGAL BOYUTUNA dondu (kalici buyume imkansiz)\n";
echo "   • hukuk degisimi ANINDA gizli yerlesir, tek seferde acilir\n";
echo "   • yerlesme sirasinda sayfa yuksekligi sabit tutulur — cokme/ziplamaz\n";
