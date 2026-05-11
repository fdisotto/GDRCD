<?php
/**
 * Widget sidebar: link al forum con conteggio bacheche che hanno nuovi topic.
 * Inline (no iframe). Si aggiorna a ogni page-load.
 */

$cntNewBoards = 0;

if (($PARAMETERS['mode']['check_forum'] ?? 'OFF') === 'ON') {
    $me_in = gdrcd_filter('in', $_SESSION['login']);
    $result = gdrcd_query("SELECT id_araldo, tipo, proprietari FROM araldo ORDER BY tipo, nome", 'result');

    while ($row = gdrcd_query($result, 'fetch')) {
        $tipo  = (int)$row['tipo'];
        $owner = (int)$row['proprietari'];

        // Verifica accesso alla bacheca
        $can_see = ($tipo <= PERTUTTI)
                || ($tipo === SOLORAZZA   && (int)($_SESSION['id_razza'] ?? -1) === $owner)
                || ($tipo === SOLOGILDA   && strpos((string)($_SESSION['gilda'] ?? ''), '*' . $owner . '*') !== false)
                || ($tipo === SOLOMASTERS && (int)$_SESSION['permessi'] >= GAMEMASTER)
                || ((int)$_SESSION['permessi'] >= MODERATOR);

        if (!$can_see) continue;

        $letti = gdrcd_query("SELECT COUNT(id) AS n FROM araldo_letto WHERE araldo_id = " . (int)$row['id_araldo'] . " AND nome = '" . $me_in . "'");
        $tot   = gdrcd_query("SELECT COUNT(id_messaggio) AS n FROM messaggioaraldo WHERE id_araldo = " . (int)$row['id_araldo'] . " AND id_messaggio_padre = -1");

        if ((int)$tot['n'] > (int)$letti['n']) {
            $cntNewBoards++;
        }
    }
    gdrcd_query($result, 'free');
}

$hasNew = ($cntNewBoards > 0);
?>
<a href="main.php?page=forum" target="_top"
   class="flex items-center gap-3 -m-1 p-2 rounded-md hover:bg-gdrcd-accent-soft/60 transition-colors group">
    <span class="inline-flex items-center justify-center w-9 h-9 rounded-full <?= $hasNew ? 'bg-gdrcd-accent text-white' : 'bg-gdrcd-accent-soft text-gdrcd-accent border border-gdrcd-accent-ring/30' ?>">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
        </svg>
    </span>
    <span class="flex-1 min-w-0">
        <span class="block font-medium text-gdrcd-text group-hover:text-gdrcd-accent-hover transition-colors">
            <?= gdrcd_filter('out', $PARAMETERS['names']['forum']['plur']) ?>
        </span>
        <span class="block text-xs text-gdrcd-muted">
            <?= $hasNew
                ? $cntNewBoards . ' bacheche con nuovi topic'
                : 'Nessuna novità' ?>
        </span>
    </span>
    <?php if ($hasNew): ?>
        <span class="gdrcd-badge-error"><?= $cntNewBoards ?></span>
    <?php endif; ?>
</a>
