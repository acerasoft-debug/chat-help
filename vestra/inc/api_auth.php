<?php
/**
 * VESTRA — external API key auth.
 * Single static bearer key per partner integration (dropship API for now).
 * Key lives in .env as DROPSHIP_API_KEY (never in git — see inc/env.php),
 * installed via .github/workflows/set-dropship-key.yml.
 */
require_once __DIR__ . '/env.php';

/** Send a JSON body and stop. Every API endpoint ends its response this way. */
function api_json(array $data, int $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/** 401s and exits unless the Authorization: Bearer header matches DROPSHIP_API_KEY. */
function api_require_key(): void {
    $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $key = '';
    if (preg_match('/^Bearer\s+(.+)$/i', trim($hdr), $m)) $key = trim($m[1]);
    $want = getenv('DROPSHIP_API_KEY') ?: '';
    if ($want === '' || $key === '' || !hash_equals($want, $key)) {
        api_json(['ok' => false, 'error' => 'unauthorized'], 401);
    }
}

/** Parse a JSON request body into an array; 400s on malformed JSON. */
function api_json_body(): array {
    $raw = (string) file_get_contents('php://input');
    if (trim($raw) === '') return [];
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        api_json(['ok' => false, 'error' => 'invalid_json'], 400);
    }
    return $data;
}
