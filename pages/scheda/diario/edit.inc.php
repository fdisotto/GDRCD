<?php
/**
 * Diario PG — form modifica pagina.
 */

$id_diario = gdrcd_filter('num', $_POST['id'] ?? 0);
$record = gdrcd_query(
    "SELECT data, titolo, testo, visibile, id FROM diario
     WHERE id = " . $id_diario . " LIMIT 1"
);
if (empty($record)) {
    echo '<div class="gdrcd-alert-warning">Pagina non trovata.</div>';
    return;
}

$lbl = $MESSAGE['interface']['sheet']['diary'];
?>

<section class="gdrcd-card">
    <div class="gdrcd-card-header">
        <h3 class="gdrcd-h3">Modifica pagina</h3>
    </div>
    <div class="gdrcd-card-body">
        <form action="main.php?page=scheda_diario&pg=<?= urlencode($_REQUEST['pg']) ?>" method="post" class="space-y-5">
            <?= gdrcd_csrf_field() ?>
            <div class="grid grid-cols-1 md:grid-cols-[1fr_12rem] gap-4">
                <div>
                    <label class="gdrcd-label" for="dia_tit"><?= gdrcd_filter('out', $lbl['title']) ?></label>
                    <input class="gdrcd-input" type="text" id="dia_tit" name="titolo"
                           value="<?= gdrcd_filter('out', $record['titolo']) ?>" required/>
                </div>
                <div>
                    <label class="gdrcd-label" for="dia_data"><?= gdrcd_filter('out', $lbl['date']) ?></label>
                    <input class="gdrcd-input" type="date" id="dia_data" name="data"
                           value="<?= gdrcd_filter('out', $record['data']) ?>" required/>
                </div>
            </div>

            <div>
                <label class="gdrcd-label" for="dia_vis"><?= gdrcd_filter('out', $lbl['visible']) ?></label>
                <select class="gdrcd-select max-w-xs" id="dia_vis" name="visibile" required>
                    <option value="si" <?= strtolower($record['visibile']) === 'si' ? 'selected' : '' ?>>Pubblica</option>
                    <option value="no" <?= strtolower($record['visibile']) === 'no' ? 'selected' : '' ?>>Privata</option>
                </select>
            </div>

            <div>
                <label class="gdrcd-label" for="dia_testo"><?= gdrcd_filter('out', $lbl['text']) ?></label>
                <textarea class="gdrcd-textarea" id="dia_testo" name="testo" rows="12"><?= gdrcd_filter('out', $record['testo']) ?></textarea>
            </div>

            <div class="flex flex-col-reverse sm:flex-row gap-3 sm:justify-end pt-2 border-t border-gdrcd-border">
                <a href="main.php?page=scheda_diario&pg=<?= urlencode($_REQUEST['pg']) ?>" class="gdrcd-btn-ghost">Annulla</a>
                <input type="hidden" name="op" value="save_edit"/>
                <input type="hidden" name="id" value="<?= (int)$record['id'] ?>"/>
                <input type="hidden" name="pg" value="<?= htmlspecialchars($_REQUEST['pg']) ?>"/>
                <button type="submit" class="gdrcd-btn-primary">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    Salva
                </button>
            </div>
        </form>
    </div>
</section>
