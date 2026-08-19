<?php
/**
 * ChatHelp — apply-pdfblob (CH_PDFBLOB) — KESIN COZUM: App'te jsPDF (harici CDN'e
 *  bagimli, guvenilirligi dogrulanamadi) YERINE, tarayicinin YERLESIK Blob+<a download>
 *  API'sini kullanir. SIFIR harici bagimlilik, print()/window.open() YOK. Tam
 *  bicimlendirilmis (.html) dosya App icinde indirilir — telefonda acilir, ayni PDF
 *  gorunumunde; gerekirse tarayicinin kendi 'Yazdir->PDF' ozelligiyle gercek PDF'e
 *  cevrilebilir. CH_APPPDF (makePDF) + CH_CPDWV (chPrintDoc x3) icindeki jsPDF
 *  dallarini bu YENI yontemle DEGISTIRIR.
 *  2 sayac-korumali str_replace (2. su >=1). Eslesmezse HICBIR sey degistirilmez.
 * KULLANIM: pull2.php?key=...&files=apply-pdfblob.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-pdfblob BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_PDFBLOB')!==false) exit("Zaten ekli (CH_PDFBLOB).\n");

$fail=[];

/* [1] makePDF (CH_APPPDF) — jsPDF -> Blob+download */
$o1 = <<<'OLD'
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
OLD;

$n1 = <<<'NEW'
  try{/*CH_APPPDF*/ /*CH_PDFBLOB*/
    var _isWv=false; try{ _isWv=(typeof isWV==='function')&&isWV(); }catch(e0){}
    if(_isWv){
      try{
        var _fn=(String(_mpTitle||'ChatHelp-Dokument').replace(/[^a-zA-Z0-9äöüÄÖÜß _-]/g,'')||'ChatHelp-Dokument')+'.html';
        var _blob=new Blob([_mpHtml],{type:'text/html'});
        var _url=URL.createObjectURL(_blob);
        var _a=document.createElement('a'); _a.href=_url; _a.download=_fn; _a.style.display='none';
        document.body.appendChild(_a); _a.click();
        setTimeout(function(){ try{ document.body.removeChild(_a); URL.revokeObjectURL(_url); }catch(e9){} },1500);
        return;
      }catch(e1){}
    }
  }catch(e2){}
  try{ if(window.chPrintDoc){ window.chPrintDoc(_mpHtml); return; } }catch(e){}
NEW;

$c1=substr_count($src,$o1);
if($c1===1){ $src=str_replace($o1,$n1,$src); echo "  ✓ [1] makePDF: jsPDF -> Blob+download (harici bagimlilik SIFIR)\n"; }
else { echo "  ✗ [1] anchor ($c1, 1 beklenir)\n"; $fail[]=1; }

/* [2] chPrintDoc (CH_CPDWV, 3 kopya) — jsPDF -> Blob+download (TAM stilli html kullanir) */
$o2 = <<<'OLD'
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
OLD;

$n2 = <<<'NEW'
setTimeout(function(){
        try{/*CH_CPDWV*/ /*CH_PDFBLOB*/
          var _isWv2=false; try{ _isWv2=(typeof isWV==='function')&&isWV(); }catch(e0){}
          if(_isWv2){
            try{
              var _blob2=new Blob([html],{type:'text/html'});
              var _url2=URL.createObjectURL(_blob2);
              var _a2=document.createElement('a'); _a2.href=_url2; _a2.download='ChatHelp-Dokument.html'; _a2.style.display='none';
              document.body.appendChild(_a2); _a2.click();
              setTimeout(function(){ try{ document.body.removeChild(_a2); URL.revokeObjectURL(_url2); }catch(e9b){} },1500);
              return;
            }catch(e1b){}
          }
          f.contentWindow.focus(); f.contentWindow.print();
        }catch(e){}
      },500);
NEW;

$c2=substr_count($src,$o2);
if($c2>=1){ $src=str_replace($o2,$n2,$src); echo "  ✓ [2] chPrintDoc: jsPDF -> Blob+download ($c2 kopya, TAM stilli HTML)\n"; }
else { echo "  ✗ [2] anchor ($c2, >=1 beklenir)\n"; $fail[]=2; }

if($fail){ echo "\nHATA: eslesmeyen adim(lar): ".implode(', ',$fail)." — HICBIR sey degistirilmedi.\n"; exit; }

$tmp=tempnam(sys_get_temp_dir(),'pb').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-pdfblob-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_PDFBLOB')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_PDFBLOB diskte (".strlen($chk)." B)\n";
echo "  Test: App'i TAM kapat-ac (2 kez) -> 'Als PDF speichern' -> App icinde kalarak\n";
echo "  bir .html dosyasi iner (Downloads). Acinca PDF gibi gorunur, disari atma YOK.\n";
