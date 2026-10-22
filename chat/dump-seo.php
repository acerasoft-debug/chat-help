<?php
/** ChatHelp — dump-seo (SADECE OKUR) — mevcut SEO/head: title, meta description,
 *  og:, twitter:, canonical, lang, JSON-LD, hreflang + robots.txt/sitemap.xml var mi.
 *  TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
echo "════ [A] index.php <head> (ilk 3500 bayt) ════\n";
$hp=stripos($idx,'</head>');
echo str_replace("\n","⏎",substr($idx,0,($hp!==false?min($hp+8,4000):3500)))."\n[/head-end @".$hp."]\n";

echo "\n════ [B] SEO etiket sayaci ════\n";
foreach([
  'meta name="description"'=>'description',
  'meta name="keywords"'=>'keywords',
  'og:title'=>'og:title','og:description'=>'og:description','og:image'=>'og:image','og:url'=>'og:url','og:type'=>'og:type',
  'twitter:card'=>'twitter:card',
  'rel="canonical"'=>'canonical',
  'application/ld+json'=>'JSON-LD',
  'hreflang'=>'hreflang',
  'name="robots"'=>'robots meta',
  '<title'=>'title etiketi',
  '<h1'=>'h1',
] as $needle=>$lbl){
  $c=substr_count($idx,$needle);
  echo "  ".($c?"✓":"✗")." $lbl: $c\n";
}

echo "\n════ [C] title + description icerigi ════\n";
if(preg_match('~<title[^>]*>(.*?)</title>~is',$idx,$m)) echo "  <title>: ".trim($m[1])."\n"; else echo "  <title>: (YOK)\n";
if(preg_match('~<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']~is',$idx,$m)) echo "  description: ".trim($m[1])."\n"; else echo "  description: (YOK)\n";
if(preg_match('~<html[^>]*>~i',$idx,$m)) echo "  <html>: ".trim($m[0])."\n";

echo "\n════ [D] robots.txt / sitemap.xml / .htaccess ════\n";
foreach(['robots.txt','sitemap.xml','.htaccess'] as $f){
  $p=dirname(__DIR__).'/'.$f; $p2=__DIR__.'/'.$f;
  echo "  $f (kök): ".(is_file($p)?"VAR (".filesize($p)." b)":"YOK")." | (chat/): ".(is_file($p2)?"VAR (".filesize($p2)." b)":"YOK")."\n";
}

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
