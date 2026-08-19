<?php
/**
 * ChatHelp — apply-cv-photo-manage (CH_CV_PHOTO_MANAGE)
 *  CV/Lebenslauf fotografina "sil" ozelligi ekler (degistirme zaten
 *  mevcut: foto varken '📷' etiketine tekrar tiklayinca yeni foto secilip
 *  kirpilir -> degisir). Bu yama:
 *   • Foto alaninin altina kucuk kirmizi '🗑' sil butonu ekler (statik).
 *   • Buton, foto varken gorunur/yokken gizli; kirpma onaylaninca gorunur,
 *     silince gizlenir — tam sayfa yeniden cizime bagli DEGIL.
 *   • Silince DATA.photo temizlenir, onizleme varsayilan avatara doner,
 *     canli onizleme guncellenir.
 *  GEREKLI: apply-cv-studio.php + apply-cv-photocrop.php.
 * KULLANIM: pull-updates.php?files=apply-cv-photo-manage.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-cv-photo-manage BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_CV_PHOTO_MANAGE') !== false) exit("Zaten ekli (CH_CV_PHOTO_MANAGE).\n");
if (strpos($src, 'CH_CV_STUDIO') === false) exit("HATA: CV Studio (CH_CV_STUDIO) yok.\n");
if (strpos($src, 'openCropModal(String(rd.result))') === false) exit("HATA: apply-cv-photocrop.php kurulu degil.\n");
file_put_contents($file . '.bak-cvphotomng-' . date('Ymd-His'), $src);

$changes = 0;

/* ── 1) foto markup'ina statik sil butonu ── */
$oldMk = '<input id="cvs-file" type="file" accept="image/*" style="display:none"></label></div>\'';
$newMk = '<input id="cvs-file" type="file" accept="image/*" style="display:none"></label><button type="button" id="cvs-photo-del" class="cvs-photo-del" title="Foto löschen / Fotoğrafı sil">🗑</button></div>\'';
$c=0; $src=str_replace($oldMk,$newMk,$src,$c); if($c){ $changes++; echo "  ✓ [1] sil butonu markup eklendi ($c)\n"; } else echo "  ✗ [1] foto markup anchor yok\n";

/* ── 2) sil butonu baslangic gorunurlugu + davranisi ── */
$oldBind = "      rd.onload=function(){ openCropModal(String(rd.result)); };\n      rd.readAsDataURL(f);\n    });";
$newBind = "      rd.onload=function(){ openCropModal(String(rd.result)); };\n      rd.readAsDataURL(f);\n    });\n"
  . "    var _pdel=document.getElementById(\"cvs-photo-del\");\n"
  . "    if(_pdel){ _pdel.style.display=DATA.photo?\"inline-block\":\"none\";\n"
  . "      _pdel.addEventListener(\"click\",function(){ try{ DATA.photo=\"\"; saveData(); var im=document.getElementById(\"cvs-phimg\"); if(im)im.src=PHOTO; _pdel.style.display=\"none\"; schedPrev(); }catch(e){} }); }";
$c=0; $src=str_replace($oldBind,$newBind,$src,$c); if($c){ $changes++; echo "  ✓ [2] sil butonu davranisi eklendi ($c)\n"; } else echo "  ✗ [2] foto binding anchor yok\n";

/* ── 3) kirpma onayinca sil butonunu goster ── */
$oldCrop = 'var pi=document.getElementById("cvs-phimg"); if(pi) pi.src=DATA.photo;';
$newCrop = 'var pi=document.getElementById("cvs-phimg"); if(pi) pi.src=DATA.photo; var pd=document.getElementById("cvs-photo-del"); if(pd) pd.style.display="inline-block";';
$c=0; $src=str_replace($oldCrop,$newCrop,$src,$c); if($c){ $changes++; echo "  ✓ [3] kirpma onayinda sil butonu gorunur ($c)\n"; } else echo "  ✗ [3] crop-apply anchor yok\n";

if ($changes < 3) { echo "\nHATA: 3 degisiklikten sadece $changes uygulandi — index.php DEGISTIRILMEDI.\n"; exit; }

/* ── 4) CSS ── */
$bundle = <<<'PMHTML'
<style id="ch-cv-photo-manage-css">
.cvs-photo-del{margin-top:8px;padding:8px 12px;border-radius:10px;border:1px solid rgba(224,80,80,.4);background:rgba(224,80,80,.1);color:#e05050;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s}
.cvs-photo-del:hover{background:rgba(224,80,80,.2)}
.cvs-photo{display:flex;flex-direction:column;align-items:flex-start}
</style>
PMHTML;

$p = strrpos($src, '</body>');
if ($p === false) exit("HATA: </body> bulunamadi — yazilmadi.\n");
$src = substr($src, 0, $p) . $bundle . "\n" . substr($src, $p);

$tmp = tempnam(sys_get_temp_dir(), 'idx') . '.php';
file_put_contents($tmp, $src);
$lo = []; $rc = 0;
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc);
@unlink($tmp);
if ($rc !== 0) { echo "LINT HATASI — index.php degistirilmedi:\n  " . implode("\n  ", $lo) . "\n"; exit; }
file_put_contents($file, $src);
echo "\n✓ CH_CV_PHOTO_MANAGE uygulandi ($changes/3 + CSS).\n";
echo "Test: CV'de foto yukle+kirp -> altinda '🗑' cikar; tikla -> foto silinir, varsayilan avatar doner. Yeniden yuklemek degistirir.\n";
