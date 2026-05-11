<?php
/**
 * Conferma cancellazione di un messaggio/topic del forum.
 */

$id_record = (int)($_REQUEST['id_record'] ?? 0);
$lbl = $MESSAGE['interface']['forums'];
?>

<div class="space-y-4">
    <section class="gdrcd-card">
        <div class="gdrcd-card-header flex items-center gap-3">
            <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-gdrcd-error-soft text-gdrcd-error border border-red-200 shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
            </span>
            <h3 class="gdrcd-h3"><?= gdrcd_filter('out', $lbl['delete']['title']) ?></h3>
        </div>
        <div class="gdrcd-card-body">
            <p class="gdrcd-prose"><?= gdrcd_filter('out', $lbl['delete']['ask']) ?></p>
        </div>
        <div class="px-6 md:px-8 py-4 border-t border-gdrcd-border flex flex-col-reverse sm:flex-row gap-3 sm:justify-end">
            <a href="main.php?page=forum" class="gdrcd-btn-ghost">Annulla</a>
            <form action="main.php?page=forum" method="post">
                <input type="hidden" name="op" value="delete"/>
                <input type="hidden" name="id_record" value="<?= $id_record ?>"/>
                <button type="submit" class="gdrcd-btn-danger">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/></svg>
                    <?= gdrcd_filter('out', $lbl['link']['delete']) ?>
                </button>
            </form>
        </div>
    </section>
</div>
