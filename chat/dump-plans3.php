<?php
/** ChatHelp — dump-plans3 (SADECE OKUR) — plan tanimlari (fiyat+ozellik+key):
 *  Konto plan kartlari renderer (@12100-14600), PLANS objesi (@22000-22700),
 *  modul plan dizisi (@747500-748200). Tek-sayfa plan ekrani icin. TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
function sl($s,$from,$len,$label){ echo "\n──────── $label (@$from,+$len) ────────\n".str_replace("\n","⏎",substr($s,max(0,$from),$len))."\n[/end]\n"; }
sl($idx,12180,1900,'[1] Konto plan kartlari (plans dizisi + render + buton)');
sl($idx,22000,760,'[2] PLANS objesi (stripeCheckout kullaniyor)');
sl($idx,747450,820,'[3] modul plan dizisi');
/* PLANS global mi? tanim satiri */
$p=strpos($idx,'PLANS=');
echo "\n──────── PLANS= tanim baglami ────────\n";
if($p!==false) echo str_replace("\n","⏎",substr($idx,max(0,$p-30),200))."\n";
else { $p2=strpos($idx,'PLANS '); echo $p2!==false?str_replace("\n","⏎",substr($idx,max(0,$p2-30),200)):"(PLANS= bulunamadi)"; }
echo "\ntoplam 'PLANS' gecis: ".substr_count($idx,'PLANS')."\n";
echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
