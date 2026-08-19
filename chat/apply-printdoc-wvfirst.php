<?php
/**
 * ChatHelp — apply-printdoc-wvfirst (CH_PRINTDOC_WVFIRST) — KESIN KOK NEDEN,
 *  tum 'Ungültige Webadresse' / App'ten disari atma macerasinin GERCEK sebebi:
 *  window.chPrintDoc IKI KEZ tanimlanmis (CH_CPDWV/CH_PDFFORM ilk/olu kopyaya
 *  eklenmis), canli olarak calisan (dosyada SONRAKI, dolayisiyla ONCEKINI ezen)
 *  IKINCI tanimda 'isMobile()' kontrolu 'isWV()' kontrolunden ONCE calisiyor ve
 *  window.open('','_blank') ile HEMEN return ediyor — App-guvenli dl.php form-post
 *  mantigina (ayni fonksiyonun ALT kismindaki) HICBIR ZAMAN ulasilamiyordu.
 *  COZUM: isWV() kontrolu artik isMobile()'dan ONCE calisiyor — App icinde
 *  window.open/yeni-sekme HIC denenmeden dogrudan ayni-sayfada dl.php'ye
 *  form-POST ediliyor (sayfa degistirmez, App'ten disari atmaz). Sadece App
 *  DISINDAKI normal mobil tarayicilar eski (yeni sekme) davranisini korur.
 *  1 sayac-korumali str_replace. Eslesmezse HICBIR sey degistirilmez.
 * KULLANIM: pull2.php?key=...&files=apply-printdoc-wvfirst.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-printdoc-wvfirst BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_PRINTDOC_WVFIRST')!==false) exit("Zaten ekli (CH_PRINTDOC_WVFIRST).\n");

$old = "window.chPrintDoc=function(html){\n"
      ."    try{\n"
      ."      var H=harden(String(html));\n"
      ."      if(isMobile()){\n"
      ."        var w=null; try{ w=window.open('','_blank'); }catch(e){ w=null; }\n"
      ."        if(w&&w.document){ try{ w.document.open(); w.document.write(toTab(H)); w.document.close(); return; }catch(e){} }\n"
      ."        overlay(String(html)); return;\n"
      ."      }\n";

$new = "window.chPrintDoc=function(html){\n"
      ."    try{\n"
      ."      var H=harden(String(html));\n"
      ."      var _isWv3=false; try{ _isWv3=(typeof isWV==='function')&&isWV(); }catch(e0){}/*CH_PRINTDOC_WVFIRST*/\n"
      ."      if(_isWv3){\n"
      ."        try{\n"
      ."          var _f3=document.createElement('form'); _f3.method='POST'; _f3.action='dl.php'; _f3.target='_self'; _f3.style.display='none';\n"
      ."          var _i3a=document.createElement('input'); _i3a.name='html'; _i3a.value=H; _f3.appendChild(_i3a);\n"
      ."          var _i3b=document.createElement('input'); _i3b.name='name'; _i3b.value='ChatHelp-Dokument.html'; _f3.appendChild(_i3b);\n"
      ."          document.body.appendChild(_f3); _f3.submit();\n"
      ."          setTimeout(function(){ try{ document.body.removeChild(_f3); }catch(e9c){} },1500);\n"
      ."          return;\n"
      ."        }catch(e1c){}\n"
      ."      }\n"
      ."      if(isMobile()){\n"
      ."        var w=null; try{ w=window.open('','_blank'); }catch(e){ w=null; }\n"
      ."        if(w&&w.document){ try{ w.document.open(); w.document.write(toTab(H)); w.document.close(); return; }catch(e){} }\n"
      ."        overlay(String(html)); return;\n"
      ."      }\n";

$c=substr_count($src,$old);
if($c!==1){ echo "  ✗ anchor ($c, 1 beklenir) — HICBIR sey degistirilmedi.\n"; exit; }
$src=str_replace($old,$new,$src);
echo "  ✓ chPrintDoc: isWV() artik isMobile()'dan ONCE kontrol ediliyor\n";
echo "  -> App'te (isWV=true): dogrudan ayni-sayfada dl.php'ye form-POST (sayfa degismez)\n";
echo "  -> Normal mobil tarayici (isWV=false, isMobile=true): eski yeni-sekme davranisi\n";

$tmp=tempnam(sys_get_temp_dir(),'pwf').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-printdocwvfirst-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_PRINTDOC_WVFIRST')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_PRINTDOC_WVFIRST diskte (".strlen($chk)." B)\n";
echo "  TEST (kritik): App'te herhangi bir belge -> 'PDF' / 'Kostenlos selbst ausdrucken' /\n";
echo "  'Senden' -> App'ten disari ATMAMALI, 'Ungültige Webadresse' CIKMAMALI, ayni\n";
echo "  ekranda PDF inmeli. Bu, aylardir suren PDF sorununun kesin duzeltmesi olmali.\n";
