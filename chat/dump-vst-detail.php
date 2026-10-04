<?php
/** ChatHelp — dump-vst-detail (SADECE OKUR) — Vertrag Studio ic yapisi:
 *  CT (sozlesme tipleri) + VOPT (opsiyon klozlari) + ulke-seti ekleme deseni
 *  (TRK + ES/FR/IT/GB) + belge/PDF uretimi. TR seti yazmak icin sart.
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
if($idx==='') exit("index.php okunamadi\n");
function sl($s,$from,$len,$label){ echo "\n──────── $label (@$from,+$len) ────────\n".str_replace("\n","⏎",substr($s,max(0,$from),$len))."\n[/end]\n"; }
echo "════ VST modul cekirdegi ════";
sl($idx,1244600,1600,'[1] VST header + CT tanim baslangici');
sl($idx,1263103,2600,'[2] VOPT opsiyon klozlari');
sl($idx,1275800,1400,'[3] buildClauses (opsiyon->metin)');
sl($idx,1316756,1500,'[4] NCX (NDA vb workflow)');
echo "\n════ ULKE SETI EKLEME DESENI (kritik) ════";
sl($idx,1389600,2600,'[5] TRK — TR sozlesme ekleme');
sl($idx,1411420,3200,'[6] ES/FR/IT/GB sozlesme setleri');
echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
