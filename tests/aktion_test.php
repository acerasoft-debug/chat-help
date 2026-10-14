<?php
/* AKTION -- sureli teklif (14 Eki 2026, operator: "geri sayimli yapalim aktion olarak 3 gunden geriye
   dogru gitsin … en az alim 100 adet … 10'arli lot"). Iki yon:
   - teklif yazilinca: min'in altindaki ucuz olmayan eski basamaklar kalir, tepeye teklif gelir,
     min ve ustundeki eski basamaklar duser; mode=sale; ustu cizili = eski liste; pinned
   - suresi dolunca OKUMA ANINDA eski hal (tiers, mode, list, pinned) geri gelir, aktion silinir
   - sepet fiyati tek yoldan (vestra_unit_price) dogru; rozet eski listeden
   - sayfalar kabloli: urun kutusu, katalog rozeti, ana sayfa bandi; 8 dilde 12 anahtar */
$root = dirname(__DIR__);
$ok=0;$bad=0;$t=function($n,$c)use(&$ok,&$bad){ if($c){$ok++;echo "  ok   $n\n";}else{$bad++;echo "  HATA $n\n";} };
require $root.'/vestra/inc/products.php';
$now = strtotime('2026-10-15 10:00:00 UTC'); $until = $now + 3*86400;
$p = ['id'=>'t-fleece','brand'=>'Lacoste','name'=>'Fleece Hoodie','mode'=>'sale','list'=>49.9,'moq'=>40,'size_step'=>10,
      'tiers'=>[['min'=>40,'price'=>49.9],['min'=>50,'price'=>45],['min'=>100,'price'=>42]]];
$q = vestra_aktion_set($p, 35, 100, $until, $now);
$t('kademeler: 40→49.9, 50→45, 100→35 (eski 100→42 dustu)', $q['tiers'] == [['min'=>40,'price'=>49.9],['min'=>50,'price'=>45],['min'=>100,'price'=>35.0]]);
$t('mode=sale, list eski liste, pinned', $q['mode']==='sale' && (float)$q['list']===49.9 && !empty($q['pinned']));
$a = vestra_aktion($q, $now);
$t('aktif teklif: 35 / 100 / -%30 / bitis', $a && $a['price']===35.0 && $a['min']===100 && $a['pct']===30 && $a['until']===$until);
$t('sepet: 40→49.9 60→45 100→35 250→35', vestra_unit_price($q,40,true)==49.9 && vestra_unit_price($q,60,true)==45 && vestra_unit_price($q,100,true)==35 && vestra_unit_price($q,250,true)==35);
$t('rozet -%30, from 35', vestra_discount($q)===30 && vestra_from_price($q,true)==35.0);
$t('bitise 1 sn kala hala aktif', vestra_aktion($q, $until-1) !== null && vestra_aktion_apply($q, $until-1)['tiers']===$q['tiers']);
$e = vestra_aktion_apply($q, $until);
$t('bitince: eski kademeler, list, aktion yok, pinned yok', $e['tiers']===$p['tiers'] && (float)$e['list']===49.9 && !isset($e['aktion']) && !isset($e['pinned']));
$t('bitince vestra_aktion null', vestra_aktion($e, $until) === null);
$t('clear = bitis ile ayni', vestra_aktion_clear($q) === $e);
/* onceden pinned olan geri acilinca pinned kalir; list yoksa ilk basamak ustu cizili olur */
$p2 = ['id'=>'t2','mode'=>'fixed','moq'=>56,'tiers'=>[['min'=>56,'price'=>55]],'pinned'=>true];
$q2 = vestra_aktion_set($p2, 39.9, 100, $until, $now);
$t('list yoksa ustu cizili = eski ilk basamak (55), -%27', (float)$q2['list']===55.0 && vestra_aktion($q2,$now)['pct']===27 && $q2['tiers']===[['min'=>56,'price'=>55.0],['min'=>100,'price'=>39.9]]);
$t('onceden pinned: bitince pinned KALIR, mode fixed doner, list silinir', ($e2=vestra_aktion_apply($q2,$until)) && !empty($e2['pinned']) && $e2['mode']==='fixed' && !isset($e2['list']));
/* ustuste yazma: ikinci set eskisini once kaldirir */
$q3 = vestra_aktion_set($q, 30, 100, $until+86400, $now);
$t('ustuste yazinca prev yine ORIJINAL kademeler', $q3['aktion']['prev']['tiers']===$p['tiers'] && vestra_aktion_apply($q3,$until+86400)['tiers']===$p['tiers']);
/* min'in altinda kalan ama teklif fiyatindan UCUZ olmayan basamak kuraliyla: teklif 46 ise 50→45 de kalmaz */
$q4 = vestra_aktion_set($p, 46, 100, $until, $now);
$t('teklif 46: 50→45 ucuz oldugu icin duser', $q4['tiers']===[['min'=>40,'price'=>49.9],['min'=>100,'price'=>46.0]]);
/* kablolama */
$prod = file_get_contents($root.'/vestra/product.php'); $shop = file_get_contents($root.'/vestra/shop.php'); $idx = file_get_contents($root.'/vestra/index.php');
$ui = file_get_contents($root.'/vestra/inc/aktion_ui.php'); $pr = file_get_contents($root.'/vestra/inc/products.php');
$t('live_listings suresi dolani okuma aninda geri alir', str_contains($pr, "array_map('vestra_aktion_apply'"));
$t('urun sayfasi: kutu + teklif satiri isaretli', str_contains($prod, 'echo vestra_aktion_box($p)') && str_contains($prod, "class=\"ak-row\""));
$t('katalog: teklif rozeti sale rozetinin yerine, css/js bir kez', str_contains($shop, 'vestra_aktion_badge($p)') && str_contains($shop, 'if ($__akAny) echo vestra_aktion_css().vestra_aktion_js();'));
$t('ana sayfa: band rakam seridinden sonra, kagit icinde', str_contains($idx, '<div class="paper"><?= vestra_aktion_band() ?></div>') && strpos($idx,'vestra_aktion_band()') > strpos($idx,'class="facts"') && strpos($idx,'vestra_aktion_band()') < strpos($idx,'id="coming-soon"'));
$t('band teklif yoksa bos', str_contains($ui, "if (!\$items) return '';"));
$t('sayac JS: biten sayac kartini soldurur', str_contains($ui, 'classList.add("ended")'));
foreach (glob($root.'/vestra/inc/lang/*.php') as $lf) { $d = require $lf; $l = basename($lf,'.php');
  foreach (['Limited offer','Three days only','from %d pc','lots of %d','Ends in','Offer ends %s','Offer ended','Browse the catalog','days','hours','min','sec'] as $k)
    if (empty($d[$k])) $t("$l: '$k' cevirisi", false);
  $t("$l: 12 anahtar", true); }
printf("\n%d gecti, %d KALDI\n", $ok, $bad); exit($bad?1:0);
