<?php
declare(strict_types=1);

/**
 * JWT (RFC 7519) helper minimale — algoritmo HS256.
 *
 * Implementazione self-contained (no Composer / no librerie esterne) per
 * l'autenticazione stateless delle API GDRCD destinate a client mobile o
 * a integrazioni esterne. Usa solo `hash_hmac()` e funzioni base64/json
 * incluse in PHP standard.
 *
 * Formato token: <base64url(header)>.<base64url(payload)>.<base64url(sig)>
 * Header fisso: {"alg":"HS256","typ":"JWT"}
 *
 * Le firme sono verificate in tempo costante via `hash_equals()` per
 * evitare timing attacks. La scadenza è controllata su `exp` (epoch
 * seconds): un token senza `exp` viene rifiutato per evitare token
 * "immortali" per dimenticanza.
 *
 * @see api/auth/login.inc.php
 * @see api/auth/refresh.inc.php
 * @see gdrcd_api_authenticate()
 */

if (!function_exists('gdrcd_jwt_base64url_encode')) {
    /**
     * Codifica base64url (RFC 7515 §C): base64 con `-` e `_` al posto di
     * `+` e `/`, padding `=` rimosso.
     */
    function gdrcd_jwt_base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if (!function_exists('gdrcd_jwt_base64url_decode')) {
    /**
     * Decodifica base64url: ripristina padding e mappa `-_` -> `+/`.
     * Ritorna `false` se l'input non è valido.
     *
     * @return string|false
     */
    function gdrcd_jwt_base64url_decode(string $data)
    {
        $remainder = strlen($data) % 4;
        if ($remainder > 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'), true);
    }
}

if (!function_exists('gdrcd_jwt_encode')) {
    /**
     * Genera un JWT HS256 firmato con $secret.
     *
     * I claim `iat` (issued at) ed `exp` (expiration) vengono aggiunti
     * automaticamente se non presenti nel payload. `iss` viene preso da
     * `$PARAMETERS['jwt']['issuer']` se disponibile.
     *
     * @param array  $payload     Claims custom (es. ['sub' => 'Nome']).
     * @param string $secret      Chiave HMAC condivisa (>= 32 byte raccomandato).
     * @param int    $expSeconds  TTL del token in secondi (default 3600).
     * @return string             Token in forma compatta.
     */
    function gdrcd_jwt_encode(array $payload, string $secret, int $expSeconds = 3600): string
    {
        $now = time();
        if (!isset($payload['iat'])) {
            $payload['iat'] = $now;
        }
        if (!isset($payload['exp'])) {
            $payload['exp'] = $now + max(1, $expSeconds);
        }
        if (!isset($payload['iss'])) {
            $issuer = $GLOBALS['PARAMETERS']['jwt']['issuer'] ?? 'gdrcd';
            if (is_string($issuer) && $issuer !== '') {
                $payload['iss'] = $issuer;
            }
        }

        $header  = ['alg' => 'HS256', 'typ' => 'JWT'];
        $segH    = gdrcd_jwt_base64url_encode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $segP    = gdrcd_jwt_base64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $signing = $segH . '.' . $segP;
        $sig     = hash_hmac('sha256', $signing, $secret, true);
        $segS    = gdrcd_jwt_base64url_encode($sig);
        return $signing . '.' . $segS;
    }
}

if (!function_exists('gdrcd_jwt_decode')) {
    /**
     * Verifica e decodifica un token HS256.
     *
     * Restituisce il payload (array) oppure `null` se il token è
     * malformato, ha un header inatteso (alg != HS256), una firma non
     * valida o è scaduto (`exp` <= now).
     *
     * @param string $token  Stringa JWT.
     * @param string $secret Chiave HMAC.
     * @return array|null
     */
    function gdrcd_jwt_decode(string $token, string $secret): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$segH, $segP, $segS] = $parts;

        $rawH = gdrcd_jwt_base64url_decode($segH);
        $rawP = gdrcd_jwt_base64url_decode($segP);
        $rawS = gdrcd_jwt_base64url_decode($segS);
        if ($rawH === false || $rawP === false || $rawS === false) {
            return null;
        }

        $header = json_decode($rawH, true);
        if (!is_array($header) || ($header['alg'] ?? '') !== 'HS256' || ($header['typ'] ?? 'JWT') !== 'JWT') {
            return null;
        }

        $payload = json_decode($rawP, true);
        if (!is_array($payload)) {
            return null;
        }

        $expected = hash_hmac('sha256', $segH . '.' . $segP, $secret, true);
        if (!hash_equals($expected, $rawS)) {
            return null;
        }

        // Senza exp -> rifiuto (no token "immortali")
        if (!isset($payload['exp']) || !is_numeric($payload['exp'])) {
            return null;
        }
        if ((int)$payload['exp'] <= time()) {
            return null;
        }

        return $payload;
    }
}

if (!function_exists('gdrcd_jwt_secret')) {
    /**
     * Restituisce la chiave HMAC da usare per firmare/verificare i JWT.
     *
     * Ordine di lookup:
     *  1. $PARAMETERS['jwt']['secret'] (config.inc.php)
     *  2. config_settings.jwt_secret  (DB, se presente)
     *  3. Genera 64 byte random, salva su config_settings.jwt_secret
     *
     * Nota: la chiave è memorizzata hex-encoded così resta safe in
     * column VARCHAR senza problemi di binary.
     */
    function gdrcd_jwt_secret(): string
    {
        $cfg = $GLOBALS['PARAMETERS']['jwt']['secret'] ?? '';
        if (is_string($cfg) && $cfg !== '') {
            return $cfg;
        }

        if (function_exists('gdrcd_config_get')) {
            $stored = gdrcd_config_get('jwt_secret', '');
            if (is_string($stored) && $stored !== '') {
                return $stored;
            }
        }

        // Genera nuova chiave e prova a persisterla.
        try {
            $bytes = random_bytes(64);
        } catch (\Throwable $e) {
            $bytes = openssl_random_pseudo_bytes(64) ?: hash('sha512', uniqid('', true) . microtime(true), true);
        }
        $secret = bin2hex($bytes);

        if (function_exists('gdrcd_config_set')) {
            // Best-effort: ignora errori (es. config_settings non disponibile).
            @gdrcd_config_set('jwt_secret', $secret, 'string');
        }

        return $secret;
    }
}

if (!function_exists('gdrcd_jwt_exp_seconds')) {
    /**
     * TTL configurato per i token (config.inc.php -> $PARAMETERS['jwt']['exp_seconds']).
     * Default: 86400 (24h).
     */
    function gdrcd_jwt_exp_seconds(): int
    {
        $v = $GLOBALS['PARAMETERS']['jwt']['exp_seconds'] ?? 86400;
        $v = (int)$v;
        return $v > 0 ? $v : 86400;
    }
}
