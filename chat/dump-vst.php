<?php
/**
 * ChatHelp — dump-vst — CANLI Vertrag Studio CT katalogu + buildPages yapisini DOGRULA (OKUR).
 *  Amac: scheidung eklemek icin dogru anchor'lari bul (genisletme sonrasi kuyruk degismis).
 * KULLANIM: pull2.php?key=...&files=dump-vst.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-vst (".strlen($src)." B) ==\n\n";

/* CT= tanimi ve tum sozlesme anahtarlari (xxx:{ic:) */
echo "=== CT sozlesme anahtarlari (xxx:{ic:) ===\n";
if(preg_match_all('/([a-z_]+):\{ic:/',$src,$mk)){
  echo "  ".implode(', ',$mk[1])."\n";
}

/* CT bloklarini kabaca bul: 'var CT=' ... nerede? */
$p=strpos($src,'var CT={');
if($p===false) $p=strpos($src,'CT={');
echo "\n=== CT baslangic offset: ".($p===false?'YOK':$p)." ===\n";

/* buildPages: tum 'key===' dallari */
echo "\n=== buildPages 'key===' dallari (±10) ===\n";
if(preg_match_all('/key===["\']([a-z_]+)["\']/',$src,$mb)){
  echo "  ".implode(', ',array_values(array_unique($mb[1])))."\n";
}

/* buildPages fonksiyonun sonu: 'var n=P.length' civari (insertion anchor) */
echo "\n=== 'var n=P.length' civari (buildPages sonu, insertion anchor) ===\n";
$p=strpos($src,'var n=P.length');
if($p!==false){ echo "  @$p\n  ...".substr($src,max(0,$p-420),520)."...\n"; }
else echo "  bulunamadi\n";

/* buildPages'in son 'else' dali: en son 'P.push(' ...') sonrasi '}' */
echo "\n=== buildPages: son dal kapanisi (\"var n=P.length\" oncesi son 200) ===\n";

/* CT'nin son girisi: 'var n=P.length' ile 'CT baslangic' arasi degil; CT tail: en son ']]}\n      ]}\n  };' benzeri */
echo "\n=== CT kapanis desenleri (']}  };' / ']}\\n  };') ===\n";
foreach(['      ]}'."\n".'  };', '      ]}'."\n".'    };', ']}'."\n".'  };'] as $i=>$pat){
  echo "  desen[$i] sayisi: ".substr_count($src,$pat)."\n";
}

/* pgs: ve steps: sayilari (kac sozlesme) */
echo "\n=== 'steps:[' sayisi (~ sozlesme sayisi): ".substr_count($src,'steps:[')." ===\n";
echo "=== 'buildPages' gecis sayisi: ".substr_count($src,'buildPages')." ===\n";
echo "\n== BITTI ==\n";
