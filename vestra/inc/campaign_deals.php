<?php
/**
 * "İndirimli ürünler" takip mektubu (17 Eki 2026, operatör: "önce önceden gönderdiğimiz ilk müşterilere tekrar
 * indirimli ürünleri gönder").
 *
 * Daha önce ilk mektubu almış dükkâna giden İKİNCİ temas: kısa hatırlatma → şu an indirimde olan premium ürünler
 * (marka başına bir ürün, −% rozetiyle) → markaya göre indirim satırı → iki düğme: indirimli ürünler (katalog
 * /shop?mode=sale süzgeciyle açılır) ve ücretsiz kayıt.
 *
 * Ürünler YALNIZ onaylı canlı ilanlardan (vestra_live_listings) ve gerçekten indirimde olanlardan: vestra_on_sale()
 * (liste fiyatı kademe fiyatının üstünde) ya da süresi dolmamış AKTION. FİYAT GÖSTERİLMEZ (KURAL 19: toptan fiyat
 * yalnız onaylı alıcıya) — yalnız indirim yüzdesi. %10'dan küçük indirim ve premium olmayan marka seçilmez
 * (Polvich / Pili Pérez vb. zaten premium listesinde yok). İndirimli ürün yoksa kampanya listeye hiç girmez
 * (vestra_finder_campaigns) — boş mektup gitmez. Künye ayraçtan ("\n\n—\n") sonra; çıkış bağlantısına müşterinin
 * jetonu vestra_finder_campaigns() içinde eklenir.
 */
require_once __DIR__.'/campaign_edit.php';   // VESTRA_EDIT_PREMIUM

const VESTRA_DEALS_UTM = 'utm_source=email&utm_medium=campaign&utm_campaign=vestra_deals';
const VESTRA_DEALS_MIN_PCT = 10;
/* ÖNE ÇIKANLAR (22 Eki 2026, operatör: SH9623/SH9608/SH9626 yeni fiyatlar + "bu indirimli Lacoste ürünlerini Angebota
   koyalım … kampanya estetik olsun, live fotoları da ekle"). Bu ilanlar ızgaranın BAŞINA, marka başına tek ürün kuralı
   dışında girer (aynı markadan üçü birden) ve markaları konuda/başlıkta öne geçer. Yine yalnız SEÇİLEBİLİR olanlar:
   canlı, onaylı, ≥%10 indirimli — indirimi biten ilan kendiliğinden düşer. Canlı (depo) fotoğrafları bu ilanlardan. */
const VESTRA_DEALS_FEATURED = ['lac-fleece-hoodie', 'lac-crew-sweatshirt', 'lac-zip-hoodie', 'fp-m3600-polo', 'fp-m7535-sweat'];
/* Fred Perry (aynı gün: "f.perry'leri de koy") indirimde değil: öne çıkan bir ilan indirimsiz de girer, rozeti
   yüzde yerine "yeni" yazar (−%0 basılmaz). Öne çıkmayan ilanlar için ≥%10 kuralı aynen. */
const VESTRA_DEALS_LIVE_MAX = 6;

function vestra_deals_texts(string $lang): array {
  $T = [
    'en' => ['m_intro' => 'A quick update for our trade customers: markdowns on %1$s right now — up to %2$d%% off the regular wholesale price. Sign in to see your trade prices.', 'm_legal' => "VESTRA is operated by Acerasoft LLC. You are receiving this message because you have a VESTRA trade account. Prefer not to receive offers? Simply reply \"unsubscribe\".", 'm_cta2' => 'Sign in to my account',
      'new' => 'New in', 'spot' => '%s in focus', 'live' => 'Real photos · our EU warehouse', 'live_note' => 'New stock in original packaging with tags — photographed in our warehouse, not catalogue images.',
      'subject' => '%s: trade markdowns up to −%d%% · VESTRA',
      'kicker' => 'Trade markdowns · while stock lasts', 'title' => 'Up to −%d%% on %s.',
      'hello' => 'Hello %s,', 'hello0' => 'Hello,',
      'intro' => 'We wrote to you recently about VESTRA, the verified B2B marketplace where independent boutiques buy authentic designer brands at wholesale. A short update: several houses have trade markdowns right now — up to %2$d%% off the regular wholesale price.',
      'intro1' => 'We wrote to you recently about VESTRA, the verified B2B marketplace where independent boutiques buy authentic designer brands at wholesale. A short update: %1$s has trade markdowns right now — up to %2$d%% off the regular wholesale price.',
      'grid' => 'Marked down now', 'houses' => 'Markdowns by house',
      'trust' => ['Verified sellers', 'Authenticity checked', 'Order & delivery monitored by VESTRA'],
      'cta1' => 'See all discounted styles', 'cta2' => 'Create a free trade account',
      'note' => 'Registration is free. Trade prices are shown once your account is approved; markdowns apply while stock lasts.',
      'bye' => "Kind regards,\nThe VESTRA team",
      'legal' => "VESTRA is operated by Acerasoft LLC. You are receiving this business message because we wrote to %s about VESTRA before.\nDon't want to hear from us again? Unsubscribe instantly: https://vestrasales.com/lead-unsubscribe",
      'shop' => 'your boutique'],
    'de' => ['m_intro' => 'Ein kurzes Update für unsere Händlerkunden: Rabatte auf %1$s — bis zu %2$d%% unter dem regulären Großhandelspreis. Melden Sie sich an, um Ihre Händlerpreise zu sehen.', 'm_legal' => "VESTRA wird von Acerasoft LLC betrieben. Sie erhalten diese Nachricht, weil Sie ein VESTRA-Händlerkonto haben. Keine Angebote mehr? Antworten Sie einfach mit „abmelden“.", 'm_cta2' => 'Zu meinem Konto',
      'new' => 'Neu', 'spot' => 'Im Fokus: %s', 'live' => 'Echte Fotos · unser EU-Lager', 'live_note' => 'Neuware in Originalverpackung mit Etiketten — in unserem Lager fotografiert, keine Katalogbilder.',
      'subject' => '%s: Händlerrabatte bis −%d%% · VESTRA',
      'kicker' => 'Händlerrabatte · solange der Vorrat reicht', 'title' => 'Bis zu −%d%% auf %s.',
      'hello' => 'Guten Tag %s,', 'hello0' => 'Guten Tag,',
      'intro' => 'Wir hatten Ihnen kürzlich über VESTRA geschrieben, den verifizierten B2B-Marktplatz, auf dem unabhängige Boutiquen authentische Designermarken im Großhandel einkaufen. Ein kurzes Update: Artikel mehrerer Marken sind für Händler gerade reduziert — bis zu %2$d %% unter dem regulären Großhandelspreis.',
      'intro1' => 'Wir hatten Ihnen kürzlich über VESTRA geschrieben, den verifizierten B2B-Marktplatz, auf dem unabhängige Boutiquen authentische Designermarken im Großhandel einkaufen. Ein kurzes Update: Artikel von %1$s sind für Händler gerade reduziert — bis zu %2$d %% unter dem regulären Großhandelspreis.',
      'grid' => 'Jetzt reduziert', 'houses' => 'Rabatte nach Marke',
      'trust' => ['Verifizierte Verkäufer', 'Echtheit geprüft', 'Bestellung & Lieferung von VESTRA überwacht'],
      'cta1' => 'Alle reduzierten Artikel ansehen', 'cta2' => 'Kostenloses Händlerkonto anlegen',
      'note' => 'Die Registrierung ist kostenlos. Großhandelspreise sind nach Freigabe Ihres Kontos sichtbar; Rabatte gelten, solange der Vorrat reicht.',
      'bye' => "Mit freundlichen Grüßen\nIhr VESTRA-Team",
      'legal' => "VESTRA wird von Acerasoft LLC betrieben. Sie erhalten diese geschäftliche Nachricht, weil wir %s bereits zu VESTRA kontaktiert haben.\nKeine weiteren Nachrichten? Sofort abmelden: https://vestrasales.com/lead-unsubscribe",
      'shop' => 'Ihre Boutique'],
    'fr' => ['m_intro' => 'Petite mise à jour pour nos clients professionnels : remises sur %1$s en ce moment — jusqu’à %2$d%% sous le prix de gros habituel. Connectez-vous pour voir vos prix professionnels.', 'm_legal' => "VESTRA est exploité par Acerasoft LLC. Vous recevez ce message car vous avez un compte professionnel VESTRA. Vous ne souhaitez plus recevoir d’offres ? Répondez simplement « désinscrire ».", 'm_cta2' => 'Accéder à mon compte',
      'new' => 'Nouveau', 'spot' => 'À la une : %s', 'live' => 'Photos réelles · notre entrepôt européen', 'live_note' => 'Stock neuf, emballage d’origine et étiquettes — photographié dans notre entrepôt, pas des images de catalogue.',
      'subject' => '%s : remises professionnelles jusqu’à −%d%% · VESTRA',
      'kicker' => 'Remises pro · dans la limite des stocks', 'title' => 'Jusqu’à −%d%% sur %s.',
      'hello' => 'Bonjour %s,', 'hello0' => 'Bonjour,',
      'intro' => 'Nous vous avons écrit récemment au sujet de VESTRA, la place de marché B2B vérifiée où les boutiques indépendantes achètent des marques de créateurs authentiques en gros. Petite mise à jour : des articles de plusieurs maisons sont actuellement remisés pour les professionnels — jusqu’à %2$d %% de moins que le prix de gros habituel.',
      'intro1' => 'Nous vous avons écrit récemment au sujet de VESTRA, la place de marché B2B vérifiée où les boutiques indépendantes achètent des marques de créateurs authentiques en gros. Petite mise à jour : les articles %1$s sont actuellement remisés pour les professionnels — jusqu’à %2$d %% de moins que le prix de gros habituel.',
      'grid' => 'En promotion en ce moment', 'houses' => 'Remises par maison',
      'trust' => ['Vendeurs vérifiés', 'Authenticité contrôlée', 'Commande et livraison suivies par VESTRA'],
      'cta1' => 'Voir tous les articles remisés', 'cta2' => 'Créer un compte pro gratuit',
      'note' => 'L’inscription est gratuite. Les prix de gros s’affichent dès que votre compte est validé ; remises dans la limite des stocks.',
      'bye' => "Bien cordialement,\nL’équipe VESTRA",
      'legal' => "VESTRA est exploité par Acerasoft LLC. Vous recevez ce message professionnel car nous avons déjà écrit à %s au sujet de VESTRA.\nVous ne souhaitez plus nous lire ? Désinscription immédiate : https://vestrasales.com/lead-unsubscribe",
      'shop' => 'votre boutique'],
    'it' => ['m_intro' => 'Un breve aggiornamento per i nostri clienti trade: sconti su %1$s in questo momento — fino al %2$d%% sul prezzo all’ingrosso abituale. Accedi per vedere i tuoi prezzi trade.', 'm_legal' => "VESTRA è gestito da Acerasoft LLC. Ricevete questo messaggio perché avete un account trade VESTRA. Non desiderate più ricevere offerte? Rispondete semplicemente «cancella».", 'm_cta2' => 'Accedi al mio account',
      'new' => 'Novità', 'spot' => 'In primo piano: %s', 'live' => 'Foto reali · il nostro magazzino UE', 'live_note' => 'Merce nuova in confezione originale con cartellini — fotografata nel nostro magazzino, non immagini di catalogo.',
      'subject' => '%s: sconti trade fino al −%d%% · VESTRA',
      'kicker' => 'Sconti trade · fino a esaurimento scorte', 'title' => 'Fino al −%d%% su %s.',
      'hello' => 'Buongiorno %s,', 'hello0' => 'Buongiorno,',
      'intro' => 'Vi abbiamo scritto di recente a proposito di VESTRA, il marketplace B2B verificato dove le boutique indipendenti acquistano marchi di design autentici all’ingrosso. Un breve aggiornamento: gli articoli di diverse maison sono ora scontati per i rivenditori — fino al %2$d%% in meno rispetto al normale prezzo all’ingrosso.',
      'intro1' => 'Vi abbiamo scritto di recente a proposito di VESTRA, il marketplace B2B verificato dove le boutique indipendenti acquistano marchi di design autentici all’ingrosso. Un breve aggiornamento: gli articoli %1$s sono ora scontati per i rivenditori — fino al %2$d%% in meno rispetto al normale prezzo all’ingrosso.',
      'grid' => 'Ora in sconto', 'houses' => 'Sconti per maison',
      'trust' => ['Venditori verificati', 'Autenticità controllata', 'Ordine e consegna seguiti da VESTRA'],
      'cta1' => 'Vedi tutti gli articoli scontati', 'cta2' => 'Crea un account trade gratuito',
      'note' => 'La registrazione è gratuita. I prezzi all’ingrosso sono visibili appena il vostro account viene approvato; sconti fino a esaurimento scorte.',
      'bye' => "Cordiali saluti,\nIl team VESTRA",
      'legal' => "VESTRA è gestito da Acerasoft LLC. Ricevete questo messaggio commerciale perché abbiamo già contattato %s a proposito di VESTRA.\nNon volete più ricevere messaggi? Disiscrizione immediata: https://vestrasales.com/lead-unsubscribe",
      'shop' => 'la vostra boutique'],
    'es' => ['m_intro' => 'Una breve novedad para nuestros clientes profesionales: descuentos en %1$s ahora mismo — hasta un %2$d%% sobre el precio mayorista habitual. Inicie sesión para ver sus precios profesionales.', 'm_legal' => "VESTRA está operado por Acerasoft LLC. Recibe este mensaje porque tiene una cuenta profesional en VESTRA. ¿Prefiere no recibir ofertas? Responda simplemente «baja».", 'm_cta2' => 'Acceder a mi cuenta',
      'new' => 'Novedad', 'spot' => 'Destacado: %s', 'live' => 'Fotos reales · nuestro almacén en la UE', 'live_note' => 'Stock nuevo en embalaje original con etiquetas — fotografiado en nuestro almacén, no imágenes de catálogo.',
      'subject' => '%s: descuentos mayoristas de hasta −%d%% · VESTRA',
      'kicker' => 'Descuentos profesionales · hasta agotar existencias', 'title' => 'Hasta −%d%% en %s.',
      'hello' => 'Hola %s,', 'hello0' => 'Hola,',
      'intro' => 'Le escribimos hace poco sobre VESTRA, el marketplace B2B verificado donde las boutiques independientes compran marcas de diseño auténticas al por mayor. Una breve novedad: varias marcas tienen ahora artículos rebajados para profesionales — hasta un %2$d %% menos que el precio mayorista habitual.',
      'intro1' => 'Le escribimos hace poco sobre VESTRA, el marketplace B2B verificado donde las boutiques independientes compran marcas de diseño auténticas al por mayor. Una breve novedad: los artículos de %1$s están ahora rebajados para profesionales — hasta un %2$d %% menos que el precio mayorista habitual.',
      'grid' => 'Con descuento ahora', 'houses' => 'Descuentos por marca',
      'trust' => ['Vendedores verificados', 'Autenticidad comprobada', 'Pedido y entrega supervisados por VESTRA'],
      'cta1' => 'Ver todos los artículos con descuento', 'cta2' => 'Crear una cuenta profesional gratis',
      'note' => 'El registro es gratuito. Los precios mayoristas se ven en cuanto se aprueba su cuenta; descuentos hasta agotar existencias.',
      'bye' => "Un cordial saludo,\nEl equipo de VESTRA",
      'legal' => "VESTRA está operado por Acerasoft LLC. Recibe este mensaje comercial porque ya escribimos a %s sobre VESTRA.\n¿No quiere recibir más mensajes? Darse de baja al instante: https://vestrasales.com/lead-unsubscribe",
      'shop' => 'su boutique'],
    'nl' => ['m_intro' => 'Een korte update voor onze zakelijke klanten: kortingen op %1$s op dit moment — tot %2$d%% onder de gewone groothandelsprijs. Log in om uw handelsprijzen te zien.', 'm_legal' => "VESTRA wordt beheerd door Acerasoft LLC. U ontvangt dit bericht omdat u een zakelijk VESTRA-account heeft. Liever geen aanbiedingen meer? Antwoord gewoon „afmelden”.", 'm_cta2' => 'Naar mijn account',
      'new' => 'Nieuw', 'spot' => 'Uitgelicht: %s', 'live' => 'Echte foto’s · ons EU-magazijn', 'live_note' => 'Nieuwe voorraad in originele verpakking met labels — gefotografeerd in ons magazijn, geen catalogusbeelden.',
      'subject' => '%s: handelskortingen tot −%d%% · VESTRA',
      'kicker' => 'Handelskortingen · zolang de voorraad strekt', 'title' => 'Tot −%d%% op %s.',
      'hello' => 'Hallo %s,', 'hello0' => 'Hallo,',
      'intro' => 'We schreven u onlangs over VESTRA, de geverifieerde B2B-marktplaats waar onafhankelijke boetieks authentieke designermerken in de groothandel inkopen. Een korte update: artikelen van verschillende merken zijn nu afgeprijsd voor de handel — tot %2$d%% onder de normale groothandelsprijs.',
      'intro1' => 'We schreven u onlangs over VESTRA, de geverifieerde B2B-marktplaats waar onafhankelijke boetieks authentieke designermerken in de groothandel inkopen. Een korte update: artikelen van %1$s zijn nu afgeprijsd voor de handel — tot %2$d%% onder de normale groothandelsprijs.',
      'grid' => 'Nu afgeprijsd', 'houses' => 'Kortingen per merk',
      'trust' => ['Geverifieerde verkopers', 'Echtheid gecontroleerd', 'Bestelling en levering bewaakt door VESTRA'],
      'cta1' => 'Bekijk alle afgeprijsde artikelen', 'cta2' => 'Gratis handelsaccount aanmaken',
      'note' => 'Registreren is gratis. Groothandelsprijzen zijn zichtbaar zodra uw account is goedgekeurd; kortingen zolang de voorraad strekt.',
      'bye' => "Met vriendelijke groet,\nHet VESTRA-team",
      'legal' => "VESTRA wordt beheerd door Acerasoft LLC. U ontvangt dit zakelijke bericht omdat we eerder contact hebben opgenomen met %s over VESTRA.\nGeen berichten meer? Direct afmelden: https://vestrasales.com/lead-unsubscribe",
      'shop' => 'uw boetiek'],
    'pt' => ['m_intro' => 'Uma breve novidade para os nossos clientes profissionais: descontos em %1$s neste momento — até %2$d%% abaixo do preço grossista habitual. Inicie sessão para ver os seus preços profissionais.', 'm_legal' => "A VESTRA é operada pela Acerasoft LLC. Recebe esta mensagem porque tem uma conta profissional VESTRA. Prefere não receber ofertas? Responda simplesmente «cancelar».", 'm_cta2' => 'Aceder à minha conta',
      'new' => 'Novidade', 'spot' => 'Em destaque: %s', 'live' => 'Fotos reais · o nosso armazém na UE', 'live_note' => 'Stock novo na embalagem original com etiquetas — fotografado no nosso armazém, não imagens de catálogo.',
      'subject' => '%s: descontos de atacado até −%d%% · VESTRA',
      'kicker' => 'Descontos profissionais · até esgotar o stock', 'title' => 'Até −%d%% em %s.',
      'hello' => 'Olá %s,', 'hello0' => 'Olá,',
      'intro' => 'Escrevemos-lhe recentemente sobre a VESTRA, o marketplace B2B verificado onde boutiques independentes compram marcas de design autênticas por atacado. Uma breve novidade: artigos de várias marcas estão agora com desconto para profissionais — até %2$d%% abaixo do preço de atacado habitual.',
      'intro1' => 'Escrevemos-lhe recentemente sobre a VESTRA, o marketplace B2B verificado onde boutiques independentes compram marcas de design autênticas por atacado. Uma breve novidade: os artigos %1$s estão agora com desconto para profissionais — até %2$d%% abaixo do preço de atacado habitual.',
      'grid' => 'Com desconto agora', 'houses' => 'Descontos por marca',
      'trust' => ['Vendedores verificados', 'Autenticidade controlada', 'Encomenda e entrega acompanhadas pela VESTRA'],
      'cta1' => 'Ver todos os artigos com desconto', 'cta2' => 'Criar conta profissional grátis',
      'note' => 'O registo é gratuito. Os preços de atacado ficam visíveis assim que a sua conta for aprovada; descontos até esgotar o stock.',
      'bye' => "Com os melhores cumprimentos,\nA equipa VESTRA",
      'legal' => "A VESTRA é operada pela Acerasoft LLC. Recebe esta mensagem comercial porque já contactámos %s sobre a VESTRA.\nNão quer receber mais mensagens? Cancelar já: https://vestrasales.com/lead-unsubscribe",
      'shop' => 'a sua boutique'],
  ];
  return $T[$lang] ?? $T['en'];
}

/** Markanın mektupta görünen adı: tamamı büyük harfle kayıtlı ad ("BALMAIN") konu satırında bağırmasın — rakamlı
 *  marka yazımı (DSQUARED2) korunur. */
function vestra_deals_brand_label(string $b): string {
  $b = trim($b);
  if ($b !== '' && $b === mb_strtoupper($b) && !preg_match('/\d/', $b) && mb_strlen($b) > 3 && !str_contains($b, '&')) return mb_convert_case(mb_strtolower($b), MB_CASE_TITLE);
  return $b;
}

/**
 * İndirimdeki premium ürünler: ['items' => [brand, label, name, img, url, pct], 'brands' => [etiket => en yüksek %],
 * 'max' => en yüksek %, 'count' => uygun ilan sayısı]. Izgara önce marka başına bir ürün (en yüksek indirimli), marka
 * azsa ikinci ürünlerle dolar. $all / $now testte verilir.
 */
function vestra_deals_pick(int $n = 6, ?array $all = null, ?int $now = null): array {
  static $memo = [];
  /* Süreç içi önbellek (kampanya listesi her çağrıda sorar); ilan dosyası değişince ve 10 dk'da bir tazelenir. */
  $lf = vestra_data_dir().'/listings.json'; clearstatcache(true, $lf);
  $key = $all === null ? $n.'|'.($now ?? 0).'|'.(int)@filemtime($lf).'|'.(int)@filesize($lf).'|'.intdiv(time(), 600) : '';
  if ($key !== '' && isset($memo[$key])) return $memo[$key];
  $all = $all ?? (function_exists('vestra_live_listings') ? vestra_live_listings() : []);
  $rows = [];
  foreach ($all as $p) {
    $brand = trim((string)($p['brand'] ?? ''));
    if ($brand === '' || ($p['id'] ?? '') === '' || !preg_match(VESTRA_EDIT_PREMIUM, $brand)) continue;
    if (function_exists('vestra_is_sold_out') && vestra_is_sold_out($p)) continue;   // satılmış / stok dışı ilan mektupta duramaz
    if (($p['status'] ?? 'approved') !== 'approved') continue;                         // liste dışarıdan verilse de yalnız onaylı ilan
    $pct = vestra_on_sale($p) ? (int)vestra_discount($p) : 0;
    if (($a = vestra_aktion($p, $now))) $pct = max($pct, (int)$a['pct']);
    $isFeat = in_array((string)($p['id'] ?? ''), VESTRA_DEALS_FEATURED, true);
    if ($pct >= 90 || ($pct < VESTRA_DEALS_MIN_PCT && !$isFeat)) continue;   // %90+ veri hatasıdır, mektuba girmez
    if ($pct < VESTRA_DEALS_MIN_PCT) $pct = 0;   // öne çıkan, indirimsiz: rozet 'yeni'
    $imgs = $p['images'] ?? []; $img = is_array($imgs) && $imgs ? (string)$imgs[0] : (string)($p['image'] ?? '');
    if ($img === '') continue;
    if (!preg_match('#^https?://#i', $img)) $img = 'https://vestrasales.com'.(str_starts_with($img, '/') ? '' : '/').$img;
    $live = [];
    if (function_exists('vestra_live_photos')) foreach (vestra_live_photos($p) as $lp) $live[] = 'https://vestrasales.com'.$lp['thumb'];
    $rows[] = ['id' => (string)$p['id'], 'brand' => $brand, 'label' => vestra_deals_brand_label($brand), 'name' => trim((string)($p['name'] ?? '')), 'img' => $img,
               'url' => 'https://vestrasales.com/product?id='.rawurlencode((string)$p['id']).'&'.VESTRA_DEALS_UTM, 'pct' => $pct, 'live' => $live];
  }
  usort($rows, static fn($a, $b) => [$b['pct'], $a['brand'], $a['name']] <=> [$a['pct'], $b['brand'], $b['name']]);
  $brands = []; $raw = [];
  foreach ($rows as $r) { $raw[$r['label']] ??= $r['brand']; if ($r['pct'] > 0) $brands[$r['label']] = max($brands[$r['label']] ?? 0, $r['pct']); }
  arsort($brands);
  $items = []; $used = []; $taken = []; $feat = [];
  foreach (VESTRA_DEALS_FEATURED as $fid) foreach ($rows as $k => $r) {
    if ($r['id'] !== $fid || isset($taken[$k])) continue;
    $items[] = $r + ['featured' => true]; $used[$r['label']] = true; $taken[$k] = true; $feat[$r['label']] = true;
  }
  /* Öne çıkan markalar başlıkta/konuda önce gelir (sıra yine indirim yüzdesiyle). */
  if ($feat) $brands = array_intersect_key($brands, $feat) + $brands;
  $n += count($items);
  foreach ($rows as $k => $r) {
    if (isset($taken[$k])) continue;
    if (count($items) >= $n) break;
    if (isset($used[$r['label']])) continue;
    $items[] = $r; $used[$r['label']] = true; $taken[$k] = true;
  }
  foreach ($rows as $k => $r) {
    if (count($items) >= $n) break;
    if (!isset($taken[$k])) { $items[] = $r; $taken[$k] = true; }
  }
  /* 'raw': mektupta görünen ad → katalogdaki kayıtlı ad (BALMAIN). Katalog süzgeci kayıtlı adla eşleşir; bağlantıda görünen ad
     ("Balmain") giderse süzgeç tutmuyor, bütün indirimli ürünler açılıyordu. */
  $out = ['items' => $items, 'brands' => $brands, 'raw' => $raw, 'max' => $brands ? (int)max($brands) : 0, 'count' => count(array_filter($rows, static fn($r) => $r['pct'] > 0))];
  if ($key !== '') $memo[$key] = $out;
  return $out;
}

/* MARKA DALGALARI (24 Eki 2026, operatör: "bugün kalan gönderimi kullan 435 email gönder lacoste indirimi" + "email
   kampanyalarına her gün otomatik gitmeli"): indirim mektubunu ALMIŞ ve 5+ gün dinlenmiş dükkânlara sırayla TEK MARKA
   indirim mektubu (aynı tasarım). Sıra: öne çıkan marka (Lacoste) önce, sonra en yüksek indirim. Yalnız ≥ %20 indirimde
   ürünü olan marka dalga olur; indirimi biten marka kendiliğinden düşer, yeni indirimli marka kendiliğinden eklenir.
   Anahtar: Lacoste 'lacoste' (24 Eki ilk dalga bu adla gitti), diğerleri 'w:<marka>'. Her dükkân her dalgayı bir kez alır. */
const VESTRA_DEALS_WAVE_MIN_PCT = 20;
function vestra_deals_waves_on(): bool { return !defined('VESTRA_DEALS_WAVES_OFF'); }   // VESTRA_DEALS_WAVES_OFF: eski testler (yalnız 'deals')
function vestra_deals_wave_key(string $label): string {
  $s = trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower($label)), '-');
  return $s === 'lacoste' ? 'lacoste' : 'w:'.$s;
}
function vestra_deals_is_wave_key(string $k): bool { return $k === 'lacoste' || str_starts_with($k, 'w:'); }
/** Dalgalar sırayla: [anahtar => ['label', 'pct', 'pick']]. */
function vestra_deals_waves(?array $all = null, ?int $now = null): array {
  if (!vestra_deals_waves_on()) return [];
  $p = vestra_deals_pick(200, $all, $now); $out = [];
  foreach ($p['brands'] as $label => $pct) {
    if ((int)$pct < VESTRA_DEALS_WAVE_MIN_PCT) continue;
    $bp = vestra_deals_pick_brand((string)$label, 6, $all, $now);
    if ($bp['items']) $out[vestra_deals_wave_key((string)$label)] = ['label' => (string)$label, 'pct' => (int)$pct, 'pick' => $bp];
  }
  return $out;
}

/** Tek markanın indirimli ürünleri (vestra_deals_pick biçiminde; yalnız gerçekten indirimdekiler, en çok $n). */
function vestra_deals_pick_brand(string $brand, int $n = 6, ?array $all = null, ?int $now = null): array {
  $p = vestra_deals_pick(200, $all, $now); $is = static fn(string $x) => strcasecmp(trim($x), $brand) === 0;
  $items = array_values(array_filter($p['items'], static fn($r) => ((int)$r['pct'] > 0) && ($is($r['brand']) || $is($r['label']))));
  usort($items, static fn($a, $b) => [empty($b['featured']), $b['pct']] <=> [empty($a['featured']), $a['pct']]);
  $brands = array_filter($p['brands'], static fn($k) => $is((string)$k), ARRAY_FILTER_USE_KEY);
  return ['items' => array_slice($items, 0, $n), 'brands' => $brands, 'raw' => array_intersect_key($p['raw'], $brands), 'max' => $brands ? (int)max($brands) : 0, 'count' => count($items)];
}

/** [konu, düz gövde, opts] — vestra_html_email ile çizilir. İndirimli ürün yoksa konu boş döner (çağıran göndermez). */
/* $member = true (22 Eki 2026, operatör: "kayıtlı müşteri + yeni müşterilere"): KAYITLI alıcı hesabına giden sürüm --
   "size daha önce yazmıştık" yerine müşteri girişi, ikinci düğme kayıt değil hesaba giriş, künye "hesabınız var"
   ve çıkış cevapla (hesaplar lead değil, lead-unsubscribe onlara işlemez). Izgara ve ürünler aynı. */
function vestra_campaign_deals(string $company = '', string $lang = 'en', ?array $pick = null, bool $member = false): array {
  if (function_exists('vestra_name_is_bare_domain') && vestra_name_is_bare_domain($company)) $company = '';
  $t = vestra_deals_texts($lang); $pick = $pick ?? vestra_deals_pick();
  if (!$pick['items']) return ['', '', []];
  $h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
  $lg = preg_match('/^[a-z]{2}$/', $lang) ? $lang : 'en';
  $shop = 'https://vestrasales.com/shop?mode=sale&'.VESTRA_DEALS_UTM.'&lang='.$lg;
  $reg = 'https://vestrasales.com/register?type=buyer&'.VESTRA_DEALS_UTM.'&lang='.$lg;
  $max = (int)$pick['max'];
  $top = array_slice(array_keys($pick['brands']), 0, 4);
  $topTxt = count($top) > 1 ? implode(', ', array_slice($top, 0, -1)).' & '.end($top) : (string)($top[0] ?? 'VESTRA');
  $subject = sprintf($t['subject'], implode(', ', array_slice($top, 0, 3)), $max);
  $hello = $company !== '' ? sprintf($t['hello'], $company) : $t['hello0'];
  $intro = count($pick['brands']) === 1 ? sprintf($t['intro1'], $topTxt, $max) : sprintf($t['intro'], $topTxt, $max);   // tek marka: "birkaç marka" denmez
  if ($member) {
    $intro = sprintf($t['m_intro'] ?? $t['intro'], $topTxt, $max);
    $reg = 'https://vestrasales.com/login?'.VESTRA_DEALS_UTM.'&lang='.$lg; $t['cta2'] = $t['m_cta2'] ?? $t['cta2']; $t['note'] = '';
  }
  $gold = '#a97f2c'; $ink = '#14110c'; $mut = '#8a8272'; $red = '#b4451f';
  $sec = static fn(string $x) => '<div style="color:'.$gold.';font-size:11px;font-weight:700;letter-spacing:.22em;text-transform:uppercase;text-align:center;margin:26px 0 12px">'.$x.'</div>';
  $newTxt = (string)($t['new'] ?? 'New in');
  $tile = static function (array $p) use ($h, $ink, $mut, $red, $newTxt): string {
    return '<a href="'.$h($p['url']).'" style="text-decoration:none;color:'.$ink.'">'
      .'<img src="'.$h($p['img']).'" width="160" alt="'.$h($p['label'].' '.$p['name']).'" style="display:block;width:100%;max-width:160px;height:auto;border-radius:10px;border:1px solid #ece6d8;background:#f7f4ee">'
      .'<div style="margin:8px 0 2px">'.((int)$p['pct'] > 0
          ? '<span style="display:inline-block;background:'.$red.';color:#ffffff;font-size:12px;font-weight:700;border-radius:999px;padding:3px 9px">−'.(int)$p['pct'].'%</span>'
          : '<span style="display:inline-block;background:#14110c;color:#f4ecd8;font-size:11px;font-weight:700;letter-spacing:.06em;border-radius:999px;padding:4px 10px">'.$h($newTxt).'</span>').'</div>'
      .'<div style="font-family:Georgia,serif;font-size:12.5px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;margin:6px 0 2px;word-break:break-word">'.$h($p['label']).'</div>'
      .'<div style="font-size:12px;color:'.$mut.';line-height:1.35">'.$h(mb_strimwidth($p['name'], 0, 46, '…', 'UTF-8')).'</div></a>';
  };
  $btn = static fn(string $label, string $url, bool $solid) => '<a href="'.$h($url).'" style="display:block;text-align:center;text-decoration:none;font-family:Georgia,serif;font-size:15px;font-weight:700;letter-spacing:.03em;padding:15px 18px;border-radius:999px;'
    .($solid ? 'background:'.$ink.';color:#f4ecd8;border:1px solid '.$ink : 'background:#ffffff;color:'.$ink.';border:1.5px solid '.$gold).'">'.$h($label).'</a>';

  $html = '<p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#3a3428">'.$h($hello).'</p>'
        . '<p style="margin:0 0 6px;font-size:15px;line-height:1.65;color:#3a3428">'.$h($intro).'</p>'
        ;
  $grid3 = static function (array $list, callable $cell): string {
    $g = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse">';
    foreach (array_chunk($list, 3) as $row) {
      $g .= '<tr>';
      foreach ($row as $x) $g .= '<td valign="top" width="33%" style="padding:0 5px 16px">'.$cell($x).'</td>';
      for ($i = count($row); $i < 3; $i++) $g .= '<td width="33%"></td>';
      $g .= '</tr>';
    }
    return $g.'</table>';
  };
  /* Öne çıkanlar (VESTRA_DEALS_FEATURED) kendi başlıklarıyla en üstte; canlı depo fotoğrafları hemen altında. */
  $featItems = array_values(array_filter($pick['items'], static fn($p) => !empty($p['featured'])));
  $restItems = array_values(array_filter($pick['items'], static fn($p) => empty($p['featured'])));
  $livePics = [];
  foreach ($featItems as $p) foreach ((array)($p['live'] ?? []) as $u) if (count($livePics) < VESTRA_DEALS_LIVE_MAX) $livePics[] = ['src' => $u, 'url' => $p['url'], 'alt' => $p['label'].' '.$p['name']];
  if ($featItems) {
    $fl = array_values(array_unique(array_column($featItems, 'label')));
    $html .= '<div style="margin:22px 0 4px;border-top:1px solid #ece6d8"></div>'.$sec($h(sprintf($t['spot'] ?? '%s', implode(' · ', $fl)))).$grid3($featItems, $tile);
    if ($livePics) {
      $html .= $sec($h($t['live'] ?? ''))
        .$grid3($livePics, static fn($x) => '<a href="'.$h($x['url']).'" style="text-decoration:none"><img src="'.$h($x['src']).'" width="160" alt="'.$h($x['alt']).'" style="display:block;width:100%;max-width:160px;height:auto;border-radius:10px;border:1px solid #ece6d8"></a>')
        .'<p style="margin:-6px 0 4px;font-size:12px;line-height:1.5;color:'.$mut.';text-align:center;font-style:italic">'.$h($t['live_note'] ?? '').'</p>';
    }
  }
  if ($restItems) $html .= $sec($h($t['grid'])).$grid3($restItems, $tile);
  $links = [];
  foreach (array_slice($pick['brands'], 0, 8, true) as $b => $pct)
    $links[] = '<a href="'.$h('https://vestrasales.com/shop?mode=sale&brand='.rawurlencode((string)($pick['raw'][$b] ?? $b)).'&'.VESTRA_DEALS_UTM.'&lang='.$lg).'" style="color:'.$ink.';text-decoration:none;white-space:nowrap"><b>'.str_replace(' ', '&nbsp;', $h($b)).'</b>&nbsp;<span style="color:'.$red.'">−'.(int)$pct.'%</span></a>';
  $html .= $sec($h($t['houses'])).'<p style="margin:0 0 6px;font-size:13px;line-height:2;color:'.$mut.';text-align:center">'.implode(' &nbsp;·&nbsp; ', $links).'</p>';
  $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:18px 0 22px;background:#f7f2e7;border-radius:10px"><tr>';
  foreach ($t['trust'] as $tr) $html .= '<td align="center" width="33%" style="padding:12px 6px;font-size:12px;color:#5a4a26;font-weight:700">✓ '.$h($tr).'</td>';
  $html .= '</tr></table>'
        . $btn($t['cta1'], $shop, true).'<div style="height:10px;line-height:10px">&nbsp;</div>'.$btn($t['cta2'], $reg, false)
        . ($t['note'] !== '' ? '<p style="margin:12px 0 4px;font-size:12px;line-height:1.5;color:'.$mut.';text-align:center">'.$h($t['note']).'</p>' : '')
        . '<p style="margin:22px 0 0;font-size:15px;line-height:1.6;color:#3a3428">'.nl2br($h($t['bye'])).'</p>';

  /* Düz metin: aynı içerik + bağlantılar. Künye ayraçtan sonra. Fransızcada iki noktadan önce boşluk. */
  $c = $lg === 'fr' ? ' :' : ':';
  $lines = [$hello, '', $intro, ''];
  if ($featItems) {
    $lines[] = sprintf($t['spot'] ?? '%s', implode(' · ', array_values(array_unique(array_column($featItems, 'label'))))).$c;
    foreach ($featItems as $p) $lines[] = '- '.$p['label'].' — '.$p['name'].' ('.((int)$p['pct'] > 0 ? '−'.(int)$p['pct'].'%' : $newTxt).'): '.$p['url'];
    if ($livePics) $lines[] = ($t['live'] ?? '').' — '.($t['live_note'] ?? '');
    $lines[] = '';
  }
  if ($restItems) { $lines[] = $t['grid'].$c; foreach ($restItems as $p) $lines[] = '- '.$p['label'].' — '.$p['name'].' (−'.(int)$p['pct'].'%): '.$p['url']; }
  $hl = []; foreach (array_slice($pick['brands'], 0, 8, true) as $b => $pct) $hl[] = $b.' −'.(int)$pct.'%';
  $lines = array_merge($lines, ['', $t['houses'].$c.' '.implode(' · ', $hl), '', implode(' · ', $t['trust']), '', $t['cta1'].$c.' '.$shop, $t['cta2'].$c.' '.$reg, $t['note'], '', $t['bye']]);
  $body = implode("\n", $lines)."\n\n—\n".($member ? (string)($t['m_legal'] ?? '') : sprintf($t['legal'], $company !== '' ? $company : $t['shop']));
  return [$subject, $body, ['html' => $html, 'hero' => ['kicker' => $t['kicker'], 'title' => sprintf($t['title'], $max, $topTxt)]]];
}
