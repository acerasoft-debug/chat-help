<?php
/**
 * ChatHelp — IS PANELI: konum kutusuna ULKE eklendi (CH_JOBS_LOC_CC)
 *  "Şehir / bölge" -> "Şehir / bölge / ülke (örn. Berlin, Almanya)" (6 dil).
 *  Backend zaten location dizgisini oldugu gibi kullaniyor (Jooble/AI) —
 *  ulke yazilinca arama otomatik ulkeye gore olur, backend degisiklik gerekmez.
 * KULLANIM: pull-updates.php?files=apply-jobs-loc-country.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-jobs-loc-country BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
if(strpos($src,"CH_JOBS_LOC_CC")!==false) exit("Zaten ekli (CH_JOBS_LOC_CC).\n");

$edits=[
 ['loc:"City / region (e.g. Berlin)"',        'loc:"City / region / country (e.g. Berlin, Germany)"'],
 ['loc:"Şehir / bölge (örn. Berlin)"',        'loc:"Şehir / bölge / ülke (örn. Berlin, Almanya)"'],
 ['loc:"Stadt / Region (z. B. Berlin)"',      'loc:"Stadt / Region / Land (z. B. Berlin, Deutschland)"/*CH_JOBS_LOC_CC*/'],
 ['loc:"Ville / région (ex. Berlin)"',        'loc:"Ville / région / pays (ex. Berlin, Allemagne)"'],
 ['loc:"Ciudad / región (p. ej. Berlín)"',    'loc:"Ciudad / región / país (p. ej. Berlín, Alemania)"'],
 ['loc:"Città / regione (es. Berlino)"',      'loc:"Città / regione / paese (es. Berlino, Germania)"'],
];
$report=[];$applied=0;
foreach($edits as $i=>$e){
  $c=substr_count($src,$e[0]);
  if($c===1){ $src=str_replace($e[0],$e[1],$src); $report[]=["dil ".($i+1),"OK"]; $applied++; }
  else $report[]=["dil ".($i+1),"ATLANDI ($c)"];
}
echo "Rapor:\n"; foreach($report as $r) echo "  ".$r[0].": ".$r[1]."\n";
if($applied<1){ echo "\nHicbir anchor eslesmedi — DEGISIKLIK YOK.\n"; exit; }

$tmp=$file.".tmp-jlc"; file_put_contents($tmp,$src);
$out=[];$code=0; exec("php -l ".escapeshellarg($tmp)." 2>&1",$out,$code); @unlink($tmp);
if($code!==0){ echo "✗ LINT HATASI — DEGISTIRILMEDI:\n".implode("\n",$out)."\n"; exit; }
file_put_contents($file.".bak-jobsloc-".date("Ymd-His"),$start);
file_put_contents($file,$src);
echo "\n✓ Konum kutusuna ulke eklendi ($applied/6 dil) (CH_JOBS_LOC_CC).\n";
echo "SIL: rm apply-jobs-loc-country.php\n";
echo "Test: Is Bul & Basvur -> konum kutusu artik 'Şehir / bölge / ülke (örn. Berlin, Almanya)';\n";
echo "      'Istanbul, Türkiye' yaz -> arama ulkeye gore calisir.\n";
