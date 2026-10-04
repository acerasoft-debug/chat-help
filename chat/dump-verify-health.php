<?php
/** ChatHelp — dump-verify-health (SADECE OKUR)
 *  Saglik denetimindeki 2 uyariyi dogrular: (a) script/body dengesizligi
 *  gercek mi yoksa kacisli-kapanis string'i mi, (b) CH_TR_MODULE x14 mukerrer
 *  mi, (c) MUKERRER ID var mi (asil kirilma sebebi), (d) mukerrer fonksiyon.
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=@file_get_contents(__DIR__.'/index.php');
if($idx===false) exit("index.php okunamadi\n");

/* ── a) script mismatch acikla ── */
echo "###### a) <script> DENGESIZLIGI ACIKLAMA ######\n";
echo "  <script  : ".substr_count($idx,'<script')."\n";
echo "  </script>: ".substr_count($idx,'</script>')."\n";
echo "  <\\/script> (kacisli, string ici): ".substr_count($idx,'<\\/script>')."\n";
echo "  '+'ipt (bolunmus): ".substr_count($idx,"'ipt")."\n";
$net = substr_count($idx,'<script') - substr_count($idx,'</script>') - substr_count($idx,'<\\/script>');
echo "  NET (gercek acik-kapali fark, kacislilar dusuldu): $net  ".($net===0?"✓ tamam (yanlis-pozitif)":"⚠ INCELE")."\n\n";

/* ── b) </body> acikla ── */
echo "###### b) </body> ACIKLAMA ######\n";
echo "  </body>: ".substr_count($idx,'</body>')."\n";
echo "  </body></html> (string ici print-HTML): ".substr_count($idx,'</body></html>')."\n";
$netB = substr_count($idx,'</body>') - substr_count($idx,'</body></html>');
echo "  NET (print-HTML disinda): $netB  ".($netB===1?"✓ tek gercek body (dogru)":($netB<=2?"~ kabul edilebilir":"⚠ INCELE"))."\n\n";

/* ── c) MUKERRER ID (asil bug sebebi) ── */
echo "###### c) MUKERRER ID KONTROLU ######\n";
$ids=['sb','pnl-chat','wlc','mi','pnl-trvisa','pnl-vst','pnl-vsf','pnl-vdos','pnl-cv','pnl-konto','pnl-ant','pnl-jobs','pnl-hub','pnl-fris',
      'nav-jobs','nav-cv','nav-vst','nav-hub','nav-verlauf','nav-trvisa','nav-fris','tb-auth-btn','ch-actprof','msgs','inp','sbtn'];
$dupFound=0;
foreach($ids as $id){
    $n=substr_count($idx,'id="'.$id.'"');
    if($n>1){ echo "  ⚠⚠ MUKERRER: id=\"$id\" x$n\n"; $dupFound++; }
    elseif($n===1){ /* ok, sessiz */ }
    else { echo "  – id=\"$id\" yok\n"; }
}
echo $dupFound? "\n  ⚠ $dupFound mukerrer ID bulundu — KIRILMA RISKI!\n" : "\n  ✓ Kritik ID'lerde mukerrer YOK.\n";

/* ── d) MUKERRER FONKSIYON ── */
echo "\n###### d) MUKERRER FONKSIYON ######\n";
foreach(['function gP(','function showResult(','function applyCC(','function setCountry(','function genDoc(','function boot('] as $fn){
    $n=substr_count($idx,$fn);
    echo "  ".str_pad($fn,26)." x$n ".($n>1?"⚠ (birden fazla tanim — sonuncusu gecerli, sorun olabilir)":"✓")."\n";
}

/* ── e) CH_TR_MODULE x14 nedir ── */
echo "\n###### e) CH_TR_MODULE (x14) BAGLAMLARI ######\n";
$off=0;$i=0;
while(($p=strpos($idx,'CH_TR_MODULE',$off))!==false && $i<16){
    echo "  [$i] ...".substr($idx,max(0,$p-25),70)."...\n";
    $off=$p+12;$i++;
}

echo "\n=== BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-verify-health.php ===\n";
