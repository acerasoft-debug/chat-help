<?php
/**
 * ChatHelp — apply-apppdf (CH_APPPDF) — App'te (Android WebView) "Als PDF speichern"
 *  kullaniciyi uygulamadan disari atiyordu (iframe.print() / window.open() native
 *  print/harici tarayici intent'i tetikliyor). KOMPLE COZUM: isWV()===true ise
 *  chPrintDoc/window.open'a HIC DOKUNMADAN, jsPDF (zaten yukleniyor: jspdf.umd.min.js)
 *  metin API'siyle (html2canvas GEREKTIRMEZ, en guvenilir yol) GERCEK bir PDF dosyasi
 *  uretip .save() ile indirir — tamamen App icinde kalir, hicbir print()/window.open() yok.
 *  Web/masaustu davranisi DEGISMEZ (sadece App/WebView icin yeni, ONCELIKLI dal eklenir).
 *  1 sayac-korumali str_replace (makePDF sonu). Eslesmezse HICBIR sey degistirilmez.
 * KULLANIM: pull2.php?key=...&files=apply-apppdf.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-apppdf BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_APPPDF')!==false) exit("Zaten ekli (CH_APPPDF).\n");

$old = "  try{ if(window.chPrintDoc){ window.chPrintDoc(_mpHtml); return; } }catch(e){}";
$new = <<<'JS'
  try{/*CH_APPPDF*/
    var _isWv=false; try{ _isWv=(typeof isWV==='function')&&isWV(); }catch(e0){}
    if(_isWv && window.jspdf && window.jspdf.jsPDF){
      try{
        var _jp=new window.jspdf.jsPDF({unit:'mm',format:'a4'});
        try{ _jp.setFont('times','normal'); }catch(ef){}
        _jp.setFontSize(11);
        var _mw=180,_mx=15,_my=22,_lh=6.2,_pgH=297-22;
        var _lines=_jp.splitTextToSize(String(doc||''),_mw);
        var _y=_my;
        for(var _i=0;_i<_lines.length;_i++){
          if(_y>_pgH){ _jp.addPage(); _y=_my; }
          _jp.text(_lines[_i],_mx,_y);
          _y+=_lh;
        }
        var _fn=(String(_mpTitle||'ChatHelp-Dokument').replace(/[^a-zA-Z0-9äöüÄÖÜß _-]/g,'')||'ChatHelp-Dokument')+'.pdf';
        _jp.save(_fn);
        return;
      }catch(e1){}
    }
  }catch(e2){}
  try{ if(window.chPrintDoc){ window.chPrintDoc(_mpHtml); return; } }catch(e){}
JS;

$c=substr_count($src,$old);
if($c!==1){ echo "  ✗ anchor ($c, 1 beklenir) — HICBIR sey degistirilmedi.\n"; exit; }
$src=str_replace($old,$new,$src);
echo "  ✓ App/WebView icin jsPDF-dogrudan PDF yolu eklendi (print()/window.open() App'te ARTIK KULLANILMIYOR)\n";

$tmp=tempnam(sys_get_temp_dir(),'ap').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-apppdf-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_APPPDF')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_APPPDF diskte (".strlen($chk)." B)\n";
echo "  Test: App'i tam kapat-ac -> bir belge -> 'Als PDF speichern' -> App icinde\n";
echo "        kalarak gercek PDF indirilir, disari atma/print ekrani YOK.\n";
echo "  KAPSAM: showResult'taki standart 'PDF' butonu (makePDF). Diger PDF butonlari\n";
echo "  (Vertrag Studio/CV Studio/Vize) bu yamaya dahil degil — orada da sorun varsa soyle.\n";
