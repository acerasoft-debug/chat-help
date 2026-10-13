<?php
/* "Amazon's $60bn B2B Engine" (13 Eki 2026, operator: "vestra Journal e daha yenilikci
   bir haber at amazon ve b2b olabilir gorseller gercek olsun live ve estetik ayrintili
   tum dillerde"). Olculenler:
   - tohumun BASINDA, gecerli kategori, slug = fotograf dosya oneki
   - dokuz dil; her dilde AYNI dort figur AYNI sirada; altyazi o dilin kendi metni ve
     sonunda fotografcinin kunyesi (CC BY / BY-SA ATIF SARTI -- govde figurunde kunye
     otomatik basilmiyor, altyazinin parcasi)
   - fotograflar SUNUCUDA (journal-photo-files.yml ile indirildi, depoda yok): adlari
     fixtures_journal_article_files.txt'te, kapak dahil
   - rakamlar dokuz dilde ayni
   - govde gercekten 4 <figure> + (blok-4) <p> olarak ciziliyor
   - art-… fotograflari GENEL HAVUZA girmiyor (kunyeli olduklari halde) */
$root = dirname(__DIR__);
$ok=0;$bad=0;$t=function($n,$c)use(&$ok,&$bad){ if($c){$ok++;echo "  ok   $n\n";}else{$bad++;echo "  HATA $n\n";} };
$src = file_get_contents($root.'/vestra/inc/journal.php');
$d = json_decode(file_get_contents($root.'/vestra/inc/journal_seed.json'), true);
$TITLE = "Amazon's \$60bn B2B Engine: What Fashion Wholesale Can Learn";
$a = $d[0] ?? [];
$t('tohumun basinda', ($a['title'] ?? '') === $TITLE);
$t('baslik tek kez', count(array_filter($d, fn($x)=>($x['title']??'')===$TITLE)) === 1);
preg_match("/const VESTRA_JOURNAL_CATS = \[(.*?)\];/", $src, $cm);
$t('kategori gecerli', str_contains($cm[1] ?? '', "'".($a['category'] ?? '-')."'"));
$slug = substr(trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($TITLE)), '-'), 0, 70);
$t('slug = dosya oneki', $slug === 'amazon-s-60bn-b2b-engine-what-fashion-wholesale-can-learn');
$files = array_filter(array_map('trim', file(__DIR__.'/fixtures_journal_article_files.txt')));
$t('kapak makalenin kendi fotografi ve sunucuda', ($a['cover'] ?? '') === '/uploads/journal/art-'.$slug.'-cover.jpg' && in_array(basename($a['cover']), $files, true));

$langs = ['en'=>$a] + ($a['i18n'] ?? []);
$t('9 dil, sitenin sirasiyla', array_keys($langs) === ['en','de','fr','it','es','pt','ru','ar','ja']);
$figRe = '/^\[img:([^|\]]+)\|([^\]]*)\]$/m';
preg_match_all($figRe, $a['body'] ?? '', $enF);
$t('ingilizcede 4 figur', count($enF[1]) === 4);
$enBlocks = count(preg_split('/\n{2,}/', trim($a['body'] ?? '')));
$CRED = ['/— Todd Van Hoosear · CC BY-SA 2\.0$/u', '/— Ingbife · CC BY-SA 4\.0$/u', '/— UNIFORM Studio · CC BY 2\.0$/u', '/— [^:—]{2,10}: VESTRA$/u'];
foreach ($langs as $l => $v) {
  $b = (string)($v['body'] ?? '');
  $t("$l: baslik+ozet dolu", trim((string)($v['title'] ?? '')) !== '' && mb_strlen((string)($v['excerpt'] ?? '')) > 60);
  $t("$l: blok sayisi ingilizceyle ayni ($enBlocks)", count(preg_split('/\n{2,}/', trim($b))) === $enBlocks);
  preg_match_all($figRe, $b, $f);
  $t("$l: 4 figur, ayni yollar, ayni sira", $f[1] === $enF[1]);
  foreach ($f[2] as $i => $cap) {
    $t("$l fig".($i+1).": kunye sonda", preg_match($CRED[$i], $cap) === 1);
    if ($l !== 'en') $t("$l fig".($i+1).": altyazi cevrilmis", $cap !== $enF[2][$i]);
    $t("$l fig".($i+1).": altyazi dolu", mb_strlen($cap) > 50);
  }
  foreach (array_slice($f[1], 0, 3) as $p) $t("$l: ".basename($p)." sunucuda", in_array(basename($p), $files, true));
  $nums = ['2015','11','97','90','30','31','27','28','57','52','10','15','2026'];
  foreach ($nums as $n) $t("$l: rakam $n", preg_match('/(?<![\d])'.$n.'(?![\d])/u', $b) === 1);
  $t("$l: 60 milyar", $l === 'ja' ? str_contains($b, '600億') : preg_match('/(?<![\d])60(?![\d])/u', $b) === 1);
  $t("$l: cozulmemis isaret yok", !preg_match('/\{(IMG|C)\d\}|\[\/?img\]/', $b));
}

/* cizim: govde 4 figure + kalan bloklar paragraf */
foreach (['vestra_journal_body_photos','vestra_journal_svg_meta','vestra_journal_figure_caption',
          'vestra_journal_photo_desc','vestra_journal_body_html'] as $fn) {
  if (!preg_match('/^function '.$fn.'\(.*?^}/ms', $src, $m)) { fwrite(STDERR,"$fn bulunamadi\n"); exit(1); }
  eval(str_replace('__DIR__', var_export(realpath($root.'/vestra/inc'), true), $m[0]));
}
function vestra_journal_cover_path($a){ return ''; }
function vestra_journal_photo_pool($f=false){ return ['/uploads/journal/pool-a.jpg']; }
function vestra_journal_credits($f=false){ return []; }
function vestra_journal_credit($p){ return ''; }
foreach (['en','de','ar','ja'] as $l) {
  $h = vestra_journal_body_html($langs[$l]['body'], $a);
  $t("$l: 4 <figure>", substr_count($h, '<figure') === 4);
  $t("$l: 4 <figcaption>", substr_count($h, '<figcaption>') === 4);
  $t("$l: ".($enBlocks-4)." <p>", substr_count($h, '<p>') === $enBlocks - 4);
  $t("$l: alt metni bos degil", !str_contains($h, 'alt=""'));
  $t("$l: havuz fotosu YOK", !str_contains($h, 'pool-'));
}

/* havuz: art- dosyasi kunyeli olsa da girmez */
$tmp = sys_get_temp_dir().'/vjpool'.getmypid();
@mkdir($tmp.'/inc', 0777, true); @mkdir($tmp.'/uploads/journal', 0777, true);
foreach (['pool-a.jpg', 'art-'.$slug.'-1.jpg'] as $fn) file_put_contents($tmp.'/uploads/journal/'.$fn, 'x');
preg_match('/^function vestra_journal_photo_pool\(.*?^}/ms', $src, $m);
$code = str_replace(['function vestra_journal_photo_pool(', 'vestra_journal_credits(', 'vestra_journal_photo_reject(', '__DIR__'],
                    ['function t_pool(', 't_credits(', 't_reject(', var_export($tmp.'/inc', true)], $m[0]);
function t_credits($f=false){ global $slug; return ['pool-a.jpg'=>['artist'=>'A'], 'art-'.$slug.'-1.jpg'=>['artist'=>'Todd Van Hoosear']]; }
function t_reject(){ return []; }
eval($code);
$pool = t_pool(true);
$t('havuz: genel foto var', in_array('/uploads/journal/pool-a.jpg', $pool, true));
$t('havuz: makalenin art- fotografi YOK', count(array_filter($pool, fn($p)=>str_contains($p, '/art-'))) === 0);
array_map('unlink', glob($tmp.'/uploads/journal/*')); @rmdir($tmp.'/uploads/journal'); @rmdir($tmp.'/uploads'); @rmdir($tmp.'/inc'); @rmdir($tmp);

printf("\n%d gecti, %d KALDI\n", $ok, $bad); exit($bad ? 1 : 0);
