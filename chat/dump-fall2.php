<?php
/**
 * ChatHelp — dump-fall2 — Meine Fälle modulunun TAM govdesi (fall acma/render
 *  fonksiyonlari) — 'Fall acilirken premium sorular' ozelligi icin (OKUR).
 * KULLANIM: pull2.php?key=...&files=dump-fall2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-fall2 (".strlen($src)." B) ==\n\n";

$p=strpos($src,"const FK='ch_falls_v1';");
if($p===false){ echo "modul bulunamadi\n"; exit; }
echo substr($src,$p,9000)."\n";
echo "\n== BITTI ==\n";
