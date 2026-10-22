<?php
/**
 * ChatHelp — dump-apimodel (SADECE OKUR) — api.php'nin Anthropic'e gonderdigi
 *  gercek MODEL string'ini bul (admin test butonu bununla eslesmeli). Ayrica
 *  fall-api.php / stripe vs. varsa modelleri de gosterir. Anahtar DEGERI YOK.
 * KULLANIM: pull2.php?key=...&files=dump-apimodel.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
echo "=== dump-apimodel — SADECE OKUR ===\n\n";
foreach(['api.php','fall-api.php','stripe-checkout.php','chat.php'] as $f){
  $c=(string)@file_get_contents("$D/$f");
  if($c===''){ echo "[$f] yok/bos\n\n"; continue; }
  echo "[$f] (".strlen($c)." bayt) — 'model' geçen satirlar:\n";
  $lines=preg_split('/\n/',$c); $shown=0;
  foreach($lines as $i=>$ln){
    if($shown>=12) break;
    if(preg_match('/[\'"]model[\'"]\s*[=:]|claude-[a-z0-9.\-]+|sonnet|opus|haiku|deepseek-|CLAUDE_MODEL|anthropic-version/i',$ln)){
      $t=trim($ln); if(strlen($t)>200)$t=substr($t,0,200).'…';
      echo "  L".($i+1).": ".$t."\n"; $shown++;
    }
  }
  if($shown===0) echo "  (model referansi yok)\n";
  echo "\n";
}
echo "=== BITTI (salt-okunur) ===\n";
