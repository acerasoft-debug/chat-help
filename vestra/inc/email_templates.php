<?php
/**
 * VESTRA — localized premium email templates for order/offer, message and
 * membership notifications (registration's own verify/welcome templates stay
 * in inc/notify.php next to vestra_send_mail()).
 *
 * Every function here returns [$subject, $bodyPlain, $opts] — $opts is the
 * structured badge/rows/button data vestra_html_email() (inc/notify.php)
 * renders on top of the plain-text body. Callers pass $bodyPlain AND $opts
 * straight into vestra_send_mail($to,$subject,$body,$replyTo,$fromName,$cfg,
 * $heroImage,$opts).
 *
 * Language: always resolved from the RECIPIENT's stored account field
 * (vestra_user_lang() in inc/i18n.php), never from the current request's
 * vlang() — these fire from someone else's request (the other party in a
 * deal, or an admin action), so there is no live language cookie to read.
 */

/* Short, localized name for a doc_requests 'type' — the stored 'note' field is a full
 * "please upload..." instruction, not a noun phrase that reads naturally inside a
 * sentence like "your ___ was approved", so this is a separate small vocabulary. Falls
 * back to the raw type string for any custom/unknown type an admin might request. */
function vestra_doc_type_label(string $lang, string $type): string {
  $L = [
    'en'=>['trade_licence'=>'trade licence / business registration','company_reg'=>'company registration certificate','vat_cert'=>'VAT/tax registration certificate','id_document'=>'government-issued ID','auth_letter'=>'authorization letter'],
    'de'=>['trade_licence'=>'Gewerbeschein','company_reg'=>'Handelsregisterauszug','vat_cert'=>'Umsatzsteuer-/Steuerregistrierungsnachweis','id_document'=>'amtlichen Ausweis','auth_letter'=>'Vollmachtsschreiben'],
    'fr'=>['trade_licence'=>'licence commerciale / extrait d\'immatriculation','company_reg'=>'extrait Kbis / immatriculation','vat_cert'=>'justificatif de TVA','id_document'=>'pièce d\'identité officielle','auth_letter'=>'lettre d\'autorisation'],
    'it'=>['trade_licence'=>'licenza commerciale / visura di attività','company_reg'=>'visura camerale','vat_cert'=>'certificato di partita IVA','id_document'=>'documento d\'identità','auth_letter'=>'lettera di autorizzazione'],
    'es'=>['trade_licence'=>'licencia de actividad / alta censal','company_reg'=>'certificado de registro mercantil','vat_cert'=>'certificado de IVA','id_document'=>'documento de identidad oficial','auth_letter'=>'carta de autorización'],
  ];
  return ($L[$lang] ?? $L['en'])[$type] ?? $type;
}

/**
 * Sign-off for mail a person at VESTRA writes — support replies and admin-started threads —
 * as opposed to the system's own notifications, which stay unsigned.
 *
 * Pass the result as $opts['signature']; vestra_html_email() renders the card and the
 * text/plain alternative gets the same lines (see vestra_email_signature_html()).
 *
 * The reply address is read from config rather than written here so it can never drift from
 * the identity the mail is actually sent under.
 */
function vestra_support_signature(string $lang='en'): array {
  $roles=[
    'en'=>'Client Services', 'de'=>'Kundenbetreuung', 'fr'=>'Service clients',
    'it'=>'Servizio clienti', 'es'=>'Atención al cliente',
  ];
  return [
    'name'  => 'VESTRA Support',
    'role'  => $roles[$lang] ?? $roles['en'],
    'email' => (string)(function_exists('vestra_cfg') ? vestra_cfg('mail_from','support@vestrasales.com') : 'support@vestrasales.com'),
    'site'  => 'vestrasales.com',
  ];
}

/* Shared vocabulary reused across templates (row labels, button labels, status
 * badges) so every template speaks the same terms instead of drifting. */
function vestra_email_labels(string $lang): array {
  $L = [
    'en' => [
      'product'=>'Product','qty'=>'Quantity','unit_price'=>'Unit price','total'=>'Total',
      'colours'=>'Colours','ref'=>'Reference','message'=>'Message','plan'=>'Plan','amount'=>'Amount',
      'counter_price'=>'Counter price',
      'btn_seller_offers'=>'View & respond','btn_buyer_offers'=>'View in my dashboard',
      'btn_messages'=>'Open conversation','btn_orders_seller'=>'View order','btn_orders_buyer'=>'View order',
      'btn_dashboard'=>'Go to my dashboard',
      'badge_new_offer'=>'💶 New offer','badge_offer_received'=>'✓ Offer received',
      'badge_accepted'=>'✓ Offer accepted','badge_declined'=>'✗ Offer declined','badge_countered'=>'↩ Counter offer',
      'badge_message'=>'💬 New message','badge_verified'=>'✅ Account verified','badge_plan'=>'⭐ Plan updated',
      'badge_released'=>'✓ Funds released','badge_refunded'=>'↩ Refunded',
      'btn_listings'=>'View my listings','badge_listing_live'=>'🎉 Listing live','badge_listing_changes'=>'✎ Changes requested',
    ],
    'de' => [
      'product'=>'Produkt','qty'=>'Menge','unit_price'=>'Stückpreis','total'=>'Gesamt',
      'colours'=>'Farben','ref'=>'Referenz','message'=>'Nachricht','plan'=>'Tarif','amount'=>'Betrag',
      'counter_price'=>'Gegenangebot',
      'btn_seller_offers'=>'Ansehen & antworten','btn_buyer_offers'=>'In meinem Dashboard ansehen',
      'btn_messages'=>'Unterhaltung öffnen','btn_orders_seller'=>'Bestellung ansehen','btn_orders_buyer'=>'Bestellung ansehen',
      'btn_dashboard'=>'Zu meinem Dashboard',
      'badge_new_offer'=>'💶 Neues Angebot','badge_offer_received'=>'✓ Angebot erhalten',
      'badge_accepted'=>'✓ Angebot angenommen','badge_declined'=>'✗ Angebot abgelehnt','badge_countered'=>'↩ Gegenangebot',
      'badge_message'=>'💬 Neue Nachricht','badge_verified'=>'✅ Konto verifiziert','badge_plan'=>'⭐ Tarif aktualisiert',
      'badge_released'=>'✓ Guthaben freigegeben','badge_refunded'=>'↩ Rückerstattet',
      'btn_listings'=>'Meine Angebote ansehen','badge_listing_live'=>'🎉 Angebot live','badge_listing_changes'=>'✎ Änderungen erforderlich',
    ],
    'fr' => [
      'product'=>'Produit','qty'=>'Quantité','unit_price'=>'Prix unitaire','total'=>'Total',
      'colours'=>'Couleurs','ref'=>'Référence','message'=>'Message','plan'=>'Formule','amount'=>'Montant',
      'counter_price'=>'Contre-offre',
      'btn_seller_offers'=>'Voir et répondre','btn_buyer_offers'=>'Voir dans mon espace',
      'btn_messages'=>'Ouvrir la conversation','btn_orders_seller'=>'Voir la commande','btn_orders_buyer'=>'Voir la commande',
      'btn_dashboard'=>'Accéder à mon espace',
      'badge_new_offer'=>'💶 Nouvelle offre','badge_offer_received'=>'✓ Offre reçue',
      'badge_accepted'=>'✓ Offre acceptée','badge_declined'=>'✗ Offre refusée','badge_countered'=>'↩ Contre-offre',
      'badge_message'=>'💬 Nouveau message','badge_verified'=>'✅ Compte vérifié','badge_plan'=>'⭐ Formule mise à jour',
      'badge_released'=>'✓ Fonds libérés','badge_refunded'=>'↩ Remboursé',
      'btn_listings'=>'Voir mes annonces','badge_listing_live'=>'🎉 Annonce en ligne','badge_listing_changes'=>'✎ Modifications requises',
    ],
    'it' => [
      'product'=>'Prodotto','qty'=>'Quantità','unit_price'=>'Prezzo unitario','total'=>'Totale',
      'colours'=>'Colori','ref'=>'Riferimento','message'=>'Messaggio','plan'=>'Piano','amount'=>'Importo',
      'counter_price'=>'Controfferta',
      'btn_seller_offers'=>'Visualizza e rispondi','btn_buyer_offers'=>'Vedi nella mia area',
      'btn_messages'=>'Apri la conversazione','btn_orders_seller'=>'Visualizza ordine','btn_orders_buyer'=>'Visualizza ordine',
      'btn_dashboard'=>'Vai alla mia area',
      'badge_new_offer'=>'💶 Nuova offerta','badge_offer_received'=>'✓ Offerta ricevuta',
      'badge_accepted'=>'✓ Offerta accettata','badge_declined'=>'✗ Offerta rifiutata','badge_countered'=>'↩ Controfferta',
      'badge_message'=>'💬 Nuovo messaggio','badge_verified'=>'✅ Account verificato','badge_plan'=>'⭐ Piano aggiornato',
      'badge_released'=>'✓ Fondi rilasciati','badge_refunded'=>'↩ Rimborsato',
      'btn_listings'=>'Vedi i miei annunci','badge_listing_live'=>'🎉 Annuncio online','badge_listing_changes'=>'✎ Modifiche richieste',
    ],
    'es' => [
      'product'=>'Producto','qty'=>'Cantidad','unit_price'=>'Precio unitario','total'=>'Total',
      'colours'=>'Colores','ref'=>'Referencia','message'=>'Mensaje','plan'=>'Plan','amount'=>'Importe',
      'counter_price'=>'Contraoferta',
      'btn_seller_offers'=>'Ver y responder','btn_buyer_offers'=>'Ver en mi panel',
      'btn_messages'=>'Abrir conversación','btn_orders_seller'=>'Ver pedido','btn_orders_buyer'=>'Ver pedido',
      'btn_dashboard'=>'Ir a mi panel',
      'badge_new_offer'=>'💶 Nueva oferta','badge_offer_received'=>'✓ Oferta recibida',
      'badge_accepted'=>'✓ Oferta aceptada','badge_declined'=>'✗ Oferta rechazada','badge_countered'=>'↩ Contraoferta',
      'badge_message'=>'💬 Nuevo mensaje','badge_verified'=>'✅ Cuenta verificada','badge_plan'=>'⭐ Plan actualizado',
      'badge_released'=>'✓ Fondos liberados','badge_refunded'=>'↩ Reembolsado',
      'btn_listings'=>'Ver mis anuncios','badge_listing_live'=>'🎉 Anuncio publicado','badge_listing_changes'=>'✎ Cambios necesarios',
    ],
  ];
  return $L[$lang] ?? $L['en'];
}

/* Buyer's confirmation right after submitting an offer. */
function vestra_tpl_offer_received(string $lang, string $buyerName, string $product, string $sku, int $qty, float $price, float $total, string $ref, string $colorsTxt): array {
  $Lb = vestra_email_labels($lang);
  $rows = [
    ['label'=>$Lb['product'],'value'=>$product.($sku!==''?" ({$sku})":'')],
    ['label'=>$Lb['qty'],'value'=>(string)$qty],
    ['label'=>$Lb['unit_price'],'value'=>'€'.number_format($price,2)],
  ];
  if ($colorsTxt !== '') $rows[] = ['label'=>$Lb['colours'],'value'=>$colorsTxt];
  $rows[] = ['label'=>$Lb['total'],'value'=>'€'.number_format($total,2),'strong'=>true];
  $rows[] = ['label'=>$Lb['ref'],'value'=>$ref];
  $opts = ['badge'=>$Lb['badge_offer_received'],'rows'=>$rows,'button'=>['label'=>$Lb['btn_buyer_offers'],'url'=>'https://vestrasales.com/buyer?tab=offers']];
  $T = [
    'en'=>["VESTRA — offer %2\$s received", "Hello %1\$s,\n\nWe have received your offer. The seller will review and respond shortly — you can track its status any time in your buyer dashboard."],
    'de'=>["VESTRA — Angebot %2\$s erhalten", "Hallo %1\$s,\n\nWir haben Ihr Angebot erhalten. Der Verkäufer prüft es und antwortet in Kürze — den Status sehen Sie jederzeit in Ihrem Käufer-Dashboard."],
    'fr'=>["VESTRA — offre %2\$s reçue", "Bonjour %1\$s,\n\nNous avons bien reçu votre offre. Le vendeur va l'examiner et vous répondre rapidement — vous pouvez suivre son statut à tout moment dans votre espace acheteur."],
    'it'=>["VESTRA — offerta %2\$s ricevuta", "Ciao %1\$s,\n\nAbbiamo ricevuto la tua offerta. Il venditore la esaminerà e risponderà a breve — puoi seguirne lo stato in qualsiasi momento nella tua area acquirente."],
    'es'=>["VESTRA — oferta %2\$s recibida", "Hola %1\$s,\n\nHemos recibido tu oferta. El vendedor la revisará y responderá en breve — puedes consultar su estado en cualquier momento desde tu panel de comprador."],
  ];
  [$subjT,$bodyT] = $T[$lang] ?? $T['en'];
  $subject = sprintf($subjT, $buyerName, $ref);
  $body = sprintf($bodyT, $buyerName) . "\n\n—\nVESTRA · vestrasales.com";
  return [$subject, $body, $opts];
}

/* Seller's notification of a new incoming offer. */
function vestra_tpl_offer_new(string $lang, string $sellerName, string $buyerCompany, string $product, string $sku, int $qty, float $price, float $total, string $ref, string $colorsTxt, string $message): array {
  $Lb = vestra_email_labels($lang);
  $rows = [
    ['label'=>$Lb['product'],'value'=>$product.($sku!==''?" ({$sku})":'')],
    ['label'=>$Lb['qty'],'value'=>(string)$qty],
    ['label'=>$Lb['unit_price'],'value'=>'€'.number_format($price,2)],
  ];
  if ($colorsTxt !== '') $rows[] = ['label'=>$Lb['colours'],'value'=>$colorsTxt];
  $rows[] = ['label'=>$Lb['total'],'value'=>'€'.number_format($total,2),'strong'=>true];
  $rows[] = ['label'=>$Lb['ref'],'value'=>$ref];
  $opts = ['badge'=>$Lb['badge_new_offer'],'rows'=>$rows,'button'=>['label'=>$Lb['btn_seller_offers'],'url'=>'https://vestrasales.com/seller?tab=offers']];
  $msgLine = $message !== '' ? "\n\n".$Lb['message'].": ".$message : '';
  $T = [
    'en'=>["VESTRA — new offer from %2\$s (%3\$s)", "Hello %1\$s,\n\nYou received a new offer on VESTRA from %2\$s.%4\$s"],
    'de'=>["VESTRA — neues Angebot von %2\$s (%3\$s)", "Hallo %1\$s,\n\nSie haben ein neues Angebot auf VESTRA von %2\$s erhalten.%4\$s"],
    'fr'=>["VESTRA — nouvelle offre de %2\$s (%3\$s)", "Bonjour %1\$s,\n\nVous avez reçu une nouvelle offre sur VESTRA de la part de %2\$s.%4\$s"],
    'it'=>["VESTRA — nuova offerta da %2\$s (%3\$s)", "Ciao %1\$s,\n\nHai ricevuto una nuova offerta su VESTRA da %2\$s.%4\$s"],
    'es'=>["VESTRA — nueva oferta de %2\$s (%3\$s)", "Hola %1\$s,\n\nHas recibido una nueva oferta en VESTRA de %2\$s.%4\$s"],
  ];
  [$subjT,$bodyT] = $T[$lang] ?? $T['en'];
  $subject = sprintf($subjT, $sellerName, $buyerCompany, $ref);
  $body = sprintf($bodyT, $sellerName, $buyerCompany, $ref, $msgLine) . "\n\n—\nVESTRA · vestrasales.com";
  return [$subject, $body, $opts];
}

/* Buyer notification when the seller accepts / declines / counters. $counterPrice only
 * used when $action==='counter'. */
function vestra_tpl_offer_response(string $lang, string $action, string $buyerName, string $product, string $ref, ?float $counterPrice): array {
  $Lb = vestra_email_labels($lang);
  $rows = [['label'=>$Lb['product'],'value'=>$product],['label'=>$Lb['ref'],'value'=>$ref]];
  $badge = $Lb['badge_accepted'];
  if ($action === 'decline') $badge = $Lb['badge_declined'];
  if ($action === 'counter') { $badge = $Lb['badge_countered']; $rows[] = ['label'=>$Lb['counter_price'],'value'=>'€'.number_format((float)$counterPrice,2),'strong'=>true]; }
  $opts = ['badge'=>$badge,'rows'=>$rows,'button'=>['label'=>$Lb['btn_buyer_offers'],'url'=>'https://vestrasales.com/buyer?tab=offers']];
  $T = [
    'en'=>[
      'accept'  => ["VESTRA — your offer on %2\$s was accepted ✓", "Hello %1\$s,\n\nGreat news — the seller accepted your offer. Your invoice will be available in your buyer dashboard shortly."],
      'decline' => ["VESTRA — your offer on %2\$s was declined", "Hello %1\$s,\n\nThe seller declined your offer. You can browse similar listings or message the seller directly from your dashboard."],
      'counter' => ["VESTRA — counter offer on %2\$s", "Hello %1\$s,\n\nThe seller has countered your offer. You can accept, decline, or reply from your dashboard."],
    ],
    'de'=>[
      'accept'  => ["VESTRA — Ihr Angebot für %2\$s wurde angenommen ✓", "Hallo %1\$s,\n\ngute Nachricht — der Verkäufer hat Ihr Angebot angenommen. Ihre Rechnung finden Sie in Kürze in Ihrem Käufer-Dashboard."],
      'decline' => ["VESTRA — Ihr Angebot für %2\$s wurde abgelehnt", "Hallo %1\$s,\n\nder Verkäufer hat Ihr Angebot abgelehnt. Sie können ähnliche Angebote durchsuchen oder den Verkäufer direkt aus Ihrem Dashboard kontaktieren."],
      'counter' => ["VESTRA — Gegenangebot für %2\$s", "Hallo %1\$s,\n\nder Verkäufer hat Ihnen ein Gegenangebot gemacht. Sie können es in Ihrem Dashboard annehmen, ablehnen oder beantworten."],
    ],
    'fr'=>[
      'accept'  => ["VESTRA — votre offre sur %2\$s a été acceptée ✓", "Bonjour %1\$s,\n\nbonne nouvelle — le vendeur a accepté votre offre. Votre facture sera bientôt disponible dans votre espace acheteur."],
      'decline' => ["VESTRA — votre offre sur %2\$s a été refusée", "Bonjour %1\$s,\n\nle vendeur a refusé votre offre. Vous pouvez parcourir des articles similaires ou contacter directement le vendeur depuis votre espace."],
      'counter' => ["VESTRA — contre-offre sur %2\$s", "Bonjour %1\$s,\n\nle vendeur vous a fait une contre-offre. Vous pouvez l'accepter, la refuser ou y répondre depuis votre espace."],
    ],
    'it'=>[
      'accept'  => ["VESTRA — la tua offerta su %2\$s è stata accettata ✓", "Ciao %1\$s,\n\nottima notizia — il venditore ha accettato la tua offerta. La fattura sarà presto disponibile nella tua area acquirente."],
      'decline' => ["VESTRA — la tua offerta su %2\$s è stata rifiutata", "Ciao %1\$s,\n\nil venditore ha rifiutato la tua offerta. Puoi sfogliare articoli simili o scrivere direttamente al venditore dalla tua area."],
      'counter' => ["VESTRA — controfferta su %2\$s", "Ciao %1\$s,\n\nil venditore ti ha fatto una controfferta. Puoi accettarla, rifiutarla o rispondere dalla tua area."],
    ],
    'es'=>[
      'accept'  => ["VESTRA — tu oferta sobre %2\$s fue aceptada ✓", "Hola %1\$s,\n\nbuenas noticias — el vendedor aceptó tu oferta. Tu factura estará disponible en breve en tu panel de comprador."],
      'decline' => ["VESTRA — tu oferta sobre %2\$s fue rechazada", "Hola %1\$s,\n\nel vendedor rechazó tu oferta. Puedes explorar artículos similares o escribir directamente al vendedor desde tu panel."],
      'counter' => ["VESTRA — contraoferta sobre %2\$s", "Hola %1\$s,\n\nel vendedor te ha hecho una contraoferta. Puedes aceptarla, rechazarla o responder desde tu panel."],
    ],
  ];
  $set = $T[$lang] ?? $T['en'];
  [$subjT,$bodyT] = $set[$action] ?? $set['decline'];
  $subject = sprintf($subjT, $buyerName, $product);
  $body = sprintf($bodyT, $buyerName, $product) . "\n\n—\nVESTRA · vestrasales.com";
  return [$subject, $body, $opts];
}

/* "You have a new message" doorbell — deliberately content-free (the conversation itself
 * only ever lives on VESTRA), just localized + premium-wrapped. */
function vestra_tpl_message(string $lang, string $recipientName, string $fromLabel, string $panelUrl): array {
  $Lb = vestra_email_labels($lang);
  $opts = ['badge'=>$Lb['badge_message'],'button'=>['label'=>$Lb['btn_messages'],'url'=>$panelUrl]];
  $T = [
    'en'=>["VESTRA — new message from %2\$s", "Hello %1\$s,\n\nYou have a new message from %2\$s on VESTRA."],
    'de'=>["VESTRA — neue Nachricht von %2\$s", "Hallo %1\$s,\n\nSie haben eine neue Nachricht von %2\$s auf VESTRA."],
    'fr'=>["VESTRA — nouveau message de %2\$s", "Bonjour %1\$s,\n\nVous avez un nouveau message de %2\$s sur VESTRA."],
    'it'=>["VESTRA — nuovo messaggio da %2\$s", "Ciao %1\$s,\n\nHai un nuovo messaggio da %2\$s su VESTRA."],
    'es'=>["VESTRA — nuevo mensaje de %2\$s", "Hola %1\$s,\n\nTienes un nuevo mensaje de %2\$s en VESTRA."],
  ];
  [$subjT,$bodyT] = $T[$lang] ?? $T['en'];
  $subject = sprintf($subjT, $recipientName, $fromLabel);
  $body = sprintf($bodyT, $recipientName, $fromLabel) . "\n\n—\nVESTRA · vestrasales.com";
  return [$subject, $body, $opts];
}

/* KYB/account verification approved — the "you're fully unlocked" moment. Currently
 * push-only; this is the reliable email fallback for anyone without push enabled. */
function vestra_tpl_kyb_approved(string $lang, string $name, string $type, string $panelUrl): array {
  $Lb = vestra_email_labels($lang);
  $opts = ['badge'=>$Lb['badge_verified'],'button'=>['label'=>$Lb['btn_dashboard'],'url'=>$panelUrl]];
  $roleWord = ['en'=>$type==='seller'?'seller':'buyer','de'=>$type==='seller'?'Verkäufer':'Käufer',
    'fr'=>$type==='seller'?'vendeur':'acheteur','it'=>$type==='seller'?'venditore':'acquirente',
    'es'=>$type==='seller'?'vendedor':'comprador'];
  $T = [
    'en'=>["VESTRA — your account is verified ✓", "Hello %1\$s,\n\nYour business is now verified as a %2\$s on VESTRA. Full wholesale access is unlocked — welcome aboard!"],
    'de'=>["VESTRA — Ihr Konto ist verifiziert ✓", "Hallo %1\$s,\n\nIhr Unternehmen ist jetzt als %2\$s auf VESTRA verifiziert. Der vollständige Großhandelszugang ist freigeschaltet — willkommen an Bord!"],
    'fr'=>["VESTRA — votre compte est vérifié ✓", "Bonjour %1\$s,\n\nVotre entreprise est désormais vérifiée en tant que %2\$s sur VESTRA. L'accès complet au catalogue de gros est débloqué — bienvenue !"],
    'it'=>["VESTRA — il tuo account è verificato ✓", "Ciao %1\$s,\n\nLa tua azienda è ora verificata come %2\$s su VESTRA. L'accesso completo all'ingrosso è sbloccato — benvenuto a bordo!"],
    'es'=>["VESTRA — tu cuenta está verificada ✓", "Hola %1\$s,\n\nTu empresa ya está verificada como %2\$s en VESTRA. El acceso completo al catálogo mayorista está desbloqueado — ¡bienvenido!"],
  ];
  [$subjT,$bodyT] = $T[$lang] ?? $T['en'];
  $subject = $subjT;
  $body = sprintf($bodyT, $name, $roleWord[$lang] ?? $roleWord['en']) . "\n\n—\nVESTRA · vestrasales.com";
  return [$subject, $body, $opts];
}

/* Welcome voucher — a personal first-order discount code for a registered customer.
   The code is printed in the body as well as sitting on the button, because a buyer who
   forwards the mail to whoever places their orders needs the code itself to survive the
   forward; a button alone does not. The link carries ?voucher= so the cart fills it in. */
function vestra_tpl_welcome_voucher(string $lang, string $name, string $code, string $valueLabel, string $expiry): array {
  $Lb  = vestra_email_labels($lang);
  $url = 'https://vestrasales.com/shop?voucher='.rawurlencode($code);
  $BTN   = ['en'=>'Browse the catalogue','de'=>'Zum Katalog','fr'=>'Voir le catalogue',
            'it'=>'Vai al catalogo','es'=>'Ver el catálogo'];
  $ROWL  = ['en'=>['Voucher code','Discount','Valid until'],'de'=>['Gutscheincode','Rabatt','Gültig bis'],
            'fr'=>['Code du bon','Remise','Valable jusqu\'au'],'it'=>['Codice buono','Sconto','Valido fino al'],
            'es'=>['Código del vale','Descuento','Válido hasta']];
  $rl = $ROWL[$lang] ?? $ROWL['en'];
  $KICK = ['en'=>'Welcome voucher','de'=>'Willkommensgutschein','fr'=>'Bon de bienvenue',
           'it'=>'Buono di benvenuto','es'=>'Vale de bienvenida'];
  $CAP  = ['en'=>'off your first wholesale order',
           'de'=>'Rabatt auf Ihre erste Großhandelsbestellung',
           'fr'=>'de remise sur votre première commande en gros',
           'it'=>'di sconto sul tuo primo ordine all\'ingrosso',
           'es'=>'de descuento en tu primer pedido mayorista'];
  /* The coupon block instead of the generic rows, and no badge above it. Through the rows
     the whole offer arrived as one grey line — the same furniture this shell puts under a
     plan change or an escrow release, for the one mail whose entire job is to carry a code.
     The badge is dropped because the coupon's own kicker already names it; keeping both
     printed "Your voucher" twice, six lines apart. */
  $opts = [
    'voucher' => [
      'kicker'       => $KICK[$lang] ?? $KICK['en'],
      'amount'       => $valueLabel,
      'caption'      => $CAP[$lang] ?? $CAP['en'],
      'code_label'   => $rl[0],
      'code'         => $code,
      'expiry_label' => $rl[2],
      'expiry'       => $expiry,
    ],
    'button' => ['label'=>$BTN[$lang] ?? $BTN['en'], 'url'=>$url],
  ];
  $T = [
    'en'=>["Your %2\$s welcome voucher for your first VESTRA order",
      "Hello %1\$s,\n\nThank you for registering with VESTRA. Here is %2\$s off your first wholesale order.\n\nYour code: %3\$s\n\nEnter it in the cart under \"Voucher code\" before placing the order. The code is tied to your account, can be used once, and is valid on a first order until %4\$s."],
    'de'=>["Ihr %2\$s Willkommensgutschein für Ihre erste VESTRA-Bestellung",
      "Hallo %1\$s,\n\nvielen Dank für Ihre Registrierung bei VESTRA. Hier sind %2\$s Rabatt auf Ihre erste Großhandelsbestellung.\n\nIhr Code: %3\$s\n\nGeben Sie ihn im Warenkorb unter \"Gutscheincode\" ein, bevor Sie die Bestellung abschicken. Der Code ist an Ihr Konto gebunden, einmal einlösbar und für eine Erstbestellung bis zum %4\$s gültig."],
    'fr'=>["Votre bon de bienvenue de %2\$s pour votre première commande VESTRA",
      "Bonjour %1\$s,\n\nMerci de votre inscription sur VESTRA. Voici %2\$s de remise sur votre première commande en gros.\n\nVotre code : %3\$s\n\nSaisissez-le dans le panier sous « Code du bon » avant de valider la commande. Le code est lié à votre compte, utilisable une fois, et valable sur une première commande jusqu'au %4\$s."],
    'it'=>["Il tuo buono di benvenuto del %2\$s per il primo ordine VESTRA",
      "Ciao %1\$s,\n\ngrazie per esserti registrato su VESTRA. Ecco %2\$s di sconto sul tuo primo ordine all'ingrosso.\n\nIl tuo codice: %3\$s\n\nInseriscilo nel carrello alla voce \"Codice buono\" prima di confermare l'ordine. Il codice è collegato al tuo account, utilizzabile una volta e valido su un primo ordine fino al %4\$s."],
    'es'=>["Tu vale de bienvenida del %2\$s para tu primer pedido VESTRA",
      "Hola %1\$s,\n\ngracias por registrarte en VESTRA. Aquí tienes un %2\$s de descuento en tu primer pedido mayorista.\n\nTu código: %3\$s\n\nIntrodúcelo en el carrito en \"Código del vale\" antes de confirmar el pedido. El código está vinculado a tu cuenta, se puede usar una vez y es válido en un primer pedido hasta el %4\$s."],
  ];
  [$subjT,$bodyT] = $T[$lang] ?? $T['en'];
  return [
    sprintf($subjT, $name, $valueLabel, $code, $expiry),
    sprintf($bodyT, $name, $valueLabel, $code, $expiry)."\n\n—\nVESTRA · vestrasales.com",
    $opts,
  ];
}

/* Membership tier changed (comp / manual upgrade by admin). */
function vestra_tpl_membership_changed(string $lang, string $name, string $tierLabel, string $panelUrl): array {
  $Lb = vestra_email_labels($lang);
  $opts = ['badge'=>$Lb['badge_plan'],'rows'=>[['label'=>$Lb['plan'],'value'=>$tierLabel,'strong'=>true]],'button'=>['label'=>$Lb['btn_dashboard'],'url'=>$panelUrl]];
  $T = [
    'en'=>["VESTRA — your plan is now %2\$s ⭐", "Hello %1\$s,\n\nYour VESTRA membership has been updated."],
    'de'=>["VESTRA — Ihr Tarif ist jetzt %2\$s ⭐", "Hallo %1\$s,\n\nIhre VESTRA-Mitgliedschaft wurde aktualisiert."],
    'fr'=>["VESTRA — votre formule est désormais %2\$s ⭐", "Bonjour %1\$s,\n\nVotre abonnement VESTRA a été mis à jour."],
    'it'=>["VESTRA — il tuo piano ora è %2\$s ⭐", "Ciao %1\$s,\n\nIl tuo abbonamento VESTRA è stato aggiornato."],
    'es'=>["VESTRA — tu plan ahora es %2\$s ⭐", "Hola %1\$s,\n\nTu membresía de VESTRA ha sido actualizada."],
  ];
  [$subjT,$bodyT] = $T[$lang] ?? $T['en'];
  $subject = sprintf($subjT, $name, $tierLabel);
  $body = sprintf($bodyT, $name, $tierLabel) . "\n\n—\nVESTRA · vestrasales.com";
  return [$subject, $body, $opts];
}

/* Escrow funds released to the seller (dispute resolved / order confirmed) — one function,
 * $role toggles the wording + dashboard link between the seller and the buyer side. */
function vestra_tpl_escrow_release(string $lang, string $name, string $role, string $ref, float $amount): array {
  $Lb = vestra_email_labels($lang);
  $panel = $role === 'seller' ? 'https://vestrasales.com/seller?tab=orders' : 'https://vestrasales.com/buyer?tab=orders';
  $opts = ['badge'=>$Lb['badge_released'],'rows'=>[['label'=>$Lb['ref'],'value'=>$ref],['label'=>$Lb['amount'],'value'=>'€'.number_format($amount,2),'strong'=>true]],
    'button'=>['label'=>$role==='seller'?$Lb['btn_orders_seller']:$Lb['btn_orders_buyer'],'url'=>$panel]];
  $T = [
    'en'=>['seller'=>["VESTRA — funds released for order %2\$s", "Hello %1\$s,\n\nVESTRA has released the held funds for your order — they're on their way to your bank."],
           'buyer' =>["VESTRA — order %2\$s resolved, funds released", "Hello %1\$s,\n\nYour order has been resolved — the held funds have been released to the seller."]],
    'de'=>['seller'=>["VESTRA — Guthaben für Bestellung %2\$s freigegeben", "Hallo %1\$s,\n\nVESTRA hat das einbehaltene Guthaben für Ihre Bestellung freigegeben — es ist auf dem Weg zu Ihrer Bank."],
           'buyer' =>["VESTRA — Bestellung %2\$s abgeschlossen, Guthaben freigegeben", "Hallo %1\$s,\n\nIhre Bestellung wurde abgeschlossen — das einbehaltene Guthaben wurde an den Verkäufer freigegeben."]],
    'fr'=>['seller'=>["VESTRA — fonds débloqués pour la commande %2\$s", "Bonjour %1\$s,\n\nVESTRA a débloqué les fonds retenus pour votre commande — ils sont en route vers votre banque."],
           'buyer' =>["VESTRA — commande %2\$s résolue, fonds débloqués", "Bonjour %1\$s,\n\nVotre commande a été résolue — les fonds retenus ont été versés au vendeur."]],
    'it'=>['seller'=>["VESTRA — fondi rilasciati per l'ordine %2\$s", "Ciao %1\$s,\n\nVESTRA ha rilasciato i fondi trattenuti per il tuo ordine — sono in arrivo sul tuo conto."],
           'buyer' =>["VESTRA — ordine %2\$s risolto, fondi rilasciati", "Ciao %1\$s,\n\nIl tuo ordine è stato risolto — i fondi trattenuti sono stati rilasciati al venditore."]],
    'es'=>['seller'=>["VESTRA — fondos liberados para el pedido %2\$s", "Hola %1\$s,\n\nVESTRA ha liberado los fondos retenidos de tu pedido — están en camino a tu banco."],
           'buyer' =>["VESTRA — pedido %2\$s resuelto, fondos liberados", "Hola %1\$s,\n\nTu pedido ha sido resuelto — los fondos retenidos se han liberado al vendedor."]],
  ];
  $set = $T[$lang] ?? $T['en'];
  [$subjT,$bodyT] = $set[$role] ?? $set['buyer'];
  $subject = sprintf($subjT, $name, $ref);
  $body = sprintf($bodyT, $name, $ref) . "\n\n—\nVESTRA · vestrasales.com";
  return [$subject, $body, $opts];
}

/* Escrow refund to the buyer (order cancelled). */
function vestra_tpl_escrow_refund(string $lang, string $name, string $role, string $ref, float $amount): array {
  $Lb = vestra_email_labels($lang);
  $panel = $role === 'seller' ? 'https://vestrasales.com/seller?tab=orders' : 'https://vestrasales.com/buyer?tab=orders';
  $opts = ['badge'=>$Lb['badge_refunded'],'rows'=>[['label'=>$Lb['ref'],'value'=>$ref],['label'=>$Lb['amount'],'value'=>'€'.number_format($amount,2),'strong'=>true]],
    'button'=>['label'=>$role==='seller'?$Lb['btn_orders_seller']:$Lb['btn_orders_buyer'],'url'=>$panel]];
  $T = [
    'en'=>['buyer' =>["VESTRA — order %2\$s refunded", "Hello %1\$s,\n\nYour order has been cancelled and refunded in full — the amount is being returned to your card."],
           'seller'=>["VESTRA — order %2\$s refunded to buyer", "Hello %1\$s,\n\nOrder %2\$s was cancelled — the buyer has been refunded in full and no funds will be released to you for it."]],
    'de'=>['buyer' =>["VESTRA — Bestellung %2\$s erstattet", "Hallo %1\$s,\n\nIhre Bestellung wurde storniert und vollständig erstattet — der Betrag wird auf Ihre Karte zurückerstattet."],
           'seller'=>["VESTRA — Bestellung %2\$s an Käufer erstattet", "Hallo %1\$s,\n\nBestellung %2\$s wurde storniert — der Käufer wurde vollständig erstattet, es wird kein Guthaben dafür an Sie ausgezahlt."]],
    'fr'=>['buyer' =>["VESTRA — commande %2\$s remboursée", "Bonjour %1\$s,\n\nVotre commande a été annulée et intégralement remboursée — le montant est en cours de retour sur votre carte."],
           'seller'=>["VESTRA — commande %2\$s remboursée à l'acheteur", "Bonjour %1\$s,\n\nLa commande %2\$s a été annulée — l'acheteur a été intégralement remboursé et aucun fonds ne vous sera versé pour celle-ci."]],
    'it'=>['buyer' =>["VESTRA — ordine %2\$s rimborsato", "Ciao %1\$s,\n\nIl tuo ordine è stato annullato e rimborsato per intero — l'importo sta per essere restituito sulla tua carta."],
           'seller'=>["VESTRA — ordine %2\$s rimborsato all'acquirente", "Ciao %1\$s,\n\nL'ordine %2\$s è stato annullato — l'acquirente è stato rimborsato per intero e non ti verrà rilasciato alcun fondo per questo ordine."]],
    'es'=>['buyer' =>["VESTRA — pedido %2\$s reembolsado", "Hola %1\$s,\n\nTu pedido ha sido cancelado y reembolsado por completo — el importe se está devolviendo a tu tarjeta."],
           'seller'=>["VESTRA — pedido %2\$s reembolsado al comprador", "Hola %1\$s,\n\nEl pedido %2\$s fue cancelado — el comprador ha sido reembolsado por completo y no se te liberarán fondos por este pedido."]],
  ];
  $set = $T[$lang] ?? $T['en'];
  [$subjT,$bodyT] = $set[$role] ?? $set['buyer'];
  $subject = sprintf($subjT, $name, $ref);
  $body = sprintf($bodyT, $name, $ref) . "\n\n—\nVESTRA · vestrasales.com";
  return [$subject, $body, $opts];
}

/* A seller's pending listing goes live. */
function vestra_tpl_listing_approved(string $lang, string $name, string $product): array {
  $Lb = vestra_email_labels($lang);
  $opts = ['badge'=>$Lb['badge_listing_live'],'rows'=>[['label'=>$Lb['product'],'value'=>$product]],
    'button'=>['label'=>$Lb['btn_listings'],'url'=>'https://vestrasales.com/seller?tab=listings']];
  $T = [
    'en'=>["VESTRA — %2\$s is now live 🎉", "Hello %1\$s,\n\nGood news — your listing has been approved and is now live in the VESTRA catalog."],
    'de'=>["VESTRA — %2\$s ist jetzt live 🎉", "Hallo %1\$s,\n\ngute Nachricht — Ihr Angebot wurde genehmigt und ist jetzt im VESTRA-Katalog live."],
    'fr'=>["VESTRA — %2\$s est maintenant en ligne 🎉", "Bonjour %1\$s,\n\nbonne nouvelle — votre annonce a été approuvée et est désormais en ligne dans le catalogue VESTRA."],
    'it'=>["VESTRA — %2\$s è ora online 🎉", "Ciao %1\$s,\n\nottima notizia — il tuo annuncio è stato approvato ed è ora online nel catalogo VESTRA."],
    'es'=>["VESTRA — %2\$s ya está en línea 🎉", "Hola %1\$s,\n\nbuenas noticias — tu anuncio ha sido aprobado y ya está en línea en el catálogo de VESTRA."],
  ];
  [$subjT,$bodyT] = $T[$lang] ?? $T['en'];
  $subject = sprintf($subjT, $name, $product);
  $body = sprintf($bodyT, $name) . "\n\n—\nVESTRA · vestrasales.com";
  return [$subject, $body, $opts];
}

/* A seller's pending listing was rejected / needs changes. $note is the admin's own text
 * (already in whatever language they wrote it — inserted as-is, not translated). */
function vestra_tpl_listing_rejected(string $lang, string $name, string $product, string $note): array {
  $Lb = vestra_email_labels($lang);
  $rows = [['label'=>$Lb['product'],'value'=>$product]];
  $opts = ['badge'=>$Lb['badge_listing_changes'],'rows'=>$rows,
    'button'=>['label'=>$Lb['btn_listings'],'url'=>'https://vestrasales.com/seller?tab=listings']];
  $noteLine = $note !== '' ? "\n\n".$note : '';
  $T = [
    'en'=>["VESTRA — %2\$s needs changes", "Hello %1\$s,\n\nYour listing wasn't approved as submitted and needs a few changes before it can go live.%3\$s"],
    'de'=>["VESTRA — %2\$s benötigt Änderungen", "Hallo %1\$s,\n\nIhr Angebot wurde in der eingereichten Form nicht genehmigt und benötigt einige Änderungen, bevor es live gehen kann.%3\$s"],
    'fr'=>["VESTRA — %2\$s nécessite des modifications", "Bonjour %1\$s,\n\nvotre annonce n'a pas été approuvée telle quelle et nécessite quelques modifications avant de pouvoir être publiée.%3\$s"],
    'it'=>["VESTRA — %2\$s richiede modifiche", "Ciao %1\$s,\n\nil tuo annuncio non è stato approvato così come inviato e richiede alcune modifiche prima di poter andare online.%3\$s"],
    'es'=>["VESTRA — %2\$s necesita cambios", "Hola %1\$s,\n\ntu anuncio no fue aprobado tal como se envió y necesita algunos cambios antes de poder publicarse.%3\$s"],
  ];
  [$subjT,$bodyT] = $T[$lang] ?? $T['en'];
  $subject = sprintf($subjT, $name, $product);
  $body = sprintf($bodyT, $name, $product, $noteLine) . "\n\n—\nVESTRA · vestrasales.com";
  return [$subject, $body, $opts];
}

/* One uploaded verification document reviewed (approved or rejected) — distinct from
 * vestra_tpl_kyb_approved(), which is the OVERALL account-verified moment once every
 * required document has been approved. $docLabel is the doc's own request note/type
 * (already human-readable, e.g. "company registration certificate"). */
function vestra_tpl_doc_reviewed(string $lang, string $name, string $status, string $docLabel, string $adminNote): array {
  $Lb = vestra_email_labels($lang);
  $approved = $status === 'approved';
  $badge = ['en'=>$approved?'✓ Document approved':'✎ Document needs changes',
    'de'=>$approved?'✓ Dokument genehmigt':'✎ Dokument benötigt Änderungen',
    'fr'=>$approved?'✓ Document approuvé':'✎ Document à corriger',
    'it'=>$approved?'✓ Documento approvato':'✎ Documento da correggere',
    'es'=>$approved?'✓ Documento aprobado':'✎ Documento requiere cambios'][$lang] ?? ($approved?'✓ Document approved':'✎ Document needs changes');
  $btnLabel = ['en'=>'Go to my documents','de'=>'Zu meinen Dokumenten','fr'=>'Voir mes documents',
    'it'=>'Vai ai miei documenti','es'=>'Ir a mis documentos'][$lang] ?? 'Go to my documents';
  $opts = ['badge'=>$badge,'button'=>['label'=>$btnLabel,'url'=>'https://vestrasales.com/seller?tab=kyc']];
  $noteLine = $adminNote !== '' ? "\n\n".$adminNote : '';
  $T = [
    'en'=>[
      true  => ["VESTRA — your %2\$s was approved ✓", "Hello %1\$s,\n\nYour uploaded %2\$s has been reviewed and approved."],
      false => ["VESTRA — your %2\$s needs changes", "Hello %1\$s,\n\nYour uploaded %2\$s couldn't be approved as submitted and needs a re-upload.%3\$s"],
    ],
    'de'=>[
      true  => ["VESTRA — Ihr %2\$s wurde genehmigt ✓", "Hallo %1\$s,\n\nIhr hochgeladenes Dokument (%2\$s) wurde geprüft und genehmigt."],
      false => ["VESTRA — %2\$s benötigt Änderungen", "Hallo %1\$s,\n\nIhr hochgeladenes Dokument (%2\$s) konnte in der eingereichten Form nicht genehmigt werden und muss erneut hochgeladen werden.%3\$s"],
    ],
    'fr'=>[
      true  => ["VESTRA — votre document (%2\$s) a été approuvé ✓", "Bonjour %1\$s,\n\nvotre document envoyé (%2\$s) a été examiné et approuvé."],
      false => ["VESTRA — votre document (%2\$s) nécessite des modifications", "Bonjour %1\$s,\n\nvotre document envoyé (%2\$s) n'a pas pu être approuvé tel quel et doit être renvoyé.%3\$s"],
    ],
    'it'=>[
      true  => ["VESTRA — il tuo documento (%2\$s) è stato approvato ✓", "Ciao %1\$s,\n\nil documento caricato (%2\$s) è stato esaminato e approvato."],
      false => ["VESTRA — il tuo documento (%2\$s) richiede modifiche", "Ciao %1\$s,\n\nil documento caricato (%2\$s) non è stato approvato così come inviato e deve essere ricaricato.%3\$s"],
    ],
    'es'=>[
      true  => ["VESTRA — tu documento (%2\$s) fue aprobado ✓", "Hola %1\$s,\n\ntu documento subido (%2\$s) ha sido revisado y aprobado."],
      false => ["VESTRA — tu documento (%2\$s) necesita cambios", "Hola %1\$s,\n\ntu documento subido (%2\$s) no pudo aprobarse tal como se envió y debe volver a subirse.%3\$s"],
    ],
  ];
  $set = $T[$lang] ?? $T['en'];
  [$subjT,$bodyT] = $set[$approved];
  $subject = sprintf($subjT, $name, $docLabel);
  $body = sprintf($bodyT, $name, $docLabel, $noteLine) . "\n\n—\nVESTRA · vestrasales.com";
  return [$subject, $body, $opts];
}

/**
 * Kabul edilen bir teklif icin "eksik bilgileri tamamla" e-postasi.
 *
 * Kabul bildirimi (vestra_tpl_offer_response) tek basina yetmiyor: alici "kabul
 * edildi" diyen bir e-posta aliyor ama fatura ve sevkiyat icin gereken bilgiler
 * hesabinda EKSIK olabiliyor ve bunu kimse istemiyor. Canli ornek O39419: teslimat
 * adresinde sehir/eyalet/ZIP yok, vergi numarasi ve sicil numarasi "n/a" yazili.
 * O halde siparis, kimsenin takip etmedigi bir bosluga giriyor.
 *
 * Sadece EKSIK olan maddeler yaziliyor. Zaten verilmis bir bilgiyi tekrar sormak
 * hem alicinin gozunde ozensiz duruyor hem de cevap oranini dusuruyor.
 *
 * Ingilizce tek dil: bu akis su an ABD/uluslararasi alicilar icin kullaniliyor ve
 * yanlis dilde gonderilen bir ticari talep, hic gondermemekten kotudur.
 */
function vestra_tpl_order_details_needed(
    string $buyerName, string $product, string $ref, int $qty, float $unit, float $total,
    string $colourNote = '', array $missing = [], float $usdRate = 0.0, string $rateNote = '',
    float $shipUsd = 0.0, string $leadTime = ''
): array {
    $rows = [
        ['label'=>'Product',    'value'=>$product],
        ['label'=>'Reference',  'value'=>$ref],
        ['label'=>'Quantity',   'value'=>$qty.' pcs'.($colourNote !== '' ? ' — '.$colourNote : '')],
        ['label'=>'Unit price', 'value'=>'€'.number_format($unit, 2)],
    ];

    /* Nakliye USD olarak belirleniyor ama siparis EUR. Ikisini ayni belgede yan yana
       birakmak ("EUR 1.798 + US$50") aliciya toplami KENDISININ hesaplamasini birakir
       ve iki para birimli bir toplam faturaya yazilamaz. O yuzden ucret ayni referans
       kuruyla euroya cevriliyor, dolar karsiligi parantezde kaliyor ve TOPLAM tek para
       biriminde veriliyor. */
    $shipEur = ($shipUsd > 0 && $usdRate > 0) ? round($shipUsd / $usdRate, 2) : 0.0;
    $grand   = $total + $shipEur;

    if ($shipUsd > 0 && $usdRate > 0) {
        $rows[] = ['label'=>'Goods',    'value'=>'€'.number_format($total, 2)];
        $rows[] = ['label'=>'Shipping', 'value'=>'€'.number_format($shipEur, 2).'  (US$'.number_format($shipUsd, 2).')'];
        $rows[] = ['label'=>'Total',    'value'=>'€'.number_format($grand, 2), 'strong'=>true];
    } elseif ($shipUsd > 0) {
        /* Kur yok: cevrim yapilamaz. Ucret kendi para biriminde ve AYRI duruyor;
           uydurma bir kurla euro yazmaktansa toplami vermemek dogru. */
        $rows[] = ['label'=>'Goods',    'value'=>'€'.number_format($total, 2), 'strong'=>true];
        $rows[] = ['label'=>'Shipping', 'value'=>'US$'.number_format($shipUsd, 2).' — invoiced in euro at the rate on the invoice date'];
    } else {
        $rows[] = ['label'=>'Total',    'value'=>'€'.number_format($total, 2), 'strong'=>true];
    }

    /* USD karsiligi ABD'li alici icin isi kolaylastiriyor, ama sozlesme EUR uzerinden:
       teklif EUR verildi, fatura EUR kesilecek. O yuzden satir "approx." diye ve KURU
       ACIKCA yazarak geciyor. Kursuz bir dolar rakami, aliciya sabit bir dolar fiyati
       taahhut etmis gibi okunur; odeme gunu kur oynayinca aradaki fark tartisma konusu
       olur. Kur cekilemezse satir HIC basilmiyor -- yanlis ya da eski bir kur, hic
       olmamasindan kotudur. */
    if ($usdRate > 0) {
        $rows[] = [
            'label' => 'Total (approx.)',
            'value' => 'US$'.number_format($grand * $usdRate, 2)
                     . ($rateNote !== '' ? '  ·  '.$rateNote : ''),
        ];
    }
    $opts = [
        'badge'  => 'Offer accepted',
        'rows'   => $rows,
        'button' => ['label'=>'Confirm my details', 'url'=>'https://vestrasales.com/buyer'],
    ];

    $ask = [];
    if (in_array('address', $missing, true)) {
        $ask[] = "1. Complete delivery address — including city, state and ZIP code, and the company name that should appear on the shipping documents.";
    }
    if (in_array('tax_id', $missing, true)) {
        $ask[] = ($ask ? count($ask)+1 : 1).". Billing details for the invoice — the company name and address exactly as they should appear, and your EIN (Federal Tax ID). Customs clearance requires a valid EIN for the importer of record.";
    }
    if (in_array('incoterm', $missing, true)) {
        $ask[] = (count($ask)+1).". Delivery terms — DAP (you act as importer of record and pay duties and clearance) or DDP (we arrange clearance and quote duties separately). Import duty on knitwear is significant, so we would rather agree this now than after dispatch.";
    }
    if (in_array('phone', $missing, true)) {
        $ask[] = (count($ask)+1).". A contact name and phone number for the carrier at the delivery address.";
    }
    $askTxt = $ask ? implode("\n\n", $ask) : "Please confirm the delivery address so we can prepare the shipment.";

    $subject = "VESTRA — offer {$ref} accepted · details required to complete your order";
    $body =
        "Dear {$buyerName},\n\n"
      . "We are pleased to confirm that your offer {$ref} has been accepted.\n\n"
      /* "Reply with the following" cevap yazma isi gibi okunuyor ve bir is
         e-postasinda erteleniyor. "Confirm your details" ise zaten sahip olduklari
         bilgiyi onaylamaya davet ediyor: daha kucuk bir istek, daha yuksek cevap. */
      . "To issue your invoice and prepare the shipment, please confirm your details below:\n\n"
      . $askTxt . "\n\n"
      . "Payment terms are 100% in advance. Once the above is confirmed we will issue the invoice with our banking details, and the goods are dispatched as soon as payment is received.\n\n"
      /* Sevkiyat aciklamasi. Iki sey acikca yaziliyor:
         - Mal AVRUPA'dan cikiyor. Alici ABD'de ve satici Delaware kayitli bir sirket;
           soylenmezse mal ABD ici bir depodan gelecek sanilir, oysa sevkiyat sinir
           asiyor. Bunu sonradan ogrenmek, teslim suresi ve gumruk beklentisini bozar.
         - Sure PARA ALINDIKTAN sonra basliyor. "Iki hafta"yi siparis tarihinden sayan
           bir alici, odemeyi uc gun sonra yaparsa gecikmis gibi hisseder. */
      . ($leadTime !== ''
          ? "Dispatch and delivery: the goods are checked at our warehouse before dispatch and ship from Europe. Total delivery time is {$leadTime} on average, counted from receipt of payment.\n\n"
          : '')
      /* Kur satiri bilgi kutusunda "approx." diye geciyor; govdede de bir kez daha
         soyluyoruz ki alici dolar rakamini sabit fiyat sanmasin. Faturayi EUR kesip
         e-postada dolar yazip sonra "aslinda kur degisti" demek, satisi degil guveni
         kaybettirir. */
      . ($shipUsd > 0
          ? "Shipping is charged at a flat US$".number_format($shipUsd, 2).", shown above converted to euro.\n\n"
          : '')
      . ($usdRate > 0
          ? "The US dollar figure above is indicative only, converted at the reference rate shown. The order and the invoice are in euro, and the amount received depends on the rate applied by your bank on the day of payment.\n\n"
          : '')
      . "If any of these details change the delivery country, please tell us — it affects the export paperwork, and we would rather correct it before the invoice is issued than after.\n\n"
      . "—\n"
      . "VESTRA · Acerasoft LLC\n"
      . "8 The Green, Suite B, Dover, Delaware 19901, USA\n"
      . "support@vestrasales.com · vestrasales.com";
    return [$subject, $body, $opts];
}

/**
 * The letter a newly registered seller gets: what to do, in the order it has to happen.
 *
 * Four asks, and the order is the point. Publishing is free and needs nothing, so it comes
 * first and the seller can act on it today; the catalogue offer removes the work that
 * actually stops people (typing hundreds of references by hand); the commission card is
 * required before money moves; Stripe is optional and clearly labelled as such. Putting the
 * two payment steps first would read as a bill arriving before any benefit.
 *
 * Every link and label names something that exists in the seller panel — "Commission card",
 * "Payouts & Escrow (Stripe)", both under /seller?tab=profile. A welcome letter that sends
 * someone hunting for a button that is not there costs more trust than it builds.
 *
 * @param float $rate Commission as a fraction (0.035) — printed, never hardcoded in the
 *                    text, so a rate change in one constant cannot leave this letter lying.
 */
function vestra_tpl_seller_onboarding(string $lang, string $name, float $rate, bool $isCompany = true): array {
    /* Ispanyolcada ondalik ayirici VIRGUL: "3.5%" bir Ispanyol okura makine
       cevirisi gibi gorunur, "3,5 %" dogal. Yuzde isaretinden onceki bosluk da
       Ispanyolca yazim kuralidir. */
    $pct  = $lang === 'es' ? number_format($rate * 100, 1, ',', '.').' %' : number_format($rate * 100, 1).'%';
    /* Bir SIRKETE "Estimado/a Calzados Pili Perez:" diye hitap edilmez -- tekil
       nezaket kalibi kisiye aittir. Ticari yazismada sirkete "Estimados senores:"
       yazilir ve sirket adi ilk cumlede gecer. Sahis ise "Estimado/a X:" dogru.
       Ayrimi cagiran biliyor (hesapta company mi name mi doluydu), tahmin
       etmiyoruz. */
    $greetEs = $isCompany ? 'Estimados señores:' : "Estimado/a {$name}:";
    $openEs  = $isCompany
        ? "Bienvenidos a VESTRA. La cuenta de vendedor de {$name} ya está activa y pueden empezar hoy mismo."
        : "Bienvenido a VESTRA. Su cuenta de vendedor ya está activa y puede empezar hoy mismo.";
    $opts = [
        'badge'  => $lang === 'es' ? 'Cuenta activa · plataforma gratuita' : 'Account active · platform free',
        'button' => [
            'label' => $lang === 'es' ? 'Abrir mi panel' : 'Open my dashboard',
            'url'   => 'https://vestrasales.com/seller',
        ],
    ];

    if ($lang === 'es') {
        /* Ispanyolcada nezaket kalibi FIILI de degistirir. "Estimados senores:"
           deyip govdede "puede/envienos" (tekil) devam etmek, bir Ispanyol okura
           yarim cevrilmis metin gibi gorunur. Iyelik sifatlari (su/sus) iki kalipta
           da ayni oldugu icin yalnizca fiiller degisiyor -- asagidaki cift. */
        $vPueden   = $isCompany ? 'pueden'    : 'puede';
        $vEnvien   = $isCompany ? 'envíennos' : 'envíenos';
        $vRespondan= $isCompany ? 'respondan' : 'responda';
        $vQuieren  = $isCompany ? 'quieren'   : 'quiere';
        $vConecten = $isCompany ? 'conecten'  : 'conecte';
        $vCompleten= $isCompany ? 'completen' : 'complete';
        $vVendan   = $isCompany ? 'vendan'    : 'venda';
        $vTienen   = $isCompany ? 'tienen'    : 'tiene';
        $vDisponen = $isCompany ? 'disponen'  : 'dispone';
        $vNecesitan= $isCompany ? 'necesitan' : 'necesita';
        $vPrefieren= $isCompany ? 'prefieren' : 'prefiere';
        $vConsideren=$isCompany ? 'consideren': 'considere';

        $subject = 'VESTRA — su cuenta de vendedor está activa: la plataforma es gratuita';
        $body =
            $greetEs."\n\n"
          . $openEs."\n\n"

          . "1) La plataforma es gratuita por el momento\n"
          . "Hoy por hoy publicar en VESTRA no cuesta nada: sin cuota de alta, sin mensualidad "
          . "y sin límite de referencias. ".ucfirst($vPueden)." subir su surtido completo de hombre, mujer "
          . "y niño.\n\n"

          . "2) Su catálogo: lo damos de alta nosotros\n"
          . "Si {$vDisponen} de catálogo o tarifa (Excel, PDF o un enlace), {$vEnvien} el archivo "
          . "respondiendo a este correo y nos encargamos de dar de alta los artículos. Para cada "
          . "referencia nos ayuda tener: código, descripción, materiales, tallas, precio mayorista, "
          . "pedido mínimo y fotografías.\n"
          /* Sinir "olabilir" diye geciyor, "vardir" diye degil: henuz konmus bir sinir
             yok ve olmayan bir kurali varmis gibi yazmak, sonradan geri almasi zor bir
             beklenti yaratir. Oncelik sorusu ayni cumlede: karsi taraf 400 referansi
             birden gondermek zorunda hissetmesin. */
          . "Más adelante es posible que establezcamos algún límite de referencias, pero para "
          . "esta primera fase {$vPueden} enviarnos el catálogo completo o, si lo {$vPrefieren}, "
          . "empezar por las líneas que {$vConsideren} prioritarias.\n\n"

          . "3) Tarjeta para las comisiones\n"
          . "Para poder liquidar la comisión de la plataforma {$vNecesitan} registrar una tarjeta "
          . "en su cuenta. Se cobra únicamente el {$pct} sobre los pedidos que {$vVendan}, y solo "
          . "cuando el pago del comprador está confirmado — no hay cargos fijos.\n"
          . "Panel → Perfil → «Commission card» → «Add commission card».\n\n"

          . "4) Venta con pago protegido (nuestra recomendación)\n"
          /* Tavsiye BASA aliniyor, sonundaki "opsiyonel"den once: sonda kalsaydi
             okuyan once "istege bagli" gorup maddeyi atlardi. Ikisi celismiyor --
             tavsiye ediyoruz ama zorunlu degil, ve ikisini de acikca soyluyoruz. */
          . "Es el método que recomendamos, sobre todo para las primeras operaciones con un "
          . "comprador nuevo. Si {$vQuieren} ofrecer a sus clientes una compra con garantía, {$vConecten} su cuenta "
          . "con Stripe desde el panel y {$vCompleten} los pasos de verificación. Con el depósito "
          . "en garantía el importe queda retenido hasta que el comprador confirma la recepción, y "
          . "su liquidación (menos la comisión) se abona automáticamente. Stripe verifica su "
          . "identidad y sus datos bancarios; VESTRA no los ve en ningún momento.\n"
          . "Panel → Perfil → «Payouts & Escrow (Stripe)» → «Set up Stripe payouts».\n"
          . "Es opcional: la transferencia bancaria contra factura sigue funcionando sin esto, pero "
          . "un primer pedido se cierra con mucha más facilidad cuando el comprador ve el pago "
          . "protegido.\n\n"

          . "Si {$vTienen} cualquier duda, {$vRespondan} a este mensaje y le ayudamos.\n\n"
          . "—\n"
          . "VESTRA · Acerasoft LLC\n"
          . "8 The Green, Suite B, Dover, Delaware 19901, USA\n"
          . "support@vestrasales.com · vestrasales.com";
        return [$subject, $body, $opts];
    }

    $subject = 'VESTRA — your seller account is active: the platform is free';
    $body =
        "Dear {$name},\n\n"
      . "Welcome to VESTRA. Your seller account is active and you can start today.\n\n"
      . "1) The platform is free for now\n"
      . "As things stand, listing on VESTRA costs you nothing: no joining fee, no monthly charge "
      . "and no limit on the number of references. You can upload your full range for men, women "
      . "and children.\n\n"
      . "2) Your catalogue: we create the listings\n"
      . "If you have a catalogue or price list (Excel, PDF or a link), reply to this email and we "
      . "will create the listings for you. For each reference it helps to have: code, description, "
      . "materials, sizes, wholesale price, minimum order and photographs.\n"
      . "We may introduce a limit on the number of references later on, but for this first stage "
      . "you can send the full catalogue — or start with the lines you consider a priority.\n\n"
      . "3) Card for commission\n"
      . "To settle the platform commission we need a card on file. Only {$pct} is charged on the "
      . "orders you sell, and only once the buyer's payment is confirmed — there are no fixed fees.\n"
      . "Dashboard → Profile → \"Commission card\" → \"Add commission card\".\n\n"
      . "4) Protected payment (our recommendation)\n"
      . "This is the method we recommend, particularly for first orders with a new buyer. "
      . "To offer your customers a guaranteed purchase, connect your account to Stripe from the "
      . "dashboard and complete the verification steps. With escrow the amount is held until the "
      . "buyer confirms delivery, and your payout (less commission) is released automatically. "
      . "Stripe verifies your identity and bank details; VESTRA never sees them.\n"
      . "Dashboard → Profile → \"Payouts & Escrow (Stripe)\" → \"Set up Stripe payouts\".\n"
      . "This is optional: bank transfer against an invoice still works without it, but a first "
      . "order closes far more easily when the buyer sees protected payment.\n\n"
      . "If anything is unclear, reply to this message and we will help.\n\n"
      . "—\n"
      . "VESTRA · Acerasoft LLC\n"
      . "8 The Green, Suite B, Dover, Delaware 19901, USA\n"
      . "support@vestrasales.com · vestrasales.com";
    return [$subject, $body, $opts];
}

/**
 * A person's name as a letter should open with it.
 *
 * Registration stores exactly what was typed, and people type their name in the same
 * lowercase they use for a password field — the live account for this buyer holds
 * "samuel kozak". Printed straight into a salutation that reads "Dear samuel kozak,",
 * which on a commercial invoice looks like a mail merge that went wrong.
 *
 * Only an all-lowercase name is touched. That single condition is what makes this safe:
 * a name carrying any capital was written deliberately, and title-casing it would break
 * exactly the names that most need leaving alone — "McDonald" would become "Mcdonald",
 * "van der Berg" would become "Van Der Berg", "DKNY" would become "Dkny". The stored
 * value is never rewritten; this is a display rule, and the account keeps what its owner
 * typed.
 */
function vestra_display_name(string $name): string {
    $name = trim($name);
    if ($name === '' || preg_match('/\p{Lu}/u', $name)) return $name;
    return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
}

/**
 * The letter a buyer gets when the invoice is issued and attached.
 *
 * A PDF arriving on its own is a demand with no context: the reader has to open it to find
 * out what it is for, what the amount covers and what they are expected to do next. The
 * figures are therefore repeated in the body — not because the PDF is untrustworthy, but
 * because a purchasing clerk reads the mail on a phone and forwards it to whoever pays.
 *
 * The one instruction that earns its place: quote the order reference on the transfer. A
 * five-figure payment that arrives carrying whatever the payer's clerk typed has to be
 * matched by hand, and until it is matched the goods do not move.
 *
 * @param string $money  Currency symbol already chosen by the caller ('US$' / '€') — the
 *                       invoice and the letter must not disagree about which one it is.
 */
function vestra_tpl_invoice_issued(
    string $buyerName, string $product, string $ref, string $invoiceNo,
    int $qty, string $colourNote, float $goods, float $shipping, float $total,
    string $money = '€', string $incoterms = '', string $leadTime = '', string $fxNote = ''
): array {
    $fmt = fn(float $n) => $money.number_format($n, 2);
    $rows = [
        ['label'=>'Invoice',    'value'=>$invoiceNo],
        ['label'=>'Order ref',  'value'=>$ref],
        ['label'=>'Product',    'value'=>$product],
        ['label'=>'Quantity',   'value'=>$qty.' pcs'.($colourNote !== '' ? ' — '.$colourNote : '')],
    ];
    /* Nakliye varsa mal ve nakliye AYRI satirda: tek bir "Toplam" gosterip icinde
       navlun oldugunu soylememek, aliciya faturayi acip cikarma yaptiriyor. */
    if ($shipping > 0) {
        $rows[] = ['label'=>'Goods',    'value'=>$fmt($goods)];
        $rows[] = ['label'=>'Shipping', 'value'=>$fmt($shipping)];
    }
    $rows[] = ['label'=>'Total due', 'value'=>$fmt($total), 'strong'=>true];

    $opts = [
        'badge'  => 'Invoice issued',
        'rows'   => $rows,
        'button' => ['label'=>'View my order', 'url'=>'https://vestrasales.com/buyer?tab=offers'],
    ];

    $subject = "VESTRA — invoice {$invoiceNo} for order {$ref}";
    $buyerName = vestra_display_name($buyerName);
    $body =
        "Dear {$buyerName},\n\n"
      . "Thank you for confirming your details. The invoice for order {$ref} is attached, "
      . "and a copy is available in your VESTRA account.\n\n"
      . "Invoice {$invoiceNo} — total ".$fmt($total)
      . ($shipping > 0 ? ", including shipping" : "").".\n\n"
      . "Payment terms are 100% in advance. Please transfer the full amount to the account "
      . "shown on the invoice and quote reference {$ref} — that reference is what matches "
      . "your transfer to this order.\n\n"
      /* Malin AVRUPA'dan ciktigi ve surenin ODEME ALINDIKTAN sonra basladigi, kabul
         mektubunda ne icin yazildiysa burada da ayni sebeple yaziliyor: satici
         Delaware kayitli, alici ABD'de -- soylenmezse mal ic piyasadan gelecek
         sanilir, ve "iki hafta"yi siparis gununden sayan alici kendi odemesinin
         gecikmesini bize yazar. */
      . ($leadTime !== ''
          ? "Dispatch and delivery: the goods are checked at our warehouse before dispatch "
            ."and ship from Europe. Total delivery time is {$leadTime} on average, counted "
            ."from receipt of payment.\n\n"
          : '')
      . ($incoterms !== '' ? "Delivery terms: {$incoterms}.\n\n" : '')
      /* $fxNote bazen noktayla biter bazen bitmez; kosulsuz nokta "piece.." uretiyordu. */
      . ($fxNote !== '' ? rtrim($fxNote, '.').".\n\n" : '')
      /* Duzeltmeyi ODEMEDEN once istemek, hem aliciya hem bize is kazandiriyor:
         kesilmis bir faturanin numarasi geri alinamaz, odeme sonrasi duzeltme
         alacak dekontu + yeni fatura demek. */
      . "If anything on the invoice needs correcting — the company name, the address or the "
      . "tax ID — please tell us before you pay. Once payment has been made a correction "
      . "means a credit note and a new invoice.\n\n"
      . "—\n"
      . "VESTRA · Acerasoft LLC\n"
      . "8 The Green, Suite B, Dover, Delaware 19901, USA\n"
      . "support@vestrasales.com · vestrasales.com";
    return [$subject, $body, $opts];
}

/**
 * The note a buyer gets when their order moves to a new stage.
 *
 * Written per language rather than through t(): t() resolves against the ACTIVE
 * request's language, and the active request here is the admin's, not the buyer's.
 * Sending a French boutique an English email while their own panel reads
 * "En cours de préparation" is the inconsistency this exists to avoid. Same shape as
 * vestra_verify_text() and the seller onboarding letter, which solved it the same way.
 *
 * @param string $stage 'preparing' | 'to_vestra' | 'cancelled'
 */
function vestra_tpl_order_stage(string $lang, string $stage, string $name, string $ref): array {
    $lang = in_array($lang, ['en','fr','es','it','de'], true) ? $lang : 'en';

    $L = [
      'en' => [
        'hi'    => "Hello {$name},",
        'ref'   => "Order ref: {$ref}",
        'track' => 'Track your order:',
        'btn'   => 'View my order',
        'preparing' => ["VESTRA — order {$ref} is being prepared",
          'Your order is now being prepared for despatch. We will write again as soon as it moves.'],
        'to_vestra' => ["VESTRA — order {$ref} is on its way to VESTRA",
          'The goods have left the supplier and are in transit to VESTRA, where they are checked before they go out to you.'],
        'cancelled' => ["VESTRA — order {$ref} has been cancelled",
          'Your order has been cancelled. If that is unexpected, reply to this message and we will look into it.'],
      ],
      'fr' => [
        'hi'    => "Bonjour {$name},",
        'ref'   => "Référence de commande : {$ref}",
        'track' => 'Suivre ma commande :',
        'btn'   => 'Voir ma commande',
        'preparing' => ["VESTRA — la commande {$ref} est en cours de préparation",
          'Votre commande est en cours de préparation pour l’expédition. Nous vous réécrivons dès qu’elle avance.'],
        'to_vestra' => ["VESTRA — la commande {$ref} est en route vers VESTRA",
          'La marchandise a quitté le fournisseur et est en route vers VESTRA, où elle est contrôlée avant de vous être expédiée.'],
        'cancelled' => ["VESTRA — la commande {$ref} a été annulée",
          'Votre commande a été annulée. Si cela vous surprend, répondez à ce message et nous vérifierons.'],
      ],
      'es' => [
        'hi'    => "Hola {$name}:",
        'ref'   => "Referencia del pedido: {$ref}",
        'track' => 'Seguir mi pedido:',
        'btn'   => 'Ver mi pedido',
        'preparing' => ["VESTRA — el pedido {$ref} se está preparando",
          'Su pedido se está preparando para el envío. Le escribiremos de nuevo en cuanto avance.'],
        'to_vestra' => ["VESTRA — el pedido {$ref} va camino de VESTRA",
          'La mercancía ha salido del proveedor y está en camino a VESTRA, donde se revisa antes de enviársela a usted.'],
        'cancelled' => ["VESTRA — el pedido {$ref} ha sido cancelado",
          'Su pedido ha sido cancelado. Si no lo esperaba, responda a este mensaje y lo revisamos.'],
      ],
      'it' => [
        'hi'    => "Buongiorno {$name},",
        'ref'   => "Riferimento ordine: {$ref}",
        'track' => 'Segui il mio ordine:',
        'btn'   => 'Vedi il mio ordine',
        'preparing' => ["VESTRA — l’ordine {$ref} è in preparazione",
          'Il suo ordine è in preparazione per la spedizione. Le scriveremo di nuovo appena si muove.'],
        'to_vestra' => ["VESTRA — l’ordine {$ref} è in viaggio verso VESTRA",
          'La merce è partita dal fornitore ed è in viaggio verso VESTRA, dove viene controllata prima di essere spedita a lei.'],
        'cancelled' => ["VESTRA — l’ordine {$ref} è stato annullato",
          'Il suo ordine è stato annullato. Se non se lo aspettava, risponda a questo messaggio e verifichiamo.'],
      ],
      'de' => [
        'hi'    => "Guten Tag {$name},",
        'ref'   => "Bestellnummer: {$ref}",
        'track' => 'Bestellung verfolgen:',
        'btn'   => 'Meine Bestellung ansehen',
        'preparing' => ["VESTRA — Bestellung {$ref} wird vorbereitet",
          'Ihre Bestellung wird jetzt für den Versand vorbereitet. Wir melden uns wieder, sobald sie weitergeht.'],
        'to_vestra' => ["VESTRA — Bestellung {$ref} ist unterwegs zu VESTRA",
          'Die Ware hat den Lieferanten verlassen und ist unterwegs zu VESTRA, wo sie geprüft wird, bevor sie an Sie hinausgeht.'],
        'cancelled' => ["VESTRA — Bestellung {$ref} wurde storniert",
          'Ihre Bestellung wurde storniert. Falls das unerwartet kommt, antworten Sie einfach auf diese Nachricht und wir sehen nach.'],
      ],
    ];

    $d = $L[$lang];
    $stage = isset($d[$stage]) && is_array($d[$stage]) ? $stage : 'preparing';
    [$subject, $line] = $d[$stage];

    $url  = 'https://vestrasales.com/buyer?tab=orders';
    $body = $d['hi']."\n\n".$line."\n\n".$d['ref']."\n\n".$d['track']." ".$url
          . "\n\n—\nVESTRA · Acerasoft LLC\nsupport@vestrasales.com · vestrasales.com";

    return [$subject, $body, ['button' => ['label' => $d['btn'], 'url' => $url]]];
}

/**
 * Short follow-up to a prospect who was written to weeks ago and has not replied.
 *
 * Deliberately NOT the campaign again. A second copy of the same letter reads as a
 * mailing list; a short human note asking whether the first one arrived reads as a
 * person, and it is also the honest thing — we do not know whether it was seen.
 *
 * Every version ends with the same offer to stop. A follow-up without one is what
 * turns a cold approach into a complaint, and a complaint costs the sending domain
 * far more than the one reply it might have won.
 *
 * Languages match the ones the campaign itself sends in (inc/notify.php's country
 * map); anything else falls back to English rather than being machine-translated
 * into a language nobody here can check.
 */
function vestra_tpl_lead_followup(string $lang, string $company): array {
    $co = trim($company);
    $L = [
      'en' => ["VESTRA — following up on our note",
        "Hello".($co !== '' ? " ".$co : '').",",
        "We wrote to you a few weeks ago about VESTRA, a B2B wholesale marketplace for branded fashion where every seller is KYC-verified and every order runs on invoice terms.",
        "I am writing once more only to ask whether that message reached you, and whether it is of any interest. If it is, I would be glad to send the current catalogue or answer anything specific.",
        "If it is not relevant to your business, just say so and we will not write again."],
      'el' => ["VESTRA — υπενθύμιση για το προηγούμενο μήνυμά μας",
        "Γεια σας".($co !== '' ? " ".$co : '').",",
        "Σας γράψαμε πριν από μερικές εβδομάδες σχετικά με τη VESTRA, μια αγορά χονδρικής B2B για επώνυμη μόδα, όπου κάθε πωλητής είναι πιστοποιημένος (KYC) και κάθε παραγγελία γίνεται με τιμολόγιο.",
        "Σας γράφω ξανά μόνο για να ρωτήσω αν έφτασε εκείνο το μήνυμα και αν σας ενδιαφέρει. Αν ναι, ευχαρίστως να σας στείλω τον τρέχοντα κατάλογο ή να απαντήσω σε ό,τι χρειάζεστε.",
        "Αν δεν αφορά την επιχείρησή σας, πείτε μας το απλώς και δεν θα ξαναγράψουμε."],
      'ja' => ["VESTRA — 先日のご案内について",
        ($co !== '' ? $co." " : '')."ご担当者様",
        "数週間前に、ブランドファッションのB2B卸売マーケットプレイス「VESTRA」についてご案内を差し上げました。出品者はすべてKYC認証済みで、ご注文はインボイス（請求書）条件で進みます。",
        "本日は、その案内が届いていたかどうか、またご関心をお持ちいただけるかどうかだけ、あらためてお伺いしたくご連絡いたしました。ご希望でしたら最新のカタログをお送りしますし、ご質問にもお答えいたします。",
        "貴社に関係のない内容でしたら、その旨お知らせください。以後ご連絡はいたしません。"],
      'ko' => ["VESTRA — 지난 안내에 대한 후속 연락",
        ($co !== '' ? $co." " : '')."담당자님께",
        "몇 주 전에 브랜드 패션 B2B 도매 마켓플레이스 VESTRA를 소개해 드린 바 있습니다. 모든 판매자는 KYC 인증을 거치며, 모든 주문은 인보이스 조건으로 진행됩니다.",
        "그 메일이 잘 도착했는지, 그리고 관심이 있으신지만 여쭙고자 다시 연락드립니다. 원하시면 현재 카탈로그를 보내드리거나 궁금하신 점에 답변드리겠습니다.",
        "귀사와 관련이 없다면 말씀만 주시면 다시 연락드리지 않겠습니다."],
      'de' => ["VESTRA — Nachfrage zu unserer Nachricht",
        "Guten Tag".($co !== '' ? " ".$co : '').",",
        "Wir haben Ihnen vor einigen Wochen zu VESTRA geschrieben, einem B2B-Großhandelsmarktplatz für Markenmode, auf dem jeder Verkäufer KYC-geprüft ist und jede Bestellung auf Rechnung läuft.",
        "Ich melde mich nur noch einmal, um zu fragen, ob diese Nachricht bei Ihnen angekommen ist und ob sie für Sie interessant ist. Gerne sende ich Ihnen den aktuellen Katalog oder beantworte konkrete Fragen.",
        "Falls es für Ihr Geschäft nicht passt, sagen Sie einfach Bescheid — dann schreiben wir nicht wieder."],
      'fr' => ["VESTRA — suite à notre message",
        "Bonjour".($co !== '' ? " ".$co : '').",",
        "Nous vous avons écrit il y a quelques semaines au sujet de VESTRA, une place de marché B2B de gros pour la mode de marque, où chaque vendeur est vérifié (KYC) et chaque commande se règle sur facture.",
        "Je reviens vers vous uniquement pour savoir si ce message vous est bien parvenu et s'il vous intéresse. Si oui, je vous envoie volontiers le catalogue actuel ou je réponds à vos questions.",
        "Si cela ne concerne pas votre activité, dites-le nous simplement et nous ne réécrirons pas."],
      'it' => ["VESTRA — seguito al nostro messaggio",
        "Buongiorno".($co !== '' ? " ".$co : '').",",
        "Le avevamo scritto qualche settimana fa a proposito di VESTRA, un marketplace B2B all'ingrosso per la moda di marca, dove ogni venditore è verificato (KYC) e ogni ordine viaggia con fattura.",
        "Le scrivo solo per sapere se quel messaggio Le è arrivato e se può interessarLe. In tal caso Le invio volentieri il catalogo attuale o rispondo a domande specifiche.",
        "Se non riguarda la Sua attività, basta dircelo e non scriveremo più."],
      'es' => ["VESTRA — seguimiento de nuestro mensaje",
        "Buenos días".($co !== '' ? " ".$co : '').",",
        "Le escribimos hace unas semanas sobre VESTRA, un marketplace mayorista B2B de moda de marca, donde cada vendedor está verificado (KYC) y cada pedido se tramita con factura.",
        "Le escribo únicamente para saber si aquel mensaje le llegó y si le resulta de interés. Si es así, le envío con gusto el catálogo actual o resuelvo cualquier duda.",
        "Si no tiene que ver con su negocio, díganoslo y no volveremos a escribir."],
      'nl' => ["VESTRA — vervolg op ons bericht",
        "Goedendag".($co !== '' ? " ".$co : '').",",
        "Wij schreven u enkele weken geleden over VESTRA, een B2B-groothandelsmarktplaats voor merkmode, waar elke verkoper KYC-geverifieerd is en elke bestelling op factuur loopt.",
        "Ik schrijf alleen nog even om te vragen of dat bericht u bereikt heeft en of het interessant voor u is. Zo ja, dan stuur ik u graag de actuele catalogus of beantwoord ik uw vragen.",
        "Past het niet bij uw zaak, laat het dan weten — dan schrijven wij niet opnieuw."],
      'pt' => ["VESTRA — seguimento da nossa mensagem",
        "Bom dia".($co !== '' ? " ".$co : '').",",
        "Escrevemos-lhe há algumas semanas sobre a VESTRA, um marketplace grossista B2B de moda de marca, onde cada vendedor é verificado (KYC) e cada encomenda segue com fatura.",
        "Escrevo apenas para saber se essa mensagem lhe chegou e se lhe interessa. Se sim, envio com gosto o catálogo atual ou respondo a questões concretas.",
        "Se não tiver a ver com o seu negócio, diga-nos e não voltaremos a escrever."],
      'pl' => ["VESTRA — nawiązanie do naszej wiadomości",
        "Dzień dobry".($co !== '' ? " ".$co : '').",",
        "Kilka tygodni temu pisaliśmy do Państwa o VESTRA — hurtowej platformie B2B z modą markową, gdzie każdy sprzedawca przechodzi weryfikację KYC, a każde zamówienie realizowane jest na fakturę.",
        "Piszę wyłącznie z pytaniem, czy tamta wiadomość do Państwa dotarła i czy temat jest interesujący. Jeśli tak, chętnie prześlę aktualny katalog lub odpowiem na konkretne pytania.",
        "Jeśli to nie dotyczy Państwa działalności, wystarczy dać znać — nie napiszemy ponownie."],
      'cs' => ["VESTRA — navázání na naši zprávu",
        "Dobrý den".($co !== '' ? " ".$co : '').",",
        "Před několika týdny jsme Vám psali o VESTRA — velkoobchodním B2B tržišti se značkovou módou, kde je každý prodejce ověřen (KYC) a každá objednávka probíhá na fakturu.",
        "Píšu jen s dotazem, zda ta zpráva dorazila a zda je to pro Vás zajímavé. Pokud ano, rád Vám pošlu aktuální katalog nebo odpovím na konkrétní dotazy.",
        "Pokud se to Vašeho podnikání netýká, stačí napsat a už se ozývat nebudeme."],
    ];

    $d = $L[$lang] ?? $L['en'];
    [$subject, $hi, $p1, $p2, $p3] = $d;

    $url  = 'https://vestrasales.com/register?type=buyer';
    $body = $hi."\n\n".$p1."\n\n".$p2."\n\n".$p3
          . "\n\n—\nVESTRA · Acerasoft LLC\nsupport@vestrasales.com · vestrasales.com";

    return [$subject, $body, []];
}
