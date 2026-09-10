<?php
/* The host runs with display_errors off, so a fatal dies silently with exit
   255 and no output at all -- which is unreadable in CI. Force errors to
   stdout for this one-shot script. */
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
set_error_handler(function($no,$str,$file,$line){
  fwrite(STDERR, "PHP HATA: {$str} ({$file}:{$line})\n"); return false;
});
register_shutdown_function(function(){
  $e = error_get_last();
  if ($e && in_array($e['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR], true)) {
    fwrite(STDERR, "PHP OLUMCUL: {$e['message']} ({$e['file']}:{$e['line']})\n");
  }
});

$home = getenv("HOME");
$inc  = $home."/public_html/inc/products.php";
if (!is_readable($inc)) { fwrite(STDERR, "HATA: {$inc} okunamadi\n"); exit(1); }
require $inc;

foreach (['vestra_listings','vestra_save_listings','vestra_data_dir'] as $fn) {
  if (!function_exists($fn)) { fwrite(STDERR, "HATA: {$fn}() tanimli degil\n"); exit(1); }
}

/* Inputs arrive as one base64'd JSON blob -- see the encode step for why. */
$raw = (string)getenv("P_JSON");
if ($raw === '') { fwrite(STDERR, "HATA: P_JSON bos -- girdiler ulasmadi\n"); exit(1); }
$dec = base64_decode($raw, true);
if ($dec === false) { fwrite(STDERR, "HATA: P_JSON base64 cozulemedi\n"); exit(1); }
$in = json_decode($dec, true);
if (!is_array($in)) { fwrite(STDERR, "HATA: P_JSON gecerli JSON degil: ".substr($dec,0,120)."\n"); exit(1); }

$brand = trim((string)($in['brand'] ?? ''));
$cat   = trim((string)($in['cat']   ?? ''));
$nameq = trim((string)($in['nameq'] ?? ''));
$price = trim((string)($in['price'] ?? ''));
$listP = trim((string)($in['list_price'] ?? ''));
$discPct = trim((string)($in['discount_pct'] ?? ''));
$sect  = strtolower(trim((string)($in['section'] ?? '')));
$markPct = trim((string)($in['markup_pct'] ?? ''));
$force = strtolower(trim((string)($in['force'] ?? 'false'))) === 'true';
$moq   = trim((string)($in['moq']   ?? ''));
$step  = trim((string)($in['step']  ?? ''));
$sizes = trim((string)($in['sizes'] ?? ''));
$offIn = strtolower(trim((string)($in['offers'] ?? '')));
$dry   = strtolower(trim((string)($in['dry'] ?? 'true'))) !== 'false';

/* PARA VIRGULLE YAZILIYOR. Operator "79,90" yaziyor; PHP'nin is_numeric'i
   virgullu ondaligi kabul etmedigi icin asagidaki dogrulama bunu
   "gecersiz fiyat" diye REDDEDIYORDU. Sessiz para kaybi yoktu -- ham
   (float) 79.00 verirdi ama oraya hic varilmiyordu -- yine de kullaniciya
   kendi dogru yazdigi rakami "gecersiz" diye geri vermek bir arac hatasi.
   Depo zaten bunun cozumleyicisini tasiyor (vestra_price_input; CLAUDE.md:
   "fiyat okunan HER yerde bu kullanilmali").
   Yalnizca PARA alanlari: moq ve size_step ctype_digit ile dogrulaniyor,
   onlara iki ondalik eklemek gecerli bir MOQ'yu gecersiz yapardi.
   'keep' sayi degil ve oyle kaliyor -- cozumleyici 0 donduruyor, deger
   ellenmiyor, boylece asagidaki $listKeep dali calismaya devam ediyor. */
$money = function (string $v): string {
  if ($v === '') return '';
  $f = vestra_price_input($v);
  return $f > 0 ? number_format($f, 2, '.', '') : $v;
};
$price   = $money($price);
$listP   = $money($listP);
$discPct = $money($discPct);
$markPct = $money($markPct);

/* Bos = dokunma. Sadece acik "evet"/"hayir" kabul edilir: yazim hatasi
   olan bir deger sessizce "kapat" diye yorumlanip tum katalogdan teklif
   panelini kaldirmasin. */
$offers = null;
if ($offIn !== '') {
  if (in_array($offIn, ['yes','true','1','evet','ac','open'], true))      $offers = true;
  elseif (in_array($offIn, ['no','false','0','hayir','kapat'], true))     $offers = false;
  else { fwrite(STDERR, "HATA: allow_offers gecersiz '{$offIn}' -- yes / no / bos olmali\n"); exit(1); }
}

echo "girdiler: marka='{$brand}' kategori='{$cat}' bolme='{$sect}' fiyat='{$price}'\n";
echo "          indirim_yuzde='{$discPct}' ZAM_yuzde='{$markPct}'".($force ? " (force=EVET)" : "")." moq='{$moq}' step='{$step}'\n";
echo "          beden='{$sizes}'\n";
echo "          teklif_al=".($offers === null ? '(dokunma)' : ($offers ? 'AC' : 'KAPAT'))."\n";
echo "          dry_run=".($dry ? 'EVET (yazmaz)' : 'HAYIR (YAZAR)')."\n\n";

if ($brand === '') { fwrite(STDERR, "HATA: marka bos\n"); exit(1); }
if ($price === '' && $listP === '' && $discPct === '' && $markPct === '' && $moq === '' && $step === '' && $sizes === '' && $offers === null) {
  fwrite(STDERR, "HATA: price/list_price/discount_pct/markup_pct/moq/sizes/allow_offers hepsi bos -- yapacak is yok\n"); exit(1);
}
/* Bolme adi yazim hatasiyla gelirse HIC urun eslesmez ve is "0 urun"
   deyip basarili biter -- yazim hatasi ile "bu bolmede urun yok"
   ayni goruntu. Once gecerli mi diye sor. */
if ($sect !== '' && !isset(vestra_sections()[$sect])) {
  fwrite(STDERR, "HATA: gecersiz bolme '{$sect}' -- gecerli: ".implode(', ', array_keys(vestra_sections()))."\n"); exit(1);
}
/* ZAM tek basina calisir. 'price' mutlak rakam yazar, 'discount_pct'
   asagi ceker; hangisinin kazandigi belirsiz kalmasin. */
if ($markPct !== '' && ($price !== '' || $listP !== '' || $discPct !== '')) {
  fwrite(STDERR, "HATA: markup_pct; price/list_price/discount_pct ile birlikte kullanilamaz\n"); exit(1);
}
/* Tavan %100: "200" yazan bir parmak hatasi fiyati uce katlardi.
   Daha fazlasi gerekiyorsa iki kosu, ve ikisi de goz onunde. */
if ($markPct !== '' && (!is_numeric($markPct) || (float)$markPct <= 0 || (float)$markPct > 100)) {
  fwrite(STDERR, "HATA: gecersiz markup_pct '{$markPct}' -- 0 ile 100 arasinda olmali\n"); exit(1);
}
// Yuzde indirim her urunun KENDI mevcut fiyatindan hesaplanir -- 'price' (tum
// urunlere ayni mutlak rakam) ile ayni anda anlamsiz, hangisinin kazanacagi belirsiz.
if ($discPct !== '' && ($price !== '' || $listP !== '')) {
  fwrite(STDERR, "HATA: discount_pct, price/list_price ile birlikte kullanilamaz\n"); exit(1);
}
if ($discPct !== '' && (!is_numeric($discPct) || (float)$discPct <= 0 || (float)$discPct >= 100)) {
  fwrite(STDERR, "HATA: gecersiz discount_pct '{$discPct}' -- 0 ile 100 arasinda olmali\n"); exit(1);
}
/* 'keep' = her urunun mevcut fiyatini eski fiyat yap. "86'ya indir" derken
   dogru davranis bu: ustu cizili rakam uydurulmaz, urunun bugunku fiyati olur.
   Katalog genelinde tek bir eski fiyat zaten dogru olmazdi -- her urunun
   fiyati farkli. */
$listKeep = (strtolower($listP) === 'keep');
if ($listKeep) $listP = 'keep';
elseif ($listP !== '' && (!is_numeric($listP) || (float)$listP <= 0)) {
  fwrite(STDERR, "HATA: gecersiz list_price '{$listP}' -- sayi ya da 'keep' olmali\n"); exit(1);
}
/* Bir indirim ancak eski fiyat yenisinden yuksekse indirimdir. Esit ya da
   dusuk verilirse rozet "-0%" olur ya da eksiye duser -- sessizce yazmak
   yerine burada durduruyoruz. */
if (!$listKeep && $listP !== '' && $price !== '' && (float)$listP <= (float)$price) {
  fwrite(STDERR, "HATA: list_price ({$listP}) satis fiyatindan ({$price}) buyuk olmali\n"); exit(1);
}
if ($listP !== '' && $price === '') {
  fwrite(STDERR, "HATA: list_price verildi ama price bos -- indirimli fiyat da gerekli\n"); exit(1);
}
// Reject a malformed price outright rather than writing 0.00 over real pricing.
if ($price !== '' && (!is_numeric($price) || (float)$price <= 0)) {
  fwrite(STDERR, "HATA: gecersiz fiyat '{$price}'\n"); exit(1);
}
if ($moq !== '' && (!ctype_digit($moq) || (int)$moq < 1)) {
  fwrite(STDERR, "HATA: gecersiz MOQ '{$moq}'\n"); exit(1);
}
if ($step !== '' && (!ctype_digit($step) || (int)$step < 1)) {
  fwrite(STDERR, "HATA: gecersiz size_step '{$step}'\n"); exit(1);
}
/* Sepet once MOQ tabanina ceker, sonra adima YUKARI yuvarlar: adima
   bolunmeyen bir MOQ hicbir zaman satin alinabilir bir adet degildir
   (moq=20, step=8 -> 24). Iki girdi birlikte verildiyse tutarliligi
   burada zorla; tek tek verildiyse mevcut degerle carpisma dry-run
   ciktisinda gorunur. */
if ($moq !== '' && $step !== '' && ((int)$moq % (int)$step) !== 0) {
  fwrite(STDERR, "HATA: MOQ ({$moq}) size_step'e ({$step}) bolunmuyor\n"); exit(1);
}

$all = vestra_listings();
if (!count($all)) { fwrite(STDERR, "HATA: listings.json bos/okunamadi\n"); exit(1); }

$hits = 0; $changes = 0; $skipped = 0; $sampleSeen = 0;
foreach ($all as $i => $p) {
  $b = (string)($p['brand'] ?? '');
  $c = (string)($p['cat'] ?? '');
  // '*' targets every listing. Sweeping with a single letter instead
  // silently skips anything whose brand lacks it -- an earlier run matched
  // only 73 of 94 that way and looked complete.
  if ($brand !== '*' && stripos($b, $brand) === false) continue;
  if ($cat !== '' && $cat !== '*' && strcasecmp(trim($c), $cat) !== 0) continue;
  if ($nameq !== '' && stripos((string)($p['name'] ?? ''), $nameq) === false) continue;
  /* Bolmeyi kodun kendi okuyucusu soyler: alan bos ya da taninmayan bir
     deger tasiyorsa urun 'premium'dur (vestra_product_section). Burada
     ham $p['section'] okunsaydi alani hic girilmemis 265 urun hicbir
     bolmeye dusmezdi. */
  if ($sect !== '' && vestra_product_section($p) !== $sect) continue;
  $hits++;
  $id = (string)($p['id'] ?? '?');
  $line = [];

  if ($price !== '') {
    $new = (float)$price;
    /* Two shapes here. Without list_price the product is plain fixed-price and
       list tracks the tiers, as before. With list_price the two deliberately
       diverge: list is the struck-through "was", the tiers are what is charged,
       and vestra_discount() reads the gap between them to size the badge. */
    if ($listKeep) {
      /* The product's own current price becomes the "was". A product already
         at or below the new figure is not being discounted -- writing it would
         print a "-0%" badge or a negative one, so it is reported and left alone. */
      $wantList = (float)($p['list'] ?? 0);
      if ($wantList <= $new) {
        $line[] = "ATLANDI: mevcut fiyat ({$wantList}) yeni fiyattan ({$new}) yuksek degil";
        $skipped++;
        if ($line) echo "  ".str_pad($id, 28)." ".implode(' | ', $line)."\n";
        continue;
      }
    } else {
      $wantList = ($listP !== '') ? (float)$listP : $new;
    }
    $oldList  = $p['list'] ?? null;
    $touched  = false;

    if ((float)$oldList !== $wantList) {
      $line[] = "list ".($oldList === null ? '(yok)' : $oldList)." -> {$wantList}";
      $all[$i]['list'] = $wantList;
      $touched = true;
    }
    // tiers drive the actual order price; leaving them stale would show one
    // price and charge another. Rewrite every tier to match.
    if (!empty($p['tiers']) && is_array($p['tiers'])) {
      $n = 0;
      foreach ($p['tiers'] as $ti => $t) {
        // A tier that is not an array would fatal on ['price'] assignment.
        if (!is_array($t)) { $line[] = "UYARI: tier[{$ti}] dizi degil, atlandi"; continue; }
        if ((float)($t['price'] ?? 0) === $new) continue;
        $all[$i]['tiers'][$ti]['price'] = $new; $n++;
      }
      if ($n) { $line[] = "tiers({$n}) -> {$new}"; $touched = true; }
    }
    /* mode gates the badge: vestra_on_sale() ignores list unless mode is 'sale',
       so writing the prices without this shows the new figure at full price. A
       product already negotiated ('offer') keeps its mode -- turning that into a
       sale would remove the offer panel buyers are using. */
    $curMode = (string)($p['mode'] ?? 'fixed');
    if ($listP !== '' && $curMode !== 'offer' && $curMode !== 'sale') {
      $line[] = "mode {$curMode} -> sale";
      $all[$i]['mode'] = 'sale';
      $touched = true;
    } elseif ($listP === '' && $curMode === 'sale') {
      // price without a "was" means the discount is over.
      $line[] = "mode sale -> fixed";
      $all[$i]['mode'] = 'fixed';
      $touched = true;
    }
    if ($touched) {
      $pct = ($listP !== '' && $wantList > 0) ? (int)round(100*($wantList-$new)/$wantList) : 0;
      if ($pct > 0) $line[] = "indirim -%{$pct}";
      $changes++;
    }
  }
  if ($discPct !== '') {
    // 'list' KENDI mevcut fiyati okunur ve DEGISTIRILMEZ -- bu hem "was" (ustu
    // cizili) rakamdir hem de yuzdenin tabani. Boylece is IKI KEZ calistirilirsa
    // (yanlislikla ikinci bir dispatch) sonuc AYNI kalir, ust uste binmez: ikinci
    // kosuda da taban yine ayni 'list', yeniden %15 daha inmez.
    $was = (float)($p['list'] ?? 0);
    if ($was <= 0) {
      $line[] = "ATLANDI: gecerli fiyat yok, yuzde hesaplanamaz";
      $skipped++;
    } else {
      $new = round($was * (1 - (float)$discPct / 100), 2);
      // Bugun gercekten tahsil edilen EN DUSUK kademe -- 'list' zaten eski bir
      // indirimden kalma yuksek bir "was" ise (mode=sale, list > tier), hesap
      // ORADAN degil BURADAN yapilirsa alici bugun odedigi tutardan FAZLASINI
      // "indirim" diye gorur. Boyle bir urunu atla, sessizce pahalilastirma.
      $curLowest = $was;
      if (!empty($p['tiers']) && is_array($p['tiers'])) {
        foreach ($p['tiers'] as $t) {
          if (is_array($t) && (float)($t['price'] ?? 0) > 0) $curLowest = min($curLowest, (float)$t['price']);
        }
      }
      if ($new >= $curLowest - 0.001) {
        $line[] = "ATLANDI: hesaplanan indirim (€{$new}) bugun tahsil edilenden (€{$curLowest}) ucuz degil";
        $skipped++;
      } else {
        $n = 0;
        if (!empty($p['tiers']) && is_array($p['tiers'])) {
          foreach ($p['tiers'] as $ti => $t) {
            if (!is_array($t)) { $line[] = "UYARI: tier[{$ti}] dizi degil, atlandi"; continue; }
            if ((float)($t['price'] ?? 0) === $new) continue;
            $all[$i]['tiers'][$ti]['price'] = $new; $n++;
          }
        }
        $curMode = (string)($p['mode'] ?? 'fixed');
        // Indirim GORUNUR olmali (operator: "indirimi belirt") -- offer modundaki
        // urun zaten pazarlikla satiliyor, sale rozeti orada anlamsiz.
        if ($curMode !== 'offer' && $curMode !== 'sale') { $all[$i]['mode'] = 'sale'; $line[] = "mode {$curMode} -> sale"; }
        if ($n) { $line[] = "tiers({$n}) {$was} -> {$new}  (-%{$discPct}, was €{$was})"; $changes++; }
      }
    }
  }
  if ($markPct !== '') {
    /* ZAM: taban her urunun KENDI bugunku rakami. Tek bir mutlak fiyat
       yazmak (price) 4 EUR'luk kulotla 40 EUR'luk sabahligi ayni sayiya
       duserirdi; yuzde ikisini de kendi seviyesinde tutar.
       'list' de AYNI oranla yukseliyor: indirimli bir urunde ustu cizili
       rakam sabit kalsaydi %20 zam indirimi kendiliginden eritir, hatta
       list < fiyat olup rozet eksiye duserdi (vestra_discount). */
    $f = 1 + (float)$markPct / 100;
    /* AYNI ZAM IKINCI KEZ UYGULANMASIN. discount_pct tabani ('list')
       degistirmedigi icin tekrar calistirilabilirdi; zam tabani
       degistiriyor, yani ikinci kosu %20 degil %44 eder. Damga bunu
       goruyor: ayni yuzde + 24 saat = atla. */
    $stampPct = (float)($p['markup_pct'] ?? 0);
    $stampAt  = (int)($p['markup_at'] ?? 0);
    $fresh    = $stampAt > 0 && (time() - $stampAt) < 86400;
    if (!$force && $fresh && abs($stampPct - (float)$markPct) < 0.001) {
      $line[] = "ATLANDI: ayni zam (%{$markPct}) ".gmdate('d M H:i', $stampAt)." UTC'de uygulanmis -- force=true ile tekrarlanabilir";
      $skipped++;
    } else {
      $n = 0; $det = [];
      if (!empty($p['tiers']) && is_array($p['tiers'])) {
        foreach ($p['tiers'] as $ti => $t) {
          if (!is_array($t)) { $line[] = "UYARI: tier[{$ti}] dizi degil, atlandi"; continue; }
          $o = (float)($t['price'] ?? 0);
          if ($o <= 0) { $line[] = "UYARI: tier[{$ti}] fiyati yok, atlandi"; continue; }
          $nw = round($o * $f, 2);
          if (abs($nw - $o) < 0.001) continue;
          $all[$i]['tiers'][$ti]['price'] = $nw; $n++;
          $det[] = number_format($o,2,'.','')."->".number_format($nw,2,'.','');
        }
      }
      $oL = (float)($p['list'] ?? 0);
      $nL = 0.0;
      if ($oL > 0) {
        $nL = round($oL * $f, 2);
        if (abs($nL - $oL) >= 0.001) { $all[$i]['list'] = $nL; $n++; }
      }
      if (!$n) {
        /* Ne kademesi ne 'list' rakami olan bir ilan: yuzdenin tabani yok.
           Sessizce gecmek "zam yapildi" diye okunurdu. */
        $line[] = "ATLANDI: fiyat alani yok (kademe de 'list' de bos)";
        $skipped++;
      } else {
        $line[] = "ZAM %{$markPct}: ".($oL > 0 ? "list ".number_format($oL,2,'.','')."->".number_format($nL,2,'.','') : "list (yok)")
                . ($det ? "  kademe ".implode(', ', $det) : "  (kademe yok)");
        /* Numune fiyati ayri bir rakam (tek parca), zam kapsaminda
           degil -- ama sessizce birakilmasin, operator gorsun. */
        if ((float)($p['sample_price'] ?? 0) > 0) {
          $line[] = "  not: numune fiyati €".number_format((float)$p['sample_price'],2,'.','')." DOKUNULMADI";
          $sampleSeen++;
        }
        /* mode'a DOKUNULMUYOR: zam indirim degil. sale bir urun sale
           kalir (iki rakam da ayni oranda yukseldi, yuzde ayni),
           offer bir urun offer kalir. */
        /* NBB ithalatinda her kayitta 'eur_margin_pct' damgasi var (maliyet
           x 1.5 -> 50). Zamdan sonra fiyat artik o orani tasimiyor; damgayi
           oldugu gibi birakmak kaydin KENDI KENDINI yalanlamasi olurdu
           (KURAL 5j'nin belge hali) ve bir sonraki ithalat 'price|50' ile
           fiyati sessizce geri indirirdi. Yeni oran BILESIK:
           (1 + eski/100) x (1 + zam/100) - 1. */
        if ((float)($p['eur_margin_pct'] ?? 0) > 0) {
          $oM = (float)$p['eur_margin_pct'];
          $nM = round(((1 + $oM/100) * $f - 1) * 100, 2);
          $all[$i]['eur_margin_pct'] = $nM;
          $line[] = "  kar orani %{$oM} -> %{$nM} (bir sonraki ithalat bu oranla kosulmali)";
        }
        $all[$i]['markup_pct'] = (float)$markPct;
        $all[$i]['markup_at']  = time();
        $changes += $n;
      }
    }
  }
  if ($moq !== '') {
    $new = (int)$moq; $old = $p['moq'] ?? null;
    if ((int)$old !== $new) {
      $line[] = "moq ".($old === null ? '(yok)' : $old)." -> {$new}";
      $all[$i]['moq'] = $new;
      // First tier gates the minimum. Guard the shape: tiers may be keyed
      // other than 0, or hold non-arrays, either of which would fatal.
      if (!empty($all[$i]['tiers']) && is_array($all[$i]['tiers'])) {
        $k = array_key_first($all[$i]['tiers']);
        if (is_array($all[$i]['tiers'][$k] ?? null)) $all[$i]['tiers'][$k]['min'] = $new;
      }
      $changes++;
    }
  }
  if ($step !== '') {
    $newS = (int)$step; $oldS = $p['size_step'] ?? null;
    if ((int)$oldS !== $newS) {
      $line[] = "size_step ".($oldS === null ? '(yok)' : $oldS)." -> {$newS}";
      $all[$i]['size_step'] = $newS;
      $changes++;
    }
  }
  if ($sizes !== '') {
    $old = (string)($p['sizes'] ?? '');
    if ($old !== $sizes) {
      $line[] = "sizes '".($old === '' ? '(yok)' : $old)."' -> '{$sizes}'";
      $all[$i]['sizes'] = $sizes;
      $changes++;
    }
  }
  if ($offers !== null) {
    /* mode=offer urunlerde pazarlik zaten TEK yol; bayrak orada anlamsiz
       ve seller formu da (seller.php) ayni sarti koyuyor. */
    if (($p['mode'] ?? '') === 'offer') {
      if (!empty($p['offers'])) { unset($all[$i]['offers']); $line[] = "offers -> (mode=offer, bayrak kaldirildi)"; $changes++; }
    } else {
      $old = !empty($p['offers']);
      if ($old !== $offers) {
        if ($offers) $all[$i]['offers'] = true; else unset($all[$i]['offers']);
        $line[] = "offers ".($old ? 'acik' : 'kapali')." -> ".($offers ? 'ACIK' : 'kapali');
        $changes++;
      }
    }
  }

  if ($line) echo "  {$id} | {$b} | {$c}\n      ".implode("\n      ", $line)."\n";
}

echo "\nfiltreye uyan urun: {$hits}".($skipped ? "  (indirime uygun olmayan, atlanan: {$skipped})" : "")."\n";
echo "degisecek alan: {$changes}\n";
if ($sampleSeen) echo "numune fiyatli urun (dokunulmadi): {$sampleSeen}\n";
if ($markPct !== '') {
  echo "\nDIKKAT: zam TABANI bugunku fiyattir -- bu is iki kez calistirilirsa\n";
  echo "        %{$markPct} iki kez biner. Ayni yuzde icin 24 saatlik damga korumasi var\n";
  echo "        (force=true ile asilir).\n";
}

if (!$hits) {
  fwrite(STDERR, "\nUYARI: hicbir urun eslesmedi -- marka/kategori yazimini kontrol edin.\n");
  fwrite(STDERR, "Mevcut kategoriler:\n");
  $cats = [];
  foreach (vestra_listings() as $p) {
    if ($brand === '*' || stripos((string)($p['brand'] ?? ''), $brand) !== false) {
      $cats[(string)($p['cat'] ?? '?')] = true;
    }
  }
  foreach (array_keys($cats) as $k) fwrite(STDERR, "  - {$k}\n");
  exit(1);
}

if ($dry) { echo "\nDRY RUN -- hicbir sey kaydedilmedi. Uygulamak icin dry_run=false.\n"; exit(0); }
if (!$changes) { echo "\nDegisiklik yok -- kaydetmeye gerek kalmadi.\n"; exit(0); }

// Timestamped backup before any write, so a wrong filter is recoverable.
$dir = vestra_data_dir();
$src = $dir.'/listings.json';
$bak = $dir.'/listings.json.bak-'.gmdate('Ymd-His');
if (!@copy($src, $bak)) { fwrite(STDERR, "HATA: yedek alinamadi, kaydetmiyorum\n"); exit(1); }
echo "yedek: {$bak}\n";

vestra_save_listings($all);

// Read back and confirm the write actually landed.
clearstatcache();
$check = vestra_listings();
if (count($check) !== count($all)) {
  fwrite(STDERR, "HATA: kayittan sonra urun sayisi degisti! yedek: {$bak}\n"); exit(1);
}
echo "KAYDEDILDI -- {$changes} alan guncellendi, {$hits} urun tarandi".($skipped ? ", {$skipped} atlandi" : "").".\n";
