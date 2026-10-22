<?php
/* dump-menuicon (READ-ONLY) — sol menu (sidebar) ACMA butonu + ikonunu bulur:
   element markup, ikon icerigi (emoji/SVG/metin), onclick, CSS.
   KULLANIM: pull2.php?key=...&files=dump-menuicon.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "dump-menuicon (READ-ONLY)\n\n";
$s=@file_get_contents(__DIR__.'/index.php');
if($s===false) exit("okunamadi\n");

/* sidebar acma butonu adaylari */
echo "=== sidebar toggle buton adaylari (onclick) ===\n";
if(preg_match_all('/<(button|div|a|span)[^>]*onclick="[^"]*(oSB|openSidebar|toggleSb|toggleSidebar|NC)\(\)[^"]*"[^>]*>.*?<\/\1>/is',$s,$m,PREG_SET_ORDER)){
  $i=0;
  foreach($m as $x){ if($i++>=6) break;
    $seg=preg_replace('/\s+/',' ',$x[0]);
    if(strlen($seg)>420) $seg=substr($seg,0,420).'…';
    echo "  ".$seg."\n\n";
  }
} else echo "  (onclick tabanli bulunamadi)\n";

/* class tabanli aday: tb-burger / tb-menu / sb-open / menu-btn */
echo "=== class tabanli aday (tb-burger/tb-menu/menu-btn/sb-open) ===\n";
if(preg_match_all('/<(button|div|a|span)[^>]*class="[^"]*(tb-burger|tb-menu|menu-btn|sb-open|sb-toggle|burger)[^"]*"[^>]*>.*?<\/\1>/is',$s,$m,PREG_SET_ORDER)){
  $i=0; foreach($m as $x){ if($i++>=6) break;
    $seg=preg_replace('/\s+/',' ',$x[0]); if(strlen($seg)>420)$seg=substr($seg,0,420).'…';
    echo "  ".$seg."\n\n"; }
} else echo "  (class tabanli bulunamadi)\n";

/* olasi emoji/simge ikonlari */
echo "=== menu simge adaylari sayimi ===\n";
foreach(['☰','≡','&#9776;','⌘','menü','Menü','<svg'] as $k){
  echo "  '".$k."': ".substr_count($s,$k)."\n";
}

/* topbar sol taraf ilk 500 karakter (genelde burada) */
$p=stripos($s,'tb-burger'); if($p===false)$p=stripos($s,'openSidebar'); if($p===false)$p=stripos($s,'oSB(');
if($p!==false){ echo "\n=== ilk toggle referansi civari (±260) ===\n";
  echo "  …".preg_replace('/\s+/',' ',substr($s,max(0,$p-260),520))."…\n"; }
echo "\nBITTI.\n";
