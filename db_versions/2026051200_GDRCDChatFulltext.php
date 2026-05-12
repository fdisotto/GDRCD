<?php
/**
 * Indice FULLTEXT su chat.testo per ricerca testuale rapida del log chat
 * (vedi pages/log_chat.inc.php tab "Ricerca testuale").
 *
 * Idempotente: l'engine MyISAM/InnoDB di MariaDB 10.0+ supporta FULLTEXT
 * su entrambi. La CREATE INDEX IF NOT EXISTS non esiste su tutte le
 * versioni: usiamo information_schema per evitare di duplicare l'indice.
 */
class GDRCDChatFulltext extends DbMigration
{
    public function up()
    {
        $row = gdrcd_query(
            "SELECT COUNT(*) AS n FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name   = 'chat'
               AND index_name   = 'ft_chat_testo'"
        );
        if ((int)($row['n'] ?? 0) > 0) {
            return;
        }
        gdrcd_query("ALTER TABLE chat ADD FULLTEXT INDEX ft_chat_testo (testo)");
    }

    public function down()
    {
        $row = gdrcd_query(
            "SELECT COUNT(*) AS n FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name   = 'chat'
               AND index_name   = 'ft_chat_testo'"
        );
        if ((int)($row['n'] ?? 0) === 0) {
            return;
        }
        gdrcd_query("ALTER TABLE chat DROP INDEX ft_chat_testo");
    }
}
