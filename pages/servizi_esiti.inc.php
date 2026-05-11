<?php
/**
 * Servizi — Pannello esiti personali del PG.
 */

$op = $_POST['op'] ?? null;
?>

<div class="space-y-6">
    <header class="space-y-1">
        <h2 class="gdrcd-h1 flex items-center gap-3">
            <span class="gdrcd-icon-circle">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
            </span>
            Pannello esiti personali
        </h2>
        <p class="text-gdrcd-text-soft text-sm"><?= $MESSAGE['interface']['esiti']['pg_page'] ?></p>
    </header>

    <?php if ($op === 'listpg'):
        $id = gdrcd_filter('num', $_POST['id']);
        $head_q = gdrcd_query("SELECT * FROM blocco_esiti
                               WHERE id = " . $id . "
                               AND pg = '" . gdrcd_filter('in', $_SESSION['login']) . "'
                               ORDER BY id", 'result');
        $head = gdrcd_query($head_q, 'fetch');
        gdrcd_query("UPDATE esiti SET letto_pg = 1 WHERE id_blocco = " . $id);
        $esiti_q = gdrcd_query("SELECT * FROM esiti
                                WHERE id_blocco = " . $id . " AND chat = 0
                                AND pg = '" . gdrcd_filter('in', $_SESSION['login']) . "'
                                ORDER BY data DESC", 'result');
    ?>
        <article class="gdrcd-card">
            <header class="gdrcd-card-header flex items-center justify-between">
                <h3 class="gdrcd-h3"><?= gdrcd_filter('out', $head['titolo']) ?></h3>
                <?php if ($head['closed'] == 0): ?>
                    <span class="gdrcd-badge-accent">Aperta</span>
                <?php else: ?>
                    <span class="gdrcd-badge-neutral">Chiusa</span>
                <?php endif; ?>
            </header>
            <div class="gdrcd-card-body space-y-3">
                <?php while ($r = gdrcd_query($esiti_q, 'fetch')): ?>
                    <article class="border border-gdrcd-border rounded-md p-3 bg-gdrcd-panel-alt/30">
                        <div class="text-xs text-gdrcd-text-soft mb-1">
                            Autore: <strong class="text-gdrcd-text"><?= gdrcd_filter('out', $r['autore']) ?></strong>
                            · Creato il <?= gdrcd_format_date($r['data']) ?> alle <?= gdrcd_format_time($r['data']) ?>
                        </div>
                        <div class="font-display text-base text-gdrcd-accent">
                            <?= gdrcd_filter('out', $r['titolo']) ?>
                        </div>
                        <?php if ($r['dice_face'] > 0 && $r['dice_num'] > 0 && TIRI_ESITO): ?>
                            <div class="text-sm text-gdrcd-text-soft mt-1">
                                Tiro <?= (int)$r['dice_num'] ?>d<?= (int)$r['dice_face'] ?>:
                                <strong class="text-gdrcd-text"><?= htmlspecialchars($r['dice_results']) ?></strong>
                            </div>
                        <?php endif; ?>
                        <div class="mt-2 text-sm text-gdrcd-text leading-relaxed">
                            <?= $r['contenuto'] ?>
                        </div>
                        <?php if (!empty($r['noteoff'])): ?>
                            <div class="mt-2 text-xs italic text-gdrcd-muted border-l-2 border-gdrcd-border pl-2">
                                <strong>Note OFF:</strong> <?= htmlspecialchars($r['noteoff']) ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endwhile; ?>
            </div>
        </article>

        <div class="flex flex-wrap gap-2">
            <?php if ($head['closed'] == 0): ?>
                <a href="main.php?page=servizi_esitinew&op=new&blocco=<?= (int)$id ?>" class="gdrcd-btn-primary">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Invia una nuova richiesta di esito
                </a>
            <?php endif; ?>
            <a href="main.php?page=servizi_esiti" class="gdrcd-btn-ghost">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Torna indietro
            </a>
        </div>

    <?php else:
        $offset = (int)($_REQUEST['offset'] ?? 0);
        $per_page = (int)$PARAMETERS['settings']['posts_per_page'];
        $pagebegin = $offset * $per_page;
        $tot_q = gdrcd_query("SELECT COUNT(*) AS n FROM blocco_esiti
                              WHERE pg = '" . gdrcd_filter('in', $_SESSION['login']) . "'");
        $totaleresults = (int)$tot_q['n'];

        $result = gdrcd_query(
            "SELECT * FROM blocco_esiti
             WHERE pg = '" . gdrcd_filter('in', $_SESSION['login']) . "'
             ORDER BY id DESC LIMIT " . $pagebegin . ", " . $per_page,
            'result'
        );
        $num = gdrcd_query($result, 'num_rows');
    ?>
        <?php if ($num === 0): ?>
            <div class="gdrcd-alert-info">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01"/></svg>
                <div>Nessuna serie di esiti aperta.</div>
            </div>
        <?php else: ?>
            <article class="gdrcd-card">
                <div class="overflow-x-auto">
                    <table class="gdrcd-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Nome master</th>
                                <th>Titolo</th>
                                <th class="tabular-nums">Esiti</th>
                                <th class="text-right">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($rec = gdrcd_query($result, 'fetch')):
                            $n = (int)gdrcd_query(gdrcd_query(
                                "SELECT id FROM esiti WHERE id_blocco = " . (int)$rec['id'] . "
                                 AND autore != '" . gdrcd_filter('in', $_SESSION['login']) . "'", 'result'), 'num_rows');
                            $nuovi = (int)gdrcd_query(gdrcd_query(
                                "SELECT id FROM esiti WHERE id_blocco = " . (int)$rec['id'] . "
                                 AND autore != '" . gdrcd_filter('in', $_SESSION['login']) . "' AND letto_pg = 0", 'result'), 'num_rows');
                        ?>
                            <tr>
                                <td class="tabular-nums text-sm"><?= gdrcd_filter('out', gdrcd_format_date($rec['data'])) ?></td>
                                <td class="text-sm">
                                    <?php if ($rec['master'] === '0' || empty($rec['master'])): ?>
                                        <span class="text-gdrcd-text-soft italic">In attesa di risposta</span>
                                    <?php else: ?>
                                        <?= gdrcd_filter('out', $rec['master']) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-sm font-display"><?= gdrcd_filter('out', $rec['titolo']) ?></td>
                                <td class="tabular-nums">
                                    <?= $n ?>
                                    <?php if ($nuovi > 0): ?>
                                        <span class="gdrcd-badge-accent text-[10px] ml-1"><?= $nuovi ?> nuov<?= $nuovi === 1 ? 'o' : 'i' ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <form action="main.php?page=servizi_esiti" method="post" class="inline">
                                        <input type="hidden" name="op" value="listpg">
                                        <input type="hidden" name="id" value="<?= (int)$rec['id'] ?>">
                                        <button type="submit" class="gdrcd-btn-ghost text-xs">Apri serie</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </article>

            <?php if ($totaleresults > $per_page): ?>
                <nav class="gdrcd-pager flex flex-wrap gap-1 justify-center" aria-label="Paginazione">
                    <span class="text-sm text-gdrcd-text-soft mr-2"><?= gdrcd_filter('out', $MESSAGE['interface']['pager']['pages_name']) ?></span>
                    <?php for ($i = 0; $i <= floor($totaleresults / $per_page); $i++): ?>
                        <a href="main.php?page=servizi_esiti&offset=<?= $i ?>"
                           class="px-2 py-1 rounded text-sm <?= ((int)$offset === $i) ? 'bg-gdrcd-accent text-white' : 'hover:bg-gdrcd-accent-soft text-gdrcd-text-soft' ?>">
                            <?= $i + 1 ?>
                        </a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>

        <div>
            <a href="main.php?page=servizi_esitinew&op=first" class="gdrcd-btn-primary">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Apri una nuova serie di esiti
            </a>
        </div>
    <?php endif; ?>
</div>
