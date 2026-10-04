<?php
/**
 * ChatHelp — STAATLICHE PDF ZORUNLU ALAN DÜZELTMESİ (form-engine.js)
 * -------------------------------------------------------------------
 *  Teşhis (dump-staatliche-pdf çıktısı): staatliche formların PDF mantığı
 *  form-engine.js'te; exportFormPDF plan kontrolü yapıyor ama zorunlu alan
 *  doğrulamasını (validateForm) HİÇ çağırmıyor -> boş zorunlu alanlarla,
 *  hatta bomboş formla bile PDF üretiliyor.
 *
 *  Düzeltme (CH_STAAT_REQFIX):
 *   1) exportFormPDF: PDF'den (ve paywall'dan) ÖNCE validateForm() çağrılır;
 *      eksik alanlar kırmızı işaretlenir + uyarı. Tamamen boş form da engellenir.
 *   2) validateForm: gizli/devre dışı alanlar denetime girmez — görünmeyen bir
 *      alan yüzünden asılsız "Pflichtfeld" hatası çıkmaz.
 *
 * KULLANIM: pull-updates.php?files=apply-staatliche-pdf-fix.php&run=1
 *           (veya tarayıcıda aç) -> opcache-reset gerekmez (JS dosyası). SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-staatliche-pdf-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file=__DIR__."/form-engine.js";
if(!file_exists($file)) exit("form-engine.js yok\n");
$src=file_get_contents($file); $start=$src;
if(strpos($src,"CH_STAAT_REQFIX")!==false) exit("Zaten ekli (CH_STAAT_REQFIX).\n");

/* ---- 1) exportFormPDF başına doğrulama + boş form koruması ---- */
$a1="  // ── PDF Export with plan check ──\n  window.exportFormPDF = async function(title, filename){\n    const plan = getUserPlan();";
$r1="  // ── Formda en az bir alan dolu mu? (boş formdan PDF üretmeyi engeller) ──\n"
   ."  function anyFieldFilled(){\n"
   ."    const sel = '.form-field input, .form-field select, .form-field textarea, .field-group input, .field-group select, .field-group textarea';\n"
   ."    return Array.prototype.some.call(document.querySelectorAll(sel), function(i){\n"
   ."      return i.value && i.value.trim() && i.value !== '— Bundesland wählen —';\n"
   ."    });\n"
   ."  }\n\n"
   ."  // ── PDF Export with plan check ──\n  window.exportFormPDF = async function(title, filename){\n"
   ."    /* CH_STAAT_REQFIX — Pflichtfelder vor PDF prüfen (paywall'dan da önce) */\n"
   ."    if (typeof window.validateForm === 'function' && !window.validateForm()){\n"
   ."      alert('Bitte füllen Sie alle Pflichtfelder aus (rot markiert).');\n"
   ."      return;\n"
   ."    }\n"
   ."    if (!anyFieldFilled()){\n"
   ."      alert('Das Formular ist leer — bitte füllen Sie es zuerst aus.');\n"
   ."      return;\n"
   ."    }\n\n"
   ."    const plan = getUserPlan();";

/* ---- 2) validateForm: gizli/devre dışı alanları atla ---- */
$a2="    document.querySelectorAll('[required]').forEach(inp=>{\n      if(!inp.value.trim()){";
$r2="    document.querySelectorAll('[required]').forEach(inp=>{\n"
   ."      /* CH_STAAT_REQFIX: gizli/devre dışı alanlar doğrulamaya girmez */\n"
   ."      if(inp.disabled || inp.getClientRects().length === 0){ inp.style.borderColor=''; return; }\n"
   ."      if(!inp.value.trim()){";

$ok1=(strpos($src,$a1)!==false);
$ok2=(strpos($src,$a2)!==false);
if(!$ok1||!$ok2){
  echo ($ok1?"":"✗ anchor-1 (exportFormPDF) bulunamadi\n").($ok2?"":"✗ anchor-2 (validateForm) bulunamadi\n");
  exit("Sunucudaki form-engine.js beklenenden farkli — HICBIR SEY DEGISTIRILMEDI.\nDosyanin ilk 60 satirini bana gonder.\n");
}

file_put_contents($file.".bak-staatreq-".date("Ymd-His"),$src);
$src=str_replace($a1,$r1,$src);
$src=str_replace($a2,$r2,$src);

if($src!==$start){
  file_put_contents($file,$src);
  echo "✓ form-engine.js yamalandi (CH_STAAT_REQFIX):\n";
  echo "  1) PDF'den once zorunlu alan dogrulamasi (eksikler kirmizi + uyari, paywall'dan once)\n";
  echo "  2) Bos formdan PDF uretimi engellendi\n";
  echo "  3) Gizli/devre disi alanlar dogrulamaya girmiyor (asilsiz Pflichtfeld hatasi yok)\n";
} else echo "degisiklik yok\n";

echo "\nNOT: JS dosyasi — opcache etkilemez ama TARAYICI cache'i etkiler.\n";
echo "Cloudflare varsa form-engine.js icin purge yap veya gizli pencerede test et.\n";
echo "SIL: rm apply-staatliche-pdf-fix.php\n";
echo "Test: bir staatliche form ac -> hicbir sey doldurmadan PDF'e bas -> 'Formular ist leer' uyarisi gelmeli.\n";
echo "      Zorunlu alanlari bos birak -> PDF'e bas -> alanlar kirmizi + uyari gelmeli.\n";
