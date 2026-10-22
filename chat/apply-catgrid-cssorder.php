<?php
/**
 * ChatHelp — apply-catgrid-cssorder (CH_CATGRID_CSSORDER)
 *  KOK SEBEP (sbprobe3 kaniti — 10+ saniye kesintisiz izleme): .wcat-grid
 *  kartlari surekli, tek yonlu (x koordinati monoton azalarak) kaymaya devam
 *  ediyordu — bu bir "karisma" degil, apply-cc-catlock.php'nin tick()
 *  fonksiyonunun HER 700ms'de kartlari insertBefore ile FIZIKSEL TASIMASI.
 *  Bu oturumda eklenen 10+4=14 yeni ulke katalogu, her biri kendi deferred-
 *  injection dongusunde (40 tick * 500ms = 20 saniyeye kadar) YENI
 *  kategoriler ekliyor -> cc-catlock'un "settled" sayaci hic sabitlenemiyor
 *  -> "want" (istenen sira) SUREKLI DEGISIYOR -> insertBefore dongusu her
 *  700ms'de yeniden calisip kartlari FIZIKSEL OLARAK TASIYOR -> sonucu
 *  gozle gorulen surekli kayma (AYNEN eski nav-jobs menu hatasinin ayni
 *  ailesi: DOM-tasima + surekli degisen hedef durum).
 *
 *  COZUM (menu hatasinda kanitlanmis STRATEJI): DOM'u HIC TASIMA — CSS
 *  `order` kullan. Her kategori kartina, data-ccat anahtarina gore ALFABETIK
 *  ve KALICI (localStorage'da rank-map olarak saklanan) bir order degeri
 *  atanir. Yeni kategori geldiginde SADECE yeni kartin kendi order'i
 *  atanir — MEVCUT kartlarin order'i degismez, DOM'da hicbir tasima
 *  olmaz -> gorsel kayma FIZIKSEL OLARAK IMKANSIZ hale gelir.
 *  Eski insertBefore tabanli tick() TAMAMEN devre disi birakilir.
 * KULLANIM: pull-updates.php?files=apply-catgrid-cssorder.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-catgrid-cssorder BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_CATGRID_CSSORDER')!==false) exit("Zaten ekli (CH_CATGRID_CSSORDER).\n");

$old = <<<'OLD'
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
OLD;

$new = <<<'NEW'
  function rankKey(){ return "ch_wcat_rank_"+cc(); } /*CH_CATGRID_CSSORDER*/
  function loadRank(){ try{ return JSON.parse(localStorage.getItem(rankKey())||"null")||{}; }catch(e){ return {}; } }
  function saveRank(r){ try{ localStorage.setItem(rankKey(), JSON.stringify(r)); }catch(e){} }
  function tick(){ /*CH_CATGRID_CSSORDER — DOM TASIMA YOK, sadece CSS order atanir*/
    try{
      var c=cards(); if(!c||!c.list.length) return;
      var rank=loadRank();
      var known=Object.keys(rank).map(function(k){ return rank[k]; }).map(Number).filter(function(n){ return !isNaN(n); });
      var nextIdx=known.length?(Math.max.apply(null,known)+1):0;
      var changed=false;
      var newKeys=c.list.map(function(el){ return el.getAttribute("data-ccat")||""; })
        .filter(function(k){ return !(k in rank); }).sort();
      newKeys.forEach(function(k){ rank[k]=nextIdx++; changed=true; });
      if(changed) saveRank(rank);
      c.list.forEach(function(el){
        var k=el.getAttribute("data-ccat")||"";
        var want=(k in rank)?rank[k]:9999;
        if(el.style.order!==String(want)) el.style.order=String(want);
      });
    }catch(e){}
  }
  setInterval(tick, 500);
NEW;

$c=0; $src=str_replace($old,$new,$src,$c);
if ($c!==1) { echo "HATA: cc-catlock tick anchor bulunamadi (x$c) — index.php DEGISTIRILMEDI.\n"; exit; }
echo "  ✓ DOM-tasima tabanli eski tick() devre disi; CSS order tabanli KALICI sabitleyici devrede\n";
echo "  ✓ yeni kategori geldiginde SADECE o kartin order'i atanir, digerleri hic tasinmaz\n";

$tmp = tempnam(sys_get_temp_dir(),'cg').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-catgridcss-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_CATGRID_CSSORDER uygulandi. Ana sayfa kategori kartlari artik hukuk\n";
echo "   degisince veya yeni kategori geldikce FIZIKSEL OLARAK OYNAMIYOR.\n";
