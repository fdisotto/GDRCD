<?php
/**
 * Scheda PG — form segnalazione giocata ai GM.
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

if (!SEND_GM) {
    echo '<div class="gdrcd-alert-error">Segnalazioni ai GM disattivate.</div>';
    $render_back();
    return;
}

$op = $_POST['op'] ?? '';

if ($op === 'segnala') {
    $row_id = (int)gdrcd_filter('num', $_POST['id'] ?? 0);
?>
    <div class="gdrcd-card p-4 flex items-center gap-3">
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-md bg-gdrcd-accent-soft text-gdrcd-accent shrink-0">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21l1.65-3.8a9 9 0 113.4 2.9L3 21z"/></svg>
        </span>
        <div>
            <div class="font-display text-base text-gdrcd-text">Segnala ai Master</div>
            <p class="text-xs text-gdrcd-text-soft">Invia segnalazione GM con motivazione.</p>
        </div>
    </div>

    <form action="main.php?page=scheda_roles&pg=<?= $pg_url ?>" method="post" class="space-y-4">
        <?= gdrcd_csrf_field() ?>
        <div class="gdrcd-alert-info">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 22a10 10 0 110-20 10 10 0 010 20z"/></svg>
            <div>Segnala la role ai GM. Assicurati che i campi <strong>Tag</strong> e <strong>Quest</strong> siano correttamente compilati.</div>
        </div>

        <article class="gdrcd-card space-y-3">
            <label class="block">
                <span class="text-sm text-gdrcd-text-soft">Note aggiuntive</span>
                <input name="note" type="text" value="" class="gdrcd-input mt-1 w-full" placeholder="Esito, obiettivo, motivazione...">
                <span class="text-xs text-gdrcd-text-soft">Scrivere la motivazione della segnalazione (esito, obiettivo, ecc).</span>
            </label>
        </article>

        <div class="flex justify-end gap-2">
            <input type="hidden" name="op" value="segnala_send">
            <input type="hidden" name="id" value="<?= $row_id ?>">
            <button type="submit" class="gdrcd-btn-primary">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21l1.65-3.8a9 9 0 113.4 2.9L3 21z"/></svg>
                Segnala ai GM
            </button>
        </div>
    </form>
<?php
    $render_back();
    return;
}

if ($op === 'segnala_send') {
    gdrcd_query(
        "INSERT INTO send_GM (data, autore, role_reg, note)
         VALUES (NOW(),
                 '" . gdrcd_filter('in', $_SESSION['login']) . "',
                 " . gdrcd_filter('num', $_POST['id'] ?? 0) . ",
                 '" . gdrcd_filter('in', $_POST['note'] ?? '') . "')"
    );
?>
    <div class="gdrcd-alert-success">
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        <div>Segnalazione inviata con successo.</div>
    </div>
<?php
    $render_back();
}
