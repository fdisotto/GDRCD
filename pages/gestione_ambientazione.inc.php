<?php
/**
 * Gestione ambientazione (main.php?page=gestione_ambientazione)
 * CRUD su tabella ambientazione (capitoli con titolo e testo bbcode).
 * PK = capitolo (numerico, modificabile dall'utente).
 */

if ($_SESSION['permessi'] < MODERATOR) {
    echo '<div class="gdrcd-alert-error">'
       . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>'
       . '<div>' . gdrcd_filter('out', $MESSAGE['error']['not_allowed']) . '</div>'
       . '</div>';
    return;
}

$lbl = $MESSAGE['interface']['administration']['plot'];
$op  = $_POST['op'] ?? $_GET['op'] ?? null;

$render_back = function () use ($lbl) {
    return '<a href="main.php?page=gestione_ambientazione" class="gdrcd-btn-ghost">'
         . '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>'
         . gdrcd_filter('out', $lbl['link']['back'])
         . '</a>';
};
?>

<div class="space-y-6">

    <header class="space-y-2">
        <h2 class="gdrcd-h1"><?= gdrcd_filter('out', $lbl['page_name']) ?></h2>
        <p class="gdrcd-muted">Capitoli dell'ambientazione di gioco: numero, titolo e testo (bbcode supportato).</p>
    </header>

    <?php if ($op === 'insert'):
        gdrcd_query(
            "INSERT INTO ambientazione (capitolo, titolo, testo) VALUES ("
            . gdrcd_filter('num', $_POST['articolo']) . ","
            . "'" . gdrcd_filter('in', $_POST['titolo']) . "',"
            . "'" . gdrcd_filter('in', $_POST['testo']) . "')"
        );
        ?>
        <div class="gdrcd-alert-success">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <div>
                <?= gdrcd_filter('out', $MESSAGE['warning']['inserted']) ?>
                <span class="text-gdrcd-muted">·</span>
                <strong class="text-gdrcd-text"><?= gdrcd_filter('out', $_POST['titolo']) ?></strong>
            </div>
        </div>
        <div><?= $render_back() ?></div>

    <?php elseif ($op === 'erase'):
        gdrcd_query("DELETE FROM ambientazione WHERE capitolo = " . gdrcd_filter('num', $_POST['id_record']) . " LIMIT 1");
        ?>
        <div class="gdrcd-alert-success">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <div><?= gdrcd_filter('out', $MESSAGE['warning']['deleted']) ?></div>
        </div>
        <div><?= $render_back() ?></div>

    <?php elseif ($op === 'doedit'):
        gdrcd_query(
            "UPDATE ambientazione SET
                titolo = '" . gdrcd_filter('in', $_POST['titolo']) . "',
                testo = '" . gdrcd_filter('in', $_POST['testo']) . "',
                capitolo = " . gdrcd_filter('num', $_POST['articolo']) . "
             WHERE capitolo = " . gdrcd_filter('num', $_POST['art']) . " LIMIT 1"
        );
        ?>
        <div class="gdrcd-alert-success">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <div>
                <?= gdrcd_filter('out', $MESSAGE['warning']['modified']) ?>
                <span class="text-gdrcd-muted">·</span>
                <strong class="text-gdrcd-text"><?= gdrcd_filter('out', $_POST['titolo']) ?></strong>
            </div>
        </div>
        <div><?= $render_back() ?></div>

    <?php elseif ($op === 'edit' || $op === 'new'):
        $is_edit = ($op === 'edit');
        $loaded = $is_edit
            ? gdrcd_query("SELECT * FROM ambientazione WHERE capitolo = " . gdrcd_filter('num', $_POST['id_record']) . " LIMIT 1")
            : ['capitolo' => 0, 'titolo' => '', 'testo' => ''];
        ?>
        <section class="gdrcd-card">
            <div class="gdrcd-card-header">
                <h3 class="gdrcd-h3"><?= $is_edit ? 'Modifica capitolo' : 'Nuovo capitolo' ?></h3>
            </div>
            <div class="gdrcd-card-body">
                <form action="main.php?page=gestione_ambientazione" method="post" class="space-y-5">
                    <?= gdrcd_csrf_field() ?>
                    <div class="grid grid-cols-1 md:grid-cols-[8rem_minmax(0,1fr)] gap-4">
                        <div>
                            <label class="gdrcd-label" for="amb_art"><?= gdrcd_filter('out', $lbl['art']) ?></label>
                            <input class="gdrcd-input" type="number" id="amb_art" name="articolo"
                                   value="<?= (int)$loaded['capitolo'] ?>" required/>
                        </div>
                        <div>
                            <label class="gdrcd-label" for="amb_titolo"><?= gdrcd_filter('out', $lbl['title']) ?></label>
                            <input class="gdrcd-input" type="text" id="amb_titolo" name="titolo"
                                   value="<?= gdrcd_filter('out', $loaded['titolo']) ?>" required/>
                        </div>
                    </div>

                    <div>
                        <label class="gdrcd-label" for="amb_testo"><?= gdrcd_filter('out', $lbl['infos']) ?></label>
                        <textarea class="gdrcd-textarea" id="amb_testo" name="testo" rows="14" data-bbcode><?= gdrcd_filter('out', $loaded['testo']) ?></textarea>
                        <p class="gdrcd-help"><?= gdrcd_filter('out', $MESSAGE['interface']['help']['bbcode']) ?></p>
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row gap-3 sm:justify-end pt-2 border-t border-gdrcd-border">
                        <a href="main.php?page=gestione_ambientazione" class="gdrcd-btn-ghost">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                            Annulla
                        </a>
                        <?php if ($is_edit): ?>
                            <input type="hidden" name="art" value="<?= (int)$loaded['capitolo'] ?>"/>
                            <input type="hidden" name="op" value="doedit"/>
                            <button type="submit" class="gdrcd-btn-primary">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                <?= gdrcd_filter('out', $MESSAGE['interface']['forms']['modify']) ?>
                            </button>
                        <?php else: ?>
                            <input type="hidden" name="op" value="insert"/>
                            <button type="submit" class="gdrcd-btn-primary">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                <?= gdrcd_filter('out', $MESSAGE['interface']['forms']['submit']) ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </section>

    <?php else:
        $offset    = (int)($_REQUEST['offset'] ?? 0);
        $per_page  = (int)$PARAMETERS['settings']['records_per_page'];
        $pagebegin = $offset * $per_page;

        $count_row     = gdrcd_query("SELECT COUNT(*) AS c FROM ambientazione");
        $totaleresults = (int)$count_row['c'];

        $result = gdrcd_query(
            "SELECT capitolo, titolo FROM ambientazione ORDER BY capitolo LIMIT " . $pagebegin . ", " . $per_page,
            'result'
        );
        $numresults = (int)gdrcd_query($result, 'num_rows');
        ?>

        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <span class="gdrcd-muted text-xs"><?= $totaleresults ?> capitoli</span>
            <a href="main.php?page=gestione_ambientazione&op=new" class="gdrcd-btn-primary">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                <?= gdrcd_filter('out', $lbl['link']['new']) ?>
            </a>
        </div>

        <?php if ($numresults === 0): ?>
            <div class="gdrcd-alert-info">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>Nessun capitolo presente.</div>
            </div>
        <?php else: ?>
            <div class="gdrcd-table-wrap">
                <table class="gdrcd-table">
                    <thead>
                        <tr>
                            <th class="w-20"><?= gdrcd_filter('out', $lbl['art']) ?></th>
                            <th><?= gdrcd_filter('out', $lbl['titolo']) ?></th>
                            <th class="text-right w-[120px]"><span class="sr-only">Azioni</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = gdrcd_query($result, 'fetch')): ?>
                            <tr>
                                <td class="text-gdrcd-muted tabular-nums whitespace-nowrap">
                                    <?= (int)$row['capitolo'] ?>
                                </td>
                                <td class="font-medium text-gdrcd-text">
                                    <?= gdrcd_filter('out', $row['titolo']) ?>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <form action="main.php?page=gestione_ambientazione" method="post" class="inline-block">
                                        <?= gdrcd_csrf_field() ?>
                                        <input type="hidden" name="id_record" value="<?= (int)$row['capitolo'] ?>"/>
                                        <input type="hidden" name="op" value="edit"/>
                                        <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-gdrcd-muted hover:bg-gdrcd-accent-soft hover:text-gdrcd-accent transition-colors"
                                                title="<?= gdrcd_filter('out', $MESSAGE['interface']['administration']['ops']['edit']) ?>">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        </button>
                                    </form>
                                    <form action="main.php?page=gestione_ambientazione" method="post" class="inline-block"
                                          onsubmit="return confirm('Eliminare questo capitolo?');">
                                        <?= gdrcd_csrf_field() ?>
                                        <input type="hidden" name="id_record" value="<?= (int)$row['capitolo'] ?>"/>
                                        <input type="hidden" name="op" value="erase"/>
                                        <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-gdrcd-muted hover:bg-gdrcd-error-soft hover:text-gdrcd-error transition-colors"
                                                title="<?= gdrcd_filter('out', $MESSAGE['interface']['administration']['ops']['erase']) ?>">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/></svg>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile;
                        gdrcd_query($result, 'free');
                        ?>
                    </tbody>
                </table>
            </div>

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
                                'page' => 'gestione_ambientazione', 'offset' => $i,
                            ]); ?>
                            <a href="<?= htmlspecialchars($url) ?>"><?= $i + 1 ?></a>
                        <?php endif;
                    endfor; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>

    <?php endif; ?>

</div>
