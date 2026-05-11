<?php
/**
 * Scheda PG — diario personale (dispatcher).
 */

if (!isset($_REQUEST['pg'])) {
    echo '<div class="gdrcd-alert-error">' . gdrcd_filter('out', $MESSAGE['error']['unknown_character_sheet']) . '</div>';
    return;
}

$lbl_m = $MESSAGE['interface']['sheet']['menu'];
$post_op = gdrcd_filter_get($_POST['op'] ?? '');

$actions = [
    'view'      => 'scheda/diario/view.inc.php',
    'edit'      => 'scheda/diario/edit.inc.php',
    'new'       => 'scheda/diario/new.inc.php',
    'save_edit' => 'scheda/diario/save.inc.php',
    'delete'    => 'scheda/diario/save.inc.php',
    'save_new'  => 'scheda/diario/save.inc.php',
];
?>

<div class="space-y-6">
    <header class="space-y-2">
        <h2 class="gdrcd-h1">
            <?= gdrcd_filter('out', $lbl_m['diary']) ?>
            <span class="text-gdrcd-accent">·</span>
            <span class="text-gdrcd-text-soft text-2xl"><?= gdrcd_filter('out', $_REQUEST['pg']) ?></span>
        </h2>
    </header>

    <nav class="flex flex-wrap gap-2 border-b border-gdrcd-border pb-3" aria-label="Sezioni scheda">
        <?php include 'scheda/menu.inc.php'; ?>
    </nav>

    <?php
    if (isset($actions[$post_op])) {
        include $actions[$post_op];
    } else {
        include 'scheda/diario/index.inc.php';
    }
    ?>
</div>
