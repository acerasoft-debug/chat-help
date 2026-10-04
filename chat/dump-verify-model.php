<?php
/** ChatHelp — dump-verify-model (SADECE OKUR) — model-fix gercekten uygulandi mi.
 *  apply-model-fix'in "✗ DOGRULAMA" mesaji yanlis alarmdi (marker'da 4-6 gecti).
 *  Bu, api.php'de KOD satirlarinda 4-6 kaldi mi / 4-5 var mi kesin gosterir. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$api=(string)@file_get_contents(__DIR__.'/api.php');
echo "api.php: ".strlen($api)." bayt\n\n";
echo "KOD satirlarinda (model=>):\n";
echo "  'model'=>'claude-sonnet-4-6' : ".substr_count($api,"'model'=>'claude-sonnet-4-6'")."  (0 olmali)\n";
echo "  'model'=>'claude-sonnet-4-5' : ".substr_count($api,"'model'=>'claude-sonnet-4-5'")."  (3 olmali)\n";
echo "  'model'=>'deepseek-chat'     : ".substr_count($api,"'model'=>'deepseek-chat'")."\n";
echo "\nMarker/genel:\n";
echo "  CH_MODEL_FIX marker         : ".substr_count($api,'CH_MODEL_FIX')."  (1 olmali)\n";
echo "  toplam 'claude-sonnet-4-6'  : ".substr_count($api,'claude-sonnet-4-6')."  (1 = sadece marker yorumu, normal)\n";
echo "  toplam 'claude-sonnet-4-5'  : ".substr_count($api,'claude-sonnet-4-5')."\n";
$ok = (substr_count($api,"'model'=>'claude-sonnet-4-6'")===0 && substr_count($api,"'model'=>'claude-sonnet-4-5'")>=3);
echo "\n".($ok ? "✅ MODEL DUZELDI — foto artik calismali.\n" : "⚠ Model hala eski — apply-model-fix tekrar gerekli.\n");
echo "\n════ BITTI. Ciktinin TAMAMINI gonder. ════\n";
