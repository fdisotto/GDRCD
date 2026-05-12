<?php
declare(strict_types=1);

/**
 * Endpoint JSON: refresh di un JWT ancora valido.
 *
 *   POST /api/auth/refresh.inc.php
 *   Content-Type: application/json
 *   Body: {"token": "<jwt corrente>"}
 *
 * Il token può anche essere passato via header `Authorization: Bearer ...`.
 * Se il token è valido e non scaduto, viene emesso un nuovo token con TTL
 * resettato. Token scaduti ottengono 401: il client deve fare un nuovo
 * login completo (no refresh token long-lived in questa prima iterazione).
 *
 * Risposta (200):
 *   {
 *     "token":      "<jwt>",
 *     "token_type": "Bearer",
 *     "expires_in": 86400,
 *     "user": {"login": "Nome", "permessi": 1}
 *   }
 *
 * Errori:
 *   - 400 invalid_request   token mancante
 *   - 401 unauthorized      token assente, malformato, scaduto o PG non valido
 *   - 405 method_not_allowed
 *
 * @see api/auth/login.inc.php
 */

require_once __DIR__ . '/../../includes/required.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Pragma: no-cache');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'method_not_allowed'], JSON_UNESCAPED_UNICODE);
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
if (empty($body) && !empty($_POST)) {
    $body = $_POST;
}

// Lookup token: body { token } oppure Authorization Bearer
$token = isset($body['token']) ? trim((string)$body['token']) : '';
if ($token === '') {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (is_string($h) && stripos($h, 'Bearer ') === 0) {
        $token = trim(substr($h, 7));
    }
}

if ($token === '') {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_request', 'detail' => 'token required'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = gdrcd_jwt_decode($token, gdrcd_jwt_secret());
if ($payload === null) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized', 'detail' => 'invalid or expired token'], JSON_UNESCAPED_UNICODE);
    exit;
}

$sub = (string)($payload['sub'] ?? '');
if ($sub === '') {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

$handleDBConnection = gdrcd_connect();

// Verifica che il PG sia ancora valido (permessi >= 0, non esiliato).
$row = Db::preparedFetch(
    "SELECT nome, permessi FROM personaggio WHERE nome = ? LIMIT 1",
    's',
    array($sub)
);
if (empty($row) || (int)$row['permessi'] < 0) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
    if (isset($handleDBConnection)) {
        gdrcd_close_connection($handleDBConnection);
    }
    exit;
}

if (function_exists('gdrcd_controllo_esilio') && gdrcd_controllo_esilio($row['nome']) === true) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized', 'detail' => 'exiled'], JSON_UNESCAPED_UNICODE);
    if (isset($handleDBConnection)) {
        gdrcd_close_connection($handleDBConnection);
    }
    exit;
}

$exp_seconds = gdrcd_jwt_exp_seconds();
$new_token = gdrcd_jwt_encode(
    ['sub' => (string)$row['nome']],
    gdrcd_jwt_secret(),
    $exp_seconds
);

echo json_encode([
    'token'      => $new_token,
    'token_type' => 'Bearer',
    'expires_in' => $exp_seconds,
    'user'       => [
        'login'    => (string)$row['nome'],
        'permessi' => (int)$row['permessi'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if (isset($handleDBConnection)) {
    gdrcd_close_connection($handleDBConnection);
    unset($handleDBConnection);
}
exit;
