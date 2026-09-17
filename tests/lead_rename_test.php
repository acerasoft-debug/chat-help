<?php
/* Lead'in firma adini duzeltme (Admin ▸ Prospects, adin uzerine tiklamak).
 *
 * NEDEN VAR: tarayici sayfanin kendini ne diye adlandirdigini aliyor ve bazi
 * sayfalar kendine "Home" diyor. factoryoutlet.gr kayitta "Αρχική" (Yunanca
 * "Anasayfa") duruyor; kampanya mektubu "Hello <firma>," diye acildigi icin o
 * kayit bu depoda DORT kez elle atlandi. Iki cikis yolu vardi ve ikisi de
 * yanlisti: oyle gondermek, ya da silip yeniden eklemek -- silmek
 * last_contacted_at / last_newcollection_at damgalarini dusurur ve ILK mektup
 * ikinci kez gider.
 *
 * NEDEN KUM HAVUZUNDA GERCEKTEN KOSTURULUYOR: kaynak taramasi bu isi olcemez.
 * Bu oturumda iki kez var olmayan bir fonksiyon adi yazildi ve IKISINI DE
 * `php -l` gecirdi. Tek dogru olcum, YAZILAN KAYDI okumak.
 *
 * VESTRA_DATA_DIR `defined()` korumali, yani bu test gercek vestra/data'ya
 * DOKUNMUYOR. Korumasizken bir test bir kez uretim dosyasina yazmisti. */

$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? "  ok   " : "  FAIL ").$n."\n"; };

$root = dirname(__DIR__).'/vestra';
$sand = sys_get_temp_dir().'/vestra_lr_'.bin2hex(random_bytes(4));
mkdir($sand.'/data', 0777, true);

$SEED = [
  ['id'=>'L1','company'=>'Αρχική','email'=>'info@factoryoutlet.gr','country'=>'Greece',
   'status'=>'contacted','last_contacted_at'=>'2026-09-08T16:53:00Z','last_newcollection_at'=>''],
  ['id'=>'L2','company'=>'Lulli','email'=>'contact@lulli.fr','country'=>'France',
   'status'=>'contacted','last_contacted_at'=>'2026-09-01T10:00:00Z','last_newcollection_at'=>'2026-09-17T17:43:00Z'],
  /* Ayni ada sahip ikinci bir kayit: id ile TAM eslesmenin gerektigini gosterir. */
  ['id'=>'L3','company'=>'Αρχική','email'=>'shop@another.gr','country'=>'Greece',
   'status'=>'new','last_contacted_at'=>'','last_newcollection_at'=>''],
];
$seed = function () use ($sand, $SEED) { file_put_contents($sand.'/data/leads.json', json_encode($SEED)); };
$load = fn(): array => json_decode((string)file_get_contents($sand.'/data/leads.json'), true) ?: [];
$byId = function (string $id) use ($load): array {
  foreach ($load() as $l) if (($l['id'] ?? '') === $id) return $l;
  return [];
};

/* admin.php'yi AYRI BIR SURECTE POST ile kosturuyoruz: handler header()+exit
 * yapiyor, yani ayni surecte cagirmak testin kendisini bitirirdi. */
$runner = $sand.'/_post.php';
file_put_contents($runner, <<<'PHP'
<?php
define('VESTRA_DATA_DIR', getenv('LR_DIR'));
define('VESTRA_ACCOUNTS', getenv('LR_DIR').'/accounts.json');
$_SESSION = [];
session_start();
$_SESSION['vadmin'] = true; $_SESSION['vadmin_csrf'] = 'tok';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/admin';
$_POST = json_decode(getenv('LR_POST'), true) ?: [];
$_POST['_csrf'] = 'tok';
ob_start(); include getenv('LR_ADMIN'); ob_end_clean();
PHP);

$post = function (array $fields) use ($runner, $sand, $root): string {
  /* CLI SAPI header() cagrisini yutar, o yuzden SONUC her zaman KAYITTAN
     okunuyor -- ki dogru olcum de o: panelin ne dedigi degil, diske ne indigi. */
  $env = ['LR_DIR' => $sand.'/data', 'LR_ADMIN' => $root.'/admin.php',
          'LR_POST' => json_encode($fields + ['_action' => 'rename_lead'])];
  $pfx = ''; foreach ($env as $k => $v) $pfx .= $k.'='.escapeshellarg($v).' ';
  return (string)shell_exec($pfx.'php '.escapeshellarg($runner).' 2>&1');
};

echo "== 1. ad duzeltiliyor ==\n";
$seed();
$out = $post(['lid' => 'L1', 'company' => 'Factory Outlet']);
$l1 = $byId('L1');
$t('yeni ad kayda indi', ($l1['company'] ?? '') === 'Factory Outlet');
$t('PHP uyarisi yok', stripos($out, 'warning') === false && stripos($out, 'fatal') === false);

echo "\n== 2. DAMGALAR korunuyor (bu yazmanin var olma sebebi) ==\n";
/* Silip yeniden eklemenin yapamadigi sey tam olarak bu: damga dusunce
   `send-outreach` o firmaya ILK mektubu ikinci kez gonderirdi. */
$t('last_contacted_at korundu', ($l1['last_contacted_at'] ?? '') === '2026-09-08T16:53:00Z');
$t('last_newcollection_at korundu', ($l1['last_newcollection_at'] ?? '') === '');
$t('durum korundu', ($l1['status'] ?? '') === 'contacted');
$t('e-posta korundu', ($l1['email'] ?? '') === 'info@factoryoutlet.gr');
$t('ulke korundu', ($l1['country'] ?? '') === 'Greece');

echo "\n== 3. TERS YON: baska hicbir kayda dokunulmuyor ==\n";
/* Tek yon yazilsaydi, butun listeyi ayni ada ceviren bir hata da yesil kalirdi. */
$l2 = $byId('L2'); $l3 = $byId('L3');
$t('L2 adi degismedi', ($l2['company'] ?? '') === 'Lulli');
$t('L2 ikinci mektup damgasi degismedi', ($l2['last_newcollection_at'] ?? '') === '2026-09-17T17:43:00Z');
$t('AYNI ADI tasiyan L3 degismedi (eslesme id ile, ad ile degil)', ($l3['company'] ?? '') === 'Αρχική');
$t('kayit sayisi ayni', count($load()) === 3);

echo "\n== 4. BOS ad reddediliyor ==\n";
/* Bos bir ad, yanlis bir addan kotu: mektup "Hello" yazip nokta koyar. */
$seed();
$post(['lid' => 'L1', 'company' => '   ']);
$t('bos ad yazilmadi', ($byId('L1')['company'] ?? '') === 'Αρχική');

echo "\n== 5. bilinmeyen id hicbir sey yazmiyor ==\n";
$seed();
$post(['lid' => 'NOPE', 'company' => 'Hayalet']);
$t('hicbir kayit degismedi', json_encode($load()) === json_encode($SEED));

echo "\n== 6. cok uzun ad kirpiliyor ==\n";
$seed();
$post(['lid' => 'L1', 'company' => str_repeat('A', 400)]);
$t('120 karaktere kirpildi', mb_strlen((string)($byId('L1')['company'] ?? '')) === 120);

echo "\n== 7. panel kablolamasi ==\n";
$src = (string)file_get_contents($root.'/admin.php');
$t('CSRF alani tasiyan paylasilan formda company kutusu var',
   str_contains($src, 'name="company" id="lrf_company"'));
$t('ad tiklanabilir (leadRename cagriliyor)', str_contains($src, 'onclick="leadRename('));
$t('JS rename_lead eylemini yaziyor', preg_match('/leadRename[\s\S]{0,400}?rename_lead/', $src) === 1);
$t('sonuc mesajinin bir metni var', str_contains($src, "'lead_renamed'=>"));
/* Damgalara dokunmadigi KAYNAKTA da sabitlenmeli: bir gun birisi "durumu da
   guncelleyelim" derse bu iddia once kirmizi doner. */
$body = '';
if (preg_match("/if\(\\\$act==='rename_lead'\)\{.*?\n  \}/s", $src, $m)) $body = $m[0];
$t('handler govdesi bulundu', $body !== '');
$t('handler last_contacted_at YAZMIYOR', $body !== '' && !str_contains($body, 'last_contacted_at'));
$t('handler status YAZMIYOR', $body !== '' && !str_contains($body, "'status'"));

echo "\n== 8. is akisi yolu (panel disindan) ==\n";
/* Admin girisi olmayan bir operator icin tek yol bu; ve musterinin ADRESI
   girdiye yazilmamali (kosu basligi kalici ve herkese acik). */
$wf = (string)file_get_contents(dirname(__DIR__).'/.github/workflows/seller-products.yml');
$step = '';
if (preg_match("/- name: Lead'in firma adını düzelt.*?(?=\n      - name: )/s", $wf, $m)) $step = $m[0];
$t('lead_rename adimi var', $step !== '');
$t('kuru kosu varsayilan (move_apply ile uygulaniyor)', $step !== '' && str_contains($step, "=== 'true'"));
$t('TAM 1 eslesme sarti', $step !== '' && str_contains($step, 'count($hits) !== 1'));
$t('adres MASKELI basiliyor', $step !== '' && str_contains($step, '$mask('));
$t('eslesme alt dize DEGIL (id/alan adi tam esitlik)',
   $step !== '' && str_contains($step, '$id === $sel') && str_contains($step, '$dom === $sel'));
$t('yazma GERI OKUNUYOR', $step !== '' && str_contains($step, 'geri okuma'));
$t('damgalarin korundugu da dogrulaniyor', $step !== '' && str_contains($step, '$okS1') && str_contains($step, '$okS2'));

echo "\n".($bad ? "KIRMIZI: {$bad}\n" : '')."lead_rename_test: {$ok} iddia gecti".($bad ? ", {$bad} DUSTU" : '')."\n";
exit($bad ? 1 : 0);
