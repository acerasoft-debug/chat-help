<?php
/** ChatHelp — apply-nl-expand-more (CH_NL_EXPAND_MORE) — NL katalog genisletme
 *  Workflow ile uretildi: +17 belge, 6 kategori. Deferred injection.
 *  KULLANIM: pull-updates.php?files=apply-nl-expand-more.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-nl-expand-more BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_NL_EXPAND_MORE')!==false) exit("Zaten ekli (CH_NL_EXPAND_MORE).\n");
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if (strpos($src,$anchor)===false) exit("HATA: anchor (getDocPrice) yok.\n");
$js = <<<'CHJS'

/* CH_NL_EXPAND_MORE — NL katalog genisletme (workflow, deferred) */
try{(function(){
  function Pe(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var NEWCATS={
    "nl_relatie":{n:"Relatie & Gezin",ic:"💑"},
    "nl_erfrecht":{n:"Erfenis & Nalatenschap",ic:"⚱️"},
    "nl_schulden":{n:"Schulden & Incasso",ic:"💳"},
    "nl_privacy":{n:"Privacy & AVG",ic:"🔒"},
    "nl_wonen":{n:"Wonen & Huur",ic:"🏠"},
    "nl_werk":{n:"Werk & Inkomen",ic:"💼"}
  };
  var E={
    "nlx_samenlevingscontract":{ic:"🤝",n:"Samenlevingscontract (concept)",cat:"nl_relatie",tier:"standard",q:[Pe("partners","Beide partners: volledige namen en geboortedata",true),Pe("adres","Gezamenlijk adres",true),Pe("bezittingen","Regeling van de gezamenlijke bezittingen",true,["Alles gemeenschappelijk","Alleen wat samen is aangeschaft","Ieder houdt eigen bezit"]),Pe("kosten","Verdeling van de kosten van de huishouding",true,["Naar rato van inkomen","Ieder de helft","Andere afspraak"]),Pe("pensioen","Elkaar aanwijzen als partner voor het partnerpensioen?",false,["Ja","Nee"]),Pe("note","Let op: voor bepaalde afspraken (zoals partnerpensioen en een verblijvingsbeding) is vaak een notarieel samenlevingscontract nodig of aan te raden",false)]},
    "nlx_ouderschapsplan":{ic:"👨‍👩‍👧",n:"Ouderschapsplan (bij scheiding, art. 815 Rv)",cat:"nl_relatie",tier:"komplex",q:[Pe("ouders","Beide ouders: namen en adressen",true),Pe("kinderen","Kinderen: namen en geboortedata",true),Pe("hoofdverblijf","Hoofdverblijfplaats van de kinderen",true,["Bij ouder 1","Bij ouder 2","Co-ouderschap (om en om)"]),Pe("zorgverdeling","Verdeling van zorg- en opvoedtaken en omgangsregeling",true),Pe("kinderalimentatie","Afspraak over kinderalimentatie (€ per maand per kind)",false),Pe("informatie","Afspraken over overleg en informatie-uitwisseling over de kinderen",false)]},
    "nlx_echtscheiding_gemeenschappelijk":{ic:"💔",n:"Gemeenschappelijk verzoek echtscheiding (opzet, art. 1:150 BW)",cat:"nl_relatie",tier:"komplex",q:[Pe("echtgenoten","Beide echtgenoten: namen en geboortedata",true),Pe("huwelijk","Datum en plaats van het huwelijk",true),Pe("kinderen","Minderjarige kinderen (namen/leeftijd, anders 'geen')",true),Pe("voorwaarden","Zijn er huwelijkse voorwaarden?",true,["Ja, huwelijkse voorwaarden","Nee, (wettelijke) gemeenschap van goederen"]),Pe("afspraken","Gemaakte afspraken (verdeling, partneralimentatie, woning)",true),Pe("note","Let op: het verzoek moet via een advocaat bij de rechtbank worden ingediend; bij minderjarige kinderen is een ouderschapsplan verplicht",false)]},
    "nlx_erkenning_kind":{ic:"👶",n:"Erkenning van een kind (verklaring, art. 1:203 BW)",cat:"nl_relatie",tier:"standard",q:[Pe("kind","Kind: naam en geboortedatum (of verwachte datum)",true),Pe("moeder","Moeder: naam en geboortedatum",true),Pe("erkenner","Erkenner: naam, geboortedatum en adres",true),Pe("toestemming","Is er schriftelijke toestemming van de moeder (en van het kind vanaf 12 jaar)?",true,["Ja","Nog te regelen"]),Pe("achternaam","Gewenste keuze achternaam van het kind",false,["Achternaam moeder","Achternaam erkenner","Nog te bepalen"])]},
    "nlx_verwerping_nalatenschap":{ic:"✍️",n:"Verwerping van een nalatenschap (art. 4:190 BW)",cat:"nl_erfrecht",tier:"standard",q:[Pe("overledene","Overledene: naam en overlijdensdatum",true),Pe("relatie","Uw relatie tot de overledene",true),Pe("reden","Reden van verwerping",false,["Nalatenschap is negatief (schulden)","Persoonlijke reden","Anders"]),Pe("rechtbank","Rechtbank (arrondissement van de laatste woonplaats van de overledene)",true),Pe("gegevens","Uw gegevens: naam, adres en BSN",true),Pe("note","Let op: verwerpen gebeurt via een verklaring bij de griffie van de rechtbank; wees terughoudend met het beheren van de nalatenschap",false)]},
    "nlx_codicil":{ic:"📜",n:"Codicil (concept inboedel, art. 4:97 BW)",cat:"nl_erfrecht",tier:"einfach",q:[Pe("goederen","Welke inboedelgoederen, sieraden of kleding wilt u nalaten?",true),Pe("begunstigden","Aan wie komt elk goed toe (naam per goed)?",true),Pe("gegevens","Uw gegevens: naam en geboortedatum",true),Pe("note","Let op: een codicil is alleen geldig als het volledig met de hand is geschreven, gedateerd en ondertekend; het geldt uitsluitend voor inboedel, kleding en sieraden (geen geld of onroerend goed)",false)]},
    "nlx_verklaring_erfrecht_opvragen":{ic:"🏦",n:"Verklaring van erfrecht opvragen (notaris)",cat:"nl_erfrecht",tier:"einfach",q:[Pe("overledene","Overledene: naam en overlijdensdatum",true),Pe("erfgenamen","Bekende erfgenamen",true),Pe("doel","Waarvoor heeft u de verklaring nodig?",false,["Bankrekening deblokkeren","Verkoop/overdracht woning","Uitkering verzekering","Anders"]),Pe("notaris","Notaris/notariskantoor (indien bekend)",false),Pe("gegevens","Uw gegevens: naam en adres",true)]},
    "nlx_betalingsregeling_voorstel":{ic:"📅",n:"Voorstel betalingsregeling aan schuldeiser",cat:"nl_schulden",tier:"einfach",q:[Pe("schuldeiser","Schuldeiser: naam en adres",true),Pe("vordering","Openstaande vordering (factuurnummer en bedrag)",true),Pe("voorstel","Voorgesteld termijnbedrag (€ per maand)",true),Pe("reden","Reden van de betalingsproblemen (kort)",true),Pe("gegevens","Uw gegevens: naam en adres",true)]},
    "nlx_verjaring_inroepen":{ic:"⏳",n:"Verjaring van een schuld inroepen (art. 3:307/3:308 BW)",cat:"nl_schulden",tier:"standard",q:[Pe("schuldeiser","Schuldeiser/incassobureau: naam en adres",true),Pe("vordering","Vordering: grond en datum van ontstaan",true),Pe("laatste","Laatste betaling of laatste schriftelijke aanmaning (datum)",true),Pe("termijn","Welke verjaringstermijn is van toepassing?",false,["5 jaar (algemeen)","2 jaar (koopprijs consumentenkoop, art. 7:28 BW)","Onbekend"]),Pe("gegevens","Uw gegevens: naam en adres",true)]},
    "nlx_wsnp_verzoek":{ic:"🏛️",n:"Verzoek toelating schuldsanering (WSNP)",cat:"nl_schulden",tier:"komplex",q:[Pe("minnelijk","Is het minnelijke traject via schuldhulpverlening doorlopen?",true,["Ja, maar mislukt","Nog niet"]),Pe("schulden","Totale schuld en aantal schuldeisers",true),Pe("situatie","Uw inkomen en persoonlijke situatie (kort)",true),Pe("gemeente","Gemeente / schuldhulpverlenende instantie",true),Pe("gegevens","Uw gegevens: naam, adres en BSN",true),Pe("note","Let op: het verzoek gaat via de rechtbank en vereist een 285-verklaring van de gemeente",false)]},
    "nlx_avg_inzage":{ic:"🔍",n:"AVG-inzageverzoek (art. 15 AVG)",cat:"nl_privacy",tier:"standard",q:[Pe("organisatie","Organisatie: naam en adres",true),Pe("relatie","Uw relatie tot de organisatie (klant, ex-werknemer e.d.)",true),Pe("omvang","Welke gegevens wilt u inzien?",false,["Alle persoonsgegevens","Specifieke gegevens"]),Pe("ontvangst","Gewenste manier van ontvangst",false,["Digitaal","Papieren kopie"]),Pe("gegevens","Uw gegevens: naam en adres",true)]},
    "nlx_avg_verwijdering":{ic:"🗑️",n:"Verwijderingsverzoek AVG (recht op vergetelheid, art. 17)",cat:"nl_privacy",tier:"standard",q:[Pe("organisatie","Organisatie: naam en adres",true),Pe("gegevens_welke","Welke gegevens moeten worden verwijderd?",true),Pe("grond","Grond voor verwijdering",true,["Gegevens niet langer nodig","Toestemming ingetrokken","Onrechtmatige verwerking","Bezwaar tegen verwerking"]),Pe("gegevens","Uw gegevens: naam en adres",true)]},
    "nlx_klacht_ap":{ic:"🛡️",n:"Klacht bij de Autoriteit Persoonsgegevens (art. 77 AVG)",cat:"nl_privacy",tier:"standard",q:[Pe("organisatie","Organisatie waarover u klaagt: naam en adres",true),Pe("feiten","Wat is er gebeurd? (de schending van uw privacy)",true),Pe("eerder","Heeft u de organisatie hier al zelf op aangesproken?",true,["Ja","Nee"]),Pe("resultaat","Wat wilt u bereiken?",true),Pe("gegevens","Uw gegevens: naam en adres",true)]},
    "nlx_servicekosten_bezwaar":{ic:"🧾",n:"Bezwaar afrekening servicekosten (art. 7:259 BW)",cat:"nl_wonen",tier:"standard",q:[Pe("verhuurder","Verhuurder: naam en adres",true),Pe("adres","Adres van de woning",true),Pe("periode","Afrekeningsperiode",true),Pe("posten","Betwiste posten en waarom",true),Pe("specificatie","Heeft u om een specificatie/onderliggende stukken gevraagd?",false,["Ja","Nee"]),Pe("gegevens","Uw gegevens: naam",true)]},
    "nlx_medehuurderschap":{ic:"🔑",n:"Verzoek medehuurderschap (art. 7:267 BW)",cat:"nl_wonen",tier:"standard",q:[Pe("verhuurder","Verhuurder: naam en adres",true),Pe("adres","Adres van de woning",true),Pe("medebewoner","Medebewoner: naam en sinds wanneer woonachtig op het adres",true),Pe("huishouding","Sinds wanneer voert u een duurzame gemeenschappelijke huishouding?",true),Pe("gegevens","Uw gegevens: naam (hoofdhuurder)",true)]},
    "nlx_bezwaar_ontslag":{ic:"⚖️",n:"Ontslag betwisten / vernietiging inroepen (art. 7:681 BW)",cat:"nl_werk",tier:"komplex",q:[Pe("werkgever","Werkgever: naam en adres",true),Pe("ontslag","Datum en reden van het ontslag",true),Pe("grond","Waarom is het ontslag onterecht?",true,["Geen instemming of UWV-vergunning","Opzegverbod (ziekte, zwangerschap)","Onterechte dringende reden","Anders"]),Pe("loon","Wilt u doorbetaling van loon en wedertewerkstelling eisen?",false,["Ja","Nee"]),Pe("gegevens","Uw gegevens: naam en adres",true),Pe("note","Let op: de vernietiging moet binnen 2 maanden na het ontslag bij de kantonrechter worden verzocht",false)]},
    "nlx_uitbetaling_vakantiedagen":{ic:"🏖️",n:"Uitbetaling niet-genoten vakantiedagen (art. 7:641 BW)",cat:"nl_werk",tier:"standard",q:[Pe("werkgever","Werkgever: naam en adres",true),Pe("einde","Einddatum van het dienstverband",true),Pe("dagen","Aantal openstaande vakantiedagen en bruto dagloon",true),Pe("termijn","Betaaltermijn (bijvoorbeeld 14 dagen)",true),Pe("gegevens","Uw gegevens: naam en adres",true)]}
  };
  function inject(){
    try{
      if(typeof DOCS==="undefined"||!DOCS) return false;
      if(typeof CATS==="undefined"||!CATS) return false;
      for(var k in E){ if(!DOCS[k]) DOCS[k]=E[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=E[k].tier||"standard"; }
      for(var nc in NEWCATS){ if(!CATS[nc]) CATS[nc]={n:NEWCATS[nc].n,ic:NEWCATS[nc].ic,docs:[],country:"NL"}; if(!CATS[nc].docs) CATS[nc].docs=[]; }
      for(var k2 in E){ var c2=E[k2].cat; if(CATS[c2]&&CATS[c2].docs&&CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); }
      if(typeof CAT_LABELS!=="undefined"){ for(var cl in NEWCATS) CAT_LABELS[cl]=NEWCATS[cl].n; }
      if(typeof CAT_DOCS!=="undefined"){
        for(var cd in NEWCATS){ if(!CAT_DOCS[cd]) CAT_DOCS[cd]=[]; }
        for(var k3 in E){ var c3=E[k3].cat; if(CAT_DOCS[c3]){ var ex=false; for(var i=0;i<CAT_DOCS[c3].length;i++){ if(CAT_DOCS[c3][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[c3].push({k:k3,ic:E[k3].ic,t:E[k3].n,tier:E[k3].tier||"standard"}); } }
      }
      return !!(DOCS.nlx_samenlevingscontract);
    }catch(e){ return false; }
  }
  var done=false, ticks=0;
  function run(){ if(!done){ if(inject()) done=true; } if(ticks++<40) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}

CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
$tmp = tempnam(sys_get_temp_dir(),'nl').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-nlmore-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "  ✓ NL: +17 belge, 6 kategori eklendi\n\n✓ CH_NL_EXPAND_MORE uygulandi.\n";
