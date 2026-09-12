<?php
/**
 * VESTRA — lookbook: SADECE marka, ad ve fotograf.
 *
 *   /lookbook.php?ids=mb-vs004,dsq-101213      istenen ilanlar, istenen SIRADA
 *
 * Operator, 12 Eyl 2026: "bunlari katalogtan bul ve fotolari ile birlikte pdf yap
 * sadece isim marka ve foto".
 *
 * NEDEN wholesale-list.php'nin bir KIPI DEGIL. O belge bir FIYAT LISTESI: her
 * satirda artikel no, beden serisi, MOQ, toptan fiyat, RRP ve stok var; kucuk bir
 * 46x74pt kucuk resim yalnizca modeli hatirlatmak icin duruyor. Buradaki belgenin
 * isi tam tersi -- fotograf asil icerik, yaninda yalnizca iki satir metin. Ayni
 * dosyaya "minimal" bayragi koymak, her satirin iki ayri duzende cizilmesi ve
 * fiyat sutunlarinin bir kosulla susturulmasi demekti; o dosya zaten fiyat
 * kapisini, RRP kuralini ve stok satirini tasiyor.
 * PAYLASILAN sey paylasiliyor: PDF yazici (inc/pdf.php), fotograf cozucu
 * (vestra_pdf_thumb) ve ilan kaydinin kendisi. Kopyalanan tek sey duzen.
 *
 * NE BILEREK YOK: fiyat, MOQ, beden, stok, satici, SKU. Operatorun cumlesi uc sey
 * sayiyor ve dordunculeri eklemek belgeyi istenmeyen bir sey yapardi -- fiyatsiz
 * bir sayfa her aliciya gosterilebilir, fiyatli bir sayfa gosterilemez.
 *
 * KAPI: web tarafinda YALNIZCA operator (admin oturumu). Fiyat tasimadigi icin
 * teknik olarak herkese acik da olabilirdi, ama kimsenin istemedigi yeni bir acik
 * uc acmak ayri bir karar; operatorun istedigi sey belgenin kendisi. CLI muaf --
 * sunucuda kabuk erisimi olan zaten dosyayi okuyabiliyor, ve olcum (kac fotograf
 * gomuldu) ancak CLI'dan yapilabiliyor.
 */
require_once __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/pdf.php';
require_once __DIR__.'/inc/auth.php';

$CLI = (PHP_SAPI === 'cli');
if (!$CLI) {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    if (empty($_SESSION['vadmin'])) { http_response_code(403); exit('forbidden'); }
}

/* CLI'da argumanlar `ids=a,b,c` seklinde geliyor; web tarafinda $_GET. */
$idsRaw = (string)($_GET['ids'] ?? '');
if ($CLI) {
    foreach (array_slice($argv, 1) as $a) {
        if (strpos($a, 'ids=') === 0) $idsRaw = substr($a, 4);
    }
}
$ids = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $idsRaw) ?: [])));

/* Ilanlar ID ile aranıyor ve UNLISTED de dahil (vestra_products(true)): id'yi
   operator acikca yaziyor, yani "katalogda gorunmuyor" ile "boyle bir ilan yok"
   ayri seyler ve ikincisini sessizce birincisi gibi gostermek yanlis olurdu. */
$byId = [];
foreach (vestra_products(true) as $p) {
    $id = (string)($p['id'] ?? '');
    if ($id !== '') $byId[$id] = $p;
}

/* Sira ISTENEN sira -- katalog sirasi degil. Operator urunleri bir sirayla
   yazdiysa belge de o sirada okunmali. */
$rows = []; $missing = [];
foreach ($ids as $id) {
    if (isset($byId[$id])) $rows[] = $byId[$id];
    else $missing[] = $id;
}

/* EKSIK ID SESSIZCE DUSMEZ. Dort urun isteyip uc urunluk bir PDF almak, belgenin
   kendisinde gorunmeyen bir kusur: operator dorduncunun fotografsiz oldugunu
   saniyor. CLI'da yaziliyor, web tarafinda hicbir sey uretilmiyor. */
if ($missing) {
    if ($CLI) fwrite(STDERR, "BULUNAMAYAN ID: ".implode(', ', $missing)."\n");
    else { http_response_code(404); exit('unknown id: '.htmlspecialchars(implode(', ', $missing))); }
}
if (!$rows) {
    if ($CLI) { fwrite(STDERR, "HATA: hicbir ilan cozulemedi\n"); exit(1); }
    http_response_code(400); exit('no products');
}

$pdf = new VestraPdf();

/* A4 595x842. Iki sutun, sayfa basina dort kart. Fotograf kutusu kartin
   %85'i: belgenin isi fotografi gostermek. */
$L = 40.0; $R = 555.0;
$GUT = 25.0;
$COL_W = ($R - $L - $GUT) / 2;          // 245
$IMG_H = 300.0;
$CARD_H = 352.0;                         // fotograf + iki satir metin + nefes
$TOP = 794.0;
$BOTTOM = 40.0;

$y = 0.0; $col = 0;

$header = function () use ($pdf, &$y, $L, $R, $TOP) {
    $pdf->text($L, $TOP, 12, 'VESTRA', true);
    $pdf->textR($R, $TOP, 8, date('j F Y'), false, 0.45);
    $pdf->line($L, $TOP - 8, $R, $TOP - 8, 0.8, 0.55);
    $y = $TOP - 30;
};

$pdf->addPage();
$header();

foreach ($rows as $i => $p) {
    if ($col === 0 && $y - $CARD_H < $BOTTOM) { $pdf->addPage(); $header(); }

    $x = $L + $col * ($COL_W + $GUT);
    $top = $y;

    /* Fotograf: ilanin ILK karesi. Ilan birden fazla renk tasiyabilir ama bu
       belge bir vitrin sayfasi, renk karti degil -- her rengi basmak operatorun
       "sadece ... foto" cumlesinin disina cikardi. */
    $srcs = array_values(array_filter(array_map('strval', (array)($p['images'] ?? []))));
    if (!$srcs && ($p['image'] ?? '') !== '') $srcs = [(string)$p['image']];
    $jpg = '';
    foreach ($srcs as $src) {
        /* vestra_pdf_thumb WEB yolu bekliyor ve dosya yolunu kendi kuruyor;
           mutlak yol verilirse yol ikiye katlanip SESSIZCE bos doner -- bu
           dosyada yillarca fotografsiz PDF uretilmesinin sebebi buydu. */
        $rel = '/'.ltrim(preg_replace('#^https?://[^/]+#', '', $src), '/');
        $jpg = vestra_pdf_thumb($rel, 900, 82);
        if ($jpg !== '') break;
    }

    $imgY = $top - $IMG_H;
    if ($jpg !== '' && $pdf->imageJpeg($jpg, $x, $imgY, $COL_W, $IMG_H, 4.0)) {
        // cizildi
    } else {
        /* Fotografsiz kart BOS BIRAKILMAZ, "fotograf yok" yazar: belgenin tek
           isi fotograf gostermek, ve eksik olani gizlemek operatore tam bir
           belge verdigimizi dusundururdu. */
        $pdf->rectFill($x, $imgY, $COL_W, $IMG_H, 0.94);
        $pdf->text($x + 10, $imgY + $IMG_H / 2 - 3, 8, 'no photo', false, 0.45);
    }

    $ty = $imgY - 16;
    $brand = trim((string)($p['brand'] ?? ''));
    if ($brand !== '') { $pdf->text($x, $ty, 7.5, mb_strtoupper($brand), false, 0.45); $ty -= 12; }

    /* Ad iki satira kadar sariliyor; daha uzunu kartin altini tasirdi ve
       tasan metin bir sonraki kartin fotografina binerdi. */
    $lines = $pdf->wrap((string)($p['name'] ?? ''), $COL_W, 10.5, true);
    foreach (array_slice($lines, 0, 2) as $ln) { $pdf->text($x, $ty, 10.5, $ln, true); $ty -= 13; }

    $col++;
    if ($col === 2) { $col = 0; $y -= $CARD_H; }
}

$pdfData = $pdf->output();

if ($CLI) {
    $out = getenv('LOOKBOOK_OUT') ?: (sys_get_temp_dir().'/vestra-lookbook.pdf');
    file_put_contents($out, $pdfData);
    printf("urun: %d   eksik id: %d\n", count($rows), count($missing));
    foreach ($rows as $p) {
        $srcs = array_values(array_filter(array_map('strval', (array)($p['images'] ?? []))));
        if (!$srcs && ($p['image'] ?? '') !== '') $srcs = [(string)$p['image']];
        printf("  %-28s | %-16s | %s | foto=%s\n",
            substr((string)($p['id'] ?? ''), 0, 28),
            substr((string)($p['brand'] ?? ''), 0, 16),
            (string)($p['name'] ?? ''),
            $srcs ? basename($srcs[0]) : '(YOK)');
    }
    /* Gomulu fotograf SAYILIYOR. "Dosya uretildi, boyut makul" bu depoda bir kez
       fotografsiz bir PDF'i yillarca gecirdi -- imza dogru, boyut makul, resim
       yok. Tek dogru olcum /DCTDecode sayimi. */
    printf("dosya: %s   %d bayt   gomulu fotograf: %d\n",
        $out, strlen($pdfData), substr_count($pdfData, '/DCTDecode'));
    exit(0);
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="vestra-lookbook-'.date('Y-m-d').'.pdf"');
header('Content-Length: '.strlen($pdfData));
header('X-Content-Type-Options: nosniff');
echo $pdfData;
