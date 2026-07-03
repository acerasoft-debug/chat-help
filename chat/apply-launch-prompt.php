<?php
/**
 * ChatHelp — GOOGLE PLAY ONCESI BELGE KALITE KURALLARI (api.php prompt)
 * ------------------------------------------------------------------------
 *  Kapsam (kullanici maddeleri):
 *   #4 Harici/egzotik karakter YASAK — sadece belge dilinin dogal alfabesi
 *      (Almanca/Ingilizce/Turkce/Fransizca/Ispanyolca/Italyanca/Felemenkce/
 *       Lehce/Isvecce/Portekizce). Emoji, sus-Unicode, kutu-cizgi, ilgisiz
 *      alfabe (Kiril/Arap/CJK) yasak.
 *   #6 Resmi format ZORUNLU: gonderen (ad/adres), yer+tarih satiri, muhatap
 *      makamin tam adi/adresi, uygun hitap ve kapanis, imza satiri.
 *   #7 Avukat gibi SONUC ODAKLI + KISA; ilgili kanun maddeleri (§/Art./madde)
 *      numarasiyla; gereksiz doldurma yok.
 *
 *  Anchor: '- Return ONLY the document text, no explanations' — hem quality-prompt
 *  uygulanmis hem uygulanmamis durumda var (o satir degismedi). Kurallar bu
 *  satirin ONUNE eklenir, string icinde kalir.
 *
 * KULLANIM: pull-updates.php?files=apply-launch-prompt.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-launch-prompt BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/api.php";
if(!file_exists($file)) exit("api.php yok\n");
$src=file_get_contents($file); $start=$src;
if(strpos($src,"CH_LAUNCH_PROMPT")!==false) exit("Zaten ekli (CH_LAUNCH_PROMPT).\n");

$anchor='- Return ONLY the document text, no explanations';
$n=substr_count($src,$anchor);
if($n<1) exit("✗ RULES anchor bulunamadi ('- Return ONLY...') — api.php farkli. Dokunulmadi.\n");
if($n>1) exit("✗ anchor $n kez var — belirsiz, dokunulmadi. Bana api.php RULES blogunu gonder.\n");

$new =
  '- Use ONLY the natural alphabet of the document language (German, English, Turkish, French, Spanish, Italian, Dutch, Polish, Swedish, Portuguese). NEVER use emoji, decorative Unicode, box-drawing characters, or letters from unrelated scripts (Cyrillic, Arabic, CJK). Plain, clean, official typography only.'."\n"
 .'- Open like an official petition: sender block (full name and address), a place-and-date line, then the exact name and address of the addressed authority/office (Behörde / makam / autorité). Use the correct salutation and closing for {$country}, and end with a signature line showing the sender name.'."\n"
 .'- Cite the specific applicable statute/article numbers of {$country} where relevant (e.g. § …, Art. …, madde …) — accurate references only, never invented ones.'."\n"
 .'- Write result-oriented and concise like a practicing lawyer: every sentence advances the request. No filler, no padding, no restating the obvious.'."\n"
 .$anchor.' /*CH_LAUNCH_PROMPT*/';

file_put_contents($file.".bak-launchprompt-".date("Ymd-His"),$src);
$src=str_replace($anchor, $new, $src);

$tmp=$file.".tmp-lp"; file_put_contents($tmp,$src);
$out=[]; $code=0; exec("php -l ".escapeshellarg($tmp)." 2>&1",$out,$code); @unlink($tmp);
if($code!==0){ echo "✗ LINT HATASI — api.php DEGISTIRILMEDI:\n".implode("\n",$out)."\n"; exit; }

file_put_contents($file,$src);
echo "✓ Kalite kurallari eklendi (CH_LAUNCH_PROMPT):\n";
echo "  #4 sadece dogal alfabe, harici karakter yasak\n";
echo "  #6 gonderen + yer/tarih + makam adi/adresi + hitap/kapanis + imza\n";
echo "  #7 avukat gibi sonuc odakli/kisa + kanun maddeleri (numarali)\n";
echo "SIL: rm apply-launch-prompt.php\n";
echo "Test: herhangi bir belge uret -> ustte makam adi + tarih, altta imza; egzotik karakter yok; kisa ve maddeye atifli.\n";
