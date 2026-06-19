<?php
/**
 * ChatHelp — dump12 (SADECE OKUR): Verträge neden görünmüyor?
 * - Patcher'lar çalıştı mı (marker)?
 * - CATS.vertraege literal'de var mı?
 * - CATS/DOCS bir IIFE içinde mi (dışarıdan erişilemez mi)?
 * KULLANIM: curl -s -A "Mozilla/5.0" "https://chat-help.com/chat/dump12.php"
 */
header('Content-Type: text/plain; charset=UTF-8');
$idx = @file_get_contents(__DIR__ . '/index.php');
$api = @file_get_contents(__DIR__ . '/api.php');
if ($idx === false) exit("index.php yok\n");
function has($s,$n){ return $s!==false && strpos($s,$n)!==false; }

echo "### MARKER (patcher çalıştı mı?) ###\n";
foreach(['ch-vertraege','ch-vertraege2','ch-vtr-home','ch-cat-toggle'] as $m){
  echo (has($idx,$m)?"  ✓ ":"  ✗ ").$m."\n";
}
echo "  api.php vtr_ backend: ".(has($api,"strpos(\$docType, 'vtr_')")||has($api,"strpos(\$docType,'vtr_')")?"✓":"✗")."\n";

echo "\n### CATS.vertraege literal'de var mı? ###\n";
echo "  'vertraege:{' -> ".(has($idx,'vertraege:{')?"VAR":"yok")."\n";
echo "  'vertraege :' -> ".(has($idx,'vertraege:')?"VAR":"yok")."\n";

echo "\n### SCOPE: const CATS / const DOCS bağlamı ###\n";
$pc=strpos($idx,'const CATS=');
if($pc!==false){ echo "--- 300 char ÖNCE const CATS ---\n".substr($idx,max(0,$pc-300),320)."\n"; }
$pd=strpos($idx,'const DOCS=');
if($pd!==false){ echo "\n--- 200 char ÖNCE const DOCS ---\n".substr($idx,max(0,$pd-200),230)."\n"; }

echo "\n### window.CATS / global ipuçları ###\n";
foreach(['window.CATS','window.DOCS','window.qS','function qS','window.showCat','self.CATS'] as $k){
  echo "  '$k' -> ".(has($idx,$k)?"VAR":"yok")."\n";
}

echo "\n### const DOCS= başlangıcı (anchor için) ###\n";
if($pd!==false) echo substr($idx,$pd,170)."\n";

echo "\n### const CATS= başlangıcı (anchor için) ###\n";
if($pc!==false) echo substr($idx,$pc,170)."\n";

echo "\n=== BİTTİ. SİL: rm dump12.php ===\n";
