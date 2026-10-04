<?php
/**
 * ChatHelp — apply-sendenfix — Senden butonu duzeltme + premium gorunum (4 edit):
 *  KOK NEDEN: 'Senden' butonu teknik olarak PDF butonuna tikliyordu (pdfBtn.click());
 *  gonderim modali PDF butonunun akisindan aciliyordu. PDF butonu 'dogrudan PDF'
 *  yapilinca Senden de modal acmadan PDF indirmeye basladi.
 *  [1] CH_FORCEMODAL_BTN : Senden tiklaninca __chForceModal bayragi kurulur.
 *  [2] CH_PDFDIRECT2     : PDF-direkt kestirmesi bayraga saygi duyar — Senden'den
 *      gelindiyse bayrak tuketilir ve GONDERIM MODALI acilir (eski davranis).
 *  [3] CH_CHATPDF2       : chat/vize PDF kestirmeleri icin ayni bayrak destegi.
 *  [4] CH_SENDEN_BIG     : Senden butonu daha BUYUK + FARKLI renk (zumrut yesili
 *      premium gradyan, parlama golgesi, PDF butonundan genis) — kullanici istegi.
 * KULLANIM: pull2.php?key=...&files=apply-sendenfix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-sendenfix BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");

$ok=0;

/* ── [1] CH_FORCEMODAL_BTN ── */
if(strpos($src,'CH_FORCEMODAL_BTN')===false){
  $o1="(function(pdfBtn){ s.addEventListener('click',function(ev){ ev.preventDefault(); ev.stopPropagation(); try{ pdfBtn.click(); }catch(e){} }); })(b);";
  $n1="(function(pdfBtn){ s.addEventListener('click',function(ev){ ev.preventDefault(); ev.stopPropagation(); try{ window.__chForceModal=true;/*CH_FORCEMODAL_BTN*/ setTimeout(function(){ try{ window.__chForceModal=false; }catch(e9){} },1500); pdfBtn.click(); }catch(e){} }); })(b);";
  $c=substr_count($src,$o1);
  if($c===1){ $src=str_replace($o1,$n1,$src); echo "  ✓ [1] CH_FORCEMODAL_BTN — Senden artik gonderim modalini zorlar\n"; $ok++; }
  else echo "  ✗ [1] anchor ($c, 1 beklenir) — atlandi\n";
} else { echo "  = [1] zaten ekli\n"; $ok++; }

/* ── [2] CH_PDFDIRECT2 ── */
if(strpos($src,'CH_PDFDIRECT2')===false){
  $o2="if(_tk3&&_up3!=='free'){window.__pdfConfirmed=true;window.__addr3OK=true;window.__addrOK=true;setTimeout(function(){try{window.__addr3OK=false;window.__addrOK=false;}catch(e){}},2500);makePDF(doc,d.n,CC);return;}/*CH_PDFDIRECT*/";
  $n2="if(_tk3&&_up3!=='free'){if(!window.__chForceModal){window.__pdfConfirmed=true;window.__addr3OK=true;window.__addrOK=true;setTimeout(function(){try{window.__addr3OK=false;window.__addrOK=false;}catch(e){}},2500);}window.__chForceModal=false;makePDF(doc,d.n,CC);return;}/*CH_PDFDIRECT*//*CH_PDFDIRECT2*/";
  $c=substr_count($src,$o2);
  if($c===1){ $src=str_replace($o2,$n2,$src); echo "  ✓ [2] CH_PDFDIRECT2 — PDF-direkt kestirmesi Senden bayragina saygili\n"; $ok++; }
  else echo "  ✗ [2] anchor ($c, 1 beklenir) — atlandi\n";
} else { echo "  = [2] zaten ekli\n"; $ok++; }

/* ── [3] CH_CHATPDF2 ── */
if(strpos($src,'CH_CHATPDF2')===false){
  $o3="    try{ if(window.chPrintDoc){ /*CH_CHATPDF_DIRECT*/window.__pdfConfirmed=true;window.__addr3OK=true;window.__addrOK=true;setTimeout(function(){try{window.__addr3OK=false;window.__addrOK=false;}catch(e0){}},2500); window.chPrintDoc(html); return; } }catch(e){}";
  $n3="    try{ if(window.chPrintDoc){ /*CH_CHATPDF_DIRECT*//*CH_CHATPDF2*/if(!window.__chForceModal){window.__pdfConfirmed=true;window.__addr3OK=true;window.__addrOK=true;setTimeout(function(){try{window.__addr3OK=false;window.__addrOK=false;}catch(e0){}},2500);} window.__chForceModal=false; window.chPrintDoc(html); return; } }catch(e){}";
  $c=substr_count($src,$o3);
  if($c>=1 && $c<=3){ $src=str_replace($o3,$n3,$src); echo "  ✓ [3] CH_CHATPDF2 — chat/vize PDF kestirmeleri de bayraga saygili ($c)\n"; $ok++; }
  else echo "  ✗ [3] anchor ($c, 1-3 beklenir) — atlandi\n";
} else { echo "  = [3] zaten ekli\n"; $ok++; }

/* ── [4] CH_SENDEN_BIG ── */
if(strpos($src,'CH_SENDEN_BIG')===false){
  $o4=".ch-senden-btn{display:flex;align-items:center;justify-content:center;gap:7px;background:linear-gradient(135deg,#d4a84a,#ecc060);color:#141018;border:none;border-radius:13px;padding:13px 12px;font-size:14px;font-weight:800;cursor:pointer;box-shadow:0 6px 18px rgba(212,168,74,.24);font-family:inherit;transition:filter .15s}";
  $n4=".ch-senden-btn{/*CH_SENDEN_BIG*/display:flex;align-items:center;justify-content:center;gap:8px;flex:1.45 1 0!important;background:linear-gradient(135deg,#37d17d,#1fa25c);color:#fff;border:none;border-radius:14px;padding:16px 14px;font-size:15.5px;font-weight:800;letter-spacing:.2px;cursor:pointer;box-shadow:0 8px 24px rgba(47,191,113,.35),0 0 0 1px rgba(255,255,255,.08) inset;font-family:inherit;transition:filter .15s,transform .12s}\n.ch-senden-btn:active{transform:scale(.97)}";
  $c=substr_count($src,$o4);
  if($c===1){ $src=str_replace($o4,$n4,$src); echo "  ✓ [4] CH_SENDEN_BIG — Senden: buyuk, zumrut-premium, PDF'den genis\n"; $ok++; }
  else echo "  ✗ [4] anchor ($c, 1 beklenir) — atlandi\n";
} else { echo "  = [4] zaten ekli\n"; $ok++; }

if($ok===0){ echo "\nHICBIR bolum uygulanamadi — HICBIR sey degistirilmedi.\n"; exit; }

$tmp=tempnam(sys_get_temp_dir(),'sf1').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-sendenfix-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
echo "\n  Sonuc: $ok/4 bolum tamam (".strlen($chk)." B)\n";
echo "  Test-1: Belge -> 'Senden' (yesil, buyuk) -> GONDERIM MODALI acilir (Fax ustte).\n";
echo "  Test-2: 'Als PDF speichern' -> modal ACILMADAN dogrudan PDF (degismedi).\n";
