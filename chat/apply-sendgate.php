<?php
/**
 * ChatHelp — apply-sendgate (CH_SENDGATE) — FAZ 2: "Schreiben Modu" = gonderim secici hep acik.
 *  Free/kayitsiz kullanici gonderim modalini + Post/Einschreiben/Fax seciciyi GORUR;
 *  odeme/login kapisi sadece COMMIT aninda (PDF-al / Senden / Original-Post).
 *  GUVENLIK: kapi mantigi ESKISIYLE BIREBIR AYNI (_isFreeUser = eski 'token+paket' kapisinin
 *  tersi), sadece 'PDF butonu'ndan 'commit'e tasindi. Kimin ne indirdigi DEGISMEZ.
 *  7 edit, hepsi sayac-korumali, HEPSI-YA-HIC.
 * KULLANIM: pull2.php?key=...&files=apply-sendgate.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-sendgate BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_SENDGATE')!==false) exit("Zaten ekli (CH_SENDGATE).\n");

$edits=[];

/* [1] _isFreeUser + _gate yardimcilari (priceStr oncesine) */
$edits[1]=[
  "  function priceStr(){ var p=plan();",
  "  function _isFreeUser(){ try{ if(!localStorage.getItem('ch_token')) return true; return !(/basic|pro|elite/.test(plan())); }catch(e){ return true; } }/*CH_SENDGATE*/\n".
  "  function _gate(){ if(_isFreeUser()){ try{ if(typeof showPaywall==='function'){ showPaywall((window.CD||''),{}); } else if(typeof window.openAuthModal==='function'){ window.openAuthModal('login'); } }catch(e){} return false; } return true; }\n".
  "  function priceStr(){ var p=plan();"
];

/* [2] doFree commit kapisi */
$edits[2]=[
  "function doFree(){ if(!fillGuard(false)) return;",
  "function doFree(){ if(!_gate())return; if(!fillGuard(false)) return;"
];

/* [3] doHome commit kapisi */
$edits[3]=[
  "function doHome(){\n    if(!fillGuard(false)) return;",
  "function doHome(){\n    if(!_gate())return;\n    if(!fillGuard(false)) return;"
];

/* [4] reallySend commit kapisi */
$edits[4]=[
  "function reallySend(btn){\n    var rec=recVal();",
  "function reallySend(btn){\n    if(!_gate())return;\n    var rec=recVal();"
];

/* [5] cancel: free kullanici yazdirmasin (sadece kapat) */
$edits[5]=[
  "document.getElementById('ai-cancel').onclick=function(){ close(); proceed(CTX.html); };",
  "document.getElementById('ai-cancel').onclick=function(){ var _pd=!_isFreeUser(); close(); if(_pd) proceed(CTX.html); };"
];

/* [6] wrapper fallthrough (mektup-disi belge) free-kapisi */
$edits[6]=[
  "}catch(e){}\n          return _cpd.apply(this,arguments);\n        };",
  "}catch(e){}\n          if(_isFreeUser()){ try{_gate();}catch(e){} return; }\n          return _cpd.apply(this,arguments);\n        };"
];

/* [7] showResult PDF butonu: free kullanici da modali acsin (kapi commit'te) */
$edits[7]=[
  "if(_tk3&&_up3!=='free'){makePDF(doc,d.n,CC);return;}",
  "{makePDF(doc,d.n,CC);return;}/*CH_SENDGATE-modal-acilir*/"
];

/* dogrula: hepsi ==1 */
$fail=[];
foreach($edits as $k=>$e){
  $c=substr_count($src,$e[0]);
  if($c!==1){ echo "  ✗ [$k] anchor ($c, 1 beklenir)\n"; $fail[]=$k; }
  else echo "  ✓ [$k] anchor bulundu\n";
}
if($fail){ echo "\nHATA: eslesmeyen: ".implode(', ',$fail)." — HICBIR sey degistirilmedi.\n"; exit; }

/* uygula */
foreach($edits as $k=>$e){ $src=str_replace($e[0],$e[1],$src); }

$tmp=tempnam(sys_get_temp_dir(),'sg').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-sendgate-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_SENDGATE')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_SENDGATE diskte (".strlen($chk)." B)\n";
echo "  TEST-1 (free/kayitsiz): belge uret -> PDF/Senden -> gonderim modali+secici GORUNUR;\n";
echo "         'Senden'/'PDF-al'/'Original-Post' -> odeme/login kapisi cikar (sizinti YOK).\n";
echo "  TEST-2 (paketli): her sey ESKISI GIBI calisir — modal + gonderim + PDF.\n";
