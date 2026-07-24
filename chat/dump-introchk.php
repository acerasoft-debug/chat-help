<?php
/* ChatHelp - dump-introchk (READ-ONLY) - cift intro riski var mi? */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; return; }
echo "== dump-introchk (".number_format(strlen($src))." B) ==\n\n";
echo "CH_INTRO_ML (yeni)      : ".(strpos($src,'CH_INTRO_ML')!==false?"VAR":"yok")."\n";
echo "ch_intro_ml_v1 anahtar  : ".substr_count($src,'ch_intro_ml_v1')." kez\n";
echo "ch_intro_story_v1 (eski): ".substr_count($src,'ch_intro_story_v1')." kez\n";
echo "chiov overlay (eski id) : ".substr_count($src,"id='chiov'")." + ".substr_count($src,"ov.id='chiov'")." (create)\n";
echo "chiov2 overlay (yeni id): ".substr_count($src,"ov.id='chiov2'")." (create)\n";
/* eski CH_INTRO yorum marker'i (apply-intro imzasi) */
echo "eski 'CH_INTRO — ChatHelp giris' imzasi: ".(strpos($src,"CH_INTRO — ChatHelp giris")!==false?"VAR (eski blok duruyor!)":"yok (temiz)")."\n";
echo "\nSONUC: ";
$hasOld = strpos($src,"CH_INTRO — ChatHelp giris")!==false;
echo $hasOld ? "DIKKAT — eski blok hala var, cift intro olabilir.\n" : "Temiz — sadece yeni cok-dilli intro oynar.\n";
echo "\n== BITTI ==\n";
