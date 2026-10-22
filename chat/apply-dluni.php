<?php
/**
 * ChatHelp — apply-dluni (CH_DL_UNI) — dl.php'ye Unicode (TrueType gomulu) premium
 *  PDF ureticisi ekler (ch_premium_pdf_uni + chf_* yardimcilar) ve cagriyi ona cevirir.
 *  Fontlar (chat/fonts/dvs.ttf, dvsb.ttf) VARSA Unicode yol; yoksa eski ch_premium_pdf.
 *  Onkosul: apply-fonts.php calistirilmis olmali.
 * KULLANIM: pull2.php?key=...&files=apply-dluni.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-dluni BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/dl.php';
$src=@file_get_contents($file);
if($src===false) exit("dl.php okunamadi\n");
if(strpos($src,'CH_DL_UNI')!==false) exit("Zaten ekli (CH_DL_UNI).\n");
if(strpos($src,'CH_DL_PREMIUM_CALL')===false) exit("Once apply-dlpremium.php gerekli (CH_DL_PREMIUM_CALL yok).\n");

$fn = <<<'PHPCODE'
/* CH_DL_UNI — Unicode gomulu premium PDF (ch_premium_pdf_uni + chf_* yardimcilar) */
/* CH_DL_UNI — Unicode (TrueType gomulu) premium DIN mektup PDF (saf-PHP) */
function chf_u16($d,$o){ return (ord($d[$o])<<8)|ord($d[$o+1]); }
function chf_s16($d,$o){ $v=chf_u16($d,$o); return $v>=0x8000?$v-0x10000:$v; }
function chf_u32($d,$o){ return (ord($d[$o])<<24)|(ord($d[$o+1])<<16)|(ord($d[$o+2])<<8)|ord($d[$o+3]); }
function chf_ttf_parse($d){
  $nt=chf_u16($d,4); $tb=[];
  for($i=0;$i<$nt;$i++){ $o=12+$i*16; $tb[substr($d,$o,4)]=[chf_u32($d,$o+8),chf_u32($d,$o+12)]; }
  $head=$tb['head'][0]; $em=chf_u16($d,$head+18);
  $xMin=chf_s16($d,$head+36);$yMin=chf_s16($d,$head+38);$xMax=chf_s16($d,$head+40);$yMax=chf_s16($d,$head+42);
  $hhea=$tb['hhea'][0]; $asc=chf_s16($d,$hhea+4);$desc=chf_s16($d,$hhea+6);$numHM=chf_u16($d,$hhea+34);
  $ng=chf_u16($d,$tb['maxp'][0]+4);
  $hmtx=$tb['hmtx'][0]; $adv=[]; $last=0;
  for($i=0;$i<$ng;$i++){ if($i<$numHM)$last=chf_u16($d,$hmtx+$i*4); $adv[$i]=$last; }
  $cmap=$tb['cmap'][0]; $ns=chf_u16($d,$cmap+2); $sub=0;
  for($i=0;$i<$ns;$i++){ $po=$cmap+4+$i*8; $pid=chf_u16($d,$po);$eid=chf_u16($d,$po+2);$oo=chf_u32($d,$po+4);
    if($pid==3&&$eid==1){ $sub=$cmap+$oo; break; } if(($pid==3&&$eid==0)||$pid==0){ $sub=$cmap+$oo; } }
  $u2g=[];
  if($sub){ $fmt=chf_u16($d,$sub);
    if($fmt==4){ $sx2=chf_u16($d,$sub+6);$sc=$sx2/2;$eO=$sub+14;$stO=$eO+$sx2+2;$dO=$stO+$sx2;$rO=$dO+$sx2;
      for($s=0;$s<$sc;$s++){ $end=chf_u16($d,$eO+$s*2);$st=chf_u16($d,$stO+$s*2);$dl=chf_u16($d,$dO+$s*2);$ro=chf_u16($d,$rO+$s*2);
        for($c=$st;$c<=$end && $c!=0xFFFF;$c++){
          if($ro==0){ $g=($c+$dl)&0xFFFF; } else { $gi=$rO+$s*2+$ro+($c-$st)*2; if($gi+1>=strlen($d))continue; $g=chf_u16($d,$gi); if($g!=0)$g=($g+$dl)&0xFFFF; }
          if($g!=0)$u2g[$c]=$g; } } } }
  return ['em'=>$em,'bbox'=>[$xMin,$yMin,$xMax,$yMax],'asc'=>$asc,'desc'=>$desc,'ng'=>$ng,'adv'=>$adv,'u2g'=>$u2g];
}
function chf_utf8cps($s){ $cp=[]; $n=strlen($s); $i=0;
  while($i<$n){ $c=ord($s[$i]);
    if($c<0x80){ $cp[]=$c; $i++; }
    elseif($c<0xE0){ $cp[]=(($c&0x1F)<<6)|(ord($s[$i+1])&0x3F); $i+=2; }
    elseif($c<0xF0){ $cp[]=(($c&0x0F)<<12)|((ord($s[$i+1])&0x3F)<<6)|(ord($s[$i+2])&0x3F); $i+=3; }
    else{ $cp[]=(($c&0x07)<<18)|((ord($s[$i+1])&0x3F)<<12)|((ord($s[$i+2])&0x3F)<<6)|(ord($s[$i+3])&0x3F); $i+=4; }
  } return $cp;
}
function ch_premium_pdf_uni(string $text, string $fReg, string $fBold): string {
  $PW=595.28;$PH=841.89;$ML=64.0;$MR=64.0;$MB=72.0;$W=$PW-$ML-$MR;
  $NAVY=[0.055,0.09,0.20];$GOLD=[0.80,0.62,0.24];$INK=[0.13,0.13,0.16];$GRAY=[0.45,0.45,0.50];
  $R=chf_ttf_parse($fReg); $B=chf_ttf_parse($fBold);
  $scaleR=1000.0/$R['em']; $scaleB=1000.0/$B['em'];
  // encode + width helpers per font
  $enc=function($str,$F){ $out=''; foreach(chf_utf8cps($str) as $cp){ $g=$F['u2g'][$cp]??($F['u2g'][0x20]??0); $out.=sprintf('%04X',$g); } return $out; };
  $wid=function($str,$size,$F,$sc){ $w=0; foreach(chf_utf8cps($str) as $cp){ $g=$F['u2g'][$cp]??($F['u2g'][0x20]??0); $w+=($F['adv'][$g]??0)*$sc; } return $w/1000.0*$size; };
  $Wc=function($str,$size,$bold)use($wid,$R,$B,$scaleR,$scaleB){ return $bold?$wid($str,$size,$B,$scaleB):$wid($str,$size,$R,$scaleR); };
  $wrap=function($line,$size,$bold)use($Wc,$W){ $line=rtrim($line); if($line==='')return [''];
    $out=[];$cur='';
    foreach(explode(' ',$line) as $wd){
      // asiri uzun kelime: karakter karakter kir
      while($Wc($wd,$size,$bold)>$W){ $acc=''; $cps=preg_split('//u',$wd,-1,PREG_SPLIT_NO_EMPTY);
        foreach($cps as $k=>$ch){ if($Wc($acc.$ch,$size,$bold)>$W){ break; } $acc.=$ch; }
        if($acc===''){ $acc=$cps[0]??$wd; } if($cur!==''){ $out[]=$cur; $cur=''; } $out[]=$acc; $wd=mb_substr($wd,mb_strlen($acc,'UTF-8'),null,'UTF-8'); if($wd===''||$wd===false)break; }
      $try=($cur===''?$wd:$cur.' '.$wd);
      if($Wc($try,$size,$bold)<=$W){ $cur=$try; } else { if($cur!=='')$out[]=$cur; $cur=$wd; } }
    if($cur!=='')$out[]=$cur; return $out;
  };
  // yapisal tespit
  $uL=explode("\n",str_replace("\r","",$text)); $N=count($uL);
  $subjIdx=-1;$closeIdx=-1;$dateIdx=-1;
  for($i=0;$i<$N;$i++){ $u=trim($uL[$i]);
    if($subjIdx<0 && preg_match('/^(Betreff|Betrifft|Konu|Subject|Objet|Asunto|Oggetto|Onderwerp)\s*:/iu',$u))$subjIdx=$i;
    if($closeIdx<0 && preg_match('/^(Mit freundlichen Grüßen|Mit freundlichem Gruß|Freundliche Grüße|Mit besten Grüßen|Hochachtungsvoll|Saygılarımla|Yours sincerely|Kind regards|Sincerely|Cordialement|Atentamente|Distinti saluti|Met vriendelijke groet)/iu',$u))$closeIdx=$i;
    if($dateIdx<0 && $i<9 && $u!=='' && mb_strlen($u,'UTF-8')<48 && preg_match('/\d{1,2}[.\/]\s?\d{1,2}[.\/]\s?\d{2,4}/u',$u) && !preg_match('/[a-zäöüçşğ]{6,}.*[a-zäöüçşğ]{6,}/iu',$u))$dateIdx=$i; }
  $sigIdx=-1; if($closeIdx>=0){ for($i=$closeIdx+1;$i<$N;$i++){ if(trim($uL[$i])!==''){ $sigIdx=$i;break; } } }
  // akit
  $contentTop=$PH-108;$pages=[];$cur=[];$y=$contentTop;
  $newp=function()use(&$pages,&$cur,&$y,$contentTop){ $pages[]=$cur;$cur=[];$y=$contentTop; };
  for($i=0;$i<$N;$i++){ $u=trim($uL[$i]);
    if($u===''){ $y-=8.5; if($y<$MB)$newp(); continue; }
    if($i===$subjIdx){ if($y-24<$MB)$newp(); $y-=8; foreach($wrap($u,11.8,true) as $sl){ if($y-16<$MB)$newp(); $cur[]=[$ML,$y,'F2',11.8,$INK,$sl,true]; $y-=16.5; } $y-=5; continue; }
    if($i===$dateIdx){ if($y-18<$MB)$newp(); $tw=$Wc($u,10.4,false); $cur[]=[$PW-$MR-$tw,$y,'F1',10.4,$INK,$u,false]; $y-=17; continue; }
    $bold=($i===$sigIdx); $font=$bold?'F2':'F1'; $size=$bold?10.8:10.4; $lead=15.6;
    foreach($wrap($u,$size,$bold) as $sl){ if($y-$lead<$MB)$newp(); $cur[]=[$ML,$y,$font,$size,$INK,$sl,$bold]; $y-=$lead; } }
  $pages[]=$cur; $tot=count($pages); if($tot===0){$pages=[[]];$tot=1;}
  // stream
  $col=function($c){ return round($c[0],3).' '.round($c[1],3).' '.round($c[2],3); };
  $T=function($x,$y,$fn,$size,$c,$hex)use($col){ return $col($c)." rg\n/$fn $size Tf\nBT ".round($x,2)." ".round($y,2)." Td <$hex> Tj ET\n"; };
  $encF=function($str,$bold)use($enc,$R,$B){ return $enc($str,$bold?$B:$R); };
  $streams=[];
  foreach($pages as $pi=>$items){ $p=$pi+1; $s='';
    $s.=$T($ML,$PH-56,'F2',13,$NAVY,$encF('Chat',true));
    $s.=$T($ML+$Wc('Chat',13,true),$PH-56,'F2',13,$GOLD,$encF('Help',true));
    $s.=$T($PW-$MR-$Wc('chat-help.com',8.5,false),$PH-53,'F1',8.5,$GRAY,$encF('chat-help.com',false));
    $s.=$col($NAVY)." RG 0.8 w $ML ".($PH-64)." m ".($PW-$MR)." ".($PH-64)." l S\n";
    $s.=$col($GOLD)." RG 2 w $ML ".($PH-64.6)." m ".($ML+46)." ".($PH-64.6)." l S\n";
    foreach($items as $it){ $s.=$T($it[0],$it[1],$it[2],$it[3],$it[4],$encF($it[5],$it[6])); }
    $s.=$col($GRAY)." RG 0.5 w $ML 58 m ".($PW-$MR)." 58 l S\n";
    $s.=$T($ML,46,'F1',8,$GRAY,$encF('Erstellt mit ChatHelp',false));
    $seite="Seite $p von $tot"; $s.=$T($PW-$MR-$Wc($seite,8,false),46,'F1',8,$GRAY,$encF($seite,false));
    $streams[]=$s;
  }
  // font objelerini kur
  $mkfont=function($F,$scale,$ttf,$psname){
    $wlist=''; for($g=0;$g<$F['ng'];$g++){ $wlist.=round(($F['adv'][$g]??0)*$scale).' '; }
    $bbox='['.round($F['bbox'][0]*$scale).' '.round($F['bbox'][1]*$scale).' '.round($F['bbox'][2]*$scale).' '.round($F['bbox'][3]*$scale).']';
    $comp=gzcompress($ttf,6);
    return ['w'=>'[0 ['.trim($wlist).']]','bbox'=>$bbox,'asc'=>round($F['asc']*$scale),'desc'=>round($F['desc']*$scale),'ttf'=>$comp,'len1'=>strlen($ttf),'ps'=>$psname];
  };
  $F1=$mkfont($R,$scaleR,$fReg,'DejaVuSans');
  $F2=$mkfont($B,$scaleB,$fBold,'DejaVuSans-Bold');
  $mktou=function($F){
    $g2u=[]; foreach($F['u2g'] as $u=>$g){ if(!isset($g2u[$g])) $g2u[$g]=$u; }
    ksort($g2u); $items=array_keys($g2u);
    $cmap="/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n/CIDSystemInfo<</Registry(Adobe)/Ordering(UCS)/Supplement 0>> def\n/CMapName/Adobe-Identity-UCS def\n/CMapType 2 def\n1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange\n";
    $chunks=array_chunk($items,100,true);
    foreach($chunks as $ck){ $cmap.=count($ck)." beginbfchar\n"; foreach($ck as $g){ $cmap.=sprintf("<%04X> <%04X>\n",$g,$g2u[$g]); } $cmap.="endbfchar\n"; }
    $cmap.="endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend";
    return $cmap;
  };
  $TU1=$mktou($R); $TU2=$mktou($B);
  $objs=[];
  $objs[3]="<</Type/Font/Subtype/Type0/BaseFont/{$F1['ps']}/Encoding/Identity-H/DescendantFonts[4 0 R]/ToUnicode 11 0 R>>";
  $objs[4]="<</Type/Font/Subtype/CIDFontType2/BaseFont/{$F1['ps']}/CIDSystemInfo<</Registry(Adobe)/Ordering(Identity)/Supplement 0>>/FontDescriptor 5 0 R/CIDToGIDMap/Identity/DW 500/W {$F1['w']}>>";
  $objs[5]="<</Type/FontDescriptor/FontName/{$F1['ps']}/Flags 32/FontBBox {$F1['bbox']}/ItalicAngle 0/Ascent {$F1['asc']}/Descent {$F1['desc']}/CapHeight 700/StemV 80/FontFile2 6 0 R>>";
  $objs[6]="<</Length ".strlen($F1['ttf'])."/Length1 {$F1['len1']}/Filter/FlateDecode>>\nstream\n{$F1['ttf']}\nendstream";
  $objs[7]="<</Type/Font/Subtype/Type0/BaseFont/{$F2['ps']}/Encoding/Identity-H/DescendantFonts[8 0 R]/ToUnicode 12 0 R>>";
  $objs[8]="<</Type/Font/Subtype/CIDFontType2/BaseFont/{$F2['ps']}/CIDSystemInfo<</Registry(Adobe)/Ordering(Identity)/Supplement 0>>/FontDescriptor 9 0 R/CIDToGIDMap/Identity/DW 500/W {$F2['w']}>>";
  $objs[9]="<</Type/FontDescriptor/FontName/{$F2['ps']}/Flags 32/FontBBox {$F2['bbox']}/ItalicAngle 0/Ascent {$F2['asc']}/Descent {$F2['desc']}/CapHeight 700/StemV 120/FontFile2 10 0 R>>";
  $objs[10]="<</Length ".strlen($F2['ttf'])."/Length1 {$F2['len1']}/Filter/FlateDecode>>\nstream\n{$F2['ttf']}\nendstream";
  $objs[11]="<</Length ".strlen($TU1).">>\nstream\n$TU1\nendstream";
  $objs[12]="<</Length ".strlen($TU2).">>\nstream\n$TU2\nendstream";
  $kids=[]; $num=12;
  foreach($streams as $st){ $pn=++$num;$cn=++$num;$kids[]="$pn 0 R";
    $objs[$pn]="<</Type/Page/Parent 2 0 R/MediaBox[0 0 595.28 841.89]/Resources<</Font<</F1 3 0 R/F2 7 0 R>>>>/Contents $cn 0 R>>";
    $objs[$cn]="<</Length ".strlen($st).">>\nstream\n$st\nendstream"; }
  $objs[1]="<</Type/Catalog/Pages 2 0 R>>";
  $objs[2]="<</Type/Pages/Kids[".implode(' ',$kids)."]/Count ".count($kids).">>";
  ksort($objs);
  $pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n"; $offs=[];
  foreach($objs as $n=>$b){ $offs[$n]=strlen($pdf); $pdf.="$n 0 obj\n$b\nendobj\n"; }
  $xref=strlen($pdf); $max=max(array_keys($objs));
  $pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";
  for($i=1;$i<=$max;$i++){ $pdf.=str_pad((string)($offs[$i]??0),10,'0',STR_PAD_LEFT)." 00000 n \n"; }
  $pdf.="trailer\n<</Size ".($max+1)." /Root 1 0 R>>\nstartxref\n$xref\n%%EOF";
  return $pdf;
}

PHPCODE;

/* [1] fonksiyonlari en uste (ilk error_reporting'ten sonra) ekle */
$anch="error_reporting(E_ERROR | E_PARSE);";
$pos=strpos($src,$anch);
if($pos===false) exit("  ✗ error_reporting capasi yok\n");
$ins=$pos+strlen($anch);
$src=substr($src,0,$ins)."\n".$fn.substr($src,$ins);
echo "  ✓ ch_premium_pdf_uni + chf_* eklendi\n";

/* [2] cagriyi font-fallback ile Unicode'a cevir */
$oc="\$pdf=ch_premium_pdf(\$txt); /*CH_DL_PREMIUM_CALL*/";
$nc="\$__fr=__DIR__.'/fonts/dvs.ttf'; \$__fb=__DIR__.'/fonts/dvsb.ttf'; if(is_file(\$__fr)&&is_file(\$__fb)&&function_exists('ch_premium_pdf_uni')){ \$pdf=ch_premium_pdf_uni(\$txt,(string)file_get_contents(\$__fr),(string)file_get_contents(\$__fb)); } else { \$pdf=ch_premium_pdf(\$txt); } /*CH_DL_UNI_CALL*/";
$c=substr_count($src,$oc);
if($c!==1){ echo "  ✗ cagri capasi ($c, 1 beklenir) — degistirilmedi.\n"; exit; }
$src=str_replace($oc,$nc,$src);
echo "  ✓ dl.php cagrisi Unicode+fallback'e cevrildi\n";

$tmp=tempnam(sys_get_temp_dir(),'dlu').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-dluni-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_DL_UNI_CALL')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_DL_UNI diskte (dl.php ".strlen($chk)." B)\n";
echo "  Test: TR/DE belge -> 'Als PDF speichern' -> ş/ğ/ı/İ + ä/ö/ü/ß dogru, aranabilir.\n";
