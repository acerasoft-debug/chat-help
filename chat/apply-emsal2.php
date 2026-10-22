<?php
/**
 * ChatHelp — apply-emsal2 (CH_EMSAL2) — Emsal modulu 5 duzeltme:
 *  [1] SADECE Pro/Elite: acilista plan kontrol; degilse premium kapisi
 *      (Paketleri gor) gosterilir, arama yapilmaz.
 *  [2] HUKUK ULKESI DOGRU: ulke ch_cc_manual -> ch_cc -> window.CC sirasiyla
 *      okunur (TR hesapta artik TR hukukunda aranir, Almancaya dusmez).
 *  [3] THEMEN'E KAYDET modul ICINDE calisir: sonuc altindaki dugme dogrudan
 *      ch_topics'e yazar + Meine Themen'i tazeler (harici koprune bagli degil).
 *  [4] ARSIV: aramalar ch_emsal_hist'te birikir; modulun ust kisminda liste
 *      halinde durur, tiklayinca acilir.
 *  [5] YENI ARAMA: her sonuctan sonra "＋ Yeni arama" ile temiz arama ekranina
 *      donulur; arsiv korunur.
 *  window.chEmsal override edilir (eski CH_EMSAL uykuya gecer). Tek eklemeli
 *  </body> blogu. node ✓ + harness ✓.
 * KULLANIM: pull2.php?key=...&files=apply-emsal2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-emsal2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_EMSAL2')!==false) exit("Zaten ekli (CH_EMSAL2).\n");
if (strpos($src,'CH_EMSAL')===false) exit("HATA: once CH_EMSAL gerekli (apply-emsal.php).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-emsal2-css">
.em2-arc{margin-bottom:14px}
.em2-arc .lb{font-size:11.5px;font-weight:800;color:#9a9ab0;margin-bottom:6px}
.em2-arc .it{background:#12122a;border:1px solid rgba(255,255,255,.1);border-radius:10px;padding:9px 12px;margin-bottom:6px;cursor:pointer;font-size:12.5px;font-weight:600;display:flex;gap:8px;align-items:center}
.em2-arc .it:hover{border-color:rgba(212,168,74,.5)}
.em2-arc .it .dt{margin-left:auto;font-size:10px;color:#8e8e9c;white-space:nowrap}
.em2-gate{text-align:center;padding:34px 18px}
.em2-gate .ic{font-size:44px}
.em2-gate h3{font-size:17px;font-weight:800;margin:12px 0 6px;color:#fff}
.em2-gate p{font-size:12.5px;color:#9a9ab0;line-height:1.6;margin-bottom:18px}
.em2-gate button{background:linear-gradient(135deg,#d4a84a,#ecc060);color:#07070e;border:none;border-radius:12px;padding:13px 22px;font-size:14px;font-weight:800;cursor:pointer;font-family:inherit}
.em2-new{background:#1a1a30;border:1px solid rgba(212,168,74,.45);color:#e8c874;border-radius:10px;padding:10px 14px;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit;margin-bottom:12px}
</style>
<script id="ch-emsal2-js">
/* CH_EMSAL2 — Pro/Elite kapisi + dogru hukuk ulkesi + Themen kaydet + arsiv + yeni arama */
try{(function(){
  var API=function(){ return (typeof window.API!=='undefined'&&window.API)?window.API:'api.php'; };
  function UIL(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  /* [2] hukuk ulkesi: manuel secim > ch_cc > window.CC > DE */
  function CCX(){
    try{
      var m=localStorage.getItem('ch_cc_manual');
      var c=localStorage.getItem('ch_cc');
      var w=(typeof window.CC!=='undefined'&&window.CC)?window.CC:'';
      var v=(m&&m.length===2?m:(c||w||'DE'));
      return String(v).toUpperCase();
    }catch(e){ return 'DE'; }
  }
  function LAW(cc){ return {DE:'Alman',AT:'Avusturya',CH:'İsviçre',TR:'Türk',FR:'Fransız',ES:'İspanyol',IT:'İtalyan',NL:'Hollanda',BE:'Belçika',PT:'Portekiz'}[cc]||cc; }
  function LNG(){ return {de:'Deutsch',tr:'Türkçe',en:'English',fr:'Français',es:'Español',it:'Italiano'}[UIL()]||'English'; }
  /* [1] plan */
  function plan(){
    try{
      if(typeof window.chPlan==='function'){ var p=window.chPlan(); if(p) return String(p).toLowerCase(); }
    }catch(e){}
    try{ if(window.P&&window.P.plan) return String(window.P.plan).toLowerCase(); }catch(e){}
    try{ var u=JSON.parse(localStorage.getItem('ch_user')||'{}'); if(u.plan) return String(u.plan).toLowerCase(); }catch(e){}
    return 'free';
  }
  function isPro(){ var p=plan(); return p.indexOf('pro')!==-1||p.indexOf('elite')!==-1; }

  function T(k){
    var L={
      ttl:{de:'Präzedenzfälle & Urteile',tr:'Emsal Kararlar',en:'Case-Law Research'},
      ph:{de:'Thema eingeben — z.B. Mietminderung wegen Schimmel',tr:'Konu yazın — ör. işe iade davası',en:'Enter a topic'},
      go:{de:'🔎 Urteile suchen',tr:'🔎 Emsal ara',en:'🔎 Search'},
      wait:{de:'⏳ Nur gesicherte, veröffentlichte Urteile…',tr:'⏳ Yalnızca doğrulanmış kararlar aranıyor…',en:'⏳ Searching verified rulings…'},
      note:{de:'NUR real existierende, veröffentlichte Urteile (Gericht, Datum, Az.).',tr:'YALNIZCA gerçek, yayımlanmış kararlar (mahkeme, tarih, dosya no).',en:'ONLY real published rulings.'},
      warn:{de:'⚠️ Vor Verwendung in offiziellen Quellen prüfen.',tr:'⚠️ Kullanmadan önce resmî kaynaklardan doğrulayın.',en:'⚠️ Verify in official sources before use.'},
      draft:{de:'✍️ Schriftsatz erstellen',tr:'✍️ Bu emsallerle dilekçe hazırla',en:'✍️ Draft petition'},
      save:{de:'➕ Als Thema speichern',tr:'➕ Themen\'e kaydet',en:'➕ Save as topic'},
      saved:{de:'✓ In „Meine Themen" gespeichert',tr:'✓ Meine Themen\'e kaydedildi',en:'✓ Saved to My Topics'},
      copy:{de:'📋 Kopieren',tr:'📋 Kopyala',en:'📋 Copy'},
      newq:{de:'＋ Neue Suche',tr:'＋ Yeni arama',en:'＋ New search'},
      arc:{de:'📚 Frühere Recherchen',tr:'📚 Önceki aramalar',en:'📚 Previous searches'},
      err:{de:'⚠ Fehlgeschlagen — erneut versuchen.',tr:'⚠ Başarısız — tekrar deneyin.',en:'⚠ Failed — try again.'},
      gateT:{de:'Nur mit Pro oder Elite',tr:'Sadece Pro ve Elite',en:'Pro & Elite only'},
      gateP:{de:'Die Präzedenzfall-Recherche ist Teil von Pro und Elite. Jetzt freischalten.',tr:'Emsal Karar araştırması Pro ve Elite paketlerine özeldir. Hemen açın.',en:'Case-law research is part of Pro & Elite.'},
      gateB:{de:'Pakete ansehen',tr:'Paketleri gör',en:'View plans'},
      law:{de:'Rechtsraum',tr:'Hukuk',en:'Law'}
    };
    var l=UIL(); return (L[k]&&(L[k][l]||L[k].en))||'';
  }
  function LINKS(){
    var cc=CCX();
    if(cc==='DE') return [['dejure.org','https://dejure.org'],['openJur','https://openjur.de'],['Rechtsprechung im Internet','https://www.rechtsprechung-im-internet.de']];
    if(cc==='AT') return [['RIS','https://www.ris.bka.gv.at']];
    if(cc==='CH') return [['Bundesgericht','https://www.bger.ch']];
    if(cc==='TR') return [['Yargıtay Karar Arama','https://karararama.yargitay.gov.tr'],['Anayasa Mahkemesi','https://kararlarbilgibankasi.anayasa.gov.tr'],['Danıştay','https://karararama.danistay.gov.tr'],['UYAP Emsal','https://emsal.uyap.gov.tr']];
    if(cc==='FR') return [['Légifrance','https://www.legifrance.gouv.fr']];
    return [['EUR-Lex','https://eur-lex.europa.eu']];
  }
  function toast(s){ try{ var t=document.createElement('div'); t.setAttribute('style','position:fixed;left:50%;bottom:92px;transform:translateX(-50%);z-index:2147483500;background:rgba(15,22,52,.97);color:#f0e6c8;border:1px solid rgba(212,168,74,.5);border-radius:12px;padding:10px 16px;font-size:13px;font-weight:700;max-width:88vw;text-align:center'); t.textContent=s; document.body.appendChild(t); setTimeout(function(){ try{ t.remove(); }catch(e){} },2600); }catch(e){} }
  function hist(){ try{ var a=JSON.parse(localStorage.getItem('ch_emsal_hist')||'[]'); return Array.isArray(a)?a:[]; }catch(e){ return []; } }
  function histW(a){ try{ localStorage.setItem('ch_emsal_hist',JSON.stringify(a.slice(-30))); }catch(e){} }
  var LAST={q:'',txt:''};

  function toChat(msg){
    try{
      var i=document.getElementById('inp');
      if(i){ i.value=msg; try{ if(typeof onInp==='function') onInp(i); }catch(e){}
        var ov=document.getElementById('em-ov'); if(ov) ov.classList.remove('on');
        i.focus(); var sent=false;
        ['sendMsg','doSend','chSend'].forEach(function(fn){ if(!sent){ try{ if(typeof window[fn]==='function'){ window[fn](); sent=true; } }catch(e){} } });
      }
    }catch(e){}
  }
  /* [3] Themen'e dogrudan kaydet */
  function saveTopic(q,txt){
    try{
      var tps=JSON.parse(localStorage.getItem('ch_topics')||'[]'); if(!Array.isArray(tps)) tps=[];
      tps.push({id:'em'+new Date().getTime(),title:'⚖️ Emsal: '+String(q||'Arama').slice(0,70),doc:String(txt).slice(0,6000),ts:new Date().getTime(),keep:1,src:'emsal'});
      localStorage.setItem('ch_topics',JSON.stringify(tps));
      try{ window.__chLastPlan=window.__chLastPlan; }catch(e){}
      /* Meine Themen bolumunu tazele (CH_THEMEN5) */
      try{ if(window.__themenRefresh) window.__themenRefresh(); }catch(e){}
      return true;
    }catch(e){ return false; }
  }

  function bd(){ return document.getElementById('em-bd'); }
  function screenSearch(prefill){
    var b=bd(); if(!b) return;
    var h=hist().slice().reverse();
    var html='<div style="font-size:11.5px;color:#9a9ab0;margin-bottom:10px">'+T('law')+': <b style="color:#e8c874">'+LAW(CCX())+' ('+CCX()+')</b></div>';
    html+='<div id="em-row"><textarea id="em-q" rows="2" placeholder="'+T('ph').replace(/"/g,'&quot;')+'">'+(prefill?String(prefill).replace(/</g,'&lt;'):'')+'</textarea><button id="em-go">'+T('go')+'</button></div>';
    html+='<div class="em-note">'+T('note')+'</div>';
    html+='<div id="em-res"></div>';
    if(h.length){
      html+='<div class="em2-arc"><div class="lb">'+T('arc')+'</div>';
      h.forEach(function(it,idx){ html+='<div class="it" data-h="'+it.__i+'">⚖️ <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+String(it.q).replace(/</g,'&lt;')+'</span></div>'; });
      html+='</div>';
    }
    b.innerHTML=html;
    var go=document.getElementById('em-go');
    if(go) go.onclick=function(){ var q=String((document.getElementById('em-q')||{}).value||'').trim(); if(q.length<4) return; doSearch(q); };
    /* arsiv tiklama */
    var items=b.querySelectorAll('.em2-arc .it');
    var H=hist();
    for(var i=0;i<items.length;i++){
      (function(el){
        var idx=parseInt(el.getAttribute('data-h'),10);
        el.onclick=function(){ var rec=null; for(var k=0;k<H.length;k++) if(H[k].__i===idx) rec=H[k]; if(rec){ LAST={q:rec.q,txt:rec.t}; showResult(rec.q,rec.t,true); } };
      })(items[i]);
    }
  }
  function showResult(q,txt,cached){
    var b=bd(); if(!b) return;
    var html='<button class="em2-new" id="em-new">'+T('newq')+'</button>';
    html+='<div class="em-res" id="em-card"></div>';
    html+='<div class="em-warn">'+T('warn')+'</div>';
    html+='<div class="em-links" id="em-lk"></div>';
    html+='<div class="em-acts" id="em-acts"></div>';
    b.innerHTML=html;
    document.getElementById('em-card').textContent=txt||T('wait');
    document.getElementById('em-new').onclick=function(){ screenSearch(''); };
    var lk=document.getElementById('em-lk');
    LINKS().forEach(function(l){ var a=document.createElement('a'); a.href=l[1]; a.target='_blank'; a.rel='noopener noreferrer'; a.textContent='🔗 '+l[0]; lk.appendChild(a); });
    var ac=document.getElementById('em-acts');
    var b1=document.createElement('button'); b1.className='em-btn-gold'; b1.setAttribute('style','flex:1;min-width:150px;background:linear-gradient(135deg,#d4a84a,#ecc060);color:#07070e;border:none;border-radius:10px;padding:11px;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit'); b1.textContent=T('draft');
    b1.onclick=function(){ toChat(({tr:'Aşağıdaki GERÇEK emsal kararlara dayanarak resmi bir dilekçe hazırla (kaynak göster). Konu: ',de:'Erstelle auf Basis dieser REALEN Urteile einen Schriftsatz (mit Zitaten). Thema: ',en:'Draft a petition citing these REAL rulings. Topic: '}[UIL()]||'')+LAST.q+'\n\n'+LAST.txt.slice(0,1600)); };
    var b2=document.createElement('button'); b2.setAttribute('style','flex:1;min-width:150px;background:#1a1a30;border:1px solid rgba(212,168,74,.45);color:#e8c874;border-radius:10px;padding:11px;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit'); b2.textContent=T('save');
    b2.onclick=function(){ if(saveTopic(LAST.q,LAST.txt)){ b2.textContent=T('saved'); b2.disabled=true; toast(T('saved')); } };
    var b3=document.createElement('button'); b3.setAttribute('style','flex:1;min-width:110px;background:#1a1a30;border:1px solid rgba(255,255,255,.16);color:#c8d8ff;border-radius:10px;padding:11px;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit'); b3.textContent=T('copy');
    b3.onclick=function(){ try{ if(navigator.clipboard&&navigator.clipboard.writeText) navigator.clipboard.writeText(LAST.txt); b3.textContent='✓'; setTimeout(function(){ try{ b3.textContent=T('copy'); }catch(e){} },1300); }catch(e){} };
    ac.appendChild(b1); ac.appendChild(b2); ac.appendChild(b3);
  }
  function doSearch(q){
    LAST={q:q,txt:''};
    showResult(q,T('wait'),false);
    var cc=CCX();
    var msg='Sen titiz bir hukuk araştırma asistanısın. GÖREV: "'+q+'" konusunda '+LAW(cc)+' hukukunda ('+cc+') EMSAL KARARLARI listele.\n'
      +'KATI KURALLAR:\n'
      +'1) SADECE '+LAW(cc)+' hukukuna ait, gerçekten var olduğundan EMİN olduğun yayımlanmış kararları yaz (yüksek mahkeme/tanınmış içtihat öncelikli). Başka ülkenin hukukunu KARIŞTIRMA.\n'
      +'2) ASLA dosya numarası/tarih/karar UYDURMA. Emin olmadığını "(doğrulanmalı)" işaretle veya yazma.\n'
      +'3) Güvenilir emsal bilmiyorsan açıkça söyle ve hangi resmî veritabanında hangi kelimelerle aranacağını öner.\n'
      +'4) En fazla 5 karar. Her biri: Mahkeme — Tarih — Dosya/Esas No — 2-3 cümle özet — bu konuya etkisi — güven düzeyi.\n'
      +'5) Sonda 2 cümle genel durum.\n'
      +'Cevabı '+LNG()+' dilinde yaz. Markdown yıldızı kullanma.';
    fetch(API()+'?action=aichat',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({message:msg,history:[],provider:'claude',lang:UIL(),country:cc})})
     .then(function(r){ return r.json(); })
     .then(function(j){
        var t=(j&&j.reply)?String(j.reply).replace(/```[a-z]*\n?|```/g,'').replace(/\*\*/g,'').trim():'';
        if(!t){ var c=document.getElementById('em-card'); if(c) c.textContent=T('err')+((j&&j.error)?(' ('+j.error+')'):''); return; }
        LAST={q:q,txt:t};
        var c=document.getElementById('em-card'); if(c) c.textContent=t;
        /* arsive yaz */
        var h=hist(); h.push({__i:new Date().getTime(),q:q,t:t.slice(0,5000),cc:cc}); histW(h);
        /* belge hafizasina isle */
        try{ var a=JSON.parse(localStorage.getItem('ch_docmem')||'[]'); if(!Array.isArray(a)) a=[]; a.push({t:'',s:'EMSAL ('+q.slice(0,70)+'): '+t.slice(0,400)}); localStorage.setItem('ch_docmem',JSON.stringify(a.slice(-8))); }catch(e){}
     })
     .catch(function(){ var c=document.getElementById('em-card'); if(c) c.textContent=T('err'); });
  }

  function gate(){
    var b=bd(); if(!b) return;
    b.innerHTML='<div class="em2-gate"><div class="ic">⚖️</div><h3>'+T('gateT')+'</h3><p>'+T('gateP')+'</p><button id="em-gate-b">'+T('gateB')+'</button></div>';
    var g=document.getElementById('em-gate-b');
    if(g) g.onclick=function(){ var o=document.getElementById('em-ov'); if(o) o.classList.remove('on'); try{ if(typeof window.gP==='function') window.gP('plans'); else if(typeof window.chPlans==='function') window.chPlans(); }catch(e){} };
  }

  function ovEl(){
    var o=document.getElementById('em-ov');
    if(!o){ o=document.createElement('div'); o.id='em-ov';
      o.innerHTML='<div class="top"><h2>⚖️ <em>'+T('ttl')+'</em></h2><button class="x" id="em-x2">✕</button></div><div id="em-bd"></div>';
      document.body.appendChild(o);
    }
    var x=document.getElementById('em-x2')||o.querySelector('.x');
    if(x) x.onclick=function(){ o.classList.remove('on'); };
    return o;
  }
  /* override */
  window.chEmsal=function(){
    var o=ovEl(); o.classList.add('on');
    if(!isPro()){ gate(); return; }
    screenSearch('');
  };
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'e2').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-emsal2-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_EMSAL2')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_EMSAL2 diskte (".strlen($chk)." bayt)\n";
echo "\n✓ [1] Emsal artik SADECE Pro/Elite (digerine premium kapisi)\n";
echo "✓ [2] Hukuk ulkesi dogru: ch_cc_manual->ch_cc (TR hesapta TR hukuku)\n";
echo "✓ [3] 'Themen'e kaydet' modul icinde calisiyor (dogrudan ch_topics)\n";
echo "✓ [4] Aramalar arsivde birikir (modul ustunde liste, tiklayinca acilir)\n";
echo "✓ [5] Her sonuctan '＋ Yeni arama' ile temiz ekrana donulur\n";
