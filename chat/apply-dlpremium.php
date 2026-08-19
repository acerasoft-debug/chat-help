<?php
/**
 * ChatHelp — apply-dlpremium (CH_DL_PREMIUM) — dl.php'nin duz-metin PDF yazicisini
 *  (ch_text_pdf) PREMIUM DIN-5008 mektup duzenine yukseltir (ch_premium_pdf):
 *   • ust marka anteti (Chat + Help) + ince navy cizgi + chat-help.com
 *   • saga-yasli tarih satiri (ilk bloktaki tarih otomatik saga)
 *   • KALIN konu satiri (Betreff/Konu/Subject…)
 *   • kurumsal marjlar, rahat satir araligi, paragraf boslugu
 *   • imza satiri (kapanistan sonra) KALIN
 *   • altbilgi cizgisi + 'Seite X von Y'
 *  Saf-PHP (kutuphane yok). Latin/Almanca tam; TR/AR harfleri translit (mevcut sinir).
 *  App/mobil TUM sunucu PDF'leri (chat + sablon) bu yoldan gecer -> hepsi premium.
 * KULLANIM: pull2.php?key=...&files=apply-dlpremium.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-dlpremium BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/dl.php';
$src=@file_get_contents($file);
if($src===false) exit("dl.php okunamadi\n");
if(strpos($src,'CH_DL_PREMIUM')!==false) exit("Zaten ekli (CH_DL_PREMIUM).\n");

/* ── premium PDF fonksiyonu (dl.php icine eklenecek) ── */
$fn = <<<'PHPFN'

/* CH_DL_PREMIUM — DIN-5008 tarzi premium mektup PDF (saf-PHP, kutuphanesiz) */
function ch_premium_pdf(string $text): string {
  $PW=595.28; $PH=841.89; $ML=64.0; $MR=64.0; $MB=72.0;
  $W=$PW-$ML-$MR;
  $NAVY=[0.055,0.09,0.20]; $GOLD=[0.80,0.62,0.24]; $INK=[0.13,0.13,0.16]; $GRAY=[0.45,0.45,0.50];
  $uLines=explode("\n",str_replace("\r","",$text));
  $t=@iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$text); if($t===false) $t=$text;
  $rLines=explode("\n",str_replace("\r","",$t));
  $N=max(count($uLines),count($rLines));
  for($i=0;$i<$N;$i++){ if(!isset($uLines[$i]))$uLines[$i]=''; if(!isset($rLines[$i]))$rLines[$i]=''; }

  /* yapisal tespit (UTF-8 uzerinde) */
  $subjIdx=-1; $closeIdx=-1; $dateIdx=-1;
  for($i=0;$i<$N;$i++){
    $u=trim($uLines[$i]);
    if($subjIdx<0 && preg_match('/^(Betreff|Betrifft|Konu|Subject|Objet|Asunto|Oggetto|Onderwerp)\s*:/iu',$u)) $subjIdx=$i;
    if($closeIdx<0 && preg_match('/^(Mit freundlichen Grüßen|Mit freundlichem Gruß|Freundliche Grüße|Mit besten Grüßen|Hochachtungsvoll|Saygılarımla|Yours sincerely|Kind regards|Sincerely|Cordialement|Atentamente|Distinti saluti|Met vriendelijke groet)/iu',$u)) $closeIdx=$i;
    if($dateIdx<0 && $i<9 && $u!=='' && strlen($u)<48 && preg_match('/\d{1,2}[.\/]\s?\d{1,2}[.\/]\s?\d{2,4}/u',$u) && !preg_match('/[a-zäöü]{6,}.*[a-zäöü]{6,}/iu',$u)) $dateIdx=$i;
  }
  $sigIdx=-1;
  if($closeIdx>=0){ for($i=$closeIdx+1;$i<$N;$i++){ if(trim($uLines[$i])!==''){ $sigIdx=$i; break; } } }

  $esc=function($s){ return str_replace(['\\','(',')',"\t"],['\\\\','\\(','\\)','    '],$s); };
  $col=function($c){ return round($c[0],3).' '.round($c[1],3).' '.round($c[2],3); };
  $fw=function($s,$size,$bold){ return strlen($s)*$size*($bold?0.548:0.508); };
  $maxChars=function($size,$bold) use($W){ return max(8,(int)floor($W/($size*($bold?0.548:0.508)))); };
  $wrap=function($line,$size,$bold) use($maxChars){
    $mc=$maxChars($size,$bold); $line=rtrim($line);
    if($line==='') return [''];
    $out=[]; $cur='';
    foreach(explode(' ',$line) as $wd){
      while(strlen($wd)>$mc){ if($cur!==''){ $out[]=$cur; $cur=''; } $out[]=substr($wd,0,$mc); $wd=substr($wd,$mc); }
      $try=($cur===''?$wd:$cur.' '.$wd);
      if(strlen($try)<=$mc){ $cur=$try; } else { if($cur!=='')$out[]=$cur; $cur=$wd; }
    }
    if($cur!=='')$out[]=$cur;
    return $out;
  };

  /* ── icerigi sayfalara akit (pass 1) ── */
  $contentTop=$PH-108; $pages=[]; $cur=[]; $y=$contentTop;
  $newpage=function() use(&$pages,&$cur,&$y,$contentTop){ $pages[]=$cur; $cur=[]; $y=$contentTop; };
  $need=function($h) use(&$y,$MB,$newpage){ if($y-$h < $MB){ $newpage(); } };

  for($i=0;$i<$N;$i++){
    $raw=$rLines[$i]; $u=trim($uLines[$i]);
    if($u===''){ $y-=8.5; if($y<$MB){ $newpage(); } continue; }

    $isSubj=($i===$subjIdx);
    $isSig=($i===$sigIdx);
    $isDate=($i===$dateIdx);

    if($isSubj){
      $need(24); $y-=8;
      foreach($wrap($raw,11.8,true) as $sl){ $need(16); $cur[]=[$ML,$y,'F2',11.8,$INK,$sl]; $y-=16.5; }
      $y-=5; continue;
    }
    if($isDate){
      $need(18);
      $sl=trim($raw); $tw=$fw($sl,10.4,false);
      $cur[]=[$PW-$MR-$tw,$y,'F1',10.4,$INK,$sl]; $y-=17; continue;
    }
    $font=$isSig?'F2':'F1'; $size=$isSig?10.8:10.4; $bold=$isSig; $lead=15.6;
    foreach($wrap($raw,$size,$bold) as $sl){ $need($lead); $cur[]=[$ML,$y,$font,$size,$INK,$sl]; $y-=$lead; }
  }
  $pages[]=$cur; if(count($pages)>1 && empty($pages[count($pages)-1])) array_pop($pages);
  $tot=count($pages); if($tot===0){ $pages=[[]]; $tot=1; }

  /* ── stream'leri olustur (pass 2: header+footer+items) ── */
  $T=function($x,$y,$fn,$size,$c,$s) use($esc,$col){
    return $col($c)." rg\n/$fn ".$size." Tf\nBT ".round($x,2)." ".round($y,2)." Td (".$esc($s).") Tj ET\n";
  };
  $streams=[];
  foreach($pages as $pi=>$items){
    $p=$pi+1; $s='';
    /* header */
    $s.=$T($ML,$PH-56,'F2',13,$NAVY,'Chat');
    $s.=$T($ML+$fw('Chat',13,true),$PH-56,'F2',13,$GOLD,'Help');
    $s.=$T($PW-$MR-$fw('chat-help.com',8.5,false),$PH-53,'F1',8.5,$GRAY,'chat-help.com');
    $s.=$col($NAVY)." RG 0.8 w ".$ML." ".($PH-64)." m ".($PW-$MR)." ".($PH-64)." l S\n";
    $s.=$col($GOLD)." RG 2 w ".$ML." ".($PH-64.6)." m ".($ML+46)." ".($PH-64.6)." l S\n";
    /* body */
    foreach($items as $it){ $s.=$T($it[0],$it[1],$it[2],$it[3],$it[4],$it[5]); }
    /* footer */
    $s.=$col($GRAY)." RG 0.5 w ".$ML." 58 m ".($PW-$MR)." 58 l S\n";
    $s.=$T($ML,46,'F1',8,$GRAY,'Erstellt mit ChatHelp');
    $seite='Seite '.$p.' von '.$tot;
    $s.=$T($PW-$MR-$fw($seite,8,false),46,'F1',8,$GRAY,$seite);
    $streams[]=$s;
  }

  /* ── PDF objelerini birlestir ── */
  $objs=[];
  $objs[3]="<</Type/Font/Subtype/Type1/BaseFont/Helvetica/Encoding/WinAnsiEncoding>>";
  $objs[4]="<</Type/Font/Subtype/Type1/BaseFont/Helvetica-Bold/Encoding/WinAnsiEncoding>>";
  $objs[5]="<</Type/Font/Subtype/Type1/BaseFont/Helvetica-Oblique/Encoding/WinAnsiEncoding>>";
  $kids=[]; $num=5;
  foreach($streams as $st){
    $pn=++$num; $cn=++$num; $kids[]="$pn 0 R";
    $objs[$pn]="<</Type/Page/Parent 2 0 R/MediaBox[0 0 595.28 841.89]/Resources<</Font<</F1 3 0 R/F2 4 0 R/F3 5 0 R>>>>/Contents $cn 0 R>>";
    $objs[$cn]="<</Length ".strlen($st).">>\nstream\n".$st."\nendstream";
  }
  $objs[1]="<</Type/Catalog/Pages 2 0 R>>";
  $objs[2]="<</Type/Pages/Kids[".implode(' ',$kids)."]/Count ".count($kids).">>";
  ksort($objs);
  $pdf="%PDF-1.4\n"; $offs=[];
  foreach($objs as $n=>$body){ $offs[$n]=strlen($pdf); $pdf.="$n 0 obj\n$body\nendobj\n"; }
  $xref=strlen($pdf); $max=max(array_keys($objs));
  $pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";
  for($i=1;$i<=$max;$i++){ $pdf.=str_pad((string)($offs[$i]??0),10,'0',STR_PAD_LEFT)." 00000 n \n"; }
  $pdf.="trailer\n<</Size ".($max+1)." /Root 1 0 R>>\nstartxref\n$xref\n%%EOF";
  return $pdf;
}
PHPFN;

/* [1] fonksiyonu ch_text_pdf'ten HEMEN once ekle */
$anchorFn="/* saf-PHP gecerli PDF";
if(strpos($src,$anchorFn)===false) exit("  ✗ ch_text_pdf capa bulunamadi — degistirilmedi.\n");
$src=str_replace($anchorFn, ltrim($fn)."\n\n".$anchorFn, $src);
echo "  ✓ ch_premium_pdf fonksiyonu eklendi\n";

/* [2] cagriyi premium'a cevir */
$oc='$pdf=ch_text_pdf($txt);';
$nc='$pdf=ch_premium_pdf($txt); /*CH_DL_PREMIUM_CALL*/';
$c=substr_count($src,$oc);
if($c!==1){ echo "  ✗ cagri capasi ($c, 1 beklenir) — degistirilmedi.\n"; exit; }
$src=str_replace($oc,$nc,$src);
echo "  ✓ dl.php cagrisi ch_premium_pdf'e cevrildi\n";

$tmp=tempnam(sys_get_temp_dir(),'dlp').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-dlpremium-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_DL_PREMIUM_CALL')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_DL_PREMIUM diskte (dl.php ".strlen($chk)." B)\n";
echo "  Test: App/mobil -> chat veya sablondan 'Als PDF speichern' -> premium DIN mektup:\n";
echo "  ust antet, sag tarih, kalin konu, duzgun marj, imza, altbilgi + sayfa no.\n";
