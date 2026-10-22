<?php
/**
 * ChatHelp — apply-atch-vertrag2 (CH_ATCH_VERTRAG2) — AT (ABGB/EheG) ve CH
 *  (OR/ZGB) katalogunu +2'ser sozlesme ile parity'ye tasi.
 *  AT: Dienstleistungsvertrag (§ 1151 ABGB) + Scheidungsvergleich (§ 55a EheG,
 *  einvernehmliche Scheidung). CH: Auftrag/Dienstleistungsvertrag (Art. 394 OR)
 *  + Scheidungskonvention (Art. 111 ZGB). Form (XSETS) + PDF metni (NCX).
 *  Enjeksiyon: CH_VST_X callback'indeki `var xBak=null,xMode=null;` anchor'i
 *  ONUNE — orada XSETS.AT/CH (atch-hook ile olusmus), NCX, ucStep, ekX, V
 *  scope'ta. Tek str_replace, count-guard. Gomulu JS node --check ✓.
 * KULLANIM: pull2.php?key=...&files=apply-atch-vertrag2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-atch-vertrag2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_ATCH_VERTRAG2')!==false) exit("Zaten ekli (CH_ATCH_VERTRAG2).\n");
if (strpos($src,'CH_ATCH_VERTRAG')===false) exit("HATA: CH_ATCH_VERTRAG yok — once apply-atch-vertrag deploy edilmeli.\n");

$inj = <<<'CHJS'
/* ══ CH_ATCH_VERTRAG2 — AT/CH +2'ser sozlesme (parity) ══ */
    try{
      var _ekDACH=function(d){ return (typeof ekX==="function")?ekX(d,"Weitere individuelle Vereinbarungen bestehen nicht."):"Weitere individuelle Vereinbarungen bestehen nicht."; };
      /* ── AT (ABGB / EheG) ── */
      var _atAdd={
        "vtr_at_dienstleistung":{ic:"🧰",n:"Dienstleistungsvertrag (AT)",sn:"Freier Dienst-/Werkvertrag — ABGB § 1151 ff.",price:"4,99 €",pgs:4,steps:[
          {t:"Parteien",f:[["auftraggeber","Auftraggeber (Name/Firma, Anschrift)","","ta"],["dienstleister","Dienstleister (Name, Anschrift, UID falls vorhanden)","","ta"]]},
          {t:"Leistung & Honorar",f:[["leistung","Leistungsbeschreibung / Umfang","","ta"],["frist","Leistungszeitraum / Termin"],["honorar","Honorar (€) zzgl. USt","zzgl. 20 % USt, sofern steuerpflichtig"],["zahlung","Zahlung","30 % bei Auftrag, 70 % nach Leistung"],["selbststaendig","Rechtsnatur","Der Dienstleister erbringt die Leistung selbstständig und weisungsfrei; es besteht kein Dienstverhältnis."]]},
          {t:"Pflichten & Unterschrift",f:[["ergebnis","Nutzungsrechte","Die Arbeitsergebnisse stehen dem Auftraggeber nach vollständiger Zahlung zu."],["verschwiegenheit","Verschwiegenheit","Beide Teile behandeln überlassene Informationen vertraulich."],["ort","Ort"],["datum","Datum"]]},
          ucStep("Eigene Klauseln (optional)","Zusatzklausel — Titel","Zusatzklausel — Text")]},
        "vtr_at_scheidung":{ic:"⚖️",n:"Scheidungsvergleich (AT)",sn:"Einvernehmliche Scheidung — § 55a EheG",price:"4,99 €",pgs:5,steps:[
          {t:"Ehegatten",f:[["e1","Ehegatte 1 — Name, Anschrift","","ta"],["e2","Ehegatte 2 — Name, Anschrift","","ta"],["ehe_datum","Tag der Eheschließung"],["trennung","Aufhebung der ehelichen Gemeinschaft seit"]]},
          {t:"Kinder & Unterhalt",f:[["kinder","Gemeinsame Kinder (Name, Geburtsdatum; sonst 'keine')","keine minderjährigen Kinder","ta"],["obsorge","Obsorge","gemeinsame Obsorge; hauptsächlicher Aufenthalt bei"],["kontakt","Kontaktrecht","nach einvernehmlicher Regelung","ta"],["kindesunterhalt","Kindesunterhalt","nach der Prozentwertmethode"]]},
          {t:"Vermögen & Wohnung",f:[["ehegattenunterhalt","Ehegattenunterhalt","Beide verzichten wechselseitig auf Ehegattenunterhalt, auch für den Fall geänderter Verhältnisse und der Not."],["aufteilung","Aufteilung ehelichen Gebrauchsvermögens","einvernehmlich aufgeteilt (§§ 81 ff. EheG)","ta"],["wohnung","Ehewohnung","verbleibt bei"],["ersparnisse","Eheliche Ersparnisse","einvernehmlich geteilt"]]},
          {t:"Unterschrift",f:[["ort","Ort"],["datum","Datum"]]},
          ucStep("Eigene Klauseln (optional)","Zusatzklausel — Titel","Zusatzklausel — Text")]}
      };
      /* ── CH (OR / ZGB) ── */
      var _chAdd={
        "vtr_ch_auftrag":{ic:"🧰",n:"Auftrag / Dienstleistungsvertrag (CH)",sn:"Einfacher Auftrag — OR Art. 394 ff.",price:"4,99 €",pgs:4,steps:[
          {t:"Parteien",f:[["auftraggeber","Auftraggeber (Name/Firma, Adresse)","","ta"],["beauftragter","Beauftragter (Name, Adresse)","","ta"]]},
          {t:"Leistung & Honorar",f:[["auftrag","Umfang des Auftrags / Leistung","","ta"],["frist","Zeitraum / Termin"],["honorar","Honorar (CHF) zzgl. MWST","zzgl. MWST, sofern steuerpflichtig"],["zahlung","Zahlung","30 % bei Auftrag, 70 % nach Ausführung"],["natur","Rechtsnatur","Der Beauftragte handelt selbstständig; es besteht kein Arbeitsverhältnis."]]},
          {t:"Pflichten & Unterschrift",f:[["sorgfalt","Sorgfalt","Der Beauftragte handelt mit der Sorgfalt eines ordentlichen Beauftragten (Art. 398 OR)."],["ergebnis","Nutzungsrechte","Die Arbeitsergebnisse stehen dem Auftraggeber nach vollständiger Zahlung zu."],["geheim","Geheimhaltung","Beide Teile behandeln überlassene Informationen vertraulich."],["ort","Ort"],["datum","Datum"]]},
          ucStep("Eigene Klauseln (optional)","Zusatzklausel — Titel","Zusatzklausel — Text")]},
        "vtr_ch_scheidung":{ic:"⚖️",n:"Scheidungskonvention (CH)",sn:"Scheidung auf gemeinsames Begehren — Art. 111 ZGB",price:"4,99 €",pgs:5,steps:[
          {t:"Ehegatten",f:[["e1","Ehegatte 1 — Name, Adresse","","ta"],["e2","Ehegatte 2 — Name, Adresse","","ta"],["ehe_datum","Tag der Eheschließung"],["getrennt","Getrenntlebend seit"]]},
          {t:"Kinder",f:[["kinder","Gemeinsame Kinder (Name, Geburtsdatum; sonst 'keine')","keine minderjährigen Kinder","ta"],["sorge","Elterliche Sorge","gemeinsame elterliche Sorge; Obhut bei"],["betreuung","Betreuungsanteile / persönlicher Verkehr","nach Vereinbarung","ta"],["kindesunterhalt","Kinderunterhalt","gemäss Unterhaltsberechnung"]]},
          {t:"Unterhalt & Vermögen",f:[["ehegattenunterhalt","Nachehelicher Unterhalt","Beide verzichten wechselseitig auf nachehelichen Unterhalt (Art. 125 ZGB)."],["gueterrecht","Güterrechtliche Auseinandersetzung","einvernehmlich vollzogen; keine gegenseitigen Ansprüche mehr","ta"],["wohnung","Familienwohnung","verbleibt bei"],["vorsorge","Berufliche Vorsorge","Die Guthaben der beruflichen Vorsorge werden nach Art. 122 ff. ZGB hälftig geteilt."]]},
          {t:"Unterschrift",f:[["ort","Ort"],["datum","Datum"]]},
          ucStep("Eigene Klauseln (optional)","Zusatzklausel — Titel","Zusatzklausel — Text")]}
      };
      if(typeof XSETS!=="undefined"&&XSETS){
        XSETS.AT=XSETS.AT||{}; for(var _ka in _atAdd){ XSETS.AT[_ka]=_atAdd[_ka]; }
        XSETS.CH=XSETS.CH||{}; for(var _kc in _chAdd){ XSETS.CH[_kc]=_chAdd[_kc]; }
      }
      if(typeof NCX!=="undefined"&&NCX){
        NCX["vtr_at_dienstleistung"]={n:"DIENSTLEISTUNGSVERTRAG",sn:"§ 1151 ff. ABGB",A:"Auftraggeber",B:"Dienstleister",cl:[
          {no:"§ 1",t:"Parteien",b:function(d){return V(d.auftraggeber)+" (Auftraggeber) und "+V(d.dienstleister)+" (Dienstleister). "+V(d.selbststaendig)+"";}},
          {no:"§ 2",t:"Leistung und Frist",b:function(d){return "Leistung: "+V(d.leistung)+". Zeitraum: "+V(d.frist)+".";}},
          {no:"§ 3",t:"Honorar",b:function(d){return "Honorar: "+V(d.honorar)+". Zahlung: "+V(d.zahlung)+".";}},
          {no:"§ 4",t:"Nutzungsrechte und Verschwiegenheit",b:function(d){return V(d.ergebnis)+" "+V(d.verschwiegenheit)+"";}},
          {no:"§ 5",t:"Individuelle Vereinbarungen",b:_ekDACH}]};
        NCX["vtr_at_scheidung"]={n:"SCHEIDUNGSVERGLEICH",sn:"§ 55a EheG — einvernehmliche Scheidung",A:"Ehegatte 1",B:"Ehegatte 2",cl:[
          {no:"§ 1",t:"Ehegatten",b:function(d){return "Die Ehegatten "+V(d.e1)+" und "+V(d.e2)+", verheiratet seit "+V(d.ehe_datum)+", leben seit "+V(d.trennung)+" getrennt und beantragen die einvernehmliche Scheidung nach § 55a EheG.";}},
          {no:"§ 2",t:"Kinder und Kindesunterhalt",b:function(d){return "Kinder: "+V(d.kinder)+". Obsorge: "+V(d.obsorge)+". Kontaktrecht: "+V(d.kontakt)+". Kindesunterhalt: "+V(d.kindesunterhalt)+".";}},
          {no:"§ 3",t:"Ehegattenunterhalt",b:function(d){return V(d.ehegattenunterhalt)+"";}},
          {no:"§ 4",t:"Vermögen und Wohnung",b:function(d){return "Aufteilung: "+V(d.aufteilung)+". Ehewohnung: "+V(d.wohnung)+". Ersparnisse: "+V(d.ersparnisse)+".";}},
          {no:"§ 5",t:"Individuelle Vereinbarungen",b:_ekDACH}]};
        NCX["vtr_ch_auftrag"]={n:"AUFTRAG / DIENSTLEISTUNGSVERTRAG",sn:"Art. 394 ff. OR",A:"Auftraggeber",B:"Beauftragter",cl:[
          {no:"Art. 1",t:"Parteien",b:function(d){return V(d.auftraggeber)+" (Auftraggeber) und "+V(d.beauftragter)+" (Beauftragter). "+V(d.natur)+"";}},
          {no:"Art. 2",t:"Auftrag und Frist",b:function(d){return "Auftrag: "+V(d.auftrag)+". Zeitraum: "+V(d.frist)+".";}},
          {no:"Art. 3",t:"Honorar und Sorgfalt",b:function(d){return "Honorar: "+V(d.honorar)+". Zahlung: "+V(d.zahlung)+". "+V(d.sorgfalt)+"";}},
          {no:"Art. 4",t:"Nutzungsrechte und Geheimhaltung",b:function(d){return V(d.ergebnis)+" "+V(d.geheim)+"";}},
          {no:"Art. 5",t:"Individuelle Vereinbarungen",b:_ekDACH}]};
        NCX["vtr_ch_scheidung"]={n:"SCHEIDUNGSKONVENTION",sn:"Art. 111 ZGB — Scheidung auf gemeinsames Begehren",A:"Ehegatte 1",B:"Ehegatte 2",cl:[
          {no:"Art. 1",t:"Ehegatten",b:function(d){return "Die Ehegatten "+V(d.e1)+" und "+V(d.e2)+", verheiratet seit "+V(d.ehe_datum)+", leben seit "+V(d.getrennt)+" getrennt und stellen ein gemeinsames Scheidungsbegehren (Art. 111 ZGB).";}},
          {no:"Art. 2",t:"Kinder",b:function(d){return "Kinder: "+V(d.kinder)+". Elterliche Sorge: "+V(d.sorge)+". Betreuung/Verkehr: "+V(d.betreuung)+". Kinderunterhalt: "+V(d.kindesunterhalt)+".";}},
          {no:"Art. 3",t:"Nachehelicher Unterhalt",b:function(d){return V(d.ehegattenunterhalt)+"";}},
          {no:"Art. 4",t:"Güterrecht, Wohnung und Vorsorge",b:function(d){return "Güterrecht: "+V(d.gueterrecht)+". Familienwohnung: "+V(d.wohnung)+". "+V(d.vorsorge)+"";}},
          {no:"Art. 5",t:"Individuelle Vereinbarungen",b:_ekDACH}]};
      }
    }catch(_atch2E){}
    /*CH_ATCH_VERTRAG2*/
CHJS;

$anchor = 'var xBak=null,xMode=null;';
$c = substr_count($src,$anchor);
if ($c!==1) exit("HATA: xBak anchor $c kez bulundu (1 bekleniyordu). index DEGISTIRILMEDI.\n");
$new = str_replace($anchor, $inj.$anchor, $src);

$tmp = tempnam(sys_get_temp_dir(),'a2').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-atch2-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_ATCH_VERTRAG2')===false || strpos($chk,'vtr_at_scheidung')===false || strpos($chk,'vtr_ch_scheidung')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_ATCH_VERTRAG2 + AT/CH sozlesmeleri diskte (".strlen($chk)." bayt)\n";
echo "\n✓ AT: +2 (Dienstleistungsvertrag, Scheidungsvergleich §55a EheG); CH: +2 (Auftrag Art.394 OR, Scheidungskonvention Art.111 ZGB).\n";
echo "  Her biri form (XSETS) + PDF metni (NCX) ile; ilgili hukukta modulde otomatik listelenir.\n";
