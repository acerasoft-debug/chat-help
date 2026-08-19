<?php
/** ChatHelp — dump-wlc-dom (SADECE OKUR) — zen modu hala "tertemiz" degil:
 *  Ekran goruntusu kaniti: zen acikken hala GORUNEN ogeler var —
 *  "Gezielte Fragen · klares Ziel" USP karti, "Brief oder Bescheid
 *  fotografieren" karti, "Kategorien"/"Staatliche Formulare" bolumu +
 *  aktif-bayrak satiri, ve alt-sag "Module" yuzen pili. Ust bar (≡ ChatHelp
 *  Serdar simgeler) de gorunuyor. CH_ZEN_FULL CSS'i bunlarin hicbirini
 *  hedeflemiyor. Bu dump o ogelerin GERCEK id/class'larini bulur.
 *  [1] "Gezielte Fragen" USP karti html/class'i
 *  [2] "Brief oder Bescheid fotografieren" foto-CTA karti
 *  [3] Kategorien bolumu + Staatliche Formulare karti + aktif-bayrak satiri
 *  [4] alt-sag yuzen "Module" pili (ID)
 *  [5] ust bar yapisi (id="tb" or benzeri) — zen'de gizlenmeli mi kontrol
 *  [6] sag-ust dil-yardimi widget'i (flag+dropdown, hukuk degil)
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
if($idx==='') exit("index.php okunamadi\n");

function occ($src,$needle,$pre,$len,$label,$max=3){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n"," ⏎ ",substr($src,max(0,$p-$pre),$len))."\n\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}

echo "[1] Gezielte Fragen USP karti\n";
occ($idx,'Gezielte Fragen',-250,650,'baglam',2);
occ($idx,'präzisere Dokumente',-250,500,'baglam2 (varsa farkli yerde)',2);

echo "\n[2] foto-analiz CTA karti\n";
occ($idx,'Brief oder Bescheid fotografieren',-250,600,'baglam',2);
occ($idx,'KI erkennt Behörde',-200,400,'alt metin baglami',2);

echo "\n[3] Kategorien + Staatliche + aktif bayrak\n";
occ($idx,'>Kategorien<',-200,500,'Kategorien basligi',2);
occ($idx,'ausblenden',-150,350,'ausblenden toggle',2);
occ($idx,'Aktiv:',-200,500,'aktif bayrak satiri',2);
occ($idx,'Staatliche Formulare',-200,500,'staatliche karti baslangici',2);

echo "\n[4] yuzen Module pili\n";
occ($idx,'>Module<',-300,600,'Module pil metni',2);
occ($idx,'id="ch-hub-fab',-100,300,'hub fab id (tahmini)',2);

echo "\n[5] ust bar yapisi\n";
occ($idx,'id="tb"',-100,400,'topbar container',2);
occ($idx,'ChatHelp</',-300,500,'topbar baslik cevresi',1);

echo "\n[6] sag-ust dil-yardimi widget (hukuk DEGIL)\n";
occ($idx,'tb-flag-emoji',-200,500,'flag widget cevresi (guncel hali)',3);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
