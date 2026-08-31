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
foreach (['vestra_listings','vestra_save_listings','vestra_data_dir'] as $fn)
  if (!function_exists($fn)) { fwrite(STDERR,"HATA: {$fn}() yok\n"); exit(1); }

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

$ALLOWED = ['cat','price','moq','sizes','name','status','desc','sample_price','sample_platform_pay','seller_uid','colors','images','size_step','min_colors','pinned','specs','specs_remove','dropship','dropship_off','sale_list','ships_from',
            'group','group_target','group_price','group_deposit_pct','group_balance_days','group_extend_days','group_started','group_deadline','group_min_qty','group_models','group_title','group_min_colors'];
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
  if (isset($set['sample_price']) && (!is_numeric($set['sample_price']) || (float)$set['sample_price'] <= 0)) {
    $errors[] = "{$ctx} ({$m}): gecersiz sample_price '{$set['sample_price']}'"; continue;
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
  if (isset($set['status']) && !in_array($set['status'], ['approved','pending'], true)) {
    $errors[] = "{$ctx} ({$m}): status 'approved' ya da 'pending' olmali"; continue;
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
  /* Once TAM eslesme (id ya da sku). Alt dize aramasi kisa kodlarda fazla
     urune uyabiliyor; tam eslesme varsa o kazanir. */
  $idx = [];
  foreach ($all as $i => $p) {
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
      $line[] = "sample_price {$old} -> {$new}";
      $all[$i]['sample_price'] = $new;
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
      $base = !empty($p['tiers'][0]['price']) ? (float)$p['tiers'][0]['price'] : $old;
      $disc = $new > 0 ? (int)round(100*($new-$base)/$new) : 0;
      $line[] = "sale_list(SADECE was) {$old} -> {$new}  (tiers[0] {$base} degismedi, gorunen indirim ~%{$disc})";
      $all[$i]['list'] = $new;
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
  printf("  %-26s | %-14s | %s\n", $p['id'] ?? '?', $p['brand'] ?? '?', "match='{$m}'");
  echo $line ? "      ".implode("\n      ", $line)."\n" : "      (zaten istenen durumda)\n";
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
