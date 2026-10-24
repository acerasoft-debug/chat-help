<?php
/**
 * ChatHelp — Rechen-Werkzeuge für den Marketing-Agenten und den Amazon-FBA-Agenten („growth“)
 * ===========================================================================================
 * Die KI rechnet nicht selbst: Marge, Gebühren, ACoS, Nachbestellmenge, Signifikanz und Listing-Grenzen kommen aus diesen
 * deterministischen Funktionen (reine Mathematik, keine Datenbank, keine externen Dienste, keine Live-Daten von Amazon).
 * Gebühren (FBA-Versandgebühr, Verkaufsgebühr in %) liefert der Händler aus Seller Central bzw. der Wissensbasis – wir raten sie nie.
 *
 * Modul-Schnittstelle (agent-tools.php): growth_enabled · growth_tools · growth_exec · growth_system_hint
 * Aktiv nur, wenn der laufende Chat einen der Agenten aus GROWTH_PLANS nutzt ($GLOBALS['ch_current_plan'], gesetzt von tokens-api.php / concierge.php).
 */
const GROWTH_PLANS = ['agent-marketing'=>'marketing','agent-amazon'=>'amazon'];
/** Werkzeuge je Agent. */
const GROWTH_TOOLSETS = [
  'marketing' => ['campaign_calc','ab_test','utm_link','ppc_metrics'],
  'amazon'    => ['fba_profit','ppc_metrics','restock_plan','listing_check','ab_test'],
];

/** Agent des laufenden Chats (Basis-ID, auch für Einmalkauf/Betrieb/BYOK) → 'marketing' | 'amazon' | null. */
function growth_kind(): ?string {
    $p=(string)($GLOBALS['ch_current_plan'] ?? ''); if($p==='') return null;
    if(isset(GROWTH_PLANS[$p])) return GROWTH_PLANS[$p];
    if(function_exists('ch_plan') && ($pl=ch_plan($p)) && isset(GROWTH_PLANS[$pl['base'] ?? ''])) return GROWTH_PLANS[$pl['base']];
    return null;
}
function growth_enabled(int $acc): bool { return growth_kind()!==null; }

/* ---------- Hilfen ---------- */
/** Zahl aus Modell-Eingabe: akzeptiert 12,90 · 12.90 · "1.234,50" · "19 %" · "€ 3,59". null = fehlt/ungültig. */
function growth_num($v): ?float {
    if(is_int($v) || is_float($v)) return is_finite((float)$v) ? (float)$v : null;
    if(!is_string($v)) return null;
    $s=trim(str_replace(["\u{00a0}",' ','€','$','£','%','EUR','USD','GBP'],'',$v)); if($s==='') return null;
    if(preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/',$s)) $s=str_replace(['.',','],['','.'],$s);       // 1.234,50
    elseif(preg_match('/^-?\d{1,3}(,\d{3})+(\.\d+)?$/',$s)) $s=str_replace(',','',$s);              // 1,234.50
    else $s=str_replace(',','.',$s);
    return is_numeric($s) ? (float)$s : null;
}
function growth_r(float $x, int $d=2): float { $r=round($x,$d); return $r==0.0 ? 0.0 : $r; }   // keine -0
function growth_pct(float $part, float $whole, int $d=1): ?float { return $whole!=0.0 ? growth_r($part/$whole*100,$d) : null; }
function growth_missing(array $names, string $hint): array { return ['error'=>'missing_input','missing'=>$names,'hint'=>$hint]; }
/** Liste aus Array oder Text (Zeilen/Komma/Semikolon). */
function growth_list($v, string $sep="/[\r\n;]+|,(?!\d)/u"): array {
    if(is_array($v)) $a=$v; else $a=preg_split($sep,(string)$v) ?: [];
    return array_values(array_filter(array_map(fn($x)=>trim(is_scalar($x) ? (string)$x : ''),$a),fn($x)=>$x!==''));
}

/* ---------- 1) Amazon FBA: Stückgewinn, Marge, ROI, Break-even-ACoS, Break-even-Preis ---------- */
function growth_fba_profit(array $in): array {
    $price=growth_num($in['price'] ?? null); $cogs=growth_num($in['cogs'] ?? null); $fba=growth_num($in['fba_fee'] ?? null);
    $miss=[]; if($price===null || $price<=0) $miss[]='price'; if($cogs===null || $cogs<0) $miss[]='cogs'; if($fba===null || $fba<0) $miss[]='fba_fee';
    if($miss) return growth_missing($miss,'Bitte Verkaufspreis (brutto), Einkaufspreis je Stück (EK inkl. Fracht bis Lager) und die FBA-Versandgebühr aus Seller Central bzw. dem Amazon-Umsatzrechner nennen – die Gebühr wird nicht geschätzt.');
    $vat=growth_num($in['vat_pct'] ?? null) ?? 19.0; $ref=growth_num($in['referral_pct'] ?? null) ?? 15.0; $refMin=growth_num($in['referral_min'] ?? null) ?? 0.30;
    $inb=growth_num($in['inbound_per_unit'] ?? null) ?? 0.0; $sto=growth_num($in['storage_per_unit_month'] ?? null) ?? 0.0; $stoM=growth_num($in['storage_months'] ?? null) ?? 1.0;
    $ret=growth_num($in['returns_pct'] ?? null) ?? 0.0; $other=growth_num($in['other_per_unit'] ?? null) ?? 0.0;
    $adUnit=growth_num($in['ad_per_unit'] ?? null); $tacos=growth_num($in['tacos_pct'] ?? null); $units=growth_num($in['units_month'] ?? null);
    if($vat<0 || $vat>30 || $ref<0 || $ref>50 || $ret<0 || $ret>100) return ['error'=>'out_of_range','hint'=>'USt 0–30 %, Verkaufsgebühr 0–50 %, Retourenquote 0–100 %.'];
    $net=$price/(1+$vat/100); $vatAmt=$price-$net;
    $referral=max($refMin,$price*$ref/100);                                   // Verkaufsgebühr auf den Bruttopreis (EU), mit Mindestgebühr
    $storage=$sto*max(0,$stoM);
    $returns=$ret/100*($cogs+$fba);                                            // Annahme: zurückgesandte Stücke sind nicht wieder verkaufbar (vorsichtig)
    $fixed=$cogs+$fba+$inb+$storage+$returns+$other;
    $pre=$net-$referral-$fixed;                                                // Gewinn vor Werbung
    $ad=$adUnit ?? ($tacos!==null ? $price*$tacos/100 : 0.0);
    $profit=$pre-$ad;
    /* Break-even-Preis ohne Werbung: P/(1+v) − max(min, r·P) − fixed = 0 */
    $den=1/(1+$vat/100)-$ref/100; $bePrice=null;
    if($den>0){ $p=$fixed/$den; if($p*$ref/100<$refMin) $p=($fixed+$refMin)*(1+$vat/100); $bePrice=growth_r($p); }
    $o=['inputs_used'=>['price'=>$price,'vat_pct'=>$vat,'referral_pct'=>$ref,'referral_min'=>$refMin,'fba_fee'=>$fba,'cogs'=>$cogs,'inbound_per_unit'=>$inb,'storage_per_unit_month'=>$sto,'storage_months'=>$stoM,'returns_pct'=>$ret,'other_per_unit'=>$other,'ad_per_unit'=>growth_r($ad)],
        'per_unit'=>['price_gross'=>growth_r($price),'vat'=>growth_r($vatAmt),'price_net'=>growth_r($net),'referral_fee'=>growth_r($referral),'fba_fee'=>growth_r($fba),'cogs'=>growth_r($cogs),
          'inbound'=>growth_r($inb),'storage'=>growth_r($storage),'returns_allowance'=>growth_r($returns),'other'=>growth_r($other),'profit_before_ads'=>growth_r($pre),'ads'=>growth_r($ad),'profit'=>growth_r($profit)],
        'margin_pct'=>growth_pct($profit,$net),'margin_before_ads_pct'=>growth_pct($pre,$net),'roi_pct'=>($cogs+$inb)>0 ? growth_pct($profit,$cogs+$inb) : null,
        'break_even_acos_pct'=>$pre>0 ? growth_r($pre/$price*100,1) : 0.0,'break_even_price'=>$bePrice,
        'verdict'=>$profit<=0 ? 'loss' : (($profit/$net)<0.10 ? 'thin' : (($profit/$net)<0.20 ? 'ok' : 'healthy')),
        'notes'=>['Verkaufsgebühr auf den Bruttopreis gerechnet (EU-Praxis), Marge bezogen auf den Nettopreis.','Break-even-ACoS = Gewinn vor Werbung ÷ Bruttopreis – darüber verliert jede Werbe-Bestellung Geld.','Retouren vorsichtig angesetzt: Ware gilt als nicht wieder verkaufbar.','Gebühren laut Eingabe – Seller Central bzw. Amazon-Umsatzrechner sind verbindlich.']];
    if($units!==null && $units>0) $o['month']=['units'=>$units,'revenue_gross'=>growth_r($price*$units),'profit'=>growth_r($profit*$units),'fees_total'=>growth_r(($referral+$fba)*$units)];
    return $o;
}

/* ---------- 2) Werbung (Amazon PPC, Google/Meta): ACoS, ROAS, TACoS, CPC, CVR, Gebotsvorschlag ---------- */
function growth_ppc_metrics(array $in): array {
    $spend=growth_num($in['spend'] ?? null); $sales=growth_num($in['sales'] ?? null);
    if($spend===null || $spend<0 || $sales===null || $sales<0){
        $miss=[]; if($spend===null || $spend<0) $miss[]='spend'; if($sales===null || $sales<0) $miss[]='sales';
        return growth_missing($miss,'Bitte Werbekosten (Spend) und Werbeumsatz (Sales) des Zeitraums nennen; Klicks, Impressionen und Bestellungen machen die Auswertung genauer.');
    }
    $clicks=growth_num($in['clicks'] ?? null); $impr=growth_num($in['impressions'] ?? null); $orders=growth_num($in['orders'] ?? null); $total=growth_num($in['total_sales'] ?? null);
    $target=growth_num($in['target_acos_pct'] ?? null); $be=growth_num($in['break_even_acos_pct'] ?? null); $bid=growth_num($in['current_bid'] ?? null);
    $acos=$sales>0 ? $spend/$sales*100 : null; $roas=$spend>0 ? $sales/$spend : null;
    $cpc=($clicks && $clicks>0) ? $spend/$clicks : null; $ctr=($impr && $impr>0 && $clicks!==null) ? $clicks/$impr*100 : null;
    $cvr=($clicks && $clicks>0 && $orders!==null) ? $orders/$clicks*100 : null; $cpa=($orders && $orders>0) ? $spend/$orders : null; $aov=($orders && $orders>0) ? $sales/$orders : null;
    $tacos=($total && $total>0) ? $spend/$total*100 : null;
    $o=['acos_pct'=>$acos!==null ? growth_r($acos,1) : null,'roas'=>$roas!==null ? growth_r($roas,2) : null,'cpc'=>$cpc!==null ? growth_r($cpc) : null,'ctr_pct'=>$ctr!==null ? growth_r($ctr,2) : null,
        'cvr_pct'=>$cvr!==null ? growth_r($cvr,1) : null,'cpa'=>$cpa!==null ? growth_r($cpa) : null,'aov'=>$aov!==null ? growth_r($aov) : null,'tacos_pct'=>$tacos!==null ? growth_r($tacos,1) : null];
    $status='no_sales';
    if($acos!==null){
        if($be!==null && $acos>$be) $status='unprofitable';
        elseif($target!==null && $acos>$target*1.05) $status='above_target';
        elseif($target!==null && $acos<$target*0.8) $status='room_to_scale';
        else $status=$target!==null ? 'on_target' : 'no_target';
    }
    if($clicks!==null && $clicks<20) $status='too_little_data';
    $o['status']=$status;
    $ref=$bid ?? $cpc;
    if($ref!==null && $target!==null && $acos!==null && $acos>0 && $status!=='too_little_data'){
        $f=max(0.7,min(1.3,$target/$acos)); $o['bid_suggestion']=['basis'=>$bid!==null ? 'current_bid' : 'cpc','from'=>growth_r($ref),'to'=>growth_r($ref*$f),'change_pct'=>growth_r(($f-1)*100,0),
          'rule'=>'Gebot × Ziel-ACoS ÷ Ist-ACoS, je Schritt höchstens ±30 %; nach 7–14 Tagen erneut prüfen.'];
    } elseif($sales==0.0 && $clicks!==null && $clicks>=20){
        $o['bid_suggestion']=['rule'=>'Viele Klicks ohne Verkauf: Suchbegriffe prüfen, irrelevante als negative Keywords ausschließen, Gebot senken oder pausieren.'];
    }
    $o['notes']=['ACoS = Werbekosten ÷ Werbeumsatz · ROAS = Werbeumsatz ÷ Werbekosten · TACoS = Werbekosten ÷ Gesamtumsatz.','Unter 20 Klicks ist die Aussage unsicher – erst Daten sammeln.'];
    return $o;
}

/* ---------- 3) FBA-Nachschub: Reichweite, Out-of-Stock-Datum, Bestellpunkt, Bestellmenge ---------- */
function growth_restock_plan(array $in): array {
    $daily=growth_num($in['units_per_day'] ?? null); $u30=growth_num($in['units_30d'] ?? null);
    if(($daily===null || $daily<=0) && $u30!==null && $u30>0) $daily=$u30/30;
    $stock=growth_num($in['stock_available'] ?? null); $lead=growth_num($in['lead_time_days'] ?? null);
    $miss=[]; if($daily===null || $daily<=0) $miss[]='units_per_day'; if($stock===null || $stock<0) $miss[]='stock_available'; if($lead===null || $lead<0) $miss[]='lead_time_days';
    if($miss) return growth_missing($miss,'Bitte Verkäufe pro Tag (oder der letzten 30 Tage), verfügbaren FBA-Bestand und die Lieferzeit ab Bestellung bis Ankunft am Amazon-Lager (Produktion + Transport) nennen.');
    $inbound=growth_num($in['inbound'] ?? null) ?? 0.0; $check=growth_num($in['checkin_days'] ?? null) ?? 10.0; $safety=growth_num($in['safety_days'] ?? null) ?? 14.0;
    $cover=growth_num($in['cover_days'] ?? null) ?? 60.0; $moq=growth_num($in['moq'] ?? null); $carton=growth_num($in['units_per_carton'] ?? null);
    $tz=new DateTimeZone('Europe/Berlin'); $today=null;
    if(!empty($in['today']) && is_string($in['today']) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$in['today'])){ try{ $today=new DateTimeImmutable($in['today'],$tz); }catch(Throwable $e){ $today=null; } }
    $today ??= new DateTimeImmutable('today',$tz);
    $total=$stock+$inbound; $doc=$total/$daily; $sellDoc=$stock/$daily;
    $pipe=$lead+$check;                                                         // Tage bis neue Ware verkaufbar ist
    $rop=$daily*($pipe+$safety);                                                // Bestellpunkt (Stück)
    $slack=$doc-$pipe-$safety;                                                  // Tage bis spätestens bestellt werden muss
    $need=$daily*($pipe+$cover)+$daily*$safety-$total; $qty=max(0,(int)ceil($need));
    if($qty>0 && $moq!==null && $moq>0) $qty=max($qty,(int)ceil($moq));
    if($qty>0 && $carton!==null && $carton>0) $qty=(int)(ceil($qty/$carton)*$carton);
    $o=['units_per_day'=>growth_r($daily,2),'stock_total'=>growth_r($total,0),'days_of_cover'=>growth_r($doc,0),'days_of_cover_sellable_only'=>growth_r($sellDoc,0),
        'stockout_date'=>$today->modify('+'.max(0,(int)floor($doc)).' days')->format('Y-m-d'),'reorder_point_units'=>(int)ceil($rop),
        'order_now'=>$total<=$rop,'latest_order_date'=>$slack>=0 ? $today->modify('+'.(int)floor($slack).' days')->format('Y-m-d') : null,'overdue_days'=>$slack<0 ? (int)ceil(-$slack) : 0,
        'order_qty'=>$qty,'new_stock_sellable_from'=>$today->modify('+'.(int)ceil($pipe).' days')->format('Y-m-d'),
        'assumptions'=>['lead_time_days'=>$lead,'checkin_days'=>$check,'safety_days'=>$safety,'cover_days'=>$cover,'moq'=>$moq,'units_per_carton'=>$carton]];
    if($doc<$pipe){
        $gap=(int)ceil($pipe-$doc); $o['stockout_risk']=['gap_days'=>$gap,'lost_units_estimate'=>(int)ceil($gap*$daily),
          'options'=>['Teilmenge per Luftfracht bzw. Expressversand','Werbung (PPC) auf diesem Artikel drosseln','Preis leicht anheben, um die Reichweite zu strecken','Restbestand aus eigenem Lager oder Prep-Center sofort einsenden']];
    }
    $o['notes']=['Bestellpunkt = Tagesabsatz × (Lieferzeit + Einlagerung bei Amazon + Sicherheitstage).','Lagerlimits/Kapazität in Seller Central prüfen, bevor große Mengen eingesendet werden; Langzeitlagergebühren bei mehr als ca. 6 Monaten Reichweite beachten.'];
    if($doc>180) $o['overstock_warning']='Mehr als 180 Tage Reichweite – Risiko von Langzeitlagergebühren; Aktion, Gutschein oder Abverkauf über andere Kanäle prüfen.';
    return $o;
}

/* ---------- 4) Amazon-Listing prüfen: Längen, Byte-Grenze Suchbegriffe, riskante Aussagen, Keyword-Abdeckung ---------- */
const GROWTH_RISKY = [
  'bestseller'=>'Werbeaussage/Ranking – unzulässig im Listing','best seller'=>'Werbeaussage/Ranking – unzulässig im Listing','nr. 1'=>'Ranking-Aussage ohne Beleg','#1'=>'Ranking-Aussage ohne Beleg','number 1'=>'Ranking-Aussage ohne Beleg',
  'top seller'=>'Werbeaussage – unzulässig','angebot'=>'Preis-/Aktionsaussage gehört nicht in Titel/Bullets','sale'=>'Preis-/Aktionsaussage gehört nicht in Titel/Bullets','rabatt'=>'Preis-/Aktionsaussage gehört nicht in Titel/Bullets',
  'gratis'=>'Preis-/Versandaussage unzulässig','kostenlos'=>'Preis-/Versandaussage unzulässig','free shipping'=>'Versandaussage unzulässig','kostenloser versand'=>'Versandaussage unzulässig',
  'heilt'=>'Heilversprechen (HWG/Amazon-Richtlinie)','heilung'=>'Heilversprechen','cure'=>'Heilversprechen','antibakteriell'=>'Biozid-Aussage – nur mit Zulassung','antibacterial'=>'Biozid-Aussage – nur mit Zulassung',
  'antiviral'=>'Biozid-/Gesundheitsaussage – nur mit Zulassung','umweltfreundlich'=>'Pauschale Umweltaussage – EU-Recht (Green Claims) verlangt Beleg','eco-friendly'=>'Pauschale Umweltaussage – Beleg nötig',
  'klimaneutral'=>'Klimaaussage – nur mit Nachweis, in der EU stark eingeschränkt','co2-neutral'=>'Klimaaussage – nur mit Nachweis','nachhaltig'=>'Pauschale Umweltaussage – konkret belegen',
  'garantiert'=>'Garantieaussage – nur mit konkreten Garantiebedingungen','100%'=>'Absolute Aussage – nur mit Beleg','100 %'=>'Absolute Aussage – nur mit Beleg','fda'=>'Behördenbezug – nur mit Nachweis',
  'zertifiziert'=>'Zertifikat konkret benennen und belegen','beste qualität'=>'Superlativ ohne Beleg','best quality'=>'Superlativ ohne Beleg',
];
function growth_listing_check(array $in): array {
    $title=trim((string)($in['title'] ?? '')); $bul=growth_list($in['bullets'] ?? [],"/[\r\n]+/u"); $desc=trim((string)($in['description'] ?? '')); $back=trim((string)($in['backend_terms'] ?? ''));
    $kws=growth_list($in['keywords'] ?? []); $brand=trim((string)($in['brand'] ?? ''));
    if($title==='' && !$bul && $back==='') return growth_missing(['title','bullets','backend_terms'],'Bitte mindestens Titel, Bullet Points oder die allgemeinen Suchbegriffe (Backend) einfügen.');
    $issues=[]; $add=function(string $lvl,string $where,string $msg) use(&$issues){ $issues[]=['level'=>$lvl,'where'=>$where,'msg'=>$msg]; };
    $tl=mb_strlen($title);
    if($title!==''){
        if($tl>200) $add('error','title',"Titel hat $tl Zeichen – Amazon erlaubt höchstens 200 (inkl. Leerzeichen).");
        elseif($tl<40) $add('warn','title',"Titel mit $tl Zeichen ist sehr kurz – wichtigste Suchbegriffe und Kernmerkmale ergänzen.");
        if(preg_match_all('/[!$?_{}^¬¦]/u',$title,$m)) $add('error','title','Sonderzeichen im Titel ('.implode(' ',array_unique($m[0])).') sind nicht erlaubt, außer sie gehören zum Markennamen.');
        $words=array_count_values(array_filter(preg_split('/[^\p{L}\p{N}]+/u',mb_strtolower($title)) ?: [],fn($w)=>mb_strlen($w)>3));
        $rep=array_keys(array_filter($words,fn($c)=>$c>2)); if($rep) $add('error','title','Wörter mehr als zweimal im Titel: '.implode(', ',$rep).' – Amazon unterdrückt solche Titel.');
        if($brand!=='' && mb_stripos($title,$brand)!==0) $add('warn','title','Titel sollte mit der Marke „'.$brand.'“ beginnen.');
        if($tl>80) $add('info','title','Mobil sind meist nur die ersten ca. 80 Zeichen sichtbar – Produktart und wichtigstes Merkmal nach vorn.');
    }
    if($bul){
        if(count($bul)<5) $add('warn','bullets','Nur '.count($bul).' Bullet Points – 5 nutzen (Nutzen zuerst, dann Merkmal).');
        if(count($bul)>5) $add('info','bullets',count($bul).' Bullet Points – angezeigt werden in vielen Kategorien 5.');
        foreach($bul as $i=>$b){ $l=mb_strlen($b); $n=$i+1;
            if($l>255) $add('warn',"bullet_$n","Bullet $n hat $l Zeichen – Richtwert höchstens 255 (kategorieabhängig, Styleguide prüfen).");
            if($l<10) $add('warn',"bullet_$n","Bullet $n ist zu kurz.");
            if(preg_match('/<[^>]+>/',$b)) $add('error',"bullet_$n","HTML in Bullet $n ist nicht erlaubt.");
        }
    } else $add('warn','bullets','Keine Bullet Points angegeben.');
    $bytes=strlen($back);
    if($back!==''){
        if($bytes>249) $add('error','backend',"Allgemeine Suchbegriffe: $bytes Bytes – über 249 Bytes werden sie von Amazon ignoriert (Umlaute zählen 2 Bytes).");
        if(str_contains($back,',')) $add('info','backend','Kommas sind unnötig – Leerzeichen reichen und sparen Bytes.');
        $front=mb_strtolower($title.' '.implode(' ',$bul));
        $dups=array_values(array_unique(array_filter(preg_split('/[\s,;]+/u',mb_strtolower($back)) ?: [],fn($w)=>mb_strlen($w)>2 && preg_match('/(^|[^\p{L}])'.preg_quote($w,'/').'([^\p{L}]|$)/u',$front))));
        if($dups) $add('info','backend','Diese Begriffe stehen schon in Titel/Bullets und verschwenden Bytes: '.implode(', ',array_slice($dups,0,15)).'.');
        $bw=preg_split('/\s+/u',mb_strtolower($back)) ?: []; $bdup=array_keys(array_filter(array_count_values($bw),fn($c)=>$c>1)); if($bdup) $add('info','backend','Doppelte Begriffe: '.implode(', ',$bdup).'.');
    }
    $all=mb_strtolower($title."\n".implode("\n",$bul)."\n".$desc);
    foreach(GROWTH_RISKY as $term=>$why){ $re='/(^|[^\p{L}\p{N}])'.preg_quote($term,'/').'([^\p{L}\p{N}]|$)/u'; if(preg_match($re,$all)) $add('warn','claims','„'.$term.'“: '.$why.'.'); }
    if(preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u',$title.implode('',$bul))) $add('error','claims','Emojis in Titel/Bullets sind nicht erlaubt.');
    if(preg_match_all('/\b\p{Lu}{4,}\b/u',$title.' '.implode(' ',$bul),$m)){ $caps=array_values(array_filter(array_unique($m[0]),fn($w)=>$brand==='' || mb_strtolower($w)!==mb_strtolower($brand))); if(count($caps)>1) $add('warn','claims','Großbuchstaben-Wörter ('.implode(', ',array_slice($caps,0,6)).') wirken wie Werbung – nur Marke/Abkürzungen groß.'); }
    /* Abdeckung: ein Keyword gilt in einem Feld als abgedeckt, wenn alle seine Wörter dort vorkommen (Reihenfolge egal, Amazon kombiniert Wörter) */
    $words=fn(string $s)=>array_values(array_filter(preg_split('/[^\p{L}\p{N}]+/u',mb_strtolower($s)) ?: [],fn($w)=>$w!==''));
    $has=function(string $field,string $kw) use($words): bool { $f=array_flip($words($field)); $k=$words($kw); if(!$k) return false; foreach($k as $w) if(!isset($f[$w])) return false; return true; };
    $cov=[];
    foreach($kws as $k) $cov[]=['keyword'=>$k,'title'=>$has($title,$k),'bullets'=>$has(implode(' ',$bul),$k),'backend'=>$has($back,$k)];
    $missing=array_values(array_map(fn($c)=>$c['keyword'],array_filter($cov,fn($c)=>!$c['title'] && !$c['bullets'] && !$c['backend'])));
    if($missing) $add('warn','keywords','Nicht abgedeckt: '.implode(', ',$missing).'.');
    $err=count(array_filter($issues,fn($x)=>$x['level']==='error')); $warn=count(array_filter($issues,fn($x)=>$x['level']==='warn'));
    return ['lengths'=>['title_chars'=>$tl,'bullets'=>array_map('mb_strlen',$bul),'description_chars'=>mb_strlen($desc),'backend_bytes'=>$bytes],'issues'=>$issues,'keyword_coverage'=>$cov,
            'score'=>max(0,100-25*$err-8*$warn),'summary'=>['errors'=>$err,'warnings'=>$warn,'infos'=>count($issues)-$err-$warn],
            'notes'=>['Grenzen: Titel 200 Zeichen, Suchbegriffe unter 250 Bytes; Bullet-Länge ist ein Richtwert – maßgeblich ist der Styleguide der Kategorie.','Aussagen-Prüfung ist eine Vorprüfung, keine Rechtsberatung.']];
}

/* ---------- 5) Kampagne planen/bewerten: Klicks, Conversions, Umsatz, ROAS, CAC, Break-even, LTV ---------- */
function growth_campaign_calc(array $in): array {
    $budget=growth_num($in['budget'] ?? null); $cpc=growth_num($in['cpc'] ?? null); $cpm=growth_num($in['cpm'] ?? null); $ctr=growth_num($in['ctr_pct'] ?? null);
    $cvr=growth_num($in['cvr_pct'] ?? null); $aov=growth_num($in['aov'] ?? null); $margin=growth_num($in['margin_pct'] ?? null);
    $miss=[]; if($budget===null || $budget<=0) $miss[]='budget'; if(($cpc===null || $cpc<=0) && ($cpm===null || $cpm<=0 || $ctr===null || $ctr<=0)) $miss[]='cpc (oder cpm + ctr_pct)';
    if($cvr===null || $cvr<=0) $miss[]='cvr_pct'; if($aov===null || $aov<=0) $miss[]='aov';
    if($miss) return growth_missing($miss,'Bitte Budget, Klickpreis (oder CPM + Klickrate), Conversion-Rate und durchschnittlichen Warenkorb nennen; mit Rohertragsmarge auch Gewinn und Break-even.');
    $impr=null;
    if($cpc!==null && $cpc>0) $clicks=$budget/$cpc; else { $impr=$budget/$cpm*1000; $clicks=$impr*$ctr/100; $cpc=$budget/max(1e-9,$clicks); }
    if($impr===null && $ctr!==null && $ctr>0) $impr=$clicks/($ctr/100);
    $conv=$clicks*$cvr/100; $rev=$conv*$aov; $roas=$rev/$budget; $cac=$conv>0 ? $budget/$conv : null;
    $o=['clicks'=>(int)round($clicks),'impressions'=>$impr!==null ? (int)round($impr) : null,'cpc'=>growth_r($cpc),'conversions'=>growth_r($conv,1),'revenue'=>growth_r($rev),'roas'=>growth_r($roas,2),'cac'=>$cac!==null ? growth_r($cac) : null];
    if($margin!==null && $margin>0 && $margin<=100){
        $gp=$rev*$margin/100; $o['gross_profit']=growth_r($gp); $o['profit_after_ads']=growth_r($gp-$budget);
        $o['break_even_roas']=growth_r(100/$margin,2); $o['break_even_cac']=growth_r($aov*$margin/100); $o['break_even_cpc']=growth_r($aov*$margin/100*$cvr/100);
        $o['verdict']=$gp-$budget>=0 ? 'profitable_first_order' : 'loss_first_order';
        $ppy=growth_num($in['purchases_per_year'] ?? null); $yrs=growth_num($in['years'] ?? null);
        if($ppy!==null && $ppy>0){ $yrs=$yrs!==null && $yrs>0 ? $yrs : 2.0; $n=max(1.0,$ppy*$yrs); $ltv=$aov*$margin/100*$n;
            $o['ltv']=['purchases'=>growth_r($n,1),'gross_profit'=>growth_r($ltv),'ltv_cac'=>$cac ? growth_r($ltv/$cac,2) : null,
              'payback_months'=>$cac ? growth_r($cac/($aov*$margin/100*$ppy/12),1) : null,'rule'=>'LTV:CAC ab 3 gilt als gesund; Amortisation unter 12 Monaten.'];
            if($o['verdict']==='loss_first_order' && $cac && $ltv>=$cac) $o['verdict']='profitable_over_lifetime'; }
    }
    $o['notes']=['Planrechnung mit den angegebenen Raten – echte Werte schwanken; nach 1–2 Wochen mit Ist-Zahlen neu rechnen.','ROAS = Umsatz ÷ Werbekosten · CAC = Werbekosten ÷ Neukunden · Break-even-ROAS = 1 ÷ Marge.'];
    return $o;
}

/* ---------- 6) A/B-Test: Signifikanz (Zwei-Stichproben-z-Test, zweiseitig) ---------- */
function growth_erf(float $x): float {   // Abramowitz-Stegun 7.1.26, Fehler < 1,5e-7
    $s=$x<0 ? -1 : 1; $x=abs($x); $t=1/(1+0.3275911*$x);
    $y=1-((((1.061405429*$t-1.453152027)*$t+1.421413741)*$t-0.284496736)*$t+0.254829592)*$t*exp(-$x*$x); return $s*$y;
}
function growth_ab_test(array $in): array {
    $na=growth_num($in['visitors_a'] ?? null); $ca=growth_num($in['conversions_a'] ?? null); $nb=growth_num($in['visitors_b'] ?? null); $cb=growth_num($in['conversions_b'] ?? null);
    if(!$na || !$nb || $ca===null || $cb===null || $na<=0 || $nb<=0) return growth_missing(['visitors_a','conversions_a','visitors_b','conversions_b'],'Bitte Besucher (bzw. Empfänger/Impressionen) und Conversions (Käufe, Klicks, Öffnungen) für A und B nennen.');
    if($ca<0 || $cb<0 || $ca>$na || $cb>$nb) return ['error'=>'invalid_counts','hint'=>'Conversions dürfen nicht größer als die Besucherzahl sein.'];
    $pa=$ca/$na; $pb=$cb/$nb; $p=($ca+$cb)/($na+$nb); $se=sqrt($p*(1-$p)*(1/$na+1/$nb));
    $z=$se>0 ? ($pb-$pa)/$se : 0.0; $pv=$se>0 ? 2*(1-0.5*(1+growth_erf(abs($z)/M_SQRT2))) : 1.0;
    $lift=$pa>0 ? ($pb-$pa)/$pa*100 : null; $sig=$pv<0.05;
    $minN=min($na,$nb); $minC=min($ca,$cb);
    return ['rate_a_pct'=>growth_r($pa*100,2),'rate_b_pct'=>growth_r($pb*100,2),'uplift_pct'=>$lift!==null ? growth_r($lift,1) : null,'z'=>growth_r($z,2),'p_value'=>growth_r($pv,4),
            'confidence_pct'=>growth_r((1-$pv)*100,1),'significant_95'=>$sig,'winner'=>$sig ? ($pb>$pa ? 'B' : 'A') : null,
            'enough_data'=>$minN>=100 && $minC>=10,
            'notes'=>['Zweiseitiger z-Test für zwei Anteile, Signifikanzniveau 5 %.','Erst auswerten, wenn je Variante mindestens ~100 Besucher und 10 Conversions vorliegen und der Test volle Wochen gelaufen ist; nicht mittendrin „spicken“ und stoppen.']];
}

/* ---------- 7) UTM-Link bauen ---------- */
function growth_utm_slug(string $s): string { $s=mb_strtolower(trim($s)); $s=strtr($s,['ä'=>'ae','ö'=>'oe','ü'=>'ue','ß'=>'ss']); $s=preg_replace('/[^a-z0-9._-]+/','-',$s) ?? ''; return trim(preg_replace('/-+/','-',$s) ?? '','-'); }
function growth_utm_link(array $in): array {
    $url=trim((string)($in['url'] ?? '')); $src=growth_utm_slug((string)($in['source'] ?? '')); $med=growth_utm_slug((string)($in['medium'] ?? '')); $cmp=growth_utm_slug((string)($in['campaign'] ?? ''));
    $miss=[]; if($url==='') $miss[]='url'; if($src==='') $miss[]='source'; if($med==='') $miss[]='medium'; if($cmp==='') $miss[]='campaign';
    if($miss) return growth_missing($miss,'Bitte Ziel-URL, Quelle (z. B. instagram), Medium (z. B. social, cpc, email) und Kampagnenname nennen.');
    if(!preg_match('~^https?://[^\s/$.?#][^\s]*$~i',$url)) return ['error'=>'invalid_url','hint'=>'Die Ziel-URL muss mit https:// beginnen.'];
    $frag=''; if(($h=strpos($url,'#'))!==false){ $frag=substr($url,$h); $url=substr($url,0,$h); }
    $q=['utm_source'=>$src,'utm_medium'=>$med,'utm_campaign'=>$cmp];
    foreach(['content'=>'utm_content','term'=>'utm_term'] as $k=>$p){ $v=growth_utm_slug((string)($in[$k] ?? '')); if($v!=='') $q[$p]=$v; }
    $parts=parse_url($url); $exist=[]; if(!empty($parts['query'])) parse_str($parts['query'],$exist);
    foreach(array_keys($q) as $k) unset($exist[$k]);
    $base=preg_replace('/\?.*$/','',$url); $qs=http_build_query($exist+$q,'','&',PHP_QUERY_RFC3986);
    $warn=[]; if(!in_array($med,['cpc','ppc','paid','paid-social','social','email','newsletter','affiliate','display','referral','organic','sms','push','influencer','qr','print','video'],true)) $warn[]='Ungewöhnliches Medium „'.$med.'“ – einheitliche Namen (z. B. cpc, social, email) halten die Auswertung sauber.';
    return ['url'=>$base.'?'.$qs.$frag,'params'=>$q,'warnings'=>$warn,'notes'=>['Alles klein und mit Bindestrichen – sonst zählt Analytics „Instagram“ und „instagram“ doppelt.','UTM nur für externe Links verwenden, nie für Links innerhalb der eigenen Website.']];
}

/* ---------- Modul-Schnittstelle ---------- */
function growth_tools(): array {
    $k=growth_kind(); $want=$k ? GROWTH_TOOLSETS[$k] : array_values(array_unique(array_merge(...array_values(GROWTH_TOOLSETS))));
    $num=fn(string $d)=>['type'=>'number','description'=>$d];
    $all=[
      'fba_profit'=>['name'=>'fba_profit','description'=>'Amazon FBA: Stückgewinn, Marge, ROI, Break-even-ACoS und Break-even-Preis exakt berechnen. Immer verwenden statt selbst zu rechnen. FBA-Gebühr und Verkaufsgebühr aus Wissensbasis bzw. Angabe des Händlers – nie schätzen.',
        'input_schema'=>['type'=>'object','properties'=>['price'=>$num('Verkaufspreis brutto je Stück'),'cogs'=>$num('Einkaufspreis je Stück (EK inkl. Fracht bis Lager/Prep)'),'fba_fee'=>$num('FBA-Versandgebühr je Stück laut Seller Central'),
          'referral_pct'=>$num('Verkaufsgebühr in % (Standard 15)'),'referral_min'=>$num('Mindest-Verkaufsgebühr je Stück (Standard 0,30)'),'vat_pct'=>$num('Umsatzsteuer % des Marktplatzes (Standard 19)'),
          'inbound_per_unit'=>$num('Versand zum Amazon-Lager je Stück'),'storage_per_unit_month'=>$num('Lagergebühr je Stück und Monat'),'storage_months'=>$num('durchschnittliche Lagerdauer in Monaten'),
          'returns_pct'=>$num('Retourenquote %'),'other_per_unit'=>$num('sonstige Kosten je Stück (Verpackung, Etiketten, Prep)'),'ad_per_unit'=>$num('Werbekosten je verkauftem Stück'),'tacos_pct'=>$num('alternativ: Werbekosten in % vom Umsatz (TACoS)'),
          'units_month'=>$num('Stück pro Monat (für Monatswerte)')],'required'=>['price','cogs','fba_fee']]],
      'ppc_metrics'=>['name'=>'ppc_metrics','description'=>'Werbe-Kennzahlen exakt berechnen (Amazon PPC, Google Ads, Meta): ACoS, ROAS, TACoS, CPC, CTR, CVR, CPA, Status gegen Ziel/Break-even und Gebotsvorschlag.',
        'input_schema'=>['type'=>'object','properties'=>['spend'=>$num('Werbekosten im Zeitraum'),'sales'=>$num('Werbeumsatz im Zeitraum'),'clicks'=>$num('Klicks'),'impressions'=>$num('Impressionen'),'orders'=>$num('Bestellungen'),
          'total_sales'=>$num('Gesamtumsatz inkl. organisch (für TACoS)'),'target_acos_pct'=>$num('Ziel-ACoS %'),'break_even_acos_pct'=>$num('Break-even-ACoS % (aus fba_profit)'),'current_bid'=>$num('aktuelles Gebot')],'required'=>['spend','sales']]],
      'restock_plan'=>['name'=>'restock_plan','description'=>'FBA-Nachschub planen: Reichweite in Tagen, Out-of-Stock-Datum, Bestellpunkt, späteste Bestellung und Bestellmenge (MOQ/Karton).',
        'input_schema'=>['type'=>'object','properties'=>['units_per_day'=>$num('Verkäufe pro Tag'),'units_30d'=>$num('alternativ: Verkäufe der letzten 30 Tage'),'stock_available'=>$num('verfügbarer FBA-Bestand'),'inbound'=>$num('bereits unterwegs/in Einlagerung'),
          'lead_time_days'=>$num('Tage von Bestellung bis Ankunft am Amazon-Lager (Produktion + Transport)'),'checkin_days'=>$num('Einlagerung bei Amazon in Tagen (Standard 10)'),'safety_days'=>$num('Sicherheitstage (Standard 14)'),
          'cover_days'=>$num('gewünschte Reichweite nach Ankunft (Standard 60)'),'moq'=>$num('Mindestbestellmenge'),'units_per_carton'=>$num('Stück je Karton'),'today'=>['type'=>'string','description'=>'Datum YYYY-MM-DD (optional)']],
          'required'=>['stock_available','lead_time_days']]],
      'listing_check'=>['name'=>'listing_check','description'=>'Amazon-Listing prüfen: Titel-Länge und -Regeln, Bullet Points, Byte-Grenze der Suchbegriffe, riskante Aussagen (Werbe-, Heil-, Umweltaussagen), Keyword-Abdeckung. Vor jedem Listing-Vorschlag und für bestehende Listings verwenden.',
        'input_schema'=>['type'=>'object','properties'=>['title'=>['type'=>'string'],'bullets'=>['type'=>'array','items'=>['type'=>'string']],'description'=>['type'=>'string'],'backend_terms'=>['type'=>'string','description'=>'allgemeine Suchbegriffe (Backend)'],
          'keywords'=>['type'=>'array','items'=>['type'=>'string'],'description'=>'Ziel-Keywords'],'brand'=>['type'=>'string']],'required'=>[]]],
      'campaign_calc'=>['name'=>'campaign_calc','description'=>'Kampagne planen oder bewerten: aus Budget, CPC (oder CPM + CTR), Conversion-Rate, Warenkorb und Marge → Klicks, Conversions, Umsatz, ROAS, CAC, Gewinn, Break-even-ROAS/-CAC/-CPC und optional Kundenwert (LTV:CAC).',
        'input_schema'=>['type'=>'object','properties'=>['budget'=>$num('Werbebudget'),'cpc'=>$num('Kosten pro Klick'),'cpm'=>$num('Kosten pro 1.000 Impressionen'),'ctr_pct'=>$num('Klickrate %'),'cvr_pct'=>$num('Conversion-Rate %'),
          'aov'=>$num('durchschnittlicher Warenkorb'),'margin_pct'=>$num('Rohertragsmarge %'),'purchases_per_year'=>$num('Käufe je Kunde pro Jahr (für LTV)'),'years'=>$num('Kundenlebensdauer in Jahren (Standard 2)')],'required'=>['budget','cvr_pct','aov']]],
      'ab_test'=>['name'=>'ab_test','description'=>'A/B-Test auswerten (Betreffzeilen, Anzeigen, Landingpages, Amazon „Manage Your Experiments“): Raten, Uplift, p-Wert, ob signifikant (95 %) und ob genug Daten vorliegen.',
        'input_schema'=>['type'=>'object','properties'=>['visitors_a'=>$num('Besucher/Empfänger A'),'conversions_a'=>$num('Conversions A'),'visitors_b'=>$num('Besucher/Empfänger B'),'conversions_b'=>$num('Conversions B')],'required'=>['visitors_a','conversions_a','visitors_b','conversions_b']]],
      'utm_link'=>['name'=>'utm_link','description'=>'Sauberen UTM-Tracking-Link bauen (einheitliche Kleinschreibung, vorhandene Parameter bleiben erhalten).',
        'input_schema'=>['type'=>'object','properties'=>['url'=>['type'=>'string'],'source'=>['type'=>'string'],'medium'=>['type'=>'string'],'campaign'=>['type'=>'string'],'content'=>['type'=>'string'],'term'=>['type'=>'string']],'required'=>['url','source','medium','campaign']]],
    ];
    return array_values(array_map(fn($n)=>$all[$n],array_filter($want,fn($n)=>isset($all[$n]))));
}
function growth_exec(int $acc, string $name, array $in, string $channel='studio', string $from=''): array {
    try{
        return match($name){
            'fba_profit'=>growth_fba_profit($in), 'ppc_metrics'=>growth_ppc_metrics($in), 'restock_plan'=>growth_restock_plan($in),
            'listing_check'=>growth_listing_check($in), 'campaign_calc'=>growth_campaign_calc($in), 'ab_test'=>growth_ab_test($in), 'utm_link'=>growth_utm_link($in),
            default=>['error'=>'unknown_tool'] };
    }catch(Throwable $e){ error_log('CH growth '.$name.': '.$e->getMessage()); return ['error'=>'calc_failed','hint'=>'Berechnung nicht möglich – bitte Eingaben prüfen.']; }
}
function growth_system_hint(int $acc, string $channel='studio'): string {
    $k=growth_kind();
    $base="\n\nRECHEN-WERKZEUGE (aktiv): Zahlen nie selbst ausrechnen oder schätzen – immer das passende Werkzeug aufrufen und dessen Ergebnis verwenden. Fehlen Eingaben, meldet das Werkzeug „missing“: dann gezielt genau danach fragen. Ergebnisse als kurze Tabelle oder Liste mit Einheit und Währung zeigen, danach 2–3 konkrete Empfehlungen.";
    if($k==='amazon') $base.=" Werkzeuge: fba_profit (Marge, Break-even-ACoS), ppc_metrics (Kampagnen), restock_plan (Nachschub), listing_check (Listing-Regeln), ab_test (Manage Your Experiments). Gebühren nur aus Wissensbasis oder Angabe des Händlers.";
    elseif($k==='marketing') $base.=" Werkzeuge: campaign_calc (Budget, ROAS, CAC, Break-even, LTV), ppc_metrics (Ist-Kennzahlen einer Kampagne), ab_test (Betreff, Anzeige, Landingpage), utm_link (Tracking-Links).";
    return $base;
}
