<?php
/**
 * ChatHelp — apply-konto-realuser (CH_KONTO_REALUSER) — Konto Profil blogu
 *  (#k-name/#k-email/#k-adr) her zaman AKTIF Absender-Profil'i (gercek kullanici,
 *   or. Melissa) gosterir; "Acerasoft LLC" gibi hesap-varsayilani degil.
 *  Kaynak oncelik: ch_profiles[ch_prof_active].d (f1 f2 ad, f7 e-posta, f3/f4/f5
 *  adres) -> profil etiketi -> window.P. MutationObserver + interval ile yanlis
 *  uzerine yazim aninda duzeltilir; profil degisince otomatik guncellenir.
 * KULLANIM: pull2.php?key=...&files=apply-konto-realuser.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-konto-realuser BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_KONTO_REALUSER')!==false) exit("Zaten ekli (CH_KONTO_REALUSER).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-konto-realuser-js">
/* CH_KONTO_REALUSER — Profil blogu = aktif Absender-Profil (gercek kullanici) */
try{(function(){
  function P(){ try{ return window.P||JSON.parse(localStorage.getItem('ch_prof_v3')||'{}')||{}; }catch(e){ return {}; } }
  function actProf(){ try{ var a=JSON.parse(localStorage.getItem('ch_profiles')||'null'); if(!a||!a.length) return null; var i=parseInt(localStorage.getItem('ch_prof_active')||'0',10); if(isNaN(i)||i<0||i>=a.length) i=0; return a[i]; }catch(e){ return null; } }
  function nameOf(){
    var ap=actProf();
    if(ap){ var d=ap.d||{}; var n=((d.f1||'')+' '+(d.f2||'')).trim(); if(n) return n; if(ap.name) return ap.name; }
    var p=P(); var pn=((p.f1||'')+' '+(p.f2||'')).trim(); if(pn) return pn;
    return '';
  }
  function emailOf(){
    var ap=actProf(); if(ap&&ap.d&&ap.d.f7) return ap.d.f7;
    var p=P(); if(p.f7) return p.f7;
    try{ var u=JSON.parse(localStorage.getItem('ch_user')||'null'); if(u&&u.email) return u.email; }catch(e){}
    return '';
  }
  function addrOf(){
    var ap=actProf(), d=(ap&&ap.d)?ap.d:P();
    if(!(d.f3||d.f4||d.f5)) d=P();
    var street=(d.f3||'').trim(), cityline=((d.f4||'')+' '+(d.f5||'')).trim(), parts=[];
    if(street) parts.push(street); if(cityline) parts.push(cityline);
    return parts.join(', ');
  }
  function setEl(id,val){ var e=document.getElementById(id); if(!e||!val) return; if(e.textContent!==val) e.textContent=val; }
  function apply(){ try{ setEl('k-name',nameOf()); setEl('k-email',emailOf()); setEl('k-adr',addrOf()); }catch(e){} }
  function watch(id){ var e=document.getElementById(id); if(!e||e.__ru) return; e.__ru=1; try{ new MutationObserver(function(){ apply(); }).observe(e,{childList:true,characterData:true,subtree:true}); }catch(_){} }
  apply(); ['k-name','k-email','k-adr'].forEach(watch);
  try{ setInterval(function(){ apply(); ['k-name','k-email','k-adr'].forEach(watch); },1200); }catch(e){}
})();}catch(e){}
</script>
HTMLBLOCK;

$pos=strripos($src,'</body>');
if($pos===false) exit("</body> bulunamadi\n");
$new=substr($src,0,$pos).$block."\n".substr($src,$pos);
$tmp=tempnam(sys_get_temp_dir(),'ru').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-realuser-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_KONTO_REALUSER')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ CH_KONTO_REALUSER eklendi (".strlen($chk)." B)\n";
echo "✓ Konto Profil artik aktif Absender-Profil'i (gercek kullanici) gosterir; profil degisince guncellenir.\n";
