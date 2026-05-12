<?php
/**
 * Scheda PG — esperienza (riepilogo log PX + assegnazione GM).
 */

if (!isset($_REQUEST['pg'])) {
    echo '<div class="gdrcd-alert-error">' . gdrcd_filter('out', $MESSAGE['error']['unknown_character_sheet']) . '</div>';
    return;
}

$pg = (string)$_REQUEST['pg'];
$pg_data = Db::preparedFetch(
    "SELECT esperienza FROM personaggio WHERE nome = ? LIMIT 1",
    's',
    array($pg)
);
if ($pg_data === null) {
    echo '<div class="gdrcd-alert-error">' . gdrcd_filter('out', $MESSAGE['error']['unknown_character_sheet']) . '</div>';
    return;
}

$alerts = [];

// Handler GM assegnazione PX
if (($_POST['op'] ?? '') === 'assegna') {
    if (is_numeric($_POST['px'] ?? null) && (int)$_SESSION['permessi'] >= GAMEMASTER) {
        $px_val   = (int)$_POST['px'];
        $causale  = (string)($_POST['causale'] ?? '');
        Db::preparedExecute(
            "UPDATE personaggio SET esperienza = esperienza + ? WHERE nome = ? LIMIT 1",
            'is',
            array($px_val, $pg)
        );
        Db::preparedExecute(
            "INSERT INTO log (nome_interessato, autore, data_evento, codice_evento, descrizione_evento) VALUES (?, ?, NOW(), ?, ?)",
            'ssis',
            array($pg, (string)$_SESSION['login'], (int)PX, '(' . $px_val . ' px) ' . $causale)
        );
        $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['warning']['done'])];

        // Aggiorno valore corrente per visualizzazione
        $refresh = Db::preparedFetch(
            "SELECT esperienza FROM personaggio WHERE nome = ? LIMIT 1",
            's',
            array($pg)
        );
        if ($refresh !== null) {
            $pg_data['esperienza'] = $refresh['esperienza'];
        }
    } else {
        $alerts[] = ['error', gdrcd_filter('out', $MESSAGE['warning']['camt_do'] ?? $MESSAGE['warning']['cant_do'])];
    }
}

$num_logs = (int)($PARAMETERS['settings']['view_logs'] ?? 20);
$result = Db::prepared(
    "SELECT descrizione_evento, autore, data_evento FROM log
     WHERE nome_interessato = ? AND codice_evento = ?
     ORDER BY data_evento DESC LIMIT ?",
    'sii',
    array($pg, (int)PX, $num_logs)
);
$numresults = ($result instanceof mysqli_result) ? (int)mysqli_num_rows($result) : 0;

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
