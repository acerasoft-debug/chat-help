<?php
/**
 * ChatHelp - dump-aitest2 (TESHIS) - dsmodel duzeltmesi sonrasi ikinci dogrulama.
 *  [1] Uretim dosyalarinda 'deepseek-chat' kaldi mi? (kalmamali)
 *  [2] DeepSeek 'deepseek-v4-pro' canli test — max_tokens=250, content + reasoning_content ayri gosterilir
 *  [3] fall_chat gercek akis taklidi: kisa sohbet mesaji + sistem talimatiyla cagri (uretimdeki gibi)
 * KULLANIM: /chat/pull2.php?key=...&files=dump-aitest2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
@set_time_limit(120);
echo "== dump-aitest2 ==\n\n";

echo "=== [1] eski model kaldi mi? ===\n";
$left=0;
foreach (glob(__DIR__.'/*.php') as $f) {
  $bn=basename($f);
  if (preg_match('/^(apply-|dump-)|\.bak/', $bn)) continue;
  $s=(string)@file_get_contents($f);
  $c=substr_count($s,'deepseek-chat');
  if($c>0){ echo "  ✗ $bn: $c adet 'deepseek-chat' HALA VAR\n"; $left+=$c; }
}
echo $left? "  TOPLAM $left kalinti!\n" : "  ✓ Temiz — tum dosyalar 'deepseek-v4-pro'.\n";

if(is_file(__DIR__.'/config.php')) include_once __DIR__.'/config.php';
$dk = defined('DEEPSEEK_KEY') ? constant('DEEPSEEK_KEY') : '';

echo "\n=== [2] deepseek-v4-pro test (250 token) ===\n";
if($dk){
  $ch=curl_init('https://api.deepseek.com/chat/completions');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
    CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$dk,'content-type: application/json'],
    CURLOPT_POSTFIELDS=>json_encode(['model'=>'deepseek-v4-pro','max_tokens'=>250,
      'messages'=>[['role'=>'user','content'=>'Antworte mit genau einem Satz: Was ist ein Widerspruch?']]]),
    CURLOPT_TIMEOUT=>60]);
  $t0=microtime(true);
  $res=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  $dt=round((microtime(true)-$t0)*1000);
  echo "  HTTP: $code ($dt ms)\n";
  $j=json_decode((string)$res,true);
  $msg=$j['choices'][0]['message'] ?? [];
  echo "  content:           ".(isset($msg['content']) && trim($msg['content'])!=='' ? '"'.trim(substr($msg['content'],0,200)).'"' : '(BOS!)')."\n";
  echo "  reasoning_content: ".(isset($msg['reasoning_content']) && trim((string)$msg['reasoning_content'])!=='' ? 'VAR ('.strlen($msg['reasoning_content']).' kr)' : 'yok')."\n";
  echo "  finish_reason:     ".($j['choices'][0]['finish_reason'] ?? '?')."\n";
} else echo "  (anahtar yok)\n";

echo "\n=== [3] fall_chat benzeri cagri (system + gecmis, 400 token) ===\n";
if($dk){
  $ch=curl_init('https://api.deepseek.com/chat/completions');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
    CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$dk,'content-type: application/json'],
    CURLOPT_POSTFIELDS=>json_encode(['model'=>'deepseek-v4-pro','max_tokens'=>400,'temperature'=>0.4,
      'messages'=>[
        ['role'=>'system','content'=>'Du bist ein juristischer Assistent. Antworte kurz auf Deutsch.'],
        ['role'=>'user','content'=>'hallo hörst du mich'],
      ]]),
    CURLOPT_TIMEOUT=>90]);
  $t0=microtime(true);
  $res=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  $dt=round((microtime(true)-$t0)*1000);
  echo "  HTTP: $code ($dt ms)\n";
  $j=json_decode((string)$res,true);
  $msg=$j['choices'][0]['message'] ?? [];
  $c=trim((string)($msg['content'] ?? ''));
  echo "  CEVAP: ".($c!=='' ? '"'.substr($c,0,250).'"' : '(BOS!)')."\n";
  echo "  finish_reason: ".($j['choices'][0]['finish_reason'] ?? '?')."\n";
  if($c==='') echo "  GOVDE(ilk 400): ".substr((string)$res,0,400)."\n";
} else echo "  (anahtar yok)\n";

echo "\nSONUC: [3]'te dolu bir Almanca cevap goruyorsan sohbet hatti SAGLAM demektir.\n";
echo "== BITTI ==\n";
