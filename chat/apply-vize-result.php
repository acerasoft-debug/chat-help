<?php
/**
 * ChatHelp — apply-vize-result (CH_VIZE_RESULT) — "bitti denip yan tarafta
 *  PDF olmadi" cozumu. KOK NEDEN: __vzGen basarili olunca makePDF()'i
 *  ASENKRON (fetch sonrasi) cagiriyor — tarayicilar kullanici jesti olmayan
 *  yazdirma/popup penceresini ENGELLER -> kullanici hicbir sey gormez.
 *  COZUM: Vize belge uretiminin CEVABI yakalanir (fetch response clone —
 *  mevcut akis hic bozulmaz) ve YAN TARAFTA kalici bir sonuc paneli acilir:
 *   • Belge metni panelde okunur halde
 *   • "📄 PDF olarak ac" butonu — TIKLAMA jestiyle makePDF cagrilir,
 *     popup engeline TAKILMAZ, her zaman calisir
 *   • "📋 Kopyala" + "Kapat"
 *  Vize cagrilari icerikten taninir ('Vize: ' + 'Başvuran' iceren aichat
 *  istekleri) — zamanlama/bayrak sorunu yok, CH_VIZE_FORMS'tan bagimsiz
 *  ve onunla uyumlu. Sadece EKLEMELI. node ✓ + 3 senaryoluk harness ✓.
 * KULLANIM: pull2.php?key=...&files=apply-vize-result.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-vize-result BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_VIZE_RESULT')!==false) exit("Zaten ekli (CH_VIZE_RESULT).\n");
if (strpos($src,'window.__vzGen=')===false) exit("HATA: Vize Asistani yok.\n");

$block = <<<'HTMLBLOCK'
<style id="ch-vize-result-css">
#vzr-dw{position:fixed;top:0;right:0;bottom:0;width:min(480px,94vw);z-index:2147483050;transform:translateX(105%);transition:transform .28s cubic-bezier(.4,0,.2,1);background:linear-gradient(180deg,#12122a,#0b0b19);border-left:1px solid rgba(212,168,74,.4);box-shadow:-18px 0 60px rgba(0,0,0,.55);display:flex;flex-direction:column;font-family:'Inter',system-ui,sans-serif;color:#f2f2fb}
#vzr-dw.on{transform:translateX(0)}
#vzr-hd{padding:16px 18px 12px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:flex-start;gap:10px}
#vzr-hd .t{flex:1;font-size:14.5px;font-weight:800;line-height:1.35}
#vzr-hd .x{width:34px;height:34px;border-radius:10px;border:1px solid rgba(255,255,255,.14);background:transparent;color:#c8c8e8;font-size:15px;cursor:pointer;flex:0 0 auto}
#vzr-bd{flex:1;overflow-y:auto;padding:16px 18px;font-size:13.5px;line-height:1.7;white-space:pre-wrap;color:#e8e8f4}
#vzr-ft{padding:12px 16px;border-top:1px solid rgba(255,255,255,.08);display:flex;gap:8px}
#vzr-ft button{flex:1;padding:12px;border-radius:11px;border:none;cursor:pointer;font-family:inherit;font-size:13px;font-weight:800}
#vzr-pdf{background:linear-gradient(135deg,#d4a84a,#ecc060);color:#191000}
#vzr-copy{background:transparent;border:1.5px solid rgba(255,255,255,.18)!important;color:#c8c8e8}
</style>
<script id="ch-vize-result-js">
/* CH_VIZE_RESULT — vize belge cevabini yakala, yan panelde goster, PDF jest-ile */
try{(function(){
  var LAST={label:'Vize Belgesi',cc:'DE',txt:''};
  function clean(s){
    return String(s||'').replace(/```[a-z]*\n?|```/g,'')
      .replace(/\[\[\/?DOC\]\]/g,'').trim();
  }
  function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
  function drawer(){
    var d=document.getElementById('vzr-dw');
    if(d) return d;
    d=document.createElement('div'); d.id='vzr-dw';
    d.innerHTML='<div id="vzr-hd"><div class="t" id="vzr-t"></div><button class="x" id="vzr-x">✕</button></div>'
      +'<div id="vzr-bd"></div>'
      +'<div id="vzr-ft"><button id="vzr-copy">📋 Kopyala</button><button id="vzr-pdf">📄 PDF olarak aç</button></div>';
    document.body.appendChild(d);
    document.getElementById('vzr-x').onclick=function(){ d.classList.remove('on'); };
    document.getElementById('vzr-copy').onclick=function(){
      try{
        if(navigator.clipboard&&navigator.clipboard.writeText){ navigator.clipboard.writeText(LAST.txt); }
        else{ var ta=document.createElement('textarea'); ta.value=LAST.txt; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove(); }
        var b=document.getElementById('vzr-copy'); if(b){ var o=b.textContent; b.textContent='✓ Kopyalandı'; setTimeout(function(){ try{ b.textContent=o; }catch(e){} },1600); }
      }catch(e){}
    };
    document.getElementById('vzr-pdf').onclick=function(){
      /* KULLANICI JESTI icinde -> popup engeline takilmaz */
      try{
        if(typeof window.makePDF==='function'){ window.makePDF(LAST.txt,LAST.label,LAST.cc); }
        else alert(LAST.txt);
      }catch(e){ alert(LAST.txt); }
    };
    return d;
  }
  function show(label,txt,cc){
    try{
      LAST={label:label||'Vize Belgesi',txt:txt,cc:cc||'DE'};
      var d=drawer();
      var t=document.getElementById('vzr-t'); if(t) t.textContent='📄 '+LAST.label;
      var b=document.getElementById('vzr-bd'); if(b) b.textContent=txt;
      d.classList.add('on');
    }catch(e){}
  }
  /* __vzGen'i sarmala: sadece belge ETIKETINI kaydet (zincir guvenli) */
  function wrap(){
    try{
      if(typeof window.__vzGen!=='function' || window.__vzGen.__vzr) return false;
      var _o=window.__vzGen;
      var w=function(gen,btn){
        try{
          var host=(btn&&btn.closest)?btn.closest('.vz-gen,.vz-cl-it'):null;
          if(host){ var nEl=host.querySelector('.n,.tx'); if(nEl&&nEl.textContent) LAST.label=nEl.textContent.replace(/\s+/g,' ').trim(); }
          else LAST.label='Vize Belgesi';
        }catch(e){}
        return _o.apply(this,arguments);
      };
      w.__vzr=1; window.__vzGen=w;
      return true;
    }catch(e){ return false; }
  }
  /* fetch cevabini klonla — vize uretim cagrilari icerikten taninir */
  (function(){
    var _f=window.fetch;
    window.fetch=function(u,opt){
      var isVz=false, cc='DE';
      try{
        if(typeof u==='string' && u.indexOf('action=aichat')!==-1 && opt && opt.body){
          var b=JSON.parse(opt.body);
          if(b && typeof b.message==='string' && b.message.indexOf('Vize: ')!==-1 && b.message.indexOf('Başvuran')!==-1){
            isVz=true; cc=b.country||'DE';
          }
        }
      }catch(e){}
      var pr=_f.apply(this,arguments);
      if(isVz && pr && pr.then){
        pr.then(function(resp){
          try{
            resp.clone().json().then(function(j){
              try{ var txt=(j&&j.reply)?clean(j.reply):''; if(txt) show(LAST.label,txt,cc); }catch(e){}
            }).catch(function(){});
          }catch(e){}
        }).catch(function(){});
      }
      return pr;
    };
  })();
  var n=0;(function loop(){ wrap(); if(n++<6000) setTimeout(loop,700); })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'vr').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-vizeresult-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_VIZE_RESULT')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_VIZE_RESULT diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Belge hazirlaninca artik YAN TARAFTA sonuc paneli acilir:\n";
echo "   belge metni gorunur, '📄 PDF olarak ac' butonu tiklama jestiyle\n";
echo "   calisir (popup engeline takilmaz), '📋 Kopyala' ile metin alinir.\n";
