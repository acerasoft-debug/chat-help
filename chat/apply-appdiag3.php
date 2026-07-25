<?php
/**
 * ChatHelp — apply-appdiag3 — DOGRU yerde popup: makePDF icindeki CH_APPPPDF yolu.
 *  free-print butonu (doFree->proceed->makePDF) tam buradan geciyor; onceki
 *  popup'lar chPrintDoc'taydi, o yuzden hic cikmadi. Bu popup isWV kontrolunden
 *  ONCE, kosulsuz calisir: isWV()/ChelpNative/userAgent gosterir.
 *  Amac: App iOS mu Android mi + native kopru var mi -> dogru fix icin.
 *  Geri alma: apply-appdiag-off.php (CH_APPDIAG / 2 / 3 hepsini siler)
 * KULLANIM: /chat/pull2.php?key=...&files=apply-appdiag3.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-appdiag3 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_APPDIAG3')!==false) exit("Zaten ekli (CH_APPDIAG3) — DEGISIKLIK YOK. App'te free butona bas, popup'i oku.\n");

$anchor='/*CH_APPPDF*/ /*CH_PDFFORM*/ ';
$cnt=substr_count($src,$anchor);
if($cnt<1) exit("Anchor bulunamadi (CH_APPPDF/PDFFORM). Kod degismis olabilir.\n");

$diag='/*CH_APPDIAG3*/ try{var _cn=window.ChelpNative;var _wv="?";try{_wv=String((typeof isWV==="function")?isWV():"YOK");}catch(_e0){_wv="err:"+_e0;}alert("DIAG3\nisWV()="+_wv+"\nChelpNative="+(typeof _cn)+"\nua="+String(navigator.userAgent||"").slice(0,180));}catch(_edg){try{alert("D3ERR "+_edg);}catch(_e2){}} ';

/* yalniz ILK gecise ekle */
$pos=strpos($src,$anchor);
$new=substr($src,0,$pos+strlen($anchor)).$diag.substr($src,$pos+strlen($anchor));

$tmp=tempnam(sys_get_temp_dir(),'ad3').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }

@file_put_contents($file.'.bak-appdiag3-'.date('Ymd-His'),$src);
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();

$chk=(string)@file_get_contents($file);
echo "  ✓ CH_APPDIAG3 eklendi (".strlen($src)." -> ".strlen($chk)." bayt)\n";
echo "  kontrol: CH_APPDIAG3=".substr_count($chk,'CH_APPDIAG3')."\n";
echo "\n✓ App'i TAM kapat-ac -> dilekce -> 'Kostenlos selbst ausdrucken' bas.\n";
echo "  Popup BU SEFER kesin cikar. Ozellikle 'ua=...' satirini bana yaz (iOS mu Android mi).\n";
