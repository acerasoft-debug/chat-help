<?php
/**
 * ChatHelp — apply-blankfill (CH_BLANKFILL) — bos referans alanlari SILINMESIN,
 *  gorunur bosluk olarak kalsin.
 *
 * SORUN: api.php doGenerate() icindeki CH_DOC_POLISH temizligi, degeri bos
 * kalan TUM etiket satirlarini siliyordu:
 *     if (preg_match('/^(Telefon|…|IBAN|Steuer-?ID)\s*:\s*$/iu', $chT)) continue;
 * Bu yuzden belgede 'IBAN:' satirindan eser kalmiyordu — kullanici elle
 * dolduracagi yeri goremiyordu.
 *
 * DUZELTME (tek satir -> iki satir):
 *  · Referans alanlari (Kundennummer, Vertragsnummer, Aktenzeichen, IBAN,
 *    Steuer-ID) bos ise SILINMEZ, su hale gelir:
 *        IBAN: ________________________
 *  · Gonderen iletisim alanlari (Telefon, Tel., Mobil, Fax, E-Mail,
 *    Geburtsdatum, Geboren am) bos ise ESKISI GIBI silinir (kalabalik olmasin).
 *
 * Degeri OLAN hicbir satira dokunulmaz — mevcut calisan belgeler aynen kalir.
 * Birebir literal eslesme (tam 1 kez), lint, .bak, dogrulama.
 * Geri alma: .bak-blankfill-* dosyasi (veya apply-blankfill-off.php)
 *
 * KULLANIM: /chat/pull2.php?key=...&files=apply-blankfill.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
echo "apply-blankfill BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$f=__DIR__.'/api.php';
$src=@file_get_contents($f);
if($src===false||$src==='') exit("api.php okunamadi\n");
echo "api.php: ".strlen($src)." B  (".date('Y-m-d H:i',filemtime($f)).")\n";

if(strpos($src,'CH_BLANKFILL')!==false) exit("\nZaten ekli (CH_BLANKFILL) — DEGISIKLIK YOK.\n");

$SEARCH = <<<'S'
if (preg_match('/^(Telefon|Tel\.?|Mobil|Fax|E-?Mail|Geburtsdatum|Geboren am|Kundennummer|Vertragsnummer|Aktenzeichen|IBAN|Steuer-?ID)\s*:\s*$/iu', $chT)) continue;
S;

$REPL = <<<'R'
if (preg_match('/^(Kundennummer|Vertragsnummer|Aktenzeichen|IBAN|Steuer-?ID)\s*:\s*$/iu', $chT, $chBM)) { /*CH_BLANKFILL*/ $chOut[] = $chBM[1] . ': ________________________'; continue; }
        if (preg_match('/^(Telefon|Tel\.?|Mobil|Fax|E-?Mail|Geburtsdatum|Geboren am)\s*:\s*$/iu', $chT)) continue;
R;

$n=substr_count($src,$SEARCH);
echo "birebir eslesme: $n\n";
if($n!==1) exit("\n✗ Beklenen tam 1 eslesme, bulunan $n — GUVENLIK GEREGI DEGISIKLIK YOK.\n");

$new=str_replace($SEARCH,$REPL,$src,$done);
if($done!==1) exit("\n✗ Degistirme sayisi $done — DEGISIKLIK YOK.\n");

/* saglik kontrolleri */
if(substr_count($new,'CH_DOC_POLISH')<1) exit("✗ CH_DOC_POLISH kayboldu — DEGISIKLIK YOK\n");
if(substr_count($new,'$chOut[] = $chLn;')<1) exit("✗ cikti satiri kayboldu — DEGISIKLIK YOK\n");
if(substr_count($new,'function doGenerate')<1) exit("✗ doGenerate kayboldu — DEGISIKLIK YOK\n");

$tmp=tempnam(sys_get_temp_dir(),'bf').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0) exit("\n✗ LINT HATASI — api.php DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n");

@file_put_contents($f.'.bak-blankfill-'.date('Ymd-His'),$src);
$w=@file_put_contents($f,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();

$chk=(string)@file_get_contents($f);
echo "\n✓ TAMAM (".strlen($src)." -> ".strlen($chk)." bayt)\n";
echo "  CH_BLANKFILL=".substr_count($chk,'CH_BLANKFILL')
    ."  CH_DOC_POLISH=".substr_count($chk,'CH_DOC_POLISH')
    ."  doGenerate=".substr_count($chk,'function doGenerate')."\n";
echo "\nDAVRANIS:\n";
echo "  · IBAN / Kundennummer / Vertragsnummer / Aktenzeichen / Steuer-ID bos ise\n";
echo "      -> 'IBAN: ________________________' olarak GORUNUR (elle doldurulur)\n";
echo "  · Telefon / Mobil / Fax / E-Mail / Geburtsdatum bos ise -> eskisi gibi silinir\n";
echo "  · Degeri olan satirlar: hic degismez\n";
echo "\nTEST: yeni bir dilekce uret; IBAN gereken bir belgede bosluk cizgisi gorunmeli.\n";
