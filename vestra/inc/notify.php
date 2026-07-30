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

/* Is this a scraped artefact / placeholder / unreachable role mailbox rather than a real
 * business inbox? filter_var() is not enough on its own: "--@keydown.escape" (a JS event
 * name), "defaultvendors@layout.theme.js" (a theme asset path) and "your@email.address"
 * are all syntactically valid yet undeliverable, and mailing them costs real sender
 * reputation. Used BOTH when harvesting (below) and again on the SEND path as a safety net,
 * so an address that entered the list before a pattern was known is still never mailed —
 * same belt-and-braces approach as vestra_name_is_blocked(). */
function vestra_email_is_junk(string $email): bool {
  $e=strtolower(trim($email));
  if($e==='' || !filter_var($e,FILTER_VALIDATE_EMAIL)) return true;
  [$lp,$dp]=array_pad(explode('@',$e,2),2,'');
  if(!preg_match('/[a-z0-9]/',$lp)) return true;   // local part with no letters/digits at all ("--@…")
  // Placeholder LOCAL parts on an otherwise ordinary domain ("example@mail.com" was scraped from
  // a boilerplate contact form). The domain-side patterns below can't see these.
  // NB: 'mail'/'info'/'contact' are deliberately NOT here — they're real generic mailboxes
  // (vestra_best_email even scores them positively) and several live leads use mail@.
  if(in_array($lp,['example','sample','demo','tests','yourname','your-name','youremail','your-email','username'],true)) return true;
  // Role mailboxes that reach no human — a reply is impossible, so outreach is pointless.
  if(in_array($lp,['noreply','no-reply','donotreply','do-not-reply','postmaster','webmaster',
                   'abuse','privacy','gdpr','dpo','hostmaster','sentry','mailer-daemon'],true)) return true;
  // "example" placeholder text in the site's own contact-form boilerplate ("your-email@example.com")
  // gets scraped as if it were a real address — cross-market fix, not just English: voorbeeld=NL,
  // beispiel=DE, exemple=FR, esempio=IT, ejemplo=ES all mean "example"; xxx@xxx/name@domain/
  // user@domain/email@email/abc@abc are generic instructional placeholders in any language.
  // The tail of the pattern catches front-end scraping artefacts: JS event names, theme/asset
  // paths and screenshot filenames that a page's markup can leave behind.
  $junk='#(example\.|@example|sentry|wixpress|@2x|godaddy|yourdomain|@sentry|\.png|\.jpg|\.jpeg|\.gif|\.webp|\.svg'
       .'|domain\.com$|email\.com$|email\.address$|test@|@test\.|voorbeeld|beispiel|exemple\.|esempio|ejemplo'
       .'|xxx@xxx|xxx\.xxx|your-email|youremail|your@email|email@email|name@domain|user@domain|abc@abc'
       .'|screenshot|keydown|keyup|onclick|javascript|defaultvendors|theme\.js|\.js$|\.escape$'
       // Website-builder / hosting platforms: a shop built on one of these leaves the PLATFORM's
       // own support address in the page footer ("powered by …"), which then gets scraped as if it
       // were the shop's. Only the platform's own domain is blocked — a shop whose mailbox merely
       // happens to be hosted there still has its own domain and is unaffected.
       .'|@webador\.|@wix\.com|@squarespace\.|@shopify\.|@jimdo\.|@webnode\.|@weebly\.|@strikingly\.'
       .'|@site123\.|@ionos\.|@one\.com|@hostpoint\.|@infomaniak\.|@web\.com)#i';
  return (bool)preg_match($junk,$e);
}

/* Rank harvested candidates: own-domain + generic mailbox wins; role/junk addresses lose. */
function vestra_best_email(array $scores, string $domain): string {
  if(!$scores) return '';
  $generic=['info','contact','kontakt','sales','hello','office','mail','enquiries','enquiry','shop','service','support','hallo','bonjour','contatti','ventas','team','commercial','wholesale'];
  $best=''; $bestScore=-999;
  foreach($scores as $e=>$sig){
    if(vestra_email_is_junk((string)$e)) continue;
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

/* Premium / designer labels a multi-brand fashion boutique would carry. A shop's own website
 * MENTIONING these (in its nav, a /brands page, product listings) is the strongest cheap signal
 * that it stocks premium fashion — exactly the tier VESTRA wholesales, so exactly the customer
 * we want. Note this is the OPPOSITE use of the discovery blocklist: there a brand name in the
 * shop's NAME means "monobrand flagship, skip"; here a brand name in the shop's CONTENT means
 * "multi-brand boutique that carries it, target". Names overlap on purpose. */
function vestra_premium_brandlist(): array {
  return [
    // VESTRA's own catalogue tier
    'lacoste','dsquared','ralph lauren','polo ralph','dolce & gabbana','dolce&gabbana','amiri',
    // Italian luxury / premium
    'gucci','prada','miu miu','versace','emporio armani','giorgio armani','fendi','valentino',
    'moschino','missoni','etro','marni','brunello cucinelli','loro piana','kiton','ermenegildo zegna','zegna',
    'stone island','c.p. company','cp company','moncler','herno','woolrich','parajumpers','paul & shark',
    'jacob cohen','dondup','peuterey','save the duck','liu jo','pinko','twinset','patrizia pepe','elisabetta franchi',
    // French luxury / premium
    'saint laurent','givenchy','balmain','kenzo','celine','céline','chloé','chloe','isabel marant',
    'ami paris','maison margiela','margiela','jacquemus','courrèges','courreges','sandro','maje',
    // Other luxury / premium-contemporary / premium-streetwear
    'burberry','hugo boss','tommy hilfiger','calvin klein','michael kors','off-white','palm angels',
    'canada goose','golden goose','common projects','autry','philipp plein','balenciaga','bottega veneta',
    'alexander mcqueen','stella mccartney','acne studios','napapijri','aspesi','k-way',
    // Deliberately NOT listed — too ambiguous as bare substrings even with word boundaries:
    //   'colmar' (a French city), 'blauer' (German "bluer"), 'off white' (a plain colour name).
  ];
}
/* Scan a boutique's own website for premium-brand mentions. Returns the matched premium
 * brands (deduped, capped) — empty means none detected / site unreachable. Reuses the same
 * fetch helpers and early-exit discipline as the email scraper; brand/designer index pages
 * (/brands, /marche, /marken …) carry the richest signal so they're checked after the home. */
function vestra_premium_brands(string $website): array {
  $domain=vestra_domain_of($website); if($domain==='') return [];
  $base=''; $home='';
  foreach(['https://'.$domain,'https://www.'.$domain,'http://'.$domain] as $b){
    $h=vestra_http_get($b.'/',8); if($h!==''){ $base=$b; $home=$h; break; }
  }
  if($base==='') return [];
  $brands=vestra_premium_brandlist(); $matches=[];
  // Whole-token match, NOT bare substring: a brand name must not be preceded/followed by a
  // letter. Without this, short names hit inside ordinary words — "etro" matched "rETRO" and
  // "mETRO", flooding the list with false positives. Unicode-aware (\p{L}, /u) so accented
  // brands (Chloé, Courrèges) and accented surrounding text both behave.
  $scan=function(string $html) use ($brands,&$matches){
    $t=strtolower($html);
    foreach($brands as $b){
      if($b==='' || isset($matches[$b])) continue;
      if(preg_match('/(?<!\p{L})'.preg_quote($b,'/').'(?!\p{L})/iu',$t)) $matches[$b]=true;
    }
  };
  $scan($home);
  $paths=['/brands','/brand','/designers','/marche','/marchi','/marken','/marcas','/marques','/collections','/shop'];
  $req=0;
  foreach($paths as $p){
    if($req>=4) break;                             // hard cap on requests per site
    if($req>=2 && count($matches)>=2) break;       // home + a couple of pages is enough once we have hits
    $html=vestra_http_get($base.$p,8); $req++;
    if($html!=='') $scan($html);
  }
  return array_slice(array_keys($matches),0,12);
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
    'max mara','harry winston','paul smith','ray-ban','rayban',
    // fast-fashion group sister brands (same parent as zara/h&m above, easy to miss)
    'massimo dutti','springfield','& other stories',
    // footwear/eyewear giants (same tier as the sportswear giants above)
    'birkenstock','geox','skechers',
    // big supermarket/department-store chains
    'monoprix',
    // smaller specialty/designer brands' OWN stores — same logic as the flagships above
    // (a multi-brand boutique that also stocks these is still a fine lead; only their
    // single-brand outlets are excluded here)
    'repetto','polène','polene','de fursac','armor lux','jacadi','princesse tam','tamaris',
    'veja','thomas sabo','boggi milano','free people','dinh van',
    // Swiss single-brand stores / large chains found in Zurich+Geneva+Basel discovery
    'freitag','breguet',"arc'teryx",'arcteryx','qwstion','christ uhren','bucherer',
    // charity / thrift chains — they receive donations, they don't buy wholesale
    'caritas','heilsarmee','salvation army','emmaus','emmaüs',
    'rotkreuz','rotes kreuz','red cross','croix-rouge','oxfam','goodwill',
    'petits riens','spullenhulp','kringloop','rode kruis',
    // single-brand houses / own-label chains found in the Brussels + Antwerp discovery
    'chopard','delvaux','frey wille','freywille','scabal','twinset','lanieri','gemmyo',
    'comptoir des cotonniers',
    // designer own-brand stores + large BE/NL retail chains (Antwerp/Rotterdam discovery)
    'dries van noten','philipp plein','phillip plein','stone island','bensimon',
    'torfs','lucardi','state of art','costes','juttu','the society shop','mayerline',
    'cavallaro','yaya','mascolori','schoenen slaets','buffalini','pedico','modemakers',
    // online-only (defensive; shouldn't appear as physical OSM shop nodes anyway)
    'zalando','farfetch','ssense','asos','amazon',
  ];
}
/* True when a company/brand name matches a big-chain / monobrand entry on the discovery
 * blocklist. Discovery already skips these when ADDING a lead, but this lets the SEND path
 * apply the exact same rule again as a safety net — so a big store that was imported by
 * hand (CSV / "Add prospect") or added before a name joined the blocklist is never actually
 * emailed an offer. Same substring rule as discovery, so behaviour is identical everywhere. */
function vestra_name_is_blocked(string $company, string $brand=''): bool {
  $k=strtolower(trim($company)); $b2=strtolower(trim($brand));
  if($k==='' && $b2==='') return false;
  foreach(vestra_discover_blocklist() as $b){
    if(($k!=='' && str_contains($k,$b)) || ($b2!=='' && str_contains($b2,$b))) return true;
  }
  return false;
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
  // Without an admin_level filter, matching a city name has to regex-scan EVERY
  // boundary=administrative area on the planet (countries, provinces, wards, hamlets —
  // there are far more of these worldwide than the ~200 country-level ones), which is
  // slow enough to time out on the free public mirrors even for well-known cities
  // (confirmed empirically: a plain Berlin/Istanbul name lookup can time out at 35-40s).
  // Real cities are tagged admin_level 3-10 in virtually every country's OSM convention
  // (4 for city-states like Berlin, 6-8 for most cities/communes/comuni/municipios) —
  // restricting to that range shrinks the scan the same way admin_level=2 already does
  // for country-wide search, without excluding any plausible city match.
  $adminFilter=$wide?'["admin_level"="2"]':'["admin_level"~"^([3-9]|10)$"]';
  $timeout=$wide?55:45;
  // Apparel trades only. jewelry/watches/tailor were harvested at first and turned out to be
  // the wrong audience for a designer-CLOTHING wholesale offer: a jeweller or a watch dealer
  // buys neither, and a tailor makes garments instead of reselling them (they were already
  // excluded from sends by hand). Dropping them at the source keeps the list on-target and
  // spends the email-lookup budget on shops that can actually order.
  $shopRe='^(clothes|boutique|fashion|fashion_accessories|shoes|bag|leather)$';
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
    // A `brand` tag is OSM's own convention for "this location represents ONE company"
    // (every chain/franchise location gets one) — genuine independent multi-brand
    // boutiques don't carry it, since there's no single brand to name. VESTRA wants
    // multi-brand retailers (they're the ones sourcing from several wholesalers, not
    // a single label's own outlet), so skip anything brand-tagged regardless of whether
    // that specific brand is on the static blocklist below — catches monobrand stores
    // the fixed name list was never going to enumerate.
    if($brandL!=='') continue;
    $blocked=false;
    foreach($block as $b){ if(str_contains($k,$b)){ $blocked=true; break; } }
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
/* Email-safe stylised brand wordmark for the campaign "house wall". External images and inline
 * SVG get stripped by Gmail/Outlook, so these are pure inline-CSS typographic wordmarks (like the
 * site's own SVG logos) — they always render, in any client, with no image-loading prompt. They
 * are stylised text, not copyrighted logo artwork. */
function vestra_email_brand_logo_html(string $brand): string {
  $b = strtolower(trim($brand));
  $ink = '#f4ecd8';
  $ss  = "'Helvetica Neue',Helvetica,Arial,sans-serif";
  $sf  = "Georgia,'Times New Roman',serif";
  if ($b === 'lacoste')
    return '<span style="font-family:'.$ss.';color:'.$ink.';font-size:18px;font-weight:700;letter-spacing:.26em">LACOSTE</span>';
  if (str_contains($b,'dsquared'))
    return '<span style="font-family:'.$sf.';color:'.$ink.';font-size:18px;font-weight:900;letter-spacing:.04em">DSQUARED<sup style="font-size:11px;font-weight:900">2</sup></span>';
  if (str_contains($b,'ralph'))
    return '<span style="font-family:'.$sf.';color:'.$ink.';font-size:15px;font-weight:400;letter-spacing:.20em">RALPH&nbsp;LAUREN</span>';
  if (str_contains($b,'dolce') || str_contains($b,'gabbana'))
    return '<span style="font-family:'.$sf.';color:'.$ink.';font-size:14px;font-weight:400;letter-spacing:.10em">DOLCE&nbsp;&amp;&nbsp;GABBANA</span>';
  if ($b === 'amiri')
    return '<span style="font-family:'.$ss.';color:'.$ink.';font-size:18px;font-weight:700;letter-spacing:.34em">AMIRI</span>';
  if (str_contains($b,'vestra'))
    return '<span style="font-family:'.$sf.';color:'.$ink.';font-size:15px;font-weight:700;letter-spacing:.12em">VESTRA&nbsp;<span style="color:#c9a86a;font-size:10px;letter-spacing:.2em">ESSENTIALS</span></span>';
  return '<span style="font-family:'.$sf.';color:'.$ink.';font-size:16px;font-weight:600;letter-spacing:.12em">'.htmlspecialchars(strtoupper($brand),ENT_QUOTES,'UTF-8').'</span>';
}

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

  // Optional download list ('downloads' => ['title'=>..,'items'=>[['label'=>,'url'=>],..]]) —
  // renders each as a labelled row with a compact gold download button. Used for campaign
  // line-sheet / catalogue (Excel) links; additive, callers that pass none are unaffected.
  $downloadsHtml='';
  if(!empty($opts['downloads']['items']) && is_array($opts['downloads']['items'])){
    $dTitle=(string)($opts['downloads']['title'] ?? 'Downloads');
    $dRows='';
    foreach($opts['downloads']['items'] as $it){
      $label=(string)($it['label']??''); $url=(string)($it['url']??''); if($url==='') continue;
      $dRows.='<tr>'
        .'<td style="padding:10px 0;border-top:1px solid #ece6d8;color:#3a3428;font-size:14px">'.htmlspecialchars($label,ENT_QUOTES,'UTF-8').'</td>'
        .'<td style="padding:10px 0;border-top:1px solid #ece6d8;text-align:right">'
        .'<a href="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'" style="display:inline-block;background:#14110c;color:#d8bd86;padding:7px 16px;border-radius:6px;text-decoration:none;font-size:12px;font-weight:700;letter-spacing:.02em">Excel &#8595;</a>'
        .'</td></tr>';
    }
    if($dRows!==''){
      $downloadsHtml='<div style="margin:2px 28px 20px">'
        .'<div style="font-size:12px;font-weight:700;color:#8a6d1f;letter-spacing:.03em;text-transform:uppercase;margin:0 0 6px">'.htmlspecialchars($dTitle,ENT_QUOTES,'UTF-8').'</div>'
        .'<div style="background:#faf8f3;border:1px solid #ece6d8;border-radius:10px;padding:2px 16px">'
        .'<table style="width:100%;border-collapse:collapse" cellpadding="0" cellspacing="0">'.$dRows.'</table></div></div>';
    }
  }

  // Optional editorial hero band ('hero' => ['kicker'=>..,'title'=>..]) — sits directly under the
  // masthead on the same dark ground, so together they read as one magazine-style hero. Additive.
  $heroBandHtml='';
  if(!empty($opts['hero']['title'])){
    $kick=(string)($opts['hero']['kicker']??'');
    $heroBandHtml='<div style="background:#14110c;padding:2px 28px 30px">'
      .($kick!==''?'<div style="color:#a97f2c;font-size:11px;font-weight:700;letter-spacing:.24em;text-transform:uppercase;margin:0 0 12px">'.htmlspecialchars($kick,ENT_QUOTES,'UTF-8').'</div>':'')
      .'<div style="color:#f4ecd8;font-family:Georgia,\'Times New Roman\',serif;font-size:27px;line-height:1.24;font-weight:700">'.htmlspecialchars((string)$opts['hero']['title'],ENT_QUOTES,'UTF-8').'</div>'
      .'</div>';
  }

  // Optional "featured houses" brand strip ('brands' => [name,..]) — centered serif names split by
  // gold dots. Gives the multi-brand campaign an elegant, editorial signature. Additive.
  // "The houses on your floor" — a premium 2-column wall of dark, gold-edged brand-logo tiles.
  // Each tile is a stylised wordmark (email-safe, always renders) and links to that brand's Excel
  // line-sheet, so the logos double as the download CTA. Additive.
  $brandsHtml='';
  if(!empty($opts['brands']) && is_array($opts['brands'])){
    $names=array_slice(array_values(array_filter(array_map('strval',$opts['brands']),fn($s)=>trim($s)!=='')),0,8);
    if($names){
      $tiles=[];
      foreach($names as $n){
        $href='https://vestrasales.com/catalog?brand='.rawurlencode($n);
        $tiles[]='<a href="'.$href.'" style="display:block;background:#14110c;border:1px solid rgba(201,168,106,.34);border-radius:10px;padding:22px 8px;text-align:center;text-decoration:none">'
          .vestra_email_brand_logo_html($n).'</a>';
      }
      $tileRows='';
      for($i=0;$i<count($tiles);$i+=2){
        if(isset($tiles[$i+1])){
          $tileRows.='<tr><td width="50%" valign="middle" style="padding:5px">'.$tiles[$i].'</td>'
            .'<td width="50%" valign="middle" style="padding:5px">'.$tiles[$i+1].'</td></tr>';
        }else{
          // lone trailing house → full-width tile (reads as intentional, no empty cell)
          $tileRows.='<tr><td colspan="2" valign="middle" style="padding:5px">'.$tiles[$i].'</td></tr>';
        }
      }
      $brandsHtml='<div style="padding:8px 23px 20px">'
        .'<div style="color:#8a6d1f;font-size:11px;font-weight:700;letter-spacing:.2em;text-transform:uppercase;text-align:center;margin:2px 0 12px">'.htmlspecialchars((string)($opts['brands_title']??'Featured houses'),ENT_QUOTES,'UTF-8').'</div>'
        .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse">'.$tileRows.'</table>'
        .'<div style="color:#9b9585;font-size:11px;text-align:center;margin:12px 0 0">'.htmlspecialchars((string)($opts['brands_hint']??'Tap a house to open its line-sheet'),ENT_QUOTES,'UTF-8').'</div>'
        .'<div style="width:38px;height:2px;background:#c9a86a;margin:14px auto 0"></div>'
        .'</div>';
    }
  }

  return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
    .'<title>VESTRA</title></head>'
    .'<body style="margin:0;padding:0;background:#f4f2ee;font-family:Georgia,\'Times New Roman\',serif">'
    .'<div style="max-width:560px;margin:0 auto;padding:32px 16px">'
    .'<div style="background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e6e0d5">'
    /* Masthead: our own logo mark + wordmark. Table-based and vertically aligned because
       Outlook ignores flex/inline-block alignment. The mark is a PNG (Gmail and Outlook strip
       inline SVG) served from the site over https, and the wordmark stays live TEXT rather than
       being baked into the image — most clients block remote images by default, so the brand
       must still read when the logo never loads. */
    .'<div style="background:#14110c;padding:20px 28px">'
    .'<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
    .'<td valign="middle" style="padding-right:10px;line-height:0">'
    .'<img src="https://vestrasales.com/icon-192.png" width="30" height="30" alt=""'
    .' style="display:block;width:30px;height:30px;border:0;border-radius:7px"></td>'
    .'<td valign="middle" style="line-height:1">'
    .'<span style="color:#d8bd86;font-size:20px;font-weight:700;letter-spacing:.02em;font-family:Georgia,\'Times New Roman\',serif">VESTRA</span>'
    .'<span style="color:#8a8272;font-size:12px;margin-left:6px">sales</span>'
    .'</td></tr></table></div>'
    .$heroBandHtml
    .$heroHtml
    .$badgeHtml
    .'<div style="padding:20px 28px 8px">'.$mainHtml.'</div>'
    .$rowsHtml
    .$brandsHtml
    .$downloadsHtml
    .$buttonHtml
    .($footerHtml!==''?'<div style="padding:14px 28px 24px;border-top:1px solid #e6e0d5;margin-top:6px">'.$footerHtml.'</div>':'')
    .'<div style="padding:18px 28px 24px;border-top:1px solid #e6e0d5;text-align:center">'
    .'<div style="width:30px;height:2px;background:#c9a86a;margin:0 auto 12px"></div>'
    .'<div style="font-family:Georgia,\'Times New Roman\',serif;color:#14110c;font-size:15px;font-weight:700;letter-spacing:.1em">VESTRASALES</div>'
    .'<div style="color:#9b9585;font-size:11px;margin-top:4px">Verified B2B wholesale marketplace · vestrasales.com</div>'
    .'</div>'
    .'</div>'
    .'</div></body></html>';
}

/* Build the premium "authentic designer wholesale" campaign email → [subject, plainBody, opts].
 * One place for the outreach content so preview sends and real sends stay identical, and every
 * aesthetic tweak lives here. The featured-brand strip and the per-brand Excel catalogue links
 * are derived from the LIVE catalogue (vestra_products), so the mail always reflects real stock.
 * $company personalises the opening line when provided. */
function vestra_campaign_preview(string $company='', string $lang='en'): array {
  $counts=[]; $brands=[];
  if(function_exists('vestra_products')){
    foreach(vestra_products() as $p){ $b=trim((string)($p['brand']??'')); if($b==='') continue; $counts[$b]=($counts[$b]??0)+1; }
    arsort($counts); $brands=array_slice(array_keys($counts),0,8);
  }
  $downloads=[];
  foreach($brands as $b){ $downloads[]=['label'=>$b,'url'=>'https://vestrasales.com/catalog?brand='.rawurlencode($b)]; }
  if(!$downloads) $downloads[]=['label'=>'Full selection','url'=>'https://vestrasales.com/catalog'];

  if($lang==='nl'){
    $subject='Les Garage de Paris × VESTRA — de authentieke designer groothandelselectie';
    $body=implode("\n",[
      $company!=='' ? "Hallo — een bericht voor {$company}." : "Hallo,",
      "",
      "Een korte kennismaking van Les Garage de Paris, via VESTRA — de KYC-geverifieerde B2B-marktplaats voor authentieke designermode tegen groothandelsprijzen.",
      "",
      "Wij leveren premium multi-brand boutiques de merken waar hun klanten met naam naar vragen — 100% authentiek, echtheid gecontroleerd bij levering, op heldere factuurvoorwaarden.",
      "",
      "De actuele selectie staat hieronder als kant-en-klare Excel line-sheets. Handelsprijzen zijn voorbehouden aan geverifieerde partners — registreer eenmalig (gratis) en elke prijs wordt direct zichtbaar.",
      "",
      "Vertel me uw merkenmix en ik stel een selectie samen voor uw winkel.",
      "",
      "Met vriendelijke groet,",
      "Les Garage de Paris · via VESTRA",
      "",
      "—",
      "Les Garage de Paris via VESTRA (beheerd door acerasoft LLC). Eenmalig zakelijk bericht — uw winkel is geïdentificeerd als mogelijke premium handelspartner.",
      "Direct uitschrijven: https://vestrasales.com/lead-unsubscribe",
    ]);
    $opts=[
      'hero'=>['kicker'=>'Authentieke designer groothandel','title'=>'De merken waar uw klanten naar vragen — tegen handelsvoorwaarden.'],
      'brands'=>$brands,
      'brands_title'=>'Uitgelichte merken',
      'brands_hint'=>'Tik op een merk voor de line-sheet',
      'badge'=>'KYC-geverifieerd · echtheid gecontroleerd · escrow-beschermd',
      'downloads'=>['title'=>'Line-sheets — download (Excel)','items'=>$downloads],
      'rows'=>[
        ['label'=>'Echtheid','value'=>'Gecontroleerd bij levering'],
        ['label'=>'Betaling','value'=>'Escrow-beschermde factuur'],
        ['label'=>'Minimums','value'=>'Laag — capsule-vriendelijk','strong'=>true],
      ],
      'button'=>['label'=>'Registreer voor handelsprijzen','url'=>'https://vestrasales.com/register'],
    ];
    return [$subject,$body,$opts];
  }

  if($lang==='de'){
    $subject="Les Garage de Paris × VESTRA — die authentische Designer-Auswahl im Großhandel";
    $body=implode("\n",[
      $company!=='' ? "Guten Tag — eine Nachricht für {$company}." : "Guten Tag,",
      "",
      "Eine kurze Vorstellung von Les Garage de Paris, über VESTRA — den KYC-verifizierten B2B-Marktplatz für authentische Designermode zu Großhandelspreisen.",
      "",
      "Wir beliefern Premium-Multibrand-Boutiquen mit den Häusern, nach denen ihre Kundschaft namentlich fragt — 100% authentisch, Echtheit bei Lieferung geprüft, zu klaren Rechnungsbedingungen.",
      "",
      "Die aktuelle Auswahl finden Sie unten als fertige Excel-Line-Sheets. Großhandelspreise sind verifizierten Partnern vorbehalten — registrieren Sie sich einmal (kostenlos) und jeder Preis wird sofort freigeschaltet.",
      "",
      "Nennen Sie mir Ihren Markenmix und ich stelle eine Auswahl für Ihre Fläche zusammen.",
      "",
      "Mit freundlichen Grüßen,",
      "Les Garage de Paris · via VESTRA",
      "",
      "—",
      "Les Garage de Paris via VESTRA (betrieben von acerasoft LLC). Einmalige geschäftliche Nachricht — Ihr Geschäft wurde als potenzieller Premium-Handelspartner identifiziert.",
      "Sofort abmelden: https://vestrasales.com/lead-unsubscribe",
    ]);
    $opts=[
      'hero'=>['kicker'=>'Authentische Designermode im Großhandel','title'=>'Die Häuser, nach denen Ihre Kundschaft fragt — zu Handelskonditionen.'],
      'brands'=>$brands,
      'brands_title'=>'Ausgewählte Häuser',
      'brands_hint'=>'Tippen Sie auf ein Haus für das Line-Sheet',
      'badge'=>'KYC-verifiziert · Echtheit geprüft · Escrow-geschützt',
      'downloads'=>['title'=>'Line-Sheets — Download (Excel)','items'=>$downloads],
      'rows'=>[
        ['label'=>'Echtheit','value'=>'Bei Lieferung geprüft'],
        ['label'=>'Zahlung','value'=>'Escrow-geschützte Rechnung'],
        ['label'=>'Mindestmengen','value'=>'Niedrig — Capsule-geeignet','strong'=>true],
      ],
      'button'=>['label'=>'Für Großhandelspreise registrieren','url'=>'https://vestrasales.com/register'],
    ];
    return [$subject,$body,$opts];
  }

  if($lang==='fr'){
    $subject="Les Garage de Paris × VESTRA — la sélection authentique de mode de créateurs en gros";
    $body=implode("\n",[
      $company!=='' ? "Bonjour — un message pour {$company}." : "Bonjour,",
      "",
      "Une brève présentation de Les Garage de Paris, via VESTRA — la marketplace B2B vérifiée KYC pour la mode de créateurs authentique en gros.",
      "",
      "Nous approvisionnons des boutiques multimarques premium avec les maisons que leurs clients demandent par leur nom — 100% authentique, authenticité vérifiée à la livraison, avec des conditions de facturation claires.",
      "",
      "La sélection actuelle est ci-dessous sous forme de line-sheets Excel prêts à l'emploi. Les prix de gros sont réservés aux partenaires vérifiés — inscrivez-vous une fois (c'est gratuit) et chaque prix se débloque instantanément.",
      "",
      "Indiquez-moi votre mix de marques et je préparerai une sélection pour votre boutique.",
      "",
      "Cordialement,",
      "Les Garage de Paris · via VESTRA",
      "",
      "—",
      "Les Garage de Paris via VESTRA (exploité par acerasoft LLC). Message commercial unique — votre boutique a été identifiée comme partenaire commercial premium potentiel.",
      "Se désinscrire immédiatement : https://vestrasales.com/lead-unsubscribe",
    ]);
    $opts=[
      'hero'=>['kicker'=>'Mode de créateurs authentique en gros','title'=>'Les maisons que vos clients demandent — à des conditions de gros.'],
      'brands'=>$brands,
      'brands_title'=>'Maisons en vedette',
      'brands_hint'=>'Touchez une maison pour ouvrir son line-sheet',
      'badge'=>'Vérifié KYC · authenticité contrôlée · protégé par séquestre',
      'downloads'=>['title'=>'Line-sheets — télécharger (Excel)','items'=>$downloads],
      'rows'=>[
        ['label'=>'Authenticité','value'=>'Vérifiée à la livraison'],
        ['label'=>'Paiement','value'=>'Facture protégée par séquestre'],
        ['label'=>'Minimums','value'=>'Bas — adaptés aux capsules','strong'=>true],
      ],
      'button'=>['label'=>"S'inscrire pour les prix de gros",'url'=>'https://vestrasales.com/register'],
    ];
    return [$subject,$body,$opts];
  }

  if($lang==='it'){
    $subject="Les Garage de Paris × VESTRA — la selezione autentica di moda di lusso all'ingrosso";
    $body=implode("\n",[
      $company!=='' ? "Salve — un messaggio per {$company}." : "Salve,",
      "",
      "Una breve presentazione di Les Garage de Paris, tramite VESTRA — il marketplace B2B verificato KYC per moda di design autentica all'ingrosso.",
      "",
      "Riforniamo boutique multimarca premium con le maison che i loro clienti richiedono per nome — 100% autentico, autenticità verificata alla consegna, con condizioni di fatturazione chiare.",
      "",
      "La selezione attuale è disponibile qui sotto come line-sheet Excel pronti all'uso. I prezzi all'ingrosso sono riservati ai partner verificati — registratevi una volta (gratis) e ogni prezzo si sblocca immediatamente.",
      "",
      "Indicatemi il vostro mix di marchi e curerò una selezione per il vostro negozio.",
      "",
      "Cordiali saluti,",
      "Les Garage de Paris · via VESTRA",
      "",
      "—",
      "Les Garage de Paris via VESTRA (gestito da acerasoft LLC). Messaggio commerciale una tantum — il vostro negozio è stato identificato come potenziale partner commerciale premium.",
      "Annulla l'iscrizione subito: https://vestrasales.com/lead-unsubscribe",
    ]);
    $opts=[
      'hero'=>['kicker'=>"Moda di design autentica all'ingrosso",'title'=>'Le maison che i vostri clienti richiedono — a condizioni commerciali.'],
      'brands'=>$brands,
      'brands_title'=>'Maison in evidenza',
      'brands_hint'=>'Toccare una maison per aprire il line-sheet',
      'badge'=>'Verificato KYC · autenticità controllata · protetto da escrow',
      'downloads'=>['title'=>'Line-sheet — scarica (Excel)','items'=>$downloads],
      'rows'=>[
        ['label'=>'Autenticità','value'=>'Verificata alla consegna'],
        ['label'=>'Pagamento','value'=>'Fattura protetta da escrow'],
        ['label'=>'Minimi','value'=>'Bassi — ideali per capsule','strong'=>true],
      ],
      'button'=>['label'=>"Registrati per i prezzi all'ingrosso",'url'=>'https://vestrasales.com/register'],
    ];
    return [$subject,$body,$opts];
  }

  if($lang==='pt'){
    $subject="Les Garage de Paris × VESTRA — a seleção autêntica de moda de grife por atacado";
    $body=implode("\n",[
      $company!=='' ? "Olá — uma mensagem para {$company}." : "Olá,",
      "",
      "Uma breve apresentação da Les Garage de Paris, através da VESTRA — o marketplace B2B verificado por KYC para moda de grife autêntica por atacado.",
      "",
      "Fornecemos a boutiques multimarcas premium as grifes que os seus clientes pedem pelo nome — 100% autênticas, autenticidade verificada na entrega, com condições de faturação claras.",
      "",
      "A seleção atual está abaixo em line-sheets Excel prontos a abrir. Os preços de atacado são reservados a parceiros verificados — registe-se uma vez (é gratuito) e cada preço é desbloqueado de imediato.",
      "",
      "Diga-me o seu mix de marcas e preparo uma seleção para a sua loja.",
      "",
      "Com os melhores cumprimentos,",
      "Les Garage de Paris · via VESTRA",
      "",
      "—",
      "Les Garage de Paris via VESTRA (operado pela acerasoft LLC). Mensagem comercial única — a sua loja foi identificada como potencial parceiro comercial premium.",
      "Cancelar subscrição imediatamente: https://vestrasales.com/lead-unsubscribe",
    ]);
    $opts=[
      'hero'=>['kicker'=>'Moda de grife autêntica por atacado','title'=>'As grifes que os seus clientes pedem — em condições de atacado.'],
      'brands'=>$brands,
      'brands_title'=>'Marcas em destaque',
      'brands_hint'=>'Toque numa marca para abrir o line-sheet',
      'badge'=>'Verificado por KYC · autenticidade verificada · protegido por escrow',
      'downloads'=>['title'=>'Line-sheets — descarregar (Excel)','items'=>$downloads],
      'rows'=>[
        ['label'=>'Autenticidade','value'=>'Verificada na entrega'],
        ['label'=>'Pagamento','value'=>'Fatura protegida por escrow'],
        ['label'=>'Mínimos','value'=>'Baixos — ideais para cápsulas','strong'=>true],
      ],
      'button'=>['label'=>'Registar para preços de atacado','url'=>'https://vestrasales.com/register'],
    ];
    return [$subject,$body,$opts];
  }

  if($lang==='cs'){
    $subject="Les Garage de Paris × VESTRA — autentický výběr značkové módy za velkoobchodní ceny";
    $body=implode("\n",[
      $company!=='' ? "Dobrý den — zpráva pro {$company}." : "Dobrý den,",
      "",
      "Krátké představení Les Garage de Paris prostřednictvím VESTRA — B2B tržiště ověřené KYC pro autentickou značkovou módu za velkoobchodní ceny.",
      "",
      "Zásobujeme prémiové multibrandové butiky značkami, o které jejich zákazníci žádají jmenovitě — 100% autentické, pravost ověřena při doručení, za jasných fakturačních podmínek.",
      "",
      "Aktuální nabídka je níže jako připravené Excel ceníky (line-sheets). Velkoobchodní ceny jsou vyhrazeny ověřeným partnerům — zaregistrujte se jednou (zdarma) a každá cena se okamžitě odemkne.",
      "",
      "Řekněte mi vaši značkovou skladbu a připravím výběr pro vaši prodejnu.",
      "",
      "S pozdravem,",
      "Les Garage de Paris · via VESTRA",
      "",
      "—",
      "Les Garage de Paris via VESTRA (provozuje acerasoft LLC). Jednorázová obchodní zpráva — váš obchod byl identifikován jako potenciální prémiový obchodní partner.",
      "Okamžité odhlášení: https://vestrasales.com/lead-unsubscribe",
    ]);
    $opts=[
      'hero'=>['kicker'=>'Autentická značková móda za velkoobchodní ceny','title'=>'Značky, o které vaši zákazníci žádají — za velkoobchodních podmínek.'],
      'brands'=>$brands,
      'brands_title'=>'Vybrané značky',
      'brands_hint'=>'Klepnutím na značku otevřete ceník',
      'badge'=>'Ověřeno KYC · pravost kontrolována · chráněno escrow',
      'downloads'=>['title'=>'Ceníky — stáhnout (Excel)','items'=>$downloads],
      'rows'=>[
        ['label'=>'Pravost','value'=>'Ověřena při doručení'],
        ['label'=>'Platba','value'=>'Faktura chráněná escrow'],
        ['label'=>'Minimální odběr','value'=>'Nízký — vhodné pro kapsulové kolekce','strong'=>true],
      ],
      'button'=>['label'=>'Registrovat se pro velkoobchodní ceny','url'=>'https://vestrasales.com/register'],
    ];
    return [$subject,$body,$opts];
  }

  if($lang==='pl'){
    $subject="Les Garage de Paris × VESTRA — autentyczny wybór mody projektantów w cenach hurtowych";
    $body=implode("\n",[
      $company!=='' ? "Dzień dobry — wiadomość dla {$company}." : "Dzień dobry,",
      "",
      "Krótkie przedstawienie Les Garage de Paris za pośrednictwem VESTRA — zweryfikowanego KYC rynku B2B dla autentycznej mody projektantów w cenach hurtowych.",
      "",
      "Zaopatrujemy premium butiki multibrandowe w marki, o które klienci pytają z nazwy — 100% autentyczne, autentyczność weryfikowana przy dostawie, na jasnych warunkach fakturowania.",
      "",
      "Aktualny wybór znajduje się poniżej jako gotowe do otwarcia arkusze Excel (line-sheets). Ceny hurtowe są zarezerwowane dla zweryfikowanych partnerów — zarejestruj się raz (bezpłatnie), a każda cena odblokuje się natychmiast.",
      "",
      "Podaj mi swój mix marek, a przygotuję wybór dla Twojego sklepu.",
      "",
      "Z poważaniem,",
      "Les Garage de Paris · via VESTRA",
      "",
      "—",
      "Les Garage de Paris via VESTRA (prowadzone przez acerasoft LLC). Jednorazowa wiadomość biznesowa — Twój sklep został zidentyfikowany jako potencjalny partner handlowy premium.",
      "Natychmiastowa rezygnacja z subskrypcji: https://vestrasales.com/lead-unsubscribe",
    ]);
    $opts=[
      'hero'=>['kicker'=>'Autentyczna moda projektantów w cenach hurtowych','title'=>'Marki, o które pytają Twoi klienci — na warunkach hurtowych.'],
      'brands'=>$brands,
      'brands_title'=>'Wyróżnione marki',
      'brands_hint'=>'Dotknij marki, aby otworzyć jej line-sheet',
      'badge'=>'Zweryfikowano KYC · autentyczność sprawdzona · chronione escrow',
      'downloads'=>['title'=>'Line-sheets — pobierz (Excel)','items'=>$downloads],
      'rows'=>[
        ['label'=>'Autentyczność','value'=>'Weryfikowana przy dostawie'],
        ['label'=>'Płatność','value'=>'Faktura chroniona escrow'],
        ['label'=>'Minima','value'=>'Niskie — idealne na kolekcje kapsułowe','strong'=>true],
      ],
      'button'=>['label'=>'Zarejestruj się, aby zobaczyć ceny hurtowe','url'=>'https://vestrasales.com/register'],
    ];
    return [$subject,$body,$opts];
  }

  if($lang==='es'){
    $subject="Les Garage de Paris × VESTRA — la selección auténtica de moda de diseño al por mayor";
    $body=implode("\n",[
      $company!=='' ? "Hola — un mensaje para {$company}." : "Hola,",
      "",
      "Una breve presentación de Les Garage de Paris, a través de VESTRA — el marketplace B2B verificado por KYC para moda de diseño auténtica al por mayor.",
      "",
      "Suministramos a boutiques multimarca premium las firmas que sus clientes piden por su nombre — 100% auténticas, autenticidad verificada en la entrega, con condiciones de facturación claras.",
      "",
      "La selección actual está a continuación en forma de line-sheets de Excel listos para abrir. Los precios mayoristas están reservados a socios verificados — regístrese una vez (es gratis) y cada precio se desbloquea al instante.",
      "",
      "Indíqueme su mix de marcas y prepararé una selección para su tienda.",
      "",
      "Un cordial saludo,",
      "Les Garage de Paris · via VESTRA",
      "",
      "—",
      "Les Garage de Paris via VESTRA (operado por acerasoft LLC). Mensaje comercial único — su tienda ha sido identificada como potencial socio comercial premium.",
      "Cancelar suscripción al instante: https://vestrasales.com/lead-unsubscribe",
    ]);
    $opts=[
      'hero'=>['kicker'=>'Moda de diseño auténtica al por mayor','title'=>'Las firmas que sus clientes piden — en condiciones mayoristas.'],
      'brands'=>$brands,
      'brands_title'=>'Firmas destacadas',
      'brands_hint'=>'Toque una firma para abrir su line-sheet',
      'badge'=>'Verificado KYC · autenticidad comprobada · protegido por depósito en garantía',
      'downloads'=>['title'=>'Line-sheets — descargar (Excel)','items'=>$downloads],
      'rows'=>[
        ['label'=>'Autenticidad','value'=>'Verificada en la entrega'],
        ['label'=>'Pago','value'=>'Factura protegida por depósito en garantía'],
        ['label'=>'Mínimos','value'=>'Bajos — ideales para cápsulas','strong'=>true],
      ],
      'button'=>['label'=>'Regístrese para ver precios mayoristas','url'=>'https://vestrasales.com/register'],
    ];
    return [$subject,$body,$opts];
  }

  if($lang==='el'){
    $subject="Les Garage de Paris × VESTRA — η αυθεντική επιλογή σχεδιαστικής μόδας χονδρικής";
    $body=implode("\n",[
      $company!=='' ? "Γεια σας — ένα μήνυμα για {$company}." : "Γεια σας,",
      "",
      "Μια σύντομη παρουσίαση της Les Garage de Paris, μέσω της VESTRA — της επαληθευμένης με KYC B2B αγοράς για αυθεντική σχεδιαστική μόδα σε τιμές χονδρικής.",
      "",
      "Προμηθεύουμε premium πολυμαρκικές μπουτίκ με τους οίκους που οι πελάτες τους ζητούν ονομαστικά — 100% αυθεντικά, με έλεγχο αυθεντικότητας κατά την παράδοση, με σαφείς όρους τιμολόγησης.",
      "",
      "Η τρέχουσα επιλογή βρίσκεται παρακάτω ως έτοιμα προς άνοιγμα line-sheets σε Excel. Οι τιμές χονδρικής προορίζονται αποκλειστικά για επαληθευμένους συνεργάτες — εγγραφείτε μία φορά (δωρεάν) και κάθε τιμή ξεκλειδώνεται άμεσα.",
      "",
      "Πείτε μου το brand mix σας και θα ετοιμάσω μια επιλογή για το κατάστημά σας.",
      "",
      "Με εκτίμηση,",
      "Les Garage de Paris · via VESTRA",
      "",
      "—",
      "Les Garage de Paris via VESTRA (λειτουργεί από την acerasoft LLC). Μοναδικό εμπορικό μήνυμα — το κατάστημά σας αναγνωρίστηκε ως πιθανός premium εμπορικός συνεργάτης.",
      "Άμεση διαγραφή: https://vestrasales.com/lead-unsubscribe",
    ]);
    $opts=[
      'hero'=>['kicker'=>'Αυθεντική σχεδιαστική μόδα χονδρικής','title'=>'Οι οίκοι που ζητούν οι πελάτες σας — με όρους χονδρικής.'],
      'brands'=>$brands,
      'brands_title'=>'Επιλεγμένοι οίκοι',
      'brands_hint'=>'Πατήστε σε έναν οίκο για να ανοίξετε το line-sheet',
      'badge'=>'Επαληθευμένο KYC · ελεγμένη αυθεντικότητα · προστασία escrow',
      'downloads'=>['title'=>'Line-sheets — λήψη (Excel)','items'=>$downloads],
      'rows'=>[
        ['label'=>'Αυθεντικότητα','value'=>'Ελέγχεται κατά την παράδοση'],
        ['label'=>'Πληρωμή','value'=>'Τιμολόγιο προστατευμένο με escrow'],
        ['label'=>'Ελάχιστες ποσότητες','value'=>'Χαμηλές — ιδανικές για capsule collections','strong'=>true],
      ],
      'button'=>['label'=>'Εγγραφείτε για τιμές χονδρικής','url'=>'https://vestrasales.com/register'],
    ];
    return [$subject,$body,$opts];
  }

  $subject='Les Garage de Paris × VESTRA — the authentic designer wholesale edit';
  $body=implode("\n",[
    $company!=='' ? "Hello — a note for {$company}." : "Hello,",
    "",
    "A brief introduction from Les Garage de Paris, through VESTRA — the KYC-verified B2B marketplace for authentic designer fashion at wholesale.",
    "",
    "We supply premium multi-brand boutiques with the houses their clients ask for by name — 100% authentic, authenticity-verified on delivery, on clear invoice terms.",
    "",
    "The current selection is below as ready-to-open Excel line-sheets. Trade pricing is reserved for verified partners — register once (it's free) and every price unlocks instantly.",
    "",
    "Tell me your brand mix and I'll curate a selection for your floor.",
    "",
    "Warm regards,",
    "Les Garage de Paris · via VESTRA",
    "",
    "—",
    "Les Garage de Paris via VESTRA (operated by acerasoft LLC). One-time business message — your store was identified as a potential premium trade partner.",
    "Unsubscribe instantly: https://vestrasales.com/lead-unsubscribe",
  ]);
  $opts=[
    'hero'=>['kicker'=>'Authentic designer wholesale','title'=>'The houses your clients ask for — at trade terms.'],
    'brands'=>$brands,
    'brands_title'=>'Featured houses',
    'brands_hint'=>'Tap a house to open its line-sheet',
    'badge'=>'KYC-verified · authenticity-checked · escrow-protected',
    'downloads'=>['title'=>'Line-sheets — download (Excel)','items'=>$downloads],
    'rows'=>[
      ['label'=>'Authenticity','value'=>'Verified on delivery'],
      ['label'=>'Payment','value'=>'Escrow-protected invoice'],
      ['label'=>'Minimums','value'=>'Low — capsule-friendly','strong'=>true],
    ],
    'button'=>['label'=>'Register for trade pricing','url'=>'https://vestrasales.com/register'],
  ];
  return [$subject,$body,$opts];
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
