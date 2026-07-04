<?php
/**
 * ChatHelp — PROFIL GORUNTULEME DUZELTMESI (CH_PROFILE_DISPLAY)
 *  Bug: Konto > Profil satirlari (#k-name, #k-email, #k-adr) statik "—" —
 *  hicbir kod bunlari kayitli profilden (P / ch_prof_v3) doldurmuyor.
 *  Duzeltme: sayfa yuklenince + periyodik olarak satirlar P'den dolar
 *  (P bos ise localStorage ch_prof_v3'ten okunur); ulke satiri da CC'den.
 * KULLANIM: pull-updates.php?files=apply-profile-display.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-profile-display BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
if(strpos($src,"CH_PROFILE_DISPLAY")!==false) exit("Zaten ekli (CH_PROFILE_DISPLAY).\n");
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-profdisp-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_PROFILE_DISPLAY — Konto>Profil satirlarini kayitli profilden doldur */
try{(function(){
  function getP(){
    var p=null;
    try{ if(typeof P!=="undefined"&&P&&(P.f1||P.f7)) p=P; }catch(e){}
    if(!p){ try{ p=JSON.parse(localStorage.getItem("ch_prof_v3")||"null"); }catch(e){} }
    return p||{};
  }
  function setTxt(id,v){
    var el=document.getElementById(id); if(!el) return;
    var t=(v&&(""+v).trim())?(""+v).trim():"—";
    if(el.textContent!==t) el.textContent=t;
  }
  function fill(){
    try{
      if(!document.getElementById("k-name")) return;
      var p=getP();
      setTxt("k-name", ((p.f1||"")+" "+(p.f2||"")).trim());
      setTxt("k-email", p.f7||"");
      var adr=[(p.f3||"").trim(),(p.f4||"").trim()].filter(Boolean).join(", ");
      setTxt("k-adr", adr);
      try{
        var cc=(typeof CC!=="undefined"&&CC)?CC:"DE";
        var c=(typeof CC_DATA!=="undefined"&&CC_DATA[cc])?CC_DATA[cc]:null;
        if(c){ var el=document.getElementById("k-cc"); if(el){ var t=c.f+" "+c.n; if(el.textContent!==t) el.textContent=t; } }
      }catch(e){}
    }catch(e){}
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",fill); else fill();
  setInterval(fill, 1200); /* profil kaydedilince/panel acilinca otomatik guncel */
})();}catch(e){}
CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
if($src===$start) exit("degisiklik yok!\n");

$tmp=$file.".tmp-pd"; file_put_contents($tmp,$src);
$out=[];$code=0; exec("php -l ".escapeshellarg($tmp)." 2>&1",$out,$code); @unlink($tmp);
if($code!==0){ echo "✗ LINT HATASI — DEGISTIRILMEDI:\n".implode("\n",$out)."\n"; exit; }
file_put_contents($file,$src);
echo "✓ Profil goruntuleme duzeltildi (CH_PROFILE_DISPLAY).\n";
echo "  Konto > Profil: Name/E-Mail/Adresse artik kayitli profilden dolar; Land CC'den.\n";
echo "SIL: rm apply-profile-display.php\n";
echo "Test: profil kayitliyken Konto ac -> Name/E-Mail/Adresse DOLU; profili duzenle-kaydet -> ~1sn icinde satirlar guncellenir.\n";
