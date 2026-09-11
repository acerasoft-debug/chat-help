<?php
/* Mesaj silme (operatör, 11 Eyl 2026: "bu mesajlari sil ve silme özelligide koy mesajlara").
 *
 * Asıl iddia ŞU: silme DİZİN NUMARASINA göre çalışmamalı. Panel satırı çizerken
 * gördüğü sıra ile silme isteği sunucuya vardığındaki sıra aynı olmayabilir —
 * bu depoda aynı anda birden fazla oturum yazıyor. Numaraya göre silen bir kod
 * kayan bir dizinde YANLIŞ satırı siler ve silinen şey bir moderasyon kaydı
 * olduğu için kimse fark etmez. Aşağıda bu, araya yeni kayıt sokularak ölçülüyor.
 */
$root = dirname(__DIR__);
/* Kum havuzu: depo YOLU artik sabit (VESTRA_MESSAGES / VESTRA_BLOCKED_MESSAGES),
   o yuzden test gercek `vestra/data`'ya dokunmuyor. Eskiden dokunuyordu ve o
   dosyalar var oldugu icin bu test HIC KOSMADI -- kosmayan bir test, hic
   dusemeyen bir iddianin dosya halidir. */
$data = sys_get_temp_dir().'/vestra_msgdel_'.getmypid();
@mkdir($data, 0777, true);
define('VESTRA_MESSAGES',         $data.'/messages.json');
define('VESTRA_BLOCKED_MESSAGES', $data.'/blocked_messages.json');
require_once $root.'/vestra/inc/messages.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};
$wr = function (string $f, array $d) { file_put_contents($f, json_encode($d)); };
$rd = fn(string $f) => json_decode((string)@file_get_contents($f), true) ?: [];
$bf = $data.'/blocked_messages.json';
$mf = $data.'/messages.json';

echo "\n== 1. Engellenen deneme kaydı silme ==\n";
$wr($bf, [
  ['at'=>'2026-09-10T10:07:00+00:00','from'=>'u1','buyer_uid'=>'u1','seller_uid'=>'u2','listing_id'=>'l1','flag'=>'phone','text'=>'amazon link'],
  ['at'=>'2026-09-10T10:09:00+00:00','from'=>'u1','buyer_uid'=>'u1','seller_uid'=>'u2','listing_id'=>'l1','flag'=>'phone','text'=>'own brand'],
  ['at'=>'2026-09-01T03:41:00+00:00','from'=>'u3','buyer_uid'=>'u3','seller_uid'=>'u4','listing_id'=>'','flag'=>'email','text'=>'catalog please'],
]);
$log = vestra_msg_blocked_log();
$k2  = vestra_msg_blocked_key($log[1]);
$r = vestra_msg_blocked_delete($k2);
$t('silme basarili',                     !empty($r['ok']));
$t('yedek dosyasi yazildi',              !empty($r['backup']) && is_file($r['backup']));
$after = vestra_msg_blocked_log();
$t('kayit sayisi 3 -> 2',                count($after) === 2);
$t('DOGRU kayit gitti',                  !in_array('own brand', array_column($after,'text'), true));
$t('komsulari duruyor',                  in_array('amazon link', array_column($after,'text'), true)
                                      && in_array('catalog please', array_column($after,'text'), true));
$t('yedek silinen kaydi tasiyor',        str_contains((string)@file_get_contents($r['backup']), 'own brand'));
$r2 = vestra_msg_blocked_delete($k2);
$t('ayni anahtar ikinci kez: not_found', empty($r2['ok']) && ($r2['error'] ?? '') === 'not_found');
$t('bos anahtar reddedildi',             empty(vestra_msg_blocked_delete('')['ok']));

echo "\n== 2. Anahtar İÇERİKTEN türüyor: kayan dizin yanlış satırı silmez ==\n";
$wr($bf, [
  ['at'=>'2026-09-10T10:07:00+00:00','from'=>'u1','buyer_uid'=>'u1','seller_uid'=>'u2','listing_id'=>'','flag'=>'phone','text'=>'SILINECEK'],
  ['at'=>'2026-09-10T10:09:00+00:00','from'=>'u1','buyer_uid'=>'u1','seller_uid'=>'u2','listing_id'=>'','flag'=>'phone','text'=>'KALACAK'],
]);
$key = vestra_msg_blocked_key(vestra_msg_blocked_log()[0]);   // panel bu anahtarla cizdi
/* ... ve tam o sirada BASKA bir oturum log'un BASINA yeni bir kayit yaziyor. */
$now = vestra_msg_blocked_log();
array_unshift($now, ['at'=>'2026-09-11T09:00:00+00:00','from'=>'u9','buyer_uid'=>'u9','seller_uid'=>'u2','listing_id'=>'','flag'=>'iban','text'=>'ARAYA GIREN']);
$wr($bf, $now);
$r = vestra_msg_blocked_delete($key);
$rest = array_column(vestra_msg_blocked_log(), 'text');
$t('silme yine basarili',                !empty($r['ok']));
$t('hedeflenen kayit gitti',             !in_array('SILINECEK', $rest, true));
$t('araya giren kayit DURUYOR',          in_array('ARAYA GIREN', $rest, true));
$t('digeri de duruyor',                  in_array('KALACAK', $rest, true));

echo "\n== 3. Konuşmadaki tek mesajı silme ==\n";
$wr($mf, [[
  'id'=>'t1','buyer_uid'=>'b1','seller_uid'=>'s1','listing_id'=>'l1',
  'created_at'=>'2026-09-10T09:00:00+00:00','last_at'=>'2026-09-10T11:00:00+00:00',
  'messages'=>[
    ['from'=>'b1','text'=>'birinci','at'=>'2026-09-10T09:00:00+00:00'],
    ['from'=>'s1','text'=>'ikinci','at'=>'2026-09-10T10:00:00+00:00'],
    ['from'=>'b1','text'=>'ucuncu','at'=>'2026-09-10T11:00:00+00:00'],
  ],
]]);
$th = vestra_msg_threads()[0];
$mk = vestra_msg_key($th['messages'][2]);          // SON mesaj
$r = vestra_msg_delete('t1', $mk);
$t('silme basarili',                     !empty($r['ok']));
$t('yedek yazildi',                      !empty($r['backup']) && is_file($r['backup']));
$th2 = vestra_msg_threads()[0];
$t('mesaj sayisi 3 -> 2',                count($th2['messages']) === 2);
$t('dogru mesaj gitti',                  !in_array('ucuncu', array_column($th2['messages'],'text'), true));
$t('digerleri duruyor',                  in_array('birinci', array_column($th2['messages'],'text'), true));
/* last_at guncellenmezse iplik, ARTIK OLMAYAN bir mesajin tarihiyle siralanir. */
$t("'last_at' geri cekildi (10:00)",     str_starts_with((string)$th2['last_at'], '2026-09-10T10:00'));
$t('konusma silinmedi',                  empty($r['thread_gone']));
$t('olmayan anahtar: not_found',         (vestra_msg_delete('t1','deadbeef1234')['error'] ?? '') === 'not_found');
$t('olmayan konusma: not_found',         (vestra_msg_delete('yok', $mk)['error'] ?? '') === 'not_found');

echo "\n== 4. Son mesaj silinince konuşma da kapanır ==\n";
$wr($mf, [
  ['id'=>'t1','buyer_uid'=>'b1','seller_uid'=>'s1','listing_id'=>'','created_at'=>'x','last_at'=>'x',
   'messages'=>[['from'=>'b1','text'=>'tek','at'=>'2026-09-10T09:00:00+00:00']]],
  ['id'=>'t2','buyer_uid'=>'b2','seller_uid'=>'s1','listing_id'=>'','created_at'=>'x','last_at'=>'x',
   'messages'=>[['from'=>'b2','text'=>'baska','at'=>'2026-09-10T09:00:00+00:00']]],
]);
$only = vestra_msg_threads()[0]['messages'][0];
$r = vestra_msg_delete('t1', vestra_msg_key($only));
$ids = array_column(vestra_msg_threads(), 'id');
$t('bosalan konusma kapandi',            !empty($r['thread_gone']) && !in_array('t1', $ids, true));
$t('DIGER konusma duruyor',              in_array('t2', $ids, true));

echo "\n== 5. Panel gerçekten bu fonksiyonları çağırıyor ==\n";
$ad = (string)file_get_contents($root.'/vestra/admin.php');
$t("'msg_blocked_del' kolu var",         str_contains($ad, "\$act==='msg_blocked_del'"));
$t("'msg_del' kolu var",                 str_contains($ad, "\$act==='msg_del'"));
$t('engellenen silme cagriliyor',        str_contains($ad, 'vestra_msg_blocked_delete('));
$t('mesaj silme cagriliyor',             str_contains($ad, 'vestra_msg_delete('));
$t('anahtar panelde iceriktten turuyor', str_contains($ad, 'vestra_msg_blocked_key($bm)') && str_contains($ad, 'vestra_msg_key($m)'));
/* CSRF her admin POST'unda global olarak dogrulaniyor: alani unutan bir form
   "csrf_fail" ile doner, yani dugme gorunur ama HIC calismaz. */
$t('iki form da csrfField tasiyor',      substr_count($ad, 'value="msg_blocked_del"') === 1
                                      && substr_count($ad, 'value="msg_del"') === 1
                                      && str_contains($ad, ".csrfField()\n        .'<input type=\"hidden\" name=\"_action\" value=\"msg_blocked_del\"")); 
$t('uyari metni geri alinamazligi soyluyor', str_contains($ad, 'silmek onu geri almaz'));

@unlink($bf); @unlink($mf);
foreach (glob($data.'/message_backups/*.json') ?: [] as $g) @unlink($g);
@rmdir($data.'/message_backups');
echo "\n".($fail ? "BASARISIZ" : "GECTI").": {$ok} iddia gecti, {$fail} dustu\n";
exit($fail ? 1 : 0);
