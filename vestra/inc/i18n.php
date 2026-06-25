<?php
/**
 * VESTRA — lightweight i18n.
 * Language is chosen via ?lang=xx and remembered in the `vlang` cookie.
 * t("English string") returns the translation for the active language,
 * falling back to the English string itself when no translation exists.
 * Dictionaries live in inc/lang/{de,fr,it,es}.php  (English = the keys).
 */
if(!function_exists('vlang')){

function vlang_list(){ return ['en'=>'EN','de'=>'DE','fr'=>'FR','it'=>'IT','es'=>'ES']; }

function vlang(){
  static $l=null; if($l!==null) return $l;
  $langs=vlang_list(); $l='en';
  if(isset($_GET['lang']) && isset($langs[$_GET['lang']])){
    $l=$_GET['lang'];
    if(!headers_sent()) @setcookie('vlang',$l,time()+31536000,'/');
    $_COOKIE['vlang']=$l;
  } elseif(isset($_COOKIE['vlang']) && isset($langs[$_COOKIE['vlang']])){
    $l=$_COOKIE['vlang'];
  }
  return $l;
}

function vtrans(){
  static $d=null; if($d!==null) return $d;
  $d=[]; $l=vlang();
  if($l!=='en'){ $f=__DIR__.'/lang/'.$l.'.php'; if(is_readable($f)){ $x=require $f; if(is_array($x)) $d=$x; } }
  return $d;
}

/* translate (with English fallback) */
function t($s){ $d=vtrans(); return isset($d[$s]) ? $d[$s] : $s; }

/* language switcher markup; links carry ?lang= to set the cookie */
function vlang_switcher($class='langsw'){
  $cur=vlang(); $out='<div class="'.$class.'">';
  foreach(vlang_list() as $code=>$label){
    $out.='<a class="lsw'.($code===$cur?' on':'').'" href="?lang='.$code.'" hreflang="'.$code.'">'.$label.'</a>';
  }
  return $out.'</div>';
}

vlang(); // resolve language + set cookie before any output
}
