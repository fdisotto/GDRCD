<?php
/**
 * Log chat (main.php?page=log_chat)
 * Filtra messaggi chat per PG o per stanza+intervallo data; risultati paginati.
 */

/* ---------- Permessi ---------- */
if (($_SESSION['permessi'] < MODERATOR) || ($PARAMETERS['mode']['spymessages'] !== 'ON')) {
    echo gdrcd_alert_error(gdrcd_filter('out', $MESSAGE['error']['not_allowed']), ['raw' => true]);
    return;
}

$op            = $_REQUEST['op'] ?? null;
$offset        = (int)($_REQUEST['offset'] ?? 0);
$per_page      = (int)$PARAMETERS['settings']['records_per_page'];
$pagebegin     = $offset * $per_page;

$page_label_msg = $MESSAGE['interface']['administration']['log']['chat'];

/* ---------- Helper: pager ---------- */
$render_pager = function (int $total, int $current_offset, array $extra_params) use ($per_page, $MESSAGE) {
    if ($total <= $per_page) return '';
    $pages = (int)floor($total / $per_page);
    $html  = '<nav class="gdrcd-pager" aria-label="Paginazione">';
    $html .= '<span class="gdrcd-pager-label !border-0 !bg-transparent">'
          .  gdrcd_filter('out', $MESSAGE['interface']['pager']['pages_name']) . '</span>';
    for ($i = 0; $i <= $pages; $i++) {
        if ($i === $current_offset) {
            $html .= '<span class="is-current" aria-current="page">' . ($i + 1) . '</span>';
        } else {
            $params = array_merge($extra_params, ['offset' => $i]);
            $url    = 'main.php?' . http_build_query($params);
            $html  .= '<a href="' . htmlspecialchars($url) . '">' . ($i + 1) . '</a>';
        }
    }
    $html .= '</nav>';
    return $html;
};

/* ---------- Helper: render tabella risultati ---------- */
$render_table = function ($rows_iter, callable $sender_for_row) use ($page_label_msg) {
    ob_start(); ?>
    <div class="gdrcd-table-wrap">
        <table class="gdrcd-table">
            <thead>
                <tr>
                    <th><?= gdrcd_filter('out', $page_label_msg['sender']) ?></th>
                    <th class="whitespace-nowrap"><?= gdrcd_filter('out', $page_label_msg['date']) ?></th>
                    <th><?= gdrcd_filter('out', $page_label_msg['text']) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = gdrcd_query($rows_iter, 'fetch')): ?>
                    <tr>
                        <td class="font-medium text-gdrcd-text whitespace-nowrap">
                            <?= gdrcd_filter('out', $sender_for_row($row)) ?>
                        </td>
                        <td class="text-gdrcd-muted whitespace-nowrap">
                            <?= gdrcd_format_datetime($row['ora']) ?>
                        </td>
                        <td>
                            <?php if (!empty($row['destinatario'])): ?>
                                <span class="gdrcd-badge-accent text-[10px] mr-1">→ <?= gdrcd_filter('out', $row['destinatario']) ?></span>
                            <?php endif; ?>
                            <?= gdrcd_filter('out', $row['testo']) ?>
                        </td>
                    </tr>
                <?php endwhile;
                gdrcd_query($rows_iter, 'free');
                ?>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
};
?>

<div class="space-y-6">

    <header class="space-y-2">
        <h2 class="gdrcd-h1"><?= gdrcd_filter('out', $page_label_msg['page_name']) ?></h2>
        <p class="gdrcd-muted">Filtra i messaggi di chat per personaggio oppure per stanza e intervallo di tempo.</p>
    </header>

    <?php if ($op === null): ?>
        <section class="gdrcd-card">
            <div class="gdrcd-card-body grid grid-cols-1 lg:grid-cols-3 gap-6 lg:divide-x lg:divide-gdrcd-border">

                <form action="main.php?page=log_chat" method="post" class="space-y-3 lg:pr-6">
                    <?= gdrcd_csrf_field() ?>
                    <h3 class="gdrcd-h3"><?= gdrcd_filter('out', $page_label_msg['log_by_user']) ?></h3>
                    <div>
                        <label class="gdrcd-label" for="lc_pg">Personaggio</label>
                        <select class="gdrcd-select" id="lc_pg" name="pg">
                            <?php
                            $result = gdrcd_query("SELECT nome FROM personaggio ORDER BY nome", 'result');
                            while ($row = gdrcd_query($result, 'fetch')):
                                ?>
                                <option value="<?= gdrcd_filter('out', $row['nome']) ?>"><?= gdrcd_filter('out', $row['nome']) ?></option>
                            <?php endwhile;
                            gdrcd_query($result, 'free');
                            ?>
                        </select>
                    </div>
                    <input type="hidden" name="op" value="view_user"/>
                    <button type="submit" class="gdrcd-btn-primary w-full sm:w-auto">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <?= gdrcd_filter('out', $MESSAGE['interface']['forms']['submit']) ?>
                    </button>
                </form>

                <form action="main.php?page=log_chat" method="post" class="space-y-3 lg:px-6">
                    <?= gdrcd_csrf_field() ?>
                    <h3 class="gdrcd-h3"><?= gdrcd_filter('out', $page_label_msg['log_by_room']) ?></h3>
                    <div>
                        <label class="gdrcd-label" for="lc_luogo">Stanza</label>
                        <select class="gdrcd-select" id="lc_luogo" name="luogo">
                            <?php
                            $result = gdrcd_query("SELECT nome, id FROM mappa WHERE chat=1 ORDER BY nome", 'result');
                            while ($row = gdrcd_query($result, 'fetch')):
                                ?>
                                <option value="<?= gdrcd_filter('out', $row['id']) ?>"><?= gdrcd_filter('out', $row['nome']) ?></option>
                            <?php endwhile;
                            gdrcd_query($result, 'free');
                            ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="gdrcd-label" for="lc_data_a"><?= gdrcd_filter('out', $page_label_msg['begin']) ?></label>
                            <input class="gdrcd-input" type="datetime-local" id="lc_data_a" name="data_a"
                                   value="<?= date('Y-m-d') ?>T00:00"/>
                        </div>
                        <div>
                            <label class="gdrcd-label" for="lc_data_b"><?= gdrcd_filter('out', $page_label_msg['end']) ?></label>
                            <input class="gdrcd-input" type="datetime-local" id="lc_data_b" name="data_b"
                                   value="<?= date('Y-m-d') ?>T23:59"/>
                        </div>
                    </div>
                    <input type="hidden" name="op" value="view_date"/>
                    <button type="submit" class="gdrcd-btn-primary w-full sm:w-auto">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <?= gdrcd_filter('out', $MESSAGE['interface']['forms']['submit']) ?>
                    </button>
                </form>

                <form action="main.php?page=log_chat" method="get" class="space-y-3 lg:pl-6">
                    <input type="hidden" name="page" value="log_chat"/>
                    <input type="hidden" name="op"   value="search"/>
                    <h3 class="gdrcd-h3">Ricerca testuale</h3>
                    <div>
                        <label class="gdrcd-label" for="lc_q">Parole chiave</label>
                        <input class="gdrcd-input" type="text" id="lc_q" name="q"
                               placeholder="es. taverna pioggia" minlength="3" required/>
                        <p class="gdrcd-help">Match FULLTEXT su <code>chat.testo</code>. Min 3-4 caratteri per token.</p>
                    </div>
                    <div>
                        <label class="gdrcd-label" for="lc_q_luogo">Stanza (opzionale)</label>
                        <select class="gdrcd-select" id="lc_q_luogo" name="luogo">
                            <option value="">— Tutte —</option>
                            <?php
                            $result = gdrcd_query("SELECT nome, id FROM mappa WHERE chat=1 ORDER BY nome", 'result');
                            while ($row = gdrcd_query($result, 'fetch')):
                                ?>
                                <option value="<?= (int)$row['id'] ?>"><?= gdrcd_filter('out', $row['nome']) ?></option>
                            <?php endwhile;
                            gdrcd_query($result, 'free');
                            ?>
                        </select>
                    </div>
                    <div>
                        <label class="gdrcd-label" for="lc_q_pg">Mittente (opzionale)</label>
                        <input class="gdrcd-input" type="text" id="lc_q_pg" name="mittente"
                               placeholder="Nome PG" maxlength="50"/>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="gdrcd-label" for="lc_q_data_a">Da</label>
                            <input class="gdrcd-input" type="date" id="lc_q_data_a" name="data_a"/>
                        </div>
                        <div>
                            <label class="gdrcd-label" for="lc_q_data_b">A</label>
                            <input class="gdrcd-input" type="date" id="lc_q_data_b" name="data_b"/>
                        </div>
                    </div>
                    <button type="submit" class="gdrcd-btn-primary w-full sm:w-auto">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        Cerca
                    </button>
                </form>
            </div>
        </section>

    <?php elseif ($op === 'view_user'):
        $pg = $_REQUEST['pg'] ?? '';
        $count_row     = gdrcd_query("SELECT COUNT(*) AS c FROM chat WHERE mittente = '" . gdrcd_filter('get', $pg) . "'");
        $totaleresults = (int)$count_row['c'];

        $result = gdrcd_query(
            "SELECT chat.destinatario, chat.tipo, chat.ora, chat.testo, mappa.nome
             FROM chat JOIN mappa ON chat.stanza = mappa.id
             WHERE chat.mittente = '" . gdrcd_filter('in', $pg) . "'
             ORDER BY ora DESC
             LIMIT " . $pagebegin . ", " . $per_page,
            'result'
        );
        $numresults = (int)gdrcd_query($result, 'num_rows');
        ?>
        <section class="space-y-3">
            <div class="flex flex-wrap items-baseline gap-2">
                <h3 class="gdrcd-h3"><?= gdrcd_filter('out', $page_label_msg['log_by_user']) ?></h3>
                <span class="gdrcd-badge-accent"><?= htmlspecialchars($pg) ?></span>
                <span class="gdrcd-muted text-xs ml-auto"><?= $totaleresults ?> risultati</span>
            </div>

            <?php if ($numresults > 0): ?>
                <?= $render_table($result, fn($row) => $row['nome']) ?>
                <?= $render_pager($totaleresults, $offset, ['page' => 'log_chat', 'op' => 'view_user', 'pg' => $pg]) ?>
            <?php else: ?>
                <?= gdrcd_alert_info('Nessun risultato per questo personaggio.') ?>
            <?php endif; ?>

            <div>
                <a href="main.php?page=log_chat" class="gdrcd-btn-ghost">
                    <?= gdrcd_icon('arrow-left', 'w-4 h-4') ?>
                    <?= gdrcd_filter('out', $MESSAGE['interface']['administration']['log']['messages']['link']['back']) ?>
                </a>
            </div>
        </section>

    <?php elseif ($op === 'view_date'):
        $luogo  = $_REQUEST['luogo'] ?? '';
        $data_a = gdrcd_format_datetime_standard($_REQUEST['data_a'] ?? '');
        $data_b = gdrcd_format_datetime_standard($_REQUEST['data_b'] ?? '');

        $count_row     = gdrcd_query("SELECT COUNT(*) AS c FROM chat WHERE stanza = '" . gdrcd_filter('get', $luogo) . "'");
        $totaleresults = (int)$count_row['c'];

        $query = "SELECT chat.mittente, chat.destinatario, chat.tipo, chat.ora, chat.testo
                  FROM chat
                  WHERE chat.stanza = '" . gdrcd_filter('get', $luogo) . "'
                    AND ora >= '" . $data_a . "'
                    AND ora <= '" . $data_b . "'
                  ORDER BY ora DESC
                  LIMIT " . $pagebegin . ", " . $per_page;
        $result     = gdrcd_query($query, 'result');
        $numresults = (int)gdrcd_query($result, 'num_rows');

        $room_name_row = gdrcd_query("SELECT nome FROM mappa WHERE id = '" . gdrcd_filter('get', $luogo) . "' LIMIT 1");
        $room_name     = $room_name_row['nome'] ?? '?';
        ?>
        <section class="space-y-3">
            <div class="flex flex-wrap items-baseline gap-2">
                <h3 class="gdrcd-h3"><?= gdrcd_filter('out', $page_label_msg['log_by_room']) ?></h3>
                <span class="gdrcd-badge-accent"><?= htmlspecialchars($room_name) ?></span>
                <span class="gdrcd-muted text-xs">
                    <?= htmlspecialchars($data_a) ?> → <?= htmlspecialchars($data_b) ?>
                </span>
                <span class="gdrcd-muted text-xs ml-auto"><?= $totaleresults ?> risultati</span>
            </div>

            <?php if ($numresults > 0): ?>
                <?= $render_table($result, fn($row) => $row['mittente']) ?>
                <?= $render_pager($totaleresults, $offset, [
                        'page' => 'log_chat', 'op' => 'view_date',
                        'luogo' => $luogo, 'data_a' => $data_a, 'data_b' => $data_b,
                    ]) ?>
            <?php else: ?>
                <?= gdrcd_alert_info('Nessun messaggio nella stanza in questo intervallo.') ?>
            <?php endif; ?>

            <div>
                <a href="main.php?page=log_chat" class="gdrcd-btn-ghost">
                    <?= gdrcd_icon('arrow-left', 'w-4 h-4') ?>
                    <?= gdrcd_filter('out', $MESSAGE['interface']['administration']['log']['messages']['link']['back']) ?>
                </a>
            </div>
        </section>

    <?php elseif ($op === 'search'):
        $q          = trim((string)($_REQUEST['q'] ?? ''));
        $luogo_q    = isset($_REQUEST['luogo']) && $_REQUEST['luogo'] !== '' ? (int)$_REQUEST['luogo'] : 0;
        $mittente_q = trim((string)($_REQUEST['mittente'] ?? ''));
        $data_a_q   = trim((string)($_REQUEST['data_a'] ?? ''));
        $data_b_q   = trim((string)($_REQUEST['data_b'] ?? ''));

        // Validazione min 3 chars (FULLTEXT richiede >= ft_min_word_len).
        if (mb_strlen($q) < 3) {
            echo gdrcd_alert_warning('Inserisci almeno 3 caratteri.');
        } else {
            // Composizione WHERE con prepared params.
            $where  = ["MATCH(chat.testo) AGAINST (? IN NATURAL LANGUAGE MODE)"];
            $types  = 's';
            $params = [$q];

            if ($luogo_q > 0) {
                $where[] = 'chat.stanza = ?';
                $types  .= 'i';
                $params[] = $luogo_q;
            }
            if ($mittente_q !== '') {
                $where[] = 'chat.mittente = ?';
                $types  .= 's';
                $params[] = $mittente_q;
            }
            if ($data_a_q !== '') {
                $where[] = 'chat.ora >= ?';
                $types  .= 's';
                $params[] = $data_a_q . ' 00:00:00';
            }
            if ($data_b_q !== '') {
                $where[] = 'chat.ora <= ?';
                $types  .= 's';
                $params[] = $data_b_q . ' 23:59:59';
            }

            $whereSql = ' WHERE ' . implode(' AND ', $where);

            $countRow = Db::preparedFetch(
                "SELECT COUNT(*) AS c FROM chat" . $whereSql,
                $types,
                $params
            );
            $totaleresults = (int)($countRow['c'] ?? 0);

            // Paginazione: appendo offset/limit ai params (inline ints, safe).
            $limit  = (int)$per_page;
            $start  = (int)$pagebegin;

            $rows = Db::preparedFetchAll(
                "SELECT chat.id, chat.mittente, chat.destinatario, chat.tipo,
                        chat.ora, chat.testo, mappa.nome AS stanza_nome
                 FROM chat LEFT JOIN mappa ON chat.stanza = mappa.id"
                 . $whereSql .
                 " ORDER BY chat.ora DESC
                 LIMIT " . $start . ", " . $limit,
                $types,
                $params
            );
            $numresults = count($rows);
        ?>
            <section class="space-y-3">
                <div class="flex flex-wrap items-baseline gap-2">
                    <h3 class="gdrcd-h3">Ricerca testuale</h3>
                    <span class="gdrcd-badge-accent"><?= htmlspecialchars($q) ?></span>
                    <?php if ($luogo_q > 0): ?>
                        <span class="gdrcd-badge-neutral text-[10px]">stanza #<?= (int)$luogo_q ?></span>
                    <?php endif; ?>
                    <?php if ($mittente_q !== ''): ?>
                        <span class="gdrcd-badge-neutral text-[10px]">@<?= htmlspecialchars($mittente_q) ?></span>
                    <?php endif; ?>
                    <span class="gdrcd-muted text-xs ml-auto"><?= $totaleresults ?> risultati</span>
                </div>

                <?php if ($numresults > 0): ?>
                    <div class="gdrcd-table-wrap">
                        <table class="gdrcd-table">
                            <thead>
                                <tr>
                                    <th>Mittente</th>
                                    <th>Stanza</th>
                                    <th class="whitespace-nowrap">Data</th>
                                    <th>Testo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <td class="font-medium text-gdrcd-text whitespace-nowrap">
                                            <?= gdrcd_filter('out', (string)$row['mittente']) ?>
                                        </td>
                                        <td class="text-xs text-gdrcd-muted whitespace-nowrap">
                                            <?= gdrcd_filter('out', (string)($row['stanza_nome'] ?? '?')) ?>
                                        </td>
                                        <td class="text-gdrcd-muted whitespace-nowrap">
                                            <?= gdrcd_format_datetime($row['ora']) ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['destinatario'])): ?>
                                                <span class="gdrcd-badge-accent text-[10px] mr-1">→ <?= gdrcd_filter('out', $row['destinatario']) ?></span>
                                            <?php endif; ?>
                                            <?= gdrcd_filter('out', (string)$row['testo']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?= $render_pager($totaleresults, $offset, [
                            'page' => 'log_chat',
                            'op'   => 'search',
                            'q'    => $q,
                            'luogo'    => $luogo_q > 0 ? $luogo_q : '',
                            'mittente' => $mittente_q,
                            'data_a'   => $data_a_q,
                            'data_b'   => $data_b_q,
                        ]) ?>
                <?php else: ?>
                    <?= gdrcd_alert_info('Nessun risultato.') ?>
                <?php endif; ?>
            </section>
        <?php } ?>

        <div>
            <a href="main.php?page=log_chat" class="gdrcd-btn-ghost">
                <?= gdrcd_icon('arrow-left', 'w-4 h-4') ?>
                <?= gdrcd_filter('out', $MESSAGE['interface']['administration']['log']['messages']['link']['back']) ?>
            </a>
        </div>
    <?php endif; ?>

</div>
