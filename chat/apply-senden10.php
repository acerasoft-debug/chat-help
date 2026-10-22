<?php
/**
 * ChatHelp — apply-senden10 (CH_SENDEN2_OFF) — sari .ch-senden2 enjektorunu KAYNAKTA
 *  susturur. CH_SENDEN2'nin inject() fonksiyonu her 700ms altin buton ekliyordu;
 *  supurge (CH_SENDEN7) siliyordu -> yanip sonme. inject basina 'return;' konur:
 *  buton hic olusmaz, savas biter. Yesil #ch-sd2 (CH_SENDEN3) kartta kalir.
 * KULLANIM: pull2.php?key=...&files=apply-senden10.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-senden10 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_SENDEN2_OFF')!==false) exit("Zaten ekli (CH_SENDEN2_OFF).\n");

/* CH_SENDEN2 enjektorunun inject() govdesi — sadece bu blokta gecen imzayi hedefle */
$o="/* CH_SENDEN2 — .racts'a '📤 Senden' (gold PDF butonunun yanina) */\ntry{(function(){\n  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2);}catch(e){return 'de';} }\n  var L={de:'Senden',tr:'Gönder',en:'Send',fr:'Envoyer',es:'Enviar',it:'Invia'};\n  var FREE={de:'Gratis-Fax',tr:'ücretsiz faks',en:'free fax',fr:'fax gratuit',es:'fax gratis',it:'fax gratis'};\n  function inject(){\n    try{";
$n="/* CH_SENDEN2 — .racts'a '📤 Senden' (gold PDF butonunun yanina) */\ntry{(function(){\n  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2);}catch(e){return 'de';} }\n  var L={de:'Senden',tr:'Gönder',en:'Send',fr:'Envoyer',es:'Enviar',it:'Invia'};\n  var FREE={de:'Gratis-Fax',tr:'ücretsiz faks',en:'free fax',fr:'fax gratuit',es:'fax gratis',it:'fax gratis'};\n  function inject(){ return; /*CH_SENDEN2_OFF — sari .ch-senden2 kaldirildi, yanip-sonme biter*/\n    try{";

$c=substr_count($src,$o);
if($c!==1){ echo "  ✗ anchor ($c, 1 beklenir) — HICBIR sey degistirilmedi.\n"; exit; }
$src=str_replace($o,$n,$src);
echo "  ✓ CH_SENDEN2 inject() susturuldu (return; eklendi)\n";

/* CH_SENDEN2 gizleme CSS'i de ekle (buton kaldiysa/onbellek) — kesin gizli */
$css="<style id=\"ch-senden2-off-css\">/*CH_SENDEN2_OFF_CSS*/ .ch-senden2{display:none!important}</style>";
$pos=strripos($src,'</body>');
if($pos!==false && strpos($src,'CH_SENDEN2_OFF_CSS')===false){
  $src=substr($src,0,$pos).$css."\n".substr($src,$pos);
  echo "  ✓ .ch-senden2 gizleme CSS eklendi (guvenlik)\n";
}

$tmp=tempnam(sys_get_temp_dir(),'s10').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-senden10-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_SENDEN2_OFF')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_SENDEN2_OFF diskte (".strlen($chk)." B)\n";
echo "  Sari Senden artik HIC olusmaz -> yanip-sonme biter. Tek YESIL Senden kalir.\n";
echo "  NOT: App'te hala goruyorsan bir kez veri temizle (eski onbellek).\n";
