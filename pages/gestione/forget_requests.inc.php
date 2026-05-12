<?php
/**
 * Gestione richieste di cancellazione GDPR (art. 17)
 *   main.php?page=gestione/forget_requests
 *
 * Pannello SUPERUSER per processare le richieste aperte dagli utenti dalla
 * pagina main.php?page=user_forget. Le richieste sono salvate in
 * `deletion_requests` (vedi migrazione 2026051116_GDRCDDeletionRequests).
 *
 * Azioni:
 *   - anonymize: rinomina il personaggio in "Cancellato_<id>" e cascading
 *                rename su tutte le tabelle che lo referenziano per nome;
 *                segna la richiesta come 'processed'.
 *   - reject:    segna la richiesta come 'rejected' con una nota motivazionale.
 *
 * CSRF: validato centralmente in main.php.
 * Schema: tabella `deletion_requests` (vedi migrazione 2026051116).
 */

if (($_SESSION['permessi'] ?? 0) !== SUPERUSER) {
    echo '<div class="gdrcd-alert-error">'
       . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>'
       . '<div>' . gdrcd_filter('out', $MESSAGE['error']['not_allowed'] ?? 'Accesso non consentito.') . '</div>'
       . '</div>';
    return;
}

/* ------------------------------------------------------------------
 * Tabelle che referenziano `personaggio.nome` come stringa libera.
 * Mappa: tabella => [ colonne da rinominare ].
 * Usata sia in fase di anonymize che, in futuro, per audit.
 * ------------------------------------------------------------------ */
$cascade_targets = [
    'log'                   => ['nome_interessato', 'autore'],
    'messaggi'              => ['mittente', 'destinatario'],
    'backmessaggi'          => ['mittente', 'destinatario'],
    'chat'                  => ['mittente', 'destinatario'],
    'clgpersonaggioabilita' => ['nome'],
    'clgpersonaggiomostrine'=> ['nome'],
    'clgpersonaggiooggetto' => ['nome'],
    'clgpersonaggioruolo'   => ['personaggio'],
    'diario'                => ['personaggio'],
    'segnalazione_role'     => ['mittente'],
];

$flash = null;

/* ------------------------------------------------------------------
 * Helpers.
 * ------------------------------------------------------------------ */
$fetch_request = function (int $id) {
    return Db::preparedFetch(
        "SELECT id, user_login, user_email, reason, status, requested_at, "
        . "processed_at, processed_by, note_admin "
        . "FROM deletion_requests WHERE id = ? LIMIT 1",
        'i',
        array($id)
    );
};

/* ------------------------------------------------------------------
 * Handler POST.
 * ------------------------------------------------------------------ */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $op = $_POST['op'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        $flash = ['kind' => 'warning', 'message' => 'Richiesta non valida.'];
    } else {
        $req = $fetch_request($id);
        if (empty($req)) {
            $flash = ['kind' => 'warning', 'message' => 'Richiesta inesistente.'];
        } elseif ($req['status'] !== 'pending') {
            $flash = ['kind' => 'warning', 'message' => 'La richiesta e\' gia\' stata processata.'];
        } elseif ($op === 'anonymize') {

            $user_login = (string)$req['user_login'];
            $anon_label = 'Cancellato_' . $id;
            $note       = trim((string)($_POST['note_admin'] ?? ''));
            $admin      = (string)($_SESSION['login'] ?? '');

            // 1) Azzera dati personali della riga `personaggio`.
            //    Nota: alcuni campi (biografia/frase) non esistono nello schema
            //    attuale, vengono ignorati. permessi = -1 disattiva l'account.
            Db::preparedExecute(
                "UPDATE personaggio SET "
                . "cognome = '', email = '', permessi = -1, "
                . "descrizione = '', storia = '', "
                . "url_img = '', url_img_chat = '', "
                . "affetti = '', stato = '', "
                . "nome = ? "
                . "WHERE nome = ?",
                'ss',
                array($anon_label, $user_login)
            );

            // 2) Cascading rename su tutte le tabelle che referenziano il nome.
            //    Whitelist statica: $cascade_targets contiene SOLO costanti del codice.
            foreach ($cascade_targets as $table => $cols) {
                foreach ($cols as $col) {
                    Db::preparedExecute(
                        "UPDATE `" . $table . "` SET `" . $col . "` = ? WHERE `" . $col . "` = ?",
                        'ss',
                        array($anon_label, $user_login)
                    );
                }
            }

            // 3) Caso speciale: segnalazione_role.partecipanti e' un campo
            //    testuale con elenco nomi separati. Sostituiamo il nome come
            //    token (best-effort), evitando di toccare prefissi/suffissi.
            Db::preparedExecute(
                "UPDATE segnalazione_role SET partecipanti = REPLACE(partecipanti, ?, ?) "
                . "WHERE partecipanti LIKE ?",
                'sss',
                array($user_login, $anon_label, '%' . $user_login . '%')
            );

            // 4) Aggiorna la richiesta.
            Db::preparedExecute(
                "UPDATE deletion_requests SET "
                . "status = 'processed', "
                . "processed_at = NOW(), "
                . "processed_by = ?, "
                . "note_admin = ? "
                . "WHERE id = ?",
                'ssi',
                array($admin, $note, $id)
            );

            if (function_exists('gdrcd_log_info')) {
                gdrcd_log_info('GDPR deletion request processed (anonymized)', [
                    'id'          => $id,
                    'user_login'  => $user_login,
                    'anonymized'  => $anon_label,
                    'admin'       => (string)($_SESSION['login'] ?? ''),
                ]);
            }

            $flash = [
                'kind'    => 'success',
                'message' => 'Account anonimizzato. Il personaggio "' . $user_login
                              . '" e\' ora "' . $anon_label . '".',
            ];

        } elseif ($op === 'reject') {

            $note    = trim((string)($_POST['note_admin'] ?? ''));
            if ($note === '') {
                $flash = ['kind' => 'warning', 'message' => 'Per rifiutare una richiesta e\' necessaria una motivazione.'];
            } else {
                Db::preparedExecute(
                    "UPDATE deletion_requests SET "
                    . "status = 'rejected', "
                    . "processed_at = NOW(), "
                    . "processed_by = ?, "
                    . "note_admin = ? "
                    . "WHERE id = ?",
                    'ssi',
                    array((string)($_SESSION['login'] ?? ''), $note, $id)
                );

                if (function_exists('gdrcd_log_info')) {
                    gdrcd_log_info('GDPR deletion request rejected', [
                        'id'    => $id,
                        'admin' => (string)($_SESSION['login'] ?? ''),
                    ]);
                }

                $flash = ['kind' => 'success', 'message' => 'Richiesta rifiutata.'];
            }
        }
    }
}

/* ------------------------------------------------------------------
 * Filtri.
 * ------------------------------------------------------------------ */
$filter         = $_REQUEST['filter'] ?? 'pending';
$allowed_filter = ['pending', 'processed', 'rejected', 'all'];
if (!in_array($filter, $allowed_filter, true)) {
    $filter = 'pending';
}

// $filter validato da whitelist sopra.
$where_sql     = '';
$filter_types  = '';
$filter_params = array();
if ($filter !== 'all') {
    $where_sql     = " WHERE status = ?";
    $filter_types  = 's';
    $filter_params = array($filter);
}

/* ------------------------------------------------------------------
 * Conteggi per i badge.
 * ------------------------------------------------------------------ */
$counts_row = gdrcd_query(
    "SELECT "
    . "SUM(status = 'pending')   AS pending_n, "
    . "SUM(status = 'processed') AS processed_n, "
    . "SUM(status = 'rejected')  AS rejected_n, "
    . "COUNT(*)                  AS all_n "
    . "FROM deletion_requests"
);
$counts = [
    'pending'   => (int)($counts_row['pending_n']   ?? 0),
    'processed' => (int)($counts_row['processed_n'] ?? 0),
    'rejected'  => (int)($counts_row['rejected_n']  ?? 0),
    'all'       => (int)($counts_row['all_n']       ?? 0),
];

/* ------------------------------------------------------------------
 * Recupero righe.
 * ------------------------------------------------------------------ */
$rs = Db::prepared(
    "SELECT id, user_login, user_email, reason, status, requested_at, "
    . "processed_at, processed_by, note_admin "
    . "FROM deletion_requests" . $where_sql . " ORDER BY requested_at DESC LIMIT 200",
    $filter_types,
    $filter_params
);

$status_badge = [
    'pending'   => ['label' => 'In attesa',  'class' => 'gdrcd-badge-warning'],
    'processed' => ['label' => 'Processata', 'class' => 'gdrcd-badge-success'],
    'rejected'  => ['label' => 'Rifiutata',  'class' => 'gdrcd-badge-neutral'],
];

$filter_tabs = [
    'pending'   => ['label' => 'In attesa',  'count' => $counts['pending']],
    'processed' => ['label' => 'Processate', 'count' => $counts['processed']],
    'rejected'  => ['label' => 'Rifiutate',  'count' => $counts['rejected']],
    'all'       => ['label' => 'Tutte',      'count' => $counts['all']],
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
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 2l8 4v6c0 5-3.5 9.5-8 10-4.5-.5-8-5-8-10V6l8-4z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/>
                </svg>
            </span>
            Richieste cancellazione (GDPR art. 17)
        </h2>
        <p class="gdrcd-muted">
            Processa le richieste di "diritto all'oblio" aperte dai giocatori.
            L'anonimizzazione rinomina il personaggio in <code>Cancellato_&lt;id&gt;</code>
            su tutte le tabelle che lo referenziano. L'operazione e' irreversibile.
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

    <nav class="flex flex-wrap gap-2" aria-label="Filtri stato">
        <?php foreach ($filter_tabs as $key => $tab):
            $url = 'main.php?page=gestione/forget_requests&filter=' . urlencode($key);
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
                            <th>Utente</th>
                            <th>Email</th>
                            <th>Data richiesta</th>
                            <th>Motivo</th>
                            <th>Stato</th>
                            <th class="text-right">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $any = false;
                        while ($row = gdrcd_query($rs, 'assoc')):
                            $any = true;
                            $status = (string)$row['status'];
                            $badge  = $status_badge[$status] ?? ['label' => $status, 'class' => 'gdrcd-badge-neutral'];
                        ?>
                            <tr>
                                <td class="tabular-nums">#<?= (int)$row['id'] ?></td>
                                <td><?= gdrcd_filter('out', (string)$row['user_login']) ?></td>
                                <td class="text-gdrcd-muted text-xs">
                                    <?= $row['user_email'] !== null && $row['user_email'] !== ''
                                        ? gdrcd_filter('out', (string)$row['user_email'])
                                        : '<span class="text-gdrcd-subtle">&mdash;</span>' ?>
                                </td>
                                <td class="tabular-nums text-xs">
                                    <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$row['requested_at']))) ?>
                                </td>
                                <td class="max-w-md text-sm" title="<?= gdrcd_filter('out', (string)$row['reason']) ?>">
                                    <?= $truncate($row['reason'] ?? '', 140) ?>
                                </td>
                                <td>
                                    <span class="<?= htmlspecialchars($badge['class']) ?>">
                                        <?= htmlspecialchars($badge['label']) ?>
                                    </span>
                                    <?php if ($status !== 'pending'): ?>
                                        <div class="text-[11px] text-gdrcd-muted mt-1">
                                            <?php if (!empty($row['processed_at'])): ?>
                                                il <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$row['processed_at']))) ?>
                                            <?php endif; ?>
                                            <?php if (!empty($row['processed_by'])): ?>
                                                da <strong><?= gdrcd_filter('out', (string)$row['processed_by']) ?></strong>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($row['note_admin'])): ?>
                                            <div class="text-[11px] text-gdrcd-muted mt-1 italic"
                                                 title="<?= gdrcd_filter('out', (string)$row['note_admin']) ?>">
                                                <?= $truncate($row['note_admin'] ?? '', 80) ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <?php if ($status === 'pending'): ?>
                                        <details class="inline-block text-left">
                                            <summary class="gdrcd-btn-secondary text-red-600 border-red-300 hover:bg-red-50 cursor-pointer">
                                                Azioni
                                            </summary>
                                            <div class="mt-2 p-3 rounded-md border border-gdrcd-border bg-gdrcd-card space-y-3 w-80">

                                                <form action="main.php?page=gestione/forget_requests"
                                                      method="post" class="space-y-2"
                                                      onsubmit="return confirm('Anonimizzazione IRREVERSIBILE del personaggio &quot;<?= htmlspecialchars((string)$row['user_login'], ENT_QUOTES) ?>&quot;. Procedere?');">
                                                    <?= gdrcd_csrf_field() ?>
                                                    <input type="hidden" name="op" value="anonymize">
                                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                                    <label class="gdrcd-label text-xs">Nota interna (opzionale)</label>
                                                    <textarea name="note_admin" rows="2" class="gdrcd-textarea w-full text-xs"
                                                              placeholder="Note di processing..."></textarea>
                                                    <button type="submit" class="gdrcd-btn-primary w-full text-xs">
                                                        Anonimizza account
                                                    </button>
                                                </form>

                                                <form action="main.php?page=gestione/forget_requests"
                                                      method="post" class="space-y-2"
                                                      onsubmit="return confirm('Rifiutare la richiesta #<?= (int)$row['id'] ?>?');">
                                                    <?= gdrcd_csrf_field() ?>
                                                    <input type="hidden" name="op" value="reject">
                                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                                    <label class="gdrcd-label text-xs">Motivazione rifiuto (obbligatoria)</label>
                                                    <textarea name="note_admin" rows="2" class="gdrcd-textarea w-full text-xs"
                                                              required placeholder="Spiega perche' la richiesta viene rifiutata..."></textarea>
                                                    <button type="submit" class="gdrcd-btn-ghost w-full text-xs">
                                                        Rifiuta
                                                    </button>
                                                </form>

                                            </div>
                                        </details>
                                    <?php else: ?>
                                        <span class="text-gdrcd-subtle text-xs">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; gdrcd_query($rs, 'free'); ?>

                        <?php if (!$any): ?>
                            <tr>
                                <td colspan="7" class="text-center text-gdrcd-muted py-6">
                                    Nessuna richiesta in questa categoria.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

</div>
