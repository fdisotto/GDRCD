<?php
/**
 * Handler POST: elimina (logicamente) i messaggi selezionati via checkbox.
 */

if (empty($_POST['ids'])) {
    return;
}

$ids = array_map(fn($v) => (int)$v, (array)$_POST['ids']);
$ids_csv = implode(',', $ids);

$delType = gdrcd_filter_in($_POST['type'] ?? '');

$column = null;
if ($delType === 'destinatario_del') {
    $column = 'destinatario';
} elseif ($delType === 'mittente_del') {
    $column = 'mittente';
}

if ($column === null) {
    return;
}

gdrcd_query(
    "UPDATE messaggi SET " . $delType . " = 1
     WHERE " . $column . " = '" . gdrcd_filter('in', $_SESSION['login']) . "'
       AND id IN (" . $ids_csv . ")"
);

if ((int)gdrcd_query("", 'affected') > 0): ?>
    <div class="gdrcd-alert-success">
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        <div><?= gdrcd_filter('out', $PARAMETERS['names']['private_message']['plur'] . $MESSAGE['interface']['messages']['all_erased']) ?></div>
    </div>
<?php endif;
