<?php
/**
 * Scheda PG — edit tag/quest di una registrazione.
 */

$pg_url = gdrcd_filter('url', $_REQUEST['pg']);

$render_back = function () use ($pg_url, $MESSAGE) { ?>
    <div>
        <a href="main.php?page=scheda_roles&pg=<?= $pg_url ?>" class="gdrcd-btn-ghost">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <?= gdrcd_filter('out', $MESSAGE['interface']['sheet']['link']['back_roles']) ?>
        </a>
    </div>
<?php };

if (!($_SESSION['permessi'] >= EDIT_PERM || $_REQUEST['pg'] == $_SESSION['login'])) {
    echo '<div class="gdrcd-alert-error">Non hai i permessi per modificare una registrazione.</div>';
    $render_back();
    return;
}

$op = $_POST['op'] ?? '';

if ($op === 'send_edit') {
    gdrcd_query(
        "UPDATE segnalazione_role SET
            tags = '" . gdrcd_filter('in', $_POST['ab'] ?? '') . "',
            quest = '" . gdrcd_filter('in', $_POST['quest'] ?? '') . "'
         WHERE id = " . gdrcd_filter('num', $_POST['id'] ?? 0)
    );
    ?>
    <div class="gdrcd-alert-success">
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        <div>Registrazione modificata con successo.</div>
    </div>
    <?php
    $render_back();
    return;
}

if ($op === 'edit') {
    $row_id = (int)gdrcd_filter('num', $_POST['id'] ?? 0);
    $query = gdrcd_query("SELECT * FROM segnalazione_role WHERE id = " . $row_id, 'result');
    $row = gdrcd_query($query, 'fetch');
    if (!$row) {
        echo '<div class="gdrcd-alert-error">Registrazione non trovata.</div>';
        $render_back();
        return;
    }
?>
    <div class="gdrcd-card p-4 flex items-center gap-3">
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-md bg-gdrcd-accent-soft text-gdrcd-accent shrink-0">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
        </span>
        <div>
            <div class="font-display text-base text-gdrcd-text">Modifica registrazione</div>
            <p class="text-xs text-gdrcd-text-soft">Aggiorna tag e note quest della giocata.</p>
        </div>
    </div>

    <form action="main.php?page=scheda_roles&pg=<?= $pg_url ?>" method="post" class="space-y-4">
        <?= gdrcd_csrf_field() ?>
        <div class="gdrcd-alert-info">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 22a10 10 0 110-20 10 10 0 010 20z"/></svg>
            <div>Puoi modificare i campi <strong>Tag</strong> e <strong>Quest</strong> entro <strong>30 giorni</strong> dalla giocata.</div>
        </div>

        <article class="gdrcd-card space-y-3">
            <label class="block">
                <span class="text-sm text-gdrcd-text-soft">Tag</span>
                <input name="ab" type="text" value="<?= htmlspecialchars($row['tags']) ?>" class="gdrcd-input mt-1 w-full">
                <span class="text-xs text-gdrcd-text-soft">I tag possono essere utili per ritrovare rapidamente una role.</span>
            </label>
            <label class="block">
                <span class="text-sm text-gdrcd-text-soft">Note quest</span>
                <input name="quest" type="text" value="<?= htmlspecialchars($row['quest']) ?>" class="gdrcd-input mt-1 w-full">
                <span class="text-xs text-gdrcd-text-soft">Brevissimo riassunto di interazioni con spunti di trama. In assenza di segnalazione un GM non riceve notifica.</span>
            </label>
        </article>

        <div class="flex justify-end gap-2">
            <input type="hidden" name="op" value="send_edit">
            <input type="hidden" name="id" value="<?= $row_id ?>">
            <button type="submit" class="gdrcd-btn-primary">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                Salva modifiche
            </button>
        </div>
    </form>
<?php
    $render_back();
}
