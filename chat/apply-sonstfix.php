<?php
/**
 * ChatHelp — apply-sonstfix (CH_SONSTFIX) — KESIN: sablonlarda 'Sonstiges' secilip
 *  serbest-metin kutusuna yazilinca BIR HARF sonra kutu kapaniyordu.
 *  KOK NEDEN: input dinleyicisi HER TUS VURUSUNDA select'e 'change' olayi
 *  gonderiyordu (sel.dispatchEvent(new Event('change'))). Bu, soru formunu
 *  yeniden cizdiriyor -> input DOM'dan silinip yeniden olusuyor -> ODAK gidiyor.
 *  COZUM: her karakterde 'change' YOK; deger sessizce saklanir, 'change' SADECE
 *  kutudan cikinca (blur) bir kez gonderilir. Boylece tam metin yazilabilir.
 *  1 sayac-korumali str_replace. Eslesmezse HICBIR sey degistirilmez.
 * KULLANIM: pull2.php?key=...&files=apply-sonstfix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-sonstfix BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_SONSTFIX')!==false) exit("Zaten ekli (CH_SONSTFIX).\n");

$old = "      inp.addEventListener('input',function(){\n"
     . "        var v=inp.value; var co=sel.querySelector('option[data-custom]');\n"
     . "        if(!co){ co=document.createElement('option'); co.setAttribute('data-custom','1'); sel.appendChild(co); }\n"
     . "        co.value=v; co.textContent=v||'Sonstiges …'; sel.value=v;\n"
     . "        try{ sel.dispatchEvent(new Event('change',{bubbles:true})); }catch(e){}\n"
     . "      });";

$new = "      function _sonstSync(fire){/*CH_SONSTFIX*/\n"
     . "        var v=inp.value; var co=sel.querySelector('option[data-custom]');\n"
     . "        if(!co){ co=document.createElement('option'); co.setAttribute('data-custom','1'); sel.appendChild(co); }\n"
     . "        co.value=v; co.textContent=v||'Sonstiges …'; sel.value=v;\n"
     . "        if(fire){ try{ sel.dispatchEvent(new Event('change',{bubbles:true})); }catch(e){} }\n"
     . "      }\n"
     . "      /* yazarken SESSIZ (change YOK -> form yeniden cizilmez -> odak kalir) */\n"
     . "      inp.addEventListener('input',function(){ _sonstSync(false); });\n"
     . "      /* deger yalnizca kutudan cikinca uygulanir */\n"
     . "      inp.addEventListener('blur',function(){ _sonstSync(true); });\n"
     . "      inp.addEventListener('change',function(){ _sonstSync(true); });";

$c=substr_count($src,$old);
if($c!==1){ echo "  ✗ anchor ($c, 1 beklenir) — HICBIR sey degistirilmedi.\n"; exit; }
$src=str_replace($old,$new,$src);
echo "  ✓ 'Sonstiges' serbest-metin: yazarken change gonderilmiyor (odak korunur)\n";

$tmp=tempnam(sys_get_temp_dir(),'sf').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-sonstfix-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_SONSTFIX')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_SONSTFIX diskte (".strlen($chk)." B)\n";
echo "  Test: bir sablon -> acilir menude 'Sonstiges' sec -> kutuya UZUN metin yaz ->\n";
echo "  kutu KAPANMAMALI, tam metin girilebilmeli.\n";
