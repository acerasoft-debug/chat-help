<?php
/**
 * ChatHelp — apply-fix-fotoframe (CH_FIX_FOTOFRAME) — foto okumasi ACIL duzeltme
 *  (index.php frontend). Analiz (workflow) 2 yuksek-olasilikli kok neden buldu:
 *   [1] CH_FOTO_GATE paywall'i analyze_photo'yu YUTUYOR: isPaid() plan'i
 *       normalize etmeden ['basic','pro','elite'] listesinde ariyor; plani
 *       'Pro'/'premium'/'elite_monthly' gibi gelen VEYA JWT'de bayat 'free'
 *       kalan ODEMIS kullanicida fetch sahte {error:'paid_only'} donuyor ->
 *       ne ozet ne ceviri geliyor.
 *       DUZELTME: isPaid()'i normalize + izin-verici yap (token varsa ve plan
 *       elite/pro/basic/premium/unbegrenzt iceriyorsa gecir; sunucu zaten
 *       kotayi uygular). Anchor: isPaid tanimi (kaynak birebir).
 *   [2] CH_FOTOREAD_FIX dinleyici sirasi bug'i: input'a dogrudan capture
 *       ('change',onPick,true) baglanmis ama bu AT_TARGET fazidir; composer'in
 *       kendi handler'i input.value='' ile FileList'i ONCE temizliyor -> inp.
 *       files bos -> analyze_photo hic tetiklenmiyor.
 *       DUZELTME: input yerine DOCUMENT seviyesinde capture dinleyici ekle
 *       (ancestor capture, AT_TARGET'ten ONCE calisir) -> dosyalar temizlenmeden
 *       okunur. Eski input-bagli dinleyici no-op'a cevrilir (cifte analiz yok).
 *  2 count-guard'li str_replace. node --check ✓ + harness ✓.
 * KULLANIM: pull2.php?key=...&files=apply-fix-fotoframe.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fix-fotoframe BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_FIX_FOTOFRAME')!==false) exit("Zaten ekli (CH_FIX_FOTOFRAME).\n");

$fail=[];

/* ── [1] foto-gate isPaid normalize + izin-verici ── */
$o1 = "  function isPaid(){ return !!localStorage.getItem('ch_token') && ['basic','pro','elite'].indexOf(plan())>-1; }";
$n1 = "  function isPaid(){ /*CH_FIX_FOTOFRAME*/ try{ if(!localStorage.getItem('ch_token')) return false; var p=plan(); if(!p) return true; /* giris var, plan okunamiyor -> gecir (sunucu kotayi uygular) */ return /elite|pro|basic|premium|unbegrenzt|unlimited|aktiv/.test(p); }catch(e){ return true; } }";
$c1 = substr_count($src,$o1);
if ($c1!==1){ echo "  ✗ [1] isPaid anchor ($c1)\n"; $fail[]='1'; }
else { $src=str_replace($o1,$n1,$src); echo "  ✓ [1] Foto paywall: plan normalize + izin-verici (odemis kullanici artik engellenmez)\n"; }

/* ── [2] fotoread: input-capture -> document-capture ── */
$o2 = <<<'ANC'
  function bind(){
    try{
      document.querySelectorAll('.ch-att-btn input[type=file]').forEach(function(inp){
        if(inp.__fread) return; inp.__fread=1;
        /* CAPTURE fazi: composer'in onPick'i input.value''yi silmeden ONCE dosyalari oku */
        inp.addEventListener('change', onPick, true);
      });
    }catch(e){}
  }
ANC;
$n2 = <<<'ANC'
  function bind(){
    try{
      /*CH_FIX_FOTOFRAME: document seviyesinde capture -> input.value='' temizliginden ONCE okur*/
      if(!document.__freadDoc){
        document.__freadDoc=1;
        document.addEventListener('change', function(ev){
          try{
            var t=ev.target;
            if(t && t.type==='file' && t.closest && t.closest('.ch-att-btn')){ onPick(ev); }
          }catch(e){}
        }, true);
      }
      /* eski input-bagli dinleyiciyi devre disi birak (cifte analiz olmasin) */
      document.querySelectorAll('.ch-att-btn input[type=file]').forEach(function(inp){ inp.__fread=1; });
    }catch(e){}
  }
ANC;
$c2 = substr_count($src,$o2);
if ($c2!==1){ echo "  ✗ [2] fotoread bind anchor ($c2)\n"; $fail[]='2'; }
else { $src=str_replace($o2,$n2,$src); echo "  ✓ [2] Foto okuma: document-capture (dosyalar temizlenmeden okunur, analiz tetiklenir)\n"; }

if ($fail) { echo "\nHATA: eslesmeyen: ".implode(', ',$fail)." — index DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(),'ff').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-fotoframe-'.date('Ymd-His'), (string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_FIX_FOTOFRAME')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "\n  ✓ DOGRULAMA: CH_FIX_FOTOFRAME diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Foto okuma iki koktan onarildi:\n";
echo "   • Odemis kullanicida paywall artik yanlis engellemiyor (plan normalize)\n";
echo "   • 📎 ile secilen foto artik gercekten okunuyor (document-capture) ->\n";
echo "     ozet/ceviri sohbete gelir.\n";
