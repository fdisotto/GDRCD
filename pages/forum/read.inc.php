<?php
/**
 * Lettura di un singolo topic con tutte le risposte.
 */

$topic_id = (int)gdrcd_filter('num', $_REQUEST['what']  ?? 0);
$araldo_q = (int)gdrcd_filter('num', $_REQUEST['where'] ?? 0);

$result = Db::prepared(
    "SELECT messaggioaraldo.id_messaggio, messaggioaraldo.id_messaggio_padre,
            messaggioaraldo.titolo, messaggioaraldo.messaggio, messaggioaraldo.autore,
            messaggioaraldo.data_messaggio, messaggioaraldo.chiuso,
            araldo.tipo, araldo.nome, araldo.proprietari, araldo.id_araldo,
            personaggio.url_img
     FROM messaggioaraldo
     LEFT JOIN araldo ON messaggioaraldo.id_araldo = araldo.id_araldo
     LEFT JOIN personaggio ON messaggioaraldo.autore = personaggio.nome
     WHERE (messaggioaraldo.id_messaggio_padre = ? AND messaggioaraldo.id_messaggio_padre != -1)
        OR messaggioaraldo.id_messaggio = ?
     ORDER BY id_messaggio_padre, data_messaggio",
    'ii',
    array($topic_id, $topic_id)
);
$head = ($result instanceof mysqli_result) ? mysqli_fetch_assoc($result) : null;

if (empty($head)): ?>
<div class="space-y-4">
    <div class="gdrcd-alert-error">
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
        <div><?= gdrcd_filter('out', $MESSAGE['interface']['forums']['warning']['topic_not_exists']) ?></div>
    </div>
    <div>
        <a href="main.php?page=forum" class="gdrcd-btn-ghost">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <?= gdrcd_filter('out', $MESSAGE['interface']['forums']['link']['back']) ?>
        </a>
    </div>
</div>
<?php
    return;
endif;

if (!gdrcd_controllo_permessi_forum($head['tipo'], $head['proprietari'])): ?>
<div class="gdrcd-alert-error">
    <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
    <div><?= gdrcd_filter('out', $MESSAGE['error']['not_allowed']) ?></div>
</div>
<?php
    return;
endif;

$araldo_id = (int)$head['id_araldo'];
$chiuso    = !empty($head['chiuso']);
$is_mod    = ((int)$_SESSION['permessi'] >= MODERATOR);

// Marca come letto
$check = Db::preparedFetch(
    "SELECT id FROM araldo_letto WHERE nome = ? AND thread_id = ?",
    'si',
    array((string)$_SESSION['login'], $topic_id)
);
if ((int)($check['id'] ?? 0) <= 0) {
    Db::preparedExecute(
        "INSERT INTO araldo_letto (nome, araldo_id, thread_id) VALUES (?, ?, ?)",
        'sii',
        array((string)$_SESSION['login'], $araldo_q, $topic_id)
    );
}

/** Render del bbcode coerente con il setting forum_bbcode. */
$render_body = function (string $msg) use ($PARAMETERS) {
    if (($PARAMETERS['settings']['forum_bbcode']['type'] ?? '') === 'bbd') {
        return bbdecoder(gdrcd_filter('out', $msg), true);
    }
    return gdrcd_bbcoder(gdrcd_filter('out', $msg));
};

/** Render di un singolo post (testa o reply). */
$render_post = function ($row, bool $is_head) use ($render_body, $chiuso, $is_mod, $araldo_q, $topic_id, $MESSAGE) {
    $can_quote = (!$chiuso || $is_mod);
    $can_edit  = ((($_SESSION['login'] ?? '') === $row['autore'] && !$chiuso) || $is_mod);
    ob_start();
    ?>
    <article class="gdrcd-card overflow-hidden">
        <div class="grid grid-cols-1 sm:grid-cols-[10rem_minmax(0,1fr)]">
            <aside class="bg-gdrcd-panel-alt/40 p-4 border-b sm:border-b-0 sm:border-r border-gdrcd-border">
                <div class="flex sm:flex-col items-center sm:items-start gap-3">
                    <?php if (!empty($row['url_img'])): ?>
                        <img src="<?= htmlspecialchars($row['url_img']) ?>" alt=""
                             class="w-14 h-14 sm:w-20 sm:h-20 rounded-full object-cover border border-gdrcd-border bg-gdrcd-panel shrink-0"/>
                    <?php else: ?>
                        <span class="inline-flex items-center justify-center w-14 h-14 sm:w-20 sm:h-20 rounded-full bg-gdrcd-accent-soft text-gdrcd-accent border border-gdrcd-accent-ring/30 font-display text-2xl font-bold">
                            <?= htmlspecialchars(strtoupper(mb_substr($row['autore'] ?: '?', 0, 1))) ?>
                        </span>
                    <?php endif; ?>
                    <div class="min-w-0">
                        <a class="gdrcd-link block truncate font-semibold" href="main.php?page=scheda&pg=<?= urlencode($row['autore']) ?>">
                            <?= gdrcd_filter('out', $row['autore']) ?>
                        </a>
                        <div class="text-xs text-gdrcd-muted mt-1">
                            <?= gdrcd_format_date($row['data_messaggio']) ?>
                            <span class="text-gdrcd-subtle">·</span>
                            <?= gdrcd_format_time($row['data_messaggio']) ?>
                        </div>
                    </div>
                </div>
            </aside>
            <div class="p-4 md:p-5 space-y-3 min-w-0">
                <?php if ($is_head): ?>
                    <h3 class="gdrcd-h2"><?= gdrcd_filter('out', $row['titolo']) ?></h3>
                <?php endif; ?>
                <div class="gdrcd-prose">
                    <?= $render_body($row['messaggio']) ?>
                </div>
                <?php if ($can_quote || $can_edit): ?>
                    <div class="flex flex-wrap gap-2 pt-3 border-t border-gdrcd-border text-xs">
                        <?php if ($can_quote): ?>
                            <a class="gdrcd-link-quiet inline-flex items-center gap-1"
                               href="main.php?page=forum&op=composer&what=<?= $topic_id ?>&where=<?= $araldo_q ?>&quote=<?= (int)$row['id_messaggio'] ?>">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                <?= gdrcd_filter('out', $MESSAGE['interface']['forums']['link']['quote']) ?>
                            </a>
                        <?php endif; ?>
                        <?php if ($can_edit): ?>
                            <a class="gdrcd-link-quiet inline-flex items-center gap-1"
                               href="main.php?page=forum&op=modifica&what=<?= (int)$row['id_messaggio'] ?>&where=<?= $araldo_q ?>">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                <?= gdrcd_filter('out', $MESSAGE['interface']['forums']['link']['edit']) ?>
                            </a>
                            <a class="gdrcd-link-quiet inline-flex items-center gap-1 text-gdrcd-muted hover:text-gdrcd-error"
                               href="main.php?page=forum&op=delete_conf&id_record=<?= (int)$row['id_messaggio'] ?>&padre=<?= (int)$row['id_messaggio_padre'] ?>">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/></svg>
                                <?= gdrcd_filter('out', $MESSAGE['interface']['forums']['link']['delete']) ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </article>
    <?php
    return ob_get_clean();
};
?>

<div class="space-y-4">

    <nav class="text-xs">
        <a href="main.php?page=forum" class="gdrcd-link-quiet"><?= gdrcd_filter('out', $PARAMETERS['names']['forum']['plur']) ?></a>
        <span class="text-gdrcd-subtle mx-1">/</span>
        <a href="main.php?page=forum&op=visit&what=<?= $araldo_id ?>" class="gdrcd-link-quiet"><?= gdrcd_filter('out', $head['nome']) ?></a>
    </nav>

    <?= $render_post($head, true) ?>

    <?php while ($row = gdrcd_query($result, 'fetch')):
        echo $render_post($row, false);
    endwhile;
    gdrcd_query($result, 'free');
    ?>

    <?php if (!$chiuso || $is_mod): ?>
        <section class="gdrcd-card">
            <div class="gdrcd-card-header">
                <h3 class="gdrcd-h3">Risposta rapida</h3>
            </div>
            <div class="gdrcd-card-body">
                <form action="main.php?page=forum" method="post" class="space-y-4">
                    <?= gdrcd_csrf_field() ?>
                    <div>
                        <textarea class="gdrcd-textarea" name="messaggio" rows="6" required data-bbcode></textarea>
                        <p class="gdrcd-help"><?= gdrcd_filter('out', $MESSAGE['interface']['help']['bbcode']) ?></p>
                    </div>
                    <div class="flex justify-end pt-2 border-t border-gdrcd-border">
                        <input type="hidden" name="op" value="insert"/>
                        <input type="hidden" name="araldo" value="<?= $araldo_q ?>"/>
                        <input type="hidden" name="padre" value="<?= $topic_id ?>"/>
                        <button type="submit" class="gdrcd-btn-primary">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                            <?= gdrcd_filter('out', $MESSAGE['interface']['forms']['submit']) ?>
                        </button>
                    </div>
                </form>
            </div>
        </section>
    <?php endif; ?>

    <div class="flex flex-wrap gap-2">
        <a href="main.php?page=forum&op=visit&what=<?= $araldo_id ?>" class="gdrcd-btn-ghost">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <?= gdrcd_filter('out', $MESSAGE['interface']['forums']['link']['forum']) ?>
        </a>
        <?php if (!$chiuso || $is_mod): ?>
            <a href="main.php?page=forum&op=composer&what=<?= $topic_id ?>&where=<?= $araldo_q ?>" class="gdrcd-btn-secondary ml-auto">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                <?= gdrcd_filter('out', $MESSAGE['interface']['forums']['link']['new_post']) ?>
            </a>
        <?php endif; ?>
    </div>

</div>
