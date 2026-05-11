<?php
/**
 * Sezione abilità della scheda PG (skill system).
 * Renderizzata dentro un gdrcd-card-body del wrapper in scheda.inc.php.
 */

// Carico abilità del pg + ranks
$abilita = gdrcd_query(
    "SELECT id_abilita, grado FROM clgpersonaggioabilita
     WHERE nome = '" . gdrcd_filter('in', $_REQUEST['pg']) . "'",
    'result'
);

$px_spesi = 0;
$ranks    = [];
while ($r = gdrcd_query($abilita, 'fetch')) {
    $g           = (int)$r['grado'];
    $px_abi      = $PARAMETERS['settings']['px_x_rank'] * (($g * ($g + 1)) / 2);
    $px_spesi   += $px_abi;
    $ranks[(int)$r['id_abilita']] = $g;
}

if (!isset($personaggio['id_razza']) || !isset($personaggio['esperienza'])) {
    $info = gdrcd_query(
        "SELECT id_razza, esperienza FROM personaggio
         WHERE nome = '" . gdrcd_filter('in', $_REQUEST['pg']) . "' LIMIT 1"
    );
    $personaggio['id_razza']  = $info['id_razza'];
    $personaggio['esperienza'] = $info['esperienza'];
}

$px_totali_pg = (int)$personaggio['esperienza'];

$is_owner = ($_SESSION['login'] === gdrcd_filter('out', $_REQUEST['pg']));
$is_mod   = ((int)$_SESSION['permessi'] >= MODERATOR);

$op   = gdrcd_filter('get', $_REQUEST['op']   ?? '');
$what = gdrcd_filter('num', $_REQUEST['what'] ?? 0);

$alerts = [];

// ============================================================
// Increment
// ============================================================
if ($op === 'addskill' && ($is_owner || $is_mod)) {
    $cur_rank     = $ranks[$what] ?? 0;
    $px_necessari = $PARAMETERS['settings']['px_x_rank'] * ($cur_rank + 1);
    if (($px_totali_pg - $px_spesi) >= $px_necessari) {
        $px_spesi += $px_necessari;
        if ($cur_rank === 0) {
            gdrcd_query(
                "INSERT INTO clgpersonaggioabilita (id_abilita, nome, grado) VALUES ("
                . $what . ","
                . "'" . gdrcd_filter('in', $_REQUEST['pg']) . "', 1)"
            );
            $ranks[$what] = 1;
        } else {
            $ranks[$what] = $cur_rank + 1;
            gdrcd_query(
                "UPDATE clgpersonaggioabilita SET grado = " . $ranks[$what] .
                " WHERE id_abilita = " . $what .
                "   AND nome = '" . gdrcd_filter('in', $_REQUEST['pg']) . "'"
            );
        }
        $alerts[] = gdrcd_filter('out', $MESSAGE['warning']['modified']);
    }
}

// ============================================================
// Decrement (mod only)
// ============================================================
if ($op === 'subskill' && $is_mod) {
    $cur_rank = $ranks[$what] ?? 0;
    if ($cur_rank === 1) {
        gdrcd_query(
            "DELETE FROM clgpersonaggioabilita
             WHERE id_abilita = " . $what . "
               AND nome = '" . gdrcd_filter('in', $_REQUEST['pg']) . "' LIMIT 1"
        );
        $ranks[$what] = 0;
    } elseif ($cur_rank > 1) {
        $ranks[$what] = $cur_rank - 1;
        gdrcd_query(
            "UPDATE clgpersonaggioabilita SET grado = " . $ranks[$what] .
            " WHERE id_abilita = " . $what .
            "   AND nome = '" . gdrcd_filter('in', $_REQUEST['pg']) . "'"
        );
    }
    $alerts[] = gdrcd_filter('out', $MESSAGE['warning']['modified']);
}

// Lista abilità (sempre da DB per riflettere update)
$result = gdrcd_query(
    "SELECT nome, car, id_abilita FROM abilita
     WHERE id_razza = -1 OR id_razza = " . (int)$personaggio['id_razza'] . "
     ORDER BY id_razza DESC, nome",
    'result'
);

$px_available = $px_totali_pg - $px_spesi;
$pg_url       = urlencode($_REQUEST['pg']);
$skills_cap   = (int)$PARAMETERS['settings']['skills_cap'];
?>

<div class="space-y-4">
    <?php foreach ($alerts as $msg): ?>
        <div class="gdrcd-alert-success">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <div><?= $msg ?></div>
        </div>
    <?php endforeach; ?>

    <div class="flex flex-wrap items-baseline justify-between gap-3 p-3 rounded-md bg-gdrcd-accent-soft/40 border border-gdrcd-accent-ring/30">
        <div class="text-sm">
            <span class="gdrcd-muted"><?= gdrcd_filter('out', $MESSAGE['interface']['sheet']['avalaible_xp']) ?>:</span>
            <strong class="text-gdrcd-accent tabular-nums ml-1"><?= $px_available ?></strong>
            <span class="gdrcd-muted">/ <?= $px_totali_pg ?> totali</span>
        </div>
        <p class="text-xs text-gdrcd-muted"><?= gdrcd_filter('out', $MESSAGE['interface']['sheet']['info_skill_cost']) ?></p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
        <?php while ($row = gdrcd_query($result, 'fetch')):
            $id        = (int)$row['id_abilita'];
            $rank      = $ranks[$id] ?? 0;
            $next_cost = $PARAMETERS['settings']['px_x_rank'] * ($rank + 1);
            $can_add   = (($next_cost <= $px_available) && $is_owner && ($rank < $skills_cap)) || $is_mod;
            $can_sub   = $is_mod && $rank > 0;
            ?>
            <div class="flex items-center gap-3 px-3 py-2 rounded-md border border-gdrcd-border bg-gdrcd-panel hover:bg-gdrcd-panel-alt/40 transition-colors">
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium text-gdrcd-text truncate">
                        <?= gdrcd_filter('out', $row['nome']) ?>
                    </div>
                    <div class="text-xs text-gdrcd-muted">
                        <?= gdrcd_filter('out', $PARAMETERS['names']['stats']['car'.(int)$row['car']]) ?>
                    </div>
                </div>
                <div class="inline-flex items-center gap-2 shrink-0">
                    <?php if ($can_sub): ?>
                        <a href="main.php?page=scheda&pg=<?= $pg_url ?>&op=subskill&what=<?= $id ?>"
                           class="inline-flex items-center justify-center w-9 h-9 rounded-md border border-gdrcd-border text-gdrcd-muted hover:border-gdrcd-error hover:text-gdrcd-error transition-colors text-lg leading-none"
                           title="Riduci grado">−</a>
                    <?php endif; ?>
                    <span class="inline-flex items-center justify-center min-w-[3rem] h-9 px-3 rounded-md bg-gdrcd-accent-soft text-gdrcd-accent border border-gdrcd-accent-ring/30 font-display font-bold tabular-nums text-lg leading-none">
                        <?= $rank ?>
                    </span>
                    <?php if ($can_add): ?>
                        <a href="main.php?page=scheda&pg=<?= $pg_url ?>&op=addskill&what=<?= $id ?>"
                           class="inline-flex items-center justify-center w-9 h-9 rounded-md border border-gdrcd-accent-ring/40 text-gdrcd-accent hover:bg-gdrcd-accent hover:text-white hover:border-gdrcd-accent transition-colors text-lg leading-none"
                           title="Aumenta grado (costo: <?= $next_cost ?> px)">+</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile;
        gdrcd_query($result, 'free');
        ?>
    </div>
</div>
