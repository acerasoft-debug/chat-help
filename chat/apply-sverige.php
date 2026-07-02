<?php
/**
 * ChatHelp — İSVEÇ MODÜLÜ (~36 belge, 6 kategori, İsveççe) — CH_TR_MODULE ile aynı kanıtlanmış desen
 * -------------------------------------------------------------------------------------------------------
 *  • Kategoriler country:'SE' etiketli -> genelleştirilmiş ülke filtresi (CH_TR_MODULE) otomatik çalışır
 *  • Welcome grid kartları data-cccountry='SE' -> kart görünürlüğü otomatik
 *  • Dil: setCountry('SE') zaten UL='sv' yapıyor (CC_DATA.SE.l) — ek dil kodu gerekmez
 *  • Belge dili/hukuku: api.php'de lawSystems['SE'] + doc-lang SE->sv (apply-lawsys-all.php ile)
 *
 * KULLANIM: pull-updates.php?files=apply-sverige.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-sverige BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,"CH_SE_MODULE")!==false) exit("Zaten ekli (CH_SE_MODULE).\n");
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-se-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_SE_MODULE — İsveç hukuku modülü (deferred; DOCS/CATS hazır olunca yüklenir) */
try{(function(){
  function Pq(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var XC={
    se_uppsagningar:{n:"Uppsägningar",ic:"📄"},
    se_boende:{n:"Boende & Hyra",ic:"🏠"},
    se_arbete:{n:"Arbete",ic:"💼"},
    se_konsument:{n:"Konsument & Tvister",ic:"🛒"},
    se_avtal:{n:"Avtal",ic:"📝"},
    se_myndigheter:{n:"Myndigheter",ic:"🏛️"}
  };
  var X_ORDER=["se_uppsagningar","se_boende","se_arbete","se_konsument","se_avtal","se_myndigheter"];
  var CCODE="SE", DOCWORD="dokument";

  var D={
    /* Uppsägningar */
    se_uppsagning_mobil:{ic:"📱",n:"Uppsägning av mobil/bredband",cat:"se_uppsagningar",tier:"einfach",q:[Pq("operator","Operatör: namn",true),Pq("kundnummer","Kund-/abonnemangsnummer",true),Pq("bindningstid","Har du bindningstid?",false,["Nej","Ja","Vet ej"]),Pq("datum","Önskat uppsägningsdatum",false),Pq("uppgifter","Ditt namn och personnummer",true)]},
    se_uppsagning_gym:{ic:"🏋️",n:"Uppsägning av gymkort",cat:"se_uppsagningar",tier:"einfach",q:[Pq("anlaggning","Gym/klubb: namn och adress",true),Pq("medlemsnummer","Medlemsnummer",false),Pq("datum","Uppsägningsdatum",true),Pq("skal","Skäl (valfritt)",false),Pq("uppgifter","Ditt namn",true)]},
    se_uppsagning_forsakring:{ic:"🛡️",n:"Uppsägning av försäkring",cat:"se_uppsagningar",tier:"einfach",q:[Pq("bolag","Försäkringsbolag: namn och adress",true),Pq("forsakringsnummer","Försäkringsnummer",true),Pq("typ","Typ av försäkring",true,["Bil","Hem","Olycksfall","Liv","Annan"]),Pq("datum","Önskat upphörandedatum",true),Pq("uppgifter","Ditt namn och personnummer",true)]},
    se_avsluta_bankkonto:{ic:"🏦",n:"Avslut av bankkonto",cat:"se_uppsagningar",tier:"einfach",q:[Pq("bank","Bank: namn och kontor",true),Pq("kontonummer","Kontonummer",true),Pq("overforing","Konto för överföring av saldot (valfritt)",false),Pq("uppgifter","Ditt namn och personnummer",true)]},
    se_uppsagning_streaming:{ic:"📺",n:"Uppsägning av streamingtjänst",cat:"se_uppsagningar",tier:"einfach",q:[Pq("tjanst","Tjänst (Netflix, Spotify…)",true),Pq("konto","Kontots e-postadress",true),Pq("datum","Uppsägningsdatum (valfritt)",false),Pq("uppgifter","Ditt namn",true)]},

    /* Boende & Hyra */
    se_uppsagning_hyresavtal:{ic:"🏠",n:"Uppsägning av hyresavtal (3 månader)",cat:"se_boende",tier:"einfach",q:[Pq("hyresvard","Hyresvärd: namn och adress",true),Pq("adress","Bostadens adress",true),Pq("utflytt","Önskat avflyttningsdatum (3 månaders uppsägningstid)",true),Pq("uppgifter","Ditt namn och personnummer",true)]},
    se_deposition:{ic:"💰",n:"Begäran om återbetalning av deposition",cat:"se_boende",tier:"standard",q:[Pq("hyresvard","Hyresvärd: namn och adress",true),Pq("adress","Bostadens adress",true),Pq("utflytt","Datum för avflyttning/nyckelöverlämning",true),Pq("belopp","Depositionens belopp (kr)",true),Pq("konto","Kontonummer för återbetalning",false),Pq("uppgifter","Ditt namn",true)]},
    se_felanmalan:{ic:"🔧",n:"Felanmälan till hyresvärd",cat:"se_boende",tier:"standard",q:[Pq("hyresvard","Hyresvärd/förvaltare: namn och adress",true),Pq("adress","Bostadens adress",true),Pq("problem","Beskrivning av felet/skadan",true),Pq("akut","Är det akut?",false,["Ja","Nej"]),Pq("uppgifter","Ditt namn",true)]},
    se_hyreshojning:{ic:"📈",n:"Invändning mot hyreshöjning",cat:"se_boende",tier:"standard",q:[Pq("hyresvard","Hyresvärd: namn och adress",true),Pq("adress","Bostadens adress",true),Pq("nuvarande","Nuvarande hyra (kr/mån)",true),Pq("ny_hyra","Begärd ny hyra (kr/mån)",true),Pq("skal","Skäl för din invändning",true)]},
    se_andrahand:{ic:"🔑",n:"Ansökan om andrahandsuthyrning",cat:"se_boende",tier:"standard",q:[Pq("hyresvard","Hyresvärd/bostadsrättsförening: namn",true),Pq("adress","Bostadens adress",true),Pq("period","Önskad uthyrningsperiod",true),Pq("skal","Skäl (arbete/studier på annan ort, provboende…)",true),Pq("hyresgast","Andrahandshyresgäst: namn (om känd)",false),Pq("uppgifter","Ditt namn och personnummer",true)]},
    se_hyresavtal:{ic:"📄",n:"Hyresavtal (bostad)",cat:"se_boende",tier:"komplex",q:[Pq("roll","Du är",true,["Hyresvärd","Hyresgäst"]),Pq("motpart","Motpart: namn och personnummer",true),Pq("adress","Bostadens adress",true),Pq("yta","Yta (kvm) och antal rum",false),Pq("start","Startdatum",true),Pq("hyra","Månadshyra (kr)",true),Pq("deposition","Deposition (kr, valfritt)",false),Pq("ingar","Vad som ingår (el, värme, internet…)",false)]},

    /* Arbete */
    se_egen_uppsagning:{ic:"👋",n:"Egen uppsägning (arbetstagare)",cat:"se_arbete",tier:"einfach",q:[Pq("arbetsgivare","Arbetsgivare: namn och adress",true),Pq("befattning","Din befattning",true),Pq("sista_dag","Sista arbetsdag (uppsägningstid vanligen 1 månad)",true),Pq("uppgifter","Ditt namn och personnummer",true)]},
    se_obetald_lon:{ic:"🧾",n:"Krav på obetald lön",cat:"se_arbete",tier:"standard",q:[Pq("arbetsgivare","Arbetsgivare: namn och adress",true),Pq("befattning","Din befattning och anställningsperiod",true),Pq("period","Period för den obetalda lönen",true),Pq("belopp","Belopp (kr)",true),Pq("uppgifter","Ditt namn",true)]},
    se_semesteransokan:{ic:"🏖️",n:"Semesteransökan",cat:"se_arbete",tier:"einfach",q:[Pq("arbetsgivare","Arbetsgivare/chef",true),Pq("fran","Från (datum)",true),Pq("till","Till (datum)",true),Pq("uppgifter","Ditt namn och befattning",true)]},
    se_arbetsgivarintyg:{ic:"📋",n:"Begäran om arbetsgivarintyg",cat:"se_arbete",tier:"einfach",q:[Pq("arbetsgivare","Arbetsgivare: namn och adress",true),Pq("anstallning","Din befattning och anställningsperiod",true),Pq("andamal","Ändamål (a-kassa, ny anställning…)",false),Pq("uppgifter","Ditt namn och personnummer",true)]},
    se_distansarbete:{ic:"🏡",n:"Begäran om distansarbete",cat:"se_arbete",tier:"einfach",q:[Pq("arbetsgivare","Arbetsgivare/chef",true),Pq("befattning","Din befattning",true),Pq("omfattning","Önskad omfattning (dagar/vecka)",true),Pq("skal","Skäl (valfritt)",false)]},
    se_las_tvist:{ic:"⚖️",n:"Tvist om uppsägning (LAS)",cat:"se_arbete",tier:"komplex",q:[Pq("arbetsgivare","Arbetsgivare: namn, org.nr och adress",true),Pq("anstallning","Din befattning och anställningstid",true),Pq("uppsagning","Uppsägningen (datum och angivet skäl)",true),Pq("omstandigheter","Omständigheter (varför uppsägningen saknar saklig grund)",true),Pq("yrkande","Ditt yrkande (ogiltigförklaring, skadestånd kr…)",true),Pq("uppgifter","Ditt namn, personnummer och adress",true)]},

    /* Konsument & Tvister */
    se_angerratt:{ic:"🔄",n:"Ångerrätt 14 dagar (distansavtal)",cat:"se_konsument",tier:"einfach",q:[Pq("saljare","Säljare: namn och adress",true),Pq("ordernummer","Ordernummer",true),Pq("datum","Datum för köp/leverans",true),Pq("varor","Berörda varor",true),Pq("konto","Konto för återbetalning (valfritt)",false)]},
    se_reklamation:{ic:"🔧",n:"Reklamation av vara (konsumentköplagen)",cat:"se_konsument",tier:"standard",q:[Pq("saljare","Säljare: namn och adress",true),Pq("vara","Vara (märke/modell)",true),Pq("kopdatum","Inköpsdatum",true),Pq("fel","Felet som upptäckts",true),Pq("krav","Ditt krav",true,["Avhjälpande","Omleverans","Prisavdrag","Hävning av köpet"])]},
    se_flygersattning:{ic:"✈️",n:"Flygersättning (EU 261/2004)",cat:"se_konsument",tier:"standard",q:[Pq("flygbolag","Flygbolag",true),Pq("flyg","Flightnummer och datum",true),Pq("problem","Problem",true,["Försening","Inställt flyg","Nekad ombordstigning","Bagage"]),Pq("krav","Ditt krav (ersättning enligt EU 261/2004…)",true),Pq("uppgifter","Ditt namn",true)]},
    se_kravbrev:{ic:"📨",n:"Betalningskrav (kravbrev)",cat:"se_konsument",tier:"standard",q:[Pq("galdenar","Gäldenär (den som är skyldig): namn och adress",true),Pq("grund","Grund för kravet (faktura, lån, avtal…)",true),Pq("belopp","Belopp (kr)",true),Pq("frist","Betalningsfrist (datum)",true),Pq("konto","Konto för betalning",false),Pq("uppgifter","Ditt namn och adress",true)]},
    se_bestrida_faktura:{ic:"🧾",n:"Bestridande av faktura",cat:"se_konsument",tier:"standard",q:[Pq("avsandare","Fakturautställare: namn och adress",true),Pq("fakturanummer","Fakturanummer och fakturadatum",true),Pq("belopp","Belopp (kr)",true),Pq("skal","Skäl för bestridandet",true),Pq("uppgifter","Ditt namn och adress",true)]},
    se_arn:{ic:"🏛️",n:"Anmälan till ARN",cat:"se_konsument",tier:"standard",q:[Pq("foretag","Företag: namn och org.nr",true),Pq("handelse","Vad som har hänt (datum, händelseförlopp)",true),Pq("krav","Ditt krav",true,["Återbetalning","Reparation","Omleverans","Ersättning"]),Pq("belopp","Belopp (kr, valfritt)",false),Pq("uppgifter","Ditt namn och adress",true)]},
    se_inkasso:{ic:"🚫",n:"Bestridande av inkassokrav",cat:"se_konsument",tier:"standard",q:[Pq("inkassobolag","Inkassobolag: namn och adress",true),Pq("arendenummer","Ärendenummer",true),Pq("belopp","Krävt belopp (kr)",true),Pq("skal","Skäl för bestridandet",true),Pq("uppgifter","Ditt namn och personnummer",true)]},

    /* Avtal */
    se_skuldebrev:{ic:"📜",n:"Skuldebrev",cat:"se_avtal",tier:"standard",q:[Pq("borgenar","Borgenär: namn och personnummer",true),Pq("galdenar","Gäldenär: namn och personnummer",true),Pq("belopp","Belopp (kr, i siffror och bokstäver)",true),Pq("aterbetalning","Återbetalningsplan",true),Pq("ranta","Ränta (%, valfritt)",false)]},
    se_kopeavtal:{ic:"🛒",n:"Köpeavtal mellan privatpersoner",cat:"se_avtal",tier:"einfach",q:[Pq("roll","Du är",true,["Säljare","Köpare"]),Pq("motpart","Motpart: namn och personnummer",true),Pq("objekt","Vad som säljs",true),Pq("pris","Pris (kr)",true),Pq("leverans","Leveransdatum",false)]},
    se_laneavtal:{ic:"🤝",n:"Låneavtal mellan privatpersoner",cat:"se_avtal",tier:"standard",q:[Pq("langivare","Långivare: namn och personnummer",true),Pq("lantagare","Låntagare: namn och personnummer",true),Pq("belopp","Lånebelopp (kr)",true),Pq("aterbetalning","Återbetalningsplan",true),Pq("ranta","Ränta (%, valfritt)",false)]},
    se_fullmakt:{ic:"📝",n:"Fullmakt",cat:"se_avtal",tier:"einfach",q:[Pq("fullmaktshavare","Fullmaktshavare: namn och personnummer",true),Pq("omfattning","Vad fullmakten omfattar",true),Pq("giltighet","Giltighetstid (valfritt)",false),Pq("uppgifter","Ditt (fullmaktsgivarens) namn och personnummer",true)]},
    se_nda:{ic:"🔒",n:"Sekretessavtal (NDA)",cat:"se_avtal",tier:"standard",q:[Pq("motpart","Motpart: namn och adress",true),Pq("syfte","Syfte med samarbetet",true),Pq("omfattning","Vilken information som omfattas",true),Pq("varaktighet","Varaktighet (valfritt)",false)]},
    se_uppdragsavtal:{ic:"🧰",n:"Uppdragsavtal",cat:"se_avtal",tier:"komplex",q:[Pq("roll","Du är",true,["Uppdragstagare","Uppdragsgivare"]),Pq("motpart","Motpart: namn och org.nr/personnummer",true),Pq("uppdrag","Beskrivning av uppdraget",true),Pq("ersattning","Ersättning och betalningsvillkor (kr)",true),Pq("tid","Tidsplan/varaktighet",false),Pq("villkor","Särskilda villkor (valfritt)",false)]},

    /* Myndigheter */
    se_parkeringsanmarkning:{ic:"🅿️",n:"Bestridande av parkeringsanmärkning",cat:"se_myndigheter",tier:"standard",q:[Pq("arendenummer","Ärendenummer (OCR)",true),Pq("datum","Datum för anmärkningen",true),Pq("regnummer","Registreringsnummer",true),Pq("skal","Skäl för bestridandet",true),Pq("uppgifter","Ditt namn och personnummer",true)]},
    se_pbot:{ic:"🚗",n:"Överklagande av kontrollavgift (privat p-bot)",cat:"se_myndigheter",tier:"standard",q:[Pq("bolag","Parkeringsbolag: namn och adress",true),Pq("arendenummer","Ärende-/fakturanummer",true),Pq("datum","Datum och plats",true),Pq("regnummer","Registreringsnummer",true),Pq("skal","Skäl för överklagandet",true),Pq("uppgifter","Ditt namn och adress",true)]},
    se_skatteverket:{ic:"🧾",n:"Skrivelse till Skatteverket (anstånd/omprövning)",cat:"se_myndigheter",tier:"komplex",q:[Pq("arende","Ärende",true,["Anstånd med betalning","Omprövning av beslut","Övrigt"]),Pq("referens","Diarie-/ärendenummer (om känt)",false),Pq("beskrivning","Beskrivning av ärendet",true),Pq("yrkande","Vad du begär",true),Pq("uppgifter","Ditt namn och personnummer",true)]},
    se_forsakringskassan:{ic:"🏥",n:"Skrivelse till Försäkringskassan",cat:"se_myndigheter",tier:"standard",q:[Pq("arende","Ärende (sjukpenning, föräldrapenning, bostadsbidrag…)",true),Pq("referens","Ärendenummer (om känt)",false),Pq("beskrivning","Beskrivning av situationen",true),Pq("begaran","Din begäran",true),Pq("uppgifter","Ditt namn och personnummer",true)]},
    se_flyttanmalan:{ic:"📦",n:"Flyttanmälan",cat:"se_myndigheter",tier:"einfach",q:[Pq("ny_adress","Ny adress",true),Pq("flyttdatum","Flyttdatum",true),Pq("medflyttande","Medflyttande personer (valfritt)",false),Pq("uppgifter","Ditt namn och personnummer",true)]},
    se_belastningsregister:{ic:"📋",n:"Begäran om utdrag ur belastningsregistret",cat:"se_myndigheter",tier:"einfach",q:[Pq("andamal","Ändamål",true,["Eget bruk","Arbete med barn (skola/förskola)","Annan anställning","Övrigt"]),Pq("uppgifter","Ditt namn, personnummer och adress",true)]}
  };

  function inject(){
    try{
      if(typeof DOCS==="undefined"||!DOCS) return false;
      for(var k in D){ if(!DOCS[k]) DOCS[k]=D[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=D[k].tier||"standard"; }
      if(typeof CATS!=="undefined"&&CATS){
        for(var ck in XC){ if(!CATS[ck]) CATS[ck]={n:XC[ck].n,ic:XC[ck].ic,docs:[],country:CCODE}; if(!CATS[ck].docs) CATS[ck].docs=[]; }
        for(var k2 in D){ var c2=D[k2].cat; if(CATS[c2]&&CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); }
      }
      if(typeof CAT_LABELS!=="undefined"){ for(var cl in XC) CAT_LABELS[cl]=XC[cl].n; }
      if(typeof CAT_DOCS!=="undefined"){
        for(var cd in XC){ if(!CAT_DOCS[cd]) CAT_DOCS[cd]=[]; }
        for(var k3 in D){ var c3=D[k3].cat; if(CAT_DOCS[c3]){ var ex=false; for(var i=0;i<CAT_DOCS[c3].length;i++){ if(CAT_DOCS[c3][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[c3].push({k:k3,ic:D[k3].ic,t:D[k3].n,tier:D[k3].tier||"standard"}); } }
      }
      return (typeof DOCS!=="undefined" && DOCS.se_kravbrev)?true:false;
    }catch(e){ return false; }
  }

  /* Ülke filtresi (CH_TR_MODULE kuruyor; yoksa aynı korumalı sarmalayıcı burada da kurulur) */
  function installMultiCountryFilter(){
    try{
      if(typeof showAllCats!=="function" || showAllCats.__multiCountry) return;
      var _sac=showAllCats;
      var w=function(){
        try{
          if(typeof CATS==="undefined") return _sac.apply(this,arguments);
          var cur=(typeof CC!=="undefined"?CC:"DE");
          var hid={};
          for(var k in CATS){
            var cc=(CATS[k]&&CATS[k].country)||"DE";
            if(cc!==cur){ hid[k]=CATS[k]; delete CATS[k]; }
          }
          try{ return _sac.apply(this,arguments); } finally { for(var h in hid) CATS[h]=hid[h]; }
        }catch(e){ return _sac.apply(this,arguments); }
      };
      w.__multiCountry=1; showAllCats=w; if(typeof window!=="undefined") window.showAllCats=w;
    }catch(e){}
  }

  /* Welcome grid: bu ülkenin kartları (data-cccountry) + görünürlük */
  function xGrid(){
    try{
      var grid=document.querySelector(".wcat-grid"); if(!grid) return;
      var cur=(typeof CC!=="undefined"?CC:"DE");
      if(cur===CCODE){
        X_ORDER.forEach(function(ck){
          if(grid.querySelector('[data-cccountry="'+CCODE+'"][data-ccat="'+ck+'"]')) return;
          var c=XC[ck]; var el=document.createElement("div"); el.className="wcat";
          el.setAttribute("data-cccountry",CCODE); el.setAttribute("data-ccat",ck);
          el.onclick=function(){ try{ if(typeof showCat==="function") showCat(ck); }catch(e){} };
          var n=(typeof CATS!=="undefined"&&CATS[ck]&&CATS[ck].docs)?CATS[ck].docs.length:0;
          el.innerHTML='<div class="wcat-ic">'+c.ic+'</div><div class="wcat-t">'+c.n+'</div><div class="wcat-s">'+n+' '+DOCWORD+'</div>';
          grid.appendChild(el);
        });
      }
      grid.querySelectorAll(".wcat").forEach(function(el){
        var cc=el.getAttribute("data-cccountry")||(el.getAttribute("data-frcat")?"FR":(el.getAttribute("data-trcat")?"TR":"DE"));
        el.style.display=(cc===cur)?"":"none";
      });
    }catch(e){}
  }

  function installCountryHook(){
    try{
      if(typeof setCountry!=="function" || setCountry["__m"+CCODE]) return;
      var _sc=setCountry;
      var s=function(cc){ var r; try{ r=_sc.apply(this,arguments); }catch(e){} try{ xGrid(); }catch(e){} return r; };
      s["__m"+CCODE]=1; setCountry=s; if(typeof window!=="undefined") window.setCountry=s;
    }catch(e){}
  }

  var done=false, ticks=0;
  function run(){ if(!done){ if(inject()) done=true; } installMultiCountryFilter(); installCountryHook(); try{ xGrid(); }catch(e){} if(ticks++<40) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}
CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
if($src!==$start){ file_put_contents($file,$src); echo "Isvec modulu eklendi (CH_SE_MODULE) - ~36 belge, 6 kategori, Isvecce.\n"; }
else echo "degisiklik yok\n";
echo "\nNOT: Belge dili/hukuku icin apply-lawsys-all.php de calistirilmali (lawSystems['SE'] + doc-lang).\n";
echo "SONRA: opcache-reset (pull-updates otomatik yapar). SIL: rm apply-sverige.php\n";
echo "Test: Ayarlar -> Sverige sec -> kategoriler Isvecce -> 'Betalningskrav (kravbrev)' uret.\n";
