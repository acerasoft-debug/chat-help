<?php
/**
 * Journal içeriği
 * ---------------
 * Kısa, işe yarar editoryal yazılar. Amaç "içerik pazarlaması" doldurması
 * değil: alıcıların gerçekten sorduğu dört şeye (herkunft, bakım, beden,
 * Vault mekaniği) uzun biçimde cevap veriyor ve ürün sayfalarından oraya
 * bağlanıyor. Aynı zamanda SEO tarafında kategoriden bağımsız derinlik.
 *
 * Almanca ve İngilizce yazılı; diğer diller İngilizceye düşer.
 */

declare(strict_types=1);

function vr_journal_entries(): array
{
    $brand = (string)vr_config('brand');
    $days  = (int)vr_config('return_days', 30);
    $steps = (int)vr_config('vault_steps', 6);
    $hours = (int)vr_config('vault_step_hours', 24);

    $de = [
        'herkunft' => [
            'kicker' => 'Herkunft',
            'title'  => 'Was „Belegkette“ bei uns tatsächlich bedeutet',
            'lead'   => 'Jeder verkauft „Original“. Der Unterschied liegt darin, was man vorlegen kann, wenn jemand nachfragt.',
            'body'   => [
                'Ein Kleidungsstück wird nicht dadurch echt, dass jemand es behauptet. Es wird nachvollziehbar dadurch, dass man den Weg zurückverfolgen kann: wer hat es wann von wem gekauft, mit welchem Beleg, und in welchem Land wurde es zuerst in Verkehr gebracht.',
                'Unsere Ware stammt aus Beständen innerhalb des Europäischen Wirtschaftsraums. Das ist keine Marketingformel, sondern die rechtliche Grundlage des Weiterverkaufs: Ist ein Markenartikel mit Zustimmung des Markeninhabers im EWR in Verkehr gebracht worden, ist das Markenrecht daran erschöpft (§ 24 MarkenG). Weiterverkauf ist dann erlaubt — ohne Vertragshändlerstatus, ohne Genehmigung.',
                'Für jedes Los halten wir die Einkaufsbelege vor. Bei Angeboten von Drittverkäufern verlangen wir dieselben Nachweise, bevor ein Angebot freigeschaltet wird; bei Unklarheiten bleibt es offline. Das ist der langweiligste Teil dieses Geschäfts und gleichzeitig der einzige, der zählt.',
                'Was wir nicht behaupten: dass eine Prüfung Fälschung mit hundertprozentiger Sicherheit ausschließt. Deshalb gilt zusätzlich eine einfache Regel — stellt sich ein Teil als nicht original heraus, erstatten wir den vollen Kaufpreis samt Versand und entfernen den Verkäufer. Unabhängig davon, wie lange die Bestellung her ist.',
            ],
        ],
        'vault' => [
            'kicker' => 'Premium Outlet',
            'title'  => 'Warum der Preis im Vault fällt — und warum er nie steigt',
            'lead'   => 'Ein Rabatt, den man nachrechnen kann. Die Mechanik in fünf Absätzen.',
            'body'   => [
                'Der übliche Outlet-Trick geht so: Preis hochsetzen, durchstreichen, „−70 %“ danebenschreiben. Rechtlich heikel, praktisch durchschaubar, und für den Käufer wertlos, weil der Referenzpreis nie echt war.',
                "Im Vault gibt es keinen Referenzpreis, den wir uns aussuchen. Jedes Los öffnet zu einem Eröffnungspreis und fällt in {$steps} gleichmäßigen Stufen, alle {$hours} Stunden eine, bis zum veröffentlichten Mindestpreis. Dieser Plan wird beim Öffnen festgeschrieben und danach nicht mehr angefasst — es gibt in der Software schlicht keinen Weg, ihn nachträglich zu ändern.",
                'Der Preis wird auf unserem Server aus diesem Plan berechnet. Nicht im Browser, nicht abhängig von Ihrem Gerät, Ihrem Standort oder davon, wie oft Sie die Seite besucht haben. Zwei Menschen, die gleichzeitig dasselbe Los ansehen, sehen denselben Betrag.',
                'Wenn wir eine Senkung ausweisen, nennen wir dazu den niedrigsten Preis der letzten 30 Tage — vorgeschrieben nach § 11 PAngV. Weil ein Vault-Preis ausschließlich fällt, ist das exakt der Preis, der vor der aktuellen Stufe galt. Und auf der ersten Stufe gibt es noch gar keine Senkung; dort steht deshalb kein durchgestrichener Preis, keine Prozentzahl, nichts.',
                'Bleibt der unangenehme Teil: Jedes Los existiert einmal. Wer auf die nächste Stufe wartet, zahlt weniger — oder findet es verkauft vor. Diese Spannung ist der Punkt der Sache, und sie ist ehrlich, weil beide Seiten dieselben Informationen haben.',
            ],
        ],
        'pflege' => [
            'kicker' => 'Pflege',
            'title'  => 'Damit das Stück Sie überlebt',
            'lead'   => 'Was mit Kaschmir, Leder, Denim und Logo-Prints wirklich passiert — und was Sie sich sparen können.',
            'body'   => [
                'Kaschmir und feine Wolle: nicht nach jedem Tragen waschen. Auslüften über Nacht erledigt fast alles. Wenn gewaschen werden muss, dann von Hand in lauwarmem Wasser mit Wollwaschmittel, nicht wringen, flach auf einem Handtuch trocknen. Bügel sind der häufigste Feind — schwere Strickteile gehören gefaltet ins Regal, sonst ziehen sich die Schultern.',
                'Denim: so selten wie möglich waschen, kalt, auf links, ohne Weichspüler. Jede Wäsche kostet Farbe und Passform. Bei Rohdenim die ersten Monate gar nicht — das ist keine Legende, sondern der Grund, warum die Verwaschungen später an Ihren Bewegungen entstehen und nicht an denen der Fabrik.',
                'Logo-Prints und Stickerei: immer auf links, maximal 30 Grad, nicht in den Trockner. Ein Print überlebt Waschen; er überlebt keine Hitze. Bügeln nur auf der Rückseite oder mit einem Tuch dazwischen.',
                'Leder und Nubuk: fern von Heizung und direkter Sonne, mit Schuhspannern aus Zedernholz lagern. Nass geworden? Bei Zimmertemperatur trocknen lassen, niemals föhnen. Zwei Paar im Wechsel tragen verdoppelt die Lebensdauer beider — ledernes Obermaterial braucht 24 Stunden, um Feuchtigkeit abzugeben.',
                'Bademode: nach jedem Tragen in klarem Wasser ausspülen. Chlor und Salz greifen Elasthan an, und zwar nicht beim Schwimmen, sondern in der Tasche danach.',
            ],
        ],
        'passform' => [
            'kicker' => 'Passform',
            'title'  => 'Italienische Größen lesen, ohne sich zu vertun',
            'lead'   => 'Warum 48 nicht gleich 48 ist, und wie Sie in zwei Minuten sicher sind.',
            'body'   => [
                'Die meisten Häuser in diesem Bestand etikettieren italienisch: 46, 48, 50. Das entspricht in Deutschland derselben Zahl — aber nur auf dem Papier. Der Schnitt entscheidet, und der ist bei Balenciaga bewusst weit, bei Dsquared2 bewusst schmal, bei Burberry klassisch.',
                'Verlassen Sie sich deshalb nicht auf die Zahl im letzten Kleidungsstück, sondern auf Ihre eigenen Maße: Brust, Taille, Hüfte. Zwei Minuten mit einem Maßband ersparen die Rücksendung. Die Tabellen dazu stehen in der Größenberatung, samt Anleitung, wo genau man ansetzt.',
                'Faustregel zwischen zwei Zeilen: bei körpernahen Schnitten die größere Größe, bei weiten Schnitten die kleinere. Ein Oversized-Hoodie, den man „passend“ kauft, sitzt am Ende wie ein Zelt.',
                "Und wenn es doch nicht passt: {$days} Tage Rückgabe bei eigener Ware, ungetragen und mit Etikett. Anprobieren ist ausdrücklich vorgesehen — dafür ist das Rückgaberecht da.",
            ],
        ],
        'privat' => [
            'kicker' => 'Marktplatz',
            'title'  => 'Händler oder Privatverkäufer — was sich für Sie ändert',
            'lead'   => 'Die Kennzeichnung auf der Produktseite ist keine Formalie. Sie entscheidet über Ihre Rechte.',
            'body'   => [
                "Auf {$brand} verkaufen drei Parteien: wir selbst, gewerbliche Händler und Privatpersonen. Auf jeder Produktseite steht, welche davon Ihr Vertragspartner ist — vor dem Bezahlen, nicht im Kleingedruckten danach.",
                'Bei uns und bei Händlern gilt das volle Verbraucherrecht: 14 Tage Widerruf ohne Begründung, gesetzliche Gewährleistung, Rechnung mit Mehrwertsteuer. Bei eigener Ware verlängern wir den Widerruf freiwillig.',
                'Bei Privatverkäufern gilt das nicht. Das Widerrufsrecht besteht nur gegenüber Unternehmern, und die Gewährleistung darf eine Privatperson ausschließen. Das ist keine Besonderheit dieser Plattform, sondern Gesetz — deshalb kennzeichnen wir es deutlich und lassen Sie es im Bestellvorgang ausdrücklich bestätigen.',
                'Eine Sache gilt trotzdem immer: Weicht die Ware wesentlich von der Beschreibung ab oder ist sie nicht original, bekommen Sie Ihr Geld zurück — auch bei einem privaten Verkäufer. Diesen Teil übernehmen wir als Plattform, weil sonst niemand ihn übernimmt.',
            ],
        ],
    ];

    $en = [
        'herkunft' => [
            'kicker' => 'Provenance',
            'title'  => 'What an "invoice trail" actually means here',
            'lead'   => 'Everyone sells "original". The difference is what you can produce when someone asks.',
            'body'   => [
                'A garment does not become genuine because someone says so. It becomes traceable when you can walk the path backwards: who bought it, when, from whom, on which invoice, and in which country it first entered the market.',
                'Our stock comes from inventories inside the European Economic Area. That is not a marketing phrase but the legal basis for resale: once a branded item has been placed on the EEA market with the trademark owner\'s consent, the trademark right in that item is exhausted. Resale is then lawful — no dealership, no permission needed.',
                'We hold purchase documentation for every lot. Third-party sellers must produce the same before a listing goes live; where anything is unclear, it stays offline. It is the dullest part of this business and the only part that matters.',
                'What we do not claim: that any inspection rules out a fake with total certainty. So one simple rule applies on top — if an item turns out not to be original, you get the full price back including shipping, and the seller leaves the platform. However long ago the order was.',
            ],
        ],
        'vault' => [
            'kicker' => 'Premium Outlet',
            'title'  => 'Why the Vault price falls — and never rises',
            'lead'   => 'A discount you can check with arithmetic. The mechanism in five paragraphs.',
            'body'   => [
                'The usual outlet trick: raise the price, strike it through, print "−70%" beside it. Legally delicate, practically transparent, and worthless to the buyer because the reference price was never real.',
                "In the Vault there is no reference price we get to choose. Every lot opens at a starting price and falls in {$steps} even steps, one every {$hours} hours, down to a published floor. That schedule is fixed the moment a lot opens and never touched again — there is simply no path in the software to change it afterwards.",
                'The price is computed on our server from that schedule. Not in your browser, not depending on your device, your location, or how many times you have visited. Two people looking at the same lot at the same moment see the same number.',
                'When we do announce a reduction, we state the lowest price of the previous 30 days, as German price-indication law requires. Because a Vault price only falls, that is exactly the price which applied before the current step. And on the first step there is no reduction at all — so there is no struck-through price, no percentage, nothing.',
                'Which leaves the uncomfortable part: each lot exists once. Wait for the next step and you pay less — or find it sold. That tension is the point, and it is honest, because both sides hold the same information.',
            ],
        ],
        'pflege' => [
            'kicker' => 'Care',
            'title'  => 'Making the piece outlast you',
            'lead'   => 'What really happens to cashmere, leather, denim and printed logos — and what you can skip.',
            'body'   => [
                'Cashmere and fine wool: do not wash after every wear. Airing overnight handles almost everything. When it must be washed, do it by hand in lukewarm water with wool detergent, never wring, dry flat on a towel. Hangers are the usual enemy — heavy knitwear belongs folded on a shelf, or the shoulders stretch.',
                'Denim: wash as rarely as possible, cold, inside out, no softener. Every wash costs colour and fit. With raw denim, not at all for the first months — that is not folklore, it is why the fades later follow your movements rather than the factory\'s.',
                'Printed logos and embroidery: always inside out, 30 degrees maximum, never a tumble dryer. A print survives washing; it does not survive heat. Iron only from the reverse, or with a cloth in between.',
                'Leather and nubuck: away from radiators and direct sun, stored with cedar shoe trees. Got wet? Dry at room temperature, never with a hairdryer. Alternating two pairs doubles the life of both — leather uppers need 24 hours to release moisture.',
                'Swimwear: rinse in clean water after every wear. Chlorine and salt attack elastane — not while you swim, but in the bag afterwards.',
            ],
        ],
        'passform' => [
            'kicker' => 'Fit',
            'title'  => 'Reading Italian sizing without getting it wrong',
            'lead'   => 'Why 48 is not always 48, and how to be certain in two minutes.',
            'body'   => [
                'Most houses in this stock label in Italian: 46, 48, 50. On paper that matches German sizing — on paper only. The cut decides, and it is deliberately wide at Balenciaga, deliberately slim at Dsquared2, classic at Burberry.',
                'So do not trust the number in your last garment; trust your own measurements: chest, waist, hips. Two minutes with a tape measure saves a return. The tables are in the size guide, with instructions on exactly where to place the tape.',
                'Rule of thumb between two rows: size up for close cuts, size down for wide ones. An oversized hoodie bought to "fit" ends up wearing you.',
                "And if it still does not work: {$days} days to return our own stock, unworn and tagged. Trying it on is explicitly expected — that is what the return window is for.",
            ],
        ],
        'privat' => [
            'kicker' => 'Marketplace',
            'title'  => 'Trader or private seller — what changes for you',
            'lead'   => 'The label on the product page is not a formality. It decides your rights.',
            'body'   => [
                "Three parties sell on {$brand}: us, registered traders, and private individuals. Every product page states which one is your counterparty — before you pay, not in the small print afterwards.",
                'With us and with traders, full consumer law applies: 14 days of withdrawal without reason, statutory warranty, an invoice with VAT. On our own stock we extend the window voluntarily.',
                'With private sellers it does not. The right of withdrawal exists only against businesses, and a private individual may exclude warranty. That is not a quirk of this platform but the law — which is why we label it plainly and ask you to confirm it during checkout.',
                'One thing holds regardless: if the goods differ materially from the description, or are not original, you get your money back — private seller included. We carry that part as the platform, because otherwise nobody would.',
            ],
        ],
    ];

    return vr_lang() === 'de' ? $de : $en;
}

/**
 * Yazının görseli — üretilmiş kompozisyon değil, katalogdan gerçek bir kadraj.
 *
 * NEDEN DEĞİŞTİ
 * -------------
 * Beş yazının beşi de aynı üretilmiş tişört çizimini gösteriyordu: liste
 * sayfası birbirinin aynı beş koyu dikdörtgendi. Journal'ın işi güven
 * kazandırmak; aynı çizimi beş kez basan bir sayfa bunu yapmıyor.
 *
 * Seçim rastgele değil: vitrinlik (tek özneli, yeterli çözünürlükte) kadrajlar
 * arasından, yazının listedeki sırasına göre sabit adımla alınıyor. Aynı yazı
 * her seferinde aynı fotoğrafı gösteriyor ve beş yazı beş farklı fotoğraf
 * alıyor. Katalog değişirse görsel de değişir — sorun değil, hiçbir yazı belli
 * bir parçadan söz etmiyor.
 */
function vr_journal_image(string $slug): ?string
{
    static $pool = null;

    if ($pool === null) {
        require_once __DIR__ . '/catalog.php';
        $grid = vr_photo_grid_index();
        $pool = [];
        foreach (vr_catalog() as $p) {
            foreach ((array)($p['images'] ?? []) as $img) {
                $img = (string)$img;
                if ($img === '' || !str_starts_with($img, '/uploads/')) continue;
                if (isset($grid[$img])) continue;
                $pool[] = $img;
                break;                       // ürün başına bir kadraj yeter
            }
        }
        // Yola göre sıralamak markaları kümeliyor (aynı klasör = aynı ev) ve
        // beş yazının üçü arka arkaya Lacoste polosu çıkıyordu. Yolun
        // özetine göre sıralamak evleri karıştırıyor — ve yine sabit.
        usort($pool, fn($a, $b) => strcmp(md5($a), md5($b)));
    }

    if (!$pool) return null;

    $slugs = array_keys(vr_journal_entries());
    $i     = array_search($slug, $slugs, true);
    if ($i === false) $i = 0;

    // Asal adım: yazılar havuzun birbirine uzak yerlerinden fotoğraf alıyor,
    // yan yana duran iki kart aynı çekimin iki karesi olmuyor.
    return $pool[(int)((($i + 1) * 197) % count($pool))];
}
