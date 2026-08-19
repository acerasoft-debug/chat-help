<?php
/**
 * ChatHelp — apply-uni1 — UC AKIS AYNI PREMIUM STANDART (chat + Fall'a Senden):
 *  Sablon karti: PDF direkt + yesil Senden->modal (tamam). Eksik olanlar:
 *  [1] CH_CHATSEND: chat belge kartina ([[DOC]]) yesil 'Senden' butonu —
 *      basinca gonderim modali (Fax/Einschreiben/Brief) acilir; PDF butonu
 *      direkt indirmeye devam eder.
 *  [2] CH_FALLSEND: Fall belgesine yesil 'Senden' butonu — ayni sekilde
 *      gonderim modalini acar (Kopieren/PDF yaninda).
 * KULLANIM: pull2.php?key=...&files=apply-uni1.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-uni1 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");

$ok=0;

/* ── [1a] chat karti HTML: Senden butonu ── */
if(strpos($src,'ch-doccard-send')===false){
  $o1=<<<'ANC'
+'<button type="button" class="ch-doccard-btn">'+esc(tr(BTN))+'</button>'
ANC;
  $n1=<<<'ANC'
+'<button type="button" class="ch-doccard-btn">'+esc(tr(BTN))+'</button>'
        +'<button type="button" class="ch-doccard-send" style="display:block;width:100%;margin-top:8px;background:linear-gradient(135deg,#3fe08a,#1fa25c);color:#fff;font-weight:800;border:0;border-radius:12px;padding:13px;font-size:14.5px;letter-spacing:.3px;box-shadow:0 6px 20px rgba(47,209,125,.4);cursor:pointer;font-family:inherit">\u{1F4E4} Senden</button>'
ANC;
  $c=substr_count($src,$o1);
  if($c===1){ $src=str_replace($o1,$n1,$src); echo "  ✓ [1a] chat kartina yesil Senden eklendi\n"; $ok++; }
  else echo "  ✗ [1a] anchor ($c, 1 beklenir) — atlandi\n";
} else { echo "  = [1a] zaten ekli\n"; $ok++; }

/* ── [1b] chat karti handler ── */
if(strpos($src,'CH_CHATSEND')===false){
  $o2=<<<'ANC'
      var b=d.querySelector(".ch-doccard-btn");
      if(b) b.addEventListener("click",function(){ printDoc(docText); });
ANC;
  $n2=<<<'ANC'
      var b=d.querySelector(".ch-doccard-btn");
      if(b) b.addEventListener("click",function(){ printDoc(docText); });
      /*CH_CHATSEND*/
      var sb2=d.querySelector(".ch-doccard-send");
      if(sb2) sb2.addEventListener("click",function(){ try{ window.__chForceModal=true; setTimeout(function(){ try{ window.__chForceModal=false; }catch(e9){} },1500); }catch(e8){} printDoc(docText); });
ANC;
  $c=substr_count($src,$o2);
  if($c===1){ $src=str_replace($o2,$n2,$src); echo "  ✓ [1b] chat Senden handler bagladi (gonderim modali)\n"; $ok++; }
  else echo "  ✗ [1b] anchor ($c, 1 beklenir) — atlandi\n";
} else { echo "  = [1b] zaten ekli\n"; $ok++; }

/* ── [2a] Fall belgesi HTML: Senden butonu ── */
if(strpos($src,'fallSend2()')===false){
  $o3=<<<'ANC'
+'<button onclick="fallPDF()" style="flex:1;padding:9px;background:linear-gradient(135deg,#d4a84a,#e8b840);border:none;border-radius:10px;color:#1a0e00;font-weight:700;cursor:pointer;font-family:inherit">📄 PDF</button></div></div>';
ANC;
  $n3=<<<'ANC'
+'<button onclick="fallSend2()" style="flex:1.3;padding:10px;background:linear-gradient(135deg,#3fe08a,#1fa25c);border:none;border-radius:10px;color:#fff;font-weight:800;cursor:pointer;font-family:inherit;box-shadow:0 4px 14px rgba(47,209,125,.35)">📤 Senden</button>'
        +'<button onclick="fallPDF()" style="flex:1;padding:9px;background:linear-gradient(135deg,#d4a84a,#e8b840);border:none;border-radius:10px;color:#1a0e00;font-weight:700;cursor:pointer;font-family:inherit">📄 PDF</button></div></div>';
ANC;
  $c=substr_count($src,$o3);
  if($c===1){ $src=str_replace($o3,$n3,$src); echo "  ✓ [2a] Fall belgesine yesil Senden eklendi\n"; $ok++; }
  else echo "  ✗ [2a] anchor ($c, 1 beklenir) — atlandi\n";
} else { echo "  = [2a] zaten ekli\n"; $ok++; }

/* ── [2b] fallSend2 fonksiyonu ── */
if(strpos($src,'CH_FALLSEND')===false){
  $o4="  window.fallPDF=function(){/*CH_FALLPDF direct*/ const f=getCur(); if(f&&f.document&&typeof makePDF==='function'){ window.__pdfConfirmed=true;window.__addr3OK=true;window.__addrOK=true;setTimeout(function(){try{window.__addr3OK=false;window.__addrOK=false;}catch(e0){}},2500); makePDF(f.document,f.title,(window.CC||'DE')); } else alert('PDF nicht verfügbar.'); };";
  $n4=$o4."\n  window.fallSend2=function(){/*CH_FALLSEND*/ const f=getCur(); if(f&&f.document&&typeof makePDF==='function'){ window.__chForceModal=true; setTimeout(function(){try{window.__chForceModal=false;}catch(e0){}},1500); makePDF(f.document,f.title,(window.CC||'DE')); } else alert('Senden nicht verfügbar.'); };";
  $c=substr_count($src,$o4);
  if($c===1){ $src=str_replace($o4,$n4,$src); echo "  ✓ [2b] fallSend2() eklendi (gonderim modali)\n"; $ok++; }
  else echo "  ✗ [2b] anchor ($c, 1 beklenir) — atlandi\n";
} else { echo "  = [2b] zaten ekli\n"; $ok++; }

if($ok===0){ echo "\nHICBIR bolum uygulanamadi — HICBIR sey degistirilmedi.\n"; exit; }

$tmp=tempnam(sys_get_temp_dir(),'u1').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-uni1-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
echo "\n  Sonuc: $ok/4 bolum tamam (".strlen($chk)." B)\n";
echo "  Test (app2.php):\n";
echo "  1) Chat'e yaz -> belge karti -> [PDF erstellen] direkt indirir, [yesil Senden] modal acar\n";
echo "  2) Fall lösen -> belge -> [Kopieren][yesil Senden][PDF] — Senden modal acar\n";
echo "  3) Sablon -> kart -> tek buyuk yesil Senden (dun tamamlandi)\n";
