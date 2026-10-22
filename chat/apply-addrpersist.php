<?php
/**
 * ChatHelp — apply-addrpersist — KESIN: adres surekli soruluyor / kaydolmuyor.
 *  KOK NEDEN: coklu-profil senkron dongusu (her 1,5 sn) window.P ANLIK BOSKEN
 *  (hesap-baglama/yukleme sirasinda) bos goruntuyu aktif profile yaziyor ve
 *  kayitli adresi SILIYOR. Adres kaybolunca form tekrar soruyor.
 *  3 katmanli cozum:
 *   [1] CH_ADDR_SYNCGUARD: BOS goruntu, DOLU bir profili ASLA ezmez.
 *   [2] CH_ADDR_SAVE2    : profil kaydet butonu window.P'yi + aktif profil slotunu
 *       da senkron yazar (aninda tutarli, dongu geri almaz).
 *   [3] CH_ADDR_HYDRATE  : sayfa acilisinda window.P bossa ch_prof_v3'ten geri
 *       doldurur (boot ve dongu oncesi).
 *  Her bolum bagimsiz sayac-korumali; eslesmeyen atlanir ve raporlanir.
 * KULLANIM: pull2.php?key=...&files=apply-addrpersist.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-addrpersist BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");

$ok=0;

/* ── [1] CH_ADDR_SYNCGUARD ── */
if(strpos($src,'CH_ADDR_SYNCGUARD')===false){
  $o1="    if(JSON.stringify(s)!==JSON.stringify(a[i].d)){ a[i].d=s; save(a); }";
  $n1="    if(JSON.stringify(s)!==JSON.stringify(a[i].d)){ /*CH_ADDR_SYNCGUARD*/ var _sE=!(s.f1||s.f2||s.f3||s.f7); var _dH=!!(a[i].d&&(a[i].d.f1||a[i].d.f3||a[i].d.f7)); if(!(_sE&&_dH)){ a[i].d=s; save(a); } }";
  $c=substr_count($src,$o1);
  if($c===1){ $src=str_replace($o1,$n1,$src); echo "  ✓ [1] CH_ADDR_SYNCGUARD — bos goruntu dolu profili ezmiyor\n"; $ok++; }
  else echo "  ✗ [1] anchor ($c, 1 beklenir) — atlandi\n";
} else { echo "  = [1] zaten ekli\n"; $ok++; }

/* ── [2] CH_ADDR_SAVE2 ── */
if(strpos($src,'CH_ADDR_SAVE2')===false){
  $o2="    P={f1,f2,f3,f4:document.getElementById('f4').value.trim(),f5:document.getElementById('f5').value,f6:document.getElementById('f6').value.trim(),f7};\n    try{localStorage.setItem(FK,JSON.stringify(P));}catch(ex){}";
  $n2="    P={f1,f2,f3,f4:document.getElementById('f4').value.trim(),f5:document.getElementById('f5').value,f6:document.getElementById('f6').value.trim(),f7};\n"
     ."    try{localStorage.setItem(FK,JSON.stringify(P));}catch(ex){}\n"
     ."    try{window.P=P;}catch(_pe1){}/*CH_ADDR_SAVE2*/\n"
     ."    try{ var _pa=JSON.parse(localStorage.getItem('ch_profiles')||'null'); if(Array.isArray(_pa)&&_pa.length){ var _ai=parseInt(localStorage.getItem('ch_prof_active')||'0',10); if(isNaN(_ai)||_ai<0||_ai>=_pa.length)_ai=0; _pa[_ai].d={f1:P.f1||'',f2:P.f2||'',f3:P.f3||'',f4:P.f4||'',f5:P.f5||'',f6:P.f6||'',f7:P.f7||''}; localStorage.setItem('ch_profiles',JSON.stringify(_pa)); } }catch(_pe2){}";
  $c=substr_count($src,$o2);
  if($c===1){ $src=str_replace($o2,$n2,$src); echo "  ✓ [2] CH_ADDR_SAVE2 — kaydet: window.P + aktif profil slotu senkron\n"; $ok++; }
  else echo "  ✗ [2] anchor ($c, 1 beklenir) — atlandi\n";
} else { echo "  = [2] zaten ekli\n"; $ok++; }

/* ── [3] CH_ADDR_HYDRATE ── */
if(strpos($src,'CH_ADDR_HYDRATE')===false){
  $block = <<<'HTMLBLOCK'
<script id="ch-addr-hydrate-js">
/* CH_ADDR_HYDRATE — window.P bossa ch_prof_v3'ten geri doldur (adres kaybini onler) */
try{(function(){
  var F=['f1','f2','f3','f4','f5','f6','f7'];
  function hyd(){
    try{
      var raw=localStorage.getItem('ch_prof_v3'); if(!raw) return;
      var d=JSON.parse(raw); if(!d||!(d.f1||d.f3)) return;
      if(!window.P){ window.P=d; return; }
      if(!window.P.f1 && d.f1){ F.forEach(function(k){ if(!window.P[k] && d[k]) window.P[k]=d[k]; }); }
    }catch(e){}
  }
  hyd(); setTimeout(hyd,600); setTimeout(hyd,1800); setTimeout(hyd,4000);
})();}catch(e){}
</script>
HTMLBLOCK;
  $pos=strripos($src,'</body>');
  if($pos!==false){ $src=substr($src,0,$pos).$block."\n".substr($src,$pos); echo "  ✓ [3] CH_ADDR_HYDRATE — acilista window.P ch_prof_v3'ten rehidrate\n"; $ok++; }
  else echo "  ✗ [3] </body> yok — atlandi\n";
} else { echo "  = [3] zaten ekli\n"; $ok++; }

if($ok===0){ echo "\nHICBIR bolum uygulanamadi — HICBIR sey degistirilmedi.\n"; exit; }

$tmp=tempnam(sys_get_temp_dir(),'ap').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-addrpersist-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
echo "\n  Sonuc: $ok/3 bolum tamam (".strlen($chk)." B)\n";
echo "  Test: Konto/profil -> adres gir -> kaydet -> App'i tam kapat-ac -> belge uret ->\n";
echo "  adres BIR DAHA SORULMAMALI (kalici). Ilk belgede bir kez sorup kaydeder.\n";
