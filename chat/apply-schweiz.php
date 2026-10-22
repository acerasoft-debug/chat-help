<?php
/**
 * ChatHelp — İSVİÇRE MODÜLÜ (~39 belge, 6 kategori, Almanca/İsviçre hukuku) — CH_TR_MODULE ile aynı kanıtlanmış desen
 * -------------------------------------------------------------------------------------------------------
 *  • Kategoriler country:'CH' etiketli -> genelleştirilmiş ülke filtresi (CH_TR_MODULE) otomatik çalışır
 *  • Welcome grid kartları data-cccountry='CH' -> kart görünürlüğü otomatik
 *  • Dil: setCountry('CH') zaten UL='de' yapıyor (CC_DATA.CH.l) — ek dil kodu gerekmez
 *  • Belge dili/hukuku: api.php'de lawSystems['CH'] + doc-lang CH->de (apply-lawsys-all.php ile)
 *
 * KULLANIM: pull-updates.php?files=apply-schweiz.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-schweiz BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,"CH_CH_MODULE")!==false) exit("Zaten ekli (CH_CH_MODULE).\n");
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-chm-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_CH_MODULE — İsviçre hukuku modülü (deferred; DOCS/CATS hazır olunca yüklenir) */
try{(function(){
  function Pq(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var XC={
    ch_kuendigung:{n:"Kündigungen",ic:"📄"},
    ch_wohnen:{n:"Wohnen & Miete",ic:"🏠"},
    ch_arbeit:{n:"Arbeit & Beruf",ic:"💼"},
    ch_konsum:{n:"Konsum & Streitigkeiten",ic:"🛒"},
    ch_vertraege:{n:"Verträge",ic:"📝"},
    ch_behoerden:{n:"Behörden & Ämter",ic:"🏛️"}
  };
  var X_ORDER=["ch_kuendigung","ch_wohnen","ch_arbeit","ch_konsum","ch_vertraege","ch_behoerden"];
  var CCODE="CH", DOCWORD="Dokumente";

  var D={
    /* Kündigungen */
    ch_krankenkasse:{ic:"🏥",n:"Krankenkasse kündigen / wechseln (KVG)",cat:"ch_kuendigung",tier:"einfach",q:[Pq("kasse","Krankenkasse: Name und Adresse",true),Pq("versichertennr","Versichertennummer",true),Pq("umfang","Was kündigen Sie?",true,["Grundversicherung (KVG)","Zusatzversicherung (VVG)","Beides"]),Pq("termin","Kündigung per (Grundversicherung: 31.12., Brief muss bis 30.11. bei der Kasse sein)",true),Pq("daten","Ihr Name, Adresse und Geburtsdatum",true)]},
    ch_handy_abo:{ic:"📱",n:"Handy- / Internet-Abo kündigen",cat:"ch_kuendigung",tier:"einfach",q:[Pq("anbieter","Anbieter: Name und Adresse",true),Pq("kundennr","Kunden- / Vertragsnummer",true),Pq("frist","Mindestvertragsdauer / Kündigungsfrist bekannt?",false,["Nein","Ja","Weiss nicht"]),Pq("termin","Gewünschter Kündigungstermin",false),Pq("daten","Ihr Name und Adresse",true)]},
    ch_fitness_abo:{ic:"🏋️",n:"Fitness-Abo kündigen",cat:"ch_kuendigung",tier:"einfach",q:[Pq("studio","Fitnesscenter: Name und Adresse",true),Pq("mitgliednr","Mitgliedernummer",false),Pq("termin","Kündigungstermin (Vertragsablauf beachten)",true),Pq("grund","Grund (optional, z.B. Umzug, Krankheit)",false),Pq("daten","Ihr Name und Adresse",true)]},
    ch_versicherung:{ic:"🛡️",n:"Versicherung kündigen",cat:"ch_kuendigung",tier:"einfach",q:[Pq("versicherer","Versicherung: Name und Adresse",true),Pq("policennr","Policennummer",true),Pq("typ","Art der Versicherung",true,["Auto / Motorfahrzeug","Hausrat","Privathaftpflicht","Rechtsschutz","Andere"]),Pq("ablauf","Vertragsablauf (Kündigungsfrist meist 3 Monate)",true),Pq("daten","Ihr Name und Adresse",true)]},
    ch_zeitung_abo:{ic:"📺",n:"Zeitungs- / Streaming-Abo kündigen",cat:"ch_kuendigung",tier:"einfach",q:[Pq("anbieter","Anbieter (Zeitung, Netflix, Spotify…)",true),Pq("kundennr","Kunden-Nr. / Konto-E-Mail",true),Pq("termin","Kündigungstermin (optional)",false),Pq("daten","Ihr Name und Adresse",true)]},
    ch_bankkonto:{ic:"🏦",n:"Bankkonto auflösen",cat:"ch_kuendigung",tier:"einfach",q:[Pq("bank","Bank: Name und Filiale",true),Pq("iban","IBAN des Kontos",true),Pq("saldo_iban","IBAN für die Überweisung des Restsaldos (optional)",false),Pq("daten","Ihr Name, Adresse und Geburtsdatum",true)]},

    /* Wohnen & Miete */
    ch_kuendigung_miete:{ic:"🏠",n:"Kündigung Mietvertrag (Mieter)",cat:"ch_wohnen",tier:"einfach",q:[Pq("vermieter","Vermieter / Verwaltung: Name und Adresse",true),Pq("adresse","Adresse der Mietwohnung",true),Pq("termin","Kündigungstermin (OR 266c: 3 Monate auf ortsüblichen Termin; nur Vermieterkündigungen brauchen das amtliche Formular)",true),Pq("mitmieter","Familienwohnung: Ehegatte / eingetragene(r) Partner(in) unterschreibt mit?",false,["Ja","Nein","Nicht zutreffend"]),Pq("daten","Ihr Name und Adresse",true)]},
    ch_mietzins_anfechten:{ic:"📈",n:"Mietzinserhöhung anfechten (Schlichtungsbehörde)",cat:"ch_wohnen",tier:"komplex",q:[Pq("behoerde","Zuständige Schlichtungsbehörde in Mietsachen (Ort)",true),Pq("vermieter","Vermieter / Verwaltung: Name und Adresse",true),Pq("adresse","Adresse der Mietwohnung",true),Pq("mietzins_alt","Bisheriger Mietzins (CHF)",true),Pq("mietzins_neu","Neuer Mietzins (CHF) und Datum des amtlichen Formulars",true),Pq("gruende","Gründe der Anfechtung (z.B. Referenzzinssatz, übersetzter Ertrag — Frist: 30 Tage)",true)]},
    ch_maengelruege_miete:{ic:"🔧",n:"Mängelrüge an Vermieter",cat:"ch_wohnen",tier:"standard",q:[Pq("vermieter","Vermieter / Verwaltung: Name und Adresse",true),Pq("adresse","Adresse der Mietwohnung",true),Pq("mangel","Beschreibung des Mangels (seit wann, Auswirkungen)",true),Pq("frist","Frist zur Behebung (z.B. 14 Tage)",false),Pq("daten","Ihr Name",true)]},
    ch_mietkaution:{ic:"💰",n:"Mietzinsdepot (Kaution) zurückfordern",cat:"ch_wohnen",tier:"standard",q:[Pq("vermieter","Vermieter / Verwaltung: Name und Adresse",true),Pq("adresse","Adresse der ehemaligen Mietwohnung",true),Pq("auszug","Datum der Wohnungsrückgabe",true),Pq("betrag","Höhe des Depots (CHF)",true),Pq("bank","Bank des Mietkautionskontos (optional)",false),Pq("daten","Ihr Name und neue Adresse",true)]},
    ch_untermiete:{ic:"🔑",n:"Gesuch um Untermiete (OR 262)",cat:"ch_wohnen",tier:"standard",q:[Pq("vermieter","Vermieter / Verwaltung: Name und Adresse",true),Pq("adresse","Adresse der Mietwohnung",true),Pq("untermieter","Untermieter: Name (und Beruf, optional)",true),Pq("bedingungen","Untermietzins (CHF) und Dauer der Untermiete",true),Pq("grund","Grund (z.B. Auslandaufenthalt, optional)",false)]},
    ch_nebenkosten:{ic:"🧾",n:"Nebenkostenabrechnung: Einsicht / Beanstandung",cat:"ch_wohnen",tier:"standard",q:[Pq("vermieter","Vermieter / Verwaltung: Name und Adresse",true),Pq("adresse","Adresse der Mietwohnung",true),Pq("periode","Abrechnungsperiode",true),Pq("beanstandung","Was beanstanden Sie / wollen Sie Einsicht in die Belege?",true),Pq("daten","Ihr Name",true)]},

    /* Arbeit & Beruf */
    ch_kuendigung_arbeit:{ic:"👋",n:"Kündigung Arbeitsvertrag (Arbeitnehmer, OR 335)",cat:"ch_arbeit",tier:"einfach",q:[Pq("arbeitgeber","Arbeitgeber: Name und Adresse",true),Pq("position","Ihre Funktion / Position",true),Pq("termin","Kündigungstermin (Frist gemäss Vertrag bzw. OR 335c: 1-3 Monate auf Monatsende)",true),Pq("daten","Ihr Name und Adresse",true)]},
    ch_arbeitszeugnis:{ic:"📜",n:"Arbeitszeugnis verlangen (OR 330a)",cat:"ch_arbeit",tier:"einfach",q:[Pq("arbeitgeber","Arbeitgeber / HR: Name und Adresse",true),Pq("position","Ihre Funktion und Anstellungsdauer",true),Pq("art","Gewünschtes Zeugnis",true,["Vollzeugnis","Zwischenzeugnis","Arbeitsbestätigung"]),Pq("daten","Ihr Name",true)]},
    ch_lohnforderung:{ic:"💵",n:"Lohnforderung (ausstehender Lohn)",cat:"ch_arbeit",tier:"standard",q:[Pq("arbeitgeber","Arbeitgeber: Name und Adresse",true),Pq("zeitraum","Betroffener Zeitraum (Monate)",true),Pq("betrag","Ausstehender Betrag (CHF, inkl. 13. Monatslohn / Ferien falls betroffen)",true),Pq("frist","Zahlungsfrist (z.B. 10 Tage)",false),Pq("daten","Ihr Name und Funktion",true)]},
    ch_einsprache_kuendigung:{ic:"⚖️",n:"Einsprache gegen Kündigung (missbräuchlich, OR 336)",cat:"ch_arbeit",tier:"komplex",q:[Pq("arbeitgeber","Arbeitgeber: Name und Adresse",true),Pq("kuendigung","Kündigung: Datum und angegebener Grund",true),Pq("gruende","Warum ist die Kündigung missbräuchlich? (Einsprache muss vor Ende der Kündigungsfrist erfolgen, OR 336b)",true),Pq("daten","Ihr Name, Funktion und Eintrittsdatum",true)]},
    ch_rav:{ic:"💼",n:"Schreiben an RAV / Arbeitslosenkasse (ALV)",cat:"ch_arbeit",tier:"standard",q:[Pq("stelle","RAV oder Arbeitslosenkasse: Ort",true),Pq("anliegen","Anliegen",true,["Anmeldung","Einsprache gegen Verfügung","Nachweis Arbeitsbemühungen","Sonstiges"]),Pq("sachverhalt","Sachverhalt (letzte Stelle, Datum der Kündigung, Details)",true),Pq("daten","Ihr Name, Adresse und AHV-Nummer (756.…)",true)]},
    ch_ueberstunden:{ic:"⏰",n:"Überstunden einfordern (OR 321c)",cat:"ch_arbeit",tier:"standard",q:[Pq("arbeitgeber","Arbeitgeber: Name und Adresse",true),Pq("zeitraum","Betroffener Zeitraum",true),Pq("stunden","Anzahl Überstunden (nach Möglichkeit belegt)",true),Pq("abgeltung","Gewünschte Abgeltung",true,["Auszahlung mit Zuschlag (125%)","Kompensation durch Freizeit"]),Pq("daten","Ihr Name und Funktion",true)]},
    ch_unbezahlter_urlaub:{ic:"🌴",n:"Gesuch um unbezahlten Urlaub",cat:"ch_arbeit",tier:"einfach",q:[Pq("arbeitgeber","Arbeitgeber / Vorgesetzte(r)",true),Pq("zeitraum","Gewünschter Zeitraum (von — bis)",true),Pq("grund","Grund (optional)",false),Pq("daten","Ihr Name und Funktion",true)]},

    /* Konsum & Streitigkeiten */
    ch_rechtsvorschlag:{ic:"🛑",n:"Rechtsvorschlag (Betreibung, SchKG)",cat:"ch_konsum",tier:"standard",q:[Pq("betreibungsamt","Betreibungsamt: Ort",true),Pq("betreibungsnr","Betreibungs-Nr. gemäss Zahlungsbefehl",true),Pq("zustellung","Datum der Zustellung des Zahlungsbefehls (Frist: 10 Tage)",true),Pq("umfang","Umfang des Rechtsvorschlags",true,["Ganze Forderung","Nur ein Teil der Forderung"]),Pq("daten","Ihr Name und Adresse",true)]},
    ch_betreibungsbegehren:{ic:"📮",n:"Betreibungsbegehren (SchKG 67)",cat:"ch_konsum",tier:"standard",q:[Pq("betreibungsamt","Betreibungsamt am Wohnsitz des Schuldners (Ort)",true),Pq("schuldner","Schuldner: Name und Adresse",true),Pq("forderung","Forderung (CHF) und Zins (z.B. 5% seit …)",true),Pq("grund","Forderungsgrund (z.B. Rechnung vom …, Vertrag)",true),Pq("daten","Gläubiger (Sie): Name und Adresse",true)]},
    ch_betreibungsauszug:{ic:"📊",n:"Betreibungsregisterauszug beantragen",cat:"ch_konsum",tier:"einfach",q:[Pq("betreibungsamt","Betreibungsamt Ihres Wohnorts",true),Pq("zweck","Verwendungszweck (z.B. Wohnungsbewerbung, optional)",false),Pq("daten","Ihr Name, Adresse und Geburtsdatum (ID-Kopie beilegen)",true)]},
    ch_mahnung:{ic:"📨",n:"Mahnung / Zahlungsaufforderung",cat:"ch_konsum",tier:"einfach",q:[Pq("schuldner","Schuldner: Name und Adresse",true),Pq("forderung","Offener Betrag (CHF)",true),Pq("grund","Grund (Rechnung / Vertrag, Datum)",true),Pq("frist","Zahlungsfrist (z.B. 10 Tage)",true),Pq("folge","Bei Nichtzahlung Betreibung androhen?",false,["Ja","Nein"])]},
    ch_widerruf_haustuer:{ic:"🔄",n:"Widerruf Haustürgeschäft (14 Tage, OR 40a ff.)",cat:"ch_konsum",tier:"einfach",q:[Pq("firma","Firma: Name und Adresse",true),Pq("vertrag","Vertrag / Bestellung: Gegenstand und Betrag (CHF)",true),Pq("datum","Datum des Vertragsschlusses (Widerrufsfrist: 14 Tage)",true),Pq("daten","Ihr Name und Adresse",true)]},
    ch_maengelruege_kauf:{ic:"🔍",n:"Mängelrüge beim Kauf (OR 201)",cat:"ch_konsum",tier:"standard",q:[Pq("verkaeufer","Verkäufer: Name und Adresse",true),Pq("produkt","Produkt (Marke / Modell)",true),Pq("kaufdatum","Kaufdatum und Preis (CHF)",true),Pq("mangel","Festgestellter Mangel (sofort rügen!)",true),Pq("forderung","Ihre Forderung",true,["Ersatzlieferung","Wandelung (Rückabwicklung)","Minderung des Preises","Reparatur"])]},
    ch_flug:{ic:"✈️",n:"Entschädigung Flugverspätung / Annullierung",cat:"ch_konsum",tier:"standard",q:[Pq("airline","Fluggesellschaft",true),Pq("flug","Flugnummer und Datum",true),Pq("problem","Problem",true,["Verspätung","Annullierung","Überbuchung","Gepäck"]),Pq("forderung","Ihre Forderung (Entschädigung nach EU 261/2004 gilt auch für Flüge ab der Schweiz mit EU-Airlines)",true),Pq("daten","Ihr Name und Adresse",true)]},

    /* Verträge */
    ch_kaufvertrag:{ic:"🛒",n:"Kaufvertrag zwischen Privaten",cat:"ch_vertraege",tier:"einfach",q:[Pq("rolle","Sie sind",true,["Verkäufer","Käufer"]),Pq("gegenpartei","Gegenpartei: Name und Adresse",true),Pq("gegenstand","Kaufgegenstand (z.B. Auto mit Kilometerstand)",true),Pq("preis","Kaufpreis (CHF)",true),Pq("uebergabe","Übergabedatum (optional)",false),Pq("gewaehr","Gewährleistung",false,["Mit Gewährleistung","Gewährleistung wegbedungen (ausgeschlossen)"])]},
    ch_darlehen:{ic:"🤝",n:"Darlehensvertrag (OR 312)",cat:"ch_vertraege",tier:"standard",q:[Pq("darleiher","Darlehensgeber: Name und Adresse",true),Pq("borger","Darlehensnehmer: Name und Adresse",true),Pq("betrag","Darlehensbetrag (CHF)",true),Pq("rueckzahlung","Rückzahlung (Raten / Termin)",true),Pq("zins","Zins (% pro Jahr, optional)",false)]},
    ch_schuldanerkennung:{ic:"✍️",n:"Schuldanerkennung (SchKG 82)",cat:"ch_vertraege",tier:"standard",q:[Pq("glaeubiger","Gläubiger: Name und Adresse",true),Pq("schuldner","Schuldner: Name und Adresse",true),Pq("betrag","Betrag (CHF, in Zahlen und Worten)",true),Pq("zahlung","Rückzahlungsplan / Fälligkeit",true),Pq("grund","Grund der Schuld (optional)",false)]},
    ch_vollmacht:{ic:"📝",n:"Vollmacht",cat:"ch_vertraege",tier:"einfach",q:[Pq("bevollmaechtigt","Bevollmächtigte Person: Name und Adresse",true),Pq("umfang","Wofür wird bevollmächtigt?",true),Pq("dauer","Gültigkeitsdauer (optional)",false),Pq("daten","Vollmachtgeber (Sie): Name, Adresse und Geburtsdatum",true)]},
    ch_nda:{ic:"🔒",n:"Geheimhaltungsvereinbarung (NDA)",cat:"ch_vertraege",tier:"standard",q:[Pq("gegenpartei","Gegenpartei: Name und Adresse",true),Pq("zweck","Zweck der Zusammenarbeit",true),Pq("umfang","Umfang der vertraulichen Informationen",true),Pq("dauer","Dauer (optional)",false)]},
    ch_werkvertrag:{ic:"🧰",n:"Werkvertrag / Auftrag (OR 363 / OR 394)",cat:"ch_vertraege",tier:"komplex",q:[Pq("rolle","Sie sind",true,["Unternehmer / Beauftragter","Besteller / Auftraggeber"]),Pq("gegenpartei","Gegenpartei: Name und Adresse",true),Pq("leistung","Beschreibung der Leistung / des Werks",true),Pq("verguetung","Vergütung (CHF) und Zahlungsmodalitäten",true),Pq("termin","Termin / Dauer (optional)",false),Pq("klauseln","Besondere Klauseln (optional)",false)]},

    /* Behörden & Ämter */
    ch_frist_steuern:{ic:"📅",n:"Fristerstreckung Steuererklärung",cat:"ch_behoerden",tier:"einfach",q:[Pq("amt","Steueramt (Gemeinde / Kanton)",true),Pq("steuerjahr","Steuerjahr",true),Pq("frist","Gewünschte neue Frist",true),Pq("grund","Grund (optional)",false),Pq("daten","Ihr Name, Adresse und Register- / AHV-Nummer",true)]},
    ch_einsprache_steuern:{ic:"🧾",n:"Einsprache gegen Steuerveranlagung",cat:"ch_behoerden",tier:"komplex",q:[Pq("amt","Steueramt / Veranlagungsbehörde",true),Pq("verfuegung","Veranlagungsverfügung: Datum und Nummer (Einsprachefrist: 30 Tage)",true),Pq("punkte","Angefochtene Punkte (z.B. Abzüge, Einkommen)",true),Pq("begruendung","Begründung und Beweismittel",true),Pq("daten","Ihr Name, Adresse und AHV-Nummer",true)]},
    ch_einsprache_busse:{ic:"🚗",n:"Einsprache gegen Ordnungsbusse (Verkehr)",cat:"ch_behoerden",tier:"standard",q:[Pq("behoerde","Ausstellende Behörde (Polizei / Gemeinde)",true),Pq("bussennr","Bussen- / Übertretungsnummer",true),Pq("datum","Datum des Vorfalls (Einsprachefrist: 30 Tage)",true),Pq("begruendung","Begründung der Einsprache",true),Pq("daten","Ihr Name, Adresse und Kontrollschild (optional)",true)]},
    ch_ahv_iv:{ic:"🏛️",n:"Schreiben an AHV / IV (Ausgleichskasse)",cat:"ch_behoerden",tier:"standard",q:[Pq("stelle","Ausgleichskasse / IV-Stelle (Kanton)",true),Pq("anliegen","Anliegen",true,["Anmeldung für Leistung","Einsprache gegen Verfügung","Auskunft / Kontoauszug","Sonstiges"]),Pq("sachverhalt","Sachverhalt (kurz)",true),Pq("daten","Ihr Name, Adresse und AHV-Nummer (756.…)",true)]},
    ch_praemienverbilligung:{ic:"💶",n:"Antrag Prämienverbilligung (Krankenkasse)",cat:"ch_behoerden",tier:"standard",q:[Pq("stelle","Zuständige kantonale Stelle (SVA / Ausgleichskasse)",true),Pq("einkommen","Steuerbares Einkommen (CHF, letzte Veranlagung)",true),Pq("haushalt","Personen im Haushalt (Erwachsene / Kinder)",true),Pq("kasse","Krankenkasse und Versichertennummer",true),Pq("daten","Ihr Name, Adresse und AHV-Nummer",true)]},
    ch_umzug_gemeinde:{ic:"📦",n:"Umzug: An- / Abmeldung bei der Gemeinde",cat:"ch_behoerden",tier:"einfach",q:[Pq("gemeinde","Einwohnerkontrolle der (neuen) Gemeinde",true),Pq("datum","Umzugsdatum (Meldung innert 14 Tagen)",true),Pq("adresse_neu","Neue Adresse",true),Pq("adresse_alt","Bisherige Adresse",true),Pq("daten","Ihr Name, Geburtsdatum und Heimatort / Nationalität",true)]},
    ch_strafregister:{ic:"📄",n:"Strafregisterauszug beantragen",cat:"ch_behoerden",tier:"einfach",q:[Pq("art","Art des Auszugs",true,["Privatauszug","Sonderprivatauszug (Arbeit mit Minderjährigen)"]),Pq("zweck","Verwendungszweck (z.B. Arbeitgeber, optional)",false),Pq("daten","Ihr Name, Adresse, Geburtsdatum und Heimatort / Nationalität",true)]}
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
      return (typeof DOCS!=="undefined" && DOCS.ch_rechtsvorschlag)?true:false;
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
if($src!==$start){ file_put_contents($file,$src); echo "Isvicre modulu eklendi (CH_CH_MODULE) - ~39 belge, 6 kategori, Almanca/Isvicre hukuku.\n"; }
else echo "degisiklik yok\n";
echo "\nNOT: Belge dili/hukuku icin apply-lawsys-all.php de calistirilmali (lawSystems['CH'] + doc-lang).\n";
echo "SONRA: opcache-reset (pull-updates otomatik yapar). SIL: rm apply-schweiz.php\n";
echo "Test: Ayarlar -> Schweiz sec -> kategoriler Almanca -> 'Rechtsvorschlag (Betreibung, SchKG)' uret.\n";
