<?php
/**
 * Log eventi (main.php?page=log_eventi)
 * Filtra il log per tipo evento; risultati paginati.
 */

/* ---------- Permessi ---------- */
if ($_SESSION['permessi'] < SUPERUSER) {
    echo '<div class="gdrcd-alert-error">'
       . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>'
       . '<div>' . gdrcd_filter('out', $MESSAGE['error']['not_allowed']) . '</div>'
       . '</div>';
    return;
}

$op         = $_REQUEST['op']         ?? null;
$which_log  = $_REQUEST['which_log']  ?? null;
$offset     = (int)($_REQUEST['offset'] ?? 0);
$per_page   = (int)$PARAMETERS['settings']['records_per_page'];
$pagebegin  = $offset * $per_page;

$lbl = $MESSAGE['interface']['administration']['log']['events'];

/* Mask IPv4: 'a.b.c.d' → 'a.b.X.X' (per eventi login/blocco). */
$mask_ip = function (string $s): string {
    $list = explode('.', $s);
    if (count($list) >= 4) {
        $list[2] = 'X';
        $list[3] = 'X';
        return implode('.', $list);
    }
    return $s;
};

$is_login_event = function ($code): bool {
    return in_array((int)$code, [BLOCKED, LOGGEDIN, ERRORELOGIN], true);
};
?>

<div class="space-y-6">

    <header class="space-y-2">
        <h2 class="gdrcd-h1"><?= gdrcd_filter('out', $lbl['page_name']) ?></h2>
        <p class="gdrcd-muted">Storico eventi di gioco filtrabile per tipologia.</p>
    </header>

    <?php if ($op === null): ?>
        <section class="gdrcd-card">
            <div class="gdrcd-card-body">
                <form action="main.php?page=log_eventi" method="post" class="space-y-4">
                    <div>
                        <label class="gdrcd-label" for="le_which_log">
                            <?= gdrcd_filter('out', $lbl['log_type']) ?>
                        </label>
                        <select class="gdrcd-select" id="le_which_log" name="which_log">
                            <option value="0">Tutti</option>
                            <?php $count = 1; foreach ($MESSAGE['event'] as $event): ?>
                                <option value="<?= $count ?>"><?= gdrcd_filter('out', $event) ?></option>
                            <?php $count++; endforeach; ?>
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

    <?php elseif ($op === 'view' && is_numeric($which_log)):
        $which_log = (int)$which_log;
        $show_all  = $which_log === 0;

        $where_sql  = $show_all ? '' : 'WHERE codice_evento = ' . $which_log;
        $count_row     = gdrcd_query("SELECT COUNT(*) AS c FROM log " . $where_sql);
        $totaleresults = (int)$count_row['c'];

        $result = gdrcd_query(
            "SELECT codice_evento, autore, nome_interessato, data_evento, descrizione_evento
             FROM log
             " . $where_sql . "
             ORDER BY data_evento DESC
             LIMIT " . $pagebegin . ", " . $per_page,
            'result'
        );
        $numresults = (int)gdrcd_query($result, 'num_rows');

        // Etichetta tipo evento (1-based su array $MESSAGE['event'])
        $event_keys  = array_keys($MESSAGE['event']);
        $event_label = $show_all
            ? 'Tutti'
            : ($MESSAGE['event'][$event_keys[$which_log - 1] ?? ''] ?? '');
        ?>
        <section class="space-y-3">
            <div class="flex flex-wrap items-baseline gap-2">
                <h3 class="gdrcd-h3"><?= gdrcd_filter('out', $lbl['log_type']) ?></h3>
                <span class="gdrcd-badge-accent"><?= gdrcd_filter('out', $event_label) ?></span>
                <span class="gdrcd-muted text-xs ml-auto"><?= $totaleresults ?> risultati</span>
            </div>

            <?php if ($numresults > 0): ?>
                <div class="gdrcd-table-wrap">
                    <table class="gdrcd-table">
                        <thead>
                            <tr>
                                <?php if ($show_all): ?>
                                    <th class="whitespace-nowrap"><?= gdrcd_filter('out', $lbl['log_type']) ?></th>
                                <?php endif; ?>
                                <th><?= gdrcd_filter('out', $lbl['author']) ?></th>
                                <th><?= gdrcd_filter('out', $lbl['dest']) ?></th>
                                <th class="whitespace-nowrap"><?= gdrcd_filter('out', $lbl['date']) ?></th>
                                <th><?= gdrcd_filter('out', $lbl['descr']) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = gdrcd_query($result, 'fetch')):
                                $row_code = (int)$row['codice_evento'];
                                $row_is_login = $is_login_event($row_code);
                                $autore = $row_is_login ? $mask_ip($row['autore']) : $row['autore'];
                                $descr  = $row_is_login ? $mask_ip($row['descrizione_evento']) : $row['descrizione_evento'];
                                $row_label = $MESSAGE['event'][$row_code] ?? ('#' . $row_code);
                                ?>
                                <tr>
                                    <?php if ($show_all): ?>
                                        <td class="whitespace-nowrap">
                                            <span class="gdrcd-badge-neutral"><?= gdrcd_filter('out', $row_label) ?></span>
                                        </td>
                                    <?php endif; ?>
                                    <td class="font-mono text-xs text-gdrcd-text whitespace-nowrap">
                                        <?= gdrcd_filter('out', $autore) ?>
                                    </td>
                                    <td class="font-medium text-gdrcd-text whitespace-nowrap">
                                        <?php if (!empty($row['nome_interessato'])): ?>
                                            <a class="gdrcd-link" href="main.php?page=scheda&pg=<?= urlencode($row['nome_interessato']) ?>">
                                                <?= gdrcd_filter('out', $row['nome_interessato']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gdrcd-subtle">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-gdrcd-muted whitespace-nowrap">
                                        <?= gdrcd_format_date($row['data_evento']) ?>
                                        <span class="text-gdrcd-subtle">·</span>
                                        <?= gdrcd_format_time($row['data_evento']) ?>
                                    </td>
                                    <td><?= gdrcd_filter('out', $descr) ?></td>
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
                                    'page' => 'log_eventi', 'op' => 'view',
                                    'which_log' => $which_log, 'offset' => $i,
                                ]); ?>
                                <a href="<?= htmlspecialchars($url) ?>"><?= $i + 1 ?></a>
                            <?php endif;
                        endfor; ?>
                    </nav>
                <?php endif; ?>

            <?php else: ?>
                <div class="gdrcd-alert-info">
                    <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>Nessun evento registrato per questa tipologia.</div>
                </div>
            <?php endif; ?>

            <div>
                <a href="main.php?page=log_eventi" class="gdrcd-btn-ghost">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <?= gdrcd_filter('out', $lbl['link']['back']) ?>
                </a>
            </div>
        </section>
    <?php endif; ?>

</div>
