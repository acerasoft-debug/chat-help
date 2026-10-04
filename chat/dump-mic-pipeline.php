<?php
/**
 * ChatHelp — dump-mic-pipeline (SADECE OKUR) — mikrofon KOKTEN teshis:
 *  Sikayet: (1) sitede ses kaydediliyor gibi ama TANIMA/yaziya cevirme hic
 *  gelmiyor; (2) App'te mikrofon butonu HIC tepki vermiyor (wrapper'lar
 *  calismiyor olabilir -> buton window.chOpenMic yerine closure icindeki
 *  orijinali cagiriyor olabilir).
 *  Bu dump: mikrofon butonunun GERCEK onclick'i, startVoice tanimlari,
 *  chOpenMic govdesi, SpeechRecognition kullanimi (onresult/onerror/lang),
 *  MediaRecorder/sunucu-STT yolu. HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-mic-pipeline.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$src=(string)@file_get_contents("$D/index.php");
echo "=== dump-mic-pipeline — SADECE OKUR ===\n\n";
echo "boyut: ".strlen($src)." bayt\n\n";

function occ($label,$src,$needle,$pre=100,$len=460,$max=6){
  echo "[$label] '$needle' (toplam ".substr_count($src,$needle)."):\n";
  $off=0;$n=0;
  while(($p=strpos($src,$needle,$off))!==false && $n<$max){
    $seg=substr($src,max(0,$p-$pre),$len); $seg=preg_replace('/\s+/',' ',$seg);
    echo "  …".$seg."…\n"; $off=$p+strlen($needle);$n++;
  }
  if($n===0) echo "  (yok)\n";
  echo "\n";
}

echo "───── [1] Mikrofon butonu (gorunur composer dugmesi) ─────\n\n";
occ('1a',$src,'startVoice(',100,300,10);
occ('1b',$src,'🎤',80,280,8);
occ('1c',$src,'id="mic',80,300,6);
occ('1d',$src,'mic-btn',80,300,6);

echo "───── [2] startVoice tanimlari (5 kez atanmisti) ─────\n\n";
occ('2a',$src,'window.startVoice=',40,520,8);
occ('2b',$src,'window.startVoice =',40,520,4);

echo "───── [3] chOpenMic govdesi + cagrilari ─────\n\n";
occ('3a',$src,'window.chOpenMic=',40,700,4);
occ('3b',$src,'chOpenMic(',60,240,10);

echo "───── [4] Tanima motoru ─────\n\n";
occ('4a',$src,'webkitSpeechRecognition',80,400,5);
occ('4b',$src,'onresult',60,420,5);
occ('4c',$src,'onerror',60,300,8);
occ('4d',$src,'.lang',40,180,10);
occ('4e',$src,'MediaRecorder',80,340,5);
occ('4f',$src,'interimResults',60,260,4);

echo "───── [5] chKbFocus tanimi ─────\n\n";
occ('5a',$src,'window.chKbFocus=',40,520,3);

echo "=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
