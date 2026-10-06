<?php
$src=file_get_contents(__DIR__.'/../vestra/inc/notify.php');
preg_match('/function vestra_email_deliverable.*?\n}/s',$src,$m); eval($m[0]);

/* GECMESI SART: bu oturumda gercekten kullanilan / listelerde duran adresler */
$must_pass = [
 'acerasoft@gmail.com','kkukusschoepfer@gmail.com','brian.bongolob@icloud.com',
 'sitacoulibaly65@gmail.com','ana.pan@vipshop.com','kontakt@szykszok.pl',
 'support@vestrasales.com','acerasoft+verifytest@gmail.com',
 'customercare@closet.com.sg','tokyo@nepenthes.co.jp','info@mboutique.com.my',
 'bali_office@laboutik.com','o@creators-blueprint.com','sects@sectsshop.com',
 'hello@sorrythanksiloveyou.com','enquiries@luxeitfwd.com.au','ask@colony.work',
 'sydney@whitestory.com.au','info@bondidesigner.au','a.b-c_d@sub.domain.co.uk',
 'x@y.io','a.very.long.local.part.but.under.sixtyfour.chars@example.com',
];
/* ELENMESI SART: saglayicinin reddettigi kaliplar */
$must_fail = [
 '', 'plainaddress', 'no-at-sign.com', '@nolocal.com', 'trailing@dot.',
 '.leading@dot.com', 'trailing.@dot.com', 'double..dot@x.com',
 'user@..double.com', 'user@-hyphen.com', 'user@nodot', 'user@x.c',
 'boşluk var@x.com', "tab\t@x.com", 'ünicode@x.com',
 str_repeat('a',65).'@x.com',
];
$ok=0;$bad=0;
foreach($must_pass as $e){
  $r=vestra_email_deliverable($e);
  if($r){$ok++;} else {$bad++; echo "  YANLIS ELENDI: ".($e===''?'(bos)':$e)."\n";}
}
foreach($must_fail as $e){
  $r=vestra_email_deliverable($e);
  if(!$r){$ok++;} else {$bad++; echo "  YANLIS GECTI : ".($e===''?'(bos)':$e)."\n";}
}
printf("\ngecmesi gereken %d + elenmesi gereken %d = %d dogru, %d YANLIS\n",
  count($must_pass), count($must_fail), $ok, $bad);
exit($bad?1:0);
