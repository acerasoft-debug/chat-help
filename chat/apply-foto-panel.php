<?php
/**
 * ChatHelp — apply-foto-panel (CH_FOTO_PANEL) — Ana sayfaya ozel FOTO BOLUMU:
 *  "📸 Belge & Foto Merkezi" karti (hero'nun hemen altina) + tam ekran panel:
 *  [1] 8'e kadar belge: 📷 KAMERAYLA CEK veya 🖼️ galeriden/dosyadan YUKLE
 *      (coklu secim); her biri aninda analize gider (kanitli backend yolu:
 *      dogrudan analyze_photo).
 *  [2] Gelen her belge KULLANICININ DILINDE ozetlenir (lang=UIL()) — tur,
 *      gonderen, tarih, dosya no, SURE, tutar + ozet karti.
 *  [3] Her belge icin SECENEKLER sorulur: ✍️ Cevap/Itiraz yaz · 🌐 Ayrintili
 *      acikla · ❓ Soru sor · 📅 Sure/plan — secim sohbete aktarilir (mesaj
 *      hazir yazilir; mumkunse otomatik gonderilir).
 *  [4] HAFIZA: analizler mevcut belge-hafizasina (ch_docmem) islenir -> chat
 *      bu belgeleri sohbet boyu UNUTMAZ (CH_FOTO_MULTI baglami + profil).
 *  Tek eklemeli </body> blogu; mevcut 📎 akisina dokunmaz. node ✓ + harness ✓.
 * KULLANIM: pull2.php?key=...&files=apply-foto-panel.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-foto-panel BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_FOTO_PANEL')!==false) exit("Zaten ekli (CH_FOTO_PANEL).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-foto-panel-css">
#fp-card{display:flex;align-items:center;gap:13px;margin:10px 0 14px;padding:14px 16px;border-radius:15px;cursor:pointer;background:linear-gradient(135deg,rgba(212,168,74,.18),rgba(212,168,74,.05));border:1.5px solid rgba(212,168,74,.45)}
#fp-card .t1{font-size:14.5px;font-weight:800;color:var(--t1,#fff)}
#fp-card .t2{font-size:11.5px;color:var(--t3,#9a9ab0);margin-top:3px;line-height:1.45}
#fp-ov{position:fixed;inset:0;z-index:2147483200;background:linear-gradient(180deg,#0b0b16,#07070e);display:none;flex-direction:column;font-family:'Inter',system-ui,sans-serif;color:#f2f2fb}
#fp-ov.on{display:flex}
#fp-ov .top{display:flex;align-items:center;gap:12px;padding:13px 16px;border-bottom:1px solid rgba(255,255,255,.08);background:#0d0d1c}
#fp-ov .top h2{font-size:16px;font-weight:800;flex:1}
#fp-ov .top h2 em{font-style:normal;color:#e8c874}
#fp-ov .x{background:#1a1a30;border:1px solid rgba(255,255,255,.12);color:#c8c8e0;border-radius:9px;padding:8px 12px;font-size:13px;cursor:pointer;font-family:inherit}
#fp-bd{flex:1;overflow-y:auto;padding:16px;max-width:720px;width:100%;margin:0 auto;box-sizing:border-box}
.fp-btns{display:flex;gap:10px;margin-bottom:14px}
.fp-btn{flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;padding:16px 10px;border-radius:14px;cursor:pointer;background:#12122a;border:1.5px solid rgba(212,168,74,.4);font-weight:800;font-size:13px;text-align:center}
.fp-btn span{font-size:26px}
.fp-note{font-size:11.5px;color:#9a9ab0;margin-bottom:14px;line-height:1.5;text-align:center}
.fp-it{background:#12122a;border:1px solid rgba(212,168,74,.28);border-radius:14px;padding:12px;margin-bottom:12px;display:flex;gap:12px}
.fp-it img{width:64px;height:64px;object-fit:cover;border-radius:10px;flex:0 0 auto;border:1px solid rgba(255,255,255,.12)}
.fp-it .bd{flex:1;min-width:0}
.fp-it .st{font-size:12px;font-weight:800;color:#e8c874}
.fp-it .sm{font-size:12.5px;color:#e7e7ef;line-height:1.55;margin-top:5px;white-space:pre-wrap;word-break:break-word}
.fp-it .ops{display:flex;flex-wrap:wrap;gap:7px;margin-top:9px}
.fp-it .ops button{background:#1a1a30;border:1px solid rgba(212,168,74,.4);color:#e8c874;border-radius:9px;padding:7px 10px;font-size:11.5px;font-weight:800;cursor:pointer;font-family:inherit}
</style>
<script id="ch-foto-panel-js">
/* CH_FOTO_PANEL — ana sayfa Belge & Foto Merkezi: kamera/yukleme(8) + dilinde ozet + secenekler + hafiza */
try{(function(){
  var LIM=8;
  var API=function(){ return (typeof window.API!=='undefined'&&window.API)?window.API:'api.php'; };
  function UIL(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  function T(k){
    var L={
      card1:{de:'📸 Dokument & Foto-Zentrum',tr:'📸 Belge & Foto Merkezi',en:'📸 Document & Photo Center'},
      card2:{de:'Bis zu 8 Dokumente fotografieren oder hochladen — sofort in Ihrer Sprache erklärt, mit Handlungsoptionen. Der Chat vergisst sie nie.',tr:'8’e kadar belgeyi çekin veya yükleyin — anında sizin dilinizde açıklanır, seçenekler sunulur. Chat bunları asla unutmaz.',en:'Photograph or upload up to 8 documents — instantly explained in your language, with action options. The chat never forgets them.'},
      cam:{de:'Foto aufnehmen',tr:'Fotoğraf çek',en:'Take photo'},
      up:{de:'Hochladen (bis 8)',tr:'Yükle (8’e kadar)',en:'Upload (up to 8)'},
      note:{de:'Jedes Dokument wird sofort analysiert und in Ihrer Sprache zusammengefasst. Alle Dokumente bleiben dem Chat dauerhaft bekannt.',tr:'Her belge anında analiz edilir ve dilinizde özetlenir. Tüm belgeler sohbet boyunca hatırlanır.',en:'Each document is analyzed instantly and summarized in your language. All documents stay known to the chat.'},
      wait:{de:'⏳ Wird analysiert…',tr:'⏳ Analiz ediliyor…',en:'⏳ Analyzing…'},
      done:{de:'✓ Analysiert',tr:'✓ Analiz edildi',en:'✓ Analyzed'},
      err:{de:'⚠ Analyse fehlgeschlagen — erneut versuchen',tr:'⚠ Analiz başarısız — tekrar deneyin',en:'⚠ Analysis failed — try again'},
      op1:{de:'✍️ Antwort/Widerspruch',tr:'✍️ Cevap/İtiraz yaz',en:'✍️ Write reply/objection'},
      op2:{de:'🌐 Ausführlich erklären',tr:'🌐 Ayrıntılı açıkla',en:'🌐 Explain in detail'},
      op3:{de:'❓ Frage stellen',tr:'❓ Soru sor',en:'❓ Ask a question'},
      op4:{de:'📅 Frist & Plan',tr:'📅 Süre & Plan',en:'📅 Deadline & plan'},
      lim:{de:'Max. 8 Dokumente pro Sitzung.',tr:'Oturum başına en fazla 8 belge.',en:'Max 8 documents per session.'},
      sent:{de:'Im Chat geöffnet — senden Sie die Nachricht ab.',tr:'Sohbete aktarıldı — mesajı gönderin.',en:'Moved to chat — press send.'}
    };
    var l=UIL(); return (L[k]&&(L[k][l]||L[k].en))||'';
  }
  function toast(s){ try{ var t=document.createElement('div'); t.setAttribute('style','position:fixed;left:50%;bottom:92px;transform:translateX(-50%);z-index:2147483400;background:rgba(15,22,52,.97);color:#f0e6c8;border:1px solid rgba(212,168,74,.5);border-radius:12px;padding:10px 16px;font-size:13px;font-weight:700;max-width:88vw;text-align:center'); t.textContent=s; document.body.appendChild(t); setTimeout(function(){ try{ t.remove(); }catch(e){} },3200); }catch(e){} }
  var CNT=0;

  /* hafizaya isle (CH_FOTO_MULTI ch_docmem formati) */
  function remember(s){
    try{
      var a=JSON.parse(localStorage.getItem('ch_docmem')||'[]'); if(!Array.isArray(a)) a=[];
      var d=new Date();
      var ts=('0'+d.getDate()).slice(-2)+'.'+('0'+(d.getMonth()+1)).slice(-2)+'.'+d.getFullYear()+' '+('0'+d.getHours()).slice(-2)+':'+('0'+d.getMinutes()).slice(-2);
      a.push({t:ts,s:s}); localStorage.setItem('ch_docmem',JSON.stringify(a.slice(-LIM)));
    }catch(e){}
  }
  function summOf(j){
    try{
      var r=(j&&(j.result||j.ocr))||{}; if(typeof r==='string') return r.slice(0,400);
      var L=[];
      if(r.erkannt||r.doc) L.push((r.erkannt||r.doc));
      var s=r.zusammenfassung||r.summary||''; if(s) L.push((''+s).slice(0,340));
      if(r.behoerde||r.absender||r.firma) L.push('⚑ '+(r.behoerde||r.absender||r.firma));
      if(r.aktenzeichen||r.az||r.kundennummer) L.push('№ '+(r.aktenzeichen||r.az||r.kundennummer));
      if(r.frist) L.push('⏰ '+r.frist);
      if(r.betrag) L.push('€ '+r.betrag);
      return L.join('\n');
    }catch(e){ return ''; }
  }
  function toChat(msg){
    try{
      var i=document.getElementById('inp');
      if(i){ i.value=msg; try{ if(typeof onInp==='function') onInp(i); }catch(e){}
        var ov=document.getElementById('fp-ov'); if(ov) ov.classList.remove('on');
        i.focus();
        /* mumkunse otomatik gonder */
        var sent=false;
        ['sendMsg','doSend','chSend'].forEach(function(fn){ if(!sent){ try{ if(typeof window[fn]==='function'){ window[fn](); sent=true; } }catch(e){} } });
        if(!sent){ try{ var sb=document.getElementById('sb')||document.querySelector('#send,.ch-send'); if(sb&&sb.click){ sb.click(); sent=true; } }catch(e){} }
        if(!sent) toast(T('sent'));
      }
    }catch(e){}
  }

  function analyze(file,itemBd,st,sm,ops){
    var fr=new FileReader();
    fr.onload=function(ev){
      var b64=(''+ev.target.result).split(',')[1];
      fetch(API()+'?action=analyze_photo',{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({b64:b64,mime:file.type||'image/jpeg',lang:UIL()})})
       .then(function(r){ return r.json(); })
       .then(function(j){
          var s=summOf(j);
          if(!s || (j&&j.error)){ st.textContent=T('err'); return; }
          st.textContent=T('done');
          sm.textContent=s;
          remember(s);
          ops.style.display='';
          ops.__sum=s.replace(/\n/g,' | ');
       })
       .catch(function(){ st.textContent=T('err'); });
    };
    fr.onerror=function(){ st.textContent=T('err'); };
    fr.readAsDataURL(file);
  }

  function addItem(file){
    if(CNT>=LIM){ toast(T('lim')); return; }
    CNT++;
    var list=document.getElementById('fp-list'); if(!list) return;
    var it=document.createElement('div'); it.className='fp-it';
    var img=document.createElement('img');
    try{ img.src=URL.createObjectURL(file); }catch(e){}
    var bd=document.createElement('div'); bd.className='bd';
    var st=document.createElement('div'); st.className='st'; st.textContent=T('wait');
    var sm=document.createElement('div'); sm.className='sm';
    var ops=document.createElement('div'); ops.className='ops'; ops.style.display='none';
    var mk=function(label,build){ var b=document.createElement('button'); b.textContent=label;
      b.onclick=function(){ toChat(build(ops.__sum||'')); }; return b; };
    ops.appendChild(mk(T('op1'),function(s){ return {de:'Verfasse eine passende formelle Antwort/einen Widerspruch zu diesem Dokument: ',tr:'Bu belgeye uygun resmi bir cevap/itiraz dilekçesi hazırla: ',en:'Draft a formal reply/objection to this document: '}[UIL()]+s; }));
    ops.appendChild(mk(T('op2'),function(s){ return {de:'Erkläre dieses Dokument ausführlich, Punkt für Punkt, und was es für mich bedeutet: ',tr:'Bu belgeyi ayrıntılı, madde madde açıkla ve benim için ne anlama geldiğini söyle: ',en:'Explain this document in detail, point by point, and what it means for me: '}[UIL()]+s; }));
    ops.appendChild(mk(T('op3'),function(s){ return {de:'Zu meinem hochgeladenen Dokument ('+s.slice(0,120)+') habe ich folgende Frage: ',tr:'Yüklediğim belgeyle ('+s.slice(0,120)+') ilgili sorum şu: ',en:'About my uploaded document ('+s.slice(0,120)+') my question is: '}[UIL()]; }));
    ops.appendChild(mk(T('op4'),function(s){ return {de:'Welche Fristen gelten bei diesem Dokument und was soll ich Schritt für Schritt tun? ',tr:'Bu belgede hangi süreler geçerli, adım adım ne yapmalıyım? ',en:'What deadlines apply to this document and what should I do step by step? '}[UIL()]+s; }));
    bd.appendChild(st); bd.appendChild(sm); bd.appendChild(ops);
    it.appendChild(img); it.appendChild(bd);
    list.insertBefore(it,list.firstChild);
    analyze(file,bd,st,sm,ops);
  }
  function onFiles(fl){
    if(!fl) return;
    for(var i=0;i<fl.length && i<LIM;i++){ if(/^image\//.test(fl[i].type||'')) addItem(fl[i]); }
  }

  function ovEl(){
    var o=document.getElementById('fp-ov');
    if(o) return o;
    o=document.createElement('div'); o.id='fp-ov';
    o.innerHTML='<div class="top"><h2>📸 <em>'+T('card1').replace('📸 ','')+'</em></h2><button class="x" id="fp-x">✕</button></div>'
      +'<div id="fp-bd"><div class="fp-btns">'
      +'<label class="fp-btn"><span>📷</span>'+T('cam')+'<input id="fp-cam" type="file" accept="image/*" capture="environment" style="display:none"></label>'
      +'<label class="fp-btn"><span>🖼️</span>'+T('up')+'<input id="fp-up" type="file" accept="image/*" multiple style="display:none"></label>'
      +'</div><div class="fp-note">'+T('note')+'</div><div id="fp-list"></div></div>';
    document.body.appendChild(o);
    document.getElementById('fp-x').onclick=function(){ o.classList.remove('on'); };
    document.getElementById('fp-cam').addEventListener('change',function(ev){ onFiles(ev.target.files); try{ ev.target.value=''; }catch(e){} });
    document.getElementById('fp-up').addEventListener('change',function(ev){ onFiles(ev.target.files); try{ ev.target.value=''; }catch(e){} });
    return o;
  }
  window.chFotoPanel=function(){ ovEl().classList.add('on'); };

  /* ana sayfa karti: kategori grid'inin ustune */
  var n=0;(function inject(){
    try{
      if(!document.getElementById('fp-card')){
        var grid=document.querySelector('.wcat-grid');
        var host=grid?grid.parentNode:null;
        if(host){
          var c=document.createElement('div'); c.id='fp-card';
          c.innerHTML='<span style="font-size:30px">📸</span><span style="flex:1"><span class="t1">'+T('card1')+'</span><span class="t2" style="display:block">'+T('card2')+'</span></span><span style="font-size:18px;color:#d4a84a">›</span>';
          c.onclick=function(){ window.chFotoPanel(); };
          host.insertBefore(c,grid);
        }
      }
    }catch(e){}
    if(n++<300) setTimeout(inject,700);
  })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'fp').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-fotopanel-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_FOTO_PANEL')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_FOTO_PANEL diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Ana sayfada '📸 Belge & Foto Merkezi' karti:\n";
echo "   • 📷 kamerayla cek VEYA 🖼️ 8'e kadar yukle — her biri ANINDA analiz\n";
echo "   • Ozet KULLANICININ DILINDE (tur, gonderen, no, SURE, tutar)\n";
echo "   • Secenekler: Cevap/Itiraz · Ayrintili acikla · Soru sor · Sure&Plan\n";
echo "   • Tum belgeler chat hafizasina islenir — sohbet boyu unutulmaz\n";
