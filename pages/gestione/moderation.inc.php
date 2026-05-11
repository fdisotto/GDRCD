<?php
/**
 * Pannello di moderazione segnalazioni utente (MODERATOR+)
 *   main.php?page=gestione/moderation
 *
 * Coda unificata: legge la tabella `moderation_reports` (vedi
 * migrazione 2026051118_GDRCDReportsModeration) e permette ai
 * moderatori di:
 *   - "Prendi in carico" (assigned_to=current_user, status='under_review')
 *   - "Risolvi"          (resolution=note, status='resolved', resolved_at=NOW())
 *   - "Archivia"         (status='dismissed')
 *
 * CSRF: validato centralmente in main.php sui POST.
 */

if (($_SESSION['permessi'] ?? 0) < MODERATOR) {
    echo '<div class="gdrcd-alert-error">'
       . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>'
       . '<div>' . gdrcd_filter('out', $MESSAGE['error']['not_allowed'] ?? 'Accesso non consentito.') . '</div>'
       . '</div>';
    return;
}

$me   = (string)($_SESSION['login'] ?? '');
$me_q = gdrcd_filter('in', $me);

$flash = null;

/* ------------------------------------------------------------------
 * Handler POST.
 * ------------------------------------------------------------------ */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $op = (string)($_POST['op'] ?? '');
    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        $flash = ['kind' => 'warning', 'message' => 'Segnalazione non valida.'];
    } else {
        $row = gdrcd_query(
            "SELECT id, status FROM moderation_reports WHERE id = " . $id . " LIMIT 1"
        );

        if (empty($row)) {
            $flash = ['kind' => 'warning', 'message' => 'Segnalazione inesistente.'];
        } elseif ($op === 'claim') {
            if (!in_array($row['status'], ['pending', 'under_review'], true)) {
                $flash = ['kind' => 'warning', 'message' => 'Segnalazione gia\' chiusa.'];
            } else {
                gdrcd_query(
                    "UPDATE moderation_reports SET "
                    . "assigned_to = '" . $me_q . "', "
                    . "status = 'under_review' "
                    . "WHERE id = " . $id
                );
                $flash = ['kind' => 'success', 'message' => 'Segnalazione presa in carico.'];

                if (function_exists('gdrcd_log_info')) {
                    gdrcd_log_info('Moderation report claimed', ['id' => $id, 'by' => $me]);
                }
            }
        } elseif ($op === 'resolve') {
            $resolution = trim((string)($_POST['resolution'] ?? ''));
            if ($resolution === '') {
                $flash = ['kind' => 'warning', 'message' => 'Per risolvere e\' richiesta una nota di chiusura.'];
            } elseif (!in_array($row['status'], ['pending', 'under_review'], true)) {
                $flash = ['kind' => 'warning', 'message' => 'Segnalazione gia\' chiusa.'];
            } else {
                $res_q = gdrcd_filter('in', $resolution);
                gdrcd_query(
                    "UPDATE moderation_reports SET "
                    . "status = 'resolved', "
                    . "resolution = '" . $res_q . "', "
                    . "resolved_at = NOW(), "
                    . "assigned_to = COALESCE(NULLIF(assigned_to, ''), '" . $me_q . "') "
                    . "WHERE id = " . $id
                );
                $flash = ['kind' => 'success', 'message' => 'Segnalazione risolta.'];

                if (function_exists('gdrcd_log_info')) {
                    gdrcd_log_info('Moderation report resolved', ['id' => $id, 'by' => $me]);
                }
            }
        } elseif ($op === 'dismiss') {
            if (!in_array($row['status'], ['pending', 'under_review'], true)) {
                $flash = ['kind' => 'warning', 'message' => 'Segnalazione gia\' chiusa.'];
            } else {
                $resolution = trim((string)($_POST['resolution'] ?? ''));
                $res_sql    = $resolution !== ''
                    ? ", resolution = '" . gdrcd_filter('in', $resolution) . "'"
                    : '';
                gdrcd_query(
                    "UPDATE moderation_reports SET "
                    . "status = 'dismissed', "
                    . "resolved_at = NOW(), "
                    . "assigned_to = COALESCE(NULLIF(assigned_to, ''), '" . $me_q . "')"
                    . $res_sql . " "
                    . "WHERE id = " . $id
                );
                $flash = ['kind' => 'success', 'message' => 'Segnalazione archiviata.'];

                if (function_exists('gdrcd_log_info')) {
                    gdrcd_log_info('Moderation report dismissed', ['id' => $id, 'by' => $me]);
                }
            }
        }
    }
}

/* ------------------------------------------------------------------
 * Filtri stato.
 * ------------------------------------------------------------------ */
$filter         = (string)($_REQUEST['status'] ?? 'pending');
$allowed_filter = ['pending', 'under_review', 'resolved', 'dismissed', 'all'];
if (!in_array($filter, $allowed_filter, true)) {
    $filter = 'pending';
}

$where_sql = '';
if ($filter !== 'all') {
    $where_sql = " WHERE status = '" . gdrcd_filter('in', $filter) . "'";
}

/* ------------------------------------------------------------------
 * Conteggi per i badge dei tab.
 * ------------------------------------------------------------------ */
$counts_row = gdrcd_query(
    "SELECT "
    . "SUM(status = 'pending')      AS pending_n, "
    . "SUM(status = 'under_review') AS review_n, "
    . "SUM(status = 'resolved')     AS resolved_n, "
    . "SUM(status = 'dismissed')    AS dismissed_n, "
    . "COUNT(*)                     AS all_n "
    . "FROM moderation_reports"
);
$counts = [
    'pending'      => (int)($counts_row['pending_n']   ?? 0),
    'under_review' => (int)($counts_row['review_n']    ?? 0),
    'resolved'     => (int)($counts_row['resolved_n']  ?? 0),
    'dismissed'    => (int)($counts_row['dismissed_n'] ?? 0),
    'all'          => (int)($counts_row['all_n']       ?? 0),
];

/* ------------------------------------------------------------------
 * Recupero righe.
 * ------------------------------------------------------------------ */
$rs = gdrcd_query(
    "SELECT id, reporter, subject, kind, body, context_url, status, severity, "
    . "assigned_to, resolution, created_at, updated_at, resolved_at "
    . "FROM moderation_reports" . $where_sql
    . " ORDER BY FIELD(status,'pending','under_review','resolved','dismissed'), "
    . "          FIELD(severity,'high','medium','low'), "
    . "          created_at DESC LIMIT 200",
    'result'
);

/* ------------------------------------------------------------------
 * Helpers UI.
 * ------------------------------------------------------------------ */
$status_badge = [
    'pending'      => ['label' => 'In attesa',  'class' => 'gdrcd-badge-warning'],
    'under_review' => ['label' => 'In esame',   'class' => 'gdrcd-badge-info'],
    'resolved'     => ['label' => 'Risolta',    'class' => 'gdrcd-badge-success'],
    'dismissed'    => ['label' => 'Archiviata', 'class' => 'gdrcd-badge-neutral'],
];

$severity_badge = [
    'low'    => ['label' => 'Bassa', 'class' => 'gdrcd-badge-neutral'],
    'medium' => ['label' => 'Media', 'class' => 'gdrcd-badge-warning'],
    'high'   => ['label' => 'Alta',  'class' => 'gdrcd-badge-danger'],
];

$kind_label = [
    'chat'     => 'Chat',
    'behavior' => 'Comportamento',
    'content'  => 'Contenuto',
    'other'    => 'Altro',
];

$filter_tabs = [
    'pending'      => ['label' => 'In attesa',  'count' => $counts['pending']],
    'under_review' => ['label' => 'In esame',   'count' => $counts['under_review']],
    'resolved'     => ['label' => 'Risolte',    'count' => $counts['resolved']],
    'dismissed'    => ['label' => 'Archiviate', 'count' => $counts['dismissed']],
    'all'          => ['label' => 'Tutte',      'count' => $counts['all']],
];

/**
 * Tronca un testo per la cella tabella mantenendo le entita' HTML.
 */
$truncate = function (?string $s, int $len = 120): string {
    $s = (string)$s;
    if ($s === '') {
        return '<span class="text-gdrcd-subtle">&mdash;</span>';
    }
    if (mb_strlen($s) > $len) {
        $s = mb_substr($s, 0, $len - 1) . "\u{2026}";
    }
    return gdrcd_filter('out', $s);
};
?>

<div class="space-y-6">

    <header class="space-y-2">
        <h2 class="gdrcd-h1 flex items-center gap-3">
            <span class="gdrcd-icon-circle">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                </svg>
            </span>
            Moderazione segnalazioni
            <?php if ($counts['pending'] > 0): ?>
                <span class="gdrcd-badge-warning text-xs">
                    <?= (int)$counts['pending'] ?> in attesa
                </span>
            <?php endif; ?>
        </h2>
        <p class="gdrcd-muted">
            Coda unificata delle segnalazioni inviate dai giocatori. Prendi in carico le
            segnalazioni e chiudile con una nota di risoluzione tracciabile.
        </p>
    </header>

    <?php if ($flash !== null): ?>
        <?php if ($flash['kind'] === 'success'): ?>
            <div class="gdrcd-alert-success" role="status">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                <div><?= gdrcd_filter('out', $flash['message']) ?></div>
            </div>
        <?php else: ?>
            <div class="gdrcd-alert-warning" role="alert">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
                <div><?= gdrcd_filter('out', $flash['message']) ?></div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <nav class="flex flex-wrap gap-2" aria-label="Filtri stato">
        <?php foreach ($filter_tabs as $key => $tab):
            $url = 'main.php?page=gestione/moderation&status=' . urlencode($key);
            $is_active = ($key === $filter);
        ?>
            <a href="<?= htmlspecialchars($url) ?>"
               class="inline-flex items-center gap-2 px-3 py-1.5 rounded-md text-sm border transition-colors
                      <?= $is_active
                            ? 'border-gdrcd-accent text-gdrcd-accent bg-gdrcd-accent-soft'
                            : 'border-gdrcd-border text-gdrcd-muted hover:text-gdrcd-text hover:border-gdrcd-accent-ring' ?>">
                <?= htmlspecialchars($tab['label']) ?>
                <span class="gdrcd-badge-neutral text-[10px]"><?= (int)$tab['count'] ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <section class="gdrcd-card">
        <div class="gdrcd-card-body">
            <div class="gdrcd-table-wrap">
                <table class="gdrcd-table">
                    <thead>
                        <tr>
                            <th class="w-12">ID</th>
                            <th>Segnalante</th>
                            <th>Oggetto</th>
                            <th>Tipo</th>
                            <th>Gravita'</th>
                            <th>Descrizione</th>
                            <th>Stato</th>
                            <th>Assegnata a</th>
                            <th>Data</th>
                            <th class="text-right">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $any = false;
                        while ($row = gdrcd_query($rs, 'assoc')):
                            $any  = true;
                            $st   = (string)$row['status'];
                            $sev  = (string)$row['severity'];
                            $bk   = $status_badge[$st]   ?? ['label' => $st,  'class' => 'gdrcd-badge-neutral'];
                            $bs   = $severity_badge[$sev] ?? ['label' => $sev, 'class' => 'gdrcd-badge-neutral'];
                            $kind = (string)$row['kind'];
                            $kl   = $kind_label[$kind] ?? $kind;
                            $open = in_array($st, ['pending', 'under_review'], true);
                        ?>
                            <tr>
                                <td class="tabular-nums">#<?= (int)$row['id'] ?></td>
                                <td class="text-sm"><?= gdrcd_filter('out', (string)$row['reporter']) ?></td>
                                <td class="text-sm">
                                    <a class="gdrcd-link"
                                       href="main.php?page=scheda&amp;pg=<?= urlencode((string)$row['subject']) ?>">
                                        <?= gdrcd_filter('out', (string)$row['subject']) ?>
                                    </a>
                                </td>
                                <td class="text-xs"><?= htmlspecialchars($kl) ?></td>
                                <td>
                                    <span class="<?= htmlspecialchars($bs['class']) ?>">
                                        <?= htmlspecialchars($bs['label']) ?>
                                    </span>
                                </td>
                                <td class="max-w-md text-sm" title="<?= gdrcd_filter('out', (string)$row['body']) ?>">
                                    <?= $truncate($row['body'] ?? '', 140) ?>
                                    <?php if (!empty($row['context_url'])):
                                        $url = (string)$row['context_url'];
                                        $is_safe = preg_match('#^(https?://|main\\.php|/)#i', $url) === 1;
                                    ?>
                                        <?php if ($is_safe): ?>
                                            <div class="text-[11px] mt-1">
                                                <a class="gdrcd-link" href="<?= htmlspecialchars($url, ENT_QUOTES) ?>"
                                                   target="_blank" rel="noopener noreferrer">contesto &rarr;</a>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (!empty($row['resolution'])): ?>
                                        <div class="text-[11px] text-gdrcd-muted mt-1 italic"
                                             title="<?= gdrcd_filter('out', (string)$row['resolution']) ?>">
                                            Nota: <?= $truncate($row['resolution'] ?? '', 80) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="<?= htmlspecialchars($bk['class']) ?>">
                                        <?= htmlspecialchars($bk['label']) ?>
                                    </span>
                                </td>
                                <td class="text-xs">
                                    <?= !empty($row['assigned_to'])
                                        ? gdrcd_filter('out', (string)$row['assigned_to'])
                                        : '<span class="text-gdrcd-subtle">&mdash;</span>' ?>
                                </td>
                                <td class="tabular-nums text-xs">
                                    <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$row['created_at']))) ?>
                                </td>
                                <td class="text-right">
                                    <?php if ($open): ?>
                                        <details class="inline-block text-left">
                                            <summary class="gdrcd-btn-secondary cursor-pointer">Azioni</summary>
                                            <div class="mt-2 p-3 rounded-md border border-gdrcd-border bg-gdrcd-card space-y-3 w-80">

                                                <?php if ($st === 'pending' || (string)$row['assigned_to'] !== $me): ?>
                                                    <form action="main.php?page=gestione/moderation&amp;status=<?= urlencode($filter) ?>"
                                                          method="post" class="space-y-2">
                                                        <?= gdrcd_csrf_field() ?>
                                                        <input type="hidden" name="op" value="claim">
                                                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                                        <button type="submit" class="gdrcd-btn-secondary w-full text-xs">
                                                            Prendi in carico
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <form action="main.php?page=gestione/moderation&amp;status=<?= urlencode($filter) ?>"
                                                      method="post" class="space-y-2"
                                                      onsubmit="return confirm('Confermi la risoluzione della segnalazione #<?= (int)$row['id'] ?>?');">
                                                    <?= gdrcd_csrf_field() ?>
                                                    <input type="hidden" name="op" value="resolve">
                                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                                    <label class="gdrcd-label text-xs">Nota di risoluzione (obbligatoria)</label>
                                                    <textarea name="resolution" rows="2" class="gdrcd-textarea w-full text-xs"
                                                              required placeholder="Cosa e\' stato fatto, esito..."></textarea>
                                                    <button type="submit" class="gdrcd-btn-primary w-full text-xs">
                                                        Risolvi
                                                    </button>
                                                </form>

                                                <form action="main.php?page=gestione/moderation&amp;status=<?= urlencode($filter) ?>"
                                                      method="post" class="space-y-2"
                                                      onsubmit="return confirm('Archiviare la segnalazione #<?= (int)$row['id'] ?> (nessuna azione richiesta)?');">
                                                    <?= gdrcd_csrf_field() ?>
                                                    <input type="hidden" name="op" value="dismiss">
                                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                                    <label class="gdrcd-label text-xs">Motivazione archiviazione (opzionale)</label>
                                                    <textarea name="resolution" rows="2" class="gdrcd-textarea w-full text-xs"
                                                              placeholder="Es: segnalazione infondata"></textarea>
                                                    <button type="submit" class="gdrcd-btn-ghost w-full text-xs">
                                                        Archivia
                                                    </button>
                                                </form>

                                            </div>
                                        </details>
                                    <?php else: ?>
                                        <span class="text-gdrcd-subtle text-xs">
                                            <?php if (!empty($row['resolved_at'])): ?>
                                                il <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$row['resolved_at']))) ?>
                                            <?php else: ?>
                                                &mdash;
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; gdrcd_query($rs, 'free'); ?>

                        <?php if (!$any): ?>
                            <tr>
                                <td colspan="10" class="text-center text-gdrcd-muted py-6">
                                    Nessuna segnalazione in questa categoria.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

</div>
