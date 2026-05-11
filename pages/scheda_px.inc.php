<?php
/**
 * Scheda PG — esperienza (riepilogo log PX + assegnazione GM).
 */

if (!isset($_REQUEST['pg'])) {
    echo '<div class="gdrcd-alert-error">' . gdrcd_filter('out', $MESSAGE['error']['unknown_character_sheet']) . '</div>';
    return;
}

$pg = $_REQUEST['pg'];
$check = gdrcd_query("SELECT esperienza FROM personaggio WHERE nome = '" . gdrcd_filter('in', $pg) . "' LIMIT 1", 'result');
if (gdrcd_query($check, 'num_rows') === 0) {
    echo '<div class="gdrcd-alert-error">' . gdrcd_filter('out', $MESSAGE['error']['unknown_character_sheet']) . '</div>';
    return;
}
$pg_data = gdrcd_query($check, 'fetch');
gdrcd_query($check, 'free');

$alerts = [];

// Handler GM assegnazione PX
if (($_POST['op'] ?? '') === 'assegna') {
    if (is_numeric($_POST['px'] ?? null) && (int)$_SESSION['permessi'] >= GAMEMASTER) {
        gdrcd_query(
            "UPDATE personaggio SET esperienza = esperienza + " . gdrcd_filter('num', $_POST['px']) .
            " WHERE nome = '" . gdrcd_filter('in', $pg) . "' LIMIT 1"
        );
        gdrcd_query(
            "INSERT INTO log (nome_interessato, autore, data_evento, codice_evento, descrizione_evento) VALUES ("
            . "'" . gdrcd_filter('in', $pg) . "',"
            . "'" . gdrcd_filter('in', $_SESSION['login']) . "',"
            . "NOW(), " . PX . ","
            . "'(" . gdrcd_filter('in', $_POST['px']) . ' px) ' . gdrcd_filter('in', $_POST['causale'] ?? '') . "')"
        );
        $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['warning']['done'])];

        // Aggiorno valore corrente per visualizzazione
        $refresh = gdrcd_query("SELECT esperienza FROM personaggio WHERE nome = '" . gdrcd_filter('in', $pg) . "' LIMIT 1");
        $pg_data['esperienza'] = $refresh['esperienza'];
    } else {
        $alerts[] = ['error', gdrcd_filter('out', $MESSAGE['warning']['camt_do'] ?? $MESSAGE['warning']['cant_do'])];
    }
}

$num_logs = (int)($PARAMETERS['settings']['view_logs'] ?? 20);
$result = gdrcd_query(
    "SELECT descrizione_evento, autore, data_evento FROM log
     WHERE nome_interessato = '" . gdrcd_filter('in', $pg) . "' AND codice_evento = " . PX . "
     ORDER BY data_evento DESC LIMIT " . $num_logs,
    'result'
);
$numresults = (int)gdrcd_query($result, 'num_rows');

$lbl = $MESSAGE['interface']['sheet']['px'];
?>

<div class="space-y-6">
    <header class="space-y-2">
        <h2 class="gdrcd-h1">
            <?= gdrcd_filter('out', $lbl['page_name']) ?>
            <span class="text-gdrcd-accent">·</span>
            <span class="text-gdrcd-text-soft text-2xl"><?= gdrcd_filter('out', $pg) ?></span>
        </h2>
        <p class="gdrcd-muted text-sm">
            Esperienza attuale:
            <strong class="text-gdrcd-accent text-base tabular-nums ml-1"><?= (int)floor($pg_data['esperienza']) ?></strong> px
        </p>
    </header>

    <nav class="flex flex-wrap gap-2 border-b border-gdrcd-border pb-3" aria-label="Sezioni scheda">
        <?php include 'scheda/menu.inc.php'; ?>
    </nav>

    <?php foreach ($alerts as [$kind, $msg]): ?>
        <div class="gdrcd-alert-<?= $kind ?>">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <div><?= $msg ?></div>
        </div>
    <?php endforeach; ?>

    <?php if ($numresults === 0): ?>
        <div class="gdrcd-alert-info">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div>Nessun evento PX registrato.</div>
        </div>
    <?php else: ?>
        <div class="gdrcd-table-wrap">
            <table class="gdrcd-table">
                <thead>
                    <tr>
                        <th><?= gdrcd_filter('out', $lbl['event']) ?></th>
                        <th class="whitespace-nowrap"><?= gdrcd_filter('out', $lbl['date']) ?></th>
                        <th class="whitespace-nowrap"><?= gdrcd_filter('out', $lbl['author']) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($r = gdrcd_query($result, 'fetch')): ?>
                        <tr>
                            <td><?= gdrcd_filter('out', $r['descrizione_evento']) ?></td>
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

    <?php if ((int)$_SESSION['permessi'] >= GAMEMASTER): ?>
        <section class="gdrcd-card">
            <div class="gdrcd-card-header">
                <h3 class="gdrcd-h3">Assegna esperienza</h3>
            </div>
            <div class="gdrcd-card-body">
                <form action="main.php?page=scheda_px&pg=<?= urlencode($pg) ?>" method="post" class="space-y-4">
                    <?= gdrcd_csrf_field() ?>
                    <div>
                        <label class="gdrcd-label" for="px_causale"><?= gdrcd_filter('out', $lbl['why']) ?></label>
                        <input class="gdrcd-input" type="text" id="px_causale" name="causale" required/>
                    </div>
                    <div>
                        <label class="gdrcd-label" for="px_val"><?= gdrcd_filter('out', $lbl['px']) ?></label>
                        <input class="gdrcd-input max-w-xs" type="number" id="px_val" name="px" value="0"/>
                        <p class="gdrcd-help">Numero positivo per assegnare, negativo per togliere.</p>
                    </div>
                    <div class="flex justify-end pt-2 border-t border-gdrcd-border">
                        <input type="hidden" name="op" value="assegna"/>
                        <input type="hidden" name="pg" value="<?= htmlspecialchars($pg) ?>"/>
                        <button type="submit" class="gdrcd-btn-primary">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            <?= gdrcd_filter('out', $MESSAGE['interface']['forms']['submit']) ?>
                        </button>
                    </div>
                </form>
            </div>
        </section>
    <?php endif; ?>
</div>
