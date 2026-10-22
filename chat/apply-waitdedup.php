<?php
/**
 * ChatHelp — apply-waitdedup (CH_WAITDEDUP) — tekrar eden bekleme balonlarini gizler.
 *
 * SORUN: 'Bitte warten, Ihr Dokument wird erstellt …' her yoklamada YENI balon
 * olarak ekleniyor; ekran goruntusunde 7 kez alt alta. Belge sonunda uretiliyor,
 * yani islev degil GORUNUM sorunu.
 *
 * COZUM (tamamen EKLEME — mevcut hicbir koda dokunulmaz):
 *  · MutationObserver bekleme balonlarini izler.
 *  · Ayni metinden birden fazla varsa SONUNCUSU haric digerlerini GIZLER
 *    (display:none — SILMEZ; hicbir JS referansi kirilmaz).
 *  · 'Ihr Dokument ist fertig' / belge karti gelince TUM bekleme balonlarini gizler.
 *  · Yukari yurume 3 seviye ile sinirli ve yalniz kisa metinli (<300) kapsayicida
 *    durur; boylece sohbetin tamami asla gizlenemez.
 *
 * Geri alma: apply-waitdedup-off.php
 * KULLANIM: /chat/pull2.php?key=...&files=apply-waitdedup.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
echo "apply-waitdedup BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_WAITDEDUP')!==false) exit("Zaten ekli (CH_WAITDEDUP) — DEGISIKLIK YOK.\n");

$js = <<<'HTML'
<script>/*CH_WAITDEDUP*/(function(){try{
var WAIT=/(bitte warten|wird erstellt|hazırlanıyor|belgeniz olu|being created|wird vorbereitet)/i;
var DONE=/(ist fertig|hazır|is ready|✅)/i;
function leaves(rx){
  var out=[],all;
  try{ all=document.querySelectorAll('div,p,span,li,section'); }catch(e){ return out; }
  for(var i=0;i<all.length;i++){
    var el=all[i],tx='';
    try{ tx=el.textContent||''; }catch(e){ continue; }
    if(tx.length>300||!rx.test(tx)) continue;
    /* daha derinde eslesen cocuk varsa bu dugumu atla (en ictekini al) */
    var deeper=false;
    try{
      var kids=el.querySelectorAll('div,p,span,li');
      for(var j=0;j<kids.length;j++){
        var kt=kids[j].textContent||'';
        if(kt.length<=300&&rx.test(kt)){ deeper=true; break; }
      }
    }catch(e){}
    if(!deeper) out.push(el);
  }
  return out;
}
function rowOf(el){
  /* balon satirini bul: en fazla 3 seviye yukari, metin kisa kaldigi surece */
  var t=el;
  for(var i=0;i<3;i++){
    var p=t.parentNode;
    if(!p||p===document.body||p.nodeType!==1) break;
    var pt='';
    try{ pt=p.textContent||''; }catch(e){ break; }
    if(pt.length>300) break;
    t=p;
  }
  return t;
}
function hide(el){ try{ if(el&&el.style&&el.style.display!=='none'){ el.style.display='none'; el.setAttribute('data-ch-waithidden','1'); } }catch(e){} }
var busy=false;
function sweep(){
  if(busy) return; busy=true;
  try{
    var w=leaves(WAIT);
    if(w.length){
      var done=leaves(DONE).length>0;
      /* belge hazirsa hepsini gizle; degilse sonuncusu kalsin */
      var keep=done?-1:w.length-1;
      for(var i=0;i<w.length;i++){ if(i!==keep) hide(rowOf(w[i])); }
    }
  }catch(e){}
  busy=false;
}
var tmr=null;
function schedule(){ if(tmr) return; tmr=setTimeout(function(){ tmr=null; sweep(); },220); }
function start(){
  try{
    sweep();
    var mo=new MutationObserver(schedule);
    mo.observe(document.body,{childList:true,subtree:true,characterData:true});
  }catch(e){}
}
if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',start);
else start();
}catch(e){}})();</script>
HTML;

$p=strripos($src,'</body>');
if($p===false) exit("✗ </body> bulunamadi — DEGISIKLIK YOK\n");
$new=substr($src,0,$p).$js.substr($src,$p);

/* kritik isaretler korunuyor mu? */
foreach(['CH_ISWV_GLOBAL'=>1,'CH_APPVIEW3'=>1,'CH_APPVIEW2C'=>4] as $m=>$min){
  if(substr_count($new,$m)<$min) exit("✗ Kritik isaret kaybolurdu ($m) — DEGISIKLIK YOK\n");
}

$tmp=tempnam(sys_get_temp_dir(),'wd').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0) exit("\n✗ LINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n");

@file_put_contents($file.'.bak-waitdedup-'.date('Ymd-His'),$src);
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();

$chk=(string)@file_get_contents($file);
echo "✓ CH_WAITDEDUP eklendi (".strlen($src)." -> ".strlen($chk)." bayt)\n";
echo "  korunanlar: CH_ISWV_GLOBAL=".substr_count($chk,'CH_ISWV_GLOBAL')
    ."  CH_APPVIEW3=".substr_count($chk,'CH_APPVIEW3')
    ."  CH_APPVIEW2C=".substr_count($chk,'CH_APPVIEW2C')."\n";
echo "\nTEST: sohbette belge uret -> tek bekleme balonu kalmali, bitince o da kaybolmali.\n";
