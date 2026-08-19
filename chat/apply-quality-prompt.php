<?php
/**
 * ChatHelp — BELGE ÜRETİM KALİTESİ YÜKSELTME (kıdemli avukat standardı)
 * ------------------------------------------------------------------------
 *  doGenerate'in taslak prompt'undaki RULES bloğuna kalite kuralları ekler:
 *  kıdemli hukukçu üslubu, ülkenin resmi yazışma gelenekleri, somut süre/hukuki
 *  sonuç, eksiksiz yapı. Anchor: dump-dogenerate ile BİREBİR doğrulanmış satır.
 * KULLANIM: pull-updates.php?files=apply-quality-prompt.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-quality-prompt BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/api.php";
if(!file_exists($file)) exit("api.php yok\n");
$src=file_get_contents($file); $start=$src;
if(strpos($src,"CH_QUALITY_PROMPT")!==false) exit("Zaten ekli (CH_QUALITY_PROMPT).\n");

$old = '- Return ONLY the document text, no explanations";';
$new = '- Write at the standard of a SENIOR lawyer: precise, complete, persuasive — never generic filler
- Follow the formal letter/contract conventions of {$country} exactly (layout, salutation, closing)
- State concrete deadlines and the legal consequences of non-compliance where applicable
- Address every fact from CASE DETAILS; omit nothing the recipient must act on
- Return ONLY the document text, no explanations"; /*CH_QUALITY_PROMPT*/';

$n=substr_count($src,$old);
if($n<1) exit("✗ RULES anchor bulunamadi — api.php farkli. Dokunulmadi.\n");
file_put_contents($file.".bak-quality-".date("Ymd-His"),$src);
$src=str_replace($old,$new,$src);

$tmp=$file.".tmp-q"; file_put_contents($tmp,$src);
$out=[]; $code=0; exec("php -l ".escapeshellarg($tmp)." 2>&1",$out,$code); @unlink($tmp);
if($code!==0){ echo "✗ LINT HATASI — api.php DEGISTIRILMEDI:\n".implode("\n",$out)."\n"; exit; }

file_put_contents($file,$src);
echo "✓ Kalite kurallari eklendi (CH_QUALITY_PROMPT) -> $n yer.\n";
echo "  Kidemli avukat standardi + ulke yazisma gelenekleri + somut sure/sonuc + eksiksizlik.\n";
echo "SIL: rm apply-quality-prompt.php\n";
echo "Test: herhangi bir belge uret -> daha dolu, resmi format birebir, sureler/sonuclar acikca yazili olmali.\n";
