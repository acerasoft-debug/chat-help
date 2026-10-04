<?php
/**
 * ChatHelp — apply-stab-calm (CH_STAB_CALM) — kart "gidip-gelip-gitme" dongusu KOKTEN
 *  TESHIS (dump-cardlock kaniti): ch-ui-stability-js, .wcat-grid VE #wlc'yi
 *  MutationObserver ile izliyor; ilk yerlesimden SONRA her dogrudan-cocuk
 *  eklemede resettle() -> izgara 'ch-settling' ile 2 sn gizleniyor (10 sn'de
 *  3 keze kadar). Yeni ana sayfa kartlari (Doc-Dev CTA, Plan CTA, hub kesfet,
 *  TR-vize karti) yuklemeden sonra kendi zamanlayicilariyla #wlc'ye eklendigi
 *  icin her ekleme = 2 sn'lik bir gizlen/gorun dongusu. Kullanicinin gordugu
 *  "kartlar gidiyor, geliyor, yine gidiyor" tam olarak bu.
 *  DUZELTME: yerlesim SONRASI childList tetiklemeleri artik resettle YAPMAZ
 *  (icerik eklemesi normal buyumedir). Ilk yerlesme kapisi ve ULKE DEGISIMI
 *  resettle'i (setCountry/applyCC kancalari + CC yoklayicisi) AYNEN kalir.
 * KULLANIM: pull2.php?key=...&files=apply-stab-calm.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-stab-calm BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_STAB_CALM')!==false) exit("Zaten ekli (CH_STAB_CALM).\n");
if (strpos($src,'CH_STAB_FINAL4')===false) exit("HATA: CH_STAB_FINAL4 katmani yok — hedef degismis.\n");

/* dump-cardlock ciktisindan BIREBIR alinan canli baytlar — arm() ve boot()
   icindeki iki ozdes gozlemci geri-cagrisi */
$old = 'else { var R=(window.__chRs=window.__chRs||{n:0,t:0}); var _n=Date.now(); if(_n-R.t>10000){ R.t=_n; R.n=0; } if(++R.n<=3) resettle(); }';
$new = 'else { /*CH_STAB_CALM — icerik EKLEMESI normal buyume, yeniden-yerlesme yok; ulke degisimi resettle\'i ayrica calisir*/ }';
$cnt = substr_count($src,$old);
if ($cnt!==2) exit("HATA: gozlemci geri-cagri capasi $cnt kez (beklenen 2) — index DEGISTIRILMEDI.\n");
$src = str_replace($old,$new,$src);
echo "  ✓ yerlesim-sonrasi mutasyon->resettle kapatildi (2 gozlemci)\n";
echo "    (ilk yerlesme + ulke-degisimi yeniden-yerlesmesi aynen duruyor)\n";

$tmp = tempnam(sys_get_temp_dir(),'sc').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-stabcalm-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_STAB_CALM uygulandi. Kartlar artik CTA/kart eklemelerinde 2 sn'ligine\n";
echo "   kaybolmaz; yalniz ilk acilista bir kez yerlesir ve ulke degisince tazelenir.\n";
