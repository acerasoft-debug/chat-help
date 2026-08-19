<?php
/**
 * ChatHelp — apply-vkbew-fix (CH_VKBEW) — 2 duzeltme:
 *  [A] "Verträge kündigen" karti sozlesme gosteriyor cunku iki capture-faz
 *      yakalayici (data-cat + onclick-metni) karti gP('vst')=Vertrag Studio'ya
 *      yonlendiriyor. CAT_DOCS['vertraege'] zaten temiz (11 kuendigung_*).
 *      COZUM: window seviyesinde capture listener (yonlendiricilerden ONCE
 *      calisir) -> Verträge kündigen kartinda stopImmediatePropagation +
 *      showCatDocs('vertraege') -> yalniz Kündigung sablonlari gosterilir.
 *  [B] Bewerbung karti yok. COZUM: ana sayfa kategori grid'ine "Bewerbung &
 *      Lebenslauf" karti eklenir -> tiklayinca gP('jobs') (Is Bul & Basvur)
 *      modulu acilir. Idempotent, tek ekleme.
 *  Tek eklemeli </body> blogu. node ✓ + harness ✓.
 * KULLANIM: pull2.php?key=...&files=apply-vkbew-fix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-vkbew-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_VKBEW')!==false) exit("Zaten ekli (CH_VKBEW).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-vkbew-css">
#ch-bew-card{display:flex;align-items:center;gap:12px;margin:10px 0;padding:13px 15px;border:1px solid rgba(212,168,74,.4);border-radius:14px;background:linear-gradient(135deg,rgba(212,168,74,.1),rgba(212,168,74,.02));cursor:pointer}
#ch-bew-card:hover{border-color:rgba(212,168,74,.8);transform:translateY(-2px);transition:.15s}
#ch-bew-card .t1{font-size:14px;font-weight:800;color:var(--t1,#fff)}
#ch-bew-card .t2{font-size:11.5px;color:var(--t3,#9a9ab0);margin-top:2px}
</style>
<script id="ch-vkbew-js">
/* CH_VKBEW — Verträge kündigen sablonlari (redirect atla) + Bewerbung karti */
try{(function(){
  function UIL(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  /* ── [A] Verträge kündigen kartini yakala (window capture -> once calisir) ── */
  window.addEventListener('click',function(ev){
    try{
      var t=ev.target; if(!t||!t.closest) return;
      var card=t.closest('.wcat, [onclick]');
      if(!card) return;
      var oc=(card.getAttribute&&card.getAttribute('onclick'))||'';
      /* Verträge kündigen karti: onclick="showCatDocs('vertraege')" */
      if(oc.indexOf("showCatDocs('vertraege')")!==-1 || oc.indexOf('showCatDocs("vertraege")')!==-1){
        ev.stopImmediatePropagation(); ev.preventDefault();
        try{ if(typeof window.showCatDocs==='function') window.showCatDocs('vertraege'); else if(typeof showCatDocs==='function') showCatDocs('vertraege'); }catch(e){}
      }
    }catch(e){}
  },true);

  /* ── [B] Bewerbung karti -> gP('jobs') ── */
  function T(){ return {de:['Bewerbung & Lebenslauf','Job finden, CV & Anschreiben erstellen'],tr:['Başvuru & CV','İş bul, CV ve başvuru mektubu oluştur'],en:['Application & CV','Find jobs, build CV & cover letter']}[UIL()]||['Bewerbung & Lebenslauf','Job finden, CV & Anschreiben']; }
  var n=0;(function inject(){
    try{
      if(!document.getElementById('ch-bew-card')){
        var grid=document.querySelector('.wcat-grid');
        var host=grid?grid.parentNode:null;
        if(host){
          var lbl=T();
          var c=document.createElement('div'); c.id='ch-bew-card';
          c.innerHTML='<span style="font-size:26px">📄</span><span style="flex:1"><span class="t1">'+lbl[0]+'</span><span class="t2" style="display:block">'+lbl[1]+'</span></span><span style="font-size:18px;color:#d4a84a">›</span>';
          c.onclick=function(){ try{ if(typeof window.gP==='function') window.gP('jobs'); else if(typeof gP==='function') gP('jobs'); }catch(e){} };
          host.insertBefore(c, grid.nextSibling);
        }
      }
    }catch(e){}
    if(n++<300) setTimeout(inject,700);
  })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);
$tmp = tempnam(sys_get_temp_dir(),'vb').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-vkbew-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_VKBEW')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_VKBEW diskte (".strlen($chk)." bayt)\n";
echo "\n✓ [A] 'Verträge kündigen' artik YALNIZ Kündigung sablonlari gosterir\n";
echo "   (Vertrag Studio yonlendirmesi window-capture ile atlanir)\n";
echo "✓ [B] Ana sayfaya 'Bewerbung & Lebenslauf' karti -> gP('jobs') (Is Bul & Basvur)\n";
