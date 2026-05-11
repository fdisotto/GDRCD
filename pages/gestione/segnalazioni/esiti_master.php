<?php
/**
 * Pannello esiti Master (main.php?page=gestione_segnalazioni&segn=esiti_master)
 *  - Vista lista: serie di esiti aperte, paginate
 *  - Vista dettaglio (POST op=list, id): un singolo blocco con tutti gli esiti
 */

if (!($_SESSION['permessi'] >= ESITI_PERM && ESITI)) {
    echo '<div class="gdrcd-alert-warning">'
       . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>'
       . '<div>Non hai i permessi per visualizzare questa sezione.</div>'
       . '</div>';
    return;
}

$op = $_POST['op'] ?? null;
?>

<div class="space-y-6">

    <header class="space-y-2">
        <h2 class="gdrcd-h1">Gestione esiti</h2>
        <p class="gdrcd-prose"><?= $MESSAGE['interface']['esiti']['gm_page'] ?></p>
    </header>

    <?php if ($op === 'list'):
        /* ============================================================
         * Vista dettaglio: singolo blocco esiti
         * ============================================================ */
        $id = gdrcd_filter('num', $_POST['id'] ?? 0);
        if ($_SESSION['permessi'] < FULL_PERM) {
            $query = gdrcd_query("SELECT * FROM blocco_esiti WHERE id = " . $id . "
                                  AND (master = '0' || master = '" . gdrcd_filter('in', $_SESSION['login']) . "')
                                  ORDER BY id", 'result');
        } else {
            $query = gdrcd_query("SELECT * FROM blocco_esiti WHERE id = " . $id . " ORDER BY id", 'result');
        }
        $blocco = gdrcd_query($query, 'fetch');

        if (empty($blocco)): ?>
            <div class="gdrcd-alert-error">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
                <div>Serie esiti non trovata o non accessibile.</div>
            </div>
            <div>
                <a href="main.php?page=gestione_segnalazioni&segn=esiti_master" class="gdrcd-btn-ghost">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Torna alla lista
                </a>
            </div>
        <?php
        else:
            gdrcd_query("UPDATE esiti SET letto_master = 1 WHERE id_blocco = " . gdrcd_filter('num', $blocco['id']));

            $res = gdrcd_query("SELECT * FROM esiti WHERE id_blocco = " . gdrcd_filter('num', $blocco['id']) . " ORDER BY data DESC", 'result');
            $is_closed = !empty($blocco['closed']);
            ?>
            <section class="gdrcd-card">
                <div class="gdrcd-card-header flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="gdrcd-h3">
                            <?= gdrcd_filter('out', $blocco['titolo']) ?>
                            <span class="text-gdrcd-muted">·</span>
                            <span class="text-gdrcd-accent"><?= gdrcd_filter('out', $blocco['pg']) ?></span>
                        </h3>
                        <?php if ($is_closed): ?>
                            <span class="gdrcd-badge-neutral mt-1">Chiuso</span>
                        <?php else: ?>
                            <span class="gdrcd-badge-success mt-1">Aperto</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!$is_closed): ?>
                        <a class="gdrcd-btn-secondary"
                           href="main.php?page=gestione_segnalazioni&segn=esito_index&op=edit&id=<?= gdrcd_filter('num', $blocco['id']) ?>">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                            Modifica
                        </a>
                    <?php endif; ?>
                </div>

                <div class="gdrcd-card-body space-y-5">
                    <?php while ($row = gdrcd_query($res, 'fetch')):
                        $abilita = gdrcd_query("SELECT nome FROM abilita WHERE id_abilita = " . (int)$row['id_ab']);
                        $chat    = gdrcd_query("SELECT nome FROM mappa WHERE id = " . (int)$row['chat']);
                        $is_chat = (int)$row['chat'] > 0;
                        $has_dice = ((int)$row['dice_face'] > 0 && (int)$row['dice_num'] > 0 && TIRI_ESITO);
                        ?>
                        <article class="border border-gdrcd-border rounded-gdrcd bg-gdrcd-panel-alt/30 p-4 space-y-3">
                            <header class="flex flex-wrap items-baseline justify-between gap-2 text-xs">
                                <div class="text-gdrcd-muted">
                                    Autore <strong class="text-gdrcd-text"><?= gdrcd_filter('out', $row['autore']) ?></strong>
                                </div>
                                <div class="text-gdrcd-muted">
                                    Creato il <?= gdrcd_format_date($row['data']) ?>
                                    <span class="text-gdrcd-subtle">·</span>
                                    <?= gdrcd_format_time($row['data']) ?>
                                </div>
                            </header>

                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="font-display font-semibold text-base text-gdrcd-text">
                                        <?= gdrcd_filter('out', $row['titolo']) ?>
                                    </h4>
                                    <?php if ($is_chat): ?>
                                        <span class="gdrcd-badge-accent">Esito in chat</span>
                                        <span class="gdrcd-muted text-xs">
                                            Chat: <?= gdrcd_filter('out', $chat['nome'] ?? '?') ?>
                                            <span class="text-gdrcd-subtle">·</span>
                                            Skill: <?= gdrcd_filter('out', $abilita['nome'] ?? '?') ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($has_dice): ?>
                                    <div class="text-xs text-gdrcd-muted">
                                        Risultato tiro <?= (int)$row['dice_num'] ?>d<?= (int)$row['dice_face'] ?>:
                                        <strong class="text-gdrcd-text font-mono"><?= gdrcd_filter('out', $row['dice_results']) ?></strong>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="gdrcd-prose">
                                <?php if ($is_chat): ?>
                                    <ul class="space-y-1 list-disc list-inside">
                                        <li><strong>Fallimento critico:</strong> <?= gdrcd_filter('out', $row['CD_1']) ?></li>
                                        <li><strong>Fallimento:</strong> <?= gdrcd_filter('out', $row['CD_2']) ?></li>
                                        <li><strong>Successo:</strong> <?= gdrcd_filter('out', $row['CD_3']) ?></li>
                                        <li><strong>Successo critico:</strong> <?= gdrcd_filter('out', $row['CD_4']) ?></li>
                                    </ul>
                                <?php else: ?>
                                    <?= $row['contenuto'] ?>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($row['noteoff'])): ?>
                                <div class="text-xs">
                                    <span class="gdrcd-eyebrow">Note OFF</span>
                                    <div class="mt-1 text-gdrcd-text-soft"><?= gdrcd_filter('out', $row['noteoff']) ?></div>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endwhile; ?>
                </div>
            </section>

            <div class="flex flex-wrap gap-2">
                <?php if (!$is_closed): ?>
                    <a class="gdrcd-btn-primary"
                       href="main.php?page=gestione_segnalazioni&segn=esito_index&op=new&blocco=<?= gdrcd_filter('num', $blocco['id']) ?>">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Invia un nuovo esito
                    </a>
                    <?php if (ESITI_CHAT): ?>
                        <a class="gdrcd-btn-secondary"
                           href="main.php?page=gestione_segnalazioni&segn=esito_index&op=newchat&blocco=<?= gdrcd_filter('num', $blocco['id']) ?>">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            Invia un esito in chat
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
                <a href="main.php?page=gestione_segnalazioni&segn=esiti_master" class="gdrcd-btn-ghost ml-auto">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Torna alla lista
                </a>
            </div>
        <?php endif; ?>

    <?php elseif ($op === null):
        /* ============================================================
         * Vista lista: tutti i blocchi accessibili
         * ============================================================ */
        $offset    = (int)($_REQUEST['offset'] ?? 0);
        $per_page  = (int)$PARAMETERS['settings']['posts_per_page'];
        $pagebegin = $offset * $per_page;

        $count_row     = gdrcd_query("SELECT COUNT(*) AS c FROM blocco_esiti");
        $totaleresults = (int)$count_row['c'];

        if ($_SESSION['permessi'] < FULL_PERM) {
            $query = "SELECT * FROM blocco_esiti
                      WHERE (master = '0' || master = '" . gdrcd_filter('in', $_SESSION['login']) . "')
                      ORDER BY closed, data DESC, pg
                      LIMIT " . $pagebegin . ", " . $per_page;
        } else {
            $query = "SELECT * FROM blocco_esiti
                      ORDER BY closed, data DESC, pg
                      LIMIT " . $pagebegin . ", " . $per_page;
        }
        $blocco     = gdrcd_query($query, 'result');
        $numresults = (int)gdrcd_query($blocco, 'num_rows');
        ?>

        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <span class="gdrcd-muted text-xs"><?= $totaleresults ?> serie di esiti</span>
            <a href="main.php?page=gestione_segnalazioni&segn=esito_index&op=first" class="gdrcd-btn-primary">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Apri nuova serie
            </a>
        </div>

        <?php if ($numresults === 0): ?>
            <div class="gdrcd-alert-info">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>Nessuna serie di esiti aperta.</div>
            </div>
        <?php else: ?>
            <div class="gdrcd-table-wrap">
                <table class="gdrcd-table">
                    <thead>
                        <tr>
                            <th class="whitespace-nowrap w-[110px]">Data</th>
                            <th class="whitespace-nowrap">PG</th>
                            <th class="whitespace-nowrap">Stato</th>
                            <th>Titolo</th>
                            <th class="whitespace-nowrap text-right">Esiti</th>
                            <th class="text-right w-[140px]"><span class="sr-only">Azioni</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($rec = gdrcd_query($blocco, 'fetch')):
                            $num = (int)gdrcd_query(gdrcd_query(
                                "SELECT id FROM esiti WHERE id_blocco = " . gdrcd_filter('num', $rec['id']) .
                                " AND autore != '" . gdrcd_filter('in', $rec['pg']) . "' ORDER BY master, data DESC",
                                'result'
                            ), 'num_rows');
                            $new = (int)gdrcd_query(gdrcd_query(
                                "SELECT id FROM esiti WHERE id_blocco = " . gdrcd_filter('num', $rec['id']) .
                                " AND letto_master = 0",
                                'result'
                            ), 'num_rows');
                            $taken = ((string)$rec['master'] !== '0');
                            ?>
                            <tr>
                                <td class="text-gdrcd-muted whitespace-nowrap">
                                    <?= gdrcd_format_date($rec['data']) ?>
                                </td>
                                <td class="font-medium text-gdrcd-text whitespace-nowrap">
                                    <?= gdrcd_filter('out', $rec['pg']) ?>
                                </td>
                                <td class="whitespace-nowrap">
                                    <?php if ($taken): ?>
                                        <span class="gdrcd-badge-success">Presa in carico</span>
                                    <?php else: ?>
                                        <span class="gdrcd-badge-accent">In attesa</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= gdrcd_filter('out', $rec['titolo']) ?></td>
                                <td class="whitespace-nowrap text-right">
                                    <span class="inline-flex items-center gap-2">
                                        <span class="font-semibold text-gdrcd-text tabular-nums"><?= $num ?></span>
                                        <?php if ($new > 0): ?>
                                            <span class="gdrcd-badge-error"><?= $new ?> nuovi</span>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td class="whitespace-nowrap text-right">
                                    <form action="main.php?page=gestione_segnalazioni&segn=esiti_master" method="post" class="inline-block">
                                        <input type="hidden" name="op" value="list"/>
                                        <input type="hidden" name="id" value="<?= (int)$rec['id'] ?>"/>
                                        <button type="submit" class="gdrcd-btn-secondary">
                                            Apri
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile;
                        gdrcd_query($blocco, 'free');
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
                                'page' => 'gestione_segnalazioni',
                                'segn' => 'esiti_master',
                                'offset' => $i,
                            ]); ?>
                            <a href="<?= htmlspecialchars($url) ?>"><?= $i + 1 ?></a>
                        <?php endif;
                    endfor; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>

    <?php endif; ?>

</div>
