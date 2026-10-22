<?php
/**
 * ChatHelp — apply-nologo (CH_NOLOGO) — dilekce PDF: ust ChatHelp YAZISI + alt marka
 *  kaldirilir; zarif lacivert+altin ust CIZGI aksani KALIR (markasiz premium antet).
 *  Sayfa no yalniz cok sayfaliysa. ch_premium_pdf + ch_premium_pdf_uni ikisine de.
 * KULLANIM: pull2.php?key=...&files=apply-nologo.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-nologo BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/dl.php';
$src=@file_get_contents($file);
if($src===false) exit("dl.php okunamadi\n");
if(strpos($src,"CH_NOLOGO")!==false) exit("Zaten ekli (CH_NOLOGO).\n");
$pairs=[];
$__o=<<<'O_H1'
    /* header */
    $s.=$T($ML,$PH-56,'F2',13,$NAVY,'Chat');
    $s.=$T($ML+$fw('Chat',13,true),$PH-56,'F2',13,$GOLD,'Help');
    $s.=$T($PW-$MR-$fw('chat-help.com',8.5,false),$PH-53,'F1',8.5,$GRAY,'chat-help.com');
    $s.=$col($NAVY)." RG 0.8 w ".$ML." ".($PH-64)." m ".($PW-$MR)." ".($PH-64)." l S\n";
O_H1;
$__n=<<<'N_H1'
    /* premium ust cizgi aksani (markasiz) */
    $s.=$col($NAVY)." RG 0.8 w ".$ML." ".($PH-64)." m ".($PW-$MR)." ".($PH-64)." l S\n";
N_H1;
$pairs[]=['H1',$__o,$__n];
$__o=<<<'O_F1'
    /* footer */
    $s.=$col($GRAY)." RG 0.5 w ".$ML." 58 m ".($PW-$MR)." 58 l S\n";
    $s.=$T($ML,46,'F1',8,$GRAY,'Erstellt mit ChatHelp');
    $seite='Seite '.$p.' von '.$tot;
    $s.=$T($PW-$MR-$fw($seite,8,false),46,'F1',8,$GRAY,$seite);
O_F1;
$__n=<<<'N_F1'
    if($tot>1){ $s.=$col($GRAY)." RG 0.5 w ".$ML." 58 m ".($PW-$MR)." 58 l S\n"; $seite='Seite '.$p.' von '.$tot; $s.=$T($PW-$MR-$fw($seite,8,false),46,'F1',8,$GRAY,$seite); }
N_F1;
$pairs[]=['F1',$__o,$__n];
$__o=<<<'O_H2'
    $s.=$T($ML,$PH-56,'F2',13,$NAVY,$encF('Chat',true));
    $s.=$T($ML+$Wc('Chat',13,true),$PH-56,'F2',13,$GOLD,$encF('Help',true));
    $s.=$T($PW-$MR-$Wc('chat-help.com',8.5,false),$PH-53,'F1',8.5,$GRAY,$encF('chat-help.com',false));
    $s.=$col($NAVY)." RG 0.8 w $ML ".($PH-64)." m ".($PW-$MR)." ".($PH-64)." l S\n";
O_H2;
$__n=<<<'N_H2'
    $s.=$col($NAVY)." RG 0.8 w $ML ".($PH-64)." m ".($PW-$MR)." ".($PH-64)." l S\n";
N_H2;
$pairs[]=['H2',$__o,$__n];
$__o=<<<'O_F2'
    $s.=$col($GRAY)." RG 0.5 w $ML 58 m ".($PW-$MR)." 58 l S\n";
    $s.=$T($ML,46,'F1',8,$GRAY,$encF('Erstellt mit ChatHelp',false));
    $seite="Seite $p von $tot"; $s.=$T($PW-$MR-$Wc($seite,8,false),46,'F1',8,$GRAY,$encF($seite,false));
O_F2;
$__n=<<<'N_F2'
    if($tot>1){ $s.=$col($GRAY)." RG 0.5 w $ML 58 m ".($PW-$MR)." 58 l S\n"; $seite="Seite $p von $tot"; $s.=$T($PW-$MR-$Wc($seite,8,false),46,'F1',8,$GRAY,$encF($seite,false)); }
N_F2;
$pairs[]=['F2',$__o,$__n];

$applied=0;
foreach($pairs as $pr){
  list($tag,$o,$n)=$pr;
  $c=substr_count($src,$o);
  if($c===0){ echo "  = [$tag] bulunamadi (atlandi)\n"; continue; }
  if($c!==1){ echo "  ⚠ [$tag] $c kez (1 beklenir) — atlandi\n"; continue; }
  $src=str_replace($o,$n,$src); echo "  ✓ [$tag] degistirildi\n"; $applied++;
}
if($applied===0){ echo "\nHicbir sey degismedi.\n"; exit; }
$src=str_replace("error_reporting(E_ERROR | E_PARSE);","error_reporting(E_ERROR | E_PARSE);/*CH_NOLOGO*/",$src,$one);
$tmp=tempnam(sys_get_temp_dir(),'nl').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-nologo-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_NOLOGO')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_NOLOGO diskte (dl.php ".strlen($chk)." B)\n";
echo "  Dilekce: ust ChatHelp yazisi + alt marka YOK; zarif cizgi aksani KALDI.\n";