<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

define('CLAUDE_KEY',   'sk-ant-***REDACTED***');
define('DEEPSEEK_KEY', 'sk-***REDACTED***');
define('STORE_DIR', __DIR__ . '/data/');
define('TTL', 86400);

if (!file_exists(STORE_DIR)) mkdir(STORE_DIR, 0700, true);
if (!file_exists(STORE_DIR.'.htaccess')) file_put_contents(STORE_DIR.'.htaccess', "Deny from all\n");

// Session
if (empty($_COOKIE['ch_sid'])) {
    $sid = bin2hex(random_bytes(16));
    setcookie('ch_sid', $sid, time()+86400*365, '/', '', true, true);
} else {
    $sid = preg_replace('/[^a-f0-9]/','', $_COOKIE['ch_sid']);
}

cleanOld();

$action = $_GET['action'] ?? 'generate';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

match($action) {
    'generate'        => doGenerate($body, $sid),
    'detect'          => doDetect($body),
    'followup'        => doFollowup($body),
    'analyze_photo'   => doPhoto($body),
    'detect_location' => doLocation(),
    'history'         => doHistory($sid),
    'delete'          => doDelete($body, $sid),
    'keepdoc'         => doKeep($body, $sid),
    default           => respond(['error' => 'Unknown action']),
};

// ── LOCATION DETECTION ────────────────────────────────
function doLocation(): void {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $ip = explode(',', $ip)[0];
    $ip = filter_var(trim($ip), FILTER_VALIDATE_IP) ? trim($ip) : '';
    
    // ipapi.co ücretsiz servis
    $url = $ip ? "https://ipapi.co/{$ip}/json/" : "https://ipapi.co/json/";
    $ch = curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>5,CURLOPT_USERAGENT=>'chat-help.de/1.0']);
    $res = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($res, true);
    $cc   = strtoupper($data['country_code'] ?? 'DE');
    
    // Desteklenen ülkeler
    $supported = ['DE','AT','CH','FR','IT','ES','NL','BE','PL','SE','DK','NO','FI','PT','GR','CZ','HU','RO','BG','HR'];
    if (!in_array($cc, $supported)) $cc = 'EU';
    
    $langs = ['DE'=>'de','AT'=>'de','CH'=>'de','FR'=>'fr','IT'=>'it','ES'=>'es',
              'NL'=>'nl','BE'=>'fr','PL'=>'pl','SE'=>'sv','DK'=>'da','NO'=>'no',
              'FI'=>'fi','PT'=>'pt','GR'=>'el','CZ'=>'cs','HU'=>'hu','RO'=>'ro',
              'BG'=>'bg','HR'=>'hr','EU'=>'en'];
    
    $names = ['DE'=>'Deutschland','AT'=>'Österreich','CH'=>'Schweiz','FR'=>'France',
              'IT'=>'Italia','ES'=>'España','NL'=>'Nederland','BE'=>'Belgique',
              'PL'=>'Polska','SE'=>'Sverige','DK'=>'Danmark','NO'=>'Norge',
              'FI'=>'Suomi','PT'=>'Portugal','GR'=>'Ελλάδα','CZ'=>'Česko',
              'HU'=>'Magyarország','RO'=>'România','BG'=>'България','HR'=>'Hrvatska','EU'=>'Europe'];
    
    respond(['country'=>$cc,'lang'=>$langs[$cc]??'de','name'=>$names[$cc]??$cc,'city'=>$data['city']??'']);
}

// ── DOCUMENT TYPE DETECTION ──────────────────────────
function doDetect(array $b): void {
    $text    = $b['text'] ?? '';
    $lang    = $b['lang'] ?? 'de';
    $country = $b['country'] ?? 'DE';
    if (!$text) { respond(['doc'=>'other','reply'=>'']); return; }
    
    $docs = 'widerspruch|strafanzeige|mietvertrag|kuendigung_mieter|kuendigung_vermieter|maengelanzeige|mietminderung|kaution|arbeitsvertrag|arbeit_kuendigung|abmahnung_widerspruch|zeugnis|aufhebungsvertrag|elternzeit|testament|vorsorgevollmacht|vollmacht|unterhalt|kindergeld|buergergeld|steuer_an|steuer_selbst|einspruch_steuer|kfz_kaufvertrag|bussgeldbescheid|widerruf|mahnschreiben|fluggastrechte|inkasso|abo_kuendigung|other';
    
    $r = callClaude(
        "Identify document type from user message. Country: {$country}. ONLY JSON: {\"doc\":\"{$docs}\",\"reply\":\"Brief friendly reply in {$lang} (1-2 sentences)\"}",
        "User message: {$text}",
        150
    );
    
    if ($r) {
        $d = json_decode(preg_replace('/```json?|```/','',$r), true);
        respond($d ?: ['doc'=>'other','reply'=>'']);
    } else {
        respond(['doc'=>'other','reply'=>'']);
    }
}

// ── FOLLOW-UP QUESTIONS ──────────────────────────────
function doFollowup(array $b): void {
    $docType  = $b['docType'] ?? '';
    $docName  = $b['docName'] ?? '';
    $answers  = $b['answers'] ?? [];
    $draft    = $b['draft'] ?? '';
    $country  = $b['country'] ?? 'DE';
    $lang     = $b['lang'] ?? 'de';
    
    $answersText = implode("\n", array_map(fn($k,$v) => "$k: $v", array_keys($answers), $answers));
    
    $r = callClaude(
        "You are a legal document assistant. Check if the draft document needs more information. Country: {$country}. ONLY JSON: {\"needs_more\":true|false,\"questions\":[{\"key\":\"field_name\",\"label\":\"Question in {$lang}\",\"type\":\"text|date|textarea\",\"required\":true}],\"message\":\"Brief explanation in {$lang} why more info needed (or empty string)\"}",
        "Document type: {$docName}\nAnswers provided:\n{$answersText}\n\nDraft document:\n".substr($draft,0,1000),
        400
    );
    
    if ($r) {
        $d = json_decode(preg_replace('/```json?|```/','',$r), true);
        respond($d ?: ['needs_more'=>false,'questions'=>[],'message'=>'']);
    } else {
        respond(['needs_more'=>false,'questions'=>[],'message'=>'']);
    }
}

// ── PHOTO ANALYSIS ───────────────────────────────────
function doPhoto(array $b): void {
    $b64  = $b['b64']  ?? '';
    $mime = $b['mime'] ?? 'image/jpeg';
    if (!$b64) { respond(['error'=>'No image']); return; }
    
    $payload = [
        'model'=>'claude-sonnet-4-6','max_tokens'=>800,
        'system'=>'Analyze German/European legal documents. ONLY JSON without formatting.',
        'messages'=>[['role'=>'user','content'=>[
            ['type'=>'image','source'=>['type'=>'base64','media_type'=>$mime,'data'=>$b64]],
            ['type'=>'text','text'=>'Analyze this document: {"erkannt":"","doc":"widerspruch|strafanzeige|kuendigung_mieter|mietvertrag|bussgeldbescheid|einspruch_steuer|mahnschreiben|other","behoerde":"","datum":"","az":"","frist":"","betrag":"","land":"","zusammenfassung":"2 sentences what this is and what to do"}']
        ]]]
    ];
    
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>json_encode($payload),
        CURLOPT_HTTPHEADER=>['x-api-key: '.CLAUDE_KEY,'anthropic-version: 2023-06-01','content-type: application/json'],
        CURLOPT_TIMEOUT=>45]);
    $res  = curl_exec($ch); curl_close($ch);
    $data = json_decode($res, true);
    $text = $data['content'][0]['text'] ?? '{}';
    $result = json_decode(preg_replace('/```json?|```/','',$text), true);
    respond(['success'=>true,'result'=>$result??[]]);
}

// ── GENERATE DOCUMENT ────────────────────────────────
function doGenerate(array $b, string $sid): void {
    $docType  = htmlspecialchars($b['docType'] ?? '');
    $docName  = htmlspecialchars($b['docName'] ?? 'Dokument');
    $answers  = $b['answers'] ?? [];
    $profile  = $b['profile'] ?? [];
    $lang     = $b['lang'] ?? 'de';
    $country  = strtoupper($b['country'] ?? 'DE');
    
    if (!$docType) { respond(['error'=>'No document type']); return; }

    // Country-specific legal system prompts
    $lawSystems = [
        'DE' => "Expert German lawyer. Apply German law: BGB, StGB, VwGO, ArbGG, ZPO, SGB. Use correct §§ references.",
        'AT' => "Expert Austrian lawyer. Apply Austrian law: ABGB, StGB, VwGVG, ArbVG. Use Austrian legal terminology.",
        'CH' => "Expert Swiss lawyer. Apply Swiss law: OR, ZGB, StGB, VwVG. Available in DE/FR/IT depending on region.",
        'FR' => "Expert avocat français. Appliquer le droit français: Code civil, Code du travail, Code pénal. Citer les articles.",
        'IT' => "Avvocato esperto italiano. Applicare il diritto italiano: Codice civile, Codice penale, Statuto lavoratori.",
        'ES' => "Abogado experto español. Aplicar el derecho español: Código civil, Código penal, Estatuto trabajadores.",
        'NL' => "Expert Nederlandse jurist. Nederlands recht toepassen: BW, WvSr, Awb. Correcte wetsartikelen citeren.",
        'BE' => "Expert juridique belge. Appliquer le droit belge/Code civil belge. Citer les articles pertinents.",
        'default' => "Expert European lawyer. Apply relevant national law and EU regulations. Cite specific laws.",
    ];
    $sysLaw = $lawSystems[$country] ?? $lawSystems['default'];
    
    // Placeholders for GDPR compliance
    $ph = [
        'VORNAME'     => $profile['f1'] ?? '',
        'NACHNAME'    => $profile['f2'] ?? '',
        'STRASSE'     => $profile['f3'] ?? '',
        'PLZ_ORT'     => $profile['f4'] ?? '',
        'ADRESSE'     => trim(($profile['f3']??'').' '.($profile['f4']??'')),
        'GEBURTSDATUM'=> $profile['f5'] ?? '',
        'TELEFON'     => $profile['f6'] ?? '',
        'EMAIL'       => $profile['f7'] ?? '',
        'DATUM_HEUTE' => date('d.m.Y'),
        'ORT'         => explode(' ', trim($profile['f4']??''), 2)[1] ?? 'Ort',
    ];
    
    $answersText = implode("\n", array_map(fn($k,$v) => $v?"- $k: $v":'', array_keys($answers), $answers));
    $answersText = implode("\n", array_filter(explode("\n", $answersText)));
    
    // Step 1: DeepSeek — anonymous research
    $research = '';
    $dsPrompt = "Research current legal requirements for: {$docName} in {$country}. Max 5 bullet points. Include key laws, deadlines, tips. No personal data. Language: {$lang}.";
    $dsR = callDeepSeek($dsPrompt, 600);
    if ($dsR) $research = $dsR;
    
    // Step 2: Claude draft with placeholders
    $draftPrompt = "Create a professional {$country} legal document: {$docName}

SENDER (use PLACEHOLDERS only - do NOT use real names):
Name: [VORNAME] [NACHNAME]
Address: [ADRESSE], [PLZ_ORT]
DOB: [GEBURTSDATUM]
Phone: [TELEFON]
Email: [EMAIL]
Date: [DATUM_HEUTE]
Place: [ORT]

CASE DETAILS:
{$answersText}

".($research ? "LEGAL CONTEXT:\n{$research}\n" : "")."
RULES:
- Keep ALL [PLACEHOLDERS] exactly as written
- Professional {$country} legal language
- Cite specific laws with §§ or Article numbers
- Complete document structure with greeting and signature block
- Return ONLY the document text, no explanations";

    $draft = callClaude($sysLaw, $draftPrompt, 2500);
    if (!$draft) $draft = '';
    
    // Step 3: Claude final — replace placeholders with real data
    $finalPrompt = $draft 
        ? "Replace all placeholders with real data and polish the document:\n\n".
          implode("\n", array_map(fn($k,$v)=>"[$k] → $v", array_keys($ph), $ph)).
          "\n\nDOCUMENT:\n{$draft}\n\nRules: Replace ALL placeholders. Keep professional tone. Fix grammar. Return ONLY the final document."
        : "Create {$docName} for {$country}. Sender: {$ph['VORNAME']} {$ph['NACHNAME']}, {$ph['ADRESSE']}, {$ph['PLZ_ORT']}. Date: {$ph['DATUM_HEUTE']}.\n{$answersText}\nReturn ONLY the document text.";
    
    $final = callClaude("Precise document assistant. Return ONLY the final document text.", $finalPrompt, 3000);
    if (!$final && $draft) {
        // Manual placeholder replacement as fallback
        $final = $draft;
        foreach ($ph as $k => $v) {
            $final = str_replace("[$k]", $v, $final);
        }
    }
    if (!$final) { respond(['error' => 'Document generation failed. Check API key.']); return; }
    
    // Save document
    $docId  = bin2hex(random_bytes(8));
    $stored = ['id'=>$docId,'sid'=>$sid,'docType'=>$docType,'docName'=>$docName,
               'document'=>$final,'research'=>$research,'country'=>$country,
               'created'=>time(),'keep'=>false,
               'preview'=>mb_substr(strip_tags($final),0,200)];
    file_put_contents(STORE_DIR.$sid.'_'.$docId.'.json', json_encode($stored, JSON_UNESCAPED_UNICODE));
    
    respond(['success'=>true,'docId'=>$docId,'document'=>$final,'research'=>$research]);
}

// ── HISTORY ─────────────────────────────────────────
function doHistory(string $sid): void {
    $docs = [];
    foreach (glob(STORE_DIR.$sid.'_*.json') as $f) {
        $d = json_decode(file_get_contents($f), true);
        if (!$d) continue;
        $exp = TTL - (time() - $d['created']);
        $docs[] = ['id'=>$d['id'],'docName'=>$d['docName'],'docType'=>$d['docType'],
                   'country'=>$d['country']??'DE','created'=>date('d.m.Y H:i',$d['created']),
                   'keep'=>$d['keep']??false,'expiresIn'=>max(0,ceil($exp/3600)).'h',
                   'preview'=>$d['preview']??''];
    }
    usort($docs, fn($a,$b)=>strcmp($b['created'],$a['created']));
    respond(['docs'=>$docs]);
}

function doDelete(array $b, string $sid): void {
    $id   = preg_replace('/[^a-f0-9]/','', $b['docId']??'');
    $file = STORE_DIR.$sid.'_'.$id.'.json';
    if (file_exists($file)) { unlink($file); respond(['success'=>true]); }
    else respond(['error'=>'Not found']);
}

function doKeep(array $b, string $sid): void {
    $id   = preg_replace('/[^a-f0-9]/','', $b['docId']??'');
    $keep = (bool)($b['keep']??true);
    $file = STORE_DIR.$sid.'_'.$id.'.json';
    if (file_exists($file)) {
        $d = json_decode(file_get_contents($file), true);
        $d['keep'] = $keep;
        file_put_contents($file, json_encode($d, JSON_UNESCAPED_UNICODE));
        respond(['success'=>true]);
    } else respond(['error'=>'Not found']);
}

// ── HELPERS ──────────────────────────────────────────
function callClaude(string $system, string $user, int $max=2000): ?string {
    $payload = ['model'=>'claude-sonnet-4-6','max_tokens'=>$max,
                'system'=>$system,'messages'=>[['role'=>'user','content'=>$user]]];
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>json_encode($payload),
        CURLOPT_HTTPHEADER=>['x-api-key: '.CLAUDE_KEY,'anthropic-version: 2023-06-01','content-type: application/json'],
        CURLOPT_TIMEOUT=>60]);
    $res = curl_exec($ch); curl_close($ch);
    $data = json_decode($res, true);
    return $data['content'][0]['text'] ?? null;
}

function callDeepSeek(string $prompt, int $max=500): ?string {
    $payload = ['model'=>'deepseek-chat','max_tokens'=>$max,
                'messages'=>[['role'=>'system','content'=>'Legal research assistant. Concise, factual, current information only.'],
                             ['role'=>'user','content'=>$prompt]]];
    $ch = curl_init('https://api.deepseek.com/v1/chat/completions');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>json_encode($payload),
        CURLOPT_HTTPHEADER=>['Authorization: Bearer '.DEEPSEEK_KEY,'content-type: application/json'],
        CURLOPT_TIMEOUT=>30]);
    $res = curl_exec($ch); curl_close($ch);
    $data = json_decode($res, true);
    return $data['choices'][0]['message']['content'] ?? null;
}

function cleanOld(): void {
    foreach (glob(STORE_DIR.'*.json') as $f) {
        $d = json_decode(file_get_contents($f), true);
        if (!$d) { unlink($f); continue; }
        if (!($d['keep']??false) && (time()-($d['created']??0)) > TTL) unlink($f);
    }
}

function respond(array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
