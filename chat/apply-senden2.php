<?php
/**
 * ChatHelp — apply-senden2 (CH_SENDEN2) — sonuc bar'ina (.racts) "📤 Senden".
 *  Gercek yapi (DOM teshisinden): .racts > .act (Kopieren, E-Mail) + .act.gold (PDF).
 *  Gold PDF butonu chPrintDoc(html)'i cagirir -> adres + Post/Einschreiben/Fax modali.
 *  Eski CH_SENDEN_BTN metni 'als pdf' aradigi icin tutmamisti (buton metni "PDF").
 *  COZUM: .racts .act.gold'u hedefle; hemen ONUNE altin "📤 Senden" koy; tikla ->
 *  ayni gold PDF butonunu tetikler (gonderim modali). Kota varsa Gratis-Fax rozeti.
 *  Her bar'a bir kez (guard) -> churn yok. 6 dil.
 * KULLANIM: pull2.php?key=...&files=apply-senden2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-senden2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_SENDEN2')!==false) exit("Zaten ekli (CH_SENDEN2).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-senden2-css">
.racts .ch-senden2{order:98!important;display:inline-flex;align-items:center;justify-content:center;gap:6px;background:linear-gradient(135deg,#d4a84a,#ecc060)!important;color:#07070e!important;border:none!important;border-radius:11px;padding:10px 14px;font-size:13.5px;font-weight:800;cursor:pointer;font-family:inherit;box-shadow:0 5px 16px rgba(212,168,74,.26)}
.racts .ch-senden2::after{content:""!important}
.racts .ch-senden2:hover{filter:brightness(1.06)}
.ch-senden2-badge{background:rgba(7,7,14,.16);border-radius:100px;padding:1px 7px;font-size:10px;font-weight:800}
</style>
<script id="ch-senden2-js">
/* CH_SENDEN2 — .racts'a '📤 Senden' (gold PDF butonunun yanina) */
try{(function(){
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2);}catch(e){return 'de';} }
  var L={de:'Senden',tr:'Gönder',en:'Send',fr:'Envoyer',es:'Enviar',it:'Invia'};
  var FREE={de:'Gratis-Fax',tr:'ücretsiz faks',en:'free fax',fr:'fax gratuit',es:'fax gratis',it:'fax gratis'};
  function inject(){
    try{
      var bars=document.querySelectorAll('.racts');
      for(var i=0;i<bars.length;i++){
        var bar=bars[i];
        var pdf=bar.querySelector('.act.gold')||bar.querySelector('button.gold');
        if(!pdf) continue;
        if(bar.querySelector('.ch-senden2')) continue;
        var t=lang();
        var s=document.createElement('button'); s.type='button'; s.className='act ch-senden2';
        var extra='';
        try{ var q=window.chFaxQuota&&window.chFaxQuota(); if(q&&q.remaining>0) extra=' <span class="ch-senden2-badge">★ '+q.remaining+' '+(FREE[t]||FREE.de)+'</span>'; }catch(e){}
        s.innerHTML='📤 '+(L[t]||L.de)+extra;
        (function(pdfBtn){ s.addEventListener('click',function(ev){ ev.preventDefault(); ev.stopPropagation(); try{ pdfBtn.click(); }catch(e){} }); })(pdf);
        bar.insertBefore(s, pdf);
      }
    }catch(e){}
  }
  try{ setInterval(inject,700); }catch(e){}
})();}catch(e){}
</script>
HTMLBLOCK;

$pos=strripos($src,'</body>');
if($pos===false) exit("</body> yok\n");
$src=substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp=tempnam(sys_get_temp_dir(),'s2').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-senden2-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_SENDEN2')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ CH_SENDEN2 eklendi (".strlen($chk)." B)\n";
echo "✓ Sonuc bar'inda (.racts) '📤 Senden' altin buton, gold PDF'in yaninda.\n";
echo "  Tikla -> ayni gonderim modali (adres + Post/Einschreiben/Fax). Kota varsa rozet.\n";
