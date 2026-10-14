<?php
/* Aktion teklif mektubu (14 Eki 2026): vestra_tpl_listing_offer suresi isleyen
   teklifi soyluyor mu, fiyat kapisi kapali uyeye EUR sizmiyor mu (KURAL 19),
   lead surumu ve aktionsuz ilan eskisi gibi mi. */
$root = dirname(__DIR__);
$ok=0;$bad=0;$t=function($n,$c)use(&$ok,&$bad){ if($c){$ok++;echo "  ok   $n\n";}else{$bad++;echo "  HATA $n\n";} };
require $root.'/vestra/inc/products.php';
require_once $root.'/vestra/inc/email_templates.php';
$now = time();
$base = ['id'=>'t-hood','name'=>'Fleece Hoodie','brand'=>'Lacoste','moq'=>40,'min_colors'=>4,'size_step'=>10,'mode'=>'sale','list'=>49.9,
         'tiers'=>[['min'=>40,'price'=>49.9],['min'=>50,'price'=>45],['min'=>100,'price'=>42]]];
$ak = vestra_aktion_set($base, 35, 100, $now + 3*86400, $now);
$blk = fn($p) => [['p'=>$p,'pairs'=>[],'rungs'=>vestra_price_ladder($p),'tag'=>'X','stock'=>null,'cover'=>'https://vestrasales.com/x.jpg']];
[$s1,$b1,$o1] = vestra_tpl_listing_offer('en','Shop',$blk($ak),true,'');
[$s2,$b2,$o2] = vestra_tpl_listing_offer('en','Shop',$blk($ak),false,'');
[$s3,$b3,$o3] = vestra_tpl_listing_offer('de','Shop',$blk($ak),false,'');
[$s4,$b4,$o4] = vestra_tpl_listing_offer('en','Shop',$blk($base),true,'');
[$s5,$b5,$o5] = vestra_tpl_listing_offer('en','Shop',$blk($ak),true,'',true);
$t('konu: 3 gun + yuzde + esik', str_contains($s1,'3-day offer') && str_contains($s1,'−30%') && str_contains($s1,'100 pc'));
$t('bitis tarihi konuda ve rozette (UTC)', str_contains($s1, gmdate('j M Y', $now + 3*86400)) && str_contains($o1['badge'],'UTC'));
$t('fiyat acik: aktion fiyati + ustu cizili', str_contains($b1,'EUR 35.00 per piece from 100 pc instead of EUR 49.90'));
$t('fiyat acik: teklif satiri ad satirinin hemen altinda', ($o1['rows'][1]['label'] ?? '') === 'Limited offer');
$t('fiyat KAPALI: EUR yok (govde + satirlar)', !str_contains($b2,'EUR') && !str_contains($b2,'€') && !str_contains(json_encode($o2['rows'], JSON_UNESCAPED_UNICODE),'EUR'));
$t('fiyat KAPALI: yuzde + esik var', str_contains($b2,'−30% from 100 pc'));
$t('almanca', str_contains($s3,'3-Tage-Aktion') && str_contains($b3,'Aktion: −30 % ab 100 Stück'));
$t('aktionsuz ilan: eski konu, teklif satiri yok', !str_contains($s4,'3-day') && !str_contains($b4,'Limited offer'));
$t('lead surumu: aktion katmani yok', !str_contains($s5,'3-day') && !str_contains($b5,'Limited offer'));
$exp = $ak; $exp['aktion']['until'] = $now - 10;
[$s6] = vestra_tpl_listing_offer('en','Shop',$blk($exp),true,'');
$t('suresi dolmus teklif mektuba girmez', !str_contains($s6,'3-day'));
echo "\n{$ok} gecti, {$bad} KALDI\n"; exit($bad ? 1 : 0);
