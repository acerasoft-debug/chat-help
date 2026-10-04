<?php
/** ChatHelp — dump-gen2 (SADECE OKUR) — doGenerate govdesinde AI cagri
 *  sirasi: DeepSeek once mi cagriliyor, sonra Claude mu? Anahtarlar maskeli. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
function mask($s){ return preg_replace('/(sk-[A-Za-z0-9_\-]{4})[A-Za-z0-9_\-]+/','$1***',$s); }
$api=(string)@file_get_contents(__DIR__.'/api.php');
echo "=== dump-gen2 — doGenerate AI cagri sirasi ===\n\n";
$p=stripos($api,'function doGenerate');
if($p===false) exit("doGenerate yok\n");
$body=substr($api,$p,4200);
/* callDeepSeek / callClaude cagrilarini isaretle */
echo "doGenerate icinde:\n";
echo "  callDeepSeek( cagrisi: ".substr_count($body,'callDeepSeek(')."\n";
echo "  callClaude/anthropic cagrisi: ".(substr_count($body,'callClaude')+substr_count($body,'api.anthropic')+substr_count($body,'CLAUDE_KEY'))."\n\n";
echo "──── doGenerate 2. yari (AI cagrilari, 2000-4200) ────\n";
echo mask(substr($body,2000,2200))."\n";
echo "\n=== BITTI. Ciktinin TAMAMINI gonder. ===\n";
