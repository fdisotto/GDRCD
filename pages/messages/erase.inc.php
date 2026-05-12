<?php
/**
 * Handler POST: elimina (logicamente) un singolo messaggio.
 */

$id_messaggio = gdrcd_filter('num', $_POST['id_messaggio'] ?? 0);
$delType      = gdrcd_filter('in', $_POST['type'] ?? '');

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
$affected = Db::preparedAffected(
    "UPDATE messaggi SET " . $delType . " = 1
          WHERE " . $column . " = ?
            AND id = ?",
    'si',
    array((string)$_SESSION['login'], (int)$id_messaggio)
);

if ($affected > 0): ?>
    <div class="gdrcd-alert-success">
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        <div><?= gdrcd_filter('out', $PARAMETERS['names']['private_message']['sing'] . $MESSAGE['interface']['messages']['erased']) ?></div>
    </div>
<?php else:
    $check = Db::preparedFetch(
        "SELECT id FROM messaggi
         WHERE " . $column . " = ?
           AND id = ? LIMIT 1",
        'si',
        array((string)$_SESSION['login'], (int)$id_messaggio)
    );
    if ($check === null): ?>
        <div class="gdrcd-alert-warning">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
            <div>Il messaggio che stai tentando di cancellare non esiste.</div>
        </div>
    <?php endif;
endif;
