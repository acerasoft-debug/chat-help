<?php
/** ChatHelp — dump-visa2 (SADECE OKUR) — Vize Asistani entegrasyon noktalari.
 *  [1] checklist RENDER fonksiyonu (grp/items nasil ciziliyor — @613300 civari)
 *  [2] bir belgenin SORU akisini baslatan fonksiyon (openDoc/startDoc/startQ/runDoc)
 *  [3] vize modullerinin menuye/hub'a nasil giris ekledigi (sidebar item / wcat / cat)
 *  [4] checklist icindeki 'chathelp' item'a tiklayinca ilgili doc nasil aciliyor
 *  [5] makePDF ile kurumsal belge uretimi ornek cagrisi (module icinden)
 *  [6] panel/gP sistemi: yeni tam-ekran panel nasil aciliyor (gP / pnl-*)
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
if($idx==='') exit("index.php okunamadi\n");

function slice($s,$from,$len,$label){ echo "\n──────── $label (@$from) ────────\n".substr($s,max(0,$from),$len)."\n[/end]\n"; }
function raw($src,$needle,$pre,$len,$label,$max=6){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0;$n=0;$tot=substr_count($src,$needle);
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        echo "[@$p] ".str_replace("\n","⏎",substr($src,max(0,$p-$pre),$len))."\n";
        $off=$p+strlen($needle);$n++;
    }
    if(!$n) echo "(YOK)\n"; echo "(toplam $tot)\n";
}

echo "════ [1] checklist RENDER (grp/items) ════\n";
slice($idx,613250,1400,'checklist render @613250');

echo "\n════ [2] belge SORU akisi baslatma ════\n";
raw($idx,'function openDoc',0,160,'openDoc',2);
raw($idx,'function startDoc',0,160,'startDoc',2);
raw($idx,'function startQ',0,160,'startQ',2);
raw($idx,'function runDoc',0,160,'runDoc',2);
raw($idx,'function openForm',0,160,'openForm',2);
raw($idx,'DOCS[',-10,70,'DOCS[ erisim',6);

echo "\n════ [3] vize modulu menu/hub girisi ════\n";
raw($idx,'CHECKLIST_KEY',-20,120,'CHECKLIST_KEY kullanimi',6);
raw($idx,'VC_ORDER',-10,120,'VC_ORDER kullanimi',4);
raw($idx,'sb-cat',-20,90,'sb-cat (sidebar kategori)',5);
raw($idx,'addCat',-20,100,'addCat / kategori ekleme',5);

echo "\n════ [4] checklist 'chathelp' item -> doc acma ════\n";
raw($idx,'src:"chathelp"',-10,60,'chathelp item',3);
raw($idx,'.doc)',-40,90,'item.doc kullanimi',6);

echo "\n════ [5] makePDF kurumsal cagri ornegi ════\n";
raw($idx,'chPrintDoc(',-80,120,'chPrintDoc cagrisi (form ciktisi)',5);
raw($idx,'vfh-s',-30,120,'visa form header (vfh)',3);

echo "\n════ [6] gP panel sistemi ════\n";
raw($idx,'function gP(',0,320,'gP tanimi',1);
raw($idx,'pnl-',-6,50,'pnl- id ornekleri',10);

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
