<?php
/**
 * ChatHelp — apply-fabfix (CH_FABFIX)
 *  KOK NEDEN BULUNDU: CH_MODHUB_NAV'daki fabVis() fonksiyonu ".pnl.on" secicisini
 *  ana chat ekraninin KENDISI de kullandigi icin HER ZAMAN "bir panel acik" sanip
 *  🧩 Module butonunu surekli gizliyordu. Bu yama o mantigi canli dosyadan cikarir;
 *  buton artik kosulsuz her zaman gorunur (CSS varsayilani zaten display:flex).
 * KULLANIM: pull-updates.php?files=apply-fabfix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fabfix BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_FABFIX') !== false) exit("Zaten ekli (CH_FABFIX).\n");

$broken = 'function fabVis(){
    try{
      var b=document.getElementById("ch-hub-fab"); if(!b) return;
      /* acik bir panel varsa gizle (chat/ana sayfa disi) */
      var open=document.querySelector(".pnl.on")||document.querySelector(\'[class*="pnl"][class*="on"]\');
      b.style.display=open?"none":"flex";
    }catch(e){}
  }

  function tick(){ addHubNav(); addFab(); fabVis(); addCardRobust(); }';

$fixed = '/* CH_FABFIX — fabVis() KOK NEDENDI: ".pnl.on" ana chat ekraninda da hep eslesiyordu, buton hep gizleniyordu. Kaldirildi. */
  function tick(){ addHubNav(); addFab(); addCardRobust(); }';

if (strpos($src, $broken) === false) {
    echo "UYARI: beklenen bozuk blok bulunamadi (belki farkli bicimde) — alternatif yontem deneniyor.\n";
    // esnek yol: sadece fabVis cagrisini ve tanimini ayri ayri kaldir
    $c1 = 0; $c2 = 0;
    $src = preg_replace('/function fabVis\(\)\{.*?\n  \}\n\n  /s', '', $src, 1, $c1);
    $src = str_replace('addFab(); fabVis(); addCardRobust();', 'addFab(); addCardRobust();', $src, $c2);
    if (!$c1 && !$c2) { echo "HATA: fabVis izine hic rastlanmadi — dosya degistirilmedi.\n"; exit; }
    echo "  ✓ esnek yontemle kaldirildi (fn:$c1, cagri:$c2)\n";
} else {
    $src = str_replace($broken, $fixed, $src, $c);
    echo "  ✓ bozuk fabVis() blogu bulundu ve kaldirildi ($c nokta)\n";
}

$src = str_replace('</head>', "<!-- CH_FABFIX -->\n</head>", $src, $mc);
if (!$mc) { $src = "<!-- CH_FABFIX -->\n" . $src; }

$tmp = tempnam(sys_get_temp_dir(), 'idx') . '.php';
file_put_contents($tmp, $src);
$lo = []; $rc = 0;
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc);
@unlink($tmp);
if ($rc !== 0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  " . implode("\n  ", $lo) . "\n"; exit; }

file_put_contents($file . '.bak-fabfix-' . date('Ymd-His'), file_get_contents($file));
file_put_contents($file, $src);
echo "\n✓ CH_FABFIX uygulandi. 🧩 Module butonu artik kosulsuz her zaman gorunur.\n";
echo "Test: ana sayfada sag altta altin '🧩 Module' butonu HEMEN gorunmeli (yenile/kapat-ac).\n";
