<?php
/**
 * Scheda PG — log moderazione (login, multi-account, messaggi, cambi nome).
 * Solo MODERATOR+.
 */

if ((int)$_SESSION['permessi'] < MODERATOR) {
    echo '<div class="gdrcd-alert-error">' . gdrcd_filter('out', $MESSAGE['error']['not_allowed']) . '</div>';
    return;
}

if (!isset($_REQUEST['pg'])) {
    echo '<div class="gdrcd-alert-error">' . gdrcd_filter('out', $MESSAGE['error']['unknown_character_sheet']) . '</div>';
    return;
}

$pg = $_REQUEST['pg'];
$check = gdrcd_query("SELECT nome FROM personaggio WHERE nome = '" . gdrcd_filter('in', $pg) . "'", 'result');
if (gdrcd_query($check, 'num_rows') === 0) {
    echo '<div class="gdrcd-alert-error">' . gdrcd_filter('out', $MESSAGE['error']['unknown_character_sheet']) . '</div>';
    return;
}
gdrcd_query($check, 'free');

$num_logs = (int)($PARAMETERS['settings']['view_logs'] ?? 20);
$lbl      = $MESSAGE['interface']['sheet']['log'];

/** Renderer compatto di tabella log. */
$render_table = function (string $title, $result, array $cols, callable $row_fn): void {
    if ((int)gdrcd_query($result, 'num_rows') === 0) return;
    ?>
    <section class="space-y-2">
        <h3 class="gdrcd-h3"><?= htmlspecialchars($title) ?></h3>
        <div class="gdrcd-table-wrap">
            <table class="gdrcd-table">
                <thead>
                    <tr><?php foreach ($cols as $c): ?><th class="<?= $c['cls'] ?? '' ?>"><?= htmlspecialchars($c['label']) ?></th><?php endforeach; ?></tr>
                </thead>
                <tbody>
                    <?php while ($r = gdrcd_query($result, 'fetch')): ?>
                        <tr><?= $row_fn($r) ?></tr>
                    <?php endwhile;
                    gdrcd_query($result, 'free');
                    ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php
};
?>

<div class="space-y-6">
    <header class="space-y-2">
        <h2 class="gdrcd-h1">
            <?= gdrcd_filter('out', $lbl['page_name']) ?>
            <span class="text-gdrcd-accent">·</span>
            <span class="text-gdrcd-text-soft text-2xl"><?= gdrcd_filter('out', $pg) ?></span>
        </h2>
    </header>

    <nav class="flex flex-wrap gap-2 border-b border-gdrcd-border pb-3" aria-label="Sezioni scheda">
        <?php include 'scheda/menu.inc.php'; ?>
    </nav>

    <?php
    $r_login = gdrcd_query("SELECT descrizione_evento, data_evento FROM log WHERE nome_interessato = '" . gdrcd_filter('in', $pg) . "' AND codice_evento = " . LOGGEDIN . " ORDER BY data_evento DESC LIMIT " . $num_logs, 'result');
    $render_table('Ultimi accessi', $r_login,
        [['label' => $lbl['date'], 'cls' => 'whitespace-nowrap w-44'], ['label' => $lbl['ip']]],
        fn ($r) => '<td class="text-gdrcd-muted whitespace-nowrap">' . gdrcd_format_date($r['data_evento']) . ' ' . gdrcd_format_time($r['data_evento']) . '</td>'
                 . '<td class="font-mono text-xs">' . gdrcd_filter('out', $r['descrizione_evento']) . '</td>'
    );

    $r_dup = gdrcd_query("SELECT descrizione_evento, data_evento FROM log WHERE nome_interessato = '" . gdrcd_filter('in', $pg) . "' AND codice_evento = " . ACCOUNTMULTIPLO . " ORDER BY data_evento DESC LIMIT " . $num_logs, 'result');
    $render_table('Account multipli', $r_dup,
        [['label' => $lbl['date'], 'cls' => 'whitespace-nowrap w-44'], ['label' => $lbl['other_account']]],
        fn ($r) => '<td class="text-gdrcd-muted whitespace-nowrap">' . gdrcd_format_date($r['data_evento']) . ' ' . gdrcd_format_time($r['data_evento']) . '</td>'
                 . '<td>' . gdrcd_filter('out', $r['descrizione_evento']) . '</td>'
    );

    if (($PARAMETERS['mode']['spymessages'] ?? 'OFF') === 'ON') {
        $r_msg = gdrcd_query("SELECT destinatario, spedito, testo FROM backmessaggi WHERE mittente = '" . gdrcd_filter('in', $pg) . "' ORDER BY spedito DESC LIMIT " . $num_logs, 'result');
        $render_table('Ultimi messaggi inviati', $r_msg,
            [['label' => $lbl['date'], 'cls' => 'whitespace-nowrap w-44'], ['label' => $lbl['message']]],
            fn ($r) => '<td class="text-gdrcd-muted whitespace-nowrap">' . gdrcd_format_date($r['spedito']) . ' ' . gdrcd_format_time($r['spedito']) . '</td>'
                     . '<td><a class="gdrcd-link mr-1" href="main.php?page=scheda&pg=' . urlencode($r['destinatario']) . '">' . gdrcd_filter('out', $r['destinatario']) . '</a> '
                     . gdrcd_filter('out', $r['testo']) . '</td>'
        );
    }

    $r_name = gdrcd_query("SELECT descrizione_evento, data_evento, autore FROM log WHERE nome_interessato = '" . gdrcd_filter('in', $pg) . "' AND codice_evento = " . CHANGEDNAME . " ORDER BY data_evento DESC LIMIT " . $num_logs, 'result');
    $render_table('Cambi nome', $r_name,
        [['label' => $lbl['date'], 'cls' => 'whitespace-nowrap w-44'], ['label' => $lbl['author'], 'cls' => 'whitespace-nowrap'], ['label' => $lbl['name_change']]],
        fn ($r) => '<td class="text-gdrcd-muted whitespace-nowrap">' . gdrcd_format_date($r['data_evento']) . ' ' . gdrcd_format_time($r['data_evento']) . '</td>'
                 . '<td class="whitespace-nowrap">' . gdrcd_filter('out', $r['autore']) . '</td>'
                 . '<td>' . gdrcd_filter('out', $r['descrizione_evento']) . '</td>'
    );
    ?>
</div>
