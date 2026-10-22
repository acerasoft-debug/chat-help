<?php
/**
 * ChatHelp — apply-hide-prompts (CH_HIDE_PROMPTS)
 *  Karsilama ekranindaki ornek cumle satirlarini (.wlc-prompts:
 *  "Mein Vermieter erhoht die Miete" / "Handyvertrag kundigen" / "Jobcenter...")
 *  TUM dillerde kaldirir. Alt kategori dugmeleri (.wlc-pill) aynen kalir.
 * KULLANIM: pull-updates.php?files=apply-hide-prompts.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-hide-prompts BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_HIDE_PROMPTS') !== false) exit("Zaten ekli (CH_HIDE_PROMPTS).\n");
file_put_contents($file . '.bak-hideprompts-' . date('Ymd-His'), $src);

$bundle = "<style id=\"ch-hide-prompts\">/* CH_HIDE_PROMPTS — karsilama ornek cumleleri kaldirildi */\n.wlc-prompts{display:none !important}\n</style>";

$n = 0;
$src = preg_replace('/<\/body>/', $bundle . "\n</body>", $src, 1, $n);
if (!$n) exit("HATA: </body> bulunamadi — yazilmadi.\n");
file_put_contents($file, $src);
echo "✓ CH_HIDE_PROMPTS uygulandi: ornek cumleler gizlendi, kategori dugmeleri duruyor.\n";
