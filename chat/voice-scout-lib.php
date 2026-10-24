<?php
/**
 * ChatHelp — Stimmen-Scout: beste muttersprachliche Stimme je Sprache und Anbieter finden (Tabelle tts_native)
 * ============================================================================================================
 * elevenlabs  ElevenLabs Voice Library (GET /v1/shared-voices): Profi-Sprecher, die ihre eigene Stimme als „Professional Voice Clone“
 *             anbieten – muttersprachlich, mit Akzent-Angabe. Auswahl je Sprache/Geschlecht nach Gesprächs-Eignung, Standard-Akzent
 *             (z. B. Istanbul, Baku, Kastilisch, Paris, Hochdeutsch, Tokio), Nutzung und Beliebtheit; Stimmen mit Aufpreis (custom rate)
 *             werden übersprungen. Übernahme in die eigene Bibliothek: POST /v1/voices/add/{public_owner_id}/{voice_id}.
 *             Kommerzielle Nutzung: mit bezahltem ElevenLabs-Tarif erlaubt; Sprecher werden von ElevenLabs je Nutzung vergütet.
 * azure       GET https://{region}.tts.speech.microsoft.com/cognitiveservices/voices/list → je Sprache HD-Stimmen (DragonHD) zuerst.
 * google      GET /v1/voices?languageCode=xx-XX → vorhandene Chirp-3-HD-Stimmen der Locale (fehlt eine Sprache, rückt Google dort nach hinten).
 * Aufruf: chat/ops/voice-scout.php (über den Ops-Workflow). Schlüssel kommen aus db.php und werden nie ausgegeben.
 */
require_once __DIR__.'/speech-lib.php';

/** Bevorzugter Standard-Akzent je Sprache (Wörter in accent/description) und regionale Varianten, die nicht der Standard sind. */
const VS_ACCENT = [
  'de'=>['std'=>'standard|german|hochdeutsch|neutral','alt'=>'swiss|austrian|bavarian|saxon|schw[äa]bisch'],
  'fr'=>['std'=>'standard|parisian|paris|france|neutral','alt'=>'canad|qu[ée]b|belg|swiss|african|cajun'],
  'es'=>['std'=>'standard|peninsular|castilian|castellano|spain|spanish|madrid|neutral','alt'=>'mexic|latin|argentin|colombia|chile|peru|venezuel|caribbean|cuban|rioplat'],
  'tr'=>['std'=>'standard|istanbul|turkish|neutral','alt'=>'cypr|kurd|azer'],
  'az'=>['std'=>'standard|baku|azerbaijan|neutral','alt'=>'iran|tabriz|south'],
  'it'=>['std'=>'standard|italian|neutral|rome|milan','alt'=>'swiss|sicil|neapol|sardin'],
  'ru'=>['std'=>'standard|russian|moscow|neutral','alt'=>'ukrain|belarus|kazakh'],
  'nl'=>['std'=>'standard|dutch|netherlands|holland|neutral','alt'=>'flemish|belgi|vlaams|surinam'],
  'sv'=>['std'=>'standard|swedish|stockholm|neutral','alt'=>'finland|finnish'],
  'no'=>['std'=>'standard|norwegian|oslo|eastern|bokm[åa]l|neutral','alt'=>'nynorsk|bergen|northern'],
  'fi'=>['std'=>'standard|finnish|helsinki|neutral','alt'=>'swedish'],
  'ja'=>['std'=>'standard|japanese|tokyo|neutral','alt'=>'kansai|osaka|kyoto'],
  'en'=>['std'=>'standard|american|british|neutral','alt'=>''],
];
const VS_LANGS = ['de','fr','tr','az','es','it','ru','nl','sv','no','fi','ja','en'];

function vs_bootstrap(): void {
    th_db()->exec("CREATE TABLE IF NOT EXISTS tts_native (provider VARCHAR(16) NOT NULL, lang CHAR(2) NOT NULL, gender CHAR(1) NOT NULL, pos TINYINT NOT NULL,
        voice VARCHAR(100) NOT NULL, label VARCHAR(120) NOT NULL DEFAULT '', meta TEXT NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(provider,lang,gender,pos)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
/** HTTP-Aufruf (JSON). → ['code'=>int,'json'=>mixed,'raw'=>string]. Schlüssel werden aus Fehlertexten entfernt. */
function vs_http(string $method, string $url, array $headers, ?string $body=null, array $secrets=[]): array {
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>30,CURLOPT_CONNECTTIMEOUT=>8,
        CURLOPT_FOLLOWLOCATION=>false,CURLOPT_NOPROXY=>'127.0.0.1,localhost,::1']);
    if($body!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,$body);
    $raw=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    $raw=is_string($raw) ? $raw : $err;
    foreach($secrets as $s) if($s!=='') $raw=str_replace([$s,rawurlencode($s)],'***',$raw);
    return ['code'=>$code,'json'=>json_decode($raw,true),'raw'=>$raw];
}
function vs_err(array $r): string { $j=$r['json']; $m=is_array($j) ? ($j['detail']['message'] ?? $j['detail']['status'] ?? $j['error']['message'] ?? $j['message'] ?? '') : ''; if(!is_string($m) || $m==='') $m=mb_substr(preg_replace('/\s+/',' ',(string)$r['raw']),0,160); return 'HTTP '.($r['code'] ?: 'curl').' '.$m; }
/** Ersetzt die Stimmen eines Anbieters für Sprache + Geschlecht (nur aufrufen, wenn mindestens eine gefunden wurde). */
function vs_store(string $provider, string $lc, string $g, array $rows): void {
    $p=th_db(); $p->beginTransaction();
    try{
        $p->prepare("DELETE FROM tts_native WHERE provider=? AND lang=? AND gender=?")->execute([$provider,$lc,$g]);
        $q=$p->prepare("INSERT INTO tts_native(provider,lang,gender,pos,voice,label,meta) VALUES(?,?,?,?,?,?,?)");
        foreach(array_values($rows) as $i=>$r) $q->execute([$provider,$lc,$g,$i,$r['voice'],mb_substr((string)($r['label'] ?? ''),0,120),json_encode($r['meta'] ?? [],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
        $p->commit();
    }catch(Throwable $e){ $p->rollBack(); throw $e; }
}
/** Merkzeile „geprüft, keine passende Stimme“ (gender '-') setzen oder entfernen – ch_tts_rank stellt den Anbieter für die Sprache dann nach hinten. */
function vs_mark_none(string $provider, string $lc, bool $none): void {
    $p=th_db(); $p->prepare("DELETE FROM tts_native WHERE provider=? AND lang=? AND gender='-'")->execute([$provider,$lc]);
    if($none) $p->prepare("INSERT INTO tts_native(provider,lang,gender,pos,voice,label,meta) VALUES(?,?,'-',0,'','',NULL)")->execute([$provider,$lc]);
}
function vs_rows(string $provider, string $lc, string $g): array {
    $q=th_db()->prepare("SELECT voice,label,meta FROM tts_native WHERE provider=? AND lang=? AND gender=? ORDER BY pos"); $q->execute([$provider,$lc,$g]);
    return array_map(fn($r)=>['voice'=>$r['voice'],'label'=>$r['label'],'meta'=>json_decode((string)$r['meta'],true) ?: []],$q->fetchAll(PDO::FETCH_ASSOC));
}

/* ---------------------------------------------------------------- ElevenLabs */
/** Bewertung einer Library-Stimme für Sprache $lc (höher = besser; null = ungeeignet). */
function vs_el_score(array $v, string $lc): ?float {
    $langs=[strtolower((string)($v['language'] ?? ''))]; foreach((array)($v['verified_languages'] ?? []) as $vl) if(is_array($vl)) $langs[]=strtolower((string)($vl['language'] ?? ''));
    $want=$lc==='no' ? ['no','nb'] : [$lc];
    if(!array_intersect($want,$langs)) return null;
    if(isset($v['rate']) && is_numeric($v['rate']) && (float)$v['rate']>1.0) return null;   // Aufpreis-Stimme
    $txt=strtolower(implode(' ',[(string)($v['accent'] ?? ''),(string)($v['locale'] ?? ''),(string)($v['description'] ?? ''),(string)($v['descriptive'] ?? '')]));
    foreach((array)($v['verified_languages'] ?? []) as $vl) if(is_array($vl) && in_array(strtolower((string)($vl['language'] ?? '')),$want,true)) $txt.=' '.strtolower((string)($vl['accent'] ?? '').' '.(string)($vl['locale'] ?? ''));
    $A=VS_ACCENT[$lc] ?? ['std'=>'standard','alt'=>''];
    $s=log10((float)($v['usage_character_count_1y'] ?? 0)+10)+0.6*log10((float)($v['cloned_by_count'] ?? 0)+10);
    $uc=strtolower((string)($v['use_case'] ?? ''));
    if($uc==='conversational') $s+=1.2; elseif(in_array($uc,['informative_educational','narrative_story','advertisement'],true)) $s+=0.3; elseif(in_array($uc,['characters_animation','characters','entertainment_tv'],true)) $s-=0.8;
    if(preg_match('/'.$A['std'].'/u',$txt)) $s+=0.8;
    if($A['alt']!=='' && preg_match('/'.$A['alt'].'/u',$txt)) $s-=1.6;
    $tag=strtolower(function_exists('ch_lang_tag') ? ch_lang_tag($lc) : ''); if($tag!=='' && str_contains($txt,$tag)) $s+=0.6;
    if(in_array($lc,array_map(fn($x)=>is_array($x) ? strtolower((string)($x['language'] ?? '')) : '',(array)($v['verified_languages'] ?? [])),true)) $s+=0.4;
    if(!empty($v['notice_period']) && (int)$v['notice_period']>=30) $s+=0.2;   // bleibt bei Rückzug noch mindestens 30 Tage nutzbar
    return round($s,3);
}
/** Muttersprachliche Profi-Stimmen je Sprache/Geschlecht suchen und (wenn $add) in die eigene Bibliothek übernehmen. */
function vs_scout_elevenlabs(array $langs, int $per, bool $add, array &$log): void {
    $c=ch_tts_creds('elevenlabs'); if(!$c){ $log[]='elevenlabs: kein Schlüssel (ELEVENLABS_KEY) – übersprungen'; return; }
    $k=$c['key']; $base=ch_tts_base('https://api.elevenlabs.io'); $H=['xi-api-key: '.$k,'Accept: application/json']; $full=false;
    foreach($langs as $lc){
        if($lc==='en'){ $log[]='elevenlabs en: Standardstimmen sind bereits englische Muttersprachler – nichts zu tun'; continue; }
        foreach(['f'=>'female','m'=>'male'] as $g=>$gn){
            $cand=[];
            foreach([['use_cases'=>'conversational'],[]] as $extra){   // erst Gesprächsstimmen, dann alle
                $q=http_build_query(['page_size'=>60,'language'=>$lc==='no' ? 'no' : $lc,'gender'=>$gn,'sort'=>'usage_character_count_1y']+$extra);
                $r=vs_http('GET',$base.'/v1/shared-voices?'.$q,$H,null,[$k]);
                if($r['code']!==200){ $log[]="elevenlabs $lc/$g: Suche fehlgeschlagen (".vs_err($r).')'; break; }
                foreach((array)($r['json']['voices'] ?? []) as $v){ if(!is_array($v) || empty($v['voice_id']) || empty($v['public_owner_id'])) continue;
                    $sc=vs_el_score($v,$lc); if($sc!==null) $cand[(string)$v['voice_id']]=['s'=>$sc,'v'=>$v]; }
                if(count($cand)>=$per*3) break;
            }
            if(!$cand){ $log[]="elevenlabs $lc/$g: keine passende Library-Stimme gefunden – bisherige Auswahl bleibt"; continue; }
            uasort($cand,fn($a,$b)=>$b['s']<=>$a['s']);
            $old=[]; foreach(vs_rows('elevenlabs',$lc,$g) as $o) if(!empty($o['meta']['src'])) $old[(string)$o['meta']['src']]=$o['voice'];
            $rows=[];
            foreach(array_slice($cand,0,$per,true) as $sid=>$x){ $v=$x['v']; $sid=(string)$sid; $vid=$old[$sid] ?? null;
                if($vid===null && $add && !$full){
                    $name=mb_substr('ChatHelp '.$lc.' '.strtoupper($g).(count($rows)+1).' · '.(string)($v['name'] ?? ''),0,90);
                    $a=vs_http('POST',$base.'/v1/voices/add/'.rawurlencode((string)$v['public_owner_id']).'/'.rawurlencode($sid),array_merge($H,['Content-Type: application/json']),json_encode(['new_name'=>$name],JSON_UNESCAPED_UNICODE),[$k]);
                    if($a['code']===200 && preg_match('/^[A-Za-z0-9]{10,40}$/',(string)($a['json']['voice_id'] ?? ''))) $vid=(string)$a['json']['voice_id'];
                    else { $e=vs_err($a); $log[]="elevenlabs $lc/$g: Übernahme von „".($v['name'] ?? $sid)."“ fehlgeschlagen ($e)"; if(preg_match('/limit|maximum|quota/i',$e)){ $full=true; $log[]='elevenlabs: Stimmen-Limit des Tarifs erreicht – weitere Übernahmen gestoppt (größerer Tarif oder per=1)'; } continue; }
                }
                if($vid===null) $vid=$add ? null : $sid;   // Probelauf: nur anzeigen
                if($vid===null) continue;
                $rows[]=['voice'=>$vid,'label'=>(string)($v['name'] ?? ''),'meta'=>['src'=>$sid,'owner'=>(string)$v['public_owner_id'],'accent'=>(string)($v['accent'] ?? ''),'locale'=>(string)($v['locale'] ?? ''),
                    'use_case'=>(string)($v['use_case'] ?? ''),'usage_1y'=>(int)($v['usage_character_count_1y'] ?? 0),'score'=>$x['s'],'preview'=>(string)($v['preview_url'] ?? '')]];
            }
            if(!$rows) continue;
            if($add) vs_store('elevenlabs',$lc,$g,$rows);
            foreach($rows as $i=>$r) $log[]=sprintf('elevenlabs %s/%s #%d: %s (Akzent: %s, %s, Score %.2f)%s',$lc,$g,$i+1,$r['label'],$r['meta']['accent'] ?: '–',$r['meta']['use_case'] ?: '–',$r['meta']['score'],$add ? '' : ' [Probelauf]');
        }
    }
}

/* ---------------------------------------------------------------- Azure */
function vs_azure_locales(string $lc): array { $t=function_exists('ch_lang_tag') ? ch_lang_tag($lc) : ''; return array_values(array_unique(array_filter($lc==='en' ? [$t,'en-US','en-GB'] : ($lc==='no' ? ['nb-NO'] : [$t])))); }
function vs_scout_azure(array $langs, array &$log): void {
    $c=ch_tts_creds('azure'); if(!$c){ $log[]='azure: kein Schlüssel (AZURE_SPEECH_KEY + AZURE_SPEECH_REGION) – übersprungen'; return; }
    $r=vs_http('GET',ch_tts_base('https://'.$c['region'].'.tts.speech.microsoft.com').'/cognitiveservices/voices/list',['Ocp-Apim-Subscription-Key: '.$c['key']],null,[$c['key']]);
    if($r['code']!==200 || !is_array($r['json'])){ $log[]='azure: Stimmenliste nicht abrufbar ('.vs_err($r).')'; return; }
    $known=[]; foreach(CH_TTS_AZURE_NATIVE as $l=>$fm) foreach($fm as $n) $known[$n]=true;
    foreach(CH_TTS_VOICES as $p) $known[$p['azure']]=true;
    foreach($langs as $lc){
        $locs=vs_azure_locales($lc);
        foreach(['f'=>'Female','m'=>'Male'] as $g=>$gn){
            $c2=[];
            foreach($r['json'] as $v){ if(!is_array($v)) continue; $n=(string)($v['ShortName'] ?? ''); $loc=(string)($v['Locale'] ?? '');
                if(!in_array($loc,$locs,true) || ($v['Gender'] ?? '')!==$gn || !preg_match(CH_TTS_AZURE_RE,$n) || strcasecmp((string)($v['Status'] ?? 'GA'),'Deprecated')===0) continue;
                $hd=str_contains($n,':DragonHD'); $s=($hd ? 10 : 0)+($hd && str_contains($n,'Latest') ? 2 : 0)+(strcasecmp((string)($v['Status'] ?? 'GA'),'GA')===0 ? 3 : 0)
                    +(isset($known[$n]) ? 2 : 0)+(str_contains($n,'Multilingual') ? 1 : 0)+($loc===$locs[0] ? 1 : 0);
                $c2[$n]=$s; }
            if(!$c2){ $log[]="azure $lc/$g: keine Stimme in ".implode('/',$locs); continue; }
            arsort($c2); $rows=[]; foreach(array_slice(array_keys($c2),0,6) as $n) $rows[]=['voice'=>$n,'label'=>preg_replace('/^[a-z]{2,3}-[A-Z]{2}-/','',$n),'meta'=>['hd'=>str_contains($n,':DragonHD')]];
            vs_store('azure',$lc,$g,$rows);
            $log[]="azure $lc/$g: ".implode(', ',array_column($rows,'voice'));
        }
    }
}

/* ---------------------------------------------------------------- Google */
function vs_scout_google(array $langs, array &$log): void {
    $c=ch_tts_creds('google'); if(!$c){ $log[]='google: kein Schlüssel (GOOGLE_TTS_KEY) – übersprungen'; return; }
    foreach($langs as $lc){
        if($lc==='az'){ $log[]='google az: Google hat keine aserbaidschanische Stimme – dafür Azure/ElevenLabs'; continue; }
        $loc=$lc==='no' ? 'nb-NO' : (function_exists('ch_lang_tag') ? ch_lang_tag($lc) : 'de-DE');
        $r=vs_http('GET',ch_tts_base('https://texttospeech.googleapis.com').'/v1/voices?languageCode='.rawurlencode($loc).'&key='.rawurlencode($c['key']),['Accept: application/json'],null,[$c['key']]);
        if($r['code']!==200){ $log[]="google $lc: Stimmenliste nicht abrufbar (".vs_err($r).')'; continue; }
        $by=['f'=>[],'m'=>[]];
        foreach((array)($r['json']['voices'] ?? []) as $v){ $n=(string)($v['name'] ?? ''); if(!is_array($v) || !preg_match('/^'.preg_quote($loc,'/').'-Chirp3-HD-[A-Za-z]+$/',$n)) continue;
            $gg=($v['ssmlGender'] ?? '')==='MALE' ? 'm' : (($v['ssmlGender'] ?? '')==='FEMALE' ? 'f' : null); if($gg) $by[$gg][]=$n; }
        vs_mark_none('google',$lc,!$by['f'] && !$by['m']);
        foreach($by as $g=>$names){ if(!$names){ $log[]="google $lc/$g: keine Chirp-3-HD-Stimme in $loc"; continue; } sort($names);
            vs_store('google',$lc,$g,array_map(fn($n)=>['voice'=>$n,'label'=>preg_replace('/^.*-Chirp3-HD-/','',$n),'meta'=>[]],array_slice($names,0,12)));
            $log[]="google $lc/$g: ".count($names).' Chirp-3-HD-Stimmen'; }
    }
}

/* ---------------------------------------------------------------- Übersicht */
/** Je Sprache: welcher Anbieter spricht, mit welcher Stimme (Frau/Mann), muttersprachlich? Für ops/voice-scout.php und den Selbsttest. */
function vs_status(): array {
    $o=[];
    foreach(VS_LANGS as $lc){
        $row=['lang'=>$lc,'provider'=>null,'f'=>'','m'=>'','native'=>false,'note'=>''];
        foreach(['f','m'] as $g){
            $v=ch_tts_voice_for(['gender'=>$g],null,$lc); $p=ch_tts_provider_for($v['lang'],$v); $row['provider']=$row['provider'] ?? $p;
            if(!$p){ $row[$g]='Browser (Piper/Gerät)'; continue; }
            $r=ch_tts_resolve($p,$v);
            $row[$g]=$p.': '.($r['voice'] ?? $r['model'] ?? '?').($p==='elevenlabs' ? ' ('.$r['model'].')' : '');
            if($p==='elevenlabs' && ($lab=vs_label('elevenlabs',$lc,$g,(string)$r['voice']))!=='') $row[$g].=' – '.$lab;
        }
        $p=$row['provider'];
        $row['native']=$p==='azure' || ($p==='google' && $lc!=='az') || ($p==='elevenlabs' && ($lc==='en' || ch_tts_native($lc,'elevenlabs')!==[]));
        if(!$p) $row['note']='kein Sprachdienst-Schlüssel → kostenlose Browserstimme';
        elseif(!$row['native']) $row['note']=$lc==='az' ? 'für echtes Aserbaidschanisch: Azure-Schlüssel oder ElevenLabs + Scout' : 'mit Akzent – Scout ausführen oder Google/Azure-Schlüssel hinterlegen';
        $o[]=$row;
    }
    return $o;
}
function vs_label(string $provider, string $lc, string $g, string $voice): string {
    try{ $q=th_db()->prepare("SELECT label FROM tts_native WHERE provider=? AND lang=? AND gender=? AND voice=? LIMIT 1"); $q->execute([$provider,$lc,$g,$voice]); return (string)($q->fetchColumn() ?: ''); }
    catch(Throwable $e){ return ''; }
}
