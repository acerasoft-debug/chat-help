<?php
/**
 * ChatHelp — Eski fiyatları temizle / güncelle
 * --------------------------------------------
 *   #1 Tarife modalı (#pm): eski Einzeln 2,99 / Monatsabo 9,99 / Jahresabo 79
 *      -> gerçek planlar: Basic 13,99 / Pro 29,99 / Elite 99,99 (Stripe'a bağlı)
 *   #2 showProModal (ölü "9,99€ Bald verfügbar" penceresi) -> Konto/tarifeler
 *   #3 TIER_CONFIG: staatliche form fiyatları -> Formular Basis 1,00€ / Plus 1,99€
 *      (yeni Stripe price ID'leri)
 *
 * KULLANIM: chat-help.com/chat/apply-prices.php -> sonra opcache-reset.php
 * İŞ BİTİNCE SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src = file_get_contents($file);
$start = $src;
file_put_contents($file . '.bak-prices-' . date('Ymd-His'), $src);
$report = [];

/* ── #1 Tarife modalı: eski 3 kartı gerçek planlarla değiştir ── */
$newGrid = <<<'HTML'
<div class="pm-grid">
    <div class="pc"><div class="pc-tier">Basic</div><div class="pc-amt">13,99&#8364;</div><div class="pc-per">/ Monat &#183; jederzeit k&#252;ndbar</div><div class="pc-sep"></div>
      <div class="pc-feats"><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>20 Dokumente / Monat</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>20 Staatl. Formulare / Mo</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>PDF-Download</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>KI-Recherche</div></div>
      <button class="pc-btn" onclick="stripeCheckout('basic')">Basic w&#228;hlen</button></div>
    <div class="pc feat"><div class="pc-tier">Pro</div><div class="pc-amt">29,99&#8364;</div><div class="pc-per">/ Monat &#183; jederzeit k&#252;ndbar</div><div class="pc-sep"></div>
      <div class="pc-feats"><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>50 Dokumente / Monat</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Unbegr. Staatl. Formulare</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Ton-Anpassung</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Strategie-Analyse</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>5 Fallanalysen / Monat</div></div>
      <button class="pc-btn g" onclick="stripeCheckout('pro')">Pro w&#228;hlen</button></div>
    <div class="pc"><div class="pc-tier">Elite</div><div class="pc-amt">99,99&#8364;</div><div class="pc-per">/ Monat &#183; jederzeit k&#252;ndbar</div><div class="pc-sep"></div>
      <div class="pc-feats"><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>300 Dokumente / Monat</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Unbegr. Staatl. Formulare</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>50 Fallanalysen / Monat</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Priorit&#228;t-Support</div></div>
      <button class="pc-btn" onclick="stripeCheckout('elite')">Elite w&#228;hlen</button></div>
  </div>
  <div style="text-align:center;font-size:12px;color:var(--t3);margin-top:12px">Einzeldokumente ab 1,99&#8364; &#183; Staatliche Formulare ab 1,00&#8364; &#8212; ohne Abo</div>
  <div style="text-align:center;font-size:11px
HTML;

$n1 = 0;
$src = preg_replace(
    '/<div class="pm-grid">.*?<div style="text-align:center;font-size:11px/s',
    $newGrid,
    $src, 1, $n1
);
$report[] = ['#1 Tarife modali (eski 2,99/9,99/79 silindi)', $n1];

/* ── #2 showProModal -> Konto tarifeleri ── */
$n2 = 0;
$src = preg_replace(
    '/function showProModal\(\)\{.*?document\.body\.appendChild\(m\);\s*\}/s',
    "function showProModal(){ gP('konto'); }",
    $src, 1, $n2
);
$report[] = ['#2 showProModal (9,99 Bald verfuegbar) -> Konto', $n2];

/* ── #3 TIER_CONFIG -> Formular Basis 1,00 / Plus 1,99 ── */
$oldTier = <<<'OLD'
const TIER_CONFIG = {
  einfach:  {price_id:'price_1Tf71jAJdvZPpak76GX3MZID',price:'1,99€',label:'Einfach'},
  standard: {price_id:'price_1Tf75LAJdvZPpak75eZIpoj6',price:'2,99€',label:'Standard'},
  komplex:  {price_id:'price_1Tf7H6AJdvZPpak7QSdWq61K',price:'3,99€',label:'Komplex'},
};
OLD;
$newTier = <<<'NEW'
const TIER_CONFIG = {
  einfach:  {price_id:'price_1TgyGdAJdvZPpak764pQThUw',price:'1,00€',label:'Formular Basis'},
  standard: {price_id:'price_1TgyGdAJdvZPpak764pQThUw',price:'1,00€',label:'Formular Basis'},
  komplex:  {price_id:'price_1TgyKuAJdvZPpak7i10q1jhJ',price:'1,99€',label:'Formular Plus'},
};
NEW;
$n3 = substr_count($src, $oldTier);
if ($n3 > 0) $src = str_replace($oldTier, $newTier, $src);
$report[] = ['#3 TIER_CONFIG -> Formular Basis/Plus (1,00/1,99)', $n3];

$changed = ($src !== $start);
if ($changed) file_put_contents($file, $src);

echo "ChatHelp — Fiyat güncelleme raporu\n==================================\n\n";
foreach ($report as [$l,$n]) echo ($n>0?"  ✓ ":"  ✗ ").$l."  →  $n\n";
echo "\n".($changed?"DURUM: index.php güncellendi. ✅\n":"DURUM: Değişiklik yok.\n");
echo "\nSONRA: opcache-reset.php aç.\n";
echo "Test: Tarife penceresi -> Basic 13,99 / Pro 29,99 / Elite 99,99, butonlar Stripe'a gidiyor.\n";
echo "Sil: rm apply-prices.php\n";
echo "\nNot: '✗ 0' satır varsa kalıp bulunamadı — bana haber ver.\n";
