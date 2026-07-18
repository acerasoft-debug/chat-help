<?php
/**
 * ChatHelp — apply-menuicon (CH_MENUICON) — sol menu (hamburger) premium gorunum.
 *  Mevcut: <div class="tb-ham"><span></span>x3> — duz 3 cizgi. Bu yama onu yumusak
 *  koseli bir kapsul icinde, altin gradyanli, hafif govdeli premium bir ikona
 *  cevirir. Hover'da sadece renk/govde degisir (reflow/oynama yok — sakin).
 *  Stil </body> oncesine eklenir (!important + kaynak sirasi -> ustun).
 * KULLANIM: pull2.php?key=...&files=apply-menuicon.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-menuicon BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_MENUICON')!==false) exit("Zaten ekli (CH_MENUICON).\n");
if(strpos($src,'tb-ham')===false) echo "  ! uyari: 'tb-ham' bulunamadi (yine de stil eklenir)\n";

$block = <<<'HTMLBLOCK'
<style id="ch-menuicon">
/* CH_MENUICON — sol menu (hamburger) premium */
.tb-ham{
  display:flex !important; flex-direction:column; align-items:center; justify-content:center;
  gap:4px !important; width:40px; height:40px; padding:0 !important; box-sizing:border-box;
  border-radius:12px !important;
  background:linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,.02)) !important;
  border:1px solid rgba(212,168,74,.26) !important;
  box-shadow:0 1px 2px rgba(0,0,0,.28), inset 0 1px 0 rgba(255,255,255,.05) !important;
  transition:border-color .18s ease, background-color .18s ease, box-shadow .18s ease !important;
  cursor:pointer;
}
.tb-ham span{
  display:block !important; height:2px !important; border-radius:2px !important;
  background:linear-gradient(90deg,#f0c860,#d4a84a) !important;
  box-shadow:0 0 6px rgba(212,168,74,.22);
}
.tb-ham span:nth-child(1){ width:18px !important; }
.tb-ham span:nth-child(2){ width:18px !important; }
.tb-ham span:nth-child(3){ width:12px !important; }
.tb-ham:hover{
  border-color:rgba(212,168,74,.55) !important;
  background:rgba(212,168,74,.12) !important;
  box-shadow:0 4px 14px rgba(212,168,74,.20) !important;
}
.tb-ham:active{ transform:translateY(1px); }
</style>
HTMLBLOCK;

$pos=strripos($src,'</body>');
if($pos===false) exit("</body> bulunamadi\n");
$new=substr($src,0,$pos).$block."\n".substr($src,$pos);
$tmp=tempnam(sys_get_temp_dir(),'mi').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-menuicon-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_MENUICON')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ CH_MENUICON eklendi (".strlen($chk)." B)\n";
echo "✓ Sol menu ikonu: yumusak koseli kapsul + altin gradyan cizgiler, premium hover.\n";
