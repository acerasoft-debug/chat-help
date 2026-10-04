<?php
/**
 * ChatHelp — apply-paiddoc (CH_PAIDDOC) — Einzeldokument tek-belge odemesini CALISIR hale getirir:
 *  [1] genDoc: odenmis krediyi kullan -> __paidDoc (paywall dongusu biter)
 *  [2] odeme handler: doc odemesinde gecersiz plan atamasini kaldir
 *  [3-5] showResult: __paidDoc ise PDF + Senden(fax) kilidini ac
 *  UYARI: canli odeme hattina dokunur. Deploy sonrasi Stripe test-mode ile GERCEK odeme testi sart.
 * KULLANIM: pull2.php?key=...&files=apply-paiddoc.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-paiddoc BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,"CH_PAIDDOC")!==false) exit("Zaten ekli (CH_PAIDDOC).\n");
$ok=0;
$o=<<<'O_GATE'
  if((!_gTk||_gPlan==='free') && !window.__freeOK){
    /* CH_FREE1IP — 1 gratis Dokument pro IP (server, E-Mail-unabhängig) */
    window._pendingDoc={dk,ans};
    (async function(){
      try{
        const _fr=await fetch('freequota.php?action=claim',{cache:'no-store'});
        const _fj=await _fr.json();
        if(_fj&&_fj.free){ window.__freeOK=true; genDoc(dk,ans); return; }
      }catch(e){}
      showPaywall(dk,ans);
    })();
    return;
  }
O_GATE;
$n=<<<'N_GATE'
  if((!_gTk||_gPlan==='free') && !window.__freeOK){
    /* CH_PAIDDOC — once odenmis kredi (Einzeldokument), yoksa 1-free-IP */
    if(typeof window.useCredit==='function' && typeof window.credits==='function' && window.credits()>0){
      window.useCredit(); window.__freeOK=true; window.__paidDoc=true;
    } else {
      window._pendingDoc={dk,ans};
      (async function(){
        try{
          const _fr=await fetch('freequota.php?action=claim',{cache:'no-store'});
          const _fj=await _fr.json();
          if(_fj&&_fj.free){ window.__freeOK=true; window.__paidDoc=false; genDoc(dk,ans); return; }
        }catch(e){}
        showPaywall(dk,ans);
      })();
      return;
    }
  }
N_GATE;
$c=substr_count($src,$o); if($c===1){ $src=str_replace($o,$n,$src); echo "  ✓ [GATE] tamam\n"; $ok++; } else echo "  ✗ [GATE] anchor ($c, 1 beklenir) — atlandi\n";
$o=<<<'O_HANDLER1'
        } else if(_vd.plan){
          P.plan = _vd.plan;
        }
O_HANDLER1;
$n=<<<'N_HANDLER1'
        } else if(_vd.plan && _vd.mode==='subscription'){
          P.plan = _vd.plan;
        }/*CH_PAIDDOC — doc odemesinde plan degistirme; kredi kullanilir*/
N_HANDLER1;
$c=substr_count($src,$o); if($c===1){ $src=str_replace($o,$n,$src); echo "  ✓ [HANDLER1] tamam\n"; $ok++; } else echo "  ✗ [HANDLER1] anchor ($c, 1 beklenir) — atlandi\n";
$o=<<<'O_SRSTART'
function showResult(doc,dk,research,docId){
  const d=DOCS[dk]||DOCS._default;const c=CC_DATA[CC]||CC_DATA.DE;
O_SRSTART;
$n=<<<'N_SRSTART'
function showResult(doc,dk,research,docId){
  const _isPaidCard=(window.__paidDoc===true); try{window.__paidDoc=false;}catch(_pe){}/*CH_PAIDDOC*/
  const d=DOCS[dk]||DOCS._default;const c=CC_DATA[CC]||CC_DATA.DE;
N_SRSTART;
$c=substr_count($src,$o); if($c===1){ $src=str_replace($o,$n,$src); echo "  ✓ [SRSTART] tamam\n"; $ok++; } else echo "  ✗ [SRSTART] anchor ($c, 1 beklenir) — atlandi\n";
$o=<<<'O_PD'
    const _up3=P.plan||_lsU3.plan||'free';
    if(_tk3&&_up3!=='free'){
O_PD;
$n=<<<'N_PD'
    const _up3=P.plan||_lsU3.plan||'free';
    if((_tk3&&_up3!=='free')||_isPaidCard){/*CH_PAIDDOC*/
N_PD;
$c=substr_count($src,$o); if($c===1){ $src=str_replace($o,$n,$src); echo "  ✓ [PD] tamam\n"; $ok++; } else echo "  ✗ [PD] anchor ($c, 1 beklenir) — atlandi\n";
$o=<<<'O_SD'
const _up4=P.plan||_lsU4.plan||'free';if(!_tk4||_up4==='free'){showPaywall(dk||CD,{});return;}
O_SD;
$n=<<<'N_SD'
const _up4=P.plan||_lsU4.plan||'free';if((!_tk4||_up4==='free') && !_isPaidCard){showPaywall(dk||CD,{});return;}/*CH_PAIDDOC*/
N_SD;
$c=substr_count($src,$o); if($c===1){ $src=str_replace($o,$n,$src); echo "  ✓ [SD] tamam\n"; $ok++; } else echo "  ✗ [SD] anchor ($c, 1 beklenir) — atlandi\n";

if($ok<5){ echo "\n⚠ ".(5-$ok)." parca uygulanamadi — DEGISTIRILMEDI (butunluk icin).\n"; if($ok<5) exit; }
$tmp=tempnam(sys_get_temp_dir(),'pd').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-paiddoc-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_PAIDDOC')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_PAIDDOC diskte ($ok/5, ".strlen($chk)." B)\n";
echo "  Tek-belge: ode -> kredi ile uretilir -> PDF + Senden(fax) acilir.\n";
echo "  SIRADA: apply-paidmail.php (otomatik e-posta).\n";