<?php
/**
 * Protokoll: retail.log (Anwendung: Mail, Checkout, Spam, Webhook) und
 * admin-audit.log (jede Änderung im Panel: wer, wann, von welcher IP).
 * Filter nach Ereignis und Text, Zeitraum, Download der Rohdatei.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/_nav.php';

vr_admin_require();

$f = ($_GET['f'] ?? 'app') === 'audit' ? 'audit' : 'app';
$file = $f === 'audit' ? 'admin-audit.log' : 'retail.log';
$q = trim((string)($_GET['q'] ?? ''));
$what = trim((string)($_GET['what'] ?? ''));
$n = max(100, min(5000, (int)($_GET['n'] ?? 1000)));

if (($_GET['download'] ?? '') === '1') {
    $abs = vr_data_dir() . '/' . $file;
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $file . '-' . date('Y-m-d') . '.txt"');
    if (is_readable($abs)) readfile($abs);
    exit;
}

$rows = vr_log_tail($file, $n);
$kinds = [];
foreach ($rows as $r) { $k = (string)($r['what'] ?? $r['action'] ?? ''); $kinds[$k] = ($kinds[$k] ?? 0) + 1; }
arsort($kinds);
$rows = array_values(array_filter($rows, function ($r) use ($q, $what) {
    $k = (string)($r['what'] ?? $r['action'] ?? '');
    if ($what !== '' && $k !== $what) return false;
    if ($q !== '' && !str_contains(strtolower(json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''), strtolower($q))) return false;
    return true;
}));
$abs = vr_data_dir() . '/' . $file;
$size = is_file($abs) ? filesize($abs) : 0;
$bad = fn(string $k) => (bool)preg_match('/fail|error|blocked|spam|denied|throttl/i', $k);
$good = fn(string $k) => (bool)preg_match('/_ok$|paid|sent|done|saved/i', $k);
$keep = array_filter(['q' => $q, 'what' => $what, 'n' => $n !== 1000 ? $n : '']);
[$page, $pag] = vr_admin_paginate($rows, 200);

vr_admin_head('Protokoll', ['wide' => true, 'sub' => $file . ' · ' . number_format($size / 1024, 1, ',', '.') . ' KB · letzte ' . $n . ' Zeilen gelesen, ' . count($rows) . ' angezeigt',
    'actions' => '<a class="btn btn--ghost btn--sm" href="' . h(vr_url('admin/logs.php', ['f' => $f, 'download' => 1])) . '"><span>Datei herunterladen</span></a>']);
vr_admin_tabs(['app' => ['Anwendung', count(vr_log_tail('retail.log', 5000))], 'audit' => ['Panel-Änderungen', count(vr_log_tail('admin-audit.log', 5000))]], $f, 'logs.php', 'f', $keep);
?>
<form class="afilter" method="get"><input type="hidden" name="f" value="<?= h($f) ?>">
  <input type="search" name="q" value="<?= h($q) ?>" placeholder="Text, Bestell-ID, IP …">
  <select name="what"><option value="">Alle Ereignisse</option><?php foreach ($kinds as $k => $c): ?><option value="<?= h($k) ?>"<?= $what === $k ? ' selected' : '' ?>><?= h($k) ?> (<?= $c ?>)</option><?php endforeach; ?></select>
  <select name="n"><?php foreach ([500, 1000, 2000, 5000] as $x): ?><option value="<?= $x ?>"<?= $n === $x ? ' selected' : '' ?>><?= $x ?> Zeilen</option><?php endforeach; ?></select>
  <button class="btn btn--ghost btn--sm" type="submit"><span>Filtern</span></button>
</form>
<?php vr_admin_card_open('', '', 'acard--pad0'); ?>
<div class="alog" style="padding:8px">
<?php foreach ($page as $r): $k = (string)($r['what'] ?? $r['action'] ?? ''); $ts = isset($r['at']) && is_int($r['at']) ? date('d.m.Y H:i:s', $r['at']) : date('d.m.Y H:i:s', strtotime((string)($r['at'] ?? '')) ?: 0); ?>
  <div class="alog__r<?= $bad($k) ? ' is-bad' : ($good($k) ? ' is-ok' : '') ?>">
    <span><?= h($ts) ?></span>
    <span><b><?= h($k) ?></b><?php if ($f === 'audit'): ?><br><span class="asmall"><?= h((string)($r['who'] ?? '')) ?> · <a href="<?= h(vr_url('admin/visitors.php', ['ip' => (string)($r['ip'] ?? '')])) ?>"><?= h((string)($r['ip'] ?? '')) ?></a></span><?php endif; ?></span>
    <span class="amono asmall" style="overflow-wrap:anywhere"><?= h(json_encode($r['ctx'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '') ?></span>
  </div>
<?php endforeach; ?>
<?php if (!$page): ?><p class="asmall" style="padding:10px">Keine Einträge.</p><?php endif; ?>
</div>
<?php vr_admin_card_close(); echo $pag; ?>
<p class="anote">Die Anwendung schreibt nie Schlüssel, Kartendaten oder vollständige E-Mail-Adressen ins Protokoll. Der Rohtext liegt in <code><?= h($abs) ?></code>.</p>
<?php vr_admin_foot(); ?>
