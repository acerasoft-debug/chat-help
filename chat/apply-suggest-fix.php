<?php
/**
 * ChatHelp — apply-suggest-fix (CH_SUGG_FIX) — "📄 Dokument erstellen" cipi olu idi
 *  HATA: sohbette AI'nin onerdigi "📄 Dokument erstellen →" cipi gP("chat")
 *  cagiriyordu — kullanici ZATEN chat'te, yani tiklayinca HICBIR SEY olmuyordu.
 *  (vst/cv/jobs cipleri dogru panele gidiyordu; yalniz doc olu idi.)
 *  [1] Cip tiklamasi: son kullanici mesaji action=detect'e gonderilir;
 *      uygun SABLON varsa (doc!=='other') dogrudan sablon akisi acilir
 *      (CD + showQuestions). Sablon yoksa/istek ozelse "Dokument erstellen &
 *      verbessern" paneli (chOpenDev) acilir — orada serbest uretim + PDF var.
 *      Diger cipler (vst/cv/jobs) aynen panellerine gider.
 *  [2] Prompt: kullanici PDF'i/belgeyi ACIKCA sohbette isterse artik tekrar
 *      sablona yonlendirilmez — [[DOC]] formatinda tam belge uretilir
 *      (mevcut belge-karti + PDF butonu akisi bunu zaten isliyor).
 * KULLANIM: pull2.php?key=...&files=apply-suggest-fix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-suggest-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$idxFile = __DIR__.'/index.php';
$apiFile = __DIR__.'/api.php';
$idx = @file_get_contents($idxFile);
$api = @file_get_contents($apiFile);
if ($idx===false) exit("index.php okunamadi\n");
if ($api===false) exit("api.php okunamadi\n");
if (strpos($idx,'CH_SUGG_FIX')!==false) exit("Zaten ekli (CH_SUGG_FIX).\n");

$fail=[];

/* ── [1] cip tiklama isleyicisi ── */
$o1 = 'var btn=d.querySelector(".ch-suggest-chip");
      if(btn) btn.addEventListener("click",function(){ try{ gP(info[1]); }catch(e){} });';
$n1 = 'var btn=d.querySelector(".ch-suggest-chip");
      if(btn) btn.addEventListener("click",async function(){ /*CH_SUGG_FIX*/
        if(kind!=="doc"){ try{ gP(info[1]); }catch(e){} return; }
        btn.disabled=true; btn.style.opacity=".6";
        try{
          var hs=getHist(), lastU="";
          for(var i2=hs.length-1;i2>=0;i2--){ if(hs[i2].role==="user"){ lastU=hs[i2].content||""; break; } }
          if(lastU){
            var rr=await fetch(API+"?action=detect",{method:"POST",headers:{"Content-Type":"application/json"},
              body:JSON.stringify({text:lastU,lang:(typeof dLang!=="undefined"?dLang:"de"),country:(typeof CC!=="undefined"?CC:"DE")})});
            var dd=await rr.json();
            if(dd&&dd.doc&&dd.doc!=="other"&&typeof DOCS!=="undefined"&&DOCS[dd.doc]){
              CD=dd.doc;
              setTimeout(function(){ try{ showQuestions(dd.doc); }catch(e){} },200);
              btn.disabled=false; btn.style.opacity="";
              return;
            }
          }
        }catch(e){}
        btn.disabled=false; btn.style.opacity="";
        try{ if(window.chOpenDev){ window.chOpenDev(); } }catch(e){}
      });';
$c=0; $idx=str_replace($o1,$n1,$idx,$c);
echo ($c===1?"  ✓ [1] doc cipi: sablon varsa sablona, yoksa Dokument-erstellen paneline\n":"  ✗ [1] cip anchor ($c)\n"); if($c!==1)$fail[]=1;

/* ── [2] prompt: acik PDF/belge istegi sohbette [[DOC]] ile uretilir ── */
$o2 = 'When the user wants a PDF, prefer guiding them to the template; you may still discuss the matter in depth. ';
$n2 = 'When the user EXPLICITLY asks you to create the document or PDF right here in the chat, or has declined the template, or no template fits, do NOT redirect to a template again — produce the complete ready-to-send document in the required [[DOC]] format. Otherwise, when a template fits, prefer guiding them to it; you may still discuss the matter in depth. ';
$c=0; $api=str_replace($o2,$n2,$api,$c);
echo ($c===1?"  ✓ [2] prompt: acik istekte sohbet ici [[DOC]] uretimi\n":"  ✗ [2] prompt anchor ($c)\n"); if($c!==1)$fail[]=2;

if ($fail) { echo "\nHATA: eslesmeyen adimlar: ".implode(', ',$fail)." — HICBIR dosya DEGISTIRILMEDI.\n"; exit; }

foreach ([[$idxFile,$idx],[$apiFile,$api]] as $pair) {
    $tmp = tempnam(sys_get_temp_dir(),'sf').'.php';
    file_put_contents($tmp,$pair[1]);
    $lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
    if ($rc!==0) { echo "\nLINT HATASI (".basename($pair[0]).") — HICBIR dosya degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
}
file_put_contents($idxFile.'.bak-suggfix-'.date('Ymd-His'), @file_get_contents($idxFile));
file_put_contents($apiFile.'.bak-suggfixapi-'.date('Ymd-His'), @file_get_contents($apiFile));
file_put_contents($idxFile,$idx);
file_put_contents($apiFile,$api);
echo "\n✓ CH_SUGG_FIX uygulandi:\n";
echo "   • 📄 cipi artik calisiyor: uygun sablon -> sablon akisi; yoksa Dokument-erstellen paneli\n";
echo "   • Sohbette acikca PDF istenirse belge [[DOC]] karti + PDF butonuyla uretilir\n";
