<?php
/**
 * FAQ içeriği
 * -----------
 * Sözlükte değil ayrı dosyada: uzun metinler i18n sözlüğünü okunmaz hale
 * getiriyor, ayrıca FAQ metni ürün/hukuk değişince tek yerden güncellenmeli.
 * Almanca ve İngilizce yazılı; diğer diller İngilizceye düşer.
 *
 * {} içindeki yer tutucular vr_faq_groups() içinde gerçek yapılandırmadan
 * dolduruluyor — kargo bedelini iki yerde güncellemek zorunda kalmayalım.
 */

declare(strict_types=1);

function vr_faq_groups(): array
{
    $lang  = vr_lang();
    $brand = (string)vr_config('brand');
    $days  = (int)vr_config('return_days', 30);
    $wd    = (int)vr_config('withdrawal_days', 14);
    $ship  = (array)vr_config('shipping');
    $vat   = (int)vr_config('vat_rate_bps', 1900) / 100;
    $steps = (int)vr_config('vault_steps', 6);
    $hours = (int)vr_config('vault_step_hours', 24);
    $email = (string)(vr_config('company')['email'] ?? '');
    $feeB  = (int)vr_config('fee_bps_business', 1200) / 100;
    $feeP  = (int)vr_config('fee_bps_private', 900) / 100;

    $deDe = vr_money((int)($ship['de']['cents'] ?? 0));
    $frDe = vr_money((int)($ship['de']['free_over'] ?? 0));
    $eu   = vr_money((int)($ship['eu']['cents'] ?? 0));

    $de = [
        'faq_cat_order' => [
            ['Brauche ich ein Konto, um zu kaufen?',
             "Nein. Sie bestellen als Gast; wir fragen nur, was für Lieferung und Rechnung nötig ist. Ein Konto brauchen nur Verkäufer."],
            ['Welche Zahlungsarten gibt es?',
             "Kartenzahlung (Visa, Mastercard, Amex), Apple Pay, Google Pay, Klarna und SEPA-Lastschrift — abgewickelt über Stripe. Ihre Kartendaten erreichen unsere Server nie, die Eingabe passiert auf der Seite von Stripe."],
            ['Sind die Preise inklusive Mehrwertsteuer?',
             "Ja. Alle angezeigten Preise sind Endpreise inkl. {$vat}% MwSt. Versandkosten werden vor dem Bezahlen separat ausgewiesen."],
            ['Wann wird abgebucht?',
             "Direkt bei Bestellabschluss. Klarna und SEPA können ein paar Tage brauchen — Ihre Bestellung ist trotzdem verbindlich reserviert, wir versenden nach Zahlungseingang."],
            ['Bekomme ich eine Rechnung?',
             "Ja, mit der Ware bzw. per E-Mail. Bei Artikeln von Händlern stellt der jeweilige Händler die Rechnung, bei eigener Ware {$brand}. Privatverkäufer stellen keine Rechnung mit MwSt."],
            ['Was ist die Merkliste — reserviert sie etwas?',
             "Nein. Die Merkliste ist eine Erinnerungshilfe und liegt als Cookie auf Ihrem Gerät, nicht auf unserem Server. Die meisten Stücke gibt es genau einmal; gemerkt heißt nicht reserviert. Wer zuerst bestellt, bekommt es."],
            ['Meine Zahlung wurde abgelehnt — was nun?',
             "Meist liegt es an der 3-D-Secure-Bestätigung Ihrer Bank. Versuchen Sie es erneut oder wählen Sie eine andere Zahlungsart. Es wurde nichts abgebucht, und Ihr Warenkorb bleibt erhalten."],
        ],
        'faq_cat_ship' => [
            ['Was kostet der Versand?',
             "Deutschland {$deDe}, versandfrei ab {$frDe}. EU ab {$eu}. Schweiz, UK, Norwegen und internationale Ziele werden im Warenkorb berechnet, sobald Sie das Land wählen."],
            ['Wie lange dauert die Lieferung?',
             "Deutschland {$ship['de']['days']} Werktage, EU {$ship['eu']['days']}, international {$ship['world']['days']}. Versand erfolgt versichert und mit Sendungsverfolgung."],
            ['Ich habe bei mehreren Verkäufern gekauft — kommt alles zusammen?',
             "Nein. Jeder Verkäufer versendet selbst, Sie erhalten also mehrere Pakete und mehrere Sendungslinks. Bezahlt wird trotzdem nur einmal, und Versandkosten fallen nur einmal an."],
            ['Liefern Sie an Packstationen?',
             "Innerhalb Deutschlands ja, wenn Sie eine DHL-Packstation als Lieferadresse angeben. Bei internationalen Sendungen ist eine Straßenadresse nötig."],
            ['Zoll und Einfuhrabgaben?',
             "Innerhalb der EU fallen keine an. Bei Lieferungen in die Schweiz, UK oder außerhalb Europas können Einfuhrabgaben anfallen, die der Empfänger trägt."],
        ],
        'faq_cat_return' => [
            ['Wie lange habe ich Rückgaberecht?',
             "Gesetzlich {$wd} Tage Widerruf. Bei eigener {$brand}-Ware und bei Händlern gewähren wir freiwillig {$days} Tage. Ausnahme: Käufe von Privatverkäufern — dort gibt es kein gesetzliches Widerrufsrecht."],
            ['Wie sende ich zurück?',
             "Schreiben Sie an {$email} mit Ihrer Bestellnummer. Sie erhalten ein Rücksendelabel und die Adresse des jeweiligen Verkäufers. Ware bitte ungetragen und mit Originaletikett zurückschicken."],
            ['Wer zahlt die Rücksendung?',
             "Bei Widerruf innerhalb der gesetzlichen Frist tragen wir die Kosten für Rücksendungen aus Deutschland. Für internationale Rücksendungen und für die freiwillige Verlängerung über {$wd} Tage hinaus tragen Sie die Kosten."],
            ['Wann bekomme ich mein Geld zurück?',
             "Nach Eingang und Prüfung der Ware, spätestens 14 Tage danach — auf dasselbe Zahlungsmittel. Bei Klarna erfolgt die Erstattung über Ihr Klarna-Konto."],
            ['Warum gilt bei Privatverkäufern kein Widerruf?',
             "Das Widerrufsrecht gilt nur zwischen Verbraucher und Unternehmer. Verkauft eine Privatperson, greift es nicht — deshalb kennzeichnen wir jedes Angebot deutlich und zeigen die Folge vor dem Bezahlen an."],
            ['Der Artikel ist beschädigt angekommen.',
             "Melden Sie das innerhalb von 14 Tagen mit Fotos an {$email}. Transportschäden regeln wir unabhängig vom Widerrufsrecht — auch bei Privatverkäufern."],
        ],
        'faq_cat_vault' => [
            ['Was ist der Vault?',
             "Unser Premium Outlet. Jedes Stück startet zu einem Eröffnungspreis und fällt in {$steps} festen Stufen — alle {$hours} Stunden eine Stufe — bis zum Mindestpreis. Wer zuerst zugreift, bekommt es."],
            ['Kann der Preis auch steigen?',
             "Nein. Der Plan wird beim Öffnen eines Loses festgeschrieben und danach nicht mehr geändert. Er kann nur fallen und endet am veröffentlichten Mindestpreis."],
            ['Wird der Preis für mich persönlich berechnet?',
             "Nein. Der Preis kommt aus dem Plan und wird auf unserem Server berechnet — identisch für alle Besucher, unabhängig von Gerät, Standort oder Verlauf."],
            ['Was heißt „niedrigster Preis der letzten 30 Tage“?',
             "Eine Pflichtangabe bei Preissenkungen (§11 PAngV). Weil Vault-Preise nur fallen, ist das genau der Preis, der vor der aktuellen Stufe galt. In der ersten Stufe gibt es noch keine Senkung — dann zeigen wir auch keine an."],
            ['Ich warte auf die nächste Stufe — riskant?',
             "Ja, bewusst. Jedes Los existiert nur einmal. Wer wartet, zahlt weniger — falls es nicht vorher jemand nimmt."],
            ['Was bringt die Mitgliedschaft?',
             "Mitglieder sehen neue Lose " . (int)vr_config('vault_member_head_start_hours', 12) . " Stunden vor allen anderen und bekommen den Senkungsplan per E-Mail. Kostenlos, jederzeit abmeldbar."],
            ['Gilt im Vault dasselbe Rückgaberecht?',
             "Ja. Ein reduzierter Preis ändert nichts an Ihren Rechten. Nur bei Privatverkäufern gilt die oben beschriebene Ausnahme."],
            ['Kann ich mich benachrichtigen lassen, wenn der Preis fällt?',
             "Ja. Auf jeder Los-Seite können Sie Ihren Wunschpreis eintragen. Sobald das Los ihn erreicht, bekommen Sie GENAU EINE E-Mail — keine Erinnerungen, keine Werbung. Danach ist der Alarm verbraucht und der Datensatz wird gelöscht. Ein Alarm reserviert das Stück nicht: wer zuerst kauft, bekommt es."],
            ['Warum kann ich keinen Alarm unter dem Mindestpreis setzen?',
             "Weil er nie auslösen würde. Der Preis endet am veröffentlichten Mindestpreis, deshalb nehmen wir nur Wunschpreise zwischen aktuellem Preis und Mindestpreis an."],
        ],
        'faq_cat_auth' => [
            ['Wie stellen Sie Echtheit sicher?',
             "Wir kaufen aus EU-Bestand mit dokumentierter Einkaufshistorie und verlangen von Verkäufern Belege. Auf jeder Produktseite steht, woher das Teil kommt. Bei Unklarheiten wird ein Angebot nicht freigeschaltet."],
            ['Was passiert, wenn ein Teil doch nicht original ist?',
             "Volle Rückerstattung inklusive Versand, und der Verkäufer wird von der Plattform entfernt. Melden Sie den Fall mit Fotos an {$email}."],
            ['Verkaufen Sie Second Hand?',
             "Sowohl neu (mit Originaletikett) als auch vorbesitzt. Der Zustand steht auf jeder Produktseite; bei vorbesitzten Teilen gehört die Beschreibung der Gebrauchsspuren zur Pflicht des Verkäufers."],
        ],
        'faq_cat_sell' => [
            ['Wer darf bei ' . $brand . ' verkaufen?',
             "Händler (angemeldetes Gewerbe) und Privatpersonen. Beide melden sich unter „Verkaufen“ an, absolvieren die Stripe-Verifizierung und stellen danach Angebote ein."],
            ['Was kostet es?',
             "Keine Einstell- und keine Monatsgebühr. Provision: {$feeB}% für Händler, {$feeP}% für Privatverkäufer, jeweils plus " . vr_money((int)vr_config('fee_fixed_cents', 35)) . " pro verkauftem Teil. Vault-Platzierung " . ((int)vr_config('fee_bps_outlet', 1500) / 100) . "%."],
            ['Wann und wie werde ich bezahlt?',
             "Über Stripe Connect. Der Käuferbetrag minus Provision landet in Ihrem Stripe-Guthaben und wird nach dem Stripe-Auszahlungsrhythmus auf Ihr Bankkonto überwiesen. Ihr Geld liegt nie bei uns."],
            ['Warum muss ich mich bei Stripe verifizieren?',
             "Gesetzliche Pflicht für Zahlungsdienstleister (Geldwäscheprävention, KYC). Ohne abgeschlossene Verifizierung kann Stripe keine Auszahlung an Sie vornehmen — solange bleiben Ihre Angebote offline."],
            ['Muss ich ein Gewerbe anmelden?',
             "Wer regelmäßig und mit Gewinnabsicht verkauft, handelt gewerblich und muss ein Gewerbe anmelden. Gelegentlicher Verkauf aus dem eigenen Kleiderschrank ist privat. Die Einordnung liegt bei Ihnen; wir prüfen Auffälligkeiten und melden uns."],
            ['Wie lange dauert die Prüfung eines Angebots?',
             "In der Regel ein Werktag. Wir prüfen Bilder, Modellcode und Plausibilität des Preises; bei Rückfragen melden wir uns per E-Mail."],
        ],
    ];

    $en = [
        'faq_cat_order' => [
            ['Do I need an account to buy?',
             "No. You order as a guest and we ask only what delivery and invoicing require. Accounts are for sellers."],
            ['Which payment methods do you accept?',
             "Cards (Visa, Mastercard, Amex), Apple Pay, Google Pay, Klarna and SEPA direct debit, all handled by Stripe. Card details never reach our servers — you enter them on Stripe's page."],
            ['Do prices include VAT?',
             "Yes. Every price shown is a final price including {$vat}% VAT. Shipping is shown separately before you pay."],
            ['When am I charged?',
             "At checkout. Klarna and SEPA can take a few days to settle; your order is reserved and ships once payment clears."],
            ['Will I get an invoice?',
             "Yes, with the parcel or by email. Trader items are invoiced by that trader, our own stock by {$brand}. Private sellers do not issue VAT invoices."],
            ['What is the saved list — does it reserve anything?',
             "No. It is a reminder, stored in a cookie on your device rather than on our server. Most pieces exist exactly once; saved does not mean reserved. Whoever orders first gets it."],
            ['My payment was declined.',
             "Usually the 3-D Secure step with your bank. Try again or pick another method — nothing was charged and your bag is still there."],
        ],
        'faq_cat_ship' => [
            ['What does shipping cost?',
             "Germany {$deDe}, free over {$frDe}. EU from {$eu}. Switzerland, UK, Norway and the rest of the world are calculated in the bag once you pick a country."],
            ['How long does delivery take?',
             "Germany {$ship['de']['days']} working days, EU {$ship['eu']['days']}, international {$ship['world']['days']}. Everything ships insured and tracked."],
            ['I bought from several sellers — one parcel?',
             "No. Each seller ships their own items, so you get several parcels and several tracking links. You still pay once, and shipping is charged once."],
            ['Do you deliver to parcel lockers?',
             "Within Germany, yes — enter a DHL Packstation as the delivery address. International orders need a street address."],
            ['Customs and import duties?',
             "None inside the EU. Deliveries to Switzerland, the UK or outside Europe may incur import charges payable by the recipient."],
        ],
        'faq_cat_return' => [
            ['How long can I return?',
             "{$wd} days statutory withdrawal. For our own stock and trader items we voluntarily extend it to {$days} days. Exception: purchases from private sellers carry no statutory right of withdrawal."],
            ['How do I send something back?',
             "Email {$email} with your order number. You get a return label and the seller's address. Items must be unworn with original tags."],
            ['Who pays for the return?',
             "For statutory withdrawals we cover returns from Germany. International returns and returns during the voluntary extension beyond {$wd} days are at your cost."],
            ['When do I get my money back?',
             "After the item arrives and is checked, at the latest 14 days later, to the same payment method. Klarna refunds run through your Klarna account."],
            ['Why is there no withdrawal right with private sellers?',
             "The right of withdrawal exists between a consumer and a business. When a private individual sells, it does not apply — so we label every listing and show the consequence before you pay."],
            ['My item arrived damaged.',
             "Report it within 14 days with photos to {$email}. Transport damage is handled regardless of withdrawal rights, private sellers included."],
        ],
        'faq_cat_vault' => [
            ['What is the Vault?',
             "Our Premium Outlet. Every piece opens at a starting price and steps down {$steps} times — one step every {$hours} hours — until it reaches the floor price. First buyer takes it."],
            ['Can the price go up?',
             "No. The schedule is fixed when a lot opens and never changes afterwards. It only falls, and it stops at the published floor."],
            ['Is the price personalised for me?',
             "No. It comes from the schedule and is computed on our server — identical for every visitor, regardless of device, location or history."],
            ['What does "lowest price in the last 30 days" mean?',
             "A mandatory disclosure for price reductions under German price-indication law. Because Vault prices only fall, it is exactly the price that applied before the current step. On the first step there is no reduction yet, so we claim none."],
            ['Waiting for the next step — risky?',
             "Yes, by design. Each lot exists once. Wait and you pay less, unless someone takes it first."],
            ['What does membership give me?',
             "Members see new lots " . (int)vr_config('vault_member_head_start_hours', 12) . " hours before everyone else and get the schedule by email. Free, unsubscribe any time."],
            ['Same return rights in the Vault?',
             "Yes. A reduced price does not change your rights. Only the private-seller exception above applies."],
            ['Can I be told when the price drops?',
             "Yes. On every lot page you can name your price. When the lot reaches it you get EXACTLY ONE email — no reminders, no marketing. The alert is then spent and the record deleted. An alert does not reserve the piece: whoever buys first gets it."],
            ['Why can I not set an alert below the floor price?',
             "Because it would never fire. The price stops at the published floor, so we only accept target prices between the current price and that floor."],
        ],
        'faq_cat_auth' => [
            ['How do you verify authenticity?',
             "We buy from EU stock with documented purchase history and require sellers to hold proof. Every product page states where the piece comes from. If anything is unclear, the listing does not go live."],
            ['What if an item turns out not to be original?',
             "Full refund including shipping, and the seller is removed from the platform. Report it with photos to {$email}."],
            ['Do you sell pre-owned?',
             "Both new with tags and pre-owned. Condition is stated on every product page; describing wear is part of the seller's duty."],
        ],
        'faq_cat_sell' => [
            ['Who can sell on ' . $brand . '?',
             "Traders (registered businesses) and private individuals. Both register under \"Sell\", complete Stripe verification, then list."],
            ['What does it cost?',
             "No listing fee, no monthly fee. Commission: {$feeB}% for traders, {$feeP}% for private sellers, plus " . vr_money((int)vr_config('fee_fixed_cents', 35)) . " per item sold. Vault placement " . ((int)vr_config('fee_bps_outlet', 1500) / 100) . "%."],
            ['When and how am I paid?',
             "Through Stripe Connect. The buyer's payment minus commission lands in your Stripe balance and is paid to your bank on Stripe's normal schedule. We never hold your money."],
            ['Why must I verify with Stripe?',
             "Legal obligation for payment providers (anti-money-laundering, KYC). Without completed verification Stripe cannot pay you — until then your listings stay offline."],
            ['Do I need to register a business?',
             "Selling regularly with intent to profit is commercial activity and needs a business registration. Occasional selling from your own wardrobe is private. The classification is yours; we review outliers and get in touch."],
            ['How long does listing review take?',
             "Usually one working day. We check images, model code and price plausibility, and email you if something needs clarifying."],
        ],
    ];

    return $lang === 'de' ? $de : $en;
}
