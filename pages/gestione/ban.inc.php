<?php
/**
 * Gestione ban/blacklist IP (main.php?page=gestione/ban)
 *
 * Pannello per moderatori (MODERATOR+) per gestire la tabella `blacklist`.
 *
 *   - granted = 0 => ban attivo
 *   - granted = 1 => ban revocato (whitelist)
 *   - expires_at NULL => ban permanente
 *   - expires_at > NOW() => ban a tempo ancora valido
 *   - expires_at <= NOW() => ban scaduto (login.php lo ignora gia')
 *
 * CSRF: validato centralmente da main.php sui POST.
 * Schema: tabella `blacklist` con colonna `expires_at` aggiunta dalla
 * migrazione 2026051117_GDRCDBlacklistExpiry.
 */

if (($_SESSION['permessi'] ?? 0) < MODERATOR) {
    echo '<div class="gdrcd-alert-error">'
       . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>'
       . '<div>' . gdrcd_filter('out', $MESSAGE['error']['not_allowed'] ?? 'Accesso non consentito.') . '</div>'
       . '</div>';
    return;
}

/* ------------------------------------------------------------------
 * Stato corrente da querystring.
 * ------------------------------------------------------------------ */
$filter   = $_REQUEST['filter'] ?? 'active';
$q        = trim((string)($_REQUEST['q'] ?? ''));
$offset   = max(0, (int)($_REQUEST['offset'] ?? 0));
$per_page = max(1, (int)($PARAMETERS['settings']['records_per_page'] ?? 15));

$allowed_filters = ['active', 'granted', 'expired', 'all'];
if (!in_array($filter, $allowed_filters, true)) {
    $filter = 'active';
}

$flash = null;

/* ------------------------------------------------------------------
 * Handler POST (CSRF gia' validato in main.php).
 * ------------------------------------------------------------------ */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $op = $_POST['op'] ?? '';

    switch ($op) {

        case 'add':
            $ip      = trim((string)($_POST['ip'] ?? ''));
            $nota    = trim((string)($_POST['nota'] ?? ''));
            $days    = (int)($_POST['days'] ?? 0);

            if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
                $flash = ['kind' => 'warning', 'message' => 'Indirizzo IP non valido.'];
            } else {
                $host = @gethostbyaddr($ip);
                if (!is_string($host) || $host === '') {
                    $host = '-';
                }

                if ($days > 0) {
                    // INTERVAL non puo' essere parametrizzato -> $days e' int.
                    $sql_insert = "INSERT INTO blacklist (ip, nota, granted, ora, host, expires_at) VALUES (?, ?, 0, NOW(), ?, DATE_ADD(NOW(), INTERVAL " . (int)$days . " DAY)) "
                                . "ON DUPLICATE KEY UPDATE nota = VALUES(nota), granted = 0, ora = NOW(), host = VALUES(host), expires_at = VALUES(expires_at)";
                } else {
                    $sql_insert = "INSERT INTO blacklist (ip, nota, granted, ora, host, expires_at) VALUES (?, ?, 0, NOW(), ?, NULL) "
                                . "ON DUPLICATE KEY UPDATE nota = VALUES(nota), granted = 0, ora = NOW(), host = VALUES(host), expires_at = VALUES(expires_at)";
                }

                Db::preparedExecute($sql_insert, 'sss', array($ip, $nota, $host));

                $flash = ['kind' => 'success', 'message' => 'Ban aggiunto: ' . $ip . '.'];
            }
            break;

        case 'revoke':
            $ip = trim((string)($_POST['ip'] ?? ''));
            if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
                $flash = ['kind' => 'warning', 'message' => 'IP non valido.'];
            } else {
                Db::preparedExecute("UPDATE blacklist SET granted = 1 WHERE ip = ?", 's', array($ip));
                $flash = ['kind' => 'success', 'message' => 'Ban revocato per ' . $ip . '.'];
            }
            break;

        case 'reactivate':
            $ip = trim((string)($_POST['ip'] ?? ''));
            if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
                $flash = ['kind' => 'warning', 'message' => 'IP non valido.'];
            } else {
                // Riattivare un ban scaduto -> azzera expires_at (ban permanente).
                Db::preparedExecute(
                    "UPDATE blacklist SET granted = 0, expires_at = NULL, ora = NOW() WHERE ip = ?",
                    's',
                    array($ip)
                );
                $flash = ['kind' => 'success', 'message' => 'Ban riattivato per ' . $ip . '.'];
            }
            break;

        case 'delete':
            $ip = trim((string)($_POST['ip'] ?? ''));
            if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
                $flash = ['kind' => 'warning', 'message' => 'IP non valido.'];
            } else {
                Db::preparedExecute("DELETE FROM blacklist WHERE ip = ?", 's', array($ip));
                $flash = ['kind' => 'success', 'message' => 'Riga eliminata per ' . $ip . '.'];
            }
            break;

        case 'edit_note':
            $ip   = trim((string)($_POST['ip'] ?? ''));
            $nota = trim((string)($_POST['nota'] ?? ''));
            if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
                $flash = ['kind' => 'warning', 'message' => 'IP non valido.'];
            } else {
                Db::preparedExecute(
                    "UPDATE blacklist SET nota = ? WHERE ip = ?",
                    'ss',
                    array($nota, $ip)
                );
                $flash = ['kind' => 'success', 'message' => 'Nota aggiornata per ' . $ip . '.'];
            }
            break;
    }
}

/* ------------------------------------------------------------------
 * Costruzione clausola WHERE in funzione di filtro + ricerca.
 * ------------------------------------------------------------------ */
$where = [];
switch ($filter) {
    case 'active':
        $where[] = 'granted = 0';
        $where[] = '(expires_at IS NULL OR expires_at > NOW())';
        break;
    case 'granted':
        $where[] = 'granted = 1';
        break;
    case 'expired':
        $where[] = 'granted = 0';
        $where[] = 'expires_at IS NOT NULL';
        $where[] = 'expires_at <= NOW()';
        break;
    case 'all':
    default:
        // Nessuna condizione: tutto.
        break;
}

$types  = '';
$params = array();

if ($q !== '') {
    $where[]  = "(ip LIKE ? OR nota LIKE ?)";
    $types   .= 'ss';
    $like     = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}

$where_sql = empty($where) ? '' : (' WHERE ' . implode(' AND ', $where));

/* ------------------------------------------------------------------
 * Conteggio totale + contatori per badge filtro.
 * ------------------------------------------------------------------ */
$count_row = Db::preparedFetch("SELECT COUNT(*) AS n FROM blacklist" . $where_sql, $types, $params);
$total     = (int)($count_row['n'] ?? 0);

$counts = ['active' => 0, 'granted' => 0, 'expired' => 0, 'all' => 0];
$crs = gdrcd_query(
    "SELECT "
    . "SUM(granted = 0 AND (expires_at IS NULL OR expires_at > NOW())) AS active_n, "
    . "SUM(granted = 1) AS granted_n, "
    . "SUM(granted = 0 AND expires_at IS NOT NULL AND expires_at <= NOW()) AS expired_n, "
    . "COUNT(*) AS all_n "
    . "FROM blacklist"
);
$counts['active']  = (int)($crs['active_n']  ?? 0);
$counts['granted'] = (int)($crs['granted_n'] ?? 0);
$counts['expired'] = (int)($crs['expired_n'] ?? 0);
$counts['all']     = (int)($crs['all_n']     ?? 0);

/* ------------------------------------------------------------------
 * Calcolo paginazione e query principale.
 * ------------------------------------------------------------------ */
$max_offset = ($total > 0) ? (int)floor(($total - 1) / $per_page) : 0;
if ($offset > $max_offset) {
    $offset = $max_offset;
}
$page_begin = $offset * $per_page;

$rows_rs = Db::prepared(
    "SELECT ip, nota, granted, ora, host, expires_at FROM blacklist"
    . $where_sql
    . " ORDER BY ora DESC LIMIT ?, ?",
    $types . 'ii',
    array_merge($params, array((int)$page_begin, (int)$per_page))
);

/* ------------------------------------------------------------------
 * Helpers di rendering.
 * ------------------------------------------------------------------ */
$base_url_params = [
    'page'   => 'gestione/ban',
    'filter' => $filter,
];
if ($q !== '') {
    $base_url_params['q'] = $q;
}

$render_pager = function (int $total, int $current_offset) use ($per_page, $base_url_params) {
    if ($total <= $per_page) {
        return '';
    }
    $pages = (int)floor(($total - 1) / $per_page);
    $html  = '<nav class="gdrcd-pager" aria-label="Paginazione">';
    $html .= '<span class="gdrcd-pager-label !border-0 !bg-transparent">Pagina</span>';
    for ($i = 0; $i <= $pages; $i++) {
        if ($i === $current_offset) {
            $html .= '<span class="is-current" aria-current="page">' . ($i + 1) . '</span>';
        } else {
            $params = array_merge($base_url_params, ['offset' => $i]);
            $url    = 'main.php?' . http_build_query($params);
            $html  .= '<a href="' . htmlspecialchars($url) . '">' . ($i + 1) . '</a>';
        }
    }
    $html .= '</nav>';
    return $html;
};

$ban_status = function (array $row) {
    $granted    = (int)$row['granted'];
    $expires_at = $row['expires_at'] ?? null;

    if ($granted === 1) {
        return ['label' => 'Revocato', 'class' => 'gdrcd-badge-neutral'];
    }
    if ($expires_at !== null && $expires_at !== '' && strtotime($expires_at) <= time()) {
        return ['label' => 'Scaduto', 'class' => 'gdrcd-badge-neutral'];
    }
    if ($expires_at === null || $expires_at === '') {
        return ['label' => 'Attivo (permanente)', 'class' => 'gdrcd-badge-error'];
    }
    return ['label' => 'Attivo (a tempo)', 'class' => 'gdrcd-badge-error'];
};

$filter_tabs = [
    'active'  => ['label' => 'Attivi',    'count' => $counts['active']],
    'expired' => ['label' => 'Scaduti',   'count' => $counts['expired']],
    'granted' => ['label' => 'Revocati',  'count' => $counts['granted']],
    'all'     => ['label' => 'Tutti',     'count' => $counts['all']],
];
?>

<div class="space-y-6">

    <header class="space-y-2">
        <h2 class="gdrcd-h1 flex items-center gap-3">
            <span class="gdrcd-icon-circle">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 2l8 4v6c0 5-3.5 9.5-8 10-4.5-.5-8-5-8-10V6l8-4z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/>
                </svg>
            </span>
            Ban e blacklist
        </h2>
        <p class="gdrcd-muted">
            Gestisci gli IP bloccati al login. I ban a tempo scadono automaticamente; i ban revocati restano in tabella
            come storico ma non bloccano piu' l'accesso.
        </p>
    </header>

    <?php if ($flash !== null): ?>
        <?php if ($flash['kind'] === 'success'): ?>
            <div class="gdrcd-alert-success">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                <div><?= gdrcd_filter('out', $flash['message']) ?></div>
            </div>
        <?php else: ?>
            <div class="gdrcd-alert-warning">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
                <div><?= gdrcd_filter('out', $flash['message']) ?></div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- ============================================================
         Form: aggiunta ban
         ============================================================ -->
    <section class="gdrcd-card">
        <div class="gdrcd-card-header">
            <h3 class="gdrcd-h3">Aggiungi ban</h3>
            <p class="gdrcd-muted text-xs">Inserisci un nuovo IP da bloccare. Durata 0 = ban permanente.</p>
        </div>

        <form action="main.php?page=gestione/ban" method="post" class="gdrcd-card-body grid grid-cols-1 md:grid-cols-12 gap-4">
            <?= gdrcd_csrf_field() ?>
            <input type="hidden" name="op" value="add">

            <div class="md:col-span-4">
                <label class="gdrcd-label" for="ban_ip">Indirizzo IP</label>
                <input class="gdrcd-input w-full" type="text" id="ban_ip" name="ip"
                       placeholder="es. 192.168.1.42" maxlength="45" required>
            </div>

            <div class="md:col-span-5">
                <label class="gdrcd-label" for="ban_nota">Nota</label>
                <input class="gdrcd-input w-full" type="text" id="ban_nota" name="nota"
                       placeholder="Motivo del ban (visibile in lista)" maxlength="255">
            </div>

            <div class="md:col-span-2">
                <label class="gdrcd-label" for="ban_days">Durata (giorni)</label>
                <input class="gdrcd-input w-full" type="number" id="ban_days" name="days"
                       value="0" min="0" max="3650">
                <p class="gdrcd-help text-xs">0 = permanente.</p>
            </div>

            <div class="md:col-span-1 flex items-end">
                <button type="submit" class="gdrcd-btn-primary w-full">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Aggiungi
                </button>
            </div>
        </form>
    </section>

    <!-- ============================================================
         Filtri + ricerca
         ============================================================ -->
    <section class="gdrcd-card">
        <div class="gdrcd-card-body space-y-4">

            <nav class="flex flex-wrap gap-2" aria-label="Filtri stato">
                <?php foreach ($filter_tabs as $key => $tab):
                    $params = ['page' => 'gestione/ban', 'filter' => $key];
                    if ($q !== '') $params['q'] = $q;
                    $url = 'main.php?' . http_build_query($params);
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

            <form action="main.php" method="get" class="flex flex-col sm:flex-row gap-3 sm:items-end">
                <input type="hidden" name="page" value="gestione/ban">
                <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">

                <div class="flex-1">
                    <label class="gdrcd-label" for="ban_q">Cerca per IP o nota</label>
                    <input class="gdrcd-input w-full" type="search" id="ban_q" name="q"
                           value="<?= gdrcd_filter('out', $q) ?>"
                           placeholder="es. 10.0.0 oppure spam">
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="gdrcd-btn-secondary">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        Cerca
                    </button>
                    <?php if ($q !== ''): ?>
                        <a class="gdrcd-btn-ghost"
                           href="main.php?page=gestione/ban&filter=<?= htmlspecialchars($filter) ?>">
                            Pulisci
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </section>

    <!-- ============================================================
         Tabella risultati
         ============================================================ -->
    <section class="gdrcd-card">
        <div class="gdrcd-card-header flex items-center justify-between gap-3 flex-wrap">
            <h3 class="gdrcd-h3">Risultati</h3>
            <span class="gdrcd-badge-neutral"><?= (int)$total ?> totali</span>
        </div>

        <div class="gdrcd-card-body space-y-4">

            <?php if ($total === 0): ?>
                <div class="gdrcd-alert-info">
                    <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>Nessun ban corrisponde ai criteri di ricerca.</div>
                </div>
            <?php else: ?>
                <div class="gdrcd-table-wrap">
                    <table class="gdrcd-table">
                        <thead>
                            <tr>
                                <th>IP</th>
                                <th>Nota</th>
                                <th>Host</th>
                                <th class="whitespace-nowrap">Aggiunto il</th>
                                <th class="whitespace-nowrap">Scade il</th>
                                <th>Stato</th>
                                <th class="text-right">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = gdrcd_query($rows_rs, 'fetch')):
                                $status   = $ban_status($row);
                                $ip_attr  = htmlspecialchars((string)$row['ip']);
                                $is_granted = ((int)$row['granted'] === 1);
                                $is_expired = (!$is_granted
                                               && !empty($row['expires_at'])
                                               && strtotime((string)$row['expires_at']) <= time());
                                ?>
                                <tr>
                                    <td class="font-mono text-xs text-gdrcd-text whitespace-nowrap">
                                        <?= gdrcd_filter('out', (string)$row['ip']) ?>
                                    </td>
                                    <td>
                                        <form action="main.php?page=gestione/ban&filter=<?= htmlspecialchars($filter) ?>&offset=<?= (int)$offset ?>"
                                              method="post" class="flex gap-2 items-center">
                                            <?= gdrcd_csrf_field() ?>
                                            <input type="hidden" name="op" value="edit_note">
                                            <input type="hidden" name="ip" value="<?= $ip_attr ?>">
                                            <input class="gdrcd-input flex-1 text-xs py-1" type="text" name="nota"
                                                   value="<?= gdrcd_filter('out', (string)($row['nota'] ?? '')) ?>"
                                                   maxlength="255">
                                            <button type="submit" class="gdrcd-btn-ghost text-xs py-1 px-2" title="Salva nota">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            </button>
                                        </form>
                                    </td>
                                    <td class="text-xs text-gdrcd-muted break-all">
                                        <?= gdrcd_filter('out', (string)($row['host'] ?? '-')) ?>
                                    </td>
                                    <td class="text-xs text-gdrcd-muted whitespace-nowrap tabular-nums">
                                        <?php if (!empty($row['ora'])): ?>
                                            <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$row['ora']))) ?>
                                        <?php else: ?>
                                            &mdash;
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-xs whitespace-nowrap tabular-nums">
                                        <?php if (empty($row['expires_at'])): ?>
                                            <span class="text-gdrcd-muted italic">Permanente</span>
                                        <?php else: ?>
                                            <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$row['expires_at']))) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="<?= $status['class'] ?>">
                                            <?= htmlspecialchars($status['label']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap gap-1 justify-end">

                                            <?php if (!$is_granted && !$is_expired): ?>
                                                <form action="main.php?page=gestione/ban&filter=<?= htmlspecialchars($filter) ?>&offset=<?= (int)$offset ?>"
                                                      method="post" class="inline">
                                                    <?= gdrcd_csrf_field() ?>
                                                    <input type="hidden" name="op" value="revoke">
                                                    <input type="hidden" name="ip" value="<?= $ip_attr ?>">
                                                    <button type="submit" class="gdrcd-btn-ghost text-xs py-1 px-2" title="Revoca ban">
                                                        Revoca
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($is_granted || $is_expired): ?>
                                                <form action="main.php?page=gestione/ban&filter=<?= htmlspecialchars($filter) ?>&offset=<?= (int)$offset ?>"
                                                      method="post" class="inline">
                                                    <?= gdrcd_csrf_field() ?>
                                                    <input type="hidden" name="op" value="reactivate">
                                                    <input type="hidden" name="ip" value="<?= $ip_attr ?>">
                                                    <button type="submit" class="gdrcd-btn-secondary text-xs py-1 px-2" title="Riattiva ban (permanente)">
                                                        Riattiva
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <form action="main.php?page=gestione/ban&filter=<?= htmlspecialchars($filter) ?>&offset=<?= (int)$offset ?>"
                                                  method="post" class="inline"
                                                  onsubmit="return confirm('Eliminare definitivamente la voce per <?= $ip_attr ?>?');">
                                                <?= gdrcd_csrf_field() ?>
                                                <input type="hidden" name="op" value="delete">
                                                <input type="hidden" name="ip" value="<?= $ip_attr ?>">
                                                <button type="submit" class="gdrcd-btn-danger text-xs py-1 px-2" title="Elimina riga">
                                                    Elimina
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile;
                            gdrcd_query($rows_rs, 'free');
                            ?>
                        </tbody>
                    </table>
                </div>

                <?= $render_pager($total, $offset) ?>
            <?php endif; ?>

        </div>
    </section>

</div>
