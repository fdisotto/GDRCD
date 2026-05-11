<?php
/**
 * Pagina pubblica: termini di servizio.
 *   main.php?page=tos
 *
 * Il contenuto e' caricato dalla tabella `legal_pages` (slug = 'tos')
 * ed editabile da admin in main.php?page=gestione/legal.
 */

$row = gdrcd_query(
    "SELECT title, body, updated_at FROM legal_pages WHERE slug = 'tos' LIMIT 1"
);
?>
<div class="space-y-6">
    <header class="space-y-1">
        <h2 class="gdrcd-h1 flex items-center gap-3">
            <span class="gdrcd-icon-circle">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </span>
            <?= $row ? gdrcd_filter('out', $row['title']) : 'Termini di servizio' ?>
        </h2>
    </header>

    <?php if (!$row): ?>
        <div class="gdrcd-alert-info">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01"/></svg>
            <div>Termini di servizio non ancora pubblicati.</div>
        </div>
    <?php else: ?>
        <article class="gdrcd-card">
            <div class="gdrcd-card-body text-gdrcd-text leading-relaxed space-y-4">
                <?= gdrcd_bbcoder(gdrcd_filter('out', $row['body'])) ?>
                <?php if (!empty($row['updated_at'])): ?>
                    <p class="gdrcd-muted text-xs pt-4 border-t border-gdrcd-border">
                        Ultimo aggiornamento:
                        <time datetime="<?= htmlspecialchars($row['updated_at']) ?>">
                            <?= htmlspecialchars(date('d/m/Y', strtotime($row['updated_at']))) ?>
                        </time>
                    </p>
                <?php endif; ?>
            </div>
        </article>
    <?php endif; ?>
</div>
