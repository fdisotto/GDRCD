<?php
/**
 * Gestione mappe (main.php?page=gestione/mappe)
 * Dispatcher per CRUD su tabella mappa_click.
 */

if (!gdrcd_controllo_permessi($PARAMETERS['administration']['maps']['access_level'])) {
    echo '<div class="gdrcd-alert-error">'
       . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>'
       . '<div>' . gdrcd_filter('out', $MESSAGE['error']['not_allowed']) . '</div>'
       . '</div>';
    return;
}

$post_op = gdrcd_filter_get($_POST['op'] ?? '');
$get_op  = gdrcd_filter_get($_GET['op']  ?? '');
?>

<div class="space-y-6">

    <header class="space-y-2">
        <h2 class="gdrcd-h1"><?= gdrcd_filter('out', $MESSAGE['interface']['administration']['maps']['page_name']) ?></h2>
        <p class="gdrcd-muted">Gestione delle mappe cliccabili di gioco.</p>
    </header>

    <?php
    if ($post_op === 'save') {
        include __DIR__ . '/save.inc.php';
    } elseif ($post_op === 'erase') {
        include __DIR__ . '/erase.inc.php';
    }

    if ($get_op === 'edit') {
        include __DIR__ . '/edit.inc.php';
    } elseif ($get_op === 'create') {
        include __DIR__ . '/create.inc.php';
    } else {
        include __DIR__ . '/view.inc.php';
    }
    ?>

</div>
