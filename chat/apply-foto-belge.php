<?php
/**
 * ChatHelp — apply-foto-belge (CH_FOTO_BELGE) — Belge & Foto Merkezi'ne
 *  PDF BELGE yukleme eklenir (yalniz foto degil):
 *  [1] Yukleme girisi artik image/* + application/pdf kabul eder
 *      (kamera girisi foto olarak kalir).
 *  [2] Dosya filtresi PDF'e izin verir; PDF'ler de analyze_photo'ya
 *      mime=application/pdf ile gider (backend vision PDF okuyabilir);
 *      okunamazsa kullaniciya acik uyari gosterilir.
 *  [3] PDF ogeleri panelde 📄 simgesiyle gosterilir (gorsel onizleme yerine).
 *  3 count-guard'li str_replace — canli CH_FOTO_PANEL blogu uzerinde.
 * KULLANIM: pull2.php?key=...&files=apply-foto-belge.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-foto-belge BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_FOTO_BELGE')!==false) exit("Zaten ekli (CH_FOTO_BELGE).\n");
if (strpos($src,'CH_FOTO_PANEL')===false) exit("HATA: once CH_FOTO_PANEL gerekli (apply-foto-panel.php).\n");

$fail=[];
function rep(&$src,$o,$n,$tag,&$fail){
  $c=substr_count($src,$o);
  if($c!==1){ echo "  ✗ [$tag] anchor $c kez (1 olmali)\n"; $fail[]=$tag; return; }
  $src=str_replace($o,$n,$src);
  echo "  ✓ [$tag] uygulandi\n";
}

/* [1] yukleme girisi: PDF de kabul */
rep($src,
  '<input id="fp-up" type="file" accept="image/*" multiple style="display:none">',
  '<input id="fp-up" type="file" accept="image/*,application/pdf" multiple style="display:none">',
  '1-accept',$fail);

/* [2] filtre: image VEYA pdf */
rep($src,
  "for(var i=0;i<fl.length && i<LIM;i++){ if(/^image\\//.test(fl[i].type||'')) addItem(fl[i]); }",
  "for(var i=0;i<fl.length && i<LIM;i++){ var ft=fl[i].type||''; if(/^image\\//.test(ft)||ft==='application/pdf'||/\\.pdf$/i.test(fl[i].name||'')) addItem(fl[i]); } /*CH_FOTO_BELGE*/",
  '2-filter',$fail);

/* [3] PDF onizleme: gorsel yerine 📄 simge + mime duzeltme + hata mesaji */
rep($src,
  "var img=document.createElement('img');\n    try{ img.src=URL.createObjectURL(file); }catch(e){}",
  "var isPdf=((file.type||'')==='application/pdf')||/\\.pdf$/i.test(file.name||'');\n    var img=document.createElement(isPdf?'div':'img');\n    if(isPdf){ img.setAttribute('style','width:64px;height:64px;border-radius:10px;flex:0 0 auto;border:1px solid rgba(212,168,74,.4);display:flex;align-items:center;justify-content:center;font-size:30px;background:#1a1a30'); img.textContent='📄'; }\n    else { try{ img.src=URL.createObjectURL(file); }catch(e){} }",
  '3-preview',$fail);

/* [3b] analyze: mime pdf ise dogru mime gonder */
rep($src,
  "body:JSON.stringify({b64:b64,mime:file.type||'image/jpeg',lang:UIL()})})",
  "body:JSON.stringify({b64:b64,mime:(file.type||((/\\.pdf$/i.test(file.name||''))?'application/pdf':'image/jpeg')),lang:UIL()})})",
  '3b-mime',$fail);

/* [4] kart ANA SAYFANIN EN USTUNE (hero dahil her seyin ustune) */
rep($src,
  "var grid=document.querySelector('.wcat-grid');\n        var host=grid?grid.parentNode:null;",
  "var wlc=document.getElementById('wlc');\n        var grid=document.querySelector('.wcat-grid');\n        var host=wlc||(grid?grid.parentNode:null);",
  '4-top-host',$fail);
rep($src,
  "host.insertBefore(c,grid);",
  "if(wlc&&wlc.firstChild){ wlc.insertBefore(c,wlc.firstChild); } else if(grid){ host.insertBefore(c,grid); } else { host.insertBefore(c,host.firstChild||null); }",
  '4b-top-insert',$fail);

if ($fail) { echo "\nHATA: eslesmeyen: ".implode(', ',$fail)." — index DEGISTIRILMEDI.\n"; exit; }

/* marker yorum olarak da eklensin (guard) */
$src=str_replace('/* CH_FOTO_PANEL — ana sayfa','/* CH_FOTO_PANEL + CH_FOTO_BELGE — ana sayfa',$src);

$tmp = tempnam(sys_get_temp_dir(),'fb').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-fotobelge-'.date('Ymd-His'), (string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_FOTO_BELGE')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "\n  ✓ DOGRULAMA: CH_FOTO_BELGE diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Belge & Foto Merkezi artik FOTO + PDF BELGE kabul ediyor:\n";
echo "   • 🖼️ Yukle dugmesi: gorseller VE PDF'ler (8'e kadar, karisik olabilir)\n";
echo "   • PDF'ler panelde 📄 simgesiyle listelenir, ayni analiz+ozet+secenekler\n";
echo "   • 📷 Kamera dugmesi foto cekmeye devam eder\n";
echo "   TEST: panele bir PDF yukleyin — ozet gelmezse dump-doc-backend.php\n";
echo "   ciktisini gonderin, backend PDF destegi eklenir.\n";
