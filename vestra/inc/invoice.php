<?php
/**
 * VESTRA — automatic PDF invoices for orders and accepted offers.
 *
 * One invoice per SELLER per sale: a cart that spans several sellers produces
 * one PDF per seller, each carrying only that seller's line items and that
 * seller's own bank details — a company can only invoice for what it sold.
 * Lines with no real seller (demo/catalog items with no seller_uid) are
 * grouped under a "VESTRA" platform-issued invoice with no bank details.
 *
 * Every invoice is generated ONCE, the first time the underlying sale is
 * confirmed (order placed / offer accepted), and the PDF bytes are persisted
 * immutably to data/invoices/ (web-blocked, same pattern as data/docs). A
 * seller editing their bank details afterwards never rewrites an
 * already-issued invoice — vestra_ensure_invoice() is idempotent and simply
 * hands back the existing file + invoice number on every later call.
 */
require_once __DIR__.'/pdf.php';

/**
 * Platform'un kendi satici kimligi (Acerasoft LLC) + banka hesabi.
 *
 * Kurasyonlu katalog urunlerinde seller_uid YOK, dolayisiyla fatura kesilirken
 * $sellerAcc null geliyor ve belge banka bilgisi OLMADAN cikiyordu: alici parayi
 * nereye gonderecegini faturadan ogrenemiyordu.
 *
 * Kimlik burada sabit (index.php ve faq.php'deki tescilli bilgilerle ayni), BANKA
 * bilgileri ise data/platform_seller.json'dan geliyor -- admin panelinden girilir,
 * web'e kapali, .gitignore'da, 0600. Rakamlar bilerek kodda DEGIL: bu depo herkese
 * acik ve ABD'de routing+hesap ikilisi ACH borclandirma icin yeterli.
 *
 * Dosya yoksa yalnizca kimlik doner: fatura yine kesilir, sadece odeme kutusu bos
 * kalir. Eksik bir odeme kutusu, kesilemeyen bir faturadan iyidir.
 */
function vestra_platform_seller(): array {
    $base = [
        'company' => 'Acerasoft LLC',
        'address' => '8 The Green, Suite B, Dover, Delaware 19901',
        'country' => 'US',
        'website' => 'vestrasales.com',
        /* EIN kodda durabilir (banka rakamlarinin aksine): EIN bir odeme bilgisi
           degil, kimlik bilgisidir -- her faturada, W-9'da ve resmi yazismada
           zaten aciga cikar, onunla hesap borclandirilamaz. Operator acikca
           yazilmasini istedi (22.08.2026). Faturada "EIN: 61-2070643" olarak
           basilir; gumruk ve alicinin muhasebesi saticiyi bununla dogrular. */
        'vat_id'  => '61-2070643',
    ];
    $f = vestra_data_dir().'/platform_seller.json';
    if (is_readable($f)) {
        $j = json_decode((string)file_get_contents($f), true);
        if (is_array($j)) foreach ($j as $k => $v) { if ($v !== '' && $v !== null) $base[$k] = $v; }
    }
    return $base;
}

/**
 * A tax identifier as its own country writes it.
 *
 * A US EIN is nine digits and is written NN-NNNNNNN everywhere it is read by a human —
 * on a W-9, in a customs entry, in a broker's file. The registration form stores whatever
 * the buyer typed, and a buyer who types nine bare digits had them printed that way:
 * "EIN: 568637722". Nothing is wrong with the number, but the reader has to count digits
 * to satisfy themselves it is an EIN at all, and a customs broker checking a shipment
 * should not have to.
 *
 * Only the shape changes, never the value: nine digits in, the same nine digits out with
 * a hyphen. Anything that is not exactly nine digits is returned untouched — a buyer who
 * already typed the hyphen keeps it, and a number that does not look like an EIN is not
 * quietly reformatted into one. Non-US identifiers are left alone; VAT numbers carry
 * their own country prefix and grouping conventions, and inventing a US shape for them
 * would be worse than printing them as given.
 */
function vestra_format_tax_id(string $id, string $country): string {
    $id = trim($id);
    $c  = strtoupper(trim($country));
    if ($c !== 'US' && $c !== 'USA' && $c !== 'UNITED STATES') return $id;
    return preg_match('/^\d{9}$/', $id) ? substr($id, 0, 2).'-'.substr($id, 2) : $id;
}

/**
 * The payment rails that belong on THIS invoice, chosen by its currency.
 *
 * A US domestic account and a European one are not interchangeable, and printing both
 * is not a kindness — it is an invitation to use the wrong one. An ABA routing number
 * is a US domestic instruction: a European buyer paying in euro cannot use it, and a
 * bank that accepts the attempt converts at its own rate or bounces the transfer days
 * later. The reverse holds too: an IBAN on a dollar invoice sends a US payer down an
 * international route that costs them a fee they did not agree to.
 *
 * So the currency of the document decides. USD prints the US rails (account + ABA),
 * anything else prints IBAN/BIC. When the matching pair is not configured the function
 * returns nothing at all, and the caller drops the whole payment box: a buyer who sees
 * no account asks for one, while a buyer who sees the wrong one wires money into a
 * route that cannot receive it.
 *
 * @return string[] Ready-to-print lines, empty when this currency has no account.
 */
function vestra_payment_rails(array $acc, string $currency): array {
    $g = fn(string $k) => trim((string)($acc[$k] ?? ''));
    if (strtoupper(trim($currency)) === 'USD') {
        if ($g('bank_account') === '' || $g('bank_routing') === '') return [];
        return [
            'Account number: '.$g('bank_account').($g('bank_acct_type') !== '' ? '  ('.$g('bank_acct_type').')' : ''),
            'Routing number (ABA): '.$g('bank_routing'),
        ];
    }
    if ($g('bank_iban') === '') return [];
    /* BIC'i secerken dikkat: yapilandirma DUZ ve tek bir 'bank_bic' tutuyor. ABD
       hesabi da tanimliysa o BIC ABD bankasinin olabilir, ve onu bir IBAN'in
       yanina basmak alicinin bankasina birbirini tutmayan bir cift vermek demek.
       Bu yuzden ABD hesabi varken BIC ancak ACIKCA 'bank_eur_bic' verilmisse
       yaziliyor; yoksa hic yazilmiyor. Kayip degil: SEPA icinde IBAN tek basina
       yeterli, BIC opsiyoneldir -- eksik bir alan, celisen bir ciftten iyidir. */
    $hasUs = $g('bank_account') !== '' || $g('bank_routing') !== '';
    $bic   = $g('bank_eur_bic') !== '' ? $g('bank_eur_bic') : ($hasUs ? '' : $g('bank_bic'));
    return array_values(array_filter([
        'IBAN: '.vestra_iban_pretty($g('bank_iban')),
        $bic !== '' ? 'BIC / SWIFT: '.$bic : '',
    ], fn($v) => $v !== ''));
}

/**
 * IBAN'i saklanacak bicime getirir: bosluk/tire atilir, buyuk harfe cekilir.
 *
 * Banka ekstresi "FR76 3000 4008 2800 0123 4567 890" diye yazar, havale formu
 * bosluksuz ister. Ikisini de kabul edip TEK bicimde saklamak, ayni hesabin
 * iki farkli metin olarak kaydedilmesini onluyor.
 */
function vestra_iban_normalize(string $v): string {
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $v));
}

/**
 * IBAN mod-97 (ISO 13616) dogrulamasi + ulke uzunlugu.
 *
 * Neden: IBAN faturaya basiliyor ve alici parayi ORAYA gonderiyor. Yanlis
 * yazilmis bir hane ya havaleyi geri cevirtir (iyi ihtimal) ya da baska bir
 * hesaba dusurur. Kontrol ucuz, hatasi pahali.
 *
 * Uzunluk tablosu kopyala-yapistir sirasinda KIRPILMIS bir numarayi yakalar;
 * mod-97 ise hane degisimi/yer degistirmesini yakalar. Tabloda olmayan ulke
 * kodunda yalnizca mod-97 uygulanir -- bilinmeyen bir ulkeyi reddetmek,
 * gecerli bir hesabi girilemez yapardi.
 */
function vestra_iban_valid(string $v): bool {
    $s = vestra_iban_normalize($v);
    if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{8,30}$/', $s)) return false;
    static $len = [
        'AD'=>24,'AT'=>20,'BE'=>16,'BG'=>22,'CH'=>21,'CY'=>28,'CZ'=>24,'DE'=>22,
        'DK'=>18,'EE'=>20,'ES'=>24,'FI'=>18,'FR'=>27,'GB'=>22,'GI'=>23,'GR'=>27,
        'HR'=>21,'HU'=>28,'IE'=>22,'IS'=>26,'IT'=>27,'LI'=>21,'LT'=>20,'LU'=>20,
        'LV'=>21,'MC'=>27,'MT'=>31,'NL'=>18,'NO'=>15,'PL'=>28,'PT'=>25,'RO'=>24,
        'RS'=>22,'SE'=>24,'SI'=>19,'SK'=>24,'SM'=>27,'TR'=>26,'UA'=>29,'AE'=>23,
    ];
    $cc = substr($s, 0, 2);
    if (isset($len[$cc]) && strlen($s) !== $len[$cc]) return false;
    /* Ilk dort hane sona tasinir, harfler A=10..Z=35 ile rakama cevrilir,
       kalan 97'ye bolumunden 1 cikmali. Sayi 64 bit'e sigmadigi icin hane
       hane yurutuluyor. */
    $r = substr($s, 4).substr($s, 0, 4);
    $rem = 0;
    for ($i = 0, $n = strlen($r); $i < $n; $i++) {
        $c   = $r[$i];
        $d   = ctype_digit($c) ? $c : (string)(ord($c) - 55);
        for ($j = 0, $m = strlen($d); $j < $m; $j++) $rem = ($rem * 10 + (int)$d[$j]) % 97;
    }
    return $rem === 1;
}

/**
 * IBAN'in BASKI bicimi: 4'lu gruplar (ISO 13616'nin kagit gosterimi) —
 * "FR47 2004 1010 1257 4096 4U03 334". Operator istegi (1 Eyl 2026):
 * faturada bu bicimde dursun; 27 bitisik hane insan gozuyle dogrulanamiyor,
 * alici havale formuna gecirirken bu gruplarla karsilastiriyor.
 * SAKLAMA bicimi degismez (normalize, bosluksuz) — bu yalnizca cikti.
 * Once normalize: eski kayitlarda bosluklu deger kalmis olabilir, dogrudan
 * chunk_split cift bosluk uretirdi.
 */
function vestra_iban_pretty(string $v): string {
    return trim(chunk_split(vestra_iban_normalize($v), 4, ' '));
}

function vestra_invoice_dir(): string {
    $dir = dirname(__DIR__).'/data/invoices';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $htaccess = $dir.'/.htaccess';
    if (!is_file($htaccess)) @file_put_contents($htaccess,
        "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order deny,allow\n  Deny from all\n</IfModule>\n");
    return $dir;
}

/**
 * A country as it belongs on an invoice: the full name.
 *
 * The registration form capped the field at three characters for an ISO code, so buyers who
 * typed their country name had it truncated by the browser — "Norway" reached an invoice as
 * "Nor". The field is wider now, but the accounts created before that still hold the stump,
 * and a customs entry cannot be filed against "Nor".
 *
 * ISO-2 codes resolve directly; anything shorter than a name resolves by prefix, but only
 * when exactly one country matches — "Ind" stays "Ind" rather than becoming a guess between
 * India and Indonesia. Anything already spelled out is returned untouched.
 */
function vestra_country_name(string $v): string {
    $v = trim($v);
    if ($v === '') return '';
    static $iso = [
        'AT'=>'Austria','BE'=>'Belgium','BG'=>'Bulgaria','CH'=>'Switzerland','CY'=>'Cyprus',
        'CZ'=>'Czechia','DE'=>'Germany','DK'=>'Denmark','EE'=>'Estonia','ES'=>'Spain',
        'FI'=>'Finland','FR'=>'France','GB'=>'United Kingdom','UK'=>'United Kingdom',
        'GR'=>'Greece','HR'=>'Croatia','HU'=>'Hungary','IE'=>'Ireland','IS'=>'Iceland',
        'IT'=>'Italy','LI'=>'Liechtenstein','LT'=>'Lithuania','LU'=>'Luxembourg','LV'=>'Latvia',
        'MC'=>'Monaco','MT'=>'Malta','NL'=>'Netherlands','NO'=>'Norway','PL'=>'Poland',
        'PT'=>'Portugal','RO'=>'Romania','RS'=>'Serbia','SE'=>'Sweden','SI'=>'Slovenia',
        'SK'=>'Slovakia','TR'=>'Türkiye','UA'=>'Ukraine',
        'AE'=>'United Arab Emirates','AZ'=>'Azerbaijan','CA'=>'Canada','JP'=>'Japan',
        'KR'=>'South Korea','US'=>'United States','QA'=>'Qatar','SA'=>'Saudi Arabia',
        /* Beyond the markets we sell to: these are here so a truncated stump has something
           to collide with. "Mal" resolved to Malta while Malaysia and Mali were missing from
           the list — a unique match against an incomplete list is a wrong country on a customs
           document, which is worse than leaving the stump alone. */
        'AL'=>'Albania','AM'=>'Armenia','AR'=>'Argentina','AU'=>'Australia','BA'=>'Bosnia and Herzegovina',
        'BH'=>'Bahrain','BR'=>'Brazil','BY'=>'Belarus','CL'=>'Chile','CN'=>'China','EG'=>'Egypt',
        'GE'=>'Georgia','ID'=>'Indonesia','IL'=>'Israel','IN'=>'India','KW'=>'Kuwait','KZ'=>'Kazakhstan',
        'MA'=>'Morocco','MD'=>'Moldova','ME'=>'Montenegro','MK'=>'North Macedonia','ML'=>'Mali',
        'MX'=>'Mexico','MY'=>'Malaysia','NZ'=>'New Zealand','RU'=>'Russia','SG'=>'Singapore',
        'TH'=>'Thailand','TW'=>'Taiwan','ZA'=>'South Africa',
    ];
    $up = mb_strtoupper($v);
    if (isset($iso[$up])) return $iso[$up];
    if (mb_strlen($v) <= 4) {
        $hit = '';
        foreach ($iso as $name) {
            if (stripos($name, $v) === 0) { if ($hit !== '' && $hit !== $name) return $v; $hit = $name; }
        }
        if ($hit !== '') return $hit;
    }
    return $v;
}

/**
 * The "Bill To" block for an order, from every place the buyer's details actually live.
 *
 * orders.csv carries what the order form collected, which for an account-holder is thin: the
 * delivery address is only present when the buyer filled the optional "deliver to" box, and
 * it lands in the free-text notes rather than a column. Where the order is silent the buyer's
 * registered account answers — that is the address they gave us, and an invoice without one
 * is no use to UPS, to the export declaration, or to the buyer's own finance department.
 *
 * Shared by the real issuing path and the preview so the document that gets approved is
 * byte-for-byte the document that gets issued.
 */
function vestra_invoice_buyer(array $orderRow): array {
    $address = '';
    if (preg_match('/Deliver to: (.*?)(?:\.\s|$)/u', (string)($orderRow['notes'] ?? ''), $m)) $address = trim($m[1]);

    $acc   = null;
    $email = strtolower(trim((string)($orderRow['email'] ?? '')));
    if ($email !== '' && function_exists('auth_accounts')) {
        foreach (auth_accounts() as $a) {
            if (strtolower(trim((string)($a['email'] ?? ''))) === $email) { $acc = $a; break; }
        }
    }
    $pick = fn(string $orderKey, string $accKey) =>
        trim((string)($orderRow[$orderKey] ?? '')) ?: trim((string)($acc[$accKey] ?? ''));

    /* Country is the one field where the order is not the better source. It was copied from
       whatever the buyer's profile held at the time, which for accounts created under the old
       three-character limit is a stump — the order carries "Nor" forever even after the
       account is corrected to "Norway". So: take the account's value when the order's still
       looks truncated and the account's does not. Everything else prefers the order, which is
       the record of what was actually agreed. */
    $ctryOrder = vestra_country_name(trim((string)($orderRow['country'] ?? '')));
    $ctryAcc   = vestra_country_name(trim((string)($acc['country'] ?? '')));
    $country   = ($ctryOrder === '' || (mb_strlen($ctryOrder) <= 4 && mb_strlen($ctryAcc) > 4))
        ? ($ctryAcc ?: $ctryOrder) : $ctryOrder;

    return [
        'company' => $pick('company', 'company'),
        'vat'     => $pick('vat', 'vat_id'),
        'reg'     => $pick('reg_number', 'reg_number'),
        'name'    => $pick('name', 'name'),
        'email'   => $orderRow['email'] ?? '',
        'country' => $country,
        'address' => $address !== '' ? $address : trim((string)($acc['address'] ?? '')),
    ];
}

/** Filesystem-safe issuer key: the seller's account id, or 'vestra' for sellerless lines. */
function vestra_invoice_seller_key(?array $sellerAcc): string {
    return ($sellerAcc['id'] ?? '') !== '' ? $sellerAcc['id'] : 'vestra';
}
function vestra_invoice_slug(string $ref, string $sellerKey): string {
    return preg_replace('/[^A-Za-z0-9_-]/', '', $ref.'__'.$sellerKey);
}
function vestra_invoice_file(string $ref, string $sellerKey): string {
    return vestra_invoice_dir().'/'.vestra_invoice_slug($ref, $sellerKey).'.pdf';
}
function vestra_invoice_meta_file(string $ref, string $sellerKey): string {
    return vestra_invoice_dir().'/'.vestra_invoice_slug($ref, $sellerKey).'.json';
}

/** Sequential per-issuer invoice numbers: INV-2026-000001. File-locked — safe under concurrent orders. */
function vestra_next_invoice_no(string $sellerKey): string {
    $f = dirname(__DIR__).'/data/invoice_seq.json';
    $fh = @fopen($f, 'c+');
    if (!$fh) return 'INV-'.date('Y').'-'.substr(bin2hex(random_bytes(3)), 0, 6); // pathological fallback, still unique
    flock($fh, LOCK_EX);
    $seq = json_decode((string)stream_get_contents($fh), true);
    if (!is_array($seq)) $seq = [];
    $year = date('Y');
    $k = $sellerKey.'-'.$year;
    $seq[$k] = (int)($seq[$k] ?? 0) + 1;
    $n = $seq[$k];
    ftruncate($fh, 0); rewind($fh);
    fwrite($fh, json_encode($seq, JSON_PRETTY_PRINT));
    flock($fh, LOCK_UN); fclose($fh);
    /* 4 hane, operatorun istegiyle: alti sifirli hali telefonda okunmuyor ve
       kimse yilda milyon fatura kesmiyor. sprintf %04d KIRPMAZ, genisletir --
       tasma yok.

       Gorunen numara = sayac + 1000. "INV-2026-0009" alicinin bunun dokuzuncu
       satis oldugunu soyler; fatura numarasindan hacim okumak alicilarin ve
       rakiplerin bilinen aliskanligi. Numaralandirmayi 1000'den baslatmak
       yaygin ve mesru pratik: sira yine TEKIL ve ARTAN (denetimde onemli olan
       bu), sadece baslangic noktasi kaydirilmis. Sayac dosyada HAM tutuluyor,
       kaydirma yalnizca basimda -- dosyayi elle okuyan biri gercek adedi yine
       gorur, belge okuyan gormez. */
    return sprintf('INV-%s-%04d', $year, $n + 1000);
}

/**
 * Word-wrap using VestraPdf's width estimate (kept here so callers don't need a PDF instance).
 *
 * Breaks inside a word when the word itself is wider than the column. Splitting on spaces
 * alone leaves a model code like WH1JQ040B139MAI whole, and a whole code wider than the SKU
 * column does not wrap — it just keeps drawing, straight over the description beside it.
 */
function vestra_invoice_wrap(string $s, float $maxW, float $size, bool $bold = false): array {
    $wide = fn(string $t): float => mb_strlen($t) * $size * ($bold ? 0.60 : 0.52);
    $chop = function (string $w) use ($wide, $maxW): array {
        $out = []; $cur = '';
        foreach (preg_split('//u', $w, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
            if ($cur !== '' && $wide($cur.$ch) > $maxW) { $out[] = $cur; $cur = ''; }
            $cur .= $ch;
        }
        if ($cur !== '') $out[] = $cur;
        return $out;
    };

    $lines = []; $cur = '';
    foreach (preg_split('/\s+/', trim($s)) as $w) {
        if ($w === '') continue;
        if ($maxW > 0 && $wide($w) > $maxW) {
            /* Unbreakable and too wide: close the current line, then let the pieces stand on
               their own so nothing is space-joined back into an over-wide line. */
            if ($cur !== '') { $lines[] = $cur; $cur = ''; }
            $pieces = $chop($w);
            $cur = (string)array_pop($pieces);
            foreach ($pieces as $p) $lines[] = $p;
            continue;
        }
        $try = $cur === '' ? $w : $cur.' '.$w;
        if ($wide($try) > $maxW && $cur !== '') { $lines[] = $cur; $cur = $w; }
        else $cur = $try;
    }
    if ($cur !== '') $lines[] = $cur;
    return $lines ?: [''];
}

/**
 * Render one seller's invoice PDF.
 * $order: ['ref'=>string,'date'=>ISO8601,'buyer'=>['company','vat','name','email','country','address']]
 * $items: list of ['sku','brand','name','colors'=>string[],'qty'=>int,'unit'=>float,'line'=>float]
 */
/**
 * Faturanin ustunde ve satici kutusunda gorunecek unvan.
 *
 * NEDEN AYRI BIR ALAN: 'company' halka acik -- showroom.php saticinin vitrin
 * adi olarak onu basiyor, Stripe etiketi de ondan turuyor. Faturayi baska bir
 * ticari unvanla kesmek isteyen bir satici icin 'company'yi degistirmek,
 * magazanin adini da degistirmek demek; kimsenin istemedigi bir yan etki.
 * 'invoice_name' YALNIZCA belgede gecerli.
 *
 * Banka tarafi buraya KARISMIYOR: IBAN'in yanindaki isim 'bank_holder' ve o
 * zaten ayri bir alan -- havale formundaki alici adi ile faturanin ustundeki
 * unvan farkli olabilir (ve sahis hesabinda genelde farklidir).
 *
 * Bos ise davranis degismiyor: company -> name -> 'Seller'.
 */
function vestra_invoice_issuer_name(?array $acc, string $fallback = 'Seller'): string {
    $inv = trim((string)($acc['invoice_name'] ?? ''));
    if ($inv !== '') return $inv;
    $co  = trim((string)($acc['company'] ?? ''));
    if ($co !== '') return $co;
    $nm  = trim((string)($acc['name'] ?? ''));
    /* $fallback CAGIRANDAN: belgenin USTUNDEKI satir sellerAcc null iken
       'Acerasoft LLC'ye dusuyordu (platform faturasi), satici kutusu ise
       'Seller'a. Ikisini tek bir sabite baglamak platform faturasinin
       basligini sessizce degistirirdi. */
    return $nm !== '' ? $nm : $fallback;
}

/* $draft=true YALNIZCA operatorun on izlemesi icin: baslik 'DRAFT INVOICE'
 * yazar, numara satiri 'not assigned yet' der. Belge diske YAZILMAZ, numara
 * YANMAZ, aliciya gitmez -- ciktinin kendisi de bunu soylemeli ki yanlislikla
 * iletilse bile fatura sanilmasin. */
function vestra_render_invoice_pdf(array $order, array $items, ?array $sellerAcc, string $invoiceNo, bool $draft = false): string {
    /* Para birimi. Belge bugune kadar EUR'a SABITTI: tutarlar eur() ile basiliyor ve
       "Currency" satiri duz "EUR" yaziyordu. ABD'li bir alicidan ABD'li bir hesaba
       yapilan satista bu yanlis: alici dolar gonderir, fatura euro der, ve hesaba
       gecen tutar faturadakiyle tutmaz.
       Daha somut bir sebep: Mercury'nin wire talimatlari EUR ile USD icin FARKLI
       banka yolu veriyor (USD -> Choice Financial Group dogrudan; EUR -> JP Morgan
       uzerinden, zorunlu /FFC/ memo satiriyla). Yanlis para biriminde kesilen bir
       fatura, aliciyi yanlis talimata yonlendirir.
       Varsayilan EUR: mevcut butun faturalar oyle kesildi, davranis degismemeli. */
    $cur  = strtoupper(trim((string)($order['currency'] ?? 'EUR')));
    $sym  = $cur === 'USD' ? 'US$' : '€';
    $money = fn($n) => $sym.number_format((float)$n, 2, '.', ',');
    $pdf = new VestraPdf();
    $left = 50.0; $right = 545.0; $width = $right - $left; $bottom = 70.0;
    $y = VestraPdf::PAGE_H - 60;
    $newPage = function() use (&$y, $pdf) { $pdf->addPage(); $y = VestraPdf::PAGE_H - 60; };
    $need = function(float $h) use (&$y, $bottom, $newPage) { if ($y - $h < $bottom) $newPage(); };

    // ── Header ──
    /* The mark, then the wordmark as live text beside it. Not one baked image: the name has
       to survive a reader that fails on the logo, and text stays selectable and searchable
       in the buyer's document system. If the file is missing the wordmark simply starts at
       the margin and the header still reads correctly.
       Taken from the 512px icon at 256px and quality 95, not the 192px one at 80: a logo is
       flat colour and hard edges, the worst case for JPEG, and at the default quality the
       gold V picked up a visible halo. It costs about 8 KB.
       Corners rounded to the proportion in favicon.svg (7 of 32): the icon file is a square
       raster because a phone's OS does the rounding, and on paper nothing does — untouched it
       prints as a hard black tile next to the wordmark.
       Centred on both text lines rather than hung off the cap line, so the mark and the
       wordmark read as one lockup instead of two things that happen to be adjacent. */
    $markSide = 30.0;
    $markX    = $left;
    $logo     = vestra_pdf_thumb('/icon-512.png', 256, 95);
    if ($logo !== '' && $pdf->imageJpeg($logo, $left, $y - 1.5 - $markSide / 2, $markSide, $markSide, $markSide * 7 / 32)) {
        $markX = $left + $markSide + 11;
    }
    $pdf->text($markX, $y, 20, 'VESTRA', true);
    /* Isletmeci adi tek yerden: banka kaydi "Acerasoft LLC" yaziyor, kodda ise
       kucuk harfli 'Acerasoft LLC' sabitti. Fatura ustte bir, altta baska turlu
       yazarsa alicinin muhasebesi iki farkli sirket gorur. */
    /* Faturadaki TICARI UNVAN. 'company' alani HALKA ACIK -- showroom.php
       saticinin vitrin adi olarak onu basiyor. Bir satici faturayi baska bir
       unvanla kesmek isteyebilir (Garage Le Paris -> "Agaya Paris") ve bunun
       icin vitrin adini degistirmek, istenmeyen bir yeniden adlandirma olurdu.
       'invoice_name' yalnizca BELGEDE gecerli; bos ise davranis eskisi gibi. */
    $opName = vestra_invoice_issuer_name($sellerAcc, 'Acerasoft LLC');
    $pdf->text($markX, $y - 16, 8, $opName.'  ·  vestrasales.com', false);
    $pdf->textR($right, $y, 22, $draft ? 'DRAFT INVOICE' : 'INVOICE', true);
    $pdf->textR($right, $y - 18, 9, 'Invoice No:  '.($draft ? 'not assigned yet' : $invoiceNo));
    $pdf->textR($right, $y - 30, 9, 'Date:  '.substr($order['date'] ?? date('c'), 0, 10));
    $pdf->textR($right, $y - 42, 9, 'Order ref:  '.($order['ref'] ?? ''));
    $y -= 66;
    $pdf->line($left, $y, $right, $y, 1.0, 0.15);
    $y -= 22;

    // ── From / Bill To ──
    $colW = ($width - 24) / 2; $fromX = $left; $toX = $left + $colW + 24;
    $pdf->text($fromX, $y, 10, 'From (Seller)', true);
    $pdf->text($toX, $y, 10, 'Bill To (Buyer)', true);
    $y -= 15;

    if ($sellerAcc) {
        $sellerLines = array_values(array_filter([
            vestra_invoice_issuer_name($sellerAcc),
            $sellerAcc['address'] ?? '',
            $sellerAcc['country'] ?? '',
            /* Ayni bicimleyici aliciyla ORTAK: satici EIN'i duz, alici EIN'i tireli
               cikarsa belge kendi icinde iki ayri gosterim tasir. */
            !empty($sellerAcc['vat_id'])    ? vestra_tax_id_hint((string)($sellerAcc['country'] ?? ''))['short'].': '
                .vestra_format_tax_id((string)$sellerAcc['vat_id'], (string)($sellerAcc['country'] ?? '')) : '',
            !empty($sellerAcc['reg_number'])? 'Reg. no: '.$sellerAcc['reg_number'] : '',
            /* No seller e-mail. The address on the account is a login credential, not a
               billing contact, and it is usually a personal one — printing it turns a
               company invoice into a person's mailbox and invites the buyer to settle the
               order off-platform. Company, address, VAT and registration number are what
               an invoice has to carry; correspondence goes through VESTRA. */
        ], fn($v) => $v !== ''));
    } else {
        $sellerLines = ['VESTRA (Acerasoft LLC)', 'Marketplace-catalog item', 'support@vestrasales.com'];
    }
    $b = $order['buyer'] ?? [];
    /* A sole trader registers under their own name, so the contact line repeats the company
       line — "GHINEA PRINTESA SABRINA" above, "Sabrina Ghinea" below. Not an exact match:
       the words come in a different order and the registered name often carries one the
       person does not use day to day. So the contact is dropped when every word of it already
       appears in the company name. Two words minimum, or a company containing a common
       surname would swallow a genuine one-word contact — and a real contact at a real company
       ("Boutique Nord AS" / "Anna Meyer") still prints, which is the point of the line. */
    $words = function (string $v): array {
        $w = preg_split('/\s+/', trim(mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $v) ?? '')));
        return array_values(array_filter($w, fn($x) => $x !== ''));
    };
    $buyerName = trim((string)($b['name'] ?? ''));
    $nameWords = $words($buyerName);
    if (count($nameWords) >= 2 && !array_diff($nameWords, $words((string)($b['company'] ?? '')))) {
        $buyerName = '';
    }

    $buyerLines = array_values(array_filter([
        $b['company'] ?? '', $b['address'] ?? '', $b['country'] ?? '',
        !empty($b['vat'])
          ? vestra_tax_id_hint((string)($b['country'] ?? ''))['short'].': '
            .vestra_format_tax_id((string)$b['vat'], (string)($b['country'] ?? ''))
          : '',
        /* Alicinin sicil numarasi, saticininkiyle AYNI kalipta (operator istegi,
           1 Eyl 2026). VAT'i olmayan alicida sirketi belgeye baglayan tek resmi
           numara bu -- Daymond ornegi: vat_id bos, reg (CUI) dolu. Bos ise satir
           hic basilmaz. */
        !empty($b['reg']) ? 'Reg. no: '.$b['reg'] : '',
        $buyerName,
        /* No buyer e-mail, for the same reason the seller's is absent. An invoice is not a
           contact sheet: it is forwarded to carriers, brokers and banks, and it sits in an
           archive for years. Company, address, VAT and the person to ask for are what it has
           to carry; the mailbox belongs in the account, not on a document that travels. */
    ], fn($v) => $v !== ''));

    /* Wrap each block to its own column before pairing them up. A company address written as
       one line — street, district, post code, city — is easily wider than half the page, and
       unwrapped it ran straight across into the buyer's block on the right. */
    $wrapCol = function (array $lines) use ($colW): array {
        $out = [];
        foreach ($lines as $l) foreach (vestra_invoice_wrap($l, $colW - 8, 9) as $w) $out[] = $w;
        return $out;
    };
    $sellerLines = $wrapCol($sellerLines);
    $buyerLines  = $wrapCol($buyerLines);

    $n = max(count($sellerLines), count($buyerLines));
    for ($i = 0; $i < $n; $i++) {
        if (isset($sellerLines[$i])) $pdf->text($fromX, $y, 9, $sellerLines[$i]);
        if (isset($buyerLines[$i]))  $pdf->text($toX, $y, 9, $buyerLines[$i]);
        $y -= 13;
    }
    $y -= 8;

    // ── Payment box ──
    // Escrow-paid orders show a "PAID via secure escrow" box instead of bank
    // details — the buyer has already paid by card and no transfer is due.
    $paid = !empty($order['paid']);
    if ($paid) {
        $paidLines = ['Paid by card via VESTRA secure escrow — no bank transfer required.'];
        if (!empty($order['paid_at'])) $paidLines[] = 'Payment received: '.$order['paid_at'];
        $boxH = 16 + count($paidLines) * 13;
        $need($boxH + 14);
        $pdf->rectFill($left, $y - $boxH + 4, $width, $boxH);
        $by = $y - 8;
        $pdf->text($left + 10, $by, 9, 'Payment status — PAID (escrow)', true);
        foreach ($paidLines as $bl) { $by -= 13; $pdf->text($left + 10, $by, 9, $bl); }
        $y -= ($boxH + 14);
    } else {
        // ── Bank details (seller-issued invoices only, when provided) ──
        /* The reference the payer must quote, stated directly under the account it is paid
           into. Without one, a five-figure transfer arrives carrying whatever the buyer's
           clerk typed and matching it becomes somebody's afternoon.
           The order reference, not the invoice number: it is what the buyer's own purchasing
           system tracks, what every message about this sale already quotes, and what stays
           constant across the part-shipments this order will be delivered in — one string
           tying the bank statement back to the sale from either side. */
        $payRef = trim((string)($order['ref'] ?? ''));
        /* Satir sirasi bilincli: kutu TANIDIK isimle acilir (Account holder =
           faturayi kesen sirket), hesabin oturdugu kurum asagida teknik detay
           olarak durur. Onceki hali "Bank: Choice Financial Group" ile aciliyordu
           ve alicinin ilk gordugu sey hic duymadigi bir isim oluyordu -- fintech
           hesaplarinda (Mercury/Brex/Relay) hesap lisansli bir ortak bankada
           oturur ve alicinin taniyacagi isim satici, o banka degil.

           Banka adi SILINEMEZ: odeyenin havale formunda alici banka alani zorunlu.
           Faturada yazmazsa alici ya routing'i arayip ayni isme kendisi varir ya da
           "Mercury" tahmin eder -- ad routing ile eslesmez, havale doner. Etiket bu
           yuzden formun kendi dili: "Beneficiary bank". */
        $rails = vestra_payment_rails($sellerAcc ?? [], $cur);
        $bankLines = $sellerAcc ? array_values(array_filter(array_merge(
            [!empty($sellerAcc['bank_holder']) ? 'Account holder: '.$sellerAcc['bank_holder'] : ''],
            $rails,
            [
              !empty($sellerAcc['bank_name'])   ? 'Beneficiary bank: '.$sellerAcc['bank_name'] : '',
              !empty($sellerAcc['bank_address']) ? 'Bank address: '.$sellerAcc['bank_address'] : '',
              $payRef !== '' ? 'Payment reference: '.$payRef : '',
            ]
        ), fn($v) => $v !== '')) : [];
        /* Para birimine uygun hesap YOKSA kutu hic basilmiyor -- $rails bos donuyor
           ve geriye yalnizca ad/adres/referans kaliyor, ki bunlarla odeme yapilamaz.
           Bos bir kutu yerine hicbir kutu: alici "buraya gonderemiyorum" diye sorar,
           yanlis rayla gondermeye kalkmaz. */
        if (!$rails) $bankLines = [];
        if ($bankLines) {
            $boxH = 16 + count($bankLines) * 13;
            $need($boxH + 14);
            $pdf->rectFill($left, $y - $boxH + 4, $width, $boxH);
            $by = $y - 8;
            $pdf->text($left + 10, $by, 9, 'Payment details — bank transfer', true);
            foreach ($bankLines as $bl) { $by -= 13; $pdf->text($left + 10, $by, 9, $bl); }
            $y -= ($boxH + 14);
        } else {
            $y -= 6;
        }
    }

    // ── Line-items table ──
    $need(50);
    /* The SKU column carries full manufacturer model codes (WH1JQ040B139MAI, WV0MG10W7SS0NI),
       which is what the buyer and the customs entry match against, so it gets the room it
       needs rather than the description's leftovers. */
    /* Sutun konumlari para birimine gore kayiyor. "€89.90" ile "US$105.17" ayni
       genislikte DEGIL: US$ oneki uc karakter fazla. EUR icin ayarlanmis konumlarla
       USD basildiginda Unit ve Total sutunlari ust uste biniyor ve fatura
       "US$105.17US$1,051.70" diye cikiyor -- musteriye giden bir belgede okunamaz.
       USD'de Qty ve Unit sola aliniyor, aradaki bosluk Total'e kaliyor. */
    $wide   = ($cur !== 'EUR');
    $colSku = $left + 4; $colDesc = $left + 92; $colCol = $left + 265;
    $colQty = $left + ($wide ? 338 : 355); $colUnit = $left + ($wide ? 376 : 400);
    $pdf->rectFill($left, $y - 14, $width, 18, 0.88);
    $pdf->text($colSku, $y - 9, 8.5, 'SKU', true);
    $pdf->text($colDesc, $y - 9, 8.5, 'Description', true);
    $pdf->text($colCol, $y - 9, 8.5, 'Colour(s)', true);
    $pdf->text($colQty, $y - 9, 8.5, 'Qty', true);
    $pdf->text($colUnit, $y - 9, 8.5, 'Unit', true);
    $pdf->textR($right - 4, $y - 9, 8.5, 'Total', true);
    $y -= 30;

    $goodsTotal = 0.0;
    $totalQty   = 0;
    foreach ($items as $it) {
        /* Marka iki kez yazilmasin -- kural products.php'de, tek yerde:
           fatura ve siparis e-postasi ayni fonksiyonu cagiriyor. */
        $desc = vestra_product_label((string)($it['brand'] ?? ''), (string)($it['name'] ?? ''));
        $descLines = vestra_invoice_wrap($desc, $colCol - $colDesc - 6, 9);
        $skuLines  = vestra_invoice_wrap((string)($it['sku'] ?? ''), $colDesc - $colSku - 8, 8);
        $rowH = max(13, max(count($descLines), count($skuLines)) * 11) + 8;
        $need($rowH);
        foreach ($skuLines as $j => $sl)  $pdf->text($colSku,  $y - ($j * 10), 8, $sl);
        foreach ($descLines as $j => $dl) $pdf->text($colDesc, $y - ($j * 11), 9, $dl);
        if (!empty($it['colors'])) {
            $colTxt = implode(', ', (array)$it['colors']);
            foreach (vestra_invoice_wrap($colTxt, $colQty - $colCol - 6, 8) as $j => $cl) $pdf->text($colCol, $y - ($j * 10), 8, $cl);
        }
        $pdf->text($colQty, $y, 9, (string)((int)($it['qty'] ?? 0)));
        $pdf->text($colUnit, $y, 9, $money($it['unit'] ?? 0));
        $pdf->textR($right - 4, $y, 9, $money($it['line'] ?? 0));
        $goodsTotal += (float)($it['line'] ?? 0);
        $totalQty   += (int)($it['qty'] ?? 0);
        $y -= $rowH;
    }

    /* A voucher and the freight charge must each be a visible line, not folded quietly into
       the goods total: this document is the seller's invoice and the basis of the bank
       transfer, so the buyer has to be able to reconcile it against the order confirmation
       line for line. On a multi-seller order the discount here is only this seller's
       apportioned share (see vestra_issue_order_invoices). */
    $discount = round((float)($order['discount'] ?? 0), 2);
    $shipping = round((float)($order['shipping'] ?? 0), 2);
    $shipLbl  = trim((string)($order['shipping_label'] ?? '')) ?: 'Shipping';

    $rows = [];
    if ($discount > 0 || $shipping > 0) $rows[] = ['Goods total', $money($goodsTotal)];
    if ($discount > 0) {
        $vcode = trim((string)($order['voucher_code'] ?? ''));
        $rows[] = ['Voucher'.($vcode !== '' ? ' '.$vcode : ''), '-'.$money($discount)];
    }
    if ($shipping > 0) $rows[] = [$shipLbl, $money($shipping)];
    $grand = max(0, $goodsTotal - $discount) + $shipping;

    $need(60 + count($rows) * 15);
    $y -= 4;
    $pdf->line($left, $y, $right, $y, 0.7, 0.5);
    $y -= 18;
    foreach ($rows as [$label, $amount]) {
        $pdf->textR($colUnit, $y, 10, $label);
        $pdf->textR($right - 4, $y, 10, $amount);
        $y -= 15;
    }
    if ($rows) { $y += 9; $pdf->line($colUnit - 60, $y, $right, $y, 0.5, 0.35); $y -= 15; }
    $pdf->textR($colUnit, $y, 10, $rows ? 'Total' : 'Goods total', true);
    $pdf->textR($right - 4, $y, 11, $money($grand), true);
    $y -= 16;

    /* Cevrim dayanagi TUTARLARIN ALTINDA. Onceden "Shipment particulars" tablosunda,
       teslim sarti ve mensei arasinda duruyordu -- oraya sevkiyat icin bakilir, fiyat
       icin degil. 89,90 x kur hesabini yapan muhasebeci rakami tutarlarin yaninda
       arar; bulamayinca "neden dolar, hangi kurla?" diye sorar ve bu soru odemeyi
       geciktirir. Parayla ilgili not, paranin yanina. */
    if (!empty($order['fx_note'])) {
        $need(24);
        $y -= 6;
        /* Dar sarma (tam genislik degil): satirlar saga hizali basiliyor ve genis
           sarmada ikinci satir tek kelimeyle sagda asili kaliyordu. Daralinca blok
           dengeli iki-uc satira boluniyor ve tutar sutunuyla hizali duruyor. */
        foreach (vestra_invoice_wrap((string)$order['fx_note'], 300, 7.5) as $fl) {
            $pdf->textR($right - 4, $y, 7.5, $fl, false, 0.35);
            $y -= 10;
        }
        $y -= 4;
    }
    $y -= 14;

    /* ── Shipment and customs particulars ──
       This invoice is not only the demand for payment: it is the document the goods travel
       on. A carrier, a customs broker and the buyer's own accountant each read it for
       something the price table does not say.
       VAT is the one that cannot be left out. A Dutch company charging nothing on a
       five-figure sale has to say on the face of the invoice WHY, or the buyer's accountant
       books it wrongly and the seller's own return has an unexplained zero-rated supply.
       Incoterms and origin are printed only when the order carries them — a guessed delivery
       term decides who pays a 25% import charge, and a guessed origin is a false declaration.
       Better a gap the operator can see than a confident invention. */
    $shipRows = [];
    $shipRows[] = ['Total quantity', number_format($totalQty).' pcs in '.count($items).' line items'];
    $shipRows[] = ['Currency', $cur === 'USD' ? 'USD — all amounts in US dollars' : 'EUR — all amounts in euro'];
    if (!empty($order['incoterms']))      $shipRows[] = ['Delivery terms', (string)$order['incoterms']];
    if (!empty($order['origin']))         $shipRows[] = ['Country of origin', (string)$order['origin']];
    if (!empty($order['export_reason']))  $shipRows[] = ['Reason for export', (string)$order['export_reason']];
    if (!empty($order['vat_note']))       $shipRows[] = ['VAT', (string)$order['vat_note']];
    /* Kur notu burada DEGIL: tutarlarin hemen altinda basiliyor (yukariya bakin).
       Iki yerde birden basmak belgeyi tekrara dusururdu. */

    $need(24 + count($shipRows) * 12);
    $pdf->line($left, $y + 10, $right, $y + 10, 0.5, 0.75);
    $pdf->text($left, $y, 8.5, 'Shipment particulars', true);
    $y -= 13;
    foreach ($shipRows as [$k, $v]) {
        $pdf->text($left, $y, 8, $k.':');
        foreach (vestra_invoice_wrap($v, $width - 110, 8) as $j => $vl) {
            $pdf->text($left + 106, $y - ($j * 10), 8, $vl);
        }
        $y -= max(12, count(vestra_invoice_wrap($v, $width - 110, 8)) * 10 + 2);
    }
    $y -= 10;

    /* Advance payment, stated on the invoice itself. An invoice that offers a credit period
       is the buyer's finance department's authority to take it, whatever was agreed in the
       thread — so the document has to carry the same term as the deal: money first, goods
       after. */
    /* Kendi kutusunda ve baslikli. Onceden ust uste uc duz paragrafin ilkiydi: hepsi
       ayni 8 punto, ayni siyah, aralarinda bosluk yok. Odeme sarti bir ihtilafta
       dayanilan cumle, altindaki iki cumle ise tasimanin gerektirdigi klise metin --
       ayni agirlikta basilmalari, onemli olani gorunmez yapiyordu. Kutu, belgedeki
       odeme kutusuyla ayni dili konusuyor: bu da paraya dair. */
    $termsHead = $paid ? 'Payment status' : 'Payment terms';
    $terms = $paid
        ? 'Paid in full via VESTRA secure escrow. Funds are released to the seller once the buyer confirms delivery.'
        : '100% advance. Goods are dispatched after the full invoice amount is received in the account shown above.';
    $termLines = $pdf->wrap($terms, $width - 20, 9);
    $tBoxH = 16 + count($termLines) * 12;
    $need($tBoxH + 14);
    $pdf->rectFill($left, $y - $tBoxH + 6, $width, $tBoxH);
    $ty = $y - 6;
    $pdf->text($left + 10, $ty, 9, $termsHead, true);
    foreach ($termLines as $fl) { $ty -= 12; $pdf->text($left + 10, $ty, 9, $fl); }
    $y -= ($tBoxH + 12);

    /* An order delivered in instalments has to say so on its invoice. Without it the first
       short consignment reads as an invoice discrepancy to the buyer's finance team, and to
       a customs broker as goods missing from the declared entry. Saying it here makes the
       short delivery the expected thing and points at the document that governs each box —
       the invoice value covers the whole order, the entry value never does. */
    if (!empty($order['partial_shipments'])) {
        $need(30);
        foreach ($pdf->wrap('Partial shipments permitted. The goods are delivered in instalments against this '
            .'invoice; each consignment travels with its own packing list stating the items and the value in that '
            .'consignment, which is the value to be declared for that entry.', $width, 8) as $fl) {
            $pdf->text($left, $y, 8, $fl); $y -= 11;
        }
        $y -= 2;
    }
    /* "VESTRA satici degildir" notu, satici GERCEKTEN baskasi oldugunda dogrudur ve
       gereklidir: pazar yeri kendini satisin tarafi yapmamalidir. Ama Acerasoft LLC'nin
       KENDI sattigi bir satista ayni cumle faturayi kendi icinde celiskiye dusuruyor --
       ustte "From (Seller): Acerasoft LLC" yazarken altta "acerasoft satici degildir"
       demek, gumrukte ve bir ihtilafta belgeyi zayiflatan bir beyandir. O yuzden not
       yalnizca uclu satista basiliyor. */
    $platformIsSeller = stripos((string)($sellerAcc['company'] ?? ''), 'acerasoft') !== false;

    /* Beyanlar. Ustteki sevkiyat tablosuyla AYNI etiket/deger duzeni kullaniliyor --
       belge boyunca tek bir okuma aliskanligi olsun, ve gumruk musaviri aradigi
       satiri cumlenin icinden ayiklamak yerine etiketten bulsun. Gri: bu metinler
       tasinmak zorunda ama tutarlarla ayni agirlikta yarismamali.
       Etiket bicimi kasitli: "Seller of record: Acerasoft LLC" ticari faturanin
       standart ALANI, cumleye gomulmus hali degil. Uclu satista ise gercekten bir
       feragat cumlesi gerekiyor, o cumle olarak kaliyor. */
    /* "Seller of record" belgenin USTUNDEKI satici ile AYNI ad olmali:
       $opName zaten vestra_invoice_issuer_name'den geliyor (invoice_name >
       company). Burada duz 'company' okunuyordu ve fatura ustte "Agaya Paris"
       derken alttaki beyan "GARAGE LE PARIS" diyordu -- ayni belgede iki ayri
       tuzel kisi adi, gumrukte ve bir ihtilafta belgeyi zayiflatan tam olarak
       budur. Operator karari (1 Eyl 2026): "Seller of record GARAGE LE PARIS,
       AGAYA PARIS OLARAK DEGISECEK". */
    $declRows = [[
        'Seller of record',
        $opName !== '' ? $opName : 'the seller named above',
    ]];
    if (!$platformIsSeller) {
        $declRows[] = ['Marketplace', 'VESTRA (Acerasoft LLC) operates the marketplace connecting buyer and '
            .'seller and is not the seller of record for this sale.'];
    }
    /* The certification a commercial invoice is expected to carry when it accompanies goods
       across a border. Brokers look for this sentence; without it some ask for the invoice to
       be reissued, which on a part-shipped order means holding a consignment at the border
       over a missing line of boilerplate. */
    $declRows[] = ['Declaration', 'We certify that the information on this invoice is true and correct, '
        .'and that the contents of this shipment are as stated above.'];

    $y -= 2;
    $need(18 + count($declRows) * 12);
    $pdf->line($left, $y + 8, $right, $y + 8, 0.5, 0.82);
    $y -= 4;
    foreach ($declRows as [$k, $v]) {
        $vl = vestra_invoice_wrap($v, $width - 110, 7.5);
        $pdf->text($left, $y, 7.5, $k, false, 0.45);
        foreach ($vl as $i => $line) $pdf->text($left + 106, $y - ($i * 10), 7.5, $line, false, 0.35);
        $y -= max(11, count($vl) * 10 + 1);
    }

    /* ── Page footer ──
       A sheet of an invoice that gets separated from the rest — and they do, once a broker
       photocopies one page or a printer collates two jobs — carries nothing on it saying what
       it belongs to. The mark and the two references make every page identify itself, and
       "page 1 of 2" is what tells a reader a second page existed at all.
       Stamped after layout because the page count is not known until the last row is drawn. */
    $ref = trim((string)($order['ref'] ?? ''));
    $pdf->stampEachPage(function (VestraPdf $p, int $n, int $total) use ($left, $right, $invoiceNo, $ref, $draft) {
        $fy = 44.0;
        $p->line($left, $fy + 16, $right, $fy + 16, 0.5, 0.8);
        $tx = $left;
        $mk = vestra_pdf_thumb('/icon-512.png', 256, 95);   // already embedded once by the header
        if ($mk !== '' && $p->imageJpeg($mk, $left, $fy - 3, 15, 15, 15 * 7 / 32)) $tx = $left + 21;
        $p->text($tx, $fy + 2, 8, 'VESTRA', true);
        /* Taslakta HER sayfanin dibinde de yazar: tek sayfa iletilse bile
           uzerinde "fatura degil" ibaresi tasinsin. */
        $p->text($tx + 44, $fy + 2, 7.5, $draft
            ? trim('DRAFT - not an issued invoice'.($ref !== '' ? '   ·   Order '.$ref : ''))
            : trim('Invoice '.$invoiceNo.($ref !== '' ? '   ·   Order '.$ref : '')));
        $p->textR($right, $fy + 2, 7.5, 'Page '.$n.' of '.$total);
    });

    return $pdf->output();
}

/**
 * Generate (once) and persist the PDF for one seller's slice of a sale.
 * Idempotent — a second call for the same ($order['ref'], $sellerAcc) returns
 * the already-issued invoice untouched rather than re-numbering/re-rendering.
 */
/* Automatic invoicing is SUSPENDED by default: after an order we confirm stock
   first and issue the invoice by hand (within the day). vestra_ensure_invoice()
   therefore only creates a PDF when explicitly forced — the operator's "Issue
   invoice" action, or a completed escrow card payment — or when auto-issuing is
   switched back on by defining VESTRA_AUTO_INVOICE=true. Already-issued invoices
   are always returned regardless of the switch. */
function vestra_auto_invoice_enabled(): bool {
    return defined('VESTRA_AUTO_INVOICE') ? (bool) VESTRA_AUTO_INVOICE : false;
}
/* $redraft re-renders an already-issued invoice in place, KEEPING its number.
   For correcting a document that has not yet left the building — a wrong buyer
   detail spotted between issuing and sending. It is deliberately not what
   happens by default and never what happens automatically: once an invoice has
   gone to the buyer, the number is theirs and a correction is a credit note plus
   a new invoice, not a quiet rewrite of the file behind it. */
function vestra_ensure_invoice(array $order, array $items, ?array $sellerAcc, bool $force = false, bool $redraft = false): array {
    $sellerKey = vestra_invoice_seller_key($sellerAcc);
    $pdfPath  = vestra_invoice_file($order['ref'], $sellerKey);
    $metaPath = vestra_invoice_meta_file($order['ref'], $sellerKey);
    if (is_file($pdfPath) && is_file($metaPath)) {
        $meta = json_decode((string)file_get_contents($metaPath), true) ?: [];
        $no   = (string)($meta['no'] ?? '');
        if (!$redraft || $no === '') {
            return ['no' => $no, 'path' => $pdfPath, 'seller_key' => $sellerKey];
        }
        /* TUTAR DA YENIDEN YAZILIYOR. Onceden yalnizca 'redrafted_at'
           ekleniyordu ve meta'daki 'total' ILK kesimin rakaminda kaliyordu --
           paneller ve alicinin fatura satiri bu alandan okudugu icin belge
           3.950 EUR derken ekranda "INV-2026-1001 · 6.300,00 EUR" yaziyordu.
           Meta belgenin OZETI; belge degistiyse ozet de degismek zorunda,
           yoksa ayni faturanin iki rakami olur. Alici da 'buyer' alanindan
           degisebilir (kalem cikarilinca degil ama duzeltme sirasinda
           duzeltilmis olabilir), o yuzden o da tazeleniyor. */
        $goodsR = 0.0; foreach ($items as $it) $goodsR += (float)($it['line'] ?? 0);
        $meta['redrafted_at'] = date('c');
        $meta['currency'] = strtoupper(trim((string)($order['currency'] ?? ($meta['currency'] ?? 'EUR'))));
        $meta['total']    = round($goodsR - (float)($order['discount'] ?? 0) + (float)($order['shipping'] ?? 0), 2);
        $meta['buyer']    = trim((string)(($order['buyer']['company'] ?? '') ?: ($order['buyer']['name'] ?? ''))) ?: (string)($meta['buyer'] ?? '');
        file_put_contents($pdfPath, vestra_render_invoice_pdf($order, $items, $sellerAcc, $no), LOCK_EX);
        file_put_contents($metaPath, json_encode($meta, JSON_PRETTY_PRINT), LOCK_EX);
        return ['no' => $no, 'path' => $pdfPath, 'seller_key' => $sellerKey, 'redrafted' => true,
                'total' => $meta['total']];
    }
    /* Suspended: no invoice is created until stock is confirmed and it is issued by hand. */
    if (!$force && !vestra_auto_invoice_enabled()) {
        return ['no' => '', 'path' => '', 'seller_key' => $sellerKey, 'pending' => true];
    }
    $no = vestra_next_invoice_no($sellerKey);
    $bytes = vestra_render_invoice_pdf($order, $items, $sellerAcc, $no);
    file_put_contents($pdfPath, $bytes, LOCK_EX);
    /* Tutar ve para birimi META'ya yaziliyor ki paneller "Invoice INV-2026-1009 ·
       US$2,153.40" diyebilsin. Alternatif, her sayfa yuklemede PDF'i ya da siparis
       satirlarini yeniden hesaplamakti; meta zaten belge basina bir kez yaziliyor
       ve belgedeki rakam NEYSE panelde o gorunmeli -- yeniden hesaplanan bir rakam,
       kesimden sonra degisen bir veriyle belgeden sapabilirdi. */
    $goods = 0.0; foreach ($items as $it) $goods += (float)($it['line'] ?? 0);
    $total = round($goods - (float)($order['discount'] ?? 0) + (float)($order['shipping'] ?? 0), 2);
    file_put_contents($metaPath, json_encode([
        'no' => $no, 'ref' => $order['ref'], 'seller_key' => $sellerKey, 'issued_at' => date('c'),
        /* Kept so the download name can carry the buyer without the serving endpoint
           re-reading orders/offers/requests to find out who the invoice belongs to. */
        'buyer' => trim((string)(($order['buyer']['company'] ?? '') ?: ($order['buyer']['name'] ?? ''))),
        'currency' => strtoupper(trim((string)($order['currency'] ?? 'EUR'))),
        'total'    => $total,
    ], JSON_PRETTY_PRINT), LOCK_EX);
    return ['no' => $no, 'path' => $pdfPath, 'seller_key' => $sellerKey];
}

/** Invoices already issued for a ref (order or offer) — for rendering download links. No regeneration. */
/**
 * Name the file gets when it is downloaded: what it is, then which order — Invoice-VES-XXXX.
 *
 * Both halves earn their place. "Invoice" says what the attachment is without opening it,
 * which matters because these get forwarded to freight forwarders, customs brokers and banks
 * who receive several different documents against one shipment. The reference is what both
 * sides of the sale already quote, so it is what the file gets filed under, and it goes last
 * so a folder of them sorts by document type first. The buyer's name is deliberately not in
 * it — the file travels further than the sale does.
 *
 * One exception. A cart spanning several sellers produces one invoice per seller for the same
 * reference, and they cannot all be called the same thing — the second download would land as
 * "(1)" or overwrite the first. Only then is the invoice number added to tell them apart.
 */
function vestra_invoice_download_name(string $ref, string $sellerKey): string {
    $safe = preg_replace('/[^A-Za-z0-9_-]/', '', $ref);
    if (count(glob(vestra_invoice_dir().'/'.$safe.'__*.json') ?: []) <= 1) return 'Invoice-'.$safe.'.pdf';
    $meta = @json_decode((string)@file_get_contents(vestra_invoice_meta_file($ref, $sellerKey)), true);
    $no   = is_array($meta) ? preg_replace('/[^A-Za-z0-9_-]/', '', (string)($meta['no'] ?? '')) : '';
    return 'Invoice-'.($no !== '' ? $no.'-' : '').$safe.'.pdf';
}

function vestra_invoices_for_ref(string $ref, bool $followGroup = true): array {
    $dir = vestra_invoice_dir();
    $safeRef = preg_replace('/[^A-Za-z0-9_-]/', '', $ref);
    $out = [];
    foreach (glob($dir.'/'.$safeRef.'__*.json') ?: [] as $metaFile) {
        $meta = json_decode((string)file_get_contents($metaFile), true);
        if (!is_array($meta)) continue;
        $sellerKey = $meta['seller_key'] ?? '';
        $label = 'VESTRA';
        if ($sellerKey !== '' && $sellerKey !== 'vestra') {
            /* auth.php BURADA yukleniyor, dosyanin basinda degil: web sayfalari onu
               zaten tasidigi icin eksik hic gorunmedi, ama bakim betikleri (siparis
               silme teshisi, 24 Agustos'ta) invoice.php'yi tek basina yukleyip tam
               bu satirda "undefined function auth_accounts" fatal'i aldi. Sart olan
               tek dal bu dal -- kosulsuz require etmek, satici etiketi gerektirmeyen
               cagrilara da auth yuku bindirirdi. */
            require_once __DIR__.'/auth.php';
            /* Etiket BELGEDEKI adla ayni olmali: vestra_invoice_issuer_name
               once 'invoice_name'e bakiyor. Burada duz 'company' okunuyordu ve
               fatura "Agaya Paris" derken panel/alici sayfasi ayni belgeyi
               "GARAGE LE PARIS" diye etiketliyordu -- operator faturayi
               degistirdigini sanip tekrar tekrar duzeltmeye calisti. */
            foreach (auth_accounts() as $a) {
                if (($a['id'] ?? '') === $sellerKey) { $label = vestra_invoice_issuer_name($a, 'Seller'); break; }
            }
        }
        $out[] = [
            'no' => $meta['no'] ?? '', 'seller_key' => $sellerKey, 'seller_label' => $label,
            'url' => '/invoice?ref='.urlencode($ref).'&seller='.urlencode($sellerKey),
            'currency'  => (string)($meta['currency'] ?? ''),
            'total'     => (float)($meta['total'] ?? 0),
            'issued_at' => (string)($meta['issued_at'] ?? ''),
        ];
    }
    /* BIRLESIK FATURA bagi: birden fazla teklif tek belgede kesildiyse dosya
       BIRINCIL ref'in adina yazilir ve uyeler offer_responses.json'da
       invoice_group_ref ile ona baglanir. Alici ya da panel hangi uyeden
       bakarsa baksin ayni belgeyi bulmali -- bu yuzden bag TEK yerde, burada
       izleniyor; onay kuyrugu, alici sayfasi ve teshis kendiliginden dogru
       calisiyor. Tek atlama ($followGroup=false ile ozyineleme): iki kaydin
       birbirine isaret ettigi bozuk bir veri dongu kurmasin.
       Dosya dogrudan okunuyor (vestra_read_json DEGIL): bu fonksiyon bakim
       betiklerinde products.php olmadan da cagriliyor (yukaridaki auth notu
       ayni dersin kaydi). */
    if (!$out && $followGroup && $safeRef !== '') {
        $f = dirname(__DIR__).'/data/offer_responses.json';
        if (is_readable($f)) {
            $rs  = json_decode((string)file_get_contents($f), true) ?: [];
            $grp = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($rs[$safeRef]['invoice_group_ref'] ?? ''));
            if ($grp !== '' && $grp !== $safeRef) return vestra_invoices_for_ref($grp, false);
        }
    }
    usort($out, fn($a, $b) => strcmp($a['no'], $b['no']));
    return $out;
}

/**
 * Move every invoice file for a ref out of the live folder, into data/invoices/deleted/.
 *
 * Used when an order is force-deleted. NOTHING is erased: the numbered PDF and its
 * metadata keep existing, timestamped, in a folder the panel does not read. That is
 * the whole point — an issued invoice is a document the company has already handed a
 * customer, and a gap in a number sequence is the first thing an auditor asks about.
 * What we are removing is the dangling link, not the record.
 *
 * Returns how many files were moved.
 */
function vestra_invoices_archive_for_ref(string $ref): int {
    $dir     = vestra_invoice_dir();
    $safeRef = preg_replace('/[^A-Za-z0-9_-]/', '', $ref);
    if ($safeRef === '') return 0;
    $bin = $dir.'/deleted';
    if (!is_dir($bin) && !@mkdir($bin, 0755, true)) return 0;
    $stamp = date('Ymd_His');
    $moved = 0;
    foreach (glob($dir.'/'.$safeRef.'__*') ?: [] as $f) {
        if (!is_file($f)) continue;
        if (@rename($f, $bin.'/'.$stamp.'-'.basename($f))) $moved++;
    }
    return $moved;
}

/**
 * Number + amount for an invoice link: "INV-2026-1009 · US$2,153.40".
 *
 * The word "Invoice" is deliberately NOT here — the panels translate it (t('Invoice')),
 * and a helper that bakes the English word in would undo their localisation. Lives here
 * so buyer, admin and seller views print the SAME line: the amount and its currency come
 * from the invoice meta, i.e. from the document itself, never re-derived from order rows
 * that may have moved since issue. Old metas predate the total field and simply have no
 * amount; the label then falls back to the bare number rather than a fabricated zero.
 */
/**
 * What a panel adds next to a euro order total when the invoice was issued in another
 * currency: " · Invoiced US$2,153.40".
 *
 * The two figures are NOT the same measurement and must not be printed as if they were.
 * orders.csv carries the accepted offer in its own currency — goods only, because that is
 * what the offer covered — while the invoice total is what the buyer actually owes,
 * shipping included and converted. Writing "€1,798.00 (US$2,153.40)" would read as one
 * amount expressed twice, and the reader would work out a rate that does not exist.
 * Hence the word "Invoiced": it names the second figure as a different fact rather than
 * a translation of the first.
 *
 * Returns '' when no invoice exists yet, or when it was issued in the same currency the
 * panel already prints — a euro amount does not need "Invoiced €1,798.00" beside it.
 */
function vestra_order_invoiced_note(string $ref, string $panelCurrency = 'EUR'): string {
    foreach (vestra_invoices_for_ref($ref) as $iv) {
        $cur = strtoupper((string)($iv['currency'] ?? ''));
        $tot = (float)($iv['total'] ?? 0);
        if ($cur === '' || $tot <= 0 || $cur === strtoupper($panelCurrency)) continue;
        return 'Invoiced '.($cur === 'USD' ? 'US$' : '€').number_format($tot, 2, '.', ',');
    }
    return '';
}

function vestra_invoice_link_label(array $iv): string {
    $s = (string)($iv['no'] ?? '');
    $t = (float)($iv['total'] ?? 0);
    if ($t > 0) {
        $sym = strtoupper((string)($iv['currency'] ?? '')) === 'USD' ? 'US$' : '€';
        $s .= ' · '.$sym.number_format($t, 2, '.', ',');
    }
    return $s;
}

/* Bir siparisin fatura YUKLERI (satici basina meta+satirlar+hesap), belge
 * uretmeden. vestra_issue_order_invoices'in govdesinden AYRILDI ki operator
 * onaylamadan once ayni yukten TASLAK cizdirilebilsin: onizleme ile kesilen
 * belge ayni koddan cikmali, yoksa operator bir sey gorur, alici baskasini
 * alir. Iskonto payi, tek seferlik navlun, satici gruplamasi -- hepsi burada. */
function vestra_order_invoice_payloads(string $ref): array {
    require_once __DIR__.'/orders.php';
    $ref = preg_replace('/[^A-Za-z0-9_-]/', '', $ref);
    $orderRow = null;
    foreach (vestra_read_csv('orders.csv') as $row) { if (($row['ref'] ?? '') === $ref) { $orderRow = $row; break; } }
    if (!$orderRow) return [];
    require_once __DIR__.'/auth.php';   // the buyer block falls back to the registered account
    $ld = vestra_order_lines($orderRow);
    $orderMeta = [
        'ref' => $ref, 'date' => $orderRow['timestamp'] ?? date('c'),
        /* Freight is charged on the order, not per seller: splitting one consignment's
           carriage across sellers would invent numbers nobody can reconcile. It rides on
           the first invoice; a second seller's invoice shows goods only. */
        'shipping' => round((float)($orderRow['shipping'] ?? 0), 2),
        'shipping_label' => trim((string)($orderRow['shipping_label'] ?? '')),
        'partial_shipments' => !empty($orderRow['partial_shipments']),
        /* Shipment particulars: printed when set, silent when not — see the footer block in
           vestra_render_invoice_pdf() for why none of these are ever guessed. */
        'incoterms'     => trim((string)($orderRow['incoterms'] ?? '')),
        'origin'        => trim((string)($orderRow['origin'] ?? '')),
        'export_reason' => trim((string)($orderRow['export_reason'] ?? '')),
        'vat_note'      => trim((string)($orderRow['vat_note'] ?? '')),
        'buyer' => vestra_invoice_buyer($orderRow),
    ];
    $bySeller = [];
    foreach ($ld['lines'] as $l) { $bySeller[$l['seller_uid'] ?: 'vestra'][] = $l; }

    /* A voucher discounts the ORDER, but invoices are issued per seller. Putting the whole
       discount on each slice would deduct it as many times as there are sellers, so it is
       split in proportion to each seller's goods value, and the last slice takes the rounding
       remainder — the per-invoice amounts then add back up to exactly what was granted. */
    $orderDiscount = round((float)($orderRow['discount'] ?? 0), 2);
    $voucherCode   = trim((string)($orderRow['voucher_code'] ?? ''));
    $goodsTotal = 0.0;
    foreach ($ld['lines'] as $l) { $goodsTotal += (float)($l['line'] ?? 0); }
    $shares = [];
    if ($orderDiscount > 0 && $goodsTotal > 0) {
        $acc = 0.0; $keys = array_keys($bySeller); $lastKey = end($keys);
        foreach ($bySeller as $sid => $sellerItems) {
            $sellerGoods = 0.0;
            foreach ($sellerItems as $l) { $sellerGoods += (float)($l['line'] ?? 0); }
            $share = ($sid === $lastKey)
                ? round($orderDiscount - $acc, 2)
                : round($orderDiscount * ($sellerGoods / $goodsTotal), 2);
            $shares[$sid] = max(0.0, $share);
            $acc = round($acc + $shares[$sid], 2);
        }
    }

    $out = [];
    foreach ($bySeller as $sid => $sellerItems) {
        $sellerAcc = null;
        if ($sid !== 'vestra') { foreach (auth_accounts() as $a) { if (($a['id'] ?? '') === $sid) { $sellerAcc = $a; break; } } }
        $meta = $orderMeta;
        if (!empty($shares[$sid])) { $meta['discount'] = $shares[$sid]; $meta['voucher_code'] = $voucherCode; }
        if ($sid !== array_key_first($bySeller)) { $meta['shipping'] = 0.0; }   // carriage billed once
        $out[] = ['meta' => $meta, 'items' => $sellerItems, 'seller' => $sellerAcc,
                  'seller_key' => vestra_invoice_seller_key($sellerAcc)];
    }
    return $out;
}

/**
 * Manually issue (force) every per-seller invoice for an order ref — used by the
 * operator's "Issue invoice" action once stock is confirmed. Rebuilds the buyer
 * and per-seller line data from orders.csv (address recovered from the notes).
 * Idempotent: already-issued invoices are returned untouched. Returns the list.
 */
function vestra_issue_order_invoices(string $ref, bool $redraft = false): array {
    $issued = [];
    foreach (vestra_order_invoice_payloads($ref) as $p) {
        $iv = vestra_ensure_invoice($p['meta'], $p['items'], $p['seller'], true, $redraft);
        if (!empty($iv['no'])) $issued[] = $iv;
    }
    return $issued;
}
