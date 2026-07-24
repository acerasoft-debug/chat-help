<?php
/* ChatHelp - dump-uilang (READ-ONLY) - arayuz dili nasil belirleniyor? */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; return; }
echo "== dump-uilang (".number_format(strlen($src))." B) ==\n\n";

echo "=== [1] T() ceviri fonksiyonu tanimi ===\n";
foreach(array('function T(','function T (',' T=function','window.T=') as $pat){
  $q=strpos($src,$pat);
  if($q!==false){ echo "  [".$pat." @".$q."]:\n  ".str_replace("\r",'',substr($src,($q-30>0?$q-30:0),420))."\n\n"; }
}

echo "=== [2] arayuz dili anahtarlari (localStorage/global) ===\n";
foreach(array('ch_uilang','ch_ui_lang','ch_lang','ch_ui','uiLang','UILANG','ui_lang','currentLang','curLang','getUILang','getLang','ch_interface','ch_iface','ch_sprache','activeLang','LANGS') as $pat){
  $off=0;$nn=0;$hit=false;
  while(($q=strpos($src,$pat,$off))!==false && $nn<2){
    $seg=str_replace(array("\r","\n"),array('',' '),substr($src,($q-50>0?$q-50:0),160));
    echo "  [".$pat." @".$q."]: ...".$seg."...\n";
    $off=$q+strlen($pat);$nn++;$hit=true;
  }
  if(!$hit) echo "  (".$pat.": yok)\n";
}

echo "\n=== [3] desteklenen diller listesi ipuclari ===\n";
foreach(array("'de'","'en'","'tr'","'fr'","'ar'","'ru'","'es'","'it'","'pl'") as $pat){
  echo "  ".$pat.": ".substr_count($src,$pat)." kez\n";
}

echo "\n=== [4] dil secici (ana arayuz) ===\n";
foreach(array('setLang','changeLang','switchLang','applyLang','data-lang','langSelect') as $pat){
  $q=strpos($src,$pat);
  echo "  ".$pat.": ".($q!==false?("VAR @".$q):"yok")."\n";
}
echo "\n== BITTI ==\n";
