<?php
/**
 * ChatHelp — dump-wpstats4 — v4: hosting config parse ETMEK yerine include edilir
 *  (format bagimsiz) -> DB_NAME/USER/PASSWORD/HOST sabitleri hazir olur -> DB'de
 *  'Vorabkosten / Verifizierte Käufer' aranir (OKUR; kimlik bilgisi BASILMAZ).
 *  Include basarisizsa config'in define satirlari MASKELI gosterilir.
 * KULLANIM: pull2.php?key=...&files=dump-wpstats4.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(0); ini_set('display_errors','0');
set_time_limit(60);
register_shutdown_function(function(){
  $e=error_get_last();
  if($e && in_array($e['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR]))
    echo "\n!! FATAL: {$e['message']} @ {$e['file']}:{$e['line']}\n";
});
echo "== dump-wpstats4 ==\n\n";
try{
  mysqli_report(MYSQLI_REPORT_OFF);
  $webroot=dirname(__DIR__);
  $cf=dirname($webroot).'/configs/wp-config-hosting.php';
  if(!is_file($cf)) $cf=$webroot.'/../configs/wp-config-hosting.php';
  if(!is_file($cf)) exit("hosting config yok\n");

  /* dogrudan include — defines yuklensin (WP fonksiyonu cagirmadigi surece guvenli) */
  $inc_ok=false;
  try{ @include_once $cf; $inc_ok=true; }catch(Throwable $t){ echo "include hatasi: ".$t->getMessage()."\n"; }

  if(!defined('DB_NAME')||!defined('DB_USER')||!defined('DB_HOST')){
    echo "include sonrasi sabitler eksik — config yapisi (MASKELI):\n";
    $cfg=(string)@file_get_contents($cf);
    foreach(preg_split('/\r?\n/',$cfg) as $i=>$L){
      $t=trim($L); if($t==='') continue;
      if(stripos($t,'define')!==false||stripos($t,'DB_')!==false||stripos($t,'table_prefix')!==false||stripos($t,'getenv')!==false){
        /* deger benzeri her seyi maskele */
        $m=preg_replace("/(['\"])((?:(?!\\1).){6,})\\1/","'***'",$t);
        echo "  ".($i+1).": ".substr($m,0,110)."\n";
      }
    }
    exit("\n== BITTI ==\n");
  }
  echo "sabitler: DB_NAME ✓  DB_USER ✓  DB_HOST ✓  pass ".(defined('DB_PASSWORD')?'✓':'✗')."\n";
  $px='wp_3c8006471f_';   /* onceki dumptan */
  if(isset($table_prefix)&&$table_prefix) $px=$table_prefix;
  echo "prefix: $px\n\n";

  $my=@mysqli_connect(DB_HOST,DB_USER,defined('DB_PASSWORD')?DB_PASSWORD:'',DB_NAME);
  if(!$my) exit("DB baglanti hatasi: ".@mysqli_connect_error()."\n");
  @mysqli_set_charset($my,'utf8mb4');
  echo "DB baglandi ✓\n\n";
  $q=function($sql)use($my){ $r=@mysqli_query($my,$sql); if(!$r) echo "  (sorgu hatasi: ".@mysqli_error($my).")\n"; return $r; };

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

  echo "\n=== [2] {$px}postmeta (Elementor vb.) ===\n";
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
}catch(Throwable $t){ echo "\n!! EXCEPTION: ".$t->getMessage()."\n"; }
echo "\n== BITTI ==\n";
