<?php
/**
 * ChatHelp — apply-appprint (CH_APPPRINT) — App WebView'de 'ausdrucken' + PDF cikisini duzeltir.
 *  Kok neden: App bir Capacitor WebView; dosya indirme icin native dinleyici sart. Yeni app (v5/1.4)
 *  window.ChelpNative.chSavePdf(base64,name,mime) kopru fonksiyonu sunar (patch-android.js).
 *  Bu yama, WEB tarafini o kopruyu KULLANACAK sekilde ayarlar:
 *   [E1] chPrintDoc App-dali (if(_isWv2){...): dl.php'ye form-POST yerine -> fetch dl.php -> PDF blob
 *        -> base64 -> ChelpNative.chSavePdf. Kopru yoksa (eski app/PWA) mevcut dl.php-POST yolu kalir.
 *   [E2] printSigned (imzali belge, window.open yolu): once window.chPrintDoc(html) denenir (App+web ortak).
 *  Idempotent + capa-say + php -l + .bak + dogrulama. E1 zorunlu; E2 bulunursa uygulanir.
 * KULLANIM: /chat/pull2.php?key=...&files=apply-appprint.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-appprint BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_APPPRINT')!==false) exit("Zaten uygulandi (CH_APPPRINT).\n");

/* ── E1: chPrintDoc App-dali ── */
$e1o = "if(_isWv2){";
$e1snip = " /*CH_APPPRINT*/ try{ if(window.ChelpNative && typeof window.ChelpNative.chSavePdf==='function'){ var _afd=new FormData(); _afd.append('html',html); _afd.append('name','ChatHelp-Dokument'); _afd.append('fmt','pdf'); fetch('dl.php',{method:'POST',body:_afd}).then(function(r){return r.blob();}).then(function(_b){ var _fr=new FileReader(); _fr.onloadend=function(){ try{ var _s=String(_fr.result||''); var _b64=_s.indexOf(',')>=0?_s.slice(_s.indexOf(',')+1):_s; window.ChelpNative.chSavePdf(_b64,'ChatHelp-Dokument.pdf','application/pdf'); }catch(_e5){} }; _fr.readAsDataURL(_b); }).catch(function(){}); return; } }catch(_e6){}";
$e1n = $e1o.$e1snip;

/* ── E2: printSigned window.open -> once chPrintDoc ── */
$e2o = "try{ if(typeof window.chVsfExportPDF==='function'){ window.chVsfExportPDF(html); return true; } }catch(e){}";
$e2n = "try{ if(typeof window.chPrintDoc==='function'){ /*CH_APPPRINT2*/ window.chPrintDoc(html); return true; } }catch(e){} ".$e2o;

/* ── say ── */
$c1=substr_count($src,$e1o);
$c2=substr_count($src,$e2o);
echo "  capa sayimlari: E1(if(_isWv2)){=$c1  E2(chVsfExportPDF)=$c2\n";
if($c1<1){ echo "  ✗ E1 capasi bulunamadi (chPrintDoc App-dali) — HICBIR SEY DEGISTIRILMEDI.\n"; exit; }

/* ── uygula ── */
$new=str_replace($e1o,$e1n,$src);      // replace_all: her webview dalina kopru
$e1done=substr_count($new,'/*CH_APPPRINT*/');
$e2done=0;
if($c2>=1){
  $new=str_replace($e2o,$e2n,$new);    // replace_all
  $e2done=substr_count($new,'/*CH_APPPRINT2*/');
  echo "  ✓ E1 ".$e1done." yere kopru; E2 ".$e2done." yere chPrintDoc-once\n";
} else {
  echo "  ✓ E1 ".$e1done." yere kopru; E2 capasi yok (atlandi, sorun degil)\n";
}
if($e1done<1){ echo "  ✗ E1 uygulanmis gorunmuyor — DEGISTIRILMEDI.\n"; exit; }

/* ── lint ── */
$tmp=tempnam(sys_get_temp_dir(),'ap').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "  ✗ LINT HATASI — HICBIR SEY YAZILMADI:\n  ".implode("\n  ",$lo)."\n"; exit; }
echo "  ✓ lint temiz\n";

/* ── yaz ── */
@file_put_contents($file.'.bak-appprint-'.date('Ymd-His'),$src);
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("  ✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_APPPRINT')===false) exit("  ✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_APPPRINT canli (".strlen($chk)." B)\n";
echo "  · Web/PWA: davranis korunur (kopru yoksa dl.php-POST yolu aynen).\n";
echo "  · App v5+ : 'ausdrucken'/PDF -> PDF fetch -> ChelpNative.chSavePdf -> Indirilenler'e kaydeder+acar.\n";
echo "  NOT: App tarafi ancak YENI AAB (v5/1.4) yuklendikten sonra bu kopruyu tanir.\n";
