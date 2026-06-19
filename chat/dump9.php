<?php
/**
 * ChatHelp — dump9 (SADECE OKUR): foto sistemi gerçek durum
 * - Hangi katmanlar ekli?
 * - Ana sayfa foto handler'ının TAM kodu (neden çalışmıyor?)
 * - Foto tetik butonu
 * - genDoc photos satırı
 * KULLANIM: curl -s -A "Mozilla/5.0" "https://chat-help.com/chat/dump9.php"
 */
header('Content-Type: text/plain; charset=UTF-8');
$idx = @file_get_contents(__DIR__ . '/index.php');
if ($idx === false) exit("index.php yok\n");
function R($s,$needle,$before,$len,$lbl){
    echo "\n──── $lbl ────\n";
    $p=strpos($s,$needle);
    if($p===false){echo "(BULUNAMADI: '$needle')\n";return;}
    echo substr($s,max(0,$p-$before),$len)."\n";
}

echo "### EKLİ KATMANLAR ###\n";
foreach([
  'ch-mega-layer'=>'mega (AI gizle/stop/ton)',
  'ch-photo2-layer'=>'photo2 (eski per-soru)',
  'ch-attach-plus'=>'attach-plus (eski +)',
  'ch-attach-v2'=>'attach v2 (tek yükleme)',
  'ch-autofill'=>'autofill',
  'ch-fall-premium'=>'fall premium',
  'ch-intake-layer'=>'intake overlay',
  'ch-q-photo'=>'photo2 css',
] as $m=>$d){
  echo (strpos($idx,$m)!==false?"  ✓ ":"  ✗ ").$d." ($m)\n";
}

echo "\n### genDoc photos satırı ###\n";
R($idx,'photos:Object.values(window._chPhotos',120,200,'genDoc photos gönderimi');

echo "\n### ANA SAYFA FOTO HANDLER (tam akış) ###\n";
// foto input ve onu işleyen fonksiyon
R($idx,'accept="image/*,application/pdf"',-120,260,'foto input HTML + tetik');
R($idx,'action=analyze_photo',-1400,2600,'foto handler fonksiyonu (analyze_photo çevresi geniş)');

echo "\n### Foto tetik butonu (onclick) ###\n";
foreach(['onclick="document.getElementById','.click()','photoBtn','id="photo','foto','Foto','camera','kamera'] as $k){
  $p=strpos($idx,$k); echo "  '$k' -> ".($p===false?'yok':"VAR @".$p)."\n";
}
R($idx,'.click()',-160,260,'ilk .click() (foto tetik olabilir)');

echo "\n=== BİTTİ. SİL: rm dump9.php ===\n";
