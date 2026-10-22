<?php
/**
 * ChatHelp — apply-forcewipe (CH_FORCEWIPE) — KESIN COZUM: heuristic (ch_bound_uid
 *  karsilastirmasi) yetersiz kaldi — Bulent'in verisi Serdar hesabinda hala cikiyor,
 *  ve edit etsen de degismiyor (periyodik autofill stale onbellekten tekrar dolduruyor).
 *  Bu yama KOSULSUZ, TEK SEFERLIK (yeni bayrak: ch_forcewipe_v1) bir temizlik yapar:
 *  HER cihazda, ch_bound_uid durumuna BAKMAKSIZIN, TUM profil-onbellegi (isim/adres/
 *  telefon/email autofill cache'i) silinir. LOGIN TOKEN/HESAP DOKUNULMAZ — kullanici
 *  cikis yapmaz, sadece profil formu bos baslar ve KENDI bilgisini bir kez girer.
 * KULLANIM: pull2.php?key=...&files=apply-forcewipe.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-forcewipe BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_FORCEWIPE')!==false) exit("Zaten ekli (CH_FORCEWIPE).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-forcewipe-js">
/* CH_FORCEWIPE — kosulsuz, tek seferlik profil-onbellegi temizligi (login/token DOKUNULMAZ) */
try{(function(){
  try{
    if(!localStorage.getItem('ch_forcewipe_v1')){
      ['ch_prof_v3','ch_prof_active','ch_profiles','ch_credits','CH_FREE_AVAIL','ch_lastdoc','ch_bound_uid'].forEach(function(k){
        try{ localStorage.removeItem(k); }catch(e){}
      });
      localStorage.setItem('ch_forcewipe_v1','1');
    }
  }catch(e){}
})();}catch(e){}
</script>
HTMLBLOCK;
$pos=strripos($src,'</body>');
if($pos===false) exit("</body> yok\n");
/* dosyanin EN BASINA yakin bir yere koymak icin: mumkunse ilk <script veya <head> sonrasi,
   ama guvenli/basit cozum: mevcut additive desenle </body> oncesine ekle -- yeterince erken
   calisir cunku DOMContentLoaded/interaktif oncesi calisir (parser script'i hemen calistirir). */
$src=substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp=tempnam(sys_get_temp_dir(),'fw').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-forcewipe-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_FORCEWIPE')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ CH_FORCEWIPE diskte (".strlen($chk)." B)\n";
echo "  ETKI: HER cihazda bu kod ilk calistiginda profil-onbellegi (isim/adres/email\n";
echo "  autofill) TAMAMEN silinir — giris/hesap/token ETKILENMEZ.\n";
echo "  Test: App'i ac -> 'Persönliche Daten' -> Bulent'in bilgileri GITMIS olmali\n";
echo "  (bos/placeholder). Kendi bilgini bir kez gir, kaydet, artik KALICI olmali.\n";
