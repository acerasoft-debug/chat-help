<?php
/** ChatHelp — dump-sidebar-dom (SADECE OKUR)
 *  Sol menu KESIN sabitleme icin: openFall butonunun KAPSAYICISI ve o
 *  kapsayicinin statik cocuklari (tag/class/id/onclick) DOM sirasiyla.
 *  Ayrica enforceOrder'in mevcut hali + sb yapisi. Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=@file_get_contents(__DIR__.'/index.php');
if($idx===false) exit("index.php okunamadi\n");

function W($src,$needle,$before,$len,$label){
    echo "──────── $label ────────\n";
    $p=strpos($src,$needle);
    if($p===false){ echo "  (yok: '$needle')\n\n"; return; }
    echo "[@$p]\n".substr($src,max(0,$p-$before),$len)."\n---\n\n";
}

/* 1) #sb / sidebar statik govde — logo'dan openFall sonrasina kadar genis kesit */
W($idx,'openFall()',-1400,1900,"openFall butonu KAPSAYICI baglami (genis)");

/* 2) sb-nav / sb-photo-btn kapsayici sinifi */
foreach(['id="sb"','class="sb-nav"','class="sb-nav-item','sb-docs-label','sb-photo-btn','class="sb"'] as $k)
    echo "  '$k' -> ".substr_count($idx,$k)." kez\n";
echo "\n";

/* 3) enforceOrder mevcut hali (menufix sonrasi) */
W($idx,'function enforceOrder',0,1400,"enforceOrder TAM (guncel)");

/* 4) addNav'lar navlari nereye ekliyor (parentNode ipucu) */
W($idx,'ref.parentNode.insertBefore(nav',-260,360,"nav ekleme (parent ipucu)");
W($idx,'sb.appendChild(nav)',-160,220,"fallback: sb.appendChild");

echo "\n=== BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-sidebar-dom.php ===\n";
