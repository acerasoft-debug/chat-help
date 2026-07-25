<?php
/**
 * ChatHelp — apply-appdiag3 (regex anchor) — makePDF CH_APPPDF yoluna popup.
 *  free-print butonu buradan geciyor. isWV kontrolunden ONCE kosulsuz alert:
 *  isWV(), olasi tum native kopruler (ChelpNative/Android/JSBridge/median/
 *  navigator.share...), userAgent. Tek popup ile dogru fix netlesir.
 *  Geri alma: apply-appdiag-off.php
 * KULLANIM: /chat/pull2.php?key=...&files=apply-appdiag3.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-appdiag3 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_APPDIAG3')!==false) exit("Zaten ekli (CH_APPDIAG3) — DEGISIKLIK YOK.\n");

$diag='/*CH_APPDIAG3*/ try{var _wv="?";try{_wv=String((typeof isWV==="function")?isWV():"YOK");}catch(_e0){_wv="err:"+_e0;}var _br=[];try{["ChelpNative","Android","AndroidInterface","JSInterface","JSBridge","NativeBridge","AndroidDownload","median","gonative"].forEach(function(n){try{if(typeof window[n]!=="undefined")_br.push(n+":"+(typeof window[n]));}catch(_e){}});if(navigator&&typeof navigator.share!=="undefined")_br.push("navigator.share:function");}catch(_e){}alert("DIAG3\\nisWV()="+_wv+"\\nbridges="+(_br.length?_br.join(", "):"HICBIRI")+"\\nua="+String(navigator.userAgent||"").slice(0,200));}catch(_edg){try{alert("D3ERR "+_edg);}catch(_e2){}} ';

/* CH_APPPDF marker'ina bosluga duyarsiz gir (isWV kontrolunden once) */
$new=preg_replace('/(\/\*CH_APPPDF\*\/)(\s*)/', '$1 '.addcslashes($diag,'\\$').'$2', $src, 1, $cnt);
if($new===null){ echo "preg hatasi\n"; exit; }
if($cnt<1){ echo "Anchor /*CH_APPPDF*/ bulunamadi.\n"; exit; }

$tmp=tempnam(sys_get_temp_dir(),'ad3').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }

@file_put_contents($file.'.bak-appdiag3-'.date('Ymd-His'),$src);
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();

$chk=(string)@file_get_contents($file);
echo "  ✓ CH_APPDIAG3 eklendi (".strlen($src)." -> ".strlen($chk)." bayt), anchor=$cnt\n";
echo "  kontrol: CH_APPDIAG3=".substr_count($chk,'CH_APPDIAG3')."\n";
echo "\n✓ App'i TAM kapat-ac -> dilekce -> 'Kostenlos selbst ausdrucken' bas.\n";
echo "  Popup BU SEFER cikar. 'isWV()=' / 'bridges=' / 'ua=' satirlarinin hepsini bana yaz.\n";
