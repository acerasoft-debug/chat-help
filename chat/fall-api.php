<?php
/**
 * ChatHelp — Fall (Vaka) AI orkestrasyon endpoint'i
 * -------------------------------------------------
 * Bağımsız "Fall eröffnen" bölümü için. Sadece Pro/Elite (JWT plan kontrolü).
 *
 * Akış (action=fall_solve):
 *   1) DeepSeek #1 — durumu analiz et, hukuki çerçeveyi çıkar
 *   2) DeepSeek #2 — eksik bilgi/sorular + strateji
 *   3) DeepSeek #3 — taslak argümanlar + hangi dilekçe türü
 *   4) Claude       — tüm analizleri sentezleyip NİHAİ, duruma özel dilekçeyi yaz
 *
 * action=fall_chat : Fall içinde serbest sohbet (DeepSeek, kısa turlar)
 *
 * İstek (POST JSON):
 *   { action, title, messages:[{role,content}], context? }
 * Yanıt:
 *   fall_chat  -> { reply }
 *   fall_solve -> { analysis:[...], document, docType }
 */
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/db.php';

/* ── Auth + plan (Pro/Elite) ── */
function fall_user(): array {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $tok  = trim(str_replace('Bearer', '', $auth));
    $parts = explode('.', $tok);
    if (count($parts) !== 3) { http_response_code(401); echo json_encode(['error'=>'Nicht autorisiert']); exit; }
    [$h,$p,$s] = $parts;
    $exp = rtrim(strtr(base64_encode(hash_hmac('sha256', "$h.$p", JWT_SECRET, true)), '+/', '-_'), '=');
    if (!hash_equals($exp, $s)) { http_response_code(401); echo json_encode(['error'=>'Token ungültig']); exit; }
    $d = json_decode(base64_decode(strtr($p, '-_', '+/')), true);
    if (!$d || ($d['exp'] ?? 0) < time()) { http_response_code(401); echo json_encode(['error'=>'Token abgelaufen']); exit; }
    // Plan'ı DB'den taze çek (token eski olabilir)
    $plan = $d['plan'] ?? 'free';
    try {
        $pdo = db();
        $st = $pdo->prepare('SELECT plan, COALESCE(status,"active") st FROM user_plans WHERE user_id=? ORDER BY id DESC LIMIT 1');
        $st->execute([$d['sub']]);
        if ($r = $st->fetch()) { $plan = ($r['st']==='active') ? $r['plan'] : 'free'; }
    } catch (Exception $e) {}
    if (!in_array($plan, ['pro','elite'], true)) {
        http_response_code(403);
        echo json_encode(['error'=>'Fall-Analyse nur für Pro & Elite verfügbar', 'upgrade'=>true]);
        exit;
    }
    return ['id'=>$d['sub'], 'email'=>$d['email']??'', 'plan'=>$plan];
}

/* ── DeepSeek çağrısı ── */
function deepseek(array $messages, float $temp = 0.4): string {
    $ch = curl_init('https://api.deepseek.com/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 90,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . DEEPSEEK_KEY],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'deepseek-chat',
            'messages' => $messages,
            'temperature' => $temp,
            'max_tokens' => 2500,
        ]),
    ]);
    $res = curl_exec($ch); curl_close($ch);
    $d = json_decode($res, true);
    return $d['choices'][0]['message']['content'] ?? '';
}

/* ── Claude çağrısı ── */
function claude(string $system, string $user, int $maxTok = 4000): string {
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . CLAUDE_KEY,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'claude-sonnet-4-5-20250929',
            'max_tokens' => $maxTok,
            'system' => $system,
            'messages' => [['role'=>'user','content'=>$user]],
        ]),
    ]);
    $res = curl_exec($ch); curl_close($ch);
    $d = json_decode($res, true);
    return $d['content'][0]['text'] ?? '';
}

$user = fall_user();
$body = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? 'fall_chat';
$title  = trim($body['title'] ?? 'Fall');
$msgs   = $body['messages'] ?? [];

/* Sohbeti düz metne çevir */
$convo = '';
foreach ($msgs as $m) {
    $role = ($m['role'] ?? 'user') === 'user' ? 'NUTZER' : 'ASSISTENT';
    $convo .= "$role: " . trim($m['content'] ?? '') . "\n";
}

if ($action === 'fall_chat') {
    // Kısa, anlamaya yönelik sohbet turu (DeepSeek)
    $sys = "Du bist ein erfahrener deutscher Rechtsberater für ChatHelp. Führe ein kurzes, "
         . "verständnisvolles Gespräch um die Situation des Nutzers GENAU zu verstehen. Stelle "
         . "höchstens 1-2 gezielte Rückfragen pro Antwort. Gib NOCH KEIN fertiges Dokument aus. "
         . "Wenn du genug verstanden hast, sage: '➡️ Ich habe genug Informationen — Sie können jetzt den Fall lösen lassen.'";
    $reply = deepseek(array_merge(
        [['role'=>'system','content'=>$sys]],
        array_map(fn($m)=>['role'=>($m['role']==='user'?'user':'assistant'),'content'=>$m['content']], $msgs)
    ), 0.5);
    echo json_encode(['reply' => $reply ?: 'Entschuldigung, bitte versuchen Sie es erneut.']);
    exit;
}

if ($action === 'fall_solve') {
    $analysis = [];

    // 1) DeepSeek — hukuki analiz
    $a1 = deepseek([
        ['role'=>'system','content'=>'Du bist deutscher Jurist. Analysiere den Fall: rechtlicher Rahmen, einschlägige Gesetze (§), Fristen, Erfolgsaussichten. Strukturiert, sachlich.'],
        ['role'=>'user','content'=>"FALL: $title\n\nGESPRÄCH:\n$convo"],
    ], 0.3);
    $analysis[] = ['step'=>'Rechtliche Analyse', 'text'=>$a1];

    // 2) DeepSeek — strateji + eksikler
    $a2 = deepseek([
        ['role'=>'system','content'=>'Du bist Prozessstratege. Auf Basis der Analyse: beste Strategie, mögliche Einwände der Gegenseite, welche Beweise/Angaben noch wichtig sind.'],
        ['role'=>'user','content'=>"FALL: $title\n\nGESPRÄCH:\n$convo\n\nANALYSE:\n$a1"],
    ], 0.4);
    $analysis[] = ['step'=>'Strategie', 'text'=>$a2];

    // 3) DeepSeek — dilekçe türü + argümanlar
    $a3 = deepseek([
        ['role'=>'system','content'=>'Bestimme die passende Dokumentenart (z.B. Widerspruch, Klage, Mahnung, Antrag) und liefere die stärksten Argumente in Stichpunkten.'],
        ['role'=>'user','content'=>"FALL: $title\n\nGESPRÄCH:\n$convo\n\nANALYSE:\n$a1\n\nSTRATEGIE:\n$a2"],
    ], 0.4);
    $analysis[] = ['step'=>'Dokumententyp & Argumente', 'text'=>$a3];

    // 4) Claude — nihai, duruma özel dilekçe
    $sys = "Du bist ein deutscher Rechtsanwalt. Erstelle ein vollständiges, sofort verwendbares, "
         . "rechtlich präzises deutsches Dokument basierend auf der gesamten Analyse. Korrekte Form, "
         . "Anrede, Betreff, Begründung mit §-Verweisen, Forderung, Fristen, Grußformel. NUR das fertige Dokument ausgeben.";
    $usr = "FALL: $title\n\nGESPRÄCH MIT NUTZER:\n$convo\n\n"
         . "JURISTISCHE ANALYSE:\n$a1\n\nSTRATEGIE:\n$a2\n\nDOKUMENTENTYP & ARGUMENTE:\n$a3\n\n"
         . "Erstelle jetzt das finale, maßgeschneiderte Dokument auf Deutsch.";
    $document = claude($sys, $usr, 4000);

    echo json_encode([
        'success'  => true,
        'analysis' => $analysis,
        'document' => $document ?: 'Dokument konnte nicht erstellt werden. Bitte erneut versuchen.',
        'title'    => $title,
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown action']);
