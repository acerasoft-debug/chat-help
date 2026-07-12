<?php
/** ChatHelp — dump-plans (SADECE OKUR) — plan/fiyat/Stripe/kota altyapisini haritalar.
 *  Faz-2 plan yapisi (Basic 13,99 / Pro 29,99 / Elite 99,99) icin: mevcut plan
 *  isimleri, fiyat metinleri, Stripe price id/link'leri, kota sabitleri, plan
 *  gate'leri (nerede 'pro'/'elite'/'basic'/'free' kontrol ediliyor) nerede?
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
@set_time_limit(60);
$D=__DIR__;

function win($src,$needle,$pre,$post,$label,$max=6){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        $s=max(0,$p-$pre);
        echo "[@$p] ".preg_replace('/[ \t]+/',' ',substr($src,$s,$pre+$post+strlen($needle)))."\n────\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(yok)\n";
    $tot=substr_count($src,$needle); if($tot>$max) echo "(toplam $tot)\n";
}

foreach (['index.php','api.php','fall-api.php'] as $f) {
    $src=(string)@file_get_contents("$D/$f");
    if($src==='') { echo "=== $f: YOK/BOS ===\n"; continue; }
    echo "\n════════════════ $f (".number_format(strlen($src))." B) ════════════════\n";
    win($src,'13,99',60,80,'fiyat 13,99',4);
    win($src,'29,99',60,80,'fiyat 29,99',4);
    win($src,'99,99',60,80,'fiyat 99,99',4);
    win($src,'Basic',20,80,'Basic gecisleri',5);
    win($src,'Elite',20,80,'Elite gecisleri',5);
    win($src,'price_',10,60,'Stripe price_ id',6);
    win($src,'buy.stripe',10,90,'Stripe payment link',6);
    win($src,'FALL_QUOTA',10,120,'Fall kota sabiti',3);
    win($src,'basic5',10,80,'kota basic5/pro20/elite',4);
    win($src,'40',0,0,'—atla—',0); /* gurultuyu onle */
}

/* stripe dosyalari var mi */
echo "\n════════ Stripe dosyalari ════════\n";
foreach (['stripe-checkout.php','stripe-webhook.php','checkout.php','buy.php'] as $f)
    echo "  ".($f).": ".(is_file("$D/$f")?number_format(filesize("$D/$f"))." B":"yok")."\n";

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
