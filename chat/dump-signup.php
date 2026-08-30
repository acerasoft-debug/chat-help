<?php
/**
 * ChatHelp — KAYIT/GİRİŞ AKIŞI DÖKÜMÜ (salt-okunur)
 * =================================================
 * "Email onayı olmadan girişi serbest bırak" için gereken tek şey: sunucudaki
 * register/login/verify kodunu + kullanıcı tablosunu görmek. Bu script onu döker.
 * HİÇBİR ŞEYİ DEĞİŞTİRMEZ. (Şifre/anahtar gibi sabitler maskelenir.)
 *
 * KULLANIM: html/chat/ -> https://chat-help.com/chat/dump-signup.php
 * Çıktının tamamını yapıştır -> tek satırlık kesin yamayı veririm. Sonra SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
@set_time_limit(30);
echo "ChatHelp — Kayıt/Giriş Dökümü  (".date('c').")\n=======================================\n\n";

/* ---- 1) Kullanıcı tablosu şeması (onay kolonu hangisi?) ---- */
echo "=== 1) Kullanıcı tablosu ===\n";
try {
    require_once __DIR__.'/db.php';
    $pdo = function_exists('db') ? db() : (isset($pdo)?$pdo:null);
    if($pdo){
        $tbls = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "  tablolar: ".implode(', ',$tbls)."\n";
        $cand=''; foreach($tbls as $t){ if(preg_match('/user|member|account|kullanic/i',$t)){ $cand=$t; break; } }
        if(!$cand && $tbls) $cand=$tbls[0];
        echo "  seçilen: $cand\n\n  kolonlar:\n";
        foreach($pdo->query("SHOW COLUMNS FROM `$cand`") as $c){
            $mark = preg_match('/verif|confirm|activ|onay|status|email_c/i',$c['Field']) ? '   <-- ONAY KOLONU OLABİLİR' : '';
            echo "    ".str_pad($c['Field'],22)." ".str_pad($c['Type'],18)." def=".($c['Default']??'NULL').$mark."\n";
        }
        // örnek: kaç kullanıcı, kaçı onaysız (tahmini)
        foreach(['verified','is_verified','email_verified','confirmed','email_confirmed','active','status'] as $col){
            try{ $r=$pdo->query("SELECT $col, COUNT(*) n FROM `$cand` GROUP BY $col")->fetchAll(PDO::FETCH_KEY_PAIR);
                 echo "\n  '$col' dağılımı: ".json_encode($r); }catch(Exception $e){}
        }
        echo "\n";
    } else echo "  PDO alınamadı\n";
} catch(Throwable $e){ echo "  DB hata: ".$e->getMessage()."\n"; }

/* ---- 2) auth.php: register / login / verify dökümü ---- */
echo "\n=== 2) auth.php akış dökümü ===\n";
$af=__DIR__.'/auth.php';
if(!file_exists($af)){ echo "  auth.php YOK\n"; exit; }
$a=file_get_contents($af);
// hafif maskeleme (şifre/anahtar sabitleri)
$a=preg_replace("/(pass|secret|key|token)(\w*\s*[:=]\s*)(['\"])[^'\"]{6,}\\3/i",'$1$2$3***MASKED***$3',$a);
echo "  boyut: ".strlen($a)." bayt\n";

function dumpBlock($a,$needle,$label,$max=1800){
    $p=stripos($a,$needle);
    if($p===false){ echo "\n  [$label] bulunamadı ($needle)\n"; return; }
    $i=strpos($a,'{',$p);
    if($i===false || $i-$p>200){ echo "\n  --- $label (bağlam) ---\n".preg_replace('/^/m','  | ',substr($a,max(0,$p-40),$max))."\n"; return; }
    $d=0;$j=$i; do{ if($a[$j]==='{')$d++; elseif($a[$j]==='}')$d--; $j++; }while($d>0 && $j<strlen($a) && ($j-$i)<$max);
    echo "\n  --- $label ---\n".preg_replace('/^/m','  | ',substr($a,max(0,$p-60),min($max,$j-max(0,$p-60))))."\n";
}
// action yönlendirmesi + register + login + verify
dumpBlock($a,"'register'","action: register",1400);
dumpBlock($a,'function register','function register()',2000);
dumpBlock($a,"'login'","action: login",1400);
dumpBlock($a,'function login','function login()',2000);
dumpBlock($a,'verify','verify akışı',1600);

echo "\n  Not: yukarıda register/login yoksa akış switch/if içinde olabilir —\n";
echo "  o zaman 'sendMail(' ve onay kolonu çevresini de basıyoruz:\n";
foreach(['sendMail(','verified','->prepare'] as $kw){
    $p=stripos($a,$kw);
    if($p!==false) echo "\n  --- '$kw' çevresi ---\n".preg_replace('/^/m','  | ',substr($a,max(0,$p-160),420))."\n";
}
echo "\n=== BİTTİ — tüm çıktıyı yapıştır, tek satırlık 'serbest bırak' yamasını vereyim. Sonra dump-signup.php'yi SİL ===\n";
