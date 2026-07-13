<?php
/**
 * ChatHelp — apply-authclean (CH_AUTHCLEAN) — KRITIK: yeni musteriye owner
 *  kimligi (Acerasoft/Elite) sizmasi. KANIT (dump-authinit): backend doLogin/
 *  doMe DOGRU; init IIFE token VARSA doMe ile dogrulayip basarisizsa temizliyor
 *  — ama token YOKSA eski ch_user'a dokunmuyor. doRegister token dondurmedigi
 *  (email-verify gerekli) icin yeni kayit olan login OLMUYOR; eski ch_user
 *  (owner) fillKonto/chFillKontoUser tarafindan gosterilmeye devam ediyor.
 *  DUZELTME: gecerli token YOKSA stale ch_user temizlenir + ust bar/plan
 *  rozeti "giris yapilmamis" durumuna cekilir. Giris YAPMIS kullanici (token
 *  var) HIC etkilenmez. Tamamen additive — mevcut kod degismez.
 * KULLANIM: pull2.php?key=...&files=apply-authclean.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-authclean BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_AUTHCLEAN')!==false) exit("Zaten ekli (CH_AUTHCLEAN).\n");

$bundle = <<<'HTML'
<script id="ch-authclean">
/* CH_AUTHCLEAN — gecerli token yoksa stale kimlik (eski ch_user) temizlenir */
try{(function(){
  function tok(){ try{ return localStorage.getItem("ch_token"); }catch(e){ return null; } }
  function clean(){
    try{
      if(tok()) return; /* giris yapilmis — asla dokunma */
      var had=false;
      try{ if(localStorage.getItem("ch_user")!==null){ localStorage.removeItem("ch_user"); had=true; } }catch(e){}
      /* token yokken P.plan free olmali; ust bar "Anmelden"; plan rozeti KEIN PLAN */
      try{ if(window.P){ if(P.plan&&P.plan!=="free"){ P.plan="free"; had=true; } } }catch(e){}
      try{
        var lbl=document.getElementById("tb-auth-label");
        if(lbl){ var t=(lbl.textContent||"").trim();
          if(t && t!=="Anmelden" && t!=="Einloggen" && t!=="Anmelden") lbl.textContent="Anmelden"; }
      }catch(e){}
      try{ var pb=document.getElementById("konto-plan-badge"); if(pb && (pb.textContent||"").toUpperCase()!=="KEIN PLAN") pb.textContent="KEIN PLAN"; }catch(e){}
      try{ var nm=document.getElementById("k-plan-name"); if(nm && nm.textContent!=="Kein Plan") nm.textContent="Kein Plan"; }catch(e){}
      try{ var info=document.getElementById("konto-user-info"); if(info) info.style.display="none"; }catch(e){}
    }catch(e){}
  }
  clean();
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",clean);
  setInterval(clean, 1200);
})();}catch(e){}
</script>
HTML;

$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$src = substr($src,0,$pos).$bundle."\n".substr($src,$pos);
echo "  ✓ token yoksa stale ch_user + ust bar/plan rozeti temizleniyor\n";
echo "  ✓ giris yapmis kullanici (token var) etkilenmiyor\n";

$tmp = tempnam(sys_get_temp_dir(),'ac').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
$bk=@file_put_contents($file.'.bak-authclean-'.date('Ymd-His'), @file_get_contents($file));
if ($bk===false) echo "  ⚠ yedek yazilamadi — devam\n";
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_AUTHCLEAN')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_AUTHCLEAN diskte (".strlen($chk)." bayt)\n";
echo "\n✓ CH_AUTHCLEAN uygulandi. Artik giris yapmadan Acerasoft/Elite gozukmez;\n";
echo "   gercek kullanici giris yapinca kendi bilgileri gelir.\n";
