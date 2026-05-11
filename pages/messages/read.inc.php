<?php
/**
 * Visualizzazione di un singolo messaggio.
 */

$id_messaggio = gdrcd_filter('num', $_REQUEST['id_messaggio'] ?? 0);
$result = gdrcd_query(
    "SELECT * FROM messaggi
     WHERE id = " . $id_messaggio . "
       AND (destinatario = '" . gdrcd_filter('in', $_SESSION['login']) . "'
            OR mittente   = '" . gdrcd_filter('in', $_SESSION['login']) . "')
     LIMIT 1",
    'result'
);

if (gdrcd_query($result, 'num_rows') === 0): ?>
<div class="space-y-4">
    <div class="gdrcd-alert-warning">
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
        <div>Impossibile visualizzare il messaggio: non esiste o non hai i permessi.</div>
    </div>
    <div>
        <a href="main.php?page=messages_center&offset=0" class="gdrcd-btn-ghost">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <?= gdrcd_filter('out', $MESSAGE['interface']['messages']['go_back']) ?>
        </a>
    </div>
</div>
<?php
    return;
endif;

$record = gdrcd_query($result, 'fetch');
gdrcd_query($result, 'free');

if ($record['destinatario'] === $_SESSION['login'] && (int)$record['letto'] === 0) {
    gdrcd_query("UPDATE messaggi SET letto = 1 WHERE id = " . gdrcd_filter('num', $record['id']) . " LIMIT 1");
}

[$data_spedito, $ora_spedito] = explode(' ', $record['spedito']);

$lbl = $MESSAGE['interface']['messages'];
$type_label = $MESSAGE['interface']['messages']['type']['options'][$record['tipo']] ?? '';
?>

<div class="space-y-4">

    <section class="gdrcd-card">
        <header class="gdrcd-card-header space-y-3">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="gdrcd-h2"><?= gdrcd_filter('out', $record['oggetto']) ?></h2>
                <span class="gdrcd-badge-neutral"><?= gdrcd_filter('out', $type_label) ?></span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs text-gdrcd-muted">
                <div>
                    <span class="gdrcd-eyebrow"><?= gdrcd_filter('out', $lbl['sender']) ?></span>
                    <div class="mt-0.5">
                        <a class="gdrcd-link" href="main.php?page=scheda&pg=<?= urlencode($record['mittente']) ?>">
                            <?= gdrcd_filter('out', $record['mittente']) ?>
                        </a>
                    </div>
                </div>
                <div>
                    <span class="gdrcd-eyebrow"><?= gdrcd_filter('out', $lbl['date']) ?></span>
                    <div class="mt-0.5 text-gdrcd-text">
                        <?= gdrcd_format_date($data_spedito) ?>
                        <span class="text-gdrcd-subtle">·</span>
                        <?= gdrcd_format_time($ora_spedito) ?>
                    </div>
                </div>
            </div>
        </header>
        <div class="gdrcd-card-body gdrcd-prose">
            <?= nl2br(gdrcd_bbcoder(gdrcd_filter('out', $record['testo']))) ?>
        </div>
    </section>

    <div class="flex flex-wrap gap-2">
        <form action="main.php?page=messages_center&op=read&id_messaggio=<?= (int)$record['id'] ?>" method="post" class="inline">
            <input type="hidden" name="reply_dest" value="<?= htmlspecialchars($record['mittente']) ?>"/>
            <input type="hidden" name="reply_subject" value="Re: <?= htmlspecialchars($record['oggetto']) ?>"/>
            <input type="hidden" name="reply_tipo" value="<?= (int)$record['tipo'] ?>"/>
            <input type="hidden" name="op" value="reply"/>
            <button type="submit" class="gdrcd-btn-primary">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                <?= gdrcd_filter('out', $lbl['reply']) ?>
            </button>
        </form>
        <form action="main.php?page=messages_center&op=read&id_messaggio=<?= (int)$record['id'] ?>" method="post" class="inline">
            <input type="hidden" name="reply_dest" value="<?= htmlspecialchars($record['mittente']) ?>"/>
            <input type="hidden" name="reply_subject" value="Re: <?= htmlspecialchars($record['oggetto']) ?>"/>
            <input type="hidden" name="reply_tipo" value="<?= (int)$record['tipo'] ?>"/>
            <input type="hidden" name="testo" value="<?= htmlspecialchars($lbl['attachment'] . $record['testo']) ?>"/>
            <input type="hidden" name="op" value="attach"/>
            <button type="submit" class="gdrcd-btn-secondary">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                <?= gdrcd_filter('out', $lbl['attach']) ?>
            </button>
        </form>
        <a href="main.php?page=messages_center&offset=0" class="gdrcd-btn-ghost ml-auto">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <?= gdrcd_filter('out', $lbl['go_back']) ?>
        </a>
    </div>
</div>
