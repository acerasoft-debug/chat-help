<?php
/**
 * ChatHelp — apply-de-minijob-gmbh (CH_DE_MINIJOB) — DE modulune 2 sozlesme ekle:
 *  • Minijob-Arbeitsvertrag (§ 8 SGB IV, 556 € siniri, Mindestlohn)
 *  • GmbH-Gesellschaftsvertrag / Satzung (§ 2 / § 7 GmbHG, 25.000 € Stammkapital)
 *  Her ikisi de "Optionale Zusatzklauseln" adiminda SILINEBILIR (default-dolu)
 *  ek maddeler icerir + ucStep ile serbest kullanici klozu.
 *  Enjeksiyon: CH_VST_X closure'indaki `CT["nc_sfv"]=...` anchor'i ONUNE (ayni
 *  scope: CT ve ucStep hazir; sonda renderCat() otomatik listeler). Tek
 *  str_replace, count-guard. Gomulu JS node --check ile dogrulandi.
 * KULLANIM: pull2.php?key=...&files=apply-de-minijob-gmbh.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-de-minijob-gmbh BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_DE_MINIJOB')!==false) exit("Zaten ekli (CH_DE_MINIJOB).\n");

/* ── Enjekte edilecek 2 sozlesme (ayni closure scope: CT + ucStep hazir) ── */
$inj = <<<'CHJS'
/*CH_DE_MINIJOB*/
      CT["nc_minijob"]={ic:"🧹",n:"Minijob-Arbeitsvertrag",sn:"Geringfügige Beschäftigung bis 556 € (§ 8 SGB IV, Mindestlohn beachten)",price:"3,99 €",pgs:5,steps:[
        {t:"Parteien",f:[["ag_name","Arbeitgeber — Name/Firma & Anschrift","","ta"],["an_name","Arbeitnehmer — Name & Anschrift","","ta"]]},
        {t:"Tätigkeit & Vergütung",f:[["taetigkeit","Tätigkeit / Aufgaben","","ta"],["beginn","Beschäftigungsbeginn"],["befristung","Befristung","unbefristet"],["stundenlohn","Stundenlohn (€, mind. gesetzl. Mindestlohn)"],["monatslohn","Monatliche Vergütung (max. 556 €)"],["auszahlung","Auszahlung","bis zum letzten Werktag des Monats, unbar"]]},
        {t:"Arbeitszeit & Urlaub",f:[["arbeitszeit","Wöchentliche/monatliche Arbeitszeit"],["lage","Lage der Arbeitszeit","nach Absprache"],["urlaub","Urlaubsanspruch","anteilig nach BUrlG"],["entgeltfort","Entgeltfortzahlung","im Krankheitsfall und an Feiertagen nach Gesetz"],["probezeit","Probezeit","einen Monat"]]},
        {t:"Optionale Zusatzklauseln (löschen, wenn nicht gewünscht)",f:[["opt_verschw","Verschwiegenheit","Der Arbeitnehmer verpflichtet sich, über Betriebs- und Geschäftsgeheimnisse Stillschweigen zu bewahren – auch nach Beendigung des Arbeitsverhältnisses.","ta"],["opt_neben","Nebentätigkeit","Nebentätigkeiten sind dem Arbeitgeber vorher anzuzeigen; sie dürfen die vertraglichen Pflichten nicht beeinträchtigen.","ta"],["opt_verfall","Ausschlussfrist","Alle Ansprüche aus dem Arbeitsverhältnis verfallen, wenn sie nicht innerhalb von drei Monaten ab Fälligkeit schriftlich geltend gemacht werden (gesetzlicher Mindestlohn ausgenommen).","ta"]]},
        ucStep("Eigene Klauseln (optional)","Zusatzklausel — Titel","Zusatzklausel — Text")]};
      CT["nc_gmbh"]={ic:"🏢",n:"GmbH-Gesellschaftsvertrag (Satzung)",sn:"Gründung einer GmbH – notarielle Beurkundung erforderlich (§ 2 GmbHG)",price:"4,99 €",pgs:6,steps:[
        {t:"Firma & Sitz",f:[["firma","Firma der Gesellschaft (mit Zusatz „GmbH“)"],["sitz","Sitz der Gesellschaft (Ort)"],["gegenstand","Gegenstand des Unternehmens","","ta"],["gj","Geschäftsjahr","Kalenderjahr"]]},
        {t:"Stammkapital & Gesellschafter",f:[["stammkapital","Stammkapital (mind. 25.000 €)"],["gesellschafter","Gesellschafter & Stammeinlagen (Name, Betrag, Geschäftsanteil)","","ta"],["einzahlung","Einzahlung","Auf jede Stammeinlage ist mindestens die Hälfte, insgesamt mindestens 12.500 €, sofort einzuzahlen (§ 7 GmbHG)."]]},
        {t:"Geschäftsführung & Vertretung",f:[["gf","Geschäftsführer (Name)"],["vertretung","Vertretung","Ist nur ein Geschäftsführer bestellt, vertritt er allein; bei mehreren gemeinschaftlich, soweit nichts anderes bestimmt ist."],["befreiung_181","Befreiung von § 181 BGB","Den Geschäftsführern kann Befreiung von den Beschränkungen des § 181 BGB erteilt werden."]]},
        {t:"Beschlüsse & Ergebnis",f:[["beschluss","Gesellschafterbeschlüsse","werden mit einfacher Mehrheit der abgegebenen Stimmen gefasst, soweit Gesetz oder Vertrag nichts anderes bestimmen; je 1 € eines Geschäftsanteils gewährt eine Stimme."],["ergebnis","Ergebnisverwendung","Über die Verwendung des Jahresergebnisses beschließen die Gesellschafter."]]},
        {t:"Optionale Zusatzklauseln (löschen, wenn nicht gewünscht)",f:[["opt_vinkul","Vinkulierung / Vorkaufsrecht","Die Abtretung von Geschäftsanteilen bedarf der Zustimmung der Gesellschafterversammlung. Den übrigen Gesellschaftern steht ein Vorkaufsrecht im Verhältnis ihrer Beteiligung zu.","ta"],["opt_wettbewerb","Wettbewerbsverbot","Den Gesellschaftern ist es untersagt, der Gesellschaft während ihrer Beteiligung unmittelbar oder mittelbar Wettbewerb zu machen.","ta"],["opt_einziehung","Einziehung von Geschäftsanteilen","Die Einziehung von Geschäftsanteilen ist zulässig, insbesondere bei Insolvenz, Pfändung oder wichtigem Grund; die Abfindung richtet sich nach dem Verkehrswert.","ta"],["opt_nachfolge","Nachfolgeklausel","Beim Tod eines Gesellschafters wird die Gesellschaft mit den Erben fortgesetzt, sofern die Gesellschafterversammlung nicht die Einziehung beschließt.","ta"]]},
        ucStep("Eigene Klauseln (optional)","Zusatzklausel — Titel","Zusatzklausel — Text")]};

CHJS;

$anchor = 'CT["nc_sfv"]={ic:"⚖️",n:"Scheidungsfolgenvereinbarung"';
$c = substr_count($src,$anchor);
if ($c!==1) exit("HATA: nc_sfv anchor $c kez bulundu (1 bekleniyordu). index DEGISTIRILMEDI.\n");
$new = str_replace($anchor, $inj.$anchor, $src);

$tmp = tempnam(sys_get_temp_dir(),'mj').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-deminijob-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_DE_MINIJOB')===false || strpos($chk,'nc_minijob')===false || strpos($chk,'nc_gmbh')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_DE_MINIJOB + nc_minijob + nc_gmbh diskte (".strlen($chk)." bayt)\n";
echo "\n✓ DE modulune Minijob-Vertrag + GmbH-Gesellschaftsvertrag eklendi (opsiyonlu ek maddeli).\n";
