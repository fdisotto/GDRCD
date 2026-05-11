<?php
/**
 * Form modifica di un messaggio del forum.
 */

$id_msg = gdrcd_filter('num', $_REQUEST['what'] ?? 0);
$row = gdrcd_query(
    "SELECT messaggioaraldo.id_messaggio, messaggioaraldo.titolo, messaggioaraldo.messaggio,
            messaggioaraldo.id_messaggio_padre, araldo.tipo, araldo.proprietari
     FROM messaggioaraldo
     LEFT JOIN araldo ON messaggioaraldo.id_araldo = araldo.id_araldo
     WHERE messaggioaraldo.id_messaggio = " . $id_msg
);

if (empty($row)): ?>
    <div class="gdrcd-alert-error">
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
        <div><?= gdrcd_filter('out', $MESSAGE['interface']['forums']['warning']['topic_not_exists']) ?></div>
    </div>
<?php
    return;
endif;

if (!gdrcd_controllo_permessi_forum($row['tipo'], $row['proprietari'])): ?>
    <div class="gdrcd-alert-error">
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
        <div><?= gdrcd_filter('out', $MESSAGE['error']['not_allowed']) ?></div>
    </div>
<?php
    return;
endif;

$is_topic = ((int)$row['id_messaggio_padre'] === -1);
?>

<div class="space-y-4">
    <section class="gdrcd-card">
        <div class="gdrcd-card-header">
            <h3 class="gdrcd-h3"><?= gdrcd_filter('out', $MESSAGE['interface']['forums']['insert']['title']) ?></h3>
        </div>
        <div class="gdrcd-card-body">
            <form action="main.php?page=forum&op=modifica&what=<?= (int)$row['id_messaggio'] ?>" method="post" class="space-y-5">
                <?php if ($is_topic): ?>
                    <div>
                        <label class="gdrcd-label" for="fm_titolo"><?= gdrcd_filter('out', $MESSAGE['interface']['forums']['insert']['title']) ?></label>
                        <input class="gdrcd-input" type="text" id="fm_titolo" name="titolo"
                               value="<?= gdrcd_filter('out', $row['titolo']) ?>" required/>
                    </div>
                <?php endif; ?>

                <div>
                    <label class="gdrcd-label" for="fm_messaggio"><?= gdrcd_filter('out', $MESSAGE['interface']['forums']['insert']['message']) ?></label>
                    <textarea class="gdrcd-textarea" id="fm_messaggio" name="messaggio" rows="10" required><?= gdrcd_filter('out', $row['messaggio']) ?></textarea>
                    <p class="gdrcd-help"><?= gdrcd_filter('out', $MESSAGE['interface']['help']['bbcode']) ?></p>
                </div>

                <div class="flex flex-col-reverse sm:flex-row gap-3 sm:justify-end pt-2 border-t border-gdrcd-border">
                    <a href="main.php?page=forum" class="gdrcd-btn-ghost">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        <?= gdrcd_filter('out', $MESSAGE['interface']['forums']['link']['topic']) ?>
                    </a>
                    <input type="hidden" name="op" value="edit"/>
                    <input type="hidden" name="araldo" value="<?= gdrcd_filter('num', $_REQUEST['where'] ?? 0) ?>"/>
                    <input type="hidden" name="messaggio_padre" value="<?= (int)$row['id_messaggio_padre'] ?>"/>
                    <input type="hidden" name="id_messaggio" value="<?= (int)$row['id_messaggio'] ?>"/>
                    <button type="submit" class="gdrcd-btn-primary">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <?= gdrcd_filter('out', $MESSAGE['interface']['forums']['submit']['edit'] ?? $MESSAGE['interface']['forms']['submit']) ?>
                    </button>
                </div>
            </form>
        </div>
    </section>
</div>
