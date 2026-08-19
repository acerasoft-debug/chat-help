<?php
/**
 * ChatHelp — apply-foto-grup (CH_FOTO_GRUP) — COKLU BELGEDE TEK CEVAP:
 *  Arka arkaya yuklenen belgelerin (2-8) analizleri toplanir; yukleme
 *  durduktan 3,5 sn sonra TEK AI cagrisiyla butunlesik degerlendirilir:
 *  • AYNI KONUYSA: tek birlesik cevap — olay nedir, taraflar, TUM
 *    sureler/tutarlar birlestirilmis, adim adim ne yapilmali.
 *  • FARKLI KONULARSA: kisa gruplama (hangi belge hangi konu).
 *  Sonuc panelin EN USTUNDE 🧩 altin kartta gosterilir, secenek dugmeleri
 *  (cevap/itiraz yaz · soru sor) butun dosyayi kapsar; ayrica kalici belge
 *  hafizasina (ch_docmem) islenir -> chat butunu hatirlar.
 *  Bagimsiz eklemeli katman: mevcut analiz akisina DOKUNMAZ (fetch gozlem).
 *  node ✓ + harness ✓.
 * KULLANIM: pull2.php?key=...&files=apply-foto-grup.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-foto-grup BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_FOTO_GRUP')!==false) exit("Zaten ekli (CH_FOTO_GRUP).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-foto-grup-js">
/* CH_FOTO_GRUP — coklu belge ayni konuysa TEK butunlesik cevap */
try{(function(){
  var API=function(){ return (typeof window.API!=='undefined'&&window.API)?window.API:'api.php'; };
  function UIL(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  function LNG(){ return {de:'Deutsch',tr:'Türkçe',en:'English',fr:'Français',es:'Español',it:'Italiano'}[UIL()]||'English'; }
  function summOf(j){
    try{
      var r=(j&&(j.result||j.ocr))||{}; if(typeof r==='string') return r.slice(0,350);
      var L=[];
      if(r.erkannt||r.doc) L.push(r.erkannt||r.doc);
      var s=r.zusammenfassung||r.summary||''; if(s) L.push((''+s).slice(0,300));
      if(r.behoerde||r.absender||r.firma) L.push('Gonderen: '+(r.behoerde||r.absender||r.firma));
      if(r.aktenzeichen||r.az) L.push('No: '+(r.aktenzeichen||r.az));
      if(r.frist) L.push('Frist/Sure: '+r.frist);
      if(r.betrag) L.push('Tutar: '+r.betrag);
      return L.join(' | ');
    }catch(e){ return ''; }
  }
  var BURST=[], tm=null, busy=false;
  function schedule(){ try{ clearTimeout(tm); }catch(e){} tm=setTimeout(evaluate,3500); }
  function evaluate(){
    if(busy) return;
    var items=BURST.slice(); BURST=[];
    if(items.length<2) return;   /* tek belge -> tekil ozet yeterli */
    busy=true;
    var lang=UIL();
    var head={tr:'🧩 Bütünleşik değerlendirme ('+items.length+' belge)',de:'🧩 Gesamtbewertung ('+items.length+' Dokumente)',en:'🧩 Combined assessment ('+items.length+' documents)'}[lang]||('🧩 '+items.length);
    var wait={tr:'⏳ Belgeler birlikte değerlendiriliyor…',de:'⏳ Dokumente werden zusammen ausgewertet…',en:'⏳ Evaluating documents together…'}[lang]||'⏳';
    var card=render(head,wait,null);
    var msg='Kullanıcı arka arkaya '+items.length+' belge yükledi. Aşağıda her belgenin analiz özeti var.\n'
      +'GÖREV: Önce belgelerin AYNI konuya/olaya/davaya ait olup olmadığına karar ver.\n'
      +'• AYNI KONUYSA: Belgeleri BİRLEŞTİREREK TEK bütünleşik değerlendirme yaz: 1) Olay nedir (tek cümle) 2) Taraflar/kurumlar 3) TÜM önemli süreler ve tutarlar (belge belge değil, birleşik liste) 4) Adım adım ne yapılmalı (en acil önce). \n'
      +'• FARKLI KONULARSA: "Belgeler farklı konulara ait" de ve her grubu 1-2 cümleyle özetle.\n'
      +'Cevabı '+LNG()+' dilinde, madde madde, en fazla 15 satır yaz. Markdown yıldızı kullanma.\n\nBELGELER:\n'
      +items.map(function(s,i){ return (i+1)+') '+s; }).join('\n');
    fetch(API()+'?action=aichat',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({message:msg,history:[],provider:'claude',lang:lang})})
     .then(function(r){ return r.json(); })
     .then(function(j){
        busy=false;
        var t=(j&&j.reply)?String(j.reply).replace(/```[a-z]*\n?|```/g,'').replace(/\*\*/g,'').trim():'';
        if(!t){ update(card,'⚠ '+((j&&j.error)||'Değerlendirme alınamadı')); return; }
        update(card,t,true);
        /* butunlesik sonucu kalici hafizaya da isle */
        try{
          var a=JSON.parse(localStorage.getItem('ch_docmem')||'[]'); if(!Array.isArray(a)) a=[];
          var d=new Date(); var ts=('0'+d.getDate()).slice(-2)+'.'+('0'+(d.getMonth()+1)).slice(-2)+'.'+d.getFullYear();
          a.push({t:ts,s:'BÜTÜNLEŞİK DEĞERLENDİRME ('+items.length+' belge): '+t.slice(0,500)});
          localStorage.setItem('ch_docmem',JSON.stringify(a.slice(-8)));
        }catch(e){}
     })
     .catch(function(){ busy=false; update(card,'⚠ Bağlantı hatası — belgeler yine de tek tek analiz edildi.'); });
  }
  function render(head,body,ok){
    try{
      var list=document.getElementById('fp-list'); if(!list) return null;
      var old=document.getElementById('fp-grup'); if(old) old.remove();
      var it=document.createElement('div'); it.id='fp-grup'; it.className='fp-it';
      it.setAttribute('style','border:2px solid rgba(212,168,74,.75);background:linear-gradient(135deg,rgba(212,168,74,.14),rgba(212,168,74,.03))');
      var bd=document.createElement('div'); bd.className='bd';
      var st=document.createElement('div'); st.className='st'; st.textContent=head;
      var sm=document.createElement('div'); sm.className='sm'; sm.textContent=body;
      var ops=document.createElement('div'); ops.className='ops'; ops.style.display='none';
      bd.appendChild(st); bd.appendChild(sm); bd.appendChild(ops);
      var ic=document.createElement('div');
      ic.setAttribute('style','width:64px;height:64px;border-radius:10px;flex:0 0 auto;border:1px solid rgba(212,168,74,.5);display:flex;align-items:center;justify-content:center;font-size:30px;background:#1a1a30');
      ic.textContent='🧩';
      it.appendChild(ic); it.appendChild(bd);
      list.insertBefore(it,list.firstChild);
      return {sm:sm,ops:ops};
    }catch(e){ return null; }
  }
  function toChat(msg){
    try{
      var i=document.getElementById('inp');
      if(i){ i.value=msg; try{ if(typeof onInp==='function') onInp(i); }catch(e){}
        var ov=document.getElementById('fp-ov'); if(ov) ov.classList.remove('on');
        i.focus();
        var sent=false;
        ['sendMsg','doSend','chSend'].forEach(function(fn){ if(!sent){ try{ if(typeof window[fn]==='function'){ window[fn](); sent=true; } }catch(e){} } });
      }
    }catch(e){}
  }
  function update(card,text,withOps){
    if(!card){ return; }
    try{
      card.sm.textContent=text;
      if(withOps){
        card.ops.innerHTML=''; card.ops.style.display='';
        var lang=UIL();
        var b1=document.createElement('button');
        b1.textContent={tr:'✍️ Bütün dosyaya cevap/itiraz yaz',de:'✍️ Antwort/Widerspruch zum Gesamtfall',en:'✍️ Reply/objection for the whole case'}[lang]||'✍️';
        b1.onclick=function(){ toChat(({tr:'Yüklediğim belgelerin bütünleşik değerlendirmesine göre uygun resmi cevap/itiraz dilekçesini hazırla: ',de:'Verfasse auf Basis der Gesamtbewertung meiner Dokumente die passende formelle Antwort/den Widerspruch: ',en:'Based on the combined assessment of my documents, draft the appropriate formal reply/objection: '}[lang]||'')+text.slice(0,900)); };
        var b2=document.createElement('button');
        b2.textContent={tr:'❓ Bütün dosya hakkında soru sor',de:'❓ Frage zum Gesamtfall',en:'❓ Ask about the whole case'}[lang]||'❓';
        b2.onclick=function(){ toChat(({tr:'Yüklediğim belgelerin bütünü hakkında sorum: ',de:'Zu meinen hochgeladenen Dokumenten insgesamt: ',en:'About all my uploaded documents: '}[lang]||'')); };
        card.ops.appendChild(b1); card.ops.appendChild(b2);
      }
    }catch(e){}
  }
  /* analiz yanitlarini gozle (mevcut akisa dokunmadan) */
  var _f=window.fetch;
  window.fetch=function(url,opt){
    var p=_f.apply(window,arguments);
    try{
      if((''+url).indexOf('action=analyze_photo')!==-1 && p && p.then){
        p=p.then(function(resp){
          try{
            var cl=resp.clone();
            cl.json().then(function(j){
              try{ var s=summOf(j); if(s){ BURST.push(s); if(BURST.length>8) BURST=BURST.slice(-8); schedule(); } }catch(e){}
            }).catch(function(){});
          }catch(e){}
          return resp;
        });
      }
    }catch(e){}
    return p;
  };
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'fg').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-fotogrup-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_FOTO_GRUP')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_FOTO_GRUP diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Coklu belgede TEK CEVAP aktif:\n";
echo "   • 2-8 belge arka arkaya yuklenince, yukleme durduktan 3,5 sn sonra\n";
echo "     hepsi BIRLIKTE degerlendirilir.\n";
echo "   • Ayni konuysa: panelin ustunde 🧩 TEK birlesik cevap (olay + tum\n";
echo "     sureler/tutarlar + adim adim plan) + butun dosyaya cevap/soru dugmeleri.\n";
echo "   • Farkli konularsa: kisa gruplama.\n";
echo "   • Birlesik sonuc kalici hafizaya islenir — chat butunu hatirlar.\n";
