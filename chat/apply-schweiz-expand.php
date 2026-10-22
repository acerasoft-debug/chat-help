<?php
/**
 * ChatHelp — İSVİÇRE KATALOG GENİŞLETME (+36 belge, 1 yeni kategori: Familie)
 * --------------------------------------------------------------------------------
 *  Mevcut 6 CH kategorisine yeni belgeler ekler + "Familie" kategorisi açar
 *  (yeni kategori enjeksiyonu apply-turkiye-expand.php ile aynı kanıtlanmış desen).
 *  Öne çıkanlar: Scheidungskonvention, Konkubinatsvertrag, Ehevertrag (Gütertrennung),
 *  Mietvertrag, Autokaufvertrag, Aufhebungsvereinbarung, Mietzinshinterlegung (OR 259g),
 *  Anfechtung Anfangsmietzins, Datenauskunft DSG, SchKG 8a Löschungsgesuch, Erlassgesuch Steuern.
 *  Tüm belgeler tier'lı -> Stripe ödeme otomatik. Belge dili/hukuku CC=CH üzerinden
 *  otomatik (lawSystems['CH'] + doc-lang CH->de zaten api.php'de).
 *
 * KULLANIM: chat-help.com/chat/apply-schweiz-expand.php -> opcache-reset.php. SİL.
 * GEREKLİ: apply-schweiz.php önce çalıştırılmış olmalı (CH_CH_MODULE, ch_* kategoriler).
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-schweiz-expand BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,"CH_CH_EXPAND")!==false) exit("Zaten ekli (CH_CH_EXPAND).\n");
if(strpos($src,"CH_CH_MODULE")===false) exit("CH_CH_MODULE yok — once apply-schweiz.php calistir.\n");
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-chexpand-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_CH_EXPAND — İsviçre katalog genişletme (+36 belge, yeni kategori: Familie; deferred yükleme) */
try{(function(){
  function Pe(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}

  var NEWCATS={
    ch_familie:{n:"Familie",ic:"👨‍👩‍👧"}
  };

  var E={
    /* Familie (neue Kategorie) */
    ch_scheidungskonvention:{ic:"📜",n:"Scheidungskonvention (einvernehmliche Scheidung, Entwurf)",cat:"ch_familie",tier:"komplex",q:[Pe("eheleute","Eheleute: Namen und Adressen",true),Pe("heirat","Datum der Heirat",true),Pe("kinder","Gemeinsame Kinder (Name/Jahrgang, sonst 'keine')",true),Pe("betreuung","Elterliche Sorge und Betreuungsregelung (falls Kinder)",false),Pe("unterhalt","Unterhaltsregelung (Kinder- / nachehelicher Unterhalt, CHF)",true),Pe("gueter","Güterrechtliche Auseinandersetzung und Vorsorgeausgleich (Zusammenfassung)",true)]},
    ch_unterhaltsforderung:{ic:"💶",n:"Unterhaltsforderung (Alimente)",cat:"ch_familie",tier:"komplex",q:[Pe("gegenpartei","Unterhaltspflichtige Person: Name und Adresse",true),Pe("art","Art des Unterhalts",true,["Kinderunterhalt","Nachehelicher Unterhalt","Ausstehende Alimente (Rückstand)"]),Pe("betrag","Geforderter Betrag (CHF pro Monat bzw. Rückstand gesamt)",true),Pe("grundlage","Grundlage (Urteil / Konvention / Vereinbarung, Datum)",false),Pe("begruendung","Begründung (Bedarf, Einkommensverhältnisse)",true)]},
    ch_elternvereinbarung:{ic:"👨‍👩‍👧",n:"Elternvereinbarung / Betreuungsplan",cat:"ch_familie",tier:"komplex",q:[Pe("eltern","Eltern: Namen und Adressen",true),Pe("kinder","Kinder: Namen und Jahrgänge",true),Pe("betreuung","Betreuungsplan (Wochentage, Wochenenden, Übergaben)",true),Pe("ferien","Ferien- und Feiertagsregelung",true),Pe("unterhalt","Unterhaltsbeiträge (CHF, optional)",false),Pe("besonderes","Besondere Abmachungen (Schule, Gesundheit, Reisen — optional)",false)]},
    ch_reisevollmacht_kind:{ic:"✈️",n:"Reisevollmacht für Kind",cat:"ch_familie",tier:"standard",q:[Pe("kind","Kind: Name und Geburtsdatum",true),Pe("begleitung","Begleitperson auf der Reise: Name und Ausweisnummer",true),Pe("vollmachtgeber","Sorgeberechtigte(r): Name, Adresse und Ausweisnummer",true),Pe("ziel","Reiseziel (Land / Ort)",true),Pe("daten","Reisedaten (von — bis)",true)]},
    ch_konkubinatsvertrag:{ic:"🤝",n:"Konkubinatsvertrag",cat:"ch_familie",tier:"komplex",q:[Pe("partner","Beide Partner: Namen und Adressen",true),Pe("wohnung","Gemeinsame Wohnung (Adresse, wer ist Mieter/Eigentümer?)",true),Pe("kosten","Aufteilung der Lebenshaltungskosten (Miete, Haushalt, CHF/Anteile)",true),Pe("eigentum","Eigentum und gemeinsame Anschaffungen (Inventar)",false),Pe("trennung","Regelung bei Trennung (Auszug, Aufteilung — optional)",false)]},
    ch_ehevertrag:{ic:"💍",n:"Ehevertrag (Gütertrennung, Entwurf)",cat:"ch_familie",tier:"komplex",q:[Pe("parteien","Ehegatten / Verlobte: Namen und Adressen",true),Pe("gueterstand","Gewählter Güterstand",true,["Gütertrennung","Errungenschaftsbeteiligung mit Modifikationen","Gütergemeinschaft"]),Pe("heirat","Datum der Heirat (bestehend oder geplant)",true),Pe("besonderes","Besondere Bestimmungen (optional)",false),Pe("hinweis","HINWEIS: Der Ehevertrag bedarf der öffentlichen (notariellen) Beurkundung (ZGB 184) — dieser Entwurf dient der Vorbereitung",false)]},
    ch_namensaenderung:{ic:"✍️",n:"Gesuch um Namensänderung (ZGB 30)",cat:"ch_familie",tier:"komplex",q:[Pe("amt","Zuständige kantonale Behörde (Zivilstandsaufsicht / Departement)",true),Pe("name_alt","Bisheriger Name",true),Pe("name_neu","Gewünschter Name",true),Pe("gruende","Achtenswerte Gründe für die Namensänderung",true),Pe("daten","Ihr Name, Adresse, Geburtsdatum und Heimatort / Nationalität",true)]},

    /* Verträge */
    ch_mietvertrag:{ic:"🏠",n:"Mietvertrag Wohnung (Entwurf)",cat:"ch_vertraege",tier:"komplex",q:[Pe("vermieter","Vermieter: Name und Adresse",true),Pe("mieter","Mieter: Name(n) und Adresse",true),Pe("objekt","Mietobjekt (Adresse, Zimmerzahl, Stockwerk, Nebenräume)",true),Pe("mietzins","Nettomietzins (CHF) und Nebenkosten (akonto / pauschal, CHF)",true),Pe("beginn","Mietbeginn und Kündigungsfrist / -termine",true),Pe("depot","Mietzinsdepot (CHF, max. 3 Monatsmieten — optional)",false)]},
    ch_untermietvertrag:{ic:"🔑",n:"Untermietvertrag",cat:"ch_vertraege",tier:"standard",q:[Pe("hauptmieter","Hauptmieter: Name und Adresse",true),Pe("untermieter","Untermieter: Name und Adresse",true),Pe("objekt","Untermietobjekt (ganze Wohnung / Zimmer, Adresse)",true),Pe("mietzins","Untermietzins (CHF) inkl. / exkl. Nebenkosten",true),Pe("dauer","Dauer (befristet bis … / unbefristet, Kündigungsfrist)",true),Pe("zustimmung","Zustimmung des Vermieters (OR 262)",true,["Liegt vor","Ist beantragt"])]},
    ch_wg_vertrag:{ic:"🏡",n:"WG-Vertrag (Wohngemeinschaft)",cat:"ch_vertraege",tier:"standard",q:[Pe("bewohner","Alle Bewohner: Namen",true),Pe("wohnung","Wohnung: Adresse und Mietverhältnis (wer steht im Mietvertrag?)",true),Pe("kosten","Aufteilung von Miete und Nebenkosten (CHF / Anteile)",true),Pe("kasse","Gemeinsame Anschaffungen / Haushaltskasse (optional)",false),Pe("auszug","Regelung bei Auszug (Frist, Nachmietersuche — optional)",false)]},
    ch_autokaufvertrag:{ic:"🚗",n:"Autokaufvertrag (Occasion)",cat:"ch_vertraege",tier:"standard",q:[Pe("rolle","Sie sind",true,["Verkäufer","Käufer"]),Pe("gegenpartei","Gegenpartei: Name und Adresse",true),Pe("fahrzeug","Fahrzeug (Marke / Modell / Jahrgang / Kilometerstand / Stammnummer)",true),Pe("preis","Kaufpreis (CHF)",true),Pe("mfk","Letzte MFK-Prüfung (Datum, optional)",false),Pe("gewaehr","Gewährleistung",true,["Mit Garantie","Gekauft wie gesehen (Gewährleistung wegbedungen)"])]},
    ch_arbeitsvertrag:{ic:"💼",n:"Arbeitsvertrag (Entwurf)",cat:"ch_vertraege",tier:"komplex",q:[Pe("arbeitgeber","Arbeitgeber: Name und Adresse",true),Pe("arbeitnehmer","Arbeitnehmer: Name und Adresse",true),Pe("funktion","Funktion / Tätigkeit und Arbeitsort",true),Pe("lohn","Lohn (CHF pro Monat, 13. Monatslohn ja/nein)",true),Pe("pensum","Pensum (%) und Stellenantritt",true),Pe("konditionen","Probezeit, Ferien, Kündigungsfrist (optional, sonst OR-Standard)",false)]},
    ch_gebrauchsleihe:{ic:"🔄",n:"Gebrauchsleihe / Leihvertrag (OR 305)",cat:"ch_vertraege",tier:"einfach",q:[Pe("verleiher","Verleiher: Name und Adresse",true),Pe("entlehner","Entlehner: Name und Adresse",true),Pe("sache","Geliehene Sache (Beschreibung, Zustand)",true),Pe("dauer","Dauer / Rückgabetermin",true)]},
    ch_zession:{ic:"✍️",n:"Abtretungserklärung (Zession, OR 164)",cat:"ch_vertraege",tier:"standard",q:[Pe("zedent","Abtretender Gläubiger (Zedent): Name und Adresse",true),Pe("zessionar","Neuer Gläubiger (Zessionar): Name und Adresse",true),Pe("forderung","Abgetretene Forderung (CHF und Rechtsgrund)",true),Pe("schuldner","Schuldner der Forderung: Name und Adresse",true),Pe("gegenleistung","Gegenleistung / Grund der Abtretung (optional)",false)]},
    ch_aufhebungsvereinbarung:{ic:"🤝",n:"Aufhebungsvereinbarung Arbeitsverhältnis",cat:"ch_vertraege",tier:"komplex",q:[Pe("arbeitgeber","Arbeitgeber: Name und Adresse",true),Pe("arbeitnehmer","Arbeitnehmer: Name und Adresse",true),Pe("ende","Beendigungsdatum des Arbeitsverhältnisses",true),Pe("leistungen","Vereinbarte Leistungen (Abgangsentschädigung, Ferien- / Überstundenabgeltung, Bonus — CHF)",true),Pe("freistellung","Freistellung bis Vertragsende? (optional)",false),Pe("zeugnis","Vereinbarung zum Arbeitszeugnis (optional)",false)]},

    /* Wohnen & Miete */
    ch_mietzins_hinterlegung:{ic:"🏦",n:"Mietzinshinterlegung ankündigen (OR 259g)",cat:"ch_wohnen",tier:"komplex",q:[Pe("vermieter","Vermieter / Verwaltung: Name und Adresse",true),Pe("adresse","Adresse der Mietwohnung",true),Pe("mangel","Mangel und bisherige Aufforderungen zur Behebung (Daten)",true),Pe("frist","Angesetzte letzte Frist zur Behebung (z.B. 30 Tage)",true),Pe("stelle","Kantonale Hinterlegungsstelle (falls bekannt, optional)",false),Pe("daten","Ihr Name",true)]},
    ch_untervermietung_zustimmung:{ic:"🔑",n:"Gesuch um Zustimmung zur Untervermietung",cat:"ch_wohnen",tier:"standard",q:[Pe("vermieter","Vermieter / Verwaltung: Name und Adresse",true),Pe("adresse","Adresse der Mietwohnung",true),Pe("untermieter","Untermieter: Name und Adresse",true),Pe("konditionen","Untermietzins (CHF) und Dauer der Untervermietung",true),Pe("grund","Grund (z.B. Auslandaufenthalt, optional)",false)]},
    ch_nk_belege:{ic:"🧾",n:"Einsicht in Nebenkostenbelege verlangen (OR 257b)",cat:"ch_wohnen",tier:"einfach",q:[Pe("vermieter","Vermieter / Verwaltung: Name und Adresse",true),Pe("adresse","Adresse der Mietwohnung",true),Pe("periode","Abrechnungsperiode",true),Pe("termin","Gewünschter Termin für die Belegeinsicht (optional)",false),Pe("daten","Ihr Name",true)]},
    ch_rueckgabeprotokoll_einwand:{ic:"📋",n:"Einwand gegen Rückgabeprotokoll (Wohnungsabgabe)",cat:"ch_wohnen",tier:"standard",q:[Pe("vermieter","Vermieter / Verwaltung: Name und Adresse",true),Pe("adresse","Adresse der ehemaligen Mietwohnung",true),Pe("abgabe","Datum der Wohnungsabgabe / des Protokolls",true),Pe("positionen","Bestrittene Positionen (Schäden, Kosten)",true),Pe("begruendung","Begründung (normale Abnutzung, Lebensdauer-Tabelle, fehlende Unterschrift)",true),Pe("daten","Ihr Name und neue Adresse",true)]},
    ch_anfangsmietzins:{ic:"📈",n:"Anfechtung Anfangsmietzins (OR 270)",cat:"ch_wohnen",tier:"komplex",q:[Pe("behoerde","Zuständige Schlichtungsbehörde in Mietsachen (Ort)",true),Pe("vermieter","Vermieter / Verwaltung: Name und Adresse",true),Pe("adresse","Adresse der Mietwohnung",true),Pe("mietzins","Anfangsmietzins (CHF) und Vormiete (falls bekannt, amtliches Formular)",true),Pe("gruende","Gründe (persönliche / familiäre Notlage, Wohnungsnot, missbräuchlicher Mietzins — Frist: 30 Tage nach Übernahme)",true),Pe("daten","Ihr Name",true)]},

    /* Arbeit & Beruf */
    ch_zwischenzeugnis:{ic:"📜",n:"Zwischenzeugnis verlangen (OR 330a)",cat:"ch_arbeit",tier:"einfach",q:[Pe("arbeitgeber","Arbeitgeber / HR: Name und Adresse",true),Pe("funktion","Ihre Funktion und Eintrittsdatum",true),Pe("anlass","Anlass (z.B. Vorgesetztenwechsel, Bewerbung — optional)",false),Pe("daten","Ihr Name",true)]},
    ch_zeugnis_berichtigung:{ic:"⚖️",n:"Einsprache gegen Arbeitszeugnis (Berichtigung)",cat:"ch_arbeit",tier:"standard",q:[Pe("arbeitgeber","Arbeitgeber / HR: Name und Adresse",true),Pe("zeugnis","Zeugnis: Datum des Erhalts",true),Pe("beanstandung","Beanstandete Formulierungen / Lücken",true),Pe("aenderung","Gewünschte Formulierung / Korrektur",true),Pe("daten","Ihr Name, Funktion und Anstellungsdauer",true)]},
    ch_ferienantrag:{ic:"🌴",n:"Ferienantrag",cat:"ch_arbeit",tier:"einfach",q:[Pe("arbeitgeber","Arbeitgeber / Vorgesetzte(r)",true),Pe("zeitraum","Gewünschter Zeitraum (von — bis)",true),Pe("tage","Anzahl Ferientage (optional)",false),Pe("daten","Ihr Name und Funktion",true)]},
    ch_sabbatical:{ic:"🧳",n:"Gesuch um unbezahlten Urlaub / Sabbatical (ausführlich)",cat:"ch_arbeit",tier:"standard",q:[Pe("arbeitgeber","Arbeitgeber / Vorgesetzte(r): Name",true),Pe("zeitraum","Gewünschter Zeitraum (von — bis)",true),Pe("vorhaben","Vorhaben / Begründung (Weiterbildung, Reise, Familie)",true),Pe("vertretung","Vorschlag für Stellvertretung / Übergabe (optional)",false),Pe("versicherung","Regelung Versicherungen / BVG während des Urlaubs ansprechen? (optional)",false,["Ja","Nein"]),Pe("daten","Ihr Name und Funktion",true)]},
    ch_lohnpfaendung_stellungnahme:{ic:"💵",n:"Stellungnahme zur Lohnpfändung (Existenzminimum)",cat:"ch_arbeit",tier:"komplex",q:[Pe("betreibungsamt","Betreibungsamt: Ort",true),Pe("pfaendung","Pfändungs- / Betreibungs-Nr. und Datum der Pfändungsankündigung",true),Pe("einkommen","Monatliches Nettoeinkommen (CHF)",true),Pe("auslagen","Auslagen (Miete, Krankenkassenprämien, Berufsauslagen, Unterhaltspflichten — CHF)",true),Pe("antrag","Antrag (Anpassung des Existenzminimums / der Pfändungsquote)",true),Pe("daten","Ihr Name und Adresse",true)]},

    /* Konsum & Streitigkeiten */
    ch_dsg_auskunft:{ic:"🔒",n:"Datenauskunft nach DSG (Art. 25)",cat:"ch_konsum",tier:"standard",q:[Pe("firma","Verantwortliche Firma / Organisation: Name und Adresse",true),Pe("beziehung","Ihre Beziehung (Kunde, Bewerber, ehemaliger Mitarbeiter …)",true),Pe("umfang","Umfang der Auskunft",true,["Alle Daten über mich","Bestimmte Daten (unten präzisieren)"]),Pe("praezisierung","Präzisierung (falls bestimmte Daten, optional)",false),Pe("daten","Ihr Name, Adresse und Geburtsdatum (ID-Kopie beilegen)",true)]},
    ch_inkasso_bestreitung:{ic:"🛑",n:"Bestreitung Inkassoforderung",cat:"ch_konsum",tier:"standard",q:[Pe("inkasso","Inkassobüro: Name und Adresse",true),Pe("referenz","Dossier- / Referenznummer",true),Pe("forderung","Geltend gemachte Forderung (CHF) und angeblicher Grund",true),Pe("bestreitung","Warum bestreiten Sie die Forderung (ganz / teilweise, inkl. Inkassogebühren)?",true),Pe("daten","Ihr Name und Adresse",true)]},
    ch_fortsetzungsbegehren:{ic:"📮",n:"Fortsetzungsbegehren (Betreibung, SchKG 88)",cat:"ch_konsum",tier:"standard",q:[Pe("betreibungsamt","Betreibungsamt: Ort",true),Pe("betreibungsnr","Betreibungs-Nr. gemäss Zahlungsbefehl",true),Pe("zustellung","Zustellung des Zahlungsbefehls (Datum; kein Rechtsvorschlag oder Rechtsvorschlag beseitigt)",true),Pe("forderung","Forderung (CHF) und Zins",true),Pe("daten","Gläubiger (Sie): Name und Adresse",true)]},
    ch_betreibung_loeschung:{ic:"🧹",n:"Gesuch um Löschung / Nichtbekanntgabe einer Betreibung (SchKG 8a)",cat:"ch_konsum",tier:"standard",q:[Pe("betreibungsamt","Betreibungsamt: Ort",true),Pe("betreibungsnr","Betreibungs-Nr.",true),Pe("glaeubiger","Gläubiger: Name",true),Pe("grund","Grund (Forderung bezahlt / ungerechtfertigt; Gläubiger hat innert 3 Monaten kein Rechtsöffnungs- / Fortsetzungsbegehren gestellt)",true),Pe("daten","Ihr Name, Adresse und Geburtsdatum",true)]},
    ch_serafe:{ic:"📺",n:"Reklamation Serafe (Radio- / TV-Abgabe)",cat:"ch_konsum",tier:"einfach",q:[Pe("rechnungsnr","Rechnungs- / Referenznummer der Serafe-Rechnung",true),Pe("beanstandung","Beanstandung",true,["Doppelte Rechnung für denselben Haushalt","Falsche Haushaltszuordnung","Befreiung (Ergänzungsleistungen)","Anderes"]),Pe("sachverhalt","Kurze Erläuterung des Sachverhalts",true),Pe("daten","Ihr Name, Adresse und Geburtsdatum",true)]},

    /* Behörden & Ämter */
    ch_steuererlass:{ic:"🧾",n:"Erlassgesuch Steuern",cat:"ch_behoerden",tier:"komplex",q:[Pe("amt","Steueramt (Gemeinde / Kanton)",true),Pe("steuerjahr","Steuerjahr und offener Betrag (CHF)",true),Pe("situation","Finanzielle Situation (Einkommen, Vermögen, Budget)",true),Pe("gruende","Gründe der Notlage (Krankheit, Arbeitslosigkeit, Trennung …)",true),Pe("daten","Ihr Name, Adresse und AHV- / Register-Nummer",true)]},
    ch_steuern_raten:{ic:"📅",n:"Ratenzahlungsgesuch Steuern",cat:"ch_behoerden",tier:"einfach",q:[Pe("amt","Steueramt (Gemeinde / Kanton)",true),Pe("betrag","Offener Betrag (CHF) und Steuerjahr",true),Pe("raten","Vorschlag Ratenzahlung (CHF pro Monat, ab wann)",true),Pe("grund","Grund (kurz, optional)",false),Pe("daten","Ihr Name, Adresse und Register-Nummer",true)]},
    ch_militaer_verschiebung:{ic:"🎖️",n:"Verschiebungsgesuch Militär / Zivilschutz",cat:"ch_behoerden",tier:"standard",q:[Pe("stelle","Zuständige Stelle (Kreiskommando / Kommandant der Einheit)",true),Pe("dienst","Dienstanlass (WK / Kurs: Einheit und Daten des Aufgebots)",true),Pe("grund","Verschiebungsgrund (beruflich, Studium / Prüfungen, familiär — mit Belegen)",true),Pe("ersatz","Möglicher Ersatztermin (optional)",false),Pe("daten","Ihr Name, AHV-Nummer und Grad / Einteilung",true)]},
    ch_stipendium:{ic:"🎓",n:"Stipendiengesuch",cat:"ch_behoerden",tier:"standard",q:[Pe("stelle","Kantonale Stipendienstelle (Kanton)",true),Pe("ausbildung","Ausbildung (Schule / Hochschule, Studiengang, Dauer)",true),Pe("kosten","Ausbildungs- und Lebenshaltungskosten (CHF pro Jahr)",true),Pe("situation","Finanzielle Verhältnisse (eigene / Eltern, kurz)",true),Pe("daten","Ihr Name, Adresse und Geburtsdatum",true)]},
    ch_einbuergerung_begleit:{ic:"🇨🇭",n:"Begleitschreiben Einbürgerungsgesuch",cat:"ch_behoerden",tier:"standard",q:[Pe("stelle","Zuständige Stelle (Gemeinde / kantonales Amt)",true),Pe("person","Gesuchsteller(in): Name, Nationalität und Geburtsdatum",true),Pe("aufenthalt","Aufenthalt (seit wann in der Schweiz / in der Gemeinde, Bewilligung C seit)",true),Pe("integration","Integration (Sprache, Arbeit, Vereine, soziale Kontakte — kurz)",true),Pe("familie","Miteinbezogene Familienmitglieder (optional)",false)]},
    ch_fristerstreckung:{ic:"⏳",n:"Fristerstreckungsgesuch (Behörde / Gericht)",cat:"ch_behoerden",tier:"einfach",q:[Pe("stelle","Behörde / Gericht und Verfahrens- / Aktenzeichen",true),Pe("frist","Laufende Frist (Ablaufdatum) und wofür",true),Pe("neu","Beantragte neue Frist",true),Pe("grund","Begründung (Ferienabwesenheit, Krankheit, Aktenbeschaffung …)",true),Pe("daten","Ihr Name und Adresse",true)]}
  };

  function inject(){
    try{
      if(typeof DOCS==="undefined"||!DOCS) return false;
      if(typeof CATS==="undefined"||!CATS.ch_kuendigung) return false; /* CH_CH_MODULE yüklenmiş olmalı */
      for(var k in E){ if(!DOCS[k]) DOCS[k]=E[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=E[k].tier||"standard"; }
      for(var nc in NEWCATS){ if(!CATS[nc]) CATS[nc]={n:NEWCATS[nc].n,ic:NEWCATS[nc].ic,docs:[],country:"CH"}; if(!CATS[nc].docs) CATS[nc].docs=[]; }
      for(var k2 in E){ var c2=E[k2].cat; if(CATS[c2]&&CATS[c2].docs&&CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); }
      if(typeof CAT_LABELS!=="undefined"){ for(var cl in NEWCATS) CAT_LABELS[cl]=NEWCATS[cl].n; }
      if(typeof CAT_DOCS!=="undefined"){
        for(var cd in NEWCATS){ if(!CAT_DOCS[cd]) CAT_DOCS[cd]=[]; }
        for(var k3 in E){ var c3=E[k3].cat; if(CAT_DOCS[c3]){ var ex=false; for(var i=0;i<CAT_DOCS[c3].length;i++){ if(CAT_DOCS[c3][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[c3].push({k:k3,ic:E[k3].ic,t:E[k3].n,tier:E[k3].tier||"standard"}); } }
      }
      return (typeof DOCS!=="undefined" && DOCS.ch_scheidungskonvention)?true:false;
    }catch(e){ return false; }
  }

  var done=false, ticks=0;
  function run(){ if(!done){ if(inject()) done=true; } if(ticks++<40) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}
CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
if($src!==$start){ file_put_contents($file,$src); echo "Isvicre genisletme eklendi (CH_CH_EXPAND) - +36 belge, yeni kategori: Familie.\n"; }
else echo "degisiklik yok\n";
echo "\nOzet:\n";
echo "  Familie (YENI): Scheidungskonvention, Unterhaltsforderung, Elternvereinbarung, Reisevollmacht Kind, Konkubinatsvertrag, Ehevertrag, Namensaenderung (+7)\n";
echo "  Vertraege: Mietvertrag, Untermietvertrag, WG-Vertrag, Autokaufvertrag, Arbeitsvertrag, Gebrauchsleihe, Zession, Aufhebungsvereinbarung (+8)\n";
echo "  Wohnen: Mietzinshinterlegung OR 259g, Zustimmung Untervermietung, NK-Belege Einsicht, Rueckgabeprotokoll-Einwand, Anfangsmietzins-Anfechtung (+5)\n";
echo "  Arbeit: Zwischenzeugnis, Zeugnis-Berichtigung, Ferienantrag, unbezahlter Urlaub/Sabbatical, Lohnpfaendung-Stellungnahme (+5)\n";
echo "  Konsum: DSG-Datenauskunft, Inkasso-Bestreitung, Fortsetzungsbegehren, SchKG 8a Loeschung, Serafe-Reklamation (+5)\n";
echo "  Behoerden: Steuererlass, Steuern-Raten, Militaer-Verschiebung, Stipendium, Einbuergerung-Begleitschreiben, Fristerstreckung (+6)\n";
echo "\nStripe: tum belgeler tier'li -> odeme otomatik. Belge dili/hukuku CC=CH ile otomatik Almanca/Isvicre hukuku.\n";
echo "SONRA: opcache-reset.php. SIL: rm apply-schweiz-expand.php\n";
echo "Test: CH sec -> 'Familie' kategorisi gorunmeli; Vertraege'de 'Autokaufvertrag (Occasion)' gorunmeli.\n";
