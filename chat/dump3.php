<?php
/**
 * ChatHelp — dump3 (SADECE OKUR): e-posta + foto + fiyat için gerçek kod
 * KULLANIM: curl -s -A "Mozilla/5.0" "https://chat-help.com/chat/dump3.php"
 * Çıktıyı bana gönder. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
function R($s,$needle,$before,$len,$lbl){
    echo "\n──── $lbl  ('$needle') ────\n";
    if($s===false){echo "(dosya yok)\n";return;}
    $p=strpos($s,$needle);
    if($p===false){echo "(BULUNAMADI)\n";return;}
    echo substr($s,max(0,$p-$before),$len)."\n";
}
$idx=@file_get_contents(__DIR__.'/index.php');
$api=@file_get_contents(__DIR__.'/api.php');
$auth=@file_get_contents(__DIR__.'/auth.php');
$db=@file_get_contents(__DIR__.'/db.php');
$cfg=@file_get_contents(__DIR__.'/config.php');

echo "### E-POSTA / SMTP ###\n";
R($auth,'function sendMail',0,2200,'sendMail TAM gövde');
foreach(['SMTP_HOST','SMTP_PORT','SMTP_USER','SMTP_PASS','SMTP_FROM'] as $k){
    foreach(['db.php'=>$db,'config.php'=>$cfg,'auth.php'=>$auth] as $fn=>$fs){
        if($fs!==false && strpos($fs,$k)!==false){
            $p=strpos($fs,$k);
            echo "  $k ($fn): ".trim(substr($fs,$p,80))."\n"; break;
        }
    }
}
R($auth,'function doRegister',0,1500,'doRegister gövde (mail çağrısı dahil)');

echo "\n\n### FOTO: genDoc + showQuestions ###\n";
R($idx,'function genDoc',0,1400,'genDoc TAM gövde (fetch body görünür)');
R($idx,'showQuestions',0,1600,'showQuestions (alan üretimi)');
R($idx,"innerHTML='<label>",0,400,'soru alanı innerHTML');
R($idx,'q.l',0,300,'q.l kullanımı (alan etiketi)');

echo "\n\n### FOTO: api.php generate ###\n";
R($api,'function doGenerate',0,1800,'doGenerate (Claude çağrısı)');
R($api,'function doGen',0,1200,'doGen (varsa)');
R($api,'claude(',0,500,'claude() çağrısı (api)');
R($api,'function claude',0,700,'claude() tanımı (api)');

echo "\n\n### FOTO: Fall solve çağrısı (index) ###\n";
R($idx,'fall_solve',0,500,'index fall_solve fetch');

echo "\n\n### FİYAT: 9,99 ve Free ###\n";
R($idx,'9,99',60,200,'9,99 geçişi');
R($idx,"'free'",0,300,'free plan tanımı');
R($idx,'Free',0,200,'Free metni');

echo "\n=== BİTTİ. SİL: rm dump3.php ===\n";
