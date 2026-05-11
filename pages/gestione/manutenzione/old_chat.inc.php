<?php
if (!gdrcd_controllo_permessi($PARAMETERS['administration']['maintenance']['access_level'])) {
    return;
}

$mesi = $_POST['mesi'] ?? null;
if (!is_numeric($mesi) || $mesi < 0 || $mesi > 12): ?>
    <div class="gdrcd-alert-error">
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
        <div><?= gdrcd_filter('out', $MESSAGE['warning']['cant_do']) ?></div>
    </div>
<?php
    return;
endif;

$m = gdrcd_filter('num', $mesi);
gdrcd_query("DELETE FROM chat WHERE DATE_SUB(NOW(), INTERVAL " . $m . " MONTH) > ora");
gdrcd_query("OPTIMIZE TABLE chat");
?>
<div class="gdrcd-alert-success">
    <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
    <div>
        <strong>Log chat puliti</strong> (più vecchi di <?= (int)$m ?> mesi).
        <span class="text-gdrcd-muted">·</span>
        <?= gdrcd_filter('out', $MESSAGE['warning']['modified']) ?>
    </div>
</div>
