<?php
/**
 * ChatHelp — apply-verlauf-recent2 (CH_VERLAUF_RECENT2) — son 3 sohbet Verlauf
 *  nav ogesinin TAM ALTINA (robust). Onceki surum window.chSessKey expose'una
 *  bagliydi; bu surum oturumlari DOGRUDAN localStorage'i tarayarak bulur
 *  (expose olmasa da calisir), Verlauf'un hemen altina sabitler, kopsa yeniden
 *  baglar, baslik yoksa ilk mesajdan turetir.
 *  Mevcut <script id="ch-verlauf-recent-js"> blogu tumuyle degistirilir (cift
 *  calisma yok). CSS blogu (ch-verlauf-recent-css) korunur.
 * KULLANIM: pull2.php?key=...&files=apply-verlauf-recent2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-verlauf-recent2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_VERLAUF_RECENT2')!==false) exit("Zaten ekli (CH_VERLAUF_RECENT2).\n");

$new = <<<'JSBLOCK'
<script id="ch-verlauf-recent-js">
/* CH_VERLAUF_RECENT2 — son 3 konusma nav-verlauf ALTINDA (robust) */
try{(function(){
  function findSessions(){
    try{ var k=window.chSessKey; if(k){ var a=JSON.parse(localStorage.getItem(k)||'[]'); if(a&&a.length) return a; } }catch(e){}
    try{
      for(var i=0;i<localStorage.length;i++){ var kk=localStorage.key(i); var v=localStorage.getItem(kk);
        if(!v||v.charAt(0)!=='[') continue;
        try{ var arr=JSON.parse(v);
          if(Array.isArray(arr)&&arr.length&&arr[0]&&typeof arr[0]==='object'&&('messages' in arr[0])&&(('title' in arr[0])||('id' in arr[0]))) return arr;
        }catch(e){}
      }
    }catch(e){}
    return [];
  }
  function loadOne(s){
    try{ if(typeof window.chLoadSession==='function'){ window.chLoadSession(s); return; } }catch(e){}
    try{ if(typeof gP==='function') gP('verlauf'); }catch(e){}
  }
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
        var tt=(s.title||(s.messages&&s.messages[0]&&String(s.messages[0].content||'').slice(0,42))||'…');
        it.textContent=tt; it.title=tt;
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
if($m!==1){ echo "  ✗ eski ch-verlauf-recent-js blogu $m kez (1 beklenir) — DEGISTIRILMEDI\n"; exit; }
$src=preg_replace($pat,$new,$src,1);

$tmp=tempnam(sys_get_temp_dir(),'v2').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-vrecent2-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_VERLAUF_RECENT2')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ CH_VERLAUF_RECENT2 (robust) yerlestirildi (".strlen($chk)." B)\n";
echo "✓ Son 3 konusma artik Verlauf'un TAM ALTINDA; localStorage taramasi -> expose'suz da calisir.\n";
