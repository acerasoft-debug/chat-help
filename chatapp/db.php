<?php
define('DB_HOST', '10.204.138.240');
define('DB_NAME', 'db_dom145450');
define('DB_USER', 'db_dom145450');
define('DB_PASS', '1qssg0cXOJ6yaiK46dku6IgtPHXIe0TPefLz5caQ');
define('JWT_SECRET', 'chathelp_jwt_s3cr3t_2026_xK9mP2qL');
define('CLAUDE_KEY', 'sk-ant-***REDACTED***');
define('STRIPE_SK', 'sk_live_***REDACTED***');
define('STRIPE_PK', 'pk_live_51RZCPoAJdvZPpak7JOnMMHuwGeVFknKi8Wh8GMtreXlAZLXTTGwE8nI8D0Zi40nKBNqHUyfuXCXtgh7dMzCxzCQU00ze3mS4lt');
define('SMTP_HOST', 'smtp.hosteurope.de');
define('SMTP_PORT_SSL', 465);
define('SMTP_PORT', 587);
define('SMTP_USER', 'support@chat-help.de');
define('SMTP_PASS', '1881Ata1938.'); // Host Europe SMTP şifresini ekle
define('SMTP_FROM', 'support@chat-help.de');
define('SMTP_NAME', 'ChatHelp');

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = new PDO(
        'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    return $pdo;
}

function respond(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit;
}
