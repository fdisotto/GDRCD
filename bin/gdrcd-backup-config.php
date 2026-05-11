<?php
/**
 * gdrcd-backup-config.php
 *
 * Loads the GDRCD configuration headlessly and emits database credentials
 * (plus a few other useful values) in shell-parseable KEY=VALUE form on
 * stdout. Consumed by bin/gdrcd-backup and bin/gdrcd-restore.
 *
 * Usage:
 *     php bin/gdrcd-backup-config.php
 *
 * Output keys:
 *     DB_HOST, DB_USER, DB_PASS, DB_NAME, GDRCD_VERSION, CURRENT_THEME
 *
 * The script is silent on success (only the variables go to stdout); any
 * loading error is printed to stderr and the process exits non-zero.
 */

// CLI guard.
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This helper must be invoked from the command line.\n");
    exit(2);
}

// Resolve project root: this file lives in <root>/bin/.
$root = dirname(__DIR__);

$configFile    = $root . '/config.inc.php';
$constantsFile = $root . '/includes/constant_values.inc.php';
if (!is_readable($configFile)) {
    fwrite(STDERR, "Cannot read {$configFile}\n");
    exit(3);
}

// config.inc.php peeks at $_SESSION / $_REQUEST near the top; pre-seed them
// so the file loads cleanly under CLI without warnings.
$_SESSION = isset($_SESSION) ? $_SESSION : [];
$_REQUEST = isset($_REQUEST) ? $_REQUEST : [];

// The config file echoes nothing, but be defensive against any stray output
// from required files: capture and discard so our stdout stays clean.
ob_start();
try {
    // Mirror includes/required.php: constants must be defined before
    // config.inc.php is parsed because the latter references some of them
    // (HTML_FILTER_BASE, etc.).
    if (is_readable($constantsFile)) {
        /** @noinspection PhpIncludeInspection */
        include_once $constantsFile;
    }

    /** @noinspection PhpIncludeInspection */
    include $configFile;

    // Mirror the load order used by includes/required.php so env-var based
    // overrides (Docker entrypoint) win over the static defaults.
    $overrides = $root . '/includes/config-overrides.php';
    if (file_exists($overrides)) {
        /** @noinspection PhpIncludeInspection */
        include $overrides;
    }
} catch (\Throwable $e) {
    ob_end_clean();
    fwrite(STDERR, "Failed to load config: " . $e->getMessage() . "\n");
    exit(4);
}
ob_end_clean();

if (!isset($PARAMETERS) || !is_array($PARAMETERS)) {
    fwrite(STDERR, "config.inc.php did not define \$PARAMETERS\n");
    exit(5);
}

$dbHost  = $PARAMETERS['database']['url']           ?? '';
$dbUser  = $PARAMETERS['database']['username']      ?? '';
$dbPass  = $PARAMETERS['database']['password']      ?? '';
$dbName  = $PARAMETERS['database']['database_name'] ?? '';
$version = $PARAMETERS['info']['GDRCD']             ?? 'unknown';
$theme   = $PARAMETERS['themes']['current_theme']   ?? 'advanced';

// Single-quote escape for shell safety: replace any ' with '\''.
$shellEscape = static function (string $value): string {
    return "'" . str_replace("'", "'\\''", $value) . "'";
};

printf("DB_HOST=%s\n",        $shellEscape($dbHost));
printf("DB_USER=%s\n",        $shellEscape($dbUser));
printf("DB_PASS=%s\n",        $shellEscape($dbPass));
printf("DB_NAME=%s\n",        $shellEscape($dbName));
printf("GDRCD_VERSION=%s\n",  $shellEscape((string)$version));
printf("CURRENT_THEME=%s\n",  $shellEscape((string)$theme));

exit(0);
