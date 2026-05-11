<?php
/**
 * Manutenzione (main.php?page=gestione/manutenzione)
 * Dispatcher per le operazioni di manutenzione del database.
 */

if (!gdrcd_controllo_permessi($PARAMETERS['administration']['maintenance']['access_level'])) {
    echo '<div class="gdrcd-alert-error">'
       . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>'
       . '<div>' . gdrcd_filter('out', $MESSAGE['error']['not_allowed']) . '</div>'
       . '</div>';
    return;
}

$post_op = gdrcd_filter_get($_POST['op'] ?? '');
$post_actions = [
    'blacklisted'  => 'blacklisted.inc.php',
    'deleted'      => 'deleted.inc.php',
    'old_chat'     => 'old_chat.inc.php',
    'old_log'      => 'old_log.inc.php',
    'old_messages' => 'old_messages.inc.php',
    'missing'      => 'missing.inc.php',
];
?>

<div class="space-y-6">

    <header class="space-y-2">
        <h2 class="gdrcd-h1"><?= gdrcd_filter('out', $MESSAGE['interface']['administration']['maintenance']['page_name']) ?></h2>
        <p class="gdrcd-muted">Operazioni di pulizia e ottimizzazione del database. Le azioni sono irreversibili.</p>
    </header>

    <?php
    if (isset($post_actions[$post_op])) {
        include __DIR__ . '/' . $post_actions[$post_op];
    }

    include __DIR__ . '/view.inc.php';
    ?>

</div>
