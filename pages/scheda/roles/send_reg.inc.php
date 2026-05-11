<?php
/**
 * Scheda PG — handler invio nuova registrazione giocata.
 */

$pg_url = gdrcd_filter('url', $_REQUEST['pg']);

$render_back = function () use ($pg_url, $MESSAGE) { ?>
    <div>
        <a href="main.php?page=scheda_roles&pg=<?= $pg_url ?>" class="gdrcd-btn-ghost">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <?= gdrcd_filter('out', $MESSAGE['interface']['sheet']['link']['back_roles']) ?>
        </a>
    </div>
<?php };

if ($_REQUEST['pg'] != $_SESSION['login']) {
    echo '<div class="gdrcd-alert-error">Non puoi inserire registrazioni nella scheda altrui.</div>';
    $render_back();
    return;
}

$date_a = gdrcd_filter('num', $_POST['anno']) . '-'
    . sprintf('%02d', gdrcd_filter('num', $_POST['month_a'])) . '-'
    . sprintf('%02d', gdrcd_filter('num', $_POST['day_a'])) . ' '
    . sprintf('%02d', gdrcd_filter('num', $_POST['hour_a'])) . ':'
    . sprintf('%02d', gdrcd_filter('num', $_POST['minut_a'])) . ':00';
$date_b = gdrcd_filter('num', $_POST['anno']) . '-'
    . sprintf('%02d', gdrcd_filter('num', $_POST['month_b'])) . '-'
    . sprintf('%02d', gdrcd_filter('num', $_POST['day_b'])) . ' '
    . sprintf('%02d', gdrcd_filter('num', $_POST['hour_b'])) . ':'
    . sprintf('%02d', gdrcd_filter('num', $_POST['minut_b'])) . ':00';

$luogo_id = gdrcd_filter('num', $_POST['luogo']);
$pg_in    = gdrcd_filter('in', $_REQUEST['pg']);
$tags_in  = gdrcd_filter('in', $_POST['ab'] ?? '');
$quest_in = gdrcd_filter('in', $_POST['quest'] ?? '');

$query = gdrcd_query(
    "SELECT chat.id, chat.mittente
     FROM chat
     INNER JOIN mappa ON mappa.id = chat.stanza
     LEFT JOIN personaggio ON personaggio.nome = chat.mittente
     WHERE stanza = " . $luogo_id . "
       AND ora >= '" . gdrcd_filter('in', $date_a) . "'
       AND ora <= '" . gdrcd_filter('in', $date_b) . "'
       AND (tipo = 'A' OR tipo = 'P' OR tipo = 'M' OR tipo = 'N')
     GROUP BY mittente ORDER BY ora",
    'result'
);
$tot = gdrcd_query($query, 'num_rows');

$message = '';
$ok = false;

if ($tot == 0) {
    $message = 'Non puoi segnalare questa giocata.';
} else {
    $part = [];
    while ($prow = gdrcd_query($query, 'fetch')) {
        $part[] = $prow['mittente'];
    }
    $listapart = implode(',', $part);
    $total = count($part);

    $start = gdrcd_query(
        "SELECT chat.id, chat.mittente, chat.destinatario, chat.tipo, chat.ora
         FROM chat
         INNER JOIN mappa ON mappa.id = chat.stanza
         LEFT JOIN personaggio ON personaggio.nome = chat.mittente
         WHERE stanza = " . $luogo_id . "
           AND ora >= '" . gdrcd_filter('in', $date_a) . "'
           AND ora <= '" . gdrcd_filter('in', $date_b) . "'
           AND mittente = '" . $pg_in . "'
           AND (tipo = 'A' OR tipo = 'P' OR tipo = 'M' OR tipo = 'N')
         ORDER BY ora",
        'result'
    );
    $num_az = gdrcd_query($start, 'num_rows');

    $time_end = gdrcd_query(
        "SELECT chat.ora FROM chat
         INNER JOIN mappa ON mappa.id = chat.stanza
         LEFT JOIN personaggio ON personaggio.nome = chat.mittente
         WHERE stanza = " . $luogo_id . "
           AND ora >= '" . gdrcd_filter('in', $date_a) . "'
           AND ora <= '" . gdrcd_filter('in', $date_b) . "'
           AND mittente = '" . $pg_in . "'
           AND (tipo = 'A' OR tipo = 'P' OR tipo = 'M' OR tipo = 'N')
         ORDER BY ora DESC LIMIT 1",
        'result'
    );
    $rte = gdrcd_query($time_end, 'fetch');
    $end_time = $rte ? date('Y-m-d H:i:s', strtotime('+1 hours', strtotime($rte['ora']))) : $date_b;

    $time_start = gdrcd_query(
        "SELECT chat.ora FROM chat
         INNER JOIN mappa ON mappa.id = chat.stanza
         LEFT JOIN personaggio ON personaggio.nome = chat.mittente
         WHERE stanza = " . $luogo_id . "
           AND ora >= '" . gdrcd_filter('in', $date_a) . "'
           AND ora <= '" . gdrcd_filter('in', $date_b) . "'
           AND mittente = '" . $pg_in . "'
           AND (tipo = 'A' OR tipo = 'P' OR tipo = 'M' OR tipo = 'N')
         ORDER BY ora ASC LIMIT 1",
        'result'
    );
    $rts = gdrcd_query($time_start, 'fetch');
    $start_time = $rts ? date('Y-m-d H:i:s', strtotime('-1 hours', strtotime($rts['ora']))) : $date_a;

    $diff = abs(strtotime($date_b) - strtotime($date_a)) / 3600;

    $segnal = gdrcd_query(
        "SELECT id FROM segnalazione_role
         WHERE data_inizio >= '" . gdrcd_filter('in', $date_a) . "'
           AND data_fine <= '" . gdrcd_filter('in', $date_b) . "'
           AND mittente = '" . $pg_in . "'
           AND (conclusa = 1 OR conclusa = 0)",
        'result'
    );
    $r_seg = gdrcd_query($segnal, 'num_rows');

    if ($total == 1) {
        $message = 'Non puoi segnalare una giocata con un solo partecipante.';
    } elseif ($num_az < REG_MIN_AZIONI) {
        $message = 'Non hai inviato azioni sufficienti ad una registrazione.';
    } elseif ($r_seg > 0) {
        $message = "C'è già una registrazione che combacia con queste date.";
    } elseif ($diff > 12) {
        $message = 'Hai scelto un range di tempo troppo ampio.';
    } elseif ($date_b < $date_a) {
        $message = 'Seleziona le date correttamente.';
    } else {
        $insert_a = $date_a;
        $insert_b = $date_b;
        $info_msg = 'Registrazione inviata con successo.';
        if ($date_b > $end_time && $date_a < $start_time) {
            $insert_a = $rts['ora'];
            $insert_b = $rte['ora'];
            $info_msg = 'Registrazione salvata sulla base della tua prima ed ultima azione in chat.';
        } elseif ($date_b > $end_time) {
            $insert_b = $rte['ora'];
            $info_msg = 'Registrazione salvata sulla base della tua ultima azione in chat.';
        } elseif ($date_a < $start_time) {
            $insert_a = $rts['ora'];
            $info_msg = 'Registrazione salvata sulla base della tua prima azione in chat.';
        }
        gdrcd_query(
            "INSERT INTO segnalazione_role (data_inizio, data_fine, mittente, partecipanti, stanza, conclusa, tags, quest)
             VALUES ('" . gdrcd_filter('in', $insert_a) . "',
                     '" . gdrcd_filter('in', $insert_b) . "',
                     '" . $pg_in . "',
                     '" . gdrcd_filter('in', $listapart) . "',
                     " . $luogo_id . ", 1,
                     '" . $tags_in . "',
                     '" . $quest_in . "')"
        );
        $message = $info_msg;
        $ok = true;
    }
}
?>

<div class="<?= $ok ? 'gdrcd-alert-success' : 'gdrcd-alert-warning' ?>">
    <?php if ($ok): ?>
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
    <?php else: ?>
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
    <?php endif; ?>
    <div><?= htmlspecialchars($message) ?></div>
</div>

<?php $render_back(); ?>
