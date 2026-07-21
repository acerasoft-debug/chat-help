<?php
/**
 * ChatHelp — apply-chprintdoc-wv (CH_CPDWV) — KOMPLE cozum: TEMEL chPrintDoc()
 *  fonksiyonunun kendisi App/WebView'de artik hicbir zaman print()/window.open()
 *  KULLANMAZ. Bu fonksiyon SITEDEKI TUM PDF/yazdirma butonlarinin ortak cikis
 *  noktasi (standart PDF, 'Kostenlos selbst ausdrucken', Vertrag Studio, CV Studio,
 *  Vize, vb. — 7+ cagri yeri) — tek yamayla HEPSI kapsanir.
 *  isWV()===true ise: iframe'e zaten yazilmis olan render edilmis metni
 *  (d.body.innerText) jsPDF ile GERCEK bir PDF'e cevirip .save() eder — App icinde
 *  kalir, disari atma YOK. Web/masaustu davranisi (print()) DEGISMEZ.
 *  1 sayac-korumali str_replace. Eslesmezse HICBIR sey degistirilmez.
 * KULLANIM: pull2.php?key=...&files=apply-chprintdoc-wv.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-chprintdoc-wv BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_CPDWV')!==false) exit("Zaten ekli (CH_CPDWV).\n");

$old = 'setTimeout(function(){ try{ f.contentWindow.focus(); f.contentWindow.print(); }catch(e){} },500);';
$new = <<<'JS'
setTimeout(function(){
        try{/*CH_CPDWV*/
          var _isWv2=false; try{ _isWv2=(typeof isWV==='function')&&isWV(); }catch(e0){}
          if(_isWv2 && window.jspdf && window.jspdf.jsPDF){
            try{
              var _txt=''; try{ _txt=d.body?(d.body.innerText||d.body.textContent||''):''; }catch(et){}
              if(_txt && _txt.replace(/\s/g,'').length>10){
                var _jp2=new window.jspdf.jsPDF({unit:'mm',format:'a4'});
                try{ _jp2.setFont('times','normal'); }catch(ef2){}
                _jp2.setFontSize(11);
                var _mw2=180,_mx2=15,_my2=22,_lh2=6.2,_pgH2=297-22;
                var _lines2=_jp2.splitTextToSize(_txt,_mw2);
                var _y2=_my2;
                for(var _i2=0;_i2<_lines2.length;_i2++){
                  if(_y2>_pgH2){ _jp2.addPage(); _y2=_my2; }
                  _jp2.text(_lines2[_i2],_mx2,_y2);
                  _y2+=_lh2;
                }
                _jp2.save('ChatHelp-Dokument.pdf');
                return;
              }
            }catch(e1b){}
          }
          f.contentWindow.focus(); f.contentWindow.print();
        }catch(e){}
      },500);
JS;

$c=substr_count($src,$old);
if($c!==1){ echo "  ✗ anchor ($c, 1 beklenir) — HICBIR sey degistirilmedi.\n"; exit; }
$src=str_replace($old,$new,$src);
echo "  ✓ TEMEL chPrintDoc App/WebView'de jsPDF ile gercek PDF uretir (print()/window.open() App'te ARTIK HIC KULLANILMIYOR)\n";
echo "  KAPSAM: standart PDF, Kostenlos selbst ausdrucken, Vertrag Studio, CV Studio, Vize — HEPSI\n";

$tmp=tempnam(sys_get_temp_dir(),'cw').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-cpdwv-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_CPDWV')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_CPDWV diskte (".strlen($chk)." B)\n";
echo "  Test: App'i tam kapat-ac -> 'Kostenlos selbst ausdrucken' VE 'Als PDF speichern'\n";
echo "        -> ikisi de App icinde kalarak gercek PDF indirir, disari atma YOK.\n";
