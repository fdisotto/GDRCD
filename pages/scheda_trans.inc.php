<?php
/**
 * Scheda PG — log trasferimenti monetari (BONIFICO).
 */

if (!isset($_REQUEST['pg'])) {
    echo '<div class="gdrcd-alert-error">' . gdrcd_filter('out', $MESSAGE['error']['unknown_character_sheet']) . '</div>';
    return;
}

$pg = (string)$_REQUEST['pg'];

$check = Db::preparedFetch("SELECT nome FROM personaggio WHERE nome = ?", 's', array($pg));
if ($check === null) {
    echo '<div class="gdrcd-alert-error">' . gdrcd_filter('out', $MESSAGE['error']['unknown_character_sheet']) . '</div>';
    return;
}

$num_logs = (int)($PARAMETERS['settings']['view_logs'] ?? 20);

$result = Db::prepared(
    "SELECT descrizione_evento, autore, data_evento, nome_interessato
     FROM log
     WHERE (nome_interessato = ? OR autore = ?)
       AND codice_evento = ?
     ORDER BY data_evento DESC LIMIT ?",
    'ssii',
    array($pg, $pg, (int)BONIFICO, $num_logs)
);
$numresults = ($result instanceof mysqli_result) ? (int)mysqli_num_rows($result) : 0;

$lbl   = $MESSAGE['interface']['sheet']['px'];
$lbl_t = $MESSAGE['interface']['sheet']['trans'];
$lbl_m = $MESSAGE['interface']['sheet']['menu'];
?>

<div class="space-y-6">
    <header class="space-y-2">
        <h2 class="gdrcd-h1">
            <?= gdrcd_filter('out', $lbl_t['page_name']) ?>
            <span class="text-gdrcd-accent">·</span>
            <span class="text-gdrcd-text-soft text-2xl"><?= gdrcd_filter('out', $pg) ?></span>
        </h2>
        <p class="gdrcd-muted text-sm">Ultime <?= $num_logs ?> transazioni.</p>
    </header>

    <nav class="flex flex-wrap gap-2 border-b border-gdrcd-border pb-3" aria-label="Sezioni scheda">
        <?php include 'scheda/menu.inc.php'; ?>
    </nav>

    <?php if ($numresults === 0): ?>
        <div class="gdrcd-alert-info">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div>Nessuna transazione registrata.</div>
        </div>
    <?php else: ?>
        <div class="gdrcd-table-wrap">
            <table class="gdrcd-table">
                <thead>
                    <tr>
                        <th><?= gdrcd_filter('out', $lbl['trans']) ?></th>
                        <th class="whitespace-nowrap"><?= gdrcd_filter('out', $lbl['to']) ?></th>
                        <th class="whitespace-nowrap"><?= gdrcd_filter('out', $lbl['date']) ?></th>
                        <th class="whitespace-nowrap"><?= gdrcd_filter('out', $lbl['author']) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($r = gdrcd_query($result, 'fetch')): ?>
                        <tr>
                            <td><?= gdrcd_filter('out', $r['descrizione_evento']) ?></td>
                            <td class="font-medium text-gdrcd-text whitespace-nowrap"><?= gdrcd_filter('out', $r['nome_interessato']) ?></td>
                            <td class="text-gdrcd-muted whitespace-nowrap"><?= gdrcd_format_date($r['data_evento']) ?></td>
                            <td class="whitespace-nowrap"><?= gdrcd_filter('out', $r['autore']) ?></td>
                        </tr>
                    <?php endwhile;
                    gdrcd_query($result, 'free');
                    ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
