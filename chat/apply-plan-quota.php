<?php
/**
 * ChatHelp — apply-plan-quota (CH_PLAN_QUOTA) — dilekce kotalari + gorunen metinler
 *  Faz-2 plan yapisi: Basic 40 · Pro 200 · Elite 1000 dilekce.
 *  Fiyatlara/Stripe priceId'lerine DOKUNMAZ (zaten dogru). Sadece:
 *   [1] PLANS.docs kaynak-kotasi: 20/50/300 -> 40/200/1000
 *   [2] nList ozellik metinleri (Konto plan listesi)
 *   [3] plans[] fiyat karti ozellik satirlari
 *  9 bayt-kesin str_replace, all-or-nothing (her capa TAM 1 kez).
 * KULLANIM: pull2.php?key=...&files=apply-plan-quota.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-plan-quota BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_PLAN_QUOTA')!==false) exit("Zaten ekli (CH_PLAN_QUOTA).\n");

$edits = [
  /* [1] PLANS.docs kaynak kotasi */
  ["color:'#d4a84a',docs:20}",  "color:'#d4a84a',docs:40}",   "PLANS basic docs 40"],
  ["color:'#6094ff',docs:50}",  "color:'#6094ff',docs:200}",  "PLANS pro docs 200"],
  ["color:'#c090ff',docs:300}", "color:'#c090ff',docs:1000}", "PLANS elite docs 1000"],
  /* [2] nList (Konto plan listesi ozellik metni) */
  ["feats:'20 Dok. · PDF · KI'", "feats:'40 Dok. · PDF · KI'", "nList basic 40 Dok"],
  ["feats:'50 Dok. · Unbegr. Formulare · 20 Fallanalysen'",  "feats:'200 Dok. · Unbegr. Formulare · 20 Fallanalysen'",  "nList pro 200 Dok"],
  ["feats:'300 Dok. · Unbegr. Formulare · 100 Fallanalysen'","feats:'1000 Dok. · Unbegr. Formulare · 100 Fallanalysen'","nList elite 1000 Dok"],
  /* [3] plans[] fiyat karti ozellik satirlari */
  ["'20 Dokumente / Monat'",  "'40 Dokumente / Monat'",   "kart basic 40 Dokumente"],
  ["'50 Dokumente / Monat'",  "'200 Dokumente / Monat'",  "kart pro 200 Dokumente"],
  ["'300 Dokumente / Monat'", "'1000 Dokumente / Monat'", "kart elite 1000 Dokumente"],
];

$fail=[];
foreach ($edits as $i=>[$o,$n,$lbl]) {
    $c = substr_count($src,$o);
    if ($c!==1) { echo "  ✗ [".($i+1)."] $lbl — capa $c kez (beklenen 1)\n"; $fail[]=$i+1; continue; }
    $src = str_replace($o,$n,$src);
    echo "  ✓ [".($i+1)."] $lbl\n";
}
if ($fail) { echo "\nHATA: eslesmeyen adimlar: ".implode(', ',$fail)." — index DEGISTIRILMEDI.\n"; exit; }

/* iz birak (idempotens icin) */
$marker = "\n<!-- CH_PLAN_QUOTA: Basic 40 · Pro 200 · Elite 1000 -->";
$pos = strrpos($src,'</body>');
if ($pos!==false) $src = substr($src,0,$pos).$marker."\n".substr($src,$pos);
else $src .= $marker;

$tmp = tempnam(sys_get_temp_dir(),'pq').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-planquota-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_PLAN_QUOTA uygulandi. Dilekce kotalari ve tum gorunen metinler:\n";
echo "   Basic 40 · Pro 200 · Elite 1000 (fiyat/Stripe degismedi).\n";
