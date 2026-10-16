<?php
/**
 * ChatHelp ops — restore-api: Recht uygulamasının ÖZGÜN chat/api.php'sini bul ve geri yükle
 * ========================================================================================
 * Arka plan: 16.10.2026 12:32 UTC'de yayın betiği, token/agent API'sini chat/api.php adıyla kopyaladı ve Recht
 * uygulamasının kendi arka ucunu (index.php → api.php?action=aichat / analyze_photo) ezdi. Token API artık
 * chat/tokens-api.php; bu betik özgün dosyayı adaylardan geri koyar.
 *
 * ÖNİZLEME (varsayılan, yazmaz): adayları listeler
 *   • chat/api.php.*  (yama betiklerinin yedekleri: .bak-*, .bak-tr-*, .bak-photobk-*)
 *   • kök api.php, wp-api.php, chatapp/api.php
 *   • kökteki / chat'teki *.zip arşivlerinin içindeki api.php'ler  (zip:<arşiv>:<içyol>)
 *   • html altında 'aichat' içeren diğer PHP dosyaları (index.php hariç)
 *   Her aday için: boyut, tarih, md5, işaretler (aichat/analyze_photo/…), aksiyon listesi; sonda ÖNERİ.
 * UYGULA: apply=1 & src=<html'e göreli yol>  ya da  src=zip:<arşiv>:<içyol>
 *   mevcut api.php → api.php.bak-restore-<ts> yedeklenir, aday kopyalanır, php -l; hata → geri alınır.
 * KULLANIM (CLI): php -r '$_GET=["apply"=>"1","src"=>"chat/api.php.bak-tr-2026…"]; include "restore-api.php";'
 */
header('Content-Type: text/plain; charset=UTF-8');
@set_time_limit(120);
$CH   = __DIR__;
$ROOT = dirname(__DIR__);
$apply = isset($_GET['apply']);
$src   = (string)($_GET['src'] ?? '');
echo "ChatHelp — restore-api  (".date('c').")   MOD: ".($apply ? 'UYGULA' : 'ÖNİZLEME')."\n";
echo "ROOT=$ROOT  CH=$CH\n\n";

$MARKS = ['aichat','analyze_photo','detect_location','strategie','followup','doGenerate','callClaude','callClaudeVision','CLAUDE_KEY','DEEPSEEK_KEY','tokens-lib','/v1/'];
function marks(string $s): string {
    global $MARKS; $o = [];
    foreach ($MARKS as $m) $o[] = $m.(strpos($s, $m) !== false ? '✓' : '✗');
    return implode(' ', $o);
}
function actions(string $s): string {
    $a = [];
    if (preg_match_all("/'([a-z_]+)'\s*=>\s*do[A-Za-z]+\s*\(/", $s, $m)) $a = $m[1];
    if (preg_match_all("/case\s+'([a-z_]+)'/", $s, $m)) $a = array_merge($a, $m[1]);
    if (preg_match_all("/\\\$action\s*===?\s*'([a-z_]+)'/", $s, $m)) $a = array_merge($a, $m[1]);
    $a = array_values(array_unique($a));
    return $a ? implode(',', $a) : '(aksiyon bulunamadı)';
}
function kind(string $s): string {
    $recht  = strpos($s, 'callClaude') !== false || strpos($s, 'doGenerate') !== false;
    $tokens = strpos($s, 'tokens-lib') !== false || strpos($s, 'th_account') !== false;
    if ($tokens) return 'TOKEN-API (benim, yanlış yerde)';
    if ($recht && strpos($s, 'aichat') !== false && strpos($s, 'analyze_photo') !== false) return 'RECHT-BACKEND TAM ★';
    if ($recht) return 'recht-backend (eski/kısmi)';
    return 'bilinmiyor';
}
function row(string $label, string $s, ?int $mtime, string $path): array {
    return ['label'=>$label, 'size'=>strlen($s), 'mtime'=>$mtime, 'md5'=>substr(md5($s),0,10), 'kind'=>kind($s), 'marks'=>marks($s), 'actions'=>actions($s), 'path'=>$path];
}
function show(array $r): void {
    echo sprintf("  %-58s %8d B  %s  md5:%s\n", $r['label'], $r['size'], $r['mtime'] ? date('Y-m-d H:i', $r['mtime']) : '          -     ', $r['md5']);
    echo "      ".$r['kind']."\n      ".$r['marks']."\n      aksiyonlar: ".$r['actions']."\n";
}

$cands = [];
/* 1) mevcut api.php */
echo "=== 1) Şu anki chat/api.php ===\n";
if (is_file("$CH/api.php")) { $s = (string)file_get_contents("$CH/api.php"); show(row('chat/api.php (ŞU AN)', $s, filemtime("$CH/api.php"), "$CH/api.php")); }
else echo "  chat/api.php YOK\n";
echo "  chat/tokens-api.php: ".(is_file("$CH/tokens-api.php") ? 'var ('.filesize("$CH/tokens-api.php").' B)' : 'yok')."\n\n";

/* 2) chat/ içindeki yedekler ve api benzeri dosyalar */
echo "=== 2) chat/ içindeki api.php yedekleri / api-benzeri dosyalar ===\n";
$skip = ['api.php','fall-api.php','intake-api.php','widget-api.php','tokens-api.php'];
$files = array_merge(glob("$CH/api.php.*") ?: [], glob("$CH/api*.php*") ?: [], glob("$CH/*api*.bak*") ?: []);
$files = array_values(array_unique($files)); usort($files, fn($a,$b)=>filemtime($b)<=>filemtime($a));
$n = 0;
foreach ($files as $f) {
    $b = basename($f); if (in_array($b, $skip, true) || is_dir($f)) continue;
    $s = (string)file_get_contents($f); $r = row('chat/'.$b, $s, filemtime($f), $f); show($r); $cands[] = $r; $n++;
}
if (!$n) echo "  (chat/ içinde api.php yedeği yok)\n";
echo "\n  --- chat/ içindeki tüm *.bak* (ad · tarih) ---\n";
$baks = glob("$CH/*.bak*") ?: []; usort($baks, fn($a,$b)=>filemtime($b)<=>filemtime($a));
foreach (array_slice($baks, 0, 60) as $f) echo "    ".date('Y-m-d H:i', filemtime($f))."  ".basename($f)."  ".filesize($f)." B\n";
if (count($baks) > 60) echo "    … +".(count($baks)-60)." dosya\n";
echo "\n";

/* 3) kök adaylar */
echo "=== 3) Kök adaylar ===\n";
foreach (['api.php','wp-api.php','chatapp/api.php','chatapp/wp-api.php'] as $rel) {
    $f = "$ROOT/$rel"; if (!is_file($f)) { echo "  $rel: yok\n"; continue; }
    $s = (string)file_get_contents($f); $r = row($rel, $s, filemtime($f), $f); show($r); $cands[] = $r;
}
echo "\n";

/* 4) zip arşivleri */
echo "=== 4) Zip arşivleri (kök ve chat/) ===\n";
$zips = array_merge(glob("$ROOT/*.zip") ?: [], glob("$CH/*.zip") ?: [], glob("$ROOT/*/*.zip") ?: []);
if (!$zips) echo "  zip yok\n";
foreach ($zips as $z) {
    echo "  ".str_replace($ROOT.'/', '', $z)."  (".filesize($z)." B, ".date('Y-m-d H:i', filemtime($z)).")\n";
    if (!class_exists('ZipArchive')) { echo "    ZipArchive yok — unzip -l:\n".(string)@shell_exec('unzip -l '.escapeshellarg($z).' 2>&1 | grep -iE "api\.php|index\.php|auth\.php" | head -20'); continue; }
    $za = new ZipArchive(); if ($za->open($z) !== true) { echo "    açılamadı\n"; continue; }
    $found = 0;
    for ($i = 0; $i < $za->numFiles; $i++) {
        $st = $za->statIndex($i); $nm = $st['name'];
        if (preg_match('~(^|/)(wp-)?api\.php$~', $nm)) {
            $s = (string)$za->getFromIndex($i); $r = row("zip:".basename($z).":$nm", $s, $st['mtime'] ?? null, "zip:$z:$nm"); show($r); $cands[] = $r; $found++;
        }
    }
    if (!$found) echo "    içinde api.php yok (".$za->numFiles." giriş)\n";
    $za->close();
}
echo "\n";

/* 5) html altında 'aichat' içeren diğer PHP dosyaları */
echo "=== 5) html altında 'aichat' geçen PHP dosyaları (index.php* hariç, WP hariç) ===\n";
$out = (string)@shell_exec('grep -rl --include="*.php" --include="*.php.bak*" --exclude="index.php*" --exclude-dir=wp-admin --exclude-dir=wp-includes --exclude-dir=wp-content -e aichat '.escapeshellarg($ROOT).' 2>/dev/null | head -40');
$hits = array_filter(array_map('trim', explode("\n", $out)));
if (!$hits) echo "  (yok)\n";
foreach ($hits as $f) {
    $already = false; foreach ($cands as $c) if ($c['path'] === $f) $already = true;
    if ($already) { echo "  ".str_replace($ROOT.'/', '', $f)."  (yukarıda listelendi)\n"; continue; }
    $s = (string)file_get_contents($f); $r = row(str_replace($ROOT.'/', '', $f), $s, filemtime($f), $f); show($r); $cands[] = $r;
}
echo "\n";

/* 6) öneri */
echo "=== 6) ÖNERİ ===\n";
$full = array_filter($cands, fn($c)=>strpos($c['kind'], 'TAM') !== false);
$part = array_filter($cands, fn($c)=>strpos($c['kind'], 'recht-backend') === 0);
usort($full, fn($a,$b)=>($b['mtime']??0)<=>($a['mtime']??0)); usort($part, fn($a,$b)=>($b['mtime']??0)<=>($a['mtime']??0));
$best = $full ? array_values($full)[0] : null;
if ($best) {
    $srcArg = str_starts_with($best['path'], 'zip:') ? $best['path'] : str_replace($ROOT.'/', '', $best['path']);
    echo "  ★ TAM aday: ".$best['label']."  →  args: {\"apply\":\"1\",\"src\":\"".$srcArg."\"}\n";
} elseif ($part) {
    $p = array_values($part)[0]; $srcArg = str_starts_with($p['path'], 'zip:') ? $p['path'] : str_replace($ROOT.'/', '', $p['path']);
    echo "  Tam (aichat+analyze_photo) aday YOK. En yeni kısmi aday: ".$p['label']."  →  args: {\"apply\":\"1\",\"src\":\"".$srcArg."\"}\n";
    echo "  Not: kısmi aday 'aichat' aksiyonunu içermiyorsa sohbet akışı çalışmaz; Host Europe/GoDaddy yedeğinden (16.10.2026 12:30 UTC öncesi)\n      html/chat/api.php dosyasının geri alınması gerekir.\n";
} else {
    echo "  Sunucuda Recht arka ucu adayı bulunamadı → Host Europe/GoDaddy 'Backups' panelinden 16.10.2026 12:30 UTC öncesi yedekten html/chat/api.php geri alınmalı.\n";
}

if (!$apply) { echo "\nÖNİZLEME bitti; hiçbir şey yazılmadı.\n"; exit; }

/* ---- UYGULA ---- */
echo "\n=== UYGULA: src=$src ===\n";
if ($src === '') exit("  src verilmedi.\n");
$content = null;
if (str_starts_with($src, 'zip:')) {
    [$_, $zf, $entry] = array_pad(explode(':', $src, 3), 3, '');
    if (!is_file($zf) && is_file("$ROOT/$zf")) $zf = "$ROOT/$zf";
    $za = new ZipArchive(); if (!is_file($zf) || $za->open($zf) !== true) exit("  zip açılamadı: $zf\n");
    $content = $za->getFromName($entry); $za->close();
    if ($content === false) exit("  zip içinde yok: $entry\n");
} else {
    $f = str_starts_with($src, '/') ? $src : "$ROOT/$src";
    $real = realpath($f);
    if ($real === false || !is_file($real) || strpos($real, realpath($ROOT)) !== 0) exit("  kaynak html altında bulunamadı: $src\n");
    $content = (string)file_get_contents($real);
}
if (strpos($content, 'callClaude') === false && strpos($content, 'doGenerate') === false) exit("  Kaynak Recht arka ucuna benzemiyor (callClaude/doGenerate yok) — yazılmadı.\n");
if (strpos($content, 'tokens-lib') !== false) exit("  Kaynak token API'si — yazılmadı.\n");
echo "  kaynak: ".strlen($content)." B  ".kind($content)."\n  ".marks($content)."\n";

$target = "$CH/api.php"; $ts = date('Ymd-His');
if (is_file($target)) { $bak = "$target.bak-restore-$ts"; copy($target, $bak); echo "  yedek: ".basename($bak)."\n"; }
$tmp = "$CH/.api.restore.$ts.tmp"; file_put_contents($tmp, $content);
$lint = (string)shell_exec('php -l '.escapeshellarg($tmp).' 2>&1');
if (stripos($lint, 'No syntax errors') === false) { unlink($tmp); exit("  ✗ php -l HATASI, yazılmadı: ".trim($lint)."\n"); }
rename($tmp, $target); @chmod($target, 0644);
echo "  ✓ chat/api.php geri yüklendi (".filesize($target)." B)  php -l: ".trim($lint)."\n";
echo "  Doğrulama: https://chat-help.com/chat/api.php?action=detect_location  → JSON beklenir; ve Recht uygulamasında bir soru sor (aichat).\n";
