<?php
/**
 * ChatHelp — dump-wpstats — '500+ Verifizierte Käufer / Vorabkosten' istatistigi
 *  WordPress VERITABANINDA ara (OKUR): wp_posts.post_content + wp_postmeta
 *  (Elementor _elementor_data) + wp_options. Kimlik bilgisi EKRANA BASILMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-wpstats.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
set_time_limit(90);
echo "== dump-wpstats ==\n\n";

$cfg=@file_get_contents(dirname(__DIR__).'/wp-config.php');
if($cfg===false) exit("wp-config.php okunamadi\n");
function cc($cfg,$k){ return preg_match("/define\(\s*['\"]".$k."['\"]\s*,\s*['\"]([^'\"]*)['\"]/",$cfg,$m)?$m[1]:''; }
$db=cc($cfg,'DB_NAME'); $us=cc($cfg,'DB_USER'); $pw=cc($cfg,'DB_PASSWORD'); $ho=cc($cfg,'DB_HOST');
$px='wp_'; if(preg_match('/\$table_prefix\s*=\s*[\'"]([^\'"]+)[\'"]/',$cfg,$m)) $px=$m[1];
echo "DB: ".($db?'✓':'✗')."  host: ".($ho?:'?')."  prefix: $px\n\n";

$my=@mysqli_connect($ho,$us,$pw,$db);
if(!$my) exit("DB baglanti hatasi: ".mysqli_connect_error()."\n");
mysqli_set_charset($my,'utf8mb4');

/* aranan kaliplar: duz + JSON-escaped (Elementor: Käufer) */
$pats=['Vorabkosten','Verifizierte K','K\\\\u00e4ufer','Kufer'];

echo "=== [1] {$px}posts.post_content ===\n";
$r=mysqli_query($my,"SELECT ID,post_title,post_type,post_status,post_content FROM {$px}posts
  WHERE post_status IN('publish','private') AND (post_content LIKE '%Vorabkosten%' OR post_content LIKE '%Verifizierte K%') LIMIT 8");
$n=0;
while($r && ($row=mysqli_fetch_assoc($r))){
  $n++;
  $c=$row['post_content'];
  $p=stripos($c,'Vorabkosten'); if($p===false)$p=stripos($c,'Verifizierte');
  echo "  ✓ ID={$row['ID']} [{$row['post_type']}/{$row['post_status']}] \"{$row['post_title']}\"\n";
  echo "    ...".str_replace(["\n","\r"],' ',substr($c,max(0,$p-350),800))."...\n\n";
}
echo $n?"":"  (yok)\n";

echo "\n=== [2] {$px}postmeta (Elementor vb.) ===\n";
$n2=0;
foreach(["meta_value LIKE '%Vorabkosten%'","meta_value LIKE '%Verifizierte K%'","meta_value LIKE '%u00e4ufer%'"] as $w){
  $r=mysqli_query($my,"SELECT post_id,meta_key,meta_value FROM {$px}postmeta WHERE $w LIMIT 4");
  while($r && ($row=mysqli_fetch_assoc($r))){
    $n2++;
    $c=$row['meta_value'];
    $p=stripos($c,'Vorabkosten'); if($p===false)$p=stripos($c,'ufer');
    echo "  ✓ post_id={$row['post_id']} meta_key={$row['meta_key']} (".number_format(strlen($c))." B)\n";
    echo "    ...".str_replace(["\n","\r"],' ',substr($c,max(0,$p-350),800))."...\n\n";
    if($n2>=6) break 2;
  }
}
echo $n2?"":"  (yok)\n";

echo "\n=== [3] {$px}options ===\n";
$r=mysqli_query($my,"SELECT option_name,LENGTH(option_value) L FROM {$px}options WHERE option_value LIKE '%Vorabkosten%' LIMIT 5");
$n3=0;
while($r && ($row=mysqli_fetch_assoc($r))){ $n3++; echo "  ✓ {$row['option_name']} ({$row['L']} B)\n"; }
echo $n3?"":"  (yok)\n";

mysqli_close($my);
echo "\n== BITTI ==\n";
