<?php
/* Mesaj bildirimi ne zaman gider (operator karari, 6 Eyl 2026: "email gitsin
 * mesajdan sonra").
 *
 * Eski kural "thread'de okunmamis mesaji YOKSA" idi: musteri onceki notu acmamissa
 * tedarikcinin cevabi HIC e-posta uretmiyordu -- mesaj, musterinin acmak icin bir
 * sebebi olmayan bir panelde bekliyordu ve disaridan bakildiginda bu, gonderilemeyen
 * bir e-postadan ayirt edilemiyordu. Yeni kural: HER mesaj bir bildirim uretir;
 * yalnizca ayni alicinin ayni yazismasindaki onceki bildirimden bu yana patlama
 * penceresi dolmadiysa susulur.
 *
 * Tuzaklar burada tutuluyor:
 *   - ilk mesaj her zaman gider (damga yok),
 *   - pencere dolduysa gider, dolmadiysa gitmez -- sinir DAHIL,
 *   - okunmamislik ARTIK KOSUL DEGIL: okumayan alici da her mesajda bildirim alir,
 *   - gap=0 "her mesaj" demektir,
 *   - geriye giden saat bildirimi sonsuza kadar susturmaz.
 */
$src = file_get_contents(__DIR__.'/../vestra/inc/messages.php');
if (!preg_match('/^const VESTRA_MSG_PING_GAP = \d+;$/m', $src, $m)) { echo "HATA: VESTRA_MSG_PING_GAP bulunamadi\n"; exit(1); }
eval($m[0]);
foreach (['vestra_msg_should_ping','vestra_msg_unread'] as $fn) {
    if (!preg_match('/^function '.preg_quote($fn,'/').'\(.*?^}/ms', $src, $m)) { echo "HATA: $fn bulunamadi\n"; exit(1); }
    eval($m[0]);
}

$ok=0; $fail=0;
$t = function (string $n, bool $c) use (&$ok,&$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};
$NOW = 1_757_000_000;
$GAP = VESTRA_MSG_PING_GAP;

echo "== 1. Ilk mesaj ==\n";
$t('damga yoksa gider',            vestra_msg_should_ping(0, $NOW));
$t('negatif damga da damgasizdir', vestra_msg_should_ping(-5, $NOW));

echo "\n== 2. Patlama penceresi ==\n";
$t('hemen ardindan gitmez',        !vestra_msg_should_ping($NOW, $NOW));
$t('pencere icinde gitmez',        !vestra_msg_should_ping($NOW - ($GAP - 1), $NOW));
$t('pencere tam dolunca gider',    vestra_msg_should_ping($NOW - $GAP, $NOW));
$t('pencereden sonra gider',       vestra_msg_should_ping($NOW - ($GAP + 1), $NOW));
$t('saatler sonra gider',          vestra_msg_should_ping($NOW - 86400, $NOW));

echo "\n== 3. Okunmamislik artik kosul DEGIL ==\n";
/* Eski kuralin tam olarak dustugu yer: alici onceki mesaji okumamis.
   vestra_msg_unread hala EVET diyor; bildirim yine de gitmeli. */
$thread = ['id'=>'t1','buyer_uid'=>'b1','seller_uid'=>'s1','read'=>['b1'=>0],
           'messages'=>[['from'=>'s1','text'=>'ilk'],['from'=>'s1','text'=>'ikinci']]];
$t('alicinin okunmamis mesaji var', vestra_msg_unread($thread, 'b1'));
$t('buna ragmen bildirim gider',    vestra_msg_should_ping($NOW - 3600, $NOW));

echo "\n== 4. gap=0: her mesaj ==\n";
$t('ayni saniyede bile gider', vestra_msg_should_ping($NOW, $NOW, 0));

echo "\n== 5. Saat geriye gitmis ==\n";
/* Gelecekte kalmis bir damga, susturmayi saat duzelene kadar surdurmemeli. */
$t('gelecekteki damga susturmaz', vestra_msg_should_ping($NOW + 600, $NOW));

echo "\nTOPLAM: {$ok} gecti, {$fail} kaldi\n";
exit($fail === 0 ? 0 : 1);
