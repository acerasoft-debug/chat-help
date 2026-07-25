<?php
/**
 * ChatHelp — apply-chlog — ALERT'SIZ teshis. Butona basinca app, bilgiyi sessizce
 *  sunucuya yollar (Image beacon, her WebView'de calisir); sonra Mac'ten okunur.
 *  1) chlog.php'yi diske yazar (logger: ?d=... yazar, ?read=1 okur, k=chl_2607).
 *  2) index.php makePDF CH_APPPDF yoluna beacon ekler (isWV/bridges/ua yollar).
 *  Geri alma: apply-chlog-off.php
 * KULLANIM: /chat/pull2.php?key=...&files=apply-chlog.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
echo "apply-chlog BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$dir=__DIR__;
$file=$dir.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");

/* 1) chlog.php logger'i diske yaz */
$logger = <<<'PHP'
<?php
/* ChatHelp gecici teshis logger (CH_APPLOG). apply-chlog-off ile silinir. */
error_reporting(0);
$K='chl_2607';
if((isset($_GET['k'])?$_GET['k']:'')!==$K){ http_response_code(403); exit; }
$f=__DIR__.'/chlog.txt';
if(isset($_GET['read'])){ header('Content-Type: text/plain; charset=UTF-8'); echo @file_get_contents($f); exit; }
$d=isset($_GET['d'])?(string)$_GET['d']:'';
if($d!==''){ if(@filesize($f)>60000) @unlink($f); @file_put_contents($f, date('H:i:s').'  '.substr($d,0,700)."\n", FILE_APPEND|LOCK_EX); }
header('Content-Type: image/gif');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
PHP;
$w=@file_put_contents($dir.'/chlog.php',$logger);
echo $w!==false ? "  ✓ chlog.php yazildi ($w bayt)\n" : "  ✗ chlog.php yazilamadi\n";

/* 2) index.php'ye beacon (ALERT YOK) */
if(strpos($src,'CH_APPLOG')!==false){ echo "  (CH_APPLOG zaten var — index'e dokunulmadi)\n"; }
else {
  $beacon='/*CH_APPLOG*/ try{var _wv="?";try{_wv=(typeof isWV==="function")?isWV():"YOK";}catch(_e0){}var _br=[];try{["ChelpNative","Android","AndroidInterface","JSInterface","JSBridge","NativeBridge","AndroidDownload","median","gonative"].forEach(function(n){try{if(typeof window[n]!=="undefined")_br.push(n);}catch(_e){}});if(navigator&&typeof navigator.share!=="undefined")_br.push("share");}catch(_e){}var _d="isWV="+_wv+"|br="+(_br.join(",")||"NONE")+"|ua="+(navigator.userAgent||"");new Image().src="chlog.php?k=chl_2607&t="+(new Date().getTime())+"&d="+encodeURIComponent(_d);}catch(_e9){} ';
  $new=preg_replace('/(\/\*CH_APPPDF\*\/)(\s*)/', '$1 '.addcslashes($beacon,'\\$').'$2', $src, 1, $cnt);
  if($new===null||$cnt<1){ echo "  ✗ CH_APPPDF anchor bulunamadi, index degismedi\n"; }
  else {
    $tmp=tempnam(sys_get_temp_dir(),'cl').'.php'; file_put_contents($tmp,$new);
    $lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
    if($rc!==0){ echo "  ✗ LINT HATASI, index degismedi:\n  ".implode("\n  ",$lo)."\n"; }
    else { @file_put_contents($file.'.bak-chlog-'.date('Ymd-His'),$src); @file_put_contents($file,$new); if(function_exists('opcache_reset'))@opcache_reset(); echo "  ✓ index.php beacon eklendi (anchor=$cnt)\n"; }
  }
}

echo "\nADIMLAR:\n";
echo " 1) Android App'i TAM kapat-ac -> dilekce -> 'Kostenlos selbst ausdrucken' bas (ekranda bir sey olmasa da olur).\n";
echo " 2) Sonra Mac'te SU komutu calistir, ciktisini bana yapistir:\n";
echo "    curl -s \"https://chat-help.com/chat/chlog.php?k=chl_2607&read=1\"\n";
echo "    -> 'isWV=... | br=... | ua=...' satirini gorursen: buton makePDF'e ULASIYOR, sorun sadece indirmede.\n";
echo "    -> BOS gelirse: buton makePDF'e hic ulasmiyor (handler kopuk) — onu ayrica cozeriz.\n";
