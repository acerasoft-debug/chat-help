<?php
/**
 * ChatHelp — apply-aichat-lang (CH_AICHAT_LANG)
 *  SORUN: Chat'te kullanici Ispanyolca yaziyor, yanit Almanca geliyor —
 *  cunku yanit dili sabit '$lang' (arayuz dili) parametresine bagliydi.
 *  COZUM: Yapay zeka kullanicinin YAZDIGI dili otomatik algilar ve AYNI
 *  dilde yanitlar (Ispanyolca->Ispanyolca, vb.). Hukuki ESAS yine secilen
 *  ulkenin hukukudur ($lawHint); yalnizca YANIT DILI kullaniciyi izler.
 *  Uretilen [[DOC]] belgesi de konusma diliyle ayni dilde yazilir.
 *  api.php doAiChat sistem promptu guncellenir.
 * KULLANIM: pull-updates.php?files=apply-aichat-lang.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-aichat-lang BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/api.php';
$src = @file_get_contents($file);
if ($src===false) exit("api.php okunamadi\n");
if (strpos($src,'CH_AICHAT_LANG')!==false) exit("Zaten ekli (CH_AICHAT_LANG).\n");

$n=0;

/* ── 1) Yanit dili: sabit $lang -> kullanicinin yazdigi dil (otomatik) ── */
$o1 = "replying in language code '{\$lang}'.";
$n1 = "and you ALWAYS reply in the SAME language the user writes in — automatically detect the language of the user's latest message and mirror it EXACTLY (Spanish→Spanish, Italian→Italian, Turkish→Turkish, French→French, English→English, German→German, etc.), regardless of any preset language code; ONLY the legal substance follows {\$lawHint}, never the reply language. /*CH_AICHAT_LANG*/";
$c=0; $src=str_replace($o1,$n1,$src,$c); $n+=$c;
echo ($c===1?"  ✓ [1] yanit dili artik kullanicinin yazdigi dil\n":"  ✗ [1] anchor bulunamadi ($c)\n");

/* ── 1b) yedek anchor (tirnak/format farkliysa) ── */
if ($c!==1) {
    $o1b = "replying in language code '\$lang'.";
    $c=0; $src=str_replace($o1b,$n1,$src,$c); $n+=$c;
    echo ($c===1?"  ✓ [1b] (yedek anchor) yanit dili duzeltildi\n":"  ✗ [1b] yedek de yok ($c)\n");
}

/* ── 2) [[DOC]] belgesi de konusma diliyle ayni dilde ── */
$o2 = "write the COMPLETE formal letter/petition and wrap it EXACTLY between a line containing only [[DOC]] and a line containing only [[/DOC]].";
$n2 = "write the COMPLETE formal letter/petition IN THE SAME LANGUAGE the user is using in the conversation, and wrap it EXACTLY between a line containing only [[DOC]] and a line containing only [[/DOC]].";
$c=0; $src=str_replace($o2,$n2,$src,$c);
echo ($c===1?"  ✓ [2] [[DOC]] belgesi konusma diliyle ayni dilde\n":"  ~ [2] [[DOC]] anchor yok (atlandi — birincil duzeltme yeterli)\n");

if ($n < 1) {
    echo "\n  Teshis: 'language code' gecen yer(ler):\n";
    $p=0; $cnt=0;
    while(($p=strpos($src,'language code',$p))!==false && $cnt<3){ echo "    ...".substr($src,max(0,$p-30),90)."...\n"; $p+=12; $cnt++; }
    echo "\nHATA: yanit-dili anchor'i eslesmedi — api.php DEGISTIRILMEDI.\n"; exit;
}

$tmp = tempnam(sys_get_temp_dir(),'al').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — api.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-aichatlang-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_AICHAT_LANG uygulandi. Chat artik kullanicinin yazdigi dilde yanitlar; hukuk secilen ulkeninki kalir.\n";
