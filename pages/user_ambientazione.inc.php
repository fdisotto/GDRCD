<?php
/**
 * Utente — ambientazione: capitoli del mondo di gioco.
 */
$result = gdrcd_query("SELECT capitolo, titolo, testo FROM ambientazione ORDER BY capitolo", 'result');
?>
<div class="space-y-6">
    <header class="space-y-1">
        <h2 class="gdrcd-h1 flex items-center gap-3">
            <span class="gdrcd-icon-circle">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
            </span>
            <?= gdrcd_filter('out', $MESSAGE['interface']['plot']['page_name']) ?>
        </h2>
    </header>

    <?php if (gdrcd_query($result, 'num_rows') === 0): ?>
        <div class="gdrcd-alert-info">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01"/></svg>
            <div>Nessun capitolo di ambientazione disponibile.</div>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php while ($row = gdrcd_query($result, 'fetch')): ?>
                <article class="gdrcd-card">
                    <header class="gdrcd-card-header flex items-baseline gap-3">
                        <span class="font-display text-2xl text-gdrcd-accent tabular-nums"><?= gdrcd_filter('out', $row['capitolo']) ?></span>
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
