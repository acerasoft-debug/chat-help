<?php
/**
 * ChatHelp — apply-chat-att (CH_CHAT_ATT) — chat 📎 = Belge & Foto Merkezi.
 *  Chat'teki atac dugmesine basinca artik dogrudan BELGE & FOTO MERKEZI
 *  paneli acilir -> tek mantik her yerde: 8'e kadar foto+PDF, aninda
 *  kullanici dilinde ozet, secenekler (cevap/itiraz, acikla, soru, sure),
 *  kalici hafiza ve ayni-konu birlesik cevap. Secilenler zaten sohbete akar.
 *  Eski kirilgan atac boru hatti devre disi kalir (dosya dialogu acilmaz).
 * KULLANIM: pull2.php?key=...&files=apply-chat-att.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-chat-att BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_CHAT_ATT')!==false) exit("Zaten ekli (CH_CHAT_ATT).\n");
if (strpos($src,'CH_FOTO_PANEL')===false) exit("HATA: once CH_FOTO_PANEL gerekli.\n");

$block = <<<'HTMLBLOCK'
<script id="ch-chat-att-js">
/* CH_CHAT_ATT — chat atac dugmesi Belge & Foto Merkezi'ni acar (tek mantik) */
try{(function(){
  document.addEventListener('click',function(ev){
    try{
      var t=ev.target; if(!t||!t.closest) return;
      var b=t.closest('.ch-att-btn'); if(!b) return;
      if(typeof window.chFotoPanel!=='function') return; /* panel yoksa eski akis calissin */
      ev.preventDefault(); ev.stopImmediatePropagation();
      window.chFotoPanel();
    }catch(e){}
  },true);
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'ca').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-chatatt-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_CHAT_ATT')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_CHAT_ATT diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Chat'teki 📎 artik Belge & Foto Merkezi'ni acar — her yerde tek mantik:\n";
echo "   foto+PDF (8), aninda dilinde ozet, secenekler, hafiza, birlesik cevap.\n";
