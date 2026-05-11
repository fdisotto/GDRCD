<?php
/**
 * Scheda PG — elenco giocate registrate.
 */

$pg = $_REQUEST['pg'];
$can_register = ($pg == $_SESSION['login']) || ($_SESSION['permessi'] >= ROLE_PERM);
$mese_now = (int)date('m');
$anno_now = (int)date('Y');
$anno_sel = isset($_GET['y']) ? (int)gdrcd_filter('num', $_GET['y']) : $anno_now;
$pg_url   = gdrcd_filter('url', $pg);
$pg_in    = gdrcd_filter('in', $pg);

$mesi = [
    1 => 'Gennaio', 2 => 'Febbraio', 3 => 'Marzo', 4 => 'Aprile',
    5 => 'Maggio', 6 => 'Giugno', 7 => 'Luglio', 8 => 'Agosto',
    9 => 'Settembre', 10 => 'Ottobre', 11 => 'Novembre', 12 => 'Dicembre',
];

// Anni disponibili
$year_res = gdrcd_query(
    "SELECT YEAR(data_inizio) AS year FROM segnalazione_role
     WHERE mittente = '" . $pg_in . "'
     GROUP BY YEAR(data_inizio) ORDER BY YEAR(data_inizio) DESC",
    'result'
);
$years = [];
while ($ry = gdrcd_query($year_res, 'fetch')) {
    $years[] = (int)$ry['year'];
}

// Statistiche
$stat_year = (int)gdrcd_query(gdrcd_query(
    "SELECT id FROM segnalazione_role WHERE mittente = '" . $pg_in . "'
     AND YEAR(data_inizio) = " . $anno_sel . " AND conclusa < 2", 'result'), 'num_rows');
$stat_done = (int)gdrcd_query(gdrcd_query(
    "SELECT id FROM segnalazione_role WHERE mittente = '" . $pg_in . "'
     AND YEAR(data_inizio) = " . $anno_sel . " AND conclusa = 1", 'result'), 'num_rows');
$stat_ongoing = $stat_year - $stat_done;
$stat_month = (int)gdrcd_query(gdrcd_query(
    "SELECT id FROM segnalazione_role WHERE mittente = '" . $pg_in . "'
     AND YEAR(data_inizio) = " . $anno_now . " AND MONTH(data_inizio) = " . $mese_now, 'result'), 'num_rows');
?>

<?php if ($can_register): ?>
<!-- Azioni rapide -->
<div class="grid grid-cols-1 md:grid-cols-[1fr_2fr] gap-3">
    <div class="gdrcd-card flex flex-wrap items-center justify-between gap-3 p-4">
        <div class="min-w-0 flex-1">
            <div class="font-display text-base text-gdrcd-text">Registra giocata</div>
            <p class="text-xs text-gdrcd-text-soft">Inserisci role del mese in corso non segnalata.</p>
        </div>
        <form action="main.php?page=scheda_roles&pg=<?= $pg_url ?>" method="post" class="shrink-0">
            <?= gdrcd_csrf_field() ?>
            <input type="hidden" name="op" value="register">
            <input type="hidden" name="mese" value="<?= $mese_now ?>">
            <input type="hidden" name="anno" value="<?= $anno_now ?>">
            <input type="hidden" name="pg" value="<?= htmlspecialchars($pg) ?>">
            <button type="submit" class="gdrcd-btn-primary">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Registra
            </button>
        </form>
    </div>

    <div class="gdrcd-card p-4">
        <form action="main.php?page=scheda_roles&pg=<?= $pg_url ?>" method="post" class="flex flex-col md:flex-row gap-2 items-end">
            <?= gdrcd_csrf_field() ?>
            <label class="block flex-1 min-w-0">
                <span class="text-xs uppercase tracking-wide text-gdrcd-text-soft font-display">Cerca per</span>
                <div class="flex gap-2 mt-1">
                    <select name="type" class="gdrcd-select w-40 shrink-0">
                        <option value="0">Personaggio</option>
                        <option value="1">Tag</option>
                        <option value="2">Quest</option>
                    </select>
                    <input name="search" class="gdrcd-input flex-1" placeholder="Chiave di ricerca…" type="text">
                </div>
            </label>
            <input type="hidden" name="op" value="search">
            <input type="hidden" name="pg" value="<?= htmlspecialchars($pg) ?>">
            <button type="submit" class="gdrcd-btn-primary shrink-0 w-full md:w-auto" title="Cerca">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
                Cerca
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Stats overview -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <div class="gdrcd-card p-3">
        <div class="text-[10px] uppercase tracking-wide text-gdrcd-text-soft font-display">Totale <?= $anno_sel ?></div>
        <div class="mt-1 text-2xl font-display text-gdrcd-text tabular-nums"><?= $stat_year ?></div>
    </div>
    <div class="gdrcd-card p-3">
        <div class="text-[10px] uppercase tracking-wide text-gdrcd-text-soft font-display flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
            Concluse
        </div>
        <div class="mt-1 text-2xl font-display text-gdrcd-success tabular-nums"><?= $stat_done ?></div>
    </div>
    <div class="gdrcd-card p-3">
        <div class="text-[10px] uppercase tracking-wide text-gdrcd-text-soft font-display flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-gdrcd-accent animate-pulse"></span>
            In corso
        </div>
        <div class="mt-1 text-2xl font-display text-gdrcd-accent tabular-nums"><?= $stat_ongoing ?></div>
    </div>
    <div class="gdrcd-card p-3">
        <div class="text-[10px] uppercase tracking-wide text-gdrcd-text-soft font-display">Mese corrente</div>
        <div class="mt-1 text-2xl font-display text-gdrcd-text tabular-nums"><?= $stat_month ?></div>
    </div>
</div>

<!-- Selettore anno -->
<?php if (!empty($years)): ?>
<nav class="flex flex-wrap gap-2 justify-center" aria-label="Selezione anno">
    <?php foreach ($years as $y):
        $active = ($y === $anno_sel);
        $cls = $active
            ? 'bg-gdrcd-accent text-white border-gdrcd-accent shadow-sm'
            : 'bg-gdrcd-panel text-gdrcd-text border-gdrcd-border hover:bg-gdrcd-accent-soft hover:border-gdrcd-accent';
    ?>
        <a class="px-4 py-1.5 rounded-md border <?= $cls ?> text-sm font-display tabular-nums transition"
           href="main.php?page=scheda_roles&pg=<?= $pg_url ?>&y=<?= $y ?>"><?= $y ?></a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>

<!-- Elenco mensile -->
<section class="space-y-4">
    <?php
    $month_res = gdrcd_query(
        "SELECT MONTH(data_inizio) AS month FROM segnalazione_role
         WHERE mittente = '" . $pg_in . "' AND YEAR(data_inizio) = " . (int)$anno_sel . "
         GROUP BY MONTH(data_inizio) ORDER BY MONTH(data_inizio) DESC",
        'result'
    );

    $rendered_any = false;
    while ($rm = gdrcd_query($month_res, 'fetch')):
        $m = (int)$rm['month'];
        $query = gdrcd_query(
            "SELECT * FROM segnalazione_role
             WHERE YEAR(data_inizio) = " . (int)$anno_sel . "
               AND MONTH(data_inizio) = " . $m . "
               AND mittente = '" . $pg_in . "'
               AND conclusa < 2
             ORDER BY data_inizio ASC, data_fine ASC",
            'result'
        );
        $totals = gdrcd_query($query, 'num_rows');
        if ($totals == 0) continue;
        $rendered_any = true;
    ?>
        <article class="gdrcd-card overflow-hidden">
            <header class="flex items-center justify-between gap-3 px-4 py-3 border-b border-gdrcd-border bg-gdrcd-panel-alt/30">
                <h4 class="font-display text-lg text-gdrcd-accent flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <?= $mesi[$m] ?? $m ?>
                </h4>
                <span class="text-xs text-gdrcd-text-soft tabular-nums"><?= $totals ?> giocat<?= $totals === 1 ? 'a' : 'e' ?></span>
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
                            <th>Stato</th>
                            <th class="text-right">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = gdrcd_query($query, 'fetch')):
                        $chat_r = gdrcd_query(
                            "SELECT nome FROM mappa WHERE id = " . gdrcd_filter('num', $row['stanza']),
                            'result'
                        );
                        $r_chat = gdrcd_query($chat_r, 'fetch');

                        $num_az = 0;
                        if ($row['conclusa'] == 1) {
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
                        }

                        $parts = array_filter(array_map('trim', explode(',', $row['partecipanti'])));

                        $new_time = date('Y-m-d H:i:s', strtotime('+30 days', strtotime($row['data_fine'])));
                        $now_str  = date('Y-m-d H:i:s');
                        $can_edit = ($row['conclusa'] == 1) && (
                            ($new_time > $now_str && $pg == $_SESSION['login'])
                            || $_SESSION['permessi'] >= EDIT_PERM
                        );
                        $can_actions = ($row['conclusa'] == 1) && (
                            $pg == $_SESSION['login'] || $_SESSION['permessi'] >= ROLE_PERM
                        );
                    ?>
                        <tr>
                            <td class="whitespace-nowrap text-sm">
                                <div class="font-display"><?= gdrcd_filter('out', gdrcd_format_date($row['data_inizio'])) ?></div>
                                <div class="text-xs text-gdrcd-text-soft tabular-nums">
                                    <?= gdrcd_format_time($row['data_inizio']) ?> – <?= gdrcd_format_time($row['data_fine']) ?>
                                </div>
                            </td>
                            <td class="text-sm font-display"><?= htmlspecialchars($r_chat['nome'] ?? '—') ?></td>
                            <td class="text-xs">
                                <div class="flex flex-wrap gap-1 max-w-[14rem]">
                                    <?php foreach ($parts as $p): ?>
                                        <span class="inline-block px-1.5 py-0.5 rounded bg-gdrcd-accent-soft text-gdrcd-accent text-[10px] font-display">
                                            <?= htmlspecialchars($p) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td class="tabular-nums text-center"><?= (int)$num_az ?></td>
                            <td class="text-xs max-w-[10rem]">
                                <?php if (!empty($row['tags'])): ?>
                                    <span class="inline-block text-gdrcd-text-soft italic"><?= htmlspecialchars($row['tags']) ?></span>
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
                            <td>
                                <?php if ($row['conclusa'] == 1): ?>
                                    <span class="gdrcd-badge-success text-[10px] whitespace-nowrap">Conclusa</span>
                                <?php else: ?>
                                    <span class="gdrcd-badge-accent text-[10px] whitespace-nowrap">In corso</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <div class="inline-flex flex-wrap gap-0.5 justify-end">
                                    <?php if ($can_edit): ?>
                                    <form action="main.php?page=scheda_roles&pg=<?= $pg_url ?>" method="post" class="inline">
                                        <?= gdrcd_csrf_field() ?>
                                        <input type="hidden" name="op" value="edit">
                                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                        <button type="submit" class="gdrcd-btn-ghost p-1.5" title="Modifica tag/quest (entro 30gg)" aria-label="Modifica tag/quest (entro 30gg)">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                    </form>
                                    <?php endif; ?>

                                    <?php if ($can_actions && $_SESSION['permessi'] >= LOG_PERM): ?>
                                    <form action="main.php?page=scheda_roles&pg=<?= $pg_url ?>" method="post" class="inline">
                                        <?= gdrcd_csrf_field() ?>
                                        <input type="hidden" name="op" value="log">
                                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                        <button type="submit" class="gdrcd-btn-ghost p-1.5" title="Log chat" aria-label="Log chat">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                                        </button>
                                    </form>
                                    <?php endif; ?>

                                    <?php if ($can_actions && SEND_GM): ?>
                                    <form action="main.php?page=scheda_roles&pg=<?= $pg_url ?>" method="post" class="inline">
                                        <?= gdrcd_csrf_field() ?>
                                        <input type="hidden" name="op" value="segnala">
                                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                        <button type="submit" class="gdrcd-btn-ghost p-1.5" title="Segnala ai Master" aria-label="Segnala ai Master">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21l1.65-3.8a9 9 0 113.4 2.9L3 21z"/></svg>
                                        </button>
                                    </form>
                                    <?php endif; ?>

                                    <?php if ($pg == $_SESSION['login'] && $row['conclusa'] == 1 && SAVE_ROLE): ?>
                                    <a href="pages/scheda/roles/save.proc.php?id=<?= (int)$row['id'] ?>" target="_blank" class="gdrcd-btn-ghost p-1.5" title="Scarica giocata" aria-label="Scarica giocata">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/></svg>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </article>
    <?php endwhile; ?>

    <?php if (!$rendered_any): ?>
        <div class="gdrcd-card text-center py-10">
            <svg class="w-16 h-16 mx-auto text-gdrcd-subtle/50 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <div class="font-display text-gdrcd-text">Nessuna giocata registrata</div>
            <p class="text-sm text-gdrcd-text-soft mt-1">Anno <?= $anno_sel ?> senza role segnalate.</p>
        </div>
    <?php endif; ?>
</section>
