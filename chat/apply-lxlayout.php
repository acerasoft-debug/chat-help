<?php
/**
 * ChatHelp — apply-lxlayout (CH_LXV2) — LetterXpress/Fax mektup PDF'i TAM DIN-uyumlu:
 *  Vorschau'da gorulen 4 sorunun kesin duzeltmesi (ayni fonksiyon send_fax'ta da
 *  kullanildigi icin FAX da ayni anda duzelir):
 *   1. Alici adresi pencere kutusunun USTUNE tasiyordu (y=725) -> simdi tam kutu
 *      icinde (y=698 baslangic, DIN 5008 pencere bolgesi).
 *   2. Govde metni y=640'tan (pencerenin ICINDEN) basliyordu -> simdi pencere
 *      ALTINDAN (y=545) baslar; gonderen iletisim bloklari/alici tekrarlari ve
 *      "(Adresse bitte selbst eintragen)" placeholder'i metinden ayiklanir.
 *   3. Imza SABIT koordinata basiliyordu (uzun mektupta metnin ustune biniyordu)
 *      -> simdi SON satirin (isim) hemen altina; yer yoksa otomatik ek sayfa.
 *   4. Sayfalama yoktu (uzun metin alttan KESILIYORDU) -> simdi cok sayfali.
 *  Ayrica: gonderen iade-adres satirinin altina ince cizgi (DIN gorunumu),
 *  PV_VERSION 5 -> 6 (status URL'sinden dogrulanabilir).
 *  Anchor'lar: fonksiyonun onundeki ve sonrasindaki yorum satirlari; eslesmezse
 *  HICBIR sey degistirilmez.
 * KULLANIM: pull2.php?key=...&files=apply-lxlayout.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-lxlayout BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/postversand.php';
$src=@file_get_contents($file);
if($src===false) exit("postversand.php okunamadi\n");
if(strpos($src,'CH_LXV2')!==false) exit("Zaten ekli (CH_LXV2).\n");

$a1="/* ── DIN5008 mektup PDF: alici adresi (pencere konumu) + govde + imza JPEG ── */";
$a2="/* ── basit ama gecerli PDF uretici (Helvetica, A4, cok sayfa) ── */";
$p1=strpos($src,$a1); $p2=strpos($src,$a2);
if($p1===false||$p2===false||$p2<=$p1) exit("  ✗ anchor bulunamadi (p1=".var_export($p1,true).", p2=".var_export($p2,true).") — HICBIR sey degistirilmedi.\n");

$fn = <<<'FNBLOCK'
/* ── DIN5008 mektup PDF v2 (CH_LXV2): pencere-uyumlu alici + sayfalama + imza isim altinda ── */
function ch_letter_pdf(string $text, string $recipient='', string $sender='', string $sigJpeg=''): string {
  $enc=function($s){ $t=@iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$s); return $t===false?$s:$t; };
  $esc=function($s){ return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$s); };
  $text=$enc($text); $recipient=$enc($recipient); $sender=$enc($sender);

  /* metnin basindaki gonderen/alici tekrarlari + iletisim satirlari + placeholder ayiklanir */
  $norm=function($s){ return preg_replace('/[^a-z0-9]/','',strtolower($s)); };
  $recL=array_values(array_filter(array_map('trim',explode("\n",$recipient)),function($x){return $x!=='';}));
  $sndParts=array_values(array_filter(array_map('trim',preg_split('/\n|\s+-\s+|·/',$sender)),function($x){return $x!=='';}));
  $recN=array_map($norm,$recL); $sndN=array_map($norm,$sndParts);
  $raw=explode("\n",str_replace("\r",'',$text));
  $out=[];
  foreach($raw as $idx=>$ln){
    $t=trim($ln); $n=$norm($t);
    if($t!==''){
      $dup=false;
      if(preg_match('/adresse bitte selbst eintragen/i',$t)) $dup=true;
      if(!$dup && $idx<14){
        if(($n!=='' && (in_array($n,$recN,true)||in_array($n,$sndN,true)))) $dup=true;
        if(!$dup && preg_match('/^(telefon|tel\.|fax|e-?mail)\s*[:.]/i',$t)) $dup=true;
        if(!$dup && count($sndN)>=2 && strlen($n)>8){
          for($i=0;$i<count($sndN)-1 && !$dup;$i++){ $acc='';
            for($j=$i;$j<count($sndN);$j++){ $acc.=$sndN[$j]; if($j>$i && $acc===$n){ $dup=true; break; } } }
        }
      }
      if(!$dup && count($recN)>=2 && strlen($n)>8){
        for($i=0;$i<count($recN)-1 && !$dup;$i++){ $acc='';
          for($j=$i;$j<count($recN);$j++){ $acc.=$recN[$j]; if($j>$i && $acc===$n){ $dup=true; break; } } }
      }
      if($dup) continue;
    }
    $out[]=rtrim($ln);
  }
  while($out && trim($out[0])==='') array_shift($out);
  while($out && trim((string)end($out))==='') array_pop($out);

  $lines=[]; $bl=0;
  foreach($out as $ln){
    if(trim($ln)===''){ $bl++; if($bl>1) continue; $lines[]=''; continue; }
    $bl=0;
    while(strlen($ln)>90){ $cut=strrpos(substr($ln,0,90),' '); if($cut===false||$cut<40)$cut=90; $lines[]=substr($ln,0,$cut); $ln=ltrim(substr($ln,$cut)); }
    $lines[]=$ln;
  }

  /* imza olculeri */
  $imgW=0;$imgH=0;$dw=0;$dh=0;
  if($sigJpeg!==''){ list($imgW,$imgH)=ch_jpeg_dims($sigJpeg); $dw=120; $dh=($imgW>0?intval($dw*$imgH/$imgW):40); if($dh>60)$dh=60; }

  /* sayfalama: s1 govde y=545 (pencere ALTI), sonraki sayfalar y=786; alt sinir 80 */
  $lead=15; $bottom=80;
  $cap1=intdiv(545-$bottom,$lead)+1; $capN=intdiv(786-$bottom,$lead)+1;
  $chunks=[]; $tops=[]; $rest=$lines;
  $chunks[]=array_splice($rest,0,$cap1); $tops[]=545;
  while(count($rest)){ $chunks[]=array_splice($rest,0,$capN); $tops[]=786; }

  /* imza: SON metin satirinin (isim) hemen altina; yer yoksa otomatik ek sayfa */
  $sigPage=-1; $sigY=0;
  if($sigJpeg!==''){
    $li=count($chunks)-1;
    $lastY=$tops[$li]-(max(count($chunks[$li]),1)-1)*$lead;
    $sigY=$lastY-14-$dh; $sigPage=$li;
    if($sigY<40){ $chunks[]=[]; $tops[]=786; $sigPage=count($chunks)-1; $sigY=786-$dh; }
  }

  /* nesneler: 1 catalog, 2 pages, 3 font, 4 imza (varsa), 5+ sayfalar */
  $objs=[]; $kids=[]; $n=4; $hasSig=($sigJpeg!=='');
  $res="<</Font<</F1 3 0 R>>".($hasSig?" /XObject<</Im1 4 0 R>>":"").">>";
  foreach($chunks as $pi=>$pl){
    $pn=++$n; $cn=++$n; $kids[]="$pn 0 R";
    $c='';
    if($pi===0){
      if($sender!==''){
        $sl=trim(preg_replace('/\s*\n\s*/',' - ',$sender));
        $c.="BT /F1 7.5 Tf 56 795 Td\n(".$esc($sl).") Tj\nET\n";
        $c.="0.4 w 56 790 m 305 790 l S\n";
      }
      if(count($recL)){
        $c.="BT /F1 11 Tf 60 698 Td 14 TL\n";
        foreach(array_slice($recL,0,6) as $rl){ $c.="(".$esc($rl).") Tj T*\n"; }
        $c.="ET\n";
      }
    }
    if(count($pl)){
      $top=$tops[$pi];
      $c.="BT /F1 11 Tf 56 $top Td $lead TL\n";
      foreach($pl as $i=>$ln){ $c.=($i?"T*\n":"")."(".$esc($ln).") Tj\n"; }
      $c.="ET\n";
    }
    if($hasSig && $pi===$sigPage){ $c.="q $dw 0 0 $dh 56 $sigY cm /Im1 Do Q\n"; }
    $objs[$pn]="<</Type/Page /Parent 2 0 R /MediaBox[0 0 595 842] /Resources $res /Contents $cn 0 R>>";
    $objs[$cn]="<</Length ".strlen($c).">>\nstream\n$c\nendstream";
  }
  $objs[1]="<</Type/Catalog /Pages 2 0 R>>";
  $objs[2]="<</Type/Pages /Kids[".implode(' ',$kids)."] /Count ".count($kids).">>";
  $objs[3]="<</Type/Font /Subtype/Type1 /BaseFont/Helvetica /Encoding/WinAnsiEncoding>>";
  if($hasSig){ $objs[4]="<</Type/XObject /Subtype/Image /Width $imgW /Height $imgH /ColorSpace/DeviceRGB /BitsPerComponent 8 /Filter/DCTDecode /Length ".strlen($sigJpeg).">>\nstream\n".$sigJpeg."\nendstream"; }
  ksort($objs);
  $pdf="%PDF-1.4\n"; $offs=[];
  foreach($objs as $num=>$body){ $offs[$num]=strlen($pdf); $pdf.="$num 0 obj\n$body\nendobj\n"; }
  $xref=strlen($pdf); $max=max(array_keys($objs));
  $pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";
  for($i=1;$i<=$max;$i++){ $pdf.=str_pad((string)($offs[$i]??0),10,'0',STR_PAD_LEFT)." 00000 n \n"; }
  $pdf.="trailer\n<</Size ".($max+1)." /Root 1 0 R>>\nstartxref\n$xref\n%%EOF";
  return $pdf;
}

FNBLOCK;

$src=substr($src,0,$p1).$fn.substr($src,$p2);
echo "  ✓ ch_letter_pdf v2 ile degistirildi (DIN pencere + sayfalama + imza isim altinda + dedup)\n";

$c=substr_count($src,"define('PV_VERSION','5')");
if($c===1){ $src=str_replace("define('PV_VERSION','5')","define('PV_VERSION','6')",$src); echo "  ✓ PV_VERSION 5 -> 6\n"; }
else echo "  = PV_VERSION bump atlandi ($c)\n";

$tmp=tempnam(sys_get_temp_dir(),'lxl').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-lxlayout-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_LXV2')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_LXV2 diskte (".strlen($chk)." B)\n";
echo "  Dogrulama: https://chat-help.com/chat/postversand.php?action=status -> \"v\":\"6\"\n";
echo "  Test: Brief/Fax gonder -> LetterXpress Vorschau'da alici TAM kutu icinde,\n";
echo "  metinde tekrar yok, imza ismin ALTINDA, uzun metin 2. sayfaya akiyor olmali.\n";
