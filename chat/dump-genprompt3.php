<?php
/**
 * ChatHelp — dump-genprompt3 (SALT-OKUR) — UYDURMA VERI yamasi icin son teshis.
 *
 * Bulgu (dump-fake): kodda sabit sahte veri YOK. Sorun su:
 *  · CH_ANON_FINAL geregi gercek ad/adres/tel/e-posta AI'ya HIC gitmiyor,
 *    yerine [VORNAME]/[ADRESSE] gibi placeholder konuyor, sonra sunucuda
 *    gercek deger geri yerlestiriliyor.
 *  · Ama prompt'ta "veri UYDURMA" yasagi yok (nicht erfinden x0) -> AI,
 *    placeholder verilmemis bir alani (ornegin e-posta) kendi kafasindan
 *    doldurabiliyor.
 *
 * Yamayi birebir dogru yazabilmem icin gereken 4 sey:
 *  [1] $ph placeholder dizisinin TAMAMI (hangi alanlar var, e-posta var mi)
 *  [2] AI'ya giden prompt'un kurulumu (kurallarin yazildigi yer)
 *  [3] Placeholder'larin geri yerlestirildigi yer (CH_ANON_FINAL)
 *  [4] Istemcide profil (window.P) nasil doluyor; Konto e-postasi f7'ye
 *      akiyor mu — akmiyorsa geri yerlestirecek gercek deger de yok demektir
 *
 * HICBIR SEY DEGISTIRILMEZ.
 * KULLANIM: /chat/pull2.php?key=...&files=dump-genprompt3.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
echo "== dump-genprompt3 (SALT-OKUR) ==\n\n";
$dir=__DIR__;
$api=(string)@file_get_contents($dir.'/api.php');
if($api===''){ echo "✗ api.php okunamadi\n"; exit; }
echo "api.php: ".strlen($api)." B  (".date('Y-m-d H:i',filemtime($dir.'/api.php')).")\n";

/* ── [1] $ph dizisi ── */
echo "\n=== [1] \$ph placeholder dizisi (TAM) ===\n";
$p=strpos($api,'$ph = [');
if($p===false) $p=strpos($api,'$ph=[');
if($p===false){ echo "  (bulunamadi)\n"; }
else{
  $e=strpos($api,'];',$p);
  echo substr($api,max(0,$p-260),($e!==false?($e-$p+3):900)+260)."\n";
}

/* ── [2] prompt kurulumu: $ph'den sonra AI cagrisina kadar ── */
echo "\n=== [2] Prompt kurulumu ve AI cagrilari ===\n";
foreach(['$answersText','$userPrompt','$prompt =','$prompt.=','$prompt .=','callClaude(','callDeepSeek('] as $k){
  $c=substr_count($api,$k);
  echo "\n  ── $k  (x$c) ──\n";
  $off=0;$n=0;
  while(($q=strpos($api,$k,$off))!==false && $n<3){
    echo "   @$q ...".preg_replace('/\s+/',' ',substr($api,max(0,$q-200),620))."...\n";
    $off=$q+strlen($k); $n++;
  }
  if(!$n) echo "   (yok)\n";
}

/* ── [3] anonimlestirme + geri yerlestirme ── */
echo "\n\n=== [3] CH_ANON_FINAL — placeholder geri yerlestirme ===\n";
$q=strpos($api,'CH_ANON_FINAL');
echo $q!==false ? substr($api,max(0,$q-2400),3000)."\n" : "  (bulunamadi)\n";

/* ── [4] mevcut kural metinleri (uydurma ile ilgili) ── */
echo "\n=== [4] Mevcut kural metinleri ===\n";
foreach(['erfinde','Erfinde','UYDURMA','uydurma','Verwende ausschliesslich','nur die angegebenen',
         'Wenn eine Information fehlt','fehlende','NICHT','WICHTIG'] as $k){
  $c=substr_count($api,$k);
  if(!$c) continue;
  echo "\n  [$k] x$c\n";
  $off=0;$n=0;
  while(($q=strpos($api,$k,$off))!==false && $n<2){
    echo "     @$q ...".preg_replace('/\s+/',' ',substr($api,max(0,$q-200),520))."...\n";
    $off=$q+strlen($k); $n++;
  }
}

/* ── [5] istemci: window.P nasil doluyor, e-posta f7'ye geliyor mu ── */
echo "\n\n================================================\n";
echo "=== [5] index.php — profil (window.P) ve f7 (e-posta) ===\n";
$idx=(string)@file_get_contents($dir.'/index.php');
if($idx===''){ echo "  okunamadi\n"; }
else{
  echo "  index.php: ".strlen($idx)." B\n";
  echo "\n  -- 'f7' gecisleri (e-posta alani) --\n";
  $off=0;$n=0;
  while(($q=strpos($idx,'f7',$off))!==false && $n<10){
    $seg=preg_replace('/\s+/',' ',substr($idx,max(0,$q-160),300));
    echo "   @$q ...$seg...\n";
    $off=$q+2;$n++;
  }
  if(!$n) echo "   (f7 hic gecmiyor!)\n";

  echo "\n  -- window.P / prof() tanimi --\n";
  foreach(['function prof(','window.P=','window.P =','var P=','P = JSON.parse'] as $k){
    $q=strpos($idx,$k);
    if($q!==false) echo "   [$k @$q] ...".preg_replace('/\s+/',' ',substr($idx,max(0,$q-120),480))."...\n";
  }

  echo "\n  -- ch_prof_v3 yazma/okuma (ilk 4) --\n";
  $off=0;$n=0;
  while(($q=strpos($idx,'ch_prof_v3',$off))!==false && $n<4){
    $seg=preg_replace('/\s+/',' ',substr($idx,max(0,$q-140),300));
    $kind=(stripos($seg,'setItem')!==false)?'YAZMA':((stripos($seg,'getItem')!==false)?'OKUMA':'?');
    echo "   [$kind] @$q ...$seg...\n";
    $off=$q+10;$n++;
  }

  echo "\n  -- Konto e-postasi profile akiyor mu (ch_user -> f7) --\n";
  $hit=false;
  foreach(['ch_user','user.email'] as $k){
    $off=0;$n=0;
    while(($q=strpos($idx,$k,$off))!==false && $n<14){
      $seg=preg_replace('/\s+/',' ',substr($idx,max(0,$q-140),300));
      if(strpos($seg,'f7')!==false){ echo "   ✓ @$q ...$seg...\n"; $hit=true; $n++; }
      $off=$q+strlen($k);
    }
  }
  if(!$hit) echo "   ✗ BAGLANTI YOK — Konto e-postasi profil f7'ye HIC aktarilmiyor.\n";

  echo "\n  -- profil form alanlarinin etiketleri (f1..f8) --\n";
  foreach(['f1','f2','f3','f4','f5','f6','f7','f8'] as $k){
    $q=strpos($idx,"id=\"pf-$k\"");
    if($q===false) $q=strpos($idx,"'pf-$k'");
    if($q!==false) echo "   $k: ...".preg_replace('/\s+/',' ',substr($idx,max(0,$q-100),220))."...\n";
  }
}

echo "\n== BITTI — ciktinin TAMAMINI gonder ==\n";
