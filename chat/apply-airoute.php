<?php
/**
 * ChatHelp — apply-airoute (CH_AIROUTE) — belge/PDF niyeti -> Claude (F2-9)
 *  MEVCUT: pickProvider zaten plan bazli (Elite 5'te 1 Claude, Pro 6'da 1,
 *  Basic/Free sadece DeepSeek). EKSIK: kullanici DOGRUDAN sohbette belge/PDF
 *  uretmek istediginde saglayici tur sayisina gore secildiginden DeepSeek'e
 *  denk gelebiliyordu. Bu yama: doAiChat'te saglayici belirlendikten HEMEN
 *  SONRA, mesajda belge/PDF uretim NIYETI varsa $provider='claude' zorlanir
 *  (plan farketmez — guvenilir uretim). Niyet dedektoru 13/13 test gecti;
 *  yaratma fiilleri + 'pdf' + 'dilekce' (de/en/tr/es/fr/it), soru cumlelerinde
 *  yanlis pozitif YOK.
 *  SADECE api.php'de $provider satirindan sonra 1 satir ekler. Baska sey degismez.
 * KULLANIM: pull2.php?key=...&files=apply-airoute.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-airoute BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/api.php';
$src = @file_get_contents($file);
if ($src===false) exit("api.php okunamadi\n");
if (strpos($src,'CH_AIROUTE')!==false) exit("Zaten ekli (CH_AIROUTE).\n");

$anchor = "=== 'claude' ? 'claude' : 'deepseek';";
$cnt = substr_count($src,$anchor);
if ($cnt!==1) exit("HATA: \$provider capasi $cnt kez (beklenen 1) — api.php DEGISTIRILMEDI.\n");

$re = '~(\bpdf\b|erstell|verfass|aufsetz|formulier|schreib(e|en|st|\b)|dilek[çc]e|\byaz(\b|[ıi]|ma.?|acak)|olu[şs]tur|haz[ıi]rla|r[eé]dig|[ée]cri[rvs]|redact|escrib|\bcrea[rt]|\bscriv|redig)~iu';
$inject = "\n    /* CH_AIROUTE — belge/PDF uretim niyeti: saglam uretim icin Claude'a zorla (plan farketmez) */\n"
        . "    if (\$provider !== 'claude' && preg_match('".$re."', (string)\$message)) \$provider = 'claude';";

$new = $anchor.$inject;
$src = str_replace($anchor,$new,$src);
echo "  ✓ belge/PDF niyeti algilanirsa \$provider='claude' (doc-intent -> Claude)\n";

$tmp = tempnam(sys_get_temp_dir(),'ar').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — api.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-airoute-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_AIROUTE uygulandi. Sohbette belge/PDF istenince Claude uretir;\n";
echo "   diger her seyde plan bazli rotasyon (DeepSeek + periyodik Claude saglamasi) aynen kalir.\n";
