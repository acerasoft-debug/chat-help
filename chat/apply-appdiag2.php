<?php
/**
 * ChatHelp — apply-appdiag2 — KOSULSUZ teshis popup'i.
 *  chPrintDoc fonksiyonunun EN BASINA (isWV kontrolunden ONCE) bir alert ekler.
 *  Boylece isWV() false donse bile popup HER ZAMAN cikar ve bize sunu gosterir:
 *    - isWV fonksiyonu var mi (typeof)
 *    - isWV() gercekte ne donuyor (true/false/err)
 *    - window.ChelpNative tipi + metod adlari
 *    - userAgent (yeni app surumunun UA'si)
 *  Amac: 'yeni surumde App kendini tanitamiyor mu / kopru gitti mi' sorusunu netlestirmek.
 *  Geri alma: apply-appdiag-off.php (CH_APPDIAG + CH_APPDIAG2 ikisini de siler)
 * KULLANIM: /chat/pull2.php?key=...&files=apply-appdiag2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-appdiag2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_APPDIAG2')!==false) exit("Zaten ekli (CH_APPDIAG2) — DEGISIKLIK YOK. App'te butona bas, popup'i oku.\n");

$anchor='window.chPrintDoc=function(html){';
$pos=strpos($src,$anchor);
if($pos===false) exit("Anchor 'window.chPrintDoc=function(html){' bulunamadi — kod degismis. dump-ausdruck2 gonder.\n");

$diag=' /*CH_APPDIAG2*/ try{var _wv2="?";try{_wv2=String((typeof isWV==="function")?isWV():"YOK");}catch(_e0){_wv2="err:"+_e0;}var _cn=window.ChelpNative;var _dg="APP-DIAG2\nisWVfn="+(typeof isWV)+"\nisWV()="+_wv2+"\nChelpNative="+(typeof _cn)+"\n";if(_cn){var _mm=[];for(var _k in _cn){try{_mm.push(_k);}catch(_e1){}}_dg+="m={"+_mm.join(",")+"}\n";}_dg+="ua="+String(navigator.userAgent||"").slice(0,180);alert(_dg);}catch(_edg){try{alert("DIAG2ERR "+_edg);}catch(_e2){}}';

$new=substr($src,0,$pos+strlen($anchor)).$diag.substr($src,$pos+strlen($anchor));

$tmp=tempnam(sys_get_temp_dir(),'ad2').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }

@file_put_contents($file.'.bak-appdiag2-'.date('Ymd-His'),$src);
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();

$chk=(string)@file_get_contents($file);
echo "  ✓ CH_APPDIAG2 eklendi (".strlen($src)." -> ".strlen($chk)." bayt)\n";
echo "  kontrol: CH_APPDIAG2=".substr_count($chk,'CH_APPDIAG2')."\n";
echo "\n✓ App'i TAM kapat-ac -> dilekce -> 'Kostenlos selbst ausdrucken' bas.\n";
echo "  Bu sefer popup HER DURUMDA cikmali. Cikan metni (isWV()=... satiri dahil) bana yaz.\n";
echo "  Popup YINE cikmazsa: buton chPrintDoc'a hic ulasmiyor demektir — onu ayrica cozeriz.\n";
