<?php
/**
 * ChatHelp — apply-vst-options (CH_VST_OPTS)
 *  Vertrag Studio: HER sozlesmeye SECILEBILIR OPSIYONEL KLOZLAR sayfasi.
 *   • Sihirbazin sonuna "Optionale Zusatzklauseln" adimi eklenir (checkbox).
 *   • Secilen klozlar PDF'e "ANLAGE 1 — Zusatzvereinbarungen" sayfasi olarak
 *     tam metin, imza blogu ile eklenir (Anlage ist Vertragsbestandteil).
 *   • Klozlar yerlesik, standart Alman hukuku metinleri (uydurma yok);
 *     bosluklar (____) elle doldurulur.
 *  15 sozlesme kapsanir (kuendigung tek tarafli yazi oldugu icin haric).
 * KULLANIM: pull-updates.php?files=apply-vst-options.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-vst-options BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_VST_OPTS')!==false) exit("Zaten ekli (CH_VST_OPTS).\n");
if (strpos($src,'function buildPages(key,d){')===false) exit("HATA: Vertrag Studio (buildPages) bulunamadi.\n");

$fail=[];

/* ── [1] alan render: checkbox tipi destegi ── */
$o1 = "     +st.f.map(function(f){\n"
    . "        return '<div class=\"vst-fl\">'+esc(f[1])+'</div><input class=\"vst-in\" data-f=\"'+f[0]+'\" value=\"'+esc(d[f[0]]||\"\")+'\" placeholder=\"'+esc(f[2]||\"\")+'\">';\n"
    . "      }).join(\"\")";
$n1 = "     +st.f.map(function(f){\n"
    . "        if(f[3]===\"cb\"){ var on=(d[f[0]]||\"\")===\"JA\"; return '<label class=\"vst-cbrow'+(on?\" on\":\"\")+'\"><input type=\"checkbox\" data-f=\"'+f[0]+'\"'+(on?\" checked\":\"\")+'><div><div class=\"vst-cbl\">'+esc(f[1])+'</div>'+(f[2]?'<div class=\"vst-cbd\">'+esc(f[2])+'</div>':'')+'</div></label>'; }\n"
    . "        return '<div class=\"vst-fl\">'+esc(f[1])+'</div><input class=\"vst-in\" data-f=\"'+f[0]+'\" value=\"'+esc(d[f[0]]||\"\")+'\" placeholder=\"'+esc(f[2]||\"\")+'\">';\n"
    . "      }).join(\"\")";
$c=0; $src=str_replace($o1,$n1,$src,$c);
echo ($c===1?"  ✓ [1] checkbox alan tipi\n":"  ✗ [1] alan render anchor ($c)\n"); if($c!==1)$fail[]=1;

/* ── [2] input handler: checkbox degeri JA/"" ── */
$o2 = "    p.querySelectorAll(\"input[data-f]\").forEach(function(el){\n"
    . "      el.addEventListener(\"input\",function(){ var dd=getD(CUR); dd[el.getAttribute(\"data-f\")]=el.value; setD(CUR,dd); schedPrev(); });\n"
    . "    });";
$n2 = "    p.querySelectorAll(\"input[data-f]\").forEach(function(el){\n"
    . "      var ev=el.type===\"checkbox\"?\"change\":\"input\";\n"
    . "      el.addEventListener(ev,function(){ var dd=getD(CUR); dd[el.getAttribute(\"data-f\")]=(el.type===\"checkbox\"?(el.checked?\"JA\":\"\"):el.value); setD(CUR,dd); try{ if(el.type===\"checkbox\"&&el.parentNode) el.parentNode.classList.toggle(\"on\",el.checked); }catch(e){} schedPrev(); });\n"
    . "    });";
$c=0; $src=str_replace($o2,$n2,$src,$c);
echo ($c===1?"  ✓ [2] checkbox handler\n":"  ✗ [2] handler anchor ($c)\n"); if($c!==1)$fail[]=2;

/* ── [3] buildPages sarmala + VOPT klozlari + adim enjeksiyonu ── */
$vopt = <<<'JS'
function buildPages(key,d){ var __P=buildPagesO(key,d); try{ __P=__P.concat(optPages(key,d)); }catch(e){} return __P; }
  /* CH_VST_OPTS — secilebilir opsiyonel klozlar (yerlesik standart metinler) */
  var VOPT={
    miet:{p:["Vermieter","Mieter"],c:[
      ["o_kleinrep","Kleinreparaturklausel","Mieter trägt Kleinreparaturen bis 100 € je Fall","Der Mieter trägt die Kosten kleinerer Instandhaltungen an den Gegenständen, die seinem häufigen und unmittelbaren Zugriff unterliegen (z. B. Armaturen, Licht- und Fensterverschlüsse), bis zu 100 € im Einzelfall, höchstens jedoch 6 % der Jahreskaltmiete pro Jahr."],
      ["o_staffel","Staffelmiete (§ 557a BGB)","Jährliche feste Mieterhöhung","Die Parteien vereinbaren eine Staffelmiete gemäß § 557a BGB. Die monatliche Kaltmiete erhöht sich jeweils nach Ablauf von zwölf Monaten um ____ €, erstmals zum ____. Während der Geltung der Staffel sind Erhöhungen nach den §§ 558 bis 559b BGB ausgeschlossen."],
      ["o_uebergabe","Übergabeprotokoll","Gemeinsames Protokoll bei Ein- und Auszug","Bei Ein- und Auszug wird ein gemeinsames Übergabeprotokoll erstellt und von beiden Parteien unterzeichnet. Der Zustand der Wohnung sowie die Zählerstände werden darin dokumentiert."],
      ["o_rauch","Rauchverbot Gemeinschaftsflächen","Treppenhaus, Keller usw.","Das Rauchen ist im Treppenhaus, im Keller und in allen Gemeinschaftsflächen des Hauses untersagt."],
      ["o_garten","Gartennutzung & -pflege","Mitbenutzung + laufende Pflege","Dem Mieter wird die Mitbenutzung des Gartens gestattet. Er übernimmt die laufende Gartenpflege (Rasenmähen, Laubbeseitigung); grundlegende Pflegearbeiten verbleiben beim Vermieter."]]},
    arbeit:{p:["Arbeitgeber","Arbeitnehmer"],c:[
      ["o_homeoffice","Mobiles Arbeiten / Homeoffice","Bis zu ____ Tage pro Woche","Der Arbeitnehmer ist berechtigt, bis zu ____ Tage pro Woche mobil bzw. im Homeoffice zu arbeiten. Ort und Erreichbarkeit werden mit dem Arbeitgeber abgestimmt; die Regelungen zu Arbeitszeit und Datenschutz gelten unverändert."],
      ["o_ueberstunden","Überstunden-Abgeltung","Begrenzte Abgeltung mit dem Gehalt","Mit der monatlichen Vergütung sind bis zu ____ Überstunden pro Monat abgegolten. Darüber hinausgehende, angeordnete Überstunden werden durch Freizeit ausgeglichen oder vergütet."],
      ["o_fortbildung","Fortbildungskosten-Rückzahlung","Anteilige Rückzahlung bei Eigenkündigung","Übernimmt der Arbeitgeber Kosten einer Fortbildung von mehr als ____ €, verpflichtet sich der Arbeitnehmer zur anteiligen Rückzahlung, wenn er innerhalb von zwölf Monaten nach Abschluss auf eigenen Wunsch ausscheidet; je vollem weiteren Beschäftigungsmonat reduziert sich der Rückzahlungsbetrag um ein Zwölftel."],
      ["o_dienstwagen","Dienstwagen (auch privat)","1-%-Regelung","Der Arbeitnehmer erhält einen Dienstwagen, der auch privat genutzt werden darf. Die Versteuerung des geldwerten Vorteils erfolgt nach der 1-%-Regelung; Einzelheiten regelt ein gesonderter Dienstwagenüberlassungsvertrag."],
      ["o_wettbewerb","Nachvertragliches Wettbewerbsverbot","Mit Karenzentschädigung (§§ 74 ff. HGB)","Der Arbeitnehmer verpflichtet sich, für die Dauer von ____ Monaten (höchstens zwei Jahre) nach Beendigung des Arbeitsverhältnisses nicht für ein Konkurrenzunternehmen tätig zu werden. Der Arbeitgeber zahlt hierfür eine Karenzentschädigung in Höhe von mindestens der Hälfte der zuletzt bezogenen vertragsmäßigen Leistungen (§§ 74 ff. HGB)."]]},
    kfz:{p:["Verkäufer","Käufer"],c:[
      ["o_gewaehr","Gewährleistungsausschluss (Privatverkauf)","Standard bei Privatverkauf","Der Verkauf erfolgt unter Ausschluss jeglicher Sachmängelhaftung. Dieser Ausschluss gilt nicht für Schadensersatzansprüche aus der Verletzung von Leben, Körper oder Gesundheit, bei Vorsatz oder grober Fahrlässigkeit sowie bei arglistig verschwiegenen Mängeln."],
      ["o_unfall","Zusicherung Unfallfreiheit","Verkäufer sichert Unfallfreiheit zu","Der Verkäufer versichert, dass das Fahrzeug in seiner Besitzzeit unfallfrei war und ihm auch aus der Zeit davor keine Unfallschäden bekannt sind."],
      ["o_zubehoer","Zubehör inklusive","Winterreifen, Zweitschlüssel …","Im Kaufpreis enthalten ist folgendes Zubehör: ____ (z. B. Winterreifen, Dachträger, Serviceheft, Zweitschlüssel)."]]},
    vollmacht:{p:["Vollmachtgeber","Bevollmächtigter"],c:[
      ["o_befrist","Befristung","Gilt bis zu einem festen Datum","Diese Vollmacht ist befristet und gilt bis zum ____. Nach Ablauf erlischt sie, ohne dass es eines Widerrufs bedarf."],
      ["o_untervoll","Untervollmacht","Bevollmächtigter darf weiter bevollmächtigen","Der Bevollmächtigte ist berechtigt, Untervollmacht zu erteilen."],
      ["o_tod","Geltung über den Tod hinaus","Transmortale Vollmacht","Die Vollmacht erlischt nicht mit dem Tod des Vollmachtgebers, sondern gilt über den Tod hinaus (transmortale Vollmacht)."]]},
    darlehen:{p:["Darlehensgeber","Darlehensnehmer"],c:[
      ["o_vorzeitig","Vorzeitige Rückzahlung","Jederzeit ohne Vorfälligkeitsentschädigung","Der Darlehensnehmer ist berechtigt, das Darlehen jederzeit ganz oder teilweise vorzeitig zurückzuzahlen, ohne dass eine Vorfälligkeitsentschädigung anfällt."],
      ["o_verzug","Verzugsfolgen","Zinsen bei Zahlungsverzug","Kommt der Darlehensnehmer mit einer Zahlung mehr als 14 Tage in Verzug, ist der jeweils offene Betrag mit fünf Prozentpunkten über dem Basiszinssatz zu verzinsen."],
      ["o_sicherheit","Sicherheit","Bürgschaft / Sicherungsübereignung","Zur Sicherung aller Ansprüche aus diesem Darlehen bestellt der Darlehensnehmer folgende Sicherheit: ____ (z. B. Bürgschaft, Sicherungsübereignung)."]]},
    untermiete:{p:["Hauptmieter","Untermieter"],c:[
      ["o_moebliert","Möblierte Überlassung","Inventarliste als Anlage","Die Räume werden möbliert überlassen. Die mitvermieteten Gegenstände ergeben sich aus der Inventarliste, die als Anlage beigefügt und Vertragsbestandteil ist."],
      ["o_endrein","Endreinigung","Besenrein + Endreinigung bei Auszug","Bei Auszug übergibt der Untermieter die Räume besenrein und führt eine Endreinigung durch."],
      ["o_zustimm","Zustimmung des Vermieters","Nachweis als Anlage","Die schriftliche Zustimmung des Hauptvermieters zur Untervermietung liegt vor und ist diesem Vertrag als Anlage beigefügt."]]},
    kaufvertrag:{p:["Verkäufer","Käufer"],c:[
      ["o_gewaehr","Gewährleistungsausschluss (Privatverkauf)","Standard bei Privatverkauf","Die Sache wird unter Ausschluss jeglicher Sachmängelhaftung verkauft. Der Ausschluss gilt nicht bei Vorsatz, grober Fahrlässigkeit, arglistig verschwiegenen Mängeln sowie bei Verletzung von Leben, Körper oder Gesundheit."],
      ["o_eigentum","Eigentumsvorbehalt","Eigentum erst nach voller Zahlung","Die Ware bleibt bis zur vollständigen Zahlung des Kaufpreises Eigentum des Verkäufers."],
      ["o_versand","Versand","Kosten & Gefahr trägt der Käufer","Auf Wunsch des Käufers wird die Sache versandt. Kosten und Gefahr des Versands trägt der Käufer (§ 447 BGB)."]]},
    aufhebung:{p:["Arbeitgeber","Arbeitnehmer"],c:[
      ["o_freistellung","Freistellung","Unwiderruflich, unter Fortzahlung","Der Arbeitnehmer wird bis zum Beendigungstermin unwiderruflich und unter Fortzahlung der vertragsmäßigen Vergütung sowie unter Anrechnung offener Urlaubsansprüche von der Arbeitsleistung freigestellt."],
      ["o_zeugnis","Qualifiziertes Zeugnis","Wohlwollend, mit Note","Der Arbeitnehmer erhält ein wohlwollendes, qualifiziertes Arbeitszeugnis mit der Leistungs- und Verhaltensbeurteilung ____ (z. B. stets zu unserer vollen Zufriedenheit)."],
      ["o_rueckgabe","Rückgabe von Firmeneigentum","Schlüssel, Laptop, Unterlagen","Der Arbeitnehmer gibt sämtliches Firmeneigentum (Schlüssel, Laptop, Unterlagen) spätestens am letzten Arbeitstag zurück."]]},
    schenkung:{p:["Schenker","Beschenkter"],c:[
      ["o_rueckforder","Rückforderungsrecht","Bei Vorversterben des Beschenkten","Der Schenker behält sich das Recht vor, die Schenkung zurückzufordern, falls der Beschenkte vor ihm verstirbt."],
      ["o_auflage","Schenkung unter Auflage","Mit konkreter Auflage","Die Schenkung erfolgt unter folgender Auflage: ____. Kommt der Beschenkte der Auflage nicht nach, kann der Schenker die Herausgabe nach den gesetzlichen Vorschriften verlangen (§ 527 BGB)."],
      ["o_anrechnung","Anrechnung auf den Erbteil","§ 2315 BGB","Die Zuwendung ist auf einen künftigen Erb- bzw. Pflichtteil des Beschenkten anzurechnen (§ 2315 BGB)."]]},
    werkvertrag:{p:["Auftraggeber","Auftragnehmer"],c:[
      ["o_abschlag","Abschlagszahlungen","Nach Leistungsstand (§ 632a BGB)","Der Auftragnehmer kann für in sich abgeschlossene Teilleistungen Abschlagszahlungen entsprechend dem Leistungsstand verlangen (§ 632a BGB)."],
      ["o_strafe","Vertragsstrafe bei Verzug","0,2 %/Werktag, max. 5 %","Bei schuldhafter Überschreitung des vereinbarten Fertigstellungstermins zahlt der Auftragnehmer eine Vertragsstrafe von 0,2 % der Netto-Auftragssumme je Werktag, insgesamt höchstens 5 % der Netto-Auftragssumme."],
      ["o_material","Materialbeistellung","Auftraggeber stellt Material","Der Auftraggeber stellt folgendes Material bei: ____. Für beigestelltes Material übernimmt der Auftragnehmer keine Gewähr."]]},
    dienstvertrag:{p:["Auftraggeber","Auftragnehmer"],c:[
      ["o_verschwiegen","Verschwiegenheit","Auch nach Vertragsende","Der Auftragnehmer verpflichtet sich, über alle im Rahmen der Tätigkeit bekannt gewordenen Geschäfts- und Betriebsgeheimnisse Stillschweigen zu bewahren — auch nach Vertragsende."],
      ["o_kundenschutz","Kundenschutz","12 Monate nach Vertragsende","Der Auftragnehmer verpflichtet sich, für die Dauer von zwölf Monaten nach Vertragsende keine Kunden des Auftraggebers, mit denen er im Rahmen dieses Vertrages in Kontakt stand, direkt oder indirekt abzuwerben."],
      ["o_spesen","Reisekosten & Spesen","Erstattung gegen Nachweis","Notwendige Reisekosten werden gegen Nachweis erstattet. Reisezeiten gelten in Höhe von ____ % als Arbeitszeit."]]},
    buergschaft:{p:["Gläubiger","Bürge"],c:[
      ["o_hoechst","Höchstbetrag","Haftung der Höhe nach begrenzt","Die Bürgschaft ist der Höhe nach auf einen Betrag von ____ € begrenzt (Höchstbetragsbürgschaft)."],
      ["o_befristet","Befristung","Erlischt nach festem Datum","Die Bürgschaft ist befristet bis zum ____. Nach Ablauf erlischt sie, soweit der Gläubiger die Ansprüche nicht vorher schriftlich geltend gemacht hat."],
      ["o_ausfall","Ausfallbürgschaft","Haftung erst nach Ausfall","Der Bürge haftet nur, soweit der Gläubiger nach Ausschöpfung aller zumutbaren Rechtsverfolgungsmaßnahmen gegen den Hauptschuldner ausgefallen ist."]]},
    leihvertrag:{p:["Verleiher","Entleiher"],c:[
      ["o_zeitwert","Ersatz bei Beschädigung","Zum Zeitwert","Bei Beschädigung oder Verlust ersetzt der Entleiher die Sache zum Zeitwert; normale Abnutzung durch vertragsgemäßen Gebrauch bleibt außer Betracht."],
      ["o_weitergabe","Keine Weitergabe an Dritte","§ 603 BGB","Der Entleiher darf die Sache Dritten nicht überlassen (§ 603 BGB)."],
      ["o_versichert","Versicherung","Für die Dauer der Leihe","Der Entleiher versichert die Sache für die Dauer der Leihe gegen ____ (z. B. Diebstahl, Beschädigung)."]]},
    ratenzahlung:{p:["Gläubiger","Schuldner"],c:[
      ["o_verfall","Verfallklausel","Restschuld sofort fällig bei Verzug","Gerät der Schuldner mit zwei aufeinanderfolgenden Raten ganz oder teilweise in Verzug, wird die gesamte Restschuld sofort zur Zahlung fällig."],
      ["o_zins","Verzinsung","____ % p. a.","Die Restschuld wird mit ____ % p. a. verzinst; die Zinsen sind in den vereinbarten Raten enthalten."],
      ["o_abloese","Vorzeitige Ablösung","Zinsvorteil bei früher Zahlung","Bei vollständiger vorzeitiger Ablösung reduziert sich die Restschuld um die noch nicht verbrauchten Zinsanteile."]]},
    garage:{p:["Vermieter","Mieter"],c:[
      ["o_nutzung","Nutzungszweck","Nur Kfz, keine brennbaren Stoffe","Der Stellplatz bzw. die Garage dient ausschließlich dem Abstellen eines Kraftfahrzeugs. Die Lagerung von Kraftstoffen und brennbaren Stoffen außerhalb des Fahrzeugtanks ist untersagt."],
      ["o_strom","Stromanschluss","Kosten trägt der Mieter","Ein vorhandener Stromanschluss darf nur zum Laden und Beleuchten genutzt werden; die Kosten trägt der Mieter nach Zählerstand bzw. pauschal mit ____ €/Monat."],
      ["o_verwahrung","Keine Verwahrungspflicht","Haftungsausschluss","Die Vermietung begründet keine Verwahrungspflichten. Der Vermieter haftet nicht für Beschädigung oder Entwendung des Fahrzeugs oder darin befindlicher Gegenstände, außer bei Vorsatz oder grober Fahrlässigkeit."]]}
  };
  function optPages(key,d){
    var m=VOPT[key]; if(!m||!CT[key]) return [];
    var sel=m.c.filter(function(o){ return (d[o[0]]||"")==="JA"; });
    if(!sel.length) return [];
    var b=head("ANLAGE 1","Zusatzvereinbarungen · "+CT[key].n);
    b+='<div style="font-size:11px;color:#555;margin:-8px 0 16px">Die folgenden von den Parteien gewählten Zusatzklauseln sind Bestandteil des Vertrages. Leerstellen (____) sind vor Unterzeichnung handschriftlich zu ergänzen.</div>';
    sel.forEach(function(o,i){ b+=par("§ Z"+(i+1),o[1],o[3]); });
    b+=sig2(d,m.p[0],m.p[1],"____________","____________");
    b+='<div class="vfoot"><span>'+esc(CT[key].n)+'</span><span>Anlage 1</span></div>';
    return ['<div class="vsA4">'+b+'</div>'];
  }
  (function(){ try{
    var css=document.createElement("style"); css.id="ch-vst-opts-css";
    css.textContent=".vst-cbrow{display:flex;gap:10px;align-items:flex-start;padding:10px 12px;border:1px solid rgba(212,168,74,.22);border-radius:10px;margin:8px 0;cursor:pointer;transition:border-color .15s,background .15s}.vst-cbrow.on{border-color:#d4a84a;background:rgba(212,168,74,.08)}.vst-cbrow input{margin-top:3px;accent-color:#d4a84a;width:16px;height:16px;flex-shrink:0}.vst-cbl{font-size:13px;font-weight:600;color:#fff}.vst-cbd{font-size:11.5px;color:var(--t3,#99a);margin-top:2px}";
    if(document.head) document.head.appendChild(css);
    Object.keys(VOPT).forEach(function(k){
      if(!CT[k]||CT[k].__opt) return; CT[k].__opt=1;
      CT[k].steps.push({t:"Optionale Zusatzklauseln — gewünschte antippen",f:VOPT[k].c.map(function(o){ return [o[0],o[1],o[2],"cb"]; })});
      CT[k].pgs=CT[k].steps.length;
    });
  }catch(e){} })();
  function buildPagesO(key,d){
    var P=[];
JS;
$o3 = "function buildPages(key,d){\n    var P=[];";
$c=0; $src=str_replace($o3,$vopt,$src,$c);
echo ($c===1?"  ✓ [3] VOPT klozlari + Anlage-1 sayfasi + adim enjeksiyonu\n":"  ✗ [3] buildPages anchor ($c)\n"); if($c!==1)$fail[]=3;

if ($fail) { echo "\nHATA: adimlar eslesmedi: ".implode(', ',$fail)." — index.php DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(),'vo').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-vstopt-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_VST_OPTS uygulandi. Her sozlesmede secilebilir opsiyonel klozlar; secilenler PDF'e 'ANLAGE 1' olarak tam metin eklenir.\n";
