<?php
/** ChatHelp — dump-siteaudit (SADECE OKUR) — KOMPLE SITE: shuffle riski + Almanca sizinti
 *  Kullanici: "hic bir yerde menu yada ana kartlar sallanmasin. Almanca sadece
 *  Almanya'da ve secildigi zaman gorunsun." Bu denetim:
 *   [A] Canli index.php'deki HER setInterval'i bulur, o araligi calistiran
 *       fonksiyonun govdesinde insertBefore/appendChild/removeChild GECIYOR
 *       MU diye bakar -> hala DOM tasiyan (shuffle riski TASIYAN) intervaller
 *       isaretlenir.
 *   [B] Canli api.php + fall-api.php'deki TUM "Du bist"/"Sie sind" (Almanca AI
 *       sistem promptu) baslangiclarini bulur, hangisi lang/country parametresi
 *       KULLANMIYOR (Almanca'ya kilitli) tespit eder.
 *   [C] index.php'de "auf Deutsch" / "auf deutsch" hardcoded ibareleri arar.
 *  Ciktinin TAMAMINI gonder. SIL: rm dump-siteaudit.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
@set_time_limit(120);
$idx = @file_get_contents(__DIR__.'/index.php');
$api = @file_get_contents(__DIR__.'/api.php');
$fall = @file_get_contents(__DIR__.'/fall-api.php');

echo "════════ [A] setInterval DOM-TASIMA RISKI (index.php) ════════\n\n";
if ($idx!==false) {
    $off=0; $n=0; $risky=0;
    while (($p = strpos($idx,'setInterval(',$off)) !== false) {
        $n++;
        /* geriye dogru en yakin function( bul, ondan setInterval'a kadarki govdeyi tara */
        $fp = strrpos(substr($idx,0,$p), 'function');
        $ctxStart = $fp!==false ? $fp : max(0,$p-600);
        $ctx = substr($idx, $ctxStart, ($p-$ctxStart)+400);
        $hasMove = (strpos($ctx,'insertBefore')!==false || strpos($ctx,'appendChild')!==false || strpos($ctx,'removeChild')!==false);
        $intervalArg = substr($idx,$p,60); $intervalArg=preg_replace('/\s+/',' ',$intervalArg);
        if ($hasMove) {
            $risky++;
            /* hangi script id icinde oldugunu bul */
            $sp = strrpos(substr($idx,0,$p), '<script id="');
            $sidEnd = $sp!==false ? strpos($idx,'"',$sp+13) : false;
            $sid = ($sp!==false && $sidEnd!==false) ? substr($idx,$sp+13,$sidEnd-$sp-13) : '?';
            echo "  ⚠ RISKLI @".$p." (script: $sid): ".$intervalArg."...\n";
        }
        $off = $p+12;
        if ($n>150) break;
    }
    echo "\n  Toplam setInterval: $n · DOM-tasima icerenler (riskli): $risky\n";
} else echo "index.php okunamadi\n";

echo "\n════════ [B] ALMANCA-KILITLI AI SISTEM PROMPTLARI (api.php + fall-api.php) ════════\n\n";
foreach (['api.php'=>$api, 'fall-api.php'=>$fall] as $fname=>$src) {
    if ($src===false) { echo "  $fname okunamadi\n"; continue; }
    echo "  --- $fname ---\n";
    $off=0; $n=0;
    while (($p = strpos($src,'"Du bist',$off)) !== false || ($p = strpos($src,"'Du bist",$off)) !== false) {
        $n++;
        $seg = substr($src,$p,140); $seg=preg_replace('/\s+/',' ',$seg);
        /* bu prompt tanimindan itibaren 900 bayt icinde 'lang' veya '$lawHint' geciyor mu? */
        $window = substr($src,$p,900);
        $hasLang = (stripos($window,'$lang')!==false || stripos($window,'lawHint')!==false || stripos($window,'detect the language')!==false || stripos($window,'SAME language')!==false);
        echo "    ".($hasLang?"✓ dil-farkinda":"⚠⚠ ALMANCA'YA KILITLI").": @".$p." ...".$seg."...\n";
        $off = $p+8;
        if ($n>15) break;
    }
    if (!$n) echo "    (bulunamadi)\n";
    echo "\n";
}

echo "\n════════ [C] index.php'de HARDCODED 'auf Deutsch' ibareleri ════════\n\n";
if ($idx!==false) {
    $off=0; $n=0;
    while (($p = stripos($idx,'auf Deutsch',$off)) !== false && $n<15) {
        $n++;
        echo "  @".$p.": ...".preg_replace('/\s+/',' ',substr($idx,max(0,$p-80),160))."...\n";
        $off = $p+8;
    }
    if (!$n) echo "  (bulunamadi)\n";
}

echo "\n════════ [D] fall-api.php + api.php'de HARDCODED 'auf Deutsch' ibareleri ════════\n\n";
foreach (['api.php'=>$api, 'fall-api.php'=>$fall] as $fname=>$src) {
    if ($src===false) continue;
    $off=0; $n=0;
    while (($p = stripos($src,'auf Deutsch',$off)) !== false && $n<15) {
        $n++;
        echo "  [$fname] @".$p.": ...".preg_replace('/\s+/',' ',substr($src,max(0,$p-100),200))."...\n";
        $off = $p+8;
    }
}

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-siteaudit.php ════════\n";
