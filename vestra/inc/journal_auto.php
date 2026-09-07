<?php
/**
 * VESTRA — GÜNLÜK OTOMATİK JOURNAL YAZISI (operatör, 7 Eyl 2026: *"her gün
 * otomatik journal e paylaşım yap, estetik ve ayrıntılı, bilgi verici ve işe
 * yarayan"*).
 *
 * NE YAZIYOR: yazının TAMAMI canlı ilan kaydından türer — hangi ürünler
 * eklendi, hangi markadan, hangi kategoride, hangi fiyat aralığında, MOQ'su ne,
 * kaç renk ve hangi bedenler. Tek bir cümlesi bile uydurulmuyor. Sunucuda dil
 * modeli yok; olsaydı bile moda yorumu yazdırmak bu depodaki "tahmin etme,
 * kayıttan oku" kuralının (KURAL 3) tam tersi olurdu. Bir toptancının okumak
 * istediği şey zaten yorum değil: **bu hafta ne geldi, kaça, kaç adetten.**
 *
 * NE ZAMAN YAZMIYOR — asıl karar bu. Her gün *çalışır*, ama yalnızca
 * söyleyecek gerçek bir şey varsa **yayımlar**:
 *   - yeni ilan yoksa ya da eşiğin altındaysa yazı YOK,
 *   - aynı gün zaten otomatik yazı çıktıysa ikincisi YOK.
 * Gerekçe iki katmanlı ve ikisi de bu dosyada yazılı olmalı: (1) KURAL 9 —
 * "stokta olmayan hiçbir şeye sayfa açılmaz, sahte/ince içerik alan adına zarar
 * verir"; her gün üretilen içi boş bir "bugün de buradayız" yazısı tam olarak
 * o ince içeriktir ve journal'ın arama değerini aşağı çeker. (2) KURAL 2c'nin
 * dersi — "her sabah '0 bekleyen' yazan bir uyarı, okunmamayı öğretir";
 * içeriksiz bir günlük yazı da okuyucuya journal'ı atlamayı öğretir.
 * Yani "her gün" ile "her gün YAYIN" aynı şey değil ve bu bilerek böyle.
 *
 * DİLLER — `t()` KULLANILMIYOR, bilerek. `vlang()` süreçte ilk çağrıda
 * sabitleniyor (CLAUDE.md, KURAL 11'in ölçüm tuzağı): tek bir cron sürecinde
 * dokuz dili `t()` ile dolaşmak dokuzunu da İngilizce yazardı ve kimse fark
 * etmezdi. Cümle parçaları bu yüzden dile göre kendi tablosundan geliyor
 * (`vestra_journal_auto_strings`), sayılar da her dilde aynı kayıttan.
 *
 * GÖVDE DÜZ METİN: `vestra_journal_body_html()` her şeyi kaçırıyor (escape),
 * boş satır = paragraf, ve tek bir yapı tanıyor: `[img:/uploads/…|başlık]`.
 * Markdown ÇALIŞMAZ. Kapak, raporda gerçekten geçen bir ürünün kendi fotoğrafı.
 */
require_once __DIR__.'/products.php';
require_once __DIR__.'/journal.php';

/** Yayım eşiği: bu kadar yeni ilan yoksa o gün yazı çıkmaz. */
const VESTRA_JOURNAL_AUTO_MIN = 3;
/** Rapor penceresi (gün). Günlük çalışır, pencere daha uzun: bir ürün hafta
 *  içinde eklendiyse haftanın raporunda görünmeli. */
const VESTRA_JOURNAL_AUTO_DAYS = 7;

/** Otomatik yazıları elle yazılanlardan ayıran damga. */
const VESTRA_JOURNAL_AUTO_FLAG = 'auto_stock_report';

/**
 * Dile göre cümle parçaları. Sayılar `%s`/`%d` ile giriyor; hiçbir rakam
 * metne gömülü değil (KURAL 6'nın escrow dersi: gömülü rakam bir gün kayıttan
 * ayrışır ve yalan söyler).
 */
function vestra_journal_auto_strings(string $lang): array {
    $en = [
        'title_one'   => 'New in stock: %1$s new lines from %2$s',
        'title_many'  => 'New in stock: %1$s new lines across %2$d brands',
        'excerpt'     => 'What landed in the VESTRA catalogue in the last %1$d days — brand by brand, with wholesale price bands, minimum order quantities and the sizes actually on the shelf.',
        'intro'       => 'This is an automatic stock report. Every figure below is read from the live listing at the moment of writing: nothing is estimated, and nothing is carried over from an earlier edition. Prices are wholesale, per piece, excluding VAT and carriage.',
        'brand_head'  => '%1$s — %2$d new %3$s',
        'line_price'  => 'Wholesale from %1$s per piece.',
        'line_moq'    => 'Minimum order %1$d pieces.',
        'line_sizes'  => 'Sizes %1$s.',
        'line_colors' => '%1$d colourways.',
        'line_ships'  => 'Ships from %1$s.',
        'cats_head'   => 'By category',
        'cats_line'   => '%1$s: %2$d new.',
        'links_head'  => 'Where to see them',
        'outro'       => 'Trade prices and the full line sheet are visible to approved trade accounts. If your account is open, the tiers on each product page are the ones that apply to your order.',
        'piece'       => 'piece', 'pieces' => 'pieces',
    ];
    $tr = [
        'de' => [
            'title_one'   => 'Neu im Lager: %1$s neue Modelle von %2$s',
            'title_many'  => 'Neu im Lager: %1$s neue Modelle aus %2$d Marken',
            'excerpt'     => 'Was in den letzten %1$d Tagen in den VESTRA-Katalog kam — Marke für Marke, mit Großhandelspreisspannen, Mindestbestellmengen und den tatsächlich verfügbaren Größen.',
            'intro'       => 'Dies ist ein automatischer Lagerbericht. Jede Zahl stammt aus dem Live-Eintrag im Moment der Erstellung: nichts ist geschätzt, nichts aus einer früheren Ausgabe übernommen. Preise sind Großhandelspreise pro Stück, ohne MwSt. und Fracht.',
            'brand_head'  => '%1$s — %2$d neue %3$s',
            'line_price'  => 'Großhandel ab %1$s pro Stück.',
            'line_moq'    => 'Mindestbestellung %1$d Stück.',
            'line_sizes'  => 'Größen %1$s.',
            'line_colors' => '%1$d Farbstellungen.',
            'line_ships'  => 'Versand ab %1$s.',
            'cats_head'   => 'Nach Kategorie',
            'cats_line'   => '%1$s: %2$d neu.',
            'links_head'  => 'Wo Sie sie sehen',
            'outro'       => 'Handelspreise und die vollständige Line Sheet sehen freigeschaltete Handelskonten. Ist Ihr Konto offen, gelten die Staffeln auf der jeweiligen Produktseite für Ihre Bestellung.',
            'piece' => 'Teil', 'pieces' => 'Teile',
        ],
        'fr' => [
            'title_one'   => 'Nouveau en stock : %1$s nouveaux modèles de %2$s',
            'title_many'  => 'Nouveau en stock : %1$s nouveaux modèles de %2$d marques',
            'excerpt'     => 'Ce qui est entré au catalogue VESTRA ces %1$d derniers jours — marque par marque, avec les fourchettes de prix de gros, les quantités minimales et les tailles réellement disponibles.',
            'intro'       => 'Ceci est un rapport de stock automatique. Chaque chiffre provient de la fiche en ligne au moment de la rédaction : rien n’est estimé, rien n’est repris d’une édition antérieure. Les prix sont des prix de gros, à la pièce, hors TVA et transport.',
            'brand_head'  => '%1$s — %2$d nouvelles %3$s',
            'line_price'  => 'Gros à partir de %1$s la pièce.',
            'line_moq'    => 'Commande minimum %1$d pièces.',
            'line_sizes'  => 'Tailles %1$s.',
            'line_colors' => '%1$d coloris.',
            'line_ships'  => 'Expédié depuis %1$s.',
            'cats_head'   => 'Par catégorie',
            'cats_line'   => '%1$s : %2$d nouveautés.',
            'links_head'  => 'Où les voir',
            'outro'       => 'Les prix professionnels et le line sheet complet sont visibles par les comptes professionnels validés. Si votre compte est ouvert, les paliers affichés sur chaque fiche produit sont ceux qui s’appliquent.',
            'piece' => 'pièce', 'pieces' => 'pièces',
        ],
        'es' => [
            'title_one'   => 'Nuevo en stock: %1$s modelos nuevos de %2$s',
            'title_many'  => 'Nuevo en stock: %1$s modelos nuevos de %2$d marcas',
            'excerpt'     => 'Lo que entró en el catálogo VESTRA en los últimos %1$d días — marca por marca, con franjas de precio mayorista, pedidos mínimos y las tallas realmente disponibles.',
            'intro'       => 'Este es un informe de stock automático. Cada cifra se lee del anuncio en vivo en el momento de redactarlo: nada se estima ni se arrastra de una edición anterior. Los precios son mayoristas, por pieza, sin IVA ni transporte.',
            'brand_head'  => '%1$s — %2$d %3$s nuevas',
            'line_price'  => 'Mayorista desde %1$s por pieza.',
            'line_moq'    => 'Pedido mínimo %1$d piezas.',
            'line_sizes'  => 'Tallas %1$s.',
            'line_colors' => '%1$d colores.',
            'line_ships'  => 'Se envía desde %1$s.',
            'cats_head'   => 'Por categoría',
            'cats_line'   => '%1$s: %2$d nuevas.',
            'links_head'  => 'Dónde verlas',
            'outro'       => 'Los precios profesionales y el line sheet completo son visibles para cuentas comerciales aprobadas. Si su cuenta está abierta, los tramos de cada ficha son los que se aplican.',
            'piece' => 'pieza', 'pieces' => 'piezas',
        ],
        'it' => [
            'title_one'   => 'Novità in stock: %1$s nuovi modelli di %2$s',
            'title_many'  => 'Novità in stock: %1$s nuovi modelli da %2$d marchi',
            'excerpt'     => 'Cosa è entrato nel catalogo VESTRA negli ultimi %1$d giorni — marchio per marchio, con fasce di prezzo all’ingrosso, minimi d’ordine e le taglie realmente disponibili.',
            'intro'       => 'Questo è un report di magazzino automatico. Ogni cifra è letta dall’annuncio dal vivo al momento della stesura: nulla è stimato, nulla riportato da un’edizione precedente. I prezzi sono all’ingrosso, al pezzo, IVA e trasporto esclusi.',
            'brand_head'  => '%1$s — %2$d nuovi %3$s',
            'line_price'  => 'Ingrosso da %1$s al pezzo.',
            'line_moq'    => 'Ordine minimo %1$d pezzi.',
            'line_sizes'  => 'Taglie %1$s.',
            'line_colors' => '%1$d varianti colore.',
            'line_ships'  => 'Spedizione da %1$s.',
            'cats_head'   => 'Per categoria',
            'cats_line'   => '%1$s: %2$d nuovi.',
            'links_head'  => 'Dove vederli',
            'outro'       => 'I prezzi commerciali e il line sheet completo sono visibili agli account business approvati. Se il tuo account è aperto, gli scaglioni su ogni scheda sono quelli che si applicano.',
            'piece' => 'pezzo', 'pieces' => 'pezzi',
        ],
        'pt' => [
            'title_one'   => 'Novo em stock: %1$s modelos novos de %2$s',
            'title_many'  => 'Novo em stock: %1$s modelos novos de %2$d marcas',
            'excerpt'     => 'O que entrou no catálogo VESTRA nos últimos %1$d dias — marca a marca, com escalões de preço grossista, quantidades mínimas e os tamanhos realmente disponíveis.',
            'intro'       => 'Este é um relatório de stock automático. Cada número é lido do anúncio em direto no momento da redação: nada é estimado nem transitado de uma edição anterior. Os preços são grossistas, por peça, sem IVA nem transporte.',
            'brand_head'  => '%1$s — %2$d %3$s novas',
            'line_price'  => 'Grossista desde %1$s por peça.',
            'line_moq'    => 'Encomenda mínima %1$d peças.',
            'line_sizes'  => 'Tamanhos %1$s.',
            'line_colors' => '%1$d cores.',
            'line_ships'  => 'Expedido de %1$s.',
            'cats_head'   => 'Por categoria',
            'cats_line'   => '%1$s: %2$d novas.',
            'links_head'  => 'Onde vê-las',
            'outro'       => 'Os preços profissionais e o line sheet completo são visíveis a contas comerciais aprovadas. Se a sua conta estiver aberta, os escalões de cada ficha são os que se aplicam.',
            'piece' => 'peça', 'pieces' => 'peças',
        ],
        'ru' => [
            'title_one'   => 'Новое на складе: %1$s новых моделей %2$s',
            'title_many'  => 'Новое на складе: %1$s новых моделей, %2$d брендов',
            'excerpt'     => 'Что поступило в каталог VESTRA за последние %1$d дней — по брендам, с оптовыми ценовыми диапазонами, минимальными заказами и реально доступными размерами.',
            'intro'       => 'Это автоматический складской отчёт. Каждая цифра считана из действующей карточки товара в момент составления: ничего не оценивается и не переносится из прошлого выпуска. Цены оптовые, за штуку, без НДС и доставки.',
            'brand_head'  => '%1$s — %2$d новых %3$s',
            'line_price'  => 'Опт от %1$s за штуку.',
            'line_moq'    => 'Минимальный заказ %1$d шт.',
            'line_sizes'  => 'Размеры %1$s.',
            'line_colors' => '%1$d цветов.',
            'line_ships'  => 'Отгрузка из %1$s.',
            'cats_head'   => 'По категориям',
            'cats_line'   => '%1$s: %2$d новых.',
            'links_head'  => 'Где посмотреть',
            'outro'       => 'Оптовые цены и полный line sheet видны одобренным торговым аккаунтам. Если ваш аккаунт открыт, действуют те градации, что указаны на странице товара.',
            'piece' => 'изделие', 'pieces' => 'изделий',
        ],
        'ar' => [
            'title_one'   => 'جديد في المخزون: %1$s موديلاً جديداً من %2$s',
            'title_many'  => 'جديد في المخزون: %1$s موديلاً جديداً من %2$d علامة',
            'excerpt'     => 'ما دخل كتالوج VESTRA خلال %1$d أيام الماضية — علامة بعلامة، مع نطاقات أسعار الجملة والحد الأدنى للطلب والمقاسات المتوفرة فعلاً.',
            'intro'       => 'هذا تقرير مخزون آلي. كل رقم مقروء من الإعلان الحي لحظة الكتابة: لا شيء مُقدَّر ولا منقول من عدد سابق. الأسعار أسعار جملة للقطعة، دون ضريبة القيمة المضافة والشحن.',
            'brand_head'  => '%1$s — %2$d %3$s جديدة',
            'line_price'  => 'الجملة تبدأ من %1$s للقطعة.',
            'line_moq'    => 'الحد الأدنى للطلب %1$d قطعة.',
            'line_sizes'  => 'المقاسات %1$s.',
            'line_colors' => '%1$d ألوان.',
            'line_ships'  => 'الشحن من %1$s.',
            'cats_head'   => 'حسب الفئة',
            'cats_line'   => '%1$s: %2$d جديدة.',
            'links_head'  => 'أين تراها',
            'outro'       => 'أسعار التجارة وورقة الخطوط الكاملة مرئية للحسابات التجارية المعتمدة. إذا كان حسابك مفتوحاً، فالشرائح المعروضة في صفحة المنتج هي المطبقة على طلبك.',
            'piece' => 'قطعة', 'pieces' => 'قطع',
        ],
        'ja' => [
            'title_one'   => '新入荷：%2$s より %1$s 型',
            'title_many'  => '新入荷：%2$d ブランド、%1$s 型',
            'excerpt'     => '過去 %1$d 日間に VESTRA カタログへ入荷した商品を、ブランド別に卸価格帯・最低発注数・実在庫のサイズとともに掲載します。',
            'intro'       => 'これは自動生成の在庫レポートです。以下の数値はすべて作成時点の掲載データから読み取っています。推定値はなく、前号からの引き継ぎもありません。価格は卸・1点あたり、税および送料は含みません。',
            'brand_head'  => '%1$s — 新着 %2$d 点（%3$s）',
            'line_price'  => '卸価格 1点 %1$s より。',
            'line_moq'    => '最低発注 %1$d 点。',
            'line_sizes'  => 'サイズ %1$s。',
            'line_colors' => 'カラー %1$d 色。',
            'line_ships'  => '出荷元：%1$s。',
            'cats_head'   => 'カテゴリー別',
            'cats_line'   => '%1$s：新着 %2$d 点。',
            'links_head'  => '掲載ページ',
            'outro'       => '卸価格と全ラインシートは承認済みの取引アカウントに表示されます。アカウントが有効であれば、各商品ページの価格帯がそのままご注文に適用されます。',
            'piece' => '点', 'pieces' => '点',
        ],
    ];
    $lang = strtolower(trim($lang));
    return $lang === 'en' || !isset($tr[$lang]) ? $en : ($tr[$lang] + $en);
}

/** Bir ilanın en düşük kademe fiyatı (yoksa `unit`/`list`). Para birimi EUR. */
function vestra_journal_auto_from_price(array $p): ?float {
    if (function_exists('vestra_from_price')) {
        $v = (float)vestra_from_price($p);
        if ($v > 0) return $v;
    }
    foreach (['unit', 'list'] as $f) {
        $v = (float)($p[$f] ?? 0);
        if ($v > 0) return $v;
    }
    return null;
}

/** Pencerede eklenmiş ilanlar, en yeniden eskiye. `added_at` yoksa ürün "yeni" sayılmaz. */
function vestra_journal_auto_new(int $days = VESTRA_JOURNAL_AUTO_DAYS, ?int $now = null, ?int $since = null): array {
    $now  = $now ?? time();
    $from = $now - $days * 86400;
    /* SON RAPORDAN BERİ. Sabit kayan pencere aynı ilanları bir hafta boyunca
       her sabah yeniden duyururdu: 3 Eylül'de giren 335 ayakkabı, 4-10 Eylül
       arasındaki her raporun içinde. Aynı içeriğin yedi kopyası okuyucuya da
       arama motoruna da aynı şeyi yedi kez söyler — bu işin kaçınmak için
       kurulduğu şeyin ta kendisi. Pencere bu yüzden son otomatik raporda
       BAŞLIYOR; `$days` yalnızca üst sınır (ilk rapor sonsuz geriye gitmesin). */
    if ($since !== null && $since > $from) $from = $since;
    $out  = [];
    foreach (vestra_products() as $p) {
        $t = strtotime((string)($p['added_at'] ?? ''));
        /* Damgasız ilan ATLANIYOR: tarihi bilinmeyeni "bugün geldi" saymak,
           kataloğun tamamını her gün yeniden "yeni" ilan etmek olurdu. */
        if (!$t || $t < $from || $t > $now + 86400) continue;
        $out[] = $p + ['_added_ts' => $t];
    }
    usort($out, fn($a, $b) => $b['_added_ts'] <=> $a['_added_ts']);
    return $out;
}

/** O gün zaten otomatik yazı çıktı mı? (cron'un yeniden çalışması ikinci yazı üretmesin) */
function vestra_journal_auto_today(?string $day = null): ?array {
    $day = $day ?? date('Y-m-d');
    foreach (vestra_journal_all() as $a) {
        if (($a['source'] ?? '') !== VESTRA_JOURNAL_AUTO_FLAG) continue;
        if (substr((string)($a['created'] ?? ''), 0, 10) === $day) return $a;
    }
    return null;
}

/** Son otomatik raporun zamanı (yoksa null) — pencerenin başlangıcı. */
function vestra_journal_auto_last_ts(): ?int {
    $best = null;
    foreach (vestra_journal_all() as $a) {
        if (($a['source'] ?? '') !== VESTRA_JOURNAL_AUTO_FLAG) continue;
        $t = strtotime((string)($a['created'] ?? ''));
        if ($t && ($best === null || $t > $best)) $best = $t;
    }
    return $best;
}

/**
 * Yazıyı kurar. SAF: diske hiçbir şey yazmaz, hiçbir şey yayımlamaz.
 * Döner: `['skip' => gerekçe]` ya da `vestra_journal_save()`'e verilebilecek kayıt.
 */
function vestra_journal_auto_build(?int $now = null, int $days = VESTRA_JOURNAL_AUTO_DAYS): array {
    $now   = $now ?? time();
    $since = vestra_journal_auto_last_ts();
    $new   = vestra_journal_auto_new($days, $now, $since);
    /* Metindeki "son %d gün" GERÇEK pencereyi söylemeli: son rapor dün çıktıysa
       "son 7 gün" yazmak, okuyucuya bir haftalık liste vaat edip bir günlük
       liste vermek olurdu. */
    $winFrom = max($now - $days * 86400, $since ?? 0);
    $days    = max(1, (int)ceil(($now - $winFrom) / 86400));
    if (count($new) < VESTRA_JOURNAL_AUTO_MIN) {
        /* İÇİ BOŞ YAZI YOK. Gerekçe yukarıda: ince içerik alan adına zarar
           verir (KURAL 9) ve okuyucuya journal'ı atlamayı öğretir (KURAL 2c). */
        return ['skip' => 'yeni ilan '.count($new).' (esik '.VESTRA_JOURNAL_AUTO_MIN.')'];
    }

    $byBrand = [];
    $byCat   = [];
    foreach ($new as $p) {
        $b = trim((string)($p['brand'] ?? '')) ?: '—';
        $c = trim((string)($p['cat'] ?? '')) ?: '—';
        $byBrand[$b][] = $p;
        $byCat[$c] = ($byCat[$c] ?? 0) + 1;
    }
    uasort($byBrand, fn($a, $b) => count($b) <=> count($a));
    arsort($byCat);

    $cover = '';
    foreach ($new as $p) {
        $img = function_exists('vestra_primary_image') ? (string)vestra_primary_image($p) : '';
        /* Kapak, raporda GERÇEKTEN geçen bir ürünün kendi fotoğrafı — konuyla
           ilgisiz bir stok görseli değil. */
        if ($img !== '' && str_starts_with($img, '/')) { $cover = $img; break; }
    }

    $out = ['i18n' => []];
    foreach (['en', 'de', 'fr', 'es', 'it', 'pt', 'ru', 'ar', 'ja'] as $lang) {
        $s = vestra_journal_auto_strings($lang);
        $brandCount = count($byBrand);
        $pieceWord  = count($new) === 1 ? $s['piece'] : $s['pieces'];
        $title = $brandCount === 1
            ? sprintf($s['title_one'], count($new), array_key_first($byBrand))
            : sprintf($s['title_many'], count($new), $brandCount);

        $para = [];
        $para[] = $s['intro'];

        foreach ($byBrand as $brand => $items) {
            $cats = [];
            foreach ($items as $p) { $c = trim((string)($p['cat'] ?? '')); if ($c !== '') $cats[$c] = true; }
            $head = sprintf($s['brand_head'], $brand, count($items), implode(' · ', array_keys($cats)) ?: $pieceWord);

            $prices = [];
            foreach ($items as $p) { $v = vestra_journal_auto_from_price($p); if ($v !== null) $prices[] = $v; }
            $moqs = array_filter(array_map(fn($p) => (int)($p['moq'] ?? 0), $items));
            $sizes = [];
            foreach ($items as $p) foreach ((array)($p['sizes'] ?? []) as $z) { $z = trim((string)$z); if ($z !== '') $sizes[$z] = true; }
            /* AYRI renkler — toplam DEĞİL. Önce her ilanın renk sayısı toplanıyordu:
               tek renkli 18 ilan "18 renk seçeneği" diye çıkıyordu. Şişirilmiş bir
               rakam, uydurulmuş bir rakamla aynı şeydir. */
            $colorSet = [];
            foreach ($items as $p) foreach ((array)($p['colors'] ?? []) as $cn) {
                $cn = trim((string)(is_array($cn) ? ($cn['name'] ?? '') : $cn));
                if ($cn !== '') $colorSet[mb_strtolower($cn)] = true;
            }
            $colors = count($colorSet);
            $ships = [];
            foreach ($items as $p) {
                $sf = function_exists('vestra_ships_from') ? trim((string)vestra_ships_from($p)) : '';
                if ($sf !== '') $ships[$sf] = true;
            }

            $lines = [$head];
            if ($prices) $lines[] = sprintf($s['line_price'], 'EUR '.number_format(min($prices), 2, '.', ','));
            if ($moqs)   $lines[] = sprintf($s['line_moq'], min($moqs));
            if ($sizes)  $lines[] = sprintf($s['line_sizes'], implode(', ', array_slice(array_keys($sizes), 0, 12)));
            if ($colors) $lines[] = sprintf($s['line_colors'], $colors);
            /* Gönderim yeri YALNIZCA hepsi aynı ve doluysa yazılıyor — KURAL 3:
               boş bir alanı doldurmak ya da iki depoyu tek satırda birleştirmek
               alıcının gümrük için okuduğu satırı yalan yapar. */
            if (count($ships) === 1) $lines[] = sprintf($s['line_ships'], array_key_first($ships));
            $para[] = implode("\n", $lines);
        }

        if (count($byCat) > 1) {
            $cl = [$s['cats_head']];
            foreach ($byCat as $c => $n) $cl[] = sprintf($s['cats_line'], $c, $n);
            $para[] = implode("\n", $cl);
        }

        /* İç bağlantılar YALNIZCA gerçekten çözülen sayfalara (KURAL 9): açılmayan
           bir /b2b/… adresine bağlantı vermek okuyucuyu 404'e yollamak olurdu. */
        $links = vestra_journal_auto_links(array_keys($byBrand), array_keys($byCat));
        if ($links) $para[] = implode("\n", array_merge([$s['links_head']], $links));

        $para[] = $s['outro'];

        $body = implode("\n\n", $para);
        if ($lang === 'en') {
            $out['title']    = $title;
            $out['excerpt']  = sprintf($s['excerpt'], $days);
            $out['body']     = $body;
        } else {
            $out['i18n'][$lang] = ['title' => $title, 'excerpt' => sprintf($s['excerpt'], $days), 'body' => $body];
        }
    }

    $out['category']  = 'Brand News';
    $out['author']    = 'VESTRA';
    $out['cover']     = $cover;
    $out['source']    = VESTRA_JOURNAL_AUTO_FLAG;
    $out['published'] = 1;
    /* Başlıkta tarih VAR: iki haftanın raporu aynı markaları taşıyabilir ve slug
       çakışması `-2` ekiyle çözülürdü — okunabilir bir adres bundan iyidir. */
    $out['slug'] = vestra_journal_slug('stock-report-'.date('Y-m-d', $now).'-'.$out['title']);
    return $out;
}

/** Rapordaki marka/kategoriler için GERÇEKTEN açılan iniş sayfaları. */
function vestra_journal_auto_links(array $brands, array $cats): array {
    if (!function_exists('vestra_seo_resolve')) {
        $f = __DIR__.'/seo.php';
        if (is_readable($f)) require_once $f; else return [];
    }
    $out = [];
    foreach ($brands as $b) {
        $slug = function_exists('vestra_seo_cat_slug') ? vestra_seo_cat_slug((string)$b) : '';
        if ($slug !== '' && vestra_seo_resolve($slug)) $out[] = $b.' — https://vestrasales.com/wholesale/'.$slug;
    }
    foreach ($cats as $c) {
        $slug = function_exists('vestra_seo_cat_slug') ? vestra_seo_cat_slug((string)$c) : '';
        if ($slug !== '' && vestra_seo_resolve($slug)) $out[] = $c.' — https://vestrasales.com/b2b/'.$slug;
    }
    return array_slice($out, 0, 10);
}
