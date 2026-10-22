<?php
/**
 * ChatHelp — PROFIL ZORUNLU (belge uretiminden once ad+soyad+adres sart)
 * ------------------------------------------------------------------------
 *  Kanit (dump-gen-debug3): profil bos (sadece "Acerasoft LLC"+email) oldugu
 *  icin gonderen blogu bos/cizgili kaliyor ve rol karisikligi doguyor.
 *  Duzeltme (CH_PROFILE_REQ): genDoc EN DISTAN sarilir — P.f1 (ad), P.f2
 *  (soyad), P.f3 (sokak) veya P.f4 (PLZ/sehir) bosse uretim BASLAMAZ:
 *  arayuz dilinde net mesaj + Konto/Profil sayfasi acilir. Bekleme-mesaji
 *  sarmalayicisiyla (CH_LAUNCH_UI __wait) cakismamak icin ONUN kurulmasini
 *  bekler, onu sarar ve __wait bayragini tasir (cifte sarma/cift mesaj olmaz;
 *  blokede bekleme mesaji hic gosterilmez).
 * KULLANIM: pull-updates.php?files=apply-profile-required.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-profile-required BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
if(strpos($src,"CH_PROFILE_REQ")!==false) exit("Zaten ekli (CH_PROFILE_REQ).\n");
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-profreq-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_PROFILE_REQ — belge uretiminden once profil (ad+soyad+adres) zorunlu */
try{(function(){
  function UIL(){ try{ var s=localStorage.getItem("ch_uilang"); if(s) return s; }catch(e){} try{ if(typeof UL!=="undefined"&&UL) return UL; }catch(e){} return "de"; }
  var MSG={
    de:"⚠️ Bitte vervollständigen Sie zuerst Ihr Profil (Vor- und Nachname, Straße, PLZ/Ort). Offizielle Schreiben brauchen einen vollständigen Absender — ohne diese Angaben wird kein Dokument erstellt.",
    tr:"⚠️ Lütfen önce profilinizi tamamlayın (ad, soyad, sokak, posta kodu/şehir). Resmî dilekçelerde tam gönderen bilgisi zorunludur — bu bilgiler olmadan belge oluşturulmaz.",
    en:"⚠️ Please complete your profile first (first/last name, street, postal code/city). Official letters require a full sender block — no document is generated without it.",
    fr:"⚠️ Veuillez d'abord compléter votre profil (nom, prénom, rue, code postal/ville). Un courrier officiel exige un expéditeur complet — aucun document ne sera créé sans ces données.",
    es:"⚠️ Complete primero su perfil (nombre, apellidos, calle, código postal/ciudad). Un escrito oficial requiere un remitente completo — sin estos datos no se genera el documento.",
    it:"⚠️ Completi prima il suo profilo (nome, cognome, via, CAP/città). Una lettera ufficiale richiede un mittente completo — senza questi dati il documento non viene creato.",
    nl:"⚠️ Vul eerst uw profiel in (voor- en achternaam, straat, postcode/plaats). Een officiële brief vereist een volledige afzender — zonder deze gegevens wordt geen document gemaakt.",
    pl:"⚠️ Najpierw uzupełnij profil (imię i nazwisko, ulica, kod pocztowy/miasto). Pismo urzędowe wymaga pełnego nadawcy — bez tych danych dokument nie zostanie utworzony.",
    sv:"⚠️ Fyll först i din profil (för- och efternamn, gata, postnummer/ort). Officiella brev kräver fullständig avsändare — utan detta skapas inget dokument.",
    pt:"⚠️ Complete primeiro o seu perfil (nome, apelido, rua, código postal/cidade). Uma carta oficial exige remetente completo — sem estes dados não é gerado documento.",
    ru:"⚠️ Сначала заполните профиль (имя, фамилия, улица, индекс/город). Официальное письмо требует полных данных отправителя — без них документ не создаётся.",
    ar:"⚠️ يرجى أولاً إكمال ملفك الشخصي (الاسم واللقب، الشارع، الرمز البريدي/المدينة). الخطاب الرسمي يتطلب بيانات مرسل كاملة — لن يُنشأ المستند بدونها."
  };
  function profOk(){
    try{
      var p=(typeof P!=="undefined"&&P)?P:{};
      var name=((p.f1||"")+"").trim() && ((p.f2||"")+"").trim();
      var addr=((p.f3||"")+"").trim() || ((p.f4||"")+"").trim();
      return !!(name && addr);
    }catch(e){ return true; } /* P okunamazsa engelleme */
  }
  function block(){
    try{ if(typeof addMsg==="function") addMsg("ai", MSG[UIL()]||MSG.en); }catch(e){}
    try{ if(typeof gP==="function") gP("konto"); }catch(e){}
  }
  function wrap(){
    try{
      if(typeof genDoc!=="function" || genDoc.__profreq) return false;
      /* bekleme sarmalayicisi kurulduktan SONRA sar (blokede bekleme mesaji cikmasin) */
      if(!genDoc.__wait && wrap.__patience>0){ wrap.__patience--; return false; }
      var _g=genDoc;
      var w=async function(){ if(!profOk()){ block(); return; } return _g.apply(this,arguments); };
      w.__profreq=1; w.__wait=1; /* CH_LAUNCH_UI tekrar sarmasin */
      genDoc=w; if(typeof window!=="undefined") window.genDoc=w;
      return true;
    }catch(e){ return false; }
  }
  wrap.__patience=25; /* ~12sn bekleme sarmalayicisini bekle, sonra yine de sar */
  var t=0; function run(){ if(!wrap() && t++<60) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}
CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
if($src===$start) exit("degisiklik yok!\n");

$tmp=$file.".tmp-pr"; file_put_contents($tmp,$src);
$out=[]; $code=0; exec("php -l ".escapeshellarg($tmp)." 2>&1",$out,$code); @unlink($tmp);
if($code!==0){ echo "✗ LINT HATASI — DEGISTIRILMEDI:\n".implode("\n",$out)."\n"; exit; }
file_put_contents($file,$src);
echo "✓ Profil zorunlulugu eklendi (CH_PROFILE_REQ).\n";
echo "  Ad+soyad VE (sokak veya PLZ/sehir) bos ise: uretim baslamaz, arayuz dilinde uyari + Konto acilir.\n";
echo "SIL: rm apply-profile-required.php\n";
echo "Test: profili bosalt -> belge uretmeye calis -> uyari + Konto sayfasi; profili doldur -> uretim normal.\n";
