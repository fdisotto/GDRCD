<?php

/**
 * Web Push subscriptions: tabella `push_subscriptions`.
 *
 * Conserva le subscription PushManager emesse dai browser degli utenti
 * autenticati. Ogni subscription contiene:
 *   - endpoint:   URL del push service del browser (FCM, Mozilla, ecc.)
 *   - p256dh:     chiave pubblica ECDH P-256 client (base64url)
 *   - auth:       segreto di autenticazione client (base64url)
 *
 * Il server usa queste credenziali per cifrare il payload (AES-128-GCM)
 * e per la firma VAPID JWT verso il push service. Vedi includes/push.inc.php.
 *
 * UNIQUE su endpoint (prefisso 255 char): un browser puo' rigenerare
 * la subscription cambiando endpoint, ma lo stesso endpoint non puo'
 * appartenere a piu' utenti.
 *
 * Idempotente: CREATE TABLE IF NOT EXISTS, safe in caso di rerun.
 *
 * Pagine collegate:
 *   - api/push-subscribe.inc.php
 *   - api/push-unsubscribe.inc.php
 *   - pages/gestione/push_test.inc.php (SUPERUSER)
 */
class GDRCDPushSubscriptions extends DbMigration
{
    /**
     * @inheritDoc
     */
    public function up()
    {
        gdrcd_query("
            CREATE TABLE IF NOT EXISTS push_subscriptions (
                id           INT AUTO_INCREMENT PRIMARY KEY,
                user_login   VARCHAR(50) NOT NULL,
                endpoint     TEXT NOT NULL,
                p256dh       VARCHAR(255) NOT NULL,
                auth         VARCHAR(255) NOT NULL,
                created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_used_at DATETIME NULL,
                INDEX idx_user (user_login),
                UNIQUE KEY uq_endpoint (endpoint(255))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    /**
     * @inheritDoc
     */
    public function down()
    {
        gdrcd_query("DROP TABLE IF EXISTS push_subscriptions");
    }
}
