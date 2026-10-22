<?php
/**
 * ChatHelp — apply-iswvfix — KOK NEDEN DUZELTMESI + teshis alert temizligi.
 *
 * KANIT (dump-iswv): window.isWV=0, function isWV=6 (hepsi IIFE icinde lokal),
 * typeof isWV==='function' = 6 kullanim. Yani makePDF/chPrintDoc icindeki TUM
 * App dallari `_isWv=false` aliyor, hic calismiyor; akis window.print()'e
 * dusuyor ve Android WebView'de sessizce hicbir sey olmuyor.
 *
 * [A] Teshis alert'lerini siler (CH_APPDIAG/2/3) — bunlar WEB kullanicisina
 *     popup olarak cikiyordu, acil.
 * [B] window.isWV'yi GLOBAL tanimlar (IIFE'lerdeki mantigin aynisi). Boylece
 *     6 App dali da dogru calisir: 'Kostenlos selbst ausdrucken' -> dl.php ->
 *     gercek PDF. (Ayrica App mikrofon dallari da amaclandigi gibi devreye girer.)
 * [C] App dalina sessiz beacon: hangi dalin calistigini log'a yazar.
 *
 * Idempotent + lint + .bak. Geri alma: apply-iswvfix-off.php
 * KULLANIM: /chat/pull2.php?key=...&files=apply-iswvfix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
echo "apply-iswvfix BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
$new=$src;

/* ── [A] teshis alert'lerini sil ── */
$before=substr_count($new,'CH_APPDIAG');
$new=preg_replace('/\s*\/\*CH_APPDIAG[23]?\*\/ try\{.*?\}catch\(_e2\)\{\}\}/s','',$new);
if($new===null) exit("preg hatasi (A)\n");
$after=substr_count($new,'CH_APPDIAG');
echo "[A] teshis alert temizligi: CH_APPDIAG $before -> $after\n";

/* ── [B] window.isWV global ── */
if(strpos($new,'CH_ISWV_GLOBAL')!==false){
  echo "[B] (zaten ekli — atlandi)\n";
} else {
  $js='<script>/*CH_ISWV_GLOBAL*/window.isWV=function(){try{var ua=navigator.userAgent||"";'
    .'if(ua.indexOf("; wv)")!==-1)return true;'
    .'if(/Android/.test(ua)&&/Version\/\d/.test(ua)&&/Chrome\//.test(ua))return true;'
    .'if(window.chIsApp===true)return true;return false;}catch(e){return false;}};</script>';
  $p=stripos($new,'</head>');
  if($p===false) exit("[B] ✗ </head> bulunamadi — DEGISIKLIK YOK\n");
  $new=substr($new,0,$p).$js.substr($new,$p);
  echo "[B] ✓ window.isWV GLOBAL tanimlandi (</head> oncesi)\n";
}

/* ── [C] App dalina sessiz beacon ── */
if(strpos($new,'CH_BRANCHLOG')!==false){
  echo "[C] (zaten ekli — atlandi)\n";
} else {
  $pat='/(var _isWv=false;\s*try\{\s*_isWv=\(typeof isWV===.function.\)&&isWV\(\);\s*\}catch\(e0\)\{\})/';
  $bc='$1 /*CH_BRANCHLOG*/try{new Image().src="chlog9k1.php?k=chl_2607&t="+(new Date().getTime())+"&d=BRANCH+isWv="+_isWv;}catch(_eb){}';
  $n2=preg_replace($pat,$bc,$new,1,$c3);
  if($n2!==null && $c3>0){ $new=$n2; echo "[C] ✓ App dali beacon eklendi\n"; }
  else echo "[C] (anchor bulunamadi — beacon atlandi, kritik degil)\n";
}

if($new===$src) exit("\nHicbir degisiklik olmadi.\n");

$tmp=tempnam(sys_get_temp_dir(),'iw').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0) exit("\n✗ LINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n");

@file_put_contents($file.'.bak-iswvfix-'.date('Ymd-His'),$src);
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();

$chk=(string)@file_get_contents($file);
echo "\n✓ TAMAM (".strlen($src)." -> ".strlen($chk)." bayt)\n";
echo "  CH_ISWV_GLOBAL=".substr_count($chk,'CH_ISWV_GLOBAL')."  CH_APPDIAG=".substr_count($chk,'CH_APPDIAG')."  CH_BRANCHLOG=".substr_count($chk,'CH_BRANCHLOG')."\n";
echo "\nTEST:\n";
echo " 1) Android App'i TAM kapat-ac -> dilekce -> 'Kostenlos selbst ausdrucken'.\n";
echo " 2) PDF inerse: BITTI. Inmezse Mac'te (z'yi degistir):\n";
echo "    curl -s \"https://chat-help.com/chat/chlog9k1.php?k=chl_2607&read=1&z=31337\"\n";
echo "    -> 'BRANCH isWv=true' gorunurse App dali artik CALISIYOR, kalan is sadece indirme.\n";
