<?php
/**
 * Handler POST: elimina tutti i messaggi letti (lato destinatario o mittente).
 */

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

// $delType / $column whitelisted sopra.
Db::preparedExecute(
    "UPDATE messaggi SET " . $delType . " = 1
     WHERE " . $column . " = ?
       AND letto = 1",
    's',
    array((string)$_SESSION['login'])
);
?>

<div class="gdrcd-alert-success">
    <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
    <div><?= gdrcd_filter('out', $PARAMETERS['names']['private_message']['plur'] . $MESSAGE['interface']['messages']['all_erased']) ?></div>
</div>
