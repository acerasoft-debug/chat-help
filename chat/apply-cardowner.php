<?php
/**
 * ChatHelp — apply-cardowner (CH_CARD_OWNER) — kart GORUNURLUGUNUN TEK SAHIBI: CSS
 *
 *  KANIT (cardwatch3, ES): C(gercek kayma)=15 ve tamami 1.6sn'de tek seferlik
 *  (acilis oturmasi) — cardlock calisiyor. AMA A(style)=509/15sn ve ekranda
 *  ALMAN kartlari ES kartlariyla AYNI ANDA gorunuyor ("Verträge kündigen",
 *  "Mieten & Wohnen"...). Yani 11 ulke modulu + evrensel enjektor ayni
 *  kartlarin display'ini her 0.9-1.2sn'de kendi gorusune gore yazip duruyor:
 *  goster/gizle SAVASI. Her flip izgarayi yeniden akitiyor -> "oynama".
 *
 *  KESIN COZUM: gorunurlugu inline-JS'ten al, tamamen CSS'e ver. CSS
 *  !important inline style'i EZER -> savasan yazicilar yazmaya devam etse de
 *  gorsel sonuc SABIT kalir:
 *   • <body data-chcc="XX"> secili hukuku tasir (kucuk JS: 300ms'de bir esitler)
 *   • Her hukuk icin statik kural cifti:
 *       body[data-chcc=XX] .wcat[data-cccountry]:not([data-cccountry=XX]) -> GIZLI
 *       body[data-chcc=XX] .wcat[data-cccountry=XX]                        -> GORUNUR
 *   • FR/TR eski isaretleri (data-frcat/data-trcat) kendi ulkesine baglanir
 *   • DE-disi hukukta ISARETSIZ (Alman taban) kartlar + DE-ozel modul kartlari GIZLI
 *  Sonuc: hangi JS ne yazarsa yazsin, gorunurluk deterministik; flip/blink ve
 *  ona bagli tum yeniden-akis KOKten biter.
 * KULLANIM: pull2.php?key=...&files=apply-cardowner.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-cardowner BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_CARD_OWNER')!==false) exit("Zaten ekli (CH_CARD_OWNER).\n");

$ccs = ['DE','AT','CH','TR','FR','BE','ES','IT','NL','PL','SE','PT','GB','US'];
$css = "/* CH_CARD_OWNER — kart gorunurlugunun tek sahibi CSS; inline display yazilari hukumsuz */\n";
foreach ($ccs as $cc) {
    $css .= 'body[data-chcc="'.$cc.'"] .wcat-grid .wcat[data-cccountry]:not([data-cccountry="'.$cc.'"]){display:none !important}'."\n";
    $css .= 'body[data-chcc="'.$cc.'"] .wcat-grid .wcat[data-cccountry="'.$cc.'"]{display:block !important}'."\n";
}
/* eski isaretler: frcat=FR, trcat=TR */
$css .= 'body[data-chcc]:not([data-chcc="FR"]) .wcat-grid .wcat[data-frcat]:not([data-cccountry]){display:none !important}'."\n";
$css .= 'body[data-chcc="FR"] .wcat-grid .wcat[data-frcat]{display:block !important}'."\n";
$css .= 'body[data-chcc]:not([data-chcc="TR"]) .wcat-grid .wcat[data-trcat]:not([data-cccountry]){display:none !important}'."\n";
$css .= 'body[data-chcc="TR"] .wcat-grid .wcat[data-trcat]{display:block !important}'."\n";
/* DE-disi: isaretsiz Alman taban kartlari + DE-ozel modul kartlari (data-chcat) gizli */
$css .= 'body[data-chcc]:not([data-chcc="DE"]):not([data-chcc="AT"]):not([data-chcc="CH"]) .wcat-grid .wcat:not([data-cccountry]):not([data-frcat]):not([data-trcat]){display:none !important}'."\n";

$bundle = "<style id=\"ch-cardowner-css\">\n".$css."</style>\n"
. <<<'HTML'
<script id="ch-cardowner-js">
/* CH_CARD_OWNER — body[data-chcc] = secili hukuk (CSS kurallarinin anahtari) */
try{(function(){
  function sync(){
    try{
      var cc=(typeof CC!=="undefined"&&CC)?String(CC):"DE";
      if(document.body && document.body.getAttribute("data-chcc")!==cc) document.body.setAttribute("data-chcc",cc);
    }catch(e){}
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",sync); else sync();
  setInterval(sync, 300);
})();}catch(e){}
</script>
HTML;

$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok — index degistirilmedi.\n");
$src = substr($src,0,$pos).$bundle."\n".substr($src,$pos);
echo "  ✓ ".count($ccs)." hukuk icin deterministik gorunurluk kurallari eklendi\n";
echo "  ✓ eski FR/TR isaretleri baglandi; DE-disi tabanda Alman kartlari gizli\n";
echo "  ✓ body[data-chcc] esitleyicisi eklendi (300ms)\n";

$tmp = tempnam(sys_get_temp_dir(),'co').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-cardowner-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_CARD_OWNER uygulandi. Gorunurluk artik tek sahipli (CSS) —\n";
echo "   goster/gizle savasi gorsel olarak HUKUMSUZ; flip/oynama KOKten bitti.\n";
