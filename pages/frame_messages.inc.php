<?php
/**
 * Widget sidebar: link al centro messaggi con contatore non letti.
 * Era un iframe legacy con auto-refresh: ora è inline e si aggiorna ad ogni page-load.
 */

$cntNewMessage = 0;
if (($PARAMETERS['mode']['check_messages'] ?? 'OFF') === 'ON') {
    $res = gdrcd_query(
        "SELECT COUNT(*) AS c FROM messaggi
         WHERE destinatario = '" . gdrcd_filter('in', $_SESSION['login']) . "'
           AND destinatario_del = 0
           AND letto = 0"
    );
    $cntNewMessage = (int)($res['c'] ?? 0);
}
$hasNew = ($cntNewMessage > 0);
?>
<a href="main.php?page=messages_center&offset=0" target="_top"
   class="flex items-center gap-3 -m-1 p-2 rounded-md hover:bg-gdrcd-accent-soft/60 transition-colors group">
    <span class="inline-flex items-center justify-center w-9 h-9 rounded-full <?= $hasNew ? 'bg-gdrcd-accent text-white' : 'bg-gdrcd-accent-soft text-gdrcd-accent border border-gdrcd-accent-ring/30' ?>">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
        </svg>
    </span>
    <span class="flex-1 min-w-0">
        <span class="block font-medium text-gdrcd-text group-hover:text-gdrcd-accent-hover transition-colors">
            <?= gdrcd_filter('out', $PARAMETERS['names']['private_message']['plur']) ?>
        </span>
        <span class="block text-xs text-gdrcd-muted">
            <?= $hasNew
                ? $cntNewMessage . ' non letti'
                : 'Nessun nuovo messaggio' ?>
        </span>
    </span>
    <?php if ($hasNew): ?>
        <span class="gdrcd-badge-error"><?= $cntNewMessage ?></span>
    <?php endif; ?>
</a>
