<?php
/* Admin ▸ Messages: konusmanin ICINDE en yeni mesaj USTTE, eskiler asagi; cevap
 * kutusu en ustte.
 *
 * NEDEN VAR (operator, 5 Eki 2026: "Vestra mesajlarinda en basta yeni mesajlar
 * gorunsun en alta dogru eskiler gitsin"). Olculdu: konusma KARTLARI zaten
 * last_at'e gore yeni-once siraliydi (admin.php usort, vestra_msg_my_threads);
 * ters duran kartin ICIydi. "Read conversation" mesajlari eskiden yeniye
 * diziyordu, 90+ mesajlik bir konusmada son mesaj sayfanin dibinde kaliyordu ve
 * cevap kutusu onun da altindaydi.
 *
 * NEDEN KUM HAVUZUNDA GERCEKTEN CIZDIRILIYOR: olculmesi gereken sey uretilen
 * HTML'deki SIRA. Kaynakta `array_reverse` gormek, onun dogru dongude oldugunu
 * soylemez.
 *
 * IKI YON:
 *   - gosterim ters, ama KAYIT kronolojik kalmali (okundu sayaci read[] konuma
 *     bakiyor; kaydi tersine yazmak her musterinin onay isaretini bozardi);
 *   - musteri sohbet paneli (vestra_msg_panel_html) BILEREK degismedi: orada
 *     yazma kutusu altta ve sayfa en yeni mesaja kayiyor. Tek yon olculseydi
 *     "her yerde tersine ceviren" bir degisiklik de yesil kalirdi. */

$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? "  ok   " : "  HATA ").$n."\n"; };

$root = dirname(__DIR__);
$sb = sys_get_temp_dir().'/vestra_msgord_'.getmypid();
exec('rm -rf '.escapeshellarg($sb));
@mkdir($sb, 0777, true);
$rc = 0; $o = [];
exec('cp -r '.escapeshellarg($root.'/vestra').' '.escapeshellarg($sb.'/vestra').' 2>&1', $o, $rc);
$t('kum havuzu kuruldu', $rc === 0);
exec('rm -rf '.escapeshellarg($sb.'/vestra/data'));
@mkdir($sb.'/vestra/data', 0777, true);
file_put_contents($sb.'/vestra/inc/config.php', "<?php return ['admin_pass'=>'x','mail_enabled'=>false];\n");

$BUY_A = 'b00000000000a001'; $BUY_B = 'b00000000000b002'; $SELL = 's00000000000c003';
file_put_contents($sb.'/vestra/data/accounts.json', json_encode([
  ['id'=>$BUY_A,'type'=>'buyer','email'=>'a@example.test','company'=>'OLDTHREAD Buyer','name'=>'A','status'=>'active'],
  ['id'=>$BUY_B,'type'=>'buyer','email'=>'b@example.test','company'=>'NEWTHREAD Buyer','name'=>'B','status'=>'active'],
  ['id'=>$SELL,'type'=>'seller','email'=>'s@example.test','company'=>'SELLER Test','name'=>'S','status'=>'active'],
]));

/* Dosyada ESKI konusma once duruyor: kart sirasini dosya degil last_at belirlemeli. */
$threads = [
  ['id'=>'th-old','buyer_uid'=>$BUY_A,'seller_uid'=>$SELL,'listing_id'=>'',
   'last_at'=>'2026-09-01T10:00:00+00:00','read'=>[],
   'messages'=>[['at'=>'2026-09-01T10:00:00+00:00','from'=>$BUY_A,'text'=>'OLD-THREAD-ONLY']]],
  ['id'=>'th-new','buyer_uid'=>$BUY_B,'seller_uid'=>$SELL,'listing_id'=>'',
   'last_at'=>'2026-10-04T12:00:00+00:00','read'=>[$BUY_B=>3,$SELL=>2],
   'messages'=>[
     ['at'=>'2026-10-02T09:00:00+00:00','from'=>$BUY_B,'text'=>'MSG-OLDEST'],
     ['at'=>'2026-10-03T09:00:00+00:00','from'=>$SELL,'text'=>'MSG-MIDDLE'],
     ['at'=>'2026-10-04T12:00:00+00:00','from'=>$BUY_B,'text'=>'MSG-NEWEST'],
   ]],
];
$msgFile = $sb.'/vestra/data/messages.json';
file_put_contents($msgFile, json_encode($threads));
$before = md5_file($msgFile);

file_put_contents($sb.'/render.php', <<<'PHP'
<?php
error_reporting(E_ALL); ini_set('display_errors','1');
session_start(); $_SESSION['vadmin']=true;
$_GET=['tab'=>'messages']; $_SERVER['REQUEST_METHOD']='GET';
$_SERVER['REQUEST_URI']='/admin?tab=messages'; $_SERVER['REMOTE_ADDR']='127.0.0.1';
$_SERVER['HTTP_HOST']='localhost';
ob_start(); include __DIR__.'/vestra/admin.php'; echo ob_get_clean();
PHP);
$html = (string)shell_exec('cd '.escapeshellarg($sb).' && php render.php 2>&1');

echo "== 0. sayfa gercekten cizildi ==\n";
$t('panel cizildi (giris formu degil)', strlen($html) > 5000 && !str_contains($html, 'name="pass"'));
$t('Messages sekmesi basildi', str_contains($html, 'Read conversation'));
$t('PHP uyarisi/fatal yok', stripos($html,'Warning:')===false && stripos($html,'Fatal error')===false
   && stripos($html,'Undefined')===false && stripos($html,'Deprecated:')===false);

echo "\n== 1. konusma kartlari: YENI konusma once (last_at, dosya sirasi degil) ==\n";
$pNewCard = strpos($html, 'NEWTHREAD Buyer'); $pOldCard = strpos($html, 'OLDTHREAD Buyer');
$t('iki kart da basildi', $pNewCard !== false && $pOldCard !== false);
$t('yeni konusmanin karti eskisinden ONCE', $pNewCard !== false && $pOldCard !== false && $pNewCard < $pOldCard);

echo "\n== 2. kartin ICI: en yeni mesaj ustte, eskiler asagi ==\n";
/* Yalniz yeni konusmanin kartina bak: sayfanin tamamina bakan bir iddia baska
   kartin metnini olcebilirdi (bu depoda iddia satiri degil navigasyonu olcmustu). */
$cardStart = (int)$pNewCard;
$cardEnd   = (int)$pOldCard;
$card = substr($html, $cardStart, max(0, $cardEnd - $cardStart));
$pN = strpos($card, 'MSG-NEWEST'); $pM = strpos($card, 'MSG-MIDDLE'); $pO = strpos($card, 'MSG-OLDEST');
$t('uc mesaj da kartta', $pN !== false && $pM !== false && $pO !== false);
$t('EN YENI, ortadakinden once', $pN !== false && $pM !== false && $pN < $pM);
$t('ortadaki, EN ESKIden once', $pM !== false && $pO !== false && $pM < $pO);

echo "\n== 3. cevap kutusu ustte, en yeni mesajin hemen ustunde ==\n";
$pReply = strpos($card, 'name="_action" value="admin_reply"');
$t('cevap formu kartta', $pReply !== false);
$t('cevap formu EN YENI mesajdan ONCE', $pReply !== false && $pN !== false && $pReply < $pN);
$t('cevap formu dogru konusmaya bagli', str_contains($card, 'name="thread_id" value="th-new"'));
$t('cevap formu kartta TEK', substr_count($card, 'value="admin_reply"') === 1);
$t('ozet "newest first" diyor', str_contains($card, 'newest first'));

echo "\n== 4. silme dugmeleri hala dogru mesaja bagli (anahtar icerikten) ==\n";
require_once $root.'/vestra/inc/messages.php';
$keys = array_map('vestra_msg_key', $threads[1]['messages']);
foreach (['MSG-NEWEST'=>2, 'MSG-MIDDLE'=>1, 'MSG-OLDEST'=>0] as $txt => $i) {
    /* Anahtar, METNIN HEMEN ONCESINDEKI silme formunda olmali. */
    $pTxt = strpos($card, $txt);
    $pKey = strpos($card, 'value="'.$keys[$i].'"');
    $nextTxtBefore = $pKey === false ? false : strpos(substr($card, $pKey, max(0, (int)$pTxt - $pKey)), 'MSG-');
    $t("$txt silme anahtari kendi satirinda", $pKey !== false && $pTxt !== false && $pKey < $pTxt && $nextTxtBefore === false);
}
$t('kartta 3 silme formu', substr_count($card, 'value="msg_del"') === 3);

echo "\n== 5. KAYIT degismedi (gosterim ters, dosya kronolojik) ==\n";
$t('messages.json cizimden sonra bayt bayt ayni', md5_file($msgFile) === $before);
$back = json_decode((string)file_get_contents($msgFile), true);
$t('kayitta sira hala eskiden yeniye',
   array_column($back[1]['messages'] ?? [], 'text') === ['MSG-OLDEST','MSG-MIDDLE','MSG-NEWEST']);

echo "\n== 6. TERS YON: musteri sohbet paneli BILEREK degismedi ==\n";
/* Orada yazma kutusu altta, sayfa en yeni mesaja kayiyor (sohbet kalibi). */
$panelSrc = (string)file_get_contents($root.'/vestra/inc/messages.php');
$body = '';
if (preg_match('/function vestra_msg_panel_html\(.*?\n}\n/s', $panelSrc, $mm)) $body = $mm[0];
$t('panel govdesi bulundu', $body !== '');
$t('panel mesajlari kayit sirasinda geziyor', str_contains($body, "foreach (\$thread['messages'] as \$i => \$m)"));
$t('panelde array_reverse YOK', !str_contains($body, 'array_reverse'));
$t('panel en yeni mesaja kaydiriyor', str_contains($body, 'mt.scrollTop=mt.scrollHeight'));

exec('rm -rf '.escapeshellarg($sb));
echo "\n".($bad ? "KIRMIZI: $bad\n" : "YESIL\n");
echo "admin_msg_order_test: $ok iddia gecti, $bad DUSTU\n";
exit($bad ? 1 : 0);
