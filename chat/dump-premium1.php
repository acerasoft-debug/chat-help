<?php
/** ChatHelp — dump-premium1: premium paket icin kod denetimi. SADECE OKUR.
 *  (A) Staatliche kalintilari (B) dil/ulke kalicilik mantigi (C) tema/CSS
 *  (D) jobs e-posta akisi (E) cache markerlari (F) api doGenerate sozlesmesi */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx = @file_get_contents(__DIR__.'/index.php');
$api = @file_get_contents(__DIR__.'/api.php');
if ($idx === false || $api === false) exit("index/api okunamadi\n");
echo "dump-premium1 (idx=".strlen($idx)." api=".strlen($api)." bayt)\n";

function FX($s,$name,$lbl,$cap=3000){
  echo "\n──── $lbl ────\n";
  $p=strpos($s,$name);
  if($p===false){ echo "(BULUNAMADI: $name)\n"; return; }
  $b=strpos($s,'{',$p); if($b===false){ echo substr($s,$p,400)."\n"; return; }
  $d=0;$i=$b;$L=strlen($s);
  for(;$i<$L;$i++){ $c=$s[$i]; if($c==='{')$d++; elseif($c==='}'){ $d--; if(!$d){ $i++; break; } } }
  echo substr($s,$p,min($i-$p,$cap)).(($i-$p)>$cap?"\n…(kirpildi ".($i-$p)." bayt)":"")."\n";
}
function OCC($s,$needle,$lbl,$ctx=130,$max=25){
  echo "\n──── $lbl ('$needle' gecen yerler) ────\n";
  $o=0;$n=0;
  while(($p=stripos($s,$needle,$o))!==false && $n<$max){
    $win=substr($s,max(0,$p-$ctx),$ctx*2+strlen($needle));
    echo "  @$p: ".trim(preg_replace('/\s+/',' ',$win))."\n";
    $o=$p+strlen($needle); $n++;
  }
  if(!$n) echo "  (yok)\n"; else echo "  toplam(ilk $max): $n\n";
}

echo "\n################ A) STAATLICHE KALINTILARI ################\n";
OCC($idx,'Staatliche','index.php');
OCC($idx,'showStaatKat','index.php cagri noktalari',100,15);

echo "\n################ B) DIL / ULKE KALICILIK ################\n";
FX($idx,'function setCountry','setCountry',2500);
FX($idx,'function setLang','setLang',1500);
FX($idx,'function applyUI','applyUI (ilk 1500)',1500);
FX($idx,'function detectLoc','detectLoc — geo override',2500);
OCC($idx,"ch_cc'",'ch_cc localStorage kullanimi',90,15);
OCC($idx,'ch_uilang','ch_uilang kullanimi',90,15);

echo "\n################ C) TEMA / CSS ################\n";
$p=strpos($idx,':root');
echo "\n──── :root ilk blok ────\n".($p!==false?substr($idx,$p,900):'(yok)')."\n";
foreach(['CH_LIGHTER_THEME','CH_PREMIUM_SKIN','ch-fall-premium'] as $m)
  echo "marker $m: ".(strpos($idx,$m)!==false?'VAR':'yok')."\n";
$p=strrpos($idx,'</body>');
echo "\n──── </body> oncesi son 600 bayt (enjeksiyon siralamasi) ────\n".substr($idx,max(0,$p-600),620)."\n";

echo "\n################ D) JOBS E-POSTA AKISI ################\n";
OCC($idx,'coverletter','index.php cover-letter akisi',150,12);
OCC($idx,'chJob','chJob* fonksiyonlari',110,20);
FX($api,'function chJobCoverLetter','api chJobCoverLetter',1800);
OCC($api,"'coverletter'","api dispatch",120,5);
OCC($api,'sendMail','api sendMail kullanimi',120,10);

echo "\n################ E) CACHE MARKERLARI ################\n";
foreach(['CH_NOCACHE','CH_STRONG_NOCACHE'] as $m)
  echo "index.php $m: ".(strpos($idx,$m)!==false?'VAR':'yok')." · api.php $m: ".(strpos($api,$m)!==false?'VAR':'yok')."\n";
$ht=@file_get_contents(dirname(__DIR__).'/.htaccess');
echo "\n──── kok .htaccess ────\n".($ht===false?'(yok/okunamadi)':substr($ht,0,1500))."\n";
$ht2=@file_get_contents(__DIR__.'/.htaccess');
echo "\n──── chat/.htaccess ────\n".($ht2===false?'(yok/okunamadi)':substr($ht2,0,1500))."\n";

echo "\n################ F) API doGenerate SOZLESMESI ################\n";
OCC($api,'doGenerate','api dispatch/cagri',130,6);
FX($api,'function doGenerate','doGenerate govde (ilk 2500)',2500);
OCC($api,"input','r",'php input okuma',120,4);
OCC($api,'answers','api answers alani (ilk 12)',100,12);

echo "\n=== BITTI. Ciktinin TAMAMINI gonder. ===\n";
