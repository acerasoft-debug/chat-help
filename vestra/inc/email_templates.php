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
    'en'=>['company_reg'=>'company registration certificate','vat_cert'=>'VAT/tax registration certificate','id_document'=>'government-issued ID','auth_letter'=>'authorization letter'],
    'de'=>['company_reg'=>'Handelsregisterauszug','vat_cert'=>'Umsatzsteuer-/Steuerregistrierungsnachweis','id_document'=>'amtlichen Ausweis','auth_letter'=>'Vollmachtsschreiben'],
    'fr'=>['company_reg'=>'extrait Kbis / immatriculation','vat_cert'=>'justificatif de TVA','id_document'=>'pièce d\'identité officielle','auth_letter'=>'lettre d\'autorisation'],
    'it'=>['company_reg'=>'visura camerale','vat_cert'=>'certificato di partita IVA','id_document'=>'documento d\'identità','auth_letter'=>'lettera di autorizzazione'],
    'es'=>['company_reg'=>'certificado de registro mercantil','vat_cert'=>'certificado de IVA','id_document'=>'documento de identidad oficial','auth_letter'=>'carta de autorización'],
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
  $BADGE = ['en'=>'🎟️ Your voucher','de'=>'🎟️ Ihr Gutschein','fr'=>'🎟️ Votre bon',
            'it'=>'🎟️ Il tuo buono','es'=>'🎟️ Tu vale'];
  $BTN   = ['en'=>'Browse the catalogue','de'=>'Zum Katalog','fr'=>'Voir le catalogue',
            'it'=>'Vai al catalogo','es'=>'Ver el catálogo'];
  $ROWL  = ['en'=>['Voucher code','Discount','Valid until'],'de'=>['Gutscheincode','Rabatt','Gültig bis'],
            'fr'=>['Code du bon','Remise','Valable jusqu\'au'],'it'=>['Codice buono','Sconto','Valido fino al'],
            'es'=>['Código del vale','Descuento','Válido hasta']];
  $rl = $ROWL[$lang] ?? $ROWL['en'];
  $opts = [
    'badge' => $BADGE[$lang] ?? $BADGE['en'],
    'rows'  => [
      ['label'=>$rl[0],'value'=>$code,'strong'=>true],
      ['label'=>$rl[1],'value'=>$valueLabel],
      ['label'=>$rl[2],'value'=>$expiry],
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
