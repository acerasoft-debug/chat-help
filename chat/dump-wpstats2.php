<?php
/**
 * ChatHelp — dump-wpstats2 — v2: mysqli exception modu KAPALI + fatal yakalayici.
 *  WP veritabaninda 'Vorabkosten / Verifizierte Käufer' istatistigini arar (OKUR).
 * KULLANIM: pull2.php?key=...&files=dump-wpstats2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(0); ini_set('display_errors','0');
set_time_limit(60);
register_shutdown_function(function(){
  $e=error_get_last();
  if($e && in_array($e['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR]))
    echo "\n!! FATAL: {$e['message']} @ {$e['file']}:{$e['line']}\n";
});
echo "== dump-wpstats2 ==\n\n";

try{
  if(!function_exists('mysqli_connect')) exit("mysqli yok\n");
  mysqli_report(MYSQLI_REPORT_OFF);   /* PHP8.1+: exception modunu kapat */

  $cfg=@file_get_contents(dirname(__DIR__).'/wp-config.php');
  if($cfg===false) exit("wp-config.php okunamadi\n");
  $cc=function($k)use($cfg){ return preg_match("/define\(\s*['\"]".$k."['\"]\s*,\s*['\"]([^'\"]*)['\"]/",$cfg,$m)?$m[1]:''; };
  $db=$cc('DB_NAME'); $us=$cc('DB_USER'); $pw=$cc('DB_PASSWORD'); $ho=$cc('DB_HOST');
  $px='wp_'; if(preg_match('/\$table_prefix\s*=\s*[\'"]([^\'"]+)[\'"]/',$cfg,$m)) $px=$m[1];
  echo "cfg: db=".($db!==''?'✓':'✗')." user=".($us!==''?'✓':'✗')." host=".($ho!==''?$ho:'?')." prefix=$px\n\n";

  $my=@mysqli_connect($ho,$us,$pw,$db);
  if(!$my) exit("DB baglanti hatasi: ".@mysqli_connect_error()."\n");
  @mysqli_set_charset($my,'utf8mb4');
  echo "DB baglandi ✓\n\n";

  $q=function($sql)use($my){ $r=@mysqli_query($my,$sql); if(!$r){ echo "  (sorgu hatasi: ".@mysqli_error($my).")\n"; } return $r; };

  echo "=== [1] {$px}posts.post_content ===\n";
  $r=$q("SELECT ID,post_title,post_type,post_status,post_content FROM {$px}posts WHERE post_content LIKE '%Vorabkosten%' OR post_content LIKE '%Verifizierte K%' LIMIT 8");
  $n=0;
  while($r && ($row=@mysqli_fetch_assoc($r))){
    $n++; $c=(string)$row['post_content'];
    $p=stripos($c,'Vorabkosten'); if($p===false)$p=stripos($c,'Verifizierte');
    echo "  ✓ ID={$row['ID']} [{$row['post_type']}/{$row['post_status']}] \"{$row['post_title']}\"\n";
    echo "    ...".str_replace(["\n","\r"],' ',substr($c,max(0,(int)$p-350),800))."...\n\n";
  }
  if(!$n) echo "  (yok)\n";

  echo "\n=== [2] {$px}postmeta ===\n";
  $n2=0;
  foreach(["meta_value LIKE '%Vorabkosten%'","meta_value LIKE '%Verifizierte K%'","meta_value LIKE '%u00e4ufer%'"] as $w){
    $r=$q("SELECT post_id,meta_key,meta_value FROM {$px}postmeta WHERE $w LIMIT 4");
    while($r && ($row=@mysqli_fetch_assoc($r))){
      $n2++; $c=(string)$row['meta_value'];
      $p=stripos($c,'Vorabkosten'); if($p===false)$p=stripos($c,'ufer');
      echo "  ✓ post_id={$row['post_id']} meta_key={$row['meta_key']} (".number_format(strlen($c))." B)\n";
      echo "    ...".str_replace(["\n","\r"],' ',substr($c,max(0,(int)$p-350),800))."...\n\n";
      if($n2>=6) break 2;
    }
  }
  if(!$n2) echo "  (yok)\n";

  echo "\n=== [3] {$px}options ===\n";
  $r=$q("SELECT option_name,LENGTH(option_value) L FROM {$px}options WHERE option_value LIKE '%Vorabkosten%' LIMIT 5");
  $n3=0;
  while($r && ($row=@mysqli_fetch_assoc($r))){ $n3++; echo "  ✓ {$row['option_name']} ({$row['L']} B)\n"; }
  if(!$n3) echo "  (yok)\n";

  @mysqli_close($my);
}catch(Throwable $t){
  echo "\n!! EXCEPTION: ".$t->getMessage()." @ ".$t->getFile().":".$t->getLine()."\n";
}
echo "\n== BITTI ==\n";
