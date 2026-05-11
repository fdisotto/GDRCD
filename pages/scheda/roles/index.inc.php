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
?>

<?php if ($can_register): ?>
<div class="gdrcd-card flex flex-wrap items-center justify-between gap-3">
    <div>
        <div class="font-display text-lg text-gdrcd-text">Registra una giocata</div>
        <p class="text-sm text-gdrcd-text-soft">Inserisci una role del mese corrente che hai dimenticato di registrare.</p>
    </div>
    <form action="main.php?page=scheda_roles&pg=<?= $pg_url ?>" method="post">
        <input type="hidden" name="op" value="register">
        <input type="hidden" name="mese" value="<?= $mese_now ?>">
        <input type="hidden" name="anno" value="<?= $anno_now ?>">
        <input type="hidden" name="pg" value="<?= htmlspecialchars($pg) ?>">
        <button type="submit" class="gdrcd-btn-primary">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Registra giocata
        </button>
    </form>
</div>

<div class="gdrcd-card">
    <div class="font-display text-base text-gdrcd-text mb-2">Cerca tra le giocate</div>
    <form action="main.php?page=scheda_roles&pg=<?= $pg_url ?>" method="post" class="flex flex-col md:flex-row gap-2">
        <select name="type" class="gdrcd-select md:w-48">
            <option value="0">Per personaggio</option>
            <option value="1">Per tag</option>
            <option value="2">Per quest</option>
        </select>
        <input name="search" class="gdrcd-input flex-1" placeholder="Inserisci la chiave di ricerca" type="text" value="">
        <input type="hidden" name="op" value="search">
        <input type="hidden" name="pg" value="<?= htmlspecialchars($pg) ?>">
        <button type="submit" class="gdrcd-btn-primary md:w-auto">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
            Cerca
        </button>
    </form>
</div>
<?php endif; ?>

<?php if (!empty($years)): ?>
<nav class="flex flex-wrap gap-2 justify-center" aria-label="Selezione anno">
    <?php foreach ($years as $y):
        $active = ($y === $anno_sel);
        $cls = $active ? 'bg-gdrcd-accent text-white border-gdrcd-accent' : 'bg-gdrcd-panel text-gdrcd-text border-gdrcd-border hover:bg-gdrcd-accent-soft';
    ?>
        <a class="px-4 py-1.5 rounded-md border <?= $cls ?> text-sm font-display transition"
           href="main.php?page=scheda_roles&pg=<?= $pg_url ?>&y=<?= $y ?>"><?= $y ?></a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>

<section class="space-y-4">
    <h3 class="gdrcd-h2 text-center"><?= $anno_sel ?></h3>

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
        if ($totals == 0) {
            continue;
        }
        $rendered_any = true;
    ?>
        <article class="gdrcd-card">
            <header class="flex items-center justify-between border-b border-gdrcd-border pb-2 mb-3">
                <h4 class="font-display text-lg text-gdrcd-accent"><?= $mesi[$m] ?? $m ?></h4>
                <span class="text-sm text-gdrcd-text-soft tabular-nums"><?= $totals ?> giocat<?= $totals === 1 ? 'a' : 'e' ?></span>
            </header>

            <div class="overflow-x-auto">
                <table class="gdrcd-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Partecipanti</th>
                            <th class="tabular-nums">Azioni</th>
                            <th>Chat</th>
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

                        $parts = explode(',', $row['partecipanti']);
                        $listapart = htmlspecialchars(implode(', ', $parts));

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
                                <div><?= gdrcd_filter('out', gdrcd_format_date($row['data_inizio'])) ?></div>
                                <div class="text-gdrcd-text-soft tabular-nums">
                                    <?= gdrcd_format_time($row['data_inizio']) ?> – <?= gdrcd_format_time($row['data_fine']) ?>
                                </div>
                            </td>
                            <td class="text-sm"><?= $listapart ?></td>
                            <td class="tabular-nums"><?= (int)$num_az ?></td>
                            <td class="text-sm"><?= htmlspecialchars($r_chat['nome'] ?? '') ?></td>
                            <td class="text-sm max-w-[12rem]"><?= htmlspecialchars($row['tags']) ?></td>
                            <td class="text-sm max-w-[14rem]"><?= htmlspecialchars($row['quest']) ?></td>
                            <td>
                                <?php if ($row['conclusa'] == 1): ?>
                                    <span class="gdrcd-badge-success">Conclusa</span>
                                <?php else: ?>
                                    <span class="gdrcd-badge-accent">In corso</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <div class="inline-flex flex-wrap gap-1 justify-end">
                                    <?php if ($can_edit): ?>
                                    <form action="main.php?page=scheda_roles&pg=<?= $pg_url ?>" method="post" class="inline">
                                        <input type="hidden" name="op" value="edit">
                                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                        <button type="submit" class="gdrcd-btn-ghost" title="Modifica registrazione">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                    </form>
                                    <?php endif; ?>

                                    <?php if ($can_actions && $_SESSION['permessi'] >= LOG_PERM): ?>
                                    <form action="main.php?page=scheda_roles&pg=<?= $pg_url ?>" method="post" class="inline">
                                        <input type="hidden" name="op" value="log">
                                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                        <button type="submit" class="gdrcd-btn-ghost" title="Log chat">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                                        </button>
                                    </form>
                                    <?php endif; ?>

                                    <?php if ($can_actions && SEND_GM): ?>
                                    <form action="main.php?page=scheda_roles&pg=<?= $pg_url ?>" method="post" class="inline">
                                        <input type="hidden" name="op" value="segnala">
                                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                        <button type="submit" class="gdrcd-btn-ghost" title="Segnala ai Master">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21l1.65-3.8a9 9 0 113.4 2.9L3 21z"/></svg>
                                        </button>
                                    </form>
                                    <?php endif; ?>

                                    <?php if ($pg == $_SESSION['login'] && $row['conclusa'] == 1 && SAVE_ROLE): ?>
                                    <a href="pages/scheda/roles/save.proc.php?id=<?= (int)$row['id'] ?>" target="_blank" class="gdrcd-btn-ghost" title="Scarica giocata">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/></svg>
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
        <div class="gdrcd-card text-center text-gdrcd-text-soft">
            Nessuna giocata registrata per l'anno <?= $anno_sel ?>.
        </div>
    <?php endif; ?>
</section>
