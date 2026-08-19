<?php
/**
 * ChatHelp — apply-qr2 (CH_QR_APP) — Google Play QR widget (sol alt) — idempotent duzeltme.
 *  apply-qr'da dogrulama yanlis metni ariyordu; dosya yine de yazilmisti.
 *  Bu surum 'id="ch-qr-wrap"' ile hem tekrar-eklemeyi engeller hem dogrular.
 * KULLANIM: pull2.php?key=...&files=apply-qr2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-qr2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'id="ch-qr-wrap"')!==false) exit("Zaten ekli (QR widget mevcut — canli). Sol altta 'Android-App' pili gorunmeli.\n");

$QR = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0MSIgaGVpZ2h0PSI0MSIgY2xhc3M9InNlZ25vIj48cGF0aCBmaWxsPSIjZmZmIiBkPSJNMCAwaDQxdjQxaC00MXoiLz48cGF0aCBjbGFzcz0icXJsaW5lIiBzdHJva2U9IiMwYjBiMTYiIGQ9Ik0yIDIuNWg3bTIgMGgybTMgMGgxbTEgMGgxbTEgMGgxbTQgMGgxbTEgMGgxbTEgMGgxbTIgMGg3bS0zNyAxaDFtNSAwaDFtMSAwaDJtMSAwaDJtMSAwaDFtMSAwaDFtNiAwaDFtMiAwaDJtMiAwaDFtNSAwaDFtLTM3IDFoMW0xIDBoM20xIDBoMW00IDBoMW0xIDBoNG0yIDBoMW03IDBoMW0yIDBoMW0xIDBoM20xIDBoMW0tMzcgMWgxbTEgMGgzbTEgMGgxbTIgMGgxbTEgMGgxbTMgMGgxbTQgMGgxbTEgMGgxbTEgMGgxbTEgMGgybTIgMGgxbTEgMGgzbTEgMGgxbS0zNyAxaDFtMSAwaDNtMSAwaDFtMSAwaDJtMiAwaDFtMiAwaDFtMiAwaDJtMyAwaDFtMiAwaDJtMiAwaDFtMSAwaDNtMSAwaDFtLTM3IDFoMW01IDBoMW0yIDBoMm0xIDBoMW0xIDBoMm0xIDBoMW0xIDBoMW0xIDBoMW0xIDBoNm0xIDBoMW01IDBoMW0tMzcgMWg3bTEgMGgxbTEgMGgxbTEgMGgxbTEgMGgxbTEgMGgxbTEgMGgxbTEgMGgxbTEgMGgxbTEgMGgxbTEgMGgxbTEgMGgxbTEgMGg3bS0yNiAxaDFtMiAwaDFtMiAwaDNtMSAwaDFtMiAwaDJtMiAwaDFtLTI5IDFoMW0xIDBoMW0xIDBoMW0xIDBoMW0yIDBoNG0xIDBoM20xIDBoMW0zIDBoN20zIDBoMW0yIDBoMW0tMzUgMWgybTEgMGgybTMgMGgxbTUgMGgybTEgMGgybTEgMGgxbTIgMGgybTIgMGgzbTUgMGgxbS0zNiAxaDFtMSAwaDVtMyAwaDJtMSAwaDRtNyAwaDFtNCAwaDNtMSAwaDNtLTM3IDFoMW00IDBoMW0xIDBoM20yIDBoMW0zIDBoNG0xIDBoMW0xIDBoNG0xIDBoMm0xIDBoMW0xIDBoMW0xIDBoMW0tMzMgMWgybTEgMGgxbTEgMGgxbTQgMGgzbTEgMGgxbTMgMGgxbTIgMGgybTEgMGg1bTEgMGgxbTEgMGgybS0zNyAxaDJtMiAwaDFtMiAwaDJtMiAwaDFtMSAwaDFtMiAwaDFtMyAwaDJtNiAwaDNtNCAwaDJtLTM3IDFoMW0xIDBoMW0xIDBoMW0xIDBoM20xIDBoMW0yIDBoMW0yIDBoMm0yIDBoMW0xIDBoMW0yIDBoMW00IDBoMm0yIDBoM20tMzUgMWgzbTIgMGgxbTIgMGg0bTEgMGgybTIgMGgxbTEgMGgxbTEgMGgzbTIgMGgxbTEgMGgxbTEgMGgxbS0zMiAxaDNtMiAwaDJtMyAwaDFtMSAwaDNtNCAwaDFtMSAwaDRtMiAwaDNtMiAwaDFtLTM0IDFoMm0xIDBoM20xIDBoMW0xIDBoMW0xIDBoNW0xIDBoMW0xIDBoMm0zIDBoMW0zIDBoM20zIDBoMW0xIDBoMW0tMzcgMWgxbTEgMGgxbTMgMGg3bTYgMGgzbTIgMGgxbTQgMGgxbTMgMGg0bS0zNyAxaDNtMiAwaDFtMSAwaDFtMSAwaDNtMSAwaDNtMSAwaDFtMiAwaDRtMSAwaDFtMyAwaDFtMiAwaDFtMiAwaDFtLTM2IDFoM20yIDBoM20zIDBoMW0xIDBoMW0yIDBoMm0xIDBoMW0xIDBoMW0xIDBoM20yIDBoM20tMzAgMWgxbTEgMGgybTMgMGgxbTEgMGgxbTEgMGgybTIgMGgybTIgMGgxbTEgMGgxbTEgMGgybTIgMGg0bTEgMGgxbTEgMGgybS0zNyAxaDFtMSAwaDFtMyAwaDJtMSAwaDJtNyAwaDFtMiAwaDFtMyAwaDJtMSAwaDRtMiAwaDNtLTM2IDFoMW0zIDBoMW0xIDBoM20yIDBoMm0xIDBoMW0xIDBoM200IDBoM20xIDBoMW0zIDBoMW0zIDBoMW0tMzIgMWgybTEgMGgxbTEgMGgzbTEgMGgzbTEgMGgxbTQgMGgzbTEgMGgxbTEgMGgybTIgMGgxbTEgMGgxbS0zNSAxaDFtMSAwaDFtMyAwaDJtMiAwaDFtMyAwaDVtMSAwaDFtMiAwaDJtMiAwaDFtMSAwaDJtMSAwaDFtMSAwaDJtLTM3IDFoMW0xIDBoMW0zIDBoMm0zIDBoNm00IDBoMm0xIDBoMW01IDBoNG0xIDBoMm0tMzYgMWgxbTIgMGgxbTIgMGg0bTIgMGgxbTIgMGg0bTEgMGgxbTEgMGg1bTQgMGgxbTIgMGgxbS0zNiAxaDFtMiAwaDFtMiAwaDFtMSAwaDFtMiAwaDJtMiAwaDNtMiAwaDJtMiAwaDNtMSAwaDVtMiAwaDJtLTI5IDFoNm0yIDBoMW0xIDBoMW0xIDBoMm0yIDBoMW0yIDBoMm0zIDBoMm0yIDBoMW0tMzcgMWg3bTMgMGgzbTQgMGgxbTQgMGgxbTMgMGgzbTEgMGgxbTEgMGgybTIgMGgxbS0zNyAxaDFtNSAwaDFtMiAwaDNtMSAwaDFtMSAwaDFtNCAwaDNtMiAwaDFtMSAwaDJtMyAwaDJtMiAwaDFtLTM3IDFoMW0xIDBoM20xIDBoMW0xIDBoMm0xIDBoM201IDBoMm0xIDBoMW0yIDBoMW0yIDBoNm0yIDBoMW0tMzcgMWgxbTEgMGgzbTEgMGgxbTIgMGgxbTIgMGgxbTEgMGgybTEgMGgxbTEgMGg0bTEgMGgxbTYgMGgybTEgMGgybS0zNiAxaDFtMSAwaDNtMSAwaDFtMSAwaDJtMyAwaDJtNCAwaDJtNCAwaDFtMyAwaDFtMiAwaDJtMSAwaDJtLTM3IDFoMW01IDBoMW02IDBoMW0xIDBoMW0xIDBoMW0yIDBoNG0xIDBoMW0xIDBoM20xIDBoMm0yIDBoMW0tMzYgMWg3bTEgMGgzbTEgMGgxbTEgMGgxbTEgMGgybTEgMGg0bTEgMGgybTEgMGgybTYgMGgyIi8+PC9zdmc+Cg==';

$block = '<style id="ch-qr-css">/* CH_QR_APP */'
.'#ch-qr-wrap{position:fixed;left:12px;bottom:86px;z-index:9000;font-family:Inter,system-ui,sans-serif}'
.'#ch-qr-pill{display:flex;align-items:center;gap:7px;background:linear-gradient(135deg,#12122a,#191930);border:1px solid rgba(212,168,74,.45);color:#e8c874;border-radius:100px;padding:8px 13px;font-size:12.5px;font-weight:700;cursor:pointer;box-shadow:0 6px 18px rgba(0,0,0,.35);-webkit-tap-highlight-color:transparent}'
.'#ch-qr-pill svg{width:15px;height:15px;stroke:#e8c874;fill:none;stroke-width:1.9}'
.'#ch-qr-panel{display:none;position:absolute;left:0;bottom:46px;width:186px;background:#0d0d1c;border:1px solid rgba(212,168,74,.3);border-radius:14px;padding:14px;box-shadow:0 12px 34px rgba(0,0,0,.5)}'
.'#ch-qr-wrap.open #ch-qr-panel{display:block}'
.'#ch-qr-panel img{width:158px;height:158px;display:block;margin:0 auto;background:#fff;border-radius:8px;padding:6px}'
.'#ch-qr-t{font-size:12px;color:#c8c8e0;text-align:center;margin:9px 0 10px;line-height:1.4}'
.'#ch-qr-btn{display:block;text-align:center;background:linear-gradient(135deg,#d4a84a,#ecc060);color:#07070e;border-radius:9px;padding:9px;font-size:12.5px;font-weight:800;text-decoration:none}'
.'#ch-qr-x{position:absolute;top:6px;right:8px;color:#7a7a92;font-size:15px;cursor:pointer;line-height:1;padding:2px 5px}'
.'#ch-qr-x:hover{color:#fff}'
.'@media(max-width:520px){#ch-qr-wrap{bottom:92px;left:8px}}'
.'</style>'
.'<div id="ch-qr-wrap">'
.'<div id="ch-qr-panel"><span id="ch-qr-x" title="Schließen">&times;</span>'
.'<img src="'.$QR.'" alt="ChatHelp Android App — Google Play QR">'
.'<div id="ch-qr-t"></div>'
.'<a id="ch-qr-btn" href="https://play.google.com/store/apps/details?id=com.acerasoft.chathelp" target="_blank" rel="noopener">▶ Google Play</a>'
.'</div>'
.'<div id="ch-qr-pill"><svg viewBox="0 0 24 24"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12" y2="18"/></svg><span id="ch-qr-pl"></span></div>'
.'</div>'
.'<script id="ch-qr-js">'."\n"
.'try{(function(){'."\n"
.'  function lang(){ try{ return (localStorage.getItem("ch_uilang")||"de").slice(0,2); }catch(e){ return "de"; } }'."\n"
.'  var PL={de:"Android-App",tr:"Android Uygulaması",en:"Android App",fr:"App Android",es:"App Android",it:"App Android",nl:"Android-app"};'."\n"
.'  var TT={de:"QR scannen & im Google Play installieren",tr:"QR’ı taratıp Google Play’den kurun",en:"Scan to install from Google Play",fr:"Scannez pour installer",es:"Escanee para instalar",it:"Scansiona per installare",nl:"Scan om te installeren"};'."\n"
.'  try{ if(localStorage.getItem("ch_qr_off")==="1"){ var w0=document.getElementById("ch-qr-wrap"); if(w0) w0.style.display="none"; return; } }catch(e){}'."\n"
.'  function boot(){'."\n"
.'    var wrap=document.getElementById("ch-qr-wrap"); if(!wrap) return;'."\n"
.'    var pl=document.getElementById("ch-qr-pl"); if(pl) pl.textContent=PL[lang()]||PL.de;'."\n"
.'    var tt=document.getElementById("ch-qr-t"); if(tt) tt.textContent=TT[lang()]||TT.de;'."\n"
.'    var pill=document.getElementById("ch-qr-pill");'."\n"
.'    if(pill) pill.onclick=function(){ wrap.classList.toggle("open"); };'."\n"
.'    var x=document.getElementById("ch-qr-x");'."\n"
.'    if(x) x.onclick=function(ev){ ev.stopPropagation(); wrap.classList.remove("open"); try{ localStorage.setItem("ch_qr_off","1"); }catch(e){} wrap.style.display="none"; };'."\n"
.'  }'."\n"
.'  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",boot); else boot();'."\n"
.'})();}catch(e){}'."\n"
.'</script>';

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);
$tmp = tempnam(sys_get_temp_dir(),'qr').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-qr2-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'id="ch-qr-wrap"')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: QR widget diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Sol altta 'Android-App' pili — tiklayinca Google Play QR + buton.\n";
