<?php
/**
 * ChatHelp — apply-qr-sidebar (CH_QR_SB) — QR'i yuzen pilden SOL MENU icine tasi.
 *  Yuzen #ch-qr-wrap gizlenir (chat/ana sayfayi kapatmasin). Sol menunun (.sb-new
 *  ebeveyni) EN ALTINA estetik/premium kompakt QR karti eklenir: kucuk QR +
 *  "Android App / Google Play". App icinde (CH_QR_HIDEAPP) zaten gizli kalir.
 * KULLANIM: pull2.php?key=...&files=apply-qr-sidebar.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-qr-sidebar BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_QR_SB')!==false) exit("Zaten ekli (CH_QR_SB).\n");

$QR = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0MSIgaGVpZ2h0PSI0MSIgY2xhc3M9InNlZ25vIj48cGF0aCBmaWxsPSIjZmZmIiBkPSJNMCAwaDQxdjQxaC00MXoiLz48cGF0aCBjbGFzcz0icXJsaW5lIiBzdHJva2U9IiMwYjBiMTYiIGQ9Ik0yIDIuNWg3bTIgMGgybTMgMGgxbTEgMGgxbTEgMGgxbTQgMGgxbTEgMGgxbTEgMGgxbTIgMGg3bS0zNyAxaDFtNSAwaDFtMSAwaDJtMSAwaDJtMSAwaDFtMSAwaDFtNiAwaDFtMiAwaDJtMiAwaDFtNSAwaDFtLTM3IDFoMW0xIDBoM20xIDBoMW00IDBoMW0xIDBoNG0yIDBoMW03IDBoMW0yIDBoMW0xIDBoM20xIDBoMW0tMzcgMWgxbTEgMGgzbTEgMGgxbTIgMGgxbTEgMGgxbTMgMGgxbTQgMGgxbTEgMGgxbTEgMGgxbTEgMGgybTIgMGgxbTEgMGgzbTEgMGgxbS0zNyAxaDFtMSAwaDNtMSAwaDFtMSAwaDJtMiAwaDFtMiAwaDFtMiAwaDJtMyAwaDFtMiAwaDJtMiAwaDFtMSAwaDNtMSAwaDFtLTM3IDFoMW01IDBoMW0yIDBoMm0xIDBoMW0xIDBoMm0xIDBoMW0xIDBoMW0xIDBoMW0xIDBoNm0xIDBoMW01IDBoMW0tMzcgMWg3bTEgMGgxbTEgMGgxbTEgMGgxbTEgMGgxbTEgMGgxbTEgMGgxbTEgMGgxbTEgMGgxbTEgMGgxbTEgMGgxbTEgMGgxbTEgMGg3bS0yNiAxaDFtMiAwaDFtMiAwaDNtMSAwaDFtMiAwaDJtMiAwaDFtLTI5IDFoMW0xIDBoMW0xIDBoMW0xIDBoMW0yIDBoNG0xIDBoM20xIDBoMW0zIDBoN20zIDBoMW0yIDBoMW0tMzUgMWgybTEgMGgybTMgMGgxbTUgMGgybTEgMGgybTEgMGgxbTIgMGgybTIgMGgzbTUgMGgxbS0zNiAxaDFtMSAwaDVtMyAwaDJtMSAwaDRtNyAwaDFtNCAwaDNtMSAwaDNtLTM3IDFoMW00IDBoMW0xIDBoM20yIDBoMW0zIDBoNG0xIDBoMW0xIDBoNG0xIDBoMm0xIDBoMW0xIDBoMW0xIDBoMW0tMzMgMWgybTEgMGgxbTEgMGgxbTQgMGgzbTEgMGgxbTMgMGgxbTIgMGgybTEgMGg1bTEgMGgxbTEgMGgybS0zNyAxaDJtMiAwaDFtMiAwaDJtMiAwaDFtMSAwaDFtMiAwaDFtMyAwaDJtNiAwaDNtNCAwaDJtLTM3IDFoMW0xIDBoMW0xIDBoMW0xIDBoM20xIDBoMW0yIDBoMW0yIDBoMm0yIDBoMW0xIDBoMW0yIDBoMW00IDBoMm0yIDBoM20tMzUgMWgzbTIgMGgxbTIgMGg0bTEgMGgybTIgMGgxbTEgMGgxbTEgMGgzbTIgMGgxbTEgMGgxbTEgMGgxbS0zMiAxaDNtMiAwaDJtMyAwaDFtMSAwaDNtNCAwaDFtMSAwaDRtMiAwaDNtMiAwaDFtLTM0IDFoMm0xIDBoM20xIDBoMW0xIDBoMW0xIDBoNW0xIDBoMW0xIDBoMm0zIDBoMW0zIDBoM20zIDBoMW0xIDBoMW0tMzcgMWgxbTEgMGgxbTMgMGg3bTYgMGgzbTIgMGgxbTQgMGgxbTMgMGg0bS0zNyAxaDNtMiAwaDFtMSAwaDFtMSAwaDNtMSAwaDNtMSAwaDFtMiAwaDRtMSAwaDFtMyAwaDFtMiAwaDFtMiAwaDFtLTM2IDFoM20yIDBoM20zIDBoMW0xIDBoMW0yIDBoMm0xIDBoMW0xIDBoMW0xIDBoM20yIDBoM20tMzAgMWgxbTEgMGgybTMgMGgxbTEgMGgxbTEgMGgybTIgMGgybTIgMGgxbTEgMGgxbTEgMGgybTIgMGg0bTEgMGgxbTEgMGgybS0zNyAxaDFtMSAwaDFtMyAwaDJtMSAwaDJtNyAwaDFtMiAwaDFtMyAwaDJtMSAwaDRtMiAwaDNtLTM2IDFoMW0zIDBoMW0xIDBoM20yIDBoMm0xIDBoMW0xIDBoM200IDBoM20xIDBoMW0zIDBoMW0zIDBoMW0tMzIgMWgybTEgMGgxbTEgMGgzbTEgMGgzbTEgMGgxbTQgMGgzbTEgMGgxbTEgMGgybTIgMGgxbTEgMGgxbS0zNSAxaDFtMSAwaDFtMyAwaDJtMiAwaDFtMyAwaDVtMSAwaDFtMiAwaDJtMiAwaDFtMSAwaDJtMSAwaDFtMSAwaDJtLTM3IDFoMW0xIDBoMW0zIDBoMm0zIDBoNm00IDBoMm0xIDBoMW01IDBoNG0xIDBoMm0tMzYgMWgxbTIgMGgxbTIgMGg0bTIgMGgxbTIgMGg0bTEgMGgxbTEgMGg1bTQgMGgxbTIgMGgxbS0zNiAxaDFtMiAwaDFtMiAwaDFtMSAwaDFtMiAwaDJtMiAwaDNtMiAwaDJtMiAwaDNtMSAwaDVtMiAwaDJtLTI5IDFoNm0yIDBoMW0xIDBoMW0xIDBoMm0yIDBoMW0yIDBoMm0zIDBoMm0yIDBoMW0tMzcgMWg3bTMgMGgzbTQgMGgxbTQgMGgxbTMgMGgzbTEgMGgxbTEgMGgybTIgMGgxbS0zNyAxaDFtNSAwaDFtMiAwaDNtMSAwaDFtMSAwaDFtNCAwaDNtMiAwaDFtMSAwaDJtMyAwaDJtMiAwaDFtLTM3IDFoMW0xIDBoM20xIDBoMW0xIDBoMm0xIDBoM201IDBoMm0xIDBoMW0yIDBoMW0yIDBoNm0yIDBoMW0tMzcgMWgxbTEgMGgzbTEgMGgxbTIgMGgxbTIgMGgxbTEgMGgybTEgMGgxbTEgMGg0bTEgMGgxbTYgMGgybTEgMGgybS0zNiAxaDFtMSAwaDNtMSAwaDFtMSAwaDJtMyAwaDJtNCAwaDJtNCAwaDFtMyAwaDFtMiAwaDJtMSAwaDJtLTM3IDFoMW01IDBoMW02IDBoMW0xIDBoMW0xIDBoMW0yIDBoNG0xIDBoMW0xIDBoM20xIDBoMm0yIDBoMW0tMzYgMWg3bTEgMGgzbTEgMGgxbTEgMGgxbTEgMGgybTEgMGg0bTEgMGgybTEgMGgybTYgMGgyIi8+PC9zdmc+Cg==';

$block = '<style id="ch-qr-sb-css">/* CH_QR_SB */'
.'#ch-qr-wrap{display:none !important}'  /* yuzen pili kaldir */
.'.ch-qr-sb{margin:10px 14px 14px;padding:11px;background:linear-gradient(135deg,rgba(212,168,74,.08),rgba(212,168,74,.02));border:1px solid rgba(212,168,74,.28);border-radius:12px;display:flex;align-items:center;gap:11px}'
.'.ch-qr-sb img{width:60px;height:60px;background:#fff;border-radius:7px;padding:4px;flex-shrink:0}'
.'.ch-qr-sb .qs-t{font-size:12px;font-weight:800;color:#e8c874;margin-bottom:3px;display:flex;align-items:center;gap:5px}'
.'.ch-qr-sb .qs-s{font-size:10.5px;color:#9a9ab0;line-height:1.35;margin-bottom:6px}'
.'.ch-qr-sb a{display:inline-block;font-size:11px;font-weight:700;color:#0b0b16;background:linear-gradient(135deg,#d4a84a,#ecc060);border-radius:7px;padding:5px 10px;text-decoration:none}'
.'</style>'
.'<script id="ch-qr-sb-js">'."\n"
.'try{(function(){'."\n"
.'  function lang(){ try{ return (localStorage.getItem("ch_uilang")||"de").slice(0,2); }catch(e){ return "de"; } }'."\n"
.'  var T={de:["Android-App","QR scannen oder tippen","Google Play →"],tr:["Android Uygulaması","QR’ı tarat ya da dokun","Google Play →"],en:["Android App","Scan the QR or tap","Google Play →"],fr:["App Android","Scannez ou touchez","Google Play →"],es:["App Android","Escanee o toque","Google Play →"]};'."\n"
.'  function inApp(){ try{ var ua=navigator.userAgent||"",ref=document.referrer||""; if(ref.indexOf("android-app://")===0)return true; if(/\\bwv\\b/.test(ua))return true; if(/com\\.acerasoft\\.chathelp/i.test(ua))return true; var sa=(window.matchMedia&&window.matchMedia("(display-mode: standalone)").matches)||window.navigator.standalone; if(sa&&/Android/i.test(ua))return true; return false; }catch(e){ return false; } }'."\n"
.'  function build(){'."\n"
.'    try{'."\n"
.'      if(inApp()) return;'."\n"
.'      if(document.getElementById("ch-qr-sb")) return;'."\n"
.'      var anchor=document.querySelector(".sb-new"); if(!anchor||!anchor.parentNode) return;'."\n"
.'      var l=T[lang()]||T.de;'."\n"
.'      var d=document.createElement("div"); d.className="ch-qr-sb"; d.id="ch-qr-sb";'."\n"
.'      d.innerHTML=\'<img src="'.$QR.'" alt="Google Play QR"><div><div class="qs-t">📱 \'+l[0]+\'</div><div class="qs-s">\'+l[1]+\'</div><a href="https://play.google.com/store/apps/details?id=com.acerasoft.chathelp" target="_blank" rel="noopener">\'+l[2]+\'</a></div>\';'."\n"
.'      anchor.parentNode.appendChild(d);'."\n"
.'    }catch(e){}'."\n"
.'  }'."\n"
.'  var n=0; (function loop(){ build(); var el=document.getElementById("ch-qr-sb"); if(el&&el.parentNode&&el.parentNode.lastElementChild!==el){ try{ el.parentNode.appendChild(el); }catch(e){} } if(n++<200) setTimeout(loop,1000); })();'."\n"
.'})();}catch(e){}'."\n"
.'</script>';

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);
$tmp = tempnam(sys_get_temp_dir(),'qs').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-qrsb-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_QR_SB')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_QR_SB diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Yuzen QR gizlendi; sol menu altina estetik QR karti eklendi. App icinde gizli.\n";
