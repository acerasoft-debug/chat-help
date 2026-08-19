<?php
/**
 * ChatHelp — GERCEK ANONIMLIK (CH_ANON_FINAL): kisisel veriler AI'ya HIC gitmez
 * ------------------------------------------------------------------------
 *  Mevcut durum (kanit: dump-gen-debug3 finalPrompt): final adiminda profil
 *  verileri ([VORNAME] → gercek ad, [EMAIL] → gercek eposta...) AI'ya acikca
 *  gonderiliyordu. "Verileriniz KI'ya gitmez" iddiasi ancak bu duzeltmeyle
 *  dogru olur.
 *  Degisiklik: final AI cagrisi SADECE anonim placeholder'li metni cilalar;
 *  gercek ad/adres/iletisim AI'ya hic verilmez. Placeholder -> gercek deger
 *  yerlestirme AI'dan DONDUKTEN SONRA sunucuda (str_replace) yapilir.
 *  (Vaka cevaplari — kundennummer gibi — belgeyi yazmak icin gerekli oldugundan
 *   CASE DETAILS'te kalir; iddia metni 'kisisel veriler: ad/adres/iletisim'
 *   uzerinden kurulmali.)
 * KULLANIM: pull-updates.php?files=apply-anon-final.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-anon-final BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/api.php";
if(!file_exists($file)) exit("api.php yok\n");
$src=file_get_contents($file); $start=$src;
if(strpos($src,"CH_ANON_FINAL")!==false) exit("Zaten ekli (CH_ANON_FINAL).\n");

$edits=[];
/* e1: final prompt basi — gercek-deger eslemesini AI'ya GONDERME */
$edits[]=[
 '? "Replace all placeholders with real data and polish the document:\n\n".
          implode("\n", array_map(fn($k,$v)=>"[$k] → $v", array_keys($ph), $ph)).',
 '? "Polish and finalize the document. The SENDER placeholders [VORNAME] [NACHNAME] [STRASSE] [PLZ_ORT] [ADRESSE] [GEBURTSDATUM] [TELEFON] [EMAIL] [DATUM_HEUTE] [ORT] must stay EXACTLY as written - never replace, remove or invent them.".'
];
/* e2: kurallar — sender placeholder'lara dokunma, sadece vaka tokenlarini doldur */
$edits[]=[
 'Rules: Replace EVERY bracketed token - sender tokens from the mapping above and any other token from CASE DETAILS. If a token has no value anywhere, replace it with ________ . Never leave a bracket in the output.',
 'Rules: Fill ONLY case tokens (like [KUNDENNUMMER], [VERTRAGSNUMMER]) from CASE DETAILS; if a case value is unknown use ________ . Keep every SENDER placeholder listed above untouched.'
];
/* e3: draftsiz fallback prompt — gercek ad/adres yerine placeholder */
$edits[]=[
 ': "Create {$docName} for {$country}. Sender: {$ph[\'VORNAME\']} {$ph[\'NACHNAME\']}, {$ph[\'ADRESSE\']}, {$ph[\'PLZ_ORT\']}. Date: {$ph[\'DATUM_HEUTE\']}.\n{$answersText}\nReturn ONLY the document text.";',
 ': "Create {$docName} for {$country}. Sender block: use the placeholders [VORNAME] [NACHNAME], [ADRESSE], [PLZ_ORT] exactly as written. Date: [DATUM_HEUTE].\n{$answersText}\nReturn ONLY the document text.";'
];
/* e4: AI dondukten sonra SUNUCUDA gercek degerleri yerlestir */
$edits[]=[
 '    if (!$final && $draft) {',
 '    if ($final) { foreach ($ph as $k => $v) { $final = str_replace(\'[\'.$k.\']\', (string)$v, $final); } } /*CH_ANON_FINAL — kisisel veri AI\'ya gitmez, EU sunucuda yerlestirilir*/
    if (!$final && $draft) {'
];

$report=[];$applied=0;
foreach($edits as $i=>$e){
  $c=substr_count($src,$e[0]);
  if($c===1){ $src=str_replace($e[0],$e[1],$src); $report[]=["e".($i+1),"OK"]; $applied++; }
  else $report[]=["e".($i+1),"ATLANDI (anchor $c kez)"];
}
echo "Rapor:\n"; foreach($report as $r) echo "  ".$r[0].": ".$r[1]."\n";
if($applied<4){ echo "\n✗ 4/4 gerekli, $applied bulundu — HICBIR SEY KAYDEDILMEDI. dump ile final blogu kontrol et.\n"; exit; }

$tmp=$file.".tmp-anon"; file_put_contents($tmp,$src);
$out=[];$code=0; exec("php -l ".escapeshellarg($tmp)." 2>&1",$out,$code); @unlink($tmp);
if($code!==0){ echo "✗ LINT HATASI — DEGISTIRILMEDI:\n".implode("\n",$out)."\n"; exit; }
file_put_contents($file.".bak-anonfinal-".date("Ymd-His"),$start);
file_put_contents($file,$src);
echo "\n✓ Gercek anonimlik aktif (CH_ANON_FINAL): ad/adres/iletisim AI'ya HIC gitmiyor;\n";
echo "  placeholder'lar AI cevabindan SONRA EU sunucuda yerlestiriliyor.\n";
echo "SIL: rm apply-anon-final.php\n";
echo "Test: belge uret -> gonderen blogu yine dolu (sunucu yerlestirdi); jbdata debug'da\n";
echo "      finalPrompt icinde gercek ad/eposta ARTIK GORUNMEMELI (sadece placeholder).\n";
