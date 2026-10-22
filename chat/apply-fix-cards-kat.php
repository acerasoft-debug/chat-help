<?php
/**
 * ChatHelp — apply-fix-cards-kat (CH_CARDS_KAT_FIX) — 2 sikayet:
 *  [A] Kart titremesi: bugun eklenen CH_ABO_REFRESH plan DEGISMESE BILE her
 *      dongude window.chApplyPlan/refreshPlanUI cagirip kartlari yeniden
 *      cizdiriyordu (titreme). DUZELTME: UI yenilemesi artik yalniz plan
 *      GERCEKTEN degisince yapilir (window.__chLastPlan koruma).
 *  [B] "Verträge kündigen" kirliligi: bir merge dongusu Vertrag Studio
 *      sozlesmelerini (vtr_*, cat:'vertraege') CAT_DOCS['vertraege']'e itiyor
 *      -> soru-cevap fesih sablonlarinin (kuendigung_*) yaninda tam sozlesmeler
 *      cikiyor. DUZELTME: showCatDocs sarmalanir; 'vertraege' kategorisi
 *      cizilmeden ONCE vtr_* girdileri ayiklanir -> yalniz kuendigung_* kalir.
 *      Sozlesmeler Vertrag Studio modulunde kalir. Ek kisa temizleyici dongu.
 *  1 count-guard'li str_replace (A) + 1 eklemeli </body> blok (B).
 *  node --check ✓ + harness ✓.
 * KULLANIM: pull2.php?key=...&files=apply-fix-cards-kat.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fix-cards-kat BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_CARDS_KAT_FIX')!==false) exit("Zaten ekli (CH_CARDS_KAT_FIX).\n");

$fail=[];

/* ── [A] abo-refresh UI yenilemesini plan-degisince'ye indir ── */
$o1 = <<<'ANC'
      /* plan-bagimli arayuzu yenile (varsa) */
      try{ if(typeof window.chApplyPlan==='function') window.chApplyPlan(np); }catch(e){}
      try{ if(typeof window.refreshPlanUI==='function') window.refreshPlanUI(); }catch(e){}
ANC;
$n1 = <<<'ANC'
      /* CH_CARDS_KAT_FIX: plan-bagimli arayuzu YALNIZ degisince yenile (titreme onleme) */
      try{ if(np!==window.__chLastPlan){ window.__chLastPlan=np; if(typeof window.chApplyPlan==='function') window.chApplyPlan(np); if(typeof window.refreshPlanUI==='function') window.refreshPlanUI(); } }catch(e){}
ANC;
$c1=substr_count($src,$o1);
if($c1!==1){ echo "  ✗ [A] abo-refresh anchor ($c1) — CH_ABO_REFRESH once gerekli olabilir\n"; $fail[]='A'; }
else { $src=str_replace($o1,$n1,$src); echo "  ✓ [A] Titreme: plan-UI yenilemesi artik yalniz plan degisince (abo-refresh)\n"; }

/* ── [B] showCatDocs sarma + kategori temizleyici ── */
$block = <<<'HTMLBLOCK'
<script id="ch-cards-kat-fix-js">
/* CH_CARDS_KAT_FIX — 'vertraege' kategorisinden vtr_* sozlesmeleri ayikla */
try{(function(){
  function clean(){
    try{
      if(typeof CAT_DOCS==='undefined'||!CAT_DOCS['vertraege']) return;
      CAT_DOCS['vertraege']=CAT_DOCS['vertraege'].filter(function(d){
        return d && typeof d.k==='string' && d.k.indexOf('vtr_')!==0;
      });
    }catch(e){}
  }
  /* showCatDocs cagrilmadan once ilgili kategoriyi temizle */
  function wrap(){
    try{
      if(typeof window.showCatDocs!=='function' || window.showCatDocs.__katfix) return false;
      var _o=window.showCatDocs;
      var w=function(cat){ if(cat==='vertraege') clean(); return _o.apply(this,arguments); };
      w.__katfix=1; window.showCatDocs=w;
      return true;
    }catch(e){ return false; }
  }
  /* merge donguleri gec calisabilir -> kisa sure periyodik temizle + wrap dene */
  var n=0;(function loop(){ wrap(); clean(); if(n++<60) setTimeout(loop,500); })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false){ echo "  ✗ [B] </body> yok\n"; $fail[]='B'; }
else { $src = substr($src,0,$pos).$block."\n".substr($src,$pos); echo "  ✓ [B] 'Verträge kündigen': vtr_* sozlesmeleri ayiklanir (yalniz kuendigung_* kalir)\n"; }

if ($fail) { echo "\nHATA: eslesmeyen: ".implode(', ',$fail)." — index DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(),'ck').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-cardskat-'.date('Ymd-His'), (string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_CARDS_KAT_FIX')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "\n  ✓ DOGRULAMA: CH_CARDS_KAT_FIX diskte (".strlen($chk)." bayt)\n";
echo "\n✓ [A] Kart titremesi azaltildi: plan-UI artik yalniz plan degisince yenilenir.\n";
echo "✓ [B] 'Verträge kündigen' artik yalniz soru-cevap fesih sablonlari gosterir;\n";
echo "   tam sozlesmeler (Arbeitsvertrag/NDA/Werkvertrag vb.) Vertrag Studio'da kalir.\n";
