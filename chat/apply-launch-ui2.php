<?php
/**
 * ChatHelp — KALAN SABIT ALMANCA CHROME CEVIRISI (arayuz diline gore)
 * ------------------------------------------------------------------------
 *  Ekran goruntusu bulgulari: select opsiyonlari (Ja/Nein), foto yukleme
 *  kutusu, input/textarea PLACEHOLDER'lari (i18n katmani placeholder'i asla
 *  ceviremez) hala Almanca kaliyordu.
 *
 *  Duzeltme (CH_LAUNCH_UI2): document.body genelinde, SADECE bilinen Almanca
 *  dizgileri (whitelist) HEDEF ARAYUZ DILINE (ch_uilang) cevirir. Kullanici
 *  girdisine / uretilen belgeye dokunmaz. Option'larda value=Almanca korunur
 *  (yalniz gorunen metin cevrilir) -> backend'e giden veri bozulmaz.
 *
 * KULLANIM: pull-updates.php?files=apply-launch-ui2.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-launch-ui2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
if(strpos($src,"CH_LAUNCH_UI2")!==false) exit("Zaten ekli (CH_LAUNCH_UI2).\n");
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-launchui2-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_LAUNCH_UI2 — kalan sabit Almanca chrome'u arayuz diline (ch_uilang) cevirir */
try{(function(){
  function UIL(){
    try{ var s=localStorage.getItem("ch_uilang"); if(s) return s; }catch(e){}
    try{ if(typeof UL!=="undefined"&&UL) return UL; }catch(e){}
    return "de";
  }
  /* Alman dizgi -> {dil: ceviri}. Diller: en,tr,fr,es,it,nl,pl,sv,pt,ar,ru */
  var D={
    "📄 Dokument generieren →":{en:"📄 Generate document →",tr:"📄 Belge oluştur →",fr:"📄 Générer le document →",es:"📄 Generar documento →",it:"📄 Genera documento →",nl:"📄 Document genereren →",pl:"📄 Utwórz dokument →",sv:"📄 Skapa dokument →",pt:"📄 Gerar documento →",ar:"📄 إنشاء المستند ←",ru:"📄 Создать документ →"},
    "📄 Dokument erstellen →":{en:"📄 Generate document →",tr:"📄 Belge oluştur →",fr:"📄 Générer le document →",es:"📄 Generar documento →",it:"📄 Genera documento →",nl:"📄 Document genereren →",pl:"📄 Utwórz dokument →",sv:"📄 Skapa dokument →",pt:"📄 Gerar documento →",ar:"📄 إنشاء المستند ←",ru:"📄 Создать документ →"},
    "⏳ Wird erstellt...":{en:"⏳ Creating...",tr:"⏳ Oluşturuluyor...",fr:"⏳ Création...",es:"⏳ Generando...",it:"⏳ Creazione...",nl:"⏳ Wordt gemaakt...",pl:"⏳ Tworzenie...",sv:"⏳ Skapas...",pt:"⏳ A criar...",ar:"⏳ جارٍ الإنشاء...",ru:"⏳ Создание..."},
    "Dokument verbessern →":{en:"Improve document →",tr:"Belgeyi geliştir →",fr:"Améliorer le document →",es:"Mejorar documento →",it:"Migliora documento →",nl:"Document verbeteren →",pl:"Ulepsz dokument →",sv:"Förbättra dokument →",pt:"Melhorar documento →",ar:"تحسين المستند ←",ru:"Улучшить документ →"},
    "— bitte wählen —":{en:"— please select —",tr:"— lütfen seçin —",fr:"— veuillez choisir —",es:"— seleccione —",it:"— seleziona —",nl:"— maak een keuze —",pl:"— wybierz —",sv:"— välj —",pt:"— selecione —",ar:"— اختر —",ru:"— выберите —"},
    "Sonstiges …":{en:"Other …",tr:"Diğer …",fr:"Autre …",es:"Otro …",it:"Altro …",nl:"Overig …",pl:"Inne …",sv:"Övrigt …",pt:"Outro …",ar:"أخرى …",ru:"Другое …"},
    "(optional)":{en:"(optional)",tr:"(isteğe bağlı)",fr:"(facultatif)",es:"(opcional)",it:"(facoltativo)",nl:"(optioneel)",pl:"(opcjonalnie)",sv:"(valfritt)",pt:"(opcional)",ar:"(اختياري)",ru:"(необязательно)"},
    "Ja":{en:"Yes",tr:"Evet",fr:"Oui",es:"Sí",it:"Sì",nl:"Ja",pl:"Tak",sv:"Ja",pt:"Sim",ar:"نعم",ru:"Да"},
    "Nein":{en:"No",tr:"Hayır",fr:"Non",es:"No",it:"No",nl:"Nee",pl:"Nie",sv:"Nej",pt:"Não",ar:"لا",ru:"Нет"},
    "Keine":{en:"None",tr:"Yok",fr:"Aucun",es:"Ninguno",it:"Nessuno",nl:"Geen",pl:"Brak",sv:"Ingen",pt:"Nenhum",ar:"لا شيء",ru:"Нет"},
    "Vielleicht":{en:"Maybe",tr:"Belki",fr:"Peut-être",es:"Quizás",it:"Forse",nl:"Misschien",pl:"Może",sv:"Kanske",pt:"Talvez",ar:"ربما",ru:"Возможно"},
    "Dokument oder Foto hochladen":{en:"Upload document or photo",tr:"Belge veya fotoğraf yükle",fr:"Télécharger un document ou une photo",es:"Subir documento o foto",it:"Carica documento o foto",nl:"Document of foto uploaden",pl:"Prześlij dokument lub zdjęcie",sv:"Ladda upp dokument eller foto",pt:"Carregar documento ou foto",ar:"تحميل مستند أو صورة",ru:"Загрузить документ или фото"},
    "Bescheid, Brief, Beleg … Daten werden automatisch übernommen & im Schreiben verwendet":{en:"Notice, letter, receipt … data is captured automatically & used in the document",tr:"Karar, mektup, belge … veriler otomatik alınır ve dilekçede kullanılır",fr:"Avis, lettre, justificatif … les données sont reprises automatiquement et utilisées dans le courrier",es:"Resolución, carta, comprobante … los datos se toman automáticamente y se usan en el escrito",it:"Provvedimento, lettera, ricevuta … i dati vengono acquisiti automaticamente e usati nel documento",nl:"Besluit, brief, bewijs … gegevens worden automatisch overgenomen en in het document gebruikt",pl:"Decyzja, pismo, dokument … dane są pobierane automatycznie i używane w piśmie",sv:"Beslut, brev, kvitto … uppgifter hämtas automatiskt och används i skrivelsen",pt:"Decisão, carta, comprovativo … os dados são recolhidos automaticamente e usados no documento",ar:"قرار، خطاب، إيصال … تُؤخذ البيانات تلقائيًا وتُستخدم في المستند",ru:"Решение, письмо, квитанция … данные берутся автоматически и используются в документе"},
    "Schreiben Sie Ihr Anliegen…":{en:"Write your request…",tr:"Talebinizi yazın…",fr:"Écrivez votre demande…",es:"Escriba su solicitud…",it:"Scriva la sua richiesta…",nl:"Schrijf uw verzoek…",pl:"Napisz swoją sprawę…",sv:"Skriv ditt ärende…",pt:"Escreva o seu pedido…",ar:"اكتب طلبك…",ru:"Опишите ваш запрос…"},
    "Beschreiben Sie Ihre Situation — auch in Ihrer Sprache.":{en:"Describe your situation — also in your language.",tr:"Durumunuzu anlatın — kendi dilinizde de olabilir.",fr:"Décrivez votre situation — également dans votre langue.",es:"Describa su situación — también en su idioma.",it:"Descriva la sua situazione — anche nella sua lingua.",nl:"Beschrijf uw situatie — ook in uw eigen taal.",pl:"Opisz swoją sytuację — także w swoim języku.",sv:"Beskriv din situation — även på ditt språk.",pt:"Descreva a sua situação — também no seu idioma.",ar:"صف حالتك — بلغتك أيضًا.",ru:"Опишите свою ситуацию — можно на вашем языке."},
    "Situation beschreiben…":{en:"Describe the situation…",tr:"Durumu anlatın…",fr:"Décrire la situation…",es:"Describir la situación…",it:"Descrivere la situazione…",nl:"Situatie beschrijven…",pl:"Opisz sytuację…",sv:"Beskriv situationen…",pt:"Descrever a situação…",ar:"صف الحالة…",ru:"Опишите ситуацию…"},
    "Ihre Antwort …":{en:"Your answer …",tr:"Cevabınız …",fr:"Votre réponse …",es:"Su respuesta …",it:"La sua risposta …",nl:"Uw antwoord …",pl:"Twoja odpowiedź …",sv:"Ditt svar …",pt:"A sua resposta …",ar:"إجابتك …",ru:"Ваш ответ …"}
  };

  function tr(s){ if(s==null) return null; var m=D[s]; if(!m) return null; var L=UIL(); if(L==="de") return null; return m[L]||null; }

  function apply(){
    var L=UIL(); if(L==="de") return;
    try{
      /* butonlar / chip'ler */
      document.querySelectorAll("button.btn-g,.chip").forEach(function(b){ var t=tr(b.textContent); if(t&&b.textContent!==t) b.textContent=t; });
      /* select opsiyonlari — value=Almanca korunur */
      document.querySelectorAll("option").forEach(function(o){
        var g=o.getAttribute("data-de")||o.textContent;
        var t=tr(g);
        if(t){ if(!o.getAttribute("data-de")){ o.setAttribute("data-de",g); if(o.value===o.textContent) o.value=g; } if(o.textContent!==t) o.textContent=t; }
      });
      /* (optional) etiketleri */
      document.querySelectorAll(".fi label span").forEach(function(s){ if(s.textContent==="(optional)"){ var t=tr("(optional)"); if(t&&s.textContent!==t) s.textContent=t; } });
      /* foto kutusu: ana metin (text node) + alt aciklama */
      document.querySelectorAll(".cha2-doc-btn .tx").forEach(function(tx){
        for(var i=0;i<tx.childNodes.length;i++){ var nd=tx.childNodes[i]; if(nd.nodeType===3){ var t=tr(nd.nodeValue.trim()); if(t) nd.nodeValue=t; } }
      });
      document.querySelectorAll(".cha2-doc .sub").forEach(function(sb){ var t=tr(sb.textContent.trim()); if(t&&sb.textContent!==t) sb.textContent=t; });
      /* placeholder'lar (input/textarea) */
      document.querySelectorAll("textarea[placeholder],input[placeholder]").forEach(function(el){ var t=tr(el.getAttribute("placeholder")); if(t&&el.getAttribute("placeholder")!==t) el.setAttribute("placeholder",t); });
    }catch(e){}
  }

  function boot(){
    apply();
    try{ new MutationObserver(function(){ apply(); }).observe(document.body,{childList:true,subtree:true}); }catch(e){}
    setInterval(apply, 1000); /* applyUI placeholder'i geri Almancaya cevirirse tekrar duzelt */
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",boot); else boot();
})();}catch(e){}
CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
if($src===$start) exit("degisiklik yok!\n");

$tmp=$file.".tmp-lui2"; file_put_contents($tmp,$src);
$out=[]; $code=0; exec("php -l ".escapeshellarg($tmp)." 2>&1",$out,$code); @unlink($tmp);
if($code!==0){ echo "✗ LINT HATASI — DEGISTIRILMEDI:\n".implode("\n",$out)."\n"; exit; }
file_put_contents($file,$src);
echo "✓ Kalan Almanca chrome cevirisi eklendi (CH_LAUNCH_UI2).\n";
echo "  select opsiyonlari (Ja/Nein/Keine), foto kutusu, placeholder'lar -> arayuz diline (ch_uilang).\n";
echo "  Option value=Almanca korunur (backend verisi bozulmaz).\n";
echo "SIL: rm apply-launch-ui2.php\n";
echo "Test: ES sec -> select 'Ja/Nein' -> 'Sí/No'; foto kutusu ve alt yazi Ispanyolca; ana giris kutusu placeholder Ispanyolca.\n";
