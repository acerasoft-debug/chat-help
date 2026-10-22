<?php
/**
 * ChatHelp — apply-chat-debuglog (CH_CHAT_DBG) — GECICI: ana sohbet log katmani
 *  "sonuc cikmiyor" hangi akista bilinmiyor; Fall loglari bos kaldi. Ana
 *  sohbet (api.php doAiChat) icin de 3 log noktasi eklenir — boylece sitede
 *  HANGI akis denenirse denensin iz duser:
 *   [A] Istek girisi: mesaj uzunlugu + saglayici + dil + gecmis sayisi
 *   [B] Ilk AI cagrisi sonrasi: yanit uzunlugu (0 = AI BOS DONDU -> sebep bu)
 *   [C] Nihai yanit oncesi: kullaniciya donecek uzunluk
 *  Log: data/chat-debug.log — dump-ai-debuglog.php ile okunur.
 *  KALDIRMA: apply-chat-debuglog-remove.php
 * KULLANIM: pull-updates.php?files=apply-chat-debuglog.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-chat-debuglog BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/api.php';
$src = @file_get_contents($file);
if ($src===false) exit("api.php okunamadi\n");
if (strpos($src,'CH_CHAT_DBG')!==false) exit("Zaten ekli (CH_CHAT_DBG).\n");

$fail=[];

/* [A] istek girisi */
$oA = "    if (\$message === '') { respond(['error' => 'empty message']); return; }";
$nA = "    if (\$message === '') { respond(['error' => 'empty message']); return; }\n"
    . "    @file_put_contents(__DIR__.'/data/chat-debug.log', date('H:i:s').\"|REQ|len=\".mb_strlen(\$message).\"|prov=\$provider|lang=\$lang|hist=\".count(\$history).\"\\n\", FILE_APPEND); /*CH_CHAT_DBG*/";
$c=0; $src=str_replace($oA,$nA,$src,$c);
echo ($c===1?"  ✓ [A] istek girisi logu\n":"  ✗ [A] anchor ($c)\n"); if($c!==1)$fail[]='A';

/* [B] ilk AI cagrisi sonrasi */
$oB = "    if (!\$reply) { respond(['error' => 'AI request failed']); return; }";
$nB = "    @file_put_contents(__DIR__.'/data/chat-debug.log', date('H:i:s').\"|AI1|len=\".strlen((string)\$reply).\"\\n\", FILE_APPEND); /*CH_CHAT_DBG*/\n"
    . "    if (!\$reply) { respond(['error' => 'AI request failed']); return; }";
$c=0; $src=str_replace($oB,$nB,$src,$c);
echo ($c===1?"  ✓ [B] ilk-AI-yaniti logu\n":"  ✗ [B] anchor ($c)\n"); if($c!==1)$fail[]='B';

/* [C] nihai yanit oncesi */
$oC = "    respond(['reply' => trim(\$reply), 'provider' => \$provider]);";
$nC = "    @file_put_contents(__DIR__.'/data/chat-debug.log', date('H:i:s').\"|FINAL|len=\".strlen(trim((string)\$reply)).\"\\n\", FILE_APPEND); /*CH_CHAT_DBG*/\n"
    . "    respond(['reply' => trim(\$reply), 'provider' => \$provider]);";
$c=0; $src=str_replace($oC,$nC,$src,$c);
echo ($c===1?"  ✓ [C] nihai-yanit logu\n":"  ✗ [C] anchor ($c)\n"); if($c!==1)$fail[]='C';

if ($fail) { echo "\nHATA: adimlar eslesmedi: ".implode(', ',$fail)." — api.php DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(),'cdb').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — api.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-chatdbg-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_CHAT_DBG uygulandi. SIMDI sitede ana sohbete Turkce bir sey yaz,\n";
echo "   sonra dump-ai-debuglog.php calistir.\n";
