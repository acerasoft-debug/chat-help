<?php
/** ChatHelp — dump-gen3 (SADECE OKUR) — doGenerate'in GERCEK AI cagrisi:
 *  fonksiyon sinirlari icinde callDeepSeek/callClaude/api cagrisini bulur ve
 *  cevresini basar. Boylece tek asama mi cift asama mi KESIN gorunur. Maskeli. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
function mask($s){ return preg_replace('/(sk-[A-Za-z0-9_\-]{4})[A-Za-z0-9_\-]+/','$1***',$s); }
$api=(string)@file_get_contents(__DIR__.'/api.php');
echo "=== dump-gen3 — doGenerate gercek AI cagrisi ===\n\n";
$p=stripos($api,'function doGenerate');
if($p===false) exit("yok\n");
/* fonksiyon sonu: sonraki "\nfunction " */
$end=stripos($api,"\nfunction ",$p+20); if($end===false) $end=$p+9000;
$body=substr($api,$p,$end-$p);
echo "doGenerate uzunlugu: ".strlen($body)." B\n";
foreach(['callDeepSeek','callClaude','callAI','aiGenerate','api.deepseek.com','api.anthropic.com','deepseek-chat','claude-'] as $nd){
  echo "  '".$nd."': ".substr_count($body,$nd)." kez";
  $q=strpos($body,$nd);
  if($q!==false) echo "  (ilk @".($p+$q).")";
  echo "\n";
}
echo "\n──── AI cagrisinin cevresi ────\n";
/* ilk gercek AI cagrisini bul */
$hit=false;
foreach(['callDeepSeek','callClaude','api.anthropic.com','api.deepseek.com','callAI'] as $nd){
  $q=strpos($body,$nd);
  if($q!==false){ echo "[".$nd." cevresi]:\n".mask(substr($body,max(0,$q-500),1400))."\n\n"; $hit=true; break; }
}
if(!$hit) echo "(AI cagrisi bu fonksiyonda bulunamadi — baska bir yardimci fonksiyona deleg ediyor olabilir)\n";
echo "=== BITTI. Ciktinin TAMAMINI gonder. ===\n";
