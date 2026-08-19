<?php
/**
 * ChatHelp — apply-einzelfax (CH_EINZELFAX) — Einzeldokument (odenmis tek-belge) icin
 *   fax gonderimini GERCEKTEN ucretsiz yapar + odeme sayfasinda kisaca gosterir.
 *
 *  Kanit (dump-ezfax): gonderim bolgesinde Stripe/checkout YOK — fax dogrudan
 *  postversand.php'ye POST edilir, fiyat sadece gosterim. Tek gercek engel: Senden kapisi.
 *
 *  [1] GENDOC : odenmis-kredi dali (genDoc: !loggedPlan()&&credits()>0) -> paidDoc isareti
 *  [2] GATE   : Senden kapisinda paidDoc ise paywall'a ATMA -> fax modali acilir (ucretsiz)
 *  [3] PAYWALL: Einzeldokument kartina '📠 inkl. Gratis-Fax-Versand' notu
 *
 *  Idempotent + hepsi-ya-hic + php -l lint + .bak + opcache + dogrulama.
 * KULLANIM: /chat/pull2.php?key=...&files=apply-einzelfax.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-einzelfax BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_EINZELFAX')!==false) exit("Zaten uygulandi (CH_EINZELFAX). Einzeldokument fax-free aktif.\n");
$ok=0;

/* ── [1] GENDOC: odenmis-kredi dalinda paidDoc isareti ── */
$o=<<<'O_GEN'
          useCredit();
          var hadTk=localStorage.getItem('ch_token'); var oPlan=window.P?P.plan:undefined;
O_GEN;
$n=<<<'N_GEN'
          useCredit();
          try{ window.__paidDoc=true; sessionStorage.setItem('ch_paiddoc','1'); }catch(_pdf){}/*CH_EINZELFAX — odenmis tek-belge: fax ucretsiz*/
          var hadTk=localStorage.getItem('ch_token'); var oPlan=window.P?P.plan:undefined;
N_GEN;
$c=substr_count($src,$o); if($c===1){ $src=str_replace($o,$n,$src); echo "  ✓ [GENDOC] tamam\n"; $ok++; } else echo "  ✗ [GENDOC] anchor ($c, 1 beklenir) — atlandi\n";

/* ── [2] GATE: Senden kapisi — paidDoc ise paywall'a atma ── */
$o="const _up4=P.plan||_lsU4.plan||'free';if(!_tk4||_up4==='free'){showPaywall(dk||CD,{});return;}";
$n="const _up4=P.plan||_lsU4.plan||'free';var _pd4=false;try{_pd4=(window.__paidDoc===true)||sessionStorage.getItem('ch_paiddoc')==='1';}catch(_e4){}if((!_tk4||_up4==='free')&&!_pd4){showPaywall(dk||CD,{});return;}/*CH_EINZELFAX*/";
$c=substr_count($src,$o); if($c===1){ $src=str_replace($o,$n,$src); echo "  ✓ [GATE] tamam\n"; $ok++; } else echo "  ✗ [GATE] anchor ($c, 1 beklenir) — atlandi\n";

/* ── [3] PAYWALL: Einzeldokument kartina ucretsiz-fax notu ── */
$o='<div style="font-size:12px;color:#808080;margin-top:2px">Nur dieses Dokument · Einmalig</div>';
$n='<div style="font-size:12px;color:#808080;margin-top:2px">Nur dieses Dokument · Einmalig</div><div style="font-size:11px;color:#42df94;margin-top:3px;font-weight:700">📠 inkl. Gratis-Fax-Versand</div>';
$c=substr_count($src,$o); if($c===1){ $src=str_replace($o,$n,$src); echo "  ✓ [PAYWALL] tamam\n"; $ok++; } else echo "  ✗ [PAYWALL] anchor ($c, 1 beklenir) — atlandi\n";

if($ok<3){ echo "\n⚠ ".(3-$ok)." parca uygulanamadi — DEGISTIRILMEDI (butunluk icin).\n"; exit; }

/* ── lint ── */
$tmp=tempnam(sys_get_temp_dir(),'ef').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "\nLINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }

/* ── yaz + dogrula ── */
@file_put_contents($file.'.bak-einzelfax-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_EINZELFAX')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_EINZELFAX diskte ($ok/3, ".strlen($chk)." B)\n";
echo "  · Odenmis Einzeldokument: Senden -> Fax dogrudan gonderilir (ucretsiz).\n";
echo "  · Ucretsiz-IP belgesi (kredisiz) hala paywall'a gider — degismedi.\n";
echo "  · Odeme sayfasinda 'inkl. Gratis-Fax-Versand' notu gorunur.\n";
