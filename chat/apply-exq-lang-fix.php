<?php
/**
 * ChatHelp — apply-exq-lang-fix (CH_EXQ_LANG_FIX) — orenk sorularda AI'nin
 *  HEP ALMANCA CEVAP VERMESI — KESIN KOK NEDEN + DUZELTME
 *
 *  KANIT (dump-examplequestions.php, apply-foto-gate.php kaynagi):
 *   Ornek soru butonlari EX=[{t:'Ich möchte...'}] Almanca sabit dizisinden
 *   kuruluyor. i18n-full katmani SADECE METIN DUGUMLERINI cevirir (TreeWalker,
 *   SHOW_TEXT) — HTML OZNITELIKLERINE ASLA DOKUNMAZ. Ama tiklama:
 *     onclick="chExAsk(this.getAttribute('data-t'))"
 *   tam olarak o OZNITELIGI okuyor — ekranda soru Ispanyolca/Turkce gorunse
 *   BILE, data-t oznitelidi sonsuza dek Almanca kaliyor. Tiklaninca
 *   sohbete Almanca metin gonderiliyor -> AI GERCEKTEN Almanca bir mesaj
 *   aldigi icin (dil garantisi dogru calissa bile) Almanca cevap veriyor.
 *   Bu, "AI hep Almanca cevap veriyor" sikayetinin TAM KOK NEDENI.
 *
 *  DUZELTME (tek satirlik kurulum, 2 cerrahi degisiklik):
 *   [1] Soru metni artik ayri bir <span class="qt"> icinde — bu, normal
 *       bir metin dugumu oldugundan i18n-full tarafindan DOGRU cevrilir.
 *   [2] Tiklama artik o span'in O ANKI (ekranda ne yaziyorsa o) metnini
 *       okuyor — data-t ozniteligi degil. Ekranda ne goruluyorsa AI'ya
 *       TAM OLARAK o gidiyor. WYSIWYG garantisi.
 *  chExAsk() fonksiyonuna DOKUNULMAZ — imzasi ayni, sadece kendisine
 *  gonderilen metin artik dogru dilde.
 * KULLANIM: pull-updates.php?files=apply-exq-lang-fix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-exq-lang-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_EXQ_LANG_FIX')!==false) exit("Zaten ekli (CH_EXQ_LANG_FIX).\n");

$fail=[];

/* [1] tiklama: data-t oznitelidi yerine .qt span'inin canli metnini oku */
$o1 = 'onclick="chExAsk(this.getAttribute(\'data-t\'))" data-t="';
$n1 = 'onclick="chExAsk(this.querySelector(\'.qt\').textContent)" data-t="';
$c=0; $src=str_replace($o1,$n1,$src,$c);
echo ($c===1?"  ✓ [1] tiklama artik EKRANDAKI (cevrilmis) metni gonderiyor\n":"  ✗ [1] anchor ($c)\n"); if($c!==1)$fail[]=1;

/* [2] soru metnini cevrilebilir bir metin dugumune (span.qt) sar */
$o2 = "<span class=\"qic\">'+e.ic+'</span>'+e.t+'<span class=\"qar\">";
$n2 = "<span class=\"qic\">'+e.ic+'</span><span class=\"qt\">'+e.t+'</span><span class=\"qar\">";
$c=0; $src=str_replace($o2,$n2,$src,$c);
echo ($c===1?"  ✓ [2] soru metni artik i18n-full tarafindan cevrilen bir <span> icinde\n":"  ✗ [2] anchor ($c)\n"); if($c!==1)$fail[]=2;

if ($fail) { echo "\nHATA: eslesmeyen adimlar: ".implode(', ',$fail)." — index.php DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(),'eq').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-exqlang-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_EXQ_LANG_FIX uygulandi. Ornek sorulara tiklaninca artik EKRANDA HANGI\n";
echo "   DIL GORUNUYORSA AI'YA TAM OLARAK O DILDE GIDIYOR — Almanca'ya donme bitti.\n";
