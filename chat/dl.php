<?php
/* ChatHelp — dl.php v2 (CH_DL2) — POST html+name -> GERCEK PDF indirme (ayni sayfada).
   fmt=html => eski HTML-indirme davranisi. PDF uretilemezse HTML'e geri doner. */
error_reporting(E_ERROR | E_PARSE);/*CH_NOLOGO*/
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
    $s.=$col($NAVY)." RG 0.8 w $ML ".($PH-64)." m ".($PW-$MR)." ".($PH-64)." l S\n";
    $s.=$col($GOLD)." RG 2 w $ML ".($PH-64.6)." m ".($ML+46)." ".($PH-64.6)." l S\n";
    foreach($items as $it){ $s.=$T($it[0],$it[1],$it[2],$it[3],$it[4],$encF($it[5],$it[6])); }
    if($tot>1){ $s.=$col($GRAY)." RG 0.5 w $ML 58 m ".($PW-$MR)." 58 l S\n"; $seite="Seite $p von $tot"; $s.=$T($PW-$MR-$Wc($seite,8,false),46,'F1',8,$GRAY,$encF($seite,false)); }
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


function ch_html_to_text($h){
  $h=preg_replace('#<script\b[^>]*>.*?</script>#is',' ',$h);
  $h=preg_replace('#<style\b[^>]*>.*?</style>#is',' ',$h);
  $h=preg_replace('#<head\b[^>]*>.*?</head>#is',' ',$h);
  $h=preg_replace('#<br\s*/?\s*>#i',"\n",$h);
  $h=preg_replace('#</(p|div|h1|h2|h3|h4|h5|h6|tr|table|section|article|header|footer|ul|ol|blockquote|li)>#i',"\n",$h);
  $h=preg_replace('#<(h1|h2|h3)\b[^>]*>#i',"\n",$h);
  $h=preg_replace('#<li\b[^>]*>#i',"\n- ",$h);
  $h=preg_replace('#<hr\s*/?\s*>#i',"\n--------------------------------\n",$h);
  $h=preg_replace('#<td\b[^>]*>#i','  ',$h);
  $h=strip_tags($h);
  $h=html_entity_decode($h,ENT_QUOTES|ENT_HTML5,'UTF-8');
  $h=str_replace("\r",'',$h);
  $h=preg_replace('/[ \t]+\n/',"\n",$h);
  $h=preg_replace('/\n{3,}/',"\n\n",$h);
  $h=preg_replace('/[ \t]{2,}/',' ',$h);
  return trim($h);
}

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
    /* premium ust cizgi aksani (markasiz) */
    $s.=$col($NAVY)." RG 0.8 w ".$ML." ".($PH-64)." m ".($PW-$MR)." ".($PH-64)." l S\n";
    $s.=$col($GOLD)." RG 2 w ".$ML." ".($PH-64.6)." m ".($ML+46)." ".($PH-64.6)." l S\n";
    /* body */
    foreach($items as $it){ $s.=$T($it[0],$it[1],$it[2],$it[3],$it[4],$it[5]); }
    if($tot>1){ $s.=$col($GRAY)." RG 0.5 w ".$ML." 58 m ".($PW-$MR)." 58 l S\n"; $seite='Seite '.$p.' von '.$tot; $s.=$T($PW-$MR-$fw($seite,8,false),46,'F1',8,$GRAY,$seite); }
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

/* saf-PHP gecerli PDF (Helvetica, A4, cok sayfa) — postversand.php ch_text_pdf ile ayni cekirdek */
function ch_text_pdf(string $text): string {
  $t = @iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$text); if($t===false) $t=$text;
  $lines=[]; foreach(explode("\n",str_replace("\r",'',$t)) as $ln){
    if($ln===''){ $lines[]=''; continue; }
    while(strlen($ln)>92){ $cut=strrpos(substr($ln,0,92),' '); if($cut===false||$cut<40)$cut=92; $lines[]=substr($ln,0,$cut); $ln=ltrim(substr($ln,$cut)); }
    $lines[]=$ln;
  }
  $perPage=48; $pages=array_chunk($lines,$perPage); if(!$pages)$pages=[['']];
  $objs=[]; $objs[1]='';
  $kids=[]; $n=3; $pageObjs=[];
  foreach($pages as $pl){
    $pn=++$n; $cn=++$n; $kids[]="$pn 0 R";
    $esc=function($s){ return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$s); };
    $stream="BT /F1 11 Tf 56 786 Td 15 TL\n";
    foreach($pl as $i=>$ln){ $stream.=($i? "T*\n":"")."(".$esc($ln).") Tj\n"; }
    $stream.="ET";
    $pageObjs[$pn]="<</Type/Page /Parent 2 0 R /MediaBox[0 0 595 842] /Resources<</Font<</F1 3 0 R>>>> /Contents $cn 0 R>>";
    $pageObjs[$cn]="<</Length ".strlen($stream).">>\nstream\n$stream\nendstream";
  }
  $objs[1]="<</Type/Catalog /Pages 2 0 R>>";
  $objs[2]="<</Type/Pages /Kids[".implode(' ',$kids)."] /Count ".count($kids).">>";
  $objs[3]="<</Type/Font /Subtype/Type1 /BaseFont/Helvetica /Encoding/WinAnsiEncoding>>";
  foreach($pageObjs as $k=>$v) $objs[$k]=$v;
  ksort($objs);
  $pdf="%PDF-1.4\n"; $offs=[];
  foreach($objs as $num=>$body){ $offs[$num]=strlen($pdf); $pdf.="$num 0 obj\n$body\nendobj\n"; }
  $xref=strlen($pdf); $max=max(array_keys($objs));
  $pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";
  for($i=1;$i<=$max;$i++){ $pdf.=str_pad((string)($offs[$i]??0),10,'0',STR_PAD_LEFT)." 00000 n \n"; }
  $pdf.="trailer\n<</Size ".($max+1)." /Root 1 0 R>>\nstartxref\n$xref\n%%EOF";
  return $pdf;
}

$html=(string)($_POST['html'] ?? '');
if($html==='' && isset($_POST['text'])) $html=(string)$_POST['text'];
if($html===''){ http_response_code(400); header('Content-Type: text/plain'); exit('no_content'); }
if(strlen($html) > 6*1024*1024){ http_response_code(413); header('Content-Type: text/plain'); exit('too_big'); }

$name=(string)($_POST['name'] ?? 'ChatHelp-Dokument');
$name=preg_replace('/[^A-Za-z0-9._\- ]/','',basename($name)); if($name==='') $name='ChatHelp-Dokument';
$base=preg_replace('/\.(html?|pdf)$/i','',$name); if($base==='') $base='ChatHelp-Dokument';

$fmt=strtolower((string)($_POST['fmt'] ?? ($_GET['fmt'] ?? 'pdf')));
if($fmt!=='html'){
  $txt=ch_html_to_text($html);
  if($txt!==''){
    $__fr=__DIR__.'/fonts/dvs.ttf'; $__fb=__DIR__.'/fonts/dvsb.ttf'; if(is_file($__fr)&&is_file($__fb)&&function_exists('ch_premium_pdf_uni')){ $pdf=ch_premium_pdf_uni($txt,(string)file_get_contents($__fr),(string)file_get_contents($__fb)); } else { $pdf=ch_premium_pdf($txt); } /*CH_DL_UNI_CALL*/
    if(strlen($pdf)>200){
      header('Content-Type: application/pdf');
      header('Content-Disposition: attachment; filename="'.$base.'.pdf"');
      header('Content-Length: '.strlen($pdf));
      header('Cache-Control: no-store');
      echo $pdf; exit;
    }
  }
}
header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$base.'.html"');
header('Cache-Control: no-store');
echo $html;