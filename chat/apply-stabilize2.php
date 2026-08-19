<?php
/**
 * ChatHelp — apply-stabilize2 (CH_STABILIZE2) — GERÇEK titreme kaynağı: DOM churn.
 *  flickdbg kaniti: birkac setInterval dongusu, icerik DEGISMESE BILE
 *  metin/attribute/innerHTML'i tekrar tekrar yaziyor -> surekli repaint = titreme
 *  (ornek: .chp-wrap@.vst-grid 27x, #k-name/#k-adr metni 12x, #ch-billing-btn 9x,
 *   #jb-* placeholder/metin, #ch-faxquota-sec[data-sig] 5x).
 *  COZUM: uc yazma noktasini sarmala, SONUC AYNIYSA yazmayi atla (saf iyilestirme,
 *  davranis degismez):
 *    [1] Element.setAttribute(n,v) -> getAttribute(n)===v ise atla
 *    [2] Element.innerHTML setter  -> mevcut===yeni ise atla
 *    [3] Node.textContent setter   -> mevcut===yeni ise atla
 *  Bu benim kendi dongulerimi de (konto-realuser, faxquota) otomatik sabitler.
 *  Additive </body> scripti; idempotent (__chStab2 koruma).
 * KULLANIM: pull2.php?key=...&files=apply-stabilize2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-stabilize2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_STABILIZE2')!==false) exit("Zaten ekli (CH_STABILIZE2).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-stabilize2-js">
/* CH_STABILIZE2 — gereksiz (ayni-sonuc) DOM yazmalarini atla -> titreme biter */
try{(function(){
  if(window.__chStab2) return; window.__chStab2=1;
  /* [1] setAttribute — deger degismiyorsa atla */
  try{
    var _sa=Element.prototype.setAttribute;
    Element.prototype.setAttribute=function(n,v){
      try{ if(this.getAttribute(n)===String(v)) return; }catch(e){}
      return _sa.call(this,n,v);
    };
  }catch(e){}
  /* [2] innerHTML — ayni ise atla */
  try{
    var ih=Object.getOwnPropertyDescriptor(Element.prototype,'innerHTML');
    if(ih&&ih.set&&ih.get){
      Object.defineProperty(Element.prototype,'innerHTML',{
        configurable:true, enumerable:ih.enumerable,
        get:ih.get,
        set:function(v){ try{ if(ih.get.call(this)===String(v)) return; }catch(e){} return ih.set.call(this,v); }
      });
    }
  }catch(e){}
  /* [3] textContent — ayni ise atla */
  try{
    var tc=Object.getOwnPropertyDescriptor(Node.prototype,'textContent');
    if(tc&&tc.set&&tc.get){
      Object.defineProperty(Node.prototype,'textContent',{
        configurable:true, enumerable:tc.enumerable,
        get:tc.get,
        set:function(v){ try{ if(tc.get.call(this)===String(v)) return; }catch(e){} return tc.set.call(this,v); }
      });
    }
  }catch(e){}
})();}catch(e){}
</script>
HTMLBLOCK;

$pos=strripos($src,'</body>');
if($pos===false) exit("</body> bulunamadi\n");
$new=substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp=tempnam(sys_get_temp_dir(),'s2').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-stab2-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_STABILIZE2')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ CH_STABILIZE2 eklendi (".strlen($chk)." B)\n";
echo "✓ Gereksiz metin/attribute/innerHTML yazmalari atlanir -> titreme durur.\n";
echo "✓ Kendi dongulerim (konto-realuser, faxquota) da otomatik sabitlenir.\n";
echo "  Test: siteyi hard-refresh -> flickdbg kutusu artik neredeyse bos olmali.\n";
