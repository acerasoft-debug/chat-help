<?php
/**
 * ChatHelp — apply-verlauf-recent3 (CH_VERLAUF_RECENT3) — Verlauf altindaki
 *  son 3 konusmayi DAHA BELIRGIN + kesin gorunur yapar. v2 JS blogu v3 ile
 *  degistirilir (robust localStorage taramasi + tik->ac), ustune yuksek-kontrast
 *  premium stil eklenir (net "Letzte" grubu, altin sol-cizgi, okunur renk).
 * KULLANIM: pull2.php?key=...&files=apply-verlauf-recent3.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-verlauf-recent3 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_VERLAUF_RECENT3')!==false) exit("Zaten ekli (CH_VERLAUF_RECENT3).\n");

$newjs = <<<'JSBLOCK'
<script id="ch-verlauf-recent-js">
/* CH_VERLAUF_RECENT3 — son 3 konusma Verlauf ALTINDA (belirgin, robust) */
try{(function(){
  function findSessions(){
    try{ var k=window.chSessKey; if(k){ var a=JSON.parse(localStorage.getItem(k)||'[]'); if(a&&a.length) return a; } }catch(e){}
    try{ for(var i=0;i<localStorage.length;i++){ var kk=localStorage.key(i); var v=localStorage.getItem(kk);
      if(!v||v.charAt(0)!=='[') continue;
      try{ var arr=JSON.parse(v); if(Array.isArray(arr)&&arr.length&&arr[0]&&typeof arr[0]==='object'&&('messages' in arr[0])&&(('title' in arr[0])||('id' in arr[0]))) return arr; }catch(e){}
    } }catch(e){}
    return [];
  }
  function loadOne(s){ try{ if(typeof window.chLoadSession==='function'){ window.chLoadSession(s); return; } }catch(e){} try{ if(typeof gP==='function') gP('verlauf'); }catch(e){} }
  function ago(t){ try{ var d=Math.floor((Date.now()-new Date(t).getTime())/60000); if(isNaN(d)||d<0) return ''; if(d<1)return 'jetzt'; if(d<60)return d+' Min'; var h=Math.floor(d/60); if(h<24)return h+' Std'; return Math.floor(h/24)+' T'; }catch(e){ return ''; } }
  function render(){
    try{
      var nav=document.getElementById('nav-verlauf'); if(!nav||!nav.parentNode) return;
      var ss=findSessions().slice().sort(function(a,b){ return (new Date(b.updated||0))-(new Date(a.updated||0)); }).slice(0,3);
      var sig=ss.map(function(s){ return (s.id||s.title||'')+':'+(s.updated||''); }).join('|');
      var old=document.getElementById('ch-verlauf-recent');
      if(old && old.getAttribute('data-sig')===sig && old.previousSibling===nav) return;
      if(old) old.remove();
      if(!ss.length) return;
      var box=document.createElement('div'); box.id='ch-verlauf-recent'; box.className='ch-vr-recent'; box.setAttribute('data-sig',sig);
      ss.forEach(function(s){
        var it=document.createElement('div'); it.className='ch-vr-item';
        var tt=(s.title||(s.messages&&s.messages[0]&&String(s.messages[0].content||'').slice(0,44))||'…');
        var t=ago(s.updated);
        it.innerHTML='<span class="ch-vr-t"></span>'+(t?'<span class="ch-vr-a"></span>':'');
        it.querySelector('.ch-vr-t').textContent=tt; if(t) it.querySelector('.ch-vr-a').textContent=t;
        it.title=tt;
        it.addEventListener('click',function(ev){ ev.stopPropagation(); loadOne(s); });
        box.appendChild(it);
      });
      nav.parentNode.insertBefore(box, nav.nextSibling);
    }catch(e){}
  }
  render(); try{ setInterval(render,1200); }catch(e){}
})();}catch(e){}
</script>
JSBLOCK;

$pat='~<script id="ch-verlauf-recent-js">.*?</script>~s';
$m=preg_match($pat,$src);
if($m!==1){ echo "  ✗ ch-verlauf-recent-js blogu $m kez (1 beklenir) — DEGISTIRILMEDI\n"; exit; }
$src=preg_replace($pat,$newjs,$src,1);

/* belirgin stil (eskisini ezer) */
$css='<style id="ch-verlauf-recent-css2">'
  .'.ch-vr-recent{margin:2px 0 8px;display:flex;flex-direction:column;gap:1px}'
  .'.ch-vr-item{display:flex;align-items:center;gap:8px;font-size:12.5px;color:#cdd2e6;padding:7px 10px 7px 30px;margin-left:6px;border-left:2px solid rgba(212,168,74,.28);border-radius:0 8px 8px 0;cursor:pointer;transition:background-color .15s ease,color .15s ease,border-color .15s ease}'
  .'.ch-vr-item .ch-vr-t{flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}'
  .'.ch-vr-item .ch-vr-a{font-size:10.5px;color:#8a90ab;flex-shrink:0}'
  .'.ch-vr-item:hover{background:rgba(212,168,74,.1);color:#fff;border-left-color:var(--gold,#d4a84a)}'
  .'</style>';
$pos=strripos($src,'</body>');
if($pos===false) exit("</body> yok\n");
$src=substr($src,0,$pos).$css."\n".substr($src,$pos);

$tmp=tempnam(sys_get_temp_dir(),'v3').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-vr3-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_VERLAUF_RECENT3')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ CH_VERLAUF_RECENT3 (belirgin) yerlestirildi (".strlen($chk)." B)\n";
echo "✓ Verlauf altinda son 3 konusma: altin sol-cizgi, okunur renk, sag'da 'vor X' zaman.\n";
echo "\n>>> Gormek icin app'i TAM KAPAT ve 2 kez ac (SW v4 cache reset).\n";
