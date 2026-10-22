<?php
/** ChatHelp — apply-at-expand-more (CH_AT_EXPAND_MORE) — AT katalog genisletme
 *  Workflow ile uretildi: +16 belge, 4 kategori. Deferred injection.
 *  KULLANIM: pull-updates.php?files=apply-at-expand-more.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-at-expand-more BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_AT_EXPAND_MORE')!==false) exit("Zaten ekli (CH_AT_EXPAND_MORE).\n");
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if (strpos($src,$anchor)===false) exit("HATA: anchor (getDocPrice) yok.\n");
$js = <<<'CHJS'

/* CH_AT_EXPAND_MORE — AT katalog genisletme (workflow, deferred) */
try{(function(){
  function Pe(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var NEWCATS={
    "at_familie":{n:"Familie & Partnerschaft",ic:"👪"},
    "at_erben":{n:"Erben & Vorsorge",ic:"📜"},
    "at_schulden":{n:"Schulden & Inkasso",ic:"💸"},
    "at_datenschutz":{n:"Datenschutz & DSGVO",ic:"🔐"}
  };
  var E={
    "atx_scheidung_einvernehmlich":{ic:"💔",n:"Antrag auf einvernehmliche Scheidung (§ 55a EheG)",cat:"at_familie",tier:"komplex",q:[Pe("ehepartner","Ehepartner/in: Name und Adresse",true),Pe("eheschliessung","Ort und Datum der Eheschließung",true),Pe("trennung","Seit wann ist die eheliche Lebensgemeinschaft aufgehoben? (mind. 6 Monate erforderlich)",true),Pe("kinder","Gemeinsame minderjährige Kinder (Namen, Geburtsdaten) — sonst 'keine'",true),Pe("einigung","Einigung über Obsorge, Kontaktrecht, Unterhalt und Vermögensaufteilung (Kurzbeschreibung)",true),Pe("gericht","Zuständiges Bezirksgericht (Wohnsitz)",false)]},
    "atx_kindesunterhalt":{ic:"🧒",n:"Kindesunterhalt geltend machen (§ 231 ABGB)",cat:"at_familie",tier:"standard",q:[Pe("kind","Kind: Name und Geburtsdatum",true),Pe("unterhaltspflichtiger","Unterhaltspflichtige(r) Elternteil: Name und Adresse",true),Pe("betreuung","Bei wem lebt das Kind hauptsächlich?",true,["Bei mir","Beim anderen Elternteil","Wechselmodell"]),Pe("einkommen","Bekanntes Netto-Einkommen des/der Unterhaltspflichtigen (monatlich, €)",false),Pe("betrag","Geforderter monatlicher Unterhalt (€, nach Prozentsatzmethode/Regelbedarf)",true),Pe("rueckstand","Werden auch rückständige Unterhaltsbeträge gefordert? Zeitraum",false)]},
    "atx_kontaktrecht":{ic:"🤝",n:"Regelung des Kontaktrechts (Besuchsrecht, § 187 ABGB)",cat:"at_familie",tier:"standard",q:[Pe("kind","Kind: Name und Geburtsdatum",true),Pe("anderer_elternteil","Anderer Elternteil: Name und Adresse",true),Pe("aktuell","Aktuelle Kontaktregelung (falls vorhanden)",false),Pe("wunsch","Gewünschte Kontaktregelung (Wochentage, Uhrzeiten, Ferien, Feiertage)",true),Pe("konflikt","Streitpunkte oder Verweigerung des Kontakts? Kurz schildern",false),Pe("daten","Ihre Daten: Name und Adresse",true)]},
    "atx_obsorge":{ic:"👨‍👩‍👧",n:"Antrag auf Obsorge (§ 177/180 ABGB)",cat:"at_familie",tier:"komplex",q:[Pe("kind","Kind: Name und Geburtsdatum",true),Pe("anderer_elternteil","Anderer Elternteil: Name und Adresse",true),Pe("antrag","Was beantragen Sie?",true,["Alleinige Obsorge","Gemeinsame Obsorge","Entzug der Obsorge des anderen Teils","Übertragung der Obsorge an mich"]),Pe("begruendung","Begründung im Sinne des Kindeswohls (warum diese Regelung dem Wohl des Kindes dient)",true),Pe("aktuell","Derzeitige Obsorge-Situation",false),Pe("gericht","Zuständiges Bezirksgericht (Pflegschaftsgericht)",false)]},
    "atx_testament_eigenhaendig":{ic:"🖋️",n:"Eigenhändiges Testament (§ 578 ABGB)",cat:"at_erben",tier:"komplex",q:[Pe("testator","Erblasser/in: Name, Geburtsdatum und Adresse",true),Pe("erben","Erben und deren Erbteile (Namen, Anteil in %)",true),Pe("vermaechtnisse","Besondere Vermächtnisse (Legate: Gegenstand und Begünstigte(r))",false),Pe("ersatzerben","Ersatzerben (falls ein Erbe wegfällt)",false),Pe("pflichtteil","Pflichtteilsberechtigte (Kinder, Ehepartner/in) — Anmerkungen",false),Pe("hinweis","HINWEIS: Ein eigenhändiges Testament muss zur Gültigkeit zur Gänze eigenhändig geschrieben und unterschrieben sein (§ 578 ABGB)",false)]},
    "atx_pflichtteil":{ic:"⚖️",n:"Pflichtteil geltend machen (§ 756 ff ABGB)",cat:"at_erben",tier:"komplex",q:[Pe("verstorbener","Verstorbene(r): Name und Sterbedatum",true),Pe("verhaeltnis","Ihr Verwandtschaftsverhältnis zum/zur Verstorbenen",true,["Kind","Ehepartner/in","Eingetragene(r) Partner/in","Enkel/in"]),Pe("erben","Eingesetzte Erben laut Testament (Namen)",true),Pe("nachlass","Bekannter Nachlasswert / Vermögen (Schätzung, €)",false),Pe("schenkungen","Bekannte Schenkungen zu Lebzeiten (anrechenbar)",false),Pe("daten","Ihre Daten: Name und Adresse",true)]},
    "atx_vorsorgevollmacht":{ic:"🩺",n:"Vorsorgevollmacht (§ 260 ABGB)",cat:"at_erben",tier:"standard",q:[Pe("vollmachtgeber","Vollmachtgeber/in: Name, Geburtsdatum und Adresse",true),Pe("bevollmaechtigter","Bevollmächtigte Person: Name, Geburtsdatum und Adresse",true),Pe("bereiche","Welche Bereiche soll die Vollmacht umfassen? (Gesundheit, Vermögen, Wohnort, Vertretung vor Behörden)",true),Pe("wirksamkeit","Ab wann soll die Vollmacht wirksam werden (z. B. bei Verlust der Entscheidungsfähigkeit)?",false),Pe("hinweis","HINWEIS: Die Vorsorgevollmacht muss vor Rechtsanwalt, Notar oder Erwachsenenschutzverein errichtet und im ÖZVV eingetragen werden",false)]},
    "atx_patientenverfuegung":{ic:"🏥",n:"Patientenverfügung (PatVG)",cat:"at_erben",tier:"standard",q:[Pe("person","Ihre Daten: Name, Geburtsdatum und Adresse",true),Pe("ablehnung","Welche medizinischen Behandlungen lehnen Sie ab? (z. B. künstliche Beatmung, Wiederbelebung, Sondenernährung)",true),Pe("art","Art der Patientenverfügung",true,["Verbindlich","Beachtlich (hinweisend)"]),Pe("vertrauensperson","Vertrauensperson / Vorsorgebevollmächtigte(r) (Name, Kontakt)",false),Pe("hinweis","HINWEIS: Eine verbindliche Patientenverfügung erfordert ärztliche Aufklärung und Errichtung vor Rechtsanwalt, Notar oder Patientenanwaltschaft (PatVG)",false)]},
    "atx_einspruch_zahlungsbefehl":{ic:"🛑",n:"Einspruch gegen Zahlungsbefehl (Mahnverfahren, § 248 ZPO)",cat:"at_schulden",tier:"komplex",q:[Pe("gericht","Gericht und Geschäftszahl (laut Zahlungsbefehl)",true),Pe("klaeger","Klagende Partei (Gläubiger): Name und Adresse",true),Pe("zustellung","Datum der Zustellung des Zahlungsbefehls (Einspruchsfrist: 4 Wochen)",true),Pe("betrag","Geforderter Betrag (€)",true),Pe("begruendung","Warum bestreiten Sie die Forderung? (ganz oder teilweise, Begründung)",true),Pe("daten","Ihre Daten: Name und Adresse",true)]},
    "atx_ratenvereinbarung_schulden":{ic:"🗓️",n:"Ratenzahlungsvereinbarung vorschlagen",cat:"at_schulden",tier:"standard",q:[Pe("glaeubiger","Gläubiger: Name und Adresse",true),Pe("forderung","Forderung (Grund, Rechnungs-/Aktennummer)",true),Pe("gesamtbetrag","Gesamter offener Betrag (€)",true),Pe("rate","Vorgeschlagene monatliche Rate (€)",true),Pe("beginn","Ab wann können Sie zahlen? (erster Ratentermin)",false),Pe("situation","Kurze Begründung Ihrer finanziellen Situation",false)]},
    "atx_inkasso_ueberhoeht":{ic:"📉",n:"Widerspruch gegen überhöhte Inkassokosten",cat:"at_schulden",tier:"standard",q:[Pe("inkasso","Inkassobüro: Name und Adresse",true),Pe("aktenzahl","Aktenzeichen / Geschäftszahl",true),Pe("hauptforderung","Ursprüngliche Hauptforderung (€)",true),Pe("inkassokosten","Verrechnete Inkasso-/Betreibungskosten (€)",true),Pe("einwand","Ihr Einwand",true,["Kosten übersteigen die gesetzlichen Höchstsätze","Forderung wird zur Gänze bestritten","Forderung bereits bezahlt","Forderung verjährt"]),Pe("daten","Ihre Daten: Name und Adresse",true)]},
    "atx_privatkonkurs":{ic:"🏦",n:"Antrag auf Schuldenregulierungsverfahren (Privatkonkurs, IO)",cat:"at_schulden",tier:"komplex",q:[Pe("gericht","Zuständiges Bezirksgericht (Wohnsitz)",false),Pe("schulden","Gesamtschulden und Anzahl der Gläubiger (Überblick)",true),Pe("glaeubiger_liste","Wichtigste Gläubiger und jeweilige Beträge (€)",true),Pe("einkommen","Monatliches Netto-Einkommen (€)",true),Pe("vermoegen","Vorhandenes Vermögen (Ersparnisse, Fahrzeug, Immobilie ...)",false),Pe("daten","Ihre Daten: Name, Geburtsdatum und Adresse",true)]},
    "atx_dsgvo_auskunft":{ic:"📂",n:"Auskunftsersuchen (Art. 15 DSGVO)",cat:"at_datenschutz",tier:"einfach",q:[Pe("verantwortlicher","Verantwortliches Unternehmen/Stelle: Name und Adresse",true),Pe("betroffene_daten","Worüber möchten Sie Auskunft? (alle Daten oder bestimmte Kategorien)",true),Pe("kundennummer","Kunden-/Vertragsnummer (falls vorhanden)",false),Pe("daten","Ihre Daten: Name und Adresse (zur Identifikation)",true),Pe("frist","HINWEIS: Die Auskunft ist binnen 1 Monat kostenlos zu erteilen (Art. 12 DSGVO)",false)]},
    "atx_dsgvo_loeschung":{ic:"🗑️",n:"Löschungsbegehren / Recht auf Vergessenwerden (Art. 17 DSGVO)",cat:"at_datenschutz",tier:"standard",q:[Pe("verantwortlicher","Verantwortliches Unternehmen/Stelle: Name und Adresse",true),Pe("daten_loeschen","Welche Daten sollen gelöscht werden?",true),Pe("grund","Grund für die Löschung",true,["Daten nicht mehr erforderlich","Einwilligung widerrufen","Widerspruch gegen Verarbeitung","Unrechtmäßige Verarbeitung"]),Pe("kundennummer","Kunden-/Vertragsnummer (falls vorhanden)",false),Pe("daten","Ihre Daten: Name und Adresse",true)]},
    "atx_dsgvo_widerspruch_werbung":{ic:"🚫",n:"Widerspruch gegen Direktwerbung (Art. 21 DSGVO)",cat:"at_datenschutz",tier:"einfach",q:[Pe("unternehmen","Unternehmen: Name und Adresse",true),Pe("kanaele","Betroffene Werbekanäle",true,["E-Mail (Newsletter)","Post","Telefon","SMS","Alle Kanäle"]),Pe("kundennummer","Kunden-/Empfängernummer (falls vorhanden)",false),Pe("daten","Ihre Daten: Name und Adresse/E-Mail",true)]},
    "atx_dsb_beschwerde":{ic:"🛡️",n:"Beschwerde an die Datenschutzbehörde (Art. 77 DSGVO)",cat:"at_datenschutz",tier:"komplex",q:[Pe("beschwerdegegner","Beschwerdegegner (verantwortliches Unternehmen/Stelle): Name und Adresse",true),Pe("sachverhalt","Sachverhalt: Welche Datenschutzverletzung liegt vor? (was, wann)",true),Pe("verletzung","Verletztes Recht",true,["Auskunft verweigert (Art. 15)","Löschung verweigert (Art. 17)","Unrechtmäßige Verarbeitung","Datenleck / Datenpanne","Unzulässige Werbung"]),Pe("vorkontakt","Haben Sie das Unternehmen bereits kontaktiert? Ergebnis",false),Pe("nachweise","Vorhandene Nachweise (E-Mails, Schriftverkehr)",false),Pe("daten","Ihre Daten: Name und Adresse",true)]}
  };
  function inject(){
    try{
      if(typeof DOCS==="undefined"||!DOCS) return false;
      if(typeof CATS==="undefined"||!CATS) return false;
      for(var k in E){ if(!DOCS[k]) DOCS[k]=E[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=E[k].tier||"standard"; }
      for(var nc in NEWCATS){ if(!CATS[nc]) CATS[nc]={n:NEWCATS[nc].n,ic:NEWCATS[nc].ic,docs:[],country:"AT"}; if(!CATS[nc].docs) CATS[nc].docs=[]; }
      for(var k2 in E){ var c2=E[k2].cat; if(CATS[c2]&&CATS[c2].docs&&CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); }
      if(typeof CAT_LABELS!=="undefined"){ for(var cl in NEWCATS) CAT_LABELS[cl]=NEWCATS[cl].n; }
      if(typeof CAT_DOCS!=="undefined"){
        for(var cd in NEWCATS){ if(!CAT_DOCS[cd]) CAT_DOCS[cd]=[]; }
        for(var k3 in E){ var c3=E[k3].cat; if(CAT_DOCS[c3]){ var ex=false; for(var i=0;i<CAT_DOCS[c3].length;i++){ if(CAT_DOCS[c3][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[c3].push({k:k3,ic:E[k3].ic,t:E[k3].n,tier:E[k3].tier||"standard"}); } }
      }
      return !!(DOCS.atx_scheidung_einvernehmlich);
    }catch(e){ return false; }
  }
  var done=false, ticks=0;
  function run(){ if(!done){ if(inject()) done=true; } if(ticks++<40) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}

CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
$tmp = tempnam(sys_get_temp_dir(),'at').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-atmore-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "  ✓ AT: +16 belge, 4 kategori eklendi\n\n✓ CH_AT_EXPAND_MORE uygulandi.\n";
