<?php
/* Konusmayi baska saticiya tasirken YAZARI da devretmek (admin_mode=thread_seller,
 * move_threads'te 'yazar:devret').
 *
 * NEDEN VAR: operator, 29 Eyl 2026 — Odzież Premium ↔ TYREX konusmasi (94 mesaj,
 * 27'si TYREX adina): "Bu saticinin ismini her yerden sil" + "bu konusmayi
 * Agaya Paris yap, tyrex yerine". Tasima araci eski satici konusmada yazdiysa
 * BILEREK duruyordu (baskasinin sozunu yeni firmaya mal etmemek icin). Bu
 * operatorun verdigi karar: ad her yerden gitsin. Secim ACIK bir bayrak, varsayilan
 * degismedi.
 *
 * NEDEN KUM HAVUZUNDA: is akisinin GERCEK PHP'si cikarilip sahte bir mesaj
 * deposuyla kosturuluyor. Olculen sey diske inen kayit: yeni id, yeni satici,
 * yazar alanlari, okuma imleci, engellenen deneme kaydi — ve kontrol grubunun
 * (ayni saticinin BASKA alicisiyla konusmasi) AYNEN durdugu. */

$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? "  ok   " : "  HATA ").$n."\n"; };

$repo = dirname(__DIR__);
$root = $repo.'/vestra';
$wf = (string)file_get_contents($repo.'/.github/workflows/seller-products.yml');
$step = '';
if (preg_match("/- name: Konuşmanın satıcısını taşı.*?(?=\n      - name: )/s", $wf, $m)) $step = $m[0];
$t('thread_seller adimi bulundu', $step !== '');
$php = '';
if ($step !== '' && preg_match("/<<'PHPEOF'\n(.*?)\n\s*PHPEOF\n/s", $step, $pm)) {
  $lines = explode("\n", $pm[1]); $ind = null;
  foreach ($lines as $ln) { if (trim($ln) === '') continue; $w = strlen($ln) - strlen(ltrim($ln, ' ')); $ind = $ind === null ? $w : min($ind, $w); }
  $php = implode("\n", array_map(fn($ln) => substr($ln, (int)$ind), $lines));
}
$t('betik cikarildi', str_starts_with(ltrim($php), '<?php'));

$sb = sys_get_temp_dir().'/vestra_tsr_'.bin2hex(random_bytes(4));
$ph = $sb.'/public_html';
@mkdir($ph.'/data', 0777, true);
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/inc', FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
foreach ($rii as $f) {
  $dst = $ph.'/inc/'.substr($f->getPathname(), strlen($root.'/inc/'));
  if ($f->isDir()) @mkdir($dst, 0777, true); else { @mkdir(dirname($dst), 0777, true); copy($f->getPathname(), $dst); }
}
file_put_contents($sb.'/run.php', $php);

$B = 'buyer0000000001'; $B2 = 'buyer0000000002';
$T = 'tyrex0000000001'; $G = 'garage000000001';
$tid = fn(string $b, string $s, string $l) => substr(md5($b.'|'.$s.'|'.$l), 0, 16);
$ACC = [
  ['id'=>$B,  'type'=>'buyer',  'company'=>'Odz Test Buyer', 'email'=>'b1@example.test'],
  ['id'=>$B2, 'type'=>'buyer',  'company'=>'Other Buyer',    'email'=>'b2@example.test'],
  ['id'=>$T,  'type'=>'seller', 'company'=>'TYREX INTERNATIONAL BV.', 'email'=>'t@example.test'],
  ['id'=>$G,  'type'=>'seller', 'company'=>'GARAGE LE PARIS', 'email'=>'g@example.test'],
];
$TH1 = [
  'id'=>$tid($B, $T, 'rl-x'), 'buyer_uid'=>$B, 'seller_uid'=>$T, 'listing_id'=>'rl-x', 'last_at'=>'2026-09-29T12:34:00+00:00',
  'read'=>[$T=>3, $B=>4], 'ping'=>[$T=>'2026-09-29T11:00:00+00:00'],
  'messages'=>[
    ['from'=>$B, 'text'=>'Do you send me?', 'at'=>'2026-09-29T10:35:00+00:00'],
    ['from'=>$T, 'text'=>'Hello, the money has arrived.', 'at'=>'2026-09-29T11:11:00+00:00'],
    ['from'=>$B, 'text'=>'Ok no problem', 'at'=>'2026-09-29T11:20:00+00:00'],
    ['from'=>'system', 'text'=>'', 'meta'=>['kind'=>'order'], 'by'=>$T, 'at'=>'2026-09-29T11:30:00+00:00'],
    ['from'=>$T, 'text'=>'Regards, Tyrex team', 'at'=>'2026-09-29T12:34:00+00:00'],
  ],
];
/* KONTROL GRUBU: ayni satici, ayni ilan, BASKA alici — hic dokunulmamali. */
$TH2 = [
  'id'=>$tid($B2, $T, 'rl-x'), 'buyer_uid'=>$B2, 'seller_uid'=>$T, 'listing_id'=>'rl-x', 'last_at'=>'2026-09-20T10:00:00+00:00',
  'messages'=>[['from'=>$T, 'text'=>'hi', 'at'=>'2026-09-20T10:00:00+00:00']],
];
$BLK = [
  ['at'=>'2026-09-28T09:00:00+00:00', 'from'=>$T, 'buyer_uid'=>$B,  'seller_uid'=>$T, 'listing_id'=>'rl-x', 'flag'=>'phone', 'text'=>'x'],
  ['at'=>'2026-09-28T09:05:00+00:00', 'from'=>$T, 'buyer_uid'=>$B2, 'seller_uid'=>$T, 'listing_id'=>'rl-x', 'flag'=>'phone', 'text'=>'y'],
];
$seed = function () use ($ph, $ACC, $TH1, $TH2, $BLK, $T) {
  file_put_contents($ph.'/data/accounts.json', json_encode($ACC));
  file_put_contents($ph.'/data/messages.json', json_encode([$TH1, $TH2]));
  file_put_contents($ph.'/data/blocked_messages.json', json_encode($BLK));
  file_put_contents($ph.'/data/listings.json', json_encode([['id'=>'rl-x', 'name'=>'Polo', 'seller_uid'=>$T, 'status'=>'approved']]));
  foreach (glob($ph.'/data/*.bak-*') ?: [] as $f) @unlink($f);
};
$raw = fn(string $f): string => (string)file_get_contents($ph.'/data/'.$f);
$run = function (string $to, string $sel, bool $go) use ($sb, $ph): string {
  return (string)shell_exec('cd '.escapeshellarg($ph).' && env HOME='.escapeshellarg($sb)
    .' MV_TO='.escapeshellarg($to).' MV_TH='.escapeshellarg($sel).' MV_GO='.($go ? 'true' : 'false')
    .' php '.escapeshellarg($sb.'/run.php').' 2>&1; echo "RC=$?"');
};
$threads = fn(): array => json_decode($raw('messages.json'), true) ?: [];
$find = function (string $id) use ($threads): ?array { foreach ($threads() as $x) if (($x['id'] ?? '') === $id) return $x; return null; };

echo "== 1. yazar devri OLMADAN: eski davranis korunuyor (durur) ==\n";
$seed(); $m0 = $raw('messages.json'); $b0 = $raw('blocked_messages.json');
$o = $run('garage', $TH1['id'], true);
$t('cikis 1 ve DURDU', str_contains($o, 'RC=1') && str_contains($o, 'DURDU: eski satici bu konusmada 3 mesaj YAZMIS'));
$t('ipucu: yazar:devret', str_contains($o, "'yazar:devret'"));
$t('messages.json DEGISMEDI', $raw('messages.json') === $m0);
$t('blocked_messages.json DEGISMEDI', $raw('blocked_messages.json') === $b0);

echo "\n== 2. yazar devri KURU KOSU ==\n";
$o = $run('garage', $TH1['id'].',yazar:devret', false);
$t('cikis 0, KURU KOSU', str_contains($o, 'RC=0') && str_contains($o, 'KURU KOSU'));
$t('yazar devri satiri', str_contains($o, 'YAZAR DEVRI (operator karari): 3 mesajin yazari TYREX INTERNATIONAL BV. => GARAGE LE PARIS'));
$t('ad METINDE sayildi (1 mesaj, degismeyecegi yazili)', str_contains($o, "('tyrex') mesaj METNINDE: 1 mesaj") && str_contains($o, 'METIN DEGISMEZ'));
$t('engellenen deneme kaydi sayildi (yalniz bu konusma: 1)', str_contains($o, 'engellenen deneme kaydi (bu konusma): 1'));
$t('ilanin saticisi notu basildi', str_contains($o, 'NOT: ilan rl-x hala TYREX INTERNATIONAL BV. saticisinda'));
$t('kuru kosu: messages.json DEGISMEDI', $raw('messages.json') === $m0);

echo "\n== 3. yazar devri UYGULA ==\n";
$o = $run('garage', $TH1['id'].',yazar:devret', true);
$newId = $tid($B, $G, 'rl-x');
$n = $find($newId);
$t('cikis 0 ve geri okundu', str_contains($o, 'RC=0') && str_contains($o, 'KAYDEDILDI — 1/1'));
$t('eski id artik YOK', $find($TH1['id']) === null);
$t('yeni id = md5(alici|YENI satici|ilan)', $n !== null);
$t('konusmanin saticisi GARAGE', ($n['seller_uid'] ?? '') === $G);
$fromT = 0; $fromG = 0; $fromB = 0; $byG = 0;
foreach ((array)($n['messages'] ?? []) as $mm) {
  $f = (string)($mm['from'] ?? '');
  if ($f === $T) $fromT++; if ($f === $G) $fromG++; if ($f === $B) $fromB++;
  if (($mm['by'] ?? '') === $G) $byG++;
}
$t('eski saticinin yazarligi 0 (from)', $fromT === 0);
$t('2 mesaj artik GARAGE adina', $fromG === 2);
$t('sistem kartinin aktoru de GARAGE (by)', $byG === 1);
$t('ALICININ mesajlari ALICIDA kaldi', $fromB === 2);
$t('mesaj sayisi ayni (5)', count((array)($n['messages'] ?? [])) === 5);
$texts = array_map(fn($x) => (string)($x['text'] ?? ''), (array)($n['messages'] ?? []));
$t('metin DEGISMEDI (imza oldugu gibi)', in_array('Regards, Tyrex team', $texts, true));
$t('eski saticinin okuma imleci silindi', !isset($n['read'][$T]) && !isset($n['ping'][$T]));
$t('alicinin okuma imleci korundu', ($n['read'][$B] ?? null) === 4);
$t('geri okuma satiri: kalan 0', str_contains($o, 'eski saticinin yazarligi kalan mesaj: 0'));
$t('messages.json yedegi alindi', (glob($ph.'/data/messages.json.bak-*') ?: []) !== []);

echo "\n== 4. TERS YON: kontrol grubu AYNEN duruyor ==\n";
$c = $find($TH2['id']);
$t('baska alicinin konusmasi hala TYREX', ($c['seller_uid'] ?? '') === $T && (($c['messages'][0]['from'] ?? '') === $T));
$bl = json_decode($raw('blocked_messages.json'), true) ?: [];
$t('bu konusmanin engellenen kaydi GARAGE', ($bl[0]['seller_uid'] ?? '') === $G && ($bl[0]['from'] ?? '') === $G);
$t('baska alicinin engellenen kaydi hala TYREX', ($bl[1]['seller_uid'] ?? '') === $T && ($bl[1]['from'] ?? '') === $T);
$t('blocked yedegi alindi', (glob($ph.'/data/blocked_messages.json.bak-*') ?: []) !== []);
$t('ilanin saticisi DEGISMEDI', str_contains($raw('listings.json'), $T));

echo "\n== 5. ikinci kez: zaten tasindi ==\n";
$o = $run('garage', $newId.',yazar:devret', true);
$t('zaten bu saticida, cikis 0', str_contains($o, 'RC=0') && str_contains($o, 'zaten bu saticida'));

echo "\n== 6. hedef belirsizse durur ==\n";
$seed(); $m0 = $raw('messages.json');
$o = $run('e', $TH1['id'].',yazar:devret', true);
$t('birden fazla hesap: cikis 1, yazilmadi', str_contains($o, 'RC=1') && $raw('messages.json') === $m0);

echo "\n== 7. msg_del 'redact': metinden TEK ifade cikar ==\n";
/* Konusmada adin METINDE gectigi bir mesaj vardi ("Agaya Paris, one of TYREX
   International's subsidiary companies"). Mesajin tamami silinmiyor, yalnizca
   o ifade. */
$st2 = '';
if (preg_match("/- name: Mesaj \/ engellenen kayıt sil.*?(?=\n      - name: )/s", $wf, $m)) $st2 = $m[0];
$php2 = '';
if ($st2 !== '' && preg_match("/<<'PHPEOF'\n(.*?)\n\s*PHPEOF\n/s", $st2, $pm)) {
  $lines = explode("\n", $pm[1]); $ind = null;
  foreach ($lines as $ln) { if (trim($ln) === '') continue; $w = strlen($ln) - strlen(ltrim($ln, ' ')); $ind = $ind === null ? $w : min($ind, $w); }
  $php2 = implode("\n", array_map(fn($ln) => substr($ln, (int)$ind), $lines));
}
$t('msg_del betigi cikarildi', str_starts_with(ltrim($php2), '<?php'));
$t('payload adima geciyor (envs)', str_contains($st2, 'MD_PAY: ${{ github.event.inputs.payload }}') && str_contains($st2, 'envs: MD_SEL,MD_GO,MD_PAY'));
file_put_contents($sb.'/md.php', $php2);
$md = function (string $sel, string $pay, bool $go) use ($sb, $ph): string {
  return (string)shell_exec('cd '.escapeshellarg($ph).' && env HOME='.escapeshellarg($sb)
    .' MD_SEL='.escapeshellarg($sel).' MD_PAY='.escapeshellarg($pay).' MD_GO='.($go ? 'true' : 'false')
    .' php '.escapeshellarg($sb.'/md.php').' 2>&1; echo "RC=$?"');
};
$seed(); $m0 = $raw('messages.json');
$sel = 'redact:'.$TH1['id'].':2026-09-29T12:34';
$o = $md($sel, ', Tyrex team=>', false);
$t('kuru kosu: uzunluklar basildi, dosya DEGISMEDI', str_contains($o, 'metin 19 -> 7 karakter') && str_contains($o, '(kuru kosu)') && $raw('messages.json') === $m0);
$t('metnin KENDISI kutuge basilmadi', !str_contains($o, 'Regards'));
$o = $md($sel, ', Tyrex team=>', true);
$x = $find($TH1['id']);
$t('uygulandi ve geri okundu', str_contains($o, 'DUZELTILDI') && (($x['messages'][4]['text'] ?? '') === 'Regards'));
$t('mesaj SILINMEDI (sayi ayni)', count((array)($x['messages'] ?? [])) === 5);
$t('diger mesajlar AYNEN', ($x['messages'][1]['text'] ?? '') === 'Hello, the money has arrived.');
$t('yazar ve tarih korundu', ($x['messages'][4]['from'] ?? '') === $T && ($x['messages'][4]['at'] ?? '') === '2026-09-29T12:34:00+00:00');
$t('yedek alindi', (glob($ph.'/data/messages.json.bak-redact-*') ?: []) !== []);
$m1 = $raw('messages.json');
$o = $md($sel, 'Tyrex=>X', true);
$t('ifade artik yok: DURDU, yazilmadi', str_contains($o, 'ifade mesajda 0 kez') && $raw('messages.json') === $m1);
$o = $md('redact:'.$TH1['id'].':2026-09-29', 'o=>0', true);
$t('secici birden fazla mesaja uyuyor: DURDU, yazilmadi', str_contains($o, 'TAM 1 gerekli') && $raw('messages.json') === $m1);
$o = $md('redact:'.$TH1['id'].':2026-09-29T11:11', 'e=>E', true);
$t('ifade mesajda BIRDEN FAZLA kez: DURDU, yazilmadi', str_contains($o, 'ifade mesajda 4 kez') && $raw('messages.json') === $m1);
$o = $md('redact:'.$TH2['id'].':2026-09-20', 'b64:'.base64_encode("h=>h'"), false);
$t("b64: payload cozuluyor (kesme isareti tasiyan ifade)", str_contains($o, 'metin 2 -> 3 karakter') && $raw('messages.json') === $m1);
$o = $md($sel, 'duz metin', true);
$t('=> yoksa DURDU', str_contains($o, "'ESKI=>YENI' bicimi degil") && $raw('messages.json') === $m1);
$o = $md('redact:'.$TH2['id'].':2026-09-20', 'h=>H', true);
$t('ayni harf bir kez: kontrol konusmasi degisti (sadece hedef)', str_contains($o, 'DUZELTILDI') && ($find($TH2['id'])['messages'][0]['text'] ?? '') === 'Hi');

exec('rm -rf '.escapeshellarg($sb));
echo "\nthread_seller_reassign_test: {$ok} iddia gecti".($bad ? ", {$bad} HATA" : '')."\n";
exit($bad ? 1 : 0);
