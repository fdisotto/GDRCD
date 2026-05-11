<?php
/**
 * Scheda PG — risultati ricerca giocate.
 */

$pg     = $_REQUEST['pg'];
$pg_in  = gdrcd_filter('in', $pg);
$pg_url = gdrcd_filter('url', $pg);
$type   = (int)($_POST['type'] ?? 0);
$search = $_POST['search'] ?? '';
$srch_in = gdrcd_filter('in', $search);

$columns = [0 => ['col' => 'partecipanti', 'label' => 'personaggio'],
            1 => ['col' => 'tags',         'label' => 'tag'],
            2 => ['col' => 'quest',        'label' => 'quest']];
$col   = $columns[$type]['col']   ?? 'partecipanti';
$label = $columns[$type]['label'] ?? 'personaggio';

$mesi = [
    1 => 'Gennaio', 2 => 'Febbraio', 3 => 'Marzo', 4 => 'Aprile',
    5 => 'Maggio', 6 => 'Giugno', 7 => 'Luglio', 8 => 'Agosto',
    9 => 'Settembre', 10 => 'Ottobre', 11 => 'Novembre', 12 => 'Dicembre',
];

$total_res = gdrcd_query(
    "SELECT COUNT(*) AS n FROM segnalazione_role
     WHERE mittente = '" . $pg_in . "'
       AND $col LIKE '%" . $srch_in . "%'
       AND conclusa = 1",
    'result'
);
$totale = (int)gdrcd_query($total_res, 'fetch')['n'];

$render_back = function () use ($pg_url, $MESSAGE) { ?>
    <div>
        <a href="main.php?page=scheda_roles&pg=<?= $pg_url ?>" class="gdrcd-btn-ghost">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <?= gdrcd_filter('out', $MESSAGE['interface']['sheet']['link']['back_roles']) ?>
        </a>
    </div>
<?php };
?>

<div class="gdrcd-card p-4 flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-3">
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-md bg-gdrcd-accent-soft text-gdrcd-accent">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
        </span>
        <div>
            <div class="font-display text-base text-gdrcd-text">Ricerca giocate</div>
            <p class="text-xs text-gdrcd-text-soft">
                Per <strong><?= htmlspecialchars($label) ?></strong>:
                <span class="text-gdrcd-accent">«<?= gdrcd_filter('out', $search) ?>»</span>
            </p>
        </div>
    </div>
    <span class="gdrcd-badge-accent tabular-nums"><?= $totale ?> risultat<?= $totale === 1 ? 'o' : 'i' ?></span>
</div>

<?php
$year_res = gdrcd_query(
    "SELECT YEAR(data_inizio) AS year FROM segnalazione_role
     WHERE mittente = '" . $pg_in . "'
     GROUP BY YEAR(data_inizio) ORDER BY YEAR(data_inizio) DESC",
    'result'
);

$any = false;
while ($ry = gdrcd_query($year_res, 'fetch')):
    $y = (int)$ry['year'];

    $month_res = gdrcd_query(
        "SELECT MONTH(data_inizio) AS month FROM segnalazione_role
         WHERE mittente = '" . $pg_in . "'
           AND YEAR(data_inizio) = " . $y . "
         GROUP BY MONTH(data_inizio) ORDER BY MONTH(data_inizio) DESC",
        'result'
    );

    $months_html = '';
    while ($rm = gdrcd_query($month_res, 'fetch')):
        $m = (int)$rm['month'];
        $q = gdrcd_query(
            "SELECT * FROM segnalazione_role
             WHERE YEAR(data_inizio) = " . $y . "
               AND MONTH(data_inizio) = " . $m . "
               AND mittente = '" . $pg_in . "'
               AND $col LIKE '%" . $srch_in . "%'
               AND conclusa = 1
             ORDER BY data_inizio, data_fine",
            'result'
        );
        $num = gdrcd_query($q, 'num_rows');
        if ($num == 0) continue;
        $any = true;
        ob_start();
        ?>
        <article class="gdrcd-card overflow-hidden">
            <header class="flex items-center justify-between gap-3 px-4 py-3 border-b border-gdrcd-border bg-gdrcd-panel-alt/30">
                <h4 class="font-display text-lg text-gdrcd-accent flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <?= $mesi[$m] ?? $m ?> <?= $y ?>
                </h4>
                <span class="text-xs text-gdrcd-text-soft tabular-nums"><?= $num ?> giocat<?= $num === 1 ? 'a' : 'e' ?></span>
            </header>
            <div class="overflow-x-auto">
                <table class="gdrcd-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Chat</th>
                            <th>Partecipanti</th>
                            <th class="tabular-nums">Az.</th>
                            <th>Tag</th>
                            <th>Note quest</th>
                            <th class="text-right">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = gdrcd_query($q, 'fetch')):
                        $chat_r = gdrcd_query(
                            "SELECT nome FROM mappa WHERE id = " . gdrcd_filter('num', $row['stanza']),
                            'result'
                        );
                        $r_chat = gdrcd_query($chat_r, 'fetch');
                        $az_r = gdrcd_query(
                            "SELECT chat.id FROM chat INNER JOIN mappa ON mappa.id = chat.stanza
                             LEFT JOIN personaggio ON personaggio.nome = chat.mittente
                             WHERE stanza = " . gdrcd_filter('num', $row['stanza']) . "
                             AND ora >= '" . gdrcd_filter('in', $row['data_inizio']) . "'
                             AND ora <= '" . gdrcd_filter('in', $row['data_fine']) . "'
                             AND (tipo = 'A' OR tipo = 'P')",
                            'result'
                        );
                        $num_az = gdrcd_query($az_r, 'num_rows');
                        $parts = array_filter(array_map('trim', explode(',', $row['partecipanti'])));
                        $can_log = ($pg == $_SESSION['login']) || ($_SESSION['permessi'] >= MODERATOR);
                    ?>
                        <tr>
                            <td class="whitespace-nowrap text-sm">
                                <div class="font-display"><?= gdrcd_filter('out', gdrcd_format_date($row['data_inizio'])) ?></div>
                                <div class="text-xs text-gdrcd-text-soft tabular-nums">
                                    <?= gdrcd_format_time($row['data_inizio']) ?> – <?= $row['data_fine'] ? gdrcd_format_time($row['data_fine']) : '—' ?>
                                </div>
                            </td>
                            <td class="text-sm font-display"><?= htmlspecialchars($r_chat['nome'] ?? '—') ?></td>
                            <td class="text-xs">
                                <div class="flex flex-wrap gap-1 max-w-[14rem]">
                                    <?php foreach ($parts as $p): ?>
                                        <span class="inline-block px-1.5 py-0.5 rounded bg-gdrcd-accent-soft text-gdrcd-accent text-[10px] font-display"><?= htmlspecialchars($p) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td class="tabular-nums text-center"><?= (int)$num_az ?></td>
                            <td class="text-xs max-w-[10rem]">
                                <?php if (!empty($row['tags'])): ?>
                                    <span class="text-gdrcd-text-soft italic"><?= htmlspecialchars($row['tags']) ?></span>
                                <?php else: ?>
                                    <span class="text-gdrcd-subtle">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-xs max-w-[12rem]">
                                <?php if (!empty($row['quest'])): ?>
                                    <?= htmlspecialchars($row['quest']) ?>
                                <?php else: ?>
                                    <span class="text-gdrcd-subtle">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <?php if ($can_log): ?>
                                <form action="main.php?page=scheda_roles&pg=<?= $pg_url ?>" method="post" class="inline">
                                    <?= gdrcd_csrf_field() ?>
                                    <input type="hidden" name="op" value="log">
                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                    <button type="submit" class="gdrcd-btn-ghost p-1.5" title="Log chat" aria-label="Log chat">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </article>
        <?php
        $months_html .= ob_get_clean();
    endwhile;

    if ($months_html !== ''):
?>
    <section class="space-y-4">
        <h3 class="gdrcd-h2 text-center"><?= $y ?></h3>
        <?= $months_html ?>
    </section>
<?php endif;
endwhile;

if (!$any): ?>
    <div class="gdrcd-card text-center py-10">
        <svg class="w-16 h-16 mx-auto text-gdrcd-subtle/50 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div class="font-display text-gdrcd-text">Nessun risultato</div>
        <p class="text-sm text-gdrcd-text-soft mt-1">Nessuna giocata corrisponde ai criteri di ricerca.</p>
    </div>
<?php endif;

$render_back();
