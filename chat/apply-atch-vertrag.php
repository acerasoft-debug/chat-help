<?php
/**
 * ChatHelp — apply-atch-vertrag (CH_ATCH_VERTRAG) — AT (ABGB) + CH (OR) sozlesme
 *  setlerini Vertrag modulune ekle. XSETS'e AT/CH anahtarlari enjekte edilir
 *  (var xBak=null anchor'i oncesi, tek str_replace) — syncX bunlari otomatik
 *  swap eder. Ardindan at_vertraege/ch_vertraege kategorileri module yonlendirilir.
 *  Gomulu JS node --check ile dogrulandi.
 * KULLANIM: pull2.php?key=...&files=apply-atch-vertrag.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-atch-vertrag BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_ATCH_VERTRAG')!==false) exit("Zaten ekli (CH_ATCH_VERTRAG).\n");

/* ── ADIM 1: XSETS'e __XSET_ADD merge hook ── */
$old = 'var xBak=null,xMode=null;';
$new_hook = 'try{if(window.__XSET_ADD){for(var _xc in window.__XSET_ADD){XSETS[_xc]=window.__XSET_ADD[_xc];}}}catch(_e){} /*CH_ATCH_VERTRAG*/ var xBak=null,xMode=null;';
$c = substr_count($src,$old);
if ($c!==1) exit("HATA: XSETS anchor $c kez (1 bekleniyordu). index DEGISTIRILMEDI.\n");
$src = str_replace($old,$new_hook,$src);
echo "  ✓ ADIM 1: XSETS __XSET_ADD merge hook'landi\n";

/* ── ADIM 2: __XSET_ADD (AT/CH setleri) + kategori yonlendirme ── */
$block = <<<'HTMLBLOCK'
<script id="ch-atch-vertrag">
/* CH_ATCH_VERTRAG — AT (ABGB) + CH (OR) sozlesme setleri (syncX/XSETS uzerinden) */
try{(function(){
  function u(){ return {t:"Eigene Klauseln (optional)",f:[["uc_t1","Zusatzklausel — Titel",""],["uc_b1","Zusatzklausel — Text","","ta"],["uc_t2","Zusatzklausel 2 — Titel",""],["uc_b2","Zusatzklausel 2 — Text","","ta"]]}; }

  var AT={};
  AT["vtr_at_miete"]={ic:"🏠",n:"Mietvertrag Wohnung (AT)",sn:"Wohnungsmiete nach ABGB/MRG",price:"4,99 €",pgs:5,steps:[
    {t:"Parteien",f:[["vermieter","Vermieter (Name, Anschrift)","","ta"],["mieter","Mieter (Name, Anschrift)","","ta"]]},
    {t:"Mietgegenstand",f:[["objekt","Mietobjekt (Adresse, Top-Nr., Nutzfläche m²)","","ta"],["zweck","Verwendungszweck","Wohnzwecke"],["beginn","Mietbeginn"],["dauer","Vertragsdauer","unbefristet"]]},
    {t:"Mietzins & Kaution",f:[["hauptmietzins","Hauptmietzins netto (€/Monat)"],["betriebskosten","Betriebskosten (€/Monat)"],["ust","Umsatzsteuer","10 % (Wohnung)"],["faellig","Fälligkeit","monatlich im Voraus bis zum 5."],["wertsicherung","Wertsicherung","VPI 2020 der Statistik Austria"],["kaution","Kaution","drei Bruttomonatsmieten (§ 16b MRG)"]]},
    {t:"Pflichten & Übergabe",f:[["erhaltung","Erhaltung","Der Vermieter trägt die Erhaltung ernster Schäden und der mitvermieteten Heiztherme/Geräte im Rahmen des MRG."],["untermiete","Untervermietung","Eine gänzliche Untervermietung ist ohne Zustimmung des Vermieters unzulässig."],["uebergabe","Zustand bei Übergabe","Die Wohnung wird gereinigt und in ordnungsgemäßem Zustand übergeben; ein Übergabeprotokoll wird erstellt.","ta"],["ort","Ort"],["datum","Datum"]]},
    u()]};
  AT["vtr_at_arbeit"]={ic:"💼",n:"Arbeitsvertrag / Dienstvertrag (AT)",sn:"Dienstvertrag nach ABGB/AngG",price:"4,99 €",pgs:5,steps:[
    {t:"Parteien",f:[["arbeitgeber","Arbeitgeber (Firma, Anschrift)","","ta"],["arbeitnehmer","Arbeitnehmer (Name, Anschrift)","","ta"]]},
    {t:"Tätigkeit & Entgelt",f:[["funktion","Verwendung / Funktion"],["dienstort","Dienstort"],["beginn","Eintrittsdatum"],["kv","Anwendbarer Kollektivvertrag"],["entgelt","Monatsbrutto (€)"],["auszahlung","Auszahlung","14× jährlich (inkl. Sonderzahlungen), am Monatsletzten"],["probemonat","Probemonat","ein Monat (§ 19 AngG)"]]},
    {t:"Arbeitszeit & Urlaub",f:[["arbeitszeit","Normalarbeitszeit","38,5 bzw. 40 Stunden/Woche nach KV"],["urlaub","Urlaubsanspruch","25 Werktage/Jahr (§ 2 UrlG)"],["kuendigung","Kündigung","nach den gesetzlichen bzw. kollektivvertraglichen Fristen"],["verschwiegenheit","Verschwiegenheit","Der Arbeitnehmer wahrt Geschäfts- und Betriebsgeheimnisse während und nach dem Dienstverhältnis.","ta"]]},
    {t:"Unterschrift",f:[["ort","Ort"],["datum","Datum"]]},
    u()]};
  AT["vtr_at_kauf"]={ic:"🛒",n:"Kaufvertrag (privat, AT)",sn:"Kauf beweglicher Sachen — ABGB § 1053 ff.",price:"3,99 €",pgs:4,steps:[
    {t:"Parteien",f:[["verkaeufer","Verkäufer (Name, Anschrift)","","ta"],["kaeufer","Käufer (Name, Anschrift)","","ta"]]},
    {t:"Kaufgegenstand & Preis",f:[["sache","Kaufgegenstand (Beschreibung; bei Kfz: Marke, Typ, Bj., Fahrgestellnr., Kilometerstand)","","ta"],["zustand","Zustand / bekannte Mängel","Dem Verkäufer sind keine Mängel bekannt."],["preis","Kaufpreis (€)"],["zahlung","Zahlung","bei Übergabe, per Überweisung"],["gewaehrleistung","Gewährleistung","Verkauf unter Privaten ohne Gewähr, soweit gesetzlich zulässig; Arglist bleibt aufrecht."]]},
    {t:"Übergabe & Unterschrift",f:[["uebergabe","Übergabe (Ort, Datum)"],["gefahr","Gefahrübergang","Nutzen und Gefahr gehen mit Übergabe auf den Käufer über (§ 1064 ABGB)."],["ort","Ort"],["datum","Datum"]]},
    u()]};
  AT["vtr_at_darlehen"]={ic:"💶",n:"Darlehensvertrag (AT)",sn:"Gelddarlehen — ABGB § 983 ff.",price:"4,99 €",pgs:4,steps:[
    {t:"Parteien",f:[["geber","Darlehensgeber (Name, Anschrift)","","ta"],["nehmer","Darlehensnehmer (Name, Anschrift)","","ta"]]},
    {t:"Darlehen & Zinsen",f:[["betrag","Darlehensbetrag (€, in Zahl und Wort)","","ta"],["auszahlung","Auszahlung","per Überweisung am Tag des Vertragsabschlusses"],["zinsen","Zinsen","zinsenfrei / jährlich ____ %"],["rueckzahlung","Rückzahlung (Termin/Raten)","","ta"]]},
    {t:"Verzug & Unterschrift",f:[["verzug","Verzug","Bei Verzug gebühren die gesetzlichen Verzugszinsen."],["faelligstellung","Fälligstellung","Der Darlehensgeber kann bei Zahlungsverzug von mehr als sechs Wochen die gesamte offene Forderung fällig stellen."],["ort","Ort"],["datum","Datum"]]},
    u()]};
  AT["vtr_at_werk"]={ic:"🔧",n:"Werkvertrag (AT)",sn:"Herstellung eines Werks — ABGB § 1151 ff.",price:"4,99 €",pgs:4,steps:[
    {t:"Parteien",f:[["besteller","Besteller (Name, Anschrift)","","ta"],["unternehmer","Werkunternehmer (Name, Anschrift)","","ta"]]},
    {t:"Werk & Entgelt",f:[["werk","Zu erbringendes Werk (Umfang, Spezifikation)","","ta"],["material","Material","wird vom Unternehmer beigestellt"],["entgelt","Werklohn (€)"],["zahlung","Zahlungsplan","30 % Anzahlung, 70 % nach Abnahme"],["frist","Fertigstellungstermin"]]},
    {t:"Abnahme & Gewähr",f:[["abnahme","Abnahme","Nach Fertigstellung erfolgt eine gemeinsame Abnahme; Mängel sind zu protokollieren."],["gewaehr","Gewährleistung","Es gelten die gesetzlichen Gewährleistungsfristen des ABGB."],["ort","Ort"],["datum","Datum"]]},
    u()]};
  AT["vtr_at_leihe"]={ic:"🔑",n:"Leihvertrag (AT)",sn:"Unentgeltliche Gebrauchsüberlassung — ABGB § 971 ff.",price:"3,99 €",pgs:3,steps:[
    {t:"Parteien & Sache",f:[["verleiher","Verleiher (Name, Anschrift)","","ta"],["entlehner","Entlehner (Name, Anschrift)","","ta"],["sache","Verliehene Sache (Beschreibung, Zustand)","","ta"]]},
    {t:"Gebrauch & Rückgabe",f:[["zweck","Verwendungszweck","Der Entlehner darf die Sache nur bestimmungsgemäß und sorgfältig gebrauchen."],["dauer","Leihdauer / Rückgabetermin"],["kosten","Kosten","Gewöhnliche Erhaltungskosten trägt der Entlehner."],["rueckgabe","Rückgabe","Die Sache ist im ursprünglichen Zustand zurückzugeben; für Schäden über die gewöhnliche Abnützung hinaus haftet der Entlehner."],["ort","Ort"],["datum","Datum"]]},
    u()]};
  AT["vtr_at_nda"]={ic:"🔒",n:"Vertraulichkeitsvereinbarung (NDA, AT)",sn:"Beidseitige Geheimhaltung",price:"4,99 €",pgs:4,steps:[
    {t:"Parteien",f:[["p1","Partei 1 (Name, Anschrift)","","ta"],["p2","Partei 2 (Name, Anschrift)","","ta"]]},
    {t:"Gegenstand",f:[["zweck","Zweck der Zusammenarbeit","","ta"],["info","Vertrauliche Informationen","Alle mündlich, schriftlich oder elektronisch ausgetauschten geschäftlichen, technischen und personenbezogenen Informationen gelten als vertraulich."],["ausnahmen","Ausnahmen","Öffentlich bekannte oder rechtmäßig anderweitig erlangte Informationen sind ausgenommen."]]},
    {t:"Pflicht & Dauer",f:[["pflicht","Geheimhaltungspflicht","Die Parteien verwenden die Informationen nur für den Zweck und geben sie nicht an Dritte weiter."],["dauer","Dauer","Die Geheimhaltung gilt auch ____ Jahre nach Ende der Zusammenarbeit fort."],["ort","Ort"],["datum","Datum"]]},
    u()]};

  var CH={};
  CH["vtr_ch_miete"]={ic:"🏠",n:"Mietvertrag Wohnung (CH)",sn:"Wohnungsmiete — OR Art. 253 ff.",price:"4,99 €",pgs:5,steps:[
    {t:"Parteien",f:[["vermieter","Vermieter (Name, Adresse)","","ta"],["mieter","Mieter (Name, Adresse)","","ta"]]},
    {t:"Mietobjekt",f:[["objekt","Mietobjekt (Adresse, Zimmerzahl, Stockwerk)","","ta"],["zweck","Nutzung","Wohnzwecke"],["beginn","Mietbeginn"],["kuendigung","Kündigungsfrist","drei Monate auf einen ortsüblichen Termin (Art. 266c OR)"]]},
    {t:"Mietzins & Kaution",f:[["nettomiete","Nettomietzins (CHF/Monat)"],["nebenkosten","Nebenkosten (CHF/Monat)"],["faellig","Fälligkeit","monatlich im Voraus"],["kaution","Kaution / Mietzinsdepot","max. drei Monatsmieten auf Sperrkonto (Art. 257e OR)"]]},
    {t:"Übergabe & Pflichten",f:[["uebergabe","Zustand bei Übergabe","Ein gemeinsames Antrittsprotokoll wird erstellt und unterzeichnet.","ta"],["kleiner_unterhalt","Kleiner Unterhalt","Der Mieter trägt den kleinen Unterhalt (Art. 259 OR)."],["untermiete","Untermiete","Untervermietung nur mit Zustimmung des Vermieters (Art. 262 OR)."],["ort","Ort"],["datum","Datum"]]},
    u()]};
  CH["vtr_ch_arbeit"]={ic:"💼",n:"Arbeitsvertrag (CH)",sn:"Einzelarbeitsvertrag — OR Art. 319 ff.",price:"4,99 €",pgs:5,steps:[
    {t:"Parteien",f:[["arbeitgeber","Arbeitgeber (Firma, Adresse)","","ta"],["arbeitnehmer","Arbeitnehmer (Name, Adresse)","","ta"]]},
    {t:"Funktion & Lohn",f:[["funktion","Funktion / Stelle"],["arbeitsort","Arbeitsort"],["beginn","Stellenantritt"],["lohn","Monatslohn brutto (CHF)"],["auszahlung","Auszahlung","13× jährlich / 12× (bitte anpassen), Ende Monat"],["probezeit","Probezeit","drei Monate (Art. 335b OR)"]]},
    {t:"Arbeitszeit & Ferien",f:[["arbeitszeit","Wöchentliche Arbeitszeit","42 Stunden"],["ferien","Ferienanspruch","vier Wochen pro Jahr (Art. 329a OR)"],["kuendigung","Kündigungsfristen","nach Art. 335c OR"],["geheimhaltung","Geheimhaltung","Der Arbeitnehmer wahrt Geschäftsgeheimnisse (Art. 321a OR).","ta"]]},
    {t:"Unterschrift",f:[["ort","Ort"],["datum","Datum"]]},
    u()]};
  CH["vtr_ch_kauf"]={ic:"🛒",n:"Kaufvertrag (CH)",sn:"Fahrniskauf — OR Art. 184 ff.",price:"3,99 €",pgs:4,steps:[
    {t:"Parteien",f:[["verkaeufer","Verkäufer (Name, Adresse)","","ta"],["kaeufer","Käufer (Name, Adresse)","","ta"]]},
    {t:"Kaufgegenstand & Preis",f:[["sache","Kaufgegenstand (Beschreibung; bei Fahrzeug: Marke, Typ, Jahr, Stammnr., km)","","ta"],["zustand","Zustand / bekannte Mängel","Dem Verkäufer sind keine Mängel bekannt."],["preis","Kaufpreis (CHF)"],["zahlung","Zahlung","bei Übergabe"],["gewaehr","Gewährleistung","Verkauf unter Privaten ‚wie besehen', Freizeichnung im Rahmen von Art. 199 OR."]]},
    {t:"Übergabe & Unterschrift",f:[["uebergabe","Übergabe (Ort, Datum)"],["nutzen","Nutzen und Gefahr","gehen mit Vertragsabschluss auf den Käufer über (Art. 185 OR)."],["ort","Ort"],["datum","Datum"]]},
    u()]};
  CH["vtr_ch_darlehen"]={ic:"💶",n:"Darlehensvertrag (CH)",sn:"Gelddarlehen — OR Art. 312 ff.",price:"4,99 €",pgs:4,steps:[
    {t:"Parteien",f:[["geber","Darlehensgeber (Name, Adresse)","","ta"],["nehmer","Darlehensnehmer (Name, Adresse)","","ta"]]},
    {t:"Darlehen & Zinsen",f:[["betrag","Darlehensbetrag (CHF, in Zahl und Wort)","","ta"],["auszahlung","Auszahlung","per Überweisung bei Vertragsabschluss"],["zinsen","Zinsen","zinslos / jährlich ____ %"],["rueckzahlung","Rückzahlung (Termin/Raten)","","ta"]]},
    {t:"Verzug & Unterschrift",f:[["verzug","Verzug","Bei Verzug gelten die gesetzlichen Verzugszinsen (Art. 104 OR)."],["kuendigung","Kündigung","Mangels Abrede ist das Darlehen sechs Wochen nach Kündigung zurückzuzahlen (Art. 318 OR)."],["ort","Ort"],["datum","Datum"]]},
    u()]};
  CH["vtr_ch_werk"]={ic:"🔧",n:"Werkvertrag (CH)",sn:"Herstellung eines Werks — OR Art. 363 ff.",price:"4,99 €",pgs:4,steps:[
    {t:"Parteien",f:[["besteller","Besteller (Name, Adresse)","","ta"],["unternehmer","Unternehmer (Name, Adresse)","","ta"]]},
    {t:"Werk & Vergütung",f:[["werk","Zu erstellendes Werk (Umfang, Spezifikation)","","ta"],["material","Material","wird vom Unternehmer gestellt"],["verguetung","Werklohn (CHF)"],["zahlung","Zahlungsplan","30 % Akonto, 70 % nach Abnahme"],["frist","Ablieferungstermin"]]},
    {t:"Abnahme & Mängel",f:[["abnahme","Abnahme / Prüfung","Der Besteller prüft das Werk nach Ablieferung und zeigt Mängel an (Art. 367 OR)."],["gewaehr","Mängelrechte","Es gelten die gesetzlichen Rechte nach Art. 368 OR."],["ort","Ort"],["datum","Datum"]]},
    u()]};
  CH["vtr_ch_gebrauchsleihe"]={ic:"🔑",n:"Gebrauchsleihe (CH)",sn:"Unentgeltliche Gebrauchsüberlassung — OR Art. 305 ff.",price:"3,99 €",pgs:3,steps:[
    {t:"Parteien & Sache",f:[["verleiher","Verleiher (Name, Adresse)","","ta"],["entlehner","Entlehner (Name, Adresse)","","ta"],["sache","Überlassene Sache (Beschreibung, Zustand)","","ta"]]},
    {t:"Gebrauch & Rückgabe",f:[["zweck","Gebrauch","Nur bestimmungsgemässer, sorgfältiger Gebrauch (Art. 306 OR)."],["dauer","Dauer / Rückgabetermin"],["kosten","Kosten","Gewöhnliche Erhaltungskosten trägt der Entlehner."],["rueckgabe","Rückgabe","Rückgabe im ursprünglichen Zustand; Haftung für Schäden über normale Abnützung hinaus."],["ort","Ort"],["datum","Datum"]]},
    u()]};
  CH["vtr_ch_nda"]={ic:"🔒",n:"Geheimhaltungsvereinbarung (NDA, CH)",sn:"Beidseitige Vertraulichkeit",price:"4,99 €",pgs:4,steps:[
    {t:"Parteien",f:[["p1","Partei 1 (Name, Adresse)","","ta"],["p2","Partei 2 (Name, Adresse)","","ta"]]},
    {t:"Gegenstand",f:[["zweck","Zweck der Zusammenarbeit","","ta"],["info","Vertrauliche Informationen","Alle ausgetauschten geschäftlichen, technischen und personenbezogenen Informationen gelten als vertraulich."],["ausnahmen","Ausnahmen","Öffentlich bekannte oder rechtmässig anderweitig erlangte Informationen sind ausgenommen."]]},
    {t:"Pflicht & Dauer",f:[["pflicht","Geheimhaltung","Verwendung nur zum Zweck; keine Weitergabe an Dritte."],["dauer","Dauer","Gilt auch ____ Jahre nach Ende der Zusammenarbeit fort."],["konventionalstrafe","Konventionalstrafe (optional)","Bei Verletzung ist eine Konventionalstrafe von CHF ____ geschuldet; weiterer Schaden bleibt vorbehalten."],["ort","Ort"],["datum","Datum"]]},
    u()]};

  window.__XSET_ADD = window.__XSET_ADD || {};
  window.__XSET_ADD.AT = AT;
  window.__XSET_ADD.CH = CH;

  /* at_vertraege / ch_vertraege kategorileri -> modul */
  var CONTRACT_CATS={ at_vertraege:1, ch_vertraege:1 };
  function openVst(){ try{ if(typeof gP==="function"){ gP("vst"); return true; } }catch(e){} return false; }
  function catKey(arg){ try{ return (typeof arg==="string")?arg:((arg&&arg.cat)||""); }catch(e){ return ""; } }
  function wrapNav(){
    ["showCat","showCatDocs"].forEach(function(fn){
      try{
        if(typeof window[fn]!=="function" || window[fn].__chatch) return;
        var _o=window[fn];
        var w=function(arg){ try{ if(CONTRACT_CATS[catKey(arg)] && openVst()) return; }catch(e){} return _o.apply(this,arguments); };
        w.__chatch=1; window[fn]=w;
      }catch(e){}
    });
  }
  var n=0;(function loop(){ wrapNav(); if(n++<80) setTimeout(loop,600); })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'ac').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-atchvertrag-'.date('Ymd-His'), (string)@file_get_contents($file));
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_ATCH_VERTRAG')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_ATCH_VERTRAG diskte (".strlen($chk)." bayt)\n";
echo "\n✓ AT (ABGB) + CH (OR) sozlesme setleri module eklendi (7+7); kategoriler modele yonlendirildi.\n";
