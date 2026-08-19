<?php
/**
 * ChatHelp — apply-menufix (CH_MENUFIX)
 *  KOK NEDEN: enforceOrder (menu sira sabitleyici) capayi 'Fall eröffnen'
 *  ALMANCA metnine gore ariyordu. Turkce/diger dil arayuzde bu metin farkli
 *  oldugundan capa bulunamiyor, enforceOrder erken donuyor ve SIRALAMA HIC
 *  CALISMIYOR -> sol menuler surekli oynuyor.
 *  COZUM: capa artik DIL-BAGIMSIZ: onclick="openFall()" ile bulunur; metin
 *  eski yedek olarak kalir; ikisi de tutmazsa ilk nav-olmayan .sb-photo-btn
 *  kullanilir. Boylece enforceOrder her dilde calisir, menu sabitlenir.
 * KULLANIM: pull-updates.php?files=apply-menufix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-menufix BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_MENUFIX')!==false) exit("Zaten ekli (CH_MENUFIX).\n");

$old = "      var fall=null;\n"
     . "      document.querySelectorAll(\".sb-photo-btn\").forEach(function(b){\n"
     . "        if(!fall && !/^nav-/.test(b.id||\"\") && /Fall\\s*er[öo]ffnen/i.test(b.textContent||\"\")) fall=b;\n"
     . "      });\n"
     . "      if(!fall||!fall.parentNode) return;";
$new = "      var fall=null;\n"
     . "      document.querySelectorAll(\".sb-photo-btn\").forEach(function(b){\n"
     . "        if(!fall && !/^nav-/.test(b.id||\"\") && (/openFall/.test(b.getAttribute(\"onclick\")||\"\") || /Fall\\s*er[öo]ffnen/i.test(b.textContent||\"\"))) fall=b;\n"
     . "      });\n"
     . "      if(!fall){ /*CH_MENUFIX dil-bagimsiz yedek*/ document.querySelectorAll(\".sb-photo-btn\").forEach(function(b){ if(!fall && !/^nav-/.test(b.id||\"\")) fall=b; }); }\n"
     . "      if(!fall||!fall.parentNode) return;";

$c=0; $src=str_replace($old,$new,$src,$c);
if ($c!==1) {
    echo "  ✗ enforceOrder anchor eslesmedi ($c)\n";
    /* teshis: hangi kisim var? */
    echo "  Fall-metin capasi var mi: ".(strpos($src,'Fall\\s*er[öo]ffnen')!==false?'EVET':'HAYIR')."\n";
    echo "  onclick capasi zaten var mi: ".(strpos($src,'/openFall/.test(b.getAttribute')!==false?'EVET (belki baska yamayla)':'HAYIR')."\n";
    echo "\nHATA: index.php DEGISTIRILMEDI.\n"; exit;
}
echo "  ✓ enforceOrder capasi dil-bagimsiz yapildi (onclick=openFall + yedek)\n";

$tmp = tempnam(sys_get_temp_dir(),'mf').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-menufix-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_MENUFIX uygulandi. Menu sirasi artik HER DILDE sabit (Turkce dahil) — sol menuler oynamaz.\n";
