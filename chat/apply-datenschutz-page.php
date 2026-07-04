<?php
/**
 * ChatHelp — apply-datenschutz-page: Gizlilik politikasi sayfasini canliya alir.
 *   1) datenschutz.html -> web koku (/datenschutz.html + /datenschutz/index.html)
 *   2) Sitede kalan datenschutz@chat-help.de -> support@chat-help.de
 * KULLANIM: pull-updates.php?files=apply-datenschutz-page.php  -> otomatik silinir.
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-datenschutz-page BASLADI OK (PHP ".PHP_VERSION.")\n\n";

/* ===== 1) datenschutz@ -> support@ (kalan yerler) ===== */
$targets = [__DIR__.'/index.php', __DIR__.'/fresh-c0d1a039.php', dirname(__DIR__).'/index2.php'];
foreach ($targets as $f) {
    if (!file_exists($f)) { echo "[mail] yok, atlandi: ".basename($f)."\n"; continue; }
    $s = file_get_contents($f);
    $n = substr_count($s, 'datenschutz@chat-help.de');
    if (!$n) { echo "[mail] temiz: ".basename($f)."\n"; continue; }
    file_put_contents($f.'.bak-dsmail-'.date('Ymd-His'), $s);
    file_put_contents($f, str_replace('datenschutz@chat-help.de', 'support@chat-help.de', $s));
    echo "[mail] ".basename($f).": $n adet datenschutz@ -> support@ degistirildi\n";
}

/* ===== 2) datenschutz.html yaz ===== */
$root = dirname(__DIR__);
$html = <<<'DSHTML'
<!doctype html>
<!--
  ChatHelp — Datenschutzerklärung (premium, self-contained)
  Gerçek veriler canlı Impressum + sunucu kanıtlarıyla dolduruldu (Temmuz 2026):
  Acerasoft LLC (Delaware, EIN 61-2070643) · Serdar Bilgiç (İçerik sorumlusu/EU irtibat)
  · support@chat-help.de · Host Europe GmbH (GoDaddy Gruppe) hosting, GoDaddy E-Mail (MX secureserver.net).
  Not: EU-Vertreter posta adresi yayınlanınca 1. bölüme eklenmeli. Yayın öncesi avukat kontrolü önerilir.
-->
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Datenschutzerklärung · ChatHelp</title>
<style>
  :root{
    --bg:#141419; --card:#1c1c23; --card2:#22222b; --line:rgba(255,255,255,.08);
    --gold:#d4a84a; --gold2:#f0c860; --t1:#f5f5fa; --t2:#c7c7d6; --t3:#8f8fa6;
    --green:#42df94; --r:16px;
  }
  *{margin:0;padding:0;box-sizing:border-box}
  html{-webkit-text-size-adjust:100%}
  body{
    font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,system-ui,sans-serif;
    background:radial-gradient(1200px 600px at 50% -10%,#20202a 0%,var(--bg) 60%) no-repeat,var(--bg);
    color:var(--t1); line-height:1.65; -webkit-font-smoothing:antialiased;
    padding:0 18px 80px;
  }
  .wrap{max-width:820px;margin:0 auto}
  header.top{
    text-align:center;padding:46px 0 30px;
  }
  .badge{
    display:inline-flex;align-items:center;gap:8px;font-size:12px;font-weight:600;
    color:var(--gold);background:rgba(212,168,74,.08);border:1px solid rgba(212,168,74,.25);
    padding:6px 14px;border-radius:100px;margin-bottom:16px;letter-spacing:.3px;
  }
  h1{
    font-size:clamp(26px,5vw,38px);font-weight:800;letter-spacing:-.5px;line-height:1.15;
    background:linear-gradient(135deg,#fff 0%,var(--gold) 120%);
    -webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;
  }
  .sub{color:var(--t3);font-size:13.5px;margin-top:10px}
  section{
    background:linear-gradient(160deg,var(--card),var(--card2));
    border:1px solid var(--line);border-radius:var(--r);
    padding:22px 22px;margin:14px 0;
    box-shadow:0 6px 24px rgba(0,0,0,.22);
  }
  h2{
    font-size:17px;font-weight:700;color:var(--t1);margin-bottom:12px;
    display:flex;align-items:center;gap:10px;
  }
  h2 .num{
    flex:0 0 auto;width:30px;height:30px;border-radius:9px;font-size:13px;
    display:inline-flex;align-items:center;justify-content:center;font-weight:800;
    color:#1a1400;background:linear-gradient(135deg,var(--gold),var(--gold2));
  }
  p{color:var(--t2);font-size:14.5px;margin:8px 0}
  strong{color:var(--t1)}
  .kv{margin:10px 0}
  .kv .k{font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:var(--gold);font-weight:700;opacity:.85}
  .kv .v{color:var(--t2);font-size:14.5px}
  a{color:var(--gold2);text-decoration:none;border-bottom:1px solid rgba(240,200,96,.3)}
  a:hover{border-bottom-color:var(--gold2)}
  ol.steps{list-style:none;counter-reset:s;margin:6px 0}
  ol.steps li{
    counter-increment:s;position:relative;padding:9px 0 9px 42px;color:var(--t2);font-size:14.5px;
    border-bottom:1px solid var(--line);
  }
  ol.steps li:last-child{border-bottom:none}
  ol.steps li::before{
    content:counter(s);position:absolute;left:0;top:8px;width:26px;height:26px;border-radius:8px;
    display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;
    color:var(--gold);background:rgba(212,168,74,.1);border:1px solid rgba(212,168,74,.28);
  }
  .tbl{width:100%;overflow-x:auto;margin:10px 0;-webkit-overflow-scrolling:touch}
  table{width:100%;border-collapse:collapse;font-size:13.5px;min-width:420px}
  th,td{text-align:left;padding:10px 12px;border-bottom:1px solid var(--line)}
  th{color:var(--gold);font-weight:700;font-size:11.5px;text-transform:uppercase;letter-spacing:.5px}
  td{color:var(--t2)}
  tr:last-child td{border-bottom:none}
  .ok{color:var(--green);font-weight:700}
  .rights li{list-style:none;padding:8px 0;border-bottom:1px solid var(--line);color:var(--t2);font-size:14.5px}
  .rights li:last-child{border-bottom:none}
  .rights b{color:var(--t1)}
  .note{
    background:rgba(66,223,148,.06);border:1px solid rgba(66,223,148,.22);border-radius:12px;
    padding:12px 14px;color:#a7e9c4;font-size:13.5px;margin-top:8px;line-height:1.55;
  }
  footer{text-align:center;color:var(--t3);font-size:12px;margin-top:34px;line-height:1.7}
  @media (max-width:560px){ section{padding:18px 16px} body{padding:0 12px 60px} }
  @media print{ body{background:#fff;color:#000} section{box-shadow:none;border:1px solid #ddd;background:#fff} h1{-webkit-text-fill-color:#111} }
</style>
</head>
<body>
<div class="wrap">

  <header class="top">
    <div class="badge">🔒 DSGVO · Privacy by Design</div>
    <h1>Datenschutzerklärung</h1>
    <div class="sub">ChatHelp · Stand: Juli 2026</div>
  </header>

  <section>
    <h2><span class="num">1</span>Verantwortlicher</h2>
    <div class="kv"><div class="k">Betreiber</div><div class="v">Acerasoft LLC · 8 The Green, Suite B · Dover, Delaware 19901 · USA · Steuer-ID (EIN): 61-2070643</div></div>
    <div class="kv"><div class="k">EU-Vertreter (Art. 27 DSGVO) &amp; Inhaltlich Verantwortlicher</div><div class="v">Serdar Bilgiç · Kontakt: <a href="mailto:support@chat-help.de">support@chat-help.de</a></div></div>
    <div class="kv"><div class="k">Datenschutz-Kontakt</div><div class="v"><a href="mailto:support@chat-help.de">support@chat-help.de</a></div></div>
    <div class="kv"><div class="k">Aufsichtsbehörde</div><div class="v">Sie können sich an jede Datenschutzaufsichtsbehörde wenden, insbesondere an die Behörde Ihres Wohnsitzes (Art. 77 DSGVO).</div></div>
  </section>

  <section>
    <h2><span class="num">2</span>Unser Kernprinzip</h2>
    <p><strong>Privacy by Design:</strong> ChatHelp AI erhält <strong>nur anonyme Platzhalter</strong> ([VORNAME], [ADRESSE] etc.). Echte personenbezogene Daten verlassen niemals den EU-Server. Automatische Löschung nach 24 Stunden.</p>
  </section>

  <section>
    <h2><span class="num">3</span>Wie die KI-Verarbeitung funktioniert</h2>
    <ol class="steps">
      <li>Nutzer gibt Falldaten ein</li>
      <li>Der Server ersetzt alle personenbezogenen Daten durch Platzhalter</li>
      <li>Die KI erhält <strong>nur Platzhalter</strong> — kein Name, keine Adresse</li>
      <li>Der Server setzt die echten Daten lokal ein — nie nach außen</li>
    </ol>
  </section>

  <section>
    <h2><span class="num">4</span>Datenspeicherung</h2>
    <div class="tbl"><table>
      <tr><th>Datenart</th><th>Speicherort</th><th>Löschung</th></tr>
      <tr><td>Profildaten</td><td>Ihr Browser (lokal)</td><td>Jederzeit</td></tr>
      <tr><td>Dokumente</td><td>EU-Server</td><td>24 h automatisch</td></tr>
      <tr><td>Dokumente (Behalten)</td><td>EU-Server</td><td>Auf Anfrage</td></tr>
      <tr><td>Serverlog / IP</td><td>EU-Server</td><td>7 Tage, anonymisiert</td></tr>
      <tr><td>Zahlungsdaten</td><td>Stripe Europe</td><td>Steuerrecht (10 J.)</td></tr>
    </table></div>
  </section>

  <section>
    <h2><span class="num">5</span>Drittanbieter</h2>
    <div class="tbl"><table>
      <tr><th>Anbieter</th><th>Zweck</th><th>AVV</th></tr>
      <tr><td>Host Europe GmbH (GoDaddy Gruppe)</td><td>Hosting (DE)</td><td class="ok">✓ Abgeschlossen</td></tr>
      <tr><td>GoDaddy.com, LLC</td><td>E-Mail-Dienste</td><td class="ok">✓ Standard-DPA</td></tr>
      <tr><td>Stripe Europe Ltd.</td><td>Zahlungen</td><td class="ok">✓ Standard-DPA</td></tr>
      <tr><td>KI-Dienste</td><td>Dokumenterstellung</td><td>Nicht nötig (anonym)</td></tr>
    </table></div>
  </section>

  <section>
    <h2><span class="num">6</span>Ihre Rechte (DSGVO)</h2>
    <ul class="rights">
      <li><b>Auskunft (Art. 15):</b> Einsicht in gespeicherte Daten</li>
      <li><b>Löschung (Art. 17):</b> In der App unter <b>Konto</b> oder jederzeit online: <a href="https://chat-help.com/konto.html">chat-help.com/konto.html</a></li>
      <li><b>Berichtigung (Art. 16):</b> Auf Anfrage sofort</li>
      <li><b>Datenübertragbarkeit (Art. 20):</b> Auf Anfrage</li>
      <li><b>Beschwerde (Art. 77):</b> Bei jeder Datenschutzaufsichtsbehörde, z. B. der Behörde Ihres Wohnsitzes</li>
    </ul>
  </section>

  <section>
    <h2><span class="num">7</span>Cookies &amp; Tracking</h2>
    <p>ChatHelp verwendet <strong>kein Google Analytics</strong>, <strong>kein Facebook Pixel</strong> und <strong>kein Tracking</strong>. Nur technisch notwendige localStorage-Einträge (Sitzung, Einstellungen).</p>
  </section>

  <section>
    <h2><span class="num">8</span>B2B — Kanzleien &amp; Unternehmen</h2>
    <p><strong>Für Berufsgeheimnisträger (§ 203 StGB):</strong> Rechtsanwälte, Notare und Steuerberater können ChatHelp datenschutzkonform nutzen. Auf Anfrage stellen wir eine Geheimhaltungsvereinbarung nach § 203 Abs. 4 StGB aus.</p>
    <div class="tbl"><table>
      <tr><th>Feature</th><th>Standard</th><th>B2B</th></tr>
      <tr><td>Anonymisierung</td><td class="ok">✓</td><td class="ok">✓</td></tr>
      <tr><td>AVV (Art. 28 DSGVO)</td><td class="ok">✓ Host + Stripe</td><td class="ok">✓ Erweitert</td></tr>
      <tr><td>§ 203 StGB Vereinbarung</td><td>—</td><td class="ok">✓</td></tr>
      <tr><td>Eigene Subdomäne</td><td>—</td><td class="ok">✓</td></tr>
      <tr><td>Audit-Log</td><td>—</td><td class="ok">✓</td></tr>
    </table></div>
    <div class="note">Hinweis: ChatHelp ersetzt keine anwaltliche Rechtsberatung. Diese Datenschutzerklärung sollte vor Veröffentlichung anwaltlich geprüft werden.</div>
  </section>

  <footer>
    Acerasoft LLC · <a href="mailto:support@chat-help.de">support@chat-help.de</a><br>
    © 2026 ChatHelp. Alle Rechte vorbehalten.
  </footer>

</div>
</body>
</html>
DSHTML;

$written = 0;
if (@file_put_contents($root.'/datenschutz.html', $html) !== false) { echo "[sayfa] yazildi: $root/datenschutz.html\n"; $written++; }
if (!is_dir($root.'/datenschutz')) @mkdir($root.'/datenschutz', 0755);
if (is_dir($root.'/datenschutz') && @file_put_contents($root.'/datenschutz/index.html', $html) !== false) { echo "[sayfa] yazildi: $root/datenschutz/index.html\n"; $written++; }

echo "\n$written sayfa dosyasi yazildi.\n";
echo "\nURL'ler (gizli pencerede test et):\n";
echo "  https://chat-help.com/datenschutz.html\n";
echo "  https://chat-help.com/datenschutz/\n";
echo "\nBITTI.\n";
