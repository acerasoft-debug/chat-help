<?php
/**
 * ChatHelp — apply-onboard-text2 (CH_ONBOARD_TEXT2)
 *  Onboarding turunun (CH_ONBOARD_TOUR) metnini duzeltir/gelistirir.
 *  Eski 1. adim "Form yok, sadece kendi kelimelerinle yaz" YANLISTI — ChatHelp
 *  bir cok sey icin hazir sablon/form doldurur ve rehberlik eder (resmi yazi,
 *  itiraz, sozlesme, CV, sure, rechner...). Uc adim da daha aciklayici hale gelir.
 *  api YOK — sadece index.php (deploy edilmis STEPS metni) str_replace edilir.
 * KULLANIM: pull-updates.php?files=apply-onboard-text2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-onboard-text2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = @file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_ONBOARD_TEXT2') !== false) exit("Zaten ekli (CH_ONBOARD_TEXT2).\n");
if (strpos($src, 'Erzählen Sie, was los ist') === false) exit("HATA: onboarding STEPS metni bulunamadi (CH_ONBOARD_TOUR deploy edilmis mi?).\n");
file_put_contents($file . '.bak-obtext2-' . date('Ymd-His'), $src);

$n = 0;
$rep = [];

/* ── DE ── */
$rep[] = [
  '{ic:"💬",t:"Erzählen Sie, was los ist",s:"Kein Formular, kein Fachjargon nötig — schreiben Sie einfach in eigenen Worten, was passiert ist."}',
  '{ic:"🗂️",t:"Für fast alles das passende Werkzeug",s:"Amtliches Schreiben, Widerspruch, Vertrag, Lebenslauf, Frist oder Rechner — ChatHelp führt Sie mit einfachen Fragen durch fertige Vorlagen. Kein Fachjargon nötig; wo etwas fehlt, schreiben Sie es einfach in eigenen Worten."}'
];
$rep[] = [
  '{ic:"⚖️",t:"Das richtige Recht — automatisch",s:"ChatHelp erkennt Ihr Land und wendet die passenden Gesetze an, von Deutschland bis in viele weitere Länder."}',
  '{ic:"⚖️",t:"Das richtige Recht — geprüft",s:"ChatHelp erkennt Ihr Land, wendet die passenden Gesetze an und nennt nur belegte, sichere Paragraphen. Die KI prüft jeden Entwurf, bevor Sie ihn erhalten."}'
];
$rep[] = [
  '{ic:"📄",t:"PDF erhalten, unterschreiben, senden",s:"Ihr fertiges Schreiben liegt in Sekunden vor — Sie drucken, unterschreiben und schicken es ab."}',
  '{ic:"📄",t:"Fertiges PDF — unterschreiben, senden",s:"Ihr Schreiben liegt in Sekunden druckfertig vor. Name und Adresse bleiben privat und werden nie an die KI gesendet."}'
];

/* ── TR ── */
$rep[] = [
  '{ic:"💬",t:"Derdinizi anlatın",s:"Form doldurmaya, hukuk terimi bilmeye gerek yok — olanı kendi kelimelerinizle yazın, yeter."}',
  '{ic:"🗂️",t:"Neredeyse her iş için doğru araç",s:"Resmi yazı, itiraz, sözleşme, özgeçmiş, süre takibi ya da hesaplama — ChatHelp basit sorularla hazır şablonlar üzerinden sizi yönlendirir. Hukuk terimi bilmeye gerek yok; eksik kalan yeri kendi kelimelerinizle yazarsınız."}'
];
$rep[] = [
  '{ic:"⚖️",t:"Doğru hukuk — otomatik",s:"ChatHelp ülkenizi tanır ve o ülkenin kanunlarını uygular — Almanya dahil birçok ülke için."}',
  '{ic:"⚖️",t:"Doğru hukuk — denetimli",s:"ChatHelp ülkenizi tanır, o ülkenin kanunlarını uygular ve yalnızca güvenli, gerçek maddeleri belirtir. Yapay zeka her taslağı siz almadan önce kontrol eder."}'
];
$rep[] = [
  '{ic:"📄",t:"PDF\'inizi alın, imzalayın, gönderin",s:"Hazır belgeniz saniyeler içinde önünüzde — yazdırın, imzalayın, gönderin."}',
  '{ic:"📄",t:"Hazır PDF — imzalayın, gönderin",s:"Belgeniz saniyeler içinde baskıya hazır. Adınız ve adresiniz gizli kalır, asla yapay zekaya gönderilmez."}'
];

/* ── EN ── */
$rep[] = [
  '{ic:"💬",t:"Tell us what happened",s:"No forms, no legal jargon — just describe it in your own words."}',
  '{ic:"🗂️",t:"The right tool for almost anything",s:"Official letters, objections, contracts, a CV, deadlines or calculators — ChatHelp guides you through ready templates with simple questions. No legal jargon; where something is missing, just write it in your own words."}'
];
$rep[] = [
  '{ic:"⚖️",t:"The right law — automatically",s:"ChatHelp detects your country and applies the matching legal system."}',
  '{ic:"⚖️",t:"The right law — checked",s:"ChatHelp detects your country, applies the matching laws and cites only safe, established provisions. The AI reviews every draft before you get it."}'
];
$rep[] = [
  '{ic:"📄",t:"Get your PDF, sign, send it",s:"Your finished letter is ready in seconds — print, sign and send."}',
  '{ic:"📄",t:"Finished PDF — sign and send",s:"Your letter is print-ready in seconds. Your name and address stay private and are never sent to the AI."}'
];

foreach ($rep as $idx => $pair) {
    $c = 0; $src = str_replace($pair[0], $pair[1], $src, $c); $n += $c;
    echo ($c ? "  ✓ [$idx] guncellendi\n" : "  ✗ [$idx] anchor yok: " . substr($pair[0], 0, 40) . "...\n");
}

if ($n < 9) { echo "\nHATA: 9 adimdan yalnizca $n eslesti — index.php DEGISTIRILMEDI.\n"; exit; }

/* marker (steps() fonksiyonu civarina yorum) */
$src = str_replace('function steps(){ return STEPS[UIL()]||STEPS.en; }',
                   '/*CH_ONBOARD_TEXT2*/function steps(){ return STEPS[UIL()]||STEPS.en; }',
                   $src, $mk);
if (!$mk) { $src = str_replace('var STEPS={', '/*CH_ONBOARD_TEXT2*/var STEPS={', $src); }

$tmp = tempnam(sys_get_temp_dir(), 'obt') . '.php';
file_put_contents($tmp, $src);
$lo = []; $rc = 0;
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc);
@unlink($tmp);
if ($rc !== 0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  " . implode("\n  ", $lo) . "\n"; exit; }
file_put_contents($file, $src);
echo "\n✓ CH_ONBOARD_TEXT2 uygulandi ($n adim). Onboarding metni artik dogru ve aciklayici.\n";
