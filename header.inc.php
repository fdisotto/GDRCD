<?php

header('Content-Type:text/html; charset=UTF-8');

//Includo i parametri, la configurazione, la lingua e le funzioni
require_once('includes/required.php');

//Eseguo la connessione al database
$handleDBConnection = gdrcd_connect();

# Controllo del login
if(!empty($_SESSION['login'])){
    $me = gdrcd_filter('in',$_SESSION['login']);

    $table_check = gdrcd_query("SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '{$PARAMETERS['database']['database_name']}' AND TABLE_NAME = 'personaggio';");

    if(!empty($table_check)) {

        $check = gdrcd_query("SELECT count(nome) as TOT FROM personaggio WHERE ora_entrata > ora_uscita AND nome='{$me}' LIMIT 1");

        if ($check['TOT'] == 0) {
            session_destroy();
            die('Non sei collegato con nessun pg.');
        }
    }
    else{
        session_destroy();
    }

}

/** * CONTROLLO PER AGGIORNAMENTO DB
 * Il controllo viene lanciato solo in index e nelle pagine di installer/upgrade.
 * Dopo l'aggiornamento non dovrebbe dare noie.
 * Nel qual caso vogliate risparmiare risorse quando si visita la homepage però è possibile modificare la variabile $check_for_update in index.php e settarla a FALSE.
 * @author Blancks
 */
if(isset($check_for_update) && $check_for_update) {
    include('upgrade_details.php');
}
/** * Fine controllo di update */

/**    * Caricamento plugins.
 * I plugins non sono vitali all'esecuzione dell'engine, per cui si includono col comando include.
 * @author Blancks
 */

/* Caricamento bbdecoder */
if(($PARAMETERS['mode']['user_bbcode'] == 'ON' && $PARAMETERS['settings']['user_bbcode']['type'] == 'bbd') || $PARAMETERS['settings']['forum_bbcode']['type'] == 'bbd') {
    include('plugins/bbdecoder/bbdecoder.php');
}

?>
<!DOCTYPE html>
<html xml:lang="it" lang="it">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <script>
        // Apply saved theme before paint to avoid FOUC.
        (function() {
            try {
                var saved = localStorage.getItem('gdrcd_theme');
                if (saved === 'dark') document.documentElement.classList.add('dark');
            } catch (e) {}
        })();
    </script>
    <link rel="shortcut icon" href="imgs/favicon.ico" type="image/png" />
    <link rel="stylesheet" href="/themes/tailwind/output.css" type="text/css" />
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#a47e3b">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="GDRCD">
    <?php
    // Web Push: espone la VAPID public key al client (includes/notifications.js)
    // solo se configurata; vuota -> il client salta la subscription push.
    $__gdrcd_vapid_pub = (string)($PARAMETERS['push']['vapid_public'] ?? '');
    if ($__gdrcd_vapid_pub !== ''):
    ?>
    <meta name="gdrcd-vapid-public" content="<?= htmlspecialchars($__gdrcd_vapid_pub, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php
    // WebSocket URL globale per i canali chat/notifications/presenti.
    // Vuoto -> client resta in polling-only.
    $__gdrcd_ws_url = '';
    if (!empty($PARAMETERS['websocket']['enabled'])) {
        $__gdrcd_ws_url = isset($PARAMETERS['websocket']['url'])
            ? (string)$PARAMETERS['websocket']['url']
            : '';
        if ($__gdrcd_ws_url === '') {
            $__gdrcd_scheme = (
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            ) ? 'wss' : 'ws';
            $__gdrcd_host = preg_replace('/:.*/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $__gdrcd_ws_url = $__gdrcd_scheme . '://' . $__gdrcd_host . ':8082';
        }
    }
    if ($__gdrcd_ws_url !== ''):
    ?>
    <meta name="gdrcd-ws-url" content="<?= htmlspecialchars($__gdrcd_ws_url, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <title><?= htmlspecialchars($PARAMETERS['info']['site_name']) ?></title>
</head>
<body class="bg-gdrcd-bg text-gdrcd-text font-sans min-h-screen flex flex-col dark:bg-gdrcd-dark-bg dark:text-gdrcd-dark-text"
      data-login="<?= htmlspecialchars($_SESSION['login'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
<?php
/** * CONTROLLO PER AGGIORNAMENTO DB
 * Il controllo viene lanciato solo in index e nelle pagine di installer/upgrade.
 * Dopo l'aggiornamento non dovrebbe dare noie.
 * Nel qual caso vogliate risparmiare risorse quando si visita la homepage però è possibile modificare la variabile $check_for_update in index.php e settarla a FALSE.
 * @author Blancks
 */
if((($table == 0) && isset($dont_check) && ! $dont_check) && isset($check_for_update) && $check_for_update) {
    echo '<div class="gdrcd-shell"><div class="gdrcd-container-sm"><div class="gdrcd-alert-error">',
         gdrcd_filter_out($MESSAGE['error']['db_empty']),
         '</div><div class="mt-4 text-center"><a class="gdrcd-btn-primary" href="installer.php">',
         gdrcd_filter_out($MESSAGE['installer']['instal']),
         '</a></div></div></div></body></html>';
    exit();

} elseif((isset($updating_queryes[0]) && ! empty($updating_queryes[0]) && ! $dont_check) && isset($check_for_update) && $check_for_update) {
    echo '<div class="gdrcd-shell"><div class="gdrcd-container-sm space-y-3">',
         '<div class="gdrcd-alert-error">', gdrcd_filter_out($MESSAGE['error']['db_not_updated']), '</div>';

    if($updating_password) {
        echo '<div class="gdrcd-alert-warning">', gdrcd_filter_out($MESSAGE['warning']['pass_not_encripted']), '</div>';
    }

    echo '<div class="text-center"><a class="gdrcd-btn-primary" href="upgrade.php">',
         gdrcd_filter_out($MESSAGE['homepage']['updater']['update']),
         '</a></div></div></div></body></html>';

    exit();
}
?>