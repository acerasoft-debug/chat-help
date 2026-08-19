<?php
/**
 * ChatHelp — apply-vize-merge (CH_VIZE_MERGE) — katalogdaki ulke-ulke vize
 *  kategorilerini (Schengen/ABD/Kanada/Ingiltere/Avustralya Vizesi Belgeleri)
 *  TEK kategoride birlestirir: "🛂 Vize Belgeleri — Tüm Ülkeler".
 *  Belgelerin kendisi DEGISMEZ (adlarinda ulke zaten yaziyor); sadece
 *  gruplama tekillesir, kartlarda ulke ulke durma biter. Ek olarak DOM'da
 *  onceden cizilmis ulke-vize kartlari varsa (etiket birebir eslesirse)
 *  gizlenir. Sadece EKLEMELI (</body> oncesi script), node ✓ + harness ✓.
 * KULLANIM: pull2.php?key=...&files=apply-vize-merge.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-vize-merge BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_VIZE_MERGE')!==false) exit("Zaten ekli (CH_VIZE_MERGE).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-vize-merge-js">
/* CH_VIZE_MERGE — ulke-ulke vize kategorileri -> tek "Vize Belgeleri" kategorisi */
try{(function(){
  var KEYS=['tr_schengen','tr_visa_usa','tr_visa_canada','tr_visa_uk','tr_visa_australia'];
  var NK='tr_vize_hepsi', NL='Vize Belgeleri — Tüm Ülkeler';
  function merge(){
    try{
      if(typeof CATS==='undefined'||!CATS) return;
      var found=KEYS.filter(function(k){ return CATS[k]; });
      if(found.length){
        if(!CATS[NK]) CATS[NK]={n:NL,ic:'🛂',docs:[],country:'TR'};
        if(!CATS[NK].docs) CATS[NK].docs=[];
        found.forEach(function(k){
          ((CATS[k]&&CATS[k].docs)||[]).forEach(function(d){ if(CATS[NK].docs.indexOf(d)<0) CATS[NK].docs.push(d); });
          delete CATS[k];
        });
      }
      if(typeof CAT_LABELS!=='undefined'&&CAT_LABELS){
        if(CATS[NK]) CAT_LABELS[NK]=NL;
        KEYS.forEach(function(k){ if(CAT_LABELS[k]) delete CAT_LABELS[k]; });
      }
      if(typeof CAT_DOCS!=='undefined'&&CAT_DOCS){
        var any=KEYS.some(function(k){ return CAT_DOCS[k]; });
        if(any){
          if(!CAT_DOCS[NK]) CAT_DOCS[NK]=[];
          KEYS.forEach(function(k){
            (CAT_DOCS[k]||[]).forEach(function(e){
              var ex=false; for(var i=0;i<CAT_DOCS[NK].length;i++){ if(CAT_DOCS[NK][i].k===e.k){ex=true;break;} }
              if(!ex) CAT_DOCS[NK].push(e);
            });
            delete CAT_DOCS[k];
          });
        }
      }
    }catch(e){}
  }
  var LBLS=['Schengen Vizesi Belgeleri','ABD Vizesi Belgeleri','Kanada Vizesi Belgeleri','İngiltere Vizesi Belgeleri','Avustralya Vizesi Belgeleri'];
  function hideDom(){
    try{
      document.querySelectorAll('.sb-cat, .wcat, .cat-card').forEach(function(el){
        try{
          if(el.__vzhide) return;
          var t=(el.textContent||'').replace(/\s+/g,' ').trim();
          for(var i=0;i<LBLS.length;i++){
            if(t.indexOf(LBLS[i])!==-1){ el.__vzhide=1; el.style.display='none'; return; }
          }
        }catch(e){}
      });
    }catch(e){}
  }
  var n=0;(function loop(){ merge(); hideDom(); if(n++<50) setTimeout(loop,500); })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'vm').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-vizemerge-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_VIZE_MERGE')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_VIZE_MERGE diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Vize kategorileri tek kartta toplandi: '🛂 Vize Belgeleri — Tüm Ülkeler'\n";
echo "   (Schengen/ABD/Kanada/İngiltere/Avustralya ayri kartlari kaldirildi;\n";
echo "    tum belgeler tek kategori altinda, adlarinda ulkeleri yaziyor.)\n";
