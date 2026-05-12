<?php
declare(strict_types=1);

/**
 * Endpoint JSON per la sottoscrizione Web Push.
 *
 *   POST /api/push-subscribe.inc.php
 *   Content-Type: application/json
 *   Body: {"endpoint": "...", "keys": {"p256dh": "...", "auth": "..."}}
 *
 * Salva (o aggiorna) la subscription in `push_subscriptions` legandola
 * all'utente autenticato. UNIQUE su endpoint: una stessa subscription
 * non puo' rimanere assegnata a due account diversi.
 *
 * Risposta (200): {"ok": true}
 * Errori:
 *   - 400 invalid_request   payload mancante o malformato
 *   - 401 unauthenticated   sessione/JWT non valido
 *   - 405 method_not_allowed metodo HTTP non POST
 *
 * @see api/push-unsubscribe.inc.php
 * @see includes/notifications.js
 * @see service-worker.js (handler push)
 */

require_once __DIR__ . '/../includes/required.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Pragma: no-cache');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'method_not_allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$handleDBConnection = gdrcd_connect();

$auth = gdrcd_api_authenticate();
if ($auth === null) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthenticated'], JSON_UNESCAPED_UNICODE);
    exit;
}

// --- Parse body (JSON) -------------------------------------------------
$raw  = file_get_contents('php://input');
$body = [];
if (is_string($raw) && $raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $body = $decoded;
    }
}

$endpoint = isset($body['endpoint']) ? (string)$body['endpoint'] : '';
$keys     = isset($body['keys']) && is_array($body['keys']) ? $body['keys'] : [];
$p256dh   = isset($keys['p256dh']) ? (string)$keys['p256dh'] : '';
$auth_key = isset($keys['auth']) ? (string)$keys['auth'] : '';

// Validazione minima: endpoint deve essere un URL https://, le chiavi non vuote.
if ($endpoint === '' || $p256dh === '' || $auth_key === '') {
    http_response_code(400);
    echo json_encode(
        ['error' => 'invalid_request', 'detail' => 'endpoint, keys.p256dh, keys.auth required'],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}
if (stripos($endpoint, 'https://') !== 0) {
    http_response_code(400);
    echo json_encode(
        ['error' => 'invalid_request', 'detail' => 'endpoint must be https://'],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

// Limiti hard sui campi salvati: VARCHAR(255) per le chiavi base64url e'
// largamente sufficiente (p256dh = 65 byte raw, auth = 16 byte raw).
if (strlen($p256dh) > 255 || strlen($auth_key) > 255) {
    http_response_code(400);
    echo json_encode(
        ['error' => 'invalid_request', 'detail' => 'keys too long'],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

// REPLACE non e' affidabile su UNIQUE prefisso (endpoint(255)): usiamo
// INSERT ... ON DUPLICATE KEY UPDATE che rispetta gli indici unique.
Db::preparedExecute(
    "INSERT INTO push_subscriptions (user_login, endpoint, p256dh, auth, created_at, last_used_at) "
    . "VALUES (?, ?, ?, ?, NOW(), NULL) "
    . "ON DUPLICATE KEY UPDATE "
    . "user_login = VALUES(user_login), "
    . "p256dh     = VALUES(p256dh), "
    . "auth       = VALUES(auth), "
    . "last_used_at = NULL",
    'ssss',
    array((string)$auth['login'], $endpoint, $p256dh, $auth_key)
);

if (function_exists('gdrcd_log_info')) {
    gdrcd_log_info('push subscription saved', [
        'user' => (string)$auth['login'],
    ]);
}

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
