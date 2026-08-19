<?php
/**
 * ChatHelp — apply-fixpack10 (CH_FIXPACK10)
 *  1) Interface-Sprache bolumu kaldirilir — dil, hukuk ulkesinden otomatik gelir
 *  2) Varsayilan tema ANTRASIT'e doner (lacivert 2. secenek)
 *  3) "10x besser als Standard-KI" ibaresi -> "anahtar soru + net hedef" mesaji
 *  4) Konto'daki calismayan 2. Design blogu gizlenir
 *  5) Jobs: Lebenslauf yazma alani yerine kayitli CV kullanimi + CV Studio yonlendirme
 *  5b) CV & Vertrag girisleri ana sayfada gorunur pill olarak eklenir
 *  6) Konto'da "Module" karti: Jobs/CV/Vertrage ac-kapa (kapatan kullanicidan gizlenir)
 *  7) "Chat" navina tiklaninca komple bos, temiz chat (welcome/katalog gizli)
 *  8) PDF: popup yerine iframe ile yazdirma (mobil/WebView'de calisir)
 *  9) Vertrag: zorunlu alanlar dolmadan PDF yok (eksige atlar, kirmizi isaretler); CV: isim zorunlu
 * KULLANIM: pull-updates.php?files=apply-fixpack10.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fixpack10 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_FIXPACK10') !== false) exit("Zaten ekli (CH_FIXPACK10).\n");
file_put_contents($file . '.bak-fixpack10-' . date('Ymd-His'), $src);

/* ── [2] varsayilan tema antrasit ── */
$a = 'function cur(){ try{ return localStorage.getItem("ch_theme")||"navy"; }catch(e){ return "navy"; } }';
$n = 'function cur(){ try{ return localStorage.getItem("ch_theme")||"anth"; }catch(e){ return "anth"; } }';
$src2 = str_replace($a, $n, $src, $c); if($c){ $src=$src2; echo "  ✓ [2] varsayilan tema antrasit\n"; } else echo "  ✗ [2] anchor yok\n";

/* ── [5] jobs: kayitli CV oncelikli ── */
$a = 'var cv=(document.getElementById("jb-cv")&&document.getElementById("jb-cv").value)||"";';
$n = 'var cv=""; try{ var _cvd=JSON.parse(localStorage.getItem("ch_cv_data")||"{}"); if(_cvd&&(_cvd.name||(_cvd.exp&&_cvd.exp.length))){ cv=[_cvd.name,_cvd.role,_cvd.prof].filter(Boolean).join("\n")+"\n"+((_cvd.exp||[]).map(function(x){return [x.d,x.r,x.f,x.t].filter(Boolean).join(" — ");}).join("\n")); } }catch(e){} if(!cv){ cv=(document.getElementById("jb-cv")&&document.getElementById("jb-cv").value)||""; }';
$src2 = str_replace($a, $n, $src, $c); if($c){ $src=$src2; echo "  ✓ [5] jobs kayitli CV kullanimi\n"; } else echo "  ✗ [5] anchor yok\n";

/* ── [8] PDF: popup -> iframe (CV + VST, 2 nokta) ── */
$a = 'var w=window.open("","_blank");
    if(!w){ alert("Popup?"); return; }
    w.document.open(); w.document.write(html); w.document.close();';
$n = 'chPrintDoc(html);';
$src2 = str_replace($a, $n, $src, $c); if($c){ $src=$src2; echo "  ✓ [8] iframe yazdirma ($c nokta)\n"; } else echo "  ✗ [8] anchor yok\n";

/* ── [9] VST zorunlu alan kontrolu ── */
$a = '} else { makePdf(); }';
$n = '} else {
        var missing=null, mi=0;
        try{
          var dd2=getD(CUR);
          for(var si=0; si<c.steps.length && !missing; si++){
            for(var fi=0; fi<c.steps[si].f.length; fi++){
              var ff=c.steps[si].f[fi];
              if(/optional|leer =/i.test(ff[1])) continue;
              if(!String(dd2[ff[0]]||"").trim()){ missing=ff; mi=si; break; }
            }
          }
        }catch(e){}
        if(missing){ STEP=mi; renderStep(); setTimeout(function(){ try{ var el2=document.querySelector(\'input[data-f="\'+missing[0]+\'"]\'); if(el2){ el2.style.borderColor="#e05050"; el2.focus(); } }catch(e){} },150); return; }
        makePdf();
      }';
$src2 = str_replace($a, $n, $src, $c); if($c){ $src=$src2; echo "  ✓ [9] Vertrag zorunlu alanlar\n"; } else echo "  ✗ [9] anchor yok\n";

/* ── [9b] CV: isim zorunlu ── */
$a = 'function makePdf(){
    if(!hasPlan()){ gate(); return; }
    var html=\'<!doctype html><html><head><meta charset="utf-8"><title>\'+esc(DATA.name||"CV")';
$n = 'function makePdf(){
    if(!(DATA.name||"").trim()){ try{ var ne=document.getElementById("cvs-name"); if(ne){ ne.style.borderColor="#e05050"; ne.focus(); } }catch(e){} return; }
    if(!hasPlan()){ gate(); return; }
    var html=\'<!doctype html><html><head><meta charset="utf-8"><title>\'+esc(DATA.name||"CV")';
$src2 = str_replace($a, $n, $src, $c); if($c){ $src=$src2; echo "  ✓ [9b] CV isim zorunlu\n"; } else echo "  ✗ [9b] anchor yok\n";

/* ── JS bundle: 1,3,4,5,5b,6,7 + chPrintDoc ── */
$bundle = <<<'FIXHTML'
<script id="ch-fixpack10">
/* CH_FIXPACK10 — UI duzeltme paketi */
try{(function(){
  function UIL(){ try{ return (window.chUIL?window.chUIL():(localStorage.getItem("ch_uilang")||"de")); }catch(e){ return "de"; } }
  function esc(s){ return String(s==null?"":s).replace(/[&<>"]/g,function(c){return{"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];}); }

  /* [8] iframe yazdirma — popup engellerinden etkilenmez */
  window.chPrintDoc=function(html){
    try{
      var old=document.getElementById("ch-print-frame"); if(old) old.remove();
      html=String(html).replace(/<script>[\s\S]*?<\/script>/g,"");
      var f=document.createElement("iframe"); f.id="ch-print-frame";
      f.style.cssText="position:fixed;right:0;bottom:0;width:1px;height:1px;border:0;opacity:0";
      document.body.appendChild(f);
      var d=f.contentWindow.document;
      d.open(); d.write(html); d.close();
      setTimeout(function(){ try{ f.contentWindow.focus(); f.contentWindow.print(); }catch(e){} },500);
    }catch(e){}
  };

  /* [1] Interface-Sprache bolumunu gizle + dili hukuk ulkesine birak */
  try{
    if(!localStorage.getItem("ch_fix10_lmclr")){
      localStorage.removeItem("ch_lm"); localStorage.removeItem("ch_lang_user");
      localStorage.setItem("ch_fix10_lmclr","1");
    }
  }catch(e){}
  function hideUISprache(){
    try{
      document.querySelectorAll(".ksec-hdr-t").forEach(function(t){
        if(/Interface[- ]?Sprache/i.test(t.textContent||"")){
          var sec=t.closest(".ksec")||t.parentNode&&t.parentNode.parentNode&&t.parentNode.parentNode.parentNode;
          if(sec&&sec.style) sec.style.display="none";
        }
      });
    }catch(e){}
  }

  /* [3] "10x besser als Standard-KI" -> anahtar soru + hedef mesaji */
  var CLAIM={de:"Gezielte Fragen · klares Ziel — präzisere Dokumente als Standard-KI",
    tr:"Anahtar sorular · net hedef — standart yapay zekâdan daha iyi sonuç",
    en:"Targeted questions · a clear goal — better documents than standard AI",
    fr:"Questions ciblées · objectif clair — de meilleurs documents que l'IA standard",
    es:"Preguntas precisas · objetivo claro — mejores documentos que la IA estándar",
    it:"Domande mirate · obiettivo chiaro — documenti migliori dell'IA standard"};
  function fixClaim(){
    try{
      var reps=0;
      var walker=document.querySelectorAll("div,span,p,b,h1,h2,h3,small");
      for(var i=0;i<walker.length;i++){
        var el=walker[i];
        if(el.children.length) continue;
        var t=el.textContent||"";
        if(t.length>140||t.length<8) continue;
        if(/10\s*[x×]/i.test(t) && /(KI|AI|yapay|besser|daha iyi|better)/i.test(t)){
          if(el.dataset.chClaim) continue;
          el.dataset.chClaim="1";
          el.textContent=CLAIM[UIL()]||CLAIM.en;
          reps++;
        }
      }
    }catch(e){}
  }

  /* [4] Konto'daki calismayan 2. Design blogunu gizle */
  function hideDupDesign(){
    try{
      document.querySelectorAll("div,span,p,small").forEach(function(el){
        if(el.children.length) return;
        var t=(el.textContent||"").trim();
        if(t!=="Ihre Wahl wird gespeichert."&&t!=="Ihre Wahl wird gespeichert") return;
        var node=el, hops=0;
        while(node&&hops<6){
          node=node.parentElement; hops++;
          if(!node) break;
          if(node.id==="ch-theme-sw") return;
          if(node.querySelector&&node.querySelector("#ch-theme-sw")) return;
          var btns=node.querySelectorAll("button,.lang-opt,[onclick]");
          if(btns.length>=2){ node.style.display="none"; return; }
        }
        el.style.display="none";
      });
    }catch(e){}
  }

  /* [5] jobs paneli: CV yazma alani yerine kayitli CV + CV Studio linki */
  var JT={de:{has:"📎 Ihr gespeicherter Lebenslauf (CV Studio) wird automatisch verwendet.",none:"Noch kein Lebenslauf — jetzt erstellen:",mk:"📇 Lebenslauf / CV erstellen →"},
    tr:{has:"📎 Kayıtlı özgeçmişin (CV Studio) otomatik kullanılır.",none:"Henüz CV yok — hemen oluştur:",mk:"📇 Lebenslauf / CV oluştur →"},
    en:{has:"📎 Your saved CV (CV Studio) is used automatically.",none:"No CV yet — create one now:",mk:"📇 Create CV →"}};
  function fixJobsCv(){
    try{
      var ta=document.getElementById("jb-cv");
      if(!ta||document.getElementById("jb-cvrow")) return;
      ta.style.display="none";
      var l=JT[UIL()]||JT.en, hasCv=false;
      try{ var d=JSON.parse(localStorage.getItem("ch_cv_data")||"{}"); hasCv=!!(d&&(d.name||(d.exp&&d.exp.length))); }catch(e){}
      var row=document.createElement("div"); row.id="jb-cvrow";
      row.style.cssText="margin-bottom:9px;padding:11px 12px;border:1px solid rgba(212,168,74,.25);border-radius:11px;background:rgba(212,168,74,.06);font-size:12.5px;color:var(--t2,#e0e0ff);line-height:1.5";
      row.innerHTML=(hasCv?esc(l.has):esc(l.none))
        +' <button type="button" id="jb-mkcv" style="display:'+(hasCv?"none":"inline-block")+';margin-top:6px;padding:8px 12px;border-radius:9px;border:1px solid rgba(212,168,74,.4);background:rgba(212,168,74,.12);color:var(--gold,#d4a84a);font-weight:700;font-size:12.5px;cursor:pointer;font-family:inherit">'+esc(l.mk)+'</button>';
      ta.parentNode.insertBefore(row, ta);
      var b=document.getElementById("jb-mkcv");
      if(b) b.addEventListener("click",function(){ try{ gP("cv"); }catch(e){} });
    }catch(e){}
  }

  /* [5b] ana sayfaya CV & Vertrage girisleri (pill) */
  var PT={de:["📇 Lebenslauf & CV","📑 Verträge"],tr:["📇 Özgeçmiş & CV","📑 Sözleşmeler"],en:["📇 CV & Résumé","📑 Contracts"],
    fr:["📇 CV","📑 Contrats"],es:["📇 CV","📑 Contratos"],it:["📇 CV","📑 Contratti"]};
  function addPills(){
    try{
      var q=document.querySelector(".wlc-quick");
      if(!q||document.getElementById("ch-pill-cv")) return;
      var l=PT[UIL()]||PT.en;
      [["ch-pill-cv",l[0],"cv"],["ch-pill-vst",l[1],"vst"]].forEach(function(x){
        var p=document.createElement("div"); p.className="wlc-pill"; p.id=x[0];
        p.style.borderColor="rgba(212,168,74,.4)";
        p.innerHTML=esc(x[1]);
        p.addEventListener("click",function(){ try{ gP(x[2]); }catch(e){} });
        q.appendChild(p);
      });
    }catch(e){}
  }

  /* [6] Konto: Module ac/kapa */
  var MT={de:{t:"Module",j:"Jobsuche",c:"Lebenslauf & CV",v:"Verträge"},tr:{t:"Modüller",j:"İş arama",c:"Özgeçmiş & CV",v:"Sözleşmeler"},en:{t:"Modules",j:"Job search",c:"CV",v:"Contracts"}};
  function mods(){ try{ return JSON.parse(localStorage.getItem("ch_mods")||"{}"); }catch(e){ return {}; } }
  function setMod(k,v){ var m=mods(); m[k]=v?1:0; try{ localStorage.setItem("ch_mods",JSON.stringify(m)); }catch(e){} }
  function modOn(k){ var m=mods(); return m[k]===undefined?true:!!m[k]; }
  function applyMods(){
    try{
      [["jobs","nav-jobs"],["cv","nav-cv"],["vst","nav-vst"]].forEach(function(x){
        var el=document.getElementById(x[1]);
        if(el) el.style.display=modOn(x[0])?"":"none";
        var pill=document.getElementById("ch-pill-"+(x[0]==="jobs"?"none":x[0]==="cv"?"cv":"vst"));
        if(pill&&x[0]!=="jobs") pill.style.display=modOn(x[0])?"":"none";
      });
    }catch(e){}
  }
  function injectMods(){
    try{
      if(document.getElementById("ch-mods-sw")) return;
      var host=document.querySelector("#pnl-konto .pnl-scroll")||document.getElementById("pnl-konto");
      if(!host) return;
      var l=MT[UIL()]||MT.en;
      var d=document.createElement("div"); d.id="ch-mods-sw";
      d.style.cssText="margin:14px 0;padding:14px;border:1px solid var(--bdr,rgba(255,255,255,.12));border-radius:14px;background:rgba(255,255,255,.03)";
      d.innerHTML='<div style="font-size:12px;font-weight:800;letter-spacing:.5px;text-transform:uppercase;color:var(--gold,#d4a84a);margin-bottom:10px">🧩 '+esc(l.t)+'</div>'
        +[["jobs",l.j],["cv",l.c],["vst",l.v]].map(function(x){
          return '<label style="display:flex;align-items:center;gap:9px;font-size:13px;color:var(--t2,#e0e0ff);margin-bottom:8px;cursor:pointer"><input type="checkbox" data-mod="'+x[0]+'" '+(modOn(x[0])?"checked":"")+' style="width:16px;height:16px;accent-color:#d4a84a">'+esc(x[1])+'</label>';
        }).join("");
      var ref=document.getElementById("ch-theme-sw");
      if(ref&&ref.parentNode===host) host.insertBefore(d, ref.nextSibling); else host.insertBefore(d, host.firstChild);
      d.querySelectorAll("input[data-mod]").forEach(function(cb){
        cb.addEventListener("change",function(){ setMod(cb.getAttribute("data-mod"), cb.checked); applyMods(); });
      });
    }catch(e){}
  }

  /* [7] Chat navina basinca temiz chat */
  var cleanCss=document.createElement("style");
  cleanCss.textContent="body.ch-clean .wlc-quick,body.ch-clean .wlc-prompts,body.ch-clean .sec-hdr,body.ch-clean .wcat-grid,body.ch-clean .wlc-h1 ~ .sec-hdr{display:none !important}"
    +"body.ch-clean .wlc-p{opacity:.85}";
  document.head.appendChild(cleanCss);
  function wrapNC(){
    try{
      if(typeof NC!=="function"||NC.__clean) return;
      var _n=NC;
      var w=function(){ try{ document.body.classList.add("ch-clean"); }catch(e){} return _n.apply(this,arguments); };
      w.__clean=1; NC=w; if(typeof window!=="undefined") window.NC=w;
    }catch(e){}
  }
  function wrapGP(){
    try{
      if(typeof gP!=="function"||gP.__clean) return;
      var _g=gP;
      var w=function(k){ try{ if(k&&k!=="chat") document.body.classList.remove("ch-clean"); }catch(e){} return _g.apply(this,arguments); };
      w.__clean=1; gP=w; if(typeof window!=="undefined") window.gP=w;
    }catch(e){}
  }

  function tick(){ hideUISprache(); fixClaim(); hideDupDesign(); fixJobsCv(); addPills(); injectMods(); applyMods(); wrapNC(); wrapGP(); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",function(){ tick(); setInterval(tick,1300); });
  else { tick(); setInterval(tick,1300); }
})();}catch(e){}
</script>
FIXHTML;

$p = strrpos($src, '</body>');
if ($p === false) exit("HATA: </body> bulunamadi — yazilmadi.\n");
$src = substr($src, 0, $p) . $bundle . "\n" . substr($src, $p);

$tmp = tempnam(sys_get_temp_dir(), 'idx') . '.php';
file_put_contents($tmp, $src);
$lo = []; $rc = 0;
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc);
@unlink($tmp);
if ($rc !== 0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  " . implode("\n  ", $lo) . "\n"; exit; }
file_put_contents($file, $src);
echo "\n✓ CH_FIXPACK10 uygulandi.\n";
