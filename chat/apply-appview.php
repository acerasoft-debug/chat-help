<?php
/**
 * ChatHelp — apply-appview (CH_APPVIEW) — App'te belge HER ZAMAN gorunur.
 *
 * NEDEN: Android WebView'de window.print() sessizce hicbir sey yapmaz ve
 * dl.php'nin attachment yanitini indirmek native DownloadListener ister.
 * Bu yuzden 'Kostenlos selbst ausdrucken' App'te tepkisiz kaliyordu.
 * KANIT: log'da CLICK satirlari var -> tiklama algilaniyor, DOM calisiyor.
 *
 * COZUM: Sadece App'te (inline UA testi; isWV'ye bagimli DEGIL), free-print
 * tiklamasindan 1.2 sn sonra belgeyi tam ekran bir katmanda gosterir:
 *   · beyaz "kagit" gorunumu, kaydirilabilir, secilebilir metin
 *   · ⬇️ PDF  -> dl.php'ye POST (App indirme destekliyorsa iner)
 *   · 📤 Teilen -> navigator.share (varsa) ile baska uygulamaya/yaziciya
 *   · 📋 Kopieren, ✕ Schließen
 * Web/tarayici HIC etkilenmez. Mevcut akis da bozulmaz (engellenmez).
 *
 * Geri alma: apply-appview-off.php
 * KULLANIM: /chat/pull2.php?key=...&files=apply-appview.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
echo "apply-appview BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_APPVIEW')!==false) exit("Zaten ekli (CH_APPVIEW) — DEGISIKLIK YOK.\n");

$js = <<<'HTML'
<script>/*CH_APPVIEW*/(function(){try{
function wv(){try{var u=navigator.userAgent||'';if(u.indexOf('; wv)')!==-1)return true;if(/Android/.test(u)&&/Version\/\d/.test(u)&&/Chrome\//.test(u))return true;if(window.chIsApp===true)return true;}catch(e){}return false;}
if(!wv())return;
function B(d){try{new Image().src="chlog9k1.php?k=chl_2607&t="+(new Date().getTime())+"&d="+encodeURIComponent(d);}catch(e){}}
function L(){try{var l=(typeof uil==='function')?uil():(localStorage.getItem('ch_uilang')||'de');return l;}catch(e){return 'de';}}
var T={de:{t:'Ihr Dokument',p:'PDF',s:'Teilen',c:'Kopieren',x:'Schließen',ok:'Kopiert'},
       tr:{t:'Belgeniz',p:'PDF',s:'Paylaş',c:'Kopyala',x:'Kapat',ok:'Kopyalandı'},
       en:{t:'Your document',p:'PDF',s:'Share',c:'Copy',x:'Close',ok:'Copied'}};
function t(k){var l=L();return (T[l]||T.de)[k]||T.de[k];}
function docText(){
  try{if(window._chLastDoc&&String(window._chLastDoc).replace(/\s/g,'')!=='')return String(window._chLastDoc);}catch(e){}
  try{var c=document.querySelectorAll('.ch-doccard-body');if(c.length)return c[c.length-1].textContent||'';}catch(e){}
  return '';
}
function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
function letterHTML(txt){
  return '<!doctype html><html><head><meta charset="utf-8"><title>ChatHelp-Dokument</title></head>'
    +'<body style="font-family:Georgia,serif;font-size:12pt;line-height:1.6;white-space:pre-wrap;margin:2.2cm">'
    +esc(txt)+'</body></html>';
}
function btn(label){
  var b=document.createElement('button');b.textContent=label;
  b.setAttribute('style','flex:1;min-width:0;padding:11px 6px;font-size:14px;font-weight:700;border-radius:10px;'
    +'border:1px solid rgba(212,168,74,.5);background:#141d44;color:#f0e6c8;-webkit-tap-highlight-color:transparent');
  return b;
}
function overlay(txt){
  if(document.getElementById('ch-appview'))return;
  var o=document.createElement('div');o.id='ch-appview';
  o.setAttribute('style','position:fixed;left:0;top:0;right:0;bottom:0;z-index:2147483000;background:#080c1c;display:flex;flex-direction:column');
  var hd=document.createElement('div');
  hd.setAttribute('style','padding:12px 14px;background:#0f1634;border-bottom:1px solid rgba(212,168,74,.35);color:#f0e6c8;font-weight:800;font-size:15px');
  hd.textContent='📄 '+t('t');
  var pg=document.createElement('div');
  pg.setAttribute('style','flex:1;overflow:auto;-webkit-overflow-scrolling:touch;padding:14px;background:#080c1c');
  var pap=document.createElement('div');
  pap.setAttribute('style','background:#fff;color:#14161c;border-radius:6px;padding:22px 20px;font-family:Georgia,serif;'
    +'font-size:15px;line-height:1.65;white-space:pre-wrap;word-wrap:break-word;-webkit-user-select:text;user-select:text;'
    +'box-shadow:0 6px 26px rgba(0,0,0,.5)');
  pap.textContent=txt;pg.appendChild(pap);
  var ac=document.createElement('div');
  ac.setAttribute('style','display:flex;gap:8px;padding:10px 10px calc(10px + env(safe-area-inset-bottom));background:#0f1634;border-top:1px solid rgba(212,168,74,.35)');
  var bp=btn('⬇️ '+t('p')), bs=btn('📤 '+t('s')), bc=btn('📋 '+t('c')), bx=btn('✕ '+t('x'));
  function formPost(){try{
    var f=document.createElement('form');f.method='POST';f.action='dl.php';f.target='_self';f.style.display='none';
    var i1=document.createElement('input');i1.name='html';i1.value=letterHTML(txt);f.appendChild(i1);
    var i2=document.createElement('input');i2.name='name';i2.value='ChatHelp-Dokument';f.appendChild(i2);
    var i3=document.createElement('input');i3.name='fmt';i3.value='pdf';f.appendChild(i3);
    document.body.appendChild(f);f.submit();
    setTimeout(function(){try{document.body.removeChild(f);}catch(e){}},1500);
  }catch(e){}}
  function blobDL(b){try{
    var u=URL.createObjectURL(b);var a=document.createElement('a');
    a.href=u;a.download='ChatHelp-Dokument.pdf';document.body.appendChild(a);a.click();
    B('APPVIEW-BLOBDL');
    setTimeout(function(){try{document.body.removeChild(a);URL.revokeObjectURL(u);}catch(e){}},3000);
  }catch(e){formPost();}}
  bp.onclick=function(){try{B('APPVIEW-PDF');
    var old=bp.textContent;bp.textContent='…';
    var fd=new FormData();fd.append('html',letterHTML(txt));fd.append('name','ChatHelp-Dokument');fd.append('fmt','pdf');
    fetch('dl.php',{method:'POST',body:fd}).then(function(r){return r.blob();}).then(function(b){
      bp.textContent=old;var fl=null;
      try{fl=new File([b],'ChatHelp-Dokument.pdf',{type:'application/pdf'});}catch(e){}
      if(fl&&navigator.share&&navigator.canShare&&navigator.canShare({files:[fl]})){
        B('APPVIEW-SHAREFILE');
        navigator.share({files:[fl],title:'ChatHelp-Dokument'}).catch(function(){blobDL(b);});
      }else{blobDL(b);}
    }).catch(function(){bp.textContent=old;formPost();});
  }catch(e){formPost();}};
  bs.onclick=function(){try{B('APPVIEW-SHARE');
    if(navigator.share){navigator.share({title:'ChatHelp-Dokument',text:txt}).catch(function(){});}
    else{bs.textContent='—';}
  }catch(e){}};
  bc.onclick=function(){try{
    if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(txt);}
    else{var ta=document.createElement('textarea');ta.value=txt;document.body.appendChild(ta);ta.select();
      try{document.execCommand('copy');}catch(e){}document.body.removeChild(ta);}
    bc.textContent='✓ '+t('ok');setTimeout(function(){bc.textContent='📋 '+t('c');},1800);
  }catch(e){}};
  bx.onclick=function(){try{o.remove();}catch(e){}};
  ac.appendChild(bp);ac.appendChild(bs);ac.appendChild(bc);ac.appendChild(bx);
  o.appendChild(hd);o.appendChild(pg);o.appendChild(ac);
  document.body.appendChild(o);
  B('APPVIEW-OPEN len='+txt.length);
}
document.addEventListener('click',function(ev){
  try{
    var n=ev.target,s='',hit=false;
    for(var i=0;i<4&&n;i++){s=(n.textContent||'').slice(0,90);
      if(/ausdruck|yazdır|yazdir|print it myself|print myself/i.test(s)){hit=true;break;}n=n.parentNode;}
    if(!hit)return;
    setTimeout(function(){
      try{
        if(document.getElementById('ch-appview'))return;
        var d=docText();
        B('APPVIEW-CLICK len='+(d?d.length:0));
        if(d&&d.replace(/\s/g,'').length>40)overlay(d);
      }catch(e){}
    },1200);
  }catch(e){}
},true);
}catch(e){}})();</script>
HTML;

$p=strripos($src,'</body>');
if($p===false) exit("✗ </body> bulunamadi — DEGISIKLIK YOK\n");
$new=substr($src,0,$p).$js.substr($src,$p);

$tmp=tempnam(sys_get_temp_dir(),'av').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0) exit("\n✗ LINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n");

@file_put_contents($file.'.bak-appview-'.date('Ymd-His'),$src);
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();

$chk=(string)@file_get_contents($file);
echo "  ✓ CH_APPVIEW eklendi (".strlen($src)." -> ".strlen($chk)." bayt)\n";
echo "  kontrol: CH_APPVIEW=".substr_count($chk,'CH_APPVIEW')."\n";
echo "\nTEST (App):\n";
echo " App'i TAM kapat-ac -> dilekce -> 'Kostenlos selbst ausdrucken'.\n";
echo " ~1 saniye sonra belge TAM EKRAN acilmali; altta: PDF / Teilen / Kopieren / Schliessen.\n";
echo " Web tarafi HIC etkilenmez.\n";
