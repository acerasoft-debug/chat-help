<?php
/**
 * ChatHelp — apply-ios-pwa (CH_IOS_PWA) — iPhone icin premium PWA deneyimi.
 *  Kullanici Safari'de "Ana Ekrana Ekle" ile app gibi kurar. Bu yama:
 *   [1] iOS splash (apple-touch-startup-image) — navy CH, GD ile 9 iPhone
 *       cozunurlugu icin uretilir (icon-512.png kaynak). Beyaz acilis flash'i
 *       yerine premium navy acilis.
 *   [2] <head>'e apple-touch-startup-image link'leri + eksikse apple-touch-icon
 *       + mobile-web-app-capable.
 *   [3] </body> oncesine tek-seferlik premium "Ana Ekrana Ekle" rehberi
 *       (yalniz iOS Safari + standalone degil + daha once kapatilmamis).
 *  Lint + dogrulama + opcache reset.
 * KULLANIM: pull2.php?key=...&files=apply-ios-pwa.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-ios-pwa BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$D=__DIR__; $file="$D/index.php";
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_IOS_PWA')!==false) exit("Zaten ekli (CH_IOS_PWA).\n");

/* ── [1] splash uret (GD) ── */
$SP=[[1290,2796,430,932,3],[1179,2556,393,852,3],[1284,2778,428,926,3],[1170,2532,390,844,3],
     [1125,2436,375,812,3],[1242,2688,414,896,3],[828,1792,414,896,2],[750,1334,375,667,2],[640,1136,320,568,2]];
$srcImg=null;$used='';
foreach(['icon-512-v2.png','icon-512.png','icon-192-v2.png','icon-192.png'] as $ic){
  if(is_file("$D/$ic")){ $im=@imagecreatefrompng("$D/$ic"); if($im){ $srcImg=$im; $used=$ic; break; } }
}
$made=0;
if($srcImg && function_exists('imagecreatetruecolor')){
  echo "  splash kaynak: $used (".imagesx($srcImg)."x".imagesy($srcImg).")\n";
  foreach($SP as $sp){ list($w,$h)=$sp;
    $im=imagecreatetruecolor($w,$h);
    for($y=0;$y<$h;$y++){ $tt=$y/max(1,$h-1);
      $r=10; $g=(int)round(0x15+(0x0f-0x15)*$tt); $b=(int)round(0x33+(0x24-0x33)*$tt);
      $c=imagecolorallocate($im,$r,$g,$b); imageline($im,0,$y,$w-1,$y,$c); }
    $isz=(int)round(min($w,$h)*0.42);
    imagecopyresampled($im,$srcImg,(int)(($w-$isz)/2),(int)(($h-$isz)/2),0,0,$isz,$isz,imagesx($srcImg),imagesy($srcImg));
    if(@imagepng($im,"$D/ios-splash-{$w}x{$h}.png")) $made++;
    imagedestroy($im);
  }
  imagedestroy($srcImg);
  echo "  ✓ [1] $made / ".count($SP)." splash uretildi\n";
} else { echo "  ! [1] GD/kaynak yok — splash atlandi (meta+rehber yine eklenir)\n"; }

/* ── [2] <head> link'leri ── */
$head="\n<!-- CH_IOS_PWA: iOS premium PWA -->\n";
if(strpos($src,'apple-touch-icon')===false) $head.='<link rel="apple-touch-icon" sizes="180x180" href="/chat/icon-180.png">'."\n";
if(strpos($src,'name="mobile-web-app-capable"')===false) $head.='<meta name="mobile-web-app-capable" content="yes">'."\n";
foreach($SP as $sp){ list($w,$h,$dw,$dh,$r)=$sp;
  $head.='<link rel="apple-touch-startup-image" media="(device-width:'.$dw.'px) and (device-height:'.$dh.'px) and (-webkit-device-pixel-ratio:'.$r.') and (orientation:portrait)" href="/chat/ios-splash-'.$w.'x'.$h.'.png">'."\n";
}
$hp=stripos($src,'</head>');
if($hp===false) exit("</head> bulunamadi\n");
$src=substr($src,0,$hp).$head.substr($src,$hp);

/* ── [3] premium "Ana Ekrana Ekle" rehberi ── */
$hint = <<<'HTMLBLOCK'
<style id="ch-ios-install-css">
#ch-ios-install{position:fixed;left:0;right:0;bottom:0;z-index:2147483000;display:none;padding:0 12px calc(env(safe-area-inset-bottom,0px) + 12px)}
#ch-ios-install .ci-card{max-width:520px;margin:0 auto;background:linear-gradient(180deg,#12224e,#0b1533);border:1px solid rgba(212,168,74,.35);border-radius:18px;box-shadow:0 -8px 40px rgba(0,0,0,.5),0 0 0 1px rgba(212,168,74,.08);padding:14px 14px 16px;display:flex;align-items:center;gap:12px;position:relative;animation:ciUp .28s cubic-bezier(.2,.9,.3,1)}
@keyframes ciUp{from{transform:translateY(30px);opacity:0}to{transform:translateY(0);opacity:1}}
#ch-ios-install .ci-ic{width:46px;height:46px;border-radius:11px;flex:0 0 auto;box-shadow:0 4px 14px rgba(0,0,0,.4)}
#ch-ios-install .ci-tx{flex:1;min-width:0}
#ch-ios-install .ci-t{font-weight:800;color:#fff;font-size:14.5px;margin-bottom:2px}
#ch-ios-install .ci-b{color:#c8cbe0;font-size:12.5px;line-height:1.45}
#ch-ios-install .ci-b b{color:#f0c860;font-weight:700}
#ch-ios-install .ci-x{position:absolute;top:8px;right:10px;background:none;border:none;color:#8e93b0;font-size:15px;cursor:pointer;padding:4px}
#ch-ios-install .ci-cta{flex:0 0 auto;align-self:stretch;display:flex;align-items:center;background:linear-gradient(135deg,#e8c877,#d4a84a);color:#171a2b;font-weight:800;border:none;border-radius:11px;padding:0 14px;font-size:13px;cursor:pointer}
</style>
<div id="ch-ios-install">
  <div class="ci-card">
    <img class="ci-ic" src="/chat/icon-180.png" alt="ChatHelp">
    <div class="ci-tx"><div class="ci-t"></div><div class="ci-b"></div></div>
    <button class="ci-cta"></button>
    <button class="ci-x" aria-label="close">✕</button>
  </div>
</div>
<script id="ch-ios-install-js">
try{(function(){
  var ua=navigator.userAgent||'';
  var isIOS=/iPad|iPhone|iPod/.test(ua) && !window.MSStream;
  var standalone=('standalone' in navigator) && navigator.standalone;
  var isSafari=/Safari/.test(ua) && !/CriOS|FxiOS|EdgiOS|GSA|Instagram|FBAN|FBAV/.test(ua);
  if(!isIOS || standalone || !isSafari) return;
  try{ if(localStorage.getItem('ch_ios_hint')==='1') return; }catch(e){}
  function UIL(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  var L={
    de:{t:"ChatHelp als App installieren",b:"Tippe unten auf <b>Teilen</b> ⬆️ und dann <b>„Zum Home‑Bildschirm"</b> — ChatHelp öffnet sich wie eine echte App.",c:"Verstanden"},
    tr:{t:"ChatHelp'i uygulama yap",b:"Alttaki <b>Paylaş</b> ⬆️ simgesine dokun, sonra <b>„Ana Ekrana Ekle"</b> — ChatHelp gerçek uygulama gibi açılır.",c:"Anladım"},
    en:{t:"Install ChatHelp",b:"Tap <b>Share</b> ⬆️ then <b>“Add to Home Screen”</b> — ChatHelp opens like a real app.",c:"Got it"},
    fr:{t:"Installer ChatHelp",b:"Touchez <b>Partager</b> ⬆️ puis <b>« Sur l'écran d'accueil »</b>.",c:"Compris"},
    es:{t:"Instalar ChatHelp",b:"Toca <b>Compartir</b> ⬆️ y luego <b>«Añadir a pantalla de inicio»</b>.",c:"Entendido"},
    it:{t:"Installa ChatHelp",b:"Tocca <b>Condividi</b> ⬆️ poi <b>«Aggiungi a Home»</b>.",c:"Ho capito"}
  };
  var box=document.getElementById('ch-ios-install'); if(!box) return;
  var l=L[UIL()]||L.de;
  box.querySelector('.ci-t').textContent=l.t;
  box.querySelector('.ci-b').innerHTML=l.b;
  box.querySelector('.ci-cta').textContent=l.c;
  function close(){ box.style.display='none'; try{ localStorage.setItem('ch_ios_hint','1'); }catch(e){} }
  box.querySelector('.ci-x').addEventListener('click',close);
  box.querySelector('.ci-cta').addEventListener('click',close);
  setTimeout(function(){ box.style.display='block'; }, 3200);
})();}catch(e){}
</script>
HTMLBLOCK;
$bp=strripos($src,'</body>');
if($bp===false) exit("</body> bulunamadi\n");
$src=substr($src,0,$bp).$hint."\n".substr($src,$bp);

/* lint + yaz */
$tmp=tempnam(sys_get_temp_dir(),'ip').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "\nLINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-iospwa-'.date('Ymd-His'),(string)@file_get_contents($file));
$wn=@file_put_contents($file,$src);
if($wn===false||$wn<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_IOS_PWA')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ DOGRULAMA: CH_IOS_PWA diskte (".strlen($chk)." B)\n";
echo "✓ iOS splash ($made adet) + apple meta + premium 'Ana Ekrana Ekle' rehberi\n";
echo "\niPhone Safari'de siteyi ac -> 3sn sonra alt rehber cikar -> Paylas -> Ana Ekrana Ekle.\n";
echo "Kurulunca navy CH ikon + navy acilis ekrani + tam-ekran app deneyimi.\n";
