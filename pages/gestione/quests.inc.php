<?php
/**
 * Gestione quest (GAMEMASTER+).
 *   main.php?page=gestione/quests
 *
 * Permette al Master di:
 *   - creare nuove quest
 *   - modificarne testo / obiettivo / ricompensa
 *   - attivarle/disattivarle (soft toggle via `quest.attiva`)
 *   - assegnarle a uno o piu' PG (UNIQUE per coppia quest/PG)
 *   - vedere la lista degli assegnatari, concludere (completata/fallita)
 *     o riaprire e aggiornare le note
 *
 * Schema:
 *   - quest (definizione)
 *   - clgquestpg (assegnazione + stato)
 * Vedi db_versions/2026051119_GDRCDQuests.php.
 *
 * CSRF: validato centralmente da main.php su tutti i POST.
 */

if (($_SESSION['permessi'] ?? 0) < GAMEMASTER) {
    echo '<div class="gdrcd-alert-error">'
       . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>'
       . '<div>' . gdrcd_filter('out', $MESSAGE['error']['not_allowed'] ?? 'Accesso non consentito.') . '</div>'
       . '</div>';
    return;
}

$me_login = (string)($_SESSION['login'] ?? '');

/* ------------------------------------------------------------------
 * Handler POST.
 * ------------------------------------------------------------------ */
$flash      = null;
$open_form  = null; // 'new' | ['edit', $id] | ['assign', $id] | ['assignees', $id]

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $op = $_POST['op'] ?? '';

    if ($op === 'create') {
        $titolo      = trim((string)($_POST['titolo'] ?? ''));
        $descrizione = (string)($_POST['descrizione'] ?? '');
        $obiettivo   = (string)($_POST['obiettivo'] ?? '');
        $ricompensa  = (string)($_POST['ricompensa'] ?? '');

        if ($titolo === '' || trim($descrizione) === '') {
            $flash = ['kind' => 'warning', 'message' => 'Titolo e descrizione sono obbligatori.'];
            $open_form = 'new';
        } else {
            Db::preparedExecute(
                "INSERT INTO quest (titolo, descrizione, obiettivo, ricompensa, autore) VALUES (?, ?, ?, ?, ?)",
                'sssss',
                array($titolo, $descrizione, $obiettivo, $ricompensa, $me_login)
            );
            $flash = ['kind' => 'success', 'message' => 'Quest creata.'];
        }

    } elseif ($op === 'edit') {
        $id          = (int)($_POST['id_quest'] ?? 0);
        $titolo      = trim((string)($_POST['titolo'] ?? ''));
        $descrizione = (string)($_POST['descrizione'] ?? '');
        $obiettivo   = (string)($_POST['obiettivo'] ?? '');
        $ricompensa  = (string)($_POST['ricompensa'] ?? '');

        if ($id <= 0) {
            $flash = ['kind' => 'warning', 'message' => 'Quest non valida.'];
        } elseif ($titolo === '' || trim($descrizione) === '') {
            $flash = ['kind' => 'warning', 'message' => 'Titolo e descrizione sono obbligatori.'];
            $open_form = ['edit', $id];
        } else {
            Db::preparedExecute(
                "UPDATE quest SET titolo = ?, descrizione = ?, obiettivo = ?, ricompensa = ? WHERE id_quest = ?",
                'ssssi',
                array($titolo, $descrizione, $obiettivo, $ricompensa, $id)
            );
            $flash = ['kind' => 'success', 'message' => 'Quest aggiornata.'];
        }

    } elseif ($op === 'toggle') {
        $id = (int)($_POST['id_quest'] ?? 0);
        if ($id > 0) {
            Db::preparedExecute("UPDATE quest SET attiva = 1 - attiva WHERE id_quest = ?", 'i', array($id));
            $flash = ['kind' => 'success', 'message' => 'Stato quest aggiornato.'];
        }

    } elseif ($op === 'assign') {
        $id = (int)($_POST['id_quest'] ?? 0);
        $pg = trim((string)($_POST['personaggio'] ?? ''));
        $note = (string)($_POST['note'] ?? '');

        if ($id <= 0 || $pg === '') {
            $flash = ['kind' => 'warning', 'message' => 'Quest o personaggio non validi.'];
            if ($id > 0) { $open_form = ['assign', $id]; }
        } else {
            // Verifica esistenza PG.
            $pg_check = Db::preparedFetch(
                "SELECT nome FROM personaggio WHERE nome = ? LIMIT 1",
                's',
                array($pg)
            );
            if (empty($pg_check)) {
                $flash = ['kind' => 'warning', 'message' => 'Personaggio "' . htmlspecialchars($pg) . '" non trovato.'];
                $open_form = ['assign', $id];
            } else {
                $affected = Db::preparedAffected(
                    "INSERT IGNORE INTO clgquestpg (id_quest, personaggio, status, note) VALUES (?, ?, 'attiva', ?)",
                    'iss',
                    array($id, (string)$pg_check['nome'], $note)
                );
                if ($affected > 0) {
                    $flash = ['kind' => 'success', 'message' => 'Quest assegnata a ' . htmlspecialchars((string)$pg_check['nome']) . '.'];
                } else {
                    $flash = ['kind' => 'warning', 'message' => htmlspecialchars((string)$pg_check['nome']) . ' ha gia\' questa quest.'];
                }
            }
        }

    } elseif ($op === 'conclude') {
        $row_id = (int)($_POST['row_id'] ?? 0);
        $id     = (int)($_POST['id_quest'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        $note   = (string)($_POST['note'] ?? '');

        if ($row_id <= 0 || !in_array($status, ['completata', 'fallita', 'attiva'], true)) {
            $flash = ['kind' => 'warning', 'message' => 'Parametri non validi.'];
        } else {
            // $status validato da whitelist sopra.
            $set_conclusa = ($status === 'attiva')
                ? "conclusa_il = NULL"
                : "conclusa_il = NOW()";
            Db::preparedExecute(
                "UPDATE clgquestpg SET status = ?, " . $set_conclusa . ", note = ? WHERE id = ?",
                'ssi',
                array($status, $note, $row_id)
            );
            $flash = ['kind' => 'success', 'message' => 'Stato assegnazione aggiornato.'];
            if ($id > 0) { $open_form = ['assignees', $id]; }
        }

    } elseif ($op === 'unassign') {
        $row_id = (int)($_POST['row_id'] ?? 0);
        $id     = (int)($_POST['id_quest'] ?? 0);
        if ($row_id > 0) {
            Db::preparedExecute("DELETE FROM clgquestpg WHERE id = ?", 'i', array($row_id));
            $flash = ['kind' => 'success', 'message' => 'Assegnazione rimossa.'];
            if ($id > 0) { $open_form = ['assignees', $id]; }
        }
    }
}

/* ------------------------------------------------------------------
 * Tab di filtro.
 * ------------------------------------------------------------------ */
$tab = $_REQUEST['tab'] ?? 'all';
if (!in_array($tab, ['all', 'active', 'inactive'], true)) {
    $tab = 'all';
}

$where = '';
if ($tab === 'active')   { $where = ' WHERE q.attiva = 1'; }
if ($tab === 'inactive') { $where = ' WHERE q.attiva = 0'; }

/* ------------------------------------------------------------------
 * Conteggi tab.
 * ------------------------------------------------------------------ */
$counts_row = gdrcd_query(
    "SELECT "
    . "SUM(attiva = 1) AS active_n, "
    . "SUM(attiva = 0) AS inactive_n, "
    . "COUNT(*)        AS all_n "
    . "FROM quest"
);
$counts = [
    'all'      => (int)($counts_row['all_n']      ?? 0),
    'active'   => (int)($counts_row['active_n']   ?? 0),
    'inactive' => (int)($counts_row['inactive_n'] ?? 0),
];

/* ------------------------------------------------------------------
 * Recupero lista quest + numero di assegnatari per ognuna.
 * ------------------------------------------------------------------ */
$rs = gdrcd_query(
    "SELECT q.id_quest, q.titolo, q.descrizione, q.obiettivo, q.ricompensa, "
    . "q.autore, q.creata_il, q.attiva, "
    . "(SELECT COUNT(*) FROM clgquestpg cqp WHERE cqp.id_quest = q.id_quest) AS n_assegnati, "
    . "(SELECT COUNT(*) FROM clgquestpg cqp WHERE cqp.id_quest = q.id_quest AND cqp.status = 'attiva') AS n_attive "
    . "FROM quest q"
    . $where
    . " ORDER BY q.creata_il DESC LIMIT 500",
    'result'
);

/* ------------------------------------------------------------------
 * Eventuali pannelli da pre-espandere a seguito di un POST.
 * ------------------------------------------------------------------ */
$expand_new      = ($open_form === 'new');
$expand_edit_id  = (is_array($open_form) && $open_form[0] === 'edit')      ? (int)$open_form[1] : 0;
$expand_assign_id= (is_array($open_form) && $open_form[0] === 'assign')    ? (int)$open_form[1] : 0;
$expand_list_id  = (is_array($open_form) && $open_form[0] === 'assignees') ? (int)$open_form[1] : 0;

$tab_meta = [
    'all'      => ['label' => 'Tutte',        'count' => $counts['all']],
    'active'   => ['label' => 'Attive',       'count' => $counts['active']],
    'inactive' => ['label' => 'Disattivate',  'count' => $counts['inactive']],
];

$status_badge = [
    'attiva'     => ['label' => 'Attiva',     'class' => 'gdrcd-badge-accent'],
    'completata' => ['label' => 'Completata', 'class' => 'gdrcd-badge-success'],
    'fallita'    => ['label' => 'Fallita',    'class' => 'gdrcd-badge-neutral'],
];

/**
 * Helper: carica gli assegnatari di una quest, ordinati per stato.
 *
 * @return array<int, array<string,mixed>>
 */
$load_assignees = function (int $id_quest): array {
    return Db::preparedFetchAll(
        "SELECT id, personaggio, status, assegnata_il, conclusa_il, note "
        . "FROM clgquestpg WHERE id_quest = ? "
        . "ORDER BY (status = 'attiva') DESC, assegnata_il DESC",
        'i',
        array($id_quest)
    );
};
?>

<div class="space-y-6">

    <header class="space-y-2">
        <h2 class="gdrcd-h1 flex items-center gap-3">
            <span class="gdrcd-icon-circle">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M7 8h10M5 4h10a2 2 0 012 2v14l-3-2-3 2-3-2-3 2V6a2 2 0 012-2z"/>
                </svg>
            </span>
            Gestione quest
        </h2>
        <p class="gdrcd-muted">
            Crea, modifica e assegna le quest ai personaggi.
            Le quest disattivate restano leggibili sulle schede dei PG che le hanno gia' assegnate
            ma non possono essere assegnate a nuovi PG.
        </p>
    </header>

    <?php if ($flash !== null): ?>
        <?php if ($flash['kind'] === 'success'): ?>
            <div class="gdrcd-alert-success">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                <div><?= $flash['message'] ?></div>
            </div>
        <?php else: ?>
            <div class="gdrcd-alert-warning">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
                <div><?= $flash['message'] ?></div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <nav class="flex flex-wrap gap-2" aria-label="Filtri">
            <?php foreach ($tab_meta as $key => $meta):
                $url = 'main.php?page=gestione/quests&tab=' . urlencode($key);
                $is_active = ($key === $tab);
            ?>
                <a href="<?= htmlspecialchars($url) ?>"
                   class="inline-flex items-center gap-2 px-3 py-1.5 rounded-md text-sm border transition-colors
                          <?= $is_active
                                ? 'border-gdrcd-accent text-gdrcd-accent bg-gdrcd-accent-soft'
                                : 'border-gdrcd-border text-gdrcd-muted hover:text-gdrcd-text hover:border-gdrcd-accent-ring' ?>">
                    <?= htmlspecialchars($meta['label']) ?>
                    <span class="gdrcd-badge-neutral text-[10px]"><?= (int)$meta['count'] ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <a href="#new-quest-form"
           class="gdrcd-btn-primary inline-flex items-center gap-2"
           onclick="var d=document.getElementById('new-quest-form'); if(d){d.open=true;}">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Nuova quest
        </a>
    </div>

    <details id="new-quest-form" class="gdrcd-card" <?= $expand_new ? 'open' : '' ?>>
        <summary class="cursor-pointer px-4 py-3 font-semibold text-gdrcd-text flex items-center gap-2 border-b border-gdrcd-border bg-gdrcd-panel-alt/40">
            <svg class="w-4 h-4 text-gdrcd-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Crea nuova quest
        </summary>
        <div class="p-4 sm:p-6">
            <form action="main.php?page=gestione/quests" method="post" class="space-y-4 max-w-3xl">
                <?= gdrcd_csrf_field() ?>
                <input type="hidden" name="op" value="create">
                <div>
                    <label class="gdrcd-label" for="new_titolo">Titolo</label>
                    <input class="gdrcd-input w-full" type="text" id="new_titolo" name="titolo"
                           maxlength="255" required>
                </div>
                <div>
                    <label class="gdrcd-label" for="new_descrizione">Descrizione</label>
                    <textarea class="gdrcd-textarea w-full" id="new_descrizione" name="descrizione"
                              rows="5" required data-bbcode></textarea>
                    <p class="gdrcd-help"><?= gdrcd_filter('out', $MESSAGE['interface']['help']['bbcode'] ?? 'BBCode supportato.') ?></p>
                </div>
                <div>
                    <label class="gdrcd-label" for="new_obiettivo">Obiettivo (opzionale)</label>
                    <textarea class="gdrcd-textarea w-full" id="new_obiettivo" name="obiettivo" rows="3" data-bbcode></textarea>
                </div>
                <div>
                    <label class="gdrcd-label" for="new_ricompensa">Ricompensa (opzionale)</label>
                    <textarea class="gdrcd-textarea w-full" id="new_ricompensa" name="ricompensa" rows="3" data-bbcode></textarea>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="gdrcd-btn-primary">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        Crea quest
                    </button>
                </div>
            </form>
        </div>
    </details>

    <div class="space-y-4">
        <?php
        $any = false;
        while ($q = gdrcd_query($rs, 'assoc')):
            $any = true;
            $id_quest = (int)$q['id_quest'];
        ?>
            <section class="gdrcd-card">
                <header class="flex flex-wrap items-start gap-3 px-4 py-3 border-b border-gdrcd-border bg-gdrcd-panel-alt/30">
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs text-gdrcd-muted tabular-nums">#<?= $id_quest ?></span>
                            <h3 class="text-base font-semibold text-gdrcd-text truncate">
                                <?= gdrcd_filter('out', (string)$q['titolo']) ?>
                            </h3>
                            <?php if ((int)$q['attiva'] === 1): ?>
                                <span class="gdrcd-badge-success text-[10px]">Attiva</span>
                            <?php else: ?>
                                <span class="gdrcd-badge-neutral text-[10px]">Disattivata</span>
                            <?php endif; ?>
                        </div>
                        <div class="mt-1 text-xs text-gdrcd-muted flex flex-wrap items-center gap-x-3 gap-y-1">
                            <span>Autore: <span class="text-gdrcd-text"><?= gdrcd_filter('out', (string)$q['autore']) ?></span></span>
                            <span class="tabular-nums">
                                <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$q['creata_il']))) ?>
                            </span>
                            <span>
                                <span class="tabular-nums text-gdrcd-text"><?= (int)$q['n_assegnati'] ?></span> PG assegnati
                                <?php if ((int)$q['n_attive'] > 0): ?>
                                    (<span class="tabular-nums"><?= (int)$q['n_attive'] ?></span> attive)
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    <form action="main.php?page=gestione/quests" method="post" class="shrink-0"
                          onsubmit="return confirm('<?= (int)$q['attiva'] === 1 ? 'Disattivare' : 'Attivare' ?> la quest #<?= $id_quest ?>?');">
                        <?= gdrcd_csrf_field() ?>
                        <input type="hidden" name="op" value="toggle">
                        <input type="hidden" name="id_quest" value="<?= $id_quest ?>">
                        <button type="submit" class="gdrcd-btn-ghost text-xs">
                            <?= (int)$q['attiva'] === 1 ? 'Disattiva' : 'Attiva' ?>
                        </button>
                    </form>
                </header>

                <div class="p-4 space-y-3">
                    <details <?= $expand_edit_id === $id_quest ? 'open' : '' ?>>
                        <summary class="cursor-pointer text-sm font-semibold text-gdrcd-text flex items-center gap-2 select-none">
                            <svg class="w-4 h-4 text-gdrcd-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            Modifica quest
                        </summary>
                        <div class="mt-3 p-4 rounded-md border border-gdrcd-border bg-gdrcd-panel-alt/30">
                            <form action="main.php?page=gestione/quests" method="post" class="space-y-3 max-w-3xl">
                                <?= gdrcd_csrf_field() ?>
                                <input type="hidden" name="op" value="edit">
                                <input type="hidden" name="id_quest" value="<?= $id_quest ?>">
                                <div>
                                    <label class="gdrcd-label" for="ed_titolo_<?= $id_quest ?>">Titolo</label>
                                    <input class="gdrcd-input w-full" type="text" id="ed_titolo_<?= $id_quest ?>"
                                           name="titolo" maxlength="255" required
                                           value="<?= gdrcd_filter('out', (string)$q['titolo']) ?>">
                                </div>
                                <div>
                                    <label class="gdrcd-label" for="ed_descr_<?= $id_quest ?>">Descrizione</label>
                                    <textarea class="gdrcd-textarea w-full" id="ed_descr_<?= $id_quest ?>"
                                              name="descrizione" rows="5" required data-bbcode><?= gdrcd_filter('out', (string)$q['descrizione']) ?></textarea>
                                </div>
                                <div>
                                    <label class="gdrcd-label" for="ed_obiet_<?= $id_quest ?>">Obiettivo</label>
                                    <textarea class="gdrcd-textarea w-full" id="ed_obiet_<?= $id_quest ?>"
                                              name="obiettivo" rows="3" data-bbcode><?= gdrcd_filter('out', (string)($q['obiettivo'] ?? '')) ?></textarea>
                                </div>
                                <div>
                                    <label class="gdrcd-label" for="ed_ric_<?= $id_quest ?>">Ricompensa</label>
                                    <textarea class="gdrcd-textarea w-full" id="ed_ric_<?= $id_quest ?>"
                                              name="ricompensa" rows="3" data-bbcode><?= gdrcd_filter('out', (string)($q['ricompensa'] ?? '')) ?></textarea>
                                </div>
                                <div class="flex justify-end">
                                    <button type="submit" class="gdrcd-btn-primary">Salva modifiche</button>
                                </div>
                            </form>
                        </div>
                    </details>

                    <details <?= $expand_assign_id === $id_quest ? 'open' : '' ?>>
                        <summary class="cursor-pointer text-sm font-semibold text-gdrcd-text flex items-center gap-2 select-none">
                            <svg class="w-4 h-4 text-gdrcd-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            Assegna a un PG
                        </summary>
                        <div class="mt-3 p-4 rounded-md border border-gdrcd-border bg-gdrcd-panel-alt/30">
                            <form action="main.php?page=gestione/quests" method="post" class="space-y-3 max-w-xl">
                                <?= gdrcd_csrf_field() ?>
                                <input type="hidden" name="op" value="assign">
                                <input type="hidden" name="id_quest" value="<?= $id_quest ?>">
                                <div>
                                    <label class="gdrcd-label" for="as_pg_<?= $id_quest ?>">Nome PG</label>
                                    <input class="gdrcd-input w-full" type="text" id="as_pg_<?= $id_quest ?>"
                                           name="personaggio" list="quest_pg_list_<?= $id_quest ?>"
                                           autocomplete="off" required>
                                    <datalist id="quest_pg_list_<?= $id_quest ?>">
                                        <?php
                                        $pg_rs = gdrcd_query(
                                            "SELECT nome FROM personaggio WHERE permessi >= 0 "
                                            . "ORDER BY nome ASC LIMIT 1000",
                                            'result'
                                        );
                                        while ($p = gdrcd_query($pg_rs, 'assoc')):
                                        ?>
                                            <option value="<?= gdrcd_filter('out', (string)$p['nome']) ?>"></option>
                                        <?php endwhile; gdrcd_query($pg_rs, 'free'); ?>
                                    </datalist>
                                </div>
                                <div>
                                    <label class="gdrcd-label" for="as_note_<?= $id_quest ?>">Nota iniziale (opzionale)</label>
                                    <textarea class="gdrcd-textarea w-full" id="as_note_<?= $id_quest ?>"
                                              name="note" rows="2"></textarea>
                                </div>
                                <div class="flex justify-end">
                                    <button type="submit" class="gdrcd-btn-primary">Assegna</button>
                                </div>
                            </form>
                        </div>
                    </details>

                    <details <?= $expand_list_id === $id_quest ? 'open' : '' ?>>
                        <summary class="cursor-pointer text-sm font-semibold text-gdrcd-text flex items-center gap-2 select-none">
                            <svg class="w-4 h-4 text-gdrcd-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            Lista assegnatari (<?= (int)$q['n_assegnati'] ?>)
                        </summary>
                        <div class="mt-3 p-4 rounded-md border border-gdrcd-border bg-gdrcd-panel-alt/30">
                            <?php
                            $assignees = $load_assignees($id_quest);
                            if (empty($assignees)):
                            ?>
                                <p class="text-xs text-gdrcd-muted italic">Nessun PG assegnato.</p>
                            <?php else: ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <?php foreach ($assignees as $a):
                                        $row_id = (int)$a['id'];
                                        $st     = (string)$a['status'];
                                        $badge  = $status_badge[$st] ?? ['label' => $st, 'class' => 'gdrcd-badge-neutral'];
                                    ?>
                                        <div class="border border-gdrcd-border rounded p-3 space-y-2 bg-gdrcd-panel">
                                            <div class="flex items-center justify-between gap-2">
                                                <div class="text-sm font-semibold text-gdrcd-text truncate">
                                                    <?= gdrcd_filter('out', (string)$a['personaggio']) ?>
                                                </div>
                                                <span class="<?= htmlspecialchars($badge['class']) ?> text-[10px] shrink-0">
                                                    <?= htmlspecialchars($badge['label']) ?>
                                                </span>
                                            </div>
                                            <div class="text-[11px] text-gdrcd-muted">
                                                Assegnata
                                                <span class="tabular-nums">
                                                    <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$a['assegnata_il']))) ?>
                                                </span>
                                                <?php if (!empty($a['conclusa_il'])): ?>
                                                    &middot; Conclusa
                                                    <span class="tabular-nums">
                                                        <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$a['conclusa_il']))) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <form action="main.php?page=gestione/quests" method="post" class="space-y-2">
                                                <?= gdrcd_csrf_field() ?>
                                                <input type="hidden" name="op" value="conclude">
                                                <input type="hidden" name="row_id" value="<?= $row_id ?>">
                                                <input type="hidden" name="id_quest" value="<?= $id_quest ?>">
                                                <label class="gdrcd-label text-[11px]">Stato</label>
                                                <select class="gdrcd-select w-full text-xs" name="status">
                                                    <option value="attiva"     <?= $st === 'attiva'     ? 'selected' : '' ?>>Attiva</option>
                                                    <option value="completata" <?= $st === 'completata' ? 'selected' : '' ?>>Completata</option>
                                                    <option value="fallita"    <?= $st === 'fallita'    ? 'selected' : '' ?>>Fallita</option>
                                                </select>
                                                <label class="gdrcd-label text-[11px]">Note</label>
                                                <textarea class="gdrcd-textarea w-full text-xs" name="note" rows="2"><?= gdrcd_filter('out', (string)($a['note'] ?? '')) ?></textarea>
                                                <button type="submit" class="gdrcd-btn-primary w-full text-xs">Salva</button>
                                            </form>
                                            <form action="main.php?page=gestione/quests" method="post"
                                                  onsubmit="return confirm('Rimuovere l\'assegnazione a <?= htmlspecialchars((string)$a['personaggio'], ENT_QUOTES) ?>?');">
                                                <?= gdrcd_csrf_field() ?>
                                                <input type="hidden" name="op" value="unassign">
                                                <input type="hidden" name="row_id" value="<?= $row_id ?>">
                                                <input type="hidden" name="id_quest" value="<?= $id_quest ?>">
                                                <button type="submit" class="gdrcd-btn-ghost w-full text-[11px] text-red-600">
                                                    Rimuovi assegnazione
                                                </button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </details>
                </div>
            </section>
        <?php endwhile; gdrcd_query($rs, 'free'); ?>

        <?php if (!$any): ?>
            <div class="gdrcd-card">
                <div class="p-6 text-center text-gdrcd-muted text-sm">
                    Nessuna quest in questa categoria.
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
