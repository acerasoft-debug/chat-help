<?php
/**
 * ChatHelp — apply-vertrag-expand (CH_VST_EXPAND)
 *  Vertrag Studio'ya 4 yeni sozlesme turu ekler (Miet/Arbeits/Kfz'deki AYNI
 *  cok-sayfali sihirbaz + PDF + aylik-kota mimarisiyle):
 *   • Vollmacht (Genel Vekaletname) — 2 sayfa, 3,99 €
 *   • Darlehensvertrag (Borc/Odunc Sozlesmesi) — 3 sayfa, 3,99 €
 *   • Untermietvertrag (Alt Kira Sozlesmesi) — 3 sayfa, 3,99 €
 *   • Kundigungsschreiben (Miet-/Arbeitsvertrag fesih bildirimi, § referansli) — 1 sayfa, 3,99 €
 *  CT nesnesi ve buildPages() ic-ice kapali (closure) oldugu icin YENI script
 *  eklemek yerine, zaten deploy edilmis ch-vertrag-studio script blogunu
 *  byte-tam str_replace ile cerrahi olarak genisletir (apply-fabfix.php'deki
 *  yontemle ayni prensip).
 * KULLANIM: pull-updates.php?files=apply-vertrag-expand.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-vertrag-expand BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_VST_EXPAND') !== false) exit("Zaten ekli (CH_VST_EXPAND).\n");
if (strpos($src, 'CH_VST') === false) exit("HATA: Vertrag Studio (CH_VST) henuz kurulu degil — once apply-contract-studio.php calistir.\n");
file_put_contents($file . '.bak-vstexpand-' . date('Ymd-His'), $src);

/* ── 1) CT nesnesine 4 yeni giris ekle ── */
$ctAnchor = <<<'ANCH'
      ]}
  };

  /* ══ SAYFA URETICILERI (premium A4 hukuki sablon) ══ */
ANCH;

$ctNew = <<<'CTNEW'
      ]},
    vollmacht:{ic:"📜",n:"Vollmacht",sn:"Allgemein/Spezial",price:"3,99 €",pgs:2,
      steps:[
        {t:"§1 · Vollmachtgeber & Bevollmächtigter",f:[
          ["vg_name","Vollmachtgeber — Name"],["vg_addr","Vollmachtgeber — Anschrift"],["vg_geb","Vollmachtgeber — Geburtsdatum (optional)"],
          ["bv_name","Bevollmächtigter — Name"],["bv_addr","Bevollmächtigter — Anschrift"],
          ["art","Art der Vollmacht","Generalvollmacht"],["umfang","Umfang / Zweck (z. B. Bankgeschäfte, Behördengänge)"]]},
        {t:"§2–4 · Umfang, Dauer & Unterschriften",f:[
          ["ausnahmen","Ausgenommene Geschäfte (optional)"],["gueltig","Gültigkeitsdauer","bis auf Widerruf"],
          ["ort","Ort"],["datum","Datum"]]}
      ]},
    darlehen:{ic:"💶",n:"Darlehensvertrag",sn:"privat",price:"3,99 €",pgs:3,
      steps:[
        {t:"§1–2 · Parteien & Darlehenssumme",f:[
          ["gl_name","Darlehensgeber — Name"],["gl_addr","Darlehensgeber — Anschrift"],
          ["gn_name","Darlehensnehmer — Name"],["gn_addr","Darlehensnehmer — Anschrift"],
          ["betrag","Darlehenssumme (€)"],["zweck","Verwendungszweck (optional)"],["auszahlung","Auszahlung am (Datum)"]]},
        {t:"§3–5 · Zinsen & Rückzahlung",f:[
          ["zins","Zinssatz (% p. a.)","0 (zinslos)"],["rate","Rückzahlung — Rate (€/Monat) oder Gesamtsumme"],
          ["laufzeit","Rückzahlung bis (Datum)"],["kuend","Kündigungsrecht","3 Monate zum Monatsende"]]},
        {t:"§6–7 · Sicherheiten & Unterschriften",f:[
          ["sicherheit","Sicherheiten (optional)"],["ort","Ort"],["datum","Datum"]]}
      ]},
    untermiete:{ic:"🔑",n:"Untermietvertrag",sn:"Zimmer/Wohnung",price:"3,99 €",pgs:3,
      steps:[
        {t:"§1–2 · Parteien & Mietobjekt",f:[
          ["hm_name","Hauptmieter (Untervermieter) — Name"],["hm_addr","Hauptmieter — Anschrift"],
          ["um_name","Untermieter — Name"],["um_addr","Untermieter — bisherige Anschrift"],
          ["obj","Untermietobjekt (Zimmer/Wohnung, Anschrift)"],["moebliert","Möbliert?","ja"]]},
        {t:"§3–4 · Mietzeit & Untermiete",f:[
          ["beginn","Beginn (Datum)"],["befristung","Befristung (leer = unbefristet)"],
          ["miete","Untermiete (€/Monat, inkl. Nebenkosten)"],["kaution","Kaution (€, optional)"],["zahltag","Zahlbar bis (Tag des Monats)","3"]]},
        {t:"§5–7 · Zustimmung & Unterschriften",f:[
          ["zustimmung","Zustimmung des Hauptvermieters","liegt vor"],["zusatz","Sonstige Vereinbarungen (optional)"],
          ["ort","Ort"],["datum","Datum"]]}
      ]},
    kuendigung:{ic:"✉️",n:"Kündigungsschreiben",sn:"Miet-/Arbeitsvertrag",price:"3,99 €",pgs:1,
      steps:[
        {t:"Angaben zur Kündigung",f:[
          ["typ","Vertragsart","Mietvertrag"],
          ["abs_name","Ihr Name"],["abs_addr","Ihre Anschrift"],
          ["empf_name","Empfänger (Vermieter/Arbeitgeber) — Name"],["empf_addr","Empfänger — Anschrift"],
          ["vdat","Vertragsdatum / Vertrags-Nr. (optional)"],["termin","Kündigung zum (Datum)"],
          ["grund","Kündigungsgrund (optional)"],["ort","Ort"],["datum","Datum"]]}
      ]}
  };

  /* ══ SAYFA URETICILERI (premium A4 hukuki sablon) ══ */
CTNEW;

$n1 = 0;
$src = str_replace($ctAnchor, $ctNew, $src, $n1);
if (!$n1) exit("HATA: CT ekleme noktasi bulunamadi — dosya degistirilmedi.\n");

/* ── 2) buildPages() dallarini genislet ── */
$bpAnchor = <<<'ANCH2'
    } else {
      P.push(head("KFZ-KAUFVERTRAG","zwischen Privatpersonen")
        +par("1.","Vertragsparteien","Verkäufer: "+V(d.vk_name)+", "+V(d.vk_addr)+"<br>Käufer: "+V(d.kf_name)+", "+V(d.kf_addr))
        +par("2.","Fahrzeug","Marke/Modell: "+V(d.marke)+" · FIN: "+V(d.fin)+"<br>Erstzulassung: "+V(d.ez)+" · Kilometerstand: "+V(d.km,"______")+" km · HU/AU: "+V(d.tuev,"—")));
      P.push(par("3.","Kaufpreis","Der Kaufpreis beträgt "+V(d.preis,"______")+" €. Zahlung: "+V(d.zahlart,"bar bei Übergabe")+". Das Fahrzeug bleibt bis zur vollständigen Zahlung Eigentum des Verkäufers.")
        +par("4.","Gewährleistung","Das Fahrzeug wird — soweit gesetzlich zulässig — <b>unter Ausschluss jeglicher Sachmängelhaftung</b> verkauft (Privatverkauf). Der Ausschluss gilt nicht für Vorsatz, Arglist oder Schäden aus der Verletzung von Leben, Körper oder Gesundheit. Bekannte Mängel: "+(d.maengel?esc(d.maengel):'<span class="vb">— keine —</span>'))
        +par("5.","Übergabe","Die Übergabe des Fahrzeugs mit Schlüsseln und Papieren (ZB I & II) erfolgt am "+V(d.uebergabe)+". Der Käufer meldet das Fahrzeug unverzüglich um.")
        +sig2(d,"Verkäufer","Käufer",d.kort,d.kdatum));
    }
    var n=P.length, doc=CT[key].n;
ANCH2;

$bpNew = <<<'BPNEW'
    } else if(key==="kfz"){
      P.push(head("KFZ-KAUFVERTRAG","zwischen Privatpersonen")
        +par("1.","Vertragsparteien","Verkäufer: "+V(d.vk_name)+", "+V(d.vk_addr)+"<br>Käufer: "+V(d.kf_name)+", "+V(d.kf_addr))
        +par("2.","Fahrzeug","Marke/Modell: "+V(d.marke)+" · FIN: "+V(d.fin)+"<br>Erstzulassung: "+V(d.ez)+" · Kilometerstand: "+V(d.km,"______")+" km · HU/AU: "+V(d.tuev,"—")));
      P.push(par("3.","Kaufpreis","Der Kaufpreis beträgt "+V(d.preis,"______")+" €. Zahlung: "+V(d.zahlart,"bar bei Übergabe")+". Das Fahrzeug bleibt bis zur vollständigen Zahlung Eigentum des Verkäufers.")
        +par("4.","Gewährleistung","Das Fahrzeug wird — soweit gesetzlich zulässig — <b>unter Ausschluss jeglicher Sachmängelhaftung</b> verkauft (Privatverkauf). Der Ausschluss gilt nicht für Vorsatz, Arglist oder Schäden aus der Verletzung von Leben, Körper oder Gesundheit. Bekannte Mängel: "+(d.maengel?esc(d.maengel):'<span class="vb">— keine —</span>'))
        +par("5.","Übergabe","Die Übergabe des Fahrzeugs mit Schlüsseln und Papieren (ZB I & II) erfolgt am "+V(d.uebergabe)+". Der Käufer meldet das Fahrzeug unverzüglich um.")
        +sig2(d,"Verkäufer","Käufer",d.kort,d.kdatum));
    } else if(key==="vollmacht"){
      P.push(head("VOLLMACHT","")
        +par("§ 1","Vollmachtgeber","Ich, "+V(d.vg_name)+", "+V(d.vg_addr)+(d.vg_geb?(", geboren am "+V(d.vg_geb)):"")+" — nachfolgend <i>Vollmachtgeber</i> — bevollmächtige hiermit "+V(d.bv_name)+", "+V(d.bv_addr)+" — nachfolgend <i>Bevollmächtigter</i>.")
        +par("§ 2","Art & Umfang","Es handelt sich um eine "+V(d.art,"Generalvollmacht")+". Der Bevollmächtigte ist berechtigt, mich in folgenden Angelegenheiten umfassend zu vertreten: "+V(d.umfang,"sämtliche Rechtsgeschäfte und Rechtshandlungen")+". Die Vertretungsmacht umfasst insbesondere den Abschluss, die Änderung und die Beendigung von Verträgen im genannten Umfang (§§ 164 ff. BGB)."));
      P.push(par("§ 3","Ausnahmen",(d.ausnahmen?("Von dieser Vollmacht ausgenommen sind: "+esc(d.ausnahmen)):'<span class="vb">— keine Ausnahmen —</span>'))
        +par("§ 4","Gültigkeit & Widerruf","Diese Vollmacht gilt "+V(d.gueltig,"bis auf Widerruf")+" und kann vom Vollmachtgeber jederzeit formlos widerrufen werden (§ 168 BGB). Sie erlischt nicht automatisch mit dem Tod des Vollmachtgebers, sofern nicht anders vereinbart.")
        +'<div class="vnote">Für Vorsorgevollmachten (Gesundheit, Betreuung) sowie Grundstücksgeschäfte wird notarielle Beurkundung empfohlen bzw. ist gesetzlich vorgeschrieben.</div>'
        +sig2(d,"Vollmachtgeber","Bevollmächtigter",d.ort,d.datum));
    } else if(key==="darlehen"){
      P.push(head("DARLEHENSVERTRAG","zwischen Privatpersonen")
        +par("§ 1","Vertragsparteien","Zwischen "+V(d.gl_name)+", "+V(d.gl_addr)+" — nachfolgend <i>Darlehensgeber</i> — und "+V(d.gn_name)+", "+V(d.gn_addr)+" — nachfolgend <i>Darlehensnehmer</i> — wird folgender Darlehensvertrag geschlossen (§§ 488 ff. BGB).")
        +par("§ 2","Darlehenssumme","Der Darlehensgeber gewährt dem Darlehensnehmer ein Darlehen in Höhe von "+V(d.betrag,"____")+" €. "+(d.zweck?("Verwendungszweck: "+esc(d.zweck)+". "):"")+"Die Auszahlung erfolgt am "+V(d.auszahlung)+"."));
      P.push(par("§ 3","Zinsen","Das Darlehen wird mit "+V(d.zins,"0")+" % p. a. verzinst"+((d.zins&&d.zins!=="0")?", zahlbar jeweils zum Ende der Zinsperiode":" (zinsloses Darlehen)")+".")
        +par("§ 4","Rückzahlung","Die Rückzahlung erfolgt in Form von "+V(d.rate,"____")+" bis spätestens "+V(d.laufzeit)+".")
        +par("§ 5","Kündigung","Beide Parteien können den Vertrag mit einer Frist von "+V(d.kuend,"3 Monaten zum Monatsende")+" kündigen; das gesetzliche Kündigungsrecht aus wichtigem Grund bleibt unberührt. Bei Zahlungsverzug gilt der gesetzliche Verzugszins (§ 288 BGB)."));
      P.push(par("§ 6","Sicherheiten",(d.sicherheit?esc(d.sicherheit):'<span class="vb">— keine Sicherheiten vereinbart —</span>'))
        +par("§ 7","Schlussbestimmungen","Änderungen und Ergänzungen dieses Vertrages bedürfen der Schriftform. Sollte eine Bestimmung unwirksam sein, bleibt der Vertrag im Übrigen wirksam.")
        +'<div class="vnote">Empfehlung: Bei Darlehen über 600 € Schriftform verwenden und als Nachweis für spätere Rückforderungen oder gegenüber dem Finanzamt aufbewahren.</div>'
        +sig2(d,"Darlehensgeber","Darlehensnehmer",d.ort,d.datum));
    } else if(key==="untermiete"){
      P.push(head("UNTERMIETVERTRAG","")
        +par("§ 1","Vertragsparteien","Zwischen "+V(d.hm_name)+", "+V(d.hm_addr)+" — nachfolgend <i>Hauptmieter</i> — und "+V(d.um_name)+", "+V(d.um_addr)+" — nachfolgend <i>Untermieter</i> — wird folgender Untermietvertrag geschlossen.")
        +par("§ 2","Mietobjekt","Untervermietet wird: "+V(d.obj)+". Das Objekt ist "+V(d.moebliert,"ja")+" möbliert. Der Untermieter erhält kein eigenständiges Rechtsverhältnis zum Hauptvermieter."));
      P.push(par("§ 3","Mietzeit","Das Untermietverhältnis beginnt am "+V(d.beginn)+" und läuft "+(d.befristung?("befristet bis "+V(d.befristung)):"auf unbestimmte Zeit")+".")
        +par("§ 4","Untermiete","Die Untermiete beträgt "+V(d.miete,"____")+" € pro Monat (inkl. Nebenkosten), zahlbar bis zum "+V(d.zahltag,"3")+". Werktag des Monats an den Hauptmieter. "+(d.kaution?("Kaution: "+V(d.kaution)+" €."):"Es wird keine Kaution vereinbart.")));
      P.push(par("§ 5","Zustimmung des Hauptvermieters","Die erforderliche Zustimmung des Hauptvermieters zur Untervermietung (§ 540 BGB) "+V(d.zustimmung,"liegt vor")+".")
        +par("§ 6","Sonstiges",(d.zusatz?esc(d.zusatz):'<span class="vb">— keine —</span>'))
        +par("§ 7","Schlussbestimmungen","Änderungen bedürfen der Textform. Die Unwirksamkeit einzelner Bestimmungen berührt den Vertrag im Übrigen nicht.")
        +'<div class="vnote">Hinweis: Der Untermieter hat keine eigenen Rechte gegenüber dem Hauptvermieter; alle Absprachen laufen über den Hauptmieter.</div>'
        +sig2(d,"Hauptmieter","Untermieter",d.ort,d.datum));
    } else if(key==="kuendigung"){
      var isArbeit=/arbeit/i.test(d.typ||"");
      P.push(head("KÜNDIGUNG",isArbeit?"des Arbeitsverhältnisses":"des Mietverhältnisses")
        +'<div class="vptx" style="margin-bottom:18px">'+V(d.abs_name)+'<br>'+V(d.abs_addr)+'</div>'
        +'<div class="vptx" style="margin-bottom:18px">An:<br>'+V(d.empf_name)+'<br>'+V(d.empf_addr)+'</div>'
        +par("Betreff","Kündigung "+(isArbeit?"des Arbeitsvertrags":"des Mietvertrags")+(d.vdat?(" vom "+V(d.vdat)):""),
          "Sehr geehrte Damen und Herren,<br><br>hiermit kündige ich "+(isArbeit?"das mit Ihnen bestehende Arbeitsverhältnis":"das mit Ihnen bestehende Mietverhältnis")+" fristgerecht zum "+V(d.termin)+"."
          +(d.grund?(" Grund: "+esc(d.grund)+"."):"")
          +"<br><br>"+(isArbeit?"Die Kündigungsfrist richtet sich nach § 622 BGB bzw. den Regelungen des Arbeitsvertrags.":"Die Kündigungsfrist richtet sich nach § 573c BGB bzw. den Regelungen des Mietvertrags.")
          +" Ich bitte um schriftliche Bestätigung des Vertragsendes.<br><br>Mit freundlichen Grüßen")
        +'<div class="vnote">Bitte per Einschreiben/Einwurf oder gegen Empfangsbestätigung versenden. Diese Vorlage ersetzt keine Rechtsberatung im Einzelfall (z. B. bei Sonderkündigungsrechten).</div>'
        +'<div class="vsig"><div class="vsig-l">'+esc(d.ort||"")+', '+esc(d.datum||"")+'</div><div class="vsig-r"><div class="vsl" style="flex:1;max-width:220px"><div class="vline"></div>Unterschrift</div></div></div>');
    }
    var n=P.length, doc=CT[key].n;
BPNEW;

$n2 = 0;
$src = str_replace($bpAnchor, $bpNew, $src, $n2);
if (!$n2) exit("HATA: buildPages ekleme noktasi bulunamadi — dosya degistirilmedi (CT eklendi ama geri alinmadi — bak dosyasindan geri yukle).\n");

$src = str_replace('<script id="ch-vertrag-studio">', "<script id=\"ch-vertrag-studio\">\n/* CH_VST_EXPAND */", $src, $n3);

$tmp = tempnam(sys_get_temp_dir(), 'idx') . '.php';
file_put_contents($tmp, $src);
$lo = []; $rc = 0;
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc);
@unlink($tmp);
if ($rc !== 0) { echo "LINT HATASI — index.php degistirilmedi:\n  " . implode("\n  ", $lo) . "\n"; exit; }
file_put_contents($file, $src);
echo "✓ CH_VST_EXPAND uygulandi ($n1 CT + $n2 buildPages nokta).\n";
echo "Test: sol menude 'Verträge' -> artik 7 karti: Miet/Arbeits/Kfz + Vollmacht/Darlehensvertrag/Untermietvertrag/Kündigungsschreiben.\n";
