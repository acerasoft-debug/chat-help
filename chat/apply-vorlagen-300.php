<?php
/**
 * ChatHelp — apply-vorlagen-300 (CH_VORL300) — ana sayfadaki "100+ Vorlagen"
 *  rozetini "300+ Vorlagen" yap (cok dilli). preg_replace: bir sablon-kelimesinin
 *  hemen ONUNDEKI "100+" (veya "100 +") sayisini 300+ yapar. Bulamazsa TANI ciktisi
 *  verir (degistirmeden). Idempotent: zaten 300+ ise 0 degisim.
 * KULLANIM: pull2.php?key=...&files=apply-vorlagen-300.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-vorlagen-300 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");

/* sablon-kelimesi (DE/TR/ES/FR/IT/EN) hemen ardinda olan "100+" -> "300+" */
$word = '(?:Vorlagen|Vorlage|Muster|Şablon|şablon|Şablonu|Şablonlar|plantillas?|modèles?|modelli|modello|templates?|Vorlag)';
$pat  = '/\b100\s*\+(?=[\s ]{0,3}'.$word.')/iu';

$n=0;
$new = preg_replace($pat, '300+', $src, -1, $n);

if ($new===null) exit("HATA: preg_replace null (regex). index DEGISTIRILMEDI.\n");

if ($n===0) {
  echo "  ⚠ '100+ <Vorlagen>' bulunamadi ($n). index DEGISTIRILMEDI.\n\n";
  echo "  ── TANI: 'Vorlagen'/'Vorlage'/'Şablon'/'100+' gecen satirlar ──\n";
  $lines = preg_split('/\n/', $src);
  $shown=0;
  foreach ($lines as $i=>$ln) {
    if ($shown>=25) break;
    if (preg_match('/Vorlage|Şablon|şablon|plantilla|modèle|template|100\s*\+|\b100\b/iu', $ln)) {
      $t = trim($ln);
      if (strlen($t)>220) $t = substr($t,0,220).'…';
      echo "  L".($i+1).": ".$t."\n";
      $shown++;
    }
  }
  if ($shown===0) echo "  (hicbir eslesme yok)\n";
  exit;
}

$tmp = tempnam(sys_get_temp_dir(),'v3').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-vorl300-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'300+')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: $n rozet '300+' yapildi (".strlen($chk)." bayt)\n";
echo "\n✓ Ana sayfa rozeti artik '300+ Vorlagen'.\n";
