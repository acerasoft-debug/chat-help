<?php
/**
 * ChatHelp ops — fba-daily: Amazon-FBA-Tagesbericht sofort auslösen und Stand zeigen
 * ===================================================================================
 * Ohne Server-Cron läuft fba_daily_tick() beim ersten Web-Aufruf nach 06:00 (Berlin). Dieses Skript:
 *   • zeigt, ob ein Keepa-Plattformschlüssel vorhanden ist (nur ja/nein, nie der Schlüssel), Konten mit Bericht, Beobachtungslisten
 *   • args {"run":"1"}  → Bericht jetzt versenden (force; Konten, die heute schon einen Bericht bekamen, werden übersprungen)
 * Keine E-Mail-Adressen im Log (nur Konto-IDs).
 */
header('Content-Type: text/plain; charset=UTF-8');
define('FBA_NO_TICK', true);
require_once __DIR__.'/fba-lib.php';
fba_bootstrap(); $p=th_db();
echo "ChatHelp — fba-daily  (".date('c').")\n\n";
echo "Keepa-Plattformschlüssel: ".(fba_platform_key()!=='' ? 'vorhanden' : 'FEHLT')."\n";
$rows=$p->query("SELECT s.account_id, s.report_on, s.last_report, (SELECT COUNT(*) FROM fba_watch w WHERE w.account_id=s.account_id) AS n FROM fba_settings s ORDER BY s.account_id")->fetchAll(PDO::FETCH_ASSOC);
echo "Konten mit FBA-Einstellungen: ".count($rows)."\n";
foreach($rows as $r) echo "  #{$r['account_id']}  Bericht ".($r['report_on'] ? 'an' : 'aus')."  Liste {$r['n']}  zuletzt ".($r['last_report'] ?: '–')."  Lizenz ".(fba_enabled((int)$r['account_id']) ? 'aktiv' : 'NEIN')."\n";
if(($_GET['run'] ?? '')==='1'){ echo "\nBericht wird versendet …\n"; print_r(fba_daily_tick(600,true)); }
echo "\nBİTTİ\n";
