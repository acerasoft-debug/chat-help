<?php
/**
 * ChatHelp — GOOGLE PLAY ONCESI UI DUZELTMELERI (#1,#2,#3,#5,#9,#10)
 * ------------------------------------------------------------------------
 *  #9 TARIH: form render'da _isDate regex Almanca-merkezli
 *     (/datum|geboren|frist|geburts/). tarih(TR)/date/fecha/scadenza vb.
 *     yakalanmiyordu -> genisletildi. Bu alanlar native takvim (type="date").
 *
 *  #2/#10/#5-chrome: Form bir sohbet baloncugu (.bbl) icinde uretiliyor, i18n
 *     katmani .bbl'i CEVIRMIYOR. Bu yuzden 'Dokument generieren' vb. sabit
 *     Almanca kaliyordu. Hedefli statik sozluk (FUI) ile SADECE bilinen chrome
 *     dizgilerini (butonlar/select placeholder/optional/chip/AI giris cumlesi)
 *     mevcut ulkenin belge diline (DOCLANG[CC]) cevirir. Kullanici girdisine ve
 *     uretilen belgeye DOKUNMAZ.
 *
 *  #3 BEKLEME: genDoc sarilir -> uretim baslayinca 'Lutfen bekleyin, belgeniz
 *     hazirlaniyor...' + typing animasyonu; bittiginde kaldirilir.
 *
 *  #1 STAATLICHE + Almanya kartlari: statik Almanca kartlar .wcat[onclick]
 *     ile isaretli (ulke modullerinin kartlari addEventListener kullaniyor,
 *     onclick ozniteligi YOK). CC!=='DE' iken .wcat[onclick] gizlenir ->
 *     staatliche + tum Almanya'ya ozel kartlar diger ulkelerden kalkar,
 *     sadece secili ulkenin kartlari kalir. showStaatKat da DE disi engellenir.
 *
 * KULLANIM: pull-updates.php?files=apply-launch-ui.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-launch-ui BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
if(strpos($src,"CH_LAUNCH_UI")!==false) exit("Zaten ekli (CH_LAUNCH_UI).\n");
$rep=[];

/* ---- #9: _isDate regex genisletme ---- */
$o9='const _isDate=/datum|geboren|frist|geburts/.test(_ctx)';
$n9='const _isDate=/datum|geboren|frist|geburts|tarih|do[gğ]um|fecha|vencimiento|scadenz|vervaldatum|\bdate\b|\bdata\b/.test(_ctx)';
$c9=substr_count($src,$o9);
if($c9===1){ $src=str_replace($o9,$n9,$src); $rep[]=["#9 tarih regex genisletildi",1]; }
else $rep[]=["#9 tarih regex (anchor $c9 kez!)",0];

/* ---- #1,#2,#3,#5,#10: JS enjeksiyonu ---- */
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,$anchor)===false){
  echo "✗ getDocPrice anchor yok — JS enjekte edilemedi.\n";
} else {
$js=<<<'CHJS'

/* CH_LAUNCH_UI — form chrome cevirisi (#2/#10/#5) + bekleme (#3) + staatliche kapisi (#1) */
try{(function(){
  var DOCLANG={DE:"de",AT:"de",CH:"de",FR:"fr",BE:"fr",LU:"fr",IT:"it",ES:"es",NL:"nl",TR:"tr",GB:"en",US:"en",PL:"pl",SE:"sv",PT:"pt"};
  function L(){ try{ var cc=(typeof CC!=="undefined"&&CC)?CC:"DE"; return DOCLANG[cc]||"de"; }catch(e){ return "de"; } }

  /* --- statik chrome sozlugu (Almanca -> hedef diller) --- */
  var FUI={
    "📄 Dokument generieren →":{tr:"📄 Belge oluştur →",en:"📄 Generate document →",fr:"📄 Générer le document →",es:"📄 Generar documento →",it:"📄 Genera documento →",nl:"📄 Document genereren →",pl:"📄 Utwórz dokument →",sv:"📄 Skapa dokument →",pt:"📄 Gerar documento →"},
    "📄 Dokument erstellen →":{tr:"📄 Belge oluştur →",en:"📄 Generate document →",fr:"📄 Générer le document →",es:"📄 Generar documento →",it:"📄 Genera documento →",nl:"📄 Document genereren →",pl:"📄 Utwórz dokument →",sv:"📄 Skapa dokument →",pt:"📄 Gerar documento →"},
    "⏳ Wird erstellt...":{tr:"⏳ Oluşturuluyor...",en:"⏳ Creating...",fr:"⏳ Création...",es:"⏳ Generando...",it:"⏳ Creazione...",nl:"⏳ Wordt gemaakt...",pl:"⏳ Tworzenie...",sv:"⏳ Skapas...",pt:"⏳ A criar..."},
    "Dokument verbessern →":{tr:"Belgeyi geliştir →",en:"Improve document →",fr:"Améliorer le document →",es:"Mejorar documento →",it:"Migliora documento →",nl:"Document verbeteren →",pl:"Ulepsz dokument →",sv:"Förbättra dokument →",pt:"Melhorar documento →"},
    "— bitte wählen —":{tr:"— lütfen seçin —",en:"— please select —",fr:"— veuillez choisir —",es:"— seleccione —",it:"— seleziona —",nl:"— maak een keuze —",pl:"— wybierz —",sv:"— välj —",pt:"— selecione —"},
    "Sonstiges …":{tr:"Diğer …",en:"Other …",fr:"Autre …",es:"Otro …",it:"Altro …",nl:"Overig …",pl:"Inne …",sv:"Övrigt …",pt:"Outro …"},
    "(optional)":{tr:"(isteğe bağlı)",en:"(optional)",fr:"(facultatif)",es:"(opcional)",it:"(facoltativo)",nl:"(optioneel)",pl:"(opcjonalnie)",sv:"(valfritt)",pt:"(opcional)"}
  };
  var INTRO={
    tr:function(n,c){return n+" belgenizi "+c+" için hazırlıyorum:";},
    en:function(n,c){return "I am preparing your "+n+" for "+c+":";},
    fr:function(n,c){return "Je prépare votre "+n+" pour "+c+" :";},
    es:function(n,c){return "Estoy preparando su "+n+" para "+c+":";},
    it:function(n,c){return "Sto preparando il suo "+n+" per "+c+":";},
    nl:function(n,c){return "Ik maak uw "+n+" voor "+c+":";},
    pl:function(n,c){return "Przygotowuję Twój "+n+" dla "+c+":";},
    sv:function(n,c){return "Jag förbereder ditt "+n+" för "+c+":";},
    pt:function(n,c){return "Estou a preparar o seu "+n+" para "+c+":";}
  };

  function tset(el,txt){ if(txt && el.textContent!==txt) el.textContent=txt; }
  function applyChrome(root){
    var lang=L(); if(lang==="de") return;
    try{
      root.querySelectorAll("button.btn-g").forEach(function(b){ var m=FUI[b.textContent]; if(m&&m[lang]) tset(b,m[lang]); });
      root.querySelectorAll("option").forEach(function(o){ var m=FUI[o.textContent]; if(m&&m[lang]) tset(o,m[lang]); });
      root.querySelectorAll(".fi label span").forEach(function(s){ if(s.textContent==="(optional)"){ var m=FUI["(optional)"]; if(m&&m[lang]) tset(s,m[lang]); } });
      root.querySelectorAll(".chip").forEach(function(c){ var m=FUI[c.textContent]; if(m&&m[lang]) tset(c,m[lang]); });
      /* AI giris cumlesi: 'Ich erstelle Ihren X für Y:' */
      root.querySelectorAll(".bbl").forEach(function(b){
        if(b.__fui) return;
        var t=b.textContent||"";
        var mm=t.match(/^Ich erstelle Ihren ([\s\S]+?) für ([\s\S]+?):\s*$/);
        if(mm){ var f=INTRO[lang]; if(f){ b.textContent=f(mm[1].trim(),mm[2].trim()); b.__fui=1; } }
      });
    }catch(e){}
  }

  /* --- #3 bekleme mesaji --- */
  var WAIT={de:"Bitte warten, Ihr Dokument wird erstellt …",tr:"Lütfen bekleyin, belgeniz hazırlanıyor …",en:"Please wait, your document is being created …",fr:"Veuillez patienter, votre document est en cours de création …",es:"Espere por favor, su documento se está creando …",it:"Attendere, il documento è in fase di creazione …",nl:"Even geduld, uw document wordt aangemaakt …",pl:"Proszę czekać, dokument jest tworzony …",sv:"Vänta, ditt dokument skapas …",pt:"Aguarde, o seu documento está a ser criado …"};
  function wrapGenDoc(){
    try{
      if(typeof genDoc!=="function" || genDoc.__wait) return;
      var _g=genDoc;
      var w=async function(){
        var lang=L();
        try{
          if(typeof addMsg==="function"){
            var n=document.createElement("div"); n.id="ch-wait-msg";
            n.innerHTML='<div class="typing" style="margin-bottom:6px"><span></span><span></span><span></span></div><div style="font-size:13px;color:var(--t3)">'+(WAIT[lang]||WAIT.de)+'</div>';
            addMsg("ai",n);
          }
        }catch(e){}
        try{ return await _g.apply(this,arguments); }
        finally{ try{ var wm=document.getElementById("ch-wait-msg"); if(wm){ (wm.closest(".msg")||wm).remove(); } }catch(e){} }
      };
      w.__wait=1; genDoc=w; if(typeof window!=="undefined") window.genDoc=w;
    }catch(e){}
  }

  /* --- #1 staatliche + Almanya kartlari sadece DE'de --- */
  function gateGermanCards(){
    try{
      var isDE=(typeof CC!=="undefined"&&CC==="DE");
      document.querySelectorAll(".wcat[onclick]").forEach(function(el){
        el.style.display=isDE?"":"none";
      });
    }catch(e){}
  }
  function gateStaatFn(){
    try{
      if(typeof showStaatKat!=="function" || showStaatKat.__gate) return;
      var _s=showStaatKat;
      var w=function(k){ try{ if(typeof CC!=="undefined"&&CC!=="DE") return; }catch(e){} return _s.apply(this,arguments); };
      w.__gate=1; showStaatKat=w; if(typeof window!=="undefined") window.showStaatKat=w;
    }catch(e){}
  }

  var msgs=null;
  function boot(){
    msgs=document.getElementById("msgs")||document.body;
    applyChrome(msgs); gateGermanCards(); wrapGenDoc(); gateStaatFn();
    try{ new MutationObserver(function(){ applyChrome(msgs); }).observe(msgs,{childList:true,subtree:true}); }catch(e){}
  }
  var ticks=0;
  function run(){ wrapGenDoc(); gateStaatFn(); gateGermanCards(); if(msgs) applyChrome(msgs); if(ticks++<50) setTimeout(run,600); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",function(){ boot(); run(); }); else { boot(); run(); }
})();}catch(e){}
CHJS;
  $src=str_replace($anchor,$anchor.$js,$src);
  $rep[]=["#1/#2/#3/#5/#10 JS enjekte edildi",1];
}

/* ---- lint + kaydet ---- */
if($src===$start){ echo "Degisiklik yok!\n"; foreach($rep as $r) echo "  ".$r[0]." -> ".$r[1]."\n"; exit; }
$tmp=$file.".tmp-lui"; file_put_contents($tmp,$src);
$out=[]; $code=0; exec("php -l ".escapeshellarg($tmp)." 2>&1",$out,$code); @unlink($tmp);
if($code!==0){ echo "✗ LINT HATASI — index.php DEGISTIRILMEDI:\n".implode("\n",$out)."\n"; exit; }
file_put_contents($file.".bak-launchui-".date("Ymd-His"),$start);
file_put_contents($file,$src);

echo "✓ CH_LAUNCH_UI uygulandi:\n";
foreach($rep as $r) echo ($r[1]>0?"  OK  ":"  ✗   ").$r[0]."\n";
echo "SIL: rm apply-launch-ui.php\n";
echo "Test #9: TR sec -> tarih alani (Gidis-donus tarihleri) native takvim gostermeli.\n";
echo "Test #2: TR sec -> buton 'Belge oluştur →', select 'lütfen seçin', giris cumlesi Turkce.\n";
echo "Test #3: belge uret -> 'Lütfen bekleyin, belgeniz hazırlanıyor …' + noktalar; bitince kalkar.\n";
echo "Test #1: TR/ES/... sec -> Jobcenter/Burgergeld/staatliche kartlari GORUNMEMELI; sadece o ulkenin kartlari.\n";
echo "         DE sec -> staatliche + Almanca kartlar geri gelmeli.\n";
