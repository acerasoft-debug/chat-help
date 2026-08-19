<?php
/** ChatHelp — dump-fall-lang (SADECE OKUR)
 *  Kullanici: "farkli hukuklara tiklandiginda farkli dilde yaziliyor ama
 *  AI Almanca cevap veriyor" diyor. Sohbet akisinin (doAiChat) dil-otomatik-
 *  algilama talimati zaten var (CH_AICHAT_LANG) — ama "Dava Ac" (Fall) akisi
 *  AYRI bir backend (fall-api.php) kullaniyor ve MUHTEMELEN kendi ayri
 *  sistem promptunda bu talimat YOK. Bu dump fall-api.php'deki TUM sistem
 *  promptu olusturma kodunu + "lang" degiskeninin nasil kullanildigini
 *  gosterir. Ciktinin TAMAMINI gonder. SIL: rm dump-fall-lang.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f = @file_get_contents(__DIR__.'/fall-api.php');
if ($f===false) exit("fall-api.php okunamadi (dosya yok olabilir)\n");

echo "════════ fall-api.php DIL/PROMPT TESHISI (".number_format(strlen($f))." bayt) ════════\n\n";

echo "###### 1) TUM FONKSIYON ISIMLERI ######\n";
preg_match_all('/function\s+(\w+)\s*\(/', $f, $mm);
echo implode(", ", $mm[1])."\n\n";

echo "###### 2) 'lang' GECEN TUM BAGLAMLAR ######\n";
$off=0;$n=0;
while(($p=stripos($f,'lang',$off))!==false && $n<60){
    $seg=substr($f,max(0,$p-55),110); $seg=preg_replace('/\s+/',' ',$seg);
    echo "  @".$p.": ...".$seg."...\n";
    $off=$p+4; $n++;
}

echo "\n###### 3) SISTEM PROMPT ('\$sys' VEYA BENZERI) OLUSTURULAN TUM YERLER ######\n";
$off=0;$n=0;
while(($p=preg_match('/\$sys\w*\s*=/', $f, $mm2, PREG_OFFSET_CAPTURE, $off)) && $n<15){
    $pos=$mm2[0][1];
    $seg=substr($f,$pos,500);
    echo "  @".$pos.":\n  ".preg_replace('/\s+/',' ',$seg)."\n\n";
    $off=$pos+10; $n++;
}

echo "\n###### 4) callClaude / callDeepSeek CAGRI BAGLAMLARI (fall icinde) ######\n";
$off=0;$n=0;
while(($p=stripos($f,'call',$off))!==false && $n<20){
    if(stripos($f,'callClaude',$p)===$p || stripos($f,'callDeepSeek',$p)===$p){
        $seg=substr($f,max(0,$p-30),160); $seg=preg_replace('/\s+/',' ',$seg);
        echo "  @".$p.": ...".$seg."...\n";
    }
    $off=$p+4; $n++;
}

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-fall-lang.php ════════\n";
