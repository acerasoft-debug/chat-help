<?php
/**
 * ChatHelp — apply-themen-fix (CH_THEMEN5) — sol menu + Meine Themen duzeni:
 *  [1] Sol menudeki "Chat" girisi kaldirilir (metni tam 'Chat'/'💬 Chat' olan
 *      menu ogesi; ChatHelp logosu ETKILENMEZ — onun metni 'ChatHelp').
 *  [2] Konular (ch_topics) artik MEINE THEMEN panelinde listelenir (kendi
 *      bolumumuz, panelin ustunde); Meine Fälle panelindeki eski "📌 Meine
 *      Themen" blogu gizlenir -> konular yanlis yerde gorunmez.
 *  [3] Konuya TIKLAYINCA yeniden uretime GITMEZ: yapilan belge goruntulenir
 *      (kayitli belgelerden baslikla eslestirilir); yalniz "🔁 Überarbeiten"
 *      dugmesi eski uretim akisini acar.
 *  [4] Goruntuleyiciden "⚖️ Fälle'ye tasi" -> konu ch_falls_v1'e eklenir
 *      (Meine Fälle'de gorunur, cift kayit engellenir).
 *  [5] EMSAL koprusu: Emsal Karar sonuclarina "➕ Themen'e kaydet" dugmesi
 *      eklenir -> arastirma bir konu olarak Meine Themen'e girer; oradan
 *      "✍️ Dilekce hazirla" ile emsallere dayali yeni dilekce uretilir.
 *  Tek eklemeli </body> blogu; mevcut koda dokunmaz. node ✓ + harness ✓.
 * KULLANIM: pull2.php?key=...&files=apply-themen-fix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-themen-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_THEMEN5')!==false) exit("Zaten ekli (CH_THEMEN5).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-themen5-css">
#th-sec{margin:8px 14px 14px;padding:12px;background:var(--s2,#131325);border:1px solid rgba(212,168,74,.3);border-radius:13px}
#th-sec .tt{font-size:13.5px;font-weight:800;color:#e8c874;margin-bottom:8px;display:flex;align-items:center;gap:7px}
.th-item{display:flex;align-items:center;gap:9px;padding:9px 10px;border:1px solid rgba(255,255,255,.09);border-radius:10px;margin-bottom:7px;cursor:pointer;background:rgba(255,255,255,.03)}
.th-item:hover{border-color:rgba(212,168,74,.5)}
.th-item .tn{flex:1;font-size:12.5px;font-weight:700;color:#e9e9f5;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.th-item .td{font-size:10px;color:#8e8e9c;white-space:nowrap}
#th-sec .emp{font-size:12px;color:#9a9ab0;padding:6px 2px}
#th-view{position:fixed;inset:0;z-index:2147483350;background:rgba(4,4,12,.85);backdrop-filter:blur(4px);display:none;align-items:center;justify-content:center;padding:16px;font-family:'Inter',system-ui,sans-serif}
#th-view.on{display:flex}
#th-box{width:min(640px,96vw);max-height:88vh;display:flex;flex-direction:column;background:linear-gradient(180deg,#12122a,#0b0b19);border:1px solid rgba(212,168,74,.45);border-radius:16px;color:#f2f2fb;overflow:hidden}
#th-box .hd{display:flex;gap:10px;align-items:flex-start;padding:14px 16px 10px;border-bottom:1px solid rgba(255,255,255,.08)}
#th-box .hd .t{flex:1;font-size:14px;font-weight:800;line-height:1.35}
#th-box .hd .x{width:32px;height:32px;border-radius:9px;border:1px solid rgba(255,255,255,.14);background:transparent;color:#c8c8e8;cursor:pointer}
#th-body{flex:1;overflow-y:auto;padding:14px 16px;font-size:13px;line-height:1.7;white-space:pre-wrap}
#th-box .ft{padding:11px 13px;border-top:1px solid rgba(255,255,255,.08);display:flex;gap:8px;flex-wrap:wrap}
#th-box .ft button{flex:1;min-width:120px;padding:11px 8px;border-radius:10px;border:1px solid rgba(255,255,255,.16);background:#1a1a30;color:#d8d8ea;cursor:pointer;font-family:inherit;font-size:12px;font-weight:800}
#th-box .ft button.gold{background:linear-gradient(135deg,#d4a84a,#ecc060);color:#07070e;border:none}
</style>
<script id="ch-themen5-js">
/* CH_THEMEN5 — Chat girisi kaldir; konular Meine Themen'de; tikla=belge gor; uber=yeniden uret; Fälle tasi; Emsal koprusu */
try{(function(){
  var API=function(){ return (typeof window.API!=='undefined'&&window.API)?window.API:'api.php'; };
  function UIL(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  function CCX(){ try{ return ((typeof window.CC!=='undefined'&&window.CC)||'DE'); }catch(e){ return 'DE'; } }
  function J(k,d){ try{ var v=JSON.parse(localStorage.getItem(k)||'null'); return (v===null||v===undefined)?d:v; }catch(e){ return d; } }
  function W(k,v){ try{ localStorage.setItem(k,JSON.stringify(v)); }catch(e){} }
  function toast(s){ try{ var t=document.createElement('div'); t.setAttribute('style','position:fixed;left:50%;bottom:92px;transform:translateX(-50%);z-index:2147483500;background:rgba(15,22,52,.97);color:#f0e6c8;border:1px solid rgba(212,168,74,.5);border-radius:12px;padding:10px 16px;font-size:13px;font-weight:700;max-width:88vw;text-align:center'); t.textContent=s; document.body.appendChild(t); setTimeout(function(){ try{ t.remove(); }catch(e){} },3000); }catch(e){} }
  function T(k){
    var L={
      sec:{de:'📌 Meine Themen',tr:'📌 Konularım',en:'📌 My Topics'},
      emp:{de:'Noch keine Themen — nach jedem Dokument entsteht hier ein Thema.',tr:'Henüz konu yok — her belgeden sonra burada bir konu oluşur.',en:'No topics yet — a topic appears here after each document.'},
      pdf:{de:'📄 PDF',tr:'📄 PDF',en:'📄 PDF'},
      uber:{de:'🔁 Überarbeiten',tr:'🔁 Yeniden düzenle',en:'🔁 Revise'},
      fall:{de:'⚖️ Zu Meine Fälle',tr:'⚖️ Fälle\'ye taşı',en:'⚖️ Move to Fälle'},
      draft:{de:'✍️ Schriftsatz erstellen',tr:'✍️ Dilekçe hazırla',en:'✍️ Draft petition'},
      del:{de:'🗑',tr:'🗑',en:'🗑'},
      nodoc:{de:'(Kein gespeichertes Dokument gefunden — mit Überarbeiten neu erstellen.)',tr:'(Kayıtlı belge bulunamadı — Überarbeiten ile yeniden üretin.)',en:'(No saved document found — recreate via Revise.)'},
      moved:{de:'Nach „Meine Fälle" verschoben.',tr:'"Meine Fälle"ye taşındı.',en:'Moved to "Meine Fälle".'},
      already:{de:'Bereits in „Meine Fälle".',tr:'Zaten "Meine Fälle"de.',en:'Already in "Meine Fälle".'},
      saved:{de:'Als Thema gespeichert.',tr:'Konu olarak kaydedildi.',en:'Saved as topic.'},
      noub:{de:'Für dieses Thema ist keine Neuerstellung verknüpft.',tr:'Bu konuya bağlı yeniden üretim akışı yok.',en:'No recreation flow linked to this topic.'}
    };
    var l=UIL(); return (L[k]&&(L[k][l]||L[k].en))||'';
  }

  /* ── [1] sol menudeki 'Chat' girisini gizle (logo haric) ── */
  function chatKill(){
    try{
      var sb=document.getElementById('sb'); if(!sb) return;
      var els=sb.querySelectorAll('div,span,a,button');
      for(var i=0;i<els.length;i++){
        var e=els[i]; if(e.__chk) continue;
        var t=(e.textContent||'').trim();
        if((t==='Chat'||t==='💬 Chat'||t==='💬Chat') && e.children.length<=1){
          /* logo satiri degil mi? (logo kapsayicisinin metni 'ChatHelp') */
          var par=e.parentNode;
          if(par && /ChatHelp/.test((par.textContent||'').replace(/\s+/g,''))) { e.__chk=1; continue; }
          e.__chk=1; e.style.display='none';
        }
      }
    }catch(e){}
  }

  /* ── konu verisi ── */
  function topics(){
    var a=J('ch_topics',[]); if(!Array.isArray(a)) return [];
    return a.map(function(x,i){
      x=x||{};
      return { i:i, id:x.id||('t'+i),
        title:String(x.title||x.t||x.name||x.thema||'Thema'),
        txt:String(x.doc||x.text||x.txt||x.preview||x.content||''),
        dk:String(x.dk||x.key||x.k||''),
        ts:x.ts||x.time||0, raw:x };
    });
  }
  window.chThemenDebug=function(){ try{ alert(String(localStorage.getItem('ch_topics')||'(bos)').slice(0,900)); }catch(e){} };

  /* ── kayitli belge eslestirme (action=history) ── */
  var HIST=null, HISTts=0;
  function loadHist(cb){
    var now=new Date().getTime();
    if(HIST && now-HISTts<60000){ cb(HIST); return; }
    fetch(API()+'?action=history').then(function(r){ return r.json(); })
      .then(function(d){ HIST=(d&&d.docs)||[]; HISTts=new Date().getTime(); cb(HIST); })
      .catch(function(){ cb(HIST||[]); });
  }
  function norm(s){ return String(s||'').toLowerCase().replace(/[^a-zà-ž0-9ğüşıöç]+/gi,' ').trim(); }
  function matchDoc(title,docs){
    var nt=norm(title); if(!nt) return null;
    var best=null,bs=0;
    (docs||[]).forEach(function(d){
      var nd=norm(d.docName||d.name||'');
      if(!nd) return;
      var s=0;
      if(nd===nt) s=3; else if(nd.indexOf(nt)!==-1||nt.indexOf(nd)!==-1) s=2;
      else { var w=nt.split(' ').filter(function(x){return x.length>3;}); var hit=0; w.forEach(function(x){ if(nd.indexOf(x)!==-1) hit++; }); if(w.length&&hit>=Math.ceil(w.length/2)) s=1; }
      if(s>bs){ bs=s; best=d; }
    });
    return best;
  }

  /* ── goruntuleyici ── */
  var CUR=null;
  function viewer(){
    var v=document.getElementById('th-view'); if(v) return v;
    v=document.createElement('div'); v.id='th-view';
    v.innerHTML='<div id="th-box"><div class="hd"><div class="t" id="th-t"></div><button class="x" id="th-x">✕</button></div><div id="th-body"></div>'
      +'<div class="ft"><button class="gold" id="th-pdf">'+T('pdf')+'</button><button id="th-uber">'+T('uber')+'</button><button id="th-fall">'+T('fall')+'</button><button id="th-draft">'+T('draft')+'</button></div></div>';
    document.body.appendChild(v);
    v.onclick=function(e){ if(e.target===v) v.classList.remove('on'); };
    document.getElementById('th-x').onclick=function(){ v.classList.remove('on'); };
    document.getElementById('th-pdf').onclick=function(){
      if(!CUR) return;
      var tx=CUR.txt||document.getElementById('th-body').textContent||'';
      try{ if(typeof window.makePDF==='function') window.makePDF(tx,CUR.title,CCX()); else alert(tx); }catch(e){ alert(tx); }
    };
    document.getElementById('th-uber').onclick=function(){
      if(!CUR) return;
      v.classList.remove('on');
      var done=false;
      try{ if(CUR.dk && typeof window.qS==='function'){ window.qS(CUR.dk); done=true; } }catch(e){}
      if(!done){ try{ if(CUR.raw && CUR.raw.onresume && typeof CUR.raw.onresume==='function'){ CUR.raw.onresume(); done=true; } }catch(e){} }
      if(!done) toast(T('noub'));
    };
    document.getElementById('th-fall').onclick=function(){
      if(!CUR) return;
      var falls=J('ch_falls_v1',[]); if(!Array.isArray(falls)) falls=[];
      var exists=falls.some(function(f){ return norm(f.title)===norm(CUR.title); });
      if(exists){ toast(T('already')); return; }
      var tx=CUR.txt||document.getElementById('th-body').textContent||CUR.title;
      falls.push({id:'f'+new Date().getTime(),title:CUR.title,created:new Date().getTime(),messages:[{role:'user',content:tx.slice(0,4000)}]});
      W('ch_falls_v1',falls); toast(T('moved'));
    };
    document.getElementById('th-draft').onclick=function(){
      if(!CUR) return;
      v.classList.remove('on');
      var tx=CUR.txt||document.getElementById('th-body').textContent||'';
      var i=document.getElementById('inp');
      if(i){ i.value=({tr:'Şu konu/belgeye dayanarak yeni bir resmi dilekçe hazırla: ',de:'Erstelle auf Basis dieses Themas/Dokuments einen neuen formellen Schriftsatz: ',en:'Draft a new formal petition based on this topic/document: '}[UIL()]||'')+CUR.title+'\n\n'+tx.slice(0,1500);
        try{ if(typeof onInp==='function') onInp(i); }catch(e){} i.focus();
        var sent=false; ['sendMsg','doSend','chSend'].forEach(function(fn){ if(!sent){ try{ if(typeof window[fn]==='function'){ window[fn](); sent=true; } }catch(e){} } });
      }
    };
    return v;
  }
  function openTopic(it){
    CUR=it;
    var v=viewer();
    document.getElementById('th-t').textContent='📌 '+it.title;
    var body=document.getElementById('th-body');
    if(it.txt){ body.textContent=it.txt; v.classList.add('on'); return; }
    body.textContent='⏳';
    v.classList.add('on');
    loadHist(function(docs){
      var d=matchDoc(it.title,docs);
      if(d && (d.preview||d.docName)){ var tx=String(d.preview||'').replace(/\\n/g,'\n'); CUR.txt=tx||''; CUR.docId=d.id; body.textContent=tx||T('nodoc'); }
      else body.textContent=T('nodoc');
    });
  }

  /* ── [2] Meine Themen panelinde kendi bolumumuz + eski blogu gizle ── */
  var lastSig='';
  function renderSec(){
    try{
      var pnl=document.getElementById('pnl-ant'); if(!pnl) return;
      var tp=topics();
      var sig=tp.length+'|'+tp.map(function(x){return x.title;}).join('¦').slice(0,300)+'|'+UIL();
      var sec=document.getElementById('th-sec');
      if(sec && sig===lastSig) return;
      lastSig=sig;
      if(!sec){ sec=document.createElement('div'); sec.id='th-sec';
        if(pnl.children.length>1) pnl.insertBefore(sec,pnl.children[1]); else pnl.appendChild(sec); }
      sec.innerHTML='';
      var tt=document.createElement('div'); tt.className='tt'; tt.textContent=T('sec'); sec.appendChild(tt);
      if(!tp.length){ var em=document.createElement('div'); em.className='emp'; em.textContent=T('emp'); sec.appendChild(em); return; }
      tp.slice().reverse().forEach(function(it){
        var d=document.createElement('div'); d.className='th-item';
        var n=document.createElement('div'); n.className='tn'; n.textContent=it.title;
        var dt=document.createElement('div'); dt.className='td';
        try{ if(it.ts){ var dd=new Date(it.ts); dt.textContent=('0'+dd.getDate()).slice(-2)+'.'+('0'+(dd.getMonth()+1)).slice(-2)+'.'; } }catch(e){}
        d.appendChild(n); d.appendChild(dt);
        d.onclick=function(){ openTopic(it); };
        sec.appendChild(d);
      });
    }catch(e){}
  }
  function hideOldSec(){
    try{
      var pf=document.getElementById('pnl-fall'); if(!pf) return;
      var els=pf.querySelectorAll('div,span,h2,h3');
      for(var i=0;i<els.length;i++){
        var e=els[i]; if(e.__thh) continue;
        var t=(e.textContent||'').trim();
        if(t.indexOf('📌')===0 && t.length<40){
          e.__thh=1;
          var blk=(e.parentNode && e.parentNode!==pf)?e.parentNode:e;
          if(blk!==pf) blk.style.display='none';
        }
      }
    }catch(e){}
  }

  /* ── [5] Emsal sonucuna "➕ Themen'e kaydet" ── */
  function emsalBridge(){
    try{
      var acts=document.querySelectorAll('#em-res .em-acts');
      for(var i=0;i<acts.length;i++){
        var a=acts[i]; if(a.__th) continue; a.__th=1;
        var b=document.createElement('button');
        b.textContent={tr:'➕ Themen\'e kaydet',de:'➕ Als Thema speichern',en:'➕ Save as topic'}[UIL()]||'➕';
        b.onclick=function(){
          try{
            var q=String((document.getElementById('em-q')||{}).value||'').trim();
            var card=document.querySelector('#em-res .em-res');
            var tx=card?card.textContent:'';
            if(!tx) return;
            var tps=J('ch_topics',[]); if(!Array.isArray(tps)) tps=[];
            tps.push({id:'em'+new Date().getTime(),title:'⚖️ Emsal: '+(q||'Arama').slice(0,70),doc:tx.slice(0,6000),ts:new Date().getTime(),keep:1,src:'emsal'});
            W('ch_topics',tps); lastSig=''; renderSec(); toast(T('saved'));
          }catch(e){}
        };
        a.appendChild(b);
      }
    }catch(e){}
  }

  setInterval(function(){ chatKill(); renderSec(); hideOldSec(); emsalBridge(); },1200);
  chatKill(); renderSec(); hideOldSec();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'t5').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-themen5-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_THEMEN5')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_THEMEN5 diskte (".strlen($chk)." bayt)\n";
echo "\n✓ [1] Sol menudeki 'Chat' girisi kaldirildi (logo etkilenmez)\n";
echo "✓ [2] Konular artik MEINE THEMEN panelinde; Fälle'deki eski blok gizli\n";
echo "✓ [3] Konuya tiklayinca YAPILAN BELGE gorunur; yalniz 🔁 Überarbeiten yeniden uretir\n";
echo "✓ [4] Goruntuleyiciden ⚖️ Fälle'ye tasima (cift kayit engelli)\n";
echo "✓ [5] Emsal sonuclarinda '➕ Themen'e kaydet' -> konu olarak saklanir,\n";
echo "     oradan ✍️ dilekce hazirlanabilir\n";
