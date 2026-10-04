<?php
/**
 * ChatHelp — apply-fix-abonelik (CH_FIX_ABO) — "abonelik tanima" ACIL duzeltme
 *  (auth.php backend). ANALIZ (workflow) kanitladi: plan doLogin'de JWT'ye
 *  GOMULUYOR (generateJWT(...,$user['plan']??'free',...)) -> kullanici GIRISTEN
 *  SONRA plan satin alinca/atanınca requireAuth hala BAYAT 'free' donuyor;
 *  CH_FREEDOC doFreeStatus/doFreeUse bu $u['plan']'a baktigi icin ODEMIS
 *  kullanici free-kota kapisina dusuyor ("premium ama free muamelesi").
 *  Ayrica plan degeri 'Pro'/'elite_monthly' gibi normalize edilmemis gelebiliyor.
 *  COZUM (auth.php'ye eklemeli + 2 count-guard'li str_replace):
 *   [1] ch_plan_of(int $uid): string — HER istekte user_plans'tan TAZE okur,
 *       ch_norm_plan (yoksa yerel kopya) ile normalize eder (basic/pro/elite/
 *       free). status whitelist ['active','aktiv','trialing',''].
 *   [2] doFreeStatus + doFreeUse'daki in_array($u['plan'],...) ->
 *       in_array(ch_plan_of((int)$u['id']),...) — plan artik DB'den, bayat
 *       JWT'den DEGIL. Odemis kullanici re-login gerektirmeden taninir.
 *  node/JS yok; lint + .bak + dogrulama.
 * KULLANIM: pull2.php?key=...&files=apply-fix-abonelik.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fix-abonelik BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/auth.php';
$src = @file_get_contents($file);
if ($src===false) exit("HATA: auth.php okunamadi\n");
if (strpos($src,'CH_FIX_ABO')!==false) exit("Zaten ekli (CH_FIX_ABO).\n");
if (strpos($src,'CH_FREEDOC')===false) exit("HATA: CH_FREEDOC yok (once apply-freedoc-be.php).\n");

$fail=[];

/* ── [1] ch_plan_of + normalize fonksiyonu (dosya sonuna) ── */
$fn = <<<'PHPFN'

/* CH_FIX_ABO — plan'i her istekte DB'den TAZE oku + normalize et (JWT bayatligina karsi) */
if (!function_exists('ch_norm_plan_abo')) {
  function ch_norm_plan_abo($raw): string {
    $p = strtolower(trim((string)$raw));
    if ($p==='') return 'free';
    if (strpos($p,'elite')!==false || strpos($p,'unbegrenzt')!==false || strpos($p,'unlimited')!==false) return 'elite';
    if (strpos($p,'pro')!==false) return 'pro';
    if (strpos($p,'basic')!==false || strpos($p,'basis')!==false) return 'basic';
    if (strpos($p,'free')!==false || strpos($p,'gratis')!==false || strpos($p,'kostenlos')!==false) return 'free';
    return 'free';
  }
}
if (!function_exists('ch_plan_of')) {
  function ch_plan_of(int $uid): string {
    if ($uid<=0) return 'free';
    try {
      $pdo = db();
      $st = $pdo->prepare('SELECT plan, COALESCE(status,\'active\') st FROM user_plans WHERE user_id=? ORDER BY id DESC LIMIT 1');
      $st->execute([$uid]);
      $r = $st->fetch();
      if (!$r) return 'free';
      $active = in_array(strtolower((string)$r['st']), ['active','aktiv','trialing',''], true);
      return $active ? ch_norm_plan_abo($r['plan'] ?? '') : 'free';
    } catch (Throwable $e) { return 'free'; }
  }
}
PHPFN;

$closeTag = strrpos($src, '?>');
if ($closeTag !== false && trim(substr($src,$closeTag+2))==='') {
  $src = substr($src,0,$closeTag).$fn."\n?>".substr($src,$closeTag+2);
} else {
  $src = rtrim($src)."\n".$fn."\n";
}
echo "  ✓ [1] ch_plan_of() + normalize eklendi (DB'den taze plan)\n";

/* ── [2] freedoc kota kontrolleri: $u['plan'] -> ch_plan_of() ── */
$o2a = "if (in_array(\$u['plan'], ['basic','pro','elite'], true)) respond(['available'=>false,'reason'=>'plan']);";
$n2a = "if (in_array(ch_plan_of((int)\$u['id']), ['basic','pro','elite'], true)) respond(['available'=>false,'reason'=>'plan']);";
$c=substr_count($src,$o2a);
if($c!==1){ echo "  ✗ [2a] doFreeStatus anchor ($c)\n"; $fail[]='2a'; }
else { $src=str_replace($o2a,$n2a,$src); echo "  ✓ [2a] doFreeStatus: plan DB'den okunuyor\n"; }

$o2b = "if (in_array(\$u['plan'], ['basic','pro','elite'], true)) respond(['ok'=>false,'reason'=>'plan']);";
$n2b = "if (in_array(ch_plan_of((int)\$u['id']), ['basic','pro','elite'], true)) respond(['ok'=>false,'reason'=>'plan']);";
$c=substr_count($src,$o2b);
if($c!==1){ echo "  ✗ [2b] doFreeUse anchor ($c)\n"; $fail[]='2b'; }
else { $src=str_replace($o2b,$n2b,$src); echo "  ✓ [2b] doFreeUse: plan DB'den okunuyor\n"; }

if ($fail) { echo "\nHATA: eslesmeyen: ".implode(', ',$fail)." — auth.php DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(),'ab').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — auth.php DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-fixabo-'.date('Ymd-His'), (string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_FIX_ABO')===false || strpos($chk,'function ch_plan_of')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_FIX_ABO + ch_plan_of() diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Abonelik tanima: plan artik HER istekte DB'den taze okunur + normalize edilir.\n";
echo "   Odemis kullanici (plan satin aldiktan sonra) re-login GEREKMEDEN taninir;\n";
echo "   'Pro'/'elite_monthly' gibi degerler de dogru esler. Free-kota kapisi\n";
echo "   artik premium kullaniciyi engellemez.\n";
