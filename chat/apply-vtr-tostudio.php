<?php
/**
 * ChatHelp — apply-vtr-tostudio (CH_VTR_TOSTUDIO) — sozlesmeler KARTLARDAN cikar,
 *  tamami VERTRAG STUDIO'da (her ulke kendi hukukuyla; DE basta ~17 sozlesme,
 *  TR 7, ES/FR/IT/GB 2'ser — hepsi premium A4 sablonlu ve "Kendi Maddeleriniz /
 *  Eigene Klauseln" adimli).
 *  [1] Kart kataloglarindaki SOZLESME-TIPI belgeler (soru-cevapla uretilen
 *      Mietvertrag, Arbeitsvertrag, Kfz-Kaufvertrag, Kira Sozlesmesi, Contrato...)
 *      TUM ulkelerde listelerden kaldirilir (baslik/anahtar kurallariyla;
 *      "Kündigung Mietvertrag" gibi DILEKCELER kalir).
 *  [2] Sozlesme kategorisi kartlari (Verträge/Sözleşmeler/Contratos...) gizlenir.
 *  [3] Sohbet/detect bir sozlesmeye yonlenirse Studio acilir (showQuestions sarmasi).
 *  Ana sayfa vitrini + hub "Verträge" karti Studio'ya acmaya devam eder.
 * KULLANIM: pull2.php?key=...&files=apply-vtr-tostudio.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-vtr-tostudio BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_VTR_TOSTUDIO')!==false) exit("Zaten ekli (CH_VTR_TOSTUDIO).\n");
if (strpos($src,'ncxBuild')===false) exit("HATA: Vertrag Studio motoru yok.\n");

$bundle = <<<'HTML'
<script id="ch-vtr-tostudio">
/* CH_VTR_TOSTUDIO — sozlesmeler kartlardan Studio'ya */
try{(function(){
  /* acikca bilinen eski sozlesme anahtarlari */
  var HARD={mietvertrag:1,arbeitsvertrag:1,kfz_kaufvertrag:1,aufhebungsvertrag:1,esx_autonomos_contrato_trade:1};
  /* baslik kurallari: TAM "…vertrag" basligi, "… Sözleşmesi" ile BITEN,
     "Contrato/Contrat/Contratto …" ile BASLAYAN, "… Agreement" ile BITEN */
  var RX=[
    /^(unbefristeter\s|befristeter\s)?[\wäöüÄÖÜß-]*vertrag$/i,
    /sözleşmesi$/i,
    /^contrato\s/i, /^contrat\s/i, /^contratto\s/i,
    /\bagreement$/i
  ];
  function isContract(k){
    try{
      if(HARD[k]) return true;
      if(k.indexOf("vtr_")===0||k.indexOf("nc_")===0) return false; /* Studio anahtarlari kartlarda degil */
      var d=(typeof DOCS!=="undefined"&&DOCS)?DOCS[k]:null; if(!d||!d.n) return false;
      var n=String(d.n).trim();
      for(var i=0;i<RX.length;i++){ if(RX[i].test(n)) return true; }
      return false;
    }catch(e){ return false; }
  }
  var CATRX=/vertraeg|verträge|sözleşme|contratos|contrats|contratti|contracts|avtal/i;
  function isContractCat(key,c){
    try{ return CATRX.test(key)||CATRX.test((c&&c.n)||""); }catch(e){ return false; }
  }
  var removed={};
  function filterCats(){
    try{
      if(typeof CATS==="undefined"||!CATS) return false;
      var did=false;
      for(var ck in CATS){
        var c=CATS[ck]; if(!c) continue;
        if(c.docs&&c.docs.length){
          var keep=[];
          for(var i=0;i<c.docs.length;i++){
            var k=c.docs[i];
            if(isContract(k)){ removed[k]=1; did=true; }
            else keep.push(k);
          }
          if(keep.length!==c.docs.length) c.docs=keep;
        }
        if(typeof CAT_DOCS!=="undefined"&&CAT_DOCS&&CAT_DOCS[ck]&&CAT_DOCS[ck].length){
          var keep2=[];
          for(var j=0;j<CAT_DOCS[ck].length;j++){
            var e2=CAT_DOCS[ck][j], k2=(e2&&e2.k)||"";
            if(isContract(k2)){ removed[k2]=1; did=true; } else keep2.push(e2);
          }
          if(keep2.length!==CAT_DOCS[ck].length) CAT_DOCS[ck]=keep2;
        }
      }
      return did;
    }catch(e){ return false; }
  }
  function hideCatCards(){
    try{
      if(typeof CATS==="undefined"||!CATS) return;
      var grid=document.querySelector(".wcat-grid"); if(!grid) return;
      for(var ck in CATS){
        var c=CATS[ck]; if(!c) continue;
        var empty=!(c.docs&&c.docs.length);
        if(isContractCat(ck,c) || (empty&&isContractCat(ck,c))){
          var el=grid.querySelector('[data-ccat="'+ck+'"],[data-univcat="'+ck+'"],[data-trcat="'+ck+'"],[data-frcat="'+ck+'"]');
          if(el&&el.style.display!=="none") el.style.display="none";
        }
      }
    }catch(e){}
  }
  /* [3] sozlesmeye yonelen akislar Studio'ya */
  function wrapSQ(){
    try{
      if(typeof showQuestions!=="function"||showQuestions._chVtr) return;
      var _s=showQuestions;
      var w=function(dk){
        try{
          if(isContract(dk)||removed[dk]){
            try{ gP("vst"); }catch(e){}
            return;
          }
        }catch(e){}
        return _s.apply(this,arguments);
      };
      w._chVtr=1;
      try{ for(var p in _s){ w[p]=_s[p]; } }catch(e){}
      showQuestions=w; if(typeof window!=="undefined") window.showQuestions=w;
    }catch(e){}
  }
  var ticks=0;
  function run(){
    filterCats(); hideCatCards(); wrapSQ();
    if(ticks++<40) setTimeout(run,500);
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
  setInterval(function(){ hideCatCards(); wrapSQ(); },1600);
})();}catch(e){}
</script>
HTML;

$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$src = substr($src,0,$pos).$bundle."\n".substr($src,$pos);
echo "  ✓ [1] sozlesme-tipi belgeler kart kataloglarindan kaldirildi (tum ulkeler)\n";
echo "  ✓ [2] sozlesme kategori kartlari gizlendi\n";
echo "  ✓ [3] detect/kart -> sozlesme istegi artik Vertrag Studio'yu acar\n";

$tmp = tempnam(sys_get_temp_dir(),'vs').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
$bk=@file_put_contents($file.'.bak-vtrstudio-'.date('Ymd-His'), @file_get_contents($file));
if ($bk===false) echo "  ⚠ yedek yazilamadi — devam\n";
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI: ".var_export($w,true)."\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_VTR_TOSTUDIO')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_VTR_TOSTUDIO diskte (".strlen($chk)." bayt)\n";
echo "\n✓ CH_VTR_TOSTUDIO uygulandi. Sozlesmeler artik yalniz Vertrag Studio'da\n";
echo "   (premium sablon + kendi maddeler); kartlarda sozlesme kalmadi.\n";
