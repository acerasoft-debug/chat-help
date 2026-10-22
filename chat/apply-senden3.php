<?php
/**
 * ChatHelp — apply-senden3 — Senden GARANTI gorunur + taze giris adresi:
 *  [1] CH_SENDEN3: Yesil buyuk 'Senden' butonu artik sonuc kartinin ICINE dogrudan
 *      insa edilir (showResult aksiyon satiri) — eski metin-eslestirmeli enjeksiyona
 *      bagimlilik yok, CIKMAMA ihtimali kalmaz. Tiklaninca gonderim modalini acar.
 *  [2] CH_APP2: chat/app2.php — YEPYENI adres, Cloudflare onbelleginde YOK ->
 *      her zaman en taze kodu sunar (test + acil erisim icin).
 * KULLANIM: pull2.php?key=...&files=apply-senden3.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-senden3 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$D=__DIR__;
$ok=0;

/* ── [1] CH_SENDEN3 ── */
$file=$D.'/index.php';
$src=@file_get_contents($file);
if($src===false){ echo "  ✗ index.php okunamadi\n"; }
elseif(strpos($src,'CH_SENDEN3')!==false){ echo "  = [1] zaten ekli\n"; $ok++; }
else{
  $o1="ac.appendChild(cp);ac.appendChild(em);ac.appendChild(pd);hdr.appendChild(ac);";
  $n1="ac.appendChild(cp);ac.appendChild(em);ac.appendChild(pd);"
     ."/*CH_SENDEN3 — yesil Senden dogrudan kartta*/"
     ."{const sd=document.createElement('button');sd.className='act';sd.id='ch-sd2';"
     ."sd.style.cssText='background:linear-gradient(135deg,#37d17d,#1fa25c);color:#fff;font-weight:800;border:0;border-radius:12px;padding:11px 20px;font-size:14px;box-shadow:0 6px 18px rgba(47,209,125,.35);cursor:pointer';"
     ."sd.innerHTML='\\u{1F4E4} Senden';"
     ."sd.onclick=()=>{const _tk4=localStorage.getItem('ch_token');const _lsU4=JSON.parse(localStorage.getItem('ch_user')||'{}');const _up4=P.plan||_lsU4.plan||'free';"
     ."if(!_tk4||_up4==='free'){showPaywall(dk||CD,{});return;}"
     ."window.__chForceModal=true;setTimeout(()=>{try{window.__chForceModal=false;}catch(e){}},1500);"
     ."makePDF(doc,d.n,CC);};"
     ."ac.appendChild(sd);}"
     ."hdr.appendChild(ac);";
  $c=substr_count($src,$o1);
  if($c===1){
    $src=str_replace($o1,$n1,$src);
    $tmp=tempnam(sys_get_temp_dir(),'s3').'.php'; file_put_contents($tmp,$src);
    $lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
    if($rc!==0){ echo "  ✗ [1] LINT: ".implode(' ',$lo)."\n"; }
    else{
      @file_put_contents($file.'.bak-senden3-'.date('Ymd-His'),(string)@file_get_contents($file));
      $w=@file_put_contents($file,$src);
      if($w!==false){ echo "  ✓ [1] CH_SENDEN3 — yesil Senden karta dogrudan insa edildi\n"; $ok++; }
      else echo "  ✗ [1] yazma hatasi\n";
    }
  } else echo "  ✗ [1] anchor ($c, 1 beklenir) — atlandi\n";
}

/* ── [2] CH_APP2 ── */
$app2="<?php /* ChatHelp — app2 (CH_APP2) — taze giris: Cloudflare onbellegini atlar */\nrequire __DIR__.'/index.php';\n";
$t2=tempnam(sys_get_temp_dir(),'a2').'.php'; file_put_contents($t2,$app2);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($t2).' 2>&1',$lo,$rc); @unlink($t2);
if($rc!==0){ echo "  ✗ [2] app2 LINT: ".implode(' ',$lo)."\n"; }
else{
  $w=@file_put_contents($D.'/app2.php',$app2);
  if($w!==false){ echo "  ✓ [2] app2.php olusturuldu (taze giris adresi)\n"; $ok++; }
  else echo "  ✗ [2] app2 yazilamadi\n";
}

if(function_exists('opcache_reset')) @opcache_reset();
echo "\n  Sonuc: $ok/2\n";
echo "  ACIL TEST (Cloudflare'i tamamen atlar, HER ZAMAN en taze kod):\n";
echo "  https://chat-help.com/chat/app2.php\n";
echo "  -> giris yap -> dilekce uret -> sonuc kartinin UST satirinda\n";
echo "  Kopieren | E-Mail | PDF | [YESIL Senden] gorunur -> bas -> gonderim ekrani.\n";
