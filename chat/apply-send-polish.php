<?php
/**
 * ChatHelp — apply-send-polish (CH_SEND_POLISH) — gonderim UX cila (guvenli parca):
 *  [1] "✍️ Unterschreiben & an Empfänger senden" -> "📤 An Empfänger senden"
 *      (kullanici 'Senden' istedi; kaynak str_replace, imza hala erforderlich).
 *  [2] Sonuc bar'inda (.racts) '📤 Senden' ve altin PDF butonu ORANTILI/esit
 *      (ikisi de flex:1 1 46%); Kopieren/E-Mail kucuk kalir.
 *  [3] Gonderim modali butonlari + kutu premium cila (CSS, metinden yakalanan
 *      .ch-sendmodal sinifina).
 *  NOT: Fax-ilk secenek + AL adres/fax yardimi + IBAN/adres bug'i AYRI, sonraki
 *       adim (backend baglantisi gerektiriyor).
 * KULLANIM: pull2.php?key=...&files=apply-send-polish.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-send-polish BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_SEND_POLISH')!==false) exit("Zaten ekli (CH_SEND_POLISH).\n");

/* [1] relabel (Unterschreiben -> Senden) */
$c1=0;
$src=str_replace('Unterschreiben & an Empfänger senden','An Empfänger senden',$src,$c1);
echo "  ".($c1>0?"✓":"=")." [1] 'Unterschreiben & an Empfänger senden' -> 'An Empfänger senden' ($c1)\n";

/* [2]+[3] CSS + modal sinif isaretleyici */
$block = <<<'HTMLBLOCK'
<style id="ch-send-polish-css">
/* [2] Senden & PDF orantili/esit */
.racts{display:flex!important;flex-wrap:wrap!important;gap:8px!important;align-items:stretch}
.racts .ch-senden2,.racts .act.gold{flex:1 1 46%!important;min-width:150px;justify-content:center}
.racts .act:not(.gold):not(.ch-senden2){flex:0 1 auto}
/* [3] gonderim modali premium cila */
.ch-sendmodal button{border-radius:13px!important;font-weight:800!important;transition:filter .15s,transform .1s!important}
.ch-sendmodal button:active{transform:scale(.99)}
.ch-sendmodal .ch-sm-prim{background:linear-gradient(135deg,#d4a84a,#ecc060)!important;color:#141018!important;border:none!important;box-shadow:0 6px 18px rgba(212,168,74,.26)!important}
.ch-sendmodal input,.ch-sendmodal textarea{border-radius:12px!important;transition:border-color .15s,box-shadow .15s}
.ch-sendmodal input:focus,.ch-sendmodal textarea:focus{border-color:var(--gold,#d4a84a)!important;box-shadow:0 0 0 3px rgba(212,168,74,.14)!important;outline:none}
</style>
<script id="ch-send-polish-js">
/* CH_SEND_POLISH — gonderim modalini metinden yakalayip .ch-sendmodal isaretle (cila) */
try{(function(){
  function mark(){
    try{
      var all=document.querySelectorAll('button');
      for(var i=0;i<all.length;i++){
        var t=(all[i].textContent||'');
        if(/selbst ausdrucken|Original nach Hause|An Empfänger senden/i.test(t)){
          var box=all[i].closest('div'); var lvl=0;
          while(box && lvl<5){ if(box.querySelectorAll('button').length>=2){ box.classList.add('ch-sendmodal'); break; } box=box.parentNode; lvl++; }
          /* birincil altin butonu isaretle (selbst ausdrucken zaten gold) */
          if(/An Empfänger senden/i.test(t)) all[i].classList.add('ch-sm-prim');
        }
      }
    }catch(e){}
  }
  try{ setInterval(mark,800); }catch(e){}
})();}catch(e){}
</script>
HTMLBLOCK;
$pos=strripos($src,'</body>');
if($pos===false) exit("</body> yok\n");
$src=substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp=tempnam(sys_get_temp_dir(),'sp').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-sendpolish-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_SEND_POLISH')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ [2] Senden & PDF orantili (esit)\n";
echo "  ✓ [3] Gonderim modali premium cila\n";
echo "\n  ✓ CH_SEND_POLISH diskte (".strlen($chk)." B).\n";
echo "SIRADA (ayri): Fax-ilk secenek + AL adres/fax yardimi + IBAN/adres bug.\n";
