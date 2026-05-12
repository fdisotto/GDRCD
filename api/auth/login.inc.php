<?php
declare(strict_types=1);

/**
 * Endpoint JSON: login stateless (rilascia JWT).
 *
 *   POST /api/auth/login.inc.php
 *   Content-Type: application/json
 *   Body: {"login": "Nome", "password": "..."}
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
 *   - 400 invalid_request   payload mancante / mal formato
 *   - 401 unauthorized      credenziali errate o PG disabilitato/esiliato
 *   - 405 method_not_allowed  metodo HTTP non POST
 *   - 429 too_many_requests rate-limit superato per l'IP
 *
 * Stesse regole di lockout della pagina /login.php (5 fallimenti / 5 min
 * via `gdrcd_login_attempts_count`). Ogni tentativo viene registrato in
 * `login_attempts` per coerenza con il flusso web.
 *
 * @see api/auth/refresh.inc.php
 * @see login.php
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

// Body: accetta sia JSON che form-urlencoded (curl base senza Content-Type)
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

$login_in = isset($body['login']) ? trim((string)$body['login']) : '';
$pass_in  = isset($body['password']) ? (string)$body['password'] : '';

if ($login_in === '' || $pass_in === '') {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_request', 'detail' => 'login and password required'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Normalizza username come fa login.php (case-insensitive, Title Case)
$login_in = ucwords(strtolower($login_in));

$ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');

$handleDBConnection = gdrcd_connect();

// Rate-limit: stessa soglia del web login (5/5min). Conta i fallimenti.
$failures = gdrcd_login_attempts_count($ip, 5);
if ($failures >= 5) {
    gdrcd_login_attempt_log($ip, $login_in, false);
    if (function_exists('gdrcd_log_warning')) {
        gdrcd_log_warning('api login rate-limit triggered', [
            'username' => $login_in,
            'ip'       => $ip,
            'failures' => $failures,
        ]);
    }
    http_response_code(429);
    header('Retry-After: 300');
    echo json_encode(['error' => 'too_many_requests'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Carico hash + permessi + flag "double connection" come login.php
$record = Db::preparedFetch(
    "SELECT pass, nome, permessi, ora_entrata, ora_uscita, ultimo_refresh
     FROM personaggio WHERE nome = ? LIMIT 1",
    's',
    [$login_in]
) ?? [];

$auth_ok = !empty($record)
    && gdrcd_password_verify($pass_in, $record['pass'])
    && ((int)$record['permessi'] > -1);

if (!$auth_ok) {
    gdrcd_login_attempt_log($ip, $login_in, false);
    if (function_exists('gdrcd_log_warning')) {
        gdrcd_log_warning('api login failed', [
            'username' => $login_in,
            'ip'       => $ip,
        ]);
    }
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Rehash silenzioso se hash legacy
if (gdrcd_password_needs_rehash($record['pass'])) {
    $newHash = gdrcd_password_hash($pass_in);
    Db::preparedExecute(
        "UPDATE personaggio SET pass = ? WHERE nome = ? LIMIT 1",
        'ss',
        [$newHash, $record['nome']]
    );
}

// Esilio: se il PG è esiliato, nega il rilascio del token.
if (function_exists('gdrcd_controllo_esilio') && gdrcd_controllo_esilio($record['nome']) === true) {
    gdrcd_login_attempt_log($ip, $login_in, false);
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized', 'detail' => 'exiled'], JSON_UNESCAPED_UNICODE);
    exit;
}

gdrcd_login_attempt_log($ip, $login_in, true);
if (function_exists('gdrcd_login_attempts_cleanup')) {
    gdrcd_login_attempts_cleanup($ip);
}

$exp_seconds = gdrcd_jwt_exp_seconds();
$token = gdrcd_jwt_encode(
    ['sub' => (string)$record['nome']],
    gdrcd_jwt_secret(),
    $exp_seconds
);

echo json_encode([
    'token'      => $token,
    'token_type' => 'Bearer',
    'expires_in' => $exp_seconds,
    'user'       => [
        'login'    => (string)$record['nome'],
        'permessi' => (int)$record['permessi'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if (isset($handleDBConnection)) {
    gdrcd_close_connection($handleDBConnection);
    unset($handleDBConnection);
}
exit;
