<?php
/**
 * ChatHelp — apply-addrpdf-fix — IKI AYRI KOK NEDEN duzeltmesi:
 *  [1] CH_PROFBOOTFIX: CH_PROFILES modulunde boot() -> 'if(hasAddr(a[i].d)||i>0)
 *      applyP(a[i].d);' — aktif profil slotu (i) 0'dan FARKLIYSA, o slotun verisi
 *      BOS olsa bile hasAddr kontrolu atlanip P (ve dolayisiyla adres) eziliyordu.
 *      Elite hesaplarda coklu-profil test edildigi icin ch_prof_active kolayca
 *      0'dan farkli kalabiliyor -> her sayfa yuklemesinde adres sessizce siliniyor.
 *      COZUM: i>0 kisayolu kaldirildi, SADECE hasAddr kontrolu kaliyor.
 *  [2] CH_STUDIOPDF: CV Studio + Vertrag Studio'nun KENDI makePdf() fonksiyonlari
 *      chPrintDoc() KULLANMIYORDU — kendi window.open("","_blank")+window.print()
 *      kodunu calistiriyordu (App/WebView'den disari atan AYNI bozuk kalip, ama
 *      onceki CH_CPDWV/CH_PDFFORM duzeltmeleri SADECE chPrintDoc'u kapsiyordu, bu
 *      2 ayri fonksiyonu degil). COZUM: ikisi de artik once window.chPrintDoc()
 *      kullaniyor (App-guvenli), sadece o yoksa eski window.open fallback'i.
 *  Her bolum BAGIMSIZ sayac-korumali str_replace. Eslesmeyen bolum ATLANIR
 *  (digerleri yine de uygulanir), sonunda hangi bolumlerin basarili oldugu raporlanir.
 * KULLANIM: pull2.php?key=...&files=apply-addrpdf-fix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-addrpdf-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_PROFBOOTFIX')!==false && strpos($src,'CH_STUDIOPDF')!==false) exit("Zaten ekli.\n");

$ok=0;$tot=0;

/* [1] CH_PROFBOOTFIX */
if(strpos($src,'CH_PROFBOOTFIX')===false){
  $tot++;
  $n1='if(hasAddr(a[i].d)||i>0) applyP(a[i].d);';
  $o1='if(hasAddr(a[i].d)) applyP(a[i].d);/*CH_PROFBOOTFIX*/';
  $c1=substr_count($src,$n1);
  if($c1===1){ $src=str_replace($n1,$o1,$src); echo "  ✓ [1] CH_PROFBOOTFIX — profil boot() adres-ezme bug'i duzeltildi\n"; $ok++; }
  else { echo "  ✗ [1] CH_PROFBOOTFIX anchor ($c1, 1 beklenir) — atlandi\n"; }
} else { echo "  = [1] CH_PROFBOOTFIX zaten ekli\n"; $ok++; }

/* [2] CH_STUDIOPDF — CV Studio */
if(strpos($src,'CH_STUDIOPDF_CV')===false){
  $tot++;
  $n2 = "  function makePdf(){\n"
       ."    if(!hasPlan()){ gate(); return; }\n"
       ."    var html='<!doctype html><html><head><meta charset=\"utf-8\"><title>'+esc(DATA.name||\"CV\")+' — Lebenslauf</title>'\n"
       ."     +'<style>@page{size:A4;margin:0}html,body{margin:0;padding:0}'+TPLCSS+'</style></head><body>'\n"
       ."     +build(DATA)\n"
       ."     +'<script>window.onload=function(){setTimeout(function(){window.print();},350);};<\/script></body></html>';\n"
       ."    var w=window.open(\"\",\"_blank\");\n"
       ."    if(!w){ alert(\"Popup?\"); return; }\n"
       ."    w.document.open(); w.document.write(html); w.document.close();\n"
       ."  }";
  $o2 = "  function makePdf(){/*CH_STUDIOPDF_CV*/\n"
       ."    if(!hasPlan()){ gate(); return; }\n"
       ."    var html='<!doctype html><html><head><meta charset=\"utf-8\"><title>'+esc(DATA.name||\"CV\")+' — Lebenslauf</title>'\n"
       ."     +'<style>@page{size:A4;margin:0}html,body{margin:0;padding:0}'+TPLCSS+'</style></head><body>'\n"
       ."     +build(DATA)\n"
       ."     +'</body></html>';\n"
       ."    if(window.chPrintDoc){ window.chPrintDoc(html); return; }\n"
       ."    var w=window.open(\"\",\"_blank\");\n"
       ."    if(!w){ alert(\"Popup?\"); return; }\n"
       ."    w.document.write(html+'<script>window.onload=function(){setTimeout(function(){window.print();},350);};<\/script>');\n"
       ."    w.document.close();\n"
       ."  }";
  $c2=substr_count($src,$n2);
  if($c2===1){ $src=str_replace($n2,$o2,$src); echo "  ✓ [2] CH_STUDIOPDF_CV — CV Studio artik chPrintDoc kullaniyor\n"; $ok++; }
  else { echo "  ✗ [2] CH_STUDIOPDF_CV anchor ($c2, 1 beklenir) — atlandi\n"; }
} else { echo "  = [2] CH_STUDIOPDF_CV zaten ekli\n"; $ok++; }

/* [3] CH_STUDIOPDF — Vertrag Studio */
if(strpos($src,'CH_STUDIOPDF_VST')===false){
  $tot++;
  $n3 = "  function makePdf(){\n"
       ."    var pl=plan(), q=QUOTA[pl]||0;\n"
       ."    if(!q){ gate(); return; }\n"
       ."    if(used()>=q){ gate(); return; }\n"
       ."    var pages=buildPages(CUR,getD(CUR));\n"
       ."    var html='<!doctype html><html><head><meta charset=\"utf-8\"><title>'+esc(CT[CUR].n)+'</title>'\n"
       ."     +'<style>@page{size:A4;margin:0}html,body{margin:0;padding:0}'+VCSS+'\\n.vsA4{page-break-after:always}.vsA4:last-child{page-break-after:auto}</style></head><body>'\n"
       ."     +pages.join(\"\")\n"
       ."     +'<script>window.onload=function(){setTimeout(function(){window.print();},350);};<\/script></body></html>';\n"
       ."    var w=window.open(\"\",\"_blank\");\n"
       ."    if(!w){ alert(\"Popup?\"); return; }\n"
       ."    w.document.open(); w.document.write(html); w.document.close();\n"
       ."    bumpUsed();\n"
       ."  }";
  $o3 = "  function makePdf(){/*CH_STUDIOPDF_VST*/\n"
       ."    var pl=plan(), q=QUOTA[pl]||0;\n"
       ."    if(!q){ gate(); return; }\n"
       ."    if(used()>=q){ gate(); return; }\n"
       ."    var pages=buildPages(CUR,getD(CUR));\n"
       ."    var html='<!doctype html><html><head><meta charset=\"utf-8\"><title>'+esc(CT[CUR].n)+'</title>'\n"
       ."     +'<style>@page{size:A4;margin:0}html,body{margin:0;padding:0}'+VCSS+'\\n.vsA4{page-break-after:always}.vsA4:last-child{page-break-after:auto}</style></head><body>'\n"
       ."     +pages.join(\"\")\n"
       ."     +'</body></html>';\n"
       ."    bumpUsed();\n"
       ."    if(window.chPrintDoc){ window.chPrintDoc(html); return; }\n"
       ."    var w=window.open(\"\",\"_blank\");\n"
       ."    if(!w){ alert(\"Popup?\"); return; }\n"
       ."    w.document.write(html+'<script>window.onload=function(){setTimeout(function(){window.print();},350);};<\/script>');\n"
       ."    w.document.close();\n"
       ."  }";
  $c3=substr_count($src,$n3);
  if($c3===1){ $src=str_replace($n3,$o3,$src); echo "  ✓ [3] CH_STUDIOPDF_VST — Vertrag Studio artik chPrintDoc kullaniyor\n"; $ok++; }
  else { echo "  ✗ [3] CH_STUDIOPDF_VST anchor ($c3, 1 beklenir) — atlandi\n"; }
} else { echo "  = [3] CH_STUDIOPDF_VST zaten ekli\n"; $ok++; }

if($ok===0){ echo "\nHICBIR bolum eslesmedi — HICBIR sey degistirilmedi.\n"; exit; }

$tmp=tempnam(sys_get_temp_dir(),'apf').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-addrpdffix-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
echo "\n  Sonuc: $ok/$tot bolum basariyla uygulandi (".strlen($chk)." B)\n";
echo "  Test 1 (adres): Elite hesapla Konto -> Persönliche Daten -> adres gir -> kaydet ->\n";
echo "  uygulamayi tam kapat-ac -> adres hala orada olmali.\n";
echo "  Test 2 (PDF): CV Studio / Vertrag Studio -> 'PDF erstellen' -> App'ten disari\n";
echo "  atmamali, direkt PDF indirmeli (chPrintDoc'un App-guvenli davranisi devreye girer).\n";
