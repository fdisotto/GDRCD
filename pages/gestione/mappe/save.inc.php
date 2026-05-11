<?php
/**
 * Handler POST: salva (create o update) una mappa cliccabile.
 */

if (!gdrcd_controllo_permessi($PARAMETERS['administration']['maps']['access_level'])) {
    return;
}

$id_click  = gdrcd_filter('num', $_POST['id_click'] ?? 0);
$is_mobile = (($_POST['mobile']     ?? '') === 'is_mobile') ? 1 : 0;
$is_main   = (($_POST['principale'] ?? '') === 'is_main')   ? 1 : 0;
$immagine  = empty($_POST['immagine']) ? 'standard_mappa.png' : gdrcd_filter('in', $_POST['immagine']);

// Se la mappa è principale, smarca le altre
if ($is_main === 1) {
    gdrcd_query("UPDATE mappa_click SET principale = 0 WHERE principale = 1");
}

if ((int)$id_click === 0) {
    gdrcd_query(
        "INSERT INTO mappa_click (nome, posizione, mobile, principale, immagine, larghezza, altezza) VALUES ("
        . "'" . gdrcd_filter('in', $_POST['nome']) . "',"
        . gdrcd_filter('num', $_POST['posizione']) . ","
        . $is_mobile . ","
        . $is_main . ","
        . "'" . $immagine . "',"
        . gdrcd_filter('num', $_POST['larghezza']) . ","
        . gdrcd_filter('num', $_POST['altezza']) . ")"
    );
    $msg = $MESSAGE['warning']['inserted'];
} else {
    gdrcd_query(
        "UPDATE mappa_click SET
            nome = '" . gdrcd_filter('in', $_POST['nome']) . "',
            mobile = " . $is_mobile . ",
            principale = " . $is_main . ",
            immagine = '" . $immagine . "',
            posizione = " . gdrcd_filter('num', $_POST['posizione']) . ",
            larghezza = " . gdrcd_filter('num', $_POST['larghezza']) . ",
            altezza = " . gdrcd_filter('num', $_POST['altezza']) . "
         WHERE id_click = " . (int)$id_click . " LIMIT 1"
    );
    $msg = $MESSAGE['warning']['modified'];
}
?>

<div class="gdrcd-alert-success">
    <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
    <div><?= gdrcd_filter('out', $msg) ?></div>
</div>
