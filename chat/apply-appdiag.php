<?php
/**
 * ChatHelp — apply-appdiag — App'in gercek yeteneklerini EKRANDA gosterir.
 *  chPrintDoc'un App dalina (if(_isWv2){ ...) bir kerelik popup ekler:
 *  isWV, window.ChelpNative tipi + metodlari, userAgent.
 *  App'te 'Kostenlos selbst ausdrucken' basilinca once bu popup cikar, sonra
 *  normal form-POST akisi devam eder. Hicbir davranis bozulmaz; sadece bilgi.
 *  Geri almak: apply-appdiag-off.php
 * KULLANIM: /chat/pull2.php?key=...&files=apply-appdiag.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-appdiag BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_APPDIAG')!==false) exit("Zaten ekli (CH_APPDIAG) — DEGISIKLIK YOK. App'te butona bas, popup'i oku.\n");

/* chPrintDoc App dali anchor'i */
$anchor='if(_isWv2){';
$pos=strpos($src,$anchor);
if($pos===false) exit("Anchor 'if(_isWv2){' bulunamadi — kod degismis. dump-ausdruck2 gonder.\n");

$diag=' /*CH_APPDIAG*/ try{var _dg="APP-DIAG\n";_dg+="isWV="+_isWv2+"\n";var _cn=window.ChelpNative;_dg+="ChelpNative="+(typeof _cn)+"\n";if(_cn&&(typeof _cn==="object"||typeof _cn==="function")){var _mm=[];for(var _kk in _cn){try{_mm.push(_kk+":"+(typeof _cn[_kk]));}catch(_e1){}}_dg+="m={"+_mm.join(", ")+"}\n";}_dg+="ua="+String(navigator.userAgent||"").slice(0,180);alert(_dg);}catch(_edg){try{alert("DIAGERR "+_edg);}catch(_e2){}}';

/* yalniz ILK if(_isWv2){ sonrasina ekle */
$new=substr($src,0,$pos+strlen($anchor)).$diag.substr($src,$pos+strlen($anchor));

$tmp=tempnam(sys_get_temp_dir(),'ad').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }

@file_put_contents($file.'.bak-appdiag-'.date('Ymd-His'),$src);
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();

$chk=(string)@file_get_contents($file);
echo "  ✓ CH_APPDIAG eklendi (".strlen($src)." -> ".strlen($chk)." bayt)\n";
echo "  kontrol: CH_APPDIAG=".substr_count($chk,'CH_APPDIAG')."\n";
echo "\n✓ App'i TAM kapat-ac -> dilekce -> 'Kostenlos selbst ausdrucken' bas.\n";
echo "  Cikan popup'taki TUM metni bana yaz (isWV / ChelpNative / m={...} / ua).\n";
