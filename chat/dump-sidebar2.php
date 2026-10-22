<?php
/** ChatHelp — dump-sidebar2 (SADECE OKUR)
 *  KESIN yama icin son eksik veriler:
 *   1) Her nav olusturma yerinden SONRA gelen appendChild HEDEFI (hangi
 *      kapsayici degiskeni) + o cagrinin tam metni
 *   2) Sidebar kapsayici degiskenlerinin TANIMI (getElementById/querySelector)
 *   3) Modul paketi navlarinin GERCEK id'leri (fris/rech/tresor/briefe)
 *   4) menufix2'nin AKTIF DOM-tasima gozcusu (CH_MENUFIX2) tam blogu
 *   5) nav yeniden-olusturma korumasi var mi (gP icinde tekrar createElement?)
 *  Ciktinin TAMAMINI gonder. SIL: rm dump-sidebar2.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
@set_time_limit(60);
$idx = @file_get_contents(__DIR__.'/index.php');
if ($idx===false) exit("index.php okunamadi\n");
echo "════════ SIDEBAR TESHIS-2 ".date('Y-m-d H:i')." ════════\n\n";

/* ── 1) her nav createElement sonrasi ilk appendChild(nav) hedefi ── */
echo "###### 1) nav OLUSTURMA -> appendChild HEDEFI ######\n";
$navCreators=["nav-verlauf","nav-jobs","nav-cv","nav-vst","nav-trvisa","nav-hub"];
foreach($navCreators as $id){
  $p=strpos($idx,'nav.id="'.$id.'"');
  if($p===false){ echo "  – $id: createElement bulunamadi\n"; continue; }
  /* bu noktadan sonra 900 bayt icinde appendChild(nav) veya .appendChild( ara */
  $seg=substr($idx,$p,900);
  if(preg_match('/(\w[\w.$\[\]\'"()]*?)\.appendChild\(\s*nav\s*\)/',$seg,$m)){
    echo "  $id -> ".$m[1].".appendChild(nav)\n";
  } elseif(preg_match('/(\w[\w.$\[\]\'"()]*?)\.(insertBefore|append)\(\s*nav/',$seg,$m)){
    echo "  $id -> ".$m[1].".".$m[2]."(nav...)\n";
  } else {
    /* daha genis ara */
    $seg2=substr($idx,$p,2000);
    if(preg_match('/(\w[\w.$\[\]\'"()]*?)\.appendChild\(\s*nav\s*\)/',$seg2,$m2))
      echo "  $id -> (uzak) ".$m2[1].".appendChild(nav)\n";
    else echo "  $id -> appendChild(nav) YAKINDA YOK (baska sekilde ekleniyor)\n";
  }
}

/* ── 2) sidebar kapsayici degisken tanimlari ── */
echo "\n###### 2) KAPSAYICI DEGISKEN TANIMLARI ######\n";
$reDefs=[
  'getElementById("sb")'       => '/(\w+)\s*=\s*document\.getElementById\(["\']sb["\']\)/',
  'querySelector .sb'          => '/(\w+)\s*=\s*document\.querySelector\(["\']\.?sb[^"\']*["\']\)/',
  '.sb-nav / sb-nav-wrap'      => '/getElementById\(["\'](sb[\w-]*)["\']\)/',
];
foreach($reDefs as $lbl=>$re){
  $n=preg_match_all($re,$idx,$mm);
  echo "  ".str_pad($lbl,26)." x".$n;
  if($n){ $u=array_unique($mm[1]); echo "  -> ".implode(', ',array_slice($u,0,8)); }
  echo "\n";
}
/* sidebar acan HTML: id="sb" nin etiketi + class + hemen ic yapisi */
$p=strpos($idx,'id="sb"');
if($p!==false){
  $seg=substr($idx,max(0,$p-80),260); $seg=preg_replace('/\s+/',' ',$seg);
  echo "  id=\"sb\" baglami: ...".$seg."...\n";
}

/* ── 3) modul paketi navlarinin gercek id'leri ── */
echo "\n###### 3) MODUL PAKETI NAV ID'LERI (fris/rech/tresor/briefe) ######\n";
foreach(['fris','rech','tresor','briefe','frist','rechner','tresor','brief','hub'] as $kw){
  $off=0;$hit=0;
  while(($q=strpos($idx,'nav.id="nav-'.$kw,$off))!==false && $hit<2){
    $seg=substr($idx,$q,60); echo "  createElement id: ".preg_replace('/\s+/',' ',$seg)."...\n"; $hit++; $off=$q+5;
  }
}
/* gP('...') cagrilari — tum modul anahtarlari */
echo "  -- gP('X') modul anahtarlari --\n";
if(preg_match_all("/gP\('([a-z]+)'\)/",$idx,$mm)){
  $u=array_count_values($mm[1]); ksort($u);
  foreach($u as $k=>$v) echo "    ".str_pad($k,12)." x".$v."\n";
}
/* setAttribute onclick gP ile eklenen tum nav idleri */
echo "  -- nav.id= ile atanan TUM idler --\n";
if(preg_match_all('/nav\.id\s*=\s*["\']([\w-]+)["\']/',$idx,$mm)){
  echo "    ".implode(', ',array_unique($mm[1]))."\n";
}

/* ── 4) menufix2 aktif gozcu blogu ── */
echo "\n###### 4) CH_MENUFIX2 BLOGU (aktif DOM-tasima gozcusu?) ######\n";
$p=strpos($idx,'CH_MENUFIX2');
if($p!==false){
  /* iceren <script id="..."> blogunu bul */
  $ss=strrpos(substr($idx,0,$p),'<script');
  $se=strpos($idx,'</script>',$p);
  if($ss!==false&&$se!==false){
    $blk=substr($idx,$ss,min($se-$ss+9, 1800));
    echo $blk."\n";
    if(strlen($se-$ss)>1800) echo "  ...(kirpildi)\n";
  }
} else echo "  CH_MENUFIX2 yok\n";

/* ── 5) nav yeniden-olusturma korumasi ── */
echo "\n###### 5) NAV TEKRAR-OLUSTURMA KORUMASI ######\n";
foreach($navCreators as $id){
  $n=substr_count($idx,'nav.id="'.$id.'"');
  /* olusturmadan hemen once getElementById(id) guard var mi */
  $p=strpos($idx,'nav.id="'.$id.'"');
  $guard='?';
  if($p!==false){
    $before=substr($idx,max(0,$p-260),260);
    $guard = (strpos($before,'getElementById("'.$id.'")')!==false || strpos($before,'getElementById(\''.$id.'\')')!==false) ? 'VAR (guard)':'YOK';
  }
  echo "  ".str_pad($id,14)." createElement x".$n." · varlik-guard: ".$guard."\n";
}

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-sidebar2.php ════════\n";
