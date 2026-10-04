<?php
/**
 * ChatHelp — apply-letter-analyzer (CH_LETTER_ANALYZER)
 *  YENI HIZMET: "📨 Mektup Analizi" — kullanicinin ALDIGI bir yaziyi
 *  (Mieterhöhung, Abmahnung, Bescheid, ceza vb.) yapistirip: ne anlama
 *  geldigini, sürelerini, haklarini ve ne yapmasi gerektigini AI'dan
 *  ogrenmesini saglar; istenirse ayni baglamda cevap dilekcesi de
 *  uretilir (mevcut [[DOC]]/PDF/anonimlestirme altyapisi aynen kullanilir
 *  — hicbir yeni backend eklenmez, sadece MEVCUT chAiChatTurn() cagrilir).
 *  Universal (ulkeye ozgu degil) — mevcut ulke-farkinda hukuk promptunu
 *  otomatik kullanir.
 *  GEREKLI: apply-aichat-frontend.php (CH_AICHAT_FE) + apply-aichat-docpdf.php.
 * KULLANIM: pull-updates.php?files=apply-letter-analyzer.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-letter-analyzer BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_LETTER_ANALYZER') !== false) exit("Zaten ekli (CH_LETTER_ANALYZER).\n");
if (strpos($src, 'CH_AICHAT_FE') === false) exit("HATA: apply-aichat-frontend.php kurulu degil.\n");
if (strpos($src, 'CH_AICHAT_DOCPDF') === false) exit("HATA: apply-aichat-docpdf.php kurulu degil.\n");
file_put_contents($file . '.bak-letteranalyzer-' . date('Ymd-His'), $src);

/* ── 1) chAiChatTurn'u window'a ac (zaten deploy edilmis dosyaya cerrahi ekleme) ── */
$oldTick = 'function tick(){ wrapDoSend(); wrapNC(); addVerlaufNav(); wrapGPForVerlauf(); }';
$newTick = 'function tick(){ wrapDoSend(); wrapNC(); addVerlaufNav(); wrapGPForVerlauf(); try{ window.__aiChatTurn=chAiChatTurn; }catch(e){} }';
$c1 = 0; $src = str_replace($oldTick, $newTick, $src, $c1);
if ($c1 !== 1) exit("HATA: chAiChatTurn expose anchor tam 1 kez eslesmedi (c=$c1) — degistirilmedi.\n");
echo "  ✓ [1] chAiChatTurn window'a acildi\n";

/* ── 2) yeni modul: panel + nav + home card ── */
$bundle = <<<'LAHTML'
<style id="ch-letter-analyzer-css">
#pnl-letter{position:fixed;inset:0;z-index:520;background:var(--bg,#17171d);display:none;flex-direction:column;overflow:hidden}
#pnl-letter.on{display:flex}
.la-topbar{display:flex;align-items:center;gap:10px;padding:14px 16px;border-bottom:1px solid var(--bdr,rgba(255,255,255,.1));background:var(--s1,#1d1d24);flex-shrink:0}
.la-back{width:38px;height:38px;border-radius:12px;border:1px solid rgba(212,168,74,.45);background:rgba(212,168,74,.09);color:var(--gold,#d4a84a);font-size:22px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center}
.la-back:hover{background:linear-gradient(135deg,#d4a84a,#f0c860);color:#1a1400}
.la-ttl{font-size:17px;font-weight:800}
.la-scroll{flex:1;overflow-y:auto;padding:18px}
.la-wrap{max-width:600px;margin:0 auto}
.la-sub{font-size:13.5px;color:var(--t3,#a8a8d0);line-height:1.6;margin-bottom:16px}
.la-ta{width:100%;min-height:220px;padding:14px;border-radius:14px;border:1px solid var(--bdr,rgba(255,255,255,.14));background:var(--s2,#25252e);color:var(--t1,#fff);font-size:13.5px;font-family:inherit;line-height:1.6;resize:vertical}
.la-ta:focus{outline:none;border-color:rgba(212,168,74,.55)}
.la-cnt{text-align:right;font-size:11px;color:var(--t3,#a8a8d0);margin-top:5px}
.la-go{width:100%;margin-top:14px;padding:14px;border-radius:13px;border:none;background:linear-gradient(135deg,#d4a84a,#f0c860);color:#1a1400;font-size:14.5px;font-weight:800;cursor:pointer;box-shadow:0 8px 22px rgba(212,168,74,.3)}
.la-go:disabled{opacity:.5;cursor:default}
.la-note{font-size:11.5px;color:var(--t3,#a8a8d0);margin-top:12px;line-height:1.55}
.la-examples{display:flex;flex-wrap:wrap;gap:7px;margin-bottom:14px}
.la-ex{padding:7px 12px;border-radius:100px;border:1px solid rgba(212,168,74,.3);background:rgba(212,168,74,.06);color:var(--gold,#d4a84a);font-size:11.5px;font-weight:700;cursor:pointer;font-family:inherit}
</style>
<script id="ch-letter-analyzer">
/* CH_LETTER_ANALYZER */
try{(function(){
  function esc(s){ return String(s==null?"":s).replace(/[&<>"]/g,function(c){return{"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];}); }
  function UIL(){ try{ return (window.chUIL?window.chUIL():(localStorage.getItem("ch_uilang")||"de")); }catch(e){ return "de"; } }
  var T={
    de:{ttl:"Schreiben verstehen",sub:"Haben Sie einen Brief, Bescheid oder eine Abmahnung erhalten? Fügen Sie den Text hier ein — wir erklären, was er bedeutet, welche Fristen gelten und was Sie tun können. Auf Wunsch entwerfen wir direkt eine Antwort.",ph:"Text des erhaltenen Schreibens hier einfügen…",go:"🔍 Analysieren",note:"Ihre eigenen Daten (Name/Adresse) werden dabei nie an die KI gesendet — wie überall bei ChatHelp.",ex1:"Mieterhöhung",ex2:"Abmahnung",ex3:"Bescheid/Bußgeld",card:"Mektup Analizi"},
    tr:{ttl:"Mektubu Anlayın",sub:"Bir mektup, tebligat veya ihtar mı aldınız? Metni buraya yapıştırın — ne anlama geldiğini, sürelerini ve ne yapabileceğinizi açıklayalım. İstersen hemen bir cevap taslağı da hazırlarız.",ph:"Aldığınız yazının metnini buraya yapıştırın…",go:"🔍 Analiz Et",note:"Kendi bilgileriniz (ad/adres) hiçbir zaman yapay zekâya gönderilmez — ChatHelp'te her zaman olduğu gibi.",ex1:"Kira zammı",ex2:"İhtar/uyarı",ex3:"Tebligat/ceza",card:"Mektup Analizi"},
    en:{ttl:"Understand a letter",sub:"Received a letter, notice or warning? Paste the text here — we'll explain what it means, what deadlines apply, and what you can do. We can also draft a reply right away.",ph:"Paste the text of the letter you received…",go:"🔍 Analyze",note:"Your own data (name/address) is never sent to the AI — as always with ChatHelp.",ex1:"Rent increase",ex2:"Warning letter",ex3:"Notice/fine",card:"Letter analysis"}
  };
  function L(){ return T[UIL()]||T.en; }

  function panel(){
    var p=document.getElementById("pnl-letter");
    if(!p){ p=document.createElement("div"); p.className="pnl"; p.id="pnl-letter"; document.body.appendChild(p); }
    return p;
  }
  function render(){
    var l=L(), p=panel();
    p.innerHTML='<div class="la-topbar"><button class="la-back" id="la-back">‹</button><div class="la-ttl">📨 '+esc(l.ttl)+'</div></div>'
     +'<div class="la-scroll"><div class="la-wrap">'
     +'<div class="la-sub">'+esc(l.sub)+'</div>'
     +'<div class="la-examples">'
     +'<button type="button" class="la-ex" data-ex="'+esc(l.ex1)+'">'+esc(l.ex1)+'</button>'
     +'<button type="button" class="la-ex" data-ex="'+esc(l.ex2)+'">'+esc(l.ex2)+'</button>'
     +'<button type="button" class="la-ex" data-ex="'+esc(l.ex3)+'">'+esc(l.ex3)+'</button></div>'
     +'<textarea class="la-ta" id="la-text" placeholder="'+esc(l.ph)+'" maxlength="3500"></textarea>'
     +'<div class="la-cnt" id="la-cnt">0 / 3500</div>'
     +'<button type="button" class="la-go" id="la-go" disabled>'+esc(l.go)+'</button>'
     +'<div class="la-note">🔒 '+esc(l.note)+'</div>'
     +'</div></div>';
    document.getElementById("la-back").addEventListener("click",function(){ try{ gP("chat"); }catch(e){} });
    var ta=document.getElementById("la-text"), cnt=document.getElementById("la-cnt"), go=document.getElementById("la-go");
    ta.addEventListener("input",function(){
      cnt.textContent=ta.value.length+" / 3500";
      go.disabled=ta.value.trim().length<15;
    });
    p.querySelectorAll(".la-ex").forEach(function(b){
      b.addEventListener("click",function(){ ta.value=(ta.value?ta.value+"\n\n":"")+"["+b.getAttribute("data-ex")+"] "; ta.dispatchEvent(new Event("input")); ta.focus(); });
    });
    go.addEventListener("click",function(){
      var txt=ta.value.trim(); if(txt.length<15) return;
      var lead=UIL()==="tr"
        ? "Aşağıdaki yazıyı aldım. Lütfen ne anlama geldiğini, varsa süreleri ve haklarımı açıkla, ne yapmam gerektiğini söyle. Gerekirse birlikte bir cevap yazısı hazırlayalım:\n\n"
        : (UIL()==="de"
          ? "Ich habe folgendes Schreiben erhalten. Bitte erkläre mir, was es bedeutet, welche Fristen und Rechte ich habe, und was ich tun sollte. Wenn sinnvoll, lass uns gemeinsam eine Antwort entwerfen:\n\n"
          : "I received the following letter. Please explain what it means, any deadlines and rights I have, and what I should do. If it makes sense, let's draft a reply together:\n\n");
      try{ gP("chat"); }catch(e){}
      setTimeout(function(){
        try{ if(typeof window.__aiChatTurn==="function") window.__aiChatTurn(lead+txt); }catch(e){}
      },200);
    });
  }

  function addNav(){
    if(document.getElementById("nav-letter")) return;
    var l=L();
    var ref=document.getElementById("nav-hub")||document.querySelector(".sb-photo-btn");
    if(!ref||!ref.parentNode) return;
    var nav=document.createElement("div"); nav.className="sn sb-photo-btn"; nav.id="nav-letter";
    nav.setAttribute("onclick","gP('letter')"); nav.style.cssText="cursor:pointer";
    nav.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="var(--gold,#d4a84a)" stroke-width="1.8" stroke-linecap="round" style="width:20px;height:20px;flex-shrink:0"><path d="M4 4h16v16H4z"/><path d="M4 6l8 7 8-7"/></svg>'
      +'<div><div class="sb-photo-t" id="nav-letter-t">'+esc(l.ttl)+'</div></div>';
    ref.parentNode.insertBefore(nav, ref.nextSibling);
  }
  function addHomeCard(){
    if(document.getElementById("ch-letter-card")) return;
    var anchor=document.querySelector(".wlc-quick")||document.querySelector(".wlc-quick-label");
    if(!anchor) return;
    var l=L();
    var c=document.createElement("div"); c.id="ch-letter-card";
    c.style.cssText="display:flex;align-items:center;gap:12px;margin:12px 0;padding:13px 15px;border:1px solid rgba(212,168,74,.3);border-radius:14px;cursor:pointer;background:linear-gradient(135deg,rgba(212,168,74,.08),rgba(212,168,74,.02))";
    c.innerHTML='<div style="font-size:24px">📨</div><div style="flex:1"><div style="font-size:13.5px;font-weight:800;color:#fff">'+esc(l.ttl)+'</div>'
      +'<div style="font-size:11.5px;color:var(--t3,#a8a8d0);margin-top:2px">'+esc(l.card)+'</div></div><div style="font-size:16px;color:var(--gold,#d4a84a)">›</div>';
    c.addEventListener("click",function(){ try{ gP("letter"); }catch(e){} });
    anchor.parentNode.insertBefore(c, anchor);
  }

  function boot(){ addNav(); addHomeCard(); if(!document.getElementById("pnl-letter")){ panel(); render(); } }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",boot); else boot();
  setInterval(function(){
    try{
      addNav(); addHomeCard();
      var t=document.getElementById("nav-letter-t"); if(t&&t.textContent!==L().ttl) t.textContent=L().ttl;
    }catch(e){}
  },1000);
})();}catch(e){}
</script>
LAHTML;

$p = strrpos($src, '</body>');
if ($p === false) exit("HATA: </body> bulunamadi — yazilmadi.\n");
$src = substr($src, 0, $p) . $bundle . "\n" . substr($src, $p);

$tmp = tempnam(sys_get_temp_dir(), 'idx') . '.php';
file_put_contents($tmp, $src);
$lo = []; $rc = 0;
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc);
@unlink($tmp);
if ($rc !== 0) { echo "LINT HATASI — index.php degistirilmedi:\n  " . implode("\n  ", $lo) . "\n"; exit; }
file_put_contents($file, $src);
echo "\n✓ CH_LETTER_ANALYZER uygulandi. '📨 Mektup Analizi' — sol menu + ana sayfa kartinda.\n";
echo "Test: bir yazi yapistir -> Analiz Et -> chat'e gecer, AI aciklar/sorar, gerekirse [[DOC]] cevap taslagi uretir.\n";
