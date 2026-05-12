<?php
/**
 * Logout — chiude sessione e aggiorna ora_uscita PG.
 */

require 'includes/required.php';

$handleDBConnection = gdrcd_connect();

$user = $_SESSION['login'] ?? '';
if ($user !== '') {
    gdrcd_query("UPDATE personaggio SET ora_uscita = NOW()
                 WHERE nome = '" . gdrcd_filter('in', $user) . "'");
    gdrcd_log_info('user logout', array(
        'username' => $user,
        'ip'       => $_SERVER['REMOTE_ADDR'] ?? null,
    ));
}

$theme = htmlspecialchars($PARAMETERS['themes']['current_theme']);
$home_name = gdrcd_filter('out', $PARAMETERS['info']['homepage_name'] ?? 'Homepage');
$site_name = htmlspecialchars($PARAMETERS['info']['site_name'] ?? '');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
        (function() {
            try {
                var saved = localStorage.getItem('gdrcd_theme');
                if (saved === 'dark') document.documentElement.classList.add('dark');
            } catch (e) {}
        })();
    </script>
    <title>Logout · <?= $site_name ?></title>
    <link rel="stylesheet" href="themes/<?= $theme ?>/main.css" type="text/css">
    <link rel="stylesheet" href="themes/tailwind/output.css" type="text/css">
    <link rel="shortcut icon" href="imgs/favicon.ico">
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#a47e3b">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="GDRCD">
</head>
<body class="min-h-screen bg-gdrcd-bg flex items-center justify-center px-4 py-10 dark:bg-gdrcd-dark-bg dark:text-gdrcd-dark-text">

    <main class="gdrcd-card max-w-md w-full text-center space-y-5 p-8">
        <div class="flex justify-center">
            <span class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gdrcd-accent-soft text-gdrcd-accent">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
            </span>
        </div>

        <header class="space-y-1">
            <?php if ($user !== ''): ?>
                <h1 class="font-display text-2xl text-gdrcd-text">
                    <?= gdrcd_filter('out', $user) ?>
                </h1>
            <?php endif; ?>
            <p class="text-gdrcd-text-soft">
                <?= gdrcd_filter('out', $MESSAGE['logout']['confirmation']) ?>
            </p>
        </header>

        <div class="text-sm text-gdrcd-text-soft">
            <?= gdrcd_filter('out', $MESSAGE['logout']['greeting']) ?>
        </div>

        <div class="pt-2 border-t border-gdrcd-border">
            <a href="index.php" class="gdrcd-btn-primary inline-flex">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <?= gdrcd_filter('out', $MESSAGE['logout']['logbackin']) ?> <?= $home_name ?>
            </a>
        </div>
    </main>

    <script src="/includes/pwa.js" defer></script>
</body>
</html>
<?php
gdrcd_close_connection($handleDBConnection);

unset($MESSAGE, $PARAMETERS);

session_unset();
session_destroy();
?>
