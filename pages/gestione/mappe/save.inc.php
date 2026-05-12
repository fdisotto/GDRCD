<?php
/**
 * Handler POST: salva (create o update) una mappa cliccabile.
 */

if (!gdrcd_controllo_permessi($PARAMETERS['administration']['maps']['access_level'])) {
    return;
}

$id_click  = (int)gdrcd_filter('num', $_POST['id_click'] ?? 0);
$is_mobile = (($_POST['mobile']     ?? '') === 'is_mobile') ? 1 : 0;
$is_main   = (($_POST['principale'] ?? '') === 'is_main')   ? 1 : 0;
$immagine  = empty($_POST['immagine']) ? 'standard_mappa.png' : (string)$_POST['immagine'];
$nome      = (string)($_POST['nome'] ?? '');
$posizione = (int)gdrcd_filter('num', $_POST['posizione'] ?? 0);
$larghezza = (int)gdrcd_filter('num', $_POST['larghezza'] ?? 0);
$altezza   = (int)gdrcd_filter('num', $_POST['altezza']   ?? 0);

// Se la mappa è principale, smarca le altre
if ($is_main === 1) {
    gdrcd_query("UPDATE mappa_click SET principale = 0 WHERE principale = 1");
}

if ($id_click === 0) {
    Db::preparedExecute(
        "INSERT INTO mappa_click (nome, posizione, mobile, principale, immagine, larghezza, altezza) VALUES (?, ?, ?, ?, ?, ?, ?)",
        'siiisii',
        array($nome, $posizione, $is_mobile, $is_main, $immagine, $larghezza, $altezza)
    );
    $msg = $MESSAGE['warning']['inserted'];
} else {
    Db::preparedExecute(
        "UPDATE mappa_click SET
            nome = ?,
            mobile = ?,
            principale = ?,
            immagine = ?,
            posizione = ?,
            larghezza = ?,
            altezza = ?
         WHERE id_click = ? LIMIT 1",
        'siisiiii',
        array($nome, $is_mobile, $is_main, $immagine, $posizione, $larghezza, $altezza, $id_click)
    );
    $msg = $MESSAGE['warning']['modified'];
}
?>

<div class="gdrcd-alert-success">
    <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
    <div><?= gdrcd_filter('out', $msg) ?></div>
</div>
