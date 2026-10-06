<?php
/* Bir makale KENDI figurlerini tasiyabiliyor mu, ve bu digerlerini bozuyor mu.
   Havuz mekanizmasi 31 makaleyi besliyor; buradaki degisiklik ona dokunmamali. */
$src = file_get_contents(__DIR__.'/../vestra/inc/journal.php');
foreach (['vestra_journal_body_photos','vestra_journal_svg_meta','vestra_journal_figure_caption',
          'vestra_journal_photo_desc','vestra_journal_body_html'] as $fn) {
  if (!preg_match('/^function '.$fn.'\(.*?^}/ms', $src, $m)) { fwrite(STDERR,"$fn bulunamadi\n"); exit(1); }
  /* __DIR__ eval icinde BU dosyanin dizinine cozulur (tests/), uretimde ise
     vestra/inc/. Degistirmezsek glob bos doner, kod sessizce havuza geri
     sarkar ve test 'kendi figurleri calismiyor' der -- oysa calisiyor.
     Testin olctugu yol, kodun uretimde yurudugu yol olmali. */
  eval(str_replace('__DIR__', var_export(realpath(__DIR__.'/../vestra/inc'), true), $m[0]));
}
/* Havuz tarafi sahte: gercek sunucu fotolarini taklit eder. */
function vestra_journal_cover_path($a){ return ''; }
function vestra_journal_photo_pool($f=false){ return ['/uploads/journal/pool-a.jpg','/uploads/journal/pool-b.jpg','/uploads/journal/pool-c.jpg']; }
function vestra_journal_credits($f=false){ return ['pool-a.jpg'=>['artist'=>'A. Photographer','license'=>'CC BY-SA 4.0','desc'=>'A clothing rail']]; }
function vestra_journal_credit($p){
  if (strpos($p,'/uploads/journal/')!==0) return '';
  $c = vestra_journal_credits()[basename($p)] ?? null;
  if (!$c || empty($c['artist'])) return '';
  return $c['artist'].(!empty($c['license'])?' · '.$c['license']:'');
}

$ok=0;$bad=0;
$t=function($n,$c)use(&$ok,&$bad){ $c?($ok++.print("  ok   $n\n")):($bad++.print("  HATA $n\n")); };

$slug='selling-on-instagram-and-facebook-what-actually-moves-stock';
$art=['slug'=>$slug,'category'=>'Wholesale Trends'];

echo "\n== Makale kendi figurlerini aliyor ==\n";
$shots = vestra_journal_body_photos($art, 4);
$t('4 figur dondu', count($shots)===4);
$t('hepsi bu makaleye ait', count(array_filter($shots, fn($s)=>str_contains($s,'art-'.$slug.'-')))===4);
$t('havuzdan HIC foto gelmedi', count(array_filter($shots, fn($s)=>str_contains($s,'pool-')))===0);
$t('sirali (1,2,3,4)', $shots[0]===end($shots)?false:str_ends_with($shots[0],'-1.svg') && str_ends_with($shots[3],'-4.svg'));
$t('yogunluk kuralina uyar (n=2 -> 2 figur)', count(vestra_journal_body_photos($art,2))===2);

echo "\n== SVG kendi metnini tasiyor ==\n";
foreach ($shots as $i=>$s) {
  $alt = vestra_journal_photo_desc($s);
  $cap = vestra_journal_figure_caption($s);
  $t('fig'.($i+1).' alt dolu',  $alt !== '' && mb_strlen($alt) > 20);
  $t('fig'.($i+1).' altyazi dolu', $cap !== '' && mb_strlen($cap) > 20);
  $t('fig'.($i+1).' alt != altyazi', $alt !== $cap);
}

echo "\n== DIGER makaleler havuzdan beslenmeye devam ediyor ==\n";
$other = ['slug'=>'made-in-italy-read-properly','category'=>'Brand News'];
$os = vestra_journal_body_photos($other, 2);
$t('havuzdan geliyor', count($os)===2 && str_contains($os[0],'pool-'));
$t('bizim figurler SIZMADI', count(array_filter($os, fn($s)=>str_contains($s,'art-')))===0);
$t('fotoda atif altyazisi duruyor', vestra_journal_figure_caption('/uploads/journal/pool-a.jpg')==='A. Photographer · CC BY-SA 4.0');

echo "\n== Gercek makale govdesiyle render ==\n";
$d = json_decode(file_get_contents(__DIR__.'/../vestra/inc/journal_seed.json'), true);
$a = null; foreach($d as $x) if (str_starts_with($x['title'],'Selling on Instagram')) $a=$x;
$t('makale tohumda var', $a !== null);
$html = vestra_journal_body_html($a['body'], $a + ['slug'=>$slug]);
preg_match_all('/<(figure|p)\b/', $html, $mm);
$t('13 paragraf + 4 figur', count(array_filter($mm[1],fn($x)=>$x==='p'))===13 && count(array_filter($mm[1],fn($x)=>$x==='figure'))===4);
$t('figurler bizim dosyalar', substr_count($html,'art-'.$slug.'-')===4);
$t('havuz fotosu YOK', !str_contains($html,'pool-'));
$t('altyazilar basildi', substr_count($html,'<figcaption>')===4);
$t('alt metinleri bos degil', !str_contains($html,'alt=""'));

printf("\n%d gecti, %d KALDI\n",$ok,$bad);
exit($bad?1:0);
