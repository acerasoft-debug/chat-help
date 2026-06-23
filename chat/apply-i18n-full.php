<?php
/**
 * ChatHelp — TAM arayüz çevirisi (menü, Anträge, başlıklar, butonlar, doc adları… hepsi)
 * ----------------------------------------------------------------------------------------
 *  Mevcut translate.php (DeepSeek + önbellek) altyapısını kullanır.
 *  Dil ≠ de iken sayfadaki TÜM kısa Almanca metinleri çevirir; dil değişince anında uygular.
 *  GÜVENLİK (hatasız): sohbet baloncukları (.bbl/.msg), üretilen belge, kullanıcı girdileri,
 *    uzun metinler (>140 krk), dil menüsü ve form etiketleri (mevcut çift-dil katmanı) HARİÇ.
 *  -> Fransa'dan giren / FR seçen kullanıcıda menü+Anträge+başlıklar+doc adları Fransızca olur.
 *  -> 8 dilin hepsinde çalışır.
 *
 * KULLANIM: chat-help.com/chat/apply-i18n-full.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
echo "ChatHelp — apply-i18n-full BAŞLADI ✓ (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src = file_get_contents($file);
$start = $src;

if (strpos($src, 'ch-i18n-full') !== false) { exit("Zaten ekli (ch-i18n-full). Değişiklik yok.\n"); }
file_put_contents($file . '.bak-i18nfull-' . date('Ymd-His'), $src);

$bundle = <<<'HTML'
<script id="ch-i18n-full">
(function(){
  'use strict';
  if(window.__chI18nFull) return; window.__chI18nFull=1;
  var SUP={tr:1,ru:1,ar:1,en:1,fr:1,es:1,it:1,pl:1};
  function getLang(){
    var s=null; try{ s=localStorage.getItem('ch_uilang'); }catch(e){}
    if(s && (s==='de'||SUP[s])) return s;
    try{ if(window.UL && (window.UL==='de'||SUP[window.UL])) return window.UL; }catch(e){}
    return 'de';
  }
  /* Çeviri DIŞINDA tutulacaklar */
  var SKIP_TAGS={SCRIPT:1,STYLE:1,NOSCRIPT:1,TEXTAREA:1,INPUT:1,CODE:1,PRE:1,SVG:1,PATH:1,LABEL:1};
  function skipEl(el){
    while(el && el!==document.body){
      if(el.nodeType===1){
        if(SKIP_TAGS[el.tagName]) return true;
        var id=el.id;
        if(id==='ch-lang-btn'||id==='ch-lang-menu'||id==='ch-lang-ask') return true;
        if(el.hasAttribute){
          if(el.hasAttribute('data-noi18n')) return true;
          if(el.getAttribute('contenteditable')==='true') return true;
        }
        var cl=el.classList;
        if(cl){
          if(cl.contains('ch-i18n-tr')) return true;
          if(cl.contains('bbl')) return true;       /* sohbet baloncuğu (AI/kullanıcı/belge) */
          if(cl.contains('msg')) return true;        /* sohbet satırı */
          if(cl.contains('doc-output')||cl.contains('docout')||cl.contains('doc-view')) return true;
        }
      }
      el=el.parentNode;
    }
    return false;
  }
  var ORIG=new WeakMap(), DONE=new WeakMap(), KEY=new WeakMap();
  var applying=false;
  var LETTERS=/[a-zA-ZÀ-ÿ]/;
  function collect(){
    var nodes=[];
    try{
      var w=document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
        acceptNode:function(n){
          var v=n.nodeValue; if(!v) return NodeFilter.FILTER_REJECT;
          var t=v.replace(/\s+/g,' ').trim();
          if(t.length<1||t.length>140) return NodeFilter.FILTER_REJECT;
          if(!LETTERS.test(t)) return NodeFilter.FILTER_REJECT;
          if(skipEl(n.parentNode)) return NodeFilter.FILTER_REJECT;
          return NodeFilter.FILTER_ACCEPT;
        }
      });
      var x; while(x=w.nextNode()) nodes.push(x);
    }catch(e){}
    return nodes;
  }
  var TC={};
  function trBatch(texts,lang,cb){
    TC[lang]=TC[lang]||{};
    var miss=texts.filter(function(t){ return !(t in TC[lang]); });
    if(!miss.length){ cb(); return; }
    fetch('translate.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({texts:miss,lang:lang})})
      .then(function(r){ return r.json(); }).then(function(d){
        var t=(d&&d.translations)||{}; for(var k in t) TC[lang][k]=t[k];
        miss.forEach(function(m){ if(!(m in TC[lang])) TC[lang][m]=m; });
        cb();
      }).catch(function(){ miss.forEach(function(m){ TC[lang][m]=m; }); cb(); });
  }
  function apply(){
    var lang=getLang();
    var nodes=collect();
    if(lang==='de'){
      applying=true;
      nodes.forEach(function(n){ if(ORIG.has(n)){ n.nodeValue=ORIG.get(n); DONE.set(n,'de'); } });
      applying=false; return;
    }
    var need=[], pend=[];
    nodes.forEach(function(n){
      if(DONE.get(n)===lang) return;
      if(!ORIG.has(n)) ORIG.set(n, n.nodeValue);
      var key=ORIG.get(n).replace(/\s+/g,' ').trim();
      KEY.set(n,key); pend.push(n); need.push(key);
    });
    if(!need.length) return;
    var seen={}, uniq=[];
    need.forEach(function(k){ if(!seen[k]){ seen[k]=1; uniq.push(k); } });
    trBatch(uniq, lang, function(){
      applying=true;
      pend.forEach(function(n){
        var key=KEY.get(n); if(!key) return;
        var tr=TC[lang]&&TC[lang][key];
        if(tr && tr!==key){
          var orig=ORIG.get(n)||n.nodeValue;
          var lead=(orig.match(/^\s*/)||[''])[0];
          var trail=(orig.match(/\s*$/)||[''])[0];
          n.nodeValue=lead+tr+trail;
        }
        DONE.set(n,lang);
      });
      applying=false;
    });
  }
  var tmr=null;
  function schedule(){ if(tmr) clearTimeout(tmr); tmr=setTimeout(apply,180); }
  var obs=new MutationObserver(function(muts){
    if(applying) return;
    for(var i=0;i<muts.length;i++){
      if((muts[i].addedNodes&&muts[i].addedNodes.length) || muts[i].type==='characterData'){ schedule(); return; }
    }
  });
  function start(){
    try{ obs.observe(document.body,{childList:true,subtree:true,characterData:true}); }catch(e){}
    apply();
    var last=getLang();
    setInterval(function(){ var l=getLang(); if(l!==last){ last=l; apply(); } }, 600);
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',start); else start();
})();
</script>
HTML;

$n=0;
$src = preg_replace('/<\/body>/', $bundle."\n</body>", $src, 1, $n);

if ($n>0 && $src!==$start) {
    file_put_contents($file, $src);
    echo "✓ Tam arayüz çeviri katmanı eklendi (ch-i18n-full).  → </body> öncesi\n";
    echo "  • Dil ≠ Almanca iken menü, Anträge, başlıklar, butonlar, doc adları otomatik çevrilir.\n";
    echo "  • Sohbet baloncukları + üretilen belge + uzun metinler korunur (bozulmaz).\n";
    echo "  • İlk çeviri DeepSeek'ten gelir (birkaç saniye), sonra cache_lang/{dil}.json'dan anında.\n";
} else {
    echo "✗ </body> bulunamadı veya değişiklik olmadı (n=$n).\n";
}

echo "\nSONRA: opcache-reset.php. SİL: rm apply-i18n-full.php\n";
echo "Test: 🇫🇷 seç (veya Fransa IP) -> menü/Anträge/başlıklar Fransızca olmalı.\n";
echo "NOT: AI sohbet soruları ayrı (backend) — sıradaki adımda Fransızca yapacağız.\n";
