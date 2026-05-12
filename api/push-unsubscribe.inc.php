<?php
/**
 * Endpoint JSON per la rimozione di una subscription Web Push.
 *
 *   POST /api/push-unsubscribe.inc.php
 *   Content-Type: application/json
 *   Body: {"endpoint": "..."}
 *
 * Cancella la riga corrispondente in `push_subscriptions`. La cancellazione
 * filtra anche per user_login: un utente non puo' eliminare la subscription
 * di un altro account anche se ne conoscesse l'endpoint.
 *
 * Risposta (200): {"ok": true, "deleted": int}
 * Errori:
 *   - 400 invalid_request   endpoint mancante
 *   - 401 unauthenticated   sessione/JWT non valido
 *   - 405 method_not_allowed metodo HTTP non POST
 *
 * @see api/push-subscribe.inc.php
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

$raw  = file_get_contents('php://input');
$body = [];
if (is_string($raw) && $raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $body = $decoded;
    }
}
$endpoint = isset($body['endpoint']) ? (string)$body['endpoint'] : '';

if ($endpoint === '') {
    http_response_code(400);
    echo json_encode(
        ['error' => 'invalid_request', 'detail' => 'endpoint required'],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

Db::preparedExecute(
    "DELETE FROM push_subscriptions WHERE user_login = ? AND endpoint = ?",
    'ss',
    array((string)$auth['login'], $endpoint)
);

// gdrcd_query non espone affected_rows in modo uniforme: rispondiamo
// idempotente, senza tradire al client se l'endpoint c'era o no.
if (function_exists('gdrcd_log_info')) {
    gdrcd_log_info('push subscription removed', [
        'user' => (string)$auth['login'],
    ]);
}

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
