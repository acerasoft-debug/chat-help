<?php
/**
 * ChatHelp — apply-topbar-dedup-identity (CH_TOPBAR_DEDUP_ID)
 *  SORUN: ust barda iki ayri kimlik gosterimi ayni anda goruluyor:
 *   • #tb-auth-btn / #tb-auth-label -> HESAP adi (ornek: "Acerasoft")
 *   • #ch-actprof                   -> AKTIF PROFIL adi (ornek: "Serdar")
 *  Kullanici SADECE birini istiyor — aktif profil adi (belgeleri dolduran
 *  kisi) gorunsun, hesap adi metni gizlensin (buton yine Konto'ya goturur,
 *  sadece tekrarlayan metin etiketi kaldirilir).
 *  COZUM: syncChip() fonksiyonu, profil cipi GORUNUR oldugunda #tb-auth-label
 *  metnini gizler (buton/ikon KALIR — Konto erisimi bozulmaz); cip gizliyken
 *  (profil yok / cikis yapilmis) hesap adi eskisi gibi geri gelir.
 * KULLANIM: pull-updates.php?files=apply-topbar-dedup-identity.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-topbar-dedup-identity BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_TOPBAR_DEDUP_ID')!==false) exit("Zaten ekli (CH_TOPBAR_DEDUP_ID).\n");

$old = <<<'OLD'
  function syncChip(){
    try{
      var r=tbR(); if(!r) return;
      var nm=actProfileName();
      var chip=document.getElementById("ch-actprof");
      if(!nm || !loggedIn()){ if(chip) chip.style.display="none"; return; }
      if(!chip){
        chip=document.createElement("div"); chip.id="ch-actprof"; chip.title="Aktif profil — değiştir";
        chip.innerHTML='<span class="ap-ic">👤</span><span class="ap-n"></span>';
        chip.addEventListener("click",function(){ try{ gP("konto"); }catch(e){} });
        r.insertBefore(chip, r.firstChild);
      }
      chip.style.display="inline-flex";
      var n=chip.querySelector(".ap-n"); if(n && n.textContent!==nm) n.textContent=nm;
    }catch(e){}
  }
OLD;

$new = <<<'NEW'
  function syncChip(){ /*CH_TOPBAR_DEDUP_ID*/
    try{
      var r=tbR(); if(!r) return;
      var nm=actProfileName();
      var chip=document.getElementById("ch-actprof");
      var authLbl=document.getElementById("tb-auth-label");
      if(!nm || !loggedIn()){
        if(chip) chip.style.display="none";
        if(authLbl && authLbl.hasAttribute("data-ch-hidden")){ authLbl.style.display=""; authLbl.removeAttribute("data-ch-hidden"); }
        return;
      }
      if(!chip){
        chip=document.createElement("div"); chip.id="ch-actprof"; chip.title="Aktif profil — değiştir";
        chip.innerHTML='<span class="ap-ic">👤</span><span class="ap-n"></span>';
        chip.addEventListener("click",function(){ try{ gP("konto"); }catch(e){} });
        r.insertBefore(chip, r.firstChild);
      }
      chip.style.display="inline-flex";
      var n=chip.querySelector(".ap-n"); if(n && n.textContent!==nm) n.textContent=nm;
      /* profil cipi gorunurken hesap adi metnini gizle — tekrar kalksin, buton/ikon Konto'ya goturmeye devam eder */
      if(authLbl && authLbl.style.display!=="none"){ authLbl.style.display="none"; authLbl.setAttribute("data-ch-hidden","1"); }
    }catch(e){}
  }
NEW;

$c=0; $src=str_replace($old,$new,$src,$c);
if ($c!==1) { echo "HATA: syncChip anchor bulunamadi (x$c) — index.php DEGISTIRILMEDI.\n"; exit; }
echo "  ✓ ust barda tekrarlayan hesap-adi metni, profil cipi gorunurken gizlendi\n";

$tmp = tempnam(sys_get_temp_dir(),'td').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-tbdedup-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_TOPBAR_DEDUP_ID uygulandi. Ust barda artik SADECE aktif profil adi (ornek: Serdar)\n";
echo "   gorunuyor; hesap butonu (Konto erisimi) kaliyor ama tekrar eden ismi gostermiyor.\n";
