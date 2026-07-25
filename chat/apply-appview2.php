<?php
/**
 * ChatHelp — apply-appview2 (CH_APPVIEW2) — belgeyi KANITLANMIS noktadan goster.
 *
 * KANIT: log'da "CPD2 true" -> chPrintDoc'un App dali (if(_isWv2){...})
 * gercekten calisiyor ve orada 'html' degiskeni elimizde. Onceki CH_APPVIEW
 * belgeyi _chLastDoc/.ch-doccard-body'den TAHMIN etmeye calisiyordu; bos
 * geldiginde hicbir sey acilmiyordu.
 *
 * BU YAMA:
 *  [1] window.chAppView(html) global gorutuleyici tanimlar (tam ekran katman:
 *      beyaz kagit, kaydirilabilir, PDF / Teilen / Kopieren / Schliessen).
 *  [2] chPrintDoc'un App dallarinin (if(_isWv2){ ve if(_isWv3){) HEMEN basina
 *      "chAppView(html); return;" ekler -> belge kesin ekrana gelir.
 * Web/tarayici HIC etkilenmez (dallar yalniz App'te calisir).
 *
 * Geri alma: apply-appview-off.php
 * KULLANIM: /chat/pull2.php?key=...&files=apply-appview2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
echo "apply-appview2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_APPVIEW2')!==false) exit("Zaten ekli (CH_APPVIEW2) — DEGISIKLIK YOK.\n");
$new=$src;

/* ── [1] global gorutuleyici ── */
$js = <<<'HTML'
<script>/*CH_APPVIEW2*/(function(){try{
function B(d){try{new Image().src="chlog9k1.php?k=chl_2607&t="+(new Date().getTime())+"&d="+encodeURIComponent(d);}catch(e){}}
function L(){try{return (typeof uil==='function')?uil():(localStorage.getItem('ch_uilang')||'de');}catch(e){return 'de';}}
var T={de:{t:'Ihr Dokument',p:'PDF',s:'Teilen',c:'Kopieren',x:'Schließen',ok:'Kopiert'},
       tr:{t:'Belgeniz',p:'PDF',s:'Paylaş',c:'Kopyala',x:'Kapat',ok:'Kopyalandı'},
       en:{t:'Your document',p:'PDF',s:'Share',c:'Copy',x:'Close',ok:'Copied'}};
function t(k){var l=L();return (T[l]||T.de)[k]||T.de[k];}
function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
function html2text(h){
  try{
    var s=String(h||'');
    s=s.replace(/<script[\s\S]*?<\/script>/gi,' ').replace(/<style[\s\S]*?<\/style>/gi,' ');
    s=s.replace(/<br\s*\/?>/gi,'\n').replace(/<\/(p|div|h1|h2|h3|tr|li)>/gi,'\n');
    var d=document.createElement('div');d.innerHTML=s;
    var txt=d.textContent||d.innerText||'';
    return txt.replace(/\r/g,'').replace(/[ \t]+\n/g,'\n').replace(/\n{3,}/g,'\n\n').trim();
  }catch(e){return String(h||'');}
}
function btn(label){
  var b=document.createElement('button');b.textContent=label;
  b.setAttribute('style','flex:1;min-width:0;padding:12px 4px;font-size:13px;font-weight:700;border-radius:10px;'
    +'border:1px solid rgba(212,168,74,.5);background:#141d44;color:#f0e6c8;-webkit-tap-highlight-color:transparent');
  return b;
}
window.chAppView=function(rawHtml){
  try{
    if(document.getElementById('ch-appview'))return true;
    var txt=html2text(rawHtml);
    if(!txt||txt.replace(/\s/g,'').length<20){B('APPVIEW2-EMPTY');return false;}
    var o=document.createElement('div');o.id='ch-appview';
    o.setAttribute('style','position:fixed;left:0;top:0;right:0;bottom:0;z-index:2147483000;background:#080c1c;display:flex;flex-direction:column');
    var hd=document.createElement('div');
    hd.setAttribute('style','padding:12px 14px;background:#0f1634;border-bottom:1px solid rgba(212,168,74,.35);color:#f0e6c8;font-weight:800;font-size:15px');
    hd.textContent='📄 '+t('t');
    var pg=document.createElement('div');
    pg.setAttribute('style','flex:1;overflow:auto;-webkit-overflow-scrolling:touch;padding:14px');
    var pap=document.createElement('div');
    pap.setAttribute('style','background:#fff;color:#14161c;border-radius:6px;padding:22px 20px;font-family:Georgia,serif;'
      +'font-size:15px;line-height:1.65;white-space:pre-wrap;word-wrap:break-word;-webkit-user-select:text;user-select:text;'
      +'box-shadow:0 6px 26px rgba(0,0,0,.5)');
    pap.textContent=txt;pg.appendChild(pap);
    var ac=document.createElement('div');
    ac.setAttribute('style','display:flex;gap:6px;padding:10px 8px calc(10px + env(safe-area-inset-bottom));background:#0f1634;border-top:1px solid rgba(212,168,74,.35)');
    var bp=btn('⬇️ '+t('p')),bs=btn('📤 '+t('s')),bc=btn('📋 '+t('c')),bx=btn('✕ '+t('x'));
    function letterHTML(){return '<!doctype html><html><head><meta charset="utf-8"><title>ChatHelp-Dokument</title></head>'
      +'<body style="font-family:Georgia,serif;font-size:12pt;line-height:1.6;white-space:pre-wrap;margin:2.2cm">'+esc(txt)+'</body></html>';}
    function formPost(){try{
      var f=document.createElement('form');f.method='POST';f.action='dl.php';f.target='_self';f.style.display='none';
      var i1=document.createElement('input');i1.name='html';i1.value=letterHTML();f.appendChild(i1);
      var i2=document.createElement('input');i2.name='name';i2.value='ChatHelp-Dokument';f.appendChild(i2);
      var i3=document.createElement('input');i3.name='fmt';i3.value='pdf';f.appendChild(i3);
      document.body.appendChild(f);f.submit();
      setTimeout(function(){try{document.body.removeChild(f);}catch(e){}},1500);
    }catch(e){}}
    function blobDL(b){try{
      var u=URL.createObjectURL(b);var a=document.createElement('a');
      a.href=u;a.download='ChatHelp-Dokument.pdf';document.body.appendChild(a);a.click();B('APPVIEW2-BLOBDL');
      setTimeout(function(){try{document.body.removeChild(a);URL.revokeObjectURL(u);}catch(e){}},3000);
    }catch(e){formPost();}}
    bp.onclick=function(){try{B('APPVIEW2-PDF');var old=bp.textContent;bp.textContent='…';
      var fd=new FormData();fd.append('html',letterHTML());fd.append('name','ChatHelp-Dokument');fd.append('fmt','pdf');
      fetch('dl.php',{method:'POST',body:fd}).then(function(r){return r.blob();}).then(function(b){
        bp.textContent=old;var fl=null;
        try{fl=new File([b],'ChatHelp-Dokument.pdf',{type:'application/pdf'});}catch(e){}
        if(fl&&navigator.share&&navigator.canShare&&navigator.canShare({files:[fl]})){
          B('APPVIEW2-SHAREFILE');navigator.share({files:[fl],title:'ChatHelp-Dokument'}).catch(function(){blobDL(b);});
        }else{blobDL(b);}
      }).catch(function(){bp.textContent=old;formPost();});
    }catch(e){formPost();}};
    bs.onclick=function(){try{B('APPVIEW2-SHARE');
      if(navigator.share){navigator.share({title:'ChatHelp-Dokument',text:txt}).catch(function(){});}
      else{bs.textContent='—';}
    }catch(e){}};
    bc.onclick=function(){try{
      if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(txt);}
      else{var ta=document.createElement('textarea');ta.value=txt;document.body.appendChild(ta);ta.select();
        try{document.execCommand('copy');}catch(e){}document.body.removeChild(ta);}
      bc.textContent='✓';setTimeout(function(){bc.textContent='📋 '+t('c');},1600);
    }catch(e){}};
    bx.onclick=function(){try{o.remove();}catch(e){}};
    ac.appendChild(bp);ac.appendChild(bs);ac.appendChild(bc);ac.appendChild(bx);
    o.appendChild(hd);o.appendChild(pg);o.appendChild(ac);
    document.body.appendChild(o);
    B('APPVIEW2-OPEN len='+txt.length);
    return true;
  }catch(e){try{B('APPVIEW2-ERR');}catch(_){}return false;}
};
}catch(e){}})();</script>
HTML;

$p=strripos($new,'</body>');
if($p===false) exit("✗ </body> bulunamadi — DEGISIKLIK YOK\n");
$new=substr($new,0,$p).$js.substr($new,$p);
echo "[1] ✓ window.chAppView global tanimlandi\n";

/* ── [2] App dallarinin basina cagri ── */
$call=' /*CH_APPVIEW2C*/try{ if(window.chAppView && window.chAppView(html)){ return; } }catch(_av){}';
$c2=0;$c3=0;
$new=str_replace('if(_isWv2){','if(_isWv2){'.$call,$new,$c2);
$new=str_replace('if(_isWv3){','if(_isWv3){'.$call,$new,$c3);
echo "[2] ✓ cagri eklendi: _isWv2=$c2 yer, _isWv3=$c3 yer\n";
if($c2+$c3<1) exit("  ✗ hicbir App dali bulunamadi — DEGISIKLIK YOK\n");

$tmp=tempnam(sys_get_temp_dir(),'av2').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0) exit("\n✗ LINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n");

@file_put_contents($file.'.bak-appview2-'.date('Ymd-His'),$src);
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();

$chk=(string)@file_get_contents($file);
echo "\n✓ TAMAM (".strlen($src)." -> ".strlen($chk)." bayt)\n";
echo "  CH_APPVIEW2=".substr_count($chk,'CH_APPVIEW2')."\n";
echo "\nTEST: App TAM kapat-ac -> dilekce -> 'Kostenlos selbst ausdrucken'\n";
echo "  -> Belge TAM EKRAN acilmali (PDF / Teilen / Kopieren / Schliessen).\n";
