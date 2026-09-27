<?php
/**
 * VESTRA — what the notifications SAY, in the recipient's language.
 *
 * Every push used to be a hard-coded English line ("VESTRA — order shipped 🚚")
 * while the site, the e-mails and the panel speak nine languages: a German buyer
 * read German everywhere except on the lock screen. The sentences now live here,
 * one table for all nine languages, and the call sites pass FACTS (order ref,
 * amount, product) — never text. Same pattern as vestra_journal_auto_strings():
 * no t(), because vlang() is fixed for the whole request to whoever is browsing
 * (an admin approving a German seller would otherwise push English).
 *
 * Terms follow the site's own dictionary (inc/lang/*): escrow = Treuhand /
 * séquestre / deposito a garanzia, claim = Reklamation / réclamation, offer =
 * Angebot / offre, listing (DE) = Artikel — "Angebot" already means an offer.
 * Formal/informal address follows the dictionary too (DE/FR/PT/RU formal,
 * IT/ES informal).
 *
 * Titles carry no "VESTRA —" prefix and no emoji: the operating system already
 * prints the app name and icon above every notification, and the prefix cost a
 * third of the visible title on a phone.
 */

function vestra_push_lang(?array $acc): string {
    if (function_exists('vestra_user_lang')) return vestra_user_lang($acc);
    $l = strtolower(substr((string)($acc['lang'] ?? ''), 0, 2));
    return in_array($l, ['en', 'fr', 'es', 'it', 'de', 'pt', 'ru', 'ar', 'ja'], true) ? $l : 'en';
}

/** Money as the recipient writes it: €1,234.50 / 1.234,50 € / 1 234,50 €. */
function vestra_push_money(float $v, string $lang, string $cur = 'EUR'): string {
    $cur = strtoupper($cur ?: 'EUR');
    $sym = ['EUR' => '€', 'USD' => 'US$', 'GBP' => '£', 'AUD' => 'A$', 'CAD' => 'C$'][$cur] ?? $cur.' ';
    $nb = "\u{00A0}";
    return match ($lang) {
        'de', 'it', 'es' => number_format($v, 2, ',', '.').$nb.trim($sym),
        'fr'             => number_format($v, 2, ',', "\u{202F}").$nb.trim($sym),
        'pt', 'ru'       => number_format($v, 2, ',', $nb).$nb.trim($sym),
        'ar'             => number_format($v, 2, '.', ',').$nb.trim($sym),
        default          => $sym.number_format($v, 2, '.', ','),   // en, ja
    };
}

/** A calendar date as the recipient writes it: 1 October 2026 / 1. Oktober 2026 / 2026年10月1日. */
function vestra_push_date(int $ts, string $lang): string {
    $d = (int)date('j', $ts); $m = (int)date('n', $ts) - 1; $y = date('Y', $ts);
    $M = [
        'en' => ['January','February','March','April','May','June','July','August','September','October','November','December'],
        'de' => ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'],
        'fr' => ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'],
        'it' => ['gennaio','febbraio','marzo','aprile','maggio','giugno','luglio','agosto','settembre','ottobre','novembre','dicembre'],
        'es' => ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'],
        'pt' => ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'],
        'ru' => ['января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря'],
        'ar' => ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'],
    ];
    return match ($lang) {
        'ja'       => $y.'年'.($m + 1).'月'.$d.'日',
        'de'       => $d.'. '.$M['de'][$m].' '.$y,
        'fr'       => ($d === 1 ? '1er' : (string)$d).' '.$M['fr'][$m].' '.$y,
        'it'       => ($d === 1 ? '1º' : (string)$d).' '.$M['it'][$m].' '.$y,
        'es', 'pt' => $d.' de '.$M[$lang][$m].' de '.$y,
        'ru'       => $d.' '.$M['ru'][$m].' '.$y.' г.',
        'ar'       => $d.' '.$M['ar'][$m].' '.$y,
        default    => $d.' '.$M['en'][$m].' '.$y,
    };
}

/**
 * kind => lang => [t = title, b = body, b0 = body when the optional fact is missing].
 * Placeholders are filled by vestra_push_compose(); a placeholder whose fact is
 * absent is never printed raw (b0 exists for exactly that).
 */
function vestra_push_texts(): array {
    return [
        /* ── words used inside other texts ─────────────────────────────── */
        '_seller' => ['en'=>'Seller','de'=>'Verkäufer','fr'=>'Vendeur','it'=>'Venditore','es'=>'Vendedor','pt'=>'Vendedor','ru'=>'Продавец','ar'=>'البائع','ja'=>'販売者'],
        '_new_message' => ['en'=>'New message','de'=>'Neue Nachricht','fr'=>'Nouveau message','it'=>'Nuovo messaggio','es'=>'Nuevo mensaje','pt'=>'Nova mensagem','ru'=>'Новое сообщение','ar'=>'رسالة جديدة','ja'=>'新着メッセージ'],
        '_news' => ['en'=>'You have news on VESTRA.','de'=>'Es gibt Neuigkeiten auf VESTRA.','fr'=>'Du nouveau sur VESTRA.','it'=>'Ci sono novità su VESTRA.','es'=>'Tienes novedades en VESTRA.','pt'=>'Tem novidades na VESTRA.','ru'=>'На VESTRA есть новости.','ar'=>'لديك مستجدات على VESTRA.','ja'=>'VESTRAにお知らせがあります。'],

        /* ── sellers ───────────────────────────────────────────────────── */
        'order_new' => [
            'en'=>['t'=>'New order {ref}','b'=>'{company} · {qty} pcs · {amount}','b0'=>'{company} · {amount}'],
            'de'=>['t'=>'Neue Bestellung {ref}','b'=>'{company} · {qty} Stk. · {amount}','b0'=>'{company} · {amount}'],
            'fr'=>['t'=>'Nouvelle commande {ref}','b'=>'{company} · {qty} pcs · {amount}','b0'=>'{company} · {amount}'],
            'it'=>['t'=>'Nuovo ordine {ref}','b'=>'{company} · {qty} pz. · {amount}','b0'=>'{company} · {amount}'],
            'es'=>['t'=>'Nuevo pedido {ref}','b'=>'{company} · {qty} uds. · {amount}','b0'=>'{company} · {amount}'],
            'pt'=>['t'=>'Nova encomenda {ref}','b'=>'{company} · {qty} un. · {amount}','b0'=>'{company} · {amount}'],
            'ru'=>['t'=>'Новый заказ {ref}','b'=>'{company} · {qty} шт. · {amount}','b0'=>'{company} · {amount}'],
            'ar'=>['t'=>'طلبية جديدة {ref}','b'=>'{company} · {qty} قطعة · {amount}','b0'=>'{company} · {amount}'],
            'ja'=>['t'=>'新規注文 {ref}','b'=>'{company} · {qty}点 · {amount}','b0'=>'{company} · {amount}'],
        ],
        'offer_new' => [
            'en'=>['t'=>'New offer','b'=>'{product} · {qty} × {price}'],
            'de'=>['t'=>'Neues Angebot','b'=>'{product} · {qty} × {price}'],
            'fr'=>['t'=>'Nouvelle offre','b'=>'{product} · {qty} × {price}'],
            'it'=>['t'=>'Nuova offerta','b'=>'{product} · {qty} × {price}'],
            'es'=>['t'=>'Nueva oferta','b'=>'{product} · {qty} × {price}'],
            'pt'=>['t'=>'Nova oferta','b'=>'{product} · {qty} × {price}'],
            'ru'=>['t'=>'Новое предложение','b'=>'{product} · {qty} × {price}'],
            'ar'=>['t'=>'عرض جديد','b'=>'{product} · {qty} × {price}'],
            'ja'=>['t'=>'新しいオファー','b'=>'{product} · {qty} × {price}'],
        ],
        'escrow_paid' => [
            'en'=>['t'=>'Paid order {ref} — ready to ship','b'=>'The payment is held in escrow. Please ship the goods.'],
            'de'=>['t'=>'Bezahlte Bestellung {ref} — bitte versenden','b'=>'Die Zahlung liegt auf dem Treuhandkonto. Bitte versenden Sie die Ware.'],
            'fr'=>['t'=>'Commande payée {ref} — à expédier','b'=>'Le paiement est bloqué sous séquestre. Merci d’expédier la marchandise.'],
            'it'=>['t'=>'Ordine pagato {ref} — da spedire','b'=>'Il pagamento è in deposito a garanzia. Spedisci la merce.'],
            'es'=>['t'=>'Pedido pagado {ref} — listo para enviar','b'=>'El pago está retenido en depósito en garantía. Envía la mercancía.'],
            'pt'=>['t'=>'Encomenda paga {ref} — pronta a enviar','b'=>'O pagamento está retido em escrow. Envie a mercadoria.'],
            'ru'=>['t'=>'Заказ {ref} оплачен — отправьте товар','b'=>'Оплата удерживается на эскроу. Пожалуйста, отправьте товар.'],
            'ar'=>['t'=>'طلبية مدفوعة {ref} — جاهزة للشحن','b'=>'الدفعة محفوظة في حساب الضمان. يرجى شحن البضاعة.'],
            'ja'=>['t'=>'支払済みの注文 {ref} — 発送してください','b'=>'代金はエスクローで保全されています。商品を発送してください。'],
        ],
        'receipt_confirmed' => [
            'en'=>['t'=>'Delivery confirmed · {ref}','b'=>'The buyer confirmed receipt. Your payout is being processed.'],
            'de'=>['t'=>'Erhalt bestätigt · {ref}','b'=>'Der Käufer hat den Erhalt bestätigt. Ihre Auszahlung wird bearbeitet.'],
            'fr'=>['t'=>'Réception confirmée · {ref}','b'=>'L’acheteur a confirmé la réception. Votre versement est en cours.'],
            'it'=>['t'=>'Ricezione confermata · {ref}','b'=>'L’acquirente ha confermato la ricezione. Il tuo pagamento è in lavorazione.'],
            'es'=>['t'=>'Recepción confirmada · {ref}','b'=>'El comprador ha confirmado la recepción. Tu pago se está procesando.'],
            'pt'=>['t'=>'Receção confirmada · {ref}','b'=>'O comprador confirmou a receção. O seu pagamento está a ser processado.'],
            'ru'=>['t'=>'Получение подтверждено · {ref}','b'=>'Покупатель подтвердил получение. Ваша выплата обрабатывается.'],
            'ar'=>['t'=>'تم تأكيد الاستلام · {ref}','b'=>'أكد المشتري استلام الطلبية. جارٍ تحويل مستحقاتك.'],
            'ja'=>['t'=>'受領確認 · {ref}','b'=>'買い手が受け取りを確認しました。お支払いの手続きを進めています。'],
        ],
        'funds_released' => [
            'en'=>['t'=>'Payout released · {ref}','b'=>'{amount} is on its way to your bank.'],
            'de'=>['t'=>'Auszahlung freigegeben · {ref}','b'=>'{amount} ist auf dem Weg zu Ihrer Bank.'],
            'fr'=>['t'=>'Versement débloqué · {ref}','b'=>'{amount} est en route vers votre banque.'],
            'it'=>['t'=>'Pagamento sbloccato · {ref}','b'=>'{amount} è in arrivo sul tuo conto.'],
            'es'=>['t'=>'Pago liberado · {ref}','b'=>'{amount} está en camino a tu banco.'],
            'pt'=>['t'=>'Pagamento libertado · {ref}','b'=>'{amount} está a caminho do seu banco.'],
            'ru'=>['t'=>'Выплата отправлена · {ref}','b'=>'{amount} переводится на ваш банковский счёт.'],
            'ar'=>['t'=>'تم الإفراج عن المستحقات · {ref}','b'=>'{amount} في طريقها إلى حسابك المصرفي.'],
            'ja'=>['t'=>'お支払いを実行しました · {ref}','b'=>'{amount}を銀行口座へ送金中です。'],
        ],
        'sample_released' => [
            'en'=>['t'=>'Sample payout released · {ref}','b'=>'{amount} is on its way to your bank.'],
            'de'=>['t'=>'Muster-Auszahlung freigegeben · {ref}','b'=>'{amount} ist auf dem Weg zu Ihrer Bank.'],
            'fr'=>['t'=>'Versement de l’échantillon débloqué · {ref}','b'=>'{amount} est en route vers votre banque.'],
            'it'=>['t'=>'Pagamento del campione sbloccato · {ref}','b'=>'{amount} è in arrivo sul tuo conto.'],
            'es'=>['t'=>'Pago de la muestra liberado · {ref}','b'=>'{amount} está en camino a tu banco.'],
            'pt'=>['t'=>'Pagamento da amostra libertado · {ref}','b'=>'{amount} está a caminho do seu banco.'],
            'ru'=>['t'=>'Выплата за образец отправлена · {ref}','b'=>'{amount} переводится на ваш банковский счёт.'],
            'ar'=>['t'=>'تم الإفراج عن مستحقات العيّنة · {ref}','b'=>'{amount} في طريقها إلى حسابك المصرفي.'],
            'ja'=>['t'=>'サンプル代金のお支払い · {ref}','b'=>'{amount}を銀行口座へ送金中です。'],
        ],
        'listing_approved' => [
            'en'=>['t'=>'Listing approved','b'=>'{product} is now live in the catalogue.'],
            'de'=>['t'=>'Artikel freigegeben','b'=>'„{product}“ ist jetzt im Katalog sichtbar.'],
            'fr'=>['t'=>'Annonce approuvée','b'=>'« {product} » est désormais en ligne dans le catalogue.'],
            'it'=>['t'=>'Inserzione approvata','b'=>'«{product}» è ora online nel catalogo.'],
            'es'=>['t'=>'Anuncio aprobado','b'=>'«{product}» ya está publicado en el catálogo.'],
            'pt'=>['t'=>'Anúncio aprovado','b'=>'«{product}» já está publicado no catálogo.'],
            'ru'=>['t'=>'Объявление одобрено','b'=>'«{product}» опубликовано в каталоге.'],
            'ar'=>['t'=>'تمت الموافقة على الإعلان','b'=>'«{product}» متاح الآن في الكتالوج.'],
            'ja'=>['t'=>'出品が承認されました','b'=>'「{product}」がカタログに掲載されました。'],
        ],
        'listing_changes' => [
            'en'=>['t'=>'Listing needs changes','b'=>'{product} — {note}','b0'=>'{product} was not approved. See your dashboard for details.'],
            'de'=>['t'=>'Artikel: Änderungen nötig','b'=>'{product} — {note}','b0'=>'„{product}“ wurde nicht freigegeben. Details in Ihrem Dashboard.'],
            'fr'=>['t'=>'Annonce à modifier','b'=>'{product} — {note}','b0'=>'« {product} » n’a pas été approuvée. Voir les détails dans votre tableau de bord.'],
            'it'=>['t'=>'Inserzione da modificare','b'=>'{product} — {note}','b0'=>'«{product}» non è stata approvata. Trovi i dettagli nella tua dashboard.'],
            'es'=>['t'=>'Anuncio con cambios pendientes','b'=>'{product} — {note}','b0'=>'«{product}» no se ha aprobado. Consulta los detalles en tu panel.'],
            'pt'=>['t'=>'Anúncio precisa de alterações','b'=>'{product} — {note}','b0'=>'«{product}» não foi aprovado. Veja os detalhes no seu painel.'],
            'ru'=>['t'=>'Объявление требует правок','b'=>'{product} — {note}','b0'=>'«{product}» не одобрено. Подробности — в вашем кабинете.'],
            'ar'=>['t'=>'الإعلان يحتاج إلى تعديلات','b'=>'{product} — {note}','b0'=>'لم تتم الموافقة على «{product}». التفاصيل في لوحة التحكم.'],
            'ja'=>['t'=>'出品の修正が必要です','b'=>'{product} — {note}','b0'=>'「{product}」は承認されませんでした。詳細はダッシュボードでご確認ください。'],
        ],
        'plan_updated' => [
            'en'=>['t'=>'Membership updated','b'=>'Your VESTRA membership is now {plan}.'],
            'de'=>['t'=>'Mitgliedschaft aktualisiert','b'=>'Ihre VESTRA-Mitgliedschaft ist jetzt {plan}.'],
            'fr'=>['t'=>'Abonnement mis à jour','b'=>'Votre abonnement VESTRA est désormais {plan}.'],
            'it'=>['t'=>'Abbonamento aggiornato','b'=>'Il tuo abbonamento VESTRA ora è {plan}.'],
            'es'=>['t'=>'Membresía actualizada','b'=>'Tu membresía de VESTRA ahora es {plan}.'],
            'pt'=>['t'=>'Adesão atualizada','b'=>'A sua adesão VESTRA passou a {plan}.'],
            'ru'=>['t'=>'Подписка обновлена','b'=>'Ваша подписка VESTRA теперь: {plan}.'],
            'ar'=>['t'=>'تم تحديث الاشتراك','b'=>'اشتراكك في VESTRA الآن: {plan}.'],
            'ja'=>['t'=>'メンバーシップを更新しました','b'=>'VESTRAのメンバーシップが{plan}になりました。'],
        ],

        /* ── both sides ────────────────────────────────────────────────── */
        'account_verified' => [
            'en'=>['t'=>'Account verified','b'=>'Your business is verified. Full wholesale access is unlocked.'],
            'de'=>['t'=>'Konto verifiziert','b'=>'Ihr Unternehmen ist verifiziert. Der volle Großhandelszugang ist freigeschaltet.'],
            'fr'=>['t'=>'Compte vérifié','b'=>'Votre entreprise est vérifiée. L’accès grossiste complet est activé.'],
            'it'=>['t'=>'Account verificato','b'=>'La tua azienda è verificata. L’accesso completo all’ingrosso è attivo.'],
            'es'=>['t'=>'Cuenta verificada','b'=>'Tu empresa está verificada. Ya tienes acceso mayorista completo.'],
            'pt'=>['t'=>'Conta verificada','b'=>'A sua empresa está verificada. O acesso grossista completo está ativo.'],
            'ru'=>['t'=>'Аккаунт подтверждён','b'=>'Ваша компания подтверждена. Полный оптовый доступ открыт.'],
            'ar'=>['t'=>'تم التحقق من الحساب','b'=>'تم التحقق من نشاطك التجاري. أصبح الوصول الكامل إلى أسعار الجملة متاحًا.'],
            'ja'=>['t'=>'アカウントが認証されました','b'=>'事業者認証が完了しました。卸売機能をすべてご利用いただけます。'],
        ],
        'test' => [
            'en'=>['t'=>'Notifications are on','b'=>'This device will now get VESTRA alerts for orders, offers and messages.'],
            'de'=>['t'=>'Benachrichtigungen sind aktiv','b'=>'Dieses Gerät erhält jetzt VESTRA-Hinweise zu Bestellungen, Angeboten und Nachrichten.'],
            'fr'=>['t'=>'Notifications activées','b'=>'Cet appareil recevra désormais les alertes VESTRA pour les commandes, offres et messages.'],
            'it'=>['t'=>'Notifiche attive','b'=>'Questo dispositivo riceverà gli avvisi VESTRA su ordini, offerte e messaggi.'],
            'es'=>['t'=>'Notificaciones activadas','b'=>'Este dispositivo recibirá avisos de VESTRA sobre pedidos, ofertas y mensajes.'],
            'pt'=>['t'=>'Notificações ativadas','b'=>'Este dispositivo vai receber alertas da VESTRA sobre encomendas, ofertas e mensagens.'],
            'ru'=>['t'=>'Уведомления включены','b'=>'Это устройство будет получать оповещения VESTRA о заказах, предложениях и сообщениях.'],
            'ar'=>['t'=>'الإشعارات مفعّلة','b'=>'سيستقبل هذا الجهاز تنبيهات VESTRA بشأن الطلبيات والعروض والرسائل.'],
            'ja'=>['t'=>'通知がオンになりました','b'=>'この端末でVESTRAの注文・オファー・メッセージの通知を受け取れます。'],
        ],

        /* ── buyers ────────────────────────────────────────────────────── */
        'order_paid' => [
            'en'=>['t'=>'Payment confirmed · {ref}','b'=>'Payment received. Your goods are being prepared.'],
            'de'=>['t'=>'Zahlung bestätigt · {ref}','b'=>'Zahlung eingegangen. Ihre Ware wird vorbereitet.'],
            'fr'=>['t'=>'Paiement confirmé · {ref}','b'=>'Paiement reçu. Votre marchandise est en préparation.'],
            'it'=>['t'=>'Pagamento confermato · {ref}','b'=>'Pagamento ricevuto. La tua merce è in preparazione.'],
            'es'=>['t'=>'Pago confirmado · {ref}','b'=>'Pago recibido. Estamos preparando tu mercancía.'],
            'pt'=>['t'=>'Pagamento confirmado · {ref}','b'=>'Pagamento recebido. A sua mercadoria está a ser preparada.'],
            'ru'=>['t'=>'Оплата подтверждена · {ref}','b'=>'Оплата получена. Ваш товар готовится к отправке.'],
            'ar'=>['t'=>'تم تأكيد الدفع · {ref}','b'=>'تم استلام الدفعة. جارٍ تجهيز بضاعتك.'],
            'ja'=>['t'=>'お支払いを確認しました · {ref}','b'=>'ご入金を確認しました。商品の準備を進めています。'],
        ],
        'order_shipped' => [
            'en'=>['t'=>'Order {ref} shipped','b'=>'Tracking: {tracking}','b0'=>'Your order is on its way.'],
            'de'=>['t'=>'Bestellung {ref} versandt','b'=>'Sendungsnummer: {tracking}','b0'=>'Ihre Bestellung ist unterwegs.'],
            'fr'=>['t'=>'Commande {ref} expédiée','b'=>'Numéro de suivi : {tracking}','b0'=>'Votre commande est en route.'],
            'it'=>['t'=>'Ordine {ref} spedito','b'=>'Numero di tracciamento: {tracking}','b0'=>'Il tuo ordine è in viaggio.'],
            'es'=>['t'=>'Pedido {ref} enviado','b'=>'Número de seguimiento: {tracking}','b0'=>'Tu pedido está en camino.'],
            'pt'=>['t'=>'Encomenda {ref} enviada','b'=>'Número de seguimento: {tracking}','b0'=>'A sua encomenda está a caminho.'],
            'ru'=>['t'=>'Заказ {ref} отправлен','b'=>'Трек-номер: {tracking}','b0'=>'Ваш заказ в пути.'],
            'ar'=>['t'=>'تم شحن الطلبية {ref}','b'=>'رقم التتبع: {tracking}','b0'=>'طلبيتك في الطريق إليك.'],
            'ja'=>['t'=>'注文 {ref} を発送しました','b'=>'追跡番号: {tracking}','b0'=>'ご注文の商品は配送中です。'],
        ],
        /* No "payment is released automatically on …": that is true only of a card
           (escrow) order. The claim window is true of every order, so that is what
           the date means here. */
        'order_delivered' => [
            'en'=>['t'=>'Order {ref} delivered','b'=>'Please check the goods and confirm receipt. Report any problem by {date}.'],
            'de'=>['t'=>'Bestellung {ref} zugestellt','b'=>'Bitte prüfen Sie die Ware und bestätigen Sie den Erhalt. Probleme bitte bis zum {date} melden.'],
            'fr'=>['t'=>'Commande {ref} livrée','b'=>'Merci de vérifier la marchandise et de confirmer la réception. Signalez tout problème d’ici le {date}.'],
            'it'=>['t'=>'Ordine {ref} consegnato','b'=>'Controlla la merce e conferma la ricezione. Segnala eventuali problemi entro il {date}.'],
            'es'=>['t'=>'Pedido {ref} entregado','b'=>'Revisa la mercancía y confirma la recepción. Comunica cualquier problema antes del {date}.'],
            'pt'=>['t'=>'Encomenda {ref} entregue','b'=>'Verifique a mercadoria e confirme a receção. Comunique qualquer problema até {date}.'],
            'ru'=>['t'=>'Заказ {ref} доставлен','b'=>'Проверьте товар и подтвердите получение. Сообщите о проблемах до {date}.'],
            'ar'=>['t'=>'تم تسليم الطلبية {ref}','b'=>'يرجى فحص البضاعة وتأكيد الاستلام. أبلغ عن أي مشكلة قبل {date}.'],
            'ja'=>['t'=>'注文 {ref} が配達されました','b'=>'商品をご確認のうえ、受け取りを確定してください。問題があれば{date}までにお知らせください。'],
        ],
        'escrow_secured' => [
            'en'=>['t'=>'Payment secured · {ref}','b'=>'Your payment is protected in escrow. We will let you know when the order ships.'],
            'de'=>['t'=>'Zahlung gesichert · {ref}','b'=>'Ihre Zahlung ist per Treuhand geschützt. Wir melden uns, sobald die Bestellung versandt wird.'],
            'fr'=>['t'=>'Paiement sécurisé · {ref}','b'=>'Votre paiement est protégé sous séquestre. Nous vous préviendrons à l’expédition.'],
            'it'=>['t'=>'Pagamento protetto · {ref}','b'=>'Il tuo pagamento è in deposito a garanzia. Ti avviseremo alla spedizione.'],
            'es'=>['t'=>'Pago protegido · {ref}','b'=>'Tu pago está protegido en depósito en garantía. Te avisaremos cuando se envíe el pedido.'],
            'pt'=>['t'=>'Pagamento protegido · {ref}','b'=>'O seu pagamento está protegido em escrow. Avisamos quando a encomenda for enviada.'],
            'ru'=>['t'=>'Оплата защищена · {ref}','b'=>'Ваш платёж защищён эскроу. Мы сообщим, когда заказ будет отправлен.'],
            'ar'=>['t'=>'تم تأمين الدفعة · {ref}','b'=>'دفعتك محمية في حساب الضمان. سنبلغك عند شحن الطلبية.'],
            'ja'=>['t'=>'お支払いを保全しました · {ref}','b'=>'お支払いはエスクローで保護されています。発送時にお知らせします。'],
        ],
        'refund_issued' => [
            'en'=>['t'=>'Refund issued · {ref}','b'=>'{amount} is being returned to your card in full.'],
            'de'=>['t'=>'Erstattung veranlasst · {ref}','b'=>'{amount} wird vollständig auf Ihre Karte erstattet.'],
            'fr'=>['t'=>'Remboursement effectué · {ref}','b'=>'{amount} est intégralement remboursé sur votre carte.'],
            'it'=>['t'=>'Rimborso emesso · {ref}','b'=>'{amount} viene rimborsato per intero sulla tua carta.'],
            'es'=>['t'=>'Reembolso emitido · {ref}','b'=>'{amount} se está devolviendo íntegramente a tu tarjeta.'],
            'pt'=>['t'=>'Reembolso emitido · {ref}','b'=>'{amount} está a ser devolvido na totalidade ao seu cartão.'],
            'ru'=>['t'=>'Возврат оформлен · {ref}','b'=>'{amount} возвращается на вашу карту в полном объёме.'],
            'ar'=>['t'=>'تم إصدار الاسترداد · {ref}','b'=>'يتم رد {amount} بالكامل إلى بطاقتك.'],
            'ja'=>['t'=>'返金を実行しました · {ref}','b'=>'{amount}を全額カードに返金しています。'],
        ],
        'request_offer' => [
            'en'=>['t'=>'New offer on your request','b'=>'{title} — {offer}'],
            'de'=>['t'=>'Neues Angebot zu Ihrer Anfrage','b'=>'{title} — {offer}'],
            'fr'=>['t'=>'Nouvelle offre pour votre demande','b'=>'{title} — {offer}'],
            'it'=>['t'=>'Nuova offerta per la tua richiesta','b'=>'{title} — {offer}'],
            'es'=>['t'=>'Nueva oferta para tu solicitud','b'=>'{title} — {offer}'],
            'pt'=>['t'=>'Nova oferta para o seu pedido','b'=>'{title} — {offer}'],
            'ru'=>['t'=>'Новое предложение по вашему запросу','b'=>'{title} — {offer}'],
            'ar'=>['t'=>'عرض جديد على طلبك','b'=>'{title} — {offer}'],
            'ja'=>['t'=>'リクエストに新しいオファーが届きました','b'=>'{title} — {offer}'],
        ],
        'claim_opened' => [
            'en'=>['t'=>'Claim {claim} received','b'=>'Order {ref} — we review it within {days} business days.'],
            'de'=>['t'=>'Reklamation {claim} eingegangen','b'=>'Bestellung {ref} — wir prüfen sie innerhalb von {days} Werktagen.'],
            'fr'=>['t'=>'Réclamation {claim} reçue','b'=>'Commande {ref} — nous l’examinons sous {days} jours ouvrés.'],
            'it'=>['t'=>'Reclamo {claim} ricevuto','b'=>'Ordine {ref} — lo esaminiamo entro {days} giorni lavorativi.'],
            'es'=>['t'=>'Reclamación {claim} recibida','b'=>'Pedido {ref}: la revisamos en un plazo de {days} días hábiles.'],
            'pt'=>['t'=>'Reclamação {claim} recebida','b'=>'Encomenda {ref} — analisamos no prazo de {days} dias úteis.'],
            'ru'=>['t'=>'Претензия {claim} получена','b'=>'Заказ {ref} — срок рассмотрения {days} раб. дн.'],
            'ar'=>['t'=>'تم استلام المطالبة {claim}','b'=>'الطلبية {ref} — سنراجعها خلال {days} أيام عمل.'],
            'ja'=>['t'=>'申立て {claim} を受け付けました','b'=>'注文 {ref} — {days}営業日以内に確認します。'],
        ],
        'claim_resolved' => [
            'en'=>['t'=>'Claim {claim} resolved','b'=>'Order {ref} — {outcome}','b0'=>'Order {ref} — see the decision in your order.'],
            'de'=>['t'=>'Reklamation {claim} abgeschlossen','b'=>'Bestellung {ref} — {outcome}','b0'=>'Bestellung {ref} — die Entscheidung finden Sie in Ihrer Bestellung.'],
            'fr'=>['t'=>'Réclamation {claim} traitée','b'=>'Commande {ref} — {outcome}','b0'=>'Commande {ref} — consultez la décision dans votre commande.'],
            'it'=>['t'=>'Reclamo {claim} risolto','b'=>'Ordine {ref} — {outcome}','b0'=>'Ordine {ref} — trovi la decisione nel tuo ordine.'],
            'es'=>['t'=>'Reclamación {claim} resuelta','b'=>'Pedido {ref}: {outcome}','b0'=>'Pedido {ref}: consulta la decisión en tu pedido.'],
            'pt'=>['t'=>'Reclamação {claim} resolvida','b'=>'Encomenda {ref} — {outcome}','b0'=>'Encomenda {ref} — veja a decisão na sua encomenda.'],
            'ru'=>['t'=>'Претензия {claim} рассмотрена','b'=>'Заказ {ref} — {outcome}','b0'=>'Заказ {ref} — решение доступно в карточке заказа.'],
            'ar'=>['t'=>'تمت معالجة المطالبة {claim}','b'=>'الطلبية {ref} — {outcome}','b0'=>'الطلبية {ref} — اطّلع على القرار في صفحة الطلبية.'],
            'ja'=>['t'=>'申立て {claim} が解決しました','b'=>'注文 {ref} — {outcome}','b0'=>'注文 {ref} — 決定内容は注文ページでご確認ください。'],
        ],
        'offer_accepted' => [
            'en'=>['t'=>'Offer accepted','b'=>'{product} — your offer was accepted.'],
            'de'=>['t'=>'Angebot angenommen','b'=>'{product} — Ihr Angebot wurde angenommen.'],
            'fr'=>['t'=>'Offre acceptée','b'=>'{product} — votre offre a été acceptée.'],
            'it'=>['t'=>'Offerta accettata','b'=>'{product} — la tua offerta è stata accettata.'],
            'es'=>['t'=>'Oferta aceptada','b'=>'{product}: tu oferta ha sido aceptada.'],
            'pt'=>['t'=>'Oferta aceite','b'=>'{product} — a sua oferta foi aceite.'],
            'ru'=>['t'=>'Предложение принято','b'=>'{product} — ваше предложение принято.'],
            'ar'=>['t'=>'تم قبول العرض','b'=>'{product} — تم قبول عرضك.'],
            'ja'=>['t'=>'オファーが承認されました','b'=>'{product} — お客様のオファーが承認されました。'],
        ],
        'offer_countered' => [
            'en'=>['t'=>'Counter offer','b'=>'{product} — the seller proposes {price} per unit.'],
            'de'=>['t'=>'Gegenangebot','b'=>'{product} — der Verkäufer schlägt {price} pro Stück vor.'],
            'fr'=>['t'=>'Contre-offre','b'=>'{product} — le vendeur propose {price} l’unité.'],
            'it'=>['t'=>'Controproposta','b'=>'{product} — il venditore propone {price} al pezzo.'],
            'es'=>['t'=>'Contraoferta','b'=>'{product}: el vendedor propone {price} por unidad.'],
            'pt'=>['t'=>'Contraoferta','b'=>'{product} — o vendedor propõe {price} por unidade.'],
            'ru'=>['t'=>'Встречное предложение','b'=>'{product} — продавец предлагает {price} за штуку.'],
            'ar'=>['t'=>'عرض مضاد','b'=>'{product} — يقترح البائع {price} للقطعة.'],
            'ja'=>['t'=>'カウンターオファー','b'=>'{product} — 販売者から1点あたり{price}の提案です。'],
        ],
        'offer_declined' => [
            'en'=>['t'=>'Offer declined','b'=>'{product} — the seller declined this offer.'],
            'de'=>['t'=>'Angebot abgelehnt','b'=>'{product} — der Verkäufer hat das Angebot abgelehnt.'],
            'fr'=>['t'=>'Offre refusée','b'=>'{product} — le vendeur a refusé cette offre.'],
            'it'=>['t'=>'Offerta rifiutata','b'=>'{product} — il venditore ha rifiutato l’offerta.'],
            'es'=>['t'=>'Oferta rechazada','b'=>'{product}: el vendedor ha rechazado la oferta.'],
            'pt'=>['t'=>'Oferta recusada','b'=>'{product} — o vendedor recusou esta oferta.'],
            'ru'=>['t'=>'Предложение отклонено','b'=>'{product} — продавец отклонил предложение.'],
            'ar'=>['t'=>'تم رفض العرض','b'=>'{product} — رفض البائع هذا العرض.'],
            'ja'=>['t'=>'オファーが辞退されました','b'=>'{product} — 販売者がこのオファーを辞退しました。'],
        ],
        'price_agreed' => [
            'en'=>['t'=>'Price agreed','b'=>'{product} — agreed at {price} per unit.'],
            'de'=>['t'=>'Preis vereinbart','b'=>'{product} — vereinbart zu {price} pro Stück.'],
            'fr'=>['t'=>'Prix convenu','b'=>'{product} — convenu à {price} l’unité.'],
            'it'=>['t'=>'Prezzo concordato','b'=>'{product} — concordato a {price} al pezzo.'],
            'es'=>['t'=>'Precio acordado','b'=>'{product}: acordado a {price} por unidad.'],
            'pt'=>['t'=>'Preço acordado','b'=>'{product} — acordado a {price} por unidade.'],
            'ru'=>['t'=>'Цена согласована','b'=>'{product} — согласовано: {price} за штуку.'],
            'ar'=>['t'=>'تم الاتفاق على السعر','b'=>'{product} — تم الاتفاق على {price} للقطعة.'],
            'ja'=>['t'=>'価格が合意されました','b'=>'{product} — 1点あたり{price}で合意しました。'],
        ],
    ];
}

/** Where each kind opens, and how notifications of one thread/order replace each other. */
function vestra_push_route(string $kind, array $f): array {
    $ref = rawurlencode((string)($f['ref'] ?? ''));
    $order = fn(string $panel) => '/'.$panel.'?tab=orders'.($ref !== '' ? '&view='.$ref : '');
    return match ($kind) {
        'order_new', 'escrow_paid', 'receipt_confirmed', 'funds_released'
                             => [$order('seller'), 'order-'.($f['ref'] ?? '')],
        'sample_released'    => ['/seller?tab=orders', 'sample-'.($f['ref'] ?? '')],
        'offer_new'          => ['/seller?tab=offers', 'offer-'.($f['ref'] ?? ($f['product'] ?? ''))],
        'listing_approved', 'listing_changes'
                             => ['/seller?tab=listings', 'listing-'.($f['listing'] ?? ($f['product'] ?? ''))],
        'plan_updated'       => ['/seller', 'plan'],
        'account_verified'   => [(($f['panel'] ?? '') === 'seller' ? '/seller' : '/buyer'), 'account'],
        'order_paid', 'order_shipped', 'order_delivered', 'escrow_secured', 'refund_issued'
                             => [$order('buyer'), 'order-'.($f['ref'] ?? '')],
        'claim_opened', 'claim_resolved'
                             => [$order('buyer'), 'claim-'.($f['claim'] ?? ($f['ref'] ?? ''))],
        'request_offer'      => ['/buyer?tab=requests', 'request-'.($f['request'] ?? ($f['title'] ?? ''))],
        'offer_accepted', 'offer_countered', 'offer_declined', 'price_agreed'
                             => ['/buyer?tab=offers'.($ref !== '' ? '&view='.$ref : ''), 'offer-'.($f['ref'] ?? ($f['product'] ?? ''))],
        'message_new'        => [(string)($f['url'] ?? '/'), 'msg-'.($f['thread'] ?? '')],
        'test'               => [(string)($f['url'] ?? '/'), 'test'],
        default              => ['/', $kind],
    };
}

/**
 * Build the notification (title, body, url, tag, language, direction) for one kind.
 * $f holds FACTS: ref, company, qty, amount (+currency), product, price, tracking,
 * date (unix time), title, offer, note, claim, days, outcome, plan, text, from,
 * seller_ident, thread, url, unread. Returns null for an unknown kind.
 */
function vestra_push_compose(string $kind, array $f, string $lang): ?array {
    $T = vestra_push_texts();
    /* message_new has no row of its own: its title is the sender, its body the message. */
    if ($kind !== 'message_new' && (!isset($T[$kind]) || $kind[0] === '_')) return null;
    $lang = isset($T['_new_message'][$lang]) ? $lang : 'en';
    $cur = (string)($f['currency'] ?? 'EUR');
    $vars = [];
    foreach (['ref', 'company', 'product', 'tracking', 'title', 'note', 'claim', 'outcome', 'plan'] as $k) {
        $v = trim(preg_replace('/\s+/u', ' ', (string)($f[$k] ?? '')));
        if ($v !== '') $vars['{'.$k.'}'] = mb_substr($v, 0, $k === 'note' || $k === 'outcome' ? 120 : 70);
    }
    foreach (['qty', 'days'] as $k) if (isset($f[$k]) && (string)$f[$k] !== '') $vars['{'.$k.'}'] = (string)$f[$k];
    if (isset($f['amount']) && is_numeric($f['amount'])) $vars['{amount}'] = vestra_push_money((float)$f['amount'], $lang, $cur);
    if (isset($f['price']) && is_numeric($f['price']))   $vars['{price}']  = vestra_push_money((float)$f['price'], $lang, $cur);
    if (isset($f['date']) && is_numeric($f['date']))     $vars['{date}']   = vestra_push_date((int)$f['date'], $lang);
    if (isset($vars['{price}'])) {
        $vars['{offer}'] = $vars['{price}'].(isset($f['qty_text']) && trim((string)$f['qty_text']) !== '' ? ' · '.trim((string)$f['qty_text']) : '');
    }

    if ($kind === 'message_new') {
        /* The sender as the recipient is allowed to see it (KURAL 8): a buyer sees the
           seller's product ident, never the shop's name; a seller sees the buyer. */
        $from = trim((string)($f['from'] ?? ''));
        if (!empty($f['seller_ident'])) $from = $T['_seller'][$lang].' '.$f['seller_ident'];
        $text = trim(preg_replace('/\s+/u', ' ', (string)($f['text'] ?? '')));
        $title = $from !== '' ? $from : $T['_new_message'][$lang];
        $body = $text !== '' ? mb_substr($text, 0, 180) : $T['_new_message'][$lang];
    } else {
        $row = $T[$kind][$lang];
        $fill = function (string $tpl) use ($vars): ?string {
            if (preg_match_all('/\{[a-z_]+\}/', $tpl, $m)) {
                foreach ($m[0] as $ph) if (!isset($vars[$ph])) return null;   // a fact is missing
            }
            return strtr($tpl, $vars);
        };
        $title = $fill($row['t']) ?? preg_replace('/\s*(·|—)?\s*\{[a-z_]+\}/u', '', $row['t']);
        $body = $fill($row['b']) ?? (isset($row['b0']) ? ($fill($row['b0']) ?? '') : '');
    }
    [$url, $tag] = vestra_push_route($kind, $f);
    $n = [
        'title' => $title, 'body' => $body, 'url' => $url, 'tag' => mb_substr($tag, 0, 64),
        'kind' => $kind, 'lang' => $lang, 'dir' => $lang === 'ar' ? 'rtl' : 'ltr',
        'urgency' => in_array($kind, ['message_new', 'order_new', 'escrow_paid'], true) ? 'high' : 'normal',
    ];
    if (isset($f['unread']) && is_numeric($f['unread'])) $n['unread'] = (int)$f['unread'];
    return $n;
}
