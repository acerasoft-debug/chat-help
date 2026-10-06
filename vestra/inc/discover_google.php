<?php
/**
 * Google-backed customer discovery.
 *
 * WHY THIS EXISTS
 * OpenStreetMap is free and needs no key, but its coverage of small independent
 * clothing shops is thin and uneven: a boutique that has traded on the same street
 * for twenty years is often simply not in OSM, and when it is, the `website` tag is
 * frequently missing. Google Maps has that shop, its address, its phone and its
 * site. Searching Google by hand really does beat the tool — this file closes that
 * gap by asking Google through its own APIs.
 *
 * WHAT THIS DELIBERATELY DOES NOT DO
 * It does not fetch google.com/search and read the HTML. That breaks Google's terms,
 * and from a shared cPanel IP it stops working within minutes (CAPTCHA, then a block)
 * — the feature would look fine in testing and quietly return nothing a week later.
 * Two official APIs instead:
 *
 *   1. Places API (New), places:searchText  — Google Maps' own business records.
 *      Gives name, formatted address, website, phone. This is the "adres" half.
 *   2. Custom Search JSON API                — the web index. Used as an email
 *      fallback for domains whose own contact page hides the address behind a form,
 *      because Google's snippet often carries the mailto it found. The "email" half.
 *
 * Both keys live in data/email_settings.json, written from the admin panel, chmod
 * 600 and web-denied. This repository is public: a key must never arrive here, nor
 * through a workflow input, nor through anything that gets echoed into an Actions log.
 */

require_once __DIR__.'/notify.php';   // vestra_cfg, vestra_domain_of, vestra_discover_blocklist

/* ── keys ─────────────────────────────────────────────────────────────────── */

function vestra_google_key(): string { return trim((string)vestra_cfg('google_key','')); }
/** Programmable Search Engine id (cx). Only the email fallback needs it. */
function vestra_google_cx(): string  { return trim((string)vestra_cfg('google_cx','')); }

/* ── run state ────────────────────────────────────────────────────────────── */

/**
 * Did the last vestra_discover_google() reach Google at all?
 *
 * Separate from "found nothing", for the same reason vestra_osm_ok() is separate
 * from vestra_osm_timeout(): a rejected key, an unbilled project and a genuinely
 * empty city all end in an empty array, and telling the operator "no shops here"
 * when the truth is "billing is off" sends them looking in the wrong place. Every
 * failure path below writes a sentence into vestra_google_note() saying which it was.
 */
function vestra_google_ok(?bool $set = null): bool {
    static $ok = true;
    if ($set !== null) $ok = $set;
    return $ok;
}
function vestra_google_note(?string $set = null): string {
    static $n = '';
    if ($set !== null) $n = $set;
    return $n;
}

/* ── country → language / region / search phrases ─────────────────────────── */

/**
 * What to type into Google for each country.
 *
 * The phrases are in the local language on purpose. "clothing boutique Milano"
 * returns tourist listicles; "abbigliamento multimarca Milano" returns the shops.
 * regionCode/languageCode bias the ranking the same way a local browser would.
 */
function vestra_google_locale(string $country): array {
    $map = [
        'germany'        => ['DE','de',['Modeboutique','Bekleidungsgeschäft','Damenmode Geschäft','Herrenmode Geschäft','Multibrand Store Mode']],
        'netherlands'    => ['NL','nl',['modeboutique','kledingwinkel dames','kledingwinkel heren','multibrand kledingwinkel']],
        'poland'         => ['PL','pl',['butik odzieżowy','sklep odzieżowy damski','sklep odzieżowy męski','odzież multibrand']],
        'france'         => ['FR','fr',['boutique multimarque vêtements','prêt-à-porter femme boutique','prêt-à-porter homme boutique','concept store mode']],
        'italy'          => ['IT','it',['abbigliamento multimarca','boutique abbigliamento donna','boutique abbigliamento uomo','negozio abbigliamento firmato']],
        'spain'          => ['ES','es',['tienda de ropa multimarca','boutique de moda mujer','boutique de moda hombre','tienda ropa de marca']],
        'united kingdom' => ['GB','en',['independent clothing boutique','multi-brand fashion boutique','designer menswear boutique','independent womenswear shop']],
        'united states'  => ['US','en',['independent clothing boutique','multi-brand fashion boutique','designer consignment boutique','independent menswear shop']],
        'australia'      => ['AU','en',['independent clothing boutique','multi-brand fashion boutique','designer womenswear boutique']],
        'uae'            => ['AE','en',['multi-brand fashion boutique','designer clothing boutique','independent fashion store']],
        'turkey'         => ['TR','tr',['çok markalı giyim mağazası','butik giyim mağazası','kadın giyim butik','erkek giyim butik']],
        'portugal'       => ['PT','pt',['loja de roupa multimarca','boutique de moda','loja roupa de marca']],
        'belgium'        => ['BE','nl',['modeboutique','kledingwinkel','boutique multimarque vêtements']],
        'switzerland'    => ['CH','de',['Modeboutique','Bekleidungsgeschäft','boutique multimarque vêtements']],
        'austria'        => ['AT','de',['Modeboutique','Bekleidungsgeschäft','Damenmode Geschäft']],
        'greece'         => ['GR','el',['κατάστημα ρούχων','boutique ρούχων','πολυκατάστημα μόδας']],
        'sweden'         => ['SE','sv',['klädbutik','modebutik dam','modebutik herr']],
        'denmark'        => ['DK','da',['tøjbutik','modebutik dame','modebutik herre']],
        'norway'         => ['NO','no',['klesbutikk','motebutikk dame','motebutikk herre']],
        'ireland'        => ['IE','en',['independent clothing boutique','multi-brand fashion boutique']],
        'canada'         => ['CA','en',['independent clothing boutique','multi-brand fashion boutique','designer consignment boutique']],
        'mexico'         => ['MX','es',['tienda de ropa multimarca','boutique de moda','tienda ropa de marca']],
    ];
    $k = strtolower(trim($country));
    /* An unknown country still works — English phrases plus the country name in the
       query text. Worse hit-rate than a localised list, but far better than the
       silent empty array the operator would otherwise get for, say, Romania. */
    return $map[$k] ?? ['', 'en', ['multi-brand clothing boutique','independent fashion store','designer clothing shop']];
}

/* ── Places API (New) ─────────────────────────────────────────────────────── */

/**
 * One places:searchText call. Returns the decoded body, or [] on failure — and on
 * failure it is this function that writes the human sentence into vestra_google_note().
 */
function vestra_google_places_call(string $query, string $region, string $lang, string $pageToken = ''): array {
    $key = vestra_google_key();
    if ($key === '') { vestra_google_ok(false); vestra_google_note('Google anahtarı girilmemiş — Müşteriler sekmesindeki "Google ile ara" kartından ekleyin.'); return []; }

    $body = ['textQuery' => $query, 'languageCode' => $lang, 'pageSize' => 20];
    if ($region !== '')    $body['regionCode'] = $region;
    if ($pageToken !== '') $body['pageToken']  = $pageToken;

    /* The field mask is mandatory and it is also the bill: ask for exactly the four
       fields the lead list stores and nothing else. Adding "everything" here is how
       a discovery run silently becomes an expensive one. */
    $mask = 'places.displayName,places.formattedAddress,places.websiteUri,'
          . 'places.nationalPhoneNumber,places.primaryType,places.types,'
          . 'places.businessStatus,nextPageToken';

    $ch = curl_init('https://places.googleapis.com/v1/places:searchText');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Goog-Api-Key: '.$key, 'X-Goog-FieldMask: '.$mask],
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);

    if ($code >= 200 && $code < 300 && is_string($raw) && $raw !== '') {
        vestra_google_ok(true);
        $d = json_decode($raw, true);
        return is_array($d) ? $d : [];
    }

    vestra_google_ok(false);
    /* Google puts the real reason in the body, not the status line. 403 alone could be
       a bad key, a disabled API or an unbilled project — three different fixes. Read it. */
    $msg = '';
    if (is_string($raw) && $raw !== '') {
        $e = json_decode($raw, true);
        $msg = (string)($e['error']['message'] ?? '');
    }
    $low = strtolower($msg);
    if ($code === 0) {
        $note = 'Google\'a ulaşılamadı ('.($cerr !== '' ? $cerr : 'bağlantı hatası').'). Birkaç dakika sonra tekrar deneyin.';
    } elseif (str_contains($low, 'billing')) {
        $note = 'Google Cloud projesinde faturalandırma açık değil. Places API ücretsiz aylık kotayla bile faturalandırmanın etkin olmasını istiyor — Cloud Console → Billing.';
    } elseif (str_contains($low, 'api key not valid') || str_contains($low, 'api_key_invalid')) {
        $note = 'Google anahtarı geçersiz. Cloud Console → Credentials\'tan yeni bir anahtar alıp panele yapıştırın.';
    } elseif (str_contains($low, 'has not been used') || str_contains($low, 'is disabled')) {
        $note = 'Places API (New) bu projede etkin değil. Cloud Console → APIs & Services → Enable APIs → "Places API (New)".';
    } elseif ($code === 429 || str_contains($low, 'quota')) {
        $note = 'Google kotası doldu (günlük/dakikalık sınır). Yarın tekrar deneyin ya da Cloud Console\'dan kotayı yükseltin.';
    } else {
        $note = 'Google Places HTTP '.$code.($msg !== '' ? ' — '.mb_substr($msg, 0, 160) : '');
    }
    vestra_google_note($note);
    error_log('[VESTRA google] places HTTP '.$code.' '.mb_substr($msg, 0, 200));
    return [];
}

/**
 * Discover apparel retailers through Google Maps.
 *
 * Returns rows in exactly the shape vestra_leads_add() takes, so the caller cannot
 * tell a Google row from an OSM row apart from the `source` field — which is the
 * point: discovery gains a second source without the rest of the pipeline changing.
 */
function vestra_discover_google(string $country, string $city = '', int $limit = 80): array {
    $country = trim($country); $city = trim($city);
    if ($country === '') return [];
    vestra_google_ok(true); vestra_google_note('');
    if (vestra_google_key() === '') {
        vestra_google_ok(false);
        vestra_google_note('Google anahtarı girilmemiş — Müşteriler sekmesindeki "Google ile ara" kartından ekleyin.');
        return [];
    }

    [$region, $lang, $phrases] = vestra_google_locale($country);
    $where = $city !== '' ? $city.', '.$country : $country;
    $block = vestra_discover_blocklist();

    /* Types Google gives a shop. department_store / shopping_mall / supermarket are
       the wrong audience (they buy through a head office, not from a wholesaler) and
       they crowd out the boutiques, so they are dropped rather than ranked down. */
    $wantType = ['clothing_store','shoe_store','store','boutique'];
    $badType  = ['department_store','shopping_mall','supermarket','wholesaler','home_goods_store',
                 'furniture_store','jewelry_store','electronics_store','convenience_store'];

    $out = []; $seen = [];
    foreach ($phrases as $phrase) {
        if (count($out) >= $limit) break;
        $token = '';
        /* Places caps a text search at 60 results (3 pages of 20). Ask for all three
           only while the cap still has room — each page is a separately billed call. */
        for ($page = 0; $page < 3; $page++) {
            if (count($out) >= $limit) break;
            $d = vestra_google_places_call($phrase.' '.$where, $region, $lang, $token);
            if (!$d) {
                /* A transport-level failure is per-call and the next phrase may well
                   work; a rejected key or an unbilled project will not, and retrying it
                   four more times only spends four more round trips to reach the same
                   sentence. Stop the whole run in that case. */
                if (!vestra_google_ok()) break 2;
                break;                            // note already written by the caller
            }
            foreach (($d['places'] ?? []) as $p) {
                $name = trim((string)($p['displayName']['text'] ?? ''));
                if ($name === '') continue;
                $k = mb_strtolower($name);
                if (isset($seen[$k])) continue;   // same shop across two phrases
                $seen[$k] = true;

                if (($p['businessStatus'] ?? 'OPERATIONAL') !== 'OPERATIONAL') continue;

                $types = array_map('strval', (array)($p['types'] ?? []));
                $prim  = (string)($p['primaryType'] ?? '');
                if (array_intersect($types, $badType)) continue;
                if ($prim !== '' && !in_array($prim, $wantType, true) && !array_intersect($types, $wantType)) continue;

                $blocked = false;
                foreach ($block as $b) { if (str_contains($k, $b)) { $blocked = true; break; } }
                if ($blocked) continue;

                $web = (string)($p['websiteUri'] ?? '');
                $out[] = [
                    'company' => $name,
                    'website' => $web,
                    'email'   => '',                       // Places never returns one; that is the next step
                    'phone'   => (string)($p['nationalPhoneNumber'] ?? ''),
                    'country' => $country,
                    'city'    => $city,
                    'address' => (string)($p['formattedAddress'] ?? ''),
                    'category'=> 'Retailer ('.($prim !== '' ? $prim : 'clothing_store').')',
                    'source'  => 'Google Maps',
                    '_hasweb' => $web !== '',
                ];
                if (count($out) >= $limit) break;
            }
            $token = (string)($d['nextPageToken'] ?? '');
            if ($token === '') break;
        }
    }

    /* Same ordering rule as the OSM source: a shop that already lists a website is
       far likelier to yield an email in the next step, so it fills the cap first. */
    usort($out, fn($a, $b) => ($b['_hasweb'] <=> $a['_hasweb']));
    $out = array_slice($out, 0, $limit);
    foreach ($out as &$r) unset($r['_hasweb']);
    unset($r);

    if (!$out && vestra_google_ok() && vestra_google_note() === '') {
        vestra_google_note($city === ''
            ? 'Google bu ülke için aradığımız kategorilerde sonuç döndürmedi. Şehir yazıp deneyin — Maps aramaları şehir bazında çok daha isabetli.'
            : 'Google bu şehirde aradığımız kategorilerde dükkan bulamadı. Şehir adını yerel dilde yazmayı deneyin (Milano / München / Sevilla).');
    }
    return $out;
}

/* ── Custom Search: the email half ────────────────────────────────────────── */

/**
 * Ask Google's index for an address published on this domain.
 *
 * Runs only after the free site-reader has already failed, because it costs a query
 * and the site-reader is free. It earns its place on the sites that put the address
 * in an image, behind a contact form, or three clicks deep — Google has usually
 * indexed a page that spells it out.
 *
 * Only addresses ON the asked-for domain are accepted. Without that check the first
 * result for a small shop is frequently its web agency's own mailbox, and the lead
 * list would fill up with agencies.
 *
 * NOTE for the operator: the Programmable Search Engine must have "Search the entire
 * web" switched on, otherwise a site: query against an arbitrary domain returns
 * nothing at all and this looks broken rather than unconfigured.
 */
function vestra_google_cse_email(string $website): string {
    $domain = vestra_domain_of($website);
    if ($domain === '') return '';
    $key = vestra_google_key(); $cx = vestra_google_cx();
    if ($key === '' || $cx === '') return '';

    /* One dead-key latch for the whole request, same as the Hunter/Anymailfinder path:
       a rejected key otherwise costs one 20-second round trip per lead, several hundred
       times per discovery run, and fills the error log with the same line. */
    static $dead = false;
    if ($dead) return '';

    $q = 'site:'.$domain.' (contact OR contatti OR kontakt OR contacto OR contato OR impressum OR "e-mail" OR email)';
    $url = 'https://www.googleapis.com/customsearch/v1?key='.urlencode($key).'&cx='.urlencode($cx)
         . '&num=10&q='.urlencode($q);

    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 8]);
    $raw = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);

    if ($code === 401 || $code === 403) { $dead = true; error_log('[VESTRA google] cse HTTP '.$code.' — anahtar reddedildi, bu istek boyunca CSE atlaniyor'); return ''; }
    if ($code < 200 || $code >= 300 || !is_string($raw) || $raw === '') {
        if ($code) error_log('[VESTRA google] cse HTTP '.$code);
        return '';
    }
    $d = json_decode($raw, true);
    if (!is_array($d)) return '';

    /* Scan title + snippet + the pagemap Google extracts; an address can sit in any
       of the three and only checking `snippet` misses a good share of them. */
    $hay = '';
    foreach (($d['items'] ?? []) as $it) {
        $hay .= ' '.(string)($it['title'] ?? '').' '.(string)($it['snippet'] ?? '').' '.(string)($it['htmlSnippet'] ?? '');
        if (isset($it['pagemap']) && is_array($it['pagemap'])) $hay .= ' '.json_encode($it['pagemap']);
    }
    if ($hay === '') return '';
    $hay = str_replace(['&#64;', ' [at] ', '(at)', ' at ', '&#46;'], ['@', '@', '@', '@', '.'], $hay);
    if (!preg_match_all('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $hay, $m)) return '';

    $generic = ['info','contact','contatti','kontakt','contacto','sales','vendite','ventas','hello','shop','office','mail'];
    $best = '';
    foreach ($m[0] as $e) {
        $e = strtolower(rtrim($e, '.'));
        if (!filter_var($e, FILTER_VALIDATE_EMAIL)) continue;
        $host = substr($e, strpos($e, '@') + 1);
        // on-domain only (or a subdomain of it) — see the note above about agencies
        if ($host !== $domain && !str_ends_with($host, '.'.$domain)) continue;
        if (preg_match('/\.(png|jpe?g|gif|webp|svg)$/', $e)) continue;   // sprite filenames look like addresses
        $local = substr($e, 0, strpos($e, '@'));
        if (in_array($local, $generic, true)) return $e;                 // a shop mailbox beats a person's
        if ($best === '') $best = $e;
    }
    return $best;
}
