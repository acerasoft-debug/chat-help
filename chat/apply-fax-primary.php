<?php
/**
 * ChatHelp — apply-fax-primary (CH_FAX_PRIMARY) — Fax = ANA gonderim.
 *  Gonderim secicisinde (#ai-box, input[name=ai-vsa]: Post/Einschreiben/Fax)
 *  additive olarak:
 *   [1] Fax secenegini EN USTE tasi.
 *   [2] Fax'i VARSAYILAN sec (ilk acilista, kullanici degistirene kadar).
 *   [3] Kota varsa "★ N Gratis-Fax" altin rozeti (window.chFaxQuota()).
 *  Her secici ornegine BIR KEZ uygulanir (__faxprimed) -> churn/savas yok;
 *  kullanici sonradan Post secerse zorlamaz. Mevcut minified kodu DUZENLEMEZ.
 * KULLANIM: pull2.php?key=...&files=apply-fax-primary.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fax-primary BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_FAX_PRIMARY')!==false) exit("Zaten ekli (CH_FAX_PRIMARY).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-fax-primary-css">
#ch-faxprim-badge,.ch-faxprim-badge{display:inline-block;margin-left:8px;background:linear-gradient(135deg,#e8c877,#d4a84a);color:#1b1b1e;border-radius:100px;padding:2px 9px;font-size:10.5px;font-weight:800;vertical-align:middle}
.ch-faxprim-opt{outline:1.5px solid rgba(212,168,74,.5);outline-offset:2px;border-radius:10px}
</style>
<script id="ch-fax-primary-js">
/* CH_FAX_PRIMARY — Fax'i ana gonderim yap (secicide en ust + varsayilan + rozet) */
try{(function(){
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2);}catch(e){return 'de';} }
  var REC={de:'Empfohlen',tr:'Önerilen',en:'Recommended',fr:'Recommandé',es:'Recomendado',it:'Consigliato'};
  var GRATIS={de:'Gratis-Fax',tr:'ücretsiz faks',en:'free fax',fr:'fax gratuit',es:'fax gratis',it:'fax gratis'};
  function prime(){
    try{
      var radios=document.querySelectorAll('input[name="ai-vsa"]');
      if(!radios.length) return;
      var scope=radios[0].closest('#ai-box')||radios[0].closest('form')||document.body;
      if(scope.__faxprimed) return;
      var faxR=null;
      radios.forEach(function(r){ var lbl=r.closest('label')||r.parentNode; var t=(lbl&&lbl.textContent)||''; if(/📠|\bfax\b/i.test(t)) faxR=r; });
      if(!faxR) return;
      scope.__faxprimed=true;
      var opt=faxR.closest('label')||faxR.parentNode;
      var cont=opt&&opt.parentNode;
      /* [1] en uste tasi */
      try{ if(cont && cont.firstChild!==opt) cont.insertBefore(opt, cont.firstChild); }catch(e){}
      /* [3] rozet */
      try{
        if(opt && !opt.querySelector('.ch-faxprim-badge')){
          var t=lang(); var txt='★ '+(REC[t]||REC.de);
          try{ var q=window.chFaxQuota&&window.chFaxQuota(); if(q&&q.remaining>0) txt='★ '+q.remaining+' '+(GRATIS[t]||GRATIS.de); }catch(e){}
          var b=document.createElement('span'); b.className='ch-faxprim-badge'; b.textContent=txt;
          var host=opt.querySelector('span,b,strong')||opt; host.appendChild(b);
          opt.classList.add('ch-faxprim-opt');
        }
      }catch(e){}
      /* [2] varsayilan sec */
      try{ if(!faxR.checked){ faxR.checked=true; faxR.dispatchEvent(new Event('change',{bubbles:true})); } }catch(e){}
    }catch(e){}
  }
  try{ setInterval(prime,700); }catch(e){}
})();}catch(e){}
</script>
HTMLBLOCK;

$pos=strripos($src,'</body>');
if($pos===false) exit("</body> yok\n");
$src=substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp=tempnam(sys_get_temp_dir(),'fp').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-faxprim-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_FAX_PRIMARY')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ CH_FAX_PRIMARY eklendi (".strlen($chk)." B)\n";
echo "✓ Gonderim secicisinde Fax: en ustte + varsayilan + 'Gratis-Fax' rozeti.\n";
echo "  (Kullanici isterse Post/Einschreiben secebilir — zorlama yok.)\n";
