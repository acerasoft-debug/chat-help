<?php
/**
 * VESTRA — Web Push endpoint.
 *   GET  /push?a=vapid      → {publicKey}                (for pushManager.subscribe)
 *   POST /push?a=subscribe  → save this device's subscription for the signed-in user
 *   GET  /push?a=pending    → return & clear pending notifications for the signed-in
 *                             user (called BY THE SERVICE WORKER when a push arrives)
 */
require_once __DIR__.'/inc/auth.php';
require_once __DIR__.'/inc/push.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$a = $_GET['a'] ?? '';

if ($a === 'vapid') {
    $k = vestra_vapid_keys();
    echo json_encode(['publicKey' => $k['publicKey'] ?? '']);
    exit;
}

$uid = $_SESSION['uid'] ?? '';
if ($uid === '') { http_response_code(401); echo '{"error":"signin"}'; exit; }

if ($a === 'subscribe' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $sub = json_decode((string)file_get_contents('php://input'), true);
    $ok = is_array($sub) && vestra_push_subscribe($uid, $sub);
    echo json_encode(['ok' => $ok]);
    exit;
}

if ($a === 'pending') {
    echo json_encode(['notifs' => vestra_push_pending_take($uid)]);
    exit;
}

http_response_code(400); echo '{"error":"bad_request"}';
