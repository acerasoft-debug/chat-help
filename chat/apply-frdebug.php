<?php
/** ChatHelp — GEÇİCİ teşhis kutusu: CATS/DOCS/CAT_DOCS runtime durumu (Fransız belge bug'ı) */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
echo "apply-frdebug BAŞLADI ✓\n";
$file=__DIR__.'/index.php';
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file);
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,'CH_FRDEBUG')!==false) exit("Zaten ekli. SİL: rm apply-frdebug.php\n");
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.'.bak-frdebug-'.date('Ymd-His'),$src);

$js = <<<'JS'

/* CH_FRDEBUG — geçici teşhis (aynı scope: CATS/DOCS/CC görebilsin) */
try{(function(){
  function box(){
    try{
      var d=document.getElementById('ch-frdebug-box');
      if(!d){ d=document.createElement('div'); d.id='ch-frdebug-box';
        d.style.cssText='position:fixed;bottom:8px;left:8px;z-index:2147483647;background:#000;color:#0f0;font:11px/1.45 monospace;padding:9px 11px;border:1px solid #0f0;border-radius:8px;max-width:92vw;white-space:pre-wrap';
        (document.body||document.documentElement).appendChild(d); }
      var L=[];
      try{ var fc=0; if(typeof DOCS!=='undefined'){ for(var k in DOCS){ if(k.indexOf('fr_')===0) fc++; } } L.push('DOCS='+(typeof DOCS)+' fr_count='+fc); }catch(e){ L.push('DOCS THROW '+e.message); }
      try{ L.push('CATS='+(typeof CATS)+(typeof CATS!=='undefined'?' frozen='+Object.isFrozen(CATS)+' ext='+Object.isExtensible(CATS):'')); }catch(e){ L.push('CATS THROW '+e.message); }
      try{ if(typeof CATS!=='undefined'){ var c=CATS['fr_resiliation']; L.push('CATS.fr_resiliation='+(c?('docs:'+(c.docs?c.docs.length:'YOK')):'MISSING')); } }catch(e){ L.push('CATS.fr THROW '+e.message); }
      try{ if(typeof CATS!=='undefined'){ var fk=Object.keys(CATS).filter(function(x){return x.indexOf('fr_')===0;}); L.push('CATS fr keys('+fk.length+'): '+fk.join(',')); } }catch(e){}
      try{ L.push('CAT_DOCS='+(typeof CAT_DOCS)+(typeof CAT_DOCS!=='undefined'?' frozen='+Object.isFrozen(CAT_DOCS):'')); }catch(e){ L.push('CAT_DOCS THROW '+e.message); }
      try{ if(typeof CAT_DOCS!=='undefined'){ var cd=CAT_DOCS['fr_resiliation']; L.push('CAT_DOCS.fr_resiliation='+(cd?cd.length:'MISSING')); } }catch(e){}
      try{ L.push('CC='+(typeof CC!=='undefined'?CC:'?')+' UL='+(typeof UL!=='undefined'?UL:'?')); }catch(e){ L.push('CC/UL THROW '+e.message); }
      try{ L.push('uilang='+localStorage.getItem('ch_uilang')+' lm='+localStorage.getItem('ch_lm')+' ldi='+localStorage.getItem('ch_ldi')); }catch(e){}
      d.textContent='FR-DEBUG\n'+L.join('\n');
    }catch(e){}
  }
  function boot(){ box(); setInterval(box,1500); }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',boot); else boot();
})();}catch(e){}
JS;

$src=str_replace($anchor,$anchor.$js,$src);
file_put_contents($file,$src);
echo "✓ Teşhis kutusu eklendi (CH_FRDEBUG). Sayfanın SOL ALT köşesinde yeşil kutu çıkacak.\n";
echo "SONRA: opcache-reset.php -> 🇫🇷 France seç -> SOL ALT yeşil kutunun EKRAN GÖRÜNTÜSÜNÜ at.\n";
echo "SİL: rm apply-frdebug.php (sonra bunu geri alacağım)\n";
