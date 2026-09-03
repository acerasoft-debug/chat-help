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
  /* "firstname.lastname" contact-form boilerplate, in the languages we harvest: nom.prenom
     (FR) was actually selected for a real send from a Lyon boutique's page. */
  if(in_array($lp,['nom.prenom','prenom.nom','vorname.nachname','nachname.vorname',
                   'nombre.apellido','apellido.nombre','naam.achternaam','nome.cognome',
                   'firstname.lastname','first.last','ad.soyad'],true)) return true;
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
       .'|@site123\.|@ionos\.|@one\.com|@hostpoint\.|@infomaniak\.|@web\.com'
       // Same failure, more platforms — each was caught mid-campaign on a real lead:
       // support@jouwweb.nl (NL site builder), blog@wordpress.com, domains@loopia.com (SE host).
       .'|@jouwweb\.|@wordpress\.com|@loopia\.|@hostinger\.|@wordpress\.org|@automattic\.)#i';
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
  /* Olu anahtar mandali. Anahtar reddedilirse (401/403) SONRAKI her cagri da
     reddedilecek: bir tarama turunda bu, yuzlerce 25 saniyelik istek ve yuzlerce
     ayni hata satiri demek (son taramada 675 tane). Bir kez ogrenip bu istek
     boyunca API'yi atliyoruz -- ucretsiz site-tarama yedegi zaten calisiyor,
     yani kesif durmuyor, sadece olu kapiyi calmayi birakiyor. */
  static $keyDead = [];
  if($key!=='' && isset($keyDead[$provider.'|'.$key])) $key='';
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
      elseif($code){
        if($code===401||$code===403){ $keyDead[$provider.'|'.$key]=true; error_log("[VESTRA finder] anymailfinder HTTP {$code} — anahtar reddedildi, bu istek boyunca API atlaniyor (ucretsiz yedege dusuluyor)"); }
        else error_log("[VESTRA finder] anymailfinder HTTP {$code}");
      }
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
      } elseif($code){
        if($code===401||$code===403){ $keyDead[$provider.'|'.$key]=true; error_log("[VESTRA finder] hunter HTTP {$code} — anahtar reddedildi, bu istek boyunca API atlaniyor (ucretsiz yedege dusuluyor)"); }
        else error_log("[VESTRA finder] hunter HTTP {$code}");
      }
    }
    if($api!=='') return $api;
  }
  // 2) Free fallback — read the company's own site. Works with NO key.
  $own=vestra_scrape_email($domain);
  if($own!=='') return $own;
  /* 3) Google's index, if a Custom Search key is configured. Last on purpose: it is
     the only step that costs a query, and it only earns it on the sites the free
     reader cannot crack -- address in an image, behind a contact form, or three
     clicks deep. Required inside the function rather than at the top of the file so
     the two never form a load-order loop (discover_google.php requires this file). */
  require_once __DIR__.'/discover_google.php';
  return vestra_google_cse_email($domain);
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
/* Son sorguda Overpass'in KENDI ic zaman asimina dusup dusmedigi.
 *
 * Bu, vestra_osm_ok()'tan AYRI bir bilgi ve ayri olmasi sart. Overpass agir bir
 * sorguya HTTP 200 + {"elements":[],"remark":"... timed out ..."} donuyor; baska
 * bir ayna da ayni sorguya temiz ama BOS cevap verebiliyor. O durumda osm_ok
 * true kaliyor ve cagiran taraf "bu bolgede hic dukkan yok" sonucunu cikariyor --
 * oysa gercek sebep sorgunun agir olmasi. Admin'deki "musteri bul" dugmesi tam
 * bu yuzden ulke genelinde hep "0 bulundu" gosteriyordu: Hollanda'da elbette
 * dukkan var, sorgu tamamlanamiyordu. Bunu ayri tutunca cagiran taraf "bos" ile
 * "yetismedi"yi ayirt edip kullaniciya dogru seyi soyleyebiliyor. */
function vestra_osm_timeout(?bool $set = null): bool {
  static $t = false;
  if ($set !== null) $t = $set;
  return $t;
}
function vestra_overpass(string $ql): string {
  vestra_osm_timeout(false);   // her cagriyi temiz baslat: bayrak ONCEKI sorgudan kalmasin
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
      if($softTimeout) vestra_osm_timeout(true);
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
    /* 2 Eyl 2026 — yeni koleksiyon (Winter 26/27) duyurusu icin havuzdan
       secilen ilk 100 lead okundu: hepsi ilk kampanyayi bloklist olgunlasmadan
       once almisti. Asagidakiler kuru kosuda cikti ve ELLE elendi; bir sonraki
       secimde kendiliginden dussunler. Kategoriler yine ayni: magaza zinciri /
       department store, kanalda rakip dev e-tailer, marka sahibi, AVM/duty-free
       isletmecisi. */
    // department store / magaza zinciri
    'b&m bargains','b&m stores','bmstores','arnotts','mcelhinneys','shaws',
    'kastner & öhler','kastner & oehler','kastner und öhler','kastner-oehler','kastneroehler',
    'harry rosen','harryrosen','culture kings','culturekings','tsum',
    // dev e-tailer (kanalda musteri degil rakip). 'bluefly' BILEREK YOK: 7 harf,
    // alan adinda alt dizi olarak eslesir ve "blueflyboutique.com"u yakardi (test).
    'mytheresa','smallable','vogacloset','style for less','styleforlessuae',
    // marka sahibi / lisans yoneticisi / kendi magazalari
    'bluestar alliance','bluestarall','bellerose',
    // AVM / outlet koyu / duty-free isletmecisi (butik degil, kiraci ya da imtiyaz)
    'kildare village','kildarevillage','value retail','dublin duty free','dublindutyfree',
    'aer rianta','bicester village','bicestervillage','ros retail outlet','ros-management',
    // ikinci el / resale devleri ve kanalda rakip off-price e-tailer (ikinci kuru kosu)
    'the realreal','therealreal','stadium goods','stadiumgoods','the outnet','theoutnet',
    // cok subeli sneaker zinciri (CZ/SK, 20+ sube)
    'buzz sneakers','buzzsneakers',
    /* ucuncu kuru kosu: pazar yeri / dev e-tailer (butik toplayan platformlar:
       bizden almazlar, bizimle ayni isi yaparlar), Japon select-shop zincirleri
       (Sazaby League: Estnation ~5, Ron Herman ~15 sube), outlet AVM isletmecileri */
    'shoptiques','moda operandi','modaoperandi','garmentory','shopbop',
    'estnation','エストネーション','ron herman','ronherman','ロンハーマン',
    'landquart fashion outlet','landquartfashionoutlet','citadel outlets','citadeloutlets',
    /* 2 Eyl 2026 — "Luks Butik Leadleri" xlsx (32 satir). Aïshti: Beyrut merkezli
       luks perakende GRUBU (department store + onlarca markanin Orta Dogu
       franchise/dagitim haklari) -- listede "bagimsiz butik" diye geciyordu. */
    'aishti','aïshti',
    /* 31 Agu 2026 — APAC "luxury stores" listesi. Engelleme listesi 73'te
       yalnizca ikisini yakaladi (DSM Ginza, Nepenthes). Elle okununca yine
       ayni uc kategori cikti; ayrica kanalda alici OLMAYAN iki tur:
       alisveris merkezi isletmecisi ve ikinci el (resale). */
    // kendi markasini satan / monobrand (SG, JP, AU, ID)
    'sabrinagoh','sabrina goh','beyond the vines','beyondthevines','kwanpen',
    'colony clothing','colonyclothing','in good company','ingoodcompany',
    'white story','whitestory','lulu yasmine','luluyasmine','magali pascal','magalipascal',
    'paul ropp','paulropp','biasa bali','biasagroup','lily jean','lilyjean',
    'jungle gold','junglegold','studious tokyo','tokyobase',
    // distributor / marka haklarini tutan grup
    'melium',
    /* 1 Eyl 2026 — ayni APAC listesi ikinci kez yuklendi. 31 Agustos'ta elle
       okunurken bu ikisi ZINCIR oldugu halde gozden kacti ve kampanya aldilar
       (run 33415951830, 16:48). Harrolds: Melbourne/Sydney/Chadstone magazalari
       olan cok subeli luks perakendeci, ustelik bazi markalarin Avustralya
       haklarini tutuyor. Incu: ~9 subeli cok markali zincir + kendi etiketi
       "Incu Collection". Geri alinamaz, ama liste ucuncu kez gelirse tekrarlamaz.
       'incu' 4 harf -> alan adinda YALNIZCA tam eslesir (incubator... elenmez). */
    'harrolds','incu',
    /* 1 Eyl 2026 — ikinci APAC listesi ("unique", 76 satir). Blokliste 76'da
       yalnizca 12'sini yakaladi; asagidakiler elle okununca cikti. Kategoriler
       yine ayni ucu. */
    // zincir / magazalar grubu (Japonya'nin iki devi: yuzlerce sube + kendi etiketleri)
    'united arrows','unitedarrows','beams','atmos tokyo','atmostokyo',
    'limited edt','limitededt',
    // distributor / bolgesel marka haklarini tutan grup
    'club 21','club21global',          // Club 21: Asya'da onlarca markanin bolge temsilcisi
    'the hour glass','thehourglass',   // cok ulkeli saat zinciri + resmi distributor
    // kendi markasini satan / uretici
    'paspaley','lucy folk','lucyfolk','pestle & mortar','pestle and mortar',
    'uma and leopold','umaandleopold','bamboo blonde','bambooblonde',
    'real mccoys','real mccoy','realmccoys','kim soo','kimsoo',
    /* 1 Eyl 2026 — 100 satirlik Avrupa listesi. Blokliste 100'de yalnizca 9'unu
       yakaladi (Slam Jam, Excelsior, LuisaViaRoma, END, DSM, Browns, Flannels,
       Wood Wood, Our Legacy); asagidakiler elle okununca cikti. */
    // zincir / magazalar grubu / kanalda rakip dev e-tailer
    'cruise fashion','cruisefashion','sevenstore','cricket fashion','cricketfashion',
    'ln-cc','antonioli','tessabit','bongenie','bongénie','steffl',
    'bernardelli','tiziana fausti','al duca d\'aosta','alducadaosta',
    'fashion clinic','fashionclinic','smets','sivasdescalzo','caliroots','furest',
    // kendi markasini uretip satan (kendi fabrikasindan alir, bizden asla)
    'slowear','lardini','norse store','norsestore','le fix',
    /* 1 Eyl 2026 — 100 satirlik kuresel liste. Blokliste 100'de 18'ini yakaladi;
       asagidakiler elle okununca cikti. Cogu SNEAKER zinciri: VESTRA'nin
       Lacoste/Ralph Lauren hattindan ayri bir kanal, ustelik hepsi cok subeli. */
    // department store / magazalar grubu
    'harvey nichols','harveynichols','brown thomas','brownthomas',
    '10 corso como','10corsocomo','leam',
    // Korfez: bolge haklarini tutan gruplarin perakende kollari
    'ounass','etoile la boutique','etoilelaboutique',
    // cok subeli sneaker zinciri / kanalda rakip e-tailer
    'footpatrol','solebox','sneakersnstuff','foot district','footdistrict','titolo',
    // ikinci el / konsinye: yeni toptan mal almiyor (Luxe It Fwd ile ayni gerekce)
    'the luxury closet','theluxurycloset',
    // kendi markasini uretip satan
    'patta','juice store','juicestore',
    // alisveris merkezi isletmecisi (butik degil, kiraci topluyor)
    'pavilion-kl','pavilionkl','klcc pavilion','klccpavilion',
    // ikinci el / konsinye: yeni toptan mal almiyor
    'luxe it fwd','luxeitfwd',
    // hic moda perakendecisi degil (ortak calisma alani)
    'colony work',
    /* 31 Agu 2026 — elle verilen 199 satirlik "luxury brand stores" listesi.
       Engelleme listesi bunlarin YALNIZCA ucunu yakaladi (Aspesi, SSENSE,
       Jacquemus); geri kalani listeyi okuyarak ayiklandi. Uc kategori:
       (a) kendi markasini satan magazalar, (b) zincir/magazalar grubu ve
       kanalda rakip olan dev e-tailerlar, (c) distributor. */
    // (a) kendi markasi / monobrand
    'our legacy','lemaire','noah ny','noahny','todd snyder','toddsnyder','billy reid','billyreid',
    'imogene + willie','imogene and willie','imogenewillie','somedays lovin','nepenthes',
    'wood wood','woodwood',
    // (b) zincir / magazalar grubu / dev e-tailer (kanalda musteri degil rakip)
    'kith','flannels','matchesfashion','matches fashion','browns fashion','brownsfashion',
    'end clothing','endclothing','luisaviaroma','luisa via roma','excelsior milano',
    'dover street market','doverstreetmarket','boon the shop','boontheshop','siwilai',
    /* 'atmos' ve 'mashburn' TEK BASINA fazla genis: "Atmos Green Concept" ve
       "Mashburn Family Store" testte elendi. Gercek kayitlarin tam adiyla. */
    'atmos usa','atmosusa','sid mashburn','sidmashburn','ann mashburn','annmashburn',
    'folli follie','follifollie',
    // (c) distributor — bolgesel marka haklarini tutuyor
    'slam jam','slamjam',
    // mass-market / fast-fashion chains
    'zara','h&m','h & m','c&a','c & a','primark','mango','uniqlo','bershka','pull&bear','pull & bear',
    'stradivarius','new yorker','takko','kik','nkd',"ernsting's family",'peek & cloppenburg','peek&cloppenburg',
    'esprit','s.oliver','tom tailor','jack & jones','vero moda','celio','kiabi','forever 21','gap',
    'old navy','banana republic','topshop','topman','river island','marks & spencer','m&s','jd sports',
    'foot locker','footlocker','deichmann','görtz','goertz','snipes','courir','next retail',
    /* ABD ayakkabi/streetwear zinciri (~250+ sube, cogu eyalette var). 3 Eyl 2026
       new_collection kuru kosusunda "onceden yazilmis" havuzundan cikti -- yani
       ilk kampanyada da kacmisti, iki mektup birden bu bosluktan geciyordu. */
    'dtlr',
    // department stores
    'galeries lafayette','el corte inglés','el corte ingles','karstadt','kaufhof','john lewis','debenhams',
    "macy's",'nordstrom','harrods','selfridges','myer','david jones',
    /* ABD magaza zincirleri ve indirim (off-price) kollari. Saks OFF 5TH bu
       kontrolden GECMISTI: listede sadece "nordstrom" vardi, "saks" yoktu --
       ayni partide Nordstrom Rack yakalanip Saks OFF 5TH gecince acik ortaya
       cikti. Zincirin indirim kolu da zincirdir; ~100 magazali bir off-price
       agi bir pazar yerinden kucuk parti almaz. */
    /* DIKKAT: kisa parcalar kullanma. Alan adi eslesmesi ayiraclari atip ALT DIZI
       ariyor, yani 4 harflik 'saks' Isvecli "Isaksson Mode"un icinde eslesip
       gercek bir butigi sessizce eliyordu. Zincirin tam adini yaz. */
    'saks off 5th','saksoff5th','saks fifth avenue','saksfifthavenue',
    'bergdorf','neiman marcus','bloomingdale',"dillard's",'dillards',
    'tj maxx','tjmaxx','tk maxx','marshalls','ross stores','ross dress',
    'burlington coat','century 21 stores','jcpenney','j.c. penney',"kohl's",'kohls',
    /* Kanada: kendi markasini satan ureticiler ve zincirler. 30 Agustos 2026'da
       gelen 25 kisilik Kanada listesinin neredeyse tamami bu iki gruptu -- biri
       cok markali butik degildi. Kendi etiketini uretip satan bir firma bizden
       almaz, rakiptir; zincir de pazar yerinden kucuk parti almaz.
       DIKKAT, tek kelimelik ad eklemedim: 'aldo' Italyan bir isim (Aldo Coppola),
       'garage' hem yaygin hem de bizim kendi kampanyamizin adinda geciyor
       (Les Garage de Paris). Ikisi de tam ad olarak yaziliyor. */
    "october's very own",'octobersveryown','canada goose','canadagoose',
    'moose knuckles','mooseknuckles','mackage','herschel','roots canada','lululemon',
    'mejuri','club monaco','clubmonaco','sentaler','jenny bird','jennybird',
    /* 'sorel' TEK BASINA YOK: Sorel-Tracy 35 bin nufuslu bir Quebec sehri ve
       oradaki bir butigin adinda gecerdi -- tam da elemek istemedigimiz musteri.
       Lead'in firma adi "Sorel Canada" oldugu icin marka yine yakalaniyor. */
    'oak + fort','oak and fort','oakandfort','sorel canada','sorelcanada','joe fresh','joefresh',
    'frank and oak','frankandoak','tentree','la senza','lasenza',
    'aldo shoes','aldogroup','dynamite clothing','groupe dynamite',
    'garage clothing','garageclothing','le chateau','lechateau','rw&co','reitmans',
    'aritzia','roots',
    /* Alan adinin kendisi "global brands" diyor -- bir butigin degil, birden
       fazla markayi/magazayi ayni catida isleten bir dagitim/gruba ait imza.
       Lead adi ("Scandal") tek basina zararsiz gorunuyordu, alan adi
       gorulmeden yakalanmazdi (3 Eyl 2026, new_collection kuru kosusu). */
    'globalbrands',
    /* AVM / outlet MERKEZI isletmecileri. Bunlar bir magaza degil, EV SAHIBI:
       mal almiyorlar, kiraya veriyorlar. 30 Agustos 2026'da McArthurGlen Malaga
       ve La Noria Outlet duyuruyu aldi -- ikisi de alisveris merkezi.
       "outlet" KELIMESI TEK BASINA EKLENMEDI: bagimsiz bir off-price magazasi
       da adinda outlet tasiyor ve o gercek musteri (Il Salvagente gibi). Sadece
       merkez isletmecilerinin kendi adlari ve "designer outlet" kalibi. */
    /* ABD kuyumcu zinciri (~50 magaza). Winter 26/27 partisinde duyuruyu aldi --
       ustelik ITALYANCA, cunku lead kaydinda ulkesi Italy yaziyor. Ad tarafinda
       kelime siniri, alan adi tarafinda 5 harf oldugu icin tam eslesme
       (reeds.com), yani "reedsboutique.fr" gibi bir ad elenmiyor. */
    'reeds jewelers','reeds',
    'mcarthurglen','value retail','neinver','sonae sierra','unibail','westfield',
    'simon property','designer outlet','la noria outlet','the mall luxury outlet',
    /* 30 Agustos aksami yeni koleksiyon kuru sayiminda cikan iki outlet KOYU:
       Batavia Stad (Lelystad, NL) ve Freeport (Znojmo, CZ). "freeport" tek
       basina degil -- sehir adi ve serbest bolge terimi; tam adiyla. */
    'batavia stad','bataviastad','freeport fashion outlet','fashion arena prague',
    /* 30 Agustos DE/NL partisinden sizanlar: eschuhe.de CCC/eobuwie grubunun
       Almanya vitrini (zincir); Miinto butik PAZARYERI (alici degil kanal);
       Luisa Cerano kendi-marka etiket. min_brands=2 bunlari elemez -- sitelerinde
       gercekten cok marka var; eksik olan addi. */
    'eschuhe','eobuwie','ccc shoes','miinto','luisa cerano','luisacerano',
    /* PKZ: ~40 magazali Isvicre erkek giyim zinciri; 30 Agustos aksam partisinde
       iki subesi mektup aldi. Ad tarafinda kelime siniri "PKZ Men"i yakalar;
       alan adi tarafi 4 harf altini zaten es gectigi icin pkz.ch'yi ad yakalar. */
    'pkz',
    /* Ayni partide cikan kendi-marka Italyan etiketleri. */
    '120% lino','120percento','carlo pignatelli','malloni','nanan','marlu gioielli',
    'marlugioielli',
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
    // Nordic own-label houses + chains (Oslo/Stockholm/Copenhagen discovery). Same rule as
    // every flagship above: the label's own store buys from its own factory, not from us.
    'acne studios','norrøna','norrona','holzweiler','polarn o. pyret','polarnopyret',
    'b.young','byoung','bytimo','by timo','cathrine hammel','woolland','nøstebarn','nostebarn','woden',
    'lillelam','ganni','norse projects','helly hansen','fjällräven','fjallraven','dressmann',
    'cubus','bik bok','carlings','varner','bestseller','jack and jones','name it','only & sons',
    'partyland','fretex',
    // second-hand / charity chains — same reason as the charities above: donated stock, no buying
    'think twice','uff vintage','uffnorge','myrorna','erikshjälpen','erikshjalpen',
    // Belgian couture house — own label, made-to-measure, not a reseller
    'natan',
    // Adjacent trades that aren't clothing retail at all: dancewear, cobblers/orthopaedic
    // shoemakers, embroidery/print services and fabric shops. They were harvested under
    // shop=clothes|shoes but none of them resells ready-to-wear designer stock.
    'dansebutikk','danseboutique','dansebutik','danceshop','dance shop','tanzschuhe','tanzboutique',
    // Workwear and folk/traditional dress (Vienna sweep): both are clothing retail on paper,
    // neither buys designer ready-to-wear. These terms recur across the whole German market.
    'berufsbekleidung','arbeitskleidung','workwear','del lavoro','abbigliamento da lavoro',
    'trachten','dirndl','lederhosen','original salzburger',
    // Austrian/German own labels found in the Vienna sweep
    'hannes roether','michel mayer','elfenkleid',
    'skomaker','skomager','schuhmacher','cordonnerie','orthopedie','orthopädie','orthopadie',
    'ortopedi','orthopaedic','borduur','stoffen','tissus','stofferie','tejidos',
    // Military surplus and fabric/workwear houses from the Vilnius/Seville sweep
    'armijai','military surplus','vestuario laboral','abbigliamento da lavoro',
    // Own-label houses / monobrand stores: Baltics, Italy, France (Turin/Lyon/Riga sweep)
    'camel active','pako lorente','maison standards','bleuforêt','bleuforet','hartford',
    'heschung','laurence bras','primo emporio',
    // online-only (defensive; shouldn't appear as physical OSM shop nodes anyway)
    'zalando','farfetch','ssense','asos','amazon',
    /* Regional retail GROUPS, franchise operators and brand DISTRIBUTORS. Standing
       operator rule: never contact chains, own-brand sellers, or large distributors.
       These are the ones that reach us as hand-picked addresses rather than through
       discovery -- a Gulf group's "info@" looks like any other boutique address, and
       the name is the only thing that gives it away. A distributor already holds the
       regional rights to the labels we wholesale; they are a competitor in the channel,
       not a customer. */
    'alshaya','al tayer','altayer','chalhoub','apparel group','landmark group',
    'majid al futtaim','bfl group','brands for less','alyasra','etoile group',
    'concept brands','trafalgar luxury','gilbert luxury','al futtaim','rivoli group',
    'liwa trading','azadea','retail arabia','fawaz alhokair','al hokair',
    // Distribution / trading houses: the business model is the disqualifier, not the size.
    'distribution','distributor','distributör','distribütör','distribuidor','distributeur',
    'trading','tradings','import export','wholesale supplier',
    // Own-brand houses seen in hand-curated lists (same rule as the flagships above)
    'hummel','valento','elisabetta franchi',
    // Own-label menswear chains (their stores are supplied by their own factory)
    "d's damat",'dsdamat','ds damat','orka holding','damat tween',
  ];
}
/* PARK EDILMIS / SATILIK alan adi: dukkan degil, satis sayfasi.
 *
 * 1 Eyl 2026: klcollective.com'a mektup gitti ve site taramasi sirket adini
 * "HugeDomains" diye getirdi -- yani alan adi satiliktir, arkasinda dukkan yok.
 * Isaret ZATEN elimizdeydi (tarama adi cekiyor), sadece kimse bakmiyordu.
 * NS/MX kontrolu bunu yakalayamaz: park saglayicilari alan adini gercekten
 * kaydeder ve cogu MX de yayinlar, yani "alan adi yasiyor" gorunur.
 *
 * Yalnizca TANINMIS park saglayicilarinin adlari ve acik "satilik" kaliplari
 * aranir; genel kelime konmaz. "Domain" gecen her adi elemek, gercek bir
 * "Domain Boutique"i sessizce silerdi -- ve sessiz eleme, yanlis gonderimden
 * pahali oldugu icin bu liste bilerek dar. */
function vestra_name_is_parked_domain(string $company): bool {
  $k = strtolower(trim($company));
  if ($k === '') return false;
  /* Sayfa basligi YALNIZCA yer tutucu ise: "Coming Soon" (wer-haus.com, 2 Eyl
     2026), "Under construction". TAM eslesme -- "Coming Soon Concept Store"
     gercek bir ad ve gecmeli (test tutuyor). */
  if (in_array($k, ['coming soon','coming soon...','under construction','site under construction',
                    'index of /','welcome to nginx!','apache2 default page','it works!'], true)) return true;
  foreach ([
    'hugedomains','sedo','afternic','dan.com','undeveloped','namecheap marketplace',
    'godaddy auctions','buy this domain','domain for sale','this domain is for sale',
    /* 1 Eyl 2026: luisaboutique.it'in taranan adi "Domain information
       luisaboutique.it" geldi -- kayit sirketi/park sayfasi basligi. Mektup
       gitmisti. Iki kelimelik TAM kalip: "Domain Boutique" gibi gercek bir ad
       bundan etkilenmiyor (test tutuyor). */
    'domain information','domain info','domain default page','web hosting default',
    'domain name for sale','parked domain','domain parking','future home of',
    'website coming soon','coming soon!','account suspended','bandwidth limit exceeded',
    /* 2 Eyl 2026: velvetmonaco.com'un taranan adi "Domain im Kundenauftrag
       registriert" (Alman kayit sirketinin park sayfasi). Elle okundugu icin
       yakalandi; kalip listeye giriyor ki bir dahakine okunmasa da yakalansin. */
    'domain im kundenauftrag','domain reserviert','diese domain steht zum verkauf',
    /* Ayni gun: vergelioshoes.it -> "Sfera.net Park Page" (Italyan hosting'in park
       sayfasi), yusty.com -> "TopDomainer Search Engine" (alan adi pazari). */
    'park page','topdomainer',
    /* Suresi dolup ELE GECIRILMIS alan adlari: jeffreynewyork.com -> "POKER369",
       nuovum.com -> "PECAH138 Situs Game..." (2 Eyl 2026). Dukkan kapanmis, alan
       adini kumar sitesi almis. Kaliplar OZEL tutuldu ('casino' tek basina bir
       butik adinda gecebilir). */
    'poker369','pecah138','situs game','situs judi','slot gacor','judi online','slot online',
    'domaine en vente','ce domaine est à vendre','dominio in vendita','dominio en venta',
  ] as $needle) {
    /* KELIME SINIRI SART -- duz str_contains bu listeyi de gercek adlarin ICINDE
       buluyor: 'sedo' -> "The Sedona Store". Ayni hata vestra_name_is_blocked'da
       bir kez yasandi (mango -> Mangobay); testi burada da tuttugu icin
       kodlanmadan once yakalandi. */
    if (preg_match('/(?<![a-z0-9])'.preg_quote($needle, '/').'(?![a-z0-9])/i', $k)) return true;
  }
  return false;
}

/* Domain-level twin of vestra_name_is_blocked(). The company name is scraped from the
 * lead's own site and that scrape FAILS in exactly the cases that matter: a bot wall
 * returns "Access to this page has been denied" and a distributor sails through the name
 * check with a company name that is really an error page. The domain is supplied by the
 * operator and cannot fail, so it gets checked too -- alshaya.com is blocked whether or
 * not its homepage answered. Only the registrable label is compared, so a boutique on a
 * shared host (mystore.wixsite.com) is not judged by its host's name. */
function vestra_domain_is_blocked(string $email, string $website=''): bool {
  $labels=[];
  if(($at=strrpos($email,'@'))!==false) $labels[]=substr($email,$at+1);
  if($website!=='') $labels[]=vestra_domain_of($website);
  foreach($labels as $host){
    $host=strtolower(trim((string)$host)); if($host==='') continue;
    $host=preg_replace('/^www\./','',$host);
    // Strip the public suffix so "alshaya.com" and "alshaya.ae" both reduce to "alshaya".
    $core=preg_replace('/\.(com|net|org|co|ae|sa|qa|kw|kr|jp|cn|uk|de|fr|it|es|nl|be|ch|at|cz|pl|ua|dk|se|no|fi|au|us|eu|info|biz|shop|store|fashion)(\.[a-z]{2})?$/','',$host);
    /* Compare with separators removed on BOTH sides. A domain has no spaces, so the
       multi-word entries ("trafalgar luxury", "apparel group") could never match one --
       trafalgarluxurygroup.com sailed past a list that literally names it. */
    $flat=preg_replace('/[^a-z0-9]/','',$core);
    if($flat==='') continue;
    $exact=vestra_blocklist_exact_only();
    foreach(vestra_discover_blocklist() as $b){
      if($b==='') continue;
      $bf=preg_replace('/[^a-z0-9]/','',strtolower($b));
      if($bf==='' || strlen($bf)<4) continue;
      /* KISA girisler SADECE tam eslesir. Alan adinda kelime siniri yok, yani
         alt dizi aramasi kisa marka adlarini baska adlarin icinde buluyor:
         zara -> zaragozamoda.es, puma -> pumaverde.it, fila -> filaticcio.it,
         marshalls -> marshallstreet.co.uk. Hepsi gercek cok markali butik ve
         hepsi sessizce eleniyordu. 6 harf siniri + adi gunluk bir kelime olan
         uzun girisler icin ayri liste; "alshaya", "nordstrom", "trafalgar
         luxury" gibi ozgun adlar alt dizi aramasinda kaliyor, cunku
         "trafalgarluxurygroup.com" tam da oyle yakalaniyor. */
      if(strlen($bf)<=6 || isset($exact[$bf])){ if($flat===$bf) return true; continue; }
      if(str_contains($flat,$bf)) return true;
    }
  }
  return false;
}
/* Zincir adi ayni zamanda sik bir kelime/soyad oldugunda alt dizi eslesmesi
   yanlis pozitif uretiyor; bunlar alan adinin TAMAMINA esit olmali. Sirket
   ADI tarafinda kelime siniri zaten var, orada bir kayip yok. */
function vestra_blocklist_exact_only(): array {
  static $m=null;
  if($m===null){
    $m=[];
    foreach(['marshalls','mango','next retail','sears','kohls',"kohl's",'ross stores',
             'ross dress','courir','snipes','fila','kappa','umbro','next',
             /* 6 harften uzun ama gunluk kelime: alan adinda alt dizi aranirsa
                "dynamiteboutique.it" gibi gercek bir dukkani elerdi. */
             'dynamite','herschel',
             /* 31 Agu 2026 listesinden: kisa ya da baska sozcuklerin icinde
                gecebilen adlar. 'atmos' -> atmosphere/atmosfera, 'kith' -> kithara,
                'lemaire' -> kisi soyadi olabilir, 'nepenthes' bitki adi. */
             'kith','lemaire','nepenthes','siwilai',
             /* 1 Eyl 2026 Avrupa listesi: 6 harften uzun ama baska adlarin
                ICINDE geciyorlar. Sondam ikisini de yakaladi:
                'slowear' -> slowearthvintage.com, 'lardini' -> lardinia.it.
                Ikisi de gercek butik olabilir ve sessizce elenirlerdi. */
             'slowear','lardini'] as $t){
      $m[preg_replace('/[^a-z0-9]/','',strtolower($t))]=true;
    }
  }
  return $m;
}
/* Monobrand rule: a company whose OWN name or domain is a premium label is that label's
 * own operation (flagship, national subsidiary, official distributor) -- it buys from its
 * own factory, never from us. This reads vestra_premium_brandlist() in the direction the
 * list's own comment describes: brand-in-CONTENT means multi-brand boutique (a target),
 * brand-in-NAME means monobrand (skip). Word-boundary matched, because short labels are
 * common substrings -- without it "etro" fires inside "metropolitan" and "autry" inside
 * a surname. */
function vestra_is_monobrand(string $company, string $email='', string $website=''): bool {
  $hay=strtolower(trim($company));
  foreach([$email,$website] as $src){
    if($src==='') continue;
    $host=$src;
    if(($at=strrpos($host,'@'))!==false) $host=substr($host,$at+1);
    $host=strtolower(preg_replace('/^www\./','',(string)vestra_domain_of($host)));
    $host=preg_replace('/\.[a-z.]{2,12}$/','',$host);
    $hay.=' '.str_replace(['-','.','_'],' ',$host);
  }
  if(trim($hay)==='') return false;
  foreach(vestra_premium_brandlist() as $b){
    if($b==='') continue;
    // Also try the space-stripped brand, so "patriziapepe.com" matches "patrizia pepe".
    $alts=[$b]; $flat=str_replace(' ','',$b); if($flat!==$b) $alts[]=$flat;
    foreach($alts as $a){
      if(preg_match('/(?<!\p{L})'.preg_quote($a,'/').'(?!\p{L})/iu',$hay)) return true;
    }
  }
  return false;
}
/* One gate for the SEND path, whatever added the lead. Checks the scraped company name,
 * the recorded brand, AND the address's own domain -- any one of them matching is enough.
 * Callers should use this rather than vestra_name_is_blocked() directly: the name alone
 * was the hole that let a batch of Gulf retail groups through a hand-curated send. */
function vestra_lead_is_blocked(array $lead): bool {
  $co=(string)($lead['company'] ?? ''); $em=(string)($lead['email'] ?? ''); $ws=(string)($lead['website'] ?? '');
  if(vestra_name_is_blocked($co, (string)($lead['brand'] ?? ''))) return true;
  if(vestra_domain_is_blocked($em, $ws)) return true;
  return vestra_is_monobrand($co, $em, $ws);
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
    if($b==='') continue;
    /* KELIME SINIRI SART. Duz str_contains, listedeki kisa adlari baska
       kelimelerin ICINDE buluyordu: "mango" -> Mangobay Boutique, "fila" ->
       Filaticcio Milano, "zara" -> Zaragoza Moda, "next" -> Next Door Concept.
       Hepsi gercek cok markali butik ve hepsi sessizce eleniyordu -- kimsenin
       fark etmedigi tek hata turu bu. (vestra_is_monobrand ayni sebeple zaten
       sinir kullaniyordu; bu fonksiyon geride kalmisti.)
       \b yerine alfanumerik ileri/geri bakis: listede '&' ve kesme isareti
       tasiyan girisler var ve \b onlarin ucunda beklenmedik davraniyor. */
    $re='/(?<![a-z0-9])'.preg_quote($b,'/').'(?![a-z0-9])/i';
    if(($k!=='' && preg_match($re,$k)) || ($b2!=='' && preg_match($re,$b2))) return true;
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
  $body.="\n\n—\n".($senderName!==''?$senderName.' via VESTRA (operated by Acerasoft LLC)':'VESTRA is operated by Acerasoft LLC').
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
 *   'button_alt' => ['label'=>'Decline','url'=>'https://...'] ikincil baglanti, ana
 *                dugmenin ALTINDA ve sessiz. Karsi teklif mektubu icin eklendi:
 *                tek dugme yalnizca "kabul et" diyordu, reddetmek isteyen aliciya
 *                mektupta hicbir yol yoktu. Iki esit agirlikta dugme koymak da
 *                yanlis olurdu -- karar alicinin, vurgu degil.
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
  /* $bodyPlain is exactly that: plain text. Escaping and paragraphing it here is right
     for every notification the platform sends, and wrong for the one case where the
     caller has already composed a letter in HTML -- markup passed through $bodyPlain was
     escaped and the reader got the tags printed at them as text. $opts['html'] lets such
     a caller hand over the finished main block. $bodyPlain still carries the letter as
     text, because it is what the text/plain part is built from: a client that refuses
     HTML must not receive an empty message. */
  $mainHtml=(isset($opts['html']) && trim((string)$opts['html'])!=='')
    ? (string)$opts['html']
    : $renderParas($main,'margin:0 0 18px;line-height:1.65;color:#3a3428;font-size:15px');
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
    $altHtml='';
    if(!empty($opts['button_alt']['url'])){
      $altHtml='<div style="margin-top:12px">'
        .'<a href="'.htmlspecialchars((string)$opts['button_alt']['url'],ENT_QUOTES,'UTF-8').'" '
        .'style="color:#8a7a5e;text-decoration:underline;font-size:13px">'
        .htmlspecialchars((string)($opts['button_alt']['label'] ?? 'Other option'),ENT_QUOTES,'UTF-8').'</a></div>';
    }
    $buttonHtml='<div style="padding:4px 28px 28px;text-align:center">'
      .'<a href="'.htmlspecialchars((string)$opts['button']['url'],ENT_QUOTES,'UTF-8').'" '
      .'style="display:inline-block;background:#14110c;color:#d8bd86;padding:13px 34px;border-radius:8px;'
      .'text-decoration:none;font-weight:700;font-size:14px;letter-spacing:.02em">'
      .htmlspecialchars($btnLabel,ENT_QUOTES,'UTF-8').'</a>'
      .$altHtml.'</div>';
  }

  /* Optional voucher coupon ('voucher' => ['kicker','amount','caption','code_label','code',
     'expiry_label','expiry']). The welcome voucher used to render through the generic
     label/value rows, which put the whole offer on one grey line reading
     "Voucher code    VES-4KQ2-8ZTF" -- the same furniture as a plan change or an escrow
     release. The one thing this mail exists to carry is the code, so it gets a coupon:
     the percentage as the hero, the code in a ruled field beneath it.

     Table-based with bgcolor attributes as well as CSS, because Outlook renders through
     Word and drops background-image and most box styling on a <div>; a dashed border and
     a solid bgcolor are the two decorations that survive everywhere. No web font -- the
     code is set in the client's monospace so the character shapes stay unambiguous when
     someone reads it off a screen to type it in. */
  $voucherHtml='';
  if(!empty($opts['voucher']['code'])){
    $v=$opts['voucher'];
    $esc=fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');
    $kick=(string)($v['kicker']??''); $amount=(string)($v['amount']??'');
    $cap=(string)($v['caption']??''); $expL=(string)($v['expiry_label']??'');
    $exp=(string)($v['expiry']??''); $codeL=(string)($v['code_label']??'');
    $voucherHtml='<div style="padding:6px 28px 22px">'
      .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#14110c"'
      .' style="border-collapse:separate;background:#14110c;border:1px solid #a97f2c;border-radius:12px">'
      .'<tr><td align="center" style="padding:26px 22px 24px">'
      .($kick!==''?'<div style="color:#a97f2c;font-size:10.5px;font-weight:700;letter-spacing:.26em;text-transform:uppercase;margin:0 0 14px">'.$esc($kick).'</div>':'')
      .($amount!==''?'<div style="color:#e8cf95;font-family:Georgia,\'Times New Roman\',serif;font-size:52px;line-height:1;font-weight:700;margin:0 0 6px">'.$esc($amount).'</div>':'')
      .($cap!==''?'<div style="color:#b9b0a0;font-size:13px;line-height:1.5;margin:0 0 20px">'.$esc($cap).'</div>':'')
      /* the code field: light ticket on the dark panel, ruled rather than filled, so it
         reads as something to be torn off and used */
      .'<table role="presentation" cellpadding="0" cellspacing="0" border="0" bgcolor="#f4ecd8"'
      .' style="border-collapse:separate;background:#f4ecd8;border:2px dashed #a97f2c;border-radius:9px">'
      .'<tr><td align="center" style="padding:13px 26px">'
      .($codeL!==''?'<div style="color:#8a6d1f;font-size:9.5px;font-weight:700;letter-spacing:.2em;text-transform:uppercase;margin:0 0 5px">'.$esc($codeL).'</div>':'')
      .'<div style="color:#14110c;font-family:\'Courier New\',Courier,monospace;font-size:21px;font-weight:700;letter-spacing:.14em;line-height:1.2">'.$esc($v['code']).'</div>'
      .'</td></tr></table>'
      .($exp!==''?'<div style="color:#8a8272;font-size:11.5px;margin:16px 0 0">'.$esc(trim($expL.' '.$exp)).'</div>':'')
      .'</td></tr></table></div>';
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

  /* Urun fotograf seridi ('shots' => [['img'=>,'label'=>,'url'=>], ..]).
     Table tabanli: Outlook flex/inline-block hizalamasini yok sayiyor. Her karenin
     ALTINDA marka adi METIN olarak duruyor -- cogu istemci uzak gorselleri varsayilan
     olarak engelliyor, gorsel hic yuklenmese bile serit anlamli kalmali. */
  $shotsHtml='';
  if(!empty($opts['shots']) && is_array($opts['shots'])){
    $cells=[];
    foreach($opts['shots'] as $s){
      $img=(string)($s['img']??''); $lab=(string)($s['label']??''); $url=(string)($s['url']??'');
      if($img==='') continue;
      $inner='<img src="'.htmlspecialchars($img,ENT_QUOTES,'UTF-8').'" width="160" alt="'.htmlspecialchars($lab,ENT_QUOTES,'UTF-8').'"'
            .' style="display:block;width:100%;max-width:160px;height:auto;border:0;border-radius:8px;border:1px solid #e6e0d5">'
            .'<div style="color:#5c5449;font-size:11px;letter-spacing:.12em;text-transform:uppercase;text-align:center;margin:6px 0 0">'
            .htmlspecialchars($lab,ENT_QUOTES,'UTF-8').'</div>';
      $cells[]=$url!==''
        ? '<a href="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'" style="text-decoration:none">'.$inner.'</a>'
        : $inner;
    }
    if($cells){
      /* 3 hucre/satir, birden fazla <tr>: eskiden TEK satirda kacsa hepsi
         yan yana diziliyordu -- 3'ten fazla oldugunda konteynerin disina
         tasiyordu (hicbir sarma yoktu). array_chunk ile satirlara boluyoruz. */
      $rowsHtmlShots='';
      foreach(array_chunk($cells,3) as $rowCells){
        $tds=''; foreach($rowCells as $c){ $tds.='<td valign="top" width="33%" style="padding:4px">'.$c.'</td>'; }
        $rowsHtmlShots.='<tr>'.$tds.'</tr>';
      }
      $shotsHtml='<div style="padding:0 23px 18px">'
        .'<div style="color:#8a6d1f;font-size:11px;font-weight:700;letter-spacing:.2em;text-transform:uppercase;text-align:center;margin:2px 0 12px">'
        .htmlspecialchars((string)($opts['shots_title']??'From the current selection'),ENT_QUOTES,'UTF-8').'</div>'
        .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse">'.$rowsHtmlShots.'</table>'
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
    .$voucherHtml
    .$rowsHtml
    .$brandsHtml
    .$shotsHtml
    .$downloadsHtml
    .$buttonHtml
    .(!empty($opts['signature']) && is_array($opts['signature']) ? vestra_email_signature_html($opts['signature']) : '')
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
/* Havuz (grup alimi) kampanyasi — mevcut kampanyanin UZERINE eklenir, yerine
   gecmez. Ayri bir sablon yazmak yerine ek olarak kuruldu: kampanyanin 14 dilli
   govdesi, marka duvari ve line-sheet listesi aynen kalsin, sadece acik havuzlar
   eklensin.

   $pools=false iken HICBIR SEY degismiyor -- bu fonksiyonu cagiran diger yerler
   (onizleme, tekil gonderimler) eskisi gibi calisir. Havuz icerigi yalnizca
   acikca istendiginde giriyor.

   Rakamlar CANLI havuz verisinden okunuyor, mektuba elle yazilmiyor: bir havuzun
   fiyati ya da son tarihi degistiginde mektup kendiliginde dogru kalir. Elle
   yazilsaydi, bugun uc kez degisen havuz ayarlarindan sonra mektup coktan yanlis
   olurdu. */
function vestra_campaign_preview(string $company='', string $lang='en', string $featureCat='', bool $pools=false): array {
  [$subject,$body,$opts] = vestra_campaign_preview_base($company,$lang,$featureCat);
  if(!$pools || !function_exists('vestra_group_pools')) return [$subject,$body,$opts];

  $open=[];
  foreach(vestra_group_pools() as $gp){
    if(($gp['_status']??'')!=='open') continue;
    $open[]=$gp;
  }
  if(!$open) return [$subject,$body,$opts];   // acik havuz yoksa mektup degismez

  $L=[
    'en'=>['h'=>'Open group buys','line'=>'Join other verified boutiques on one order and the wholesale price unlocks for everyone.',
           'unit'=>'per piece','min'=>'from','dep'=>'deposit','until'=>'closes','cta'=>'See open group buys'],
    'fr'=>['h'=>'Achats groupés ouverts','line'=>'Rejoignez d\'autres boutiques vérifiées sur une même commande et le prix de gros se débloque pour tous.',
           'unit'=>'la pièce','min'=>'à partir de','dep'=>'acompte','until'=>'clôture','cta'=>'Voir les achats groupés'],
    'cs'=>['h'=>'Otevřené skupinové nákupy','line'=>'Připojte se k dalším ověřeným buticích na jedné objednávce a velkoobchodní cena se odemkne pro všechny.',
           'unit'=>'za kus','min'=>'od','dep'=>'záloha','until'=>'uzavírá se','cta'=>'Zobrazit skupinové nákupy'],
  ];
  $t=$L[$lang] ?? $L['en'];

  /* Havuz rakamlari SADECE govdedeki paragrafta duruyor. Bir ara ustteki bilgi
     kutusuna da eklenmisti; ayni fiyat/minimum/kapora iki kez goruntuleniyordu ve
     kutu "orijinallik / odeme / minimum" gibi guven bilgileri icin -- oraya urun
     teklifi koymak kutunun isini bulandiriyor. */
  $lines=[];
  foreach($open as $gp){
    /* Havuz basligi urun adindan geliyor ve marka icermeyebiliyor: Lacoste havuzu
       mektupta "Basic Crew Neck T-Shirt" diye cikiyordu -- alici bunun Lacoste
       oldugunu anlamiyordu. Marka adi basta gecmiyorsa oneklenir. */
    $title = function_exists('vestra_group_title') ? vestra_group_title($gp) : (string)($gp['name']??'');
    $brand = trim((string)($gp['brand']??''));
    if($brand!=='' && stripos($title,$brand)===false) $title=$brand.' '.$title;
    $minQ  = function_exists('vestra_group_min_qty') ? vestra_group_min_qty($gp) : (int)($gp['moq']??1);
    $dep   = function_exists('pool_deposit_pct') ? pool_deposit_pct($gp) : 0.0;
    $depTxt= $dep>0 ? ' · '.rtrim(rtrim(number_format($dep,1),'0'),'.').'% '.$t['dep'] : '';
    $val   = '€'.number_format((float)$gp['_gprice'],2).' '.$t['unit']
           .' · '.$t['min'].' '.number_format($minQ).' '.(string)($gp['unit']??'pc').$depTxt;
    $lines[]='· '.$title.' — '.$val.' · '.$t['until'].' '.date('d.m.Y', strtotime($gp['_deadline']))
            .'  https://vestrasales.com/group?id='.rawurlencode((string)$gp['id']);
  }
  $opts['button']=['label'=>$t['cta'],'url'=>'https://vestrasales.com/groups'];

  /* Marka duvari yer darligindan ilk 10 markayi gosteriyor; geri kalanlar mektupta
     hic gecmiyordu. Kalan markalar line-sheet listesinin ALTINA ekleniyor -- oradaki
     markalar zaten tiklanabilir link, digerleri de ayni bicimde giriyor. Govdeye
     ayrica cumle olarak yazmadim: HTML'de ayni bilgi iki kez gorunurdu.

     Liste SADECE onayli canli ilanlardan cikariliyor, vestra_products()'tan degil:
     o fonksiyon demo urunleri ve seed katalogu da dondurur, yani satilamayacak bir
     markayi mektupta "mevcut" diye saymis olurduk. */
  if(function_exists('vestra_live_listings') && !empty($opts['downloads']['items'])){
    $featured=[];
    foreach((array)($opts['brands']??[]) as $b) $featured[strtolower(trim((string)$b))]=true;
    $restCount=[];
    foreach(vestra_live_listings() as $lp){
      $b=trim((string)($lp['brand']??'')); if($b==='') continue;
      if(isset($featured[strtolower($b)])) continue;
      $restCount[$b]=($restCount[$b]??0)+1;
    }
    arsort($restCount);
    foreach(array_keys($restCount) as $b){
      $opts['downloads']['items'][]=['label'=>$b,
        'url'=>'https://vestrasales.com/catalog?brand='.rawurlencode($b)];
    }
  }

  /* Havuz metni MEKTUBUN GOVDESINE giriyor, kunyeye degil. vestra_html_email()
     govdeyi "\n\n—\n" ayracindan ikiye boluyor: ustu mektup (15px, koyu), alti
     kunye (12px, gri, abonelikten cikma satiri). Metni govdenin sonuna eklemek
     onu ayracin ALTINA dusuruyordu -- yani kampanyanin asil mesaji, abonelikten
     cikma linkinin altinda kunye punto­sunda cikiyordu. Ayrac varsa oncesine
     ekleniyor, yoksa sona.

     Imzadan sonra, "P.S." konumunda duruyor: imzadan ONCE koymak icin her dilin
     kapanis cumlesini tanimak gerekirdi (14 dil), o da kirilgan. Rakamlar zaten
     ustteki kutuda ve dugmede de goruntuleniyor. */
  $poolTxt="\n\n".$t['h']."\n".$t['line']."\n".implode("\n",$lines);
  $sep="\n\n—\n";
  $at=strpos($body,$sep);
  $body = $at===false
    ? rtrim($body).$poolTxt."\n"
    : rtrim(substr($body,0,$at)).$poolTxt.substr($body,$at);

  return [$subject,$body,$opts];
}

function vestra_campaign_preview_base(string $company='', string $lang='en', string $featureCat=''): array {
  $counts=[]; $brands=[]; $shots=[];
  if(function_exists('vestra_products')){
    $all=vestra_products();
    foreach($all as $p){ $b=trim((string)($p['brand']??'')); if($b==='') continue; $counts[$b]=($counts[$b]??0)+1; }
    arsort($counts);
    $brands=array_slice(array_keys($counts),0,10);

    /* Denim ayri tutuluyor. Sirf urun ADEDINE gore ilk 10'u alinca jeans hic
       gorunmeyebiliyor -- katalogda tisort/sweat adedi denimden cok fazla, oysa
       denim satisi en yuksek sepet degerine sahip kalem. En az iki denim evi
       listeye garanti ediliyor; zaten ilk 10'daysa hicbir sey degismiyor. */
    $denimBrands=[];
    foreach($all as $p){
      $cat=strtolower((string)($p['cat']??''));
      if(!str_contains($cat,'jean') && !str_contains($cat,'denim')) continue;
      $b=trim((string)($p['brand']??'')); if($b==='') continue;
      $denimBrands[$b]=($denimBrands[$b]??0)+1;
    }
    arsort($denimBrands);
    $forced=array_slice(array_keys($denimBrands),0,2);
    foreach($forced as $b){
      if(!in_array($b,$brands,true)){ array_pop($brands); array_unshift($brands,$b); }
    }

    /* Operator tek seferlik bir gonderim icin bir ya da birden fazla kategoriyi
       one cikarmak isteyebilir (orn. Ibiza/Mallorca butiklerine mayo/short/bikini
       -- virgulle ayrilmis kisa anahtar kelimeler, "swim,short" gibi: "swim" hem
       "Swim Shorts" hem "Women's Swimwear" (bikini) icinde geciyor, "short" hem
       "Shorts" hem "Jeans Shorts" icinde -- tek tek tam kategori adi gerekmiyor.
       $featureCat bos oldugunda (butun diger cagrilar) hicbir sey degismez. */
    $featureNeedles = array_values(array_filter(array_map(
      fn($s) => strtolower(trim($s)), explode(',', $featureCat)
    ), fn($s) => $s !== ''));
    $isFeatCat = function(string $cat) use ($featureNeedles): bool {
      foreach($featureNeedles as $needle) if(str_contains($cat,$needle)) return true;
      return false;
    };
    if($featureNeedles){
      $featBrands=[];
      foreach($all as $p){
        $cat=strtolower((string)($p['cat']??''));
        if(!$isFeatCat($cat)) continue;
        $b=trim((string)($p['brand']??'')); if($b==='') continue;
        $featBrands[$b]=($featBrands[$b]??0)+1;
      }
      arsort($featBrands);
      foreach(array_slice(array_keys($featBrands),0,3) as $b){
        if(!in_array($b,$brands,true)){ array_pop($brands); array_unshift($brands,$b); }
      }
    }

    /* Fotograf seridi: istenen kategori(ler) (varsa) once, sonra denim, sonra
       genel. Birden fazla feature kategorisi varsa serit 4'e cikiyor (2 feat +
       denim + genel) ki hicbiri digerini tamamen disarida birakmasin.
       Cold e-postada urun gormek, marka adi okumaktan cok daha ikna edici.
       Gorseller katalog sayfasindaki ile ayni -- fiyat yok, o yuzden uye olmayan
       birine gostermekte sakinca yok. */
    /* Operatorun elle "bunu da goster" dedigi 2 urun serit basina sabitleniyor.
       Marka'lari $seen'e once yaziyoruz ki asagidaki otomatik dongu ayni markadan
       baska bir parca secip yerlerini almasin. */
    $seen=['DSQUARED2'=>true,'BALMAIN'=>true];
    /* Operator istegiyle: feature-kategori yokken serit T-Shirts / Hoodies &
       Sweatshirts'ten marka basina 1 parca ile 12'ye kadar doluyor (2 sabit +
       10 apparel -- katalogda tam 10 farkli marka var, o yuzden baska bir
       pass'e gerek yok; katalog degisirse serit sadece daha kisa doldurur,
       ilgisiz kategoriden tamamlama YAPILMAZ). */
    $shotLimit  = $featureNeedles ? 4 : 12;
    $featShots  = 0; $featCap = 2;   // en fazla 2 kare feature kategoriye ayrilir,
                                      // gerisi denim + genele kaliyor -- tek kategori
                                      // butun seridi kaplamasin diye.
    $passes = $featureNeedles ? ['feat','denim','other'] : ['apparel'];
    foreach($passes as $pass){
      foreach($all as $p){
        if(count($shots)>=$shotLimit) break 2;
        if($pass==='feat' && $featShots>=$featCap) break;
        $cat=strtolower((string)($p['cat']??''));
        $isDenim=str_contains($cat,'jean')||str_contains($cat,'denim');
        $isFeat=$isFeatCat($cat);
        $isApparel=str_contains($cat,'t-shirt')||str_contains($cat,'hoodie')||str_contains($cat,'sweatshirt');
        if($pass==='feat'    && !$isFeat) continue;
        if($pass==='apparel' && !$isApparel) continue;
        if($pass==='denim'   && (!$isDenim || $isFeat)) continue;
        if($pass==='other'   && ($isDenim || $isFeat)) continue;
        $b=trim((string)($p['brand']??'')); if($b===''||isset($seen[$b])) continue;
        $imgs=$p['images']??[]; $img=is_array($imgs)&&$imgs?(string)$imgs[0]:'';
        if($img==='') continue;
        if(!preg_match('#^https?://#i',$img)) $img='https://vestrasales.com'.(str_starts_with($img,'/')?'':'/').$img;
        $seen[$b]=true;
        if($pass==='feat') $featShots++;
        $shots[]=['img'=>$img,'label'=>$b,
                  'url'=>'https://vestrasales.com/catalog?brand='.rawurlencode($b)];
      }
    }
    $forcedShots=[
      ['id'=>'blm-bkbga0700116','label'=>'BALMAIN'],
      ['id'=>'dsq-s74lb1026','label'=>'DSQUARED2'],
    ];
    foreach(array_reverse($forcedShots) as $f){
      foreach($all as $p){
        if((string)($p['id']??'')!==$f['id']) continue;
        $imgs=$p['images']??[]; $img=is_array($imgs)&&$imgs?(string)$imgs[0]:'';
        if($img==='') break;
        if(!preg_match('#^https?://#i',$img)) $img='https://vestrasales.com'.(str_starts_with($img,'/')?'':'/').$img;
        array_unshift($shots,['img'=>$img,'label'=>$f['label'],
                               'url'=>'https://vestrasales.com/catalog?brand='.rawurlencode($f['label'])]);
        break;
      }
    }
  }
  $downloads=[];
  foreach($brands as $b){ $downloads[]=['label'=>$b,'url'=>'https://vestrasales.com/catalog?brand='.rawurlencode($b)]; }
  if(!$downloads) $downloads[]=['label'=>'Full selection','url'=>'https://vestrasales.com/catalog'];

  // Fotograf seridi basligi, alicinin dilinde.
  $shotsTitle=[
    'nl'=>'Uit de actuele selectie','de'=>'Aus der aktuellen Auswahl','fr'=>'De la sélection actuelle',
    'it'=>'Dalla selezione attuale','es'=>'De la selección actual','pt'=>'Da seleção atual',
    'cs'=>'Z aktuální nabídky','pl'=>'Z aktualnej oferty','el'=>'Από την τρέχουσα συλλογή',
    'ru'=>'Из текущей подборки','ja'=>'現在のセレクションより','ko'=>'현재 셀렉션에서','az'=>'Cari seçimdən',
  ][$lang] ?? 'From the current selection';

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
      "Les Garage de Paris via VESTRA (beheerd door Acerasoft LLC). Eenmalig zakelijk bericht — uw winkel is geïdentificeerd als mogelijke premium handelspartner.",
      "Direct uitschrijven: https://vestrasales.com/lead-unsubscribe",
    ]);
    $opts=[
      'hero'=>['kicker'=>'Authentieke designer groothandel','title'=>'De merken waar uw klanten naar vragen — tegen handelsvoorwaarden.'],
      'brands'=>$brands,
      'brands_title'=>'Uitgelichte merken',
      'brands_hint'=>'Tik op een merk voor de line-sheet',
      'badge'=>'KYC-geverifieerd · echtheid gecontroleerd · escrow-beschermd',
      'shots'=>$shots,
      'shots_title'=>$shotsTitle,
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
      "Les Garage de Paris via VESTRA (betrieben von Acerasoft LLC). Einmalige geschäftliche Nachricht — Ihr Geschäft wurde als potenzieller Premium-Handelspartner identifiziert.",
      "Sofort abmelden: https://vestrasales.com/lead-unsubscribe",
    ]);
    $opts=[
      'hero'=>['kicker'=>'Authentische Designermode im Großhandel','title'=>'Die Häuser, nach denen Ihre Kundschaft fragt — zu Handelskonditionen.'],
      'brands'=>$brands,
      'brands_title'=>'Ausgewählte Häuser',
      'brands_hint'=>'Tippen Sie auf ein Haus für das Line-Sheet',
      'badge'=>'KYC-verifiziert · Echtheit geprüft · Escrow-geschützt',
      'shots'=>$shots,
      'shots_title'=>$shotsTitle,
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
      "Les Garage de Paris via VESTRA (exploité par Acerasoft LLC). Message commercial unique — votre boutique a été identifiée comme partenaire commercial premium potentiel.",
      "Se désinscrire immédiatement : https://vestrasales.com/lead-unsubscribe",
    ]);
    $opts=[
      'hero'=>['kicker'=>'Mode de créateurs authentique en gros','title'=>'Les maisons que vos clients demandent — à des conditions de gros.'],
      'brands'=>$brands,
      'brands_title'=>'Maisons en vedette',
      'brands_hint'=>'Touchez une maison pour ouvrir son line-sheet',
      'badge'=>'Vérifié KYC · authenticité contrôlée · protégé par séquestre',
      'shots'=>$shots,
      'shots_title'=>$shotsTitle,
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
      "Les Garage de Paris via VESTRA (gestito da Acerasoft LLC). Messaggio commerciale una tantum — il vostro negozio è stato identificato come potenziale partner commerciale premium.",
      "Annulla l'iscrizione subito: https://vestrasales.com/lead-unsubscribe",
    ]);
    $opts=[
      'hero'=>['kicker'=>"Moda di design autentica all'ingrosso",'title'=>'Le maison che i vostri clienti richiedono — a condizioni commerciali.'],
      'brands'=>$brands,
      'brands_title'=>'Maison in evidenza',
      'brands_hint'=>'Toccare una maison per aprire il line-sheet',
      'badge'=>'Verificato KYC · autenticità controllata · protetto da escrow',
      'shots'=>$shots,
      'shots_title'=>$shotsTitle,
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
      "Les Garage de Paris via VESTRA (operado pela Acerasoft LLC). Mensagem comercial única — a sua loja foi identificada como potencial parceiro comercial premium.",
      "Cancelar subscrição imediatamente: https://vestrasales.com/lead-unsubscribe",
    ]);
    $opts=[
      'hero'=>['kicker'=>'Moda de grife autêntica por atacado','title'=>'As grifes que os seus clientes pedem — em condições de atacado.'],
      'brands'=>$brands,
      'brands_title'=>'Marcas em destaque',
      'brands_hint'=>'Toque numa marca para abrir o line-sheet',
      'badge'=>'Verificado por KYC · autenticidade verificada · protegido por escrow',
      'shots'=>$shots,
      'shots_title'=>$shotsTitle,
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
      "Les Garage de Paris via VESTRA (provozuje Acerasoft LLC). Jednorázová obchodní zpráva — váš obchod byl identifikován jako potenciální prémiový obchodní partner.",
      "Okamžité odhlášení: https://vestrasales.com/lead-unsubscribe",
    ]);
    $opts=[
      'hero'=>['kicker'=>'Autentická značková móda za velkoobchodní ceny','title'=>'Značky, o které vaši zákazníci žádají — za velkoobchodních podmínek.'],
      'brands'=>$brands,
      'brands_title'=>'Vybrané značky',
      'brands_hint'=>'Klepnutím na značku otevřete ceník',
      'badge'=>'Ověřeno KYC · pravost kontrolována · chráněno escrow',
      'shots'=>$shots,
      'shots_title'=>$shotsTitle,
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
      "Les Garage de Paris via VESTRA (prowadzone przez Acerasoft LLC). Jednorazowa wiadomość biznesowa — Twój sklep został zidentyfikowany jako potencjalny partner handlowy premium.",
      "Natychmiastowa rezygnacja z subskrypcji: https://vestrasales.com/lead-unsubscribe",
    ]);
    $opts=[
      'hero'=>['kicker'=>'Autentyczna moda projektantów w cenach hurtowych','title'=>'Marki, o które pytają Twoi klienci — na warunkach hurtowych.'],
      'brands'=>$brands,
      'brands_title'=>'Wyróżnione marki',
      'brands_hint'=>'Dotknij marki, aby otworzyć jej line-sheet',
      'badge'=>'Zweryfikowano KYC · autentyczność sprawdzona · chronione escrow',
      'shots'=>$shots,
      'shots_title'=>$shotsTitle,
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
      "Les Garage de Paris via VESTRA (operado por Acerasoft LLC). Mensaje comercial único — su tienda ha sido identificada como potencial socio comercial premium.",
      "Cancelar suscripción al instante: https://vestrasales.com/lead-unsubscribe",
    ]);
    $opts=[
      'hero'=>['kicker'=>'Moda de diseño auténtica al por mayor','title'=>'Las firmas que sus clientes piden — en condiciones mayoristas.'],
      'brands'=>$brands,
      'brands_title'=>'Firmas destacadas',
      'brands_hint'=>'Toque una firma para abrir su line-sheet',
      'badge'=>'Verificado KYC · autenticidad comprobada · protegido por depósito en garantía',
      'shots'=>$shots,
      'shots_title'=>$shotsTitle,
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
      "Les Garage de Paris via VESTRA (λειτουργεί από την Acerasoft LLC). Μοναδικό εμπορικό μήνυμα — το κατάστημά σας αναγνωρίστηκε ως πιθανός premium εμπορικός συνεργάτης.",
      "Άμεση διαγραφή: https://vestrasales.com/lead-unsubscribe",
    ]);
    $opts=[
      'hero'=>['kicker'=>'Αυθεντική σχεδιαστική μόδα χονδρικής','title'=>'Οι οίκοι που ζητούν οι πελάτες σας — με όρους χονδρικής.'],
      'brands'=>$brands,
      'brands_title'=>'Επιλεγμένοι οίκοι',
      'brands_hint'=>'Πατήστε σε έναν οίκο για να ανοίξετε το line-sheet',
      'badge'=>'Επαληθευμένο KYC · ελεγμένη αυθεντικότητα · προστασία escrow',
      'shots'=>$shots,
      'shots_title'=>$shotsTitle,
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

  if($lang==='ru'){
    $subject='Les Garage de Paris × VESTRA — подлинная дизайнерская мода для оптовых закупок';
    $body=implode("\n",[
      $company!=='' ? "Здравствуйте — сообщение для {$company}." : "Здравствуйте,",
      "",
      "Les Garage de Paris представляет подлинную дизайнерскую моду мультибрендовым бутикам через VESTRA — B2B-маркетплейс с KYC-верификацией для профессиональных байеров.",
      "",
      "Мы поставляем именно те дома моды, которые ваши клиенты уже просят по имени — 100% подлинность, проверка при доставке, прозрачные условия по счетам.",
      "",
      "Актуальная подборка приведена ниже в виде готовых прайс-листов Excel. Оптовые цены доступны только верифицированным партнёрам — зарегистрируйтесь один раз (бесплатно), и все цены откроются мгновенно.",
      "",
      "Сообщите свой микс брендов, и я подберу подборку для вашего магазина.",
      "",
      "С уважением,",
      "Les Garage de Paris · via VESTRA",
      "",
      "—",
      "Les Garage de Paris via VESTRA (управляется компанией Acerasoft LLC). Разовое деловое сообщение — ваш магазин был отмечен как потенциальный премиальный торговый партнёр.",
      "Отписаться мгновенно: https://vestrasales.com/lead-unsubscribe",
    ]);
    $opts=[
      'hero'=>['kicker'=>'Подлинная дизайнерская мода оптом','title'=>'Дома моды, которые уже просят ваши клиенты — на оптовых условиях.'],
      'brands'=>$brands,
      'brands_title'=>'Избранные дома',
      'brands_hint'=>'Нажмите на бренд, чтобы открыть прайс-лист',
      'badge'=>'KYC-верификация · проверка подлинности · защита сделки через эскроу',
      'shots'=>$shots,
      'shots_title'=>$shotsTitle,
      'downloads'=>['title'=>'Прайс-листы — скачать (Excel)','items'=>$downloads],
      'rows'=>[
        ['label'=>'Подлинность','value'=>'Проверяется при доставке'],
        ['label'=>'Оплата','value'=>'Счёт с защитой эскроу'],
        ['label'=>'Минимальный заказ','value'=>'Низкий — подходит для капсульных коллекций','strong'=>true],
      ],
      'button'=>['label'=>'Зарегистрироваться для оптовых цен','url'=>'https://vestrasales.com/register'],
    ];
    return [$subject,$body,$opts];
  }

  if($lang==='ja'){
    $subject='Les Garage de Paris × VESTRA — 本物のデザイナーズブランドを卸価格で';
    $body=implode("\n",[
      $company!=='' ? "{$company} 様、ご案内です。" : "こんにちは、",
      "",
      "Les Garage de Paris は、KYC認証済みのB2Bマーケットプレイス VESTRA を通じて、マルチブランドブティック向けに本物のデザイナーズファッションを卸価格で提供しています。",
      "",
      "お客様が指名で求めるブランドを、100%正規品・配送時の真贋確認付き・明確な請求条件でお届けします。",
      "",
      "現在のセレクションは、以下からすぐに開けるExcelラインシートでご覧いただけます。卸価格は認証済みパートナー限定です — 一度ご登録いただければ(無料)、すべての価格がすぐに表示されます。",
      "",
      "お取り扱いブランドの傾向をお知らせいただければ、貴店向けのセレクションをご提案します。",
      "",
      "よろしくお願いいたします、",
      "Les Garage de Paris · via VESTRA",
      "",
      "—",
      "Les Garage de Paris via VESTRA(運営: Acerasoft LLC)。一度限りのビジネスメールです — 貴店はプレミアム取引先候補として選ばれました。",
      "配信停止はこちら: https://vestrasales.com/lead-unsubscribe",
    ]);
    $opts=[
      'hero'=>['kicker'=>'本物のデザイナーズ卸売','title'=>'お客様が指名で求めるブランドを、卸条件で。'],
      'brands'=>$brands,
      'brands_title'=>'注目のブランド',
      'brands_hint'=>'ブランドをタップするとラインシートが開きます',
      'badge'=>'KYC認証済み · 真贋確認済み · エスクロー決済保護',
      'shots'=>$shots,
      'shots_title'=>$shotsTitle,
      'downloads'=>['title'=>'ラインシート — Excelでダウンロード','items'=>$downloads],
      'rows'=>[
        ['label'=>'真贋','value'=>'配送時に確認'],
        ['label'=>'お支払い','value'=>'エスクロー保護付き請求'],
        ['label'=>'最小ロット','value'=>'少量から対応','strong'=>true],
      ],
      'button'=>['label'=>'卸価格を見るには登録','url'=>'https://vestrasales.com/register'],
    ];
    return [$subject,$body,$opts];
  }

  if($lang==='ko'){
    $subject='Les Garage de Paris × VESTRA — 정품 디자이너 브랜드를 도매가로';
    $body=implode("\n",[
      $company!=='' ? "안녕하세요 — {$company} 님께 드리는 메시지입니다." : "안녕하세요,",
      "",
      "Les Garage de Paris는 KYC 인증 B2B 마켓플레이스 VESTRA를 통해 멀티브랜드 편집숍에 정품 디자이너 패션을 도매가로 공급합니다.",
      "",
      "고객이 이름으로 찾는 바로 그 브랜드를 100% 정품, 배송 시 진품 확인, 명확한 인보이스 조건으로 제공합니다.",
      "",
      "현재 셀렉션은 아래에서 바로 열어볼 수 있는 엑셀 라인시트로 확인하실 수 있습니다. 도매가는 인증된 파트너에게만 제공됩니다 — 한 번만 등록하시면(무료) 모든 가격이 즉시 공개됩니다.",
      "",
      "취급하시는 브랜드 구성을 알려주시면 매장에 맞는 셀렉션을 준비해 드리겠습니다.",
      "",
      "감사합니다,",
      "Les Garage de Paris · via VESTRA",
      "",
      "—",
      "Les Garage de Paris via VESTRA(운영: Acerasoft LLC). 일회성 비즈니스 메시지입니다 — 귀하의 매장이 프리미엄 거래 파트너 후보로 확인되었습니다.",
      "즉시 수신 거부: https://vestrasales.com/lead-unsubscribe",
    ]);
    $opts=[
      'hero'=>['kicker'=>'정품 디자이너 도매','title'=>'고객이 이름으로 찾는 바로 그 브랜드를, 도매 조건으로.'],
      'brands'=>$brands,
      'brands_title'=>'주요 브랜드',
      'brands_hint'=>'브랜드를 탭하면 라인시트가 열립니다',
      'badge'=>'KYC 인증 · 정품 확인 · 에스크로 보호 결제',
      'shots'=>$shots,
      'shots_title'=>$shotsTitle,
      'downloads'=>['title'=>'라인시트 — 엑셀 다운로드','items'=>$downloads],
      'rows'=>[
        ['label'=>'정품 여부','value'=>'배송 시 확인'],
        ['label'=>'결제','value'=>'에스크로 보호 인보이스'],
        ['label'=>'최소 수량','value'=>'소량 주문 가능','strong'=>true],
      ],
      'button'=>['label'=>'도매가 확인을 위해 등록하기','url'=>'https://vestrasales.com/register'],
    ];
    return [$subject,$body,$opts];
  }

  if($lang==='az'){
    $subject='Les Garage de Paris × VESTRA — topdan qiymətlərlə orijinal dizayner moda';
    $body=implode("\n",[
      $company!=='' ? "Salam — {$company} üçün qısa məlumat." : "Salam,",
      "",
      "Les Garage de Paris, VESTRA vasitəsilə — orijinal dizayner modası üçün KYC ilə təsdiqlənmiş B2B bazarı — çoxbrendli butiklərə topdan mal təqdim edir.",
      "",
      "Müştərilərinizin adını çəkərək soruşduğu brendləri təmin edirik — 100% orijinal, çatdırılma zamanı həqiqiliyi yoxlanılmış, aydın faktura şərtləri ilə.",
      "",
      "Cari seçim aşağıda hazır Excel line-sheet formatında verilib. Topdan qiymətlər yalnız təsdiqlənmiş partnyorlar üçündür — bir dəfə (pulsuz) qeydiyyatdan keçin, bütün qiymətlər dərhal açılsın.",
      "",
      "Brend çeşidinizi bildirin, mağazanız üçün seçim hazırlayım.",
      "",
      "Hörmətlə,",
      "Les Garage de Paris · via VESTRA",
      "",
      "—",
      "Les Garage de Paris via VESTRA (Acerasoft LLC tərəfindən idarə olunur). Bir dəfəlik biznes mesajı — mağazanız potensial premium ticarət partnyoru kimi müəyyən edilib.",
      "Dərhal abunəlikdən çıxın: https://vestrasales.com/lead-unsubscribe",
    ]);
    $opts=[
      'hero'=>['kicker'=>'Orijinal dizayner topdan satışı','title'=>'Müştərilərinizin artıq adını çəkərək soruşduğu brendlər — indi topdan şərtlərlə.'],
      'brands'=>$brands,
      'brands_title'=>'Seçilmiş brendlər',
      'brands_hint'=>'Line-sheet üçün brendə toxunun',
      'badge'=>'KYC təsdiqlənib · həqiqilik yoxlanılıb · escrow ilə qorunur',
      'shots'=>$shots,
      'shots_title'=>$shotsTitle,
      'downloads'=>['title'=>'Line-sheet-lər — yüklə (Excel)','items'=>$downloads],
      'rows'=>[
        ['label'=>'Orijinallıq','value'=>'Çatdırılma zamanı yoxlanılır'],
        ['label'=>'Ödəniş','value'=>'Escrow ilə qorunan faktura'],
        ['label'=>'Minimum sifariş','value'=>'Aşağı — kapsul kolleksiyalar üçün əlverişli','strong'=>true],
      ],
      'button'=>['label'=>'Topdan qiymətlər üçün qeydiyyatdan keçin','url'=>'https://vestrasales.com/register'],
    ];
    return [$subject,$body,$opts];
  }

  $subject='Les Garage de Paris × VESTRA — the authentic designer wholesale edit';
  $body=implode("\n",[
    $company!=='' ? "For {$company}." : "Hello,",
    "",
    "Les Garage de Paris sources authentic designer fashion for multi-brand boutiques, through VESTRA — the KYC-verified B2B marketplace built for the trade.",
    "",
    "We supply the houses your clients already ask for by name — 100% authentic, verified on delivery, on clear invoice terms.",
    "",
    "The current selection follows below as ready-to-open Excel line-sheets. Trade pricing is reserved for verified partners — register once, it's free, and every price unlocks instantly.",
    "",
    "Share your brand mix and I'll curate a selection for your floor.",
    "",
    "Warm regards,",
    "Les Garage de Paris · via VESTRA",
    "",
    "—",
    "Les Garage de Paris via VESTRA (operated by Acerasoft LLC). One-time business message — your store was identified as a potential premium trade partner.",
    "Unsubscribe instantly: https://vestrasales.com/lead-unsubscribe",
  ]);
  $opts=[
    'hero'=>['kicker'=>'Authentic designer wholesale — sourced in Paris','title'=>'The houses your best clients already ask for — now at trade terms.'],
    'brands'=>$brands,
    'brands_title'=>'Featured houses',
    'brands_hint'=>'Tap a house to open its line-sheet',
    'badge'=>'KYC-verified · authenticity-checked · escrow-protected',
    'shots'=>$shots,
    'shots_title'=>$shotsTitle,
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

/**
 * Promo variant: two Lacoste polos carry a display-only markdown (list price
 * raised so "-10%/-15%" shows, the real wholesale price is unchanged — see
 * product-fixes/lacoste-polos-discount-badges.json). English only for now
 * (unlike vestra_campaign_preview, which is 9-language) — a small, one-off
 * promo send doesn't warrant translating discount copy into all of them;
 * extend with a $lang branch the same way if that's ever needed.
 * Reuses the exact same $opts shape as vestra_campaign_preview() so it
 * renders through the identical premium HTML template.
 */
function vestra_campaign_promo_polos(string $company=''): array {
  $subject = 'Lacoste polos — up to −15% this week · Les Garage de Paris × VESTRA';
  $body = implode("\n", [
    $company !== '' ? "Hello — a note for {$company}." : "Hello,",
    "",
    "Quick heads-up from Les Garage de Paris, via VESTRA: two Lacoste polo styles are marked down this week — same authentic stock, same wholesale terms, just a better margin for you.",
    "",
    "Regular Fit Logo Trim Polo — was €33.22, now €29.90 (−10%)",
    "Classic Fit Monogram Jacquard Polo — was €41.18, now €35.00 (−15%)",
    "",
    "Trade pricing is reserved for verified partners — register once (it's free) and every price unlocks instantly.",
    "",
    "Want the full Lacoste range? Just reply and I'll send the line-sheet.",
    "",
    "Warm regards,",
    "Les Garage de Paris · via VESTRA",
    "",
    "—",
    "Les Garage de Paris via VESTRA (operated by Acerasoft LLC). One-time business message — your store was identified as a potential premium trade partner.",
    "Unsubscribe instantly: https://vestrasales.com/lead-unsubscribe",
  ]);
  $opts = [
    'hero' => ['kicker' => 'Limited-time trade pricing', 'title' => 'Lacoste polos — up to −15% this week.'],
    'badge' => 'KYC-verified · authenticity-checked · escrow-protected',
    'shots' => [
      ['img' => 'https://vestrasales.com/uploads/lacoste/logotrim-polo/bordeaux.jpg', 'label' => 'Logo Trim Polo −10%', 'url' => 'https://vestrasales.com/product?id=lac-logotrim-polo'],
      ['img' => 'https://vestrasales.com/uploads/lacoste/monogram-polo/black.avif', 'label' => 'Monogram Polo −15%', 'url' => 'https://vestrasales.com/product?id=lac-monogram-polo'],
    ],
    'shots_title' => "This week's markdowns",
    'downloads' => ['title' => 'Shop the discounted styles', 'items' => [
      ['label' => 'Logo Trim Polo — €29.90 (−10%)', 'url' => 'https://vestrasales.com/product?id=lac-logotrim-polo'],
      ['label' => 'Monogram Polo — €35.00 (−15%)', 'url' => 'https://vestrasales.com/product?id=lac-monogram-polo'],
    ]],
    'rows' => [
      ['label' => 'Logo Trim Polo', 'value' => '€29.90 (was €33.22) · −10%', 'strong' => true],
      ['label' => 'Monogram Jacquard Polo', 'value' => '€35.00 (was €41.18) · −15%', 'strong' => true],
    ],
    'button' => ['label' => 'Shop Lacoste polos', 'url' => 'https://vestrasales.com/catalog?brand=Lacoste'],
  ];
  return [$subject, $body, $opts];
}

/* Builds a multipart/alternative body (plain text + the HTML shell above) for transports that
 * send raw MIME themselves (SMTP, PHP mail()) — HTTP APIs take the two parts separately. */
/**
 * Sign-off card for mail written by a person at VESTRA rather than emitted by the system.
 *
 * No postal address, on purpose: this is a signature, not an imprint. The legal and company
 * details belong on the site, and five lines of address under every reply turn a personal
 * answer back into a form letter. What a recipient actually needs is who wrote, in what
 * capacity, and where to reply.
 *
 * Table-based because Outlook ignores flex and inline-block alignment, and the name stays
 * live TEXT next to the mark rather than being baked into it — most clients block remote
 * images by default, and a signature that disappears with the image is not a signature.
 *
 * The sign-off is departmental on purpose — no individual's name. Support is answered by
 * whoever is on the desk, and a name in the signature invites the reply to go to a person
 * who may not pick it up.
 *
 * $sig keys, all optional: name, role, email, site.
 */
function vestra_email_signature_html(array $sig): string {
  $e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
  $name =trim((string)($sig['name']  ?? 'VESTRA Support'));
  $role =trim((string)($sig['role']  ?? ''));
  $mail =trim((string)($sig['email'] ?? ''));
  $site =trim((string)($sig['site']  ?? 'vestrasales.com'));
  if($name==='' && $mail==='') return '';

  $contact='';
  if($mail!=='') $contact.='<a href="mailto:'.$e($mail).'" style="color:#8a6d1f;text-decoration:none;font-size:13px">'.$e($mail).'</a>';
  if($site!=='') $contact.=($contact!==''?'<br>':'')
    .'<a href="https://'.$e($site).'" style="color:#9b9585;text-decoration:none;font-size:12px">'.$e($site).'</a>';

  return '<div style="margin:2px 28px 22px">'
    .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;'
    .'background:#faf8f3;border:1px solid #ece6d8;border-radius:10px"><tr>'
    /* alt="VESTRA" with the mark's own colours on the <img>: clients that block remote
       images fall back to the alt text inside the styled box, so a blocked logo still
       leaves a dark gold monogram rather than a hole in the card. */
    .'<td valign="top" width="62" style="padding:16px 0 16px 16px;line-height:0">'
    .'<img src="https://vestrasales.com/icon-192.png" width="46" height="46" alt="VESTRA"'
    .' style="display:block;width:46px;height:46px;border:0;border-radius:10px;background:#14110c;'
    .'color:#d8bd86;font-family:Georgia,\'Times New Roman\',serif;font-size:10px;font-weight:700;'
    .'letter-spacing:.04em;text-align:center;line-height:46px"></td>'
    .'<td valign="top" style="padding:16px 16px 16px 12px">'
    .'<div style="font-family:Georgia,\'Times New Roman\',serif;color:#14110c;font-size:15px;font-weight:700;letter-spacing:.02em">'.$e($name).'</div>'
    .($role!==''?'<div style="color:#a97f2c;font-size:10px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;margin:3px 0 0">'.$e($role).'</div>':'')
    .'<div style="width:26px;height:2px;background:#c9a86a;margin:11px 0 9px"></div>'
    .$contact
    .'</td></tr></table></div>';
}

/** The same signature as plain text, for the text/plain alternative. */
function vestra_email_signature_text(array $sig): string {
  $lines=array_values(array_filter(array_map('trim',[
    (string)($sig['name']  ?? 'VESTRA Support'),
    (string)($sig['role']  ?? ''),
    (string)($sig['email'] ?? ''),
    (string)($sig['site']  ?? 'vestrasales.com'),
  ]), fn($v)=>$v!==''));
  // "-- " (dash dash space) is the RFC 3676 signature marker; clients use it to fold the
  // block away on reply instead of quoting it back.
  return $lines ? "-- \n".implode("\n",$lines) : '';
}

/**
 * Text alternative of an email.
 *
 * The signature is declared once in $opts and rendered into both alternatives from there.
 * Appending it to $bodyPlain instead would also feed it through the HTML paragraph
 * renderer, and the recipient would get the card and the text one under the other.
 */
function vestra_mail_text_part(string $bodyPlain, array $opts): string {
  $sig=(!empty($opts['signature']) && is_array($opts['signature']))
    ? vestra_email_signature_text($opts['signature']) : '';
  return $sig==='' ? $bodyPlain : rtrim($bodyPlain)."\n\n".$sig;
}

function vestra_mime_multipart(string $bodyPlain, string $boundary, string $heroImage='', array $opts=[]): string {
  $html=vestra_html_email($bodyPlain,$heroImage,$opts);
  return "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
       .vestra_mail_text_part($bodyPlain,$opts)."\r\n\r\n"
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
/* filter_var, saglayicinin kabul ettiginden DAHA GENIS. Canli hata gunlugunde
 * 32 kez "email is not valid in to" var: adres yerel dogrulamadan geciyor,
 * Brevo reddediyor, ve kayit "gonderilmedi" olarak isaretlenmedigi icin ayni
 * adres her kosuda yeniden deneniyor. Ucretsiz planda her deneme bir kredi.
 *
 * Burada YALNIZCA saglayicinin kesin reddettigi kaliplar eleniyor. Daha
 * siki olmak cazip ama tehlikeli: gecerli bir adresi sessizce elemek,
 * gecersiz bir adrese bosuna gondermekten pahalidir -- kimse fark etmez.
 * Bu yuzden 'suphe varsa gecir' tarafinda kaliniyor. */
function vestra_email_deliverable(string $e): bool {
    $e = trim($e);
    if ($e === '' || !filter_var($e, FILTER_VALIDATE_EMAIL)) return false;
    if (strlen($e) > 254) return false;
    /* ASCII disi: Brevo SMTPUTF8 kabul etmiyor, adres reddediliyor. */
    if (preg_match('/[^\x21-\x7E]/', $e)) return false;
    $at = strrpos($e, '@');
    $local = substr($e, 0, $at); $dom = substr($e, $at + 1);
    if ($local === '' || strlen($local) > 64) return false;
    if ($local[0] === '.' || substr($local, -1) === '.') return false;
    if (str_contains($local, '..')) return false;
    if (str_contains($dom, '..') || $dom[0] === '.' || $dom[0] === '-') return false;
    /* Alan adinda en az bir nokta ve en az iki harflik bir uzanti. */
    if (!preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/i', $dom)) return false;
    return true;
}

function vestra_send_mail($to,$subject,$body,$replyTo='',$fromName='',$cfg=null,$heroImage='',array $opts=[]){
  /* TRIM once, and carry the trimmed value onward. Validating the trimmed
     string but sending the raw one would pass a trailing space straight to
     the provider -- which is exactly the "email is not valid in to" it keeps
     rejecting. Kaydedilmis adreslerde bosluk sik: CSV/yapistirma artigi. */
  $to = trim((string)$to);
  if(!vestra_email_deliverable($to)){
    /* Sessizce false donmek, "neden gitmedi" sorusunu cevapsiz birakiyordu.
       Adres maskeli yaziliyor: gunluk halka acik degil ama musteri adresi
       gereksiz yere tam yazilmamali. */
    error_log('[VESTRA Mail] gecersiz alici, gonderilmedi: '.preg_replace('/^(.).*(@.*)$/','$1***$2',(string)$to));
    return false;
  }
  // Explicit sender config (e.g. a seller's OWN SMTP/API) — send truly "from" them.
  if($cfg!==null){
    if(($cfg['mail_api_key']??'')!=='') return vestra_api_send($to,$subject,$body,$replyTo,$fromName,$cfg,$heroImage,$opts);
    if(($cfg['smtp_host']??'')!=='' && ($cfg['smtp_pass']??'')!=='') return vestra_smtp_send($to,$subject,$body,$replyTo,$fromName,$cfg,$heroImage,$opts);
    return false; // sender selected but their transport isn't set up
  }
  if(!vestra_cfg('mail_enabled',false)) return false;
  if(vestra_cfg('mail_api_key','')!==''){
    $ok = vestra_api_send($to,$subject,$body,$replyTo,$fromName,null,$heroImage,$opts);
    if($ok) return true;
    error_log("[VESTRA Mail] API send failed to {$to} — subject: {$subject}");
    /* Second transport. The primary API has a monthly/daily allowance; once it is spent
     * every further send is refused and the campaign simply stops. If a backup SMTP
     * transport is configured (Amazon SES, Scaleway, any SMTP provider — vestra_smtp_send
     * speaks STARTTLS + AUTH LOGIN, which all of them offer), carry on through it.
     *
     * Guarded by vestra_api_definitively_rejected(): retry ONLY when the provider
     * answered with an HTTP status, meaning nothing was sent. On an ambiguous network
     * failure we accept losing the message rather than risk mailing the same boutique
     * twice — a duplicate cold email costs more goodwill than a miss.
     *
     * Set mail_fallback_smtp=true in config to arm this; it is off by default so a
     * misconfigured backup can never silently take over the primary path. */
    if(vestra_cfg('mail_fallback_smtp',false) && vestra_api_definitively_rejected()
       && vestra_cfg('smtp_host','')!=='' && vestra_cfg('smtp_pass','')!==''){
      error_log("[VESTRA Mail] primary refused — retrying {$to} via fallback SMTP");
      $ok2 = vestra_smtp_send($to,$subject,$body,$replyTo,$fromName,null,$heroImage,$opts);
      error_log($ok2 ? "[VESTRA Mail] fallback SMTP delivered to {$to}"
                     : "[VESTRA Mail] fallback SMTP ALSO failed for {$to}");
      return $ok2;
    }
    return false;
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
    $payload=['from'=>"{$name} <{$from}>",'to'=>[$to],'subject'=>$subject,'text'=>vestra_mail_text_part($body,$opts),'html'=>vestra_html_email($body,$heroImage,$opts)];
    if($replyTo && filter_var($replyTo,FILTER_VALIDATE_EMAIL)) $payload['reply_to']=$replyTo;
  } else { // brevo (default)
    $url='https://api.brevo.com/v3/smtp/email';
    $headers=['api-key: '.$key,'Content-Type: application/json','Accept: application/json'];
    $payload=[
      'sender'=>['name'=>$name,'email'=>$from],
      'to'=>[['email'=>$to]],
      'subject'=>$subject,
      'textContent'=>vestra_mail_text_part($body,$opts),
      'htmlContent'=>vestra_html_email($body,$heroImage,$opts),
    ];
    if($replyTo && filter_var($replyTo,FILTER_VALIDATE_EMAIL)) $payload['replyTo']=['email'=>$replyTo];
  }

  /* Ekler. $opts['attachments'] = [['name'=>'x.xlsx','path'=>'/abs/yol'], ...]
     ya da hazir base64 icin ['name'=>..., 'content'=>...].

     Bir toptan fiyat listesi eninde sonunda alicinin kendi satin alma tablosuna
     yapistirilir; linkle gonderilen dosya "sonra bakarim" olur, ekteki dosya
     acilir. Iki saglayicinin alan adlari farkli: Brevo 'attachment', Resend
     'attachments'.

     Okunamayan dosya SESSIZCE atlanmiyor -- kutuge yaziliyor. Eksik ekli bir
     teklif e-postasi, hic gonderilmemis olandan daha kotu: musteri listeyi
     bekler, biz gonderdik saniriz. */
  $atts=[];
  foreach((array)($opts['attachments']??[]) as $a){
    $n=trim((string)($a['name']??''));
    if($n==='') continue;
    $b64=(string)($a['content']??'');
    if($b64===''){
      $p=(string)($a['path']??'');
      if($p==='' || !is_readable($p)){ error_log("[VESTRA Mail] ek okunamadi, atlandi: {$p}"); continue; }
      $raw=@file_get_contents($p);
      if($raw===false){ error_log("[VESTRA Mail] ek okunamadi, atlandi: {$p}"); continue; }
      $b64=base64_encode($raw);
    }
    $atts[]=['name'=>$n,'content'=>$b64];
  }
  if($atts){
    if($provider==='resend') $payload['attachments']=$atts;
    else                     $payload['attachment']=$atts;
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
  if($resp===false){
    /* Network/transport error: the request may or may not have reached the provider.
     * Record it as AMBIGUOUS so the caller does NOT retry on a second transport --
     * a timeout that arrives after the provider already accepted the message would
     * otherwise send the same recipient two copies. */
    error_log('[VESTRA API] curl error: '.curl_error($ch)); curl_close($ch);
    $GLOBALS['vestra_api_last_rejected']=false;
    return false;
  }
  $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  if($code>=200 && $code<300){ $GLOBALS['vestra_api_last_rejected']=false; return true; }
  /* Definitive rejection with an HTTP status: the provider refused it and nothing was
   * sent, so a fallback transport is safe. Quota exhaustion lands here (Brevo answers
   * 402 "not enough credits"), which is exactly the case worth failing over. */
  $GLOBALS['vestra_api_last_rejected']=true;
  error_log("[VESTRA API] {$provider} HTTP {$code}: ".substr((string)$resp,0,300));
  return false;
}

/* True when the last vestra_api_send() was refused by the provider with an HTTP status
 * (quota, bad sender, rejected recipient) rather than failing ambiguously mid-flight.
 * Only then may the caller retry the same message on a different transport. */
function vestra_api_definitively_rejected(): bool {
  return !empty($GLOBALS['vestra_api_last_rejected']);
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
     /* E-POSTA YOLU (KURAL 2d, 2 Eyl 2026): yukleyemeyen kullanici dosyayi bu
        mektuba ekleyip yanitlar; operator panelden hesaba ekler. Bir alici iki
        gun yukleyemeyip sonunda e-postayla gonderdi -- mektup bunu bastan
        soylemeliydi. */
     "Hello %s,\n\nYour VESTRA account has been created as a verified %s.\n\nNext step: please upload your verification documents at:\n%s\n\nYou can also simply reply to this e-mail with the document attached (PDF or a photo) and we add it to your account for you.\n\nOur team will review them and activate your account. Thank you for joining!\n\n— VESTRA · vestrasales.com",
   ],
   'de'=>[
     'Willkommen bei VESTRA — Konto erstellt',
     "Hallo %s,\n\nIhr VESTRA-Konto als %s wurde erfolgreich erstellt.\n\nNächster Schritt: Bitte laden Sie Ihre Verifizierungsdokumente hoch:\n%s\n\nSie können das Dokument auch einfach als Antwort auf diese E-Mail anhängen (PDF oder Foto) — wir fügen es Ihrem Konto hinzu.\n\nUnser Team prüft diese und aktiviert Ihr Konto. Vielen Dank!\n\n— VESTRA · vestrasales.com",
   ],
   'fr'=>[
     'Bienvenue sur VESTRA — compte créé',
     "Bonjour %s,\n\nVotre compte VESTRA en tant que %s a été créé avec succès.\n\nProchaine étape : veuillez télécharger vos documents de vérification :\n%s\n\nVous pouvez aussi simplement répondre à cet e-mail avec le document en pièce jointe (PDF ou photo) : nous l'ajouterons à votre compte.\n\nNotre équipe les examinera et activera votre compte. Merci de nous rejoindre !\n\n— VESTRA · vestrasales.com",
   ],
   'it'=>[
     'Benvenuto su VESTRA — account creato',
     "Ciao %s,\n\nIl tuo account VESTRA come %s è stato creato con successo.\n\nProssimo passo: carica i tuoi documenti di verifica:\n%s\n\nPuoi anche semplicemente rispondere a questa e-mail allegando il documento (PDF o foto): lo aggiungeremo noi al tuo account.\n\nIl nostro team li esaminerà e attiverà il tuo account. Grazie!\n\n— VESTRA · vestrasales.com",
   ],
   'es'=>[
     'Bienvenido a VESTRA — cuenta creada',
     "Hola %s,\n\nTu cuenta VESTRA como %s ha sido creada con éxito.\n\nSiguiente paso: sube tus documentos de verificación:\n%s\n\nTambién puedes simplemente responder a este correo adjuntando el documento (PDF o foto) y lo añadiremos a tu cuenta.\n\nNuestro equipo los revisará y activará tu cuenta. ¡Gracias!\n\n— VESTRA · vestrasales.com",
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

/* ── Sending allowance ────────────────────────────────────────────────────────
 * How many messages the mail provider will still accept today.
 *
 * This exists because a campaign and a password reset draw on the SAME pool, and
 * the campaign always wins by arriving first. When the day's allowance runs out,
 * every later send is refused -- and the ones that matter most are the ones a
 * person is sitting there waiting for: a reset link, a verification mail, an order
 * confirmation. "It works, then after a while it stops" is exactly what an
 * exhausted daily allowance looks like from the outside.
 *
 * Note for anyone reading this later: configuring an SMTP fallback does NOT help
 * when the SMTP relay belongs to the same provider account (smtp-relay.brevo.com
 * is the same allowance as the Brevo API). A second transport only helps if it is
 * a second PROVIDER.
 *
 * Returns null when the provider cannot be asked -- callers must treat null as
 * "unknown", never as "plenty left".
 */
function vestra_mail_credits_left(): ?int {
    static $cached = false, $val = null;
    if ($cached) return $val;
    $cached = true;
    $key = (string)vestra_cfg('mail_api_key', '');
    if ($key === '') return $val = null;
    $ch = curl_init('https://api.brevo.com/v3/account');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['api-key: '.$key, 'Accept: application/json']]);
    $raw = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code < 200 || $code >= 300 || !is_string($raw)) return $val = null;
    $d = json_decode($raw, true);
    foreach ((array)($d['plan'] ?? []) as $p) {
        if (($p['creditsType'] ?? '') === 'sendLimit') return $val = (int)($p['credits'] ?? 0);
    }
    return $val = null;
}

/**
 * May a BULK run of $need messages start right now?
 *
 * Keeps $reserve messages back for the transactional mail that a person is waiting
 * on. A campaign postponed by a day costs nothing; a password reset that never
 * arrives costs the customer their account. Returns [allowed, human sentence].
 *
 * When the allowance cannot be read the run is allowed: refusing to send on a
 * failed status call would turn a provider hiccup into a silent outreach freeze,
 * which is the opposite of the problem being solved here.
 */
function vestra_mail_bulk_allowed(int $need, int $reserve = 60): array {
    $left = vestra_mail_credits_left();
    if ($left === null) return [true, 'kalan kota okunamadi -- gonderim yine de deneniyor'];
    $usable = $left - $reserve;
    if ($usable <= 0) {
        return [false, "gunluk kota bitti: {$left} kaldi, {$reserve} tanesi sifre sifirlama / dogrulama / siparis bildirimi icin ayrilmis. Toplu gonderim yapilmadi."];
    }
    if ($need > $usable) {
        return [false, "istenen {$need}, ayrilan pay dusuldukten sonra kullanilabilir {$usable} (toplam kalan {$left}). Daha kucuk bir parti ile deneyin."];
    }
    return [true, "kota tamam: {$left} kalan, {$reserve} ayrilmis, bu parti {$need}"];
}
