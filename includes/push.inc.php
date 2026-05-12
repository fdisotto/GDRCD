<?php
declare(strict_types=1);

/**
 * Web Push sender (STUB).
 *
 * Espone `gdrcd_push_send($user_login, $payload)` che dovrebbe inviare una
 * notifica push a tutte le subscription registrate per l'utente, ma
 * l'implementazione COMPLETA del protocollo Web Push richiede:
 *
 *   1. Firma VAPID JWT (ES256 / ECDSA su curva P-256)
 *      - header  {"typ":"JWT","alg":"ES256"}
 *      - payload {"aud": <origin del push service>, "exp": <unix ts>, "sub": <mailto:...>}
 *      - firma con la chiave privata VAPID (parametri.push.vapid_private)
 *
 *   2. Cifratura payload secondo RFC 8291 (Message Encryption for Web Push):
 *      - ECDH P-256 fra chiave del server (ephemeral) e p256dh del client
 *      - HKDF -> CEK (Content Encryption Key) + nonce
 *      - AES-128-GCM sul payload con padding RFC 8188 (aes128gcm)
 *
 *   3. POST verso `endpoint` con header
 *      - Authorization: vapid t=<jwt>, k=<vapid_public_base64url>
 *      - Content-Encoding: aes128gcm
 *      - TTL: <ttl in secondi>
 *      - Content-Type: application/octet-stream
 *
 *   4. Gestione risposte (201/204 = OK, 404/410 = subscription scaduta -> DELETE,
 *      429 = retry con backoff, 413 = payload troppo grande).
 *
 * Tutto questo richiede openssl ECDSA + ECDH + HKDF + AES-GCM e una libreria
 * JWT che firmi ES256: e' troppo per essere implementato qui in modo affidabile
 * senza ampia copertura test. La scelta corretta in produzione e' importare
 * `minishlink/web-push` via Composer e usare l'helper per delegare a quello.
 *
 *   TODO: integrare minishlink/web-push (https://github.com/web-push-libs/web-push-php)
 *   oppure implementare un client minimale con sodium_crypto_box + openssl.
 *
 * Per ora questa funzione e' uno STUB: log della richiesta, conteggio delle
 * subscription trovate, e ritorno del numero di destinatari "che sarebbero
 * stati notificati". Non effettua HTTP requests verso il push service.
 *
 * @see api/push-subscribe.inc.php
 * @see service-worker.js (handler push)
 */

if (!function_exists('gdrcd_push_send')) {
    /**
     * Invia (stub: oggi solo logga) una notifica push a tutte le subscription
     * dell'utente `$user_login`.
     *
     * @param string $user_login Login del destinatario (FK push_subscriptions.user_login).
     * @param array  $payload    Array assoc. con almeno `title`. Chiavi note:
     *                           - title (string)  obbligatorio nel SW handler
     *                           - body  (string)  testo della notifica
     *                           - url   (string)  URL aperta al click
     *                           - tag   (string)  id di deduplica
     * @return int Numero di subscription trovate (= notifiche che sarebbero
     *             state inviate). 0 se nessuna subscription o config mancante.
     */
    function gdrcd_push_send(string $user_login, array $payload): int
    {
        global $PARAMETERS;

        $vapid_pub  = (string)($PARAMETERS['push']['vapid_public']  ?? '');
        $vapid_priv = (string)($PARAMETERS['push']['vapid_private'] ?? '');

        if ($vapid_pub === '' || $vapid_priv === '') {
            if (function_exists('gdrcd_log_warning')) {
                gdrcd_log_warning('gdrcd_push_send: VAPID keys missing, skipping send', [
                    'user' => $user_login,
                ]);
            }
            return 0;
        }

        $user_q = gdrcd_filter('in', $user_login);
        $rows   = [];

        // gdrcd_query() su SELECT con piu' righe ritorna direttamente il
        // resultset solo se la wrapper supporta MULTI; in mancanza, fallback
        // a mysqli su $handleDBConnection.
        global $handleDBConnection;
        $res = mysqli_query(
            $handleDBConnection,
            "SELECT id, endpoint, p256dh, auth FROM push_subscriptions "
            . "WHERE user_login = '" . $user_q . "'"
        );
        if ($res instanceof mysqli_result) {
            while ($r = mysqli_fetch_assoc($res)) {
                $rows[] = $r;
            }
            mysqli_free_result($res);
        }

        if (empty($rows)) {
            return 0;
        }

        // TODO: ciclare $rows e per ognuna:
        //   - firmare VAPID JWT con $vapid_priv (ES256)
        //   - cifrare json_encode($payload) verso $row['p256dh'] / $row['auth']
        //   - POST a $row['endpoint'] (vedi block doc in cima)
        //   - se 404/410, DELETE FROM push_subscriptions WHERE id = $row['id']
        //
        // L'attuale STUB si limita a tracciare l'intent: nessun pacchetto
        // arriva davvero al browser finche' la cifratura non e' implementata
        // o non si integra minishlink/web-push.

        if (function_exists('gdrcd_log_info')) {
            gdrcd_log_info('gdrcd_push_send (STUB) - would dispatch', [
                'user'          => $user_login,
                'subscriptions' => count($rows),
                'title'         => (string)($payload['title'] ?? ''),
                'has_body'      => isset($payload['body']),
                'url'           => (string)($payload['url'] ?? ''),
            ]);
        }

        return count($rows);
    }
}
