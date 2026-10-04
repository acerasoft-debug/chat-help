<?php
/** ChatHelp — dump-db (SADECE OKUR) — admin paneli (musteri/paket yonetimi) +
 *  free-doc IP/email kotasi icin DB altyapisi. HASSAS DEGERLER MASKELENIR.
 *  [1] auth.php basi: require/include (db baglantisi nereden geliyor)
 *  [2] db() fonksiyonu tanimi (hangi dosyada, PDO nasil kuruluyor) — sifre maskeli
 *  [3] config.php: tanimli sabit ISIMLERI (degerler maskeli)
 *  [4] requireAuth() tanimi (free_claim login kontrolu icin)
 *  [5] user_plans'a plan YAZMA ornegi (varsa) + tablo kolonlari
 *  [6] admin.php: 3500 sonrasi (mevcut yapinin geri kalani, musteri bolumu nereye) */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$auth=(string)@file_get_contents("$D/auth.php");
$admin=(string)@file_get_contents("$D/admin.php");

function maskLine($s){
  /* sk-... , sifre, host kimlik bilgilerini maskele */
  $s=preg_replace('/(sk-[a-zA-Z0-9_-]{6})[a-zA-Z0-9_-]+/','$1***MASKED***',$s);
  $s=preg_replace("/(define\\('DB_PASS[^']*',\\s*')[^']*(')/i",'$1***MASKED***$2',$s);
  $s=preg_replace("/(define\\('DB_USER[^']*',\\s*')[^']*(')/i",'$1***MASKED***$2',$s);
  $s=preg_replace("/(password[^,=]*[=,]\\s*['\"])[^'\"]+/i",'$1***MASKED***',$s);
  return $s;
}
function fnBody($s,$decl,$cap=800){
  $p=strpos($s,$decl); if($p===false) return "(YOK: $decl)";
  $b=strpos($s,'{',$p); if($b===false) return substr($s,$p,150);
  $depth=0;$i=$b;$len=strlen($s);
  for(;$i<$len;$i++){ $c=$s[$i]; if($c==='{')$depth++; elseif($c==='}'){ $depth--; if($depth===0){ $i++; break; } } }
  return substr($s,$p,min($i-$p,$cap)).(($i-$p)>$cap?"\n…":"");
}

echo "════ [1] auth.php basi (require/include) ════\n";
echo maskLine(substr($auth,0,900))."\n";

echo "\n════ [2] db() fonksiyonu ════\n";
if(strpos($auth,'function db(')!==false){ echo "auth.php'de:\n".maskLine(fnBody($auth,'function db(',700))."\n"; }
else {
    echo "auth.php'de YOK — require edilen dosyalarda araniyor:\n";
    foreach(['config.php','db.php','database.php','conn.php','init.php'] as $f){
        $p="$D/$f";
        if(is_file($p)){
            $cc=(string)@file_get_contents($p);
            echo "  $f (".strlen($cc)." bayt): ".(strpos($cc,'function db(')!==false?"db() VAR":"db() yok")."\n";
            if(strpos($cc,'function db(')!==false){ echo maskLine(fnBody($cc,'function db(',700))."\n"; }
        }
    }
}

echo "\n════ [3] config.php sabit isimleri (degerler maskeli) ════\n";
$cfg=(string)@file_get_contents("$D/config.php");
if($cfg!==''){
    if(preg_match_all("/define\\('([A-Z_]+)'/",$cfg,$m)){ echo "  tanimlar: ".implode(', ',$m[1])."\n"; }
    echo "  (config.php ".strlen($cfg)." bayt)\n";
} else echo "  (config.php YOK/okunamadi)\n";

echo "\n════ [4] requireAuth() ════\n";
echo maskLine(fnBody($auth,'function requireAuth(',700))."\n";

echo "\n════ [5] user_plans YAZMA (plan atama) ════\n";
$off=0;$n=0;
foreach(['INSERT INTO user_plans','UPDATE user_plans','REPLACE INTO user_plans'] as $nd){
    $p=strpos($auth,$nd);
    echo "  '$nd': ".($p!==false?"@$p: ".str_replace("\n"," ",substr($auth,$p,180)):"YOK")."\n";
}

echo "\n════ [6] admin.php geri kalani (3500-9000) ════\n";
echo strlen($admin)>3500 ? substr($admin,3500,5500)."\n" : "(admin.php kisa)\n";

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
