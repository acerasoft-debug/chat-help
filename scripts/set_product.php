<?php
ini_set('display_errors','1'); ini_set('display_startup_errors','1'); error_reporting(E_ALL);
register_shutdown_function(function(){
  $e = error_get_last();
  if ($e && in_array($e['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR],true))
    fwrite(STDERR, "PHP OLUMCUL: {$e['message']} ({$e['file']}:{$e['line']})\n");
});
$home = getenv("HOME");
require $home."/public_html/inc/auth.php";
require $home."/public_html/inc/products.php";
/* vlang_list() burada gerekli (name_i18n/desc_i18n dil dogrulamasi) ve i18n.php
   products.php tarafindan garanti edilmiyor -- KURAL 15. function_exists ile
   gecistirmek daha da kotu olurdu: dosya yuklenmemisse dogrulama SESSIZCE
   atlanir ve gecersiz bir dil kodu kayda girer. */
require_once $home."/public_html/inc/i18n.php";
/* vestra_offers_open() burada da aranıyor: 'offers' alanini yazan dal onu
   cagiriyor ve sunucudaki kod eskiyse hata apply'in ORTASINDA cikardi --
   yani bir kismi yazilmis bir listings.json. Preflight'ta durmasi, deploy'un
   henuz gecmedigini soyleyen okunur bir mesaj demek. */
foreach (['vestra_listings','vestra_save_listings','vestra_data_dir','vestra_offers_open'] as $fn)
  if (!function_exists($fn)) { fwrite(STDERR,"HATA: {$fn}() yok (sunucudaki kod eski olabilir -- once deploy)\n"); exit(1); }

/* seller_uid dogrulamasi icin: hedef id gercek, type=seller bir hesaba
   ait olmali -- yoksa yazim hatasiyla urun sahipsiz/hayalet bir uid'e
   baglanip kimse odeme goremez. */
$sellerIds = [];
foreach (auth_accounts() as $acc) if (($acc['type'] ?? '') === 'seller') $sellerIds[(string)($acc['id'] ?? '')] = true;

$dry = strtolower(trim((string)getenv("P_DRY"))) !== 'false';
$dec = base64_decode((string)getenv("P_JSON"), true);
if ($dec === false) { fwrite(STDERR,"HATA: base64 cozulemedi\n"); exit(1); }
$fixes = json_decode($dec, true);
if (!is_array($fixes) || !$fixes) { fwrite(STDERR,"HATA: JSON dizi degil/bos\n"); exit(1); }

$all = vestra_listings();
if (!count($all)) { fwrite(STDERR,"HATA: listings.json bos\n"); exit(1); }

/* Model kodlari dosya adinda "dsq-s74lb0764.jpg", SKU'da "S74LB0764",
   urun adinda "S74LB0764" gibi farkli yazimlarla duruyor. Karsilastirmayi
   harf/rakam disindaki her seyi atarak yapiyoruz ki "XH16B005 BB04",
   "xh16b005-bb04" ve "XH16B005BB04" ayni sey sayilsin. */
$norm = function($s){ return strtolower(preg_replace('/[^A-Za-z0-9]+/', '', (string)$s)); };

/* Bir urunun icinde aranacak butun metinler tek torbada. */
$haystack = function(array $p) use ($norm) {
  $bits = [ $p['id'] ?? '', $p['sku'] ?? '', $p['name'] ?? '' ];
  foreach ((array)($p['images'] ?? []) as $img) $bits[] = $img;
  if (!empty($p['image'])) $bits[] = $p['image'];
  return $norm(implode('|', array_map('strval', $bits)));
};

$ALLOWED = ['cat','price','moq','tiers','offers','sizes','name','name_i18n','desc','desc_i18n','status','sample_price','sample_platform_pay','seller_uid','seller','colors','images','size_step','min_colors','pinned','specs','specs_remove','dropship','dropship_off','sale_list','ships_from','sold_out','preorder_ship','stock','colorqty','redirect_to',
            'group','group_target','group_price','group_deposit_pct','group_balance_days','group_extend_days','group_started','group_deadline','group_extended_to','group_min_qty','group_models','group_title','group_min_colors'];
/* group_extended_to: cron_pool_sweep.php'nin bir havuzu KENDI koydugu tek seferlik
   uzatma tarihi (inc/products.php: vestra_group_deadline() bu alani group_deadline'in
   ONUNE alir). Bir havuzu yeniden acmak icin yalnizca group_deadline yazmak yetmez --
   sweeper daha once zaten uzatmis ve bu alani doldurmus olabilir; o zaman eski
   (gecmis) uzatma tarihi hala kazanir ve havuz "kapali" gorunmeye devam eder. Bu
   yuzden ikisi BIRLIKTE, ayni tarihe yazilir. */
$errors = []; $plan = [];

foreach ($fixes as $n => $fx) {
  $ctx = "satir ".($n+1);
  if (!is_array($fx)) { $errors[] = "{$ctx}: nesne degil"; continue; }
  $m = trim((string)($fx['match'] ?? ''));
  if ($m === '') { $errors[] = "{$ctx}: 'match' bos"; continue; }
  $expect = isset($fx['expect']) ? (int)$fx['expect'] : 1;
  if ($expect < 1) { $errors[] = "{$ctx} ({$m}): expect >= 1 olmali"; continue; }

  /* Degistirilecek bir alan yoksa satir anlamsiz -- sessizce gecmek yerine
     soyle, cunku genelde bir yazim hatasinin belirtisi. */
  $set = [];
  foreach ($ALLOWED as $k) if (array_key_exists($k, $fx) && $fx[$k] !== '') $set[$k] = $fx[$k];
  if (!$set) { $errors[] = "{$ctx} ({$m}): degistirilecek alan yok"; continue; }
  if (isset($set['price']) && (!is_numeric($set['price']) || (float)$set['price'] <= 0)) {
    $errors[] = "{$ctx} ({$m}): gecersiz fiyat '{$set['price']}'"; continue;
  }
  if (isset($set['moq']) && ((int)$set['moq'] < 1)) {
    $errors[] = "{$ctx} ({$m}): gecersiz moq '{$set['moq']}'"; continue;
  }
  /* sample_price 0 = numune KUTUSU YOK. Sifir bilerek gecerli: eskiden
     "<= 0" hata sayiliyordu, yani bir ilandan numune secenegini kaldirmanin
     hicbir yolu yoktu (panelde de alan yoktu). Negatif hala hata. */
  if (isset($set['sample_price']) && (!is_numeric($set['sample_price']) || (float)$set['sample_price'] < 0)) {
    $errors[] = "{$ctx} ({$m}): gecersiz sample_price '{$set['sample_price']}'"; continue;
  }
  /* offers: teklif (pazarlik) kutusu acik mi. 'off' vestra_offers_open()'in
     okudugu 'no_offers' anahtarini kaldirir -- saticinin kendi formundan geri
     acamayacagi tek yol bu (seller.php her kaydetmede 'offers' alanini
     kutucuktan yeniden yaziyor). */
  if (isset($set['offers'])) {
    $ov = strtolower(trim((string)$set['offers']));
    if (!in_array($ov, ['on','off','true','false','1','0','acik','kapali'], true)) {
      $errors[] = "{$ctx} ({$m}): offers 'on' ya da 'off' olmali ('{$set['offers']}')"; continue;
    }
    $set['offers'] = in_array($ov, ['on','true','1','acik'], true);
  }
  /* tiers: kademe merdiveni [{"min":48,"price":19.00},{"min":104,"price":17.50}].
     'price' alani TUM kademeleri ayni rakama yaziyor, yani iki basamakli bir
     merdiven kuramiyordu; tek yol paneldi. Sirali ve tekil olmasi sart:
     vestra_unit_price() listeyi bastan sona geziyor ve SON eslesen basamagi
     aliyor, yani sirasi bozuk bir merdivende alici daha buyuk miktarda daha
     pahali fiyat gorebilir. */
  if (isset($set['tiers'])) {
    if (!is_array($set['tiers']) || !$set['tiers']) {
      $errors[] = "{$ctx} ({$m}): tiers bos-olmayan dizi olmali"; continue;
    }
    $tt = []; $bad = false; $prevMin = 0; $prevPrice = null;
    foreach ($set['tiers'] as $row) {
      if (!is_array($row) || !isset($row['min'], $row['price'])
          || !is_numeric($row['min']) || !is_numeric($row['price'])
          || (int)$row['min'] < 1 || (float)$row['price'] <= 0) { $bad = true; break; }
      $mn = (int)$row['min']; $pr = round((float)$row['price'], 2);
      if ($mn <= $prevMin) { $bad = true; break; }               // artan ve tekil
      if ($prevPrice !== null && $pr >= $prevPrice) {
        $errors[] = "{$ctx} ({$m}): kademe {$mn} -> €{$pr}, bir onceki basamaktan ucuz degil"
                  . " — daha cok alan daha pahaliya alir"; continue 2;
      }
      $prevMin = $mn; $prevPrice = $pr;
      $tt[] = ['min' => $mn, 'price' => $pr];
    }
    if ($bad) { $errors[] = "{$ctx} ({$m}): tiers satirlari {min,price} olmali, min artan"; continue; }
    $set['tiers'] = $tt;
  }
  /* sale_list: SADECE "was" fiyatini (uzeri cizili list) degistirir, tiers'a
     (gercekten tahsil edilen tutara) DOKUNMAZ -- 'price' alaninin aksine.
     Gorunurde indirim rozeti ("-%X") gostermek icin ama gercek satis
     fiyatini degistirmemek icin var: liste fiyatini yukari cekip aradaki
     farki indirim gibi gostermek. mode='sale' olmayan bir urunde badge hic
     gorunmez (product.php sadece mode==='sale' iken 'was' satirini basiyor)
     -- bu yuzden hedef urunun zaten sale modunda olmasi bekleniyor,
     burada mode'u degistirmiyoruz. */
  if (isset($set['sale_list']) && (!is_numeric($set['sale_list']) || (float)$set['sale_list'] <= 0)) {
    $errors[] = "{$ctx} ({$m}): gecersiz sale_list '{$set['sale_list']}'"; continue;
  }
  /* sold_out: yalniz gercek bir bool. "false" STRINGI true'ya donusurdu ve
     urun satista kalirdi -- operator kapattigini sanip satmaya devam ederdi. */
  if (array_key_exists('sold_out', $set) && !is_bool($set['sold_out'])) {
    $errors[] = "{$ctx} ({$m}): sold_out true ya da false (tirnaksiz) olmali"; continue;
  }
  /* preorder_ship: YYYY-MM-DD ve GERCEK bir takvim gunu. Bozuk bir tarih notu
     sessizce susturur, yani operator ilanda "Ekim basi" yazdigini sanir.
     Eski kontrol yalniz BICIME bakiyordu, yani bu yorumun vaat ettigi "gecerli
     tarih" hic denetlenmiyordu (2 Eki 2026, Gallery Dept.): '2026-13-45'
     regex'ten geciyor, strtotime() false donuyor ve not SESSIZCE susuyordu;
     '2026-02-31' ise 3 Mart'a kayip sayfaya yanlis bir ay yazdiriyordu. */
  if (isset($set['preorder_ship'])) {
    $pd = (string)$set['preorder_ship'];
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $pd, $pm) || !checkdate((int)$pm[2], (int)$pm[3], (int)$pm[1])) {
      $errors[] = "{$ctx} ({$m}): preorder_ship YYYY-MM-DD ve gercek bir takvim gunu olmali ('{$pd}')"; continue;
    }
  }
  /* 'rejected' de yazilabilir ve bu bir genisletme degil, eksigin kapatilmasi:
     panelin KENDI "ilani reddet" dugmesi (admin.php:96) tam bu degeri yaziyor,
     admin listeler sekmesi icin ayri bir kova ($rejList) ve rozeti var, satici
     panelinde "✗ Rejected" diye gorunuyor. Betik yalnizca onu YAZAMIYORDU.
     Bir ilani siteden KALDIRMANIN dogru durumu bu: 'pending' onu operatorun
     "⚠️ Listings to approve" sayacina sokar, yani kaldirdigimiz ilan her sabah
     yapilacak is gibi gorunur -- KURAL 2c'nin ("her gun 0 bekleyen yazan uyari
     okunmamayi ogretir") ters yonu. 'suspended' bilerek YOK: o alan satici
     askisinin ilan tarafindaki karsiligi, bir urun kararinin degil. */
  if (isset($set['status']) && !in_array($set['status'], ['approved','pending','rejected'], true)) {
    $errors[] = "{$ctx} ({$m}): status 'approved', 'pending' ya da 'rejected' olmali"; continue;
  }
  if (isset($set['seller_uid']) && !isset($sellerIds[(string)$set['seller_uid']])) {
    $errors[] = "{$ctx} ({$m}): seller_uid '{$set['seller_uid']}' gecerli bir seller hesabina ait degil"; continue;
  }
  if (isset($set['colors'])) {
    if (!is_array($set['colors']) || !$set['colors'] || array_filter($set['colors'], fn($c)=>!is_string($c)||$c==='')) {
      $errors[] = "{$ctx} ({$m}): colors bos-olmayan string dizisi olmali"; continue;
    }
    $set['colors'] = array_values($set['colors']);
  }
  if (isset($set['images'])) {
    if (!is_array($set['images']) || !$set['images']) { $errors[] = "{$ctx} ({$m}): images bos-olmayan dizi olmali"; continue; }
    foreach ($set['images'] as $img) {
      if (!is_string($img) || !preg_match('#^/uploads/[a-z0-9._/-]+$#i', $img)) { $errors[] = "{$ctx} ({$m}): gecersiz gorsel yolu '".(is_string($img)?$img:'?')."'"; continue 2; }
      if (!is_file($home.'/public_html'.$img)) { $errors[] = "{$ctx} ({$m}): gorsel SUNUCUDA YOK: {$img}"; continue 2; }
    }
    $set['images'] = array_values($set['images']);
  }
  if (isset($set['size_step']) && (!is_numeric($set['size_step']) || (int)$set['size_step'] < 2)) {
    $errors[] = "{$ctx} ({$m}): gecersiz size_step '{$set['size_step']}'"; continue;
  }
  if (isset($set['min_colors']) && (!is_numeric($set['min_colors']) || (int)$set['min_colors'] < 1)) {
    $errors[] = "{$ctx} ({$m}): gecersiz min_colors '{$set['min_colors']}'"; continue;
  }
  if (isset($set['specs'])) {
    if (!is_array($set['specs']) || !$set['specs']) { $errors[] = "{$ctx} ({$m}): specs bos-olmayan {anahtar:deger} nesnesi olmali"; continue; }
    foreach ($set['specs'] as $sk => $sv) {
      if (!is_string($sk) || $sk === '' || !is_string($sv) || $sv === '') { $errors[] = "{$ctx} ({$m}): specs icinde gecersiz anahtar/deger"; continue 2; }
    }
  }
  if (isset($set['specs_remove'])) {
    if (!is_array($set['specs_remove']) || !$set['specs_remove']) { $errors[] = "{$ctx} ({$m}): specs_remove bos-olmayan anahtar dizisi olmali"; continue; }
    foreach ($set['specs_remove'] as $sk) {
      if (!is_string($sk) || $sk === '') { $errors[] = "{$ctx} ({$m}): specs_remove icinde gecersiz anahtar"; continue 2; }
    }
  }
  /* ships_from = malin CIKTIGI yer (urun sayfasindaki "Ships from ..." satiri).
     Alici bunu gumruk ve teslim suresi icin okuyor, yani yanlis deger yanlis
     bilgi. Tahmin edilmiyor: ya 2 harfli ulke kodu ya da ulke adi yazilir.
     Cozulemeyen bir ad HATA degil UYARI -- metin yine basilir, sadece
     bayrak dusme riski var ve operator bunu gormeli. */
  if (isset($set['ships_from'])) {
    $z = trim((string)$set['ships_from']);
    if ($z === '' || mb_strlen($z) > 40) { $errors[] = "{$ctx} ({$m}): ships_from bos olamaz, en fazla 40 karakter"; continue; }
    if (mb_strlen($z) === 2 && !preg_match('/^[A-Za-z]{2}$/', $z)) {
      $errors[] = "{$ctx} ({$m}): 2 karakterli ships_from bir ulke kodu olmali (ör: IT, DE)"; continue;
    }
    $set['ships_from'] = mb_strlen($z) === 2 ? mb_strtoupper($z) : $z;
  }
  if (isset($set['dropship_off'])) {
    /* Dropship'i TEK ilandan cikarmak icin. Marka/urun-turu listeleri kategori
       genisliginde calisiyor; bu alan yalnizca bu ilani etkiler. */
    if (!is_bool($set['dropship_off'])) { $errors[] = "{$ctx} ({$m}): dropship_off true/false olmali"; continue; }
  }
  if (isset($set['dropship'])) {
    $d = $set['dropship'];
    if (!is_array($d)) { $errors[] = "{$ctx} ({$m}): dropship bir nesne olmali"; continue; }
    if (!isset($d['price']) || !is_numeric($d['price']) || (float)$d['price'] <= 0) { $errors[] = "{$ctx} ({$m}): dropship.price gecersiz"; continue; }
    if (!isset($d['ship_fr']) || !is_numeric($d['ship_fr']) || (float)$d['ship_fr'] < 0) { $errors[] = "{$ctx} ({$m}): dropship.ship_fr gecersiz"; continue; }
    if (!isset($d['ship_eu']) || !is_numeric($d['ship_eu']) || (float)$d['ship_eu'] < 0) { $errors[] = "{$ctx} ({$m}): dropship.ship_eu gecersiz"; continue; }
    if (!isset($d['stock']) || !is_array($d['stock']) || !$d['stock']) { $errors[] = "{$ctx} ({$m}): dropship.stock bos-olmayan {renk:{beden:adet}} nesnesi olmali"; continue; }
    foreach ($d['stock'] as $colour => $sizes) {
      if (!is_string($colour) || $colour === '' || !is_array($sizes) || !$sizes) { $errors[] = "{$ctx} ({$m}): dropship.stock icinde gecersiz renk '{$colour}'"; continue 2; }
      foreach ($sizes as $sz => $qty) {
        if (!is_string($sz) || $sz === '' || !is_numeric($qty) || (int)$qty < 0) { $errors[] = "{$ctx} ({$m}): dropship.stock.{$colour} icinde gecersiz beden/adet"; continue 3; }
      }
    }
    $set['dropship'] = [
      'enabled' => true,
      'price'   => (float)$d['price'],
      'ship_fr' => (float)$d['ship_fr'],
      'ship_eu' => (float)$d['ship_eu'],
      'stock'   => $d['stock'],
    ];
  }

  /* stock: GERCEK beden stogu (inc/stock.php: vestra_stock_real). Tedarikci
     listesinden gelen adet; fiyat listeleri turetilmis bandin yerine bunu basar.
     null = alani KALDIR (liste turetilmis banda doner). Dogrulama add-products
     ile AYNI ve HEPSI ya da HICBIRI: yarim okunmus bir stok satiri, yanlis bir
     stok satiridir. JSON'da "44" gibi sayisal beden PHP'de int anahtar olur --
     anahtar (string)'e cevrilip denetleniyor; duz dizi ([2,6,5]) beden bilgisi
     tasimadigi icin reddediliyor. */
  if (array_key_exists('stock', $set) && $set['stock'] !== null) {
    $rs = $set['stock'];
    if (!is_array($rs) || !$rs || array_keys($rs) === range(0, count($rs) - 1)) {
      $errors[] = "{$ctx} ({$m}): stock {beden: adet} nesnesi ya da null olmali"; continue;
    }
    /* IKI SEKIL (inc/stock.php: vestra_stock_real): duz {beden: adet} ya da renk
       basina {renk: {beden: adet}} -- cok renkli TEK ilan (Burberry pike polo,
       8 model, 29 Eyl 2026). Karisik sekil reddedilir; renk adlari ilanin kendi
       renk listesinde olmak ZORUNDA (asagida, ilan bulununca denetleniyor):
       olmayan bir rengin stogu, hicbir alicinin secemeyecegi bir stoktur. */
    $flatOk = function (array $mm, string &$why): ?array {
      $o = [];
      foreach ($mm as $sz => $q) {
        $sz = trim((string)$sz);
        if (!preg_match('/^[A-Za-z0-9]{1,6}$/', $sz) || !is_int($q) || $q < 0) { $why = $sz; return null; }
        $o[$sz] = $q;
      }
      return $o;
    };
    $nested = true; $anyArr = false;
    foreach ($rs as $v0) { if (is_array($v0)) $anyArr = true; else $nested = false; }
    $why = '';
    if (!$anyArr) {
      $stk = $flatOk($rs, $why);
      if ($stk === null) { $errors[] = "{$ctx} ({$m}): stock icinde gecersiz beden/adet ('{$why}')"; continue; }
    } elseif (!$nested) {
      $errors[] = "{$ctx} ({$m}): stock ya {beden: adet} ya {renk: {beden: adet}} olmali, ikisi karisik olamaz"; continue;
    } else {
      $stk = [];
      foreach ($rs as $cn => $mm) {
        $cn = trim((string)$cn);
        $o = ($cn === '' || !$mm) ? null : $flatOk((array)$mm, $why);
        if ($o === null) { $errors[] = "{$ctx} ({$m}): stock.{$cn} icinde gecersiz beden/adet ('{$why}')"; continue 2; }
        $stk[$cn] = $o;
      }
    }
    $set['stock'] = $stk;
  }
  /* colorqty: parca (lot 1) ilanda renk basina adet secici (vestra_is_colorqty_listing).
     sold_out ile ayni kural: yalniz gercek bool. */
  if (array_key_exists('colorqty', $set) && !is_bool($set['colorqty'])) {
    $errors[] = "{$ctx} ({$m}): colorqty true ya da false (tirnaksiz) olmali"; continue;
  }
  /* redirect_to: bu ilan bir BASKASINA katlandi (vestra_product_redirect). Hedef
     kayitta VAR olmali ve kendisi olamaz; null alani kaldirir. Yazim hatasi
     yapilmis bir hedef, mektuplardaki eski adresi 404'e yollar -- burada durur. */
  if (array_key_exists('redirect_to', $set) && $set['redirect_to'] !== null) {
    $rt = trim((string)$set['redirect_to']);
    $rtOk = false;
    foreach ($all as $pp) if (trim((string)($pp['id'] ?? '')) === $rt) { $rtOk = true; break; }
    if ($rt === '' || !$rtOk) { $errors[] = "{$ctx} ({$m}): redirect_to '{$rt}' kayitta yok"; continue; }
    $set['redirect_to'] = $rt;
  }

  /* ─── Havuz (grup alimi) alanlari ───────────────────────────────
     Bu alanlar dogrudan PARA belirliyor: group_price alicinin odeyecegi
     birim fiyat, group_deposit_pct ise katilim aninda kartindan cekilecek
     oran. Bir yazim hatasi burada sessizce yanlis tahsilat demek, o yuzden
     hepsi tek tek dogrulaniyor. */
  if (isset($set['group_price']) && (!is_numeric($set['group_price']) || (float)$set['group_price'] <= 0)) {
    $errors[] = "{$ctx} ({$m}): gecersiz group_price '{$set['group_price']}'"; continue;
  }
  if (isset($set['group_target']) && (int)$set['group_target'] < 1) {
    $errors[] = "{$ctx} ({$m}): group_target >= 1 olmali"; continue;
  }
  if (isset($set['group_min_qty']) && (int)$set['group_min_qty'] < 1) {
    $errors[] = "{$ctx} ({$m}): group_min_qty >= 1 olmali"; continue;
  }
  /* Secilmesi zorunlu renk sayisi, sunulan renk sayisindan fazla olamaz --
     aksi halde form ASLA gonderilemez: alici 4 renk secmek zorundadir ama
     ortada 3 renk vardir. Sunulan liste ayni istekte geliyorsa ondan,
     gelmiyorsa urunun mevcut listesinden sayiliyor. */
  if (isset($set['group_min_colors']) && (int)$set['group_min_colors'] < 0) {
    $errors[] = "{$ctx} ({$m}): group_min_colors negatif olamaz"; continue;
  }
  /* Karma havuzun kapsadigi modeller: her id GERCEK bir urune cozulmeli.
     Cozulemeyen bir id havuz sayfasinda sessizce atlanir -- alici 10 model
     vaat edilen bir havuzda 8 model gorur ve eksigi fark etmez. */
  if (isset($set['group_models'])) {
    if (!is_array($set['group_models']) || !$set['group_models']) {
      $errors[] = "{$ctx} ({$m}): group_models bos ya da dizi degil"; continue;
    }
    $bad = [];
    foreach ($set['group_models'] as $gm) {
      $hit = false;
      foreach ($all as $q) if (($q['id'] ?? '') === (string)$gm) { $hit = true; break; }
      if (!$hit) $bad[] = (string)$gm;
    }
    if ($bad) { $errors[] = "{$ctx} ({$m}): group_models icinde bulunamayan id: ".implode(', ', $bad); continue; }
  }
  if (isset($set['group_deposit_pct'])) {
    $dp = $set['group_deposit_pct'];
    if (!is_numeric($dp) || (float)$dp < 0 || (float)$dp > 100) {
      $errors[] = "{$ctx} ({$m}): group_deposit_pct 0-100 arasi olmali, '{$dp}' verildi"; continue;
    }
  }
  foreach (['group_balance_days','group_extend_days'] as $dk) {
    if (isset($set[$dk]) && (int)$set[$dk] < 1) {
      $errors[] = "{$ctx} ({$m}): {$dk} >= 1 olmali"; continue 2;
    }
  }
  foreach (['group_started','group_deadline'] as $tk) {
    if (isset($set[$tk]) && strtotime((string)$set[$tk]) === false) {
      $errors[] = "{$ctx} ({$m}): {$tk} okunamayan tarih: '{$set[$tk]}'"; continue 2;
    }
  }
  /* Havuz acan her satir son tarihi ACIKCA vermeli. Bos birakilirsa
     vestra_group_deadline() baslangici her sayfa yuklemesinde date('c')
     sayar; son tarih surekli 14 gun ileri kayar ve geri sayim asla
     bitmez -- havuz sonsuza kadar "acik" gorunur. */
  if (!empty($set['group']) && !isset($set['group_deadline']) && !isset($set['group_started'])) {
    $errors[] = "{$ctx} ({$m}): havuz aciliyor ama group_deadline/group_started yok — son tarih hic dolmaz"; continue;
  }

  $mn = $norm($m);
  /* 1. BIREBIR id/sku (yalnizca bosluk ve buyuk/kucuk harf hosgorusu).
     $norm harf-rakam disindaki her seyi atiyor, yani YALNIZCA tireyle ayrilan
     iki ayri ilan ayni metne iniyor: 'pp-tobby-pirata' ile 'pp-tobbypirata'
     ikisi de 'pptobbypirata'. Ikisi de gercek ve ayri ilan oldugundan hicbiri
     hedeflenemiyordu -- is "2 urune uydu" deyip duruyordu (3 Eyl 2026, 85
     satirlik ayakkabi seri duzeltmesi). Birebir yazilan bir id, hosgorulu
     eslesmeden once gelmeli: daralttigi icin guvenli, tarif ettigi urun tek. */
  $idx = [];
  foreach ($all as $i => $p) {
    if (strcasecmp(trim((string)($p['id'] ?? '')), trim($m)) === 0
     || strcasecmp(trim((string)($p['sku'] ?? '')), trim($m)) === 0) $idx[] = $i;
  }
  /* 2. Sonra hosgorulu TAM eslesme (id ya da sku). Alt dize aramasi kisa
     kodlarda fazla urune uyabiliyor; tam eslesme varsa o kazanir. */
  if (!$idx) foreach ($all as $i => $p) {
    if ($norm($p['id'] ?? '') === $mn || $norm($p['sku'] ?? '') === $mn) $idx[] = $i;
  }
  if (!$idx) {
    foreach ($all as $i => $p) if (strpos($haystack($p), $mn) !== false) $idx[] = $i;
  }

  if (count($idx) !== $expect) {
    $found = [];
    foreach ($idx as $i) $found[] = ($all[$i]['id'] ?? '?').' ['.($all[$i]['cat'] ?? '?').']';
    $errors[] = "{$ctx} ({$m}): expect={$expect} ama ".count($idx)." urune uydu"
              . ($found ? " -> ".implode(', ', array_slice($found,0,8)) : " -> hicbiri");
    continue;
  }
  /* Havuz fiyati urunun EN UCUZ kademesinin altinda olmali. Ustunde ya da
     esitse havuz tek basina almaya gore hicbir sey kazandirmaz: sayfa
     "-%0" yazar, ki kod tabani bu sahte indirim gorunumune karsi zaten
     uyariyor (vestra_on_sale). Bu kontrol bir kez gercekten gerekti --
     16.90'in aslinda 500+ fiyati oldugu, 19.90'in ise 20.00'lik tek
     kademenin 10 kurus altinda kaldigi fark edilmeseydi vitrine sifir
     indirimli bir havuz cikacakti. */
  if (isset($set['group_price'])) {
    foreach ($idx as $i) {
      $tp = [];
      foreach ((array)($all[$i]['tiers'] ?? []) as $t) if (isset($t['price'])) $tp[] = (float)$t['price'];
      if (!$tp) continue;
      $cheapest = min($tp);
      if ((float)$set['group_price'] >= $cheapest) {
        $errors[] = "{$ctx} ({$m}): group_price ".$set['group_price']." >= en ucuz kademe {$cheapest}"
                  . " — havuz indirim vermez, vitrinde -%0 cikar";
        continue 2;
      }
    }
  }

  /* MOQ, paket adimin KATI olmali. Sepet ve teklif ucu miktari
     size_step'in katina YUKARI yuvarliyor ($qty % $step), yani 10'luk
     paketli bir ilanda "min 48" yazmak sayfada 48, kasada 50 demek --
     ilan edilen minimum hicbir zaman satin alinamiyor. Ayni sebeple
     kademelerin basamaklari da adima oturmali: oturmayan bir basamaga
     (ornegin 104, adim 10) alici tam olarak hic ulasamaz, fiyat ancak
     bir sonraki katta (110) devreye girer. MOQ hata, basamak uyari:
     birincisi ilani yalanci yapar, ikincisi yalnizca erisilmez. */
  $stepNew = isset($set['size_step']) ? (int)$set['size_step'] : null;
  $moqNew  = isset($set['moq'])       ? (int)$set['moq']       : null;
  foreach ($idx as $i) {
    /* Renk basina stok: her renk ilanin renk listesinde olmali (ayni istekte
       gelen liste varsa ondan, yoksa kayittakinden). */
    if (isset($set['stock']) && is_array($set['stock']) && is_array(reset($set['stock']))) {
      $offeredC = isset($set['colors']) && is_array($set['colors']) ? $set['colors'] : (array)($all[$i]['colors'] ?? []);
      $offeredC = array_map(fn($c) => trim((string)$c), $offeredC);
      foreach (array_keys($set['stock']) as $cn) if (!in_array((string)$cn, $offeredC, true)) {
        $errors[] = "{$ctx} ({$m}): stock rengi '{$cn}' ilanin renk listesinde yok (".implode(', ', $offeredC).")";
        continue 3;
      }
    }
    if (isset($set['redirect_to']) && trim((string)$set['redirect_to']) === trim((string)($all[$i]['id'] ?? ''))) {
      $errors[] = "{$ctx} ({$m}): redirect_to ilanin kendisi olamaz"; continue 2;
    }
    $step = $stepNew ?? (int)($all[$i]['size_step'] ?? 0);
    $moq  = $moqNew  ?? (int)($all[$i]['moq'] ?? 0);
    if ($step > 1 && $moq > 0 && $moq % $step !== 0) {
      $errors[] = "{$ctx} ({$m}): moq {$moq}, paket adimi {$step} — sepet {$moq} adedi "
                . (int)(ceil($moq/$step)*$step)." adede yuvarlar, ilan edilen minimum alinamaz";
      continue 2;
    }
    if (isset($set['tiers'])) {
      $firstMin = (int)$set['tiers'][0]['min'];
      if ($moq > 0 && $firstMin !== $moq) {
        $errors[] = "{$ctx} ({$m}): ilk kademe {$firstMin} ama moq {$moq} — merdiven "
                  . "minimum siparisten baslamali (ikisini ayni satirda verin)";
        continue 2;
      }
      if ($step > 1) {
        foreach ($set['tiers'] as $tr) {
          if ((int)$tr['min'] % $step !== 0)
            echo "  UYARI ({$m}): kademe ".(int)$tr['min']." paket adimi {$step}'e oturmuyor"
               . " — alici o miktara tam ulasamaz, fiyat ".(int)(ceil($tr['min']/$step)*$step)." adette devreye girer\n";
        }
      }
    }
    /* mode='offer' urunun sabit fiyati yok: teklifi kapatmak onu satin
       alinamaz birakir. Bu bilesim burada reddediliyor ki urun sayfasinin
       savunma dali (bos bir kutu yerine "artik siparis edilemiyor") hic
       gerekmesin. */
    if (isset($set['offers']) && $set['offers'] === false
        && (string)($all[$i]['mode'] ?? '') === 'offer') {
      $errors[] = "{$ctx} ({$m}): mode='offer' ilanda teklif kapatilamaz — urun satin "
                . "alinamaz hale gelir; once mode'u fixed/sale yapin (panel: Prices)";
      continue 2;
    }
  }

  /* Zorunlu renk sayisi sunulan renk sayisini asamaz: asarsa form ASLA
     gonderilemez -- alici 4 renk secmek zorundadir ama ortada 3 renk
     vardir ve dugme her tiklamada sessizce reddedilir. Sunulan liste
     ayni istekte geliyorsa ondan, gelmiyorsa urunun mevcut listesinden
     sayiliyor. */
  if (!empty($set['group_min_colors'])) {
    foreach ($idx as $i) {
      $offered = isset($set['colors']) && is_array($set['colors'])
               ? $set['colors'] : (array)($all[$i]['colors'] ?? []);
      if ((int)$set['group_min_colors'] > count($offered)) {
        $errors[] = "{$ctx} ({$m}): group_min_colors=".(int)$set['group_min_colors']
                  . " ama sunulan renk sayisi ".count($offered)." — form hic gonderilemez";
        continue 2;
      }
    }
  }

  foreach ($idx as $i) $plan[] = [$i, $set, $m];
}

if ($errors) {
  fwrite(STDERR, "\n===== HATA (".count($errors).") — HICBIR SEY KAYDEDILMEDI =====\n");
  foreach ($errors as $e) fwrite(STDERR, "  - {$e}\n");
  exit(1);
}

/* Ayni urune iki farkli satirdan dokunmak sessiz bir cakisma olurdu. */
$seen = [];
foreach ($plan as [$i, , $m]) {
  if (isset($seen[$i])) {
    fwrite(STDERR, "HATA: '{$m}' ve '{$seen[$i]}' ayni urune ({$all[$i]['id']}) isaret ediyor\n");
    exit(1);
  }
  $seen[$i] = $m;
}

echo "===== PLAN (".count($plan)." urun) =====\n";
$changes = 0;
foreach ($plan as [$i, $set, $m]) {
  $p = $all[$i];
  $line = [];
  foreach ($set as $k => $v) {
    if ($k === 'price') {
      $new = (float)$v; $old = (float)($p['list'] ?? 0);
      if ($old === $new) continue;
      $line[] = "list {$old} -> {$new}";
      $all[$i]['list'] = $new;
      /* tiers gercek siparis tutarini belirliyor; list'i degistirip
         tiers'i birakmak bir fiyat gosterip baskasini tahsil etmek olur. */
      if (!empty($p['tiers']) && is_array($p['tiers'])) {
        $c = 0;
        foreach ($p['tiers'] as $ti => $t) {
          if (!is_array($t)) continue;
          $all[$i]['tiers'][$ti]['price'] = $new; $c++;
        }
        if ($c) $line[] = "tiers({$c}) -> {$new}";
      }
      $changes++;
    } elseif ($k === 'sample_price') {
      $new = (float)$v; $old = (float)($p['sample_price'] ?? 0);
      if ($old === $new) continue;
      $line[] = "sample_price {$old} -> {$new}".($new <= 0 ? '  (numune kutusu KALKAR)' : '');
      $all[$i]['sample_price'] = $new;
      $changes++;
    } elseif ($k === 'tiers') {
      $fmt = function (array $t): string {
        $s = [];
        foreach ($t as $r) $s[] = ($r['min'] ?? '?').'+ -> €'.number_format((float)($r['price'] ?? 0), 2);
        return $s ? implode(' | ', $s) : '(yok)';
      };
      $old = [];
      foreach ((array)($p['tiers'] ?? []) as $r)
        if (is_array($r) && isset($r['min'], $r['price'])) $old[] = ['min'=>(int)$r['min'], 'price'=>round((float)$r['price'],2)];
      if ($old === $v) continue;
      $line[] = "tiers ".$fmt($old)."  ->  ".$fmt($v);
      $all[$i]['tiers'] = $v;
      $changes++;
    } elseif ($k === 'offers') {
      /* Iki alan birden: 'offers' saticinin kutucugu, 'no_offers' operatorun
         ezicisi. Kapatirken ikisi de yazilmali -- yalnizca 'offers'i silmek
         saticinin bir sonraki kaydinda geri gelirdi; yalnizca 'no_offers'
         yazmak da satici panelinde kutucugu isaretli birakirdi, yani satici
         acik sanip aciklama beklerdi. */
      $wasOpen = vestra_offers_open($p);
      if ($wasOpen === (bool)$v) continue;
      if ($v) { $all[$i]['offers'] = true;  unset($all[$i]['no_offers']); }
      else    { $all[$i]['no_offers'] = true; unset($all[$i]['offers']); }
      $line[] = 'teklif '.($wasOpen ? 'ACIK' : 'kapali').' -> '.($v ? 'ACIK' : 'KAPALI')
              . ($v ? '' : '  (urun sayfasindaki kutu ve /offer ucu birlikte kapanir)');
      $changes++;
    } elseif ($k === 'moq') {
      $new = (int)$v; $old = (int)($p['moq'] ?? 0);
      if ($old === $new) continue;
      $line[] = "moq {$old} -> {$new}";
      $all[$i]['moq'] = $new;
      if (!empty($all[$i]['tiers']) && is_array($all[$i]['tiers'])) {
        $k0 = array_key_first($all[$i]['tiers']);
        if (is_array($all[$i]['tiers'][$k0] ?? null)) $all[$i]['tiers'][$k0]['min'] = $new;
      }
      $changes++;
    } elseif ($k === 'size_step' || $k === 'min_colors') {
      $new = (int)$v; $old = (int)($p[$k] ?? 0);
      if ($old === $new) continue;
      $line[] = "{$k} {$old} -> {$new}";
      $all[$i][$k] = $new;
      $changes++;
    } elseif ($k === 'name_i18n' || $k === 'desc_i18n') {
      /* Dil bazli ad/aciklama (KURAL 21). Ilanin `name`/`desc` alani hicbir
         yerde t()'den gecmiyor; ceviri ilanin UZERINDE duruyor ve tek cozucu
         vestra_product_name()/_desc() okuyor.
         Dogrulama: bilinen dil kodu + bos olmayan dizge. Bilinmeyen bir
         anahtari sessizce saklamak, kayitta duran ama hicbir sayfanin
         okumadigi bir "ceviri" birakirdi -- cevrildi sanilan bir bosluk. */
      if (!is_array($v)) { echo "  ! {$m}: {$k} dizi olmali, atlandi\n"; continue; }
      $langs = array_keys(vlang_list());
      $new = []; $bad = '';
      foreach ($v as $lg => $txt) {
        if ($langs && !in_array((string)$lg, $langs, true)) { $bad = (string)$lg; break; }
        if (!is_string($txt) || trim($txt) === '') { $bad = (string)$lg.' (bos)'; break; }
        $new[(string)$lg] = trim($txt);
      }
      if ($bad !== '') { echo "  ! {$m}: {$k} gecersiz dil '{$bad}', atlandi\n"; continue; }
      $old = is_array($p[$k] ?? null) ? $p[$k] : [];
      if ($old === $new) continue;
      $line[] = sprintf('%s (%d dil) -> (%d dil): %s', $k, count($old), count($new), mb_substr((string)($new['en'] ?? reset($new)), 0, 46));
      $all[$i][$k] = $new;
      $changes++;
    } elseif ($k === 'colors' || $k === 'images' || $k === 'group_models') {
      $new = array_values($v); $old = array_values((array)($p[$k] ?? []));
      if ($old === $new) continue;
      $line[] = "{$k} (".count($old).") -> (".count($new)."): ".implode(', ', $k === 'colors' ? $new : array_map('basename', $new));
      $all[$i][$k] = $new;
      $changes++;
    } elseif ($k === 'specs_remove') {
      $old = (array)($p['specs'] ?? []);
      $new = $old;
      $removed = [];
      foreach ($v as $sk) { if (array_key_exists($sk, $new)) { unset($new[$sk]); $removed[] = $sk; } }
      if (!$removed) continue;
      $line[] = "specs -".count($removed).": ".implode(', ', $removed);
      $all[$i]['specs'] = $new;
      $changes++;
    } elseif ($k === 'sale_list') {
      $new = (float)$v; $old = (float)($p['list'] ?? 0);
      if ($old === $new) continue;
      /* Burada ESKIDEN "gorunen indirim ~%X" yaziyordu ve X'i ESKI ilk kademeden
         (tiers[0]) hesapliyordu. Sayfanin rozeti ise EN DUSUK kademeden
         (vestra_discount -> vestra_from_price) hesaplaniyor: iki kademeli
         26,90/25,00 bir ilanda list 33,33 icin satir "%19" diyor, sayfa "-%25"
         basiyordu -- ve ayni satirda kademeler de degisiyorsa X, henuz
         yazilmamis eski merdivenden cikiyordu. Rakam artik burada tahmin
         edilmiyor: satirin sonundaki SAYFADA satiri, SAYFANIN cagirdigi ayni
         fonksiyonlarla son kayittan hesaplaniyor. */
      $line[] = "sale_list(SADECE was) {$old} -> {$new}  (tiers'a dokunmaz; sayfanin gosterecegi rozet asagidaki SAYFADA satirinda)";
      $all[$i]['list'] = $new;
      $changes++;
    } elseif ($k === 'preorder_ship') {
      /* ON SIPARIS SEVK TARIHI (operator, 22 Eyl 2026 AMI Paris; 2 Eki 2026
         Gallery Dept.: "stock giris tarihi ekim sonu yap fakat siparisleri
         kabul ediyoruz"). Genel dal yalniz "preorder_ship '(yok)' ->
         '2026-10-31'" yaziyordu ve SAYFANIN NE BASACAGINI soylemiyordu. Not
         tarihten uretiliyor ve tarih gecince KENDILIGINDEN susuyor; yani gecmis
         bir tarih yazmak sessizce "notu kaldirmak" demek (bos deger bu betikte
         zaten elenir, kaldirmanin tek yolu bu) -- yazmadan once gostermek gerek.
         Cumle vestra_preorder_note()'tan: urun sayfasinin cagirdigi AYNI
         fonksiyon, ikinci bir kopya yok. Not siparisi ENGELLEMIYOR (satin alma
         yolu bu alana bakmiyor); engelleyen tek sey sold_out ve o da UYARI
         olarak yaziliyor. */
      $new = (string)$v; $old = (string)($p['preorder_ship'] ?? '');
      if ($old === $new) continue;
      $hasNote = function_exists('vestra_preorder_note');
      $note    = $hasNote ? vestra_preorder_note(['preorder_ship' => $new]) : '';
      $line[] = "preorder_ship '".($old === '' ? '(yok)' : $old)."' -> '{$new}'";
      if (!$hasNote)         $line[] = "  UYARI: vestra_preorder_note() sunucuda YOK (kod eski) -- sayfa cumlesi gosterilemedi";
      elseif ($note !== '')  $line[] = "  sayfada: \"{$note}\"  (siparis KABUL EDILMEYE devam eder)";
      else                   $line[] = "  UYARI: tarih gecmis -- sayfa HIC on siparis notu basmaz";
      if (vestra_is_sold_out($p)) $line[] = "  UYARI: ilan SATILDI -- on siparis notu gorunur ama siparis ALINAMAZ";
      if (isset($p['status']) && $p['status'] !== 'approved')
        $line[] = "  UYARI: ilan durumu '{$p['status']}' -- katalogda GORUNMUYOR";
      $all[$i]['preorder_ship'] = $new;
      $changes++;
    } elseif ($k === 'sold_out') {
      /* GENEL DAL BU ALANI BOZAR ve bu dal tam onun icin var. En asagidaki
         else `(string)$v` yapiyor; PHP'de (string)false = "" demek, yani
         stoga GERI ALINAN bir ilan kayda `sold_out: ""` diye inerdi.
         Bugun kimse yanmiyor -- her okuyan vestra_is_sold_out()'tan geciyor
         ve o bos dizgeyi false okuyor -- ama alani `isset()`/
         `array_key_exists()` ile soracak bir okuyan, SATISTA olan urunu
         "satildi" sayar. Yani sessiz, gec patlayan bir tuzak.
         `group` dali bu depoda birebir ayni sebeple yazilmisti; orada
         bozulma TERS yone gidiyor ("false" -> dolu string -> !empty TRUE)
         ve kapatilmak istenen havuzu acik birakiyordu.
         $old kapinin IKINCI BIR KOPYASI DEGIL: alti satin alma yolunun
         cagirdigi AYNI fonksiyondan okunuyor. */
      $old = vestra_is_sold_out($p);
      if ($old === $v) continue;
      $line[] = 'sold_out '.($old?'true':'false').' -> '.($v?'true':'false')
              . ($v ? '  (satin alinamaz; vitrinde SOLD rozetiyle durur)' : '  (yeniden SATISTA)');
      $all[$i]['sold_out'] = $v;   // gercek bool, "1"/"" degil
      $changes++;
    } elseif ($k === 'stock') {
      /* Genel dal diziyi (string)'e cevirip "Array" yazardi. null alani
         kaldirir: liste turetilmis banda doner. Renk basina sekilde satir
         basina bir renk basilir. */
      $fmtFlat = fn(array $st) => implode(' · ', array_map(fn($a, $b) => $a.' '.$b, array_keys($st), $st)).' ('.array_sum($st).' ad.)';
      $fmtS = function ($st) use ($fmtFlat): string {
        if (!is_array($st) || !$st) return '(kayitli stok yok -- turetilmis bant)';
        if (is_array(reset($st))) {
          $bits = []; $tot = 0;
          foreach ($st as $cn => $mm) { $bits[] = $cn.': '.$fmtFlat((array)$mm); $tot += array_sum((array)$mm); }
          return "\n          ".implode("\n          ", $bits)."\n          toplam ".$tot.' ad.';
        }
        return $fmtFlat($st);
      };
      $old = is_array($p['stock'] ?? null) ? $p['stock'] : null;
      if ($old === $v) continue;
      $line[] = 'stock '.$fmtS($old).' -> '.$fmtS($v);
      if ($v === null) unset($all[$i]['stock']); else $all[$i]['stock'] = $v;
      $changes++;
    } elseif ($k === 'colorqty') {
      /* sold_out ile ayni sebep: genel dal (string)false = "" yazardi. */
      $old = !empty($p['colorqty']);
      if ($old === $v) continue;
      $line[] = 'colorqty '.($old?'true':'false').' -> '.($v?'true':'false')
              . ($v ? '  (renk basina adet secici, lot 1)' : '  (renk basina adet secici KAPALI)');
      if ($v) $all[$i]['colorqty'] = true; else unset($all[$i]['colorqty']);
      $changes++;
    } elseif ($k === 'redirect_to') {
      $old = trim((string)($p['redirect_to'] ?? ''));
      $new = $v === null ? '' : trim((string)$v);
      if ($old === $new) continue;
      $line[] = 'redirect_to '.($old === '' ? '(yok)' : $old).' -> '.($new === '' ? '(kaldirildi)' : $new)
              . ($new !== '' ? '  (urun sayfasi 301 ile oraya gider; bu kaydi status=rejected ile de kapatin)' : '');
      if ($new === '') unset($all[$i]['redirect_to']); else $all[$i]['redirect_to'] = $new;
      $changes++;
    } elseif ($k === 'dropship_off') {
      $old = !empty($p['dropship_off']);
      if ($old === (bool)$v) continue;
      $line[] = 'dropship_off '.($old?'true':'false').' -> '.($v?'true':'false')
              . ($v ? '  (bu ilan dropship listesinden CIKAR)' : '  (bu ilan dropship listesine DONER)');
      $all[$i]['dropship_off'] = (bool)$v;
      $changes++;
    } elseif ($k === 'dropship') {
      $old = $p['dropship'] ?? null;
      if ($old === $v) continue;
      $colours = implode(', ', array_keys($v['stock']));
      $line[] = "dropship price={$v['price']} ship_fr={$v['ship_fr']} ship_eu={$v['ship_eu']} stock[{$colours}]";
      $all[$i]['dropship'] = $v;
      $changes++;
    } elseif ($k === 'specs') {
      /* Merge rather than replace -- a demo product's existing specs
         (Composition, Fit, Care, ...) should survive a one-off addition
         like a dropshipping note, not get wiped by it. */
      $old = (array)($p['specs'] ?? []);
      $new = array_merge($old, $v);
      if ($old === $new) continue;
      $added = array_diff_key($v, $old) ?: $v;
      $line[] = "specs +".count($added).": ".implode(', ', array_keys($added));
      $all[$i]['specs'] = $new;
      $changes++;
    } elseif ($k === 'group') {
      /* Gercek boolean yaziliyor, "1"/"0" degil: kapatmak icin "false"
         yazildiginda genel dal bunu dolu bir string yapar ve
         !empty("false") TRUE dondurur -- havuz kapatilmak istenirken
         acik kalirdi. */
      $new = in_array(strtolower((string)$v), ['1','true','yes','evet'], true);
      $old = !empty($p['group']);
      if ($old === $new) continue;
      $line[] = "group ".($old?'acik':'kapali')." -> ".($new?'ACIK':'KAPALI');
      $all[$i]['group'] = $new;
      $changes++;
    } elseif ($k === 'group_target' || $k === 'group_balance_days' || $k === 'group_extend_days' || $k === 'group_min_qty' || $k === 'group_min_colors') {
      $new = (int)$v; $old = (int)($p[$k] ?? 0);
      if ($old === $new) continue;
      $line[] = "{$k} {$old} -> {$new}";
      $all[$i][$k] = $new;
      $changes++;
    } elseif ($k === 'group_price' || $k === 'group_deposit_pct') {
      $new = (float)$v; $old = (float)($p[$k] ?? 0);
      if (abs($old - $new) < 0.0001) continue;
      $line[] = "{$k} {$old} -> {$new}";
      $all[$i][$k] = $new;
      $changes++;
    } else {
      $new = (string)$v; $old = (string)($p[$k] ?? '');
      if ($old === $new) continue;
      $line[] = "{$k} '".($old === '' ? '(yok)' : $old)."' -> '{$new}'";
      $all[$i][$k] = $new;
      $changes++;
    }
  }
  /* SAYFADA: list / tiers / price degistiyse urun sayfasinin bu kayittan NE
     basacagi. Hesap sayfanin cagirdigi AYNI fonksiyonlardan (vestra_display_mode,
     vestra_from_price, vestra_discount) ve SON kayittan (bu satirin tiers ve list
     yazilari dahil) -- ikinci bir formul yazilmadi. Uc sey gorunur hale geliyor:
       - rozet kac: en dusuk kademeye gore, ilk kademeye degil;
       - mode='sale' degilse sayfa ustu cizili "was" fiyatini ve rozeti HIC
         basmaz (sale_list yalniz mode=sale'de gorunur): eskiden sessizce
         ise yaramaz bir yazma ve satir yine de "gorunen indirim" diyordu;
       - list en dusuk kademeden yuksek degilse "-%0" yerine sayfa sabit fiyat
         gosterir (vestra_on_sale).
     Satir yalniz bu satir bir sey DEGISTIRDIYSE basiliyor: aksi halde
     "(zaten istenen durumda)" yazisini ezer ve tekrar kosan bir dosya
     degisiklik varmis gibi gorunurdu. */
  if ($line && (isset($set['sale_list']) || isset($set['tiers']) || isset($set['price']))) {
    if (!function_exists('vestra_display_mode') || !function_exists('vestra_from_price') || !function_exists('vestra_discount')) {
      $line[] = "  UYARI: sayfa fonksiyonlari sunucuda YOK (kod eski) -- rozet gosterilemedi";
    } else {
      $fin  = $all[$i];
      $fm   = number_format((float)($fin['list'] ?? 0), 2);
      $fp   = number_format((float)vestra_from_price($fin, true), 2);
      if (($fin['mode'] ?? '') !== 'sale') {
        if (isset($set['sale_list']))
          $line[] = "  UYARI: mode='".($fin['mode'] ?? '')."' -- sayfa ustu cizili fiyati ve rozeti HIC basmaz (sale_list yalniz mode=sale'de gorunur)";
        else
          $line[] = "  SAYFADA: mode='".($fin['mode'] ?? '')."' -- sabit fiyat, rozet yok, from €{$fp}";
      } elseif (vestra_display_mode($fin) !== 'sale') {
        $line[] = "  UYARI: list €{$fm} en dusuk kademe €{$fp} ustunde degil -- indirim yok, sayfa sabit fiyat gosterir (\"-%0\" basmaz)";
      } else {
        $line[] = "  SAYFADA: ustu cizili €{$fm} · from €{$fp} · rozet -%".vestra_discount($fin)."  (mode=sale; sayfanin kendi fonksiyonlari)";
      }
    }
  }
  printf("  %-26s | %-14s | %s\n", $p['id'] ?? '?', $p['brand'] ?? '?', "match='{$m}'");
  echo $line ? "      ".implode("\n      ", $line)."\n" : "      (zaten istenen durumda)\n";
}

/* MARKA KAPSAMI (2 Eki 2026, Gallery Dept. / Casablanca). Operator MARKAYI
   soyluyor ("Casablanca urunleri"), dosya ise bir id LISTESI. Liste markanin
   hepsini kapsamiyorsa -- sonradan eklenen, baska bir partiden gelen, onay
   bekleyen ya da reddedilmis bir kayit -- eksik kalan ilan SESSIZCE eski
   durumunda kalir ve operator "tum marka" yazdigini sanir; parti dosyasi da
   canli kaydin aynasi degil (bu depoda defalarca kayitli). Sayim HAM listeden,
   her durumda: vestra_products() onay bekleyeni ve gizli markayi zaten gostermez.
   Eslesme marka adi TAM (buyuk/kucuk harf ve bosluk hosgorusu). Yalniz OKUR. */
$touchedBrands = [];
foreach ($plan as [$pi]) {
  $bn = trim((string)($all[$pi]['brand'] ?? ''));
  /* Gorunen ad ILK dokunulan ilanin yazimi: sonrakiler eziyorsa ayni marka
     kayitlardaki yazim farkina gore bir calistirmada "Gallery Dept.", digerinde
     "gallery dept." diye cikar. */
  if ($bn !== '' && !isset($touchedBrands[mb_strtolower($bn)])) $touchedBrands[mb_strtolower($bn)] = $bn;
}
foreach ($touchedBrands as $bl => $bn) {
  $tot = 0; $miss = [];
  foreach ($all as $j => $q) {
    if (mb_strtolower(trim((string)($q['brand'] ?? ''))) !== $bl) continue;
    $tot++;
    if (!isset($seen[$j])) $miss[] = ($q['id'] ?? '?').'['.($q['status'] ?? 'approved').']';
  }
  echo $miss
    ? "  MARKA KAPSAMI: {$bn} kayitta {$tot} ilan, bu dosya ".($tot - count($miss))." tanesine dokunuyor; DOKUNULMAYAN "
      .count($miss).": ".implode(', ', array_slice($miss, 0, 30)).(count($miss) > 30 ? ' ...' : '')."\n"
    : "  MARKA KAPSAMI: {$bn} -- markanin TUM {$tot} ilani bu dosyada\n";
}

echo "\ndegisecek alan: {$changes}\n";
if ($dry) { echo "\nDRY RUN — hicbir sey kaydedilmedi. Uygulamak icin dry_run=false.\n"; exit(0); }
if (!$changes) { echo "\nDegisiklik yok.\n"; exit(0); }

$dir = vestra_data_dir();
$bak = $dir.'/listings.json.bak-'.gmdate('Ymd-His');
if (!@copy($dir.'/listings.json', $bak)) { fwrite(STDERR,"HATA: yedek alinamadi\n"); exit(1); }
echo "yedek: {$bak}\n";
vestra_save_listings(array_values($all));

clearstatcache();
$check = vestra_listings();
if (count($check) !== count($all)) {
  fwrite(STDERR,"HATA: urun sayisi degisti! yedek: {$bak}\n"); exit(1);
}
echo "KAYDEDILDI — {$changes} alan guncellendi.\n";
