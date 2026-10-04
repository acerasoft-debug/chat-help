<?php
/** ChatHelp — dump-examplequestions (SADECE OKUR)
 *  HEDEF: "ornek sorulara tiklaninca AI hep Almanca cevap veriyor" + kartlarda
 *  hala oynama. Kesin kanit icin:
 *   [1] buildEx() TAM govdesi — dedup koruma var mi? tiklama ne gonderiyor
 *       (j.t Almanca orijinal mi, yoksa DOM'daki cevrilmis metin mi)?
 *   [2] Tiklamanin cagirdigi gercek gonderim fonksiyonu (addMsg/sendMsg/
 *       askQ/vb) — lang parametresi ekliyor mu, hangi degeri?
 *   [3] api.php'ye giden fetch(API+... action=aichat) cagrisinin TUM govdesi
 *       — body'de lang alani var mi, degeri neyden geliyor (UL mi sabit mi)?
 *   [4] Kart (.wcat) olusturan TUM yerlerde innerHTML/textContent — periyodik
 *       (interval icinde) yeniden yaziliyor mu yoksa bir kere mi?
 *   [5] CH_I18N_TRUCE canlida var mi (dogrulama)
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
@set_time_limit(60);
$src = @file_get_contents(__DIR__.'/index.php');
if ($src===false) exit("index.php okunamadi\n");
echo "dump-examplequestions BASLADI (index ".number_format(strlen($src))." B)\n";

function win($src,$needle,$pre,$post,$label,$max=4){
    echo "\n──────── $label ('$needle') ────────\n";
    $off=0; $n=0;
    while(($p=strpos($src,$needle,$off))!==false && $n<$max){
        $s=max(0,$p-$pre);
        echo "[@".$p."]\n".preg_replace('/[ \t]+/',' ',substr($src,$s,$pre+$post+strlen($needle)))."\n────\n";
        $off=$p+strlen($needle); $n++;
    }
    if(!$n) echo "(hic yok)\n";
    $tot=substr_count($src,$needle);
    if($tot>$max) echo "(toplam $tot, ilk $max gosterildi)\n";
}

/* [1] buildEx TAM govdesi (genis pencere — fonksiyon basindan tiklama handler'ina kadar) */
win($src,'function buildEx',150,2200,'[1] buildEx TAM govdesi (dedup + click handler dahil)',1);

/* [2] EX dizisi baslangici (Almanca orijinal metinler burada mi sabit) */
win($src,'var EX=[',80,400,'[2] EX dizisi basi',1);

/* [3] chat gonderim fonksiyonu: addMsg tanimi + action:"aichat" fetch govdesi */
win($src,'function addMsg',100,900,'[3a] addMsg tanimi',1);
win($src,"action=aichat'",300,50,"[3b] aichat fetch cagrisi cevresi (tek tirnak)",4);
win($src,'action=aichat"',300,50,'[3c] aichat fetch cagrisi cevresi (cift tirnak)',4);
win($src,"action:'aichat'",300,300,"[3d] aichat body govdesi (tek tirnak)",4);
win($src,'action:"aichat"',300,300,'[3e] aichat body govdesi (cift tirnak)',4);

/* [4] gonderim/send ana fonksiyonu (kullanicinin normal yazdigi da buradan gecer) */
win($src,'async function sendMsg',100,1200,'[4] sendMsg tanimi',1);
win($src,'function send(',100,900,'[4b] send() tanimi',1);

/* [5] kart olusturma yerlerinde interval-baglamli innerHTML/textContent yazimi */
win($src,'wcat-t">',150,150,'[5] wcat-t innerHTML yazim noktalari',10);

/* [6] dogrulama */
echo "\n──────── [6] marker kontrol ────────\n";
foreach (['CH_I18N_TRUCE','CH_STAB_FINAL3','CH_LANG_FORCE','CH_LANG_FORCE2','__chI18nFull'] as $m)
    echo str_pad($m,16).": ".(strpos($src,$m)!==false?"VAR":"yok")."\n";

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
