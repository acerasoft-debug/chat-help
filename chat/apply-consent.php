<?php
/**
 * ChatHelp — apply-consent (CH_PIXEL) — DSGVO opt-in onay banner'i + onaya-bagli Meta Pixel altyapisi.
 *   · Pixel SADECE 'Akzeptieren' sonrasi yuklenir (opt-in). Reddedende hic ates almaz.
 *   · Base kod <head>'e (yukleyici), banner </body> oncesine. Cok dilli.
 *   · window.chPixel(ev,data) -> event helper (event yamalari bunu cagirir).
 *   Additive + fail-open + idempotent + php -l lint + .bak + opcache + dogrulama.
 *
 *   AYAR: asagidaki $PIXEL_ID ve $DS_URL'i doldur. $PIXEL_ID bos/placeholder ise pixel
 *   ATES ALMAZ (banner yine de calisir) — guvenli.
 * KULLANIM: /chat/pull2.php?key=...&files=apply-consent.php
 */
$PIXEL_ID='__CH_PIXEL_ID__';         /* <-- Events Manager'daki ~15 haneli Pixel ID */
$DS_URL='/datenschutz';              /* <-- Datenschutzerklarung sayfasinin yolu */

header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-consent BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_PIXEL')!==false) exit("Zaten ekli (CH_PIXEL).\n");

$pxid=json_encode($PIXEL_ID);
$dsurl=json_encode($DS_URL);

/* ── [A] <head> icine: onay-durumu + pixel yukleyici (erken) ── */
$boot=<<<BOOT
<script id="ch-pixel-boot">/*CH_PIXEL — DSGVO opt-in Meta Pixel yukleyici*/(function(){try{
  var PXID=$pxid;
  window.CH_PXID=PXID;
  function consented(){ try{ return localStorage.getItem('ch_consent_v1')==='granted'; }catch(e){ return false; } }
  window.chConsented=consented;
  var loaded=false;
  window.chLoadPixel=function(){
    if(loaded) return; if(!PXID || PXID.charAt(0)==='_') return; if(!consented()) return; loaded=true;
    !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
    try{ fbq('init',PXID); fbq('track','PageView'); }catch(e){}
  };
  window.chPixel=function(ev,data){ try{ if(!consented()) return; window.chLoadPixel(); if(window.fbq) window.fbq('track',ev,data||{}); }catch(e){} };
  if(consented()) window.chLoadPixel();
}catch(e){}})();</script>
BOOT;

/* ilk <head> = gercek dokuman head'i (2301); sonraki ~9 tanesi body-ici JS string'lerinde */
$hpos=strpos($src,'<head>');
if($hpos===false){ echo "  ✗ <head> capasi bulunamadi — DEGISTIRILMEDI.\n"; exit; }
if($hpos>5000){ echo "  ✗ ilk <head> beklenmedik konumda (@$hpos) — DEGISTIRILMEDI.\n"; exit; }
$src=substr($src,0,$hpos+6)."\n".$boot.substr($src,$hpos+6);
echo "  ✓ [BOOT] ilk <head> (@$hpos) icine pixel yukleyici eklendi\n";

/* ── [B] </body> oncesine: cok dilli onay banner'i ── */
$banner=<<<BANNER
<script id="ch-consent-banner">/*CH_PIXEL — onay banner'i*/(function(){try{
  function uil(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2).toLowerCase(); }catch(e){ return 'de'; } }
  try{ if(localStorage.getItem('ch_consent_v1')) return; }catch(e){ return; }
  var DS=$dsurl;
  var T={
    de:{m:'Wir verwenden Cookies für Marketing &amp; Statistik, um unsere Werbung zu verbessern. Sie können frei zustimmen oder ablehnen.',a:'Akzeptieren',d:'Ablehnen',p:'Datenschutz'},
    en:{m:'We use cookies for marketing &amp; statistics to improve our ads. You may freely accept or decline.',a:'Accept',d:'Decline',p:'Privacy'},
    tr:{m:'Reklamlarımızı iyileştirmek için pazarlama &amp; istatistik çerezleri kullanıyoruz. Kabul veya reddedebilirsiniz.',a:'Kabul Et',d:'Reddet',p:'Gizlilik'},
    fr:{m:'Nous utilisons des cookies marketing &amp; statistiques pour améliorer nos publicités. Vous pouvez accepter ou refuser librement.',a:'Accepter',d:'Refuser',p:'Confidentialité'},
    es:{m:'Usamos cookies de marketing &amp; estadística para mejorar nuestros anuncios. Puede aceptar o rechazar libremente.',a:'Aceptar',d:'Rechazar',p:'Privacidad'},
    it:{m:'Usiamo cookie di marketing &amp; statistica per migliorare i nostri annunci. Puoi accettare o rifiutare liberamente.',a:'Accetta',d:'Rifiuta',p:'Privacy'},
    nl:{m:'We gebruiken marketing- &amp; statistiekcookies om onze advertenties te verbeteren. U kunt vrij accepteren of weigeren.',a:'Accepteren',d:'Weigeren',p:'Privacy'},
    ar:{m:'نستخدم ملفات تعريف الارتباط للتسويق &amp; الإحصاء لتحسين إعلاناتنا. يمكنك القبول أو الرفض بحرية.',a:'قبول',d:'رفض',p:'الخصوصية'},
    ru:{m:'Мы используем маркетинговые &amp; статистические файлы cookie для улучшения рекламы. Вы можете принять или отклонить.',a:'Принять',d:'Отклонить',p:'Конфиденциальность'}
  };
  var t=T[uil()]||T.de; var rtl=(uil()==='ar');
  function decide(v){ try{ localStorage.setItem('ch_consent_v1',v); }catch(e){} var b=document.getElementById('ch-cb'); if(b){ b.style.opacity='0'; setTimeout(function(){ try{b.remove();}catch(_){} },300); } if(v==='granted'){ try{ window.chLoadPixel&&window.chLoadPixel(); }catch(e){} } }
  function build(){
    if(document.getElementById('ch-cb')) return;
    var css="#ch-cb{position:fixed;left:12px;right:12px;bottom:12px;z-index:2147482000;max-width:760px;margin:0 auto;background:linear-gradient(180deg,#12233f,#0b1526);border:1px solid rgba(212,168,74,.32);border-radius:16px;box-shadow:0 18px 50px rgba(0,0,0,.5);padding:16px 18px;color:#EAF0F8;font-family:system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;display:flex;flex-wrap:wrap;align-items:center;gap:12px;opacity:1;transition:opacity .3s}"
      +"#ch-cb .cb-m{flex:1;min-width:220px;font-size:13px;line-height:1.5;color:#C7D4E8}"
      +"#ch-cb .cb-m a{color:#E4C688;text-decoration:underline;text-underline-offset:2px}"
      +"#ch-cb .cb-btns{display:flex;gap:8px;flex-wrap:wrap}"
      +"#ch-cb button{border:0;cursor:pointer;font:inherit;font-weight:700;font-size:13.5px;border-radius:999px;padding:10px 20px}"
      +"#ch-cb .cb-a{color:#0B1E38;background:linear-gradient(180deg,#E4C688,#C6A15B)}"
      +"#ch-cb .cb-d{color:#C7D4E8;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.16)}"
      +"@media(max-width:520px){#ch-cb{flex-direction:column;align-items:stretch}#ch-cb .cb-btns{justify-content:stretch}#ch-cb button{flex:1}}";
    var st=document.createElement('style'); st.id='ch-cb-css'; st.textContent=css; (document.head||document.documentElement).appendChild(st);
    var b=document.createElement('div'); b.id='ch-cb'; if(rtl) b.setAttribute('dir','rtl');
    b.innerHTML="<div class='cb-m'>"+t.m+" &nbsp;<a href='"+DS+"'>"+t.p+"</a></div>"
      +"<div class='cb-btns'><button class='cb-d' type='button' data-v='denied'>"+t.d+"</button>"
      +"<button class='cb-a' type='button' data-v='granted'>"+t.a+"</button></div>";
    document.body.appendChild(b);
    b.addEventListener('click',function(e){ var v=e.target&&e.target.getAttribute&&e.target.getAttribute('data-v'); if(v) decide(v); });
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',build); else build();
}catch(e){}})();</script>
BANNER;

$bpos=strrpos($src,'</body>');
if($bpos===false){ echo "  ✗ </body> capasi bulunamadi — DEGISTIRILMEDI.\n"; exit; }
$src=substr($src,0,$bpos).$banner."\n".substr($src,$bpos);
echo "  ✓ [BANNER] </body> oncesine onay banner'i eklendi\n";

/* ── lint + yaz ── */
$tmp=tempnam(sys_get_temp_dir(),'cx').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "  ✗ LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }

@file_put_contents($file.'.bak-consent-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("  ✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_PIXEL')===false) exit("  ✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_PIXEL enjekte edildi (".strlen($chk)." B)\n";
$phset = ($PIXEL_ID!=='' && $PIXEL_ID[0]!=='_');
echo "  · Pixel ID: ".($phset?"AYARLI ($PIXEL_ID)":"AYARLI DEGIL — banner calisir ama pixel ates almaz")."\n";
echo "  · Banner cok dilli, opt-in. 'Akzeptieren' -> pixel yuklenir + PageView.\n";
echo "  · Sirada: apply-pixelevents.php (CompleteRegistration + Purchase).\n";
