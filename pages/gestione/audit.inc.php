<?php
/**
 * Audit log amministrativo (main.php?page=gestione/audit)
 *
 * Vista globale sulla tabella `log` con filtri combinabili:
 *   - range data (data_evento BETWEEN ? AND ?)
 *   - codice_evento (selezione multipla)
 *   - nome_interessato (LIKE)
 *   - autore (LIKE)
 *   - descrizione_evento (LIKE)
 *
 * Solo MODERATOR+. Tutte le query usano gdrcd_query() con input
 * sanitizzato via gdrcd_filter('in', ...). Filtri persistenti tramite GET
 * per permettere bookmark/condivisione e paginazione coerente.
 */

if (((int)($_SESSION['permessi'] ?? 0)) < MODERATOR) {
    echo '<div class="gdrcd-alert-error">'
       . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>'
       . '<div>' . gdrcd_filter('out', $MESSAGE['error']['not_allowed'] ?? 'Accesso non consentito.') . '</div>'
       . '</div>';
    return;
}

/* ------------------------------------------------------------------
 * Mappa codice_evento -> label leggibile + classe badge.
 * Le label provengono da $MESSAGE['event'] (vocabolario IT) quando
 * disponibili; il colore badge raggruppa per "famiglia" semantica.
 * ------------------------------------------------------------------ */
$event_labels = [
    BLOCKED         => ['label' => $MESSAGE['event'][BLOCKED]         ?? 'Postazioni bloccate', 'badge' => 'gdrcd-badge-error'],
    LOGGEDIN        => ['label' => $MESSAGE['event'][LOGGEDIN]        ?? 'Log in',              'badge' => 'gdrcd-badge-success'],
    ACCOUNTMULTIPLO => ['label' => $MESSAGE['event'][ACCOUNTMULTIPLO] ?? 'Account multipli',    'badge' => 'gdrcd-badge-warning'],
    ERRORELOGIN     => ['label' => $MESSAGE['event'][ERRORELOGIN]     ?? 'Log in errati',       'badge' => 'gdrcd-badge-warning'],
    BONIFICO        => ['label' => $MESSAGE['event'][BONIFICO]        ?? 'Bonifico',            'badge' => 'gdrcd-badge-accent'],
    NUOVOLAVORO     => ['label' => $MESSAGE['event'][NUOVOLAVORO]     ?? 'Assunzione',          'badge' => 'gdrcd-badge-neutral'],
    DIMISSIONE      => ['label' => $MESSAGE['event'][DIMISSIONE]      ?? 'Dimissione',          'badge' => 'gdrcd-badge-neutral'],
    CHANGEDROLE     => ['label' => $MESSAGE['event'][CHANGEDROLE]     ?? 'Cambio permessi',     'badge' => 'gdrcd-badge-accent'],
    CHANGEDPASS     => ['label' => $MESSAGE['event'][CHANGEDPASS]     ?? 'Cambio password',     'badge' => 'gdrcd-badge-neutral'],
    PX              => ['label' => $MESSAGE['event'][PX]              ?? 'Esperienza',          'badge' => 'gdrcd-badge-accent'],
    DELETEPG        => ['label' => $MESSAGE['event'][DELETEPG]        ?? 'PG cancellato',       'badge' => 'gdrcd-badge-error'],
    CHANGEDNAME     => ['label' => $MESSAGE['event'][CHANGEDNAME]     ?? 'Cambio nome',         'badge' => 'gdrcd-badge-accent'],
];

/* ------------------------------------------------------------------
 * Parametri filtro (GET).
 * Default: ultimi 30 giorni, nessun altro filtro.
 * ------------------------------------------------------------------ */
$today        = date('Y-m-d');
$default_from = date('Y-m-d', strtotime('-30 days'));

$is_valid_date = static function ($s): bool {
    if (!is_string($s) || $s === '') return false;
    return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $s);
};

$date_from = $_GET['date_from'] ?? $default_from;
$date_to   = $_GET['date_to']   ?? $today;
if (!$is_valid_date($date_from)) $date_from = $default_from;
if (!$is_valid_date($date_to))   $date_to   = $today;

// Normalizza ordine se invertiti
if (strtotime($date_from) > strtotime($date_to)) {
    [$date_from, $date_to] = [$date_to, $date_from];
}

$codes_raw = $_GET['codes'] ?? [];
if (!is_array($codes_raw)) {
    $codes_raw = [$codes_raw];
}
$valid_codes = array_keys($event_labels);
$selected_codes = [];
foreach ($codes_raw as $c) {
    if (!is_numeric($c)) continue;
    $ci = (int)$c;
    if (in_array($ci, $valid_codes, true) && !in_array($ci, $selected_codes, true)) {
        $selected_codes[] = $ci;
    }
}

$q_interessato = trim((string)($_GET['interessato'] ?? ''));
$q_autore      = trim((string)($_GET['autore']      ?? ''));
$q_descr       = trim((string)($_GET['descr']       ?? ''));

$offset   = max(0, (int)($_GET['offset'] ?? 0));
$per_page = max(1, (int)($PARAMETERS['settings']['records_per_page'] ?? 15));

/* ------------------------------------------------------------------
 * Costruzione WHERE.
 * ------------------------------------------------------------------ */
$where = [];

// Range data (inclusivo: da 00:00:00 a 23:59:59).
$from_q = gdrcd_filter('in', $date_from . ' 00:00:00');
$to_q   = gdrcd_filter('in', $date_to   . ' 23:59:59');
$where[] = "data_evento BETWEEN '" . $from_q . "' AND '" . $to_q . "'";

if (!empty($selected_codes)) {
    $codes_sql = implode(',', array_map('intval', $selected_codes));
    $where[] = "codice_evento IN (" . $codes_sql . ")";
}

if ($q_interessato !== '') {
    $like = gdrcd_filter('in', '%' . $q_interessato . '%');
    $where[] = "nome_interessato LIKE '" . $like . "'";
}
if ($q_autore !== '') {
    $like = gdrcd_filter('in', '%' . $q_autore . '%');
    $where[] = "autore LIKE '" . $like . "'";
}
if ($q_descr !== '') {
    $like = gdrcd_filter('in', '%' . $q_descr . '%');
    $where[] = "descrizione_evento LIKE '" . $like . "'";
}

$where_sql = ' WHERE ' . implode(' AND ', $where);

/* ------------------------------------------------------------------
 * Conteggio + query principale paginata.
 * ------------------------------------------------------------------ */
$count_row = gdrcd_query("SELECT COUNT(*) AS n FROM log" . $where_sql);
$total     = (int)($count_row['n'] ?? 0);

$max_offset = ($total > 0) ? (int)floor(($total - 1) / $per_page) : 0;
if ($offset > $max_offset) {
    $offset = $max_offset;
}
$page_begin = $offset * $per_page;

$rows_rs = gdrcd_query(
    "SELECT id, data_evento, codice_evento, autore, nome_interessato, descrizione_evento"
    . " FROM log"
    . $where_sql
    . " ORDER BY data_evento DESC, id DESC"
    . " LIMIT " . (int)$page_begin . ", " . (int)$per_page,
    'result'
);

/* ------------------------------------------------------------------
 * Helpers rendering.
 * ------------------------------------------------------------------ */
$base_params = [
    'page'      => 'gestione/audit',
    'date_from' => $date_from,
    'date_to'   => $date_to,
];
if ($q_interessato !== '') $base_params['interessato'] = $q_interessato;
if ($q_autore      !== '') $base_params['autore']      = $q_autore;
if ($q_descr       !== '') $base_params['descr']       = $q_descr;

$render_pager = function (int $total, int $current_offset) use ($per_page, $base_params, $selected_codes) {
    if ($total <= $per_page) return '';
    $pages = (int)floor(($total - 1) / $per_page);
    $html  = '<nav class="gdrcd-pager" aria-label="Paginazione">';
    $html .= '<span class="gdrcd-pager-label !border-0 !bg-transparent">Pagina</span>';
    for ($i = 0; $i <= $pages; $i++) {
        if ($i === $current_offset) {
            $html .= '<span class="is-current" aria-current="page">' . ($i + 1) . '</span>';
            continue;
        }
        $params = array_merge($base_params, ['offset' => $i]);
        $qs = http_build_query($params);
        foreach ($selected_codes as $c) {
            $qs .= '&codes%5B%5D=' . (int)$c;
        }
        $html .= '<a href="main.php?' . $qs . '">' . ($i + 1) . '</a>';
    }
    $html .= '</nav>';
    return $html;
};

$truncate = static function (string $s, int $len = 80): array {
    $s = trim($s);
    if (function_exists('mb_strlen') ? mb_strlen($s) <= $len : strlen($s) <= $len) {
        return ['short' => $s, 'full' => $s, 'truncated' => false];
    }
    $short = function_exists('mb_substr') ? mb_substr($s, 0, $len) : substr($s, 0, $len);
    return ['short' => $short . '…', 'full' => $s, 'truncated' => true];
};
?>

<div class="space-y-6">

    <header class="space-y-2">
        <h2 class="gdrcd-h1 flex items-center gap-3">
            <span class="gdrcd-icon-circle">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2M9 12h6m-6 4h4"/>
                </svg>
            </span>
            Audit log
        </h2>
        <p class="gdrcd-muted">
            Vista globale degli eventi registrati nella tabella <code class="font-mono text-xs">log</code>.
            Combina range data, tipologie evento e ricerche testuali per individuare attivita' specifiche.
        </p>
    </header>

    <!-- ============================================================
         Filtri
         ============================================================ -->
    <section class="gdrcd-card">
        <div class="gdrcd-card-header">
            <h3 class="gdrcd-h3">Filtri</h3>
            <p class="gdrcd-muted text-xs">
                Tutti i filtri sono combinati con AND. Le ricerche testuali usano LIKE %valore%.
            </p>
        </div>

        <form action="main.php" method="get" class="gdrcd-card-body space-y-4">
            <input type="hidden" name="page" value="gestione/audit">

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">

                <div class="md:col-span-3">
                    <label class="gdrcd-label" for="audit_from">Dal</label>
                    <input class="gdrcd-input w-full" type="date" id="audit_from" name="date_from"
                           value="<?= htmlspecialchars($date_from) ?>" max="<?= htmlspecialchars($today) ?>">
                </div>

                <div class="md:col-span-3">
                    <label class="gdrcd-label" for="audit_to">Al</label>
                    <input class="gdrcd-input w-full" type="date" id="audit_to" name="date_to"
                           value="<?= htmlspecialchars($date_to) ?>" max="<?= htmlspecialchars($today) ?>">
                </div>

                <div class="md:col-span-6">
                    <label class="gdrcd-label" for="audit_codes">Tipo evento</label>
                    <select class="gdrcd-select w-full" id="audit_codes" name="codes[]" multiple size="5">
                        <?php foreach ($event_labels as $code => $info):
                            $sel = in_array($code, $selected_codes, true) ? ' selected' : ''; ?>
                            <option value="<?= (int)$code ?>"<?= $sel ?>>
                                <?= gdrcd_filter('out', $info['label']) ?> (#<?= (int)$code ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="gdrcd-help text-xs">Tieni premuto Ctrl/Cmd per selezione multipla. Vuoto = tutti.</p>
                </div>

                <div class="md:col-span-4">
                    <label class="gdrcd-label" for="audit_interessato">Interessato</label>
                    <input class="gdrcd-input w-full" type="text" id="audit_interessato" name="interessato"
                           value="<?= gdrcd_filter('out', $q_interessato) ?>"
                           placeholder="Nome PG o frammento" maxlength="64">
                </div>

                <div class="md:col-span-4">
                    <label class="gdrcd-label" for="audit_autore">Autore</label>
                    <input class="gdrcd-input w-full" type="text" id="audit_autore" name="autore"
                           value="<?= gdrcd_filter('out', $q_autore) ?>"
                           placeholder="Autore o frammento" maxlength="64">
                </div>

                <div class="md:col-span-4">
                    <label class="gdrcd-label" for="audit_descr">Descrizione</label>
                    <input class="gdrcd-input w-full" type="text" id="audit_descr" name="descr"
                           value="<?= gdrcd_filter('out', $q_descr) ?>"
                           placeholder="Cerca nel testo evento" maxlength="255">
                </div>
            </div>

            <div class="flex flex-wrap gap-2 justify-end">
                <a href="main.php?page=gestione/audit" class="gdrcd-btn-ghost">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Reset
                </a>
                <button type="submit" class="gdrcd-btn-primary">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    Applica
                </button>
            </div>
        </form>
    </section>

    <!-- ============================================================
         Risultati
         ============================================================ -->
    <section class="gdrcd-card">
        <div class="gdrcd-card-header flex items-center justify-between gap-3 flex-wrap">
            <h3 class="gdrcd-h3">Risultati</h3>
            <div class="flex items-center gap-2">
                <span class="gdrcd-badge-neutral"><?= (int)$total ?> totali</span>
                <span class="gdrcd-muted text-xs">
                    <?= gdrcd_filter('out', $date_from) ?>
                    <span class="text-gdrcd-subtle">→</span>
                    <?= gdrcd_filter('out', $date_to) ?>
                </span>
            </div>
        </div>

        <div class="gdrcd-card-body space-y-4">

            <?php if ($total === 0): ?>
                <div class="gdrcd-alert-info">
                    <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>Nessun evento corrisponde ai filtri selezionati.</div>
                </div>
            <?php else: ?>
                <div class="gdrcd-table-wrap">
                    <table class="gdrcd-table">
                        <thead>
                            <tr>
                                <th class="whitespace-nowrap">ID</th>
                                <th class="whitespace-nowrap">Data</th>
                                <th class="whitespace-nowrap">Tipo</th>
                                <th class="whitespace-nowrap">Autore</th>
                                <th class="whitespace-nowrap">Interessato</th>
                                <th>Dettaglio</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = gdrcd_query($rows_rs, 'fetch')):
                                $code   = (int)$row['codice_evento'];
                                $info   = $event_labels[$code] ?? ['label' => '#' . $code, 'badge' => 'gdrcd-badge-neutral'];
                                $detail = $truncate((string)($row['descrizione_evento'] ?? ''), 80);
                                ?>
                                <tr>
                                    <td class="font-mono text-xs text-gdrcd-muted whitespace-nowrap">
                                        #<?= (int)$row['id'] ?>
                                    </td>
                                    <td class="text-gdrcd-muted whitespace-nowrap tabular-nums">
                                        <?= gdrcd_format_date($row['data_evento']) ?>
                                        <span class="text-gdrcd-subtle">·</span>
                                        <?= gdrcd_format_time($row['data_evento']) ?>
                                    </td>
                                    <td class="whitespace-nowrap">
                                        <span class="<?= $info['badge'] ?>" title="codice_evento = <?= (int)$code ?>">
                                            <?= gdrcd_filter('out', $info['label']) ?>
                                        </span>
                                    </td>
                                    <td class="font-mono text-xs whitespace-nowrap">
                                        <?php if (!empty($row['autore'])): ?>
                                            <a class="gdrcd-link"
                                               href="main.php?page=scheda&pg=<?= urlencode((string)$row['autore']) ?>"
                                               title="Apri scheda autore">
                                                <?= gdrcd_filter('out', (string)$row['autore']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gdrcd-subtle">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="font-medium text-gdrcd-text whitespace-nowrap">
                                        <?php if (!empty($row['nome_interessato'])): ?>
                                            <a class="gdrcd-link"
                                               href="main.php?page=scheda&pg=<?= urlencode((string)$row['nome_interessato']) ?>"
                                               title="Apri scheda interessato">
                                                <?= gdrcd_filter('out', (string)$row['nome_interessato']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gdrcd-subtle">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-sm break-words">
                                        <?php if ($detail['truncated']): ?>
                                            <span title="<?= htmlspecialchars($detail['full'], ENT_QUOTES) ?>">
                                                <?= gdrcd_filter('out', $detail['short']) ?>
                                            </span>
                                        <?php else: ?>
                                            <?= gdrcd_filter('out', $detail['full']) ?>
                                        <?php endif; ?>
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
