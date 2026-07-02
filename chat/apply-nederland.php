<?php
/**
 * ChatHelp — HOLLANDA MODÜLÜ (~38 belge, 6 kategori, Felemenkçe) — CH_TR_MODULE ile aynı kanıtlanmış desen
 * -------------------------------------------------------------------------------------------------------
 *  • Kategoriler country:'NL' etiketli -> genelleştirilmiş ülke filtresi (CH_TR_MODULE) otomatik çalışır
 *  • Welcome grid kartları data-cccountry='NL' -> kart görünürlüğü otomatik
 *  • Dil: setCountry('NL') zaten UL='nl' yapıyor (CC_DATA.NL.l) — ek dil kodu gerekmez
 *  • Belge dili/hukuku: api.php'de lawSystems['NL'] + doc-lang NL->nl (apply-lawsys-all.php ile)
 *
 * KULLANIM: pull-updates.php?files=apply-nederland.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-nederland BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,"CH_NL_MODULE")!==false) exit("Zaten ekli (CH_NL_MODULE).\n");
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-nl-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_NL_MODULE — Hollanda hukuku modülü (deferred; DOCS/CATS hazır olunca yüklenir) */
try{(function(){
  function Pq(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var XC={
    nl_opzeggingen:{n:"Opzeggingen",ic:"📄"},
    nl_wonen:{n:"Wonen & Huur",ic:"🏠"},
    nl_werk:{n:"Werk & Inkomen",ic:"💼"},
    nl_consument:{n:"Consument & Geschillen",ic:"🛒"},
    nl_contracten:{n:"Contracten",ic:"📝"},
    nl_overheid:{n:"Overheid & Instanties",ic:"🏛️"}
  };
  var X_ORDER=["nl_opzeggingen","nl_wonen","nl_werk","nl_consument","nl_contracten","nl_overheid"];
  var CCODE="NL", DOCWORD="documenten";

  var D={
    /* Opzeggingen */
    nl_opzegging_energie:{ic:"⚡",n:"Opzegging energiecontract",cat:"nl_opzeggingen",tier:"einfach",q:[Pq("leverancier","Energieleverancier: naam en adres",true),Pq("klantnummer","Klantnummer / contractnummer",true),Pq("adres","Adres van de aansluiting",true),Pq("datum","Gewenste einddatum",true),Pq("gegevens","Uw naam en adres",true)]},
    nl_opzegging_telecom:{ic:"📱",n:"Opzegging telefoon / internet",cat:"nl_opzeggingen",tier:"einfach",q:[Pq("aanbieder","Aanbieder: naam",true),Pq("klantnummer","Klantnummer / telefoonnummer",true),Pq("looptijd","Zit u nog in een vaste contractperiode?",false,["Nee","Ja","Weet ik niet"]),Pq("datum","Gewenste einddatum",false),Pq("gegevens","Uw naam en adres",true)]},
    nl_opzegging_sportschool:{ic:"🏋️",n:"Opzegging sportschool / vereniging",cat:"nl_opzeggingen",tier:"einfach",q:[Pq("club","Sportschool/vereniging: naam en adres",true),Pq("lidnummer","Lidmaatschapsnummer",false),Pq("datum","Gewenste einddatum (opzegtermijn 1 maand)",true),Pq("reden","Reden (optioneel)",false),Pq("gegevens","Uw naam",true)]},
    nl_opzegging_verzekering:{ic:"🛡️",n:"Opzegging verzekering",cat:"nl_opzeggingen",tier:"einfach",q:[Pq("verzekeraar","Verzekeraar: naam en adres",true),Pq("polisnummer","Polisnummer",true),Pq("type","Soort verzekering",true,["Auto","Inboedel","Zorg","Aansprakelijkheid","Overig"]),Pq("datum","Gewenste einddatum (let op opzegtermijn)",true),Pq("gegevens","Uw naam en geboortedatum",true)]},
    nl_opheffen_bankrekening:{ic:"🏦",n:"Opheffen bankrekening",cat:"nl_opzeggingen",tier:"einfach",q:[Pq("bank","Bank: naam en vestiging",true),Pq("iban","IBAN van de rekening",true),Pq("overboeken","IBAN voor het restsaldo (optioneel)",false),Pq("gegevens","Uw naam en adres",true)]},
    nl_opzegging_streaming:{ic:"📺",n:"Opzegging streamingdienst / abonnement",cat:"nl_opzeggingen",tier:"einfach",q:[Pq("dienst","Dienst (Netflix, Spotify…)",true),Pq("account","E-mailadres van het account",true),Pq("datum","Gewenste einddatum (optioneel)",false),Pq("gegevens","Uw naam",true)]},

    /* Wonen & Huur */
    nl_huur_opzeggen:{ic:"🏠",n:"Huur opzeggen (huurder)",cat:"nl_wonen",tier:"einfach",q:[Pq("verhuurder","Verhuurder/beheerder: naam en adres",true),Pq("adres","Adres van de woning",true),Pq("einddatum","Gewenste einddatum (opzegtermijn 1 maand)",true),Pq("gegevens","Uw naam",true)]},
    nl_borg_terugvragen:{ic:"💶",n:"Borg (waarborgsom) terugvragen",cat:"nl_wonen",tier:"standard",q:[Pq("verhuurder","Verhuurder: naam en adres",true),Pq("adres","Adres van de woning",true),Pq("oplevering","Datum sleuteloverdracht/oplevering",true),Pq("bedrag","Bedrag van de borg (€)",true),Pq("iban","IBAN voor terugbetaling",false),Pq("gegevens","Uw naam",true)]},
    nl_huurcontract:{ic:"📄",n:"Huurovereenkomst woonruimte",cat:"nl_wonen",tier:"komplex",q:[Pq("rol","U bent",true,["Verhuurder","Huurder"]),Pq("wederpartij","Wederpartij: naam en adres",true),Pq("adres","Adres van de woning",true),Pq("oppervlakte","Oppervlakte (m²) en aantal kamers",false),Pq("ingangsdatum","Ingangsdatum",true),Pq("huurprijs","Kale huurprijs per maand (€)",true),Pq("borg","Waarborgsom (€, optioneel)",false),Pq("servicekosten","Servicekosten / bijkomende kosten (optioneel)",false)]},
    nl_gebreken_melden:{ic:"🔧",n:"Gebreken melden aan verhuurder",cat:"nl_wonen",tier:"standard",q:[Pq("verhuurder","Verhuurder: naam en adres",true),Pq("adres","Adres van de woning",true),Pq("probleem","Omschrijving van het gebrek",true),Pq("urgentie","Is het spoedeisend?",false,["Ja","Nee"]),Pq("gegevens","Uw naam",true)]},
    nl_bezwaar_huurverhoging:{ic:"📈",n:"Bezwaar tegen huurverhoging (Huurcommissie)",cat:"nl_wonen",tier:"standard",q:[Pq("verhuurder","Verhuurder: naam en adres",true),Pq("adres","Adres van de woning",true),Pq("huur_huidig","Huidige huurprijs (€)",true),Pq("huur_nieuw","Voorgestelde huurprijs (€)",true),Pq("motivering","Gronden van uw bezwaar",true)]},
    nl_klacht_vve_buren:{ic:"🏢",n:"Klacht aan VvE / buren (overlast)",cat:"nl_wonen",tier:"standard",q:[Pq("ontvanger","Geadresseerde (buren/VvE/beheerder)",true),Pq("probleem","Omschrijving van de overlast",true),Pq("tijden","Frequentie en tijdstippen",false),Pq("verzoek","Uw verzoek",true),Pq("gegevens","Uw naam en huisnummer",true)]},

    /* Werk & Inkomen */
    nl_ontslagbrief:{ic:"👋",n:"Ontslagbrief (werknemer)",cat:"nl_werk",tier:"einfach",q:[Pq("werkgever","Werkgever: naam en adres",true),Pq("functie","Uw functie",true),Pq("laatste_dag","Laatste werkdag (opzegtermijn 1 maand)",true),Pq("gegevens","Uw naam en adres",true)]},
    nl_loonvordering:{ic:"🧾",n:"Loonvordering (achterstallig loon)",cat:"nl_werk",tier:"standard",q:[Pq("werkgever","Werkgever: naam en adres",true),Pq("functie","Uw functie en dienstverband (data)",true),Pq("vordering","Wat vordert u (loon, vakantiegeld, uren)",true),Pq("bedrag","Geschat bedrag (€)",false),Pq("gegevens","Uw naam",true)]},
    nl_verzoek_thuiswerken:{ic:"🏡",n:"Verzoek thuiswerken (Wet flexibel werken)",cat:"nl_werk",tier:"einfach",q:[Pq("werkgever","Werkgever/leidinggevende",true),Pq("functie","Uw functie",true),Pq("regeling","Gewenste regeling (dagen/week)",true),Pq("reden","Reden (optioneel)",false)]},
    nl_vakantieaanvraag:{ic:"🏖️",n:"Vakantieaanvraag",cat:"nl_werk",tier:"einfach",q:[Pq("werkgever","Werkgever/leidinggevende",true),Pq("vanaf","Vanaf (datum)",true),Pq("tot","Tot en met (datum)",true),Pq("gegevens","Uw naam en functie",true)]},
    nl_bezwaar_ontslag:{ic:"⚖️",n:"Bezwaar tegen ontslag",cat:"nl_werk",tier:"komplex",q:[Pq("werkgever","Werkgever: naam en adres",true),Pq("dienstverband","Uw functie en duur van het dienstverband",true),Pq("ontslag","Ontslag (datum, wijze, opgegeven reden)",true),Pq("gronden","Waarom is het ontslag onterecht?",true),Pq("verzoek","Uw verzoek (herstel, vergoeding €…)",true),Pq("gegevens","Uw naam en adres",true)]},
    nl_getuigschrift:{ic:"📜",n:"Getuigschrift opvragen",cat:"nl_werk",tier:"einfach",q:[Pq("werkgever","Werkgever/HR: naam en adres",true),Pq("functie","Uw functie en periode van het dienstverband",true),Pq("inhoud","Gewenste inhoud (taken, functioneren)",false),Pq("gegevens","Uw naam",true)]},
    nl_klacht_werkgever:{ic:"🚨",n:"Klacht werkgever (arbeidsomstandigheden)",cat:"nl_werk",tier:"standard",q:[Pq("ontvanger","Geadresseerde (werkgever/HR/vertrouwenspersoon)",true),Pq("feiten","Feiten (data, personen, omschrijving)",true),Pq("getuigen","Getuigen (optioneel)",false),Pq("verzoek","Uw verzoek",true),Pq("gegevens","Uw naam en functie",true)]},

    /* Consument & Geschillen */
    nl_herroeping_koop:{ic:"🔄",n:"Herroeping koop op afstand (14 dagen)",cat:"nl_consument",tier:"einfach",q:[Pq("verkoper","Verkoper: naam en adres",true),Pq("bestelnummer","Bestel-/ordernummer",true),Pq("datum","Datum van bestelling/ontvangst",true),Pq("producten","Producten waarop de herroeping ziet",true),Pq("iban","IBAN voor terugbetaling (optioneel)",false)]},
    nl_klacht_product:{ic:"🔧",n:"Klachtenbrief ondeugdelijk product (non-conformiteit)",cat:"nl_consument",tier:"standard",q:[Pq("verkoper","Verkoper: naam en adres",true),Pq("product","Product (merk/model)",true),Pq("aankoopdatum","Aankoopdatum",true),Pq("gebrek","Geconstateerd gebrek",true),Pq("verzoek","Uw verzoek",true,["Reparatie","Vervanging","Prijsvermindering","Geld terug"])]},
    nl_claim_vliegvertraging:{ic:"✈️",n:"Claim vliegvertraging (EU 261/2004)",cat:"nl_consument",tier:"standard",q:[Pq("maatschappij","Luchtvaartmaatschappij",true),Pq("vlucht","Vluchtnummer en datum",true),Pq("probleem","Probleem",true,["Vertraging","Annulering","Overboeking","Bagage"]),Pq("verzoek","Uw verzoek (compensatie €, kosten…)",true),Pq("gegevens","Uw naam",true)]},
    nl_chargeback:{ic:"💳",n:"Storneren / chargeback verzoek",cat:"nl_consument",tier:"standard",q:[Pq("bank","Bank/kaartuitgever: naam",true),Pq("transactie","Transactie (datum, bedrag €, begunstigde)",true),Pq("reden","Reden (niet geleverd, onterecht afgeschreven…)",true),Pq("gegevens","Uw naam en IBAN/kaartnummer (laatste 4 cijfers)",true)]},
    nl_klacht_telecom:{ic:"📶",n:"Klacht telecomaanbieder",cat:"nl_consument",tier:"standard",q:[Pq("aanbieder","Aanbieder",true),Pq("klantnummer","Klantnummer",true),Pq("probleem","Probleem (facturering, storing, contract)",true),Pq("verzoek","Uw verzoek",true),Pq("gegevens","Uw naam en adres",true)]},
    nl_ingebrekestelling:{ic:"📨",n:"Ingebrekestelling (laatste aanmaning)",cat:"nl_consument",tier:"standard",q:[Pq("wederpartij","Wederpartij: naam en adres",true),Pq("verplichting","Welke verplichting is niet nagekomen?",true),Pq("termijn","Redelijke termijn voor nakoming (bijv. 14 dagen)",true),Pq("gevolg","Gevolg bij uitblijven (ontbinding, schadevergoeding €)",false),Pq("gegevens","Uw naam en adres",true)]},

    /* Contracten */
    nl_schuldbekentenis:{ic:"💶",n:"Schuldbekentenis",cat:"nl_contracten",tier:"standard",q:[Pq("schuldeiser","Schuldeiser: naam en adres",true),Pq("schuldenaar","Schuldenaar: naam en adres",true),Pq("bedrag","Bedrag (€, in cijfers en letters)",true),Pq("terugbetaling","Termijn/wijze van terugbetaling",true),Pq("rente","Met rente?",false,["Zonder rente","Met rente"])]},
    nl_koopovereenkomst:{ic:"🛒",n:"Koopovereenkomst tussen particulieren",cat:"nl_contracten",tier:"einfach",q:[Pq("rol","U bent",true,["Verkoper","Koper"]),Pq("wederpartij","Wederpartij: naam en adres",true),Pq("object","Wat wordt verkocht",true),Pq("prijs","Prijs (€)",true),Pq("levering","Datum van levering",false)]},
    nl_leenovereenkomst:{ic:"🤝",n:"Leenovereenkomst tussen particulieren",cat:"nl_contracten",tier:"standard",q:[Pq("uitlener","Uitlener: naam en adres",true),Pq("lener","Lener: naam en adres",true),Pq("bedrag","Geleend bedrag (€)",true),Pq("terugbetaling","Terugbetalingsplan",true),Pq("rente","Rente (%, optioneel)",false)]},
    nl_volmacht:{ic:"📝",n:"Volmacht / machtiging",cat:"nl_contracten",tier:"einfach",q:[Pq("gemachtigde","Gemachtigde: naam en geboortedatum",true),Pq("object","Waarvoor wordt gemachtigd",true),Pq("geldigheid","Geldigheidsduur (optioneel)",false),Pq("gegevens","Uw (volmachtgever) naam en geboortedatum",true)]},
    nl_nda:{ic:"🔒",n:"Geheimhoudingsverklaring (NDA)",cat:"nl_contracten",tier:"standard",q:[Pq("wederpartij","Wederpartij: naam en adres",true),Pq("object","Doel van de samenwerking",true),Pq("omvang","Omvang van de vertrouwelijke informatie",true),Pq("duur","Duur (optioneel)",false)]},
    nl_opdrachtovereenkomst:{ic:"🧰",n:"Opdrachtovereenkomst (overeenkomst van opdracht)",cat:"nl_contracten",tier:"komplex",q:[Pq("rol","U bent",true,["Opdrachtnemer","Opdrachtgever"]),Pq("wederpartij","Wederpartij: naam en KvK-nummer",true),Pq("opdracht","Omschrijving van de opdracht",true),Pq("vergoeding","Vergoeding en betalingswijze (€)",true),Pq("looptijd","Looptijd/planning",false),Pq("clausules","Bijzondere bepalingen (optioneel)",false)]},

    /* Overheid & Instanties */
    nl_bezwaarschrift_gemeente:{ic:"🏛️",n:"Bezwaarschrift gemeente (o.a. parkeerboete)",cat:"nl_overheid",tier:"standard",q:[Pq("gemeente","Gemeente en afdeling",true),Pq("kenmerk","Kenmerk/zaaknummer van het besluit",true),Pq("besluit","Besluit waartegen u bezwaar maakt (datum)",true),Pq("gronden","Gronden van uw bezwaar",true),Pq("gegevens","Uw naam, adres en geboortedatum",true)]},
    nl_bezwaar_belastingdienst:{ic:"🧾",n:"Bezwaar Belastingdienst",cat:"nl_overheid",tier:"komplex",q:[Pq("kantoor","Belastingkantoor",true),Pq("kenmerk","Aanslagnummer/kenmerk",true),Pq("aanslag","Aanslag/beschikking waartegen u bezwaar maakt",true),Pq("gronden","Gronden van uw bezwaar",true),Pq("gegevens","Uw naam en BSN",true)]},
    nl_betalingsregeling:{ic:"📅",n:"Betalingsregeling Belastingdienst",cat:"nl_overheid",tier:"standard",q:[Pq("kantoor","Belastingkantoor",true),Pq("schuld","Schuld (soort belasting en bedrag €)",true),Pq("situatie","Uw financiële situatie",true),Pq("voorstel","Voorgesteld betalingsplan",false),Pq("gegevens","Uw naam en BSN",true)]},
    nl_beroep_verkeersboete:{ic:"🚗",n:"Beroep verkeersboete (CJIB / officier van justitie)",cat:"nl_overheid",tier:"standard",q:[Pq("beschikking","CJIB-/beschikkingsnummer",true),Pq("datum","Datum van de gedraging",true),Pq("kenteken","Kenteken",false),Pq("gronden","Gronden van uw beroep",true),Pq("gegevens","Uw naam, adres en geboortedatum",true)]},
    nl_brief_uwv:{ic:"💼",n:"Brief aan het UWV (uitkering)",cat:"nl_overheid",tier:"standard",q:[Pq("object","Onderwerp",true,["Aanvraag uitkering","Bezwaar tegen beslissing","Informatie/vraag"]),Pq("situatie","Uw situatie (laatste werkgever, datum einde dienstverband)",true),Pq("kenmerk","Kenmerk/zaaknummer (optioneel)",false),Pq("gegevens","Uw naam, adres en BSN",true)]},
    nl_uittreksel_brp:{ic:"📊",n:"Uittreksel BRP aanvragen",cat:"nl_overheid",tier:"einfach",q:[Pq("gemeente","Gemeente",true),Pq("doel","Waarvoor heeft u het uittreksel nodig?",true),Pq("gegevens","Uw naam, adres en geboortedatum",true)]},
    nl_verhuizing_doorgeven:{ic:"🚚",n:"Verhuizing doorgeven aan gemeente",cat:"nl_overheid",tier:"einfach",q:[Pq("gemeente","Nieuwe gemeente",true),Pq("oud_adres","Oud adres",true),Pq("nieuw_adres","Nieuw adres",true),Pq("datum","Verhuisdatum",true),Pq("meeverhuizers","Wie verhuizen er mee? (optioneel)",false),Pq("gegevens","Uw naam en geboortedatum",true)]}
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
      return (typeof DOCS!=="undefined" && DOCS.nl_ingebrekestelling)?true:false;
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
if($src!==$start){ file_put_contents($file,$src); echo "Hollanda modulu eklendi (CH_NL_MODULE) - ~38 belge, 6 kategori, Felemenkce.\n"; }
else echo "degisiklik yok\n";
echo "\nNOT: Belge dili/hukuku icin apply-lawsys-all.php de calistirilmali (lawSystems['NL'] + doc-lang).\n";
echo "SONRA: opcache-reset (pull-updates otomatik yapar). SIL: rm apply-nederland.php\n";
echo "Test: Ayarlar -> Nederland sec -> kategoriler Felemenkce -> 'Ingebrekestelling' uret.\n";
