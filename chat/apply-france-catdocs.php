<?php
/**
 * ChatHelp — FRANSA "0 documents" FIX: CAT_DOCS/CAT_LABELS'i Fransız belgelerle doldur
 * ------------------------------------------------------------------------------------
 *  Welcome kategori kartları belge listesini CAT_DOCS sisteminden okuyor; Fransız kategoriler
 *  için boştu -> "0 documents". Bu yama, DOCS'taki tüm fr_ belgeleri ilgili CAT_DOCS'a doldurur,
 *  CAT_LABELS'i ayarlar ve görünür sayıları günceller. (Geç çalışır -> sıralamadan bağımsız.)
 *
 * KULLANIM: chat-help.com/chat/apply-france-catdocs.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
echo "ChatHelp — apply-france-catdocs BAŞLADI ✓ (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src = file_get_contents($file);
$start = $src;

$anchor = "function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if (strpos($src, 'CH_FR_CATDOCS') !== false) { exit("Zaten ekli (CH_FR_CATDOCS). Değişiklik yok.\n"); }
if (strpos($src, $anchor) === false) { exit("✗ anchor (getDocPrice) bulunamadı.\n"); }
file_put_contents($file . '.bak-frcatdocs-' . date('Ymd-His'), $src);

$js = <<<'JS'

/* CH_FR_CATDOCS — Fransız belgeleri CAT_DOCS/CAT_LABELS'e doldur (welcome kart + sayım) */
try{(function(){
  var LBL={fr_resiliation:"Résiliations",fr_logement:"Logement & Bail",fr_travail:"Travail & Emploi",fr_litige:"Litiges & Réclamations",fr_conso:"Consommation",fr_admin:"Administratif"};
  function populate(){
    try{
      if(typeof DOCS==='undefined') return;
      if(typeof CAT_LABELS!=='undefined'){ for(var c in LBL) CAT_LABELS[c]=LBL[c]; }
      if(typeof CAT_DOCS!=='undefined'){
        for(var c2 in LBL){ if(!CAT_DOCS[c2]) CAT_DOCS[c2]=[]; }
        for(var k in DOCS){
          if(k.indexOf('fr_')!==0) continue;
          var d=DOCS[k]; var cat=d&&d.cat; if(!cat||!LBL[cat]) continue;
          if(!CAT_DOCS[cat]) CAT_DOCS[cat]=[];
          var ex=false; for(var i=0;i<CAT_DOCS[cat].length;i++){ if(CAT_DOCS[cat][i].k===k){ ex=true; break; } }
          if(!ex) CAT_DOCS[cat].push({k:k,ic:d.ic||'📄',t:d.n||k,tier:d.tier||'standard'});
        }
      }
      if(typeof CATS!=='undefined'){
        for(var c3 in LBL){ if(CATS[c3] && !CATS[c3].docs) CATS[c3].docs=[]; }
        for(var k2 in DOCS){ if(k2.indexOf('fr_')!==0) continue; var cc=DOCS[k2].cat; if(CATS[cc] && CATS[cc].docs.indexOf(k2)<0) CATS[cc].docs.push(k2); }
      }
    }catch(e){}
  }
  function refreshCounts(){
    try{
      var els=document.querySelectorAll('[onclick]');
      for(var j=0;j<els.length;j++){
        var el=els[j], oc=el.getAttribute('onclick')||'';
        var m=oc.match(/show(?:CatDocs|Cat)\(\s*['"]([^'"]+)['"]/);
        if(!m) continue; var cat=m[1]; if(!LBL[cat]) continue;
        var cnt=(typeof CAT_DOCS!=='undefined'&&CAT_DOCS[cat])?CAT_DOCS[cat].length:((typeof CATS!=='undefined'&&CATS[cat]&&CATS[cat].docs)?CATS[cat].docs.length:0);
        if(!cnt) continue;
        var w=document.createTreeWalker(el,NodeFilter.SHOW_TEXT,null), n;
        while(n=w.nextNode()){ if(/\d+\s*(documents?|dokumente?|belge|modèles?)/i.test(n.nodeValue)){ n.nodeValue=n.nodeValue.replace(/\d+/, cnt); break; } }
      }
    }catch(e){}
  }
  var ticks=0;
  function run(){ populate(); refreshCounts(); if(++ticks<10) setTimeout(run,400); }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',run); else run();
})();}catch(e){}
JS;

$src = str_replace($anchor, $anchor . $js, $src);

if ($src !== $start) {
    file_put_contents($file, $src);
    echo "✓ CAT_DOCS/CAT_LABELS Fransız belgelerle dolduruldu (CH_FR_CATDOCS).\n";
    echo "  • Kategori kartları artık doğru sayıyı gösterir (0 değil).\n";
    echo "  • Kategoriye tıklayınca Fransız belgeler listelenir.\n";
    echo "  • Geç çalışır (DOMContentLoaded + ~4sn tekrar) -> diğer modüllerden bağımsız.\n";
} else {
    echo "✗ Değişiklik uygulanamadı.\n";
}

echo "\nSONRA: opcache-reset.php. SİL: rm apply-france-catdocs.php\n";
echo "Test: 🇫🇷 France -> kategoriler artık 'X documents' gösterir -> tıkla -> belgeler çıkar.\n";
