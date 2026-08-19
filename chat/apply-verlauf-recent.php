<?php
/**
 * ChatHelp — apply-verlauf-recent (CH_VERLAUF_RECENT) — sol menude "Verlauf"
 *  nav ogesinin ALTINA son 3 konusma tiklanabilir listelenir; tiklayinca o
 *  konusma acilir (mevcut loadSession).
 *  [1] (str) CH_AICHAT_FE tick'ine loadSession + SK global expose eklenir
 *      (window.chLoadSession, window.chSessKey) — IIFE ici scope'tan disari.
 *  [2] (add) </body> oncesi: son 3 oturumu (updated desc) nav-verlauf altina
 *      cizen script + premium/sakin stil.
 *  Lint + dogrulama + opcache.
 * KULLANIM: pull2.php?key=...&files=apply-verlauf-recent.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-verlauf-recent BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_VERLAUF_RECENT')!==false) exit("Zaten ekli (CH_VERLAUF_RECENT).\n");

/* [1] loadSession + SK expose */
$anc='try{ window.__aiChatTurn=chAiChatTurn; }catch(e){}';
$c1=substr_count($src,$anc);
if($c1<1){ echo "  ✗ [1] tick anchor bulunamadi ($c1)\n"; exit; }
$src=str_replace($anc, $anc.' try{ window.chLoadSession=loadSession; window.chSessKey=SK; }catch(e){}', $src);
echo "  ✓ [1] loadSession + SK global expose (".$c1." yer)\n";

/* [2] recent-3 script + stil */
$block = <<<'HTMLBLOCK'
<style id="ch-verlauf-recent-css">
.ch-vr-recent{margin:1px 0 7px;display:flex;flex-direction:column}
.ch-vr-item{font-size:12px;color:var(--t3,#b4b4c0);padding:6px 10px 6px 34px;border-radius:8px;cursor:pointer;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;position:relative;transition:background-color .15s ease,color .15s ease}
.ch-vr-item::before{content:"›";position:absolute;left:20px;color:var(--gold,#d4a84a);opacity:.55}
.ch-vr-item:hover{background:rgba(212,168,74,.09);color:#fff}
</style>
<script id="ch-verlauf-recent-js">
/* CH_VERLAUF_RECENT — son 3 konusma nav-verlauf altinda */
try{(function(){
  function sessions(){ try{ var k=window.chSessKey; if(!k) return []; return JSON.parse(localStorage.getItem(k)||'[]')||[]; }catch(e){ return []; } }
  function render(){
    try{
      var nav=document.getElementById('nav-verlauf'); if(!nav||!nav.parentNode) return;
      var ss=sessions().slice().sort(function(a,b){ return (new Date(b.updated||0))-(new Date(a.updated||0)); }).slice(0,3);
      var sig=ss.map(function(s){ return (s.id||'')+':'+(s.updated||''); }).join('|');
      var old=document.getElementById('ch-verlauf-recent');
      if(old && old.getAttribute('data-sig')===sig) return;
      if(old) old.remove();
      if(!ss.length) return;
      var box=document.createElement('div'); box.id='ch-verlauf-recent'; box.className='ch-vr-recent'; box.setAttribute('data-sig',sig);
      ss.forEach(function(s){
        var it=document.createElement('div'); it.className='ch-vr-item';
        var tt=(s.title||'…'); it.textContent=tt; it.title=tt;
        it.addEventListener('click',function(ev){ ev.stopPropagation();
          try{ if(typeof window.chLoadSession==='function'){ window.chLoadSession(s); } else if(typeof gP==='function'){ gP('verlauf'); } }catch(e){}
        });
        box.appendChild(it);
      });
      nav.parentNode.insertBefore(box, nav.nextSibling);
    }catch(e){}
  }
  render();
  try{ setInterval(render, 1500); }catch(e){}
})();}catch(e){}
</script>
HTMLBLOCK;
$pos=strripos($src,'</body>');
if($pos===false) exit("</body> bulunamadi\n");
$src=substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp=tempnam(sys_get_temp_dir(),'vr').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-vrecent-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_VERLAUF_RECENT')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ CH_VERLAUF_RECENT eklendi (".strlen($chk)." B)\n";
echo "✓ Verlauf altinda son 3 konusma tiklanabilir listelenir (tik -> konusma acilir)\n";
