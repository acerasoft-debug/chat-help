<?php
/**
 * ChatHelp — Amazon-FBA-Produktfinder (Lizenz buy-amazon-fba, Agent agent-amazon-fba, Modul „fba“, Präfix fba_)
 * ==========================================================================================================
 * Datenquelle: Keepa API (api.keepa.com). Marktplätze: DE, FR, IT, ES, UK, US.
 * Werkzeuge (nur Studio-Chat des Kontoinhabers und Demo – CH_MODULE_CHANNELS in agent-tools.php):
 *   fba_analyze   – ein Produkt (ASIN, EAN/UPC) auf bis zu 6 Marktplätzen: Buy-Box-Preis, 90-Tage-Schnitt, BSR, geschätzte
 *                   Monatsverkäufe, FBA-Angebote, Amazon selbst im Angebot, Referral- und FBA-Gebühr → Gewinn, Marge, ROI, Risiken, Urteil.
 *   fba_find      – Produkt-Recherche (Keepa Product Finder) nach Stichwort/Kategorie, Preisspanne, BSR, Konkurrenz → bis 20 Kandidaten.
 *   fba_watch     – Beobachtungsliste (hinzufügen / entfernen / anzeigen) mit Einkaufspreis.
 *   fba_settings  – täglicher E-Mail-Bericht an/aus, Empfänger, eigener Keepa-Schlüssel (wird nie zurückgegeben).
 * Täglicher Bericht: fba_daily_tick() – ohne Server-Cron, einmal am Tag nach 06:00 (Europe/Berlin) beim ersten Seitenaufruf,
 * nach dem Absenden der Antwort, in Portionen (Zeitbudget). Manuell: chat/ops/fba-daily.php.
 * Keepa-Schlüssel: eigener des Kunden (acct_secrets 'keepa_key') > Plattform (CH_KEEPA_KEY in db.php, sonst cache/.keepa-key.php,
 * geschrieben von chat/ops/fba-keepa-key.php aus dem GitHub-Secret). Mit dem Plattform-Schlüssel gilt ein Tageslimit je Konto.
 * Rechnung: alle Beträge in der Marktplatzwährung; Einkaufspreis wird mit den Tageskursen (ch_fx_rates, EZB) umgerechnet.
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/plans.php';

const FBA_AGENT = 'agent-amazon-fba';
/** Keepa-Domain, Währung, USt.-Satz (im Endpreis enthalten; US: Sales Tax nicht im Preis) */
const FBA_MARKETS = [
    'de'=>['domain'=>3,'cur'=>'EUR','vat'=>0.19,'host'=>'amazon.de',    'name'=>'Amazon.de'],
    'fr'=>['domain'=>4,'cur'=>'EUR','vat'=>0.20,'host'=>'amazon.fr',    'name'=>'Amazon.fr'],
    'it'=>['domain'=>8,'cur'=>'EUR','vat'=>0.22,'host'=>'amazon.it',    'name'=>'Amazon.it'],
    'es'=>['domain'=>9,'cur'=>'EUR','vat'=>0.21,'host'=>'amazon.es',    'name'=>'Amazon.es'],
    'uk'=>['domain'=>2,'cur'=>'GBP','vat'=>0.20,'host'=>'amazon.co.uk', 'name'=>'Amazon.co.uk'],
    'us'=>['domain'=>1,'cur'=>'USD','vat'=>0.00,'host'=>'amazon.com',   'name'=>'Amazon.com'],
];
const FBA_PLATFORM_DAILY_PRODUCTS = 300;   // Keepa-Produkte je Konto und Tag mit dem Plattform-Schlüssel
const FBA_REFERRAL_DEFAULT = 0.15;         // falls Keepa keinen Satz liefert
const FBA_FEE_FALLBACK = ['EUR'=>350,'GBP'=>300,'USD'=>400];   // FBA-Versandgebühr (Cent) falls unbekannt – als Schätzung markiert
/** Urteil: gut, wenn ROI ≥ 30 %, Marge ≥ 15 %, Amazon nicht im Angebot, höchstens 12 FBA-Angebote */
const FBA_GOOD = ['roi'=>0.30,'margin'=>0.15,'max_fba_offers'=>12];

/* ---------------------------------------------------------------- Datenbank */
function fba_bootstrap(): void {
    static $done=false; if($done) return; $done=true;
    $p=th_db();
    $p->exec("CREATE TABLE IF NOT EXISTS fba_watch (id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, asin VARCHAR(16) NOT NULL, market VARCHAR(4) NOT NULL,
        cost_cents INT NOT NULL DEFAULT 0, cost_cur CHAR(3) NOT NULL DEFAULT 'EUR', inbound_cents INT NOT NULL DEFAULT 0, note VARCHAR(200) DEFAULT '',
        last_json TEXT NULL, last_at DATETIME NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq (account_id, asin, market), INDEX(account_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS fba_settings (account_id INT PRIMARY KEY, report_on TINYINT NOT NULL DEFAULT 0, report_email VARCHAR(190) DEFAULT '',
        report_lang VARCHAR(8) DEFAULT 'de', last_report DATE NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS fba_usage (account_id INT NOT NULL, day DATE NOT NULL, products INT NOT NULL DEFAULT 0, PRIMARY KEY(account_id, day)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/* ---------------------------------------------------------------- Lizenz / Schlüssel */
function fba_enabled(int $acc): bool { return $acc===0 || th_plan_active($acc,FBA_AGENT); }

function fba_platform_key(): string {
    if(defined('CH_KEEPA_KEY') && (string)CH_KEEPA_KEY!=='') return (string)CH_KEEPA_KEY;
    $env=getenv('KEEPA_API_KEY'); if(is_string($env) && $env!=='') return $env;
    $f=__DIR__.'/cache/.keepa-key.php'; if(is_file($f)){ $v=@include $f; if(is_string($v) && $v!=='') return $v; }
    return '';
}
/** [key, own] – eigener Schlüssel des Kunden hat Vorrang (kein Tageslimit). */
function fba_key(int $acc): array {
    $own=$acc>0 ? th_secret($acc,'keepa_key') : '';
    return $own!=='' ? [$own,true] : [fba_platform_key(),false];
}
/** Verbraucht n Produkte vom Tageskontingent (nur Plattform-Schlüssel). false = Limit erreicht. */
function fba_quota_take(int $acc, int $n): bool {
    if($acc<=0 || $n<=0) return true;
    fba_bootstrap(); $p=th_db(); $day=date('Y-m-d');
    $s=$p->prepare("SELECT products FROM fba_usage WHERE account_id=? AND day=?"); $s->execute([$acc,$day]); $used=(int)($s->fetchColumn() ?: 0);
    if($used+$n>FBA_PLATFORM_DAILY_PRODUCTS) return false;
    $p->prepare("INSERT INTO fba_usage(account_id,day,products) VALUES(?,?,?) ON DUPLICATE KEY UPDATE products=products+VALUES(products)")->execute([$acc,$day,$n]);
    return true;
}

/* ---------------------------------------------------------------- Keepa */
/** GET api.keepa.com/<path>. Ergebnis: decodiertes JSON oder ['error'=>…]. Testbar über $GLOBALS['fba_http'] (callable(url): [code, body]). */
function fba_keepa(string $path, array $q, string $key): array {
    if($key==='') return ['error'=>'no_key'];
    $url='https://api.keepa.com/'.$path.'?'.http_build_query(['key'=>$key]+$q);
    if(isset($GLOBALS['fba_http']) && is_callable($GLOBALS['fba_http'])){ [$code,$body]=($GLOBALS['fba_http'])($url); }
    else {
        $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>25,CURLOPT_ENCODING=>'gzip',CURLOPT_HTTPHEADER=>['Accept: application/json']]);
        $body=(string)curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    }
    $j=json_decode((string)$body,true);
    if($code===429) return ['error'=>'keepa_rate','hint'=>'Keepa-Tokens aufgebraucht – in einigen Minuten erneut versuchen.','refill_in_ms'=>(int)($j['refillIn'] ?? 0)];
    if($code===402 || $code===401 || $code===403) return ['error'=>'keepa_key','hint'=>'Keepa-Schlüssel ungültig oder Abo abgelaufen.'];
    if($code!==200 || !is_array($j)) return ['error'=>'keepa_http','status'=>$code];
    if(!empty($j['error'])) return ['error'=>'keepa','hint'=>(string)($j['error']['message'] ?? $j['error']['type'] ?? 'Keepa-Fehler')];
    return $j;
}
/** Kennung erkennen: ASIN (B0…, 10 Zeichen) oder EAN/UPC/ISBN (8–14 Ziffern). */
function fba_ident(string $raw): ?array {
    $t=strtoupper(preg_replace('/[\s-]+/','',trim($raw)) ?? '');
    if(preg_match('/^(B0[A-Z0-9]{8}|[0-9]{9}[0-9X])$/',$t)) return ['asin',$t];
    if(preg_match('/amazon\.[a-z.]+\/(?:[^\/]+\/)?(?:dp|gp\/product)\/([A-Z0-9]{10})/i',$raw,$m)) return ['asin',strtoupper($m[1])];
    if(preg_match('/^[0-9]{8,14}$/',$t)) return ['code',$t];
    return null;
}
/** Keepa-Wert (Cent, -1 = keine Daten) */
function fba_v($a, int $i): ?int { $v=is_array($a) ? ($a[$i] ?? null) : null; return (is_numeric($v) && (int)$v>0) ? (int)$v : null; }

/** Ein Keepa-Produkt (mit stats) auf das Nötige reduzieren. */
function fba_product_facts(array $p, string $mk): array {
    $st=(array)($p['stats'] ?? []); $cur=(array)($st['current'] ?? []); $avg=(array)($st['avg90'] ?? []);
    $bb=fba_v([$st['buyBoxPrice'] ?? null],0) ?? fba_v($cur,18) ?? fba_v($cur,1) ?? fba_v($cur,0);
    $bbAvg=fba_v($avg,18) ?? fba_v($avg,1) ?? fba_v($avg,0);
    $ref=$p['referralFeePercentage'] ?? ($p['referralFeePercent'] ?? null);
    $fbaFee=(int)($p['fbaFees']['pickAndPackFee'] ?? 0);
    $drops=(int)($st['salesRankDrops30'] ?? 0);
    $sold=(int)($p['monthlySold'] ?? 0);
    $amazonNow=fba_v($cur,0)!==null || !empty($st['buyBoxIsAmazon']) || in_array((int)($p['availabilityAmazon'] ?? -1),[0,1,2,3,4],true) && fba_v($cur,0)!==null;
    $tree=array_values(array_filter(array_map(fn($c)=>(string)($c['name'] ?? ''),(array)($p['categoryTree'] ?? []))));
    return [
        'asin'=>(string)($p['asin'] ?? ''), 'title'=>mb_substr((string)($p['title'] ?? ''),0,160), 'brand'=>(string)($p['brand'] ?? ''),
        'category'=>$tree ? implode(' › ',array_slice($tree,-2)) : (string)($p['productGroup'] ?? ''),
        'market'=>$mk, 'currency'=>FBA_MARKETS[$mk]['cur'],
        'price'=>$bb, 'price_avg90'=>$bbAvg,
        'bsr'=>fba_v($cur,3), 'bsr_avg90'=>fba_v($avg,3),
        'monthly_sold'=>$sold>0 ? $sold : ($drops>0 ? $drops : null), 'sold_source'=>$sold>0 ? 'amazon' : ($drops>0 ? 'rank_drops_30d' : null),
        'fba_offers'=>isset($st['offerCountFBA']) ? (int)$st['offerCountFBA'] : null, 'fbm_offers'=>isset($st['offerCountFBM']) ? (int)$st['offerCountFBM'] : null,
        'amazon_sells'=>(bool)$amazonNow,
        'referral_pct'=>is_numeric($ref) && (float)$ref>0 ? (float)$ref/100 : null,
        'fba_fee'=>$fbaFee>0 ? $fbaFee : null,
        'url'=>'https://www.'.FBA_MARKETS[$mk]['host'].'/dp/'.(string)($p['asin'] ?? ''),
    ];
}

/** Einkaufspreis (Cent, Währung $from) in Marktplatzwährung $to umrechnen – EZB-Tageskurse (EUR-Basis). */
function fba_convert(int $cents, string $from, string $to, ?array $rates=null): int {
    $from=strtoupper($from); $to=strtoupper($to); if($from===$to || $cents===0) return $cents;
    $r=$rates ?? (function_exists('ch_fx_rates') ? (array)(ch_fx_rates()['rates'] ?? []) : []);
    $def=['EUR'=>1.0,'USD'=>1.16,'GBP'=>0.87];
    $rf=$from==='EUR' ? 1.0 : (float)($r[$from] ?? $def[$from] ?? 0); $rt=$to==='EUR' ? 1.0 : (float)($r[$to] ?? $def[$to] ?? 0);
    if($rf<=0 || $rt<=0) return $cents;
    return (int)round($cents/$rf*$rt);
}

/**
 * Gewinnrechnung je Einheit (alles in Cent der Marktplatzwährung).
 * Erlös netto = Preis / (1+USt) falls umsatzsteuerlich registriert (EU/UK), sonst Bruttopreis (Kleinunternehmer: USt bleibt Kosten).
 * Referral-Gebühr auf den Bruttopreis; FBA-Gebühr von Keepa, sonst Schätzung (markiert).
 */
function fba_profit(array $f, int $costCents, int $inboundCents, bool $vatReg=true): array {
    $price=(int)($f['price'] ?? 0); $mk=(string)$f['market'];
    if($price<=0) return ['ok'=>false,'reason'=>'Kein aktueller Preis auf '.FBA_MARKETS[$mk]['name']];
    $vat=FBA_MARKETS[$mk]['vat']; $net=$vatReg && $vat>0 ? (int)round($price/(1+$vat)) : $price;
    $refPct=$f['referral_pct'] ?? FBA_REFERRAL_DEFAULT; $ref=(int)round($price*$refPct);
    $feeEst=$f['fba_fee']===null; $fee=$feeEst ? FBA_FEE_FALLBACK[FBA_MARKETS[$mk]['cur']] : (int)$f['fba_fee'];
    $landed=$costCents+$inboundCents;
    $profit=$net-$ref-$fee-$landed;
    $flags=[];
    if(!empty($f['amazon_sells'])) $flags[]='Amazon verkauft selbst';
    if(($f['fba_offers'] ?? 0)>FBA_GOOD['max_fba_offers']) $flags[]='viele FBA-Anbieter ('.$f['fba_offers'].')';
    if($f['price_avg90'] && $price<(int)round($f['price_avg90']*0.85)) $flags[]='Preis 15 %+ unter 90-Tage-Schnitt';
    if($f['monthly_sold']!==null && $f['monthly_sold']<10) $flags[]='geringe Nachfrage';
    if($feeEst) $flags[]='FBA-Gebühr geschätzt';
    if($f['referral_pct']===null) $flags[]='Provision geschätzt (15 %)';
    $margin=$price>0 ? $profit/$price : 0; $roi=$landed>0 ? $profit/$landed : null;
    $good=$profit>0 && $margin>=FBA_GOOD['margin'] && ($roi===null || $roi>=FBA_GOOD['roi']) && empty($f['amazon_sells']) && (($f['fba_offers'] ?? 0)<=FBA_GOOD['max_fba_offers']);
    $verdict=$profit<=0 ? 'nicht profitabel' : ($good ? 'gute Chance' : 'prüfen');
    return ['ok'=>true,'price'=>$price,'net'=>$net,'referral'=>$ref,'referral_pct'=>round($refPct*100,1),'fba_fee'=>$fee,'fba_fee_estimated'=>$feeEst,
        'landed_cost'=>$landed,'profit'=>$profit,'margin_pct'=>round($margin*100,1),'roi_pct'=>$roi===null ? null : round($roi*100,1),
        'flags'=>$flags,'verdict'=>$verdict,'break_even_cost'=>$net-$ref-$fee-$inboundCents];
}
function fba_money(?int $cents, string $cur): string {
    if($cents===null) return '–'; $s=['EUR'=>'€','GBP'=>'£','USD'=>'$'][$cur] ?? $cur.' ';
    return ($cents<0 ? '−' : '').($cur==='EUR' ? number_format(abs($cents)/100,2,',','.').' '.$s : $s.number_format(abs($cents)/100,2,'.',','));
}
function fba_markets_in($v): array {
    $l=is_array($v) ? $v : preg_split('/[\s,;]+/',(string)$v); $o=[];
    foreach((array)$l as $m){ $m=strtolower(trim((string)$m)); $m=['co.uk'=>'uk','gb'=>'uk','com'=>'us','usa'=>'us'][$m] ?? $m; if(isset(FBA_MARKETS[$m])) $o[$m]=true; }
    return $o ? array_keys($o) : ['de'];
}
function fba_cents($v): int { if(is_numeric($v)) return (int)round((float)$v*100); $t=str_replace([' ','€','$','£'],'',(string)$v); $t=preg_match('/,\d{1,2}$/',$t) ? str_replace(['.',','],['','.'],$t) : str_replace(',','',$t); return is_numeric($t) ? (int)round((float)$t*100) : 0; }

/** Ein Produkt auf mehreren Marktplätzen analysieren. */
function fba_analyze(int $acc, string $ident, array $markets, int $costCents, string $costCur, int $inboundCents, bool $vatReg=true): array {
    $id=fba_ident($ident); if(!$id) return ['error'=>'bad_ident','hint'=>'Bitte ASIN (z. B. B0C1234567), Amazon-Link oder EAN/UPC angeben.'];
    [$key,$own]=fba_key($acc); if($key==='') return ['error'=>'no_key','hint'=>'Kein Keepa-Schlüssel hinterlegt (Plattform oder eigener).'];
    if(!$own && !fba_quota_take($acc,count($markets))) return ['error'=>'quota','hint'=>'Tageslimit mit dem Plattform-Schlüssel erreicht ('.FBA_PLATFORM_DAILY_PRODUCTS.' Produkte). Morgen wieder – oder eigenen Keepa-Schlüssel hinterlegen.'];
    $out=['ident'=>$id[1],'markets'=>[]];
    foreach($markets as $mk){
        $q=['domain'=>FBA_MARKETS[$mk]['domain'],'stats'=>90,'buybox'=>1,$id[0]=>$id[1]];
        $r=fba_keepa('product',$q,$key);
        if(isset($r['error'])){ $out['markets'][$mk]=['error'=>$r['error'],'hint'=>$r['hint'] ?? '']; if($r['error']!=='keepa_http') break; continue; }
        $p=(array)(($r['products'] ?? [])[0] ?? []);
        if(!$p || empty($p['asin'])){ $out['markets'][$mk]=['error'=>'not_found','hint'=>'Auf '.FBA_MARKETS[$mk]['name'].' nicht gefunden.']; continue; }
        $f=fba_product_facts($p,$mk);
        $cost=fba_convert($costCents,$costCur,FBA_MARKETS[$mk]['cur']); $inb=fba_convert($inboundCents,$costCur,FBA_MARKETS[$mk]['cur']);
        $out['markets'][$mk]=['facts'=>$f,'calc'=>$costCents>0 ? fba_profit($f,$cost,$inb,$vatReg) : null,'cost_converted'=>$cost];
        if(empty($out['title'])) $out['title']=$f['title'];
        if(empty($out['asin'])) $out['asin']=$f['asin'];
    }
    $out['tokens_left']=$r['tokensLeft'] ?? null;
    return $out;
}

/** Produkt-Recherche: Keepa Product Finder → Top-Kandidaten mit Kennzahlen. */
function fba_find(int $acc, array $in): array {
    $mk=fba_markets_in($in['market'] ?? 'de')[0]; $cur=FBA_MARKETS[$mk]['cur'];
    [$key,$own]=fba_key($acc); if($key==='') return ['error'=>'no_key','hint'=>'Kein Keepa-Schlüssel hinterlegt.'];
    $limit=max(1,min(20,(int)($in['limit'] ?? 10)));
    if(!$own && !fba_quota_take($acc,$limit+10)) return ['error'=>'quota','hint'=>'Tageslimit mit dem Plattform-Schlüssel erreicht.'];
    $sel=['perPage'=>50,'page'=>0,'sort'=>[['current_SALES','asc']],'current_SALES_gte'=>1];
    $kw=trim((string)($in['keyword'] ?? '')); if($kw!=='') $sel['title']=mb_substr($kw,0,80);
    if(!empty($in['category_id'])) $sel['rootCategory']=[(int)$in['category_id']];
    if(isset($in['price_min'])) $sel['current_BUY_BOX_SHIPPING_gte']=fba_cents($in['price_min']);
    if(isset($in['price_max'])) $sel['current_BUY_BOX_SHIPPING_lte']=fba_cents($in['price_max']);
    if(!empty($in['bsr_max'])) $sel['current_SALES_lte']=(int)$in['bsr_max'];
    if(!empty($in['max_fba_offers'])) $sel['current_COUNT_NEW_lte']=(int)$in['max_fba_offers'];
    if(!empty($in['exclude_amazon'])) $sel['availabilityAmazon']=[-1];
    if(!empty($in['min_monthly_sold'])) $sel['monthlySold_gte']=(int)$in['min_monthly_sold'];
    $r=fba_keepa('query',['domain'=>FBA_MARKETS[$mk]['domain'],'selection'=>json_encode($sel)],$key);
    if(isset($r['error'])) return $r;
    $asins=array_slice(array_values(array_filter((array)($r['asinList'] ?? []))),0,$limit);
    if(!$asins) return ['market'=>$mk,'count'=>0,'items'=>[],'hint'=>'Keine Treffer – Filter lockern (Preis, BSR, Konkurrenz).'];
    $pr=fba_keepa('product',['domain'=>FBA_MARKETS[$mk]['domain'],'stats'=>90,'buybox'=>1,'asin'=>implode(',',$asins)],$key);
    if(isset($pr['error'])) return $pr;
    $items=[];
    foreach((array)($pr['products'] ?? []) as $p){
        $f=fba_product_facts((array)$p,$mk);
        $max=$f['price'] ? fba_profit($f,0,0,true)['break_even_cost'] ?? null : null;
        $items[]=$f+['max_buy_cost_for_break_even'=>$max,'max_buy_cost_for_30pct_roi'=>$max!==null ? (int)floor($max/1.3) : null];
    }
    usort($items,fn($a,$b)=>((int)($b['monthly_sold'] ?? 0))<=>((int)($a['monthly_sold'] ?? 0)));
    return ['market'=>$mk,'currency'=>$cur,'total_matches'=>(int)($r['totalResults'] ?? count((array)($r['asinList'] ?? []))),'count'=>count($items),'items'=>$items,'tokens_left'=>$pr['tokensLeft'] ?? null];
}

/* ---------------------------------------------------------------- Werkzeuge */
function fba_tools(): array {
    $mk=['type'=>'array','items'=>['type'=>'string','enum'=>array_keys(FBA_MARKETS)],'description'=>'Marktplätze: de, fr, it, es, uk, us (Standard: de)'];
    return [
      ['name'=>'fba_analyze','description'=>'Amazon-FBA-Analyse EINES Produkts auf einem oder mehreren Marktplätzen: Buy-Box-Preis, 90-Tage-Schnitt, Bestseller-Rang, geschätzte Monatsverkäufe, FBA-Konkurrenz, ob Amazon selbst verkauft, Provision und FBA-Gebühr – und mit Einkaufspreis Gewinn, Marge, ROI und Urteil. Zahlen NUR aus diesem Werkzeug verwenden.',
       'input_schema'=>['type'=>'object','properties'=>[
         'product'=>['type'=>'string','description'=>'ASIN, Amazon-Produktlink oder EAN/UPC'],
         'markets'=>$mk,
         'buy_cost'=>['type'=>'number','description'=>'Einkaufspreis je Stück (netto), optional'],
         'cost_currency'=>['type'=>'string','enum'=>['EUR','USD','GBP'],'description'=>'Währung des Einkaufspreises (Standard EUR)'],
         'inbound_per_unit'=>['type'=>'number','description'=>'Versand zum Amazon-Lager je Stück (optional)'],
         'vat_registered'=>['type'=>'boolean','description'=>'Umsatzsteuerlich registriert (Standard ja; nein = USt bleibt Kosten)']],
       'required'=>['product']]],
      ['name'=>'fba_find','description'=>'Produkt-Recherche für Amazon FBA (Keepa Product Finder): Kandidaten nach Stichwort und Filtern, sortiert nach Nachfrage, mit maximalem Einkaufspreis für Break-even und 30 % ROI.',
       'input_schema'=>['type'=>'object','properties'=>[
         'market'=>['type'=>'string','enum'=>array_keys(FBA_MARKETS)],
         'keyword'=>['type'=>'string','description'=>'Stichwort im Produkttitel, z. B. „yoga matte“'],
         'category_id'=>['type'=>'integer','description'=>'Keepa/Amazon-Hauptkategorie-ID (optional)'],
         'price_min'=>['type'=>'number'],'price_max'=>['type'=>'number'],
         'bsr_max'=>['type'=>'integer','description'=>'Höchster Bestseller-Rang (z. B. 30000)'],
         'min_monthly_sold'=>['type'=>'integer'],'max_fba_offers'=>['type'=>'integer'],
         'exclude_amazon'=>['type'=>'boolean','description'=>'Produkte ausschließen, die Amazon selbst verkauft'],
         'limit'=>['type'=>'integer','description'=>'1–20 (Standard 10)']],
       'required'=>['market']]],
      ['name'=>'fba_watch','description'=>'Beobachtungsliste für den täglichen Bericht: add (Produkt + Marktplatz + Einkaufspreis), remove, list.',
       'input_schema'=>['type'=>'object','properties'=>[
         'action'=>['type'=>'string','enum'=>['add','remove','list']],
         'product'=>['type'=>'string','description'=>'ASIN oder Amazon-Link'],
         'market'=>['type'=>'string','enum'=>array_keys(FBA_MARKETS)],
         'buy_cost'=>['type'=>'number'],'cost_currency'=>['type'=>'string','enum'=>['EUR','USD','GBP']],
         'inbound_per_unit'=>['type'=>'number'],'note'=>['type'=>'string']],
       'required'=>['action']]],
      ['name'=>'fba_settings','description'=>'Einstellungen: täglicher E-Mail-Bericht (an/aus, Empfänger, Sprache) und optional eigener Keepa-API-Schlüssel (wird verschlüsselt gespeichert und nie angezeigt). Ohne Angaben: aktuellen Stand zeigen.',
       'input_schema'=>['type'=>'object','properties'=>[
         'report_on'=>['type'=>'boolean'],'report_email'=>['type'=>'string'],'report_lang'=>['type'=>'string','enum'=>['de','en','tr','fr','it','es']],
         'keepa_key'=>['type'=>'string','description'=>'Eigener Keepa-Schlüssel; leerer Text = entfernen']]]],
    ];
}
function fba_system_hint(int $acc, string $channel='studio'): string {
    return "\n\nAMAZON-FBA-PRODUKTFINDER (aktiv): Für jede Zahl zu Preisen, Rängen, Verkäufen, Gebühren oder Gewinn die Werkzeuge fba_analyze bzw. fba_find benutzen – nie schätzen oder erfinden. "
        ."Ergebnisse als kompakte Tabelle (Marktplatz, Preis, BSR, Verkäufe/Monat, FBA-Anbieter, Gewinn, Marge, ROI, Urteil) und darunter Risiken (Marke/Markenschutz, Amazon im Angebot, Preisverfall, Gefahrgut/Größe, Zulassungen) und klare nächste Schritte. "
        ."Fehlt der Einkaufspreis, nenne den maximalen Einkaufspreis für Break-even. Marketing: auf Wunsch Listing-Titel, 5 Bullet Points, Backend-Keywords und A+-Ideen schreiben – nur mit belegbaren Produkteigenschaften, keine Heilversprechen, keine fremden Marken nutzen. "
        ."Keine Rechts- oder Steuerberatung; Gewinne sind Schätzungen (Lagergebühren, Retouren, Werbung nicht enthalten)."
        .($acc===0 ? ' DEMO: Beispiel-Konto.' : '');
}

function fba_exec(int $acc, string $name, array $in, string $channel, string $from=''): array {
    try{
        fba_bootstrap(); $p=th_db();
        if($name==='fba_analyze'){
            $mk=fba_markets_in($in['markets'] ?? ['de']);
            return fba_analyze($acc,(string)($in['product'] ?? ''),$mk,fba_cents($in['buy_cost'] ?? 0),strtoupper((string)($in['cost_currency'] ?? 'EUR')),fba_cents($in['inbound_per_unit'] ?? 0),($in['vat_registered'] ?? true)!==false);
        }
        if($name==='fba_find') return fba_find($acc,$in);
        if($name==='fba_watch'){
            if($acc<=0) return ['error'=>'demo','hint'=>'In der Demo wird nichts gespeichert.'];
            $act=(string)($in['action'] ?? 'list');
            if($act==='list'){ $s=$p->prepare("SELECT asin,market,cost_cents,cost_cur,inbound_cents,note,last_at FROM fba_watch WHERE account_id=? ORDER BY id"); $s->execute([$acc]); return ['items'=>$s->fetchAll(PDO::FETCH_ASSOC)]; }
            $id=fba_ident((string)($in['product'] ?? '')); if(!$id || $id[0]!=='asin') return ['error'=>'bad_ident','hint'=>'Für die Beobachtungsliste bitte die ASIN oder den Amazon-Link angeben.'];
            $mk=fba_markets_in($in['market'] ?? 'de')[0];
            if($act==='remove'){ $s=$p->prepare("DELETE FROM fba_watch WHERE account_id=? AND asin=? AND market=?"); $s->execute([$acc,$id[1],$mk]); return ['removed'=>$s->rowCount()]; }
            $n=(int)$p->query("SELECT COUNT(*) FROM fba_watch WHERE account_id=".(int)$acc)->fetchColumn(); if($n>=100) return ['error'=>'limit','hint'=>'Höchstens 100 Produkte auf der Beobachtungsliste.'];
            $p->prepare("INSERT INTO fba_watch(account_id,asin,market,cost_cents,cost_cur,inbound_cents,note) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE cost_cents=VALUES(cost_cents),cost_cur=VALUES(cost_cur),inbound_cents=VALUES(inbound_cents),note=VALUES(note)")
              ->execute([$acc,$id[1],$mk,fba_cents($in['buy_cost'] ?? 0),strtoupper((string)($in['cost_currency'] ?? 'EUR')),fba_cents($in['inbound_per_unit'] ?? 0),mb_substr(trim((string)($in['note'] ?? '')),0,200)]);
            return ['added'=>$id[1],'market'=>$mk,'hint'=>'Steht ab morgen im täglichen Bericht (falls aktiviert).'];
        }
        if($name==='fba_settings'){
            if($acc<=0) return ['error'=>'demo','hint'=>'In der Demo wird nichts gespeichert.'];
            $cur=fba_settings($acc); $upd=false;
            if(array_key_exists('keepa_key',$in)){ $k=trim((string)$in['keepa_key']); if($k!=='' && !preg_match('/^[A-Za-z0-9]{40,80}$/',$k)) return ['error'=>'bad_key','hint'=>'Das sieht nicht wie ein Keepa-API-Schlüssel aus.']; th_secret_set($acc,'keepa_key',$k); $upd=true; }
            if(array_key_exists('report_on',$in)){ $cur['report_on']=!empty($in['report_on']) ? 1 : 0; $upd=true; }
            if(array_key_exists('report_email',$in)){ $e=trim((string)$in['report_email']); if($e!=='' && !filter_var($e,FILTER_VALIDATE_EMAIL)) return ['error'=>'bad_email']; $cur['report_email']=$e; $upd=true; }
            if(array_key_exists('report_lang',$in)){ $cur['report_lang']=(string)$in['report_lang']; $upd=true; }
            if($upd) $p->prepare("INSERT INTO fba_settings(account_id,report_on,report_email,report_lang) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE report_on=VALUES(report_on),report_email=VALUES(report_email),report_lang=VALUES(report_lang),updated_at=NOW()")
                ->execute([$acc,(int)$cur['report_on'],(string)$cur['report_email'],(string)$cur['report_lang']]);
            $own=th_secret($acc,'keepa_key')!=='';
            return ['report_on'=>(bool)$cur['report_on'],'report_email'=>$cur['report_email'] ?: '(Konto-E-Mail)','report_lang'=>$cur['report_lang'],
                'keepa'=>$own ? 'eigener Schlüssel hinterlegt' : (fba_platform_key()!=='' ? 'Plattform-Schlüssel ('.FBA_PLATFORM_DAILY_PRODUCTS.' Produkte/Tag)' : 'kein Schlüssel'),'saved'=>$upd];
        }
        return ['error'=>'unknown_tool'];
    }catch(Throwable $e){ error_log('CH fba_exec: '.$e->getMessage()); return ['error'=>'internal','hint'=>'Vorübergehender Fehler – bitte erneut versuchen.']; }
}
function fba_settings(int $acc): array {
    fba_bootstrap(); $s=th_db()->prepare("SELECT * FROM fba_settings WHERE account_id=?"); $s->execute([$acc]);
    return $s->fetch(PDO::FETCH_ASSOC) ?: ['account_id'=>$acc,'report_on'=>0,'report_email'=>'','report_lang'=>'de','last_report'=>null];
}

/* ---------------------------------------------------------------- Täglicher Bericht */
/** Bericht eines Kontos bauen (HTML) und Beobachtungsliste aktualisieren. null = nichts zu berichten. */
function fba_report_build(int $acc): ?array {
    fba_bootstrap(); $p=th_db();
    $s=$p->prepare("SELECT * FROM fba_watch WHERE account_id=? ORDER BY market, id"); $s->execute([$acc]); $rows=$s->fetchAll(PDO::FETCH_ASSOC);
    if(!$rows) return null;
    $tr=''; $alerts=[];
    foreach($rows as $w){
        $a=fba_analyze($acc,(string)$w['asin'],[(string)$w['market']],(int)$w['cost_cents'],(string)$w['cost_cur'],(int)$w['inbound_cents']);
        $m=$a['markets'][$w['market']] ?? null;
        if(!$m || isset($m['error'])){ $tr.='<tr><td>'.htmlspecialchars((string)$w['asin']).'</td><td>'.strtoupper((string)$w['market']).'</td><td colspan="6">'.htmlspecialchars((string)($m['hint'] ?? $a['hint'] ?? 'keine Daten')).'</td></tr>'; if(($a['error'] ?? '')==='quota' || ($m['error'] ?? '')==='keepa_rate') break; continue; }
        $f=$m['facts']; $c=$m['calc']; $cur=$f['currency'];
        $old=json_decode((string)($w['last_json'] ?? ''),true) ?: [];
        if(!empty($old['price']) && $f['price'] && abs($f['price']-$old['price'])/$old['price']>=0.05) $alerts[]=$f['asin'].' ('.strtoupper($f['market']).'): Preis '.fba_money((int)$old['price'],$cur).' → '.fba_money($f['price'],$cur);
        if(empty($old['amazon_sells']) && !empty($f['amazon_sells']) && $old) $alerts[]=$f['asin'].' ('.strtoupper($f['market']).'): Amazon verkauft jetzt selbst';
        $p->prepare("UPDATE fba_watch SET last_json=?, last_at=NOW() WHERE id=?")->execute([json_encode(['price'=>$f['price'],'bsr'=>$f['bsr'],'amazon_sells'=>$f['amazon_sells'],'fba_offers'=>$f['fba_offers']]),(int)$w['id']]);
        $tr.='<tr><td><a href="'.htmlspecialchars($f['url']).'">'.htmlspecialchars($f['asin']).'</a><br><span style="color:#666;font-size:12px">'.htmlspecialchars(mb_substr($f['title'],0,70)).'</span></td>'
            .'<td>'.strtoupper($f['market']).'</td><td>'.fba_money($f['price'],$cur).'</td><td>'.($f['bsr'] ? number_format($f['bsr'],0,',','.') : '–').'</td>'
            .'<td>'.($f['monthly_sold'] ?? '–').'</td><td>'.($f['fba_offers'] ?? '–').'</td>'
            .'<td>'.($c && $c['ok'] ? fba_money($c['profit'],$cur).' · '.$c['margin_pct'].' %'.($c['roi_pct']!==null ? ' · ROI '.$c['roi_pct'].' %' : '') : '–').'</td>'
            .'<td>'.htmlspecialchars($c && $c['ok'] ? $c['verdict'] : '').'</td></tr>';
    }
    $th='<tr style="background:#f3f4f6;text-align:left"><th>Produkt</th><th>Markt</th><th>Preis</th><th>BSR</th><th>Verk./Monat</th><th>FBA</th><th>Gewinn · Marge · ROI</th><th>Urteil</th></tr>';
    $html='<div style="font-family:Arial,sans-serif;font-size:14px;color:#111"><h2 style="margin:0 0 8px">Amazon-FBA-Bericht · '.date('d.m.Y').'</h2>'
        .($alerts ? '<div style="background:#fff7ed;border:1px solid #fdba74;border-radius:8px;padding:10px 12px;margin:0 0 12px"><b>Änderungen seit gestern</b><br>'.implode('<br>',array_map('htmlspecialchars',$alerts)).'</div>' : '')
        .'<table cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:13px" border="1" bordercolor="#e5e7eb">'.$th.$tr.'</table>'
        .'<p style="color:#666;font-size:12px">Daten: Keepa. Gewinn ohne Lagergebühren, Retouren und Werbung. Beobachtungsliste und Einstellungen im Studio (Amazon-FBA-Produktfinder).</p></div>';
    return ['html'=>$html,'alerts'=>count($alerts),'items'=>count($rows)];
}
/** Billige Vorprüfung (ohne DB): nach 06:00 Berlin und heute noch nicht fertig? */
function fba_tick_due(): bool {
    $now=new DateTimeImmutable('now',new DateTimeZone('Europe/Berlin')); if((int)$now->format('G')<6) return false;
    $mark=__DIR__.'/cache/fba-daily.txt'; return !(is_file($mark) && trim((string)@file_get_contents($mark))===$now->format('Y-m-d'));
}
/** Einmal täglich nach 06:00 (Europe/Berlin) Berichte versenden – in Portionen, höchstens $budget Sekunden. */
function fba_daily_tick(int $budget=20, bool $force=false): array {
    $out=['sent'=>0,'skipped'=>0,'done'=>false];
    try{
        $tz=new DateTimeZone('Europe/Berlin'); $now=new DateTimeImmutable('now',$tz);
        if(!$force && (int)$now->format('G')<6) return $out;
        $dir=__DIR__.'/cache'; if(!is_dir($dir)) @mkdir($dir,0750,true);
        $mark=$dir.'/fba-daily.txt'; $today=$now->format('Y-m-d');
        if(!$force && is_file($mark) && trim((string)@file_get_contents($mark))===$today) return $out+['done'=>true];
        $lk=@fopen($dir.'/fba-daily.lock','c'); if(!$lk || !flock($lk,LOCK_EX|LOCK_NB)) return $out;
        fba_bootstrap(); $p=th_db(); $t0=time();
        $s=$p->prepare("SELECT s.account_id, s.report_email, a.email FROM fba_settings s JOIN token_accounts a ON a.id=s.account_id WHERE s.report_on=1 AND (s.last_report IS NULL OR s.last_report<?)");
        $s->execute([$today]); $rest=0;
        foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r){
            if(time()-$t0>$budget){ $rest++; continue; }
            $acc=(int)$r['account_id'];
            $p->prepare("UPDATE fba_settings SET last_report=? WHERE account_id=?")->execute([$today,$acc]);
            if(!fba_enabled($acc)){ $out['skipped']++; continue; }
            $rep=fba_report_build($acc); if(!$rep){ $out['skipped']++; continue; }
            $to=trim((string)$r['report_email']) ?: (string)$r['email'];
            if(th_mail($to,'Amazon-FBA-Bericht '.$now->format('d.m.Y').($rep['alerts'] ? ' · '.$rep['alerts'].' Änderung(en)' : ''),$rep['html'])) $out['sent']++;
        }
        if($rest===0){ @file_put_contents($mark,$today); $out['done']=true; }
        flock($lk,LOCK_UN); fclose($lk);
    }catch(Throwable $e){ error_log('CH fba_daily_tick: '.$e->getMessage()); }
    return $out;
}
/* Ohne Server-Cron: nach dem Absenden der Antwort eines Web-Aufrufs (nicht CLI, nicht in Tests) */
if(PHP_SAPI!=='cli' && !defined('FBA_NO_TICK')){
    register_shutdown_function(function(){ if(!fba_tick_due()) return; if(function_exists('fastcgi_finish_request')) @fastcgi_finish_request(); fba_daily_tick(); });
}
