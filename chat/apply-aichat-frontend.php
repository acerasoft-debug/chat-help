<?php
/**
 * ChatHelp — apply-aichat-frontend (CH_AICHAT_FE)
 *  doSend()'i SADIK sekilde yeniden yazar (profil kontrolu, dil algilama,
 *  typing gostergesi BIREBIR korunur) — SADECE son "bilinmeyen istek" dalinda
 *  eski statik "Welches Dokument?" + kart izgarasi yerine GERCEK DeepSeek/
 *  Claude sohbeti calisir. Ayrica:
 *   • Kota/katman: Free serbest (nudge ~10 mesaj sonra), Basic gunluk ~80
 *     mesaj + 4 saat kilit, Pro/Elite DeepSeek agirlikli + periyodik Claude.
 *   • Gecmis sohbetler: NC() sarmalanir (oturum kaydedilir), yeni "🕘 Verlauf"
 *     nav girisi Chat'in hemen altina eklenir, tiklaninca gecmis sohbetleri
 *     listeler; birine tiklayinca o sohbet geri yuklenir.
 *   • AI yaniti sonunda [[SUGGEST:x]] etiketi varsa tiklanabilir modul
 *     onerisi (Dokument/CV/Vertrag/Job) kabarcik olarak gosterilir.
 * KULLANIM: pull-updates.php?files=apply-aichat-frontend.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-aichat-frontend BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_AICHAT_FE') !== false) exit("Zaten ekli (CH_AICHAT_FE).\n");
file_put_contents($file . '.bak-aichatfe-' . date('Ymd-His'), $src);

$bundle = <<<'FEHTML'
<style id="ch-aichat-css">
/* CH_AICHAT_FE */
#pnl-verlauf{position:fixed;inset:0;z-index:520;background:var(--bg,#17171d);display:none;flex-direction:column;overflow:hidden}
#pnl-verlauf.on{display:flex}
.ch-suggest-chip{transition:transform .12s,box-shadow .2s}
.ch-suggest-chip:hover{transform:translateY(-1px);box-shadow:0 8px 20px rgba(212,168,74,.25)}
</style>
<script id="ch-aichat-frontend">
/* CH_AICHAT_FE — DeepSeek/Claude sohbeti + gecmis sohbetler */
try{(function(){
  function esc(s){ return String(s==null?"":s).replace(/[&<>"]/g,function(c){return{"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];}); }
  function UIL(){ try{ return (window.chUIL?window.chUIL():(localStorage.getItem("ch_uilang")||"de")); }catch(e){ return "de"; } }

  var T={
   de:{verlauf:"Verlauf",empty:"Noch keine gespeicherten Unterhaltungen.",neu:"+ Neue Unterhaltung",msgs:"Nachrichten",
     lockBasic:"Tageslimit erreicht — weiter geht's in ein paar Stunden. Alternativ: Dokument direkt erstellen.",
     suggDoc:"📄 Dokument erstellen",suggCv:"📇 Lebenslauf & CV öffnen",suggVst:"📑 Vertrag erstellen",suggJobs:"💼 Job finden",
     fail:"Entschuldigung, das hat nicht geklappt. Bitte erneut versuchen.",neterr:"Verbindungsfehler. Bitte erneut versuchen."},
   tr:{verlauf:"Geçmiş",empty:"Henüz kayıtlı bir sohbet yok.",neu:"+ Yeni sohbet",msgs:"mesaj",
     lockBasic:"Günlük limit doldu — birkaç saat sonra devam edebilirsin. Ya da direkt belge oluştur.",
     suggDoc:"📄 Belge oluştur",suggCv:"📇 Lebenslauf & CV aç",suggVst:"📑 Sözleşme oluştur",suggJobs:"💼 İş bul",
     fail:"Üzgünüm, bu işe yaramadı. Lütfen tekrar dene.",neterr:"Bağlantı hatası. Lütfen tekrar dene."},
   en:{verlauf:"History",empty:"No saved conversations yet.",neu:"+ New conversation",msgs:"messages",
     lockBasic:"Daily limit reached — you can continue in a few hours. Or create a document directly.",
     suggDoc:"📄 Create document",suggCv:"📇 Open CV & Résumé",suggVst:"📑 Create contract",suggJobs:"💼 Find a job",
     fail:"Sorry, that didn't work. Please try again.",neterr:"Connection error. Please try again."},
   fr:{verlauf:"Historique",empty:"Aucune conversation enregistrée.",neu:"+ Nouvelle conversation",msgs:"messages",
     lockBasic:"Limite quotidienne atteinte — continuez dans quelques heures. Ou créez un document directement.",
     suggDoc:"📄 Créer un document",suggCv:"📇 Ouvrir CV",suggVst:"📑 Créer un contrat",suggJobs:"💼 Trouver un emploi",
     fail:"Désolé, ça n'a pas fonctionné. Réessayez.",neterr:"Erreur de connexion. Réessayez."},
   es:{verlauf:"Historial",empty:"Aún no hay conversaciones guardadas.",neu:"+ Nueva conversación",msgs:"mensajes",
     lockBasic:"Límite diario alcanzado — podrá continuar en unas horas. O cree un documento directamente.",
     suggDoc:"📄 Crear documento",suggCv:"📇 Abrir CV",suggVst:"📑 Crear contrato",suggJobs:"💼 Buscar empleo",
     fail:"Lo sentimos, no funcionó. Inténtelo de nuevo.",neterr:"Error de conexión. Inténtelo de nuevo."},
   it:{verlauf:"Cronologia",empty:"Nessuna conversazione salvata.",neu:"+ Nuova conversazione",msgs:"messaggi",
     lockBasic:"Limite giornaliero raggiunto — potrai continuare tra qualche ora. Oppure crea un documento.",
     suggDoc:"📄 Crea documento",suggCv:"📇 Apri CV",suggVst:"📑 Crea contratto",suggJobs:"💼 Trova lavoro",
     fail:"Spiacenti, non ha funzionato. Riprova.",neterr:"Errore di connessione. Riprova."}
  };
  function L(){ return T[UIL()]||T.en; }

  var HK="ch_chat_hist", SK="ch_chat_sessions", CK="ch_chat_current";
  function getHist(){ try{ return JSON.parse(sessionStorage.getItem(HK)||"[]"); }catch(e){ return []; } }
  function setHist(h){ try{ sessionStorage.setItem(HK, JSON.stringify(h)); }catch(e){} }
  function plan(){ try{ return (window.P&&P.plan)||JSON.parse(localStorage.getItem("ch_user")||"{}").plan||""; }catch(e){ return ""; } }

  function saveSession(hist){
    try{
      if(!hist||!hist.length) return;
      var sessions=[]; try{ sessions=JSON.parse(localStorage.getItem(SK)||"[]"); }catch(e){}
      var curId=sessionStorage.getItem(CK);
      if(!curId){ curId="cs_"+Date.now()+"_"+Math.random().toString(36).slice(2,8); sessionStorage.setItem(CK,curId); }
      var title=(hist[0]&&hist[0].content||"…").slice(0,60);
      var idx=-1; for(var i=0;i<sessions.length;i++){ if(sessions[i].id===curId){ idx=i; break; } }
      var entry={id:curId,title:title,updated:Date.now(),messages:hist};
      if(idx>-1) sessions[idx]=entry; else sessions.unshift(entry);
      if(sessions.length>30) sessions=sessions.slice(0,30);
      localStorage.setItem(SK, JSON.stringify(sessions));
    }catch(e){}
  }

  /* ── kota (client-side, sitenin genelindeki plan-kontrol konvansiyonuyla ayni) ── */
  function quotaCheck(){
    var pl=plan();
    if(pl==="pro"||pl==="elite") return {ok:true};
    if(pl==="basic"){
      var lockUntil=+(localStorage.getItem("ch_chat_basic_lock")||0);
      if(Date.now()<lockUntil) return {ok:false,msg:L().lockBasic};
      var day=new Date().toISOString().slice(0,10);
      var k="ch_chat_basic_"+day;
      var used=+(localStorage.getItem(k)||0);
      if(used>=80){ localStorage.setItem("ch_chat_basic_lock", String(Date.now()+4*3600*1000)); return {ok:false,msg:L().lockBasic}; }
      localStorage.setItem(k, String(used+1));
      return {ok:true};
    }
    return {ok:true}; /* free/kayitsiz: her zaman izinli, nudge sunucuya bayrakla bildirilir */
  }
  function pickProvider(turnIdx){
    var pl=plan();
    if(pl==="elite") return (turnIdx%3===2)?"claude":"deepseek";
    if(pl==="pro")   return (turnIdx%6===5)?"claude":"deepseek";
    if(pl==="basic") return (turnIdx%6===5)?"claude":"deepseek";
    return (turnIdx%6===5)?"claude":"deepseek"; /* free: ~5-6 mesajda bir Claude kontrolu */
  }

  var SUGG_MAP={doc:["suggDoc","chat"],cv:["suggCv","cv"],vst:["suggVst","vst"],jobs:["suggJobs","jobs"]};
  function showSuggestChip(kind){
    try{
      var info=SUGG_MAP[kind]; if(!info) return;
      var mi=document.getElementById("mi"); if(!mi) return;
      var l=L();
      var d=document.createElement("div"); d.className="msg ai";
      d.innerHTML='<div class="av">CH</div><div class="mbody"><div class="bbl"><button type="button" class="ch-suggest-chip" style="padding:10px 16px;border-radius:100px;border:1px solid rgba(212,168,74,.4);background:rgba(212,168,74,.1);color:var(--gold,#d4a84a);font-weight:700;font-size:13px;cursor:pointer;font-family:inherit">'+esc(l[info[0]])+' →</button></div></div>';
      mi.appendChild(d);
      var btn=d.querySelector(".ch-suggest-chip");
      if(btn) btn.addEventListener("click",function(){ try{ gP(info[1]); }catch(e){} });
      try{ mi.scrollTop=mi.scrollHeight; }catch(e){}
    }catch(e){}
  }

  async function chAiChatTurn(userText){
    var l=L();
    var q=quotaCheck();
    if(!q.ok){ addMsg("ai", q.msg); return; }
    var hist=getHist();
    var freeNudge=(plan()==="" && hist.length>=9);
    var provider=pickProvider(hist.length);
    var profile={}; try{ profile={f1:P.f1,f2:P.f2,f3:P.f3,f4:P.f4,f5:P.f5,f7:P.f7}; }catch(e){}
    try{
      var r=await fetch(API+"?action=aichat",{method:"POST",headers:{"Content-Type":"application/json"},
        body:JSON.stringify({message:userText,history:hist,provider:provider,profile:profile,lang:(typeof dLang!=="undefined"?dLang:"de"),nudge:freeNudge})});
      var d=await r.json();
      if(d&&d.reply){
        var reply=d.reply, suggest=null;
        var m=reply.match(/\[\[SUGGEST:(doc|cv|vst|jobs)\]\]\s*$/);
        if(m){ suggest=m[1]; reply=reply.replace(/\[\[SUGGEST:(doc|cv|vst|jobs)\]\]\s*$/,"").trim(); }
        addMsg("ai", reply);
        if(suggest) showSuggestChip(suggest);
        hist=hist.concat([{role:"user",content:userText},{role:"ai",content:reply}]);
        if(hist.length>40) hist=hist.slice(-40);
        setHist(hist); saveSession(hist);
      } else { addMsg("ai", l.fail); }
    }catch(e){ addMsg("ai", l.neterr); }
  }

  /* ── doSend(): SADIK yeniden yazim — sadece 'other' dalinda gercek sohbet ── */
  function wrapDoSend(){
    try{
      if(typeof doSend!=="function" || doSend.__aichat) return;
      var w=async function(){
        const inpEl=document.getElementById('inp');const tx=inpEl.value.trim();
        if(!tx||busy)return;inpEl.value='';inpEl.style.height='auto';
        setFlag(detL(tx));addMsg('user',tx);cSB();
        busy=true;document.getElementById('sbtn').disabled=true;
        showTyping();await sleep(500);remT();
        if(!P.f1){addMsg('ai','Bitte zuerst Ihre persönlichen Daten:');showProfileForm(null);busy=false;document.getElementById('sbtn').disabled=false;return;}
        try{
          const r=await fetch(API+'?action=detect',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({text:tx,lang:dLang,country:CC})});
          const d=await r.json();
          if(d.doc&&d.doc!=='other'&&DOCS[d.doc]){if(d.reply)addMsg('ai',d.reply);CD=d.doc;setTimeout(()=>showQuestions(d.doc),300);}
          else{ await chAiChatTurn(tx); }
        }catch(e){ await chAiChatTurn(tx); }
        busy=false;document.getElementById('sbtn').disabled=false;
      };
      w.__aichat=1; doSend=w; if(typeof window!=="undefined") window.doSend=w;
    }catch(e){}
  }

  /* ── NC() sarmalayici: yeni sohbete gecerken mevcut oturumu kaydet ── */
  function wrapNC(){
    try{
      if(typeof NC!=="function" || NC.__aichat) return;
      var _nc=NC;
      var w=function(){
        try{ var h=getHist(); if(h.length) saveSession(h); sessionStorage.removeItem(CK); setHist([]); }catch(e){}
        return _nc.apply(this,arguments);
      };
      w.__aichat=1; NC=w; if(typeof window!=="undefined") window.NC=w;
    }catch(e){}
  }

  /* ── Verlauf (gecmis sohbetler) paneli ── */
  function loadSession(s){
    try{
      var mi=document.getElementById("mi"); if(mi) mi.innerHTML="";
      (s.messages||[]).forEach(function(m){ addMsg(m.role==="ai"?"ai":"user", m.content); });
      sessionStorage.setItem(CK, s.id); setHist(s.messages||[]);
      gP("chat");
    }catch(e){}
  }
  function renderVerlauf(){
    var l=L();
    var p=document.getElementById("pnl-verlauf");
    if(!p){ p=document.createElement("div"); p.className="pnl"; p.id="pnl-verlauf"; document.body.appendChild(p); }
    var sessions=[]; try{ sessions=JSON.parse(localStorage.getItem(SK)||"[]"); }catch(e){}
    p.innerHTML='<div class="vst-topbar"><button class="vst-back" id="verlauf-back">‹</button><div class="vst-ttl">🕘 '+esc(l.verlauf)+'</div></div>'
      +'<div class="vst-scroll"><div class="vst-wrap" style="max-width:640px">'
      +'<button class="vst-nextb" id="verlauf-neu" style="width:100%;margin-bottom:14px">'+esc(l.neu)+'</button>'
      +(sessions.length ? sessions.map(function(s,i){
          var dt=""; try{ dt=new Date(s.updated).toLocaleString(); }catch(e){}
          return '<div class="vst-card" data-i="'+i+'" style="cursor:pointer"><div class="vst-n" style="font-size:14px">'+esc(s.title||"…")+'</div>'
            +'<div class="vst-sn">'+esc(dt)+' · '+((s.messages||[]).length)+' '+esc(l.msgs)+'</div></div>';
        }).join("") : '<div class="vst-sub">'+esc(l.empty)+'</div>')
      +'</div></div>';
    var b=document.getElementById("verlauf-back"); if(b) b.addEventListener("click",function(){ try{ gP("chat"); }catch(e){} });
    var nb=document.getElementById("verlauf-neu"); if(nb) nb.addEventListener("click",function(){ try{ NC(); gP("chat"); }catch(e){} });
    p.querySelectorAll("[data-i]").forEach(function(c){
      c.addEventListener("click",function(){ var s=sessions[+c.getAttribute("data-i")]; if(s) loadSession(s); });
    });
  }
  function addVerlaufNav(){
    try{
      var ex=document.getElementById("nav-verlauf");
      if(ex){ var t=ex.querySelector("span.chv-t"); if(t&&t.textContent!==L().verlauf) t.textContent=L().verlauf; return; }
      var navs=document.querySelectorAll(".sb-nav-item");
      var chatNav=null;
      navs.forEach(function(n){ if(!chatNav && n.getAttribute("onclick")==="NC()") chatNav=n; });
      if(!chatNav||!chatNav.parentNode) return;
      var nav=document.createElement("div"); nav.className="sb-nav-item"; nav.id="nav-verlauf";
      nav.setAttribute("onclick","gP('verlauf')");
      nav.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" style="width:16px;height:16px;flex-shrink:0"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2.5M9 2h6"/></svg><span class="chv-t">'+esc(L().verlauf)+'</span>';
      chatNav.parentNode.insertBefore(nav, chatNav.nextSibling);
    }catch(e){}
  }
  function wrapGPForVerlauf(){
    try{
      if(typeof gP!=="function" || gP.__aichat) return;
      var _g=gP;
      var w=function(k){ var r=_g.apply(this,arguments); try{ if(k==="verlauf") renderVerlauf(); }catch(e){} return r; };
      w.__aichat=1; w.__clean=_g.__clean; w.__modpack=_g.__modpack; w.__hub=_g.__hub;
      gP=w; if(typeof window!=="undefined") window.gP=w;
    }catch(e){}
  }

  function tick(){ wrapDoSend(); wrapNC(); addVerlaufNav(); wrapGPForVerlauf(); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",function(){ tick(); setInterval(tick,1200); });
  else { tick(); setInterval(tick,1200); }
})();}catch(e){}
</script>
FEHTML;

$nn = 0;
$src = preg_replace('/<\/body>/', $bundle . "\n</body>", $src, 1, $nn);
if (!$nn) exit("HATA: </body> bulunamadi — yazilmadi.\n");

$tmp = tempnam(sys_get_temp_dir(), 'idx') . '.php';
file_put_contents($tmp, $src);
$lo = []; $rc = 0;
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc);
@unlink($tmp);
if ($rc !== 0) { echo "LINT HATASI — index.php degistirilmedi:\n  " . implode("\n  ", $lo) . "\n"; exit; }
file_put_contents($file, $src);
echo "✓ CH_AICHAT_FE uygulandi.\n";
echo "Test: 'Kirami arttirdi ne yapmaliyim' gibi belirsiz bir seyle konus -> gercek sohbet cevabi gelmeli.\n";
echo "      Sol menude Chat'in altinda '🕘 Verlauf' -> gecmis sohbetler.\n";
