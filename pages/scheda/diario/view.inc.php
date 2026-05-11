<?php
/**
 * Diario PG — lettura singola pagina.
 */

$id_diario = gdrcd_filter('num', $_POST['id'] ?? 0);
$row = gdrcd_query(
    "SELECT data, titolo, testo, data_modifica, data_inserimento FROM diario
     WHERE id = " . $id_diario . " LIMIT 1"
);

if (empty($row)) {
    echo '<div class="gdrcd-alert-warning">Pagina non trovata.</div>';
    return;
}

$lbl = $MESSAGE['interface']['sheet']['diary'];
?>

<article class="gdrcd-card">
    <header class="gdrcd-card-header space-y-2">
        <div class="flex flex-wrap items-baseline justify-between gap-3">
            <h3 class="gdrcd-h2"><?= gdrcd_filter('out', $row['titolo']) ?></h3>
            <span class="gdrcd-badge-accent"><?= gdrcd_format_date($row['data']) ?></span>
        </div>
    </header>
    <div class="gdrcd-card-body gdrcd-prose">
        <?= nl2br(gdrcd_filter('out', $row['testo'])) ?>
    </div>
    <footer class="px-6 md:px-8 py-3 border-t border-gdrcd-border text-xs text-gdrcd-muted flex flex-wrap gap-4">
        <div>
            <span class="gdrcd-eyebrow">Inserita</span>
            <div class="mt-0.5"><?= gdrcd_format_datetime($row['data_inserimento']) ?></div>
        </div>
        <?php if (!empty($row['data_modifica'])): ?>
            <div>
                <span class="gdrcd-eyebrow">Ultima modifica</span>
                <div class="mt-0.5"><?= gdrcd_format_datetime($row['data_modifica']) ?></div>
            </div>
        <?php endif; ?>
    </footer>
</article>

<div>
    <a href="main.php?page=scheda_diario&pg=<?= urlencode($_REQUEST['pg']) ?>" class="gdrcd-btn-ghost">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        <?= gdrcd_filter('out', $lbl['back']) ?>
    </a>
</div>
