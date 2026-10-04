<?php
/**
 * ChatHelp — dump-doclang (SADECE OKUR) — KURAL: belge/dilekce HUKUK ULKESININ
 *  dilinde uretilmeli (arayuz dili degil). Bul: api.php uretim promptu dili nasil
 *  belirliyor (lang mi country mi), country->dil haritasi var mi; frontend
 *  (doc-dev/genDoc/[[DOC]]) hangi 'lang'/'country' gonderiyor. HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-doclang.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$api=(string)@file_get_contents("$D/api.php");
$idx=(string)@file_get_contents("$D/index.php");
echo "=== dump-doclang — SADECE OKUR (api=".strlen($api)." index=".strlen($idx)." ) ===\n\n";

function occ($label,$hay,$needle,$pre,$len,$max=6){
  echo "── $label ('$needle') ──\n";
  $off=0;$n=0;$tot=substr_count($hay,$needle);
  while(($p=strpos($hay,$needle,$off))!==false && $n<$max){
    $seg=substr($hay,max(0,$p-$pre),$len); $seg=preg_replace('/\s+/',' ',$seg);
    echo "  …".$seg."…\n"; $off=$p+strlen($needle);$n++;
  }
  if(!$tot) echo "  (yok)\n"; echo "  [toplam $tot]\n";
}

echo "════ [1] api.php: 'lang' ve 'country' parametreleri nasil aliniyor ════\n";
occ('$lang alma',$api,'$lang',10,90,8);
occ('$country / $cc alma',$api,'country',12,90,10);
occ('LANGNAMES / dil adi haritasi',$api,'LANGNAMES',10,120,3);

echo "\n════ [2] api.php: uretim promptunda DIL talimati ════\n";
occ('"in der Sprache" / "auf X" / "language"',$api,'Sprache',20,160,8);
occ('"Schreibe" / "write in"',$api,'write',20,140,6);
occ('SYSTEM prompt dil satiri',$api,'You are',10,180,4);
occ('country->language map (cc)',$api,'de\'=>',6,80,6);

echo "\n════ [3] api.php: doGenerate/aichat system prompt (dil kismi) ════\n";
/* system prompt tanimlarini bul */
foreach(['$system',"'system'",'system ='] as $nd){ occ("system tanimi ($nd)",$api,$nd,10,150,4); }

echo "\n════ [4] frontend: uretim cagrilari hangi lang/country yolluyor ════\n";
occ("aichat body (lang/country)",$idx,'provider:',10,180,6);
occ("genDoc lang",$idx,'lang:',6,90,10);
occ("country: cc",$idx,'country:',6,90,10);
occ("UIL() kullanimi",$idx,'UIL()',20,100,8);
occ("CC (hukuk ulkesi) global",$idx,'var CC',0,80,3);

echo "\n════ [5] doc-dev modulu (Belge olustur) uretim cagrisi ════\n";
occ("docdev/dev submit",$idx,'ch-dev',10,120,6);
occ("Belgeyi hazirla handler",$idx,'Belgeyi hazirla',30,140,3);
occ("devGen / ddGen",$idx,'devGen',10,120,4);

echo "\n=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
