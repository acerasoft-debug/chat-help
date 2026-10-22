<?php
/** ChatHelp — dump-chatdoc (SADECE OKUR) — sohbet-ici [[DOC]] uretimi haritasi:
 *  Ekran goruntusu kaniti: sohbette "Vollmacht" istendi, AI Turkce yazdi
 *  (hukuk=DE oldugu halde), belge karti + "PDF olarak kaydet"/"Als PDF
 *  speichern" butonu cikti ama PDF calismiyor; kullanicinin adi zaten
 *  kayitli olmasina ragmen chat'te tekrar soruldu; belge URETIMINDE isim
 *  YANLIS cikti (Luda Soku / Sonyheim — kullanicinin yazdigi Bülent Duru /
 *  Sontheim degil); hicbir odeme/plan kontrolu gorulmedi.
 *  [1] [[DOC]] isaretlemesi client-tarafinda nerede/nasil algilaniyor,
 *      kart (BELGENIZ/IHR DOKUMENT) nasil olusuyor, PDF butonunun onclick'i
 *  [2] api.php doAiChat() TAM govdesi: dil talimati, profil kullanimi,
 *      plan/odeme kontrolu var mi
 *  [3] CH_AIROUTE / CH_SUGG_FIX yamalarinin bu akisla kesisimi
 *  [4] makePDF fonksiyonu (var olan PDF motoru) bu karttan cagirilabiliyor mu
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
$api=(string)@file_get_contents(__DIR__.'/api.php');
if($idx==='') exit("index.php okunamadi\n");

function occ($src,$needle,$pre,$len,$label,$max=3){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".substr($src,max(0,$p-$pre),$len)."\n[/end]\n\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}

echo "════ [1] [[DOC]] client algilama + kart + PDF butonu ════\n";
occ($idx,'[[DOC]]',30,900,'[[DOC]] isareti kullanildigi yerler',4);
occ($idx,'BELGENIZ',30,700,'BELGENIZ karti (buyuk harf, sohbet-ici)',2);
occ($idx,'PDF olarak kaydet',200,500,'PDF butonu cevresi',2);
occ($idx,'Als PDF speichern',200,500,'PDF butonu (DE) cevresi',2);

echo "\n════ [2] api.php doAiChat() TAM govdesi ════\n";
$p=strpos($api,'function doAiChat');
echo $p!==false ? substr($api,$p,3500)."\n" : "(YOK)\n";

echo "\n════ [3] makePDF cagrilari (bu kartta var mi) ════\n";
occ($idx,'makePDF(',100,300,'makePDF cagri noktalari',10);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
