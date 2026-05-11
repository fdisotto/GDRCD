<?php
/**
 * gdrcd-migrate-runner.php
 *
 * Entry point CLI per il sistema di migrazioni del DB.
 * Viene normalmente invocato attraverso il wrapper bash `bin/gdrcd-migrate`,
 * ma puo' essere eseguito anche direttamente:
 *
 *   php bin/gdrcd-migrate-runner.php [--status] [--up] [--down=<migration_id>]
 *
 * Flag:
 *   --status        elenca le migrazioni disponibili indicando lo stato
 *   --up (default)  applica tutte le migrazioni pendenti
 *   --down=<id>     riporta il DB alla migrazione indicata (esegue down())
 *   -h, --help      stampa la guida
 *
 * Exit codes:
 *   0  successo
 *   1  errore in fase di esecuzione (eccezione, DB irraggiungibile, ...)
 *   2  argomenti non validi
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "gdrcd-migrate-runner: deve essere eseguito da CLI.\n");
    exit(1);
}

// ----- arg parsing --------------------------------------------------------

$action = 'up';       // default
$target = null;

for ($i = 1; $i < $argc; $i++) {
    $arg = $argv[$i];
    if ($arg === '--status') {
        $action = 'status';
    } elseif ($arg === '--up') {
        $action = 'up';
    } elseif (strpos($arg, '--down=') === 0) {
        $action = 'down';
        $target = substr($arg, strlen('--down='));
        if ($target === '' || !ctype_digit($target)) {
            fwrite(STDERR, "gdrcd-migrate-runner: --down richiede un migration_id numerico.\n");
            exit(2);
        }
    } elseif ($arg === '-h' || $arg === '--help') {
        echo "Usage: php bin/gdrcd-migrate-runner.php [--status] [--up] [--down=<migration_id>]\n";
        exit(0);
    } else {
        fwrite(STDERR, "gdrcd-migrate-runner: argomento sconosciuto: {$arg}\n");
        exit(2);
    }
}

// ----- bootstrap GDRCD ----------------------------------------------------

// Lo script vive in bin/, la root del progetto e' la parent directory.
$root = dirname(__DIR__);
chdir($root);

// includes/required.php a sua volta carica config.inc.php, l'engine e tutte le
// utility necessarie (gdrcd_query, gdrcd_connect, ecc.).
require_once $root . '/includes/required.php';

// Apre la connessione al DB (lo stesso fa index.php / installer.php).
try {
    gdrcd_connect();
} catch (Throwable $e) {
    fwrite(STDERR, "gdrcd-migrate-runner: connessione al DB fallita: " . $e->getMessage() . "\n");
    exit(1);
}

// ----- helpers ------------------------------------------------------------

/**
 * Ritorna la lista dei migration_id gia' applicati a DB.
 * Usa una query diretta perche' i metodi del genere nell'engine sono privati.
 *
 * @return array<string,string> migration_id => applied_on
 */
function gdrcd_migrate_applied_ids()
{
    $applied = [];
    // La tabella e' creata dall'engine; se non esiste ancora, ritorniamo vuoto.
    $check = @gdrcd_query(
        "SELECT COUNT(*) AS n FROM information_schema.TABLES "
        . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '_gdrcd_db_versions'"
    );
    if (empty($check['n'])) {
        return $applied;
    }

    $res = gdrcd_query("SELECT migration_id, applied_on FROM _gdrcd_db_versions ORDER BY migration_id", 'result');
    while ($row = gdrcd_query($res, 'assoc')) {
        $applied[(string)$row['migration_id']] = (string)$row['applied_on'];
    }
    gdrcd_query($res, 'free');
    return $applied;
}

// ----- dispatch -----------------------------------------------------------

try {
    switch ($action) {
        case 'status':
            $migrations = DbMigrationEngine::getAllAvailableMigrations();
            $applied    = gdrcd_migrate_applied_ids();

            echo "Migrazioni disponibili (" . count($migrations) . "):\n\n";
            printf("  %-12s  %-10s  %s\n", 'MIGRATION_ID', 'STATO', 'CLASSE');
            printf("  %-12s  %-10s  %s\n", str_repeat('-', 12), str_repeat('-', 10), str_repeat('-', 30));

            $pending = 0;
            foreach ($migrations as $m) {
                $id  = (string)$m->getMigrationId();
                $cls = get_class($m);
                if (isset($applied[$id])) {
                    printf("  %-12s  %-10s  %s  (applicata il %s)\n",
                        $id, 'applied', $cls, $applied[$id]);
                } else {
                    printf("  %-12s  %-10s  %s\n", $id, 'pending', $cls);
                    $pending++;
                }
            }

            // Migrazioni a DB ma non piu' presenti su disco (orfane).
            $orphan = array_diff_key($applied, array_flip(array_map(
                static function ($m) { return (string)$m->getMigrationId(); },
                $migrations
            )));
            if (!empty($orphan)) {
                echo "\n  Migrazioni applicate ma assenti dai file (orfane):\n";
                foreach ($orphan as $id => $on) {
                    printf("    %s  (applicata il %s)\n", $id, $on);
                }
            }

            echo "\nTotale pendenti: {$pending}\n";
            exit(0);

        case 'up':
            $applied = DbMigrationEngine::updateDbSchema();
            if ($applied === 0) {
                echo "Nessuna migrazione da applicare. Il DB e' gia' aggiornato.\n";
            } else {
                echo "Applicate {$applied} migrazioni.\n";
            }
            exit(0);

        case 'down':
            $applied = DbMigrationEngine::updateDbSchema((int)$target);
            echo "Rollback completato: {$applied} migrazioni invertite (target: {$target}).\n";
            exit(0);
    }
} catch (Throwable $e) {
    fwrite(STDERR, "gdrcd-migrate-runner: errore: " . $e->getMessage() . "\n");
    exit(1);
}

exit(0);
