<?php
declare(strict_types=1);

/**
 * GDRCD - Logger applicativo strutturato.
 *
 * Logger file-based PSR-3 inspired, senza dipendenze esterne.
 * Centralizza il logging di errori applicativi, eventi di sicurezza, warning
 * di deprecazione e query lente, separandoli dalla tabella `log` del DB che
 * resta dedicata agli eventi di dominio del gioco.
 *
 * Configurazione (opzionale) tramite $PARAMETERS['logger']:
 *  - 'path'      : percorso del file di log (default logs/app.log a fianco del progetto)
 *  - 'min_level' : livello minimo da registrare (default 'info')
 *  - 'max_size'  : dimensione massima file in byte prima della rotazione (default 10MB)
 *  - 'max_files' : numero di file storici da conservare (default 5)
 *  - 'enabled'   : false per disabilitare totalmente il logging (default true)
 *
 * Livelli supportati (in ordine crescente di gravità):
 *   debug, info, notice, warning, error, critical, alert, emergency
 */

if (!defined('GDRCD_LOG_DEBUG')) {
    define('GDRCD_LOG_DEBUG',     'debug');
    define('GDRCD_LOG_INFO',      'info');
    define('GDRCD_LOG_NOTICE',    'notice');
    define('GDRCD_LOG_WARNING',   'warning');
    define('GDRCD_LOG_ERROR',     'error');
    define('GDRCD_LOG_CRITICAL',  'critical');
    define('GDRCD_LOG_ALERT',     'alert');
    define('GDRCD_LOG_EMERGENCY', 'emergency');
}

/**
 * Ritorna la mappa livello => peso numerico per il filtro min_level.
 *
 * @return array<string,int>
 */
function gdrcd_log_levels(): array
{
    return array(
        'debug'     => 100,
        'info'      => 200,
        'notice'    => 250,
        'warning'   => 300,
        'error'     => 400,
        'critical'  => 500,
        'alert'     => 550,
        'emergency' => 600,
    );
}

/**
 * Ritorna (e cache-a per request) la configurazione effettiva del logger.
 *
 * @return array{path:string,min_level:string,max_size:int,max_files:int,enabled:bool}
 */
function gdrcd_log_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }

    $defaults = array(
        'path'      => dirname(__FILE__) . '/../logs/app.log',
        'min_level' => 'info',
        'max_size'  => 10 * 1024 * 1024, // 10 MB
        'max_files' => 5,
        'enabled'   => true,
    );

    $user = array();
    if (isset($GLOBALS['PARAMETERS']['logger']) && is_array($GLOBALS['PARAMETERS']['logger'])) {
        $user = $GLOBALS['PARAMETERS']['logger'];
    }

    $cfg = array_merge($defaults, $user);
    $cfg['min_level'] = strtolower((string)$cfg['min_level']);
    $cfg['max_size']  = (int)$cfg['max_size'];
    $cfg['max_files'] = (int)$cfg['max_files'];
    $cfg['enabled']   = (bool)$cfg['enabled'];

    $levels = gdrcd_log_levels();
    if (!isset($levels[$cfg['min_level']])) {
        $cfg['min_level'] = 'info';
    }
    if ($cfg['max_size'] <= 0) {
        $cfg['max_size'] = 10 * 1024 * 1024;
    }
    if ($cfg['max_files'] < 0) {
        $cfg['max_files'] = 5;
    }

    return $cfg;
}

/**
 * Esegue rotazione manuale del file di log se supera la dimensione massima.
 * Mantiene gli ultimi $max_files file storici (.1 .. .N).
 *
 * @param string $path
 * @param int    $maxSize
 * @param int    $maxFiles
 * @return void
 */
function gdrcd_log_rotate_if_needed(string $path, int $maxSize, int $maxFiles): void
{
    if (!file_exists($path)) {
        return;
    }
    $size = @filesize($path);
    if ($size === false || $size < $maxSize) {
        return;
    }

    // Elimina il file più vecchio
    $oldest = $path . '.' . $maxFiles;
    if (file_exists($oldest)) {
        @unlink($oldest);
    }
    // Sposta gli intermedi
    for ($i = $maxFiles - 1; $i >= 1; $i--) {
        $src = $path . '.' . $i;
        $dst = $path . '.' . ($i + 1);
        if (file_exists($src)) {
            @rename($src, $dst);
        }
    }
    // Rinomina il corrente
    @rename($path, $path . '.1');
}

/**
 * Codifica il context in JSON sicuro per il log (no eccezioni, no caratteri di
 * controllo che spezzino la grep-ability di una riga).
 *
 * @param array $context
 * @return string
 */
function gdrcd_log_encode_context(array $context): string
{
    if (empty($context)) {
        return '';
    }
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR;
    $json = @json_encode($context, $flags);
    if ($json === false) {
        return '{"_log_encode_error":true}';
    }
    // Normalizza i newline per garantire una sola riga per evento
    return str_replace(array("\r\n", "\r", "\n"), ' ', $json);
}

/**
 * Funzione core: scrive un evento sul file di log se il livello è abilitato.
 * Fallisce silenziosamente in caso di errore (non deve mai rompere la request).
 *
 * @param string $level   debug|info|notice|warning|error|critical|alert|emergency
 * @param string $message messaggio human-readable
 * @param array  $context dati strutturati opzionali (saranno JSON-encoded)
 * @return void
 */
function gdrcd_log(string $level, string $message, array $context = array()): void
{
    $cfg = gdrcd_log_config();
    if (!$cfg['enabled']) {
        return;
    }

    $levels = gdrcd_log_levels();
    $level = strtolower((string)$level);
    if (!isset($levels[$level])) {
        $level = 'info';
    }
    if ($levels[$level] < $levels[$cfg['min_level']]) {
        return;
    }

    $path = $cfg['path'];
    $dir  = dirname($path);
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return; // impossibile creare la directory, esci silenziosamente
        }
    }

    // Rotazione preventiva
    gdrcd_log_rotate_if_needed($path, $cfg['max_size'], $cfg['max_files']);

    // Identità (utente loggato o guest)
    $user = 'guest';
    if (!empty($_SESSION['login'])) {
        $user = (string)$_SESSION['login'];
    }

    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '-';

    $timestamp = date('c'); // ISO 8601 con timezone
    $levelTag  = strtoupper($level);
    $msg = (string)$message;
    // Normalizza newline nel messaggio per mantenere una sola riga
    $msg = str_replace(array("\r\n", "\r", "\n"), ' ', $msg);

    $line = '[' . $timestamp . '] '
          . '[' . $levelTag . '] '
          . '[user=' . $user . '] '
          . '[ip=' . $ip . '] '
          . $msg;

    $ctx = gdrcd_log_encode_context($context);
    if ($ctx !== '') {
        $line .= ' ' . $ctx;
    }
    $line .= PHP_EOL;

    // Scrittura atomica con lock; modalità 0644 al primo write
    $existed = file_exists($path);
    $written = @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    if ($written !== false && !$existed) {
        @chmod($path, 0644);
    }
}

/**
 * Helper di convenienza per i singoli livelli.
 */
function gdrcd_log_debug(string $message, array $context = array()): void
{
    gdrcd_log('debug', $message, $context);
}

function gdrcd_log_info(string $message, array $context = array()): void
{
    gdrcd_log('info', $message, $context);
}

function gdrcd_log_notice(string $message, array $context = array()): void
{
    gdrcd_log('notice', $message, $context);
}

function gdrcd_log_warning(string $message, array $context = array()): void
{
    gdrcd_log('warning', $message, $context);
}

function gdrcd_log_error(string $message, array $context = array()): void
{
    gdrcd_log('error', $message, $context);
}

function gdrcd_log_critical(string $message, array $context = array()): void
{
    gdrcd_log('critical', $message, $context);
}
