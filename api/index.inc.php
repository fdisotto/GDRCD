<?php
/**
 * Endpoint JSON di metadata per le API GDRCD.
 *
 * Risponde con:
 * {
 *   "version": "1.0",
 *   "server_time": "ISO-8601 UTC",
 *   "endpoints": [ ... ]
 * }
 *
 * Richiede sessione valida (per coerenza con gli altri endpoint).
 *
 * @see api/notifications.inc.php
 */

require_once __DIR__ . '/../includes/required.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$auth = gdrcd_api_authenticate();
if ($auth === null) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthenticated'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
    'version'     => '1.1',
    'server_time' => gmdate('Y-m-d\TH:i:s\Z'),
    'auth'        => [
        'login'   => '/api/auth/login.inc.php',
        'refresh' => '/api/auth/refresh.inc.php',
    ],
    'endpoints'   => [
        '/api/notifications.inc.php',
        '/api/presenti.inc.php',
        '/api/chat.inc.php',
        '/api/luogo.inc.php',
        '/api/scheda.inc.php',
        '/api/search.inc.php',
        '/api/admin-metrics.inc.php',
        '/api/user-export.inc.php',
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
