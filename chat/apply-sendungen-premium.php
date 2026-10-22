<?php
/**
 * ChatHelp — apply-sendungen-premium (CH_SND_PREMIUM) — "Meine Sendungen"
 *  sayfasini premium/estetik yapar. Mevcut .snd-* siniflari (row/badge/meta/
 *  btn/empty/up) uzerine cohesive navy+gold premium stil: gradyan kartlar,
 *  altin rozet, yumusak golgeler, premium hover, sik bos-durum.
 *  Sadece stil (CSS) — davranis degismez. </body> oncesi, !important + kaynak
 *  sirasi ile ustun.
 * KULLANIM: pull2.php?key=...&files=apply-sendungen-premium.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-sendungen-premium BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_SND_PREMIUM')!==false) exit("Zaten ekli (CH_SND_PREMIUM).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-sendungen-premium">
/* CH_SND_PREMIUM — Meine Sendungen premium estetik */
.snd-sub{ color:var(--t3,#9fb4d8) !important; font-size:12.5px !important; margin-bottom:16px !important }
.snd-row{
  border:1px solid rgba(212,168,74,.16) !important;
  border-radius:16px !important; padding:14px 16px !important; margin-bottom:12px !important;
  background:linear-gradient(135deg,rgba(255,255,255,.045),rgba(212,168,74,.028)) !important;
  box-shadow:0 2px 10px rgba(0,0,0,.22) !important;
  transition:border-color .18s ease, box-shadow .18s ease, transform .18s ease !important;
}
.snd-row:hover{
  border-color:rgba(212,168,74,.42) !important;
  box-shadow:0 8px 26px rgba(0,0,0,.32) !important; transform:translateY(-1px) !important;
}
.snd-top{ gap:9px !important; margin-bottom:7px !important; align-items:center !important }
.snd-badge{
  font-size:9.5px !important; letter-spacing:.6px !important; padding:3px 10px !important;
  border-radius:100px !important; font-weight:800 !important; border:none !important;
  background:linear-gradient(135deg,#e8c877,#d4a84a) !important; color:#171a2b !important;
  box-shadow:0 1px 5px rgba(212,168,74,.25) !important; text-transform:uppercase !important;
}
.snd-meta{ color:var(--t3,#9fb4d8) !important; font-size:11.5px !important; line-height:1.6 !important }
.snd-btn{
  font-size:11.5px !important; padding:6px 13px !important; border-radius:10px !important;
  border:1px solid rgba(255,255,255,.14) !important; background:rgba(255,255,255,.04) !important;
  cursor:pointer !important; transition:border-color .16s ease, background-color .16s ease, color .16s ease !important;
}
.snd-btn:hover{ border-color:rgba(212,168,74,.42) !important; background:rgba(212,168,74,.1) !important; color:#f0d493 !important }
.snd-btn.p{ border-color:transparent !important; background:linear-gradient(135deg,#e8c877,#d4a84a) !important; color:#171a2b !important; font-weight:800 !important }
.snd-btn.p:hover{ filter:brightness(1.06) !important }
.snd-empty{ color:var(--t4,#8a9ec4) !important; font-size:13.5px !important; padding:40px 16px 34px !important; line-height:1.6 !important }
.snd-empty::before{ content:"📨"; display:block; font-size:40px; margin-bottom:14px; opacity:.9 }
.snd-up{
  border:1px solid rgba(212,168,74,.35) !important; border-radius:16px !important;
  background:linear-gradient(135deg,rgba(212,168,74,.13),rgba(212,168,74,.03)) !important;
  box-shadow:0 4px 20px rgba(0,0,0,.26) !important; padding:16px 18px !important;
}
</style>
HTMLBLOCK;

$pos=strripos($src,'</body>');
if($pos===false) exit("</body> bulunamadi\n");
$new=substr($src,0,$pos).$block."\n".substr($src,$pos);
$tmp=tempnam(sys_get_temp_dir(),'sp').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-sndprem-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_SND_PREMIUM')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ CH_SND_PREMIUM eklendi (".strlen($chk)." B)\n";
echo "✓ Meine Sendungen: gradyan kartlar, altin rozet, premium hover/butonlar, sik bos-durum.\n";
