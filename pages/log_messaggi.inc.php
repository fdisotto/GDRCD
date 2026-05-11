<?php
/**
 * Log messaggi privati (main.php?page=log_messaggi)
 * Filtra per PG mittente, risultati paginati.
 */

/* ---------- Permessi ---------- */
if (($_SESSION['permessi'] < MODERATOR) || ($PARAMETERS['mode']['spymessages'] !== 'ON')) {
    echo '<div class="gdrcd-alert-error">'
       . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>'
       . '<div>' . gdrcd_filter('out', $MESSAGE['error']['not_allowed']) . '</div>'
       . '</div>';
    return;
}

$op        = $_REQUEST['op']     ?? null;
$pg        = $_REQUEST['pg']     ?? '';
$offset    = (int)($_REQUEST['offset'] ?? 0);
$per_page  = (int)$PARAMETERS['settings']['records_per_page'];
$pagebegin = $offset * $per_page;

$lbl = $MESSAGE['interface']['administration']['log']['messages'];
?>

<div class="space-y-6">

    <header class="space-y-2">
        <h2 class="gdrcd-h1"><?= gdrcd_filter('out', $lbl['page_name']) ?></h2>
        <p class="gdrcd-muted">Storico messaggi privati filtrabile per personaggio mittente.</p>
    </header>

    <?php if ($op === null): ?>
        <section class="gdrcd-card">
            <div class="gdrcd-card-body">
                <form action="main.php?page=log_messaggi" method="post" class="space-y-4">
                    <?= gdrcd_csrf_field() ?>
                    <div>
                        <label class="gdrcd-label" for="lm_pg">
                            <?= gdrcd_filter('out', $lbl['log_type']) ?>
                        </label>
                        <select class="gdrcd-select" id="lm_pg" name="pg">
                            <?php
                            $result = gdrcd_query("SELECT nome FROM personaggio WHERE permessi > " . DELETED . " ORDER BY nome", 'result');
                            while ($row = gdrcd_query($result, 'fetch')): ?>
                                <option value="<?= gdrcd_filter('out', $row['nome']) ?>"><?= gdrcd_filter('out', $row['nome']) ?></option>
                            <?php endwhile;
                            gdrcd_query($result, 'free');
                            ?>
                        </select>
                    </div>
                    <input type="hidden" name="op" value="view"/>
                    <button type="submit" class="gdrcd-btn-primary">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <?= gdrcd_filter('out', $MESSAGE['interface']['forms']['submit']) ?>
                    </button>
                </form>
            </div>
        </section>

    <?php elseif ($op === 'view'):
        $count_row     = gdrcd_query("SELECT COUNT(*) AS c FROM backmessaggi WHERE mittente = '" . gdrcd_filter('in', $pg) . "'");
        $totaleresults = (int)$count_row['c'];

        $result = gdrcd_query(
            "SELECT destinatario, spedito, testo
             FROM backmessaggi
             WHERE mittente = '" . gdrcd_filter('in', $pg) . "'
             ORDER BY spedito DESC
             LIMIT " . $pagebegin . ", " . $per_page,
            'result'
        );
        $numresults = (int)gdrcd_query($result, 'num_rows');
        ?>
        <section class="space-y-3">
            <div class="flex flex-wrap items-baseline gap-2">
                <h3 class="gdrcd-h3"><?= gdrcd_filter('out', $lbl['log_type']) ?></h3>
                <span class="gdrcd-badge-accent"><?= htmlspecialchars($pg) ?></span>
                <span class="gdrcd-muted text-xs ml-auto"><?= $totaleresults ?> risultati</span>
            </div>

            <?php if ($numresults > 0): ?>
                <div class="gdrcd-table-wrap">
                    <table class="gdrcd-table">
                        <thead>
                            <tr>
                                <th><?= gdrcd_filter('out', $lbl['dest']) ?></th>
                                <th class="whitespace-nowrap"><?= gdrcd_filter('out', $lbl['date']) ?></th>
                                <th><?= gdrcd_filter('out', $lbl['text']) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = gdrcd_query($result, 'fetch')): ?>
                                <tr>
                                    <td class="font-medium text-gdrcd-text whitespace-nowrap">
                                        <?php if (!empty($row['destinatario'])): ?>
                                            <a class="gdrcd-link" href="main.php?page=scheda&pg=<?= urlencode($row['destinatario']) ?>">
                                                <?= gdrcd_filter('out', $row['destinatario']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gdrcd-subtle">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-gdrcd-muted whitespace-nowrap">
                                        <?= gdrcd_format_date($row['spedito']) ?>
                                        <span class="text-gdrcd-subtle">·</span>
                                        <?= gdrcd_format_time($row['spedito']) ?>
                                    </td>
                                    <td><?= gdrcd_filter('out', $row['testo']) ?></td>
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
                                    'page' => 'log_messaggi', 'op' => 'view',
                                    'pg' => $pg, 'offset' => $i,
                                ]); ?>
                                <a href="<?= htmlspecialchars($url) ?>"><?= $i + 1 ?></a>
                            <?php endif;
                        endfor; ?>
                    </nav>
                <?php endif; ?>
            <?php else: ?>
                <div class="gdrcd-alert-info">
                    <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>Nessun messaggio inviato da questo personaggio.</div>
                </div>
            <?php endif; ?>

            <div>
                <a href="main.php?page=log_messaggi" class="gdrcd-btn-ghost">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <?= gdrcd_filter('out', $lbl['link']['back']) ?>
                </a>
            </div>
        </section>
    <?php endif; ?>

</div>
