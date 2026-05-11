<?php
/**
 * Giocate segnalate (main.php?page=gestione_segnalazioni&segn=roles_gm)
 * Elenco paginato delle segnalazioni di ruolo.
 */

if ($_SESSION['permessi'] < ROLE_PERM) {
    echo '<div class="gdrcd-alert-error">'
       . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>'
       . '<div>' . gdrcd_filter('out', $MESSAGE['error']['not_allowed']) . '</div>'
       . '</div>';
    return;
}

$offset    = (int)($_REQUEST['offset'] ?? 0);
$per_page  = (int)$PARAMETERS['settings']['posts_per_page'];
$pagebegin = $offset * $per_page;

$count_row     = gdrcd_query("SELECT COUNT(*) AS c FROM send_GM");
$totaleresults = (int)$count_row['c'];

$result = gdrcd_query(
    "SELECT * FROM send_GM ORDER BY data DESC LIMIT " . $pagebegin . ", " . $per_page,
    'result'
);
$numresults = (int)gdrcd_query($result, 'num_rows');
?>

<div class="space-y-6">

    <header class="space-y-2">
        <h2 class="gdrcd-h1">Giocate segnalate</h2>
        <p class="gdrcd-muted">Segnalazioni di ruolo inviate dai giocatori, in ordine cronologico inverso.</p>
    </header>

    <div class="flex items-baseline gap-2">
        <span class="gdrcd-muted text-xs ml-auto"><?= $totaleresults ?> segnalazioni</span>
    </div>

    <?php if ($numresults === 0): ?>
        <div class="gdrcd-alert-info">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div>Nessuna segnalazione presente.</div>
        </div>
    <?php else: ?>
        <div class="gdrcd-table-wrap">
            <table class="gdrcd-table">
                <thead>
                    <tr>
                        <th class="whitespace-nowrap">Data</th>
                        <th class="whitespace-nowrap">Autore</th>
                        <th>Note</th>
                        <th>Partecipanti</th>
                        <th class="whitespace-nowrap">Chat</th>
                        <th class="whitespace-nowrap">Tag quest</th>
                        <th class="whitespace-nowrap"><?= gdrcd_filter('out', $MESSAGE['interface']['administration']['ops_col']) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = gdrcd_query($result, 'fetch')):
                        $roles_f = gdrcd_query("SELECT * FROM segnalazione_role WHERE id = " . (int)$row['role_reg']);
                        $room    = gdrcd_query("SELECT nome FROM mappa WHERE id = '" . gdrcd_filter('in', $roles_f['stanza'] ?? '') . "' LIMIT 1");
                        ?>
                        <tr>
                            <td class="text-gdrcd-muted whitespace-nowrap">
                                <?= gdrcd_format_date($row['data']) ?>
                            </td>
                            <td class="font-medium text-gdrcd-text whitespace-nowrap">
                                <?= gdrcd_filter('out', $row['autore']) ?>
                            </td>
                            <td class="max-w-md">
                                <?= gdrcd_filter('out', $row['note']) ?>
                            </td>
                            <td class="text-gdrcd-text-soft">
                                <?= gdrcd_filter('out', $roles_f['partecipanti'] ?? '') ?>
                            </td>
                            <td class="text-gdrcd-text-soft whitespace-nowrap">
                                <?= gdrcd_filter('out', $room['nome'] ?? '') ?>
                            </td>
                            <td class="whitespace-nowrap">
                                <?php if (!empty($roles_f['quest'])): ?>
                                    <span class="gdrcd-badge-accent"><?= gdrcd_filter('out', $roles_f['quest']) ?></span>
                                <?php else: ?>
                                    <span class="text-gdrcd-subtle">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="whitespace-nowrap">
                                <form action="popup.php?page=scheda_roles&pg=<?= urlencode($row['autore']) ?>" method="post">
                                    <input type="hidden" name="op" value="log"/>
                                    <input type="hidden" name="id" value="<?= (int)$row['role_reg'] ?>"/>
                                    <button type="submit" class="gdrcd-btn-secondary">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                        Apri log chat
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
                            'page' => 'gestione_segnalazioni',
                            'segn' => 'roles_gm',
                            'offset' => $i,
                        ]); ?>
                        <a href="<?= htmlspecialchars($url) ?>"><?= $i + 1 ?></a>
                    <?php endif;
                endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>

</div>
