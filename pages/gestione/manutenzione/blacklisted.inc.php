<?php
if (!gdrcd_controllo_permessi($PARAMETERS['administration']['maintenance']['access_level'])) {
    return;
}

gdrcd_query("DELETE FROM blacklist WHERE 1");
gdrcd_query("OPTIMIZE TABLE blacklist");
?>
<div class="gdrcd-alert-success">
    <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
    <div>
        <strong>Blacklist svuotata.</strong>
        <span class="text-gdrcd-muted">·</span>
        <?= gdrcd_filter('out', $MESSAGE['warning']['modified']) ?>
    </div>
</div>
