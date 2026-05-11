<?php
/**
 * Utente — regolamento articoli.
 */
$result = gdrcd_query("SELECT articolo, titolo, testo FROM regolamento ORDER BY articolo", 'result');
?>
<div class="space-y-6">
    <header class="space-y-1">
        <h2 class="gdrcd-h1 flex items-center gap-3">
            <span class="gdrcd-icon-circle">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </span>
            <?= gdrcd_filter('out', $MESSAGE['interface']['rules']['page_name']) ?>
        </h2>
    </header>

    <?php if (gdrcd_query($result, 'num_rows') === 0): ?>
        <div class="gdrcd-alert-info">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01"/></svg>
            <div>Nessun articolo presente.</div>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php while ($row = gdrcd_query($result, 'fetch')): ?>
                <article class="gdrcd-card">
                    <header class="gdrcd-card-header flex items-baseline gap-3">
                        <span class="font-display text-2xl text-gdrcd-accent tabular-nums">
                            <?= gdrcd_filter('out', $row['articolo']) ?>)
                        </span>
                        <h3 class="gdrcd-h3 m-0"><?= gdrcd_filter('out', $row['titolo']) ?></h3>
                    </header>
                    <div class="gdrcd-card-body text-gdrcd-text leading-relaxed">
                        <?= gdrcd_bbcoder(gdrcd_filter('out', $row['testo'])) ?>
                    </div>
                </article>
            <?php endwhile; gdrcd_query($result, 'free'); ?>
        </div>
    <?php endif; ?>
</div>
