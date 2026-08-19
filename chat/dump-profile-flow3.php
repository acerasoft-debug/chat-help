<?php
/** ChatHelp — dump-profile-flow3: Konto panelinin 829000-834000 arasi (Profil satirlari burada).
 *  SADECE OKUR. SIL: rm dump-profile-flow3.php */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
$idx=@file_get_contents(__DIR__.'/index.php');
if($idx===false) exit("index.php yok\n");
$p=strpos($idx,'id="pnl-konto"');
echo "pnl-konto @".($p===false?'YOK':$p)."\n";
echo "\n──── HAM PENCERE: Profil satirlari bolgesi ────\n";
echo substr($idx, 828800, 5000)."\n";
echo "\n=== BITTI. TAMAMINI yapistir. SIL: rm dump-profile-flow3.php ===\n";
