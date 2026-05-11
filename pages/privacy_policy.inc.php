<?php
/**
 * Pagina pubblica: informativa sulla privacy.
 *   main.php?page=privacy_policy
 *
 * Il contenuto e' caricato dalla tabella `legal_pages` (slug = 'privacy_policy')
 * ed editabile da admin in main.php?page=gestione/legal.
 */

$row = gdrcd_query(
    "SELECT title, body, updated_at FROM legal_pages WHERE slug = 'privacy_policy' LIMIT 1"
);
?>
<div class="space-y-6">
    <header class="space-y-1">
        <h2 class="gdrcd-h1 flex items-center gap-3">
            <span class="gdrcd-icon-circle">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 11c0-1.657 1.343-3 3-3s3 1.343 3 3-1.343 3-3 3-3-1.343-3-3zM4 6a2 2 0 012-2h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 11h2m-2 4h6"/>
                </svg>
            </span>
            <?= $row ? gdrcd_filter('out', $row['title']) : 'Informativa sulla privacy' ?>
        </h2>
    </header>

    <?php if (!$row): ?>
        <div class="gdrcd-alert-info">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01"/></svg>
            <div>Informativa non ancora pubblicata.</div>
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
