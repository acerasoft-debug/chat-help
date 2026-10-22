<?php
/**
 * ChatHelp — apply-menu-clean (CH_MENU_CLEAN) — sol menude HIC modul kalmaz
 *  Kullanici istegi: "menüde hiçbir modül kalmasın, kullanıcı isterse kendi eklesin."
 *  Mevcut Modul-Zentrale (🧩 hub) zaten tam bunun icin var: ch_mods ile
 *  modul nav'lari acilip kapanabiliyor — ama varsayilan ACIK'ti.
 *  [1] modOn() varsayilani ACIK -> KAPALI cevrilir (2 kopya: modulepack + modhub).
 *      Boylece jobs/cv/vst/fris/rech/tresor/briefe/fav nav'lari yeni/temiz
 *      kullanicida gorunmez; hub'dan "＋ Zur Oberfläche" deyince aninda gelir.
 *      Kullanicinin DAHA ONCE actigi moduller (ch_mods'ta 1) aynen kalir.
 *  [2] Hub'da anahtari olmayan modul nav'lari kalici gizlenir:
 *      #nav-trvisa #nav-vsf #nav-vdos (TR vize — ana sayfa kartindan acilir)
 *      #nav-dev #nav-plans (zaten devplan-fix ile kalkiyor; emniyet kemeri).
 *      Cekirdek girisler (Chat, Verlauf, Anträge, Fälle, Konto, 🧩 Hub) DURUR.
 * KULLANIM: pull2.php?key=...&files=apply-menu-clean.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-menu-clean BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_MENU_CLEAN')!==false) exit("Zaten ekli (CH_MENU_CLEAN).\n");

/* [1] modOn varsayilanini kapali yap — modulepack + modhub ayni bayt dizisi */
$old = 'function modOn(k){ var m=mods(); return m[k]===undefined?true:!!m[k]; }';
$new = 'function modOn(k){ var m=mods(); return m[k]===undefined?false:!!m[k]; }/*CH_MENU_CLEAN — moduller varsayilan gizli, hub ile eklenir*/';
$cnt = substr_count($src,$old);
if ($cnt<1) exit("HATA: modOn capasi hic yok ($cnt) — index DEGISTIRILMEDI.\n");
$src = str_replace($old,$new,$src);
echo "  ✓ [1] modOn varsayilani KAPALI yapildi ($cnt kopya) — menu bos baslar, hub'dan eklenir\n";

/* [2] hub-anahtarsiz modul nav'lari kalici gizle */
$css = "\n<style id=\"ch-menu-clean-css\">\n"
     . "/* CH_MENU_CLEAN — hub'da anahtari olmayan modul girisleri menuden kalkar */\n"
     . "#nav-trvisa,#nav-vsf,#nav-vdos,#nav-dev,#nav-plans{display:none !important}\n"
     . "</style>";
$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$src = substr($src,0,$pos).$css."\n".substr($src,$pos);
echo "  ✓ [2] trvisa/vsf/vdos/dev/plans nav girisleri kalici gizlendi\n";

$tmp = tempnam(sys_get_temp_dir(),'mc').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-menuclean-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_MENU_CLEAN uygulandi. Sol menude modul KALMADI;\n";
echo "   kullanici 🧩 Modul-Zentrale'den (ana sayfa karti / menu girisi) istedigini ekler.\n";
echo "   Daha once acilmis moduller (ch_mods) aynen korunur.\n";
