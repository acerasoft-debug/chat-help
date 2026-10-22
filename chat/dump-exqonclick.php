<?php
/** ChatHelp — dump-exqonclick (SADECE OKUR)
 *  apply-exq-lang-fix anchor [1] canlida eslesmedi — chExAsk butonunun
 *  onclick/data-t yapisi apply-foto-gate.php kaynagindan FARKLI (aradan
 *  baska bir yama gecmis). Bu dump canlidaki TAM, HAM (bicimlendirilmemis)
 *  metni cikarir — kirpma/bosluk sikistirma YOK. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src = @file_get_contents(__DIR__.'/index.php');
if ($src===false) exit("index.php okunamadi\n");
echo "dump-exqonclick BASLADI (index ".number_format(strlen($src))." B)\n\n";

$p = strpos($src,'chExAsk');
if ($p===false) { echo "chExAsk hic bulunamadi!\n"; exit; }
echo "ilk 'chExAsk' konumu: @$p\n";
echo "toplam 'chExAsk' gecis sayisi: ".substr_count($src,'chExAsk')."\n\n";

/* TUM chExAsk gecislerinin HAM (bicimlendirilmemis) 200-char penceresini goster */
$off=0; $n=0;
while(($pos=strpos($src,'chExAsk',$off))!==false){
    $n++;
    $s=max(0,$pos-120);
    echo "──── gecis #$n @$pos (HAM, degistirilmemis) ────\n";
    echo bin2hex_visible(substr($src,$s,320))."\n\n";
    $off=$pos+7;
}

function bin2hex_visible($s){
    /* kontrol karakterlerini gorunur yap ama diger her seyi AYNEN birak */
    return str_replace(["\n","\t"], ['\\n',"\\t"], $s);
}

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
