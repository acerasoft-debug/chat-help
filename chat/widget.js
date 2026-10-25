/* ChatHelp Website-Widget — Einbinden: <script src="https://chat-help.com/chat/widget.js" data-key="wk_…" [data-color="#2a2a3a"] [data-position="right|left"] async></script>
 * Schnellwahl-Knöpfe + Folge-Vorschläge, „Mit einem Menschen sprechen“ (Rückrufseite bzw. Nachricht an den Agenten), 13 Sprachen (Texte von widget-api.php GET ui/chips/human),
 * Sprachauswahl (gemerkt in localStorage), barrierearm (role=log, Fokusfalle, Esc, Touch-Ziele ≥ 44 px, Textfarbe nach Kontrast), Telefon ≤ 480 px Vollbild.
 * KI-Text nur per textContent (Links nur http/https, **fett**). Eigenes Shadow-DOM, einzige globale Variable: window.ChatHelpWidget {open, close, toggle, setLang}. */
(function(){
  'use strict';
  if(window.ChatHelpWidget) return;
  var S=document.currentScript||(function(){var a=document.querySelectorAll('script[data-key][src*="widget.js"]');return a[a.length-1];})();
  var KEY=S&&S.getAttribute('data-key')||''; if(!KEY) return;
  var API=(S.src||'').replace(/widget\.js.*$/,'widget-api.php'), POS=S.getAttribute('data-position')==='left'?'left':'right', CSSCOL=S.getAttribute('data-color')||'#2a2a3a';
  var NS=window.ChatHelpWidget={open:function(){},close:function(){},toggle:function(){},setLang:function(){}};
  var FB={de:{open:'Chat öffnen',close:'Chat schließen',ph:'Nachricht schreiben …',send:'Senden',typing:'schreibt …',online:'online',agent:'Digitaler Assistent',powered:'powered by {brand}',you:'Sie',log:'Chatverlauf',sugg:'Vorschläge',newtab:'öffnet in neuem Tab',
      err_net:'Keine Verbindung – Ihre Nachricht wurde nicht gesendet.',retry:'Erneut senden',err_busy:'Gerade ist viel los. Bitte versuchen Sie es gleich noch einmal.',err_na:'Der Assistent antwortet gerade nicht.',err:'Das hat leider nicht geklappt. Bitte versuchen Sie es erneut.',
      human:'Mit einem Menschen sprechen',human_t:'Ich möchte bitte mit einem Menschen sprechen.',call:'Anrufen: {phone}',lang:'Sprache',greet:'Hallo! Wie kann ich helfen?'},
    en:{open:'Open chat',close:'Close chat',ph:'Write a message …',send:'Send',typing:'typing …',online:'online',agent:'Digital assistant',powered:'powered by {brand}',you:'You',log:'Chat history',sugg:'Suggestions',newtab:'opens in a new tab',
      err_net:'No connection – your message was not sent.',retry:'Send again',err_busy:'It’s busy right now. Please try again in a moment.',err_na:'The assistant isn’t responding right now.',err:'Sorry, that didn’t work. Please try again.',
      human:'Talk to a person',human_t:'I’d like to talk to a person, please.',call:'Call: {phone}',lang:'Language',greet:'Hi! How can I help?'}};
  var LS='chw:lang:'+KEY.slice(-12), SS='chw:hist:'+KEY.slice(-12);
  function store(k,v){ try{ if(v===undefined) return window.localStorage.getItem(k); window.localStorage.setItem(k,v); }catch(e){} return null; }
  function sess(v){ try{ if(v===undefined) return JSON.parse(window.sessionStorage.getItem(SS)||'null'); window.sessionStorage.setItem(SS,JSON.stringify(v)); }catch(e){} return null; }
  /* Besuch-Kennung (zufällig, nur in dieser Browser-Sitzung): fasst die Nachrichten eines Besuchs in der Übersicht des Betriebs zusammen */
  var SID=(function(){ var k='chw:sid:'+KEY.slice(-12), v=''; try{ v=window.sessionStorage.getItem(k)||''; }catch(e){}
    if(!/^[A-Za-z0-9_-]{8,64}$/.test(v)){ v=''; var a='abcdefghijklmnopqrstuvwxyz0123456789'; for(var i=0;i<24;i++) v+=a.charAt(Math.floor(Math.random()*a.length)); try{ window.sessionStorage.setItem(k,v); }catch(e){} }
    return v; })();
  var lang=store(LS)||'', chosen=!!lang, meta={}, ui=null, hist=[], isOpen=false, busy=false, started=false, locked=null, greetEl=null;

  function boot(){
    /* Farbe: beliebige CSS-Farbe → rgb (ungültig → Standard); Textfarbe nach WCAG-Kontrast */
    var probe=document.createElement('i'); probe.style.display='none'; probe.style.color='rgb(1, 2, 3)'; probe.style.color=CSSCOL; document.body.appendChild(probe);
    var cs=getComputedStyle(probe).color, nums=cs.match(/[\d.]+/g); document.body.removeChild(probe);
    var BG=(!nums||cs==='rgb(1, 2, 3)')?[42,42,58]:[+nums[0],+nums[1],+nums[2]], WH=[255,255,255], DK=[17,24,39];
    var cw=contrast(BG,WH), light=!(cw>=4.5||cw>=contrast(BG,DK)), ACC='rgb('+BG.join(',')+')', FG=light?'#111827':'#fff';
    var RING=cw>=3?ACC:'#111827', LINK=cw>=4.5?ACC:'#1d4ed8', HOV=light?'rgba(0,0,0,.08)':'rgba(255,255,255,.16)', TINT='rgba('+BG.join(',')+',.09)';
    var host=document.createElement('div'); host.setAttribute('data-chathelp-widget',''); document.body.appendChild(host);
    var root=host.attachShadow?host.attachShadow({mode:'open'}):host, side=POS;
    var css=':host{all:initial}.chw{font:15px/1.45 system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,"Noto Sans",sans-serif;color:#1a1d29;-webkit-text-size-adjust:100%}'
     +'.chw *,.chw *::before,.chw *::after{box-sizing:border-box}:where(.chw) button,:where(.chw) input,:where(.chw) select{font:inherit;color:inherit;margin:0;letter-spacing:normal}.chw svg{display:block;flex:none}'
     +'.chw-sr{position:absolute!important;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}'
     +'.chw :focus{outline:none}.chw .chw-p:focus-visible{outline:none}.chw :focus-visible{outline:3px solid '+RING+';outline-offset:2px}'
     +'.chw-b{position:fixed;bottom:calc(20px + env(safe-area-inset-bottom));'+side+':20px;width:60px;height:60px;border-radius:50%;background:'+ACC+';color:'+FG+';border:0;box-shadow:0 10px 30px rgba(0,0,0,.25);cursor:pointer;z-index:2147483000;display:flex;align-items:center;justify-content:center;transition:transform .15s}'
     +'.chw-b:hover{transform:scale(1.05)}.chw .chw-b:focus-visible{outline:3px solid #fff;outline-offset:2px;box-shadow:0 0 0 7px #111827}.chw .ic-x{display:none}.chw.open .ic-x{display:block}.chw.open .ic-c{display:none}'
     +'.chw-p{position:fixed;bottom:calc(92px + env(safe-area-inset-bottom));'+side+':20px;width:380px;max-width:calc(100vw - 32px);height:620px;max-height:calc(100vh - 120px);background:#fff;border-radius:18px;box-shadow:0 24px 60px rgba(0,0,0,.28);display:flex;flex-direction:column;overflow:hidden;z-index:2147483001}'
     +'.chw-p[hidden]{display:none!important}'
     +'.chw-h{background:'+ACC+';color:'+FG+';padding:8px 6px 8px 14px;display:flex;align-items:center;gap:8px;flex:none}'
     +'.chw-av{width:36px;height:36px;border-radius:50%;background:'+HOV+';display:flex;align-items:center;justify-content:center;font-weight:700;font-size:16px;flex:none}'
     +'.chw-hd{flex:1;min-width:0}.chw-tt{font-weight:650;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.chw-st{font-size:12.5px;display:flex;align-items:center;gap:6px;white-space:nowrap;overflow:hidden}'
     +'.chw-dot{width:8px;height:8px;border-radius:50%;background:#16a34a;box-shadow:0 0 0 2px '+(light?'rgba(255,255,255,.7)':'rgba(255,255,255,.45)')+';flex:none}'
     +'.chw-lg{position:relative;display:flex;align-items:center;gap:3px;min-width:44px;height:44px;padding:0 8px;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;flex:none}.chw-lg:hover{background:'+HOV+'}'
     +'.chw-lg select{position:absolute;top:0;left:0;width:100%;height:100%;opacity:0;cursor:pointer;border:0;-webkit-appearance:none;appearance:none;font-size:16px}.chw-lg:focus-within{outline:3px solid '+FG+';outline-offset:-3px}.chw-lg[hidden]{display:none}'
     +'.chw-x{width:44px;height:44px;border:0;background:transparent;color:'+FG+';border-radius:10px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex:none}.chw-x:hover{background:'+HOV+'}.chw .chw-x:focus-visible{outline:3px solid '+FG+';outline-offset:-3px}'
     +'.chw-sc{flex:1;min-height:0;overflow-y:auto;overflow-x:hidden;background:#f5f6fa;overscroll-behavior:contain}.chw-m{padding:16px 14px 8px;display:flex;flex-direction:column;gap:10px}'
     +'.chw-msg{max-width:86%;padding:10px 13px;border-radius:16px;white-space:pre-wrap;overflow-wrap:anywhere;word-break:break-word}'
     +'.chw-u{align-self:flex-end;background:'+ACC+';color:'+FG+';border-bottom-right-radius:5px}'
     +'.chw-a{align-self:flex-start;background:#fff;border:1px solid #e1e3ea;border-bottom-left-radius:5px;color:#1a1d29}.chw-a a{color:'+LINK+';text-decoration:underline;text-underline-offset:2px}.chw-a strong{font-weight:700}'
     +'.chw-ty{display:flex;align-items:center;gap:4px;color:#555a6a;font-size:13px}.chw-ty i{width:7px;height:7px;border-radius:50%;background:#7c8193;animation:chwb 1.2s infinite ease-in-out}.chw-ty i:nth-of-type(2){animation-delay:.15s}.chw-ty i:nth-of-type(3){animation-delay:.3s}.chw-ty span.chw-tl{margin-left:6px}'
     +'@keyframes chwb{0%,80%,100%{opacity:.35;transform:translateY(0)}40%{opacity:1;transform:translateY(-3px)}}'
     +'.chw-er{width:86%;background:#fff5f6;border-color:#f0c2cb;color:#7a1730}.chw-a a.chw-act{color:#1a1d29;text-decoration:none}.chw-acts{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;white-space:normal}'
     +'.chw-act{display:inline-flex;align-items:center;gap:6px;min-height:44px;padding:0 14px;border-radius:12px;border:1.5px solid '+RING+';background:#fff;color:#1a1d29;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none}.chw-act.pri{background:'+ACC+';color:'+FG+';border-color:'+ACC+'}'
     +'.chw-ch{display:flex;flex-wrap:wrap;gap:8px;padding:2px 14px 14px}.chw-ch[hidden]{display:none}'
     +'.chw-chip{display:inline-flex;align-items:center;gap:6px;min-height:44px;max-width:100%;padding:0 15px;border-radius:22px;border:1.5px solid '+RING+';background:#fff;color:#1a1d29;font-size:14px;font-weight:600;line-height:1.25;text-align:left;cursor:pointer;text-decoration:none}.chw-chip:hover,.chw-act:hover{background:'+TINT+'}.chw-act.pri:hover{background:'+ACC+';filter:brightness(1.08)}'
     +'.chw-f{display:flex;gap:8px;padding:10px 12px;border-top:1px solid #e1e3ea;background:#fff;flex:none;align-items:center}'
     +'.chw-f input{flex:1;min-width:0;height:46px;border:1.5px solid #8a8fa0;border-radius:12px;padding:0 14px;font-size:16px;background:#fff;color:#1a1d29}.chw-f input::placeholder{color:#636878;opacity:1}.chw .chw-f input:focus-visible,.chw .chw-f input:focus{border-color:'+RING+';outline:2px solid '+RING+';outline-offset:0}'
     +'.chw-s{width:46px;height:46px;border:0;border-radius:12px;background:'+ACC+';color:'+FG+';display:flex;align-items:center;justify-content:center;cursor:pointer;flex:none}.chw-s:disabled{opacity:.55;cursor:default}'
     +'.chw-c{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0 8px;padding:0 8px calc(2px + env(safe-area-inset-bottom)) 4px;background:#fff;flex:none}'
     +'.chw-hu{display:inline-flex;align-items:center;gap:7px;min-height:44px;padding:0 10px;border:0;border-radius:10px;background:transparent;color:#1a1d29;font-size:13.5px;font-weight:600;cursor:pointer;text-decoration:none}.chw-hu:hover{background:#f0f1f5}'
     +'.chw-pw{font-size:11.5px;color:#5c6170;padding:4px}.chw-pw a{color:inherit;text-decoration:underline;display:inline-block;min-height:24px;line-height:24px}'
     +'@media (prefers-reduced-motion:reduce){.chw-ty i{animation:none}.chw-b{transition:none}}'
     +'@media (max-width:480px){.chw-p{top:0;left:0;right:0;bottom:0;width:100%;max-width:none;height:100vh;height:100dvh;max-height:none;border-radius:0}.chw-h{padding-top:calc(8px + env(safe-area-inset-top))}.chw.open .chw-b{display:none}.chw-msg{max-width:90%}}';
    var ic={chat:'<svg class="ic-c" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20.5l1.4-4.6A8 8 0 1 1 21 12z"/></svg>',
      x:function(c,s){return '<svg class="'+c+'" width="'+s+'" height="'+s+'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>';},
      send:'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h13M13 6l6 6-6 6"/></svg>',
      person:'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>'};
    var W=document.createElement('div'); W.className='chw';
    W.innerHTML='<style>'+css+'</style><button class="chw-b" type="button" aria-haspopup="dialog" aria-expanded="false">'+ic.chat+ic.x('ic-x',26)+'</button>'
      +'<div class="chw-p" role="dialog" aria-modal="false" tabindex="-1" hidden><div class="chw-h"><div class="chw-av" aria-hidden="true"></div><div class="chw-hd"><div class="chw-tt"></div><div class="chw-st"><span class="chw-dot" aria-hidden="true"></span><span class="chw-stt"></span></div></div>'
      +'<label class="chw-lg" hidden><span class="chw-lgc" aria-hidden="true"></span><svg width="10" height="10" viewBox="0 0 10 10" aria-hidden="true"><path d="M1 3l4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.6"/></svg><select></select></label>'
      +'<button class="chw-x" type="button">'+ic.x('',22)+'</button></div>'
      +'<div class="chw-sc"><div class="chw-m" role="log" aria-live="polite" aria-relevant="additions"></div><div class="chw-ch" role="group" hidden></div></div>'
      +'<form class="chw-f" novalidate><input type="text" autocomplete="off" maxlength="2000" enterkeyhint="send"><button class="chw-s" type="submit">'+ic.send+'</button></form>'
      +'<div class="chw-c"><span class="chw-hus"></span><span class="chw-pw"></span></div></div>';
    root.appendChild(W);
    var q=function(s){return W.querySelector(s);}, B=q('.chw-b'), P=q('.chw-p'), TT=q('.chw-tt'), AV=q('.chw-av'), STT=q('.chw-stt'), LG=q('.chw-lg'), LGC=q('.chw-lgc'), SEL=q('.chw-lg select'), X=q('.chw-x'),
      SC=q('.chw-sc'), M=q('.chw-m'), C=q('.chw-ch'), F=q('.chw-f'), I=F.querySelector('input'), SB=q('.chw-s'), HUS=q('.chw-hus'), PW=q('.chw-pw');
    var uid='chw'+Math.random().toString(36).slice(2,8); TT.id=uid+'t'; P.id=uid+'p'; P.setAttribute('aria-labelledby',TT.id); B.setAttribute('aria-controls',P.id);
    var mq=window.matchMedia?window.matchMedia('(max-width: 480px)'):null, coarse=window.matchMedia&&window.matchMedia('(pointer: coarse)').matches;

    function el(t,c){ var e=document.createElement(t); if(c) e.className=c; return e; }
    function sr(t){ var s=el('span','chw-sr'); s.textContent=t; return s; }
    function scroll(){ SC.scrollTop=SC.scrollHeight; }
    function safeUrl(u){ try{ var x=new URL(String(u),API); return (x.protocol==='https:'||x.protocol==='http:')?x.href:null; }catch(e){ return null; } }
    function link(u,label,cls){ var h=safeUrl(u); if(!h) return null; var a=el('a',cls); a.href=h; a.target='_blank'; a.rel='noopener noreferrer'; a.textContent=label; return a; }
    /* KI-Text sicher darstellen: nur Textknoten, <strong> für **fett**, <a> für http(s)-Links ([Text](URL) oder nackte URL) */
    function rich(d,t){
      var re=/\*\*([^*\n](?:[^*\n]*?))\*\*|\[([^\]\n]{1,200})\]\((https?:\/\/[^\s)]+)\)|(https?:\/\/[^\s<>"'`]+)/g, i=0, m;
      while((m=re.exec(t))){
        if(m.index>i) d.appendChild(document.createTextNode(t.slice(i,m.index)));
        if(m[1]!==undefined){ var b=el('strong'); b.textContent=m[1]; d.appendChild(b); }
        else { var u=m[3]||m[4], tail=''; if(m[4]){ var p=u.match(/[.,;:!?)\]]+$/); if(p){ tail=p[0]; u=u.slice(0,-tail.length); } }
          d.appendChild(link(u,m[2]||u)||document.createTextNode(m[2]||u)); if(tail) d.appendChild(document.createTextNode(tail)); }
        i=re.lastIndex;
      }
      if(i<t.length) d.appendChild(document.createTextNode(t.slice(i)));
    }
    function bubble(role,t){ var d=el('div','chw-msg '+(role==='user'?'chw-u':'chw-a')); d.appendChild(sr((role==='user'?ui.you:(meta.title||ui.agent))+': '));
      if(role==='user') d.appendChild(document.createTextNode(t)); else rich(d,t); M.appendChild(d); scroll(); return d; }
    function greet(){ if(greetEl||started) return; greetEl=bubble('bot',meta.greeting||ui.greet); }
    function setChips(list){ C.textContent=''; (list||[]).slice(0,4).forEach(function(c){ if(!c||!c.label) return; var b;
        if(c.url){ b=link(c.url,c.label,'chw-chip'); if(!b) return; b.appendChild(sr(' ('+ui.newtab+')')); b.insertAdjacentHTML('beforeend','<span aria-hidden="true">↗</span>'); }
        else { b=el('button','chw-chip'); b.type='button'; b.textContent=c.label; b.addEventListener('click',function(){ send(c.text||c.label); }); }
        C.appendChild(b); });
      C.hidden=!C.firstChild; scroll(); }
    /* Mensch-Kontakt: Rückrufseite (neuer Tab) → im Fehlerfall Telefon → sonst Nachricht an den Agenten */
    function humanEl(inErr){ var h=meta.human||{}, a;
      if(h.url && (a=link(h.url,inErr?(h.url_label||ui.human):ui.human,inErr?'chw-act':'chw-hu'))){ a.title=h.url_label||''; a.appendChild(sr(' ('+ui.newtab+')')); }
      else if(inErr && h.phone){ a=el('a','chw-act'); a.href='tel:'+String(h.phone).replace(/[^\d+]/g,''); a.textContent=(ui.call||'{phone}').replace('{phone}',h.phone); }
      else if(inErr) return null;
      else { a=el('button','chw-hu'); a.type='button'; a.textContent=ui.human; a.addEventListener('click',function(){ send(h.text||ui.human_t); }); }
      if(!inErr) a.insertAdjacentHTML('afterbegin',ic.person); return a; }
    function texts(){
      var L=(meta.langs||[]).filter(function(x){return x&&x.code===lang;})[0]; W.setAttribute('lang',L&&L.tag||lang||'de');
      B.setAttribute('aria-label',isOpen?ui.close:ui.open); X.setAttribute('aria-label',ui.close); X.title=ui.close; TT.textContent=meta.title||ui.agent;
      var tt=String(meta.title||ui.agent||'A').trim(); AV.textContent=((Array.from?Array.from(tt)[0]:tt.charAt(0))||'A').toUpperCase(); STT.textContent=ui.agent+' · '+ui.online;
      M.setAttribute('aria-label',ui.log); C.setAttribute('aria-label',ui.sugg); I.placeholder=ui.ph; I.setAttribute('aria-label',ui.ph.replace(/\s*…\s*$/,'')); SB.setAttribute('aria-label',ui.send); SB.title=ui.send;
      var ls=meta.langs||[]; LG.hidden=ls.length<2; SEL.textContent=''; ls.forEach(function(x){ var o=el('option'); o.value=x.code; o.textContent=x.name; o.lang=x.tag||x.code; SEL.appendChild(o); });
      SEL.value=lang; SEL.setAttribute('aria-label',ui.lang+': '+(L?L.name:lang)); LGC.textContent=String(lang||'').toUpperCase();
      HUS.textContent=''; HUS.appendChild(humanEl(false));
      PW.textContent=''; var pp=String(ui.powered||'powered by {brand}').split('{brand}'); PW.appendChild(document.createTextNode(pp[0]||'')); PW.appendChild(link('https://chat-help.com','ChatHelp')); PW.appendChild(document.createTextNode(pp[1]||''));
    }
    function apply(j){ meta=j; ui=merge(j.ui); lang=j.lang||lang||'de'; texts();
      if(!started){ if(greetEl){ greetEl.textContent=''; greetEl.appendChild(sr((meta.title||ui.agent)+': ')); rich(greetEl,meta.greeting||ui.greet); } setChips(meta.chips); } else setChips([]); }
    function merge(u){ var b=FB[(lang||navigator.language||'de').slice(0,2)==='de'?'de':'en'], o={}, k; for(k in b) o[k]=b[k]; if(u) for(k in u) if(u[k]) o[k]=u[k]; return o; }

    function typing(){ var d=el('div','chw-msg chw-a chw-ty'); for(var i=0;i<3;i++){ var x=el('i'); x.setAttribute('aria-hidden','true'); d.appendChild(x); } var s=el('span','chw-tl'); s.textContent=ui.typing; d.appendChild(s); M.appendChild(d); scroll(); return d; }
    function fail(msg,retry,human){ var d=el('div','chw-msg chw-a chw-er'), p=el('div'), row=el('div','chw-acts'); p.textContent=msg; d.appendChild(p);
      if(retry){ var b=el('button','chw-act pri'); b.type='button'; b.textContent=ui.retry; b.addEventListener('click',function(){ if(busy) return; if(d.parentNode) d.parentNode.removeChild(d); post(); I.focus(); }); row.appendChild(b); }
      if(human){ var h=humanEl(true); if(h) row.appendChild(h); }
      if(row.firstChild) d.appendChild(row); M.appendChild(d); scroll(); }
    function save(){ sess(hist.slice(-30)); }
    function send(t){ t=String(t||'').trim(); if(!t||busy) return; started=true; setChips([]); bubble('user',t); hist.push({role:'user',content:t}); save(); post(); }
    function post(){ busy=true; SB.disabled=true; M.setAttribute('aria-busy','true'); var ty=typing(), gone=function(){ if(ty.parentNode) ty.parentNode.removeChild(ty); };
      fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({key:KEY,messages:hist.slice(-12),lang:lang||'de',sid:SID})})
        .then(function(r){ return r.json().catch(function(){ return {}; }).then(function(j){ return {s:r.status,j:j||{}}; }); })
        .then(function(x){ gone(); var j=x.j;
          if(x.s===200 && j.reply){ hist.push({role:'assistant',content:String(j.reply)}); save();
            if(j.ui && !chosen && j.lang && j.lang!==lang){ lang=j.lang; ui=merge(j.ui); if(j.human) meta.human=j.human; if(j.start) meta.chips=j.start; texts(); }
            bubble('bot',String(j.reply)); setChips((j.chips||[]).slice(0,2)); }
          else if(x.s===402) fail(j.message||ui.err_na,false,true);
          else if(x.s===429) fail(ui.err_busy,true,true);
          else if(x.s===503) fail(ui.err_na,true,true);
          else fail(ui.err,true,false);
        },function(){ gone(); fail(ui.err_net,true,false); })
        .catch(function(){})
        .then(function(){ busy=false; SB.disabled=false; M.removeAttribute('aria-busy'); });
    }
    function lock(){ if(!isOpen||!mq||!mq.matches||locked) return; var de=document.documentElement, bd=document.body; locked=[de.style.overflow,bd.style.overflow]; de.style.overflow='hidden'; bd.style.overflow='hidden'; P.setAttribute('aria-modal','true'); }
    function unlock(){ if(!locked) return; document.documentElement.style.overflow=locked[0]; document.body.style.overflow=locked[1]; locked=null; P.setAttribute('aria-modal','false'); }
    function open(){ if(isOpen) return; isOpen=true; P.hidden=false; W.classList.add('open'); B.setAttribute('aria-expanded','true'); B.setAttribute('aria-label',ui.close); lock(); greet(); scroll();
      setTimeout(function(){ (coarse?P:I).focus(); },30); }
    function close(){ if(!isOpen) return; isOpen=false; P.hidden=true; W.classList.remove('open'); B.setAttribute('aria-expanded','false'); B.setAttribute('aria-label',ui.open); unlock(); B.focus(); }
    function active(){ return root.activeElement||document.activeElement; }
    function focusables(){ return Array.prototype.filter.call(P.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select'),function(e){ return e.getClientRects().length>0; }); }
    B.addEventListener('click',function(){ isOpen?close():open(); });
    X.addEventListener('click',close);
    W.addEventListener('keydown',function(e){
      if((e.key==='Escape'||e.key==='Esc') && isOpen){ e.preventDefault(); e.stopPropagation(); close(); return; }
      if(e.key!=='Tab'||!isOpen) return; var f=focusables(); if(!f.length) return; var a=active(), first=f[0], last=f[f.length-1];
      if(e.shiftKey && (a===first||a===P||a===B)){ e.preventDefault(); last.focus(); } else if(!e.shiftKey && (a===last||a===B)){ e.preventDefault(); first.focus(); }
    });
    F.addEventListener('submit',function(e){ e.preventDefault(); if(busy) return; var t=I.value.trim(); if(!t) return; I.value=''; send(t); });
    SEL.addEventListener('change',function(){ var v=SEL.value; if(!v||v===lang) return; chosen=true; store(LS,v); lang=v; LGC.textContent=v.toUpperCase(); load(v,function(j){ if(j) apply(j); }); });
    if(mq){ var onMq=function(){ if(isOpen && mq.matches) lock(); else unlock(); }; if(mq.addEventListener) mq.addEventListener('change',onMq); else if(mq.addListener) mq.addListener(onMq); }
    NS.open=open; NS.close=close; NS.toggle=function(){ isOpen?close():open(); }; NS.setLang=function(v){ SEL.value=v; if(SEL.value===v) SEL.dispatchEvent(new Event('change')); };
    return {apply:apply, restore:function(h){ greet(); hist=h; started=true; setChips([]); h.forEach(function(m){ bubble(m.role==='assistant'?'bot':'user',String(m.content||'')); }); }, fallback:function(){ ui=merge(null); meta={greeting:ui.greet}; lang=lang||((navigator.language||'').slice(0,2)==='de'?'de':'en'); texts(); }};
  }
  function contrast(a,b){ function L(c){ var v=c.map(function(x){ x/=255; return x<=0.03928?x/12.92:Math.pow((x+0.055)/1.055,2.4); }); return 0.2126*v[0]+0.7152*v[1]+0.0722*v[2]; } var x=L(a), y=L(b); return (Math.max(x,y)+0.05)/(Math.min(x,y)+0.05); }
  /* GET: Titel, Begrüßung, Oberflächentexte, Schnellwahl, Mensch-Knopf (Sprache: gewählte → Browser → Standard des Betriebs). cb(null) bei Netzfehler, false bei Ablehnung. */
  function load(l,cb){ fetch(API+'?key='+encodeURIComponent(KEY)+(l?'&lang='+encodeURIComponent(l):'')+'&_='+Date.now(),{cache:'no-store'})
      .then(function(r){ return r.json().catch(function(){ return null; }).then(function(j){ cb(r.ok&&j&&j.ok?j:(r.status>=400&&r.status<500?false:null)); }); },function(){ cb(null); }).catch(function(){}); }
  function init(){
    load(lang,function(j){
      if(j===false){ if(window.console&&console.warn) console.warn('ChatHelp-Widget: Schlüssel ungültig, Widget deaktiviert oder Paket nicht aktiv.'); return; }
      var w=boot(); if(j) w.apply(j); else w.fallback();
      var h=sess(); if(h && h.length) w.restore(h.filter(function(m){ return m && (m.role==='user'||m.role==='assistant') && typeof m.content==='string'; }));
    });
  }
  if(document.body) init(); else document.addEventListener('DOMContentLoaded',init);
})();
