<?php
/**
 * Servizi — Anagrafe (wrapper + dispatcher).
 */
$title = gdrcd_filter('out', $MESSAGE['interface']['pg_list']['pg_list']);
?>
<div class="space-y-6">
    <header class="space-y-1">
        <h2 class="gdrcd-h1 flex items-center gap-3">
            <span class="gdrcd-icon-circle">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
            </span>
            <?= $title ?>
        </h2>
    </header>

    <?php
    switch (gdrcd_filter_get($_POST['op'] ?? '')) {
        default:
            include 'servizi/anagrafe/index.inc.php';
            break;
    }
    ?>
</div>
