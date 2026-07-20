<?php
/**
 * ChatHelp — apply-flickdbg-remove (CH_FLICKDBG_REMOVE) — ana sayfadaki mor
 *  TESHIS KUTUSUNU ("FLICKER…") kalicildan kaldirir.
 *   [1] index.php'den <script id="ch-flickdbg*"> bloklarinin HEPSINI siler
 *       (ch-flickdbg, ch-flickdbg2/3/4 — teshis + ekran kutusu buradan geliyordu).
 *   [2] Eski cihaz/SW cache'inde kutu hala olusuyorsa diye kucuk bir calisma-ani
 *       temizleyici ekler: metni "FLICKER" ile baslayan sabit kutuyu kaldirir.
 *  Titreme zaten CH_STABILIZE + CH_STABILIZE2 ile durduruldu; bu sadece teshis
 *  gorselini temizler.
 * KULLANIM: pull2.php?key=...&files=apply-flickdbg-remove.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-flickdbg-remove BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");

/* [1] tum ch-flickdbg* script bloklarini sil */
$n=0;
$src=preg_replace('#<script id="ch-flickdbg[0-9]*">.*?</script>#s','',$src,-1,$n);
echo "  ".($n>0?"✓":"=")." $n adet ch-flickdbg script blogu silindi\n";

/* [2] calisma-ani temizleyici (idempotent) */
if(strpos($src,'CH_FLICKDBG_REMOVE')===false){
  $clean = <<<'HTMLBLOCK'
<script id="ch-flickdbg-remove-js">
/* CH_FLICKDBG_REMOVE — kalmis teshis kutusunu (FLICKER…) kaldir */
try{(function(){
  function kill(){
    try{
      var ns=document.querySelectorAll('body>div');
      for(var i=0;i<ns.length;i++){
        var t=(ns[i].textContent||'');
        if(t.indexOf('FLICKER')===0 || t.indexOf('FLICKER (')>=0){ ns[i].remove(); }
      }
    }catch(e){}
  }
  kill(); var k=0; var iv=setInterval(function(){ kill(); if(++k>10) clearInterval(iv); },500);
})();}catch(e){}
</script>
HTMLBLOCK;
  $pos=strripos($src,'</body>');
  if($pos!==false){ $src=substr($src,0,$pos).$clean."\n".substr($src,$pos); echo "  ✓ calisma-ani temizleyici eklendi\n"; }
}

$tmp=tempnam(sys_get_temp_dir(),'fr').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-flickrm-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
$kalan=substr_count($chk,'id="ch-flickdbg4"')+substr_count($chk,'id="ch-flickdbg3"');
echo "\n  ✓ index.php guncellendi (".strlen($chk)." B) — kalan flickdbg script: $kalan\n";
echo "✓ Mor teshis kutusu kaldirildi. Hard-refresh -> kutu artik gelmemeli.\n";
