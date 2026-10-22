<?php
/**
 * ChatHelp — apply-stability-final4 (CH_STAB_FINAL4) — DE-disi kart oynamasi KESIN SON
 *
 *  ASIMETRININ KANITLI SEBEBI: Almanya kartlari sayfanin ilk yuklemesinde hazir;
 *  yabanci hukuk kartlari ise 900/1000/1200/1500ms aralikli zamanlayicilarla
 *  SONRADAN ekleniyor. Perdenin sessizlik penceresi 1200ms = zamanlayici
 *  araliklariyla AYNI/KISA -> perde tam bir sonraki kart salvosundan hemen once
 *  aciliyor, kartlar GOZ ONUNDE ekleniyor. Almanca'da ekleme olmadigi icin
 *  sorun yalniz DE-disinda gorunuyordu. (Ayrica ornek-soru bolumunun saniyelik
 *  yeniden-eklemesi sessizligi surekli bozuyordu — CH_EXQ_NATIVE onu durdurdu;
 *  bu yama o duzeltmeyi SART kosar.)
 *
 *  DEGISIKLIKLER:
 *   [1] Sessizlik penceresi 1200ms -> 2000ms (en yavas zamanlayici 1500ms'yi
 *       asar: pespese salvolar perdeyi ACIK TUTAR, tek seferde acilir).
 *   [2] ACILIS SONRASI SIGORTASI: perde acildiktan sonra gelen HER gec kart,
 *       perdeyi otomatik yeniden kapatir (gizli yerlesir, toplu acilir) —
 *       kart eklenirken GORMEK fiziksel olarak imkansiz. Dongu emniyeti:
 *       10 saniyede en fazla 3 yeniden-yerlesme (bilinmeyen bir periyodik
 *       yazici cikarsa sonsuz gizlenme OLMAZ).
 *   [3] Acilis ve ulke-degisim tavanlari 8sn -> 10sn (yavas cihaz emniyeti).
 * KULLANIM: pull-updates.php?files=apply-exq-native.php,apply-stability-final4.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-stability-final4 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_STAB_FINAL4')!==false) exit("Zaten ekli (CH_STAB_FINAL4).\n");
if (strpos($src,'CH_STAB_FINAL3')===false) exit("HATA: CH_STAB_FINAL3 yok.\n");
if (strpos($src,'CH_EXQ_NATIVE')===false) exit("HATA: once apply-exq-native gerekli (saniyelik DOM gurultusunu o durduruyor).\n");

$fail=[];

/* [1] sessizlik 1200 -> 2000ms */
$o1 = "    clearTimeout(t); t=setTimeout(reveal, 1200); /*CH_STAB_FINAL*/";
$n1 = "    clearTimeout(t); t=setTimeout(reveal, 2000); /*CH_STAB_FINAL4*/";
$c=0; $src=str_replace($o1,$n1,$src,$c);
echo ($c===1?"  ✓ [1] sessizlik penceresi 2000ms (tum zamanlayici araliklarini asar)\n":"  ✗ [1] anchor ($c)\n"); if($c!==1)$fail[]=1;

/* [2] acilis-sonrasi sigortasi (iki gozlemcide birden; dongu emniyetli) */
$o2 = 'function(){ if(!shown) arm(); }';
$n2 = 'function(){ if(!shown){ arm(); } else { var R=(window.__chRs=window.__chRs||{n:0,t:0}); var _n=Date.now(); if(_n-R.t>10000){ R.t=_n; R.n=0; } if(++R.n<=3) resettle(); } } /*CH_STAB_FINAL4*/';
$c=0; $src=str_replace($o2,$n2,$src,$c);
echo ($c===2?"  ✓ [2] gec kart sigortasi devrede (2/2 gozlemci; 10sn'de max 3 yeniden-yerlesme)\n":"  ✗ [2] anchor ($c, beklenen 2)\n"); if($c!==2)$fail[]=2;

/* [3a] acilis tavani 10sn */
$o3 = "    setTimeout(function(){ if(!shown) reveal(); }, 8000); /* guvenlik: en gec 8 sn */ /*CH_STAB_FINAL*/";
$n3 = "    setTimeout(function(){ if(!shown) reveal(); }, 10000); /* guvenlik: en gec 10 sn */ /*CH_STAB_FINAL4*/";
$c=0; $src=str_replace($o3,$n3,$src,$c);
echo ($c===1?"  ✓ [3a] acilis tavani 10sn\n":"  ✗ [3a] anchor ($c)\n"); if($c!==1)$fail[]='3a';

/* [3b] ulke-degisim tavani 10sn */
$o4 = '  function resettle(){ shown=false; arm(); setTimeout(function(){ if(!shown) reveal(); }, 8000); } /*CH_STAB_FINAL*/';
$n4 = '  function resettle(){ shown=false; arm(); setTimeout(function(){ if(!shown) reveal(); }, 10000); } /*CH_STAB_FINAL*/ /*CH_STAB_FINAL4*/';
$c=0; $src=str_replace($o4,$n4,$src,$c);
echo ($c===1?"  ✓ [3b] ulke-degisim tavani 10sn\n":"  ✗ [3b] anchor ($c)\n"); if($c!==1)$fail[]='3b';

if ($fail) { echo "\nHATA: eslesmeyen adimlar: ".implode(', ',$fail)." — index.php DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(),'s4').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-stabfinal4-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_STAB_FINAL4 uygulandi:\n";
echo "   • DE-disi hukuklarda kart eklenmesi artik HICBIR ZAMAN goz onunde olmaz:\n";
echo "     perde acikken gelen her gec kart perdeyi kapatir, toplu acilir\n";
echo "   • pespese kart salvolari tek yerlesmede birlesir (2sn sessizlik)\n";
