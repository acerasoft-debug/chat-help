<?php
/**
 * ChatHelp — apply-vize-v2-prompts2 (CH_VIZE_V2P) — err anchor V2'ye ozgu 2 satirla ayristirildi (v1'deki ayni satirla cakisiyordu) — V2 belge sablonlari
 *  KONSOLOSLUK STANDARDINA yukseltildi: her belge icin zorunlu yapi
 *  (gonderen blogu, tarih, konu satiri, kimlik/pasaport blogu, finansman+
 *  sigorta+donus guvenceleri, imza blogu) + kelime araligi + "sadece verilen
 *  gercek bilgiler, uydurma yok" kurali. Itinerary artik donemin HER gunu
 *  icin satir uretir. Ayrica "Belge uretilemedi" hatasi artik SEBEBIYLE
 *  gosterilir (api error mesaji eklenir) — "evrak vermiyor" durumunda ne
 *  oldugu gorunur. 6 count-guard'li degisiklik, CH_VIZE_V2 blogu icinde.
 * KULLANIM: pull2.php?key=...&files=apply-vize-v2-prompts2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-vize-v2-prompts2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_VIZE_V2')===false) exit("HATA: once CH_VIZE_V2 gerekli.\n");
if (strpos($src,'CH_VIZE_V2P')!==false) exit("Zaten ekli (CH_VIZE_V2P).\n");

$fail=[]; $n=0;
function rep(&$src,&$fail,&$n,$label,$o,$nw){
  $c=substr_count($src,$o);
  if($c!==1){ echo "  ✗ [$label] anchor ($c)\n"; $fail[]=$label; return; }
  $src=str_replace($o,$nw,$src); $n++;
  echo "  ✓ [$label] konsolosluk-standardi sablon uygulandi\n";
}
$o_cover = <<<'ANC'
    if(g==='cover') return 'Erstelle ein formelles, überzeugendes Motivations-/Begleitschreiben (cover letter) für einen Visumantrag. '+base+' Erwähne den Reisezweck, die genauen Daten, die Finanzierung, die Reiseversicherung und die feste Rückkehrabsicht (Bindungen zum Heimatland). Adressiere es an das zuständige Konsulat. Schreibe den vollständigen Brief ausschließlich auf '+ln+', mit Datum und Unterschriftszeile. Gib NUR den Brieftext aus.'+tail;
ANC;
$n_cover = <<<'ANC'
    if(g==='cover') return 'Erstelle ein formelles, konsulatstaugliches Motivations-/Begleitschreiben (cover letter) für einen Visumantrag mit GENAU dieser Struktur: (1) Absenderblock (Name, Adresse, Passnummer), (2) Ort/Datum, (3) Empfängerzeile an das zuständige Konsulat / die Visastelle, (4) Betreffzeile mit Visumtyp und Reisedaten, (5) Anrede, (6) Absätze: Reisezweck und geplantes Programm, Unterkunft, Finanzierung der Reise, Reisekrankenversicherung (mind. 30.000 EUR Deckung), verbindliche Rückkehrabsicht mit konkreten Bindungen an das Heimatland (Arbeit/Familie/Wohnsitz), (7) höfliche Schlussformel, (8) Unterschriftszeile mit vollem Namen. Umfang 280–380 Wörter. '+base+' Schreibe ausschließlich auf '+ln+'. Verwende NUR die gegebenen echten Angaben, erfinde NICHTS; fehlende Angaben elegant weglassen. Gib NUR den Brieftext aus.'+tail;
ANC;
rep($src,$fail,$n,'cover',$o_cover,$n_cover);
$o_invitation = <<<'ANC'
    if(g==='invitation') return 'Erstelle ein formelles Einladungsschreiben, das die EINLADENDE Person im Zielland unterschreibt, zur Unterstützung dieses Visumantrags. '+base+' Die einladende Person bestätigt Beziehung, Unterkunft und ggf. Kostenübernahme sowie die fristgerechte Rückkehr des Gastes. Schreibe ausschließlich auf '+ln+'. Gib NUR den Brieftext mit Unterschriftszeile für den Gastgeber aus.'+tail;
ANC;
$n_invitation = <<<'ANC'
    if(g==='invitation') return 'Erstelle ein formelles, konsulatstaugliches Einladungsschreiben, das die EINLADENDE Person im Zielland unterschreibt, mit GENAU dieser Struktur: (1) Absenderblock des Gastgebers (Name, Adresse im Zielland), (2) Ort/Datum, (3) Titelzeile Einladungsschreiben, (4) Identität des Gastes (Name, Geburtsdatum, Passnummer), (5) Beziehung zum Gast, (6) Aufenthaltszeitraum und Unterkunftsadresse, (7) Erklärung zu Unterkunft und ggf. Kostenübernahme, (8) Zusicherung der fristgerechten Ausreise des Gastes, (9) Unterschriftszeile des Gastgebers. Umfang 220–320 Wörter. '+base+' Schreibe ausschließlich auf '+ln+'. Verwende NUR die gegebenen echten Angaben, erfinde NICHTS. Gib NUR den Brieftext aus.'+tail;
ANC;
rep($src,$fail,$n,'invitation',$o_invitation,$n_invitation);
$o_employer = <<<'ANC'
    if(g==='employer') return 'Erstelle ein formelles Arbeitgeber-Schreiben, das der ARBEITGEBER unterschreibt: bestätigt Anstellung, Position, Gehalt, den genehmigten Urlaub für den Reisezeitraum und die Rückkehr an den Arbeitsplatz. '+base+' Schreibe ausschließlich auf '+ln+'. Gib NUR den Brieftext aus, mit Firmenkopf-Zeile und Unterschriftszeile.'+tail;
ANC;
$n_employer = <<<'ANC'
    if(g==='employer') return 'Erstelle eine formelle, konsulatstaugliche Arbeitgeberbescheinigung mit Freistellungserklärung, die der ARBEITGEBER unterschreibt, mit GENAU dieser Struktur: (1) Firmenkopf-Zeile (Firmenname, Ort), (2) Ort/Datum, (3) Titel Arbeitgeberbescheinigung / Urlaubsfreistellung, (4) Identität des Mitarbeiters (Name, Geburtsdatum, Passnummer), Position und Beschäftigungsbeginn, ggf. Gehalt, (5) Bestätigung des genehmigten Urlaubs GENAU für den Reisezeitraum, (6) Bestätigung, dass der Arbeitsplatz nach der Rückkehr erhalten bleibt, (7) Kontaktzeile für Rückfragen, (8) Unterschriftszeile (Geschäftsführung/Personalabteilung) mit Firmenstempel-Hinweis. Umfang 200–300 Wörter. '+base+' Schreibe ausschließlich auf '+ln+'. Verwende NUR die gegebenen echten Angaben, erfinde NICHTS. Gib NUR den Brieftext aus.'+tail;
ANC;
rep($src,$fail,$n,'employer',$o_employer,$n_employer);
$o_selfemployed = <<<'ANC'
    if(g==='selfemployed') return 'Erstelle eine formelle Selbstständigen-Erklärung (Eigenerklärung des Geschäftsinhabers) für diesen Visumantrag: bestätigt die selbstständige Tätigkeit, die Firma, die Fortführung des Geschäfts während der Reise und die Rückkehr. '+base+' Schreibe ausschließlich auf '+ln+'. Gib NUR den Brieftext mit Unterschriftszeile aus.'+tail;
ANC;
$n_selfemployed = <<<'ANC'
    if(g==='selfemployed') return 'Erstelle eine formelle, konsulatstaugliche Eigenerklärung eines selbstständigen Unternehmers / Geschäftsinhabers mit GENAU dieser Struktur: (1) Briefkopf (Firmenname, Ort), (2) Ort/Datum, (3) Titel Eigenerklärung des Geschäftsinhabers, (4) Identität (Name, Geburtsdatum, Passnummer) und Firma (Name, ggf. Steuernummer/Registereintrag, Tätigkeitsfeld), (5) Erklärung der aktiven selbstständigen Tätigkeit, ggf. mit Einkommensangabe, (6) Bestätigung, dass der Betrieb während der Reise fortgeführt wird und die Reise selbst finanziert ist, (7) verbindliche Rückkehrzusage wegen der Geschäftsverantwortung, (8) Unterschriftszeile mit Firmenstempel-Hinweis. Umfang 200–300 Wörter. '+base+' Schreibe ausschließlich auf '+ln+'. Verwende NUR die gegebenen echten Angaben, erfinde NICHTS. Gib NUR den Brieftext aus.'+tail;
ANC;
rep($src,$fail,$n,'selfemployed',$o_selfemployed,$n_selfemployed);
$o_itinerary = <<<'ANC'
    if(g==='itinerary') return 'Erstelle einen übersichtlichen Tages-Reiseplan (itinerary) für diesen Visumantrag. '+base+' Liste für jeden Tag Datum, Ort/Stadt und geplante Aktivität. Schreibe ausschließlich auf '+ln+'. Gib NUR den Reiseplan aus.'+tail;
ANC;
$n_itinerary = <<<'ANC'
    if(g==='itinerary') return 'Erstelle einen konsulatstauglichen, vollständigen Tages-Reiseplan (itinerary) mit GENAU dieser Struktur: (1) Kopfblock: Reisender (Name, Passnummer), Reisezeitraum, Städte, (2) danach für JEDEN einzelnen Tag des Zeitraums eine Zeile im Format Tag N — TT.MM.JJJJ — Stadt — geplante Aktivität — Unterkunft, (3) abschließend eine Hinweiszeile, dass Buchungen als Reservierungen vorliegen. Nutze die angegebenen Plan- und Unterkunftsangaben und verteile sie sinnvoll auf die Tage; erfinde keine konkreten Buchungsnummern. '+base+' Schreibe ausschließlich auf '+ln+'. Gib NUR den Reiseplan aus.'+tail;
ANC;
rep($src,$fail,$n,'itinerary',$o_itinerary,$n_itinerary);
$o_err = <<<'ANC'
        var txt=(j&&j.reply)?clean(j.reply):'';
        if(!txt){ alert('Belge üretilemedi, lütfen tekrar deneyin.'); return; }
ANC;
$n_err = <<<'ANC'
        var txt=(j&&j.reply)?clean(j.reply):'';
        if(!txt){ var em=(j&&(j.error||j.message))?(' — '+(j.error||j.message)):''; alert('Belge üretilemedi'+em+'. Tekrar deneyin; sürerse giriş/paket durumunuzu kontrol edin.'); return; }
ANC;
rep($src,$fail,$n,'err',$o_err,$n_err);

if ($fail) { echo "\nHATA: eslesmeyen: ".implode(', ',$fail)." — index DEGISTIRILMEDI.\n"; exit; }
$src = str_replace('/* CH_VIZE_V2 — vize modulu tek parca','/* CH_VIZE_V2 + CH_VIZE_V2P — vize modulu tek parca',$src);

$tmp = tempnam(sys_get_temp_dir(),'vp2').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-v2p-'.date('Ymd-His'), (string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_VIZE_V2P')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "\n  ✓ DOGRULAMA: CH_VIZE_V2P diskte (".strlen($chk)." bayt)\n";
echo "\n✓ 5 belge sablonu konsolosluk standardinda: yapi zorunlu (gonderen/\n";
echo "   tarih/konu/kimlik/finansman/sigorta/donus/imza), uydurma yasak,\n";
echo "   itinerary her gun icin satir. Hatalar artik sebebiyle gorunur.\n";
