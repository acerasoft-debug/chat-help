<?php
/**
 * ChatHelp — Stripe Checkout: TOKEN paketi, SERBEST miktar veya AYLIK PAKET/AGENT
 * ==============================================================================
 *   /chat/tokens-checkout.php?pack=10k|50k|200k        → sabit token paketi (Stripe Price ID, STRIPE_TOKEN_PRICES)
 *   /chat/tokens-checkout.php?tokens=37000[&email=..]  → istediği miktar (kademeli tarife, price_data, tek seferlik)
 *   /chat/tokens-checkout.php?plan=agent-support       → AYLIK abonelik (plans.php; price_data + recurring; dahil tokenlar her ay yüklenir)
 *   /chat/tokens-checkout.php?plan=buy-termine         → Einmalkauf (Lizenz + Startguthaben + CH_BUY_INCLUDED_DAYS Tage Betrieb)
 *   /chat/tokens-checkout.php?plan=buy-amazon-fba&term=2  → Lizenz mit Laufzeitwahl (CH_BUY_TERMS: Preis + Betriebstage je Laufzeit)
 *   /chat/tokens-checkout.php?plan=betrieb-termine&email=…  bzw. byok-termine  → Folge-Abo zur Lizenz (Lizenz muss zum Konto gehören;
 *                                                         während des Inklusiv-Betriebs startet die Abrechnung erst danach: trial_end)
 * db.php: define('STRIPE_SK','sk_live_…')  (alternatif adlar: STRIPE_SECRET, STRIPE_SECRET_KEY)
 *         define('STRIPE_TOKEN_PRICES', ['10k'=>'price_…','50k'=>'price_…','200k'=>'price_…']);  // sabit paketler için (isteğe bağlı)
 * Kademeli tarife siteyle BİREBİR aynı (tk_unit). Metadata webhook tarafından okunur. Mevcut stripe-checkout.php'ye DOKUNMAZ.
 *
 * Landeswährung: &cur=USD|GBP|CHF|TRY|SEK|NOK|DKK|PLN|JPY|AZN|CAD|AUD (Tabelle CH_CURRENCIES in i18n.php, nur ausdrücklich per Parameter –
 *   Studio-Links ohne cur bleiben EUR). Der Stripe-Preis ist der umgerechnete, „schön“ gerundete Landespreis (ch_price_local, derselbe Betrag wie
 *   auf der Website), Abos laufen in derselben Währung (price_data + recurring). Metadaten zusätzlich: currency, eur_cents, fx_rate, fx_date –
 *   die Gutschrift im Webhook bleibt an plan/tokens/pack gebunden (unverändert). Nicht freigeschaltete Währung (CH_STRIPE_CURRENCIES in db.php)
 *   oder Stripe lehnt die Währung ab → automatisch EUR. RUB: keine Zahlung in Rubel → EUR.  &lang=xx → Sprache der Stripe-Bezahlseite.
 */
require_once __DIR__.'/tokens-lib.php';   // db.php + billing-lib (Lizenzprüfung für Betrieb / eigenen Schlüssel)
require_once __DIR__.'/plans.php';
/* Yönlendirme (Stripe oturum URL'si) kişiye özel → asla edge/platform önbelleğine girmesin (platform varsayılanı 31 gün public) */
header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0'); header('Cloudflare-CDN-Cache-Control: no-store'); header('CDN-Cache-Control: no-store'); header('Pragma: no-cache'); header('Expires: 0');
$sk = defined('STRIPE_SK') ? STRIPE_SK : (defined('STRIPE_SECRET') ? STRIPE_SECRET : (defined('STRIPE_SECRET_KEY') ? STRIPE_SECRET_KEY : ''));
if(!$sk){ http_response_code(500); exit('Stripe anahtarı (STRIPE_SK / STRIPE_SECRET / STRIPE_SECRET_KEY) db.php\'de tanımlı değil.'); }

function tk_unit(int $n): float { return $n>=1000000?0.0025:($n>=200000?0.00345:($n>=50000?0.0040:($n>=10000?0.0049:0.0055))); }
const PACKS = ['10k'=>10000,'50k'=>50000,'200k'=>200000];
const SITE  = 'https://chat-help.com';

$prices = defined('STRIPE_TOKEN_PRICES') ? STRIPE_TOKEN_PRICES : [];
$pack   = preg_replace('/[^0-9a-z]/','',strtolower($_GET['pack'] ?? ''));
$planId = preg_replace('/[^a-z0-9-]/','',strtolower($_GET['plan'] ?? ''));
$req    = (int)preg_replace('/\D/','',(string)($_GET['tokens'] ?? ''));
$email  = (!empty($_GET['email']) && filter_var($_GET['email'],FILTER_VALIDATE_EMAIL)) ? $_GET['email'] : '';

$params = [
  'cancel_url'=>SITE.'/#tokens',
  'allow_promotion_codes'=>'true','automatic_tax[enabled]'=>'true','tax_id_collection[enabled]'=>'true',
  'line_items[0][quantity]'=>1,
];
if($email) $params['customer_email']=$email;

if($planId !== ''){                                                    /* ---- AYLIK PAKET / AGENT / EINMALKAUF / BETRIEB / EIGENER SCHLÜSSEL ---- */
  $pl = ch_plan($planId);
  if(!$pl){ http_response_code(404); header('Content-Type: text/plain; charset=UTF-8'); exit("Bilinmeyen paket: $planId\nMevcut: ".implode(', ',array_keys(CH_PLANS))); }
  if(isset(CH_BUY_TERMS[$pl['id']])){                                 // Laufzeitwahl (z. B. buy-amazon-fba: &term=1|2 Jahre)
    $tm=ch_buy_term($pl['id'],(int)($_GET['term'] ?? 1));
    if(!$tm){ http_response_code(400); header('Content-Type: text/plain; charset=UTF-8'); exit('Unbekannte Laufzeit. Erlaubt: term='.implode('|',array_keys(CH_BUY_TERMS[$pl['id']]))); }
    $pl['term']=$tm;
  }
  $res = bl_checkout_plan_params($pl,$email,SITE);   // billing-lib: Lizenzprüfung, trial_end bis Ende des Inklusiv-Betriebs, Metadaten
  if(isset($res['error'])){ http_response_code((int)$res['status']); header('Content-Type: text/plain; charset=UTF-8'); exit($res['message']); }
  $params = $res['params'];
} elseif($req>0){                                                      /* ---- SERBEST TOKEN MİKTARI ---- */
  $n=max(1000,min(5000000,(int)(round($req/1000)*1000)));
  $cents=(int)round($n*tk_unit($n)*100);
  $params['mode']='payment'; $params['invoice_creation[enabled]']='true';
  $params['success_url']=SITE.'/chat/studio.php?tokens=success&session_id={CHECKOUT_SESSION_ID}';
  $params['line_items[0][price_data][currency]']='eur';
  $params['line_items[0][price_data][unit_amount]']=$cents;
  $params['line_items[0][price_data][tax_behavior]']='exclusive';
  $params['line_items[0][price_data][product_data][name]']='ChatHelp Tokens – '.number_format($n,0,',','.');
  $params['line_items[0][price_data][product_data][description]']=number_format(tk_unit($n),4,',','.').' € / Token · gültig für alle Pakete, Agenten & API · verfallen nicht';
  $params['metadata[type]']='tokens'; $params['metadata[tokens]']=$n; $params['metadata[pack]']='custom';
} elseif(isset($prices[$pack])){                                       /* ---- SABİT PAKET (Price ID) ---- */
  $params['mode']='payment'; $params['invoice_creation[enabled]']='true';
  $params['success_url']=SITE.'/chat/studio.php?tokens=success&session_id={CHECKOUT_SESSION_ID}';
  $params['line_items[0][price]']=$prices[$pack];
  $params['metadata[type]']='tokens'; $params['metadata[tokens]']=PACKS[$pack] ?? 0; $params['metadata[pack]']=$pack;
} elseif(isset(PACKS[$pack])){                                         /* ---- SABİT PAKET (Price ID tanımlı değilse price_data) ---- */
  $n=PACKS[$pack]; $cents=(int)round($n*tk_unit($n)*100);
  $params['mode']='payment'; $params['invoice_creation[enabled]']='true';
  $params['success_url']=SITE.'/chat/studio.php?tokens=success&session_id={CHECKOUT_SESSION_ID}';
  $params['line_items[0][price_data][currency]']='eur';
  $params['line_items[0][price_data][unit_amount]']=$cents;
  $params['line_items[0][price_data][tax_behavior]']='exclusive';
  $params['line_items[0][price_data][product_data][name]']='ChatHelp Tokens – '.number_format($n,0,',','.').' ('.strtoupper($pack).')';
  $params['metadata[type]']='tokens'; $params['metadata[tokens]']=$n; $params['metadata[pack]']=$pack;
} else { http_response_code(400); header('Content-Type: text/plain; charset=UTF-8'); exit('Geçersiz istek. ?tokens=N (min 1000) · ?pack=10k|50k|200k · ?plan=<id>'); }

/* ---- Landeswährung: EUR-Parameter → Währung + umgerechneter, gerundeter Betrag; EUR-Gegenwert in die Metadaten ---- */
$cur=ch_cur_norm((string)($_GET['cur'] ?? '')); if($cur==='' || !ch_cur_checkout($cur)) $cur='EUR';
$eurParams=$params;
$lang=ch_lang_norm((string)($_GET['lang'] ?? ''));
if($lang!==''){ $sl=['no'=>'nb','az'=>'']; $sl=$sl[$lang] ?? $lang; if($sl!==''){ $params['locale']=$sl; $eurParams['locale']=$sl; } }
/** Parameter auf Währung $c umstellen (Kopie). Fester Price-ID-Paketpreis ist EUR → in Fremdwährung als price_data. */
function co_currency(array $p, string $c): array {
    $base=isset($p['line_items[0][price_data][unit_amount]']) ? (int)$p['line_items[0][price_data][unit_amount]'] : null;
    $priceId=$base===null && isset($p['line_items[0][price]']);
    if($priceId){ $n=(int)($p['metadata[tokens]'] ?? 0); $base=(int)round($n*tk_unit($n)*100); }
    if($base===null || $base<=0) return $p;
    if($priceId && $c!=='EUR'){
        unset($p['line_items[0][price]']); $p['line_items[0][price_data][tax_behavior]']='exclusive';
        $p['line_items[0][price_data][product_data][name]']='ChatHelp Tokens – '.number_format($n,0,',','.').' ('.strtoupper((string)($p['metadata[pack]'] ?? '')).')';
    }
    $fx=ch_fx_rates(); $local=ch_price_local($base,$c,$fx);
    if(!($priceId && $c==='EUR')){ $p['line_items[0][price_data][currency]']=strtolower($c); $p['line_items[0][price_data][unit_amount]']=$local; }
    if($c!=='EUR'){ $k='line_items[0][price_data][product_data][description]';
        $p[$k]=mb_substr(implode(' · ',array_filter([trim((string)($p[$k] ?? '')),'Preisbasis '.number_format($base/100,2,',','.').' € netto, umgerechnet in '.$c])),0,500,'UTF-8'); }
    $meta=['currency'=>$c,'eur_cents'=>$base,'amount_local'=>$local]+($c!=='EUR' ? ['fx_rate'=>(string)$fx['rates'][$c],'fx_date'=>(string)($fx['date'] ?? 'fallback')] : []);
    foreach(['metadata','subscription_data[metadata]'] as $m){
        if($m!=='metadata' && ($p['mode'] ?? '')!=='subscription') continue;
        foreach($meta as $k=>$v) $p[$m.'['.$k.']']=$v; }
    return $p;
}
function co_stripe(string $sk, array $p): array {
    $ch=curl_init(bl_stripe_base().'/checkout/sessions');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($p),CURLOPT_USERPWD=>$sk.':',CURLOPT_TIMEOUT=>20]);
    $res=json_decode((string)curl_exec($ch),true); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    return [$code,is_array($res) ? $res : []];
}
$params=co_currency($params,$cur);
[$code,$res]=co_stripe($sk,$params);
/* Währung fürs Konto nicht freigeschaltet / von Stripe abgelehnt → einmal in EUR wiederholen */
if($cur!=='EUR' && $code>=400 && $code<500 && preg_match('/currenc/i',(string)($res['error']['param'] ?? '').' '.(string)($res['error']['message'] ?? '').' '.(string)($res['error']['code'] ?? ''))){
    error_log('CH tokens-checkout: Währung '.$cur.' abgelehnt ('.($res['error']['message'] ?? $code).') → EUR');
    $params=co_currency($eurParams,'EUR'); [$code,$res]=co_stripe($sk,$params);
}
if($code>=200 && $code<300 && !empty($res['url'])){ header('Location: '.$res['url'],true,303); exit; }
http_response_code(502); header('Content-Type: text/plain; charset=UTF-8');
echo "Stripe Checkout oluşturulamadı (HTTP $code): ".($res['error']['message'] ?? 'bilinmeyen hata');
