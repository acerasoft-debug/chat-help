<?php
/* dump-theme (READ-ONLY) — tema motoru ici: chApplyTheme govde, ch_theme get/set,
   matchMedia/prefers-color-scheme, iki selector script bloklarinin ID+sinirlari,
   ust(TT) selector'un apply fonksiyonu. Kalicilik bug'i + guvenli silme icin.
   KULLANIM: pull2.php?key=...&files=dump-theme.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "dump-theme (READ-ONLY)\n\n";
$s=@file_get_contents(__DIR__.'/index.php');
if($s===false) exit("okunamadi\n");

function win($s,$p,$b,$a){ $x=substr($s,max(0,$p-$b),$b+$a); return preg_replace('/[ \t]+/',' ',$x); }

/* 1) chApplyTheme govdesi */
echo "=== chApplyTheme tanimi ===\n";
$p=strpos($s,'chApplyTheme=function'); if($p===false)$p=strpos($s,'function chApplyTheme');
if($p!==false){ echo win($s,$p,20,900)."\n"; } else echo "  (bulunamadi)\n";

/* 2) ch_theme get/set tum baglamlar */
echo "\n=== ch_theme get/set baglamlari ===\n";
foreach(['setItem(\'ch_theme','setItem("ch_theme','getItem(\'ch_theme','getItem("ch_theme'] as $k){
  $off=0; while(($q=strpos($s,$k,$off))!==false){ echo "  [".$q."] …".trim(win($s,$q,60,120))."…\n"; $off=$q+strlen($k); }
}

/* 3) matchMedia / prefers-color-scheme */
echo "\n=== matchMedia / prefers-color-scheme baglamlari ===\n";
$off=0; $c=0; while(($q=strpos($s,'prefers-color-scheme',$off))!==false && $c<4){ echo "  [".$q."] …".trim(win($s,$q,120,120))."…\n\n"; $off=$q+5; $c++; }
$off=0; $c=0; while(($q=strpos($s,'matchMedia',$off))!==false && $c<6){ echo "  matchMedia[".$q."] …".trim(win($s,$q,40,90))."…\n"; $off=$q+8; $c++; }

/* 4) iki selector script blogunun ID'si + sinirlari */
echo "\n=== ALT selector (chtheme-btn) iceren <script id> ===\n";
$p=strpos($s,'chtheme-btn');
if($p!==false){ $so=strrpos(substr($s,0,$p),'<script'); $eo=strpos($s,'</script>',$p);
  echo "  script BASI @".$so.": ".trim(win($s,$so,0,120))."\n";
  echo "  script SONU @".$eo." (uzunluk ".($eo-$so+9).")\n";
  echo "  bas 60: ".trim(substr($s,$so,60))."\n";
  echo "  son 80: ...".trim(substr($s,max(0,$eo-70),79))."\n";
}

echo "\n=== UST selector (TT={de:{t:Design...}) iceren <script id> + apply cagirisi ===\n";
$p=strpos($s,'n:"Marine (Standard)"');
if($p!==false){ $so=strrpos(substr($s,0,$p),'<script'); $eo=strpos($s,'</script>',$p);
  echo "  script BASI @".$so.": ".trim(win($s,$so,0,120))."\n";
  echo "  script SONU @".$eo." (uzunluk ".($eo-$so+9).")\n";
  /* bu blokta hangi apply fonksiyonu? */
  $blk=substr($s,$so,$eo-$so+9);
  foreach(['chApplyTheme','onclick','ch_theme','data-t=','classList','setAttribute'] as $k){
    if(preg_match('/.{0,50}'.preg_quote($k,'/').'.{0,70}/',$blk,$mm)) echo "    ~".$k.": ".trim(preg_replace('/\s+/',' ',$mm[0]))."\n";
  }
}

/* 5) yuklemede tema init: ch_theme okuyup uygulayan yer */
echo "\n=== yuklemede ch_theme okuyup uygulama (init) ===\n";
$off=0;$c=0;
while(($q=strpos($s,'ch_theme',$off))!==false && $c<8){ echo "  [".$q."] …".trim(win($s,$q,50,90))."…\n"; $off=$q+8; $c++; }
echo "\nBITTI.\n";
