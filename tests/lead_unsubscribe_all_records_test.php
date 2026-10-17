<?php
/* Tek tık abonelikten çıkma (17 Eki 2026 incelemesi): çıkış AYNI ADRESİN BÜTÜN kayıtlarına işlenir (büyük/küçük harf /
 * boşluk farkıyla kopya kayıt bir daha yazılmasın) ve kilit altında taze listeye yazılır (aynı anda bir gönderim damgası
 * yazılırsa çıkış ezilmesin). GET yalnız onay ekranı — bağlantı tarayıcıları kimseyi çıkarmaz. Sayfa ayrı süreçte koşar. */
$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? "  ok   " : "  FAIL ").$n."\n"; };
$sand = sys_get_temp_dir().'/vestra_unsub_'.bin2hex(random_bytes(4)); @mkdir($sand, 0775, true);
file_put_contents($sand.'/leads.json', json_encode([['id' => 'A', 'email' => 'info@x.example', 'unsub_token' => 'tokA', 'status' => 'contacted'],
  ['id' => 'B', 'email' => 'INFO@x.example ', 'status' => 'contacted'], ['id' => 'C', 'email' => 'other@x.example', 'status' => 'contacted']]));
file_put_contents($sand.'/run.php', '<?php define("VESTRA_DATA_DIR", '.var_export($sand, true).');
$_SERVER["HTTP_HOST"] = "localhost"; $_SERVER["REQUEST_URI"] = "/lead-unsubscribe"; $_SERVER["REQUEST_METHOD"] = getenv("M");
if (getenv("M") === "POST") $_POST["token"] = "tokA"; else $_GET["token"] = "tokA";
include '.var_export(dirname(__DIR__).'/vestra/lead-unsubscribe.php', true).';');
$page = fn(string $m) => (string)shell_exec('M='.$m.' php '.escapeshellarg($sand.'/run.php').' 2>&1');
$st = fn() => array_column(json_decode((string)file_get_contents($sand.'/leads.json'), true), 'status', 'id');
$g = $page('GET');
$t('GET: yalnız onay ekranı, kimse çıkmaz', $st() === ['A' => 'contacted', 'B' => 'contacted', 'C' => 'contacted'] && !preg_match('/Fatal|Warning/', $g));
$p = $page('POST'); $s = $st();
$t('POST: jetonun kaydı VE aynı adresin kopya kaydı çıktı; başka adres dokunulmadı', $s['A'] === 'unsubscribed' && $s['B'] === 'unsubscribed' && $s['C'] === 'contacted');
$t('kilit dosyası kullanıldı, geçici dosya kalmadı, sayfa onayı', is_file($sand.'/leads.lock') && !glob($sand.'/leads.json.tmp-*') && str_contains($p, 'unsubscribed') && !preg_match('/Fatal|Warning/', $p));
exec('rm -rf '.escapeshellarg($sand));
echo "\nTOPLAM: {$ok} gecti, {$bad} kaldi\n";
exit($bad ? 1 : 0);
