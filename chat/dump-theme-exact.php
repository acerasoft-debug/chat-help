<?php
/**
 * ChatHelp — dump-theme-exact (SADECE OKUR) — otomatik acik/koyu tema icin
 *  KESIN, KIRPILMAMIS anchor metinleri:
 *  [1] ilk-boyama-oncesi tema-set IIFE'sinin TAM metni (anthracite varsayilan)
 *  [2] chtheme-btn switcher HTML'inin TAM insa metni (Weiss dugmesi eklemek icin)
 *  [3] ikinci 'anth'/cur()/apply() sisteminin TAM govdesi (baglantili mi, ayri mi)
 *  HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-theme-exact.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=(string)@file_get_contents(__DIR__.'/index.php');
echo "=== dump-theme-exact — SADECE OKUR (".strlen($src)." bayt) ===\n\n";

/* [1] ilk theme-init IIFE: 'anthracite'in ilk gectigi yerden 250 once - 500 sonra, KIRPILMAMIS */
echo "[1] Ilk tema-init (anthracite ilk gecis, TAM, kirpilmamis):\n";
$p=strpos($src,"||'anthracite'");
if($p!==false){ echo substr($src,max(0,$p-300),650)."\n"; } else echo "(bulunamadi)\n";

/* [2] chtheme-btn switcher insa TAM metni */
echo "\n[2] chtheme-btn switcher insa (TAM, Marine dugmesinden 400 sonrasina kadar):\n";
$p=strpos($src,'data-t="anthracite" onclick');
if($p!==false){ echo substr($src,max(0,$p-200),900)."\n"; } else echo "(bulunamadi)\n";

/* [3] ikinci sistem: cur()/apply() TAM govde */
echo "\n[3] ikinci sistem (cur/apply, 'anth') TAM baglam:\n";
$p=strpos($src,'"anth"');
if($p!==false){ echo substr($src,max(0,$p-400),1200)."\n"; } else echo "(bulunamadi)\n";

echo "\n=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
