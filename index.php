<?php

header('Content-Type:text/html; charset=UTF-8');

//Includo i parametri, la configurazione, la lingua e le funzioni
require_once('includes/required.php');

//Eseguo la connessione al database
$handleDBConnection = gdrcd_connect();

/**
 * Nel caso stessi utilizzando un sistema di protezione per il sito, prevedo il caricamento della pagina
 * @author Breaker
 */
if ($PARAMETERS['settings']['protection'] == 'ON') {
    require 'protezione.php';
}


if (DbMigrationEngine::dbNeedsInstallation()) {
    /*
     * Fix per installare il database la prima volta.
     */
    gdrcd_redirect("installer.php");
}

/*
 * Definizione pagina da visualizzare
 */
$page = (!empty($_GET['page']) && $_GET['page'] != 'homepage') ? 'homepage__' . gdrcd_filter('include', $_GET['page']) : 'homepage';

/*
 * Definizione dell'eventuale contenuto interno
 * Utile se si vuol mantenere la struttura della homepage quando si aprono i link
 */
$content = (!empty($_GET['content'])) ? gdrcd_filter('include', $_GET['content']) : 'home';


/**
 * Avvio la costruzione dei contenuti della pagina
 * @author Kasa
 */
?>
<!DOCTYPE html>
<html xml:lang="it" lang="it">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link rel="shortcut icon" href="imgs/favicon.ico" type="image/png"/>
    <link rel="stylesheet" href="/themes/tailwind/output.css" type="text/css"/>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
    <link rel="manifest" href="/manifest.webmanifest"/>
    <meta name="theme-color" content="#a47e3b"/>
    <meta name="apple-mobile-web-app-capable" content="yes"/>
    <meta name="apple-mobile-web-app-status-bar-style" content="default"/>
    <meta name="apple-mobile-web-app-title" content="GDRCD"/>
    <title><?= htmlspecialchars($PARAMETERS['info']['site_name']) ?></title>
</head>
<body class="bg-gdrcd-bg text-gdrcd-text font-sans min-h-screen flex flex-col">
<?php

// Includo la pagina
gdrcd_load_modules($page, ['content' => $content]);

require 'footer.inc.php';
