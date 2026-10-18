<?php
/**
 * ChatHelp ops — name-scrub: entfernt den Namen des früheren Inhaltsverantwortlichen überall (Dateien im Webroot,
 * ChatHelp-Datenbank, WordPress-Datenbank) und setzt „Management“ ein.
 * ---------------------------------------------------------------------------------------------------------------
 * VORSCHAU (Standard): zählt Fundstellen und zeigt kurze Ausschnitte – der Name selbst wird IMMER geschwärzt (▇),
 *   E-Mail-Adressen und lange Ziffernfolgen ebenfalls; es wird nichts geändert.
 * apply=1: ersetzt den vollen Namen (alle Schreibweisen/Kodierungen) durch „Management“.
 *   · Dateien: Sicherung AUSSERHALB des Webroots (~/ch-backups/name-scrub-<ts>/…), PHP-Dateien werden mit php -l
 *     geprüft, bei Fehler sofort zurückgesetzt.
 *   · Datenbanken: nur Zeilen mit Primärschlüssel; Originalwerte als JSONL in derselben Sicherung; serialisierte
 *     PHP-Werte (WordPress-Optionen/-Meta) werden entpackt, ersetzt und neu serialisiert.
 *   · Nie angefasst: db.php, config.php, wp-config.php (nur zum Lesen der WP-Zugangsdaten geparst, nichts ausgegeben),
 *     Spalten für E-Mail/Hash/Schlüssel/Passwörter, gespeicherte Nutzerdokumente (…/data/*.json, nur gemeldet).
 *   · Fundstellen nur mit dem Vornamen werden gemeldet, aber nicht automatisch ersetzt.
 */
header('Content-Type: text/plain; charset=UTF-8');
@set_time_limit(0);
$apply = !empty($_GET['apply']);
$root = (!empty($_GET['root']) && is_dir((string)$_GET['root'])) ? rtrim((string)$_GET['root'], '/') : dirname(__DIR__);
$ts = date('Ymd-His');
$bakDir = dirname($root).'/ch-backups/name-scrub-'.$ts;
echo "ChatHelp — name-scrub (".gmdate('c').")   MODUS: ".($apply ? 'ANWENDEN' : 'VORSCHAU')."\n\n";

/* Vollständiger Name in allen Kodierungen: Klartext, HTML-Entity, JSON-/JS-Escape, Großschreibung, mit/ohne Cedille */
const NS_FULL = '/Serdar(?:\s|&nbsp;|\\\\u00a0|%20|\+)+Bilgi(?:ç|Ç|c|C|Ã§|Ã‡|&ccedil;|&Ccedil;|&#231;|&#xe7;|\\\\u00e7|\\\\u00E7|\\\\u00c7|%C3%A7)/iu';
const NS_ANY  = '/Serdar|Bilgi(?:ç|Ç|Ã§|Ã‡|&ccedil;|&Ccedil;|&#231;|&#xe7;|\\\\u00e7|\\\\u00E7|\\\\u00c7|%C3%A7)|\bBilgic\b/iu';
const NS_NEW  = 'Management';
function ns_mask(string $s): string {
    $s = preg_replace(NS_ANY, '▇', $s);
    $s = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[mail]', $s);
    $s = preg_replace('/\d[\d \/-]{5,}\d/', '[nr]', $s);
    return preg_replace('/\s+/', ' ', $s);
}
function ns_snip(string $t, int $max = 3): array {
    $out = []; $last = -1000;
    if (preg_match_all(NS_ANY, $t, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[0] as $hit) {
            if ($hit[1] - $last < 120) continue;   // Treffer im selben Ausschnitt nicht doppelt zeigen
            $last = $hit[1]; $a = max(0, $hit[1] - 70);
            $raw = preg_replace('/<[^<>]{0,300}>/', ' ', substr($t, $a, 170));
            $out[] = '…'.ns_mask(mb_convert_encoding($raw, 'UTF-8', 'UTF-8')).'…';
            if (count($out) >= $max) break;
        }
    }
    return $out;
}
function ns_replace_deep($v, int &$n) {
    if (is_string($v)) { $r = preg_replace(NS_FULL, NS_NEW, $v, -1, $c); $n += $c; return $r ?? $v; }
    if (is_array($v)) { foreach ($v as $k => $x) $v[$k] = ns_replace_deep($x, $n); return $v; }
    if (is_object($v) && !($v instanceof __PHP_Incomplete_Class)) { foreach (get_object_vars($v) as $k => $x) $v->$k = ns_replace_deep($x, $n); return $v; }
    return $v;
}
function ns_bak(string $bakDir, string $rel, string $data, bool $append = false): void {
    $f = $bakDir.'/'.$rel; if (!is_dir(dirname($f))) @mkdir(dirname($f), 0700, true);
    file_put_contents($f, $data, $append ? FILE_APPEND : 0);
}

/* ---------- 1) Dateien ---------- */
echo "== Dateien unter dem Webroot\n";
$skipDirs = ['.git', 'node_modules', 'wp-admin', 'wp-includes'];
$never = ['db.php', 'config.php', 'wp-config.php'];
$files = 0; $hitsFull = 0; $hitsPart = 0; $changed = 0;
$it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    function ($f) use ($skipDirs) { return !($f->isDir() && in_array($f->getFilename(), $skipDirs, true)); }
), RecursiveIteratorIterator::LEAVES_ONLY);
foreach ($it as $f) {
    if (!$f->isFile() || $f->isLink()) continue;
    $bn = $f->getFilename(); $path = $f->getPathname(); $rel = ltrim(substr($path, strlen($root)), '/');
    if (in_array($bn, $never, true) || $f->getSize() > 6 * 1024 * 1024) continue;
    $t = @file_get_contents($path); if ($t === false || $t === '') continue;
    if (strpos(substr($t, 0, 8192), "\0") !== false) continue;   // Binärdatei
    $files++;
    if (!preg_match(NS_ANY, $t)) continue;
    $full = preg_match_all(NS_FULL, $t); $any = preg_match_all(NS_ANY, $t);
    $isDoc = (bool)preg_match('#(^|/)data/[^/]+\.json$#', $rel);
    $hitsFull += $full; $hitsPart += max(0, $any - 2 * $full);
    echo "  · $rel — voller Name: $full, Teil-Treffer: ".max(0, $any - 2 * $full).($isDoc ? '  [gespeichertes Nutzerdokument – nur gemeldet]' : '')."\n";
    foreach (ns_snip($t) as $s) echo "      $s\n";
    if (!$apply || $isDoc || !$full) continue;
    $new = preg_replace(NS_FULL, NS_NEW, $t);
    if ($new === null || $new === $t) continue;
    ns_bak($bakDir, 'files/'.$rel, $t);
    if (@file_put_contents($path, $new) === false) { echo "      ✗ nicht beschreibbar\n"; continue; }
    if (preg_match('/\.php(\.|$)/i', $bn) && preg_match('/\.php$/i', $bn)) {
        $lint = (string)@shell_exec('php -l '.escapeshellarg($path).' 2>&1');
        if ($lint !== '' && stripos($lint, 'No syntax errors') === false) { file_put_contents($path, $t); echo "      ✗ php -l fehlgeschlagen → zurückgesetzt\n"; continue; }
    }
    $changed++; echo "      ✓ ersetzt durch „".NS_NEW."“ (Sicherung außerhalb des Webroots)\n";
}
echo "  Geprüft: $files Textdateien · voller Name: $hitsFull · Teil-Treffer: $hitsPart · geändert: $changed\n\n";

/* ---------- 2) Datenbanken ---------- */
$dbs = [];
try { require_once __DIR__.'/tokens-lib.php'; $p = th_db(); $dbs[(string)$p->query('SELECT DATABASE()')->fetchColumn()] = ['ChatHelp', $p]; }
catch (Throwable $e) { echo "== ChatHelp-DB: nicht erreichbar (".get_class($e).")\n"; }
$wpc = $root.'/wp-config.php';
if (is_file($wpc)) {
    $c = (string)file_get_contents($wpc); $g = function ($k) use ($c) { return preg_match("/define\(\s*['\"]".$k."['\"]\s*,\s*['\"]((?:[^'\"\\\\]|\\\\.)*)['\"]\s*\)/", $c, $m) ? stripcslashes($m[1]) : null; };
    $prefix = preg_match('/\$table_prefix\s*=\s*[\'"]([A-Za-z0-9_]+)[\'"]/', $c, $m) ? $m[1] : 'wp_';
    $name = $g('DB_NAME'); $host = $g('DB_HOST') ?: 'localhost';
    if ($name && !isset($dbs[$name])) {
        try {
            $hp = explode(':', $host, 2); $dsn = 'mysql:host='.$hp[0].(isset($hp[1]) && ctype_digit($hp[1]) ? ';port='.$hp[1] : (isset($hp[1]) ? ';unix_socket='.$hp[1] : '')).';dbname='.$name.';charset=utf8mb4';
            $wp = new PDO($dsn, (string)$g('DB_USER'), (string)$g('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $dbs[$name] = ['WordPress ('.$prefix.'*)', $wp];
        } catch (Throwable $e) { echo "== WordPress-DB: nicht erreichbar (".get_class($e).")\n"; }
    } elseif ($name) echo "== WordPress nutzt dieselbe Datenbank wie ChatHelp\n";
}
$skipCol = '/e?mail|hash|token|secret|passw|pass$|api_?key|^key$|_key$|sid$|endpoint|p256dh|auth$/i';
$skipTab = '/auth_codes|token_ledger|api_usage|sessions?$/i';
foreach ($dbs as $dbn => [$label, $pdo]) {
    echo "== Datenbank $label\n";
    $cols = $pdo->query("SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND DATA_TYPE IN ('char','varchar','tinytext','text','mediumtext','longtext','json') ORDER BY TABLE_NAME, ORDINAL_POSITION")->fetchAll(PDO::FETCH_ASSOC);
    $byT = []; foreach ($cols as $r) $byT[$r['t']][] = $r['c'];
    $pks = []; foreach ($pdo->query("SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND CONSTRAINT_NAME='PRIMARY'")->fetchAll(PDO::FETCH_ASSOC) as $r) $pks[$r['t']][] = $r['c'];
    $tot = 0; $chg = 0;
    foreach ($byT as $t => $cs) {
        $where = []; $args = [];
        foreach ($cs as $col) foreach (['%serdar%', '%bilgiç%', '%bilgiÃ§%', '%bilgi&ccedil;%', '%bilgi\\\\u00e7%'] as $pat) { $where[] = "`$col` LIKE ?"; $args[] = $pat; }
        try { $s = $pdo->prepare("SELECT * FROM `$t` WHERE ".implode(' OR ', $where)." LIMIT 500"); $s->execute($args); $rows = $s->fetchAll(PDO::FETCH_ASSOC); }
        catch (Throwable $e) { echo "  · $t: Abfrage fehlgeschlagen (".get_class($e).")\n"; continue; }
        if (!$rows) continue;
        if (count($rows) >= 500) echo "  · $t: mindestens 500 Zeilen – nach dem Anwenden erneut ausführen\n";
        $perCol = [];
        foreach ($rows as $row) foreach ($cs as $col) { $v = (string)($row[$col] ?? ''); if ($v !== '' && preg_match(NS_ANY, $v)) { $perCol[$col][] = $row; } }
        if (!$perCol) continue;
        foreach ($perCol as $col => $rs) {
            $full = 0; foreach ($rs as $row) $full += preg_match_all(NS_FULL, (string)$row[$col]);
            $tot += count($rs); $prot = preg_match($skipCol, $col) || preg_match($skipTab, $t);
            echo "  · $t.$col — Zeilen: ".count($rs).", voller Name: $full".($prot ? '  [geschützte Spalte – nur gemeldet]' : '').(empty($pks[$t]) ? '  [kein Primärschlüssel – nur gemeldet]' : '')."\n";
            foreach (array_slice($rs, 0, 2) as $row) foreach (ns_snip((string)$row[$col], 1) as $sn) echo "      $sn\n";
            if (!$apply || $prot || empty($pks[$t]) || !$full) continue;
            foreach ($rs as $row) {
                $old = (string)$row[$col]; $n = 0;
                $un = (preg_match('/^(a|O|s|i|b|d):/', $old) ? @unserialize($old, ['allowed_classes' => false]) : false);
                if ($un !== false || $old === 'b:0;') { $newV = serialize(ns_replace_deep($un, $n)); }
                else { $newV = preg_replace(NS_FULL, NS_NEW, $old, -1, $n); }
                if (!$n || $newV === null || $newV === $old) continue;
                $pk = []; foreach ($pks[$t] as $k) $pk[$k] = $row[$k];
                ns_bak($bakDir, 'db-'.preg_replace('/\W/', '_', $dbn).'.jsonl', json_encode(['table' => $t, 'col' => $col, 'pk' => $pk, 'old' => $old], JSON_UNESCAPED_UNICODE)."\n", true);
                $w = implode(' AND ', array_map(fn($k) => "`$k`=?", array_keys($pk)));
                $u = $pdo->prepare("UPDATE `$t` SET `$col`=? WHERE $w AND `$col`=?");
                $u->execute(array_merge([$newV], array_values($pk), [$old]));
                $chg += $u->rowCount();
            }
        }
    }
    echo "  Zeilen mit Treffern: $tot · geändert: $chg\n\n";
}
if ($apply) echo "Sicherung: ~/".basename(dirname($bakDir)).'/'.basename($bakDir)." (außerhalb des Webroots, nicht öffentlich)\n";
echo "FERTIG\n";
