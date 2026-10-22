<?php
/**
 * ChatHelp — dump-verifyresume — CH_RESUMEGEN iki degisikligi CANLI kodda dogrular (OKUR):
 *  [1] genDoc basi profil-once kontrolu  [2] showProfileForm kaydet -> genDoc resume.
 * KULLANIM: pull2.php?key=...&files=dump-verifyresume.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-verifyresume ==\n\n";

echo "marker CH_RESUMEGEN: ".(substr_count($src,'CH_RESUMEGEN'))." kez\n\n";

echo "=== [1] genDoc basi (profil-once) ===\n";
$p=strpos($src,'window._lastGenAns={dk:dk,ans:Object.assign({},ans)};');
if($p!==false) echo substr($src,$p,320)."\n\n"; else echo "  (anchor yok!)\n\n";

echo "=== [2] showProfileForm kaydet (resume) ===\n";
$p=strpos($src,"const dk=afterDk||CD;");
if($p!==false) echo "...".substr($src,max(0,$p-30),430)."...\n\n"; else echo "  (anchor yok!)\n\n";

echo "=== [3] genDoc async + free gate hala yerinde mi ===\n";
foreach(['async function genDoc','CH_FREE1IP','function showProfileForm','function genDoc'] as $pat){
  echo "  '$pat': ".(strpos($src,$pat)!==false?'VAR ✓':'YOK ✗')."\n";
}

/* lint canli index.php */
echo "\n=== [4] canli index.php lint ===\n";
$tmp=tempnam(sys_get_temp_dir(),'vr').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
echo "  ".($rc===0?'✓ PHP sozdizimi temiz':'✗ HATA: '.implode(' ',$lo))."\n";

echo "\n== BITTI ==\n";
