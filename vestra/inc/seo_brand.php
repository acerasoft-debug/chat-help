<?php
/**
 * VESTRA — marka ve kategori sayfalarinin SEO govdesi, 9 dilde.
 *
 * NEDEN (operator, 8 Eki 2026: "diger markalari da SEO'ya alalim, en yuksek ve en iyi
 * SEO'yu yap, butun dillerde"). Search Console ayni gun: "fornitore dsquared2" 227
 * gosterim, 0 tiklama -- Italyan alici "fornitore <marka>" yaziyor, sayfa baslikta
 * "<marka> ingrosso — fornitore B2B" diyordu ve 24 marka sayfasinin 24'u ayni kalip
 * metni tasiyordu (marka adi degisiyor, cumle degismiyor). Arama motoru bunu "ince
 * icerik" sayar ve hepsini birden geri iter.
 *
 * UC SEY DEGISIYOR:
 *  1. BASLIK ve ACIKLAMA her dilde o dilin KENDI kalibiyla ("Fornitore X all'ingrosso",
 *     "Grossiste X", "Mayorista X") -- sprintf ile kelime kelime cevrilmis bir Ingilizce
 *     cumle degil. Kalip burada, dil dosyasinda degil: kelime SIRASI dile gore degisiyor.
 *  2. MARKA PROFILI canli veriden: kategoriler, MOQ araligi, raftaki bedenler, renkler,
 *     paket adetleri, cikis ulkeleri, son gelen parti. Her markada FARKLI, cunku veri
 *     farkli -- ve hicbiri elle yazilmiyor (KURAL 3: bilinmeyen yazilmaz).
 *  3. SSS: alicinin aradigi sorular, o dilde, cevaplari canli veriden + kod sabitlerinden
 *     (escrow tavani VESTRA_ESCROW_MAX, talep suresi VESTRA_CLAIM_DAYS). FAQPage JSON-LD.
 *
 * Her metin 9 dilde burada; Ingilizce yedek. t() yalniz kategori ve renk adlari icin
 * (dil dosyalarinda zaten cevrili).
 */

/** Canli ilanlardan marka profili. $items = o markanin onayli ilanlari. */
function vestra_seo_brand_profile(array $items): array {
    $cats = function_exists('vestra_seo_count_cats') ? vestra_seo_count_cats($items) : [];
    $moqs = []; $sizes = []; $colours = []; $packs = []; $ships = []; $sellers = []; $newest = 0;
    $tiers = 0; $sale = 0; $offer = 0;
    foreach ($items as $p) {
        $m = (int)($p['moq'] ?? 0); if ($m > 0) $moqs[] = $m;
        if (function_exists('vestra_size_options')) {
            foreach (vestra_size_options($p) as $s) { $s = trim((string)$s); if ($s !== '') $sizes[$s] = true; }
        }
        foreach ((array)($p['colors'] ?? []) as $c) { $c = trim((string)$c); if ($c !== '') $colours[$c] = ($colours[$c] ?? 0) + 1; }
        $ps = (int)($p['size_step'] ?? 0); if ($ps > 1) $packs[$ps] = true;
        /* YALNIZ satici yazdiysa (KURAL 3): bos alanda vestra_ships_from() platform
           varsayilani 'EU' doner -- bunu "X'ten gonderilir" diye bir arama sonucuna
           yazmak dogrulanmamis bir beyan olurdu. */
        $sf = trim((string)($p['ships_from'] ?? ''));
        if ($sf !== '') { $sf = mb_strlen($sf) === 2 ? mb_strtoupper($sf) : $sf; $ships[$sf] = ($ships[$sf] ?? 0) + 1; }
        $su = trim((string)($p['seller_uid'] ?? '')); if ($su !== '') $sellers[$su] = true;
        foreach (['updated_at', 'added_at'] as $k) { $ts = (int)strtotime((string)($p[$k] ?? '')); if ($ts > $newest) $newest = $ts; }
        if (count((array)($p['tiers'] ?? [])) >= 2) $tiers++;
        if (($p['mode'] ?? '') === 'sale') $sale++;
        if (($p['mode'] ?? '') === 'offer' || !empty($p['offers'])) $offer++;
    }
    sort($moqs);
    /* Beden sirasi: harfli bedenler sabit sirada, sayisal bedenler kucukten buyuge,
       geri kalan alfabetik -- alici "S M L XL" bekler, "L M S XL" degil. */
    $order = ['XXXS' => 1, 'XXS' => 2, 'XS' => 3, 'S' => 4, 'M' => 5, 'L' => 6, 'XL' => 7, 'XXL' => 8, '2XL' => 8, 'XXXL' => 9, '3XL' => 9, '4XL' => 10, '5XL' => 11, 'ONE SIZE' => 99];
    $sz = array_keys($sizes);
    usort($sz, function ($a, $b) use ($order) {
        $ka = $order[strtoupper($a)] ?? (is_numeric($a) ? 50 + (float)$a / 1000 : 80);
        $kb = $order[strtoupper($b)] ?? (is_numeric($b) ? 50 + (float)$b / 1000 : 80);
        return $ka <=> $kb ?: strnatcasecmp($a, $b);
    });
    arsort($colours); arsort($ships);
    $pk = array_keys($packs); sort($pk);
    return [
        'n' => count($items), 'cats' => $cats,
        'moq_min' => $moqs ? $moqs[0] : 0, 'moq_max' => $moqs ? $moqs[count($moqs) - 1] : 0,
        'moq_median' => $moqs ? $moqs[intdiv(count($moqs), 2)] : 0,
        'sizes' => $sz, 'colours' => array_keys($colours), 'packs' => $pk, 'ships' => array_keys($ships),
        'sellers' => count($sellers), 'newest' => $newest, 'tiers' => $tiers, 'sale' => $sale, 'offer' => $offer,
    ];
}

/** Dil sozlugu: kaliplar + etiketler. {B} marka, {C} kategori, {N} sayi+ad, {n} ciplak sayi. */
function vestra_seo_brand_lang(string $lang): array {
    static $L = null;
    if ($L === null) $L = [
    'en' => [
        'one' => 'listing', 'many' => 'listings', 'pc' => 'pcs', 'and' => 'and',
        'title'     => '{B} wholesale supplier — {N} for retailers',
        'title_cat' => '{B} {C} wholesale — {N} for retailers',
        'title_c'   => '{C} wholesale — B2B supplier, {N}',
        'meta'      => '{B} wholesale on VESTRA: {N} for boutiques and retailers{CATS}. Minimum order from {MOQ} per listing{SHIPS}. Trade prices after free sign-up, B2B invoice, worldwide shipping.',
        'cats_in'   => ' ({CATLIST})', 'ships_from' => ', shipped from {SHIPLIST}',
        'glance'    => '{B} wholesale at a glance',
        'l_cats' => 'Categories in stock', 'l_moq' => 'Minimum order', 'l_sizes' => 'Sizes in stock', 'l_colours' => 'Colours',
        'l_packs' => 'Pack size', 'l_ships' => 'Ships from', 'l_newest' => 'Latest arrival', 'l_sellers' => 'Verified sellers',
        'l_tiers' => 'Volume pricing', 'l_tiers_v' => '{n} listings with quantity tiers', 'l_sale' => 'On sale', 'l_sale_v' => '{n} listings below list price',
        'per_listing' => '{MOQ} per listing', 'moq_range' => '{A}–{Z} {pc}', 'packs_v' => 'multiples of {P}',
        'faq_h'     => 'Frequently asked questions — {B} wholesale',
        'see_all'   => 'See all {n} {B} listings →',
        'faq' => [
            ['Where can I buy {B} wholesale?', 'On VESTRA, a B2B marketplace for retailers, where KYC-verified sellers currently offer {N} of {B}. Register a free trade account to see the trade prices and order directly from the listing, invoiced B2B.'],
            ['What is the minimum order for {B}?', 'Minimums are set per listing, not per brand: for {B} they currently run from {MOQMIN} to {MOQMAX} {pc}{PACKS}. A single boutique can order one listing without a distributor contract.'],
            ['Which {B} products are in stock?', 'Right now {N} across {NC} categories: {CATLIST}.{SIZES}{COLOURS}'],
            ['Where does the {B} stock ship from?', 'Each listing states the country the goods leave from — for {B} that is currently {SHIPLIST}. Orders are invoiced B2B and shipped worldwide; VAT is handled per your country.'],
            ['How do I see {B} trade prices?', 'Create a free trade account with your company name and VAT or trade registration number. Once the account is approved, wholesale prices, the line sheet (.xlsx) and ordering open on every listing.'],
            ['How is a {B} order paid?', 'By bank transfer against the invoice, or by card held in escrow for orders up to {ESCROW}: the money is released to the seller after delivery, and any issue can be reported within {CLAIM} business days.'],
            ['Is the {B} stock authentic?', 'Every seller is KYC-verified before a listing goes live and confirms on each listing that the goods are genuine, lawfully acquired and first placed on the EEA market with the brand owner\'s consent.'],
        ],
        'months' => ['January','February','March','April','May','June','July','August','September','October','November','December'],
    ],
    'it' => [
        'one' => 'referenza', 'many' => 'referenze', 'pc' => 'pz', 'and' => 'e',
        'title'     => 'Fornitore {B} all\'ingrosso — {N} per negozi',
        'title_cat' => '{B} {C} all\'ingrosso — fornitore B2B, {N}',
        'title_c'   => '{C} all\'ingrosso — fornitore B2B, {N}',
        'meta'      => '{B} all\'ingrosso su VESTRA: {N} per boutique e rivenditori{CATS}. Ordine minimo da {MOQ} per referenza{SHIPS}. Prezzi all\'ingrosso dopo la registrazione gratuita, fattura B2B, spedizione nel mondo.',
        'cats_in'   => ' ({CATLIST})', 'ships_from' => ', spedizione da {SHIPLIST}',
        'glance'    => '{B} all\'ingrosso in sintesi',
        'l_cats' => 'Categorie disponibili', 'l_moq' => 'Ordine minimo', 'l_sizes' => 'Taglie in stock', 'l_colours' => 'Colori',
        'l_packs' => 'Confezione', 'l_ships' => 'Spedizione da', 'l_newest' => 'Ultimo arrivo', 'l_sellers' => 'Venditori verificati',
        'l_tiers' => 'Prezzi a volume', 'l_tiers_v' => '{n} referenze con scaglioni di quantità', 'l_sale' => 'In saldo', 'l_sale_v' => '{n} referenze sotto il prezzo di listino',
        'per_listing' => '{MOQ} per referenza', 'moq_range' => '{A}–{Z} {pc}', 'packs_v' => 'multipli di {P}',
        'faq_h'     => 'Domande frequenti — {B} all\'ingrosso',
        'see_all'   => 'Vedi tutte le {n} referenze {B} →',
        'faq' => [
            ['Dove si compra {B} all\'ingrosso?', 'Su VESTRA, marketplace B2B per rivenditori: al momento {N} {B} di venditori verificati KYC. Registri un account professionale gratuito, vedi i prezzi all\'ingrosso e ordini direttamente dalla referenza, con fattura B2B.'],
            ['Qual è l\'ordine minimo per {B}?', 'Il minimo è per referenza, non per marchio: per {B} va oggi da {MOQMIN} a {MOQMAX} {pc}{PACKS}. Una singola boutique può ordinare una referenza senza contratto di distribuzione.'],
            ['Quali prodotti {B} sono disponibili?', 'In questo momento {N} in {NC} categorie: {CATLIST}.{SIZES}{COLOURS}'],
            ['Da dove viene spedita la merce {B}?', 'Ogni referenza indica il paese di partenza della merce: per {B} oggi {SHIPLIST}. Gli ordini sono fatturati B2B e spediti in tutto il mondo; l\'IVA è gestita secondo il tuo paese.'],
            ['Come vedo i prezzi all\'ingrosso {B}?', 'Crea un account professionale gratuito con ragione sociale e partita IVA o numero di registro. Dopo l\'approvazione dell\'account, prezzi all\'ingrosso, listino (.xlsx) e ordine si aprono su ogni referenza.'],
            ['Come si paga un ordine {B}?', 'Con bonifico a fronte di fattura, oppure con carta in escrow per ordini fino a {ESCROW}: il denaro viene liberato al venditore dopo la consegna e qualsiasi problema può essere segnalato entro {CLAIM} giorni lavorativi.'],
            ['La merce {B} è originale?', 'Ogni venditore è verificato KYC prima della pubblicazione e dichiara su ogni referenza che la merce è originale, acquisita legalmente e immessa nel mercato SEE con il consenso del titolare del marchio.'],
        ],
        'months' => ['gennaio','febbraio','marzo','aprile','maggio','giugno','luglio','agosto','settembre','ottobre','novembre','dicembre'],
    ],
    'fr' => [
        'one' => 'référence', 'many' => 'références', 'pc' => 'pcs', 'and' => 'et',
        'title'     => 'Grossiste {B} — fournisseur B2B, {N} pour boutiques',
        'title_cat' => '{B} {C} en gros — grossiste B2B, {N}',
        'title_c'   => '{C} en gros — fournisseur B2B, {N}',
        'meta'      => '{B} en gros sur VESTRA : {N} pour boutiques et détaillants{CATS}. Minimum de commande dès {MOQ} par référence{SHIPS}. Prix pro après inscription gratuite, facture B2B, livraison mondiale.',
        'cats_in'   => ' ({CATLIST})', 'ships_from' => ', expédié depuis {SHIPLIST}',
        'glance'    => '{B} en gros en un coup d\'œil',
        'l_cats' => 'Catégories en stock', 'l_moq' => 'Minimum de commande', 'l_sizes' => 'Tailles en stock', 'l_colours' => 'Coloris',
        'l_packs' => 'Conditionnement', 'l_ships' => 'Expédié depuis', 'l_newest' => 'Dernier arrivage', 'l_sellers' => 'Vendeurs vérifiés',
        'l_tiers' => 'Prix dégressifs', 'l_tiers_v' => '{n} références avec paliers de quantité', 'l_sale' => 'En promotion', 'l_sale_v' => '{n} références sous le prix catalogue',
        'per_listing' => '{MOQ} par référence', 'moq_range' => '{A}–{Z} {pc}', 'packs_v' => 'par multiples de {P}',
        'faq_h'     => 'Questions fréquentes — {B} en gros',
        'see_all'   => 'Voir les {n} références {B} →',
        'faq' => [
            ['Où acheter {B} en gros ?', 'Sur VESTRA, place de marché B2B pour détaillants : {N} {B} actuellement proposées par des vendeurs vérifiés KYC. Créez un compte professionnel gratuit pour voir les prix de gros et commander directement depuis la référence, avec facture B2B.'],
            ['Quel est le minimum de commande pour {B} ?', 'Le minimum est fixé par référence, pas par marque : pour {B} il va aujourd\'hui de {MOQMIN} à {MOQMAX} {pc}{PACKS}. Une seule boutique peut commander une référence sans contrat de distribution.'],
            ['Quels produits {B} sont disponibles ?', 'Actuellement {N} dans {NC} catégories : {CATLIST}.{SIZES}{COLOURS}'],
            ['D\'où est expédiée la marchandise {B} ?', 'Chaque référence indique le pays de départ de la marchandise : pour {B}, aujourd\'hui {SHIPLIST}. Les commandes sont facturées B2B et expédiées dans le monde entier ; la TVA est traitée selon votre pays.'],
            ['Comment voir les prix de gros {B} ?', 'Créez un compte professionnel gratuit avec votre raison sociale et votre numéro de TVA ou de registre du commerce. Une fois le compte approuvé, les prix de gros, la fiche tarifaire (.xlsx) et la commande s\'ouvrent sur chaque référence.'],
            ['Comment payer une commande {B} ?', 'Par virement sur facture, ou par carte avec séquestre pour les commandes jusqu\'à {ESCROW} : l\'argent est libéré au vendeur après la livraison et tout problème peut être signalé sous {CLAIM} jours ouvrés.'],
            ['La marchandise {B} est-elle authentique ?', 'Chaque vendeur est vérifié KYC avant la mise en ligne et confirme sur chaque référence que la marchandise est authentique, acquise légalement et mise sur le marché de l\'EEE avec l\'accord du titulaire de la marque.'],
        ],
        'months' => ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'],
    ],
    'de' => [
        'one' => 'Angebot', 'many' => 'Angebote', 'pc' => 'Stück', 'and' => 'und',
        'title'     => '{B} Großhandel — B2B-Lieferant, {N} für Händler',
        'title_cat' => '{B} {C} Großhandel — {N} für Händler',
        'title_c'   => '{C} Großhandel — B2B-Lieferant, {N}',
        'meta'      => '{B} Großhandel auf VESTRA: {N} für Boutiquen und Einzelhändler{CATS}. Mindestbestellmenge ab {MOQ} je Angebot{SHIPS}. Händlerpreise nach kostenloser Anmeldung, B2B-Rechnung, weltweiter Versand.',
        'cats_in'   => ' ({CATLIST})', 'ships_from' => ', Versand aus {SHIPLIST}',
        'glance'    => '{B} Großhandel auf einen Blick',
        'l_cats' => 'Kategorien auf Lager', 'l_moq' => 'Mindestbestellmenge', 'l_sizes' => 'Größen auf Lager', 'l_colours' => 'Farben',
        'l_packs' => 'Packungsgröße', 'l_ships' => 'Versand aus', 'l_newest' => 'Letzter Wareneingang', 'l_sellers' => 'Verifizierte Verkäufer',
        'l_tiers' => 'Staffelpreise', 'l_tiers_v' => '{n} Angebote mit Mengenstaffeln', 'l_sale' => 'Reduziert', 'l_sale_v' => '{n} Angebote unter Listenpreis',
        'per_listing' => '{MOQ} je Angebot', 'moq_range' => '{A}–{Z} {pc}', 'packs_v' => 'Vielfache von {P}',
        'faq_h'     => 'Häufige Fragen — {B} Großhandel',
        'see_all'   => 'Alle {n} {B}-Angebote ansehen →',
        'faq' => [
            ['Wo kann ich {B} im Großhandel kaufen?', 'Auf VESTRA, einem B2B-Marktplatz für Einzelhändler: derzeit {N} von {B}, angeboten von KYC-verifizierten Verkäufern. Mit einem kostenlosen Händlerkonto sehen Sie die Händlerpreise und bestellen direkt aus dem Angebot, mit B2B-Rechnung.'],
            ['Wie hoch ist die Mindestbestellmenge für {B}?', 'Die Mindestmenge gilt je Angebot, nicht je Marke: bei {B} liegt sie derzeit zwischen {MOQMIN} und {MOQMAX} {pc}{PACKS}. Eine einzelne Boutique kann ein Angebot ohne Distributionsvertrag bestellen.'],
            ['Welche {B}-Produkte sind auf Lager?', 'Aktuell {N} in {NC} Kategorien: {CATLIST}.{SIZES}{COLOURS}'],
            ['Von wo wird die {B}-Ware versendet?', 'Jedes Angebot nennt das Land, aus dem die Ware versendet wird — bei {B} derzeit {SHIPLIST}. Bestellungen werden per B2B-Rechnung abgewickelt und weltweit versendet; die Umsatzsteuer richtet sich nach Ihrem Land.'],
            ['Wie sehe ich die {B}-Händlerpreise?', 'Legen Sie ein kostenloses Händlerkonto mit Firmenname und USt-IdNr. oder Handelsregisternummer an. Nach der Freischaltung sind Großhandelspreise, Preisliste (.xlsx) und Bestellung in jedem Angebot offen.'],
            ['Wie wird eine {B}-Bestellung bezahlt?', 'Per Überweisung gegen Rechnung oder per Karte mit Treuhand für Bestellungen bis {ESCROW}: Das Geld wird nach der Lieferung an den Verkäufer freigegeben, Probleme können innerhalb von {CLAIM} Werktagen gemeldet werden.'],
            ['Ist die {B}-Ware original?', 'Jeder Verkäufer wird vor der Veröffentlichung KYC-verifiziert und bestätigt bei jedem Angebot, dass die Ware original, rechtmäßig erworben und mit Zustimmung des Markeninhabers im EWR in Verkehr gebracht wurde.'],
        ],
        'months' => ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'],
    ],
    'es' => [
        'one' => 'referencia', 'many' => 'referencias', 'pc' => 'uds', 'and' => 'y',
        'title'     => 'Mayorista {B} — proveedor B2B, {N} para tiendas',
        'title_cat' => '{B} {C} al por mayor — {N} para tiendas',
        'title_c'   => '{C} al por mayor — proveedor B2B, {N}',
        'meta'      => '{B} al por mayor en VESTRA: {N} para boutiques y minoristas{CATS}. Pedido mínimo desde {MOQ} por referencia{SHIPS}. Precios de mayorista tras registro gratuito, factura B2B, envío mundial.',
        'cats_in'   => ' ({CATLIST})', 'ships_from' => ', envío desde {SHIPLIST}',
        'glance'    => '{B} al por mayor de un vistazo',
        'l_cats' => 'Categorías en stock', 'l_moq' => 'Pedido mínimo', 'l_sizes' => 'Tallas en stock', 'l_colours' => 'Colores',
        'l_packs' => 'Pack', 'l_ships' => 'Envío desde', 'l_newest' => 'Última entrada', 'l_sellers' => 'Vendedores verificados',
        'l_tiers' => 'Precios por volumen', 'l_tiers_v' => '{n} referencias con tramos de cantidad', 'l_sale' => 'En oferta', 'l_sale_v' => '{n} referencias por debajo del precio de lista',
        'per_listing' => '{MOQ} por referencia', 'moq_range' => '{A}–{Z} {pc}', 'packs_v' => 'múltiplos de {P}',
        'faq_h'     => 'Preguntas frecuentes — {B} al por mayor',
        'see_all'   => 'Ver las {n} referencias {B} →',
        'faq' => [
            ['¿Dónde comprar {B} al por mayor?', 'En VESTRA, marketplace B2B para minoristas: ahora mismo {N} de {B} de vendedores verificados KYC. Crea una cuenta profesional gratuita para ver los precios de mayorista y pedir directamente desde la referencia, con factura B2B.'],
            ['¿Cuál es el pedido mínimo de {B}?', 'El mínimo se fija por referencia, no por marca: para {B} va hoy de {MOQMIN} a {MOQMAX} {pc}{PACKS}. Una sola tienda puede pedir una referencia sin contrato de distribución.'],
            ['¿Qué productos {B} hay en stock?', 'Ahora mismo {N} en {NC} categorías: {CATLIST}.{SIZES}{COLOURS}'],
            ['¿Desde dónde se envía la mercancía {B}?', 'Cada referencia indica el país desde el que sale la mercancía: para {B}, hoy {SHIPLIST}. Los pedidos se facturan B2B y se envían a todo el mundo; el IVA se gestiona según tu país.'],
            ['¿Cómo veo los precios de mayorista de {B}?', 'Crea una cuenta profesional gratuita con la razón social y el NIF/IVA o número de registro mercantil. Una vez aprobada la cuenta, los precios de mayorista, la lista de precios (.xlsx) y el pedido se abren en cada referencia.'],
            ['¿Cómo se paga un pedido {B}?', 'Por transferencia contra factura, o con tarjeta en depósito de garantía para pedidos de hasta {ESCROW}: el dinero se libera al vendedor tras la entrega y cualquier incidencia puede comunicarse en {CLAIM} días laborables.'],
            ['¿La mercancía {B} es original?', 'Cada vendedor pasa una verificación KYC antes de publicar y confirma en cada referencia que la mercancía es original, adquirida legalmente y comercializada en el EEE con el consentimiento del titular de la marca.'],
        ],
        'months' => ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'],
    ],
    'pt' => [
        'one' => 'referência', 'many' => 'referências', 'pc' => 'un.', 'and' => 'e',
        'title'     => '{B} por grosso — fornecedor B2B, {N} para lojas',
        'title_cat' => '{B} {C} por grosso — {N} para lojas',
        'title_c'   => '{C} por grosso — fornecedor B2B, {N}',
        'meta'      => '{B} por grosso na VESTRA: {N} para boutiques e retalhistas{CATS}. Encomenda mínima desde {MOQ} por referência{SHIPS}. Preços de revenda após registo gratuito, fatura B2B, envio mundial.',
        'cats_in'   => ' ({CATLIST})', 'ships_from' => ', enviado de {SHIPLIST}',
        'glance'    => '{B} por grosso em resumo',
        'l_cats' => 'Categorias em stock', 'l_moq' => 'Encomenda mínima', 'l_sizes' => 'Tamanhos em stock', 'l_colours' => 'Cores',
        'l_packs' => 'Embalagem', 'l_ships' => 'Enviado de', 'l_newest' => 'Última entrada', 'l_sellers' => 'Vendedores verificados',
        'l_tiers' => 'Preços por volume', 'l_tiers_v' => '{n} referências com escalões de quantidade', 'l_sale' => 'Em promoção', 'l_sale_v' => '{n} referências abaixo do preço de tabela',
        'per_listing' => '{MOQ} por referência', 'moq_range' => '{A}–{Z} {pc}', 'packs_v' => 'múltiplos de {P}',
        'faq_h'     => 'Perguntas frequentes — {B} por grosso',
        'see_all'   => 'Ver as {n} referências {B} →',
        'faq' => [
            ['Onde comprar {B} por grosso?', 'Na VESTRA, marketplace B2B para retalhistas: neste momento {N} {B} de vendedores verificados KYC. Crie uma conta profissional gratuita para ver os preços de revenda e encomendar diretamente a partir da referência, com fatura B2B.'],
            ['Qual é a encomenda mínima para {B}?', 'O mínimo é definido por referência, não por marca: para {B} vai hoje de {MOQMIN} a {MOQMAX} {pc}{PACKS}. Uma única loja pode encomendar uma referência sem contrato de distribuição.'],
            ['Que produtos {B} estão em stock?', 'Neste momento {N} em {NC} categorias: {CATLIST}.{SIZES}{COLOURS}'],
            ['De onde é enviada a mercadoria {B}?', 'Cada referência indica o país de onde a mercadoria é enviada: para {B}, hoje {SHIPLIST}. As encomendas são faturadas B2B e enviadas para todo o mundo; o IVA é tratado segundo o seu país.'],
            ['Como vejo os preços de revenda {B}?', 'Crie uma conta profissional gratuita com a denominação social e o NIF ou número de registo comercial. Depois da aprovação da conta, os preços de revenda, a lista de preços (.xlsx) e a encomenda abrem em cada referência.'],
            ['Como se paga uma encomenda {B}?', 'Por transferência contra fatura, ou com cartão em depósito de garantia para encomendas até {ESCROW}: o dinheiro é libertado ao vendedor após a entrega e qualquer problema pode ser comunicado em {CLAIM} dias úteis.'],
            ['A mercadoria {B} é original?', 'Cada vendedor é verificado KYC antes da publicação e confirma em cada referência que a mercadoria é original, adquirida legalmente e colocada no mercado do EEE com o consentimento do titular da marca.'],
        ],
        'months' => ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'],
    ],
    'ru' => [
        'one' => 'позиция', 'many' => 'позиций', 'few' => 'позиции', 'pc' => 'шт.', 'and' => 'и',
        'title'     => '{B} оптом — поставщик B2B, {N} для магазинов',
        'title_cat' => '{B} {C} оптом — {N} для магазинов',
        'title_c'   => '{C} оптом — поставщик B2B, {N}',
        'meta'      => '{B} оптом на VESTRA: {N} для бутиков и розничных магазинов{CATS}. Минимальный заказ от {MOQ} на позицию{SHIPS}. Оптовые цены после бесплатной регистрации, счёт B2B, доставка по миру.',
        'cats_in'   => ' ({CATLIST})', 'ships_from' => ', отправка из {SHIPLIST}',
        'glance'    => '{B} оптом — коротко',
        'l_cats' => 'Категории в наличии', 'l_moq' => 'Минимальный заказ', 'l_sizes' => 'Размеры в наличии', 'l_colours' => 'Цвета',
        'l_packs' => 'Упаковка', 'l_ships' => 'Отправка из', 'l_newest' => 'Последнее поступление', 'l_sellers' => 'Проверенные продавцы',
        'l_tiers' => 'Цены от объёма', 'l_tiers_v' => '{n} позиций со ступенями по количеству', 'l_sale' => 'Со скидкой', 'l_sale_v' => '{n} позиций ниже прайса',
        'per_listing' => '{MOQ} на позицию', 'moq_range' => '{A}–{Z} {pc}', 'packs_v' => 'кратно {P}',
        'faq_h'     => 'Частые вопросы — {B} оптом',
        'see_all'   => 'Все позиции {B} ({n}) →',
        'faq' => [
            ['Где купить {B} оптом?', 'На VESTRA — B2B-маркетплейсе для розницы: сейчас {N} {B} от проверенных (KYC) продавцов. Зарегистрируйте бесплатный торговый аккаунт, чтобы видеть оптовые цены и заказывать прямо из карточки, со счётом B2B.'],
            ['Какой минимальный заказ на {B}?', 'Минимум задаётся на позицию, а не на бренд: у {B} сейчас от {MOQMIN} до {MOQMAX} {pc}{PACKS}. Один магазин может заказать одну позицию без дистрибьюторского договора.'],
            ['Какие товары {B} есть в наличии?', 'Сейчас {N} в {NC} категориях: {CATLIST}.{SIZES}{COLOURS}'],
            ['Откуда отправляется товар {B}?', 'В каждой позиции указана страна отправки — у {B} сейчас {SHIPLIST}. Заказы оформляются со счётом B2B и доставляются по всему миру; НДС — по правилам вашей страны.'],
            ['Как увидеть оптовые цены {B}?', 'Создайте бесплатный торговый аккаунт с названием компании и номером НДС или регистрационным номером. После одобрения аккаунта в каждой позиции открываются оптовые цены, прайс-лист (.xlsx) и заказ.'],
            ['Как оплачивается заказ {B}?', 'Банковским переводом по счёту или картой с эскроу для заказов до {ESCROW}: деньги переводятся продавцу после доставки, а о проблеме можно сообщить в течение {CLAIM} рабочих дней.'],
            ['Товар {B} оригинальный?', 'Каждый продавец проходит проверку KYC до публикации и подтверждает в каждой позиции, что товар оригинальный, приобретён законно и введён в оборот в ЕЭЗ с согласия правообладателя.'],
        ],
        'months' => ['январь','февраль','март','апрель','май','июнь','июль','август','сентябрь','октябрь','ноябрь','декабрь'],
    ],
    'ar' => [
        'one' => 'منتج', 'many' => 'منتجاً', 'pc' => 'قطع', 'and' => 'و',
        'title'     => '{B} بالجملة — مورد B2B، {N} للمتاجر',
        'title_cat' => '{B} {C} بالجملة — {N} للمتاجر',
        'title_c'   => '{C} بالجملة — مورد B2B، {N}',
        'meta'      => '{B} بالجملة على VESTRA: {N} للبوتيكات وتجار التجزئة{CATS}. الحد الأدنى للطلب من {MOQ} لكل منتج{SHIPS}. أسعار الجملة بعد التسجيل المجاني، فاتورة B2B، شحن إلى جميع أنحاء العالم.',
        'cats_in'   => ' ({CATLIST})', 'ships_from' => '، يُشحن من {SHIPLIST}',
        'glance'    => '{B} بالجملة في لمحة',
        'l_cats' => 'الفئات المتوفرة', 'l_moq' => 'الحد الأدنى للطلب', 'l_sizes' => 'المقاسات المتوفرة', 'l_colours' => 'الألوان',
        'l_packs' => 'حجم العبوة', 'l_ships' => 'يُشحن من', 'l_newest' => 'آخر وصول', 'l_sellers' => 'بائعون موثّقون',
        'l_tiers' => 'أسعار الكمية', 'l_tiers_v' => '{n} منتجاً بشرائح كمية', 'l_sale' => 'تخفيضات', 'l_sale_v' => '{n} منتجاً دون سعر القائمة',
        'per_listing' => '{MOQ} لكل منتج', 'moq_range' => '{A}–{Z} {pc}', 'packs_v' => 'مضاعفات {P}',
        'faq_h'     => 'الأسئلة الشائعة — {B} بالجملة',
        'see_all'   => 'عرض كل منتجات {B} ({n}) →',
        'faq' => [
            ['أين أشتري {B} بالجملة؟', 'على VESTRA، سوق B2B لتجار التجزئة: حالياً {N} من {B} لدى بائعين موثّقين (KYC). سجّل حساباً تجارياً مجانياً لترى أسعار الجملة وتطلب مباشرة من المنتج بفاتورة B2B.'],
            ['ما الحد الأدنى لطلب {B}؟', 'يُحدَّد الحد الأدنى لكل منتج لا لكل علامة: لدى {B} حالياً من {MOQMIN} إلى {MOQMAX} {pc}{PACKS}. يمكن لمتجر واحد طلب منتج واحد دون عقد توزيع.'],
            ['ما منتجات {B} المتوفرة؟', 'حالياً {N} في {NC} فئات: {CATLIST}.{SIZES}{COLOURS}'],
            ['من أين تُشحن بضاعة {B}؟', 'يذكر كل منتج بلد الشحن — لدى {B} حالياً {SHIPLIST}. تُصدر الطلبات بفاتورة B2B وتُشحن إلى جميع أنحاء العالم؛ تُعامَل ضريبة القيمة المضافة حسب بلدك.'],
            ['كيف أرى أسعار جملة {B}؟', 'أنشئ حساباً تجارياً مجانياً باسم الشركة ورقم الضريبة أو السجل التجاري. بعد اعتماد الحساب تظهر أسعار الجملة وقائمة الأسعار (.xlsx) وإمكانية الطلب في كل منتج.'],
            ['كيف يُدفع طلب {B}؟', 'بتحويل بنكي مقابل الفاتورة، أو ببطاقة مع ضمان (escrow) للطلبات حتى {ESCROW}: يُحوَّل المبلغ إلى البائع بعد التسليم، ويمكن الإبلاغ عن أي مشكلة خلال {CLAIM} أيام عمل.'],
            ['هل بضاعة {B} أصلية؟', 'يخضع كل بائع لتحقق KYC قبل النشر ويؤكد في كل منتج أن البضاعة أصلية ومُقتناة قانونياً وطُرحت في سوق المنطقة الاقتصادية الأوروبية بموافقة مالك العلامة.'],
        ],
        'months' => ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'],
    ],
    'ja' => [
        'one' => '点', 'many' => '点', 'pc' => '点', 'and' => '・', 'nospace' => true,
        'title'     => '{B} 卸売 — B2B仕入れ、小売店向け{N}',
        'title_cat' => '{B} {C} 卸売 — 小売店向け{N}',
        'title_c'   => '{C} 卸売 — B2B仕入れ、{N}',
        'meta'      => 'VESTRAの{B}卸売：ブティック・小売店向け{N}{CATS}。最小ロットは1商品あたり{MOQ}から{SHIPS}。無料登録後に卸価格を表示、B2B請求書払い、全世界へ発送。',
        'cats_in'   => '（{CATLIST}）', 'ships_from' => '、{SHIPLIST}から発送',
        'glance'    => '{B}卸売の概要',
        'l_cats' => '在庫カテゴリー', 'l_moq' => '最小ロット', 'l_sizes' => '在庫サイズ', 'l_colours' => 'カラー',
        'l_packs' => 'パック単位', 'l_ships' => '発送元', 'l_newest' => '最新入荷', 'l_sellers' => '認証済み販売者',
        'l_tiers' => '数量別価格', 'l_tiers_v' => '数量段階価格あり：{n}商品', 'l_sale' => 'セール', 'l_sale_v' => '定価より安い商品：{n}点',
        'per_listing' => '1商品あたり{MOQ}', 'moq_range' => '{A}〜{Z}{pc}', 'packs_v' => '{P}の倍数',
        'faq_h'     => 'よくある質問 — {B}卸売',
        'see_all'   => '{B}の全{n}商品を見る →',
        'faq' => [
            ['{B}を卸売で仕入れるには？', '小売店向けB2BマーケットプレイスVESTRAで仕入れられます。{B}はKYC認証済みの販売者が{N}を出品しています。無料の事業者アカウントを登録すると卸価格が表示され、商品ページから直接B2B請求書払いで注文できます。'],
            ['{B}の最小ロットは？', '最小ロットはブランド単位ではなく商品単位です。{B}は現在{MOQMIN}〜{MOQMAX}{pc}{PACKS}。1店舗でも代理店契約なしで1商品から注文できます。'],
            ['{B}のどの商品が在庫にありますか？', '現在{NC}カテゴリーで{N}：{CATLIST}。{SIZES}{COLOURS}'],
            ['{B}の商品はどこから発送されますか？', '各商品に発送元の国を明記しています。{B}は現在{SHIPLIST}。注文はB2B請求書で処理され、全世界へ発送します。付加価値税はお客様の国の規定に従います。'],
            ['{B}の卸価格はどうすれば見られますか？', '会社名と税番号または商業登記番号で無料の事業者アカウントを作成してください。承認後、各商品で卸価格・価格表（.xlsx）・注文が利用できます。'],
            ['{B}の注文の支払い方法は？', '請求書に対する銀行振込、または{ESCROW}までの注文はカード決済（エスクロー）。代金は納品後に販売者へ送金され、問題があれば納品から{CLAIM}営業日以内に申告できます。'],
            ['{B}の商品は正規品ですか？', 'すべての販売者は出品前にKYC認証を受け、各商品について正規品であること、適法に取得したこと、ブランド権利者の同意のもとEEA市場に投入されたことを確認しています。'],
        ],
        'months' => ['1月','2月','3月','4月','5月','6月','7月','8月','9月','10月','11月','12月'],
    ],
    ];
    return $L[$lang] ?? $L['en'];
}

/** "{n} listings" dil ve sayiya gore. */
function vestra_seo_brand_count(int $n, string $lang): string {
    $L = vestra_seo_brand_lang($lang);
    if ($lang === 'ru') {
        $m10 = $n % 10; $m100 = $n % 100;
        $w = ($m10 === 1 && $m100 !== 11) ? $L['one'] : (($m10 >= 2 && $m10 <= 4 && ($m100 < 12 || $m100 > 14)) ? $L['few'] : $L['many']);
    } elseif ($lang === 'ar') {
        $w = $n === 1 ? $L['one'] : ($n >= 3 && $n <= 10 ? 'منتجات' : $L['many']);
    } else {
        $w = $n === 1 ? $L['one'] : $L['many'];
    }
    return !empty($L['nospace']) ? $n.$w : $n.' '.$w;
}

/** Kategori adlarini o dilde, "a, b, c" olarak. */
function vestra_seo_brand_catlist(array $cats, string $lang, int $max = 4): string {
    $tr = fn(string $s) => function_exists('t') ? t($s) : $s;
    $names = array_map($tr, array_slice(array_keys($cats), 0, $max));
    $sep = $lang === 'ja' ? '・' : ($lang === 'ar' ? '، ' : ', ');
    return implode($sep, $names);
}

/** Cikis ulkelerinin okunur listesi (kod -> ad; 'EU' oldugu gibi). */
function vestra_seo_brand_shiplist(array $ships, string $lang, int $max = 3): string {
    $out = [];
    foreach (array_slice($ships, 0, $max) as $s) {
        if ($s === 'EU') { $out[] = 'EU'; continue; }
        $out[] = (strlen($s) === 2 && function_exists('vestra_country_name')) ? vestra_country_name($s) : $s;
    }
    $sep = $lang === 'ja' ? '・' : ($lang === 'ar' ? '، ' : ', ');
    return implode($sep, $out);
}

/** Ay + yil, o dilde ("ottobre 2026", "October 2026", "2026年10月"). */
function vestra_seo_brand_month(int $ts, string $lang): string {
    if ($ts <= 0) return '';
    $L = vestra_seo_brand_lang($lang);
    $m = $L['months'][(int)date('n', $ts) - 1] ?? date('F', $ts);
    return $lang === 'ja' ? date('Y', $ts).'年'.$m : $m.' '.date('Y', $ts);
}

/** Kalip doldurucu: tum yer tutucular tek yerden. */
function vestra_seo_brand_vars(string $brand, array $prof, string $lang, string $cat = ''): array {
    $L = vestra_seo_brand_lang($lang);
    $pc = $L['pc'];
    $sp = !empty($L['nospace']) ? '' : ' ';
    $catlist = vestra_seo_brand_catlist($prof['cats'], $lang);
    $shiplist = vestra_seo_brand_shiplist($prof['ships'], $lang);
    $sizes = $prof['sizes'] ? implode(' · ', array_slice($prof['sizes'], 0, 12)) : '';
    $tr = fn(string $s) => function_exists('t') ? t($s) : $s;
    $colours = $prof['colours'] ? implode($lang === 'ja' ? '・' : ($lang === 'ar' ? '، ' : ', '), array_map($tr, array_slice($prof['colours'], 0, 8))) : '';
    $packs = $prof['packs'] ? implode('/', $prof['packs']) : '';
    $__em = (float)(defined('VESTRA_ESCROW_MAX') ? VESTRA_ESCROW_MAX : 3000);
    /* Tutar her dilin kendi biciminde: "3.000 €" (it/de/es/pt), "3 000 €" (fr/ru), "€3,000" (en/ar/ja). */
    $escrow = in_array($lang, ['it', 'de', 'es', 'pt'], true) ? number_format($__em, 0, ',', '.').' €'
            : (in_array($lang, ['fr', 'ru'], true) ? number_format($__em, 0, ',', "\u{202F}").' €' : '€'.number_format($__em, 0, '.', ','));
    $claim  = (string)(defined('VESTRA_CLAIM_DAYS') ? (int)VESTRA_CLAIM_DAYS : 3);
    /* SSS cumle ekleri: veri yoksa ek yok -- bos bir "bedenler: " yazilmaz. */
    $sizesS = $sizes !== '' ? ' '.[
        'en' => 'Sizes on the shelf: {S}.', 'it' => 'Taglie a magazzino: {S}.', 'fr' => 'Tailles en stock : {S}.', 'de' => 'Größen auf Lager: {S}.',
        'es' => 'Tallas en stock: {S}.', 'pt' => 'Tamanhos em stock: {S}.', 'ru' => 'Размеры в наличии: {S}.', 'ar' => 'المقاسات المتوفرة: {S}.', 'ja' => '在庫サイズ：{S}。',
    ][$lang] ?? 'Sizes on the shelf: {S}.' : '';
    $coloursS = $colours !== '' ? ' '.[
        'en' => 'Colours: {S}.', 'it' => 'Colori: {S}.', 'fr' => 'Coloris : {S}.', 'de' => 'Farben: {S}.', 'es' => 'Colores: {S}.',
        'pt' => 'Cores: {S}.', 'ru' => 'Цвета: {S}.', 'ar' => 'الألوان: {S}.', 'ja' => 'カラー：{S}。',
    ][$lang] ?? 'Colours: {S}.' : '';
    $packsS = $packs !== '' ? [
        'en' => ', ordered in multiples of {P}', 'it' => ', in confezioni da {P}', 'fr' => ', par multiples de {P}', 'de' => ', in Vielfachen von {P}',
        'es' => ', en múltiplos de {P}', 'pt' => ', em múltiplos de {P}', 'ru' => ', кратно {P}', 'ar' => '، بمضاعفات {P}', 'ja' => '（{P}の倍数）',
    ][$lang] ?? ', ordered in multiples of {P}' : '';
    return [
        '{B}' => $brand, '{C}' => $cat, '{n}' => (string)$prof['n'], '{N}' => vestra_seo_brand_count((int)$prof['n'], $lang),
        '{NC}' => (string)count($prof['cats']), '{CATLIST}' => $catlist,
        '{CATS}' => $catlist !== '' ? strtr($L['cats_in'], ['{CATLIST}' => $catlist]) : '',
        '{MOQ}' => $prof['moq_min'] ? $prof['moq_min'].$sp.$pc : '—',
        '{MOQMIN}' => (string)$prof['moq_min'], '{MOQMAX}' => (string)$prof['moq_max'], '{pc}' => $pc,
        '{SHIPLIST}' => $shiplist, '{SHIPS}' => $shiplist !== '' ? strtr($L['ships_from'], ['{SHIPLIST}' => $shiplist]) : '',
        '{SIZES}' => strtr($sizesS, ['{S}' => $sizes]), '{COLOURS}' => strtr($coloursS, ['{S}' => $colours]),
        '{PACKS}' => strtr($packsS, ['{P}' => $packs]), '{P}' => $packs,
        '{ESCROW}' => $escrow, '{CLAIM}' => $claim,
    ];
}

/** <title> govdesi (head.php " — VESTRA" ekler). $cat verilirse marka×kategori, $brand bos ve $cat doluysa kategori. */
function vestra_seo_brand_title(string $brand, array $prof, string $lang, string $cat = ''): string {
    $L = vestra_seo_brand_lang($lang);
    $key = $brand === '' ? 'title_c' : ($cat !== '' ? 'title_cat' : 'title');
    return trim(strtr($L[$key], vestra_seo_brand_vars($brand, $prof, $lang, $cat)));
}

/** meta description, ~160 karaktere kirpilmis (kelime sinirinda). */
function vestra_seo_brand_meta(string $brand, array $prof, string $lang): string {
    $L = vestra_seo_brand_lang($lang);
    $v = vestra_seo_brand_vars($brand, $prof, $lang);
    $s = strtr($L['meta'], $v);
    /* Uzunsa ortadan kesmek yerine once en az onemli parcalari birak: cikis ulkesi,
       sonra kategori listesi. Hala uzunsa kelime sinirinda kirp. */
    if (mb_strlen($s) > 160) { $v['{SHIPS}'] = ''; $s = strtr($L['meta'], $v); }
    if (mb_strlen($s) > 160) { $v['{CATS}'] = ''; $s = strtr($L['meta'], $v); }
    /* Google sonuc sayfasinda ~155-160 karakterden sonrasini KENDISI kisaltiyor; bizim
       ortadan "…" ile kesmemiz cumleyi bozar. Yalniz asiri uzunda kelime sinirinda kes. */
    if (mb_strlen($s) > 220) { $s = mb_substr($s, 0, 217); $s = preg_replace('/\s+\S*$/u', '', $s).'…'; }
    return $s;
}

/** SSS: [[soru, cevap], ...] -- veri olmayan soru atlanir (ornegin cikis ulkesi yoksa o soru yok). */
function vestra_seo_brand_faq(string $brand, array $prof, string $lang): array {
    $L = vestra_seo_brand_lang($lang);
    $v = vestra_seo_brand_vars($brand, $prof, $lang);
    $out = [];
    foreach ($L['faq'] as $i => [$q, $a]) {
        if ($i === 1 && !$prof['moq_min']) continue;
        if ($i === 2 && !$prof['cats']) continue;
        if ($i === 3 && $v['{SHIPLIST}'] === '') continue;
        $out[] = [strtr($q, $v), trim(strtr($a, $v))];
    }
    return $out;
}

/** FAQPage JSON-LD. */
function vestra_seo_brand_faq_ld(array $faq): array {
    return ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn($qa) => [
        '@type' => 'Question', 'name' => $qa[0],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa[1]],
    ], $faq)];
}

/** "Bir bakista" bloklari: [etiket, deger] -- yalniz verisi olanlar. */
function vestra_seo_brand_glance(string $brand, array $prof, string $lang): array {
    $L = vestra_seo_brand_lang($lang);
    $v = vestra_seo_brand_vars($brand, $prof, $lang);
    $tr = fn(string $s) => function_exists('t') ? t($s) : $s;
    $sep = $lang === 'ja' ? '・' : ($lang === 'ar' ? '، ' : ', ');
    $rows = [];
    if ($prof['cats']) $rows[] = [$L['l_cats'], implode($sep, array_map(fn($c, $n) => $tr($c).' ('.$n.')', array_keys($prof['cats']), $prof['cats']))];
    if ($prof['moq_min']) $rows[] = [$L['l_moq'], $prof['moq_min'] === $prof['moq_max']
        ? strtr($L['per_listing'], ['{MOQ}' => $v['{MOQ}']])
        : strtr($L['moq_range'], ['{A}' => $prof['moq_min'], '{Z}' => $prof['moq_max'], '{pc}' => $L['pc']])];
    if ($prof['packs']) $rows[] = [$L['l_packs'], strtr($L['packs_v'], ['{P}' => implode('/', $prof['packs'])])];
    if ($prof['sizes']) $rows[] = [$L['l_sizes'], implode(' · ', array_slice($prof['sizes'], 0, 14))];
    if ($prof['colours']) $rows[] = [$L['l_colours'], implode($sep, array_map($tr, array_slice($prof['colours'], 0, 10)))];
    if ($v['{SHIPLIST}'] !== '') $rows[] = [$L['l_ships'], $v['{SHIPLIST}']];
    if ($prof['tiers'] >= 2) $rows[] = [$L['l_tiers'], strtr($L['l_tiers_v'], ['{n}' => $prof['tiers']])];
    if ($prof['sale'] >= 2) $rows[] = [$L['l_sale'], strtr($L['l_sale_v'], ['{n}' => $prof['sale']])];
    if ($prof['sellers']) $rows[] = [$L['l_sellers'], (string)$prof['sellers']];
    if ($prof['newest']) $rows[] = [$L['l_newest'], vestra_seo_brand_month($prof['newest'], $lang)];
    return $rows;
}
