<?php
/**
 * ChatHelp API v1 — TEK giriş noktası, tüm sektörler
 * ==================================================
 *   POST /chat/api.php/v1/generate   {task, sector, input, lang}   → metin üretimi (post/script/widerspruch/...)
 *   POST /chat/api.php/v1/translate  {text, target[], lang}        → çeviri
 *   POST /chat/api.php/v1/subtitles  {transcript, target[], ...}   → zaman damgalı SRT (transkript metninden)
 *   POST /chat/api.php/v1/agent      {sector, goal, context}       → sektör ajanı tek tur
 *   GET  /chat/api.php/v1/balance                                   → bakiye + son kullanım
 *   GET  /chat/api.php/v1/sectors                                   → desteklenen sektör/task + token fiyatları
 * Auth: Authorization: Bearer ch_live_…   Fiyat: sabit task tarifesi (şeffaf, aşağıda).
 * Sektör eklemek = SECTORS'a bir satır. Model: CLAUDE_KEY varsa Claude, yoksa DeepSeek.
 * api.chat-help.com → bu dosyaya rewrite (veya /chat/api.php/v1/... doğrudan).
 */
require_once __DIR__.'/tokens-lib.php';
header('Access-Control-Allow-Origin: *'); header('Access-Control-Allow-Headers: Authorization, Content-Type'); header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){ http_response_code(204); exit; }
th_bootstrap();

/* ---- TARİFE (token / işlem) — siteyle aynı Richtwerte ---- */
const PRICING = [
  'post'=>5, 'caption'=>3, 'hashtags'=>2, 'script'=>50, 'title'=>5, 'seo'=>8,
  'translate_100w'=>2, 'subtitle_min'=>2,
  'widerspruch'=>80, 'brief'=>40, 'formular'=>30,
  'agent_run'=>10, 'generic'=>10,
];
/* ---- SEKTÖRLER = yapılandırma (yeni sektör = yeni satır) ---- */
const SECTORS = [
  'recht'      =>['name'=>'Recht & Verwaltung',   'sys'=>'Du bist ein präziser deutscher Rechtsassistent. Nenne Rechtsgrundlagen (§§), Fristen und Form. Keine Rechtsberatung im Einzelfall; verweise bei Unsicherheit auf Anwalt/Mieterverein. Antworte strukturiert und versandfertig.'],
  'ecommerce'  =>['name'=>'E-Commerce & Handel',  'sys'=>'Du bist ein E-Commerce-Texter und Support-Experte. Schreibe konversionsstarke, ehrliche Produkttexte und freundliche, lösungsorientierte Kundenantworten (Retouren, Lieferung, Gewährleistung nach EU-Recht).'],
  'immobilien' =>['name'=>'Immobilien',           'sys'=>'Du bist ein Immobilien-Assistent (DE/AT/CH). Erstelle Exposés, beantworte Anfragen, erkläre Mietvertragsklauseln verständlich. Nenne relevante §§ (BGB) wo nötig.'],
  'praxis'     =>['name'=>'Praxis & Gesundheit',  'sys'=>'Du bist ein Verwaltungsassistent für Arztpraxen. NUR Organisation/Kommunikation (Termine, Erinnerungen, allgemeine Praxisinfos). Keine medizinischen Diagnosen oder Therapieempfehlungen.'],
  'hr'         =>['name'=>'HR & Recruiting',      'sys'=>'Du bist ein HR-Assistent. Schreibe AGG-konforme Stellenanzeigen, strukturierte Screening-Fragen und Onboarding-Pläne. Diskriminierungsfrei, klar, professionell.'],
  'finanzen'   =>['name'=>'Finanzen & Steuern',   'sys'=>'Du bist ein Assistent für Buchhaltung/Steuern (DE). Erkläre Belege, Fristen, Mahnwesen und Formulare verständlich. Keine verbindliche Steuerberatung; verweise auf Steuerberater bei Einzelfällen.'],
  'gastro'     =>['name'=>'Gastronomie & Hotel',  'sys'=>'Du bist ein Gastro-/Hotel-Assistent. Speisekartentexte, Bewertungsantworten (höflich, deeskalierend), Buchungskommunikation, mehrsprachig.'],
  'handwerk'   =>['name'=>'Handwerk & Service',   'sys'=>'Du bist ein Assistent für Handwerks- und Dienstleistungsbetriebe. Angebote, Rechnungstexte, Terminbestätigungen, Reklamationsantworten – klar und kundenfreundlich.'],
  'bildung'    =>['name'=>'Bildung & Coaching',   'sys'=>'Du bist ein didaktischer Assistent. Kursinhalte, Lernziele, Übungen, Zusammenfassungen; verständlich, strukturiert, zielgruppengerecht.'],
  'marketing'  =>['name'=>'Marketing & Agenturen','sys'=>'Du bist ein Senior Content- und SEO-Stratege. Erstelle Posts, Captions, Hashtag-Strategien, SEO-Titel/Beschreibungen und Kampagnenideen – lizenzfrei, in der gewünschten Tonalität.'],
  'logistik'   =>['name'=>'Logistik & Industrie', 'sys'=>'Du bist ein Assistent für Logistik/Industrie. Prozessdokumentation, Übersetzungen, Lieferkommunikation, Support-Antworten – präzise und knapp.'],
  'medien'     =>['name'=>'Content & Medien',     'sys'=>'Du bist ein YouTube-/Video-Skript-Autor und Untertitel-Experte. Hook, Struktur, Call-to-Action; Titelvarianten, Beschreibungen, Kapitel; saubere Untertitelzeilen.'],
];

/* ---- Yardımcılar ---- */
function body(): array { $j=json_decode(file_get_contents('php://input'),true); return is_array($j)?$j:[]; }
function route(): string { $u=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH); $i=strpos($u,'/v1/'); return $i===false?'':substr($u,$i+4); }
function ai_complete(string $system, string $user, int $maxTokens=1500): string {
    if(defined('CLAUDE_KEY') && CLAUDE_KEY){
        $model = defined('CLAUDE_MODEL') ? CLAUDE_MODEL : 'claude-sonnet-4-5';
        $ch=curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>90,
            CURLOPT_POSTFIELDS=>json_encode(['model'=>$model,'max_tokens'=>$maxTokens,'system'=>$system,'messages'=>[['role'=>'user','content'=>$user]]]),
            CURLOPT_HTTPHEADER=>['x-api-key: '.CLAUDE_KEY,'anthropic-version: 2023-06-01','content-type: application/json']]);
        $r=json_decode(curl_exec($ch),true); curl_close($ch);
        if(!empty($r['content'][0]['text'])) return $r['content'][0]['text'];
        if(!empty($r['error'])) error_log('CH api claude: '.json_encode($r['error']));
    }
    if(defined('DEEPSEEK_KEY') && DEEPSEEK_KEY){
        $ch=curl_init('https://api.deepseek.com/chat/completions');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>90,
            CURLOPT_POSTFIELDS=>json_encode(['model'=>'deepseek-chat','max_tokens'=>$maxTokens,'messages'=>[['role'=>'system','content'=>$system],['role'=>'user','content'=>$user]]]),
            CURLOPT_HTTPHEADER=>['Authorization: Bearer '.DEEPSEEK_KEY,'content-type: application/json']]);
        $r=json_decode(curl_exec($ch),true); curl_close($ch);
        if(!empty($r['choices'][0]['message']['content'])) return $r['choices'][0]['message']['content'];
    }
    th_json(502,['error'=>'ai_unavailable','message'=>'KI-Backend nicht erreichbar']);
}
function sys_for(string $sector, string $lang): string {
    $s = SECTORS[$sector]['sys'] ?? 'Du bist ein präziser, hilfreicher Business-Assistent.';
    return $s." Antworte in der Sprache: ".strtoupper($lang).". Gib nur das Ergebnis, ohne Vorrede.";
}
/** Ücretli işlem sarmalayıcı: bakiye → iş → düş → logla. */
function charged(array $acc, int $cost, string $task, string $sector, callable $work): void {
    $t0=microtime(true);
    if(th_balance((int)$acc['id']) < $cost) th_json(402,['error'=>'insufficient_tokens','need'=>$cost,'balance'=>th_balance((int)$acc['id']),'buy'=>'https://chat-help.com/#tokens']);
    $result=$work();
    th_debit((int)$acc['id'],$cost,'api:'.$task);
    th_log_usage((int)$acc['id'],$task,$sector,$cost,(int)((microtime(true)-$t0)*1000));
    th_json(200,['result'=>$result,'tokens_used'=>$cost,'balance'=>th_balance((int)$acc['id'])]);
}

/* ---- Router ---- */
$r = rtrim(route(),'/'); $m=$_SERVER['REQUEST_METHOD'];
if($r==='sectors' && $m==='GET'){
    th_json(200,['sectors'=>array_map(fn($k,$v)=>['id'=>$k,'name'=>$v['name']],array_keys(SECTORS),SECTORS),'pricing_tokens'=>PRICING,'tasks'=>['generate','translate','subtitles','agent']]);
}
$hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
$acc = th_auth(preg_replace('/^Bearer\s+/i','',trim($hdr)));
if(!$acc) th_json(401,['error'=>'unauthorized','message'=>'Authorization: Bearer ch_live_… fehlt oder ungültig','get_key'=>'https://chat-help.com/#tokens']);
$b=body(); $lang=substr(preg_replace('/[^a-z]/','',strtolower($b['lang']??'de')),0,2) ?: 'de';
$sector=preg_replace('/[^a-z]/','',strtolower($b['sector']??'marketing')); if(!isset(SECTORS[$sector])) $sector='marketing';

switch(true){
  case $r==='balance' && $m==='GET':
    $u=th_db()->prepare("SELECT task,sector,tokens,created_at FROM api_usage WHERE account_id=? ORDER BY id DESC LIMIT 20"); $u->execute([$acc['id']]);
    th_json(200,['email'=>$acc['email'],'balance'=>th_balance((int)$acc['id']),'recent'=>$u->fetchAll(PDO::FETCH_ASSOC)]);

  case $r==='generate' && $m==='POST':
    $task=preg_replace('/[^a-z_]/','',strtolower($b['task']??'generic')); $input=trim((string)($b['input']??''));
    if($input==='') th_json(400,['error'=>'input_required']);
    $cost=PRICING[$task] ?? PRICING['generic'];
    charged($acc,$cost,$task,$sector,fn()=>ai_complete(sys_for($sector,$lang),"Aufgabe: $task\n\n$input",1800));

  case $r==='translate' && $m==='POST':
    $text=trim((string)($b['text']??'')); $targets=array_values(array_filter(array_map('strtolower',(array)($b['target']??['en']))));
    if($text===''||!$targets) th_json(400,['error'=>'text_and_target_required']);
    $words=max(1,str_word_count($text)); $cost=(int)ceil($words/100)*PRICING['translate_100w']*count($targets);
    charged($acc,$cost,'translate',$sector,function()use($text,$targets){
        $out=[]; foreach($targets as $t){ $out[$t]=ai_complete("Du bist ein professioneller Übersetzer. Übersetze exakt, idiomatisch, ohne Zusätze.","Zielsprache: ".strtoupper($t)."\n\n$text",2000); } return $out; });

  case $r==='subtitles' && $m==='POST':
    $tr=trim((string)($b['transcript']??'')); $targets=array_values(array_filter(array_map('strtolower',(array)($b['target']??['de'])))); $mins=max(1,(int)($b['minutes']??ceil(str_word_count($tr)/130)));
    if($tr==='') th_json(400,['error'=>'transcript_required','hint'=>'Video-Upload/Transkription folgt; aktuell Transkript-Text senden.']);
    $cost=$mins*PRICING['subtitle_min']*count($targets);
    charged($acc,$cost,'subtitles',$sector,function()use($tr,$targets,$mins){
        $out=[]; foreach($targets as $t){ $out[$t]=ai_complete("Du erzeugst SRT-Untertitel. Teile den Text in Segmente von max. 2 Zeilen/42 Zeichen, verteile Zeitstempel gleichmäßig über ".$mins." Minuten, Format 00:00:00,000 --> 00:00:00,000. Gib NUR gültiges SRT aus.","Zielsprache: ".strtoupper($t)."\n\nTranskript:\n$tr",3000); } return ['format'=>'srt','files'=>$out]; });

  case $r==='agent' && $m==='POST':
    $goal=trim((string)($b['goal']??'')); if($goal==='') th_json(400,['error'=>'goal_required']);
    $ctx=trim((string)($b['context']??''));
    charged($acc,PRICING['agent_run'],'agent_run',$sector,fn()=>ai_complete(sys_for($sector,$lang)." Arbeite als autonomer Agent: Ziel analysieren, Plan (3-5 Schritte), dann das fertige Ergebnis liefern.","Ziel: $goal\n\nKontext:\n$ctx",2200));

  default:
    th_json(404,['error'=>'not_found','routes'=>['GET /v1/sectors','GET /v1/balance','POST /v1/generate','POST /v1/translate','POST /v1/subtitles','POST /v1/agent']]);
}
