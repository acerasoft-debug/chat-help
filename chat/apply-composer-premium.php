<?php
/**
 * ChatHelp — apply-composer-premium (CH_COMPOSER_PREMIUM)
 *  Claude tarzi premium chat composer. EKLEMELI (style+script, </body> oncesi):
 *   (1) mic <-> gonder gecisi: bos input -> sadece mikrofon (#pb), gonder (#sbtn)
 *       gizli; yaziya baslayinca #sbtn premium pop ile belirir, #pb gizlenir.
 *   (2) premium ATAC (📎): .ia-btns'e tek temiz ataç; 8 belgeye kadar
 *       (image/pdf), kaldirilabilir premium cipler; mevcut _chDocFiles/_chPhotos
 *       hattina beslenir. Eski tek-foto .chp[data-ctx=main] butonu gizlenir.
 *  Hicbir mevcut fonksiyon degistirilmez (byte-exact insert; guvenli).
 * KULLANIM: pull2.php?key=...&files=apply-composer-premium.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-composer-premium BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_COMPOSER_PREMIUM')!==false) exit("Zaten ekli (CH_COMPOSER_PREMIUM).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-composer-premium-css">
/* CH_COMPOSER_PREMIUM — mic-by-default, send-on-type, premium 8-file attach */
#ia .ia-btns #sbtn{display:none !important}
#ia .ia-btns.ch-has-text #sbtn{display:flex !important;animation:chSendPop .22s cubic-bezier(.2,.9,.3,1)}
#ia .ia-btns.ch-has-text #pb{display:none !important}
@keyframes chSendPop{0%{transform:scale(.55);opacity:0}60%{transform:scale(1.08)}100%{transform:scale(1);opacity:1}}
#pb,#sbtn,.ch-att-btn{transition:transform .15s ease,box-shadow .15s ease,color .15s ease,background .15s ease}
#pb:hover{color:var(--gold,#d4a84a);transform:translateY(-1px)}
#sbtn:hover{transform:translateY(-1px) scale(1.05);box-shadow:0 4px 16px rgba(212,168,74,.35)}
/* eski tek-foto butonunu gizle (yerine premium ataç) */
#ia .ia-btns .chp[data-ctx="main"]{display:none !important}
/* premium ataç butonu */
.ch-att-btn{display:flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:10px;cursor:pointer;color:var(--t2,#a8a8c8);background:transparent;flex-shrink:0;position:relative}
.ch-att-btn:hover{color:var(--gold,#d4a84a);background:rgba(212,168,74,.09);transform:translateY(-1px)}
.ch-att-btn svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.ch-att-btn input{display:none}
.ch-att-btn.ch-full{opacity:.45;cursor:not-allowed}
.ch-att-btn.ch-full:hover{background:transparent;transform:none;color:var(--t2,#a8a8c8)}
/* premium cip seridi */
.ch-att-strip{display:flex;flex-wrap:wrap;gap:7px;padding:2px 2px 8px}
.ch-att-strip:empty{display:none;padding:0}
.ch-att-chip{display:inline-flex;align-items:center;gap:7px;max-width:200px;padding:6px 10px;border-radius:11px;background:linear-gradient(135deg,rgba(212,168,74,.14),rgba(212,168,74,.05));border:1px solid rgba(212,168,74,.3);font-size:12px;color:var(--t1,#f0f0f8);box-shadow:0 1px 4px rgba(0,0,0,.15)}
.ch-att-chip .ic{flex-shrink:0}
.ch-att-chip .nm{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ch-att-chip .x{cursor:pointer;opacity:.65;flex-shrink:0;font-weight:700;padding:0 1px;font-size:13px}
.ch-att-chip .x:hover{opacity:1;color:#e05a5a}
@media(max-width:640px){.ch-att-btn{width:36px;height:36px}.ch-att-btn svg{width:18px;height:18px}}
</style>
<script id="ch-composer-premium-js">
/* CH_COMPOSER_PREMIUM */
try{(function(){
  var MAX=8;
  /* ───────── 1) mic <-> gonder gecisi ───────── */
  function syncBtns(){
    try{
      var inp=document.getElementById('inp');
      var btns=document.querySelector('#ia .ia-btns')||document.querySelector('.ia-btns');
      if(!inp||!btns) return;
      var has=((inp.value||'').trim().length>0);
      if(btns.classList.contains('ch-has-text')!==has) btns.classList.toggle('ch-has-text',has);
    }catch(e){}
  }
  function wireInput(){
    try{
      var inp=document.getElementById('inp');
      if(inp && !inp.__chwire){
        inp.__chwire=1;
        ['input','keyup','change','paste','focus','blur'].forEach(function(ev){ inp.addEventListener(ev,function(){ setTimeout(syncBtns,0); }); });
        inp.addEventListener('keydown',function(e){ if(e.key==='Enter'&&!e.shiftKey){ setTimeout(syncBtns,30); setTimeout(syncBtns,140);} });
      }
      var sb=document.getElementById('sbtn');
      if(sb && !sb.__chwire){ sb.__chwire=1; sb.addEventListener('click',function(){ setTimeout(syncBtns,30); setTimeout(syncBtns,150);}); }
    }catch(e){}
  }
  /* ───────── 2) premium 8-dosya ataç ───────── */
  window._chAtt = window._chAtt || [];
  function readFile(file,cb){
    try{
      if(file.size>6*1024*1024){ alert('„'+file.name+'" ist zu groß (max. 6 MB).'); return; }
      var ok=/^image\//.test(file.type)||file.type==='application/pdf';
      if(!ok){ alert('Nur Bilder oder PDF.'); return; }
      var fr=new FileReader();
      fr.onload=function(e){ cb({data:(''+e.target.result).split(',')[1],type:file.type,name:file.name}); };
      fr.readAsDataURL(file);
    }catch(e){}
  }
  /* paylasilan stores'a yansit (uretim/backend bunlari okur) — in-place mutasyon */
  function pushPhotos(){
    try{
      window._chDocFiles = window._chDocFiles || [];
      for(var j=window._chDocFiles.length-1;j>=0;j--){ if(window._chDocFiles[j] && window._chDocFiles[j].__att) window._chDocFiles.splice(j,1); }
      window._chAtt.forEach(function(f){ window._chDocFiles.push({data:f.data,type:f.type,name:f.name,__att:true}); });
      window._chPhotos = window._chPhotos || {};
      Object.keys(window._chPhotos).forEach(function(k){ if(k.indexOf('att')===0) delete window._chPhotos[k]; });
      window._chAtt.forEach(function(f,i){ window._chPhotos['att'+i]={data:f.data,type:f.type,name:f.name}; });
    }catch(e){}
  }
  function refreshBtn(){ try{ var b=document.querySelector('.ch-att-btn'); if(b) b.classList.toggle('ch-full', window._chAtt.length>=MAX); }catch(e){} }
  function draw(){
    try{
      var strip=document.querySelector('.ch-att-strip'); if(!strip) return;
      strip.innerHTML='';
      window._chAtt.forEach(function(f,i){
        var c=document.createElement('span'); c.className='ch-att-chip';
        c.innerHTML='<span class="ic"></span><span class="nm"></span><span class="x">✕</span>';
        c.querySelector('.ic').textContent=(f.type==='application/pdf')?'📄':'🖼️';
        c.querySelector('.nm').textContent=f.name||'Datei';
        c.querySelector('.x').onclick=function(){ window._chAtt.splice(i,1); pushPhotos(); draw(); refreshBtn(); };
        strip.appendChild(c);
      });
      refreshBtn();
    }catch(e){}
  }
  function onPick(input){
    try{
      var files=Array.prototype.slice.call(input.files||[]);
      input.value='';
      var over=false;
      files.forEach(function(f){
        readFile(f,function(o){
          if(window._chAtt.length>=MAX){ over=true; return; }
          window._chAtt.push(o); pushPhotos(); draw(); refreshBtn();
        });
      });
      if(files.length>MAX || over) setTimeout(function(){ alert('Max. '+MAX+' Dateien — es werden die ersten '+MAX+' verwendet.'); },80);
    }catch(e){}
  }
  function ensureAttach(){
    try{
      var btns=document.querySelector('#ia .ia-btns')||document.querySelector('.ia-btns'); if(!btns) return;
      var inner=document.querySelector('#ia .ia-inner')||btns.parentNode;
      if(inner && !inner.querySelector('.ch-att-strip')){
        var strip=document.createElement('div'); strip.className='ch-att-strip';
        inner.insertBefore(strip, inner.firstChild);
      }
      if(!btns.querySelector('.ch-att-btn')){
        var lab=document.createElement('label'); lab.className='ch-att-btn'; lab.title='Dokument/Foto hochladen (max 8)';
        lab.innerHTML='<svg viewBox="0 0 24 24"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg><input type="file" accept="image/*,application/pdf" multiple>';
        lab.querySelector('input').addEventListener('change',function(){ onPick(this); });
        btns.insertBefore(lab, btns.firstChild);
        draw();
      }
    }catch(e){}
  }
  /* chat icindeki image/pdf file input'larini coklu yap */
  function upgradeMultiple(){
    try{
      document.querySelectorAll('input[type=file]').forEach(function(i){
        var a=(i.accept||'');
        if((/image/.test(a)||/pdf/.test(a)) && (i.closest('.ia-btns')||i.closest('.cha2-doc')||i.closest('.chp')||i.closest('.ch-att-btn'))){
          if(!i.multiple) i.multiple=true;
        }
      });
    }catch(e){}
  }
  window.chAttClear=function(){ try{ window._chAtt=[]; pushPhotos(); draw(); refreshBtn(); }catch(e){} };

  function tick(){ wireInput(); syncBtns(); ensureAttach(); upgradeMultiple(); }
  var n=0; (function loop(){ tick(); if(n++<240) setTimeout(loop,500); })();
  try{ if(window.MutationObserver){ new MutationObserver(function(){ wireInput(); ensureAttach(); }).observe(document.body,{childList:true,subtree:true}); } }catch(e){}
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'cp').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-composerpremium-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_COMPOSER_PREMIUM')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_COMPOSER_PREMIUM diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Composer premium: bos->mic, yazinca->gonder; premium ataç (8 belge).\n";
echo "  Eski tek-foto butonu gizlendi; belgeler uretim hattina beslenir.\n";
