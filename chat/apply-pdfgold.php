<?php
/**
 * ChatHelp — apply-pdfgold (CH_PDFGOLD) — App katmaninda 'Drucken' kalksin,
 *  yerine '⬇️ PDF' dugmesi ALTIN (birincil) olsun.
 *
 * GEREKCE: WebView'de window.print() sessiz no-op; kullanici icin 'Drucken'
 *  hicbir sey ifade etmiyor. PDF indirmek yetiyor (CH_NATIVEPDF ile calisiyor).
 *
 * BU YAMA (tamamen EKLEME, cok kucuk):
 *  · Sadece #ch-appview katmani icinde is yapar (katman yalniz App'te acilir)
 *  · #cv-print -> display:none  (SILINMEZ; baska kod referans verirse patlamaz)
 *  · #cv-pdf   -> class 'cv-pri' eklenir (CH_APPVIEW3'un mevcut altin stili)
 *  · Idempotent: is bitmisse hicbir sey yapmaz; CH_NATIVEPDF dugmeyi
 *    cloneNode ile degistirse bile sinif/klon ile korunur, yine de kendini onarir
 *  · Dinleyicilere DOKUNMAZ (clone/replace YOK) -> CH_NATIVEPDF tiklamalari bozulmaz
 *  · Web tarayicisinda #ch-appview hic olusmaz -> web HIC etkilenmez
 *
 * Kritik isaret dogrulamasi + lint + .bak + kilit tazeleme.
 * KULLANIM: /chat/pull2.php?key=...&files=apply-pdfgold.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
echo "apply-pdfgold BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$dir=__DIR__; $file=$dir.'/index.php';
$src=@file_get_contents($file);
if($src===false||$src==='') exit("index.php okunamadi\n");
echo "index.php: ".strlen($src)." B  (".date('Y-m-d H:i',filemtime($file)).")\n";

if(strpos($src,'CH_PDFGOLD')!==false) exit("\nZaten ekli (CH_PDFGOLD) — DEGISIKLIK YOK.\n");

/* on kosul: katman ve native pdf yamasi yerinde olmali */
foreach(['CH_APPVIEW3','CH_NATIVEPDF'] as $need){
  if(substr_count($src,$need)<1) exit("\n✗ Onkosul eksik ($need) — DEGISIKLIK YOK.\n");
}

$js = <<<'HTML'
<script>/*CH_PDFGOLD*/(function(){try{
/* App katmaninda: 'Drucken' gizlenir, '⬇️ PDF' altin birincil dugme olur. */
function apply(){
  try{
    var ov=document.getElementById('ch-appview'); if(!ov) return;
    var act=ov.querySelector('.cv-act'); if(!act) return;

    /* 1) Drucken dugmesini gizle (id ile; id yoksa metinden bul) */
    var pr=ov.querySelector('#cv-print');
    if(!pr){
      var bs=act.querySelectorAll('button');
      for(var i=0;i<bs.length;i++){
        var tx=(bs[i].textContent||'');
        if(/Drucken|Yazd|Print/i.test(tx) && !/PDF/i.test(tx)){ pr=bs[i]; break; }
      }
    }
    if(pr && pr.style.display!=='none'){ pr.style.display='none'; }

    /* 2) PDF dugmesini altin yap (E-Mail dugmesi haric) */
    var pd=ov.querySelector('#cv-pdf');
    if(!pd){
      var bs2=act.querySelectorAll('button');
      for(var j=0;j<bs2.length;j++){
        var t2=(bs2[j].textContent||'');
        if(/PDF/i.test(t2) && !/E-?Mail|posta|mail/i.test(t2)){ pd=bs2[j]; break; }
      }
    }
    if(pd && pd.className.indexOf('cv-pri')<0){
      pd.className=(pd.className?pd.className+' ':'')+'cv-pri';
    }
  }catch(e){}
}
function start(){ try{ apply();
  var mo=new MutationObserver(function(){ apply(); });
  mo.observe(document.body,{childList:true,subtree:true});
}catch(e){} }
if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',start); else start();
}catch(e){}})();</script>
HTML;

$p=strripos($src,'</body>');
if($p===false) exit("✗ </body> bulunamadi — DEGISIKLIK YOK\n");
$new=substr($src,0,$p).$js.substr($src,$p);

foreach(['CH_ISWV_GLOBAL'=>1,'CH_APPVIEW3'=>1,'CH_APPVIEW2C'=>4,'CH_MAILBTN2'=>1,
         'CH_FILLGAPS'=>1,'CH_NATIVEPDF'=>1,'CH_PDFGOLD'=>1] as $m=>$min){
  if(substr_count($new,$m)<$min) exit("✗ Kritik isaret eksik olurdu ($m) — DEGISIKLIK YOK\n");
}

$tmp=tempnam(sys_get_temp_dir(),'pg').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0) exit("\n✗ LINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n");

@file_put_contents($file.'.bak-pdfgold-'.date('Ymd-His'),$src);
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();

$cur=(string)@file_get_contents($file);
@file_put_contents($file.'.GOOD-appprint',$cur);
@file_put_contents($dir.'/ch-applock.json',json_encode([
 'locked_at'=>date('c'),'size'=>strlen($cur),'sha1'=>sha1($cur),'note'=>'pdfgold',
 'markers'=>['CH_ISWV_GLOBAL'=>substr_count($cur,'CH_ISWV_GLOBAL'),'CH_APPVIEW3'=>substr_count($cur,'CH_APPVIEW3'),
             'CH_APPVIEW2C'=>substr_count($cur,'CH_APPVIEW2C'),'CH_MAILBTN2'=>substr_count($cur,'CH_MAILBTN2'),
             'CH_FILLGAPS'=>substr_count($cur,'CH_FILLGAPS'),'CH_NATIVEPDF'=>substr_count($cur,'CH_NATIVEPDF'),
             'CH_PDFGOLD'=>substr_count($cur,'CH_PDFGOLD')],
 'files'=>['pv.php','pvsave.php','pvmail.php','pdflib.php']
],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

echo "\n✓ CH_PDFGOLD eklendi (".strlen($src)." -> ".strlen($cur)." bayt)\n";
echo "  CH_PDFGOLD=".substr_count($cur,'CH_PDFGOLD')
    ."  CH_NATIVEPDF=".substr_count($cur,'CH_NATIVEPDF')
    ."  CH_MAILBTN2=".substr_count($cur,'CH_MAILBTN2')
    ."  CH_APPVIEW3=".substr_count($cur,'CH_APPVIEW3')
    ."  CH_ISWV_GLOBAL=".substr_count($cur,'CH_ISWV_GLOBAL')."\n";
echo "  ✓ kilit tazelendi (sha1 ".substr(sha1($cur),0,12)."…)\n";
echo "\nTEST (App): belgeyi ac -> dugme siras:  📧 PDF per E-Mail | ⬇️ PDF (ALTIN) | 📋 | ✕\n";
echo "  · 'Drucken' artik gorunmuyor\n";
echo "  · '⬇️ PDF' tiklayinca eskisi gibi calisiyor (CH_NATIVEPDF dokunulmadi)\n";
echo "  · Web: #ch-appview olusmadigi icin hicbir degisiklik yok\n";
