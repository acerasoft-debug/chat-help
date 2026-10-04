<?php
/**
 * ChatHelp — BELGE KALITE KOK DUZELTMESI (api.php doGenerate)
 * ------------------------------------------------------------------------
 *  Bulgular (dump-anon):
 *   - Taslak prompt CASE DETAILS'de cevaplari GERCEK deger olarak veriyor,
 *     ama "Keep ALL [PLACEHOLDERS]" + "SENIOR lawyer/complete" kurallari AI'yi
 *     kundennummer'i [KUNDENNUMMER] placeholder'ina cevirmeye itiyor.
 *   - Final adima answersText HIC gecmiyor; sadece profil haritasi ($ph)
 *     veriliyor -> [KUNDENNUMMER] geri konamiyor, belgede kaliyor.
 *   - Kurallar belgeyi gereksiz uzatiyor.
 *
 *  Duzeltmeler (CH_DOCQUAL):
 *   1) Sadece SENDER placeholder'lari korunur; CASE DETAILS degerleri inline
 *      kullanilir (yeni bracket uydurma yok) -> girilen cevaplar belgede cikar.
 *   2) Uzunluk belge turune gore: basit dilekce KISA (1-2 madde); karmasik derin.
 *   3) Sadece GERCEK emsal karar (supheliyse hic yazma).
 *   4) Sadece ilgili kanun maddeleri, uydurma yasak.
 *   5) Muhatap adi/adresi: CASE DETAILS'den al; emin degilse temiz bosluk, bracket yok.
 *   6) Final adima answersText eklenir + kalan TUM bracket'ler doldurulur/temizlenir.
 *
 * KULLANIM: pull-updates.php?files=apply-docquality-fix.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-docquality-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/api.php";
if(!file_exists($file)) exit("api.php yok\n");
$src=file_get_contents($file); $start=$src;
if(strpos($src,"CH_DOCQUAL")!==false) exit("Zaten ekli (CH_DOCQUAL).\n");

$edits=[];
/* 1) SENDER-only placeholder + cevaplari inline kullan (KOK bug) */
$edits[]=[
 '- Keep ALL [PLACEHOLDERS] exactly as written',
 '- The ONLY placeholders to keep are the SENDER fields shown above ([VORNAME], [NACHNAME], [ADRESSE], [PLZ_ORT], [GEBURTSDATUM], [TELEFON], [EMAIL], [DATUM_HEUTE], [ORT]). For every fact in CASE DETAILS write the ACTUAL value inline. NEVER invent new bracketed placeholders such as [KUNDENNUMMER] or [VERTRAGSNUMMER] when the value is given. If a needed detail is truly missing, write a short blank ________ instead of a bracket. /*CH_DOCQUAL*/'
];
/* 2) uzunluk belge turune gore */
$edits[]=[
 'Write at the standard of a SENIOR lawyer: precise, complete, persuasive',
 'Write like a focused practicing lawyer, result-oriented; match length to the matter (routine letters and cancellations stay short with 1-2 key statutes, only genuinely complex disputes get depth)'
];
/* 3) fact kullan, sisirme, basit=kisa */
$edits[]=[
 '- Address every fact from CASE DETAILS; omit nothing the recipient must act on',
 '- Use every relevant fact from CASE DETAILS (dates, numbers, names) as concrete values; do not pad or restate the obvious; keep simple documents short'
];
/* 4) gercek emsal karar kurali (statutes satirina ekle) */
$edits[]=[
 'accurate references only, never invented ones.',
 'accurate references only, never invented ones. Add a court precedent/case-law citation ONLY if certain it is a real decision correctly cited (court and file number); if in any doubt, cite no case law rather than invent one.'
];
/* 5) muhatap blogu */
$edits[]=[
 'Use the correct salutation and closing for {$country}, and end with a signature line showing the sender name.',
 'Use the correct salutation and closing for {$country}, and end with a signature line showing the sender name. Recipient block: use the recipient name from CASE DETAILS if given, add the official postal address only if you are certain of it, otherwise put a clean blank ________________________ for the address; never output an ambiguous bracket like [NAME UND ANSCHRIFT].'
];
/* 6) final adima answersText + tum bracketleri doldur/temizle */
$edits[]=[
 '\n\nDOCUMENT:\n{$draft}\n\nRules: Replace ALL placeholders. Keep professional tone. Fix grammar. Return ONLY the final document.',
 '\n\nCASE DETAILS (real values - fill any remaining bracket like [KUNDENNUMMER], [VERTRAGSNUMMER] or recipient name/address from here):\n{$answersText}\n\nDOCUMENT:\n{$draft}\n\nRules: Replace EVERY bracketed token - sender tokens from the mapping above and any other token from CASE DETAILS. If a token has no value anywhere, replace it with ________ . Never leave a bracket in the output. Keep concise, fix grammar, add no new sections. Return ONLY the final document.'
];

$report=[]; $applied=0; $critical_ok=false;
foreach($edits as $i=>$e){
  $c=substr_count($src,$e[0]);
  if($c===1){ $src=str_replace($e[0],$e[1],$src); $report[]=["EDIT ".($i+1),"OK ($c)"]; $applied++; if($i===0||$i===5)$critical_ok=true; }
  else { $report[]=["EDIT ".($i+1),"ATLANDI (anchor $c kez)"]; }
}

echo "Rapor:\n"; foreach($report as $r) echo "  ".$r[0].": ".$r[1]."\n"; echo "\n";
if($applied===0){ echo "Hicbir anchor eslesmedi — DEGISIKLIK YOK.\n"; exit; }

$tmp=$file.".tmp-dq"; file_put_contents($tmp,$src);
$out=[]; $code=0; exec("php -l ".escapeshellarg($tmp)." 2>&1",$out,$code); @unlink($tmp);
if($code!==0){ echo "✗ LINT HATASI — api.php DEGISTIRILMEDI:\n".implode("\n",$out)."\n"; exit; }
file_put_contents($file.".bak-docqual-".date("Ymd-His"),$start);
file_put_contents($file,$src);

echo "✓ Belge kalite kok duzeltmesi uygulandi (CH_DOCQUAL) — $applied/6 edit.\n";
echo "  Girilen cevaplar artik belgede GERCEK deger olarak cikar ([KUNDENNUMMER] kalmaz).\n";
echo "  Basit dilekce kisa/oz; karmasik derin; sadece gercek emsal + ilgili maddeler; muhatap temiz.\n";
echo "SIL: rm apply-docquality-fix.php\n";
echo "Test: Kundigung Internetvertrag -> kundennummer/vertragsnummer/anbieter gir -> belge KISA,\n";
echo "      girdigin numaralar belgede yazili, [PLACEHOLDER] yok, gereksiz kanun listesi yok.\n";
