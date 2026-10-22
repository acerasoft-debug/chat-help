<?php
/**
 * ChatHelp — apply-fall-topics (CH_FALL_TOPICS) — F2-10: otomatik konu (topic)
 *  • Her dilekce uretiminde (showResult) otomatik bir KONU acilir; ayni belge
 *    turunde 24 saat icinde uretilen yeni belgeler AYNI konuda birikir.
 *  • Konular "Meine Fälle" panelinde ayri bir bolumde listelenir (mevcut
 *    Fall listesine ve Fall-analiz KOTASINA dokunulmaz).
 *  • Ilk belgeden sonra kullaniciya sorulur: "Bu konu hafizada saklansin mi?"
 *    Sakla -> kalici. Cevapsiz/Hayir -> 24 saat sonra otomatik silinir.
 *  • Konudaki kayda tiklayinca belge yeniden gosterilir.
 *  Tamamen EK: showResult zincir-korumali sarilir (CH_FIX5 bayraklari tasinir).
 * KULLANIM: pull2.php?key=...&files=apply-fall-topics.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fall-topics BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_FALL_TOPICS')!==false) exit("Zaten ekli (CH_FALL_TOPICS).\n");

$bundle = <<<'HTML'
<style id="ch-falltopics-css">
#ch-topics-sec{margin:12px 14px;padding:14px;border:1px solid rgba(212,168,74,.28);border-radius:14px;background:linear-gradient(180deg,rgba(212,168,74,.06),rgba(212,168,74,.02))}
#ch-topics-sec .tt-h{font-size:13px;font-weight:800;color:var(--gold,#e9c46a);margin-bottom:8px;display:flex;align-items:center;gap:7px}
#ch-topics-sec .tt-empty{font-size:12px;color:var(--t4,#9a9aae)}
.tt-item{border:1px solid rgba(255,255,255,.08);border-radius:11px;padding:10px 12px;margin-bottom:8px;background:var(--s2,#171a24)}
.tt-item .tt-t{font-size:13px;font-weight:700;color:var(--t1,#f0f0f6)}
.tt-item .tt-m{font-size:11px;color:var(--t4,#9a9aae);margin-top:2px}
.tt-item .tt-docs{margin-top:7px;display:flex;flex-direction:column;gap:4px}
.tt-item .tt-doc{font-size:12px;color:var(--t2,#cfcfe0);cursor:pointer;padding:5px 8px;border-radius:8px;background:rgba(255,255,255,.04)}
.tt-item .tt-doc:hover{background:rgba(212,168,74,.12)}
.tt-item .tt-acts{margin-top:8px;display:flex;gap:8px}
.tt-item .tt-btn{font-size:11px;font-weight:700;padding:5px 10px;border-radius:8px;border:1px solid rgba(255,255,255,.14);background:transparent;color:var(--t3,#bdbdcc);cursor:pointer;font-family:inherit}
.tt-item .tt-btn.keep{border-color:rgba(94,203,126,.5);color:#5ecb7e}
.tt-item .tt-btn.del{border-color:rgba(194,91,91,.5);color:#c25b5b}
.tt-badge{display:inline-block;font-size:10px;font-weight:800;padding:2px 8px;border-radius:999px;margin-left:6px;vertical-align:middle}
.tt-badge.kept{background:rgba(94,203,126,.15);color:#5ecb7e}
.tt-badge.tmp{background:rgba(212,168,74,.14);color:#e9c46a}
#ch-topic-ask{position:fixed;left:10px;right:10px;bottom:12px;z-index:99960;max-width:560px;margin:0 auto;background:linear-gradient(180deg,#1a1c28,#12141c);border:1.5px solid rgba(212,168,74,.5);border-radius:16px;padding:14px 16px;box-shadow:0 14px 40px rgba(0,0,0,.5)}
#ch-topic-ask .q{font-size:13.5px;font-weight:700;color:var(--t1,#f0f0f6)}
#ch-topic-ask .s{font-size:11.5px;color:var(--t4,#9a9aae);margin-top:3px}
#ch-topic-ask .row{display:flex;gap:10px;margin-top:11px}
#ch-topic-ask button{flex:1;padding:10px;border-radius:11px;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit;border:0}
#ch-topic-ask .yes{background:linear-gradient(135deg,#e9c46a,#d4a84a);color:#2a2410}
#ch-topic-ask .no{background:var(--s3,#232633);color:var(--t3,#bdbdcc);border:1px solid rgba(255,255,255,.12)}
</style>
<script id="ch-falltopics-js">
/* CH_FALL_TOPICS — otomatik konu + Meine Fälle bolumu + sakla/24s sil */
try{(function(){
  var KEY="ch_topics", DAY=86400000;
  function L(){ try{ return (window.chUIL?window.chUIL():null)||localStorage.getItem("ch_uilang")||(window.UL||"de"); }catch(e){ return "de"; } }
  var TT={
   de:{sec:"📌 Meine Themen",empty:"Noch keine Themen — nach jedem Schreiben entsteht hier automatisch ein Thema.",q:"Dieses Thema im Speicher behalten?",qs:"Ohne Antwort wird es nach 24 Std. automatisch gelöscht.",yes:"Behalten",no:"Nach 24 Std. löschen",kept:"gespeichert",tmp:"noch ~{h} Std.",rec:"Einträge",del:"Löschen",keep:"Behalten"},
   en:{sec:"📌 My topics",empty:"No topics yet — each document automatically opens one here.",q:"Keep this topic in memory?",qs:"Without an answer it is deleted after 24 h.",yes:"Keep",no:"Delete after 24 h",kept:"saved",tmp:"~{h} h left",rec:"entries",del:"Delete",keep:"Keep"},
   tr:{sec:"📌 Konularım",empty:"Henüz konu yok — her dilekçeden sonra burada otomatik bir konu açılır.",q:"Bu konu hafızada saklansın mı?",qs:"Cevap verilmezse 24 saat içinde otomatik silinir.",yes:"Sakla",no:"24 saat sonra sil",kept:"saklandı",tmp:"~{h} saat kaldı",rec:"kayıt",del:"Sil",keep:"Sakla"},
   es:{sec:"📌 Mis temas",empty:"Aún no hay temas: cada escrito abre uno automáticamente.",q:"¿Guardar este tema en la memoria?",qs:"Sin respuesta se elimina en 24 h.",yes:"Guardar",no:"Borrar en 24 h",kept:"guardado",tmp:"quedan ~{h} h",rec:"registros",del:"Borrar",keep:"Guardar"},
   fr:{sec:"📌 Mes sujets",empty:"Pas encore de sujet — chaque courrier en ouvre un automatiquement.",q:"Conserver ce sujet en mémoire ?",qs:"Sans réponse, il sera supprimé sous 24 h.",yes:"Conserver",no:"Supprimer après 24 h",kept:"enregistré",tmp:"~{h} h restantes",rec:"entrées",del:"Supprimer",keep:"Conserver"},
   it:{sec:"📌 I miei temi",empty:"Ancora nessun tema: ogni documento ne apre uno automaticamente.",q:"Conservare questo tema in memoria?",qs:"Senza risposta verrà eliminato entro 24 h.",yes:"Conserva",no:"Elimina dopo 24 h",kept:"salvato",tmp:"~{h} h rimaste",rec:"voci",del:"Elimina",keep:"Conserva"}
  };
  function t(){ return TT[L()]||TT.en; }
  function load(){ try{ return JSON.parse(localStorage.getItem(KEY)||"[]")||[]; }catch(e){ return []; } }
  function save(a){ try{ localStorage.setItem(KEY, JSON.stringify(a.slice(0,20))); }catch(e){} }
  function purge(){
    var now=Date.now();
    var a=load().filter(function(x){ return x.keep===true || (now-(x.created||0))<DAY; });
    save(a); return a;
  }
  function onDoc(doc,dk,docId){
    var a=purge(), now=Date.now();
    var name=""; try{ name=(typeof DOCS!=="undefined"&&DOCS[dk]&&DOCS[dk].n)?DOCS[dk].n:String(dk||"Dokument"); }catch(e){ name=String(dk||"Dokument"); }
    var tp=a.find(function(x){ return x.dk===dk && (x.keep===true || (now-x.created)<DAY); });
    var fresh=false;
    if(!tp){ tp={id:Math.random().toString(36).slice(2,9),dk:dk,title:name,items:[],created:now,keep:null,asked:false}; a.unshift(tp); fresh=true; }
    tp.items.unshift({n:name,doc:String(doc||"").slice(0,40000),id:docId||null,ts:now});
    if(tp.items.length>10) tp.items=tp.items.slice(0,10);
    save(a);
    if(fresh && !tp.asked) ask(tp.id);
  }
  function ask(tid){
    try{
      var old=document.getElementById("ch-topic-ask"); if(old) old.remove();
      var Lx=t();
      var d=document.createElement("div"); d.id="ch-topic-ask"; d.setAttribute("data-noi18n","1");
      d.innerHTML='<div class="q">'+Lx.q+'</div><div class="s">'+Lx.qs+'</div>'
        +'<div class="row"><button class="yes">'+Lx.yes+'</button><button class="no">'+Lx.no+'</button></div>';
      function set(v){ var a=load(); var x=a.find(function(y){return y.id===tid;}); if(x){ x.keep=v; x.asked=true; save(a); } d.remove(); }
      d.querySelector(".yes").addEventListener("click",function(){ set(true); });
      d.querySelector(".no").addEventListener("click",function(){ set(false); });
      document.body.appendChild(d);
      setTimeout(function(){ try{ if(document.getElementById("ch-topic-ask")===d) d.remove(); }catch(e){} }, 30000);
    }catch(e){}
  }
  /* Meine Fälle paneline bolum */
  var lastSig="";
  function render(){
    try{
      var p=document.getElementById("pnl-fall"); if(!p) return;
      var vis=false; try{ vis=(getComputedStyle(p).display!=="none"); }catch(e){}
      if(!vis) return;
      var a=purge(), Lx=t();
      var sig=L()+"|"+JSON.stringify(a.map(function(x){return [x.id,x.keep,x.items.length];}));
      var sec=document.getElementById("ch-topics-sec");
      if(sec && sig===lastSig) return;
      lastSig=sig;
      if(!sec){
        sec=document.createElement("div"); sec.id="ch-topics-sec"; sec.setAttribute("data-noi18n","1");
        var tb=p.querySelector(".pnl-topbar");
        if(tb && tb.nextSibling) p.insertBefore(sec, tb.nextSibling); else p.appendChild(sec);
      }
      var h='<div class="tt-h">'+Lx.sec+'</div>';
      if(!a.length){ sec.innerHTML=h+'<div class="tt-empty">'+Lx.empty+'</div>'; return; }
      h+=a.map(function(x){
        var badge = x.keep===true
          ? '<span class="tt-badge kept">✓ '+Lx.kept+'</span>'
          : '<span class="tt-badge tmp">⏳ '+Lx.tmp.replace("{h}", String(Math.max(1,Math.round((DAY-(Date.now()-x.created))/3600000))))+'</span>';
        var docs=x.items.map(function(it,i){
          return '<div class="tt-doc" data-tid="'+x.id+'" data-i="'+i+'">📄 '+it.n+' · '+new Date(it.ts).toLocaleDateString()+'</div>';
        }).join("");
        var kb = x.keep===true ? "" : '<button class="tt-btn keep" data-keep="'+x.id+'">⭐ '+Lx.keep+'</button>';
        return '<div class="tt-item"><div class="tt-t">'+x.title+badge+'</div>'
          +'<div class="tt-m">'+x.items.length+' '+Lx.rec+' · '+new Date(x.created).toLocaleDateString()+'</div>'
          +'<div class="tt-docs">'+docs+'</div>'
          +'<div class="tt-acts">'+kb+'<button class="tt-btn del" data-del="'+x.id+'">🗑 '+Lx.del+'</button></div></div>';
      }).join("");
      sec.innerHTML=h;
      sec.querySelectorAll("[data-keep]").forEach(function(b){ b.addEventListener("click",function(){ var a2=load(); var x=a2.find(function(y){return y.id===b.getAttribute("data-keep");}); if(x){ x.keep=true; x.asked=true; save(a2); lastSig=""; render(); } }); });
      sec.querySelectorAll("[data-del]").forEach(function(b){ b.addEventListener("click",function(){ save(load().filter(function(y){return y.id!==b.getAttribute("data-del");})); lastSig=""; render(); }); });
      sec.querySelectorAll(".tt-doc").forEach(function(el){ el.addEventListener("click",function(){
        var a2=load(); var x=a2.find(function(y){return y.id===el.getAttribute("data-tid");}); if(!x) return;
        var it=x.items[parseInt(el.getAttribute("data-i"),10)]; if(!it||!it.doc) return;
        try{ if(typeof gP==="function") gP("chat"); }catch(e){}
        try{ if(typeof window.showResult==="function") window.showResult(it.doc, x.dk, null, it.id); }catch(e){}
      }); });
    }catch(e){}
  }
  /* showResult'i zincir-korumali sar */
  function hook(){
    var f=window.showResult;
    if(typeof f!=="function"||f._chTopic) return;
    var w=function(doc,dk,research,docId){
      var r=f.apply(this,arguments);
      try{ if(!w._replay) onDoc(doc,dk,docId); }catch(e){}
      return r;
    };
    w._chTopic=1;
    try{ for(var p in f){ w[p]=f[p]; } }catch(e){}
    window.showResult=w;
  }
  purge();
  var n=0, iv=setInterval(function(){ hook(); render(); if(++n>2400) clearInterval(iv); }, 1500);
  hook();
})();}catch(e){}
</script>
HTML;

$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok — index degistirilmedi.\n");
$src = substr($src,0,$pos).$bundle."\n".substr($src,$pos);
echo "  ✓ otomatik konu sistemi + Meine Fälle bolumu + sakla/24s sorusu eklendi\n";

$tmp = tempnam(sys_get_temp_dir(),'ft').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-falltopics-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_FALL_TOPICS uygulandi:\n";
echo "   • her dilekceden sonra otomatik konu acilir (Fall kotasi YENMEZ)\n";
echo "   • ayni belge turu 24s icinde ayni konuda birikir\n";
echo "   • 'hafizada saklansin mi?' sorusu; cevapsiz/hayir -> 24s'te silinir\n";
echo "   • Meine Fälle panelinde 'Konularim' bolumu; kayda tiklayinca belge tekrar acilir\n";
