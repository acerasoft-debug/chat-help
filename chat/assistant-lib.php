<?php
/**
 * ChatHelp — Persönlicher Assistent im Studio („Lea“ oder ein eigener Name)
 * ==========================================================================
 * Eine realistische, sprechende Assistentin bzw. ein Assistent – Aussehen, Stimme und Persönlichkeit legt jeder Inhaber selbst fest.
 * Der Inhaber erzählt, was erledigt werden soll; der Assistent fragt nach, was er noch lernen muss (Betrieb, Angebot, Zeiten,
 * Kundenfragen, Ton, Kanäle), merkt sich die Antworten und richtet die Agenten mit echten Werkzeugen ein – immer erst nach Rückfrage.
 *
 * Tabellen:  assistant_profiles (Persona je Konto) · assistant_messages (Verlauf) · assistant_memory (gemerkte Fakten)
 * REST (Studio, Bearer-Key):  GET|POST /v1/assistant/profile · GET /v1/assistant/state · POST /v1/assistant/chat
 *                             POST /v1/assistant/speak (audio/mpeg) · POST /v1/assistant/reset · POST /v1/assistant/forget
 * Stimme: nutzt chat/speech-lib.php (ch_tts_synth …), wenn vorhanden – sonst spricht das Studio-Panel mit der besten Browserstimme.
 * Wichtig: Die Werkzeuge gehören ausschließlich dem Inhaber im Studio – nie in Kundenkanäle (Widget, Telefon, WhatsApp) einbinden.
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/plans.php';
require_once __DIR__.'/appt-lib.php';
require_once __DIR__.'/order-lib.php';
require_once __DIR__.'/agent-tools.php';   // ch_plan_active_any, Fachmodule (Taxi, Hotel, Rückruf, Training) – bereits geladen, wenn agent-tools uns einbindet
if(is_file(__DIR__.'/speech-lib.php')) require_once __DIR__.'/speech-lib.php';

const ASSISTANT_COST_TURN = 6;          // Tokens je Antwort
const ASSISTANT_COST_TOOL = 2;          // + je Werkzeugaufruf
const ASSISTANT_HISTORY_AI = 24;        // so viele Nachrichten bekommt die KI als Verlauf
const ASSISTANT_HISTORY_KEEP = 400;     // ältere Nachrichten werden aufgeräumt
const ASSISTANT_MEMORY_MAX = 150;
const ASSISTANT_MSG_MAX = 2000;
const ASSISTANT_PHOTO_MAX = 358400;     // 350 KB (Binärgröße nach dem Verkleinern im Browser)
const ASSISTANT_SPEAK_MAX = 400;
const ASSISTANT_TONES = ['warm','ruhig','energisch','sachlich'];
const ASSISTANT_MOODS = ['herzlich','professionell','locker'];
const ASSISTANT_LANGS = ['de','en','tr','fr','es','it','nl','pl','ru'];
const ASSISTANT_AGENT_TONES = ['freundlich-professionell','seriös-zugewandt','sachlich-respektvoll','locker-modern','einfühlsam-präzise','kompetent-ehrlich'];
const ASSISTANT_TABS = ['home'=>'Übersicht','agents'=>'Agenten','training'=>'Training','phone'=>'Telefon','rueckruf'=>'Rückrufe','termine'=>'Termine','bestellungen'=>'Bestellungen','taxi'=>'Taxi','hotel'=>'Hotel','benachrichtigungen'=>'Benachrichtigungen','tokens'=>'Tokens & API','assistant'=>'Assistent'];
/** Eingebaute Porträts, solange site/media/avatars/avatars.json fehlt: dezente SVG-Platzhalter (Initiale + weicher Verlauf). */
const ASSISTANT_FALLBACK_PORTRAITS = [
    ['id'=>'f1','gender'=>'f','label'=>'Lea','age'=>32,'c'=>['#f6e3c4','#d9a95b','#8a5a1c']],
    ['id'=>'f2','gender'=>'f','label'=>'Sophie','age'=>41,'c'=>['#f3dfe6','#c98aa3','#7c3b57']],
    ['id'=>'f3','gender'=>'f','label'=>'Amira','age'=>28,'c'=>['#e2ecf6','#8fb0d4','#34557d']],
    ['id'=>'m1','gender'=>'m','label'=>'Jonas','age'=>34,'c'=>['#e4ecef','#88a6b3','#2f4f5c']],
    ['id'=>'m2','gender'=>'m','label'=>'David','age'=>45,'c'=>['#ece6dc','#b39a78','#5b4428']],
    ['id'=>'m3','gender'=>'m','label'=>'Emre','age'=>29,'c'=>['#e6e7f4','#9a9fd1','#3c3f7a']],
];
/**
 * Stimm-Voreinstellungen (gleiche IDs wie ch_tts_voices() in speech-lib). Die speech-lib-Liste hat Vorrang, diese Tabelle ist der Ersatz,
 * solange speech-lib (noch) keine Voreinstellungen liefert. browser = Namensbestandteile guter Browserstimmen (Edge/Chrome/Safari).
 */
const ASSISTANT_VOICE_PRESETS = [
    'f_warm'=>['gender'=>'f','label'=>'Warm','desc'=>'Herzlich und ruhig – wie eine vertraute Kollegin','rate'=>1.0,'pitch'=>1.0,'openai'=>'coral','google'=>'de-DE-Chirp3-HD-Aoede','azure'=>'de-DE-SeraphinaMultilingualNeural','browser'=>['Seraphina','Katja','Amala','Anna','Petra','Helena']],
    'f_klar'=>['gender'=>'f','label'=>'Klar','desc'=>'Deutlich, sachlich und souverän','rate'=>1.0,'pitch'=>1.0,'openai'=>'sage','google'=>'de-DE-Chirp3-HD-Kore','azure'=>'de-DE-SeraphinaMultilingualNeural','browser'=>['Katja','Seraphina','Petra','Anna','Helena']],
    'f_frisch'=>['gender'=>'f','label'=>'Frisch','desc'=>'Lebendig, positiv und schwungvoll','rate'=>1.05,'pitch'=>1.05,'openai'=>'shimmer','google'=>'de-DE-Chirp3-HD-Leda','azure'=>'de-DE-SeraphinaMultilingualNeural','browser'=>['Amala','Seraphina','Katja','Anna','Petra']],
    'm_ruhig'=>['gender'=>'m','label'=>'Ruhig','desc'=>'Tief, gelassen und vertrauenswürdig','rate'=>0.97,'pitch'=>0.95,'openai'=>'onyx','google'=>'de-DE-Chirp3-HD-Orus','azure'=>'de-DE-FlorianMultilingualNeural','browser'=>['Florian','Conrad','Killian','Markus','Yannick']],
    'm_freundlich'=>['gender'=>'m','label'=>'Freundlich','desc'=>'Warm, nahbar und gut gelaunt','rate'=>1.0,'pitch'=>1.0,'openai'=>'ash','google'=>'de-DE-Chirp3-HD-Charon','azure'=>'de-DE-FlorianMultilingualNeural','browser'=>['Conrad','Florian','Killian','Markus','Yannick']],
    'm_souveraen'=>['gender'=>'m','label'=>'Souverän','desc'=>'Klar, professionell und bestimmt','rate'=>1.0,'pitch'=>0.97,'openai'=>'verse','google'=>'de-DE-Chirp3-HD-Puck','azure'=>'de-DE-FlorianMultilingualNeural','browser'=>['Killian','Conrad','Florian','Markus','Yannick']],
];
/** Ton der Sprechweise → passende Voreinstellung je Geschlecht. */
const ASSISTANT_TONE_PRESET = ['f'=>['warm'=>'f_warm','ruhig'=>'f_warm','energisch'=>'f_frisch','sachlich'=>'f_klar'],'m'=>['warm'=>'m_freundlich','ruhig'=>'m_ruhig','energisch'=>'m_freundlich','sachlich'=>'m_souveraen']];
/** Branche (Stichwort) → passende Agenten, erste Wahl zuerst. */
const ASSISTANT_SECTOR_MAP = [
    ['/pizz|restaurant|imbiss|liefer|döner|doener|burger|sushi|gastro|trattoria|grill|kebap|kebab|asia/iu',['agent-bestellung','gastro-agent']],
    ['/café|cafe|bistro|bar\b|kneipe|brunch/iu',['gastro-agent','agent-bestellung']],
    ['/friseur|frisör|salon|kosmetik|nagel|nail|massage|physio|tattoo|fitness|yoga|barber|beauty|wellness|spa\b|fußpflege|podolog/iu',['agent-termine','agent-phone']],
    ['/zahnarzt|arztpraxis|praxis|ärzt|arzt|therapeut|heilpraktik|tierarzt/iu',['agent-termine','praxis-agent']],
    ['/taxi|mietwagen|shuttle|chauffeur|fahrdienst|krankenfahrt/iu',['agent-taxi']],
    ['/hotel|pension|apartment|ferienwohnung|hostel|gasthof|gästehaus|b&b/iu',['agent-hotel']],
    ['/kanzlei|anwalt|anwält|rechtsanw|notar/iu',['agent-kanzlei']],
    ['/steuer|buchhaltung|lohnbüro/iu',['agent-steuer']],
    ['/inkasso|forderung/iu',['agent-inkasso']],
    ['/hausverwalt|weg-verwalt|mietverwalt/iu',['agent-hausverwaltung']],
    ['/immobil|makler|wohnung|vermiet/iu',['immo-agent']],
    ['/versicher/iu',['agent-versicherung']],
    ['/autohaus|werkstatt|kfz|reifen|auto/iu',['agent-autohaus']],
    ['/shop|onlineshop|e-commerce|ecommerce|handel|versand|boutique/iu',['agent-support']],
    ['/website|homepage|webseite/iu',['agent-website']],
    ['/handwerk|elektr|sanitär|maler|dachdeck|tischler|schreiner|installat/iu',['agent-phone','agent-backoffice']],
];

/* ======================================================================= Tabellen */
function assistant_bootstrap(): void {
    static $done=false; if($done) return; $done=true; $p=th_db();
    $p->exec("CREATE TABLE IF NOT EXISTS assistant_profiles (
        account_id INT NOT NULL PRIMARY KEY, name VARCHAR(30) NOT NULL DEFAULT 'Lea', gender CHAR(1) NOT NULL DEFAULT 'f',
        portrait VARCHAR(40) NOT NULL DEFAULT '', portrait_data MEDIUMTEXT NULL, voice TEXT NULL, personality TEXT NULL,
        lang VARCHAR(8) NOT NULL DEFAULT 'de', updated_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS assistant_messages (
        id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, role VARCHAR(12) NOT NULL, content MEDIUMTEXT NOT NULL, meta TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX(account_id, id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS assistant_memory (
        id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, topic VARCHAR(60) NOT NULL, fact VARCHAR(500) NOT NULL,
        source VARCHAR(20) NOT NULL DEFAULT 'chat', created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_fact (account_id, topic, fact(190)), INDEX(account_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/* ======================================================================= Kleine Helfer */
function assistant_str($v, int $max=0): string { $s=is_scalar($v) ? trim(str_replace("\r",'',(string)$v)) : ''; $s=preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u','',$s) ?? ''; return $max>0 ? mb_substr($s,0,$max) : $s; }
function assistant_line($v, int $max): string { return mb_substr(trim(preg_replace('/\s+/u',' ',assistant_str($v)) ?? ''),0,$max); }
function assistant_eur(int $c): string { return number_format($c/100,2,',','.').' €'; }
function assistant_cents($v): ?int {
    if(is_int($v) || is_float($v)) return $v<0 ? null : (int)round($v*100);
    $s=str_replace(['€','EUR','eur',' '],'',assistant_str($v)); if($s==='') return null;
    if(preg_match('/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/',$s)) $s=str_replace(['.',','],['','.'],$s); else $s=str_replace(',','.',$s);
    return is_numeric($s) && (float)$s>=0 ? (int)round((float)$s*100) : null;
}
function assistant_truthy($v): ?bool { if($v===null || $v==='') return null; if(is_bool($v)) return $v; if(is_numeric($v)) return (float)$v>0; return in_array(mb_strtolower(trim((string)$v)),['1','true','ja','yes','on','an','ein'],true); }
function assistant_tz(): DateTimeZone { return new DateTimeZone('Europe/Berlin'); }
function assistant_now_text(): string {
    $d=new DateTimeImmutable('now',assistant_tz()); $w=['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'][(int)$d->format('w')];
    return $w.', '.$d->format('d.m.Y, H:i').' Uhr';
}
function assistant_plan_name(string $id): string { $p=ch_plan($id); return $p ? $p['name'] : $id; }
function assistant_has_tab(string $tab): bool {
    if(in_array($tab,['home','agents','phone','termine','bestellungen','tokens'],true)) return true;
    return isset(ASSISTANT_TABS[$tab]) && is_file(__DIR__.'/studio-panels/'.$tab.'.html') && is_file(__DIR__.'/studio-panels/'.$tab.'.js');
}

/* ======================================================================= Porträts */
/** Ordner mit avatars.json: live /html/media/avatars (neben /html/chat), im Repository site/media/avatars. ASSISTANT_AVATAR_DIR nur für Tests. */
function assistant_avatar_dir(): ?string {
    $dirs=defined('ASSISTANT_AVATAR_DIR') ? [(string)ASSISTANT_AVATAR_DIR] : [__DIR__.'/../media/avatars',__DIR__.'/../site/media/avatars'];
    foreach($dirs as $d) if(is_file($d.'/avatars.json')) return $d;
    return null;
}
/** SVG-Platzhalter als data-URL (keine fremden Bilder). */
function assistant_svg_portrait(array $c, string $initial): string {
    $i=htmlspecialchars(mb_strtoupper(mb_substr($initial!=='' ? $initial : 'A',0,1)),ENT_QUOTES|ENT_XML1);
    $svg='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 500"><defs>'
        .'<radialGradient id="g" cx="32%" cy="22%" r="95%"><stop offset="0" stop-color="#ffffff"/><stop offset=".38" stop-color="'.$c[0].'"/><stop offset="1" stop-color="'.$c[1].'"/></radialGradient>'
        .'<linearGradient id="b" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="'.$c[2].'" stop-opacity=".30"/><stop offset="1" stop-color="'.$c[2].'" stop-opacity=".62"/></linearGradient></defs>'
        .'<rect width="400" height="500" fill="url(#g)"/>'
        .'<circle cx="200" cy="205" r="74" fill="url(#b)"/>'
        .'<path d="M58 500c6-92 66-150 142-150s136 58 142 150z" fill="url(#b)"/>'
        .'<text x="200" y="232" text-anchor="middle" font-family="Fraunces,Georgia,serif" font-size="82" font-weight="600" fill="#ffffff" fill-opacity=".92">'.$i.'</text></svg>';
    return 'data:image/svg+xml;base64,'.base64_encode($svg);
}
/** Liste [{id,url,gender,label,age}] – aus avatars.json (nur vorhandene Dateien) oder die eingebauten Platzhalter. */
function assistant_portraits(): array {
    static $list=null; if($list!==null) return $list; $list=[];
    $dir=assistant_avatar_dir();
    if($dir){ $j=json_decode((string)@file_get_contents($dir.'/avatars.json'),true);
        foreach(is_array($j) ? $j : [] as $a){
            if(!is_array($a)) continue; $id=(string)($a['id'] ?? ''); $file=(string)($a['file'] ?? ''); $g=(string)($a['gender'] ?? '');
            if(!preg_match('/^[A-Za-z0-9_-]{1,40}$/',$id) || !preg_match('/^[A-Za-z0-9_.-]{1,80}\.(jpe?g|png|webp)$/i',$file) || !in_array($g,['f','m'],true) || !is_file($dir.'/'.$file)) continue;
            $list[]=['id'=>$id,'url'=>'/media/avatars/'.rawurlencode($file),'gender'=>$g,'label'=>assistant_line($a['label'] ?? '',40),'age'=>(int)($a['age'] ?? 0),'stock'=>true];
            if(count($list)>=60) break; } }
    if(!$list) foreach(ASSISTANT_FALLBACK_PORTRAITS as $f) $list[]=['id'=>$f['id'],'url'=>assistant_svg_portrait($f['c'],$f['label']),'gender'=>$f['gender'],'label'=>$f['label'],'age'=>$f['age'],'stock'=>false];
    return $list;
}
function assistant_portrait(string $id): ?array { foreach(assistant_portraits() as $p) if($p['id']===$id) return $p; return null; }
function assistant_default_portrait(string $gender): string { foreach(assistant_portraits() as $p) if($p['gender']===$gender) return $p['id']; return assistant_portraits()[0]['id'] ?? ''; }
/** Bild-URL fürs Profil: eigenes Foto, echtes Porträt oder Platzhalter mit der Initiale des Assistenten-Namens. */
function assistant_portrait_url(array $pr): string {
    if($pr['portrait']==='custom' && !empty($pr['portrait_data'])) return (string)$pr['portrait_data'];
    $p=assistant_portrait($pr['portrait']) ?? assistant_portrait(assistant_default_portrait($pr['gender']));
    if(!$p) return '';
    if(!$p['stock']){ foreach(ASSISTANT_FALLBACK_PORTRAITS as $f) if($f['id']===$p['id']) return assistant_svg_portrait($f['c'],$pr['name']); }
    return $p['url'];
}
/**
 * Eigenes Foto prüfen: data-URL (jpeg/png/webp), ≤ 350 KB, echtes Rasterbild (getimagesizefromstring), 64–2048 px.
 * Mit GD: auf höchstens 512 px verkleinert und als JPEG neu gespeichert (entfernt Metadaten). Rückgabe [dataUrl|null, Fehlertext|null].
 */
function assistant_photo_check($raw): array {
    $s=is_string($raw) ? trim($raw) : '';
    if(!preg_match('#^data:image/(jpeg|jpg|png|webp);base64,([A-Za-z0-9+/=\s]+)$#',$s,$m)) return [null,'Bitte ein Foto als JPEG, PNG oder WebP hochladen.'];
    if(strlen($m[2])>(int)ceil(ASSISTANT_PHOTO_MAX*4/3)+64) return [null,'Das Foto ist zu groß (höchstens 350 KB).'];
    $bin=base64_decode(preg_replace('/\s+/','',$m[2]),true);
    if($bin===false || $bin==='') return [null,'Das Foto ist beschädigt – bitte erneut auswählen.'];
    if(strlen($bin)>ASSISTANT_PHOTO_MAX) return [null,'Das Foto ist zu groß (höchstens 350 KB).'];
    $info=@getimagesizefromstring($bin);
    if(!$info || !in_array($info['mime'] ?? '',['image/jpeg','image/png','image/webp'],true)) return [null,'Das ist kein gültiges JPEG-, PNG- oder WebP-Bild.'];
    [$w,$h]=[(int)$info[0],(int)$info[1]];
    if($w<64 || $h<64) return [null,'Das Foto ist zu klein (mindestens 64 × 64 Pixel).'];
    if($w>2048 || $h>2048) return [null,'Das Foto ist zu groß (höchstens 2048 Pixel Kantenlänge).'];
    $mime=$info['mime'];
    if(function_exists('imagecreatefromstring') && function_exists('imagejpeg')){
        $im=@imagecreatefromstring($bin);
        if($im!==false){
            $sc=min(1,512/max($w,$h)); $nw=max(1,(int)round($w*$sc)); $nh=max(1,(int)round($h*$sc));
            $out=imagecreatetruecolor($nw,$nh); imagefill($out,0,0,imagecolorallocate($out,255,255,255));
            imagecopyresampled($out,$im,0,0,0,0,$nw,$nh,$w,$h); ob_start(); imagejpeg($out,null,86); $jpg=(string)ob_get_clean();
            imagedestroy($im); imagedestroy($out);
            if($jpg!=='' && strlen($jpg)<=ASSISTANT_PHOTO_MAX){ $bin=$jpg; $mime='image/jpeg'; }
        }
    }
    return ['data:'.$mime.';base64,'.base64_encode($bin),null];
}

/* ======================================================================= Profil (Persona) */
function assistant_profile_defaults(int $acc): array {
    return ['account_id'=>$acc,'name'=>'Lea','gender'=>'f','portrait'=>assistant_default_portrait('f'),'portrait_data'=>null,
        'voice'=>['tone'=>'warm','rate'=>1.0,'pitch'=>1.0,'preset'=>assistant_preset_for('f','warm')],'personality'=>['address'=>'du','mood'=>'herzlich','humor'=>1,'detail'=>'kurz'],'lang'=>'de','saved'=>false];
}
/** Stimm-Voreinstellungen: id → {gender,label,desc,rate,pitch,openai,google,azure,browser[]}. speech-lib (ch_tts_voices) hat Vorrang. */
function assistant_voice_presets(): array {
    static $out=null; if($out!==null) return $out; $out=ASSISTANT_VOICE_PRESETS;
    if(function_exists('ch_tts_voices')){
        try{ $list=ch_tts_voices(); }catch(Throwable $e){ $list=[]; }
        foreach(is_array($list) ? $list : [] as $k=>$v){
            if(!is_array($v)) continue; $id=(string)($v['id'] ?? (is_string($k) ? $k : ''));
            if(!preg_match('/^[a-z]_[a-z0-9_]{2,30}$/',$id) || !in_array($v['gender'] ?? '',['f','m'],true)) continue;
            $base=$out[$id] ?? ASSISTANT_VOICE_PRESETS[$v['gender']==='m' ? 'm_freundlich' : 'f_warm'];
            $br=$v['browser'] ?? $base['browser']; $br=is_array($br) ? $br : (is_string($br) && $br!=='' ? preg_split('/\s*[,|]\s*/',$br) : $base['browser']);
            $out[$id]=['gender'=>$v['gender'],'label'=>assistant_line($v['label'] ?? $base['label'],30),'desc'=>assistant_line($v['desc'] ?? $base['desc'],120),
                'rate'=>is_numeric($v['rate'] ?? null) ? (float)$v['rate'] : $base['rate'],'pitch'=>is_numeric($v['pitch'] ?? null) ? (float)$v['pitch'] : $base['pitch'],
                'openai'=>(string)($v['openai'] ?? $base['openai']),'google'=>(string)($v['google'] ?? $base['google']),'azure'=>(string)($v['azure'] ?? $base['azure']),
                'browser'=>array_values(array_filter(array_map(fn($x)=>assistant_line($x,40),(array)$br),fn($x)=>$x!==''))];
        }
    }
    return $out;
}
function assistant_preset_for(string $gender, string $tone): string { return ASSISTANT_TONE_PRESET[$gender==='m' ? 'm' : 'f'][$tone] ?? ($gender==='m' ? 'm_freundlich' : 'f_warm'); }
/** Liste fürs Panel (ohne Anbieter-Stimmnamen). */
function assistant_voice_presets_public(): array {
    $o=[]; foreach(assistant_voice_presets() as $id=>$v) $o[]=['id'=>$id,'gender'=>$v['gender'],'label'=>$v['label'],'desc'=>$v['desc'],'rate'=>$v['rate'],'pitch'=>$v['pitch'],'browser'=>$v['browser']];
    return $o;
}
function assistant_profile(int $acc): array {
    assistant_bootstrap(); $d=assistant_profile_defaults($acc);
    if($acc<=0) return $d;
    $s=th_db()->prepare("SELECT * FROM assistant_profiles WHERE account_id=?"); $s->execute([$acc]); $r=$s->fetch(PDO::FETCH_ASSOC);
    if(!$r) return $d;
    $v=json_decode((string)($r['voice'] ?? ''),true); $p=json_decode((string)($r['personality'] ?? ''),true);
    $o=['account_id'=>$acc,'name'=>(string)$r['name'] ?: 'Lea','gender'=>$r['gender']==='m' ? 'm' : 'f','portrait'=>(string)$r['portrait'],'portrait_data'=>$r['portrait_data'] ?: null,
        'voice'=>array_merge($d['voice'],is_array($v) ? $v : []),'personality'=>array_merge($d['personality'],is_array($p) ? $p : []),'lang'=>in_array($r['lang'],ASSISTANT_LANGS,true) ? $r['lang'] : 'de','saved'=>true];
    if(!in_array($o['voice']['tone'],ASSISTANT_TONES,true)) $o['voice']['tone']='warm';
    $o['voice']['rate']=max(0.7,min(1.3,round((float)$o['voice']['rate'],2))); $o['voice']['pitch']=max(0.7,min(1.3,round((float)$o['voice']['pitch'],2)));
    if(!in_array($o['personality']['address'],['du','sie'],true)) $o['personality']['address']='du';
    if(!in_array($o['personality']['mood'],ASSISTANT_MOODS,true)) $o['personality']['mood']='herzlich';
    $o['personality']['humor']=max(0,min(2,(int)$o['personality']['humor']));
    if(!in_array($o['personality']['detail'],['kurz','ausführlich'],true)) $o['personality']['detail']='kurz';
    $pre=assistant_voice_presets(); $pid=(string)($o['voice']['preset'] ?? '');
    if(!isset($pre[$pid]) || $pre[$pid]['gender']!==$o['gender']) $o['voice']['preset']=assistant_preset_for($o['gender'],$o['voice']['tone']);
    if($o['portrait']!=='custom' && !assistant_portrait($o['portrait'])) $o['portrait']=assistant_default_portrait($o['gender']);
    if($o['portrait']==='custom' && !$o['portrait_data']) $o['portrait']=assistant_default_portrait($o['gender']);
    return $o;
}
/** Rollenbezeichnung für die Oberfläche. */
function assistant_role_label(array $pr): string {
    $sie=$pr['personality']['address']==='sie';
    return $pr['gender']==='m' ? ($sie ? 'Ihr persönlicher Assistent' : 'Dein persönlicher Assistent') : ($sie ? 'Ihre persönliche Assistentin' : 'Deine persönliche Assistentin');
}
function assistant_profile_public(array $pr): array {
    $pre=assistant_voice_presets()[$pr['voice']['preset']] ?? null;
    return ['name'=>$pr['name'],'gender'=>$pr['gender'],'portrait'=>$pr['portrait'],'portrait_url'=>assistant_portrait_url($pr),'has_photo'=>!empty($pr['portrait_data']),'voice_label'=>$pre ? $pre['label'] : '',
        'voice'=>$pr['voice'],'personality'=>$pr['personality'],'lang'=>$pr['lang'],'role'=>assistant_role_label($pr),'saved'=>$pr['saved']];
}
/**
 * Profil speichern (nur übergebene Felder ändern sich). Felder: name, gender, portrait, photo (data-URL), remove_photo,
 * voice{tone,rate,pitch}, personality{address,mood,humor,detail}, lang. Fehler → $err=['error'=>'invalid','field'=>…,'message'=>…], nichts gespeichert.
 */
function assistant_profile_save(int $acc, array $d, ?array &$err=null): array {
    assistant_bootstrap(); $err=null; $c=assistant_profile($acc);
    if($acc<=0){ $err=['error'=>'demo_readonly','message'=>'In der Demo kann nichts gespeichert werden.']; return $c; }
    $bad=function(string $f,string $m) use(&$err,$c){ $err=['error'=>'invalid','field'=>$f,'message'=>$m]; return $c; };
    $v=$c;
    if(array_key_exists('name',$d)){
        $n=trim(preg_replace('/\s+/u',' ',assistant_str($d['name'])) ?? '');
        if($n==='' || mb_strlen($n)>30 || !preg_match('/^\p{L}[\p{L}\p{M} \-]*$/u',$n)) return $bad('name','Name: 1–30 Zeichen, nur Buchstaben, Leerzeichen und Bindestrich.');
        $v['name']=$n; }
    if(array_key_exists('gender',$d)){ $g=assistant_str($d['gender']); if(!in_array($g,['f','m'],true)) return $bad('gender','Bitte „Frau“ oder „Mann“ wählen.'); $v['gender']=$g; }
    if(!empty($d['remove_photo'])){ $v['portrait_data']=null; if($v['portrait']==='custom') $v['portrait']=assistant_default_portrait($v['gender']); }
    if(array_key_exists('photo',$d) && $d['photo']!==null && $d['photo']!==''){
        [$url,$perr]=assistant_photo_check($d['photo']); if($perr!==null) return $bad('photo',$perr);
        $v['portrait_data']=$url; $v['portrait']='custom'; }
    if(array_key_exists('portrait',$d) && !(array_key_exists('photo',$d) && $d['photo'])){
        $pid=assistant_str($d['portrait'],40);
        if($pid==='custom'){ if(empty($v['portrait_data'])) return $bad('portrait','Bitte zuerst ein eigenes Foto hochladen.'); $v['portrait']='custom'; }
        elseif($pid!==''){ $pp=assistant_portrait($pid); if(!$pp) return $bad('portrait','Dieses Porträt gibt es nicht.'); $v['portrait']=$pid; }
    } elseif($v['gender']!==$c['gender'] && $v['portrait']!=='custom'){ $pp=assistant_portrait($v['portrait']); if(!$pp || $pp['gender']!==$v['gender']) $v['portrait']=assistant_default_portrait($v['gender']); }
    if(array_key_exists('voice',$d)){
        if(!is_array($d['voice'])) return $bad('voice','Ungültige Stimmeinstellung.'); $vo=$d['voice'];
        if(isset($vo['tone'])){ if(!in_array($vo['tone'],ASSISTANT_TONES,true)) return $bad('voice.tone','Stimme: warm, ruhig, energisch oder sachlich.'); $v['voice']['tone']=$vo['tone']; }
        foreach(['rate','pitch'] as $k) if(isset($vo[$k])){ if(!is_numeric($vo[$k])) return $bad('voice.'.$k,'Ungültiger Wert für '.($k==='rate' ? 'das Tempo' : 'die Tonhöhe').'.'); $v['voice'][$k]=max(0.7,min(1.3,round((float)$vo[$k],2))); }
        if(isset($vo['preset']) && $vo['preset']!==''){ $pre=assistant_voice_presets(); $pid=assistant_str($vo['preset'],40);
            if(!isset($pre[$pid])) return $bad('voice.preset','Diese Stimme gibt es nicht.');
            if($pre[$pid]['gender']!==$v['gender']) return $bad('voice.preset','Die Stimme passt nicht zum gewählten Geschlecht.');
            $v['voice']['preset']=$pid; }
    }
    $pre=assistant_voice_presets(); $pid=(string)($v['voice']['preset'] ?? '');
    if(!isset($pre[$pid]) || $pre[$pid]['gender']!==$v['gender']) $v['voice']['preset']=assistant_preset_for($v['gender'],$v['voice']['tone']);
    if(array_key_exists('personality',$d)){
        if(!is_array($d['personality'])) return $bad('personality','Ungültige Persönlichkeit.'); $pe=$d['personality'];
        if(isset($pe['address'])){ if(!in_array($pe['address'],['du','sie'],true)) return $bad('personality.address','Anrede: du oder Sie.'); $v['personality']['address']=$pe['address']; }
        if(isset($pe['mood'])){ if(!in_array($pe['mood'],ASSISTANT_MOODS,true)) return $bad('personality.mood','Persönlichkeit: herzlich, professionell oder locker.'); $v['personality']['mood']=$pe['mood']; }
        if(isset($pe['humor'])){ if(!is_numeric($pe['humor']) || (int)$pe['humor']<0 || (int)$pe['humor']>2) return $bad('personality.humor','Humor: 0, 1 oder 2.'); $v['personality']['humor']=(int)$pe['humor']; }
        if(isset($pe['detail'])){ if(!in_array($pe['detail'],['kurz','ausführlich'],true)) return $bad('personality.detail','Antwortlänge: kurz oder ausführlich.'); $v['personality']['detail']=$pe['detail']; }
    }
    if(array_key_exists('lang',$d)){ $l=assistant_str($d['lang'],8); if(!in_array($l,ASSISTANT_LANGS,true)) return $bad('lang','Diese Sprache wird nicht unterstützt.'); $v['lang']=$l; }
    th_db()->prepare("INSERT INTO assistant_profiles(account_id,name,gender,portrait,portrait_data,voice,personality,lang) VALUES(?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE name=VALUES(name),gender=VALUES(gender),portrait=VALUES(portrait),portrait_data=VALUES(portrait_data),voice=VALUES(voice),personality=VALUES(personality),lang=VALUES(lang),updated_at=NOW()")
      ->execute([$acc,$v['name'],$v['gender'],$v['portrait'],$v['portrait_data'],json_encode($v['voice']),json_encode($v['personality'],JSON_UNESCAPED_UNICODE),$v['lang']]);
    return assistant_profile($acc);
}
/* Modul-Vertrag: Einstellungen = Profil */
function assistant_settings(int $acc): array { return assistant_profile($acc); }
function assistant_settings_save(int $acc, array $d): array { return assistant_profile_save($acc,$d); }
function assistant_settings_public(array $s): array { return assistant_profile_public($s); }
/** Kein Kundenkanal-Modul: die Werkzeuge gehören nur dem Inhaber im Studio (Widget/Telefon/WhatsApp bekommen sie nie). */
function assistant_enabled(int $acc): bool { return false; }
function assistant_system_hint(int $acc, string $channel='chat'): string { return ''; }

/* ======================================================================= Stimme */
function assistant_tts_ready(): bool {
    if(!function_exists('ch_tts_synth') || !function_exists('ch_tts_provider')) return false;
    try{ return ch_tts_provider()!==null; }catch(Throwable $e){ return false; }
}
/** Stimmparameter für ch_tts_synth aus Geschlecht, Ton und Persönlichkeit. $over = ungespeicherte Vorschau (Hörprobe). */
function assistant_voice(array $pr, array $over=[]): array {
    $g=in_array($over['gender'] ?? '',['f','m'],true) ? $over['gender'] : $pr['gender'];
    $tone=in_array($over['tone'] ?? '',ASSISTANT_TONES,true) ? $over['tone'] : $pr['voice']['tone'];
    $rate=isset($over['rate']) && is_numeric($over['rate']) ? max(0.7,min(1.3,(float)$over['rate'])) : (float)$pr['voice']['rate'];
    $pitch=isset($over['pitch']) && is_numeric($over['pitch']) ? max(0.7,min(1.3,(float)$over['pitch'])) : (float)$pr['voice']['pitch'];
    $pre=assistant_voice_presets(); $pid=(string)($over['preset'] ?? ($g===$pr['gender'] ? ($pr['voice']['preset'] ?? '') : ''));
    if(!isset($pre[$pid]) || $pre[$pid]['gender']!==$g) $pid=assistant_preset_for($g,$tone);
    $vm=$pre[$pid];
    $who=$g==='m' ? 'a friendly German man in his thirties' : 'a friendly German woman in her thirties';
    $toneTxt=['warm'=>'warm, kind and reassuring, with a gentle smile in the voice','ruhig'=>'calm, soft and unhurried, with natural pauses','energisch'=>'bright, upbeat and energetic, but never rushed','sachlich'=>'clear, composed and matter-of-fact, precise articulation'][$tone];
    $mood=['herzlich'=>'Sound heartfelt and personal.','professionell'=>'Sound polished and professional.','locker'=>'Sound relaxed and casual, like talking to a colleague.'][$pr['personality']['mood']] ?? '';
    $humor=(int)$pr['personality']['humor']>=2 ? ' A light, playful undertone is welcome.' : '';
    return ['gender'=>$g,'preset'=>$pid,'openai'=>$vm['openai'],'google'=>$vm['google'],'azure'=>$vm['azure'],'lang'=>'de-DE',
        'instructions'=>'Speak natural, native German as '.$who.' who is a personal business assistant. Voice: '.$toneTxt.'. '.$mood.$humor.' Never sound robotic; vary intonation like a real conversation.',
        'rate'=>round(max(0.7,min(1.3,$rate*(float)$vm['rate'])),2),'pitch'=>round(max(0.7,min(1.3,$pitch*(float)$vm['pitch'])),2)];
}
/** Antworttext → vorlesbare Sätze (ohne Markdown, Links, Emojis). */
function assistant_speech(string $text, string $lang='de', bool $du=true): array {
    if(function_exists('ch_speech_sentences')){ try{ $r=ch_speech_sentences($text,$lang,$du); if(is_array($r) && $r) return array_values(array_filter(array_map(fn($x)=>mb_substr(trim((string)$x),0,ASSISTANT_SPEAK_MAX),$r),fn($x)=>$x!=='')); }catch(Throwable $e){} }
    $t=preg_replace('#https?://\S+#u','',$text) ?? $text;
    $t=preg_replace(['/\*\*|__|`+|^#+\s*/mu','/^\s*[-•*]\s+/mu','/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{2705}\x{2728}]/u','/\(\s*\)/u'],['','',' ',''],$t) ?? $t;
    $t=trim(preg_replace('/\s+/u',' ',$t) ?? $t); if($t==='') return [];
    $prot=['z. B.'=>'zum Beispiel','z.B.'=>'zum Beispiel','ca.'=>'circa','bzw.'=>'beziehungsweise','usw.'=>'und so weiter','inkl.'=>'inklusive','ggf.'=>'gegebenenfalls','Nr.'=>'Nummer','Str.'=>'Straße','Dr.'=>'Doktor','evtl.'=>'eventuell','d. h.'=>'das heißt','u. a.'=>'unter anderem'];
    $t=str_replace(array_keys($prot),array_values($prot),$t);
    $parts=preg_split('/(?<=[.!?…])\s+(?=[\p{Lu}\p{N}„"»(])/u',$t) ?: [$t]; $out=[];
    foreach($parts as $p){ $p=trim($p); if($p==='') continue;
        while(mb_strlen($p)>ASSISTANT_SPEAK_MAX){ $cut=mb_strrpos(mb_substr($p,0,ASSISTANT_SPEAK_MAX),', '); if($cut===false || $cut<60) $cut=mb_strrpos(mb_substr($p,0,ASSISTANT_SPEAK_MAX),' '); if($cut===false || $cut<30) $cut=ASSISTANT_SPEAK_MAX-1; $out[]=trim(mb_substr($p,0,$cut+1)); $p=trim(mb_substr($p,$cut+1)); }
        if($p!=='') $out[]=$p; }
    return $out;
}

/* ======================================================================= Gedächtnis */
function assistant_memory(int $acc, int $limit=100): array {
    assistant_bootstrap(); if($acc<=0) return [];
    $s=th_db()->prepare("SELECT id,topic,fact,source FROM assistant_memory WHERE account_id=? ORDER BY id DESC LIMIT ".max(1,min(300,$limit))); $s->execute([$acc]);
    return array_map(fn($r)=>['id'=>(int)$r['id'],'topic'=>$r['topic'],'fact'=>$r['fact'],'source'=>$r['source']],$s->fetchAll(PDO::FETCH_ASSOC));
}
/** Fakt merken. $replace = ältere Fakten zum selben Thema ersetzen. Rückgabe ['id','new'] oder Fehler. */
function assistant_remember(int $acc, string $topic, string $fact, string $source='chat', bool $replace=false): array {
    assistant_bootstrap(); if($acc<=0) return ['error'=>'demo_readonly','hint'=>'In der Demo wird nichts gespeichert.'];
    $topic=assistant_line($topic,60); $fact=assistant_line($fact,500);
    if($topic==='' || $fact==='') return ['error'=>'topic_and_fact_required','hint'=>'Bitte ein kurzes Thema und den Fakt angeben.'];
    $p=th_db();
    if($replace) $p->prepare("DELETE FROM assistant_memory WHERE account_id=? AND topic=? AND fact<>?")->execute([$acc,$topic,$fact]);
    $s=$p->prepare("INSERT IGNORE INTO assistant_memory(account_id,topic,fact,source) VALUES(?,?,?,?)"); $s->execute([$acc,$topic,$fact,mb_substr($source,0,20)]);
    $new=$s->rowCount()>0;
    $q=$p->prepare("SELECT id FROM assistant_memory WHERE account_id=? AND topic=? AND fact=? LIMIT 1"); $q->execute([$acc,$topic,$fact]); $id=(int)$q->fetchColumn();
    $c=$p->prepare("SELECT COUNT(*) FROM assistant_memory WHERE account_id=?"); $c->execute([$acc]); $n=(int)$c->fetchColumn();
    if($n>ASSISTANT_MEMORY_MAX){ $o=$p->prepare("SELECT id FROM assistant_memory WHERE account_id=? ORDER BY id ASC LIMIT ".($n-ASSISTANT_MEMORY_MAX)); $o->execute([$acc]); $ids=array_map('intval',$o->fetchAll(PDO::FETCH_COLUMN));
        if($ids) $p->prepare("DELETE FROM assistant_memory WHERE account_id=? AND id IN (".implode(',',$ids).")")->execute([$acc]); }
    return ['id'=>$id,'new'=>$new];
}
function assistant_forget(int $acc, int $id): bool {
    assistant_bootstrap(); if($acc<=0 || $id<=0) return false;
    $s=th_db()->prepare("DELETE FROM assistant_memory WHERE id=? AND account_id=?"); $s->execute([$id,$acc]); return $s->rowCount()>0;
}

/* ======================================================================= Verlauf */
function assistant_history(int $acc, int $limit=30): array {
    assistant_bootstrap(); if($acc<=0) return [];
    $s=th_db()->prepare("SELECT id,role,content,meta,created_at FROM assistant_messages WHERE account_id=? ORDER BY id DESC LIMIT ".max(1,min(200,$limit))); $s->execute([$acc]);
    $rows=array_reverse($s->fetchAll(PDO::FETCH_ASSOC));
    return array_map(function($r){ $m=json_decode((string)($r['meta'] ?? ''),true); $m=is_array($m) ? $m : [];
        return ['id'=>(int)$r['id'],'role'=>$r['role'],'content'=>$r['content'],'actions'=>array_values((array)($m['actions'] ?? [])),'greeting'=>!empty($m['greeting']),'created_at'=>th_dbiso($r['created_at'])]; },$rows);
}
function assistant_add_message(int $acc, string $role, string $content, array $meta=[]): int {
    assistant_bootstrap(); $p=th_db();
    $p->prepare("INSERT INTO assistant_messages(account_id,role,content,meta) VALUES(?,?,?,?)")->execute([$acc,$role==='assistant' ? 'assistant' : 'user',mb_substr($content,0,20000),$meta ? json_encode($meta,JSON_UNESCAPED_UNICODE) : null]);
    $id=(int)$p->lastInsertId();
    if(random_int(1,20)===1){ $q=$p->prepare("SELECT id FROM assistant_messages WHERE account_id=? ORDER BY id DESC LIMIT 1 OFFSET ".ASSISTANT_HISTORY_KEEP); $q->execute([$acc]); $cut=(int)$q->fetchColumn();
        if($cut>0) $p->prepare("DELETE FROM assistant_messages WHERE account_id=? AND id<=?")->execute([$acc,$cut]); }
    return $id;
}
/** Verlauf für die KI: erledigte Aktionen werden als kurze Notiz an die Antwort gehängt, damit der Assistent weiß, was er schon getan hat. */
function assistant_ai_messages(int $acc): array {
    $out=[];
    foreach(assistant_history($acc,ASSISTANT_HISTORY_AI) as $h){
        $c=mb_substr((string)$h['content'],0,4000);
        if($h['role']==='assistant' && $h['actions']) $c.="\n(Ausgeführt: ".implode('; ',array_map(fn($a)=>($a['ok'] ? '' : 'FEHLGESCHLAGEN: ').($a['summary'] ?? $a['name'] ?? ''),$h['actions'])).')';
        $out[]=['role'=>$h['role'],'content'=>$c];
    }
    return $out;
}
/** Persönliche Begrüßung beim ersten Besuch (ohne KI, kostenlos). */
function assistant_greeting(int $acc, array $pr, ?array $ov=null): string {
    $ov=$ov ?? assistant_overview($acc); $sie=$pr['personality']['address']==='sie'; $n=$pr['name']; $f=$pr['gender']!=='m';
    $company=''; foreach($ov['configs'] as $c) if($c['company']!==''){ $company=$c['company']; break; }
    if($pr['lang']==='en'){
        return 'Hello! I\'m '.$n.', your personal assistant here in ChatHelp Studio. Just tell me what you need done – I\'ll ask whatever I still need to know and set up your agents with you, step by step. '
            .($company!=='' ? 'I can see "'.$company.'" is already set up. What shall I take care of first?' : 'To start: what kind of business do you run?');
    }
    $hi=['herzlich'=>'Hallo und herzlich willkommen!','professionell'=>'Guten Tag!','locker'=>($sie ? 'Hallo, schön, dass Sie da sind!' : 'Hey, schön, dass du da bist!')][$pr['personality']['mood']] ?? 'Hallo!';
    $role=$f ? ($sie ? 'Ihre persönliche Assistentin' : 'deine persönliche Assistentin') : ($sie ? 'Ihr persönlicher Assistent' : 'dein persönlicher Assistent');
    $mid=$sie ? 'Erzählen Sie mir einfach, was erledigt werden soll – ich frage nach, was ich noch wissen muss, und richte Ihre Agenten Schritt für Schritt mit Ihnen ein.'
              : 'Erzähl mir einfach, was erledigt werden soll – ich frage nach, was ich noch wissen muss, und richte deine Agenten Schritt für Schritt mit dir ein.';
    $end=$company!=='' ? ($sie ? 'Ich sehe, „'.$company.'“ ist schon angelegt. Was soll ich als Erstes für Sie übernehmen?' : 'Ich sehe, „'.$company.'“ ist schon angelegt. Was soll ich als Erstes für dich übernehmen?')
                       : ($sie ? 'Zum Start: Was für einen Betrieb führen Sie?' : 'Zum Start: Was für einen Betrieb hast du?');
    return $hi.' Ich bin '.$n.', '.$role.' hier im ChatHelp Studio. '.$mid.' '.$end;
}
/** Begrüßung einmalig in den Verlauf legen, wenn er leer ist. */
function assistant_ensure_greeting(int $acc, array $pr): void {
    if($acc<=0) return; $s=th_db()->prepare("SELECT COUNT(*) FROM assistant_messages WHERE account_id=?"); $s->execute([$acc]);
    if((int)$s->fetchColumn()===0) assistant_add_message($acc,'assistant',assistant_greeting($acc,$pr),['greeting'=>true]);
}

/* ======================================================================= Konto-Überblick */
function assistant_configs(int $acc): array {
    $s=th_db()->prepare("SELECT * FROM agent_configs WHERE account_id=? ORDER BY updated_at DESC, id DESC"); $s->execute([$acc]); return $s->fetchAll(PDO::FETCH_ASSOC);
}
function assistant_active_plans(int $acc): array {
    $o=[]; foreach(th_plans($acc) as $p){ if(!in_array($p['status'],['active','trialing','past_due'],true)) continue; $pl=ch_plan($p['plan']); if($pl) $o[$p['plan']]=['id'=>$p['plan'],'name'=>$pl['name'],'kind'=>$pl['kind'],'base'=>$pl['base'],'period_end'=>$p['period_end']]; }
    return array_values($o);
}
/** Passende Agenten zu einer Branchenbeschreibung (erste Wahl zuerst). */
function assistant_sector_plans(string $text): array {
    foreach(ASSISTANT_SECTOR_MAP as [$re,$plans]) if(preg_match($re,$text)) return array_values(array_filter($plans,fn($p)=>isset(CH_PLANS[$p])));
    return [];
}
/** Agent, auf den sich Werkzeuge ohne „plan“ beziehen: aktiver Plan → zuletzt bearbeitete Konfiguration → Branche aus dem Gedächtnis → Telefon-Agent. */
function assistant_main_plan(int $acc): string {
    foreach(assistant_active_plans($acc) as $p){ $b=$p['base']; if((CH_PLANS[$b][4] ?? '')==='agent') return $b; }
    foreach(assistant_configs($acc) as $c){ $pl=ch_plan((string)$c['plan']); if($pl && $pl['kind']==='agent') return $pl['id']; }
    $mem=implode(' ',array_map(fn($m)=>$m['topic'].' '.$m['fact'],assistant_memory($acc,60)));
    $sp=assistant_sector_plans($mem); if($sp && (CH_PLANS[$sp[0]][4] ?? '')==='agent') return $sp[0];
    return 'agent-phone';
}
/** Plan-Eingabe der KI auflösen (buy-… → zugrunde liegender Agent). Rückgabe [plan|null, Fehler|null]. */
function assistant_resolve_plan(int $acc, $raw): array {
    $id=preg_replace('/[^a-z0-9-]/','',strtolower(assistant_str($raw)));
    if($id==='') return [assistant_main_plan($acc),null];
    $id=CH_PLAN_BASE[$id] ?? $id; $pl=ch_plan($id);
    if(!$pl || $pl['kind']!=='agent'){ $known=array_map(fn($c)=>(string)$c['plan'],assistant_configs($acc));
        return [null,['error'=>'unknown_plan','hint'=>'Diesen Agenten gibt es nicht. Ohne „plan“ wird der Haupt-Agent ('.assistant_main_plan($acc).') verwendet.'.($known ? ' Vorhandene Agenten: '.implode(', ',$known).'.' : '')]]; }
    return [$id,null];
}
function assistant_db_count(string $sql, array $args): int { try{ $s=th_db()->prepare($sql); $s->execute($args); return (int)$s->fetchColumn(); }catch(Throwable $e){ return 0; } }
/** Kompletter Überblick (Agenten, Module, Guthaben) – Grundlage für Systemprompt, Checkliste und get_overview. */
function assistant_overview(int $acc): array {
    $cfgs=[]; $faqTrain=[];
    if(function_exists('train_bootstrap')){ try{ train_bootstrap(); $s=th_db()->prepare("SELECT plan,COUNT(*) n FROM agent_faq WHERE account_id=? AND active=1 GROUP BY plan"); $s->execute([$acc]); foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r) $faqTrain[$r['plan']]=(int)$r['n']; }catch(Throwable $e){} }
    foreach(assistant_configs($acc) as $c){ $kb=(string)($c['kb'] ?? ''); $plan=(string)$c['plan'];
        $sec=assistant_kb_section_get($kb,'Häufige Fragen'); $kbFaq=$sec===null ? 0 : preg_match_all('/^F:\s*\S/mu',$sec);
        $cfgs[]=['plan'=>$plan,'name'=>assistant_plan_name($plan),'company'=>(string)$c['company'],'tone'=>(string)$c['tone'],'lang'=>(string)$c['lang'],'kb'=>$kb,'kb_chars'=>mb_strlen($kb),
            'widget_on'=>(int)$c['widget_on'],'phone_on'=>(int)$c['phone_on'],'phone_number'=>(string)($c['phone_number'] ?? ''),'phone_hours'=>(string)($c['phone_hours'] ?? ''),
            'has_profile'=>assistant_kb_section_get($kb,'Betriebsprofil')!==null,'faq'=>($faqTrain[$plan] ?? 0)+$kbFaq,'active'=>ch_plan_active_any($acc,$plan),'updated_at'=>$c['updated_at']]; }
    $mods=[];
    try{ $a=appt_settings($acc); $svc=appt_services($acc,true); $hRow=assistant_db_count("SELECT COUNT(*) FROM appt_settings WHERE account_id=? AND hours IS NOT NULL",[$acc]);
        $mods['appt']=['label'=>'Termine','tab'=>'termine','on'=>(int)$a['appt_on']===1,'company'=>(string)$a['company'],'address'=>(string)$a['address'],'services'=>count($svc),'service_names'=>array_slice(array_map(fn($x)=>$x['name'],$svc),0,8),'hours_saved'=>$hRow>0]; }catch(Throwable $e){}
    try{ $o=ord_settings($acc); $menu=ord_menu($acc,true); $hRow=assistant_db_count("SELECT COUNT(*) FROM order_settings WHERE account_id=? AND hours IS NOT NULL",[$acc]);
        $mods['order']=['label'=>'Bestellungen','tab'=>'bestellungen','on'=>(int)$o['order_on']===1,'company'=>(string)$o['name'],'address'=>(string)$o['address'],'items'=>count($menu),'whatsapp'=>(string)$o['wa_number']!=='' || (string)$o['wa_phone_id']!=='','hours_saved'=>$hRow>0]; }catch(Throwable $e){}
    if(function_exists('ride_settings')) try{ $r=ride_settings($acc); $mods['ride']=['label'=>'Taxi','tab'=>'taxi','on'=>(int)$r['ride_on']===1,'company'=>(string)$r['name'],'address'=>(string)$r['address'],'saved'=>!empty($r['saved'])]; }catch(Throwable $e){}
    if(function_exists('hotel_settings')) try{ $h=hotel_settings($acc); $rooms=function_exists('hotel_rooms') ? count(hotel_rooms($acc,true)) : 0; $mods['hotel']=['label'=>'Hotel','tab'=>'hotel','on'=>(int)$h['hotel_on']===1,'company'=>(string)$h['name'],'address'=>(string)$h['address'],'rooms'=>$rooms]; }catch(Throwable $e){}
    if(function_exists('cb_settings')) try{ $cb=cb_settings($acc); $mods['callback']=['label'=>'Rückrufe','tab'=>'rueckruf','on'=>(int)$cb['cb_on']===1,'ai_callback'=>(int)$cb['ai_callback']===1]; }catch(Throwable $e){}
    if(function_exists('train_faq')) $mods['train']=['label'=>'Training','tab'=>'training','on'=>true,'faq'=>array_sum($faqTrain)];
    $tested=assistant_db_count("SELECT COUNT(*) FROM api_usage WHERE account_id=? AND (task LIKE 'agent:%' OR task IN ('widget','phone','whatsapp','booking','train:test'))",[$acc])
           +assistant_db_count("SELECT COUNT(*) FROM phone_calls WHERE account_id=?",[$acc]);
    return ['balance'=>th_balance($acc),'plans'=>assistant_active_plans($acc),'configs'=>$cfgs,'modules'=>$mods,'main_plan'=>assistant_main_plan($acc),'tested'=>$tested];
}
/** Überblick als kompakter Text für den Systemprompt / das Werkzeug get_overview. */
function assistant_overview_text(array $ov): string {
    $l=[];
    $l[]='Guthaben: '.number_format($ov['balance'],0,',','.').' Tokens.';
    $l[]='Gemietete/gekaufte Agenten: '.($ov['plans'] ? implode(', ',array_map(fn($p)=>$p['name'].' ('.$p['id'].')',$ov['plans'])) : 'noch keiner – alles läuft im Studio-Test mit Tokens.');
    if($ov['configs']) foreach($ov['configs'] as $c) $l[]='Agent '.$c['name'].' ('.$c['plan'].'): Firma „'.($c['company'] ?: '–').'“, Ton '.$c['tone'].', Sprache '.$c['lang'].', Wissensbasis '.number_format($c['kb_chars'],0,',','.').' Zeichen'
        .($c['has_profile'] ? ' (mit Betriebsprofil)' : '').', FAQ '.$c['faq'].', Widget '.($c['widget_on'] ? 'an' : 'aus').', Telefon '.($c['phone_on'] ? 'an'.($c['phone_number'] ? ' ('.$c['phone_number'].')' : '') : 'aus').($c['active'] ? ', aktiv' : '').'.';
    else $l[]='Agent-Konfigurationen: noch keine.';
    $l[]='Haupt-Agent für Werkzeuge ohne „plan“: '.$ov['main_plan'].' ('.assistant_plan_name($ov['main_plan']).').';
    foreach($ov['modules'] as $k=>$m){
        $x=$m['label'].': '.($m['on'] ? 'eingeschaltet' : 'aus');
        if($k==='appt') $x.=', '.$m['services'].' Leistungen'.($m['service_names'] ? ' ('.implode(', ',$m['service_names']).')' : '');
        if($k==='order') $x.=', '.$m['items'].' Artikel auf der Karte'.($m['whatsapp'] ? ', WhatsApp verbunden' : '');
        if($k==='hotel') $x.=', '.$m['rooms'].' Zimmerkategorien';
        if($k==='callback') $x.=$m['ai_callback'] ? ', KI-Rückruf an' : '';
        if($k==='train') $x='Training: '.$m['faq'].' FAQ-Antworten';
        $l[]=$x.'.';
    }
    $l[]='Testgespräche bisher: '.($ov['tested']>0 ? 'ja' : 'noch keine').'.';
    return '- '.implode("\n- ",$l);
}

/* ======================================================================= Wissensbasis-Abschnitte (## Titel) */
function assistant_section_title(string $t): string { return mb_substr(trim(preg_replace('/[#\r\n]+/u',' ',$t) ?? ''),0,60); }
function assistant_kb_section_get(string $kb, string $title): ?string {
    $t=preg_quote(assistant_section_title($title),'/');
    if(!preg_match('/^##[ \t]+'.$t.'[ \t]*$/miu',$kb,$m,PREG_OFFSET_CAPTURE)) return null;
    $start=$m[0][1]+strlen($m[0][0]); $rest=substr($kb,$start);
    $end=preg_match('/^##[ \t]+\S/mu',$rest,$n,PREG_OFFSET_CAPTURE) ? $n[0][1] : strlen($rest);
    return trim(substr($rest,0,$end));
}
/** Abschnitt setzen: mode replace (ersetzen) oder append (anhängen); fehlt er, wird er angelegt ($top = an den Anfang). */
function assistant_kb_section_set(string $kb, string $title, string $text, string $mode='replace', bool $top=false): string {
    $title=assistant_section_title($title); $text=trim($text); $t=preg_quote($title,'/'); $head='## '.$title;
    if(preg_match('/^##[ \t]+'.$t.'[ \t]*$/miu',$kb,$m,PREG_OFFSET_CAPTURE)){
        $start=$m[0][1]; $bodyStart=$start+strlen($m[0][0]); $rest=substr($kb,$bodyStart);
        $end=preg_match('/^##[ \t]+\S/mu',$rest,$n,PREG_OFFSET_CAPTURE) ? $n[0][1] : strlen($rest);
        $old=trim(substr($rest,0,$end)); $body=$mode==='append' && $old!=='' ? $old."\n".$text : $text;
        $after=ltrim(substr($rest,$end));
        return rtrim(substr($kb,0,$start)).($start>0 ? "\n\n" : '').$head."\n".$body.($after!=='' ? "\n\n".$after : '');
    }
    $kb=trim($kb); $sec=$head."\n".$text;
    if($kb==='') return $sec;
    return $top ? $sec."\n\n".$kb : $kb."\n\n".$sec;
}
/** Wissensbasis speichern, ohne Widget/Telefon versehentlich abzuschalten. */
function assistant_cfg_save(int $acc, string $plan, array $d): void {
    $cur=th_agent_cfg($acc,$plan);
    $keep=ch_plan_active_any($acc,$plan) || !empty($cur['widget_on']) || !empty($cur['phone_on']);
    th_agent_cfg_save($acc,$plan,$d,$keep);
    if(function_exists('train_cache_bust')) train_cache_bust();
}

/* ======================================================================= Checkliste */
const ASSISTANT_CHECK = [
    'profil'=>['Betriebsprofil','Name, Adresse, Telefon und was dein Betrieb macht.','Ich erzähle dir kurz von meinem Betrieb.'],
    'zeiten'=>['Öffnungszeiten','Wann bist du für Kundinnen und Kunden da?','Trag bitte unsere Öffnungszeiten ein.'],
    'angebot'=>['Leistungen, Preise & Karte','Was bietest du an – und zu welchem Preis?','Lass uns meine Leistungen und Preise anlegen.'],
    'faq'=>['Häufige Fragen (mind. 5)','Die Fragen, die Kunden dir ständig stellen – mit deinen Antworten.','Welche Fragen stellen meine Kunden am häufigsten?'],
    'ton'=>['Ton & Sprache','Wie soll dein Agent klingen – du oder Sie, locker oder seriös?','Wie soll mein Agent mit Kunden sprechen?'],
    'kanaele'=>['Website, Telefon oder WhatsApp','Wo sollen Kunden deinen Agenten erreichen?','Wie bringe ich den Agenten auf meine Website oder ans Telefon?'],
    'aktiv'=>['Agent aktiv','Gemietet oder gekauft – dann läuft er rund um die Uhr.','Welcher Agent passt am besten zu meinem Betrieb?'],
    'test'=>['Testgespräch geführt','Einmal selbst ausprobieren, wie er antwortet.','Wie teste ich meinen Agenten am besten?'],
];
function assistant_mem_has(array $mem, string $re): bool { foreach($mem as $m) if(preg_match($re,$m['topic'])) return true; return false; }
/** Checkliste aus echten Daten (Agenten, Module, Training) + Gedächtnis. Rückgabe [items, progress 0–100]. */
function assistant_checklist(int $acc, ?array $ov=null, ?array $mem=null): array {
    $ov=$ov ?? assistant_overview($acc); $mem=$mem ?? assistant_memory($acc,150); $m=$ov['modules']; $c=$ov['configs']; $done=[]; $info=[];
    $kbAll=implode("\n",array_map(fn($x)=>$x['kb'],$c));
    $co=''; foreach($c as $x) if($x['company']!=='' && ($x['has_profile'] || $x['kb_chars']>=300)){ $co=$x['company']; break; }
    if($co==='') foreach(['appt','order','hotel','ride'] as $k) if(!empty($m[$k]['company']) && !empty($m[$k]['address'])){ $co=$m[$k]['company']; break; }
    $done['profil']=$co!==''; if($co!=='') $info['profil']='„'.$co.'“';
    $done['zeiten']=!empty($m['appt']['hours_saved']) || (!empty($m['order']['hours_saved']) && !empty($m['order']['on']))
        || (bool)preg_match('/öffnungszeit|geöffnet|sprechzeit|erreichbar|\b(mo|di|mi|do|fr|sa|so)[a-z]*\.?\s*[–-]\s*(mo|di|mi|do|fr|sa|so)[a-z]*\.?\s+\d|\d{1,2}([:.]\d{2})?\s*[–-]\s*\d{1,2}([:.]\d{2})?\s*uhr/iu',$kbAll)
        || (bool)array_filter($c,fn($x)=>$x['phone_hours']!=='') || assistant_mem_has($mem,'/öffnungszeit|zeiten|geöffnet/iu');
    $offer=(int)($m['appt']['services'] ?? 0)+(int)($m['order']['items'] ?? 0)+(int)($m['hotel']['rooms'] ?? 0);
    $done['angebot']=$offer>0 || !empty($m['ride']['saved']) || (bool)preg_match('/^##[ \t]+(Leistungen|Angebot|Preise|Speisekarte|Sortiment)/miu',$kbAll)
        || assistant_mem_has($mem,'/angebot|leistung|preis|karte|sortiment|produkt/iu');
    if($offer>0) $info['angebot']=$offer.' Einträge';
    $faq=array_sum(array_map(fn($x)=>$x['faq'],$c)); $done['faq']=$faq>=5; $info['faq']=$faq>=5 ? $faq.' Antworten hinterlegt' : ($faq>0 ? 'Noch '.(5-$faq).' bis 5 – '.$faq.' vorhanden' : null);
    $done['ton']=(bool)array_filter($c,fn($x)=>$x['tone']!=='' && $x['tone']!=='freundlich-professionell') || assistant_mem_has($mem,'/^(ton|tonalität|anrede|sprache)/iu');
    $done['kanaele']=(bool)array_filter($c,fn($x)=>$x['widget_on'] || $x['phone_on']) || !empty($m['order']['whatsapp']);
    $done['aktiv']=count($ov['plans'])>0; if($ov['plans']) $info['aktiv']=$ov['plans'][0]['name'];
    $done['test']=$ov['tested']>0;
    $items=[]; foreach(ASSISTANT_CHECK as $k=>[$label,$hint,$prompt]) $items[]=['key'=>$k,'label'=>$label,'done'=>$done[$k],'hint'=>$done[$k] ? ($info[$k] ?? 'Erledigt') : ($info[$k] ?? $hint),'prompt'=>$prompt];
    $n=count(array_filter($items,fn($i)=>$i['done']));
    return [$items,(int)round($n/count($items)*100)];
}
/** 3–4 Vorschläge für die nächsten Schritte (anklickbare Chips). */
function assistant_suggestions(array $items, array $mem): array {
    $o=[]; foreach($items as $i){ if(!$i['done']) $o[]=$i['prompt']; if(count($o)>=3) break; }
    if(!$o) $o=['Was kann ich an meinem Agenten noch verbessern?','Fasse den Stand meiner Agenten zusammen.','Schreib mir drei neue FAQ-Antworten.'];
    $o[]=$mem ? 'Was weißt du schon über meinen Betrieb?' : 'Was kannst du alles für mich erledigen?';
    return array_slice($o,0,4);
}

/* ======================================================================= Systemprompt */
function assistant_system(int $acc, array $pr, array $ov, array $items, int $progress, array $mem, bool $voice): string {
    $f=$pr['gender']!=='m'; $n=$pr['name']; $sie=$pr['personality']['address']==='sie'; $pe=$pr['personality'];
    $s="Du bist {$n}, ".($f ? 'die persönliche Assistentin' : 'der persönliche Assistent')." des Inhabers bzw. der Inhaberin dieses Kontos im ChatHelp Studio. ChatHelp vermietet KI-Agenten für kleine und mittlere Betriebe (Website-Chat, Telefon, WhatsApp, Termine, Bestellungen, Taxi, Hotel, Rückrufe). Du bist kein Kundenservice für Endkunden, sondern die rechte Hand des Inhabers: Du hörst zu, lernst seinen Betrieb kennen und richtest seine Agenten mit ihm ein.";
    $s.="\n\nPERSONA\n• Du bist ".($f ? 'eine Frau. Sprich über dich in weiblichen Formen („ich bin deine Assistentin“, „deine Ansprechpartnerin“).' : 'ein Mann. Sprich über dich in männlichen Formen („ich bin dein Assistent“, „dein Ansprechpartner“).');
    $s.="\n• Anrede: ".($sie ? 'Du siezt den Inhaber konsequent („Sie“, „Ihr“).' : 'Du duzt den Inhaber konsequent („du“, „dein“).');
    $s.="\n• Art: ".['herzlich'=>'herzlich, warm und ermutigend – du freust dich ehrlich über Fortschritte.','professionell'=>'professionell, ruhig und präzise – seriös wie eine erfahrene Büroleitung.','locker'=>'locker, entspannt und nahbar – wie eine gute Kollegin bzw. ein guter Kollege.'][$pe['mood']];
    $s.="\n• Humor: ".[0=>'kein Humor, freundlich-sachlich.',1=>'ab und zu ein leichtes Augenzwinkern, nie albern.',2=>'gern humorvoll und schlagfertig, aber nie auf Kosten anderer und nie bei ernsten Themen.'][(int)$pe['humor']];
    $s.="\n• Antwortlänge: ".($pe['detail']==='kurz' ? 'kurz – meist 1 bis 3 Sätze.' : 'ausführlicher – bis 6 Sätze, mit kurzer Erklärung, wo es hilft.');
    $s.="\n• Ehrlichkeit: Fragt dich jemand, ob du ein Mensch bist, sagst du freundlich, dass du eine KI-".($f ? 'Assistentin' : 'Assistent')." bist. Behaupte nie, ein Mensch zu sein oder Dinge außerhalb des Studios getan zu haben.";
    $s.="\n• Sprache: ".($pr['lang']==='de' ? 'Deutsch' : strtoupper($pr['lang'])).", außer der Inhaber schreibt erkennbar in einer anderen Sprache – dann antwortest du in seiner Sprache.";
    $s.=$voice ? "\n• SPRACHAUSGABE IST AN: Deine Antwort wird mit deiner Stimme vorgelesen. Sprich natürlich wie im echten Gespräch: kurze Sätze, Alltagswörter, keine Listen, kein Markdown, keine Emojis, keine Klammern, keine URLs (Links stehen ohnehin im Chat). Zahlen und Uhrzeiten so, wie man sie spricht."
               : "\n• Schreibstil: natürlich und persönlich. Kurze Aufzählungen mit „•“ sind erlaubt, wenn du etwas zusammenfasst; sonst Fließtext. Kein Markdown außer **fett** für einzelne Wörter.";
    $s.="\n\nARBEITSWEISE";
    $s.="\n1. Zuhören: Reagiere zuerst kurz und konkret auf das, was der Inhaber gesagt hat.";
    $s.="\n2. Interview: Frag aktiv nach dem, was du noch nicht weißt – immer nur EINE konkrete, freundliche Frage pro Antwort. Orientiere dich an der Checkliste: Art des Betriebs, Angebot und Preise, Öffnungszeiten, typische Kundenfragen (mit Antworten), Ton und Anrede, Kanäle (Telefon, WhatsApp, Website). Frag nie nach etwas, das schon im Gedächtnis oder im Konto-Überblick steht – bestätige es höchstens kurz. Erzählt der Inhaber mehrere Dinge auf einmal, nimm alles auf und frag dann nach dem wichtigsten fehlenden Punkt.";
    $s.="\n3. Merken: Jede neue, dauerhafte Information über Betrieb, Wünsche oder Vorlieben des Inhabers sofort mit remember speichern (kurzes Thema wie „Branche“, „Öffnungszeiten“, „Zielgruppe“ + Fakt). Ändert sich etwas, mit replace=true ersetzen. Nichts doppelt merken.";
    $s.="\n4. Einrichten: BEVOR du Einstellungen änderst (save_business_profile, update_agent_knowledge, add_faq, set_agent_tone, configure_appointments, configure_menu, configure_taxi, configure_hotel, configure_callbacks), fasse in einem Satz zusammen, was du eintragen willst, und frag: „Soll ich das so übernehmen?“. Erst nach einem klaren Ja (ja, passt, mach, übernehmen …) das Werkzeug aufrufen. Ohne Rückfrage erlaubt: remember, get_overview, recommend_agent, open_studio_tab.";
    $s.="\n5. Danach: kurz bestätigen, was geändert wurde, und den nächsten offenen Punkt der Checkliste ansprechen.";
    $s.="\n6. Fakten: Erfinde nie Preise, Zeiten, Adressen oder Leistungen – nur, was der Inhaber gesagt hat. Fehlt etwas, frag nach.";
    $s.="\n7. Grenzen: Du kannst keine Zahlungen auslösen, keine Pläne kaufen und keine Telefonnummern schalten. Für einen Agenten empfiehlst du mit recommend_agent den passenden Plan (Preis + Link im Chat, nie vorlesen). Eine eigene Rufnummer richtet support@chat-help.com ein. Keine Rechts- oder Steuerberatung.";
    $s.="\n8. Tabs: Will der Inhaber etwas selbst sehen oder bearbeiten, öffne mit open_studio_tab den passenden Bereich (".implode(', ',array_keys(array_filter(ASSISTANT_TABS,fn($l,$k)=>assistant_has_tab($k),ARRAY_FILTER_USE_BOTH))).').';
    $s.="\n\nKONTO-ÜBERBLICK (live)\n".assistant_overview_text($ov);
    $s.="\n\nWAS DU SCHON WEISST (Gedächtnis)\n".($mem ? '- '.implode("\n- ",array_map(fn($m)=>$m['topic'].': '.$m['fact'],array_slice($mem,0,80))) : '- noch nichts – lerne den Betrieb kennen.');
    $s.="\n\nCHECKLISTE (".$progress." % erledigt)";
    foreach($items as $i) $s.="\n[".($i['done'] ? 'x' : ' ').'] '.$i['label'].($i['done'] ? '' : ' – '.$i['hint']);
    $next=null; foreach($items as $i) if(!$i['done']){ $next=$i; break; }
    $s.="\nNächster offener Punkt: ".($next ? $next['label'] : 'alles erledigt – hilf beim Feinschliff (mehr FAQ, bessere Texte, Testgespräch).');
    $s.="\n\nJetzt (Europe/Berlin): ".assistant_now_text().'.';
    return $s;
}

/* ======================================================================= Werkzeuge (nur Studio, nur Inhaber) */
function assistant_tools(): array {
    $plan=['type'=>'string','description'=>'Agent-ID, z. B. agent-termine, agent-bestellung, agent-phone. Leer = Haupt-Agent des Kontos.'];
    $hoursDesc='Öffnungszeiten je Wochentag: Objekt mit Schlüsseln "0" (Sonntag) bis "6" (Samstag), Wert = Liste von [von,bis] im Format HH:MM, z. B. {"1":[["09:00","18:00"]],"6":[["09:00","14:00"]],"0":[]}. Alle 7 Tage angeben; fehlende Tage gelten als geschlossen.';
    return [
        ['name'=>'get_overview','description'=>'Aktueller Stand des Kontos: Agenten, Wissensbasis, Module (Termine, Bestellungen, Taxi, Hotel, Rückrufe, Training), Guthaben und Checkliste. Nutzen, bevor du etwas behauptest, das sich geändert haben könnte.','input_schema'=>['type'=>'object']],
        ['name'=>'remember','description'=>'Einen dauerhaften Fakt über den Betrieb oder die Wünsche des Inhabers merken (z. B. Branche, Zielgruppe, Öffnungszeiten, Besonderheiten).','input_schema'=>['type'=>'object','properties'=>[
            'topic'=>['type'=>'string','description'=>'Kurzes Thema, max. 60 Zeichen, z. B. „Branche“'],'fact'=>['type'=>'string','description'=>'Der Fakt in einem Satz, max. 500 Zeichen'],
            'replace'=>['type'=>'boolean','description'=>'true = ersetzt ältere Fakten zum selben Thema']],'required'=>['topic','fact']]],
        ['name'=>'save_business_profile','description'=>'Betriebsprofil in den Agenten übernehmen (Firmenname + Abschnitt „Betriebsprofil“ in der Wissensbasis; der Rest der Wissensbasis bleibt). Nur nach Bestätigung.','input_schema'=>['type'=>'object','properties'=>[
            'company'=>['type'=>'string','description'=>'Name des Betriebs'],'address'=>['type'=>'string'],'phone'=>['type'=>'string'],'hours_text'=>['type'=>'string','description'=>'Öffnungszeiten als Text, z. B. „Mo–Fr 9–18 Uhr, Sa 9–14 Uhr“'],
            'description'=>['type'=>'string','description'=>'Was der Betrieb macht, Besonderheiten, Zielgruppe (2–5 Sätze)'],'plan'=>$plan],'required'=>['company']]],
        ['name'=>'update_agent_knowledge','description'=>'Einen Abschnitt der Wissensbasis schreiben (z. B. „Leistungen & Preise“, „Anfahrt & Parken“, „Regeln“). Nur nach Bestätigung.','input_schema'=>['type'=>'object','properties'=>[
            'plan'=>$plan,'section'=>['type'=>'string','description'=>'Überschrift des Abschnitts'],'text'=>['type'=>'string','description'=>'Inhalt, sachlich, mit allen Fakten'],
            'mode'=>['type'=>'string','enum'=>['append','replace_section'],'description'=>'append = anhängen, replace_section = Abschnitt ersetzen']],'required'=>['section','text']]],
        ['name'=>'add_faq','description'=>'Eine verbindliche FAQ-Antwort für den Agenten speichern (gleiche Frage wird aktualisiert). Mehrere auf einmal über items. Nur nach Bestätigung.','input_schema'=>['type'=>'object','properties'=>[
            'question'=>['type'=>'string'],'answer'=>['type'=>'string'],'plan'=>$plan,
            'items'=>['type'=>'array','description'=>'Weitere Fragen/Antworten (max. 10)','items'=>['type'=>'object','properties'=>['question'=>['type'=>'string'],'answer'=>['type'=>'string']],'required'=>['question','answer']]]],'required'=>['question','answer']]],
        ['name'=>'set_agent_tone','description'=>'Tonalität und Sprache eines Agenten festlegen. Nur nach Bestätigung.','input_schema'=>['type'=>'object','properties'=>[
            'plan'=>$plan,'tone'=>['type'=>'string','enum'=>ASSISTANT_AGENT_TONES],'lang'=>['type'=>'string','enum'=>ASSISTANT_LANGS]],'required'=>['tone']]],
        ['name'=>'configure_appointments','description'=>'Terminbuchung einrichten: Leistungen (Name, Dauer, Preis), Öffnungszeiten, Online-Buchung einschalten. Nur nach Bestätigung.','input_schema'=>['type'=>'object','properties'=>[
            'services'=>['type'=>'array','items'=>['type'=>'object','properties'=>['name'=>['type'=>'string'],'duration_min'=>['type'=>'integer'],'price_eur'=>['type'=>'number']],'required'=>['name']]],
            'mode'=>['type'=>'string','enum'=>['append','replace'],'description'=>'append = ergänzen/aktualisieren (Standard), replace = Liste ersetzen'],
            'hours'=>['type'=>'object','description'=>$hoursDesc],'enable'=>['type'=>'boolean','description'=>'Online-Terminbuchung einschalten']]]],
        ['name'=>'configure_menu','description'=>'Speisekarte für die Bestellannahme anlegen oder ergänzen. Optionen-Format: „Größe: Normal 0 | Groß +3,00; Extra: Käse +1,50“. Nur nach Bestätigung.','input_schema'=>['type'=>'object','properties'=>[
            'items'=>['type'=>'array','items'=>['type'=>'object','properties'=>['category'=>['type'=>'string'],'name'=>['type'=>'string'],'price_eur'=>['type'=>'number'],'description'=>['type'=>'string'],'options'=>['type'=>'string']],'required'=>['name','price_eur']]],
            'mode'=>['type'=>'string','enum'=>['append','replace']],'enable'=>['type'=>'boolean','description'=>'Bestellannahme einschalten']],'required'=>['items']]],
        ['name'=>'configure_taxi','description'=>'Grunddaten der Taxi-Zentrale (Name, Telefon, Adresse, Wartezeit, Einsatzgebiet) setzen und einschalten. Nur nach Bestätigung.','input_schema'=>['type'=>'object','properties'=>[
            'name'=>['type'=>'string'],'phone'=>['type'=>'string'],'address'=>['type'=>'string'],'eta_min'=>['type'=>'integer','description'=>'übliche Wartezeit in Minuten'],'area_km'=>['type'=>'integer','description'=>'Einsatzradius km'],'enable'=>['type'=>'boolean']]]],
        ['name'=>'configure_hotel','description'=>'Grunddaten der Hotel-Rezeption (Name, Adresse, Telefon, Check-in/-out, Hausregeln) setzen und einschalten. Nur nach Bestätigung.','input_schema'=>['type'=>'object','properties'=>[
            'name'=>['type'=>'string'],'address'=>['type'=>'string'],'phone'=>['type'=>'string'],'checkin_from'=>['type'=>'string','description'=>'HH:MM'],'checkout_until'=>['type'=>'string','description'=>'HH:MM'],'policies'=>['type'=>'string'],'enable'=>['type'=>'boolean']]]],
        ['name'=>'configure_callbacks','description'=>'Rückrufliste einstellen: ein/aus, Benachrichtigung, KI-Rückruf, Sofortrückruf für Leads, Anrufzeiten. Nur nach Bestätigung.','input_schema'=>['type'=>'object','properties'=>[
            'enable'=>['type'=>'boolean'],'notify_email'=>['type'=>'string'],'ai_callback'=>['type'=>'boolean'],'lead_instant'=>['type'=>'boolean'],'call_window'=>['type'=>'object','description'=>$hoursDesc]]]],
        ['name'=>'recommend_agent','description'=>'Passende Agenten (Plan, Preis, Mietlink) für eine Branche und die Wünsche des Inhabers ermitteln.','input_schema'=>['type'=>'object','properties'=>[
            'sector'=>['type'=>'string','description'=>'Branche, z. B. „Pizzeria“, „Friseursalon“, „Steuerkanzlei“'],'needs'=>['type'=>'string','description'=>'Wünsche, z. B. „Telefon annehmen, Termine buchen“']],'required'=>['sector']]],
        ['name'=>'open_studio_tab','description'=>'Einen Bereich des Studios für den Inhaber öffnen.','input_schema'=>['type'=>'object','properties'=>['tab'=>['type'=>'string','enum'=>array_keys(ASSISTANT_TABS)]],'required'=>['tab']]],
    ];
}
/** Öffnungszeiten aus der KI (Schlüssel 0–6 oder Tagesnamen, [von,bis] / {from,to} / "9-18") → {"0":[["HH:MM","HH:MM"]],…}. */
function assistant_hours_norm($raw): ?array {
    if(!is_array($raw)) return null;
    $names=['so'=>0,'sonntag'=>0,'sun'=>0,'sunday'=>0,'mo'=>1,'montag'=>1,'mon'=>1,'monday'=>1,'di'=>2,'dienstag'=>2,'tue'=>2,'tuesday'=>2,'mi'=>3,'mittwoch'=>3,'wed'=>3,'wednesday'=>3,
        'do'=>4,'donnerstag'=>4,'thu'=>4,'thursday'=>4,'fr'=>5,'freitag'=>5,'fri'=>5,'friday'=>5,'sa'=>6,'samstag'=>6,'sat'=>6,'saturday'=>6];
    $hm=function($t){ $t=trim(str_replace('.',':',(string)$t)); if(preg_match('/^(\d{1,2})(?::(\d{2}))?$/',$t,$m) && (int)$m[1]<=24 && (int)($m[2] ?? 0)<60) return sprintf('%02d:%02d',(int)$m[1],(int)($m[2] ?? 0)); return null; };
    $out=['0'=>[],'1'=>[],'2'=>[],'3'=>[],'4'=>[],'5'=>[],'6'=>[]]; $any=false;
    foreach($raw as $k=>$list){
        $kk=mb_strtolower(trim((string)$k)); $w=ctype_digit($kk) ? (int)$kk : ($names[rtrim($kk,'.')] ?? null); if($w===null || $w<0 || $w>6) continue; $any=true;
        if(is_string($list)) $list=preg_match('/geschlossen|closed|zu|^-$/iu',$list) ? [] : array_map(fn($x)=>preg_split('/\s*[–-]\s*/u',trim($x)),explode(',',$list));
        foreach((array)$list as $iv){ if(is_string($iv)) $iv=preg_split('/\s*[–-]\s*/u',trim($iv)); if(!is_array($iv)) continue;
            $a=$hm($iv['from'] ?? ($iv[0] ?? '')); $b=$hm($iv['to'] ?? ($iv[1] ?? '')); if($a===null || $b===null) continue; if($b==='24:00') $b='24:00'; $out[(string)$w][]=[$a,$b]; }
    }
    return $any ? $out : null;
}
function assistant_hours_text(array $h): string {
    $wd=['So','Mo','Di','Mi','Do','Fr','Sa']; $parts=[];
    foreach([1,2,3,4,5,6,0] as $w){ $iv=$h[(string)$w] ?? []; $parts[]=$wd[$w].' '.($iv ? implode(', ',array_map(fn($x)=>$x[0].'–'.$x[1],$iv)) : 'zu'); }
    return implode(' · ',$parts);
}
/** Ergebnis eines Werkzeugs als Aktions-Chip fürs Panel. */
function assistant_action(string $name, array $r): array {
    $ok=empty($r['error']);
    return ['name'=>$name,'ok'=>$ok,'summary'=>mb_substr((string)($ok ? ($r['summary'] ?? 'Erledigt.') : ($r['message'] ?? $r['hint'] ?? 'Das hat nicht geklappt.')),0,300),
        'tab'=>isset($r['tab']) && assistant_has_tab((string)$r['tab']) ? (string)$r['tab'] : null,'plan'=>$r['plan'] ?? null,'open'=>!empty($r['open'])];
}
/**
 * Werkzeug ausführen. Nur Kanal 'studio' (bzw. 'selftest'): der angemeldete Inhaber selbst. Fehler als ['error','hint'] (nie Exceptions für Nutzerfehler).
 */
function assistant_exec(int $acc, string $name, array $in, string $channel='studio', string $from=''): array {
    if(!in_array($channel,['studio','selftest'],true)) return ['error'=>'owner_only','hint'=>'Dieses Werkzeug steht nur dem Inhaber im Studio zur Verfügung.'];
    if($acc<=0) return ['error'=>'demo_readonly','hint'=>'In der Demo wird nichts gespeichert.'];
    assistant_bootstrap();
    try{
        switch($name){
            case 'get_overview':
                $ov=assistant_overview($acc); [$items,$pg]=assistant_checklist($acc,$ov);
                return ['overview'=>assistant_overview_text($ov),'checklist'=>array_map(fn($i)=>['key'=>$i['key'],'label'=>$i['label'],'done'=>$i['done'],'hint'=>$i['hint']],$items),'progress'=>$pg,'summary'=>'Überblick geladen'];
            case 'remember':
                $r=assistant_remember($acc,(string)($in['topic'] ?? ''),(string)($in['fact'] ?? ''),'chat',!empty($in['replace']));
                if(isset($r['error'])) return $r;
                return ['ok'=>true,'id'=>$r['id'],'new'=>$r['new'],'summary'=>'Gemerkt: '.assistant_line($in['topic'],60).' – '.assistant_line($in['fact'],160)];
            case 'save_business_profile': return assistant_tool_profile($acc,$in);
            case 'update_agent_knowledge': return assistant_tool_knowledge($acc,$in);
            case 'add_faq': return assistant_tool_faq($acc,$in);
            case 'set_agent_tone': return assistant_tool_tone($acc,$in);
            case 'configure_appointments': return assistant_tool_appt($acc,$in);
            case 'configure_menu': return assistant_tool_menu($acc,$in);
            case 'configure_taxi': return assistant_tool_taxi($acc,$in);
            case 'configure_hotel': return assistant_tool_hotel($acc,$in);
            case 'configure_callbacks': return assistant_tool_callbacks($acc,$in);
            case 'recommend_agent': return assistant_tool_recommend($acc,$in);
            case 'open_studio_tab':
                $t=assistant_str($in['tab'] ?? '',20);
                if(!isset(ASSISTANT_TABS[$t]) || !assistant_has_tab($t)) return ['error'=>'unknown_tab','hint'=>'Diesen Bereich gibt es nicht. Möglich: '.implode(', ',array_keys(array_filter(ASSISTANT_TABS,fn($l,$k)=>assistant_has_tab($k),ARRAY_FILTER_USE_BOTH)))];
                return ['ok'=>true,'open'=>true,'tab'=>$t,'summary'=>'Bereich „'.ASSISTANT_TABS[$t].'“ geöffnet'];
        }
    }catch(Throwable $e){ error_log('CH assistant tool '.$name.': '.$e->getMessage()); return ['error'=>'internal','hint'=>'Technischer Fehler beim Speichern – bitte dem Inhaber sagen, dass es gerade nicht geklappt hat.']; }
    return ['error'=>'unknown_tool','hint'=>'Dieses Werkzeug gibt es nicht.'];
}
function assistant_tool_profile(int $acc, array $in): array {
    [$plan,$e]=assistant_resolve_plan($acc,$in['plan'] ?? ''); if($e) return $e;
    $company=assistant_line($in['company'] ?? '',120); if($company==='') return ['error'=>'company_required','hint'=>'Bitte zuerst nach dem Namen des Betriebs fragen.'];
    $f=['Unternehmen'=>$company,'Adresse'=>assistant_line($in['address'] ?? '',200),'Telefon'=>assistant_line($in['phone'] ?? '',60),'Öffnungszeiten'=>assistant_line($in['hours_text'] ?? '',300),'Über uns'=>assistant_str($in['description'] ?? '',1500)];
    $text=implode("\n",array_map(fn($k,$v)=>$k.': '.$v,array_keys(array_filter($f,fn($v)=>$v!=='')),array_filter($f,fn($v)=>$v!=='')));
    $cur=th_agent_cfg($acc,$plan); $kb=assistant_kb_section_set((string)$cur['kb'],'Betriebsprofil',$text,'replace',true);
    if(mb_strlen($kb)>60000) return ['error'=>'kb_full','hint'=>'Die Wissensbasis ist voll (60.000 Zeichen). Bitte im Training kürzen.'];
    $d=['company'=>$company,'kb'=>$kb]; if($f['Öffnungszeiten']!=='' && trim((string)$cur['phone_hours'])==='') $d['phone_hours']=mb_substr($f['Öffnungszeiten'],0,160);
    assistant_cfg_save($acc,$plan,$d);
    $parts=array_keys(array_filter(['Firma'=>1,'Adresse'=>$f['Adresse'],'Telefon'=>$f['Telefon'],'Öffnungszeiten'=>$f['Öffnungszeiten'],'Beschreibung'=>$f['Über uns']]));
    $synced=assistant_sync_modules($acc,$company,$f['Adresse'],$f['Telefon']);
    assistant_remember($acc,'Betriebsprofil',$company.($f['Adresse']!=='' ? ', '.$f['Adresse'] : '').($f['Telefon']!=='' ? ', Tel. '.$f['Telefon'] : ''),'tool',true);
    return ['ok'=>true,'plan'=>$plan,'tab'=>'agents','synced'=>$synced,'summary'=>'Betriebsprofil „'.$company.'“ im '.assistant_plan_name($plan).' gespeichert ('.implode(', ',$parts).')'.($synced ? ' · auch in '.implode(', ',$synced).' eingetragen' : '')];
}
/**
 * Firmenname/Adresse/Telefon in eingeschaltete Fachmodule übernehmen – nur leere Felder, nie überschreiben.
 * Ausgeschaltete Module bleiben unberührt (kein ungewolltes Anlegen). Adressen nur dort, wo kein Geocoding nötig ist (Termine, Hotel).
 */
function assistant_sync_modules(int $acc, string $company, string $address, string $phone): array {
    $done=[];
    try{ $a=appt_settings($acc); if((int)$a['appt_on']===1){ $d=[]; if(trim((string)$a['company'])==='') $d['company']=$company; if($address!=='' && trim((string)$a['address'])==='') $d['address']=$address;
        if($d){ $e=null; appt_settings_save($acc,$d,$e); if(!$e) $done[]='Termine'; } } }catch(Throwable $e){}
    try{ $o=ord_settings($acc); if((int)$o['order_on']===1){ $d=[]; if(trim((string)$o['name'])==='') $d['name']=$company; if($phone!=='' && trim((string)$o['phone'])==='') $d['phone']=$phone;
        if($d){ ord_settings_save($acc,$d); $done[]='Bestellungen'; } } }catch(Throwable $e){}
    if(function_exists('ride_settings_save')) try{ $r=ride_settings($acc,true); if((int)$r['ride_on']===1){ $d=[]; if(trim((string)$r['name'])==='') $d['name']=$company; if($phone!=='' && trim((string)$r['phone'])==='') $d['phone']=$phone;
        if($d){ ride_settings_save($acc,$d); $done[]='Taxi'; } } }catch(Throwable $e){}
    if(function_exists('hotel_settings_save')) try{ $h=hotel_settings($acc); if((int)$h['hotel_on']===1){ $d=[]; if(trim((string)$h['name'])==='') $d['name']=$company; if($address!=='' && trim((string)$h['address'])==='') $d['address']=$address; if($phone!=='' && trim((string)$h['phone'])==='') $d['phone']=$phone;
        if($d){ hotel_settings_save($acc,$d); $done[]='Hotel'; } } }catch(Throwable $e){}
    return $done;
}
function assistant_tool_knowledge(int $acc, array $in): array {
    [$plan,$e]=assistant_resolve_plan($acc,$in['plan'] ?? ''); if($e) return $e;
    $sec=assistant_section_title(assistant_str($in['section'] ?? '')); $text=assistant_str($in['text'] ?? '',8000);
    if($sec==='' || $text==='') return ['error'=>'section_and_text_required','hint'=>'Bitte Überschrift und Inhalt angeben.'];
    $mode=($in['mode'] ?? 'append')==='replace_section' ? 'replace' : 'append';
    $cur=th_agent_cfg($acc,$plan); $kb=assistant_kb_section_set((string)$cur['kb'],$sec,$text,$mode,false);
    if(mb_strlen($kb)>60000) return ['error'=>'kb_full','hint'=>'Die Wissensbasis wäre zu groß (max. 60.000 Zeichen). Kürzer fassen oder einen Abschnitt ersetzen.'];
    assistant_cfg_save($acc,$plan,['kb'=>$kb]);
    return ['ok'=>true,'plan'=>$plan,'tab'=>assistant_has_tab('training') ? 'training' : 'agents','kb_chars'=>mb_strlen($kb),
        'summary'=>'Wissensbasis: Abschnitt „'.$sec.'“ '.($mode==='replace' ? 'ersetzt' : 'ergänzt').' ('.assistant_plan_name($plan).')'];
}
function assistant_tool_faq(int $acc, array $in): array {
    [$plan,$e]=assistant_resolve_plan($acc,$in['plan'] ?? ''); if($e) return $e;
    $list=[['question'=>$in['question'] ?? '','answer'=>$in['answer'] ?? '']];
    foreach(array_slice(is_array($in['items'] ?? null) ? $in['items'] : [],0,10) as $it) if(is_array($it)) $list[]=['question'=>$it['question'] ?? '','answer'=>$it['answer'] ?? ''];
    $new=[]; foreach($list as $it){ $q=assistant_line($it['question'],500); $a=assistant_str($it['answer'],2000); if($q!=='' && $a!=='') $new[]=['question'=>$q,'answer'=>$a]; }
    if(!$new) return ['error'=>'faq_required','hint'=>'Bitte Frage und Antwort angeben.'];
    $norm=function_exists('train_norm_q') ? fn($s)=>train_norm_q((string)$s) : fn($s)=>trim(preg_replace('/\s+/u',' ',preg_replace('/[^\p{L}\p{N}]+/u',' ',mb_strtolower((string)$s)) ?? '') ?? '');
    if(function_exists('train_faq_save') && function_exists('train_faq')){
        $items=array_map(fn($f)=>['id'=>$f['id'],'question'=>$f['question'],'answer'=>$f['answer'],'source'=>$f['source'],'active'=>$f['active']],train_faq($acc,$plan)); $upd=0; $add=0;
        foreach($new as $n){ $hit=false; foreach($items as &$it){ if($norm($it['question'])===$norm($n['question'])){ $it['answer']=$n['answer']; $it['active']=1; $hit=true; $upd++; break; } } unset($it);
            if(!$hit){ $items[]=['question'=>$n['question'],'answer'=>$n['answer'],'source'=>'manual','active'=>1]; $add++; } }
        $r=train_faq_save($acc,$plan,$items,[]);   // $known=[]: der Assistent löscht nie bestehende Einträge
        if(isset($r['error'])) return ['error'=>$r['error'],'hint'=>$r['message'] ?? 'FAQ konnte nicht gespeichert werden.'];
        $total=count(array_filter($r['items'] ?? [],fn($f)=>(int)$f['active']===1));
        return ['ok'=>true,'plan'=>$plan,'tab'=>assistant_has_tab('training') ? 'training' : 'agents','faq_total'=>$total,
            'summary'=>'FAQ: '.($add ? $add.' neu' : '').($add && $upd ? ', ' : '').($upd ? $upd.' aktualisiert' : '').' – „'.mb_substr($new[0]['question'],0,70).'“ ('.$total.' insgesamt)'];
    }
    $cur=th_agent_cfg($acc,$plan); $kb=(string)$cur['kb']; $sec=assistant_kb_section_get($kb,'Häufige Fragen') ?? '';
    $blocks=$sec==='' ? [] : preg_split('/\n\s*\n/u',$sec);
    foreach($new as $n){ $line="F: ".$n['question']."\nA: ".preg_replace('/\s*\n\s*/u',' ',$n['answer']); $hit=false;
        foreach($blocks as &$b){ if(preg_match('/^F:\s*(.+)$/mu',$b,$m) && $norm($m[1])===$norm($n['question'])){ $b=$line; $hit=true; break; } } unset($b);
        if(!$hit) $blocks[]=$line; }
    $kb=assistant_kb_section_set($kb,'Häufige Fragen',implode("\n\n",array_filter(array_map('trim',$blocks))),'replace');
    if(mb_strlen($kb)>60000) return ['error'=>'kb_full','hint'=>'Die Wissensbasis ist voll (60.000 Zeichen).'];
    assistant_cfg_save($acc,$plan,['kb'=>$kb]);
    $total=preg_match_all('/^F:\s*\S/mu',(string)assistant_kb_section_get($kb,'Häufige Fragen'));
    return ['ok'=>true,'plan'=>$plan,'tab'=>'agents','faq_total'=>$total,'summary'=>'FAQ gespeichert – „'.mb_substr($new[0]['question'],0,70).'“ ('.$total.' insgesamt)'];
}
function assistant_tool_tone(int $acc, array $in): array {
    [$plan,$e]=assistant_resolve_plan($acc,$in['plan'] ?? ''); if($e) return $e;
    $tone=assistant_str($in['tone'] ?? ''); if(!in_array($tone,ASSISTANT_AGENT_TONES,true)) return ['error'=>'tone_invalid','hint'=>'Erlaubt: '.implode(', ',ASSISTANT_AGENT_TONES)];
    $d=['tone'=>$tone]; $lang=assistant_str($in['lang'] ?? ''); if($lang!==''){ if(!in_array($lang,ASSISTANT_LANGS,true)) return ['error'=>'lang_invalid','hint'=>'Erlaubt: '.implode(', ',ASSISTANT_LANGS)]; $d['lang']=$lang; }
    assistant_cfg_save($acc,$plan,$d);
    $lname=['de'=>'Deutsch','en'=>'Englisch','tr'=>'Türkisch','fr'=>'Französisch','es'=>'Spanisch','it'=>'Italienisch','nl'=>'Niederländisch','pl'=>'Polnisch','ru'=>'Russisch'][$d['lang'] ?? th_agent_cfg($acc,$plan)['lang']] ?? 'Deutsch';
    assistant_remember($acc,'Ton & Sprache',assistant_plan_name($plan).': '.$tone.', '.$lname,'tool',true);
    return ['ok'=>true,'plan'=>$plan,'tab'=>'agents','summary'=>'Ton „'.$tone.'“, Sprache '.$lname.' für '.assistant_plan_name($plan)];
}
function assistant_tool_appt(int $acc, array $in): array {
    if(!function_exists('appt_settings_save')) return ['error'=>'module_unavailable','hint'=>'Die Terminbuchung ist hier nicht verfügbar.'];
    $svcIn=is_array($in['services'] ?? null) ? $in['services'] : []; $hours=isset($in['hours']) ? assistant_hours_norm($in['hours']) : null; $en=assistant_truthy($in['enable'] ?? null);
    if(isset($in['hours']) && $hours===null) return ['error'=>'hours_invalid','hint'=>'Öffnungszeiten bitte als {"1":[["09:00","18:00"]],…} (0 = Sonntag) angeben.'];
    if(!$svcIn && $hours===null && $en===null) return ['error'=>'nothing_to_do','hint'=>'Bitte Leistungen, Öffnungszeiten oder enable angeben.'];
    $sum=[];
    if($svcIn){
        $clean=[]; foreach(array_slice($svcIn,0,40) as $s){ if(!is_array($s)) continue; $n=assistant_line($s['name'] ?? '',120); if($n==='') continue;
            $dur=isset($s['duration_min']) && is_numeric($s['duration_min']) && (int)$s['duration_min']>0 ? max(5,min(600,(int)$s['duration_min'])) : null; $pc=assistant_cents($s['price_eur'] ?? ($s['price'] ?? null));
            $clean[mb_strtolower($n)]=['name'=>$n,'duration_min'=>$dur,'price_cents'=>$pc]; }
        if(!$clean) return ['error'=>'services_invalid','hint'=>'Jede Leistung braucht einen Namen.'];
        $replace=($in['mode'] ?? 'append')==='replace'; $list=[]; $seen=[];
        foreach(appt_services($acc,false) as $ex){ $k=mb_strtolower($ex['name']);
            if(isset($clean[$k])){ $c=$clean[$k]; $clean[$k]['duration_min']=$c['duration_min'] ?? $ex['duration_min']; $clean[$k]['price_cents']=$c['price_cents'] ?? $ex['price_cents'];
                $list[]=['id'=>$ex['id'],'name'=>$c['name'],'duration_min'=>$clean[$k]['duration_min'],'price_cents'=>$clean[$k]['price_cents'],'staff'=>$ex['staff'],'active'=>1]; $seen[$k]=1; }
            elseif(!$replace) $list[]=$ex; }
        foreach($clean as $k=>$c) if(!isset($seen[$k])){ $clean[$k]['duration_min']=$c['duration_min'] ?? 30; $list[]=['name'=>$c['name'],'duration_min'=>$clean[$k]['duration_min'],'price_cents'=>$c['price_cents'] ?? 0,'active'=>1]; }
        appt_services_save($acc,$list);
        $sum[]=count($clean).' Leistung'.(count($clean)===1 ? '' : 'en').' ('.implode(', ',array_map(fn($c)=>$c['name'].' '.$c['duration_min'].' Min'.($c['price_cents']!==null ? ' · '.assistant_eur($c['price_cents']) : ''),array_slice(array_values($clean),0,4))).(count($clean)>4 ? ' …' : '').')';
    }
    $d=[]; if($hours!==null) $d['hours']=$hours; if($en!==null) $d['appt_on']=$en ? 1 : 0;
    $cur=appt_settings($acc); if(trim((string)$cur['company'])===''){ $mc=th_agent_cfg($acc,assistant_main_plan($acc))['company'] ?? ''; if($mc!=='') $d['company']=$mc; }
    if($d){ $err=null; $st=appt_settings_save($acc,$d,$err); if($err) return ['error'=>$err['error'] ?? 'hours_invalid','hint'=>$err['message'] ?? 'Öffnungszeiten ungültig.'];
        if($hours!==null) $sum[]='Öffnungszeiten '.assistant_hours_text($st['hours']); if($en!==null) $sum[]='Online-Buchung '.($en ? 'eingeschaltet' : 'ausgeschaltet'); }
    return ['ok'=>true,'tab'=>'termine','services'=>count(appt_services($acc,true)),'summary'=>'Termine: '.implode(' · ',$sum)];
}
function assistant_tool_menu(int $acc, array $in): array {
    if(!function_exists('ord_menu_save')) return ['error'=>'module_unavailable','hint'=>'Die Bestellannahme ist hier nicht verfügbar.'];
    $itemsIn=is_array($in['items'] ?? null) ? $in['items'] : []; $clean=[];
    foreach(array_slice($itemsIn,0,120) as $it){ if(!is_array($it)) continue; $n=assistant_line($it['name'] ?? '',120); $pc=assistant_cents($it['price_eur'] ?? ($it['price'] ?? null)); if($n==='' || $pc===null) continue;
        $clean[mb_strtolower($n)]=['category'=>assistant_line($it['category'] ?? '',60),'name'=>$n,'description'=>assistant_line($it['description'] ?? '',300),'price_cents'=>$pc,'options'=>is_string($it['options'] ?? null) ? assistant_line($it['options'],600) : (is_array($it['options'] ?? null) ? $it['options'] : [])]; }
    if(!$clean) return ['error'=>'items_invalid','hint'=>'Jeder Artikel braucht Name und Preis in Euro.'];
    $replace=($in['mode'] ?? 'append')==='replace'; $list=[]; $seen=[];
    foreach(ord_menu($acc,false) as $ex){ $k=mb_strtolower($ex['name']);
        if(isset($clean[$k])){ $c=$clean[$k]; $list[]=['id'=>$ex['id'],'category'=>$c['category'] ?: $ex['category'],'name'=>$c['name'],'description'=>$c['description'] ?: $ex['description'],'price_cents'=>$c['price_cents'],'options'=>$c['options'] ?: $ex['options'],'tags'=>$ex['tags'],'active'=>1]; $seen[$k]=1; }
        elseif(!$replace) $list[]=$ex; }
    foreach($clean as $k=>$c) if(!isset($seen[$k])) $list[]=$c+['active'=>1];
    ord_menu_save($acc,$list); $sum=[count($clean).' Artikel '.($replace ? 'als neue Karte' : 'ergänzt').' ('.implode(', ',array_map(fn($c)=>$c['name'].' '.assistant_eur($c['price_cents']),array_slice(array_values($clean),0,4))).(count($clean)>4 ? ' …' : '').')'];
    $en=assistant_truthy($in['enable'] ?? null); $d=[];
    if($en!==null) $d['order_on']=$en ? 1 : 0;
    $cur=ord_settings($acc); if(trim((string)$cur['name'])===''){ $mc=th_agent_cfg($acc,assistant_main_plan($acc))['company'] ?? ''; if($mc!=='') $d['name']=$mc; }
    if($d){ ord_settings_save($acc,$d); if($en!==null) $sum[]='Bestellannahme '.($en ? 'eingeschaltet' : 'ausgeschaltet'); }
    $total=count(ord_menu($acc,true)); $sum[]=$total.' Artikel auf der Karte';
    return ['ok'=>true,'tab'=>'bestellungen','items'=>$total,'summary'=>'Speisekarte: '.implode(' · ',$sum)];
}
/** Nur übergebene Grundfelder übernehmen. */
function assistant_pick(array $in, array $keys, int $max=200): array { $o=[]; foreach($keys as $k) if(array_key_exists($k,$in) && is_scalar($in[$k]) && trim((string)$in[$k])!=='') $o[$k]=mb_substr(trim((string)$in[$k]),0,$max); return $o; }
function assistant_tool_taxi(int $acc, array $in): array {
    if(!function_exists('ride_settings_save')) return ['error'=>'module_unavailable','hint'=>'Das Taxi-Modul ist hier nicht verfügbar.'];
    $d=assistant_pick($in,['name','phone','address']); foreach(['eta_min','area_km'] as $k) if(isset($in[$k]) && is_numeric($in[$k])) $d[$k]=(int)$in[$k];
    $en=assistant_truthy($in['enable'] ?? null); if($en!==null) $d['ride_on']=$en ? 1 : 0;
    if(!$d) return ['error'=>'nothing_to_do','hint'=>'Bitte mindestens ein Feld angeben.'];
    $s=ride_settings_save($acc,$d);
    $sum=array_filter(['Name'=>isset($d['name']) ? $s['name'] : null,'Telefon'=>isset($d['phone']) ? $s['phone'] : null,'Adresse'=>isset($d['address']) ? $s['address'] : null,'Wartezeit'=>isset($d['eta_min']) ? $s['eta_min'].' Min' : null,'Gebiet'=>isset($d['area_km']) ? $s['area_km'].' km' : null]);
    return ['ok'=>true,'tab'=>'taxi','summary'=>'Taxi: '.implode(' · ',array_map(fn($k,$v)=>$k.' '.$v,array_keys($sum),$sum)).($en!==null ? ($sum ? ' · ' : '').'Fahrtannahme '.($en ? 'eingeschaltet' : 'ausgeschaltet') : '')];
}
function assistant_tool_hotel(int $acc, array $in): array {
    if(!function_exists('hotel_settings_save')) return ['error'=>'module_unavailable','hint'=>'Das Hotel-Modul ist hier nicht verfügbar.'];
    $d=assistant_pick($in,['name','address','phone','checkin_from','checkout_until']); if(isset($in['policies']) && is_string($in['policies']) && trim($in['policies'])!=='') $d['policies']=mb_substr(trim($in['policies']),0,4000);
    $en=assistant_truthy($in['enable'] ?? null); if($en!==null) $d['hotel_on']=$en ? 1 : 0;
    if(!$d) return ['error'=>'nothing_to_do','hint'=>'Bitte mindestens ein Feld angeben.'];
    $s=hotel_settings_save($acc,$d);
    $sum=array_filter(['Name'=>isset($d['name']) ? $s['name'] : null,'Adresse'=>isset($d['address']) ? $s['address'] : null,'Telefon'=>isset($d['phone']) ? $s['phone'] : null,'Check-in ab'=>isset($d['checkin_from']) ? $s['checkin_from'] : null,'Check-out bis'=>isset($d['checkout_until']) ? $s['checkout_until'] : null,'Hausregeln'=>isset($d['policies']) ? 'gespeichert' : null]);
    return ['ok'=>true,'tab'=>'hotel','summary'=>'Hotel: '.implode(' · ',array_map(fn($k,$v)=>$k.' '.$v,array_keys($sum),$sum)).($en!==null ? ($sum ? ' · ' : '').'Rezeption '.($en ? 'eingeschaltet' : 'ausgeschaltet') : '')];
}
function assistant_tool_callbacks(int $acc, array $in): array {
    if(!function_exists('cb_settings_save')) return ['error'=>'module_unavailable','hint'=>'Das Rückruf-Modul ist hier nicht verfügbar.'];
    $d=[]; $sum=[];
    foreach(['enable'=>'cb_on','ai_callback'=>'ai_callback','lead_instant'=>'lead_instant'] as $k=>$col){ $b=assistant_truthy($in[$k] ?? null); if($b!==null){ $d[$col]=$b ? 1 : 0; $sum[]=['cb_on'=>'Rückrufliste','ai_callback'=>'KI-Rückruf','lead_instant'=>'Sofortrückruf für Leads'][$col].' '.($b ? 'an' : 'aus'); } }
    if(isset($in['notify_email']) && is_string($in['notify_email']) && trim($in['notify_email'])!==''){ if(!filter_var(trim($in['notify_email']),FILTER_VALIDATE_EMAIL)) return ['error'=>'email_invalid','hint'=>'Bitte eine gültige E-Mail-Adresse nennen lassen.']; $d['notify_email']=trim($in['notify_email']); $sum[]='Benachrichtigung an '.$d['notify_email']; }
    if(isset($in['call_window'])){ $w=assistant_hours_norm($in['call_window']); if($w===null) return ['error'=>'hours_invalid','hint'=>'Anrufzeiten bitte als {"1":[["09:00","18:00"]],…} angeben.']; $d['call_window']=$w; $sum[]='Anrufzeiten '.assistant_hours_text($w); }
    if(!$d) return ['error'=>'nothing_to_do','hint'=>'Bitte mindestens eine Einstellung angeben.'];
    cb_settings_save($acc,$d);
    return ['ok'=>true,'tab'=>'rueckruf','summary'=>'Rückrufe: '.implode(' · ',$sum)];
}
function assistant_tool_recommend(int $acc, array $in): array {
    $sector=assistant_line($in['sector'] ?? '',120); $needs=assistant_line($in['needs'] ?? '',300);
    $ids=assistant_sector_plans($sector.' '.$needs);
    if(preg_match('/telefon|anruf|anrufe|erreichbar/iu',$needs) && !in_array('agent-phone',$ids,true)) $ids[]='agent-phone';
    if(preg_match('/whatsapp|website|webseite|chat|online/iu',$needs) && !$ids) $ids[]='agent-support';
    if(!$ids) $ids=['agent-phone','agent-support'];
    $buyOf=array_flip(CH_PLAN_BASE); $out=[];
    foreach(array_slice(array_values(array_unique($ids)),0,3) as $id){ $pl=ch_plan($id); if(!$pl) continue;
        $r=['id'=>$id,'name'=>$pl['name'],'price'=>assistant_eur($pl['cents']).'/Monat','tokens_month'=>$pl['tokens'],'desc'=>$pl['desc'],'active'=>ch_plan_active_any($acc,$id),
            'checkout_url'=>'https://chat-help.com/chat/tokens-checkout.php?plan='.rawurlencode($id)];
        if(isset($buyOf[$id]) && ($b=ch_plan($buyOf[$id]))) $r['lifetime']=['id'=>$b['id'],'price'=>assistant_eur($b['cents']).' einmalig','checkout_url'=>'https://chat-help.com/chat/tokens-checkout.php?plan='.rawurlencode($b['id'])];
        $out[]=$r; }
    return ['ok'=>true,'agents'=>$out,'tab'=>'agents','summary'=>'Empfehlung: '.implode(', ',array_map(fn($a)=>$a['name'].' ('.$a['price'].')',$out))];
}

/* ======================================================================= Gespräch */
/**
 * Eine Nachricht des Inhabers beantworten. Rückgabe [status, json]. Abrechnung nur, wenn die KI geantwortet hat:
 * 6 Tokens + 2 je Werkzeugaufruf (th_charge, höchstens Restguthaben).
 */
function assistant_chat(int $acc, string $message, bool $voice=false): array {
    assistant_bootstrap(); $message=trim(str_replace("\r",'',$message));
    if($message==='') return [400,['error'=>'message_required','message'=>'Bitte eine Nachricht eingeben.']];
    if(mb_strlen($message)>ASSISTANT_MSG_MAX) return [400,['error'=>'message_too_long','message'=>'Die Nachricht ist zu lang (höchstens 2.000 Zeichen).']];
    $bal=th_balance($acc);
    if($bal<ASSISTANT_COST_TURN) return [402,['error'=>'insufficient_tokens','need'=>ASSISTANT_COST_TURN,'balance'=>$bal,'buy'=>'/#preise','message'=>'Dein Guthaben reicht nicht für eine Antwort ('.ASSISTANT_COST_TURN.' Tokens). Bitte Tokens nachkaufen.']];
    if(!th_rate('as:chat:'.$acc,150,3600)) return [429,['error'=>'rate_limited','message'=>'Sehr viele Nachrichten in kurzer Zeit – bitte einen Moment warten.']];
    if(!th_lock('as:'.$acc,8)) return [429,['error'=>'busy','message'=>'Ich beantworte gerade noch deine letzte Nachricht – einen Moment bitte.']];
    try{
        $t0=microtime(true); $pr=assistant_profile($acc);
        assistant_ensure_greeting($acc,$pr);
        $uid=assistant_add_message($acc,'user',$message);
        $ov=assistant_overview($acc); $mem=assistant_memory($acc,120); [$items,$pg]=assistant_checklist($acc,$ov,$mem);
        $sys=assistant_system($acc,$pr,$ov,$items,$pg,$mem,$voice);
        $msgs=assistant_ai_messages($acc); $tools=assistant_tools(); $actions=[]; $calls=0;
        $exec=function(string $n, array $i) use($acc,&$actions,&$calls): array {
            if(++$calls>12) return ['error'=>'too_many_tools','hint'=>'Genug Werkzeuge für diese Antwort – fasse zusammen und frag nach.'];
            foreach(array_keys($i) as $k) if($k==='force' || $k==='account_id' || str_starts_with((string)$k,'_')) unset($i[$k]);
            $r=assistant_exec($acc,$n,$i,'studio');
            if($n!=='get_overview') $actions[]=assistant_action($n,$r);
            return $r;
        };
        $hook=in_array(PHP_SAPI,['cli','cli-server'],true) && function_exists('assistant_test_ai');   // nur lokale Tests (Testskript definiert den Haken)
        $reply=$hook ? assistant_test_ai($sys,$msgs,$tools,$exec) : th_ai_tools($sys,$msgs,$tools,$exec,$voice ? 500 : 900,60,6);
        $reply=is_string($reply) ? trim($reply) : '';
        if($reply===''){
            if(!$actions) th_db()->prepare("DELETE FROM assistant_messages WHERE id=? AND account_id=?")->execute([$uid,$acc]);
            else assistant_add_message($acc,'assistant',($pr['personality']['address']==='sie' ? 'Ich habe Folgendes erledigt, aber meine Antwort ist unterwegs verloren gegangen: ' : 'Ich habe Folgendes erledigt, aber meine Antwort ist unterwegs verloren gegangen: ').implode('; ',array_map(fn($a)=>$a['summary'],$actions)).'.',['actions'=>$actions]);
            return [503,['error'=>'ai_unavailable','message'=>'Ich bin gerade nicht erreichbar – bitte versuch es in einem Moment noch einmal. Es wurden keine Tokens berechnet.','actions'=>$actions]];
        }
        assistant_add_message($acc,'assistant',$reply,$actions ? ['actions'=>$actions] : []);
        $cost=ASSISTANT_COST_TURN+ASSISTANT_COST_TOOL*$calls;
        $charged=th_charge($acc,$cost,'assistant:chat'); th_log_usage($acc,'assistant','assistant',$charged,(int)((microtime(true)-$t0)*1000));
        $ov=assistant_overview($acc); $mem=assistant_memory($acc,100); [$items,$pg]=assistant_checklist($acc,$ov,$mem);
        return [200,['reply'=>$reply,'speech'=>assistant_speech($reply,$pr['lang'],$pr['personality']['address']!=='sie'),'actions'=>$actions,'checklist'=>$items,'progress'=>$pg,
            'memory'=>array_map(fn($m)=>['id'=>$m['id'],'topic'=>$m['topic'],'fact'=>$m['fact']],$mem),'suggestions'=>assistant_suggestions($items,$mem),'tokens_used'=>$charged,'balance'=>th_balance($acc)]];
    } finally { th_unlock('as:'.$acc); }
}
/** Zustand fürs Panel: Verlauf, Gedächtnis, Checkliste, Vorschläge, Guthaben. Legt beim ersten Besuch die Begrüßung an. */
function assistant_state(int $acc): array {
    $pr=assistant_profile($acc); assistant_ensure_greeting($acc,$pr);
    $ov=assistant_overview($acc); $mem=assistant_memory($acc,100); [$items,$pg]=assistant_checklist($acc,$ov,$mem);
    return ['history'=>assistant_history($acc,30),'memory'=>array_map(fn($m)=>['id'=>$m['id'],'topic'=>$m['topic'],'fact'=>$m['fact']],$mem),'checklist'=>$items,'progress'=>$pg,
        'suggestions'=>assistant_suggestions($items,$mem),'balance'=>$ov['balance'],'cost'=>['turn'=>ASSISTANT_COST_TURN,'tool'=>ASSISTANT_COST_TOOL]];
}
/** MP3 direkt ausliefern (nur Web); in der Kommandozeile (Tests) als JSON-Beschreibung zurück. */
function assistant_emit_audio(string $mp3): array {
    if(PHP_SAPI==='cli') return [200,['audio'=>true,'content_type'=>'audio/mpeg','bytes'=>strlen($mp3)]];
    th_nocache(); http_response_code(200); header('Content-Type: audio/mpeg'); header('Content-Length: '.strlen($mp3)); echo $mp3; exit;
}

/* ======================================================================= Studio-REST */
function assistant_api(string $route, string $method, int $aid, array $body, array $account): ?array {
    if($route!=='assistant' && !str_starts_with($route,'assistant/')) return null;
    if($aid<=0) return [403,['error'=>'forbidden']];
    assistant_bootstrap();
    $pub=fn()=>['profile'=>assistant_profile_public(assistant_profile($aid)),'portraits'=>array_map(fn($p)=>['id'=>$p['id'],'url'=>$p['url'],'gender'=>$p['gender'],'label'=>$p['label']],assistant_portraits()),
        'voices'=>assistant_voice_presets_public(),'tts'=>assistant_tts_ready(),'photo_max'=>ASSISTANT_PHOTO_MAX];
    switch(true){
        case $route==='assistant/profile' && $method==='GET': return [200,$pub()];
        case $route==='assistant/profile' && $method==='POST':
            $err=null; assistant_profile_save($aid,$body,$err); if($err) return [422,$err];
            return [200,['ok'=>true]+$pub()];
        case $route==='assistant/state' && $method==='GET': return [200,assistant_state($aid)];
        case $route==='assistant/chat' && $method==='POST':
            return assistant_chat($aid,assistant_str($body['message'] ?? ''),!empty($body['voice']));
        case $route==='assistant/speak' && $method==='POST':
            $text=assistant_str($body['text'] ?? '');
            if($text==='') return [400,['error'=>'text_required','message'=>'Kein Text zum Vorlesen.']];
            if(mb_strlen($text)>ASSISTANT_SPEAK_MAX) return [400,['error'=>'text_too_long','message'=>'Höchstens '.ASSISTANT_SPEAK_MAX.' Zeichen je Satz.']];
            if(!assistant_tts_ready()) return [501,['error'=>'tts_unavailable','message'=>'Server-Stimme nicht eingerichtet – das Studio nutzt die Stimme deines Browsers.']];
            if(!th_rate('as:tts:'.$aid,300,86400)) return [429,['error'=>'rate_limited','message'=>'Tageslimit für die Sprachausgabe erreicht – ab jetzt spricht die Browserstimme.']];
            $pr=assistant_profile($aid); $mp3=null;
            try{ $mp3=ch_tts_synth($text,assistant_voice($pr,is_array($body['voice'] ?? null) ? $body['voice'] : [])); }catch(Throwable $e){ error_log('CH assistant tts: '.$e->getMessage()); }
            if(!is_string($mp3) || $mp3==='') return [502,['error'=>'tts_failed','message'=>'Die Stimme konnte gerade nicht erzeugt werden.']];
            return assistant_emit_audio($mp3);
        case $route==='assistant/reset' && $method==='POST':
            th_db()->prepare("DELETE FROM assistant_messages WHERE account_id=?")->execute([$aid]);
            return [200,['ok'=>true]+assistant_state($aid)];
        case $route==='assistant/forget' && $method==='POST':
            $ok=assistant_forget($aid,(int)($body['id'] ?? 0));
            if(!$ok) return [404,['error'=>'not_found','message'=>'Diesen Eintrag gibt es nicht (mehr).']];
            $ov=assistant_overview($aid); $mem=assistant_memory($aid,100); [$items,$pg]=assistant_checklist($aid,$ov,$mem);
            return [200,['ok'=>true,'memory'=>array_map(fn($m)=>['id'=>$m['id'],'topic'=>$m['topic'],'fact'=>$m['fact']],$mem),'checklist'=>$items,'progress'=>$pg,'suggestions'=>assistant_suggestions($items,$mem)]];
    }
    return [404,['error'=>'not_found','routes'=>['GET|POST /v1/assistant/profile','GET /v1/assistant/state','POST /v1/assistant/chat','POST /v1/assistant/speak','POST /v1/assistant/reset','POST /v1/assistant/forget']]];
}
