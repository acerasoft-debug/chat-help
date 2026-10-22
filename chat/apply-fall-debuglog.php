<?php
/**
 * ChatHelp — apply-fall-debuglog (CH_FALL_DBG) — GECICI istek/yanit kaydi
 *  "sonuc cikmiyor" teshisi icin fall-api.php'ye 4 hafif log noktasi ekler:
 *   [A] Istek girisi: auth basligi var mi + govde boyutu
 *   [B] Govde cozumu: action + plan + mesaj sayisi
 *   [C] fall_chat AI yaniti: uzunluk (0 = AI bos dondu)
 *   [D] fall_solve belge: uzunluk (0 = belge uretilemedi)
 *  Log: data/fall-debug.log — dump-fall-debuglog.php ile okunur.
 *  KALDIRMA: apply-fall-debuglog-remove.php
 * KULLANIM: pull-updates.php?files=apply-fall-debuglog.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fall-debuglog BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/fall-api.php';
$src = @file_get_contents($file);
if ($src===false) exit("fall-api.php okunamadi\n");
if (strpos($src,'CH_FALL_DBG')!==false) exit("Zaten ekli (CH_FALL_DBG).\n");

$fail=[];

/* [A] istek girisi */
$oA = "if (\$_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;";
$nA = "if (\$_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;\n"
    . "@file_put_contents(__DIR__.'/data/fall-debug.log', date('H:i:s').\"|REQ|auth=\".(isset(\$_SERVER['HTTP_AUTHORIZATION'])?'yes':'no').\"|len=\".strlen((string)@file_get_contents('php://input')).\"\\n\", FILE_APPEND); /*CH_FALL_DBG*/";
$c=0; $src=str_replace($oA,$nA,$src,$c);
echo ($c===1?"  ✓ [A] istek girisi logu\n":"  ✗ [A] anchor ($c)\n"); if($c!==1)$fail[]='A';

/* [B] govde cozumu */
$oB = '$msgs   = $body[\'messages\'] ?? [];';
$nB = '$msgs   = $body[\'messages\'] ?? [];' . "\n"
    . '@file_put_contents(__DIR__.\'/data/fall-debug.log\', date(\'H:i:s\')."|BODY|action=$action|plan=".($user[\'plan\']??\'?\')."|msgs=".count($msgs)."\n", FILE_APPEND); /*CH_FALL_DBG*/';
$c=0; $src=str_replace($oB,$nB,$src,$c);
echo ($c===1?"  ✓ [B] govde logu\n":"  ✗ [B] anchor ($c)\n"); if($c!==1)$fail[]='B';

/* [C] fall_chat AI yaniti */
$oC = <<<'OLD'
    $reply = chAI1(array_merge(
        [['role'=>'system','content'=>$sys]],
        array_map(fn($m)=>['role'=>($m['role']==='user'?'user':'assistant'),'content'=>$m['content']], $msgs)
    ), 0.5);
OLD;
$nC = <<<'NEW'
    $reply = chAI1(array_merge(
        [['role'=>'system','content'=>$sys]],
        array_map(fn($m)=>['role'=>($m['role']==='user'?'user':'assistant'),'content'=>$m['content']], $msgs)
    ), 0.5);
    @file_put_contents(__DIR__.'/data/fall-debug.log', date('H:i:s')."|CHAT_REPLY|len=".strlen((string)$reply)."\n", FILE_APPEND); /*CH_FALL_DBG*/
NEW;
$c=0; $src=str_replace($oC,$nC,$src,$c);
echo ($c===1?"  ✓ [C] chat-yanit logu\n":"  ✗ [C] anchor ($c)\n"); if($c!==1)$fail[]='C';

/* [D] belge uretimi */
$oD = '    $document = chAI2($sys, $usr, 4000, array_values($photos));';
$nD = '    $document = chAI2($sys, $usr, 4000, array_values($photos));' . "\n"
    . '    @file_put_contents(__DIR__.\'/data/fall-debug.log\', date(\'H:i:s\')."|DOC|len=".strlen((string)$document)."\n", FILE_APPEND); /*CH_FALL_DBG*/';
$c=0; $src=str_replace($oD,$nD,$src,$c);
echo ($c===1?"  ✓ [D] belge logu\n":"  ✗ [D] anchor ($c)\n"); if($c!==1)$fail[]='D';

if ($fail) { echo "\nHATA: adimlar eslesmedi: ".implode(', ',$fail)." — fall-api.php DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(),'fdb').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — fall-api.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-falldbg-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_FALL_DBG uygulandi. SIMDI: sitede 'sonuc cikmiyor' durumunu BIR KEZ tekrarla,\n";
echo "   sonra dump-fall-debuglog.php calistir ve ciktiyi gonder.\n";
