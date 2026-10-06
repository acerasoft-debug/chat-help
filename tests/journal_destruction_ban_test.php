<?php
/* "No More Bonfires" (27 Eyl 2026): kendi figurleriyle, DOKUZ dilde yayinlanan
   ilk dergi yazisi. Olculenler:
   - makale tohum dosyasinin BASINDA (vestra_journal_starters() created damgasini
     sirayla verir; basta = en yeni) ve dokuz dilin hepsinde tam
   - her dilde AYNI dort figur, AYNI sirada, dosyasi diskte; altyazi o dilin
     kendi metni (ingilizce altyaziyi sekiz dile basmak "tum dillerde" degil)
   - govde gercekten dort <figure> + on uc <p> olarak ciziliyor, havuz fotosu yok
   - SVG: gecerli XML, <title> (alt) != <desc>, alt 140 karakteri gecmiyor
   - kapak 21:9 kirpilma penceresinde (y 88..431)
   - olgular dokuz dilde ayni: tuzuk/direktif numaralari, esikler */
$root = __DIR__.'/..';
$src = file_get_contents($root.'/vestra/inc/journal.php');
foreach (['vestra_journal_body_photos','vestra_journal_svg_meta','vestra_journal_figure_caption',
          'vestra_journal_photo_desc','vestra_journal_body_html'] as $fn) {
  if (!preg_match('/^function '.$fn.'\(.*?^}/ms', $src, $m)) { fwrite(STDERR,"$fn bulunamadi\n"); exit(1); }
  eval(str_replace('__DIR__', var_export(realpath($root.'/vestra/inc'), true), $m[0]));
}
function vestra_journal_cover_path($a){ return ''; }
function vestra_journal_photo_pool($f=false){ return ['/uploads/journal/pool-a.jpg']; }
function vestra_journal_credits($f=false){ return []; }
function vestra_journal_credit($p){ return ''; }

$ok=0; $bad=0;
$t=function($n,$c)use(&$ok,&$bad){ if($c){$ok++;echo "  ok   $n\n";}else{$bad++;echo "  HATA $n\n";} };

$TITLE = "No More Bonfires: Where Europe's Unsold Fashion Goes Now";
$d = json_decode(file_get_contents($root.'/vestra/inc/journal_seed.json'), true);
$t('tohum dosyasi okunuyor', is_array($d));
$art = null; $idx = null;
foreach ($d as $i=>$x) if (($x['title'] ?? '') === $TITLE) { $art = $x; $idx = $i; }
$t('makale tohumda', $art !== null);
if (!$art) { printf("\n%d gecti, %d KALDI\n",$ok,$bad); exit(1); }
/* 6 Eki 2026: daha yeni bir yazi (150 € paket gumrugu) one gecti; bu yazi artik
   onun HEMEN ARKASINDA -- sira tarihtir, yeni yazi basa gelir. */
$t('tohumun basinda, yalniz daha yeni yazinin arkasinda', $idx === 1);
$t('baslik tek kez', count(array_filter($d, fn($x)=>($x['title']??'')===$TITLE)) === 1);
preg_match("/const VESTRA_JOURNAL_CATS = \[(.*?)\];/", $src, $cm);
$t('kategori gecerli', str_contains($cm[1] ?? '', "'".$art['category']."'"));

$slug = substr(trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($TITLE)), '-'), 0, 70);
$t('slug = figur dosya oneki', $slug === 'no-more-bonfires-where-europe-s-unsold-fashion-goes-now');

/* site dilleri: inc/lang/*.php (en temel dil, dosyasi yok) */
$langs = array_map(fn($f)=>basename($f,'.php'), glob($root.'/vestra/inc/lang/*.php'));
sort($langs);
$t('site dilleri 8 ceviri', count($langs) === 8);
$t('i18n tum site dillerini tasiyor', array_values(array_diff($langs, array_keys($art['i18n'] ?? []))) === []);

$texts = ['en'=>['title'=>$art['title'],'excerpt'=>$art['excerpt'],'body'=>$art['body']]] + ($art['i18n'] ?? []);
$figRe = '/^\[img:([^|\]]+)\|([^\]]*)\]$/m';
preg_match_all($figRe, $texts['en']['body'], $enF);
$t('ingilizcede 4 figur', count($enF[1]) === 4);

foreach ($texts as $lg=>$tx) {
  $b = (string)($tx['body'] ?? '');
  $t("$lg baslik+ozet dolu", trim((string)($tx['title']??'')) !== '' && mb_strlen((string)($tx['excerpt']??'')) > 80);
  preg_match_all($figRe, $b, $f);
  $t("$lg: 4 figur, ingilizceyle ayni yollar, ayni sira", $f[1] === $enF[1]);
  foreach ($f[1] as $p) if (!is_file($root.'/vestra'.$p)) $t("$lg dosya diskte: $p", false);
  $t("$lg altyazilar dolu", count(array_filter($f[2], fn($c)=>mb_strlen(trim($c)) > 30)) === 4);
  if ($lg !== 'en') $t("$lg altyazilar KENDI dilinde", count(array_intersect($f[2], $enF[2])) === 0);
  foreach (['2024/1781','2025/1892','C(2026) 659','250'] as $fact)
    if (!str_contains($b, $fact)) $t("$lg olgu yok: $fact", false);
  $t("$lg olgular tam (tuzuk, direktif, C(2026) 659, 250)", true);
  $t("$lg Turkce karakter yok", !preg_match('/[şğıİŞĞ]/u', $tx['title'].$tx['excerpt'].$b));

  $html = vestra_journal_body_html($b, ['slug'=>$slug]);
  preg_match_all('/<(figure|p)\b/', $html, $mm);
  $t("$lg cizim: 4 figure + 13 paragraf",
     count(array_filter($mm[1],fn($x)=>$x==='figure'))===4 && count(array_filter($mm[1],fn($x)=>$x==='p'))===13);
  $t("$lg figcaption = o dilin altyazisi", substr_count($html,'<figcaption>')===4
     && str_contains($html, '<figcaption>'.htmlspecialchars($f[2][0]).'</figcaption>'));
  $t("$lg havuz fotosu yok, ham isaret kalmadi", !str_contains($html,'pool-') && !str_contains($html,'[img:'));
  $t("$lg alt bos degil", !str_contains($html,'alt=""'));
}

echo "\n== SVG'ler ==\n";
$dir = $root.'/vestra/uploads/journal/art-'.$slug;
foreach (['cover','1','2','3','4'] as $n) {
  $file = "$dir-$n.svg";
  $x = @simplexml_load_file($file);
  $t("$n gecerli XML", $x !== false);
  if (!$x) continue;
  $meta = vestra_journal_svg_meta('/uploads/journal/'.basename($file));
  $t("$n title dolu, <=140", $meta['title'] !== '' && mb_strlen($meta['title']) <= 140);
  $t("$n desc dolu ve title'dan farkli", $meta['desc'] !== '' && $meta['desc'] !== $meta['title']);
  $vb = (string)$x['viewBox'];
  $t("$n viewBox", $n === 'cover' ? $vb === '0 0 800 520' && (string)$x['preserveAspectRatio'] === 'xMidYMid slice' : $vb === '0 0 1200 675');
}
/* kapak 21:9 penceresi: journal_cover_crop_test.php ile ayni olcu */
$x = simplexml_load_file("$dir-cover.svg"); $lo=INF; $hi=-INF; $n=0;
foreach ($x->xpath('//*') as $el) {
  $tag = $el->getName();
  if ($tag === 'rect') { if ((float)$el['width'] >= 800) continue; $lo=min($lo,(float)$el['y']); $hi=max($hi,(float)$el['y']+(float)$el['height']); $n++; }
  elseif ($tag === 'circle') { $lo=min($lo,(float)$el['cy']-(float)$el['r']); $hi=max($hi,(float)$el['cy']+(float)$el['r']); $n++; }
  elseif ($tag === 'path') {
    $dd=(string)$el['d']; if (str_contains($dd,'h800')) continue;
    if (preg_match_all('/[ML]\s*(-?[\d.]+)[\s,]+(-?[\d.]+)/', $dd, $pm)) foreach ($pm[2] as $yy) { $lo=min($lo,(float)$yy); $hi=max($hi,(float)$yy); $n++; }
  }
}
$t(sprintf('kapak 21:9 penceresinde (y %.0f..%.0f, %d olcu)', $lo, $hi, $n), $n >= 8 && $lo >= 88 && $hi <= 431);

printf("\n%d gecti, %d KALDI\n",$ok,$bad);
exit($bad?1:0);
