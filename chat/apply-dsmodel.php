<?php
/**
 * ChatHelp — apply-dsmodel — KOK NEDEN DUZELTMESI: DeepSeek model adi guncellenir.
 *   DeepSeek API artik 'deepseek-chat' kabul etmiyor (HTTP 400):
 *   "The supported API model names are deepseek-v4-pro or deepseek-v4-flash".
 *   Bu yuzden sohbet 'das hat nicht geklappt' veriyor, uretim 'Bitte warten'da takiliyor.
 *
 *   YAPILAN: chat/ altindaki uretim PHP dosyalarinda 'deepseek-chat' -> 'deepseek-v4-pro'.
 *   (apply-, dump- ve .bak dosyalari atlanir. Her dosya ayri lint + .bak + dogrulama.)
 * KULLANIM: /chat/pull2.php?key=...&files=apply-dsmodel.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-dsmodel BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$NEW='deepseek-v4-pro';
$found=0; $changed=0; $failed=0;
foreach (glob(__DIR__.'/*.php') as $f) {
  $bn=basename($f);
  if (preg_match('/^(apply-|dump-)|\.bak/', $bn)) continue;
  $src=@file_get_contents($f);
  if ($src===false) continue;
  $c = substr_count($src,"'deepseek-chat'") + substr_count($src,'"deepseek-chat"');
  if ($c===0) continue;
  $found++;
  $new=str_replace(["'deepseek-chat'",'"deepseek-chat"'], ["'$NEW'","\"$NEW\""], $src);

  /* lint */
  $tmp=tempnam(sys_get_temp_dir(),'ds').'.php'; file_put_contents($tmp,$new);
  $lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
  if ($rc!==0) { echo "  ✗ $bn: LINT HATASI — DOKUNULMADI\n"; $failed++; continue; }

  @file_put_contents($f.'.bak-dsmodel-'.date('Ymd-His'),$src);
  $w=@file_put_contents($f,$new);
  if ($w===false || $w<strlen($new)) { echo "  ✗ $bn: YAZMA HATASI\n"; $failed++; continue; }
  echo "  ✓ $bn: $c adet 'deepseek-chat' -> '$NEW'\n";
  $changed++;
}
if ($found===0) echo "  · Hicbir dosyada 'deepseek-chat' kalmamis — zaten guncel.\n";
if (function_exists('opcache_reset')) @opcache_reset();

/* dogrulama: canli mini test (yeni model) */
echo "\n=== Dogrulama: DeepSeek '$NEW' canli test ===\n";
if (is_file(__DIR__.'/config.php')) include_once __DIR__.'/config.php';
$dk = defined('DEEPSEEK_KEY') ? constant('DEEPSEEK_KEY') : '';
if ($dk) {
  $ch=curl_init('https://api.deepseek.com/chat/completions');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
    CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$dk,'content-type: application/json'],
    CURLOPT_POSTFIELDS=>json_encode(['model'=>$NEW,'max_tokens'=>16,
      'messages'=>[['role'=>'user','content'=>'Sag nur: OK']]]),
    CURLOPT_TIMEOUT=>30]);
  $res=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  $j=json_decode((string)$res,true);
  if ($code===200 && isset($j['choices'][0]['message']['content'])) {
    echo "  ✓ HTTP 200 — CEVAP: ".trim($j['choices'][0]['message']['content'])."\n";
  } else {
    echo "  ✗ HTTP $code — ".substr((string)$res,0,300)."\n";
  }
} else echo "  (DEEPSEEK_KEY yok)\n";

echo "\nSONUC: $changed dosya guncellendi".($failed?", $failed HATA":"").".\n";
echo "Sohbet ve belge uretimi artik calisiyor olmali — 'hallo' yazarak test edin.\n";
