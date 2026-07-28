<?php
/**
 * VESTRA — local config loader + outgoing email (plain PHP mail()).
 * Real settings live in inc/config.php (NOT in git). Falls back to safe defaults.
 */
require_once __DIR__.'/i18n.php';       // vestra_user_lang() — every template call site resolves the recipient's language
require_once __DIR__.'/email_templates.php';
function vestra_cfg($k,$def=null){
  static $c=null;
  if($c===null){
    $f=__DIR__.'/config.php'; $c=is_readable($f)?(require $f):[]; if(!is_array($c)) $c=[];
    // Admin-editable sending settings (Admin → Customers → Sending email) override
    // config.php so the operator can point outbound mail at their own address/SMTP
    // without editing files. Stored web-denied + gitignored under data/.
    $mf=dirname(__DIR__).'/data/email_settings.json';
    if(is_readable($mf)){ $m=json_decode((string)file_get_contents($mf),true);
      if(is_array($m)) foreach($m as $mk=>$mv){ if($mv!=='' && $mv!==null) $c[$mk]=$mv; } }
    // Reuse server constants (e.g. the ones already in chat/config.php: SMTP_* and
    // DEEPSEEK_KEY) as defaults when a vestra setting isn't otherwise configured —
    // define them in inc/config.php and sending / AI work with no re-entry.
    foreach(['smtp_host'=>'SMTP_HOST','smtp_port'=>'SMTP_PORT','smtp_user'=>'SMTP_USER','smtp_pass'=>'SMTP_PASS','smtp_from'=>'SMTP_FROM','mail_from'=>'SMTP_FROM','ai_key'=>'DEEPSEEK_KEY'] as $ck=>$const){
      if(($c[$ck]??'')==='' && defined($const) && (string)constant($const)!=='') $c[$ck]=(string)constant($const);
    }
    if(!isset($c['mail_enabled']) && ($c['smtp_host']??'')!=='') $c['mail_enabled']=true;
  }
  return array_key_exists($k,$c) ? $c[$k] : $def;
}

/* Per-seller sending identity (their OWN From address + SMTP/API) so offers/outreach
 * go out truly "from" the seller — best deliverability (their provider signs SPF/DKIM).
 * Stored web-denied + gitignored under data/, keyed by seller account id; same key shape
 * as the global settings (smtp_host, smtp_port, smtp_user, smtp_pass, mail_from, smtp_name). */
function vestra_seller_mail_all(): array {
  $f=dirname(__DIR__).'/data/seller_mail.json';
  if(is_readable($f)){ $d=json_decode((string)file_get_contents($f),true); if(is_array($d)) return $d; }
  return [];
}
function vestra_seller_mail(string $uid): array {
  $a=vestra_seller_mail_all(); return (isset($a[$uid])&&is_array($a[$uid]))?$a[$uid]:[];
}
function vestra_seller_mail_save(string $uid, array $cfg): void {
  $dir=dirname(__DIR__).'/data'; if(!is_dir($dir)) @mkdir($dir,0775,true);
  $a=vestra_seller_mail_all(); $a[$uid]=$cfg;
  file_put_contents($dir.'/seller_mail.json',json_encode($a,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
  @chmod($dir.'/seller_mail.json',0600);
}
/* True when a seller has a usable own-transport configured. */
function vestra_seller_can_send(array $cfg): bool {
  return (($cfg['smtp_host']??'')!=='' && ($cfg['smtp_pass']??'')!=='') || ($cfg['mail_api_key']??'')!=='';
}

/* Extract a bare host/domain from a URL or host string ('' if it isn't a domain). */
function vestra_domain_of(string $website): string {
  $d=strtolower(trim($website));
  $d=preg_replace('#^https?://#','',$d);
  $d=preg_replace('~[/?#].*$~','',$d);
  $d=preg_replace('#^www\.#','',$d);
  return ($d===''||strpos($d,'.')===false)?'':$d;
}

/* Minimal HTTP GET (browser-ish UA, follows redirects, size-capped). '' on any error. */
function vestra_http_get(string $url, int $timeout=12): string {
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>4,
    CURLOPT_TIMEOUT=>$timeout,CURLOPT_CONNECTTIMEOUT=>7,
    CURLOPT_USERAGENT=>'Mozilla/5.0 (compatible; VestraBot/1.0; +https://vestrasales.com)',
    CURLOPT_HTTPHEADER=>['Accept: text/html,application/xhtml+xml'],CURLOPT_ENCODING=>'']);
  $r=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  if($r===false||$code<200||$code>=400) return '';
  return substr((string)$r,0,600000);
}

/* Pull email addresses out of one HTML page into a score map (mailto: links weigh most,
 * plain text least; simple " at "/" dot " de-obfuscation). Mutates $scores. */
function vestra_harvest_emails(string $html, array &$scores): void {
  if(preg_match_all('#mailto:([^"\'>?\s]+)#i',$html,$m)) foreach($m[1] as $e){
    $e=strtolower(rawurldecode($e)); if(filter_var($e,FILTER_VALIDATE_EMAIL)) $scores[$e]=($scores[$e]??0)+6; }
  $flat=str_ireplace([' at ','(at)','[at]',' [at] ','&#64;'],'@',$html);
  $flat=str_ireplace([' dot ','(dot)','[dot]'],'.',$flat);
  if(preg_match_all('#[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,24}#i',$flat,$m2)) foreach($m2[0] as $e){
    $e=strtolower($e); if(filter_var($e,FILTER_VALIDATE_EMAIL)) $scores[$e]=($scores[$e]??0)+1; }
}

/* Rank harvested candidates: own-domain + generic mailbox wins; role/junk addresses lose. */
function vestra_best_email(array $scores, string $domain): string {
  if(!$scores) return '';
  $junk='#(example\.|@example|sentry|wixpress|@2x|godaddy|yourdomain|@sentry|\.png|\.jpg|\.jpeg|\.gif|\.webp|\.svg|domain\.com$|email\.com$|test@|@test\.)#i';
  $generic=['info','contact','kontakt','sales','hello','office','mail','enquiries','enquiry','shop','service','support','hallo','bonjour','contatti','ventas','team','commercial','wholesale'];
  $best=''; $bestScore=-999;
  foreach($scores as $e=>$sig){
    if(preg_match($junk,$e)) continue;
    [$lp,$dp]=array_pad(explode('@',$e,2),2,'');
    $s=$sig;
    if($dp===$domain || str_ends_with($dp,'.'.$domain)) $s+=12;                       // on the company's own domain
    elseif(preg_match('#(gmail|yahoo|hotmail|outlook|gmx|web\.de|icloud|aol|orange\.fr|libero\.it)\.#',$dp)) $s+=2; // still a real mailbox
    if(in_array($lp,$generic,true)) $s+=7;
    if(in_array($lp,['noreply','no-reply','postmaster','webmaster','abuse','privacy','gdpr','dpo','hostmaster','sentry'],true)) $s-=12;
    if($s>$bestScore){ $bestScore=$s; $best=$e; }
  }
  return $best;
}

/* Free, key-less email finder: fetch the company's OWN contact / imprint pages and pull a
 * published business address. EU/UK shops must list a reachable email in their Impressum /
 * mentions-légales / contact page, so hit-rate is high across the target market. Returns the
 * best generic contact mailbox, or '' if none found / unreachable. No API, no key, no guessing. */
function vestra_scrape_email(string $website): string {
  $domain=vestra_domain_of($website); if($domain==='') return '';
  $base=''; $home='';
  foreach(['https://'.$domain,'https://www.'.$domain,'http://'.$domain] as $b){
    $h=vestra_http_get($b.'/',8); if($h!==''){ $base=$b; $home=$h; break; }
  }
  if($base==='') return '';
  $scores=[]; vestra_harvest_emails($home,$scores);
  $paths=['/contact','/contact-us','/kontakt','/impressum','/imprint','/about','/about-us',
          '/legal','/mentions-legales','/pages/contact','/en/contact','/contatti','/contacto'];
  $req=0;
  foreach($paths as $p){
    if($req>=7) break;                 // hard cap on requests per site
    if($req>=3 && $scores) break;      // homepage + a few pages is enough once we have hits
    $html=vestra_http_get($base.$p,9); $req++;
    if($html!=='') vestra_harvest_emails($html,$scores);
  }
  return vestra_best_email($scores,$domain);
}

/* Look up a business email for a company website. Cascade:
 *   1) email-finder API (Hunter.io / Anymailfinder) IF a key is configured — verified addresses;
 *   2) free fallback — read the site's own contact/imprint pages (no key, works out of the box).
 * Config (global email_settings or per-seller override): finder_provider, finder_key.
 * Returns the best real, published/verified email, or '' if none found. Never an LLM guess. */
function vestra_find_email(string $website, string $keyOverride='', string $providerOverride=''): string {
  $domain=vestra_domain_of($website);
  if($domain==='') return '';
  $key=$keyOverride!==''?$keyOverride:(string)vestra_cfg('finder_key','');
  $provider=strtolower($providerOverride!==''?$providerOverride:(string)vestra_cfg('finder_provider','hunter'));
  // 1) API finder — only when a key exists.
  if($key!==''){
    $api='';
    if($provider==='anymailfinder'){
      $ch=curl_init('https://api.anymailfinder.com/v5.0/search/company.json');
      curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>25,
        CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json'],
        CURLOPT_POSTFIELDS=>json_encode(['domain'=>$domain])]);
      $r=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
      if($code>=200&&$code<300){ $d=json_decode((string)$r,true); $e=$d['email']??($d['results'][0]['email']??'');
        if(is_string($e)&&filter_var($e,FILTER_VALIDATE_EMAIL)) $api=strtolower($e); }
      elseif($code) error_log("[VESTRA finder] anymailfinder HTTP {$code}");
    } else {
      // Hunter.io domain-search (default)
      $ch=curl_init('https://api.hunter.io/v2/domain-search?domain='.urlencode($domain).'&api_key='.urlencode($key));
      curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>25]);
      $r=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
      if($code>=200&&$code<300){
        $d=json_decode((string)$r,true); $emails=$d['data']['emails']??[];
        // rank: prefer generic (info@/sales@) then higher confidence
        usort($emails,fn($a,$b)=>((($b['type']??'')==='generic'?100:0)+($b['confidence']??0))<=>((($a['type']??'')==='generic'?100:0)+($a['confidence']??0)));
        foreach($emails as $e){ if(!empty($e['value'])&&filter_var($e['value'],FILTER_VALIDATE_EMAIL)){ $api=strtolower($e['value']); break; } }
      } elseif($code){ error_log("[VESTRA finder] hunter HTTP {$code}"); }
    }
    if($api!=='') return $api;
  }
  // 2) Free fallback — read the company's own site. Works with NO key.
  return vestra_scrape_email($domain);
}

/* Discover real small/medium clothing & textile retailers from OpenStreetMap (free, no key).
 * OSM is full of independent boutiques — exactly the target (multi-brand stores, not big chains)
 * — and many list website / email / phone directly. Returns rows shaped for vestra_leads_add():
 * ['company','website','email','phone','country','city','address','category','source']. */
/* Whether the most recent vestra_overpass() call reached a working mirror. False means
 * every mirror failed transport-wide (not "genuinely zero shops") — callers that want to
 * tell "OSM is down today" apart from "this area really has none" check this right after
 * vestra_discover_osm(). Pass a bool to set it (internal), call with no args to read it. */
function vestra_osm_ok(?bool $set = null): bool {
  static $ok = true;
  if ($set !== null) $ok = $set;
  return $ok;
}
function vestra_overpass(string $ql): string {
  // Free public Overpass mirrors are prone to transient 502/503/504 under load, especially
  // for whole-country queries — four independent instances plus one same-mirror retry on a
  // 5xx (a momentary spike often clears within a couple of seconds) makes a total outage
  // much less likely than the original two-mirror, no-retry version.
  $mirrors = [
    'https://overpass-api.de/api/interpreter',
    'https://overpass.kumi.systems/api/interpreter',
    'https://overpass.osm.ch/api/interpreter',
    'https://overpass.private.coffee/api/interpreter',
  ];
  $lastEmpty = null; // a well-formed 2xx response whose "elements" came back empty
  foreach($mirrors as $ep){
    for($attempt=1; $attempt<=2; $attempt++){
      $ch=curl_init($ep);
      curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>65,CURLOPT_CONNECTTIMEOUT=>10,
        CURLOPT_USERAGENT=>'VestraBot/1.0 (+https://vestrasales.com)',
        CURLOPT_POSTFIELDS=>'data='.urlencode($ql)]);
      $r=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
      // Overpass often answers a server-side timeout with HTTP 200 and an empty result set —
      // the real error is buried in a "remark" field, not the status code (confirmed empirically:
      // {"elements":[],"remark":"runtime error: Query timed out in \"query\" ..."}). Treating that
      // as success would silently report "0 found" for what's actually a failed lookup.
      $softTimeout = is_string($r) && $r!=='' && stripos($r,'"remark"')!==false
        && (stripos($r,'timed out')!==false || stripos($r,'timeout')!==false);
      if($code>=200&&$code<300 && is_string($r) && $r!=='' && !$softTimeout){
        // Mirrors also disagree on completeness: a mirror can answer cleanly (no error at
        // all) with an empty "elements" array simply because ITS copy of OSM's derived area
        // index doesn't have the place we asked about (confirmed empirically: a Berlin
        // clothing-shop search came back as valid, remark-free, genuinely empty JSON). Don't
        // trust the first mirror's "found nothing" — remember it and let other mirrors, which
        // may have a more complete area index, get a real shot before giving up.
        $d=json_decode($r,true);
        $empty = is_array($d) && array_key_exists('elements',$d) && count($d['elements'])===0;
        vestra_osm_ok(true);
        if(!$empty) return $r;
        $lastEmpty=$r;
        break; // no point retrying the same mirror on a legitimately-empty answer — try the next one
      }
      if($code) error_log("[VESTRA osm] {$ep} HTTP {$code}".($softTimeout?' (Overpass ic zaman asimi remark)':'')." (deneme {$attempt})");
      if($attempt===1 && (in_array($code,[502,503,504],true) || $softTimeout)){ sleep(2); continue; } // transient — retry same mirror once
      break;
    }
  }
  if($lastEmpty!==null) return $lastEmpty; // every mirror that answered agreed: genuinely nothing found
  vestra_osm_ok(false);
  return '';
}
/* Names/brands to exclude from Discovery: mass-market chains (Zara, H&M, Primark…) and
 * monobrand flagship stores of the very designer labels VESTRA sells (Lacoste, Ralph Lauren,
 * DSQUARED2, D&G…) — neither is a wholesale customer: chains don't buy small lots from a
 * marketplace, and a brand's own store doesn't stock competitors. Best-effort substring
 * match on the shop's name/brand tag; admin can still remove any that slip through. */
function vestra_discover_blocklist(): array {
  return [
    // mass-market / fast-fashion chains
    'zara','h&m','h & m','c&a','c & a','primark','mango','uniqlo','bershka','pull&bear','pull & bear',
    'stradivarius','new yorker','takko','kik','nkd',"ernsting's family",'peek & cloppenburg','peek&cloppenburg',
    'esprit','s.oliver','tom tailor','jack & jones','vero moda','celio','kiabi','forever 21','gap',
    'old navy','banana republic','topshop','topman','river island','marks & spencer','m&s','jd sports',
    'foot locker','footlocker','deichmann','görtz','goertz','snipes','courir','next retail',
    // department stores
    'galeries lafayette','el corte inglés','el corte ingles','karstadt','kaufhof','john lewis','debenhams',
    "macy's",'nordstrom','harrods','selfridges','myer','david jones',
    // sportswear giants
    'nike','adidas','puma','under armour','the north face','columbia sportswear','reebok','fila','kappa',
    'umbro','asics','new balance',
    // designer/luxury monobrand flagships — VESTRA's own brands; their stores aren't buyers
    'lacoste','ralph lauren','polo ralph lauren','dsquared2','dsquared 2','dolce & gabbana','dolce&gabbana',
    'd&g','gucci','prada','emporio armani','giorgio armani','armani','versace','burberry','hugo boss',
    'tommy hilfiger','calvin klein','michael kors','louis vuitton','chanel','dior','balenciaga','fendi',
    'moncler','off-white','amiri','valentino','saint laurent','givenchy','bottega veneta',
    // online-only (defensive; shouldn't appear as physical OSM shop nodes anyway)
    'zalando','farfetch','ssense','asos','amazon',
  ];
}
/* $country is the search scope (required — matches an OSM country-level admin boundary).
 * $city is an optional narrowing filter: blank searches the whole country, set it to search
 * just that city instead (same as the old behaviour). Country-wide queries scan a much
 * bigger area so they're given a longer budget — and may still time out / come back empty
 * for very large countries on the free public Overpass server; that's an inherent trade-off
 * of not requiring a city, not a bug. */
function vestra_discover_osm(string $country, string $city='', int $limit=80): array {
  $country=trim($country); $city=trim($city);
  if($country==='') return [];
  $wide=($city==='');
  $areaEsc=preg_replace('#[\\\\"\r\n]#','',$wide?$country:$city);   // guard the Overpass QL string
  $adminFilter=$wide?'["admin_level"="2"]':'';                       // country-level boundary only when scope-wide
  $timeout=$wide?55:35;
  $shopRe='^(clothes|boutique|fashion|fashion_accessories|shoes|bag|leather|tailor|jewelry|watches)$';
  $f='["shop"~"'.$shopRe.'"]';
  // OSM tags admin boundaries with the LOCAL name in `name` (e.g. "Deutschland") and the
  // English name in a separate `name:en` tag when it differs. Matching only `name` silently
  // returns zero results for Germany/Netherlands/Italy/Spain and many non-English city names
  // (Munich/München, Rome/Roma, ...) — union both tags so the English country/city list we use
  // actually resolves everywhere.
  $ql="[out:json][timeout:{$timeout}];".
      '(area["name"~"^'.$areaEsc.'$",i]["boundary"="administrative"]'.$adminFilter.';'.
      'area["name:en"~"^'.$areaEsc.'$",i]["boundary"="administrative"]'.$adminFilter.';)->.a;'.
      "(node{$f}(area.a);way{$f}(area.a););out tags center ".max(1,min(400,$limit*4)).";";
  $body=vestra_overpass($ql);
  if($body==='') return [];
  $d=json_decode($body,true); $els=$d['elements']??[]; if(!$els) return [];
  $block=vestra_discover_blocklist();
  $out=[]; $seen=[];
  foreach($els as $el){
    $t=$el['tags']??[]; $name=trim((string)($t['name']??'')); if($name==='') continue;
    $k=strtolower($name); if(isset($seen[$k])) continue; $seen[$k]=true;
    $brandL=strtolower((string)($t['brand']??''));
    $blocked=false;
    foreach($block as $b){ if(str_contains($k,$b) || ($brandL!==''&&str_contains($brandL,$b))){ $blocked=true; break; } }
    if($blocked) continue;
    $web=(string)($t['website']??($t['contact:website']??($t['url']??'')));
    $email=(string)($t['email']??($t['contact:email']??''));
    $phone=(string)($t['phone']??($t['contact:phone']??''));
    $street=trim(((string)($t['addr:street']??'')).' '.((string)($t['addr:housenumber']??'')));
    $pc=trim(((string)($t['addr:postcode']??'')).' '.((string)($t['addr:city']??$city)));
    $out[]=[
      'company'=>$name,
      'website'=>$web,
      'email'=>(filter_var($email,FILTER_VALIDATE_EMAIL)?strtolower($email):''),
      'phone'=>$phone,
      'country'=>$country,
      'city'=>trim((string)($t['addr:city']??$city)),
      'address'=>trim($street.($pc!==''?', '.$pc:'')),
      'category'=>'Retailer ('.((string)($t['shop']??'clothes')).')',
      'source'=>'OpenStreetMap',
      '_hasweb'=>$web!=='',   // sort key only, stripped below
    ];
  }
  // Candidates with a website already listed have far better odds of yielding a real email
  // next (via the free site-reader) — fill the cap with those first.
  usort($out, fn($a,$b) => ($b['_hasweb']<=>$a['_hasweb']));
  $out=array_slice($out,0,$limit);
  foreach($out as &$r) unset($r['_hasweb']);
  unset($r);
  return $out;
}

/* AI outreach personalisation (DeepSeek by default, OpenAI-compatible). The key comes
 * from vestra_cfg('ai_key') (admin/config) or a DEEPSEEK_KEY constant if the server
 * already defines one (e.g. shared with the chat app) — never committed to git. */
function vestra_ai_key(): string {
  $k=(string)vestra_cfg('ai_key',''); if($k!=='') return $k;
  if(defined('DEEPSEEK_KEY') && constant('DEEPSEEK_KEY')) return (string)constant('DEEPSEEK_KEY');
  return '';
}
function vestra_ai_chat(array $messages, float $temp=0.6, int $max=700, string $keyOverride=''): string {
  $key=$keyOverride!==''?$keyOverride:vestra_ai_key(); if($key==='') return '';
  $url=(string)vestra_cfg('ai_url','https://api.deepseek.com/chat/completions');
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>60,CURLOPT_POST=>true,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$key],
    CURLOPT_POSTFIELDS=>json_encode(['model'=>(string)vestra_cfg('ai_model','deepseek-chat'),'messages'=>$messages,'temperature'=>$temp,'max_tokens'=>$max],JSON_UNESCAPED_UNICODE)]);
  $res=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  if($res===false||$code<200||$code>=300){ error_log("[VESTRA AI] HTTP {$code}"); return ''; }
  $d=json_decode((string)$res,true); return trim((string)($d['choices'][0]['message']['content'] ?? ''));
}
/* Personalise the outreach for one customer → [subject, body] (with the required
 * sender + unsubscribe footer appended), or null if AI is off/fails (caller falls
 * back to the template). $senderName lets a seller's offer be signed as the seller. */
function vestra_ai_personalize(array $lead, array $tpl, string $senderName='', string $keyOverride=''): ?array {
  if(($keyOverride!==''?$keyOverride:vestra_ai_key())==='') return null;
  $company=(string)($lead['company']??''); $country=(string)($lead['country']??'');
  $cat=(string)($lead['category']??''); $contact=(string)($lead['contact_name']??'');
  $sender=$senderName!==''?$senderName:'VESTRA';
  $sys="You write concise, professional B2B wholesale outreach emails for a KYC-verified marketplace selling AUTHENTIC branded fashion (Lacoste, DSQUARED2, Ralph Lauren, Dolce & Gabbana, Amiri) wholesale to small/medium multi-brand retailers. Warm but businesslike, 80-120 words, no invented facts, no unfilled placeholders. Plain text only.";
  $usr="Write a personalised wholesale outreach email from \"{$sender}\" to this retailer.\nCompany: {$company}\nContact: {$contact}\nCountry: {$country}\nSegment/notes: {$cat}\n\nReference 1-2 relevant brands, invite them to browse or request a quote at https://vestrasales.com/shop, sign as \"{$sender}\". First line 'Subject: ...', then a blank line, then the body.";
  $out=vestra_ai_chat([['role'=>'system','content'=>$sys],['role'=>'user','content'=>$usr]],0.6,700,$keyOverride);
  if($out==='') return null;
  $subject=(string)($tpl['subject']??'Wholesale offer'); $body=$out;
  if(preg_match('/^\s*Subject:\s*(.+?)\r?\n(.*)$/is',$out,$m)){ $subject=trim($m[1]); $body=trim($m[2]); }
  $map=['{{company}}'=>$company,'{{contact_name}}'=>($contact?:'there'),'{{country}}'=>$country];
  $subject=strtr($subject,$map); $body=strtr($body,$map);
  $unsubUrl='https://vestrasales.com/lead-unsubscribe?token='.urlencode((string)($lead['unsub_token']??''));
  $body.="\n\n—\n".($senderName!==''?$senderName.' via VESTRA (operated by acerasoft LLC)':'VESTRA is operated by acerasoft LLC').
         ". You're receiving this one-time business message because {$company} was identified as a potential trade partner.\nUnsubscribe: {$unsubUrl}";
  return [$subject,$body];
}

/* Turn a bare URL into a clickable link inside already-escaped HTML text. */
function vestra_html_linkify(string $escapedHtml): string {
  return preg_replace('#(https?://[^\s<]+)#','<a href="$1" style="color:#a97f2c;text-decoration:underline">$1</a>',$escapedHtml);
}

/* VESTRA's branded HTML shell for every outreach/offer/quote email — plain text stays the
 * primary content (nothing here changes what any template generates), this just wraps it in
 * a premium visual layout for the HTML half of the send. Blank lines become paragraphs;
 * everything after the "—" sender-identity/unsubscribe separator (every template appends one)
 * renders as a smaller, muted footer block so the legal text doesn't compete with the pitch.
 *
 * $opts optionally adds the structured elements that make a receipt/status email read as
 * premium rather than a plain note — all purely additive, every existing caller (which
 * passes none of these) renders exactly as before:
 *   'badge'  => short status pill under the header, e.g. "✅ Verified", "💶 New offer"
 *   'rows'   => [['label'=>'Qty','value'=>'104','strong'=>false], ...] detail card
 *   'button' => ['label'=>'View in dashboard','url'=>'https://...'] CTA button
 */
function vestra_html_email(string $bodyPlain, string $heroImage='', array $opts=[]): string {
  $parts=explode("\n\n—\n",$bodyPlain,2);
  $main=trim($parts[0]); $footer=isset($parts[1])?trim($parts[1]):'';
  $renderParas=function(string $text,string $style): string {
    $out='';
    foreach(preg_split('/\n{2,}/',trim($text)) as $p){
      $p=trim($p); if($p==='') continue;
      $esc=nl2br(htmlspecialchars($p,ENT_QUOTES,'UTF-8'));
      $out.='<p style="'.$style.'">'.vestra_html_linkify($esc).'</p>';
    }
    return $out;
  };
  $mainHtml=$renderParas($main,'margin:0 0 18px;line-height:1.65;color:#3a3428;font-size:15px');
  $footerHtml=$footer!==''?$renderParas($footer,'margin:0 0 8px;line-height:1.5;color:#8a8272;font-size:12px'):'';
  $heroHtml=$heroImage!==''
    ?'<img src="'.htmlspecialchars($heroImage,ENT_QUOTES,'UTF-8').'" alt="" width="560" style="display:block;width:100%;max-width:560px;height:auto">'
    :'';

  $badgeHtml='';
  if(!empty($opts['badge'])){
    $badgeHtml='<div style="padding:20px 28px 0"><span style="display:inline-block;background:#f4ecd8;color:#8a6d1f;'
      .'font-size:12px;font-weight:700;letter-spacing:.02em;padding:5px 12px;border-radius:20px">'
      .htmlspecialchars((string)$opts['badge'],ENT_QUOTES,'UTF-8').'</span></div>';
  }

  $rowsHtml='';
  if(!empty($opts['rows']) && is_array($opts['rows'])){
    $tr=''; $i=0;
    foreach($opts['rows'] as $row){
      $label=(string)($row['label']??''); $value=(string)($row['value']??''); $strong=!empty($row['strong']);
      $top=$i>0?'border-top:1px solid #ece6d8;':''; $i++;
      $tr.='<tr>'
        .'<td style="padding:8px 0;'.$top.'color:#8a8272;font-size:13px">'.htmlspecialchars($label,ENT_QUOTES,'UTF-8').'</td>'
        .'<td style="padding:8px 0;'.$top.'color:#3a3428;font-size:13px;text-align:right;'.($strong?'font-weight:700;font-size:15px':'').'">'.htmlspecialchars($value,ENT_QUOTES,'UTF-8').'</td>'
        .'</tr>';
    }
    $rowsHtml='<div style="margin:0 28px 18px;background:#faf8f3;border:1px solid #ece6d8;border-radius:10px;padding:2px 16px">'
      .'<table style="width:100%;border-collapse:collapse" cellpadding="0" cellspacing="0">'.$tr.'</table></div>';
  }

  $buttonHtml='';
  if(!empty($opts['button']['url'])){
    $btnLabel=(string)($opts['button']['label'] ?? 'View in VESTRA');
    $buttonHtml='<div style="padding:4px 28px 28px;text-align:center">'
      .'<a href="'.htmlspecialchars((string)$opts['button']['url'],ENT_QUOTES,'UTF-8').'" '
      .'style="display:inline-block;background:#14110c;color:#d8bd86;padding:13px 34px;border-radius:8px;'
      .'text-decoration:none;font-weight:700;font-size:14px;letter-spacing:.02em">'
      .htmlspecialchars($btnLabel,ENT_QUOTES,'UTF-8').'</a></div>';
  }

  return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
    .'<title>VESTRA</title></head>'
    .'<body style="margin:0;padding:0;background:#f4f2ee;font-family:Georgia,\'Times New Roman\',serif">'
    .'<div style="max-width:560px;margin:0 auto;padding:32px 16px">'
    .'<div style="background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e6e0d5">'
    .'<div style="background:#14110c;padding:22px 28px">'
    .'<span style="color:#d8bd86;font-size:20px;font-weight:700;letter-spacing:.02em">VESTRA</span>'
    .'<span style="color:#8a8272;font-size:12px;margin-left:6px">sales</span></div>'
    .$heroHtml
    .$badgeHtml
    .'<div style="padding:20px 28px 8px">'.$mainHtml.'</div>'
    .$rowsHtml
    .$buttonHtml
    .($footerHtml!==''?'<div style="padding:14px 28px 24px;border-top:1px solid #e6e0d5;margin-top:6px">'.$footerHtml.'</div>':'')
    .'</div>'
    .'<p style="text-align:center;color:#9b9585;font-size:11px;margin:18px 0 0">VESTRA — verified B2B wholesale marketplace</p>'
    .'</div></body></html>';
}

/* Builds a multipart/alternative body (plain text + the HTML shell above) for transports that
 * send raw MIME themselves (SMTP, PHP mail()) — HTTP APIs take the two parts separately. */
function vestra_mime_multipart(string $bodyPlain, string $boundary, string $heroImage='', array $opts=[]): string {
  $html=vestra_html_email($bodyPlain,$heroImage,$opts);
  return "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
       .$bodyPlain."\r\n\r\n"
       ."--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
       .$html."\r\n\r\n--{$boundary}--";
}

/* low-level: send one UTF-8 plain-text email. Transport priority:
 *   1) HTTP API (mail_api_key set) — sends over HTTPS/443, which shared hosts
 *      leave open even when they block outbound SMTP ports (25/465/587). Best
 *      deliverability: the provider signs with its own SPF/DKIM. RECOMMENDED.
 *   2) Authenticated SMTP (smtp_host set) — needs an outbound SMTP port open.
 *   3) Local mail() — only lands in inboxes if the domain's SPF/DKIM authorize
 *      this server's IP.
 */
function vestra_send_mail($to,$subject,$body,$replyTo='',$fromName='',$cfg=null,$heroImage='',array $opts=[]){
  if(!filter_var($to,FILTER_VALIDATE_EMAIL)) return false;
  // Explicit sender config (e.g. a seller's OWN SMTP/API) — send truly "from" them.
  if($cfg!==null){
    if(($cfg['mail_api_key']??'')!=='') return vestra_api_send($to,$subject,$body,$replyTo,$fromName,$cfg,$heroImage,$opts);
    if(($cfg['smtp_host']??'')!=='' && ($cfg['smtp_pass']??'')!=='') return vestra_smtp_send($to,$subject,$body,$replyTo,$fromName,$cfg,$heroImage,$opts);
    return false; // sender selected but their transport isn't set up
  }
  if(!vestra_cfg('mail_enabled',false)) return false;
  if(vestra_cfg('mail_api_key','')!==''){
    $ok = vestra_api_send($to,$subject,$body,$replyTo,$fromName,null,$heroImage,$opts);
    if(!$ok) error_log("[VESTRA Mail] API send failed to {$to} — subject: {$subject}");
    return $ok;
  }
  if(vestra_cfg('smtp_host','')!==''){
    $ok = vestra_smtp_send($to,$subject,$body,$replyTo,$fromName,null,$heroImage,$opts);
    if(!$ok) error_log("[VESTRA Mail] SMTP send failed to {$to} — subject: {$subject}");
    return $ok;
  }
  $from=vestra_cfg('mail_from','support@vestrasales.com');
  $dispName=$fromName!==''?$fromName:'VESTRA';
  $boundary='vestra-'.bin2hex(random_bytes(12));
  $h ="From: {$dispName} <{$from}>\r\n";
  $h.="MIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
  if($replyTo && filter_var($replyTo,FILTER_VALIDATE_EMAIL)) $h.="Reply-To: {$replyTo}\r\n";
  $subj='=?UTF-8?B?'.base64_encode($subject).'?=';
  $ok = mail($to,$subj,vestra_mime_multipart($body,$boundary,$heroImage,$opts),$h);
  if(!$ok) error_log("[VESTRA Mail] mail() returned false sending to {$to} — subject: {$subject}");
  return $ok;
}

/* Dependency-free authenticated SMTP (STARTTLS + AUTH LOGIN) — no PHPMailer/composer.
 * Config: smtp_host, smtp_port (default 587), smtp_user, smtp_pass, smtp_from, smtp_name. */
function vestra_smtp_send($to,$subject,$body,$replyTo='',$fromName='',$cfg=null,$heroImage='',array $opts=[]){
  $g=fn($k,$d)=> $cfg!==null ? ($cfg[$k]??$d) : vestra_cfg($k,$d);
  $host=$g('smtp_host',''); $port=(int)$g('smtp_port',587);
  $user=$g('smtp_user',''); $pass=$g('smtp_pass','');
  $from=$g('smtp_from','')?:$user; $name=$fromName!==''?$fromName:$g('smtp_name','VESTRA');
  if($host===''||$user===''||$pass===''||$from===''){ error_log('[VESTRA SMTP] smtp_host set but user/pass/from missing'); return false; }

  $fp=@fsockopen($host,$port,$errno,$errstr,15);
  if(!$fp){ error_log("[VESTRA SMTP] connect failed: {$errstr} ({$errno})"); return false; }
  stream_set_timeout($fp,15);

  $read=function() use ($fp){
    $data=''; while(($line=fgets($fp,515))!==false){ $data.=$line; if(isset($line[3])&&$line[3]===' ') break; } return $data;
  };
  $cmd=function($c) use ($fp,$read){ fwrite($fp,$c."\r\n"); return $read(); };

  $read(); // greeting
  $cmd('EHLO vestrasales.com');
  $r=$cmd('STARTTLS');
  if(strpos($r,'220')===false || !@stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)){
    fclose($fp); error_log('[VESTRA SMTP] STARTTLS failed'); return false;
  }
  $cmd('EHLO vestrasales.com');
  $cmd('AUTH LOGIN');
  $cmd(base64_encode($user));
  $r=$cmd(base64_encode($pass));
  if(strpos($r,'235')===false){ fclose($fp); error_log('[VESTRA SMTP] AUTH LOGIN rejected — check smtp_user/smtp_pass'); return false; }

  $cmd('MAIL FROM:<'.$from.'>');
  $r=$cmd('RCPT TO:<'.$to.'>');
  if(strpos($r,'250')===false){ fclose($fp); error_log("[VESTRA SMTP] RCPT TO rejected: {$to}"); return false; }
  $cmd('DATA');

  $boundary='vestra-'.bin2hex(random_bytes(12));
  $h ="From: {$name} <{$from}>\r\nTo: <{$to}>\r\n";
  $h.='Subject: =?UTF-8?B?'.base64_encode($subject)."?=\r\n";
  $h.="MIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
  if($replyTo && filter_var($replyTo,FILTER_VALIDATE_EMAIL)) $h.="Reply-To: {$replyTo}\r\n";
  $mime=vestra_mime_multipart($body,$boundary,$heroImage,$opts);
  $escapedMime=preg_replace('/^\./m','..',$mime); // SMTP dot-stuffing
  $r=$cmd($h."\r\n".$escapedMime."\r\n.");
  $cmd('QUIT');
  fclose($fp);
  return strpos($r,'250')!==false;
}

/* Send via a transactional-email HTTP API (over HTTPS/443, no SMTP port needed).
 * Config: mail_api_provider ('brevo' default | 'resend'), mail_api_key,
 *         mail_from (verified sender address), smtp_name (display name).
 * Returns true on a 2xx from the provider. */
function vestra_api_send($to,$subject,$body,$replyTo='',$fromName='',$cfg=null,$heroImage='',array $opts=[]){
  $g=fn($k,$d)=> $cfg!==null ? ($cfg[$k]??$d) : vestra_cfg($k,$d);
  $provider=strtolower((string)$g('mail_api_provider','brevo'));
  $key=(string)$g('mail_api_key','');
  $from=(string)$g('mail_from','support@vestrasales.com');
  $name=$fromName!==''?$fromName:(string)$g('smtp_name','VESTRA');
  if($key===''||$from===''){ error_log('[VESTRA API] mail_api_key or mail_from missing'); return false; }

  if($provider==='resend'){
    $url='https://api.resend.com/emails';
    $headers=['Authorization: Bearer '.$key,'Content-Type: application/json'];
    $payload=['from'=>"{$name} <{$from}>",'to'=>[$to],'subject'=>$subject,'text'=>$body,'html'=>vestra_html_email($body,$heroImage,$opts)];
    if($replyTo && filter_var($replyTo,FILTER_VALIDATE_EMAIL)) $payload['reply_to']=$replyTo;
  } else { // brevo (default)
    $url='https://api.brevo.com/v3/smtp/email';
    $headers=['api-key: '.$key,'Content-Type: application/json','Accept: application/json'];
    $payload=[
      'sender'=>['name'=>$name,'email'=>$from],
      'to'=>[['email'=>$to]],
      'subject'=>$subject,
      'textContent'=>$body,
      'htmlContent'=>vestra_html_email($body,$heroImage,$opts),
    ];
    if($replyTo && filter_var($replyTo,FILTER_VALIDATE_EMAIL)) $payload['replyTo']=['email'=>$replyTo];
  }

  $ch=curl_init($url);
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_POST=>true,
    CURLOPT_HTTPHEADER=>$headers,
    CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT=>20,
  ]);
  $resp=curl_exec($ch);
  if($resp===false){ error_log('[VESTRA API] curl error: '.curl_error($ch)); curl_close($ch); return false; }
  $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  if($code>=200 && $code<300) return true;
  error_log("[VESTRA API] {$provider} HTTP {$code}: ".substr((string)$resp,0,300));
  return false;
}

/* notify the operator address(es) configured in inc/config.php */
function vestra_notify($subject,$body,$replyTo=''){
  $list=vestra_cfg('notify',[]); if(is_string($list)) $list=[$list];
  $ok=false; foreach((array)$list as $to){ if(vestra_send_mail($to,$subject,$body,$replyTo)) $ok=true; }
  return $ok;
}

/* localized email-verification email → [subject, body, opts]. $opts carries a CTA button
 * (the raw URL still appears in the plain-text body too, for text-only clients). */
function vestra_verify_text($lang, $name, $token) {
  $url = 'https://vestrasales.com/verify?token='.$token;
  $btnLabel = ['en'=>'Verify email address','de'=>'E-Mail-Adresse bestätigen','fr'=>'Vérifier mon adresse e-mail',
    'it'=>'Verifica indirizzo e-mail','es'=>'Verificar dirección de correo'][$lang] ?? 'Verify email address';
  $T = [
   'en' => [
     'VESTRA — please verify your email address',
     "Hello %s,\n\nThank you for registering on VESTRA. Please verify your email address by clicking the link below:\n\n%s\n\nThis link is valid for 72 hours. If you did not register, you can safely ignore this email.\n\n— VESTRA · vestrasales.com",
   ],
   'de' => [
     'VESTRA — bitte bestätigen Sie Ihre E-Mail-Adresse',
     "Hallo %s,\n\nVielen Dank für Ihre Registrierung bei VESTRA. Bitte bestätigen Sie Ihre E-Mail-Adresse über den folgenden Link:\n\n%s\n\nDieser Link ist 72 Stunden gültig. Falls Sie sich nicht registriert haben, können Sie diese E-Mail ignorieren.\n\n— VESTRA · vestrasales.com",
   ],
   'fr' => [
     'VESTRA — veuillez vérifier votre adresse e-mail',
     "Bonjour %s,\n\nMerci de vous être inscrit sur VESTRA. Veuillez vérifier votre adresse e-mail en cliquant sur le lien ci-dessous :\n\n%s\n\nCe lien est valide 72 heures. Si vous n'êtes pas à l'origine de cette inscription, ignorez simplement cet e-mail.\n\n— VESTRA · vestrasales.com",
   ],
   'it' => [
     'VESTRA — verifica il tuo indirizzo e-mail',
     "Ciao %s,\n\nGrazie per esserti registrato su VESTRA. Verifica il tuo indirizzo e-mail cliccando sul link qui sotto:\n\n%s\n\nQuesto link è valido per 72 ore. Se non ti sei registrato, puoi ignorare questa e-mail.\n\n— VESTRA · vestrasales.com",
   ],
   'es' => [
     'VESTRA — por favor verifica tu dirección de correo',
     "Hola %s,\n\nGracias por registrarte en VESTRA. Por favor verifica tu dirección de correo electrónico haciendo clic en el enlace de abajo:\n\n%s\n\nEste enlace caduca en 72 horas. Si no te registraste, puedes ignorar este correo.\n\n— VESTRA · vestrasales.com",
   ],
  ];
  $t = $T[$lang] ?? $T['en'];
  return [$t[0], sprintf($t[1], $name, $url), ['button'=>['label'=>$btnLabel,'url'=>$url]]];
}

/* localized welcome email after registration → [subject, body, opts]. */
function vestra_ack_text($lang,$name,$type){
  $roleWord = ['en'=>$type==='seller'?'seller':'buyer','de'=>$type==='seller'?'Verkäufer':'Käufer',
    'fr'=>$type==='seller'?'vendeur':'acheteur','it'=>$type==='seller'?'venditore':'acquirente',
    'es'=>$type==='seller'?'vendedor':'comprador'][$lang] ?? ($type==='seller'?'seller':'buyer');
  $url  = $type==='seller' ? 'https://vestrasales.com/seller?tab=kyc' : 'https://vestrasales.com/buyer?tab=kyc';
  $btnLabel = ['en'=>'Upload documents','de'=>'Dokumente hochladen','fr'=>'Téléverser les documents',
    'it'=>'Carica documenti','es'=>'Subir documentos'][$lang] ?? 'Upload documents';
  $T=[
   'en'=>[
     'Welcome to VESTRA — account created',
     "Hello %s,\n\nYour VESTRA account has been created as a verified %s.\n\nNext step: please upload your verification documents at:\n%s\n\nOur team will review them and activate your account. Thank you for joining!\n\n— VESTRA · vestrasales.com",
   ],
   'de'=>[
     'Willkommen bei VESTRA — Konto erstellt',
     "Hallo %s,\n\nIhr VESTRA-Konto als %s wurde erfolgreich erstellt.\n\nNächster Schritt: Bitte laden Sie Ihre Verifizierungsdokumente hoch:\n%s\n\nUnser Team prüft diese und aktiviert Ihr Konto. Vielen Dank!\n\n— VESTRA · vestrasales.com",
   ],
   'fr'=>[
     'Bienvenue sur VESTRA — compte créé',
     "Bonjour %s,\n\nVotre compte VESTRA en tant que %s a été créé avec succès.\n\nProchaine étape : veuillez télécharger vos documents de vérification :\n%s\n\nNotre équipe les examinera et activera votre compte. Merci de nous rejoindre !\n\n— VESTRA · vestrasales.com",
   ],
   'it'=>[
     'Benvenuto su VESTRA — account creato',
     "Ciao %s,\n\nIl tuo account VESTRA come %s è stato creato con successo.\n\nProssimo passo: carica i tuoi documenti di verifica:\n%s\n\nIl nostro team li esaminerà e attiverà il tuo account. Grazie!\n\n— VESTRA · vestrasales.com",
   ],
   'es'=>[
     'Bienvenido a VESTRA — cuenta creada',
     "Hola %s,\n\nTu cuenta VESTRA como %s ha sido creada con éxito.\n\nSiguiente paso: sube tus documentos de verificación:\n%s\n\nNuestro equipo los revisará y activará tu cuenta. ¡Gracias!\n\n— VESTRA · vestrasales.com",
   ],
  ];
  $t = $T[$lang] ?? $T['en'];
  return [$t[0], sprintf($t[1], $name, $roleWord, $url), ['button'=>['label'=>$btnLabel,'url'=>$url]]];
}

/* localized password-reset email → [subject, body, opts]. Called from forgot.php's own
 * request (the account holder typing their own email), so $lang is that request's vlang()
 * — unlike offer/message/membership mail, which fires from someone ELSE's request and must
 * use the recipient's stored vestra_user_lang() instead. */
function vestra_reset_text($lang, $name, $link) {
  $btnLabel = ['en'=>'Set new password','de'=>'Neues Passwort festlegen','fr'=>'Définir un nouveau mot de passe',
    'it'=>'Imposta nuova password','es'=>'Establecer nueva contraseña'][$lang] ?? 'Set new password';
  $badge = ['en'=>'🔑 Password reset','de'=>'🔑 Passwort zurücksetzen','fr'=>'🔑 Réinitialisation du mot de passe',
    'it'=>'🔑 Reimposta password','es'=>'🔑 Restablecer contraseña'][$lang] ?? '🔑 Password reset';
  $T = [
   'en' => ["VESTRA — reset your password",
     "Hello %s,\n\nSomeone (hopefully you) requested a password reset for your VESTRA account.\n\nSet a new password using the button below (link valid for 1 hour):\n%s\n\nIf you didn't request this, you can ignore this email — your password stays unchanged.\n\n— VESTRA · vestrasales.com"],
   'de' => ["VESTRA — Passwort zurücksetzen",
     "Hallo %s,\n\njemand (hoffentlich Sie) hat das Zurücksetzen des Passworts für Ihr VESTRA-Konto angefordert.\n\nLegen Sie über den Button unten ein neues Passwort fest (Link 1 Stunde gültig):\n%s\n\nFalls Sie das nicht angefordert haben, ignorieren Sie diese E-Mail — Ihr Passwort bleibt unverändert.\n\n— VESTRA · vestrasales.com"],
   'fr' => ["VESTRA — réinitialiser votre mot de passe",
     "Bonjour %s,\n\nQuelqu'un (vous, espérons-le) a demandé la réinitialisation du mot de passe de votre compte VESTRA.\n\nDéfinissez un nouveau mot de passe via le bouton ci-dessous (lien valable 1 heure) :\n%s\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail — votre mot de passe reste inchangé.\n\n— VESTRA · vestrasales.com"],
   'it' => ["VESTRA — reimposta la password",
     "Ciao %s,\n\nqualcuno (speriamo tu) ha richiesto la reimpostazione della password del tuo account VESTRA.\n\nImposta una nuova password tramite il pulsante qui sotto (link valido 1 ora):\n%s\n\nSe non sei stato tu, ignora questa e-mail — la tua password resta invariata.\n\n— VESTRA · vestrasales.com"],
   'es' => ["VESTRA — restablecer tu contraseña",
     "Hola %s,\n\nalguien (esperamos que tú) ha solicitado restablecer la contraseña de tu cuenta VESTRA.\n\nEstablece una nueva contraseña con el botón de abajo (enlace válido 1 hora):\n%s\n\nSi no lo has solicitado, ignora este correo — tu contraseña no cambia.\n\n— VESTRA · vestrasales.com"],
  ];
  $t = $T[$lang] ?? $T['en'];
  return [$t[0], sprintf($t[1], $name, $link), ['badge'=>$badge, 'button'=>['label'=>$btnLabel,'url'=>$link]]];
}
