<?php
/**
 * Form modifica blocco esiti (titolo + stato aperto/chiuso).
 * Submit POST a esito_index?op=modify gestito da modify.php.
 */

if (($_GET['op'] ?? '') !== 'edit' || $_SESSION['permessi'] < ESITI_PERM) {
    return;
}

$id_edit = gdrcd_query(
    "SELECT * FROM blocco_esiti
     WHERE id = " . gdrcd_filter('num', $_GET['id'] ?? 0) . "
       AND (master = '0' || master = '" . gdrcd_filter('in', $_SESSION['login']) . "')",
    'result'
);
$tit = gdrcd_query($id_edit, 'fetch');
?>

<div class="space-y-6">

    <header class="space-y-2">
        <h2 class="gdrcd-h1">Modifica serie di esiti</h2>
        <p class="gdrcd-muted">Aggiorna titolo o stato della serie.</p>
    </header>

    <?php if (empty($tit)): ?>
        <div class="gdrcd-alert-warning">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
            <div>Non hai i permessi per modificare questa serie di esiti.</div>
        </div>
    <?php else: ?>
        <section class="gdrcd-card">
            <div class="gdrcd-card-body">
                <form action="main.php?page=gestione_segnalazioni&segn=esito_index" method="post" class="space-y-5">
                    <?= gdrcd_csrf_field() ?>
                    <div>
                        <label class="gdrcd-label" for="ee_titolo">Titolo</label>
                        <input class="gdrcd-input" type="text" id="ee_titolo" name="titolo"
                               value="<?= gdrcd_filter('out', $tit['titolo']) ?>" required/>
                    </div>
                    <div>
                        <label class="gdrcd-label" for="ee_stato">Stato</label>
                        <select class="gdrcd-select" id="ee_stato" name="stato">
                            <option value="0" <?= (int)$tit['closed'] === 0 ? 'selected' : '' ?>>Aperta</option>
                            <option value="1" <?= (int)$tit['closed'] === 1 ? 'selected' : '' ?>>Chiusa</option>
                        </select>
                    </div>
                    <div class="flex flex-col-reverse sm:flex-row gap-3 sm:justify-end pt-2 border-t border-gdrcd-border">
                        <a href="main.php?page=gestione_segnalazioni&segn=esiti_master" class="gdrcd-btn-ghost">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                            Annulla
                        </a>
                        <input type="hidden" name="op" value="modify"/>
                        <input type="hidden" name="id" value="<?= (int)$tit['id'] ?>"/>
                        <button type="submit" class="gdrcd-btn-primary">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            <?= gdrcd_filter('out', $MESSAGE['interface']['forms']['submit']) ?>
                        </button>
                    </div>
                </form>
            </div>
        </section>
    <?php endif; ?>
</div>
