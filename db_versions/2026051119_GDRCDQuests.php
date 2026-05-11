<?php

/**
 * Sistema quest: tabelle `quest` (definizioni) e `clgquestpg` (assegnazione
 * PG <-> quest con stato).
 *
 *   - `quest` contiene la definizione testuale di una quest (titolo,
 *     descrizione, obiettivo, ricompensa) creata/curata dal GM.
 *   - `clgquestpg` lega una quest a uno o piu' personaggi e ne traccia
 *     lo stato individuale (attiva / completata / fallita) con timestamp
 *     di assegnazione, di conclusione e note dell'admin.
 *
 * La relazione e' UNIQUE su (id_quest, personaggio) per evitare doppie
 * assegnazioni della stessa quest allo stesso PG.
 *
 * Idempotente: le CREATE TABLE usano IF NOT EXISTS, quindi la migrazione
 * resta sicura se rieseguita.
 *
 * Pagine collegate:
 *   - PG:    main.php?page=scheda_quest&pg=<nome>
 *   - Admin: main.php?page=gestione/quests (GAMEMASTER)
 */
class GDRCDQuests extends DbMigration
{
    /**
     * @inheritDoc
     */
    public function up()
    {
        gdrcd_query("
            CREATE TABLE IF NOT EXISTS quest (
                id_quest    INT AUTO_INCREMENT PRIMARY KEY,
                titolo      VARCHAR(255) NOT NULL,
                descrizione TEXT NOT NULL,
                obiettivo   TEXT NULL,
                ricompensa  TEXT NULL,
                autore      VARCHAR(50) NOT NULL,
                creata_il   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                attiva      TINYINT(1) NOT NULL DEFAULT 1,
                INDEX idx_attiva (attiva)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        gdrcd_query("
            CREATE TABLE IF NOT EXISTS clgquestpg (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                id_quest      INT NOT NULL,
                personaggio   VARCHAR(50) NOT NULL,
                status        ENUM('attiva','completata','fallita') NOT NULL DEFAULT 'attiva',
                assegnata_il  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                conclusa_il   DATETIME NULL,
                note          TEXT NULL,
                UNIQUE KEY uq_quest_pg (id_quest, personaggio),
                INDEX idx_pg_status (personaggio, status),
                FOREIGN KEY (id_quest) REFERENCES quest(id_quest) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    /**
     * @inheritDoc
     */
    public function down()
    {
        // L'ordine e' importante: drop prima la tabella figlia (FK).
        gdrcd_query("DROP TABLE IF EXISTS clgquestpg");
        gdrcd_query("DROP TABLE IF EXISTS quest");
    }
}
