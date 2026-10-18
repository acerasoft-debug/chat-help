<?php
/**
 * ChatHelp ops — tts-probe: prüft, ob auf dem Server eine lokale Sprachausgabe (Piper, Open Source, ohne API-Schlüssel)
 * laufen kann: Architektur, freier Platz, ausführbare Dateien im Home-Verzeichnis, Download von GitHub/Hugging Face,
 * shell_exec/proc_open. Nur lesen + ein kleiner Test in ~/ch-probe (danach gelöscht). Gibt keine Schlüssel aus.
 */
header('Content-Type: text/plain; charset=UTF-8');
@set_time_limit(120);
echo "ChatHelp — tts-probe (".gmdate('c').")\n\n";
$f=fn($c)=>trim((string)@shell_exec($c.' 2>&1'));
echo "PHP ".PHP_VERSION." · SAPI ".PHP_SAPI."\n";
echo "shell_exec: ".(function_exists('shell_exec')?'ja':'nein')." · proc_open: ".(function_exists('proc_open')?'ja':'nein')." · exec: ".(function_exists('exec')?'ja':'nein')."\n";
echo "disable_functions: ".ini_get('disable_functions')."\n";
echo "uname: ".$f('uname -a')."\n";
echo "CPU: ".$f("nproc")." Kerne · ".$f("grep -m1 'model name' /proc/cpuinfo")."\n";
echo "RAM: ".$f("free -m | head -2 | tail -1")."\n";
$home=(string)(getenv('HOME') ?: ''); if($home==='' || !is_dir($home)) $home=dirname(dirname(__DIR__));
echo "HOME: ".($home!==''?'gesetzt':'?')." · schreibbar: ".(is_writable($home)?'ja':'nein')."\n";
echo "Platz (HOME): ".$f('df -h '.escapeshellarg($home).' | tail -1')."\n";
echo "Quota: ".$f('quota -s 2>/dev/null | tail -2')."\n";
echo "glibc: ".$f('ldd --version | head -1')."\n";
$dir=rtrim($home,'/').'/ch-probe'; @mkdir($dir,0700,true);
file_put_contents($dir.'/t.sh',"#!/bin/sh\necho EXEC_OK\n"); @chmod($dir.'/t.sh',0700);
echo "Ausführen im HOME: ".$f(escapeshellarg($dir.'/t.sh'))."\n";
$web=__DIR__.'/cache'; @mkdir($web,0750,true); file_put_contents($web.'/.t.sh',"#!/bin/sh\necho EXEC_OK\n"); @chmod($web.'/.t.sh',0700);
echo "Ausführen in chat/cache: ".$f(escapeshellarg($web.'/.t.sh'))."\n"; @unlink($web.'/.t.sh');
foreach(['https://github.com/rhasspy/piper/releases/latest','https://huggingface.co/rhasspy/piper-voices/resolve/main/voices.json'] as $u){
    $ch=curl_init($u); curl_setopt_array($ch,[CURLOPT_NOBODY=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>20,CURLOPT_RETURNTRANSFER=>true]); curl_exec($ch);
    echo "Download-Test $u → HTTP ".curl_getinfo($ch,CURLINFO_HTTP_CODE)." ".curl_error($ch)."\n"; curl_close($ch);
}
echo "ffmpeg: ".($f('command -v ffmpeg') ?: '—')." · lame: ".($f('command -v lame') ?: '—')." · python3: ".($f('command -v python3') ?: '—')."\n";
@unlink($dir.'/t.sh'); @rmdir($dir);
echo "\nFERTIG\n";
