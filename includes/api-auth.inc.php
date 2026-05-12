<?php
declare(strict_types=1);

/**
 * Middleware di autenticazione API.
 *
 * Espone `gdrcd_api_authenticate()` che cerca un utente valido prima nella
 * sessione PHP (compatibilità con i client web esistenti) e poi nell'header
 * `Authorization: Bearer <jwt>` (per client mobile / integrazioni esterne).
 *
 * Restituisce `['login' => string, 'permessi' => int]` oppure `null` se
 * non autenticato. Non emette response HTTP: la decisione su 401 vs altro
 * resta al chiamante per uniformità con gli altri endpoint.
 *
 * @see includes/jwt.inc.php
 * @see api/auth/login.inc.php
 */

require_once __DIR__ . '/jwt.inc.php';

if (!function_exists('gdrcd_api_authenticate')) {
    /**
     * Restituisce i dati dell'utente autenticato (login + permessi) o null.
     *
     * Ordine di lookup:
     *  1. $_SESSION['login']        (sessione PHP, flusso web)
     *  2. Authorization Bearer JWT  (mobile / API esterna)
     *
     * Nel caso JWT, il `sub` del token viene risolto su `personaggio` per
     * caricare i permessi correnti dal DB: questo previene token stale
     * che continuerebbero a vantare permessi modificati nel frattempo.
     *
     * @return array{login: string, permessi: int}|null
     */
    function gdrcd_api_authenticate(): ?array
    {
        // 1. Sessione attiva: stesso comportamento storico dei *.inc.php.
        if (!empty($_SESSION['login'])) {
            return [
                'login'    => (string)$_SESSION['login'],
                'permessi' => (int)($_SESSION['permessi'] ?? 0),
            ];
        }

        // 2. Authorization Bearer header. Apache/PHP-FPM lo passano in
        // HTTP_AUTHORIZATION; alcuni setup lo prefissano REDIRECT_*.
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';
        if (!is_string($header) || $header === '') {
            return null;
        }
        if (stripos($header, 'Bearer ') !== 0) {
            return null;
        }
        $token = trim(substr($header, 7));
        if ($token === '') {
            return null;
        }

        $payload = gdrcd_jwt_decode($token, gdrcd_jwt_secret());
        if ($payload === null) {
            return null;
        }

        $sub = (string)($payload['sub'] ?? '');
        if ($sub === '') {
            return null;
        }

        // Carica permessi correnti dal DB: lo `sub` del JWT è l'identità,
        // ma i permessi devono riflettere lo stato attuale del PG.
        $sub_safe = gdrcd_filter('in', $sub);
        $row = gdrcd_query(
            "SELECT permessi FROM personaggio WHERE nome = '" . $sub_safe . "' LIMIT 1"
        );
        if (empty($row) || !isset($row['permessi'])) {
            return null;
        }
        $permessi = (int)$row['permessi'];
        if ($permessi < 0) {
            // PG disabilitato / esiliato: nega il token.
            return null;
        }

        return [
            'login'    => $sub,
            'permessi' => $permessi,
        ];
    }
}

if (!function_exists('gdrcd_api_require_auth')) {
    /**
     * Wrapper "fail-closed": se l'utente non è autenticato emette
     * Content-Type JSON + 401 + body `{"error": "unauthenticated"}` e
     * chiama exit. Altrimenti restituisce l'array con login/permessi.
     *
     * Pensato per ridurre la boilerplate negli endpoint /api/*.inc.php.
     *
     * @return array{login: string, permessi: int}
     */
    function gdrcd_api_require_auth(): array
    {
        $auth = gdrcd_api_authenticate();
        if ($auth === null) {
            if (!headers_sent()) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(
                ['error' => 'unauthenticated'],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
            exit;
        }
        return $auth;
    }
}
