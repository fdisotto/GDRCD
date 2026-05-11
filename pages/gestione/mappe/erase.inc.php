<?php
/**
 * Handler POST: cancella una mappa cliccabile.
 * Vincoli: non si può cancellare la mappa principale né l'ultima rimasta.
 */

if (!gdrcd_controllo_permessi($PARAMETERS['administration']['maps']['access_level'])) {
    return;
}

$lbl_m  = $MESSAGE['interface']['administration']['maps'];
$id     = gdrcd_filter('num', $_POST['id_click'] ?? 0);
$errors = [];

$check_main = gdrcd_query("SELECT principale FROM mappa_click WHERE id_click = " . $id . " LIMIT 1");
if ((int)($check_main['principale'] ?? 0) === 1) {
    $errors[] = gdrcd_filter('out', $lbl_m['no_erase_main']);
}

$count_row = gdrcd_query("SELECT COUNT(*) AS c FROM mappa_click");
if ((int)$count_row['c'] === 1) {
    $errors[] = gdrcd_filter('out', $lbl_m['no_erase_last']);
}

if (!empty($errors)): ?>
    <div class="gdrcd-alert-error">
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
        <ul class="space-y-1 list-disc list-inside">
            <?php foreach ($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php else:
    gdrcd_query("DELETE FROM mappa_click WHERE id_click = " . $id . " LIMIT 1");
    ?>
    <div class="gdrcd-alert-success">
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        <div><?= gdrcd_filter('out', $MESSAGE['warning']['deleted']) ?></div>
    </div>
<?php endif;
