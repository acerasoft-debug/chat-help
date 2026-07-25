<?php
/**
 * ChatHelp — apply-appprint-off — BUGUNKU CH_APPPRINT degisikligini GERI ALIR.
 *  Neden: 'Kostenlos selbst ausdrucken' dun calisiyordu, bugun CH_APPPRINT (dl.php
 *  fetch -> ChelpNative.chSavePdf koprusu) eklendikten sonra App'te "hicbir tepki yok".
 *  Bu betik yalniz CH_APPPRINT (E1) + CH_APPPRINT2 (E2) parcalarini SILER; chPrintDoc
 *  dunku CALISIR form-POST (CH_PDFFORM) haline doner. Baska HICBIR sey degismez.
 *  Idempotent + lint + .bak + dogrulama.
 * KULLANIM: /chat/pull2.php?key=...&files=apply-appprint-off.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-appprint-off BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_APPPRINT')===false) exit("Zaten yok (CH_APPPRINT bulunmadi) — DEGISIKLIK YOK.\n");

/* ── E1: CH_APPPRINT koprusu (if(_isWv2){ hemen sonrasina eklenmisti) ── */
$e1snip = " /*CH_APPPRINT*/ try{ if(window.ChelpNative && typeof window.ChelpNative.chSavePdf==='function'){ var _afd=new FormData(); _afd.append('html',html); _afd.append('name','ChatHelp-Dokument'); _afd.append('fmt','pdf'); fetch('dl.php',{method:'POST',body:_afd}).then(function(r){return r.blob();}).then(function(_b){ var _fr=new FileReader(); _fr.onloadend=function(){ try{ var _s=String(_fr.result||''); var _b64=_s.indexOf(',')>=0?_s.slice(_s.indexOf(',')+1):_s; window.ChelpNative.chSavePdf(_b64,'ChatHelp-Dokument.pdf','application/pdf'); }catch(_e5){} }; _fr.readAsDataURL(_b); }).catch(function(){}); return; } }catch(_e6){}";

/* ── E2: CH_APPPRINT2 (printSigned window.open dalina eklenen chPrintDoc-once) ── */
$e2snip = "try{ if(typeof window.chPrintDoc==='function'){ /*CH_APPPRINT2*/ window.chPrintDoc(html); return true; } }catch(e){} ";

$c1=substr_count($src,$e1snip);
$c2=substr_count($src,$e2snip);
echo "  bulunan: E1(kopru)=$c1  E2(printSigned)=$c2\n";

$new=$src;
if($c1>=1) $new=str_replace($e1snip,'',$new);
if($c2>=1) $new=str_replace($e2snip,'',$new);

$remain=substr_count($new,'CH_APPPRINT');
if($c1<1 && $c2<1){
  echo "  ! Tam eslesme bulunamadi (kod baska yamayla degismis olabilir). Guvenli: DEGISIKLIK YOK.\n";
  echo "  (Ic içe tirnak/boşluk farki olabilir — dump-ausdruck2 ciktisini gonder, birebir hedefleyeyim.)\n";
  exit;
}

$tmp=tempnam(sys_get_temp_dir(),'ao').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }

@file_put_contents($file.'.bak-appprintoff-'.date('Ymd-His'),$src);
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();

$chk=(string)@file_get_contents($file);
echo "  ✓ E1 silindi: $c1 yer, E2 silindi: $c2 yer  (".strlen($src)." -> ".strlen($chk)." bayt)\n";
echo "  kalan CH_APPPRINT referansi: ".substr_count($chk,'CH_APPPRINT')."\n";
echo "\n✓ chPrintDoc dunku CALISIR haline (form-POST / iframe.print) dondu.\n";
echo "  App'i TAM kapat-ac, sonra 'Kostenlos selbst ausdrucken' dene.\n";
