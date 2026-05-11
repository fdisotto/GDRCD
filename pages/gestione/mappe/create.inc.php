<?php
/**
 * Form creazione nuova mappa.
 */

if (!gdrcd_controllo_permessi($PARAMETERS['administration']['maps']['access_level'])) {
    return;
}

$lbl_m  = $MESSAGE['interface']['administration']['maps'];
$record = [
    'id_click'   => 0,
    'nome'       => $_POST['nome']       ?? '',
    'mobile'     => (($_POST['mobile']     ?? '') === 'is_mobile') ? 1 : 0,
    'principale' => (($_POST['principale'] ?? '') === 'is_main')   ? 1 : 0,
    'posizione'  => (int)($_POST['posizione'] ?? 0),
    'immagine'   => $_POST['immagine']   ?? '',
    'larghezza'  => $_POST['larghezza']  ?? '',
    'altezza'    => $_POST['altezza']    ?? '',
];
$is_edit = false;
include __DIR__ . '/_form.inc.php';
