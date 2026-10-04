<?php
/**
 * ChatHelp — ONBOARDING: girişte profil zorunlu + DSGVO/anonimlik notu (CH_ONBOARD)
 * ------------------------------------------------------------------------
 *  1) Site acilisinda (her plan, Free dahil) profil (ad+soyad+adres) BOSSA:
 *     Konto paneli OTOMATIK acilir + arayuz dilinde karsilama mesaji:
 *     "önce bilgilerinizi girin — tüm dilekçeler bu isme yapılır".
 *     (Uretim tarafi zaten CH_PROFILE_REQ ile kilitli; bu, kullaniciyi
 *      dilekceye baslamadan ONCE dogru yere goturur.)
 *  2) Gizlilik/anonimlik notu (CH_ANON_FINAL sayesinde artik %100 dogru):
 *     Konto panelinin ustune + kayit (Anmeldung) formunun ustune eklenir:
 *     "DSGVO/EU uyumlu; ad/adres/iletisim KI'ya ASLA gonderilmez — KI yalniz
 *      anonim placeholder'larla calisir; gercek veriler EU sunucuda yerlestirilir."
 * KULLANIM: pull-updates.php?files=apply-onboarding.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-onboarding BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
if(strpos($src,"CH_ONBOARD")!==false) exit("Zaten ekli (CH_ONBOARD).\n");
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-onboard-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_ONBOARD — giriste profil zorunlu yonlendirme + DSGVO/anonimlik notu */
try{(function(){
  function UIL(){ try{ var s=localStorage.getItem("ch_uilang"); if(s) return s; }catch(e){} try{ if(typeof UL!=="undefined"&&UL) return UL; }catch(e){} return "de"; }
  var PRIV={
    de:"🔒 100% DSGVO/EU-konform · Ihre persönlichen Daten (Name, Adresse, Kontakt) werden NIEMALS an KI-Systeme übermittelt — die KI arbeitet ausschließlich mit anonymen Platzhaltern. Ihre echten Daten werden erst auf dem EU-Server in das fertige Dokument eingesetzt.",
    tr:"🔒 %100 GDPR/AB uyumlu · Kişisel verileriniz (ad, adres, iletişim) hiçbir yapay zekâ sistemine GÖNDERİLMEZ — yapay zekâ yalnızca anonim yer tutucularla çalışır. Gerçek verileriniz belgeye yalnızca AB sunucusunda yerleştirilir.",
    en:"🔒 100% GDPR/EU compliant · Your personal data (name, address, contact) is NEVER sent to any AI system — the AI works only with anonymous placeholders. Your real data is inserted into the finished document on the EU server only.",
    fr:"🔒 100% conforme RGPD/UE · Vos données personnelles (nom, adresse, contact) ne sont JAMAIS transmises à une IA — l'IA ne travaille qu'avec des espaces réservés anonymes. Vos vraies données ne sont insérées que sur le serveur UE.",
    es:"🔒 100% conforme RGPD/UE · Sus datos personales (nombre, dirección, contacto) NUNCA se envían a ninguna IA — la IA trabaja solo con marcadores anónimos. Sus datos reales se insertan únicamente en el servidor de la UE.",
    it:"🔒 100% conforme GDPR/UE · I suoi dati personali (nome, indirizzo, contatto) non vengono MAI inviati ad alcuna IA — l'IA lavora solo con segnaposto anonimi. I dati reali vengono inseriti solo sul server UE."
  };
  var WELCOME={
    de:"👋 Willkommen! Bitte geben Sie zuerst Ihre Daten ein (Vor-/Nachname, Adresse) — alle Dokumente werden auf genau diesen Namen erstellt.",
    tr:"👋 Hoş geldiniz! Lütfen önce bilgilerinizi girin (ad-soyad, adres) — tüm dilekçeler tam olarak bu isme hazırlanır.",
    en:"👋 Welcome! Please enter your details first (name, address) — every document is created in exactly this name.",
    fr:"👋 Bienvenue ! Saisissez d'abord vos données (nom, adresse) — chaque document est établi à ce nom.",
    es:"👋 ¡Bienvenido! Introduzca primero sus datos (nombre, dirección) — cada documento se crea a este nombre.",
    it:"👋 Benvenuto! Inserisca prima i suoi dati (nome, indirizzo) — ogni documento viene creato a questo nome."
  };
  function profOk(){
    try{
      var p=(typeof P!=="undefined"&&P)?P:{};
      return !!(((p.f1||"")+"").trim() && ((p.f2||"")+"").trim() && (((p.f3||"")+"").trim() || ((p.f4||"")+"").trim()));
    }catch(e){ return true; }
  }
  function mkNote(id){
    var d=document.createElement("div"); d.id=id; d.setAttribute("data-noi18n","1");
    d.style.cssText="font-size:11.5px;line-height:1.55;color:#8fd6a0;background:rgba(66,223,148,.07);border:1px solid rgba(66,223,148,.25);border-radius:11px;padding:10px 12px;margin:10px 0";
    d.textContent=PRIV[UIL()]||PRIV.en;
    return d;
  }
  function injectKontoNote(){
    try{
      if(document.getElementById("ch-priv-konto")) return;
      var sc=document.querySelector("#pnl-konto .pnl-scroll"); if(!sc) return;
      sc.insertBefore(mkNote("ch-priv-konto"), sc.firstChild);
    }catch(e){}
  }
  function injectRegisterNote(){
    try{
      if(document.getElementById("ch-priv-reg")) return;
      var inp=document.querySelector('input[placeholder*="vollständiger Name"],input[placeholder*="vollstandiger Name"]');
      if(!inp||!inp.parentNode) return;
      inp.parentNode.insertBefore(mkNote("ch-priv-reg"), inp);
    }catch(e){}
  }
  var redirected=false;
  function onboard(){
    try{
      if(redirected) return;
      if(sessionStorage.getItem("ch_onb")) { redirected=true; return; }
      if(typeof gP!=="function") return;
      if(profOk()){ redirected=true; return; }
      redirected=true; sessionStorage.setItem("ch_onb","1");
      try{ if(typeof addMsg==="function") addMsg("ai",(WELCOME[UIL()]||WELCOME.en)+"\n\n"+(PRIV[UIL()]||PRIV.en)); }catch(e){}
      setTimeout(function(){ try{ gP("konto"); }catch(e){} }, 600);
    }catch(e){}
  }
  var t=0;
  function run(){
    injectKontoNote(); injectRegisterNote();
    if(t===3) onboard(); /* ~1.5sn sonra (uygulama yuklenince) */
    if(t++<80) setTimeout(run,500);
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}
CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
if($src===$start) exit("degisiklik yok!\n");

$tmp=$file.".tmp-onb"; file_put_contents($tmp,$src);
$out=[];$code=0; exec("php -l ".escapeshellarg($tmp)." 2>&1",$out,$code); @unlink($tmp);
if($code!==0){ echo "✗ LINT HATASI — DEGISTIRILMEDI:\n".implode("\n",$out)."\n"; exit; }
file_put_contents($file,$src);
echo "✓ Onboarding eklendi (CH_ONBOARD):\n";
echo "  1) Profil bos ise: karsilama mesaji + Konto OTOMATIK acilir (Free dahil her plan)\n";
echo "  2) DSGVO/anonimlik notu: Konto ustu + Anmeldung formu ustu (arayuz dilinde)\n";
echo "SIL: rm apply-onboarding.php\n";
echo "Test: profili bos bir tarayicida ac -> ~2sn icinde karsilama + Konto acilir + yesil DSGVO notu;\n";
echo "      kayit formunu ac -> ayni not formda da gorunur; profil doluysa hicbir yonlendirme olmaz.\n";
