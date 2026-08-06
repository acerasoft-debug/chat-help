<?php
/**
 * Yönetici hesabı kur — CLI
 * =========================
 *   php tools/admin-user.php set <e-posta> <şifre>
 *   php tools/admin-user.php show
 *
 * Şifre data/admin.json'a password_hash ile yazılır; dosya data/.gitignore
 * sayesinde depoya girmez. Alternatif olarak ortam değişkeni kullanılabilir:
 *   VR_ADMIN_USER=... VR_ADMIN_PW_HASH='$2y$...'
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Nur über die Kommandozeile.\n";
    exit(1);
}

require_once __DIR__ . '/../inc/boot.php';
require_once __DIR__ . '/../inc/admin.php';

$cmd = (string)($argv[1] ?? 'show');

if ($cmd === 'show') {
    $c = vr_admin_config();
    if ($c === null) {
        echo "Kein Administrator eingerichtet. Das Panel ist geschlossen.\n";
        echo "Einrichten mit:  php tools/admin-user.php set <E-Mail> <Passwort>\n";
        exit(0);
    }
    $src = getenv('VR_ADMIN_USER') ? 'Umgebungsvariable' : 'data/admin.json';
    echo "Administrator : {$c['email']}\n";
    echo "Quelle        : {$src}\n";
    exit(0);
}

if ($cmd !== 'set' || count($argv) < 4) {
    echo "Aufruf: php tools/admin-user.php set <E-Mail> <Passwort>\n";
    echo "        php tools/admin-user.php show\n";
    exit(1);
}

[$ok, $err] = vr_admin_set((string)$argv[2], (string)$argv[3]);
if (!$ok) { echo "Fehler: {$err}\n"; exit(1); }

@chmod(vr_store_path(VR_ADMIN_FILE), 0600);
echo "Administrator gesetzt: " . strtolower(trim((string)$argv[2])) . "\n";
echo "Datei: " . vr_store_path(VR_ADMIN_FILE) . " (chmod 600)\n";
echo "Panel: " . vr_abs('admin/login.php') . "\n";
