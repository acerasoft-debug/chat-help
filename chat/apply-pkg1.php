<?php
/**
 * ChatHelp — apply-pkg1 — Paket & kullanici turu (3 duzeltme):
 *  [1] CH_SENDPERUSER: 'Meine Sendungen' kayitlari artik KULLANICIYA OZEL
 *      (ch_sendungen::<email>) — ayni cihazda hesap degistirince baskasinin
 *      gonderileri GORUNMEZ. Eski ortak liste, giris yapmis kullaniciya
 *      tek seferlik tasinir (kayip yok).
 *  [2] CH_QUOTA40: Basic paket vaadi 40 dilekce/ay — kodda 20 idi, 40 yapildi.
 *  [3] CH_KONTOCSS: Konto sayfasi premium cila — bolum kartlari, altin vurgulu
 *      basliklar, plan rozeti (SADECE CSS, davranis degismez).
 * KULLANIM: pull2.php?key=...&files=apply-pkg1.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-pkg1 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");

$ok=0;

/* ── [1] CH_SENDPERUSER ── */
if(strpos($src,'CH_SENDPERUSER')===false){
  $o1=<<<'ANC'
  function list(){ try{ var a=JSON.parse(localStorage.getItem('ch_sendungen')||'[]'); return Array.isArray(a)?a:[]; }catch(e){ return []; } }
  function save(a){ try{ localStorage.setItem('ch_sendungen',JSON.stringify(a.slice(-200))); }catch(e){} }
ANC;
  $n1=<<<'ANC'
  /*CH_SENDPERUSER*/
  function skey(){ var u=''; try{ u=String((JSON.parse(localStorage.getItem('ch_user')||'{}').email)||'').toLowerCase().trim(); }catch(e){} return 'ch_sendungen::'+(u||'anon'); }
  function list(){ try{ var k=skey(); var a=JSON.parse(localStorage.getItem(k)||'null'); if(!Array.isArray(a)){ var leg=JSON.parse(localStorage.getItem('ch_sendungen')||'[]'); if(Array.isArray(leg)&&leg.length){ localStorage.setItem(k,JSON.stringify(leg.slice(-200))); localStorage.removeItem('ch_sendungen'); return leg; } return []; } return a; }catch(e){ return []; } }
  function save(a){ try{ localStorage.setItem(skey(),JSON.stringify(a.slice(-200))); }catch(e){} }
ANC;
  $c=substr_count($src,$o1);
  if($c===1){ $src=str_replace($o1,$n1,$src); echo "  ✓ [1] CH_SENDPERUSER — gonderiler kullaniciya ozel saklaniyor\n"; $ok++; }
  else echo "  ✗ [1] CH_SENDPERUSER anchor ($c, 1 beklenir) — atlandi\n";
} else { echo "  = [1] zaten ekli\n"; $ok++; }

/* ── [2] CH_QUOTA40 ── */
if(strpos($src,'CH_QUOTA40')===false){
  $o2="const _qm2={basic:20,pro:99999,elite:99999};";
  $n2="const _qm2={basic:40,pro:99999,elite:99999};/*CH_QUOTA40*/";
  $c=substr_count($src,$o2);
  if($c>=1 && $c<=3){ $src=str_replace($o2,$n2,$src); echo "  ✓ [2] CH_QUOTA40 — Basic aylik kota 20 -> 40 ($c yer)\n"; $ok++; }
  else echo "  ✗ [2] CH_QUOTA40 anchor ($c, 1-3 beklenir) — atlandi\n";
} else { echo "  = [2] zaten ekli\n"; $ok++; }

/* ── [3] CH_KONTOCSS ── */
if(strpos($src,'CH_KONTOCSS')===false){
  $css = <<<'CSSBLOCK'
<style id="ch-konto-css">
/* CH_KONTOCSS — Konto premium cila (sadece gorunum) */
.ksec{background:linear-gradient(160deg,rgba(255,255,255,.045),rgba(255,255,255,.015))!important;border:1px solid rgba(255,255,255,.09)!important;border-radius:16px!important;box-shadow:0 8px 26px rgba(0,0,0,.22)!important;padding:16px!important;margin:12px 0!important}
.ksec:hover{border-color:rgba(212,168,74,.25)!important}
.ksec-hdr-t{font-weight:800!important;letter-spacing:.3px}
.ksec-hdr-t::before{content:'';display:inline-block;width:4px;height:14px;border-radius:3px;background:linear-gradient(180deg,#e8c877,#d4a84a);margin-right:8px;vertical-align:-2px}
#konto-plan-badge{background:linear-gradient(135deg,#e8c877,#d4a84a)!important;color:#17171b!important;font-weight:800!important;border-radius:100px!important;padding:4px 14px!important;box-shadow:0 4px 14px rgba(212,168,74,.28)!important;border:0!important}
.ksec input,.ksec select,.ksec textarea{border-radius:10px!important}
.ksec button{border-radius:10px!important;transition:border-color .15s,background .15s}
</style>
CSSBLOCK;
  $pos=strripos($src,'</body>');
  if($pos!==false){ $src=substr($src,0,$pos).$css."\n".substr($src,$pos); echo "  ✓ [3] CH_KONTOCSS — Konto premium CSS eklendi\n"; $ok++; }
  else echo "  ✗ [3] </body> yok — atlandi\n";
} else { echo "  = [3] zaten ekli\n"; $ok++; }

if($ok===0){ echo "\nHICBIR bolum uygulanamadi — HICBIR sey degistirilmedi.\n"; exit; }

$tmp=tempnam(sys_get_temp_dir(),'pk1').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-pkg1-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
echo "\n  Sonuc: $ok/3 bolum tamam (".strlen($chk)." B)\n";
echo "  Test-1: A hesabiyla fax gonder -> Gönderilerim'de gorunur; B hesabina gec ->\n";
echo "          A'nin gonderileri GORUNMEZ; A'ya donunce yine gorunur.\n";
echo "  Test-2: Konto -> bolumler kartli/altin vurgulu premium gorunumde.\n";
