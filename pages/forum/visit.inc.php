<?php
/**
 * Lista topic di una singola bacheca (forum/visit).
 */

$araldo_id = gdrcd_filter('num', $_REQUEST['what'] ?? 0);
$araldo    = gdrcd_query("SELECT nome, tipo, proprietari FROM araldo WHERE id_araldo = " . $araldo_id);

if (empty($araldo) || !gdrcd_controllo_permessi_forum($araldo['tipo'], $araldo['proprietari'])):
?>
<div class="space-y-4">
    <div class="gdrcd-alert-error">
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
        <div><?= gdrcd_filter('out', $MESSAGE['error']['not_allowed']) ?></div>
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

/* Handler azioni master (importante / chiuso) */
if ($_SESSION['permessi'] >= MODERATOR && isset($_POST['ops'])) {
    if ($_POST['ops'] === 'important') {
        $id_record  = (int)($_POST['id_record'] ?? 0);
        $status_imp = (int)($_POST['status_imp'] ?? 0);
        gdrcd_query("UPDATE messaggioaraldo SET importante = " . $status_imp . " WHERE id_messaggio = " . $id_record);
    } elseif ($_POST['ops'] === 'close') {
        $id_record  = (int)($_POST['id_record'] ?? 0);
        $status_cls = (int)($_POST['status_cls'] ?? 0);
        gdrcd_query("UPDATE messaggioaraldo SET chiuso = " . $status_cls . " WHERE id_messaggio = " . $id_record);
    }
}

$offset    = (int)($_REQUEST['offset'] ?? 0);
$per_page  = (int)$PARAMETERS['settings']['posts_per_page'];
$pagebegin = $offset * $per_page;

$count_row     = gdrcd_query("SELECT COUNT(*) AS c FROM messaggioaraldo WHERE id_messaggio_padre = -1 AND id_araldo = " . $araldo_id);
$totaleresults = (int)$count_row['c'];

$result = gdrcd_query(
    "SELECT MA.id_messaggio, MA.titolo, MA.autore, MA.data_messaggio, MA.data_ultimo_messaggio,
            MA.importante, MA.chiuso, AL.id AS read_id
     FROM messaggioaraldo AS MA
     LEFT JOIN araldo_letto AS AL ON MA.id_messaggio = AL.thread_id AND AL.nome = '" . gdrcd_filter('in', $_SESSION['login']) . "'
     WHERE MA.id_messaggio_padre = -1 AND MA.id_araldo = " . $araldo_id . "
     ORDER BY MA.importante DESC, MA.data_ultimo_messaggio DESC
     LIMIT " . $pagebegin . ", " . $per_page,
    'result'
);
$numresults = (int)gdrcd_query($result, 'num_rows');

$is_mod = ((int)$_SESSION['permessi'] >= MODERATOR);
?>

<div class="space-y-6">

    <header class="flex flex-wrap items-end justify-between gap-3">
        <div class="space-y-2">
            <nav class="text-xs">
                <a href="main.php?page=forum" class="gdrcd-link-quiet">
                    <?= gdrcd_filter('out', $PARAMETERS['names']['forum']['plur']) ?>
                </a>
                <span class="text-gdrcd-subtle mx-1">/</span>
            </nav>
            <h2 class="gdrcd-h1"><?= gdrcd_filter('out', $araldo['nome']) ?></h2>
            <p class="gdrcd-muted"><?= $totaleresults ?> topic</p>
        </div>
        <a href="main.php?page=forum&op=composer&what=-1&where=<?= $araldo_id ?>" class="gdrcd-btn-primary">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            <?= gdrcd_filter('out', $MESSAGE['interface']['forums']['link']['new_topic']) ?>
        </a>
    </header>

    <?php if ($numresults === 0): ?>
        <div class="gdrcd-alert-info">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div><?= gdrcd_filter('out', $MESSAGE['interface']['forums']['warning']['no_topic']) ?></div>
        </div>
    <?php else: ?>
        <div class="gdrcd-card overflow-hidden">
            <ul class="divide-y divide-gdrcd-border">
                <?php while ($row = gdrcd_query($result, 'fetch')):
                    $info = gdrcd_query("SELECT MAX(data_messaggio) AS latest, COUNT(*) AS replies
                                          FROM messaggioaraldo WHERE id_messaggio_padre = " . (int)$row['id_messaggio']);
                    $is_new      = ((int)($row['read_id'] ?? 0) === 0);
                    $is_imp      = !empty($row['importante']);
                    $is_chiuso   = !empty($row['chiuso']);
                    $replies     = (int)$info['replies'];
                    $latest      = $info['latest'];
                    $url_read    = 'main.php?page=forum&op=read&what=' . (int)$row['id_messaggio'] . '&where=' . $araldo_id;
                    ?>
                    <li class="group <?= $is_new ? 'bg-gdrcd-accent-soft/10' : '' ?> hover:bg-gdrcd-accent-soft/30 transition-colors">
                        <div class="flex items-start gap-3 px-4 py-3">
                            <span class="inline-flex items-center justify-center w-10 h-10 rounded-full shrink-0 <?= $is_new ? 'bg-gdrcd-accent text-white' : 'bg-gdrcd-accent-soft text-gdrcd-accent border border-gdrcd-accent-ring/30' ?>">
                                <?php if ($is_chiuso): ?>
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                <?php else: ?>
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                                <?php endif; ?>
                            </span>

                            <a href="<?= htmlspecialchars($url_read) ?>" class="flex-1 min-w-0 block">
                                <div class="flex flex-wrap items-center gap-2">
                                    <?php if ($is_imp): ?>
                                        <span class="gdrcd-badge-accent text-[10px]">
                                            <?= gdrcd_filter('out', $MESSAGE['interface']['administration']['ops']['important']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($is_chiuso): ?>
                                        <span class="gdrcd-badge-neutral text-[10px]">
                                            <?= gdrcd_filter('out', $MESSAGE['interface']['administration']['ops']['close']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="<?= $is_new ? 'font-semibold text-gdrcd-text' : 'text-gdrcd-text-soft' ?>">
                                        <?= gdrcd_filter('out', $row['titolo']) ?>
                                    </span>
                                    <?php if ($is_new): ?>
                                        <span class="inline-block w-2 h-2 rounded-full bg-gdrcd-accent" title="Nuovi messaggi"></span>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-1 text-xs text-gdrcd-muted flex flex-wrap gap-2">
                                    <span>di <strong class="text-gdrcd-text-soft"><?= gdrcd_filter('out', $row['autore']) ?></strong></span>
                                    <span class="text-gdrcd-subtle">·</span>
                                    <span><?= gdrcd_format_date($row['data_messaggio']) ?></span>
                                    <span class="text-gdrcd-subtle">·</span>
                                    <span><?= $replies ?> <?= gdrcd_filter('out', $MESSAGE['interface']['forums']['topic']['posts']) ?></span>
                                    <?php if ($replies > 0): ?>
                                        <span class="text-gdrcd-subtle">·</span>
                                        <span><?= gdrcd_filter('out', $MESSAGE['interface']['forums']['topic']['last_post']) ?>: <?= gdrcd_format_date($latest) ?> <?= gdrcd_format_time($latest) ?></span>
                                    <?php endif; ?>
                                </div>
                            </a>

                            <?php if ($is_mod):
                                $next_imp = $is_imp ? 0 : 1;
                                $next_cls = $is_chiuso ? 0 : 1;
                                ?>
                                <div class="shrink-0 flex items-center gap-1 opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition-opacity">
                                    <form action="main.php?<?= htmlspecialchars($_SERVER['QUERY_STRING'] ?? 'page=forum&op=visit&what=' . $araldo_id) ?>" method="post" class="inline">
                                        <?= gdrcd_csrf_field() ?>
                                        <input type="hidden" name="id_record" value="<?= (int)$row['id_messaggio'] ?>"/>
                                        <input type="hidden" name="status_imp" value="<?= $next_imp ?>"/>
                                        <input type="hidden" name="ops" value="important"/>
                                        <button type="submit" title="<?= $is_imp ? 'Rendi non importante' : 'Rendi importante' ?>"
                                                class="inline-flex items-center justify-center w-8 h-8 rounded-md <?= $is_imp ? 'text-gdrcd-accent' : 'text-gdrcd-muted' ?> hover:bg-gdrcd-accent-soft hover:text-gdrcd-accent transition-colors">
                                            <svg class="w-4 h-4" fill="<?= $is_imp ? 'currentColor' : 'none' ?>" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                                        </button>
                                    </form>
                                    <form action="main.php?<?= htmlspecialchars($_SERVER['QUERY_STRING'] ?? 'page=forum&op=visit&what=' . $araldo_id) ?>" method="post" class="inline">
                                        <?= gdrcd_csrf_field() ?>
                                        <input type="hidden" name="id_record" value="<?= (int)$row['id_messaggio'] ?>"/>
                                        <input type="hidden" name="status_cls" value="<?= $next_cls ?>"/>
                                        <input type="hidden" name="ops" value="close"/>
                                        <button type="submit" title="<?= $is_chiuso ? 'Riapri topic' : 'Chiudi topic' ?>"
                                                class="inline-flex items-center justify-center w-8 h-8 rounded-md text-gdrcd-muted hover:bg-gdrcd-accent-soft hover:text-gdrcd-accent transition-colors">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <?php if ($is_chiuso): ?>
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/>
                                                <?php else: ?>
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                                <?php endif; ?>
                                            </svg>
                                        </button>
                                    </form>
                                    <a href="main.php?page=forum&op=delete_conf&id_record=<?= (int)$row['id_messaggio'] ?>&padre=-1"
                                       class="inline-flex items-center justify-center w-8 h-8 rounded-md text-gdrcd-muted hover:bg-gdrcd-error-soft hover:text-gdrcd-error transition-colors"
                                       title="Elimina">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/></svg>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endwhile;
                gdrcd_query($result, 'free');
                ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($totaleresults > $per_page): ?>
        <nav class="gdrcd-pager" aria-label="Paginazione">
            <span class="gdrcd-pager-label !border-0 !bg-transparent">
                <?= gdrcd_filter('out', $MESSAGE['interface']['pager']['pages_name']) ?>
            </span>
            <?php $pages = (int)floor($totaleresults / $per_page);
            for ($i = 0; $i <= $pages; $i++):
                if ($i === $offset): ?>
                    <span class="is-current" aria-current="page"><?= $i + 1 ?></span>
                <?php else:
                    $url = 'main.php?' . http_build_query([
                        'page' => 'forum', 'op' => 'visit',
                        'what' => $araldo_id, 'offset' => $i,
                    ]); ?>
                    <a href="<?= htmlspecialchars($url) ?>"><?= $i + 1 ?></a>
                <?php endif;
            endfor; ?>
        </nav>
    <?php endif; ?>

</div>
