<?php
/** ChatHelp — dump-verify-all: bu oturumda eklenen TUM CH_* marker'larin sunucuda
 *  gercekten var olup olmadigini tek seferde kontrol eder. SADECE OKUR.
 *  SIL: rm dump-verify-all.php */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);

$files = [
  'index.php' => __DIR__.'/index.php',
  'api.php'   => __DIR__.'/api.php',
  'form-engine.js' => __DIR__.'/form-engine.js',
];
$content = [];
foreach ($files as $k=>$p) $content[$k] = file_exists($p) ? file_get_contents($p) : false;

/* marker => hangi dosyada aranmali */
$checks = [
  'index.php' => [
    'CH_NOCACHE','CH_STRONG_NOCACHE','CH_CC_LOCK','CH_FLAG_ID_FIX','CH_LANG_LAW_SEP',
    'CH_LANG_MISMATCH','CH_HIDE_SB_COUNTRY','CH_UNIV_CAT_GRID','CH_PREMIUM_SKIN',
    'CH_LIGHTER_THEME','CH_LAUNCH_UI','CH_LAUNCH_UI2','CH_CC_UILANG','CH_RECIPIENT',
    'CH_JOBS_UI',
  ],
  'api.php' => [
    'CH_DOCLANG_FIX','CH_QUALITY_PROMPT','CH_LAUNCH_PROMPT','CH_DOCQUAL','CH_JOBFIND',
    'CH_GENDEBUG','CH_GENDEBUG2',
  ],
  'form-engine.js' => [
    'CH_STAAT_REQFIX',
  ],
];

$total=0; $ok=0; $missing=[];
foreach ($checks as $file=>$markers) {
  echo "\n════ $file ".($content[$file]===false?"(DOSYA YOK!)":"(".strlen($content[$file])." bayt)")." ════\n";
  foreach ($markers as $m) {
    $total++;
    $found = $content[$file]!==false && strpos($content[$file], $m) !== false;
    echo "  ".($found?"✓ OK   ":"✗ YOK  ")." $m\n";
    if ($found) $ok++; else $missing[] = "$file: $m";
  }
}

echo "\n════════════════════════════════════\n";
echo "SONUC: $ok / $total marker sunucuda mevcut.\n";
if ($missing) {
  echo "\nEKSIK OLANLAR (sunucuya HENUZ uygulanmamis):\n";
  foreach ($missing as $m) echo "  - $m\n";
  echo "\n-> Bu eksikler icin ilgili apply-*.php dosyalarini pull-updates ile tekrar calistir.\n";
} else {
  echo "\nHEPSI TAMAM — bu oturumda yazilan tum ozellikler sunucuda canli.\n";
}

/* gecici debug dosyalarini da hatirlat (launch oncesi temizlenmeli) */
echo "\n--- GECICI TESHIS KAYITLARI (launch oncesi silinmeli) ---\n";
foreach (['jbdata/gen-debug.json','jbdata/gen-debug-in.json'] as $f) {
  $p = __DIR__.'/'.$f;
  echo "  ".(is_file($p)?"VAR (sil: rm chat/$f)":"yok")."  - $f\n";
}

echo "\n=== BITTI. TAMAMINI yapistir. SIL: rm dump-verify-all.php ===\n";
