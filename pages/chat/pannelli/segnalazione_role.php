<?php
/**
 * Pannello registrazione giocate (popup.php?page=chat_pannelli_index&pannello=segnalazione_role).
 * Aperto in modale dalla chat. Gestisce avvio/chiusura/cancellazione registrazione + invio segnalazione.
 */

if (!isset($_SESSION['login'])) {
    echo '<div class="gdrcd-alert-error">'
       . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>'
       . '<div>' . gdrcd_filter('out', $MESSAGE['error']['not_allowed']) . '</div>'
       . '</div>';
    return;
}

$panel_url = 'popup.php?page=chat_pannelli_index&pannello=segnalazione_role';
$op = $_POST['op'] ?? null;

$render_back = function () use ($panel_url) {
    return '<div class="pt-3"><a href="' . htmlspecialchars($panel_url) . '" class="gdrcd-btn-ghost">'
         . '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>'
         . 'Torna indietro</a></div>';
};
$render_alert_info = function (string $msg) {
    return '<div class="gdrcd-alert-info">'
         . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
         . '<div>' . $msg . '</div></div>';
};
?>

<header class="space-y-1">
    <h2 class="gdrcd-h2">Registrazione giocate</h2>
</header>

<?php
$log = gdrcd_query(
    "SELECT * FROM segnalazione_role
     WHERE mittente = '" . gdrcd_filter('in', $_SESSION['login']) . "' AND conclusa = 0",
    'result'
);
$row     = gdrcd_query($log, 'fetch');
$num_log = (int)gdrcd_query($log, 'num_rows');

if ($op === 'leave') {
    gdrcd_query("UPDATE segnalazione_role SET data_fine = NOW(), conclusa = 2 WHERE id = " . gdrcd_filter('num', $row['id']) . " LIMIT 1");
    echo $render_alert_info('La registrazione aperta è stata cancellata.');
    echo $render_back();
    return;
}

if ($op === 'start_segn') {
    gdrcd_query(
        "INSERT INTO segnalazione_role (data_inizio, mittente, stanza, conclusa) VALUES ("
        . "NOW(), '" . gdrcd_filter('in', $_SESSION['login']) . "',"
        . gdrcd_filter('num', $_SESSION['luogo']) . ", 0)"
    );
    echo $render_alert_info('La registrazione è stata aperta.');
    echo $render_back();
    return;
}

if ($op === 'start_ret') {
    $mydate = date('Y-m-d H:i:s');
    $date   = gdrcd_filter('num', $_POST['year']) . '-'
            . sprintf('%02s', gdrcd_filter('num', $_POST['month'])) . '-'
            . sprintf('%02s', gdrcd_filter('num', $_POST['day'])) . ' '
            . sprintf('%02s', gdrcd_filter('num', $_POST['hour'])) . ':'
            . sprintf('%02s', gdrcd_filter('num', $_POST['minut'])) . ':00';
    $start_time = date('Y-m-d H:i:s', strtotime('-6 hours', strtotime($mydate)));

    $query = gdrcd_query(
        "SELECT chat.id FROM chat
         WHERE stanza = " . gdrcd_filter('num', $_SESSION['luogo']) . "
           AND ora >= '" . $date . "' AND ora <= NOW()
           AND mittente = '" . gdrcd_filter('in', $_SESSION['login']) . "'
           AND (tipo = 'A' OR tipo = 'P' OR tipo = 'M' OR tipo = 'N')",
        'result'
    );
    $num_az = (int)gdrcd_query($query, 'num_rows');

    $time_start = gdrcd_query(
        "SELECT chat.ora FROM chat
         WHERE stanza = " . gdrcd_filter('num', $_SESSION['luogo']) . "
           AND ora >= '" . $date . "' AND ora <= NOW()
           AND mittente = '" . gdrcd_filter('in', $_SESSION['login']) . "'
           AND (tipo = 'A' OR tipo = 'P' OR tipo = 'M' OR tipo = 'N')
         ORDER BY ora LIMIT 1"
    );

    if ($date < $start_time) {
        $message = 'Non è possibile selezionare un orario più lontano di sei ore.';
    } elseif ($num_az === 0) {
        $message = 'Non hai inviato alcuna azione a partire dall\'orario segnalato.';
    } else {
        gdrcd_query(
            "INSERT INTO segnalazione_role (data_inizio, mittente, stanza, conclusa) VALUES ("
            . "'" . $time_start['ora'] . "',"
            . "'" . gdrcd_filter('in', $_SESSION['login']) . "',"
            . gdrcd_filter('num', $_SESSION['luogo']) . ", 0)"
        );
        $message = 'La registrazione è stata aperta.';
    }
    echo $render_alert_info($message);
    echo $render_back();
    return;
}

if ($op === 'send_segn') {
    $listapart = join(',', $_POST['parte'] ?? []);
    $total     = count($_POST['parte'] ?? []);
    $singolo   = substr_count($listapart, $_SESSION['login']);

    $query = gdrcd_query(
        "SELECT chat.id FROM chat
         WHERE stanza = " . gdrcd_filter('num', $_SESSION['luogo']) . "
           AND ora >= '" . gdrcd_filter('in', $row['data_inizio']) . "' AND ora <= NOW()
           AND mittente = '" . gdrcd_filter('in', $_SESSION['login']) . "'
           AND (tipo = 'A' OR tipo = 'P' OR tipo = 'M' OR tipo = 'N')",
        'result'
    );
    $num_az = (int)gdrcd_query($query, 'num_rows');

    $time_end = gdrcd_query(
        "SELECT chat.ora FROM chat
         WHERE stanza = " . gdrcd_filter('num', $_SESSION['luogo']) . "
           AND ora >= '" . gdrcd_filter('in', $row['data_inizio']) . "' AND ora <= NOW()
           AND mittente = '" . gdrcd_filter('in', $_SESSION['login']) . "'
           AND (tipo = 'A' OR tipo = 'P' OR tipo = 'M' OR tipo = 'N')
         ORDER BY ora DESC LIMIT 1"
    );
    $end_time = date('Y-m-d H:i:s', strtotime('+1 hours', strtotime($time_end['ora'] ?? 'now')));
    $mydate   = date('Y-m-d H:i:s');

    if (empty($_POST['parte'])) {
        $message = "Non c'è nessuna giocata in corso.";
    } elseif ($singolo === 0) {
        $message = 'Non puoi segnalare questa giocata.';
    } elseif ($total === 1) {
        $message = 'Non puoi segnalare una giocata con un solo partecipante.';
    } elseif ($num_az < REG_MIN_AZIONI) {
        $message = 'Non hai inviato azioni sufficienti ad una registrazione.';
    } elseif ($mydate > $end_time) {
        gdrcd_query(
            "UPDATE segnalazione_role SET
                data_fine = '" . gdrcd_filter('in', $time_end['ora']) . "',
                conclusa = 1,
                partecipanti = '" . gdrcd_filter('in', $listapart) . "',
                tags = '" . gdrcd_filter('in', $_POST['ab'] ?? '') . "',
                quest = '" . gdrcd_filter('in', $_POST['quest'] ?? '') . "'
             WHERE id = " . gdrcd_filter('num', $row['id'])
        );
        $message = 'La registrazione è stata salvata sulla base della tua ultima azione in chat.';
    } else {
        gdrcd_query(
            "UPDATE segnalazione_role SET
                data_fine = NOW(),
                conclusa = 1,
                partecipanti = '" . gdrcd_filter('in', $listapart) . "',
                tags = '" . gdrcd_filter('in', $_POST['ab'] ?? '') . "',
                quest = '" . gdrcd_filter('in', $_POST['quest'] ?? '') . "'
             WHERE id = " . gdrcd_filter('num', $row['id'])
        );
        $message = 'Registrazione inviata con successo.';
    }
    echo $render_alert_info($message);
    echo $render_back();
    return;
}

if ($num_log > 0 && $row['stanza'] !== $_SESSION['luogo']):
    ?>
    <div class="gdrcd-alert-warning">
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
        <div>Stai ancora giocando altrove. Cancella la registrazione aperta per avviarne una nuova qui.</div>
    </div>
    <form action="<?= htmlspecialchars($panel_url) ?>" method="post" class="pt-3">
        <?= gdrcd_csrf_field() ?>
        <input type="hidden" name="op" value="leave"/>
        <button type="submit" class="gdrcd-btn-danger w-full"
                onclick="return confirm('Cancellando la registrazione aperta, la giocata in questione non sarà salvata e non sarà conteggiata nelle segnalazioni. Sicuro?');">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/></svg>
            Cancella la registrazione precedente
        </button>
    </form>
<?php

elseif ($num_log === 0):
    $now = date('Y-m-d H:i:s');
    $cur_d = (int)date('d', strtotime($now));
    $cur_m = (int)date('m', strtotime($now));
    $cur_y = (int)date('Y', strtotime($now));
    $cur_h = (int)date('H', strtotime($now));
    ?>
    <section class="gdrcd-card">
        <div class="gdrcd-card-header">
            <h3 class="gdrcd-h3">Avvia registrazione <span class="text-gdrcd-accent">ora</span></h3>
        </div>
        <div class="gdrcd-card-body space-y-3">
            <p class="gdrcd-prose">
                <strong>Attenzione:</strong> ogni giocatore deve inviare la propria registrazione.
                La registrazione va avviata all'inizio della giocata e chiusa alla fine per essere valida.
                Giocate con meno di <strong><?= REG_MIN_AZIONI ?></strong> azioni non saranno considerate segnalabili.
            </p>
            <form action="<?= htmlspecialchars($panel_url) ?>" method="post">
                <?= gdrcd_csrf_field() ?>
                <input type="hidden" name="op" value="start_segn"/>
                <button type="submit" class="gdrcd-btn-primary w-full">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Avvia registrazione
                </button>
            </form>
        </div>
    </section>

    <div class="flex items-center gap-3 my-2">
        <div class="flex-1 h-px bg-gdrcd-border"></div>
        <span class="text-gdrcd-muted text-xs uppercase tracking-wide">oppure</span>
        <div class="flex-1 h-px bg-gdrcd-border"></div>
    </div>

    <section class="gdrcd-card">
        <div class="gdrcd-card-header">
            <h3 class="gdrcd-h3">Avvia da una data di inizio specifica</h3>
            <p class="gdrcd-muted text-xs">Massimo 6 ore indietro. Per giocate più vecchie usa <em>Scheda &gt; Giocate registrate</em>.</p>
        </div>
        <div class="gdrcd-card-body space-y-4">
            <form action="<?= htmlspecialchars($panel_url) ?>" method="post" class="space-y-3">
                <?= gdrcd_csrf_field() ?>
                <div>
                    <label class="gdrcd-label">Data</label>
                    <div class="grid grid-cols-3 gap-2">
                        <select class="gdrcd-select" name="day">
                            <?php for ($i = 1; $i <= 31; $i++): ?>
                                <option value="<?= $i ?>" <?= ($i === $cur_d) ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                        <select class="gdrcd-select" name="month">
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>" <?= ($i === $cur_m) ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                        <select class="gdrcd-select" name="year">
                            <?php for ($i = 2021; $i <= (int)date('Y') + 20; $i++): ?>
                                <option value="<?= $i ?>" <?= ($i === $cur_y) ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="gdrcd-label">Ora</label>
                    <div class="grid grid-cols-2 gap-2 max-w-xs">
                        <select class="gdrcd-select" name="hour">
                            <?php for ($i = 0; $i <= 23; $i++): ?>
                                <option value="<?= $i ?>" <?= ($i === $cur_h) ? 'selected' : '' ?>><?= sprintf('%02d', $i) ?></option>
                            <?php endfor; ?>
                        </select>
                        <select class="gdrcd-select" name="minut">
                            <?php for ($i = 0; $i < 60; $i += 5): ?>
                                <option value="<?= $i ?>"><?= sprintf('%02d', $i) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <input type="hidden" name="op" value="start_ret"/>
                <button type="submit" class="gdrcd-btn-secondary w-full">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Avvia registrazione retroattiva
                </button>
            </form>
        </div>
    </section>
<?php

elseif ($num_log > 0 && $row['stanza'] === $_SESSION['luogo']):
    $partecipanti = gdrcd_query(
        "SELECT chat.mittente FROM chat
         INNER JOIN mappa ON mappa.id = chat.stanza
         LEFT JOIN personaggio ON personaggio.nome = chat.mittente
         WHERE stanza = " . gdrcd_filter('num', $_SESSION['luogo']) . "
           AND ora >= '" . $row['data_inizio'] . "' AND ora <= NOW()
           AND (tipo = 'A' OR tipo = 'P' OR tipo = 'M' OR tipo = 'N')
         GROUP BY mittente ORDER BY ora",
        'result'
    );
    ?>
    <section class="gdrcd-card">
        <div class="gdrcd-card-header">
            <h3 class="gdrcd-h3">Chiudi e segnala la giocata</h3>
            <p class="gdrcd-muted text-xs">Ogni giocatore deve inviare la propria registrazione.</p>
        </div>
        <div class="gdrcd-card-body">
            <form action="<?= htmlspecialchars($panel_url) ?>" method="post" class="space-y-4">
                <?= gdrcd_csrf_field() ?>

                <div>
                    <div class="gdrcd-label">Partecipanti</div>
                    <div class="flex flex-wrap gap-x-3 gap-y-1.5 pt-1">
                        <?php while ($p = gdrcd_query($partecipanti, 'fetch')): ?>
                            <label class="inline-flex items-center gap-2 text-sm text-gdrcd-text-soft cursor-pointer">
                                <input type="checkbox" checked name="parte[]"
                                       value="<?= gdrcd_filter('out', $p['mittente']) ?>"
                                       class="rounded border-gdrcd-border text-gdrcd-accent focus:ring-gdrcd-accent-ring"/>
                                <span><?= gdrcd_filter('out', $p['mittente']) ?></span>
                            </label>
                        <?php endwhile;
                        gdrcd_query($partecipanti, 'free');
                        ?>
                    </div>
                </div>

                <div>
                    <label class="gdrcd-label" for="sr_tags">Tag</label>
                    <input class="gdrcd-input" type="text" id="sr_tags" name="ab"/>
                    <p class="gdrcd-help">I tag aiutano a ritrovare rapidamente una giocata.</p>
                </div>

                <div>
                    <label class="gdrcd-label" for="sr_quest">Note quest</label>
                    <input class="gdrcd-input" type="text" id="sr_quest" name="quest"/>
                    <p class="gdrcd-help">Brevissimo riassunto, focalizzato su interazioni e spunti di trama.</p>
                </div>

                <div class="flex flex-col-reverse sm:flex-row gap-3 sm:justify-end pt-2 border-t border-gdrcd-border">
                    <button type="submit" name="op" value="leave" class="gdrcd-btn-danger"
                            onclick="return confirm('Cancellando la registrazione aperta, la giocata in questione non sarà salvata. Sicuro?');">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/></svg>
                        Cancella
                    </button>
                    <button type="submit" name="op" value="send_segn" class="gdrcd-btn-primary">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        Registra la giocata
                    </button>
                </div>
            </form>
        </div>
    </section>
<?php endif; ?>
