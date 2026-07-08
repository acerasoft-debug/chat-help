<?php
/** ChatHelp — dump-sidebar (SADECE OKUR)
 *  Sol menu NEDEN oynuyor? Canli index.php'den GERCEK yapiyi cikarir:
 *   A) nav-* elemanlarinin HTML baglamlari (hangi kapsayicida, kardesleri)
 *   B) sidebar'i YENIDEN CIZEN kod var mi (innerHTML= / insertBefore /
 *      appendChild / .sort( / .order) — order'imi silen ana suphe
 *   C) CH_MENUFIX* + enforceOrder durumu
 *   D) order / flex-direction / display:flex iceren CSS kurallari
 *   E) setInterval + MutationObserver sayisi (cakisan gozcu?)
 *  Ciktinin TAMAMINI gonder. SIL: rm dump-sidebar.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
@set_time_limit(60);
$idx = @file_get_contents(__DIR__.'/index.php');
if ($idx===false) exit("index.php okunamadi\n");
echo "════════ SIDEBAR TESHIS ".date('Y-m-d H:i')." · ".number_format(strlen($idx))." bayt ════════\n\n";

/* ── A) her nav-* nerede tanimli, HTML baglami ── */
echo "###### A) nav-* HTML BAGLAMLARI (ilk gecis) ######\n";
$navs=["nav-verlauf","nav-jobs","nav-cv","nav-vst","nav-trvisa","nav-fris","nav-rech","nav-tresor","nav-briefe","nav-hub"];
foreach($navs as $id){
  $p=strpos($idx,'id="'.$id.'"');
  if($p===false){ echo "  – $id: BULUNAMADI\n"; continue; }
  $ctx=substr($idx,max(0,$p-60),150);
  $ctx=preg_replace('/\s+/',' ',$ctx);
  echo "  $id @".$p.":\n    ...".$ctx."...\n";
}

/* ── B) sidebar'i yeniden ciziyor olabilecek kod ── */
echo "\n###### B) YENIDEN-CIZIM / TASIMA SUPHELILERI ######\n";
$patterns=[
  'innerHTML nav ataması'      => '/(\w+)\.innerHTML\s*=/',
  'insertBefore'               => '/insertBefore\s*\(/',
  'appendChild'                => '/appendChild\s*\(/',
  'removeChild'                => '/removeChild\s*\(/',
  '.sort('                     => '/\.sort\s*\(/',
  'style.order'                => '/\.order\s*=/',
  'prepend('                   => '/\.prepend\s*\(/',
  'insertAdjacentHTML'         => '/insertAdjacentHTML/',
];
foreach($patterns as $lbl=>$re){
  $n=preg_match_all($re,$idx,$mm);
  echo "  ".str_pad($lbl,26)." x".$n."\n";
}

/* ── B2) 'sb'/'nav' etrafinda innerHTML/insert baglamlari ── */
echo "\n###### B2) SIDEBAR YAKININDA innerHTML/insert BAGLAMLARI ######\n";
$off=0;$shown=0;
while(($p=strpos($idx,'.innerHTML',$off))!==false && $shown<40){
  $seg=substr($idx,max(0,$p-40),120);
  if(preg_match('/sb|nav|side|menu|cat/i',$seg)){
    $seg=preg_replace('/\s+/',' ',$seg);
    echo "  @".$p.": ...".$seg."...\n"; $shown++;
  }
  $off=$p+10;
}
if(!$shown) echo "  (sidebar yakininda innerHTML ataması bulunmadi)\n";

/* ── C) menufix + enforceOrder durumu ── */
echo "\n###### C) MENUFIX / enforceOrder DURUMU ######\n";
foreach(['CH_MENUFIX3','CH_MENUFIX2','CH_MENUFIX','function enforceOrder(){ return;','function enforceOrder('] as $k){
  echo "  ".str_pad($k,34)." x".substr_count($idx,$k)."\n";
}

/* ── D) order / flex CSS kurallari ── */
echo "\n###### D) CSS: order / flex-direction (sidebar ilgili) ######\n";
$off=0;$shown=0;
while(($p=strpos($idx,'order:',$off))!==false && $shown<30){
  $seg=substr($idx,max(0,$p-60),90);
  $seg=preg_replace('/\s+/',' ',$seg);
  echo "  @".$p.": ...".$seg."...\n"; $shown++; $off=$p+6;
}
if(!$shown) echo "  (statik 'order:' CSS kurali yok — order tamamen JS ile)\n";
echo "  '.sb' display:flex iceren mi? ";
echo (preg_match('/#sb[^{]*\{[^}]*display:\s*flex/i',$idx)||preg_match('/\.sb[^{]*\{[^}]*display:\s*flex/i',$idx))?"EVET\n":"belirsiz\n";

/* ── E) gozcu/interval sayisi ── */
echo "\n###### E) GOZCU / INTERVAL ######\n";
echo "  setInterval        x".substr_count($idx,'setInterval')."\n";
echo "  MutationObserver   x".substr_count($idx,'MutationObserver')."\n";
echo "  requestAnimationFr x".substr_count($idx,'requestAnimationFrame')."\n";

/* ── F) sidebar kapsayicisinin GERCEK id/class'i ── */
echo "\n###### F) nav-verlauf'un GERCEK KAPSAYICISI (geriye dogru en yakin acilis etiketi) ######\n";
$p=strpos($idx,'id="nav-verlauf"');
if($p!==false){
  $before=substr($idx,max(0,$p-400),400);
  /* en son acilan <div ...> ya da <nav ...> bul */
  if(preg_match_all('/<(div|nav|section|aside)([^>]*)>/i',$before,$mm,PREG_OFFSET_CAPTURE)){
    $last=end($mm[0]);
    echo "  nav-verlauf'tan hemen once acilan etiket:\n    <".$last[0]."\n";
    /* birkac onceki de goster */
    $cnt=count($mm[0]);
    for($i=max(0,$cnt-4);$i<$cnt;$i++){
      echo "    [".$i."] <".preg_replace('/\s+/',' ',$mm[0][$i][0])."\n";
    }
  }
} else echo "  nav-verlauf yok\n";

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-sidebar.php ════════\n";
