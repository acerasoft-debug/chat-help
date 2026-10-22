<?php
/**
 * ChatHelp — apply-dominspect (CH_DOMINSPECT) — canli DOM teshis kutusu.
 *  Ekranda sag-altta "🔍 DOM" dugmesi. Dokununca:
 *   - "PDF/Speichern" butonunu bulur, UST KAPSAYICISININ HTML'ini gosterir
 *     (Senden butonunu nereye/nasil koyacagimi gormek icin).
 *   - Acik modal/overlay (position:fixed, yuksek z-index) HTML'ini gosterir
 *     (gonderim secici + versand radio yapisi).
 *   - input[name] listesini (radio isimleri) gosterir (Fax-primary icin).
 *  Mor, kaydirilir, dokun=kapat kutu. Ekran goruntusu al, bana at.
 *  SADECE gosterir — hicbir sey degistirmez.
 * KULLANIM: pull2.php?key=...&files=apply-dominspect.php  (bittiginde apply-dominspect-off ile kaldir)
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-dominspect BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_DOMINSPECT')!==false) exit("Zaten ekli (CH_DOMINSPECT).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-dominspect-js">
/* CH_DOMINSPECT — canli DOM teshis */
try{(function(){
  function cut(s,n){ s=String(s||''); return s.length>n? s.slice(0,n)+' …[+'+(s.length-n)+']':s; }
  function findPdfBtn(){
    var all=document.querySelectorAll('button,a'), r=[];
    for(var i=0;i<all.length;i++){ var t=(all[i].textContent||'').trim().toLowerCase();
      if(/pdf|speichern|senden|als pdf/.test(t) && all[i].offsetParent!==null) r.push(all[i]); }
    return r;
  }
  function openOverlays(){
    var all=document.querySelectorAll('div'), r=[];
    for(var i=0;i<all.length;i++){ var st=getComputedStyle(all[i]);
      if(st.position==='fixed' && st.display!=='none' && (parseInt(st.zIndex)||0)>=100 && all[i].offsetHeight>80) r.push(all[i]); }
    return r.slice(0,3);
  }
  function report(){
    var out='';
    var pdfs=findPdfBtn();
    out+='=== PDF/SENDEN BUTONLARI ('+pdfs.length+') ===\n';
    pdfs.slice(0,4).forEach(function(b,i){
      out+='['+i+'] <'+b.tagName.toLowerCase()+'> "'+cut(b.textContent.trim(),40)+'"\n';
      out+='   id='+(b.id||'-')+' class='+cut(b.className,60)+'\n';
      var par=b.parentNode;
      out+='   PARENT <'+(par?par.tagName.toLowerCase():'?')+'> class='+(par?cut(par.className,50):'')+'\n';
      out+='   PARENT.HTML: '+cut(par?par.innerHTML:'',500)+'\n\n';
    });
    var ovs=openOverlays();
    out+='=== ACIK OVERLAY/MODAL ('+ovs.length+') ===\n';
    ovs.forEach(function(o,i){ out+='['+i+'] id='+(o.id||'-')+' class='+cut(o.className,50)+'\n   HTML: '+cut(o.innerHTML,700)+'\n\n'; });
    var radios=document.querySelectorAll('input[type=radio],input[name]');
    var names={}; for(var i=0;i<radios.length;i++){ var n=radios[i].getAttribute('name'); if(n) names[n]=(names[n]||0)+1; }
    out+='=== input[name] (radio isimleri) ===\n';
    for(var k in names) out+='  '+k+' : '+names[k]+'\n';
    return out;
  }
  function show(){
    var d=document.createElement('div');
    d.style.cssText='position:fixed;left:6px;top:6px;right:6px;bottom:60px;z-index:2147483647;background:#1a0d1a;color:#ffd0ff;border:2px solid #a4a;border-radius:10px;padding:10px;font:10.5px/1.4 monospace;overflow:auto;white-space:pre-wrap';
    d.textContent='🔍 DOM (dokun=kapat)\n\n'+report();
    d.onclick=function(){ d.remove(); };
    document.body.appendChild(d);
  }
  function btn(){
    if(document.getElementById('ch-dominspect-b')) return;
    var b=document.createElement('button'); b.id='ch-dominspect-b'; b.textContent='🔍 DOM';
    b.style.cssText='position:fixed;right:10px;bottom:12px;z-index:2147483646;background:#a4a;color:#fff;border:none;border-radius:20px;padding:9px 13px;font-size:12px;font-weight:800;box-shadow:0 4px 14px rgba(0,0,0,.5)';
    b.onclick=function(ev){ ev.stopPropagation(); show(); };
    document.body.appendChild(b);
  }
  setInterval(btn,700);
})();}catch(e){}
</script>
HTMLBLOCK;

$pos=strripos($src,'</body>');
if($pos===false) exit("</body> yok\n");
$src=substr($src,0,$pos).$block."\n".substr($src,$pos);
$tmp=tempnam(sys_get_temp_dir(),'di').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-dominspect-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
echo "  ✓ CH_DOMINSPECT eklendi — sag-altta '🔍 DOM' dugmesi.\n";
echo "KULLAN: 1) Belge uret -> sonuc ekraninda '🔍 DOM'a dokun -> ekran goruntusu.\n";
echo "        2) 'PDF Speichern'e bas -> gonderim penceresi acilinca '🔍 DOM'a dokun -> ekran goruntusu.\n";
echo "Ikisini bana at; Senden + Fax-birincil + AI adres/fax yardimini kesin baglarim.\n";
