<?php

/**
 * Crea la tabella `config_settings` per memorizzare override delle costanti
 * "feature gate" (ROLE_PERM, LOG_PERM, EDIT_PERM, SEND_GM, SAVE_ROLE, REG_MIN_AZIONI)
 * gestibili da admin tramite UI, in alternativa alla modifica diretta di
 * `includes/constant_values.inc.php`.
 *
 * Il loader `gdrcd_config_get()` (vedi includes/functions.inc.php) legge i valori
 * da questa tabella con caching per-request e fallback al default costante.
 */
class GDRCDConfigSettings extends DbMigration
{
    /**
     * @inheritDoc
     */
    public function up()
    {
        gdrcd_query("
            CREATE TABLE IF NOT EXISTS config_settings (
                setting_key   VARCHAR(64) NOT NULL,
                setting_value TEXT NULL,
                setting_type  VARCHAR(16) NOT NULL DEFAULT 'string',
                description   VARCHAR(255) NULL,
                updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Seed dei valori di default: corrispondono ai default attuali in
        // constant_values.inc.php (ROLE_PERM = LOG_PERM = EDIT_PERM = GAMEMASTER = 2,
        // SEND_GM = SAVE_ROLE = true, REG_MIN_AZIONI = 4).
        $rows = [
            ['role_perm',      '2', 'int',  'Livello minimo permesso per gestire registrazioni role (default GAMEMASTER)'],
            ['log_perm',       '2', 'int',  'Livello minimo permesso per accedere ai log chat (default GAMEMASTER)'],
            ['edit_perm',      '2', 'int',  'Livello minimo permesso per modificare registrazioni role oltre i 30 giorni (default GAMEMASTER)'],
            ['send_gm',        '1', 'bool', 'Abilita la funzione "Segnala ai Master" nelle giocate'],
            ['save_role',      '1', 'bool', 'Abilita il download della giocata in HTML'],
            ['reg_min_azioni', '4', 'int',  'Numero minimo di azioni per validare una registrazione di giocata'],
        ];

        foreach ($rows as $r) {
            $key  = gdrcd_filter('in', $r[0]);
            $val  = gdrcd_filter('in', $r[1]);
            $type = gdrcd_filter('in', $r[2]);
            $desc = gdrcd_filter('in', $r[3]);
            gdrcd_query(
                "INSERT IGNORE INTO config_settings (setting_key, setting_value, setting_type, description) VALUES ("
                . "'" . $key . "', '" . $val . "', '" . $type . "', '" . $desc . "')"
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function down()
    {
        gdrcd_query("DROP TABLE IF EXISTS config_settings");
    }
}
