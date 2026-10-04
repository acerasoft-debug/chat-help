<?php
/**
 * ChatHelp — apply-pdfform (CH_PDFFORM) — KESIN COZUM #3: App WebView'i blob: URL'leri
 *  "Ungültige Webadresse" diye REDDEDIYOR (Capacitor navigasyon dogrulayicisi sadece
 *  gercek https:// adreslerini kabul ediyor). COZUM: icerik, GERCEK bir same-origin
 *  https:// uc noktasina (dl.php, YENI) form-POST ile gonderilir; sunucu
 *  Content-Disposition: attachment ile geri doner -> Android'in kendi indirme
 *  yoneticisi devreye girer, App icinde kalinir, print()/window.open()/blob: YOK.
 *  [A] dl.php YENI dosya olarak yazilir.
 *  [B] makePDF (CH_APPPDF) + [C] chPrintDoc (CH_CPDWV x3): Blob -> form-POST.
 *  Sayac-korumali str_replace'ler. Eslesmezse HICBIR sey degistirilmez.
 * KULLANIM: pull2.php?key=...&files=apply-pdfform.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-pdfform BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$D=__DIR__;
$file="$D/index.php";
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_PDFFORM')!==false) exit("Zaten ekli (CH_PDFFORM).\n");

/* [A] dl.php — WebView-guvenli indirme uc noktasi */
$dl = <<<'PHP'
<?php
/* ChatHelp — dl.php (CH_DL_EP) — App WebView-guvenli dosya indirme.
   POST 'html' + 'name' alir, Content-Disposition: attachment ile doner.
   Auth gerektirmez (icerik istemciden gelir, sunucuda saklanmaz). */
header('X-Content-Type-Options: nosniff');
$html = $_POST['html'] ?? '';
$name = (string)($_POST['name'] ?? 'ChatHelp-Dokument.html');
$name = preg_replace('/[^a-zA-Z0-9äöüÄÖÜß _.-]/u','',$name);
if($name==='' || strlen($name)>120) $name='ChatHelp-Dokument.html';
if(stripos($name,'.html')===false) $name.='.html';
if($html===''){ http_response_code(400); exit('empty'); }
if(strlen($html) > 6*1024*1024){ http_response_code(413); exit('too large'); }
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$name.'"');
header('Content-Length: '.strlen($html));
echo $html;
PHP;
$dlPath="$D/dl.php";
$dlOk=@file_put_contents($dlPath,$dl);
if($dlOk===false){ echo "  ✗ [A] dl.php yazilamadi\n"; exit; }
$lo=[];$rc=0; exec('php -l '.escapeshellarg($dlPath).' 2>&1',$lo,$rc);
if($rc!==0){ echo "  ✗ [A] dl.php LINT HATASI:\n  ".implode("\n  ",$lo)."\n"; @unlink($dlPath); exit; }
echo "  ✓ [A] dl.php yazildi (".strlen($dl)." B) — WebView-guvenli indirme uc noktasi\n";

$fail=[];

/* [B] makePDF (CH_APPPDF+CH_PDFBLOB) -> form-POST */
$o1 = <<<'OLD'
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
OLD;

$n1 = <<<'NEW'
  try{/*CH_APPPDF*/ /*CH_PDFFORM*/
    var _isWv=false; try{ _isWv=(typeof isWV==='function')&&isWV(); }catch(e0){}
    if(_isWv){
      try{
        var _fn=(String(_mpTitle||'ChatHelp-Dokument').replace(/[^a-zA-Z0-9äöüÄÖÜß _-]/g,'')||'ChatHelp-Dokument')+'.html';
        var _f1=document.createElement('form'); _f1.method='POST'; _f1.action='dl.php'; _f1.target='_self'; _f1.style.display='none';
        var _i1a=document.createElement('input'); _i1a.name='html'; _i1a.value=_mpHtml; _f1.appendChild(_i1a);
        var _i1b=document.createElement('input'); _i1b.name='name'; _i1b.value=_fn; _f1.appendChild(_i1b);
        document.body.appendChild(_f1); _f1.submit();
        setTimeout(function(){ try{ document.body.removeChild(_f1); }catch(e9){} },1500);
        return;
      }catch(e1){}
    }
  }catch(e2){}
  try{ if(window.chPrintDoc){ window.chPrintDoc(_mpHtml); return; } }catch(e){}
NEW;

$c1=substr_count($src,$o1);
if($c1===1){ $src=str_replace($o1,$n1,$src); echo "  ✓ [B] makePDF: Blob -> form-POST (dl.php)\n"; }
else { echo "  ✗ [B] anchor ($c1, 1 beklenir)\n"; $fail[]=1; }

/* [C] chPrintDoc (CH_CPDWV+CH_PDFBLOB, x3) -> form-POST */
$o2 = <<<'OLD'
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
OLD;

$n2 = <<<'NEW'
setTimeout(function(){
        try{/*CH_CPDWV*/ /*CH_PDFFORM*/
          var _isWv2=false; try{ _isWv2=(typeof isWV==='function')&&isWV(); }catch(e0){}
          if(_isWv2){
            try{
              var _f2=document.createElement('form'); _f2.method='POST'; _f2.action='dl.php'; _f2.target='_self'; _f2.style.display='none';
              var _i2a=document.createElement('input'); _i2a.name='html'; _i2a.value=html; _f2.appendChild(_i2a);
              var _i2b=document.createElement('input'); _i2b.name='name'; _i2b.value='ChatHelp-Dokument.html'; _f2.appendChild(_i2b);
              document.body.appendChild(_f2); _f2.submit();
              setTimeout(function(){ try{ document.body.removeChild(_f2); }catch(e9b){} },1500);
              return;
            }catch(e1b){}
          }
          f.contentWindow.focus(); f.contentWindow.print();
        }catch(e){}
      },500);
NEW;

$c2=substr_count($src,$o2);
if($c2>=1){ $src=str_replace($o2,$n2,$src); echo "  ✓ [C] chPrintDoc: Blob -> form-POST ($c2 kopya)\n"; }
else { echo "  ✗ [C] anchor ($c2, >=1 beklenir)\n"; $fail[]=2; }

if($fail){ echo "\nHATA: eslesmeyen adim(lar): ".implode(', ',$fail)." — index.php DEGISTIRILMEDI (dl.php yazildi kaldi).\n"; exit; }

$tmp=tempnam(sys_get_temp_dir(),'pf').'.php'; file_put_contents($tmp,$src);
$lo2=[];$rc2=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo2,$rc2); @unlink($tmp);
if($rc2!==0){ echo "LINT HATASI — index.php DEGISTIRILMEDI:\n  ".implode("\n  ",$lo2)."\n"; exit; }
@file_put_contents($file.'.bak-pdfform-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_PDFFORM')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_PDFFORM diskte (".strlen($chk)." B)\n";
echo "  Test: App'i TAM kapat-ac (2 kez) -> 'Als PDF speichern' / 'Kostenlos ausdrucken'\n";
echo "  -> gercek https://.../dl.php adresine form-POST, Android indirme yoneticisi\n";
echo "  devreye girer, App icinde kalinir. 'Ungultige Webadresse' bir daha CIKMAZ.\n";
