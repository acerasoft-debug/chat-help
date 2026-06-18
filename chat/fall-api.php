<?php
/**
 * ChatHelp — Fall (Vaka) AI orkestrasyon endpoint'i  v3
 * Planlar ve aylık Fall kotaları:  Basic 5 · Pro 20 · Elite 100
 *
 * v3 değişiklikleri:
 *  - fall_chat: kompleks konularda daha fazla soru sorar, bağlamdan kopmaz
 *  - fall_solve: ton parametresi (formell/sachlich/bestimmt) + fotoğraf desteği
 *  - Güncel model, hiçbir AI sağlayıcı adı kullanıcıya gösterilmez
 *  - Gerçek kişisel veriler 3. taraflara gönderilmez (sunucuda birleştirilir)
 */
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/db.php';
if (file_exists(__DIR__ . '/config.php')) require_once __DIR__ . '/config.php';

if (!defined('DEEPSEEK_KEY') || !defined('CLAUDE_KEY')) {
    http_response_code(500);
    echo json_encode(['error' => 'Konfigurationsfehler. Bitte wenden Sie sich an den Support.']);
    exit;
}

const FALL_QUOTA = ['basic' => 5, 'pro' => 20, 'elite' => 100];

/* Plan adını normalize et — webhook 'Pro'/'PRO'/'pro_plan'/'elite_monthly' vs.
   ne yazarsa yazsın, kod hep 'basic'/'pro'/'elite' ile çalışsın.
   Böylece kullanıcı paketi alınca KESİN tanınır. */
function ch_norm_plan(string $raw): string {
    $p = strtolower(trim($raw));
    if ($p === '') return 'free';
    if (strpos($p, 'elite') !== false || strpos($p, 'premium') !== false) return 'elite';
    if (strpos($p, 'pro')   !== false) return 'pro';
    if (strpos($p, 'basic') !== false || strpos($p, 'basis') !== false || strpos($p, 'start') !== false) return 'basic';
    return $p; // bilinmeyen -> olduğu gibi (free dahil)
}

/* ── Auth + plan ── */
function fall_user(): array {
    $auth  = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $tok   = trim(str_replace('Bearer', '', $auth));
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
        $st  = $pdo->prepare('SELECT plan, COALESCE(status,"active") st FROM user_plans WHERE user_id=? ORDER BY id DESC LIMIT 1');
        $st->execute([$d['sub']]);
        if ($r = $st->fetch()) {
            $st_raw  = strtolower(trim($r['st'] ?? 'active'));
            $isActive = in_array($st_raw, ['active', 'aktiv', 'paid', 'complete', 'completed', 'trialing', ''], true);
            $plan     = $isActive ? ch_norm_plan($r['plan'] ?? '') : 'free';
        }
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

/* ── AI çağrıları (sağlayıcı adı kullanıcıya gösterilmez) ── */
function chAI1(array $messages, float $temp = 0.4): string {
    $ch = curl_init('https://api.deepseek.com/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 90, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . DEEPSEEK_KEY],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'deepseek-chat', 'messages' => $messages,
            'temperature' => $temp, 'max_tokens' => 2500,
        ]),
    ]);
    $res = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    if ($res === false) { error_log('CH-AI1 curl: ' . $err); return ''; }
    $d = json_decode($res, true);
    if (isset($d['error'])) error_log('CH-AI1 API: ' . json_encode($d['error']));
    return $d['choices'][0]['message']['content'] ?? '';
}

/* $photos: [['type'=>'image/jpeg','data'=>'base64...'], ...] */
function chAI2(string $system, string $userText, int $maxTok = 4000, array $photos = []): string {
    // Fotoğraf varsa multimodal içerik oluştur
    if ($photos) {
        $content = [['type'=>'text','text'=>$userText]];
        foreach ($photos as $ph) {
            $mt = $ph['type'] ?? 'image/jpeg';
            if (empty($ph['data'])) continue;
            if ($mt === 'application/pdf') {
                $content[] = ['type'=>'document','source'=>['type'=>'base64','media_type'=>'application/pdf','data'=>$ph['data']]];
            } elseif (in_array($mt, ['image/jpeg','image/png','image/gif','image/webp'])) {
                $content[] = ['type'=>'image','source'=>['type'=>'base64','media_type'=>$mt,'data'=>$ph['data']]];
            }
        }
        if (count($content) < 2) { $msgs = [['role'=>'user','content'=>$userText]]; }
        else { $msgs = [['role'=>'user','content'=>$content]]; }
    } else {
        $msgs = [['role'=>'user','content'=>$userText]];
    }

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . CLAUDE_KEY,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model'      => 'claude-sonnet-4-6',
            'max_tokens' => $maxTok,
            'system'     => $system,
            'messages'   => $msgs,
        ]),
    ]);
    $res = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    if ($res === false) { error_log('CH-AI2 curl: ' . $err); return ''; }
    $d = json_decode($res, true);
    if (isset($d['error'])) error_log('CH-AI2 API: ' . json_encode($d['error']));
    return $d['content'][0]['text'] ?? '';
}

$user   = fall_user();
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? 'fall_chat';
$title  = trim($body['title'] ?? 'Fall');
$msgs   = $body['messages'] ?? [];

$convo = '';
foreach ($msgs as $m) {
    $role   = ($m['role'] ?? 'user') === 'user' ? 'NUTZER' : 'BERATER';
    $convo .= "$role: " . trim($m['content'] ?? '') . "\n";
}

if ($action === 'fall_quota') {
    $used = fall_used($user['id']);
    echo json_encode(['plan'=>$user['plan'], 'used'=>$used, 'quota'=>FALL_QUOTA[$user['plan']]]);
    exit;
}

/* ── Sohbet: önce tam anla, sonra yönlendir (+ foto/dosya okuma) ── */
if ($action === 'fall_chat') {
    // Sohbet sırasında eklenen foto/belgeleri Vision ile oku, bağlama kat
    $photos = array_values(array_filter($body['photos'] ?? [], fn($p) =>
        isset($p['data']) && strlen($p['data']) > 100 && strlen($p['data']) < 5000000
    ));
    $photoCtx = '';
    if ($photos) {
        $ext = chAI2(
            "Du wertest hochgeladene Dokumente/Belege aus. Extrahiere alle relevanten Fakten "
            . "(Daten, Fristen, Aktenzeichen, Beträge, Behörde/Absender, Kernaussagen) als knappe deutsche Stichpunkte. Keine Einleitung.",
            "Beigefügte Dokumente des Nutzers:",
            1000,
            array_slice($photos, 0, 4)
        );
        if ($ext) $photoCtx = "\n\nWICHTIG — Der Nutzer hat Dokumente hochgeladen. Daraus extrahierte Fakten "
            . "(beziehe dich aktiv darauf, stelle bei Bedarf Rückfragen):\n$ext";
    }

    $sys = "Du bist ein erfahrener, einfühlsamer Rechtsberater. "
         . "Deine ERSTE Aufgabe: die Situation des Nutzers VOLLSTÄNDIG verstehen — "
         . "besonders bei komplexen Themen wie Aufenthaltsrecht, Einspruch, Widerspruch, "
         . "Steuerrecht, Arbeitsrecht, Sozialrecht, Mietrecht, Familienrecht. "
         . "Stelle gezielte Rückfragen (1–2 pro Antwort) bis du ALLE wesentlichen Details kennst: "
         . "relevante Fristen/Daten, beteiligte Behörden oder Parteien, bisherige Schritte, "
         . "Aktenzeichen/Bescheid-Datum (falls vorhanden), konkretes Ziel des Nutzers. "
         . "WICHTIG: Wenn der Nutzer eine Folgefrage stellt oder etwas nochmals sehen/erklärt haben möchte "
         . "(z.B. 'zeig mir das nochmal', 'was meinst du mit …', 'erkläre …'), "
         . "beantworte diese direkt und vollständig im Kontext des laufenden Gesprächs — weiche NICHT ab. "
         . "Generell: bleib immer beim Thema der aktuellen Situation. "
         . "Gib NOCH KEIN fertiges Dokument oder konkreten Rechtsrat aus. "
         . "Erst wenn du alle nötigen Informationen hast, sage: "
         . "'➡️ Ich habe nun alle wichtigen Informationen — Sie können auf »Fall lösen« klicken.'"
         . $photoCtx;

    $reply = chAI1(array_merge(
        [['role'=>'system','content'=>$sys]],
        array_map(fn($m)=>['role'=>($m['role']==='user'?'user':'assistant'),'content'=>$m['content']], $msgs)
    ), 0.5);
    echo json_encode(['reply' => $reply ?: 'Der KI-Dienst ist momentan nicht erreichbar. Bitte erneut versuchen.']);
    exit;
}

/* ── Fall lösen: 3 analiz + nihai belge (+ opsiyonel fotoğraf) ── */
if ($action === 'fall_solve') {
    $used  = fall_used($user['id']);
    $quota = FALL_QUOTA[$user['plan']];
    if ($used >= $quota) {
        http_response_code(403);
        echo json_encode(['error' => "Monatslimit erreicht ($used/$quota Fälle). Upgrade für mehr Fallanalysen.", 'upgrade'=>true]);
        exit;
    }

    // Schreibton (Pro/Elite seçebilir)
    $toneMap = [
        'formell'  => 'höflich-formell, professioneller Briefstil',
        'sachlich' => 'sachlich-nüchtern, direkt und klar, ohne Floskeln',
        'bestimmt' => 'bestimmt und fordernd, klare Rechtspositionen, aber stets respektvoll',
    ];
    $toneStr = $toneMap[trim($body['tone'] ?? 'formell')] ?? $toneMap['formell'];

    // Fotoğraflar (base64, kullanıcının yüklediği belgeler/fişler/yazışmalar)
    $photos = array_filter($body['photos'] ?? [], fn($p) =>
        isset($p['data']) && strlen($p['data']) > 100 && strlen($p['data']) < 5000000
    );
    $photoNote = $photos
        ? "\n\nHINWEIS: Der Nutzer hat " . count($photos) . " Bild(er) hochgeladen (z.B. Bescheid, Schreiben, Beleg). "
          . "Analysiere den Bildinhalt und nutze alle daraus ersichtlichen Informationen für das Dokument."
        : '';

    $analysis = [];

    $a1 = chAI1([
        ['role'=>'system','content'=>'Du bist deutscher Jurist. Analysiere sachlich: rechtlicher Rahmen, §-Angaben, Fristen, Erfolgsaussichten.'],
        ['role'=>'user','content'=>"FALL: $title\n\nGESPRÄCH:\n$convo$photoNote"],
    ], 0.3);
    $analysis[] = ['step'=>'Rechtliche Analyse', 'text'=>$a1];

    $a2 = chAI1([
        ['role'=>'system','content'=>'Du bist Prozessstratege. Beste Strategie, mögliche Gegenargumente, wichtige Beweise.'],
        ['role'=>'user','content'=>"FALL: $title\n\nGESPRÄCH:\n$convo\n\nANALYSE:\n$a1"],
    ], 0.4);
    $analysis[] = ['step'=>'Strategie', 'text'=>$a2];

    $a3 = chAI1([
        ['role'=>'system','content'=>'Bestimme den passenden Dokumententyp (Widerspruch, Klage, Mahnung, Antrag …) und die stärksten Argumente als Stichpunkte.'],
        ['role'=>'user','content'=>"FALL: $title\n\nGESPRÄCH:\n$convo\n\nANALYSE:\n$a1\n\nSTRATEGIE:\n$a2"],
    ], 0.4);
    $analysis[] = ['step'=>'Dokumententyp & Argumente', 'text'=>$a3];

    // Gerçek profil + tarih: sadece sunucuda birleştirilir, 3. taraflara gönderilmez
    $prof    = $body['profile'] ?? [];
    $datum   = trim($body['datum'] ?? date('d.m.Y'));
    $absName = trim(($prof['f1'] ?? '') . ' ' . ($prof['f2'] ?? ''));
    $absAdr  = trim(($prof['f3'] ?? '') . (($prof['f4'] ?? '') ? ', ' . $prof['f4'] : ''));
    $profBlock = "ABSENDERDATEN (exakt diese Daten im Dokument verwenden, KEINE Platzhalter):\n"
        . "Name: "    . ($absName ?: '[Vorname Nachname]') . "\n"
        . "Adresse: " . ($absAdr  ?: '[Straße, PLZ Ort]')  . "\n"
        . "E-Mail: "  . ($prof['f7'] ?? '') . "\n"
        . "Telefon: " . ($prof['f6'] ?? '') . "\n"
        . "Datum: "   . $datum . "\n\n";

    $sys = "Du bist ein erfahrener deutscher Rechtsanwalt. Erstelle ein vollständiges, sofort einsetzbares "
         . "rechtliches Dokument. Korrekte Briefform: Absender (echte Daten), Empfänger, Ort + Datum, "
         . "Betreff, Anrede, ausführliche Begründung mit §-Verweisen, klare Forderung + Frist, Grußformel. "
         . "Schreibstil: $toneStr. "
         . "Reiner Fließtext — kein Markdown, keine Sternchen, keine Emojis. "
         . "Echte Absenderdaten und angegebenes Datum verwenden. "
         . "Kein Versandvermerk (z.B. 'Per Einschreiben'). "
         . "Falls Bilder mitgeschickt wurden: Analysiere deren Inhalt (Bescheide, Belege, Schreiben) "
         . "und nutze alle relevanten Informationen daraus im Dokument. "
         . "Nur das fertige Dokument ausgeben — keine Kommentare.";

    $usr = $profBlock
         . "FALL: $title\n\nGESPRÄCH:\n$convo\n\nRECHTLICHE ANALYSE:\n$a1\n\nSTRATEGIE:\n$a2\n\n"
         . "DOKUMENTENTYP & ARGUMENTE:\n$a3\n\nErstelle jetzt das finale Dokument auf Deutsch.";

    $document = chAI2($sys, $usr, 4000, array_values($photos));
    if ($document) fall_log($user['id'], $title);

    echo json_encode([
        'success'  => (bool)$document,
        'analysis' => $analysis,
        'document' => $document ?: 'Das Dokument konnte nicht erstellt werden. Bitte erneut versuchen.',
        'title'    => $title,
        'used'     => $used + ($document ? 1 : 0),
        'quota'    => $quota,
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unbekannte Aktion']);
