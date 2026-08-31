<?php
/* Fonksiyon workflow'un heredoc'unda yasiyor; oradan cikarilip sinaniyor
   ki testin olctugu sey GERCEKTEN calisan kod olsun. */
/* Fonksiyon workflow'un heredoc'unda yasiyor; testin olctugu sey GERCEKTEN
   calisan kod olsun diye oradan cikariliyor. Girinti degisken oldugu icin
   desenle degil PARANTEZ SAYARAK kesiliyor -- '^}' varsayimi, ic bloklarin
   girintisi soyulunca sutun 0'a dustugunde fonksiyonu erken kesiyordu. */
$yml = file_get_contents(__DIR__.'/../.github/workflows/fetch-external-images.yml');
$i = strpos($yml, 'function ld_image_url');
if ($i === false) { fwrite(STDERR, "ld_image_url workflow icinde bulunamadi\n"); exit(1); }
$open = strpos($yml, '{', $i);
$d = 0; $endPos = null;
for ($j = $open; $j < strlen($yml); $j++) {
  if ($yml[$j] === '{') $d++;
  elseif ($yml[$j] === '}') { $d--; if ($d === 0) { $endPos = $j; break; } }
}
if ($endPos === null) { fwrite(STDERR, "fonksiyon govdesi kapanmadi\n"); exit(1); }
eval(substr($yml, $i, $endPos - $i + 1));
$cases=[
 ['duz string','https://x.com/a.jpg','https://x.com/a.jpg'],
 ['string dizisi',['https://x.com/a.jpg','https://x.com/b.jpg'],'https://x.com/a.jpg'],
 ['ImageObject',['@type'=>'ImageObject','url'=>'https://x.com/c.jpg'],'https://x.com/c.jpg'],
 ['ImageObject DIZISI',[['@type'=>'ImageObject','url'=>'https://x.com/d.jpg']],'https://x.com/d.jpg'],
 ['contentUrl',[['@type'=>'ImageObject','contentUrl'=>'https://x.com/e.jpg']],'https://x.com/e.jpg'],
 ['@id',[['@id'=>'https://x.com/f.jpg']],'https://x.com/f.jpg'],
 ['ic ice',[[['https://x.com/g.jpg']]],'https://x.com/g.jpg'],
 ['bos dizi',[],null],
 ['null',null,null],
 ['bos string','',null],
 ['bosluklu','  https://x.com/h.jpg  ','https://x.com/h.jpg'],
 ['sayi',123,null],
];
$bad=0;
foreach($cases as [$n,$in,$want]){
  $got=ld_image_url($in);
  $ok=($got===$want);
  if(!$ok){$bad++; printf("  HATA %-20s beklenen=%s  gelen=%s\n",$n,var_export($want,true),var_export($got,true));}
  else printf("  ok   %-20s -> %s\n",$n,var_export($got,true));
}
/* Eski kod bu girdide ne yapiyordu? */
$old=[['@type'=>'ImageObject','url'=>'https://x.com/d.jpg']];
echo "\nESKI kod ayni girdide: ";
$r=reset($old); echo is_array($r) ? "dizi -> (string) => \"Array\" + PHP uyarisi\n" : "ok\n";
printf("\n%d durum, %d yanlis\n",count($cases),$bad);
exit($bad?1:0);
