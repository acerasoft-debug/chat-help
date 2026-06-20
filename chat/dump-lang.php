<?php
/** ChatHelp — dump-lang: dil sistemi (UI_TEXTS, seçici, setLang, auto-detect, Konto) */
header('Content-Type: text/plain; charset=UTF-8');
$idx=@file_get_contents(__DIR__.'/index.php');
if($idx===false) exit("index.php yok\n");
function R($s,$needle,$b,$l,$lbl){echo "\n──── $lbl ────\n";$p=strpos($s,$needle);if($p===false){echo "(BULUNAMADI: '$needle')\n";return;}echo substr($s,max(0,$p-$b),$l)."\n";}

echo "### UI_TEXTS (hangi diller var?) ###\n";
R($idx,'UI_TEXTS',0,1400,'UI_TEXTS tanımı');

echo "\n### Dil/bayrak seçici ###\n";
R($idx,'fl-de',-200,700,'bayrak seçici (fl-de çevresi)');
R($idx,'lf2',-100,400,'lf2 (dil bayrakları)');
R($idx,'ia-lfs',-60,500,'ia-lfs (alt bar dil seçici)');

echo "\n### Dil ayarlama fonksiyonları ###\n";
foreach(['function setFlag','function setLang','function selLang','function switchLang','function setUL','UL=','let UL','var UL','dLang=','let dLang','navigator.language','localStorage.getItem(\'ch_lang\')','ch_lang'] as $k){
  $p=strpos($idx,$k); echo "  '$k' -> ".($p===false?'yok':"VAR @".$p)."\n";
}
R($idx,'function setFlag',0,400,'setFlag()');
R($idx,'function detL',0,400,'detL() dil algılama');
R($idx,'navigator.language',-150,300,'navigator.language (auto-detect var mı)');

echo "\n### UL ilk değer (varsayılan dil nasıl seçiliyor) ###\n";
R($idx,"UL=",-80,200,'UL ataması');
R($idx,"dLang",-60,200,'dLang ataması');

echo "\n### Konto paneli (dil ayarı eklemek için) ###\n";
R($idx,'k-plan-selector',-200,400,'Konto plan/ayar bölümü');
R($idx,'renderKontoPlanSelector',0,300,'renderKontoPlanSelector');

echo "\n=== BİTTİ. SİL: rm dump-lang.php ===\n";
