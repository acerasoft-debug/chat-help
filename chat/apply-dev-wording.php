<?php
/**
 * ChatHelp — apply-dev-wording (CH_DEV_WORDING) — Doc-Dev metin duzeltmesi
 *  Kullanici geri bildirimi: buton "Dokument erstellen" diyor ama modul aninda
 *  bitmis belge uretmiyor, TASLAK HAZIRLIYOR — dogru fiil "vorbereiten".
 *  6 dilde sekme (t1), buton (go1) ve bekleme (busy) metinleri guncellenir:
 *   de: Vorbereiten / Dokument vorbereiten / Wird vorbereitet…
 *   en: Prepare / Prepare document / Preparing…
 *   tr: Hazırla / Belgeyi hazırla / (Hazırlanıyor… zaten dogru)
 *   es: Preparar / Preparar documento / Preparando…
 *   fr: Préparer / Préparer le document / En préparation…
 *   it: Preparare / Prepara documento / In preparazione…
 *  Modul ADI ("Dokument erstellen & verbessern") taninirlik icin degismez.
 * KULLANIM: pull2.php?key=...&files=apply-dev-wording.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-dev-wording BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_DEV_WORDING')!==false) exit("Zaten ekli (CH_DEV_WORDING).\n");
if (strpos($src,'CH_DEVFIX2')===false) exit("HATA: Doc-Dev v3 (CH_DEVFIX2) yok.\n");

$edits = [
  /* [dil, eski, yeni] — hepsi CH_DEVFIX2 bundle'inin birebir baytlari */
  ['de t1',  't1:"✍️ Erstellen"',                           't1:"✍️ Vorbereiten"'],
  ['de go1', 'go1:"Dokument erstellen",',                    'go1:"Dokument vorbereiten",'],
  ['de busy','go2:"Dokument verbessern",busy:"Wird erstellt…"','go2:"Dokument verbessern",busy:"Wird vorbereitet…"'],
  ['en t1',  't1:"✍️ Create"',                               't1:"✍️ Prepare"'],
  ['en go1', 'go1:"Create document",',                       'go1:"Prepare document",'],
  ['en busy','go2:"Improve document",busy:"Working…"',       'go2:"Improve document",busy:"Preparing…"'],
  ['tr t1',  't1:"✍️ Oluştur"',                              't1:"✍️ Hazırla"'],
  ['tr go1', 'go1:"Belge oluştur",',                         'go1:"Belgeyi hazırla",'],
  ['es t1',  't1:"✍️ Crear"',                                't1:"✍️ Preparar"'],
  ['es go1', 'go1:"Crear documento",',                       'go1:"Preparar documento",'],
  ['es busy','go2:"Mejorar documento",busy:"Creando…"',      'go2:"Mejorar documento",busy:"Preparando…"'],
  ['fr t1',  't1:"✍️ Créer"',                                't1:"✍️ Préparer"'],
  ['fr go1', 'go1:"Créer le document",',                     'go1:"Préparer le document",'],
  ['fr busy','go2:"Améliorer le document",busy:"En cours…"', 'go2:"Améliorer le document",busy:"En préparation…"'],
  ['it t1',  't1:"✍️ Creare"',                               't1:"✍️ Preparare"'],
  ['it go1', 'go1:"Crea documento",',                        'go1:"Prepara documento",'],
  ['it busy','go2:"Migliora documento",busy:"In corso…"',    'go2:"Migliora documento",busy:"In preparazione…"'],
];

$fail=[];
foreach ($edits as $e) {
    $c=substr_count($src,$e[1]);
    if ($c!==1) { echo "  ✗ {$e[0]} anchor ($c)\n"; $fail[]=$e[0]; continue; }
    $src=str_replace($e[1],$e[2],$src);
    echo "  ✓ {$e[0]}\n";
}
if ($fail) { echo "\nHATA: eslesmeyen: ".implode(', ',$fail)." — index DEGISTIRILMEDI.\n"; exit; }

/* iz birak (idempotency) */
$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$src = substr($src,0,$pos)."<!-- CH_DEV_WORDING -->\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'dw').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
$bk=@file_put_contents($file.'.bak-devword-'.date('Ymd-His'), @file_get_contents($file));
if ($bk===false) echo "  ⚠ yedek yazilamadi — devam\n";
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI: ".var_export($w,true)."\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_DEV_WORDING')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_DEV_WORDING diskte (".strlen($chk)." bayt)\n";
echo "\n✓ CH_DEV_WORDING uygulandi: 'vorbereiten' fiili 6 dilde tutarli.\n";
