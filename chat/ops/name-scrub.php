<?php
/**
 * ChatHelp ops — name-scrub: entfernt den Namen des früheren Inhaltsverantwortlichen aus allen ÖFFENTLICHEN Inhalten
 * (Dateien im Webroot inkl. Sicherungskopien, WordPress-Seiten/-Einstellungen/-Formulartexte/-Übersetzungen,
 * WordPress-Autorenname) und setzt „Management“ ein.
 * ---------------------------------------------------------------------------------------------------------------
 * AUSGABE: nur Zählungen und (geschwärzte) Pfade/Tabellennamen – NIEMALS Textausschnitte (Protokolle liegen bei GitHub).
 * Der Name steht nicht im Klartext in diesem Skript (Base64), damit er in keinem Suchergebnis auftaucht.
 *
 * VORSCHAU (Standard): zählt nur.  apply=1: ersetzt.
 *   · Dateien: Sicherung AUSSERHALB des Webroots (~/ch-backups/name-scrub-<ts>/…), PHP mit php -l geprüft, sonst zurück.
 *   · Datenbank: nur Zeilen mit Primärschlüssel; Originalwerte als JSONL in derselben Sicherung; serialisierte PHP-Werte
 *     werden entpackt, ersetzt und neu serialisiert.
 *   · NUR GEMELDET (persönliche Datensätze, keine öffentlichen Inhalte): gespeicherte Nutzerdokumente (…/data/*.json),
 *     Formular-Einsendungen, Buchungen/Bestellungen, App-Konten und -Profile, Spalten für E-Mail/Login/Hash/Schlüssel.
 *   · Nie geöffnet: db.php, config.php; wp-config.php nur zum Lesen der WP-Zugangsdaten geparst (nichts ausgegeben).
 */
header('Content-Type: text/plain; charset=UTF-8');
@set_time_limit(0);
$apply = !empty($_GET['apply']);
$root = (!empty($_GET['root']) && is_dir((string)$_GET['root'])) ? rtrim((string)$_GET['root'], '/') : dirname(__DIR__);
$ts = date('Ymd-His');
$bakDir = dirname($root).'/ch-backups/name-scrub-'.$ts;
echo "ChatHelp — name-scrub v2 (".gmdate('c').")   MODUS: ".($apply ? 'ANWENDEN' : 'VORSCHAU')."\n\n";

[$F, $M, $L] = array_map('base64_decode', ['U2VyZGFy', 'Q2VtYWxlZGRpbg==', 'QmlsZ2k=']);
$SEP = '(?:\s|&nbsp;|&#160;|\\\\+u00a0|%20|\+)+';
$CC  = '(?:ç|Ç|c|Ã§|Ã‡|&ccedil;|&#231;|&#xe7;|&#199;|\\\\+u00e7|\\\\+u00c7|%C3%A7|%C3%87)';
$CCs = '(?:ç|Ç|Ã§|Ã‡|&ccedil;|&#231;|&#xe7;|&#199;|\\\\+u00e7|\\\\+u00c7|%C3%A7|%C3%87)';
$f = preg_quote($F, '/'); $m = preg_quote($M, '/'); $l = preg_quote($L, '/');
$FULLNAME = "(?:$m$SEP)?$f(?:$SEP$m)?$SEP$l$CC";
define('NS_TITLE', "/(?:\\bHerrn?|\\bHr\\.)$SEP$FULLNAME/iu");   // „vertreten durch Herr X“ → „vertreten durch das Management“
define('NS_FULL',  "/$FULLNAME/iu");
define('NS_ANY',   "/\\b$f\\b|\\b$m\\b|$l$CCs|\\b{$l}c\\b/iu");
define('NS_NEW', 'Management');

function ns_rep(string $s, int &$n): string {
    $s = preg_replace(NS_TITLE, 'das '.NS_NEW, $s, -1, $a) ?? $s;
    $s = preg_replace(NS_FULL, NS_NEW, $s, -1, $b) ?? $s;
    $n += $a + $b; return $s;
}
function ns_count(string $s): array {               // [voller Name, übrige Teil-Treffer]
    $full = preg_match_all(NS_FULL, $s);
    $rest = preg_match_all(NS_ANY, (string)preg_replace(NS_FULL, ' ', $s));
    return [(int)$full, (int)$rest];
}
function ns_mask(string $s): string { return (string)preg_replace(NS_ANY, '▇', $s); }
function ns_deep($v, int &$n) {
    if (is_string($v)) return ns_rep($v, $n);
    if (is_array($v)) { foreach ($v as $k => $x) $v[$k] = ns_deep($x, $n); return $v; }
    if (is_object($v) && !($v instanceof __PHP_Incomplete_Class)) { foreach (get_object_vars($v) as $k => $x) $v->$k = ns_deep($x, $n); return $v; }
    return $v;
}
function ns_bak(string $bakDir, string $rel, string $data, bool $append = false): void {
    $file = $bakDir.'/'.$rel; if (!is_dir(dirname($file))) @mkdir(dirname($file), 0700, true);
    file_put_contents($file, $data, $append ? FILE_APPEND : 0);
}
function ns_http_status(string $url): string {
    if (!function_exists('curl_init')) return '?';
    $ch = curl_init($url); curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_FOLLOWLOCATION => false]);
    curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE); $t = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE); curl_close($ch);
    return $c.($t !== '' ? ' '.$t : '');
}

/* ---------- 1) Dateien ---------- */
echo "== Dateien unter dem Webroot\n";
$skipDirs = ['.git', 'node_modules', 'wp-admin', 'wp-includes'];
$never = ['db.php', 'config.php', 'wp-config.php'];
$files = 0; $sumF = 0; $sumP = 0; $changed = 0; $docs = 0; $docDirs = []; $bakSamples = [];
$it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    function ($x) use ($skipDirs) { return !($x->isDir() && in_array($x->getFilename(), $skipDirs, true)); }
), RecursiveIteratorIterator::LEAVES_ONLY);
foreach ($it as $x) {
    if (!$x->isFile() || $x->isLink()) continue;
    $bn = $x->getFilename(); $path = $x->getPathname(); $rel = ltrim(substr($path, strlen($root)), '/');
    if (in_array($bn, $never, true) || strpos($bn, '_ops_') === 0 || $x->getSize() > 6 * 1024 * 1024) continue;
    $t = @file_get_contents($path); if ($t === false || $t === '') continue;
    if (strpos(substr($t, 0, 8192), "\0") !== false) continue;
    $files++;
    if (!preg_match(NS_ANY, $t)) continue;
    [$full, $part] = ns_count($t); $sumF += $full; $sumP += $part;
    $isDoc = (bool)preg_match('#(^|/)data/[^/]+\.json$#', $rel);
    if ($isDoc) { $docs++; $docDirs[dirname($rel)] = $rel; continue; }
    echo "  · ".ns_mask($rel)." — voller Name: $full, Teil-Treffer: $part\n";
    if (preg_match('/\.(bak|prev|GOOD|old|orig)\b|\.php\.[A-Za-z]/', $bn) && !preg_match('/\.php$/', $bn)) $bakSamples[] = $rel;
    if (!$apply || !$full) continue;
    $n = 0; $new = ns_rep($t, $n);
    if (!$n || $new === $t) continue;
    ns_bak($bakDir, 'files/'.$rel, $t);
    if (@file_put_contents($path, $new) === false) { echo "      ✗ nicht beschreibbar\n"; continue; }
    if (preg_match('/\.php$/i', $bn)) {
        $lint = (string)@shell_exec('php -l '.escapeshellarg($path).' 2>&1');
        if ($lint !== '' && stripos($lint, 'No syntax errors') === false) { file_put_contents($path, $t); echo "      ✗ php -l fehlgeschlagen → zurückgesetzt\n"; continue; }
    }
    $changed++; echo "      ✓ {$n}× ersetzt\n";
}
echo "  Gespeicherte Nutzerdokumente mit Treffern (nur gemeldet, unverändert): $docs\n";
foreach ($docDirs as $d => $one) {
    $ht = is_file($root.'/'.$d.'/.htaccess') ? (preg_match('/deny\s+from\s+all|require\s+all\s+denied/i', (string)file_get_contents($root.'/'.$d.'/.htaccess')) ? 'gesperrt' : 'vorhanden, ohne Sperre') : 'FEHLT';
    echo "    Ordner ".ns_mask($d)."/ — .htaccess: $ht · Web-Abruf eines Dokuments: ".ns_http_status('https://chat-help.com/'.str_replace('%2F', '/', rawurlencode($one)))."\n";
}
if ($bakSamples) echo "  Sicherungskopien im Web (Beispiel-Abruf): ".ns_mask($bakSamples[0])." → ".ns_http_status('https://chat-help.com/'.str_replace('%2F', '/', rawurlencode($bakSamples[0])))."\n";
echo "  Geprüft: $files Textdateien · voller Name: $sumF · Teil-Treffer: $sumP · geändert: $changed Dateien\n\n";

/* ---------- 2) Datenbanken ---------- */
$dbs = [];
try { require_once __DIR__.'/tokens-lib.php'; $p = th_db(); $dbs[(string)$p->query('SELECT DATABASE()')->fetchColumn()] = ['ChatHelp', $p]; }
catch (Throwable $e) { echo "== ChatHelp-DB: nicht erreichbar (".get_class($e).")\n"; }
$wpc = $root.'/wp-config.php';
if (is_file($wpc)) {
    $c = (string)file_get_contents($wpc); $g = function ($k) use ($c) { return preg_match("/define\(\s*['\"]".$k."['\"]\s*,\s*['\"]((?:[^'\"\\\\]|\\\\.)*)['\"]\s*\)/", $c, $mm) ? stripcslashes($mm[1]) : null; };
    $name = $g('DB_NAME'); $host = $g('DB_HOST') ?: 'localhost';
    if ($name && !isset($dbs[$name])) {
        try {
            $hp = explode(':', $host, 2); $dsn = 'mysql:host='.$hp[0].(isset($hp[1]) && ctype_digit($hp[1]) ? ';port='.$hp[1] : (isset($hp[1]) ? ';unix_socket='.$hp[1] : '')).';dbname='.$name.';charset=utf8mb4';
            $dbs[$name] = ['WordPress', new PDO($dsn, (string)$g('DB_USER'), (string)$g('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION])];
        } catch (Throwable $e) { echo "== WordPress-DB: nicht erreichbar (".get_class($e).")\n"; }
    } elseif ($name) echo "== WordPress nutzt dieselbe Datenbank wie ChatHelp\n";
}
/* Datensätze (nur melden) vs. öffentliche Inhalte (ersetzen) */
$recordTab = '/fluentform_(submissions|entry_details|submission_meta|logs|draft)|_(orders?|order_items?|wc_|woocommerce_|edd_|actionscheduler)|^(users|user_profiles|appt_bookings|orders|order_items|rides|hotel_bookings|hotel_requests|callbacks|phone_calls|wa_.*|push_.*|auth_codes|token_.*|api_usage|agent_gaps|train_jobs|chat_.*|messages?|conversations?|leads?)$/i';
$protCol   = '/e?mail|hash|token|secret|passw|pass$|api_?key|^key$|_key$|sid$|endpoint|p256dh|^auth$|user_login|user_nicename|^login$/i';
$identity  = ['first_name', 'last_name', 'nickname', 'display_name'];
foreach ($dbs as $dbn => [$label, $pdo]) {
    echo "== Datenbank $label\n";
    $cols = $pdo->query("SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND DATA_TYPE IN ('char','varchar','tinytext','text','mediumtext','longtext','json') ORDER BY TABLE_NAME, ORDINAL_POSITION")->fetchAll(PDO::FETCH_ASSOC);
    $byT = []; foreach ($cols as $r) $byT[$r['t']][] = $r['c'];
    $pks = []; foreach ($pdo->query("SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND CONSTRAINT_NAME='PRIMARY'")->fetchAll(PDO::FETCH_ASSOC) as $r) $pks[$r['t']][] = $r['c'];
    $pats = ['%'.$F.'%', '%'.$M.'%', '%'.$L.'ç%', '%'.$L.'Ã§%', '%'.$L.'&ccedil;%', '%'.$L.'\\\\u00e7%', '%'.$L.'\\\\\\\\u00e7%'];
    $tot = 0; $chg = 0; $rep = 0;
    foreach ($byT as $t => $cs) {
        $short = (string)preg_replace('/^wp_[a-z0-9]*?_?(?=[a-z])/i', '', $t);
        if (preg_match('/^wp_[0-9a-f]{6,}_(.+)$/i', $t, $mm)) $short = $mm[1]; elseif (preg_match('/^wp_(.+)$/', $t, $mm) && $label === 'WordPress') $short = $mm[1];
        $isRecord = (bool)preg_match($recordTab, $short) && !($label === 'WordPress' && $short === 'users');
        $isWpUsers = ($label === 'WordPress' && $short === 'users');
        $isMeta = in_array($short, ['usermeta', 'uwp_usermeta'], true);
        $where = []; $args = [];
        foreach ($cs as $col) foreach ($pats as $pat) { $where[] = "`$col` LIKE ?"; $args[] = $pat; }
        try { $s = $pdo->prepare("SELECT * FROM `$t` WHERE ".implode(' OR ', $where)." LIMIT 2000"); $s->execute($args); $rows = $s->fetchAll(PDO::FETCH_ASSOC); }
        catch (Throwable $e) { echo "  · $t: Abfrage fehlgeschlagen (".get_class($e).")\n"; continue; }
        if (!$rows) continue;
        if (count($rows) >= 2000) echo "  · $t: mindestens 2000 Zeilen – nach dem Anwenden erneut ausführen\n";
        foreach ($cs as $col) {
            $hit = []; $full = 0; $part = 0;
            foreach ($rows as $row) { $v = (string)($row[$col] ?? ''); if ($v === '' || !preg_match(NS_ANY, $v)) continue; $hit[] = $row; [$a, $b] = ns_count($v); $full += $a; $part += $b; }
            if (!$hit) continue;
            $tot += count($hit);
            $prot = (bool)preg_match($protCol, $col);
            $policy = $prot ? 'geschützte Spalte – nur gemeldet' : ($isRecord ? 'persönlicher Datensatz – nur gemeldet' : (empty($pks[$t]) ? 'kein Primärschlüssel – nur gemeldet' : 'öffentlicher Inhalt – wird ersetzt'));
            /* WordPress-Autorenname: einzelne Namensfelder (Vor-/Nachname, Spitzname) */
            $identityCol = ($isWpUsers && $col === 'display_name') || ($short === 'uwp_usermeta' && in_array($col, $identity, true)) || ($short === 'usermeta' && $col === 'meta_value');
            if ($identityCol && !$prot && !empty($pks[$t])) $policy = ($short === 'usermeta') ? 'Autorenname (Vor-/Nachname, Spitzname) – wird ersetzt, übrige Metadaten nur gemeldet' : 'Autorenname – wird ersetzt';
            elseif ($isMeta && !$identityCol && !$prot) $policy = 'Nutzer-Metadaten – nur gemeldet';
            echo "  · $t.$col — Zeilen: ".count($hit).", voller Name: $full, Teil-Treffer: $part  [$policy]\n";
            if (!$apply || strpos($policy, 'wird ersetzt') === false) continue;
            foreach ($hit as $row) {
                $old = (string)$row[$col]; $n = 0;
                if ($identityCol) {
                    if ($short === 'usermeta' && !in_array((string)($row['meta_key'] ?? ''), $identity, true)) continue;
                    $tv = trim($old);
                    if (preg_match('/^'.$f.'$/iu', $tv)) { $newV = NS_NEW; $n = 1; }
                    elseif (preg_match('/^(?:'.$m.')$|^'.$l.$CC.'$/iu', $tv)) { $newV = ''; $n = 1; }
                    else $newV = ns_rep($old, $n);
                } else {
                    $un = (preg_match('/^(a|O|s|i|b|d):/', $old) ? @unserialize($old, ['allowed_classes' => false]) : false);
                    $newV = ($un !== false || $old === 'b:0;') ? serialize(ns_deep($un, $n)) : ns_rep($old, $n);
                }
                if (!$n || $newV === $old) continue;
                $pk = []; foreach ($pks[$t] as $k) $pk[$k] = $row[$k];
                ns_bak($bakDir, 'db-'.preg_replace('/\W/', '_', $dbn).'.jsonl', json_encode(['table' => $t, 'col' => $col, 'pk' => $pk, 'old' => $old], JSON_UNESCAPED_UNICODE)."\n", true);
                $w = implode(' AND ', array_map(fn($k) => "`$k`=?", array_keys($pk)));
                $u = $pdo->prepare("UPDATE `$t` SET `$col`=? WHERE $w AND `$col`=?");
                $u->execute(array_merge([$newV], array_values($pk), [$old]));
                $chg += $u->rowCount(); $rep += $n;
            }
        }
    }
    echo "  Zeilen mit Treffern: $tot · geändert: $chg Zeilen ($rep Ersetzungen)\n\n";
}
if ($apply && is_dir($bakDir)) echo "Sicherung: ~/ch-backups/".basename($bakDir)." (außerhalb des Webroots, nicht öffentlich)\n";
echo "FERTIG\n";
