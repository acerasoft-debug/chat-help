<?php
/**
 * ChatHelp — apply-uni2 (CH_CHATSEND) — chat belge kartina yesil 'Senden' —
 *  canli (CH_CHATDOC_FIX'li) surume birebir anchor. Plan kapisina saygili:
 *  paketli kullanici -> gonderim modali; paketsiz -> mevcut odeme akisi.
 * KULLANIM: pull2.php?key=...&files=apply-uni2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-uni2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");

$ok=0;

/* ── [1] HTML: Senden butonu ── */
if(strpos($src,'ch-doccard-send')===false){
  $o1=<<<'ANC'
        +'<button type="button" class="ch-doccard-btn">'+_btnLbl+'</button>'
ANC;
  $n1=<<<'ANC'
        +'<button type="button" class="ch-doccard-btn">'+_btnLbl+'</button>'
        +'<button type="button" class="ch-doccard-send" style="display:block;width:100%;margin-top:8px;background:linear-gradient(135deg,#3fe08a,#1fa25c);color:#fff;font-weight:800;border:0;border-radius:11px;padding:13px;font-size:14px;letter-spacing:.3px;box-shadow:0 6px 20px rgba(47,209,125,.4);cursor:pointer;font-family:inherit">\u{1F4E4} Senden</button>'
ANC;
  $c=substr_count($src,$o1);
  if($c===1){ $src=str_replace($o1,$n1,$src); echo "  ✓ [1] chat kartina yesil Senden eklendi\n"; $ok++; }
  else echo "  ✗ [1] anchor ($c, 1 beklenir) — atlandi\n";
} else { echo "  = [1] zaten ekli\n"; $ok++; }

/* ── [2] handler ── */
if(strpos($src,'CH_CHATSEND')===false){
  $o2=<<<'ANC'
      var b=d.querySelector(".ch-doccard-btn");
      if(b) b.addEventListener("click",function(){ /*CH_CHATDOC_FIX*/
        if(_hasPlan){ printDoc(docText); }
        else { try{ window.stripeCheckout(null,"devdoc",_tier[0]); }catch(e){} }
      });
ANC;
  $n2=<<<'ANC'
      var b=d.querySelector(".ch-doccard-btn");
      if(b) b.addEventListener("click",function(){ /*CH_CHATDOC_FIX*/
        if(_hasPlan){ printDoc(docText); }
        else { try{ window.stripeCheckout(null,"devdoc",_tier[0]); }catch(e){} }
      });
      /*CH_CHATSEND — yesil Senden: paketli -> gonderim modali, paketsiz -> odeme*/
      var sb2=d.querySelector(".ch-doccard-send");
      if(sb2) sb2.addEventListener("click",function(){
        if(_hasPlan){ try{ window.__chForceModal=true; setTimeout(function(){ try{ window.__chForceModal=false; }catch(e9){} },1500); }catch(e8){} printDoc(docText); }
        else { try{ window.stripeCheckout(null,"devdoc",_tier[0]); }catch(e){} }
      });
ANC;
  $c=substr_count($src,$o2);
  if($c===1){ $src=str_replace($o2,$n2,$src); echo "  ✓ [2] Senden handler bagladi (modal/odeme kapisi)\n"; $ok++; }
  else echo "  ✗ [2] anchor ($c, 1 beklenir) — atlandi\n";
} else { echo "  = [2] zaten ekli\n"; $ok++; }

if($ok===0){ echo "\nHICBIR bolum uygulanamadi — HICBIR sey degistirilmedi.\n"; exit; }

$tmp=tempnam(sys_get_temp_dir(),'u2').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-uni2-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
echo "\n  Sonuc: $ok/2 bolum tamam (".strlen($chk)." B)\n";
echo "  Test (app2.php): chat'e konu yaz -> belge karti -> altin 'PDF' direkt indirir,\n";
echo "  yesil 'Senden' gonderim modalini acar. UC AKIS ARTIK AYNI STANDART.\n";
