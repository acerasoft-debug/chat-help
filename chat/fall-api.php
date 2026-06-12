<?php
/**
 * ChatHelp — Fall (Vaka) AI orkestrasyon endpoint'i  v2
 * -----------------------------------------------------
 * Planlar ve aylık Fall kotaları:  Basic 5 · Pro 20 · Elite 100
 * Kota sayımı sunucu tarafında (user_falls tablosu, otomatik oluşturulur).
 *
 * Akış (action=fall_solve):  3× DeepSeek (analiz, strateji, argümanlar) + 1× Claude (nihai dilekçe)
 * action=fall_chat        :  anlamaya yönelik kısa sohbet (DeepSeek)
 */
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/db.php';
// DEEPSEEK_KEY config.php'de tanımlı — onu da yükle
if (file_exists(__DIR__ . '/config.php')) require_once __DIR__ . '/config.php';

if (!defined('DEEPSEEK_KEY') || !defined('CLAUDE_KEY')) {
    http_response_code(500);
    echo json_encode(['error' => 'API-Schlüssel fehlen (config.php prüfen)']);
    exit;
}

const FALL_QUOTA = ['basic' => 5, 'pro' => 20, 'elite' => 100];

/* ── Auth + plan ── */
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
    $plan = 'free';
    try {
        $pdo = db();
        $st = $pdo->prepare('SELECT plan, COALESCE(status,"active") st FROM user_plans WHERE user_id=? ORDER BY id DESC LIMIT 1');
        $st->execute([$d['sub']]);
        if ($r = $st->fetch()) { $plan = ($r['st'] === 'active') ? $r['plan'] : 'free'; }
    } catch (Exception $e) {}
    if (!isset(FALL_QUOTA[$plan])) {
        http_response_code(403);
        echo json_encode(['error'=>'Fall-Analyse erfordert einen aktiven Plan (Basic: 5, Pro: 20, Elite: 100 Fälle/Monat)', 'upgrade'=>true]);
        exit;
    }
    return ['id'=>$d['sub'], 'email'=>$d['email'] ?? '', 'plan'=>$plan];
}

/* ── Aylık kota ── */
function fall_used(int $uid): int {
    try {
        $pdo = db();
        $pdo->exec('CREATE TABLE IF NOT EXISTS user_falls (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) DEFAULT "",
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (user_id, created_at)
        ) CHARACTER SET utf8mb4');
        $st = $pdo->prepare('SELECT COUNT(*) c FROM user_falls WHERE user_id=? AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())');
        $st->execute([$uid]);
        return (int)($st->fetch()['c'] ?? 0);
    } catch (Exception $e) { return 0; }
}
function fall_log(int $uid, string $title): void {
    try { db()->prepare('INSERT INTO user_falls (user_id, title) VALUES (?,?)')->execute([$uid, mb_substr($title, 0, 250)]); } catch (Exception $e) {}
}

/* ── DeepSeek ── */
function deepseek(array $messages, float $temp = 0.4): string {
    $ch = curl_init('https://api.deepseek.com/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 90, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . DEEPSEEK_KEY],
        CURLOPT_POSTFIELDS => json_encode(['model'=>'deepseek-chat','messages'=>$messages,'temperature'=>$temp,'max_tokens'=>2500]),
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) { error_log('DeepSeek curl: ' . $err); return ''; }
    $d = json_decode($res, true);
    if (isset($d['error'])) error_log('DeepSeek API: ' . json_encode($d['error']));
    return $d['choices'][0]['message']['content'] ?? '';
}

/* ── Claude ── */
function claude(string $system, string $user, int $maxTok = 4000): string {
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-api-key: ' . CLAUDE_KEY, 'anthropic-version: 2023-06-01'],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'claude-sonnet-4-5-20250929',
            'max_tokens' => $maxTok,
            'system' => $system,
            'messages' => [['role'=>'user','content'=>$user]],
        ]),
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) { error_log('Claude curl: ' . $err); return ''; }
    $d = json_decode($res, true);
    if (isset($d['error'])) error_log('Claude API: ' . json_encode($d['error']));
    return $d['content'][0]['text'] ?? '';
}

$user = fall_user();
$body = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? 'fall_chat';
$title  = trim($body['title'] ?? 'Fall');
$msgs   = $body['messages'] ?? [];

$convo = '';
foreach ($msgs as $m) {
    $role = ($m['role'] ?? 'user') === 'user' ? 'NUTZER' : 'ASSISTENT';
    $convo .= "$role: " . trim($m['content'] ?? '') . "\n";
}

if ($action === 'fall_quota') {
    $used = fall_used($user['id']);
    echo json_encode(['plan'=>$user['plan'], 'used'=>$used, 'quota'=>FALL_QUOTA[$user['plan']]]);
    exit;
}

if ($action === 'fall_chat') {
    $sys = "Du bist ein erfahrener, freundlicher deutscher Rechtsberater für ChatHelp. Führe ein natürliches "
         . "Gespräch, um die Situation des Nutzers GENAU zu verstehen. Antworte warm und konkret, stelle pro "
         . "Antwort höchstens 1-2 gezielte Rückfragen. Gib noch KEIN fertiges Dokument aus. Wenn du genug weißt, "
         . "sage: '➡️ Ich habe genug Informationen — Sie können jetzt unten auf »Fall lösen« klicken.'";
    $reply = deepseek(array_merge(
        [['role'=>'system','content'=>$sys]],
        array_map(fn($m)=>['role'=>($m['role']==='user'?'user':'assistant'),'content'=>$m['content']], $msgs)
    ), 0.5);
    echo json_encode(['reply' => $reply ?: 'Entschuldigung, der KI-Dienst ist gerade nicht erreichbar. Bitte erneut versuchen.']);
    exit;
}

if ($action === 'fall_solve') {
    // Kota kontrolü
    $used  = fall_used($user['id']);
    $quota = FALL_QUOTA[$user['plan']];
    if ($used >= $quota) {
        http_response_code(403);
        echo json_encode(['error' => "Monatslimit erreicht ($used/$quota Fälle). Upgrade für mehr Fallanalysen.", 'upgrade'=>true]);
        exit;
    }

    $analysis = [];
    $a1 = deepseek([
        ['role'=>'system','content'=>'Du bist deutscher Jurist. Analysiere den Fall: rechtlicher Rahmen, einschlägige Gesetze (§), Fristen, Erfolgsaussichten. Strukturiert, sachlich.'],
        ['role'=>'user','content'=>"FALL: $title\n\nGESPRÄCH:\n$convo"],
    ], 0.3);
    $analysis[] = ['step'=>'Rechtliche Analyse', 'text'=>$a1];

    $a2 = deepseek([
        ['role'=>'system','content'=>'Du bist Prozessstratege. Auf Basis der Analyse: beste Strategie, mögliche Einwände der Gegenseite, wichtige Beweise.'],
        ['role'=>'user','content'=>"FALL: $title\n\nGESPRÄCH:\n$convo\n\nANALYSE:\n$a1"],
    ], 0.4);
    $analysis[] = ['step'=>'Strategie', 'text'=>$a2];

    $a3 = deepseek([
        ['role'=>'system','content'=>'Bestimme die passende Dokumentenart (Widerspruch, Klage, Mahnung, Antrag …) und liefere die stärksten Argumente in Stichpunkten.'],
        ['role'=>'user','content'=>"FALL: $title\n\nGESPRÄCH:\n$convo\n\nANALYSE:\n$a1\n\nSTRATEGIE:\n$a2"],
    ], 0.4);
    $analysis[] = ['step'=>'Dokumententyp & Argumente', 'text'=>$a3];

    // Gerçek profil bilgileri + tarih (boş/placeholder gitmesin)
    $prof    = $body['profile'] ?? [];
    $datum   = trim($body['datum'] ?? date('d.m.Y'));
    $absName = trim(($prof['f1'] ?? '') . ' ' . ($prof['f2'] ?? ''));
    $absAdr  = trim(($prof['f3'] ?? '') . (($prof['f4'] ?? '') ? ', ' . $prof['f4'] : ''));
    $profBlock = "ABSENDERDATEN (verwende diese echten Daten direkt im Dokument, KEINE Platzhalter):\n"
        . "Name: "    . ($absName ?: '[Vorname Nachname]') . "\n"
        . "Adresse: " . ($absAdr  ?: '[Straße, PLZ Ort]')  . "\n"
        . "E-Mail: "  . ($prof['f7'] ?? '') . "\n"
        . "Telefon: " . ($prof['f6'] ?? '') . "\n"
        . "Datum: "   . $datum . "\n\n";

    $sys = "Du bist ein deutscher Rechtsanwalt. Erstelle ein vollständiges, sofort verwendbares, rechtlich "
         . "präzises deutsches Dokument basierend auf der gesamten Analyse. Korrekte Briefform: Absender (echte "
         . "Daten oben), Empfänger, Ort und Datum, Betreff, Anrede, Begründung mit §-Verweisen, klare Forderung "
         . "mit Frist, Grußformel. "
         . "WICHTIG: Reiner Fließtext ohne Markdown, ohne Sternchen, ohne Emojis. Verwende die echten Absenderdaten "
         . "und das angegebene Datum. Füge KEINE Versandvermerke wie 'Per Einschreiben mit Rückschein' oder "
         . "'per E-Mail' ein. Gib NUR das fertige Dokument aus.";
    $usr = $profBlock . "FALL: $title\n\nGESPRÄCH MIT NUTZER:\n$convo\n\nJURISTISCHE ANALYSE:\n$a1\n\nSTRATEGIE:\n$a2\n\n"
         . "DOKUMENTENTYP & ARGUMENTE:\n$a3\n\nErstelle jetzt das finale, maßgeschneiderte Dokument auf Deutsch.";
    $document = claude($sys, $usr, 4000);

    if ($document) fall_log($user['id'], $title);

    echo json_encode([
        'success'  => (bool)$document,
        'analysis' => $analysis,
        'document' => $document ?: 'Dokument konnte nicht erstellt werden. Bitte erneut versuchen.',
        'title'    => $title,
        'used'     => $used + ($document ? 1 : 0),
        'quota'    => $quota,
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown action']);
