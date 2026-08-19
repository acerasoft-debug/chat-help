<?php
/** ChatHelp — dump-gen-debug: son belge uretiminde ne alindi/ne uretildi (CH_GENDEBUG ciktisi).
 *  SADECE OKUR. SIL: rm dump-gen-debug.php */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
$dir=__DIR__.'/jbdata';
echo "=== JBDATA KLASOR DURUMU ===\n";
echo "klasor var mi : ".(is_dir($dir)?'EVET':'HAYIR')."\n";
echo "yazilabilir   : ".(is_writable($dir)?'EVET':'HAYIR')."\n";
$files=is_dir($dir)?array_values(array_filter(scandir($dir),function($x){return $x!=='.'&&$x!=='..';})):[];
echo "icindeki dosyalar: ".($files?implode(", ",$files):'(bos)')."\n";

$fin=__DIR__.'/jbdata/gen-debug-in.json';
if(is_file($fin)){
  $di=json_decode(file_get_contents($fin),true);
  echo "\n=== GIRIS KAYDI (doGenerate BASI — ham istek) ===\n";
  echo "zaman        : ".($di['time']??'?')."\n";
  echo "docType      : ".($di['docType']??'?')."  docName: ".($di['docName']??'?')."  country: ".($di['country']??'?')."\n";
  echo "body_keys    : ".(is_array($di['body_keys']??null)?implode(", ",$di['body_keys']):var_export($di['body_keys']??null,true))."\n";
  echo "answers_type : ".($di['answers_type']??'?')."\n";
  $ak=$di['answers_keys']??null;
  echo "answers_keys : ".(is_array($ak)?(count($ak)?implode(", ",$ak):"(BOS!)"):var_export($ak,true))."\n";
  echo "photos_in_body: ".($di['photos_in_body']??'?')."\n";
  echo "answers(tam) :\n".json_encode($di['answers']??null,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
  echo "answersText  :\n".($di['answersText']??'(yok)')."\n";
} else {
  echo "\n=== GIRIS KAYDI YOK (gen-debug-in.json) ===\n";
  echo "-> doGenerate ya HIC cagrilmadi ya answersText satirina ulasmadi.\n";
}

$f=__DIR__.'/jbdata/gen-debug.json';
if(!is_file($f)){ echo "\n=== KAYDETME-ONCESI KAYDI YOK (gen-debug.json) ===\n-> Uretim kaydetme adimina ULASMADI (AI cagrisi sirasinda koptu?).\n\n=== BITTI. TAMAMINI yapistir. ===\n"; exit; }
$d=json_decode(file_get_contents($f),true);
if(!$d){ echo "Kayit okunamadi, ham icerik:\n".file_get_contents($f)."\n"; exit; }

echo "=== SON URETIM TESHISI ===\n";
echo "zaman        : ".($d['time']??'?')."\n";
echo "docType      : ".($d['docType']??'?')."\n";
echo "docName      : ".($d['docName']??'?')."\n";
echo "country      : ".($d['country']??'?')."\n";
echo "foto sayisi  : ".($d['photo_count']??'?')."\n";
echo "vision cevaba girdi mi: ".(($d['vision_in_answers']??false)?'EVET':'HAYIR')."\n";
echo "research uzunlugu     : ".($d['research_len']??'?')."\n";
echo "taslak (draft) uzunlugu: ".($d['draft_len']??'?')."\n";
echo "nihai (final) uzunlugu : ".($d['final_len']??'?')."\n";

echo "\n--- ANSWERS KEYS (formdan gelen cevap anahtarlari) ---\n";
$ak=$d['answers_keys']??null;
echo is_array($ak)?(count($ak)? implode(", ",$ak) : "(BOS! — hic cevap gelmemis)") : var_export($ak,true);
echo "\n";

echo "\n--- ANSWERS (tam) ---\n";
echo json_encode($d['answers']??null, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";

echo "\n--- answersText (CASE DETAILS'e giden metin) ---\n";
echo ($d['answersText']??'(yok)')."\n";

echo "\n--- finalPrompt BASI (AI'ya giden nihai istem) ---\n";
echo ($d['finalPrompt_head']??'(yok)')."\n";

echo "\n--- DRAFT BASI (ilk AI ciktisi) ---\n";
echo ($d['draft_head']??'(yok)')."\n";

echo "\n--- FINAL BASI (kullaniciya donen belge) ---\n";
echo ($d['final_head']??'(yok)')."\n";

echo "\n=== BITTI. TAMAMINI yapistir. SIL: rm dump-gen-debug.php ===\n";
