<?php
/**
 * ChatHelp — E-POSTA ONAYI OLMADAN GİRİŞİ SERBEST BIRAK (DB seviyesi, geri alınabilir)
 * =================================================================================
 * Kullanıcı tablosundaki onay kolonunu bulur; NET isimliyse:
 *   1) mevcut TÜM kullanıcıları onaylı yapar (UPDATE col=1)  -> mevcut hesaplar girer
 *   2) kolonun DEFAULT'unu 1 yapar
 *   3) BEFORE INSERT trigger ekler -> YENİ kayıtlar otomatik onaylı (register kodu ne derse desin)
 * Kırılgan PHP auth mantığına dokunmaz. Her adım try/catch + rapor. Geri alma komutları yazılır.
 * Ayrıca register/login/verify kodunu döker (gerekirse PHP yamasını sonra veririm).
 *
 * KULLANIM:
 *   Önizleme (yazmaz):  https://chat-help.com/chat/open-signup.php
 *   UYGULA:             https://chat-help.com/chat/open-signup.php?apply=1
 * Çıktının tamamını yapıştır. Bitince opcache-reset.php aç, open-signup.php'yi SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
@set_time_limit(30);
$apply = isset($_GET['apply']);
echo "ChatHelp — Girişi Serbest Bırak  (".date('c').")   MOD: ".($apply?"UYGULA":"ÖNİZLEME")."\n=================================================\n\n";

require_once __DIR__.'/db.php';
try { $pdo = function_exists('db') ? db() : (isset($pdo)?$pdo:null); }
catch(Throwable $e){ exit("DB bağlantısı hata: ".$e->getMessage()."\n"); }
if(!$pdo){ exit("PDO alınamadı (db.php).\n"); }

/* 1) tablo + onay kolonu bul */
$tbls = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "tablolar: ".implode(', ',$tbls)."\n";
$T=''; foreach($tbls as $t){ if(preg_match('/user|member|account|kullanic/i',$t)){ $T=$t; break; } }
if(!$T){ echo "kullanıcı tablosu bulunamadı — dökümü aşağıda incele.\n"; }
echo "kullanıcı tablosu: ".($T?:'(?)')."\n\n";

$col=''; $colType='';
if($T){
    echo "kolonlar:\n";
    $cols=$pdo->query("SHOW COLUMNS FROM `$T`")->fetchAll(PDO::FETCH_ASSOC);
    $WL=['verified','is_verified','email_verified','confirmed','email_confirmed','is_active','active','activated'];
    foreach($cols as $c){
        $isVer = in_array(strtolower($c['Field']),$WL,true) && preg_match('/int|bool|bit|tinyint/i',$c['Type']);
        if($isVer && !$col){ $col=$c['Field']; $colType=$c['Type']; }
        echo "  ".str_pad($c['Field'],22)." ".str_pad($c['Type'],16)." def=".($c['Default']??'NULL').($isVer?"   <== ONAY KOLONU":"")."\n";
    }
    echo "\nseçilen onay kolonu: ".($col?:'(net kolon yok — DB açma atlanacak, PHP yaması gerekebilir)')."\n";
    if($col){
        try{ $dist=$pdo->query("SELECT `$col` v, COUNT(*) n FROM `$T` GROUP BY `$col`")->fetchAll(PDO::FETCH_KEY_PAIR);
             echo "  '$col' dağılımı (önce): ".json_encode($dist)."\n"; }catch(Exception $e){}
    }
}

/* 2) uygula */
if($col){
    echo "\n=== DB açma ".($apply?"(UYGULANIYOR)":"(önizleme — ?apply=1 ile uygular)")." ===\n";
    $trig='ch_auto_verify';
    $steps=[
        "UPDATE `$T` SET `$col`=1 WHERE `$col`<>1 OR `$col` IS NULL",
        "ALTER TABLE `$T` ALTER `$col` SET DEFAULT 1",
        "DROP TRIGGER IF EXISTS `$trig`",
        "CREATE TRIGGER `$trig` BEFORE INSERT ON `$T` FOR EACH ROW SET NEW.`$col`=1",
    ];
    foreach($steps as $sql){
        echo "  ".( $apply ? "» " : "(atlanır) " ).$sql."\n";
        if($apply){
            try{ $n=$pdo->exec($sql); echo "     -> OK".($n!==false?" (etkilenen: $n)":"")."\n"; }
            catch(Throwable $e){ echo "     -> HATA: ".$e->getMessage()."\n"; }
        }
    }
    if($apply){
        try{ $dist=$pdo->query("SELECT `$col` v, COUNT(*) n FROM `$T` GROUP BY `$col`")->fetchAll(PDO::FETCH_KEY_PAIR);
             echo "  '$col' dağılımı (sonra): ".json_encode($dist)."\n"; }catch(Exception $e){}
    }
    echo "\n  GERİ ALMA (gerekirse): DROP TRIGGER IF EXISTS `$trig`;  ve istersen `$col`=0 yaparsın.\n";
}

/* 3) PHP akış dökümü (yedek — DB yetmezse PHP yaması için) */
echo "\n=== register/login/verify (inceleme) ===\n";
$af=__DIR__.'/auth.php';
if(file_exists($af)){
    $a=file_get_contents($af);
    $a=preg_replace("/(pass|secret|key|token)(\w*\s*[:=]\s*)(['\"])[^'\"]{6,}\\3/i",'$1$2$3***$3',$a);
    foreach([['function register','register()'],['function login','login()'],['verify','verify']] as $kw){
        $p=stripos($a,$kw[0]);
        if($p!==false){ $i=strpos($a,'{',$p); $seg = ($i!==false && $i-$p<200)
            ? (function()use($a,$i,$p){$d=0;$j=$i;do{if($a[$j]==='{')$d++;elseif($a[$j]==='}')$d--;$j++;}while($d>0&&$j<strlen($a)&&($j-$i)<1600);return substr($a,max(0,$p-50),min(1650,$j-max(0,$p-50)));})()
            : substr($a,max(0,$p-50),700);
            echo "\n--- {$kw[1]} ---\n".preg_replace('/^/m','  | ',$seg)."\n";
        }
    }
} else echo "  auth.php yok\n";

echo "\n=== SONUÇ ===\n";
echo $col
  ? ($apply ? "  ✅ DB açıldı: mevcut kullanıcılar onaylı + yeni kayıtlar otomatik onaylı.\n     opcache-reset.php aç -> yeni kayıt + giriş dene.\n"
            : "  ÖNİZLEME. Uygulamak için: open-signup.php?apply=1\n")
  : "  Net onay kolonu yok. Yukarıdaki dökümü yapıştır, PHP tarafına tam yamayı vereyim.\n";
echo "  Bitince: open-signup.php'yi SİL.\n";
