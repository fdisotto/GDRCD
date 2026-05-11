<?php
/**
 * Servizi — Prenotazioni stanze (wrapper + dispatcher).
 */
$title = gdrcd_filter('out', $MESSAGE['interface']['hotel']['page_name']);
?>
<div class="space-y-6">
    <header class="space-y-1">
        <h2 class="gdrcd-h1 flex items-center gap-3">
            <span class="gdrcd-icon-circle">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
            </span>
            <?= $title ?>
        </h2>
    </header>

    <?php
    switch (gdrcd_filter_get($_POST['op'] ?? '')) {
        default:
            include 'servizi/prenotazioni/index.inc.php';
            break;
    }
    ?>
</div>
