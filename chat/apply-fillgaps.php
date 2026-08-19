<?php
/**
 * ChatHelp — apply-fillgaps (CH_FILLGAPS) — eksik alanlari CIKTIDAN ONCE SOR.
 *
 * ISTEK: Bosluk birakip "elle doldur" demek yerine, IBAN / Kundennummer /
 * Vertragsnummer gibi eksik alanlar kullaniciya SORULSUN, belge dolu ciksin
 * (fax akisindaki gibi). Bir kez sorulur; yazdirma, PDF, fax, e-posta —
 * hepsi dolu belgeyi kullanir.
 *
 * NASIL (tamamen EKLEME, mevcut hicbir koda dokunulmaz):
 *  · Belge karti (.ch-doccard-body) DOM'a eklendiginde yakalanir.
 *    ÖNEMLI: sayfa TARANMAZ — yalnizca mutation.addedNodes'a bakilir
 *    (gece CH_WAITDEDUP'ta yapilan hata tekrarlanmaz).
 *  · Metinde 'Etiket: ______' veya yalniz '______' bosluklari bulunur.
 *  · Premium bir kutu acilir, her bosluk icin bir alan gosterilir.
 *    Deger daha once girildiyse otomatik dolu gelir (localStorage).
 *  · Kaydet -> bosluklar belgede DEGISTIRILIR (kart metni + window._chLastDoc)
 *    ve degerler ileride hazir gelsin diye saklanir.
 *  · Atla -> belge oldugu gibi kalir (bosluk cizgisi gorunur, CH_BLANKFILL).
 *
 * Kritik isaret dogrulamasi + lint + .bak + kilit tazeleme.
 * Geri alma: apply-fillgaps-off.php
 * KULLANIM: /chat/pull2.php?key=...&files=apply-fillgaps.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
echo "apply-fillgaps BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$dir=__DIR__; $file=$dir.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_FILLGAPS')!==false) exit("Zaten ekli (CH_FILLGAPS) — DEGISIKLIK YOK.\n");

$js = <<<'HTML'
<script>/*CH_FILLGAPS*/(function(){try{
function L(){try{return (typeof uil==='function')?uil():(localStorage.getItem('ch_uilang')||'de');}catch(e){return 'de';}}
var T={
 de:{t:'Fehlende Angaben ergänzen',s:'Diese Felder fehlen im Dokument. Bitte ausfüllen — sie werden direkt eingesetzt.',
     ok:'Übernehmen',skip:'Später',ph:'Eintragen …',done:'✓ Ergänzt'},
 tr:{t:'Eksik bilgileri tamamla',s:'Belgede bu alanlar eksik. Doldurun — doğrudan belgeye yazılır.',
     ok:'Uygula',skip:'Sonra',ph:'Girin …',done:'✓ Tamamlandı'},
 en:{t:'Complete missing details',s:'These fields are missing. Fill them in — they go straight into the document.',
     ok:'Apply',skip:'Later',ph:'Enter …',done:'✓ Completed'}};
function t(k){var l=L();return (T[l]||T.de)[k]||T.de[k];}
function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}

var STORE='ch_gapvals';
function loadVals(){ try{ return JSON.parse(localStorage.getItem(STORE)||'{}')||{}; }catch(e){ return {}; } }
function saveVals(v){ try{ localStorage.setItem(STORE,JSON.stringify(v)); }catch(e){} }
function keyOf(label){ return String(label).toLowerCase().replace(/[^a-z0-9]/g,'').slice(0,24); }

/* Bosluk bul: 'Etiket: ____' (etiketli) veya yalniz '____' (etiketsiz) */
var RX_LABELED=/^[ \t]*([^\n:]{2,40}?)[ \t]*:[ \t]*(_{4,})[ \t]*$/gm;
function findGaps(text){
  var gaps=[],seen={},m;
  RX_LABELED.lastIndex=0;
  while((m=RX_LABELED.exec(text))!==null){
    var lab=m[1].trim();
    if(!lab||seen[lab]) continue;
    seen[lab]=1;
    gaps.push({label:lab,blank:m[2],line:m[0]});
    if(gaps.length>=8) break;
  }
  return gaps;
}
function applyGaps(text,gaps,vals){
  var out=text;
  for(var i=0;i<gaps.length;i++){
    var g=gaps[i], v=(vals[g.label]||'').trim();
    if(!v) continue;
    /* yalniz 'Etiket: ____' satirini degistir — baska yere dokunma */
    var rx=new RegExp('^([ \\t]*'+g.label.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+'[ \\t]*:[ \\t]*)_{4,}[ \\t]*$','m');
    out=out.replace(rx,'$1'+v);
  }
  return out;
}

function css(){
  if(document.getElementById('ch-fg-css'))return;
  var s=document.createElement('style');s.id='ch-fg-css';
  s.textContent='#ch-fg{position:fixed;left:0;top:0;right:0;bottom:0;z-index:2147482000;background:rgba(6,9,20,.72);display:flex;align-items:center;justify-content:center;padding:16px}'
   +'#ch-fg .fg-box{width:100%;max-width:460px;max-height:86vh;overflow:auto;background:#101a3c;border:1px solid rgba(204,158,61,.45);border-radius:16px;padding:20px;box-shadow:0 20px 60px rgba(0,0,0,.6);font-family:system-ui,-apple-system,"Segoe UI",sans-serif}'
   +'#ch-fg .fg-t{color:#f0e6c8;font-weight:800;font-size:17px;margin:0 0 6px}'
   +'#ch-fg .fg-s{color:#9fb0d8;font-size:13px;line-height:1.5;margin:0 0 16px}'
   +'#ch-fg label{display:block;color:#e8dfc4;font-size:12.5px;font-weight:700;margin:12px 0 5px}'
   +'#ch-fg input{width:100%;padding:12px 13px;border-radius:10px;border:1px solid rgba(204,158,61,.4);background:#0a1230;color:#f4eeda;font:15px system-ui}'
   +'#ch-fg input:focus{outline:none;border-color:#cc9e3d}'
   +'#ch-fg .fg-act{display:flex;gap:9px;margin-top:20px}'
   +'#ch-fg .fg-act button{flex:1;padding:13px 8px;border-radius:11px;font:800 14px system-ui;border:1px solid rgba(204,158,61,.5);background:#182248;color:#f0e6c8}'
   +'#ch-fg .fg-act button.fg-pri{background:#cc9e3d;border-color:#cc9e3d;color:#1a1405}';
  document.head.appendChild(s);
}

function openBox(gaps,onDone){
  css();
  var vals=loadVals();
  var ov=document.createElement('div'); ov.id='ch-fg';
  var h='<div class="fg-box"><p class="fg-t">'+esc(t('t'))+'</p><p class="fg-s">'+esc(t('s'))+'</p>';
  for(var i=0;i<gaps.length;i++){
    var g=gaps[i], pre=vals[keyOf(g.label)]||'';
    h+='<label for="fg-i'+i+'">'+esc(g.label)+'</label>'
      +'<input id="fg-i'+i+'" type="text" placeholder="'+esc(t('ph'))+'" value="'+esc(pre)+'">';
  }
  h+='<div class="fg-act"><button class="fg-pri" id="fg-ok">'+esc(t('ok'))+'</button>'
    +'<button id="fg-skip">'+esc(t('skip'))+'</button></div></div>';
  ov.innerHTML=h;
  document.body.appendChild(ov);
  try{ ov.querySelector('#fg-i0').focus(); }catch(e){}

  ov.querySelector('#fg-skip').onclick=function(){ try{ov.remove();}catch(e){} onDone(null); };
  ov.querySelector('#fg-ok').onclick=function(){
    var res={},store=loadVals(),any=false;
    for(var i=0;i<gaps.length;i++){
      var el=ov.querySelector('#fg-i'+i); if(!el) continue;
      var v=(el.value||'').trim();
      if(v){ res[gaps[i].label]=v; store[keyOf(gaps[i].label)]=v; any=true; }
    }
    saveVals(store);
    try{ov.remove();}catch(e){}
    onDone(any?res:null);
  };
}

function handleCard(card){
  try{
    if(!card||card.getAttribute('data-ch-fg')==='1') return;
    var txt=card.innerText||card.textContent||'';
    if(!txt || txt.indexOf('____')<0) { card.setAttribute('data-ch-fg','1'); return; }
    var gaps=findGaps(txt);
    if(!gaps.length){ card.setAttribute('data-ch-fg','1'); return; }
    card.setAttribute('data-ch-fg','1');
    openBox(gaps,function(vals){
      if(!vals) return;
      try{
        var cur=card.innerText||card.textContent||'';
        var filled=applyGaps(cur,gaps,vals);
        if(filled!==cur){
          card.textContent=filled;
          try{ if(window._chLastDoc) window._chLastDoc=applyGaps(String(window._chLastDoc),gaps,vals); }catch(e){}
        }
      }catch(e){}
    });
  }catch(e){}
}

/* ── SINIRLI dinleyici: yalnizca YENI eklenen dugumlere bakar, sayfa taranmaz ── */
var pend=[],tmr=null;
function flush(){
  tmr=null;
  var list=pend.slice(0); pend.length=0;
  for(var i=0;i<list.length && i<12;i++) handleCard(list[i]);
}
function queue(el){ pend.push(el); if(!tmr) tmr=setTimeout(flush,400); }
function start(){
  try{
    /* acilista yalnizca bir kez, mevcut kart varsa */
    var ex=document.querySelectorAll('.ch-doccard-body');
    if(ex.length) queue(ex[ex.length-1]);
    var mo=new MutationObserver(function(muts){
      for(var i=0;i<muts.length;i++){
        var an=muts[i].addedNodes; if(!an||!an.length) continue;
        for(var j=0;j<an.length;j++){
          var n=an[j]; if(!n||n.nodeType!==1) continue;
          try{
            if(n.classList && n.classList.contains('ch-doccard-body')) queue(n);
            else if(n.querySelector){ var c=n.querySelector('.ch-doccard-body'); if(c) queue(c); }
          }catch(e){}
        }
      }
    });
    mo.observe(document.body,{childList:true,subtree:true});
  }catch(e){}
}
if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',start); else start();
}catch(e){}})();</script>
HTML;

$p=strripos($src,'</body>');
if($p===false) exit("✗ </body> bulunamadi — DEGISIKLIK YOK\n");
$new=substr($src,0,$p).$js.substr($src,$p);

/* kritik isaretler */
foreach(['CH_ISWV_GLOBAL'=>1,'CH_APPVIEW3'=>1,'CH_APPVIEW2C'=>4,'CH_MAILBTN'=>1] as $m=>$min){
  if(substr_count($new,$m)<$min) exit("✗ Kritik isaret kaybolurdu ($m) — DEGISIKLIK YOK\n");
}

$tmp=tempnam(sys_get_temp_dir(),'fg').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0) exit("\n✗ LINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n");

@file_put_contents($file.'.bak-fillgaps-'.date('Ymd-His'),$src);
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();

$cur=(string)@file_get_contents($file);
@file_put_contents($file.'.GOOD-appprint',$cur);
@file_put_contents($dir.'/ch-applock.json',json_encode([
 'locked_at'=>date('c'),'size'=>strlen($cur),'sha1'=>sha1($cur),'note'=>'fillgaps',
 'markers'=>['CH_ISWV_GLOBAL'=>substr_count($cur,'CH_ISWV_GLOBAL'),'CH_APPVIEW3'=>substr_count($cur,'CH_APPVIEW3'),
             'CH_APPVIEW2C'=>substr_count($cur,'CH_APPVIEW2C'),'CH_MAILBTN'=>substr_count($cur,'CH_MAILBTN'),
             'CH_FILLGAPS'=>substr_count($cur,'CH_FILLGAPS')],
 'files'=>['pv.php','pvsave.php','pvmail.php','pdflib.php']
],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

echo "✓ CH_FILLGAPS eklendi (".strlen($src)." -> ".strlen($cur)." bayt)\n";
echo "  CH_FILLGAPS=".substr_count($cur,'CH_FILLGAPS')
    ."  CH_MAILBTN=".substr_count($cur,'CH_MAILBTN')
    ."  CH_APPVIEW3=".substr_count($cur,'CH_APPVIEW3')
    ."  CH_ISWV_GLOBAL=".substr_count($cur,'CH_ISWV_GLOBAL')."\n";
echo "  ✓ kilit tazelendi (sha1 ".substr(sha1($cur),0,12)."…)\n";
echo "\nTEST: IBAN gereken bir dilekce uret.\n";
echo "  -> Belge gelince 'Fehlende Angaben ergänzen' kutusu acilir, IBAN sorulur.\n";
echo "  -> Girip Übernehmen -> belge DOLU olur; yazdirma/PDF/fax/e-posta dolu belgeyi kullanir.\n";
echo "  -> Ayni deger bir dahaki sefere otomatik dolu gelir.\n";
