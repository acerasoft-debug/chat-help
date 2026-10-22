<?php
/** ChatHelp — dump-card (SADECE OKUR) — sonuc karti butonlari + sizinti + email/PDF.
 *  [1] "NUR BELEGTE NUTZERANGABEN" / "nichts erfinden" / "nicht angegeben" KAYNAGI
 *      (api.php prompt sizmasi mi, research alani mi)
 *  [2] kart butonlari: Behalten / Überarbeiten / Neues Dokument / Meine Anträge onclick
 *  [3] E-Mail butonu akisi (mailto — su an metin) + attach imkani
 *  [4] sunucuda PDF kutuphanesi (dompdf/mpdf/tcpdf/wkhtmltopdf) + mail() / SMTP
 *  [5] research alani nasil dolduruluyor (doGenerate research)
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$idx=(string)@file_get_contents("$D/index.php");
$api=(string)@file_get_contents("$D/api.php");

function raw($src,$needle,$pre,$len,$label,$max=6){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","⏎",substr($src,max(0,$p-$pre),$len))."\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}

echo "════ [1] prompt sizmasi kaynagi ════\n";
raw($api,'BELEGTE',-40,160,'BELEGTE (api)',5);
raw($api,'nichts erfinden',-60,140,'nichts erfinden (api)',4);
raw($idx,'BELEGTE',-40,140,'BELEGTE (idx)',4);
raw($api,'nicht angegeben',-40,120,'nicht angegeben (api)',5);
raw($api,'Beteiligte Stelle',-40,120,'Beteiligte Stelle (api)',4);

echo "\n════ [2] kart butonlari onclick ════\n";
raw($idx,'Behalten',-90,150,'Behalten',5);
raw($idx,'Überarbeiten',-90,150,'Uberarbeiten',5);
raw($idx,'berarbeiten',-90,140,'berarbeiten (umlautsuz)',4);
raw($idx,'Neues Dokument',-90,140,'Neues Dokument',4);
raw($idx,'Meine Anträge',-90,140,'Meine Antrage',4);
raw($idx,'function keepDoc',0,160,'keepDoc',2);
raw($idx,'action=keepdoc',-40,120,'keepdoc cagrisi',3);

echo "\n════ [3] E-Mail butonu ════\n";
raw($idx,'mailto:',-80,140,'mailto',5);
raw($idx,"'E-Mail'",-60,120,'E-Mail buton metni',4);

echo "\n════ [4] sunucu PDF kutuphanesi + mail ════\n";
foreach(['dompdf','mpdf','Mpdf','tcpdf','TCPDF','wkhtmltopdf','Dompdf'] as $k){ echo "  '$k': api.php=".substr_count($api,$k)."\n"; }
foreach(['vendor/autoload.php','composer.json'] as $f){ echo "  dosya $f: ".(is_file("$D/$f")?'VAR':'yok')."\n"; }
$vd="$D/vendor"; echo "  vendor/ dizini: ".(is_dir($vd)?'VAR':'yok')."\n";
if(is_dir($vd)){ $ls=@scandir($vd); echo "   -> ".implode(', ',array_slice(array_diff((array)$ls,['.','..']),0,30))."\n"; }
raw($api,'mail(',-40,120,'mail() kullanimi (api)',4);
raw($api,'smtp',-30,100,'smtp (api)',3);
raw($idx,'action=email',-40,120,'email endpoint cagrisi (idx)',3);

echo "\n════ [5] research alani (doGenerate) ════\n";
raw($api,"'research'",-60,120,'research alani',6);
raw($api,'$research',-30,120,'$research atamasi',8);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
