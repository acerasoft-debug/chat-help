<?php
/**
 * "VESTRA Edit" kampanyası (10 Eki 2026, operatör: "daha iyi ve estetik kampanya yap, Gallery Dept,
 * Casablanca'lar da olsun, siteye yönlendirme ve kayıt olma linki de").
 *
 * Koyu üst bant + başlık → kısa giriş → "Öne çıkanlar": Gallery Dept ve Casablanca (katalogda varsa büyük
 * ürün görselleriyle, yoksa marka kutusu) → 6 ürünlük seçki ızgarası (marka başına 1) → güven satırı →
 * iki büyük düğme: koleksiyon (katalog) ve ücretsiz kayıt (alıcı) → imza → yasal/çıkış künyesi.
 *
 * Ürünler YALNIZ onaylı canlı ilanlardan (vestra_live_listings) — satılamayacak bir ürün gösterilmez.
 * Fiyat gösterilmez (alıcı onaylanmadan toptan fiyat görünmez). Ana blok $opts['html'] ile gider;
 * düz metin bölümü aynı içeriği bağlantılarıyla taşır (HTML açmayan istemci boş mektup almasın).
 * Künye ve tek tık abonelikten çıkma satırı gövdenin "\n\n—\n" ayracından sonrasındadır (çizici onu
 * künye puntosunda basar; vestra_finder_campaigns() bağlantıya müşterinin jetonunu ekler).
 */

/* 10 Eki 2026: operatör Lacoste, Fred Perry ve Valentino'yu da istedi. İlk ikisi büyük, kalan üçü ikinci sırada. */
const VESTRA_EDIT_SPOTLIGHT = ['Gallery Dept' => '/gallery\s*dept/i', 'Casablanca' => '/casablanca/i',
  'Lacoste' => '/lacoste/i', 'Fred Perry' => '/fred\s*perry/i', 'Valentino' => '/valentino/i'];
/* Seçki ve marka satırı YALNIZ tasarımcı / premium markalardan (10 Eki test mektubunda ürün sayısına göre seçim
   Pili Pérez, Visatin saten gecelik, Polvich, NBB, Q-EN getirdi — premium kampanyada yeri yok). Katalogda olmayan
   marka zaten seçilmez. */
const VESTRA_EDIT_PREMIUM = '/^(dsquared2?|balmain|burberry|givenchy|fendi|gucci|dolce\s*&\s*gabbana|d&g|marcelo\s*burlon|amiri|palm\s*angels|off-?white|stone\s*island|moncler|versace|prada|miu\s*miu|moschino|balenciaga|saint\s*laurent|ysl|kenzo|hugo\s*boss|boss|ralph\s*lauren|polo\s*ralph\s*lauren|tommy\s*hilfiger|calvin\s*klein|diesel|jacob\s*cohen|dior|loewe|jacquemus|alexander\s*mcqueen|bottega\s*veneta|valentino|lacoste|fred\s*perry|casablanca|gallery\s*dept\.?|rhude|represent|ami(\s*paris)?|maison\s*margiela|mm6|acne\s*studios|ganni|isabel\s*marant|zadig\s*&\s*voltaire|max\s*mara|herno|parajumpers|c\.?p\.?\s*company|philipp\s*plein|karl\s*lagerfeld|armani|emporio\s*armani|ea7|michael\s*kors|guess)$/i';
const VESTRA_EDIT_UTM = 'utm_source=email&utm_medium=campaign&utm_campaign=vestra_edit';

function vestra_edit_texts(string $lang): array {
  $T = [
    'en' => ['subject' => 'Gallery Dept, Casablanca, Valentino, Lacoste & more — trade prices on VESTRA',
      'kicker' => 'New season · trade only', 'title' => 'Gallery Dept, Casablanca and the houses your clients ask for — at wholesale.',
      'hello' => 'Hello %s,', 'hello0' => 'Hello,',
      'intro' => 'VESTRA is a KYC-verified B2B marketplace where independent boutiques buy authentic designer and streetwear brands at wholesale prices. Every seller is verified, authenticity is checked and payment is protected by escrow until your order arrives.',
      'focus' => 'In focus', 'grid' => 'From the current selection',
      'trust' => ['Verified sellers', 'Authenticity checked', 'Escrow-protected payment'],
      'cta1' => 'View the collection', 'cta2' => 'Create a free trade account',
      'note' => 'Registration is free. Trade prices become visible as soon as your account is approved.',
      'bye' => "Kind regards,\nThe VESTRA team",
      'legal' => "VESTRA is operated by Acerasoft LLC. This is a one-time business message from our verified B2B wholesale marketplace — you are receiving it because %s was identified as a potential trade partner.\nDon't want to hear from us again? Unsubscribe instantly: https://vestrasales.com/lead-unsubscribe",
      'shop' => 'your boutique'],
    'de' => ['subject' => 'Gallery Dept, Casablanca, Valentino, Lacoste & mehr — Großhandel auf VESTRA',
      'kicker' => 'Neue Saison · nur für Händler', 'title' => 'Gallery Dept, Casablanca und die Marken, nach denen Ihre Kunden fragen — im Großhandel.',
      'hello' => 'Guten Tag %s,', 'hello0' => 'Guten Tag,',
      'intro' => 'VESTRA ist ein KYC-verifizierter B2B-Marktplatz, auf dem unabhängige Boutiquen authentische Designer- und Streetwear-Marken zu Großhandelspreisen einkaufen. Jeder Verkäufer ist verifiziert, die Echtheit wird geprüft und die Zahlung ist per Treuhand geschützt, bis Ihre Bestellung ankommt.',
      'focus' => 'Im Fokus', 'grid' => 'Aus der aktuellen Auswahl',
      'trust' => ['Verifizierte Verkäufer', 'Echtheit geprüft', 'Treuhand-geschützte Zahlung'],
      'cta1' => 'Kollektion ansehen', 'cta2' => 'Kostenloses Händlerkonto anlegen',
      'note' => 'Die Registrierung ist kostenlos. Großhandelspreise werden sichtbar, sobald Ihr Konto freigegeben ist.',
      'bye' => "Mit freundlichen Grüßen\nIhr VESTRA-Team",
      'legal' => "VESTRA wird von Acerasoft LLC betrieben. Dies ist eine einmalige geschäftliche Nachricht unseres verifizierten B2B-Großhandelsmarktplatzes — Sie erhalten sie, weil %s als möglicher Handelspartner identifiziert wurde.\nKeine weiteren Nachrichten? Sofort abmelden: https://vestrasales.com/lead-unsubscribe",
      'shop' => 'Ihre Boutique'],
    'fr' => ['subject' => 'Gallery Dept, Casablanca, Valentino, Lacoste et plus — prix de gros sur VESTRA',
      'kicker' => 'Nouvelle saison · réservé aux professionnels', 'title' => 'Gallery Dept, Casablanca et les maisons que vos clients demandent — en gros.',
      'hello' => 'Bonjour %s,', 'hello0' => 'Bonjour,',
      'intro' => "VESTRA est une place de marché B2B vérifiée KYC où les boutiques indépendantes achètent des marques de créateurs et de streetwear authentiques aux prix de gros. Chaque vendeur est vérifié, l'authenticité est contrôlée et le paiement est protégé par séquestre jusqu'à la réception de votre commande.",
      'focus' => "À l'honneur", 'grid' => 'De la sélection actuelle',
      'trust' => ['Vendeurs vérifiés', 'Authenticité contrôlée', 'Paiement sous séquestre'],
      'cta1' => 'Voir la collection', 'cta2' => 'Créer un compte pro gratuit',
      'note' => "L'inscription est gratuite. Les prix de gros sont visibles dès que votre compte est validé.",
      'bye' => "Bien cordialement,\nL'équipe VESTRA",
      'legal' => "VESTRA est exploité par Acerasoft LLC. Ceci est un message professionnel unique de notre place de marché B2B vérifiée — vous le recevez car %s a été identifié comme partenaire commercial potentiel.\nVous ne souhaitez plus nous lire ? Désinscription immédiate : https://vestrasales.com/lead-unsubscribe",
      'shop' => 'votre boutique'],
    'it' => ['subject' => "Gallery Dept, Casablanca, Valentino, Lacoste e altro — all'ingrosso su VESTRA",
      'kicker' => 'Nuova stagione · solo per il trade', 'title' => "Gallery Dept, Casablanca e le maison che i vostri clienti chiedono — all'ingrosso.",
      'hello' => 'Buongiorno %s,', 'hello0' => 'Buongiorno,',
      'intro' => "VESTRA è un marketplace B2B verificato KYC dove le boutique indipendenti acquistano marchi di design e streetwear autentici a prezzi all'ingrosso. Ogni venditore è verificato, l'autenticità è controllata e il pagamento è protetto da escrow fino all'arrivo dell'ordine.",
      'focus' => 'In primo piano', 'grid' => 'Dalla selezione attuale',
      'trust' => ['Venditori verificati', 'Autenticità controllata', 'Pagamento protetto da escrow'],
      'cta1' => 'Scopri la collezione', 'cta2' => 'Crea un account trade gratuito',
      'note' => "La registrazione è gratuita. I prezzi all'ingrosso sono visibili appena il vostro account viene approvato.",
      'bye' => "Cordiali saluti,\nIl team VESTRA",
      'legal' => "VESTRA è gestito da Acerasoft LLC. Questo è un messaggio commerciale unico del nostro marketplace B2B verificato — lo ricevete perché %s è stato individuato come possibile partner commerciale.\nNon volete più ricevere messaggi? Disiscrizione immediata: https://vestrasales.com/lead-unsubscribe",
      'shop' => 'la vostra boutique'],
    'es' => ['subject' => 'Gallery Dept, Casablanca, Valentino, Lacoste y más — al por mayor en VESTRA',
      'kicker' => 'Nueva temporada · solo profesionales', 'title' => 'Gallery Dept, Casablanca y las marcas que piden sus clientes — al por mayor.',
      'hello' => 'Hola %s,', 'hello0' => 'Hola,',
      'intro' => 'VESTRA es un marketplace B2B verificado KYC donde las boutiques independientes compran marcas de diseño y streetwear auténticas a precio mayorista. Cada vendedor está verificado, la autenticidad se comprueba y el pago está protegido en depósito hasta que llega su pedido.',
      'focus' => 'Destacados', 'grid' => 'De la selección actual',
      'trust' => ['Vendedores verificados', 'Autenticidad comprobada', 'Pago protegido en depósito'],
      'cta1' => 'Ver la colección', 'cta2' => 'Crear una cuenta profesional gratis',
      'note' => 'El registro es gratuito. Los precios mayoristas se ven en cuanto se aprueba su cuenta.',
      'bye' => "Un cordial saludo,\nEl equipo de VESTRA",
      'legal' => "VESTRA está operado por Acerasoft LLC. Este es un mensaje comercial único de nuestro marketplace B2B verificado — lo recibe porque %s fue identificado como posible socio comercial.\n¿No quiere recibir más mensajes? Darse de baja al instante: https://vestrasales.com/lead-unsubscribe",
      'shop' => 'su boutique'],
    'nl' => ['subject' => 'Gallery Dept, Casablanca, Valentino, Lacoste & meer — groothandel op VESTRA',
      'kicker' => 'Nieuw seizoen · alleen voor de handel', 'title' => 'Gallery Dept, Casablanca en de merken waar uw klanten om vragen — in de groothandel.',
      'hello' => 'Hallo %s,', 'hello0' => 'Hallo,',
      'intro' => 'VESTRA is een KYC-geverifieerde B2B-marktplaats waar onafhankelijke boetieks authentieke designer- en streetwearmerken tegen groothandelsprijzen inkopen. Elke verkoper is geverifieerd, de echtheid wordt gecontroleerd en de betaling is via escrow beschermd tot uw bestelling binnen is.',
      'focus' => 'Uitgelicht', 'grid' => 'Uit de actuele selectie',
      'trust' => ['Geverifieerde verkopers', 'Echtheid gecontroleerd', 'Betaling via escrow'],
      'cta1' => 'Bekijk de collectie', 'cta2' => 'Gratis handelsaccount aanmaken',
      'note' => 'Registreren is gratis. Groothandelsprijzen zijn zichtbaar zodra uw account is goedgekeurd.',
      'bye' => "Met vriendelijke groet,\nHet VESTRA-team",
      'legal' => "VESTRA wordt beheerd door Acerasoft LLC. Dit is een eenmalig zakelijk bericht van onze geverifieerde B2B-groothandelsmarktplaats — u ontvangt het omdat %s als mogelijke handelspartner is aangemerkt.\nGeen berichten meer? Direct afmelden: https://vestrasales.com/lead-unsubscribe",
      'shop' => 'uw boetiek'],
    'pt' => ['subject' => 'Gallery Dept, Casablanca, Valentino, Lacoste e mais — atacado na VESTRA',
      'kicker' => 'Nova estação · só para profissionais', 'title' => 'Gallery Dept, Casablanca e as marcas que os seus clientes pedem — por atacado.',
      'hello' => 'Olá %s,', 'hello0' => 'Olá,',
      'intro' => 'A VESTRA é um marketplace B2B verificado KYC onde boutiques independentes compram marcas de design e streetwear autênticas a preço de atacado. Cada vendedor é verificado, a autenticidade é controlada e o pagamento fica protegido em custódia até a encomenda chegar.',
      'focus' => 'Em destaque', 'grid' => 'Da seleção atual',
      'trust' => ['Vendedores verificados', 'Autenticidade controlada', 'Pagamento em custódia'],
      'cta1' => 'Ver a coleção', 'cta2' => 'Criar conta profissional grátis',
      'note' => 'O registo é gratuito. Os preços de atacado ficam visíveis assim que a sua conta for aprovada.',
      'bye' => "Com os melhores cumprimentos,\nA equipa VESTRA",
      'legal' => "A VESTRA é operada pela Acerasoft LLC. Esta é uma mensagem comercial única do nosso marketplace B2B verificado — recebe-a porque %s foi identificado como possível parceiro comercial.\nNão quer receber mais mensagens? Cancelar já: https://vestrasales.com/lead-unsubscribe",
      'shop' => 'a sua boutique'],
  ];
  return $T[$lang] ?? $T['en'];
}

/** Katalogdan görseller: [spotlight => [marka => [ürün…]], grid => [ürün…]]. Ürün: brand, name, img, url. */
function vestra_edit_pick(int $gridN = 3): array {   // 10 Eki: öne çıkan 5 marka → seçki tek sıra
  $all = function_exists('vestra_live_listings') ? vestra_live_listings() : [];
  $norm = static function (array $p): ?array {
    $imgs = $p['images'] ?? []; $img = is_array($imgs) && $imgs ? (string)$imgs[0] : (string)($p['image'] ?? '');
    if ($img === '' || ($p['id'] ?? '') === '') return null;
    if (!preg_match('#^https?://#i', $img)) $img = 'https://vestrasales.com'.(str_starts_with($img, '/') ? '' : '/').$img;
    return ['brand' => trim((string)($p['brand'] ?? '')), 'name' => trim((string)($p['name'] ?? '')), 'img' => $img,
            'url' => 'https://vestrasales.com/product?id='.rawurlencode((string)$p['id']).'&'.VESTRA_EDIT_UTM];
  };
  $spot = []; $used = [];
  foreach (VESTRA_EDIT_SPOTLIGHT as $label => $re) {
    $spot[$label] = [];
    foreach ($all as $p) {
      if (count($spot[$label]) >= 2) break;
      if (!preg_match($re, (string)($p['brand'] ?? ''))) continue;
      if (($n = $norm($p)) === null) continue;
      $spot[$label][] = $n; $used[strtolower($n['brand'])] = true;
    }
  }
  /* Izgara: en çok ürünü olan markalardan marka başına 1 görsel. */
  $count = [];
  foreach ($all as $p) { $b = trim((string)($p['brand'] ?? '')); if ($b !== '' && preg_match(VESTRA_EDIT_PREMIUM, $b)) $count[$b] = ($count[$b] ?? 0) + 1; }
  arsort($count);
  $grid = [];
  foreach (array_keys($count) as $b) {
    if (count($grid) >= $gridN) break;
    if (isset($used[strtolower($b)])) continue;
    foreach ($all as $p) {
      if (trim((string)($p['brand'] ?? '')) !== $b) continue;
      if (($n = $norm($p)) === null) continue;
      $grid[] = $n; $used[strtolower($b)] = true; break;
    }
  }
  return ['spotlight' => $spot, 'grid' => $grid, 'brands' => array_slice(array_keys($count), 0, 16)];
}

/** [konu, düz gövde, opts] — vestra_html_email ile çizilir. */
function vestra_campaign_edit(string $company = '', string $lang = 'en'): array {
  if (function_exists('vestra_name_is_bare_domain') && vestra_name_is_bare_domain($company)) $company = '';
  $t = vestra_edit_texts($lang); $pick = vestra_edit_pick();
  $h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
  $cat = 'https://vestrasales.com/catalog?'.VESTRA_EDIT_UTM;
  $reg = 'https://vestrasales.com/register?type=buyer&'.VESTRA_EDIT_UTM;
  $hello = $company !== '' ? sprintf($t['hello'], $company) : $t['hello0'];
  $gold = '#a97f2c'; $ink = '#14110c'; $mut = '#8a8272';
  $sec = static fn(string $x) => '<div style="color:'.$gold.';font-size:11px;font-weight:700;letter-spacing:.22em;text-transform:uppercase;text-align:center;margin:26px 0 12px">'.$x.'</div>';
  $tile = static function (array $p, int $w) use ($h, $ink, $mut): string {
    return '<a href="'.$h($p['url']).'" style="text-decoration:none;color:'.$ink.'">'
      .'<img src="'.$h($p['img']).'" width="'.$w.'" alt="'.$h($p['brand'].' '.$p['name']).'" style="display:block;width:100%;max-width:'.$w.'px;height:auto;border-radius:10px;border:1px solid #ece6d8;background:#f7f4ee">'
      .'<div style="font-family:Georgia,serif;font-size:13px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;margin:8px 0 2px">'.$h($p['brand']).'</div>'
      .'<div style="font-size:12px;color:'.$mut.';line-height:1.35">'.$h(mb_strimwidth($p['name'], 0, 46, '…', 'UTF-8')).'</div></a>';
  };
  $btn = static fn(string $label, string $url, bool $solid) => '<a href="'.$h($url).'" style="display:block;text-align:center;text-decoration:none;font-family:Georgia,serif;font-size:15px;font-weight:700;letter-spacing:.03em;padding:15px 18px;border-radius:999px;'
    .($solid ? 'background:'.$ink.';color:#f4ecd8;border:1px solid '.$ink : 'background:#ffffff;color:'.$ink.';border:1.5px solid '.$gold).'">'.$h($label).'</a>';

  $html = '<p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#3a3428">'.$h($hello).'</p>'
        . '<p style="margin:0 0 6px;font-size:15px;line-height:1.65;color:#3a3428">'.$h($t['intro']).'</p>';

  /* Öne çıkanlar: Gallery Dept + Casablanca büyük (iki sütun), Lacoste / Fred Perry / Valentino ikinci sırada
     (üç sütun). Görsel yoksa marka kutusu (her istemcide görünür). */
  $spotCell = static function (string $label, array $items, int $w, int $pad, int $fs) use ($tile, $h, $ink): string {
    return $items ? $tile($items[0], $w)
      : '<a href="'.$h('https://vestrasales.com/catalog?brand='.rawurlencode($label).'&'.VESTRA_EDIT_UTM).'" style="display:block;text-decoration:none;background:'.$ink.';border-radius:10px;padding:'.$pad.'px 8px;text-align:center">'
        .'<span style="font-family:Georgia,serif;color:#d8bd86;font-size:'.$fs.'px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;white-space:nowrap">'.$h($label).'</span></a>';
  };
  $html .= $sec($h($t['focus']));
  $spotRows = [array_slice($pick['spotlight'], 0, 2, true), array_slice($pick['spotlight'], 2, null, true)];
  foreach ($spotRows as $ri => $row) {
    if (!$row) continue;
    $n = count($row); $w = $n <= 2 ? 248 : 160;
    $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 '.($ri === 0 ? 12 : 4).'px"><tr>';
    foreach ($row as $label => $items)
      $html .= '<td valign="top" width="'.(int)floor(100 / max(1, $n)).'%" style="padding:0 5px">'.$spotCell($label, $items, $w, $n <= 2 ? 46 : 34, $n <= 2 ? 19 : 14).'</td>';
    $html .= '</tr></table>';
  }

  if ($pick['grid']) {
    $html .= $sec($h($t['grid'])).'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse">';
    foreach (array_chunk($pick['grid'], 3) as $row) {
      $html .= '<tr>';
      foreach ($row as $p) $html .= '<td valign="top" width="33%" style="padding:0 5px 14px">'.$tile($p, 160).'</td>';
      for ($i = count($row); $i < 3; $i++) $html .= '<td width="33%"></td>';
      $html .= '</tr>';
    }
    $html .= '</table>';
  }

  $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:14px 0 22px;background:#f7f2e7;border-radius:10px"><tr>';
  foreach ($t['trust'] as $tr) $html .= '<td align="center" width="33%" style="padding:12px 6px;font-size:12px;color:#5a4a26;font-weight:700">✓ '.$h($tr).'</td>';
  $html .= '</tr></table>'
        . $btn($t['cta1'], $cat, true).'<div style="height:10px;line-height:10px">&nbsp;</div>'.$btn($t['cta2'], $reg, false)
        . '<p style="margin:12px 0 4px;font-size:12px;line-height:1.5;color:'.$mut.';text-align:center">'.$h($t['note']).'</p>';
  if ($pick['brands']) {
    $links = [];
    foreach ($pick['brands'] as $b) $links[] = '<a href="'.$h('https://vestrasales.com/catalog?brand='.rawurlencode($b).'&'.VESTRA_EDIT_UTM).'" style="color:'.$ink.';text-decoration:none;white-space:nowrap">'.str_replace(' ', '&nbsp;', $h($b)).'</a>';
    $html .= '<p style="margin:18px 0 4px;font-size:11.5px;line-height:1.9;color:'.$mut.';text-align:center;letter-spacing:.04em">'.implode(' &nbsp;·&nbsp; ', $links).'</p>';
  }
  $html .= '<p style="margin:22px 0 0;font-size:15px;line-height:1.6;color:#3a3428">'.nl2br($h($t['bye'])).'</p>';

  /* Düz metin: aynı içerik + bağlantılar. Künye ayraçtan sonra. */
  $lines = [$hello, '', $t['intro'], '', strtoupper($t['focus']).': '.implode(', ', array_keys(VESTRA_EDIT_SPOTLIGHT))];
  foreach ($pick['spotlight'] as $items) foreach ($items as $p) $lines[] = '- '.$p['brand'].' — '.$p['name'].': '.$p['url'];
  if ($pick['grid']) { $lines[] = ''; $lines[] = $t['grid'].':'; foreach ($pick['grid'] as $p) $lines[] = '- '.$p['brand'].' — '.$p['name'].': '.$p['url']; }
  $lines = array_merge($lines, ['', implode(' · ', $t['trust']), '', $t['cta1'].': '.$cat, $t['cta2'].': '.$reg, $t['note'], '', $t['bye']]);
  $body = implode("\n", $lines)."\n\n—\n".sprintf($t['legal'], $company !== '' ? $company : $t['shop']);

  return [$t['subject'], $body, ['html' => $html, 'hero' => ['kicker' => $t['kicker'], 'title' => $t['title']]]];
}
