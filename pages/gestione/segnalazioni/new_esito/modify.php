<?php
/**
 * Handler POST: aggiorna titolo e stato di una serie esiti.
 * Inviato dal form edit.php.
 */

if (($_POST['op'] ?? '') !== 'modify') {
    return;
}

gdrcd_query(
    "UPDATE blocco_esiti
     SET titolo = '" . gdrcd_filter('in', $_POST['titolo']) . "',
         closed = " . gdrcd_filter('num', $_POST['stato']) . "
     WHERE id = " . gdrcd_filter('num', $_POST['id']) . "
       AND (master = '0' || master = '" . gdrcd_filter('in', $_SESSION['login']) . "')"
);

$back_url = 'main.php?' . http_build_query(['page' => 'gestione_segnalazioni', 'segn' => 'esiti_master']);
?>

<div class="gdrcd-shell">
    <div class="gdrcd-container-sm">
        <section class="gdrcd-card">
            <div class="gdrcd-card-body text-center space-y-4 py-8">
                <span class="gdrcd-icon-circle bg-gdrcd-success-soft text-gdrcd-success border-green-200">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                </span>
                <h2 class="gdrcd-h2"><?= gdrcd_filter('out', $MESSAGE['warning']['inserted']) ?></h2>
                <p class="gdrcd-muted">
                    Serie aggiornata: <strong class="text-gdrcd-text"><?= gdrcd_filter('out', $_POST['titolo']) ?></strong>
                </p>
                <div class="pt-2">
                    <a href="<?= htmlspecialchars($back_url) ?>" class="gdrcd-btn-primary">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7-7 7M3 12h18"/></svg>
                        Torna alla lista
                    </a>
                </div>
            </div>
        </section>
    </div>
</div>
