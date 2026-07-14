<?php
/** ChatHelp — dump-health (SADECE OKUR) — bu oturumdaki tum degisikliklerin
 *  saglik kontrolu. Tum CH_* isaretlerini index/api/auth'ta sayar; cift-uygulama
 *  (count>1), eksik marker, dosya butunlugu (</body></html>), <script> dengesi,
 *  ve olasi cakismalari raporlar.
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$files=['index.php','api.php','auth.php','admin-users.php'];

foreach($files as $f){
  $p="$D/$f"; $s=(string)@file_get_contents($p);
  echo "════════ $f ".(is_file($p)?"(".strlen($s)." bayt)":"(YOK)")." ════════\n";
  if($s===''){ echo "  (okunamadi)\n\n"; continue; }
  /* CH_* markerlari */
  if(preg_match_all('/CH_[A-Z0-9_]+/',$s,$m)){
    $cnt=array_count_values($m[0]);
    ksort($cnt);
    echo "  CH_ markerlari (".count($cnt)." tur):\n";
    foreach($cnt as $k=>$v){
      $flag = ($v>2) ? "  ⚠ COK FAZLA (cift-uygulama?)" : "";
      echo "    $k = $v".$flag."\n";
    }
  } else echo "  (CH_ marker yok)\n";
  /* yapisal */
  echo "  <script> acilis: ".substr_count($s,'<script').", </script>: ".substr_count($s,'</scr'.'ipt>')."\n";
  echo "  <style> acilis: ".substr_count($s,'<style').", </style>: ".substr_count($s,'</style>')."\n";
  if($f==='index.php'){
    echo "  </body>: ".substr_count($s,'</body>').", </html>: ".substr_count($s,'</html>')."\n";
    echo "  son 60 bayt: ".str_replace("\n","⏎",substr($s,-60))."\n";
    /* onemli fonksiyonlar duruyor mu */
    foreach(['function makePDF','window.chDlGate','function gP(','function startVoice','window.chPrintDoc','window.chVizeAsistan'] as $fn){
      echo "  '$fn': ".(strpos($s,$fn)!==false?"VAR":"⚠ YOK")."\n";
    }
  }
  if($f==='api.php'){
    foreach(['function doAiChat','function doGenerate','function doPhoto','function ch_mem_append','=> doAiChat','=> doPhoto'] as $fn){
      echo "  '$fn': ".(strpos($s,$fn)!==false?"VAR":"⚠ YOK")."\n";
    }
  }
  if($f==='auth.php'){
    foreach(['function doLogin','function doRegister','function doBillingPortal','function doEmailDoc','function sendMail','=> doBillingPortal','=> doEmailDoc'] as $fn){
      echo "  '$fn': ".(strpos($s,$fn)!==false?"VAR":"⚠ YOK")."\n";
    }
  }
  echo "\n";
}

/* apply/dump betikleri (sunucuda kalinti kalmis mi) */
echo "════════ sunucudaki apply-/dump- betikleri ════════\n";
$left=[];
foreach(glob("$D/apply-*.php") as $g) $left[]=basename($g);
foreach(glob("$D/dump-*.php") as $g) $left[]=basename($g);
echo count($left)? "  ⚠ kalinti: ".implode(', ',$left)."\n" : "  ✓ temiz (apply/dump betigi yok)\n";

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
