<?php

/**
 * Aggiunge indici di performance sulle tabelle "calde" lette molto frequentemente
 * (chat, personaggio, log, messaggi, segnalazione_role, clgpersonaggio*, backmessaggi).
 *
 * Tutti gli indici sono idempotenti: la presenza viene verificata via SHOW INDEX
 * prima dell'esecuzione di CREATE INDEX / ALTER TABLE ADD INDEX, così la migrazione
 * resta sicura anche se eseguita su DB dove alcuni indici esistono già.
 */
class GDRCDPerformanceIndexes extends DbMigration
{
    /**
     * Elenco indici da gestire: [tabella, nome_indice, colonne]
     *
     * @var array<int, array{table:string, name:string, cols:string}>
     */
    private $indexes = [
        // Chat poll: WHERE stanza = X AND ora > NOW() - 30min ORDER BY id
        ['table' => 'chat',                   'name' => 'idx_chat_stanza_ora',                'cols' => '`stanza`, `ora`'],
        // Presenti / presenti_estesi: DATE_ADD(ultimo_refresh, INTERVAL 4 MINUTE) > NOW()
        ['table' => 'personaggio',            'name' => 'idx_personaggio_ultimo_refresh',     'cols' => '`ultimo_refresh`'],
        // scheda_log: WHERE nome_interessato = X AND codice_evento = Y ORDER BY data_evento DESC
        ['table' => 'log',                    'name' => 'idx_log_pg_evento_data',             'cols' => '`nome_interessato`, `codice_evento`, `data_evento`'],
        // Posta in arrivo: WHERE destinatario = X ORDER BY spedito DESC
        ['table' => 'messaggi',               'name' => 'idx_messaggi_destinatario_spedito',  'cols' => '`destinatario`, `spedito`'],
        // Scheda role: WHERE mittente = X AND YEAR(data_inizio) = Y
        ['table' => 'segnalazione_role',      'name' => 'idx_segnrole_mittente_data',         'cols' => '`mittente`, `data_inizio`'],
        // Inventario PG (frame_chat azioni oggetto): WHERE nome = X AND posizione > 0
        ['table' => 'clgpersonaggiooggetto',  'name' => 'idx_clgpgoggetto_nome_posizione',    'cols' => '`nome`, `posizione`'],
        // Banca / ruoli: WHERE personaggio = X (tabella priva di indici)
        ['table' => 'clgpersonaggioruolo',    'name' => 'idx_clgpgruolo_personaggio',         'cols' => '`personaggio`'],
        // scheda_log spymessages: WHERE mittente = X ORDER BY spedito DESC
        ['table' => 'backmessaggi',           'name' => 'idx_backmessaggi_mittente_spedito',  'cols' => '`mittente`, `spedito`'],
    ];

    /**
     * Verifica se un indice esiste su una tabella.
     */
    private function indexExists(string $table, string $name): bool
    {
        $row = gdrcd_query(
            "SHOW INDEX FROM `" . $table . "` WHERE Key_name = '" . $name . "'",
            'result'
        );
        $exists = (gdrcd_query($row, 'num_rows') > 0);
        gdrcd_query($row, 'free');
        return $exists;
    }

    /**
     * @inheritDoc
     */
    public function up()
    {
        foreach ($this->indexes as $idx) {
            if (!$this->indexExists($idx['table'], $idx['name'])) {
                gdrcd_query(
                    "ALTER TABLE `" . $idx['table'] . "` ADD INDEX `" . $idx['name'] . "` (" . $idx['cols'] . ")"
                );
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function down()
    {
        foreach ($this->indexes as $idx) {
            if ($this->indexExists($idx['table'], $idx['name'])) {
                gdrcd_query(
                    "ALTER TABLE `" . $idx['table'] . "` DROP INDEX `" . $idx['name'] . "`"
                );
            }
        }
    }
}
