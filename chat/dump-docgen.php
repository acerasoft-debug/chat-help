<?php
/* dump-docgen — "Dokument generieren" butonu + uretim fonksiyonu teshisi (salt-okunur).
   KULLANIM: pull2.php?key=...&files=dump-docgen.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false) exit("index.php okunamadi\n");
echo "index.php ".strlen($src)." B\n\n";

/* 'Dokument generieren' / 'Generate document' gecen yerler + cevresi */
echo "== 'Dokument generieren' / gen buton gecisleri ==\n";
$needles=['Dokument generieren','dd-gen','ddGen','dev-gen','doc-gen','id="ddo-gen"','chDocGen','function doGenerate','doGenerate(','id="dd-','onclick'];
foreach(['Dokument generieren','Generate document','Belge oluştur'] as $nq){
  $p=0; $cnt=0;
  while(($p=strpos($src,$nq,$p))!==false){ $cnt++; if($cnt<=3){ echo "  [".$nq."] @".$p.":\n    ".str_replace("\n"," ",substr($src,max(0,$p-90),260))."\n"; } $p+=strlen($nq); }
  echo "  '$nq' toplam: $cnt\n\n";
}

/* uretim fonksiyon adaylari */
echo "== uretim fonksiyonlari ==\n";
foreach(['function doGenerate','function ddGenerate','function chDevGen','function genDoc','chPrintDoc','makePDF','ddo_gen','__addr3OK','isLetter(','function proceed','ai-fillp'] as $f){
  echo "  ".str_pad($f,22)." : ".substr_count($src,$f)." kez\n";
}

/* onclick iceren, generieren gecen buton HTML'i */
echo "\n== buton HTML (regex) ==\n";
if(preg_match_all('/<button[^>]*>[^<]*(?:generieren|Generate document|Belge olu)[^<]*<\/button>/i',$src,$mm)){
  foreach(array_slice($mm[0],0,4) as $b) echo "  ".substr($b,0,200)."\n";
} else echo "  (inline <button> bulunamadi — muhtemelen JS ile olusturuluyor)\n";

/* generieren'e en yakin onclick/addEventListener */
$gp=strpos($src,'Dokument generieren');
if($gp!==false){
  echo "\n== 'Dokument generieren' civari 500 char ==\n";
  echo substr($src,max(0,$gp-250),600)."\n";
}
