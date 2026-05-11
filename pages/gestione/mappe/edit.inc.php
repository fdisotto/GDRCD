<?php
/**
 * Form modifica mappa esistente.
 */

if (!gdrcd_controllo_permessi($PARAMETERS['administration']['maps']['access_level'])) {
    return;
}

$lbl_m = $MESSAGE['interface']['administration']['maps'];

$results = gdrcd_query(
    "SELECT * FROM mappa_click WHERE id_click = " . gdrcd_filter('num', $_POST['id_click'] ?? 0) . " LIMIT 1",
    'result'
);

if (gdrcd_query($results, 'num_rows') === 0): ?>
    <div class="gdrcd-alert-error">
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
        <div><?= gdrcd_filter('out', $MESSAGE['error']['unknown']) ?></div>
    </div>
    <div class="mt-3">
        <a href="main.php?page=gestione/mappe" class="gdrcd-btn-ghost">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <?= gdrcd_filter('out', $lbl_m['link']['back']) ?>
        </a>
    </div>
<?php
    return;
endif;

$record = gdrcd_query($results, 'fetch');
gdrcd_query($results, 'free');
$is_edit = true;
include __DIR__ . '/_form.inc.php';
