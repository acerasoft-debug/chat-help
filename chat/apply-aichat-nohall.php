<?php
/**
 * ChatHelp — apply-aichat-nohall (CH_AICHAT_NOHALL)
 *  Chat [[DOC]] uretimini de halusinasyon-korumali yapar:
 *  AI, [[DOC]] icinde SADECE kullanicinin kendi belirttigi olgu/talepleri
 *  yazar; ek olay, §§, hukuki arguman veya talep UYDURMAZ. §§ yalnizca
 *  kullanici acikca andiysa; yoksa hic. (api.php — CH_AICHAT_INTAKE promptu)
 * KULLANIM: pull-updates.php?files=apply-aichat-nohall.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-aichat-nohall BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/api.php';
$src  = @file_get_contents($file);
if ($src === false) exit("api.php okunamadi\n");
if (strpos($src, 'CH_AICHAT_NOHALL') !== false) exit("Zaten ekli (CH_AICHAT_NOHALL).\n");
if (strpos($src, 'GENERATE ONLY WHEN READY') === false) exit("HATA: CH_AICHAT_INTAKE promptu bulunamadi.\n");
file_put_contents($file . '.bak-aichatnohall-' . date('Ymd-His'), $src);

$old = "a CONCISE body that states the facts and cites the relevant {\$lawHint} article/paragraph numbers, and — only for genuinely complex disputes — one fitting precedent; then a clear demand";
$new = "a CONCISE body that states ONLY the facts the user themselves provided /*CH_AICHAT_NOHALL*/ — invent nothing: no extra facts, no law paragraphs/§§, no legal arguments and no demands the user did not state; cite a paragraph ONLY if the user explicitly named it, otherwise cite none; then exactly the demand the user actually asked for";

$c = 0; $src = str_replace($old, $new, $src, $c);
if (!$c) exit("HATA: [[DOC]] uretim cumlesi bulunamadi — degistirilmedi.\n");

$tmp = tempnam(sys_get_temp_dir(), 'api') . '.php';
file_put_contents($tmp, $src);
$lo = []; $rc = 0;
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc);
@unlink($tmp);
if ($rc !== 0) { echo "LINT HATASI — api.php degistirilmedi:\n  " . implode("\n  ", $lo) . "\n"; exit; }
file_put_contents($file, $src);
echo "✓ CH_AICHAT_NOHALL uygulandi ($c). Chat [[DOC]] artik uydurma §§/arguman/talep icermez.\n";
