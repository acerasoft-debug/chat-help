<?php
/**
 * ChatHelp — apply-cardwatch (CH_CARDWATCH) — DE-disi kart oynamasi CANLI TESHIS
 *  Perde (FINAL4) kartlari ilk saniyelerde gizliyor; buna ragmen oynama
 *  suruyorsa acilistan SONRA da sürekli oynatan bir enjektor var demektir.
 *  Bu izleyici 12 saniye boyunca .wcat-grid'i izler ve sag altta bir rozette:
 *   • kac kez kart EKLENDI/CIKARILDI/YER DEGISTIRDI (childList olaylari)
 *   • her hareket eden kartin KIMLIK etiketi: data-ccat / data-univcat /
 *     data-chcat / data-frcat / data-cccountry — yani hangi ENJEKTOR sahibi
 *   • hareketin zaman damgasi (acilistan kac sn sonra — ilk 2sn mi, surekli mi)
 *  Boylece "hangi kod oynatiyor" TAHMIN degil KANIT olur. SADECE TESHIS —
 *  hicbir davranisi degistirmez; is bitince apply-cardwatch-remove ile kalkar.
 * KULLANIM: pull2.php?key=...&files=apply-cardwatch.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-cardwatch BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_CARDWATCH')!==false) exit("Zaten ekli (CH_CARDWATCH).\n");

$bundle = <<<'HTML'
<style id="ch-cardwatch-css">
#ch-cw{position:fixed;right:8px;bottom:8px;z-index:2147483647;max-width:74vw;max-height:52vh;overflow:auto;
 background:#0d1030;border:2px solid #6ad;border-radius:12px;padding:9px 30px 9px 11px;color:#cfe8ff;
 font:11.5px/1.5 ui-monospace,Menlo,monospace;white-space:pre-wrap;word-break:break-word;pointer-events:auto}
#ch-cw b{color:#ffd166}
#ch-cw-x{position:absolute;right:8px;top:5px;color:#6ad;font-size:18px;cursor:pointer}
</style>
<script id="ch-cardwatch-js">
/* CH_CARDWATCH — canli kart hareketi kaydedici (SADECE TESHIS) */
try{(function(){
  var t0=Date.now();
  function ts(){ return ((Date.now()-t0)/1000).toFixed(1)+"s"; }
  var LOG=[], counts={add:0,rem:0,move:0};
  function idtag(el){
    if(!el||el.nodeType!==1) return "(?)";
    var a=["data-ccat","data-univcat","data-chcat","data-frcat","data-trcat"];
    for(var i=0;i<a.length;i++){ if(el.hasAttribute&&el.hasAttribute(a[i])) return a[i].replace("data-","")+"="+el.getAttribute(a[i]); }
    if(el.className&&(""+el.className).indexOf("wcat")>=0) return "wcat(etiketSIZ!)";
    return el.tagName.toLowerCase();
  }
  function render(){
    var b=document.getElementById("ch-cw");
    if(!b){ b=document.createElement("div"); b.id="ch-cw";
      var x=document.createElement("span"); x.id="ch-cw-x"; x.textContent="×";
      x.onclick=function(){ b.remove(); }; b.appendChild(x);
      var p=document.createElement("div"); p.id="ch-cw-p"; b.appendChild(p);
      document.body.appendChild(b); }
    var p=document.getElementById("ch-cw-p");
    if(p) p.innerHTML="<b>KART IZLEME</b>  ekle:"+counts.add+" cikar:"+counts.rem+" tasi:"+counts.move+"\n"
      +"aktif hukuk: "+((typeof CC!=="undefined")?CC:"?")+"\n"+LOG.slice(-16).join("\n");
  }
  function watch(g,gi){
    try{
      new MutationObserver(function(muts){
        muts.forEach(function(m){
          if(m.type!=="childList") return;
          for(var i=0;i<m.addedNodes.length;i++){ var n=m.addedNodes[i]; if(n.nodeType===1){ counts.add++; LOG.push("["+ts()+"] G"+gi+" +EKLE "+idtag(n)); } }
          for(var j=0;j<m.removedNodes.length;j++){ var r=m.removedNodes[j]; if(r.nodeType===1){ counts.rem++; LOG.push("["+ts()+"] G"+gi+" -CIKAR "+idtag(r)); } }
        });
        render();
      }).observe(g,{childList:true});
    }catch(e){}
  }
  function boot(){
    var gs=document.querySelectorAll(".wcat-grid");
    if(!gs.length){ setTimeout(boot,200); return; }
    for(var i=0;i<gs.length;i++) watch(gs[i],i);
    LOG.push("["+ts()+"] izleme basladi ("+gs.length+" grid)"); render();
    /* ozet: 12sn sonra ekle/cikar dengesi -> surekli churn mu, tek seferlik mi */
    setTimeout(function(){ LOG.push("["+ts()+"] --- 12sn OZET: ekle="+counts.add+" cikar="+counts.rem+" (ekle≈cikar ise SUREKLI CHURN) ---"); render(); },12000);
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",boot); else boot();
})();}catch(e){}
</script>
HTML;

$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok — index degistirilmedi.\n");
$src = substr($src,0,$pos).$bundle."\n".substr($src,$pos);
echo "  ✓ canli kart-hareketi izleyicisi eklendi (sag alt rozet)\n";

$tmp = tempnam(sys_get_temp_dir(),'cw').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-cardwatch-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_CARDWATCH uygulandi. Cihazda ac (orn /chat/?z=30), DE-disi bir hukuk sec,\n";
echo "   sag alttaki mavi rozetin 12 sn sonraki EKRAN GORUNTUSUNU gonder.\n";
