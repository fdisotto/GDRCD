<?php

/**
 * Crea la tabella `moderation_reports`: coda unificata di segnalazioni
 * utente -> staff per la moderazione (chat, comportamento, contenuto, altro).
 *
 * Schema:
 *   - reporter:     login del segnalante (USER+)
 *   - subject:      login del personaggio oggetto della segnalazione
 *   - kind:         categoria della segnalazione (chat/behavior/content/other)
 *   - body:         descrizione fornita dal segnalante
 *   - context_url:  link opzionale al contesto (es: log chat, bacheca)
 *   - status:       pending -> under_review -> resolved | dismissed
 *   - severity:     low/medium/high (default medium)
 *   - assigned_to:  moderatore che ha preso in carico la segnalazione
 *   - resolution:   nota di chiusura del moderatore
 *
 * Convenzione: la segnalazione resta nella tabella anche dopo la chiusura
 * (status='resolved' / 'dismissed') per audit e statistiche di moderazione.
 *
 * Idempotente: usa CREATE TABLE IF NOT EXISTS, puo' essere rieseguita senza
 * side effect.
 */
class GDRCDReportsModeration extends DbMigration
{
    /**
     * @inheritDoc
     */
    public function up()
    {
        gdrcd_query("
            CREATE TABLE IF NOT EXISTS moderation_reports (
                id INT AUTO_INCREMENT PRIMARY KEY,
                reporter VARCHAR(50) NOT NULL,
                subject VARCHAR(50) NOT NULL,
                kind ENUM('chat','behavior','content','other') NOT NULL DEFAULT 'other',
                body TEXT NOT NULL,
                context_url VARCHAR(255) NULL,
                status ENUM('pending','under_review','resolved','dismissed') NOT NULL DEFAULT 'pending',
                severity ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
                assigned_to VARCHAR(50) NULL,
                resolution TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                resolved_at DATETIME NULL,
                INDEX idx_status (status),
                INDEX idx_subject (subject),
                INDEX idx_reporter (reporter)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    /**
     * @inheritDoc
     */
    public function down()
    {
        gdrcd_query("DROP TABLE IF EXISTS moderation_reports");
    }
}
