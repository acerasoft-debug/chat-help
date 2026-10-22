<?php
/**
 * ChatHelp — apply-tone-open (CH_TONE_OPEN) — F2-6
 *  [1] Uslup (Ton) secicisi TUM kullanicilara acilir: injectTone'daki
 *      "if(plan!=='pro'&&plan!=='elite') return;" kapisi kaldirilir.
 *      (Backend toneMap zaten plansiz calisiyor — sadece frontend kapisiydi.)
 *  [2] Strateji onerisi PAKETLERDE: Elite-only olan Strategie dugmesi
 *      Pro+Elite olur (plan kartlari zaten Pro'da Strategie-Analyse vaadediyor).
 *      Free/Basic'te gorunmez.
 * KULLANIM: pull2.php?key=...&files=apply-tone-open.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-tone-open BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_TONE_OPEN')!==false) exit("Zaten ekli (CH_TONE_OPEN).\n");

$fail=[];

/* [1] uslup kapisini kaldir */
$o1 = "if(plan!=='pro'&&plan!=='elite') return;";
$c = substr_count($src,$o1);
if ($c===1) { $src=str_replace($o1,"/*CH_TONE_OPEN — uslup secicisi HERKESE acik*/",$src); echo "  ✓ [1] uslup secicisi tum kullanicilara acildi\n"; }
else { echo "  ✗ [1] anchor $c kez (beklenen 1)\n"; $fail[]=1; }

/* [2] strateji: Elite-only -> Pro+Elite */
$o2 = "if(plan==='elite'){";
$c = substr_count($src,$o2);
if ($c===1) { $src=str_replace($o2,"if(plan==='elite'||plan==='pro'){ /*CH_TONE_OPEN — strateji paketlerde (Pro+Elite)*/",$src); echo "  ✓ [2] strateji onerisi Pro+Elite'te (Free/Basic'te yok)\n"; }
else { echo "  ✗ [2] anchor $c kez (beklenen 1)\n"; $fail[]=2; }

if ($fail) { echo "\nHATA: eslesmeyen adimlar: ".implode(', ',$fail)." — index DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(),'to').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-toneopen-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_TONE_OPEN uygulandi. Uslup (sachlich/förmlich/bestimmt...) herkese acik;\n";
echo "   strateji analizi yalniz Pro+Elite paketlerinde.\n";
