<?php
/**
 * ChatHelp — dump-qa — KOMPLE bütünlük denetimi (OKUR). PDF üretimi + geri-dönüş +
 *  bu turda canliya atilan markerlarin tutarliligini kontrol eder, supheli yerleri raporlar.
 * KULLANIM: pull2.php?key=...&files=dump-qa.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-qa index.php (".strlen($src)." B) ==\n\n";
function cnt($src,$s){ return substr_count($src,$s); }
function ck($lbl,$ok,$extra=''){ echo ($ok?"  ✓ ":"  ✗ ").$lbl.($extra!==''?"  [$extra]":"")."\n"; return $ok; }

echo "=== [A] Bu turda atilan markerlar (1 olmali) ===\n";
foreach(['CH_SIG_UNDER','CH_HISTPDF','CH_SCHEID','CH_CALM3','CH_PLANFAX','CH_SENDGATE','CH_SENDEN2','CH_SEND_POLISH','CH_FAX_QUOTA'] as $m){
  $c=cnt($src,$m); ck("$m",$c>=1,"$c");
}

echo "\n=== [B] PDF cekirdek fonksiyonlar ===\n";
ck("chPrintDoc tanimi",cnt($src,'chPrintDoc=function')>=1||cnt($src,'function chPrintDoc')>=1,cnt($src,'chPrintDoc=function').'/'.cnt($src,'function chPrintDoc'));
ck("chPrintDoc cagri sayisi",cnt($src,'chPrintDoc(')>=5,cnt($src,'chPrintDoc('));
ck("makePDF tanimi",cnt($src,'function makePDF')>=1,cnt($src,'function makePDF'));
ck("showResult tanimi",cnt($src,'function showResult')===1,cnt($src,'function showResult'));
ck("adres-imza wrapper (__addr4)",cnt($src,'__addr4')>=1,cnt($src,'__addr4'));

echo "\n=== [C] CH_SENDGATE 7 edit tutarliligi ===\n";
ck("[1] _isFreeUser tanim (1)",cnt($src,'function _isFreeUser')===1,cnt($src,'function _isFreeUser'));
ck("[1] _gate tanim (1)",cnt($src,'function _gate(')===1,cnt($src,'function _gate('));
ck("[2] doFree kapisi",cnt($src,'function doFree(){ if(!_gate())return;')===1);
ck("[3] doHome kapisi",cnt($src,"function doHome(){\n    if(!_gate())return;")===1);
ck("[4] reallySend kapisi",cnt($src,"function reallySend(btn){\n    if(!_gate())return;")===1);
ck("[5] cancel free no-print",cnt($src,'var _pd=!_isFreeUser(); close(); if(_pd) proceed')===1);
ck("[6] wrapper fallthrough free-kapi",cnt($src,'if(_isFreeUser()){ try{_gate();}catch(e){} return; }')===1);
ck("[7] showResult PDF butonu modal-acilir",cnt($src,'{makePDF(doc,d.n,CC);return;}/*CH_SENDGATE-modal-acilir*/')===1);
ck("[7b] ESKI kapi kalmadi (if(_tk3&&_up3))",cnt($src,"if(_tk3&&_up3!=='free'){makePDF")===0,cnt($src,"if(_tk3&&_up3!=='free'){makePDF"));
ck("_gate/_isFreeUser cagri sayisi (>=6)",cnt($src,'_gate()')>=4 && cnt($src,'_isFreeUser()')>=2,cnt($src,'_gate()').'/'.cnt($src,'_isFreeUser()'));

echo "\n=== [D] Geri-donus (CH_HISTPDF) ===\n";
ck("openTopic tam-metin (doc||body||content)",cnt($src,'d.doc||d.body||d.content||d.text||d.full')>=1);
ck("th-pdf alert(tx) KALDIRILDI",cnt($src,"window.makePDF(tx,CUR.title,CCX()); else alert(tx); }catch(e){ alert(tx); }")===0,'eski kalinti');
ck("th-pdf bos/kisa guard",cnt($src,'CH_HISTPDF_B')>=1);
ck("imza ismin altina (CH_SIG_UNDER)",cnt($src,'CH_SIG_UNDER')>=1);

echo "\n=== [E] Boşanma modulu (CH_SCHEID) ===\n";
$sc=cnt($src,'CH_SCHEID');
ck("CH_SCHEID atildi mi",$sc>=1,$sc.' (0 ise apply-scheidung2 calistirilmamis)');
if($sc>=1){ ck("scheidung CT girisi",cnt($src,'scheidung:{ic:"⚖️"')>=1); ck("scheidung buildPages dali",cnt($src,'key==="scheidung"')>=1); }

echo "\n=== [F] Genel saglik ===\n";
ck("showPaywall tanimi (1)",cnt($src,'function showPaywall')===1,cnt($src,'function showPaywall'));
ck("proceed tanimi",cnt($src,'function proceed')>=1,cnt($src,'function proceed'));
ck("kirik anchor kalintisi yok (CH_SENDGATE-modal 1)",cnt($src,'CH_SENDGATE-modal-acilir')===1);
/* opcache/parse: PHP lint sunucuda gecti (yazma oncesi). JS brace kabaca: */
$ob=cnt($src,'{'); $cb=cnt($src,'}');
echo "  i süslü parantez denge (kaba): { =$ob  } =$cb  fark=".($ob-$cb)."\n";
$op=cnt($src,'('); $cp=cnt($src,')');
echo "  i normal parantez denge (kaba): ( =$op  ) =$cp  fark=".($op-$cp)."\n";

echo "\n=== [G] plan free-fax (CH_PLANFAX) ===\n";
ck("Gratis-Fax satiri sayisi (>=6)",cnt($src,'Gratis-Fax')>=6,cnt($src,'Gratis-Fax'));
ck("hero nasil-calisir (ch-howto)",cnt($src,'ch-howto')>=1);

echo "\n== BITTI ==\n";
