<?php
/**
 * ChatHelp — apply-chlog3 — SAYFA-ACILIS beacon'i (kok neden testi).
 *  Hipotez: App, service worker (sw.js) onbelleginden ESKI index.php'yi
 *  calistiriyor; bu yuzden bugunku hicbir yama App'te gorunmedi.
 *  Bu betik <head> sonuna, sayfa acilir acilmaz ateslenen beacon koyar:
 *    stamp (bu yamanin damgasi) + sw (service worker sayfayi kontrol ediyor mu)
 *    + isWV + ua. Ayrica 'ausdrucken' iceren her tiklamayi da loglar.
 *  Boylece:
 *   - log'da stamp GORUNURSE  -> App taze sayfayi yukluyor; sorun buton zincirinde.
 *   - log BOS kalirsa         -> App eski/onbellekli sayfayi calistiriyor (SW/cache).
 *  Geri alma: apply-chlog-off.php
 * KULLANIM: /chat/pull2.php?key=...&files=apply-chlog3.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
echo "apply-chlog3 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$dir=__DIR__;
$file=$dir.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");

/* logger yoksa yaz (chlog2 ile ayni dosya) */
if(!is_file($dir.'/chlog9k1.php')){
  $logger = <<<'PHP'
<?php
error_reporting(0);
$K='chl_2607';
if((isset($_GET['k'])?$_GET['k']:'')!==$K){ http_response_code(403); exit; }
$f=__DIR__.'/chlog9k1.txt';
if(isset($_GET['read'])){ header('Content-Type: text/plain; charset=UTF-8'); echo @file_get_contents($f); exit; }
$d=isset($_GET['d'])?(string)$_GET['d']:'';
if($d!==''){ if(@filesize($f)>60000) @unlink($f); @file_put_contents($f, date('H:i:s').'  '.substr($d,0,700)."\n", FILE_APPEND|LOCK_EX); }
header('Content-Type: image/gif');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
PHP;
  @file_put_contents($dir.'/chlog9k1.php',$logger);
  echo "  ✓ chlog9k1.php (logger) yazildi\n";
}

if(strpos($src,'CH_LOADLOG')!==false) exit("  (CH_LOADLOG zaten ekli — App'te sayfayi ac, sonra logu oku)\n");

$stamp=date('Hi');  /* bu yamanin damgasi */
$js='<script>/*CH_LOADLOG*/(function(){try{'
  .'function B(d){try{new Image().src="chlog9k1.php?k=chl_2607&t="+(new Date().getTime())+"&d="+encodeURIComponent(d);}catch(e){}}'
  .'var sw="no-sw";try{if(navigator.serviceWorker){sw=navigator.serviceWorker.controller?"SW-CONTROLLED":"sw-idle";}}catch(e){}'
  .'var wv="?";try{wv=(typeof isWV==="function")?isWV():"fn-yok";}catch(e){}'
  .'B("LOAD stamp='.$stamp.'|sw="+sw+"|isWV="+wv+"|ua="+(navigator.userAgent||""));'
  .'document.addEventListener("click",function(ev){try{var t=ev.target;var s="";for(var i=0;i<4&&t;i++){s=(t.textContent||"").slice(0,60);if(/ausdruck|yazdir|print/i.test(s)){B("CLICK stamp='.$stamp.'|txt="+s);break;}t=t.parentNode;}}catch(e){}},true);'
  .'}catch(e){}})();</script>';

/* <head> sonuna (yoksa <body> sonrasina) yerlestir */
$new=null;$where='';
if(($p=stripos($src,'</head>'))!==false){ $new=substr($src,0,$p).$js.substr($src,$p); $where='</head> oncesi'; }
elseif(preg_match('/<body[^>]*>/i',$src,$m,PREG_OFFSET_CAPTURE)){ $o=$m[0][1]+strlen($m[0][0]); $new=substr($src,0,$o).$js.substr($src,$o); $where='<body> sonrasi'; }
if($new===null) exit("  ✗ <head>/<body> bulunamadi — index degismedi\n");

$tmp=tempnam(sys_get_temp_dir(),'cl3').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0) exit("  ✗ LINT HATASI, index degismedi:\n  ".implode("\n  ",$lo)."\n");

@file_put_contents($file.'.bak-chlog3-'.date('Ymd-His'),$src);
@file_put_contents($file,$new);
if(function_exists('opcache_reset')) @opcache_reset();
echo "  ✓ CH_LOADLOG eklendi ($where), damga=$stamp\n";

echo "\nADIMLAR:\n";
echo " 1) Android App'i TAM kapat-ac. SADECE ACMAK YETER (butona basmana gerek yok).\n";
echo " 2) Sonra Mac'te:\n";
echo "    curl -s \"https://chat-help.com/chat/chlog9k1.php?k=chl_2607&read=1\"\n";
echo "\nOKUMA:\n";
echo "  'LOAD stamp=$stamp|sw=...' gorunurse -> App TAZE sayfayi yukluyor (sorun buton zincirinde).\n";
echo "  sw=SW-CONTROLLED ise -> service worker sayfayi servis ediyor (onbellek supheli).\n";
echo "  HIC satir yoksa -> App ESKI/onbellekli sayfayi calistiriyor; yamalar ona hic ulasmiyor.\n";
