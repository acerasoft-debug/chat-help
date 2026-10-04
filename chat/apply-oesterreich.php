<?php
/**
 * ChatHelp — AVUSTURYA MODÜLÜ (~38 belge, 6 kategori, Almanca — Avusturya hukuku) — CH_TR_MODULE ile aynı kanıtlanmış desen
 * -------------------------------------------------------------------------------------------------------
 *  • Kategoriler country:'AT' etiketli -> genelleştirilmiş ülke filtresi (CH_TR_MODULE) otomatik çalışır
 *  • Welcome grid kartları data-cccountry='AT' -> kart görünürlüğü otomatik
 *  • Dil: setCountry('AT') zaten UL='de' yapıyor (CC_DATA.AT.l) — ek dil kodu gerekmez
 *  • Belge dili/hukuku: api.php'de lawSystems['AT'] + doc-lang AT->de (apply-lawsys-all.php ile)
 *  • Avusturya özellikleri: MRG, AMS, Finanzamt Österreich, FAGG, ÖGK, RTR — belgeler Almanca, tutarlar €
 *
 * KULLANIM: pull-updates.php?files=apply-oesterreich.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-oesterreich BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,"CH_AT_MODULE")!==false) exit("Zaten ekli (CH_AT_MODULE).\n");
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-at-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_AT_MODULE — Avusturya hukuku modülü (deferred; DOCS/CATS hazır olunca yüklenir) */
try{(function(){
  function Pq(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var XC={
    at_kuendigungen:{n:"Kündigungen",ic:"📄"},
    at_wohnen:{n:"Wohnen & Miete",ic:"🏠"},
    at_arbeit:{n:"Arbeit & Beruf",ic:"💼"},
    at_konsum:{n:"Konsum & Streitigkeiten",ic:"🛒"},
    at_vertraege:{n:"Verträge",ic:"📝"},
    at_aemter:{n:"Ämter & Behörden",ic:"🏛️"}
  };
  var X_ORDER=["at_kuendigungen","at_wohnen","at_arbeit","at_konsum","at_vertraege","at_aemter"];
  var CCODE="AT", DOCWORD="Dokumente";

  var D={
    /* Kündigungen */
    at_handy_kuendigung:{ic:"📱",n:"Handy / Internet kündigen",cat:"at_kuendigungen",tier:"einfach",q:[Pq("anbieter","Anbieter: Name und Adresse (z. B. A1, Magenta, Drei)",true),Pq("kundennummer","Kunden-/Vertragsnummer bzw. Rufnummer",true),Pq("bindung","Besteht noch eine Mindestvertragsdauer (Bindung)?",false,["Nein","Ja","Weiß nicht"]),Pq("termin","Gewünschter Kündigungstermin",false),Pq("daten","Ihre Daten: Name und Adresse",true)]},
    at_fitness_kuendigung:{ic:"🏋️",n:"Fitnessstudio kündigen",cat:"at_kuendigungen",tier:"einfach",q:[Pq("studio","Studio: Name und Adresse",true),Pq("mitgliedsnummer","Mitgliedsnummer",false),Pq("termin","Kündigungstermin",true),Pq("grund","Grund (optional, z. B. Übersiedlung, Krankheit)",false),Pq("daten","Ihre Daten: Name und Adresse",true)]},
    at_versicherung_kuendigung:{ic:"🛡️",n:"Versicherung kündigen",cat:"at_kuendigungen",tier:"einfach",q:[Pq("versicherung","Versicherung: Name und Adresse",true),Pq("polizze","Polizzennummer",true),Pq("art","Art der Versicherung",true,["Kfz","Haushalt/Eigenheim","Rechtsschutz","Leben","Unfall","Sonstige"]),Pq("hauptfaelligkeit","Hauptfälligkeit (Kündigungsfrist meist 1 Monat davor)",true),Pq("daten","Ihre Daten: Name und Adresse",true)]},
    at_energie_kuendigung:{ic:"⚡",n:"Strom / Gas kündigen",cat:"at_kuendigungen",tier:"einfach",q:[Pq("anbieter","Energieanbieter: Name und Adresse",true),Pq("kundennummer","Kundennummer / Zählpunktnummer",true),Pq("abnahmestelle","Adresse der Abnahmestelle",true),Pq("termin","Gewünschter Kündigungstermin",true),Pq("daten","Ihre Daten: Name und Adresse",true)]},
    at_konto_aufloesung:{ic:"🏦",n:"Bankkonto auflösen",cat:"at_kuendigungen",tier:"einfach",q:[Pq("bank","Bank: Name und Filiale",true),Pq("iban","IBAN des Kontos",true),Pq("uebertrag","IBAN für die Überweisung des Restguthabens (optional)",false),Pq("daten","Ihre Daten: Name, Adresse und Geburtsdatum",true)]},
    at_streaming_kuendigung:{ic:"📺",n:"Streaming-Abo kündigen",cat:"at_kuendigungen",tier:"einfach",q:[Pq("plattform","Plattform (Netflix, Spotify …)",true),Pq("konto","E-Mail-Adresse des Kontos",true),Pq("termin","Kündigungstermin (optional)",false),Pq("daten","Ihre Daten: Name",true)]},

    /* Wohnen & Miete */
    at_kuendigung_mietvertrag:{ic:"🏠",n:"Kündigung Mietvertrag (Mieter)",cat:"at_wohnen",tier:"einfach",q:[Pq("vermieter","Vermieter/Hausverwaltung: Name und Adresse",true),Pq("adresse","Adresse der Wohnung (inkl. Top-Nummer)",true),Pq("termin","Kündigungstermin (Frist laut MRG/Mietvertrag, meist 1–3 Monate zum Monatsletzten)",true),Pq("daten","Ihre Daten: Name und Adresse",true)]},
    at_kaution:{ic:"💶",n:"Kaution zurückfordern",cat:"at_wohnen",tier:"standard",q:[Pq("vermieter","Vermieter: Name und Adresse",true),Pq("adresse","Adresse der Wohnung",true),Pq("uebergabe","Datum der Wohnungsübergabe / Schlüsselrückgabe",true),Pq("betrag","Kautionsbetrag (€)",true),Pq("iban","IBAN für die Rücküberweisung",false),Pq("daten","Ihre Daten: Name und neue Adresse",true)]},
    at_betriebskosten:{ic:"🧾",n:"Einspruch gegen Betriebskostenabrechnung",cat:"at_wohnen",tier:"standard",q:[Pq("vermieter","Vermieter/Hausverwaltung: Name und Adresse",true),Pq("adresse","Adresse der Wohnung",true),Pq("jahr","Abrechnungsjahr",true),Pq("positionen","Beanstandete Positionen und Begründung (Belegeinsicht nach MRG möglich)",true),Pq("daten","Ihre Daten: Name",true)]},
    at_maengelanzeige:{ic:"🔧",n:"Mängelanzeige an den Vermieter",cat:"at_wohnen",tier:"standard",q:[Pq("vermieter","Vermieter/Hausverwaltung: Name und Adresse",true),Pq("adresse","Adresse der Wohnung",true),Pq("mangel","Beschreibung des Mangels/Schadens",true),Pq("dringend","Ist der Mangel dringend (z. B. Wasserschaden, Heizungsausfall)?",false,["Ja","Nein"]),Pq("daten","Ihre Daten: Name",true)]},
    at_mietzinsminderung:{ic:"📉",n:"Mietzinsminderung geltend machen",cat:"at_wohnen",tier:"standard",q:[Pq("vermieter","Vermieter/Hausverwaltung: Name und Adresse",true),Pq("adresse","Adresse der Wohnung",true),Pq("mangel","Mangel und Zeitraum der Beeinträchtigung",true),Pq("mietzins","Aktueller Mietzins (€/Monat)",true),Pq("minderung","Geforderte Minderung (% oder €, optional)",false),Pq("daten","Ihre Daten: Name",true)]},
    at_mietvertrag:{ic:"📄",n:"Mietvertrag (Wohnung)",cat:"at_wohnen",tier:"komplex",q:[Pq("rolle","Sie sind",true,["Vermieter","Mieter"]),Pq("partei","Andere Partei: Name und Adresse",true),Pq("adresse","Adresse der Wohnung (inkl. Top-Nummer)",true),Pq("groesse","Größe (m²) und Zimmeranzahl",false),Pq("beginn","Mietbeginn",true),Pq("mietzins","Hauptmietzins und Betriebskosten (€/Monat)",true),Pq("kaution","Kaution (€, üblich 3 Bruttomonatsmieten)",false),Pq("befristung","Befristung (unbefristet oder befristet, MRG: mind. 3 Jahre)",false)]},

    /* Arbeit & Beruf */
    at_kuendigung_dienstverhaeltnis:{ic:"👋",n:"Kündigung Dienstverhältnis (Arbeitnehmer)",cat:"at_arbeit",tier:"einfach",q:[Pq("arbeitgeber","Arbeitgeber: Name und Adresse",true),Pq("position","Ihre Position",true),Pq("termin","Kündigungstermin (Frist laut Angestelltengesetz/Kollektivvertrag beachten)",true),Pq("daten","Ihre Daten: Name und Adresse",true)]},
    at_dienstzeugnis:{ic:"📜",n:"Dienstzeugnis anfordern",cat:"at_arbeit",tier:"einfach",q:[Pq("arbeitgeber","Arbeitgeber: Name und Adresse",true),Pq("position","Ihre Position und Beschäftigungsdauer",true),Pq("art","Art des Zeugnisses",true,["Einfaches Dienstzeugnis","Qualifiziertes Dienstzeugnis"]),Pq("daten","Ihre Daten: Name und Adresse",true)]},
    at_lohnforderung:{ic:"💶",n:"Offene Lohnforderung geltend machen",cat:"at_arbeit",tier:"standard",q:[Pq("arbeitgeber","Arbeitgeber: Name und Adresse",true),Pq("position","Ihre Position und Beschäftigungszeitraum",true),Pq("anspruch","Offene Ansprüche (Lohn/Gehalt, Überstunden, Sonderzahlungen, Urlaubsersatzleistung)",true),Pq("betrag","Geschätzter Betrag (€, brutto)",false),Pq("frist","Hinweis: Verfallsfristen laut Kollektivvertrag beachten — Zahlungsfrist (z. B. 14 Tage)",false),Pq("daten","Ihre Daten: Name und Adresse",true)]},
    at_urlaubsantrag:{ic:"🏖️",n:"Urlaubsantrag",cat:"at_arbeit",tier:"einfach",q:[Pq("arbeitgeber","Arbeitgeber / Vorgesetzte(r)",true),Pq("von","Von (Datum)",true),Pq("bis","Bis (Datum)",true),Pq("daten","Ihre Daten: Name und Position",true)]},
    at_homeoffice:{ic:"🏡",n:"Antrag auf Home-Office",cat:"at_arbeit",tier:"einfach",q:[Pq("arbeitgeber","Arbeitgeber / Vorgesetzte(r)",true),Pq("position","Ihre Position",true),Pq("regelung","Gewünschte Regelung (Tage/Woche)",true),Pq("begruendung","Begründung (optional)",false)]},
    at_einspruch_entlassung:{ic:"⚖️",n:"Einspruch gegen Entlassung / Kündigung",cat:"at_arbeit",tier:"komplex",q:[Pq("arbeitgeber","Arbeitgeber: Name und Adresse",true),Pq("position","Ihre Position und Dauer des Dienstverhältnisses",true),Pq("datum","Datum der Entlassung/Kündigung (Anfechtung binnen 2 Wochen beim Arbeits- und Sozialgericht)",true),Pq("gruende","Gründe für den Einspruch (z. B. ungerechtfertigte Entlassung, Sozialwidrigkeit)",true),Pq("forderung","Ihre Forderung (Weiterbeschäftigung, Kündigungsentschädigung €)",true),Pq("daten","Ihre Daten: Name und Adresse",true)]},
    at_ams:{ic:"💼",n:"Schreiben an das AMS (Arbeitslosengeld)",cat:"at_arbeit",tier:"standard",q:[Pq("anliegen","Anliegen",true,["Antrag Arbeitslosengeld","Antrag Notstandshilfe","Einspruch gegen Bescheid","Sonstiges"]),Pq("geschaeftsstelle","Zuständige AMS-Geschäftsstelle",true),Pq("situation","Ihre Situation (letzter Arbeitgeber, Ende des Dienstverhältnisses)",true),Pq("daten","Ihre Daten: Name, Adresse und SV-Nummer",true)]},

    /* Konsum & Streitigkeiten */
    at_ruecktritt_fagg:{ic:"🔄",n:"Rücktritt vom Fernabsatz-Kauf (14 Tage, FAGG)",cat:"at_konsum",tier:"einfach",q:[Pq("haendler","Händler: Name und Adresse",true),Pq("bestellnummer","Bestellnummer",true),Pq("datum","Bestell-/Lieferdatum (Rücktrittsfrist 14 Tage ab Erhalt)",true),Pq("produkte","Betroffene Produkte",true),Pq("iban","IBAN für die Rückerstattung (optional)",false)]},
    at_gewaehrleistung:{ic:"🔧",n:"Gewährleistung geltend machen (2 Jahre)",cat:"at_konsum",tier:"standard",q:[Pq("haendler","Händler: Name und Adresse",true),Pq("produkt","Produkt (Marke/Modell)",true),Pq("kaufdatum","Kaufdatum",true),Pq("mangel","Festgestellter Mangel",true),Pq("begehren","Ihr Begehren",true,["Verbesserung (Reparatur)","Austausch","Preisminderung","Vertragsauflösung (Wandlung)"])]},
    at_reklamation:{ic:"📨",n:"Reklamation / Beschwerde an ein Unternehmen",cat:"at_konsum",tier:"standard",q:[Pq("unternehmen","Unternehmen: Name und Adresse",true),Pq("sachverhalt","Sachverhalt (was, wann)",true),Pq("forderung","Ihre Forderung",true,["Rückerstattung","Reparatur","Austausch","Entschädigung"]),Pq("betrag","Betroffener Betrag (€, optional)",false),Pq("daten","Ihre Daten: Name und Adresse",true)]},
    at_flug:{ic:"✈️",n:"Flugentschädigung fordern (EU 261/2004)",cat:"at_konsum",tier:"standard",q:[Pq("airline","Fluglinie",true),Pq("flug","Flugnummer und Datum",true),Pq("problem","Problem",true,["Verspätung","Annullierung","Überbuchung","Gepäck"]),Pq("forderung","Ihre Forderung (Entschädigung 250–600 € nach EU 261/2004)",true),Pq("daten","Ihre Daten: Name und Adresse",true)]},
    at_mahnung:{ic:"💰",n:"Mahnung / Zahlungsaufforderung",cat:"at_konsum",tier:"standard",q:[Pq("schuldner","Schuldner: Name und Adresse",true),Pq("forderung","Forderung (Grund, Rechnungsnummer, Datum)",true),Pq("betrag","Offener Betrag (€)",true),Pq("stufe","Mahnstufe",false,["Erste Mahnung","Zweite Mahnung","Letzte Mahnung vor Klage"]),Pq("frist","Zahlungsfrist (z. B. 14 Tage)",true),Pq("daten","Ihre Daten: Name, Adresse und IBAN",true)]},
    at_einspruch_rechnung:{ic:"🧾",n:"Einspruch gegen eine Rechnung",cat:"at_konsum",tier:"standard",q:[Pq("aussteller","Rechnungssteller: Name und Adresse",true),Pq("rechnung","Rechnungsnummer und Datum",true),Pq("betrag","Beanstandeter Betrag (€)",true),Pq("begruendung","Begründung des Einspruchs",true),Pq("daten","Ihre Daten: Name und Kundennummer",true)]},
    at_rtr_beschwerde:{ic:"📶",n:"Beschwerde Telekom-Anbieter (RTR-Schlichtung)",cat:"at_konsum",tier:"standard",q:[Pq("anbieter","Anbieter: Name",true),Pq("kundennummer","Kundennummer / Rufnummer",true),Pq("problem","Problem (Verrechnung, Bindung, Service)",true),Pq("vorgeschichte","Bisherige Beschwerde beim Anbieter (Datum, Antwort)",true),Pq("forderung","Ihre Forderung",true),Pq("daten","Ihre Daten: Name und Adresse",true)]},

    /* Verträge */
    at_kaufvertrag:{ic:"🛒",n:"Kaufvertrag zwischen Privatpersonen",cat:"at_vertraege",tier:"einfach",q:[Pq("rolle","Sie sind",true,["Verkäufer","Käufer"]),Pq("partei","Andere Partei: Name und Adresse",true),Pq("gegenstand","Kaufgegenstand",true),Pq("preis","Kaufpreis (€)",true),Pq("uebergabe","Übergabedatum (optional)",false)]},
    at_darlehensvertrag:{ic:"🤝",n:"Darlehensvertrag (privat)",cat:"at_vertraege",tier:"standard",q:[Pq("geber","Darlehensgeber: Name und Adresse",true),Pq("nehmer","Darlehensnehmer: Name und Adresse",true),Pq("betrag","Darlehensbetrag (€)",true),Pq("rueckzahlung","Rückzahlungsplan (Raten, Fälligkeit)",true),Pq("zinsen","Zinsen (% p. a., optional)",false)]},
    at_schuldschein:{ic:"💶",n:"Schuldschein / Schuldanerkenntnis",cat:"at_vertraege",tier:"standard",q:[Pq("glaeubiger","Gläubiger: Name und Adresse",true),Pq("schuldner","Schuldner: Name und Adresse",true),Pq("betrag","Betrag (€, in Ziffern und Worten)",true),Pq("rueckzahlung","Rückzahlung (Frist/Raten)",true),Pq("zinsen","Verzinsung",false,["Ohne Zinsen","Mit Zinsen"])]},
    at_vollmacht:{ic:"📝",n:"Vollmacht",cat:"at_vertraege",tier:"einfach",q:[Pq("bevollmaechtigte","Bevollmächtigte Person: Name und Geburtsdatum",true),Pq("zweck","Wofür wird bevollmächtigt",true),Pq("gueltigkeit","Gültigkeit (optional)",false),Pq("daten","Ihre Daten (Vollmachtgeber): Name, Adresse und Geburtsdatum",true)]},
    at_nda:{ic:"🔒",n:"Geheimhaltungsvereinbarung (NDA)",cat:"at_vertraege",tier:"standard",q:[Pq("partei","Andere Partei: Name und Adresse",true),Pq("gegenstand","Gegenstand der Zusammenarbeit",true),Pq("umfang","Umfang der vertraulichen Informationen",true),Pq("dauer","Dauer (optional)",false)]},
    at_werkvertrag:{ic:"🧰",n:"Werkvertrag",cat:"at_vertraege",tier:"komplex",q:[Pq("rolle","Sie sind",true,["Auftragnehmer","Auftraggeber"]),Pq("partei","Andere Partei: Name und Adresse",true),Pq("werk","Beschreibung der Werkleistung",true),Pq("entgelt","Entgelt und Zahlungsweise (€)",true),Pq("frist","Fertigstellungstermin (optional)",false),Pq("klauseln","Besondere Vereinbarungen (optional)",false)]},
    at_uebergabeprotokoll:{ic:"🔑",n:"Übergabeprotokoll (Wohnung)",cat:"at_vertraege",tier:"standard",q:[Pq("anlass","Anlass",true,["Einzug","Auszug"]),Pq("parteien","Parteien (Vermieter und Mieter: Namen)",true),Pq("adresse","Adresse der Wohnung",true),Pq("zaehler","Zählerstände (Strom/Gas/Wasser, optional)",false),Pq("zustand","Zustand/Mängel der Wohnung",true),Pq("schluessel","Anzahl der übergebenen Schlüssel",false)]},

    /* Ämter & Behörden */
    at_verkehrsstrafe:{ic:"🚗",n:"Einspruch gegen Verkehrsstrafe (Anonym-/Strafverfügung)",cat:"at_aemter",tier:"standard",q:[Pq("behoerde","Behörde (LPD, Magistrat, Bezirkshauptmannschaft)",true),Pq("aktenzahl","Aktenzahl / Geschäftszahl",true),Pq("datum","Datum der Verfügung (Einspruch gegen Strafverfügung binnen 2 Wochen)",true),Pq("kennzeichen","Kennzeichen (optional)",false),Pq("begruendung","Begründung des Einspruchs",true),Pq("daten","Ihre Daten: Name, Adresse und Geburtsdatum",true)]},
    at_finanzamt_raten:{ic:"📅",n:"Ratenzahlung / Beschwerde ans Finanzamt Österreich",cat:"at_aemter",tier:"komplex",q:[Pq("anliegen","Anliegen",true,["Ratenzahlung / Zahlungserleichterung","Stundung","Beschwerde gegen Bescheid"]),Pq("steuernummer","Steuernummer / Abgabenkontonummer",true),Pq("abgabe","Abgabe und Betrag (€)",true),Pq("begruendung","Begründung (wirtschaftliche Lage bzw. Beschwerdegründe)",true),Pq("vorschlag","Ratenvorschlag (€/Monat, optional)",false),Pq("daten","Ihre Daten: Name und Adresse",true)]},
    at_fristverlaengerung:{ic:"🗓️",n:"Fristverlängerung Steuererklärung",cat:"at_aemter",tier:"einfach",q:[Pq("steuernummer","Steuernummer",true),Pq("jahr","Betroffenes Veranlagungsjahr",true),Pq("erklaerung","Erklärung",true,["Einkommensteuererklärung","Arbeitnehmerveranlagung","Umsatzsteuererklärung"]),Pq("frist","Gewünschte neue Frist",true),Pq("begruendung","Begründung (optional)",false),Pq("daten","Ihre Daten: Name und Adresse",true)]},
    at_ummeldung:{ic:"🏠",n:"Hauptwohnsitz ummelden (Meldezettel)",cat:"at_aemter",tier:"einfach",q:[Pq("meldeamt","Gemeinde/Magistrat (Meldeamt)",true),Pq("neue_adresse","Neue Adresse (Hauptwohnsitz)",true),Pq("alte_adresse","Bisherige Adresse",true),Pq("einzug","Einzugsdatum (Meldung binnen 3 Tagen)",true),Pq("unterkunftgeber","Unterkunftgeber: Name",false),Pq("daten","Ihre Daten: Name, Geburtsdatum und Staatsangehörigkeit",true)]},
    at_strafregister:{ic:"📋",n:"Strafregisterbescheinigung beantragen",cat:"at_aemter",tier:"einfach",q:[Pq("behoerde","Behörde (Gemeindeamt/Magistrat oder Landespolizeidirektion)",true),Pq("art","Art der Bescheinigung",true,["Standard","Kinder- und Jugendfürsorge","Pflege und Betreuung"]),Pq("zweck","Verwendungszweck",true),Pq("daten","Ihre Daten: Name, Geburtsdatum und Geburtsort",true)]},
    at_oegk:{ic:"🏥",n:"Schreiben an die ÖGK (Krankenkasse)",cat:"at_aemter",tier:"standard",q:[Pq("anliegen","Anliegen",true,["Kostenerstattung","Krankengeld","Einspruch gegen Bescheid","Sonstiges"]),Pq("sachverhalt","Sachverhalt (Behandlung, Zeitraum, Belege)",true),Pq("forderung","Ihre Forderung",true),Pq("daten","Ihre Daten: Name, Adresse und SV-Nummer",true)]}
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
      return (typeof DOCS!=="undefined" && DOCS.at_mahnung)?true:false;
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
if($src!==$start){ file_put_contents($file,$src); echo "Avusturya modulu eklendi (CH_AT_MODULE) - ~38 belge, 6 kategori, Almanca.\n"; }
else echo "degisiklik yok\n";
echo "\nNOT: Belge dili/hukuku icin apply-lawsys-all.php de calistirilmali (lawSystems['AT'] + doc-lang).\n";
echo "SONRA: opcache-reset (pull-updates otomatik yapar). SIL: rm apply-oesterreich.php\n";
echo "Test: Ayarlar -> Oesterreich sec -> kategoriler Almanca -> 'Mahnung / Zahlungsaufforderung' uret.\n";
