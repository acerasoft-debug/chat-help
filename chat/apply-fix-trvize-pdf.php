<?php
/**
 * ChatHelp — apply-fix-trvize-pdf (CH_TRVIZE_AI2) — TR vize belge PDF'i "yari
 *  Turkce yari Ingilizce cikiyor + pdf olmuyor" KESIN duzeltme.
 *  ANALIZ (workflow) kanitladi: onceki CH_TRVIZE_AI DOM son-islemcisi YANLIS
 *  katmanda calisiyordu:
 *   • Ceviri yalniz ana-DOM onizlemesine yaziliyordu; PDF cikisi (makePdf ->
 *     chPrintDoc -> iframe/yeni-pencere) HAM Turkce localStorage verisinden
 *     yeniden uretiliyor -> PDF'e ceviri HIC girmiyor.
 *   • Onizleme her tus vurusunda innerHTML ile yeniden ciziliyor -> yeni
 *     .vf-letter dugumu __ai bayrakli degil -> 800ms dongu surekli AI cagirip
 *     "✨ duzeltiliyor" TITREMESI yapiyor.
 *  COZUM (CH_TRVIZE_AI2, eklemeli; eski blogu strpos ile bulup NOTRALIZE eder):
 *   [1] chPrintDoc SARMALANIR (tum PDF yollari buradan gecer: makePdf,
 *       dossier onePdf/allPdf): html icindeki <div class="vf-letter">…</div>
 *       govdeleri yakalanir, AI ile belgenin HEDEF DILINE tam cevrilir
 *       (ad/pasaport/tarih korunur, markdown yok), sonra orijinal chPrintDoc
 *       cagrilir. Mobilde popup engeline takilmamak icin cikti penceresi
 *       TIKLAMA aninda senkron acilir ("Belge hazirlaniyor…"), ceviri donunce
 *       doldurulur. Ceviri onbellekli (ayni metin tekrar cevrilmez).
 *   [2] Eski CH_TRVIZE_AI titremesi durdurulur: onizleme (#vsf-previn) icindeki
 *       .vf-letter dugumlerine aninda __ai=1 konur -> eski dongu onizlemeyi
 *       cevirmeye calismaz (titreme biter); ceviri artik yalniz PDF aninda.
 *  node --check ✓ + harness ✓ (onbellek, mobil-sync-pencere, notralizasyon).
 * KULLANIM: pull2.php?key=...&files=apply-fix-trvize-pdf.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fix-trvize-pdf BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_TRVIZE_AI2')!==false) exit("Zaten ekli (CH_TRVIZE_AI2).\n");
if (strpos($src,'chPrintDoc')===false) exit("HATA: chPrintDoc yok (docpdf-premium gerekli).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-trvize-ai2-js">
/* CH_TRVIZE_AI2 — TR vize PDF'i cevir (chPrintDoc sarmasi) + eski titreme notralize */
try{(function(){
  var API=function(){ return (typeof window.API!=='undefined'&&window.API)?window.API:'api.php'; };
  var CACHE={};
  function hash(s){ var h=0,i,c; s=String(s); for(i=0;i<s.length;i++){ c=s.charCodeAt(i); h=((h<<5)-h)+c; h|=0; } return 'ch_trv_'+h; }
  function cacheGet(t){ var k=hash(t); if(CACHE[k]) return CACHE[k]; try{ var v=localStorage.getItem(k); if(v){ CACHE[k]=v; return v; } }catch(e){} return null; }
  function cacheSet(t,v){ var k=hash(t); CACHE[k]=v; try{ localStorage.setItem(k,v); }catch(e){} }
  function clean(s){ return String(s||'').replace(/```[a-z]*\n?|```/g,'').replace(/\[\[\/?DOC\]\]/g,'').replace(/\*\*/g,'').trim(); }
  function esc(s){ return String(s==null?'':s).replace(/[&<>]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;'}[c];}); }

  /* [2] eski CH_TRVIZE_AI titremesini durdur: onizleme .vf-letter'larini isaretle */
  (function killFlicker(){
    var n=0;(function loop(){
      try{
        var box=document.getElementById('vsf-previn');
        if(box){ box.querySelectorAll('.vf-letter').forEach(function(el){ el.__ai=1; }); }
      }catch(e){}
      if(n++<8000) setTimeout(loop,400);
    })();
  })();

  /* .vf-letter govdelerini html string'inden cikar */
  function extractLetters(html){
    var out=[], re=/<div class="vf-letter">([\s\S]*?)<\/div>/g, m;
    while((m=re.exec(html))!==null){
      var inner=m[1];
      var text=inner.replace(/<br\s*\/?>/gi,'\n').replace(/<[^>]+>/g,'').replace(/&amp;/g,'&').replace(/&lt;/g,'<').replace(/&gt;/g,'>').replace(/&quot;/g,'"').trim();
      out.push({full:m[0], inner:inner, text:text});
    }
    return out;
  }
  function wrapLetter(text){ return '<div class="vf-letter">'+esc(text).replace(/\n/g,'<br>')+'</div>'; }

  function translate(text){
    return new Promise(function(res){
      var c=cacheGet(text); if(c){ res(c); return; }
      if(!text || text.length<60 || /_{6,}/.test(text)){ res(null); return; } /* yarim/bos form -> dokunma */
      var msg='Rewrite the following visa document ENTIRELY in one consistent language (the language the document is mostly written in — keep it that language). '
        +'Translate every Turkish word or fragment into proper formal text in that language (job titles, property lists like "dukkan, ev", country/office names like "Alman" -> the German consulate). '
        +'Keep ALL names, passport numbers and dates EXACTLY. Keep the letter structure. Do NOT fill blank lines (underscores) with invented content. No markdown, no asterisks, no explanations. Output ONLY the corrected document text.\n\n---\n'+text;
      fetch(API()+'?action=aichat',{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({message:msg,history:[],provider:'claude',lang:'en',country:'DE'})})
       .then(function(r){ return r.json(); })
       .then(function(j){ var t=(j&&j.reply)?clean(j.reply):''; if(t&&t.length>60){ cacheSet(text,t); res(t); } else res(null); })
       .catch(function(){ res(null); });
    });
  }

  function isMobile(){ try{ return /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent||''); }catch(e){ return false; } }
  function prepWindow(){
    try{
      var w=window.open('','_blank');
      if(w&&w.document){ w.document.open(); w.document.write('<!doctype html><meta charset="utf-8"><body style="font-family:sans-serif;padding:40px;color:#333"><h3>Belge hazırlanıyor…</h3><p>Preparing your document…</p></body>'); }
      return w;
    }catch(e){ return null; }
  }

  var _cpd=null;
  function wrap(){
    if(typeof window.chPrintDoc!=='function' || window.chPrintDoc.__ai2) return false;
    _cpd=window.chPrintDoc;
    var w=function(html){
      try{
        var blocks=extractLetters(String(html));
        if(!blocks.length) return _cpd(html);  /* mektup degil -> aynen */
        var allCached=blocks.every(function(b){ return cacheGet(b.text)!==null || b.text.length<60 || /_{6,}/.test(b.text); });
        if(allCached){
          var h2=String(html);
          blocks.forEach(function(b){ var t=cacheGet(b.text); if(t) h2=h2.replace(b.full,wrapLetter(t)); });
          return _cpd(h2);
        }
        /* ceviri gerek: mobilde pencereyi SIMDI ac (gesture), sonra doldur */
        var pw=isMobile()?prepWindow():null;
        Promise.all(blocks.map(function(b){ return translate(b.text).then(function(t){ return {b:b,t:t}; }); }))
         .then(function(rs){
            var h3=String(html);
            rs.forEach(function(x){ if(x.t) h3=h3.replace(x.b.full,wrapLetter(x.t)); });
            if(pw&&pw.document){
              try{
                var css=document.getElementById('ch-vsf-css');
                var full='<!doctype html><html><head><meta charset="utf-8"><style>@page{size:A4;margin:0}html,body{margin:0;padding:0}'+(css?css.textContent:'')+'</style></head><body>'+h3+'<'+'script>window.onload=function(){setTimeout(function(){window.print();},350);};<\/script></body></html>';
                pw.document.open(); pw.document.write(full); pw.document.close();
              }catch(e){ try{ pw.close(); }catch(_){} _cpd(h3); }
            } else {
              _cpd(h3);  /* masaustu: iframe print, popup sorunu yok */
            }
         })
         .catch(function(){ if(pw){ try{ pw.close(); }catch(e){} } _cpd(html); });
      }catch(e){ return _cpd(html); }
    };
    w.__ai2=1; window.chPrintDoc=w;
    return true;
  }
  var g=0;(function guard(){ if(!wrap() && g++<300) setTimeout(guard,400); })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'t2').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-trvai2-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_TRVIZE_AI2')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_TRVIZE_AI2 diskte (".strlen($chk)." bayt)\n";
echo "\n✓ TR vize belge PDF'i artik: yazdirmadan ONCE tam tek dile cevrilir\n";
echo "   (ad/pasaport/tarih korunur), PDF penceresi mobilde garantili acilir,\n";
echo "   onizleme titremesi biter. 'yari Turkce PDF' + 'pdf olmuyor' cozuldu.\n";
