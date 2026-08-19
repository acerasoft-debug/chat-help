<?php
/**
 * ChatHelp — apply-vertrag-expand2 (CH_VST_EXPAND2)
 *  Vertrag Studio'ya 9 yeni Alman sozlesmesi ekler (mevcut 7 + 9 = 16):
 *   Kaufvertrag, Aufhebungsvertrag, Schenkungsvertrag, Werkvertrag,
 *   Dienstleistungsvertrag (freie Mitarbeit), Bürgschaft, Leihvertrag,
 *   Ratenzahlungsvereinbarung, Garagen-/Stellplatzmietvertrag.
 *  Hepsi mevcut cok-sayfali sihirbaz + PDF + kota mimarisiyle; ilgili
 *  BGB maddeleri metinde belirtilir. CT + buildPages byte-tam str_replace.
 *  GEREKLI: apply-contract-studio.php (CH_VST) + apply-vertrag-expand.php.
 * KULLANIM: pull-updates.php?files=apply-vertrag-expand2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-vertrag-expand2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_VST_EXPAND2') !== false) exit("Zaten ekli (CH_VST_EXPAND2).\n");
if (strpos($src, 'CH_VST_EXPAND') === false) exit("HATA: apply-vertrag-expand.php (CH_VST_EXPAND) kurulu olmali.\n");
file_put_contents($file . '.bak-vstexp2-' . date('Ymd-His'), $src);

/* ── 1) CT nesnesine 9 yeni giris ── */
$ctOld = <<<'ANCH'
          ["grund","Kündigungsgrund (optional)"],["ort","Ort"],["datum","Datum"]]}
      ]}
  };
ANCH;

$ctNew = <<<'CTNEW'
          ["grund","Kündigungsgrund (optional)"],["ort","Ort"],["datum","Datum"]]}
      ]},
    kaufvertrag:{ic:"🛒",n:"Kaufvertrag",sn:"bewegliche Sache",price:"3,99 €",pgs:2,
      steps:[
        {t:"§1–2 · Parteien & Kaufgegenstand",f:[
          ["vk_name","Verkäufer — Name"],["vk_addr","Verkäufer — Anschrift"],
          ["kf_name","Käufer — Name"],["kf_addr","Käufer — Anschrift"],
          ["ware","Kaufgegenstand (genaue Beschreibung)"],["zustand","Zustand","gebraucht, wie besichtigt"]]},
        {t:"§3–5 · Preis, Übergabe & Unterschriften",f:[
          ["preis","Kaufpreis (€)"],["zahlart","Zahlungsart","bar bei Übergabe"],
          ["uebergabe","Übergabe am (Datum)"],["ort","Ort"],["datum","Datum"]]}
      ]},
    aufhebung:{ic:"🤝",n:"Aufhebungsvertrag",sn:"Arbeitsverhältnis",price:"4,99 €",pgs:1,
      steps:[
        {t:"Angaben zum Aufhebungsvertrag",f:[
          ["ag_name","Arbeitgeber — Firma"],["ag_addr","Arbeitgeber — Anschrift"],
          ["an_name","Arbeitnehmer — Name"],["an_addr","Arbeitnehmer — Anschrift"],
          ["beginn","Arbeitsverhältnis seit"],["ende","Beendigung zum (Datum)"],
          ["abfindung","Abfindung (€, optional)"],["urlaub","Resturlaub (Tage, optional)"],
          ["ort","Ort"],["datum","Datum"]]}
      ]},
    schenkung:{ic:"🎁",n:"Schenkungsvertrag",sn:"§ 516 BGB",price:"3,99 €",pgs:1,
      steps:[
        {t:"Angaben zur Schenkung",f:[
          ["sch_name","Schenker — Name"],["sch_addr","Schenker — Anschrift"],
          ["be_name","Beschenkter — Name"],["be_addr","Beschenkter — Anschrift"],
          ["gegenstand","Schenkungsgegenstand"],["wert","Wert (€, optional)"],
          ["uebergabe","Übergabe am (Datum)"],["ort","Ort"],["datum","Datum"]]}
      ]},
    werkvertrag:{ic:"🔧",n:"Werkvertrag",sn:"§ 631 BGB",price:"4,99 €",pgs:2,
      steps:[
        {t:"§1–2 · Parteien & Werkleistung",f:[
          ["ag_name","Besteller — Name"],["ag_addr","Besteller — Anschrift"],
          ["an_name","Unternehmer — Name/Firma"],["an_addr","Unternehmer — Anschrift"],
          ["werk","Zu erbringende Werkleistung (Beschreibung)"],["termin","Fertigstellung bis (Datum)"]]},
        {t:"§3–6 · Vergütung & Unterschriften",f:[
          ["verguetung","Vergütung (€)"],["zahlung","Zahlungsweise","nach Abnahme"],
          ["ort","Ort"],["datum","Datum"]]}
      ]},
    dienstvertrag:{ic:"🧑‍💻",n:"Dienstleistungsvertrag",sn:"freie Mitarbeit",price:"4,99 €",pgs:2,
      steps:[
        {t:"§1–2 · Parteien & Leistung",f:[
          ["ag_name","Auftraggeber — Name/Firma"],["ag_addr","Auftraggeber — Anschrift"],
          ["an_name","Dienstleister — Name"],["an_addr","Dienstleister — Anschrift"],
          ["leistung","Leistungsgegenstand (Beschreibung)"],["beginn","Beginn (Datum)"]]},
        {t:"§3–5 · Honorar & Unterschriften",f:[
          ["honorar","Honorar (€, ggf. pro Stunde/Tag)"],["abrechnung","Abrechnung","monatlich nach Aufwand"],
          ["ort","Ort"],["datum","Datum"]]}
      ]},
    buergschaft:{ic:"🛡️",n:"Bürgschaft",sn:"§ 765 BGB",price:"3,99 €",pgs:1,
      steps:[
        {t:"Angaben zur Bürgschaft",f:[
          ["glaeubiger","Gläubiger — Name/Firma"],["gl_addr","Gläubiger — Anschrift"],
          ["buerge","Bürge — Name"],["bg_addr","Bürge — Anschrift"],
          ["schuldner","Hauptschuldner — Name"],["forderung","Verbürgte Forderung (Betrag/Bezeichnung)"],
          ["ort","Ort"],["datum","Datum"]]}
      ]},
    leihvertrag:{ic:"📦",n:"Leihvertrag",sn:"§ 598 BGB",price:"3,99 €",pgs:1,
      steps:[
        {t:"Angaben zur Leihe",f:[
          ["verleiher","Verleiher — Name"],["vl_addr","Verleiher — Anschrift"],
          ["entleiher","Entleiher — Name"],["el_addr","Entleiher — Anschrift"],
          ["sache","Leihsache (Beschreibung)"],["dauer","Leihdauer / Rückgabe bis"],
          ["ort","Ort"],["datum","Datum"]]}
      ]},
    ratenzahlung:{ic:"💳",n:"Ratenzahlungsvereinbarung",sn:"Schuldanerkenntnis",price:"3,99 €",pgs:1,
      steps:[
        {t:"Angaben zur Ratenzahlung",f:[
          ["glaeubiger","Gläubiger — Name/Firma"],["gl_addr","Gläubiger — Anschrift"],
          ["schuldner","Schuldner — Name"],["sch_addr","Schuldner — Anschrift"],
          ["gesamt","Gesamtforderung (€)"],["rate","Monatliche Rate (€)"],
          ["erste","Erste Rate am (Datum)"],["iban","Konto des Gläubigers (IBAN)"],
          ["ort","Ort"],["datum","Datum"]]}
      ]},
    garage:{ic:"🅿️",n:"Garagen-/Stellplatzmietvertrag",sn:"Garage/Stellplatz",price:"3,99 €",pgs:1,
      steps:[
        {t:"Angaben zum Stellplatz",f:[
          ["vm_name","Vermieter — Name"],["vm_addr","Vermieter — Anschrift"],
          ["mt_name","Mieter — Name"],["mt_addr","Mieter — Anschrift"],
          ["objekt","Garage/Stellplatz — Lage & Nr."],["miete","Miete (€ / Monat)"],
          ["beginn","Mietbeginn (Datum)"],["kaution","Kaution (€, optional)"],
          ["ort","Ort"],["datum","Datum"]]}
      ]}
  };
CTNEW;

$c1 = 0; $src = str_replace($ctOld, $ctNew, $src, $c1);
if (!$c1) exit("HATA: CT ekleme noktasi bulunamadi — degistirilmedi.\n");

/* ── 2) buildPages: 9 yeni dal ── */
$bpOld = <<<'ANCH2'
<div class="vline"></div>Unterschrift</div></div></div>');
    }
    var n=P.length, doc=CT[key].n;
ANCH2;

$bpNew = <<<'BPNEW'
<div class="vline"></div>Unterschrift</div></div></div>');
    } else if(key==="kaufvertrag"){
      P.push(head("KAUFVERTRAG","über eine bewegliche Sache")
        +par("§ 1","Vertragsparteien","Zwischen "+V(d.vk_name)+", "+V(d.vk_addr)+" — <i>Verkäufer</i> — und "+V(d.kf_name)+", "+V(d.kf_addr)+" — <i>Käufer</i> — wird folgender Kaufvertrag geschlossen (§ 433 BGB).")
        +par("§ 2","Kaufgegenstand","Verkauft wird: "+V(d.ware)+". Zustand: "+V(d.zustand,"gebraucht, wie besichtigt")+"."));
      P.push(par("§ 3","Kaufpreis","Der Kaufpreis beträgt "+V(d.preis,"____")+" €. Zahlung: "+V(d.zahlart,"bar bei Übergabe")+". Bis zur vollständigen Zahlung bleibt die Sache Eigentum des Verkäufers.")
        +par("§ 4","Gewährleistung","Der Verkauf erfolgt von privat <b>unter Ausschluss jeglicher Sachmängelhaftung</b>. Der Ausschluss gilt nicht für Vorsatz, Arglist sowie für Schäden aus der Verletzung von Leben, Körper oder Gesundheit.")
        +par("§ 5","Übergabe & Gefahrübergang","Die Übergabe erfolgt am "+V(d.uebergabe)+". Mit der Übergabe geht die Gefahr auf den Käufer über.")
        +sig2(d,"Verkäufer","Käufer",d.ort,d.datum));
    } else if(key==="aufhebung"){
      P.push(head("AUFHEBUNGSVERTRAG","zur einvernehmlichen Beendigung des Arbeitsverhältnisses")
        +par("§ 1","Vertragsparteien","Zwischen "+V(d.ag_name)+", "+V(d.ag_addr)+" — <i>Arbeitgeber</i> — und "+V(d.an_name)+", "+V(d.an_addr)+" — <i>Arbeitnehmer</i> — wird Folgendes vereinbart.")
        +par("§ 2","Beendigung","Das seit "+V(d.beginn)+" bestehende Arbeitsverhältnis wird im gegenseitigen Einvernehmen zum "+V(d.ende)+" beendet.")
        +par("§ 3","Abfindung",(d.abfindung?("Der Arbeitgeber zahlt eine Abfindung in Höhe von "+V(d.abfindung)+" € (entsprechend § 1a KSchG), fällig mit dem Beendigungszeitpunkt."):'<span class="vb">Eine Abfindung wird nicht vereinbart.</span>'))
        +par("§ 4","Urlaub & Zeugnis",(d.urlaub?("Der Resturlaub von "+V(d.urlaub)+" Tagen wird bis zur Beendigung gewährt bzw. abgegolten. "):"")+"Der Arbeitnehmer erhält ein wohlwollendes qualifiziertes Zeugnis.")
        +par("§ 5","Ausgleichsklausel","Mit Erfüllung dieses Vertrages sind alle wechselseitigen Ansprüche aus dem Arbeitsverhältnis erledigt.")
        +'<div class="vnote">Hinweis: Ein Aufhebungsvertrag kann zu einer Sperrzeit beim Arbeitslosengeld führen (§ 159 SGB III). Vorherige Beratung wird empfohlen.</div>'
        +sig2(d,"Arbeitgeber","Arbeitnehmer",d.ort,d.datum));
    } else if(key==="schenkung"){
      P.push(head("SCHENKUNGSVERTRAG","")
        +par("§ 1","Vertragsparteien","Zwischen "+V(d.sch_name)+", "+V(d.sch_addr)+" — <i>Schenker</i> — und "+V(d.be_name)+", "+V(d.be_addr)+" — <i>Beschenkter</i> — wird folgende Schenkung vereinbart (§ 516 BGB).")
        +par("§ 2","Schenkungsgegenstand","Der Schenker schenkt dem Beschenkten unentgeltlich: "+V(d.gegenstand)+(d.wert?(" (Wert ca. "+V(d.wert)+" €)"):"")+". Der Beschenkte nimmt die Schenkung an.")
        +par("§ 3","Vollzug","Die Übergabe erfolgt am "+V(d.uebergabe)+". Mit der Übergabe ist die Schenkung vollzogen; der Formmangel des § 518 BGB ist damit geheilt.")
        +par("§ 4","Gegenleistung","Eine Gegenleistung wird nicht geschuldet. Rückforderungsrechte bei grobem Undank (§ 530 BGB) bleiben unberührt.")
        +sig2(d,"Schenker","Beschenkter",d.ort,d.datum));
    } else if(key==="werkvertrag"){
      P.push(head("WERKVERTRAG","§ 631 BGB")
        +par("§ 1","Vertragsparteien","Zwischen "+V(d.ag_name)+", "+V(d.ag_addr)+" — <i>Besteller</i> — und "+V(d.an_name)+", "+V(d.an_addr)+" — <i>Unternehmer</i> — wird folgender Werkvertrag geschlossen.")
        +par("§ 2","Werkleistung","Der Unternehmer verpflichtet sich zur Herstellung folgenden Werks: "+V(d.werk)+". Die Fertigstellung erfolgt bis zum "+V(d.termin)+"."));
      P.push(par("§ 3","Vergütung","Die Vergütung beträgt "+V(d.verguetung,"____")+" €, zahlbar "+V(d.zahlung,"nach Abnahme")+".")
        +par("§ 4","Abnahme","Nach Fertigstellung nimmt der Besteller das Werk ab (§ 640 BGB). Mit der Abnahme wird die Vergütung fällig.")
        +par("§ 5","Mängel","Bei Mängeln stehen dem Besteller die Rechte aus § 634 BGB zu (Nacherfüllung, Minderung, Rücktritt, Schadensersatz).")
        +par("§ 6","Schlussbestimmungen","Änderungen bedürfen der Textform. Sollte eine Bestimmung unwirksam sein, bleibt der Vertrag im Übrigen wirksam.")
        +sig2(d,"Besteller","Unternehmer",d.ort,d.datum));
    } else if(key==="dienstvertrag"){
      P.push(head("DIENSTLEISTUNGSVERTRAG","freie Mitarbeit · § 611 BGB")
        +par("§ 1","Vertragsparteien","Zwischen "+V(d.ag_name)+", "+V(d.ag_addr)+" — <i>Auftraggeber</i> — und "+V(d.an_name)+", "+V(d.an_addr)+" — <i>Dienstleister</i> — wird folgender Dienstvertrag über freie Mitarbeit geschlossen.")
        +par("§ 2","Leistungsgegenstand","Der Dienstleister erbringt folgende Leistungen: "+V(d.leistung)+". Beginn: "+V(d.beginn)+"."));
      P.push(par("§ 3","Selbstständigkeit","Der Dienstleister ist selbstständig tätig, unterliegt keinen Weisungen zu Ort und Zeit und ist nicht in die Arbeitsorganisation des Auftraggebers eingegliedert. Für Steuern und Sozialabgaben sorgt er selbst.")
        +par("§ 4","Honorar","Das Honorar beträgt "+V(d.honorar,"____")+" €. Abrechnung: "+V(d.abrechnung,"monatlich nach Aufwand")+" zzgl. gesetzlicher USt.")
        +par("§ 5","Laufzeit & Kündigung","Der Vertrag läuft auf unbestimmte Zeit und kann von beiden Seiten mit angemessener Frist gekündigt werden. Das Recht zur Kündigung aus wichtigem Grund bleibt unberührt.")
        +'<div class="vnote">Hinweis: Bei dauerhafter Tätigkeit für nur einen Auftraggeber kann Scheinselbstständigkeit vorliegen (§ 7 SGB IV).</div>'
        +sig2(d,"Auftraggeber","Dienstleister",d.ort,d.datum));
    } else if(key==="buergschaft"){
      P.push(head("BÜRGSCHAFT","§ 765 BGB")
        +par("§ 1","Parteien","Zwischen "+V(d.glaeubiger)+", "+V(d.gl_addr)+" — <i>Gläubiger</i> — und "+V(d.buerge)+", "+V(d.bg_addr)+" — <i>Bürge</i> — wird folgende Bürgschaft vereinbart.")
        +par("§ 2","Verbürgte Forderung","Der Bürge verbürgt sich für die Verbindlichkeit des Hauptschuldners "+V(d.schuldner)+" gegenüber dem Gläubiger: "+V(d.forderung)+".")
        +par("§ 3","Umfang","Die Bürgschaft umfasst die Hauptforderung nebst Zinsen und Kosten. Sie erlischt mit Erlöschen der Hauptforderung (Akzessorietät).")
        +par("§ 4","Form","Diese Bürgschaftserklärung wird schriftlich abgegeben (§ 766 BGB). Der Bürge handelt nicht als Kaufmann.")
        +sig2(d,"Bürge","Gläubiger",d.ort,d.datum));
    } else if(key==="leihvertrag"){
      P.push(head("LEIHVERTRAG","§ 598 BGB")
        +par("§ 1","Parteien","Zwischen "+V(d.verleiher)+", "+V(d.vl_addr)+" — <i>Verleiher</i> — und "+V(d.entleiher)+", "+V(d.el_addr)+" — <i>Entleiher</i> — wird folgender Leihvertrag geschlossen.")
        +par("§ 2","Leihsache","Der Verleiher überlässt dem Entleiher unentgeltlich zum Gebrauch: "+V(d.sache)+".")
        +par("§ 3","Dauer & Rückgabe","Die Leihe endet am "+V(d.dauer)+". Der Entleiher gibt die Sache im vertragsgemäßen Zustand zurück.")
        +par("§ 4","Pflichten","Der Entleiher trägt die gewöhnlichen Erhaltungskosten (§ 601 BGB) und haftet für schuldhaft verursachte Schäden. Eine Überlassung an Dritte bedarf der Zustimmung.")
        +sig2(d,"Verleiher","Entleiher",d.ort,d.datum));
    } else if(key==="ratenzahlung"){
      P.push(head("RATENZAHLUNGSVEREINBARUNG","mit Schuldanerkenntnis")
        +par("§ 1","Parteien","Zwischen "+V(d.glaeubiger)+", "+V(d.gl_addr)+" — <i>Gläubiger</i> — und "+V(d.schuldner)+", "+V(d.sch_addr)+" — <i>Schuldner</i> — wird Folgendes vereinbart.")
        +par("§ 2","Anerkanntes Schuld","Der Schuldner erkennt die Forderung des Gläubigers in Höhe von "+V(d.gesamt,"____")+" € dem Grunde und der Höhe nach an.")
        +par("§ 3","Ratenzahlung","Die Forderung wird in monatlichen Raten von "+V(d.rate,"____")+" € beglichen, beginnend am "+V(d.erste)+", zahlbar jeweils bis zum Monatsende auf das Konto "+V(d.iban)+".")
        +par("§ 4","Verzug","Gerät der Schuldner mit einer Rate ganz oder teilweise länger als 14 Tage in Verzug, wird der gesamte offene Restbetrag sofort zur Zahlung fällig.")
        +sig2(d,"Gläubiger","Schuldner",d.ort,d.datum));
    } else if(key==="garage"){
      P.push(head("GARAGEN-/STELLPLATZMIETVERTRAG","")
        +par("§ 1","Parteien","Zwischen "+V(d.vm_name)+", "+V(d.vm_addr)+" — <i>Vermieter</i> — und "+V(d.mt_name)+", "+V(d.mt_addr)+" — <i>Mieter</i> — wird folgender Mietvertrag geschlossen.")
        +par("§ 2","Mietobjekt","Vermietet wird die Garage / der Stellplatz: "+V(d.objekt)+", ausschließlich zum Abstellen eines Kraftfahrzeugs.")
        +par("§ 3","Mietzeit","Das Mietverhältnis beginnt am "+V(d.beginn)+" und läuft auf unbestimmte Zeit. Es kann mit gesetzlicher Frist gekündigt werden; ist der Stellplatz nicht mit einer Wohnung verbunden, gilt die Frist des § 580a BGB.")
        +par("§ 4","Miete & Kaution","Die monatliche Miete beträgt "+V(d.miete,"____")+" €, zahlbar im Voraus. "+(d.kaution?("Kaution: "+V(d.kaution)+" €."):"Eine Kaution wird nicht vereinbart."))
        +sig2(d,"Vermieter","Mieter",d.ort,d.datum));
    }
    var n=P.length, doc=CT[key].n;
BPNEW;

$c2 = 0; $src = str_replace($bpOld, $bpNew, $src, $c2);
if (!$c2) exit("HATA: buildPages ekleme noktasi bulunamadi — CT eklendi ama .bak'tan geri yukle.\n");

$tmp = tempnam(sys_get_temp_dir(), 'idx') . '.php';
file_put_contents($tmp, $src);
$lo = []; $rc = 0;
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc);
@unlink($tmp);
if ($rc !== 0) { echo "LINT HATASI — index.php degistirilmedi:\n  " . implode("\n  ", $lo) . "\n"; exit; }
file_put_contents($file, $src);
echo "✓ CH_VST_EXPAND2 uygulandi ($c1 CT + $c2 buildPages).\n";
echo "Vertrag Studio artik 16 sozlesme: Miet/Arbeits/Kfz/Vollmacht/Darlehen/Untermiete/Kündigung + Kauf/Aufhebung/Schenkung/Werk/Dienst/Bürgschaft/Leihe/Raten/Garage.\n";
