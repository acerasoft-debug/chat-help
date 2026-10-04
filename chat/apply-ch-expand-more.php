<?php
/** ChatHelp — apply-ch-expand-more (CH_CH_EXPAND_MORE) — CH katalog genisletme
 *  Workflow ile uretildi: +16 belge, 4 kategori. Deferred injection.
 *  KULLANIM: pull-updates.php?files=apply-ch-expand-more.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-ch-expand-more BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_CH_EXPAND_MORE')!==false) exit("Zaten ekli (CH_CH_EXPAND_MORE).\n");
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if (strpos($src,$anchor)===false) exit("HATA: anchor (getDocPrice) yok.\n");
$js = <<<'CHJS'

/* CH_CH_EXPAND_MORE — CH katalog genisletme (workflow, deferred) */
try{(function(){
  function Pe(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var NEWCATS={
    "ch_erbrecht":{n:"Erbrecht & Vorsorge",ic:"⚜️"},
    "ch_nachbarschaft":{n:"Nachbarschaft & Eigentum",ic:"🏡"},
    "ch_sozialrecht":{n:"Sozialversicherung & Gesundheit",ic:"🩺"},
    "ch_digital":{n:"Daten & Digitales",ic:"🔐"}
  };
  var E={
    "chx_testament_eigenhaendig":{ic:"📜",n:"Eigenhändiges Testament (letztwillige Verfügung, ZGB 505)",cat:"ch_erbrecht",tier:"komplex",q:[Pe("erblasser","Erblasser(in): Name, Adresse, Geburtsdatum und Heimatort",true),Pe("erben","Erben und Vermächtnisnehmer (Namen, Verhältnis, jeweiliger Anteil bzw. Vermächtnis)",true),Pe("pflichtteil","Sind pflichtteilsgeschützte Erben vorhanden (Ehegatte/eingetragene(r) Partner(in), Nachkommen)?",true,["Ja","Nein","Unsicher"]),Pe("widerruf","Sollen frühere Testamente/Erbverträge widerrufen werden?",false,["Ja, alle früheren Verfügungen widerrufen","Nein","Keine früheren Verfügungen"]),Pe("willensvollstrecker","Willensvollstrecker einsetzen? (Name und Adresse, sonst 'keiner')",false),Pe("besonderes","Besondere Anordnungen (Teilungsvorschriften, Auflagen, Bestattungswünsche)",false),Pe("hinweis","HINWEIS: Das eigenhändige Testament muss von Anfang bis Ende von Hand geschrieben, mit Datum (Jahr, Monat, Tag) versehen und unterschrieben sein (ZGB 505) – dieser Entwurf dient nur der Vorbereitung",false)]},
    "chx_erbausschlagung":{ic:"✋",n:"Ausschlagung der Erbschaft (ZGB 566 ff.)",cat:"ch_erbrecht",tier:"standard",q:[Pe("behoerde","Zuständige Behörde (Bezirksgericht / Erbschaftsamt am letzten Wohnsitz der verstorbenen Person)",true),Pe("verstorbene","Verstorbene Person: Name, Todestag und letzter Wohnsitz",true),Pe("verhaeltnis","Ihr Verwandtschaftsverhältnis zur verstorbenen Person",true),Pe("kenntnis","Datum, an dem Sie vom Erbfall Kenntnis erhielten (Ausschlagungsfrist: 3 Monate, ZGB 567)",true),Pe("grund","Grund der Ausschlagung (z.B. Überschuldung des Nachlasses – optional)",false),Pe("daten","Ihr Name, Adresse und Geburtsdatum",true)]},
    "chx_vorsorgeauftrag":{ic:"🧭",n:"Vorsorgeauftrag (ZGB 360 ff.)",cat:"ch_erbrecht",tier:"komplex",q:[Pe("auftraggeber","Auftraggeber(in): Name, Adresse und Geburtsdatum",true),Pe("beauftragte","Beauftragte Person(en): Name, Adresse und Verhältnis zu Ihnen",true),Pe("bereiche","Umfasste Bereiche",true,["Personensorge","Vermögenssorge","Vertretung im Rechtsverkehr","Alle Bereiche"]),Pe("ersatz","Ersatzperson, falls die beauftragte Person verhindert ist (optional)",false),Pe("entschaedigung","Entschädigung und Spesenersatz für die beauftragte Person (optional)",false),Pe("hinweis","HINWEIS: Der Vorsorgeauftrag ist entweder vollständig eigenhändig zu errichten (handschriftlich, mit Datum und Unterschrift) oder öffentlich zu beurkunden; wirksam wird er erst nach Validierung durch die KESB (ZGB 360 ff.)",false)]},
    "chx_patientenverfuegung":{ic:"🩺",n:"Patientenverfügung (ZGB 370 ff.)",cat:"ch_erbrecht",tier:"standard",q:[Pe("verfasser","Verfasser(in): Name, Adresse und Geburtsdatum",true),Pe("vertretung","Vertretungsberechtigte Person für medizinische Entscheide (Name und Kontakt – optional, aber empfohlen)",false),Pe("massnahmen","Gewünschter Umfang lebenserhaltender Massnahmen",true,["Alle medizinisch sinnvollen Massnahmen","Verzicht auf lebensverlängernde Massnahmen in aussichtsloser Lage","Individuell (unten beschreiben)"]),Pe("wuensche","Persönliche Wertvorstellungen und konkrete Wünsche (z.B. Reanimation, künstliche Ernährung, Palliative Care)",true),Pe("organspende","Haltung zur Organspende (optional)",false),Pe("hinweis","HINWEIS: Die Patientenverfügung ist schriftlich zu errichten, zu datieren und zu unterzeichnen (ZGB 371)",false)]},
    "chx_erbteilungsvertrag":{ic:"📑",n:"Erbteilungsvertrag (ZGB 634)",cat:"ch_erbrecht",tier:"komplex",q:[Pe("erblasser","Erblasser(in): Name, Todestag und letzter Wohnsitz",true),Pe("erben","Alle Erben: Namen, Adressen und Erbquoten",true),Pe("nachlass","Nachlassvermögen (Liegenschaften, Konten, Wertsachen, Schulden – Inventar)",true),Pe("verteilung","Vereinbarte Zuteilung der Vermögenswerte an die einzelnen Erben",true),Pe("ausgleich","Ausgleichszahlungen zwischen den Erben (CHF, optional)",false),Pe("vorbezuege","Zu berücksichtigende Vorempfänge / Ausgleichungspflichten (ZGB 626, optional)",false)]},
    "chx_laermbeschwerde_nachbar":{ic:"🔊",n:"Beschwerde wegen Lärm / Immissionen an Nachbarn (ZGB 684)",cat:"ch_nachbarschaft",tier:"einfach",q:[Pe("nachbar","Nachbar(in): Name und Adresse",true),Pe("art","Art der übermässigen Einwirkung",true,["Lärm","Rauch / Gerüche","Erschütterungen","Licht / Schattenwurf","Anderes"]),Pe("haeufigkeit","Wann und wie oft treten die Störungen auf?",true),Pe("forderung","Was verlangen Sie (Unterlassung, Rücksichtnahme) und bis wann?",true),Pe("daten","Ihr Name und Adresse",true)]},
    "chx_grenzueberwuchs":{ic:"🌳",n:"Aufforderung: überragende Äste / eindringende Wurzeln zurückschneiden (ZGB 687)",cat:"ch_nachbarschaft",tier:"einfach",q:[Pe("nachbar","Nachbar(in) / Grundeigentümer(in): Name und Adresse",true),Pe("bewuchs","Betroffener Bewuchs (Äste, Wurzeln, Hecke) und wo er auf Ihr Grundstück ragt",true),Pe("beeintraechtigung","Beeinträchtigung (Schatten, Laub, Schäden – optional)",false),Pe("frist","Gesetzte angemessene Frist zum Zurückschneiden",true),Pe("daten","Ihr Name und Adresse",true)]},
    "chx_stwe_beschluss_anfechtung":{ic:"🏢",n:"Anfechtung eines Beschlusses der Stockwerkeigentümerversammlung (ZGB 712m i.V.m. 75)",cat:"ch_nachbarschaft",tier:"komplex",q:[Pe("verwaltung","Stockwerkeigentümergemeinschaft / Verwaltung (Gegenpartei): Name und Adresse",true),Pe("versammlung","Datum der Eigentümerversammlung und betroffenes Traktandum",true),Pe("beschluss","Angefochtener Beschluss (Wortlaut / Inhalt)",true),Pe("gruende","Anfechtungsgründe (Gesetzes- oder Reglementsverstoss, formelle Mängel)",true),Pe("antrag","Ihr Antrag (Aufhebung des Beschlusses)",true),Pe("daten","Ihr Name und Miteigentumsanteil / Stockwerkeinheit",true),Pe("hinweis","HINWEIS: Die Anfechtung erfolgt mit Klage beim zuständigen Gericht innert 1 Monat seit Kenntnis des Beschlusses (ZGB 75) – dieser Entwurf dient der Vorbereitung",false)]},
    "chx_grunddienstbarkeit":{ic:"🚧",n:"Vereinbarung Wegrecht / Grunddienstbarkeit (ZGB 730 ff.)",cat:"ch_nachbarschaft",tier:"komplex",q:[Pe("parteien","Beteiligte Grundeigentümer: Namen und Adressen",true),Pe("grundstuecke","Berechtigtes und belastetes Grundstück (Adresse, Grundbuch- / Parzellennummer)",true),Pe("inhalt","Inhalt der Dienstbarkeit (z.B. Fuss- und Fahrwegrecht, Leitungsrecht, Bauverbot)",true),Pe("umfang","Umfang und Ausübung (Lage, Breite, Nutzungszeiten)",true),Pe("entschaedigung","Entschädigung und Unterhaltskosten (CHF, optional)",false),Pe("hinweis","HINWEIS: Die Errichtung bedarf der öffentlichen Beurkundung und des Grundbucheintrags (ZGB 732) – dieser Entwurf dient der Vorbereitung",false)]},
    "chx_kk_leistung_einsprache":{ic:"🏥",n:"Einsprache gegen eine Verfügung der Krankenkasse (KVG / ATSG 52)",cat:"ch_sozialrecht",tier:"standard",q:[Pe("kasse","Krankenkasse: Name und Adresse",true),Pe("versichertennr","Versicherten- / Policennummer",true),Pe("abrechnung","Beanstandete Verfügung der Krankenkasse (Datum und Betrag in CHF)",true),Pe("grund","Grund der Einsprache (Leistung zu Unrecht verweigert, falsch berechnet, Franchise / Selbstbehalt)",true),Pe("forderung","Was verlangen Sie (Kostenübernahme / Neuberechnung, CHF)?",true),Pe("frist","Datum der Zustellung der Verfügung (Einsprachefrist: 30 Tage, ATSG 52)",true),Pe("daten","Ihr Name, Adresse und Geburtsdatum",true)]},
    "chx_iv_verfuegung_einsprache":{ic:"♿",n:"Einwand gegen den Vorbescheid der IV-Stelle (Art. 57a IVG)",cat:"ch_sozialrecht",tier:"komplex",q:[Pe("stelle","IV-Stelle: Kanton und Adresse",true),Pe("vorbescheid","Angefochtener Vorbescheid (Datum und Verfügungs- / Geschäftsnummer) – Einwandfrist: 30 Tage seit Zustellung (Art. 57a IVG)",true),Pe("entscheid","Was stellt die IV-Stelle in Aussicht (Rentenablehnung / -kürzung, abgelehnte Eingliederungsmassnahme)?",true),Pe("begruendung","Ihre Begründung (Gesundheitszustand, Arztberichte, Arbeitsunfähigkeit)",true),Pe("antrag","Ihr Antrag (Rentenanspruch, Weiterführung der Massnahme, ergänzende medizinische Abklärungen)",true),Pe("daten","Ihr Name, Adresse und AHV-Nummer",true)]},
    "chx_haftpflicht_forderung":{ic:"🚑",n:"Schadenersatzforderung an Haftpflichtversicherung (OR 41)",cat:"ch_sozialrecht",tier:"standard",q:[Pe("gegenpartei","Haftpflichtversicherung bzw. Schädiger: Name und Adresse",true),Pe("ereignis","Schadenereignis (Datum, Ort und Hergang)",true),Pe("schaden","Erlittener Schaden (Heilungskosten, Sachschaden, Erwerbsausfall – CHF)",true),Pe("haftung","Verschulden bzw. Haftungsgrund der Gegenseite",true),Pe("belege","Vorhandene Belege (Polizeirapport, Arztzeugnisse, Rechnungen – optional)",false),Pe("daten","Ihr Name, Adresse und Bankverbindung für die Zahlung",true)]},
    "chx_ergaenzungsleistungen":{ic:"💰",n:"Antrag auf Ergänzungsleistungen (ELG)",cat:"ch_sozialrecht",tier:"standard",q:[Pe("stelle","Zuständige Ausgleichskasse / EL-Stelle (Kanton bzw. Gemeinde)",true),Pe("person","Antragsteller(in): Name, Adresse und AHV-Nummer",true),Pe("rente","Bezogene Rente (AHV / IV) und monatlicher Betrag (CHF)",true),Pe("einnahmen","Einkommen und Vermögen (Renten, Zinsen, Vermögen – CHF)",true),Pe("ausgaben","Anerkannte Ausgaben (Miete, Krankenkassenprämie, Heimkosten – CHF)",true)]},
    "chx_dsg_loeschung":{ic:"🗑️",n:"Löschung persönlicher Daten verlangen (DSG 32)",cat:"ch_digital",tier:"standard",q:[Pe("firma","Verantwortliche Firma / Plattform: Name und Adresse",true),Pe("beziehung","Ihre Beziehung (Kunde, Nutzerkonto, ehemalige(r) Mitarbeiter(in) …)",true),Pe("umfang","Zu löschende Daten (ganzes Konto, Profil, bestimmte Einträge)",true),Pe("grund","Grund",true,["Einwilligung widerrufen","Daten nicht mehr erforderlich","Unrechtmässige Bearbeitung","Anderes"]),Pe("bestaetigung","Schriftliche Bestätigung der Löschung verlangen?",false,["Ja","Nein"]),Pe("daten","Ihr Name, Adresse und Kontoangaben (E-Mail / Kundennummer)",true)]},
    "chx_persoenlichkeitsverletzung_online":{ic:"🚫",n:"Aufforderung zur Löschung rechtsverletzender Online-Inhalte (ZGB 28)",cat:"ch_digital",tier:"standard",q:[Pe("stoerer","Verantwortliche Person / Plattform / Betreiber: Name und Adresse",true),Pe("inhalt","Beanstandeter Inhalt (URL, Beschreibung, Datum der Veröffentlichung)",true),Pe("verletzung","Worin liegt die Persönlichkeitsverletzung (falsche Tatsachen, Rufschädigung, Bildrechte, Privatsphäre)?",true),Pe("forderung","Ihre Forderung (Löschung, Berichtigung, Unterlassung, Gegendarstellung)",true),Pe("frist","Gesetzte Frist zur Behebung",true),Pe("daten","Ihr Name und Adresse",true)]},
    "chx_bonitaet_berichtigung":{ic:"📉",n:"Berichtigung / Löschung eines Bonitätseintrags (DSG)",cat:"ch_digital",tier:"standard",q:[Pe("stelle","Auskunftei: Name und Adresse (z.B. ZEK, CRIF, Intrum)",true),Pe("eintrag","Beanstandeter Eintrag (Inhalt, Datum, Gläubiger)",true),Pe("fehler","Warum ist der Eintrag falsch oder unzulässig (bezahlt, bestritten, veraltet)?",true),Pe("forderung","Was verlangen Sie (Berichtigung, Löschung, Sperrung)?",true),Pe("daten","Ihr Name, Adresse und Geburtsdatum",true)]}
  };
  function inject(){
    try{
      if(typeof DOCS==="undefined"||!DOCS) return false;
      if(typeof CATS==="undefined"||!CATS) return false;
      for(var k in E){ if(!DOCS[k]) DOCS[k]=E[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=E[k].tier||"standard"; }
      for(var nc in NEWCATS){ if(!CATS[nc]) CATS[nc]={n:NEWCATS[nc].n,ic:NEWCATS[nc].ic,docs:[],country:"CH"}; if(!CATS[nc].docs) CATS[nc].docs=[]; }
      for(var k2 in E){ var c2=E[k2].cat; if(CATS[c2]&&CATS[c2].docs&&CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); }
      if(typeof CAT_LABELS!=="undefined"){ for(var cl in NEWCATS) CAT_LABELS[cl]=NEWCATS[cl].n; }
      if(typeof CAT_DOCS!=="undefined"){
        for(var cd in NEWCATS){ if(!CAT_DOCS[cd]) CAT_DOCS[cd]=[]; }
        for(var k3 in E){ var c3=E[k3].cat; if(CAT_DOCS[c3]){ var ex=false; for(var i=0;i<CAT_DOCS[c3].length;i++){ if(CAT_DOCS[c3][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[c3].push({k:k3,ic:E[k3].ic,t:E[k3].n,tier:E[k3].tier||"standard"}); } }
      }
      return !!(DOCS.chx_testament_eigenhaendig);
    }catch(e){ return false; }
  }
  var done=false, ticks=0;
  function run(){ if(!done){ if(inject()) done=true; } if(ticks++<40) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}

CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
$tmp = tempnam(sys_get_temp_dir(),'ch').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-chmore-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "  ✓ CH: +16 belge, 4 kategori eklendi\n\n✓ CH_CH_EXPAND_MORE uygulandi.\n";
