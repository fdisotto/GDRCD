<?php
/**
 * Scheda PG — giocate registrate (dispatcher).
 */

if (!isset($_REQUEST['pg'])) {
    echo '<div class="gdrcd-alert-error">' . gdrcd_filter('out', $MESSAGE['error']['unknown_character_sheet']) . '</div>';
    return;
}

$post_op = gdrcd_filter_get($_POST['op'] ?? '');

$actions = [
    'search'       => 'scheda/roles/search.inc.php',
    'send_edit'    => 'scheda/roles/edit.inc.php',
    'edit'         => 'scheda/roles/edit.inc.php',
    'register'     => 'scheda/roles/register.inc.php',
    'send_segn'    => 'scheda/roles/send_reg.inc.php',
    'log'          => 'scheda/roles/log.inc.php',
    'segnala_send' => 'scheda/roles/segnala.inc.php',
    'segnala'      => 'scheda/roles/segnala.inc.php',
];
?>

<div class="space-y-6">
    <header class="space-y-2 flex flex-wrap items-end justify-between gap-3">
        <h2 class="gdrcd-h1 flex items-center gap-3">
            <span class="gdrcd-icon-circle">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </span>
            <span>
                Giocate registrate
                <span class="text-gdrcd-accent">·</span>
                <span class="text-gdrcd-text-soft text-2xl"><?= gdrcd_filter('out', $_REQUEST['pg']) ?></span>
            </span>
        </h2>
    </header>

    <nav class="flex flex-wrap gap-2 border-b border-gdrcd-border pb-3" aria-label="Sezioni scheda">
        <?php include 'scheda/menu.inc.php'; ?>
    </nav>

    <?php
    if (isset($actions[$post_op])) {
        include $actions[$post_op];
    } else {
        include 'scheda/roles/index.inc.php';
    }
    ?>
</div>
