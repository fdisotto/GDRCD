<?php

/**
 * Aggiunge supporto a ban temporanei sulla tabella `blacklist`.
 *
 * Schema: nuova colonna `expires_at DATETIME NULL` + indice di copertura
 * `idx_blacklist_expires` su (granted, expires_at) per consentire al
 * controllo di login (login.php) di filtrare in modo efficiente i ban
 * ancora attivi:
 *
 *   SELECT 1 FROM blacklist
 *    WHERE ip = ? AND granted = 0
 *      AND (expires_at IS NULL OR expires_at > NOW());
 *
 * Convenzione: expires_at NULL = ban permanente; valore in futuro = ban
 * a tempo (scade quando NOW() supera la data); valore nel passato = ban
 * scaduto, non piu' applicato dal login.
 *
 * Idempotente: colonna e indice sono verificati via INFORMATION_SCHEMA
 * prima dell'ALTER, cosi' la migrazione resta sicura anche se eseguita
 * piu' volte (es: re-run dopo intervento manuale).
 */
class GDRCDBlacklistExpiry extends DbMigration
{
    /**
     * Verifica se una colonna esiste su una tabella nel DB corrente.
     */
    private function columnExists(string $table, string $column): bool
    {
        $table_q  = gdrcd_filter('in', $table);
        $column_q = gdrcd_filter('in', $column);
        $rs = gdrcd_query(
            "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS "
            . "WHERE TABLE_SCHEMA = DATABASE() "
            . "AND TABLE_NAME = '" . $table_q . "' "
            . "AND COLUMN_NAME = '" . $column_q . "' LIMIT 1",
            'result'
        );
        $exists = (gdrcd_query($rs, 'num_rows') > 0);
        gdrcd_query($rs, 'free');
        return $exists;
    }

    /**
     * Verifica se un indice esiste su una tabella.
     */
    private function indexExists(string $table, string $name): bool
    {
        $rs = gdrcd_query(
            "SHOW INDEX FROM `" . $table . "` WHERE Key_name = '" . $name . "'",
            'result'
        );
        $exists = (gdrcd_query($rs, 'num_rows') > 0);
        gdrcd_query($rs, 'free');
        return $exists;
    }

    /**
     * @inheritDoc
     */
    public function up()
    {
        if (!$this->columnExists('blacklist', 'expires_at')) {
            gdrcd_query(
                "ALTER TABLE `blacklist` ADD COLUMN `expires_at` DATETIME NULL DEFAULT NULL"
            );
        }

        if (!$this->indexExists('blacklist', 'idx_blacklist_expires')) {
            gdrcd_query(
                "ALTER TABLE `blacklist` ADD INDEX `idx_blacklist_expires` (`granted`, `expires_at`)"
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function down()
    {
        if ($this->indexExists('blacklist', 'idx_blacklist_expires')) {
            gdrcd_query("ALTER TABLE `blacklist` DROP INDEX `idx_blacklist_expires`");
        }

        if ($this->columnExists('blacklist', 'expires_at')) {
            gdrcd_query("ALTER TABLE `blacklist` DROP COLUMN `expires_at`");
        }
    }
}
