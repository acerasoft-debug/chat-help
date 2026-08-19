<?php
/** ChatHelp — apply-de-expand-more (CH_DE_EXPAND_MORE) — DE katalog genisletme
 *  Workflow ile uretildi: +18 belge, 4 kategori. Deferred injection.
 *  KULLANIM: pull-updates.php?files=apply-de-expand-more.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-de-expand-more BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_DE_EXPAND_MORE')!==false) exit("Zaten ekli (CH_DE_EXPAND_MORE).\n");
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if (strpos($src,$anchor)===false) exit("HATA: anchor (getDocPrice) yok.\n");
$js = <<<'CHJS'

/* CH_DE_EXPAND_MORE — DE katalog genisletme (workflow, deferred) */
try{(function(){
  function Pe(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var NEWCATS={
    "dex_erbrecht":{n:"Erbrecht & Vorsorge",ic:"📜"},
    "dex_weg":{n:"WEG & Eigentümergemeinschaft",ic:"🏢"},
    "dex_sozialrecht":{n:"Sozialleistungen & Widerspruch",ic:"🧾"},
    "dex_verbraucher":{n:"Verbraucherschutz, Nachbarn & Vereine",ic:"🛡️"}
  };
  var E={
    "dex_erbausschlagung":{ic:"📜",n:"Erbausschlagung",cat:"dex_erbrecht",tier:"komplex",q:[Pe("antragsteller_name","Name und Anschrift der ausschlagenden Person",true),Pe("erblasser_name","Name des Erblassers/der Erblasserin",true),Pe("sterbedatum","Sterbedatum",true),Pe("verwandtschaft","Verwandtschaftsverhältnis zum Erblasser",true),Pe("erbgrund","Grund der Ausschlagung",false,["Überschuldeter Nachlass","Unbekannter Nachlasswert","Persönliche Gründe","Sonstiges"]),Pe("kenntnis_datum","Datum der Kenntnis vom Erbfall (Ausschlagungsfrist beginnt hier)",true),Pe("nachlassgericht","Zuständiges Nachlassgericht",true)]},
    "dex_erbschein_antrag":{ic:"🧾",n:"Antrag auf Erbschein",cat:"dex_erbrecht",tier:"komplex",q:[Pe("antragsteller_name","Name und Anschrift des Antragstellers/der Antragstellerin",true),Pe("erblasser_daten","Name, letzter Wohnsitz und Sterbedatum des Erblassers",true),Pe("erbfolge_art","Art der Erbfolge",true,["Gesetzliche Erbfolge","Testament vorhanden","Erbvertrag vorhanden"]),Pe("erbquote","Ihre Erbquote (z.B. 1/2)",true),Pe("miterben","Namen weiterer Miterben",false),Pe("verwendungszweck","Wofür wird der Erbschein benötigt (z.B. Grundbuch, Bank)",false)]},
    "dex_pflichtteilsanspruch":{ic:"⚖️",n:"Geltendmachung des Pflichtteilsanspruchs",cat:"dex_erbrecht",tier:"standard",q:[Pe("antragsteller_name","Name und Anschrift des/der Pflichtteilsberechtigten",true),Pe("erblasser_name","Name des Erblassers/der Erblasserin und Sterbedatum",true),Pe("verwandtschaft","Verwandtschaftsverhältnis (z.B. Kind, Ehegatte)",true),Pe("status","Wurden Sie durch Testament enterbt oder auf den Pflichtteil gesetzt?",true,["Ja, enterbt","Auf den Pflichtteil gesetzt","Unsicher"]),Pe("erbe_ansprechpartner","Name und Anschrift des/der Erben",true),Pe("auskunft_gewuenscht","Zusätzlich Auskunft über den Nachlassbestand verlangen?",false,["Ja","Nein"])]},
    "dex_testament_privat":{ic:"✍️",n:"Eigenhändiges Testament (privatschriftlich)",cat:"dex_erbrecht",tier:"standard",q:[Pe("testierender_name","Vollständiger Name und Geburtsdatum",true),Pe("familienstand","Familienstand",true,["Ledig","Verheiratet","Verwitwet","Geschieden","Eingetragene Lebenspartnerschaft"]),Pe("erben","Wer soll erben (Namen, Anteile)?",true),Pe("vermaechtnisse","Sollen einzelne Gegenstände als Vermächtnis zugewiesen werden?",false),Pe("testamentsvollstrecker","Soll ein Testamentsvollstrecker benannt werden?",false,["Ja","Nein"]),Pe("enterbung","Sollen bestimmte gesetzliche Erben ausdrücklich enterbt werden?",false)]},
    "dex_vorsorgevollmacht":{ic:"🤝",n:"Vorsorgevollmacht",cat:"dex_erbrecht",tier:"standard",q:[Pe("vollmachtgeber","Name und Geburtsdatum des Vollmachtgebers/der Vollmachtgeberin",true),Pe("bevollmaechtigter","Name und Anschrift der bevollmächtigten Person",true),Pe("ersatzbevollmaechtigter","Soll eine Ersatzperson benannt werden?",false),Pe("umfang","Umfang der Vollmacht",true,["Gesundheitssorge","Vermögenssorge","Aufenthaltsbestimmung","Alle Bereiche"]),Pe("registrierung","Registrierung im Zentralen Vorsorgeregister gewünscht?",false,["Ja","Nein"])]},
    "dex_patientenverfuegung":{ic:"🏥",n:"Patientenverfügung",cat:"dex_erbrecht",tier:"standard",q:[Pe("person_name","Vollständiger Name und Geburtsdatum",true),Pe("situationen","Für welche Situationen soll die Verfügung gelten?",true,["Unumkehrbarer Sterbeprozess","Dauerhafter Bewusstseinsverlust","Fortgeschrittene Demenz","Alle genannten Situationen"]),Pe("lebenserhaltende_massnahmen","Wie soll mit lebenserhaltenden Maßnahmen verfahren werden?",true),Pe("bevollmaechtigte_person","Name der bevollmächtigten Vertrauensperson (falls vorhanden)",false),Pe("hausarzt","Name des Hausarztes/der Hausärztin",false)]},
    "dex_weg_anfechtungsklage":{ic:"⚖️",n:"Anfechtungsklage gegen Eigentümerversammlungsbeschluss",cat:"dex_weg",tier:"komplex",q:[Pe("klaeger_daten","Name und Anschrift des klagenden Wohnungseigentümers",true),Pe("gemeinschaft_anschrift","Anschrift der Wohnungseigentümergemeinschaft",true),Pe("versammlungsdatum","Datum der Eigentümerversammlung",true),Pe("beschluss_nummer","Betroffener Tagesordnungspunkt/Beschluss-Nr.",true),Pe("anfechtungsgrund","Grund der Anfechtung",true,["Formfehler bei Einberufung","Verstoß gegen ordnungsgemäße Verwaltung","Beschluss überschreitet Beschlusskompetenz","Sonstiger Grund"]),Pe("frist_beachtet","Liegt die Versammlung weniger als einen Monat zurück (Klagefrist)?",true,["Ja","Nein","Unsicher"])]},
    "dex_weg_einberufung_antrag":{ic:"📅",n:"Antrag auf Einberufung einer außerordentlichen Eigentümerversammlung",cat:"dex_weg",tier:"standard",q:[Pe("antragsteller","Name und Wohnungsnummer des Antragstellers",true),Pe("verwalter_name","Name des Verwalters",true),Pe("tagesordnungspunkte","Gewünschte Tagesordnungspunkte",true),Pe("unterstuetzer_anteil","Wird der Antrag von mindestens einem Viertel der Eigentümer unterstützt?",false,["Ja","Nein","Unbekannt"]),Pe("dringlichkeit","Begründung der Dringlichkeit",false)]},
    "dex_weg_verwalter_abberufung":{ic:"🚫",n:"Antrag auf Abberufung des Verwalters",cat:"dex_weg",tier:"standard",q:[Pe("antragsteller","Name und Wohnungsnummer des Antragstellers",true),Pe("verwalter_name","Name der Verwaltung/des Verwalters",true),Pe("grundlage","Grundlage der Abberufung",true,["Abberufung ohne wichtigen Grund (seit der WEG-Reform 2020 jederzeit per Mehrheitsbeschluss möglich)","Außerordentliche Abberufung aus wichtigem Grund"]),Pe("pflichtverletzungen","Falls wichtiger Grund vorliegt: welche konkreten Pflichtverletzungen werden dem Verwalter vorgeworfen?",false),Pe("nachweise","Liegen Nachweise vor (z.B. E-Mails, Protokolle)?",false,["Ja","Nein"]),Pe("neuer_verwalter","Soll gleichzeitig ein neuer Verwalter vorgeschlagen werden?",false)]},
    "dex_weg_mahnung_hausgeld":{ic:"💶",n:"Mahnung wegen rückständigen Hausgelds",cat:"dex_weg",tier:"standard",q:[Pe("absender_name","Name und Anschrift der Verwaltung bzw. der Wohnungseigentümergemeinschaft (Absender)",true),Pe("schuldner_name","Name und Wohnungsnummer des säumigen Eigentümers",true),Pe("offener_betrag","Höhe des rückständigen Hausgeldes (in Euro)",true),Pe("zeitraum","Zeitraum bzw. betroffene Monate des Rückstands",true),Pe("faelligkeit","Fälligkeitsdatum(en) der ausstehenden Zahlungen",true),Pe("verzugszinsen","Sollen Verzugszinsen und Mahnkosten geltend gemacht werden?",false,["Ja","Nein"]),Pe("zahlungsfrist","Neu gesetzte Zahlungsfrist",false)]},
    "dex_buergergeld_widerspruch":{ic:"🧾",n:"Widerspruch gegen Bürgergeld-Bescheid",cat:"dex_sozialrecht",tier:"standard",q:[Pe("antragsteller","Name und Anschrift",true),Pe("bg_nummer","Bedarfsgemeinschafts- bzw. Kundennummer",true),Pe("bescheiddatum","Datum des Bescheids",true),Pe("streitpunkt","Worum geht es im Bescheid?",true,["Höhe der Leistung","Anrechnung von Einkommen","Ablehnung des Antrags","Rückforderung","Sonstiges"]),Pe("begruendung","Begründung des Widerspruchs",true)]},
    "dex_sanktionsbescheid_widerspruch":{ic:"⚠️",n:"Widerspruch gegen Sanktions-/Leistungsminderungsbescheid des Jobcenters",cat:"dex_sozialrecht",tier:"standard",q:[Pe("antragsteller","Name und Anschrift",true),Pe("kundennummer","Kundennummer beim Jobcenter",true),Pe("sanktionsgrund","Vom Jobcenter genannter Grund der Leistungsminderung",true,["Meldeversäumnis","Pflichtverletzung Eingliederungsvereinbarung","Ablehnung zumutbarer Arbeit","Sonstiges"]),Pe("entschuldigungsgrund","Lag ein wichtiger Grund vor (z.B. Krankheit)?",false),Pe("bescheiddatum","Datum des Sanktionsbescheids",true)]},
    "dex_wohngeld_widerspruch":{ic:"🏠",n:"Widerspruch gegen Wohngeldbescheid",cat:"dex_sozialrecht",tier:"standard",q:[Pe("antragsteller","Name und Anschrift",true),Pe("aktenzeichen","Aktenzeichen des Bescheids",true),Pe("bescheiddatum","Datum des Bescheids",true),Pe("beanstandung","Was wird beanstandet?",true,["Zu niedrige Wohngeldberechnung","Falsche Haushaltsgröße berücksichtigt","Falsches Einkommen angerechnet","Ablehnung des Antrags"]),Pe("begruendung","Ausführliche Begründung",true)]},
    "dex_untaetigkeitsklage_sozialgericht":{ic:"🏛️",n:"Untätigkeitsklage beim Sozialgericht",cat:"dex_sozialrecht",tier:"komplex",q:[Pe("klaeger_daten","Name und Anschrift",true),Pe("behoerde","Betroffene Behörde (z.B. Jobcenter, Krankenkasse)",true),Pe("antragsdatum","Datum des ursprünglichen Antrags bzw. Widerspruchs",true),Pe("verfahrensart","Handelt es sich um einen unbeschiedenen Antrag oder Widerspruch?",true,["Antrag ohne Entscheidung","Widerspruch ohne Entscheidung"]),Pe("zustaendiges_gericht","Zuständiges Sozialgericht",true),Pe("grund_mitgeteilt","Wurde ein zureichender Grund für die Verzögerung mitgeteilt?",false,["Ja","Nein"])]},
    "dex_widerruf_fernabsatz":{ic:"🛒",n:"Widerruf eines Fernabsatzvertrags",cat:"dex_verbraucher",tier:"einfach",q:[Pe("verbraucher_name","Name und Anschrift",true),Pe("unternehmer_name","Name und Anschrift des Unternehmens",true),Pe("vertragsgegenstand","Vertragsgegenstand (Ware/Dienstleistung)",true),Pe("bestelldatum","Bestell- bzw. Vertragsdatum",true),Pe("bestellnummer","Bestell- oder Vertragsnummer",false),Pe("rueckerstattung_konto","Bankverbindung für die Rückerstattung angeben?",false,["Ja","Nein"])]},
    "dex_dsgvo_auskunftsersuchen":{ic:"🔍",n:"DSGVO-Auskunftsersuchen (Art. 15 DSGVO)",cat:"dex_verbraucher",tier:"einfach",q:[Pe("betroffene_person","Name und Anschrift der betroffenen Person",true),Pe("verantwortlicher","Name und Anschrift des Unternehmens/der Stelle",true),Pe("datenkategorien","Sollen bestimmte Datenkategorien besonders angefragt werden?",false),Pe("kopie_gewuenscht","Kopie der gespeicherten Daten verlangen?",false,["Ja","Nein"]),Pe("kundennummer","Kunden- oder Referenznummer (falls vorhanden)",false)]},
    "dex_nachbarschaft_grenzabstand":{ic:"🌳",n:"Aufforderungsschreiben wegen Verletzung des Grenzabstands (Bepflanzung/Bau)",cat:"dex_verbraucher",tier:"standard",q:[Pe("absender","Name und Anschrift",true),Pe("nachbar_name","Name und Anschrift des Nachbarn",true),Pe("art_verstoss","Art der Beeinträchtigung",true,["Bäume/Hecken zu nah an der Grundstücksgrenze","Bauliche Anlage ohne Grenzabstand","Überhängende Äste/Wurzeln","Sonstiges"]),Pe("bundesland","Bundesland (maßgeblich für das jeweilige Nachbarrechtsgesetz)",true),Pe("frist_zur_beseitigung","Gewünschte Frist zur Beseitigung",false)]},
    "dex_verein_mitgliederversammlung_einberufung":{ic:"🗳️",n:"Antrag auf Einberufung einer außerordentlichen Mitgliederversammlung",cat:"dex_verbraucher",tier:"standard",q:[Pe("antragsteller","Name und Mitgliedsnummer",true),Pe("verein_name","Name des Vereins",true),Pe("vorstand_adresse","Anschrift des Vorstands",true),Pe("tagesordnungspunkte","Gewünschte Tagesordnungspunkte",true),Pe("unterstuetzer_anzahl","Anzahl unterstützender Mitglieder (gesetzlich mindestens 10% der Mitglieder, sofern die Satzung nichts anderes regelt)",false),Pe("satzung_frist","Ist in der Satzung ein besonderes Quorum oder eine Frist geregelt?",false,["Ja","Nein","Unbekannt"])]}
  };
  function inject(){
    try{
      if(typeof DOCS==="undefined"||!DOCS) return false;
      if(typeof CATS==="undefined"||!CATS) return false;
      for(var k in E){ if(!DOCS[k]) DOCS[k]=E[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=E[k].tier||"standard"; }
      for(var nc in NEWCATS){ if(!CATS[nc]) CATS[nc]={n:NEWCATS[nc].n,ic:NEWCATS[nc].ic,docs:[],country:"DE"}; if(!CATS[nc].docs) CATS[nc].docs=[]; }
      for(var k2 in E){ var c2=E[k2].cat; if(CATS[c2]&&CATS[c2].docs&&CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); }
      if(typeof CAT_LABELS!=="undefined"){ for(var cl in NEWCATS) CAT_LABELS[cl]=NEWCATS[cl].n; }
      if(typeof CAT_DOCS!=="undefined"){
        for(var cd in NEWCATS){ if(!CAT_DOCS[cd]) CAT_DOCS[cd]=[]; }
        for(var k3 in E){ var c3=E[k3].cat; if(CAT_DOCS[c3]){ var ex=false; for(var i=0;i<CAT_DOCS[c3].length;i++){ if(CAT_DOCS[c3][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[c3].push({k:k3,ic:E[k3].ic,t:E[k3].n,tier:E[k3].tier||"standard"}); } }
      }
      return !!(DOCS.dex_erbausschlagung);
    }catch(e){ return false; }
  }
  var done=false, ticks=0;
  function run(){ if(!done){ if(inject()) done=true; } if(ticks++<40) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}

CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
$tmp = tempnam(sys_get_temp_dir(),'de').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-demore-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "  ✓ DE: +18 belge, 4 kategori eklendi\n\n✓ CH_DE_EXPAND_MORE uygulandi.\n";
