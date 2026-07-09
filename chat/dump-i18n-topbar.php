<?php
/** ChatHelp — dump-i18n-topbar (SADECE OKUR)
 *  Iki sorunu teshis eder:
 *   [A] Sablon alan basliklari Almanca+cevrilmis dil UST USTE gorunuyor —
 *       genel i18n mekanizmasi Almanca metni DEGISTIRMIYOR, altina cevrilmis
 *       span EKLIYOR. Bu fonksiyonu bulur.
 *   [B] Ust barda hem profil adi (Serdar) hem hesap adi (Acerasoft) birlikte
 *       gorunuyor — ikisinin de nereden render edildigini bulur.
 *  Ciktinin TAMAMINI gonder. SIL: rm dump-i18n-topbar.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx = @file_get_contents(__DIR__.'/index.php');
if ($idx===false) exit("index.php okunamadi\n");

echo "════════ [A] GENEL i18n CEVIRI MEKANIZMASI ════════\n\n";
/* i18n ile ilgili fonksiyon adaylari */
foreach (['function tI18n','function i18n','function translateField','function applyI18n','function trAll','function tField','data-i18n-done'] as $needle) {
    $n = substr_count($idx, $needle);
    echo "  '$needle' x$n\n";
}
echo "\n--- 'insertAdjacent' veya 'span' ekleyerek ceviri ekleyen kod baglamlari ---\n";
$off=0;$n=0;
while(($p=stripos($idx,'i18n',$off))!==false && $n<40){
    $seg=substr($idx,max(0,$p-70),150); $seg=preg_replace('/\s+/',' ',$seg);
    echo "  @".$p.": ...".$seg."...\n";
    $off=$p+4; $n++;
}

echo "\n\n════════ [B] UST BAR KIMLIK GOSTERIMI (Serdar + Acerasoft) ════════\n\n";
foreach (['ch-actprof','tb-auth-btn','tb-auth-name','function syncChip','account_name','acct_name'] as $needle) {
    $n = substr_count($idx, $needle);
    echo "  '$needle' x$n\n";
}
echo "\n--- tb-auth-btn / hesap adi render baglamlari ---\n";
$off=0;$n=0;
while(($p=stripos($idx,'tb-auth',$off))!==false && $n<20){
    $seg=substr($idx,max(0,$p-80),180); $seg=preg_replace('/\s+/',' ',$seg);
    echo "  @".$p.": ...".$seg."...\n";
    $off=$p+8; $n++;
}
echo "\n--- ch-actprof (profil cipi) render baglamlari ---\n";
$off=0;$n=0;
while(($p=stripos($idx,'ch-actprof',$off))!==false && $n<20){
    $seg=substr($idx,max(0,$p-80),180); $seg=preg_replace('/\s+/',' ',$seg);
    echo "  @".$p.": ...".$seg."...\n";
    $off=$p+10; $n++;
}

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-i18n-topbar.php ════════\n";
