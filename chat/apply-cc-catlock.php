<?php
/**
 * ChatHelp — apply-cc-catlock (CH_CC_CATLOCK)
 *  [1] HUKUK ULKESI KALICI: geoMulti artik elle secimi (ch_cc_manual) EZMEZ —
 *      kullanici ulke sectiyse geo-detect bir daha asla degistiremez.
 *  [2] frGeo (FR'ye ozel geo kancasi) ayni sekilde korunur.
 *  [3] KATALOG SIRASI KILIDI: ana sayfadaki ulke kategori kartlari (wcat)
 *      ilk tam gorunumde siralari kaydedilir (ch_wcat_order_<CC>) ve her
 *      yuklemede AYNEN geri uygulanir — kartlar bir daha oynamaz. Sonradan
 *      eklenen yeni kategoriler sona, alfabetik ve deterministik eklenir.
 * KULLANIM: pull-updates.php?files=apply-cc-catlock.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-cc-catlock BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_CC_CATLOCK')!==false) exit("Zaten ekli (CH_CC_CATLOCK).\n");

$fail=[];

/* ── [1] geoMulti: elle secim korumasi ── */
$o1 = 'if(d && d.country && SUPPORTED.indexOf(d.country)>=0';
$n1 = 'if(d && d.country && !localStorage.getItem("ch_cc_manual") && SUPPORTED.indexOf(d.country)>=0';
$c=0; $src=str_replace($o1,$n1,$src,$c);
echo ($c===1?"  ✓ [1] geoMulti elle secimi ezemez\n":"  ✗ [1] geoMulti anchor ($c)\n"); if($c!==1)$fail[]=1;

/* ── [2] frGeo: ayni koruma (varsa) ── */
$o2 = 'function frGeo(){';
$n2 = 'function frGeo(){ if(localStorage.getItem("ch_cc_manual")) return; /*CH_CC_CATLOCK*/';
$c=0; $src=str_replace($o2,$n2,$src,$c);
echo ($c>=1?"  ✓ [2] frGeo korundu ($c)\n":"  ~ [2] frGeo yok (atlandi)\n");

/* ── [3] wcat kategori sira kilidi ── */
$bundle = <<<'HTML'
<script id="ch-catlock-js">
/* CH_CC_CATLOCK — ulke kategori kartlari sira kilidi (bir kez kaydet, hep uygula) */
try{(function(){
  function cc(){ try{ return (typeof CC!=="undefined"&&CC)?CC:"DE"; }catch(e){ return "DE"; } }
  function key(){ return "ch_wcat_order_"+cc(); }
  function cards(){
    var g=document.querySelector(".wcat-grid"); if(!g) return null;
    var list=[]; g.querySelectorAll("[data-cccountry]").forEach(function(el){
      if(el.getAttribute("data-cccountry")===cc()) list.push(el);
    });
    return {g:g, list:list};
  }
  var settled=0, lastN=-1;
  function tick(){
    try{
      var c=cards(); if(!c||!c.list.length) return;
      /* kart sayisi degismiyorsa 'oturdu' say */
      if(c.list.length===lastN) settled++; else { settled=0; lastN=c.list.length; }
      var saved=null;
      try{ saved=JSON.parse(localStorage.getItem(key())||"null"); }catch(e){}
      if(!saved){
        if(settled<3) return; /* enjeksiyonlar bitmeden kaydetme */
        saved=c.list.map(function(el){ return el.getAttribute("data-ccat")||""; });
        try{ localStorage.setItem(key(), JSON.stringify(saved)); }catch(e){}
      }
      /* kayitli siraya gore diz; listede olmayan yeniler sona alfabetik */
      var byKey={}; c.list.forEach(function(el){ byKey[el.getAttribute("data-ccat")||""]=el; });
      var order=saved.filter(function(k){ return byKey[k]; });
      var extra=Object.keys(byKey).filter(function(k){ return order.indexOf(k)<0; }).sort();
      var want=order.concat(extra);
      /* yeni anahtarlari kayda isle */
      if(extra.length){ try{ localStorage.setItem(key(), JSON.stringify(want)); }catch(e){} }
      /* mevcut DOM sirasi istenenle ayni mi? */
      var cur=c.list.map(function(el){ return el.getAttribute("data-ccat")||""; });
      if(JSON.stringify(cur)===JSON.stringify(want)) return;
      /* yeniden diz: referans = ilk kartin oncesi */
      var anchor=c.list[0].previousSibling, parent=c.list[0].parentNode;
      want.forEach(function(k){
        var el=byKey[k]; if(!el) return;
        if(anchor&&anchor.nextSibling!==el) parent.insertBefore(el, anchor?anchor.nextSibling:parent.firstChild);
        else if(!anchor&&parent.firstChild!==el) parent.insertBefore(el, parent.firstChild);
        anchor=el;
      });
    }catch(e){}
  }
  setInterval(tick, 700);
})();}catch(e){}
</script>
HTML;
$pos = strrpos($src,'</body>');
if ($pos===false) { echo "  ✗ [3] </body> yok\n"; $fail[]=3; }
else { $src = substr($src,0,$pos).$bundle."\n".substr($src,$pos); echo "  ✓ [3] kategori sira kilidi eklendi\n"; }

if ($fail) { echo "\nHATA: adimlar eslesmedi: ".implode(', ',$fail)." — index.php DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(),'cl').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-catlock-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_CC_CATLOCK uygulandi. Hukuk ulkesi secimi kalici (geo ezemez); kategori kartlari sabit sirada.\n";
