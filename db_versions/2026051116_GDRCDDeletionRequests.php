<?php

/**
 * Crea la tabella `deletion_requests` per il flusso "Right to be forgotten"
 * previsto dall'art. 17 del Regolamento UE 2016/679 (GDPR).
 *
 * Il flusso e' a due step:
 *   1. L'utente apre una richiesta di cancellazione da
 *      main.php?page=user_forget (campo motivo opzionale, conferma password).
 *   2. Un amministratore (SUPERUSER) la processa da
 *      main.php?page=gestione/forget_requests, anonimizzando il personaggio
 *      e tutte le tabelle che lo referenziano per nome.
 *
 * Convenzione:
 *   - status = 'pending'   richiesta da processare
 *   - status = 'processed' richiesta accolta + anonimizzazione completata
 *   - status = 'rejected'  richiesta rifiutata (motivazione in note_admin)
 *
 * La tabella e' costruita in modo idempotente: la migrazione puo' essere
 * eseguita piu' volte di seguito senza side effect.
 */
class GDRCDDeletionRequests extends DbMigration
{
    /**
     * @inheritDoc
     */
    public function up()
    {
        gdrcd_query("
            CREATE TABLE IF NOT EXISTS deletion_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_login VARCHAR(50) NOT NULL,
                user_email VARCHAR(255) NULL,
                reason TEXT NULL,
                status ENUM('pending','processed','rejected') NOT NULL DEFAULT 'pending',
                requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                processed_at DATETIME NULL,
                processed_by VARCHAR(50) NULL,
                note_admin TEXT NULL,
                INDEX idx_status (status),
                INDEX idx_user_login (user_login)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    /**
     * @inheritDoc
     */
    public function down()
    {
        gdrcd_query("DROP TABLE IF EXISTS deletion_requests");
    }
}
