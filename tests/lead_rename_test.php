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
$t('eslesme alt dize DEGIL (id / e-posta alan adi / site alan adi tam esitlik)',
   $step !== '' && str_contains($step, '$id === $ref') && str_contains($step, '$dom === $ref')
   && str_contains($step, '$web === $ref'));
$t('yazma GERI OKUNUYOR', $step !== '' && str_contains($step, 'geri okuma'));
$t('damgalarin korundugu da dogrulaniyor', $step !== '' && str_contains($step, '$okS1') && str_contains($step, '$okS2'));

echo "\n== 9. is akisinin GERCEK PHP'si kum havuzunda (toplu kip + site alan adi) ==\n";
/* 27 Eyl 2026: ayakkabi partisinde 13 lead'in adi sayfa basligi olarak
   kaydedildi; bir kismi serbest posta saglayicili (gmail, libero), yani
   e-posta alan adiyla bulunamiyor. Toplu kip + site alan adi eslesmesi
   eklendi. Kaynak taramasi bunu olcemez: betik workflow'dan cikarilip
   sahte bir lead kaydiyla GERCEKTEN kosturuluyor. */
$php9 = '';
if ($step !== '' && preg_match("/<<'PHPEOF'\n(.*?)\n\s*PHPEOF\n/s", $step, $pm)) {
  $lines = explode("\n", $pm[1]); $ind = null;
  foreach ($lines as $ln) { if (trim($ln) === '') continue; $w = strlen($ln) - strlen(ltrim($ln, ' ')); $ind = $ind === null ? $w : min($ind, $w); }
  $php9 = implode("\n", array_map(fn($ln) => substr($ln, (int)$ind), $lines));
}
$t('betik cikarildi', $php9 !== '' && str_starts_with(ltrim($php9), '<?php'));
$sb = sys_get_temp_dir().'/vestra_lr9_'.bin2hex(random_bytes(4));
$ph = $sb.'/public_html';
@mkdir($ph.'/data', 0777, true);
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/inc', FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
foreach ($rii as $f) {
  $dst = $ph.'/inc/'.substr($f->getPathname(), strlen($root.'/inc/'));
  if ($f->isDir()) @mkdir($dst, 0777, true); else { @mkdir(dirname($dst), 0777, true); copy($f->getPathname(), $dst); }
}
file_put_contents($sb.'/run.php', $php9);
$S9 = [
  ['id'=>'LDA','company'=>'Modische & bequeme Schuhe in Lus','email'=>'schuhhaus.test@gmail.com','website'=>'https://schuhhaus-a.at',
   'country'=>'Austria','status'=>'new','last_contacted_at'=>'','last_newcollection_at'=>''],
  ['id'=>'LDB','company'=>'Schuhgesch?ft','email'=>'shop@peter-b.ch','website'=>'https://www.peter-b.ch',
   'country'=>'Switzerland','status'=>'contacted','last_contacted_at'=>'2026-09-20T10:00:00Z','last_newcollection_at'=>'2026-09-24T10:00:00Z'],
  ['id'=>'LDC','company'=>'Scarpe per uomo e per donna','email'=>'dimarco.test@libero.it','website'=>'https://dimarco-c.it',
   'country'=>'Italy','status'=>'new','last_contacted_at'=>'','last_newcollection_at'=>''],
  /* KONTROL GRUBU: hic dokunulmamali. */
  ['id'=>'LDD','company'=>'Kontroll Schuhe','email'=>'info@kontroll.de','website'=>'https://kontroll.de',
   'country'=>'Germany','status'=>'contacted','last_contacted_at'=>'2026-09-01T08:00:00Z','last_newcollection_at'=>''],
  /* Ayni firmanin iki kutusu: site alan adi IKI kayda uyar -> TAM 1 kurali durdurmali. */
  ['id'=>'LDE','company'=>'Twin','email'=>'info@twin.fr','website'=>'https://twin.fr','country'=>'France','status'=>'new','last_contacted_at'=>''],
  ['id'=>'LDF','company'=>'Twin','email'=>'shop@twin.fr','website'=>'https://twin.fr','country'=>'France','status'=>'new','last_contacted_at'=>''],
  /* Ikinci gmail lead'i: "gmail.com" ref'i yuzlerce kayda uyar gibi burada 2'ye uyar. */
  ['id'=>'LDG','company'=>'Someone','email'=>'someone.else@gmail.com','website'=>'','country'=>'Spain','status'=>'new','last_contacted_at'=>''],
  /* ALT DIZE TUZAGI: 'peter-b.ch' bunun icinde geciyor. Tam esitlik 1 kayit
     bulur; alt dize eslesmesi 2 bulur ve parti durur. */
  ['id'=>'LDI','company'=>'Neu Peter','email'=>'kontakt@neu-peter-b.ch','website'=>'https://neu-peter-b.ch',
   'country'=>'Switzerland','status'=>'new','last_contacted_at'=>''],
];
$seed9 = fn() => file_put_contents($ph.'/data/leads.json', json_encode($S9, JSON_UNESCAPED_UNICODE));
$load9 = fn(): array => json_decode((string)file_get_contents($ph.'/data/leads.json'), true) ?: [];
$raw9  = fn(): string => (string)file_get_contents($ph.'/data/leads.json');
$run9 = function (string $sel, string $name, bool $go) use ($sb, $ph): string {
  return (string)shell_exec('cd '.escapeshellarg($ph).' && env HOME='.escapeshellarg($sb)
    .' LR_SEL='.escapeshellarg($sel).' LR_NAME='.escapeshellarg($name).' LR_GO='.($go ? 'true' : 'false')
    .' php '.escapeshellarg($sb.'/run.php').' 2>&1; echo "RC=$?"');
};
$co = function (array $L, string $id): string { foreach ($L as $l) if (($l['id'] ?? '') === $id) return (string)($l['company'] ?? ''); return '?'; };
$BATCH = 'schuhhaus-a.at=Schuhhaus Günter|peter-b.ch=Schuhhaus Peterhans|https://www.dimarco-c.it/contatti=Di Marco Calzature';

$seed9(); $r0 = $raw9();
$o = $run9('batch', $BATCH, false);
$t('toplu kuru kosu: cikis 0 ve KURU KOSU yaziyor', str_contains($o, 'RC=0') && str_contains($o, 'KURU KOSU') && str_contains($o, 'degisecek: 3'));
$t('toplu kuru kosu: leads.json DEGISMEDI', $raw9() === $r0);
$t('link verilen ref alan adina indirgendi', str_contains($o, "-- dimarco-c.it\n"));
$t('adres MASKELI (tam adres cikti da yok)', str_contains($o, 's***@gmail.com') && !str_contains($o, 'schuhhaus.test@gmail.com'));

$o = $run9('batch', $BATCH, true);
$L = $load9();
$t('toplu uygula: cikis 0, 3 ad yazildi', str_contains($o, 'RC=0') && str_contains($o, 'YAZILDI (3 ad)'));
$t('serbest posta saglayicili lead SITE alan adiyla bulundu (gmail)', $co($L, 'LDA') === 'Schuhhaus Günter');
$t('www. ile kayitli site eslesti', $co($L, 'LDB') === 'Schuhhaus Peterhans');
$t('serbest posta saglayicili lead (libero) linkten bulundu', $co($L, 'LDC') === 'Di Marco Calzature');
$b9 = []; foreach ($L as $l) if (($l['id'] ?? '') === 'LDB') $b9 = $l;
$t('damgalar KORUNDU (ilk + ikinci mektup)', ($b9['last_contacted_at'] ?? '') === '2026-09-20T10:00:00Z' && ($b9['last_newcollection_at'] ?? '') === '2026-09-24T10:00:00Z');
$t('durum KORUNDU', ($b9['status'] ?? '') === 'contacted');
$untouched = true;
foreach ($S9 as $k => $l) { if (in_array($l['id'], ['LDA','LDB','LDC'], true)) continue; if (($L[$k] ?? null) != $l) $untouched = false; }
$t('IKI YON: hedef disi 5 lead AYNEN duruyor', $untouched && count($L) === count($S9));
$t('geri okuma satiri hedef disini da sayiyor', str_contains($o, 'hedef disi lead: 5 kayit, degisen: 0'));

$o = $run9('batch', $BATCH, true);
$t('ikinci kez: ZATEN BU AD, cikis 0', str_contains($o, 'RC=0') && str_contains($o, 'yapilacak bir sey yok'));

/* HEPSI YA DA HICBIRI */
$seed9(); $r0 = $raw9();
$o = $run9('batch', 'schuhhaus-a.at=Schuhhaus Günter|twin.fr=Twin Store', true);
$t('belirsiz ref (ayni sitede 2 kutu): cikis 1', str_contains($o, 'RC=1') && str_contains($o, 'twin.fr: TAM 1 eslesme gerekiyor, bulunan: 2'));
$t('belirsiz ref: GECERLI olan cift de YAZILMADI', $raw9() === $r0);
$t('adaylar maskeli listelendi', str_contains($o, 'i***@twin.fr') && !str_contains($o, 'info@twin.fr'));

$o = $run9('batch', 'peter-b.ch=Schuhhaus Peterhans|yok.example=Hayalet', true);
$t('olmayan ref: cikis 1, hicbir sey yazilmadi', str_contains($o, 'RC=1') && $raw9() === $r0);

$o = $run9('batch', 'gmail.com=Hepsi', true);
$t('serbest posta alan adi (gmail.com) ref olamaz: durdu', str_contains($o, 'RC=1') && str_contains($o, 'gmail.com: TAM 1 eslesme gerekiyor, bulunan: 2') && $raw9() === $r0);

$o = $run9('batch', 'peter-b.ch=A|LDB=B', true);
$t('iki ref ayni lead: durdu', str_contains($o, 'RC=1') && str_contains($o, "ayni lead'e ikinci ref") && $raw9() === $r0);

$o = $run9('batch', 'peter-b.ch Schuhhaus Peterhans', true);
$t("'=' olmayan parca: durdu", str_contains($o, 'RC=1') && $raw9() === $r0);

$o = $run9('batch', 'eter-b.ch=Kesik', true);
$t('alt dize ile eslesme YOK (eter-b.ch -> 0)', str_contains($o, 'RC=1') && str_contains($o, 'bulunan: 0') && $raw9() === $r0);

/* TEKIL KIP geriye uyumlu: id ile ve site alan adiyla. */
$o = $run9('LDC', 'Di Marco Calzature', true);
$t('tekil kip, id ile: yazildi', str_contains($o, 'RC=0') && $co($load9(), 'LDC') === 'Di Marco Calzature');
$o = $run9('schuhhaus-a.at', 'Schuhhaus Günter', true);
$t('tekil kip, site alan adiyla: yazildi', str_contains($o, 'RC=0') && $co($load9(), 'LDA') === 'Schuhhaus Günter');
$t('uyari/fatal yok', !preg_match('/Fatal|Warning|Deprecated|Notice/i', $o));
shell_exec('rm -rf '.escapeshellarg($sb));

echo "\n".($bad ? "KIRMIZI: {$bad}\n" : '')."lead_rename_test: {$ok} iddia gecti".($bad ? ", {$bad} DUSTU" : '')."\n";
exit($bad ? 1 : 0);
