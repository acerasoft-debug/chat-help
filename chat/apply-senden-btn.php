<?php
/**
 * ChatHelp — apply-senden-btn (CH_SENDEN_BTN) — sonuc ekraninda "📤 Senden" +
 *  "Als PDF" YAN YANA (esit), tum belgelerde.
 *  Mimari: PDF butonu window.chPrintDoc(html)'i cagirir; imza5 bunu sarmalayip
 *  mektuplarda adres + Post/Einschreiben/Fax secicisini (Fax birincil) acar.
 *  Bu yuzden "Senden" ayni akisi tetikler: PDF butonunun handler'ini calistirir.
 *  Additive: her PDF butonu bir flex satira alinir, soluna "📤 Senden" konur
 *  (ikisi flex:1 = esit). Her butona BIR KEZ (data-guard) -> churn yok. 6 dil.
 * KULLANIM: pull2.php?key=...&files=apply-senden-btn.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-senden-btn BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_SENDEN_BTN')!==false) exit("Zaten ekli (CH_SENDEN_BTN).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-senden-btn-css">
.ch-send-row{display:flex;gap:8px;align-items:stretch;margin:0 0 2px}
.ch-send-row>*{flex:1 1 0;min-width:0}
.ch-senden-btn{display:flex;align-items:center;justify-content:center;gap:7px;background:linear-gradient(135deg,#d4a84a,#ecc060);color:#141018;border:none;border-radius:13px;padding:13px 12px;font-size:14px;font-weight:800;cursor:pointer;box-shadow:0 6px 18px rgba(212,168,74,.24);font-family:inherit;transition:filter .15s}
.ch-senden-btn:hover{filter:brightness(1.06)}
.ch-senden-badge{display:inline-block;margin-left:6px;background:rgba(20,16,24,.16);border-radius:100px;padding:1px 7px;font-size:10px;font-weight:800}
</style>
<script id="ch-senden-btn-js">
/* CH_SENDEN_BTN — 'Senden' butonu Als-PDF butonunun yanina (esit), tum belgeler */
try{(function(){
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2);}catch(e){return 'de';} }
  var L={de:'Senden',tr:'Gönder',en:'Send',fr:'Envoyer',es:'Enviar',it:'Invia'};
  var FREE={de:'Gratis-Fax',tr:'ücretsiz faks',en:'free fax',fr:'fax gratuit',es:'fax gratis',it:'fax gratis'};
  function isPdfBtn(b){
    try{ var t=(b.textContent||'').trim().toLowerCase();
      return /als pdf|pdf speichern|pdf olarak|save as pdf|pdf kaydet|enregistrer.*pdf|guardar.*pdf|salva.*pdf/.test(t);
    }catch(e){ return false; }
  }
  function inject(){
    try{
      var all=document.querySelectorAll('button,a');
      for(var i=0;i<all.length;i++){
        var b=all[i];
        if(b.__sendenDone) continue;
        if(!isPdfBtn(b)) continue;
        if(b.closest && b.closest('.ch-send-row')) { b.__sendenDone=true; continue; }
        b.__sendenDone=true;
        var t=lang();
        var s=document.createElement('button'); s.type='button'; s.className='ch-senden-btn';
        var extra='';
        try{ var q=window.chFaxQuota&&window.chFaxQuota(); if(q&&q.remaining>0) extra='<span class="ch-senden-badge">★ '+q.remaining+' '+(FREE[t]||FREE.de)+'</span>'; }catch(e){}
        s.innerHTML='📤 '+(L[t]||L.de)+extra;
        (function(pdfBtn){ s.addEventListener('click',function(ev){ ev.preventDefault(); ev.stopPropagation(); try{ pdfBtn.click(); }catch(e){} }); })(b);
        /* flex satira al: Senden solda, PDF sagda, esit */
        var row=document.createElement('div'); row.className='ch-send-row';
        var par=b.parentNode; if(!par) continue;
        par.insertBefore(row, b);
        row.appendChild(s); row.appendChild(b);
      }
    }catch(e){}
  }
  try{ setInterval(inject,800); }catch(e){}
})();}catch(e){}
</script>
HTMLBLOCK;

$pos=strripos($src,'</body>');
if($pos===false) exit("</body> yok\n");
$src=substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp=tempnam(sys_get_temp_dir(),'sb').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-sendenbtn-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_SENDEN_BTN')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ CH_SENDEN_BTN eklendi (".strlen($chk)." B)\n";
echo "✓ Her belge sonucunda '📤 Senden' + 'Als PDF' YAN YANA (esit); Senden akisi ac.\n";
echo "  Mektuplarda: adres + Post/Einschreiben/Fax (Fax birincil). Kota varsa Gratis-Fax rozeti.\n";
